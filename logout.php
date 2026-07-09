<?php
// logout.php
// Skrip untuk menamatkan sesi log masuk pengguna

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    require_once 'config/db.php';
    if (function_exists('log_system_activity')) {
        log_system_activity("User Logged Out", "users", $_SESSION['user_id'], "Pengguna '{$_SESSION['username']}' log keluar secara manual.");
    }
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header("Location: login.php");
exit;
?>
