<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$page_title = $page_title ?? 'MMS WMS System';

// Sekatan akses pengguna (kecuali pada halaman login.php)
$current_page = basename($_SERVER['PHP_SELF']);
if (!isset($_SESSION['user_id']) && $current_page !== 'login.php') {
    header('Location: login.php');
    exit;
}
$has_write_permission = true;
if (function_exists('check_write_permission')) {
    $has_write_permission = check_write_permission($current_page);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <!-- Favicon / Gambar Browser Logo -->
    <link rel="shortcut icon" href="img/logo.png" type="image/png">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <!-- Custom Design & Responsive Stylesheet -->
    <link href="assets/css/style.css?v=<?= time() ?>" rel="stylesheet">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Dynamic Responsive Tables Helper for Mobile Device View -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const wrappers = document.querySelectorAll(".table-responsive");
        wrappers.forEach(wrapper => {
            wrapper.classList.add("responsive-table-cards");
            const table = wrapper.querySelector("table");
            if (!table) return;
            
            const headers = [];
            const ths = table.querySelectorAll("thead th");
            ths.forEach(th => {
                headers.push(th.textContent.trim());
            });
            
            const trs = table.querySelectorAll("tbody tr");
            trs.forEach(tr => {
                const tds = tr.querySelectorAll("td");
                tds.forEach((td, index) => {
                    if (headers[index]) {
                        if (!td.getAttribute("data-label")) {
                            td.setAttribute("data-label", headers[index]);
                        }
                    }
                });
            });
        });
    });
    </script>
    <script>
    window.MMS_HAS_WRITE_PERMISSION = <?= ($has_write_permission ?? true) ? 'true' : 'false' ?>;
    document.addEventListener("DOMContentLoaded", function() {
        if (!window.MMS_HAS_WRITE_PERMISSION) {
            // Disable all form inputs, selects, textareas, and buttons inside forms, except filters/searches
            const forms = document.querySelectorAll("form");
            forms.forEach(form => {
                if (form.id === "loginForm" || form.id === "langForm" || form.id === "changePasswordForm") return;
                if (form.getAttribute("method") && form.getAttribute("method").toLowerCase() === "get") return;
                
                form.querySelectorAll("input, select, textarea, button").forEach(el => {
                    // Do not disable search boxes, date pickers, or language toggles
                    if (
                        el.id === 'audit_date_picker' || 
                        el.name === 'date' || 
                        el.id === 'search_product' || 
                        el.id === 'search_batch' || 
                        el.id === 'search_expiry' || 
                        el.id === 'search_do' || 
                        el.id === 'search_po' || 
                        el.id === 'search_date' ||
                        el.closest('.dataTables_filter') || 
                        el.closest('.dataTables_length') || 
                        el.type === 'search'
                    ) {
                        return;
                    }
                    
                    // Don't disable back/close/cancel buttons
                    if (
                        el.tagName === 'BUTTON' && 
                        (el.type === 'button' || el.classList.contains('btn-close') || 
                         el.innerText.toLowerCase().includes('back') || el.innerText.toLowerCase().includes('kembali'))
                    ) {
                        return;
                    }
                    
                    el.disabled = true;
                    el.classList.add("readonly-disabled-field");
                });
            });
            
            // Also disable any page buttons meant for write actions
            document.querySelectorAll(".btn").forEach(btn => {
                // Ignore cancel/print/dashboard/home/history buttons
                const text = btn.innerText.toLowerCase();
                const is_safe_action = (
                    text.includes("back") || 
                    text.includes("kembali") || 
                    text.includes("print") || 
                    text.includes("cetak") || 
                    text.includes("dashboard") ||
                    text.includes("history") ||
                    btn.classList.contains("btn-outline-light")
                );
                
                if (!is_safe_action && (
                    text.includes("add") || 
                    text.includes("tambah") || 
                    text.includes("save") || 
                    text.includes("simpan") || 
                    text.includes("delete") || 
                    text.includes("padam") || 
                    text.includes("import") || 
                    text.includes("unlock") || 
                    text.includes("confirm") || 
                    text.includes("sahkan") || 
                    text.includes("update") ||
                    btn.type === "submit"
                )) {
                    btn.disabled = true;
                    btn.classList.add("disabled");
                    btn.setAttribute("title", "Anda tiada kebenaran untuk melakukan tindakan ini.");
                }
            });
        }
    });
    </script>
