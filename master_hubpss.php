<?php
// master_hubpss.php - Premium Integrated PSS Logistics Dashboard (Old System Restored)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? '';
$full_name = $_SESSION['full_name'] ?? 'Pengguna';

// Switch DB context to read initial stats
try {
    $pdo->exec("USE susumura_mms_logistik");
    
    if ($role === 'dealer') {
        $total_schools   = $pdo->prepare("SELECT COUNT(*) FROM mms_logistik WHERE dealer = ?");
        $total_schools->execute([$username]);
        $total_schools   = $total_schools->fetchColumn() ?: 0;

        $total_delivered = $pdo->prepare("SELECT COUNT(*) FROM mms_logistik WHERE dealer = ? AND isDelivered = 1");
        $total_delivered->execute([$username]);
        $total_delivered = $total_delivered->fetchColumn() ?: 0;

        $total_cartons   = $pdo->prepare("SELECT SUM(totalCartons) FROM mms_logistik WHERE dealer = ?");
        $total_cartons->execute([$username]);
        $total_cartons   = $total_cartons->fetchColumn() ?: 0;
    } else {
        $total_schools   = $pdo->query("SELECT COUNT(*) FROM mms_logistik")->fetchColumn() ?: 0;
        $total_delivered = $pdo->query("SELECT COUNT(*) FROM mms_logistik WHERE isDelivered = 1")->fetchColumn() ?: 0;
        $total_cartons   = $pdo->query("SELECT SUM(totalCartons) FROM mms_logistik")->fetchColumn() ?: 0;
    }

    $progress_percent = ($total_schools > 0) ? round(($total_delivered / $total_schools) * 100) : 0;
} catch (PDOException $e) {
    $total_schools = $total_delivered = $total_cartons = 0;
    $progress_percent = 0;
}

