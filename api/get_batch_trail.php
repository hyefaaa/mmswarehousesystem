<?php
// api/get_batch_trail.php
// MMS Warehouse System | Moo Moo Supplies
// Endpoint to retrieve complete movement history of a specific batch/product

header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth check
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sesi tamat. Sila log masuk semula.']);
    exit;
}

$batch_no = trim($_GET['batch_no'] ?? '');
$product_id = (int)($_GET['product_id'] ?? 0);

if (empty($batch_no) || $product_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Batch No and Product ID are required.']);
    exit;
}

try {
    // 1. Get Product Metadata
    $prod_stmt = $pdo->prepare("SELECT name, category, uom, pack_size, pcs_per_carton, barcode FROM products WHERE id = ?");
    $prod_stmt->execute([$product_id]);
    $product = $prod_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        throw new Exception("Product ID $product_id not found.");
    }

    $events = [];

    // 2. Fetch Inbound/GRN history
    $grn_stmt = $pdo->prepare("
        SELECT 
            il.received_date,
            il.supplier_do,
            il.transporter_name,
            il.driver_name,
            il.vehicle_plate,
            ii.qty_received
        FROM inbound_items ii
        JOIN inbound_logs il ON ii.inbound_id = il.id
        WHERE ii.batch_no = ? AND ii.product_id = ?
        ORDER BY il.received_date ASC
    ");
    $grn_stmt->execute([$batch_no, $product_id]);
    $grn_records = $grn_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($grn_records as $r) {
        $events[] = [
            'timestamp' => $r['received_date'],
            'type' => 'inbound',
            'title' => 'Stok Diterima (GRN)',
            'ref' => $r['supplier_do'],
            'qty' => $r['qty_received'],
            'meta' => [
                'Transporter' => $r['transporter_name'] ?: 'N/A',
                'Pemandu' => $r['driver_name'] ?: 'N/A',
                'No. Kenderaan' => $r['vehicle_plate'] ?: 'N/A'
            ]
        ];
    }

    // 3. Fetch Stock Transfer logs
    $transfer_stmt = $pdo->prepare("
        SELECT 
            sl.created_at,
            sl.username,
            sl.details
        FROM system_logs sl
        WHERE sl.action = 'Stock Transfer'
          AND sl.target_table = 'inventory_batches'
          AND sl.record_id IN (
              SELECT id FROM inventory_batches WHERE batch_no = ? AND product_id = ?
          )
        ORDER BY sl.created_at ASC
    ");
    $transfer_stmt->execute([$batch_no, $product_id]);
    $transfer_records = $transfer_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($transfer_records as $r) {
        $events[] = [
            'timestamp' => $r['created_at'],
            'type' => 'transfer',
            'title' => 'Pindahan Stok',
            'ref' => 'Oleh: ' . $r['username'],
            'qty' => null,
            'meta' => [
                'Butiran' => $r['details']
            ]
        ];
    }

    // 4. Fetch PSS Dispatches
    $pss_stmt = $pdo->prepare("
        SELECT 
            d.do_number,
            d.delivery_date,
            d.vehicle_plate,
            di.qty_cartons,
            h.name AS dealer_name,
            s.school_name,
            d.status
        FROM delivery_items_pss di
        JOIN deliveries_pss d ON di.delivery_id = d.id
        JOIN inventory_batches ib ON di.inventory_batch_id = ib.id
        LEFT JOIN hds h ON d.hd_id = h.id
        LEFT JOIN schools s ON d.school_id = s.id
        WHERE ib.batch_no = ? AND ib.product_id = ?
        ORDER BY d.delivery_date ASC
    ");
    $pss_stmt->execute([$batch_no, $product_id]);
    $pss_records = $pss_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pss_records as $r) {
        $events[] = [
            'timestamp' => $r['delivery_date'] . ' 00:00:00', // PSS DO only has date
            'type' => 'outbound_pss',
            'title' => 'Pengeluaran PSS (DO)',
            'ref' => $r['do_number'],
            'qty' => $r['qty_cartons'],
            'meta' => [
                'Hub Dealer' => $r['dealer_name'] ?: 'N/A',
                'Sekolah' => $r['school_name'] ?: 'N/A',
                'Lori' => $r['vehicle_plate'] ?: 'N/A',
                'Status DO' => $r['status']
            ]
        ];
    }

    // 5. Fetch Commercial/Jomcha Dispatches
    $comm_stmt = $pdo->prepare("
        SELECT 
            ol.date AS date_dispatched,
            ol.doc_ref,
            ol.customer,
            ol.vehicle,
            ol.category,
            oi.qty AS qty_cartons
        FROM outbound_items oi
        JOIN outbound_logs ol ON oi.outbound_id = ol.id
        WHERE oi.batch = ? AND oi.product_id = ?
        ORDER BY ol.date ASC
    ");
    $comm_stmt->execute([$batch_no, $product_id]);
    $comm_records = $comm_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($comm_records as $r) {
        $events[] = [
            'timestamp' => $r['date_dispatched'] . ' 00:00:00',
            'type' => 'outbound_commercial',
            'title' => 'Pengeluaran ' . ($r['category'] === 'Jomcha' ? 'Jomcha' : 'Komersial'),
            'ref' => $r['doc_ref'],
            'qty' => $r['qty_cartons'],
            'meta' => [
                'Pelanggan' => $r['customer'] ?: 'N/A',
                'Kenderaan' => $r['vehicle'] ?: 'N/A'
            ]
        ];
    }

    // Sort events chronologically (oldest first)
    usort($events, function($a, $b) {
        return strcmp($a['timestamp'], $b['timestamp']);
    });

    if (empty($events)) {
        // Fallback: Look up earliest record in inventory_batches
        $earliest_stmt = $pdo->prepare("
            SELECT created_at, qty_on_hand, pallet_type, pallet_id_tag, location_status
            FROM inventory_batches
            WHERE batch_no = ? AND product_id = ?
            ORDER BY created_at ASC
            LIMIT 1
        ");
        $earliest_stmt->execute([$batch_no, $product_id]);
        $earliest = $earliest_stmt->fetch(PDO::FETCH_ASSOC);
        if ($earliest) {
            $events[] = [
                'timestamp' => $earliest['created_at'],
                'type' => 'inbound',
                'title' => 'Batch Dijana (System Created)',
                'ref' => 'System Created / Single GRN',
                'qty' => $earliest['qty_on_hand'],
                'meta' => [
                    'Lokasi Awal' => $earliest['location_status'],
                    'Jenis Pallet' => $earliest['pallet_type'] ?: 'None',
                    'Tag Pallet' => $earliest['pallet_id_tag'] ?: 'N/A'
                ]
            ];
        }
    }

    // 6. Fetch Current Slot Placement
    $current_stmt = $pdo->prepare("
        SELECT 
            ib.qty_on_hand,
            ib.pallet_type,
            ib.pallet_id_tag,
            ib.location_status,
            sa.location_code
        FROM inventory_batches ib
        LEFT JOIN slot_assignments sa ON ib.id = sa.batch_id
        WHERE ib.batch_no = ? AND ib.product_id = ? AND ib.qty_on_hand > 0
    ");
    $current_stmt->execute([$batch_no, $product_id]);
    $current_stock = $current_stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'product' => $product,
        'batch_no' => $batch_no,
        'current_stock' => $current_stock,
        'timeline' => $events
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
