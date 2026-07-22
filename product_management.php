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
} catch (Exception $ex) {}

// Auto-migration for products table columns
try {
    $c1 = $pdo->query("SHOW COLUMNS FROM products LIKE 'permitted_location'")->fetch();
    if (!$c1) {
        $pdo->exec("ALTER TABLE products ADD COLUMN `permitted_location` VARCHAR(500) DEFAULT NULL AFTER `category`");
    } else {
        $pdo->exec("ALTER TABLE products MODIFY COLUMN `permitted_location` VARCHAR(500) DEFAULT NULL");
    }
} catch (Exception $ex) {}

try {
    $c3 = $pdo->query("SHOW COLUMNS FROM products LIKE 'sub_category'")->fetch();
    if (!$c3) {
        $pdo->exec("ALTER TABLE products ADD COLUMN `sub_category` VARCHAR(255) DEFAULT NULL AFTER `category`");
    }
} catch (Exception $ex) {}

// Auto-migration for product_categories table & location / sub_categories columns
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_categories (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        category_name VARCHAR(255) NOT NULL UNIQUE,
        permitted_location VARCHAR(500) DEFAULT NULL,
        permitted_sub_location VARCHAR(500) DEFAULT NULL,
        sub_categories TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $ex) {}

try {
    $pdo->exec("ALTER TABLE product_categories ADD COLUMN `permitted_location` VARCHAR(500) DEFAULT NULL AFTER `category_name`");
} catch (Exception $ex) {}

try {
    $pdo->exec("ALTER TABLE product_categories ADD COLUMN `permitted_sub_location` VARCHAR(500) DEFAULT NULL AFTER `permitted_location`");
} catch (Exception $ex) {}

try {
    $pdo->exec("ALTER TABLE product_categories ADD COLUMN `sub_categories` TEXT DEFAULT NULL AFTER `permitted_sub_location`");
} catch (Exception $ex) {}

// Helper to keep product_categories table in sync with categories, sub_categories & permitted locations
function sync_category_master($pdo, $category_name, $permitted_location = null, $permitted_sub_location = null, $sub_categories_list = null) {
    $category_name = trim($category_name);
    if (empty($category_name)) return;

    try {
        $pdo->exec("ALTER TABLE product_categories ADD COLUMN `permitted_location` VARCHAR(500) DEFAULT NULL");
        $pdo->exec("ALTER TABLE product_categories ADD COLUMN `permitted_sub_location` VARCHAR(500) DEFAULT NULL");
        $pdo->exec("ALTER TABLE product_categories ADD COLUMN `sub_categories` TEXT DEFAULT NULL");
    } catch (Exception $ex) {}

    try {
        $check = $pdo->prepare("SELECT id, permitted_location, permitted_sub_location, sub_categories FROM product_categories WHERE LOWER(category_name) = LOWER(?) LIMIT 1");
        $check->execute([$category_name]);
        $existing = $check->fetch();

        if ($existing) {
            $p_loc = ($permitted_location !== null && $permitted_location !== '') ? $permitted_location : ($existing['permitted_location'] ?? '');
            $p_sub_loc = ($permitted_sub_location !== null && $permitted_sub_location !== '') ? $permitted_sub_location : ($existing['permitted_sub_location'] ?? '');
            $p_sub_cats = ($sub_categories_list !== null && $sub_categories_list !== '') ? $sub_categories_list : ($existing['sub_categories'] ?? '');
            
            $up = $pdo->prepare("UPDATE product_categories SET permitted_location = ?, permitted_sub_location = ?, sub_categories = ? WHERE id = ?");
            $up->execute([$p_loc, $p_sub_loc, $p_sub_cats, $existing['id']]);
        } else {
            $ins = $pdo->prepare("INSERT INTO product_categories (category_name, permitted_location, permitted_sub_location, sub_categories) VALUES (?, ?, ?, ?)");
            $ins->execute([$category_name, $permitted_location, $permitted_sub_location, $sub_categories_list]);
        }
    } catch (Exception $ex) {
        try {
            $ins = $pdo->prepare("INSERT IGNORE INTO product_categories (category_name) VALUES (?)");
            $ins->execute([$category_name]);
        } catch (Exception $e2) {}
    }
}

// Pre-seed user requested Main Categories & Sub-Categories
$preseeded_categories = [
    'UHT' => ['UHT 100ml', 'UHT 115ml', 'UHT 125ml', 'UHT 180ml', 'UHT 200ml', 'UHT 1L'],
    'PST' => ['PST 200ml', 'PST 568ml', 'PST 700ml', 'PST 1L', 'PST 2L'],
    'Powder' => ['Powder 35gm', 'Powder 500gm', 'Powder 800gm', 'Powder 1kg', 'Powder 2kg'],
    'IC' => ['IC 60ml', 'IC 75ml', 'IC 109ml'],
    'Butter' => ['Butter 9gm', 'Butter 200gm'],
    'Cooking' => ['Cooking 200ml', 'Cooking 1L'],
    'Yogurt' => ['Yogurt 120gm', 'Yogurt 400gm', 'Yogurt 470gm', 'Yogurt 1.4kg'],
    'Glass' => ['Glass 200ml', 'Glass 1L'],
    'Merchandise' => ['Merch GiftBox', 'Merch Fridge Magnet']
];

foreach ($preseeded_categories as $m_cat => $sub_arr) {
    $sub_str = implode(', ', $sub_arr);
    sync_category_master($pdo, $m_cat, null, null, $sub_str);
}

// Back-fill existing categories from products table into product_categories table safely
try {
    $all_cats = $pdo->query("SELECT DISTINCT category, permitted_location, permitted_sub_location FROM products WHERE category IS NOT NULL AND category != ''")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all_cats as $ac) {
        sync_category_master($pdo, $ac['category'], $ac['permitted_location'], $ac['permitted_sub_location']);
    }
} catch (Exception $ex) {}

$debug_post_log = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debug_post_log = [
        'action' => $_POST['action'] ?? 'UNKNOWN',
        'location_opts' => $_POST['permitted_location_opts'] ?? [],
        'location_custom' => $_POST['permitted_location'] ?? '',
        'location_final' => get_posted_permitted_values('permitted_location'),
        'sub_location_opts' => $_POST['permitted_sub_location_opts'] ?? [],
        'sub_location_custom' => $_POST['permitted_sub_location'] ?? '',
        'sub_location_final' => get_posted_permitted_values('permitted_sub_location'),
    ];
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

// Helper to resolve category and sub_category from select dropdowns or custom input text
function resolve_posted_category_and_sub() {
    $category = trim($_POST['category_select'] ?? '');
    if ($category === '__new__' || empty($category)) {
        $category = trim($_POST['category_custom'] ?? $_POST['category'] ?? '');
    }

    $sub_category = trim($_POST['sub_category_select'] ?? '');
    if ($sub_category === '__new__') {
        $sub_category = trim($_POST['sub_category_custom'] ?? $_POST['sub_category'] ?? '');
    }
    
    return [$category, $sub_category];
}