</head>
<body>

<?php if (!isset($hide_navbar) || !$hide_navbar): 

$role = $_SESSION['role'] ?? '';
$is_admin = ($role === 'admin');
$is_staff = function_exists('is_staff_role') ? is_staff_role($role) : ($role === 'admin' || $role === 'staff');

// Get allowed view modules
$allowed_view_modules = [];
if (isset($_SESSION['user_id']) && isset($pdo)) {
    try {
        $stmtView = $pdo->prepare("SELECT allowed_view_modules FROM users WHERE id = ? LIMIT 1");
        $stmtView->execute([$_SESSION['user_id']]);
        $allowed_view_str = $stmtView->fetchColumn();
        if ($allowed_view_str) {
            $allowed_view_modules = array_filter(array_map('trim', explode(',', $allowed_view_str)));
        }
    } catch (Exception $e) {
        // Silently ignore if column doesn't exist yet (migration pending)
        $allowed_view_modules = [];
    }
}
?>
<nav class="navbar navbar-expand-lg mms-navbar sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <img src="img/logo.png" alt="MMS Logo" style="height: 32px; width: auto; border-radius: 6px; border: 1px solid rgba(255,255,255,0.15);">
            <span class="fw-800 tracking-wide text-white">MMS HUB</span>
        </a>
        <button class="navbar-toggler text-white border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mmsNav">
            <i class="bi bi-list fs-2"></i>
        </button>
        <div class="collapse navbar-collapse" id="mmsNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (strtolower($role) !== 'staff_jomcha'): ?>
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-house-door me-1"></i><span data-lang="nav_dashboard">Dashboard</span></a></li>
                
                <?php if ($is_staff): ?>
                
                <?php if ($is_admin || in_array('receiving', $allowed_view_modules)): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-box-arrow-in-down me-1"></i><span data-lang="nav_receiving">Receiving</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="receiving.php"><span data-lang="nav_single_receive">Single Item Receive</span></a></li>
                        <li><a class="dropdown-item" href="receiving_multi.php"><span data-lang="nav_multi_receive">Multi-Item Receive</span></a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php 
                $show_operations = $is_admin || 
                                   (($_SESSION['role'] ?? '') === 'staff_jomcha') ||
                                   in_array('outbound_reconcile', $allowed_view_modules) || 
                                   in_array('pss', $allowed_view_modules) || 
                                   in_array('daily_closing', $allowed_view_modules) || 
                                   in_array('stock_take', $allowed_view_modules) || 
                                   in_array('stock_transfer', $allowed_view_modules);
                if ($show_operations): 
                ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-truck me-1"></i><span data-lang="nav_operations">Operations</span>
                    </a>
                    <ul class="dropdown-menu">
                        <?php if ($is_admin || in_array('outbound_reconcile', $allowed_view_modules)): ?>
                            <li><a class="dropdown-item" href="commercial_outbound.php"><span data-lang="nav_commercial_out">Commercial Outbound</span></a></li>
                            <li><a class="dropdown-item" href="jomcha_outbound.php"><i class="bi bi-shop me-1 text-info"></i><span>Jomcha Outbound</span></a></li>
                        <?php endif; ?>
                        <?php if ($is_admin || (($_SESSION['role'] ?? '') === 'staff_jomcha') || in_array('outbound_reconcile', $allowed_view_modules)): ?>
                            <li><a class="dropdown-item" href="jomcha_request_stock.php"><i class="bi bi-cart-plus me-1 text-primary"></i><span>Mohon Stok (Jomcha)</span></a></li>
                        <?php endif; ?>
                        <?php if ($is_admin || (($_SESSION['role'] ?? '') === 'staff_jomcha')): ?>
                            <li><a class="dropdown-item" href="jomcha_stock_take.php"><i class="bi bi-clipboard-check me-1 text-purple"></i><span>Kiraan Stok (Jomcha)</span></a></li>
                            <li><a class="dropdown-item" href="jomcha_monitor_stock.php"><i class="bi bi-display me-1 text-success"></i><span>Pantau Stok (Jomcha)</span></a></li>
                        <?php endif; ?>
                        <?php if ($is_admin || in_array('pss', $allowed_view_modules)): ?>
                            <li><a class="dropdown-item" href="pss_delivery.php"><span data-lang="nav_pss_delivery">PSS Delivery</span></a></li>
                        <?php endif; ?>
                        
                        <li><a class="dropdown-item" href="outbound_history.php"><span data-lang="nav_outbound_hist">Outbound History</span></a></li>
                        
                        <?php if ($is_admin || in_array('outbound_reconcile', $allowed_view_modules) || in_array('daily_closing', $allowed_view_modules) || in_array('stock_take', $allowed_view_modules) || in_array('stock_transfer', $allowed_view_modules)): ?>
                            <div class="dropdown-divider"></div>
                        <?php endif; ?>
                        
                        <?php if ($is_admin || in_array('outbound_reconcile', $allowed_view_modules)): ?>
                            <li><a class="dropdown-item" href="reconcile.php"><span data-lang="nav_daily_reconcile">Daily Reconcile</span></a></li>
                        <?php endif; ?>
                        <?php if ($is_admin || in_array('daily_closing', $allowed_view_modules)): ?>
                            <li><a class="dropdown-item" href="daily_closing_report.php"><i class="bi bi-calendar2-check-fill me-1 text-warning"></i><span data-lang="nav_daily_closing">Daily Closing Audit</span></a></li>
                        <?php endif; ?>
                        <?php if ($is_admin || in_array('stock_take', $allowed_view_modules)): ?>
                            <li><a class="dropdown-item" href="stock_take.php"><span data-lang="nav_stock_take">Stock Take</span></a></li>
                        <?php endif; ?>
                        <?php if ($is_admin || in_array('stock_transfer', $allowed_view_modules)): ?>
                            <li><a class="dropdown-item" href="stock_transfer.php"><i class="bi bi-arrow-left-right me-1"></i><span data-lang="nav_stock_transfer">Stock Transfer</span></a></li>
                        <?php endif; ?>
                        <?php if ($is_admin || in_array('pss', $allowed_view_modules)): ?>
                            <li><a class="dropdown-item" href="hd_stock_take.php"><i class="bi bi-clipboard-check-fill me-1 text-warning"></i><span data-lang="hd_stock_take_title">HD Stock Take</span></a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-graph-up-arrow me-1"></i><span data-lang="nav_reports">Reports</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="reports.php"><i class="bi bi-search me-1"></i><span data-lang="nav_wh_monitor">Warehouse Monitor</span></a></li>
                        <li><a class="dropdown-item" href="inventory_report.php"><i class="bi bi-clipboard2-data me-1"></i><span data-lang="nav_inv_report">Inventory Report</span></a></li>
                        <li><a class="dropdown-item" href="pallet_management.php"><i class="bi bi-grid-3x3-gap me-1"></i><span data-lang="nav_pallet_monitor">Pallet Monitor</span></a></li>
                    </ul>
                </li>
                
                <?php 
                $show_system = $is_admin || 
                               in_array('product_management', $allowed_view_modules) || 
                               in_array('spoilage', $allowed_view_modules);
                if ($show_system): 
                ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-gear me-1"></i><span data-lang="nav_system">System</span>
                    </a>
                    <ul class="dropdown-menu">
                        <?php if ($is_admin || in_array('product_management', $allowed_view_modules)): ?>
                            <li><a class="dropdown-item" href="product_management.php"><span data-lang="nav_master_data">Product Management</span></a></li>
                        <?php endif; ?>
                        <?php if ($is_admin || in_array('spoilage', $allowed_view_modules)): ?>
                            <li><a class="dropdown-item" href="spoilage_record.php"><span data-lang="nav_spoilage_report">Spoilage Report</span></a></li>
                            <li><a class="dropdown-item" href="spoilage_report.php"><span data-lang="nav_spoilage_list">Spoilage List</span></a></li>
                        <?php endif; ?>
                        <?php if ($is_admin): ?>
                        <div class="dropdown-divider"></div>
                        <li><a class="dropdown-item" href="user_management.php"><i class="bi bi-people me-1"></i><span data-lang="nav_user_mgmt">User Management</span></a></li>
                        <li><a class="dropdown-item" href="notification_settings.php"><i class="bi bi-bell me-1 text-warning"></i><span>Notification Settings</span></a></li>
                        <li><a class="dropdown-item" href="system_logs.php"><i class="bi bi-shield-check me-1"></i><span data-lang="nav_audit_logs">System Audit Logs</span></a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <?php else: ?>
                <li class="nav-item"><a class="nav-link" href="master_hubpss.php"><i class="bi bi-cpu-fill me-1 text-info"></i><span data-lang="card_pss_hub">PSS Master Hub</span></a></li>
                <li class="nav-item"><a class="nav-link" href="pss_delivery.php"><i class="bi bi-mortarboard-fill me-1 text-success"></i><span data-lang="card_pss_delivery">School Delivery (PSS)</span></a></li>
                <li class="nav-item"><a class="nav-link" href="hd_stock_take.php"><i class="bi bi-clipboard-check-fill me-1 text-warning"></i><span data-lang="hd_stock_take_title">HD Stock Take</span></a></li>
                <?php endif; ?>
                <?php endif; ?>
            </ul>

            <!-- Language Toggle + Profile + Logout -->
            <div class="d-flex align-items-center gap-2">

                <!-- ===== LANGUAGE TOGGLE ===== -->
                <div class="d-flex align-items-center" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); border-radius: 20px; padding: 3px 4px; gap: 2px;">
                    <button id="lang-btn-en"
                            onclick="MMS_LANG.set('en')"
                            title="Switch to English"
                            style="background:transparent; border:none; border-radius:16px; padding:3px 9px; font-size:0.75rem; font-weight:700; color:rgba(255,255,255,0.6); cursor:pointer; transition:all 0.2s; letter-spacing:0.3px;">
                        🇬🇧 EN
                    </button>
                    <button id="lang-btn-ms"
                            onclick="MMS_LANG.set('ms')"
                            title="Tukar ke Bahasa Melayu"
                            style="background:transparent; border:none; border-radius:16px; padding:3px 9px; font-size:0.75rem; font-weight:700; color:rgba(255,255,255,0.6); cursor:pointer; transition:all 0.2s; letter-spacing:0.3px;">
                        🇲🇾 BM
                    </button>
                </div>

                <!-- Profile Info -->
                <span class="text-white small fw-bold d-flex align-items-center gap-1">
                    <i class="bi bi-person-circle me-1 text-info fs-5"></i> 
                    <?= htmlspecialchars($_SESSION['full_name'] ?? 'Pengguna') ?> 
                    <span class="badge bg-info text-dark ms-1 fw-bold text-uppercase" style="font-size: 0.65rem;"><?= htmlspecialchars($role) ?></span>
                    <button type="button" class="btn btn-sm btn-outline-info border-0 p-1 text-white ms-1" data-bs-toggle="modal" data-bs-target="#changePasswordModal" title="Tukar Kata Laluan">
                        <i class="bi bi-key-fill"></i>
                    </button>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm fw-bold border-white border-opacity-25 px-3">
                    <i class="bi bi-box-arrow-right me-1"></i><span data-lang="nav_logout">Log Keluar</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<style>
    /* Active language button */
    #lang-btn-en.active, #lang-btn-ms.active {
        background: rgba(6,182,212,0.85) !important;
        color: white !important;
        box-shadow: 0 2px 8px rgba(6,182,212,0.4);
    }
    #lang-btn-en:hover:not(.active), #lang-btn-ms:hover:not(.active) {
        background: rgba(255,255,255,0.12) !important;
        color: white !important;
    }
