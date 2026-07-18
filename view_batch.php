<?php
// view_batch.php - MMS UPGRADED INTERFACE (DARK BLUE THEME)
ini_set('display_errors', 0);
error_reporting(E_ALL);

$configFile = __DIR__ . '/config/db.php';
if (!file_exists($configFile)) die("Config file not found.");
require_once $configFile;

if (!isset($pdo) || $pdo === null) die("Database connection failed.");

$batchId = $_GET['batch_id'] ?? null;
$batches = [];

try {
    $stmt = $pdo->query("SELECT * FROM import_batches ORDER BY created_at DESC");
    $batches = $stmt->fetchAll();
    if (!$batchId && count($batches) > 0) {
        $batchId = $batches[0]['id'];
    }
} catch (Exception $e) {
    die("Error fetching batches: " . $e->getMessage());
}

$reportData = [];
$summary = ['total_schools' => 0, 'total_students' => 0];

if ($batchId) {
    $sql = "
        SELECT 
            t.no_sap, t.kod_sekolah, t.bil_murid,
            s.school_name AS nama_sekolah, s.address AS alamat, s.no_tel, h.name AS nama_hd, s.zone_code AS daerah_master
        FROM import_transactions t
        LEFT JOIN schools s ON t.kod_sekolah = s.school_code
        LEFT JOIN hds h ON s.default_hd_id = h.id
        WHERE t.batch_id = ?
        ORDER BY t.no_sap ASC
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$batchId]);
        $reportData = $stmt->fetchAll();
        
        $summary['total_schools'] = count($reportData);
        foreach($reportData as $r) {
            $summary['total_students'] += $r['bil_murid'];
        }
    } catch (Exception $e) {
        die("Error fetching report: " . $e->getMessage());
    }
}


$page_title = 'MMS | Monthly Order Report';
require_once 'includes/header.php';
?>
<style>
    @media print {
        .no-print { display: none !important; }
        .mms-navbar { display: none !important; }
        body { background: white; padding: 0; }
        .print-container { width: 100%; max-width: 100%; margin: 0; box-shadow: none; border: 1px solid #dee2e6; }
        table { font-size: 10px; }
        .table-dark th { background-color: var(--mms-navy) !important; color: white !important; -webkit-print-color-adjust: exact; }
    }
</style>

<div class="page-header mb-4 no-print">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center flex-column flex-md-row gap-3">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-file-earmark-spreadsheet-fill me-2 text-warning"></i>Monthly Order Report</h1>
                <p class="opacity-75 mb-0 fw-light">Sistem Pengurusan Batch MMS - View Monthly SAP Reports</p>
            </div>
            
            <div class="d-flex gap-3 align-items-center">
                <form method="GET" class="d-flex align-items-center m-0 bg-white bg-opacity-10 p-1.5 rounded-3 border border-white border-opacity-20">
                    <select name="batch_id" onchange="this.form.submit()" class="form-select form-select-sm border-0 bg-transparent text-white fw-bold shadow-none" style="min-width: 250px; color-scheme: dark; cursor: pointer;">
                        <?php foreach ($batches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $b['id'] == $batchId ? 'selected' : '' ?> class="text-dark">
                                Batch #<?= $b['id'] ?> - <?= htmlspecialchars($b['contract_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                
                <button onclick="window.print()" class="btn btn-info text-white fw-bold shadow-sm">
                    <i class="bi bi-printer me-2"></i> Cetak Laporan
                </button>
                <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> Dashboard</a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">

    <div class="row g-4 mb-4 no-print">
        <div class="col-md-6">
            <div class="card shadow-sm h-100 border-start border-4 border-navy" style="border-left-color: var(--mms-navy) !important;">
                <div class="card-body">
                    <p class="text-muted small fw-bold text-uppercase mb-1">Jumlah Sekolah</p>
                    <h2 class="fw-bold text-dark mb-0"><?= number_format($summary['total_schools']) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm h-100 border-start border-4 border-success">
                <div class="card-body">
                    <p class="text-muted small fw-bold text-uppercase mb-1">Jumlah Keseluruhan Murid</p>
                    <h2 class="fw-bold text-success mb-0"><?= number_format($summary['total_students']) ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm print-container">
        <div class="d-none d-print-block p-4 border-bottom bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold text-navy mb-0">MOO MOO SUPPLIES</h4>
                    <p class="text-muted small fw-bold mb-0">LAPORAN PESANAN BATCH #<?= $batchId ?></p>
                </div>
                <div class="text-end small text-muted text-uppercase">
                    Generated on: <?= date('d/m/Y H:i') ?>
                </div>
            </div>
        </div>

        <!-- Search Bar (Screen Only) -->
        <div class="p-3 border-bottom bg-white d-flex flex-column flex-md-row justify-content-between align-items-center no-print">
            <h5 class="mb-3 mb-md-0 fw-bold text-navy"><i class="bi bi-list-ul me-2"></i>Senarai Sekolah</h5>
            <div class="input-group shadow-sm" style="max-width: 400px;">
                <span class="input-group-text bg-light border-primary border-end-0 text-primary"><i class="bi bi-search"></i></span>
                <input type="text" id="tableSearch" class="form-control border-primary border-start-0" placeholder="Cari Kod, Nama, Handler...">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark" style="background-color: var(--mms-navy);">
                    <tr>
                        <th class="ps-4">No. SAP</th>
                        <th>Kod</th>
                        <th>Nama Sekolah</th>
                        <th>Alamat Penghantaran</th>
                        <th class="text-center">Bil. Murid</th>
                        <th>Handler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($reportData) > 0): 
 foreach ($reportData as $row): ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="fw-bold text-primary"><?= htmlspecialchars($row['no_sap']) ?></span>
                                </td>
                                <td class="small fw-medium text-muted"><?= htmlspecialchars($row['kod_sekolah']) ?></td>
                                <td>
                                    <div class="small fw-bold text-dark text-uppercase"><?= htmlspecialchars($row['nama_sekolah'] ?? 'TIADA DALAM MASTER') ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($row['no_tel'] ?? '-') ?></div>
                                </td>
                                <td>
                                    <p class="mb-0 text-muted" style="font-size: 0.8rem; max-width: 250px;"><?= htmlspecialchars($row['alamat'] ?? '-') ?></p>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold fs-5"><?= number_format($row['bil_murid']) ?></span>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-secondary fst-italic"><?= htmlspecialchars($row['nama_hd'] ?? '-') ?></div>
                                </td>
                            </tr>
                        <?php endforeach; 
 else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <p class="text-muted fw-medium mb-0">Tiada rekod dijumpai untuk batch ini.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="card-footer bg-light p-4 text-end d-print-block">
            <div class="small fw-bold text-dark text-uppercase">
                Jumlah Keseluruhan : <span class="ms-2 fs-4 text-navy fw-bold text-decoration-underline"><?= number_format($summary['total_students']) ?> Murid</span>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('tableSearch');
    if(searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('.table-responsive tbody tr');
            
            rows.forEach(row => {
                // Ignore "No records found" row if present
                if(row.cells.length === 1) return; 
                
                // Read all text inside the row
                const text = row.textContent.toLowerCase();
                if(text.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>