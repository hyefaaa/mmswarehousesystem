<?php
// config/settings_helper.php
// Central helper for retrieving and managing system notification configurations

require_once __DIR__ . '/db.php';

// Self-healing database check
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    // Initialize defaults if empty
    $defaults = [
        'enable_notifications' => '0',
        'telegram_bot_token' => '',
        'telegram_chat_id' => '',
        'email_recipient' => '',
        'low_stock_threshold' => '50',
        'near_expiry_threshold' => '30'
    ];

    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
    $insertStmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");

    foreach ($defaults as $key => $val) {
        $checkStmt->execute([$key]);
        if ($checkStmt->fetchColumn() == 0) {
            $insertStmt->execute([$key, $val]);
        }
    }
} catch (PDOException $e) {
    error_log("Settings Helper DDL Error: " . $e->getMessage());
}

if (!function_exists('get_system_setting')) {
    function get_system_setting($key, $default = null) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            return ($val !== false) ? $val : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('get_all_system_settings')) {
    function get_all_system_settings() {
        global $pdo;
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('send_telegram_notification')) {
    function send_telegram_notification($message) {
        $token = get_system_setting('telegram_bot_token');
        $chat_id = get_system_setting('telegram_chat_id');

        if (empty($token) || empty($chat_id)) {
            return ['success' => false, 'message' => 'Telegram Bot Token or Chat ID not configured.'];
        }

        $url = "https://api.telegram.org/bot" . $token . "/sendMessage";
        $data = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'message' => 'Curl Error: ' . $err];
        }

        $resDecoded = json_decode($response, true);
        if (isset($resDecoded['ok']) && $resDecoded['ok']) {
            return ['success' => true];
        } else {
            return ['success' => false, 'message' => $resDecoded['description'] ?? 'Unknown API Error'];
        }
    }
}

if (!function_exists('send_email_notification')) {
    function send_email_notification($subject, $message) {
        $recipient = get_system_setting('email_recipient');
        if (empty($recipient)) {
            return ['success' => false, 'message' => 'Email recipient not configured.'];
        }

        // Prepare simple HTML email header
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: MMS WAREHOUSE ALERT <noreply@susumurah.com.my>" . "\r\n";

        // Wrap message in simple HTML frame
        $htmlMessage = "
        <html>
        <head>
            <title>" . htmlspecialchars($subject ?? '') . "</title>
        </head>
        <body style='font-family: Arial, sans-serif; background-color: #f8fafc; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0;'>
                <h2 style='color: #0f172a; border-bottom: 2px solid #3b82f6; padding-bottom: 10px;'>" . htmlspecialchars($subject ?? '') . "</h2>
                <div style='color: #334155; font-size: 14px; line-height: 1.6; margin-top: 15px;'>
                    " . nl2br($message) . "
                </div>
                <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                <p style='font-size: 11px; color: #94a3b8; text-align: center; margin: 0;'>MMS Warehouse Management System System Generated Alert.</p>
            </div>
        </body>
        </html>";

        if (mail($recipient, $subject, $htmlMessage, $headers)) {
            return ['success' => true];
        } else {
            return ['success' => false, 'message' => 'PHP mail() function failed to dispatch.'];
        }
    }
}
