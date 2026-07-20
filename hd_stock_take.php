<?php
// hd_stock_take.php
// Updated: Premium design for Hub Dealer Stock Take & Distribution Tracking

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? '';
$full_name = $_SESSION['full_name'] ?? 'Pengguna';

// Ensure the table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS hd_stock_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dealer VARCHAR(50) NOT NULL,
        product_id INT NOT NULL,
        batch_no VARCHAR(50) DEFAULT NULL,
        transaction_type ENUM('Stock Take', 'Distribution') NOT NULL,
        qty_cartons INT NOT NULL DEFAULT 0,
        qty_packs INT NOT NULL DEFAULT 0,
        destination VARCHAR(255) DEFAULT NULL,
        remarks TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
} catch (PDOException $e) {
    die("Database Initialization Error: " . $e->getMessage());
}

$products = [];
$schools = [];
$logs = [];

try {
    // Get PSS products only
    $products = $pdo->query("SELECT id, name, category, pack_size FROM products WHERE is_active = 1 AND category = 'PSS' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Get schools assigned to this dealer
    try {
        $pdo->exec("USE susumura_mms_logistik");
        if (is_staff_role($role)) {
            $stmtSch = $pdo->query("SELECT DISTINCT name FROM mms_logistik ORDER BY name ASC");
        } else {
            $stmtSch = $pdo->prepare("SELECT DISTINCT name FROM mms_logistik WHERE dealer = ? ORDER BY name ASC");
            $stmtSch->execute([$username]);
        }
        $schools = $stmtSch->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $ex) {
        // Fallback if logistics db is offline or not configured
        $schools = [];
    }

    // Switch back to mmswarehousesystem for logs and general query
    $pdo->exec("USE mmswarehousesystem");

    // Get stock logs
    if (is_staff_role($role)) {
        $stmtLogs = $pdo->query("
            SELECT l.*, p.name as product_name 
            FROM hd_stock_logs l 
            JOIN products p ON l.product_id = p.id 
            ORDER BY l.created_at DESC
        ");
    } else {
        $stmtLogs = $pdo->prepare("
            SELECT l.*, p.name as product_name 
            FROM hd_stock_logs l 
            JOIN products p ON l.product_id = p.id 
            WHERE l.dealer = ? 
            ORDER BY l.created_at DESC
        ");
        $stmtLogs->execute([$username]);
    }
    $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

$page_title = 'MMS | HD Stock Take & Tracking';
require_once 'includes/header.php';
?>
<!-- Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    :root {
        --primary-color: #0b2147;
        --accent-cyan: #06b6d4;
        --bg-light: #f8fafc;
    }
    .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    }
    .card-header {
        background-color: #fff;
        border-bottom: 1px solid #f1f5f9;
        font-weight: 700;
        color: var(--primary-color);
    }
    .btn-mms-primary {
        background-color: var(--primary-color);
        color: white;
        border: none;
        font-weight: 600;
        transition: all 0.2s ease;
    }
    .btn-mms-primary:hover {
        background-color: #15305b;
        color: white;
    }
</style>

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-800 mb-1">
                    <i class="bi bi-clipboard-check-fill me-2 text-info"></i>
                    <span data-lang="hd_stock_take_title">HD Stock Take & Tracking</span>
                </h1>
                <p class="opacity-75 mb-0 fw-light" data-lang="hd_stock_take_desc">Record physical stock counts and milk batch distribution</p>
            </div>
            <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> <span data-lang="nav_dashboard">Dashboard</span></a>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    <div class="row">
        <!-- Borang Tambah Rekod -->
        <div class="col-lg-4 mb-4">
            <form id="hdStockForm" action="api/save_hd_stock.php" method="POST" class="card h-100">
                <div class="card-header py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-plus-circle-fill me-2 text-primary"></i><span data-lang="hd_stock_form_title">Log Stock Transaction</span></h5>
                </div>
                <div class="card-body bg-white">
                    <div class="mb-3">
                        <label class="form-label small fw-bold" data-lang="hd_stock_product">Product *</label>
                        <select name="product_id" id="productSelect" class="form-select w-100" required>
                            <option value="">-- Pilih Produk --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>">[<?= htmlspecialchars($p['category'] ?? '') ?>] <?= htmlspecialchars($p['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold" data-lang="hd_stock_batch">Batch No / DO</label>
                        <input type="text" name="batch_no" class="form-control" placeholder="Contoh: BANO260304 atau DO9801">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold" data-lang="hd_stock_type">Transaction Type *</label>
                        <select name="transaction_type" id="typeSelect" class="form-select" required>
                            <option value="Stock Take" data-lang="hd_stock_type_take">Stock Take (Pengiraan)</option>
                            <option value="Distribution" data-lang="hd_stock_type_dist">Agihan (Penghantaran)</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold" data-lang="hd_stock_qty_ctn">Quantity (Cartons)</label>
                            <input type="number" name="qty_cartons" class="form-control" value="0" min="0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold" data-lang="hd_stock_qty_pack">Quantity (Packs)</label>
                            <input type="number" name="qty_packs" class="form-control" value="0" min="0" required>
                        </div>
                    </div>

                    <div class="mb-3 d-none" id="destinationWrapper">
                        <label class="form-label small fw-bold" data-lang="hd_stock_destination">Destination *</label>
                        <div class="input-group">
                            <select id="destSelect" class="form-select">
                                <option value="" data-lang="hd_stock_destination_placeholder">-- Pilih Destinasi --</option>
                                <?php foreach ($schools as $sch): ?>
                                    <option value="<?= htmlspecialchars($sch ?? '') ?>"><?= htmlspecialchars($sch ?? '') ?></option>
                                <?php endforeach; ?>
                                <option value="CUSTOM">-- Destinasi Lain --</option>
                            </select>
                            <input type="text" name="destination" id="destCustomInput" class="form-control d-none" placeholder="Masukkan nama destinasi">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold" data-lang="hd_stock_remarks">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="3" placeholder="Ulasan atau catatan tambahan..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-mms-primary w-100 py-2 mt-2">
                        <i class="bi bi-save-fill me-1"></i> <span data-lang="hd_stock_btn_save">Save Stock Record</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Sejarah Transaksi -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i><span data-lang="hd_stock_history_title">Recent Stock Logs</span></h5>
                </div>
                <div class="card-body bg-white p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size:0.85rem;">
                            <thead class="table-light">
                                <tr class="text-secondary fw-bold small">
                                    <th class="ps-3" data-lang="hd_stock_history_date">Date & Time</th>
                                    <?php if (is_staff_role($role)): ?>
                                        <th>Dealer</th>
                                    <?php endif; ?>
                                    <th data-lang="hd_stock_product">Product</th>
                                    <th data-lang="hd_stock_batch">Batch No</th>
                                    <th data-lang="hd_stock_history_type">Type</th>
                                    <th class="text-center" data-lang="hd_stock_history_qty">Qty</th>
                                    <th data-lang="hd_stock_history_dest">Destination</th>
                                    <th class="pe-3" data-lang="hd_stock_remarks">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">Tiada rekod log stok ditemui.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): 
                                        $is_take = $log['transaction_type'] === 'Stock Take';
                                        $type_badge = $is_take ? 'success' : 'info';
                                        $type_label = $is_take ? 'Stock Take' : 'Distribution';
                                        $qty_str = "";
                                        if ($log['qty_cartons'] > 0) $qty_str .= $log['qty_cartons'] . " Ctn ";
                                        if ($log['qty_packs'] > 0) $qty_str .= $log['qty_packs'] . " Pcs";
                                    ?>
                                        <tr>
                                            <td class="ps-3 text-secondary font-monospace"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                                            <?php if (is_staff_role($role)): ?>
                                                <td class="fw-bold text-navy text-uppercase"><?= htmlspecialchars($log['dealer'] ?? '') ?></td>
                                            <?php endif; ?>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($log['product_name'] ?? '') ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($log['batch_no'] ?? '' ?: '-') ?></span></td>
                                            <td><span class="badge bg-<?= $type_badge ?>"><?= $type_label ?></span></td>
                                            <td class="text-center fw-bold text-primary"><?= htmlspecialchars(trim($qty_str)) ?></td>
                                            <td class="text-secondary"><?= htmlspecialchars($log['destination'] ?? '' ?: '-') ?></td>
                                            <td class="pe-3 text-muted"><?= htmlspecialchars($log['remarks'] ?? '' ?: '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize Select2 on Product select
    $('#productSelect').select2({
        placeholder: "-- Pilih Produk --",
        width: '100%'
    });

    // Handle transaction type visibility
    $('#typeSelect').on('change', function() {
        const type = $(this).val();
        if (type === 'Distribution') {
            $('#destinationWrapper').removeClass('d-none');
            $('#destSelect').prop('required', true);
        } else {
            $('#destinationWrapper').addClass('d-none');
            $('#destSelect').prop('required', false);
            $('#destCustomInput').addClass('d-none').val('').prop('required', false);
            $('#destSelect').val('');
        }
    });

    // Handle custom destination input
    $('#destSelect').on('change', function() {
        const val = $(this).val();
        if (val === 'CUSTOM') {
            $('#destCustomInput').removeClass('d-none').prop('required', true).focus();
            // Clear name attribute from select, assign to custom input
            $('#destCustomInput').attr('name', 'destination');
        } else {
            $('#destCustomInput').addClass('d-none').prop('required', false);
            // Assign name attribute to select value
            $('#destCustomInput').removeAttr('name');
            if (val) {
                // We create a hidden input or append to serialize
            }
        }
    });

    // Submit form via AJAX
    $('#hdStockForm').on('submit', function(e) {
        e.preventDefault();
        
        // Prepare data
        let formData = $(this).serializeArray();
        
        // Handle select vs custom input for destination
        if ($('#typeSelect').val() === 'Distribution') {
            const selectVal = $('#destSelect').val();
            if (selectVal && selectVal !== 'CUSTOM') {
                formData.push({ name: 'destination', value: selectVal });
            }
        }

        Swal.fire({
            title: 'Simpan Rekod?',
            text: "Adakah anda pasti mahu menyimpan rekod transaksi stok ini?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0b2147',
            confirmButtonText: 'Ya, Simpan'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Menyimpan...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Berjaya!', response.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Ralat', response.message, 'error');
                        }
                    },
                    error: function(xhr) {
                        const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Ralat sambungan pelayan.';
                        Swal.fire('Ralat', msg, 'error');
                    }
                });
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
