<?php
/* =========================================================
   MRS MILL@ — AJAX: PLACE MANUAL ORDER
   File: ./ajax/manual-place-order.php
   Orders table stores customer_id as VARCHAR (MRSMCxxxxx).
   Customer must already be registered.

   Delivery boy resolution (priority order):
     1. POST delivery_boy_id  → if valid & active, use it (manual override)
     2. Auto-assign:
         - Apartment delivery        → lookup APTxxx in apartment_delivery_boys
         - Individual House delivery → lookup IH_<md5(trim(full_address))>
         - Pickup                    → null
     3. null
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

$mobile         = trim($_POST['customer_mobile'] ?? '');
$mode           = trim($_POST['delivery_mode'] ?? 'delivery');
$addressType    = trim($_POST['address_type'] ?? 'apartment');
$apartmentId    = (int)($_POST['apartment_id'] ?? 0);
$division       = trim($_POST['division'] ?? '');
$divisionCharge = (float)($_POST['division_charge'] ?? 0);
$fullAddress    = trim($_POST['full_address'] ?? '');
$mapLink        = trim($_POST['map_link'] ?? '');
$branchId       = (int)($_POST['pickup_branch_id'] ?? 0);
$productsJson   = $_POST['products'] ?? '[]';
$products       = json_decode($productsJson, true);
$paymentMethod  = trim($_POST['payment_method'] ?? 'qr');
$forcedBoyId    = (int)($_POST['delivery_boy_id'] ?? 0);   // ← manual override

if (!in_array($paymentMethod, ['qr', 'wallet'], true)) $paymentMethod = 'qr';
if (!in_array($addressType, ['apartment', 'individual_house'], true)) $addressType = 'apartment';
if (!in_array($mode, ['delivery', 'pickup'], true)) $mode = 'delivery';
if (!preg_match('/^[0-9]{10,15}$/', $mobile)) jsonResponse(false, 'Invalid mobile number.');
if (!is_array($products) || count($products) === 0) jsonResponse(false, 'Please add at least one product.');

try {
    /* Lookup customer by mobile */
    $cStmt = $pdo->prepare(
        "SELECT id, customer_id, full_name, mobile_number, wallet_balance, address_json
         FROM customers WHERE mobile_number = ? LIMIT 1"
    );
    $cStmt->execute([$mobile]);
    $customer = $cStmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        jsonResponse(false, 'Customer not found. Customer must register first.');
    }

    $customerRowId = (int)$customer['id'];              // integer primary key
    $customerId    = (string)$customer['customer_id'];  // varchar MRSMCxxxxx

    /* ---------- Address JSON ---------- */
    $addressJson   = [];
    $branchName    = '';
    $apartmentCode = '';
    $apartmentName = '';

    if ($mode === 'delivery') {
        if ($addressType === 'apartment') {
            if ($apartmentId <= 0 || $division === '') {
                jsonResponse(false, 'Please choose apartment + division.');
            }

            $aStmt = $pdo->prepare(
                "SELECT apartment_code, apartment_name, divisions
                 FROM apartments WHERE id = ? AND status = 1 LIMIT 1"
            );
            $aStmt->execute([$apartmentId]);
            $apt = $aStmt->fetch(PDO::FETCH_ASSOC);
            if (!$apt) jsonResponse(false, 'Apartment not found.');

            $apartmentCode = $apt['apartment_code'];
            $apartmentName = $apt['apartment_name'];

            $block = ''; $door = ''; $floor = '';
            foreach (array_map('trim', explode('/', $division)) as $p) {
                if (stripos($p, 'Blk ') === 0) $block = trim(substr($p, 4));
                elseif (stripos($p, 'Door ') === 0) $door = trim(substr($p, 5));
                elseif (stripos($p, 'Fl ') === 0) $floor = trim(substr($p, 3));
            }

            $divs = json_decode($apt['divisions'] ?? '[]', true);
            $matched = null;
            if (is_array($divs)) {
                foreach ($divs as $d) {
                    $parts = [];
                    if (!empty($d['block']))   $parts[] = "Blk " . $d['block'];
                    if (!empty($d['door_no'])) $parts[] = "Door " . $d['door_no'];
                    if (!empty($d['floor']))   $parts[] = "Fl " . $d['floor'];
                    $label = $parts ? implode(" / ", $parts) : (string)($d['division'] ?? "");

                    if (strcasecmp($label, $division) === 0 ||
                        strcasecmp((string)($d['division'] ?? ''), $division) === 0) {
                        $matched = (float)($d['charge'] ?? 0);
                        break;
                    }
                }
            }
            if ($matched === null) jsonResponse(false, 'Division is not valid for this apartment.');
            $divisionCharge = $matched;

            $addressJson = [
                'apartment_id'   => $apartmentId,
                'apartment_code' => $apartmentCode,
                'apartment_name' => $apartmentName,
                'block'          => $block,
                'door_no'        => $door,
                'floor'          => $floor,
                'division_label' => $division,
                'charge'         => $divisionCharge,
            ];

        } else {
            /* individual_house */
            if ($fullAddress === '') jsonResponse(false, 'Full address is required for individual house delivery.');

            $apartmentId    = 0;
            $apartmentCode  = '';
            $apartmentName  = '';
            $division       = '';
            $divisionCharge = 0;

            $houseImage = '';
            $custAddr = json_decode($customer['address_json'] ?? '{}', true);
            if (is_array($custAddr) && !empty($custAddr['house_image'])) {
                $houseImage = $custAddr['house_image'];
            }

            $addressJson = [
                'full_address' => $fullAddress,
                'house_image'  => $houseImage,
            ];
        }

    } else {
        /* pickup */
        $apartmentId    = 0;
        $apartmentCode  = '';
        $apartmentName  = '';
        $division       = '';
        $divisionCharge = 0;

        if ($branchId <= 0) jsonResponse(false, 'Please select a pickup branch.');
        $bStmt = $pdo->prepare("SELECT branch_name FROM settings_branches WHERE id = ? LIMIT 1");
        $bStmt->execute([$branchId]);
        $branchName = $bStmt->fetchColumn() ?: '';
        if ($branchName === '') jsonResponse(false, 'Invalid pickup branch.');

        $addressJson = [
            'branch_id'   => $branchId,
            'branch_name' => $branchName,
        ];

        $addressType = 'pickup';
    }

    $addressJsonFinal = json_encode($addressJson, JSON_UNESCAPED_UNICODE);

    /* =========================================================
       DELIVERY BOY RESOLUTION
       1) Manual override (POST delivery_boy_id) if valid + active
       2) Auto-assign by address
       3) null
       ========================================================= */
    $deliveryBoyId = null;

    if ($mode === 'delivery') {

        /* ---- 1) Manual override ---- */
        if ($forcedBoyId > 0) {
            try {
                $chk = $pdo->prepare(
                    "SELECT id FROM delivery_boys WHERE id = ? AND status = 1 LIMIT 1"
                );
                $chk->execute([$forcedBoyId]);
                if ($chk->fetch()) {
                    $deliveryBoyId = $forcedBoyId;
                }
            } catch (PDOException $e) {
                /* fall through to auto-assign */
            }
        }

        /* ---- 2) Auto-assign by address (only if override didn't stick) ---- */
        if ($deliveryBoyId === null) {

            $lookupCode = '';

            if ($addressType === 'apartment' && $apartmentCode !== '') {
                $lookupCode = $apartmentCode;
            } elseif ($addressType === 'individual_house' && $fullAddress !== '') {
                $lookupCode = 'IH_' . substr(md5(trim($fullAddress)), 0, 12);
            }

            if ($lookupCode !== '') {
                try {
                    $dBoyStmt = $pdo->prepare(
                        "SELECT adb.delivery_boy_id
                         FROM apartment_delivery_boys adb
                         INNER JOIN delivery_boys db ON db.id = adb.delivery_boy_id
                         WHERE adb.apartment_code = ?
                           AND db.status = 1
                         ORDER BY adb.id ASC
                         LIMIT 1"
                    );
                    $dBoyStmt->execute([$lookupCode]);
                    $row = $dBoyStmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $deliveryBoyId = (int)$row['delivery_boy_id'];
                    }
                } catch (PDOException $e) {
                    /* silent */
                }
            }
        }
    }

    /* Build items */
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

    /* Tax */
    $settings   = getSettings($pdo);
    $taxStatus  = (int)($settings['tax_status'] ?? 0);
    $taxRate    = (float)($settings['tax_rate'] ?? 0);
    $taxType    = strtolower($settings['tax_type'] ?? 'exclusive');
    if (!in_array($taxType, ['inclusive', 'exclusive'], true)) $taxType = 'exclusive';

    $taxAmount = 0.0;
    $deliveryChargeToAdd = ($mode === 'delivery' ? $divisionCharge : 0);

    if ($taxStatus === 1 && $taxRate > 0) {
        if ($taxType === 'inclusive') {
            $taxAmount = round($subtotal - ($subtotal / (1 + ($taxRate / 100))));
            $total = $subtotal + $deliveryChargeToAdd;
        } else {
            $taxAmount = round($subtotal * ($taxRate / 100));
            $total = $subtotal + $deliveryChargeToAdd + $taxAmount;
        }
    } else {
        $taxRate = 0.0;
        $taxAmount = 0.0;
        $total = $subtotal + $deliveryChargeToAdd;
    }
    $total = round($total);

    /* Order code */
    $last = $pdo->query("SELECT order_code FROM orders ORDER BY id DESC LIMIT 1")->fetchColumn();
    $nextNum = 1;
    if ($last && preg_match('/(\d+)$/', $last, $m)) $nextNum = (int)$m[1] + 1;
    $orderCode = 'ORD' . str_pad((string)$nextNum, 6, '0', STR_PAD_LEFT);

    /* Payment */
    $paymentStatus = 'unpaid';
    $paidAt        = null;
    $walletInfo    = null;
    $upiString     = '';

    if ($paymentMethod === 'wallet') {
        $pdo->beginTransaction();
        try {
            $wStmt = $pdo->prepare(
                "SELECT id, full_name, wallet_balance FROM customers
                 WHERE id = ? LIMIT 1 FOR UPDATE"
            );
            $wStmt->execute([$customerRowId]);
            $cust = $wStmt->fetch(PDO::FETCH_ASSOC);

            $walletBalance = (float)$cust['wallet_balance'];
            if ($walletBalance < $total) {
                $pdo->rollBack();
                jsonResponse(false, 'Insufficient wallet. Available: ₹' . number_format($walletBalance, 0) .
                    ' · Required: ₹' . number_format($total, 0));
            }

            $newBalance = $walletBalance - $total;

            $pdo->prepare("UPDATE customers SET wallet_balance = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$newBalance, $customerRowId]);

            try {
                $txnCode = 'TXN' . date('ymd') . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                $pdo->prepare(
                    "INSERT INTO customer_wallet_transactions
                        (customer_id, customer_name, customer_mobile, txn_code,
                         txn_type, amount, balance_before, balance_after,
                         source, note, created_by_id, created_by_name, created_at)
                     VALUES (?, ?, ?, ?, 'debit', ?, ?, ?, 'admin', ?, ?, ?, NOW())"
                )->execute([
                    $customerRowId, $customer['full_name'], $customer['mobile_number'], $txnCode,
                    $total, $walletBalance, $newBalance,
                    'Manual order ' . $orderCode,
                    (int)$_SESSION['admin_id'], $_SESSION['admin_name'] ?? 'Admin',
                ]);
            } catch (PDOException $e) {}

            $paymentStatus = 'paid';
            $paidAt        = date('Y-m-d H:i:s');
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
        $upiId    = trim($settings['upi_id'] ?? '');
        $siteName = trim($settings['username'] ?? 'Mrs Mill@');

        if ($upiId === '') jsonResponse(false, 'UPI ID is not configured.');

        $upiParams = [
            'pa' => $upiId,
            'pn' => $siteName,
            'am' => (string)round($total),
            'cu' => 'INR',
            'tn' => 'Order ' . $orderCode,
        ];
        $upiString = 'upi://pay?' . http_build_query($upiParams);
    }

    /* Insert order */
    $ins = $pdo->prepare(
        "INSERT INTO orders
            (order_code, customer_id, delivery_boy_id,
             address_type, address_json,
             delivery_mode,
             pickup_branch_id, pickup_branch_name,
             division_charge,
             tax_amount, tax_rate, tax_type,
             subtotal, total_amount, products_json,
             status, delivery_status, payment_status, payment_upi_string, paid_at, created_at)
         VALUES
            (?, ?, ?,
             ?, ?,
             ?,
             ?, ?,
             ?,
             ?, ?, ?,
             ?, ?, ?,
             'pending', 'disabled', ?, ?, ?, NOW())"
    );

    $ins->execute([
        $orderCode,
        $customerId,
        $deliveryBoyId,
        $addressType,
        $addressJsonFinal,
        $mode,
        $branchId > 0 ? $branchId : null,
        $branchName !== '' ? $branchName : null,
        $divisionCharge,
        $taxAmount,
        $taxRate,
        $taxType,
        $subtotal,
        $total,
        json_encode($items, JSON_UNESCAPED_UNICODE),
        $paymentStatus,
        $upiString !== '' ? $upiString : null,
        $paidAt,
    ]);

    $orderId = (int)$pdo->lastInsertId();

    /* Containers */
    if ($totalContainers > 0) {
        try {
            $pdo->prepare(
                "INSERT INTO order_containers
                    (order_id, customer_id, total_containers, container_amount, created_at)
                 VALUES (?, ?, ?, ?, NOW())"
            )->execute([$orderId, $customerRowId, $totalContainers, $containerAmount]);
        } catch (PDOException $e) {}
    }

    if ($pdo->inTransaction()) $pdo->commit();

    jsonResponse(true, 'Order placed.', [
        'order_id'         => $orderId,
        'order_code'       => $orderCode,
        'customer_id'      => $customerId,
        'customer_name'    => $customer['full_name'],
        'customer_mobile'  => $customer['mobile_number'],
        'subtotal'         => $subtotal,
        'tax_amount'       => $taxAmount,
        'tax_rate'         => $taxRate,
        'tax_type'         => $taxType,
        'tax_status'       => $taxStatus,
        'total'            => $total,
        'mode'             => $mode,
        'address_type'     => $addressType,
        'payment_method'   => $paymentMethod,
        'payment_status'   => $paymentStatus,
        'upi_string'       => $upiString,
        'wallet'           => $walletInfo,
        'total_containers' => $totalContainers,
        'container_amount' => $containerAmount,
        'has_containers'   => $totalContainers > 0,
        'delivery_boy_id'  => $deliveryBoyId,
        'delivery_boy_source' => $forcedBoyId > 0 && $deliveryBoyId === $forcedBoyId
                                  ? 'manual'
                                  : ($deliveryBoyId !== null ? 'auto' : 'none'),
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollback();
    jsonResponse(false, 'Server error placing order: ' . $e->getMessage());
}