// Handle product updates (Edit Product Form POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_product') {
    $product_id = (int)$_POST['id'];
    $barcode = trim($_POST['barcode'] ?? '');
    $qrcode = trim($_POST['qrcode'] ?? '');
    list($category, $sub_category) = resolve_posted_category_and_sub();
    $permitted_location = get_posted_permitted_values('permitted_location');
    $permitted_sub_location = get_posted_permitted_values('permitted_sub_location');
    $uom = trim($_POST['uom'] ?? 'Carton');
    $pack_size = (int)($_POST['pack_size'] ?? 1);
    $pallet_capacity = (int)($_POST['pallet_capacity'] ?? 60);
    $sync_cat = isset($_POST['sync_category']) && $_POST['sync_category'] == '1';
    
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

            $stmt = $pdo->prepare("UPDATE products SET barcode = ?, qrcode = ?, category = ?, sub_category = ?, permitted_location = ?, permitted_sub_location = ?, uom = ?, pack_size = ?, pallet_capacity = ? WHERE id = ?");
            $stmt->execute([$barcode, $qrcode, $category, $sub_category, $permitted_location, $permitted_sub_location, $uom, $pack_size, $pallet_capacity, $product_id]);
            
            if ($sync_cat && !empty($category)) {
                if (!empty($sub_category)) {
                    $upCat = $pdo->prepare("UPDATE products SET permitted_location = ?, permitted_sub_location = ? WHERE LOWER(category) = LOWER(?) AND LOWER(sub_category) = LOWER(?)");
                    $upCat->execute([$permitted_location, $permitted_sub_location, $category, $sub_category]);
                } else {
                    $upCat = $pdo->prepare("UPDATE products SET permitted_location = ?, permitted_sub_location = ? WHERE LOWER(category) = LOWER(?)");
                    $upCat->execute([$permitted_location, $permitted_sub_location, $category]);
                }
            }
            
            // Sync into product_categories master table
            sync_category_master($pdo, $category, $permitted_location, $permitted_sub_location, $sub_category);
            
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
    $sub_categories = trim($_POST['sub_categories'] ?? '');
    $initial_product = trim($_POST['initial_product_name'] ?? '');
    $cat_permitted_location = get_posted_permitted_values('permitted_location');
    $cat_permitted_sub_location = get_posted_permitted_values('permitted_sub_location');
    
    if (empty($new_cat_name)) {
        $error_msg = "Category name is required.";
    } else {
        try {
            // Register / Sync directly into product_categories table!
            sync_category_master($pdo, $new_cat_name, $cat_permitted_location, $cat_permitted_sub_location, $sub_categories);

            // Check if category already exists in products table
            $check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category = ?");
            $check->execute([$new_cat_name]);
            if ($check->fetchColumn() > 0) {
                if (!empty($cat_permitted_location) || !empty($cat_permitted_sub_location)) {
                    $upCat = $pdo->prepare("UPDATE products SET permitted_location = ?, permitted_sub_location = ? WHERE category = ?");
                    $upCat->execute([$cat_permitted_location, $cat_permitted_sub_location, $new_cat_name]);
                }
                $success_msg = "Category '$new_cat_name' registered & updated in the catalog!";
                $_GET['category'] = $new_cat_name;
            } else {
                $prod_name = !empty($initial_product) ? $initial_product : ($new_cat_name . " Initial SKU");
                $stmt = $pdo->prepare("INSERT INTO products (name, category, permitted_location, permitted_sub_location, uom, pack_size, pallet_capacity, is_active) VALUES (?, ?, ?, ?, 'Carton', 1, 60, 1)");
                $stmt->execute([$prod_name, $new_cat_name, $cat_permitted_location, $cat_permitted_sub_location]);
                $success_msg = "New category '$new_cat_name' registered successfully in product_categories table!";
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
    list($category, $sub_category) = resolve_posted_category_and_sub();
    $permitted_location = get_posted_permitted_values('permitted_location');
    $permitted_sub_location = get_posted_permitted_values('permitted_sub_location');
    $uom = trim($_POST['uom'] ?? 'Carton');
    $pack_size = (int)($_POST['pack_size'] ?? 1);
    $pallet_capacity = (int)($_POST['pallet_capacity'] ?? 60);
    $sync_cat = isset($_POST['sync_category']) && $_POST['sync_category'] == '1';
    
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
                $stmt = $pdo->prepare("INSERT INTO products (name, barcode, qrcode, category, sub_category, permitted_location, permitted_sub_location, uom, pack_size, pallet_capacity, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$name, $barcode, $qrcode, $category, $sub_category, $permitted_location, $permitted_sub_location, $uom, $pack_size, $pallet_capacity]);
                $new_id = $pdo->lastInsertId();
                
                if ($sync_cat && !empty($category)) {
                    if (!empty($sub_category)) {
                        $upCat = $pdo->prepare("UPDATE products SET permitted_location = ?, permitted_sub_location = ? WHERE LOWER(category) = LOWER(?) AND LOWER(sub_category) = LOWER(?)");
                        $upCat->execute([$permitted_location, $permitted_sub_location, $category, $sub_category]);
                    } else {
                        $upCat = $pdo->prepare("UPDATE products SET permitted_location = ?, permitted_sub_location = ? WHERE LOWER(category) = LOWER(?)");
                        $upCat->execute([$permitted_location, $permitted_sub_location, $category]);
                    }
                }

                // Sync into product_categories master table
                sync_category_master($pdo, $category, $permitted_location, $permitted_sub_location, $sub_category);
                
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

$categories = [];
try {
    $cats_pc = $pdo->query("SELECT category_name FROM product_categories WHERE category_name IS NOT NULL AND category_name != '' ORDER BY category_name ASC")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($cats_pc as $c) {
        $c = trim($c);
        if ($c !== '' && !in_array($c, $categories)) $categories[] = $c;
    }
} catch (Exception $ex) {}
try {
    $cats_p = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($cats_p as $c) {
        $c = trim($c);
        if ($c !== '' && !in_array($c, $categories)) $categories[] = $c;
    }
} catch (Exception $ex) {}
sort($categories);

$category_location_map = [];
try {
    $rows_pc = $pdo->query("SELECT category_name, permitted_location, permitted_sub_location, sub_categories FROM product_categories")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows_pc as $rpc) {
        $c_name = trim($rpc['category_name']);
        if ($c_name !== '') {
            $category_location_map[$c_name] = [
                'permitted_location' => $rpc['permitted_location'] ?? '',
                'permitted_sub_location' => $rpc['permitted_sub_location'] ?? '',
                'sub_categories' => $rpc['sub_categories'] ?? ''
            ];
        }
    }
} catch (Exception $ex) {}

try {
    $rows_prod_cat = $pdo->query("SELECT category, GROUP_CONCAT(DISTINCT permitted_location SEPARATOR ', ') as locs, GROUP_CONCAT(DISTINCT permitted_sub_location SEPARATOR ', ') as sub_locs FROM products WHERE category IS NOT NULL AND category != '' GROUP BY category")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows_prod_cat as $rpc) {
        $c_name = trim($rpc['category']);
        if ($c_name !== '' && !isset($category_location_map[$c_name])) {
            $category_location_map[$c_name] = [
                'permitted_location' => $rpc['locs'] ?? '',
                'permitted_sub_location' => $rpc['sub_locs'] ?? '',
                'sub_categories' => ''
            ];
        }
    }
} catch (Exception $ex) {}

// Fetch distinct Main Locations & Sub-Locations from database + standard list safely
$main_locations_set = ['Kedai Jomcha', 'Store Area', 'JC Barn', 'PSS Zone', 'COM Zone', 'POW Zone'];
try {
    $c1 = $pdo->query("SHOW COLUMNS FROM products LIKE 'permitted_location'")->fetch();
    if ($c1) {
        $main_locations_raw = $pdo->query("SELECT DISTINCT permitted_location FROM products WHERE permitted_location IS NOT NULL AND permitted_location != ''")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($main_locations_raw as $loc_item) {
            $parts = explode(',', $loc_item);
            foreach ($parts as $p_val) {
                $p_val = trim($p_val);
                if ($p_val !== '' && !in_array($p_val, $main_locations_set)) {
                    $main_locations_set[] = $p_val;
                }
            }
        }
    }
    $pc_locs = $pdo->query("SELECT DISTINCT permitted_location FROM product_categories WHERE permitted_location IS NOT NULL AND permitted_location != ''")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($pc_locs as $loc_item) {
        $parts = explode(',', $loc_item);
        foreach ($parts as $p_val) {
            $p_val = trim($p_val);
            if ($p_val !== '' && !in_array($p_val, $main_locations_set)) {
                $main_locations_set[] = $p_val;
            }
        }
    }
} catch (Exception $ex) {}
sort($main_locations_set);

$standard_sub_list = ['Freezer (Meat)', 'Freezer (Ice Cream)', 'Chiller 1', 'Chiller 2', 'Pallet 1', 'Pallet 2', 'Pallet', 'Rack', 'Barn Chiller 1', 'Barn Chiller 2', 'Barn Rack', 'Freezer 1', 'Freezer 2', 'Store Chiller 1', 'Store Chiller 2', 'Store Rack'];
$custom_sub_locations = [];

try {
    $c2 = $pdo->query("SHOW COLUMNS FROM products LIKE 'permitted_sub_location'")->fetch();
    if ($c2) {
        $sub_locations_raw = $pdo->query("SELECT DISTINCT permitted_sub_location FROM products WHERE permitted_sub_location IS NOT NULL AND permitted_sub_location != ''")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($sub_locations_raw as $sub_item) {
            $parts = explode(',', $sub_item);
            foreach ($parts as $p_val) {
                $p_val = trim($p_val);
                if ($p_val !== '' && !in_array($p_val, $standard_sub_list) && !in_array($p_val, $custom_sub_locations)) {
                    $custom_sub_locations[] = $p_val;
                }
            }
        }
    }
    $pc_sub_locs = $pdo->query("SELECT DISTINCT permitted_sub_location FROM product_categories WHERE permitted_sub_location IS NOT NULL AND permitted_sub_location != ''")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($pc_sub_locs as $sub_item) {
        $parts = explode(',', $sub_item);
        foreach ($parts as $p_val) {
            $p_val = trim($p_val);
            if ($p_val !== '' && !in_array($p_val, $standard_sub_list) && !in_array($p_val, $custom_sub_locations)) {
                $custom_sub_locations[] = $p_val;
            }
        }
    }
} catch (Exception $ex) {}
sort($custom_sub_locations);
$cat_subcat_map = [];
try {
    $rows_pc = $pdo->query("SELECT category_name, sub_categories FROM product_categories")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows_pc as $rpc) {
        $c_name = trim($rpc['category_name']);
        if ($c_name !== '') {
            $sub_list = [];
            if (!empty($rpc['sub_categories'])) {
                $parts = explode(',', $rpc['sub_categories']);
                foreach ($parts as $p) {
                    $p = trim($p);
                    if ($p !== '' && !in_array($p, $sub_list)) $sub_list[] = $p;
                }
            }
            $cat_subcat_map[$c_name] = $sub_list;
        }
    }
} catch (Exception $ex) {}

try {
    $rows_prod_subs = $pdo->query("SELECT DISTINCT category, sub_category FROM products WHERE category IS NOT NULL AND category != '' AND sub_category IS NOT NULL AND sub_category != ''")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows_prod_subs as $rps) {
        $c_name = trim($rps['category']);
        $s_name = trim($rps['sub_category']);
        if ($c_name !== '' && $s_name !== '') {
            if (!isset($cat_subcat_map[$c_name])) {
                $cat_subcat_map[$c_name] = [];
            }
            if (!in_array($s_name, $cat_subcat_map[$c_name])) {
                $cat_subcat_map[$c_name][] = $s_name;
            }
        }
    }
} catch (Exception $ex) {}

