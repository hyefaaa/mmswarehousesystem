<?php
// api/get_batches.php
// Mengambil senarai batch aktif bagi suatu produk tertentu secara dinamik untuk carian Outbound

header('Content-Type: application/json');

ini_set('display_errors', 0); // Sembunyikan ralat untuk paparan JSON bersih
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Akses dinafikan.']);
    exit;
}

if (!file_exists('../config/db.php')) {
    http_response_code(500);
    echo json_encode(['error' => 'Konfigurasi pangkalan data tidak ditemui.']);
    exit;
}
require_once '../config/db.php';

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if ($product_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    // Ambil semua batch yang mempunyai baki stok > 0 disusun ikut FIFO/FEFO (tarikh luput terawal)
    $stmt = $pdo->prepare("
        SELECT id, batch_no, expiry_date, qty_on_hand 
        FROM inventory_batches 
        WHERE product_id = ? AND qty_on_hand > 0 AND location_status = 'Warehouse'
        ORDER BY expiry_date ASC
    ");
    $stmt->execute([$product_id]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format tarikh untuk kemudahan pengguna
    $formatted_batches = array_map(function($b) {
        $exp_formatted = !empty($b['expiry_date']) ? date('d/m/Y', strtotime($b['expiry_date'])) : 'Tiada Tarikh';
        return [
            'id' => $b['id'],
            'batch_no' => $b['batch_no'] ?: 'Tiada Kod',
            'expiry_date' => $exp_formatted,
            'qty_on_hand' => $b['qty_on_hand']
        ];
    }, $batches);

    echo json_encode($formatted_batches);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
exit;
?>
