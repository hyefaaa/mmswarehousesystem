<?php
// api/save_reconciliation.php
// UPDATED: Added auth checks and input validation.

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

$date = $_POST['date'] ?? '';
$category = $_POST['category'] ?? 'Commercial'; // Default to Commercial
$sys_qty  = $_POST['system_qty'] ?? 0;
$inv_qty  = $_POST['invoice_qty'] ?? 0;
$inv_nos  = $_POST['invoice_nos'] ?? '';
$reason   = $_POST['reason'] ?? '';

if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    die("Error: Format tarikh tidak sah.");
}
if (!is_numeric($sys_qty) || !is_numeric($inv_qty)) {
    http_response_code(400);
    die("Error: Kuantiti mestilah nombor.");
}
$sys_qty = (int)$sys_qty;
$inv_qty = (int)$inv_qty;
$variance = $sys_qty - $inv_qty;

try {
    $pdo->beginTransaction();

    $sql = "INSERT INTO daily_reconciliation 
            (date, category, system_qty_cartons, invoice_qty_cartons, invoice_numbers, variance, reason)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            system_qty_cartons = VALUES(system_qty_cartons),
            invoice_qty_cartons = VALUES(invoice_qty_cartons),
            invoice_numbers = VALUES(invoice_numbers),
            variance = VALUES(variance),
            reason = VALUES(reason)";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date, $category, $sys_qty, $inv_qty, $inv_nos, $variance, $reason]);

    $pdo->commit();
    header("Location: ../reconcile.php?date=$date&success=1");

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error: " . $e->getMessage());
}
?>