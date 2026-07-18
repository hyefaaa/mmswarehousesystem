<?php
// api/process_product_import.php
// MASTER IMPORT: Category Support + Duplicate Prevention

header('Content-Type: application/json');
require_once '../config/db.php';

try {
    if (!isset($_FILES['product_file'])) throw new Exception("No file.");
    
    $handle = fopen($_FILES['product_file']['tmp_name'], "r");
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    fgetcsv($handle); // Skip header
    $pdo->beginTransaction();

    $totalLines = 0; $inserted = 0; $updated = 0; $skipped = 0;

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if (empty($data) || empty(trim($data[0]))) continue;
        
        $totalLines++;
        $name = trim($data[0] ?? '');
        $category = trim($data[1] ?? ''); // Supports Cooking, IceCream, Beef, etc.
        $uom = isset($data[2]) ? trim($data[2]) : 'Carton';
        $pack = (int)($data[3] ?? 1);
        $cap = (int)($data[4] ?? 60);

        // ON DUPLICATE KEY UPDATE ensures we don't crash if a name exists
        $stmt = $pdo->prepare("
            INSERT INTO products (name, category, uom, pack_size, pallet_capacity, is_active) 
            VALUES (?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE 
            category = VALUES(category), 
            uom = VALUES(uom),
            pack_size = VALUES(pack_size), 
            pallet_capacity = VALUES(pallet_capacity),
            is_active = 1
        ");
        
        $stmt->execute([$name, $category, $uom, $pack, $cap]);
        
        $res = $stmt->rowCount();
        if ($res == 1) $inserted++; // New
        elseif ($res == 2) $updated++; // Modified
        else $skipped++; // Identical
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => "Processed $totalLines rows: $inserted new, $updated updated, $skipped identical."]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}