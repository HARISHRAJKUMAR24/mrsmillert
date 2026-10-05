<?php
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
$apartmentId    = (int)($_POST['apartment_id'] ?? 0);
$apartmentCode  = trim($_POST['apartment_code'] ?? '');
$apartmentName  = trim($_POST['apartment_name'] ?? '');
$division       = trim($_POST['division'] ?? '');
$divisionCharge = (float)($_POST['division_charge'] ?? 0);

/* ---------- Validate ---------- */
if ($fullName === '')     jsonResponse(false, 'Full name is required.');
if ($mobileNumber === '') jsonResponse(false, 'Mobile number is required.');
if (!preg_match('/^[0-9]{10,15}$/', $mobileNumber)) jsonResponse(false, 'Invalid mobile number.');

$status = $status ? 1 : 0;

try {
    /* Ensure wallet_balance exists */
    try {
        $pdo->exec("ALTER TABLE customers ADD COLUMN wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER division_charge");
    } catch (PDOException $e) { /* ignore */ }

    /* ---------- Duplicate mobile check ---------- */
    $dupSql = "SELECT id FROM customers WHERE mobile_number = ?";
    $dupParams = [$mobileNumber];
    if ($id > 0) {
        $dupSql .= " AND id <> ?";
        $dupParams[] = $id;
    }
    $dupSql .= " LIMIT 1";
    $dup = $pdo->prepare($dupSql);
    $dup->execute($dupParams);
    if ($dup->fetch()) {
        jsonResponse(false, 'A customer with this mobile already exists.');
    }

    /* =========================================
       UPDATE
       ========================================= */
    if ($id > 0) {
        /* Ensure customer exists */
        $check = $pdo->prepare("SELECT id FROM customers WHERE id = ? LIMIT 1");
        $check->execute([$id]);
        if (!$check->fetch()) jsonResponse(false, 'Customer not found.');

        if ($password !== '' && strlen($password) < 3) {
            jsonResponse(false, 'New password must be at least 3 characters.');
        }

        $sql = "UPDATE customers SET
                    full_name = ?,
                    mobile_number = ?,
                    apartment_id = ?,
                    apartment_code = ?,
                    apartment_name = ?,
                    division = ?,
                    division_charge = ?,
                    status = ?,
                    updated_at = NOW()";
        $params = [
            $fullName,
            $mobileNumber,
            $apartmentId > 0 ? $apartmentId : null,
            $apartmentCode,
            $apartmentName,
            $division,
            $divisionCharge,
            $status
        ];

        if ($password !== '') {
            $sql .= ", password_hash = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $upd = $pdo->prepare($sql);
        $upd->execute($params);

        jsonResponse(true, 'Customer updated successfully.', ['id' => $id]);
    }

    /* =========================================
       INSERT
       ========================================= */
    if ($password === '')     jsonResponse(false, 'Password is required.');
    if (strlen($password) < 3) jsonResponse(false, 'Password must be at least 3 characters.');

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $ins = $pdo->prepare(
        "INSERT INTO customers
            (full_name, mobile_number, password_hash,
             apartment_id, apartment_code, apartment_name,
             division, division_charge, wallet_balance,
             profile_completed, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, NOW(), NOW())"
    );
    $ins->execute([
        $fullName,
        $mobileNumber,
        $hash,
        $apartmentId > 0 ? $apartmentId : null,
        $apartmentCode,
        $apartmentName,
        $division,
        $divisionCharge,
        $status
    ]);

    jsonResponse(true, 'Customer added successfully.', ['id' => (int)$pdo->lastInsertId()]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error: ' . $e->getMessage());
}