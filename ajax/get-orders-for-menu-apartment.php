<?php
/* =========================================================
   MRS MILL@ — AJAX: ORDERS FOR SELECTED GROUPS
   File: ./ajax/get-orders-for-menu-apartment.php

   Query params:
     menu_id       int
     from_boy_id   int
     row_keys      JSON array of "<aptCode>|<divisionLabel>"
                   or "IH_<hash>|" for individual houses
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

$menuId    = (int)($_GET['menu_id'] ?? 0);
$fromBoyId = (int)($_GET['from_boy_id'] ?? 0);
$rowKeysRaw = $_GET['row_keys'] ?? '[]';

if ($menuId <= 0)    jsonResponse(false, 'Invalid menu.');
if ($fromBoyId <= 0) jsonResponse(false, 'Please select a current delivery boy.');

$rowKeys = json_decode($rowKeysRaw, true);
if (!is_array($rowKeys) || count($rowKeys) === 0) {
    jsonResponse(false, 'No groups selected.');
}

/* Split into apartment rows vs IH rows */
$apartmentGroups = [];   // [ [code, division], ... ]
$ihCodes         = [];   // [ 'IH_xxx', ... ]

foreach ($rowKeys as $rk) {
    $rk = (string)$rk;
    if ($rk === '') continue;

    $parts = explode('|', $rk, 2);
    $code  = $parts[0] ?? '';
    $div   = $parts[1] ?? '';

    if ($code === '') continue;

    if (strpos($code, 'IH_') === 0) {
        $ihCodes[$code] = true;
    } else {
        $apartmentGroups[] = ['code' => $code, 'division' => $div];
    }
}

if (empty($apartmentGroups) && empty($ihCodes)) {
    jsonResponse(false, 'No valid groups.');
}

try {
    $mStmt = $pdo->prepare(
        "SELECT id, start_at, end_at FROM menus WHERE id = ? LIMIT 1"
    );
    $mStmt->execute([$menuId]);
    $menu = $mStmt->fetch(PDO::FETCH_ASSOC);
    if (!$menu) jsonResponse(false, 'Menu not found.');

    /* Build OR filter for apartment groups */
    $aptClauses  = [];
    $aptParams   = [];

    foreach ($apartmentGroups as $g) {
        $aptClauses[] = "(
            JSON_UNQUOTE(JSON_EXTRACT(o.address_json, '$.apartment_code')) = ?
            AND JSON_UNQUOTE(JSON_EXTRACT(o.address_json, '$.division_label')) = ?
        )";
        $aptParams[] = $g['code'];
        $aptParams[] = $g['division'];
    }

    /* Build OR filter for IH groups (match by md5 hash) */
    $ihClauses = [];
    $ihParams  = [];

    foreach (array_keys($ihCodes) as $ihCode) {
        $ihClauses[] = "MD5(TRIM(JSON_UNQUOTE(JSON_EXTRACT(o.address_json, '$.full_address')))) LIKE ?";
        $ihParams[]  = substr($ihCode, 3) . '%';   // strip 'IH_'
    }

    $allFilters = array_merge($aptClauses, $ihClauses);
    if (empty($allFilters)) {
        jsonResponse(false, 'No valid filters.');
    }

    $filterSql = '(' . implode(' OR ', $allFilters) . ')';

    $sql = "SELECT o.id, o.order_code,
                   o.customer_id,
                   c.full_name     AS customer_name,
                   c.mobile_number AS customer_mobile,
                   o.address_type,
                   CASE WHEN JSON_VALID(o.address_json)
                        THEN JSON_UNQUOTE(JSON_EXTRACT(o.address_json, '$.apartment_code'))
                        ELSE NULL END AS apartment_code,
                   CASE WHEN JSON_VALID(o.address_json)
                        THEN JSON_UNQUOTE(JSON_EXTRACT(o.address_json, '$.apartment_name'))
                        ELSE NULL END AS apartment_name,
                   CASE WHEN JSON_VALID(o.address_json)
                        THEN JSON_UNQUOTE(JSON_EXTRACT(o.address_json, '$.division_label'))
                        ELSE NULL END AS division,
                   CASE WHEN JSON_VALID(o.address_json)
                        THEN JSON_UNQUOTE(JSON_EXTRACT(o.address_json, '$.full_address'))
                        ELSE NULL END AS full_address,
                   o.delivery_boy_id, o.total_amount, o.status,
                   o.delivery_status, o.payment_status, o.created_at,
                   b.full_name AS boy_name, b.delivery_code AS boy_code
            FROM orders o
            LEFT JOIN customers c     ON c.customer_id = o.customer_id
            LEFT JOIN delivery_boys b ON b.id          = o.delivery_boy_id
            WHERE o.delivery_boy_id = ?
              AND o.status <> 'cancelled'
              AND o.status <> 'delivered'
              AND o.delivery_mode = 'delivery'
              AND o.created_at BETWEEN ? AND ?
              AND $filterSql
            ORDER BY o.created_at DESC, o.id DESC";

    $params = array_merge(
        [$fromBoyId, $menu['start_at'], $menu['end_at']],
        $aptParams,
        $ihParams
    );

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'              => (int)$r['id'],
            'order_code'      => $r['order_code'],
            'customer_name'   => $r['customer_name']   ?: '—',
            'customer_mobile' => $r['customer_mobile'] ?: '—',
            'address_type'    => $r['address_type']    ?: 'apartment',
            'apartment_code'  => $r['apartment_code'],
            'apartment_name'  => $r['apartment_name'],
            'division'        => $r['division'],
            'full_address'    => $r['full_address'],
            'delivery_boy_id' => (int)$r['delivery_boy_id'],
            'boy_name'        => $r['boy_name'] ?: '',
            'boy_code'        => $r['boy_code'] ?: '',
            'total_amount'    => (float)$r['total_amount'],
            'status'          => $r['status'],
            'delivery_status' => $r['delivery_status'],
            'payment_status'  => $r['payment_status'],
            'created_at'      => $r['created_at'],
        ];
    }

    jsonResponse(true, 'OK', $out);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load orders: ' . $e->getMessage());
}