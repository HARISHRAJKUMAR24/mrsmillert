<?php
/* =========================================================
   MRS MILL@ — AJAX: SAVE ALL SETTINGS
   File: ./ajax/settings-save.php
   Handles general + branches + banners + offers + payment + tax + branding
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.');
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) jsonResponse(false, 'Not logged in.');
if (($_SESSION['admin_role'] ?? '') !== 'admin') jsonResponse(false, 'Admin access only.');

/* ---------- READ ---------- */
$username     = trim($_POST['username'] ?? '');
$mobile       = trim($_POST['mobile_number'] ?? '');
$email        = trim($_POST['email_address'] ?? '');
$currency     = trim($_POST['currency'] ?? '₹');
$currencyCode = trim($_POST['currency_code'] ?? 'INR');

$keyId     = trim($_POST['razorpay_key_id'] ?? '');
$keySecret = trim($_POST['razorpay_key_secret'] ?? '');
$upiId     = trim($_POST['upi_id'] ?? '');
$gstNumber = strtoupper(trim($_POST['gst_number'] ?? ''));

$taxStatusRaw = $_POST['tax_status'] ?? '0';
$taxStatus    = ($taxStatusRaw === '1' || $taxStatusRaw === 1) ? 1 : 0;
$taxTypeRaw   = strtolower(trim($_POST['tax_type'] ?? ''));
$taxType      = in_array($taxTypeRaw, ['inclusive', 'exclusive'], true) ? $taxTypeRaw : 'exclusive';
$taxRateRaw   = trim($_POST['tax_rate'] ?? '0');
$taxRate      = is_numeric($taxRateRaw) ? (float)$taxRateRaw : 0.0;

$removeFavicon       = (int)($_POST['remove_favicon'] ?? 0);
$removeLogo          = (int)($_POST['remove_logo'] ?? 0);
$removeTodayOffer    = (int)($_POST['remove_today_offer'] ?? 0);
$removeTomorrowOffer = (int)($_POST['remove_tomorrow_offer'] ?? 0);

$branchesRaw = $_POST['branches'] ?? '[]';
$branches    = json_decode($branchesRaw, true);

/* Banner remove flags */
$removeBanner = [
    1 => (int)($_POST['remove_banner_1'] ?? 0),
    2 => (int)($_POST['remove_banner_2'] ?? 0),
    3 => (int)($_POST['remove_banner_3'] ?? 0),
    4 => (int)($_POST['remove_banner_4'] ?? 0),
];

/* ---------- VALIDATE GENERAL ---------- */
if ($username === '') jsonResponse(false, 'Site name is required.');
if ($mobile !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $mobile)) jsonResponse(false, 'Invalid mobile number.');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'Invalid email address.');
if ($currency === '') $currency = '₹';

/* ---------- VALIDATE PAYMENT ---------- */
if ($keyId === '') jsonResponse(false, 'Razorpay Key ID is required.');
if (stripos($keyId, 'rzp_') !== 0) jsonResponse(false, 'Razorpay Key ID must start with "rzp_".');
if (strlen($keyId) < 10) jsonResponse(false, 'Razorpay Key ID too short.');

if ($keySecret === '') jsonResponse(false, 'Razorpay Key Secret is required.');
if (strlen($keySecret) < 10) jsonResponse(false, 'Razorpay Key Secret too short.');

if ($upiId === '') jsonResponse(false, 'UPI ID is required.');
if (!preg_match('/^[a-zA-Z0-9._-]{2,}@[a-zA-Z]{2,}$/', $upiId)) {
    jsonResponse(false, 'UPI ID invalid. Example: yourname@okhdfcbank');
}

/* ---------- VALIDATE GST ---------- */
if ($gstNumber !== '') {
    if (strlen($gstNumber) !== 15) jsonResponse(false, 'GST Number must be exactly 15 characters.');
    if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstNumber)) {
        jsonResponse(false, 'GST Number invalid. Example: 22AAAAA0000A1Z5');
    }
    $state = (int)substr($gstNumber, 0, 2);
    if ($state < 1 || $state > 37) jsonResponse(false, 'GST state code invalid (01–37).');
}

if ($taxStatus === 1) {
    if ($taxRate < 0 || $taxRate > 100) jsonResponse(false, 'Tax Rate must be 0–100.');
    if ($gstNumber === '') jsonResponse(false, 'GST Number is required when Tax is enabled.');
} else {
    $taxType = 'exclusive';
    $taxRate = 0.0;
}