$search = $_GET['search'] ?? '';
$cat_filter = $_GET['category'] ?? '';
$sub_cat_filter = $_GET['sub_category'] ?? '';

// Build dependent list of sub-categories for PHP initial page render
$sub_categories_list = [];
if (!empty($cat_filter)) {
    foreach ($cat_subcat_map as $c_name => $subs) {
        if (strtoupper(trim($c_name)) === strtoupper(trim($cat_filter))) {
            $sub_categories_list = array_merge($sub_categories_list, $subs);
        }
    }
} else {
    foreach ($cat_subcat_map as $c_name => $subs) {
        $sub_categories_list = array_merge($sub_categories_list, $subs);
    }
}
$sub_categories_list = array_unique($sub_categories_list);
sort($sub_categories_list);

$sql = "SELECT * FROM products WHERE 1=1";
$params = [];
if ($search) {
    $sql .= " AND (name LIKE ? OR category LIKE ? OR sub_category LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($cat_filter) { $sql .= " AND category = ?"; $params[] = $cat_filter; }
if ($sub_cat_filter) { $sql .= " AND sub_category = ?"; $params[] = $sub_cat_filter; }
$sql .= " ORDER BY category ASC, sub_category ASC, name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$page_title = 'Product Management | MMS';
require_once 'includes/header.php';
?>

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-box-seam-fill me-2"></i>Product Master</h1>
                <p class="opacity-75 mb-0 fw-light">Moo Moo Supplies Catalog Management</p>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> Dashboard</a>
                <button type="button" class="btn btn-outline-light fw-bold" onclick="openCategoryModal()"><i class="bi bi-folder2-open me-1"></i> Manage / New Category</button>
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

    <?php if (isset($success_msg) || isset($error_msg)): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sessionKey = 'mms_filter_state_product_management.php';
            <?php if (isset($success_msg)): ?>
                if (typeof sessionStorage !== 'undefined') {
                    sessionStorage.setItem(sessionKey, '?category=' + encodeURIComponent(<?= json_encode($cat_filter ?? '') ?>));
                }
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
    <?php endif; ?>
    
    <div class="card main-card border-0 mb-4" style="margin-top: 10px;">
        <div class="card-body p-3">
            <form class="row g-2 align-items-center" method="GET" action="product_management.php">
                <div class="col-md-4">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0 py-2" data-lang-placeholder="prod_search_ph" placeholder="Search by name, category..." value="<?= htmlspecialchars($search ?? '') ?>">
                        <button type="submit" class="d-none" data-lang="prod_btn_search">Search</button>
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="category" id="main_category_filter_select" class="form-select py-2 shadow-sm" onchange="this.form.submit()">
                        <option value="" data-lang="prod_filter_cat">-- All Categories --</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat ?? '') ?>" <?= strtoupper($cat_filter) == strtoupper($cat) ? 'selected' : '' ?>><?= htmlspecialchars($cat ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="sub_category" id="sub_category_filter_select" class="form-select py-2 shadow-sm" onchange="this.form.submit()">
                        <option value="" data-lang="prod_filter_subcat">-- All Sub-Categories --</option>
                        <?php foreach($sub_categories_list as $sc): ?>
                            <option value="<?= htmlspecialchars($sc ?? '') ?>" <?= strtoupper($sub_cat_filter) == strtoupper($sc) ? 'selected' : '' ?>><?= htmlspecialchars($sc ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
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
            <div class="table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Product Info</th>
                            <th data-lang="prod_col_cat">Category</th>
                            <th>Permitted Locations</th>
                            <th class="text-center" data-lang="prod_col_uom">UOM</th>
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
                                    } elseif (strpos($cat_u, 'CHILLED') !== false || strpos($cat_u, 'MILK') !== false || strpos($cat_u, 'DAIRY') !== false) {
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
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fw-bold text-uppercase" style="font-size: 0.72rem;"><?= htmlspecialchars($p['category'] ?? '') ?></span>
                                    <?php if (!empty($p['sub_category'])): ?>
                                        <div class="mt-1">
                                            <span class="badge bg-secondary-subtle text-dark border px-2 py-1 fw-bold" style="font-size: 0.68rem;"><i class="bi bi-tag-fill text-muted me-1"></i><?= htmlspecialchars($p['sub_category']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1 align-items-start">
                                        <?php if ($p_loc): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fw-bold text-wrap text-start" style="font-size: 0.72rem; max-width: 280px; word-break: break-word; line-height: 1.35;">
                                                <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($p_loc) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($p_sub_loc): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fw-bold text-wrap text-start" style="font-size: 0.72rem; max-width: 280px; word-break: break-word; line-height: 1.35;">
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
                                            data-sub-category="<?= htmlspecialchars($p['sub_category'] ?? '') ?>"
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
                    <h5 class="modal-title fw-bold" id="addCategoryModalLabel"><i class="bi bi-folder2-open me-2"></i>Register / Manage Category Locations</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Category to Manage or Enter New Category Name *</label>
                        <input type="text" name="category_name" id="cat_modal_name_input" list="product_categories_list" class="form-control text-uppercase" placeholder="Select existing category or type new (e.g. UHT, BEEF, FROZEN, COOKING)" required onchange="autoLoadCategoryLocations(this.value)" oninput="autoLoadCategoryLocations(this.value)">
                        <div class="form-text" data-lang="prod_modal_cat_desc">Choose any previously created category (or type a new name) to view & update its assigned storage locations.</div>
                    </div>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-primary"><i class="bi bi-geo-alt me-1"></i><span data-lang="prod_modal_loc_main">Permitted Main Location(s) (Room/Area)</span></label>
                            <div class="p-2 border rounded bg-light" style="max-height: 160px; overflow-y: auto;">
                                <?php foreach($main_locations_set as $m_idx => $m_loc): ?>
                                    <div class="form-check m-0 mb-1">
                                        <input class="form-check-input perm-loc-chk" type="checkbox" name="permitted_location_opts[]" value="<?= htmlspecialchars($m_loc) ?>" id="cat_loc_chk_<?= md5($m_loc . '_' . $m_idx) ?>">
                                        <label class="form-check-label small fw-bold" for="cat_loc_chk_<?= md5($m_loc . '_' . $m_idx) ?>"><?= htmlspecialchars($m_loc) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="text" name="permitted_location" class="form-control form-control-sm perm-loc-input mt-2" list="permitted_main_locations_list" placeholder="Or type custom location(s), comma separated">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-success"><i class="bi bi-box-seam me-1"></i><span data-lang="prod_modal_loc_sub">Permitted Sub-Location(s) (Equipment/Slot)</span></label>
                            <div class="p-2 border rounded bg-light" style="max-height: 160px; overflow-y: auto;">
                                <div class="fw-bold text-primary small border-bottom pb-1 mb-1"><i class="bi bi-shop me-1"></i>Kedai Jomcha Equipment:</div>
                                <?php foreach(['Freezer (Meat)', 'Freezer (Ice Cream)', 'Chiller 1', 'Chiller 2', 'Rack'] as $s_idx => $s_loc): ?>
                                    <div class="form-check form-check-inline m-0 me-2 mb-1">
                                        <input class="form-check-input perm-sub-chk" type="checkbox" name="permitted_sub_location_opts[]" value="<?= htmlspecialchars($s_loc) ?>" id="cat_sub_mms_<?= md5($s_loc . '_' . $s_idx) ?>">
                                        <label class="form-check-label small" for="cat_sub_mms_<?= md5($s_loc . '_' . $s_idx) ?>"><?= htmlspecialchars($s_loc) ?></label>
                                    </div>
                                <?php endforeach; ?>

                                <div class="fw-bold text-primary small border-bottom pb-1 mb-1 mt-2"><i class="bi bi-building me-1"></i>Store Area Equipment:</div>
                                <?php foreach(['Pallet 1', 'Pallet 2', 'Pallet', 'Chiller 1', 'Chiller 2', 'Freezer 1', 'Freezer 2', 'Rack'] as $s_idx => $s_loc): ?>
                                    <div class="form-check form-check-inline m-0 me-2 mb-1">
                                        <input class="form-check-input perm-sub-chk" type="checkbox" name="permitted_sub_location_opts[]" value="<?= htmlspecialchars($s_loc) ?>" id="cat_sub_sa_<?= md5($s_loc . '_' . $s_idx) ?>">
                                        <label class="form-check-label small" for="cat_sub_sa_<?= md5($s_loc . '_' . $s_idx) ?>"><?= htmlspecialchars($s_loc) ?></label>
                                    </div>
                                <?php endforeach; ?>

                                <div class="fw-bold text-primary small border-bottom pb-1 mb-1 mt-2"><i class="bi bi-cup-hot me-1"></i>JC Barn Equipment:</div>
                                <?php foreach(['Barn Chiller 1', 'Barn Chiller 2', 'Barn Rack'] as $s_idx => $s_loc): ?>
                                    <div class="form-check form-check-inline m-0 me-2 mb-1">
                                        <input class="form-check-input perm-sub-chk" type="checkbox" name="permitted_sub_location_opts[]" value="<?= htmlspecialchars($s_loc) ?>" id="cat_sub_jcb_<?= md5($s_loc . '_' . $s_idx) ?>">
                                        <label class="form-check-label small" for="cat_sub_jcb_<?= md5($s_loc . '_' . $s_idx) ?>"><?= htmlspecialchars($s_loc) ?></label>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (!empty($custom_sub_locations)): ?>
                                    <div class="fw-bold text-success small border-bottom pb-1 mb-1 mt-2"><i class="bi bi-plus-circle me-1"></i>Custom / Registered Equipment:</div>
                                    <?php foreach($custom_sub_locations as $cs_idx => $cs_loc): ?>
                                        <div class="form-check form-check-inline m-0 me-2 mb-1">
                                            <input class="form-check-input perm-sub-chk" type="checkbox" name="permitted_sub_location_opts[]" value="<?= htmlspecialchars($cs_loc) ?>" id="cat_sub_custom_<?= md5($cs_loc . '_' . $cs_idx) ?>">
                                            <label class="form-check-label small fw-bold text-success" for="cat_sub_custom_<?= md5($cs_loc . '_' . $cs_idx) ?>"><?= htmlspecialchars($cs_loc) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <input type="text" name="permitted_sub_location" class="form-control form-control-sm perm-sub-input mt-2" list="permitted_sub_locations_list" placeholder="Or type custom sub-location(s), comma separated">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark"><i class="bi bi-tags me-1 text-primary"></i><span data-lang="prod_modal_subcat">Sub-Categories / Size Variants (Comma Separated)</span></label>
                        <input type="text" name="sub_categories" id="cat_modal_sub_cats_input" class="form-control form-control-sm" placeholder="e.g. UHT 100ml, UHT 115ml, UHT 125ml, UHT 180ml, UHT 200ml, UHT 1L">
                        <div class="form-text small text-muted" data-lang="prod_modal_subcat_desc">List the sub-categories or size variants for this category.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" data-lang="prod_modal_first_prod">First Product / SKU Name (Optional)</label>
                        <input type="text" name="initial_product_name" class="form-control" placeholder="e.g. Frozen Beef Patty 1kg">
                        <div class="form-text" data-lang="prod_modal_first_prod_desc">Leave blank to automatically create a default catalog item for this category.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><span data-lang="btn_cancel">Cancel</span></button>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-check-circle me-1"></i> <span data-lang="prod_modal_btn_save_cat">Save Category</span></button>
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
                    <h5 class="modal-title fw-bold" id="editProductModalLabel"><i class="bi bi-pencil-square me-2 text-cyan"></i><span data-lang="prod_modal_edit">Edit Product Details</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold" data-lang="prod_modal_prod_name">Product Name</label>
                        <input type="text" id="edit-name" class="form-control bg-light" readonly>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-5 mb-3">
                            <label class="form-label fw-bold" data-lang="prod_modal_main_cat">Main Category *</label>
                            <select name="category_select" id="edit-category-select" class="form-select" onchange="onCategorySelectChange('edit', this.value)" required>
                                <option value="" data-lang="prod_modal_sel_cat">-- Select Category --</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat ?? '') ?>"><?= htmlspecialchars($cat ?? '') ?></option>
                                <?php endforeach; ?>
                                <option value="__new__" class="fw-bold text-primary" data-lang="prod_modal_new_cat">+ Add New Main Category...</option>
                            </select>
                            <input type="text" name="category_custom" id="edit-category-custom" class="form-control form-control-sm mt-2 text-uppercase d-none" placeholder="Type new category name...">
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label fw-bold" data-lang="prod_modal_sub_size">Sub-Category / Size</label>
                            <select name="sub_category_select" id="edit-sub-category-select" class="form-select" onchange="onSubCategorySelectChange('edit', this.value)">
                                <option value="" data-lang="prod_modal_sel_subcat">-- Select Sub-Category --</option>
                                <option value="__new__" class="fw-bold text-primary" data-lang="prod_modal_new_subcat">+ Add New Sub-Category...</option>
                            </select>
                            <input type="text" name="sub_category_custom" id="edit-sub-category-custom" class="form-control form-control-sm mt-2 d-none" placeholder="Type new sub-category...">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label fw-bold" data-lang="prod_modal_uom">UOM</label>
                            <input type="text" name="uom" id="edit-uom" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-primary"><i class="bi bi-geo-alt me-1"></i><span data-lang="prod_modal_loc_main">Permitted Main Location(s) (Room/Area)</span></label>
                            <div class="p-2 border rounded bg-light" style="max-height: 160px; overflow-y: auto;">
                                <?php foreach($main_locations_set as $m_idx => $m_loc): ?>
                                    <div class="form-check m-0 mb-1">
                                        <input class="form-check-input perm-loc-chk" type="checkbox" name="permitted_location_opts[]" value="<?= htmlspecialchars($m_loc) ?>" id="edit_loc_chk_<?= md5($m_loc . '_' . $m_idx) ?>">
                                        <label class="form-check-label small fw-bold" for="edit_loc_chk_<?= md5($m_loc . '_' . $m_idx) ?>"><?= htmlspecialchars($m_loc) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="text" name="permitted_location" id="edit-permitted-location" class="form-control form-control-sm perm-loc-input mt-2" list="permitted_main_locations_list" placeholder="Or type custom location(s), comma separated">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-success"><i class="bi bi-box-seam me-1"></i><span data-lang="prod_modal_loc_sub">Permitted Sub-Location(s) (Equipment/Slot)</span></label>
                            <div class="p-2 border rounded bg-light" style="max-height: 160px; overflow-y: auto;">
                                <div class="fw-bold text-primary small border-bottom pb-1 mb-1"><i class="bi bi-shop me-1"></i>Kedai Jomcha Equipment:</div>
                                <?php foreach(['Freezer (Meat)', 'Freezer (Ice Cream)', 'Chiller 1', 'Chiller 2', 'Rack'] as $s_idx => $s_loc): ?>
                                    <div class="form-check form-check-inline m-0 me-2 mb-1">
                                        <input class="form-check-input perm-sub-chk" type="checkbox" name="permitted_sub_location_opts[]" value="<?= htmlspecialchars($s_loc) ?>" id="edit_sub_mms_<?= md5($s_loc . '_' . $s_idx) ?>">
                                        <label class="form-check-label small" for="edit_sub_mms_<?= md5($s_loc . '_' . $s_idx) ?>"><?= htmlspecialchars($s_loc) ?></label>
                                    </div>
                                <?php endforeach; ?>

                                <div class="fw-bold text-primary small border-bottom pb-1 mb-1 mt-2"><i class="bi bi-building me-1"></i>Store Area Equipment:</div>
                                <?php foreach(['Pallet 1', 'Pallet 2', 'Pallet', 'Chiller 1', 'Chiller 2', 'Freezer 1', 'Freezer 2', 'Rack'] as $s_idx => $s_loc): ?>
                                    <div class="form-check form-check-inline m-0 me-2 mb-1">
                                        <input class="form-check-input perm-sub-chk" type="checkbox" name="permitted_sub_location_opts[]" value="<?= htmlspecialchars($s_loc) ?>" id="edit_sub_sa_<?= md5($s_loc . '_' . $s_idx) ?>">
                                        <label class="form-check-label small" for="edit_sub_sa_<?= md5($s_loc . '_' . $s_idx) ?>"><?= htmlspecialchars($s_loc) ?></label>
                                    </div>
                                <?php endforeach; ?>

                                <div class="fw-bold text-primary small border-bottom pb-1 mb-1 mt-2"><i class="bi bi-cup-hot me-1"></i>JC Barn Equipment:</div>
                                <?php foreach(['Barn Chiller 1', 'Barn Chiller 2', 'Barn Rack'] as $s_idx => $s_loc): ?>
                                    <div class="form-check form-check-inline m-0 me-2 mb-1">
                                        <input class="form-check-input perm-sub-chk" type="checkbox" name="permitted_sub_location_opts[]" value="<?= htmlspecialchars($s_loc) ?>" id="edit_sub_jcb_<?= md5($s_loc . '_' . $s_idx) ?>">
                                        <label class="form-check-label small" for="edit_sub_jcb_<?= md5($s_loc . '_' . $s_idx) ?>"><?= htmlspecialchars($s_loc) ?></label>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (!empty($custom_sub_locations)): ?>
                                    <div class="fw-bold text-success small border-bottom pb-1 mb-1 mt-2"><i class="bi bi-plus-circle me-1"></i>Custom / Registered Equipment:</div>
                                    <?php foreach($custom_sub_locations as $cs_idx => $cs_loc): ?>
                                        <div class="form-check form-check-inline m-0 me-2 mb-1">
                                            <input class="form-check-input perm-sub-chk" type="checkbox" name="permitted_sub_location_opts[]" value="<?= htmlspecialchars($cs_loc) ?>" id="edit_sub_custom_<?= md5($cs_loc . '_' . $cs_idx) ?>">
                                            <label class="form-check-label small fw-bold text-success" for="edit_sub_custom_<?= md5($cs_loc . '_' . $cs_idx) ?>"><?= htmlspecialchars($cs_loc) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <input type="text" name="permitted_sub_location" id="edit-permitted-sub-location" class="form-control form-control-sm perm-sub-input mt-2" list="permitted_sub_locations_list" placeholder="Or type custom sub-location(s), comma separated">
                        </div>
                    </div>

                    <div class="form-check mb-3 p-2 bg-light border rounded ms-1 me-1">
                        <input class="form-check-input ms-1 me-2" type="checkbox" name="sync_category" value="1" id="edit_sync_cat">
                        <label class="form-check-label small fw-bold text-navy" for="edit_sync_cat">
                            <i class="bi bi-arrow-repeat me-1 text-primary"></i>Sync & apply these storage locations to ALL products under this category
                        </label>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold" data-lang="prod_modal_pack_size">Pack Size</label>
                            <input type="number" name="pack_size" id="edit-pack-size" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold" data-lang="prod_modal_pallet_cap">Pallet Capacity</label>
                            <input type="number" name="pallet_capacity" id="edit-pallet-capacity" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-primary" data-lang="prod_modal_barcode">Barcode</label>
                        <input type="text" name="barcode" id="edit-barcode" class="form-control" placeholder="Scan or enter barcode value">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-info" data-lang="prod_modal_qr">QR Code (Unique SKU ID)</label>
                        <input type="text" name="qrcode" id="edit-qrcode" class="form-control" placeholder="Enter QR code unique ID (e.g. O2CW1CO-0100S32A)">
                        <div class="form-text small text-muted">The unique segment of your QR structure (e.g., text after <strong>GGGITN</strong> and before <strong>/BAN</strong>). Example: <code>O2CW1CO-0100S32A</code></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><span data-lang="btn_cancel">Cancel</span></button>
                    <button type="submit" class="btn btn-primary" style="background: var(--mms-navy); border: none;"><span data-lang="prod_modal_btn_save_prod">Save Changes</span></button>
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
                    <h5 class="modal-title fw-bold" id="addProductModalLabel"><i class="bi bi-plus-circle me-2 text-cyan"></i><span data-lang="prod_add_prod">Add New Product</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold" data-lang="prod_modal_prod_name">Product Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Chocolate Milk 125ml" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-5 mb-3">
                            <label class="form-label fw-bold" data-lang="prod_modal_main_cat">Main Category *</label>
                            <select name="category_select" id="add-category-select" class="form-select" onchange="onCategorySelectChange('add', this.value)" required>
                                <option value="" data-lang="prod_modal_sel_cat">-- Select Category --</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat ?? '') ?>"><?= htmlspecialchars($cat ?? '') ?></option>
                                <?php endforeach; ?>
                                <option value="__new__" class="fw-bold text-primary" data-lang="prod_modal_new_cat">+ Add New Main Category...</option>
                            </select>
                            <input type="text" name="category_custom" id="add-category-custom" class="form-control form-control-sm mt-2 text-uppercase d-none" placeholder="Type new category name...">
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label fw-bold" data-lang="prod_modal_sub_size">Sub-Category / Size</label>
                            <select name="sub_category_select" id="add-sub-category-select" class="form-select" onchange="onSubCategorySelectChange('add', this.value)">
                                <option value="" data-lang="prod_modal_sel_subcat">-- Select Sub-Category --</option>
                                <option value="__new__" class="fw-bold text-primary" data-lang="prod_modal_new_subcat">+ Add New Sub-Category...</option>
                            </select>
                            <input type="text" name="sub_category_custom" id="add-sub-category-custom" class="form-control form-control-sm mt-2 d-none" placeholder="Type new sub-category (e.g. UHT 125ml)...">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label fw-bold" data-lang="prod_modal_uom">UOM</label>
                            <input type="text" name="uom" class="form-control" value="Carton" placeholder="Carton / PCS" required>
                        </div>
                    </div>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-primary"><i class="bi bi-geo-alt me-1"></i><span data-lang="prod_modal_loc_main">Permitted Main Location(s) (Room/Area)</span></label>
                            <div class="p-2 border rounded bg-light" style="max-height: 160px; overflow-y: auto;">
                                <?php foreach($main_locations_set as $m_idx => $m_loc): ?>
                                    <div class="form-check m-0 mb-1">
                                        <input class="form-check-input perm-loc-chk" type="checkbox" name="permitted_location_opts[]" value="<?= htmlspecialchars($m_loc) ?>" id="add_loc_chk_<?= md5($m_loc . '_' . $m_idx) ?>">
                                        <label class="form-check-label small fw-bold" for="add_loc_chk_<?= md5($m_loc . '_' . $m_idx) ?>"><?= htmlspecialchars($m_loc) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="text" name="permitted_location" class="form-control form-control-sm perm-loc-input mt-2" list="permitted_main_locations_list" placeholder="Or type custom location(s), comma separated">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-success"><i class="bi bi-box-seam me-1"></i><span data-lang="prod_modal_loc_sub">Permitted Sub-Location(s) (Equipment/Slot)</span></label>
                            <div class="p-2 border rounded bg-light" style="max-height: 160px; overflow-y: auto;">
                                <div class="fw-bold text-primary small border-bottom pb-1 mb-1"><i class="bi bi-shop me-1"></i>Kedai Jomcha Equipment:</div>
                                <?php foreach(['Freezer (Meat)', 'Freezer (Ice Cream)', 'Chiller 1', 'Chiller 2', 'Rack'] as $s_idx => $s_loc): ?>
                                    <div class="form-check form-check-inline m-0 me-2 mb-1">
                                        <input class="form-check-input perm-sub-chk" type="checkbox" name="permitted_sub_location_opts[]" value="<?= htmlspecialchars($s_loc) ?>" id="add_sub_mms_<?= md5($s_loc . '_' . $s_idx) ?>">
                                        <label class="form-check-label small" for="add_sub_mms_<?= md5($s_loc . '_' . $s_idx) ?>"><?= htmlspecialchars($s_loc) ?></label>
                                    </div>
                                <?php endforeach; ?>

                                <div class="fw-bold text-primary small border-bottom pb-1 mb-1 mt-2"><i class="bi bi-building me-1"></i>Store Area Equipment:</div>
                                <?php foreach(['Pallet 1', 'Pallet 2', 'Pallet', 'Chiller 1', 'Chiller 2', 'Freezer 1', 'Freezer 2', 'Rack'] as $s_idx => $s_loc): ?>
                                    <div class="form-check form-check-inline m-0 me-2 mb-1">
                                        <input class="form-check-input perm-sub-chk" type="checkbox" name="permitted_sub_location_opts[]" value="<?= htmlspecialchars($s_loc) ?>" id="add_sub_sa_<?= md5($s_loc . '_' . $s_idx) ?>">
                                        <label class="form-check-label small" for="add_sub_sa_<?= md5($s_loc . '_' . $s_idx) ?>"><?= htmlspecialchars($s_loc) ?></label>
                                    </div>
                                <?php endforeach; ?>

                                <div class="fw-bold text-primary small border-bottom pb-1 mb-1 mt-2"><i class="bi bi-cup-hot me-1"></i>JC Barn Equipment:</div>
                                <?php foreach(['Barn Chiller 1', 'Barn Chiller 2', 'Barn Rack'] as $s_idx => $s_loc): ?>
                                    <div class="form-check form-check-inline m-0 me-2 mb-1">
                                        <input class="form-check-input perm-sub-chk" type="checkbox" name="permitted_sub_location_opts[]" value="<?= htmlspecialchars($s_loc) ?>" id="add_sub_jcb_<?= md5($s_loc . '_' . $s_idx) ?>">
                                        <label class="form-check-label small" for="add_sub_jcb_<?= md5($s_loc . '_' . $s_idx) ?>"><?= htmlspecialchars($s_loc) ?></label>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (!empty($custom_sub_locations)): ?>
                                    <div class="fw-bold text-success small border-bottom pb-1 mb-1 mt-2"><i class="bi bi-plus-circle me-1"></i>Custom / Registered Equipment:</div>
                                    <?php foreach($custom_sub_locations as $cs_idx => $cs_loc): ?>
                                        <div class="form-check form-check-inline m-0 me-2 mb-1">
                                            <input class="form-check-input perm-sub-chk" type="checkbox" name="permitted_sub_location_opts[]" value="<?= htmlspecialchars($cs_loc) ?>" id="add_sub_custom_<?= md5($cs_loc . '_' . $cs_idx) ?>">
                                            <label class="form-check-label small fw-bold text-success" for="add_sub_custom_<?= md5($cs_loc . '_' . $cs_idx) ?>"><?= htmlspecialchars($cs_loc) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <input type="text" name="permitted_sub_location" class="form-control form-control-sm perm-sub-input mt-2" list="permitted_sub_locations_list" placeholder="Or type custom sub-location(s), comma separated">
                        </div>
                    </div>

                    <div class="form-check mb-3 p-2 bg-light border rounded ms-1 me-1">
                        <input class="form-check-input ms-1 me-2" type="checkbox" name="sync_category" value="1" id="add_sync_cat">
                        <label class="form-check-label small fw-bold text-navy" for="add_sync_cat">
                            <i class="bi bi-arrow-repeat me-1 text-primary"></i>Sync & apply these storage locations to ALL products under this category
                        </label>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold" data-lang="prod_modal_pack_size">Pack Size (Units/Ctn)</label>
                            <input type="number" name="pack_size" class="form-control" value="1" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold" data-lang="prod_modal_pallet_cap">Pallet Capacity</label>
                            <input type="number" name="pallet_capacity" class="form-control" value="60" min="1" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-primary" data-lang="prod_modal_barcode">Barcode</label>
                        <input type="text" name="barcode" class="form-control" placeholder="Scan or enter barcode value (optional)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-info" data-lang="prod_modal_qr">QR Code (Unique SKU ID)</label>
                        <input type="text" name="qrcode" class="form-control" placeholder="Enter QR code unique ID (optional)">
                        <div class="form-text small text-muted">The unique segment of your QR structure (e.g., text after <strong>GGGITN</strong> and before <strong>/BAN</strong>). Example: <code>O2CW1CO-0100S32A</code></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><span data-lang="btn_cancel">Cancel</span></button>
                    <button type="submit" class="btn btn-success text-white fw-bold" data-lang="prod_modal_btn_add_prod">Add Product</button>
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

