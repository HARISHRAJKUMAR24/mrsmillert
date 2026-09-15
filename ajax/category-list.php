<?php
/* =========================================================
   MRS MILL@ — AJAX: LIST CATEGORIES
   File: ./ajax/category-list.php
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
            "SELECT * FROM categories
         WHERE category_name LIKE ?
         ORDER BY id DESC"
        );

        $like = '%' . $search . '%';
        $stmt->execute([$like]);
    } else {

        $stmt = $pdo->query(
            "SELECT * FROM categories ORDER BY id DESC"
        );
    }

    $rows = $stmt->fetchAll();

    // add image URL
    foreach ($rows as &$row) {
        $row['image_url'] = $row['category_image']
            ? ADMIN_URL . $row['category_image']
            : '';
    }

    jsonResponse(true, 'OK', $rows);
} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load categories.');
}
