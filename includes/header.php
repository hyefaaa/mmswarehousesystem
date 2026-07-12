<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = $page_title ?? 'MMS WMS System';

// Sekatan akses pengguna (kecuali pada halaman login.php)
$current_page = basename($_SERVER['PHP_SELF']);
if (!isset($_SESSION['user_id']) && $current_page !== 'login.php') {
    header('Location: login.php');
    exit;
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
</head>
<body>

<?php if (!isset($hide_navbar) || !$hide_navbar): 

$role = $_SESSION['role'] ?? '';
$is_admin = ($role === 'admin');
$is_staff = ($role === 'admin' || $role === 'staff');
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
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-house-door me-1"></i><span data-lang="nav_dashboard">Dashboard</span></a></li>
                
                <?php if ($is_staff): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-box-arrow-in-down me-1"></i><span data-lang="nav_receiving">Receiving</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="receiving.php"><span data-lang="nav_single_receive">Single Item Receive</span></a></li>
                        <li><a class="dropdown-item" href="receiving_multi.php"><span data-lang="nav_multi_receive">Multi-Item Receive</span></a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-truck me-1"></i><span data-lang="nav_operations">Operations</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="commercial_outbound.php"><span data-lang="nav_commercial_out">Commercial Outbound</span></a></li>
                        <li><a class="dropdown-item" href="pss_delivery.php"><span data-lang="nav_pss_delivery">PSS Delivery</span></a></li>
                        <li><a class="dropdown-item" href="outbound_history.php"><span data-lang="nav_outbound_hist">Outbound History</span></a></li>
                        <div class="dropdown-divider"></div>
                        <li><a class="dropdown-item" href="reconcile.php"><span data-lang="nav_daily_reconcile">Daily Reconcile</span></a></li>
                        <li><a class="dropdown-item" href="daily_closing_report.php"><i class="bi bi-calendar2-check-fill me-1 text-warning"></i><span>Daily Closing Audit</span></a></li>
                        <li><a class="dropdown-item" href="stock_take.php"><span data-lang="nav_stock_take">Stock Take</span></a></li>
                        <li><a class="dropdown-item" href="stock_transfer.php"><i class="bi bi-arrow-left-right me-1"></i><span data-lang="nav_stock_transfer">Stock Transfer</span></a></li>
                    </ul>
                </li>

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
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-gear me-1"></i><span data-lang="nav_system">System</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="product_management.php"><span data-lang="nav_master_data">Product Management</span></a></li>
                        <li><a class="dropdown-item" href="spoilage_record.php"><span data-lang="nav_spoilage_report">Spoilage Report</span></a></li>
                        <li><a class="dropdown-item" href="spoilage_report.php"><span data-lang="nav_spoilage_list">Spoilage List</span></a></li>
                        <?php if ($is_admin): ?>
                        <div class="dropdown-divider"></div>
                        <li><a class="dropdown-item" href="user_management.php"><i class="bi bi-people me-1"></i><span data-lang="nav_user_mgmt">User Management</span></a></li>
                        <li><a class="dropdown-item" href="system_logs.php"><i class="bi bi-shield-check me-1"></i><span data-lang="nav_audit_logs">System Audit Logs</span></a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item"><a class="nav-link" href="master_hubpss.php"><i class="bi bi-cpu-fill me-1 text-info"></i><span data-lang="card_pss_hub">PSS Master Hub</span></a></li>
                <li class="nav-item"><a class="nav-link" href="pss_delivery.php"><i class="bi bi-mortarboard-fill me-1 text-success"></i><span data-lang="card_pss_delivery">School Delivery (PSS)</span></a></li>
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
                <span class="text-white small fw-bold">
                    <i class="bi bi-person-circle me-1 text-info fs-5"></i> 
                    <?= htmlspecialchars($_SESSION['full_name'] ?? 'Pengguna') ?> 
                    <span class="badge bg-info text-dark ms-1 fw-bold text-uppercase" style="font-size: 0.65rem;"><?= htmlspecialchars($role) ?></span>
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
<?php endif; ?>

