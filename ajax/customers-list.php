<?php
/* =========================================================
   MRS MILL@ — AJAX: LIST CUSTOMERS
   File: ./ajax/customers-list.php
   Reads address_type + address_json, flattens for the UI.
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

try {
    $stmt = $pdo->query(
        "SELECT id, customer_id, full_name, mobile_number,
                address_type, address_json,
                wallet_balance, profile_completed, status,
                last_login_at, created_at, updated_at
         FROM customers
         ORDER BY id DESC"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];

    foreach ($rows as $r) {
        $addr = [];
        if (!empty($r['address_json'])) {
            $decoded = json_decode($r['address_json'], true);
            if (is_array($decoded)) $addr = $decoded;
        }

        $type = $r['address_type'] ?: 'apartment';

        if ($type === 'apartment') {
            $aptName = $addr['apartment_name'] ?? '';
            $aptCode = $addr['apartment_code'] ?? '';
            $block   = $addr['block']   ?? '';
            $door    = $addr['door_no'] ?? '';
            $floor   = $addr['floor']   ?? '';
            $label   = $addr['division_label'] ?? '';
            $charge  = (float)($addr['charge'] ?? 0);

            /* If division_label missing, rebuild from parts */
            if ($label === '') {
                $parts = [];
                if ($block) $parts[] = "Blk " . $block;
                if ($door)  $parts[] = "Door " . $door;
                if ($floor) $parts[] = "Fl " . $floor;
                $label = $parts ? implode(" / ", $parts) : '';
            }

            $out[] = [
                'id'                => (int)$r['id'],
                'customer_id'       => $r['customer_id'] ?? '',
                'full_name'         => $r['full_name'],
                'mobile_number'     => $r['mobile_number'],
                'address_type'      => 'apartment',
                'apartment_id'      => $addr['apartment_id'] ?? null,
                'apartment_code'    => $aptCode,
                'apartment_name'    => $aptName,
                'division'          => $label,
                'division_charge'   => $charge,
                'full_address'      => '',
                'map_link'          => '',
                'house_image'       => '',
                'wallet_balance'    => (float)$r['wallet_balance'],
                'profile_completed' => (int)$r['profile_completed'],
                'status'            => (int)$r['status'],
                'last_login_at'     => $r['last_login_at'],
                'created_at'        => $r['created_at'],
                'updated_at'        => $r['updated_at'],
            ];
        } else {
            /* individual_house */
            $out[] = [
                'id'                => (int)$r['id'],
                'customer_id'       => $r['customer_id'] ?? '',
                'full_name'         => $r['full_name'],
                'mobile_number'     => $r['mobile_number'],
                'address_type'      => 'individual_house',
                'apartment_id'      => null,
                'apartment_code'    => '',
                'apartment_name'    => '',
                'division'          => '',
                'division_charge'   => 0,
                'full_address'      => $addr['full_address'] ?? '',
                'map_link'          => $addr['map_link'] ?? '',
                'house_image'       => $addr['house_image'] ?? '',
                'wallet_balance'    => (float)$r['wallet_balance'],
                'profile_completed' => (int)$r['profile_completed'],
                'status'            => (int)$r['status'],
                'last_login_at'     => $r['last_login_at'],
                'created_at'        => $r['created_at'],
                'updated_at'        => $r['updated_at'],
            ];
        }
    }

    jsonResponse(true, 'OK', $out);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load customers: ' . $e->getMessage());
}