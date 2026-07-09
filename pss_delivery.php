<?php
// pss_delivery.php - MMS UPGRADED INTERFACE
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = $_SESSION['role'] ?? '';
$hd_id_sess = $_SESSION['hd_id'] ?? null;

$co_list = $pdo->query("
    SELECT DISTINCT co_number FROM (
        SELECT DISTINCT co_number FROM schools WHERE co_number IS NOT NULL
        UNION
        SELECT DISTINCT co_number FROM import_cos WHERE co_number IS NOT NULL
    ) AS combined ORDER BY co_number DESC
")->fetchAll();
$selected_co = $_GET['co'] ?? ($co_list[0]['co_number'] ?? '');

// Check if there are imported transactions in import_cos for selected CO
$stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM import_cos WHERE co_number = ?");
$stmtCheck->execute([$selected_co]);
$has_imported_transactions = $stmtCheck->fetchColumn() > 0;

if ($has_imported_transactions) {
    if ($role === 'dealer') {
        $sql = "SELECT s.id, s.school_name, s.school_code, t.bil_murid AS student_count, 
                       s.default_hd_id, ? AS co_number, t.no_sap AS sap_no, 
                       s.tender_no, s.contract_no, s.zone_code, h.name as hd_name 
                FROM import_transactions t
                JOIN import_cos co ON t.batch_id = co.batch_id
                JOIN schools s ON t.kod_sekolah = s.school_code
                LEFT JOIN hds h ON s.default_hd_id = h.id
                WHERE co.co_number = ? AND s.default_hd_id = ? 
                ORDER BY s.school_name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$selected_co, $selected_co, $hd_id_sess]);
    } else {
        $sql = "SELECT s.id, s.school_name, s.school_code, t.bil_murid AS student_count, 
                       s.default_hd_id, ? AS co_number, t.no_sap AS sap_no, 
                       s.tender_no, s.contract_no, s.zone_code, h.name as hd_name 
                FROM import_transactions t
                JOIN import_cos co ON t.batch_id = co.batch_id
                JOIN schools s ON t.kod_sekolah = s.school_code
                LEFT JOIN hds h ON s.default_hd_id = h.id
                WHERE co.co_number = ? 
                ORDER BY s.school_name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$selected_co, $selected_co]);
    }
} else {
    if ($role === 'dealer') {
        $stmt = $pdo->prepare("SELECT s.id, s.school_name, s.school_code, s.student_count, s.default_hd_id, s.co_number, s.sap_no, s.tender_no, s.contract_no, s.zone_code, h.name as hd_name FROM schools s LEFT JOIN hds h ON s.default_hd_id = h.id WHERE s.co_number = ? AND s.default_hd_id = ? ORDER BY s.school_name ASC");
        $stmt->execute([$selected_co, $hd_id_sess]);
    } else {
        $stmt = $pdo->prepare("SELECT s.id, s.school_name, s.school_code, s.student_count, s.default_hd_id, s.co_number, s.sap_no, s.tender_no, s.contract_no, s.zone_code, h.name as hd_name FROM schools s LEFT JOIN hds h ON s.default_hd_id = h.id WHERE s.co_number = ? ORDER BY s.school_name ASC");
        $stmt->execute([$selected_co]);
    }
}
$schools = $stmt->fetchAll();

// DASHBOARD DATA
$stats_contract = [
    'total_schools' => count($schools),
    'total_students' => array_sum(array_column($schools, 'student_count'))
];

$stmt = $pdo->query("SELECT SUM(b.qty_on_hand) as total_stock FROM inventory_batches b JOIN products p ON b.product_id = p.id WHERE p.category = 'PSS' AND b.location_status = 'Warehouse'");
$stats_stock = $stmt->fetch();

