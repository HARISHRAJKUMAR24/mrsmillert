<?php
/* =========================================================
   MRS MILL@ — AJAX: ADD PRODUCT
   File: ./ajax/add-product.php
   - Auto-generates product_code (PRD001, PRD002 ...)
   - Uploads product image
   - Inserts MULTIPLE quantity variants into product_variants
   - Links apartments by CODE (apartment_code) into
     product_apartments
   - Saves product status (active/inactive)
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$name         = trim($_POST['product_name'] ?? '');
$categoryId   = (int) ($_POST['category_id'] ?? 0);
$statusRaw    = $_POST['product_status'] ?? '1';
$aptIdsRaw    = $_POST['apartment_ids'] ?? '[]';
$variantsRaw  = $_POST['variants'] ?? '[]';

$apartmentIds = json_decode($aptIdsRaw, true);
$variants     = json_decode($variantsRaw, true);

/* Status: 1 = Active, 0 = Inactive */
$status = ($statusRaw === '1' || $statusRaw === 1 || $statusRaw === true) ? 1 : 0;

/* ---------------- VALIDATION ---------------- */

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

/* ---- Variants ---- */
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

if (count($cleanVariants) === 0) {
    jsonResponse(false, 'Please add at least one valid quantity variant.');
}

/* image required */
if (!isset($_FILES['product_image']) ||
    ($_FILES['product_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    jsonResponse(false, 'Please upload a product image.');
}

/* verify category */
try {
    $chk = $pdo->prepare("SELECT id FROM categories WHERE id = ? LIMIT 1");
    $chk->execute([$categoryId]);
    if (!$chk->fetch()) {
        jsonResponse(false, 'Category not found.');
    }
} catch (PDOException $e) {
    jsonResponse(false, 'Server error. Please try again.');
}

/* ---------------- FETCH APARTMENT CODES ---------------- */

$apartmentCodes = [];

try {

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

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to read apartments.');
}

/* ---------------- UPLOAD IMAGE ---------------- */

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

$baseDir    = dirname(__DIR__);
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

/* ---------------- GENERATE PRODUCT CODE ---------------- */

$productCode = generateProductCode($pdo);

if ($productCode === '' || $productCode === null) {
    @unlink($absPath);
    jsonResponse(false, 'Could not generate a product code.');
}

/* ---------------- INSERT ---------------- */

try {

    $pdo->beginTransaction();

    /* 1) products */
    $stmt = $pdo->prepare(
        "INSERT INTO products
            (product_code, product_name, category_id, product_image, status)
         VALUES (?, ?, ?, ?, ?)"
    );

    $stmt->execute([
        $productCode,
        $name,
        $categoryId,
        $relPath,
        $status
    ]);

    $productId = (int) $pdo->lastInsertId();

    if ($productId <= 0) {
        throw new PDOException('Could not get product id.');
    }

    /* 2) product_variants */
    $vStmt = $pdo->prepare(
        "INSERT INTO product_variants
            (product_code, quantity, quantity_unit, quantity_name, price)
         VALUES (?, ?, ?, ?, ?)"
    );

    foreach ($cleanVariants as $v) {
        $vStmt->execute([
            $productCode,
            $v['quantity'],
            $v['quantity_unit'],
            $v['quantity_name'],
            $v['price']
        ]);
    }

    /* 3) product_apartments */
    $link = $pdo->prepare(
        "INSERT INTO product_apartments (product_code, apartment_code)
         VALUES (?, ?)"
    );

    foreach ($apartmentCodes as $code) {
        $link->execute([$productCode, $code]);
    }

    $pdo->commit();

    jsonResponse(
        true,
        'Product added successfully.',
        [
            'id'          => $productId,
            'code'        => $productCode,
            'status'      => $status,
            'variants'    => $cleanVariants,
            'image_url'   => ADMIN_URL . $relPath,
            'apartments'  => $apartmentCodes
        ]
    );

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (is_file($absPath)) @unlink($absPath);

    jsonResponse(false, 'Failed to add product: ' . $e->getMessage());
}