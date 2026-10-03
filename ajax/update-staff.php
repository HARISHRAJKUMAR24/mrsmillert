<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.');
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) jsonResponse(false, 'Not logged in.');
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') jsonResponse(false, 'Admin access required.');

$id       = (int)($_POST['id'] ?? 0);
$fullName = trim($_POST['full_name'] ?? '');
$mobile   = trim($_POST['mobile_number'] ?? '');
$email    = trim($_POST['email_address'] ?? '');
$role     = trim($_POST['role'] ?? 'staff');
$status   = isset($_POST['status']) ? (int)$_POST['status'] : 1;

if ($id <= 0) jsonResponse(false, 'Invalid staff ID.');
if ($fullName === '' || mb_strlen($fullName) < 3) jsonResponse(false, 'Full name must be at least 3 characters.');
if (!preg_match('/^[0-9]{10,15}$/', $mobile)) jsonResponse(false, 'Mobile must be 10–15 digits.');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'Invalid email.');
if (!in_array($role, ['admin', 'staff'], true)) $role = 'staff';
$status = $status === 1 ? 1 : 0;

try {
    $dup = $pdo->prepare("SELECT id FROM staff WHERE mobile_number = ? AND id <> ? LIMIT 1");
    $dup->execute([$mobile, $id]);
    if ($dup->fetch()) jsonResponse(false, 'Another staff uses this mobile number.');

    $stmt = $pdo->prepare(
        "UPDATE staff
         SET full_name = ?, mobile_number = ?, email_address = ?, role = ?, status = ?
         WHERE id = ?"
    );
    $stmt->execute([$fullName, $mobile, $email ?: null, $role, $status, $id]);

    jsonResponse(true, 'Staff updated successfully.', ['id' => $id]);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to update staff.');
}