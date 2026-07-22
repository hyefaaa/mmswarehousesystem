<?php
// print_prf_slip.php - Printable Official Pallet Recovery Form (PRF) Slip
require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$return_id = (int)($_POST['return_id'] ?? $_GET['id'] ?? 0);
if ($return_id <= 0) {
    die("Invalid Return Record ID.");
}

$stmt = $pdo->prepare("SELECT * FROM pallet_returns WHERE id = ? LIMIT 1");
$stmt->execute([$return_id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    die("Pallet Return Record not found.");
}

$item_stmt = $pdo->prepare("SELECT * FROM pallet_return_items WHERE return_id = ? ORDER BY id ASC");
$item_stmt->execute([$return_id]);
$items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);

$photo_stmt = $pdo->prepare("SELECT * FROM pallet_return_photos WHERE return_id = ? ORDER BY id ASC");
$photo_stmt->execute([$return_id]);
$photos = $photo_stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pallet Recovery Form (PRF) - <?= htmlspecialchars($record['prf_no']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #1e293b; background: #f8fafc; }
        .prf-card { background: white; border: 2px solid #0f172a; border-radius: 12px; max-width: 850px; margin: 20px auto; padding: 30px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
        .table-prf th { background-color: #f1f5f9; color: #0f172a; border: 1px solid #cbd5e1; }
        .table-prf td { border: 1px solid #cbd5e1; }
        .signature-line { border-bottom: 2px solid #0f172a; height: 45px; margin-top: 35px; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .prf-card { border: none; box-shadow: none; margin: 0; padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="container no-print text-center my-3">
    <button onclick="window.print()" class="btn btn-primary btn-lg fw-bold px-4"><i class="bi bi-printer me-2"></i><span data-lang="prf_slip_btn_print">Print PRF Form</span></button>
    <button onclick="window.close()" class="btn btn-outline-secondary btn-lg fw-bold ms-2"><span data-lang="prf_slip_btn_close">Close</span></button>
</div>

<div class="prf-card">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <img src="img/logo.png" alt="MMS Logo" style="height: 55px; width: auto;" onerror="this.style.display='none'">
            <div>
                <h3 class="fw-bold mb-0 text-navy">MOO MOO SUPPLIES SDN BHD</h3>
                <div class="text-muted small" data-lang="prf_slip_title">PALLET RECOVERY FORM (PRF)</div>
            </div>
        </div>
        <div class="text-end">
            <h4 class="fw-extrabold text-danger mb-0">NO: <?= htmlspecialchars($record['prf_no']) ?></h4>
            <div class="badge bg-dark px-3 py-1 mt-1">DATE: <?= date('d/m/Y H:i', strtotime($record['return_date'])) ?></div>
        </div>
    </div>

    <!-- Supplier & Transporter Grid -->
    <div class="row g-3 mb-4">
        <div class="col-6">
            <div class="p-3 bg-light rounded border">
                <span class="text-muted small fw-bold text-uppercase d-block mb-1" data-lang="prf_slip_customer">Customer / Return To Supplier</span>
                <h5 class="fw-bold text-navy mb-0"><?= htmlspecialchars($record['supplier_name']) ?></h5>
            </div>
        </div>
        <div class="col-6">
            <div class="p-3 bg-light rounded border">
                <span class="text-muted small fw-bold text-uppercase d-block mb-1" data-lang="prf_slip_transporter">Transporter & Driver Details</span>
                <div class="fw-bold text-dark"><?= htmlspecialchars($record['transporter_name']) ?></div>
                <small class="text-muted"><span data-lang="prf_slip_driver">Driver Name</span>: <strong><?= htmlspecialchars($record['driver_name']) ?></strong> | <span data-lang="prf_slip_plate">Lorry Plate</span>: <strong><?= htmlspecialchars($record['vehicle_plate']) ?></strong></small>
            </div>
        </div>
    </div>

    <!-- Pallet Items Table matching physical PRF -->
    <h5 class="fw-bold text-navy mb-2"><i class="bi bi-box-seam me-2"></i><span data-lang="prf_slip_items">Pallet Items Breakdown</span></h5>
    <table class="table table-prf align-middle mb-4">
        <thead>
            <tr class="small text-uppercase fw-bold">
                <th style="width: 50%;" data-lang="prf_slip_type">Type of Pallet</th>
                <th style="width: 25%;" class="text-center" data-lang="prf_slip_qty">Quantity</th>
                <th style="width: 25%;" data-lang="prf_slip_cond">Good / Damaged</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($items)): ?>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td class="fw-bold fs-6 text-navy"><?= htmlspecialchars($it['pallet_type']) ?></td>
                        <td class="text-center fw-extrabold fs-5 text-primary"><?= number_format($it['quantity']) ?></td>
                        <td><?= htmlspecialchars($it['pallet_condition']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" class="text-center text-muted py-3" data-lang="prf_slip_no_items">No pallet items listed</td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr class="table-light">
                <td class="text-end fw-extrabold fs-6" data-lang="prf_slip_total">TOTAL PALLETS:</td>
                <td class="text-center fw-extrabold fs-4 text-danger"><?= number_format($record['total_quantity']) ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <?php if (!empty($record['remarks'])): ?>
        <div class="p-3 bg-light border rounded mb-4">
            <strong><span data-lang="prf_slip_remarks">Remarks / Return Notes:</span></strong> <?= htmlspecialchars($record['remarks']) ?>
        </div>
    <?php endif; ?>

    <!-- Photos Evidence Attachment -->
    <?php if (!empty($photos)): ?>
        <h6 class="fw-bold text-navy mb-2"><i class="bi bi-camera me-1"></i><span data-lang="prf_slip_photos">Trailer Stack Proof Photos</span> (<?= count($photos) ?> Stacks)</h6>
        <div class="row g-2 mb-4">
            <?php foreach ($photos as $p): ?>
                <div class="col-3 text-center">
                    <img src="<?= htmlspecialchars($p['photo_path']) ?>" class="img-fluid border rounded" style="height: 100px; width:100%; object-fit: cover;">
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Signatures Row -->
    <div class="row text-center mt-5 pt-3">
        <div class="col-4">
            <div class="signature-line"></div>
            <div class="fw-bold mt-2" data-lang="prf_slip_issued">Issued by Warehouse</div>
            <small class="text-muted"><span data-lang="prf_slip_name">Name:</span> <?= htmlspecialchars($record['created_by']) ?></small>
        </div>
        <div class="col-4">
            <div class="signature-line"></div>
            <div class="fw-bold mt-2" data-lang="prf_slip_received">Received by Driver</div>
            <small class="text-muted"><span data-lang="prf_slip_name">Name:</span> <?= htmlspecialchars($record['driver_name']) ?></small>
        </div>
        <div class="col-4">
            <div class="signature-line"></div>
            <div class="fw-bold mt-2" data-lang="prf_slip_ack">Acknowledged by Supplier</div>
            <small class="text-muted" data-lang="prf_slip_stamp">Name & Stamp</small>
        </div>
    </div>
</div>


<script src="lang/translations.js"></script>
<script>
    // Ensure translation is applied on page load since header.php is not included here
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof MMS_LANG !== 'undefined') {
            MMS_LANG.init();
        }
    });
</script>
</body>
</html>
