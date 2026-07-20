<?php
// jomcha_request_stock.php
// Requisition management screen for Jomcha Outlet staff and Warehouse Admins

require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? '';
if (!in_array(strtolower($role), ['admin', 'staff', 'intern', 'pss_admin', 'staff_jomcha'])) {
    header("Location: login.php");
    exit;
}

$is_admin = (strtolower($role) === 'admin');

// Self-healing database tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS jomcha_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_date DATE NOT NULL,
        requested_by VARCHAR(100) NOT NULL,
        status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
        remarks TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_at TIMESTAMP NULL,
        processed_by VARCHAR(100) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS jomcha_request_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        product_id INT NOT NULL,
        qty_requested INT NOT NULL,
        CONSTRAINT fk_jomcha_request FOREIGN KEY (request_id) REFERENCES jomcha_requests(id) ON DELETE CASCADE,
        CONSTRAINT fk_jomcha_product FOREIGN KEY (product_id) REFERENCES products(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
} catch (PDOException $e) {
    error_log("Jomcha Requests self-healing failed: " . $e->getMessage());
}

$error_msg = '';
$success_msg = '';

// Handle Requisition Request Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_request') {
    $date = $_POST['request_date'] ?? date('Y-m-d');
    $remarks = trim($_POST['remarks'] ?? '');
    $items = $_POST['items'] ?? [];
    $username = $_SESSION['username'] ?? 'System';

    $has_valid_items = false;
    foreach ($items as $item) {
        if ((int)($item['product_id'] ?? 0) > 0 && (int)($item['qty'] ?? 0) > 0) {
            $has_valid_items = true;
            break;
        }
    }

    if (!$has_valid_items) {
        $error_lang = "jomcha_msg_err_empty";
        $error_msg = "Sila isi sekurang-kurangnya satu produk dengan kuantiti yang sah.";
    } else {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("INSERT INTO jomcha_requests (request_date, requested_by, remarks, status) VALUES (?, ?, ?, 'Pending')");
            $stmt->execute([$date, $username, $remarks]);
            $req_id = $pdo->lastInsertId();

            $stmtItem = $pdo->prepare("INSERT INTO jomcha_request_items (request_id, product_id, qty_requested) VALUES (?, ?, ?)");
            foreach ($items as $item) {
                $pid = (int)$item['product_id'];
                $qty = (int)$item['qty'];
                if ($pid > 0 && $qty > 0) {
                    $stmtItem->execute([$req_id, $pid, $qty]);
                }
            }

            // Log activity
            if (function_exists('log_system_activity')) {
                log_system_activity("Created Jomcha Request", "jomcha_requests", $req_id, "Pengguna '$username' memohon stok untuk outlet Jomcha (ID Mohon: #$req_id).");
            }

            $pdo->commit();
            $success_msg = "Permohonan stok Jomcha berjaya dihantar ke gudang untuk pengesahan!";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error_msg = "Gagal menghantar permohonan: " . $e->getMessage();
        }
    }
}

