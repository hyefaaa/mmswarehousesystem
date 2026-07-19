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
</body>
</html>
