<?php
/* =========================================================
   MRS MILL@ — AJAX: UPDATE DISCOUNT
   File: ./ajax/update-discount.php
   - Updates discount row
   - Replaces all discount_times for this discount (with slot_name)
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$id          = (int) ($_POST['id'] ?? 0);
$name        = trim($_POST['discount_name'] ?? '');
$type        = strtolower(trim($_POST['discount_type'] ?? 'time'));
$couponCode  = strtoupper(trim($_POST['coupon_code'] ?? ''));
$validFrom   = trim($_POST['valid_from_date'] ?? '');
$validTo     = trim($_POST['valid_to_date'] ?? '');
$statusRaw   = $_POST['discount_status'] ?? '1';
$slotsRaw    = $_POST['slots'] ?? '[]';

$slots = json_decode($slotsRaw, true);

$status = ($statusRaw === '1' || $statusRaw === 1 || $statusRaw === true) ? 1 : 0;

if ($id <= 0)     jsonResponse(false, 'Invalid discount ID.');
if ($name === '') jsonResponse(false, 'Discount name is required.');
if (!in_array($type, ['time', 'coupon'], true)) jsonResponse(false, 'Invalid type.');

if ($type === 'coupon') {
    if ($couponCode === '') jsonResponse(false, 'Coupon code is required.');
    if (!preg_match('/^[A-Z0-9_-]{3,50}$/', $couponCode)) jsonResponse(false, 'Invalid coupon code format.');
    if ($validFrom === '' || $validTo === '') jsonResponse(false, 'Dates required.');
    if (strtotime($validTo) < strtotime($validFrom)) jsonResponse(false, 'Invalid date range.');
} else {
    $couponCode = null;
    $validFrom  = null;
    $validTo    = null;
}

if (!is_array($slots) || count($slots) === 0) {
    jsonResponse(false, 'At least one slot required.');
}

$cleanSlots = [];

foreach ($slots as $i => $s) {
    $label = 'Slot #' . ($i + 1);

    $slotName = trim((string)($s['slot_name'] ?? ''));
    $st = trim((string)($s['start_time'] ?? ''));
    $et = trim((string)($s['end_time'] ?? ''));
    $aType = strtolower(trim((string)($s['amount_type'] ?? 'fixed')));
    $amt = trim((string)($s['discount_amount'] ?? ''));
    $del = !empty($s['delivery_enabled']) ? 1 : 0;

    if ($slotName === '') {
        jsonResponse(false, $label . ': slot name is required.');
    }
    if (strlen($slotName) > 50) {
        jsonResponse(false, $label . ': slot name is too long.');
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $st) || !preg_match('/^\d{2}:\d{2}$/', $et)) {
        jsonResponse(false, $label . ': invalid time format.');
    }
    if ($st === $et) jsonResponse(false, $label . ': same start/end time.');
    if (!in_array($aType, ['fixed', 'percent'], true)) {
        jsonResponse(false, $label . ': invalid amount type.');
    }
    if ($amt === '' || !is_numeric($amt) || (float)$amt < 0) {
        jsonResponse(false, $label . ': invalid amount.');
    }
    if ($aType === 'percent' && (float)$amt > 100) {
        jsonResponse(false, $label . ': percent cannot exceed 100.');
    }

    $cleanSlots[] = [
        'slot_name'        => $slotName,
        'start_time'       => $st,
        'end_time'         => $et,
        'amount_type'      => $aType,
        'discount_amount'  => number_format((float)$amt, 2, '.', ''),
        'delivery_enabled' => $del
    ];
}

try {

    /* Load existing */
    $stmt = $pdo->prepare("SELECT * FROM discounts WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) jsonResponse(false, 'Discount not found.');

    /* Coupon uniqueness (excluding this) */
    if ($type === 'coupon' && $couponCode !== null) {
        $chk = $pdo->prepare("SELECT id FROM discounts WHERE coupon_code = ? AND id <> ? LIMIT 1");
        $chk->execute([$couponCode, $id]);
        if ($chk->fetch()) jsonResponse(false, 'Coupon code already in use.');
    }

    $pdo->beginTransaction();

    /* 1) update discounts */
    $up = $pdo->prepare(
        "UPDATE discounts
         SET discount_name = ?, discount_type = ?, coupon_code = ?,
             valid_from_date = ?, valid_to_date = ?, status = ?
         WHERE id = ?"
    );
    $up->execute([
        $name,
        $type,
        $couponCode,
        $validFrom ?: null,
        $validTo   ?: null,
        $status,
        $id
    ]);

    /* 2) replace slots */
    $del = $pdo->prepare("DELETE FROM discount_times WHERE discount_code = ?");
    $del->execute([$existing['discount_code']]);

    $ins = $pdo->prepare(
        "INSERT INTO discount_times
            (discount_code, slot_name, start_time, end_time, amount_type, discount_amount, delivery_enabled)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    foreach ($cleanSlots as $s) {
        $ins->execute([
            $existing['discount_code'],
            $s['slot_name'],
            $s['start_time'],
            $s['end_time'],
            $s['amount_type'],
            $s['discount_amount'],
            $s['delivery_enabled']
        ]);
    }

    $pdo->commit();

    jsonResponse(true, 'Discount updated successfully.', [
        'id'    => $id,
        'code'  => $existing['discount_code'],
        'slots' => $cleanSlots
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(false, 'Failed to update discount: ' . $e->getMessage());
}