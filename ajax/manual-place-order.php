<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

$name           = trim($_POST['customer_name'] ?? '');
$mobile         = trim($_POST['customer_mobile'] ?? '');
$mode           = trim($_POST['delivery_mode'] ?? 'delivery');
$apartmentId    = (int)($_POST['apartment_id'] ?? 0);
$division       = trim($_POST['division'] ?? '');
$divisionCharge = (float)($_POST['division_charge'] ?? 0);
$branchId       = (int)($_POST['pickup_branch_id'] ?? 0);
$productsJson   = $_POST['products'] ?? '[]';
$products       = json_decode($productsJson, true);

/* ---------- Validation ---------- */
if ($name === '' || mb_strlen($name) < 2) {
    jsonResponse(false, 'Customer name is required.');
}
if (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
    jsonResponse(false, 'Invalid mobile number.');
}
if (!in_array($mode, ['delivery', 'pickup'], true)) {
    $mode = 'delivery';
}
if (!is_array($products) || count($products) === 0) {
    jsonResponse(false, 'Please add at least one product.');
}

try {
    $apartmentCode = '';
    $apartmentName = '';
    $branchName    = '';

    if ($mode === 'delivery') {
        if ($apartmentId <= 0 || $division === '') {
            jsonResponse(false, 'Please choose apartment + division.');
        }

        $aStmt = $pdo->prepare(
            "SELECT apartment_code, apartment_name, divisions
             FROM apartments
             WHERE id = ? AND status = 1
             LIMIT 1"
        );
        $aStmt->execute([$apartmentId]);
        $apt = $aStmt->fetch(PDO::FETCH_ASSOC);
        if (!$apt) jsonResponse(false, 'Apartment not found.');

        $apartmentCode = $apt['apartment_code'];
        $apartmentName = $apt['apartment_name'];

        /* Validate division + get its charge */
        $divs = json_decode($apt['divisions'] ?? '[]', true);
        $matched = null;
        if (is_array($divs)) {
            foreach ($divs as $d) {
                if (strcasecmp(trim($d['division'] ?? ''), $division) === 0) {
                    $matched = (float)($d['charge'] ?? 0);
                    break;
                }
            }
        }
        if ($matched === null) {
            jsonResponse(false, 'Division is not valid for this apartment.');
        }
        $divisionCharge = $matched;

    } else {
        /* Store pickup */
        $apartmentId    = 0;
        $apartmentCode  = '';
        $apartmentName  = '';
        $division       = '';
        $divisionCharge = 0;

        if ($branchId <= 0) {
            jsonResponse(false, 'Please select a pickup branch.');
        }
        $bStmt = $pdo->prepare("SELECT branch_name FROM settings_branches WHERE id = ? LIMIT 1");
        $bStmt->execute([$branchId]);
        $branchName = $bStmt->fetchColumn() ?: '';
        if ($branchName === '') {
            jsonResponse(false, 'Invalid pickup branch.');
        }
    }

    /* Auto-assign delivery boy for delivery orders */
    $deliveryBoyId = null;
    if ($mode === 'delivery' && $apartmentCode !== '') {
        try {
            $dBoyStmt = $pdo->prepare(
                "SELECT adb.delivery_boy_id
                 FROM apartment_delivery_boys adb
                 INNER JOIN delivery_boys db ON db.id = adb.delivery_boy_id
                 WHERE adb.apartment_code = ?
                   AND db.status = 1
                 LIMIT 1"
            );
            $dBoyStmt->execute([$apartmentCode]);
            $row = $dBoyStmt->fetch(PDO::FETCH_ASSOC);
            if ($row) $deliveryBoyId = (int)$row['delivery_boy_id'];
        } catch (PDOException $e) {}
    }

    /* ---------- Build items ---------- */
    $items = [];
    $subtotal = 0;

    foreach ($products as $p) {
        $qty   = max(1, (int)($p['qty'] ?? 1));
        $price = (float)($p['price'] ?? 0);
        $line  = $price * $qty;
        $subtotal += $line;

        $items[] = [
            'product_id'   => (int)($p['product_id'] ?? 0),
            'code'         => $p['code'] ?? '',
            'name'         => $p['name'] ?? '',
            'image'        => $p['image'] ?? '',
            'variant_id'   => (int)($p['variant_id'] ?? 0),
            'variant_name' => $p['variant_name'] ?? '',
            'variant_qty'  => $p['variant_qty'] ?? '',
            'price'        => $price,
            'qty'          => $qty,
            'line_total'   => $line,
        ];
    }

    $total = $subtotal + ($mode === 'delivery' ? $divisionCharge : 0);

    /* ---------- Order code ---------- */
    $last = $pdo->query("SELECT order_code FROM orders ORDER BY id DESC LIMIT 1")->fetchColumn();
    $nextNum = 1;
    if ($last && preg_match('/(\d+)$/', $last, $m)) {
        $nextNum = (int)$m[1] + 1;
    }
    $orderCode = 'ORD' . str_pad((string)$nextNum, 6, '0', STR_PAD_LEFT);

    /* ---------- Insert ---------- */
    $ins = $pdo->prepare(
        "INSERT INTO orders
            (order_code, delivery_boy_id, customer_name, customer_mobile,
             apartment_id, apartment_code, apartment_name,
             division, division_charge, delivery_mode,
             pickup_branch_id, pickup_branch_name,
             subtotal, total_amount, products_json,
             status, delivery_status, payment_status, created_at)
         VALUES
            (?, ?, ?, ?,
             ?, ?, ?,
             ?, ?, ?,
             ?, ?,
             ?, ?, ?,
             'pending', 'disabled', 'unpaid', NOW())"
    );

    $ins->execute([
        $orderCode,
        $deliveryBoyId,
        $name,
        $mobile,
        $apartmentId > 0 ? $apartmentId : null,
        $apartmentCode,
        $apartmentName,
        $division,
        $divisionCharge,
        $mode,
        $branchId > 0 ? $branchId : null,
        $branchName !== '' ? $branchName : null,
        $subtotal,
        $total,
        json_encode($items, JSON_UNESCAPED_UNICODE),
    ]);

    $orderId = (int)$pdo->lastInsertId();

    jsonResponse(true, 'Order placed.', [
        'order_id'   => $orderId,
        'order_code' => $orderCode,
        'total'      => $total,
        'mode'       => $mode,
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error placing order.');
}