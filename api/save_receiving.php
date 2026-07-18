<?php
// api/save_receiving.php
// UPDATED: Added auth checks and mapped short pallet codes to descriptive names.

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (!file_exists('../config/db.php')) {
    die("❌ Configuration File Not Found.");
}
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || !is_staff_role($_SESSION['role'] ?? '')) {
    http_response_code(403);
    die(json_encode(['error' => 'Akses dinafikan. Hanya staf dibenarkan.']));
}

function convertDate($dateStr) {
    if (empty($dateStr)) return null;
    if (strpos($dateStr, '-') !== false) return $dateStr;
    $date = DateTime::createFromFormat('d/m/Y', $dateStr);
    return $date ? $date->format('Y-m-d') : null;
}

$category = $_POST['category'] ?? 'PSS';
$product_id = $_POST['product_id'] ?? 0;
$batch_no = $_POST['batch_no'] ?? '';
$expiry_raw = $_POST['expiry_date'] ?? null;
$expiry_date = convertDate($expiry_raw);
$qty = $_POST['qty'] ?? 0;
$pallet_id_tag = $_POST['pallet_id_tag'] ?? '';
$pallet_type_raw = $_POST['pallet_type'] ?? 'none';
$temp_truck = $_POST['temp_truck'] ?? 0;
$temp_stock = $_POST['temp_stock'] ?? 0;

$pallet_map = [
    'loscam_red' => 'Loscam Red',
    'lhp_green' => 'LHP Green',
    'ffm_orange' => 'FFM Orange',
    'ffm_green' => 'FFM Green',
    'plain_wood' => 'Plain Wood',
    'plastic_black' => 'Plastic Black'
];
$pallet_type = $pallet_map[strtolower($pallet_type_raw)] ?? 'No Pallet';

if ($qty <= 0 || empty($product_id)) {
    die("Error: Invalid Quantity or Product ID.");
}

try {
    $pdo->beginTransaction();

    // Pallet Logic (Single Item Mode = 1 Pallet usually)
    $qty_red = ($pallet_type == 'Loscam Red') ? 1 : 0;
    $qty_lhp_green = ($pallet_type == 'LHP Green') ? 1 : 0;
    $qty_ffm_orange = ($pallet_type == 'FFM Orange') ? 1 : 0;
    $qty_ffm_green = ($pallet_type == 'FFM Green') ? 1 : 0;
    $qty_black = ($pallet_type == 'Plastic Black') ? 1 : 0;
    
    $stmt = $pdo->prepare("INSERT INTO inbound_logs 
        (category, received_date, temp_truck, temp_stock, pallet_qty_loscam_red, pallet_qty_ffm_orange, pallet_qty_plastic_black) 
        VALUES (?, NOW(), ?, ?, ?, ?, ?)");
    
    $stmt->execute([$category, $temp_truck, $temp_stock, $qty_red, $qty_ffm_orange, $qty_black]);
    
    $inbound_id = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO inbound_items (inbound_id, product_id, batch_no, qty_received) VALUES (?, ?, ?, ?)");
    $stmt->execute([$inbound_id, $product_id, $batch_no, $qty]);

    $stmt = $pdo->prepare("INSERT INTO inventory_batches 
        (product_id, batch_no, expiry_date, qty_on_hand, pallet_type, pallet_id_tag, location_status) 
        VALUES (?, ?, ?, ?, ?, ?, 'Warehouse')");
    $stmt->execute([$product_id, $batch_no, $expiry_date, $qty, $pallet_type, $pallet_id_tag]);

    $pdo->commit();

    echo "<script>alert('✅ Stock Received Successfully! (Inbound ID: $inbound_id)'); window.location.href='../receiving.php';</script>";

} catch (Exception $e) {
    $pdo->rollBack();
    die("Database Error: " . $e->getMessage());
}
?>