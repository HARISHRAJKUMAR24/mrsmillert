<?php
/* =========================================================
   MRS MILL@ — AJAX: DELETE PRODUCT
   File: ./ajax/product-delete.php
   Deletes row + image file + linked apartment links
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(false, 'Invalid product ID.');
}

try {

    $stmt = $pdo->prepare(
        "SELECT * FROM products WHERE id = ? LIMIT 1"
    );
    $stmt->execute([$id]);

    $row = $stmt->fetch();

    if (!$row) {
        jsonResponse(false, 'Product not found.');
    }

    $pdo->beginTransaction();

    /* delete apartment links */
    $del = $pdo->prepare(
        "DELETE FROM product_apartments WHERE product_code = ?"
    );
    $del->execute([$row['product_code']]);

    /* delete product row */
    $del2 = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $del2->execute([$id]);

    $pdo->commit();

    /* delete image file + empty folder */
    if (!empty($row['product_image'])) {
        deleteCategoryImage($row['product_image'], dirname(__DIR__));
    }

    jsonResponse(true, 'Product deleted successfully.');

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(false, 'Failed to delete product.');
}