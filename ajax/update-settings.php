<?php
/* =========================================================
   MRS MILL@ — AJAX: UPDATE SETTINGS
   File: ./ajax/settings-update.php

   Rules:
   - Replace favicon / logo → delete old file, save new
   - Remove favicon / logo  → delete old file, set NULL
   - No change             → keep existing file
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$username     = trim($_POST['username'] ?? '');
$mobileNumber = trim($_POST['mobile_number'] ?? '');
$emailAddress = trim($_POST['email_address'] ?? '');
$currency     = trim($_POST['currency'] ?? '');
$currencyCode = trim($_POST['currency_code'] ?? '');

$removeFavicon = (int) ($_POST['remove_favicon'] ?? 0);
$removeLogo    = (int) ($_POST['remove_logo'] ?? 0);

/* ---------------- VALIDATION ---------------- */

if ($username === '') {
    jsonResponse(false, 'Username is required.');
}

if ($mobileNumber !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $mobileNumber)) {
    jsonResponse(false, 'Please enter a valid mobile number.');
}

if ($emailAddress !== '' && !filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Please enter a valid email address.');
}

if ($currency === '') {
    $currency = '₹';
}

/* ---------------- LOAD EXISTING ---------------- */

try {

    $settings = getSettings($pdo);

    if (!$settings) {
        jsonResponse(false, 'Settings not found.');
    }

} catch (PDOException $e) {
    jsonResponse(false, 'Server error. Please try again.');
}

$baseDir         = dirname(__DIR__);
$currentFavicon  = $settings['favicon_image'] ?: '';
$currentLogo     = $settings['logo_image'] ?: '';

$newFavicon = $currentFavicon;
$newLogo    = $currentLogo;

$uploadedFavicon = false;
$uploadedLogo    = false;

/* ---------------- FAVICON ---------------- */

if (isset($_FILES['favicon_image']) &&
    ($_FILES['favicon_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {

    $upload = uploadSettingsImage($_FILES['favicon_image'], $baseDir, 'favicon');

    if (!$upload['success']) {
        jsonResponse(false, 'Favicon: ' . $upload['message']);
    }

    if ($currentFavicon !== '') {
        deleteStoredFile($currentFavicon, $baseDir);
    }

    $newFavicon = $upload['path'];
    $uploadedFavicon = true;

} elseif ($removeFavicon === 1 && $currentFavicon !== '') {

    deleteStoredFile($currentFavicon, $baseDir);
    $newFavicon = null;
}

/* ---------------- LOGO ---------------- */

if (isset($_FILES['logo_image']) &&
    ($_FILES['logo_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {

    $upload = uploadSettingsImage($_FILES['logo_image'], $baseDir, 'logo');

    if (!$upload['success']) {
        // rollback favicon upload if we already did one
        if ($uploadedFavicon && $newFavicon) {
            deleteStoredFile($newFavicon, $baseDir);
        }
        jsonResponse(false, 'Logo: ' . $upload['message']);
    }

    if ($currentLogo !== '') {
        deleteStoredFile($currentLogo, $baseDir);
    }

    $newLogo = $upload['path'];
    $uploadedLogo = true;

} elseif ($removeLogo === 1 && $currentLogo !== '') {

    deleteStoredFile($currentLogo, $baseDir);
    $newLogo = null;
}

/* ---------------- UPDATE ---------------- */

try {

    $stmt = $pdo->prepare(
        "UPDATE settings
         SET username      = ?,
             mobile_number = ?,
             email_address = ?,
             currency      = ?,
             currency_code = ?,
             favicon_image = ?,
             logo_image    = ?
         WHERE id = 1"
    );

    $stmt->execute([
        $username,
        $mobileNumber,
        $emailAddress,
        $currency,
        $currencyCode,
        $newFavicon,
        $newLogo
    ]);

    jsonResponse(
        true,
        'Settings updated successfully.',
        [
            'favicon_url' => $newFavicon ? ADMIN_URL . $newFavicon : '',
            'logo_url'    => $newLogo    ? ADMIN_URL . $newLogo    : ''
        ]
    );

} catch (PDOException $e) {

    // rollback uploads
    if ($uploadedFavicon && $newFavicon) deleteStoredFile($newFavicon, $baseDir);
    if ($uploadedLogo    && $newLogo)    deleteStoredFile($newLogo, $baseDir);

    jsonResponse(false, 'Failed to update settings.');
}