<?php
/* =========================================================
   MRS MILL@ — AJAX: MENU APARTMENT GROUPS (for reassign)
   File: ./ajax/get-menu-apartments.php

   Returns per-division groups:
     apartment orders → { apartment_code, division_label, apartment_name, pending_count }
     individual houses → { apartment_code = "IH_<hash>", division_label = "", apartment_name = address, pending_count }
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

$menuId    = (int)($_GET['menu_id'] ?? 0);
$fromBoyId = (int)($_GET['from_boy_id'] ?? 0);

if ($menuId <= 0)    jsonResponse(false, 'Invalid menu.');
if ($fromBoyId <= 0) jsonResponse(false, 'Please select a current delivery boy.');

try {
    $mStmt = $pdo->prepare(
        "SELECT id, menu_code, menu_name, start_at, end_at
         FROM menus WHERE id = ? LIMIT 1"
    );
    $mStmt->execute([$menuId]);
    $menu = $mStmt->fetch(PDO::FETCH_ASSOC);
    if (!$menu) jsonResponse(false, 'Menu not found.');

    /*
       Group orders into rows:
         - Apartment → one row per (apartment_code, division_label)
         - Individual House → one row per unique full_address
       Each row carries a synthetic "row_key" that the JS will use for selection.
    */
    $sql = "
        SELECT
            o.address_type,
            CASE WHEN JSON_VALID(o.address_json)
                 THEN JSON_UNQUOTE(JSON_EXTRACT(o.address_json, '$.apartment_code'))
                 ELSE NULL END AS apartment_code,
            CASE WHEN JSON_VALID(o.address_json)
                 THEN JSON_UNQUOTE(JSON_EXTRACT(o.address_json, '$.apartment_name'))
                 ELSE NULL END AS apartment_name,
            CASE WHEN JSON_VALID(o.address_json)
                 THEN JSON_UNQUOTE(JSON_EXTRACT(o.address_json, '$.division_label'))
                 ELSE NULL END AS division_label,
            CASE WHEN JSON_VALID(o.address_json)
                 THEN JSON_UNQUOTE(JSON_EXTRACT(o.address_json, '$.full_address'))
                 ELSE NULL END AS full_address,
            COUNT(*) AS pending_count
        FROM orders o
        WHERE o.delivery_boy_id = ?
          AND o.status <> 'cancelled'
          AND o.status <> 'delivered'
          AND o.delivery_mode = 'delivery'
          AND o.created_at BETWEEN ? AND ?
        GROUP BY
            o.address_type, apartment_code, apartment_name, division_label, full_address
        ORDER BY apartment_name ASC, division_label ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fromBoyId, $menu['start_at'], $menu['end_at']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];

    foreach ($rows as $r) {
        $type = $r['address_type'] ?: 'apartment';

        if ($type === 'individual_house') {
            $addr = trim((string)($r['full_address'] ?? ''));
            if ($addr === '') continue;

            $code = 'IH_' . substr(md5($addr), 0, 12);

            $out[] = [
                'row_key'         => $code . '|',                 // no division
                'address_type'    => 'individual_house',
                'apartment_code'  => $code,
                'apartment_name'  => $addr,
                'division_label'  => '',
                'pending_count'   => (int)$r['pending_count'],
                'is_individual'   => true,
            ];
        } else {
            $aptCode = trim((string)($r['apartment_code'] ?? ''));
            if ($aptCode === '') continue;

            $div = trim((string)($r['division_label'] ?? ''));

            $out[] = [
                'row_key'         => $aptCode . '|' . $div,
                'address_type'    => 'apartment',
                'apartment_code'  => $aptCode,
                'apartment_name'  => $r['apartment_name'] ?: $aptCode,
                'division_label'  => $div,
                'pending_count'   => (int)$r['pending_count'],
                'is_individual'   => false,
            ];
        }
    }

    jsonResponse(true, 'OK', $out);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load apartments: ' . $e->getMessage());
}