<?php
// product_management.php - MASTER PRODUCT CATALOG (UPGRADED INTERFACE)
require_once 'config/db.php';

// Auto-fix table products schema & id AUTO_INCREMENT if corrupted or missing in database
try {
    $zero_check = $pdo->query("SELECT COUNT(*) FROM products WHERE id = 0")->fetchColumn();
    if ($zero_check > 0) {
        $max_id = (int)$pdo->query("SELECT MAX(id) FROM products")->fetchColumn();
        $next_id = max($max_id + 1, 500);
        $pdo->exec("UPDATE products SET id = $next_id WHERE id = 0");
    }
    $pdo->exec("ALTER TABLE products MODIFY id INT(11) NOT NULL AUTO_INCREMENT");

    // Auto-migration for permitted_location & permitted_sub_location columns
    $c1 = $pdo->query("SHOW COLUMNS FROM products LIKE 'permitted_location'")->fetch();
    if (!$c1) {
        $pdo->exec("ALTER TABLE products ADD COLUMN `permitted_location` VARCHAR(255) DEFAULT NULL AFTER `category`");
    }
    $c2 = $pdo->query("SHOW COLUMNS FROM products LIKE 'permitted_sub_location'")->fetch();
    if (!$c2) {
        $pdo->exec("ALTER TABLE products ADD COLUMN `permitted_sub_location` VARCHAR(255) DEFAULT NULL AFTER `permitted_location`");
    }
} catch (Exception $ex) {
    // Abaikan jika tiada kebenaran ALTER
}

// Helper to parse multi-select checkboxes & custom text inputs for permitted locations
function get_posted_permitted_values($prefix) {
    $vals = [];
    if (isset($_POST[$prefix . '_opts']) && is_array($_POST[$prefix . '_opts'])) {
        foreach ($_POST[$prefix . '_opts'] as $v) {
            $v = trim($v);
            if ($v !== '' && !in_array($v, $vals)) $vals[] = $v;
        }
    }
    if (!empty($_POST[$prefix])) {
        $custom_items = explode(',', $_POST[$prefix]);
        foreach ($custom_items as $ci) {
            $ci = trim($ci);
            if ($ci !== '' && !in_array($ci, $vals)) {
                $vals[] = $ci;
            }
        }
    }
    return implode(', ', $vals);
}

// Handle product updates (Edit Product Form POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_product') {
    $product_id = (int)$_POST['id'];
    $barcode = trim($_POST['barcode'] ?? '');
    $qrcode = trim($_POST['qrcode'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $permitted_location = get_posted_permitted_values('permitted_location');
    $permitted_sub_location = get_posted_permitted_values('permitted_sub_location');
    $uom = trim($_POST['uom'] ?? 'Carton');
    $pack_size = (int)($_POST['pack_size'] ?? 1);
    $pallet_capacity = (int)($_POST['pallet_capacity'] ?? 60);
    
    if (empty($category)) {
        $error_msg = "Category is required.";
    } else {
        try {
            // Ambil maklumat produk lama untuk perbandingan log
            $old_stmt = $pdo->prepare("SELECT name, barcode FROM products WHERE id = ? LIMIT 1");
            $old_stmt->execute([$product_id]);
            $old_product = $old_stmt->fetch();
            $product_name = $old_product['name'] ?? 'Unknown';
            $old_barcode = $old_product['barcode'] ?? '';

            $stmt = $pdo->prepare("UPDATE products SET barcode = ?, qrcode = ?, category = ?, permitted_location = ?, permitted_sub_location = ?, uom = ?, pack_size = ?, pallet_capacity = ? WHERE id = ?");
            $stmt->execute([$barcode, $qrcode, $category, $permitted_location, $permitted_sub_location, $uom, $pack_size, $pallet_capacity, $product_id]);
            $success_msg = "Product updated successfully under category '$category'!";
            $_GET['category'] = $category;
            
            if (function_exists('log_system_activity')) {
                $username = $_SESSION['username'] ?? 'system';
                if (empty($old_barcode) && !empty($barcode)) {
                    log_system_activity("Added Barcode", "products", $product_id, "$username add barcode for product $product_name");
                } else {
                    log_system_activity("Updated Product", "products", $product_id, "$username updated product $product_name");
                }
            }
        } catch (PDOException $e) {
            $error_msg = "Database error: " . $e->getMessage();
        }
    }
}

// Handle standalone category creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    $new_cat_name = trim($_POST['category_name'] ?? '');
    $initial_product = trim($_POST['initial_product_name'] ?? '');
    $cat_permitted_location = get_posted_permitted_values('permitted_location');
    $cat_permitted_sub_location = get_posted_permitted_values('permitted_sub_location');
    
    if (empty($new_cat_name)) {
        $error_msg = "Category name is required.";
    } else {
        try {
            // Check if category already exists
            $check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category = ?");
            $check->execute([$new_cat_name]);
            if ($check->fetchColumn() > 0) {
                if (!empty($cat_permitted_location) || !empty($cat_permitted_sub_location)) {
                    $upCat = $pdo->prepare("UPDATE products SET permitted_location = ?, permitted_sub_location = ? WHERE category = ? AND (permitted_location IS NULL OR permitted_location = '')");
                    $upCat->execute([$cat_permitted_location, $cat_permitted_sub_location, $new_cat_name]);
                }
                $success_msg = "Category '$new_cat_name' updated in the catalog!";
                $_GET['category'] = $new_cat_name;
            } else {
                $prod_name = !empty($initial_product) ? $initial_product : ($new_cat_name . " Initial SKU");
                $stmt = $pdo->prepare("INSERT INTO products (name, category, permitted_location, permitted_sub_location, uom, pack_size, pallet_capacity, is_active) VALUES (?, ?, ?, ?, 'Carton', 1, 60, 1)");
                $stmt->execute([$prod_name, $new_cat_name, $cat_permitted_location, $cat_permitted_sub_location]);
                $success_msg = "New category '$new_cat_name' registered successfully!";
                $_GET['category'] = $new_cat_name;
            }
        } catch (PDOException $e) {
            $error_msg = "Database error: " . $e->getMessage();
        }
    }
}

