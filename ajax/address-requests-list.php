<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

try {
    $stmt = $pdo->query(
        "SELECT id, customer_name, customer_mobile,
                requested_apartment, requested_division, delivery_charge,
                request_type, map_link, house_image,
                status, created_customer_id, created_apartment_id,
                created_branch_id, approved_at, created_at, updated_at
         FROM customer_address_requests
         ORDER BY id DESC"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = array_map(function ($r) {

        /* ---- Build full image URL using MAIN_URL (customer site) ---- */
        $raw = (string)($r['house_image'] ?? '');
        $full = '';
        if ($raw !== '') {
            if (preg_match('#^https?://#i', $raw)) {
                $full = $raw; // already absolute
            } else {
                $full = rtrim(MAIN_URL, '/') . '/' . ltrim($raw, '/');
            }
        }

        return [
            'id'                   => (int)$r['id'],
            'customer_name'        => $r['customer_name'],
            'customer_mobile'      => $r['customer_mobile'],
            'requested_apartment'  => $r['requested_apartment'],
            'requested_division'   => $r['requested_division'],
            'delivery_charge'      => (float)($r['delivery_charge'] ?? 0),
            'request_type'         => $r['request_type'],
            'map_link'             => $r['map_link'],
            'house_image'          => $full,   // ✅ full URL for <img>
            'house_image_raw'      => $raw,    // raw path for DB updates
            'status'               => (int)$r['status'],
            'created_customer_id'  => $r['created_customer_id'] !== null ? (int)$r['created_customer_id'] : null,
            'created_apartment_id' => $r['created_apartment_id'] !== null ? (int)$r['created_apartment_id'] : null,
            'created_branch_id'    => $r['created_branch_id'] !== null ? (int)$r['created_branch_id'] : null,
            'approved_at'          => $r['approved_at'],
            'created_at'           => $r['created_at'],
            'updated_at'           => $r['updated_at'],
        ];
    }, $rows);

    /* Branch list for the approve modal */
    $bStmt = $pdo->query("SELECT id, branch_name FROM settings_branches ORDER BY branch_name ASC");
    $branches = $bStmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse(true, 'OK', [
        'requests' => $out,
        'branches' => array_map(function ($b) {
            return ['id' => (int)$b['id'], 'branch_name' => $b['branch_name']];
        }, $branches)
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load requests: ' . $e->getMessage());
}