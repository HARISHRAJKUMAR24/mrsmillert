<?php
/* =========================================================
   MRS MILL@ — AJAX: UPDATE PAYMENT SETTINGS
   File: ./ajax/update-payment-settings.php
   Saves Razorpay + UPI + GST + Tax into settings (id = 1)
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
$gstNumber = strtoupper(trim($_POST['gst_number'] ?? ''));

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

/* GST Number — optional, but if provided must be valid */
if ($gstNumber !== '') {

    /* Standard Indian GSTIN format:
       2 digits (state code) + 10 chars PAN + 1 entity code + Z + 1 check digit = 15 chars
       Example: 22AAAAA0000A1Z5
    */
    if (strlen($gstNumber) !== 15) {
        jsonResponse(false, 'GST Number must be exactly 15 characters.');
    }

    if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstNumber)) {
        jsonResponse(false, 'GST Number is invalid. Example: 22AAAAA0000A1Z5');
    }

    /* Verify state code (01–37 valid) */
    $stateCode = (int)substr($gstNumber, 0, 2);
    if ($stateCode < 1 || $stateCode > 37) {
        jsonResponse(false, 'GST Number has an invalid state code (01–37).');
    }
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

    $settings = getSettings($pdo);
    if (!$settings) {
        jsonResponse(false, 'Settings row not found.');
    }

    $stmt = $pdo->prepare(
        "UPDATE settings
         SET razorpay_key_id     = ?,
             razorpay_key_secret = ?,
             upi_id              = ?,
             gst_number          = ?,
             tax_status          = ?,
             tax_type            = ?,
             tax_rate            = ?
         WHERE id = 1"
    );

    $stmt->execute([
        $keyId,
        $keySecret,
        $upiId,
        $gstNumber !== '' ? $gstNumber : null,
        $taxStatus,
        $taxType,
        $taxRate
    ]);

    jsonResponse(true, 'Payment settings saved successfully.');

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to save payment settings.');
}