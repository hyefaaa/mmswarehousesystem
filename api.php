<?php
// api.php
// FIXED: Removed hardcoded credentials. Now uses config/db.php

header('Content-Type: application/json');

// Load shared DB config - same as all other pages
$configFile = __DIR__ . '/config/db.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    die(json_encode(['error' => 'Config file not found.']));
}
require_once $configFile;

// $pdo is now available from config/db.php
if (!isset($pdo) || $pdo === null) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed.']));
}

$action = $_GET['action'] ?? '';

// --- 1. GET SCHOOL DATA ---
if ($action == 'get_schools') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $role = $_SESSION['role'] ?? '';
    $username = $_SESSION['username'] ?? '';

    if ($role === 'dealer') {
        $stmt = $pdo->prepare("SELECT * FROM mms_logistik WHERE LOWER(dealer) = LOWER(?) ORDER BY plan_date ASC, name ASC");
        $stmt->execute([$username]);
    } else {
        $stmt = $pdo->query("SELECT * FROM mms_logistik ORDER BY plan_date ASC, name ASC");
    }
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// --- 2. SAVE / UPDATE SCHOOL DATA ---
if ($action == 'save_schools') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!empty($data)) {
        foreach ($data as $s) {
            $sql = "INSERT INTO mms_logistik 
                        (id, name, district, totalCartons, extraPacks, isDelivered, isDocSigned, dealer, plan_date, delivery_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        plan_date       = VALUES(plan_date), 
                        delivery_date   = VALUES(delivery_date), 
                        isDelivered     = VALUES(isDelivered), 
                        isDocSigned     = VALUES(isDocSigned)";
            
            $pdo->prepare($sql)->execute([
                $s['id'], 
                $s['name'], 
                $s['district'], 
                $s['totalCartons'], 
                $s['extraPacks'], 
                isset($s['isDelivered']) && $s['isDelivered'] ? 1 : 0, 
                isset($s['isDocSigned']) && $s['isDocSigned'] ? 1 : 0, 
                $s['dealer'] ?? 'admin', 
                $s['plan_date'], 
                $s['delivery_date'] ?? null
            ]);
        }
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'no_data']);
    }
    exit;
}

// --- 3. GET VEHICLE DATA ---
if ($action == 'get_vehicles') {
    $stmt = $pdo->query("SELECT * FROM mms_vehicles ORDER BY v_capacity DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// No matching action
echo json_encode(['error' => 'Invalid action']);
?>