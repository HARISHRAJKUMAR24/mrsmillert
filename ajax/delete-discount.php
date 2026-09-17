<?php
/* =========================================================
   MRS MILL@ — AJAX: DELETE DISCOUNT
   File: ./ajax/delete-discount.php
   Removes discount_times + discounts row
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'Invalid discount ID.');

try {

    $stmt = $pdo->prepare("SELECT discount_code FROM discounts WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) jsonResponse(false, 'Discount not found.');

    $code = $row['discount_code'];

    $pdo->beginTransaction();

    $del1 = $pdo->prepare("DELETE FROM discount_times WHERE discount_code = ?");
    $del1->execute([$code]);

    $del2 = $pdo->prepare("DELETE FROM discounts WHERE id = ?");
    $del2->execute([$id]);

    $pdo->commit();

    jsonResponse(true, 'Discount deleted successfully.');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(false, 'Failed to delete discount: ' . $e->getMessage());
}