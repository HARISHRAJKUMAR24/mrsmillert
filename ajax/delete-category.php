<?php
/* =========================================================
   MRS MILL@ — AJAX: DELETE CATEGORY
   File: ./ajax/category-delete.php
   Deletes row + its image file
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(false, 'Invalid category ID.');
}

try {

    $stmt = $pdo->prepare(
        "SELECT category_image FROM categories WHERE id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        jsonResponse(false, 'Category not found.');
    }

    $delete = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $delete->execute([$id]);

    // delete image from disk
    if (!empty($row['category_image'])) {
        deleteCategoryImage($row['category_image'], dirname(__DIR__));
    }

    jsonResponse(true, 'Category deleted successfully.');

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to delete category.');
}