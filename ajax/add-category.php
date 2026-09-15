<?php
/* =========================================================
   MRS MILL@ — AJAX: ADD CATEGORY
   File: ./ajax/category-add.php
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$name   = trim($_POST['category_name'] ?? '');
$status = isset($_POST['status']) ? (int) $_POST['status'] : 1;

if ($name === '') {
    jsonResponse(false, 'Category name is required.');
}

if (!isset($_FILES['category_image']) ||
    ($_FILES['category_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    jsonResponse(false, 'Category image is required.');
}

/* duplicate check */

try {

    $check = $pdo->prepare(
        "SELECT id FROM categories WHERE category_name = ? LIMIT 1"
    );
    $check->execute([$name]);

    if ($check->fetch()) {
        jsonResponse(false, 'A category with this name already exists.');
    }

} catch (PDOException $e) {
    jsonResponse(false, 'Server error. Please try again.');
}

/* upload image */

$baseDir = dirname(__DIR__); // project root
$upload  = uploadCategoryImage($_FILES['category_image'], $baseDir);

if (!$upload['success']) {
    jsonResponse(false, $upload['message']);
}

$slug = makeSlug($name);

try {

    $stmt = $pdo->prepare(
        "INSERT INTO categories
            (category_name, category_slug, category_image, status)
         VALUES (?, ?, ?, ?)"
    );

    $stmt->execute([
        $name,
        $slug,
        $upload['path'],
        $status
    ]);

    jsonResponse(
        true,
        'Category added successfully.',
        [
            'id'        => (int) $pdo->lastInsertId(),
            'image_url' => ADMIN_URL . $upload['path']
        ]
    );

} catch (PDOException $e) {

    // rollback: delete the uploaded file
    deleteCategoryImage($upload['path'], $baseDir);

    jsonResponse(false, 'Failed to add category.');
}