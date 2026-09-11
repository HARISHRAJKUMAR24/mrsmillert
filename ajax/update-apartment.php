<?php
/* =========================================================
   MRS MILL@ — AJAX: UPDATE APARTMENT
   File: ./ajax/update-apartment.php
   Updates name, address, status, divisions (JSON)
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

/* -----------------------------------------
   ONLY POST
----------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

/* -----------------------------------------
   READ INPUT
----------------------------------------- */

$id        = (int) ($_POST['id'] ?? 0);
$name      = trim($_POST['apartment_name'] ?? '');
$address   = trim($_POST['apartment_address'] ?? '');
$status    = isset($_POST['status']) ? (int) $_POST['status'] : 1;
$divisionsRaw = $_POST['divisions'] ?? '[]';

$divisions = json_decode($divisionsRaw, true);

/* -----------------------------------------
   VALIDATION
----------------------------------------- */

if ($id <= 0) {
    jsonResponse(false, 'Invalid apartment ID.');
}

if ($name === '') {
    jsonResponse(false, 'Apartment name is required.');
}

if ($address === '') {
    jsonResponse(false, 'Apartment address is required.');
}

if (!is_array($divisions) || count($divisions) === 0) {
    jsonResponse(false, 'Please add at least one division with a charge.');
}

$cleanDivisions = [];
$seen           = [];

foreach ($divisions as $d) {

    $division = isset($d['division']) ? trim((string) $d['division']) : '';
    $charge   = isset($d['charge'])   ? trim((string) $d['charge'])   : '';

    if ($division === '') {
        jsonResponse(false, 'Each division must have a name.');
    }

    if ($charge === '' || !is_numeric($charge) || (float) $charge < 0) {
        jsonResponse(false, 'Each division must have a valid delivery charge.');
    }

    $key = strtolower($division);
    if (isset($seen[$key])) {
        jsonResponse(false, 'Duplicate division "' . $division . '" found.');
    }
    $seen[$key] = true;

    $cleanDivisions[] = [
        'division' => $division,
        'charge'   => (float) $charge
    ];
}

$divisionsJson = json_encode($cleanDivisions, JSON_UNESCAPED_UNICODE);

/* -----------------------------------------
   CHECK EXISTS
----------------------------------------- */

try {

    $check = $pdo->prepare(
        "SELECT id, apartment_code FROM apartments WHERE id = ? LIMIT 1"
    );
    $check->execute([$id]);

    $existing = $check->fetch();

    if (!$existing) {
        jsonResponse(false, 'Apartment not found.');
    }

    // duplicate name check (excluding current)
    $dup = $pdo->prepare(
        "SELECT id FROM apartments
         WHERE apartment_name = ? AND id <> ?
         LIMIT 1"
    );
    $dup->execute([$name, $id]);

    if ($dup->fetch()) {
        jsonResponse(false, 'Another apartment already uses this name.');
    }

} catch (PDOException $e) {

    jsonResponse(false, 'Server error. Please try again.');
}

/* -----------------------------------------
   UPDATE
----------------------------------------- */

try {

    $stmt = $pdo->prepare(
        "UPDATE apartments
         SET apartment_name    = ?,
             apartment_address = ?,
             divisions         = ?,
             status            = ?
         WHERE id = ?"
    );

    $stmt->execute([
        $name,
        $address,
        $divisionsJson,
        $status,
        $id
    ]);

    jsonResponse(
        true,
        'Apartment updated successfully.',
        [
            'id'        => $id,
            'code'      => $existing['apartment_code'],
            'divisions' => $cleanDivisions
        ]
    );

} catch (PDOException $e) {

    jsonResponse(false, 'Failed to update apartment.');
}