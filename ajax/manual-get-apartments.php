<?php
/* =========================================================
   MRS MILL@ — AJAX: GET APARTMENTS + DIVISIONS
   File: ./ajax/manual-get-apartments.php
   Returns variants normalized for the searchable dropdowns
   (Add Customer / Edit Customer).
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

try {
    $stmt = $pdo->query(
        "SELECT id, apartment_code, apartment_name, apartment_address, divisions
         FROM apartments
         WHERE status = 1
         ORDER BY apartment_name ASC"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];

    foreach ($rows as $r) {

        $divs = json_decode($r['divisions'] ?? '[]', true);
        if (!is_array($divs)) $divs = [];

        $clean = [];

        foreach ($divs as $d) {

            if (!is_array($d)) continue;

            /* ---------- Normalize ---------- */
            $block  = isset($d['block'])   ? (string)$d['block']   : '';
            $doorNo = isset($d['door_no']) ? (string)$d['door_no'] : '';
            $floor  = isset($d['floor'])   ? (string)$d['floor']   : '';
            $charge = (float)($d['charge'] ?? 0);

            /* Legacy: {division, charge} only */
            if ($block === '' && $doorNo === '' && $floor === '') {
                if (isset($d['division'])) {
                    $doorNo = (string)$d['division'];
                } else {
                    continue;
                }
            }

            /* ---------- Build human label ---------- */
            $labelParts = [];
            if ($block !== '')  $labelParts[] = "Blk " . $block;
            if ($doorNo !== '') $labelParts[] = "Door " . $doorNo;
            if ($floor !== '')  $labelParts[] = "Fl " . $floor;

            $label = $labelParts ? implode(" · ", $labelParts) : "—";

            /* ---------- Build value (used for matching on save) ---------- */
            $valueParts = [];
            if ($block !== '')  $valueParts[] = "Blk " . $block;
            if ($doorNo !== '') $valueParts[] = "Door " . $doorNo;
            if ($floor !== '')  $valueParts[] = "Fl " . $floor;

            $value = $valueParts ? implode(" / ", $valueParts) : $doorNo;

            $clean[] = [
                'block'          => $block,
                'door_no'        => $doorNo,
                'floor'          => $floor,
                'charge'         => $charge,
                'division_label' => $label,     // "Blk A · Door 101 · Fl 1"
                'division_value' => $value,     // "Blk A / Door 101 / Fl 1"
                'division'       => $value,     // legacy key for old JS fallbacks
            ];
        }

        $out[] = [
            'id'                => (int)$r['id'],
            'apartment_code'    => $r['apartment_code'],
            'apartment_name'    => $r['apartment_name'],
            'apartment_address' => $r['apartment_address'],
            'divisions'         => $clean,
        ];
    }

    jsonResponse(true, 'OK', $out);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load apartments.');
}