// Handle product creation (Add Product Form POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    $name = trim($_POST['name'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $qrcode = trim($_POST['qrcode'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $permitted_location = get_posted_permitted_values('permitted_location');
    $permitted_sub_location = get_posted_permitted_values('permitted_sub_location');
    $uom = trim($_POST['uom'] ?? 'Carton');
    $pack_size = (int)($_POST['pack_size'] ?? 1);
    $pallet_capacity = (int)($_POST['pallet_capacity'] ?? 60);
    
    if (empty($name) || empty($category)) {
        $error_msg = "Product Name and Category are required.";
    } else {
        try {
            // Check if product with same name already exists
            $check = $pdo->prepare("SELECT id FROM products WHERE name = ? LIMIT 1");
            $check->execute([$name]);
            if ($check->fetch()) {
                $error_msg = "A product with this name already exists.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO products (name, barcode, qrcode, category, permitted_location, permitted_sub_location, uom, pack_size, pallet_capacity, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$name, $barcode, $qrcode, $category, $permitted_location, $permitted_sub_location, $uom, $pack_size, $pallet_capacity]);
                $new_id = $pdo->lastInsertId();
                $success_msg = "Product created successfully under category '$category'!";
                $_GET['category'] = $category; // Auto-set filter so newly added product/category shows immediately
                
                if (function_exists('log_system_activity')) {
                    $username = $_SESSION['username'] ?? 'system';
                    log_system_activity("Created Product", "products", $new_id, "$username add new product $name (Category: $category)");
                }
            }
        } catch (PDOException $e) {
            $error_msg = "Database error: " . $e->getMessage();
        }
    }
}

