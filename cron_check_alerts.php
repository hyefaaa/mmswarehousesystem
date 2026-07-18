<?php
// cron_check_alerts.php
// Warehouse Inventory Alert Engine
// Checks for low stock and near expiry batches, then dispatches alerts via configured channels

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/settings_helper.php';

// Check if running from browser or command line
$is_cli = (php_sapi_name() === 'cli');

echo "--- INVENTORY ALERT ENGINE START ---<br>\n";

$enabled = (int)get_system_setting('enable_notifications', 0);
if (!$enabled) {
    echo "Notifications are currently disabled in settings.<br>\n";
    exit;
}

$low_threshold = (int)get_system_setting('low_stock_threshold', 50);
$expiry_threshold = (int)get_system_setting('near_expiry_threshold', 30);

$telegram_active = (!empty(get_system_setting('telegram_bot_token')) && !empty(get_system_setting('telegram_chat_id')));
$email_active = (!empty(get_system_setting('email_recipient')));

if (!$telegram_active && !$email_active) {
    echo "No notification channels (Telegram or Email) are configured.<br>\n";
    exit;
}

// 1. Fetch Low Stock Items (Aggregate in Warehouse location)
$low_stock_query = $pdo->prepare("
    SELECT p.name, COALESCE(SUM(b.qty_on_hand), 0) as total_qty
    FROM products p
    LEFT JOIN inventory_batches b ON p.id = b.product_id AND b.location_status = 'Warehouse'
    WHERE p.is_active = 1
    GROUP BY p.id, p.name
    HAVING total_qty < ?
    ORDER BY total_qty ASC
");
$low_stock_query->execute([$low_threshold]);
$low_stock_items = $low_stock_query->fetchAll();

// 2. Fetch Near Expiry Batches (Warehouse location)
$expiry_query = $pdo->prepare("
    SELECT p.name as product_name, b.batch_no, b.expiry_date, b.qty_on_hand, DATEDIFF(b.expiry_date, CURDATE()) as days_left
    FROM inventory_batches b
    JOIN products p ON b.product_id = p.id
    WHERE b.qty_on_hand > 0 
      AND b.location_status = 'Warehouse'
      AND b.expiry_date IS NOT NULL 
      AND b.expiry_date != '0000-00-00'
      AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
    ORDER BY b.expiry_date ASC
");
$expiry_query->execute([$expiry_threshold]);
$near_expiry_batches = $expiry_query->fetchAll();

// Check if there are warnings to send
if (empty($low_stock_items) && empty($near_expiry_batches)) {
    echo "Inventory is healthy. No alerts generated.<br>\n";
    exit;
}

// 3. Format Telegram Message (HTML support)
$telegram_msg = "<b>⚠️ MMS WAREHOUSE ALERT</b>\n\n";

if (!empty($low_stock_items)) {
    $telegram_msg .= "<b>🚨 STOK DI TAHAP KRITIKAL (< {$low_threshold} ctn):</b>\n";
    foreach ($low_stock_items as $item) {
        $telegram_msg .= "• " . htmlspecialchars($item['name']) . ": <b>" . number_format($item['total_qty']) . " ctn</b>\n";
    }
    $telegram_msg .= "\n";
}

if (!empty($near_expiry_batches)) {
    $telegram_msg .= "<b>📅 BATCH HAMPIR LUPUT (< {$expiry_threshold} hari):</b>\n";
    foreach ($near_expiry_batches as $batch) {
        $formattedDate = date('d/m/Y', strtotime($batch['expiry_date']));
        $telegram_msg .= "• " . htmlspecialchars($batch['product_name']) . " (B: " . htmlspecialchars($batch['batch_no']) . "): <b>" . number_format($batch['qty_on_hand']) . " ctn</b> - Luput: <b>" . $formattedDate . "</b> ({$batch['days_left']} hari lagi)\n";
    }
}

$telegram_msg .= "\n<i>Sila layar portal WMS untuk pelarasan stok.</i>";

// 4. Format Email Plain/Html Content
$email_subject = "⚠️ KESELAMATAN STOK GUDANG MMS - AMARAN INVENTORI";
$email_msg = "Sila semak laporan stok gudang semasa:\n\n";

if (!empty($low_stock_items)) {
    $email_msg .= "🚨 STOK DI TAHAP KRITIKAL (< {$low_threshold} ctn):\n";
    foreach ($low_stock_items as $item) {
        $email_msg .= " - " . $item['name'] . ": " . number_format($item['total_qty']) . " ctn\n";
    }
    $email_msg .= "\n";
}

if (!empty($near_expiry_batches)) {
    $email_msg .= "📅 BATCH HAMPIR LUPUT (< {$expiry_threshold} hari):\n";
    foreach ($near_expiry_batches as $batch) {
        $formattedDate = date('d/m/Y', strtotime($batch['expiry_date']));
        $email_msg .= " - " . $batch['product_name'] . " (Batch: " . $batch['batch_no'] . "): " . number_format($batch['qty_on_hand']) . " ctn - Luput: " . $formattedDate . " (" . $batch['days_left'] . " hari lagi)\n";
    }
}

// 5. Dispatch Alerts
$dispatched = false;

if ($telegram_active) {
    echo "Sending Telegram notification... ";
    $res = send_telegram_notification($telegram_msg);
    if ($res['success']) {
        echo "✅ Success.<br>\n";
        $dispatched = true;
    } else {
        echo "❌ Failed: " . htmlspecialchars($res['message']) . "<br>\n";
    }
}

if ($email_active) {
    echo "Sending Email notification... ";
    $res = send_email_notification($email_subject, $email_msg);
    if ($res['success']) {
        echo "✅ Success.<br>\n";
        $dispatched = true;
    } else {
        echo "❌ Failed: " . htmlspecialchars($res['message']) . "<br>\n";
    }
}

echo "--- ALERT ENGINE END ---<br>\n";