const categoryLocationsMap = <?= json_encode($category_location_map ?? []) ?>;

function autoLoadCategoryLocations(catName) {
    if (!catName) return;
    catName = catName.trim().toUpperCase();
    
    let match = null;
    for (let k in categoryLocationsMap) {
        if (k.toUpperCase() === catName) {
            match = categoryLocationsMap[k];
            break;
        }
    }
    
    if (match) {
        setMultiSelectFields('addCategoryModal', match.permitted_location || '', match.permitted_sub_location || '');
        if (document.getElementById('cat_modal_sub_cats_input')) {
            document.getElementById('cat_modal_sub_cats_input').value = match.sub_categories || '';
        }
    }
}

$(document).ready(function() {
    const preCat = $('#main_category_filter_select').val();
    if (preCat) {
        updateSubCategoryDropdown(preCat, 'sub_category_filter_select', '<?= htmlspecialchars(addslashes($sub_cat_filter ?? '')) ?>');
    }

    // Auto-tick sync checkbox ONLY when sub-category has input/selected
    $(document).on('input change', '#add-sub-category', function() {
        const val = $(this).val().trim();
        $('#add_sync_cat').prop('checked', val.length > 0);
    });

    $(document).on('input change', '#edit-sub-category', function() {
        const val = $(this).val().trim();
        $('#edit_sync_cat').prop('checked', val.length > 0);
    });
});

