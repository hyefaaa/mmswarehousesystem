<?php
// api/change_password.php
// Membolehkan pengguna menukar kata laluan mereka sendiri

header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sesi tidak sah. Sila log masuk semula.']);
    exit;
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    echo json_encode(['success' => false, 'message' => 'Sila lengkapkan semua ruangan wajib.']);
    exit;
}

if ($new_password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'Kata laluan baharu tidak sepadan.']);
    exit;
}

if (strlen($new_password) < 4) {
    echo json_encode(['success' => false, 'message' => 'Kata laluan baharu mestilah sekurang-kurangnya 4 aksara.']);
    exit;
}

try {
    // Ambil kata laluan sedia ada
    $stmt = $pdo->prepare("SELECT password_hash, username FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemui.']);
        exit;
    }

    // Padan kata laluan semasa
    $matched = false;
    if ($current_password === $user['password_hash'] || password_verify($current_password, $user['password_hash'])) {
        $matched = true;
    }

    if (!$matched) {
        echo json_encode(['success' => false, 'message' => 'Kata laluan semasa adalah salah.']);
        exit;
    }

    // Set kata laluan baharu
    $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
    $stmtUpdate = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmtUpdate->execute([$new_hash, $user_id]);

    if (function_exists('log_system_activity')) {
        log_system_activity("User Changed Password", "users", $user_id, "Pengguna '{$user['username']}' berjaya menukar kata laluan sendiri.");
    }

    echo json_encode(['success' => true, 'message' => 'Kata laluan anda telah berjaya ditukar!']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ralat sistem: ' . $e->getMessage()]);
}
exit;
?>