// Fetch Active Products sorted by Category and Name
$products = $pdo->query("
    SELECT id, name, category, pack_size
    FROM products
    WHERE is_active = 1
    ORDER BY category ASC, name ASC
")->fetchAll();

$grouped_products = [];
foreach ($products as $p) {
    $cat = trim($p['category']) ?: 'Lain-lain';
    $grouped_products[$cat][] = $p;
}

// Fetch Requests
if ($is_admin) {
    // Admin sees everything
    $requests = $pdo->query("
        SELECT r.*, 
               (SELECT COUNT(*) FROM jomcha_request_items WHERE request_id = r.id) as item_count,
               (SELECT SUM(qty_requested) FROM jomcha_request_items WHERE request_id = r.id) as total_qty
        FROM jomcha_requests r
        ORDER BY CASE r.status WHEN 'Pending' THEN 1 WHEN 'Approved' THEN 2 ELSE 3 END, r.id DESC
    ")->fetchAll();
} else {
    // Storekeeper / Jomcha staff only sees their own requests
    $requests_stmt = $pdo->prepare("
        SELECT r.*, 
               (SELECT COUNT(*) FROM jomcha_request_items WHERE request_id = r.id) as item_count,
               (SELECT SUM(qty_requested) FROM jomcha_request_items WHERE request_id = r.id) as total_qty
        FROM jomcha_requests r
        WHERE r.requested_by = ?
        ORDER BY r.id DESC
    ");
    $requests_stmt->execute([$_SESSION['username']]);
    $requests = $requests_stmt->fetchAll();
}

$page_title = 'Jomcha Requisitions | MMS';
require_once 'includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    body { background-color: #f8fafc; }
    .page-header {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        padding: 40px 0 35px;
        border-bottom: 5px solid #34d399;
        box-shadow: 0 4px 20px rgba(16, 185, 129, 0.2);
    }
    .custom-card {
        background: #ffffff;
        border-radius: 20px;
        box-shadow: 0 15px 35px -5px rgba(109, 40, 217, 0.06), 0 5px 15px -3px rgba(0, 0, 0, 0.02);
        border: 1px solid rgba(139, 92, 246, 0.08);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .custom-card:hover {
        box-shadow: 0 20px 40px -5px rgba(109, 40, 217, 0.1), 0 8px 20px -3px rgba(0, 0, 0, 0.04);
    }
    .form-control, .form-select {
        border: 1px solid #ddd6fe;
        border-radius: 12px !important;
        padding: 10px 14px;
        font-size: 0.95rem;
        transition: all 0.2s;
    }
    .form-control:focus, .form-select:focus {
        border-color: #8b5cf6;
        box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15);
    }
    .btn-outline-light {
        border-radius: 30px;
        padding: 8px 22px;
        font-weight: 600;
        border-width: 2px;
        transition: all 0.2s;
    }
    .btn-outline-light:hover {
        background-color: white;
        color: #4c1d95;
        transform: translateY(-2px);
    }
    .btn-add-row {
        background-color: #f5f3ff;
        color: #6d28d9;
        border: 2px dashed #c084fc;
        padding: 12px;
        border-radius: 12px;
        font-weight: 700;
        width: 100%;
        transition: all 0.2s;
    }
    .btn-add-row:hover {
        background-color: #ede9fe;
        border-color: #a78bfa;
        color: #5b21b6;
    }
    .btn-primary-custom {
        background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 700;
        box-shadow: 0 4px 15px rgba(109, 40, 217, 0.25);
        transition: all 0.2s;
    }
    .btn-primary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(109, 40, 217, 0.35);
        color: white;
    }
</style>

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="fw-800 mb-1" style="font-size: 2.2rem; letter-spacing: -0.5px;"><i class="bi bi-cart-plus-fill me-2 text-warning"></i><span data-lang="jomcha_title">Jomcha Stock Requisition</span></h1>
                <p class="opacity-75 mb-0 fw-light" style="font-size: 1.05rem;" data-lang="jomcha_subtitle">Permohonan pemindahan stok dari Gudang Utama ke Kedai Jomcha</p>
            </div>
            <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> <span data-lang="jomcha_outbound_back">Dashboard</span></a>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    <?php if ($success_msg): ?>
        <div class="alert alert-success border-0 shadow-sm mb-4"><i class="bi bi-check-circle-fill me-2"></i><span data-lang="<?= isset($success_lang) ? $success_lang : 'jomcha_msg_success' ?>"><?= htmlspecialchars($success_msg ?? '') ?></span></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><span <?= isset($error_lang) && $error_lang ? 'data-lang="'.$error_lang.'"' : '' ?>><?= htmlspecialchars($error_msg ?? '') ?></span></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Permohonan Baru (Bagi Jomcha Staff) / Preview Summary (Bagi Admin) -->
        <?php if (!$is_admin): ?>
        <div class="col-lg-5">
            <form action="" method="POST" class="card custom-card p-4">
                <input type="hidden" name="action" value="create_request">
                <h5 class="fw-bold mb-3 text-navy"><i class="bi bi-pencil-square me-2 text-primary"></i><span data-lang="jomcha_form_title">Borang Permohonan Stok</span></h5>
                
                <div class="mb-3">
                    <label class="form-label small fw-bold" data-lang="jomcha_req_date">Tarikh Mohon</label>
                    <input type="date" name="request_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold" data-lang="jomcha_remarks">Nota / Ulasan</label>
                    <textarea name="remarks" class="form-control" rows="2" data-lang-placeholder="jomcha_remarks" placeholder="Cth: Penambahan stok hujung minggu"></textarea>
                </div>

                <h6 class="fw-bold text-secondary mb-2 mt-4 small" data-lang="jomcha_item_list">Senarai Item Keluar</h6>
                <div class="table-responsive">
                    <table class="table align-middle table-sm border-0" id="reqItemsTable">
                        <thead>
                            <tr class="small text-muted border-bottom">
                                <th width="70%" data-lang="jomcha_product">Produk</th>
                                <th width="20%" class="text-center" data-lang="jomcha_ctn">Ctn</th>
                                <th width="10%" class="text-center" data-lang="jomcha_action">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody id="reqItemsBody">
                            <tr>
                                <td>
                                    <select name="items[0][product_id]" class="form-select form-select-sm product-select" required>
                                        <option value="">-- Pilih Produk --</option>
                                        <?php foreach ($grouped_products as $category => $items): ?>
                                            <optgroup label="<?= htmlspecialchars(strtoupper($category)) ?>">
                                                <?php foreach ($items as $p): ?>
                                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name'] ?? '') ?> (<?= htmlspecialchars($p['pack_size'] ?? '') ?>)</option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="items[0][qty]" class="form-control form-control-sm text-center" min="1" placeholder="0" required>
                                </td>
                                <td class="text-center"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <button type="button" class="btn-add-row mb-4" onclick="addRequestRow()">
                    <i class="bi bi-plus-lg me-1"></i> <span data-lang="jomcha_add_item">Tambah Item Seterusnya</span>
                </button>

                <button type="submit" class="btn btn-primary-custom w-100 py-2.5 fw-bold"><i class="bi bi-send me-2"></i> <span data-lang="jomcha_submit_req">Hantar Permohonan</span></button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Log Permohonan Column (Staff / Admin View) -->
        <div class="<?= $is_admin ? 'col-lg-12' : 'col-lg-7' ?>">
            <div class="card custom-card p-4">
                <h5 class="fw-bold mb-3 text-navy">
                    <i class="bi bi-list-stars me-2 text-warning"></i>
                    <?php if ($is_admin): ?>
                        <span data-lang="jomcha_hist_all_title">Semua Senarai Permohonan Jomcha</span>
                    <?php else: ?>
                        <span data-lang="jomcha_hist_title">Sejarah Permohonan Anda</span>
                    <?php endif; ?>
                </h5>
                
                <div class="table-responsive">
                    <table class="table table-hover align-middle table-borderless" style="font-size: 0.9rem;">
                        <thead class="table-light">
                            <tr class="small text-muted fw-bold">
                                <th class="ps-3" width="10%" data-lang="jomcha_id">ID</th>
                                <th width="15%" data-lang="jomcha_req_date">Tarikh Mohon</th>
                                <th width="20%" data-lang="jomcha_req_by">Dipohon Oleh</th>
                                <th width="15%" class="text-center" data-lang="jomcha_item_count">Jumlah Item</th>
                                <th width="15%" class="text-center" data-lang="jomcha_total_qty">Jumlah Kuantiti</th>
                                <th width="15%" class="text-center" data-lang="jomcha_status">Status</th>
                                <th class="pe-3 text-end" width="10%" data-lang="jomcha_action">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($requests)): ?>
                                <tr><td colspan="7" class="text-center py-5 text-muted" data-lang="jomcha_empty_hist">Tiada sejarah permohonan ditemui.</td></tr>
                            <?php else: ?>
                                <?php foreach ($requests as $req): 
                                    $status_badge = 'bg-secondary';
                                    if ($req['status'] === 'Pending') $status_badge = 'bg-warning text-dark';
                                    if ($req['status'] === 'Approved') $status_badge = 'bg-success text-white';
                                    if ($req['status'] === 'Rejected') $status_badge = 'bg-danger text-white';
                                ?>
                                    <tr class="border-bottom">
                                        <td class="ps-3 fw-bold">#<?= $req['id'] ?></td>
                                        <td><?= date('d/m/Y', strtotime($req['request_date'])) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($req['requested_by'] ?? '') ?></span></td>
                                        <td class="text-center fw-semibold"><?= $req['item_count'] ?> <span data-lang="lbl_product_unit">product</span></td>
                                        <td class="text-center fw-bold text-primary"><?= number_format($req['total_qty']) ?> ctn</td>
                                        <td class="text-center">
                                            <span class="badge <?= $status_badge ?> px-3 py-1.5 rounded-pill fw-bold">
                                                <?= $req['status'] ?>
                                            </span>
                                        </td>
                                        <td class="pe-3 text-end">
                                            <?php if ($is_admin && $req['status'] === 'Pending'): ?>
                                                <button class="btn btn-sm btn-primary px-3 py-1 fw-bold rounded-pill" onclick="reviewRequest(<?= $req['id'] ?>)" data-lang="jomcha_btn_review">
                                                    Semak & Lulus
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-secondary px-3 py-1 rounded-pill" onclick="viewDetails(<?= $req['id'] ?>)" data-lang="jomcha_btn_view">
                                                    Lihat
                                                </button>
                                            <?php endif; ?>
                                        </td>
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

<!-- Modal Review / View Request Details -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title fw-bold" id="reviewModalLabel"><i class="bi bi-clipboard-check me-2 text-warning"></i><span data-lang="jomcha_modal_title">Butiran Permohonan Stok Jomcha</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row mb-3 pb-3 border-bottom">
                    <div class="col-md-4">
                        <span class="text-muted small d-block" data-lang="jomcha_modal_req_id">ID Permohonan:</span>
                        <strong class="fs-5" id="modal_req_id">#0</strong>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted small d-block" data-lang="jomcha_modal_req_by">Pemohon:</span>
                        <strong class="fs-5" id="modal_req_by">-</strong>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted small d-block" data-lang="jomcha_modal_req_date">Tarikh Permohonan:</span>
                        <strong class="fs-5" id="modal_req_date">-</strong>
                    </div>
                </div>

                <div class="mb-3">
                    <span class="text-muted small d-block" data-lang="jomcha_modal_remarks">Ulasan Pemohon:</span>
                    <em id="modal_req_remarks">-</em>
                </div>

                <h6 class="fw-bold mt-4 mb-2 text-navy" data-lang="jomcha_modal_list_title">Senarai Produk & Kuantiti</h6>
                <div class="table-responsive">
                    <table class="table align-middle table-bordered" style="font-size: 0.9rem;">
                        <thead class="table-light">
                            <tr class="fw-bold text-secondary">
                                <th data-lang="jomcha_col_prod_name">Nama Produk</th>
                                <th class="text-center" width="20%" data-lang="jomcha_col_req_qty">Kuantiti Mohon (Ctn)</th>
                                <th class="text-center" width="25%" data-lang="jomcha_col_wh_stock">Stok Sedia Ada Gudang</th>
                                <th class="text-center" width="15%" data-lang="jomcha_col_stock_status">Status Stok</th>
                            </tr>
                        </thead>
                        <tbody id="modal_items_body">
                            <!-- Dynamic Content -->
                        </tbody>
                    </table>
                </div>

                <!-- Footer Operations for Admin -->
                <div id="adminActionSection" class="mt-4 pt-3 border-top d-flex gap-2 justify-content-end d-none">
                    <button type="button" class="btn btn-outline-danger px-4 py-2 fw-bold rounded-pill" onclick="processRequest('reject')"><i class="bi bi-x-circle me-1"></i> <span data-lang="jomcha_btn_reject">Tolak Permohonan</span></button>
                    <button type="button" class="btn btn-success px-4 py-2 fw-bold rounded-pill shadow-sm" onclick="processRequest('approve')"><i class="bi bi-check-circle me-1"></i> <span data-lang="jomcha_btn_approve">Lulus & Potong Stok Gudang</span></button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="productOptionsTemplate" style="display: none;">
    <?php foreach ($grouped_products as $category => $items): ?>
        <optgroup label="<?= htmlspecialchars(strtoupper($category)) ?>">
            <?php foreach ($items as $p): ?>
                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name'] ?? '') ?> (<?= htmlspecialchars($p['pack_size'] ?? '') ?>)</option>
            <?php endforeach; ?>
        </optgroup>
    <?php endforeach; ?>
</div>

<script>
    let requestRowCount = 1;

    function initSelect2() {
        if (typeof $.fn.select2 === 'undefined') {
            console.warn("Select2 is not loaded yet or failed to load.");
            return;
        }
        // Only target product-select classes
        $('.product-select').each(function() {
            if (!$(this).hasClass("select2-hidden-accessible")) {
                $(this).select2({
                    width: '100%',
                    placeholder: '-- Pilih Produk --',
                    allowClear: true
                });
            }
        });
    }

    $(document).ready(function() {
        initSelect2();
    });

    function addRequestRow() {
        const tbody = document.getElementById('reqItemsBody');
        const optionsHtml = document.getElementById('productOptionsTemplate').innerHTML;
        
        const html = `
            <tr class="border-top">
                <td>
                    <select name="items[${requestRowCount}][product_id]" class="form-select form-select-sm product-select" required>
                        <option value="">-- Pilih Produk --</option>
                        ${optionsHtml}
                    </select>
                </td>
                <td>
                    <input type="number" name="items[${requestRowCount}][qty]" class="form-control form-control-sm text-center" min="1" placeholder="0" required>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-outline-danger btn-sm border-0" onclick="removeRequestRow(this)">
                        <i class="bi bi-trash3"></i>
                    </button>
                </td>
            </tr>`;
        tbody.insertAdjacentHTML('beforeend', html);
        requestRowCount++;

        // Re-initialize select2
        initSelect2();
    }

    function removeRequestRow(btn) {
        const row = btn.closest('tr');
        row.remove();
    }

    let activeRequestId = null;

    function reviewRequest(id) {
        console.log("reviewRequest triggered for ID:", id);
        activeRequestId = id;
        loadRequestDetails(id, true);
    }

    function viewDetails(id) {
        console.log("viewDetails triggered for ID:", id);
        activeRequestId = id;
        loadRequestDetails(id, false);
    }

    function loadRequestDetails(id, showAdminActions) {
        console.log("loadRequestDetails initiating fetch for ID:", id);
        
        // Fetch details from API
        fetch(`api/process_jomcha_request.php?action=get_details&request_id=${id}`)
            .then(res => {
                console.log("Fetch response status:", res.status);
                return res.json();
            })
            .then(data => {
                console.log("Fetch response data:", data);
                if (data.success) {
                    document.getElementById('modal_req_id').innerText = `#${data.metadata.id}`;
                    document.getElementById('modal_req_by').innerText = data.metadata.requested_by;
                    document.getElementById('modal_req_date').innerText = data.metadata.request_date;
                    document.getElementById('modal_req_remarks').innerText = data.metadata.remarks || 'Tiada nota';

                    const tbody = document.getElementById('modal_items_body');
                    tbody.innerHTML = '';

                    data.items.forEach(item => {
                        const requested = parseInt(item.qty_requested);
                        const stock = parseInt(item.qty_on_hand);
                        const is_sufficient = (stock >= requested);
                        
                        let statusText = 'Sufficient';
                        let statusLow = 'Low Stock!';
                        if (typeof MMS_LANG !== 'undefined') {
                            statusText = MMS_LANG.t('jomcha_status_sufficient');
                            statusLow = MMS_LANG.t('jomcha_status_low');
                        }
                        
                        const statusBadge = is_sufficient 
                            ? `<span class="badge bg-success-subtle text-success px-2 py-1 rounded">${statusText}</span>` 
                            : `<span class="badge bg-danger-subtle text-danger px-2 py-1 rounded">${statusLow}</span>`;
                        
                        const tr = `
                            <tr>
                                <td><strong>${item.product_name}</strong></td>
                                <td class="text-center fw-bold text-primary">${requested} ctn</td>
                                <td class="text-center fw-semibold">${stock} ctn</td>
                                <td class="text-center">${statusBadge}</td>
                            </tr>
                        `;
                        tbody.insertAdjacentHTML('beforeend', tr);
                    });

                    // Action buttons
                    const actionSection = document.getElementById('adminActionSection');
                    if (showAdminActions && data.metadata.status === 'Pending') {
                        actionSection.classList.remove('d-none');
                    } else {
                        actionSection.classList.add('d-none');
                    }

                    // Trigger modal via Vanilla Bootstrap 5 with jQuery fallback
                    try {
                        console.log("Attempting to show modal...");
                        var modalEl = document.getElementById('reviewModal');
                        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            var reviewModal = bootstrap.Modal.getInstance(modalEl);
                            if (!reviewModal) {
                                reviewModal = new bootstrap.Modal(modalEl);
                            }
                            reviewModal.show();
                            console.log("Modal shown using bootstrap.Modal");
                        } else {
                            $('#reviewModal').modal('show');
                            console.log("Modal shown using jQuery fallback");
                        }
                    } catch (modalErr) {
                        console.warn("Vanilla Bootstrap modal failed, retrying with jQuery:", modalErr);
                        $('#reviewModal').modal('show');
                    }
                } else {
                    alert(`Gagal memuatkan butiran permohonan: ${data.message}`);
                }
            })
            .catch(err => {
                console.error("Fetch/Processing error:", err);
                alert(`Ralat sambungan: ${err.message}`);
            });
    }

    function processRequest(act) {
        if (!activeRequestId) return;
        
        let confirmText = (act === 'approve') 
            ? 'Adakah anda setuju meluluskan permohonan ini? Baki stok di Warehouse akan dipotong secara FEFO secara automatik.' 
            : 'Adakah anda pasti mahu menolak permohonan ini?';
            
        Swal.fire({
            title: act === 'approve' ? 'Luluskan Permohonan?' : 'Tolak Permohonan?',
            text: confirmText,
            icon: act === 'approve' ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonColor: act === 'approve' ? '#10b981' : '#ef4444',
            cancelButtonColor: '#6c757d',
            confirmButtonText: act === 'approve' ? 'Ya, Lulus!' : 'Ya, Tolak'
        }).then((result) => {
            if (result.isConfirmed) {
                // Post action to API
                fetch('api/process_jomcha_request.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=${act}&request_id=${activeRequestId}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire(
                            act === 'approve' ? 'Diluluskan!' : 'Ditolak!',
                            data.message,
                            'success'
                        ).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire('Ralat!', data.message, 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('Ralat!', `Masalah sambungan: ${err.message}`, 'error');
                });
            }
        });
    }
</script>

<?php require_once 'includes/footer.php'; ?>
