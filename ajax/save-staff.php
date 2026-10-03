<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.');
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) jsonResponse(false, 'Not logged in.');
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') jsonResponse(false, 'Admin access required.');

$fullName = trim($_POST['full_name'] ?? '');
$mobile   = trim($_POST['mobile_number'] ?? '');
$email    = trim($_POST['email_address'] ?? '');
$role     = trim($_POST['role'] ?? 'staff');
$password = trim($_POST['password'] ?? '');
$status   = isset($_POST['status']) ? (int)$_POST['status'] : 1;

if ($fullName === '' || mb_strlen($fullName) < 3) jsonResponse(false, 'Full name must be at least 3 characters.');
if (!preg_match('/^[0-9]{10,15}$/', $mobile)) jsonResponse(false, 'Mobile must be 10–15 digits.');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'Invalid email.');
if (!in_array($role, ['admin', 'staff'], true)) $role = 'staff';
if (strlen($password) < 6) jsonResponse(false, 'Password must be at least 6 characters.');
$status = $status === 1 ? 1 : 0;

try {
    $dup = $pdo->prepare("SELECT id FROM staff WHERE mobile_number = ? LIMIT 1");
    $dup->execute([$mobile]);
    if ($dup->fetch()) jsonResponse(false, 'This mobile number is already registered.');

    $cStmt = $pdo->query(
        "SELECT staff_code FROM staff WHERE staff_code LIKE 'STF%'
         ORDER BY CAST(SUBSTRING(staff_code, 4) AS UNSIGNED) DESC LIMIT 1"
    );
    $last = $cStmt->fetch();

    if ($last && preg_match('/STF(\d+)/', $last['staff_code'], $m)) {
        $next = (int)$m[1] + 1;
    } else {
        $next = 1;
    }
    $staffCode = 'STF' . str_pad($next, 4, '0', STR_PAD_LEFT);

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        "INSERT INTO staff
            (staff_code, full_name, mobile_number, email_address,
             password_hash, role, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$staffCode, $fullName, $mobile, $email ?: null, $hash, $role, $status]);

    jsonResponse(true, 'Staff created successfully.', [
        'id'   => (int)$pdo->lastInsertId(),
        'code' => $staffCode
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to save staff.');
}