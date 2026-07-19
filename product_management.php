<?php
// product_management.php - MASTER PRODUCT CATALOG (UPGRADED INTERFACE)
require_once 'config/db.php';

// Handle product updates (Edit Product Form POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_product') {
    $product_id = (int)$_POST['id'];
    $barcode = trim($_POST['barcode']);
    $qrcode = trim($_POST['qrcode']);
    $category = trim($_POST['category']);
    $uom = trim($_POST['uom']);
    $pack_size = (int)$_POST['pack_size'];
    $pallet_capacity = (int)$_POST['pallet_capacity'];
    
    try {
        // Ambil maklumat produk lama untuk perbandingan log
        $old_stmt = $pdo->prepare("SELECT name, barcode FROM products WHERE id = ? LIMIT 1");
        $old_stmt->execute([$product_id]);
        $old_product = $old_stmt->fetch();
        $product_name = $old_product['name'] ?? 'Unknown';
        $old_barcode = $old_product['barcode'] ?? '';

        $stmt = $pdo->prepare("UPDATE products SET barcode = ?, qrcode = ?, category = ?, uom = ?, pack_size = ?, pallet_capacity = ? WHERE id = ?");
        $stmt->execute([$barcode, $qrcode, $category, $uom, $pack_size, $pallet_capacity, $product_id]);
        $success_msg = "Product updated successfully!";
        
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

// Handle product creation (Add Product Form POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    $name = trim($_POST['name']);
    $barcode = trim($_POST['barcode']);
    $qrcode = trim($_POST['qrcode']);
    $category = trim($_POST['category']);
    $uom = trim($_POST['uom']);
    $pack_size = (int)$_POST['pack_size'];
    $pallet_capacity = (int)$_POST['pallet_capacity'];
    
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
                $stmt = $pdo->prepare("INSERT INTO products (name, barcode, qrcode, category, uom, pack_size, pallet_capacity, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$name, $barcode, $qrcode, $category, $uom, $pack_size, $pallet_capacity]);
                $new_id = $pdo->lastInsertId();
                $success_msg = "Product created successfully!";
                
                if (function_exists('log_system_activity')) {
                    $username = $_SESSION['username'] ?? 'system';
                    log_system_activity("Created Product", "products", $new_id, "$username add new product $name");
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

$categories = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
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

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-box-seam-fill me-2"></i>Product Master</h1>
                <p class="opacity-75 mb-0 fw-light">Moo Moo Supplies Catalog Management</p>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> Dashboard</a>
                <button type="button" class="btn btn-success fw-bold text-white" onclick="openAddModal()"><i class="bi bi-plus-lg me-1"></i> Add Product</button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    <?php if (isset($success_msg)): ?>
        <div class="alert alert-success border-0 shadow-sm mb-3">
            <i class="bi bi-check-circle-fill me-2 text-success"></i><?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>
    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-3">
            <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i><?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>
    
    <div class="card main-card border-0 mb-4">
        <div class="card-body p-3">
            <form class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by name or category..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="category" class="form-select">
                        <option value="">-- All Categories --</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat ?>" <?= $cat_filter == $cat ? 'selected' : '' ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-dark w-100 fw-bold">APPLY FILTERS</button>
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
                            <tr><td colspan="9" class="text-center py-5 text-muted">No products found in the catalog.</td></tr>
                        <?php else: 
                            foreach($products as $p): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded p-2 d-flex align-items-center justify-content-center me-3 fs-5">
                                            <i class="bi bi-box"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($p['name']) ?></div>
                                            <small class="text-muted">ID: #<?= $p['id'] ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fw-bold text-uppercase" style="font-size: 0.72rem;"><?= htmlspecialchars($p['category']) ?></span></td>
                                <td class="text-center">
                                    <small class="fw-bold text-uppercase"><?= htmlspecialchars($p['uom']) ?></small>
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
                                    <a href="?toggle_id=<?= $p['id'] ?>" 
                                       class="btn btn-sm <?= $p['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>" 
                                       title="<?= $p['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                        <i class="bi <?= $p['is_active'] ? 'bi-lock' : 'bi-unlock' ?>"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-primary" 
                                            title="Edit Product" 
                                            onclick="openEditModal(this)"
                                            data-id="<?= $p['id'] ?>"
                                            data-name="<?= htmlspecialchars($p['name']) ?>"
                                            data-category="<?= htmlspecialchars($p['category']) ?>"
                                            data-uom="<?= htmlspecialchars($p['uom']) ?>"
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

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="">
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
                            <label class="form-label fw-bold">Category</label>
                            <input type="text" name="category" id="edit-category" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">UOM</label>
                            <input type="text" name="uom" id="edit-uom" class="form-control" required>
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
    <div class="modal-dialog">
        <form method="POST" action="">
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
                            <input type="text" name="category" list="category_datalist" class="form-control" placeholder="UHT / PST / PSS" required>
                            <datalist id="category_datalist">
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">UOM</label>
                            <input type="text" name="uom" class="form-control" value="Carton" placeholder="Carton / PCS" required>
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
function openAddModal() {
    $('#addProductModal form')[0].reset();
    const myModal = new bootstrap.Modal(document.getElementById('addProductModal'));
    myModal.show();
}

function openEditModal(btn) {
    const id = $(btn).data('id');
    const name = $(btn).data('name');
    const category = $(btn).data('category');
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
    
    const myModal = new bootstrap.Modal(document.getElementById('editProductModal'));
    myModal.show();
}
</script>

<?php require_once 'includes/footer.php'; ?>