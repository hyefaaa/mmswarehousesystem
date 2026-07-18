<?php
// api/update_spoilage_status.php
// UPDATED: Added auth checks and status value validation.

header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || !is_staff_role($_SESSION['role'] ?? '')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized Access']);
    exit;
}

try {
    $id = $_POST['id'] ?? 0;
    $status = $_POST['status'] ?? '';
    $submit_date = !empty($_POST['submit_date']) ? $_POST['submit_date'] : null;
    $cn_num = !empty($_POST['cn_num']) ? $_POST['cn_num'] : null;
    $cn_date = !empty($_POST['cn_date']) ? $_POST['cn_date'] : null;

    if (!in_array($status, ['Pending', 'Submitted', 'Approved', 'Rejected'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid status value.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE spoilage_logs SET 
        claim_status = ?, 
        supplier_submitted_at = ?, 
        cn_number = ?, 
        cn_date = ? 
        WHERE id = ?");

    if ($stmt->execute([$status, $submit_date, $cn_num, $cn_date, $id])) {
        echo json_encode(['status' => 'success']);
    } else {
        throw new Exception("Database update failed.");
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
exit;
?>