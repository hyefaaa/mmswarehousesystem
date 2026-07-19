<?php
// api/save_daily_closing.php
// Saves or deletes daily closing stock audit reports

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? '';
$is_staff = is_staff_role($role);

if (!$is_staff) {
    http_response_code(403);
    echo "Akses dinafikan.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $audit_date = $_POST['audit_date'] ?? date('Y-m-d');
    $action = $_POST['action'] ?? '';

    if ($action === 'unlock') {
        if ($role !== 'admin') {
            die("Akses dinafikan untuk unlock. Hanya admin dibenarkan.");
        }
        // Unlock action: Admin only (or checked via a simple prompt passcode in UI)
        try {
            $stmtDel = $pdo->prepare("DELETE FROM daily_closing_reports WHERE audit_date = ?");
            $stmtDel->execute([$audit_date]);
            
            if (function_exists('log_system_activity')) {
                $username = $_SESSION['username'] ?? 'admin';
                log_system_activity("Unlocked Daily Closing", "daily_closing_reports", null, "$username telah membatalkan/unlock laporan closing untuk tarikh $audit_date");
            }
            
            header("Location: ../daily_closing_report.php?date=" . urlencode($audit_date) . "&msg=unlocked");
            exit;
        } catch (Exception $e) {
            die("Ralat sistem semasa unlock: " . $e->getMessage());
        }
    }

    $checked_by = $_POST['checked_by'] ?? '';
    $items = $_POST['items'] ?? [];

    if (empty($checked_by)) {
        die("Nama Pemeriksa (Checked By) wajib dipilih.");
    }

    try {
        $pdo->beginTransaction();

        // 1. Confirm double submission prevention
        $stmtCheck = $pdo->prepare("SELECT id FROM daily_closing_reports WHERE audit_date = ? LIMIT 1");
        $stmtCheck->execute([$audit_date]);
        if ($stmtCheck->fetch()) {
            throw new Exception("Laporan audit closing bagi tarikh " . $audit_date . " telah disahkan sebelumnya.");
        }

        // 2. Insert master audit record
        $stmtInsertReport = $pdo->prepare("INSERT INTO daily_closing_reports (audit_date, checked_by, status) VALUES (?, ?, 'Completed')");
        $stmtInsertReport->execute([$audit_date, $checked_by]);
        $report_id = $pdo->lastInsertId();

        // 3. Insert items
        $stmtInsertItem = $pdo->prepare("
            INSERT INTO daily_closing_items (report_id, product_id, system_qty_ctn, physical_qty_ctn, variance_ctn) 
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($items as $product_id => $data) {
            $system_qty = isset($data['system_qty']) ? (int)$data['system_qty'] : 0;
            $physical_qty = ($data['physical_qty'] !== '') ? (int)$data['physical_qty'] : $system_qty;
            $variance = $physical_qty - $system_qty;

            $stmtInsertItem->execute([
                $report_id,
                $product_id,
                $system_qty,
                $physical_qty,
                $variance
            ]);
        }

        $pdo->commit();
        
        if (function_exists('log_system_activity')) {
            $username = $_SESSION['username'] ?? 'system';
            log_system_activity("Completed Daily Closing", "daily_closing_reports", $report_id, "$username update daily closing stock untuk tarikh $audit_date (Pemeriksa: $checked_by)");
        }

        header("Location: ../daily_closing_report.php?date=" . urlencode($audit_date) . "&msg=saved");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Gagal menyimpan laporan closing: " . $e->getMessage());
    }
}
?>
