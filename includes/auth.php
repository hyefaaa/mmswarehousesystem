<?php
// includes/auth.php - User Access Control & Request Interception
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

if (!function_exists('check_write_permission')) {
    function check_write_permission($page_name) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        global $pdo;
        $role = 'dealer';
        $allowed_modules_str = '';
        
        try {
            if (isset($pdo)) {
                $stmt = $pdo->prepare("SELECT role, allowed_modules FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                if ($user) {
                    $role = $user['role'];
                    $allowed_modules_str = $user['allowed_modules'] ?? '';
                }
            } else {
                $role = $_SESSION['role'] ?? 'dealer';
                $allowed_modules_str = $_SESSION['allowed_modules'] ?? '';
            }
        } catch (Exception $e) {
            $role = $_SESSION['role'] ?? 'dealer';
            $allowed_modules_str = $_SESSION['allowed_modules'] ?? '';
        }

        if ($role === 'admin') {
            return true;
        }

        if ($role === 'dealer') {
            $clean_page = strtolower(basename($page_name));
            if ($clean_page === 'api_pss.php') {
                $action = $_GET['action'] ?? '';
                if ($action === 'start_new_co') {
                    return false;
                }
                return true;
            }
            if ($clean_page === 'pss_delivery.php' || $clean_page === 'master_hubpss.php' || $clean_page === 'hd_stock_take.php' || $clean_page === 'save_hd_stock.php') {
                return true;
            }
            return false;
        }

        if ($role === 'staff_jomcha') {
            $clean_page = strtolower(basename($page_name));
            if ($clean_page === 'jomcha_request_stock.php' || $clean_page === 'process_jomcha_request.php' || $clean_page === 'jomcha_stock_take.php' || $clean_page === 'jomcha_monitor_stock.php') {
                return true;
            }
        }

        // Clean page name (lowercase, basename)
        $page_name = strtolower(basename($page_name));

        // Parse user allowed modules
        $allowed_modules = array_filter(array_map('trim', explode(',', $allowed_modules_str)));

        // Module mapping
        $module_map = [
            'master_hubpss.php' => 'pss',
            'pss_delivery.php' => 'pss',
            'api_pss.php' => 'pss',
            'save_delivery.php' => 'pss',
            'save_school_import.php' => 'pss',
            'import_schools.php' => 'pss',
            'import_co_ui.php' => 'pss',
            'save_import.php' => 'pss',
            'save_co_update.php' => 'pss',
            'hd_stock_take.php' => 'pss',
            'save_hd_stock.php' => 'pss',
            
            'daily_closing_report.php' => 'daily_closing',
            'save_daily_closing.php' => 'daily_closing',
            'daily_closing_history.php' => 'daily_closing',
            
            'stock_transfer.php' => 'stock_transfer',
            'save_stock_transfer.php' => 'stock_transfer',
            'assign_pallet_slot.php' => 'stock_transfer',
            
            'stock_take.php' => 'stock_take',
            'save_stock_take.php' => 'stock_take',
            
            'product_management.php' => 'product_management',
            'process_product_import.php' => 'product_management',
            'import_products.php' => 'product_management',
            
            'receiving.php' => 'receiving',
            'receiving_multi.php' => 'receiving',
            'save_receiving.php' => 'receiving',
            'save_receiving_multi.php' => 'receiving',
            
            'commercial_outbound.php' => 'outbound_reconcile',
            'reconcile.php' => 'outbound_reconcile',
            'save_commercial_outbound.php' => 'outbound_reconcile',
            'save_reconciliation.php' => 'outbound_reconcile',
            
            'jomcha_outbound.php' => 'jomcha',
            'save_jomcha_outbound.php' => 'jomcha',
            'jomcha_request_stock.php' => 'jomcha',
            'process_jomcha_request.php' => 'jomcha',
            'jomcha_stock_take.php' => 'jomcha',
            'jomcha_monitor_stock.php' => 'jomcha',
            
            'spoilage_record.php' => 'spoilage',
            'spoilage_report.php' => 'spoilage',
            'save_spoilage.php' => 'spoilage',
            'update_spoilage_status.php' => 'spoilage',
            'cleanup_photos.php' => 'spoilage',
            
            'user_management.php' => 'user_management',
            'save_user.php' => 'user_management',
            'delete_user.php' => 'user_management',
            
            'pallet_return.php' => 'pallet_return',
            'print_prf_slip.php' => 'pallet_return',
            'get_pallet_return_details.php' => 'pallet_return',
            'pallet_management.php' => 'pallet_return'
        ];

        $target_module = $module_map[$page_name] ?? null;
        if ($target_module && in_array($target_module, $allowed_modules)) {
            return true;
        }

        // Special check: If page name contains 'pss', check 'pss' permission
        if (strpos($page_name, 'pss') !== false && in_array('pss', $allowed_modules)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('check_view_permission')) {
    function check_view_permission($page_name) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        global $pdo;
        $role = 'dealer';
        $allowed_view_modules_str = '';
        
        try {
            if (isset($pdo)) {
                $stmt = $pdo->prepare("SELECT role, allowed_view_modules FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                if ($user) {
                    $role = $user['role'];
                    $allowed_view_modules_str = $user['allowed_view_modules'] ?? '';
                }
            } else {
                $role = $_SESSION['role'] ?? 'dealer';
                $allowed_view_modules_str = $_SESSION['allowed_view_modules'] ?? '';
            }
        } catch (Exception $e) {
            $role = $_SESSION['role'] ?? 'dealer';
            $allowed_view_modules_str = $_SESSION['allowed_view_modules'] ?? '';
        }

        if ($role === 'admin') {
            return true;
        }

        if ($role === 'dealer') {
            $clean_page = strtolower(basename($page_name));
            // Dealers can view dashboard, pss delivery, master hub, and hd stock take
            if ($clean_page === 'index.php' || $clean_page === 'pss_delivery.php' || $clean_page === 'master_hubpss.php' || $clean_page === 'hd_stock_take.php' || $clean_page === 'api_pss.php' || $clean_page === 'save_hd_stock.php') {
                return true;
            }
            return false;
        }

        if ($role === 'staff_jomcha') {
            $clean_page = strtolower(basename($page_name));
            if ($clean_page === 'jomcha_request_stock.php' || $clean_page === 'process_jomcha_request.php' || $clean_page === 'jomcha_stock_take.php' || $clean_page === 'jomcha_monitor_stock.php') {
                return true;
            }
        }

        // Clean page name
        $page_name = strtolower(basename($page_name));

        // Pages that are viewable by any authenticated staff/user
        $always_allow = [
            'index.php', 
            'logout.php', 
            'profile.php', 
 
            'get_batches.php', 
            'get_outbound_details.php',
            'inventory_report.php',
            'outbound_history.php',
            'warehouse_layout.php',
            'get_warehouse_grid.php',
            'get_available_batches.php'
        ];
        if (in_array($page_name, $always_allow)) {
            return true;
        }

        // Parse allowed view modules
        $allowed_view_modules = array_filter(array_map('trim', explode(',', $allowed_view_modules_str)));

        // Module mapping
        $module_map = [
            'master_hubpss.php' => 'pss',
            'pss_delivery.php' => 'pss',
            'api_pss.php' => 'pss',
            'save_delivery.php' => 'pss',
            'save_school_import.php' => 'pss',
            'import_schools.php' => 'pss',
            'import_co_ui.php' => 'pss',
            'save_import.php' => 'pss',
            'save_co_update.php' => 'pss',
            'hd_stock_take.php' => 'pss',
            'save_hd_stock.php' => 'pss',
            
            'daily_closing_report.php' => 'daily_closing',
            'save_daily_closing.php' => 'daily_closing',
            'daily_closing_history.php' => 'daily_closing',
            
            'stock_transfer.php' => 'stock_transfer',
            'save_stock_transfer.php' => 'stock_transfer',
            
            'stock_take.php' => 'stock_take',
            'save_stock_take.php' => 'stock_take',
            
            'product_management.php' => 'product_management',
            'process_product_import.php' => 'product_management',
            'import_products.php' => 'product_management',
            
            'receiving.php' => 'receiving',
            'receiving_multi.php' => 'receiving',
            'save_receiving.php' => 'receiving',
            'save_receiving_multi.php' => 'receiving',
            
            'commercial_outbound.php' => 'outbound_reconcile',
            'reconcile.php' => 'outbound_reconcile',
            'save_commercial_outbound.php' => 'outbound_reconcile',
            'save_reconciliation.php' => 'outbound_reconcile',
            
            'jomcha_outbound.php' => 'jomcha',
            'save_jomcha_outbound.php' => 'jomcha',
            'jomcha_request_stock.php' => 'jomcha',
            'process_jomcha_request.php' => 'jomcha',
            'jomcha_stock_take.php' => 'jomcha',
            'jomcha_monitor_stock.php' => 'jomcha',
            
            'spoilage_record.php' => 'spoilage',
            'spoilage_report.php' => 'spoilage',
            'save_spoilage.php' => 'spoilage',
            'update_spoilage_status.php' => 'spoilage',
            'cleanup_photos.php' => 'spoilage',
            
            'user_management.php' => 'user_management',
            'save_user.php' => 'user_management',
            'delete_user.php' => 'user_management',
            
            'pallet_return.php' => 'pallet_return',
            'print_prf_slip.php' => 'pallet_return',
            'get_pallet_return_details.php' => 'pallet_return',
            'pallet_management.php' => 'pallet_return'
        ];

        $target_module = $module_map[$page_name] ?? null;
        if ($target_module && in_array($target_module, $allowed_view_modules)) {
            return true;
        }

        if (strpos($page_name, 'pss') !== false && in_array('pss', $allowed_view_modules)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('is_staff_role')) {
    function is_staff_role($role) {
        return in_array(strtolower($role), ['admin', 'staff', 'intern', 'pss_admin', 'staff_jomcha']);
    }
}

// Global request interceptor for write actions
$current_script = basename($_SERVER['PHP_SELF']);
$is_write_action = false;

// 1. All POST requests (except login/logout) are write actions
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && $current_script !== 'login.php' && $current_script !== 'logout.php') {
    $is_write_action = true;
}

// 2. Specific GET parameters that trigger writes
if (isset($_GET['toggle_id']) && $current_script === 'product_management.php') {
    $is_write_action = true;
}
if ($current_script === 'api_pss.php') {
    $action = $_GET['action'] ?? '';
    if (in_array($action, ['save_vehicles_global', 'save_schools', 'start_new_co'])) {
        $is_write_action = true;
    }
}

// Enforce read/view permission for GET requests
if (isset($_SESSION['user_id']) && $current_script !== 'login.php' && $current_script !== 'logout.php') {
    // Only check if it's not a write action, because write actions are handled below
    if (!$is_write_action) {
        if (!check_view_permission($current_script)) {
            http_response_code(403);
            echo '<div style="font-family: sans-serif; text-align: center; padding: 100px 20px;">
                    <h1 style="color: #e74c3c;">🚫 Akses Dihalang!</h1>
                    <p>Akaun anda tiada kebenaran untuk melihat halaman ini.</p>
                    <a href="index.php" style="color: #3498db; font-weight: bold; text-decoration: none;">Kembali ke Dashboard</a>
                  </div>';
            exit;
        }
    }
}

if ($is_write_action) {
    if (isset($_SESSION['user_id'])) {
        if (!check_write_permission($current_script)) {
            // Determine if request expects JSON (API or AJAX)
            $is_api = (
                strpos($_SERVER['PHP_SELF'], '/api/') !== false ||
                $current_script === 'api_pss.php' ||
                $current_script === 'save_import.php' ||
                (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            );

            if ($is_api) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Akses Dihalang: Akaun anda tiada kebenaran untuk melakukan tindakan ini.'
                ]);
                exit;
            } else {
                // Standard form post redirects back to referer with error param
                $referer = $_SERVER['HTTP_REFERER'] ?? '';
                if ($referer) {
                    $clean_referer = preg_replace('/[?&]error=[^&]+/', '', $referer);
                    $separator = (parse_url($clean_referer, PHP_URL_QUERY) === null) ? '?' : '&';
                    header("Location: " . $clean_referer . $separator . "error=no_permission");
                } else {
                    http_response_code(403);
                    echo '<div style="font-family: sans-serif; text-align: center; padding: 100px 20px;">
                            <h1 style="color: #e74c3c;">🚫 Akses Dihalang!</h1>
                            <p>Akaun anda tiada kebenaran untuk menulis/mengedit data pada halaman ini.</p>
                            <a href="index.php" style="color: #3498db; font-weight: bold; text-decoration: none;">Kembali ke Dashboard</a>
                          </div>';
                }
                exit;
            }
        }
    }
}
?>
