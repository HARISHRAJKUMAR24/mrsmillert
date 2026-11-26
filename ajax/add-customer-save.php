<?php
/* =========================================================
   MRS MILL@ — AJAX: ADD / UPDATE CUSTOMER
   File: ./ajax/add-customer-save.php

   Image storage policy:
   - Saved ONLY inside the admin app (ADMIN_URL document root)
   - Path: uploads/houses/<Y>/<m>/<d>/customer_<id>/house.<ext>
   - Re-upload on the same day OVERWRITES the previous image
   - Old image from a previous day is deleted when a new one is saved
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.');
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) jsonResponse(false, 'Not logged in.');

$id             = (int)($_POST['id'] ?? 0);
$fullName       = trim($_POST['full_name'] ?? '');
$mobileNumber   = trim($_POST['mobile_number'] ?? '');
$password       = trim($_POST['password'] ?? '');
$status         = (int)($_POST['status'] ?? 1);
$addressType    = trim($_POST['address_type'] ?? 'apartment');
$addressJsonRaw = trim($_POST['address_json'] ?? '{}');
$initWallet     = (float)($_POST['init_wallet'] ?? 0);
$initNote       = trim($_POST['init_wallet_note'] ?? '');
$houseImgB64    = trim($_POST['house_image_data'] ?? '');
$aptImgB64      = trim($_POST['apt_house_image_data'] ?? '');

if ($fullName === '')     jsonResponse(false, 'Full name is required.');
if ($mobileNumber === '') jsonResponse(false, 'Mobile number is required.');
if (!preg_match('/^[0-9]{10,15}$/', $mobileNumber)) jsonResponse(false, 'Invalid mobile number.');

$status = $status ? 1 : 0;

if (!in_array($addressType, ['apartment', 'individual_house'], true)) {
    jsonResponse(false, 'Invalid address type.');
}

$addressJson = json_decode($addressJsonRaw, true);
if (!is_array($addressJson)) {
    jsonResponse(false, 'Invalid address data.');
}

/* Remove "?v=..." from an existing image path before saving */
if (!empty($addressJson['house_image'])) {
    $addressJson['house_image'] = explode('?', (string)$addressJson['house_image'])[0];
}

/* Validate */
if ($addressType === 'apartment') {
    if (empty($addressJson['apartment_id']))  jsonResponse(false, 'Please select an apartment.');
    if (empty($addressJson['division_label'])) jsonResponse(false, 'Please select a division.');
} else {
    if (empty($addressJson['full_address'])) jsonResponse(false, 'Full address is required.');
}

/* =========================================================
   AUTO-GENERATE CUSTOMER ID
   ========================================================= */
function generateCustomerId($pdo)
{
    $maxTries = 20;
    for ($i = 0; $i < $maxTries; $i++) {
        $digits = random_int(4, 7);
        $num    = random_int((int)pow(10, $digits - 1), (int)pow(10, $digits) - 1);
        $candidate = 'MRSMC' . $num;
        $chk = $pdo->prepare("SELECT id FROM customers WHERE customer_id = ? LIMIT 1");
        $chk->execute([$candidate]);
        if (!$chk->fetch()) return $candidate;
    }
    return 'MRSMC' . date('ymdHis') . random_int(10, 99);
}

/* =========================================================
   ABSOLUTE PATH OF THE ADMIN APP (where images must live)
   Derived from ADMIN_URL so it always matches your config.
   Falls back to __DIR__/.. if ADMIN_URL can't be resolved.
   ========================================================= */
function adminAppRoot(): string
{
    if (defined('ADMIN_URL')) {
        $u = parse_url(ADMIN_URL);
        if (!empty($u['path'])) {
            $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
            if ($docroot !== '') {
                return $docroot . rtrim($u['path'], '/');
            }
        }
    }
    // fallback: this file is in <admin>/ajax/, so <admin> = __DIR__/..
    return rtrim(__DIR__ . '/..', '/\\');
}

/* =========================================================
   Save base64 image into ADMIN-ONLY, DATE-FOLDERED customer path.
   Path: uploads/houses/<Y>/<m>/<d>/customer_<id>/house.<ext>
   Overwrites any previous image for that customer on the same day.
   Deletes the old image file stored in $oldRelPath (if any) so
   only one image per customer exists on disk.
   Returns relative path (e.g. uploads/houses/2026/11/26/customer_12/house.jpg)
   ========================================================= */
