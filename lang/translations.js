/**
 * MMS Warehouse System — Dual Language (EN / MS)
 * Translations cover: navbar, dashboard, inventory_report, stock_transfer,
 *                     login, stock_take, reports, and all shared UI text.
 */

const MMS_TRANSLATIONS = {

    en: {
        // ===== NAVBAR =====
        nav_dashboard:        'Dashboard',
        nav_receiving:        'Receiving',
        nav_single_receive:   'Single Item Receive',
        nav_multi_receive:    'Multi-Item Receive',
        nav_operations:       'Operations',
        nav_commercial_out:   'Commercial Outbound',
        nav_pss_delivery:     'PSS Delivery',
        nav_outbound_hist:    'Outbound History',
        nav_daily_reconcile:  'Daily Reconcile',
        nav_stock_take:       'Stock Take',
        nav_stock_transfer:   'Stock Transfer',
        nav_reports:          'Reports',
        nav_wh_monitor:       'Warehouse Monitor',
        nav_inv_report:       'Inventory Report',
        nav_system:           'System',
        nav_master_data:      'Product Management',
        nav_spoilage_report:  'Spoilage Report',
        nav_spoilage_list:    'Spoilage List',
        nav_user_mgmt:        'User Management',
        nav_audit_logs:       'System Audit Logs',
        nav_logout:           'Log Out',

        // ===== DASHBOARD =====
        dash_title:           'MMS MASTER HUB',
        dash_subtitle:        'Warehouse Management & Logistics Command Center',
        dash_active_skus:     'Active SKUs',
        dash_total_stock:     'Total Stock (Units)',
        dash_pending_spoil:   'Pending Spoilage',
        dash_live_inv:        'Live Inventory',
        dash_action_req:      'Action Required',
        dash_sys_healthy:     'System Healthy',

        dash_expiry_title:    'EXPIRY RISK MONITOR (FEFO CONTROL)',
        dash_low_stock_title: 'LOW STOCK ALERTS (< 50 CTN)',
        dash_product:         'Product',
        dash_batch_no:        'Batch No',
        dash_balance:         'Balance',
        dash_days_left:       'Days Left',
        dash_risk:            'Risk',
        dash_category:        'Category',
        dash_total_balance:   'Total Balance',
        dash_stock_ok:        'All stock levels are at a safe level.',

        // Dashboard Section Titles
        sec_stock_receiving:  'Stock & Receiving',
        sec_operations:       'Operations & Delivery',
        sec_reports_audit:    'Reports & Audit',
        sec_system_pss:       'System & PSS Hub',

        // Dashboard Cards
        card_multi_receive:   'Multi Receive',
        card_multi_receive_d: 'Log incoming batches — multiple products at once.',
        card_single_receive:  'Single Receive',
        card_single_receive_d:'Log one product in with QR scan.',
        card_stock_transfer:  'Stock Transfer',
        card_stock_transfer_d:'Move stock between Warehouse, Buffer, Shop, Damaged.',
        card_stock_take:      'Stock Take',
        card_stock_take_d:    'Count physical stock & adjust system records.',
        card_pss_delivery:    'PSS School Delivery',
        card_pss_delivery_d:  'Process DOs for School Milk project.',
        card_pss_op:          'PSS Operation',
        card_pss_op_d:        'Daily PSS operations management.',
        card_comm_out:        'Commercial Outbound',
        card_comm_out_d:      'Wholesale & retail distribution outbound.',
        card_out_hist:        'Outbound History',
        card_out_hist_d:      'All outbound delivery records.',
        card_inv_report:      'Inventory Report',
        card_inv_report_d:    'Current stock report by location & category.',
        card_wh_monitor:      'Warehouse Monitor',
        card_wh_monitor_d:    'Live balance, traceability & expiry tracking.',
        card_reconcile:       'Daily Reconcile',
        card_reconcile_d:     'Audit system stock vs physical invoice.',
        card_spoilage_rep:    'Spoilage Report',
        card_spoilage_rep_d:  'Log damaged items with photo evidence.',
        card_spoilage_list:   'Spoilage List',
        card_spoilage_list_d: 'View & manage all damage records.',
        card_pss_hub:         'PSS Master Hub',
        card_pss_hub_d:       'Control center, Excel import & trip engine.',
        card_co_import:       'Monthly CO Import',
        card_co_import_d:     'Generate SAP lists from CSV.',
        card_batch_arch:      'Batch Archives',
        card_batch_arch_d:    'History of generated SAP reports.',
        card_master_data:     'Master Data',
        card_master_data_d:   'Manage SKUs, specs and categories.',
        card_user_mgmt:       'User Management',
        card_user_mgmt_d:     'Manage user accounts & permissions.',
        card_sys_logs:        'System Audit Logs',
        card_sys_logs_d:      'Activity records & system audit trail.',
        card_qr_scanner:      'QR Barcode Scanner',
        card_qr_scanner_d:    'Fast scanning of products and pallets.',
        import_master_title:  'Import Master PSS',
        import_master_desc:   'Update school data and master contracts.',

        // ===== LOGIN PAGE =====
        login_title:          'Warehouse & Logistics Management',
        login_username:       'Username',
        login_password:       'Password',
        login_btn:            'LOG IN TO PORTAL',
        login_intranet:       'Intranet Access Only',

        // ===== INVENTORY REPORT =====
        inv_title:            'Inventory Report',
        inv_subtitle:         'Current Stock Report',
        inv_btn_print:        'Print',
        inv_btn_export:       'Export CSV',
        inv_stat_products:    'Total Products',
        inv_stat_stock:       'Total Stock (ctn)',
        inv_stat_low:         'Low Stock (<50)',
        inv_stat_no_stock:    'No Stock',
        inv_filter_title:     'Filter Data',
        inv_filter_cat:       'Category',
        inv_filter_product:   'Product Name',
        inv_filter_loc:       'Location',
        inv_filter_stock:     'Stock Status',
        inv_filter_all:       '-- All --',
        inv_filter_in_stock:  'In Stock Only',
        inv_filter_zero:      'Zero Stock Only',
        inv_filter_btn:       'Search',
        inv_table_title:      'Inventory List',
        inv_col_product:      'Product Name',
        inv_col_category:     'Category',
        inv_col_batch:        'Batch No.',
        inv_col_lot:          'Lot No.',
        inv_col_expiry:       'Expiry Date',
        inv_col_location:     'Location',
        inv_col_stock_ctn:    'Stock (ctn)',
        inv_col_stock_pcs:    'Stock (pcs)',
        inv_col_pallet:       'Pallet Type',
        inv_col_date_in:      'Date In',
        inv_grand_total:      'TOTAL OVERALL STOCK:',
        inv_empty:            'No Data',
        inv_empty_sub:        'No inventory records match your filter.',
        inv_reset:            'Reset Filter',
        inv_expired:          'Expired',
        inv_days_left:        'days left',

        // ===== STOCK TRANSFER =====
        xfer_title:           'Stock Transfer',
        xfer_subtitle:        'Move stock between locations — Warehouse, Buffer, Shop, Damaged',
        xfer_flow_label:      'Stock Flow — Click to filter by location',
        xfer_main_store:      'Main Store',
        xfer_temp_stock:      'Temporary Stock',
        xfer_display:         'Display / Shop',
        xfer_filter_current:  'Current Location',
        xfer_filter_cat:      'Category',
        xfer_filter_product:  'Product Name',
        xfer_filter_status:   'Stock Status',
        xfer_search:          'Search',
        xfer_stock_at:        'Stock at',
        xfer_batches:         'batch(es)',
        xfer_select_all:      'Select All',
        xfer_col_product:     'Product',
        xfer_col_cat:         'Category',
        xfer_col_batch:       'Batch / Lot',
        xfer_col_expiry:      'Expiry',
        xfer_col_stock:       'Available Stock',
        xfer_col_qty:         'Transfer Qty',
        xfer_col_dest:        'Move To',
        xfer_col_reason:      'Reason',
        xfer_col_action:      'Action',
        xfer_btn_move:        'Move',
        xfer_bulk_selected:   'batch(es) selected',
        xfer_bulk_dest:       'Move to:',
        xfer_bulk_reason:     'Reason:',
        xfer_bulk_btn:        'Move All Selected (Full Qty)',
        xfer_empty:           'No Stock at',
        xfer_confirm:         'Confirm stock transfer?',

        // ===== COMMON =====
        btn_save:             'Save',
        btn_cancel:           'Cancel',
        btn_search:           'Search',
        btn_reset:            'Reset',
        btn_back:             'Back',
        btn_confirm:          'Confirm',
        btn_delete:           'Delete',
        btn_edit:             'Edit',
        btn_add:              'Add',
        btn_export:           'Export',
        btn_print:            'Print',
        lbl_loading:          'Loading...',
        lbl_no_data:          'No data found.',
        lbl_success:          'Success',
        lbl_error:            'Error',
        lbl_warning:          'Warning',
        lbl_date:             'Date',
        lbl_remarks:          'Remarks',
        lbl_status:           'Status',
        lbl_action:           'Action',
        lbl_name:             'Name',
        lbl_category:         'Category',
        lbl_qty:              'Quantity',
        lbl_total:            'Total',
        err_category_mismatch: 'Error: You scanned a [{prod_cat}] product, but the active category is [{selected_cat}]. Please select [{prod_cat}] in Step 1 first.',
        err_product_not_registered: 'Error: This product is not registered in the database. Please add its barcode/qrcode in Product Management first.',
        nav_pallet_monitor: 'Pallet Monitor',
        pallet_subtitle: 'Real-time assets tracking, empty pallet stocks & movement logs',
        btn_adjust_pallet: 'Manual Adjustment',
        lbl_pallet_total: 'Total Balance',
        lbl_pallet_loaded: 'Loaded',
        lbl_pallet_empty: 'Empty',
        pallet_history_title: 'Pallet Movement Ledger History',
        lbl_trans_type: 'Trans Type',
        lbl_ref_no: 'Ref No',
        modal_adjust_title: 'Pallet Manual Adjustment',
        lbl_select_pallet: 'Select Pallet Type',
        lbl_adj_type: 'Adjustment Type',
        opt_add_pcs: 'Add (+)',
        opt_sub_pcs: 'Subtract (-)',
        opt_set_total: 'Set Total Balance'
    },

    ms: {
        // ===== NAVBAR =====
        nav_dashboard:        'Papan Pemuka',
        nav_receiving:        'Penerimaan',
        nav_single_receive:   'Terima Satu Item',
        nav_multi_receive:    'Terima Pelbagai Item',
        nav_operations:       'Operasi',
        nav_commercial_out:   'Pengeluaran Komersial',
        nav_pss_delivery:     'Penghantaran PSS',
        nav_outbound_hist:    'Sejarah Pengeluaran',
        nav_daily_reconcile:  'Rekonsiliasi Harian',
        nav_stock_take:       'Kiraan Stok',
        nav_stock_transfer:   'Pindah Stok',
        nav_reports:          'Laporan',
        nav_wh_monitor:       'Monitor Gudang',
        nav_inv_report:       'Laporan Inventori',
        nav_system:           'Sistem',
        nav_master_data:      'Pengurusan Produk',
        nav_spoilage_report:  'Laporan Kerosakan',
        nav_spoilage_list:    'Senarai Kerosakan',
        nav_user_mgmt:        'Pengurusan Pengguna',
        nav_audit_logs:       'Log Audit Sistem',
        nav_logout:           'Log Keluar',

        // ===== DASHBOARD =====
        dash_title:           'MMS MASTER HUB',
        dash_subtitle:        'Pengurusan Gudang & Pusat Kawalan Logistik',
        dash_active_skus:     'SKU Aktif',
        dash_total_stock:     'Jumlah Stok (Unit)',
        dash_pending_spoil:   'Kerosakan Tertunda',
        dash_live_inv:        'Inventori Langsung',
        dash_action_req:      'Tindakan Diperlukan',
        dash_sys_healthy:     'Sistem Normal',

        dash_expiry_title:    'MONITOR RISIKO LUPUT (KAWALAN FEFO)',
        dash_low_stock_title: 'AMARAN STOK RENDAH (< 50 CTN)',
        dash_product:         'Produk',
        dash_batch_no:        'No. Batch',
        dash_balance:         'Baki',
        dash_days_left:       'Hari Lagi',
        dash_risk:            'Risiko',
        dash_category:        'Kategori',
        dash_total_balance:   'Jumlah Baki',
        dash_stock_ok:        'Semua baki stok berada di tahap selamat.',

        // Dashboard Section Titles
        sec_stock_receiving:  'Stok & Penerimaan',
        sec_operations:       'Operasi & Penghantaran',
        sec_reports_audit:    'Laporan & Audit',
        sec_system_pss:       'Sistem & PSS Hub',

        // Dashboard Cards
        card_multi_receive:   'Terima Pelbagai',
        card_multi_receive_d: 'Log batch masuk — pelbagai produk sekaligus.',
        card_single_receive:  'Terima Satu',
        card_single_receive_d:'Log satu produk masuk dengan imbasan QR.',
        card_stock_transfer:  'Pindah Stok',
        card_stock_transfer_d:'Pindah stok antara Gudang, Penampan, Kedai, Rosak.',
        card_stock_take:      'Kiraan Stok',
        card_stock_take_d:    'Kira stok fizikal & laraskan rekod sistem.',
        card_pss_delivery:    'Penghantaran Sekolah PSS',
        card_pss_delivery_d:  'Proses DO untuk projek Susu Sekolah.',
        card_pss_op:          'Operasi PSS',
        card_pss_op_d:        'Pengurusan operasi harian PSS.',
        card_comm_out:        'Pengeluaran Komersial',
        card_comm_out_d:      'Pengedaran borong & runcit keluar.',
        card_out_hist:        'Sejarah Pengeluaran',
        card_out_hist_d:      'Semua rekod penghantaran keluar.',
        card_inv_report:      'Laporan Inventori',
        card_inv_report_d:    'Laporan stok semasa mengikut lokasi & kategori.',
        card_wh_monitor:      'Monitor Gudang',
        card_wh_monitor_d:    'Baki langsung, keterlacakan & penjejakan luput.',
        card_reconcile:       'Rekonsiliasi Harian',
        card_reconcile_d:     'Audit stok sistem vs invois fizikal.',
        card_spoilage_rep:    'Laporan Kerosakan',
        card_spoilage_rep_d:  'Log barangan rosak dengan bukti foto.',
        card_spoilage_list:   'Senarai Kerosakan',
        card_spoilage_list_d: 'Lihat & urus semua rekod kerosakan.',
        card_pss_hub:         'PSS Master Hub',
        card_pss_hub_d:       'Pusat kawalan, import Excel & enjin trip.',
        card_co_import:       'Import CO Bulanan',
        card_co_import_d:     'Jana senarai SAP dari CSV.',
        card_batch_arch:      'Arkib Batch',
        card_batch_arch_d:    'Sejarah laporan SAP yang dijana.',
        card_master_data:     'Data Induk',
        card_master_data_d:   'Urus SKU, spesifikasi & kategori produk.',
        card_user_mgmt:       'Pengurusan Pengguna',
        card_user_mgmt_d:     'Urus akaun & kebenaran pengguna.',
        card_sys_logs:        'Log Audit Sistem',
        card_sys_logs_d:      'Rekod aktiviti & jejak audit sistem.',
        card_qr_scanner:      'Pengimbas Kod QR',
        card_qr_scanner_d:    'Imbas kod bar produk dan pallet secara pantas.',
        import_master_title:  'Import Master PSS',
        import_master_desc:   'Kemas kini data sekolah dan kontrak master.',

        // ===== LOGIN PAGE =====
        login_title:          'Pengurusan Gudang & Logistik',
        login_username:       'Nama Pengguna',
        login_password:       'Kata Laluan',
        login_btn:            'LOG MASUK KE PORTAL',
        login_intranet:       'Akses Intranet Sahaja',

        // ===== INVENTORY REPORT =====
        inv_title:            'Laporan Inventori',
        inv_subtitle:         'Laporan Stok Semasa',
        inv_btn_print:        'Cetak',
        inv_btn_export:       'Eksport CSV',
        inv_stat_products:    'Jumlah Produk',
        inv_stat_stock:       'Jumlah Stok (ctn)',
        inv_stat_low:         'Stok Rendah (<50)',
        inv_stat_no_stock:    'Tiada Stok',
        inv_filter_title:     'Tapis Data',
        inv_filter_cat:       'Kategori',
        inv_filter_product:   'Nama Produk',
        inv_filter_loc:       'Lokasi',
        inv_filter_stock:     'Status Stok',
        inv_filter_all:       '-- Semua --',
        inv_filter_in_stock:  'Ada Stok Sahaja',
        inv_filter_zero:      'Kosong Sahaja',
        inv_filter_btn:       'Cari',
        inv_table_title:      'Senarai Inventori',
        inv_col_product:      'Nama Produk',
        inv_col_category:     'Kategori',
        inv_col_batch:        'No. Batch',
        inv_col_lot:          'No. Lot',
        inv_col_expiry:       'Tarikh Luput',
        inv_col_location:     'Lokasi',
        inv_col_stock_ctn:    'Stok (ctn)',
        inv_col_stock_pcs:    'Stok (pcs)',
        inv_col_pallet:       'Jenis Pallet',
        inv_col_date_in:      'Tarikh Masuk',
        inv_grand_total:      'JUMLAH KESELURUHAN STOK:',
        inv_empty:            'Tiada Data',
        inv_empty_sub:        'Tiada rekod inventori yang sepadan dengan tapisan anda.',
        inv_reset:            'Set Semula',
        inv_expired:          'Tamat Tempoh',
        inv_days_left:        'hari lagi',

        // ===== STOCK TRANSFER =====
        xfer_title:           'Pindah Stok',
        xfer_subtitle:        'Pindah stok antara lokasi — Gudang, Penampan, Kedai, Rosak',
        xfer_flow_label:      'Aliran Stok — Klik untuk tapis mengikut lokasi',
        xfer_main_store:      'Stor Utama',
        xfer_temp_stock:      'Stok Sementara',
        xfer_display:         'Paparan / Kedai',
        xfer_filter_current:  'Lokasi Semasa',
        xfer_filter_cat:      'Kategori',
        xfer_filter_product:  'Nama Produk',
        xfer_filter_status:   'Status Stok',
        xfer_search:          'Cari',
        xfer_stock_at:        'Stok di',
        xfer_batches:         'batch',
        xfer_select_all:      'Pilih Semua',
        xfer_col_product:     'Produk',
        xfer_col_cat:         'Kategori',
        xfer_col_batch:       'Batch / Lot',
        xfer_col_expiry:      'Tarikh Luput',
        xfer_col_stock:       'Stok Ada',
        xfer_col_qty:         'Qty Pindah',
        xfer_col_dest:        'Pindah Ke',
        xfer_col_reason:      'Sebab',
        xfer_col_action:      'Tindakan',
        xfer_btn_move:        'Pindah',
        xfer_bulk_selected:   'batch dipilih',
        xfer_bulk_dest:       'Pindah ke:',
        xfer_bulk_reason:     'Sebab:',
        xfer_bulk_btn:        'Pindah Semua Yang Dipilih (Qty Penuh)',
        xfer_empty:           'Tiada Stok di',
        xfer_confirm:         'Sahkan pindahan stok?',

        // ===== COMMON =====
        btn_save:             'Simpan',
        btn_cancel:           'Batal',
        btn_search:           'Cari',
        btn_reset:            'Set Semula',
        btn_back:             'Kembali',
        btn_confirm:          'Sahkan',
        btn_delete:           'Padam',
        btn_edit:             'Edit',
        btn_add:              'Tambah',
        btn_export:           'Eksport',
        btn_print:            'Cetak',
        lbl_loading:          'Memuatkan...',
        lbl_no_data:          'Tiada data dijumpai.',
        lbl_success:          'Berjaya',
        lbl_error:            'Ralat',
        lbl_warning:          'Amaran',
        lbl_date:             'Tarikh',
        lbl_remarks:          'Catatan',
        lbl_status:           'Status',
        lbl_action:           'Tindakan',
        lbl_name:             'Nama',
        lbl_category:         'Kategori',
        lbl_qty:              'Kuantiti',
        lbl_total:            'Jumlah',
        err_category_mismatch: 'Ralat: Anda mengimbas produk [{prod_cat}], tetapi kategori aktif ialah [{selected_cat}]. Sila pilih [{prod_cat}] di Step 1 terlebih dahulu.',
        err_product_not_registered: 'Ralat: Produk ini tidak didaftarkan di dalam database. Sila daftarkan barcode/qrcode produk ini terlebih dahulu.',
        nav_pallet_monitor: 'Monitor Pallet',
        pallet_subtitle: 'Penjejakan aset langsung, baki pallet kosong & sejarah pergerakan',
        btn_adjust_pallet: 'Pelarasan Manual',
        lbl_pallet_total: 'Baki Keseluruhan',
        lbl_pallet_loaded: 'Berisi Stok',
        lbl_pallet_empty: 'Kosong',
        pallet_history_title: 'Sejarah Lejar Pergerakan Pallet',
        lbl_trans_type: 'Jenis Trans',
        lbl_ref_no: 'No. Rujukan',
        modal_adjust_title: 'Pelarasan Manual Pallet',
        lbl_select_pallet: 'Pilih Jenis Pallet',
        lbl_adj_type: 'Jenis Pelarasan',
        opt_add_pcs: 'Tambah (+)',
        opt_sub_pcs: 'Tolak (-)',
        opt_set_total: 'Set Jumlah Baki'
    }
};

