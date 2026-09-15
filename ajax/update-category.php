<?php
/* =========================================================
   MRS MILL@ — AJAX: UPDATE CATEGORY
   File: ./ajax/update-category.php

   Rules:
   - If remove_image=1 and no new file → delete old, set NULL
   - If new file uploaded          → delete old, save new
   - Else                          → keep existing image
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$id          = (int) ($_POST['id'] ?? 0);
$name        = trim($_POST['category_name'] ?? '');
$status      = isset($_POST['status']) ? (int) $_POST['status'] : 1;
$removeImage = (int) ($_POST['remove_image'] ?? 0);

if ($id <= 0)     jsonResponse(false, 'Invalid category ID.');
if ($name === '') jsonResponse(false, 'Category name is required.');

/* load existing */

try {

    $stmt = $pdo->prepare(
        "SELECT * FROM categories WHERE id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        jsonResponse(false, 'Category not found.');
    }

    $dup = $pdo->prepare(
        "SELECT id FROM categories
         WHERE category_name = ? AND id <> ?
         LIMIT 1"
    );
    $dup->execute([$name, $id]);

    if ($dup->fetch()) {
        jsonResponse(false, 'Another category already uses this name.');
    }

} catch (PDOException $e) {
    jsonResponse(false, 'Server error. Please try again.');
}

$baseDir      = dirname(__DIR__);
$currentPath  = $existing['category_image'] ?: '';
$newImagePath = $currentPath;
$uploadedNew  = false;

/* did a new file come in? */

$hasNewFile = isset($_FILES['category_image']) &&
    ($_FILES['category_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

if ($hasNewFile) {

    // 1) upload new
    $upload = uploadCategoryImage($_FILES['category_image'], $baseDir);

    if (!$upload['success']) {
        jsonResponse(false, $upload['message']);
    }

    // 2) delete old file
    if ($currentPath !== '') {
        deleteCategoryImage($currentPath, $baseDir);
    }

    $newImagePath = $upload['path'];
    $uploadedNew  = true;

} elseif ($removeImage === 1 && $currentPath !== '') {

    // user explicitly asked to remove the image
    deleteCategoryImage($currentPath, $baseDir);
    $newImagePath = null;
}

$slug = makeSlug($name);

try {

    $stmt = $pdo->prepare(
        "UPDATE categories
         SET category_name   = ?,
             category_slug   = ?,
             category_image  = ?,
             status          = ?
         WHERE id = ?"
    );

    $stmt->execute([
        $name,
        $slug,
        $newImagePath,
        $status,
        $id
    ]);

    jsonResponse(
        true,
        'Category updated successfully.',
        [
            'id'        => $id,
            'image_url' => $newImagePath ? ADMIN_URL . $newImagePath : ''
        ]
    );

} catch (PDOException $e) {

    // rollback newly-uploaded file if DB fails
    if ($uploadedNew && $newImagePath) {
        deleteCategoryImage($newImagePath, $baseDir);
    }

    jsonResponse(false, 'Failed to update category.');
}