<?php
// reports.php - FULL UPGRADED INTERFACE FOR MOO MOO SUPPLIES
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

// --- 1. DATA PREPARATION FOR SEARCH ---
// Ambil senarai semua produk untuk dropdown/datalist carian
$sql_all_products = "SELECT name FROM products ORDER BY name ASC";
$all_products = $pdo->query($sql_all_products)->fetchAll(PDO::FETCH_COLUMN);

// --- 2. MASTER SEARCH LOGIC ---
$trace_results = [];
$search_batch   = $_GET['search_batch'] ?? '';
$search_expiry  = $_GET['search_expiry'] ?? '';
$search_product = $_GET['search_product'] ?? '';
$search_do      = $_GET['search_do'] ?? '';
$search_po      = $_GET['search_po'] ?? '';
$search_date    = $_GET['search_date'] ?? '';

if (!empty($search_batch) || !empty($search_expiry) || !empty($search_product) || !empty($search_do) || !empty($search_po) || !empty($search_date)) {
    $sql_trace = "
        SELECT p.name as product_name, i.batch_no, i.qty_received, i.expiry_date, 
               l.supplier_do, l.received_date, l.remarks, l.category
        FROM inbound_items i
        JOIN inbound_logs l ON i.inbound_id = l.id
        JOIN products p ON i.product_id = p.id
        WHERE 1=1
    ";
    
    $params = [];
    if (!empty($search_batch)) { $sql_trace .= " AND i.batch_no LIKE ?"; $params[] = "%$search_batch%"; }
    if (!empty($search_expiry)) { $sql_trace .= " AND i.expiry_date = ?"; $params[] = $search_expiry; }
    if (!empty($search_product)) { $sql_trace .= " AND p.name LIKE ?"; $params[] = "%$search_product%"; }
    if (!empty($search_do)) { $sql_trace .= " AND l.supplier_do LIKE ?"; $params[] = "%$search_do%"; }
    if (!empty($search_po)) { $sql_trace .= " AND l.remarks LIKE ?"; $params[] = "%$search_po%"; }
    if (!empty($search_date)) { $sql_trace .= " AND l.received_date = ?"; $params[] = $search_date; }
    
    $sql_trace .= " ORDER BY l.received_date DESC";
    
    try {
        $stmt = $pdo->prepare($sql_trace);
        $stmt->execute($params);
        $trace_results = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error_msg = "Search Error: " . $e->getMessage();
    }
}

// --- 3. DASHBOARD DATA FETCHING ---
// Recent Inbound Logs
$sql_inbound = "
    SELECT l.id, l.received_date, l.supplier_do, l.category, COUNT(i.id) as item_count, 
           (COALESCE(l.pallet_qty_loscam_red, 0) + COALESCE(l.pallet_qty_ffm_orange, 0) + 
            COALESCE(l.pallet_qty_ffm_green, 0) + COALESCE(l.pallet_qty_lhp_green, 0) + 
            COALESCE(l.pallet_qty_plain_wood, 0) + COALESCE(l.pallet_qty_plastic_black, 0)) as total_pallets
    FROM inbound_logs l
    LEFT JOIN inbound_items i ON l.id = i.inbound_id
    GROUP BY l.id ORDER BY l.received_date DESC LIMIT 50
";
$inbound_logs = $pdo->query($sql_inbound)->fetchAll();

// Stock Balance (All Products grouped by Category & Expiry Date)
$sql_stock = "
    SELECT p.name, p.category, SUM(b.qty_on_hand) as total_qty, p.uom, b.expiry_date
    FROM products p
    LEFT JOIN inventory_batches b ON p.id = b.product_id AND b.qty_on_hand > 0 AND b.location_status = 'Warehouse'
    WHERE p.is_active = 1
    GROUP BY p.category, p.id, b.expiry_date
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
$stock_balance = $pdo->query($sql_stock)->fetchAll();

// Pallet Liability
$sql_pallets = "SELECT SUM(pallet_qty_loscam_red) as red, SUM(pallet_qty_ffm_orange) as orange,
                SUM(pallet_qty_ffm_green) as ffm_green, SUM(pallet_qty_lhp_green) as lhp_green,
                SUM(pallet_qty_plain_wood) as plain,
                SUM(pallet_qty_plastic_black) as black FROM inbound_logs";