// ============================================================
//  LANGUAGE ENGINE — Auto-apply translations on page load
// ============================================================

const MMS_LANG = {

    // Get current language from localStorage (fallback: 'en')
    current: function () {
        return localStorage.getItem('mms_lang') || 'en';
    },

    // Set language
    set: function (lang) {
        localStorage.setItem('mms_lang', lang);
        this.apply(lang);
        this.updateToggle(lang);

        // Also update server-side session
        fetch('lang_switch.php?lang=' + lang).catch(() => {});
    },

    // Get a single translation string
    t: function (key) {
        const lang = this.current();
        const strings = MMS_TRANSLATIONS[lang] || MMS_TRANSLATIONS['en'];
        return strings[key] || MMS_TRANSLATIONS['en'][key] || key;
    },

    // Apply translations to DOM elements with [data-lang] attributes
    apply: function (lang) {
        const strings = MMS_TRANSLATIONS[lang] || MMS_TRANSLATIONS['en'];

        // Text content elements
        document.querySelectorAll('[data-lang]').forEach(el => {
            const key = el.getAttribute('data-lang');
            if (strings[key]) el.textContent = strings[key];
        });

        // Placeholder elements
        document.querySelectorAll('[data-lang-placeholder]').forEach(el => {
            const key = el.getAttribute('data-lang-placeholder');
            if (strings[key]) el.placeholder = strings[key];
        });

        // Title attributes
        document.querySelectorAll('[data-lang-title]').forEach(el => {
            const key = el.getAttribute('data-lang-title');
            if (strings[key]) el.title = strings[key];
        });
    },

    // Update the toggle button state
    updateToggle: function (lang) {
        const btnEn = document.getElementById('lang-btn-en');
        const btnMs = document.getElementById('lang-btn-ms');
        if (!btnEn || !btnMs) return;

        if (lang === 'ms') {
            btnMs.classList.add('active');
            btnEn.classList.remove('active');
        } else {
            btnEn.classList.add('active');
            btnMs.classList.remove('active');
        }
    },

    // Initialize on page load
    init: function () {
        const lang = this.current();
        this.apply(lang);
        this.updateToggle(lang);
    }
};

// Auto-run on DOM ready
document.addEventListener('DOMContentLoaded', function () {
    MMS_LANG.init();
});