function openCategoryModal() {
    $('#addCategoryModal form')[0].reset();
    setMultiSelectFields('addCategoryModal', '', '');
    const myModal = new bootstrap.Modal(document.getElementById('addCategoryModal'));
    myModal.show();
}

function onCategorySelectChange(prefix, selectedCat) {
    const $customInput = $(`#${prefix}-category-custom`);
    if (selectedCat === '__new__') {
        $customInput.removeClass('d-none').focus();
    } else {
        $customInput.addClass('d-none').val('');
    }
    
    populateModalSubCategoryDropdown(prefix, selectedCat);
    
    if (selectedCat && selectedCat !== '__new__' && typeof categoryLocationsMap !== 'undefined' && categoryLocationsMap[selectedCat]) {
        const match = categoryLocationsMap[selectedCat];
        setMultiSelectFields(prefix === 'add' ? 'addProductModal' : 'editProductModal', match.permitted_location || '', match.permitted_sub_location || '');
    }
}

function onSubCategorySelectChange(prefix, selectedSub) {
    const $customInput = $(`#${prefix}-sub-category-custom`);
    const $syncChk = $(`#${prefix}_sync_cat`);
    
    if (selectedSub === '__new__') {
        $customInput.removeClass('d-none').focus();
        $syncChk.prop('checked', true);
    } else {
        $customInput.addClass('d-none').val('');
        $syncChk.prop('checked', selectedSub.trim().length > 0);
    }
}

