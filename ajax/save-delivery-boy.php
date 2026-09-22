<?php
/* =========================================================
   MRS MILL@ — AJAX: SAVE DELIVERY BOY
   File: ./ajax/save-delivery-boy.php
   Handles BOTH add (mode=add) and edit (mode=edit)
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

/* -----------------------------------------
   READ INPUT
----------------------------------------- */
$mode        = trim($_POST['mode'] ?? 'add');
$id          = (int) ($_POST['id'] ?? 0);
$full_name   = trim($_POST['full_name'] ?? '');
$mobile      = trim($_POST['mobile_number'] ?? '');
$email       = trim($_POST['email_address'] ?? '');
$branchId    = (int) ($_POST['branch_id'] ?? 0);
$password    = trim($_POST['password'] ?? '');
$status      = isset($_POST['status']) ? (int) $_POST['status'] : 1;

/* -----------------------------------------
   VALIDATION
----------------------------------------- */
if ($mode !== 'add' && $mode !== 'edit') {
    jsonResponse(false, 'Invalid mode.');
}

if ($mode === 'edit' && $id <= 0) {
    jsonResponse(false, 'Invalid delivery boy ID.');
}

if ($full_name === '' || mb_strlen($full_name) < 3) {
    jsonResponse(false, 'Full name must be at least 3 characters.');
}

if ($mobile === '' || !preg_match('/^[0-9]{10,15}$/', $mobile)) {
    jsonResponse(false, 'Mobile number must be 10–15 digits.');
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Please enter a valid email address.');
}

if ($branchId <= 0) {
    jsonResponse(false, 'Please select a branch.');
}

if ($status !== 0 && $status !== 1) {
    $status = 1;
}

/* -----------------------------------------
   VERIFY BRANCH
----------------------------------------- */
try {
    $bc = $pdo->prepare("SELECT id FROM settings_branches WHERE id = ? LIMIT 1");
    $bc->execute([$branchId]);
    if (!$bc->fetch()) {
        jsonResponse(false, 'Selected branch does not exist.');
    }
} catch (PDOException $e) {
    jsonResponse(false, 'Server error. Please try again.');
}

/* -----------------------------------------
   DUPLICATE MOBILE CHECK
----------------------------------------- */
try {
    if ($mode === 'add') {
        $dup = $pdo->prepare(
            "SELECT id FROM delivery_boys WHERE mobile_number = ? LIMIT 1"
        );
        $dup->execute([$mobile]);
    } else {
        $dup = $pdo->prepare(
            "SELECT id FROM delivery_boys WHERE mobile_number = ? AND id <> ? LIMIT 1"
        );
        $dup->execute([$mobile, $id]);
    }

    if ($dup->fetch()) {
        jsonResponse(false, 'This mobile number is already registered.');
    }
} catch (PDOException $e) {
    jsonResponse(false, 'Server error. Please try again.');
}

/* -----------------------------------------
   ADD
----------------------------------------- */
if ($mode === 'add') {

    if ($password === '' || strlen($password) < 6) {
        jsonResponse(false, 'Password must be at least 6 characters.');
    }

    /* Generate delivery_code: DB000001, DB000002, ... */
    try {
        $codeStmt = $pdo->query(
            "SELECT delivery_code FROM delivery_boys
             WHERE delivery_code LIKE 'DB%'
             ORDER BY id DESC LIMIT 1"
        );
        $last = $codeStmt->fetch();

        if ($last && preg_match('/DB(\d+)/', $last['delivery_code'], $m)) {
            $nextNum = (int)$m[1] + 1;
        } else {
            $nextNum = 1;
        }

        $delivery_code = 'DB' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        jsonResponse(false, 'Server error. Please try again.');
    }

    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            "INSERT INTO delivery_boys
                (delivery_code, full_name, mobile_number,
                 email_address, password_hash, branch_id, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        $stmt->execute([
            $delivery_code,
            $full_name,
            $mobile,
            $email !== '' ? $email : null,
            $hash,
            $branchId,
            $status
        ]);

        jsonResponse(true, 'Delivery boy added successfully.', [
            'id'   => (int)$pdo->lastInsertId(),
            'code' => $delivery_code
        ]);

    } catch (PDOException $e) {
        jsonResponse(false, 'Failed to add delivery boy.');
    }
}

/* -----------------------------------------
   EDIT
----------------------------------------- */
try {
    /* Verify the boy exists */
    $chk = $pdo->prepare("SELECT id FROM delivery_boys WHERE id = ? LIMIT 1");
    $chk->execute([$id]);
    if (!$chk->fetch()) {
        jsonResponse(false, 'Delivery boy not found.');
    }

    $stmt = $pdo->prepare(
        "UPDATE delivery_boys
         SET full_name     = ?,
             mobile_number = ?,
             email_address = ?,
             branch_id     = ?,
             status        = ?
         WHERE id = ?"
    );

    $stmt->execute([
        $full_name,
        $mobile,
        $email !== '' ? $email : null,
        $branchId,
        $status,
        $id
    ]);

    jsonResponse(true, 'Delivery boy updated successfully.', ['id' => $id]);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to update delivery boy.');
}