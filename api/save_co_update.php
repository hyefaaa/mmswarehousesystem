<?php
// api/save_co_update.php
// UPDATED: Now uses shared PDO config and matches schools schema

header('Content-Type: application/json');
ini_set('display_errors', 0); // Suppress HTML errors in JSON response
error_reporting(E_ALL);

// 1. Load Central Database Config (PDO)
if (!file_exists('../config/db.php')) {
    echo json_encode(['success' => false, 'message' => 'Config file not found']);
    exit;
}
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || !is_staff_role($_SESSION['role'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit;
}

// 2. Capture POST Data
$contract_no = $_POST['contract_no'] ?? '';
$co_no       = $_POST['co_no'] ?? '';
$month_sess  = $_POST['month_session'] ?? '';

if (!$contract_no || !$co_no) {
    echo json_encode(['success' => false, 'message' => 'Missing Contract or CO Number']);
    exit;
}

// 3. Handle File Upload
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != 0) {
    echo json_encode(['success' => false, 'message' => 'File upload failed']);
    exit;
}

$file = fopen($_FILES['csv_file']['tmp_name'], 'r');

$updated_count = 0;
$skipped_count = 0;
$errors = [];

// 4. Find the Header Row
$header_found = false;
$col_map = []; 

try {
    $pdo->beginTransaction();

    // PREPARE STATEMENTS ONCE (Optimization)
    // We check against 'schools' because that is the correct table name in your system
    $check_stmt = $pdo->prepare("SELECT id FROM schools WHERE school_code = ?");
    
    $insert_stmt = $pdo->prepare("INSERT INTO co_entitlements 
            (school_code, contract_no, co_no, month, student_count) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE student_count = VALUES(student_count)");

    while (($row = fgetcsv($file)) !== FALSE) {
        // Check if this is the header row
        if (strtoupper($row[0]) == 'BIL' && !$header_found) {
            $header_found = true;
            
            // Map columns dynamically with robust variations
            foreach ($row as $index => $colName) {
                $colName = strtoupper(trim($colName));
                if (strpos($colName, 'KOD SEKOLAH') !== false || strpos($colName, 'SCHOOL CODE') !== false || strpos($colName, 'SCHOOL_CODE') !== false || $colName === 'CODE') {
                    $col_map['code'] = $index;
                }
                if (strpos($colName, 'BIL MURID') !== false || strpos($colName, 'BIL PELAJAR') !== false || strpos($colName, 'STUDENT COUNT') !== false || strpos($colName, 'STUDENTS') !== false || $colName === 'COUNT') {
                    $col_map['count'] = $index;
                }
            }
            
            // Fallback defaults
            if (!isset($col_map['code'])) $col_map['code'] = 2; 
            if (!isset($col_map['count'])) $col_map['count'] = 4; 
            
            continue; 
        }

        // Process Data Rows
        if ($header_found) {
            if (count($row) < 3) continue;

            $school_code = trim($row[$col_map['code']]);
            
            // Clean the number
            $raw_count = $row[$col_map['count']];
            $clean_count = preg_replace('/[^0-9]/', '', $raw_count);
            $student_count = (int)$clean_count;

            if (empty($school_code) || strlen($school_code) > 10) continue; 

            // 5. CHECK IF SCHOOL EXISTS IN MAIN DB (Using PDO)
            $check_stmt->execute([$school_code]);
            $school_exists = $check_stmt->fetch();

            if ($school_exists) {
                // School exists! UPSERT the entitlement
                try {
                    $insert_stmt->execute([$school_code, $contract_no, $co_no, $month_sess, $student_count]);
                    $updated_count++;
                } catch (PDOException $e) {
                    $errors[] = "DB Error for $school_code: " . $e->getMessage();
                }
            } else {
                $skipped_count++;
                $errors[] = "Skipped $school_code (Not in Master School List)";
            }
        }
    }

    fclose($file);

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'updated' => $updated_count,
        'skipped' => $skipped_count,
        'errors'  => array_slice($errors, 0, 50) 
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>