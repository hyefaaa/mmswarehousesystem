<?php
// system_logs.php
// Portal Paparan Log Audit & Aktiviti Sistem (Khas untuk Admin)

require_once 'config/db.php';

// Memastikan sesi dimulakan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sekatan peranan: Hanya Admin sahaja boleh melihat log audit
$role = $_SESSION['role'] ?? '';
if ($role !== 'admin') {
    http_response_code(403);
    $page_title = 'Akses Dihalang';
    require_once 'includes/header.php';
    echo '<div class="container-fluid px-4 py-5 text-center">
            <div class="card shadow-sm mx-auto p-5" style="max-width: 500px; border-radius: 16px;">
                <h1 style="color: #e74c3c;" class="display-4"><i class="bi bi-shield-slash-fill"></i></h1>
                <h3 class="fw-bold mt-3">Akses Dihalang!</h3>
                <p class="text-muted">Anda tidak mempunyai kebenaran untuk melihat halaman log audit sistem ini.</p>
                <a href="index.php" class="btn btn-primary mt-3 py-2 px-4" style="background: #0f172a; border: none;">Kembali ke Dashboard</a>
            </div>
          </div>';
    require_once 'includes/footer.php';
    exit;
}

$error = '';
$logs = [];

try {
    // Ambil log sistem dari jadual system_logs
    $query = "SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 500";
    $logs = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Gagal memuatkan log sistem: ' . $e->getMessage();
}

$page_title = 'Audit Logs | Moo Moo Supplies';
require_once 'includes/header.php';
?>

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-shield-fill-check me-2"></i>System Audit Trail</h1>
                <p class="opacity-75 mb-0 fw-light">Historical records of all user and system operations</p>
            </div>
            <a href="index.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
        </div>
    </div>
</div>

<div class="container-fluid px-4">
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error ?? '') ?>
        </div>
    <?php endif; ?>

    <div class="card main-card border-0 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-800 text-navy mb-0"><i class="bi bi-journal-text me-2"></i>Log Aktiviti Gudang</h5>
            <span class="badge bg-secondary px-3 py-2 rounded-pill"><?= count($logs) ?> Log Dikesan</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="logsTable">
                <thead>
                    <tr class="text-secondary small fw-bold">
                        <th>Tarikh & Masa</th>
                        <th>Pengguna</th>
                        <th>Tindakan (Action)</th>
                        <th>Jadual Sasaran</th>
                        <th class="text-center">ID Rekod</th>
                        <th>Perincian Aktiviti</th>
                        <th class="text-center">Alamat IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-clock-history fs-1 mb-2"></i>
                                <p class="mb-0 fw-bold">Tiada data log sistem ditemui.</p>
                            </td>
                        </tr>
                    <?php else: 
 foreach($logs as $row): 
                            $badge_color = 'bg-secondary';
                            $action = $row['action'];
                            if (stripos($action, 'created') !== false || stripos($action, 'received') !== false) {
                                $badge_color = 'bg-success-subtle text-success';
                            } elseif (stripos($action, 'login') !== false || stripos($action, 'logged in') !== false) {
                                $badge_color = 'bg-info-subtle text-info';
                            } elseif (stripos($action, 'delete') !== false || stripos($action, 'remove') !== false) {
                                $badge_color = 'bg-danger-subtle text-danger';
                            } elseif (stripos($action, 'outbound') !== false) {
                                $badge_color = 'bg-primary-subtle text-primary';
                            }
                        ?>
                        <tr style="font-size: 0.85rem;">
                            <td class="text-muted fw-semibold"><?= date('d/m/Y H:i:s', strtotime($row['created_at'])) ?></td>
                            <td>
                                <span class="fw-bold text-dark"><i class="bi bi-person me-1"></i><?= htmlspecialchars($row['username'] ?? '') ?></span>
                            </td>
                            <td>
                                <span class="badge <?= $badge_color ?> rounded-pill px-2.5 py-1.5 fw-bold" style="font-size: 0.7rem;">
                                    <?= htmlspecialchars($row['action'] ?? '') ?>
                                </span>
                            </td>
                            <td><code class="text-secondary"><?= htmlspecialchars($row['target_table'] ?? '' ?: '-') ?></code></td>
                            <td class="text-center fw-bold text-navy"><?= $row['record_id'] ?: '-' ?></td>
                            <td class="fw-medium text-dark"><?= htmlspecialchars($row['details'] ?? '' ?: 'Tiada keterangan') ?></td>
                            <td class="text-center text-muted font-monospace" style="font-size: 0.8rem;"><?= htmlspecialchars($row['ip_address'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; 
 endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<script>
    $(document).ready(function() {
        if ($('#logsTable tbody tr:not(.text-center)').length > 0) {
            $('#logsTable').DataTable({
                "order": [[ 0, "desc" ]],
                "pageLength": 25,
                "lengthMenu": [10, 25, 50, 100],
                "language": {
                    "search": "Cari Log:",
                    "lengthMenu": "Papar _MENU_ baris log",
                    "info": "Memaparkan _START_ hingga _END_ daripada _TOTAL_ log",
                    "infoEmpty": "Tiada entri dipaparkan",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "Seterusnya",
                        "previous": "Sebelumnya"
                    }
                }
            });
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>
