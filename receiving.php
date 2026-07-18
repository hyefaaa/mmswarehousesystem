<?php
// receiving.php
// Updated: Security Checked, Category & Searchable Product Filter, Camera Scan Integrated

require_once 'config/db.php';

// Memastikan sesi dimulakan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sekatan peranan: Hanya Admin dan Staff sahaja boleh menerima stok
$role = $_SESSION['role'] ?? '';
$is_staff = is_staff_role($role);
if (!$is_staff) {
    http_response_code(403);
    $page_title = 'Akses Dihalang';
    require_once 'includes/header.php';
    echo '<div class="container-fluid px-4 py-5 text-center">
            <div class="card shadow-sm mx-auto p-5" style="max-width: 500px; border-radius: 16px;">
                <h1 style="color: #e74c3c;" class="display-4"><i class="bi bi-shield-slash-fill"></i></h1>
                <h3 class="fw-bold mt-3">Akses Dihalang!</h3>
                <p class="text-muted">Akaun anda tiada kebenaran untuk mengakses halaman Penerimaan Stok.</p>
                <a href="index.php" class="btn btn-primary mt-3 py-2 px-4" style="background: #0f172a; border: none;">Kembali ke Dashboard</a>
            </div>
          </div>';
    require_once 'includes/footer.php';
    exit;
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $category    = htmlspecialchars(strip_tags($_POST['category'] ?? ''));
    $product_id  = (int)($_POST['product_id'] ?? 0);
    $lot_no      = htmlspecialchars(strip_tags($_POST['lot_no'] ?? ''));
    $expiry_date = htmlspecialchars(strip_tags($_POST['expiry_date'] ?? ''));
    $batch_no    = htmlspecialchars(strip_tags($_POST['batch_no'] ?? ''));
    $pallet_code = htmlspecialchars(strip_tags($_POST['pallet_type'] ?? 'none'));
    $qty         = (int)($_POST['qty'] ?? 0);

    if (empty($category) || empty($product_id) || empty($batch_no) || empty($qty) || empty($expiry_date)) {
        $message = '<div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-x-circle-fill me-2"></i>Sila lengkapkan semua ruangan wajib.</div>';
    } else {
        try {
            $pdo->beginTransaction();

            // Fetch pallet map dynamically
            $pallet_map = ['none' => 'No Pallet'];
            $db_pallets = $pdo->query("SELECT name, code FROM pallet_types")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($db_pallets as $p) {
                $pallet_map[strtolower($p['code'])] = $p['name'];
            }
            $p_type = $pallet_map[strtolower($pallet_code)] ?? 'No Pallet';

            // 1. Rekod header inbound
            $supplier_do = 'SR-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            $pallet_red = ($pallet_code === 'red') ? 1 : 0;
            $pallet_orange = ($pallet_code === 'orange') ? 1 : 0;
            $pallet_black = ($pallet_code === 'black') ? 1 : 0;
            $pallet_ffm = ($pallet_code === 'ffm') ? 1 : 0;
            $pallet_lhp = ($pallet_code === 'lhp') ? 1 : 0;
            $pallet_plain = ($pallet_code === 'plain') ? 1 : 0;
            $pallet_remarks = "Single GRN: Lot $lot_no";

            $stmtHeader = $pdo->prepare("INSERT INTO inbound_logs 
                (category, received_date, supplier_do, remarks, 
                 pallet_qty_loscam_red, pallet_qty_ffm_orange, pallet_qty_plastic_black,
                 pallet_qty_ffm_green, pallet_qty_lhp_green, pallet_qty_plain_wood) 
                VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtHeader->execute([
                $category, $supplier_do, $pallet_remarks,
                $pallet_red, $pallet_orange, $pallet_black, $pallet_ffm, $pallet_lhp, $pallet_plain
            ]);
            $inbound_id = $pdo->lastInsertId();

            // Record to pallet_ledger (Dynamic)
            if ($pallet_code !== 'none') {
                $stmtPalletLedger = $pdo->prepare("INSERT INTO pallet_ledger 
                    (transaction_date, transaction_type, pallet_code, qty, reference_no, notes) 
                    VALUES (NOW(), 'IN', ?, 1, ?, ?)");
                $stmtPalletLedger->execute([
                    $pallet_code,
                    $supplier_do,
                    "Received via Single-Receive (GRN ID: $inbound_id)"
                ]);
            }

            // 2. Rekod item inbound
            $stmtItem = $pdo->prepare("INSERT INTO inbound_items 
                (inbound_id, product_id, batch_no, qty_received, expiry_date) 
                VALUES (?, ?, ?, ?, ?)");
            $stmtItem->execute([$inbound_id, $product_id, $batch_no, $qty, $expiry_date]);

            // 3. Masukkan ke stok aktif (inventory_batches)
            $stmtStock = $pdo->prepare("INSERT INTO inventory_batches 
                (product_id, batch_no, lot_no_raw, expiry_date, qty_on_hand, location_status, pallet_type, pallet_id_tag) 
                VALUES (?, ?, ?, ?, ?, 'Warehouse', ?, ?)");
            $stmtStock->execute([$product_id, $batch_no, $lot_no, $expiry_date, $qty, $p_type, '']);

            // 4. Rekod log aktiviti sistem
            if (function_exists('log_system_activity')) {
                log_system_activity("Received Stock (Single)", "inbound_logs", $inbound_id, "Single GRN diproses: ID $inbound_id, Rujukan $supplier_do (Qty: $qty ctn).");
            }

            $pdo->commit();
            $message = '<div class="alert alert-success border-0 shadow-sm"><i class="bi bi-check-circle-fill me-2"></i>Stok berjaya diterima! (GRN ID: ' . $inbound_id . ')</div>';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = '<div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i>Ralat menyimpan data: ' . $e->getMessage() . '</div>';
        }
    }
}

// Dapatkan senarai semua produk aktif
$products = [];
try {
    $stmt = $pdo->query("SELECT id, name, category, pack_size, pallet_capacity FROM products WHERE is_active = 1 ORDER BY name ASC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Abaikan ralat
}

$pallet_types = [];
try {
    $stmtPallet = $pdo->query("SELECT name, code FROM pallet_types");
    $pallet_types = $stmtPallet->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Abaikan ralat
}

$page_title = 'Single Inbound Receiving';
require_once 'includes/header.php';
?>

<style>
    .content-wrapper {
        flex-grow: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 30px 20px;
        margin-top: 1rem;
    }

    .form-container { 
        width: 100%;
        max-width: 650px; 
        background: white; 
        padding: 40px; 
        border-radius: 24px; 
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.03); 
        border: 1px solid rgba(226, 232, 240, 0.8);
    }
    .readonly-input { background-color: #f8fafc !important; cursor: not-allowed; font-weight: 700; color: #475569 !important; border: 1.5px solid #e2e8f0; }
    .form-control, .form-select {
        border: 1.5px solid #cbd5e1;
        border-radius: 12px;
        height: 42px;
    }
    .form-control:focus, .form-select:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.15);
    }
    .form-label {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #475569;
    }
    .select2-container .select2-selection--single {
        height: 42px;
        border: 1.5px solid #cbd5e1;
        display: flex;
        align-items: center;
        font-weight: 600;
        font-size: 0.9rem;
        border-radius: 12px;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px;
    }
    
    @media (max-width: 576px) {
        .form-container {
            padding: 24px 20px;
            border-radius: 20px;
        }
        .content-wrapper {
            padding: 15px 10px;
            margin-top: 0.5rem;
        }
    }
</style>

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-box-seam-fill me-2 text-warning"></i><span data-lang="nav_single_receive">Single Item Receiving</span></h1>
                <p class="opacity-75 mb-0 fw-light">Receive and record inbound stock items with batch configurations</p>
            </div>
            <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> Dashboard</a>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
<div class="content-wrapper mt-0 pt-0">
    <div class="form-container">
        
        <?= $message ?>

        <form action="receiving.php" method="post" id="singleReceiveForm">
            
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold" data-lang="lbl_category">Category</label>
                    <select name="category" id="category" class="form-select" onchange="filterProducts()" required>
                        <option value="UHT" selected>UHT (Retail)</option>
                        <option value="PSS">PSS (School)</option>
                        <option value="PST">PST (Fresh/Dairy)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold" data-lang="recv_lbl_select_product">Select Product</label>
                    <select name="product_id" id="product_id" class="form-select" required>
                        <!-- Pilihan akan dimasukkan melalui JavaScript -->
                    </select>
                </div>
            </div>

            <div class="mb-4 bg-light p-3 rounded border border-primary border-opacity-25">
                <label class="form-label fw-bold text-primary mb-1 d-flex justify-content-between align-items-center">
                    <span data-lang="recv_lbl_scan_lot">SCAN LOT NO:</span>
                    <button type="button" class="btn btn-primary btn-sm fw-bold px-3 py-1" onclick="openCamera()">
                        <i class="bi bi-camera-fill me-1"></i> <span data-lang="recv_btn_scan_camera">Scan Camera</span>
                    </button>
                </label>
                <input type="text" name="lot_no" id="lot_no" class="form-control border-primary text-uppercase fw-bold" placeholder="e.g. 260831-MFB010-PP003" data-lang-placeholder="recv_lot_placeholder" oninput="parseLotNo()" autocomplete="off">
                <div class="form-text small text-muted" data-lang="recv_lot_help">Mengisi butiran di bawah secara automatik daripada lot/QR barcode.</div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold" data-lang="inv_col_expiry">Expiry Date</label>
                    <input type="date" name="expiry_date" id="expiry_date" class="form-control readonly-input" required readonly>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" data-lang="dash_batch_no">Batch No</label>
                    <input type="text" name="batch_no" id="batch_no" class="form-control readonly-input text-center" required readonly>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold" data-lang="recv_lbl_shelf_life">Shelf Life (Months)</label>
                    <input type="text" id="shelf_life" class="form-control readonly-input text-center" readonly>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" data-lang="inv_col_pallet">Pallet Type</label>
                    <select name="pallet_type" id="pallet_type" class="form-select" required>
                        <option value="none" data-lang="pallet_none">None</option>
                        <?php foreach ($pallet_types as $pt): ?>
                            <option value="<?= htmlspecialchars($pt['code']) ?>"><?= htmlspecialchars($pt['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold" data-lang="recv_lbl_qty_pcs">Quantity (Pieces)</label>
                    <input type="number" id="qty_pcs" class="form-control readonly-input text-center" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold text-primary" data-lang="recv_lbl_qty_ctns">Quantity (Cartons) *</label>
                    <input type="number" name="qty" id="qty" class="form-control text-center fw-bold" placeholder="Masukkan Kuantiti (ctn)" data-lang-placeholder="recv_placeholder_qty" required min="1">
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-success btn-lg fw-bold shadow-sm" data-lang="recv_btn_confirm">Confirm Receive</button>
                <a href="index.php" class="btn btn-secondary py-2 fw-bold" data-lang="btn_cancel">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>

<!-- Modal Kamera QR/Barcode -->
<div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-0">
            <div class="modal-body p-0 text-center position-relative">
                <div id="reader" style="width:100%;"></div>
                <button class="btn btn-danger m-3 px-4 fw-bold" data-bs-dismiss="modal" data-lang="recv_camera_close">Tutup Kamera</button>
            </div>
        </div>
    </div>
</div>


<script>
    const productList = <?= json_encode($products); ?>;
    let html5QrCode = null;
    let cameraModalInstance = null;

    $(document).ready(function() {
        cameraModalInstance = new bootstrap.Modal(document.getElementById('cameraModal'));
        
        // Initialize Select2
        $('#product_id').select2({
            width: '100%',
            placeholder: '-- Select Product --'
        });
        
        // Filter produk berdasarkan kategori lalai
        filterProducts();

        // Kawalan kamera
        document.getElementById('cameraModal').addEventListener('shown.bs.modal', startScanner);
        document.getElementById('cameraModal').addEventListener('hidden.bs.modal', stopScanner);
    });

    function filterProducts() {
        const cat = document.getElementById('category').value;
        const sel = $('#product_id');
        const currentVal = sel.val();
        
        sel.empty();
        sel.append('<option value="">-- Select Product --</option>');
        
        productList.forEach(p => {
            if (p.category === cat) {
                sel.append(`<option value="${p.id}" data-cat="${p.category}" data-packsize="${p.pack_size}">${p.name}</option>`);
            }
        });
        
        if (currentVal) {
            sel.val(currentVal);
        }
        sel.trigger('change');
    }

    let parseTimeout;
    function parseLotNo() {
        let lotString = document.getElementById('lot_no').value.trim();
        if (lotString.length < 5) return;

        clearTimeout(parseTimeout);
        parseTimeout = setTimeout(() => {
            const catVal = document.getElementById('category').value;
            fetch('ajax_parse_lot.php?lot_no=' + encodeURIComponent(lotString) + '&category=' + encodeURIComponent(catVal))
            .then(res => {
                if (!res.ok) throw new Error('Network response was not ok');
                return res.text();
            })
            .then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse JSON. Raw response:', text);
                    throw e;
                }
            })
            .then(data => {
                if (data.status === 'success') {
                    // Verify product exists in database
                    if (!data.data.product_id) {
                        alert(MMS_LANG.t('err_product_not_registered'));
                        document.getElementById('lot_no').value = '';
                        document.getElementById('lot_no').style.borderColor = "#ef4444";
                        
                        // Clear fields
                        document.getElementById('batch_no').value = '';
                        document.getElementById('expiry_date').value = '';
                        document.getElementById('shelf_life').value = '';
                        document.getElementById('pallet_type').value = 'none';
                        document.getElementById('qty_pcs').value = '';
                        document.querySelector('input[name="qty"]').value = '';
                        return;
                    }

                    // Strict category verification
                    if (data.data.category) {
                        const catSelect = document.getElementById('category');
                        if (catSelect && catSelect.value !== data.data.category) {
                            let errMsg = MMS_LANG.t('err_category_mismatch')
                                            .replace('{prod_cat}', data.data.category)
                                            .replace('{selected_cat}', catSelect.value);
                            alert(errMsg);
                            document.getElementById('lot_no').value = '';
                            document.getElementById('lot_no').style.borderColor = "#ef4444";
                            
                            // Clear fields
                            document.getElementById('batch_no').value = '';
                            document.getElementById('expiry_date').value = '';
                            document.getElementById('shelf_life').value = '';
                            document.getElementById('pallet_type').value = 'none';
                            document.getElementById('qty_pcs').value = '';
                            document.querySelector('input[name="qty"]').value = '';
                            return;
                        }
                    }

                    // Clear any previous error border
                    document.getElementById('lot_no').style.borderColor = "#10b981";

                    // Isi Batch No & Qty Pieces
                    document.getElementById('batch_no').value = data.data.batch || '';
                    document.getElementById('qty_pcs').value = data.data.qty_pieces || '';
                    
                    // Expiry Date format conversion (d/m/Y -> Y-m-d untuk input type="date")
                    if (data.data.expiry_date) {
                        let expParts = data.data.expiry_date.split('/');
                        if (expParts.length === 3) {
                            document.getElementById('expiry_date').value = `${expParts[2]}-${expParts[1]}-${expParts[0]}`;
                        }
                    }

                    // Kira Shelf Life Months berdasarkan Batch No (cth: B10 -> 10)
                    let batch = data.data.batch || '';
                    let numericShelf = batch.replace(/[^0-9]/g, '');
                    if (numericShelf) {
                        document.getElementById('shelf_life').value = parseInt(numericShelf, 10);
                    }

                    // Padanan Produk Pintar
                    let matched = false;
                    const sel = document.getElementById('product_id');
                    if (data.data.product_id) {
                        $('#product_id').val(data.data.product_id).trigger('change');
                        matched = true;
                    } else if (data.data.product_code) {
                        let pCode = String(data.data.product_code).toUpperCase();
                        let numericSize = pCode.replace(/[^0-9]/g, '');
                        numericSize = parseInt(numericSize, 10).toString();

                        for (let opt of sel.options) {
                            let optText = opt.text.toUpperCase();
                            if (optText.includes(pCode)) {
                                $('#product_id').val(opt.value).trigger('change');
                                matched = true;
                                break;
                            }
                            if (numericSize && optText.includes(numericSize + "ML")) {
                                let isSchoolProduct = optText.includes("SCHOOL");
                                if (catVal === 'PSS' && isSchoolProduct) {
                                    if (pCode.includes('C') && optText.includes('CHOCOLATE')) {
                                        $('#product_id').val(opt.value).trigger('change');
                                        matched = true;
                                        break;
                                    } else if (!pCode.includes('C') && !optText.includes('CHOCOLATE')) {
                                        $('#product_id').val(opt.value).trigger('change');
                                        matched = true;
                                        break;
                                    }
                                } else if (catVal !== 'PSS' && !isSchoolProduct) {
                                    if (pCode.includes('S') && (optText.includes('STRAWBERRY') || optText.includes('SOY') || optText.includes('SWEET'))) {
                                        $('#product_id').val(opt.value).trigger('change');
                                        matched = true;
                                        break;
                                    } else if (pCode.includes('C') && optText.includes('CHOCOLATE')) {
                                        $('#product_id').val(opt.value).trigger('change');
                                        matched = true;
                                        break;
                                    } else if (pCode.includes('F') && (optText.includes('FRESH') || optText.includes('FULL CREAM'))) {
                                        $('#product_id').val(opt.value).trigger('change');
                                        matched = true;
                                        break;
                                    } else if (pCode.includes('K') && optText.includes('KURMA')) {
                                        $('#product_id').val(opt.value).trigger('change');
                                        matched = true;
                                        break;
                                    } else if (pCode.includes('B') && optText.includes('BANANA')) {
                                        $('#product_id').val(opt.value).trigger('change');
                                        matched = true;
                                        break;
                                    }
                                }
                            }
                        }

                        if (!matched && numericSize) {
                            for (let opt of sel.options) {
                                if (opt.text.toUpperCase().includes(numericSize + "ML")) {
                                    $('#product_id').val(opt.value).trigger('change');
                                    matched = true;
                                    break;
                                }
                            }
                        }
                    }

                    // Kira Kuantiti Carton selepas Produk Dipilih untuk memastikan pack size yang betul
                    if (data.data.qty_pieces && data.data.qty_pieces > 0) {
                        let finalQty = data.data.qty_pieces;
                        let packSize = parseInt(data.data.pack_size || 0);
                        if (packSize > 0) {
                            finalQty = Math.floor(data.data.qty_pieces / packSize);
                        } else if (sel && sel.value) {
                            const opt = sel.options[sel.selectedIndex];
                            let pSize = parseInt($(opt).data('packsize') || 0);
                            if (pSize > 0) {
                                finalQty = Math.floor(data.data.qty_pieces / pSize);
                            } else {
                                const optText = opt.text;
                                const match = optText.match(/(\d+)\s*(PK|PCS|PC)\/CTN/i);
                                if (match) {
                                    let pSizeMatch = parseInt(match[1]);
                                    if (pSizeMatch > 0) {
                                        finalQty = Math.floor(data.data.qty_pieces / pSizeMatch);
                                    }
                                }
                            }
                        }
                        document.querySelector('input[name="qty"]').value = finalQty;
                    }
                }
            })
            .catch(err => console.error('Error parsing QR:', err));
        }, 300);
    }

    function openCamera() {
        cameraModalInstance.show();
    }

    let isScanning = false;
    function startScanner() {
        isScanning = true;
        if (!html5QrCode) html5QrCode = new Html5Qrcode("reader");
        const config = { 
            fps: 10, 
            qrbox: 250,
            formatsToSupport: [ Html5QrcodeSupportedFormats.QR_CODE ] 
        };
        html5QrCode.start({ facingMode: "environment" }, config, (text) => {
            if (!isScanning) return;
            isScanning = false;
            document.getElementById('lot_no').value = text;
            parseLotNo();
            cameraModalInstance.hide();
        });
    }

    function stopScanner() {
        if (html5QrCode) {
            html5QrCode.stop().catch(err => console.error("Error stopping scanner:", err));
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>