</style>

<!-- Modal Tukar Kata Laluan -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="changePasswordModalLabel"><i class="bi bi-key-fill me-2 text-info"></i>Tukar Kata Laluan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="changePasswordForm">
                <div class="modal-body">
                    <div id="changePasswordAlert" class="alert d-none"></div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Kata Laluan Semasa *</label>
                        <input type="password" name="current_password" class="form-control" required placeholder="Masukkan kata laluan semasa">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Kata Laluan Baharu *</label>
                        <input type="password" name="new_password" class="form-control" required placeholder="Min 4 aksara">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Sahkan Kata Laluan Baharu *</label>
                        <input type="password" name="confirm_password" class="form-control" required placeholder="Masukkan semula kata laluan baharu">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info text-white btn-sm fw-bold">Simpan Laluan Baharu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#changePasswordForm').on('submit', function(e) {
        e.preventDefault();
        const alertBox = $('#changePasswordAlert');
        alertBox.addClass('d-none').removeClass('alert-success alert-danger');
        
        $.ajax({
            url: 'api/change_password.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alertBox.addClass('alert-success').removeClass('d-none').text(response.message);
                    $('#changePasswordForm')[0].reset();
                    setTimeout(function() {
                        $('#changePasswordModal').modal('hide');
                        alertBox.addClass('d-none');
                    }, 2000);
                } else {
                    alertBox.addClass('alert-danger').removeClass('d-none').text(response.message);
                }
            },
            error: function(xhr) {
                const errMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Ralat sambungan pelayan.';
                alertBox.addClass('alert-danger').removeClass('d-none').text(errMsg);
            }
        });
    });
});
</script>
<?php endif; ?>

