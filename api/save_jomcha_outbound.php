<?php
// api/save_jomcha_outbound.php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || !is_staff_role($_SESSION['role'] ?? '')) {
    http_response_code(403);
    die("Error: Akses dinafikan. Hanya staf dibenarkan.");
}

try {
    $pdo->beginTransaction();

    $date = $_POST['out_date'] ?? date('Y-m-d');
    $customer = 'Jomcha Shop';
    $ref = $_POST['doc_ref'] ?? '';
    $vehicle = $_POST['vehicle'] ?? '';
    $items = $_POST['items'] ?? [];

    if (empty($ref)) {
        throw new Exception("Sila isi No. Rujukan DO / Resit.");
    }

    // 1. Create Header with category 'Jomcha'
    $stmt = $pdo->prepare("INSERT INTO outbound_logs (date, customer, doc_ref, vehicle, category) VALUES (?, ?, ?, ?, 'Jomcha')");
    $stmt->execute([$date, $customer, $ref, $vehicle]);
    $out_id = $pdo->lastInsertId();

    // 2. Process Items
    foreach ($items as $item) {
        $pid = $item['product_id'];
        $qty = intval($item['qty'] ?? 0);
        $batch = $item['batch'] ?? '';

        if ($qty > 0) {
            // Record Line Item
            $stmtItem = $pdo->prepare("INSERT INTO outbound_items (outbound_id, product_id, qty, batch) VALUES (?, ?, ?, ?)");
            $stmtItem->execute([$out_id, $pid, $qty, $batch]);

            // Deduct Stock
            if (!empty($batch)) {
                $batch_clean = trim($batch);
                $stmtStock = $pdo->prepare("UPDATE inventory_batches 
                    SET qty_on_hand = qty_on_hand - ? 
                    WHERE product_id = ? AND batch_no = ? AND qty_on_hand >= ? LIMIT 1");
                $stmtStock->execute([$qty, $pid, $batch_clean, $qty]);
                
                if ($stmtStock->rowCount() === 0) {
                    throw new Exception("Baki stok tidak mencukupi untuk Produk ID $pid di bawah Batch '$batch_clean' (Diperlukan: $qty ctn).");
                }
            } else {
                // Auto FEFO
                $stmtGetBatches = $pdo->prepare("SELECT id, batch_no, qty_on_hand FROM inventory_batches 
                    WHERE product_id = ? AND qty_on_hand > 0 
                    ORDER BY expiry_date ASC");
                $stmtGetBatches->execute([$pid]);
                $available_batches = $stmtGetBatches->fetchAll();
                
                $remaining_qty = $qty;
                foreach ($available_batches as $avail) {
                    if ($remaining_qty <= 0) break;
                    $deduct = min($remaining_qty, $avail['qty_on_hand']);
                    
                    $stmtDeduct = $pdo->prepare("UPDATE inventory_batches SET qty_on_hand = qty_on_hand - ? WHERE id = ?");
                    $stmtDeduct->execute([$deduct, $avail['id']]);
                    
                    $remaining_qty -= $deduct;
                }
                
                if ($remaining_qty > 0) {
                    throw new Exception("Jumlah stok sedia ada tidak mencukupi untuk Produk ID $pid (Kekurangan: $remaining_qty ctn).");
                }
            }
        }
    }

    // Record audit log
    if (function_exists('log_system_activity')) {
        log_system_activity("Processed Jomcha Outbound", "outbound_logs", $out_id, "Outbound Jomcha diproses: Rujukan $ref (Lori: $vehicle).");
    }

    $pdo->commit();
    echo "<script>alert('Outbound Jomcha Recorded!'); window.location.href='../jomcha_outbound.php';</script>";

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error: " . $e->getMessage());
}
?>
