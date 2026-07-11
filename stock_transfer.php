<?php
// stock_transfer.php
// Pindah stok antara lokasi: Warehouse → Buffer → Shop → Damaged
// MMS Warehouse System | Moo Moo Supplies

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';

// Sekatan akses: Admin & Staff sahaja
$role = $_SESSION['role'] ?? '';
$is_staff = ($role === 'admin' || $role === 'staff');
// Note: 'admin' role covers this, check header.php
// Allow any logged-in user to view; restrict saving to admin/staff
$can_edit = ($role === 'admin' || $role === 'staff');

// --- FILTER ---
$filter_location = $_GET['from_location'] ?? 'Warehouse';
if (!in_array($filter_location, ['Warehouse', 'Buffer'])) {
    $filter_location = 'Warehouse';
}
$filter_category = $_GET['category'] ?? '';
$filter_product  = $_GET['product'] ?? '';

// --- FETCH CATEGORIES ---
$all_categories = $pdo->query("SELECT DISTINCT category FROM products WHERE is_active = 1 ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// --- FETCH BATCHES FOR SELECTED LOCATION ---
$sql = "
    SELECT b.id as batch_id, b.batch_no, b.lot_no_raw, b.qty_on_hand,
           b.location_status, b.expiry_date, b.pallet_type,
           p.name as product_name, p.category, p.uom, p.id as product_id
    FROM inventory_batches b
    JOIN products p ON b.product_id = p.id
    WHERE b.qty_on_hand > 0
";
$params = [];

if (!empty($filter_location)) {
    $sql .= " AND b.location_status = ?";
    $params[] = $filter_location;
}
if (!empty($filter_category)) {
    $sql .= " AND p.category = ?";
    $params[] = $filter_category;
}
if (!empty($filter_product)) {
    $sql .= " AND p.name LIKE ?";
    $params[] = "%$filter_product%";
}

$sql .= " ORDER BY p.category, p.name, b.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$batches = $stmt->fetchAll();

// --- LOCATION STOCK SUMMARY ---
$loc_summary = $pdo->query("
    SELECT location_status, COUNT(*) as batch_count, COALESCE(SUM(qty_on_hand),0) as total_qty
    FROM inventory_batches
    WHERE qty_on_hand > 0 AND location_status IN ('Warehouse','Buffer')
    GROUP BY location_status
    ORDER BY FIELD(location_status, 'Warehouse','Buffer')
")->fetchAll();

// Build quick lookup
$loc_totals = [];
foreach ($loc_summary as $ls) {
    $loc_totals[$ls['location_status']] = $ls['total_qty'];
}

$page_title = 'Stock Transfer | Moo Moo Supplies';
require_once 'includes/header.php';
?>

<style>
    .page-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
        color: white;
        padding: 28px 0 24px;
        border-bottom: 3px solid #06b6d4;
    }

    /* ===== LOCATION FLOW DIAGRAM ===== */
    .flow-wrap {
        background: white;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        padding: 24px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 24px;
    }
    .flow-node {
        flex: 1;
        text-align: center;
        padding: 16px 12px;
        border-radius: 14px;
        border: 2px solid;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }
    .flow-node:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.12); }
    .flow-node.active { box-shadow: 0 0 0 4px rgba(6,182,212,0.3); }
    .flow-node .fn-qty   { font-size: 1.6rem; font-weight: 800; display: block; line-height: 1; }
    .flow-node .fn-label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-top: 4px; }
    .flow-node .fn-sub   { font-size: 0.7rem; opacity: 0.7; display: block; margin-top: 2px; }

    .node-warehouse { border-color: #3b82f6; background: #eff6ff; color: #1d4ed8; }
    .node-buffer    { border-color: #f59e0b; background: #fffbeb; color: #92400e; }
    .node-shop      { border-color: #10b981; background: #ecfdf5; color: #065f46; }
    .node-damaged   { border-color: #ef4444; background: #fef2f2; color: #991b1b; }

    .flow-arrow { color: #94a3b8; font-size: 1.4rem; padding: 0 6px; align-self: center; }

    /* ===== FILTER PANEL ===== */
    .filter-panel {
        background: white;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        padding: 18px 22px;
        margin-bottom: 20px;
    }

    /* ===== BATCH TABLE ===== */
    .batch-wrap {
        background: white;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    }
    .batch-wrap .tbl-head {
        background: #f8fafc;
        padding: 14px 20px;
        border-bottom: 1px solid #e2e8f0;
    }
    .batch-table thead th {
        background: #1e293b;
        color: #94a3b8;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 12px 14px;
        border: none;
        white-space: nowrap;
    }
    .batch-table tbody tr { border-bottom: 1px solid #f1f5f9; transition: background 0.15s; }
    .batch-table tbody tr:hover { background: #f8fafc; }
    .batch-table tbody td { padding: 10px 14px; vertical-align: middle; font-size: 0.875rem; }

    /* ===== TRANSFER CONTROLS ===== */
    .qty-input {
        width: 80px;
        text-align: center;
        font-weight: 700;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        padding: 5px 8px;
        transition: border-color 0.2s;
    }
    .qty-input:focus { outline: none; border-color: #06b6d4; box-shadow: 0 0 0 3px rgba(6,182,212,0.15); }
    .qty-input.over { border-color: #ef4444; background: #fef2f2; }

    .dest-select {
        font-size: 0.8rem;
        font-weight: 600;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        padding: 5px 8px;
        cursor: pointer;
        transition: border-color 0.2s;
        min-width: 120px;
    }
    .dest-select:focus { outline: none; border-color: #06b6d4; }

    .btn-transfer-row {
        font-size: 0.78rem;
        font-weight: 700;
        padding: 5px 12px;
        border-radius: 8px;
        white-space: nowrap;
    }

    /* ===== CATEGORY GROUP ===== */
    .cat-group-row td {
        background: #1e293b;
        color: #64748b;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 7px 14px !important;
    }

    /* ===== STOCK COLOUR ===== */
    .qty-high   { color: #10b981; font-weight: 800; }
    .qty-medium { color: #f59e0b; font-weight: 700; }
    .qty-low    { color: #ef4444; font-weight: 700; }

    /* ===== LOC TAG ===== */
    .loc-tag { font-size: 0.72rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
    .loc-Warehouse { background: #dbeafe; color: #1e40af; }
    .loc-Buffer    { background: #fef9c3; color: #854d0e; }
    .loc-Shop      { background: #d1fae5; color: #065f46; }
    .loc-Damaged   { background: #fee2e2; color: #991b1b; }

    /* ===== TOAST ===== */
    .toast-wrap { position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 300px; }

    /* ===== EMPTY ===== */
    .empty-box { text-align: center; padding: 60px 20px; color: #94a3b8; }
    .empty-box i { font-size: 3rem; display: block; margin-bottom: 12px; }
</style>

<!-- PAGE HEADER -->
<div class="page-header">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="fw-800 mb-1 fs-4"><i class="bi bi-arrow-left-right me-2"></i><span data-lang="xfer_title">Stock Transfer</span></h1>
                <p class="opacity-75 mb-0 small" data-lang="xfer_subtitle">Pindah stok antara lokasi — Warehouse, Buffer, Shop, Damaged</p>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-outline-light btn-sm"><i class="bi bi-house me-1"></i> <span data-lang="nav_dashboard">Dashboard</span></a>
                <a href="inventory_report.php" class="btn btn-outline-light btn-sm"><i class="bi bi-clipboard2-data me-1"></i> <span data-lang="nav_inv_report">Inventory</span></a>
            </div>
        </div>
    </div>
</div>

<!-- TOAST NOTIFICATION -->
<div class="toast-wrap" id="toastWrap" style="display:none;">
    <div class="alert shadow-lg mb-2 d-flex align-items-center gap-2" id="toastMsg" role="alert">
        <i class="bi bi-check-circle-fill"></i>
        <span id="toastText"></span>
    </div>
</div>

<div class="container-fluid px-4 py-4 pb-5">

    <!-- ===== LOCATION FLOW DIAGRAM ===== -->
    <div class="flow-wrap">
        <div class="small fw-bold text-muted text-uppercase mb-3" style="letter-spacing:0.6px;" data-lang="xfer_flow_label">
            <i class="bi bi-diagram-3 me-1"></i> Aliran Stok — Klik untuk tapis lokasi
        </div>
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <a href="?from_location=Warehouse&category=<?= urlencode($filter_category) ?>"
               class="flow-node node-warehouse <?= ($filter_location === 'Warehouse') ? 'active' : '' ?>">
                <i class="bi bi-building fs-4 d-block mb-1"></i>
                <span class="fn-qty"><?= number_format($loc_totals['Warehouse'] ?? 0) ?></span>
                <span class="fn-label">Warehouse</span>
                <span class="fn-sub">Stor Utama</span>
            </a>

            <div class="flow-arrow"><i class="bi bi-arrow-left-right"></i></div>

            <a href="?from_location=Buffer&category=<?= urlencode($filter_category) ?>"
               class="flow-node node-buffer <?= ($filter_location === 'Buffer') ? 'active' : '' ?>">
                <i class="bi bi-boxes fs-4 d-block mb-1"></i>
                <span class="fn-qty"><?= number_format($loc_totals['Buffer'] ?? 0) ?></span>
                <span class="fn-label">Buffer</span>
                <span class="fn-sub">Stok Sementara</span>
            </a>
        </div>
    </div>

    <!-- ===== FILTER PANEL ===== -->
    <div class="filter-panel">
        <div class="small fw-bold text-muted text-uppercase mb-3" style="letter-spacing:0.6px;">
            <i class="bi bi-funnel me-1"></i> Tapis
        </div>
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="from_location" value="<?= htmlspecialchars($filter_location) ?>">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted text-uppercase mb-1">Lokasi Semasa</label>
                <select name="from_location" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="Warehouse" <?= ($filter_location === 'Warehouse') ? 'selected' : '' ?>>📦 Warehouse</option>
                    <option value="Buffer"    <?= ($filter_location === 'Buffer')    ? 'selected' : '' ?>>🗃️ Buffer</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted text-uppercase mb-1">Kategori</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">-- Semua --</option>
                    <?php foreach ($all_categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= ($filter_category === $cat) ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted text-uppercase mb-1">Nama Produk</label>
                <input type="text" name="product" class="form-control form-control-sm"
                       placeholder="Cari produk..." value="<?= htmlspecialchars($filter_product) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm fw-bold w-100" style="background:#0f172a;color:white;">
                    <i class="bi bi-search me-1"></i> Cari
                </button>
                <a href="stock_transfer.php" class="btn btn-sm btn-light border"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>

    <!-- ===== BATCH TABLE ===== -->
    <div class="batch-wrap">
        <div class="tbl-head d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <span class="fw-800 text-dark">
                    <i class="bi bi-arrow-left-right me-2 text-info"></i>
                    Stok di
                    <span class="loc-tag loc-<?= htmlspecialchars($filter_location) ?> ms-1">
                        <?= htmlspecialchars($filter_location) ?>
                    </span>
                </span>
                <span class="badge bg-secondary ms-2"><?= count($batches) ?> batch</span>
            </div>
            <?php if (!empty($batches)): ?>
            <button class="btn btn-sm btn-outline-primary fw-bold" onclick="selectAll()">
                <i class="bi bi-check2-square me-1"></i> Pilih Semua
            </button>
            <?php endif; ?>
        </div>

        <?php if (empty($batches)): ?>
            <div class="empty-box">
                <i class="bi bi-inbox"></i>
                <h5 class="fw-bold">Tiada Stok di <?= htmlspecialchars($filter_location) ?></h5>
                <p class="small text-muted">Tiada batch dengan stok > 0 di lokasi ini.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table batch-table mb-0" id="batchTable">
                <thead>
                    <tr>
                        <th style="width:36px"><input type="checkbox" id="checkAll" onchange="toggleAll(this)"></th>
                        <th data-lang="xfer_col_product">Produk</th>
                        <th data-lang="xfer_col_cat">Kategori</th>
                        <th data-lang="xfer_col_batch">Batch / Lot</th>
                        <th data-lang="xfer_col_expiry">Tarikh Luput</th>
                        <th class="text-end" data-lang="xfer_col_stock">Stok Ada</th>
                        <th class="text-center" data-lang="xfer_col_qty">Qty Pindah</th>
                        <th data-lang="xfer_col_dest">Pindah Ke</th>
                        <th data-lang="xfer_col_reason">Sebab</th>
                        <th class="text-center" data-lang="xfer_col_action">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $prev_cat = null;
                    foreach ($batches as $b):
                        $cat = $b['category'] ?? '—';
                        if ($cat !== $prev_cat):
                            $prev_cat = $cat;
                    ?>
                    <tr class="cat-group-row">
                        <td colspan="10"><i class="bi bi-folder me-2"></i><?= htmlspecialchars($cat) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr data-batch-id="<?= $b['batch_id'] ?>" data-max="<?= $b['qty_on_hand'] ?>">
                        <td><input type="checkbox" class="row-check" value="<?= $b['batch_id'] ?>"></td>
                        <td>
                            <div class="fw-600"><?= htmlspecialchars($b['product_name']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($b['uom']) ?></small>
                        </td>
                        <td>
                            <?php
                                $cat_css = match($b['category']) {
                                    'UHT' => '#dbeafe;color:#1d4ed8', 'PSS' => '#d1fae5;color:#065f46',
                                    'PST' => '#fef3c7;color:#92400e', default => '#f1f5f9;color:#475569'
                                };
                            ?>
                            <span style="font-size:0.72rem;font-weight:700;padding:3px 10px;border-radius:20px;background:<?= $cat_css ?>">
                                <?= htmlspecialchars($b['category']) ?>
                            </span>
                        </td>
                        <td class="font-monospace small">
                            <?= htmlspecialchars($b['batch_no'] ?? '—') ?>
                            <?php if (!empty($b['lot_no_raw'])): ?>
                                <br><span class="text-muted"><?= htmlspecialchars($b['lot_no_raw']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                                if (!empty($b['expiry_date'])) {
                                    $exp = new DateTime($b['expiry_date']);
                                    $days = (new DateTime())->diff($exp)->days * ($exp > new DateTime() ? 1 : -1);
                                    $cls = $days < 0 ? 'text-danger fw-bold' : ($days <= 30 ? 'text-danger' : ($days <= 90 ? 'text-warning' : 'text-muted'));
                                    echo '<span class="' . $cls . ' small">' . $exp->format('d/m/Y') . '</span>';
                                } else {
                                    echo '<span class="text-muted small">—</span>';
                                }
                            ?>
                        </td>
                        <td class="text-end">
                            <?php
                                $q = (int)$b['qty_on_hand'];
                                $qcls = $q >= 50 ? 'qty-high' : ($q >= 20 ? 'qty-medium' : 'qty-low');
                            ?>
                            <span class="<?= $qcls ?> fs-6"><?= number_format($q) ?></span>
                            <small class="text-muted d-block" style="font-size:0.7rem;">ctn</small>
                        </td>
                        <td class="text-center">
                            <input type="number"
                                   class="qty-input"
                                   id="qty_<?= $b['batch_id'] ?>"
                                   min="1" max="<?= $q ?>"
                                   placeholder="0"
                                   oninput="validateQty(this, <?= $q ?>)">
                        </td>
                        <td>
                            <select class="dest-select" id="dest_<?= $b['batch_id'] ?>">
                                <?php
                                    $locs = ['Warehouse','Buffer'];
                                    foreach ($locs as $l):
                                        if ($l === $filter_location) continue;
                                ?>
                                <option value="<?= $l ?>"><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   id="reason_<?= $b['batch_id'] ?>"
                                   placeholder="Sebab (pilihan)..."
                                   style="min-width:130px;font-size:0.8rem;">
                        </td>
                        <td class="text-center">
                            <button class="btn btn-transfer-row btn-primary"
                                    onclick="doTransfer(<?= $b['batch_id'] ?>, <?= $b['product_id'] ?>, '<?= htmlspecialchars($b['product_name'], ENT_QUOTES) ?>', <?= $q ?>)">
                                <i class="bi bi-arrow-right-circle me-1"></i> Pindah
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- BULK TRANSFER BAR -->
        <div id="bulkBar" class="d-none border-top" style="background:#f8fafc; padding: 14px 20px;">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <span class="fw-bold text-muted small"><span id="selectedCount">0</span> <span data-lang="xfer_bulk_selected">batch(es) selected</span></span>
                <div class="d-flex align-items-center gap-2">
                    <label class="small fw-bold text-muted" data-lang="xfer_bulk_dest">Move to:</label>
                    <select class="dest-select" id="bulkDest">
                        <?php
                            $locs = ['Warehouse','Buffer'];
                            foreach ($locs as $l):
                                if ($l === $filter_location) continue;
                        ?>
                        <option value="<?= $l ?>"><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label class="small fw-bold text-muted" data-lang="xfer_col_reason">Sebab:</label>
                    <input type="text" id="bulkReason" class="form-control form-control-sm" placeholder="Sebab pindahan..." style="width:200px;">
                </div>
                <button class="btn btn-primary btn-sm fw-bold" onclick="doBulkTransfer()">
                    <i class="bi bi-arrow-right-circle me-1"></i> <span data-lang="xfer_bulk_btn">Pindah Semua Yang Dipilih (Qty Penuh)</span>
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
const FROM_LOCATION = '<?= htmlspecialchars($filter_location) ?>';

// ===== QTY VALIDATION =====
function validateQty(input, max) {
    const v = parseInt(input.value);
    if (v > max) {
        input.classList.add('over');
    } else {
        input.classList.remove('over');
    }
}

// Helper to fetch product name and batch from DOM row
function getRowDetails(batchId) {
    const tr = document.querySelector(`tr[data-batch-id="${batchId}"]`);
    if (!tr) return { productName: 'Unknown', batchNo: '—' };
    const productName = tr.querySelector('.fw-600').textContent.trim();
    // Get batch no from fourth column (index 3)
    const batchCell = tr.querySelectorAll('td')[3];
    const batchNo = batchCell.childNodes[0].textContent.trim();
    return { productName, batchNo };
}

// ===== PRINT TRANSFER SLIP =====
function printTransferSlip(items, destination, reason) {
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    const dateStr = new Date().toLocaleString('ms-MY');
    
    let rowsHtml = '';
    items.forEach((item, idx) => {
        rowsHtml += `
            <tr>
                <td style="padding:10px; border:1px solid #cbd5e1; text-align:center;">${idx + 1}</td>
                <td style="padding:10px; border:1px solid #cbd5e1;">${item.productName}</td>
                <td style="padding:10px; border:1px solid #cbd5e1; text-align:center; font-family:monospace;">${item.batchNo || '—'}</td>
                <td style="padding:10px; border:1px solid #cbd5e1; text-align:center; font-weight:bold;">${item.qty} ctn</td>
            </tr>
        `;
    });
    
    const htmlContent = `
        <html>
        <head>
            <title>Nota Pemindahan Stok (Stock Transfer Slip)</title>
            <style>
                body { font-family: Arial, sans-serif; color: #1e293b; padding: 40px; line-height: 1.5; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 3px double #cbd5e1; padding-bottom: 15px; }
                .header h2 { margin: 0; color: #0f172a; }
                .header p { margin: 5px 0 0 0; color: #64748b; font-size: 0.9rem; letter-spacing: 1px; }
                .info-table { width: 100%; margin-bottom: 25px; border-collapse: collapse; }
                .info-table td { padding: 8px 0; font-size: 0.9rem; }
                .info-table td.label { font-weight: bold; width: 160px; color: #475569; }
                .items-table { width: 100%; border-collapse: collapse; margin-bottom: 35px; }
                .items-table th { background: #f1f5f9; padding: 10px; border: 1px solid #cbd5e1; font-weight: bold; text-align: left; font-size: 0.9rem; }
                .items-table td { font-size: 0.85rem; }
                .footer-sig { width: 100%; margin-top: 60px; display: table; table-layout: fixed; }
                .sig-col { display: table-cell; text-align: center; }
                .sig-box { width: 220px; margin: 0 auto; text-align: center; border-top: 1px solid #94a3b8; padding-top: 8px; font-size: 0.85rem; color: #475569; }
                @media print {
                    body { padding: 10px; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>MOO MOO SUPPLIES WMS</h2>
                <p>NOTA PEMINDAHAN STOK GUDANG (STOCK TRANSFER NOTE)</p>
            </div>
            <table class="info-table">
                <tr>
                    <td class="label">Tarikh & Masa:</td>
                    <td>${dateStr}</td>
                    <td class="label">Dari Lokasi:</td>
                    <td>${FROM_LOCATION}</td>
                </tr>
                <tr>
                    <td class="label">Sebab Pindahan:</td>
                    <td>${reason}</td>
                    <td class="label">Ke Destinasi:</td>
                    <td>${destination}</td>
                </tr>
            </table>
            
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="text-align:center; width:50px;">No.</th>
                        <th>Nama Produk</th>
                        <th style="text-align:center; width:140px;">Batch / Lot</th>
                        <th style="text-align:center; width:120px;">Kuantiti</th>
                    </tr>
                </thead>
                <tbody>
                    ${rowsHtml}
                </tbody>
            </table>
            
            <div class="footer-sig">
                <div class="sig-col">
                    <div class="sig-box" style="margin-bottom: 40px;"></div>
                    <div class="sig-box">Disediakan Oleh<br>(Penyelia Stor)</div>
                </div>
                <div class="sig-col">
                    <div class="sig-box" style="margin-bottom: 40px;"></div>
                    <div class="sig-box">Diterima Oleh<br>(Penerima Lokasi)</div>
                </div>
            </div>
            
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(function() { window.close(); }, 500);
                };
            <\/script>
        </body>
        </html>
    `;
    
    printWindow.document.write(htmlContent);
    printWindow.document.close();
}

// ===== SINGLE TRANSFER =====
function doTransfer(batchId, productId, productName, maxQty) {
    const qtyInput  = document.getElementById('qty_' + batchId);
    const destSel   = document.getElementById('dest_' + batchId);
    const reasonInp = document.getElementById('reason_' + batchId);

    const qty    = parseInt(qtyInput.value) || 0;
    const dest   = destSel.value;
    const reason = reasonInp.value.trim();

    if (qty <= 0) {
        showToast('Sila masukkan kuantiti yang hendak dipindah!', 'danger');
        qtyInput.focus();
        return;
    }
    if (qty > maxQty) {
        showToast('Kuantiti melebihi stok yang ada (' + maxQty + ' ctn)!', 'danger');
        qtyInput.focus();
        return;
    }
    if (reason === '') {
        showToast('Sila masukkan sebab pemindahan stok!', 'danger');
        reasonInp.focus();
        return;
    }

    const confirmed = confirm(
        `Sahkan pindahan stok?\n\n` +
        `Produk : ${productName}\n` +
        `Dari   : ${FROM_LOCATION}\n` +
        `Ke     : ${dest}\n` +
        `Kuantiti: ${qty} ctn\n` +
        `Sebab  : ${reason}`
    );

    if (!confirmed) return;

    const details = getRowDetails(batchId);
    const printItems = [{ productName: details.productName, batchNo: details.batchNo, qty: qty }];

    sendTransfer([{ batch_id: batchId, qty: qty }], dest, reason, printItems);
}

// ===== BULK TRANSFER =====
function doBulkTransfer() {
    const checked = document.querySelectorAll('.row-check:checked');
    const dest    = document.getElementById('bulkDest').value;
    const reason  = document.getElementById('bulkReason').value.trim();

    if (checked.length === 0) {
        showToast('Tiada batch dipilih!', 'warning');
        return;
    }
    if (reason === '') {
        showToast('Sila masukkan sebab pemindahan stok!', 'danger');
        document.getElementById('bulkReason').focus();
        return;
    }

    let items = [];
    let printItems = [];
    checked.forEach(cb => {
        const tr = cb.closest('tr');
        const max = parseInt(tr.dataset.max) || 0;
        const bid = parseInt(cb.value);
        items.push({ batch_id: bid, qty: max });
        
        const details = getRowDetails(bid);
        printItems.push({ productName: details.productName, batchNo: details.batchNo, qty: max });
    });

    const confirmed = confirm(
        `Sahkan pindahan stok?\n\n` +
        `${items.length} batch akan dipindah ke: ${dest}\n` +
        `(Kuantiti penuh setiap batch)\n` +
        `Sebab: ${reason}`
    );
    if (!confirmed) return;

    sendTransfer(items, dest, reason, printItems);
}

// ===== AJAX TRANSFER =====
function sendTransfer(items, destination, reason, printItems) {
    fetch('api/save_stock_transfer.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items, destination, reason, from_location: FROM_LOCATION })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Stok berjaya dipindah!', 'success');
            printTransferSlip(printItems, destination, reason);
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Ralat: ' + (data.message || 'Transfer gagal.'), 'danger');
        }
    })
    .catch(err => {
        showToast('Ralat sambungan: ' + err.message, 'danger');
    });
}

// ===== CHECKBOX SELECT ALL =====
function toggleAll(cb) {
    document.querySelectorAll('.row-check').forEach(c => c.checked = cb.checked);
    updateBulkBar();
}

function selectAll() {
    document.querySelectorAll('.row-check').forEach(c => c.checked = true);
    document.getElementById('checkAll').checked = true;
    updateBulkBar();
}

document.addEventListener('change', function(e) {
    if (e.target.classList.contains('row-check')) updateBulkBar();
});

function updateBulkBar() {
    const count = document.querySelectorAll('.row-check:checked').length;
    const bar   = document.getElementById('bulkBar');
    document.getElementById('selectedCount').textContent = count;
    bar.classList.toggle('d-none', count === 0);
}

// ===== TOAST =====
function showToast(msg, type) {
    const wrap = document.getElementById('toastWrap');
    const box  = document.getElementById('toastMsg');
    const txt  = document.getElementById('toastText');
    const icons = { success: 'check-circle-fill', danger: 'x-circle-fill', warning: 'exclamation-triangle-fill' };

    box.className = `alert alert-${type} shadow-lg mb-2 d-flex align-items-center gap-2`;
    box.querySelector('i').className = `bi bi-${icons[type] || 'info-circle-fill'}`;
    txt.textContent = msg;
    wrap.style.display = 'block';
    setTimeout(() => { wrap.style.display = 'none'; }, 4000);
}
</script>

<?php require_once 'includes/footer.php'; ?>
