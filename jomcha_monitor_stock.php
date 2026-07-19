<?php
// jomcha_monitor_stock.php
require_once 'includes/auth.php';
require_once 'config/db.php';

// Only allow admin or staff_jomcha
$role = $_SESSION['role'] ?? '';
if ($role !== 'admin' && $role !== 'staff_jomcha') {
    header('Location: login.php');
    exit;
}

// Query to get the latest stock take record for each product
$stmt = $pdo->query("
    SELECT st.*, p.name as product_name, p.category, p.pack_size, p.uom
    FROM jomcha_stock_takes st
    JOIN (
        SELECT product_id, MAX(id) as max_id 
        FROM jomcha_stock_takes 
        GROUP BY product_id
    ) latest ON st.id = latest.max_id
    JOIN products p ON st.product_id = p.id
    ORDER BY p.name ASC
");
$latest_takes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Parse data for each of the 3 sections
$jc_barn_products = [];
$jc_barn_total_units = 0;

$mms_products = [];
$mms_total_units = 0;

$sa_products = [];
$sa_total_units = 0;

foreach ($latest_takes as $take) {
    $cs = (int)$take['pack_size'] > 0 ? (int)$take['pack_size'] : 1;
    
    // 1. JC Barn
    $jcb_ch1 = ((int)$take['jcb_chiller_1_ctn'] * $cs) + (int)$take['jcb_chiller_1_pcs'];
    $jcb_ch2 = ((int)$take['jcb_chiller_2_ctn'] * $cs) + (int)$take['jcb_chiller_2_pcs'];
    $jcb_rk = ((int)$take['jcb_rack_ctn'] * $cs) + (int)$take['jcb_rack_pcs'];
    $jcb_sum = $jcb_ch1 + $jcb_ch2 + $jcb_rk;
    
    if ($jcb_sum > 0) {
        $take['ch1_total'] = $jcb_ch1;
        $take['ch2_total'] = $jcb_ch2;
        $take['rk_total'] = $jcb_rk;
        $take['total'] = $jcb_sum;
        $jc_barn_products[] = $take;
        $jc_barn_total_units += $jcb_sum;
    }
    
    // 2. Kedai Jomcha (Moo Moo Station)
    $mms_rk = ((int)$take['mms_rack_ctn'] * $cs) + (int)$take['mms_rack_pcs'];
    $mms_ch1 = ((int)$take['mms_chiller_1_ctn'] * $cs) + (int)$take['mms_chiller_1_pcs'];
    $mms_ch2 = ((int)$take['mms_chiller_2_ctn'] * $cs) + (int)$take['mms_chiller_2_pcs'];
    $mms_meat = ((int)$take['mms_freezer_meat_ctn'] * $cs) + (int)$take['mms_freezer_meat_pcs'];
    $mms_ic = ((int)$take['mms_freezer_ice_cream_ctn'] * $cs) + (int)$take['mms_freezer_ice_cream_pcs'];
    $mms_sum = $mms_rk + $mms_ch1 + $mms_ch2 + $mms_meat + $mms_ic;
    
    if ($mms_sum > 0) {
        $take['rk_total'] = $mms_rk;
        $take['ch1_total'] = $mms_ch1;
        $take['ch2_total'] = $mms_ch2;
        $take['meat_total'] = $mms_meat;
        $take['ic_total'] = $mms_ic;
        $take['total'] = $mms_sum;
        $mms_products[] = $take;
        $mms_total_units += $mms_sum;
    }
    
    // 3. Store Area
    $sa_rk = ((int)$take['sa_rack_ctn'] * $cs) + (int)$take['sa_rack_pcs'];
    $sa_pl1 = ((int)$take['sa_pallet_1_ctn'] * $cs) + (int)$take['sa_pallet_1_pcs'];
    $sa_pl2 = ((int)$take['sa_pallet_2_ctn'] * $cs) + (int)$take['sa_pallet_2_pcs'];
    $sa_ch1 = ((int)$take['sa_chiller_1_ctn'] * $cs) + (int)$take['sa_chiller_1_pcs'];
    $sa_ch2 = ((int)$take['sa_chiller_2_ctn'] * $cs) + (int)$take['sa_chiller_2_pcs'];
    $sa_fz1 = ((int)$take['sa_freezer_1_ctn'] * $cs) + (int)$take['sa_freezer_1_pcs'];
    $sa_fz2 = ((int)$take['sa_freezer_2_ctn'] * $cs) + (int)$take['sa_freezer_2_pcs'];
    $sa_sum = $sa_rk + $sa_pl1 + $sa_pl2 + $sa_ch1 + $sa_ch2 + $sa_fz1 + $sa_fz2;
    
    if ($sa_sum > 0) {
        $take['rk_total'] = $sa_rk;
        $take['pl1_total'] = $sa_pl1;
        $take['pl2_total'] = $sa_pl2;
        $take['ch1_total'] = $sa_ch1;
        $take['ch2_total'] = $sa_ch2;
        $take['fz1_total'] = $sa_fz1;
        $take['fz2_total'] = $sa_fz2;
        $take['total'] = $sa_sum;
        $sa_products[] = $take;
        $sa_total_units += $sa_sum;
    }
}

$page_title = 'Pemantauan Stok Jomcha | MMS';
require_once 'includes/header.php';
?>

<style>
    body {
        background-color: #faf5ff;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
    
    .jomcha-header {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border-bottom: 4px solid #34d399;
    }
    
    .jomcha-card {
        background-color: #ffffff;
        border-radius: 16px;
        border: 1px solid rgba(168, 85, 247, 0.12);
    }
    
    .text-navy {
        color: #1e1b4b;
    }
    
    .nav-pills .nav-link {
        color: #6b21a8;
        border: 1px solid rgba(107, 33, 168, 0.15);
        background-color: #ffffff;
        transition: all 0.2s ease-in-out;
    }
    
    .nav-pills .nav-link.active {
        background: linear-gradient(135deg, #a855f7 0%, #7e22ce 100%) !important;
        color: #ffffff !important;
        border-color: transparent;
        box-shadow: 0 4px 12px rgba(126, 34, 206, 0.2);
    }
</style>

<!-- HEADER HERO -->
<div class="jomcha-header text-white py-4 px-4 mb-4 shadow-sm">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <span class="badge bg-purple-subtle text-white text-uppercase fw-extrabold px-3 py-1.5 rounded-pill mb-2" style="font-size:0.72rem; letter-spacing:1px; background: rgba(255,255,255,0.15);" data-lang="jomcha_mon_badge">Jomcha Monitor Hub</span>
                <h1 class="h2 fw-extrabold m-0" style="letter-spacing: -1px;" data-lang="jomcha_mon_title">
                    🎯 Pemantauan Stok Jomcha
                </h1>
                <p class="text-white-50 m-0 mt-1" style="font-size: 0.95rem;" data-lang="jomcha_mon_subtitle">
                    Pantau baki inventori semasa yang diedarkan merentasi ketiga-tiga zon fizikal outlet.
                </p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-light fw-bold px-4 py-2.5 rounded-pill" style="border-width: 2px;">
                    <i class="bi bi-house me-1"></i> Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    
    <!-- SEARCH & METRIC HIGHLIGHTS -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="input-group shadow-sm" style="border-radius:15px; overflow:hidden;">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="searchMonitor" onkeyup="tapisMonitor()" class="form-control border-start-0 py-3" placeholder="Cari nama produk di mana-mana zon..." data-lang-placeholder="jomcha_mon_search_placeholder">
            </div>
        </div>
        <div class="col-md-6">
            <div class="row g-2">
                <div class="col-4">
                    <div class="p-2 text-center bg-white rounded-3 border shadow-sm">
                        <small class="text-muted d-block small" data-lang="jomcha_mon_jcb_units">Unit JC Barn</small>
                        <strong class="text-purple fs-5"><?= number_format($jc_barn_total_units) ?></strong>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-2 text-center bg-white rounded-3 border shadow-sm">
                        <small class="text-muted d-block small" data-lang="jomcha_mon_mms_units">Unit Kedai Jomcha</small>
                        <strong class="text-purple fs-5"><?= number_format($mms_total_units) ?></strong>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-2 text-center bg-white rounded-3 border shadow-sm">
                        <small class="text-muted d-block small" data-lang="jomcha_mon_sa_units">Unit Store Area</small>
                        <strong class="text-purple fs-5"><?= number_format($sa_total_units) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABS NAVIGATION -->
    <ul class="nav nav-pills mb-4 gap-2 bg-white p-2 rounded-3 shadow-sm border" id="monitorTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold px-3" id="jcb-monitor-tab" data-bs-toggle="pill" data-bs-target="#tab-jcb" type="button" role="tab"><i class="bi bi-shop me-1"></i> <span data-lang="jomcha_mon_tab_jcb">1. JC Barn</span> (<?= count($jc_barn_products) ?>)</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold px-3" id="mms-monitor-tab" data-bs-toggle="pill" data-bs-target="#tab-mms" type="button" role="tab"><i class="bi bi-box2 me-1"></i> <span data-lang="jomcha_mon_tab_mms">2. Kedai Jomcha</span> (<?= count($mms_products) ?>)</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold px-3" id="sa-monitor-tab" data-bs-toggle="pill" data-bs-target="#tab-sa" type="button" role="tab"><i class="bi bi-house-door me-1"></i> <span data-lang="jomcha_mon_tab_sa">3. Store Area</span> (<?= count($sa_products) ?>)</button>
        </li>
    </ul>

    <div class="tab-content" id="monitorTabContent">
        
        <!-- ════════════════════════════════════════ -->
        <!-- TAB: JC BARN                             -->
        <!-- ════════════════════════════════════════ -->
        <div class="tab-pane fade show active" id="tab-jcb" role="tabpanel">
            <div class="jomcha-card p-4 shadow-sm">
                <h5 class="fw-bold text-navy mb-3"><i class="bi bi-shop text-purple me-2"></i><span data-lang="jomcha_mon_sec_jcb">Zon 1: JC Barn (2 Chiller, 1 Rack)</span></h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle table-borderless" style="font-size:0.92rem;">
                        <thead class="table-light">
                            <tr class="text-muted fw-bold small">
                                <th class="ps-3" style="width: 40%;" data-lang="jomcha_mon_col_prod">Nama Produk</th>
                                <th class="text-center" style="width: 15%;" data-lang="jomcha_mon_col_ch1">Chiller 1</th>
                                <th class="text-center" style="width: 15%;" data-lang="jomcha_mon_col_ch2">Chiller 2</th>
                                <th class="text-center" style="width: 15%;" data-lang="jomcha_mon_col_rack">Rak</th>
                                <th class="pe-3 text-end" style="width: 15%;" data-lang="jomcha_mon_col_total">Jumlah Baki</th>
                            </tr>
                        </thead>
                        <tbody class="monitor-tbody">
                            <?php if (empty($jc_barn_products)): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted" data-lang="jomcha_mon_empty_jcb">Tiada stok direkodkan di JC Barn.</td></tr>
                            <?php else: foreach ($jc_barn_products as $stk): 
                                $cs = (int)$stk['pack_size'] > 0 ? (int)$stk['pack_size'] : 1;
                            ?>
                                <tr class="monitor-row-item border-bottom" data-name="<?= strtolower(htmlspecialchars($stk['product_name'])) ?>">
                                    <td class="ps-3">
                                        <span class="badge bg-purple-subtle text-purple mb-1" style="font-size: 0.7rem;"><?= htmlspecialchars($stk['category']) ?></span>
                                        <h6 class="fw-bold mb-0 text-navy"><?= htmlspecialchars($stk['product_name']) ?></h6>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark"><?= floor($stk['jcb_chiller_1_ctn']) ?> ctn</span>
                                        <div class="text-muted small"><?= $stk['jcb_chiller_1_pcs'] ?> pcs</div>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark"><?= floor($stk['jcb_chiller_2_ctn']) ?> ctn</span>
                                        <div class="text-muted small"><?= $stk['jcb_chiller_2_pcs'] ?> pcs</div>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark"><?= floor($stk['jcb_rack_ctn']) ?> ctn</span>
                                        <div class="text-muted small"><?= $stk['jcb_rack_pcs'] ?> pcs</div>
                                    </td>
                                    <td class="pe-3 text-end fw-extrabold text-purple fs-6">
                                        <?= number_format($stk['total']) ?> pcs
                                        <div class="text-muted small fw-semibold" style="font-size: 10.5px;">
                                            (<?= floor($stk['total'] / $cs) ?> ctn + <?= $stk['total'] % $cs ?> pcs)
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════ -->
        <!-- TAB: KEDAI JOMCHA (MOO MOO STATION)      -->
        <!-- ════════════════════════════════════════ -->
        <div class="tab-pane fade" id="tab-mms" role="tabpanel">
            <div class="jomcha-card p-4 shadow-sm">
                <h5 class="fw-bold text-navy mb-3"><i class="bi bi-box2 text-purple me-2"></i><span data-lang="jomcha_mon_sec_mms">Zon 2: Kedai Jomcha (1 Rack, 2 Chiller, 2 Freezer)</span></h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle table-borderless" style="font-size:0.92rem;">
                        <thead class="table-light">
                            <tr class="text-muted fw-bold small">
                                <th class="ps-3" style="width: 30%;" data-lang="jomcha_mon_col_prod">Nama Produk</th>
                                <th class="text-center" data-lang="jomcha_mon_col_rack">Rak</th>
                                <th class="text-center" data-lang="jomcha_mon_col_ch1">Chiller 1</th>
                                <th class="text-center" data-lang="jomcha_mon_col_ch2">Chiller 2</th>
                                <th class="text-center" data-lang="jomcha_mon_col_freezer_meat">Freezer (Daging)</th>
                                <th class="text-center" data-lang="jomcha_mon_col_freezer_ic">Freezer (Aiskrim)</th>
                                <th class="pe-3 text-end" style="width: 15%;" data-lang="jomcha_mon_col_total">Jumlah Baki</th>
                            </tr>
                        </thead>
                        <tbody class="monitor-tbody">
                            <?php if (empty($mms_products)): ?>
                                <tr><td colspan="7" class="text-center py-5 text-muted" data-lang="jomcha_mon_empty_mms">Tiada stok direkodkan di Kedai Jomcha.</td></tr>
                            <?php else: foreach ($mms_products as $stk): 
                                $cs = (int)$stk['pack_size'] > 0 ? (int)$stk['pack_size'] : 1;
                            ?>
                                <tr class="monitor-row-item border-bottom" data-name="<?= strtolower(htmlspecialchars($stk['product_name'])) ?>">
                                    <td class="ps-3">
                                        <span class="badge bg-purple-subtle text-purple mb-1" style="font-size: 0.7rem;"><?= htmlspecialchars($stk['category']) ?></span>
                                        <h6 class="fw-bold mb-0 text-navy"><?= htmlspecialchars($stk['product_name']) ?></h6>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark"><?= floor($stk['mms_rack_ctn']) ?> ctn</span>
                                        <div class="text-muted small"><?= $stk['mms_rack_pcs'] ?> pcs</div>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark"><?= floor($stk['mms_chiller_1_ctn']) ?> ctn</span>
                                        <div class="text-muted small"><?= $stk['mms_chiller_1_pcs'] ?> pcs</div>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark"><?= floor($stk['mms_chiller_2_ctn']) ?> ctn</span>
                                        <div class="text-muted small"><?= $stk['mms_chiller_2_pcs'] ?> pcs</div>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark"><?= floor($stk['mms_freezer_meat_ctn']) ?> ctn</span>
                                        <div class="text-muted small"><?= $stk['mms_freezer_meat_pcs'] ?> pcs</div>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark"><?= floor($stk['mms_freezer_ice_cream_ctn']) ?> ctn</span>
                                        <div class="text-muted small"><?= $stk['mms_freezer_ice_cream_pcs'] ?> pcs</div>
                                    </td>
                                    <td class="pe-3 text-end fw-extrabold text-purple fs-6">
                                        <?= number_format($stk['total']) ?> pcs
                                        <div class="text-muted small fw-semibold" style="font-size: 10.5px;">
                                            (<?= floor($stk['total'] / $cs) ?> ctn + <?= $stk['total'] % $cs ?> pcs)
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════ -->
        <!-- TAB: STORE AREA                          -->
        <!-- ════════════════════════════════════════ -->
        <div class="tab-pane fade" id="tab-sa" role="tabpanel">
            <div class="jomcha-card p-4 shadow-sm">
                <h5 class="fw-bold text-navy mb-3"><i class="bi bi-house-door text-purple me-2"></i><span data-lang="jomcha_mon_sec_sa">Zon 3: Store Area (1 Rack, 2 Pallet, 2 Chiller, 2 Freezer)</span></h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle table-borderless" style="font-size:0.92rem;">
                        <thead class="table-light">
                            <tr class="text-muted fw-bold small">
                                <th class="ps-3" style="width: 25%;" data-lang="jomcha_mon_col_prod">Nama Produk</th>
                                <th class="text-center" data-lang="jomcha_mon_col_rack">Rak</th>
                                <th class="text-center" data-lang="jomcha_mon_col_pallet1">Pallet 1</th>
                                <th class="text-center" data-lang="jomcha_mon_col_pallet2">Pallet 2</th>
                                <th class="text-center" data-lang="jomcha_mon_col_ch1">Chiller 1</th>
                                <th class="text-center" data-lang="jomcha_mon_col_ch2">Chiller 2</th>
                                <th class="text-center" data-lang="jomcha_mon_col_freezer1">Freezer 1</th>
                                <th class="text-center" data-lang="jomcha_mon_col_freezer2">Freezer 2</th>
                                <th class="pe-3 text-end" style="width: 15%;" data-lang="jomcha_mon_col_total">Jumlah Baki</th>
                            </tr>
                        </thead>
                        <tbody class="monitor-tbody">
                            <?php if (empty($sa_products)): ?>
                                <tr><td colspan="9" class="text-center py-5 text-muted" data-lang="jomcha_mon_empty_sa">Tiada stok direkodkan di Store Area.</td></tr>
                            <?php else: foreach ($sa_products as $stk): 
                                $cs = (int)$stk['pack_size'] > 0 ? (int)$stk['pack_size'] : 1;
                            ?>
                                <tr class="monitor-row-item border-bottom" data-name="<?= strtolower(htmlspecialchars($stk['product_name'])) ?>">
                                    <td class="ps-3">
                                        <span class="badge bg-purple-subtle text-purple mb-1" style="font-size: 0.7rem;"><?= htmlspecialchars($stk['category']) ?></span>
                                        <h6 class="fw-bold mb-0 text-navy"><?= htmlspecialchars($stk['product_name']) ?></h6>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark"><?= floor($stk['sa_rack_ctn']) ?> ctn</span>
                                        <div class="text-muted small"><?= $stk['sa_rack_pcs'] ?> pcs</div>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark"><?= floor($stk['sa_pallet_1_ctn']) ?> ctn</span>
                                        <div class="text-muted small"><?= $stk['sa_pallet_1_pcs'] ?> pcs</div>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark"><?= floor($stk['sa_pallet_2_ctn']) ?> ctn</span>
                                        <div class="text-muted small"><?= $stk['sa_pallet_2_pcs'] ?> pcs</div>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark"><?= floor($stk['sa_chiller_1_ctn']) ?> ctn</span>
                                        <div class="text-muted small"><?= $stk['sa_chiller_1_pcs'] ?> pcs</div>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark"><?= floor($stk['sa_chiller_2_ctn']) ?> ctn</span>
                                        <div class="text-muted small"><?= $stk['sa_chiller_2_pcs'] ?> pcs</div>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark"><?= floor($stk['sa_freezer_1_ctn']) ?> ctn</span>
                                        <div class="text-muted small"><?= $stk['sa_freezer_1_pcs'] ?> pcs</div>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark"><?= floor($stk['sa_freezer_2_ctn']) ?> ctn</span>
                                        <div class="text-muted small"><?= $stk['sa_freezer_2_pcs'] ?> pcs</div>
                                    </td>
                                    <td class="pe-3 text-end fw-extrabold text-purple fs-6">
                                        <?= number_format($stk['total']) ?> pcs
                                        <div class="text-muted small fw-semibold" style="font-size: 10.5px;">
                                            (<?= floor($stk['total'] / $cs) ?> ctn + <?= $stk['total'] % $cs ?> pcs)
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
    // Live Search Filter for Monitor Tables
    function tapisMonitor() {
        let input = document.getElementById('searchMonitor').value.toLowerCase().trim();
        let rows = document.querySelectorAll('.monitor-row-item');
        
        rows.forEach(row => {
            let name = row.getAttribute('data-name');
            if (name.includes(input)) {
                row.style.display = 'table-row';
            } else {
                row.style.display = 'none';
            }
        });
    }
</script>

<?php require_once 'includes/footer.php'; ?>
