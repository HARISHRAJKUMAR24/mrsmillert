<?php
/* =========================================================
   MRS MILL@ — AJAX: LOOKUP CUSTOMER FOR MANUAL ORDER
   File: ./ajax/manual-lookup-customer.php
   Supports both apartment and individual_house customers.
   ========================================================= */

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
        "SELECT id, customer_id, full_name, mobile_number,
                address_type, address_json,
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

    /* Parse address_json */
    $addr = [];
    if (!empty($c['address_json'])) {
        $decoded = json_decode($c['address_json'], true);
        if (is_array($decoded)) $addr = $decoded;
    }

    $type = $c['address_type'] ?: 'apartment';

    if ($type === 'apartment') {
        $block  = $addr['block']   ?? '';
        $door   = $addr['door_no'] ?? '';
        $floor  = $addr['floor']   ?? '';
        $label  = $addr['division_label'] ?? '';

        if ($label === '') {
            $parts = [];
            if ($block) $parts[] = "Blk " . $block;
            if ($door)  $parts[] = "Door " . $door;
            if ($floor) $parts[] = "Fl " . $floor;
            $label = $parts ? implode(" / ", $parts) : '';
        }

        jsonResponse(true, 'OK', [
            'found'           => true,
            'id'              => (int)$c['id'],
            'customer_id'     => $c['customer_id'] ?? '',
            'name'            => $c['full_name'],
            'mobile'          => $c['mobile_number'],
            'address_type'    => 'apartment',
            'apartment_id'    => (int)($addr['apartment_id'] ?? 0),
            'apartment_code'  => $addr['apartment_code'] ?? '',
            'apartment_name'  => $addr['apartment_name'] ?? '',
            'division'        => $label,
            'division_charge' => (float)($addr['charge'] ?? 0),
            'full_address'    => '',
            'map_link'        => '',
            'house_image'     => '',
            'wallet_balance'  => (float)$c['wallet_balance'],
        ]);
    } else {
        /* individual_house */
        jsonResponse(true, 'OK', [
            'found'           => true,
            'id'              => (int)$c['id'],
            'customer_id'     => $c['customer_id'] ?? '',
            'name'            => $c['full_name'],
            'mobile'          => $c['mobile_number'],
            'address_type'    => 'individual_house',
            'apartment_id'    => 0,
            'apartment_code'  => '',
            'apartment_name'  => '',
            'division'        => '',
            'division_charge' => 0,
            'full_address'    => $addr['full_address'] ?? '',
            'map_link'        => $addr['map_link'] ?? '',
            'house_image'     => $addr['house_image'] ?? '',
            'wallet_balance'  => (float)$c['wallet_balance'],
        ]);
    }

} catch (PDOException $e) {
    jsonResponse(false, 'Lookup failed: ' . $e->getMessage());
}