if ($role === 'dealer') {
    $stmt = $pdo->prepare("SELECT d.do_number, d.delivery_date, d.vehicle_plate, s.school_name FROM deliveries_pss d LEFT JOIN schools s ON d.school_id = s.id WHERE d.hd_id = ? ORDER BY d.created_at DESC LIMIT 10");
    $stmt->execute([$hd_id_sess]);
} else {
    $stmt = $pdo->prepare("SELECT d.do_number, d.delivery_date, d.vehicle_plate, s.school_name FROM deliveries_pss d LEFT JOIN schools s ON d.school_id = s.id ORDER BY d.created_at DESC LIMIT 10");
    $stmt->execute();
}
$recent_deliveries = $stmt->fetchAll();

// FORM DATA
$hds = $pdo->query("SELECT id, name FROM hds WHERE status='Active' ORDER BY name ASC")->fetchAll();

$ppd_list = array_filter(array_unique(array_column($schools, 'zone_code')));
sort($ppd_list);

$batches = $pdo->query("SELECT b.id, b.batch_no, b.expiry_date, b.qty_on_hand FROM inventory_batches b JOIN products p ON b.product_id = p.id WHERE p.category = 'PSS' AND b.qty_on_hand > 0 AND b.location_status = 'Warehouse' ORDER BY b.expiry_date ASC")->fetchAll();

