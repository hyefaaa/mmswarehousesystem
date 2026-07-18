<?php
// api/update_hd_names.php
// Updates HD Names in Database from CSV File

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die("Akses dinafikan. Hanya admin dibenarkan.");
}

// Path to your uploaded CSV file (You might need to upload it first via import_schools.php or place it manually)
// For this script, let's assume you upload it via a form or it's in a known location.
// I'll build a simple form here to upload it directly for this specific update task.

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");

    if ($handle === FALSE) {
        die("❌ Error: Cannot open file.");
    }

    try {
        $pdo->beginTransaction();

        // 1. Get Header
        $header = fgetcsv($handle, 1000, ",");
        $header = array_map('trim', $header);
        $header = array_map('strtoupper', $header);

        $idx_hd = array_search('NAMA HD', $header);
        if ($idx_hd === false) $idx_hd = array_search('HD NAME', $header);

        if ($idx_hd === false) {
            die("❌ Error: Could not find 'NAMA HD' column.");
        }

        // 2. Fetch Existing HDs
        $existing_hds = $pdo->query("SELECT id, name FROM hds")->fetchAll(PDO::FETCH_KEY_PAIR);
        // Format: [1 => 'WALI', 2 => 'FIZI', ...]

        $updated_count = 0;
        $inserted_count = 0;
        $processed_names = [];

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $csv_hd_name = strtoupper(trim($data[$idx_hd]));
            
            if (empty($csv_hd_name) || in_array($csv_hd_name, $processed_names)) continue;
            
            $processed_names[] = $csv_hd_name; // Avoid processing same name twice

            // Match Logic
            $matched_id = null;
            foreach ($existing_hds as $id => $db_name) {
                $db_name = strtoupper($db_name);
                // Check if DB name is part of CSV name (e.g. "WALI" in "WALI KHAN")
                // OR if CSV name is part of DB name
                if (strpos($csv_hd_name, $db_name) !== false || strpos($db_name, $csv_hd_name) !== false) {
                    $matched_id = $id;
                    break;
                }
            }

            if ($matched_id) {
                // Update existing
                $stmt = $pdo->prepare("UPDATE hds SET name = ? WHERE id = ?");
                $stmt->execute([$csv_hd_name, $matched_id]);
                $updated_count++;
            } else {
                // Insert new (Only if you want to add new HDs automatically)
                // For now, let's log it or insert it. I'll insert.
                $stmt = $pdo->prepare("INSERT INTO hds (name, status) VALUES (?, 'Active')");
                $stmt->execute([$csv_hd_name]);
                $inserted_count++;
            }
        }

        $pdo->commit();
        echo "<div class='alert alert-success'>✅ Success! Updated: $updated_count, Inserted: $inserted_count HDs.</div>";

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
    fclose($handle);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update HD Names</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h3>🛠 Update HD Names from CSV</h3>
        <p>Upload your <b>school pss.csv</b> file here to rename 'Wali' to 'WALI KHAN' etc.</p>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <input type="file" name="csv_file" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Update Names</button>
        </form>
        <a href="../index.php" class="btn btn-link mt-3">Back to Home</a>
    </div>
</body>
</html>