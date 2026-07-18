<?php
// api/save_user.php
// Menyimpan atau mengemaskini maklumat pengguna di pangkalan data (Khas untuk Admin)
// Ditambah kebenaran view dan edit modules.

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

$user_id   = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$username  = trim($_POST['username'] ?? '');
$full_name = trim($_POST['full_name'] ?? '');
$password  = trim($_POST['password'] ?? '');
$role      = trim($_POST['role'] ?? 'dealer');
$is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
$hd_id     = null;

$allowed_modules_arr = isset($_POST['allowed_modules']) ? (array)$_POST['allowed_modules'] : [];
$allowed_modules = in_array($role, ['staff', 'intern', 'pss_admin', 'staff_jomcha']) ? implode(',', array_map('trim', $allowed_modules_arr)) : null;

$allowed_view_modules_arr = isset($_POST['allowed_view_modules']) ? (array)$_POST['allowed_view_modules'] : [];
$allowed_view_modules = in_array($role, ['staff', 'intern', 'pss_admin', 'staff_jomcha']) ? implode(',', array_map('trim', $allowed_view_modules_arr)) : null;

if (empty($username) || empty($full_name) || empty($role)) {
    http_response_code(400);
    echo json_encode(['error' => 'Sila lengkapkan semua ruangan wajib.']);
    exit;
}

try {
    // 1. Validasi Awal (Lari sebelum transaction bermula)
    if ($user_id <= 0) {
        if (empty($password)) {
            http_response_code(400);
            echo json_encode(['error' => 'Kata laluan diperlukan untuk pengguna baharu.']);
            exit;
        }

        // Semak sama ada username telah digunakan
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmtCheck->execute([$username]);
        if ($stmtCheck->fetchColumn() > 0) {
            http_response_code(400);
            echo json_encode(['error' => "Nama pengguna '$username' telah digunakan. Sila pilih nama lain."]);
            exit;
        }
    }

    $pdo->beginTransaction();

    if ($user_id > 0) {
        // OPERASI EDIT PENGGUNA
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            // Jika kata laluan diisi, kemaskini sekali
            $stmt = $pdo->prepare("
                UPDATE users 
                SET username = ?, full_name = ?, password_hash = ?, role = ?, is_active = ?, hd_id = ?, allowed_modules = ?, allowed_view_modules = ?
                WHERE id = ?
            ");
            $stmt->execute([$username, $full_name, $hashed, $role, $is_active, $hd_id, $allowed_modules, $allowed_view_modules, $user_id]);
        } else {
            // Jika kata laluan kosong, kekalkan yang asal
            $stmt = $pdo->prepare("
                UPDATE users 
                SET username = ?, full_name = ?, role = ?, is_active = ?, hd_id = ?, allowed_modules = ?, allowed_view_modules = ?
                WHERE id = ?
            ");
            $stmt->execute([$username, $full_name, $role, $is_active, $hd_id, $allowed_modules, $allowed_view_modules, $user_id]);
        }

        if (function_exists('log_system_activity')) {
            log_system_activity("Updated User", "users", $user_id, "Mengemas kini pengguna '$username' (Role: $role, Aktif: $is_active).");
        }
        
        $pdo->commit();
        echo json_encode(['success' => 'Maklumat pengguna berjaya dikemaskini.']);
    } else {
        // OPERASI TAMBAH PENGGUNA BARU
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmtInsert = $pdo->prepare("
            INSERT INTO users (username, full_name, password_hash, role, is_active, hd_id, allowed_modules, allowed_view_modules) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtInsert->execute([$username, $full_name, $hashed, $role, $is_active, $hd_id, $allowed_modules, $allowed_view_modules]);
        $new_id = $pdo->lastInsertId();

        if (function_exists('log_system_activity')) {
            log_system_activity("Created User", "users", $new_id, "Mendaftar pengguna baharu '$username' (Role: $role, Aktif: $is_active).");
        }
        
        $pdo->commit();
        echo json_encode(['success' => 'Pengguna baharu berjaya didaftarkan.']);
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Ralat pangkalan data: ' . $e->getMessage()]);
}
exit;
?>
