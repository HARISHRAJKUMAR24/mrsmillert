<?php
/* =========================================================
   MRS MILL@ — AJAX: GET ONE PRODUCT
   File: ./ajax/product-get.php
   Accepts: ?id=1
   Returns: product + variants + apartment codes + status
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method not allowed.');
}

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(false, 'Invalid product ID.');
}

try {

    $stmt = $pdo->prepare(
        "SELECT p.*, c.category_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.id = ? LIMIT 1"
    );
    $stmt->execute([$id]);

    $row = $stmt->fetch();

    if (!$row) {
        jsonResponse(false, 'Product not found.');
    }

    /* Apartment codes */
    $stmt2 = $pdo->prepare(
        "SELECT apartment_code
         FROM product_apartments
         WHERE product_code = ?"
    );
    $stmt2->execute([$row['product_code']]);
    $row['apartments'] = $stmt2->fetchAll(PDO::FETCH_COLUMN);

    /* Variants */
    $stmt3 = $pdo->prepare(
        "SELECT id, quantity, quantity_unit, quantity_name, price
         FROM product_variants
         WHERE product_code = ?
         ORDER BY id ASC"
    );
    $stmt3->execute([$row['product_code']]);
    $row['variants'] = $stmt3->fetchAll();

    /* Status (cast to int) */
    $row['status'] = isset($row['status']) ? (int)$row['status'] : 1;

    /* Image URL */
    $row['image_url'] = $row['product_image']
        ? ADMIN_URL . $row['product_image']
        : '';

    jsonResponse(true, 'OK', $row);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load product.');
}