<?php
// pss_delivery.php - Dealer/HD Specific School Deliveries Checklist (Old System Restored)
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
    :root {
        --mms-navy: #0b2147;
        --mms-cyan: #06b6d4;
        --mms-light: #f8fafc;
        --mms-success: #10b981;
        --mms-warning: #f59e0b;
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

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-mortarboard-fill me-2 text-success"></i>School Delivery PSS</h1>
                <p class="opacity-75 mb-0 fw-light">Checklist and SAP documentation for schools under your delivery zone</p>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> Dashboard</a>
            </div>
        </div>
    </div>
</div>

<div class="container px-4 pb-5">
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="fw-bold text-navy mb-0"><i class="bi bi-list-check me-2 text-success"></i>Senarai Penghantaran Sekolah PSS</h3>
        <span class="badge bg-success px-3 py-2 rounded-pill text-uppercase" style="letter-spacing: 0.5px;">AKSES: <?= htmlspecialchars($role) ?></span>
    </div>

    <!-- Real-time progress bar -->
    <div class="overall-progress-container">
        <div class="overall-progress-bar" id="overallBar" style="width: <?= $progress_percent ?>%"></div>
        <div class="progress-text" id="overallText">Loading progress...</div>
    </div>

    <!-- CO / Cycle Filter (before stats so user picks CO first) -->
    <div class="card border-0 shadow-sm p-3 mb-3" style="border-radius:14px; background:#f8fafc;">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted text-uppercase mb-1"><i class="bi bi-funnel me-1"></i>Tapis Mengikut Kitaran (CO)</label>
                <select id="coFilterSelect" class="form-select" onchange="processAndRender()">
                    <option value="">&mdash; Semua Kitaran &mdash;</option>
                    <?php foreach ($co_list as $co): ?>
                    <option value="<?= htmlspecialchars($co) ?>" <?= ($co === $latest_co) ? 'selected' : '' ?>>
                        Kitaran <?= htmlspecialchars($co) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8">
                <p class="text-muted small mb-0 mt-2"><i class="bi bi-info-circle me-1 text-primary"></i>Pilih kitaran untuk melihat senarai penghantaran. Default: Kitaran terkini <strong>(<?= htmlspecialchars($latest_co) ?>)</strong>.</p>
            </div>
        </div>
    </div>

    <!-- Stats grid -->
    <div class="stock-grid">
        <div class="stock-card" style="border-left-color: var(--mms-cyan);">
            <small class="stat-label">Jumlah Sekolah</small><br>
            <b class="stat-value" id="statsSchools">0</b>
        </div>
        <div class="stock-card" style="border-left-color: var(--mms-success);">
            <small class="stat-label">Selesai Dihantar</small><br>
            <b class="stat-value text-success" id="statsDelivered">0</b>
        </div>
        <div class="stock-card" style="border-left-color: var(--mms-warning);">
            <small class="stat-label">Jumlah Karton</small><br>
            <b class="stat-value text-warning" id="statsCartons">0</b>
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
                                        ${s.isDelivered && s.delivery_date ? `<br><small class="text-success d-block fw-bold mt-1" style="font-size: 0.72rem; line-height: 1.1;"><i class="bi bi-clock me-1"></i>${formatDateTime(s.delivery_date)}</small>` : ''}
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input" ${s.isDocSigned ? 'checked' : ''} 
                                               onchange="updateRec('${s.id}', 'isDocSigned', this.checked)">
                                    </td>
                                    <td class="text-end text-muted small fw-bold">
                                        ${s.plan_date || 'Tiada Tarikh'}
                                    </td>
                                </tr>`;
                            }).join('')}
                        </tbody>
                    </table>
                </div>`;
            container.appendChild(dayDiv);
        });

        if (Object.keys(grp).length === 0) {
            container.innerHTML = '<p class="text-center text-muted py-5">Tiada sekolah ditugaskan untuk anda pada kitaran ini.</p>';
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