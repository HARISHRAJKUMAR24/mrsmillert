<?php
/* =========================================================
   MRS MILL@ — AJAX: DELETE APARTMENT
   File: ./ajax/delete.php
   Returns JSON: { success, message }
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(false, 'Invalid apartment ID.');
}

try {

    $stmt = $pdo->prepare(
        "DELETE FROM apartments WHERE id = ?"
    );

    $stmt->execute([$id]);

    if ($stmt->rowCount() < 1) {
        jsonResponse(false, 'Apartment not found.');
    }

    jsonResponse(true, 'Apartment deleted successfully.');

} catch (PDOException $e) {

    jsonResponse(false, 'Failed to delete apartment.');
}