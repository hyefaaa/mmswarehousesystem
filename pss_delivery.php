<?php
// pss_delivery.php - Dealer/HD Specific School Deliveries Checklist (Old System Restored)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
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
    try {
        $pdo->exec("USE susumura_mms_logistik");
    } catch (PDOException $e) {
        error_log("USE susumura_mms_logistik failed in pss_delivery, falling back to main database: " . $e->getMessage());
    }

    // Fetch distinct CO list for the filter dropdown (latest first)
    $co_list = $pdo->query("SELECT DISTINCT co_no FROM mms_logistik ORDER BY co_no DESC")->fetchAll(PDO::FETCH_COLUMN);
    $latest_co = !empty($co_list) ? $co_list[0] : '';

    // Stats will be calculated client-side after CO filter is applied
    $total_schools   = 0;
    $total_delivered = 0;
    $total_cartons   = 0;
    $progress_percent = 0;
} catch (PDOException $e) {
    $total_schools = $total_delivered = $total_cartons = 0;
    $progress_percent = 0;
    $co_list = [];
    $latest_co = '';
}

$page_title = 'School Delivery PSS | MMS';
require_once 'includes/header.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
    :root { --mms-navy:#0b2147; --mms-cyan:#06b6d4; --mms-success:#10b981; --mms-warning:#f59e0b; }
    body { font-family:'Inter',sans-serif; background:#f1f5f9; }

    /* Hero */
    .pss-hero { background:linear-gradient(135deg,#0b2147 0%,#1e3a5f 50%,#0e7490 100%); padding:36px 0 56px; margin-bottom:-32px; position:relative; overflow:hidden; }
    .pss-hero::after { content:''; position:absolute; top:0;left:0;right:0;bottom:0; background:radial-gradient(circle at 80% 30%,rgba(6,182,212,0.18) 0%,transparent 60%); pointer-events:none; }
    .pss-hero h1 { font-size:1.8rem; font-weight:900; letter-spacing:-0.5px; }

    /* Progress */
    .progress-wrap { background:rgba(255,255,255,0.12); border-radius:30px; height:36px; position:relative; overflow:hidden; box-shadow:inset 0 2px 4px rgba(0,0,0,0.1); margin-top:16px; }
    .progress-fill { background:linear-gradient(90deg,#10b981,#34d399,#6ee7b7,#34d399); background-size:200% 100%; animation:shimmer 2.5s infinite linear; height:100%; border-radius:30px; transition:width 1s cubic-bezier(0.4,0,0.2,1); }
    @keyframes shimmer { 0%{background-position:200% center} 100%{background-position:-200% center} }
    .progress-label { position:absolute; width:100%; text-align:center; top:50%; transform:translateY(-50%); font-weight:800; font-size:0.88rem; color:white; z-index:2; text-shadow:0 1px 3px rgba(0,0,0,0.35); }

    /* Filter */
    .filter-card { background:white; border-radius:18px; padding:20px 24px; border:1px solid #e2e8f0; box-shadow:0 4px 20px rgba(0,0,0,0.06); margin-bottom:20px; }
    .filter-label { font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:1.2px; color:#64748b; display:block; margin-bottom:8px; }
    .filter-card .form-select { border-radius:12px; border:1.5px solid #e2e8f0; font-weight:600; font-size:0.9rem; padding:10px 16px; transition:border-color 0.2s,box-shadow 0.2s; }
    .filter-card .form-select:focus { border-color:#06b6d4; box-shadow:0 0 0 3px rgba(6,182,212,0.15); }

    /* Stats */
    .stats-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px; }
    @media(max-width:768px){.stats-grid{grid-template-columns:1fr;}}
    .stat-card-pss { background:white; border-radius:18px; padding:18px 20px; border:1px solid #e2e8f0; box-shadow:0 2px 12px rgba(0,0,0,0.05); display:flex; align-items:center; gap:14px; transition:transform 0.2s,box-shadow 0.2s; }
    .stat-card-pss:hover { transform:translateY(-3px); box-shadow:0 8px 25px rgba(0,0,0,0.1); }
    .stat-icon-box { width:50px; height:50px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.35rem; flex-shrink:0; }
    .stat-lbl { font-size:0.68rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#94a3b8; margin-bottom:3px; }
    .stat-num { font-size:1.75rem; font-weight:900; line-height:1; letter-spacing:-1px; color:#0f172a; }

    /* Day Sections */
    .day-section { background:white; border-radius:18px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.05); border:1px solid #e2e8f0; margin-bottom:20px; }
    .day-header { background:linear-gradient(135deg,#0b2147,#1e3a5f); color:white; padding:14px 20px; font-weight:800; font-size:0.93rem; letter-spacing:0.3px; display:flex; align-items:center; gap:10px; }
    .day-badge { background:rgba(255,255,255,0.15); border-radius:20px; padding:3px 12px; font-size:0.72rem; font-weight:700; }
    .day-summary { background:linear-gradient(135deg,#fffbeb,#fef9c3); padding:10px 20px; border-bottom:1px solid #fef3c7; color:#d97706; font-weight:700; font-size:0.82rem; display:flex; align-items:center; gap:6px; }

    /* Table */
    .pss-table { width:100%; border-collapse:collapse; }
    .pss-table th { background:#f8fafc; color:#64748b; font-weight:700; text-transform:uppercase; font-size:0.7rem; letter-spacing:0.8px; padding:12px 18px; border-bottom:2px solid #e2e8f0; }
    .pss-table td { padding:13px 18px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
    .pss-table tr:last-child td { border-bottom:none; }
    .pss-table tr.delivered-row { background:linear-gradient(135deg,#f0fdf4,#ecfdf5); }
    .pss-table tbody tr:hover td { background:rgba(6,182,212,0.03); }
    .co-badge { display:inline-flex; align-items:center; background:#1e3a5f; color:#7dd3fc; font-size:0.62rem; font-weight:800; padding:2px 8px; border-radius:6px; font-family:monospace; margin-right:5px; }
    .school-name-main { font-weight:700; color:#1e293b; font-size:0.92rem; }
    .school-sub { font-size:0.76rem; color:#94a3b8; margin-top:2px; }
    .delivery-stamp { font-size:0.7rem; color:#10b981; font-weight:700; margin-top:4px; display:flex; align-items:center; gap:3px; }
    .form-check-input { width:1.3em; height:1.3em; cursor:pointer; border-radius:6px !important; border:2px solid #cbd5e1; transition:all 0.15s; }
    .form-check-input:checked { background-color:#10b981; border-color:#10b981; }
    .form-check-input:focus { box-shadow:0 0 0 3px rgba(16,185,129,0.2); }
    .plan-input { width:140px; font-size:0.78rem; border-radius:10px; border:1.5px solid #e2e8f0; padding:6px 10px; transition:border-color 0.2s; }
    .plan-input:focus { border-color:#06b6d4; box-shadow:0 0 0 3px rgba(6,182,212,0.15); outline:none; }

    /* Toast */
    #toast-container { position:fixed; top:80px; left:50%; transform:translateX(-50%); z-index:10000; }
    .toast-msg { background:linear-gradient(135deg,#1e293b,#0f172a); color:white; padding:11px 24px; border-radius:30px; margin-bottom:10px; font-weight:700; font-size:0.85rem; box-shadow:0 10px 30px rgba(0,0,0,0.3); animation:slideDown 0.3s forwards; border:1px solid rgba(255,255,255,0.1); display:flex; align-items:center; gap:8px; }
    @keyframes slideDown { from{transform:translateY(-20px);opacity:0} to{transform:translateY(0);opacity:1} }
    .empty-state { text-align:center; padding:60px 20px; color:#94a3b8; }
    .empty-state i { font-size:3rem; opacity:0.4; margin-bottom:12px; display:block; }
    @media print { .pss-hero,.filter-card,#toast-container{display:none!important} .day-section{break-inside:avoid} }
    
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
</style>

<div id="toast-container"></div>

<!-- PREMIUM HERO HEADER -->
<div class="pss-hero">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div class="text-white">
                <h1 class="mb-1">
                    <i class="bi bi-mortarboard-fill me-2" style="color:#34d399;"></i>
                    Senarai Penghantaran Sekolah PSS
                </h1>
                <p class="mb-0" style="opacity:0.7;font-size:0.88rem;">Checklist pengesahan &amp; dokumentasi SAP — zon penghantaran anda</p>
            </div>
            <div class="d-flex flex-column align-items-end gap-2">
                <span class="badge px-3 py-2 rounded-pill text-uppercase fw-bold" style="background:rgba(255,255,255,0.12);color:white;font-size:0.7rem;border:1px solid rgba(255,255,255,0.2);">
                    <i class="bi bi-person-badge me-1"></i><?= strtoupper(htmlspecialchars($role)) ?>
                </span>
                <a href="index.php" class="btn btn-sm btn-outline-light rounded-pill px-3">
                    <i class="bi bi-house me-1"></i>Dashboard
                </a>
            </div>
        </div>
        <div class="progress-wrap">
            <div class="progress-fill" id="overallBar" style="width:0%;"></div>
            <div class="progress-label" id="overallText">Memuatkan progres...</div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5" style="padding-top:48px;"> 

    <!-- CO Filter Card -->
    <div class="filter-card">
        <div class="row g-3 align-items-center">
            <div class="col-md-4">
                <span class="filter-label"><i class="bi bi-funnel-fill me-1"></i>Tapis Mengikut Kitaran (CO)</span>
                <select id="coFilterSelect" class="form-select" onchange="processAndRender()">
                    <option value="">&mdash; Semua Kitaran &mdash;</option>
                    <?php foreach ($co_list as $co): ?>
                    <option value="<?= htmlspecialchars($co) ?>" <?= ($co === $latest_co) ? 'selected' : '' ?>>
                        &#x25CF; Kitaran <?= htmlspecialchars($co) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8 d-flex align-items-center gap-2 flex-wrap">
                <?php foreach ($co_list as $co): ?>
                <span class="badge rounded-pill px-3 py-2 fw-bold" style="background:<?= $co===$latest_co?'#0b2147':'#f1f5f9'; ?>; color:<?= $co===$latest_co?'white':'#475569'; ?>; font-size:0.75rem; cursor:pointer;"
                      onclick="document.getElementById('coFilterSelect').value='<?= htmlspecialchars($co) ?>'; processAndRender();">
                    <?= htmlspecialchars($co) ?><?= $co===$latest_co?' &#x2605;':'' ?>
                </span>
                <?php endforeach; ?>
                <small class="text-muted ms-1"><i class="bi bi-info-circle"></i> Default: Kitaran terkini</small>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card-pss">
            <div class="stat-icon-box" style="background:#eff6ff;"><i class="bi bi-building-fill" style="color:#3b82f6;"></i></div>
            <div><div class="stat-lbl">Jumlah Sekolah</div><div class="stat-num" id="statsSchools">—</div></div>
        </div>
        <div class="stat-card-pss">
            <div class="stat-icon-box" style="background:#f0fdf4;"><i class="bi bi-check-circle-fill" style="color:#10b981;"></i></div>
            <div><div class="stat-lbl">Selesai Dihantar</div><div class="stat-num" id="statsDelivered" style="color:#10b981;">—</div></div>
        </div>
        <div class="stat-card-pss">
            <div class="stat-icon-box" style="background:#fffbeb;"><i class="bi bi-box-seam-fill" style="color:#f59e0b;"></i></div>
            <div><div class="stat-lbl">Jumlah Karton</div><div class="stat-num" id="statsCartons" style="color:#f59e0b;">—</div></div>
        </div>
    </div>

    <!-- Display area for grouped days -->
    <div id="mainDisplay"></div>

</div>

<script>
    const currentDealer = '<?= $username ?>';
    const currentRole = '<?= $role ?>';
    const PALLET_SIZE = 144;
    let schoolsData = [];

    function showToast(msg, type='success') {
        const c = document.getElementById('toast-container');
        const t = document.createElement('div');
        t.className = 'toast-msg';
        const icon = type === 'success' ? '&#10003;' : '&#x26A0;';
        t.innerHTML = `<span>${icon}</span> ${msg}`;
        c.appendChild(t);
        setTimeout(() => t.remove(), 2800);
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
            processAndRender();
        } catch (e) {
            console.error("Gagal memuatkan data PSS dari pelayan.");
        }
    }

    function processAndRender() {
        const container = document.getElementById('mainDisplay');
        container.innerHTML = "";
        
        let fD = schoolsData;
        if (currentRole !== 'admin' && currentRole !== 'staff') {
            fD = fD.filter(x => x.dealer === currentDealer);
        }

        // Apply CO filter
        const coFilter = document.getElementById('coFilterSelect')?.value || '';
        if (coFilter) {
            fD = fD.filter(x => x.co_no === coFilter);
        }

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

        Object.keys(grp).sort().forEach(dt => {
            let rC = 0, rP = 0;
            grp[dt].forEach(s => {
                rC += Number(s.totalCartons);
                rP += Number(s.extraPacks);
            });

            const dayDiv = document.createElement('div');
            dayDiv.className = 'day-section';
            dayDiv.innerHTML = `
                <div class="day-header">
                    <i class="bi bi-calendar3"></i>
                    ${formatTarikhCantik(dt)}
                    <span class="day-badge ms-auto">${grp[dt].length} sekolah</span>
                </div>
                <div class="day-summary">
                    <i class="bi bi-box-seam-fill"></i>
                    Muatan Harian: ${calcFullMuatan(rC, rP)}
                </div>
                <div style="overflow-x:auto;">
                    <table class="pss-table align-middle">
                        <thead>
                            <tr>
                                <th>Sekolah / Lokasi</th>
                                <th class="text-center" style="width:120px;">Penghantaran</th>
                                <th class="text-center" style="width:100px;">SAP (Kunci)</th>
                                <th class="text-end" style="width:160px;">Perancangan Tarikh</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${grp[dt].map(s => {
                                const extraText = Number(s.extraPacks) > 0 ? ` + ${s.extraPacks} pek` : "";
                                return `
                                <tr class="${s.isDelivered ? 'delivered-row' : ''}">
                                    <td>
                                        <div><span class="co-badge">${s.co_no}</span><span class="school-name-main">${s.name}</span></div>
                                        <div class="school-sub">${s.district} &nbsp;|&nbsp; ${s.totalCartons} ctn${extraText} &nbsp;|&nbsp; HD: ${(s.dealer||'').toUpperCase()}</div>
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input" ${s.isDelivered ? 'checked' : ''}
                                               onchange="updateRec('${s.id}', 'isDelivered', this.checked)">
                                        ${s.isDelivered && s.delivery_date ? `<div class="delivery-stamp"><i class="bi bi-clock"></i>${formatDateTime(s.delivery_date)}</div>` : ''}
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input" ${s.isDocSigned ? 'checked' : ''}
                                               onchange="updateRec('${s.id}', 'isDocSigned', this.checked)">
                                        ${s.isDocSigned && s.doc_signed_date ? `<div class="delivery-stamp"><i class="bi bi-clock"></i>${formatDateTime(s.doc_signed_date)}</div>` : ''}
                                    </td>
                                    <td class="text-end">
                                        <span class="text-muted small fw-bold" style="font-size:0.78rem;">${s.plan_date && s.plan_date!=='0000-00-00' ? s.plan_date : '<span style="color:#cbd5e1;">Tiada Tarikh</span>'}</span>
                                    </td>
                                </tr>`;
                            }).join('')}
                        </tbody>
                    </table>
                </div>`;
            container.appendChild(dayDiv);
        });

        if (Object.keys(grp).length === 0) {
            container.innerHTML = '<div class="empty-state"><i class="bi bi-inbox"></i><p>Tiada sekolah ditugaskan untuk anda pada kitaran ini.</p></div>';
        }
    }

    function formatDateTime(dtStr) {
        if (!dtStr || dtStr === "0000-00-00 00:00:00" || dtStr === "0000-00-00") return "";
        try {
            const parts = dtStr.split(' ');
            const datePart = parts[0];
            const timePart = parts[1] || '';
            
            const d = new Date(datePart);
            if (isNaN(d.getTime())) return dtStr;
            const day = String(d.getDate()).padStart(2, '0');
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const year = d.getFullYear();
            
            let formatted = `${day}/${month}/${year}`;
            if (timePart) {
                const tParts = timePart.split(':');
                if (tParts.length >= 2) {
                    formatted += ` ${tParts[0]}:${tParts[1]}`;
                }
            }
            return formatted;
        } catch (e) {
            return dtStr;
        }
    }

    async function updateRec(id, field, value) {
        const s = schoolsData.find(x => x.id === id);
        if (!s) return;
        
        s[field] = value;
        if (field === 'isDelivered') {
            if (value) {
                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const seconds = String(now.getSeconds()).padStart(2, '0');
                s.delivery_date = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
            } else {
                s.delivery_date = null;
            }
        }
        if (field === 'isDocSigned') {
            if (value) {
                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const seconds = String(now.getSeconds()).padStart(2, '0');
                s.doc_signed_date = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
            } else {
                s.doc_signed_date = null;
            }
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

    window.onload = loadDataFromServer;
</script>

<?php require_once 'includes/footer.php'; ?>