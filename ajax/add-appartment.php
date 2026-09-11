<?php
/* =========================================================
   MRS MILL@ — AJAX: ADD APARTMENT
   File: ./ajax/add-appartment.php
   Saves divisions as JSON: [{division, charge}, ...]
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

$name      = trim($_POST['apartment_name'] ?? '');
$address   = trim($_POST['apartment_address'] ?? '');
$status    = isset($_POST['status']) ? (int) $_POST['status'] : 1;

// divisions sent as JSON array from JS
$divisionsRaw = $_POST['divisions'] ?? '[]';

$divisions = json_decode($divisionsRaw, true);

/* -----------------------------------------
   VALIDATION
----------------------------------------- */

if ($name === '') {
    jsonResponse(false, 'Apartment name is required.');
}

if ($address === '') {
    jsonResponse(false, 'Apartment address is required.');
}

if (!is_array($divisions) || count($divisions) === 0) {
    jsonResponse(false, 'Please add at least one division with a charge.');
}

/* Clean + validate each division */

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

    // prevent duplicate division inside the same apartment
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
   DUPLICATE APARTMENT CHECK
   (same name + same set of divisions)
----------------------------------------- */

try {

    $check = $pdo->prepare(
        "SELECT id FROM apartments WHERE apartment_name = ? LIMIT 1"
    );

    $check->execute([$name]);

    if ($check->fetch()) {
        jsonResponse(false, 'An apartment with this name already exists.');
    }
} catch (PDOException $e) {
    jsonResponse(false, 'Server error. Please try again.');
}

/* -----------------------------------------
   GENERATE CODE + INSERT
----------------------------------------- */

$code = generateApartmentCode($pdo);

try {

    $stmt = $pdo->prepare(
        "INSERT INTO apartments
            (apartment_code, apartment_name,
             apartment_address, divisions, status)
         VALUES (?, ?, ?, ?, ?)"
    );

    $stmt->execute([
        $code,
        $name,
        $address,
        $divisionsJson,
        $status
    ]);

    jsonResponse(
        true,
        'Apartment added successfully.',
        [
            'code'      => $code,
            'divisions' => $cleanDivisions
        ]
    );
} catch (PDOException $e) {

    jsonResponse(false, 'Failed to add apartment.');
}
