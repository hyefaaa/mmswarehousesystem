<?php
// api/assign_pallet_slot.php
require_once '../config/db.php';
header('Content-Type: application/json');

// Sahkan peranan semasa: Hanya kakitangan dibenarkan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Akses dinafikan.']);
    exit;
}

$location_code = trim($_POST['location_code'] ?? '');
$batch_ids = isset($_POST['batch_ids']) ? (array)$_POST['batch_ids'] : [];

if (empty($location_code)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Location code is required.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Hapus tugasan lama untuk lokasi ini
    $stmtDeleteLoc = $pdo->prepare("DELETE FROM slot_assignments WHERE location_code = ?");
    $stmtDeleteLoc->execute([$location_code]);

    // 2. Masukkan tugasan baharu
    if (!empty($batch_ids)) {
        $stmtInsert = $pdo->prepare("INSERT INTO slot_assignments (location_code, batch_id) VALUES (?, ?)");
        foreach ($batch_ids as $batch_id) {
            $batch_id = (int)$batch_id;
            if ($batch_id > 0) {
                // Pastikan batch ini tidak ditugaskan di tempat lain (jika ada, padam yang lama)
                $stmtClearOld = $pdo->prepare("DELETE FROM slot_assignments WHERE batch_id = ?");
                $stmtClearOld->execute([$batch_id]);

                $stmtInsert->execute([$location_code, $batch_id]);
            }
        }
    }

    if (function_exists('log_system_activity')) {
        $username = $_SESSION['username'] ?? 'system';
        $batch_count = count($batch_ids);
        log_system_activity("Assign Pallet Slot", "slot_assignments", null, "$username assigned $batch_count batch(es) to $location_code");
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Slot assignments updated successfully.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
