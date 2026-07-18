<?php
// import_co_ui.php - PHP version of Monthly CO Import
require_once 'config/db.php';

$page_title = 'Import Monthly CO | MMS';
require_once 'includes/header.php';
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.4.1/papaparse.min.js"></script>

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-file-earmark-excel-fill me-2 text-warning"></i>Import & Configure Contract Data</h1>
                <p class="opacity-75 mb-0 fw-light">Convert school monthly contract lists (CSV) to SAP generated lists.</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> Dashboard</a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            
            <!-- Step 1: Upload CSV File -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-cloud-upload me-2 text-warning"></i>1. Upload CSV File</h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small uppercase">Select CSV File</label>
                        <input type="file" id="csvFile" accept=".csv" class="form-control form-control-lg">
                    </div>
                    <p class="text-muted mb-0 small">
                        <i class="bi bi-info-circle me-1"></i> Upload the contract file (e.g. <code>CO OKTOBER 2025.csv</code>). The system handles headers automatically.
                    </p>
                </div>
            </div>

            <!-- Step 2: Select Daerah to Import -->
            <div class="card border-0 shadow-sm mb-4" id="districtSection" style="display:none;">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-geo-alt-fill me-2 text-warning"></i>2. Select Daerah to Import</h5>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-3">Select the districts you want to include in this batch:</p>
                    <div class="d-flex gap-3 mb-4 text-sm">
                        <button type="button" onclick="toggleAll(true)" class="btn btn-sm btn-outline-primary fw-bold"><i class="bi bi-check-all me-1"></i>Select All</button>
                        <button type="button" onclick="toggleAll(false)" class="btn btn-sm btn-outline-secondary fw-bold"><i class="bi bi-x-lg me-1"></i>Deselect All</button>
                    </div>
                    
                    <div id="daerahCheckboxes" class="row row-cols-2 row-cols-md-4 g-3 mb-3">
                        <!-- Dynamic checkboxes injected here -->
                    </div>
                    
                    <div class="border-top pt-3 mt-4 d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-navy">Total Schools Selected:</span>
                        <span class="badge bg-primary fs-6 px-3 py-2" id="schoolCount">0</span>
                    </div>
                </div>
            </div>

            <!-- Step 3: Contract & CO Details -->
            <div class="card border-0 shadow-sm mb-4" id="scenarioSection" style="display:none;">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-event me-2 text-warning"></i>3. Contract & CO Details</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted small uppercase">Contract Name</label>
                            <select id="contractName" class="form-select">
                                <option>Contract of July 2025</option>
                                <option>Contract of August 2025</option>
                                <option>Contract of September 2025</option>
                                <option>Contract of October 2025</option>
                                <option>Contract of November 2025</option>
                                <option>Contract of December 2025</option>
                                <option>Contract of January 2026</option>
                                <option>Contract of February 2026</option>
                                <option>Contract of March 2026</option>
                                <option>Contract of April 2026</option>
                                <option>Contract of May 2026</option>
                                <option>Contract of June 2026</option>
                                <option>Contract of July 2026</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted small uppercase">Last Date to Deliver</label>
                            <input type="date" id="lastDateDeliver" class="form-control">
                        </div>
                    </div>

                    <div class="bg-light p-4 rounded border">
                        <h6 class="fw-bold text-navy mb-3"><i class="bi bi-list-task me-1"></i>Contract Orders (COs)</h6>
                        <div id="coContainer">
                            <!-- Dynamic CO rows injected here -->
                        </div>
                        <button type="button" onclick="addCoRow()" class="btn btn-success btn-sm mt-3 fw-bold">
                            <i class="bi bi-plus-lg me-1"></i> Add Another CO
                        </button>
                    </div>
                </div>
            </div>

            <!-- Ready to Import -->
            <div class="card border-0 shadow-sm" id="actionSection" style="display:none;">
                <div class="card-body p-4 text-center">
                    <h3 class="fw-bold text-navy mb-2">Ready to Import?</h3>
                    <p class="text-muted small mb-4">This will generate SAP Numbers and save to the database.</p>
                    <button id="btnImport" onclick="generateAndSave()" class="btn btn-primary btn-lg px-5 py-3 fw-bold shadow">
                        <i class="bi bi-cloud-arrow-up-fill me-2"></i>Import to Database
                    </button>
                    <div id="statusMessage" class="mt-4 alert alert-info" style="display:none;"></div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    let allData = [];
    let headers = [];

    // 1. Handle File Upload
    document.getElementById('csvFile').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function(evt) {
            const text = evt.target.result;
            const lines = text.split('\n');
            
            // Assuming row index 2 (3rd line) is the header based on your file format
            const cleanCsv = lines.slice(2).join('\n');

            Papa.parse(cleanCsv, {
                header: true,
                skipEmptyLines: true,
                complete: function(results) {
                    allData = results.data;
                    headers = results.meta.fields;
                    console.log("Parsed Data:", allData);
                    initDaerahSelection();
                }
            });
        };
        reader.readAsText(file);
    });

    // 2. Initialize Daerah Checkboxes
    function initDaerahSelection() {
        const container = document.getElementById('daerahCheckboxes');
        container.innerHTML = '';
        
        const daerahs = [...new Set(allData.map(item => item['DAERAH']).filter(d => d))].sort();

        daerahs.forEach(daerah => {
            const col = document.createElement('div');
            col.className = 'col';
            col.innerHTML = `
                <div class="form-check">
                    <input type="checkbox" value="${daerah}" id="d_${daerah}" class="form-check-input daerah-checkbox" onchange="updateCount()">
                    <label class="form-check-label text-dark fw-medium small" for="d_${daerah}">${daerah}</label>
                </div>
            `;
            container.appendChild(col);
        });

        document.getElementById('districtSection').style.display = 'block';
        document.getElementById('scenarioSection').style.display = 'block';
        document.getElementById('actionSection').style.display = 'block';

        if(document.querySelectorAll('.co-row').length === 0) {
            addCoRow(); 
        }
    }

    // Helper: Select/Deselect All
    function toggleAll(check) {
        document.querySelectorAll('.daerah-checkbox').forEach(cb => cb.checked = check);
        updateCount();
    }

    // 3. Update School Count
    function updateCount() {
        const selectedDaerahs = Array.from(document.querySelectorAll('.daerah-checkbox:checked')).map(cb => cb.value);
        const count = allData.filter(row => selectedDaerahs.includes(row['DAERAH'])).length;
        document.getElementById('schoolCount').innerText = count;
    }

    // 4. Dynamic CO Form Logic
    function addCoRow() {
        const container = document.getElementById('coContainer');
        const index = container.children.length + 1;
        
        const row = document.createElement('div');
        row.className = 'co-row row g-2 mb-3 pb-3 border-bottom align-items-end';
        row.innerHTML = `
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold uppercase">CO Number</label>
                <input type="text" placeholder="e.g. CO7" class="co-number form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold uppercase">Bil TP</label>
                <input type="number" placeholder="e.g. 25" class="co-tp form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold uppercase">Consumption Start</label>
                <input type="date" class="co-start form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold uppercase">Consumption End</label>
                <div class="d-flex align-items-center gap-2">
                    <input type="date" class="co-end form-control form-control-sm">
                    ${index > 1 ? `<button type="button" onclick="this.closest('.co-row').remove()" class="btn btn-outline-danger btn-sm px-2"><i class="bi bi-trash"></i></button>` : '<div style="width: 32px;"></div>'}
                </div>
            </div>
        `;
        container.appendChild(row);
    }

    // 5. Generate Data and Send to Server
    function generateAndSave() {
        // A. Validation
        const selectedDaerahs = Array.from(document.querySelectorAll('.daerah-checkbox:checked')).map(cb => cb.value);
        if(selectedDaerahs.length === 0) {
            alert("Please select at least one DAERAH.");
            return;
        }

        const contract = document.getElementById('contractName').value;
        const lastDate = document.getElementById('lastDateDeliver').value;

        if(!lastDate) {
            alert("Please select the 'Last Date to Deliver'.");
            return;
        }

        // B. Prepare Data
        const coRows = document.querySelectorAll('.co-row');
        const coList = Array.from(coRows).map(row => ({
            co_number: row.querySelector('.co-number').value,
            bil_tp: row.querySelector('.co-tp').value,
            consumption_start: row.querySelector('.co-start').value,
            consumption_end: row.querySelector('.co-end').value
        }));

        // Find dynamic 'BIL MURID' column key
        const bilMuridKey = Object.keys(allData[0] || {}).find(k => k.includes("BIL MURID PENERIMA"));

        const filteredSchools = allData
            .filter(row => selectedDaerahs.includes(row['DAERAH']))
            .map(row => ({
                daerah: row['DAERAH'],
                kod_sekolah: row['KOD SEKOLAH'],
                nama_sekolah: row['NAMA SEKOLAH'],
                bil_murid: row[bilMuridKey] ? row[bilMuridKey].trim().replace(/,/g, '') : '0' // Remove commas
            }));

        const payload = {
            metadata: {
                contract_name: contract,
                last_delivery_date: lastDate,
                cos: coList
            },
            schools: filteredSchools
        };

        // C. Send to Backend
        const btn = document.getElementById('btnImport');
        const statusDiv = document.getElementById('statusMessage');
        
        btn.disabled = true;
        btn.innerText = "Processing...";
        statusDiv.className = "mt-4 alert alert-warning";
        statusDiv.innerText = "Sending data to server... Please wait.";
        statusDiv.style.display = 'block';

        fetch('save_import.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                statusDiv.className = "mt-4 alert alert-success";
                statusDiv.innerHTML = `<strong>Success!</strong> ${data.message} (Batch ID: ${data.batch_id})`;
                btn.innerText = "Import Complete";
            } else {
                throw new Error(data.message || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            statusDiv.className = "mt-4 alert alert-danger";
            statusDiv.innerText = "Error: " + error.message;
            btn.innerText = "Try Again";
            btn.disabled = false;
        });
    }
</script>

<?php require_once 'includes/footer.php'; ?>
