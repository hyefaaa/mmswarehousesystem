<?php
// index.php - THE MASTER HUB (PREMIUM EDITION)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';

require_once 'config/db.php';

try {
    // Statistik untuk Dashboard
    // Mengambil data produk aktif, jumlah stok (inventory), dan laporan kerosakan yang pending
    $total_products = $pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();
    $total_stock = $pdo->query("SELECT SUM(qty_on_hand) FROM inventory_batches")->fetchColumn() ?: 0;
    $pending_spoilage = $pdo->query("SELECT COUNT(*) FROM spoilage_logs WHERE claim_status = 'Pending'")->fetchColumn() ?: 0;

    // Ambil senarai batch yang bakal tamat tempoh dalam masa 90 hari
    $expiring_batches = $pdo->query("
        SELECT b.id, b.batch_no, b.expiry_date, b.qty_on_hand, p.name as product_name,
               DATEDIFF(b.expiry_date, NOW()) as days_left
        FROM inventory_batches b
        JOIN products p ON b.product_id = p.id
        WHERE b.qty_on_hand > 0 
          AND b.expiry_date IS NOT NULL 
          AND DATEDIFF(b.expiry_date, NOW()) <= 90
        ORDER BY b.expiry_date ASC
        LIMIT 10
    ")->fetchAll();

    // Ambil senarai produk dengan stok rendah (jumlah < 50 ctn)
    $low_stock_products = $pdo->query("
        SELECT p.id, p.name, p.category, SUM(COALESCE(b.qty_on_hand, 0)) as total_qty, p.uom
        FROM products p
        LEFT JOIN inventory_batches b ON p.id = b.product_id AND b.location_status = 'Warehouse'
        WHERE p.is_active = 1
        GROUP BY p.id
        HAVING total_qty < 50
        ORDER BY total_qty ASC
        LIMIT 10
    ")->fetchAll();

    // Ambil permohonan stok Jomcha yang pending (untuk admin/staff gudang)
    $pending_jomcha_requests = $pdo->query("
        SELECT r.*, 
               (SELECT COUNT(*) FROM jomcha_request_items WHERE request_id = r.id) as item_count,
               (SELECT SUM(qty_requested) FROM jomcha_request_items WHERE request_id = r.id) as total_qty
        FROM jomcha_requests r
        WHERE r.status = 'Pending'
        ORDER BY r.id DESC
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {
    $total_products = $total_stock = $pending_spoilage = 0;
    $expiring_batches = [];
    $low_stock_products = [];
    $pending_jomcha_requests = [];
}

$is_jomcha = (strtolower($role) === 'staff_jomcha');
$total_pending_req = 0;
$total_approved_req = 0;
$total_rejected_req = 0;
$days_since_last_take = null;
$last_take_date_fmt = "Belum Pernah";
$recent_jomcha_requests = [];

if ($is_jomcha) {
    try {
        $lt = $pdo->query("SELECT MAX(take_date) as last_date FROM jomcha_stock_takes")->fetch();
        if ($lt && $lt['last_date']) {
            $last_take_date_fmt = date('d/m/Y', strtotime($lt['last_date']));
            $days_since_last_take = (new DateTime($lt['last_date']))->diff(new DateTime())->days;
        }

        $total_pending_req = $pdo->prepare("SELECT COUNT(*) FROM jomcha_requests WHERE requested_by = ? AND status = 'Pending'");
        $total_pending_req->execute([$username]);
        $total_pending_req = $total_pending_req->fetchColumn();

        $total_approved_req = $pdo->prepare("SELECT COUNT(*) FROM jomcha_requests WHERE requested_by = ? AND status = 'Approved' AND MONTH(request_date) = MONTH(CURDATE())");
        $total_approved_req->execute([$username]);
        $total_approved_req = $total_approved_req->fetchColumn();

        $total_rejected_req = $pdo->prepare("SELECT COUNT(*) FROM jomcha_requests WHERE requested_by = ? AND status = 'Rejected'");
        $total_rejected_req->execute([$username]);
        $total_rejected_req = $total_rejected_req->fetchColumn();

        $recent_jomcha_stmt = $pdo->prepare("
            SELECT r.*, 
                   (SELECT COUNT(*) FROM jomcha_request_items WHERE request_id = r.id) as item_count,
                   (SELECT SUM(qty_requested) FROM jomcha_request_items WHERE request_id = r.id) as total_qty
            FROM jomcha_requests r
            WHERE r.requested_by = ?
            ORDER BY r.id DESC
            LIMIT 5
        ");
        $recent_jomcha_stmt->execute([$username]);
        $recent_jomcha_requests = $recent_jomcha_stmt->fetchAll();
    } catch (Exception $e) {}
}

$page_title = 'MMS Master Hub | Susumura';
require_once 'includes/header.php';
?>
<style>
    /* Hero Header */
    .hero-section {
        background: var(--gradient-primary);
        color: white;
        padding: 70px 0 110px 0;
        margin-bottom: -60px;
        position: relative;
        overflow: hidden;
        border-bottom: 3.5px solid var(--mms-cyan);
    }

    .hero-section::after {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: radial-gradient(circle at 80% 20%, rgba(6, 182, 212, 0.15) 0%, transparent 60%);
        pointer-events: none;
    }

    /* Stats Card Upgraded - Focus on Depth */
    .stat-card {
        border: 1px solid rgba(241, 245, 249, 0.8);
        border-radius: 24px;
        background: white;
        box-shadow: var(--card-shadow);
        transition: var(--transition-smooth);
        position: relative;
        overflow: hidden;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .stat-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--hover-shadow);
        border-color: rgba(99, 102, 241, 0.15);
    }

    .stat-icon {
        width: 56px; height: 56px;
        border-radius: 16px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.6rem;
        margin-bottom: 20px;
        transition: var(--transition-smooth);
    }

    .stat-card:hover .stat-icon {
        transform: scale(1.1) rotate(6deg);
    }

    .stat-value {
        font-size: 2.2rem;
        font-weight: 800;
        line-height: 1.1;
        margin-bottom: 6px;
        letter-spacing: -1px;
        color: var(--mms-navy);
    }

    .stat-label {
        font-size: 0.74rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        color: var(--mms-text-muted);
        margin-bottom: 4px;
    }

    /* Navigation Cards */
    .section-title {
        font-weight: 800;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: var(--mms-navy);
        margin-top: 2rem;
        margin-bottom: 1.2rem;
        padding-left: 12px;
        border-left: 4px solid var(--mms-indigo);
    }

    .nav-card {
        border: 1px solid rgba(241, 245, 249, 0.7);
        border-radius: 18px;
        background: white;
        transition: var(--transition-smooth);
        text-decoration: none;
        display: flex;
        align-items: center;
        padding: 1.5rem;
        margin-bottom: 1.2rem;
        box-shadow: var(--card-shadow);
    }
    
    .nav-card:hover {
        transform: translateY(-5px) scale(1.008);
        box-shadow: var(--hover-shadow);
        border-color: rgba(99, 102, 241, 0.2);
    }

    .nav-card .icon-box {
        width: 52px; height: 52px;
        border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem; 
        margin-right: 1.2rem;
        flex-shrink: 0;
        transition: var(--transition-smooth);
    }

    .nav-card:hover .icon-box {
        transform: scale(1.1) rotate(-3deg);
    }
    
    .nav-card .title { 
        font-weight: 700; 
        color: var(--mms-navy); 
        display: block; 
        font-size: 1.05rem; 
        margin-bottom: 3px;
    }
    
    .nav-card .desc { 
        font-size: 0.84rem; 
        color: var(--mms-text-muted); 
        line-height: 1.4;
    }

    /* Dashboard Responsiveness Overrides */
    @media (max-width: 767px) {
        .hero-section {
            padding: 40px 0 80px 0;
            margin-bottom: -50px;
        }
        
        .stat-card {
            padding: 1.4rem !important;
        }
        
        .stat-value {
            font-size: 1.85rem;
        }
        
        .nav-card {
            padding: 1.1rem;
        }
        
        .nav-card .icon-box {
            width: 44px; height: 44px;
            font-size: 1.2rem;
            margin-right: 0.9rem;
        }
    }
    /* Shimmer Progress bar for PSS */
    .progress-wrap-hd {
        background: rgba(255,255,255,0.12);
        border-radius: 30px;
        height: 38px;
        position: relative;
        overflow: hidden;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        margin-top: 15px;
    }
    .progress-fill-hd {
        background: linear-gradient(90deg, #10b981, #34d399, #6ee7b7, #34d399);
        background-size: 200% 100%;
        animation: shimmer-hd 2.5s infinite linear;
        height: 100%;
        border-radius: 30px;
        transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
    }
    @keyframes shimmer-hd {
        0% { background-position: 200% center; }
        100% { background-position: -200% center; }
    }
</style>

<div class="hero-section">
    <div class="container-fluid px-4">
        <div class="row align-items-center">
            <div class="col-md-8 d-flex flex-column flex-md-row align-items-center gap-3 text-center text-md-start">
                <img src="img/logo.png" alt="MMS Logo" style="height: 60px; width: auto; border-radius: 12px; border: 2.5px solid rgba(255,255,255,0.25); box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                <div>
                    <?php if ($is_jomcha): ?>
                    <h1 class="fw-800 mb-0" style="font-size: 2.1rem; letter-spacing: -0.5px;" data-lang="jomcha_dash_title">JOMCHA OUTLET HUB</h1>
                    <p class="opacity-75 mb-0 fw-light" style="font-size: 0.92rem;" data-lang="jomcha_dash_subtitle">Jomcha Outlet Requisitions & Stock Management Portal</p>
                    <?php elseif ($is_staff): ?>
                    <h1 class="fw-800 mb-0" style="font-size: 2.1rem; letter-spacing: -0.5px;" data-lang="dash_title">MMS MASTER HUB</h1>
                    <p class="opacity-75 mb-0 fw-light" style="font-size: 0.92rem;" data-lang="dash_subtitle">Warehouse Management & Logistics Command Center</p>
                    <?php else: ?>
                    <h1 class="fw-800 mb-0" style="font-size: 2.1rem; letter-spacing: -0.5px;" data-lang="dash_dealer_title">PSS DELIVERIES COMMAND</h1>
                    <p class="opacity-75 mb-0 fw-light" style="font-size: 0.92rem;" data-lang="dash_dealer_subtitle">School Milk Deliveries & Logistics Control Center</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                <div class="d-inline-block bg-white bg-opacity-10 p-2 rounded-3 text-white">
                    <i class="bi bi-calendar3 me-2 text-info"></i>
                    <span class="fw-bold"><?= date('l, d M Y') ?></span>
                </div>
            </div>
        </div>
        
        <?php if (!$is_staff): ?>
        <!-- Hero progress bar for dealer view -->
        <div class="progress-wrap-hd mt-4">
            <div class="progress-fill-hd" id="hdOverallBar" style="width: 0%;"></div>
            <div id="hdOverallText" style="position:absolute; width:100%; text-align:center; top:50%; transform:translateY(-50%); font-weight:800; font-size:0.9rem; color:white; z-index:2; text-shadow:0 1px 3px rgba(0,0,0,0.35);">Memuatkan data PSS...</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    <?php if ($is_jomcha): ?>
    <!-- ==================== JOMCHA OUTLET DASHBOARD ==================== -->

    <!-- Statistic Cards Row for Jomcha -->
    <div class="row g-4 mb-4">
        <!-- Pending Requests -->
        <div class="col-md-6">
            <div class="card stat-card p-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border-left: 5px solid #d97706 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label text-warning-emphasis fw-bold text-uppercase" style="letter-spacing:0.5px; font-size:0.75rem;" data-lang="jomcha_dash_stat_pending_lbl">Permohonan Pending</div>
                        <div class="stat-value text-warning-emphasis mt-2 fw-extrabold" style="font-size:2rem;"><?= $total_pending_req ?></div>
                        <div class="small text-muted mt-1" style="font-size:0.78rem;" data-lang="jomcha_dash_stat_pending_sub">Menunggu kelulusan gudang</div>
                    </div>
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning-emphasis rounded-3 p-3 mb-0">
                        <i class="bi bi-clock-history fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approved This Month -->
        <div class="col-md-6">
            <div class="card stat-card p-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #f0fdfa 0%, #ccfbf1 100%); border-left: 5px solid #0d9488 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-label text-teal-emphasis fw-bold text-uppercase" style="letter-spacing:0.5px; font-size:0.75rem;" data-lang="jomcha_dash_stat_approved_lbl">Diluluskan Bulan Ini</div>
                        <div class="stat-value text-teal-emphasis mt-2 fw-extrabold" style="font-size:2rem;"><?= $total_approved_req ?></div>
                        <div class="small text-muted mt-1" style="font-size:0.78rem;" data-lang="jomcha_dash_stat_approved_sub">Karton diterima dari gudang</div>
                    </div>
                    <div class="stat-icon bg-teal bg-opacity-10 text-teal-emphasis rounded-3 p-3 mb-0">
                        <i class="bi bi-check-circle fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Requisition Quick Card -->
        <div class="col-md-4">
            <div class="card stat-card border-0 shadow-sm rounded-4 p-4 text-center" style="background: linear-gradient(135deg, #f5f3ff 0%, #edd8fc 100%); border: 1px solid #ddd6fe !important; height: 100%; display: flex; flex-direction: column; justify-content: space-between; transition: all 0.3s ease;">
                <div>
                    <div class="my-4">
                        <i class="bi bi-cart-plus-fill text-primary-emphasis" style="font-size: 4.5rem; filter: drop-shadow(0 4px 6px rgba(124, 58, 237, 0.15));"></i>
                    </div>
                    <h4 class="fw-extrabold" style="color: #3b0764; letter-spacing: -0.5px;" data-lang="jomcha_dash_card_title">Mohon Stok Gudang</h4>
                    <p class="text-muted px-3" style="font-size:0.88rem;" data-lang="jomcha_dash_card_desc">Hantar permohonan bekalan karton baharu dari Warehouse utama terus ke outlet Jomcha anda.</p>
                </div>
                <a href="jomcha_request_stock.php" class="btn btn-primary btn-lg w-100 py-3 rounded-pill fw-bold shadow-sm mt-3" style="background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%); border: none; font-size:1.05rem;">
                    <i class="bi bi-plus-lg me-2"></i> <span data-lang="jomcha_dash_card_btn">Buka Borang Permohonan</span>
                </a>
            </div>
        </div>

        <!-- Stock Take Quick Card -->
        <div class="col-md-4">
            <div class="card stat-card border-0 shadow-sm rounded-4 p-4 text-center" style="background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%); border: 1px solid #e9d5ff !important; height: 100%; display: flex; flex-direction: column; justify-content: space-between; transition: all 0.3s ease;">
                <div>
                    <div class="my-4">
                        <i class="bi bi-clipboard-check text-purple" style="font-size: 4.5rem; color: #a855f7; filter: drop-shadow(0 4px 6px rgba(168, 85, 247, 0.15));"></i>
                    </div>
                    <h4 class="fw-extrabold" style="color: #3b0764; letter-spacing: -0.5px;" data-lang="jomcha_dash_st_title">Kiraan Stok Jomcha</h4>
                    <p class="text-muted px-3" style="font-size:0.88rem;" data-lang="jomcha_dash_st_desc">Kira fizikal stok kaunter & simpanan Jomcha. Auto-billing bagi perbezaan stok yang dikesan berkurang.</p>
                </div>
                <a href="jomcha_stock_take.php" class="btn btn-primary btn-lg w-100 py-3 rounded-pill fw-bold shadow-sm mt-3" style="background: linear-gradient(135deg, #a855f7 0%, #7e22ce 100%); border: none; font-size:1.05rem;">
                    <i class="bi bi-calculator me-2"></i> <span data-lang="jomcha_dash_st_btn">Mula Kira Stok</span>
                </a>
            </div>
        </div>

        <!-- Stock Monitor Quick Card -->
        <div class="col-md-4">
            <div class="card stat-card border-0 shadow-sm rounded-4 p-4 text-center" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1px solid #bbf7d0 !important; height: 100%; display: flex; flex-direction: column; justify-content: space-between; transition: all 0.3s ease;">
                <div>
                    <div class="my-4">
                        <i class="bi bi-display text-success" style="font-size: 4.5rem; color: #15803d; filter: drop-shadow(0 4px 6px rgba(22, 163, 74, 0.15));"></i>
                    </div>
                    <h4 class="fw-extrabold" style="color: #14532d; letter-spacing: -0.5px;" data-lang="jomcha_dash_mon_title">Pantau Stok Jomcha</h4>
                    <p class="text-muted px-3" style="font-size:0.88rem;" data-lang="jomcha_dash_mon_desc">Pantau baki kuantiti produk mengikut sub-lokasi (JC Barn, Kedai Jomcha, Store Area) secara langsung.</p>
                </div>
                <a href="jomcha_monitor_stock.php" class="btn btn-success btn-lg w-100 py-3 rounded-pill fw-bold shadow-sm mt-3" style="background: linear-gradient(135deg, #22c55e 0%, #15803d 100%); border: none; font-size:1.05rem;">
                    <i class="bi bi-display me-2"></i> <span data-lang="jomcha_dash_mon_btn">Buka Pemantauan Stok</span>
                </a>
            </div>
        </div>

        <!-- Sejarah Permohonan Ringkas -->
        <div class="col-12 mt-4">
            <div class="card stat-card border-0 shadow-sm rounded-3 p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0 text-navy"><i class="bi bi-list-stars text-warning me-2"></i><span data-lang="jomcha_dash_recent_title">Status Permohonan Terkini</span></h5>
                    <a href="jomcha_request_stock.php" class="btn btn-sm btn-outline-primary rounded-pill px-3" data-lang="jomcha_dash_view_all">Lihat Semua</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle table-borderless mb-0" style="font-size: 0.88rem;">
                        <thead class="table-light">
                            <tr class="small text-muted fw-bold">
                                <th data-lang="jomcha_id">ID</th>
                                <th data-lang="jomcha_req_date">Tarikh Mohon</th>
                                <th class="text-center" data-lang="jomcha_total_qty">Jumlah Kuantiti</th>
                                <th class="text-center" data-lang="jomcha_status">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_jomcha_requests)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted" data-lang="jomcha_empty_hist">Tiada permohonan direkodkan.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent_jomcha_requests as $req): 
                                    $status_badge = 'bg-secondary';
                                    if ($req['status'] === 'Pending') $status_badge = 'bg-warning text-dark';
                                    if ($req['status'] === 'Approved') $status_badge = 'bg-success text-white';
                                    if ($req['status'] === 'Rejected') $status_badge = 'bg-danger text-white';
                                ?>
                                    <tr class="border-bottom">
                                        <td class="fw-bold">#<?= $req['id'] ?></td>
                                        <td><?= date('d/m/Y', strtotime($req['request_date'])) ?></td>
                                        <td class="text-center fw-bold text-primary"><?= $req['total_qty'] ?> ctn</td>
                                        <td class="text-center">
                                            <span class="badge <?= $status_badge ?> px-2.5 py-1 rounded-pill small">
                                                <?= $req['status'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($is_staff): ?>
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card stat-card p-4">
                <div class="stat-icon bg-primary-subtle text-primary">
                    <i class="bi bi-box-seam-fill"></i>
                </div>
                <div>
                    <div class="stat-label" data-lang="dash_active_skus">Active SKUs</div>
                    <div class="stat-value text-dark"><?= $total_products ?></div>
                    <div class="progress mt-3" style="height: 6px;">
                        <div class="progress-bar bg-primary" style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card stat-card p-4 border-bottom border-success border-5">
                <div class="stat-icon bg-success-subtle text-success">
                    <i class="bi bi-stack"></i>
                </div>
                <div>
                    <div class="stat-label" data-lang="dash_total_stock">Total Stock (Units)</div>
                    <div class="stat-value text-dark"><?= number_format($total_stock) ?></div>
                    <div class="text-success small fw-bold mt-2" data-lang="dash_live_inv">
                        <i class="bi bi-arrow-up-short"></i> Live Inventory
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card stat-card p-4 border-bottom border-danger border-5">
                <div class="stat-icon bg-danger-subtle text-danger">
                    <i class="bi bi-exclamation-octagon-fill"></i>
                </div>
                <div>
                    <div class="stat-label" data-lang="dash_pending_spoil">Pending Spoilage</div>
                    <div class="stat-value text-danger"><?= $pending_spoilage ?></div>
                    <?php if($pending_spoilage > 0): ?>
                        <div class="badge bg-danger-subtle text-danger rounded-pill mt-2" data-lang="dash_action_req">Action Required</div>
                    <?php else: ?>
                        <div class="badge bg-success-subtle text-success rounded-pill mt-2" data-lang="dash_sys_healthy">System Healthy</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Expiry Risk & Low Stock Alert Dashboard Section -->
    <?php if ($is_staff && !$is_jomcha && (!empty($expiring_batches) || !empty($low_stock_products))): ?>
    <?php 
    $has_expiry = !empty($expiring_batches);
    $col_class = $has_expiry ? 'col-lg-6' : 'col-lg-12';
    ?>
    <div class="row mb-5">
        
        <?php if ($has_expiry): ?>
        <!-- KIRI: EXPIRY RISK MONITOR -->
        <div class="<?= $col_class ?> mb-4">
            <div class="card shadow-sm border-0 border-start border-warning border-5 h-100" style="border-radius: 16px; overflow: hidden;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="fw-800 text-warning mb-0"><i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i><span data-lang="dash_expiry_title">EXPIRY RISK MONITOR (FIFO CONTROL)</span></h6>
                    <span class="badge bg-warning text-dark fw-bold px-3 py-2 rounded-pill"><?= count($expiring_batches) ?> Batch</span>
                </div>
                <div class="card-body p-0" style="max-height: 380px; overflow-y: auto;">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.82rem;">
                            <thead class="table-light">
                                <tr class="text-secondary small fw-bold">
                                    <th class="ps-3" data-lang="dash_product">Produk</th>
                                    <th data-lang="dash_batch_no">Batch No</th>
                                    <th class="text-center" data-lang="dash_balance">Baki</th>
                                    <th class="text-center" data-lang="dash_days_left">Hari Lagi</th>
                                    <th class="text-center pe-3" data-lang="dash_risk">Risiko</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($expiring_batches as $eb): 
                                    $is_critical = ($eb['days_left'] <= 30);
                                    $badge_color = $is_critical ? 'danger' : 'warning';
                                    $risk_text = $is_critical ? 'CRIT' : 'WARN';
                                ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-dark text-truncate" style="max-width: 140px;"><?= htmlspecialchars($eb['product_name']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($eb['batch_no'] ?: 'Tiada') ?></span></td>
                                    <td class="text-center fw-bold"><?= number_format($eb['qty_on_hand']) ?> <small class="text-muted">ctn</small></td>
                                    <td class="text-center fw-bold text-<?= $badge_color ?>"><?= $eb['days_left'] ?> H</td>
                                    <td class="text-center pe-3">
                                        <span class="badge bg-<?= $badge_color ?>-subtle text-<?= $badge_color ?> rounded-pill px-2 py-1 font-monospace" style="font-size: 0.65rem; font-weight: 800;">
                                            <?= $risk_text ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- KANAN: LOW STOCK ALERTS -->
        <div class="<?= $col_class ?> mb-4">
            <div class="card shadow-sm border-0 border-start border-danger border-5 h-100" style="border-radius: 16px; overflow: hidden;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="fw-800 text-danger mb-0"><i class="bi bi-cart-x-fill me-2 fs-5"></i><span data-lang="dash_low_stock_title">LOW STOCK ALERTS (< 50 CTN)</span></h6>
                    <span class="badge bg-danger text-white fw-bold px-3 py-2 rounded-pill"><?= count($low_stock_products) ?> Item</span>
                </div>
                <div class="card-body p-0" style="max-height: 380px; overflow-y: auto;">
                    <?php if (empty($low_stock_products)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-check-circle-fill text-success fs-1 mb-2"></i>
                            <p class="mb-0 fw-bold" data-lang="dash_stock_ok">Semua baki stok berada di tahap selamat.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.82rem;">
                                <thead class="table-light">
                                    <tr class="text-secondary small fw-bold">
                                        <th class="ps-3" data-lang="dash_product">Nama Produk</th>
                                        <th data-lang="dash_category">Kategori</th>
                                        <th class="text-end pe-4" data-lang="dash_balance">Jumlah Baki</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($low_stock_products as $lp): 
                                        $qty = (int)$lp['total_qty'];
                                        $color = ($qty == 0) ? 'danger' : 'warning';
                                        $badge_class = ($qty == 0) ? 'bg-danger text-white' : 'bg-warning-subtle text-warning-emphasis';
                                    ?>
                                    <tr>
                                        <td class="ps-3 fw-bold text-dark text-truncate" style="max-width: 220px;"><?= htmlspecialchars($lp['name']) ?></td>
                                         <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fw-bold text-uppercase" style="font-size: 0.72rem;"><?= htmlspecialchars($lp['category']) ?></span></td>
                                        <td class="text-end pe-4 fw-bold text-<?= $color ?>">
                                            <span class="badge <?= $badge_class ?> rounded-pill px-2.5 py-1">
                                                <?= number_format($qty) ?> ctn
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
    <?php endif; ?>

    <!-- Jomcha Requisition Alert Section -->
    <?php if ($is_staff && !$is_jomcha && !empty($pending_jomcha_requests)): ?>
    <div class="row mb-5 animate-fade-in">
        <div class="col-12">
            <div class="card shadow-sm border-0 border-start border-purple border-5 animate-fade-in" style="border-radius: 16px; overflow: hidden; --mms-purple: #8b5cf6;">
                <style>
                    .border-purple { border-left-color: var(--mms-purple) !important; }
                    .text-purple { color: var(--mms-purple) !important; }
                    .bg-purple-subtle { background-color: #f5f3ff !important; }
                </style>
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="fw-800 text-purple mb-0">
                        <i class="bi bi-cart-fill me-2 fs-5"></i>
                        <span data-lang="dash_jomcha_req_title">PENDING JOMCHA REQUISITIONS</span>
                    </h6>
                    <span class="badge bg-purple text-white fw-bold px-3 py-2 rounded-pill" style="background-color: var(--mms-purple) !important;">
                        <?= count($pending_jomcha_requests) ?> Pending
                    </span>
                </div>
                <div class="card-body p-0" style="max-height: 380px; overflow-y: auto;">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.82rem;">
                            <thead class="table-light">
                                <tr class="text-secondary small fw-bold">
                                    <th class="ps-3" data-lang="jomcha_id">ID</th>
                                    <th data-lang="jomcha_req_date">Tarikh Mohon</th>
                                    <th data-lang="jomcha_req_by">Dipohon Oleh</th>
                                    <th class="text-center" data-lang="jomcha_item_count">Jumlah Item</th>
                                    <th class="text-center" data-lang="jomcha_total_qty">Jumlah Kuantiti</th>
                                    <th class="text-center" data-lang="jomcha_status">Status</th>
                                    <th class="text-end pe-3" data-lang="jomcha_action">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($pending_jomcha_requests as $req): ?>
                                <tr>
                                    <td class="ps-3 fw-bold">#<?= $req['id'] ?></td>
                                    <td><?= date('d/m/Y', strtotime($req['request_date'])) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($req['requested_by']) ?></span></td>
                                    <td class="text-center fw-semibold"><?= $req['item_count'] ?> <span data-lang="lbl_product_unit">product</span></td>
                                    <td class="text-center fw-bold text-primary"><?= number_format($req['total_qty']) ?> ctn</td>
                                    <td class="text-center">
                                        <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold">
                                            <?= $req['status'] ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <a href="jomcha_request_stock.php" class="btn btn-sm text-white px-3 py-1 fw-bold rounded-pill" style="background-color: var(--mms-purple);" data-lang="dash_jomcha_req_btn">
                                            Semak Permohonan
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4 justify-content-center">
        <?php if ($is_staff && !$is_jomcha): ?>
        <!-- SEKSYEN 1: GUDANG & INVENTORI -->
        <div class="col-xl-3 col-md-6">
            <div class="section-title" data-lang="sec_stock_receiving">Gudang & Inventori</div>
            
            <a href="receiving_multi.php" class="nav-card">
                <div class="icon-box bg-primary text-white"><i class="bi bi-boxes"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_multi_receive">Multi-Item Receive</span>
                    <span class="desc" data-lang="card_multi_receive_d">Terima stok secara pukal & tetapan pallet.</span>
                </div>
            </a>
            <a href="receiving.php" class="nav-card">
                <div class="icon-box bg-primary-subtle text-primary"><i class="bi bi-box-arrow-in-down"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_single_receive">Single Item Receive</span>
                    <span class="desc" data-lang="card_single_receive_d">Terima produk tunggal dengan kod bar.</span>
                </div>
            </a>
            <a href="stock_transfer.php" class="nav-card">
                <div class="icon-box bg-info text-white"><i class="bi bi-arrow-left-right"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_stock_transfer">Stock Transfer</span>
                    <span class="desc" data-lang="card_stock_transfer_d">Pindahkan stok antara lokasi gudang.</span>
                </div>
            </a>
            <a href="stock_take.php" class="nav-card">
                <div class="icon-box bg-secondary text-white"><i class="bi bi-clipboard-check"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_stock_take">Stock Take / Opname</span>
                    <span class="desc" data-lang="card_stock_take_d">Audit fizikal stok & penyelarasan inventori.</span>
                </div>
            </a>
        </div>

        <!-- SEKSYEN 2: LOGISTIK & OUTBOUND -->
        <div class="col-xl-3 col-md-6">
            <div class="section-title" data-lang="sec_operations">Logistik & Outbound</div>
            
            <a href="commercial_outbound.php" class="nav-card border-start border-primary border-4">
                <div class="icon-box bg-info text-white"><i class="bi bi-truck-flatbed"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_comm_out">Commercial Outbound</span>
                    <span class="desc" data-lang="card_comm_out_d">Stok keluar untuk jualan komersial.</span>
                </div>
            </a>
            <a href="import_co_ui.php" class="nav-card">
                <div class="icon-box bg-warning text-dark"><i class="bi bi-file-earmark-excel"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_co_import">Monthly CO Import</span>
                    <span class="desc" data-lang="card_co_import_d">Import data CO & jana fail SAP sekolah.</span>
                </div>
            </a>
            <a href="view_batch.php" class="nav-card">
                <div class="icon-box bg-secondary text-white"><i class="bi bi-archive"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_batch_arch">Batch Archives</span>
                    <span class="desc" data-lang="card_batch_arch_d">Rekod arkib laporan SAP yang dijana.</span>
                </div>
            </a>
            
            <a href="outbound_history.php" class="nav-card">
                <div class="icon-box bg-dark text-white"><i class="bi bi-clock-history"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_out_hist">Outbound History</span>
                    <span class="desc" data-lang="card_out_hist_d">Sejarah penghantaran komersial & sekolah.</span>
                </div>
            </a>
        </div>

        <!-- SEKSYEN 3: PENGURUSAN PSS -->
        <div class="col-xl-3 col-md-6">
            <div class="section-title" data-lang="sec_system_pss">Pengurusan PSS</div>
            
            <a href="master_hubpss.php" class="nav-card border-start border-info border-4" style="background: #eefbff;">
                <div class="icon-box bg-info text-white"><i class="bi bi-cpu-fill"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_pss_hub">PSS Master Hub</span>
                    <span class="desc" data-lang="card_pss_hub_d">Pusat kawalan sekolah, trip & import data.</span>
                </div>
            </a>
            
            <a href="pss_delivery.php" class="nav-card">
                <div class="icon-box bg-success text-white"><i class="bi bi-mortarboard-fill"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_pss_delivery">School Delivery (PSS)</span>
                    <span class="desc" data-lang="card_pss_delivery_d">Proses dokumen DO bagi projek Susu Sekolah.</span>
                </div>
            </a>

            <a href="import_master.php" class="nav-card">
                <div class="icon-box bg-success-subtle text-success"><i class="bi bi-file-earmark-spreadsheet"></i></div>
                <div class="content">
                    <span class="title" data-lang="import_master_title">Import Master PSS</span>
                    <span class="desc" data-lang="import_master_desc">Kemas kini data sekolah & kontrak master.</span>
                </div>
            </a>
        </div>

        <!-- SEKSYEN 4: AUDIT, PENTADBIR & LOG -->
        <div class="col-xl-3 col-md-6">
            <div class="section-title" data-lang="sec_reports_audit">Audit, Pentadbir & Log</div>
            
            <a href="reconcile.php" class="nav-card">
                <div class="icon-box bg-warning-subtle text-warning-emphasis"><i class="bi bi-shield-shaded"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_reconcile">Daily Reconcile</span>
                    <span class="desc" data-lang="card_reconcile_d">Audit bil fizikal lori vs rekod stok keluar.</span>
                </div>
            </a>
            
            <a href="daily_closing_report.php" class="nav-card border-start border-warning border-3">
                <div class="icon-box bg-warning text-dark"><i class="bi bi-calendar2-check-fill"></i></div>
                <div class="content">
                    <span class="title">Daily Closing Audit</span>
                    <span class="desc">Borang pengesahan baki stok fizikal harian (closing).</span>
                </div>
            </a>

            <a href="spoilage_record.php" class="nav-card">
                <div class="icon-box bg-danger text-white"><i class="bi bi-patch-exclamation"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_spoilage_rep">Report Spoilage</span>
                    <span class="desc" data-lang="card_spoilage_rep_d">Rekod stok rosak/tamat tempoh dengan foto.</span>
                </div>
            </a>
            <a href="spoilage_report.php" class="nav-card">
                <div class="icon-box bg-danger-subtle text-danger"><i class="bi bi-list-ul"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_spoilage_list">Spoilage Logs</span>
                    <span class="desc" data-lang="card_spoilage_list_d">Senarai tuntutan kerosakan & status tuntutan.</span>
                </div>
            </a>
            <a href="reports.php" class="nav-card">
                <div class="icon-box bg-primary text-white"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_wh_monitor">Warehouse Monitor</span>
                    <span class="desc" data-lang="card_wh_monitor_d">Carta alir gudang & status bebanan pallet.</span>
                </div>
            </a>
            <a href="product_management.php" class="nav-card">
                <div class="icon-box bg-primary-subtle text-primary"><i class="bi bi-database-fill-gear"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_master_data">Master Data SKUs</span>
                    <span class="desc" data-lang="card_master_data_d">Uruskan senarai produk & spesifikasi UOM.</span>
                </div>
            </a>
            
            <?php if ($is_admin): ?>
            <a href="user_management.php" class="nav-card border-start border-success border-3">
                <div class="icon-box bg-success text-white"><i class="bi bi-people-fill"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_user_mgmt">User Management</span>
                    <span class="desc" data-lang="card_user_mgmt_d">Uruskan akaun kakitangan & peranan.</span>
                </div>
            </a>
            <a href="system_logs.php" class="nav-card border-start border-danger border-3">
                <div class="icon-box bg-danger text-white"><i class="bi bi-shield-fill-check"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_sys_logs">System Audit Trail</span>
                    <span class="desc" data-lang="card_sys_logs_d">Jejak sejarah aktiviti pengguna sistem.</span>
                </div>
            </a>
            <?php endif; ?>
        </div>

        <!-- SEKSYEN 5: PENGURUSAN JOMCHA -->
        <div class="col-xl-3 col-md-6">
            <div class="section-title text-purple" style="border-left-color: #a855f7;"><i class="bi bi-shop me-1 text-purple"></i><span data-lang="sec_jomcha_mgmt">Pengurusan Jomcha</span></div>
            
            <a href="jomcha_outbound.php" class="nav-card border-start border-purple border-4" style="background: #faf5ff; border-left-color: #a855f7 !important;">
                <div class="icon-box text-white" style="background: linear-gradient(135deg, #a855f7 0%, #7e22ce 100%);"><i class="bi bi-truck-flatbed"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_jomcha_out">Jomcha Outbound</span>
                    <span class="desc" data-lang="card_jomcha_out_d">Keluarkan stok dari gudang utama ke outlet kedai Jomcha.</span>
                </div>
            </a>
            
            <a href="jomcha_request_stock.php" class="nav-card">
                <div class="icon-box text-white" style="background: #a855f7;"><i class="bi bi-cart-plus-fill"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_jomcha_req">Permohonan Stok Jomcha</span>
                    <span class="desc" data-lang="card_jomcha_req_d">Semak dan luluskan permohonan bekalan stok dari outlet Jomcha.</span>
                </div>
            </a>
        </div>
        <?php elseif (!$is_staff && !$is_jomcha): ?>
        <!-- DEALER / HD VIEW: PSS Dashboard with Live Analytics -->

        <!-- Mini Stats Row -->
        <div class="col-md-4">
            <div class="card stat-card p-4 border-bottom border-info border-5">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="stat-label">Jumlah Sekolah</div>
                    <div class="stat-icon bg-info-subtle text-info m-0" style="width: 40px; height: 40px; font-size: 1.2rem; border-radius: 10px;">
                        <i class="bi bi-building"></i>
                    </div>
                </div>
                <div class="stat-value text-dark" id="hdStatSchools">—</div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card stat-card p-4 border-bottom border-success border-5">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="stat-label">Selesai Dihantar</div>
                    <div class="stat-icon bg-success-subtle text-success m-0" style="width: 40px; height: 40px; font-size: 1.2rem; border-radius: 10px;">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
                <div class="stat-value text-success" id="hdStatDelivered">—</div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card stat-card p-4 border-bottom border-warning border-5">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="stat-label">Jumlah Karton</div>
                    <div class="stat-icon bg-warning-subtle text-warning m-0" style="width: 40px; height: 40px; font-size: 1.2rem; border-radius: 10px;">
                        <i class="bi bi-box-seam"></i>
                    </div>
                </div>
                <div class="stat-value text-warning" id="hdStatCartons">—</div>
            </div>
        </div>

        <!-- Analytics Section with CO Filter -->
        <div class="col-12 mt-3">
            <div style="border:1px solid #e2e8f0; border-radius:18px; overflow:hidden; background:#fff; box-shadow:0 4px 16px rgba(0,0,0,0.06);">
                <div style="background:linear-gradient(135deg,#0b2147,#1e3a5f); padding:16px 24px; display:flex; align-items:center; gap:12px; justify-content:space-between; flex-wrap:wrap;">
                    <h5 class="fw-bold mb-0 text-white" style="font-size:0.95rem;">
                        <i class="bi bi-bar-chart-line-fill me-2" style="color:#34d399;"></i>Analytics — Kemajuan Penghantaran Sekolah PSS
                    </h5>
                    <select id="hdCoFilter" class="form-select form-select-sm" style="width:auto;border-radius:10px;font-weight:600;font-size:0.82rem;border:1.5px solid rgba(255,255,255,0.2);background:rgba(255,255,255,0.1);color:white;" onchange="loadHDAnalytics()">
                        <option value="" style="color:#0f172a;">— Semua Kitaran —</option>
                    </select>
                </div>
                <div style="overflow-x:auto; max-height:400px; overflow-y:auto;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead style="position:sticky;top:0;z-index:1;">
                            <tr>
                                <th style="background:#f8fafc;color:#64748b;font-size:0.7rem;font-weight:800;text-transform:uppercase;letter-spacing:0.8px;padding:12px 18px;border-bottom:2px solid #e2e8f0;width:130px;">Dealer</th>
                                <th style="background:#f8fafc;color:#64748b;font-size:0.7rem;font-weight:800;text-transform:uppercase;letter-spacing:0.8px;padding:12px 18px;border-bottom:2px solid #e2e8f0;">Sekolah</th>
                                <th style="background:#f8fafc;color:#64748b;font-size:0.7rem;font-weight:800;text-transform:uppercase;letter-spacing:0.8px;padding:12px 18px;border-bottom:2px solid #e2e8f0;width:140px;">Progress (%)</th>
                                <th style="background:#f8fafc;color:#64748b;font-size:0.7rem;font-weight:800;text-transform:uppercase;letter-spacing:0.8px;padding:12px 18px;border-bottom:2px solid #e2e8f0;">Baki Muatan</th>
                            </tr>
                        </thead>
                        <tbody id="hdDealerSummaryBody">
                            <tr><td colspan="4" style="text-align:center;padding:30px;color:#94a3b8;font-size:0.88rem;"><i class="bi bi-hourglass-split me-2"></i>Memuatkan...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="col-md-4">
            <div class="card p-4 mt-2 border-0 shadow-sm" style="border-left:5px solid #3b82f6 !important; border-radius:16px;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;border-radius:12px;background:#eff6ff;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#3b82f6;"><i class="bi bi-truck-flatbed"></i></div>
                    <div><div class="stat-label text-primary" style="font-size:0.68rem;">Perlu Dihantar (Total)</div><div id="hdSumPerlu" style="font-size:1rem;font-weight:800;color:#1d4ed8;">—</div></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-4 mt-2 border-0 shadow-sm" style="border-left:5px solid #10b981 !important; border-radius:16px;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;border-radius:12px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#10b981;"><i class="bi bi-check-circle-fill"></i></div>
                    <div><div class="stat-label text-success" style="font-size:0.68rem;">Siap Dihantar</div><div id="hdSumSiap" style="font-size:1rem;font-weight:800;color:#059669;">—</div></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-4 mt-2 border-0 shadow-sm" style="border-left:5px solid #ef4444 !important; border-radius:16px;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;border-radius:12px;background:#fef2f2;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#ef4444;"><i class="bi bi-clock-history"></i></div>
                    <div><div class="stat-label text-danger" style="font-size:0.68rem;">Baki Belum Hantar</div><div id="hdSumBaki" style="font-size:1rem;font-weight:800;color:#dc2626;">—</div></div>
                </div>
            </div>
        </div>

        <!-- Navigation Cards -->
        <div class="col-12 mt-4">
            <div class="section-title" data-lang="sec_pss_shortcuts">Navigasi PSS</div>
        </div>

        <div class="col-lg-6 col-md-6 col-sm-12">
            <a href="master_hubpss.php" class="nav-card border-start border-info border-4 h-100" style="background: #f0f9ff; transition: all 0.25s ease;">
                <div class="icon-box bg-info text-white"><i class="bi bi-cpu-fill fs-4"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_pss_hub" style="font-weight:750; font-size:1.05rem; color:#0369a1;">PSS Master Hub</span>
                    <span class="desc" data-lang="card_pss_hub_d">Pusat kawalan sekolah, trip &amp; import data.</span>
                </div>
            </a>
        </div>

        <div class="col-lg-6 col-md-6 col-sm-12">
            <a href="pss_delivery.php" class="nav-card border-start border-success border-4 h-100" style="background: #f0fdf4; transition: all 0.25s ease;">
                <div class="icon-box bg-success text-white"><i class="bi bi-mortarboard-fill fs-4"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_pss_delivery" style="font-weight:750; font-size:1.05rem; color:#15803d;">School Delivery (PSS)</span>
                    <span class="desc" data-lang="card_pss_delivery_d">Proses dokumen DO bagi projek Susu Sekolah.</span>
                </div>
            </a>
        </div>

        <script>
        (function() {
            const PALLET_SIZE = 144;
            const currentDealer = '<?= $username ?>';
            const currentRole   = '<?= $role ?>';

            function calcFullMuatan(ctnTotal, pcsExtra) {
                let totalCtn = Number(ctnTotal) || 0;
                let extraPcs = Number(pcsExtra) || 0;
                if (extraPcs >= 24) { totalCtn += Math.floor(extraPcs / 24); extraPcs = extraPcs % 24; }
                const pallet = Math.floor(totalCtn / PALLET_SIZE);
                const bakiCtn = totalCtn % PALLET_SIZE;
                let r = [];
                if (pallet > 0) r.push(pallet + ' pallet');
                if (bakiCtn > 0 || (pallet === 0 && extraPcs === 0)) r.push(bakiCtn + ' ctn');
                if (extraPcs > 0) r.push(extraPcs + ' pcs');
                return r.join(' + ');
            }

            let allData = [];

            async function loadHDAnalytics() {
                try {
                    // Only fetch data once; re-use for filtering
                    if (allData.length === 0) {
                        const res = await fetch('api_pss.php?action=get_schools&dealer=' + currentDealer + '&role=' + currentRole + '&t=' + Date.now());
                        const raw = await res.json();
                        allData = Array.isArray(raw) ? raw.map(s => ({...s, isDelivered: s.isDelivered == 1})) : [];

                        // Populate CO filter dropdown
                        const coSel = document.getElementById('hdCoFilter');
                        const cos = [...new Set(allData.map(s => s.co_no).filter(Boolean))].sort().reverse();
                        cos.forEach(co => {
                            const opt = document.createElement('option');
                            opt.value = co; opt.textContent = 'Kitaran ' + co;
                            opt.style.color = '#0f172a';
                            coSel.appendChild(opt);
                        });
                        // Default to latest CO
                        if (cos.length > 0) { coSel.value = cos[0]; }
                    }

                    const selectedCo = document.getElementById('hdCoFilter')?.value || '';
                    let data = allData;
                    if (selectedCo) data = data.filter(s => s.co_no === selectedCo);

                    // Filter to current dealer if dealer role
                    const myData = currentRole === 'dealer' ? data.filter(s => s.dealer === currentDealer) : data;

                    // Overall stats (for progress bar and top stat cards)
                    let totalCtn = 0, doneCtn = 0, totalSch = myData.length, doneSch = 0;
                    myData.forEach(s => {
                        const c = Number(s.totalCartons) || 0;
                        totalCtn += c;
                        if (s.isDelivered) { doneCtn += c; doneSch++; }
                    });
                    const pct = totalCtn ? Math.round((doneCtn / totalCtn) * 100) : 0;

                    document.getElementById('hdOverallBar').style.width = pct + '%';
                    document.getElementById('hdOverallText').innerText = 'Progres Kitaran: ' + pct + '% (' + doneCtn + '/' + totalCtn + ' Carton)' + (selectedCo ? ' — ' + selectedCo : '');
                    document.getElementById('hdStatSchools').innerText = totalSch;
                    document.getElementById('hdStatDelivered').innerText = doneSch;
                    document.getElementById('hdStatCartons').innerText = totalCtn.toLocaleString();

                    // Per-dealer breakdown (use full filtered data, not myData)
                    const byDealer = {};
                    data.forEach(s => {
                        const d = s.dealer || 'Unknown';
                        if (!byDealer[d]) byDealer[d] = { total:0, done:0, totalCtn:0, doneCtn:0, extraTotal:0, extraDone:0 };
                        byDealer[d].total++;
                        byDealer[d].totalCtn   += Number(s.totalCartons) || 0;
                        byDealer[d].extraTotal  += Number(s.extraPacks) || 0;
                        if (s.isDelivered) {
                            byDealer[d].done++;
                            byDealer[d].doneCtn  += Number(s.totalCartons) || 0;
                            byDealer[d].extraDone += Number(s.extraPacks) || 0;
                        }
                    });

                    let grandTotalCtn=0, grandDoneCtn=0, grandTotalExtra=0, grandDoneExtra=0;
                    let grandTotalSchools=0, grandDoneSchools=0;
                    const tbody = document.getElementById('hdDealerSummaryBody');
                    
                    const rowsHtml = Object.keys(byDealer).sort().map(d => {
                        const r = byDealer[d];
                        const p = r.total > 0 ? Math.round((r.done / r.total) * 100) : 0;
                        const bCtn = r.totalCtn - r.doneCtn;
                        const bEx  = r.extraTotal - r.extraDone;
                        const bStr = calcFullMuatan(bCtn, bEx) || '0 ctn';
                        const complete = p >= 100;
                        const pc = p >= 100 ? '#10b981' : p >= 60 ? '#f59e0b' : '#ef4444';
                        const bgRow = complete ? 'background:#f0fdf4;' : '';
                        
                        grandTotalSchools += r.total;
                        grandDoneSchools  += r.done;
                        grandTotalCtn     += r.totalCtn;
                        grandDoneCtn      += r.doneCtn;
                        grandTotalExtra   += r.extraTotal;
                        grandDoneExtra    += r.extraDone;
                        
                        return '<tr style="' + bgRow + '">' +
                            '<td style="padding:12px 18px;border-bottom:1px solid #f1f5f9;">' +
                                '<span style="display:inline-block;background:#1e3a5f;color:#7dd3fc;font-family:monospace;font-size:0.7rem;font-weight:800;padding:2px 8px;border-radius:6px;">' + d.toUpperCase() + '</span>' +
                            '</td>' +
                            '<td style="padding:12px 18px;border-bottom:1px solid #f1f5f9;">' +
                                '<span style="color:#0f172a;font-weight:700;">' + r.done + '/' + r.total + '</span> <span style="color:#94a3b8;font-size:0.78rem;">sekolah</span>' +
                                '<div style="background:#e2e8f0;border-radius:20px;height:7px;margin-top:6px;overflow:hidden;"><div style="background:linear-gradient(90deg,#10b981,#34d399);height:100%;border-radius:20px;width:' + p + '%;transition:width 0.6s;"></div></div>' +
                            '</td>' +
                            '<td style="padding:12px 18px;border-bottom:1px solid #f1f5f9;">' +
                                '<span style="display:inline-block;background:' + (complete?'#dcfce7':p>=60?'#fef9c3':'#fef2f2') + ';color:' + pc + ';font-weight:800;font-size:0.85rem;padding:3px 12px;border-radius:20px;">' + p + '%</span>' +
                            '</td>' +
                            '<td style="padding:12px 18px;border-bottom:1px solid #f1f5f9;color:' + (complete?'#10b981':'#ef4444') + ';font-weight:700;font-size:0.82rem;">' +
                                (complete ? '<i class="bi bi-check-circle-fill me-1"></i>Lengkap' : '(' + bStr + ')') +
                            '</td>' +
                        '</tr>';
                    }).join('');

                    const grandPercent = grandTotalCtn ? Math.round((grandDoneCtn / grandTotalCtn) * 100) : 0;
                    const grandBakiCtn = grandTotalCtn - grandDoneCtn;
                    const grandBakiEx  = grandTotalExtra - grandDoneExtra;
                    const grandBakiStr = calcFullMuatan(grandBakiCtn, grandBakiEx) || '0 ctn';
                    const grandComplete = grandPercent >= 100;

                    const totalRowHtml = `
                        <tr style="background:#f8fafc; font-weight:bold; border-top:2px solid #cbd5e1; position:sticky; bottom:0; z-index:1;">
                            <td style="padding:14px 18px; border-bottom:none;">
                                <span style="display:inline-block;background:#64748b;color:white;font-size:0.7rem;font-weight:800;padding:3px 10px;border-radius:6px;letter-spacing:0.5px;">TOTAL</span>
                            </td>
                            <td style="padding:14px 18px; border-bottom:none;">
                                <span style="color:#0f172a;font-weight:800;">${grandDoneSchools}/${grandTotalSchools}</span> <span style="color:#64748b;font-size:0.78rem;">sekolah</span>
                                <div style="background:#e2e8f0;border-radius:20px;height:7px;margin-top:6px;overflow:hidden;">
                                    <div style="background:linear-gradient(90deg,#0ea5e9,#2563eb);height:100%;border-radius:20px;width:${grandPercent}%;transition:width 0.6s;"></div>
                                </div>
                            </td>
                            <td style="padding:14px 18px; border-bottom:none;">
                                <span style="display:inline-block;background:${grandComplete?'#dcfce7':'#e0f2fe'};color:${grandComplete?'#10b981':'#2563eb'};font-weight:800;font-size:0.85rem;padding:3px 12px;border-radius:20px;">${grandPercent}%</span>
                            </td>
                            <td style="padding:14px 18px; border-bottom:none; color:${grandComplete?'#10b981':'#ef4444'}; font-weight:800; font-size:0.82rem;">
                                ${grandComplete ? '<i class="bi bi-check-circle-fill me-1"></i>Lengkap' : '(' + grandBakiStr + ')'}
                            </td>
                        </tr>
                    `;

                    tbody.innerHTML = rowsHtml + totalRowHtml;

                    // Summary cards
                    const bTotal = grandTotalCtn - grandDoneCtn;
                    const bEx    = grandTotalExtra - grandDoneExtra;
                    document.getElementById('hdSumPerlu').textContent = calcFullMuatan(grandTotalCtn, grandTotalExtra);
                    document.getElementById('hdSumSiap').textContent  = calcFullMuatan(grandDoneCtn,  grandDoneExtra);
                    document.getElementById('hdSumBaki').textContent  = calcFullMuatan(bTotal, bEx);

                } catch(e) {
                    console.error('Gagal memuatkan analytics PSS:', e);
                }
            }

            loadHDAnalytics();
        })();
        </script>

        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