// Handle status toggles
if (isset($_GET['toggle_id'])) {
    if (function_exists('check_write_permission') && !check_write_permission('product_management.php')) {
        header("Location: product_management.php?error=no_permission");
        exit;
    }
    
    // Dapatkan nama produk dan status asal untuk catatan log
    $p_stmt = $pdo->prepare("SELECT name, is_active FROM products WHERE id = ? LIMIT 1");
    $p_stmt->execute([$_GET['toggle_id']]);
    $p = $p_stmt->fetch();
    $p_name = $p['name'] ?? 'Unknown';
    $p_action = $p['is_active'] ? 'deactivate' : 'activate';

    $stmt = $pdo->prepare("UPDATE products SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$_GET['toggle_id']]);
    
    if (function_exists('log_system_activity')) {
        $username = $_SESSION['username'] ?? 'system';
        log_system_activity("Toggled Product Status", "products", $_GET['toggle_id'], "$username $p_action product $p_name");
    }
    
    $q = $_GET;
    unset($q['toggle_id']);
    $qs = http_build_query($q);
    header("Location: product_management.php" . ($qs ? "?" . $qs : ""));
    exit;
}

$categories = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

// Fetch distinct Main Locations & Sub-Locations from database + standard list safely
$main_locations_set = ['JC Barn', 'Kedai Jomcha', 'Store Area', 'PSS Zone', 'COM Zone', 'POW Zone'];
try {
    $c1 = $pdo->query("SHOW COLUMNS FROM products LIKE 'permitted_location'")->fetch();
    if ($c1) {
        $main_locations_raw = $pdo->query("SELECT DISTINCT permitted_location FROM products WHERE permitted_location IS NOT NULL AND permitted_location != ''")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($main_locations_raw as $loc_item) {
            $loc_item = trim($loc_item);
            if ($loc_item !== '' && !in_array($loc_item, $main_locations_set)) {
                $main_locations_set[] = $loc_item;
            }
        }
    }
} catch (Exception $ex) {}
sort($main_locations_set);

$sub_locations_set = ['Rack', 'Chiller 1', 'Chiller 2', 'Freezer (Meat)', 'Freezer (Ice Cream)', 'Pallet 1', 'Pallet 2', 'Chiller', 'Freezer', 'Pallet'];
try {
    $c2 = $pdo->query("SHOW COLUMNS FROM products LIKE 'permitted_sub_location'")->fetch();
    if ($c2) {
        $sub_locations_raw = $pdo->query("SELECT DISTINCT permitted_sub_location FROM products WHERE permitted_sub_location IS NOT NULL AND permitted_sub_location != ''")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($sub_locations_raw as $sub_item) {
            $sub_item = trim($sub_item);
            if ($sub_item !== '' && !in_array($sub_item, $sub_locations_set)) {
                $sub_locations_set[] = $sub_item;
            }
        }
    }
} catch (Exception $ex) {}
sort($sub_locations_set);
$search = $_GET['search'] ?? '';
$cat_filter = $_GET['category'] ?? '';

$sql = "SELECT * FROM products WHERE 1=1";
$params = [];
if ($search) {
    $sql .= " AND (name LIKE ? OR category LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($cat_filter) { $sql .= " AND category = ?"; $params[] = $cat_filter; }
$sql .= " ORDER BY category ASC, name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
$page_title = 'Product Management | MMS';
require_once 'includes/header.php';
?>
<style>
/* Modern Selectable Pill Checkboxes for Permitted Locations */
.location-pills-container {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 10px;
    border: 1px solid #dee2e6;
    border-radius: 12px;
    background-color: #f8f9fa;
    max-height: 125px;
    overflow-y: auto;
}

.loc-pill-item {
    position: relative;
    user-select: none;
}

.loc-pill-item input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.loc-pill-label {
    display: inline-flex;
    align-items: center;
    padding: 5px 12px;
    font-size: 0.78rem;
    font-weight: 700;
    color: #495057;
    background-color: #fff;
    border: 1px solid #ced4da;
    border-radius: 30px;
    cursor: pointer;
    transition: all 0.15s ease-in-out;
}

.loc-pill-label:hover {
    background-color: #f1f3f5;
    border-color: #adb5bd;
}

/* Selected state for Main Locations (Blue) */
.loc-pill-item.loc-pill-primary input[type="checkbox"]:checked + .loc-pill-label {
    background-color: #e7f1ff;
    color: #0d6efd;
    border-color: #0d6efd;
    box-shadow: 0 2px 4px rgba(13, 110, 253, 0.1);
}

.loc-pill-item.loc-pill-primary input[type="checkbox"]:checked + .loc-pill-label::before {
    content: "\F272"; /* bootstrap-icons check-circle-fill */
    font-family: "bootstrap-icons";
    margin-right: 5px;
    font-size: 0.85rem;
}

/* Selected state for Sub Locations (Green) */
.loc-pill-item.loc-pill-success input[type="checkbox"]:checked + .loc-pill-label {
    background-color: #e8f5e9;
    color: #198754;
    border-color: #198754;
    box-shadow: 0 2px 4px rgba(25, 135, 84, 0.1);
}

.loc-pill-item.loc-pill-success input[type="checkbox"]:checked + .loc-pill-label::before {
    content: "\F272"; /* bootstrap-icons check-circle-fill */
    font-family: "bootstrap-icons";
    margin-right: 5px;
    font-size: 0.85rem;
}
</style>

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-box-seam-fill me-2"></i>Product Master</h1>
                <p class="opacity-75 mb-0 fw-light">Moo Moo Supplies Catalog Management</p>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> Dashboard</a>
                <button type="button" class="btn btn-outline-light fw-bold" onclick="openCategoryModal()"><i class="bi bi-folder-plus me-1"></i> + New Category</button>
                <button type="button" class="btn btn-success fw-bold text-white" onclick="openAddModal()"><i class="bi bi-plus-lg me-1"></i> Add Product</button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    <?php if (isset($success_msg)): ?>
        <div class="alert alert-success border-0 shadow-sm mb-3">
            <i class="bi bi-check-circle-fill me-2 text-success"></i><?= htmlspecialchars($success_msg ?? '') ?>
        </div>
    <?php endif; ?>
    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-3">
            <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i><?= htmlspecialchars($error_msg ?? '') ?>
        </div>
    <?php endif; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sessionKey = 'mms_filter_state_product_management.php';
        const urlParams = new URLSearchParams(window.location.search);
        
        // If clear_filter is set in URL, clear sessionStorage and redirect to clean URL
        if (urlParams.has('clear_filter')) {
            if (typeof sessionStorage !== 'undefined') {
                sessionStorage.removeItem(sessionKey);
            }
            window.location.href = 'product_management.php';
            return;
        }
        
        // If we are on the base page with no filters, restore the saved filter state if available
        if (window.location.search === '') {
            if (typeof sessionStorage !== 'undefined') {
                const savedFilter = sessionStorage.getItem(sessionKey);
                if (savedFilter && savedFilter !== '?category=') {
                    window.location.href = 'product_management.php' + savedFilter;
                    return;
                }
            }
        }
        
        // If we have active filters in URL, save them to sessionStorage
        if (urlParams.has('category') || urlParams.has('search')) {
            if (typeof sessionStorage !== 'undefined') {
                sessionStorage.setItem(sessionKey, window.location.search);
            }
        }

        // Show SweetAlert alerts if set
        <?php if (isset($success_msg)): ?>
            Swal.fire({
                title: 'Success!',
                text: <?= json_encode($success_msg) ?>,
                icon: 'success',
                confirmButtonColor: '#198754'
            });
        <?php endif; ?>
        <?php if (isset($error_msg)): ?>
            Swal.fire({
                title: 'Notice / Error!',
                text: <?= json_encode($error_msg) ?>,
                icon: 'error',
                confirmButtonColor: '#dc3545'
            });
        <?php endif; ?>
    });
    </script>
    
    <div class="card main-card border-0 mb-4">
        <div class="card-body p-3">
            <form class="row g-2 align-items-center" method="GET" action="product_management.php">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by name or category..." value="<?= htmlspecialchars($search ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="category" class="form-select">
                        <option value="">-- All Categories --</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat ?? '') ?>" <?= $cat_filter == $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-dark w-100 fw-bold">APPLY FILTERS</button>
                    <?php if ($search || $cat_filter): ?>
                        <a href="product_management.php?clear_filter=1" class="btn btn-outline-secondary fw-bold" title="Reset Filters"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-navy"><i class="bi bi-list-ul me-2"></i>Product List</h5>
            <span class="badge bg-light text-dark border"><?= count($products) ?> Total Products</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Product Info</th>
                            <th>Category</th>
                            <th>Permitted Locations</th>
                            <th class="text-center">UOM</th>
                            <th>Barcode</th>
                            <th>QR Code</th>
                            <th class="text-center">Pack Size</th>
                            <th class="text-center">Pallet Cap</th>
                            <th class="text-center">Status</th>
                            <th class="text-center pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr><td colspan="10" class="text-center py-5 text-muted">No products found in the catalog.</td></tr>
                        <?php else: 
                            foreach($products as $p): 
                                $p_loc = $p['permitted_location'] ?? '';
                                $p_sub_loc = $p['permitted_sub_location'] ?? '';
                                if (empty($p_loc) && empty($p_sub_loc)) {
                                    $cat_u = strtoupper(trim($p['category']));
                                    if (strpos($cat_u, 'BEEF') !== false || strpos($cat_u, 'MEAT') !== false || strpos($cat_u, 'FROZEN') !== false || strpos($cat_u, 'ICE') !== false) {
                                        $p_loc = 'Store Area';
                                        $p_sub_loc = 'Freezer';
                                    } elseif (strpos($cat_u, 'CHILLED') !== false || strpos($cat_u, 'MILK') !== false || strpos($cat_u, 'DAIRY') !== false || strpos($cat_u, 'PST') !== false) {
                                        $p_loc = 'JC Barn';
                                        $p_sub_loc = 'Chiller';
                                    } else {
                                        $p_loc = 'Store Area';
                                        $p_sub_loc = 'Rack, Pallet';
                                    }
                                }
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded p-2 d-flex align-items-center justify-content-center me-3 fs-5">
                                            <i class="bi bi-box"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($p['name'] ?? '') ?></div>
                                            <small class="text-muted">ID: #<?= $p['id'] ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fw-bold text-uppercase" style="font-size: 0.72rem;"><?= htmlspecialchars($p['category'] ?? '') ?></span></td>
                                <td>
                                    <div class="d-flex flex-column gap-1 align-items-start">
                                        <?php if ($p_loc): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fw-bold" style="font-size: 0.72rem;">
                                                <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($p_loc) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($p_sub_loc): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fw-bold" style="font-size: 0.72rem;">
                                                <i class="bi bi-box-seam me-1"></i><?= htmlspecialchars($p_sub_loc) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <small class="fw-bold text-uppercase"><?= htmlspecialchars($p['uom'] ?? '') ?></small>
                                </td>
                                <td>
                                    <small class="font-monospace text-secondary"><?= htmlspecialchars($p['barcode'] ?? '-') ?></small>
                                </td>
                                <td>
                                    <small class="font-monospace text-secondary"><?= htmlspecialchars($p['qrcode'] ?? '-') ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold text-primary"><?= $p['pack_size'] ?></span>
                                    <div style="font-size: 10px;" class="text-muted text-uppercase">Units/Ctn</div>
                                </td>
                                <td class="text-center fw-bold text-secondary"><?= $p['pallet_capacity'] ?></td>
                                <td class="text-center">
                                    <?php if($p['is_active']): ?>
                                        <span class="badge rounded-pill bg-success px-3 py-2 border border-success">
                                            <i class="bi bi-check-circle-fill me-1"></i> Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill bg-secondary px-3 py-2 border border-secondary">
                                            <i class="bi bi-x-circle-fill me-1"></i> Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center pe-4">
                                    <button type="button" 
                                            class="btn btn-sm <?= $p['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>" 
                                            title="<?= $p['is_active'] ? 'Deactivate' : 'Activate' ?>"
                                            onclick="confirmToggle(<?= $p['id'] ?>, <?= $p['is_active'] ? 'true' : 'false' ?>, '<?= htmlspecialchars(addslashes($p['name'] ?? '')) ?>')">
                                        <i class="bi <?= $p['is_active'] ? 'bi-lock' : 'bi-unlock' ?>"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary" 
                                            title="Edit Product" 
                                            onclick="openEditModal(this)"
                                            data-id="<?= $p['id'] ?>"
                                            data-name="<?= htmlspecialchars($p['name'] ?? '') ?>"
                                            data-category="<?= htmlspecialchars($p['category'] ?? '') ?>"
                                            data-permitted-location="<?= htmlspecialchars($p['permitted_location'] ?? '') ?>"
                                            data-permitted-sub-location="<?= htmlspecialchars($p['permitted_sub_location'] ?? '') ?>"
                                            data-uom="<?= htmlspecialchars($p['uom'] ?? '') ?>"
                                            data-pack-size="<?= $p['pack_size'] ?>"
                                            data-pallet-capacity="<?= $p['pallet_capacity'] ?>"
                                            data-barcode="<?= htmlspecialchars($p['barcode'] ?? '') ?>"
                                            data-qrcode="<?= htmlspecialchars($p['qrcode'] ?? '') ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; 
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Datalist for existing product categories -->
<datalist id="product_categories_list">
    <?php foreach($categories as $cat): ?>
        <option value="<?= htmlspecialchars($cat ?? '') ?>">
    <?php endforeach; ?>
