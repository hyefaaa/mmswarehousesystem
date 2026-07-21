<?php
// api/get_warehouse_grid.php
require_once '../config/db.php';
header('Content-Type: application/json');

try {
    // Dapatkan semua slot fizikal gudang
    $slots_stmt = $pdo->query("SELECT location_code, zone, lane, row_num FROM warehouse_slots ORDER BY location_code ASC");
    $slots = $slots_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Dapatkan tugasan aktif daripada slot_assignments
    $assign_stmt = $pdo->query("
        SELECT 
            sa.location_code,
            sa.batch_id,
            b.batch_no,
            b.product_id AS product_id,
            b.qty_on_hand AS quantity,
            p.name AS sku_name,
            p.pallet_capacity,
            p.pack_size,
            b.pallet_type
        FROM slot_assignments sa
        JOIN inventory_batches b ON sa.batch_id = b.id
        JOIN products p ON b.product_id = p.id
        WHERE b.qty_on_hand > 0
    ");
    $assignments = $assign_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Kumpulkan tugasan mengikut location_code
    $grouped = [];
    foreach ($assignments as $a) {
        $grouped[$a['location_code']][] = $a;
    }

    // Pasangkan senarai tugasan ke dalam setiap slot
    foreach ($slots as &$slot) {
        $slot['items'] = $grouped[$slot['location_code']] ?? [];
    }

    echo json_encode(['status' => 'success', 'data' => $slots]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>