<?php
// api/save_commercial_outbound.php

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../config/db.php';

try {
    $pdo->beginTransaction();

    $date = $_POST['out_date'];
    $customer = $_POST['customer_name'];
    $ref = $_POST['doc_ref'];
    $vehicle = $_POST['vehicle'] ?? '';
    $items = $_POST['items'] ?? [];

    // 1. Create Header
    $stmt = $pdo->prepare("INSERT INTO outbound_logs (date, customer, doc_ref, vehicle, category) VALUES (?, ?, ?, ?, 'Commercial')");
    $stmt->execute([$date, $customer, $ref, $vehicle]);
    $out_id = $pdo->lastInsertId();

    // 2. Process Items
    foreach ($items as $item) {
        $pid = $item['product_id'];
        $qty = $item['qty'];
        $batch = $item['batch'];

        if ($qty > 0) {
            // Record Line Item
            $stmtItem = $pdo->prepare("INSERT INTO outbound_items (outbound_id, product_id, qty, batch) VALUES (?, ?, ?, ?)");
            $stmtItem->execute([$out_id, $pid, $qty, $batch]);

            // Deduct Stock (Logik Dinaik Taraf untuk Batch Integriti & FEFO)
            if (!empty($batch)) {
                $batch_clean = trim($batch);
                // 1. Tolak secara spesifik mengikut Batch yang dipilih pengguna
                $stmtStock = $pdo->prepare("UPDATE inventory_batches 
                    SET qty_on_hand = qty_on_hand - ? 
                    WHERE product_id = ? AND batch_no = ? AND qty_on_hand >= ? LIMIT 1");
                $stmtStock->execute([$qty, $pid, $batch_clean, $qty]);
                
                // Jika tiada baris terkesan, bermakna batch tidak wujud atau baki stok tidak mencukupi
                if ($stmtStock->rowCount() === 0) {
                    throw new Exception("Baki stok tidak mencukupi untuk Produk ID $pid di bawah Batch '$batch_clean' (Diperlukan: $qty ctn).");
                }
            } else {
                // 2. Jika batch dikosongkan, potong menggunakan kaedah FEFO secara automatik
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

    // Rekod log audit sistem
    if (function_exists('log_system_activity')) {
        log_system_activity("Processed Commercial Outbound", "outbound_logs", $out_id, "Outbound Komersial diproses: Rujukan $ref bagi Pelanggan $customer (Lori: $vehicle).");
    }

    $pdo->commit();
    echo "<script>alert('Outbound Recorded!'); window.location.href='../commercial_outbound.php';</script>";

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error: " . $e->getMessage());
}
?>