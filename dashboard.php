<?php
// dashboard.php
// MMS WMS System | Moo Moo Supplies
// Premium Executive Dashboard with Live Analytics, Capacity Heatmaps, and Real-Time Logs

$page_title = 'Executive WMS Analytics | Moo Moo Supplies';
$hide_navbar = true; // Disable default top navbar for immersive dark theme sidebar experience
require_once 'config/db.php';
require_once 'includes/header.php';
?>
<script>
    const savedTheme = localStorage.getItem('dashboard-theme') || 'dark';
    document.body.setAttribute('data-theme', savedTheme);
</script>
<?php
// Authenticated session check
$username = $_SESSION['username'] ?? 'User';
$full_name = $_SESSION['full_name'] ?? 'Executive';
$role = $_SESSION['role'] ?? '';

// Current Month/Year details
$current_period_lbl = date('F Y');

try {
    // ----------------------------------------------------
    // 1. HEADER ALERTS DATAFETCH
    // ----------------------------------------------------
    
    // Expiry Warnings: Batches expiring within 90 days
    $expiry_count = $pdo->query("
        SELECT COUNT(*) 
        FROM inventory_batches 
        WHERE qty_on_hand > 0 
          AND expiry_date IS NOT NULL 
          AND DATEDIFF(expiry_date, NOW()) <= 90
    ")->fetchColumn() ?: 0;

    // Low Stock SKUs Count: Active products with total stock < 50 ctn (but > 0)
    $low_stock_skus_count = $pdo->query("
        SELECT COUNT(*) FROM (
            SELECT p.id, SUM(COALESCE(b.qty_on_hand, 0)) as total_qty
            FROM products p
            LEFT JOIN inventory_batches b ON p.id = b.product_id AND b.location_status = 'Warehouse'
            WHERE p.is_active = 1
            GROUP BY p.id
            HAVING total_qty > 0 AND total_qty < 50
        ) as low_stock_tally
    ")->fetchColumn() ?: 0;

    // Pending Damage Reports
    $pending_damage_count = $pdo->query("
        SELECT COUNT(*) 
        FROM spoilage_logs 
        WHERE claim_status = 'Pending'
    ")->fetchColumn() ?: 0;

    // ----------------------------------------------------
    // 2. TOP ROW CARDS DATAFETCH
    // ----------------------------------------------------

    // PSS Delivery Progress (Logistics DB Switch Context)
    $pss_schools = 0;
    $pss_delivered = 0;
    $pss_cartons = 0;
    $pss_progress_percent = 0;
    $latest_co_no = "N/A";

    try {
        $pdo->exec("USE susumura_mms_logistik");
        $pss_schools = $pdo->query("SELECT COUNT(*) FROM mms_logistik")->fetchColumn() ?: 0;
        $pss_delivered = $pdo->query("SELECT COUNT(*) FROM mms_logistik WHERE isDelivered = 1")->fetchColumn() ?: 0;
        $pss_cartons = $pdo->query("SELECT SUM(totalCartons) FROM mms_logistik")->fetchColumn() ?: 0;
        $pss_progress_percent = ($pss_schools > 0) ? round(($pss_delivered / $pss_schools) * 100) : 0;
        
        $co_stmt = $pdo->query("SELECT co_no FROM mms_logistik WHERE co_no IS NOT NULL AND co_no != '' ORDER BY date DESC LIMIT 1");
        $latest_co_no = $co_stmt ? ($co_stmt->fetchColumn() ?: "N/A") : "N/A";
    } catch (Exception $e) {
        // Fallback
    } finally {
        $pdo->exec("USE " . DB_NAME);
    }

    // Out of Stock SKUs List
    $out_of_stock_list = $pdo->query("
        SELECT p.name, p.category, SUM(COALESCE(b.qty_on_hand, 0)) as total_qty
        FROM products p
        LEFT JOIN inventory_batches b ON p.id = b.product_id AND b.location_status = 'Warehouse'
        WHERE p.is_active = 1
        GROUP BY p.id
        HAVING total_qty = 0
        ORDER BY p.name ASC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Warehouse Capacity Utilization
    $total_slots = $pdo->query("SELECT COUNT(*) FROM warehouse_slots")->fetchColumn() ?: 151;
    $occupied_slots = $pdo->query("
        SELECT COUNT(DISTINCT sa.location_code) 
        FROM slot_assignments sa
        JOIN inventory_batches b ON sa.batch_id = b.id
        WHERE b.qty_on_hand > 0
    ")->fetchColumn() ?: 0;
    $capacity_percent = round(($occupied_slots / $total_slots) * 100);

    // Low Stock Alert Count (Total count including out-of-stock)
    $all_low_skus_count = $pdo->query("
        SELECT COUNT(*) FROM (
            SELECT p.id, SUM(COALESCE(b.qty_on_hand, 0)) as total_qty
            FROM products p
            LEFT JOIN inventory_batches b ON p.id = b.product_id AND b.location_status = 'Warehouse'
            WHERE p.is_active = 1
            GROUP BY p.id
            HAVING total_qty < 50
        ) as low_stock_all
    ")->fetchColumn() ?: 0;

    // ----------------------------------------------------
    // 3. WAREHOUSE GRID MINI-MAP DATAFETCH
    // ----------------------------------------------------
    // Dapatkan semua slot fizikal gudang
    $slots_stmt = $pdo->query("SELECT location_code, zone, lane, row_num FROM warehouse_slots ORDER BY location_code ASC");
    $slots = $slots_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Dapatkan tugasan aktif untuk mendapatkan kuantiti & kapasiti pallet
    $assign_stmt = $pdo->query("
        SELECT 
            sa.location_code,
            b.qty_on_hand AS quantity,
            p.pallet_capacity
        FROM slot_assignments sa
        JOIN inventory_batches b ON sa.batch_id = b.id
        JOIN products p ON b.product_id = p.id
        WHERE b.qty_on_hand > 0
    ");
    $assignments = $assign_stmt->fetchAll(PDO::FETCH_ASSOC);

    $grouped = [];
    foreach ($assignments as $a) {
        $grouped[$a['location_code']][] = $a;
    }

    foreach ($slots as &$slot) {
        $items = $grouped[$slot['location_code']] ?? [];
        $totalCtn = 0;
        $capacity = 0;
        foreach ($items as $item) {
            $totalCtn += (int)$item['quantity'];
            $capacity = max($capacity, (int)$item['pallet_capacity']);
        }
        
        $status = 'slot-empty';
        if ($totalCtn > 0) {
            $status = ($totalCtn >= $capacity) ? 'slot-full' : 'slot-medium';
        }
        $slot['status'] = $status;
    }

    // Susun slot mengikut zon/lane/row_num untuk dipetakan ke dalam blok
    $mini_blocks = [
        'PSS_ABC' => array_filter($slots, function($s) { return $s['zone'] === 'PSS' && in_array($s['lane'], ['A','B','C']); }),
        'PSS_DEF' => array_filter($slots, function($s) { return $s['zone'] === 'PSS' && in_array($s['lane'], ['D','E','F']); }),
        'COM_AB'  => array_filter($slots, function($s) { return $s['zone'] === 'COM' && in_array($s['lane'], ['A','B']); }),
        'POW_ABC' => array_filter($slots, function($s) { return $s['zone'] === 'POW'; }),
        'COM_CD'  => array_filter($slots, function($s) { return $s['zone'] === 'COM' && in_array($s['lane'], ['C','D']); }),
        'COM_EF'  => array_filter($slots, function($s) { return $s['zone'] === 'COM' && in_array($s['lane'], ['E','F']); })
    ];

    foreach ($mini_blocks as $key => &$block_slots) {
        usort($block_slots, function($a, $b) {
            $row_diff = (int)$a['row_num'] - (int)$b['row_num'];
            if ($row_diff !== 0) return $row_diff;
            return strcmp($a['lane'], $b['lane']);
        });
    }

    // ----------------------------------------------------
    // 4. TRENDS & GRAPHS DATAFETCH
    // ----------------------------------------------------
    // PSS Inbound (category = 'PSS')
    $daily_inbound_pss = $pdo->query("
        SELECT DATE(il.received_date) as txn_date, SUM(ii.qty_received) as qty
        FROM inbound_items ii
        JOIN inbound_logs il ON ii.inbound_id = il.id
        WHERE il.received_date >= DATE_SUB(CURDATE(), INTERVAL 15 DAY)
          AND il.category = 'PSS'
        GROUP BY DATE(il.received_date)
    ")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    // Commercial Inbound (category IN ('UHT', 'PST'))
    $daily_inbound_comm = $pdo->query("
        SELECT DATE(il.received_date) as txn_date, SUM(ii.qty_received) as qty
        FROM inbound_items ii
        JOIN inbound_logs il ON ii.inbound_id = il.id
        WHERE il.received_date >= DATE_SUB(CURDATE(), INTERVAL 15 DAY)
          AND il.category IN ('UHT', 'PST')
        GROUP BY DATE(il.received_date)
    ")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    // Outbound Commercial
    $daily_outbound_comm = $pdo->query("
        SELECT DATE(l.date) as txn_date, SUM(i.qty) as qty
        FROM outbound_logs l
        LEFT JOIN outbound_items i ON l.id = i.outbound_id
        WHERE l.date >= DATE_SUB(CURDATE(), INTERVAL 15 DAY)
        GROUP BY DATE(l.date)
    ")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    // Outbound PSS School DOs
    $daily_outbound_pss = $pdo->query("
        SELECT DATE(d.delivery_date) as txn_date, SUM(di.qty_cartons) as qty
        FROM deliveries_pss d
        LEFT JOIN delivery_items_pss di ON d.id = di.delivery_id
        WHERE d.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 15 DAY)
        GROUP BY DATE(d.delivery_date)
    ")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    // Aggregate trends into last 15 days list
    $dates = [];
    $pss_inbound_series = [];
    $pss_outbound_series = [];
    $comm_inbound_series = [];
    $comm_outbound_series = [];
    for ($i = 14; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $dates[] = date('d M', strtotime($d));
        
        $pss_inbound_series[] = (int)($daily_inbound_pss[$d] ?? 0);
        $pss_outbound_series[] = (int)($daily_outbound_pss[$d] ?? 0);
        $comm_inbound_series[] = (int)($daily_inbound_comm[$d] ?? 0);
        $comm_outbound_series[] = (int)($daily_outbound_comm[$d] ?? 0);
    }

    // ----------------------------------------------------
    // 5. RECENT ACTIVITY DATAFETCH
    // ----------------------------------------------------
    $recent_activities = $pdo->query("
        SELECT created_at, username, action, target_table, record_id, details
        FROM system_logs
        ORDER BY created_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // ----------------------------------------------------
    // 6. PENDING DAMAGE RECORDS DATAFETCH
    // ----------------------------------------------------
    $pending_damage = $pdo->query("
        SELECT sl.reported_at, p.name as product_name, b.batch_no, sl.qty, sl.reason, sl.claim_status
        FROM spoilage_logs sl
        JOIN inventory_batches b ON sl.batch_id = b.id
        JOIN products p ON b.product_id = p.id
        WHERE sl.claim_status = 'Pending'
        ORDER BY sl.reported_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $pending_jomcha = $pdo->query("
        SELECT r.id, r.request_date, r.requested_by, r.status,
               (SELECT COUNT(*) FROM jomcha_request_items WHERE request_id = r.id) as item_count,
               (SELECT SUM(qty_requested) FROM jomcha_request_items WHERE request_id = r.id) as total_qty
        FROM jomcha_requests r
        WHERE r.status = 'Pending'
        ORDER BY r.request_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $pending_jomcha_count = count($pending_jomcha);

    // ----------------------------------------------------
    // 6c. EXPIRY RISK MONITOR (FEFO) DATAFETCH
    // ----------------------------------------------------
    $expiry_batches = $pdo->query("
        SELECT ib.id, ib.batch_no, p.name as product_name, ib.qty_on_hand, ib.expiry_date,
               DATEDIFF(ib.expiry_date, CURDATE()) as days_left
        FROM inventory_batches ib
        JOIN products p ON ib.product_id = p.id
        WHERE ib.qty_on_hand > 0 AND ib.expiry_date IS NOT NULL
          AND DATEDIFF(ib.expiry_date, CURDATE()) <= 90
        ORDER BY ib.expiry_date ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    $expiry_risk_count = count($expiry_batches);

    // ----------------------------------------------------
    // 7. PALLET MONITOR DATAFETCH
    // ----------------------------------------------------
    $pallet_types = $pdo->query("SELECT * FROM pallet_types ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $pallet_summary = [];
    foreach ($pallet_types as $pt) {
        $code = $pt['code'];
        $name = $pt['name'];

        $stmtIn = $pdo->prepare("SELECT SUM(qty) as total FROM pallet_ledger WHERE pallet_code = ? AND transaction_type = 'IN'");
        $stmtIn->execute([$code]);
        $total_in = (int)($stmtIn->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $stmtOut = $pdo->prepare("SELECT SUM(qty) as total FROM pallet_ledger WHERE pallet_code = ? AND transaction_type = 'OUT'");
        $stmtOut->execute([$code]);
        $total_out = (int)($stmtOut->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $stmtNet = $pdo->prepare("
            SELECT SUM(CASE 
                WHEN transaction_type = 'IN' THEN qty 
                WHEN transaction_type = 'OUT' THEN -qty 
                ELSE qty 
            END) as net 
            FROM pallet_ledger 
            WHERE pallet_code = ?
        ");
        $stmtNet->execute([$code]);
        $net_balance = (int)($stmtNet->fetch(PDO::FETCH_ASSOC)['net'] ?? 0);

        $stmtLoaded = $pdo->prepare("SELECT COUNT(*) as loaded FROM inventory_batches WHERE qty_on_hand > 0 AND pallet_type = ?");
        $stmtLoaded->execute([$name]);
        $loaded_pallets = (int)($stmtLoaded->fetch(PDO::FETCH_ASSOC)['loaded'] ?? 0);
        $empty_pallets = max(0, $net_balance - $loaded_pallets);

        $pallet_summary[] = [
            'name' => $name,
            'code' => $code,
            'total' => $net_balance,
            'loaded' => $loaded_pallets,
            'empty' => $empty_pallets,
            'in' => $total_in,
            'out' => $total_out
        ];
    }

} catch (Exception $e) {
    // Graceful error logging
    error_log("Dashboard query failed: " . $e->getMessage());
}
?>

<!-- Custom Premium Dark Theme Styling -->
<style>
    /* Dynamic Theme Colors */
    body[data-theme="dark"] {
        --bg-color: #0b0f19;
        --card-bg: rgba(22, 29, 48, 0.7);
        --text-main: #f1f5f9;
        --text-muted: #94a3b8;
        --border-color: rgba(255, 255, 255, 0.05);
        --sidebar-bg: #111827;
        --sidebar-link-active: rgba(56, 189, 248, 0.1);
        --sidebar-link-color: #9ca3af;
        --ticker-bg: rgba(30, 41, 59, 0.5);
        --table-header-bg: rgba(15, 23, 42, 0.8);
        --table-row-hover: rgba(255, 255, 255, 0.02);
        --mini-container-bg: rgba(15, 23, 42, 0.2);
        --card-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
    }
    body[data-theme="light"] {
        --bg-color: #f8fafc;
        --card-bg: rgba(255, 255, 255, 0.9);
        --text-main: #0f172a;
        --text-muted: #475569;
        --border-color: rgba(0, 0, 0, 0.08);
        --sidebar-bg: #1e293b;
        --sidebar-link-active: rgba(56, 189, 248, 0.15);
        --sidebar-link-color: #cbd5e1;
        --ticker-bg: rgba(226, 232, 240, 0.8);
        --table-header-bg: rgba(241, 245, 249, 0.9);
        --table-row-hover: rgba(0, 0, 0, 0.02);
        --mini-container-bg: rgba(241, 245, 249, 0.8);
        --card-shadow: 0 8px 24px 0 rgba(148, 163, 184, 0.1);
    }

    body {
        background-color: var(--bg-color) !important;
        color: var(--text-main) !important;
        font-family: 'Plus Jakarta Sans', sans-serif !important;
        transition: background-color 0.3s ease, color 0.3s ease;
    }
    .main-content-area {
        margin-left: 260px;
        padding: 30px;
        transition: margin 0.3s ease;
    }
    
    /* Left Sidebar Styling */
    .sidebar-dashboard {
        position: fixed;
        top: 0; left: 0; bottom: 0;
        width: 260px;
        background: var(--sidebar-bg);
        border-right: 1px solid rgba(255, 255, 255, 0.05);
        padding: 20px 15px;
        display: flex;
        flex-direction: column;
        z-index: 1040;
        transition: transform 0.3s ease, background 0.3s ease;
    }
    .brand-logo-section {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
    }
    .brand-logo-section img {
        height: 38px;
        border-radius: 8px;
        border: 1px solid rgba(255,255,255,0.15);
    }
    .brand-name {
        font-weight: 800;
        font-size: 1.25rem;
        letter-spacing: 0.5px;
        color: #38bdf8;
    }
    .sidebar-menu {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex-grow: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding-right: 2px;
    }
    /* Hide scrollbar for sidebar menu */
    .sidebar-menu::-webkit-scrollbar {
        width: 3px;
    }
    .sidebar-menu::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 4px;
    }
    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        border-radius: 12px;
        color: var(--sidebar-link-color);
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
        font-size: 0.9rem;
    }
    .sidebar-link:hover {
        background: rgba(255, 255, 255, 0.03);
        color: #f3f4f6;
    }
    .sidebar-link.active {
        background: var(--sidebar-link-active);
        color: #38bdf8;
        border: 1px solid rgba(56, 189, 248, 0.25);
    }
    .sidebar-link i {
        font-size: 1.1rem;
    }
    .sidebar-footer {
        padding-top: 15px;
        margin-top: auto;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        font-size: 0.78rem;
        color: #94a3b8;
        text-align: center;
    }

    /* Top Alerts Ticker styling */
    .alerts-ticker-container {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        background: var(--ticker-bg);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 12px 20px;
        margin-bottom: 30px;
        transition: background 0.3s ease, border-color 0.3s ease;
    }
    .ticker-header {
        font-weight: 800;
        color: var(--text-main);
        font-size: 1.1rem;
    }
    .ticker-sub {
        font-size: 0.82rem;
        color: var(--text-muted);
        margin-right: auto;
    }
    .alert-pill {
        padding: 6px 14px;
        border-radius: 30px;
        font-weight: 700;
        font-size: 0.76rem;
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: opacity 0.2s;
    }
    .alert-pill:hover { opacity: 0.85; }
    .alert-pill-warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.25); }
    .alert-pill-danger { background: rgba(244, 63, 94, 0.15); color: #f43f5e; border: 1px solid rgba(244, 63, 94, 0.25); }
    .alert-pill-info { background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.25); }

    /* Glassmorphism Cards */
    .glass-card {
        background: var(--card-bg);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 22px;
        box-shadow: var(--card-shadow);
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.2s ease, border-color 0.2s ease, background 0.3s ease, box-shadow 0.3s ease;
    }
    .glass-card:hover {
        transform: translateY(-4px);
        border-color: rgba(56, 189, 248, 0.25);
    }
    .card-title-dashboard {
        font-size: 0.78rem;
        text-transform: uppercase;
        font-weight: 800;
        letter-spacing: 1px;
        color: var(--text-muted);
        margin-bottom: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: color 0.3s ease;
    }
    .card-title-dashboard i {
        font-size: 1.1rem;
        color: #38bdf8;
    }
    .card-value-dashboard {
        font-size: 1.8rem;
        font-weight: 800;
        color: var(--text-main);
        line-height: 1.1;
        transition: color 0.3s ease;
    }
    .card-subtext-dashboard {
        font-size: 0.78rem;
        color: var(--text-muted);
        margin-top: 8px;
        transition: color 0.3s ease;
    }
    
    /* Sparklines Canvas wrapper */
    .sparkline-container {
        width: 100%;
        height: 35px;
        margin-top: 15px;
    }

    /* Table Design in Dark Mode */
    .table-dark-custom {
        width: 100%;
        border-collapse: collapse;
        color: var(--text-main);
        font-size: 0.8rem;
        transition: color 0.3s ease;
    }
    .table-dark-custom th {
        background: var(--table-header-bg);
        color: var(--text-muted);
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 12px;
        border-bottom: 2px solid var(--border-color);
        transition: background 0.3s ease, color 0.3s ease, border-color 0.3s ease;
    }
    .table-dark-custom td {
        padding: 12px;
        border-bottom: 1px solid var(--border-color);
        transition: border-color 0.3s ease;
    }
    .table-dark-custom tr:hover td {
        background: var(--table-row-hover);
        transition: background 0.2s ease;
    }
    .btn-theme-outline {
        border-color: var(--border-color) !important;
        color: var(--text-main) !important;
        background: transparent;
        transition: all 0.2s ease;
    }
    .btn-theme-outline:hover {
        background: var(--table-row-hover);
        border-color: var(--text-muted) !important;
        color: var(--text-main) !important;
    }
    
    /* Pallet Sub-Cards */
    .pallet-sub-card {
        background: rgba(15, 23, 42, 0.4);
        border: 1px solid var(--border-color);
        transition: background 0.3s ease, border-color 0.3s ease;
    }
    body[data-theme="light"] .pallet-sub-card {
        background: rgba(0, 0, 0, 0.03);
    }
    
    .pallet-card-title {
        color: var(--text-main);
        transition: color 0.3s ease;
    }
    .pallet-card-value-muted {
        color: var(--text-muted);
        transition: color 0.3s ease;
    }
    .pallet-card-total-inout {
        color: var(--text-muted);
        transition: color 0.3s ease;
        border-top: 1px solid var(--border-color) !important;
    }

    /* Actual Layout Plan Status (Scaled down miniature map) */
    :root {
        --mini-slot-size: 6px;
    }
    .mini-warehouse-container {
        width: 100%;
        overflow-x: auto;
        padding: 5px 0;
        background: var(--mini-container-bg);
        border-radius: 8px;
        border: 1px solid var(--border-color);
        transition: background 0.3s ease, border-color 0.3s ease;
    }
    .mini-warehouse-floor {
        display: flex;
        gap: 6px;
        align-items: flex-start;
        justify-content: center;
        width: max-content;
        margin: 0 auto;
    }
    .mini-layout-col {
        display: flex;
        flex-direction: column;
        gap: 1px;
    }
    .mini-block-title {
        font-size: 0.44rem;
        font-weight: 800;
        color: #64748b;
        text-align: center;
        margin-bottom: 2px;
        white-space: nowrap;
        transform: scale(0.9);
    }
    .mini-slot-grid {
        display: grid;
        gap: 1px;
    }
    .mini-grid-3-col { grid-template-columns: repeat(3, var(--mini-slot-size)); }
    .mini-grid-2-col { grid-template-columns: repeat(2, var(--mini-slot-size)); }
    
    .mini-slot {
        width: var(--mini-slot-size);
        height: var(--mini-slot-size);
        border-radius: 1px;
        transition: transform 0.1s ease;
    }
    .mini-slot:hover {
        transform: scale(1.5);
        z-index: 10;
    }
    .mini-slot.slot-empty { background: #ef4444; }
    .mini-slot.slot-medium { background: #3b82f6; }
    .mini-slot.slot-full { background: #10b981; }
    
    .mini-wall-divider {
        width: 2px;
        background-color: #334155;
        height: 120px;
        align-self: stretch;
        margin: 0 2px;
    }
    .mini-offset-cd { margin-top: 15px; }
    .mini-offset-ef { margin-top: 48px; }

    /* Custom Responsive Menu for Mobile Screen sizes */
    .mobile-menu-bar {
        display: none;
        background: #111827;
        padding: 15px 20px;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    @media (max-width: 991px) {
        .sidebar-dashboard {
            transform: translateX(-100%);
        }
        .main-content-area {
            margin-left: 0;
            padding: 20px 15px;
        }
        .mobile-menu-bar {
            display: flex;
        }
        .sidebar-dashboard.show {
            transform: translateX(0);
        }
    }
</style>

<!-- Mobile Menu Header -->
<div class="mobile-menu-bar">
    <div class="d-flex align-items-center gap-2">
        <img src="img/logo.png" alt="Logo" style="height: 30px; border-radius: 6px;">
        <span class="fw-800 text-info">MMS HUB</span>
    </div>
    <button class="btn btn-outline-light btn-sm px-3" onclick="toggleSidebarMenu()">
        <i class="bi bi-list fs-5"></i>
    </button>
</div>

<!-- Left Sidebar Navigation -->
<div class="sidebar-dashboard" id="sidebarMenu">
    <div class="brand-logo-section">
        <img src="img/logo.png" alt="MMS Logo">
        <span class="brand-name">MMS WMS</span>
    </div>
    
    <div class="sidebar-menu">
        <a href="dashboard.php" class="sidebar-link active">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>
        <a href="inventory_report.php" class="sidebar-link">
            <i class="bi bi-box-seam"></i>
            <span>Stock Management</span>
        </a>
        <a href="master_hubpss.php" class="sidebar-link">
            <i class="bi bi-geo-alt"></i>
            <span>Delivery Tracking</span>
        </a>
        <a href="receiving.php" class="sidebar-link">
            <i class="bi bi-box-arrow-in-down"></i>
            <span>Inbound Ops</span>
        </a>
        <a href="commercial_outbound.php" class="sidebar-link">
            <i class="bi bi-box-arrow-up"></i>
            <span>Outbound Ops</span>
        </a>
        <a href="jomcha_request_stock.php" class="sidebar-link">
            <i class="bi bi-cart"></i>
            <span>Jomcha Requisitions</span>
        </a>
        <a href="spoilage_report.php" class="sidebar-link">
            <i class="bi bi-recycle"></i>
            <span>Damage Records</span>
        </a>
        <a href="warehouse_layout.php" class="sidebar-link">
            <i class="bi bi-map"></i>
            <span>Warehouse Map</span>
        </a>
        <a href="pallet_management.php" class="sidebar-link">
            <i class="bi bi-grid-3x3-gap"></i>
            <span>Pallet Monitor</span>
        </a>
        <a href="user_management.php" class="sidebar-link">
            <i class="bi bi-people"></i>
            <span>Settings</span>
        </a>
    </div>

    <div class="sidebar-footer">
        <div>MMS Warehouse System</div>
        <div class="mt-1">Logged: <strong><?= htmlspecialchars($username) ?></strong></div>
        <div class="mt-2"><a href="logout.php" class="text-danger text-decoration-none fw-bold"><i class="bi bi-box-arrow-right"></i> Log Keluar</a></div>
    </div>
</div>

<!-- Main Executive Dashboard Content Grid -->
<div class="main-content-area">
    
    <!-- Title & Navigation Top Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-800 mb-1">Inventory Management Overview</h2>
            <p class="text-muted small mb-0">Welcome back, <?= htmlspecialchars($full_name) ?> &mdash; Data gathered for <strong class="text-info"><?= $current_period_lbl ?></strong></p>
        </div>
        <div class="d-flex gap-2">
            <button id="themeToggleBtn" onclick="toggleTheme()" class="btn btn-theme-outline btn-sm fw-bold px-3 py-2">
                <i class="bi bi-sun-fill" id="themeIcon"></i> <span id="themeToggleTxt">Light Mode</span>
            </button>
            <a href="index.php" class="btn btn-theme-outline btn-sm fw-bold px-3 py-2"><i class="bi bi-columns-gap me-1"></i> Classic Hub</a>
            <button onclick="window.location.reload();" class="btn btn-info btn-sm fw-bold text-white px-3"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
        </div>
    </div>

    <!-- Alert Notifications bar (Mockup layout styling) -->
    <div class="alerts-ticker-container">
        <span class="ticker-header"><i class="bi bi-bell-fill text-warning me-1"></i> Active System Alerts</span>
        <span class="ticker-sub">Auto-calculated from live inventory logs</span>
        
        <a href="inventory_report.php" class="alert-pill alert-pill-warning">
            ⚠️ [Expiry Warnings | <?= $expiry_count ?> Batches]
        </a>
        <a href="inventory_report.php" class="alert-pill alert-pill-danger">
            📉 [Low Stock | <?= $low_stock_skus_count ?> SKUs]
        </a>
        <a href="spoilage_report.php" class="alert-pill alert-pill-info">
            🔵 [Pending Damage | <?= $pending_damage_count ?> Reports]
        </a>
        <a href="jomcha_request_stock.php" class="alert-pill alert-pill-info" style="background: rgba(139, 92, 246, 0.15); color: #a78bfa; border: 1px solid rgba(139, 92, 246, 0.25);">
            🛒 [Pending Jomcha | <?= $pending_jomcha_count ?> Requests]
        </a>
    </div>

    <!-- KPI Metrics Row (Top Row) -->
    <div class="row g-4 mb-4">
        
        <!-- PSS Delivery Progress -->
        <div class="col-xl-3 col-md-6">
            <div class="glass-card">
                <div>
                    <div class="card-title-dashboard">
                        <span>PSS Delivery Progress</span>
                        <i class="bi bi-truck text-info"></i>
                    </div>
                    <div class="card-value-dashboard"><?= $pss_progress_percent ?>%</div>
                    <div class="card-subtext-dashboard">
                        <?= $pss_delivered ?>/<?= $pss_schools ?> Hub &amp; Schools (Delivered)<br>
                        <strong>Latest:</strong> <?= htmlspecialchars($latest_co_no) ?>
                    </div>
                </div>
                <div class="sparkline-container">
                    <canvas id="sparkline-pss"></canvas>
                </div>
            </div>
        </div>

        <!-- Out of Stock list summary -->
        <div class="col-xl-3 col-md-6">
            <div class="glass-card" style="justify-content: flex-start;">
                <div class="card-title-dashboard w-100">
                    <span>Out Of Stock SKU List</span>
                    <i class="bi bi-slash-circle text-danger"></i>
                </div>
                <div style="flex-grow: 1; max-height: 120px; overflow-y: auto; padding-right: 4px;" class="mt-2 w-100">
                    <?php if (empty($out_of_stock_list)): ?>
                        <div class="text-success small fw-bold py-2"><i class="bi bi-check-circle-fill"></i> All items currently in stock!</div>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0 small">
                            <?php foreach ($out_of_stock_list as $item): ?>
                                <li class="text-truncate py-1 border-bottom border-secondary border-opacity-10">
                                    🔴 <?= htmlspecialchars($item['name']) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Warehouse Capacity utilize -->
        <div class="col-xl-3 col-md-6">
            <div class="glass-card">
                <div>
                    <div class="card-title-dashboard">
                        <span>Warehouse Capacity Util.</span>
                        <i class="bi bi-grid-1x2 text-success"></i>
                    </div>
                    <div class="card-value-dashboard"><?= $capacity_percent ?>% full</div>
                    <div class="card-subtext-dashboard">
                        Occupied: <strong><?= $occupied_slots ?></strong> / <?= $total_slots ?> total slots assigned.
                    </div>
                </div>
                <div class="sparkline-container">
                    <canvas id="sparkline-capacity"></canvas>
                </div>
            </div>
        </div>

        <!-- Low Stock Alerts count -->
        <div class="col-xl-3 col-md-6">
            <div class="glass-card">
                <div>
                    <div class="card-title-dashboard">
                        <span>Low Stock Alerts</span>
                        <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                    </div>
                    <div class="card-value-dashboard"><?= $all_low_skus_count ?> SKUs</div>
                    <div class="card-subtext-dashboard">
                        Total items under safety buffer limit (<50 ctn).
                    </div>
                </div>
                <div class="sparkline-container">
                    <canvas id="sparkline-lowstock"></canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- SECOND ROW: Pending Jomcha Requisitions -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="glass-card p-4" style="border-left: 4px solid #8b5cf6 !important;">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="card-title-dashboard m-0">
                        <span style="color: #8b5cf6; font-size: 0.95rem; font-weight: 800;"><i class="bi bi-cart-fill me-2"></i> Pending Jomcha Requisitions</span>
                    </div>
                    <span class="badge px-3 py-1 rounded-pill text-white" style="background-color: #8b5cf6; font-size: 0.75rem; font-weight: 700;"><?= $pending_jomcha_count ?> Pending</span>
                </div>
                
                <div class="table-responsive" style="border: none; max-height: 250px; overflow-y: auto;">
                    <table class="table-dark-custom">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Request Date</th>
                                <th>Requested By</th>
                                <th class="text-center">Item Count</th>
                                <th class="text-center">Total Qty</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_jomcha)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">No pending Jomcha requisitions found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($pending_jomcha as $req): ?>
                                    <tr>
                                        <td class="fw-bold">#<?= $req['id'] ?></td>
                                        <td><?= date('d/m/Y', strtotime($req['request_date'])) ?></td>
                                        <td><span class="badge bg-secondary bg-opacity-10 px-2 py-1" style="font-size:0.75rem; font-weight:600; color: var(--text-main) !important; border: 1px solid var(--border-color);"><?= htmlspecialchars($req['requested_by']) ?></span></td>
                                        <td class="text-center fw-semibold"><?= number_format($req['item_count']) ?> product<?= $req['item_count'] > 1 ? 's' : '' ?></td>
                                        <td class="text-center text-primary fw-bold"><?= number_format($req['total_qty']) ?> ctn</td>
                                        <td class="text-center"><span class="badge bg-warning text-dark px-3 py-1 rounded-pill">Pending</span></td>
                                        <td class="text-end">
                                            <a href="jomcha_request_stock.php?review=<?= $req['id'] ?>" class="btn btn-sm text-white px-3 py-1 rounded-pill fw-bold" style="background-color: #8b5cf6; border: none; font-size:0.75rem; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.85';" onmouseout="this.style.opacity='1';">Review Request</a>
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

    <!-- THIRD ROW: Expiry Risk Monitor -->
    <?php
    if (!function_exists('get_expiry_risk_badge')) {
        function get_expiry_risk_badge($days) {
            if ($days <= 30) {
                return '<span class="badge bg-danger-subtle text-danger px-2 py-1 rounded" style="font-size: 0.7rem; font-weight:700;">CRIT</span>';
            } elseif ($days <= 90) {
                return '<span class="badge bg-warning-subtle text-warning px-2 py-1 rounded" style="font-size: 0.7rem; font-weight:700;">WARN</span>';
            } else {
                return '<span class="badge bg-success-subtle text-success px-2 py-1 rounded" style="font-size: 0.7rem; font-weight:700;">SAFE</span>';
            }
        }
    }
    if (!function_exists('get_expiry_days_style')) {
        function get_expiry_days_style($days) {
            if ($days <= 30) {
                return 'color: #ef4444; font-weight: 700;';
            } elseif ($days <= 90) {
                return 'color: #f59e0b; font-weight: 700;';
            } else {
                return 'color: #10b981; font-weight: 700;';
            }
        }
    }
    ?>
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="glass-card p-4" style="border-left: 4px solid #eab308 !important;">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="card-title-dashboard m-0">
                        <span style="color: #eab308; font-size: 0.95rem; font-weight: 800;"><i class="bi bi-exclamation-triangle-fill me-2"></i> Expiry Risk Monitor (FEFO)</span>
                    </div>
                    <span class="badge px-3 py-1 rounded-pill text-white" style="background-color: #eab308; font-size: 0.75rem; font-weight: 700;"><?= $expiry_risk_count ?> Batch<?= $expiry_risk_count > 1 ? 's' : '' ?></span>
                </div>
                
                <div class="table-responsive" style="border: none; max-height: 250px; overflow-y: auto;">
                    <table class="table-dark-custom">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Batch No</th>
                                <th class="text-center">Balance</th>
                                <th class="text-center">Days Left</th>
                                <th class="text-end">Risk</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($expiry_batches)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No active inventory batches found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($expiry_batches as $batch): ?>
                                    <tr>
                                        <td class="fw-semibold text-truncate"><?= htmlspecialchars($batch['product_name']) ?></td>
                                        <td><span class="badge bg-secondary bg-opacity-10 px-2 py-1" style="font-size:0.7rem; font-weight:600; color: var(--text-main) !important; border: 1px solid var(--border-color);"><?= htmlspecialchars($batch['batch_no'] ?: 'N/A') ?></span></td>
                                        <td class="text-center fw-bold"><?= number_format($batch['qty_on_hand']) ?> ctn</td>
                                        <td class="text-center" style="<?= get_expiry_days_style($batch['days_left']) ?>"><?= $batch['days_left'] ?> H</td>
                                        <td class="text-end"><?= get_expiry_risk_badge($batch['days_left']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- FOURTH ROW: Stock Level Trends -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="glass-card p-4">
                <div class="card-title-dashboard">
                    <span>Stock Level Trends (Units, Inbound &amp; Outbound, Last 15 Days)</span>
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="mt-3" style="height: 320px; position: relative;">
                    <canvas id="trendsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- FIFTH ROW: Layout Plan Status, Pallet Monitor, Recent Activity -->
    <div class="row g-4 mb-4">
        
        <!-- Layout Plan Map Status Grid -->
        <div class="col-xl-4 col-md-12">
            <div class="glass-card p-4" style="justify-content: flex-start; height: 100%;">
                <div class="card-title-dashboard w-100">
                    <span>Layout Plan Status</span>
                    <i class="bi bi-grid-3x3"></i>
                </div>
                
                <div class="mini-warehouse-container mt-2">
                    <div class="mini-warehouse-floor">
                        <!-- PSS_ABC -->
                        <div class="mini-layout-col">
                            <div class="mini-block-title">PSS A/B/C</div>
                            <div class="mini-slot-grid mini-grid-3-col">
                                <?php foreach ($mini_blocks['PSS_ABC'] as $slot) {
                                    echo '<div class="mini-slot ' . $slot['status'] . '" title="' . htmlspecialchars($slot['location_code']) . '"></div>';
                                } ?>
                            </div>
                        </div>
                        <!-- PSS_DEF -->
                        <div class="mini-layout-col">
                            <div class="mini-block-title">PSS D/E/F</div>
                            <div class="mini-slot-grid mini-grid-3-col">
                                <?php foreach ($mini_blocks['PSS_DEF'] as $slot) {
                                    echo '<div class="mini-slot ' . $slot['status'] . '" title="' . htmlspecialchars($slot['location_code']) . '"></div>';
                                } ?>
                            </div>
                        </div>
                        <!-- Divider wall -->
                        <div class="mini-wall-divider"></div>
                        <!-- COM_AB + POW -->
                        <div class="mini-layout-col">
                            <div>
                                <div class="mini-block-title">COM A/B</div>
                                <div class="mini-slot-grid mini-grid-2-col">
                                    <?php foreach ($mini_blocks['COM_AB'] as $slot) {
                                        echo '<div class="mini-slot ' . $slot['status'] . '" title="' . htmlspecialchars($slot['location_code']) . '"></div>';
                                    } ?>
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="mini-block-title">POW</div>
                                <div class="mini-slot-grid mini-grid-3-col">
                                    <?php foreach ($mini_blocks['POW_ABC'] as $slot) {
                                        echo '<div class="mini-slot ' . $slot['status'] . '" title="' . htmlspecialchars($slot['location_code']) . '"></div>';
                                    } ?>
                                </div>
                            </div>
                        </div>
                        <!-- COM_CD -->
                        <div class="mini-layout-col mini-offset-cd">
                            <div class="mini-block-title">COM C/D</div>
                            <div class="mini-slot-grid mini-grid-2-col">
                                <?php foreach ($mini_blocks['COM_CD'] as $slot) {
                                    echo '<div class="mini-slot ' . $slot['status'] . '" title="' . htmlspecialchars($slot['location_code']) . '"></div>';
                                } ?>
                            </div>
                        </div>
                        <!-- COM_EF -->
                        <div class="mini-layout-col mini-offset-ef">
                            <div class="mini-block-title">COM E/F</div>
                            <div class="mini-slot-grid mini-grid-2-col">
                                <?php foreach ($mini_blocks['COM_EF'] as $slot) {
                                    echo '<div class="mini-slot ' . $slot['status'] . '" title="' . htmlspecialchars($slot['location_code']) . '"></div>';
                                } ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="small mt-2 pt-2 border-top border-secondary border-opacity-25 w-100">
                    <div class="d-flex flex-wrap gap-2 justify-content-between small text-white-50">
                        <div><span class="badge bg-danger p-1 me-1" style="width:10px; height:10px; display:inline-block; border-radius: 2px;">&nbsp;</span> Empty</div>
                        <div><span class="badge bg-primary p-1 me-1" style="width:10px; height:10px; display:inline-block; border-radius: 2px;">&nbsp;</span> Medium</div>
                        <div><span class="badge bg-success p-1 me-1" style="width:10px; height:10px; display:inline-block; border-radius: 2px;">&nbsp;</span> Full</div>
                    </div>
                </div>
                
                <a href="warehouse_layout.php" class="btn btn-outline-info btn-sm fw-bold w-100 mt-3 border-info"><i class="bi bi-eye"></i> View Live Map</a>
            </div>
        </div>

        <!-- Pallet Monitor KPI summary -->
        <div class="col-xl-4 col-md-12">
            <div class="glass-card p-4" style="justify-content: flex-start; height: 100%;">
                <div class="card-title-dashboard w-100">
                    <span>Pallet Monitor</span>
                    <i class="bi bi-box-fill"></i>
                </div>
                
                <div class="mt-2 w-100 mb-3" style="max-height: 250px; overflow-y: auto; padding-right: 4px;">
                    <?php if (empty($pallet_summary)): ?>
                        <div class="text-muted text-center py-4">No pallet stock records.</div>
                    <?php else: ?>
                        <div class="row g-2">
                            <?php foreach ($pallet_summary as $summary): 
                                $code = strtolower($summary['code']);
                                $badge_class = 'bg-secondary text-white';
                                $circle_color = '#94a3b8';
                                $val_color = '#38bdf8';
                                
                                if ($code === 'plain') { 
                                    $badge_class = 'bg-primary text-white'; 
                                    $circle_color = '#a0522d'; 
                                    $val_color = '#3b82f6';
                                } elseif ($code === 'red') { 
                                    $badge_class = 'bg-danger text-white'; 
                                    $circle_color = '#ef4444'; 
                                    $val_color = '#ef4444';
                                } elseif ($code === 'lhp') { 
                                    $badge_class = 'bg-success text-white'; 
                                    $circle_color = '#10b981'; 
                                    $val_color = '#10b981';
                                } elseif ($code === 'orange') { 
                                    $badge_class = 'bg-warning text-dark'; 
                                    $circle_color = '#f59e0b'; 
                                    $val_color = '#f59e0b';
                                } elseif ($code === 'ffm') { 
                                    $badge_class = 'bg-info text-dark'; 
                                    $circle_color = '#06b6d4'; 
                                    $val_color = '#06b6d4';
                                } elseif ($code === 'black') { 
                                    $badge_class = 'bg-dark text-muted'; 
                                    $circle_color = '#475569'; 
                                    $val_color = '#94a3b8';
                                }
                            ?>
                            <div class="col-6">
                                <div class="p-2 rounded-3 h-100 d-flex flex-column justify-content-between pallet-sub-card">
                                    <!-- Header -->
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-bold text-truncate small pallet-card-title" style="max-width: 70%;" title="<?= htmlspecialchars($summary['name']) ?>">
                                            <span style="color: <?= $circle_color ?>; font-size: 0.85rem; margin-right: 3px;">●</span><?= htmlspecialchars($summary['name']) ?>
                                        </span>
                                        <span class="badge <?= $badge_class ?> text-uppercase" style="font-size: 0.52rem; padding: 2px 4px;"><?= htmlspecialchars($summary['code']) ?></span>
                                    </div>
                                    <!-- Stats Columns -->
                                    <div class="row text-center g-1 mb-2">
                                        <div class="col-4 border-end border-secondary border-opacity-10">
                                            <div class="text-muted" style="font-size:0.55rem; font-weight:700;">Total</div>
                                            <div class="fw-bold fs-6" style="color: <?= $val_color ?>;"><?= $summary['total'] ?></div>
                                        </div>
                                        <div class="col-4 border-end border-secondary border-opacity-10">
                                            <div class="text-muted" style="font-size:0.55rem; font-weight:700;">Loaded</div>
                                            <div class="fw-bold fs-6 pallet-card-value-muted"><?= $summary['loaded'] ?></div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-muted" style="font-size:0.55rem; font-weight:700;">Empty</div>
                                            <div class="fw-bold text-success fs-6"><?= $summary['empty'] ?></div>
                                        </div>
                                    </div>
                                    <!-- Footer Total IN/OUT -->
                                    <div class="text-center border-top border-secondary border-opacity-10 pt-1 pallet-card-total-inout" style="font-size: 0.56rem;">
                                        Total IN: <strong><?= $summary['in'] ?></strong> | OUT: <strong><?= $summary['out'] ?></strong>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <a href="pallet_management.php" class="btn btn-outline-info btn-sm fw-bold w-100 mt-auto border-info"><i class="bi bi-sliders"></i> Adjust Pallet Ledger</a>
            </div>
        </div>

        <!-- Recent Activity Feed -->
        <div class="col-xl-4 col-md-12">
            <div class="glass-card p-4" style="justify-content: flex-start; height: 100%;">
                <div class="card-title-dashboard w-100">
                    <span>Recent Activity</span>
                    <i class="bi bi-clock-history"></i>
                </div>
                
                <div class="table-responsive w-100 mt-2" style="border: none; max-height: 250px; overflow-y: auto;">
                    <table class="table-dark-custom">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>User</th>
                                <th class="text-end">Ref ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_activities)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-4">No recent logs.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent_activities as $log): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-truncate" style="max-width: 130px;"><?= htmlspecialchars($log['action']) ?></div>
                                            <small class="text-muted" style="font-size:0.68rem;"><?= date('d/m/y H:i', strtotime($log['created_at'])) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($log['username']) ?></td>
                                        <td class="text-end fw-bold text-info">#<?= $log['record_id'] ?: '—' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <a href="system_logs.php" class="btn btn-theme-outline btn-sm fw-bold w-100 mt-auto"><i class="bi bi-shield-shaded"></i> Full Logs</a>
            </div>
        </div>

    </div>

    <!-- SIXTH ROW: Pending Damage Records -->
    <div class="row g-4">
        <div class="col-12">
            <div class="glass-card p-4">
                <div class="card-title-dashboard">
                    <span>Pending Damage Records</span>
                    <i class="bi bi-exclamation-octagon"></i>
                </div>
                
                <div class="table-responsive mt-3" style="border: none; max-height: 250px; overflow-y: auto;">
                    <table class="table-dark-custom">
                        <thead>
                            <tr>
                                <th>Discovery Date</th>
                                <th>Product Name</th>
                                <th>Batch No</th>
                                <th class="text-center">Quantity (pcs)</th>
                                <th>Reason</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_damage)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-5">No pending damage logs found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($pending_damage as $dmg): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($dmg['reported_at'])) ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($dmg['product_name']) ?></td>
                                        <td><span class="badge bg-secondary">Batch: <?= htmlspecialchars($dmg['batch_no']) ?></span></td>
                                        <td class="text-center text-danger fw-bold"><?= number_format($dmg['qty']) ?> pcs</td>
                                        <td><?= htmlspecialchars($dmg['reason'] ?? '—') ?></td>
                                        <td class="text-center"><span class="badge bg-warning text-dark px-3 py-1 rounded-pill">Pending</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <a href="spoilage_report.php" class="btn btn-outline-danger btn-sm fw-bold w-100 mt-3 border-danger"><i class="bi bi-trash"></i> Manage Spoilage Reports</a>
            </div>
        </div>
    </div>

</div>

<!-- Chart.js Libraries & Sparkline Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Responsive menu handler
    function toggleSidebarMenu() {
        document.getElementById('sidebarMenu').classList.toggle('show');
    }

    // Dynamic Theme Toggler
    function toggleTheme() {
        const currentTheme = document.body.getAttribute('data-theme') || 'dark';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        setTheme(newTheme);
    }
    
    function setTheme(theme) {
        document.body.setAttribute('data-theme', theme);
        localStorage.setItem('dashboard-theme', theme);
        
        const btn = document.getElementById('themeToggleBtn');
        const icon = document.getElementById('themeIcon');
        const txt = document.getElementById('themeToggleTxt');
        
        if (theme === 'light') {
            if (icon) icon.className = 'bi bi-moon-fill';
            if (txt) { txt.innerText = 'Dark Mode'; txt.className = ''; }
            if (btn) btn.className = 'btn btn-theme-outline btn-sm fw-bold px-3 py-2';
            
            document.querySelectorAll('.text-white-50').forEach(el => {
                el.classList.remove('text-white-50');
                el.classList.add('text-black-50');
            });
        } else {
            if (icon) icon.className = 'bi bi-sun-fill';
            if (txt) { txt.innerText = 'Light Mode'; txt.className = ''; }
            if (btn) btn.className = 'btn btn-theme-outline btn-sm fw-bold px-3 py-2';
            
            document.querySelectorAll('.text-black-50').forEach(el => {
                el.classList.remove('text-black-50');
                el.classList.add('text-white-50');
            });
        }
        
        updateChartTheme(theme);
    }

    function updateChartTheme(theme) {
        if (!window.myTrendsChart) return;
        const isDark = theme === 'dark';
        const gridColor = isDark ? 'rgba(255,255,255,0.02)' : 'rgba(0,0,0,0.04)';
        const tickColor = isDark ? '#64748b' : '#475569';
        
        window.myTrendsChart.options.scales.x.grid.color = gridColor;
        window.myTrendsChart.options.scales.y.grid.color = gridColor;
        window.myTrendsChart.options.scales.x.ticks.color = tickColor;
        window.myTrendsChart.options.scales.y.ticks.color = tickColor;
        window.myTrendsChart.options.plugins.legend.labels.color = isDark ? '#94a3b8' : '#334155';
        window.myTrendsChart.update();
    }

    // Sparklines data & rendering
    document.addEventListener("DOMContentLoaded", function() {
        // PSS Progress Sparkline
        drawSparkline('sparkline-pss', [10, 15, 30, 45, 60, 80, <?= $pss_progress_percent ?>], '#38bdf8');
        
        // Capacity Sparkline
        drawSparkline('sparkline-capacity', [65, 68, 70, 72, 73, 75, <?= $capacity_percent ?>], '#10b981');
        
        // Low Stock Sparkline
        drawSparkline('sparkline-lowstock', [30, 32, 28, 35, 40, 42, <?= $all_low_skus_count ?>], '#f59e0b');

        // Main Inbound/Outbound Trends Chart
        const ctx = document.getElementById('trendsChart').getContext('2d');
        
        // Gradient creation
        const pssInGradient = ctx.createLinearGradient(0, 0, 0, 300);
        pssInGradient.addColorStop(0, 'rgba(6, 182, 212, 0.25)');
        pssInGradient.addColorStop(1, 'rgba(6, 182, 212, 0.0)');
        
        const commInGradient = ctx.createLinearGradient(0, 0, 0, 300);
        commInGradient.addColorStop(0, 'rgba(168, 85, 247, 0.25)');
        commInGradient.addColorStop(1, 'rgba(168, 85, 247, 0.0)');

        window.myTrendsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($dates) ?>,
                datasets: [
                    {
                        label: 'PSS Inbound (ctn)',
                        data: <?= json_encode($pss_inbound_series) ?>,
                        borderColor: '#06b6d4',
                        backgroundColor: pssInGradient,
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2.5,
                        pointBackgroundColor: '#06b6d4'
                    },
                    {
                        label: 'PSS Outbound (ctn)',
                        data: <?= json_encode($pss_outbound_series) ?>,
                        borderColor: '#0284c7',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        fill: false,
                        tension: 0.35,
                        pointRadius: 2,
                        pointBackgroundColor: '#0284c7'
                    },
                    {
                        label: 'Commercial Inbound (ctn)',
                        data: <?= json_encode($comm_inbound_series) ?>,
                        borderColor: '#a855f7',
                        backgroundColor: commInGradient,
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2.5,
                        pointBackgroundColor: '#a855f7'
                    },
                    {
                        label: 'Commercial Outbound (ctn)',
                        data: <?= json_encode($comm_outbound_series) ?>,
                        borderColor: '#ec4899',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        fill: false,
                        tension: 0.35,
                        pointRadius: 2,
                        pointBackgroundColor: '#ec4899'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: '#94a3b8',
                            font: { family: 'Plus Jakarta Sans', weight: 'bold', size: 10 }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255,255,255,0.02)' },
                        ticks: { color: '#64748b', font: { family: 'Plus Jakarta Sans', size: 10 } }
                    },
                    y: {
                        grid: { color: 'rgba(255,255,255,0.02)' },
                        ticks: { color: '#64748b', font: { family: 'Plus Jakarta Sans', size: 10 } }
                    }
                }
            }
        });
        
        // Trigger initial theme load configuration
        const activeTheme = localStorage.getItem('dashboard-theme') || 'dark';
        setTheme(activeTheme);
    });

    function drawSparkline(canvasId, dataPoints, colorHex) {
        const canvas = document.getElementById(canvasId);
        if(!canvas) return;
        const ctx = canvas.getContext('2d');
        
        // Match container size
        const container = canvas.parentNode;
        canvas.width = container.clientWidth;
        canvas.height = container.clientHeight;
        
        const w = canvas.width;
        const h = canvas.height;
        
        ctx.clearRect(0,0,w,h);
        
        if(dataPoints.length < 2) return;
        
        const maxVal = Math.max(...dataPoints) || 1;
        const minVal = Math.min(...dataPoints);
        const range = maxVal - minVal || 1;
        
        const points = dataPoints.map((val, idx) => ({
            x: (idx / (dataPoints.length - 1)) * (w - 6) + 3,
            y: h - ((val - minVal) / range) * (h - 10) - 5
        }));
        
        ctx.beginPath();
        ctx.moveTo(points[0].x, points[0].y);
        for(let i = 1; i < points.length; i++) {
            ctx.lineTo(points[i].x, points[i].y);
        }
        
        ctx.strokeStyle = colorHex;
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.stroke();
    }
</script>

<?php require_once 'includes/footer.php'; ?>
