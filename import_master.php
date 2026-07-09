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

if (!file_exists($csvFile)) {
    die("<h3 style='color:red'>Error: File '$csvFile' not found.</h3>");
}

echo "<h3>Starting Master Data Import...</h3>";

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
    if ($handle === FALSE) throw new Exception("Could not open CSV file.");

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
            echo "<pre>CSV Headers Found: " . print_r($header, true) . "</pre>";
            throw new Exception("Missing required column: '$req'. Please check CSV headers.");
        }
    }

    $count = 0;
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

    echo "<h2 style='color:green'>Success! Imported $count schools into Master Database.</h2>";

} catch (Exception $e) {
    // Safe Rollback
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo "<h2 style='color:red'>Import Failed!</h2>";
    echo "<p><strong>Reason:</strong> " . $e->getMessage() . "</p>";
    if (isset($row)) {
        echo "<p><strong>Failed at Row Data:</strong> " . json_encode($row) . "</p>";
    }
}
?>