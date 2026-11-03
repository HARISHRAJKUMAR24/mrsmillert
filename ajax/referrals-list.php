<?php
/* =========================================================
   MRS MILL@ — AJAX: LIST REFERRALS
   File: ./ajax/referrals-list.php
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

try {
    $stmt = $pdo->query(
        "SELECT r.id,
                r.customer_id,
                r.customer_name,
                r.customer_mobile,
                r.referrer_name,
                r.referrer_mobile,
                r.referrer_customer_id,
                r.status,
                r.created_at,
                r.updated_at,
                c.full_name    AS ref_cust_name,
                c.mobile_number AS ref_cust_mobile,
                c.customer_id  AS ref_cust_code
         FROM customer_referrals r
         LEFT JOIN customers c ON c.id = r.referrer_customer_id
         ORDER BY r.id DESC"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = array_map(function ($r) {
        return [
            'id'                    => (int)$r['id'],
            'customer_id'           => $r['customer_id'] !== null ? (int)$r['customer_id'] : null,
            'customer_name'         => $r['customer_name'],
            'customer_mobile'       => $r['customer_mobile'],
            'referrer_name'         => $r['referrer_name'],
            'referrer_mobile'       => $r['referrer_mobile'],
            'referrer_customer_id'  => $r['referrer_customer_id'] !== null ? (int)$r['referrer_customer_id'] : null,
            'referrer_customer_name'   => $r['ref_cust_name'],
            'referrer_customer_mobile' => $r['ref_cust_mobile'],
            'referrer_customer_code'   => $r['ref_cust_code'],
            'status'                => (int)$r['status'],
            'created_at'            => $r['created_at'],
            'updated_at'            => $r['updated_at'],
        ];
    }, $rows);

    jsonResponse(true, 'OK', $out);
} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load referrals: ' . $e->getMessage());
}