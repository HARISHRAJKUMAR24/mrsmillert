<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

try {
    /* Ensure wallet_balance column exists */
    try {
        $pdo->exec(
            "ALTER TABLE customers
             ADD COLUMN wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00
             AFTER division_charge"
        );
    } catch (PDOException $e) { /* ignore */ }

    $stmt = $pdo->query(
        "SELECT id, full_name, mobile_number,
                apartment_id, apartment_code, apartment_name,
                division, division_charge, wallet_balance,
                profile_completed, status, last_login_at,
                created_at, updated_at
         FROM customers
         ORDER BY id DESC"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = array_map(function ($r) {
        return [
            'id'                => (int)$r['id'],
            'full_name'         => $r['full_name'],
            'mobile_number'     => $r['mobile_number'],
            'apartment_id'      => $r['apartment_id'] ? (int)$r['apartment_id'] : null,
            'apartment_code'    => $r['apartment_code'] ?: '',
            'apartment_name'    => $r['apartment_name'] ?: '',
            'division'          => $r['division'] ?: '',
            'division_charge'   => (float)$r['division_charge'],
            'wallet_balance'    => (float)$r['wallet_balance'],
            'profile_completed' => (int)$r['profile_completed'],
            'status'            => (int)$r['status'],
            'last_login_at'     => $r['last_login_at'],
            'created_at'        => $r['created_at'],
            'updated_at'        => $r['updated_at'],
        ];
    }, $rows);

    jsonResponse(true, 'OK', $out);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load customers.');
}