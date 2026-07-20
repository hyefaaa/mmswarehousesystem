<?php
// spoilage_report.php
// DASHBOARD: Manage Spoilage, Photos, and Supplier Claims
require_once 'config/db.php';

// Fetch reports with product and batch details
try {
    $query = "SELECT sl.*, p.name as product_name, b.batch_no 
              FROM spoilage_logs sl
              JOIN inventory_batches b ON sl.batch_id = b.id
              JOIN products p ON b.product_id = p.id
              ORDER BY sl.reported_at DESC";
    $reports = $pdo->query($query)->fetchAll() ?: [];
} catch (Exception $e) {
    $reports = [];
}


$page_title = 'Spoilage & Supplier Claims';
require_once 'includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    .table-container { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .thumb-gallery img { width: 45px; height: 45px; object-fit: cover; border-radius: 6px; margin-right: 4px; border: 1px solid #dee2e6; }
    .badge-status { font-size: 0.75rem; padding: 5px 10px; border-radius: 15px; }
</style>

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center flex-column flex-md-row gap-3">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-card-list me-2"></i>Spoilage & Claims List</h1>
                <p class="opacity-75 mb-0 fw-light">Manage Spoilage Logs, Photos, and Supplier Claims</p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="runCleanup()" class="btn btn-outline-light">
                    <i class="bi bi-trash3 me-1"></i> Cleanup Photos
                </button>
                <a href="spoilage_record.php" class="btn btn-info text-white fw-bold"><i class="bi bi-plus-lg me-1"></i> New Spoilage Report</a>
                <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> Dashboard</a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    <div class="card main-card border-0 mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3">Discovery</th>
                        <th>Product / Batch</th>
                        <th>Qty (pcs)</th>
                        <th>Photos</th>
                        <th>Status</th>
                        <th>Supplier Sent</th>
                        <th>CN Details</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No damage records found.</td></tr>
                    <?php endif; 
 foreach($reports as $row): ?>
                    <tr>
                        <td class="ps-3 small"><?= date('d/m/Y', strtotime($row['reported_at'])) ?></td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($row['product_name'] ?? '') ?></div>
                            <span class="badge bg-light text-dark border" style="font-size: 0.7rem;">Batch: <?= htmlspecialchars($row['batch_no'] ?? '') ?></span>
                        </td>
                        <td class="text-danger fw-bold"><?= number_format($row['qty']) ?> <small>pcs</small></td>
                        <td>
                            <div class="thumb-gallery">
                                <?php if($row['photo_path']): 
                                    $photos = explode(',', $row['photo_path']);
                                    foreach($photos as $p): ?>
                                    <a href="uploads/spoilage/<?= trim($p) ?>" target="_blank">
                                        <img src="uploads/spoilage/<?= trim($p) ?>" alt="Evidence">
                                    </a>
                                <?php endforeach; else: ?>
                                    <span class="text-muted small italic">Cleared</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php 
                                $status = $row['claim_status'] ?? 'Pending';
                                $color = $status == 'Approved' ? 'success' : ($status == 'Rejected' ? 'danger' : 'warning text-dark');
                            ?>
                            <span class="badge bg-<?= $color ?> badge-status"><?= $status ?></span>
                        </td>
                        <td class="small">
                            <?= $row['supplier_submitted_at'] ? date('d/m/Y', strtotime($row['supplier_submitted_at'])) : '<span class="text-muted">Not Yet</span>' ?>
                        </td>
                        <td class="small">
                            <?php if($row['cn_number']): ?>
                                <div class="fw-bold">#<?= htmlspecialchars($row['cn_number'] ?? '') ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?= date('d/m/Y', strtotime($row['cn_date'])) ?></div>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-primary btn-sm px-3" onclick="manageClaim(<?= htmlspecialchars(json_encode($row)) ?>)">Manage</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Logic to trigger the physical deletion of evidence
function runCleanup() {
    Swal.fire({
        title: 'Run Photo Cleanup?',
        text: "This will permanently delete photos for all 'Approved' reports with a 'CN Number'. This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#6c757d',
        confirmButtonText: 'Confirm Cleanup'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            
            fetch('api/cleanup_photos.php')
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        Swal.fire('Storage Cleared', data.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(() => Swal.fire('Error', 'Could not reach cleanup script.', 'error'));
        }
    });
}

async function manageClaim(row) {
    const { value: formValues } = await Swal.fire({
        title: 'Update Supplier Claim',
        html: `
            <div class="text-start">
                <label class="form-label small fw-bold">Claim Status</label>
                <select id="swal-status" class="form-select mb-3">
                    <option value="Pending" ${row.claim_status == 'Pending' ? 'selected' : ''}>Pending</option>
                    <option value="Approved" ${row.claim_status == 'Approved' ? 'selected' : ''}>Approved</option>
                    <option value="Rejected" ${row.claim_status == 'Rejected' ? 'selected' : ''}>Rejected</option>
                </select>
                <label class="form-label small fw-bold">Date Submitted to Supplier</label>
                <input type="date" id="swal-submit-date" class="form-control mb-3" value="${row.supplier_submitted_at || ''}">
                <hr>
                <label class="form-label small fw-bold">CN Number</label>
                <input type="text" id="swal-cn-num" class="form-control mb-3" placeholder="e.g. CN2026-001" value="${row.cn_number || ''}">
                <label class="form-label small fw-bold">CN Date</label>
                <input type="date" id="swal-cn-date" class="form-control" value="${row.cn_date || ''}">
            </div>`,
        showCancelButton: true,
        confirmButtonText: 'Save Changes',
        preConfirm: () => {
            return {
                id: row.id,
                status: document.getElementById('swal-status').value,
                submit_date: document.getElementById('swal-submit-date').value,
                cn_num: document.getElementById('swal-cn-num').value,
                cn_date: document.getElementById('swal-cn-date').value
            }
        }
    });

    if (formValues) {
        fetch('api/update_spoilage_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(formValues)
        })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') location.reload();
        });
    }
}
</script>
<?php require_once 'includes/footer.php'; ?>