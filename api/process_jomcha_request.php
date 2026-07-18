<?php
// api/process_jomcha_request.php
// Handles requisition details lookup, approvals, and stock deductions

header('Content-Type: application/json');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? '';
if (!in_array(strtolower($role), ['admin', 'staff', 'intern', 'pss_admin', 'staff_jomcha'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses dinafikan.']);
    exit;
}

$is_admin = (strtolower($role) === 'admin');

// 1. GET HANDLER (Details Lookup)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_details') {
    $request_id = (int)($_GET['request_id'] ?? 0);
    if ($request_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak sah.']);
        exit;
    }

    try {
        // Fetch Request Metadata
        $stmtMeta = $pdo->prepare("SELECT * FROM jomcha_requests WHERE id = ? LIMIT 1");
        $stmtMeta->execute([$request_id]);
        $metadata = $stmtMeta->fetch();

        if (!$metadata) {
            echo json_encode(['success' => false, 'message' => 'Permohonan tidak ditemui.']);
            exit;
        }

        // Fetch Request Items with Warehouse Current Stock
        $stmtItems = $pdo->prepare("
            SELECT ri.id, ri.product_id, ri.qty_requested, p.name as product_name,
                   COALESCE((
                       SELECT SUM(qty_on_hand) 
                       FROM inventory_batches 
                       WHERE product_id = ri.product_id 
                         AND location_status = 'Warehouse' 
                         AND qty_on_hand > 0
                   ), 0) as qty_on_hand
            FROM jomcha_request_items ri
            JOIN products p ON ri.product_id = p.id
            WHERE ri.request_id = ?
        ");
        $stmtItems->execute([$request_id]);
        $items = $stmtItems->fetchAll();

        echo json_encode(['success' => true, 'metadata' => $metadata, 'items' => $items]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 2. POST HANDLER (Approve / Reject Action)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only Admin can approve/reject
    if (!$is_admin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Akses dinafikan. Hanya Admin boleh meluluskan permohonan.']);
        exit;
    }

    // Parse input (handles normal urlencoded form posts)
    $action = $_POST['action'] ?? '';
    $request_id = (int)($_POST['request_id'] ?? 0);

    if ($request_id <= 0 || !in_array($action, ['approve', 'reject'])) {
        echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap atau tidak sah.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Check if request is still pending
        $stmtReq = $pdo->prepare("SELECT * FROM jomcha_requests WHERE id = ? FOR UPDATE");
        $stmtReq->execute([$request_id]);
        $request = $stmtReq->fetch();

        if (!$request) {
            throw new Exception("Permohonan tidak ditemui.");
        }
        if ($request['status'] !== 'Pending') {
            throw new Exception("Permohonan ini telah pun diproses sebelumnya (Status semasa: {$request['status']}).");
        }

        if ($action === 'reject') {
            // REJECT FLOW
            $stmtReject = $pdo->prepare("UPDATE jomcha_requests SET status = 'Rejected', processed_at = NOW(), processed_by = ? WHERE id = ?");
            $stmtReject->execute([$_SESSION['username'], $request_id]);

            if (function_exists('log_system_activity')) {
                log_system_activity("Rejected Jomcha Request", "jomcha_requests", $request_id, "Admin '{$_SESSION['username']}' menolak permohonan stok Jomcha #$request_id.");
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Permohonan telah ditolak.']);
            exit;
        }

        // APPROVE & DEDUCT FLOW
        // 1. Fetch items
        $stmtItems = $pdo->prepare("SELECT * FROM jomcha_request_items WHERE request_id = ?");
        $stmtItems->execute([$request_id]);
        $items = $stmtItems->fetchAll();

        if (empty($items)) {
            throw new Exception("Permohonan tidak mengandungi sebarang item.");
        }

        // 2. Validate aggregate stocks first (Prevent half-done processes)
        foreach ($items as $item) {
            $pid = $item['product_id'];
            $req_qty = $item['qty_requested'];

            $stmtCheck = $pdo->prepare("
                SELECT COALESCE(SUM(qty_on_hand), 0) as total_stock 
                FROM inventory_batches 
                WHERE product_id = ? AND location_status = 'Warehouse' AND qty_on_hand > 0
            ");
            $stmtCheck->execute([$pid]);
            $total_stock = $stmtCheck->fetchColumn();

            if ($total_stock < $req_qty) {
                // Fetch product name
                $stmtProdName = $pdo->prepare("SELECT name FROM products WHERE id = ?");
                $stmtProdName->execute([$pid]);
                $pname = $stmtProdName->fetchColumn() ?: "ID #$pid";
                throw new Exception("Stok tidak mencukupi untuk '$pname'. Diperlukan: $req_qty ctn, Sedia ada: $total_stock ctn.");
            }
        }

        // 3. Create Jomcha Outbound Log Header
        $outbound_date = date('Y-m-d');
        $customer = 'Jomcha Shop';
        $ref_doc = 'REQ-#' . $request_id;
        $vehicle = 'TBA';

        $stmtOutLog = $pdo->prepare("INSERT INTO outbound_logs (date, customer, doc_ref, vehicle, category) VALUES (?, ?, ?, ?, 'Jomcha')");
        $stmtOutLog->execute([$outbound_date, $customer, $ref_doc, $vehicle]);
        $outbound_id = $pdo->lastInsertId();

        // 4. Perform FEFO Deduction for each item & Log outbound items
        foreach ($items as $item) {
            $pid = $item['product_id'];
            $req_qty = $item['qty_requested'];

            // Fetch available batches in Warehouse ordered by Expiry Date (FEFO)
            $stmtBatches = $pdo->prepare("
                SELECT id, batch_no, qty_on_hand 
                FROM inventory_batches 
                WHERE product_id = ? AND location_status = 'Warehouse' AND qty_on_hand > 0 
                ORDER BY expiry_date ASC
            ");
            $stmtBatches->execute([$pid]);
            $batches = $stmtBatches->fetchAll();

            $remaining_qty = $req_qty;
            foreach ($batches as $batch) {
                if ($remaining_qty <= 0) break;
                
                $deduct = min($remaining_qty, $batch['qty_on_hand']);
                
                // Deduct stock from this batch
                $stmtDeduct = $pdo->prepare("UPDATE inventory_batches SET qty_on_hand = qty_on_hand - ? WHERE id = ?");
                $stmtDeduct->execute([$deduct, $batch['id']]);

                // Record line item in outbound details
                $stmtOutItem = $pdo->prepare("INSERT INTO outbound_items (outbound_id, product_id, qty, batch) VALUES (?, ?, ?, ?)");
                $stmtOutItem->execute([$outbound_id, $pid, $deduct, $batch['batch_no']]);

                $remaining_qty -= $deduct;
            }

            if ($remaining_qty > 0) {
                throw new Exception("Sistem mengalami kegagalan semasa menolak stok untuk produk ID #$pid.");
            }
        }

        // 5. Update request status to Approved
        $stmtApprove = $pdo->prepare("UPDATE jomcha_requests SET status = 'Approved', processed_at = NOW(), processed_by = ? WHERE id = ?");
        $stmtApprove->execute([$_SESSION['username'], $request_id]);

        if (function_exists('log_system_activity')) {
            log_system_activity("Approved Jomcha Request", "jomcha_requests", $request_id, "Admin '{$_SESSION['username']}' meluluskan permohonan stok Jomcha #$request_id (Outbound ID: #$outbound_id).");
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Permohonan berjaya diluluskan dan baki stok Warehouse telah ditolak!']);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
