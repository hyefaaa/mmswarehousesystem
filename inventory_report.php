<?php
// inventory_report.php - Laporan Inventori Lengkap
// MMS Warehouse System | Moo Moo Supplies

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';

// --- FILTER PARAMETERS ---
$filter_category = $_GET['category'] ?? '';
$filter_location = $_GET['location'] ?? '';
$filter_product  = $_GET['product'] ?? '';
$filter_stock    = $_GET['stock_filter'] ?? ''; // 'all', 'in_stock', 'zero'
$filter_expiry   = $_GET['expiry'] ?? '';

// --- FETCH CATEGORIES & PRODUCTS FOR FILTER DROPDOWNS ---
$all_categories = $pdo->query("SELECT DISTINCT category FROM products WHERE is_active = 1 ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
$all_products   = $pdo->query("SELECT id, name, category FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// --- SUMMARY STATS ---
$stats = $pdo->query("
    SELECT
        COUNT(DISTINCT p.id) as total_products,
        COALESCE(SUM(b.qty_on_hand), 0) as total_stock,
        COUNT(DISTINCT CASE WHEN b.qty_on_hand > 0 THEN p.id END) as products_with_stock,
        COUNT(DISTINCT CASE WHEN b.qty_on_hand = 0 OR b.qty_on_hand IS NULL THEN p.id END) as products_no_stock
    FROM products p
    LEFT JOIN inventory_batches b ON p.id = b.product_id AND b.location_status = 'Warehouse'
    WHERE p.is_active = 1
")->fetch();

// --- LOW STOCK COUNT (< 50 cartons) ---
$low_stock_count = $pdo->query("
    SELECT COUNT(*) FROM (
        SELECT p.id, COALESCE(SUM(b.qty_on_hand),0) as total
        FROM products p
        LEFT JOIN inventory_batches b ON p.id = b.product_id AND b.location_status = 'Warehouse'
        WHERE p.is_active = 1
        GROUP BY p.id
        HAVING total > 0 AND total < 50
    ) as low
")->fetchColumn();

// --- MAIN INVENTORY QUERY WITH FILTERS ---
$sql = "
    SELECT
        p.id as product_id,
        p.name as product_name,
        p.category,
        p.uom,
        p.pack_size,
        p.pcs_per_carton,
        p.pallet_capacity,
        p.barcode,
        b.id as batch_id,
        b.batch_no,
        b.lot_no_raw,
        b.expiry_date,
        b.production_date,
        b.qty_on_hand,
        b.pallet_type,
        b.location_status,
        b.created_at as batch_created
    FROM products p
    LEFT JOIN inventory_batches b ON p.id = b.product_id
    WHERE p.is_active = 1
";

$params = [];

if (!empty($filter_category)) {
    $sql .= " AND p.category = ?";
    $params[] = $filter_category;
}
if (!empty($filter_location)) {
    $sql .= " AND b.location_status = ?";
    $params[] = $filter_location;
}
if (!empty($filter_product)) {
    $sql .= " AND p.name LIKE ?";
    $params[] = "%$filter_product%";
}
if (!empty($filter_expiry)) {
    $sql .= " AND b.expiry_date = ?";
    $params[] = $filter_expiry;
}
if ($filter_stock === 'in_stock') {
    $sql .= " AND b.qty_on_hand > 0";
} elseif ($filter_stock === 'zero') {
    $sql .= " AND (b.qty_on_hand = 0 OR b.qty_on_hand IS NULL)";
}

$sql .= "
    ORDER BY
        CASE p.category
            WHEN 'UHT' THEN 1
            WHEN 'PSS' THEN 2
            WHEN 'PST' THEN 3
            ELSE 4
        END,
        p.name ASC,
        b.expiry_date ASC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inventory = $stmt->fetchAll();
} catch (PDOException $e) {
    $inventory = [];
    $db_error = $e->getMessage();
}

// --- CATEGORY SUMMARY ---
$cat_summary = $pdo->query("
    SELECT p.category,
           COUNT(DISTINCT p.id) as product_count,
           COALESCE(SUM(b.qty_on_hand), 0) as total_qty
    FROM products p
    LEFT JOIN inventory_batches b ON p.id = b.product_id AND b.location_status = 'Warehouse'
    WHERE p.is_active = 1
    GROUP BY p.category
    ORDER BY p.category ASC
")->fetchAll();

$page_title = 'Inventory Report | Moo Moo Supplies';
require_once 'includes/header.php';
?>

<style>
    /* ============ PAGE LAYOUT ============ */
    .page-header {
        background: linear-gradient(135deg, var(--mms-navy, #0f172a) 0%, #1e3a5f 100%);
        color: white;
        padding: 28px 0 24px;
        border-bottom: 3px solid var(--mms-cyan, #06b6d4);
        margin-bottom: 0;
    }

    /* ============ STAT CARDS ============ */
    .stat-box {
        background: white;
        border-radius: 16px;
        padding: 20px 24px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        border: 1px solid #e2e8f0;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        position: relative;
        overflow: hidden;
    }
    .stat-box:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
    .stat-box::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 4px;
        border-radius: 16px 16px 0 0;
    }
    .stat-box.blue::before   { background: #3b82f6; }
    .stat-box.green::before  { background: #10b981; }
    .stat-box.orange::before { background: #f59e0b; }
    .stat-box.red::before    { background: #ef4444; }
    .stat-num { font-size: 2rem; font-weight: 800; line-height: 1; margin-bottom: 4px; }
    .stat-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #64748b; }

    /* ============ FILTER PANEL ============ */
    .filter-card {
        background: white;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        padding: 20px 24px;
        margin-bottom: 20px;
    }
    .filter-card .filter-title {
        font-size: 0.78rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #94a3b8;
        margin-bottom: 14px;
    }

    /* ============ CATEGORY PILL BADGES ============ */
    .cat-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 30px;
        font-size: 0.78rem;
        font-weight: 700;
        border: 2px solid transparent;
        cursor: default;
    }
    .cat-UHT  { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
    .cat-PSS  { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
    .cat-PST  { background: #fff7ed; color: #9a3412; border-color: #fed7aa; }
    .cat-Other { background: #f8fafc; color: #475569; border-color: #cbd5e1; }

    /* ============ INVENTORY TABLE ============ */
    .inv-table-wrap {
        background: white;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(0,0,0,0.05);
    }
    .inv-table-wrap .table-header {
        background: #f8fafc;
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .inv-table { margin-bottom: 0; }
    @media (min-width: 768px) {
        .inv-table { min-width: 1050px; }
    }
    .inv-table thead th {
        background-color: #1e293b !important;
        color: #ffffff !important;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 12px 14px;
        border: none;
        white-space: nowrap;
    }
    .inv-table tbody tr {
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.15s;
    }
    .inv-table tbody tr:hover { background: #f8fafc; }
    .inv-table tbody td { padding: 11px 14px; vertical-align: middle; font-size: 0.9rem; }

    /* ============ STOCK BADGES ============ */
    .qty-badge {
        font-weight: 800;
        font-size: 1rem;
        padding: 3px 0;
        display: block;
        text-align: right;
    }
    .qty-high   { color: #10b981; }
    .qty-medium { color: #f59e0b; }
    .qty-low    { color: #ef4444; }
    .qty-zero   { color: #94a3b8; font-style: italic; font-size: 0.8rem; }

    /* ============ LOCATION TAG ============ */
    .loc-tag {
        font-size: 0.72rem;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 20px;
        display: inline-block;
    }
    .loc-Warehouse { background: #dbeafe; color: #1e40af; }
    .loc-Buffer    { background: #fef9c3; color: #854d0e; }
    .loc-Shop      { background: #d1fae5; color: #065f46; }
    .loc-Damaged   { background: #fee2e2; color: #991b1b; }
    .loc-null      { background: #f1f5f9; color: #94a3b8; }

    /* ============ EXPIRY INDICATOR ============ */
    .expiry-danger  { color: #ef4444; font-weight: 700; }
    .expiry-warning { color: #f59e0b; font-weight: 600; }
    .expiry-ok      { color: #10b981; }
    .expiry-none    { color: #cbd5e1; font-style: italic; font-size: 0.8rem; }

    /* ============ CATEGORY GROUP ROW ============ */
    .group-row td {
        background: #1e293b;
        color: #94a3b8;
        font-size: 0.72rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 8px 14px !important;
    }

    /* ============ PRINT STYLES ============ */
    @media print {
        .no-print { display: none !important; }
        .page-header { background: #0f172a !important; -webkit-print-color-adjust: exact; }
        .inv-table thead th { background: #1e293b !important; -webkit-print-color-adjust: exact; }
        body { font-size: 11px; }
    }

    /* ============ EMPTY STATE ============ */
    .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
    .empty-state i { font-size: 3rem; margin-bottom: 16px; display: block; }
</style>

<!-- ===== PAGE HEADER ===== -->
<div class="page-header no-print">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="fw-800 mb-1 fs-4"><i class="bi bi-clipboard2-data me-2"></i><span data-lang="inv_title">Inventory Report</span></h1>
                <p class="opacity-75 mb-0 small"><span data-lang="inv_subtitle">Laporan Stok Semasa</span> — <?= date('d F Y, h:i A') ?></p>
            </div>
            <div class="d-flex gap-2 no-print">
                <a href="index.php" class="btn btn-outline-light btn-sm"><i class="bi bi-house me-1"></i> <span data-lang="nav_dashboard">Dashboard</span></a>
                <a href="reports.php" class="btn btn-outline-light btn-sm"><i class="bi bi-graph-up-arrow me-1"></i> <span data-lang="nav_wh_monitor">Monitor</span></a>
                <button onclick="window.print()" class="btn btn-sm fw-bold" style="background:#06b6d4; color:white;">
                    <i class="bi bi-printer me-1"></i> <span data-lang="inv_btn_print">Print</span>
                </button>
                <button onclick="exportCSV()" class="btn btn-success btn-sm fw-bold">
                    <i class="bi bi-file-earmark-excel me-1"></i> <span data-lang="inv_btn_export">Export CSV</span>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 py-4 pb-5">

    <?php if (isset($db_error)): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-2"></i><?= htmlspecialchars($db_error) ?></div>
    <?php endif; ?>

    <!-- ===== SUMMARY STATS ===== -->
    <div class="row g-3 mb-4 no-print">
        <div class="col-6 col-md-3">
            <div class="stat-box blue">
                <div class="stat-num text-primary"><?= number_format($stats['total_products'] ?? 0) ?></div>
                <div class="stat-label"><i class="bi bi-box me-1"></i> <span data-lang="inv_stat_products">Total Produk</span></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-box green">
                <div class="stat-num text-success"><?= number_format($stats['total_stock'] ?? 0) ?></div>
                <div class="stat-label"><i class="bi bi-layers me-1"></i> <span data-lang="inv_stat_stock">Jumlah Stok (ctn)</span></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-box orange">
                <div class="stat-num text-warning"><?= number_format($low_stock_count ?? 0) ?></div>
                <div class="stat-label"><i class="bi bi-exclamation-triangle me-1"></i> <span data-lang="inv_stat_low">Stok Rendah (&lt;50)</span></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-box red">
                <div class="stat-num text-danger"><?= number_format($stats['products_no_stock'] ?? 0) ?></div>
                <div class="stat-label"><i class="bi bi-slash-circle me-1"></i> <span data-lang="inv_stat_no_stock">Tiada Stok</span></div>
            </div>
        </div>
    </div>

    <!-- ===== CATEGORY SUMMARY PILLS ===== -->
    <div class="mb-4 no-print">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="small fw-bold text-muted me-1">KATEGORI:</span>
            <?php foreach ($cat_summary as $cat): 

                    $css = match($cat['category']) {
                        'UHT' => 'cat-UHT', 'PSS' => 'cat-PSS', 'PST' => 'cat-PST', default => 'cat-Other'
                    };
                ?>
                <span class="cat-badge <?= $css ?>">
                    <?= htmlspecialchars($cat['category']) ?>
                    <span class="badge bg-white text-dark ms-1 shadow-sm"><?= number_format($cat['total_qty']) ?> ctn</span>
                </span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ===== FILTER PANEL ===== -->
    <div class="filter-card no-print">
        <div class="filter-title"><i class="bi bi-funnel me-1"></i> <span data-lang="inv_filter_title">Tapis Data</span></div>
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-xl-2 col-md-4">
                <label class="form-label small fw-bold text-muted text-uppercase mb-1" data-lang="inv_filter_cat">Kategori</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="" data-lang="inv_filter_all">-- Semua Kategori --</option>
                    <?php foreach ($all_categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= ($filter_category === $cat) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-3 col-md-4">
                <label class="form-label small fw-bold text-muted text-uppercase mb-1" data-lang="inv_filter_product">Nama Produk</label>
                <input type="text" name="product" class="form-control form-control-sm" 
                       placeholder="Cari nama produk..." data-lang-placeholder="inv_filter_product_placeholder"
                       value="<?= htmlspecialchars($filter_product) ?>">
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="form-label small fw-bold text-muted text-uppercase mb-1" data-lang="inv_col_expiry">Tarikh Luput</label>
                <input type="date" name="expiry" class="form-control form-control-sm" 
                       value="<?= htmlspecialchars($filter_expiry) ?>">
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="form-label small fw-bold text-muted text-uppercase mb-1" data-lang="inv_filter_loc">Lokasi</label>
                <select name="location" class="form-select form-select-sm">
                    <option value="" data-lang="inv_filter_all">-- Semua Lokasi --</option>
                    <option value="Warehouse" <?= ($filter_location === 'Warehouse') ? 'selected' : '' ?>>Warehouse</option>
                    <option value="Buffer"    <?= ($filter_location === 'Buffer')    ? 'selected' : '' ?>>Buffer</option>
                    <option value="Shop"      <?= ($filter_location === 'Shop')      ? 'selected' : '' ?>>Shop</option>
                    <option value="Damaged"   <?= ($filter_location === 'Damaged')   ? 'selected' : '' ?>>Damaged</option>
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="form-label small fw-bold text-muted text-uppercase mb-1" data-lang="inv_filter_stock">Status Stok</label>
                <select name="stock_filter" class="form-select form-select-sm">
                    <option value="" data-lang="inv_filter_all">-- Semua --</option>
                    <option value="in_stock" <?= ($filter_stock === 'in_stock') ? 'selected' : '' ?> data-lang="inv_filter_in_stock">Ada Stok Sahaja</option>
                    <option value="zero"     <?= ($filter_stock === 'zero')     ? 'selected' : '' ?> data-lang="inv_filter_zero">Kosong Sahaja</option>
                </select>
            </div>
            <div class="col-xl-1 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-sm fw-bold w-100" style="background:#0f172a; color:white;" data-lang-title="btn_search" title="Cari">
                    <i class="bi bi-search"></i>
                </button>
                <a href="inventory_report.php" class="btn btn-sm btn-light border w-100" data-lang-title="btn_reset" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- ===== INVENTORY TABLE ===== -->
    <div class="inv-table-wrap">
        <div class="table-header">
            <div>
                <span class="fw-800 text-dark"><i class="bi bi-table me-2 text-info"></i><span data-lang="inv_table_title">Senarai Inventori</span></span>
                <span class="badge bg-secondary ms-2"><?= count($inventory) ?> baris</span>
                <?php if (!empty($filter_category) || !empty($filter_product) || !empty($filter_location) || !empty($filter_stock) || !empty($filter_expiry)): ?>
                    <span class="badge ms-1" style="background:#06b6d4;">Filtered</span>
                <?php endif; ?>
            </div>
            <small class="text-muted no-print">Dijana: <?= date('d/m/Y H:i') ?></small>
        </div>

        <?php if (empty($inventory)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h5 class="fw-bold" data-lang="inv_empty">Tiada Data</h5>
                <p class="text-muted small" data-lang="inv_empty_sub">Tiada rekod inventori yang sepadan dengan tapisan anda.</p>
                <a href="inventory_report.php" class="btn btn-outline-secondary btn-sm" data-lang="inv_reset">Reset Tapisan</a>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table inv-table" id="inventoryTable">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th data-lang="inv_col_product">Nama Produk</th>
                        <th data-lang="inv_col_category">Kategori</th>
                        <th data-lang="inv_col_lot">Lot No.</th>
                        <th data-lang="inv_col_batch">Batch No.</th>
                        <th data-lang="inv_col_expiry">Tarikh Luput</th>
                        <th data-lang="inv_col_location">Lokasi</th>
                        <th class="text-end" data-lang="inv_col_stock_ctn">Stok (ctn)</th>
                        <th class="text-end no-print" data-lang="inv_col_stock_pcs">Stok (pcs)</th>
                        <th data-lang="inv_col_pallet">Jenis Pallet</th>
                        <th class="no-print" data-lang="inv_col_date_in">Tarikh Masuk</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $row_num = 0;
                    $prev_cat = null;
                    $cat_totals = [];

                    // Pre-group by category for totals
                    foreach ($inventory as $row) {
                        $c = $row['category'] ?? 'Lain-lain';
                        if (!isset($cat_totals[$c])) $cat_totals[$c] = 0;
                        $cat_totals[$c] += ($row['qty_on_hand'] ?? 0);
                    }

                    foreach ($inventory as $row):
                        $cat = $row['category'] ?? 'Lain-lain';

                        // Group header row per category
                        if ($cat !== $prev_cat):
                            $prev_cat = $cat;
                    ?>
                    <tr class="group-row">
                        <td colspan="11">
                            <i class="bi bi-folder me-2"></i>
                            <?= htmlspecialchars($cat) ?>
                            &nbsp;&nbsp;—&nbsp;&nbsp;
                            Jumlah: <?= number_format($cat_totals[$cat] ?? 0) ?> ctn
                        </td>
                    </tr>
                    <?php endif; $row_num++; ?>
                    <tr>
                        <td class="text-muted small"><?= $row_num ?></td>
                        <td>
                            <div class="fw-600" style="max-width: 260px;">
                                <?= htmlspecialchars($row['product_name'] ?? '') ?>
                            </div>
                            <?php if (!empty($row['barcode'])): ?>
                                <small class="text-muted font-monospace"><?= htmlspecialchars($row['barcode']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                                $cat_css = match($row['category']) {
                                    'UHT' => 'cat-UHT', 'PSS' => 'cat-PSS', 'PST' => 'cat-PST', default => 'cat-Other'
                                };
                            ?>
                            <span class="cat-badge <?= $cat_css ?>" style="font-size:0.7rem; padding: 3px 10px;">
                                <?= htmlspecialchars($row['category'] ?? '') ?>
                            </span>
                        </td>
                        <td class="font-monospace small text-muted"><?= htmlspecialchars($row['lot_no_raw'] ?? '—') ?></td>
                        <td class="font-monospace small"><?= htmlspecialchars($row['batch_no'] ?? '—') ?></td>
                        <td>
                            <?php
                                if (!empty($row['expiry_date'])) {
                                    $exp = new DateTime($row['expiry_date']);
                                    $now = new DateTime();
                                    $days_left = $now->diff($exp)->days * ($exp > $now ? 1 : -1);

                                    if ($days_left < 0) {
                                        echo '<span class="expiry-danger"><i class="bi bi-x-circle me-1"></i>Tamat Tempoh</span><br><small class="text-danger">' . $exp->format('d/m/Y') . '</small>';
                                    } elseif ($days_left <= 30) {
                                        echo '<span class="expiry-danger"><i class="bi bi-exclamation-triangle me-1"></i>' . $exp->format('d/m/Y') . '</span><br><small class="text-danger">' . $days_left . ' hari lagi</small>';
                                    } elseif ($days_left <= 90) {
                                        echo '<span class="expiry-warning"><i class="bi bi-clock me-1"></i>' . $exp->format('d/m/Y') . '</span><br><small class="text-warning">' . $days_left . ' hari lagi</small>';
                                    } else {
                                        echo '<span class="expiry-ok">' . $exp->format('d/m/Y') . '</span>';
                                    }
                                } else {
                                    echo '<span class="expiry-none">—</span>';
                                }
                            ?>
                        </td>
                        <td>
                            <?php
                                $loc = $row['location_status'] ?? null;
                                $loc_css = 'loc-' . ($loc ?? 'null');
                            ?>
                            <span class="loc-tag <?= $loc_css ?>"><?= htmlspecialchars($loc ?? 'N/A') ?></span>
                        </td>
                        <td class="text-end">
                            <?php
                                $qty = (int)($row['qty_on_hand'] ?? 0);
                                if ($qty === 0) {
                                    echo '<span class="qty-zero">Kosong</span>';
                                } elseif ($qty < 20) {
                                    echo '<span class="qty-badge qty-low">' . number_format($qty) . '</span>';
                                } elseif ($qty < 50) {
                                    echo '<span class="qty-badge qty-medium">' . number_format($qty) . '</span>';
                                } else {
                                    echo '<span class="qty-badge qty-high">' . number_format($qty) . '</span>';
                                }
                            ?>
                        </td>
                        <td class="text-end no-print">
                            <?php
                                $pcs_per_ctn = (int)($row['pcs_per_carton'] ?? 1);
                                $total_pcs = $qty * $pcs_per_ctn;
                                echo $total_pcs > 0
                                    ? '<small class="text-muted">' . number_format($total_pcs) . ' pcs</small>'
                                    : '<span class="text-muted small">—</span>';
                            ?>
                        </td>
                        <td>
                            <?php if (!empty($row['pallet_type'])): ?>
                                <small class="text-muted"><?= htmlspecialchars($row['pallet_type']) ?></small>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="no-print">
                            <small class="text-muted">
                                <?= !empty($row['batch_created']) ? date('d/m/y', strtotime($row['batch_created'])) : '—' ?>
                            </small>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <!-- GRAND TOTAL ROW -->
                    <tr style="background: #f0fdf4; border-top: 2px solid #10b981;">
                        <td colspan="7" class="fw-800 text-success text-end">
                            <i class="bi bi-calculator me-1"></i> JUMLAH KESELURUHAN STOK:
                        </td>
                        <td class="text-end fw-800 text-success fs-5">
                            <?= number_format(array_sum(array_column($inventory, 'qty_on_hand'))) ?> ctn
                        </td>
                        <td colspan="3" class="no-print"></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- PRINT FOOTER INFO -->
    <div class="mt-3 text-center text-muted small d-none d-print-block">
        <hr>
        Laporan ini dijana oleh <strong>MMS Warehouse System</strong> — Moo Moo Supplies<br>
        <?= date('d F Y, h:i A') ?>
    </div>

</div>

<script>
// ===== CSV EXPORT =====
function exportCSV() {
    const table = document.getElementById('inventoryTable');
    if (!table) return;

    let csv = [];
    // Header
    let headers = [];
    table.querySelectorAll('thead th').forEach(th => {
        if (!th.classList.contains('no-print')) {
            headers.push('"' + th.innerText.trim() + '"');
        }
    });
    csv.push(headers.join(','));

    // Rows
    table.querySelectorAll('tbody tr').forEach(tr => {
        if (tr.classList.contains('group-row')) return; // skip group headers
        let row = [];
        tr.querySelectorAll('td').forEach((td, i) => {
            if (!td.classList.contains('no-print')) {
                let val = td.innerText.trim().replace(/\n/g, ' ').replace(/"/g, '""');
                row.push('"' + val + '"');
            }
        });
        if (row.length > 0) csv.push(row.join(','));
    });

    const blob = new Blob(["\uFEFF" + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'inventory_report_<?= date('Ymd_His') ?>.csv';
    a.click();
    URL.revokeObjectURL(url);
}

// ===== DATATABLE REMOVED =====
// DataTables tidak digunakan pada jadual ini kerana reka bentuk jadual menggunakan baris kumpulan (group-row) 
// dan baris jumlah keseluruhan (grand-total) dengan atribut colspan (yang tidak disokong oleh DataTables). 
// Carian dan tapisan telah disediakan sepenuhnya di bahagian pelayan (server-side PHP).
</script>

<?php require_once 'includes/footer.php'; ?>
