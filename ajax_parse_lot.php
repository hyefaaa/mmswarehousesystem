<?php
// ajax_parse_lot.php
// VERSION 2026: Fixed Regex Patterns for Accurate QR Inbound Parsing

header('Content-Type: application/json');

if (!isset($_GET['lot_no'])) {
    echo json_encode(['status' => 'error', 'message' => 'No lot number provided']);
    exit;
}

$lot = trim($_GET['lot_no']);
$category = isset($_GET['category']) ? trim($_GET['category']) : 'UHT';

// Helper function to extract and clean barcode/QR code variants from scanned input
function getCleanedCodes($input) {
    $codes = [];
    $input = trim($input);
    if ($input === '') return $codes;
    
    // 1. Add raw input
    $codes[] = $input;
    
    // 2. If it contains slashes, get the first part (component 1)
    if (strpos($input, '/') !== false) {
        $parts = explode('/', $input);
        $part0 = trim($parts[0]);
        $codes[] = $part0;
        
        // Strip GGGITN/GGGITNO prefix from first part
        $stripped0 = preg_replace('/^(?:G{1,3}ITN[O0]?)/i', '', $part0);
        $codes[] = $stripped0;
    }
    
    // 3. Strip GGGITN/GGGITNO prefix from raw input itself
    $stripped_raw = preg_replace('/^(?:G{1,3}ITN[O0]?)/i', '', $input);
    $codes[] = $stripped_raw;
    
    // 4. Extract characters between GGGITN and /BAN or similar patterns
    if (preg_match('/GG{1,2}ITN(.*?)\//i', $input, $m)) {
        $codes[] = trim($m[1]);
        $codes[] = preg_replace('/^[O0]/i', '', trim($m[1]));
    }
    if (preg_match('/GG{1,2}ITN(.*?)$/i', $input, $m)) {
        $codes[] = trim($m[1]);
        $codes[] = preg_replace('/^[O0]/i', '', trim($m[1]));
    }
    
    // Clean unique values, filter out empty ones
    $codes = array_filter(array_unique(array_map('trim', $codes)));
    return array_values($codes);
}

// Sediakan struktur data lalai (Default Response)
$response = [
    'status' => 'success',
    'data' => [
        'product_id'   => 0,
        'product_code' => '',
        'category'     => '',
        'pack_size'    => 0,
        'expiry_date'  => '',
        'batch'        => '',
        'qty_pieces'   => 0,
        'pallet_id_short' => '',
        'pallet_raw_code' => ''
    ]
];

// 1. Check if the scanned code is a match for any product barcode or qrcode in the DB
$db_matched = false;
try {
    require_once 'config/db.php';
    
    $search_codes = getCleanedCodes($lot);
    
    if (!empty($search_codes)) {
        // Build placeholders
        $placeholders = implode(',', array_fill(0, count($search_codes), '?'));
        
        // Prepare query checking both barcode and qrcode fields
        $params = array_merge($search_codes, $search_codes);
        $stmt = $pdo->prepare("SELECT id, name, barcode, qrcode, category, pack_size FROM products WHERE (barcode IN ($placeholders) OR qrcode IN ($placeholders)) AND is_active = 1 LIMIT 1");
        $stmt->execute($params);
        
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($prod) {
            $response['data']['product_id'] = (int)$prod['id'];
            $response['data']['product_code'] = !empty($prod['qrcode']) ? $prod['qrcode'] : (!empty($prod['barcode']) ? $prod['barcode'] : '');
            $response['data']['category'] = !empty($prod['category']) ? $prod['category'] : '';
            $response['data']['pack_size'] = !empty($prod['pack_size']) ? (int)$prod['pack_size'] : 0;
            $db_matched = true;
        }
    }
} catch (Exception $e) {
    // Ignore
}

if ($db_matched && strpos($lot, '/') === false) {
    echo json_encode($response);
    exit;
}

