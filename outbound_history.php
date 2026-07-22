<?php
// outbound_history.php
// Paparan Senarai Sejarah Penghantaran Bersepadu (Commercial & PSS School)

require_once 'config/db.php';

// Auto-migration for PSS Tables if they do not exist
try {
    // 1. Create hds
    $pdo->exec("CREATE TABLE IF NOT EXISTS `hds` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(100) DEFAULT NULL,
      `short_code` varchar(10) DEFAULT NULL,
      `contact_number` varchar(20) DEFAULT NULL,
      `status` enum('Active','Inactive') DEFAULT 'Active',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    // Seed default hds if empty
    $countHds = $pdo->query("SELECT COUNT(*) FROM hds")->fetchColumn();
    if ($countHds == 0) {
        $pdo->exec("INSERT INTO `hds` (`id`, `name`, `short_code`, `contact_number`, `status`) VALUES
            (2,'MOHD HAFIZI TALIB','FIZI',NULL,'Active'),
            (3,'WALI KHAN','WALI',NULL,'Active'),
            (5,'NOIDORA ABDULLAH','DORA',NULL,'Active'),
            (7,'AHMAD TARMIZI MOHAMED','MMS','01120621990','Active'),
            (8,'SHARIFAH MUNIRAH','SYA',NULL,'Active'),
            (9,'SITI NOOR IDAYU','AYU',NULL,'Active')");
    }

    // 2. Create schools
    $pdo->exec("CREATE TABLE IF NOT EXISTS `schools` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `school_code` varchar(20) NOT NULL,
      `school_name` varchar(255) NOT NULL,
      `zone_code` varchar(100) DEFAULT NULL,
      `default_hd_id` int(11) DEFAULT NULL,
      `student_count` int(11) DEFAULT 0,
      `address` text DEFAULT NULL,
      `no_tel` varchar(50) DEFAULT NULL,
      `co_number` varchar(50) DEFAULT NULL,
      `sap_no` varchar(50) DEFAULT NULL,
      `tender_no` varchar(50) DEFAULT NULL,
      `contract_no` varchar(50) DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `idx_school_code` (`school_code`),
      KEY `fk_school_hd` (`default_hd_id`),
      CONSTRAINT `fk_school_hd` FOREIGN KEY (`default_hd_id`) REFERENCES `hds` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    // 3. Create co_cycles if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `co_cycles` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(50) DEFAULT NULL,
      `start_date` date DEFAULT NULL,
      `end_date` date DEFAULT NULL,
      `is_active` tinyint(4) DEFAULT 1,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    // 4. Create deliveries_pss
    $pdo->exec("CREATE TABLE IF NOT EXISTS `deliveries_pss` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `do_number` varchar(20) DEFAULT NULL,
      `delivery_date` date DEFAULT NULL,
      `hd_id` int(11) DEFAULT NULL,
      `vehicle_plate` varchar(20) DEFAULT NULL,
      `school_id` int(11) DEFAULT NULL,
      `co_cycle_id` int(11) DEFAULT NULL,
      `pallets_out_red` int(11) DEFAULT 0,
      `pallets_out_green` int(11) DEFAULT 0,
      `pallets_out_orange` int(11) DEFAULT 0,
      `status` enum('Draft','Loaded','Delivered','Verified') DEFAULT 'Draft',
      `created_at` timestamp NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    // 5. Create delivery_items_pss
    $pdo->exec("CREATE TABLE IF NOT EXISTS `delivery_items_pss` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `delivery_id` int(11) DEFAULT NULL,
      `inventory_batch_id` int(11) DEFAULT NULL,
      `qty_cartons` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    // 6. Create mms_logistik
    $pdo->exec("CREATE TABLE IF NOT EXISTS `mms_logistik` (
      `id` varchar(50) NOT NULL,
      `name` varchar(255) NOT NULL,
      `district` varchar(50) DEFAULT NULL,
      `date` date NOT NULL,
      `totalCartons` int(11) NOT NULL,
      `extraPacks` int(11) NOT NULL,
      `isDelivered` tinyint(1) DEFAULT 0,
      `isDocSigned` tinyint(1) DEFAULT 0,
      `dealer` varchar(50) DEFAULT 'admin',
      `co_no` varchar(100) DEFAULT NULL,
      `delivery_date` date DEFAULT NULL,
      `plan_date` date DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_dealer` (`dealer`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    // 7. Create vehicles
    $pdo->exec("CREATE TABLE IF NOT EXISTS `vehicles` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `v_name` varchar(50) NOT NULL,
      `v_capacity` int(11) NOT NULL,
      `owner` varchar(50) DEFAULT 'admin',
      `is_enabled` tinyint(1) DEFAULT 1,
      `v_priority` int(11) DEFAULT 1,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

} catch (Exception $e) {
    error_log("PSS auto-migration failed: " . $e->getMessage());
}

$error = '';
$history = [];

try {
    // Gabungkan log Outbound Komersial dan PSS School menggunakan UNION
    $query = "
        (SELECT 
            l.id,
            l.date AS txn_date,
            'Commercial' AS category,
            l.doc_ref AS do_number,
            l.customer AS destination,
            l.vehicle AS vehicle_plate,
            SUM(i.qty) AS total_cartons,
            l.created_at
        FROM outbound_logs l
        LEFT JOIN outbound_items i ON l.id = i.outbound_id
        GROUP BY l.id)
        
        UNION ALL
        
        (SELECT 
            d.id,
            d.delivery_date AS txn_date,
            'PSS School' AS category,
            d.do_number AS do_number,
            s.school_name AS destination,
            d.vehicle_plate AS vehicle_plate,
            SUM(di.qty_cartons) AS total_cartons,
            d.created_at
        FROM deliveries_pss d
        LEFT JOIN schools s ON d.school_id = s.id
        LEFT JOIN delivery_items_pss di ON d.id = di.delivery_id
        GROUP BY d.id)
        
        ORDER BY created_at DESC
    ";
    
    $history = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = 'Gagal memuatkan sejarah penghantaran: ' . $e->getMessage();
}

$page_title = 'Sejarah Outbound | Moo Moo Supplies';
require_once 'includes/header.php';
?>

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-clock-history me-2"></i>Outbound History</h1>
                <p class="opacity-75 mb-0 fw-light">Unified record of Commercial & PSS school shipments</p>
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
            <h5 class="fw-800 text-navy mb-0"><i class="bi bi-card-list me-2"></i>Rekod Penghantaran Keluar</h5>
            <div class="d-flex gap-2">
                <span class="badge bg-primary px-3 py-2 rounded-pill"><?= count($history) ?> Transaksi</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="historyTable">
                <thead>
                    <tr class="text-secondary small fw-bold">
                        <th>Tarikh</th>
                        <th>Kategori</th>
                        <th>No. DO / Rujukan</th>
                        <th>Destinasi</th>
                        <th>No. Plat Lori</th>
                        <th class="text-center">Kuantiti (ctn)</th>
                        <th class="text-center">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr class="no-datatable">
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 mb-2"></i>
                                <p class="mb-0 fw-bold">Tiada rekod penghantaran keluar ditemui.</p>
                            </td>
                        </tr>
                    <?php else: 
 foreach($history as $row): 
                            $badge_class = ($row['category'] === 'Commercial') ? 'bg-primary-subtle text-primary' : 'bg-success-subtle text-success';
                        ?>
                        <tr>
                            <td class="fw-bold text-dark"><?= date('d/m/Y', strtotime($row['txn_date'])) ?></td>
                            <td>
                                <span class="badge <?= $badge_class ?> rounded-pill px-3 py-1.5 font-monospace" style="font-size: 0.72rem; font-weight: 800;">
                                    <?= htmlspecialchars($row['category'] ?? '') ?>
                                </span>
                            </td>
                            <td><code class="fw-bold text-navy"><?= htmlspecialchars($row['do_number'] ?? '' ?: 'TBA') ?></code></td>
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($row['destination'] ?? '' ?: 'Tiada Maklumat') ?></td>
                            <td><span class="badge bg-light text-dark border fw-bold font-monospace"><?= htmlspecialchars($row['vehicle_plate'] ?? '' ?: 'Tiada') ?></span></td>
                            <td class="text-center fw-bold"><?= number_format($row['total_cartons'] ?: 0) ?></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-info btn-sm fw-bold px-3 btn-view-details" 
                                        data-id="<?= $row['id'] ?>" data-category="<?= $row['category'] ?>" data-do="<?= htmlspecialchars($row['do_number'] ?? '') ?>">
                                    <i class="bi bi-eye-fill me-1"></i> Papar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; 
 endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Pop-up Butiran Item DO -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px;">
            <div class="modal-header bg-navy text-white py-3" style="background-color: #0f172a; border-top-left-radius: 18px; border-top-right-radius: 18px;">
                <h6 class="modal-title fw-800" id="modalTitle"><i class="bi bi-box-seam me-2"></i>Butiran Penghantaran Item</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3 d-flex justify-content-between">
                    <span class="small text-muted fw-bold">NO. RUJUKAN DO:</span>
                    <span class="fw-bold text-navy" id="modalDoRef">-</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0" style="font-size: 0.85rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Produk</th>
                                <th>Kod Batch</th>
                                <th class="text-end">Kuantiti (ctn)</th>
                            </tr>
                        </thead>
                        <tbody id="modalItemsBody">
                            <!-- Kandungan dijana JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 py-3" style="border-bottom-left-radius: 18px; border-bottom-right-radius: 18px;">
                <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>


<script>
    $(document).ready(function() {
        // Aktifkan dataTables untuk kemudahan carian/penapis dinamik
        if ($('#historyTable tbody tr:not(.no-datatable)').length > 0) {
            let table = $('#historyTable').DataTable({
                "order": [[ 0, "desc" ]],
                "pageLength": 15,
                "lengthMenu": [10, 15, 25, 50],
                "language": {
                    "search": "Cari Rekod:",
                    "lengthMenu": "Papar _MENU_ rekod",
                    "info": "Memaparkan _START_ hingga _END_ daripada _TOTAL_ entri",
                    "infoEmpty": "Tiada entri dipaparkan",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "Seterusnya",
                        "previous": "Sebelumnya"
                    }
                }
            });
            
            let urlParams = new URLSearchParams(window.location.search);
            let searchVal = urlParams.get('search');
            if (searchVal) {
                table.search(searchVal).draw();
            }
        }

        // Panggilan AJAX untuk memaparkan butiran item
        $('.btn-view-details').on('click', function() {
            let id = $(this).data('id');
            let cat = $(this).data('category');
            let doNum = $(this).data('do');
            
            $('#modalDoRef').text(doNum || 'TBA');
            $('#modalItemsBody').empty().append('<tr><td colspan="3" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> Memuatkan butiran...</td></tr>');
            
            let modal = new bootstrap.Modal(document.getElementById('detailsModal'));
            modal.show();

            fetch(`api/get_outbound_details.php?id=${id}&category=${encodeURIComponent(cat)}`)
            .then(async res => {
                const isJson = res.headers.get('content-type')?.includes('application/json');
                const data = isJson ? await res.json() : await res.text();
                if (!res.ok) {
                    const errorMsg = isJson ? (data.error || res.statusText) : "Server error: " + data.substring(0, 80);
                    throw new Error(errorMsg);
                }
                return data;
            })
            .then(data => {
                $('#modalItemsBody').empty();
                if (data.length === 0) {
                    $('#modalItemsBody').append('<tr><td colspan="3" class="text-center text-muted">Tiada item ditemui.</td></tr>');
                } else {
                    data.forEach(item => {
                        $('#modalItemsBody').append(`
                            <tr>
                                <td class="fw-bold text-dark">${item.product_name}</td>
                                <td><span class="badge bg-light text-dark border">${item.batch_no}</span></td>
                                <td class="text-end fw-bold">${item.qty}</td>
                            </tr>
                        `);
                    });
                }
            })
            .catch(err => {
                $('#modalItemsBody').empty().append(`<tr><td colspan="3" class="text-center text-danger">Gagal memuatkan butiran: ${err.message}</td></tr>`);
                console.error("Gagal mendapatkan butiran DO:", err);
            });
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>
