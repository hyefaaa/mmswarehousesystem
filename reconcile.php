<?php
// reconcile.php - CORPORATE AUDIT INTERFACE
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';

$date = $_GET['date'] ?? date('Y-m-d');

// 1. FETCH COMMERCIAL OUTBOUND TOTAL
// Mengambil jumlah qty dari semua item outbound mengikut tarikh dan kategori
$sql_comm = "
    SELECT SUM(i.qty) as total_qty
    FROM outbound_items i
    JOIN outbound_logs l ON i.outbound_id = l.id
    WHERE l.date = ? AND l.category = 'Commercial'
";
$stmt = $pdo->prepare($sql_comm);
$stmt->execute([$date]);
$comm_sys_qty = $stmt->fetch()['total_qty'] ?? 0;

// 2. FETCH SAVED RECON DATA (Jika audit sudah pernah disimpan sebelum ini)
$sql_recon = "SELECT * FROM daily_reconciliation WHERE date = ? AND category = 'Commercial'";
$stmt = $pdo->prepare($sql_recon);
$stmt->execute([$date]);
$saved = $stmt->fetch();


$page_title = 'Stock Reconciliation | MMS';
require_once 'includes/header.php';
?>

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center flex-column flex-md-row gap-3">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-scale me-2"></i>Stock Reconciliation</h1>
                <p class="opacity-75 mb-0 fw-light">Commercial Audit & Verification Panel</p>
            </div>
            <div class="d-flex gap-3 align-items-center">
                <form class="d-flex gap-2 bg-white bg-opacity-10 p-1.5 rounded-3 border border-white border-opacity-20" method="GET">
                    <input type="date" name="date" class="form-control form-control-sm border-0 bg-transparent text-white fw-bold" value="<?= $date ?>" style="color-scheme: dark;">
                    <button type="submit" class="btn btn-info btn-sm text-white fw-bold px-3">Semak</button>
                </form>
                <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> Dashboard</a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    <form action="api/save_reconciliation.php" method="POST" class="card main-card border-0">
        <input type="hidden" name="date" value="<?= $date ?>">
        <input type="hidden" name="category" value="Commercial">
        <input type="hidden" name="system_qty" id="system_qty" value="<?= $comm_sys_qty ?>">

        <?php 
            $inv_qty = $saved['invoice_qty_cartons'] ?? 0;
            $variance = $comm_sys_qty - $inv_qty;
            $is_match = ($variance == 0);
        ?>

        <div id="status_card" class="card border-0 rounded-4 shadow-sm mb-4">
            <div class="card-body p-0">
                <div class="row g-0">
                    
                    <div class="col-md-4 p-4 text-center bg-light">
                        <div class="text-uppercase fw-bold text-muted mb-3" style="font-size: 0.75rem; letter-spacing: 1px;"><i class="bi bi-cpu me-1"></i> WMS System Records</div>
                        <div class="display-3 fw-bold text-primary mb-2"><?= number_format($comm_sys_qty) ?></div>
                        <div class="text-muted small">Total Cartons Outbound</div>
                    </div>

                    <div class="col-md-4 p-4 text-center border-start border-end">
                        <div class="text-uppercase fw-bold text-dark mb-3" style="font-size: 0.75rem; letter-spacing: 1px;"><i class="bi bi-file-earmark-text me-1"></i> Actual Invoice Qty</div>
                        <input type="number" name="invoice_qty" id="invoice_qty" class="form-control text-center fw-bold bg-light mb-2" 
                               style="font-size: 2.5rem; border: 2px solid #e2e8f0; border-radius: 15px; color: var(--mms-navy);"
                               value="<?= $inv_qty ?>" placeholder="0" oninput="calculateVariance()">
                        <input type="text" name="invoice_nos" class="form-control form-control-sm text-center border-0 bg-transparent" 
                               placeholder="Add Invoice # (Optional)" value="<?= $saved['invoice_numbers'] ?? '' ?>">
                    </div>

                    <div id="result_box" class="col-md-4 p-4 text-center <?= $is_match ? 'bg-success-subtle' : 'bg-danger-subtle' ?>">
                        <div class="text-uppercase fw-bold text-muted mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">Audit Result</div>
                        <div class="bg-white rounded-4 p-4 d-inline-block shadow-sm mb-2" style="min-width: 150px;">
                            <div id="variance_display" class="display-3 fw-bold <?= $is_match ? 'text-success' : 'text-danger' ?>">
                                <?= $variance > 0 ? "+$variance" : $variance ?>
                            </div>
                        </div>
                        <div>
                            <span id="badge_display" class="badge rounded-pill <?= $is_match ? 'bg-success' : 'bg-danger' ?> px-4 py-2 fs-6 shadow-sm">
                                <?= $is_match ? 'VERIFIED MATCH' : 'DISCREPANCY DETECTED' ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-white border-top">
                    <div class="text-uppercase fw-bold text-danger mb-2" style="font-size: 0.75rem; letter-spacing: 1px;">Remarks / Reason for Variance</div>
                    <textarea name="reason" class="form-control border-secondary-subtle" rows="2" 
                              placeholder="Please explain why the system doesn't match the invoice..."><?= $saved['reason'] ?? '' ?></textarea>
                </div>
            </div>

            <div class="card-footer bg-white p-4 text-center border-0">
                <button type="submit" class="btn btn-lg px-5 fw-bold rounded-pill text-white" style="background: var(--mms-navy);">
                    <i class="bi bi-shield-check me-2"></i> SAVE AUDIT VERIFICATION
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function calculateVariance() {
    const sysQty = parseInt(document.getElementById('system_qty').value) || 0;
    const invQty = parseInt(document.getElementById('invoice_qty').value) || 0;
    const variance = sysQty - invQty;
    
    const display = document.getElementById('variance_display');
    const badge = document.getElementById('badge_display');
    const resultBox = document.getElementById('result_box');
    const statusCard = document.getElementById('status_card');

    display.innerText = variance > 0 ? "+" + variance : variance;

    if (variance === 0) {
        display.className = "display-3 fw-bold text-success";
        badge.className = "badge rounded-pill bg-success px-4 py-2 fs-6 shadow-sm";
        badge.innerText = "VERIFIED MATCH";
        resultBox.className = "col-md-4 p-4 text-center bg-success-subtle";
        statusCard.style.borderTopColor = "var(--mms-success)";
    } else {
        display.className = "display-3 fw-bold text-danger";
        badge.className = "badge rounded-pill bg-danger px-4 py-2 fs-6 shadow-sm";
        badge.innerText = "DISCREPANCY DETECTED";
        resultBox.className = "col-md-4 p-4 text-center bg-danger-subtle";
        statusCard.style.borderTopColor = "var(--mms-danger)";
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>