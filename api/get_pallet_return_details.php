<?php
// api/get_pallet_return_details.php
header('Content-Type: application/json');
require_once '../config/db.php';

$return_id = isset($_GET['return_id']) ? (int)$_GET['return_id'] : 0;

if ($return_id <= 0) {
    echo json_encode(['items' => [], 'photos' => []]);
    exit;
}

try {
    $stmtItems = $pdo->prepare("SELECT * FROM pallet_return_items WHERE return_id = ? ORDER BY id ASC");
    $stmtItems->execute([$return_id]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    $stmtPhotos = $pdo->prepare("SELECT * FROM pallet_return_photos WHERE return_id = ? ORDER BY id ASC");
    $stmtPhotos->execute([$return_id]);
    $photos = $stmtPhotos->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['items' => $items, 'photos' => $photos]);
} catch (Exception $e) {
    echo json_encode(['items' => [], 'photos' => []]);
}
