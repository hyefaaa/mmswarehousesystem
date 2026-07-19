<?php
// api/get_warehouse_grid.php
require_once '../config/db.php';
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("
        SELECT 
            ws.location_code, 
            ws.zone, 
            ws.lane, 
            ws.row_num,
            p.name AS sku_name,
            p.pallet_capacity,
            b.batch_no,
            b.qty_on_hand AS quantity,
            b.created_at AS received_date_timestamp,
            b.pallet_id_tag,
            b.pallet_type
        FROM warehouse_slots ws
        LEFT JOIN inventory_batches b ON ws.batch_id = b.id
        LEFT JOIN products p ON b.product_id = p.id
        ORDER BY 
            CASE ws.zone
                WHEN 'PSS' THEN 1
                WHEN 'COM' THEN 2
                WHEN 'POW' THEN 3
            END,
            ws.lane ASC, 
            ws.row_num ASC
    ");
    
    $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $slots]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>