<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

$mobile = trim($_GET['mobile'] ?? '');
if ($mobile === '') {
    jsonResponse(false, 'Mobile number required.');
}

try {
    $stmt = $pdo->prepare(
        "SELECT id, full_name, mobile_number,
                apartment_id, apartment_code, apartment_name,
                division, division_charge,
                COALESCE(wallet_balance, 0) AS wallet_balance
         FROM customers
         WHERE mobile_number = ?
         LIMIT 1"
    );
    $stmt->execute([$mobile]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$c) {
        jsonResponse(true, 'New customer.', ['found' => false]);
    }

    jsonResponse(true, 'OK', [
        'found'           => true,
        'id'              => (int)$c['id'],
        'name'            => $c['full_name'],
        'mobile'          => $c['mobile_number'],
        'apartment_id'    => (int)($c['apartment_id'] ?? 0),
        'apartment_code'  => $c['apartment_code'] ?? '',
        'apartment_name'  => $c['apartment_name'] ?? '',
        'division'        => $c['division'] ?? '',
        'division_charge' => (float)($c['division_charge'] ?? 0),
        'wallet_balance'  => (float)$c['wallet_balance'],
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Lookup failed.');
}