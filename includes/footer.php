<?php
// includes/footer.php
?>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <!-- HTML5 QRCode -->
    <script src="https://unpkg.com/html5-qrcode"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- MMS Dual Language Engine -->
    <script src="lang/translations.js?v=<?= time() ?>"></script>
    <!-- Global Filter State Manager -->
    <script>
    (function() {
        if (!window.location.pathname.endsWith('.php')) return;
        
        const path = window.location.pathname;
        const pageName = path.substring(path.lastIndexOf('/') + 1);
        const sessionKey = 'mms_filter_state_' + pageName;
        const currentQuery = window.location.search;
        
        const urlParams = new URLSearchParams(currentQuery);
        if (urlParams.has('reset_filters')) {
            sessionStorage.removeItem(sessionKey);
            urlParams.delete('reset_filters');
            const newQuery = urlParams.toString() ? '?' + urlParams.toString() : '';
            window.history.replaceState(null, '', path + newQuery);
            return;
        }

        if (currentQuery && currentQuery !== '?') {
            sessionStorage.setItem(sessionKey, currentQuery);
        } else {
            const savedQuery = sessionStorage.getItem(sessionKey);
            if (savedQuery) {
                window.location.replace(path + savedQuery);
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const resetBtns = document.querySelectorAll('.btn-clear, .btn-reset, a[href="'+pageName+'"]');
            resetBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    sessionStorage.removeItem(sessionKey);
                });
            });
        });
    })();
    </script>
    <!-- Modal Audit Trail Pergerakan Batch -->
    <div class="modal fade" id="batchTrailModal" tabindex="-1" aria-labelledby="batchTrailModalLabel" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content shadow-lg border-0" style="border-radius: 16px;">
                <div class="modal-header bg-dark text-white py-3">
                    <h5 class="modal-title fw-bold" id="batchTrailModalLabel">
                        <i class="bi bi-clock-history me-2 text-info"></i>Audit Trail Pergerakan Batch
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <!-- Product Metadata Section -->
                    <div class="card border-0 shadow-sm p-3 mb-4" style="border-radius: 12px; background: white;">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <span class="badge bg-secondary text-uppercase mb-2 fw-bold" id="bt-category">—</span>
                                <h5 class="fw-bold text-dark mb-1" id="bt-product-name">Product Name</h5>
                                <small class="text-muted font-monospace" id="bt-barcode">Barcode</small>
                            </div>
                            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                <div class="small fw-bold text-muted text-uppercase" style="font-size: 0.72rem;">Batch No</div>
                                <div class="fs-3 fw-bold text-primary" id="bt-batch-no">#BATCH</div>
                            </div>
                        </div>
                    </div>

                    <!-- Current Stock Details -->
                    <h6 class="fw-bold text-secondary mb-3 text-uppercase tracking-wider small"><i class="bi bi-geo-alt-fill me-1 text-primary"></i>Kedudukan Stok Semasa (Current Placements)</h6>
                    <div class="row g-3 mb-4" id="bt-current-locations-container">
                        <!-- Dynamic stock status blocks -->
                    </div>

                    <!-- Timeline Section -->
                    <h6 class="fw-bold text-secondary mb-3 text-uppercase tracking-wider small"><i class="bi bi-clock-history me-1 text-info"></i>Kronologi Pergerakan Batch (Timeline)</h6>
                    <div class="timeline-trail" id="bt-timeline">
                        <!-- Dynamic timeline items -->
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light p-3">
                    <button type="button" class="btn btn-secondary btn-sm fw-bold px-4" data-bs-dismiss="modal" style="border-radius: 8px;">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <style>
    .timeline-trail {
        position: relative;
        padding-left: 24px;
        margin-left: 12px;
        border-left: 2px dashed #cbd5e1;
    }
    .timeline-item-trail {
        position: relative;
        margin-bottom: 24px;
    }
    .timeline-item-trail:last-child {
        margin-bottom: 0;
    }
    .timeline-icon-trail {
        position: absolute;
        left: -37px;
        top: 2px;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        color: white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.15);
    }
    .timeline-icon-inbound { background-color: #10b981; }
    .timeline-icon-transfer { background-color: #f59e0b; }
    .timeline-icon-outbound_pss { background-color: #ef4444; }
    .timeline-icon-outbound_commercial { background-color: #6366f1; }

    .timeline-card-trail {
        background: white;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        padding: 12px 16px;
    }
    .timeline-card-trail .time-trail {
        font-size: 0.72rem;
        font-weight: 700;
        color: #94a3b8;
        margin-bottom: 4px;
    }
    .timeline-card-trail .title-trail {
        font-size: 0.88rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .timeline-card-trail .ref-trail {
        font-size: 0.75rem;
        font-weight: 700;
        color: #0f172a;
        background: #f1f5f9;
        padding: 3px 8px;
        border-radius: 6px;
    }
    .timeline-meta-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 8px;
        font-size: 0.78rem;
        color: #475569;
        border-top: 1px solid #f1f5f9;
        padding-top: 8px;
        margin-top: 8px;
    }
    </style>

    <script>
    let batchTrailModal = null;
    function showBatchTrail(batchNo, productId) {
        if (!batchTrailModal) {
            batchTrailModal = new bootstrap.Modal(document.getElementById('batchTrailModal'));
        }
        
        // Clear and show loading state
        document.getElementById('bt-product-name').innerText = "Memuat naik...";
        document.getElementById('bt-batch-no').innerText = "#" + batchNo;
        document.getElementById('bt-category').innerText = "—";
        document.getElementById('bt-barcode').innerText = "—";
        document.getElementById('bt-current-locations-container').innerHTML = '<div class="col-12 text-center text-muted py-3"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Memuatkan butiran batch...</div>';
        document.getElementById('bt-timeline').innerHTML = '';
        
        batchTrailModal.show();
        
        fetch(`api/get_batch_trail.php?batch_no=${encodeURIComponent(batchNo)}&product_id=${productId}`)
            .then(res => {
                if (!res.ok) throw new Error("Gagal mengambil maklumat.");
                return res.json();
            })
            .then(data => {
                if (!data.success) {
                    alert(data.message);
                    batchTrailModal.hide();
                    return;
                }
                
                // Product info
                document.getElementById('bt-product-name').innerText = data.product.name;
                document.getElementById('bt-category').innerText = data.product.category;
                
                const cat = data.product.category;
                let bgClass = "bg-secondary";
                if (cat === 'UHT') bgClass = "bg-primary text-white";
                else if (cat === 'PSS') bgClass = "bg-success text-white";
                else if (cat === 'PST') bgClass = "bg-warning text-dark";
                document.getElementById('bt-category').className = `badge ${bgClass} text-uppercase mb-2 fw-bold`;
                document.getElementById('bt-barcode').innerText = data.product.barcode ? "Barcode: " + data.product.barcode : "Tiada Barcode";
                
                // Placements
                let locHtml = '';
                if (data.current_stock && data.current_stock.length > 0) {
                    data.current_stock.forEach(l => {
                        const loc = l.location_code || 'Tiada Slot';
                        const qty = l.qty_on_hand;
                        const status = l.location_status;
                        const palletType = l.pallet_type || 'None';
                        const tag = l.pallet_id_tag ? "Tag: " + l.pallet_id_tag : "Tiada Tag";
                        
                        let statusClass = "bg-light text-dark";
                        if (status === 'Warehouse') statusClass = "bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25";
                        else if (status === 'Buffer') statusClass = "bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25";
                        else if (status === 'Shop') statusClass = "bg-success bg-opacity-10 text-success border border-success border-opacity-25";
                        else if (status === 'Damaged') statusClass = "bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25";
                        
                        locHtml += `
                            <div class="col-sm-6 col-md-4">
                                <div class="p-3 rounded-3 shadow-xs h-100 ${statusClass}">
                                    <div class="small fw-bold text-uppercase tracking-wider opacity-75">${status}</div>
                                    <div class="fs-4 fw-bold my-1">${loc}</div>
                                    <div class="small fw-bold">Kuantiti: ${qty} CTN</div>
                                    <div class="small opacity-90">${palletType} (${tag})</div>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    locHtml = '<div class="col-12"><div class="alert alert-secondary border-0 text-center py-3 mb-0" style="border-radius:10px;"><i class="bi bi-info-circle me-1"></i>Tiada stok aktif bagi batch ini di dalam gudang (Kuantiti: 0 ctn).</div></div>';
                }
                document.getElementById('bt-current-locations-container').innerHTML = locHtml;
                
                // Timeline
                let timelineHtml = '';
                if (data.timeline && data.timeline.length > 0) {
                    data.timeline.forEach(e => {
                        let icon = '📥';
                        let iconClass = 'timeline-icon-inbound';
                        if (e.type === 'transfer') {
                            icon = '🔄';
                            iconClass = 'timeline-icon-transfer';
                        } else if (e.type === 'outbound_pss') {
                            icon = '📤';
                            iconClass = 'timeline-icon-outbound_pss';
                        } else if (e.type === 'outbound_commercial') {
                            icon = '📤';
                            iconClass = 'timeline-icon-outbound_commercial';
                        }
                        
                        // Format Date
                        let formattedDate = e.timestamp;
                        try {
                            const dateObj = new Date(e.timestamp);
                            formattedDate = dateObj.toLocaleDateString('ms-MY', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                        } catch(err) {}
                        
                        let refHtml = '';
                        if (e.ref) {
                            refHtml = `<span class="ref-trail">${e.ref}</span>`;
                        }
                        
                        let qtyHtml = '';
                        if (e.qty !== null) {
                            qtyHtml = `<div class="fw-bold text-end text-primary" style="font-size:1rem;">${e.qty} CTN</div>`;
                        }
                        
                        let metaHtml = '';
                        if (e.meta) {
                            metaHtml += '<div class="timeline-meta-grid">';
                            for (const [k, v] of Object.entries(e.meta)) {
                                metaHtml += `<div><strong>${k}:</strong> ${v}</div>`;
                            }
                            metaHtml += '</div>';
                        }
                        
                        timelineHtml += `
                            <div class="timeline-item-trail">
                                <div class="timeline-icon-trail ${iconClass}">${icon}</div>
                                <div class="timeline-card-trail">
                                    <div class="time-trail"><i class="bi bi-clock me-1"></i>${formattedDate}</div>
                                    <div class="title-trail">
                                        <span class="fw-bold">${e.title}</span>
                                        ${refHtml}
                                    </div>
                                    ${qtyHtml}
                                    ${metaHtml}
                                </div>
                            </div>
                        `;
                    });
                } else {
                    timelineHtml = '<div class="text-center text-muted py-4"><i class="bi bi-slash-circle fs-3 mb-2 d-block"></i>Tiada sejarah pergerakan direkodkan bagi batch ini.</div>';
                }
                document.getElementById('bt-timeline').innerHTML = timelineHtml;
            })
            .catch(err => {
                console.error(err);
                document.getElementById('bt-current-locations-container').innerHTML = '<div class="col-12 text-center text-danger py-3">Ralat memuatkan data.</div>';
            });
    }
    </script>
</body>
</html>
