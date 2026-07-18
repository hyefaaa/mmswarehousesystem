<?php
// import_schools.php
// Module to Bulk Update School Data from CSV

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';
ini_set('log_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';


$page_title = 'Import School Data';
require_once 'includes/header.php';
?>

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Import School Database</h1>
                <p class="opacity-75 mb-0 fw-light">Bulk update school registries, student counts, and dealer assignments from CSV</p>
            </div>
            <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> Dashboard</a>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-upload me-2 text-info"></i>Upload CSV File</h5>
                </div>
                <div class="card-body p-4 bg-white">
                    
                    <div class="alert alert-info border-0 bg-info bg-opacity-10 text-info rounded-3 p-3 mb-4">
                        <h6 class="fw-bold mb-2"><i class="bi bi-info-circle-fill me-1"></i>Instructions:</h6>
                        <ul class="mb-0 small ps-3">
                            <li>Save your Excel file as <b>CSV (Comma delimited)</b>.</li>
                            <li>The system will match schools by <b>KOD SEKOLAH</b>.</li>
                            <li>It will update: <b>Student Count, HD Assignment, Zone, Address</b>.</li>
                            <li>Ensure HD Names in the file match the HD Names in the system exactly (e.g., "WALI", "NOR IDAYU").</li>
                        </ul>
                    </div>

                    <form action="api/save_school_import.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label for="csv_file" class="form-label fw-bold small text-secondary">Select CSV File *</label>
                            <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold" style="background: #0f172a; border: none;">
                            <i class="bi bi-cloud-arrow-up-fill me-1"></i> Upload & Update Database
                        </button>
                    </form>

                </div>
            </div>

            <!-- Sample Column Reference -->
            <div class="card shadow-sm border-0 border-start border-3 border-info">
                <div class="card-body p-3">
                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-table me-2 text-info"></i>Required CSV Columns (Order doesn't matter):</h6>
                    <p class="text-muted small mb-0 font-monospace">
                        KOD SEKOLAH, NAMA SEKOLAH, BIL PELAJAR, ALAMAT, POSKOD, BANDAR, DAERAH, NEGERI, NO TEL, ZON HD, Nama HD
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>