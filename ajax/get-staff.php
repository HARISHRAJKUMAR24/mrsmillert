<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    jsonResponse(false, 'Admin access required.');
}

try {
    $stmt = $pdo->query(
        "SELECT id, staff_code, full_name, mobile_number, email_address,
                role, status, last_login_at, created_at
         FROM staff
         ORDER BY id DESC"
    );
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'            => (int)$r['id'],
            'staff_code'    => $r['staff_code'],
            'full_name'     => $r['full_name'],
            'mobile_number' => $r['mobile_number'],
            'email_address' => $r['email_address'],
            'role'          => $r['role'],
            'status'        => (int)$r['status'],
            'last_login_at' => $r['last_login_at'],
            'created_at'    => $r['created_at']
        ];
    }

    jsonResponse(true, 'OK', $out);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load staff.');
}