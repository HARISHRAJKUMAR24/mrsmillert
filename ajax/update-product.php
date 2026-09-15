<?php
/* =========================================================
   MRS MILL@ — AJAX: UPDATE PRODUCT
   File: ./ajax/product-update.php

   - Saves product_name, category_id, product_image, status
   - Rebuilds variants (delete + re-insert)
   - Rebuilds apartment links
   ========================================================= */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'message' => 'PHP Fatal: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']
        ]);
    }
});

set_error_handler(function ($no, $str, $file, $line) {
    throw new ErrorException($str, 0, $no, $file, $line);
});

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

$baseDir = dirname(__DIR__);

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Method not allowed.');
    }

    $id          = (int) ($_POST['id'] ?? 0);
    $name        = trim($_POST['product_name'] ?? '');
    $categoryId  = (int) ($_POST['category_id'] ?? 0);
    $statusRaw   = $_POST['product_status'] ?? '1';
    $removeImage = (int) ($_POST['remove_image'] ?? 0);
    $aptIdsRaw   = $_POST['apartment_ids'] ?? '[]';
    $variantsRaw = $_POST['variants'] ?? '[]';

    $apartmentIds = json_decode($aptIdsRaw, true);
    $variants     = json_decode($variantsRaw, true);

    /* Status: 1 = Active, 0 = Inactive */
    $status = ($statusRaw === '1' || $statusRaw === 1 || $statusRaw === true) ? 1 : 0;

    /* ---------------- VALIDATION ---------------- */

    if ($id <= 0)             jsonResponse(false, 'Invalid product ID.');
    if ($name === '')         jsonResponse(false, 'Product name is required.');
    if ($categoryId <= 0)     jsonResponse(false, 'Please choose a category.');

    if (!is_array($apartmentIds) || count($apartmentIds) === 0) {
        jsonResponse(false, 'Please select at least one apartment.');
    }

    $apartmentIds = array_values(array_unique(array_map('intval', $apartmentIds)));
    $apartmentIds = array_values(array_filter($apartmentIds, fn ($v) => $v > 0));

    if (count($apartmentIds) === 0) {
        jsonResponse(false, 'Please select at least one valid apartment.');
    }

    if (!is_array($variants) || count($variants) === 0) {
        jsonResponse(false, 'Please add at least one quantity variant.');
    }

    $allowedUnits = ['liter', 'milliliter', 'gram', 'kilogram', 'plate', 'packet', 'bucket'];

    $cleanVariants = [];

    foreach ($variants as $i => $v) {

        $label = 'Variant #' . ($i + 1);

        $qty   = trim((string)($v['quantity'] ?? ''));
        $unit  = trim((string)($v['quantity_unit'] ?? ''));
        $qname = trim((string)($v['quantity_name'] ?? ''));
        $price = trim((string)($v['price'] ?? ''));

        if ($qty === '' || !is_numeric($qty) || (float)$qty <= 0) {
            jsonResponse(false, $label . ': please enter a valid quantity.');
        }
        if ($unit === '' || !in_array($unit, $allowedUnits, true)) {
            jsonResponse(false, $label . ': please choose a valid unit.');
        }
        if ($qname === '') {
            jsonResponse(false, $label . ': quantity name is required.');
        }
        if ($price === '' || !is_numeric($price) || (float)$price < 0) {
            jsonResponse(false, $label . ': please enter a valid price.');
        }

        $cleanVariants[] = [
            'quantity'      => number_format((float)$qty, 2, '.', ''),
            'quantity_unit' => $unit,
            'quantity_name' => $qname,
            'price'         => number_format((float)$price, 2, '.', '')
        ];
    }

    /* ---------------- LOAD EXISTING ---------------- */

    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        jsonResponse(false, 'Product not found.');
    }

    $chk = $pdo->prepare("SELECT id FROM categories WHERE id = ? LIMIT 1");
    $chk->execute([$categoryId]);
    if (!$chk->fetch()) {
        jsonResponse(false, 'Category not found.');
    }

    /* ---------------- APARTMENT CODES ---------------- */

    $placeholders = implode(',', array_fill(0, count($apartmentIds), '?'));

    $stmt = $pdo->prepare(
        "SELECT apartment_code
         FROM apartments
         WHERE id IN ($placeholders)"
    );
    $stmt->execute($apartmentIds);

    $apartmentCodes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($apartmentCodes) === 0) {
        jsonResponse(false, 'No valid apartments found.');
    }

    /* ---------------- IMAGE HANDLING ---------------- */

    $currentPath  = $existing['product_image'] ?: '';
    $newImagePath = $currentPath;
    $uploadedNew  = false;

    $hasNewFile = isset($_FILES['product_image']) &&
        ($_FILES['product_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($hasNewFile) {

        $file = $_FILES['product_image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(false, 'Image upload failed (code ' . $file['error'] . ').');
        }

        if ($file['size'] > 3 * 1024 * 1024) {
            jsonResponse(false, 'Image must be under 3 MB.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif'
        ];

        if (!isset($allowed[$mime])) {
            jsonResponse(false, 'Only JPG, PNG, WEBP or GIF allowed.');
        }

        $ext = $allowed[$mime];

        $datePart   = date('Ymd');
        $randomPart = bin2hex(random_bytes(5));

        $relFolder = 'uploads/product/' . $datePart . '/' . $randomPart . '/';
        $absFolder = rtrim($baseDir, '/\\') . '/' . $relFolder;

        if (!is_dir($absFolder)) {
            if (!mkdir($absFolder, 0775, true)) {
                jsonResponse(false, 'Could not create upload folder.');
            }
        }

        $fileName = 'image.' . $ext;
        $absPath  = $absFolder . $fileName;
        $relPath  = $relFolder . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            jsonResponse(false, 'Could not save uploaded image.');
        }

        if ($currentPath !== '' && function_exists('deleteCategoryImage')) {
            deleteCategoryImage($currentPath, $baseDir);
        }

        $newImagePath = $relPath;
        $uploadedNew  = true;

    } elseif ($removeImage === 1 && $currentPath !== '') {

        if (function_exists('deleteCategoryImage')) {
            deleteCategoryImage($currentPath, $baseDir);
        }
        $newImagePath = null;
    }

    /* ---------------- UPDATE DB ---------------- */

    $pdo->beginTransaction();

    /* 1) products (includes status) */
    $stmt = $pdo->prepare(
        "UPDATE products
         SET product_name  = ?,
             category_id   = ?,
             product_image = ?,
             status        = ?
         WHERE id = ?"
    );

    $stmt->execute([
        $name,
        $categoryId,
        $newImagePath,
        $status,
        $id
    ]);

    /* 2) Rebuild variants */
    $delV = $pdo->prepare(
        "DELETE FROM product_variants WHERE product_code = ?"
    );
    $delV->execute([$existing['product_code']]);

    $insV = $pdo->prepare(
        "INSERT INTO product_variants
            (product_code, quantity, quantity_unit, quantity_name, price)
         VALUES (?, ?, ?, ?, ?)"
    );

    foreach ($cleanVariants as $v) {
        $insV->execute([
            $existing['product_code'],
            $v['quantity'],
            $v['quantity_unit'],
            $v['quantity_name'],
            $v['price']
        ]);
    }

    /* 3) Rebuild apartment links */
    $delA = $pdo->prepare(
        "DELETE FROM product_apartments WHERE product_code = ?"
    );
    $delA->execute([$existing['product_code']]);

    $insA = $pdo->prepare(
        "INSERT INTO product_apartments (product_code, apartment_code)
         VALUES (?, ?)"
    );

    foreach ($apartmentCodes as $code) {
        $insA->execute([$existing['product_code'], $code]);
    }

    $pdo->commit();

    jsonResponse(
        true,
        'Product updated successfully.',
        [
            'id'        => $id,
            'code'      => $existing['product_code'],
            'status'    => $status,
            'variants'  => $cleanVariants,
            'image_url' => $newImagePath ? ADMIN_URL . $newImagePath : ''
        ]
    );

} catch (Throwable $e) {

    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (!empty($uploadedNew) && !empty($newImagePath) && function_exists('deleteCategoryImage')) {
        try { deleteCategoryImage($newImagePath, $baseDir); } catch (Throwable $x) {}
    }

    jsonResponse(false, 'Server error: ' . $e->getMessage());
}