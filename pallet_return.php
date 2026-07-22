<?php
// pallet_return.php - Pallet Return to Supplier & Credit Note (CN) Tracking System
require_once 'config/db.php';

// Auto Migration for Pallet Return, Items & Photo Tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `pallet_returns` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `prf_no` VARCHAR(100) NOT NULL,
      `supplier_name` VARCHAR(255) NOT NULL,
      `return_date` DATETIME NOT NULL,
      `total_quantity` INT(11) NOT NULL DEFAULT 0,
      `driver_name` VARCHAR(150) NOT NULL,
      `transporter_name` VARCHAR(150) NOT NULL,
      `vehicle_plate` VARCHAR(50) NOT NULL,
      `prf_form_file` VARCHAR(255) NOT NULL,
      `remarks` TEXT DEFAULT NULL,
      `cn_status` ENUM('PENDING','RECEIVED','DISCREPANCY','REJECTED') NOT NULL DEFAULT 'PENDING',
      `cn_no` VARCHAR(100) DEFAULT NULL,
      `cn_date` DATE DEFAULT NULL,
      `cn_amount` DECIMAL(10,2) DEFAULT NULL,
      `cn_file` VARCHAR(255) DEFAULT NULL,
      `cn_notes` TEXT DEFAULT NULL,
      `cn_updated_at` DATETIME DEFAULT NULL,
      `created_by` VARCHAR(100) NOT NULL,
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `prf_no` (`prf_no`),
      KEY `supplier_name` (`supplier_name`),
      KEY `cn_status` (`cn_status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `pallet_return_items` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `return_id` INT(11) NOT NULL,
      `pallet_type` VARCHAR(100) NOT NULL,
      `pallet_color` VARCHAR(50) NOT NULL,
      `quantity` INT(11) NOT NULL DEFAULT 1,
      `pallet_condition` VARCHAR(100) NOT NULL DEFAULT 'Good / Sound',
      PRIMARY KEY (`id`),
      KEY `return_id` (`return_id`),
      CONSTRAINT `fk_pallet_return_items` FOREIGN KEY (`return_id`) REFERENCES `pallet_returns` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `pallet_return_photos` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `return_id` INT(11) NOT NULL,
      `photo_path` VARCHAR(255) NOT NULL,
      `caption` VARCHAR(255) DEFAULT NULL,
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `return_id` (`return_id`),
      CONSTRAINT `fk_pallet_return_photos` FOREIGN KEY (`return_id`) REFERENCES `pallet_returns` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    // Check if total_quantity column exists in pallet_returns, add if missing
    $chk_col = $pdo->query("SHOW COLUMNS FROM pallet_returns LIKE 'total_quantity'")->fetch();
    if (!$chk_col) {
        $pdo->exec("ALTER TABLE pallet_returns ADD COLUMN total_quantity INT(11) NOT NULL DEFAULT 0 AFTER return_date");
    }

    // Ensure pallet_ledger table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `pallet_ledger` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `transaction_date` datetime DEFAULT current_timestamp(),
      `transaction_type` enum('IN','OUT','ADJUSTMENT') NOT NULL,
      `pallet_code` varchar(50) NOT NULL,
      `qty` int(11) NOT NULL,
      `reference_no` varchar(100) DEFAULT NULL,
      `notes` text DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `pallet_code` (`pallet_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
} catch (Exception $e) {
    error_log("Pallet return migration error: " . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? 'System';

$success_msg = '';
$error_msg = '';

// Handle POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. CREATE NEW PALLET RETURN RECORD
    if ($_POST['action'] === 'add_return') {
        $prf_no           = trim($_POST['prf_no'] ?? '');
        $supplier_name    = strtoupper(trim($_POST['supplier_name'] ?? ''));
        if ($supplier_name === '__NEW__' || empty($supplier_name)) {
            $supplier_name = strtoupper(trim($_POST['new_supplier_name'] ?? ''));
        }
        $return_date      = trim($_POST['return_date'] ?? date('Y-m-d H:i:s'));
        $driver_name      = trim($_POST['driver_name'] ?? '');
        $transporter_name = trim($_POST['transporter_name'] ?? '');
        $vehicle_plate    = trim($_POST['vehicle_plate'] ?? '');
        $remarks          = trim($_POST['remarks'] ?? '');
        $items            = $_POST['items'] ?? [];

        // Compulsory fields validation
        if (empty($prf_no) || empty($supplier_name) || empty($transporter_name) || empty($driver_name) || empty($vehicle_plate)) {
            $error_msg = "Please fill in PRF Number, Supplier Name, Transporter Name, Driver Name, and Vehicle Plate Number.";
        } else if (empty($_FILES['prf_form_file']['name'])) {
            $error_msg = "Compulsory: Please upload a snapshot of the signed PRF Form document.";
        } else if (empty($_FILES['stack_photos']['name'][0])) {
            $error_msg = "Compulsory: Please capture or upload at least one trailer stack proof photo.";
        } else if (empty($items) || !is_array($items)) {
            $error_msg = "Please add at least one pallet line item with type, quantity, and condition.";
        } else {
            try {
                $pdo->beginTransaction();

                $upload_dir = 'uploads/pallet_returns/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                // Handle Compulsory Signed PRF Form File Upload
                $prf_form_path = '';
                $file_ext = pathinfo($_FILES['prf_form_file']['name'], PATHINFO_EXTENSION);
                $new_filename = 'PRF_' . time() . '_' . bin2hex(random_bytes(2)) . '.' . $file_ext;
                $dest = $upload_dir . $new_filename;
                if (move_uploaded_file($_FILES['prf_form_file']['tmp_name'], $dest)) {
                    $prf_form_path = $dest;
                } else {
                    throw new Exception("Failed to upload signed PRF Form file.");
                }

                // Calculate Total Quantity & Insert Header
                $total_qty = 0;
                foreach ($items as $it) {
                    $q = (int)($it['quantity'] ?? 0);
                    if ($q > 0) $total_qty += $q;
                }

                $stmt = $pdo->prepare("INSERT INTO pallet_returns (
                    prf_no, supplier_name, return_date, total_quantity, 
                    driver_name, transporter_name, vehicle_plate, prf_form_file, 
                    remarks, cn_status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', ?)");
                
                $stmt->execute([
                    $prf_no, $supplier_name, $return_date, $total_qty,
                    $driver_name, $transporter_name, $vehicle_plate, $prf_form_path,
                    $remarks, $username
                ]);
                $return_id = $pdo->lastInsertId();

                // Insert Pallet Line Items & Ledger Entries
                $item_stmt = $pdo->prepare("INSERT INTO pallet_return_items (return_id, pallet_type, pallet_color, quantity, pallet_condition) VALUES (?, ?, ?, ?, ?)");
                $ledger_stmt = $pdo->prepare("INSERT INTO pallet_ledger (transaction_type, pallet_code, qty, reference_no, notes) VALUES ('OUT', ?, ?, ?, ?)");

                foreach ($items as $it) {
                    $p_type  = trim($it['pallet_type'] ?? '');
                    $p_color = trim($it['pallet_color'] ?? '');
                    $p_qty   = (int)($it['quantity'] ?? 0);
                    $p_cond  = trim($it['pallet_condition'] ?? 'Good / Sound');

                    if (!empty($p_type) && $p_qty > 0) {
                        $item_stmt->execute([$return_id, $p_type, $p_color, $p_qty, $p_cond]);

                        // Add OUT entry to ledger
                        $ledger_notes = "Pallet return to supplier $supplier_name via PRF $prf_no (Color: $p_color, Condition: $p_cond)";
                        $ledger_stmt->execute([$p_type, $p_qty, $prf_no, $ledger_notes]);
                    }
                }

                // Handle Multiple Trailer Stack Proof Photos Upload
                if (!empty($_FILES['stack_photos']['name'][0])) {
                    $photo_stmt = $pdo->prepare("INSERT INTO pallet_return_photos (return_id, photo_path, caption) VALUES (?, ?, ?)");
                    foreach ($_FILES['stack_photos']['name'] as $idx => $orig_name) {
                        if (empty($orig_name)) continue;
                        $tmp_file = $_FILES['stack_photos']['tmp_name'][$idx];
                        $file_ext = pathinfo($orig_name, PATHINFO_EXTENSION);
                        $photo_filename = 'STACK_' . $return_id . '_' . time() . '_' . $idx . '_' . bin2hex(random_bytes(2)) . '.' . $file_ext;
                        $photo_dest = $upload_dir . $photo_filename;
                        
                        if (move_uploaded_file($tmp_file, $photo_dest)) {
                            $caption = "Stack Photo #" . ($idx + 1) . " - PRF " . $prf_no;
                            $photo_stmt->execute([$return_id, $photo_dest, $caption]);
                        }
                    }
                }

                $pdo->commit();

                if (function_exists('log_system_activity')) {
                    log_system_activity("Pallet Return Recorded", "pallet_returns", $return_id, "$username logged pallet return PRF $prf_no ($total_qty pallets total to $supplier_name)");
                }

                $success_msg = "Pallet return record (PRF #$prf_no) logged successfully with $total_qty pallets!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = "Error recording pallet return: " . $e->getMessage();
            }
        }
    }

    // 2. UPDATE CREDIT NOTE (CN) RECONCILIATION
    if ($_POST['action'] === 'update_cn') {
        $return_id  = (int)($_POST['return_id'] ?? 0);
        $cn_status  = trim($_POST['cn_status'] ?? 'PENDING');
        $cn_no      = trim($_POST['cn_no'] ?? '');
        $cn_date    = !empty($_POST['cn_date']) ? $_POST['cn_date'] : null;
        $cn_amount  = !empty($_POST['cn_amount']) ? (float)$_POST['cn_amount'] : null;
        $cn_notes   = trim($_POST['cn_notes'] ?? '');

        if ($return_id <= 0) {
            $error_msg = "Invalid return record selected.";
        } else {
            try {
                $stmtExist = $pdo->prepare("SELECT * FROM pallet_returns WHERE id = ? LIMIT 1");
                $stmtExist->execute([$return_id]);
                $existing = $stmtExist->fetch();

                if (!$existing) {
                    $error_msg = "Pallet return record not found.";
                } else {
                    $upload_dir = 'uploads/pallet_returns/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $cn_file_path = $existing['cn_file'];
                    if (!empty($_FILES['cn_file']['name'])) {
                        $file_ext = pathinfo($_FILES['cn_file']['name'], PATHINFO_EXTENSION);
                        $new_filename = 'CN_' . $return_id . '_' . time() . '.' . $file_ext;
                        $dest = $upload_dir . $new_filename;
                        if (move_uploaded_file($_FILES['cn_file']['tmp_name'], $dest)) {
                            $cn_file_path = $dest;
                        }
                    }

                    $stmtUp = $pdo->prepare("UPDATE pallet_returns SET 
                        cn_status = ?, cn_no = ?, cn_date = ?, cn_amount = ?, cn_file = ?, cn_notes = ?, cn_updated_at = NOW() 
                        WHERE id = ?");
                    $stmtUp->execute([$cn_status, $cn_no, $cn_date, $cn_amount, $cn_file_path, $cn_notes, $return_id]);

                    if (function_exists('log_system_activity')) {
                        log_system_activity("CN Updated", "pallet_returns", $return_id, "$username updated CN status to $cn_status (CN No: $cn_no) for PRF {$existing['prf_no']}");
                    }

                    $success_msg = "Credit Note (CN) reconciliation updated successfully for PRF #{$existing['prf_no']}!";
                }
            } catch (Exception $e) {
                $error_msg = "Error updating CN: " . $e->getMessage();
            }
        }
    }

    // 3. DELETE RETURN RECORD
    if ($_POST['action'] === 'delete_return') {
        $return_id = (int)($_POST['return_id'] ?? 0);
        if ($return_id > 0) {
            try {
                $stmtDel = $pdo->prepare("DELETE FROM pallet_returns WHERE id = ?");
                $stmtDel->execute([$return_id]);
                $success_msg = "Pallet return record deleted successfully.";
            } catch (Exception $e) {
                $error_msg = "Error deleting record: " . $e->getMessage();
            }
        }
    }
}

// Fetch Filter parameters
$search_query    = trim($_GET['search'] ?? '');
$supplier_filter = trim($_GET['supplier'] ?? '');
$status_filter   = trim($_GET['status'] ?? '');

// Build Query
$where_clauses = ["1=1"];
$params = [];

if (!empty($search_query)) {
    $where_clauses[] = "(r.prf_no LIKE ? OR r.supplier_name LIKE ? OR r.driver_name LIKE ? OR r.vehicle_plate LIKE ? OR r.cn_no LIKE ?)";
    $params = array_merge($params, ["%$search_query%", "%$search_query%", "%$search_query%", "%$search_query%", "%$search_query%"]);
}
if (!empty($supplier_filter)) {
    $where_clauses[] = "r.supplier_name = ?";
    $params[] = $supplier_filter;
}
if (!empty($status_filter)) {
    $where_clauses[] = "r.cn_status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(' AND ', $where_clauses);
$sql = "SELECT r.*, 
        (SELECT COUNT(*) FROM pallet_return_photos p WHERE p.return_id = r.id) as photo_count,
        (SELECT GROUP_CONCAT(CONCAT(i.pallet_type, ' (', i.quantity, ')') SEPARATOR ', ') FROM pallet_return_items i WHERE i.return_id = r.id) as item_summary
        FROM pallet_returns r 
        WHERE $where_sql 
        ORDER BY r.return_date DESC, r.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$return_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Summary KPI stats
$total_returns_count = count($return_records);
$total_pallets_returned = array_sum(array_column($return_records, 'total_quantity'));
$pending_cn_count = count(array_filter($return_records, fn($r) => $r['cn_status'] === 'PENDING'));
$received_cn_count = count(array_filter($return_records, fn($r) => $r['cn_status'] === 'RECEIVED'));
$total_cn_amount = array_sum(array_map(fn($r) => (float)($r['cn_amount'] ?? 0), array_filter($return_records, fn($r) => $r['cn_status'] === 'RECEIVED')));
$discrepancy_count = count(array_filter($return_records, fn($r) => in_array($r['cn_status'], ['DISCREPANCY', 'REJECTED'])));

// Registered Pallet Types from Database
$registered_pallet_types = [];
try {
    $registered_pallet_types = $pdo->query("SELECT * FROM pallet_types ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Distinct Suppliers for Filter & Dropdowns (Strictly from DB)
$suppliers_list = [];
try {
    $s1 = $pdo->query("SELECT DISTINCT supplier_name FROM pallet_returns WHERE supplier_name IS NOT NULL AND supplier_name != ''")->fetchAll(PDO::FETCH_COLUMN);
    $s2 = [];
    try {
        $s2 = $pdo->query("SELECT DISTINCT supplier_name FROM inventory_batches WHERE supplier_name IS NOT NULL AND supplier_name != ''")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}
    $s3 = [];
    try {
        $s3 = $pdo->query("SELECT name FROM suppliers ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}

    $all_raw = array_merge($s1, $s2, $s3);
    $all_sup = array_unique(array_map('strtoupper', array_map('trim', array_filter($all_raw))));
    sort($all_sup);
    $suppliers_list = array_values($all_sup);
} catch (Exception $e) {
    $suppliers_list = [];
}

$page_title = 'Pallet Return & Credit Note Tracking | MMS';
require_once 'includes/header.php';
?>

<style>
.badge-cn-pending { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
.badge-cn-received { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.badge-cn-discrepancy { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.badge-cn-rejected { background-color: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }
.kpi-card { border-radius: 16px; transition: transform 0.2s ease, box-shadow 0.2s ease; }
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
</style>

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-arrow-return-left me-2 text-warning"></i>Pallet Return & Credit Note (CN) Tracking</h1>
                <p class="opacity-75 mb-0 fw-light">Manage supplier pallet returns, signed PRF documentation, stack photo evidence, and CN reconciliation</p>
            </div>
            <div class="d-flex gap-2">
                <a href="pallet_management.php" class="btn btn-outline-light"><i class="bi bi-grid-3x3-gap me-1"></i> Pallet Monitor</a>
                <button type="button" class="btn btn-warning fw-bold text-dark" onclick="openAddReturnModal()">
                    <i class="bi bi-plus-lg me-1"></i> Record Pallet Return (PRF)
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    
    <?php if ($success_msg): ?>
        <div class="alert alert-success border-0 shadow-sm mb-4 alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i><?= htmlspecialchars($success_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4 alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i><?= htmlspecialchars($error_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- KPI Summary Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card kpi-card border-0 shadow-sm bg-white p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Total Returned</span>
                        <h3 class="fw-extrabold text-navy mb-0 mt-1"><?= number_format($total_pallets_returned) ?> <small class="fs-6 text-muted">pallets</small></h3>
                        <small class="text-muted"><?= $total_returns_count ?> PRF dispatch shipments</small>
                    </div>
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle">
                        <i class="bi bi-box-seam fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card kpi-card border-0 shadow-sm bg-white p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Pending CN</span>
                        <h3 class="fw-extrabold text-warning mb-0 mt-1"><?= number_format($pending_cn_count) ?> <small class="fs-6 text-muted">returns</small></h3>
                        <small class="text-warning-emphasis">Awaiting Supplier Credit Note</small>
                    </div>
                    <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-circle">
                        <i class="bi bi-hourglass-split fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card kpi-card border-0 shadow-sm bg-white p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">CN Received</span>
                        <h3 class="fw-extrabold text-success mb-0 mt-1">RM <?= number_format($total_cn_amount, 2) ?></h3>
                        <small class="text-success"><?= $received_cn_count ?> returns reconciled</small>
                    </div>
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-circle">
                        <i class="bi bi-patch-check-fill fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card kpi-card border-0 shadow-sm bg-white p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase">Discrepancy / Action</span>
                        <h3 class="fw-extrabold text-danger mb-0 mt-1"><?= number_format($discrepancy_count) ?></h3>
                        <small class="text-danger">Discrepancies flagged</small>
                    </div>
                    <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-circle">
                        <i class="bi bi-exclamation-octagon-fill fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Card -->
    <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;">
        <div class="card-body p-3">
            <form method="GET" action="pallet_return.php" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search PRF #, Supplier, Driver, CN #..." value="<?= htmlspecialchars($search_query) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="supplier" class="form-select">
                        <option value="" data-lang="preturn_filter_supplier">-- All Suppliers --</option>
                        <?php foreach ($suppliers_list as $sup): ?>
                            <option value="<?= htmlspecialchars($sup) ?>" <?= $supplier_filter === $sup ? 'selected' : '' ?>><?= htmlspecialchars($sup) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="" data-lang="preturn_filter_status">-- CN Status --</option>
                        <option value="PENDING" <?= $status_filter === 'PENDING' ? 'selected' : '' ?>>⏳ Pending CN</option>
                        <option value="RECEIVED" <?= $status_filter === 'RECEIVED' ? 'selected' : '' ?>>✅ CN Received</option>
                        <option value="DISCREPANCY" <?= $status_filter === 'DISCREPANCY' ? 'selected' : '' ?>>⚠️ Discrepancy</option>
                        <option value="REJECTED" <?= $status_filter === 'REJECTED' ? 'selected' : '' ?>>❌ Rejected</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-dark w-100 fw-bold"><i class="bi bi-funnel me-1"></i>Filter</button>
                    <?php if ($search_query || $supplier_filter || $status_filter): ?>
                        <a href="pallet_return.php" class="btn btn-outline-secondary fw-bold" data-lang-title="btn_reset" title="Reset Filters"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Return Records Table Card -->
    <div class="card border-0 shadow-sm" style="border-radius:16px;">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-navy"><i class="bi bi-table me-2 text-primary"></i>Pallet Return Records & CN Status</h5>
            <span class="badge bg-secondary rounded-pill px-3"><?= count($return_records) ?> entries</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:0.92rem;">
                    <thead class="table-light text-muted fw-bold small">
                        <tr>
                            <th class="ps-4">PRF & Return Date</th>
                            <th>Supplier</th>
                            <th>Pallet Item Breakdown</th>
                            <th class="text-center">Total Qty</th>
                            <th>Transporter & Driver Details *</th>
                            <th class="text-center">Signed PRF Form *</th>
                            <th class="text-center">Evidence Photos</th>
                            <th class="text-center">CN Status & Ref</th>
                            <th class="pe-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($return_records)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                    No pallet return records found matching filters.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($return_records as $row): 
                                $status = $row['cn_status'];
                                $badge_cls = match($status) {
                                    'RECEIVED' => 'badge-cn-received',
                                    'DISCREPANCY' => 'badge-cn-discrepancy',
                                    'REJECTED' => 'badge-cn-rejected',
                                    default => 'badge-cn-pending'
                                };
                                $status_label = match($status) {
                                    'RECEIVED' => 'CN Received',
                                    'DISCREPANCY' => 'Discrepancy',
                                    'REJECTED' => 'Rejected',
                                    default => 'Pending CN'
                                };
                            ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-navy"><i class="bi bi-file-earmark-text text-primary me-1"></i><?= htmlspecialchars($row['prf_no']) ?></div>
                                        <div class="small text-muted"><i class="bi bi-clock me-1"></i><?= date('d/m/Y H:i', strtotime($row['return_date'])) ?></div>
                                    </td>

                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['supplier_name']) ?></div>
                                        <div class="small text-muted">By: <?= htmlspecialchars($row['created_by']) ?></div>
                                    </td>

                                    <td>
                                        <small class="fw-semibold text-dark"><?= htmlspecialchars($row['item_summary'] ?: 'Line Items') ?></small>
                                    </td>

                                    <td class="text-center fw-extrabold text-navy fs-5">
                                        <?= number_format($row['total_quantity']) ?> <span class="fs-6 text-muted font-normal">pcs</span>
                                    </td>

                                    <td>
                                        <div class="fw-bold text-dark"><i class="bi bi-truck me-1 text-primary"></i><?= htmlspecialchars($row['transporter_name']) ?></div>
                                        <div class="small text-muted">Driver: <strong><?= htmlspecialchars($row['driver_name']) ?></strong> | <code><?= htmlspecialchars($row['vehicle_plate']) ?></code></div>
                                    </td>

                                    <td class="text-center">
                                        <?php if (!empty($row['prf_form_file'])): ?>
                                            <a href="<?= htmlspecialchars($row['prf_form_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-1">
                                                <i class="bi bi-file-earmark-check-fill text-success me-1"></i> Signed PRF
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Missing PRF</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-3" onclick='viewReturnDetails(<?= json_encode($row) ?>)'>
                                            <i class="bi bi-images me-1"></i> <?= $row['photo_count'] ?> Stacks
                                        </button>
                                    </td>

                                    <td class="text-center">
                                        <span class="badge <?= $badge_cls ?> px-3 py-1.5 rounded-pill fw-bold mb-1 d-inline-block">
                                            <?= $status_label ?>
                                        </span>
                                        <?php if (!empty($row['cn_no'])): ?>
                                            <div class="small fw-bold text-navy">CN: <?= htmlspecialchars($row['cn_no']) ?></div>
                                            <?php if ($row['cn_amount']): ?>
                                                <div class="small text-success fw-bold">RM <?= number_format($row['cn_amount'], 2) ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="small text-muted">Awaiting CN #</div>
                                        <?php endif; ?>
                                    </td>

                                    <td class="pe-4 text-center">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-light border" title="View Full Details" onclick='viewReturnDetails(<?= json_encode($row) ?>)'>
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-warning text-dark fw-bold" title="Update Credit Note (CN)" onclick='openUpdateCnModal(<?= json_encode($row) ?>)'>
                                                <i class="bi bi-pencil-square me-1"></i> CN
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" title="Print PRF Slip" onclick='printPrfSlip(<?= json_encode($row) ?>)'>
                                                <i class="bi bi-printer"></i>
                                            </button>
                                        </div>
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

<!-- ════════════════════════════════════════════════════════════════ -->
<!-- MODAL 1: RECORD NEW PALLET RETURN (PRF)                         -->
<!-- ════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addReturnModal" tabindex="-1" aria-labelledby="addReturnModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <form method="POST" action="pallet_return.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_return">
            <div class="modal-content" style="border-radius: 16px; overflow: hidden; border: none;">
                <div class="modal-header bg-navy text-white px-4 py-3" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
                    <h5 class="modal-title fw-bold" id="addReturnModalLabel"><i class="bi bi-arrow-return-left text-warning me-2"></i>Record New Pallet Recovery Form (PRF)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">PRF Serial / Ref No *</label>
                            <input type="text" name="prf_no" id="add_prf_no" class="form-control fw-bold" placeholder="e.g. 22171" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold" data-lang="preturn_modal_supplier">Supplier Name *</label>
                            <select name="supplier_name" id="supplier_select_input" class="form-select fw-semibold" onchange="handleSupplierSelectChange(this)" required>
                                <option value="">-- Select Supplier --</option>
                                <?php foreach ($suppliers_list as $s): ?>
                                    <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                                <?php endforeach; ?>
                                <option value="__NEW__" class="fw-bold text-primary">+ Add New Supplier Name...</option>
                            </select>
                            
                            <div id="new_supplier_input_container" class="mt-2 d-none">
                                <input type="text" name="new_supplier_name" id="new_supplier_name_input" class="form-control fw-bold border-primary" placeholder="Type new supplier name (e.g. Dutch Lady)">
                                <div class="form-text small text-primary"><i class="bi bi-info-circle me-1"></i>Enter new supplier name above.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold" data-lang="preturn_modal_date">Return Date & Time *</label>
                            <input type="datetime-local" name="return_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>
                    </div>

                    <!-- COMPULSORY TRANSPORTER & DRIVER DETAILS SECTION -->
                    <div class="p-3 border rounded bg-white mb-4 shadow-sm" style="border-left: 5px solid #0284c7 !important;">
                        <h6 class="fw-extrabold text-navy mb-2"><i class="bi bi-truck me-2 text-primary"></i>Transporter & Driver Details <span class="badge bg-danger small ms-1">Compulsory *</span></h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Transporter / Logistics Company *</label>
                                <input type="text" name="transporter_name" class="form-control" placeholder="e.g. Farm Fresh Logistics / Swift" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Driver Name *</label>
                                <input type="text" name="driver_name" class="form-control" placeholder="Driver's Full Name (e.g. Nazeri Yusoh)" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Vehicle / Lorry Plate No *</label>
                                <input type="text" name="vehicle_plate" class="form-control text-uppercase fw-bold" placeholder="e.g. VGS 15037 / BQA 1234" required>
                            </div>
                        </div>
                    </div>

                    <!-- DYNAMIC MULTI-ITEM PALLET TABLE -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                            <h6 class="fw-bold mb-0 text-navy"><i class="bi bi-list-check me-2 text-warning"></i>List of Pallet Items Returned in this PRF</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="addPalletItemRow()">
                                <i class="bi bi-plus-lg me-1"></i> Add Item Row
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="palletItemsTable">
                                    <thead class="table-light text-muted small fw-bold">
                                        <tr>
                                            <th style="width: 50%;">Type of Pallet *</th>
                                            <th style="width: 20%;" class="text-center">Quantity *</th>
                                            <th style="width: 20%;">Condition *</th>
                                            <th style="width: 10%;" class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="palletItemsTbody">
                                        <!-- Row 1 Default -->
                                        <tr>
                                            <td>
                                                <input type="text" name="items[0][pallet_type]" class="form-control form-control-sm fw-bold" list="pallet_types_datalist" placeholder="Type or select pallet (e.g. Loscam Red, Plain Wood)" required>
                                            </td>
                                            <td>
                                                <input type="number" name="items[0][quantity]" class="form-control form-control-sm text-center fw-bold item-qty" min="1" value="1" oninput="calculateFormTotalQty()" required>
                                            </td>
                                            <td>
                                                <select name="items[0][pallet_condition]" class="form-select form-select-sm" required>
                                                    <option value="Good / Sound">Good / Sound</option>
                                                    <option value="Damaged">Damaged</option>
                                                    <option value="Repairable">Repairable</option>
                                                    <option value="Broken / Scrap">Broken / Scrap</option>
                                                </select>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removePalletItemRow(this)"><i class="bi bi-trash"></i></button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <td class="text-end fw-bold text-navy">TOTAL PALLETS:</td>
                                            <td class="text-center fw-extrabold text-primary fs-5" id="formTotalQtyDisplay">1</td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- DATALIST FOR REGISTERED PALLET TYPES -->
                    <datalist id="pallet_types_datalist">
                        <?php foreach ($registered_pallet_types as $pt): ?>
                            <option value="<?= htmlspecialchars($pt['name']) ?>"><?= htmlspecialchars($pt['code']) ?></option>
                        <?php endforeach; ?>
                        <option value="Loscam Red">Loscam Red</option>
                        <option value="FFM Green">FFM Green</option>
                        <option value="FFM Orange">CRATE FFM Orange</option>
                        <option value="Plain Wood">Plain Wood</option>
                        <option value="LHP Green">LHP Green</option>
                        <option value="Plastic Black">Plastic Black</option>
                    </datalist>

                    <!-- COMPULSORY TRAILER STACK EVIDENCE PHOTOS (FIRST) -->
                    <div class="p-3 border rounded bg-success bg-opacity-10 border-success mb-3">
                        <label class="form-label fw-bold text-navy mb-1">
                            <i class="bi bi-camera me-1 text-success"></i><span data-lang="preturn_modal_photos">Trailer Stack Proof Photos (Multiple Upload)</span> <span class="badge bg-danger ms-1">Compulsory *</span>
                        </label>
                        <div class="d-flex gap-2 mb-2 flex-wrap">
                            <button type="button" class="btn btn-outline-success fw-bold" onclick="triggerCamStack()">
                                <i class="bi bi-camera-fill me-1"></i> Take Stack Photo (Camera)
                            </button>
                            <button type="button" class="btn btn-outline-primary fw-bold" onclick="triggerGalleryStack()">
                                <i class="bi bi-images me-1"></i> Pick Stack Photos (Gallery)
                            </button>
                        </div>
                        <input type="file" name="stack_photos[]" id="stack_photos_input" class="form-control d-none" multiple accept="image/*" onchange="previewMultiPhotos(this, 'stack_preview_container')" required>
                        <input type="file" id="stack_camera_input" class="d-none" accept="image/*" capture="environment" onchange="appendCameraPhoto(this, 'stack_photos_input', 'stack_preview_container')">

                        <div id="stack_preview_container" class="row g-2 mt-1"></div>
                        <div class="form-text text-dark small fw-semibold mt-1"><i class="bi bi-camera-fill me-1 text-success"></i><span data-lang="preturn_modal_photos_desc">Compulsory: Take photos of every stack of pallets loaded onto the trailer/truck for physical verification proof.</span></div>
                    </div>

                    <!-- COMPULSORY SIGNED PRF SNAPSHOT UPLOAD (SECOND) -->
                    <div class="p-3 border rounded bg-warning bg-opacity-10 border-warning mb-3">
                        <label class="form-label fw-bold text-navy mb-1">
                            <i class="bi bi-file-earmark-image me-1 text-danger"></i><span data-lang="preturn_modal_doc">Snapshot of Signed PRF Form (Pallet Recovery Form)</span> <span class="badge bg-danger ms-1">Compulsory *</span>
                        </label>
                        <div class="d-flex gap-2 mb-2 flex-wrap">
                            <button type="button" class="btn btn-outline-danger fw-bold" onclick="triggerCamPrf()">
                                <i class="bi bi-camera-fill me-1"></i> Snap PRF from Camera
                            </button>
                            <button type="button" class="btn btn-outline-primary fw-bold" onclick="triggerGalleryPrf()">
                                <i class="bi bi-images me-1"></i> Select PRF from Gallery
                            </button>
                        </div>
                        <input type="file" name="prf_form_file" id="prf_camera_input" class="form-control d-none" accept="image/*,.pdf" onchange="previewSinglePhoto(this, 'prf_preview_container')" required>
                        <input type="file" id="prf_gallery_input" class="d-none" accept="image/*,.pdf" onchange="syncSinglePhoto(this, 'prf_camera_input', 'prf_preview_container')">
                        
                        <div id="prf_preview_container"></div>
                        <div class="form-text text-dark small fw-semibold mt-1"><i class="bi bi-exclamation-circle me-1 text-danger"></i><span data-lang="preturn_modal_doc_desc">It is compulsory to upload a snapshot/photo of the physical paper PRF form signed by driver & warehouse.</span></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" data-lang="preturn_modal_remarks">Remarks / Notes</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Any extra remarks..."></textarea>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><span data-lang="btn_cancel">Cancel</span></button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark"><i class="bi bi-check-circle me-1"></i> <span data-lang="preturn_modal_btn_submit">Submit Pallet Return</span></button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════ -->
<!-- MODAL 2: UPDATE CREDIT NOTE (CN) RECONCILIATION                 -->
<!-- ════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="updateCnModal" tabindex="-1" aria-labelledby="updateCnModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="pallet_return.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_cn">
            <input type="hidden" name="return_id" id="cn_return_id">
            
            <div class="modal-content" style="border-radius:16px; overflow:hidden;">
                <div class="modal-header bg-warning text-dark px-4 py-3">
                    <h5 class="modal-title fw-bold" id="updateCnModalLabel"><i class="bi bi-receipt-cutoff me-2"></i><span data-lang="preturn_modal_cn_update">Update Supplier Credit Note (CN)</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    
                    <div class="alert alert-info border-0 p-3 mb-3" style="border-radius:12px;">
                        <div class="fw-bold text-navy mb-1" id="cn_modal_prf_display">PRF: -</div>
                        <small class="text-muted" id="cn_modal_supplier_display">Supplier: -</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" data-lang="preturn_modal_cn_status">CN Status *</label>
                        <select name="cn_status" id="cn_status_select" class="form-select fw-bold" required>
                            <option value="PENDING">⏳ PENDING (Awaiting Supplier CN)</option>
                            <option value="RECEIVED">✅ RECEIVED (CN Verified & Accepted)</option>
                            <option value="DISCREPANCY">⚠️ DISCREPANCY (Quantity/Amount Mismatch)</option>
                            <option value="REJECTED">❌ REJECTED (Supplier Rejected Return)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" data-lang="preturn_modal_cn_no">Credit Note (CN) Serial No</label>
                        <input type="text" name="cn_no" id="cn_no_input" class="form-control fw-bold" placeholder="e.g. CN-2026-9812">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold" data-lang="preturn_modal_cn_date">CN Issued Date</label>
                            <input type="date" name="cn_date" id="cn_date_input" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" data-lang="preturn_modal_cn_amt">CN Value Amount (RM)</label>
                            <input type="number" step="0.01" name="cn_amount" id="cn_amount_input" class="form-control fw-bold" placeholder="0.00">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" data-lang="preturn_modal_cn_doc">Upload CN Document / Photo</label>
                        <input type="file" name="cn_file" class="form-control" accept="image/*,.pdf">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" data-lang="preturn_modal_cn_notes">Reconciliation Notes</label>
                        <textarea name="cn_notes" id="cn_notes_input" class="form-control" rows="2" placeholder="Record any notes or discrepancy comments..."></textarea>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><span data-lang="btn_cancel">Cancel</span></button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark"><i class="bi bi-save me-1"></i> <span data-lang="preturn_modal_btn_save_cn">Save CN Status</span></button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════ -->
<!-- MODAL 3: VIEW RECORD DETAILS & PHOTO GALLERY                     -->
<!-- ════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="viewDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:16px; overflow:hidden;">
            <div class="modal-header bg-navy text-white px-4 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-text me-2 text-warning"></i><span data-lang="preturn_modal_details">Pallet Return Details & Evidence Gallery</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                
                <div id="detail_modal_body">
                    <!-- Loaded dynamically via JS -->
                </div>

            </div>
            <div class="modal-footer bg-white d-flex justify-content-between">
                <button type="button" class="btn btn-outline-danger btn-sm" id="btn_delete_return" onclick="deleteReturnRecord()"><i class="bi bi-trash me-1"></i> <span data-lang="preturn_modal_btn_del">Delete Record</span></button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><span data-lang="preturn_modal_btn_close">Close</span></button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Form for Printing PRF Slip -->
<form id="printPrfForm" action="print_prf_slip.php" method="POST" target="_blank" class="d-none">
    <input type="hidden" name="return_id" id="print_return_id">
</form>

<script>
let itemRowIndex = 1;

function addPalletItemRow(presetType = '', presetQty = 1) {
    const tbody = document.getElementById('palletItemsTbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <input type="text" name="items[${itemRowIndex}][pallet_type]" class="form-control form-control-sm fw-bold" list="pallet_types_datalist" value="${presetType}" placeholder="Type or select pallet (e.g. Loscam Red, Plain Wood)" required>
        </td>
        <td>
            <input type="number" name="items[${itemRowIndex}][quantity]" class="form-control form-control-sm text-center fw-bold item-qty" min="1" value="${presetQty}" oninput="calculateFormTotalQty()" required>
        </td>
        <td>
            <select name="items[${itemRowIndex}][pallet_condition]" class="form-select form-select-sm" required>
                <option value="Good / Sound">Good / Sound</option>
                <option value="Damaged">Damaged</option>
                <option value="Repairable">Repairable</option>
                <option value="Broken / Scrap">Broken / Scrap</option>
            </select>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removePalletItemRow(this)"><i class="bi bi-trash"></i></button>
        </td>
    `;
    tbody.appendChild(tr);
    itemRowIndex++;
    calculateFormTotalQty();
}

function removePalletItemRow(btn) {
    const tbody = document.getElementById('palletItemsTbody');
    if (tbody.querySelectorAll('tr').length > 1) {
        btn.closest('tr').remove();
        calculateFormTotalQty();
    } else {
        alert("You must have at least one pallet item row.");
    }
}

function calculateFormTotalQty() {
    let total = 0;
    document.querySelectorAll('.item-qty').forEach(input => {
        let val = parseInt(input.value) || 0;
        total += val;
    });
    document.getElementById('formTotalQtyDisplay').innerText = total;
}

function handleSupplierSelectChange(selectEl) {
    const newDiv = document.getElementById('new_supplier_input_container');
    const newInp = document.getElementById('new_supplier_name_input');
    if (selectEl.value === '__NEW__') {
        newDiv.classList.remove('d-none');
        newInp.setAttribute('required', 'required');
        newInp.focus();
    } else {
        newDiv.classList.add('d-none');
        newInp.removeAttribute('required');
        newInp.value = '';
    }
}

function openAddReturnModal() {
    const now = new Date();
    const dateStr = now.getFullYear() + String(now.getMonth() + 1).padStart(2, '0') + String(now.getDate()).padStart(2, '0');
    const randNum = String(Math.floor(Math.random() * 900) + 100);
    document.getElementById('add_prf_no').value = 'PRF-' + dateStr + '-' + randNum;
    
    // Reset supplier select
    const supSelect = document.getElementById('supplier_select_input');
    if (supSelect) {
        supSelect.value = '';
        handleSupplierSelectChange(supSelect);
    }

    const myModal = new bootstrap.Modal(document.getElementById('addReturnModal'));
    myModal.show();
}

function openUpdateCnModal(row) {
    document.getElementById('cn_return_id').value = row.id;
    document.getElementById('cn_modal_prf_display').innerText = 'PRF #: ' + row.prf_no;
    document.getElementById('cn_modal_supplier_display').innerText = 'Supplier: ' + row.supplier_name + ' | Total: ' + row.total_quantity + ' pallets';
    
    document.getElementById('cn_status_select').value = row.cn_status || 'PENDING';
    document.getElementById('cn_no_input').value = row.cn_no || '';
    document.getElementById('cn_date_input').value = row.cn_date || '';
    document.getElementById('cn_amount_input').value = row.cn_amount || '';
    document.getElementById('cn_notes_input').value = row.cn_notes || '';

    const myModal = new bootstrap.Modal(document.getElementById('updateCnModal'));
    myModal.show();
}

let activeDetailRowId = null;

function viewReturnDetails(row) {
    activeDetailRowId = row.id;
    
    // Fetch line items and photos via AJAX
    $.getJSON('api/get_pallet_return_details.php?return_id=' + row.id, function(data) {
        let items = data.items || [];
        let photos = data.photos || [];

        let itemsHtml = '<table class="table table-sm table-bordered mt-2"><thead class="table-light small fw-bold"><tr><th data-lang="preturn_modal_type">Pallet Type</th><th class="text-center" data-lang="preturn_modal_qty">Qty</th><th data-lang="preturn_modal_cond">Condition</th></tr></thead><tbody>';
        if (items.length > 0) {
            items.forEach(it => {
                itemsHtml += `<tr><td class="fw-bold">${it.pallet_type}</td><td class="text-center fw-bold">${it.quantity}</td><td>${it.pallet_condition}</td></tr>`;
            });
        } else {
            itemsHtml += `<tr><td colspan="3" class="text-center text-muted"><span data-lang="prf_slip_no_items">No line items</span></td></tr>`;
        }
        itemsHtml += '</tbody></table>';

        let photosHtml = '';
        if (photos && photos.length > 0) {
            photosHtml = '<div class="row g-2 mt-2">';
            photos.forEach((p, idx) => {
                photosHtml += `
                    <div class="col-md-3 col-6 text-center">
                        <a href="${p.photo_path}" target="_blank" title="View Full Stack Photo">
                            <img src="${p.photo_path}" class="img-fluid rounded border shadow-sm" style="height: 120px; width:100%; object-fit: cover;">
                        </a>
                        <small class="text-muted d-block mt-1">Stack #${idx + 1}</small>
                    </div>
                `;
            });
            photosHtml += '</div>';
        } else {
            photosHtml = '<p class="text-muted small italic"><span data-lang="prf_slip_photos">No stack evidence photos uploaded.</span></p>';
        }

        let prfDocHtml = row.prf_form_file ? `<a href="${row.prf_form_file}" target="_blank" class="btn btn-sm btn-primary fw-bold"><i class="bi bi-file-earmark-check-fill me-1"></i> View Signed PRF Document</a>` : '<span class="badge bg-danger">Missing PRF Document</span>';
        let cnDocHtml = row.cn_file ? `<a href="${row.cn_file}" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-receipt me-1"></i> View Credit Note Attachment</a>` : '<span class="text-muted small">No CN document attached</span>';

        let html = `
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                        <div>
                            <h5 class="fw-bold text-navy mb-0">PRF #${row.prf_no}</h5>
                            <small class="text-muted">Return Date: ${row.return_date}</small>
                        </div>
                        <div>
                            <span class="badge bg-primary fs-6 px-3 py-1.5">${row.total_quantity} Total Pallets</span>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless small mb-0">
                                <tr><td class="text-muted fw-bold" style="width:40%;">Supplier:</td><td class="fw-bold text-navy">${row.supplier_name}</td></tr>
                                <tr><td class="text-muted fw-bold">Transporter:</td><td class="fw-bold text-dark">${row.transporter_name}</td></tr>
                                <tr><td class="text-muted fw-bold">Driver Name:</td><td><strong>${row.driver_name}</strong></td></tr>
                                <tr><td class="text-muted fw-bold">Vehicle Plate:</td><td><code>${row.vehicle_plate}</code></td></tr>
                                <tr><td class="text-muted fw-bold">Logged By:</td><td>${row.created_by}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded border">
                                <span class="fw-bold text-navy small d-block mb-1"><i class="bi bi-file-earmark-pdf text-danger me-1"></i>Signed PRF Form Document:</span>
                                ${prfDocHtml}
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold text-navy mb-1"><i class="bi bi-box-seam me-1 text-warning"></i><span data-lang="preturn_modal_items_brk">Pallet Items Breakdown</span></h6>
                    ${itemsHtml}

                    ${row.remarks ? `<div class="p-2 bg-light border rounded small mt-2"><strong>Remarks:</strong> ${row.remarks}</div>` : ''}
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-3 bg-white">
                    <h6 class="fw-bold text-navy mb-2"><i class="bi bi-camera me-1 text-success"></i><span data-lang="preturn_modal_stack">Trailer Stack Proof Photos</span></h6>
                    ${photosHtml}
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-3 bg-white">
                    <h6 class="fw-bold text-navy mb-2"><i class="bi bi-receipt-cutoff me-1 text-warning"></i><span data-lang="preturn_modal_cn_recon">Credit Note (CN) Reconciliation</span></h6>
                    <div class="row g-2 small">
                        <div class="col-md-4"><strong>CN Status:</strong> <span class="badge bg-dark">${row.cn_status}</span></div>
                        <div class="col-md-4"><strong>CN Number:</strong> ${row.cn_no || 'Not Issued'}</div>
                        <div class="col-md-4"><strong>CN Amount:</strong> RM ${parseFloat(row.cn_amount || 0).toFixed(2)}</div>
                        <div class="col-md-12 mt-2"><strong>CN Attachment:</strong> ${cnDocHtml}</div>
                        ${row.cn_notes ? `<div class="col-md-12 mt-1"><strong>CN Notes:</strong> ${row.cn_notes}</div>` : ''}
                    </div>
                </div>
            </div>
        `;

        document.getElementById('detail_modal_body').innerHTML = html;
        const myModal = new bootstrap.Modal(document.getElementById('viewDetailModal'));
        myModal.show();
    });
}

function deleteReturnRecord() {
    if (!activeDetailRowId) return;
    Swal.fire({
        title: 'Delete Return Record?',
        text: 'Adakah anda pasti untuk memadam rekod pemulangan palet ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Ya, Padam'
    }).then((res) => {
        if (res.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'pallet_return.php';
            form.innerHTML = `<input type="hidden" name="action" value="delete_return"><input type="hidden" name="return_id" value="${activeDetailRowId}">`;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function printPrfSlip(row) {
    document.getElementById('print_return_id').value = row.id;
    document.getElementById('printPrfForm').submit();
}

// Camera & Gallery Photo Upload Triggers & Live Previews
function triggerCamPrf() {
    const el = document.getElementById('prf_camera_input');
    el.removeAttribute('disabled');
    el.click();
}

function triggerGalleryPrf() {
    const el = document.getElementById('prf_gallery_input');
    el.click();
}

function syncSinglePhoto(sourceEl, targetId, previewId) {
    if (sourceEl.files && sourceEl.files[0]) {
        const target = document.getElementById(targetId);
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(sourceEl.files[0]);
        target.files = dataTransfer.files;
        previewSinglePhoto(target, previewId);
    }
}

function previewSinglePhoto(inputEl, previewId) {
    const container = document.getElementById(previewId);
    container.innerHTML = '';
    if (inputEl.files && inputEl.files[0]) {
        const file = inputEl.files[0];
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                container.innerHTML = `<div class="d-flex align-items-center gap-2 p-2 border rounded bg-white mt-2" style="max-width: 350px;"><img src="${e.target.result}" style="height: 70px; width: 70px; object-fit: cover; border-radius: 6px;"><div class="small fw-bold text-success"><i class="bi bi-check-circle-fill me-1"></i>${file.name}</div></div>`;
            };
            reader.readAsDataURL(file);
        } else {
            container.innerHTML = `<div class="p-2 border rounded bg-white text-primary small fw-bold mt-2"><i class="bi bi-file-earmark-pdf me-1"></i>${file.name}</div>`;
        }
    }
}

function triggerCamStack() {
    const el = document.getElementById('stack_camera_input');
    el.click();
}

function triggerGalleryStack() {
    const el = document.getElementById('stack_photos_input');
    el.click();
}

function appendCameraPhoto(camInput, targetInputId, previewId) {
    if (camInput.files && camInput.files[0]) {
        const target = document.getElementById(targetInputId);
        const dataTransfer = new DataTransfer();
        
        if (target.files) {
            for (let i = 0; i < target.files.length; i++) {
                dataTransfer.items.add(target.files[i]);
            }
        }
        dataTransfer.items.add(camInput.files[0]);
        target.files = dataTransfer.files;
        previewMultiPhotos(target, previewId);
    }
}

function previewMultiPhotos(inputEl, previewId) {
    const container = document.getElementById(previewId);
    container.innerHTML = '';
    if (inputEl.files && inputEl.files.length > 0) {
        Array.from(inputEl.files).forEach((file, idx) => {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'col-md-3 col-6 text-center';
                    div.innerHTML = `<div class="p-1 border rounded bg-white shadow-sm"><img src="${e.target.result}" style="height: 90px; width: 100%; object-fit: cover; border-radius: 6px;"><small class="text-muted d-block mt-1 text-truncate">Stack #${idx + 1}</small></div>`;
                    container.appendChild(div);
                };
                reader.readAsDataURL(file);
            }
        });
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