function saveCustomerImage(string $b64, int $customerId, ?string $oldRelPath = null): ?string
{
    if ($b64 === '' || $customerId <= 0) return null;
    if (!preg_match('/^data:image\/(png|jpe?g|webp|gif);base64,/i', $b64, $m)) return null;

    $ext  = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
    $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $b64));
    if ($data === false || strlen($data) > 3 * 1024 * 1024) return null;

    $root   = adminAppRoot();

    /* --- Date / month / year folder --- */
    $y   = date('Y');
    $mo  = date('m');
    $d   = date('d');

    $relDir = 'uploads/houses/' . $y . '/' . $mo . '/' . $d . '/customer_' . $customerId;
    $absDir = rtrim($root, '/\\') . '/' . $relDir;

    if (!is_dir($absDir) && !@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
        return null;
    }

    $fileName = 'house.' . $ext;
    $absFile  = $absDir . '/' . $fileName;

    /* Write to a temp file first, then OVERWRITE the old one.
       If anything fails, the old image stays safe. */
    $tmpFile = $absDir . '/tmp_' . bin2hex(random_bytes(6));
    if (@file_put_contents($tmpFile, $data) === false) return null;
    @chmod($tmpFile, 0644);

    if (!@rename($tmpFile, $absFile)) {
        @unlink($tmpFile);
        return null;
    }

    /* Same day, different extension (house.png -> house.jpg): remove the others */
    foreach (glob($absDir . '/house.*') ?: [] as $other) {
        if (basename($other) !== $fileName && is_file($other)) @unlink($other);
    }

    /* Remove the previous image if it was in another folder (older day) */
    if ($oldRelPath) {
        $oldReal = realpath($root . '/' . ltrim($oldRelPath, '/\\'));
        $newReal = realpath($absFile);
        $upReal  = realpath($root . '/uploads/houses');
        if ($oldReal && $upReal && $oldReal !== $newReal && strpos($oldReal, $upReal) === 0) {
            @unlink($oldReal);
        }
    }

    return $relDir . '/' . $fileName;
}

/* ---------------------------------------------------------
   Convert a normal file upload into a data URI,
   so saveCustomerImage() can handle it.
   --------------------------------------------------------- */
function uploadedImageToBase64(string $field): string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return '';

    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) jsonResponse(false, 'Image upload failed (code ' . $f['error'] . ').');
    if ($f['size'] > 3 * 1024 * 1024) jsonResponse(false, 'Image must be under 3 MB.');

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
        jsonResponse(false, 'Only JPG, PNG, WEBP or GIF allowed.');
    }

    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($f['tmp_name']));
}

$uploaded = uploadedImageToBase64('house_image');
if ($uploaded !== '') {
    if ($addressType === 'apartment') $aptImgB64 = $uploaded;
    else $houseImgB64 = $uploaded;
}

/* ---------------------------------------------------------
   Preload existing image path (so we can clean it up)
   --------------------------------------------------------- */
$oldImageRel = '';
if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT address_json FROM customers WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $cur = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $cur = false;
    }
    if (!empty($cur['address_json'])) {
        $oldAddr = json_decode($cur['address_json'], true);
        if (is_array($oldAddr) && !empty($oldAddr['house_image'])) {
            $oldImageRel = (string)$oldAddr['house_image'];
        }
    }
}