/* ---------- VALIDATE BRANCHES ---------- */
$cleanBranches = [];
if (is_array($branches)) {
    foreach ($branches as $i => $b) {
        $label = 'Branch #' . ($i + 1);
        $name    = trim((string)($b['branch_name'] ?? ''));
        $address = trim((string)($b['branch_address'] ?? ''));
        $bmob    = trim((string)($b['branch_mobile'] ?? ''));
        $bemail  = trim((string)($b['branch_email'] ?? ''));

        if ($name === '' && $address === '' && $bmob === '' && $bemail === '') continue;

        if ($name === '')    jsonResponse(false, $label . ': branch name required.');
        if ($address === '') jsonResponse(false, $label . ': branch address required.');
        if ($bmob !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $bmob)) {
            jsonResponse(false, $label . ': invalid mobile.');
        }
        if ($bemail !== '' && !filter_var($bemail, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(false, $label . ': invalid email.');
        }

        $cleanBranches[] = [
            'branch_name'    => $name,
            'branch_address' => $address,
            'branch_mobile'  => $bmob,
            'branch_email'   => $bemail
        ];
    }
}

/* ---------- LOAD EXISTING ---------- */
try {
    $settings = getSettings($pdo);
    if (!$settings) jsonResponse(false, 'Settings row not found.');
} catch (PDOException $e) {
    jsonResponse(false, 'Server error.');
}

$baseDir = dirname(__DIR__);

/* =========================================================
   Ensure banner + offer columns exist BEFORE transaction.
   ========================================================= */
for ($i = 1; $i <= 4; $i++) {
    $col = 'banner_' . $i;
    try {
        $chk = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'settings'
               AND COLUMN_NAME = ?"
        );
        $chk->execute([$col]);
        $exists = (int)$chk->fetchColumn() > 0;
        if (!$exists) {
            $pdo->exec("ALTER TABLE settings ADD COLUMN {$col} VARCHAR(255) DEFAULT NULL");
        }
    } catch (PDOException $e) { /* ignore */ }
}

/* Ensure offer columns exist */
foreach (['today_offer_image', 'tomorrow_offer_image'] as $col) {
    try {
        $chk = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'settings'
               AND COLUMN_NAME = ?"
        );
        $chk->execute([$col]);
        $exists = (int)$chk->fetchColumn() > 0;
        if (!$exists) {
            $pdo->exec("ALTER TABLE settings ADD COLUMN {$col} VARCHAR(255) DEFAULT NULL");
        }
    } catch (PDOException $e) { /* ignore */ }
}

/* ---------- FAVICON ---------- */
$newFavicon = $settings['favicon_image'] ?? null;
if (!empty($_FILES['favicon_image']['name'])) {
    $upload = uploadSettingsImage($_FILES['favicon_image'], $baseDir, 'favicon');
    if (!$upload['success']) jsonResponse(false, 'Favicon: ' . $upload['message']);
    if (!empty($newFavicon)) deleteStoredFile($newFavicon, $baseDir);
    $newFavicon = $upload['path'];
} elseif ($removeFavicon === 1) {
    if (!empty($newFavicon)) deleteStoredFile($newFavicon, $baseDir);
    $newFavicon = null;
}

/* ---------- LOGO ---------- */
$newLogo = $settings['logo_image'] ?? null;
if (!empty($_FILES['logo_image']['name'])) {
    $upload = uploadSettingsImage($_FILES['logo_image'], $baseDir, 'logo');
    if (!$upload['success']) jsonResponse(false, 'Logo: ' . $upload['message']);
    if (!empty($newLogo)) deleteStoredFile($newLogo, $baseDir);
    $newLogo = $upload['path'];
} elseif ($removeLogo === 1) {
    if (!empty($newLogo)) deleteStoredFile($newLogo, $baseDir);
    $newLogo = null;
}

/* ---------- BANNERS ---------- */
$bannerUpdates = [];
for ($i = 1; $i <= 4; $i++) {
    $current = $settings['banner_' . $i] ?? null;
    $newVal = $current;

    if (!empty($_FILES['banner_' . $i]['name'])) {
        $upload = uploadSettingsImage($_FILES['banner_' . $i], $baseDir, 'banner');
        if ($upload['success']) {
            if (!empty($current)) deleteStoredFile($current, $baseDir);
            $newVal = $upload['path'];
        }
    } elseif ($removeBanner[$i] === 1) {
        if (!empty($current)) deleteStoredFile($current, $baseDir);
        $newVal = null;
    }

    $bannerUpdates[$i] = $newVal;
}

