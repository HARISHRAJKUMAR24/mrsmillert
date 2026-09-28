<?php
/* =========================================================
   MRS MILL@ — AJAX: GET ORDERS (admin panel)
   File: ./ajax/get-orders.php
   Returns ALL orders by default (delivery + pickup).
   Only filters by mode when $filter = 'delivery' or 'pickup'.
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

$where  = [];
$params = [];

/* ---------- Payment / mode filter (ONLY when user selects it) ---------- */
if ($filter === 'paid') {
    $where[] = "o.payment_status = 'paid'";
} elseif ($filter === 'unpaid') {
    $where[] = "o.payment_status = 'unpaid'";
} elseif ($filter === 'delivery') {
    $where[] = "o.delivery_mode = 'delivery'";
} elseif ($filter === 'pickup') {
    $where[] = "o.delivery_mode = 'pickup'";
} elseif ($filter === 'today') {
    $where[] = "DATE(o.created_at) = CURDATE()";
} elseif ($filter === 'yesterday') {
    $where[] = "DATE(o.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
} elseif ($filter === 'week') {
    $where[] = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)";
} elseif ($filter === 'month') {
    $where[] = "YEAR(o.created_at) = YEAR(CURDATE())
                AND MONTH(o.created_at) = MONTH(CURDATE())";
}
/* NOTE: 'all' adds NOTHING → returns every order */

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

/* ---------- Search ---------- */
if ($q !== '') {
    $where[] = "(o.order_code LIKE ? OR o.customer_name LIKE ? OR o.customer_mobile LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

try {

    $sql = "SELECT o.id, o.order_code, o.customer_name, o.customer_mobile,
                   o.apartment_code, o.apartment_name, o.division,
                   o.division_charge, o.subtotal, o.total_amount,
                   o.delivery_mode,
                   o.pickup_branch_id, o.pickup_branch_name,
                   o.products_json, o.status, o.delivery_status,
                   o.payment_status, o.paid_at, o.created_at,
                   b.full_name AS boy_name, b.delivery_code AS boy_code
            FROM orders o
            LEFT JOIN delivery_boys b ON b.id = o.delivery_boy_id
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

        $containerIssued    = 0;
        $containerReturned  = 0;
        $containerTotalAmt  = 0.0;

        foreach ($products as $p) {
            $enabled = (int)($p['container_enabled'] ?? 0) === 1;
            if (!$enabled) continue;

            $qty            = (int)($p['qty'] ?? 0);
            $containerPrice = (float)($p['container_price'] ?? 0);
            $lineTotal      = (float)($p['container_line_total'] ?? ($containerPrice * $qty));

            $containerIssued   += $qty;
            $containerTotalAmt += $lineTotal;

            $returned = (int)($p['container_returned'] ?? 0);
            $containerReturned += $returned;
        }

        $containerBalance = max(0, $containerIssued - $containerReturned);

        $out[] = [
            'id'               => (int)$r['id'],
            'order_code'       => $r['order_code'],
            'customer_name'    => $r['customer_name'],
            'customer_mobile'  => $r['customer_mobile'],

            'delivery_mode'    => $r['delivery_mode'] ?: 'delivery',

            'apartment_code'   => $r['apartment_code'],
            'apartment_name'   => $r['apartment_name'],
            'division'         => $r['division'],
            'division_charge'  => (float)$r['division_charge'],

            'pickup_branch_id'   => (int)$r['pickup_branch_id'],
            'pickup_branch_name' => $r['pickup_branch_name'] ?: '',

            'subtotal'         => (float)$r['subtotal'],
            'total_amount'     => (float)$r['total_amount'],
            'products'         => $products,

            'status'           => $r['status'],
            'delivery_status'  => $r['delivery_status'],
            'payment_status'   => $r['payment_status'],
            'paid_at'          => $r['paid_at'],
            'created_at'       => $r['created_at'],

            'boy_name'         => $r['boy_name'] ?: '—',
            'boy_code'         => $r['boy_code'] ?: '',

            'container_total_amount' => $containerTotalAmt,
            'containers_balance'     => $containerBalance,
            'container_issued'       => $containerIssued,
            'container_returned'     => $containerReturned,
        ];
    }

    jsonResponse(true, 'OK', $out);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load orders: ' . $e->getMessage());
}