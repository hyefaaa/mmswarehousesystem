<?php
// import_master.php
// FIXED VERSION: Handles Truncate correctly and shows real errors

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load Database Config
$configFile = __DIR__ . '/config/db.php';
if (!file_exists($configFile)) die("Config file not found.");
require_once $configFile;

// Verify Connection
if (!isset($pdo) || $pdo === null) die("Database connection failed.");

$csvFile = 'sample school data csv.csv';
$status = '';
$count = 0;
$error_msg = '';

if (!file_exists($csvFile)) {
    $error_msg = "Fail '$csvFile' tidak ditemui.";
} else {
    try {
        // 1. Fetch all HDs for mapping
        $hds = $pdo->query("SELECT id, name FROM hds")->fetchAll(PDO::FETCH_KEY_PAIR);

        function findHdId($csv_name, $db_hds) {
            $csv_name = strtoupper(trim($csv_name));
            if (empty($csv_name)) return null;

            foreach ($db_hds as $hd_id => $db_name) {
                $db_name_upper = strtoupper($db_name);
                if ($db_name_upper === $csv_name) return $hd_id;
                if (strpos($csv_name, $db_name_upper) !== false) return $hd_id;
                if (strpos($db_name_upper, $csv_name) !== false) return $hd_id;
            }
            return null; 
        }

        // 2. OPEN FILE
        $handle = fopen($csvFile, "r");
        if ($handle === FALSE) throw new Exception("Tidak dapat membuka fail CSV.");

        // 3. START TRANSACTION
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO schools 
            (school_code, school_name, address, no_tel, default_hd_id, zone_code) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            school_name = VALUES(school_name),
            address = VALUES(address),
            no_tel = VALUES(no_tel),
            default_hd_id = VALUES(default_hd_id),
            zone_code = VALUES(zone_code)
        ");

        // Get Headers
        $header = fgetcsv($handle);
        
        // Clean Headers (Remove byte order marks or whitespace)
        $header = array_map('trim', $header);
        // Remove BOM if exists
        $header[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header[0]);

        // Map Columns
        $colMap = array_flip($header);
        
        // Debug: Print headers if mapping fails
        $required = ['KOD SEKOLAH', 'NAMA SEKOLAH', 'DAERAH', 'NO TEL', 'Nama HD'];
        foreach ($required as $req) {
            if (!isset($colMap[$req])) {
                throw new Exception("Kolum '$req' tidak ditemui dalam fail CSV.");
            }
        }

        while (($row = fgetcsv($handle)) !== FALSE) {
            // Skip empty rows
            if (empty($row) || empty($row[0])) continue;

            // Extract Data
            $kod    = trim($row[$colMap['KOD SEKOLAH']]);
            $nama   = trim($row[$colMap['NAMA SEKOLAH']]);
            $daerah = trim($row[$colMap['DAERAH']]);
            $notel  = trim($row[$colMap['NO TEL']]);
            $hdName = trim($row[$colMap['Nama HD']]);

            // Find HD ID
            $hd_id = findHdId($hdName, $hds);

            // Construct Address
            $alamatPart = $row[$colMap['ALAMAT']] ?? '';
            $poskod     = $row[$colMap['POSKOD']] ?? '';
            $bandar     = $row[$colMap['BANDAR']] ?? '';
            $negeri     = $row[$colMap['NEGERI']] ?? '';

            $fullAddress = "$alamatPart, $poskod $bandar, $negeri";
            $fullAddress = trim(preg_replace('/,+/', ',', $fullAddress), ', ');

            $stmt->execute([$kod, $nama, $fullAddress, $notel, $hd_id, $daerah]);
            $count++;
        }

        $pdo->commit();
        fclose($handle);
        $status = 'success';

    } catch (Exception $e) {
        // Safe Rollback
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $status = 'failed';
        $error_msg = $e->getMessage();
    }
}

$page_title = 'Import Master Data | MMS';
require_once 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0" style="border-radius:16px; overflow:hidden;">
                <div class="card-header bg-navy text-white fw-bold py-3 text-center" style="letter-spacing:0.5px;">
                    📂 STATUS PENGIMPORTAN DATA INDUK PSS
                </div>
                <div class="card-body p-5 text-center">
                    <?php if ($status === 'success'): ?>
                        <div class="display-1 text-success mb-3">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <h3 class="fw-bold text-success">Import Berjaya!</h3>
                        <p class="text-muted mt-2 fs-5">
                            Sebanyak <strong><?= $count ?> sekolah</strong> telah berjaya diimport/dikemas kini ke dalam pangkalan data induk.
                        </p>
                        <div class="alert alert-success d-inline-block px-4 mt-3 small">
                            <i class="bi bi-info-circle-fill me-1"></i> Semua pemetaan zon dan dealer (HD) lalai telah berjaya diproses.
                        </div>
                    <?php else: ?>
                        <div class="display-1 text-danger mb-3">
                            <i class="bi bi-x-circle-fill"></i>
                        </div>
                        <h3 class="fw-bold text-danger">Import Gagal!</h3>
                        <p class="text-muted mt-2">
                            Proses import terhenti disebabkan ralat teknikal.
                        </p>
                        <div class="alert alert-danger d-inline-block px-4 mt-3">
                            <strong>Sebab:</strong> <?= htmlspecialchars($error_msg) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-4 pt-3 border-top">
                        <a href="index.php" class="btn btn-navy btn-lg px-5 fw-bold" style="border-radius:30px;">
                            🏠 KEMBALI KE DASHBOARD
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>