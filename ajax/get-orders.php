<?php
/* =========================================================
   MRS MILL@ — AJAX: GET ORDERS (admin panel)
   File: ./ajax/get-orders.php
   - Joins customers for name + mobile
   - Reads apartment_code / apartment_name / division /
     full_address from orders.address_json
   - Sends address_type so JS can show "Individual House"
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

/* ---------- Filter ---------- */
if ($filter === 'paid')         $where[] = "o.payment_status = 'paid'";
elseif ($filter === 'unpaid')   $where[] = "o.payment_status = 'unpaid'";
elseif ($filter === 'delivery') $where[] = "o.delivery_mode = 'delivery'";
elseif ($filter === 'pickup')   $where[] = "o.delivery_mode = 'pickup'";
elseif ($filter === 'today')    $where[] = "DATE(o.created_at) = CURDATE()";

/* ---------- Date filter ---------- */
if ($date !== '' && $date !== 'all') {
    if ($date === 'today')         $where[] = "DATE(o.created_at) = CURDATE()";
    elseif ($date === 'yesterday') $where[] = "DATE(o.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
    elseif ($date === 'week')      $where[] = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)";
    elseif ($date === 'month')     $where[] = "YEAR(o.created_at) = YEAR(CURDATE())
                                                AND MONTH(o.created_at) = MONTH(CURDATE())";
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
    $where[] = "(o.order_code LIKE ? OR c.full_name LIKE ? OR c.mobile_number LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

try {

    $sql = "SELECT
                o.id,
                o.order_code,
                o.customer_id,
                c.full_name       AS customer_name,
                c.mobile_number   AS customer_mobile,

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
                o.created_at,
                b.full_name       AS boy_name,
                b.delivery_code   AS boy_code
            FROM orders o
            LEFT JOIN customers c     ON c.customer_id = o.customer_id
            LEFT JOIN delivery_boys b ON b.id          = o.delivery_boy_id
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

        $issued = $returned = 0;
        $containerTotalAmt = 0.0;

        foreach ($products as $p) {
            if ((int)($p['container_enabled'] ?? 0) !== 1) continue;

            $qty   = (int)($p['qty'] ?? 0);
            $price = (float)($p['container_price'] ?? 0);
            $line  = (float)($p['container_line_total'] ?? ($price * $qty));

            $issued   += $qty;
            $returned += (int)($p['container_returned'] ?? 0);
            $containerTotalAmt += $line;
        }

        $out[] = [
            'id'              => (int)$r['id'],
            'order_code'      => $r['order_code'],
            'customer_id'     => $r['customer_id'],
            'customer_name'   => $r['customer_name']   ?: '—',
            'customer_mobile' => $r['customer_mobile'] ?: '—',

            'delivery_mode'   => $r['delivery_mode'] ?: 'delivery',

            'address_type'    => $r['address_type'] ?: 'apartment',
            'full_address'    => $r['full_address'],

            'apartment_code'  => $r['apartment_code'],
            'apartment_name'  => $r['apartment_name'],
            'division'        => $r['division'],
            'division_charge' => (float)$r['division_charge'],

            'pickup_branch_id'   => (int)$r['pickup_branch_id'],
            'pickup_branch_name' => $r['pickup_branch_name'] ?: '',

            'subtotal'        => (float)$r['subtotal'],
            'total_amount'    => (float)$r['total_amount'],
            'products'        => $products,

            'status'          => $r['status'],
            'delivery_status' => $r['delivery_status'],
            'payment_status'  => $r['payment_status'],
            'paid_at'         => $r['paid_at'],
            'created_at'      => $r['created_at'],

            'boy_name'        => $r['boy_name'] ?: '—',
            'boy_code'        => $r['boy_code'] ?: '',

            'container_total_amount' => $containerTotalAmt,
            'containers_balance'     => max(0, $issued - $returned),
            'container_issued'       => $issued,
            'container_returned'     => $returned,
        ];
    }

    jsonResponse(true, 'OK', $out);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load orders: ' . $e->getMessage());
}