$pallet_totals = $pdo->query($sql_pallets)->fetch();
$page_title = 'Warehouse Monitor | Moo Moo Supplies';
require_once 'includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<style>
    .mms-header-sub { background: var(--mms-navy); color: white; padding: 15px 0; border-bottom: 4px solid var(--mms-cyan); margin-top: -1.5rem; }
    .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 20px; }
    .card-header { background: white; border-bottom: 1px solid #edf2f7; font-weight: 700; color: var(--mms-navy); }
    
    .search-container { background: white; border-left: 5px solid var(--mms-cyan); border-radius: 12px; }
    
    .pallet-box { padding: 15px; border-radius: 10px; background: white; border: 1px solid #e2e8f0; text-align: center; }
    .pallet-num { font-size: 1.8rem; font-weight: 800; display: block; }
    
    .table thead { background: #f8fafc; }
    .text-mms-cyan { color: var(--mms-cyan); }
</style>

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-graph-up-arrow me-2"></i><span data-lang="nav_wh_monitor">Warehouse Monitor</span></h1>
                <p class="opacity-75 mb-0 fw-light">Traceability Tool & Live Inventory Status</p>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> <span data-lang="nav_dashboard">Dashboard</span></a>
                <a href="receiving_multi.php" class="btn btn-info text-white fw-bold"><i class="bi bi-plus-lg me-1"></i> <span data-lang="nav_receiving">Receive Stock</span></a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    <div class="card main-card border-0 mb-4">
        <h5 class="fw-bold mb-3 text-mms-cyan"><i class="bi bi-search me-2"></i><span data-lang="xfer_search">Smart Traceability Tool</span></h5>
        <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted text-uppercase">Supplier DO</label>
                    <input type="text" name="search_do" class="form-control" placeholder="DO-XXXX" value="<?= htmlspecialchars($search_do) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted text-uppercase">PO / SO No.</label>
                    <input type="text" name="search_po" class="form-control" placeholder="SO-XXXX" value="<?= htmlspecialchars($search_po) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted text-uppercase">Batch Number</label>
                    <input type="text" name="search_batch" class="form-control" placeholder="Batch..." value="<?= htmlspecialchars($search_batch) ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Product Name</label>
                    <input list="product_list" name="search_product" class="form-control" placeholder="Type or select product..." value="<?= htmlspecialchars($search_product) ?>" autocomplete="off">
                    <datalist id="product_list">
                        <?php foreach ($all_products as $p_name): ?>
                            <option value="<?= htmlspecialchars($p_name) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted text-uppercase">Received Date</label>
                    <input type="date" name="search_date" class="form-control" value="<?= htmlspecialchars($search_date) ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end gap-1">
                    <button type="submit" class="btn btn-primary w-100" style="background: var(--mms-navy);"><i class="bi bi-search"></i></button>
                    <a href="reports.php" class="btn btn-light border w-100"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>

            <?php if (isset($error_msg)): ?>
                <div class="alert alert-danger mt-3"><?= $error_msg ?></div>
            <?php endif; ?>

            <?php if (!empty($trace_results)): ?>
                <div class="mt-4 p-3 bg-light rounded border">
                    <h6 class="fw-bold text-success mb-3"><i class="bi bi-check2-circle me-1"></i> Found <?= count($trace_results) ?> Records</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle bg-white border">
                            <thead class="table-dark">
                                <tr>
                                    <th>Product</th>
                                    <th>Batch</th>
                                    <th>Supplier DO</th>
                                    <th>Date</th>
                                    <th>Qty</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trace_results as $row): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($row['product_name'] ?? '') ?></td>
                                    <td><span class="badge bg-primary"><?= htmlspecialchars($row['batch_no'] ?? '') ?></span></td>
                                    <td class="text-mms-cyan fw-bold"><?= htmlspecialchars($row['supplier_do'] ?? 'N/A') ?></td>
                                    <td><?= !empty($row['received_date']) ? date('d/m/y', strtotime($row['received_date'])) : '-' ?></td>
                                    <td class="fw-bold"><?= $row['qty_received'] ?? 0 ?></td>
                                    <td class="small text-muted"><?= htmlspecialchars($row['remarks'] ?? '') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif (!empty($search_do) || !empty($search_batch) || !empty($search_product)): ?>
                <div class="alert alert-warning mt-3">No matching records found.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col"><div class="pallet-box border-top border-4" style="border-top-color: #8b5a2b !important;"><span class="pallet-num" style="color: #8b5a2b;"><?= number_format($pallet_totals['plain'] ?? 0) ?></span><small class="fw-bold text-muted">PLAIN WOOD</small></div></div>
        <div class="col"><div class="pallet-box border-top border-danger border-4"><span class="pallet-num text-danger"><?= number_format($pallet_totals['red'] ?? 0) ?></span><small class="fw-bold text-muted">LOSCAM RED</small></div></div>
        <div class="col"><div class="pallet-box border-top border-warning border-4"><span class="pallet-num text-warning"><?= number_format($pallet_totals['orange'] ?? 0) ?></span><small class="fw-bold text-muted">FFM ORANGE</small></div></div>
        <div class="col"><div class="pallet-box border-top border-success border-4"><span class="pallet-num text-success"><?= number_format($pallet_totals['lhp_green'] ?? 0) ?></span><small class="fw-bold text-muted">LHP GREEN</small></div></div>
        <div class="col"><div class="pallet-box border-top border-success border-4" style="border-top-color: #2ecc71 !important;"><span class="pallet-num" style="color: #2ecc71;"><?= number_format($pallet_totals['ffm_green'] ?? 0) ?></span><small class="fw-bold text-muted">FFM GREEN</small></div></div>
        <div class="col"><div class="pallet-box border-top border-dark border-4"><span class="pallet-num text-dark"><?= number_format($pallet_totals['black'] ?? 0) ?></span><small class="fw-bold text-muted">PLASTIC BLACK</small></div></div>
    </div>

    <div class="row">
        <div class="col-md-7">
            <div class="card h-100 shadow-sm">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <div><i class="bi bi-box-fill me-2 text-mms-cyan"></i>STOCK BALANCE</div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-success fw-bold px-2 py-0.5" style="font-size:0.75rem;" onclick="exportStockToExcel()">
                            <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
                        </button>
                        <button class="btn btn-sm btn-outline-dark fw-bold px-2 py-0.5" style="font-size:0.75rem;" onclick="printStockTable()">
                            <i class="bi bi-printer me-1"></i> Cetak
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle" id="stockTable">
                        <thead>
                            <tr class="small text-muted">
                                <th class="ps-3">Category</th>
                                <th>Product</th>
                                <th>Expiry Date</th>
                                <th class="text-end pe-3">Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($stock_balance as $row): ?>
                            <tr>
                                <td class="ps-3"><span class="badge bg-white text-dark border border-secondary-subtle category-badge"><?= htmlspecialchars($row['category'] ?? '') ?></span></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($row['name'] ?? '') ?></td>
                                <td>
                                    <?php if(!empty($row['expiry_date'])): ?>
                                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1"><?= date('d M Y', strtotime($row['expiry_date'])) ?></span>
                                    <?php else: ?>
                                        <span class="text-danger small fw-bold"><i class="bi bi-x-circle me-1"></i>No Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3 fw-bold fs-5">
                                    <?php if($row['total_qty'] > 0): ?>
                                        <?= number_format($row['total_qty']) ?> <small class="text-muted fw-normal"><?= htmlspecialchars($row['uom'] ?? '') ?></small>
                                    <?php else: ?>
                                        <span class="text-danger fs-6">0</span> <small class="text-muted fw-normal"><?= htmlspecialchars($row['uom'] ?? '') ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card h-100 shadow-sm">
                <div class="card-header py-3"><i class="bi bi-truck me-2 text-mms-cyan"></i>RECENT ACTIVITY</div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="bg-light">
                            <tr class="small text-muted">
                                <th class="ps-3">Date</th>
                                <th>DO Number</th>
                                <th class="text-center">Pallets</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($inbound_logs as $log): ?>
                            <tr>
                                <td class="ps-3 small"><?= !empty($log['received_date']) ? date('d/m/y', strtotime($log['received_date'])) : '-' ?></td>
                                <td class="fw-bold text-mms-cyan"><?= htmlspecialchars($log['supplier_do'] ?? 'N/A') ?></td>
                                <td class="text-center fw-bold"><?= $log['total_pallets'] ?? 0 ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
    $(document).ready(function () {
        var table = $('#stockTable').DataTable({
            "pageLength": 10,
            "dom": '<"p-3 d-flex justify-content-between align-items-center"f>rt<"p-3 d-flex justify-content-between"ip>',
            "language": { 
                "search": "" ,
                "searchPlaceholder": "Filter stock..."
            },
            "order": [[0, 'asc'], [1, 'asc']]
        });

        // Filter mengikut kategori apabila butang diklik
        $('#categoryFilter .nav-link').on('click', function(e) {
            e.preventDefault();
            // Buang class 'active' dari semua butang, dan tambah pada butang yang diklik
            $('#categoryFilter .nav-link').removeClass('active');
            $(this).addClass('active');

            // Dapatkan kategori yang mahu ditapis
            var category = $(this).data('category');

            // Tapis jadual menggunakan DataTables API pada lajur ke-0 (Category)
            if (category === "") {
                table.column(0).search('').draw(); // Tunjuk semua
            } else {
                // Gunakan regex untuk padanan tepat (exact match)
                table.column(0).search('^' + category + '$', true, false).draw();
            }
        });
    });

    function exportStockToExcel() {
        const data = <?php echo json_encode($stock_balance); ?>;
        
        const ws_data = [
            ["Category", "Product", "Expiry Date", "Quantity", "UOM"]
        ];
        
        data.forEach(item => {
            let exp = item.expiry_date ? new Date(item.expiry_date).toLocaleDateString('ms-MY') : 'No Stock';
            ws_data.push([
                item.category || '',
                item.name || '',
                exp,
                parseInt(item.total_qty) || 0,
                item.uom || 'ctn'
            ]);
        });
        
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.aoa_to_sheet(ws_data);
        
        // Auto-width columns
        const max_len = ws_data[0].map((_, colIdx) => Math.max(...ws_data.map(row => String(row[colIdx] || '').length)));
        ws['!cols'] = max_len.map(w => ({ w: w + 3 }));
        
        XLSX.utils.book_append_sheet(wb, ws, "Stock Balance");
        XLSX.writeFile(wb, "MMS_Stock_Balance_" + new Date().toISOString().split('T')[0] + ".xlsx");
    }

    function printStockTable() {
        const printWindow = window.open('', '_blank', 'width=900,height=700');
        const dateStr = new Date().toLocaleString('ms-MY');
        
        let rowsHtml = '';
        const data = <?php echo json_encode($stock_balance); ?>;
        
        data.forEach((item, idx) => {
            let exp = item.expiry_date ? new Date(item.expiry_date).toLocaleDateString('ms-MY') : 'No Stock';
            rowsHtml += `
                <tr>
                    <td style="padding:8px; border:1px solid #cbd5e1; text-align:center;">${idx + 1}</td>
                    <td style="padding:8px; border:1px solid #cbd5e1; text-align:center;"><span style="font-size:0.75rem; font-weight:bold; padding:2px 8px; border-radius:10px; background:#f1f5f9;">${item.category || ''}</span></td>
                    <td style="padding:8px; border:1px solid #cbd5e1; font-weight:bold;">${item.name || ''}</td>
                    <td style="padding:8px; border:1px solid #cbd5e1; text-align:center;">${exp}</td>
                    <td style="padding:8px; border:1px solid #cbd5e1; text-align:right; font-weight:bold;">${Number(item.total_qty || 0).toLocaleString()} ${item.uom || 'ctn'}</td>
                </tr>
            `;
        });
        
        const htmlContent = `
            <html>
            <head>
                <title>Laporan Baki Stok (Stock Balance Report)</title>
                <style>
                    body { font-family: Arial, sans-serif; color: #1e293b; padding: 30px; line-height: 1.5; }
                    .header { text-align: center; margin-bottom: 25px; border-bottom: 3px double #cbd5e1; padding-bottom: 15px; }
                    .header h2 { margin: 0; color: #0f172a; }
                    .header p { margin: 5px 0 0 0; color: #64748b; font-size: 0.9rem; }
                    .info-bar { font-size: 0.85rem; color: #475569; margin-bottom: 20px; display: flex; justify-content: space-between; }
                    .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                    .items-table th { background: #f1f5f9; padding: 10px; border: 1px solid #cbd5e1; font-weight: bold; font-size: 0.85rem; }
                    .items-table td { font-size: 0.82rem; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2>MOO MOO SUPPLIES</h2>
                    <p>LAPORAN BAKI STOK SEMASA (CURRENT STOCK BALANCE)</p>
                </div>
                <div class="info-bar">
                    <span><strong>Tarikh Cetak:</strong> ${dateStr}</span>
                    <span><strong>Lokasi:</strong> Warehouse</span>
                </div>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width:50px; text-align:center;">No.</th>
                            <th style="width:100px; text-align:center;">Kategori</th>
                            <th>Nama Produk</th>
                            <th style="width:130px; text-align:center;">Tarikh Luput</th>
                            <th style="width:120px; text-align:right;">Jumlah Baki</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                </table>
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
</script>
<?php require_once 'includes/footer.php'; ?>