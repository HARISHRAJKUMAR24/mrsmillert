<?php
/* =========================================================
   MRS MILL@ — AJAX: LIST APARTMENTS
   File: ./ajax/apartment.php
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method not allowed.');
}

$search = trim($_GET['search'] ?? '');

try {

    if ($search !== '') {
        $stmt = $pdo->prepare(
            "SELECT * FROM apartments
             WHERE apartment_name LIKE ?
                OR apartment_code LIKE ?
                OR apartment_address LIKE ?
                OR divisions LIKE ?
             ORDER BY id DESC"
        );
        $like = '%' . $search . '%';
        $stmt->execute([$like, $like, $like, $like]);
    } else {
        $stmt = $pdo->query("SELECT * FROM apartments ORDER BY id DESC");
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['divisions'] = decodeDivisions($row['divisions'] ?? '');
        $row['type'] = 'apartment';
    }
    unset($row);

    jsonResponse(true, 'OK', $rows);

} catch (Throwable $e) {
    jsonResponse(false, 'Failed to load apartments: ' . $e->getMessage());
}