$page_title = 'MMS | PSS Management Hub';
require_once 'includes/header.php';
?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    :root {
        --mms-blue: #0d47a1; /* Biru Korporat MMS */
        --mms-light-blue: #e3f2fd;
        --mms-accent: #ffc107; /* Kuning Aksen */
    }
    
    /* Navbar style Header */
    .mms-header-sub { background-color: white; border-bottom: 3px solid var(--mms-blue); padding: 1rem 0; margin-bottom: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-top: -1.5rem; }
    
    /* Card Styling */
    .card { border: none; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .card-header { background-color: var(--mms-blue) !important; color: white; border-radius: 10px 10px 0 0 !important; font-weight: 600; }
    
    /* Dashboard Stats */
    .dash-card { border-left: 5px solid var(--mms-blue); }
    .stat-label { font-size: 0.75rem; text-transform: uppercase; color: #6c757d; font-weight: bold; }
    .stat-value { font-size: 1.5rem; font-weight: 800; color: var(--mms-blue); }

    /* Form Styling */
    .form-label { font-weight: 600; color: #495057; font-size: 0.85rem; }
    .read-only-field { background-color: var(--mms-light-blue); border-color: #bbdefb; font-weight: bold; color: #0d47a1; }
    
    /* Picking Box - High Contrast */
    .picking-box { background-color: #212529; color: var(--mms-accent); border-radius: 8px; padding: 1.5rem; font-family: 'Courier New', monospace; font-size: 1.4rem; text-align: center; border: 2px solid var(--mms-blue); }
    
    /* HD Summary */
    .hd-summary-box { background-color: #fff9c4; border: 1px dashed var(--mms-accent); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
    
    .btn-mms { background-color: var(--mms-blue); color: white; border: none; transition: 0.3s; }
    .btn-mms:hover { background-color: #0a3d8d; color: white; transform: translateY(-2px); }
    
    .scrolled-table-container { max-height: 400px; overflow-y: auto; }
    .select2-container .select2-selection--single { height: 38px; border-color: #dee2e6; }
</style>

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center flex-column flex-md-row gap-3">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-truck me-2"></i>PSS Delivery Hub</h1>
                <p class="opacity-75 mb-0 fw-light">Susu Sekolah (PSS) Management Hub</p>
            </div>
            <div class="d-flex gap-3 align-items-center">
                <form method="GET" class="d-flex align-items-center bg-white bg-opacity-10 p-1.5 rounded-3 border border-white border-opacity-20 text-white">
                    <small class="px-2 fw-bold text-uppercase small" style="letter-spacing: 0.5px;">Cycle:</small>
                    <select name="co" class="form-select form-select-sm border-0 bg-transparent text-white fw-bold cursor-pointer" onchange="this.form.submit()" style="background-color: transparent; outline: none; box-shadow: none;">
                        <?php foreach($co_list as $co): ?>
                            <option value="<?= $co['co_number'] ?>" <?= $selected_co == $co['co_number'] ? 'selected' : '' ?> class="text-dark">
                                <?= htmlspecialchars($co['co_number']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> Dashboard</a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4">
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card dash-card h-100">
                <div class="card-body">
                    <div class="stat-label">Stok Semasa</div>
                    <div class="stat-value text-success"><?= number_format($stats_stock['total_stock'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dash-card h-100">
                <div class="card-body">
                    <div class="stat-label">Jumlah Sekolah (<?= $selected_co ?>)</div>
                    <div class="stat-value"><?= number_format($stats_contract['total_schools'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dash-card h-100">
                <div class="card-body">
                    <div class="stat-label">Jumlah Murid</div>
                    <div class="stat-value"><?= number_format($stats_contract['total_students'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-dark text-white h-100">
                <div class="card-body">
                    <div class="stat-label text-light opacity-75">Keperluan Unit (Pcs)</div>
                    <div class="stat-value text-warning"><?= number_format(($stats_contract['total_students'] ?? 0) * 44) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <form method="POST" action="api/save_delivery.php">
                <input type="hidden" name="co_number" value="<?= htmlspecialchars($selected_co) ?>">
                
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between">
                        <span>📝 PENYEDIAAN DELIVERY ORDER (DO)</span>
                        <span class="badge bg-warning text-dark"><?= date('d-m-Y') ?></span>
                    </div>
                    <div class="card-body">
                        
                        <div class="row g-3 mb-4 bg-light p-3 rounded border" <?php if ($role === 'dealer') echo 'style="display:none;"'; ?>>
                            <div class="col-md-6">
                                <label class="form-label">Tapis PPD</label>
                                <select id="ppd_filter" class="form-select select2" onchange="filterSchools()">
                                    <option value="all">Semua PPD</option>
                                    <?php foreach($ppd_list as $ppd): ?>
                                        <option value="<?= htmlspecialchars(strtoupper($ppd)) ?>"><?= htmlspecialchars(strtoupper($ppd)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tapis HD/Kontraktor</label>
                                <select name="hd_id" id="hd_filter" class="form-select select2" onchange="filterSchools()">
                                    <?php if ($role !== 'dealer'): ?>
                                        <option value="all">Semua HD</option>
                                    <?php endif; ?>
                                    <?php foreach($hds as $hd): ?>
                                        <option value="<?= $hd['id'] ?>" <?= ($role === 'dealer' && $hd_id_sess == $hd['id']) ? 'selected' : '' ?>><?= htmlspecialchars($hd['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="hd-summary-box" id="hd_stats_panel">
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <small class="stat-label">Sekolah</small><br>
                                    <span class="fw-bold" id="hd_total_schools">0</span>
                                </div>
                                <div class="col-md-4 border-start">
                                    <small class="stat-label">Murid</small><br>
                                    <span class="fw-bold" id="hd_total_students">0</span>
                                </div>
                                <div class="col-md-4 border-start">
                                    <small class="stat-label">Jumlah Keperluan</small><br>
                                    <span class="fw-bold text-primary" id="hd_total_packs">0</span> <small>Pcs</small>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Pilih Sekolah</label>
                                <select name="school_id" class="form-select select2" id="school_select" onchange="updateQuota()">
                                    <option value="">-- Sila Pilih --</option>
                                    <?php foreach($schools as $s): ?>
                                        <option value="<?= $s['id'] ?>" 
                                                data-hd="<?= $s['default_hd_id'] ?>"
                                                data-hd-name="<?= htmlspecialchars($s['hd_name'] ?? 'Tiada HD') ?>"
                                                data-ppd="<?= htmlspecialchars(strtoupper($s['zone_code'] ?? '')) ?>"
                                                data-students="<?= $s['student_count'] ?>"
                                                data-sap="<?= htmlspecialchars($s['sap_no'] ?? '') ?>">
                                            <?= htmlspecialchars($s['school_code']) ?> - <?= htmlspecialchars($s['school_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">HD Bertanggungjawab</label>
                                <input type="text" id="disp_hd_name" class="form-control read-only-field" readonly>
                            </div>
                        </div>

                        <div class="row g-2 mb-4">
                            <div class="col-md-2">
                                <label class="form-label">Murid</label>
                                <input type="text" id="disp_students" class="form-control read-only-field text-center" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">SAP No</label>
                                <input type="text" id="disp_sap" class="form-control read-only-field text-center" readonly>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label text-primary">TP(1)</label>
                                <input type="number" id="bil_tp" class="form-control fw-bold text-center border-primary" value="44" oninput="updateQuota()"> 
                            </div>
                            <div class="col-md-1">
                                <label class="form-label text-success">TP(2)</label>
                                <input type="number" id="bil_tp_2" class="form-control fw-bold text-center border-success" value="0" oninput="updateQuota()"> 
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Jumlah Pesanan (Pcs)</label>
                                <input type="text" id="calc_packs_small" class="form-control read-only-field text-center" style="font-size: 1.1rem; border: 2px solid var(--mms-blue);" readonly>
                            </div>
                        </div>

                        <div class="picking-box shadow-sm mb-4">
                            <div class="small opacity-75 mb-2" style="font-size: 0.8rem; font-family: sans-serif; color: white;">ARAHAN PICKING (WAREHOUSE)</div>
                            <span id="pick_plt">0</span> <small>PLT</small> &nbsp;|&nbsp; 
                            <span id="pick_ctn">0</span> <small>CTN</small> &nbsp;|&nbsp; 
                            <span id="pick_pcs">0</span> <small>PCS</small>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-7">
                                <label class="form-label fw-bold text-primary"><i class="bi bi-layer-backward me-1"></i> Pilih Batch (FEFO Lalai)</label>
                                <select name="inventory_batch_id" class="form-select select2" required>
                                    <?php if (empty($batches)): ?>
                                        <option value="" disabled selected>⚠️ Tiada Stok PSS Tersedia</option>
                                    <?php else: ?>
                                        <?php foreach($batches as $b): ?>
                                             <option value="<?= $b['id'] ?>">
                                                 Batch: <?= htmlspecialchars($b['batch_no'] ?: 'Tiada Kod') ?> | Baki: <?= $b['qty_on_hand'] ?> ctn | Luput: <?= $b['expiry_date'] ? date('d/m/y', strtotime($b['expiry_date'])) : 'Tiada Tarikh' ?>
                                             </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold text-danger"><i class="bi bi-truck me-1"></i> No. Plat Kenderaan</label>
                                <input type="text" name="vehicle_plate" class="form-control text-uppercase fw-bold border-danger-subtle" placeholder="e.g. VDU 7677" required>
                            </div>
                        </div>

                        <input type="hidden" name="qty" id="real_qty_cartons">
                        <input type="hidden" name="delivery_date" value="<?= date('Y-m-d') ?>">

                        <div class="d-grid">
                            <button type="submit" class="btn btn-mms btn-lg fw-bold py-3 shadow">SAHKAN & CETAK DO</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4 border-0" id="hd_school_list_card" style="display:none;">
                <div class="card-header bg-info d-flex justify-content-between align-items-center">
                    <span>🏫 SENARAI SEKOLAH</span>
                    <span class="badge bg-white text-dark" id="hd_school_count_badge">0</span>
                </div>
                <div class="card-body p-0 scrolled-table-container border">
                    <table class="table table-sm table-hover mb-0" style="font-size: 0.85rem;">
                        <thead class="bg-light sticky-top">
                            <tr>
                                <th class="ps-3">Kod</th>
                                <th>Nama Sekolah</th>
                                <th class="text-end pe-3">Murid</th>
                            </tr>
                        </thead>
                        <tbody id="hd_school_table_body"></tbody>
                    </table>
                </div>
            </div>

            <div class="card border-0">
                <div class="card-header bg-secondary text-white">🕒 REKOD TERKINI</div>
                <div class="card-body p-0 border">
                    <table class="table table-sm mb-0" style="font-size: 0.8rem;">
                        <tbody>
                            <?php foreach($recent_deliveries as $row): ?>
                            <tr>
                                <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($row['do_number']) ?></td>
                                <td class="text-muted"><?= date('d/m', strtotime($row['delivery_date'])) ?></td>
                                <td class="text-truncate" style="max-width: 150px;"><?= htmlspecialchars($row['school_name']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    const schoolsData = <?= json_encode($schools); ?>;
    
    $(document).ready(function() {
        $('.select2').select2({ width: '100%' });
        filterSchools(); 
    });

    function filterSchools() {
        let selectedPPD = $('#ppd_filter').val(); 
        let selectedHD = $('#hd_filter').val();   
        let $schoolSelect = $('#school_select');
        let hdTotalSchools = 0, hdTotalStudents = 0;
        
        $schoolSelect.find('option').each(function() {
            let val = $(this).val();
            if (val === "") return; 
            let hdId = $(this).data('hd'), schoolPPD = ($(this).data('ppd') || '').toUpperCase();
            let matchPPD = (selectedPPD === 'all') || (schoolPPD === selectedPPD);
            let matchHD = (selectedHD === 'all') || (hdId == selectedHD);

            if (matchPPD && matchHD) {
                $(this).prop('disabled', false);
                hdTotalSchools++;
                hdTotalStudents += parseInt($(this).data('students')) || 0;
            } else {
                $(this).prop('disabled', true);
            }
        });

        if ($schoolSelect.find(':selected').prop('disabled')) { $schoolSelect.val('').trigger('change'); } 
        else { $schoolSelect.trigger('change.select2'); }

        let totalTp = (parseInt($('#bil_tp').val()) || 0) + (parseInt($('#bil_tp_2').val()) || 0);
        $('#hd_total_schools').text(hdTotalSchools);
        $('#hd_total_students').text(hdTotalStudents.toLocaleString());
        $('#hd_total_packs').text((hdTotalStudents * totalTp).toLocaleString());

        updateSidebarTable(selectedPPD, selectedHD);
    }

    function updateSidebarTable(selectedPPD, selectedHD) {
        let $tableBody = $('#hd_school_table_body'), $card = $('#hd_school_list_card'), count = 0;
        $tableBody.empty(); 
        if (selectedHD === 'all' && selectedPPD === 'all') { $card.hide(); return; }

        schoolsData.forEach(function(s) {
            let matchPPD = (selectedPPD === 'all') || ((s.zone_code || '').toUpperCase() === selectedPPD);
            let matchHD = (selectedHD === 'all') || (s.default_hd_id == selectedHD);
            if (matchPPD && matchHD) {
                count++;
                $tableBody.append(`<tr><td class="ps-3 text-muted">${s.school_code}</td><td class="text-truncate" style="max-width:150px;">${s.school_name}</td><td class="text-end pe-3 fw-bold">${s.student_count}</td></tr>`);
            }
        });
        $('#hd_school_count_badge').text(count);
        $card.toggle(count > 0);
    }

    function updateQuota() {
        let selected = $('#school_select').find(':selected');
        if (!selected.val()) {
             $('.read-only-field').val('');
             $('#pick_plt, #pick_ctn, #pick_pcs').text('0');
             return;
        }

        let studentCount = parseInt(selected.data('students')) || 0;
        $('#disp_students').val(studentCount);
        $('#disp_sap').val(selected.data('sap') || '-');
        $('#disp_hd_name').val(selected.data('hd-name'));

        let totalPacks = studentCount * ((parseInt($('#bil_tp').val()) || 0) + (parseInt($('#bil_tp_2').val()) || 0));
        $('#calc_packs_small').val(totalPacks.toLocaleString() + " PCS");
        
        // Logic Packing
        let plt = Math.floor(totalPacks / 3456); // 24 * 144
        let rem = totalPacks % 3456;
        let ctn = Math.floor(rem / 24);
        let pcs = rem % 24;

        $('#pick_plt').text(plt); $('#pick_ctn').text(ctn); $('#pick_pcs').text(pcs);
        $('#real_qty_cartons').val(totalPacks / 24);
    }
</script>
<?php require_once 'includes/footer.php'; ?>