</datalist>

<!-- Datalist for Permitted Main Locations (Rooms / Areas) -->
<datalist id="permitted_main_locations_list">
    <?php foreach($main_locations_set as $loc_opt): ?>
        <option value="<?= htmlspecialchars($loc_opt) ?>">
    <?php endforeach; ?>
</datalist>

<!-- Datalist for Permitted Sub-Locations (Equipment / Slots) -->
<datalist id="permitted_sub_locations_list">
    <?php foreach($sub_locations_set as $sub_opt): ?>
        <option value="<?= htmlspecialchars($sub_opt) ?>">
    <?php endforeach; ?>
</datalist>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="product_management.php">
            <input type="hidden" name="action" value="add_category">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="addCategoryModalLabel"><i class="bi bi-folder-plus me-2"></i>Register New Category</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">New Category Name *</label>
                        <input type="text" name="category_name" class="form-control text-uppercase" placeholder="e.g. FROZEN, SYRUP, BEVERAGE, BEEF" required>
                        <div class="form-text">Enter the new category code or name to register into the system catalog.</div>
                    </div>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-primary"><i class="bi bi-geo-alt me-1"></i>Permitted Main Location(s) (Room/Area)</label>
                            <div class="location-pills-container mb-2">
                                <?php foreach($main_locations_set as $m_idx => $m_loc): ?>
                                    <div class="loc-pill-item loc-pill-primary">
                                        <input class="perm-loc-chk" type="checkbox" name="permitted_location_opts[]" value="<?= htmlspecialchars($m_loc) ?>" id="cat_loc_chk_<?= md5($m_loc . '_' . $m_idx) ?>">
                                        <label class="loc-pill-label" for="cat_loc_chk_<?= md5($m_loc . '_' . $m_idx) ?>"><?= htmlspecialchars($m_loc) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="text" name="permitted_location" class="form-control form-control-sm perm-loc-input" list="permitted_main_locations_list" placeholder="Or type custom location(s), comma separated">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-success"><i class="bi bi-box-seam me-1"></i>Permitted Sub-Location(s) (Equipment)</label>
                            <div class="location-pills-container mb-2">
                                <?php foreach($sub_locations_set as $s_idx => $s_loc): ?>
                                    <div class="loc-pill-item loc-pill-success">
                                        <input class="perm-sub-chk" type="checkbox" name="permitted_sub_location_opts[]" value="<?= htmlspecialchars($s_loc) ?>" id="cat_sub_chk_<?= md5($s_loc . '_' . $s_idx) ?>">
                                        <label class="loc-pill-label" for="cat_sub_chk_<?= md5($s_loc . '_' . $s_idx) ?>"><?= htmlspecialchars($s_loc) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="text" name="permitted_sub_location" class="form-control form-control-sm perm-sub-input" list="permitted_sub_locations_list" placeholder="Or type custom sub-location(s), comma separated">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">First Product / SKU Name (Optional)</label>
                        <input type="text" name="initial_product_name" class="form-control" placeholder="e.g. Frozen Beef Patty 1kg">
                        <div class="form-text">Leave blank to automatically create a default catalog item for this category.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-check-circle me-1"></i> Save Category</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="product_management.php">
            <input type="hidden" name="action" value="edit_product">
            <input type="hidden" name="id" id="edit-id">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--gradient-primary); color: white;">
                    <h5 class="modal-title fw-bold" id="editProductModalLabel"><i class="bi bi-pencil-square me-2 text-cyan"></i>Edit Product Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Product Name</label>
                        <input type="text" id="edit-name" class="form-control bg-light" readonly>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Category *</label>
                            <input type="text" name="category" id="edit-category" list="product_categories_list" class="form-control text-uppercase" placeholder="Select or type new category" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">UOM</label>
                            <input type="text" name="uom" id="edit-uom" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-primary"><i class="bi bi-geo-alt me-1"></i>Permitted Main Location(s) (Room/Area)</label>
                            <div class="location-pills-container mb-2">
                                <?php foreach($main_locations_set as $m_idx => $m_loc): ?>
                                    <div class="loc-pill-item loc-pill-primary">
                                        <input class="perm-loc-chk" type="checkbox" name="permitted_location_opts[]" value="<?= htmlspecialchars($m_loc) ?>" id="edit_loc_chk_<?= md5($m_loc . '_' . $m_idx) ?>">
                                        <label class="loc-pill-label" for="edit_loc_chk_<?= md5($m_loc . '_' . $m_idx) ?>"><?= htmlspecialchars($m_loc) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="text" name="permitted_location" id="edit-permitted-location" class="form-control form-control-sm perm-loc-input" list="permitted_main_locations_list" placeholder="Or type custom location(s), comma separated">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-success"><i class="bi bi-box-seam me-1"></i>Permitted Sub-Location(s) (Equipment)</label>
                            <div class="location-pills-container mb-2">
                                <?php foreach($sub_locations_set as $s_idx => $s_loc): ?>
                                    <div class="loc-pill-item loc-pill-success">
                                        <input class="perm-sub-chk" type="checkbox" name="permitted_sub_location_opts[]" value="<?= htmlspecialchars($s_loc) ?>" id="edit_sub_chk_<?= md5($s_loc . '_' . $s_idx) ?>">
                                        <label class="loc-pill-label" for="edit_sub_chk_<?= md5($s_loc . '_' . $s_idx) ?>"><?= htmlspecialchars($s_loc) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="text" name="permitted_sub_location" id="edit-permitted-sub-location" class="form-control form-control-sm perm-sub-input" list="permitted_sub_locations_list" placeholder="Or type custom sub-location(s), comma separated">
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Pack Size</label>
                            <input type="number" name="pack_size" id="edit-pack-size" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Pallet Capacity</label>
                            <input type="number" name="pallet_capacity" id="edit-pallet-capacity" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-primary">Barcode</label>
                        <input type="text" name="barcode" id="edit-barcode" class="form-control" placeholder="Scan or enter barcode value">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-info">QR Code (Unique SKU ID)</label>
                        <input type="text" name="qrcode" id="edit-qrcode" class="form-control" placeholder="Enter QR code unique ID (e.g. O2CW1CO-0100S32A)">
                        <div class="form-text small text-muted">The unique segment of your QR structure (e.g., text after <strong>GGGITN</strong> and before <strong>/BAN</strong>). Example: <code>O2CW1CO-0100S32A</code></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: var(--mms-navy); border: none;">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="product_management.php">
            <input type="hidden" name="action" value="add_product">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--gradient-primary); color: white;">
                    <h5 class="modal-title fw-bold" id="addProductModalLabel"><i class="bi bi-plus-circle me-2 text-cyan"></i>Add New Product</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Product Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Chocolate Milk 125ml" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Category *</label>
                            <input type="text" name="category" id="add-category" list="product_categories_list" class="form-control text-uppercase" placeholder="Select or type new category (e.g. BEEF, FROZEN, UHT)" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">UOM</label>
                            <input type="text" name="uom" class="form-control" value="Carton" placeholder="Carton / PCS" required>
                        </div>
                    </div>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-primary"><i class="bi bi-geo-alt me-1"></i>Permitted Main Location(s) (Room/Area)</label>
                            <div class="location-pills-container mb-2">
                                <?php foreach($main_locations_set as $m_idx => $m_loc): ?>
                                    <div class="loc-pill-item loc-pill-primary">
                                        <input class="perm-loc-chk" type="checkbox" name="permitted_location_opts[]" value="<?= htmlspecialchars($m_loc) ?>" id="add_loc_chk_<?= md5($m_loc . '_' . $m_idx) ?>">
                                        <label class="loc-pill-label" for="add_loc_chk_<?= md5($m_loc . '_' . $m_idx) ?>"><?= htmlspecialchars($m_loc) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="text" name="permitted_location" class="form-control form-control-sm perm-loc-input" list="permitted_main_locations_list" placeholder="Or type custom location(s), comma separated">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-success"><i class="bi bi-box-seam me-1"></i>Permitted Sub-Location(s) (Equipment)</label>
                            <div class="location-pills-container mb-2">
                                <?php foreach($sub_locations_set as $s_idx => $s_loc): ?>
                                    <div class="loc-pill-item loc-pill-success">
                                        <input class="perm-sub-chk" type="checkbox" name="permitted_sub_location_opts[]" value="<?= htmlspecialchars($s_loc) ?>" id="add_sub_chk_<?= md5($s_loc . '_' . $s_idx) ?>">
                                        <label class="loc-pill-label" for="add_sub_chk_<?= md5($s_loc . '_' . $s_idx) ?>"><?= htmlspecialchars($s_loc) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="text" name="permitted_sub_location" class="form-control form-control-sm perm-sub-input" list="permitted_sub_locations_list" placeholder="Or type custom sub-location(s), comma separated">
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Pack Size (Units/Ctn)</label>
                            <input type="number" name="pack_size" class="form-control" value="1" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Pallet Capacity</label>
                            <input type="number" name="pallet_capacity" class="form-control" value="60" min="1" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-primary">Barcode</label>
                        <input type="text" name="barcode" class="form-control" placeholder="Scan or enter barcode value (optional)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-info">QR Code (Unique SKU ID)</label>
                        <input type="text" name="qrcode" class="form-control" placeholder="Enter QR code unique ID (optional)">
                        <div class="form-text small text-muted">The unique segment of your QR structure (e.g., text after <strong>GGGITN</strong> and before <strong>/BAN</strong>). Example: <code>O2CW1CO-0100S32A</code></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success text-white fw-bold">Add Product</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function setMultiSelectFields(modalId, locVal, subLocVal) {
    let locList = (locVal || '').split(',').map(s => s.trim());
    let subList = (subLocVal || '').split(',').map(s => s.trim());

    $(`#${modalId} .perm-loc-chk`).prop('checked', false);
    $(`#${modalId} .perm-sub-chk`).prop('checked', false);

    let customLocs = [];
    locList.forEach(l => {
        if (!l) return;
        let matched = false;
        $(`#${modalId} .perm-loc-chk`).each(function() {
            if ($(this).val().toLowerCase() === l.toLowerCase()) {
                $(this).prop('checked', true);
                matched = true;
            }
        });
        if (!matched) customLocs.push(l);
    });
    $(`#${modalId} .perm-loc-input`).val(customLocs.join(', '));

    let customSubs = [];
    subList.forEach(s => {
        if (!s) return;
        let matched = false;
        $(`#${modalId} .perm-sub-chk`).each(function() {
            if ($(this).val().toLowerCase() === s.toLowerCase()) {
                $(this).prop('checked', true);
                matched = true;
            }
        });
        if (!matched) customSubs.push(s);
    });
    $(`#${modalId} .perm-sub-input`).val(customSubs.join(', '));
}

