<?php
// Gunakan konstanta atau environment variables untuk keselamatan
define('DB_HOST', 'localhost');
define('DB_NAME', 'mmswarehousesystem');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHAR', 'utf8mb4');

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHAR;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    // Memastikan sambungan menggunakan timezone yang betul jika perlu
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHAR . " COLLATE utf8mb4_unicode_ci"
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // Dalam production, jangan paparkan error detail kepada user
    // Log error ke fail sebaliknya
    error_log($e->getMessage());
    die("Sistem mengalami masalah teknikal. Sila cuba sebentar lagi.");
}

// Fungsi log aktiviti sistem global untuk audit trail
if (!function_exists('log_system_activity')) {
    function log_system_activity($action, $target_table = null, $record_id = null, $details = '') {
        global $pdo;
        
        // Elakkan pemulaan sesi jika ia dipanggil sebelum session_start lain (e.g. dalam API)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user_id    = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $username   = isset($_SESSION['username']) ? $_SESSION['username'] : 'System';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO system_logs (user_id, username, action, target_table, record_id, details, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $username, $action, $target_table, $record_id, $details, $ip_address]);
        } catch (Exception $e) {
            error_log("Gagal merekod log sistem: " . $e->getMessage());
        }
    }
}

// Auto-migration has completed, commenting out to save redundant DB queries on every request.
/*
try {
    $check_column = $pdo->query("SHOW COLUMNS FROM products LIKE 'qrcode'")->fetch();
    if (!$check_column) {
        $pdo->exec("ALTER TABLE products ADD COLUMN qrcode VARCHAR(100) NULL AFTER barcode");
    }
} catch (Exception $e) {
    // Silently ignore if table doesn't exist yet
}
*/
?>