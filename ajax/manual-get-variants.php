<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'Invalid product.');

try {
    $stmt = $pdo->prepare(
        "SELECT id, product_code, product_name, product_image
         FROM products
         WHERE id = ? AND status = 1
         LIMIT 1"
    );
    $stmt->execute([$id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) jsonResponse(false, 'Product not found.');

    $vStmt = $pdo->prepare(
        "SELECT id, quantity, quantity_unit, quantity_name, price,
                container_enabled, container_price
         FROM product_variants
         WHERE product_code = ? AND status = 1
         ORDER BY quantity ASC"
    );
    $vStmt->execute([$p['product_code']]);
    $rows = $vStmt->fetchAll(PDO::FETCH_ASSOC);

    $variants = [];
    foreach ($rows as $v) {
        $variants[] = [
            'id'                => (int)$v['id'],
            'quantity'          => (float)$v['quantity'],
            'quantity_unit'     => $v['quantity_unit'],
            'quantity_name'     => $v['quantity_name'],
            'price'             => (float)$v['price'],
            'container_enabled' => (int)($v['container_enabled'] ?? 0),
            'container_price'   => (float)($v['container_price'] ?? 0),
        ];
    }

    jsonResponse(true, 'OK', [
        'id'           => (int)$p['id'],
        'product_code' => $p['product_code'],
        'product_name' => $p['product_name'],
        'image'        => !empty($p['product_image']) ? ADMIN_URL . $p['product_image'] : '',
        'variants'     => $variants,
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load variants.');
}