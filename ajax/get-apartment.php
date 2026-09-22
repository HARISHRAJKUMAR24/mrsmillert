<?php
/* =========================================================
   MRS MILL@ — AJAX: GET ONE APARTMENT
   File: ./ajax/get-apartment.php
   Accepts: ?id=1  OR  ?code=APT001
   Returns JSON: { success, message, data: {...} }
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

    /* Decode divisions */
    $divisions = [];
    if (!empty($row['divisions'])) {
        if (function_exists('decodeDivisions')) {
            $decoded = decodeDivisions($row['divisions']);
            if (is_array($decoded)) $divisions = $decoded;
        } else {
            $decoded = json_decode($row['divisions'], true);
            if (is_array($decoded)) $divisions = $decoded;
        }
    }

    /* Build clean response */
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