<?php
// save_import.php

// 1. SHOW ERRORS (For debugging)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// 2. INITIALIZE VARIABLES (Prevents "Undefined Variable" errors)
$pdo = null; 
$response = [];

try {
    // 3. LOAD CONFIGURATION
    $configFile = __DIR__ . '/config/db.php';
    
    if (!file_exists($configFile)) {
        throw new Exception("Config file not found: " . $configFile);
    }
    
    require_once $configFile;

    // 4. VERIFY CONNECTION
    // If db.php loaded correctly, $pdo should be set.
    if (!isset($pdo) || $pdo === null) {
        throw new Exception("Database connection failed. \$pdo variable is missing after loading config/db.php.");
    }

    // --- MAIN LOGIC ---

    // Get JSON Input
    $input = file_get_contents('php://input');
    if (!$input) throw new Exception("No data received. Ensure you are sending JSON data.");
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) throw new Exception("Invalid JSON: " . json_last_error_msg());

    // Helper Function
    function getMalayMonth($dateString) {
        $months = [
            1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MAC', 4 => 'APRIL',
            5 => 'MEI', 6 => 'JUN', 7 => 'JULAI', 8 => 'OGOS',
            9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DISEMBER'
        ];
        return $months[(int)date('n', strtotime($dateString))];
    }

    // Start Transaction
    $pdo->beginTransaction();

    // A. Batch Header
    $stmtBatch = $pdo->prepare("INSERT INTO import_batches (contract_name, last_delivery_date) VALUES (?, ?)");
    $stmtBatch->execute([$data['metadata']['contract_name'], $data['metadata']['last_delivery_date']]);
    $batchId = $pdo->lastInsertId();

    // B. CO Details
    $stmtCo = $pdo->prepare("INSERT INTO import_cos (batch_id, co_number, bil_tp, consumption_start, consumption_end) VALUES (?, ?, ?, ?, ?)");
    foreach ($data['metadata']['cos'] as $co) {
        $stmtCo->execute([$batchId, $co['co_number'], $co['bil_tp'], $co['consumption_start'], $co['consumption_end']]);
    }

    // C. Schools & SAP
    $primaryCO = $data['metadata']['cos'][0];
    $coNumber = $primaryCO['co_number'];
    $monthName = getMalayMonth($primaryCO['consumption_start']);
    $year = date('Y', strtotime($primaryCO['consumption_start']));

    $schools = $data['schools'];

    // Sort: District A-Z, then School Code A-Z
    usort($schools, function($a, $b) {
        $daerahCmp = strcmp($a['daerah'], $b['daerah']);
        return ($daerahCmp !== 0) ? $daerahCmp : strcmp($a['kod_sekolah'], $b['kod_sekolah']);
    });

    $stmtSchool = $pdo->prepare("INSERT INTO import_transactions (batch_id, kod_sekolah, bil_murid, no_sap) VALUES (?, ?, ?, ?)");
    $districtCounters = [];

    foreach ($schools as $school) {
        $daerah = strtoupper($school['daerah']);
        
        if (!isset($districtCounters[$daerah])) {
            $districtCounters[$daerah] = 1;
        }

        $sapPrefix = str_replace(' ', '', $daerah);
        $sapSeq = str_pad($districtCounters[$daerah], 3, '0', STR_PAD_LEFT);
        $sapSuffix = "({$coNumber}/{$monthName}/{$year})";
        $finalSAP = "{$sapPrefix}{$sapSeq} {$sapSuffix}";

        $stmtSchool->execute([$batchId, $school['kod_sekolah'], (int)$school['bil_murid'], $finalSAP]);
        
        $districtCounters[$daerah]++;
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'batch_id' => $batchId, 'message' => 'Import Successful!']);

} catch (Exception $e) {
    // 5. SAFER ROLLBACK (This fixes your Fatal Error)
    // We check if $pdo exists AND if a transaction is actually running
    if ($pdo !== null && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Return specific error details
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()), 
        'line' => $e->getLine()
    ]);
}
?>