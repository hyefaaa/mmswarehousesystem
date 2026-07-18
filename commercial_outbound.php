<?php
// commercial_outbound.php - UPGRADED CORPORATE INTERFACE
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';

// Fetch All Active Products with Stock Levels (Including PSS)
$products = $pdo->query("
    SELECT p.id, p.name, p.category, p.pack_size,
           COALESCE((SELECT SUM(qty_on_hand) FROM inventory_batches WHERE product_id = p.id AND qty_on_hand > 0), 0) as qty_on_hand
    FROM products p
    WHERE p.is_active=1
    ORDER BY p.name ASC
")->fetchAll();

// Fetch last 10 processed commercial outbound logs
$history_logs = $pdo->query("
    SELECT id, date, customer, doc_ref, created_at
    FROM outbound_logs
    WHERE category = 'Commercial'
    ORDER BY id DESC
    LIMIT 10
")->fetchAll();

$page_title = 'Commercial Outbound | MMS LOGISTIK';
require_once 'includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    body {
        background-color: #f8fafc;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }
    
    /* Importer Section Container */
    .importer-card {
        background: #ffffff;
        border: 2px dashed #0284c7;
        border-radius: 16px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
        transition: all 0.3s;
    }
    .importer-card:hover {
        border-color: #0ea5e9;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08);
    }
    
    /* Headers */
    .block-header {
        font-size: 1.05rem;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        margin-bottom: 1.25rem;
    }
    .block-header i {
        color: #0ea5e9;
        margin-right: 8px;
    }
    
    /* Custom form elements */
    .custom-input, .form-control, .form-select {
        border: 1px solid #cbd5e1;
        border-radius: 10px !important;
        padding: 10px 14px;
        font-size: 0.95rem;
        font-weight: 500;
        color: #1e293b;
        background-color: #ffffff;
        transition: all 0.2s;
    }
    .custom-input:focus, .form-control:focus, .form-select:focus {
        border-color: #0ea5e9;
        box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.12);
        outline: none;
    }
    
    .input-group-text {
        border: 1px solid #cbd5e1;
        border-radius: 10px 0 0 10px !important;
        background-color: #f1f5f9;
        color: #64748b;
    }
    
    /* Table Styling */
    .modern-table-container {
        border-radius: 12px;
        overflow-x: auto;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        background: #ffffff;
    }
    .modern-table {
        margin-bottom: 0;
    }
    .modern-table th {
        background-color: #0f172a !important;
        color: #f8fafc !important;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.75px;
        padding: 14px 20px !important;
        border: none;
    }
    .modern-table td {
        padding: 14px 20px !important;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }
    .modern-table tr:last-child td {
        border-bottom: none;
    }
    .modern-table tr:hover {
        background-color: #f8fafc;
    }
    
    /* Buttons */
    .btn-process {
        background: linear-gradient(135deg, #0284c7, #0369a1);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 700;
        transition: all 0.2s;
    }
    .btn-process:hover {
        background: linear-gradient(135deg, #0ea5e9, #0284c7);
        transform: translateY(-1px);
        box-shadow: 0 8px 20px rgba(2, 132, 199, 0.2);
        color: white;
    }
    
    .btn-add-row {
        color: #0284c7;
        background-color: #f0f9ff;
        border: 2px dashed #bae6fd;
        border-radius: 12px;
        padding: 12px;
        font-weight: 700;
        width: 100%;
        transition: all 0.2s;
    }
    .btn-add-row:hover {
        background-color: #e0f2fe;
        border-color: #0284c7;
        color: #0369a1;
        transform: translateY(-1px);
    }
    
    .btn-mms-confirm {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        font-weight: 800;
        font-size: 1.1rem;
        padding: 16px;
        border-radius: 14px;
        border: none;
        box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.2);
        transition: all 0.3s;
    }
    .btn-mms-confirm:hover {
        background: linear-gradient(135deg, #34d399, #059669);
        transform: translateY(-2px);
        box-shadow: 0 20px 25px -5px rgba(16, 185, 129, 0.35);
        color: white;
    }
    
    /* Alert styles inside dropdowns */
    .batch-select option[disabled] {
        color: #ef4444;
        font-weight: 600;
        background-color: #fef2f2;
    }
    
    /* Select2 customizations to look clean and neat */
    .select2-container--default .select2-selection--single {
        border: 1px solid #cbd5e1 !important;
        border-radius: 10px !important;
        height: 42px !important;
        padding: 6px 12px !important;
        background-color: #ffffff !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        font-weight: 600 !important;
        color: #1e293b !important;
        line-height: 28px !important;
    }
</style>

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-box-arrow-up-right me-2"></i><span data-lang="nav_commercial_out">Stock Outbound</span></h1>
                <p class="opacity-75 mb-0 fw-light">Commercial & Retail Distribution Command</p>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> <span data-lang="nav_dashboard">Dashboard</span></a>
                <a href="reconcile.php" class="btn btn-info text-white fw-bold"><i class="bi bi-scale me-1"></i> <span data-lang="nav_daily_reconcile">Reconcile</span></a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    
    <!-- NEW: Import Invoice (Hybrid Approach) -->
    <div class="importer-card p-4 mb-4">
        <h5 class="fw-bold mb-2 text-primary d-flex align-items-center">
            <i class="bi bi-file-earmark-arrow-up-fill me-2"></i> <span data-lang="co_import_invoice_title">Import Invoice / DO (Hybrid Importer)</span>
        </h5>
        <p class="text-muted small mb-3" data-lang="co_import_invoice_desc">Muat naik fail invois atau DO harian (format .xlsx, .xls, .csv, atau .pdf) untuk mengisi senarai produk, kuantiti, tarikh & maklumat pelanggan secara automatik.</p>
        
        <div class="row align-items-center g-3">
            <div class="col-md-9 col-sm-8">
                <input type="file" id="invoice_file_input" class="form-control form-control-lg" accept=".xlsx, .xls, .csv, .pdf">
            </div>
            <div class="col-md-3 col-sm-4">
                <button type="button" id="btn_process_invoice" class="btn btn-process btn-lg w-100 py-2 shadow-sm d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-lightning-charge-fill"></i> <span data-lang="co_col_view">PROSES FAIL</span>
                </button>
            </div>
        </div>
    </div>

    <form action="api/save_commercial_outbound.php" method="POST" id="outboundForm">
        
        <!-- SECTION 1: Delivery Information -->
        <div class="card shadow-sm border-0 mb-4 p-4">
            <div class="block-header"><i class="bi bi-info-circle-fill"></i> <span data-lang="co_delivery_info">Delivery Information</span></div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted" data-lang="co_delivery_date">DELIVERY DATE</label>
                    <input type="date" name="out_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted" data-lang="co_customer_outlet">CUSTOMER / OUTLET</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-shop"></i></span>
                        <input type="text" name="customer_name" class="form-control" placeholder="e.g. Lotus's Kuala Terengganu" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted" data-lang="co_do_ref">DO / INVOICE REF</label>
                    <input type="text" name="doc_ref" class="form-control" placeholder="e.g. DO-2026-0450">
                </div>
            </div>
        </div>

        <!-- SECTION 2: Items to Outbound -->
        <div class="card shadow-sm border-0 mb-4 p-4">
            <div class="block-header"><i class="bi bi-cart-check-fill"></i> <span data-lang="co_items_outbound">Items to Outbound</span></div>
            
            <div class="modern-table-container mb-3">
                <table class="table modern-table align-middle" id="outTable">
                    <thead>
                        <tr>
                            <th class="ps-4" data-lang="co_col_product">Product Selection</th>
                            <th width="30%" data-lang="co_col_batch">Batch / Lot No.</th>
                            <th width="15%" class="text-center" data-lang="co_col_qty">Qty (Carton)</th>
                            <th width="10%" class="text-center" data-lang="co_col_action">Action</th>
                        </tr>
                    </thead>
                    <tbody id="outBody">
                        <tr class="item-row">
                            <td class="ps-4" data-label="Product">
                                <select name="items[0][product_id]" class="form-select product-select" required>
                                    <option value="">-- Choose Product --</option>
                                    <?php foreach($products as $p): ?>
                                        <option value="<?= $p['id'] ?>" data-stock="<?= $p['qty_on_hand'] ?>"><?= htmlspecialchars($p['name']) ?> (Baki: <?= $p['qty_on_hand'] ?> ctn)</option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td data-label="Expiry">
                                <select name="items[0][batch]" class="form-select form-select-sm batch-select text-center fw-bold" onchange="validateBatchStock(this)">
                                    <option value="">-- Auto FEFO --</option>
                                </select>
                            </td>
                            <td data-label="Qty (ctn)">
                                <input type="number" name="items[0][qty]" class="form-control form-control-sm fw-bold text-center qty-input" required min="1" placeholder="0" oninput="validateBatchStock(this)">
                            </td>
                            <td class="text-center" data-label="Action">
                                <button type="button" class="btn btn-outline-danger btn-sm border-0" onclick="removeRow(this)">
                                    <i class="bi bi-trash3 fs-5"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="mb-2">
                <button type="button" class="btn btn-add-row" onclick="addRow()">
                    <i class="bi bi-plus-lg me-2"></i> <span data-lang="co_btn_add">Add Another Product</span>
                </button>
            </div>
        </div>

        <div class="d-grid shadow-sm mb-5">
            <button type="submit" class="btn btn-mms-confirm btn-lg py-3">
                <i class="bi bi-send-check-fill me-2"></i> <span data-lang="co_btn_confirm">CONFIRM & PROCESS OUTBOUND</span>
            </button>
        </div>
        
    </form>

    <!-- SEKSYEN 3: Sejarah Fail Invois / DO Diproses -->
    <div class="card shadow-sm border-0 mb-5 p-4" style="border-radius: 16px;">
        <div class="block-header text-navy" style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem;"><i class="bi bi-clock-history text-primary"></i> <span data-lang="co_history_title">Sejarah Fail Invois / DO Diproses</span></div>
        <p class="text-muted small mb-3" data-lang="co_history_desc">Senarai 10 fail invois komersial terakhir yang telah berjaya diproses dan memotong stok gudang.</p>
        
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="table-light">
                    <tr class="text-secondary small fw-bold">
                        <th class="ps-3" data-lang="co_col_proc_date">Tarikh Proses</th>
                        <th data-lang="co_delivery_date">Tarikh Penghantaran</th>
                        <th data-lang="co_do_ref">No. DO / Invois Ref</th>
                        <th data-lang="co_customer_outlet">Nama Pelanggan / Outlet</th>
                        <th class="text-center" data-lang="co_col_action">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history_logs)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                <i class="bi bi-info-circle-fill fs-4 d-block mb-1"></i> <span data-lang="lbl_no_data">Tiada rekod pemprosesan ditemui.</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history_logs as $log): ?>
                            <tr>
                                <td class="ps-3 text-muted"><?= date('d/m/Y h:i A', strtotime($log['created_at'])) ?></td>
                                <td class="fw-bold text-dark"><?= date('d/m/Y', strtotime($log['date'])) ?></td>
                                <td><span class="badge bg-light text-primary border font-monospace px-2.5 py-1.5 fs-7" style="color: #0b2147; border-color: #e2e8f0;"><?= htmlspecialchars($log['doc_ref'] ?: 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($log['customer']) ?></td>
                                <td class="text-center">
                                    <a href="outbound_history.php?search=<?= urlencode($log['doc_ref']) ?>" class="btn btn-sm btn-outline-primary py-1 px-3 fw-bold">
                                        <i class="bi bi-eye-fill me-1"></i> <span data-lang="co_col_view">Papar Detail</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<script>
    let rowCount = 1;
    const products = <?php echo json_encode($products); ?>;

    function initSelect2() {
        $('.product-select:not(.select2-hidden-accessible)').select2({
            placeholder: "-- Choose Product --",
            allowClear: true,
            width: '100%'
        });
    }

    $(document).ready(function() {
        initSelect2();
    });
    
    function addRow() {
        let options = '<option value="">-- Choose Product --</option>';
        products.forEach(p => options += `<option value="${p.id}" data-stock="${p.qty_on_hand}">${p.name} (Baki: ${p.qty_on_hand} ctn)</option>`);
        
        const html = `
            <tr class="item-row">
                <td class="ps-4" data-label="Product">
                    <select name="items[${rowCount}][product_id]" class="form-select product-select" required>${options}</select>
                </td>
                <td data-label="Expiry">
                    <select name="items[${rowCount}][batch]" class="form-select form-select-sm batch-select text-center fw-bold" onchange="validateBatchStock(this)">
                        <option value="">-- Auto FEFO --</option>
                    </select>
                </td>
                <td data-label="Qty (ctn)"><input type="number" name="items[${rowCount}][qty]" class="form-control form-control-sm fw-bold text-center qty-input" required min="1" placeholder="0" oninput="validateBatchStock(this)"></td>
                <td class="text-center pe-4" data-label="Action">
                    <button type="button" class="btn btn-outline-danger btn-sm border-0" onclick="removeRow(this)">
                        <i class="bi bi-trash3 fs-5"></i>
                    </button>
                </td>
            </tr>
        `;
        document.getElementById('outBody').insertAdjacentHTML('beforeend', html);
        rowCount++;
        initSelect2();
    }

    function removeRow(btn) {
        if(document.querySelectorAll('.item-row').length > 1) {
            btn.closest('tr').remove();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Constraint',
                text: 'At least one product is required for outbound.',
                confirmButtonColor: '#0b2147'
            });
        }
    }

    function checkStockAlert(row) {
        let sel = $(row).find('.product-select');
        let selectedOpt = sel.find(':selected');
        let stock = parseInt(selectedOpt.data('stock')) || 0;
        let qtyInput = $(row).find('.qty-input');
        let qty = parseInt(qtyInput.val()) || 0;
        
        let alertDiv = $(row).find('.stock-alert-msg');
        if (alertDiv.length === 0) {
            $(row).find('td:first-child').append('<div class="stock-alert-msg mt-1"></div>');
            alertDiv = $(row).find('.stock-alert-msg');
        }
        
        if (sel.val() && qty > stock) {
            alertDiv.html(`<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" style="font-size:0.75rem;"><i class="bi bi-exclamation-triangle-fill me-1"></i>Stok Tidak Mencukupi (Sedia Ada: ${stock} ctn)</span>`);
        } else {
            alertDiv.html('');
        }
    }

    $(document).on('input', '.qty-input', function() {
        let row = $(this).closest('tr');
        checkStockAlert(row);
    });

    // Panggilan AJAX untuk memuatkan batch produk secara dinamik
    $(document).on('change', '.product-select', function() {
        let row = $(this).closest('tr');
        let pid = $(this).val();
        let lastPid = row.data('last-pid') || '';
        let batchSelect = row.find('.batch-select');
        let qtyInput = row.find('.qty-input');
        
        if (pid !== lastPid) {
            row.data('last-pid', pid);
            batchSelect.empty().append('<option value="">-- Auto FEFO --</option>');
            qtyInput.val('');
        }
        
        if (pid) {
            fetch('api/get_batches.php?product_id=' + pid)
            .then(res => res.json())
            .then(batches => {
                // Keep the current selection if it exists, otherwise empty it
                let currentVal = batchSelect.val();
                batchSelect.empty().append('<option value="">-- Auto FEFO --</option>');
                if (batches.length === 0) {
                    batchSelect.empty().append('<option value="" disabled selected>⚠️ Tiada Stok Aktif</option>');
                } else {
                    batches.forEach(b => {
                        let selectedAttr = (b.batch_no === currentVal) ? 'selected' : '';
                        batchSelect.append(`<option value="${b.batch_no}" data-qty="${b.qty_on_hand}" ${selectedAttr}>Batch: ${b.batch_no} (Baki: ${b.qty_on_hand} ctn | Exp: ${b.expiry_date})</option>`);
                    });
                }
                checkStockAlert(row);
            })
            .catch(err => console.error("Gagal mendapatkan batch:", err));
        } else {
            checkStockAlert(row);
        }
    });

    // Validasi stok batch di sebelah client
    function validateBatchStock(el) {
        let row = $(el).closest('tr');
        let qtyInput = row.find('.qty-input');
        let qty = parseInt(qtyInput.val()) || 0;
        
        let batchSelect = row.find('.batch-select');
        let selectedOption = batchSelect.find(':selected');
        let maxQty = parseInt(selectedOption.data('qty'));
        
        if (!isNaN(maxQty) && qty > maxQty) {
            Swal.fire({
                icon: 'warning',
                title: 'Had Baki Dilampaui',
                text: `Stok tersedia bagi batch '${selectedOption.val()}' hanyalah ${maxQty} karton. Sila pilih batch lain atau kurangkan kuantiti.`,
                confirmButtonColor: '#ffc107'
            });
            qtyInput.val(maxQty);
        }
        checkStockAlert(row);
    }

    // Mapping dictionary for SKU shortcodes to keywords in product names
    const skuMap = {
        'F1': 'Fresh 1l',
        'C1': 'Chocolate 1l',
        'K1': 'Kurma 1l',
        'UHTBaristaNoCap': 'Barista 1l',
        'YrPro1NoCap': 'Full Cream Professional 1l',
        'YrC1': 'Yarra Chocolate 1l',
        'YrS1': 'Yarra Strawberry 1l',
        'YrFC1': 'Yarra Full Cream 1l',
        'K200': 'Kurma 200ml',
        'C200': 'Chocolate 200ml',
        'B200': 'Banana 200ml',
        'F200': 'Fresh 200ml',
        'YrS200': 'Yarra Strawberry 200ml',
        'YrC200': 'Yarra Chocolate 200ml',
        'YrFC200': 'Yarra Full Cream 200ml',
        'Ymix200': 'Yog Mix Berry 200ml',
        'YS200': 'Yog Strawberry 200ml',
        'YM200': 'Yog Mango 200ml',
        'G200': 'Grow Up 200ml',
        'A200': 'Almond 200ml',
        'AUns200': 'Almond Unsweetened 200ml',
        'YrS125': 'Yarra Strawberry 125ml',
        'YrC125': 'Yarra Chocolate 125ml',
        'YrFC125': 'Yarra Full Cream 125ml',
        'K125': 'Kurma 125ml',
        'B125': 'Banana 125ml',
        'C125': 'Chocolate 125ml',
        'F125': 'Fresh 125ml',
        'G125': 'Grow Up 125ml',
        'YMix125': 'Yog Mix Berry 125ml',
        'YM125': 'Yog Mango 125ml',
        'YS125': 'Yog Strawberry 125ml',
        'moola100': 'Moola Choco Malt 100ml',
        'Fcp800': 'Full Cream Milk Powder 800g',
        'cm800': 'Chocomalt 800g',
        'cmKaw1kg': 'Chocomalt Kaw 1 Kg',
        'cm10x35': 'Chocomalt Powder 35gx10',
        
        // Twin Matrix DO Specific SKUs
        'P-C1': 'PST Chocolate 1L',
        'P-FyogM': 'PST Farm Yogurt 120G - Mango',
        'P-FYogS': 'PST Farm Yogurt 120G - Strawberry',
        'P-FyogMix': 'PST Farm Yogurt 120G - Mix Berry',
        'P-GYogN': 'PST Greek Yogurt Natural 120G',
        'P-GC100 Apple': 'PST GC Apple',
        'P-GC100 Ori': 'PST GC Original',
        'P-GC100 Melon': 'PST GC Melon',
        'P-GC100 Grape': 'PST GC Grape',
        'P-GC100 Frutti': 'PST GC Tutti Frutti',
        'P-K7': 'PST Kurma 700ml',
        'O1': 'UHT FF Oat 1l',
        'A1': 'UHT FF Almond 1l',
        'O200': 'UHT FF Oat 200ml',
        'CLTA200': 'PST Cafe Latte 200ml'
    };

    function normalizeText(txt) {
        return String(txt || '')
            .toUpperCase()
            .replace(/YOG\b/g, 'YOGURT')
            .replace(/MIXBERRIES/g, 'MIX BERRY')
            .replace(/CHOCO\b/g, 'CHOCOLATE')
            .replace(/1L\b/g, '1L')
            .replace(/2L\b/g, '2L')
            .replace(/[^A-Z0-9\s]/g, ' ')
            .trim();
    }

    function findProductByInvoiceRow(sku, desc) {
        sku = String(sku || '').trim().toUpperCase();
        desc = normalizeText(desc);
        
        // 1. Try to find direct SKU match in our manual mapping dictionary
        if (sku !== '') {
            for (const [key, val] of Object.entries(skuMap)) {
                if (key.toUpperCase() === sku) {
                    const matchedProd = products.find(p => normalizeText(p.name).includes(normalizeText(val)));
                    if (matchedProd) return matchedProd;
                }
            }
        }
        
        // 2. Try to find match using description containing product name keywords
        let bestMatch = null;
        let maxMatches = 0;
        
        products.forEach(p => {
            const pNorm = normalizeText(p.name);
            const pWords = pNorm.split(/\s+/);
            let matchCount = 0;
            pWords.forEach(word => {
                if (word.length > 2 && word !== 'UHT' && word !== 'PST' && word !== 'MILK' && desc.includes(word)) {
                    matchCount++;
                }
            });
            if (matchCount > maxMatches) {
                maxMatches = matchCount;
                bestMatch = p;
            }
        });
        
        if (maxMatches >= 1) {
            return bestMatch;
        }
        
        // 3. Fallback: search product names directly for the SKU as a substring code
        if (sku !== '') {
            const skuMatch = products.find(p => normalizeText(p.name).includes(sku));
            if (skuMatch) return skuMatch;
        }
        
        return null;
    }

    async function parsePDF(file) {
        if (typeof pdfjsLib === 'undefined') {
            throw new Error("Pustaka PDF.js tidak dimuatkan dengan betul.");
        }
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
        
        const arrayBuffer = await file.arrayBuffer();
        const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
        let allRows = [];
        
        for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
            const page = await pdf.getPage(pageNum);
            const textContent = await page.getTextContent();
            
            // Group text items by Y coordinate with a tolerance of 5px
            const yGroups = [];
            textContent.items.forEach(item => {
                const y = item.transform[5];
                const x = item.transform[4];
                const text = item.str.trim();
                if (text === '') return;
                
                let group = yGroups.find(g => Math.abs(g.y - y) < 5);
                if (!group) {
                    group = { y: y, items: [] };
                    yGroups.push(group);
                }
                group.items.push({ x: x, str: text });
            });
            
            // Sort by Y descending
            yGroups.sort((a, b) => b.y - a.y);
            
            yGroups.forEach(g => {
                g.items.sort((a, b) => a.x - b.x);
                const rowText = g.items.map(it => it.str);
                allRows.push(rowText);
            });
        }
        return allRows;
    }

    function parseExcelCSV(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const firstSheetName = workbook.SheetNames[0];
                    const worksheet = workbook.Sheets[firstSheetName];
                    const rows = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
                    resolve(rows);
                } catch (err) {
                    reject(err);
                }
            };
            reader.onerror = err => reject(err);
            reader.readAsArrayBuffer(file);
        });
    }

    document.getElementById('btn_process_invoice').onclick = async function() {
        const fileInput = document.getElementById('invoice_file_input');
        if (!fileInput.files || fileInput.files.length === 0) {
            Swal.fire('Sila pilih fail', 'Pilih fail Excel, CSV, atau PDF invois terlebih dahulu.', 'warning');
            return;
        }

        const file = fileInput.files[0];
        Swal.fire({ title: 'Memproses fail...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const isPDF = file.name.toLowerCase().endsWith('.pdf');

        try {
            let rows = [];
            if (isPDF) {
                rows = await parsePDF(file);
            } else {
                rows = await parseExcelCSV(file);
            }
            
            processInvoiceRows(rows, isPDF);
        } catch (err) {
            console.error(err);
            Swal.fire('Ralat Memproses', err.message || 'Gagal menganalisis struktur fail.', 'error');
        }
    };

    function processInvoiceRows(rows, isPDF) {
        if (rows.length < 2) {
            throw new Error("Fail kosong atau format tidak sah.");
        }

        // Heuristic metadata extraction (Date, Customer Name, DO Number)
        let docRef = '';
        let customerName = '';
        let deliveryDate = '';

        for (let r = 0; r < Math.min(rows.length, 15); r++) {
            const rowStr = rows[r].map(c => String(c || '').trim().toUpperCase()).join(' | ');
            
            if (rowStr.includes('BILL TO') || rowStr.includes('CUSTOMER') || rowStr.includes('DELIVERY TO')) {
                if (isPDF && rows[r+1]) {
                    let part1 = String(rows[r+1][0] || '').trim();
                    let part2 = '';
                    if (rows[r+2] && rows[r+2][0]) {
                        const p2 = String(rows[r+2][0] || '').trim();
                        if (!/^\d+/i.test(p2) && !p2.toUpperCase().includes('JALAN') && !p2.toUpperCase().includes('TAMAN') && !p2.toUpperCase().includes('NO.')) {
                            part2 = p2;
                        }
                    }
                    customerName = (part1 + ' ' + part2).trim();
                } else {
                    for (let offset = 1; offset <= 3; offset++) {
                        if (rows[r+offset]) {
                            const candidate = rows[r+offset].find(c => String(c || '').trim().length > 10);
                            if (candidate) {
                                customerName = String(candidate).trim();
                                break;
                            }
                        }
                    }
                }
            }
            
            rows[r].forEach(cell => {
                const cellStr = String(cell || '').trim();
                if (/^(DO|DOTME|INV|INV-)\w+[-/\w]*/i.test(cellStr)) {
                    docRef = cellStr;
                }
                const dateMatch = cellStr.match(/\b(\d{1,2})[/-](\d{1,2})[/-](\d{4})\b/);
                if (dateMatch) {
                    const yr = parseInt(dateMatch[3]);
                    if (yr >= 2000 && yr <= 2100) {
                        const day = dateMatch[1].padStart(2, '0');
                        const month = dateMatch[2].padStart(2, '0');
                        const year = dateMatch[3];
                        deliveryDate = `${year}-${month}-${day}`;
                    }
                }
            });
        }

        // Find Header Row dynamically
        let headerIndex = -1;
        let skuCol = -1;
        let nameCol = -1;
        let qtyCol = -1;
        let uomCol = -1;

        for (let r = 0; r < rows.length; r++) {
            const row = rows[r].map(c => String(c || '').trim().toUpperCase());
            
            if (row.indexOf('SKU') !== -1) {
                skuCol = row.indexOf('SKU');
            }
            if (row.indexOf('NAME') !== -1) {
                nameCol = row.indexOf('NAME');
            } else if (row.indexOf('DESCRIPTION') !== -1) {
                nameCol = row.indexOf('DESCRIPTION');
            } else if (row.indexOf('DESC') !== -1) {
                nameCol = row.indexOf('DESC');
            }
            
            if (row.indexOf('QTY') !== -1) {
                qtyCol = row.indexOf('QTY');
            } else if (row.indexOf('QUANTITY') !== -1) {
                qtyCol = row.indexOf('QUANTITY');
            }
            
            if (row.indexOf('UOM') !== -1) {
                uomCol = row.indexOf('UOM');
            } else if (row.indexOf('UNIT') !== -1) {
                uomCol = row.indexOf('UNIT');
            }

            if (qtyCol !== -1 && (skuCol !== -1 || nameCol !== -1)) {
                headerIndex = r;
                break;
            }
        }

        // Fallback to default index assumptions if header not found
        if (headerIndex === -1) {
            headerIndex = 0;
            skuCol = 1;
            nameCol = 2;
            qtyCol = 3;
            uomCol = 4;
        }

        // Parse items
        let importedItems = [];
        let unmappedCount = 0;

        for (let r = headerIndex + 1; r < rows.length; r++) {
            const row = rows[r];
            if (!row || row.length < 2) continue;

            const sku = skuCol !== -1 && skuCol < row.length ? String(row[skuCol] || '').trim() : '';
            const nameVal = nameCol !== -1 && nameCol < row.length ? String(row[nameCol] || '').trim() : '';
            const rawQty = qtyCol !== -1 && qtyCol < row.length ? parseFloat(row[qtyCol]) || 0 : 0;
            const uom = uomCol !== -1 && uomCol < row.length ? String(row[uomCol] || '').toLowerCase().trim() : '';

            // Skip invalid rows, delivery charge or total summary rows, or zero/negative quantities
            if (rawQty <= 0) continue;
            if (sku === '' && nameVal === '') continue;
            if (nameVal.toUpperCase().includes('DELIVERY CHARGE') || nameVal.toUpperCase().includes('TOTAL')) continue;

            const matchedProd = findProductByInvoiceRow(sku, nameVal);

            if (matchedProd) {
                const packSize = parseInt(matchedProd.pack_size) || 12;
                let cartons = 0;

                if (uom.includes('ctn') || uom.includes('carton')) {
                    cartons = Math.ceil(rawQty);
                } else {
                    cartons = Math.ceil(rawQty / packSize);
                }

                importedItems.push({
                    product_id: matchedProd.id,
                    qty: cartons,
                    desc: nameVal || sku,
                    orig_qty: rawQty,
                    orig_uom: uom || 'pcs'
                });
            } else {
                unmappedCount++;
            }
        }

        if (importedItems.length === 0) {
            throw new Error("Tiada barisan produk yang sepadan ditemui dalam sistem.");
        }

        // Autofill metadata
        if (customerName) document.querySelector('input[name="customer_name"]').value = customerName;
        if (docRef) document.querySelector('input[name="doc_ref"]').value = docRef;
        if (deliveryDate) document.querySelector('input[name="out_date"]').value = deliveryDate;

        // Clear manual rows and populate with imported rows
        document.getElementById('outBody').innerHTML = '';
        rowCount = 0;
        
        importedItems.forEach(item => {
            addImportedRow(item.product_id, item.qty, item.desc, item.orig_qty, item.orig_uom);
        });

        Swal.fire({
            icon: 'success',
            title: 'Fail Berjaya Diimport!',
            text: `Berjaya mengimport ${importedItems.length} produk dari fail. ${unmappedCount > 0 ? unmappedCount + ' baris gagal dipadankan.' : ''}`,
            confirmButtonColor: '#10b981'
        });
    }

    function addImportedRow(productId, qty, invoiceDesc = '', originalQty = '', originalUom = '') {
        let options = '<option value="">-- Choose Product --</option>';
        products.forEach(p => {
            const selected = p.id == productId ? 'selected' : '';
            options += `<option value="${p.id}" data-stock="${p.qty_on_hand}" ${selected}>${p.name} (Baki: ${p.qty_on_hand} ctn)</option>`;
        });
        
        let descHtml = '';
        if (invoiceDesc) {
            descHtml = `<div class="text-muted small mt-1 ps-1" style="font-size: 0.78rem;">
                <i class="bi bi-file-earmark-text text-primary me-1"></i>Fail asal: <strong>"${invoiceDesc}"</strong> (${originalQty} ${originalUom})
            </div>`;
        }
        
        const html = `
            <tr class="item-row">
                <td class="ps-4" data-label="Product">
                    <select name="items[${rowCount}][product_id]" class="form-select product-select" required>${options}</select>
                    ${descHtml}
                </td>
                <td data-label="Expiry">
                    <select name="items[${rowCount}][batch]" class="form-select form-select-sm batch-select text-center fw-bold" onchange="validateBatchStock(this)">
                        <option value="">-- Auto FEFO --</option>
                    </select>
                </td>
                <td data-label="Qty (ctn)"><input type="number" name="items[${rowCount}][qty]" class="form-control form-control-sm fw-bold text-center qty-input" required min="1" value="${qty}" oninput="validateBatchStock(this)"></td>
                <td class="text-center pe-4" data-label="Action">
                    <button type="button" class="btn btn-outline-danger btn-sm border-0" onclick="removeRow(this)">
                        <i class="bi bi-trash3 fs-5"></i>
                    </button>
                </td>
            </tr>
        `;
        document.getElementById('outBody').insertAdjacentHTML('beforeend', html);
        
        const row = document.getElementById('outBody').lastElementChild;
        $(row).find('.product-select').trigger('change');
        
        // Force retention of qty after the change event handler completes
        $(row).find('.qty-input').val(qty);
        
        checkStockAlert(row);
        
        rowCount++;
        initSelect2();
    }

    document.getElementById('outboundForm').onsubmit = function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Confirm Shipment?',
            text: "This will deduct the stock from inventory balance.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Process Outbound',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                this.submit();
            }
        });
    };
</script>
<?php require_once 'includes/footer.php'; ?>