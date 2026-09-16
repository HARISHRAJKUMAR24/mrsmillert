<?php
/* =========================================================
   MRS MILL@ — AJAX: DELETE PRODUCT
   File: ./ajax/product-delete.php
   Deletes:
     - product_variants (by product_code)
     - product_apartments (by product_code)
     - products row
     - product image file + empty folder
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

    $productCode = $row['product_code'];

    $pdo->beginTransaction();

    /* 1) delete all variants */
    $delVar = $pdo->prepare(
        "DELETE FROM product_variants WHERE product_code = ?"
    );
    $delVar->execute([$productCode]);

    /* 2) delete apartment links */
    $delApt = $pdo->prepare(
        "DELETE FROM product_apartments WHERE product_code = ?"
    );
    $delApt->execute([$productCode]);

    /* 3) delete product row */
    $delProd = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $delProd->execute([$id]);

    $pdo->commit();

    /* 4) delete image file + empty folder */
    if (!empty($row['product_image']) && function_exists('deleteCategoryImage')) {
        deleteCategoryImage($row['product_image'], dirname(__DIR__));
    }

    jsonResponse(true, 'Product deleted successfully.');

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(false, 'Failed to delete product: ' . $e->getMessage());
}