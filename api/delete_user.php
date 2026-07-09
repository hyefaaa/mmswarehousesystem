<?php
// api/delete_user.php
// Memadam pengguna daripada pangkalan data (Khas untuk Admin)

header('Content-Type: application/json');
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!file_exists('../config/db.php')) {
    http_response_code(500);
    echo json_encode(['error' => 'Konfigurasi pangkalan data tidak ditemui.']);
    exit;
}
require_once '../config/db.php';

// Sahkan peranan semasa: Hanya Admin dibenarkan
$role_check = $_SESSION['role'] ?? '';
if ($role_check !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Akses dinafikan. Anda tiada kebenaran.']);
    exit;
}

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID pengguna tidak sah.']);
    exit;
}

try {
    // Cari nama pengguna terlebih dahulu untuk tujuan pembalakan audit
    $stmtUser = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmtUser->execute([$user_id]);
    $username = $stmtUser->fetchColumn();

    if (!$username) {
        http_response_code(404);
        echo json_encode(['error' => 'Pengguna tidak ditemui.']);
        exit;
    }

    // Elakkan Admin memadam akaun mereka sendiri secara tidak sengaja
    if ($_SESSION['user_id'] == $user_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Anda tidak dibenarkan memadam akaun sendiri yang sedang digunakan.']);
        exit;
    }

    // Padam rekod
    $stmtDelete = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmtDelete->execute([$user_id]);

    if (function_exists('log_system_activity')) {
        log_system_activity("Deleted User", "users", $user_id, "Memadam akaun pengguna '$username' daripada sistem.");
    }
    echo json_encode(['success' => "Akaun '$username' berjaya dipadam."]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ralat pangkalan data: ' . $e->getMessage()]);
}
exit;
?>