$page_title = 'PSS Master Hub & Logistics | MMS';
require_once 'includes/header.php';
?>
<!-- SheetJS XLSX Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<style>
    :root {
        --mms-navy: #0b2147;
        --mms-cyan: #06b6d4;
        --mms-light: #f8fafc;
        --mms-success: #10b981;
        --mms-warning: #f59e0b;
        --mms-danger: #ef4444;
    }
    
    .container {
        max-width: 1200px;
        margin: 20px auto;
    }

    .overall-progress-container {
        background: #e2e8f0;
        border-radius: 30px;
        height: 34px;
        position: relative;
        overflow: hidden;
        margin: 20px 0;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.06);
    }
    .overall-progress-bar {
        background: linear-gradient(90deg, #10b981, #34d399);
        height: 100%;
        transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        border-radius: 30px;
    }
    .progress-text {
        position: absolute;
        width: 100%;
        text-align: center;
        top: 6px;
        font-weight: 800;
        font-size: 0.9rem;
        color: #0f172a;
        z-index: 2;
        text-shadow: 0 1px 1px rgba(255,255,255,0.6);
    }

    .stock-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stock-card {
        padding: 20px;
        border-radius: 16px;
        background: white;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
        border-left: 6px solid #cbd5e1;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .stock-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
    }

    .setup-grid {
        background: #eff6ff;
        padding: 24px;
        border-radius: 16px;
        margin: 20px 0 30px;
        border: 1px solid #bfdbfe;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
    }

    .day-section {
        margin-top: 30px;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        background: white;
    }
    .day-header {
        background: var(--mms-navy);
        color: white;
        padding: 14px 20px;
        font-weight: 800;
        font-size: 0.95rem;
        letter-spacing: 0.5px;
    }
    .day-summary {
        background: #fffbeb;
        padding: 12px 20px;
        border-bottom: 1px solid #fef3c7;
        color: #d97706;
        font-weight: 700;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .table-wrapper {
        overflow-x: auto;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        padding: 14px 20px;
        border-bottom: 1px solid #f1f5f9;
        text-align: left;
        font-size: 0.9rem;
    }
    th {
        background-color: #f8fafc;
        color: #475569;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.78rem;
        letter-spacing: 0.5px;
    }
    tr:last-child td {
        border-bottom: none;
    }

    .trip-block {
        background: #f0f9ff;
        padding: 20px;
        border-radius: 16px;
        margin-top: 20px;
        border: 1px solid #bae6fd;
        page-break-inside: avoid;
    }
    .van-info {
        margin-bottom: 12px;
        padding: 14px;
        background: white;
        border-radius: 10px;
        border-left: 5px solid #cbd5e1;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .van-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.72rem;
        color: white;
        font-weight: 800;
        text-transform: uppercase;
        margin-bottom: 6px;
    }
    .v-hiace { background: #8b5cf6; }
    .v-granmax { background: #f97316; }
    .v-3rd { background: #10b981; }

    .btn-mms {
        padding: 10px 24px;
        border-radius: 10px;
        font-weight: 700;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-mms:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .v-row {
        display: flex;
        gap: 12px;
        align-items: center;
        margin-bottom: 10px;
        background: #fff;
        padding: 10px 14px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
    }

    .prio-item {
        background: #fff;
        border: 1px solid #e2e8f0;
        padding: 12px 16px;
        margin-bottom: 6px;
        border-radius: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .prio-controls button {
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        width: 28px;
        height: 28px;
        cursor: pointer;
        font-weight: bold;
        transition: all 0.15s;
    }
    .prio-controls button:hover {
        background: #e2e8f0;
    }

    #toast-container {
        position: fixed;
        top: 80px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 10000;
    }
    .toast {
        background: #1e293b;
        color: white;
        padding: 12px 28px;
        border-radius: 30px;
        margin-bottom: 10px;
        font-weight: 700;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        animation: slideDown 0.3s forwards;
    }
    @keyframes slideDown {
        from { transform: translateY(-20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    @media print {
        .no-print { display: none !important; }
        body { background: white; }
        .container { max-width: 100%; width: 100%; margin: 0; padding: 0; }
        .trip-block { border: 1px solid #000; background: white; margin-top: 15px; }
    }
</style>

<div id="toast-container"></div>

<div class="page-header mb-4 no-print">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-truck me-2"></i>PSS Logistics Command</h1>
                <p class="opacity-75 mb-0 fw-light">Susu Sekolah (PSS) Real-time Monitor & Van Trip Dispatch</p>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> Dashboard</a>
            </div>
        </div>
    </div>
</div>

<div class="container px-4 pb-5">
    
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h3 class="fw-bold text-navy mb-0"><i class="bi bi-display me-2 text-primary"></i>Logistics Monitor & Control</h3>
        <span class="badge bg-primary px-3 py-2 rounded-pill text-uppercase" style="letter-spacing: 0.5px;">AKSES: <?= htmlspecialchars($role) ?></span>
    </div>

    <!-- Overall real-time progress bar -->
    <div class="overall-progress-container no-print">
        <div class="overall-progress-bar" id="overallBar" style="width: <?= $progress_percent ?>%"></div>
        <div class="progress-text" id="overallText">Loading progress...</div>
    </div>

    <!-- Stats grid -->
    <div class="stock-grid no-print">
        <div class="stock-card" style="border-left-color: var(--mms-cyan);">
            <small class="stat-label">Jumlah Sekolah</small><br>
            <b class="stat-value" id="statsSchools"><?= number_format($total_schools) ?></b>
        </div>
        <div class="stock-card" style="border-left-color: var(--mms-success);">
            <small class="stat-label">Selesai Dihantar</small><br>
            <b class="stat-value text-success" id="statsDelivered"><?= number_format($total_delivered) ?></b>
        </div>
        <div class="stock-card" style="border-left-color: var(--mms-warning);">
            <small class="stat-label">Jumlah Karton</small><br>
            <b class="stat-value text-warning" id="statsCartons"><?= number_format($total_cartons) ?></b>
        </div>
    </div>

    <!-- Admin Setup Tools -->
    <?php if ($role === 'admin' || $role === 'staff'): ?>
    <div id="adminArea" class="setup-grid no-print">
        <h5 class="fw-bold text-primary mb-3"><i class="bi bi-sliders me-2"></i>Alatan Admin & Pengurusan PSS</h5>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-bold">📅 Bilangan Hari Minum</label>
                <input type="number" id="globalMultiplier" class="form-control" value="32" onchange="processAndRender()">
            </div>
            <div class="col-md-8">
                <label class="form-label fw-bold">Muat Naik Data Excel Sekolah (.xlsx / .xls)</label>
                <div class="input-group">
                    <input type="file" id="excelInput" class="form-control" accept=".xlsx, .xls">
                    <button onclick="saveDataToServer()" id="saveBtn" class="btn btn-success fw-bold px-4" style="display:none;"><i class="bi bi-cloud-upload me-1"></i> Simpan ke DB</button>
                </div>
            </div>
        </div>
        <hr class="my-4">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted text-uppercase">Tapis Mengikut Dealer (HD)</label>
                <select id="adminDealerFilter" onchange="processAndRender()" class="form-select"></select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted text-uppercase">Tapis Mengikut Daerah</label>
                <select id="adminDistrictFilter" onchange="processAndRender()" class="form-select"></select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted text-uppercase">Tapis Mengikut CO / Cycle</label>
                <select id="adminCoFilter" onchange="processAndRender()" class="form-select"></select>
            </div>
        </div>
    </div>
    <?php else: ?>
        <!-- Hidden elements for script references on dealer dashboard -->
        <select id="adminDealerFilter" style="display:none;"></select>
        <select id="adminDistrictFilter" style="display:none;"></select>
        <select id="adminCoFilter" style="display:none;"></select>
    <?php endif; ?>

    <!-- Display area for grouped days -->
    <div id="mainDisplay"></div>

    <!-- Vehicles List configuration -->
    <div id="vehicleSection" class="card shadow-sm border-0 p-4 mt-4 no-print" style="background:#f8fafc; border-radius:16px;">
        <h5 class="fw-bold text-navy mb-3"><i class="bi bi-truck-flatbed me-2 text-primary"></i>Daftar Kenderaan Master PSS</h5>
        <div id="vehicleList" class="mb-3"></div>
        <div class="d-flex gap-2">
            <button onclick="addVehicleRow()" class="btn btn-outline-primary fw-bold btn-sm"><i class="bi bi-plus-lg me-1"></i> Tambah Lori / Van</button>
            <button onclick="saveVehicles()" class="btn btn-primary fw-bold btn-sm"><i class="bi bi-save me-1"></i> Simpan Senarai Kenderaan</button>
        </div>
    </div>

    <!-- Trip Engine -->
    <div id="tripArea" class="card shadow-sm border-0 p-4 mt-4 no-print" style="background:#fffbeb; border-radius:16px; border:1px solid #fef3c7;">
        <h5 class="fw-bold text-warning mb-2"><i class="bi bi-lightning-charge-fill me-2"></i>Janaan Trip & Muatan Van</h5>
        <p class="text-muted small">Pilih tarikh penghantaran dan lori untuk melakukan simulasi muatan lori.</p>
        
        <div class="row g-3 align-items-end">
            <div class="col-md-8">
                <label class="form-label fw-bold">Pilih Tarikh untuk Dijana:</label>
                <select id="filterDateSelect" onchange="loadPriorityList()" class="form-select"></select>
            </div>
            <div class="col-md-4">
                <div id="activeVehicleChecklist" class="d-flex flex-wrap gap-2 mb-2"></div>
            </div>
        </div>

        <div id="priorityArea" style="display:none;" class="mt-4">
            <label class="form-label fw-bold"><i class="bi bi-sort-down me-1"></i>Susun Keutamaan Sekolah (Drag/Guna Anak Panah):</label>
            <div id="priorityList" class="mb-3" style="max-height: 250px; overflow-y: auto;"></div>
            
            <div class="d-grid mt-3">
                <button onclick="calculateTrips()" class="btn btn-warning btn-lg fw-bold text-dark py-3"><i class="bi bi-play-fill me-1"></i> JANA ARAHAN TRIP & MUATAN</button>
            </div>
        </div>
    </div>

    <!-- Trip Results -->
    <div id="results" style="display:none;" class="mt-4 card shadow-sm border-0 p-4">
        <div id="tripDetails"></div>
        <div class="d-flex gap-2 mt-3 no-print">
            <button onclick="window.print()" class="btn btn-secondary fw-bold w-100 py-2.5"><i class="bi bi-printer-fill me-1"></i> Cetak Jadual Trip</button>
        </div>
    </div>

</div>

<script>
    const currentDealer = '<?= $username ?>';
    const currentRole = '<?= $role ?>';
    const PALLET_SIZE = 144;
    let schoolsData = [];
    let registeredVehicles = [];
    let currentPriorityQueue = [];

    function showToast(msg) {
        const c = document.getElementById('toast-container');
        const t = document.createElement('div');
        t.className = 'toast';
        t.innerText = "✅ " + msg;
        c.appendChild(t);
        setTimeout(() => t.remove(), 2500);
    }

    function formatTarikhCantik(dateStr) {
        if (!dateStr || dateStr === "Tiada Tarikh" || dateStr === "0000-00-00") return "Tiada Tarikh";
        const d = new Date(dateStr);
        const hari = ["Ahad", "Isnin", "Selasa", "Rabu", "Khamis", "Jumaat", "Sabtu"];
        const bulan = ["Jan", "Feb", "Mac", "Apr", "Mei", "Jun", "Jul", "Ogos", "Sep", "Okt", "Nov", "Dis"];
        return `${d.getDate()} ${bulan[d.getMonth()]} ${d.getFullYear()}, ${hari[d.getDay()]}`;
    }

    function calcFullMuatan(ctnTotal, pcsExtra = 0) {
        let totalCtn = Number(ctnTotal);
        let extraPcs = Number(pcsExtra);
        if (extraPcs >= 24) {
            totalCtn += Math.floor(extraPcs / 24);
            extraPcs = extraPcs % 24;
        }
        const pallet = Math.floor(totalCtn / PALLET_SIZE);
        const bakiCtn = totalCtn % PALLET_SIZE;
        let result = [];
        if (pallet > 0) result.push(`${pallet} pallet`);
        if (bakiCtn > 0 || (pallet === 0 && extraPcs === 0)) result.push(`${bakiCtn} ctn`);
        if (extraPcs > 0) result.push(`${extraPcs} pcs`);
        return "(" + result.join(' + ') + ")";
    }

    async function loadDataFromServer() {
        try {
            const r = await fetch(`api_pss.php?action=get_schools&dealer=${currentDealer}&role=${currentRole}&t=${Date.now()}`);
            const res = await r.json();
            schoolsData = Array.isArray(res) ? res.map(s => ({
                ...s,
                isDelivered: s.isDelivered == 1,
                isDocSigned: s.isDocSigned == 1
            })) : [];
            
            await loadVehicles();
            updateFilters();
            
            // Set default filter to latest CO
            const coList = [...new Set(schoolsData.map(s => s.co_no))].filter(Boolean).sort();
            if (coList.length > 0) {
                const latestCo = coList[coList.length - 1];
                const filterEl = document.getElementById('adminCoFilter');
                if (filterEl) { filterEl.value = latestCo; }
            }
            
            processAndRender();
        } catch (e) {
            console.error("Gagal memuatkan data PSS dari pelayan.");
        }
    }

    function updateFilters() {
        const dSel = document.getElementById('adminDealerFilter');
        const xSel = document.getElementById('adminDistrictFilter');
        const cSel = document.getElementById('adminCoFilter');
        if (!dSel || !xSel || !cSel) return;

        const dealers   = [...new Set(schoolsData.map(s => s.dealer))].filter(Boolean).sort();
        const districts = [...new Set(schoolsData.map(s => s.district))].filter(Boolean).sort();
        const cos       = [...new Set(schoolsData.map(s => s.co_no))].filter(Boolean).sort();

        dSel.innerHTML = '<option value="all">-- Semua Dealer --</option>' + dealers.map(d => `<option value="${d}">${d.toUpperCase()}</option>`).join('');
        xSel.innerHTML = '<option value="all">-- Semua Daerah --</option>' + districts.map(d => `<option value="${d}">${d}</option>`).join('');
        cSel.innerHTML = '<option value="all">-- Semua CO / Cycle --</option>' + cos.map(c => `<option value="${c}">${c}</option>`).join('');
    }

    function processAndRender() {
        const container = document.getElementById('mainDisplay');
        container.innerHTML = "";
        
        let fD = schoolsData;
        const sd = document.getElementById('adminDealerFilter').value;
        const sx = document.getElementById('adminDistrictFilter').value;
        const sc = document.getElementById('adminCoFilter').value;

        if (currentRole === 'admin' || currentRole === 'staff') {
            if (sd && sd !== 'all') fD = fD.filter(x => x.dealer === sd);
        } else {
            fD = fD.filter(x => x.dealer === currentDealer);
        }
        if (sx && sx !== 'all') fD = fD.filter(x => x.district === sx);
        if (sc && sc !== 'all') fD = fD.filter(x => x.co_no === sc);

        // Update real-time progress calculations
        let totalCtn = 0, doneCtn = 0;
        let totalSch = fD.length, doneSch = 0;
        
        fD.forEach(s => {
            const c = Number(s.totalCartons) || 0;
            totalCtn += c;
            if (s.isDelivered) {
                doneCtn += c;
                doneSch++;
            }
        });

        const percent = totalCtn ? Math.round((doneCtn / totalCtn) * 100) : 0;
        document.getElementById('overallBar').style.width = percent + "%";
        document.getElementById('overallText').innerText = `Progres Kitaran: ${percent}% (${doneCtn}/${totalCtn} Carton)`;

        document.getElementById('statsSchools').innerText = totalSch;
        document.getElementById('statsDelivered').innerText = doneSch;
        document.getElementById('statsCartons').innerText = totalCtn;

        // Group by Date
        const grp = fD.reduce((g, s) => {
            const d = s.plan_date || "Tiada Tarikh";
            (g[d] = g[d] || []).push(s);
            return g;
        }, {});

        // Date Dropdown for Trip
        const dateSelect = document.getElementById('filterDateSelect');
        const activeDates = Object.keys(grp).filter(d => d !== "Tiada Tarikh" && d !== "0000-00-00").sort();
        dateSelect.innerHTML = '<option value="">-- Sila Pilih Tarikh --</option>' + 
            activeDates.map(d => `<option value="${d}">${formatTarikhCantik(d)}</option>`).join('');

        Object.keys(grp).sort().forEach(dt => {
            let rC = 0, rP = 0;
            grp[dt].forEach(s => {
                rC += Number(s.totalCartons);
                rP += Number(s.extraPacks);
            });

            const dayDiv = document.createElement('div');
            dayDiv.className = 'day-section';
            dayDiv.innerHTML = `
                <div class="day-header">📅 ${formatTarikhCantik(dt)}</div>
                <div class="day-summary">
                    <i class="bi bi-box-seam-fill"></i> Muatan Harian: ${calcFullMuatan(rC, rP)}
                </div>
                <div class="table-wrapper">
                    <table class="align-middle">
                        <thead>
                            <tr>
                                <th>Sekolah / Lokasi</th>
                                <th class="text-center" style="width:100px;">Penghantaran</th>
                                <th class="text-center" style="width:100px;">SAP (Kunci)</th>
                                <th class="text-end" style="width:180px;">Perancangan Tarikh</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${grp[dt].map(s => {
                                const extraText = Number(s.extraPacks) > 0 ? ` + ${s.extraPacks} pek` : "";
                                return `
                                <tr style="${s.isDelivered ? 'background:#ecfdf5' : ''}">
                                    <td>
                                        <span class="badge bg-secondary font-monospace fs-7 me-1">${s.co_no}</span>
                                        <strong>${s.name}</strong>
                                        <br>
                                        <small class="text-muted">${s.district} | Baki: ${s.totalCartons} ctn${extraText} | HD: ${s.dealer.toUpperCase()}</small>
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input" ${s.isDelivered ? 'checked' : ''} 
                                               onchange="updateRec('${s.id}', 'isDelivered', this.checked)">
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input" ${s.isDocSigned ? 'checked' : ''} 
                                               onchange="updateRec('${s.id}', 'isDocSigned', this.checked)">
                                    </td>
                                    <td class="text-end">
                                        <input type="date" class="form-control form-control-sm d-inline-block text-center" 
                                               value="${s.plan_date || ''}" 
                                               onchange="updateRec('${s.id}', 'plan_date', this.value)" 
                                               style="width:140px; font-size:0.8rem;"
                                               ${currentRole !== 'admin' && currentRole !== 'staff' ? 'disabled' : ''}>
                                    </td>
                                </tr>`;
                            }).join('')}
                        </tbody>
                    </table>
                </div>`;
            container.appendChild(dayDiv);
        });

        if (Object.keys(grp).length === 0) {
            container.innerHTML = '<p class="text-center text-muted py-5">Tiada sekolah ditemui untuk tapisan semasa.</p>';
        }
    }

    async function updateRec(id, field, value) {
        const s = schoolsData.find(x => x.id === id);
        if (!s) return;
        
        s[field] = value;
        if (field === 'isDelivered' && value && !s.delivery_date) {
            s.delivery_date = new Date().toISOString().split('T')[0];
        }

        try {
            await fetch('api_pss.php?action=save_schools', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify([s])
            });
            showToast(`Kemaskini berjaya: ${s.name}`);
            processAndRender();
        } catch (e) {
            showToast('Gagal mengemas kini rekod.');
        }
    }

    async function loadVehicles() {
        const r = await fetch(`api_pss.php?action=get_vehicles&dealer=${currentDealer}&t=${Date.now()}`);
        registeredVehicles = await r.json();
        
        const list = document.getElementById('vehicleList');
        const chk = document.getElementById('activeVehicleChecklist');
        list.innerHTML = '';
        chk.innerHTML = '';

        if (Array.isArray(registeredVehicles)) {
            registeredVehicles.forEach(v => {
                list.innerHTML += `
                <div class="v-row">
                    <span class="badge bg-secondary font-monospace" style="min-width:60px;">${v.owner}</span>
                    <input type="text" class="form-control form-control-sm v-name" value="${v.v_name}" style="flex:1;">
                    <input type="number" class="form-control form-control-sm v-cap" value="${v.v_capacity}" style="width:90px;" placeholder="Kapasiti">
                    <button onclick="this.parentElement.remove()" class="btn btn-outline-danger btn-sm p-1.5 border-0"><i class="bi bi-trash"></i></button>
                </div>`;

                chk.innerHTML += `
                <label class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1.5 cursor-pointer">
                    <input type="checkbox" class="v-sel form-check-input" value="${v.v_name}" data-cap="${v.v_capacity}" checked> 
                    ${v.v_name} (${v.v_capacity} ctn)
                </label>`;
            });
        }
    }

    function addVehicleRow() {
        document.getElementById('vehicleList').innerHTML += `
        <div class="v-row">
            <span class="badge bg-secondary font-monospace" style="min-width:60px;">${currentDealer}</span>
            <input type="text" class="form-control form-control-sm v-name" placeholder="Nama Lori / No Plate" style="flex:1;">
            <input type="number" class="form-control form-control-sm v-cap" placeholder="Ctn Cap" style="width:90px;">
            <button onclick="this.parentElement.remove()" class="btn btn-outline-danger btn-sm p-1.5 border-0"><i class="bi bi-trash"></i></button>
        </div>`;
    }

    async function saveVehicles() {
        const rows = document.querySelectorAll('.v-row');
        const data = Array.from(rows).map(r => ({
            v_name: r.querySelector('.v-name').value,
            v_capacity: Number(r.querySelector('.v-cap').value),
            owner: currentDealer
        })).filter(v => v.v_name !== "");

        try {
            await fetch('api_pss.php?action=save_vehicles_global', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });
            showToast("Senarai kenderaan disimpan!");
            await loadVehicles();
        } catch (e) {
            showToast("Gagal menyimpan senarai kenderaan.");
        }
    }

    function loadPriorityList() {
        const date = document.getElementById('filterDateSelect').value;
        currentPriorityQueue = JSON.parse(JSON.stringify(schoolsData.filter(s => s.plan_date === date && !s.isDelivered)));
        
        const area = document.getElementById('priorityArea');
        if (!currentPriorityQueue.length) {
            area.style.display = 'none';
            return;
        }
        area.style.display = 'block';
        renderPriorityUI();
    }

    function renderPriorityUI() {
        document.getElementById('priorityList').innerHTML = currentPriorityQueue.map((s, i) => `
            <div class="prio-item" data-id="${s.id}">
                <span><b>${i+1}.</b> [${s.co_no}] <b>${s.name}</b> (${s.totalCartons} ctn)</span>
                <div class="prio-controls d-flex gap-1">
                    <button type="button" onclick="moveItem(${i},-1)"><i class="bi bi-arrow-up"></i></button>
                    <button type="button" onclick="moveItem(${i},1)"><i class="bi bi-arrow-down"></i></button>
                </div>
            </div>`).join('');
    }

    function moveItem(idx, dir) {
        let tidx = idx + dir;
        if (tidx >= 0 && tidx < currentPriorityQueue.length) {
            [currentPriorityQueue[idx], currentPriorityQueue[tidx]] = [currentPriorityQueue[tidx], currentPriorityQueue[idx]];
            renderPriorityUI();
        }
    }

    function calculateTrips() {
        const date = document.getElementById('filterDateSelect').value;
        const activeV = Array.from(document.querySelectorAll('.v-sel:checked')).map(cb => ({
            v_name: cb.value,
            v_capacity: Number(cb.dataset.cap)
        }));

        if (activeV.length === 0) return alert("Sila pilih sekurang-kurangnya sebuah kenderaan!");

        let q = JSON.parse(JSON.stringify(currentPriorityQueue));
        let html = `<h4 class="fw-bold mb-3 border-bottom pb-2 text-primary no-print"><i class="bi bi-file-earmark-text-fill me-2"></i>Arahan Trip Penghantaran: ${formatTarikhCantik(date)}</h4>`;
        let tripNum = 1;

        while (q.length > 0) {
            html += `<div class="trip-block">
                <h5 class="fw-bold text-navy mb-3"><i class="bi bi-truck me-2"></i>TRIP ${tripNum}</h5>`;
            
            activeV.forEach(v => {
                let cap = v.v_capacity;
                let current = 0;
                let list = [];

                while (q.length > 0 && current < cap) {
                    let s = q[0];
                    let space = cap - current;
                    let sCtn = Number(s.totalCartons);

                    if (sCtn <= space) {
                        current += sCtn;
                        list.push(`<li>${s.name}: <b>${sCtn} ctn</b> ${Number(s.extraPacks) > 0 ? '+ ' + s.extraPacks + ' pek' : ''}</li>`);
                        q.shift();
                    } else {
                        current += space;
                        s.totalCartons = sCtn - space;
                        list.push(`<li>${s.name} (Baki/Pecahan): <b>${space} ctn</b></li>`);
                        break;
                    }
                }

                if (list.length) {
                    let badgeClass = 'v-3rd';
                    if (v.v_name.toLowerCase().includes('hiace')) badgeClass = 'v-hiace';
                    else if (v.v_name.toLowerCase().includes('granmax')) badgeClass = 'v-granmax';

                    html += `
                    <div class="van-info">
                        <span class="van-badge ${badgeClass}">${v.v_name}</span>
                        <strong>Muatan: ${current} ctn</strong>
                        <ul class="mb-0 mt-2 text-secondary" style="font-size:0.85rem;">${list.join('')}</ul>
                    </div>`;
                }
            });
            html += `</div>`;
            tripNum++;
        }

        document.getElementById('tripDetails').innerHTML = html;
        document.getElementById('results').style.display = 'block';
    }

    // Excel Parser (Old System logic)
    document.getElementById('excelInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const reader = new FileReader();
        const multiplier = Number(document.getElementById('globalMultiplier').value);

        reader.onload = function(evt) {
            const data = new Uint8Array(evt.target.result);
            const workbook = XLSX.read(data, {type: 'array'});
            const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
            const json = XLSX.utils.sheet_to_json(firstSheet);

            schoolsData = json.map((row) => {
                const keys = Object.keys(row);
                const n = row[keys.find(k => k.toLowerCase().includes('sekolah'))];
                const t = parseExcelDate(row[keys.find(k => k.toLowerCase().includes('tarikh'))]);
                const b = Number(row[keys.find(k => k.toLowerCase().includes('pelajar') || k.toLowerCase().includes('murid'))]) || 0;
                const co = row[keys.find(k => k.toLowerCase().includes('co') || k.toLowerCase().includes('cycle'))] || 'CO-NEW';
                const d = row[keys.find(k => k.toLowerCase().includes('dealer') || k.toLowerCase().includes('hd'))] || 'admin';
                const dist = row[keys.find(k => k.toLowerCase().includes('daerah') || k.toLowerCase().includes('district'))] || 'Besut';
                
                const total = b * multiplier;
                
                return {
                    id: (n + "_" + t).replace(/\s+/g, '_'),
                    name: n,
                    plan_date: t,
                    totalCartons: Math.floor(total/24),
                    extraPacks: total%24,
                    dealer: d.toLowerCase(),
                    co_no: co,
                    district: dist,
                    isDelivered: false,
                    isDocSigned: false
                };
            });

            document.getElementById('saveBtn').style.display = 'inline-block';
            showToast("Excel parsed. Klik 'Simpan ke DB' untuk menyegerakkan data.");
            processAndRender();
        };
        reader.readAsArrayBuffer(file);
    });

    function parseExcelDate(d) {
        if (!d) return new Date().toISOString().split('T')[0];
        if (!isNaN(d) && typeof d === 'number') return new Date((d - 25569) * 86400 * 1000).toISOString().split('T')[0];
        if (typeof d === 'string' && d.includes('/')) {
            const p = d.split('/');
            return `${p[2]}-${p[1].padStart(2,'0')}-${p[0].padStart(2,'0')}`;
        }
        return d;
    }

    async function saveDataToServer() {
        if (!schoolsData.length) return;
        try {
            await fetch('api_pss.php?action=save_schools', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(schoolsData)
            });
            document.getElementById('saveBtn').style.display = 'none';
            showToast("Data PSS berjaya disegerakkan ke pangkalan data.");
            loadDataFromServer();
        } catch (e) {
            showToast("Sync Error");
        }
    }

    window.onload = loadDataFromServer;
</script>

<?php require_once 'includes/footer.php'; ?>