function openCategoryModal() {
    $('#addCategoryModal form')[0].reset();
    setMultiSelectFields('addCategoryModal', '', '');
    const myModal = new bootstrap.Modal(document.getElementById('addCategoryModal'));
    myModal.show();
}

function openAddModal(prefilledCategory = '') {
    $('#addProductModal form')[0].reset();
    setMultiSelectFields('addProductModal', '', '');
    if (prefilledCategory && prefilledCategory !== '__new__') {
        $('#add-category').val(prefilledCategory);
    }
    const myModal = new bootstrap.Modal(document.getElementById('addProductModal'));
    myModal.show();
}

function openEditModal(btn) {
    const id = $(btn).data('id');
    const name = $(btn).data('name');
    const category = $(btn).data('category');
    const permittedLocation = $(btn).data('permitted-location');
    const permittedSubLocation = $(btn).data('permitted-sub-location');
    const uom = $(btn).data('uom');
    const packSize = $(btn).data('pack-size');
    const palletCapacity = $(btn).data('pallet-capacity');
    const barcode = $(btn).data('barcode');
    const qrcode = $(btn).data('qrcode');
    
    $('#edit-id').val(id);
    $('#edit-name').val(name);
    $('#edit-category').val(category);
    $('#edit-uom').val(uom);
    $('#edit-pack-size').val(packSize);
    $('#edit-pallet-capacity').val(palletCapacity);
    $('#edit-barcode').val(barcode);
    $('#edit-qrcode').val(qrcode);

    setMultiSelectFields('editProductModal', permittedLocation, permittedSubLocation);
    
    const myModal = new bootstrap.Modal(document.getElementById('editProductModal'));
    myModal.show();
}

function confirmToggle(id, isActive, name) {
    const actionText = isActive ? 'Nyahaktif' : 'Aktifkan';
    const color = isActive ? '#dc3545' : '#198754';
    const icon = isActive ? 'warning' : 'question';
    
    Swal.fire({
        title: `${actionText} Produk?`,
        html: `Adakah anda pasti untuk <b>${actionText.toLowerCase()}</b> produk:<br><span class="text-navy fw-bold">${name}</span>?`,
        icon: icon,
        showCancelButton: true,
        confirmButtonColor: color,
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Teruskan',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `?toggle_id=${id}`;
        }
    });
}
</script>
<?php require_once 'includes/footer.php'; ?>