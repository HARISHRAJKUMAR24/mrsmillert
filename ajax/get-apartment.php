<?php
/* =========================================================
   MRS MILL@ — AJAX: GET ONE APARTMENT
   File: ./ajax/get-apartment.php
   Accepts: ?id=1  OR  ?code=APT001
   Returns JSON: { success, message, data: {...} }

   NOTE: Divisions are normalized to the new variant shape:
         { block, door_no, floor, charge }
   Legacy rows { division, charge } are auto-migrated:
         division -> door_no
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method not allowed.');
}

$id   = isset($_GET['id'])   ? (int) $_GET['id'] : 0;
$code = isset($_GET['code']) ? trim($_GET['code']) : '';

if ($id <= 0 && $code === '') {
    jsonResponse(false, 'Apartment ID or code is required.');
}

try {

    if ($id > 0) {
        $stmt = $pdo->prepare(
            "SELECT id, apartment_code, apartment_name, apartment_address,
                    branch_id, divisions, status
             FROM apartments
             WHERE id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, apartment_code, apartment_name, apartment_address,
                    branch_id, divisions, status
             FROM apartments
             WHERE apartment_code = ?
             LIMIT 1"
        );
        $stmt->execute([$code]);
    }

    $row = $stmt->fetch();

    if (!$row) {
        jsonResponse(false, 'Apartment not found.');
    }

    /* -----------------------------------------
       DECODE divisions JSON (raw)
    ----------------------------------------- */

    $rawDivisions = [];
    if (!empty($row['divisions'])) {
        if (function_exists('decodeDivisions')) {
            $decoded = decodeDivisions($row['divisions']);
            if (is_array($decoded)) $rawDivisions = $decoded;
        } else {
            $decoded = json_decode($row['divisions'], true);
            if (is_array($decoded)) $rawDivisions = $decoded;
        }
    }

    /* -----------------------------------------
       NORMALIZE each variant to:
         { block, door_no, floor, charge }
       Old shape: { division, charge } -> door_no
    ----------------------------------------- */

    $divisions = [];

    foreach ($rawDivisions as $d) {

        if (!is_array($d)) continue;

        $block  = isset($d['block'])   ? trim((string) $d['block'])   : '';
        $doorNo = isset($d['door_no']) ? trim((string) $d['door_no']) : '';
        $floor  = isset($d['floor'])   ? trim((string) $d['floor'])   : '';
        $charge = isset($d['charge'])  ? (float) $d['charge']         : 0.0;

        /* Legacy: if no block/door_no/floor but has "division",
           treat division as door_no */
        $hasNewShape = (
            $block !== '' || $doorNo !== '' || $floor !== ''
        );

        if (!$hasNewShape && isset($d['division'])) {
            $doorNo = trim((string) $d['division']);
        }

        $divisions[] = [
            'block'   => $block,
            'door_no' => $doorNo,
            'floor'   => $floor,
            'charge'  => $charge,
        ];
    }

    /* -----------------------------------------
       BUILD CLEAN RESPONSE
    ----------------------------------------- */

    $payload = [
        'id'                => (int) $row['id'],
        'apartment_code'    => (string) $row['apartment_code'],
        'apartment_name'    => (string) $row['apartment_name'],
        'apartment_address' => (string) $row['apartment_address'],
        'branch_id'         => ($row['branch_id'] !== null && $row['branch_id'] !== '')
                                ? (int) $row['branch_id']
                                : null,
        'divisions'         => $divisions,
        'status'            => (int) $row['status'],
    ];

    jsonResponse(true, 'OK', $payload);

} catch (PDOException $e) {

    jsonResponse(false, 'Failed to load apartment: ' . $e->getMessage());
}