<?php
// pallet_management.php
require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Role restriction: Admin and Staff only
$role = $_SESSION['role'] ?? '';
$is_staff = ($role === 'admin' || $role === 'staff');
if (!$is_staff) {
    http_response_code(403);
    echo '<div style="font-family: sans-serif; text-align: center; padding: 100px 20px;">
            <h1 style="color: #e74c3c;">🚫 Access Denied!</h1>
            <p>You do not have permission to access the Pallet Management portal.</p>
            <a href="index.php" style="color: #3498db; font-weight: bold; text-decoration: none;">Back to Dashboard</a>
          </div>';
    exit;
}

$message = "";

// Handle Manual Adjustment
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'adjust') {
    $pallet_code = htmlspecialchars(strip_tags($_POST['pallet_code'] ?? ''));
    $adj_type    = $_POST['adj_type'] ?? 'add';
    $qty_val     = (int)($_POST['qty'] ?? 0);
    $notes       = htmlspecialchars(strip_tags($_POST['notes'] ?? ''));

    if (empty($pallet_code) || $qty_val <= 0) {
        $message = '<div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-x-circle-fill me-2"></i>Please complete all fields correctly.</div>';
    } else {
        try {
            $pdo->beginTransaction();

            // Calculate current net balance
            $stmtBal = $pdo->prepare("
                SELECT SUM(CASE 
                    WHEN transaction_type = 'IN' THEN qty 
                    WHEN transaction_type = 'OUT' THEN -qty 
                    ELSE qty 
                END) as balance 
                FROM pallet_ledger 
                WHERE pallet_code = ?
            ");
            $stmtBal->execute([$pallet_code]);
            $current_balance = (int)($stmtBal->fetch(PDO::FETCH_ASSOC)['balance'] ?? 0);

            $final_qty = 0;
            if ($adj_type === 'add') {
                $final_qty = $qty_val;
            } elseif ($adj_type === 'sub') {
                $final_qty = -$qty_val;
            } elseif ($adj_type === 'set') {
                $final_qty = $qty_val - $current_balance;
            }

            // Insert adjustment row
            $stmtAdj = $pdo->prepare("
                INSERT INTO pallet_ledger (transaction_type, pallet_code, qty, notes, reference_no) 
                VALUES ('ADJUSTMENT', ?, ?, ?, 'Manual Adjustment')
            ");
            $stmtAdj->execute([$pallet_code, $final_qty, $notes]);

            // System Log
            if (function_exists('log_system_activity')) {
                log_system_activity("Adjusted Pallet Stock", "pallet_ledger", null, "Pallet $pallet_code adjusted: $adj_type $qty_val. Notes: $notes");
            }

            $pdo->commit();
            $message = '<div class="alert alert-success border-0 shadow-sm"><i class="bi bi-check-circle-fill me-2"></i>Adjustment saved successfully!</div>';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = '<div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i>Error: ' . $e->getMessage() . '</div>';
        }
    }
}

// Fetch all pallet types
$pallet_types = [];
try {
    $pallet_types = $pdo->query("SELECT * FROM pallet_types ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Calculate KPI Metrics for each pallet type
$pallet_summary = [];
foreach ($pallet_types as $pt) {
    $code = $pt['code'];
    $name = $pt['name'];

    // 1. Total IN
    $stmtIn = $pdo->prepare("SELECT SUM(qty) as total FROM pallet_ledger WHERE pallet_code = ? AND transaction_type = 'IN'");
    $stmtIn->execute([$code]);
    $total_in = (int)($stmtIn->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    // 2. Total OUT
    $stmtOut = $pdo->prepare("SELECT SUM(qty) as total FROM pallet_ledger WHERE pallet_code = ? AND transaction_type = 'OUT'");
    $stmtOut->execute([$code]);
    $total_out = (int)($stmtOut->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    // 3. Net Balance (Total physically in warehouse)
    $stmtNet = $pdo->prepare("
        SELECT SUM(CASE 
            WHEN transaction_type = 'IN' THEN qty 
            WHEN transaction_type = 'OUT' THEN -qty 
            ELSE qty 
        END) as net 
        FROM pallet_ledger 
        WHERE pallet_code = ?
    ");
    $stmtNet->execute([$code]);
    $net_balance = (int)($stmtNet->fetch(PDO::FETCH_ASSOC)['net'] ?? 0);

    // 4. Loaded Pallets (Currently holds stock)
    $stmtLoaded = $pdo->prepare("SELECT COUNT(*) as loaded FROM inventory_batches WHERE qty_on_hand > 0 AND pallet_type = ?");
    $stmtLoaded->execute([$name]);
    $loaded_pallets = (int)($stmtLoaded->fetch(PDO::FETCH_ASSOC)['loaded'] ?? 0);

    // 5. Empty Pallets (Net Balance - Loaded)
    $empty_pallets = max(0, $net_balance - $loaded_pallets);

    $pallet_summary[] = [
        'code' => $code,
        'name' => $name,
        'in' => $total_in,
        'out' => $total_out,
        'net' => $net_balance,
        'loaded' => $loaded_pallets,
        'empty' => $empty_pallets
    ];
}

// Fetch Full Movement History Ledger
$ledger_history = [];
try {
    $ledger_history = $pdo->query("
        SELECT l.*, p.name as pallet_name 
        FROM pallet_ledger l
        LEFT JOIN pallet_types p ON l.pallet_code = p.code
        ORDER BY l.transaction_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$page_title = 'Pallet Management & Monitor';
require_once 'includes/header.php';
?>

<div class="container-fluid px-5 mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-800 mb-0" data-lang="nav_pallet_monitor"><i class="bi bi-grid-3x3-gap-fill me-2 text-primary"></i>Pallet Monitor & Management</h2>
            <p class="text-muted mb-0 small text-uppercase" data-lang="pallet_subtitle">Real-time assets tracking, empty pallet stocks & movement logs</p>
        </div>
        <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#adjustModal">
            <i class="bi bi-sliders me-1"></i> <span data-lang="btn_adjust_pallet">Manual Adjustment</span>
        </button>
    </div>

    <?= $message ?>

    <!-- KPI Metrics Section -->
    <div class="row g-4 mb-5">
        <?php 
        $colors = ['primary', 'danger', 'success', 'warning', 'info', 'dark'];
        $icons  = ['🟤', '🔴', '🟢', '🟠', '🟢', '⚫'];
        foreach ($pallet_summary as $index => $summary): 
            $c = $colors[$index % count($colors)];
            $i = $icons[$index % count($icons)];
        ?>
        <div class="col-xl-4 col-md-6">
            <div class="card h-100 border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-header bg-white border-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark"><?= $i ?> <?= htmlspecialchars($summary['name']) ?></h5>
                    <span class="badge bg-<?= $c ?>-subtle text-<?= $c ?> fw-bold text-uppercase"><?= htmlspecialchars($summary['code']) ?></span>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-3">
                        <div class="col-4 border-end">
                            <span class="text-muted small d-block" data-lang="lbl_pallet_total">Total Balance</span>
                            <h3 class="fw-800 text-<?= $c ?> mb-0"><?= $summary['net'] ?></h3>
                        </div>
                        <div class="col-4 border-end">
                            <span class="text-muted small d-block" data-lang="lbl_pallet_loaded">Loaded</span>
                            <h3 class="fw-800 text-dark mb-0"><?= $summary['loaded'] ?></h3>
                        </div>
                        <div class="col-4">
                            <span class="text-muted small d-block" data-lang="lbl_pallet_empty">Empty</span>
                            <h3 class="fw-800 text-success mb-0"><?= $summary['empty'] ?></h3>
                        </div>
                    </div>
                    <div class="bg-light p-2 rounded-3 d-flex justify-content-around small">
                        <span class="text-muted">Total IN: <strong class="text-dark"><?= $summary['in'] ?></strong></span>
                        <span class="text-muted">|</span>
                        <span class="text-muted">Total OUT: <strong class="text-dark"><?= $summary['out'] ?></strong></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Ledger Transaction Log -->
    <div class="card border-0 shadow-sm mb-5" style="border-radius: 16px;">
        <div class="card-header bg-dark text-white p-3 d-flex justify-content-between align-items-center" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
            <h5 class="mb-0 fw-bold"><i class="bi bi-list-columns me-2"></i><span data-lang="pallet_history_title">Pallet Movement Ledger History</span></h5>
        </div>
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle w-100" id="ledgerTable">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3" data-lang="lbl_date">Date & Time</th>
                            <th data-lang="lbl_pallet_type">Pallet Type</th>
                            <th data-lang="lbl_trans_type">Trans Type</th>
                            <th class="text-center" data-lang="lbl_qty">Quantity</th>
                            <th data-lang="lbl_ref_no">Ref No</th>
                            <th data-lang="lbl_remarks">Remarks / Notes</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach ($ledger_history as $row): 
                            $badge_class = 'bg-secondary';
                            if ($row['transaction_type'] === 'IN') $badge_class = 'bg-success';
                            if ($row['transaction_type'] === 'OUT') $badge_class = 'bg-danger';
                            if ($row['transaction_type'] === 'ADJUSTMENT') $badge_class = 'bg-warning text-dark';
                        ?>
                        <tr>
                            <td class="ps-3 fw-bold text-secondary"><?= date('d/m/Y H:i:s', strtotime($row['transaction_date'])) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($row['pallet_name']) ?></td>
                            <td><span class="badge <?= $badge_class ?> fw-bold"><?= $row['transaction_type'] ?></span></td>
                            <td class="text-center fw-bold <?= $row['qty'] < 0 ? 'text-danger' : 'text-success' ?>">
                                <?= ($row['qty'] > 0 ? '+' : '') . $row['qty'] ?>
                            </td>
                            <td class="fw-bold text-primary"><?= htmlspecialchars($row['reference_no'] ?: '-') ?></td>
                            <td class="text-muted"><?= htmlspecialchars($row['notes'] ?: '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Manual Adjustment Modal -->
<div class="modal fade" id="adjustModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="pallet_management.php" method="POST" class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <input type="hidden" name="action" value="adjust">
            <div class="modal-header bg-dark text-white py-3" style="border-top-left-radius: 20px; border-top-right-radius: 20px;">
                <h5 class="modal-title fw-bold"><i class="bi bi-sliders me-2"></i><span data-lang="modal_adjust_title">Pallet Manual Adjustment</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold" data-lang="lbl_select_pallet">Select Pallet Type</label>
                    <select name="pallet_code" class="form-select" required>
                        <option value="">-- Choose Pallet --</option>
                        <?php foreach ($pallet_types as $pt): ?>
                            <option value="<?= htmlspecialchars($pt['code']) ?>"><?= htmlspecialchars($pt['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold" data-lang="lbl_adj_type">Adjustment Type</label>
                    <div class="d-flex gap-3 mt-1">
                        <label class="d-flex align-items-center gap-1 cursor-pointer">
                            <input type="radio" name="adj_type" value="add" checked> <span data-lang="opt_add_pcs">Add (+)</span>
                        </label>
                        <label class="d-flex align-items-center gap-1 cursor-pointer">
                            <input type="radio" name="adj_type" value="sub"> <span data-lang="opt_sub_pcs">Subtract (-)</span>
                        </label>
                        <label class="d-flex align-items-center gap-1 cursor-pointer">
                            <input type="radio" name="adj_type" value="set"> <span data-lang="opt_set_total">Set Total Balance</span>
                        </label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold" data-lang="lbl_qty">Quantity</label>
                    <input type="number" name="qty" class="form-control text-center fw-bold" min="1" placeholder="e.g. 10" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold" data-lang="lbl_remarks">Remarks / Reason</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="e.g. Broken pallet replacement, stock adjustment..." required></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 py-3" style="border-bottom-left-radius: 20px; border-bottom-right-radius: 20px;">
                <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal" data-lang="btn_cancel">Cancel</button>
                <button type="submit" class="btn btn-success fw-bold px-4" data-lang="btn_save">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- DataTables Integration -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        $('#ledgerTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search ledger..."
            }
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>
