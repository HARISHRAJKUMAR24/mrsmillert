<?php
/* =========================================================
   MRS MILL@ — AJAX: ADD PRODUCT
   File: ./ajax/add-product.php
   - Auto-generates product_code
   - Uploads product image
   - Inserts MULTIPLE variants with OPTIONAL container price
   - Saves product status
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
$variantsRaw  = $_POST['variants'] ?? '[]';

$variants     = json_decode($variantsRaw, true);

$status = ($statusRaw === '1' || $statusRaw === 1 || $statusRaw === true) ? 1 : 0;

/* ---------------- VALIDATION ---------------- */

if ($name === '')       jsonResponse(false, 'Product name is required.');
if ($categoryId <= 0)   jsonResponse(false, 'Please choose a category.');

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

    $contEnabled = isset($v['container_enabled']) ? (int)$v['container_enabled'] : 0;
    $contPrice   = trim((string)($v['container_price'] ?? '0'));

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

    if ($contEnabled === 1) {
        if ($contPrice === '' || !is_numeric($contPrice) || (float)$contPrice < 0) {
            jsonResponse(false, $label . ': please enter a valid container price.');
        }
    } else {
        $contPrice = '0';
    }

    $cleanVariants[] = [
        'quantity'          => number_format((float)$qty, 2, '.', ''),
        'quantity_unit'     => $unit,
        'quantity_name'     => $qname,
        'price'             => number_format((float)$price, 2, '.', ''),
        'container_enabled' => $contEnabled === 1 ? 1 : 0,
        'container_price'   => number_format((float)$contPrice, 2, '.', '')
    ];
}

if (count($cleanVariants) === 0) {
    jsonResponse(false, 'Please add at least one valid quantity variant.');
}

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
            (product_code, quantity, quantity_unit, quantity_name, price,
             container_enabled, container_price)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    foreach ($cleanVariants as $v) {
        $vStmt->execute([
            $productCode,
            $v['quantity'],
            $v['quantity_unit'],
            $v['quantity_name'],
            $v['price'],
            $v['container_enabled'],
            $v['container_price']
        ]);
    }

    $pdo->commit();

    jsonResponse(
        true,
        'Product added successfully.',
        [
            'id'         => $productId,
            'code'       => $productCode,
            'status'     => $status,
            'variants'   => $cleanVariants,
            'image_url'  => ADMIN_URL . $relPath
        ]
    );

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (is_file($absPath)) @unlink($absPath);

    jsonResponse(false, 'Failed to add product: ' . $e->getMessage());
}