<?php
/* =========================================================
   MRS MILL@ — AJAX: GET ONE CATEGORY
   File: ./ajax/category-get.php
   Accepts: ?id=1
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method not allowed.');
}

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(false, 'Invalid category ID.');
}

try {

    $stmt = $pdo->prepare(
        "SELECT * FROM categories WHERE id = ? LIMIT 1"
    );
    $stmt->execute([$id]);

    $row = $stmt->fetch();

    if (!$row) {
        jsonResponse(false, 'Category not found.');
    }

    $row['image_url'] = $row['category_image']
        ? ADMIN_URL . $row['category_image']
        : '';

    jsonResponse(true, 'OK', $row);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load category.');
}