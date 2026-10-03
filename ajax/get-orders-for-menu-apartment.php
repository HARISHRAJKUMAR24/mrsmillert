<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

$menuId = (int)($_GET['menu_id'] ?? 0);
if ($menuId <= 0) jsonResponse(false, 'Invalid menu.');

/* From boy is now REQUIRED */
$fromBoyId = (int)($_GET['from_boy_id'] ?? 0);
if ($fromBoyId <= 0) jsonResponse(false, 'Please select a current delivery boy.');

/* Apartment codes (single or JSON array) */
$apartmentCode  = trim($_GET['apartment_code'] ?? '');
$apartmentCodes = [];

if ($apartmentCode !== '') {
    $apartmentCodes[] = $apartmentCode;
}

if (!empty($_GET['apartment_codes'])) {
    $decoded = json_decode($_GET['apartment_codes'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $c) {
            $c = trim((string)$c);
            if ($c !== '' && !in_array($c, $apartmentCodes, true)) {
                $apartmentCodes[] = $c;
            }
        }
    }
}

if (empty($apartmentCodes)) {
    jsonResponse(false, 'No apartments selected.');
}

try {
    /* Menu window */
    $mStmt = $pdo->prepare(
        "SELECT id, start_at, end_at FROM menus WHERE id = ? LIMIT 1"
    );
    $mStmt->execute([$menuId]);
    $menu = $mStmt->fetch(PDO::FETCH_ASSOC);
    if (!$menu) jsonResponse(false, 'Menu not found.');

    $placeholders = implode(',', array_fill(0, count($apartmentCodes), '?'));

    $sql = "SELECT o.id, o.order_code, o.customer_name, o.customer_mobile,
                   o.apartment_code, o.apartment_name, o.division,
                   o.delivery_boy_id, o.total_amount, o.status,
                   o.delivery_status, o.payment_status, o.created_at,
                   b.full_name AS boy_name, b.delivery_code AS boy_code
            FROM orders o
            LEFT JOIN delivery_boys b ON b.id = o.delivery_boy_id
            WHERE o.apartment_code IN ($placeholders)
              AND o.delivery_boy_id = ?
              AND o.status <> 'cancelled'
              AND o.status <> 'delivered'
              AND o.created_at BETWEEN ? AND ?
            ORDER BY o.apartment_name ASC, o.id DESC";

    $params = array_merge(
        $apartmentCodes,
        [$fromBoyId, $menu['start_at'], $menu['end_at']]
    );

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = array_map(function ($r) {
        return [
            'id'              => (int)$r['id'],
            'order_code'      => $r['order_code'],
            'customer_name'   => $r['customer_name'],
            'customer_mobile' => $r['customer_mobile'],
            'apartment_code'  => $r['apartment_code'],
            'apartment_name'  => $r['apartment_name'],
            'division'        => $r['division'],
            'delivery_boy_id' => (int)$r['delivery_boy_id'],
            'boy_name'        => $r['boy_name'] ?: '',
            'boy_code'        => $r['boy_code'] ?: '',
            'total_amount'    => (float)$r['total_amount'],
            'status'          => $r['status'],
            'delivery_status' => $r['delivery_status'],
            'payment_status'  => $r['payment_status'],
            'created_at'      => $r['created_at'],
        ];
    }, $rows);

    jsonResponse(true, 'OK', $out);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load orders.');
}