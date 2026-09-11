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
            "SELECT * FROM apartments WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);

    } else {

        $stmt = $pdo->prepare(
            "SELECT * FROM apartments WHERE apartment_code = ? LIMIT 1"
        );
        $stmt->execute([$code]);
    }

    $row = $stmt->fetch();

    if (!$row) {
        jsonResponse(false, 'Apartment not found.');
    }

    $row['divisions'] = decodeDivisions($row['divisions'] ?? '');

    jsonResponse(true, 'OK', $row);

} catch (PDOException $e) {

    jsonResponse(false, 'Failed to load apartment.');
}