function populateModalSubCategoryDropdown(prefix, selectedCat, preselectedSub = '') {
    const $subSelect = $(`#${prefix}-sub-category-select`);
    const $customInput = $(`#${prefix}-sub-category-custom`);
    if (!$subSelect.length) return;
    
    $subSelect.empty();
    $subSelect.append('<option value="" data-lang="prod_modal_sel_subcat">-- Select Sub-Category --</option>');
    
    let allowedSubs = [];
    selectedCat = (selectedCat || '').trim().toUpperCase();
    
    if (selectedCat !== '' && selectedCat !== '__NEW__') {
        for (let k in catSubcatMap) {
            if (k.toUpperCase() === selectedCat) {
                allowedSubs = catSubcatMap[k] || [];
                break;
            }
        }
    }
    
    allowedSubs.sort();
    let isMatch = false;
    allowedSubs.forEach(s => {
        const isSel = (s.toUpperCase() === (preselectedSub || '').trim().toUpperCase());
        if (isSel) isMatch = true;
        $subSelect.append(`<option value="${s}" ${isSel ? 'selected' : ''}>${s}</option>`);
    });
    
    $subSelect.append('<option value="__new__" class="fw-bold text-primary" data-lang="prod_modal_new_subcat">+ Add New Sub-Category...</option>');
    
    if (preselectedSub && !isMatch && preselectedSub.trim() !== '') {
        $subSelect.val('__new__');
        $customInput.removeClass('d-none').val(preselectedSub);
    } else {
        $customInput.addClass('d-none').val('');
    }
}

