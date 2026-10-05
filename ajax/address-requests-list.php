<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

/* Admin + staff both allowed */
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

try {
    /*
       We add a `status` column dynamically if it doesn't exist,
       so the page works even on older DB dumps.
    */
    try {
        $pdo->exec(
            "ALTER TABLE customer_address_requests
             ADD COLUMN status TINYINT(1) NOT NULL DEFAULT 0"
        );
    } catch (PDOException $e) {
        /* Column already exists — ignore */
    }

    $stmt = $pdo->query(
        "SELECT id, customer_name, customer_mobile,
                requested_apartment, requested_division,
                status, created_at, updated_at
         FROM customer_address_requests
         ORDER BY id DESC"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = array_map(function ($r) {
        return [
            'id'                  => (int)$r['id'],
            'customer_name'       => $r['customer_name'],
            'customer_mobile'     => $r['customer_mobile'],
            'requested_apartment' => $r['requested_apartment'],
            'requested_division'  => $r['requested_division'],
            'status'              => (int)$r['status'],
            'created_at'          => $r['created_at'],
            'updated_at'          => $r['updated_at'],
        ];
    }, $rows);

    jsonResponse(true, 'OK', $out);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load requests.');
}