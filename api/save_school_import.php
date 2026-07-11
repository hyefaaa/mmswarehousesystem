<?php
// api/save_school_import.php
// Handles CSV Import for Schools
// UPDATED: Fixed "No active transaction" error (Moved DDL outside transaction) & removed deprecated settings

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../config/db.php';

// REMOVED: ini_set('auto_detect_line_endings', true); (Deprecated in PHP 8.1+)

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != 0) {
    die("❌ Error: No file uploaded.");
}

$file = $_FILES['csv_file']['tmp_name'];
$handle = fopen($file, "r");

if ($handle === FALSE) {
    die("❌ Error: Cannot open file.");
}

// --- CRITICAL FIX: EXPAND DATABASE COLUMN ---
// MOVED OUTSIDE TRANSACTION: DDL statements (ALTER TABLE) cause an implicit commit in MySQL.
// Must run this before starting the transaction.
try {
    $pdo->exec("ALTER TABLE schools MODIFY COLUMN zone_code VARCHAR(100)");
} catch (Exception $e) {
    // Ignore error if permission denied or column issues
}
// ---------------------------------------------

try {
    $pdo->beginTransaction();

    // 1. Get Header Row (Unlimited Length)
    $header = fgetcsv($handle, 0, ","); 
    
    // --- BOM FIX ---
    if (isset($header[0])) {
        $bom = pack('H*','EFBBBF');
        $header[0] = preg_replace("/^$bom/", '', $header[0]);
    }

    // Normalize headers
    $header = array_map('trim', $header);
    $header = array_map('strtoupper', $header);
    
    // Helper to dynamically find header index with variations
    function findHeaderIndex($needles, $header) {
        foreach ($needles as $needle) {
            $idx = array_search($needle, $header);
            if ($idx !== false) return $idx;
        }
        // Substring fallback (case-insensitive checks since headers are normalized to uppercase)
        foreach ($needles as $needle) {
            foreach ($header as $index => $col) {
                if ($col !== '' && (strpos($col, $needle) !== false || strpos($needle, $col) !== false)) {
                    return $index;
                }
            }
        }
        return false;
    }

    // Find Indexes with robust variations
    $idx_code     = findHeaderIndex(['KOD SEKOLAH', 'SCHOOL CODE', 'SCHOOL_CODE', 'KOD_SEKOLAH', 'CODE'], $header);
    $idx_name     = findHeaderIndex(['NAMA SEKOLAH', 'SCHOOL NAME', 'SCHOOL_NAME', 'NAME'], $header);
    $idx_stud     = findHeaderIndex(['BIL PELAJAR', 'BIL MURID', 'STUDENT COUNT', 'STUDENTS', 'BIL_PELAJAR', 'BIL_MURID', 'TOTAL STUDENTS'], $header);
    $idx_addr     = findHeaderIndex(['ALAMAT', 'ADDRESS', 'ALAMAT SEKOLAH'], $header);

    // TARGET COLUMNS
    $idx_zon      = findHeaderIndex(['PPD', 'ZONE', 'ZON', 'KOD PPD', 'KOD_PPD'], $header);     
    $idx_hd       = findHeaderIndex(['NAMA HD', 'HUB DEALER', 'DEALER', 'HD NAME', 'HD', 'NAMA_HD'], $header); 

    // Contract Columns
    $idx_co       = findHeaderIndex(['CO NUMBER', 'CO NO', 'NO CO', 'CO_NUMBER', 'CO_NO'], $header);
    $idx_sap      = findHeaderIndex(['NO SAP', 'SAP NO', 'NO_SAP', 'SAP_NO', 'SAP'], $header);
    $idx_tender   = findHeaderIndex(['NO TENDER', 'TENDER NO', 'NO_TENDER', 'TENDER_NO', 'TENDER'], $header);
    $idx_contract = findHeaderIndex(['NO KONTRAK', 'CONTRACT NO', 'NO_KONTRAK', 'CONTRACT_NO', 'CONTRACT'], $header);

    if ($idx_code === false) {
        die("❌ Error: Could not find 'KOD SEKOLAH' or 'SCHOOL CODE' column in CSV.");
    }

    // 2. Fetch all HDs
    $hds = $pdo->query("SELECT id, name FROM hds")->fetchAll(PDO::FETCH_KEY_PAIR); 
    
    // Helper to match HD names
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

    $updated_count = 0;

    // 3. Loop Rows (Unlimited Length)
    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) { 
        if (!isset($data[$idx_code])) continue;

        $code = trim($data[$idx_code]);
        if (empty($code)) continue; 

        $name = ($idx_name !== false && isset($data[$idx_name])) ? trim($data[$idx_name]) : '';
        $students = ($idx_stud !== false && isset($data[$idx_stud])) ? (int)$data[$idx_stud] : 0;
        $address = ($idx_addr !== false && isset($data[$idx_addr])) ? trim($data[$idx_addr]) : '';
        
        // Capture PPD/Zone
        $zone = ($idx_zon !== false && isset($data[$idx_zon])) ? strtoupper(trim($data[$idx_zon])) : '';
        
        // Capture HD
        $hd_name_csv = ($idx_hd !== false && isset($data[$idx_hd])) ? trim($data[$idx_hd]) : '';
        $hd_id = findHdId($hd_name_csv, $hds);

        // Capture Contract Info
        $co_num   = ($idx_co !== false && isset($data[$idx_co])) ? trim($data[$idx_co]) : '';
        $sap_no   = ($idx_sap !== false && isset($data[$idx_sap])) ? trim($data[$idx_sap]) : '';
        $tend_no  = ($idx_tender !== false && isset($data[$idx_tender])) ? trim($data[$idx_tender]) : '';
        $cont_no  = ($idx_contract !== false && isset($data[$idx_contract])) ? trim($data[$idx_contract]) : '';

        // UPSERT QUERY
        $sql = "INSERT INTO schools 
                (school_code, school_name, student_count, zone_code, default_hd_id, address, 
                 co_number, sap_no, tender_no, contract_no)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                school_name = VALUES(school_name),
                student_count = VALUES(student_count),
                zone_code = VALUES(zone_code),
                default_hd_id = VALUES(default_hd_id),
                address = VALUES(address),
                co_number = VALUES(co_number),
                sap_no = VALUES(sap_no),
                tender_no = VALUES(tender_no),
                contract_no = VALUES(contract_no)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$code, $name, $students, $zone, $hd_id, $address, $co_num, $sap_no, $tend_no, $cont_no]);
        
        $updated_count++;
    }

    fclose($handle);
    $pdo->commit();

    echo "<script>
        alert('✅ Success! Processed $updated_count schools. Database column resized. PPD Fixed.');
        window.location.href='../import_schools.php';
    </script>";

} catch (Exception $e) {
    // Only rollback if transaction is active
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fclose($handle);
    die("Database Error: " . $e->getMessage());
}
?>