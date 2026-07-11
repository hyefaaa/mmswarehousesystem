<?php
// index.php - THE MASTER HUB (PREMIUM EDITION)
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
} catch (Exception $e) {
    $total_products = $total_stock = $pending_spoilage = 0;
    $expiring_batches = [];
    $low_stock_products = [];
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
</style>

<div class="hero-section">
    <div class="container-fluid px-4">
        <div class="row align-items-center">
            <div class="col-md-8 d-flex flex-column flex-md-row align-items-center gap-3 text-center text-md-start">
                <img src="img/logo.png" alt="MMS Logo" style="height: 60px; width: auto; border-radius: 12px; border: 2.5px solid rgba(255,255,255,0.25); box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                <div>
                    <h1 class="fw-800 mb-0" style="font-size: 2.1rem; letter-spacing: -0.5px;" data-lang="dash_title">MMS MASTER HUB</h1>
                    <p class="opacity-75 mb-0 fw-light" style="font-size: 0.92rem;" data-lang="dash_subtitle">Warehouse Management & Logistics Command Center</p>
                </div>
            </div>
            <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
                <div class="d-inline-block bg-white bg-opacity-10 p-2 rounded-3">
                    <i class="bi bi-calendar3 me-2 text-info"></i>
                    <span class="fw-bold"><?= date('l, d M Y') ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    <?php if ($is_staff): ?>
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
    <?php if ($is_staff && (!empty($expiring_batches) || !empty($low_stock_products))): ?>
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
                    <h6 class="fw-800 text-warning mb-0"><i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i><span data-lang="dash_expiry_title">EXPIRY RISK MONITOR (FEFO CONTROL)</span></h6>
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
                                        <td><span class="badge-cat"><?= htmlspecialchars($lp['category']) ?></span></td>
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

    <div class="row g-4 justify-content-center">
        <?php if ($is_staff): ?>
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
        <?php else: ?>
        <!-- DEALER / HD VIEW: 2 Symmetric PSS-focused cards -->
        <div class="col-12 text-center mb-3">
            <div class="section-title text-center justify-content-center d-inline-block" style="border-left:none; border-bottom:4px solid #0ea5e9; padding-left:0; padding-bottom:8px; font-weight:800; font-size:1.1rem; color:#0f172a; text-transform:uppercase; letter-spacing:1px;" data-lang="sec_system_pss">
                Pusat Kawalan Operasi PSS
            </div>
        </div>

        <div class="col-md-5 col-sm-12">
            <a href="master_hubpss.php" class="nav-card border-start border-info border-4 h-100" style="background: #f0f9ff; transition: all 0.25s ease;">
                <div class="icon-box bg-info text-white"><i class="bi bi-cpu-fill fs-4"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_pss_hub" style="font-weight:750; font-size:1.05rem; color:#0369a1;">PSS Master Hub</span>
                    <span class="desc" data-lang="card_pss_hub_d">Pusat kawalan sekolah, trip & import data.</span>
                </div>
            </a>
        </div>

        <div class="col-md-5 col-sm-12">
            <a href="pss_delivery.php" class="nav-card border-start border-success border-4 h-100" style="background: #f0fdf4; transition: all 0.25s ease;">
                <div class="icon-box bg-success text-white"><i class="bi bi-mortarboard-fill fs-4"></i></div>
                <div class="content">
                    <span class="title" data-lang="card_pss_delivery" style="font-weight:750; font-size:1.05rem; color:#15803d;">School Delivery (PSS)</span>
                    <span class="desc" data-lang="card_pss_delivery_d">Proses dokumen DO bagi projek Susu Sekolah.</span>
                </div>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>