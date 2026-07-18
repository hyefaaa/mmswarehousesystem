<?php
// stock_take.php
// PHYSICAL STOCK TAKE MODULE
// Allows staff to count actual stock and adjust system records.

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';

// Fetch Current Stock by Batch
// We group by Category -> Product -> Batch for clarity
$sql = "
    SELECT b.id as batch_id, p.name, p.category, p.uom, b.batch_no, b.expiry_date, b.qty_on_hand, b.pallet_type
    FROM inventory_batches b
    JOIN products p ON b.product_id = p.id
    WHERE b.qty_on_hand != 0 -- Optional: Show 0 qty items if you want to confirm they are gone?
    ORDER BY p.category, p.name, b.expiry_date ASC
";
$stock_items = $pdo->query($sql)->fetchAll();

$current_cat = '';


$page_title = 'Physical Stock Take';
require_once 'includes/header.php';
?>


<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center flex-column flex-md-row gap-3">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-calendar-check me-2"></i>Physical Stock Take</h1>
                <p class="opacity-75 mb-0 fw-light">Verify actual physical stock and register adjustments</p>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> Dashboard</a>

                <button class="btn btn-success fw-bold px-4" onclick="submitStockTake()"><i class="bi bi-cloud-check-fill me-1"></i> Save Adjustments</button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    
    <form action="api/save_stock_take.php" method="post" id="stockTakeForm" class="card main-card border-0">
        <div class="alert alert-info border-0 shadow-sm mb-4">
            <strong>Instructions:</strong> Enter the <b>Actual Physical Quantity</b> in the input boxes. 
            The system will automatically calculate the variance. Only modified rows will be saved.
        </div>
        
        <div class="card border-0 mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th width="25%">Product Name</th>
                                <th width="10%">Batch No</th>
                                <th width="10%">Expiry</th>
                                <th width="10%">System Qty</th>
                                <th width="12%">Physical Qty</th>
                                <th width="10%">Variance</th>
                                <th width="23%">Reason / Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($stock_items)): ?>
                                <tr><td colspan="7" class="text-center p-4">No active stock found.</td></tr>
                            <?php else: 
 foreach ($stock_items as $item): 
 
                                        // Group Header logic
                                        if ($current_cat != $item['category']): 
                                            $current_cat = $item['category'];
                                    ?>
                                        <tr class="bg-secondary bg-opacity-10 fw-bold">
                                            <td colspan="7" class="text-uppercase text-secondary ps-3">
                                                📁 <?= htmlspecialchars($current_cat ?? '') ?> SECTION
                                            </td>
                                        </tr>
                                    <?php endif; ?>

                                    <tr data-batch-id="<?= $item['batch_id'] ?>">
                                        <td>
                                            <?= htmlspecialchars($item['name'] ?? '') ?>
                                            <small class="text-muted d-block"><?= $item['uom'] ?></small>
                                        </td>
                                        <td class="fw-bold text-primary"><?= htmlspecialchars($item['batch_no'] ?? '') ?></td>
                                        <td><?= $item['expiry_date'] ?></td>
                                        <td class="text-center bg-light">
                                            <span id="sys_<?= $item['batch_id'] ?>" class="fw-bold"><?= $item['qty_on_hand'] ?></span>
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   name="adjustments[<?= $item['batch_id'] ?>][actual_qty]" 
                                                   id="act_<?= $item['batch_id'] ?>" 
                                                   class="form-control form-control-sm fw-bold border-primary text-center" 
                                                   placeholder="<?= $item['qty_on_hand'] ?>"
                                                   oninput="calcVariance(<?= $item['batch_id'] ?>)">
                                        </td>
                                        <td class="text-center">
                                            <span id="var_<?= $item['batch_id'] ?>">-</span>
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="adjustments[<?= $item['batch_id'] ?>][reason]" 
                                                   class="form-control form-control-sm" 
                                                   placeholder="Reason (if mismatch)...">
                                            <!-- Hidden Inputs for Original Data -->
                                            <input type="hidden" name="adjustments[<?= $item['batch_id'] ?>][system_qty]" value="<?= $item['qty_on_hand'] ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; 
 endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="d-grid mt-4 mb-5">
            <button type="submit" class="btn btn-success btn-lg py-3 fw-bold">✅ CONFIRM & UPDATE STOCK</button>
        </div>
    </form>
</div>

<script>
    function calcVariance(id) {
        const sysQty = parseInt(document.getElementById('sys_' + id).innerText) || 0;
        const actInput = document.getElementById('act_' + id);
        const varDisplay = document.getElementById('var_' + id);

        if (actInput.value === '') {
            varDisplay.innerText = '-';
            varDisplay.className = '';
            return;
        }

        const actQty = parseInt(actInput.value) || 0;
        const diff = actQty - sysQty;

        if (diff > 0) {
            varDisplay.innerText = '+' + diff;
            varDisplay.className = 'text-success fw-bold';
        } else if (diff < 0) {
            varDisplay.innerText = diff;
            varDisplay.className = 'text-danger fw-bold';
        } else {
            varDisplay.innerText = 'OK';
            varDisplay.className = 'text-success fw-bold';
        }
    }

    function submitStockTake() {
        document.getElementById('stockTakeForm').submit();
    }
</script>

<?php require_once 'includes/footer.php'; ?>