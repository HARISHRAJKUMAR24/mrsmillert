<?php
/* =========================================================
   MRS MILL@ — AJAX: UPDATE PAYMENT SETTINGS
   File: ./ajax/update-payment-settings.php
   Saves Razorpay Key ID + Key Secret + UPI ID + Tax fields
   into settings row (id = 1) in ONE query
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$keyId     = trim($_POST['razorpay_key_id'] ?? '');
$keySecret = trim($_POST['razorpay_key_secret'] ?? '');
$upiId     = trim($_POST['upi_id'] ?? '');

/* ---- Tax ---- */
$taxStatusRaw = $_POST['tax_status'] ?? '0';
$taxStatus    = ($taxStatusRaw === '1' || $taxStatusRaw === 1 || $taxStatusRaw === true) ? 1 : 0;

$taxTypeRaw = strtolower(trim($_POST['tax_type'] ?? ''));
$taxType    = in_array($taxTypeRaw, ['inclusive', 'exclusive'], true) ? $taxTypeRaw : '';

$taxRateRaw = trim($_POST['tax_rate'] ?? '0');
$taxRate    = is_numeric($taxRateRaw) ? (float)$taxRateRaw : 0.0;

/* ---------------- VALIDATION ---------------- */

/* Razorpay Key ID */
if ($keyId === '') {
    jsonResponse(false, 'Razorpay Key ID is required.');
}
if (stripos($keyId, 'rzp_') !== 0) {
    jsonResponse(false, 'Razorpay Key ID must start with "rzp_".');
}
if (strlen($keyId) < 10) {
    jsonResponse(false, 'Razorpay Key ID looks too short.');
}

/* Razorpay Key Secret */
if ($keySecret === '') {
    jsonResponse(false, 'Razorpay Key Secret is required.');
}
if (strlen($keySecret) < 10) {
    jsonResponse(false, 'Razorpay Key Secret looks too short.');
}

/* UPI ID */
if ($upiId === '') {
    jsonResponse(false, 'UPI ID is required.');
}
if (strlen($upiId) > 255) {
    jsonResponse(false, 'UPI ID is too long.');
}
if (!preg_match('/^[a-zA-Z0-9._-]{2,}@[a-zA-Z]{2,}$/', $upiId)) {
    jsonResponse(false, 'UPI ID looks invalid. Example: yourname@okhdfcbank');
}

/* Tax — only enforce when enabled */
if ($taxStatus === 1) {

    if ($taxType === '') {
        jsonResponse(false, 'Please choose Inclusive or Exclusive tax type.');
    }

    if ($taxRate < 0 || $taxRate > 100) {
        jsonResponse(false, 'Tax Rate must be between 0 and 100.');
    }

} else {

    /* Disabled → force type + rate */
    $taxType = 'exclusive';
    $taxRate = 0.0;
}

/* ---------------- UPDATE ---------------- */

try {

    /* Ensure row exists */
    $settings = getSettings($pdo);
    if (!$settings) {
        jsonResponse(false, 'Settings row not found.');
    }

    $stmt = $pdo->prepare(
        "UPDATE settings
         SET razorpay_key_id     = ?,
             razorpay_key_secret = ?,
             upi_id              = ?,
             tax_status          = ?,
             tax_type            = ?,
             tax_rate            = ?
         WHERE id = 1"
    );

    $stmt->execute([
        $keyId,
        $keySecret,
        $upiId,
        $taxStatus,
        $taxType,
        $taxRate
    ]);

    jsonResponse(true, 'Payment settings saved successfully.');

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to save payment settings.');
}