function openAddModal(prefilledCategory = '') {
    $('#addProductModal form')[0].reset();
    setMultiSelectFields('addProductModal', '', '');
    $('#add_sync_cat').prop('checked', false);
    
    if (prefilledCategory && prefilledCategory !== '__new__') {
        $('#add-category-select').val(prefilledCategory);
        onCategorySelectChange('add', prefilledCategory);
    } else {
        $('#add-category-select').val('');
        onCategorySelectChange('add', '');
    }
    
    const myModal = new bootstrap.Modal(document.getElementById('addProductModal'));
    myModal.show();
}

const catSubcatMap = <?= json_encode($cat_subcat_map ?? []) ?>;

function updateSubCategoryDropdown(catSelectVal, subCatSelectId, currentSelectedSubCat = '<?= htmlspecialchars(addslashes($sub_cat_filter ?? '')) ?>') {
    const $subSelect = $('#' + subCatSelectId);
    if (!$subSelect.length) return;
    
    $subSelect.empty();
    $subSelect.append('<option value="" data-lang="prod_filter_subcat">-- All Sub-Categories --</option>');
    
    let allowedSubs = [];
    catSelectVal = (catSelectVal || '').trim().toUpperCase();
    
    if (catSelectVal !== '') {
        for (let k in catSubcatMap) {
            if (k.toUpperCase() === catSelectVal) {
                allowedSubs = catSubcatMap[k] || [];
                break;
            }
        }
    } else {
        for (let k in catSubcatMap) {
            (catSubcatMap[k] || []).forEach(s => {
                if (!allowedSubs.includes(s)) allowedSubs.push(s);
            });
        }
    }
    
    allowedSubs.sort();
    allowedSubs.forEach(s => {
        const isSel = (s.toUpperCase() === (currentSelectedSubCat || '').toUpperCase()) ? 'selected' : '';
        $subSelect.append(`<option value="${s}" ${isSel}>${s}</option>`);
    });
}

