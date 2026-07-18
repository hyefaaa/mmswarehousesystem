<?php
// api/save_notification_settings.php
// Saves WMS notification configurations

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only admin role allowed
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die("Akses dinafikan. Hanya admin dibenarkan.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Kaedah tidak dibenarkan.");
}

$enable_notifications  = isset($_POST['enable_notifications']) ? '1' : '0';
$telegram_bot_token    = trim($_POST['telegram_bot_token'] ?? '');
$telegram_chat_id      = trim($_POST['telegram_chat_id'] ?? '');
$email_recipient       = trim($_POST['email_recipient'] ?? '');
$low_stock_threshold   = (int)($_POST['low_stock_threshold'] ?? 50);
$near_expiry_threshold = (int)($_POST['near_expiry_threshold'] ?? 30);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    $stmt->execute(['enable_notifications', $enable_notifications]);
    $stmt->execute(['telegram_bot_token', $telegram_bot_token]);
    $stmt->execute(['telegram_chat_id', $telegram_chat_id]);
    $stmt->execute(['email_recipient', $email_recipient]);
    $stmt->execute(['low_stock_threshold', (string)$low_stock_threshold]);
    $stmt->execute(['near_expiry_threshold', (string)$near_expiry_threshold]);

    if (function_exists('log_system_activity')) {
        log_system_activity("Updated Alert Settings", "system_settings", 0, "Mengemas kini tetapan amaran & saluran notifikasi sistem.");
    }

    $pdo->commit();
    header("Location: ../notification_settings.php?success=1");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error saving settings: " . $e->getMessage());
}
