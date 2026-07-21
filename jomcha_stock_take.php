<?php
// jomcha_stock_take.php
// OUTLET STOCK COUNT & AUDIT SYSTEM FOR JOMCHA
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? '';
if (!in_array(strtolower($role), ['admin', 'staff', 'intern', 'pss_admin', 'staff_jomcha'])) {
    header("Location: login.php");
    exit;
}

$is_admin = (strtolower($role) === 'admin');

// ── SELF-HEALING DATABASE TABLES ─────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `jomcha_stock_takes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `product_id` INT NOT NULL,
        `jcb_chiller_1_ctn` INT NOT NULL DEFAULT 0,
        `jcb_chiller_1_pcs` INT NOT NULL DEFAULT 0,
        `jcb_chiller_2_ctn` INT NOT NULL DEFAULT 0,
        `jcb_chiller_2_pcs` INT NOT NULL DEFAULT 0,
        `jcb_rack_ctn` INT NOT NULL DEFAULT 0,
        `jcb_rack_pcs` INT NOT NULL DEFAULT 0,
        
        `mms_rack_ctn` INT NOT NULL DEFAULT 0,
        `mms_rack_pcs` INT NOT NULL DEFAULT 0,
        `mms_chiller_1_ctn` INT NOT NULL DEFAULT 0,
        `mms_chiller_1_pcs` INT NOT NULL DEFAULT 0,
        `mms_chiller_2_ctn` INT NOT NULL DEFAULT 0,
        `mms_chiller_2_pcs` INT NOT NULL DEFAULT 0,
        `mms_freezer_meat_ctn` INT NOT NULL DEFAULT 0,
        `mms_freezer_meat_pcs` INT NOT NULL DEFAULT 0,
        `mms_freezer_ice_cream_ctn` INT NOT NULL DEFAULT 0,
        `mms_freezer_ice_cream_pcs` INT NOT NULL DEFAULT 0,
        
        `sa_rack_ctn` INT NOT NULL DEFAULT 0,
        `sa_rack_pcs` INT NOT NULL DEFAULT 0,
        `sa_pallet_1_ctn` INT NOT NULL DEFAULT 0,
        `sa_pallet_1_pcs` INT NOT NULL DEFAULT 0,
        `sa_pallet_2_ctn` INT NOT NULL DEFAULT 0,
        `sa_pallet_2_pcs` INT NOT NULL DEFAULT 0,
        `sa_chiller_1_ctn` INT NOT NULL DEFAULT 0,
        `sa_chiller_1_pcs` INT NOT NULL DEFAULT 0,
        `sa_chiller_2_ctn` INT NOT NULL DEFAULT 0,
        `sa_chiller_2_pcs` INT NOT NULL DEFAULT 0,
        `sa_freezer_1_ctn` INT NOT NULL DEFAULT 0,
        `sa_freezer_1_pcs` INT NOT NULL DEFAULT 0,
        `sa_freezer_2_ctn` INT NOT NULL DEFAULT 0,
        `sa_freezer_2_pcs` INT NOT NULL DEFAULT 0,
        
        `physical_qty` INT NOT NULL,
        `theoretical_qty` INT NOT NULL,
        `variance` INT NOT NULL,
        `taken_by` VARCHAR(100) NOT NULL,
        `take_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

} catch (PDOException $e) {
    error_log("Jomcha Stock Take tables creation failed: " . $e->getMessage());
}

$msg = '';
$msg_type = 'success';

// ── POST HANDLERS ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'jomcha_add_stocktake') {
        $stocktake_data = $_POST['stocktake'] ?? [];
        $taken_by = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Outlet Staff';
        $success_count = 0;
        $total_auto_sales = 0;

        if (!empty($stocktake_data)) {
            try {
                $pdo->beginTransaction();

                foreach ($stocktake_data as $product_id => $counts) {
                    $product_id = intval($product_id);
                    if ($product_id <= 0) continue;

                    // Ambil saiz karton
                    $stmtProd = $pdo->prepare("SELECT pack_size, name FROM products WHERE id = ?");
                    $stmtProd->execute([$product_id]);
                    $prod_info = $stmtProd->fetch();
                    if (!$prod_info) continue;

                    $cs = intval($prod_info['pack_size']) > 0 ? intval($prod_info['pack_size']) : 1;

                    // JC Barn
                    $jcb_chiller_1 = (intval($counts['jcb_chiller_1_ctn'] ?? 0) * $cs) + intval($counts['jcb_chiller_1_pcs'] ?? 0);
                    $jcb_chiller_2 = (intval($counts['jcb_chiller_2_ctn'] ?? 0) * $cs) + intval($counts['jcb_chiller_2_pcs'] ?? 0);
                    $jcb_rack      = (intval($counts['jcb_rack_ctn'] ?? 0) * $cs) + intval($counts['jcb_rack_pcs'] ?? 0);

                    // Moo Moo Station
                    $mms_rack             = (intval($counts['mms_rack_ctn'] ?? 0) * $cs) + intval($counts['mms_rack_pcs'] ?? 0);
                    $mms_chiller_1        = (intval($counts['mms_chiller_1_ctn'] ?? 0) * $cs) + intval($counts['mms_chiller_1_pcs'] ?? 0);
                    $mms_chiller_2        = (intval($counts['mms_chiller_2_ctn'] ?? 0) * $cs) + intval($counts['mms_chiller_2_pcs'] ?? 0);
                    $mms_freezer_meat     = (intval($counts['mms_freezer_meat_ctn'] ?? 0) * $cs) + intval($counts['mms_freezer_meat_pcs'] ?? 0);
                    $mms_freezer_ice_cream = (intval($counts['mms_freezer_ice_cream_ctn'] ?? 0) * $cs) + intval($counts['mms_freezer_ice_cream_pcs'] ?? 0);

                    // Store Area
                    $sa_rack      = (intval($counts['sa_rack_ctn'] ?? 0) * $cs) + intval($counts['sa_rack_pcs'] ?? 0);
                    $sa_pallet_1  = (intval($counts['sa_pallet_1_ctn'] ?? 0) * $cs) + intval($counts['sa_pallet_1_pcs'] ?? 0);
                    $sa_pallet_2  = (intval($counts['sa_pallet_2_ctn'] ?? 0) * $cs) + intval($counts['sa_pallet_2_pcs'] ?? 0);
                    $sa_chiller_1 = (intval($counts['sa_chiller_1_ctn'] ?? 0) * $cs) + intval($counts['sa_chiller_1_pcs'] ?? 0);
                    $sa_chiller_2 = (intval($counts['sa_chiller_2_ctn'] ?? 0) * $cs) + intval($counts['sa_chiller_2_pcs'] ?? 0);
                    $sa_freezer_1 = (intval($counts['sa_freezer_1_ctn'] ?? 0) * $cs) + intval($counts['sa_freezer_1_pcs'] ?? 0);
                    $sa_freezer_2 = (intval($counts['sa_freezer_2_ctn'] ?? 0) * $cs) + intval($counts['sa_freezer_2_pcs'] ?? 0);

                    // Total Physical Qty (in loose pieces)
                    $physical_qty = $jcb_chiller_1 + $jcb_chiller_2 + $jcb_rack + 
                                    $mms_rack + $mms_chiller_1 + $mms_chiller_2 + $mms_freezer_meat + $mms_freezer_ice_cream + 
                                    $sa_rack + $sa_pallet_1 + $sa_pallet_2 + $sa_chiller_1 + $sa_chiller_2 + $sa_freezer_1 + $sa_freezer_2;

                    // Ambil baki teoretikal
                    // Theoretical = (Delivered ctn * pack_size)
                    $stmtDel = $pdo->prepare("
                        SELECT COALESCE(SUM(oi.qty * p.pack_size), 0)
                        FROM outbound_items oi
                        JOIN outbound_logs ol ON oi.outbound_id = ol.id
                        JOIN products p ON oi.product_id = p.id
                        WHERE oi.product_id = ? AND ol.category = 'Jomcha'
                    ");
                    $stmtDel->execute([$product_id]);
                    $total_delivered_pcs = intval($stmtDel->fetchColumn());

                    $theoretical = $total_delivered_pcs;
                    
                    if (!isset($counts['_counted']) || $counts['_counted'] != '1') continue;

                    $variance = $physical_qty - $theoretical;

                    // Insert Jomcha Stock Take Record
                    $stmtInsert = $pdo->prepare("
                        INSERT INTO jomcha_stock_takes (
                            product_id, jcb_chiller_1_ctn, jcb_chiller_1_pcs, jcb_chiller_2_ctn, jcb_chiller_2_pcs, jcb_rack_ctn, jcb_rack_pcs,
                            mms_rack_ctn, mms_rack_pcs, mms_chiller_1_ctn, mms_chiller_1_pcs, mms_chiller_2_ctn, mms_chiller_2_pcs, mms_freezer_meat_ctn, mms_freezer_meat_pcs, mms_freezer_ice_cream_ctn, mms_freezer_ice_cream_pcs,
                            sa_rack_ctn, sa_rack_pcs, sa_pallet_1_ctn, sa_pallet_1_pcs, sa_pallet_2_ctn, sa_pallet_2_pcs, sa_chiller_1_ctn, sa_chiller_1_pcs, sa_chiller_2_ctn, sa_chiller_2_pcs, sa_freezer_1_ctn, sa_freezer_1_pcs, sa_freezer_2_ctn, sa_freezer_2_pcs,
                            physical_qty, theoretical_qty, variance, taken_by
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?,
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                            ?, ?, ?, ?
                        )
                    ");
                    $stmtInsert->execute([
                        $product_id, 
                        intval($counts['jcb_chiller_1_ctn'] ?? 0), intval($counts['jcb_chiller_1_pcs'] ?? 0),
                        intval($counts['jcb_chiller_2_ctn'] ?? 0), intval($counts['jcb_chiller_2_pcs'] ?? 0),
                        intval($counts['jcb_rack_ctn'] ?? 0), intval($counts['jcb_rack_pcs'] ?? 0),
                        
                        intval($counts['mms_rack_ctn'] ?? 0), intval($counts['mms_rack_pcs'] ?? 0),
                        intval($counts['mms_chiller_1_ctn'] ?? 0), intval($counts['mms_chiller_1_pcs'] ?? 0),
                        intval($counts['mms_chiller_2_ctn'] ?? 0), intval($counts['mms_chiller_2_pcs'] ?? 0),
                        intval($counts['mms_freezer_meat_ctn'] ?? 0), intval($counts['mms_freezer_meat_pcs'] ?? 0),
                        intval($counts['mms_freezer_ice_cream_ctn'] ?? 0), intval($counts['mms_freezer_ice_cream_pcs'] ?? 0),
                        
                        intval($counts['sa_rack_ctn'] ?? 0), intval($counts['sa_rack_pcs'] ?? 0),
                        intval($counts['sa_pallet_1_ctn'] ?? 0), intval($counts['sa_pallet_1_pcs'] ?? 0),
                        intval($counts['sa_pallet_2_ctn'] ?? 0), intval($counts['sa_pallet_2_pcs'] ?? 0),
                        intval($counts['sa_chiller_1_ctn'] ?? 0), intval($counts['sa_chiller_1_pcs'] ?? 0),
                        intval($counts['sa_chiller_2_ctn'] ?? 0), intval($counts['sa_chiller_2_pcs'] ?? 0),
                        intval($counts['sa_freezer_1_ctn'] ?? 0), intval($counts['sa_freezer_1_pcs'] ?? 0),
                        intval($counts['sa_freezer_2_ctn'] ?? 0), intval($counts['sa_freezer_2_pcs'] ?? 0),
                        
                        $physical_qty, $theoretical, $variance, $taken_by
                    ]);

                    $success_count++;
                }

                if (function_exists('log_system_activity')) {
                    log_system_activity("Performed Jomcha Stock Take", "jomcha_stock_takes", null, "Audit stok fizikal oleh $taken_by. Bilangan produk terlibat: $success_count.");
                }

                $pdo->commit();
                $msg = "Berjaya! $success_count produk dikira. Kiraan stok fizikal telah direkodkan.";
                $msg_type = 'success';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $msg = "Gagal memproses kiraan stok: " . $e->getMessage();
                $msg_type = 'danger';
            }
        } else {
            $msg = "Tiada data kiraan stok yang dihantar.";
            $msg_type = 'warning';
        }
    }
}

// ── HEURISTIC EXPIRY FUNCTION ────────────────────────────────────────────────
function dapatkan_status_expiry($expiry_date, $category) {
    if (!$expiry_date) {
        return ['label' => 'Tiada Rekod', 'class' => 'bg-secondary text-white', 'warn' => false, 'days' => 999];
    }
    $now = new DateTime(date('Y-m-d'));
    $exp = new DateTime($expiry_date);
    $diff = $now->diff($exp);
    $days_left = (int)$diff->format("%r%a");

    if ($days_left < 0) {
        return ['label' => 'TELAH LUPUT (' . abs($days_left) . ' hari lepas)', 'class' => 'bg-danger text-white', 'warn' => true, 'days' => $days_left];
    }

    $is_uht_or_ic = (stripos($category, 'UHT') !== false || stripos($category, 'IC') !== false || stripos($category, 'IceCream') !== false);
    $is_pst = (stripos($category, 'PST') !== false || stripos($category, 'Butter') !== false || stripos($category, 'Cooking') !== false);

    $threshold = 0;
    if ($is_uht_or_ic) $threshold = 20;
    elseif ($is_pst) $threshold = 10;

    if ($days_left <= $threshold) {
        return ['label' => 'AMARAN (' . $days_left . ' hari berbaki)', 'class' => 'bg-warning text-dark', 'warn' => true, 'days' => $days_left];
    }
    return ['label' => 'Selamat (' . $days_left . ' hari berbaki)', 'class' => 'bg-success text-white', 'warn' => false, 'days' => $days_left];
}

// ── GET DATA FOR OUTLET VIEW ─────────────────────────────────────────────────
$products_list = $pdo->query("SELECT * FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// Compliance - Check days since last stock take
$days_since_last_take = null;
$last_take_date_fmt = "Belum Pernah";
$lt = $pdo->query("SELECT MAX(take_date) as last_date FROM jomcha_stock_takes")->fetch();
if ($lt && $lt['last_date']) {
    $last_take_date_fmt = date('d/m/Y', strtotime($lt['last_date']));
    $days_since_last_take = (new DateTime($lt['last_date']))->diff(new DateTime())->days;
}

// Check permitted_location & permitted_sub_location columns on products table
$perm_loc_select = "'' as permitted_location, '' as permitted_sub_location,";
try {
    $c1 = $pdo->query("SHOW COLUMNS FROM products LIKE 'permitted_location'")->fetch();
    $c2 = $pdo->query("SHOW COLUMNS FROM products LIKE 'permitted_sub_location'")->fetch();
    if ($c1 && $c2) {
        $perm_loc_select = "p.permitted_location, p.permitted_sub_location,";
    } elseif ($c1) {
        $perm_loc_select = "p.permitted_location, '' as permitted_sub_location,";
    }
} catch (Exception $ex) {
    $perm_loc_select = "'' as permitted_location, '' as permitted_sub_location,";
}

// Active stocks with balance calculation
$stocks_raw = $pdo->query("
    SELECT p.id, p.name, p.category, {$perm_loc_select} p.pack_size, p.uom,
            (SELECT COALESCE(SUM(oi.qty * p2.pack_size), 0)
             FROM outbound_items oi
             JOIN outbound_logs ol ON oi.outbound_id = ol.id
             JOIN products p2 ON oi.product_id = p2.id
             WHERE oi.product_id = p.id AND ol.category = 'Jomcha') as baki
    FROM products p 
    WHERE p.is_active = 1
    ORDER BY baki DESC, p.name ASC
")->fetchAll();

// Cache category-level locations from existing products to support automatic inheritance
$category_locations = [];
try {
    if ($c1 && $c2) {
        $cat_locs = $pdo->query("
            SELECT category, permitted_location, permitted_sub_location 
            FROM products 
            WHERE (permitted_location IS NOT NULL AND permitted_location != '') 
               OR (permitted_sub_location IS NOT NULL AND permitted_sub_location != '')
        ")->fetchAll();
        foreach ($cat_locs as $cl) {
            $cat = trim(strtolower($cl['category']));
            if (!isset($category_locations[$cat])) {
                $category_locations[$cat] = [
                    'loc' => $cl['permitted_location'],
                    'sub' => $cl['permitted_sub_location']
                ];
            }
        }
    }
} catch (Exception $ex) {}

$jomcha_stocks = [];
foreach ($stocks_raw as $stk) {
    $stk_loc = $stk['permitted_location'] ?? '';
    $stk_sub_loc = $stk['permitted_sub_location'] ?? '';
    
    if (empty($stk_loc) && empty($stk_sub_loc)) {
        // 1. Try to inherit from category configuration
        $cat = trim(strtolower($stk['category']));
        if (isset($category_locations[$cat])) {
            $stk_loc = $category_locations[$cat]['loc'];
            $stk_sub_loc = $category_locations[$cat]['sub'];
        }
    }
    
    // 2. Fall back to smart defaults if still empty (consistent with product_management.php)
    if (empty($stk_loc) && empty($stk_sub_loc)) {
        $cat_u = strtoupper(trim($stk['category']));
        if (strpos($cat_u, 'BEEF') !== false || strpos($cat_u, 'MEAT') !== false || strpos($cat_u, 'FROZEN') !== false || strpos($cat_u, 'ICE') !== false) {
            $stk_loc = ''; // Allow all areas by default
            $stk_sub_loc = 'Freezer';
        } elseif (strpos($cat_u, 'CHILLED') !== false || strpos($cat_u, 'MILK') !== false || strpos($cat_u, 'DAIRY') !== false || strpos($cat_u, 'PST') !== false) {
            $stk_loc = ''; // Allow all areas by default
            $stk_sub_loc = 'Chiller';
        } else {
            $stk_loc = ''; // Allow all areas by default
            $stk_sub_loc = 'Rack, Pallet';
        }
    }
    
    $stk['permitted_location'] = $stk_loc;
    $stk['permitted_sub_location'] = $stk_sub_loc;
    $jomcha_stocks[] = $stk;
}

// Expiry Monitoring
$all_expiry_batches = [];
$expiry_expired = [];
$expiry_warn = [];
$expiry_safe = [];

$raw_batches = $pdo->query("
    SELECT 
        oi.product_id, 
        oi.batch, 
        p.name as p_name, 
        p.category as category, 
        p.pack_size,
        SUM(oi.qty) as total_qty_ctn,
        MAX(ol.date) as delivery_date,
        (SELECT expiry_date FROM inventory_batches WHERE product_id = oi.product_id AND batch_no = oi.batch LIMIT 1) as expiry_date
    FROM outbound_items oi
    JOIN outbound_logs ol ON oi.outbound_id = ol.id
    JOIN products p ON oi.product_id = p.id
    WHERE ol.category = 'Jomcha'
    GROUP BY oi.product_id, oi.batch
    ORDER BY expiry_date ASC
")->fetchAll();

foreach ($raw_batches as $batch) {
    $status_data = dapatkan_status_expiry($batch['expiry_date'], $batch['category']);
    $batch['status_info'] = $status_data;
    $all_expiry_batches[] = $batch;
    if ($status_data['days'] < 0) {
        $expiry_expired[] = $batch;
    } elseif ($status_data['warn']) {
        $expiry_warn[] = $batch;
    } else {
        $expiry_safe[] = $batch;
    }
}

$page_title = 'Kiraan Stok Jomcha | MMS';
require_once 'includes/header.php';
?>

<style>
    body {
        background-color: #faf5ff;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
    
    .jomcha-header {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        padding: 30px 0 25px;
        border-bottom: 5px solid #34d399;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
    }
    
    .jomcha-card {
        background: #ffffff;
        border-radius: 18px;
        border: 1px solid #f3e8ff;
        box-shadow: 0 10px 25px -5px rgba(88, 28, 135, 0.05);
        transition: transform 0.25s, box-shadow 0.25s;
    }
    
    .jomcha-card:hover {
        box-shadow: 0 15px 30px -5px rgba(88, 28, 135, 0.08);
    }

    .stk-card-row {
        background: #ffffff;
        border-radius: 14px;
        border: 1px solid #e9d5ff;
        padding: 16px 20px;
        margin-bottom: 12px;
        transition: all 0.2s;
    }

    .stk-card-row.mandatory {
        border-left: 4px solid #ef4444;
    }

    .stk-card-row.optional {
        border-left: 4px solid #cbd5e1;
    }
    
    .sub-section-title {
        font-size: 0.8rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #6b21a8;
        background: #faf5ff;
        padding: 6px 12px;
        border-radius: 8px;
        margin-bottom: 12px;
        border-left: 3px solid #a855f7;
    }
    
    .nav-pills .nav-link {
        color: #6b21a8;
        font-weight: 700;
        border-radius: 12px;
        padding: 10px 20px;
        transition: all 0.2s;
    }

    .nav-pills .nav-link.active {
        background: linear-gradient(135deg, #a855f7 0%, #7e22ce 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(126, 34, 206, 0.2);
    }
    
    .location-cell {
        background: #faf8ff;
        border: 1px solid #f3e8ff;
        border-radius: 10px;
        padding: 10px 14px;
    }
    
    .text-navy {
        color: #1e1b4b;
    }
</style>

<div class="jomcha-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <span class="badge mb-2 px-3 py-1.5 fw-bold text-uppercase" style="background: rgba(255,255,255,0.15); color: #fff; font-size: 0.72rem; letter-spacing: 1px;" data-lang="jomcha_st_badge">
                    Jomcha Shop Audit
                </span>
                <h1 class="fw-extrabold m-0 text-white" style="font-size: 2.1rem; letter-spacing: -0.5px;">
                    <i class="bi bi-clipboard-check me-2 text-warning"></i><span data-lang="jomcha_st_title">Kiraan Stok & Audit Jomcha</span>
                </h1>
                <p class="text-white-50 m-0 mt-1" style="font-size: 0.95rem;" data-lang="jomcha_st_subtitle">
                    Uruskan kiraan stok fizikal mengikut sub-lokasi, rekod stok rosak & semak status hayat luput.
                </p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-light fw-bold px-4 py-2.5 rounded-pill" style="border-width: 2px;">
                    <i class="bi bi-house me-1"></i> <span data-lang="jomcha_st_btn_dashboard">Dashboard</span>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    
    <?php if (isset($_GET['msg']) || !empty($msg)): ?>
        <?php 
        $display_msg = $_GET['msg'] ?? $msg; 
        $display_type = $_GET['type'] ?? $msg_type;
        ?>
        <div class="alert alert-<?= htmlspecialchars($display_type ?? '') ?> border-0 shadow-sm mb-4 alert-dismissible fade show" role="alert">
            <i class="bi <?= $display_type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i>
            <?= htmlspecialchars($display_msg ?? '') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- TABS NAVIGATION -->
    <ul class="nav nav-pills mb-4 gap-2 bg-white p-2 rounded-3 shadow-sm border" id="jomchaTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="stok-tab" data-bs-toggle="pill" data-bs-target="#tab-stok" type="button" role="tab" data-lang="jomcha_st_tab_st"><i class="bi bi-calculator me-1"></i> Kiraan Stok (Stock Take)</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="luput-tab" data-bs-toggle="pill" data-bs-target="#tab-luput" type="button" role="tab" data-lang="jomcha_st_tab_exp"><i class="bi bi-calendar-x me-1"></i> Tarikh Luput (Expiry)</button>
        </li>
    </ul>

    <div class="tab-content" id="jomchaTabContent">
        
        <!-- ════════════════════════════════════════ -->
        <!-- TAB: KIRAAN STOK                        -->
        <!-- ════════════════════════════════════════ -->
        <div class="tab-pane fade show active" id="tab-stok" role="tabpanel">
            
            <!-- Compliance status -->
            <?php if ($days_since_last_take === null): ?>
                <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center gap-3 py-3 mb-4">
                    <i class="bi bi-exclamation-octagon-fill fs-3 text-danger"></i>
                    <div>
                        <strong class="d-block text-danger" data-lang="jomcha_st_alert1_title">Tiada Rekod Pengauditan Stok Temui!</strong>
                        <span class="small text-muted" data-lang="jomcha_st_alert1_desc">Sila hantar laporan kiraan stok pertama anda hari ini untuk menstabilkan data inventori outlet.</span>
                    </div>
                </div>
            <?php elseif ($days_since_last_take > 7): ?>
                <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center gap-3 py-3 mb-4">
                    <i class="bi bi-exclamation-triangle-fill fs-3 text-warning"></i>
                    <div>
                        <strong class="d-block text-warning-emphasis" data-lang="jomcha_st_alert2_title">Kiraan Stok Tertunggak (Overdue)!</strong>
                        <span class="small text-muted">
                            <span data-lang="jomcha_st_alert2_desc1">Kiraan terakhir dilakukan pada</span> <?= $last_take_date_fmt ?> (<?= $days_since_last_take ?> <span data-lang="jomcha_st_alert2_desc2">hari yang lalu). Audit stok fizikal perlu dilakukan seminggu sekali.</span>
                        </span>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-success border-0 shadow-sm d-flex align-items-center gap-3 py-3 mb-4">
                    <i class="bi bi-check-circle-fill fs-3 text-success"></i>
                    <div>
                        <strong class="d-block text-success" data-lang="jomcha_st_alert3_title">Status Pematuhan Tally (OK)</strong>
                        <span class="small text-muted">
                            <span data-lang="jomcha_st_alert3_desc1">Kiraan stok terakhir disahkan pada</span> <?= $last_take_date_fmt ?> (<?= $days_since_last_take ?> <span data-lang="jomcha_st_alert3_desc2">hari yang lalu).</span>
                        </span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Search & Filters Row -->
            <div class="row g-3 mb-4">
                <!-- Search Input -->
                <div class="col-md-6">
                    <div class="input-group shadow-sm" style="border-radius:15px; overflow:hidden;">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="searchStk" onkeyup="tapisKiraan()" class="form-control border-start-0 py-3" data-lang-placeholder="jomcha_st_search" placeholder="Cari nama produk...">
                    </div>
                </div>
                <!-- Category Filter -->
                <div class="col-md-3">
                    <select id="filterCategory" onchange="tapisKiraan()" class="form-select py-3 shadow-sm" style="border-radius:15px; border: 1px solid #dee2e6; font-weight:600; color:#4a5568;">
                        <option value="" data-lang="jomcha_st_filter_cat_all">-- Semua Kategori --</option>
                        <?php 
                        $cats = array_unique(array_column($jomcha_stocks, 'category'));
                        sort($cats);
                        foreach ($cats as $cat): 
                            if (empty(trim($cat))) continue;
                        ?>
                            <option value="<?= htmlspecialchars(strtolower(trim($cat))) ?>"><?= htmlspecialchars($cat ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Sort Selector -->
                <div class="col-md-3">
                    <select id="sortStk" onchange="susunKiraan()" class="form-select py-3 shadow-sm" style="border-radius:15px; border: 1px solid #dee2e6; font-weight:600; color:#4a5568;">
                        <option value="default" data-lang="jomcha_st_sort_default">Urutan: Baki Terbanyak</option>
                        <option value="name" data-lang="jomcha_st_sort_name">Urutan: Nama (A-Z)</option>
                        <option value="category" data-lang="jomcha_st_sort_category">Urutan: Kategori (A-Z)</option>
                    </select>
                </div>
            </div>

            <!-- TABLE LAYOUT FOR CLEAN VIEW -->
            <form action="" method="POST" onsubmit="return sahkanKiraan(event)">
                <input type="hidden" name="action" value="jomcha_add_stocktake">
                
                <div class="jomcha-card p-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle table-borderless" style="font-size:0.92rem;">
                            <thead class="table-light">
                                <tr class="text-muted fw-bold small">
                                    <th class="ps-3" style="width: 45%;" data-lang="jomcha_st_col_prod">Nama Produk</th>
                                    <th class="text-center" style="width: 15%;" data-lang="jomcha_st_col_pack">Pack Size</th>
                                    <th class="text-center" style="width: 15%;" data-lang="jomcha_st_col_theo">Teoretikal</th>
                                    <th class="text-center" style="width: 15%;" data-lang="jomcha_st_col_phys">Kiraan Fizikal</th>
                                    <th class="pe-3 text-center" style="width: 10%;" data-lang="jomcha_st_col_act">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody id="stkContainer">
                                <?php foreach ($jomcha_stocks as $stk): 
                                    $is_mandatory = $stk['baki'] > 0;
                                    $cs = intval($stk['pack_size']) > 0 ? intval($stk['pack_size']) : 1;
                                ?>
                                    <tr class="stk-row-item border-bottom" 
                                         data-id="<?= $stk['id'] ?>"
                                         data-name="<?= strtolower(htmlspecialchars($stk['name'] ?? '')) ?>"
                                         data-category="<?= strtolower(htmlspecialchars(trim($stk['category']))) ?>"
                                         data-baki="<?= $stk['baki'] ?>"
                                         data-mandatory="<?= $is_mandatory ? 'true' : 'false' ?>">
                                        
                                        <!-- Nama Produk & Kategori -->
                                        <td class="ps-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <div>
                                                    <span class="badge bg-secondary-subtle text-secondary small mb-1"><?= htmlspecialchars($stk['category'] ?? '') ?></span>
                                                    <h6 class="fw-bold mb-0 text-navy"><?= htmlspecialchars($stk['name'] ?? '') ?></h6>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Pack Size -->
                                        <td class="text-center">
                                            <span class="fw-bold text-muted"><?= $stk['pack_size'] ?> <?= $stk['uom'] ?>/ctn</span>
                                        </td>

                                        <!-- Theoretical stock -->
                                        <td class="text-center">
                                            <span class="fw-bold text-secondary"><?= $stk['baki'] ?> pcs</span>
                                            <div class="text-muted small" style="font-size: 11px;">
                                                (<?= floor($stk['baki'] / $cs) ?> ctn + <?= $stk['baki'] % $cs ?> pcs)
                                            </div>
                                        </td>

                                        <!-- Physical stock (updated dynamically) -->
                                        <td class="text-center">
                                            <strong id="val_actual_<?= $stk['id'] ?>" class="text-muted fs-6">0 pcs</strong>
                                            <div class="text-muted small" id="val_actual_detail_<?= $stk['id'] ?>" style="font-size: 11px;">
                                                (0 ctn + 0 pcs)
                                            </div>
                                        </td>

                                        <!-- Action button to open Modal -->
                                        <td class="pe-3 text-center">
                                            <button type="button" class="btn btn-purple btn-sm fw-bold px-3 py-2 rounded-pill shadow-sm text-white" 
                                                    style="background: linear-gradient(135deg, #a855f7 0%, #7e22ce 100%); border:none;"
                                                    onclick="bukaModalKiraan(<?= $stk['id'] ?>, '<?= htmlspecialchars(addslashes($stk['name'] ?? '')) ?>', <?= $cs ?>, <?= $stk['baki'] ?>, '<?= htmlspecialchars(addslashes($stk['category'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($stk['permitted_location'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($stk['permitted_sub_location'] ?? '')) ?>')">
                                                <i class="bi bi-pencil-square"></i> <span data-lang="jomcha_st_btn_count">Kira</span>
                                            </button>
                                        </td>

                                        <!-- HIDDEN FIELDS FOR SUB-LOCATIONS -->
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][_counted]" id="counted_<?= $stk['id'] ?>" value="0">
                                        
                                        <!-- JC Barn -->
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][jcb_chiller_1_ctn]" id="jcb_chiller_1_ctn_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][jcb_chiller_1_pcs]" id="jcb_chiller_1_pcs_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][jcb_chiller_2_ctn]" id="jcb_chiller_2_ctn_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][jcb_chiller_2_pcs]" id="jcb_chiller_2_pcs_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][jcb_rack_ctn]" id="jcb_rack_ctn_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][jcb_rack_pcs]" id="jcb_rack_pcs_<?= $stk['id'] ?>" value="0">

                                        <!-- Moo Moo Station -->
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][mms_rack_ctn]" id="mms_rack_ctn_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][mms_rack_pcs]" id="mms_rack_pcs_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][mms_chiller_1_ctn]" id="mms_chiller_1_ctn_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][mms_chiller_1_pcs]" id="mms_chiller_1_pcs_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][mms_chiller_2_ctn]" id="mms_chiller_2_ctn_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][mms_chiller_2_pcs]" id="mms_chiller_2_pcs_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][mms_freezer_meat_ctn]" id="mms_freezer_meat_ctn_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][mms_freezer_meat_pcs]" id="mms_freezer_meat_pcs_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][mms_freezer_ice_cream_ctn]" id="mms_freezer_ice_cream_ctn_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][mms_freezer_ice_cream_pcs]" id="mms_freezer_ice_cream_pcs_<?= $stk['id'] ?>" value="0">

                                        <!-- Store Area -->
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][sa_rack_ctn]" id="sa_rack_ctn_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][sa_rack_pcs]" id="sa_rack_pcs_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][sa_pallet_1_ctn]" id="sa_pallet_1_ctn_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][sa_pallet_1_pcs]" id="sa_pallet_1_pcs_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][sa_pallet_2_ctn]" id="sa_pallet_2_ctn_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][sa_pallet_2_pcs]" id="sa_pallet_2_pcs_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][sa_chiller_1_ctn]" id="sa_chiller_1_ctn_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][sa_chiller_1_pcs]" id="sa_chiller_1_pcs_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][sa_chiller_2_ctn]" id="sa_chiller_2_ctn_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][sa_chiller_2_pcs]" id="sa_chiller_2_pcs_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][sa_freezer_1_ctn]" id="sa_freezer_1_ctn_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][sa_freezer_1_pcs]" id="sa_freezer_1_pcs_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][sa_freezer_2_ctn]" id="sa_freezer_2_ctn_<?= $stk['id'] ?>" value="0">
                                        <input type="hidden" name="stocktake[<?= $stk['id'] ?>][sa_freezer_2_pcs]" id="sa_freezer_2_pcs_<?= $stk['id'] ?>" value="0">
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-lg py-3 shadow fw-bold text-white rounded-pill" style="background: linear-gradient(135deg, #a855f7 0%, #7e22ce 100%); border:none;"><i class="bi bi-save me-2"></i><span data-lang="jomcha_st_submit">Sahkan & Hantar Semua Kiraan</span></button>
                </div>
            </form>
        </div>

        <!-- ════════════════════════════════════════ -->
        <!-- TAB: TARIKH LUPUT                       -->
        <!-- ════════════════════════════════════════ -->
        <div class="tab-pane fade" id="tab-luput" role="tabpanel">
            <div class="jomcha-card p-4">
                <h5 class="fw-bold text-navy mb-4"><i class="bi bi-calendar-x-fill text-danger me-2"></i><span data-lang="jomcha_st_exp_table_title">Pemantauan Tarikh Luput Stok Jomcha</span></h5>
                
                <div class="row g-3 mb-4 text-center">
                    <div class="col-md-4">
                        <div class="p-3 bg-danger bg-opacity-10 border border-danger-subtle rounded-3">
                            <span class="text-danger fw-bold uppercase small tracking-wider">Telah Luput</span>
                            <h2 class="fw-extrabold text-danger mt-1"><?= count($expiry_expired) ?></h2>
                            <small class="text-muted">Perlu dibuang serta-merta</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-warning bg-opacity-10 border border-warning-subtle rounded-3">
                            <span class="text-warning-emphasis fw-bold uppercase small tracking-wider">Amaran Luput</span>
                            <h2 class="fw-extrabold text-warning-emphasis mt-1"><?= count($expiry_warn) ?></h2>
                            <small class="text-muted">Had amaran 10 / 20 hari</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-success bg-opacity-10 border border-success-subtle rounded-3">
                            <span class="text-success fw-bold uppercase small tracking-wider">Selamat</span>
                            <h2 class="fw-extrabold text-success mt-1"><?= count($expiry_safe) ?></h2>
                            <small class="text-muted">Jangka hayat sihat</small>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle table-borderless" style="font-size:0.9rem;">
                        <thead class="table-light">
                            <tr class="small text-muted fw-bold">
                                <th class="ps-3">Nama Produk</th>
                                <th class="text-center">Batch No</th>
                                <th class="text-center">Tarikh Luput</th>
                                <th class="text-end">Hari Berbaki</th>
                                <th class="pe-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($all_expiry_batches)): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">Tiada batch dikesan di outlet.</td></tr>
                            <?php else: foreach ($all_expiry_batches as $b): 
                                $s_info = $b['status_info'];
                                $row_class = $s_info['days'] < 0 ? 'bg-danger bg-opacity-10' : ($s_info['warn'] ? 'bg-warning bg-opacity-10' : '');
                            ?>
                                <tr class="<?= $row_class ?> border-bottom">
                                    <td class="ps-3">
                                        <strong><?= htmlspecialchars($b['p_name'] ?? '') ?></strong>
                                        <div class="small text-muted"><?= htmlspecialchars($b['category'] ?? '') ?></div>
                                    </td>
                                    <td class="text-center"><code class="fw-bold"><?= htmlspecialchars($b['batch'] ?? '-') ?></code></td>
                                    <td class="text-center fw-bold"><?= $b['expiry_date'] ? date('d/m/Y', strtotime($b['expiry_date'])) : '-' ?></td>
                                    <td class="text-end fw-extrabold fs-5"><?= $s_info['days'] ?> H</td>
                                    <td class="pe-3 text-center">
                                        <span class="badge <?= $s_info['class'] ?> px-3 py-1.5 rounded-pill text-uppercase"><?= $s_info['label'] ?></span>
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

<!-- ════════════════════════════════════════════════════════════════ -->
<!-- DYNAMIC MODAL FOR JOMCHA DETAILED STOCK TAKE                    -->
<!-- ════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="stockTakeModal" tabindex="-1" aria-labelledby="stockTakeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 16px; overflow: hidden; border: none; box-shadow: 0 15px 35px rgba(0,0,0,0.15);">
            
            <div class="modal-header text-white px-4 py-3" style="background: linear-gradient(135deg, #581c87 0%, #3b0764 100%);">
                <div>
                    <h5 class="modal-title fw-bold" id="stockTakeModalLabel">📝 <span data-lang="jomcha_st_modal_title">Masukkan Kiraan Stok</span></h5>
                    <small id="modalProductName" class="text-white-50 fw-semibold"></small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 bg-light">
                
                <!-- Sub-tabs within the Modal -->
                <ul class="nav nav-tabs mb-4 border-bottom-0 justify-content-center" id="modalSectionTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold px-3 text-purple" id="modal-jcb-tab" data-bs-toggle="tab" data-bs-target="#modal-jcb" type="button" role="tab" style="border-radius: 8px 8px 0 0;"><i class="bi bi-cup-hot me-1"></i>1. JC Barn</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold px-3 text-purple" id="modal-mms-tab" data-bs-toggle="tab" data-bs-target="#modal-mms" type="button" role="tab" style="border-radius: 8px 8px 0 0;"><i class="bi bi-box2 me-1"></i>2. Kedai Jomcha</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold px-3 text-purple" id="modal-sa-tab" data-bs-toggle="tab" data-bs-target="#modal-sa" type="button" role="tab" style="border-radius: 8px 8px 0 0;"><i class="bi bi-house-door me-1"></i>3. Store Area</button>
                    </li>
                </ul>

                <div class="tab-content" id="modalSectionTabsContent">
                    
                    <!-- MODAL TAB: JC BARN (2 Chillers, 1 Rack) -->
                    <div class="tab-pane fade show active" id="modal-jcb" role="tabpanel">
                        <div class="row g-3">
                            <!-- Chiller 1 -->
                            <div class="col-md-4">
                                <div class="location-cell shadow-sm">
                                    <label class="form-label small fw-extrabold text-muted text-uppercase mb-2">❄️ Chiller 1</label>
                                    <div class="row g-1">
                                        <div class="col-6">
                                            <input type="number" id="modal_jcb_chiller_1_ctn" min="0" value="0" class="form-control form-control-sm text-center fw-bold border-purple" placeholder="Ctn" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">ctn</span>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" id="modal_jcb_chiller_1_pcs" min="0" value="0" class="form-control form-control-sm text-center border-purple" placeholder="Pcs" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">pcs</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Chiller 2 -->
                            <div class="col-md-4">
                                <div class="location-cell shadow-sm">
                                    <label class="form-label small fw-extrabold text-muted text-uppercase mb-2">❄️ Chiller 2</label>
                                    <div class="row g-1">
                                        <div class="col-6">
                                            <input type="number" id="modal_jcb_chiller_2_ctn" min="0" value="0" class="form-control form-control-sm text-center fw-bold border-purple" placeholder="Ctn" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">ctn</span>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" id="modal_jcb_chiller_2_pcs" min="0" value="0" class="form-control form-control-sm text-center border-purple" placeholder="Pcs" oninput="hitungSubTotalModal();">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">pcs</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Rack -->
                            <div class="col-md-4">
                                <div class="location-cell shadow-sm">
                                    <label class="form-label small fw-extrabold text-muted text-uppercase mb-2">🪵 Rack (Rak)</label>
                                    <div class="row g-1">
                                        <div class="col-6">
                                            <input type="number" id="modal_jcb_rack_ctn" min="0" value="0" class="form-control form-control-sm text-center fw-bold border-purple" placeholder="Ctn" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">ctn</span>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" id="modal_jcb_rack_pcs" min="0" value="0" class="form-control form-control-sm text-center border-purple" placeholder="Pcs" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">pcs</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MODAL TAB: KEDAI JOMCHA (1 Rack, 2 Chillers, 2 Freezers) -->
                    <div class="tab-pane fade" id="modal-mms" role="tabpanel">
                        <div class="row g-3">
                            <!-- Rack -->
                            <div class="col-md-4">
                                <div class="location-cell shadow-sm">
                                    <label class="form-label small fw-extrabold text-muted text-uppercase mb-2">🪵 Rack (Rak)</label>
                                    <div class="row g-1">
                                        <div class="col-6">
                                            <input type="number" id="modal_mms_rack_ctn" min="0" value="0" class="form-control form-control-sm text-center fw-bold border-purple" placeholder="Ctn" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">ctn</span>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" id="modal_mms_rack_pcs" min="0" value="0" class="form-control form-control-sm text-center border-purple" placeholder="Pcs" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">pcs</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Chiller 1 -->
                            <div class="col-md-4">
                                <div class="location-cell shadow-sm">
                                    <label class="form-label small fw-extrabold text-muted text-uppercase mb-2">❄️ Chiller 1</label>
                                    <div class="row g-1">
                                        <div class="col-6">
                                            <input type="number" id="modal_mms_chiller_1_ctn" min="0" value="0" class="form-control form-control-sm text-center fw-bold border-purple" placeholder="Ctn" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">ctn</span>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" id="modal_mms_chiller_1_pcs" min="0" value="0" class="form-control form-control-sm text-center border-purple" placeholder="Pcs" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">pcs</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Chiller 2 -->
                            <div class="col-md-4">
                                <div class="location-cell shadow-sm">
                                    <label class="form-label small fw-extrabold text-muted text-uppercase mb-2">❄️ Chiller 2</label>
                                    <div class="row g-1">
                                        <div class="col-6">
                                            <input type="number" id="modal_mms_chiller_2_ctn" min="0" value="0" class="form-control form-control-sm text-center fw-bold border-purple" placeholder="Ctn" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">ctn</span>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" id="modal_mms_chiller_2_pcs" min="0" value="0" class="form-control form-control-sm text-center border-purple" placeholder="Pcs" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">pcs</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Freezer Meat -->
                            <div class="col-md-6">
                                <div class="location-cell shadow-sm">
                                    <label class="form-label small fw-extrabold text-muted text-uppercase mb-2">🥩 Freezer (Meat)</label>
                                    <div class="row g-1">
                                        <div class="col-6">
                                            <input type="number" id="modal_mms_freezer_meat_ctn" min="0" value="0" class="form-control form-control-sm text-center fw-bold border-purple" placeholder="Ctn" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">ctn</span>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" id="modal_mms_freezer_meat_pcs" min="0" value="0" class="form-control form-control-sm text-center border-purple" placeholder="Pcs" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">pcs</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Freezer Ice Cream -->
                            <div class="col-md-6">
                                <div class="location-cell shadow-sm">
                                    <label class="form-label small fw-extrabold text-muted text-uppercase mb-2">🍦 Freezer (Ice Cream)</label>
                                    <div class="row g-1">
                                        <div class="col-6">
                                            <input type="number" id="modal_mms_freezer_ice_cream_ctn" min="0" value="0" class="form-control form-control-sm text-center fw-bold border-purple" placeholder="Ctn" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">ctn</span>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" id="modal_mms_freezer_ice_cream_pcs" min="0" value="0" class="form-control form-control-sm text-center border-purple" placeholder="Pcs" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">pcs</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MODAL TAB: STORE AREA (1 Rack, 2 Pallets, 2 Chillers, 2 Freezers) -->
                    <div class="tab-pane fade" id="modal-sa" role="tabpanel">
                        <div class="row g-3">
                            <!-- Rack -->
                            <div class="col-md-4">
                                <div class="location-cell shadow-sm">
                                    <label class="form-label small fw-extrabold text-muted text-uppercase mb-2">🪵 Rack (Rak)</label>
                                    <div class="row g-1">
                                        <div class="col-6">
                                            <input type="number" id="modal_sa_rack_ctn" min="0" value="0" class="form-control form-control-sm text-center fw-bold border-purple" placeholder="Ctn" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">ctn</span>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" id="modal_sa_rack_pcs" min="0" value="0" class="form-control form-control-sm text-center border-purple" placeholder="Pcs" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">pcs</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Pallet 1 -->
                            <div class="col-md-4">
                                <div class="location-cell shadow-sm">
                                    <label class="form-label small fw-extrabold text-muted text-uppercase mb-2">📦 Pallet 1</label>
                                    <div class="row g-1">
                                        <div class="col-6">
                                            <input type="number" id="modal_sa_pallet_1_ctn" min="0" value="0" class="form-control form-control-sm text-center fw-bold border-purple" placeholder="Ctn" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">ctn</span>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" id="modal_sa_pallet_1_pcs" min="0" value="0" class="form-control form-control-sm text-center border-purple" placeholder="Pcs" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">pcs</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Pallet 2 -->
                            <div class="col-md-4">
                                <div class="location-cell shadow-sm">
                                    <label class="form-label small fw-extrabold text-muted text-uppercase mb-2">📦 Pallet 2</label>
                                    <div class="row g-1">
                                        <div class="col-6">
                                            <input type="number" id="modal_sa_pallet_2_ctn" min="0" value="0" class="form-control form-control-sm text-center fw-bold border-purple" placeholder="Ctn" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">ctn</span>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" id="modal_sa_pallet_2_pcs" min="0" value="0" class="form-control form-control-sm text-center border-purple" placeholder="Pcs" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">pcs</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Chiller 1 -->
                            <div class="col-md-6">
                                <div class="location-cell shadow-sm">
                                    <label class="form-label small fw-extrabold text-muted text-uppercase mb-2">❄️ Chiller 1</label>
                                    <div class="row g-1">
                                        <div class="col-6">
                                            <input type="number" id="modal_sa_chiller_1_ctn" min="0" value="0" class="form-control form-control-sm text-center fw-bold border-purple" placeholder="Ctn" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">ctn</span>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" id="modal_sa_chiller_1_pcs" min="0" value="0" class="form-control form-control-sm text-center border-purple" placeholder="Pcs" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">pcs</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Chiller 2 -->
                            <div class="col-md-6">
                                <div class="location-cell shadow-sm">
                                    <label class="form-label small fw-extrabold text-muted text-uppercase mb-2">❄️ Chiller 2</label>
                                    <div class="row g-1">
                                        <div class="col-6">
                                            <input type="number" id="modal_sa_chiller_2_ctn" min="0" value="0" class="form-control form-control-sm text-center fw-bold border-purple" placeholder="Ctn" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">ctn</span>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" id="modal_sa_chiller_2_pcs" min="0" value="0" class="form-control form-control-sm text-center border-purple" placeholder="Pcs" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">pcs</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Freezer 1 -->
                            <div class="col-md-6">
                                <div class="location-cell shadow-sm">
                                    <label class="form-label small fw-extrabold text-muted text-uppercase mb-2">⚡ Freezer 1</label>
                                    <div class="row g-1">
                                        <div class="col-6">
                                            <input type="number" id="modal_sa_freezer_1_ctn" min="0" value="0" class="form-control form-control-sm text-center fw-bold border-purple" placeholder="Ctn" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">ctn</span>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" id="modal_sa_freezer_1_pcs" min="0" value="0" class="form-control form-control-sm text-center border-purple" placeholder="Pcs" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">pcs</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Freezer 2 -->
                            <div class="col-md-6">
                                <div class="location-cell shadow-sm">
                                    <label class="form-label small fw-extrabold text-muted text-uppercase mb-2">⚡ Freezer 2</label>
                                    <div class="row g-1">
                                        <div class="col-6">
                                            <input type="number" id="modal_sa_freezer_2_ctn" min="0" value="0" class="form-control form-control-sm text-center fw-bold border-purple" placeholder="Ctn" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">ctn</span>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" id="modal_sa_freezer_2_pcs" min="0" value="0" class="form-control form-control-sm text-center border-purple" placeholder="Pcs" oninput="hitungSubTotalModal()">
                                            <span class="text-muted" style="font-size:10px; display:block; text-align:center;">pcs</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Live counted totals display in Modal -->
                <div class="mt-4 p-3 rounded bg-white shadow-sm border border-purple border-opacity-25 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small d-block" data-lang="jomcha_st_modal_expected">Jangkaan Sistem (Theoretical):</span>
                        <strong id="modalExpectedText" class="text-purple">0 pcs</strong>
                    </div>
                    <div class="text-end">
                        <span class="text-muted small d-block" data-lang="jomcha_st_modal_counted">Jumlah Fizikal Dikira (Counted):</span>
                        <strong id="modalCountedText" class="text-navy fs-5">0 pcs</strong>
                    </div>
                </div>

            </div>

            <div class="modal-footer bg-light px-4 py-3">
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal" data-lang="jomcha_st_modal_btn_cancel">Batal</button>
                <button type="button" class="btn btn-purple btn-sm text-white rounded-pill px-4" style="background: linear-gradient(135deg, #a855f7 0%, #7e22ce 100%); border:none;" onclick="simpanKiraanModal()">
                    <i class="bi bi-check-circle me-1"></i> <span data-lang="jomcha_st_modal_btn_save">Simpan Kiraan</span>
                </button>
            </div>

        </div>
    </div>
</div>

<script>
    let currentProductId = 0;
    let currentPackSize = 1;
    let currentExpected = 0;
    
    const locationFields = [
        'jcb_chiller_1_ctn', 'jcb_chiller_1_pcs',
        'jcb_chiller_2_ctn', 'jcb_chiller_2_pcs',
        'jcb_rack_ctn', 'jcb_rack_pcs',
        
        'mms_rack_ctn', 'mms_rack_pcs',
        'mms_chiller_1_ctn', 'mms_chiller_1_pcs',
        'mms_chiller_2_ctn', 'mms_chiller_2_pcs',
        'mms_freezer_meat_ctn', 'mms_freezer_meat_pcs',
        'mms_freezer_ice_cream_ctn', 'mms_freezer_ice_cream_pcs',
        
        'sa_rack_ctn', 'sa_rack_pcs',
        'sa_pallet_1_ctn', 'sa_pallet_1_pcs',
        'sa_pallet_2_ctn', 'sa_pallet_2_pcs',
        'sa_chiller_1_ctn', 'sa_chiller_1_pcs',
        'sa_chiller_2_ctn', 'sa_chiller_2_pcs',
        'sa_freezer_1_ctn', 'sa_freezer_1_pcs',
        'sa_freezer_2_ctn', 'sa_freezer_2_pcs'
    ];

    // Buka Modal & Load values
    function bukaModalKiraan(productId, productName, packSize, expected, category, customPermittedLocation = '', customPermittedSubLocation = '') {
        currentProductId = productId;
        currentPackSize = packSize;
        currentExpected = expected;

        // Set Headers
        document.getElementById('modalProductName').innerText = productName + ' (Kapasiti: ' + packSize + ')';
        
        // Translate / display expected
        const lblExpected = MMS_LANG.t('jomcha_st_modal_expected');
        document.getElementById('modalExpectedText').parentNode.firstElementChild.innerText = lblExpected;
        document.getElementById('modalExpectedText').innerText = expected + ' pcs (' + Math.floor(expected / packSize) + ' ctn, ' + (expected % packSize) + ' pcs)';
        
        // Translate / display counted label
        const lblCounted = MMS_LANG.t('jomcha_st_modal_counted');
        document.getElementById('modalCountedText').parentNode.firstElementChild.innerText = lblCounted;

        // Load Values from hidden inputs to modal inputs
        locationFields.forEach(f => {
            let hiddenVal = parseInt(document.getElementById(f + '_' + productId).value) || 0;
            document.getElementById('modal_' + f).value = hiddenVal;
        });

        // Dynamic Storage Logic: Hide/Show sub-locations based on permitted location & sub-location or category default
        let allowedStorage = [];
        let allowedAreas = [];

        if (customPermittedLocation && customPermittedLocation.trim() !== '') {
            let rawLocs = customPermittedLocation.toLowerCase().split(',').map(s => s.trim());
            rawLocs.forEach(rawLoc => {
                if (rawLoc.includes('jcb') || rawLoc.includes('jc barn') || rawLoc.includes('barn')) allowedAreas.push('jcb');
                if (rawLoc.includes('mms') || rawLoc.includes('kedai') || rawLoc.includes('jomcha')) allowedAreas.push('mms');
                if (rawLoc.includes('sa') || rawLoc.includes('store') || rawLoc.includes('store area')) allowedAreas.push('sa');
            });
        }
        if (allowedAreas.length === 0) {
            allowedAreas = ['jcb', 'mms', 'sa'];
        }

        if (customPermittedSubLocation && customPermittedSubLocation.trim() !== '') {
            allowedStorage = customPermittedSubLocation.toLowerCase().split(',').map(s => s.trim());
        } else {
            category = (category || '').toLowerCase().trim();
            if (['powder', 'uht', 'syrup', 'topping', 'packaging', 'cups', 'straws', 'pss', 'com', 'pow'].some(k => category.includes(k))) {
                allowedStorage = ['rack', 'pallet'];
            } else if (['pst', 'pasteurised', 'butter', 'cheese', 'yogurt', 'cooking', 'chilled', 'milk'].some(k => category.includes(k))) {
                allowedStorage = ['chiller'];
            } else if (['ic', 'icecream', 'freezer', 'frozen', 'beef', 'meat'].some(k => category.includes(k))) {
                allowedStorage = ['freezer'];
                allowedAreas = ['mms', 'sa'];
            } else {
                allowedStorage = ['rack', 'pallet', 'chiller', 'freezer'];
            }
        }

        locationFields.forEach(f => {
            let inputEl = document.getElementById('modal_' + f);
            if (!inputEl) return;
            
            // Find parent grid column
            let cellCol = inputEl.closest('.col-md-4') || inputEl.closest('.col-md-6');
            
            // Determine Area prefix (jcb, mms, sa)
            let fieldArea = 'jcb';
            if (f.startsWith('mms_')) fieldArea = 'mms';
            else if (f.startsWith('sa_')) fieldArea = 'sa';

            // Determine type of this field
            let fieldType = 'rack';
            if (f.includes('chiller')) fieldType = 'chiller';
            else if (f.includes('freezer')) fieldType = 'freezer';
            else if (f.includes('pallet')) fieldType = 'pallet';
            
            // Check compatibility with allowed areas and storage types
            let areaMatch = allowedAreas.includes(fieldArea);
            
            // Extract field sub-code (e.g., chiller 1, chiller 2, freezer meat, freezer ice cream, pallet 1, rack)
            let fieldSubCode = f.replace('jcb_', '').replace('mms_', '').replace('sa_', '').replace('_ctn', '').replace('_pcs', '').replace(/_/g, ' ');

            let typeMatch = allowedStorage.length === 0 || allowedStorage.some(a => {
                let cleanA = a.replace('>', ' ').replace('(', ' ').replace(')', ' ').replace('-', ' ').trim();
                return cleanA.includes(fieldArea) || 
                       cleanA.includes(fieldType) || 
                       fieldType.includes(cleanA) ||
                       cleanA.includes(fieldSubCode) ||
                       fieldSubCode.includes(cleanA) ||
                       (cleanA.includes('jcb') && fieldArea === 'jcb') ||
                       (cleanA.includes('mms') && fieldArea === 'mms') ||
                       (cleanA.includes('sa') && fieldArea === 'sa');
            });
            
            if (areaMatch && typeMatch) {
                // Compatible: show
                if (cellCol) cellCol.style.display = '';
            } else {
                // Incompatible: reset value and hide
                inputEl.value = '0';
                if (cellCol) cellCol.style.display = 'none';
            }
        });

        // Show/Hide zone tabs depending on whether they contain any visible fields
        let hasJcbVisible = false, hasMmsVisible = false, hasSaVisible = false;
        locationFields.forEach(f => {
            let el = document.getElementById('modal_' + f);
            if (el) {
                let cellCol = el.closest('.col-md-4') || el.closest('.col-md-6');
                if (cellCol && cellCol.style.display !== 'none') {
                    if (f.startsWith('jcb_')) hasJcbVisible = true;
                    if (f.startsWith('mms_')) hasMmsVisible = true;
                    if (f.startsWith('sa_')) hasSaVisible = true;
                }
            }
        });
        
        let tabJcb = document.getElementById('modal-jcb-tab');
        let tabMms = document.getElementById('modal-mms-tab');
        let tabSa = document.getElementById('modal-sa-tab');
        
        if (tabJcb) tabJcb.style.display = hasJcbVisible ? '' : 'none';
        if (tabMms) tabMms.style.display = hasMmsVisible ? '' : 'none';
        if (tabSa) tabSa.style.display = hasSaVisible ? '' : 'none';

        // Auto select first visible tab
        let firstVisibleTab = null;
        if (hasJcbVisible) firstVisibleTab = 'modal-jcb-tab';
        else if (hasMmsVisible) firstVisibleTab = 'modal-mms-tab';
        else if (hasSaVisible) firstVisibleTab = 'modal-sa-tab';
        
        if (firstVisibleTab) {
            let targetTab = new bootstrap.Tab(document.getElementById(firstVisibleTab));
            targetTab.show();
        }

        // Calculate initially
        hitungSubTotalModal();

        // Open Modal
        let myModal = new bootstrap.Modal(document.getElementById('stockTakeModal'));
        myModal.show();
    }

    // Kira live dalam modal
    function hitungSubTotalModal() {
        let totalCtn = 0;
        let totalPcs = 0;

        locationFields.forEach(f => {
            let val = parseInt(document.getElementById('modal_' + f).value) || 0;
            if (f.endsWith('_ctn')) {
                totalCtn += val;
            } else {
                totalPcs += val;
            }
        });

        let grandTotal = (totalCtn * currentPackSize) + totalPcs;
        document.getElementById('modalCountedText').innerText = grandTotal + ' pcs (' + totalCtn + ' ctn + ' + totalPcs + ' pcs)';
    }

    // Simpan dari modal ke hidden inputs utama
    function simpanKiraanModal() {
        let totalCtn = 0;
        let totalPcs = 0;

        // Copy modal inputs to hidden inputs
        locationFields.forEach(f => {
            let val = parseInt(document.getElementById('modal_' + f).value) || 0;
            document.getElementById(f + '_' + currentProductId).value = val;
            if (f.endsWith('_ctn')) {
                totalCtn += val;
            } else {
                totalPcs += val;
            }
        });

        let grandTotal = (totalCtn * currentPackSize) + totalPcs;

        // Set counted indicator
        document.getElementById('counted_' + currentProductId).value = '1';

        // Update main page table display
        document.getElementById('val_actual_' + currentProductId).innerText = grandTotal + ' pcs';
        document.getElementById('val_actual_detail_' + currentProductId).innerText = '(' + totalCtn + ' ctn + ' + totalPcs + ' pcs)';
        
        // Highlight row indicating counted
        let row = document.getElementById('val_actual_' + currentProductId).closest('tr');
        row.style.backgroundColor = 'rgba(168, 85, 247, 0.04)';

        // Close Modal
        let modalEl = document.getElementById('stockTakeModal');
        let modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) {
            modalInstance.hide();
        }
    }

    // Tapis produk (Search & Category)
    function tapisKiraan() {
        let searchInput = document.getElementById('searchStk').value.toLowerCase().trim();
        let catSelect = document.getElementById('filterCategory').value;
        let rows = document.querySelectorAll('.stk-row-item');
        
        rows.forEach(row => {
            let name = row.getAttribute('data-name');
            let category = row.getAttribute('data-category');
            
            let matchesSearch = name.includes(searchInput);
            let matchesCategory = (catSelect === '' || category === catSelect);
            
            if (matchesSearch && matchesCategory) {
                row.style.display = 'table-row';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Susun produk (Sorting)
    function susunKiraan() {
        let sortBy = document.getElementById('sortStk').value;
        let container = document.getElementById('stkContainer');
        let rows = Array.from(container.querySelectorAll('.stk-row-item'));
        
        rows.sort((a, b) => {
            if (sortBy === 'name') {
                let nameA = a.getAttribute('data-name');
                let nameB = b.getAttribute('data-name');
                return nameA.localeCompare(nameB);
            } else if (sortBy === 'category') {
                let catA = a.getAttribute('data-category');
                let catB = b.getAttribute('data-category');
                if (catA === catB) {
                    return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
                }
                return catA.localeCompare(catB);
            } else {
                // default: sort by baki desc
                let bakiA = parseInt(a.getAttribute('data-baki')) || 0;
                let bakiB = parseInt(b.getAttribute('data-baki')) || 0;
                return bakiB - bakiA;
            }
        });
        
        // Re-append sorted rows to container
        rows.forEach(row => container.appendChild(row));
    }

    // Sahkan hantar borang kiraan
    function sahkanKiraan(e) {
        e.preventDefault();
        
        let mandatoryRows = document.querySelectorAll('.stk-row-item[data-mandatory="true"]');
        let uncountedMandatory = 0;
        
        mandatoryRows.forEach(row => {
            let pid = row.getAttribute('data-id');
            let counted = document.getElementById('counted_' + pid).value;
            if (counted !== '1') {
                uncountedMandatory++;
            }
        });

        if (uncountedMandatory > 0) {
            const isMalay = (MMS_LANG.current() === 'ms');
            const alertMsg = isMalay 
                ? "Amaran: Terdapat " + uncountedMandatory + " produk wajib yang belum anda audit kuantiti stoknya! Sila klik butang 'Kira' bagi produk bertanda teoretikal."
                : "Warning: There are " + uncountedMandatory + " mandatory products that you have not audited! Please click the 'Count' button for those products.";
            alert(alertMsg);
            return false;
        }

        const isMalay = (MMS_LANG.current() === 'ms');
        const confirmMsg = isMalay
            ? "Sahkan penyerahan semua kiraan stok Jomcha? Tindakan ini akan mengemaskini baki stok sistem."
            : "Confirm submission of all Jomcha stock counts? This will update system inventory records.";
            
        if (confirm(confirmMsg)) {
            e.target.submit();
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>