try {
    /* Duplicate mobile */
    $dupSql = "SELECT id FROM customers WHERE mobile_number = ?";
    $dupParams = [$mobileNumber];
    if ($id > 0) {
        $dupSql .= " AND id <> ?";
        $dupParams[] = $id;
    }
    $dupSql .= " LIMIT 1";
    $dup = $pdo->prepare($dupSql);
    $dup->execute($dupParams);
    if ($dup->fetch()) jsonResponse(false, 'A customer with this mobile already exists.');

    /* =========================================================
       UPDATE
       ========================================================= */
    if ($id > 0) {
        $check = $pdo->prepare("SELECT id, customer_id FROM customers WHERE id = ? LIMIT 1");
        $check->execute([$id]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);
        if (!$existing) jsonResponse(false, 'Customer not found.');

        if ($password !== '' && strlen($password) < 3) {
            jsonResponse(false, 'New password must be at least 3 characters.');
        }

        $customerId = $existing['customer_id'] ?? '';
        if ($customerId === '' || $customerId === null) {
            $customerId = generateCustomerId($pdo);
        }

        /* --- Image handling: save AFTER we know the id --- */
        $incomingB64 = ($addressType === 'apartment') ? $aptImgB64 : $houseImgB64;
        if ($incomingB64 !== '') {
            $newRel = saveCustomerImage($incomingB64, $id, $oldImageRel);
            if ($newRel === null) {
                jsonResponse(false, 'Could not save the image. Please check the uploads folder permission.');
            }
            $addressJson['house_image'] = $newRel;
        }

        /* Keep the old image if no new one was uploaded */
        if (empty($addressJson['house_image']) && $oldImageRel !== '') {
            $addressJson['house_image'] = $oldImageRel;
        }

        $addressJsonFinal = json_encode($addressJson, JSON_UNESCAPED_UNICODE);

        $sql = "UPDATE customers SET
                    customer_id = ?,
                    full_name = ?,
                    mobile_number = ?,
                    address_type = ?,
                    address_json = ?,
                    status = ?,
                    updated_at = NOW()";
        $params = [$customerId, $fullName, $mobileNumber, $addressType, $addressJsonFinal, $status];

        if ($password !== '') {
            $sql .= ", password_hash = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $pdo->prepare($sql)->execute($params);

        jsonResponse(true, 'Customer updated successfully.', [
            'id'          => $id,
            'customer_id' => $customerId
        ]);
    }

    /* =========================================================
       INSERT
       ========================================================= */
    if ($password === '')      jsonResponse(false, 'Password is required.');
    if (strlen($password) < 3) jsonResponse(false, 'Password must be at least 3 characters.');

    $hash       = password_hash($password, PASSWORD_DEFAULT);
    $customerId = generateCustomerId($pdo);

    /* Insert first without image so we get the id, then save image. */
    $ins = $pdo->prepare(
        "INSERT INTO customers
            (customer_id, full_name, mobile_number, address_type, address_json,
             password_hash, wallet_balance,
             profile_completed, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, NOW(), NOW())"
    );
    $ins->execute([
        $customerId,
        $fullName,
        $mobileNumber,
        $addressType,
        json_encode($addressJson, JSON_UNESCAPED_UNICODE), // no image yet
        $hash,
        $initWallet > 0 ? $initWallet : 0,
        $status
    ]);

    $newId = (int)$pdo->lastInsertId();

    /* Now save image (if any) into uploads/houses/<Y>/<m>/<d>/customer_<newId>/house.<ext> */
    $incomingB64 = ($addressType === 'apartment') ? $aptImgB64 : $houseImgB64;
    if ($incomingB64 !== '') {
        $newRel = saveCustomerImage($incomingB64, $newId, null);
        if ($newRel !== null) {
            $addressJson['house_image'] = $newRel;
            $pdo->prepare("UPDATE customers SET address_json = ? WHERE id = ?")
                ->execute([json_encode($addressJson, JSON_UNESCAPED_UNICODE), $newId]);
        }
    }

    /* Wallet transaction log */
    if ($initWallet > 0) {
        try {
            $txn = 'TXN' . date('ymd') . mt_rand(1000, 9999);
            $pdo->prepare(
                "INSERT INTO customer_wallet_transactions
                    (customer_id, customer_name, customer_mobile, txn_code, txn_type,
                     amount, balance_before, balance_after, source, note,
                     created_by_id, created_by_name, created_at)
                 VALUES (?, ?, ?, ?, 'credit', ?, 0, ?, 'admin', ?, ?, ?, NOW())"
            )->execute([
                $newId,
                $fullName,
                $mobileNumber,
                $txn,
                $initWallet,
                $initWallet,
                $initNote !== '' ? $initNote : 'Initial wallet balance',
                (int)$_SESSION['admin_id'],
                $_SESSION['admin_name'] ?? 'Admin'
            ]);
        } catch (PDOException $e) { /* ignore */
        }
    }

    jsonResponse(true, 'Customer added successfully.', [
        'id'          => $newId,
        'customer_id' => $customerId,
        'code'        => $customerId
    ]);
} catch (PDOException $e) {
    jsonResponse(false, 'Server error: ' . $e->getMessage());
}