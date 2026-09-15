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
                OR p.quantity_name LIKE ?
                OR c.category_name LIKE ?
             ORDER BY p.id DESC"
        );

        $like = '%' . $search . '%';
        $stmt->execute([$like, $like, $like, $like]);

    } else {

        $stmt = $pdo->query(
            "SELECT p.*, c.category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             ORDER BY p.id DESC"
        );
    }

    $rows = $stmt->fetchAll();

    /* attach apartment codes for each product */

    if (count($rows) > 0) {

        $codes = array_column($rows, 'product_code');

        $placeholders = implode(',', array_fill(0, count($codes), '?'));

        $stmt2 = $pdo->prepare(
            "SELECT product_code, apartment_code
             FROM product_apartments
             WHERE product_code IN ($placeholders)"
        );

        $stmt2->execute($codes);

        $map = [];

        while ($row = $stmt2->fetch()) {
            $map[$row['product_code']][] = $row['apartment_code'];
        }

        foreach ($rows as &$r) {
            $r['apartments'] = $map[$r['product_code']] ?? [];
            $r['image_url']  = $r['product_image']
                ? ADMIN_URL . $r['product_image']
                : '';
        }
    }

    jsonResponse(true, 'OK', $rows);

} catch (PDOException $e) {

    jsonResponse(false, 'Failed to load products.');
}