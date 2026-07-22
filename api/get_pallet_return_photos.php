<?php
// api/get_pallet_return_photos.php
header('Content-Type: application/json');
require_once '../config/db.php';

$return_id = isset($_GET['return_id']) ? (int)$_GET['return_id'] : 0;

if ($return_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, photo_path, caption, created_at FROM pallet_return_photos WHERE return_id = ? ORDER BY id ASC");
    $stmt->execute([$return_id]);
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($photos);
} catch (Exception $e) {
    echo json_encode([]);
}
