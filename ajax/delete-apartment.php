<?php
/* =========================================================
   MRS MILL@ — AJAX: DELETE APARTMENT
   File: ./ajax/delete.php
   Also removes any product_apartments links for this apartment.
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

    /* 1) Fetch apartment_code so we can clean up links */
    $stmt = $pdo->prepare("SELECT apartment_code FROM apartments WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        jsonResponse(false, 'Apartment not found.');
    }

    $apartmentCode = $row['apartment_code'];

    $pdo->beginTransaction();

    /* 2) Remove product links for this apartment */
    $delLinks = $pdo->prepare(
        "DELETE FROM product_apartments WHERE apartment_code = ?"
    );
    $delLinks->execute([$apartmentCode]);

    /* 3) Delete the apartment itself */
    $delApt = $pdo->prepare("DELETE FROM apartments WHERE id = ?");
    $delApt->execute([$id]);

    if ($delApt->rowCount() < 1) {
        throw new PDOException('Apartment not found on delete.');
    }

    $pdo->commit();

    jsonResponse(true, 'Apartment deleted successfully.');

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(false, 'Failed to delete apartment: ' . $e->getMessage());
}