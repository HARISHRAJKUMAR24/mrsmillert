<?php
/* =========================================================
   MRS MILL@ — AJAX: ADD / UPDATE CUSTOMER
   File: ./ajax/add-customer-save.php
   Auto-generates customer_id like MRSMC123456
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.');
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) jsonResponse(false, 'Not logged in.');

$id            = (int)($_POST['id'] ?? 0);
$fullName      = trim($_POST['full_name'] ?? '');
$mobileNumber  = trim($_POST['mobile_number'] ?? '');
$password      = trim($_POST['password'] ?? '');
$status        = (int)($_POST['status'] ?? 1);
$addressType   = trim($_POST['address_type'] ?? 'apartment');
$addressJsonRaw= trim($_POST['address_json'] ?? '{}');
$initWallet    = (float)($_POST['init_wallet'] ?? 0);
$initNote      = trim($_POST['init_wallet_note'] ?? '');
$houseImgB64   = trim($_POST['house_image_data'] ?? '');

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

/* Validate apartment */
if ($addressType === 'apartment') {
    if (empty($addressJson['apartment_id'])) jsonResponse(false, 'Please select an apartment.');
    if (empty($addressJson['division_label'])) jsonResponse(false, 'Please select a division.');
} else {
    if (empty($addressJson['full_address'])) jsonResponse(false, 'Full address is required.');
    if (empty($addressJson['map_link'])) jsonResponse(false, 'Map link is required.');
}

/* =========================================================
   AUTO-GENERATE CUSTOMER ID — MRSMC + random digits
   ========================================================= */
function generateCustomerId($pdo)
{
    $maxTries = 20;

    for ($i = 0; $i < $maxTries; $i++) {

        /* 4 to 7 random digits, no leading zero */
        $digits = random_int(4, 7);
        $num    = random_int((int)pow(10, $digits - 1), (int)pow(10, $digits) - 1);

        $candidate = 'MRSMC' . $num;

        /* Check collision */
        $chk = $pdo->prepare("SELECT id FROM customers WHERE customer_id = ? LIMIT 1");
        $chk->execute([$candidate]);

        if (!$chk->fetch()) return $candidate;
    }

    /* Fallback: timestamp-based, guaranteed unique enough */
    return 'MRSMC' . date('ymdHis') . random_int(10, 99);
}

/* Save house image if provided */
if ($addressType === 'individual_house' && $houseImgB64 !== '') {
    if (preg_match('/^data:image\/(png|jpe?g|webp|gif);base64,/i', $houseImgB64, $m)) {
        $ext  = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
        $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $houseImgB64));
        if ($data !== false && strlen($data) <= 3 * 1024 * 1024) {
            $folder = 'uploads/houses/' . date('Ymd') . '/' . bin2hex(random_bytes(5));
            $absDir = rtrim(__DIR__ . '/../', '/\\') . '/' . $folder;
            if (!is_dir($absDir)) @mkdir($absDir, 0775, true);
            $fileName = 'house.' . $ext;
            if (@file_put_contents($absDir . '/' . $fileName, $data) !== false) {
                $addressJson['house_image'] = $folder . '/' . $fileName;
            }
        }
    }
}

$addressJsonFinal = json_encode($addressJson, JSON_UNESCAPED_UNICODE);

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

    /* =========================================
       UPDATE
       ========================================= */
    if ($id > 0) {
        $check = $pdo->prepare("SELECT id, customer_id FROM customers WHERE id = ? LIMIT 1");
        $check->execute([$id]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);
        if (!$existing) jsonResponse(false, 'Customer not found.');

        if ($password !== '' && strlen($password) < 3) {
            jsonResponse(false, 'New password must be at least 3 characters.');
        }

        /* Backfill customer_id if missing */
        $customerId = $existing['customer_id'] ?? '';
        if ($customerId === '' || $customerId === null) {
            $customerId = generateCustomerId($pdo);
        }

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

    /* =========================================
       INSERT
       ========================================= */
    if ($password === '')      jsonResponse(false, 'Password is required.');
    if (strlen($password) < 3) jsonResponse(false, 'Password must be at least 3 characters.');

    $hash       = password_hash($password, PASSWORD_DEFAULT);
    $customerId = generateCustomerId($pdo);

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
        $addressJsonFinal,
        $hash,
        $initWallet > 0 ? $initWallet : 0,
        $status
    ]);

    $newId = (int)$pdo->lastInsertId();

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
                $newId, $fullName, $mobileNumber, $txn,
                $initWallet, $initWallet,
                $initNote !== '' ? $initNote : 'Initial wallet balance',
                (int)$_SESSION['admin_id'],
                $_SESSION['admin_name'] ?? 'Admin'
            ]);
        } catch (PDOException $e) { /* ignore */ }
    }

    jsonResponse(true, 'Customer added successfully.', [
        'id'          => $newId,
        'customer_id' => $customerId,
        'code'        => $customerId
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error: ' . $e->getMessage());
}