<?php
// import_products.php
require_once 'config/db.php';


$page_title = 'Master Product Import | MMS';
require_once 'includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    .template-table { font-size: 0.8rem; background: #f8f9fa; }
    .card-import { border-top: 5px solid var(--mms-cyan); }
</style>
<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-box-seam me-2"></i>Master Product Import</h1>
                <p class="opacity-75 mb-0 fw-light">Bulk update product catalogs, categories, pack sizes, and capacities from CSV</p>
            </div>
            <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> Dashboard</a>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-upload me-2 text-info"></i>Master Product Import & Update</h5>
                </div>
                <div class="card-body p-4 bg-white">
                    <p class="text-muted small mb-4">Use this tool to bulk-update your product master list. If a product <strong>name</strong> matches an existing record, the system will update its details. Otherwise, a new product will be created.</p>
                    
                    <div class="alert alert-warning border-0 bg-warning bg-opacity-10 text-warning rounded-3 p-3 mb-4">
                        <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-1"></i>CSV Column Order (Required):</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered template-table bg-white mb-0" style="font-size: 0.8rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>name</th>
                                        <th>category</th>
                                        <th>uom</th>
                                        <th>pack_size</th>
                                        <th>pallet_capacity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Yarra Full Cream 1L</td>
                                        <td>PST</td>
                                        <td>Carton</td>
                                        <td>12</td>
                                        <td>75</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <form id="importForm" action="api/process_product_import.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label class="form-label fw-bold small text-secondary">Choose CSV File *</label>
                            <input type="file" name="product_file" class="form-control" accept=".csv" required>
                            <div class="form-text small">Save your Excel as <strong>CSV (Comma Delimited)</strong> before uploading.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold" style="background: #0f172a; border: none;">
                            <i class="bi bi-cloud-arrow-up-fill me-1"></i> Process Master List
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('importForm').onsubmit = function(e) {
    e.preventDefault();
    Swal.fire({ 
        title: 'Updating Database...', 
        text: 'Please wait while we process the records.',
        allowOutsideClick: false, 
        didOpen: () => { Swal.showLoading(); } 
    });
    
    fetch(this.action, { method: 'POST', body: new FormData(this) })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            Swal.fire('Import Complete', data.message, 'success').then(() => location.href='index.php');
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    });
};
</script>
<?php require_once 'includes/footer.php'; ?>