/* ---------- TODAY OFFER ---------- */
$newTodayOffer = $settings['today_offer_image'] ?? null;
if (!empty($_FILES['today_offer_image']['name'])) {
    $upload = uploadSettingsImage($_FILES['today_offer_image'], $baseDir, 'offer');
    if (!$upload['success']) jsonResponse(false, 'Today Offer: ' . $upload['message']);
    if (!empty($newTodayOffer)) deleteStoredFile($newTodayOffer, $baseDir);
    $newTodayOffer = $upload['path'];
} elseif ($removeTodayOffer === 1) {
    if (!empty($newTodayOffer)) deleteStoredFile($newTodayOffer, $baseDir);
    $newTodayOffer = null;
}

/* ---------- TOMORROW OFFER ---------- */
$newTomorrowOffer = $settings['tomorrow_offer_image'] ?? null;
if (!empty($_FILES['tomorrow_offer_image']['name'])) {
    $upload = uploadSettingsImage($_FILES['tomorrow_offer_image'], $baseDir, 'offer');
    if (!$upload['success']) jsonResponse(false, 'Tomorrow Offer: ' . $upload['message']);
    if (!empty($newTomorrowOffer)) deleteStoredFile($newTomorrowOffer, $baseDir);
    $newTomorrowOffer = $upload['path'];
} elseif ($removeTomorrowOffer === 1) {
    if (!empty($newTomorrowOffer)) deleteStoredFile($newTomorrowOffer, $baseDir);
    $newTomorrowOffer = null;
}

/* ---------- SAVE ---------- */
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "UPDATE settings SET
            username = ?,
            mobile_number = ?,
            email_address = ?,
            currency = ?,
            currency_code = ?,
            favicon_image = ?,
            logo_image = ?,
            banner_1 = ?,
            banner_2 = ?,
            banner_3 = ?,
            banner_4 = ?,
            today_offer_image = ?,
            tomorrow_offer_image = ?,
            razorpay_key_id = ?,
            razorpay_key_secret = ?,
            upi_id = ?,
            gst_number = ?,
            tax_status = ?,
            tax_type = ?,
            tax_rate = ?,
            updated_at = NOW()
         WHERE id = 1"
    );

    $stmt->execute([
        $username,
        $mobile,
        $email,
        $currency,
        $currencyCode,
        $newFavicon,
        $newLogo,
        $bannerUpdates[1],
        $bannerUpdates[2],
        $bannerUpdates[3],
        $bannerUpdates[4],
        $newTodayOffer,
        $newTomorrowOffer,
        $keyId,
        $keySecret,
        $upiId,
        $gstNumber !== '' ? $gstNumber : null,
        $taxStatus,
        $taxType,
        $taxRate
    ]);

    /* Replace branches */
    $pdo->exec("DELETE FROM settings_branches");
    if (!empty($cleanBranches)) {
        $ins = $pdo->prepare(
            "INSERT INTO settings_branches
                (branch_name, branch_address, branch_mobile, branch_email)
             VALUES (?, ?, ?, ?)"
        );
        foreach ($cleanBranches as $b) {
            $ins->execute([
                $b['branch_name'],
                $b['branch_address'],
                $b['branch_mobile'],
                $b['branch_email']
            ]);
        }
    }

    $pdo->commit();

    jsonResponse(true, 'All settings saved successfully.', [
        'favicon_url'        => $newFavicon ? ADMIN_URL . $newFavicon : '',
        'logo_url'           => $newLogo    ? ADMIN_URL . $newLogo    : '',
        'today_offer_url'    => $newTodayOffer    ? ADMIN_URL . $newTodayOffer    : '',
        'tomorrow_offer_url' => $newTomorrowOffer ? ADMIN_URL . $newTomorrowOffer : '',
        'branches'           => $cleanBranches
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        try { $pdo->rollBack(); } catch (PDOException $e2) { /* ignore */ }
    }
    jsonResponse(false, 'Failed to save settings: ' . $e->getMessage());
}