<?php
// api/send_test_alert.php
// Dispatch a test alert to verifying notification integrations

header('Content-Type: application/json');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../config/db.php';
require_once '../config/settings_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses dinafikan. Hanya admin dibenarkan.']);
    exit;
}

$channel = $_GET['channel'] ?? '';

if ($channel === 'telegram') {
    $test_msg = "<b>🔔 MMS WAREHOUSE - TEST ALERT</b>\n\nKoneksi Telegram Bot anda telah berjaya disambungkan! Sistem kini bersedia menghantar amaran stok kritikal & tarikh luput.";
    $res = send_telegram_notification($test_msg);
    echo json_encode($res);
} elseif ($channel === 'email') {
    $subject = "🔔 UJIAN ALAMAT EMEL MMS WMS";
    $test_msg = "Sambungan e-mel notifikasi sistem WMS telah berjaya diuji! Sistem kini bersedia menghantar amaran stok kritikal & tarikh luput ke alamat ini.";
    $res = send_email_notification($subject, $test_msg);
    echo json_encode($res);
} else {
    echo json_encode(['success' => false, 'message' => 'Saluran komunikasi tidak sah.']);
}
