<?php
// api/save_hd_stock.php
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

// Ensure the table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS hd_stock_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dealer VARCHAR(50) NOT NULL,
        product_id INT NOT NULL,
        batch_no VARCHAR(50) DEFAULT NULL,
        transaction_type ENUM('Stock Take', 'Distribution') NOT NULL,
        qty_cartons INT NOT NULL DEFAULT 0,
        qty_packs INT NOT NULL DEFAULT 0,
        destination VARCHAR(255) DEFAULT NULL,
        remarks TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal membina jadual database: ' . $e->getMessage()]);
    exit;
}

$username = $_SESSION['username'] ?? 'unknown';
$product_id = (int)($_POST['product_id'] ?? 0);
$batch_no = trim($_POST['batch_no'] ?? '');
$transaction_type = trim($_POST['transaction_type'] ?? '');
$qty_cartons = (int)($_POST['qty_cartons'] ?? 0);
$qty_packs = (int)($_POST['qty_packs'] ?? 0);
$destination = trim($_POST['destination'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');

if (empty($product_id) || empty($transaction_type) || ($qty_cartons === 0 && $qty_packs === 0)) {
    echo json_encode(['success' => false, 'message' => 'Sila isi maklumat produk, jenis transaksi, dan kuantiti yang sah.']);
    exit;
}

if ($transaction_type === 'Distribution' && empty($destination)) {
    echo json_encode(['success' => false, 'message' => 'Destinasi agihan wajib diisi bagi transaksi Agihan (Distribution).']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO hd_stock_logs (dealer, product_id, batch_no, transaction_type, qty_cartons, qty_packs, destination, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$username, $product_id, $batch_no, $transaction_type, $qty_cartons, $qty_packs, $destination, $remarks]);

    if (function_exists('log_system_activity')) {
        log_system_activity("Logged HD Stock Activity", "hd_stock_logs", $pdo->lastInsertId(), "Dealer '{$username}' logged {$transaction_type} for product_id {$product_id}");
    }

    echo json_encode(['success' => true, 'message' => 'Rekod stok berjaya disimpan!']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ralat sistem: ' . $e->getMessage()]);
}
exit;
?>
