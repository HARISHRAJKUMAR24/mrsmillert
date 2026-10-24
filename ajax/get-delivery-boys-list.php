<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

try {
    $stmt = $pdo->query(
        "SELECT id, delivery_code, full_name, mobile_number
         FROM delivery_boys
         WHERE status = 1
         ORDER BY full_name ASC"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'            => (int)$r['id'],
            'delivery_code' => $r['delivery_code'],
            'full_name'     => $r['full_name'],
            'mobile_number' => $r['mobile_number'],
        ];
    }
    jsonResponse(true, 'OK', $out);
} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load delivery boys.');
}