<!-- Check and show visual alert if user has no write permission on this page -->
<?php if (!$has_write_permission && $current_page !== 'index.php' && $current_page !== 'login.php' && isset($_SESSION['user_id'])): ?>
<div class="container-fluid px-4 mt-3 no-print">
    <div class="alert alert-warning border-0 bg-warning bg-opacity-10 text-warning d-flex align-items-center gap-3 py-3 px-4 rounded-3 shadow-sm" role="alert">
        <i class="bi bi-eye-fill fs-3"></i>
        <div>
            <h5 class="alert-heading mb-1 fw-bold">Mod Paparan Sahaja (Read-Only Mode)</h5>
            <p class="mb-0 small opacity-90">Akaun anda hanya dibenarkan untuk melihat data pada halaman ini. Sebarang tindakan penambahan, pengeditan, atau pemadaman disekat.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Display error alert if redirected back with error=no_permission -->
<?php if (isset($_GET['error']) && $_GET['error'] === 'no_permission'): ?>
<div class="container-fluid px-4 mt-3 no-print">
    <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger d-flex align-items-center gap-3 py-3 px-4 rounded-3 shadow-sm" role="alert">
        <i class="bi bi-shield-slash-fill fs-3"></i>
        <div>
            <h5 class="alert-heading mb-1 fw-bold">Tindakan Disekat (Access Denied)</h5>
            <p class="mb-0 small opacity-90">Ralat: Anda tidak mempunyai kebenaran keselamatan untuk menyimpan atau mengubah data pada halaman ini.</p>
        </div>
    </div>
</div>
<?php endif; ?>

