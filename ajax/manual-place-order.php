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
$paymentMethod  = trim($_POST['payment_method'] ?? 'qr');

if (!in_array($paymentMethod, ['qr', 'wallet'], true)) {
    $paymentMethod = 'qr';
}

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
    /* Ensure wallet column exists */
    try {
        $pdo->exec("ALTER TABLE customers ADD COLUMN wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER division_charge");
    } catch (PDOException $e) { /* ignore */
    }

    /* Ensure simplified container table exists */
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS order_containers (
            id INT(11) NOT NULL AUTO_INCREMENT,
            order_id INT(11) NOT NULL,
            customer_id INT(11) DEFAULT NULL,
            total_containers INT(11) NOT NULL DEFAULT 0,
            container_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
            PRIMARY KEY (id),
            UNIQUE KEY order_unique (order_id),
            KEY customer_id (customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    } catch (PDOException $e) { /* ignore */
    }

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

    /* Auto-assign delivery boy */
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
        } catch (PDOException $e) {
        }
    }

    /* Find customer id (for container mapping) */
    $customerId = null;
    try {
        $ccStmt = $pdo->prepare("SELECT id FROM customers WHERE mobile_number = ? LIMIT 1");
        $ccStmt->execute([$mobile]);
        $cid = $ccStmt->fetchColumn();
        if ($cid) $customerId = (int)$cid;
    } catch (PDOException $e) {
    }

    /* ---------- Build items ---------- */
    $items = [];
    $subtotal = 0;

    $totalContainers = 0;
    $containerAmount = 0;

    foreach ($products as $p) {
        $qty   = max(1, (int)($p['qty'] ?? 1));
        $price = (float)($p['price'] ?? 0);
        $line  = $price * $qty;
        $subtotal += $line;

        $containerEnabled = (int)($p['container_enabled'] ?? 0) === 1 ? 1 : 0;
        $containerPrice   = (float)($p['container_price'] ?? 0);

        $lineContainerAmount = 0;
        if ($containerEnabled && $containerPrice > 0) {
            $lineContainerAmount = $containerPrice * $qty;
            $totalContainers    += $qty;
            $containerAmount    += $lineContainerAmount;
        }

        $items[] = [
            'product_id'           => (int)($p['product_id'] ?? 0),
            'code'                 => $p['code'] ?? '',
            'name'                 => $p['name'] ?? '',
            'image'                => $p['image'] ?? '',
            'variant_id'           => (int)($p['variant_id'] ?? 0),
            'variant_name'         => $p['variant_name'] ?? '',
            'variant_qty'          => $p['variant_qty'] ?? '',
            'price'                => $price,
            'qty'                  => $qty,
            'line_total'           => $line,
            'container_enabled'    => $containerEnabled,
            'container_price'      => $containerEnabled ? $containerPrice : 0,
            'container_line_total' => $lineContainerAmount,
        ];
    }

    /* =====================================================
       APPLY TAX (from settings) — ROUNDED TO WHOLE RUPEE
       - Exclusive : tax = subtotal × rate%   (added on top)
       - Inclusive : tax is inside subtotal   (back-calculated)
       - Delivery charge is NOT taxed
       - Container deposit is NOT taxed
       - Everything rounded to nearest rupee (no decimals)
    ===================================================== */
    $settings   = getSettings($pdo);
    $taxStatus  = (int)($settings['tax_status'] ?? 0);
    $taxRate    = (float)($settings['tax_rate'] ?? 0);
    $taxType    = strtolower($settings['tax_type'] ?? 'exclusive');

    if (!in_array($taxType, ['inclusive', 'exclusive'], true)) {
        $taxType = 'exclusive';
    }

    $taxAmount           = 0.0;
    $deliveryChargeToAdd = ($mode === 'delivery' ? $divisionCharge : 0);

    if ($taxStatus === 1 && $taxRate > 0) {
        if ($taxType === 'inclusive') {
            $rawTax    = $subtotal - ($subtotal / (1 + ($taxRate / 100)));
            $taxAmount = round($rawTax);
            $total     = $subtotal + $deliveryChargeToAdd;
        } else {
            $rawTax    = $subtotal * ($taxRate / 100);
            $taxAmount = round($rawTax);
            $total     = $subtotal + $deliveryChargeToAdd + $taxAmount;
        }
    } else {
        $taxRate   = 0.0;
        $taxAmount = 0.0;
        $total     = $subtotal + $deliveryChargeToAdd;
    }

    /* Round the total to a whole rupee (drives QR + wallet + DB) */
    $total = round($total);

    /* ---------- Order code ---------- */
    $last = $pdo->query("SELECT order_code FROM orders ORDER BY id DESC LIMIT 1")->fetchColumn();
    $nextNum = 1;
    if ($last && preg_match('/(\d+)$/', $last, $m)) {
        $nextNum = (int)$m[1] + 1;
    }
    $orderCode = 'ORD' . str_pad((string)$nextNum, 6, '0', STR_PAD_LEFT);

    /* ---------- Wallet or QR ---------- */
    $paymentStatus = 'unpaid';
    $paidAt        = null;
    $walletInfo    = null;
    $upiString     = '';

    if ($paymentMethod === 'wallet') {

        $pdo->beginTransaction();

        try {
            $cStmt = $pdo->prepare(
                "SELECT id, full_name, wallet_balance
                 FROM customers
                 WHERE mobile_number = ?
                 LIMIT 1
                 FOR UPDATE"
            );
            $cStmt->execute([$mobile]);
            $cust = $cStmt->fetch(PDO::FETCH_ASSOC);

            if (!$cust) {
                $pdo->rollBack();
                jsonResponse(false, 'No wallet account found for this mobile. Please use QR payment.');
            }

            $walletBalance = (float)$cust['wallet_balance'];

            if ($walletBalance < $total) {
                $pdo->rollBack();
                jsonResponse(
                    false,
                    'Insufficient wallet balance. Available: ₹' . number_format($walletBalance, 0) .
                        ' · Required: ₹' . number_format($total, 0)
                );
            }

            $newBalance = $walletBalance - $total;

            $upd = $pdo->prepare(
                "UPDATE customers
                 SET wallet_balance = ?, updated_at = NOW()
                 WHERE id = ?"
            );
            $upd->execute([$newBalance, (int)$cust['id']]);

            try {
                $txnCode = 'TXN' . date('ymd') . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);

                $log = $pdo->prepare(
                    "INSERT INTO customer_wallet_transactions
                        (customer_id, customer_name, customer_mobile, txn_code,
                         txn_type, amount, balance_before, balance_after,
                         source, note, created_by_id, created_by_name, created_at)
                     VALUES (?, ?, ?, ?, 'debit', ?, ?, ?, 'admin', ?, ?, ?, NOW())"
                );
                $log->execute([
                    (int)$cust['id'],
                    $name,
                    $mobile,
                    $txnCode,
                    $total,
                    $walletBalance,
                    $newBalance,
                    'Manual order ' . $orderCode,
                    (int)$_SESSION['admin_id'],
                    $_SESSION['admin_name'] ?? 'Admin'
                ]);
            } catch (PDOException $e) { /* ignore */
            }

            $paymentStatus = 'paid';
            $paidAt        = date('Y-m-d H:i:s');
            $customerId    = (int)$cust['id'];
            $walletInfo = [
                'balance_before' => $walletBalance,
                'balance_after'  => $newBalance,
                'amount_paid'    => $total,
            ];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(false, 'Wallet deduction failed.');
        }
    } else {
        /* QR — build UPI string with WHOLE-RUPEE amount */
        $upiId    = trim($settings['upi_id'] ?? '');
        $siteName = trim($settings['username'] ?? 'Mrs Mill@');

        if ($upiId === '') {
            jsonResponse(false, 'UPI ID is not configured. Please contact admin.');
        }

        $upiParams = [
            'pa' => $upiId,
            'pn' => $siteName,
            'am' => (string)round($total),   // ← whole rupee (no ".00")
            'cu' => 'INR',
            'tn' => 'Order ' . $orderCode
        ];
        $upiString = 'upi://pay?' . http_build_query($upiParams);
    }

    /* ---------- Insert order ---------- */
    $ins = $pdo->prepare(
        "INSERT INTO orders
            (order_code, delivery_boy_id, customer_name, customer_mobile,
             apartment_id, apartment_code, apartment_name,
             division, division_charge,
             tax_amount, tax_rate, tax_type,
             delivery_mode,
             pickup_branch_id, pickup_branch_name,
             subtotal, total_amount, products_json,
             status, delivery_status, payment_status, payment_upi_string, paid_at, created_at)
         VALUES
            (?, ?, ?, ?,
             ?, ?, ?,
             ?, ?,
             ?, ?, ?,
             ?,
             ?, ?,
             ?, ?, ?,
             'pending', 'disabled', ?, ?, ?, NOW())"
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
        $taxAmount,
        $taxRate,
        $taxType,
        $mode,
        $branchId > 0 ? $branchId : null,
        $branchName !== '' ? $branchName : null,
        $subtotal,
        $total,
        json_encode($items, JSON_UNESCAPED_UNICODE),
        $paymentStatus,
        $upiString !== '' ? $upiString : null,
        $paidAt,
    ]);

    $orderId = (int)$pdo->lastInsertId();

    /* ---------- Insert container row ---------- */
    if ($totalContainers > 0) {
        try {
            $cIns = $pdo->prepare(
                "INSERT INTO order_containers
                    (order_id, customer_id, total_containers, container_amount, created_at)
                 VALUES (?, ?, ?, ?, NOW())"
            );
            $cIns->execute([
                $orderId,
                $customerId,
                $totalContainers,
                $containerAmount,
            ]);
        } catch (PDOException $e) { /* ignore */
        }
    }

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }

    jsonResponse(true, 'Order placed.', [
        'order_id'         => $orderId,
        'order_code'       => $orderCode,
        'subtotal'         => $subtotal,
        'tax_amount'       => $taxAmount,
        'tax_rate'         => $taxRate,
        'tax_type'         => $taxType,
        'tax_status'       => $taxStatus,
        'total'            => $total,
        'mode'             => $mode,
        'payment_method'   => $paymentMethod,
        'payment_status'   => $paymentStatus,
        'upi_string'       => $upiString,
        'wallet'           => $walletInfo,
        'total_containers' => $totalContainers,
        'container_amount' => $containerAmount,
        'has_containers'   => $totalContainers > 0,
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(false, 'Server error placing order.');
}
