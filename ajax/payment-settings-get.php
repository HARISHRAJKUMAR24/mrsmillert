<?php
/* =========================================================
   MRS MILL@ — AJAX: GET PAYMENT SETTINGS
   File: ./ajax/payment-settings-get.php
   Returns: razorpay_key_id, razorpay_key_secret, upi_id,
            tax_status, tax_type, tax_rate
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method not allowed.');
}

try {

    $settings = getSettings($pdo);

    if (!$settings) {
        jsonResponse(false, 'Settings not found.');
    }

    jsonResponse(true, 'OK', [
        'razorpay_key_id'     => $settings['razorpay_key_id'] ?? '',
        'razorpay_key_secret' => $settings['razorpay_key_secret'] ?? '',
        'upi_id'              => $settings['upi_id'] ?? '',
        'tax_status'          => isset($settings['tax_status']) ? (int)$settings['tax_status'] : 0,
        'tax_type'            => $settings['tax_type'] ?? 'exclusive',
        'tax_rate'            => isset($settings['tax_rate']) ? (float)$settings['tax_rate'] : 0
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load payment settings.');
}