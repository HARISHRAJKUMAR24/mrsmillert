<?php
/* =========================================================
   MRS MILL@ — AJAX LOGIN ENDPOINT
   File: ./ajax/login.php
   Checks admin table first, then staff table.
   ========================================================= */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Please enter username and password.']);
    exit;
}

try {
    /* ---------------- 1. CHECK ADMIN TABLE (by username) ---------------- */
    $stmt = $pdo->prepare(
        "SELECT id, username AS full_name, password AS password_hash
         FROM admin
         WHERE username = ?
         LIMIT 1"
    );
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {

        $token = bin2hex(random_bytes(32));

        $pdo->prepare("UPDATE admin SET token = ? WHERE id = ?")
            ->execute([$token, $admin['id']]);

        $_SESSION['admin_id']    = (int)$admin['id'];
        $_SESSION['admin_name']  = $admin['full_name'];
        $_SESSION['admin_token'] = $token;
        $_SESSION['admin_role']  = 'admin';

        echo json_encode([
            'success' => true,
            'message' => 'Login successful.',
            'role'    => 'admin'
        ]);
        exit;
    }

    /* ---------------- 2. CHECK STAFF TABLE (by mobile OR email) ---------------- */
    $stmt = $pdo->prepare(
        "SELECT id, staff_code, full_name, mobile_number, email_address,
                password_hash, role, status
         FROM staff
         WHERE (mobile_number = ? OR email_address = ?)
         LIMIT 1"
    );
    $stmt->execute([$username, $username]);
    $staff = $stmt->fetch();

    if ($staff) {

        if ((int)$staff['status'] !== 1) {
            echo json_encode([
                'success' => false,
                'message' => 'Your account is inactive. Contact admin.'
            ]);
            exit;
        }

        if (!password_verify($password, $staff['password_hash'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid username or password.'
            ]);
            exit;
        }

        $token = bin2hex(random_bytes(32));

        $pdo->prepare(
            "UPDATE staff SET token = ?, last_login_at = NOW() WHERE id = ?"
        )->execute([$token, $staff['id']]);

        $_SESSION['admin_id']    = (int)$staff['id'];
        $_SESSION['admin_name']  = $staff['full_name'];
        $_SESSION['admin_token'] = $token;
        $_SESSION['admin_role']  = $staff['role']; /* 'admin' or 'staff' */

        echo json_encode([
            'success' => true,
            'message' => 'Login successful.',
            'role'    => $staff['role']
        ]);
        exit;
    }

    /* ---------------- NO MATCH ---------------- */
    echo json_encode([
        'success' => false,
        'message' => 'Invalid username or password.'
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error. Please try again.'
    ]);
    exit;
}