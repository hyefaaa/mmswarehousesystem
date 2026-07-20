<?php
// jomcha_outbound.php - JOMCHA SHOP OUTBOUND MANAGEMENT
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';

// Check view permission
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = $_SESSION['role'] ?? '';
if (!is_staff_role($role)) {
    header("Location: login.php");
    exit;
}

// Fetch All Active Products with Stock Levels (Including PSS)
$products = $pdo->query("
    SELECT p.id, p.name, p.category, p.pack_size,
           COALESCE((SELECT SUM(qty_on_hand) FROM inventory_batches WHERE product_id = p.id AND qty_on_hand > 0), 0) as qty_on_hand
    FROM products p
    WHERE p.is_active=1
    ORDER BY p.name ASC
")->fetchAll();

// Fetch last 10 processed Jomcha outbound logs
$history_logs = $pdo->query("
    SELECT id, date, customer, doc_ref, vehicle, created_at
    FROM outbound_logs
    WHERE category = 'Jomcha'
    ORDER BY id DESC
    LIMIT 10
")->fetchAll();

$page_title = 'Jomcha Shop Outbound | MMS LOGISTIK';
require_once 'includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    body {
        background-color: #fcfaff;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }
    
    .page-header {
        background: linear-gradient(135deg, #581c87 0%, #2e1065 100%);
        color: white;
        padding: 35px 0 30px;
        border-bottom: 4px solid #a855f7;
    }
    
    .block-header {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1e1b4b;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        margin-bottom: 1.25rem;
    }
    .block-header i {
        color: #a855f7;
        margin-right: 8px;
    }
    
    .custom-card {
        background: #ffffff;
        border-radius: 18px;
        box-shadow: 0 10px 25px -5px rgba(124, 58, 237, 0.05);
        border: 1px solid #f3e8ff;
        transition: all 0.3s;
    }
    .custom-card:hover {
        box-shadow: 0 20px 25px -5px rgba(124, 58, 237, 0.08);
    }

    .form-control, .form-select {
        border: 1px solid #d8b4fe;
        border-radius: 12px !important;
        padding: 11px 15px;
        font-size: 0.95rem;
        font-weight: 500;
        color: #1e1b4b;
        background-color: #ffffff;
        transition: all 0.2s;
    }
    .form-control:focus, .form-select:focus {
        border-color: #a855f7;
        box-shadow: 0 0 0 4px rgba(168, 85, 247, 0.15);
        outline: none;
    }
    
    .input-group-text {
        border: 1px solid #d8b4fe;
        border-radius: 12px 0 0 12px !important;
        background-color: #faf5ff;
        color: #6b21a8;
        font-weight: 600;
    }
    
    .modern-table-container {
        border-radius: 14px;
        overflow-x: auto;
        border: 1px solid #f3e8ff;
        box-shadow: 0 4px 15px rgba(124, 58, 237, 0.02);
        background: #ffffff;
    }
    
    .btn-add-row {
        background-color: #faf5ff;
        color: #7e22ce;
        border: 2px dashed #c084fc;
        padding: 10px 20px;
        border-radius: 12px;
        font-weight: 600;
        width: 100%;
        transition: all 0.2s;
    }
    .btn-add-row:hover {
        background-color: #f3e8ff;
        border-color: #a855f7;
        color: #6b21a8;
    }
    
    .btn-jomcha-confirm {
        background: linear-gradient(135deg, #a855f7 0%, #7e22ce 100%);
        color: white;
        border: none;
        border-radius: 14px;
        font-weight: 700;
        box-shadow: 0 4px 15px rgba(168, 85, 247, 0.3);
        transition: all 0.3s;
    }
    .btn-jomcha-confirm:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(168, 85, 247, 0.4);
        color: white;
    }
    
    .badge-ref {
        background-color: #faf5ff;
        color: #7e22ce;
        border: 1px solid #e9d5ff;
        font-family: monospace;
        font-size: 0.8rem;
        padding: 4px 8px;
        border-radius: 6px;
    }
</style>

<div class="page-header mb-4">
    <div class="container-fluid px-4 text-center text-md-start">
        <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
            <div>
                <span class="badge bg-purple-subtle text-white mb-2 px-3 py-1.5 fw-bold text-uppercase" style="background: rgba(255,255,255,0.15); font-size: 0.72rem; letter-spacing: 1px;" data-lang="jomcha_outbound_badge">
                    Jomcha Shop Outbound
                </span>
                <h1 class="fw-extrabold m-0 text-white" style="font-size: 2.2rem; letter-spacing: -0.5px;" data-lang="jomcha_outbound_title">
                    Penghantaran Stok Warehouse ke Kedai Jomcha
                </h1>
                <p class="text-white-50 m-0 mt-1" style="font-size: 0.95rem;" data-lang="jomcha_outbound_desc">
                    Gunakan borang ini untuk mengeluarkan stok dari gudang utama dan menghantarnya terus ke Outlet Jomcha.
                </p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-light fw-bold px-4 py-2.5 rounded-pill" style="border-width:2px;">
                    <i class="bi bi-arrow-left me-1"></i> <span data-lang="jomcha_outbound_back">Kembali ke Dashboard</span>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">

    <!-- SEKSYEN EXCEL IMPORT -->
    <div class="custom-card p-4 mb-4" style="background: #faf5ff; border: 1.5px dashed #d8b4fe;">
        <div class="block-header text-purple" style="color: #6b21a8;"><i class="bi bi-file-earmark-excel-fill"></i> <span data-lang="jomcha_import_title">Import DO / Resit Fail (Excel / PDF)</span></div>
        <p class="text-muted small mb-3" data-lang="jomcha_import_desc">Muat naik fail invois atau DO harian (format .xlsx, .xls, .csv, atau .pdf) untuk mengisi senarai produk, kuantiti, tarikh & maklumat penghantaran secara automatik.</p>
        
        <div class="row align-items-center g-3">
            <div class="col-md-9 col-sm-8">
                <input type="file" id="invoice_file_input" class="form-control" accept=".xlsx, .xls, .csv, .pdf">
            </div>
            <div class="col-md-3 col-sm-4">
                <button type="button" id="btn_process_invoice" class="btn btn-jomcha-confirm w-100 py-2 shadow-sm d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-lightning-charge-fill"></i> <span data-lang="jomcha_process_file_btn">PROSES FAIL</span>
                </button>
            </div>
        </div>
    </div>

    <form action="api/save_jomcha_outbound.php" method="POST" id="outboundForm">
        
        <!-- SEKSYEN 1: Maklumat Penghantaran -->
        <div class="custom-card p-4 mb-4">
            <div class="block-header">
                <i class="bi bi-info-circle-fill"></i> <span data-lang="jomcha_outbound_info">Maklumat Outbound Jomcha</span>
            </div>
            
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold" data-lang="jomcha_outbound_date">Tarikh Keluar *</label>
                    <input type="date" name="out_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold" data-lang="jomcha_outbound_ref">No. Rujukan DO / Resit *</label>
                    <input type="text" name="doc_ref" class="form-control" data-lang-placeholder="jomcha_outbound_ref" placeholder="Cth: DO-JOMCHA-001" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold" data-lang="jomcha_outbound_vehicle">Plat Kenderaan / Lori</label>
                    <input type="text" name="vehicle" class="form-control" data-lang-placeholder="jomcha_outbound_vehicle" placeholder="Cth: WX 1234 A" required>
                </div>
            </div>
        </div>

        <!-- SEKSYEN 2: Senarai Produk -->
        <div class="custom-card p-4 mb-4">
            <div class="block-header">
                <i class="bi bi-box-seam-fill"></i> <span data-lang="jomcha_item_list">Senarai Item Keluar</span>
            </div>
            
            <div class="modern-table-container mb-3">
                <table class="table table-hover align-middle mb-0 modern-table" id="outboundTable" style="font-size: 0.9rem;">
                    <thead class="table-light">
                        <tr class="text-secondary small fw-bold">
                            <th width="50%" class="ps-4" data-lang="jomcha_product">Produk</th>
                            <th width="30%" data-lang="jomcha_batch_expiry">Batch & Tarikh Luput</th>
                            <th width="15%" class="text-center" data-lang="jomcha_qty_ctn">Kuantiti (Ctn)</th>
                            <th width="5%" class="text-center" data-lang="jomcha_action">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody id="outBody">
                        <tr class="item-row">
                            <td class="ps-4">
                                <select name="items[0][product_id]" class="form-select product-select" required>
                                    <option value="" data-lang="jomcha_product">-- Pilih Produk --</option>
                                    <?php foreach($products as $p): ?>
                                        <option value="<?= $p['id'] ?>" data-stock="<?= $p['qty_on_hand'] ?>"><?= htmlspecialchars($p['name'] ?? '') ?> (Baki: <?= $p['qty_on_hand'] ?> ctn)</option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="items[0][batch]" class="form-select form-select-sm batch-select text-center fw-bold" onchange="validateBatchStock(this)">
                                    <option value="">-- Auto FEFO --</option>
                                </select>
                            </td>
                            <td>
                                <input type="number" name="items[0][qty]" class="form-control form-control-sm fw-bold text-center qty-input" required min="1" placeholder="0" oninput="validateBatchStock(this)">
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
            
            <div class="mb-2">
                <button type="button" class="btn btn-add-row" onclick="addRow()">
                    <i class="bi bi-plus-lg me-2"></i> <span data-lang="jomcha_add_item">Tambah Item Seterusnya</span>
                </button>
            </div>
        </div>
 
        <div class="d-grid mb-5">
            <button type="submit" class="btn btn-jomcha-confirm btn-lg py-3">
                <i class="bi bi-send-check-fill me-2"></i> <span data-lang="jomcha_process_btn">PROSES OUTBOUND JOMCHA</span>
            </button>
        </div>
        
    </form>
 
    <!-- SEKSYEN 3: Sejarah Outbound Jomcha -->
    <div class="custom-card p-4">
        <div class="block-header">
            <i class="bi bi-clock-history"></i> <span data-lang="jomcha_outbound_history">Sejarah Outbound Jomcha (10 Terakhir)</span>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="table-light">
                    <tr class="text-secondary small fw-bold">
                        <th class="ps-3" data-lang="jomcha_col_process_date">Tarikh Proses</th>
                        <th data-lang="jomcha_col_out_date">Tarikh Keluar</th>
                        <th data-lang="jomcha_col_do_ref">No. DO Rujukan</th>
                        <th data-lang="jomcha_col_customer">Kanal / Pelanggan</th>
                        <th data-lang="jomcha_col_driver">Pemandu / Lori</th>
                        <th class="text-center" data-lang="jomcha_action">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history_logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted" data-lang="jomcha_empty_history">
                                <i class="bi bi-info-circle-fill fs-4 d-block mb-1"></i> Tiada rekod pemprosesan ditemui.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history_logs as $log): ?>
                            <tr>
                                <td class="ps-3 text-muted"><?= date('d/m/Y h:i A', strtotime($log['created_at'])) ?></td>
                                <td class="fw-bold text-dark"><?= date('d/m/Y', strtotime($log['date'])) ?></td>
                                <td><span class="badge-ref"><?= htmlspecialchars($log['doc_ref'] ?? '' ?: 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($log['customer'] ?? '') ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($log['vehicle'] ?? '' ?: '-') ?></span></td>
                                <td class="text-center">
                                    <a href="outbound_history.php?search=<?= urlencode($log['doc_ref']) ?>" class="btn btn-sm btn-outline-purple py-1 px-3 fw-bold btn-outline-primary">
                                        <i class="bi bi-eye-fill me-1"></i> <span data-lang="jomcha_btn_view">Papar Detail</span>
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

<!-- SheetJS Library for client-side Excel reading -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script>
    let rowCount = 1;
    const products = <?= json_encode($products) ?>;

    function initSelect2() {
        $('.product-select').each(function() {
            if (!$(this).hasClass("select2-hidden-accessible")) {
                $(this).select2({
                    width: '100%',
                    placeholder: '-- Pilih Produk --',
                    allowClear: true
                });
            }
        });
    }

    $(document).ready(function() {
        initSelect2();
    });

    function addRow(productId = '', qty = '') {
        let options = '<option value="">-- Pilih Produk --</option>';
        products.forEach(p => {
            const selected = (p.id == productId) ? 'selected' : '';
            options += `<option value="${p.id}" data-stock="${p.qty_on_hand}" ${selected}>${p.name} (Baki: ${p.qty_on_hand} ctn)</option>`;
        });
        
        const qtyVal = qty ? qty : '';
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
                <td>
                    <input type="number" name="items[${rowCount}][qty]" class="form-control form-control-sm fw-bold text-center qty-input" value="${qtyVal}" required min="1" placeholder="0" oninput="validateBatchStock(this)">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-outline-danger btn-sm border-0" onclick="removeRow(this)">
                        <i class="bi bi-trash3 fs-5"></i>
                    </button>
                </td>
            </tr>
        `;
        document.getElementById('outBody').insertAdjacentHTML('beforeend', html);
        
        // Initialize Select2 for the newly added dropdown
        initSelect2();
        
        if (productId) {
            const newRow = document.getElementById('outBody').lastElementChild;
            const selectEl = newRow.querySelector('.product-select');
            $(selectEl).val(productId).trigger('change');
        }
        
        rowCount++;
    }

    // JS Excel & PDF parsing logic
    document.getElementById('btn_process_invoice').addEventListener('click', function() {
        const fileInput = document.getElementById('invoice_file_input');
        const file = fileInput.files[0];
        if (!file) {
            Swal.fire('Ralat!', 'Sila pilih fail invois/DO terlebih dahulu.', 'error');
            return;
        }

        const ext = file.name.split('.').pop().toLowerCase();
        if (ext === 'pdf') {
            processPdfInvoice(file);
        } else if (['xlsx', 'xls', 'csv'].includes(ext)) {
            processExcelInvoice(file);
        } else {
            Swal.fire('Format Tidak Disokong!', 'Sila muat naik fail dalam format .xlsx, .xls, .csv, atau .pdf.', 'warning');
        }
    });

    function processExcelInvoice(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                const firstSheetName = workbook.SheetNames[0];
                const worksheet = workbook.Sheets[firstSheetName];
                const rows = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

                if (rows.length < 2) {
                    Swal.fire('Ralat!', 'Fail Excel kosong atau tidak sah.', 'error');
                    return;
                }

                parseAndMatchRows(rows);
            } catch (err) {
                console.error(err);
                Swal.fire('Gagal membaca fail Excel!', err.message, 'error');
            }
        };
        reader.readAsArrayBuffer(file);
    }

    function processPdfInvoice(file) {
        Swal.fire({
            title: 'Memproses PDF...',
            text: 'Sila tunggu, sistem sedang menganalisis dokumen PDF.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        const formData = new FormData();
        formData.append('pdf_file', file);

        fetch('api/parse_pdf_invoice.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            Swal.close();
            if (data.success) {
                if (data.metadata) {
                    if (data.metadata.doc_ref) {
                        document.querySelector('input[name="doc_ref"]').value = data.metadata.doc_ref;
                    }
                    if (data.metadata.date) {
                        document.querySelector('input[name="out_date"]').value = data.metadata.date;
                    }
                }
                
                if (data.items && data.items.length > 0) {
                    populateParsedItems(data.items);
                } else {
                    Swal.fire('Amaran!', 'Fail PDF berjaya diproses tetapi tiada produk yang dikenali ditemui.', 'warning');
                }
            } else {
                Swal.fire('Gagal memproses PDF!', data.message || 'Ralat tidak diketahui.', 'error');
            }
        })
        .catch(err => {
            Swal.close();
            console.error(err);
            Swal.fire('Ralat Hubungan!', 'Gagal menghubungi server pemprosesan PDF.', 'error');
        });
    }

    function parseAndMatchRows(rows) {
        let headerRowIdx = -1;
        let descColIdx = -1;
        let qtyColIdx = -1;
        let doRef = '';
        let invoiceDate = '';

        for (let r = 0; r < Math.min(rows.length, 15); r++) {
            const row = rows[r];
            if (!row) continue;
            for (let c = 0; c < row.length; c++) {
                const cellVal = String(row[c] || '').trim();
                
                if (/^(DO|Invoice|Inv|No\b|Ref|No\. Rujukan)/i.test(cellVal) && c + 1 < row.length) {
                    const nextVal = String(row[c+1] || '').trim();
                    if (nextVal.length > 3 && !doRef) {
                        doRef = nextVal;
                    }
                }
                if (/^(DO No|Invoice No|No\. DO|No\. Invois)[:\s]+(.*)/i.test(cellVal)) {
                    const match = cellVal.match(/^(DO No|Invoice No|No\. DO|No\. Invois)[:\s]+(.*)/i);
                    if (match && match[2].trim()) doRef = match[2].trim();
                }

                if (/^(Date|Tarikh)/i.test(cellVal) && c + 1 < row.length) {
                    const nextVal = String(row[c+1] || '').trim();
                    if (nextVal && !invoiceDate) {
                        invoiceDate = parseDateString(nextVal);
                    }
                }
            }
        }

        for (let r = 0; r < rows.length; r++) {
            const row = rows[r];
            if (!row) continue;
            for (let c = 0; c < row.length; c++) {
                const val = String(row[c] || '').toLowerCase();
                if (val.includes('product') || val.includes('description') || val.includes('perihalan') || val.includes('nama barang') || val.includes('sku')) {
                    descColIdx = c;
                }
                if (val.includes('qty') || val.includes('quantity') || val.includes('kuantiti') || val.includes('ctn') || val.includes('karton') || val.includes('jumlah')) {
                    qtyColIdx = c;
                }
            }
            if (descColIdx !== -1 && qtyColIdx !== -1) {
                headerRowIdx = r;
                break;
            }
        }

        if (headerRowIdx === -1 || descColIdx === -1 || qtyColIdx === -1) {
            descColIdx = 1;
            qtyColIdx = 2;
            headerRowIdx = 0;
        }

        const parsedItems = [];
        for (let r = headerRowIdx + 1; r < rows.length; r++) {
            const row = rows[r];
            if (!row || row.length <= Math.max(descColIdx, qtyColIdx)) continue;

            const rawName = String(row[descColIdx] || '').trim();
            const rawQty = parseFloat(row[qtyColIdx]);

            if (rawName && !isNaN(rawQty) && rawQty > 0) {
                parsedItems.push({
                    name: rawName,
                    qty: Math.ceil(rawQty)
                });
            }
        }

        if (doRef) document.querySelector('input[name="doc_ref"]').value = doRef;
        if (invoiceDate) document.querySelector('input[name="out_date"]').value = invoiceDate;

        if (parsedItems.length > 0) {
            populateParsedItems(parsedItems);
        } else {
            Swal.fire('Ralat!', 'Tiada senarai produk yang valid dapat dikesan di dalam fail.', 'error');
        }
    }

    function parseDateString(str) {
        const parts = str.match(/(\d{4})[-/](\d{2})[-/](\d{2})/);
        if (parts) return `${parts[1]}-${parts[2]}-${parts[3]}`;
        const partsDMY = str.match(/(\d{2})[-/](\d{2})[-/](\d{4})/);
        if (partsDMY) return `${partsDMY[3]}-${partsDMY[2]}-${partsDMY[1]}`;
        return '';
    }

    function populateParsedItems(parsedItems) {
        document.getElementById('outBody').innerHTML = '';
        rowCount = 0;

        let matchedCount = 0;
        let unmatchedItems = [];

        parsedItems.forEach(item => {
            const matchedProduct = matchProductName(item.name);
            if (matchedProduct) {
                addRow(matchedProduct.id, item.qty);
                matchedCount++;
            } else {
                unmatchedItems.push(item.name);
            }
        });

        if (unmatchedItems.length > 0) {
            Swal.fire({
                title: 'Import Selesai dengan Amaran!',
                html: `<p>Berjaya memadankan <strong>${matchedCount}</strong> produk Jomcha.</p>
                       <p class="text-danger mb-0"><strong>${unmatchedItems.length} produk berikut tidak dapat dipadankan:</strong></p>
                       <div class="text-start bg-light p-2 rounded border mt-2 scrollable-div" style="max-height: 150px; overflow-y: auto; font-size: 0.8rem;">
                           <ul>
                               ${unmatchedItems.map(name => `<li>${name}</li>`).join('')}
                           </ul>
                       </div>
                       <p class="small text-muted mt-2">Sila tambah item yang tidak dapat dipadankan secara manual.</p>`,
                icon: 'warning'
            });
        } else {
            Swal.fire('Berjaya!', `Berjaya mengimport ${matchedCount} produk dari fail.`, 'success');
        }
    }

    function matchProductName(name) {
        name = name.toLowerCase().trim();
        let bestMatch = null;
        let bestScore = 0;

        for (let i = 0; i < products.length; i++) {
            const p = products[i];
            const pName = p.name.toLowerCase();
            
            if (name === pName) {
                return p;
            }
            if (name.includes(pName) || pName.includes(name)) {
                const score = Math.min(name.length, pName.length) / Math.max(name.length, pName.length);
                if (score > bestScore) {
                    bestScore = score;
                    bestMatch = p;
                }
            }
        }

        if (bestScore > 0.4) {
            return bestMatch;
        }
        return null;
    }

    function removeRow(btn) {
        if(document.querySelectorAll('.item-row').length > 1) {
            let row = btn.closest('tr');
            let sel = $(row).find('.product-select');
            if (sel.data('select2')) {
                sel.select2('destroy');
            }
            row.remove();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Had Minimum',
                text: 'Sila masukkan sekurang-kurangnya satu produk untuk outbound.',
                confirmButtonColor: '#7e22ce'
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
                confirmButtonColor: '#a855f7'
            });
            qtyInput.val(maxQty);
        }
        checkStockAlert(row);
    }

    document.getElementById('outboundForm').onsubmit = function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Sahkan Penghantaran?',
            text: "Ini akan menolak baki stok di dalam inventory gudang.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#a855f7',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Proses Outbound',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                this.submit();
            }
        });
    };
</script>

<?php require_once 'includes/footer.php'; ?>
