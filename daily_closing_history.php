<?php
// daily_closing_history.php - View Daily Closing Stock Audit Matrix
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

// 1. Fetch the last 8 daily closing reports (oldest to newest)
$reports = $pdo->query("SELECT * FROM daily_closing_reports ORDER BY audit_date DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
$reports = array_reverse($reports); // show left-to-right (chronological)

// 2. Fetch saved physical counts for these reports
$report_ids = array_column($reports, 'id');
$matrix_data = []; // structure: [product_id][report_id] = physical_qty_ctn
if (!empty($report_ids)) {
    $placeholders = implode(',', array_fill(0, count($report_ids), '?'));
    $sql = "
        SELECT report_id, product_id, physical_qty_ctn, system_qty_ctn, variance_ctn
        FROM daily_closing_items
        WHERE report_id IN ($placeholders)
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($report_ids);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $matrix_data[$row['product_id']][$row['report_id']] = [
            'physical' => $row['physical_qty_ctn'],
            'system' => $row['system_qty_ctn'],
            'variance' => $row['variance_ctn']
        ];
    }
}

// 3. Fetch all active products
$products = $pdo->query("SELECT id, name, category, uom, pack_size FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// Function to classify products into sections matching the clipboard
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

// Group products
$sections = [
    '200ml / 180ml' => [],
    '1L / Bottles / Fresh' => [],
    '125ml / 100ml' => [],
    'Powders & Others' => [],
    'Other Products' => []
];
foreach ($products as $p) {
    $sec = getProductSection($p['name']);
    $sections[$sec][] = $p;
}

$page_title = 'Closing Stock History | Moo Moo Supplies';
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
    .grid-cell-value {
        font-weight: bold;
        font-size: 0.95rem;
    }
    .variance-indicator {
        font-size: 0.68rem;
        display: block;
        margin-top: 2px;
    }
    .var-ok { color: #059669; }
    .var-missing { color: #dc2626; }
    .var-extra { color: #2563eb; }
    
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
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<div class="page-header mb-4 no-print">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center flex-column flex-md-row gap-3">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-grid-3x3-gap-fill me-2 text-warning"></i>Closing Stock Matrix</h1>
                <p class="opacity-75 mb-0 fw-light">Historical view of physical stock counts compared across audit dates</p>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> Dashboard</a>
                <a href="daily_closing_report.php" class="btn btn-warning text-dark fw-bold"><i class="bi bi-plus-lg me-1"></i> New Audit</a>
                <button type="button" class="btn btn-success text-white fw-bold" onclick="exportMatrixToExcel()"><i class="bi bi-file-earmark-excel me-1"></i> Export Excel</button>
                <button type="button" class="btn btn-info text-white fw-bold" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print Matrix</button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    <div class="main-card card">
        
        <div class="text-center print-header mb-4 d-none d-print-block">
            <h3>MOO MOO SUPPLIES WAREHOUSE</h3>
            <h4>DAILY CLOSING STOCK RECORD MATRIX</h4>
            <hr>
        </div>

        <?php if (empty($reports)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 mb-2"></i>
                <p>Tiada rekod closing harian yang disimpan lagi. Sila lakukan audit penutupan pertama anda.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0" style="font-size: 0.85rem;">
                    <thead class="table-dark sticky-top">
                        <tr>
                            <th width="30%">Product Name</th>
                            <?php foreach ($reports as $r): ?>
                                <th class="text-center" style="min-width: 100px;">
                                    <div class="fw-bold"><?= date('d/m', strtotime($r['audit_date'])) ?></div>
                                    <div class="text-uppercase text-warning" style="font-size: 10px; font-weight: normal;">
                                        <?= htmlspecialchars($r['checked_by'] ?? '') ?>
                                    </div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sections as $secName => $items): ?>
                            <?php if (empty($items)) continue; ?>
                            <tr class="table-secondary fw-bold">
                                <td colspan="<?= count($reports) + 1 ?>" class="text-uppercase text-secondary ps-3">
                                    📁 <?= $secName ?> SECTION
                                </td>
                            </tr>
                            <?php foreach ($items as $p): ?>
                                <tr>
                                    <td class="fw-bold text-dark text-truncate" style="max-width: 250px;">
                                        <?= htmlspecialchars($p['name'] ?? '') ?>
                                        <small class="text-muted d-block small" style="font-size: 9px; font-weight: normal;">UOM: <?= $p['uom'] ?> | Pack: <?= $p['pack_size'] ?></small>
                                    </td>
                                    <?php foreach ($reports as $r): ?>
                                        <td class="text-center">
                                            <?php if (isset($matrix_data[$p['id']][$r['id']])): ?>
                                                <?php 
                                                $data = $matrix_data[$p['id']][$r['id']];
                                                $phys = $data['physical'];
                                                $v = $data['variance'];
                                                ?>
                                                <span class="grid-cell-value"><?= $phys ?></span>
                                                <?php if ($v != 0): ?>
                                                    <span class="variance-indicator <?= $v < 0 ? 'var-missing' : 'var-extra' ?>">
                                                        <?= $v < 0 ? $v : '+'.$v ?> ctn
                                                    </span>
                                                <?php else: ?>
                                                    <span class="variance-indicator var-ok">✔ OK</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function exportMatrixToExcel() {
    // Select the matrix table
    const table = document.querySelector('.table-bordered');
    if (!table) return;
    
    // Convert table to workbook
    const wb = XLSX.utils.table_to_book(table, { sheet: "Closing Stock Matrix" });
    
    // Save workbook
    XLSX.writeFile(wb, "MMS_Closing_Stock_Matrix_" + new Date().toISOString().split('T')[0] + ".xlsx");
}
</script>

<?php require_once 'includes/footer.php'; ?>
