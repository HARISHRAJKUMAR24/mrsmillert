<?php
/* =========================================================
   MRS MILL@ — AJAX: LIST PRODUCTS
   File: ./ajax/product-list.php
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
            "SELECT p.*, c.category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.product_name LIKE ?
                OR p.product_code LIKE ?
                OR c.category_name LIKE ?
             ORDER BY p.id DESC"
        );

        $like = '%' . $search . '%';
        $stmt->execute([$like, $like, $like]);

    } else {

        $stmt = $pdo->query(
            "SELECT p.*, c.category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             ORDER BY p.id DESC"
        );
    }

    $rows = $stmt->fetchAll();

    /* attach image URL for each product */

    foreach ($rows as &$r) {
        $r['image_url'] = $r['product_image']
            ? ADMIN_URL . $r['product_image']
            : '';
    }
    unset($r);

    jsonResponse(true, 'OK', $rows);

} catch (PDOException $e) {

    jsonResponse(false, 'Failed to load products.');
}