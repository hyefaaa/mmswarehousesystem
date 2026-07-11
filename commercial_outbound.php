<?php
// commercial_outbound.php - UPGRADED CORPORATE INTERFACE
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';

// Fetch Commercial Products (Excluding PSS for internal use)
$products = $pdo->query("SELECT id, name, category, pack_size FROM products WHERE category != 'PSS' AND is_active=1 ORDER BY name ASC")->fetchAll();


$page_title = 'Commercial Outbound | MMS LOGISTIK';
require_once 'includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    /* Header Styling */
    .mms-header { 
        background: var(--mms-navy); 
        color: white; 
        padding: 1.5rem 0; 
        border-bottom: 4px solid var(--mms-cyan);
        margin-bottom: 2rem;
        margin-top: -1.5rem; /* pull up under navbar */
    }

    /* Card Styling */
    .card { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .section-title { 
        font-size: 0.9rem; 
        font-weight: 700; 
        color: var(--mms-navy); 
        text-transform: uppercase; 
        letter-spacing: 1px;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
    }
    .section-title i { margin-right: 10px; color: var(--mms-cyan); }

    /* Table Styling */
    .item-row td { padding: 12px 15px; vertical-align: middle; }
    
    /* Form Elements */
    .form-control, .form-select {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 15px;
        transition: all 0.2s;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--mms-cyan);
        box-shadow: 0 0 0 3px rgba(0, 174, 239, 0.1);
    }
    
    .product-select { font-weight: 600; color: var(--mms-navy); }
    
    /* Buttons */
    .btn-mms-confirm { 
        background: var(--mms-emerald, #10b981); 
        color: white; 
        font-weight: 700; 
        padding: 12px;
        border-radius: 10px;
        border: none;
        transition: all 0.3s;
    }
    .btn-mms-confirm:hover { 
        background: #059669; 
        transform: translateY(-2px); 
        box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        color: white;
    }
    
    .btn-add-row {
        color: var(--mms-cyan);
        font-weight: 600;
        border: 2px dashed #cbd5e1;
        border-radius: 10px;
        width: 100%;
        padding: 10px;
        transition: all 0.2s;
    }
    .btn-add-row:hover { background: #f1f5f9; border-color: var(--mms-cyan); }
</style>

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-box-arrow-up-right me-2"></i>Stock Outbound</h1>
                <p class="opacity-75 mb-0 fw-light">Commercial & Retail Distribution Command</p>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> Dashboard</a>
                <a href="reconcile.php" class="btn btn-info text-white fw-bold"><i class="bi bi-scale me-1"></i> Reconcile</a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    
    <!-- NEW: Import Invoice (Hybrid Approach) -->
    <div class="card shadow-sm border-0 mb-4 bg-light-subtle">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-2 text-primary d-flex align-items-center">
                <i class="bi bi-file-earmark-arrow-up-fill me-2"></i> Import Invoice / DO (Hybrid Importer)
            </h5>
            <p class="text-muted small mb-3">Muat naik fail invois atau DO harian (format .xlsx, .xls, atau .csv) untuk mengisi senarai produk, kuantiti, tarikh & maklumat pelanggan secara automatik.</p>
            
            <div class="row align-items-center g-3">
                <div class="col-md-9 col-sm-8">
                    <input type="file" id="invoice_file_input" class="form-control form-control-lg border-primary-subtle" accept=".xlsx, .xls, .csv">
                </div>
                <div class="col-md-3 col-sm-4">
                    <button type="button" id="btn_process_invoice" class="btn btn-primary btn-lg w-100 fw-bold py-2 shadow-sm">
                        <i class="bi bi-cloud-arrow-up-fill me-1"></i> PROSES FAIL
                    </button>
                </div>
            </div>
        </div>
    </div>

    <form action="api/save_commercial_outbound.php" method="POST" id="outboundForm" class="card main-card border-0">
        
        <div class="section-title"><i class="bi bi-info-circle-fill"></i> Delivery Information</div>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">DELIVERY DATE</label>
                <input type="date" name="out_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">CUSTOMER / OUTLET</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-shop"></i></span>
                    <input type="text" name="customer_name" class="form-control" placeholder="e.g. Lotus's Kuala Terengganu" required>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">DO / INVOICE REF</label>
                <input type="text" name="doc_ref" class="form-control" placeholder="e.g. DO-2026-0450">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">VEHICLE / DRIVER</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-truck"></i></span>
                    <input type="text" name="vehicle" class="form-control" placeholder="e.g. WWW 9999 (Ali)">
                </div>
            </div>
        </div>

        <div class="section-title"><i class="bi bi-cart-check-fill"></i> Items to Outbound</div>
        <div class="table-responsive mb-3 border rounded-3">
            <table class="table align-middle mb-0" id="outTable">
                <thead>
                    <tr class="table-light text-secondary small fw-bold">
                        <th class="ps-3">Product Selection</th>
                        <th width="25%">Batch / Lot No.</th>
                        <th width="15%" class="text-center">Qty (Carton)</th>
                        <th width="10%" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="outBody">
                    <tr class="item-row">
                        <td class="ps-3">
                            <select name="items[0][product_id]" class="form-select product-select" required>
                                <option value="">-- Choose Product --</option>
                                <?php foreach($products as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="items[0][batch]" class="form-select form-select-sm batch-select text-center fw-bold" onchange="validateBatchStock(this)">
                                <option value="">-- Auto FEFO --</option>
                            </select>
                        </td>
                        <td>
                            <input type="number" name="items[0][qty]" class="form-control form-control-sm fw-bold text-center border-primary-subtle qty-input" required min="1" placeholder="0" oninput="validateBatchStock(this)">
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-outline-danger btn-sm border-0" onclick="removeRow(this)">
                                <i class="bi bi-trash3 fs-5"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="mb-4">
            <button type="button" class="btn btn-add-row" onclick="addRow()">
                <i class="bi bi-plus-lg me-2"></i> Add Another Product
            </button>
        </div>

        <div class="d-grid shadow-sm">
            <button type="submit" class="btn btn-mms-confirm btn-lg py-3">
                <i class="bi bi-send-check-fill me-2"></i> CONFIRM & PROCESS OUTBOUND
            </button>
        </div>
        
    </form>
</div>


<script>
    let rowCount = 1;
    const products = <?php echo json_encode($products); ?>;
    
    function addRow() {
        let options = '<option value="">-- Choose Product --</option>';
        products.forEach(p => options += `<option value="${p.id}">${p.name}</option>`);
        
        const html = `
            <tr class="item-row">
                <td class="ps-4">
                    <select name="items[${rowCount}][product_id]" class="form-select product-select" required>${options}</select>
                </td>
                <td>
                    <select name="items[${rowCount}][batch]" class="form-select form-select-sm batch-select text-center fw-bold" onchange="validateBatchStock(this)">
                        <option value="">-- Auto FEFO --</option>
                    </select>
                </td>
                <td><input type="number" name="items[${rowCount}][qty]" class="form-control form-control-sm fw-bold text-center border-primary-subtle qty-input" required min="1" placeholder="0" oninput="validateBatchStock(this)"></td>
                <td class="text-center pe-4">
                    <button type="button" class="btn btn-outline-danger btn-sm border-0" onclick="removeRow(this)">
                        <i class="bi bi-trash3 fs-5"></i>
                    </button>
                </td>
            </tr>
        `;
        document.getElementById('outBody').insertAdjacentHTML('beforeend', html);
        rowCount++;
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

    // Panggilan AJAX untuk memuatkan batch produk secara dinamik
    $(document).on('change', '.product-select', function() {
        let row = $(this).closest('tr');
        let pid = $(this).val();
        let batchSelect = row.find('.batch-select');
        let qtyInput = row.find('.qty-input');
        
        batchSelect.empty().append('<option value="">-- Auto FEFO --</option>');
        qtyInput.val('');
        
        if (pid) {
            fetch('api/get_batches.php?product_id=' + pid)
            .then(res => res.json())
            .then(batches => {
                if (batches.length === 0) {
                    batchSelect.empty().append('<option value="" disabled selected>⚠️ Tiada Stok Aktif</option>');
                } else {
                    batches.forEach(b => {
                        batchSelect.append(`<option value="${b.batch_no}" data-qty="${b.qty_on_hand}">Batch: ${b.batch_no} (Baki: ${b.qty_on_hand} ctn | Exp: ${b.expiry_date})</option>`);
                    });
                }
            })
            .catch(err => console.error("Gagal mendapatkan batch:", err));
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
        'cm10x35': 'Chocomalt Powder 35gx10'
    };

    function findProductByInvoiceRow(sku, desc) {
        sku = String(sku || '').trim().toUpperCase();
        desc = String(desc || '').trim().toUpperCase();
        
        // 1. Try to find direct SKU match in our manual mapping dictionary
        for (const [key, val] of Object.entries(skuMap)) {
            if (key.toUpperCase() === sku) {
                const matchedProd = products.find(p => p.name.toUpperCase().includes(val.toUpperCase()));
                if (matchedProd) return matchedProd;
            }
        }
        
        // 2. Try to find match using description containing product name keywords
        let bestMatch = null;
        let maxMatches = 0;
        
        products.forEach(p => {
            const pWords = p.name.toUpperCase().split(/\s+/);
            let matchCount = 0;
            pWords.forEach(word => {
                if (word.length > 2 && desc.includes(word)) {
                    matchCount++;
                }
            });
            if (matchCount > maxMatches) {
                maxMatches = matchCount;
                bestMatch = p;
            }
        });
        
        if (maxMatches >= 2) {
            return bestMatch;
        }
        
        // 3. Fallback: search product names directly for the SKU as a substring code
        const skuMatch = products.find(p => p.name.toUpperCase().includes(sku));
        if (skuMatch) return skuMatch;
        
        return null;
    }

    document.getElementById('btn_process_invoice').onclick = function() {
        const fileInput = document.getElementById('invoice_file_input');
        if (!fileInput.files || fileInput.files.length === 0) {
            Swal.fire('Sila pilih fail', 'Pilih fail Excel (.xlsx, .xls) atau CSV invois terlebih dahulu.', 'warning');
            return;
        }

        const file = fileInput.files[0];
        Swal.fire({ title: 'Memproses fail...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                const firstSheetName = workbook.SheetNames[0];
                const worksheet = workbook.Sheets[firstSheetName];
                const rows = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

                if (rows.length < 2) {
                    throw new Error("Fail kosong atau format tidak sah.");
                }

                // Heuristic metadata extraction (Date, Customer Name, DO Number)
                let docRef = '';
                let customerName = '';
                let deliveryDate = '';

                for (let r = 0; r < Math.min(rows.length, 15); r++) {
                    const rowStr = rows[r].map(c => String(c || '').trim().toUpperCase()).join(' | ');
                    
                    if (rowStr.includes('BILL TO') || rowStr.includes('CUSTOMER')) {
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
                    
                    rows[r].forEach(cell => {
                        const cellStr = String(cell || '').trim();
                        if (/^(DO|DOTME|INV|INV-)\w+[-/\w]*/i.test(cellStr)) {
                            docRef = cellStr;
                        }
                        const dateMatch = cellStr.match(/(\d{1,2})[/-](\d{1,2})[/-](\d{4})/);
                        if (dateMatch) {
                            const day = dateMatch[1].padStart(2, '0');
                            const month = dateMatch[2].padStart(2, '0');
                            const year = dateMatch[3];
                            deliveryDate = `${year}-${month}-${day}`;
                        }
                    });
                }

                // Find Item Table Headers row
                let headerIndex = -1;
                let skuCol = -1;
                let descCol = -1;
                let qtyCol = -1;
                let uomCol = -1;

                for (let r = 0; r < rows.length; r++) {
                    const row = rows[r].map(c => String(c || '').trim().toUpperCase());
                    if (row.includes('SKU') && (row.includes('QTY') || row.includes('QUANTITY'))) {
                        headerIndex = r;
                        skuCol = row.indexOf('SKU');
                        qtyCol = row.indexOf('QTY') !== -1 ? row.indexOf('QTY') : row.indexOf('QUANTITY');
                        descCol = row.indexOf('DESCRIPTION') !== -1 ? row.indexOf('DESCRIPTION') : row.indexOf('DESC');
                        uomCol = row.indexOf('UOM') !== -1 ? row.indexOf('UOM') : row.indexOf('UNIT');
                        break;
                    }
                }

                // Fallback in case columns aren't named standard
                if (headerIndex === -1) {
                    headerIndex = 0;
                    skuCol = 1;
                    descCol = 2;
                    qtyCol = 3;
                    uomCol = 4;
                }

                // Parse items
                let importedItems = [];
                let unmappedCount = 0;

                for (let r = headerIndex + 1; r < rows.length; r++) {
                    const row = rows[r];
                    if (!row || row.length < 2) continue;

                    const sku = row[skuCol];
                    const desc = row[descCol] || '';
                    const rawQty = parseFloat(row[qtyCol]) || 0;
                    const uom = String(row[uomCol] || 'ctn').toLowerCase().trim();

                    if (!sku || rawQty <= 0) continue;

                    const matchedProd = findProductByInvoiceRow(sku, desc);

                    if (matchedProd) {
                        const packSize = parseInt(matchedProd.pack_size) || 12;
                        let cartons = 0;

                        if (uom.includes('ctn') || uom.includes('carton')) {
                            cartons = Math.ceil(rawQty);
                        } else {
                            // Convert pcs to carton, rounding up
                            cartons = Math.ceil(rawQty / packSize);
                        }

                        importedItems.push({
                            product_id: matchedProd.id,
                            product_name: matchedProd.name,
                            qty: cartons
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
                    addImportedRow(item.product_id, item.qty);
                });

                Swal.fire({
                    icon: 'success',
                    title: 'Invois Berjaya Diimport!',
                    text: `Berjaya mengimport ${importedItems.length} produk. ${unmappedCount > 0 ? unmappedCount + ' baris gagal dipadankan.' : ''}`,
                    confirmButtonColor: '#10b981'
                });

            } catch (err) {
                console.error(err);
                Swal.fire('Ralat Memproses', err.message || 'Gagal menganalisis struktur fail.', 'error');
            }
        };
        reader.readAsArrayBuffer(file);
    };

    function addImportedRow(productId, qty) {
        let options = '<option value="">-- Choose Product --</option>';
        products.forEach(p => {
            const selected = p.id == productId ? 'selected' : '';
            options += `<option value="${p.id}" ${selected}>${p.name}</option>`;
        });
        
        const html = `
            <tr class="item-row">
                <td class="ps-4">
                    <select name="items[${rowCount}][product_id]" class="form-select product-select" required>${options}</select>
                </td>
                <td>
                    <select name="items[${rowCount}][batch]" class="form-select form-select-sm batch-select text-center fw-bold" onchange="validateBatchStock(this)">
                        <option value="">-- Auto FEFO --</option>
                    </select>
                </td>
                <td><input type="number" name="items[${rowCount}][qty]" class="form-control form-control-sm fw-bold text-center border-primary-subtle qty-input" required min="1" value="${qty}" oninput="validateBatchStock(this)"></td>
                <td class="text-center pe-4">
                    <button type="button" class="btn btn-outline-danger btn-sm border-0" onclick="removeRow(this)">
                        <i class="bi bi-trash3 fs-5"></i>
                    </button>
                </td>
            </tr>
        `;
        document.getElementById('outBody').insertAdjacentHTML('beforeend', html);
        
        const row = document.getElementById('outBody').lastElementChild;
        $(row).find('.product-select').trigger('change');
        rowCount++;
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