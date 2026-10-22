<?php
/* =========================================================
   MRS MILL@ — AJAX: GET ONE INDIVIDUAL HOUSE
   File: ./ajax/get-individual-house.php
   Accepts: ?id=1  OR  ?code=IH0001
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
    jsonResponse(false, 'House ID or code is required.');
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare(
            "SELECT id, house_code, customer_name, mobile_number,
                    full_address, map_link, house_image, status, created_at
             FROM individual_houses
             WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, house_code, customer_name, mobile_number,
                    full_address, map_link, house_image, status, created_at
             FROM individual_houses
             WHERE house_code = ? LIMIT 1"
        );
        $stmt->execute([$code]);
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        jsonResponse(false, 'House not found.');
    }

    jsonResponse(true, 'OK', [
        'id'            => (int)$row['id'],
        'house_code'    => (string)$row['house_code'],
        'customer_name' => (string)$row['customer_name'],
        'mobile_number' => (string)$row['mobile_number'],
        'full_address'  => (string)$row['full_address'],
        'map_link'      => (string)($row['map_link'] ?? ''),
        'house_image'   => (string)($row['house_image'] ?? ''),
        'status'        => (int)$row['status'],
        'created_at'    => $row['created_at'],
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load house: ' . $e->getMessage());
}