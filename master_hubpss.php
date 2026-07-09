<?php
// master_hubpss.php
// FIXED: Removed hardcoded credentials. Now uses config/db.php

// Load shared DB config
$configFile = __DIR__ . '/config/db.php';
if (!file_exists($configFile)) {
    die("<div style='color:red;padding:20px'>Error: config/db.php not found.</div>");
}
require_once $configFile;

// $pdo is now available from config/db.php
try {
    // Live stats from database
    $total_schools   = $pdo->query("SELECT COUNT(*) FROM mms_logistik")->fetchColumn() ?: 0;
    $total_delivered = $pdo->query("SELECT COUNT(*) FROM mms_logistik WHERE isDelivered = 1")->fetchColumn() ?: 0;
    $total_cartons   = $pdo->query("SELECT SUM(totalCartons) FROM mms_logistik")->fetchColumn() ?: 0;

    $progress_percent = ($total_schools > 0) ? round(($total_delivered / $total_schools) * 100) : 0;

} catch (PDOException $e) {
    $total_schools = $total_delivered = $total_cartons = 0;
    $progress_percent = 0;
}


$page_title = 'MMS Master Hub PSS';
require_once 'includes/header.php';
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<style>
    :root { --primary: #2c3e50; --blue: #3498db; --green: #27ae60; --red: #e74c3c; --orange: #e67e22; --navy: #0b2147; }
    .container { max-width: 1100px; margin: 20px auto; background: white; padding: 25px; border-radius: 15px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); }
    .setup-grid { background: #e3f2fd; padding: 20px; border-radius: 12px; margin: 15px 0; border: 1px solid #bbdefb; }
    .overall-progress-container { background: #eee; border-radius: 20px; height: 26px; position: relative; overflow: hidden; margin: 15px 0; }
    .overall-progress-bar { background: linear-gradient(90deg, #27ae60, #2ecc71); height: 100%; transition: width 0.8s ease; }
    .progress-text { position: absolute; width: 100%; text-align: center; top: 4px; font-weight: bold; font-size: 0.85rem; z-index: 1; }
    .stock-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .stock-card { padding: 15px; border-radius: 10px; border-left: 6px solid #ccc; background: #f8fafc; }
    .day-section { margin-top: 25px; border: 1px solid #ddd; border-radius: 10px; overflow: hidden; }
    .day-header { background: var(--primary); color: white; padding: 12px; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 12px; border-bottom: 1px solid #eee; text-align: left; font-size: 0.9rem; }
    .btn { padding: 10px 20px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; }
    #toast-container { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 10000; }
    .toast { background: rgba(44, 62, 80, 0.95); color: white; padding: 12px 25px; border-radius: 30px; margin-bottom: 10px; }
    @media (max-width: 768px) {
        .container { padding: 15px; margin: 10px auto; border-radius: 10px; }
        .stock-grid { grid-template-columns: 1fr; }
        .setup-grid { padding: 15px; }
        td, th { padding: 10px 8px; font-size: 0.85rem; }
    }
</style>

<div id="toast-container"></div>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
        <h2 style="color:var(--navy);">PSS Master Control</h2>
        <span style="background:var(--green); color:white; padding:5px 15px; border-radius:20px; font-size:0.8rem; font-weight:bold;">AKSES: ADMIN</span>
    </div>

    <div class="overall-progress-container">
        <div class="overall-progress-bar" style="width: <?= $progress_percent ?>%"></div>
        <div class="progress-text">Progres Keseluruhan: <?= $progress_percent ?>%</div>
    </div>

    <div class="stock-grid">
        <div class="stock-card" style="border-left-color: var(--blue);">
            <small>JUMLAH SEKOLAH</small><br>
            <b style="font-size:1.5rem;"><?= number_format($total_schools) ?></b>
        </div>
        <div class="stock-card" style="border-left-color: var(--green);">
            <small>SIAP HANTAR</small><br>
            <b style="font-size:1.5rem;"><?= number_format($total_delivered) ?></b>
        </div>
        <div class="stock-card" style="border-left-color: var(--orange);">
            <small>TOTAL KARTON</small><br>
            <b style="font-size:1.5rem;"><?= number_format($total_cartons) ?></b>
        </div>
    </div>

    <?php if ($role === 'admin'): ?>
    <div id="adminArea" class="setup-grid">
        <strong>Import Data Baru (Excel):</strong><br>
        <input type="file" id="excelInput" onchange="handleExcel(event)" style="margin:15px 0;">
        <button id="saveBtn" onclick="saveDataToServer()" class="btn" style="background:var(--green); color:white; display:none;">Simpan Ke Database</button>
        
        <div style="display:flex; gap:12px; margin-top:15px;">
            <select id="adminDealerFilter" onchange="processAndRender()" style="flex:1; padding:10px; border-radius:6px; border:1px solid #ccc;"></select>
            <select id="adminDistrictFilter" onchange="processAndRender()" style="flex:1; padding:10px; border-radius:6px; border:1px solid #ccc;"></select>
        </div>
    </div>
    <?php endif; ?>

    <div id="mainDisplay"></div>
</div>

<script>
    let schoolsData = [];
    const PALLET_SIZE = 144;

    async function loadDataFromServer() {
        try {
            const r = await fetch(`api.php?action=get_schools&t=${Date.now()}`);
            const res = await r.json();
            schoolsData = Array.isArray(res) ? res.map(s => ({
                ...s,
                isDelivered: s.isDelivered == 1,
                isDocSigned: s.isDocSigned == 1
            })) : [];
            updateFilters();
            processAndRender();
        } catch (e) {
            console.error("Data gagal dimuatkan dari server:", e);
        }
    }

    function updateFilters() {
        const dSel = document.getElementById('adminDealerFilter');
        const xSel = document.getElementById('adminDistrictFilter');
        if (!dSel || !xSel) return;

        const dealers   = [...new Set(schoolsData.map(s => s.dealer))].filter(Boolean).sort();
        const districts = [...new Set(schoolsData.map(s => s.district))].filter(Boolean).sort();

        dSel.innerHTML = '<option value="all">Semua Dealer</option>' + dealers.map(d => `<option value="${d}">${d}</option>`).join('');
        xSel.innerHTML = '<option value="all">Semua Daerah</option>' + districts.map(d => `<option value="${d}">${d}</option>`).join('');
    }

    function processAndRender() {
        const c = document.getElementById('mainDisplay');
        c.innerHTML = "";

        let fD = schoolsData;
        const dSel = document.getElementById('adminDealerFilter');
        const xSel = document.getElementById('adminDistrictFilter');
        const sd = dSel ? dSel.value : 'all';
        const sx = xSel ? xSel.value : 'all';
        if (sd && sd !== 'all') fD = fD.filter(x => x.dealer === sd);
        if (sx && sx !== 'all') fD = fD.filter(x => x.district === sx);

        const grp = fD.reduce((g, s) => {
            const d = s.plan_date || "Tiada Tarikh";
            (g[d] = g[d] || []).push(s);
            return g;
        }, {});

        Object.keys(grp).sort().forEach(dt => {
            c.innerHTML += `
                <div class="day-section">
                    <div class="day-header">${dt}</div>
                    <table>
                        ${grp[dt].map(s => `
                            <tr>
                                <td>
                                    <strong>${s.name}</strong><br>
                                    <small>${s.district} | ${s.totalCartons} karton</small>
                                </td>
                                <td style="text-align:right">
                                    <input type="checkbox" ${s.isDelivered ? 'checked' : ''} 
                                           onchange="updateRec('${s.id}', 'isDelivered', this.checked)"> Siap
                                </td>
                            </tr>
                        `).join('')}
                    </table>
                </div>`;
        });

        if (Object.keys(grp).length === 0) {
            c.innerHTML = '<p style="text-align:center;color:#999;padding:30px">Tiada data untuk dipaparkan.</p>';
        }
    }

    async function updateRec(id, field, value) {
        schoolsData = schoolsData.map(s => s.id == id ? {...s, [field]: value} : s);
        try {
            await fetch('api.php?action=save_schools', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(schoolsData.filter(s => s.id == id))
            });
            showToast('Rekod dikemaskini');
        } catch(e) {
            showToast('Gagal kemaskini');
        }
    }

    function showToast(msg) {
        const t = document.createElement('div');
        t.className = 'toast';
        t.textContent = msg;
        document.getElementById('toast-container').appendChild(t);
        setTimeout(() => t.remove(), 3000);
    }

    window.onload = loadDataFromServer;
</script>

<?php require_once 'includes/footer.php'; ?>