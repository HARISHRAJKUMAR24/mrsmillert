<?php
/* =========================================================
   MRS MILL@ — AJAX: ADD DISCOUNT
   File: ./ajax/add-discount.php
   - Generates discount_code (DSC001, DSC002 ...)
   - Saves type (time | coupon), status
   - Saves one or more slots with NAME + time + amount + delivery
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$name        = trim($_POST['discount_name'] ?? '');
$type        = strtolower(trim($_POST['discount_type'] ?? 'time'));
$couponCode  = strtoupper(trim($_POST['coupon_code'] ?? ''));
$validFrom   = trim($_POST['valid_from_date'] ?? '');
$validTo     = trim($_POST['valid_to_date'] ?? '');
$statusRaw   = $_POST['discount_status'] ?? '1';
$slotsRaw    = $_POST['slots'] ?? '[]';

$slots = json_decode($slotsRaw, true);

$status = ($statusRaw === '1' || $statusRaw === 1 || $statusRaw === true) ? 1 : 0;

if (!in_array($type, ['time', 'coupon'], true)) {
    jsonResponse(false, 'Invalid discount type.');
}

if ($name === '') jsonResponse(false, 'Discount name is required.');

if ($type === 'coupon') {
    if ($couponCode === '') jsonResponse(false, 'Coupon code is required.');
    if (!preg_match('/^[A-Z0-9_-]{3,50}$/', $couponCode)) {
        jsonResponse(false, 'Coupon code must be 3-50 letters/numbers/underscore/dash.');
    }
    if ($validFrom === '' || $validTo === '') {
        jsonResponse(false, 'Valid from/to date are required.');
    }
    if (strtotime($validTo) < strtotime($validFrom)) {
        jsonResponse(false, 'Valid To must be after Valid From.');
    }
} else {
    $couponCode = null;
    $validFrom  = null;
    $validTo    = null;
}

if (!is_array($slots) || count($slots) === 0) {
    jsonResponse(false, 'Please add at least one time slot.');
}

$cleanSlots = [];

foreach ($slots as $i => $s) {

    $label = 'Slot #' . ($i + 1);

    $slotName = trim((string)($s['slot_name'] ?? ''));
    $st       = trim((string)($s['start_time'] ?? ''));
    $et       = trim((string)($s['end_time'] ?? ''));
    $amountType = strtolower(trim((string)($s['amount_type'] ?? 'fixed')));
    $amt      = trim((string)($s['discount_amount'] ?? ''));
    $del      = !empty($s['delivery_enabled']) ? 1 : 0;

    if ($slotName === '') {
        jsonResponse(false, $label . ': slot name is required.');
    }
    if (strlen($slotName) > 50) {
        jsonResponse(false, $label . ': slot name is too long.');
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $st) || !preg_match('/^\d{2}:\d{2}$/', $et)) {
        jsonResponse(false, $label . ': invalid time format.');
    }
    if ($st === $et) {
        jsonResponse(false, $label . ': start and end time cannot be same.');
    }
    if (!in_array($amountType, ['fixed', 'percent'], true)) {
        jsonResponse(false, $label . ': invalid amount type.');
    }
    if ($amt === '' || !is_numeric($amt) || (float)$amt < 0) {
        jsonResponse(false, $label . ': invalid amount.');
    }
    if ($amountType === 'percent' && (float)$amt > 100) {
        jsonResponse(false, $label . ': percentage cannot exceed 100.');
    }

    $cleanSlots[] = [
        'slot_name'        => $slotName,
        'start_time'       => $st,
        'end_time'         => $et,
        'amount_type'      => $amountType,
        'discount_amount'  => number_format((float)$amt, 2, '.', ''),
        'delivery_enabled' => $del
    ];
}

try {
    if ($type === 'coupon' && $couponCode !== null) {
        $chk = $pdo->prepare("SELECT id FROM discounts WHERE coupon_code = ? LIMIT 1");
        $chk->execute([$couponCode]);
        if ($chk->fetch()) {
            jsonResponse(false, 'This coupon code is already in use.');
        }
    }
} catch (PDOException $e) {
    jsonResponse(false, 'Server error while checking coupon.');
}

$discountCode = generateDiscountCode($pdo);
if (!$discountCode) jsonResponse(false, 'Could not generate a discount code.');

try {

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO discounts
            (discount_code, discount_name, discount_type,
             coupon_code, valid_from_date, valid_to_date, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $discountCode,
        $name,
        $type,
        $couponCode,
        $validFrom ?: null,
        $validTo   ?: null,
        $status
    ]);

    $discountId = (int)$pdo->lastInsertId();
    if ($discountId <= 0) throw new PDOException('Could not get discount id.');

    $link = $pdo->prepare(
        "INSERT INTO discount_times
            (discount_code, slot_name, start_time, end_time, amount_type, discount_amount, delivery_enabled)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    foreach ($cleanSlots as $s) {
        $link->execute([
            $discountCode,
            $s['slot_name'],
            $s['start_time'],
            $s['end_time'],
            $s['amount_type'],
            $s['discount_amount'],
            $s['delivery_enabled']
        ]);
    }

    $pdo->commit();

    jsonResponse(true, 'Discount added successfully.', [
        'id'    => $discountId,
        'code'  => $discountCode,
        'type'  => $type,
        'slots' => $cleanSlots
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(false, 'Failed to add discount: ' . $e->getMessage());
}