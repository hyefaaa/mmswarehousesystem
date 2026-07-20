<?php
// api/get_available_batches.php
require_once '../config/db.php';
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("
        SELECT 
            b.id AS batch_id, 
            b.batch_no, 
            b.qty_on_hand,
            p.name AS product_name,
            p.pack_size,
            sa.location_code AS assigned_location
        FROM inventory_batches b
        JOIN products p ON b.product_id = p.id
        LEFT JOIN slot_assignments sa ON b.id = sa.batch_id
        WHERE b.qty_on_hand > 0
        ORDER BY b.created_at DESC
    ");
    
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $batches]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>