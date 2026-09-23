<?php
/* =========================================================
   MRS MILL@ — AJAX: GET ONE PRODUCT
   File: ./ajax/product-get.php
   Accepts: ?id=1
   Returns: product + variants (with container) + apartment codes + status
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

    /* Variants (with container fields) */
    $stmt3 = $pdo->prepare(
        "SELECT id, quantity, quantity_unit, quantity_name, price,
                container_enabled, container_price
         FROM product_variants
         WHERE product_code = ?
         ORDER BY id ASC"
    );
    $stmt3->execute([$row['product_code']]);
    $variants = $stmt3->fetchAll();

    $cleanVariants = [];
    foreach ($variants as $v) {
        $cleanVariants[] = [
            'id'                => (int)$v['id'],
            'quantity'          => (float)$v['quantity'],
            'quantity_unit'     => $v['quantity_unit'],
            'quantity_name'     => $v['quantity_name'],
            'price'             => (float)$v['price'],
            'container_enabled' => (int)($v['container_enabled'] ?? 0),
            'container_price'   => (float)($v['container_price'] ?? 0)
        ];
    }
    $row['variants'] = $cleanVariants;

    /* Status (cast to int) */
    $row['status'] = isset($row['status']) ? (int)$row['status'] : 1;

    /* Image URL */
    $row['image_url'] = !empty($row['product_image'])
        ? ADMIN_URL . $row['product_image']
        : '';

    jsonResponse(true, 'OK', $row);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load product.');
}