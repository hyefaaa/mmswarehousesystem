<?php
// api/save_spoilage.php
// Updated to handle Loose Pcs and Auto-Organized Photos by Discovery Date

header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../config/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.");
    }

    $pdo->beginTransaction();

    // 1. Capture Incident Metadata
    $discovery_date = $_POST['report_date'] ?? date('Y-m-d');
    $remarks = $_POST['remarks'] ?? '';
    $uploaded_paths = [];

    // 2. Auto-Manage Photos based on Discovery Date
    if (!empty($_FILES['spoilage_photos']['name'][0])) {
        $date_parts = explode('-', $discovery_date); 
        $sub_path = $date_parts[0] . "/" . $date_parts[1] . "/" . $date_parts[2] . "/";
        $target_dir = __DIR__ . "/../uploads/spoilage/" . $sub_path;

        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        foreach ($_FILES['spoilage_photos']['name'] as $key => $name) {
            $tmp_name = $_FILES['spoilage_photos']['tmp_name'][$key];
            $file_ext = pathinfo($name, PATHINFO_EXTENSION);
            
            // Unique filename to prevent overwriting evidence
            $new_filename = time() . "_" . bin2hex(random_bytes(2)) . "." . $file_ext;
            $destination = $target_dir . $new_filename;

            if (move_uploaded_file($tmp_name, $destination)) {
                $uploaded_paths[] = $sub_path . $new_filename;
            }
        }
    }

    // Convert array of paths to comma-separated string for DB
    $db_photo_string = !empty($uploaded_paths) ? implode(',', $uploaded_paths) : null;

    // 3. Process Inventory Adjustments (in Pieces)
    if (empty($_POST['items'])) {
        throw new Exception("No items submitted in report.");
    }

    foreach ($_POST['items'] as $item) {
        $batch_id = (int)$item['batch_id'];
        $qty_pcs = (float)$item['qty']; // The final calculated pieces from the frontend
        $reason = $item['reason'];

        if ($qty_pcs <= 0) continue;

        // A. Log the event (Record the loss first)
        $stmtLog = $pdo->prepare("INSERT INTO spoilage_logs 
            (batch_id, qty, reason, remarks, reported_at, photo_path, claim_status) 
            VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
        $stmtLog->execute([$batch_id, $qty_pcs, $reason, $remarks, $discovery_date, $db_photo_string]);

        // B. Deduct from Inventory (Logic matches commercial_outbound)
        $stmtUpdate = $pdo->prepare("UPDATE inventory_batches 
            SET qty_on_hand = qty_on_hand - ? 
            WHERE id = ? AND qty_on_hand >= ?");
        $stmtUpdate->execute([$qty_pcs, $batch_id, $qty_pcs]);

        if ($stmtUpdate->rowCount() === 0) {
            throw new Exception("Error: Insufficient stock for Batch ID $batch_id.");
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Report saved. Total ' . count($uploaded_paths) . ' photos uploaded.']);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}