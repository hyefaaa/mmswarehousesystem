<?php
// api/save_delivery.php
// Skrip pemprosesan penghantaran PSS sekolah (Penyediaan DO)
// Ditulis dengan kawalan transaksi PDO dan keselamatan pemotongan stok

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!file_exists('../config/db.php')) {
    die("❌ Configuration File Not Found.");
}
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die("❌ Error: Sesi tidak sah. Sila log masuk semula.");
}

function convertDate($dateStr) {
    if (empty($dateStr)) return null;
    if (strpos($dateStr, '-') !== false) return $dateStr;
    $date = DateTime::createFromFormat('d/m/Y', $dateStr);
    return $date ? $date->format('Y-m-d') : null;
}

try {
    $pdo->beginTransaction();

    // Dapatkan parameter input
    $co_number          = $_POST['co_number'] ?? '';
    $hd_id              = !empty($_POST['hd_id']) ? (int)$_POST['hd_id'] : null;
    $school_id          = !empty($_POST['school_id']) ? (int)$_POST['school_id'] : null;
    $inventory_batch_id = !empty($_POST['inventory_batch_id']) ? (int)$_POST['inventory_batch_id'] : null;
    $qty_cartons        = !empty($_POST['qty']) ? (int)$_POST['qty'] : 0;
    $vehicle_plate      = strtoupper(trim($_POST['vehicle_plate'] ?? ''));
    $delivery_date_raw  = $_POST['delivery_date'] ?? date('d/m/Y');
    $delivery_date      = convertDate($delivery_date_raw);

    if (empty($school_id) || empty($inventory_batch_id) || $qty_cartons <= 0) {
        throw new Exception("Parameter input tidak lengkap atau tidak sah (School, Batch, atau Kuantiti kosong).");
    }

    if (empty($vehicle_plate) || $vehicle_plate === 'TBA') {
        throw new Exception("No. Plat Kenderaan (Vehicle Plate) wajib diisi dan tidak boleh 'TBA'.");
    }

    // 1. Dapatkan maklumat Sekolah (kod sekolah)
    $stmtSchool = $pdo->prepare("SELECT school_code FROM schools WHERE id = ? LIMIT 1");
    $stmtSchool->execute([$school_id]);
    $school_code = $stmtSchool->fetchColumn();

    if (!$school_code) {
        throw new Exception("Sekolah tidak ditemui di dalam pangkalan data induk.");
    }

    // 2. Semak dan potong stok daripada inventory_batches
    $stmtBatch = $pdo->prepare("SELECT qty_on_hand, batch_no FROM inventory_batches WHERE id = ? FOR UPDATE");
    $stmtBatch->execute([$inventory_batch_id]);
    $batch = $stmtBatch->fetch();

    if (!$batch) {
        throw new Exception("Batch inventori tidak wujud.");
    }

    if ($batch['qty_on_hand'] < $qty_cartons) {
        throw new Exception("Stok tidak mencukupi untuk Batch " . $batch['batch_no'] . " (Sedia ada: " . $batch['qty_on_hand'] . " ctn, Diperlukan: " . $qty_cartons . " ctn).");
    }

    // Tolak stok karton
    $stmtDeduct = $pdo->prepare("UPDATE inventory_batches SET qty_on_hand = qty_on_hand - ? WHERE id = ?");
    $stmtDeduct->execute([$qty_cartons, $inventory_batch_id]);

    // 3. Dapatkan co_cycle_id dari kitaran co_cycles berdasarkan nama kitaran
    $co_cycle_id = null;
    if (!empty($co_number)) {
        $stmtCycle = $pdo->prepare("SELECT id FROM co_cycles WHERE name = ? LIMIT 1");
        $stmtCycle->execute([$co_number]);
        $co_cycle_id = $stmtCycle->fetchColumn() ?: null;
    }

    // --- LOGIK AUTOMASASI PALET (PALLET LEDGER) ---
    $pallets_red    = isset($_POST['pallets_red'])    ? (int)$_POST['pallets_red']    : 0;
    $pallets_green  = isset($_POST['pallets_green'])  ? (int)$_POST['pallets_green']  : 0;
    $pallets_orange = isset($_POST['pallets_orange']) ? (int)$_POST['pallets_orange'] : 0;

    // Jika kesemua palet bernilai 0 (cth: dari quick DO form di pss_delivery.php), kira secara automatik!
    if ($pallets_red === 0 && $pallets_green === 0 && $pallets_orange === 0) {
        $stmtProdInfo = $pdo->prepare("
            SELECT p.pallet_capacity, b.pallet_type 
            FROM inventory_batches b 
            JOIN products p ON b.product_id = p.id 
            WHERE b.id = ? 
            LIMIT 1
        ");
        $stmtProdInfo->execute([$inventory_batch_id]);
        $prod_info = $stmtProdInfo->fetch();

        if ($prod_info) {
            $pallet_capacity = !empty($prod_info['pallet_capacity']) ? (int)$prod_info['pallet_capacity'] : 144;
            $p_type          = !empty($prod_info['pallet_type']) ? $prod_info['pallet_type'] : '';

            $calc_plt = ceil($qty_cartons / $pallet_capacity);

            if ($p_type === 'Loscam Red') {
                $pallets_red = $calc_plt;
            } elseif ($p_type === 'FFM Green' || $p_type === 'LHP Green') {
                $pallets_green = $calc_plt;
            } elseif ($p_type === 'FFM Orange') {
                $pallets_orange = $calc_plt;
            }
        }
    }

    // 4. Masukkan rekod penghantaran ke deliveries_pss (termasuk palet keluar)
    $stmtInsertDO = $pdo->prepare("INSERT INTO deliveries_pss 
        (do_number, delivery_date, hd_id, vehicle_plate, school_id, co_cycle_id, status, pallets_out_red, pallets_out_green, pallets_out_orange) 
        VALUES ('PENDING', ?, ?, ?, ?, ?, 'Delivered', ?, ?, ?)");
    $stmtInsertDO->execute([$delivery_date, $hd_id, $vehicle_plate, $school_id, $co_cycle_id, $pallets_red, $pallets_green, $pallets_orange]);
    $delivery_id = $pdo->lastInsertId();

    // 5. Bina DO Number unik berasaskan ID transaksi dan tarikh
    $do_number = "DO-PSS-" . date('Ymd', strtotime($delivery_date)) . "-" . sprintf("%04d", $delivery_id);
    
    // Kemaskini DO Number pada deliveries_pss
    $stmtUpdateDO = $pdo->prepare("UPDATE deliveries_pss SET do_number = ? WHERE id = ?");
    $stmtUpdateDO->execute([$do_number, $delivery_id]);

    // Merekodkan transaksi palet keluar ke dalam lejar pallet (pallet_ledger)
    $pallet_out_map = [
        'red' => $pallets_red,
        'lhp' => $pallets_green,
        'orange' => $pallets_orange
    ];

    $stmtLedger = $pdo->prepare("INSERT INTO pallet_ledger 
        (transaction_date, transaction_type, pallet_code, qty, reference_no, notes) 
        VALUES (NOW(), 'OUT', ?, ?, ?, ?)");

    foreach ($pallet_out_map as $p_code => $p_qty) {
        if ($p_qty > 0) {
            $stmtLedger->execute([
                $p_code,
                $p_qty,
                $do_number,
                "School Delivery (DO ID: $delivery_id, HD: $hd_id)"
            ]);
        }
    }

    // 6. Rekod item DO ke delivery_items_pss
    $stmtInsertItem = $pdo->prepare("INSERT INTO delivery_items_pss 
        (delivery_id, inventory_batch_id, qty_cartons) 
        VALUES (?, ?, ?)");
    $stmtInsertItem->execute([$delivery_id, $inventory_batch_id, $qty_cartons]);

    // 7. Kemaskini status logistik sekolah di mms_logistik
    // Nota: 'id' di dalam mms_logistik sepadan dengan kod sekolah (school_code)
    $stmtLogistik = $pdo->prepare("UPDATE mms_logistik 
        SET isDelivered = 1, delivery_date = ? 
        WHERE id = ?");
    $stmtLogistik->execute([$delivery_date, $school_code]);

    // Rekod log audit sistem
    if (function_exists('log_system_activity')) {
        log_system_activity("Created PSS DO", "deliveries_pss", $delivery_id, "DO PSS dicipta: $do_number bagi Sekolah $school_code ($qty_cartons ctn) bertarikh $delivery_date.");
    }

    $pdo->commit();

    echo "<script>
        alert('✅ DO PSS Berjaya Dicipta! (DO No: $do_number)');
        window.location.href = '../pss_delivery.php?co=' + encodeURIComponent('$co_number');
    </script>";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("❌ Ralat Menyimpan DO PSS: " . $e->getMessage());
}
?>
