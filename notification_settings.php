<?php
// notification_settings.php
// Configuration dashboard for automated Telegram & Email stock alerts

require_once 'config/db.php';
require_once 'config/settings_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only admin allowed
if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit;
}

$settings = get_all_system_settings();

$enable_notifications  = (int)($settings['enable_notifications'] ?? 0);
$telegram_bot_token    = $settings['telegram_bot_token'] ?? '';
$telegram_chat_id      = $settings['telegram_chat_id'] ?? '';
$email_recipient       = $settings['email_recipient'] ?? '';
$low_stock_threshold   = (int)($settings['low_stock_threshold'] ?? 50);
$near_expiry_threshold = (int)($settings['near_expiry_threshold'] ?? 30);

// Fetch current warnings preview to display on settings load
$low_stock_items = [];
try {
    $low_stock_query = $pdo->prepare("
        SELECT p.name, COALESCE(SUM(b.qty_on_hand), 0) as total_qty
        FROM products p
        LEFT JOIN inventory_batches b ON p.id = b.product_id AND b.location_status = 'Warehouse'
        WHERE p.is_active = 1
        GROUP BY p.id, p.name
        HAVING total_qty < ?
        ORDER BY total_qty ASC
    ");
    $low_stock_query->execute([$low_stock_threshold]);
    $low_stock_items = $low_stock_query->fetchAll();
} catch (Exception $e) {}

$near_expiry_batches = [];
try {
    $expiry_query = $pdo->prepare("
        SELECT p.name as product_name, b.batch_no, b.expiry_date, b.qty_on_hand, DATEDIFF(b.expiry_date, CURDATE()) as days_left
        FROM inventory_batches b
        JOIN products p ON b.product_id = p.id
        WHERE b.qty_on_hand > 0 
          AND b.location_status = 'Warehouse'
          AND b.expiry_date IS NOT NULL 
          AND b.expiry_date != '0000-00-00'
          AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
        ORDER BY b.expiry_date ASC
    ");
    $expiry_query->execute([$near_expiry_threshold]);
    $near_expiry_batches = $expiry_query->fetchAll();
} catch (Exception $e) {}

$page_title = 'Notification Settings | MMS';
require_once 'includes/header.php';
?>

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-bell-fill me-2 text-warning"></i>Notification Settings</h1>
                <p class="opacity-75 mb-0 fw-light">Configure automated Telegram & Email warnings for inventory management</p>
            </div>
            <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> Dashboard</a>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success border-0 shadow-sm mb-4 alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2 text-success"></i>Configuration saved successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Configuration Form Column -->
        <div class="col-lg-7">
            <form action="api/save_notification_settings.php" method="POST" class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-gear-fill me-2"></i>Configure Alerts Channels</h5>
                </div>
                <div class="card-body bg-white p-4">
                    
                    <!-- Toggle Switch -->
                    <div class="form-check form-switch mb-4 p-3 bg-light rounded-3 d-flex align-items-center justify-content-between border">
                        <div class="ms-1">
                            <label class="form-check-label fw-bold d-block mb-1" for="enable_notifications">Enable Automated Alerts</label>
                            <span class="text-muted small">Enable active inventory checks and push notifications</span>
                        </div>
                        <input class="form-check-input fs-4 cursor-pointer" type="checkbox" name="enable_notifications" id="enable_notifications" value="1" <?= $enable_notifications ? 'checked' : '' ?>>
                    </div>

                    <!-- Thresholds Section -->
                    <h6 class="fw-bold border-bottom pb-2 mb-3 text-navy"><i class="bi bi-sliders me-2"></i>Warning Thresholds</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Low Stock Limit (Cartons)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-archive"></i></span>
                                <input type="number" name="low_stock_threshold" class="form-control" value="<?= $low_stock_threshold ?>" min="1" required>
                            </div>
                            <span class="text-muted small" style="font-size: 11px;">Trigger alerts when items in warehouse fall below this value</span>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Expiry Date Threshold (Days)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-calendar-event"></i></span>
                                <input type="number" name="near_expiry_threshold" class="form-control" value="<?= $near_expiry_threshold ?>" min="1" required>
                            </div>
                            <span class="text-muted small" style="font-size: 11px;">Trigger alerts when batches expire within this amount of days</span>
                        </div>
                    </div>

                    <!-- Telegram Settings -->
                    <div class="p-3 border rounded-3 mb-4 bg-light bg-opacity-50">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-primary mb-0"><i class="bi bi-telegram me-2"></i>Telegram Bot Channel</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="testConnection('telegram')"><i class="bi bi-send me-1"></i>Test Bot Connection</button>
                        </div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-bold">Bot Token</label>
                                <input type="text" name="telegram_bot_token" class="form-control font-monospace" placeholder="123456789:ABCdefGh..." value="<?= htmlspecialchars($telegram_bot_token) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Chat ID (Channel or Group)</label>
                                <input type="text" name="telegram_chat_id" class="form-control font-monospace" placeholder="-100123456789" value="<?= htmlspecialchars($telegram_chat_id) ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Email Settings -->
                    <div class="p-3 border rounded-3 mb-4 bg-light bg-opacity-50">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-success mb-0"><i class="bi bi-envelope-fill me-2"></i>Email Target</h6>
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="testConnection('email')"><i class="bi bi-send me-1"></i>Test Email Dispatch</button>
                        </div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-bold">Recipient Email Address</label>
                                <input type="email" name="email_recipient" class="form-control" placeholder="manager@susumurah.com.my" value="<?= htmlspecialchars($email_recipient) ?>">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-dark w-100 py-2.5 fw-bold"><i class="bi bi-save me-2"></i>Save Configuration</button>

                </div>
            </form>
        </div>

        <!-- Preview Column -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-navy text-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-eye-fill me-2"></i>Warnings Preview</h5>
                    <span class="badge bg-danger"><?= count($low_stock_items) + count($near_expiry_batches) ?> active warnings</span>
                </div>
                <div class="card-body bg-white p-4">
                    <p class="text-muted small mb-4">A preview of items currently triggering alerts based on configured thresholds.</p>

                    <!-- Low Stock List -->
                    <h6 class="fw-bold mb-2"><i class="bi bi-archive-fill text-danger me-2"></i>Low Stock items (< <?= $low_stock_threshold ?> ctn)</h6>
                    <?php if (empty($low_stock_items)): ?>
                        <div class="alert alert-light border py-2 text-muted small"><i class="bi bi-check-circle-fill text-success me-2"></i>No products below the limit.</div>
                    <?php else: ?>
                        <div class="list-group mb-4 shadow-sm">
                            <?php foreach ($low_stock_items as $item): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="small fw-semibold text-truncate" style="max-width: 70%;"><?= htmlspecialchars($item['name']) ?></span>
                                    <span class="badge bg-danger-subtle text-danger px-2.5 py-1 rounded"><?= number_format($item['total_qty']) ?> ctn</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Near Expiry List -->
                    <h6 class="fw-bold mb-2"><i class="bi bi-calendar-x-fill text-warning me-2"></i>Near Expiry batches (< <?= $near_expiry_threshold ?> days)</h6>
                    <?php if (empty($near_expiry_batches)): ?>
                        <div class="alert alert-light border py-2 text-muted small"><i class="bi bi-check-circle-fill text-success me-2"></i>No batches expiring soon.</div>
                    <?php else: ?>
                        <div class="list-group shadow-sm">
                            <?php foreach ($near_expiry_batches as $batch): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-start flex-column">
                                    <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                        <span class="small fw-semibold text-truncate" style="max-width: 70%;"><?= htmlspecialchars($batch['product_name']) ?></span>
                                        <span class="badge bg-warning-subtle text-warning-emphasis px-2.5 py-1 rounded"><?= number_format($batch['qty_on_hand']) ?> ctn</span>
                                    </div>
                                    <div class="small text-muted d-flex justify-content-between w-100">
                                        <span>Batch: <?= htmlspecialchars($batch['batch_no']) ?></span>
                                        <span class="text-danger fw-bold">Exp: <?= date('d/m/Y', strtotime($batch['expiry_date'])) ?> (<?= $batch['days_left'] ?> days left)</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cron Trigger Card -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body bg-light">
                    <h6 class="fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Cron Job Automation</h6>
                    <p class="small text-muted mb-2">To schedule alerts daily, set up a server-side Cron Job pointing to this script URL:</p>
                    <div class="p-2.5 bg-dark text-white rounded font-monospace small mb-2" style="font-size: 11px;">
                        curl -s https://wms.susumurah.com.my/cron_check_alerts.php >/dev/null 2>&1
                    </div>
                    <p class="small text-muted mb-0">Alternatively, accessing this file URL directly in a browser triggers immediate checkups and alerts.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Alert Connection Progress -->
<div class="modal fade" id="testAlertModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="fw-bold mb-1">Testing Connection</h5>
                <p class="text-muted small">Sending a test message to your configured alert channel. Please wait...</p>
            </div>
        </div>
    </div>
</div>

<script>
    function testConnection(channel) {
        // Show progress spinner modal
        const testModal = new bootstrap.Modal(document.getElementById('testAlertModal'), {
            backdrop: 'static',
            keyboard: false
        });
        testModal.show();

        fetch(`api/send_test_alert.php?channel=${channel}`)
            .then(res => res.json())
            .then(data => {
                testModal.hide();
                // Add tiny timeout to let backdrop remove properly
                setTimeout(() => {
                    if (data.success) {
                        alert(`✅ Success: Test message dispatched successfully to your ${channel} target!`);
                    } else {
                        alert(`❌ Failed: ${data.message}`);
                    }
                }, 150);
            })
            .catch(err => {
                testModal.hide();
                setTimeout(() => {
                    alert(`❌ Error connecting to test endpoint: ${err.message}`);
                }, 150);
            });
    }
</script>
<?php require_once 'includes/footer.php'; ?>
