<?php
// db_migration_slots.php
require_once 'config/db.php';

try {
    // 1. Create the physical slots mapping table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `warehouse_slots` (
      `location_code` varchar(20) NOT NULL,
      `zone` varchar(10) NOT NULL,
      `lane` varchar(5) NOT NULL,
      `row_num` int(11) NOT NULL,
      `batch_id` int(11) DEFAULT NULL,
      PRIMARY KEY (`location_code`),
      KEY `batch_id` (`batch_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    // 2. Seed the exact warehouse layout if empty
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
?>