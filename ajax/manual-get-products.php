<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

$tab = trim($_GET['tab'] ?? 'menu');

try {
    if ($tab === 'menu') {
        /* Active menu products only */
        $menu = getActiveMenu($pdo);
        if (!$menu) {
            jsonResponse(true, 'No active menu', []);
        }

        $stmt = $pdo->prepare(
            "SELECT DISTINCT
                    p.id, p.product_code, p.product_name, p.product_image,
                    c.category_name,
                    (SELECT MIN(v.price) FROM product_variants v
                     WHERE v.product_code = p.product_code AND v.status = 1) AS min_price
             FROM menu_products mp
             INNER JOIN products p    ON p.product_code = mp.product_code
             LEFT  JOIN categories c  ON c.id = p.category_id
             WHERE mp.menu_code = ? AND p.status = 1
             ORDER BY p.id ASC"
        );
        $stmt->execute([$menu['menu_code']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        /* All products */
        $stmt = $pdo->query(
            "SELECT p.id, p.product_code, p.product_name, p.product_image,
                    c.category_name,
                    (SELECT MIN(v.price) FROM product_variants v
                     WHERE v.product_code = p.product_code AND v.status = 1) AS min_price
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.status = 1
             ORDER BY p.id DESC"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $out = array_map(function ($r) {
        return [
            'id'            => (int)$r['id'],
            'product_code'  => $r['product_code'],
            'product_name'  => $r['product_name'],
            'product_image' => !empty($r['product_image']) ? ADMIN_URL . $r['product_image'] : '',
            'category_name' => $r['category_name'] ?? '',
            'min_price'     => (float)$r['min_price'],
        ];
    }, $rows);

    jsonResponse(true, 'OK', $out);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load products.');
}