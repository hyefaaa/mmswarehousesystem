<?php
// daily_closing_report.php - Daily Closing Stock Take Audit
// MMS Warehouse System | Moo Moo Supplies

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? '';
$is_staff = is_staff_role($role);

if (!$is_staff) {
    header('Location: login.php');
    exit;
}

$selected_date = $_GET['date'] ?? date('Y-m-d');

// 1. Check if a report already exists for this date
$stmtCheck = $pdo->prepare("SELECT * FROM daily_closing_reports WHERE audit_date = ? LIMIT 1");
$stmtCheck->execute([$selected_date]);
$existing_report = $stmtCheck->fetch();

$report_items = [];
if ($existing_report) {
    // Load saved counts
    $stmtItems = $pdo->prepare("
        SELECT 
            p.id, p.name, p.category, p.uom, p.pack_size,
            i.system_qty_ctn as total_cartons,
            i.physical_qty_ctn,
            i.variance_ctn
        FROM daily_closing_items i
        JOIN products p ON i.product_id = p.id
        WHERE i.report_id = ?
        ORDER BY p.name ASC
    ");
    $stmtItems->execute([$existing_report['id']]);
    $report_items = $stmtItems->fetchAll();
} else {
    // Fetch live system stock
    $sqlLive = "
        SELECT 
            p.id, p.name, p.category, p.uom, p.pack_size,
            COALESCE(SUM(b.qty_on_hand), 0) AS total_cartons
        FROM products p
        LEFT JOIN inventory_batches b ON p.id = b.product_id AND b.location_status = 'Warehouse'
        WHERE p.is_active = 1
        GROUP BY p.id, p.name, p.category, p.uom, p.pack_size
        ORDER BY p.name ASC
    ";
    $report_items = $pdo->query($sqlLive)->fetchAll();
}

// Function to classify products into clipboard-matched sections
function getProductSection($name) {
    $name_lower = strtolower($name);
    if (strpos($name_lower, '125ml') !== false || strpos($name_lower, '100ml') !== false) {
        return '125ml / 100ml';
    } elseif (strpos($name_lower, '200ml') !== false || strpos($name_lower, '180ml') !== false) {
        return '200ml / 180ml';
    } elseif (strpos($name_lower, '1l') !== false || strpos($name_lower, '1 liter') !== false || strpos($name_lower, '2l') !== false || strpos($name_lower, '700ml') !== false || strpos($name_lower, '568ml') !== false || strpos($name_lower, 'cream') !== false || strpos($name_lower, 'butter') !== false) {
        return '1L / Bottles / Fresh';
    } elseif (strpos($name_lower, 'powder') !== false || strpos($name_lower, 'g') !== false || strpos($name_lower, 'kg') !== false || strpos($name_lower, 'pack') !== false || strpos($name_lower, 'chocomalt') !== false) {
        return 'Powders & Others';
    }
    return 'Other Products';
}

// Group items into sections
$sections = [
    '200ml / 180ml' => [],
    '1L / Bottles / Fresh' => [],
    '125ml / 100ml' => [],
    'Powders & Others' => [],
    'Other Products' => []
];

foreach ($report_items as $item) {
    $sec = getProductSection($item['name']);
    $sections[$sec][] = $item;
}

$page_title = 'Daily Closing Audit | Moo Moo Supplies';
require_once 'includes/header.php';
?>

<style>
    .page-header {
        background: linear-gradient(135deg, var(--mms-navy, #0f172a) 0%, #1e3a5f 100%);
        color: white;
        padding: 28px 0 24px;
        border-bottom: 3px solid var(--mms-cyan, #06b6d4);
        margin-bottom: 0;
    }
    .main-card {
        border: 1px solid rgba(241, 245, 249, 0.9);
        border-radius: 18px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.04);
        background: white;
        margin-top: -3rem;
        padding: 2rem;
    }
    .section-header {
        background: #f1f5f9;
        color: #1e293b;
        font-weight: 800;
        font-size: 0.95rem;
        padding: 10px 16px;
        border-radius: 8px;
        margin-top: 1.8rem;
        margin-bottom: 1rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-left: 5px solid var(--mms-indigo, #6366f1);
    }
    .product-row:hover {
        background-color: #f8fafc;
    }
    .variance-badge {
        font-weight: bold;
        padding: 4px 8px;
        border-radius: 6px;
    }
    .variance-ok { background-color: #d1fae5; color: #065f46; }
    .variance-missing { background-color: #fee2e2; color: #991b1b; }
    .variance-extra { background-color: #dbeafe; color: #1e40af; }
    
    @media print {
        .no-print, .mms-navbar, footer, .page-header, .main-card {
            box-shadow: none !important;
            border: none !important;
            margin-top: 0 !important;
            padding: 0 !important;
        }
        .main-card {
            border: none !important;
            box-shadow: none !important;
            margin-top: 0 !important;
        }
        .form-control, .form-select {
            border: none !important;
            background: transparent !important;
            padding: 0 !important;
            text-align: center !important;
        }
        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type="number"] {
            -moz-appearance: textfield;
        }
        .print-only-input {
            border-bottom: 1px dashed #000 !important;
            display: inline-block;
            width: 80px;
            height: 20px;
        }
    }
</style>

<div class="page-header mb-4 no-print">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center flex-column flex-md-row gap-3">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-calendar2-check-fill me-2 text-warning"></i>Daily Closing Audit</h1>
                <p class="opacity-75 mb-0 fw-light">Verify system counts against physical stock before warehouse closing</p>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> Dashboard</a>
                <a href="daily_closing_history.php" class="btn btn-warning text-dark fw-bold"><i class="bi bi-grid-3x3-gap-fill me-1"></i> View History Matrix</a>
                <button type="button" class="btn btn-info text-white fw-bold" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print Sheet</button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    <div class="main-card card">
        <div class="row g-3 align-items-center mb-4 no-print">
            <div class="col-md-4">
                <label class="form-label fw-bold text-secondary">Pilih Tarikh Audit:</label>
                <input type="date" id="audit_date_picker" class="form-control fw-bold border-primary" value="<?= $selected_date ?>" onchange="changeAuditDate(this.value)">
            </div>
            <div class="col-md-8 text-md-end pt-4">
                <?php if ($existing_report): ?>
                    <span class="badge bg-success px-3 py-2 rounded-pill fs-6"><i class="bi bi-check-circle-fill me-1"></i> Telah Disahkan Oleh: <?= htmlspecialchars($existing_report['checked_by'] ?? '') ?></span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fs-6"><i class="bi bi-exclamation-triangle-fill me-1"></i> Menunggu Pengesahan Harian</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center print-header mb-4 d-none d-print-block">
            <h3>MOO MOO SUPPLIES WAREHOUSE</h3>
            <h4>DAILY CLOSING STOCK TAKE SHEET</h4>
            <h5>TARIKH: <?= date('d/m/Y', strtotime($selected_date)) ?></h5>
            <hr>
        </div>

        <form id="closingReportForm" method="POST" action="api/save_daily_closing.php">
            <input type="hidden" name="audit_date" value="<?= $selected_date ?>">

            <div class="row g-3 mb-4">
                <div class="col-md-6 col-6">
                    <label class="form-label fw-bold">Pemeriksa (Checked By) *</label>
                    <select name="checked_by" class="form-select fw-bold border-primary" required <?= $existing_report ? 'disabled' : '' ?>>
                        <option value="">-- Sila Pilih --</option>
                        <option value="SHAHRUL" <?= ($existing_report && $existing_report['checked_by'] === 'SHAHRUL') ? 'selected' : '' ?>>SHAHRUL</option>
                        <option value="FADIAH" <?= ($existing_report && $existing_report['checked_by'] === 'FADIAH') ? 'selected' : '' ?>>FADIAH</option>
                        <option value="MILA" <?= ($existing_report && $existing_report['checked_by'] === 'MILA') ? 'selected' : '' ?>>MILA</option>
                        <option value="ADMIN" <?= ($existing_report && $existing_report['checked_by'] === 'ADMIN') ? 'selected' : '' ?>>ADMIN</option>
                    </select>
                </div>
                <div class="col-md-6 col-6 text-end pt-4 no-print">
                    <?php if (!$existing_report): ?>
                        <button type="submit" class="btn btn-success btn-lg fw-bold px-5 py-3 shadow"><i class="bi bi-shield-lock-fill me-1"></i> SAVE & LOCK CLOSING</button>
                    <?php elseif ($role === 'admin'): ?>
                        <button type="button" class="btn btn-outline-danger fw-bold" onclick="unlockReport()"><i class="bi bi-unlock-fill me-1"></i> Unlock/Edit (Admin)</button>
                    <?php endif; ?>
                </div>
            </div>

            <?php foreach ($sections as $secName => $items): ?>
                <?php if (empty($items)) continue; ?>
                <div class="section-header"><?= $secName ?></div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th width="40%">Product Name</th>
                                <th width="15%" class="text-center">System Qty (Ctn)</th>
                                <th width="15%" class="text-center">System Qty (Pcs)</th>
                                <th width="15%" class="text-center">Physical Count (Ctn)</th>
                                <th width="15%" class="text-center">Variance (Ctn)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr class="product-row" id="row_<?= $item['id'] ?>">
                                    <td class="fw-bold text-dark">
                                        <?= htmlspecialchars($item['name'] ?? '') ?>
                                        <small class="text-muted d-block small" style="font-size:10px;">ID: #<?= $item['id'] ?> | UOM: <?= $item['uom'] ?> | Pack: <?= $item['pack_size'] ?> units</small>
                                    </td>
                                    <td class="text-center bg-light fw-bold" id="sys_<?= $item['id'] ?>"><?= $item['total_cartons'] ?></td>
                                    <td class="text-center text-muted"><?= $item['total_cartons'] * $item['pack_size'] ?></td>
                                    <td class="text-center">
                                        <?php if ($existing_report): ?>
                                            <span class="fw-bold"><?= $item['physical_qty_ctn'] ?></span>
                                        <?php else: ?>
                                            <input type="number" 
                                                   name="items[<?= $item['id'] ?>][physical_qty]" 
                                                   id="physical_<?= $item['id'] ?>" 
                                                   class="form-control form-control-sm text-center fw-bold border-primary mx-auto" 
                                                   style="max-width: 100px;"
                                                   placeholder="0"
                                                   oninput="calcVariance(<?= $item['id'] ?>)"
                                                   min="0">
                                            <input type="hidden" name="items[<?= $item['id'] ?>][system_qty]" value="<?= $item['total_cartons'] ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($existing_report): ?>
                                            <?php 
                                            $v = $item['variance_ctn']; 
                                            if ($v == 0) {
                                                echo '<span class="variance-badge variance-ok">✔ OK</span>';
                                            } elseif ($v < 0) {
                                                echo '<span class="variance-badge variance-missing">'.$v.' ctn</span>';
                                            } else {
                                                echo '<span class="variance-badge variance-extra">+'. $v .' ctn</span>';
                                            }
                                            ?>
                                        <?php else: ?>
                                            <span id="variance_display_<?= $item['id'] ?>" class="fw-bold text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </form>
    </div>
</div>

<script>
    function changeAuditDate(date) {
        window.location.href = '?date=' + date;
    }

    function calcVariance(id) {
        const sysVal = parseInt(document.getElementById('sys_' + id).innerText) || 0;
        const physInput = document.getElementById('physical_' + id);
        const display = document.getElementById('variance_display_' + id);
        const row = document.getElementById('row_' + id);

        if (physInput.value === '') {
            display.innerText = '-';
            display.className = 'fw-bold text-muted';
            row.style.backgroundColor = '';
            return;
        }

        const physVal = parseInt(physInput.value) || 0;
        const diff = physVal - sysVal;

        if (diff === 0) {
            display.innerText = '✔ OK';
            display.className = 'variance-badge variance-ok';
            row.style.backgroundColor = 'rgba(209, 250, 229, 0.2)';
        } else if (diff < 0) {
            display.innerText = diff + ' ctn';
            display.className = 'variance-badge variance-missing';
            row.style.backgroundColor = 'rgba(254, 226, 226, 0.2)';
        } else {
            display.innerText = '+' + diff + ' ctn';
            display.className = 'variance-badge variance-extra';
            row.style.backgroundColor = 'rgba(219, 234, 254, 0.2)';
        }
    }

    function unlockReport() {
        const date = '<?= $selected_date ?>';
        if (confirm("Adakah anda pasti mahu memadam rekod closing harian ini untuk pengisian semula?")) {
            const f = document.createElement('form');
            f.method = 'POST';
            f.action = 'api/save_daily_closing.php';
            
            const act = document.createElement('input');
            act.type = 'hidden';
            act.name = 'action';
            act.value = 'unlock';
            f.appendChild(act);
            
            const dt = document.createElement('input');
            dt.type = 'hidden';
            dt.name = 'audit_date';
            dt.value = date;
            f.appendChild(dt);
            
            document.body.appendChild(f);
            f.submit();
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>
