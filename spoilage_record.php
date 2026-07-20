<?php
// spoilage_record.php
// Updated: Corporate Theme for Moo Moo Supplies
// Logic: Strictly preserved original batch selection and calculation

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

try {
    $batches = $pdo->query("
        SELECT b.id, b.batch_no, b.qty_on_hand, b.expiry_date, p.name as product_name, p.pack_size
        FROM inventory_batches b
        JOIN products p ON b.product_id = p.id
        WHERE b.qty_on_hand > 0
        ORDER BY p.name ASC
    ")->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$page_title = 'MMS | Damage & Spoilage Report';
require_once 'includes/header.php';
?>
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    :root {
        --primary-color: #2c3e50;
        --accent-red: #e74c3c;
        --bg-light: #f8f9fa;
        --sidebar-width: 250px;
    }
    
    .brand-text {
        font-weight: 800;
        color: var(--primary-color);
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    .card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        margin-bottom: 1.5rem;
    }

    .card-header {
        background-color: #fff;
        border-bottom: 1px solid #f0f0f0;
        padding: 15px 20px;
        font-weight: 700;
        color: var(--primary-color);
    }

    .table thead th {
        background-color: #fcfcfc;
        color: #6c757d;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #f0f0f0;
    }

    .calc-box {
        font-size: 0.8rem;
        background: #fff5f5;
        padding: 6px 12px;
        border-radius: 6px;
        border: 1px solid #fed7d7;
        color: #c53030;
        display: inline-block;
    }

    .btn-mms-primary {
        background-color: var(--primary-color);
        color: white;
        border: none;
        font-weight: 600;
    }

    .btn-mms-danger {
        background-color: var(--accent-red);
        color: white;
        border: none;
        font-weight: 700;
        transition: all 0.3s ease;
    }

    .btn-mms-danger:hover {
        background-color: #c0392b;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
    }

    .preview-thumb {
        height: 60px;
        width: 60px;
        object-fit: cover;
        border-radius: 6px;
        border: 2px solid #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: none;
    }
</style>

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-exclamation-octagon-fill me-2"></i><span data-lang="nav_spoilage_report">Damage & Spoilage Report</span></h1>
                <p class="opacity-75 mb-0 fw-light" data-lang="spoil_subtitle">Laporan kerosakan produk dan pelarasan baki stok automatik</p>
            </div>
            <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> <span data-lang="nav_dashboard">Dashboard</span></a>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    <form id="spoilageForm" action="api/save_spoilage.php" method="POST" enctype="multipart/form-data" class="card main-card border-0">
        
        <div class="section-title"><i class="bi-info-circle-fill bi"></i> <span data-lang="spoil_sec_report_info">1. MAKLUMAT LAPORAN</span></div>
        <div class="card-body bg-white p-4 mb-4 rounded shadow-sm border">
            <div class="row g-4">
                <div class="col-md-3">
                    <label class="form-label small fw-bold" data-lang="spoil_lbl_date_discovery">Tarikh Penemuan</label>
                    <input type="date" name="report_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-bold" data-lang="spoil_lbl_general_remarks">Ulasan Umum</label>
                    <input type="text" name="remarks" class="form-control" placeholder="Sila nyatakan punca (Contoh: Kerosakan Transit)" data-lang-placeholder="spoil_placeholder_remarks">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-primary"><i class="fas fa-camera me-1"></i> <span data-lang="spoil_lbl_upload_evidence">Muat Naik Bukti</span></label>
                    <input type="file" name="spoilage_photos[]" id="photo_input" class="form-control" accept="image/*" capture="environment" multiple>
                    <div id="preview_container" class="d-flex flex-wrap gap-2 mt-2"></div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span data-lang="spoil_sec_items">2. SENARAI ITEM & KUANTITI</span>
                <span class="badge bg-secondary font-monospace">Auto-Pcs Conversion</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4" width="40%" data-lang="spoil_col_product_batch">Batch Produk</th>
                                <th width="25%" data-lang="spoil_col_qty">Kuantiti (Pcs/Ctn)</th>
                                <th width="25%" data-lang="spoil_col_reason">Sebab Kerosakan</th>
                                <th width="10%" class="text-center" data-lang="lbl_action">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="spoilageBody">
                            <tr>
                                <td class="p-4">
                                    <select name="items[0][batch_id]" class="form-select batch-select shadow-sm" required onchange="calculateRow(this)">
                                        <option value="" data-pcs="1" data-lang="spoil_select_batch_placeholder">-- Pilih Batch --</option>
                                        <?php foreach($batches as $b): ?>
                                            <option value="<?= $b['id'] ?>" data-pcs="<?= $b['pack_size'] ?>" data-expiry="<?= $b['expiry_date'] ?>" data-batch="<?= htmlspecialchars($b['batch_no'] ?? '') ?>">
                                                <?= htmlspecialchars($b['product_name'] ?? '') ?> (B: <?= $b['batch_no'] ?> | Stok: <?= $b['qty_on_hand'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="batch-info-box mt-2 d-none" style="font-size: 0.8rem;">
                                        <span class="badge bg-light text-dark border me-1 py-2 px-3" style="font-size: 0.76rem; font-weight: 600;">
                                            <i class="far fa-calendar-alt text-danger me-1"></i>Expiry: <span class="expiry-date-text">-</span>
                                        </span>
                                        <span class="badge bg-light text-dark border py-2 px-3" style="font-size: 0.76rem; font-weight: 600;">
                                            <i class="fas fa-barcode text-primary me-1"></i>Batch: <span class="batch-no-text">-</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div class="input-group shadow-sm">
                                        <input type="number" step="1" class="form-control qty-input" placeholder="0" required oninput="calculateRow(this)">
                                        <select class="form-select unit-type" style="max-width: 90px;" onchange="calculateRow(this)">
                                            <option value="pcs">pcs</option>
                                            <option value="ctn">ctn</option>
                                        </select>
                                    </div>
                                    <input type="hidden" name="items[0][qty]" class="final-qty-input">
                                    <div class="calc-box mt-2 d-none animate__animated animate__fadeIn">
                                        <i class="fas fa-calculator me-2"></i>Total: <span class="final-qty-text">0</span> pcs
                                    </div>
                                </td>
                                <td class="p-4">
                                    <select name="items[0][reason]" class="form-select shadow-sm" required>
                                        <option value="Leaking">Leaking</option>
                                        <option value="Expired">Expired</option>
                                        <option value="Crushed">Crushed</option>
                                        <option value="Pest Damage">Pest Damage</option>
                                    </select>
                                </td>
                                <td class="text-center p-4"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white border-0 py-3">
                <button type="button" class="btn btn-sm btn-outline-dark px-3" onclick="addRow()">
                    <i class="fas fa-plus me-1"></i> <span data-lang="spoil_btn_add_item">Tambah Item</span>
                </button>
            </div>
        </div>

        <div class="mt-4 mb-5">
            <button type="submit" class="btn btn-mms-danger btn-lg w-100 py-3 shadow">
                <i class="fas fa-check-double me-2"></i><span data-lang="spoil_btn_submit">SAHKAN & TOLAK STOK</span>
            </button>
        </div>
    </form>
</div>

<script>
    let rowCount = 1;
    const selectPlaceholderText = typeof MMS_LANG !== 'undefined' ? MMS_LANG.t('spoil_select_batch_placeholder') : "-- Pilih Batch --";
    const batchOptions = `<option value="" data-pcs="1">${selectPlaceholderText}</option><?php 
        $opt = '';
        foreach($batches as $b) {
            $opt .= '<option value="'.$b['id'].'" data-pcs="'.$b['pack_size'].'" data-expiry="'.$b['expiry_date'].'" data-batch="'.htmlspecialchars($b['batch_no'] ?? '').'">'.htmlspecialchars($b['product_name'] ?? '').' (B: '.$b['batch_no'].' | Stok: '.$b['qty_on_hand'].')</option>';
        }
        echo $opt;
    ?>`;

    $(document).ready(function() {
        const selectPlaceholder = typeof MMS_LANG !== 'undefined' ? MMS_LANG.t('spoil_select_batch_placeholder') : "-- Pilih Batch --";
        $('.batch-select').select2({
            placeholder: selectPlaceholder,
            width: '100%'
        });
    });

    function calculateRow(el) {
        const row = el.closest('tr');
        const batch = row.querySelector('.batch-select');
        const selectedOpt = batch.options[batch.selectedIndex];
        
        // Handle Expiry Date & Batch display
        const infoBox = row.querySelector('.batch-info-box');
        if (infoBox) {
            const expiry = selectedOpt ? selectedOpt.dataset.expiry : '';
            const batchNo = selectedOpt ? selectedOpt.dataset.batch : '';
            if (expiry || batchNo) {
                let formattedDate = expiry || 'No Expiry';
                if (expiry && expiry !== '0000-00-00') {
                    try {
                        const d = new Date(expiry);
                        if (!isNaN(d.getTime())) {
                            const day = String(d.getDate()).padStart(2, '0');
                            const month = String(d.getMonth() + 1).padStart(2, '0');
                            const year = d.getFullYear();
                            formattedDate = `${day}/${month}/${year}`;
                        }
                    } catch (e) {}
                }
                infoBox.querySelector('.expiry-date-text').innerText = formattedDate;
                infoBox.querySelector('.batch-no-text').innerText = batchNo || 'N/A';
                infoBox.classList.remove('d-none');
            } else {
                infoBox.classList.add('d-none');
            }
        }
        
        const pcsPerCtn = parseInt(selectedOpt ? selectedOpt.dataset.pcs : 1) || 1;
        const val = parseFloat(row.querySelector('.qty-input').value) || 0;
        const type = row.querySelector('.unit-type').value;
        const display = row.querySelector('.calc-box');
        
        let total = type === 'ctn' ? (val * pcsPerCtn) : val;
        
        row.querySelector('.final-qty-input').value = total;
        row.querySelector('.final-qty-text').innerText = total;
        display.classList.toggle('d-none', val <= 0);
    }

    function addRow() {
        const tbody = document.getElementById('spoilageBody');
        const selectPlaceholder = typeof MMS_LANG !== 'undefined' ? MMS_LANG.t('spoil_select_batch_placeholder') : "-- Pilih Batch --";
        const html = `
            <tr class="border-top">
                <td class="p-4">
                    <select name="items[${rowCount}][batch_id]" class="form-select batch-select" required onchange="calculateRow(this)">
                        ${batchOptions}
                    </select>
                    <div class="batch-info-box mt-2 d-none" style="font-size: 0.8rem;">
                        <span class="badge bg-light text-dark border me-1 py-2 px-3" style="font-size: 0.76rem; font-weight: 600;">
                            <i class="far fa-calendar-alt text-danger me-1"></i>Expiry: <span class="expiry-date-text">-</span>
                        </span>
                        <span class="badge bg-light text-dark border py-2 px-3" style="font-size: 0.76rem; font-weight: 600;">
                            <i class="fas fa-barcode text-primary me-1"></i>Batch: <span class="batch-no-text">-</span>
                        </span>
                    </div>
                </td>
                <td class="p-4">
                    <div class="input-group">
                        <input type="number" class="form-control qty-input" placeholder="0" required oninput="calculateRow(this)">
                        <select class="form-select unit-type" style="max-width: 90px;" onchange="calculateRow(this)">
                            <option value="pcs">pcs</option>
                            <option value="ctn">ctn</option>
                        </select>
                    </div>
                    <input type="hidden" name="items[${rowCount}][qty]" class="final-qty-input">
                    <div class="calc-box mt-2 d-none">Total: <span class="final-qty-text">0</span> pcs</div>
                </td>
                <td class="p-4">
                    <select name="items[${rowCount}][reason]" class="form-select" required>
                        <option value="Leaking">Leaking</option>
                        <option value="Expired">Expired</option>
                        <option value="Crushed">Crushed</option>
                        <option value="Pest Damage">Pest Damage</option>
                    </select>
                </td>
                <td class="text-center p-4">
                    <button type="button" class="btn btn-outline-danger btn-sm rounded-circle" onclick="this.closest('tr').remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>`;
        tbody.insertAdjacentHTML('beforeend', html);
        $(`select[name="items[${rowCount}][batch_id]"]`).select2({
            placeholder: selectPlaceholder,
            width: '100%'
        });
        rowCount++;
    }

    let compressedFiles = [];

    document.getElementById('photo_input').onchange = async e => {
        const cont = document.getElementById('preview_container');
        cont.innerHTML = '';
        compressedFiles = [];
        
        const files = Array.from(e.target.files);
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            
            // Render loading or empty thumbnail first
            const img = document.createElement('img');
            img.className = 'preview-thumb';
            cont.appendChild(img);
            
            try {
                // Resize image to max 800px width with 0.75 quality compression
                const compressedBlob = await compressImage(file, 800, 0.75);
                compressedFiles.push(new File([compressedBlob], file.name, { type: 'image/jpeg' }));
                img.src = URL.createObjectURL(compressedBlob);
            } catch (err) {
                console.error("Compression failed, using original file: ", err);
                compressedFiles.push(file);
                img.src = URL.createObjectURL(file);
            }
        }
    };

    function compressImage(file, maxWidth, quality) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = event => {
                const img = new Image();
                img.src = event.target.result;
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    let width = img.width;
                    let height = img.height;
                    
                    if (width > maxWidth) {
                        height = Math.round((height * maxWidth) / width);
                        width = maxWidth;
                    }
                    
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    canvas.toBlob(blob => {
                        if (blob) resolve(blob);
                        else reject(new Error("Canvas export failed"));
                    }, 'image/jpeg', quality);
                };
                img.onerror = err => reject(err);
            };
            reader.onerror = err => reject(err);
        });
    }

    document.getElementById('spoilageForm').onsubmit = function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Hantar Laporan?',
            text: "Stok akan ditolak dari gudang secara automatik.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c',
            confirmButtonText: 'Sahkan'
        }).then(res => {
            if(res.isConfirmed) {
                Swal.fire({ title: 'Menghantar...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                
                // Build form data manually to swap raw files with compressed ones
                const fd = new FormData(this);
                fd.delete('spoilage_photos[]');
                compressedFiles.forEach(file => {
                    fd.append('spoilage_photos[]', file);
                });

                fetch(this.action, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if(data.status==='success') Swal.fire('Berjaya!', data.message, 'success').then(()=>location.href='index.php');
                    else Swal.fire('Ralat', data.message, 'error');
                })
                .catch(err => {
                    Swal.fire('Ralat', 'Gagal memuat naik data laporan.', 'error');
                });
            }
        });
    };
</script>
<?php require_once 'includes/footer.php'; ?>