function openEditModal(btn) {
    const id = $(btn).data('id');
    const name = $(btn).data('name');
    const category = $(btn).data('category') || '';
    const subCategory = $(btn).data('sub-category') || '';
    const permittedLocation = $(btn).data('permitted-location');
    const permittedSubLocation = $(btn).data('permitted-sub-location');
    const uom = $(btn).data('uom');
    const packSize = $(btn).data('pack-size');
    const palletCapacity = $(btn).data('pallet-capacity');
    const barcode = $(btn).data('barcode');
    const qrcode = $(btn).data('qrcode');
    
    $('#edit-id').val(id);
    $('#edit-name').val(name);
    $('#edit-uom').val(uom);
    $('#edit-pack-size').val(packSize);
    $('#edit-pallet-capacity').val(palletCapacity);
    $('#edit-barcode').val(barcode);
    $('#edit-qrcode').val(qrcode);

    // Set Category Dropdown
    let catMatched = false;
    $('#edit-category-select option').each(function() {
        if ($(this).val().toUpperCase() === category.toUpperCase()) {
            $(this).prop('selected', true);
            catMatched = true;
        }
    });
    
    if (!catMatched && category !== '') {
        $('#edit-category-select').val('__new__');
        $('#edit-category-custom').removeClass('d-none').val(category);
    } else {
        $('#edit-category-custom').addClass('d-none').val('');
    }

    populateModalSubCategoryDropdown('edit', category, subCategory);

    // Auto-tick sync checkbox ONLY if sub-category is selected/filled
    $('#edit_sync_cat').prop('checked', !!(subCategory && subCategory.trim().length > 0));

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