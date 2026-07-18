<?php
// api/get_outbound_details.php
// Mengambil butiran item bagi DO Komersial atau PSS untuk paparan modal secara AJAX

header('Content-Type: application/json');
ini_set('display_errors', 0);
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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

if ($id <= 0 || empty($category)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parameter tidak lengkap.']);
    exit;
}

try {
    $items = [];
    if ($category === 'Commercial') {
        // Ambil butiran item Commercial Outbound
        $stmt = $pdo->prepare("
            SELECT p.name as product_name, i.batch, i.qty 
            FROM outbound_items i
            JOIN products p ON i.product_id = p.id
            WHERE i.outbound_id = ?
        ");
        $stmt->execute([$id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $items[] = [
                'product_name' => $r['product_name'],
                'batch_no' => $r['batch'] ?: 'Tiada Kod',
                'qty' => (int)$r['qty']
            ];
        }
    } elseif ($category === 'PSS School') {
        // Ambil butiran item PSS Outbound
        $stmt = $pdo->prepare("
            SELECT p.name as product_name, b.batch_no, i.qty_cartons 
            FROM delivery_items_pss i
            JOIN inventory_batches b ON i.inventory_batch_id = b.id
            JOIN products p ON b.product_id = p.id
            WHERE i.delivery_id = ?
        ");
        $stmt->execute([$id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $items[] = [
                'product_name' => $r['product_name'],
                'batch_no' => $r['batch_no'] ?: 'Tiada Kod',
                'qty' => (int)$r['qty_cartons']
            ];
        }
    }

    echo json_encode($items);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
exit;
?>