// Check if new format (contains /)
if (strpos($lot, '/') !== false) {
    $parts = explode('/', $lot);
    
    // 1. Parse Component 1 (Product & Packaging info)
    $prod_code = '';
    $size = 0;
    $suffix = '';
    if (preg_match('/^(?:G{1,3}ITN[O0]?\d*)([A-Z0-9]+)-?(\d+)([A-Z])(\d+)([A-Z])$/i', trim($parts[0]), $m1)) {
        $prod_code = strtoupper($m1[1]);
        $size      = (int)$m1[2];
        $packaging = strtoupper($m1[3]);
        $pack_size = (int)$m1[4];
        $suffix    = strtoupper($m1[5]);
        
        $response['data']['pack_size'] = $pack_size;
        
        if (empty($response['data']['product_code'])) {
            $response['data']['product_code'] = $prod_code . '-' . $m1[2] . $packaging . $m1[4] . $suffix;
        }
    } else {
        if (empty($response['data']['product_code'])) {
            $response['data']['product_code'] = preg_replace('/^(?:G{1,3}ITN[O0]?)/i', '', trim($lot));
        }
        if (preg_match('/-[0-9]+[A-Z](\d+)([A-Z])$/i', trim($lot), $suffix_m)) {
            $response['data']['pack_size'] = (int)$suffix_m[1];
            $suffix = strtoupper($suffix_m[2]);
        }
    }
    
    // 2. Parse Component 2 (Date, Batch, Pallet)
    if (isset($parts[1])) {
        $comp2_parts = explode('-', trim($parts[1]));
        
        // Expiry Date (first segment, extract 6 digits as date)
        if (isset($comp2_parts[0]) && preg_match('/(\d{2})(\d{2})(\d{2})/', $comp2_parts[0], $date_m)) {
            $year  = "20" . $date_m[1];
            $month = $date_m[2];
            $day   = $date_m[3];
            $response['data']['expiry_date'] = "$day/$month/$year";
        }
        
        // Batch & Pallet segments
        if (count($comp2_parts) === 3) {
            // Batch is segment 2 (e.g., MPD012 or MFC010)
            if (preg_match('/M[PF]([A-Z])(\d+)/i', $comp2_parts[1], $batch_m)) {
                $batch_letter = strtoupper($batch_m[1]);
                $batch_num    = (int)$batch_m[2];
                if ($category === 'PSS' || $suffix === 'F') {
                    $response['data']['batch'] = 'S' . $batch_letter . $batch_num;
                } else {
                    $response['data']['batch'] = $batch_letter . $batch_num;
                }
            } else {
                $response['data']['batch'] = $comp2_parts[1];
            }
            
            // Pallet is segment 3 (e.g., PA010)
            $pallet_raw = $comp2_parts[2];
        } elseif (count($comp2_parts) === 2) {
            // Pallet is segment 2 (e.g., PC018)
            $pallet_raw = $comp2_parts[1];
        } else {
            $pallet_raw = '';
        }
        
        if ($pallet_raw) {
            if (preg_match('/^P([A-Z])(\d+)$/i', $pallet_raw, $pallet_m)) {
                $pallet_prefix = 'P' . strtoupper($pallet_m[1]);
                $pallet_num    = $pallet_m[2];
                $response['data']['pallet_raw_code'] = $pallet_prefix . $pallet_num;
                $response['data']['pallet_id_short']  = $pallet_prefix . (int)$pallet_num;
            } else {
                $response['data']['pallet_raw_code'] = $pallet_raw;
                $response['data']['pallet_id_short']  = $pallet_raw;
            }
        }
    }
    
    // 3. Parse Component 3 (Quantity, e.g. QTY432)
    if (isset($parts[2]) && preg_match('/QTY(\d+)/i', trim($parts[2]), $qty_m)) {
        $response['data']['qty_pieces'] = (int)$qty_m[1];
    } else {
        // Fallback search in entire code
        if (preg_match('/QTY(\d+)/i', $lot, $qty_m)) {
            $response['data']['qty_pieces'] = (int)$qty_m[1];
        }
    }
} else {
    // Old format fallback
    if (preg_match('/QTY(\d+)/i', $lot, $matches)) {
        $response['data']['qty_pieces'] = (int)$matches[1];
    }
    
    if (preg_match('/(?:BAN|BBD|EXP)(\d{6})/i', $lot, $matches)) {
        $exp_raw = $matches[1];
        $year  = "20" . substr($exp_raw, 0, 2);
        $month = substr($exp_raw, 2, 2);
        $day   = substr($exp_raw, 4, 2);
        $response['data']['expiry_date'] = "$day/$month/$year";
    } elseif (preg_match('/(\d{6})-?MF[A-Z]/i', $lot, $matches)) {
        $exp_raw = $matches[1];
        $year  = "20" . substr($exp_raw, 0, 2);
        $month = substr($exp_raw, 2, 2);
        $day   = substr($exp_raw, 4, 2);
        $response['data']['expiry_date'] = "$day/$month/$year";
    }
    
    $prod_code = '';
    // Check old format
    if (preg_match('/(\d{4}[A-Z0-9]*)-?\d{6}-?MF[A-Z]/i', $lot, $matches)) {
        $prod_code = $matches[1];
        if (empty($response['data']['product_code'])) {
            $response['data']['product_code'] = $prod_code; 
        }
    }
    // Check new format without slashes but with full product details
    elseif (preg_match('/^(?:G{1,3}ITN[O0]?\d*)([A-Z0-9]+)-?(\d+)([A-Z])(\d+)([A-Z])/i', $lot, $matches)) {
        $prod_code = strtoupper($matches[1]);
        $size = (int)$matches[2];
        $suffix = strtoupper($matches[5]);
        if (empty($response['data']['product_code'])) {
            $response['data']['product_code'] = $prod_code . '-' . $matches[2] . strtoupper($matches[3]) . $matches[4] . $suffix;
        }
    }
    // Check old format (just prefix)
    elseif (preg_match('/^(?:G{1,3}ITN[O0]?)(.*)/i', $lot, $matches)) {
        $prod_code = strtoupper(trim($matches[1]));
        if (empty($response['data']['product_code'])) {
            $response['data']['product_code'] = $prod_code; 
        }
    }
    
    if (preg_match('/MF([A-Z])(\d+)/i', $lot, $matches)) {
        $batch_letter = strtoupper($matches[1]);
        $batch_num = (int)$matches[2];
        if ($category === 'PSS') {
            $batch_final = 'S' . $batch_letter . $batch_num;
        } else {
            $batch_final = $batch_letter . $batch_num;
        }
        $response['data']['batch'] = $batch_final;
    }
    
    if (preg_match('/(PW|PM|LR|PR|LG|PG|FO|FG|P[A-Z])(\d+)/i', $lot, $matches)) {
        $p_prefix = strtoupper($matches[1]);
        $p_num    = (int)$matches[2];
        $response['data']['pallet_raw_code'] = $p_prefix . $matches[2];
        $response['data']['pallet_id_short']  = $p_prefix . $p_num;
    }
}

echo json_encode($response);
exit;
?>