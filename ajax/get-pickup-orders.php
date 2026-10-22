<?php
/* =========================================================
   MRS MILL@ — AJAX: GET PICKUP ORDERS (admin panel)
   File: ./ajax/get-pickup-orders.php

   Fix:
   - customer_name / customer_mobile  → JOIN customers
   - apartment_code / apartment_name  → orders.address_json
   - division                         → orders.address_json (division_label)
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

$filter = trim($_GET['filter'] ?? 'all');
$date   = trim($_GET['date']   ?? '');
$from   = trim($_GET['from']   ?? '');
$to     = trim($_GET['to']     ?? '');
$q      = trim($_GET['q']      ?? '');

/* Always restrict to pickup orders */
$where  = ["o.delivery_mode = 'pickup'"];
$params = [];

/* ---------- Status / payment filter ---------- */
if ($filter === 'paid') {
    $where[] = "o.payment_status = 'paid'";
} elseif ($filter === 'unpaid') {
    $where[] = "o.payment_status = 'unpaid'";
} elseif ($filter === 'pending') {
    $where[] = "o.status = 'pending'";
} elseif ($filter === 'processing') {
    $where[] = "o.status = 'processing'";
} elseif ($filter === 'delivered') {
    $where[] = "o.status = 'delivered'";
} elseif ($filter === 'cancelled') {
    $where[] = "o.status = 'cancelled'";
} elseif ($filter === 'today') {
    $where[] = "DATE(o.created_at) = CURDATE()";
}

/* ---------- Date filter ---------- */
if ($date !== '' && $date !== 'all') {
    if ($date === 'today') {
        $where[] = "DATE(o.created_at) = CURDATE()";
    } elseif ($date === 'yesterday') {
        $where[] = "DATE(o.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
    } elseif ($date === 'week') {
        $where[] = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)";
    } elseif ($date === 'month') {
        $where[] = "YEAR(o.created_at) = YEAR(CURDATE())
                    AND MONTH(o.created_at) = MONTH(CURDATE())";
    }
}

/* ---------- Custom range ---------- */
if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $where[]  = "DATE(o.created_at) >= ?";
    $params[] = $from;
}
if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $where[]  = "DATE(o.created_at) <= ?";
    $params[] = $to;
}

/* ---------- Search (uses joined columns) ---------- */
if ($q !== '') {
    $where[] = "(o.order_code LIKE ?
                 OR c.full_name LIKE ?
                 OR c.mobile_number LIKE ?
                 OR o.pickup_branch_name LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

try {

    $sql = "SELECT
                o.id,
                o.order_code,
                o.customer_id,
                c.full_name       AS customer_name,
                c.mobile_number   AS customer_mobile,

                CASE WHEN JSON_VALID(o.address_json)
                     THEN JSON_UNQUOTE(JSON_EXTRACT(o.address_json, '$.apartment_code'))
                     ELSE NULL END AS apartment_code,
                CASE WHEN JSON_VALID(o.address_json)
                     THEN JSON_UNQUOTE(JSON_EXTRACT(o.address_json, '$.apartment_name'))
                     ELSE NULL END AS apartment_name,
                CASE WHEN JSON_VALID(o.address_json)
                     THEN JSON_UNQUOTE(JSON_EXTRACT(o.address_json, '$.division_label'))
                     ELSE NULL END AS division,

                o.division_charge,
                o.subtotal,
                o.total_amount,
                o.delivery_mode,
                o.pickup_branch_id,
                o.pickup_branch_name,
                o.products_json,
                o.status,
                o.delivery_status,
                o.payment_status,
                o.paid_at,
                o.created_at
            FROM orders o
            LEFT JOIN customers c ON c.customer_id = o.customer_id
            $whereSql
            ORDER BY o.id DESC
            LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $out = [];

    foreach ($rows as $r) {

        $products = json_decode($r['products_json'] ?? '[]', true);
        if (!is_array($products)) $products = [];

        $out[] = [
            'id'                 => (int)$r['id'],
            'order_code'         => $r['order_code'],
            'customer_id'        => $r['customer_id'],
            'customer_name'      => $r['customer_name']   ?: '—',
            'customer_mobile'    => $r['customer_mobile'] ?: '—',
            'delivery_mode'      => $r['delivery_mode'] ?: 'pickup',
            'apartment_code'     => $r['apartment_code'],
            'apartment_name'     => $r['apartment_name'],
            'division'           => $r['division'],
            'division_charge'    => (float)$r['division_charge'],
            'pickup_branch_id'   => (int)$r['pickup_branch_id'],
            'pickup_branch_name' => $r['pickup_branch_name'] ?: '',
            'subtotal'           => (float)$r['subtotal'],
            'total_amount'       => (float)$r['total_amount'],
            'products'           => $products,
            'status'             => $r['status'],
            'delivery_status'    => $r['delivery_status'],
            'payment_status'     => $r['payment_status'],
            'paid_at'            => $r['paid_at'],
            'created_at'         => $r['created_at'],
        ];
    }

    jsonResponse(true, 'OK', $out);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load pickup orders: ' . $e->getMessage());
}