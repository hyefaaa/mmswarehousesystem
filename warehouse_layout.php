<?php
// warehouse_layout.php
session_start();
require_once 'includes/header.php';
require_once 'config/db.php';

// Auto-migration for Warehouse Layout Grid
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `warehouse_slots` (
      `location_code` varchar(20) NOT NULL,
      `zone` varchar(10) NOT NULL,
      `lane` varchar(5) NOT NULL,
      `row_num` int(11) NOT NULL,
      PRIMARY KEY (`location_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    $count = $pdo->query("SELECT COUNT(*) FROM warehouse_slots")->fetchColumn();
    if ($count == 0) {
        $layout_config = [
            ['zone' => 'PSS', 'lanes' => ['A', 'B', 'C'], 'rows' => 18],
            ['zone' => 'PSS', 'lanes' => ['D', 'E', 'F'], 'rows' => 14],
            ['zone' => 'COM', 'lanes' => ['A', 'B'], 'rows' => 13],
            ['zone' => 'COM', 'lanes' => ['C', 'D'], 'rows' => 11],
            ['zone' => 'COM', 'lanes' => ['E', 'F'], 'rows' => 2],
            ['zone' => 'POW', 'lanes' => ['A', 'B', 'C'], 'rows' => 1]
        ];

        $stmt = $pdo->prepare("INSERT INTO warehouse_slots (location_code, zone, lane, row_num) VALUES (?, ?, ?, ?)");
        
        $pdo->beginTransaction();
        foreach ($layout_config as $block) {
            foreach ($block['lanes'] as $lane) {
                for ($r = 1; $r <= $block['rows']; $r++) {
                    $loc_code = "{$block['zone']}-{$lane}-{$r}";
                    $stmt->execute([$loc_code, $block['zone'], $lane, $r]);
                }
            }
        }
        $pdo->commit();
    }
} catch (Exception $e) {
    error_log("Warehouse slots migration failed: " . $e->getMessage());
}

$page_title = 'Warehouse Visual Layout | MMS';
?>

<style>
    :root {
        --bg-dark: #0f172a;
        --tooltip-bg: #1e293b;
        --grid-gap: 6px;
        --slot-size: 40px;
    }

    .layout-container {
        background-color: var(--bg-dark);
        padding: 3rem 2rem;
        border-radius: 12px;
        min-height: 80vh;
        overflow-x: auto;
    }

    .occupancy-card {
        background-color: var(--bg-dark);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 10px;
        padding: 1rem 1.5rem;
        color: white;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .occupancy-title {
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #94a3b8;
    }
    .occupancy-value {
        font-size: 1.5rem;
        font-weight: 800;
        margin-top: 5px;
    }

    .warehouse-floor {
        display: flex;
        gap: 2.5rem;
        align-items: flex-start;
        width: max-content;
    }

    .layout-col {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .offset-com-cd { margin-top: 138px; } 
    .offset-com-ef { margin-top: 368px; } 

    .wall-divider {
        width: 6px;
        background-color: #334155;
        height: 850px;
        border-radius: 4px;
        margin: 0 0.5rem;
    }

    .block-container {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .block-title {
        color: #ffffff;
        font-weight: 800;
        text-align: center;
        margin-bottom: 5px;
        font-size: 0.85rem;
        letter-spacing: 1px;
        white-space: nowrap;
    }

    .slot-grid {
        display: grid;
        gap: var(--grid-gap);
        margin: 0 auto;
    }

    .grid-3-col { grid-template-columns: repeat(3, var(--slot-size)); }
    .grid-2-col { grid-template-columns: repeat(2, var(--slot-size)); }

    .pallet-slot {
        width: var(--slot-size);
        height: var(--slot-size);
        border-radius: 8px;
        position: relative;
        cursor: pointer;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.65rem;
        font-weight: 800;
        color: rgba(255, 255, 255, 0.7);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.1);
    }
    
    .pallet-slot:hover { 
        transform: scale(1.15); 
        z-index: 10; 
        box-shadow: 0 6px 12px rgba(0,0,0,0.5);
    }

    .slot-empty { background: linear-gradient(135deg, #ef4444 0%, #991b1b 100%); }
    .slot-medium { background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); }
    .slot-full { background: linear-gradient(135deg, #10b981 0%, #047857 100%); }

    /* Tooltip with Scrolling and Interaction Bridge */
    .slot-tooltip {
        position: absolute;
        bottom: 120%;
        left: 50%;
        transform: translateX(-50%);
        background-color: var(--tooltip-bg);
        color: white;
        padding: 12px 16px;
        border-radius: 8px;
        font-size: 0.75rem;
        width: max-content;
        max-width: 300px;
        max-height: 250px;
        overflow-y: auto;
        opacity: 0;
        visibility: hidden;
        z-index: 1000;
        pointer-events: none;
        transition: opacity 0.2s;
        border: 1px solid rgba(255,255,255,0.1);
        margin-bottom: 10px;
    }

    .pallet-slot::after {
        content: '';
        position: absolute;
        bottom: 100%;
        left: 0;
        width: 100%;
        height: 20px;
        background: transparent;
    }

    .pallet-slot:hover .slot-tooltip,
    .slot-tooltip:hover {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }

    .pallet-slot.tooltip-down .slot-tooltip {
        bottom: auto;
        top: 120%;
        margin-bottom: 0;
        margin-top: 10px;
    }

    .legend {
        display: flex;
        gap: 1.5rem;
        color: white;
        font-weight: 600;
        margin-top: 3rem;
    }
    
    .legend-item { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; }
    .legend-box { width: 20px; height: 20px; border-radius: 4px; }
</style>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-800 text-dark"><i class="bi bi-map-fill me-2"></i>Live Layout Map</h2>
        <div class="bg-dark text-white px-3 py-2 rounded shadow-sm fw-bold">
            <i class="bi bi-calendar3 me-2"></i><?= date('d/m/Y') ?>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="occupancy-card"><div class="occupancy-title">PSS (School Stocks)</div><div class="occupancy-value text-info" id="occ-PSS">...</div></div></div>
        <div class="col-md-4"><div class="occupancy-card"><div class="occupancy-title">COM (Commercial Stocks)</div><div class="occupancy-value text-warning" id="occ-COM">...</div></div></div>
        <div class="col-md-4"><div class="occupancy-card"><div class="occupancy-title">POW (Powder Stocks)</div><div class="occupancy-value text-success" id="occ-POW">...</div></div></div>
    </div>

    <div class="layout-container shadow-lg">
        <div class="warehouse-floor">
            <div class="layout-col"><div class="block-title">PSS-A | PSS-B | PSS-C</div><div class="slot-grid grid-3-col" id="grid-PSS_ABC"></div></div>
            <div class="layout-col"><div class="block-title">PSS-D | PSS-E | PSS-F</div><div class="slot-grid grid-3-col" id="grid-PSS_DEF"></div></div>
            <div class="wall-divider"></div>
            <div class="layout-col">
                <div class="block-container"><div class="block-title">COM-A | COM-B</div><div class="slot-grid grid-2-col" id="grid-COM_AB"></div></div>
                <div class="block-container" style="margin-top: 0.5rem;"><div class="block-title">POW-A | POW-B | POW-C</div><div class="slot-grid grid-3-col" id="grid-POW_ABC"></div></div>
            </div>
            <div class="layout-col offset-com-cd"><div class="block-title">COM-C | COM-D</div><div class="slot-grid grid-2-col" id="grid-COM_CD"></div></div>
            <div class="layout-col offset-com-ef"><div class="block-title">COM-E | COM-F</div><div class="slot-grid grid-2-col" id="grid-COM_EF"></div></div>
        </div>
    </div>
</div>

<!-- Assignment Modal -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white" style="border: 1px solid rgba(255,255,255,0.1); border-radius: 12px;">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-in-down me-2"></i>Assign Pallet Slot</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="assignForm">
                    <input type="hidden" id="inputLocationCode" name="location_code">
                    <div class="text-center mb-3"><span class="text-muted small text-uppercase">Selected Location</span><h2 class="fw-900 text-info mb-0" id="displayLocationCode"></h2></div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small text-uppercase">Select Inventory Batch(es)</label>
                        <select class="form-select fw-bold" id="inputBatchId" name="batch_ids[]" multiple style="height: 180px;"></select>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success px-4 fw-bold" onclick="saveAssignment()">Save Assignment</button>
            </div>
        </div>
    </div>
</div>

<script>
let assignModal = new bootstrap.Modal(document.getElementById('assignModal'));
document.addEventListener('DOMContentLoaded', () => { fetchWarehouseData(); setInterval(fetchWarehouseData, 30000); });

function fetchWarehouseData() {
    fetch('api/get_warehouse_grid.php')
        .then(res => res.json())
        .then(data => { if(data.status === 'success') { renderWarehouseGrid(data.data); calculateOccupancy(data.data); } });
}

function calculateOccupancy(slots) {
    const stats = { 'PSS': { o: 0, t: 0 }, 'COM': { o: 0, t: 0 }, 'POW': { o: 0, t: 0 } };
    slots.forEach(s => { if(stats[s.zone]) { stats[s.zone].t++; if(s.items?.length > 0) stats[s.zone].o++; } });
    for (const z in stats) {
        const el = document.getElementById(`occ-${z}`);
        if(el) el.innerText = `(${stats[z].o}/${stats[z].t} - ${stats[z].t > 0 ? Math.round((stats[z].o/stats[z].t)*100) : 0}%)`;
    }
}

function renderWarehouseGrid(slots) {
    const blocks = {
        'PSS_ABC': { id: 'grid-PSS_ABC', filter: s => s.zone === 'PSS' && ['A','B','C'].includes(s.lane) },
        'PSS_DEF': { id: 'grid-PSS_DEF', filter: s => s.zone === 'PSS' && ['D','E','F'].includes(s.lane) },
        'COM_AB':  { id: 'grid-COM_AB',  filter: s => s.zone === 'COM' && ['A','B'].includes(s.lane) },
        'COM_CD':  { id: 'grid-COM_CD',  filter: s => s.zone === 'COM' && ['C','D'].includes(s.lane) },
        'COM_EF':  { id: 'grid-COM_EF',  filter: s => s.zone === 'COM' && ['E','F'].includes(s.lane) },
        'POW_ABC': { id: 'grid-POW_ABC', filter: s => s.zone === 'POW' }
    };

    for (const [key, config] of Object.entries(blocks)) {
        let blockSlots = slots.filter(config.filter).sort((a,b) => parseInt(a.row_num)-parseInt(b.row_num) || a.lane.localeCompare(b.lane));
        let html = '';
        blockSlots.forEach(slot => {
            let totalCtn = 0, tooltipList = '', assignedIds = [];
            if(slot.items?.length > 0) {
                slot.items.forEach(item => {
                    totalCtn += parseInt(item.quantity) || 0;
                    assignedIds.push(item.batch_id);
                    tooltipList += `<div style="margin-bottom:8px; border-bottom:1px solid #334; padding-bottom:4px;">
                        <div style="color:#38bdf8; font-weight:800;">${item.sku_name}</div>
                        <div>Qty: ${parseInt(item.quantity) || 0} CTN | Pallet: ${item.pallet_type||'None'}</div>
                        <div>Batch: <a href="javascript:void(0)" onclick="event.stopPropagation(); showBatchTrail('${item.batch_no}', ${item.product_id})" style="color: #60a5fa; font-weight: bold; text-decoration: underline;">#${item.batch_no}</a></div>
                    </div>`;
                });
            }
            let status = totalCtn > 0 ? (totalCtn >= (parseInt(slot.items?.[0]?.pallet_capacity)||0) ? 'slot-full' : 'slot-medium') : 'slot-empty';
            html += `<div class="pallet-slot ${status} ${(parseInt(slot.row_num)<=2||slot.zone==='POW')?'tooltip-down':''}" 
                     onclick="openAssignModal('${slot.location_code}', ${JSON.stringify(assignedIds).replace(/"/g, '&quot;')})">
                     ${slot.zone==='POW'?slot.lane:slot.lane+slot.row_num}
                     <div class="slot-tooltip">
                        <div style="position:sticky; top:0; background:var(--tooltip-bg); font-weight:800; margin-bottom:5px;">${slot.location_code}</div>
                        ${tooltipList||'Empty Slot'}
                     </div>
                     </div>`;
        });
        document.getElementById(config.id).innerHTML = html;
    }
}

function openAssignModal(locationCode, currentBatchIds) {
    document.getElementById('displayLocationCode').innerText = locationCode;
    document.getElementById('inputLocationCode').value = locationCode;
    const selectEl = document.getElementById('inputBatchId');
    selectEl.innerHTML = '<option value="">-- CLEAR SLOT (Empty) --</option>';
    fetch('api/get_available_batches.php')
        .then(res => res.json())
        .then(data => {
            data.data.forEach(b => {
                const isAssigned = (b.assigned_location && b.assigned_location !== locationCode);
                selectEl.innerHTML += `<option value="${b.batch_id}" ${currentBatchIds.includes(parseInt(b.batch_id))?'selected':''}>
                    [Batch ${b.batch_no}] ${b.product_name} (${parseInt(b.qty_on_hand) || 0} CTN) ${isAssigned?'(Assigned in '+b.assigned_location+')':''}
                </option>`;
            });
            assignModal.show();
        });
}

function saveAssignment() {
    fetch('api/assign_pallet_slot.php', { method: 'POST', body: new FormData(document.getElementById('assignForm')) })
    .then(res => res.json())
    .then(data => { if(data.status === 'success') { assignModal.hide(); fetchWarehouseData(); } else alert(data.message); });
}
</script>

<?php require_once 'includes/footer.php'; ?>