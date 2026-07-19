<?php
// warehouse_layout.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/db.php';

// Auto-migration for Warehouse Layout Grid
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `warehouse_slots` (
      `location_code` varchar(20) NOT NULL,
      `zone` varchar(10) NOT NULL,
      `lane` varchar(5) NOT NULL,
      `row_num` int(11) NOT NULL,
      `batch_id` int(11) DEFAULT NULL,
      PRIMARY KEY (`location_code`),
      KEY `batch_id` (`batch_id`)
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
require_once 'includes/header.php';
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

    /* Section Summary Cards */
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

    /* EXACT BLUEPRINT OFFSETS */
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

    /* GRADIENT STATUS COLORS */
    .slot-empty { 
        background: linear-gradient(135deg, #ef4444 0%, #991b1b 100%); /* Red */
    }
    .slot-medium { 
        background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); /* Blue */
    }
    .slot-full { 
        background: linear-gradient(135deg, #10b981 0%, #047857 100%); /* Green */
    }

    .slot-tooltip {
        position: absolute;
        bottom: 120%;
        left: 50%;
        transform: translateX(-50%);
        background-color: var(--tooltip-bg);
        color: white;
        padding: 10px 14px;
        border-radius: 8px;
        font-size: 0.75rem;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s;
        box-shadow: 0 4px 12px rgba(0,0,0,0.4);
        z-index: 100;
        pointer-events: none;
        border: 1px solid rgba(255,255,255,0.1);
    }
    
    .pallet-slot:hover .slot-tooltip {
        opacity: 1;
        visibility: visible;
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

    <!-- Occupancy Summary Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="occupancy-card">
                <div class="occupancy-title"><i class="bi bi-backpack me-2"></i>PSS (School Stocks)</div>
                <div class="occupancy-value text-info" id="occ-PSS">Calculating...</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="occupancy-card">
                <div class="occupancy-title"><i class="bi bi-shop me-2"></i>COM (Commercial Stocks)</div>
                <div class="occupancy-value text-warning" id="occ-COM">Calculating...</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="occupancy-card">
                <div class="occupancy-title"><i class="bi bi-box-seam me-2"></i>POW (Powder Stocks)</div>
                <div class="occupancy-value text-success" id="occ-POW">Calculating...</div>
            </div>
        </div>
    </div>

    <div class="layout-container shadow-lg">
        <div class="warehouse-floor">
            <!-- Column 1: PSS A-C -->
            <div class="layout-col">
                <div class="block-title">PSS-A | PSS-B | PSS-C</div>
                <div class="slot-grid grid-3-col" id="grid-PSS_ABC"></div>
            </div>

            <!-- Column 2: PSS D-F -->
            <div class="layout-col">
                <div class="block-title">PSS-D | PSS-E | PSS-F</div>
                <div class="slot-grid grid-3-col" id="grid-PSS_DEF"></div>
            </div>

            <div class="wall-divider"></div>

            <!-- Column 3: COM A-B & POW -->
            <div class="layout-col">
                <div class="block-container">
                    <div class="block-title">COM-A | COM-B</div>
                    <div class="slot-grid grid-2-col" id="grid-COM_AB"></div>
                </div>
                <div class="block-container" style="margin-top: 0.5rem;">
                    <div class="block-title">POW-A | POW-B | POW-C</div>
                    <div class="slot-grid grid-3-col" id="grid-POW_ABC"></div>
                </div>
            </div>

            <!-- Column 4: COM C-D -->
            <div class="layout-col offset-com-cd">
                <div class="block-title">COM-C | COM-D</div>
                <div class="slot-grid grid-2-col" id="grid-COM_CD"></div>
            </div>

            <!-- Column 5: COM E-F -->
            <div class="layout-col offset-com-ef">
                <div class="block-title">COM-E | COM-F</div>
                <div class="slot-grid grid-2-col" id="grid-COM_EF"></div>
            </div>
        </div>
        
        <div class="legend">
            <div class="legend-item">
                <div class="legend-box slot-empty"></div> Empty (0)
            </div>
            <div class="legend-item">
                <div class="legend-box slot-medium"></div> Partial Load
            </div>
            <div class="legend-item">
                <div class="legend-box slot-full"></div> Full Pallet
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    fetchWarehouseData();
    setInterval(fetchWarehouseData, 30000); 
});

function fetchWarehouseData() {
    fetch('api/get_warehouse_grid.php')
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                renderWarehouseGrid(data.data);
                calculateOccupancy(data.data);
            }
        })
        .catch(error => console.error('Error fetching grid:', error));
}

// Function to calculate and update the occupancy stats
function calculateOccupancy(slots) {
    const stats = {
        'PSS': { occupied: 0, total: 0 },
        'COM': { occupied: 0, total: 0 },
        'POW': { occupied: 0, total: 0 }
    };

    slots.forEach(slot => {
        if (stats[slot.zone] !== undefined) {
            stats[slot.zone].total++;
            if (parseInt(slot.quantity) > 0) {
                stats[slot.zone].occupied++;
            }
        }
    });

    for (const zone in stats) {
        const data = stats[zone];
        const percentage = data.total > 0 ? Math.round((data.occupied / data.total) * 100) : 0;
        
        const el = document.getElementById(`occ-${zone}`);
        if (el) {
            el.innerText = `(${data.occupied}/${data.total} - ${percentage}%)`;
        }
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
        let blockSlots = slots.filter(config.filter);
        
        blockSlots.sort((a, b) => {
            if (a.row_num !== b.row_num) {
                return parseInt(a.row_num) - parseInt(b.row_num);
            }
            return a.lane.localeCompare(b.lane);
        });

        let floorHtml = '';

        blockSlots.forEach(slot => {
            let qty = parseInt(slot.quantity) || 0;
            let capacity = parseInt(slot.pallet_capacity) || 0;
            
            let statusClass = 'slot-empty';
            if (qty > 0) {
                if (capacity > 0 && qty >= capacity) {
                    statusClass = 'slot-full';
                } else {
                    statusClass = 'slot-medium';
                }
            }

            const shortLabel = slot.zone === 'POW' ? slot.lane : slot.lane + slot.row_num;
            
            let timeStr = '';
            if(qty > 0 && slot.received_date_timestamp) {
                const dateObj = new Date(slot.received_date_timestamp);
                timeStr = dateObj.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            }

            const tooltipContent = (qty > 0) 
                ? `<strong>${slot.location_code} #${slot.batch_no || 'N/A'}</strong><br>
                   ${slot.sku_name || 'Unknown Item'}<br>
                   Qty: ${qty} / ${capacity || '?'} CTN<br>
                   <small>Received ${timeStr}</small>` 
                : `<strong>${slot.location_code}</strong><br>Empty`;

            floorHtml += `
                <div class="pallet-slot ${statusClass}" id="slot-${slot.location_code}">
                    ${shortLabel}
                    <div class="slot-tooltip">${tooltipContent}</div>
                </div>
            `;
        });

        const container = document.getElementById(config.id);
        if(container) {
            container.innerHTML = floorHtml;
        }
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>