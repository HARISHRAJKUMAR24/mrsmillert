<?php
/* =========================================================
   MRS MILL@ — AJAX: ADD MENU
   File: ./ajax/add-menu.php
   - Generates menu_code (MEN001, MEN002 ...)
   - Saves status (1 = Active, 0 = Inactive)
   - Saves common start/end datetime
   - Saves per product row, expanding each selected variant into a row
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$name    = trim($_POST['menu_name'] ?? '');
$statusRaw = $_POST['menu_status'] ?? '1';
$startAt = trim($_POST['start_at'] ?? '');
$endAt   = trim($_POST['end_at'] ?? '');
$rowsRaw = $_POST['rows'] ?? '[]';

$rows = json_decode($rowsRaw, true);

/* Status */
$status = ($statusRaw === '1' || $statusRaw === 1 || $statusRaw === true) ? 1 : 0;

if ($name === '') jsonResponse(false, 'Menu name is required.');

$startObj = DateTime::createFromFormat('Y-m-d H:i:s', $startAt);
$endObj   = DateTime::createFromFormat('Y-m-d H:i:s', $endAt);

if (!$startObj) jsonResponse(false, 'Invalid start date/time.');
if (!$endObj)   jsonResponse(false, 'Invalid end date/time.');
if ($endObj <= $startObj) jsonResponse(false, 'End must be after start.');

if (!is_array($rows) || count($rows) === 0) {
    jsonResponse(false, 'Please add at least one product.');
}

/* ---------------- FLATTEN ROWS -> ONE ENTRY PER VARIANT ---------------- */

$flat = [];

foreach ($rows as $i => $r) {

    $label = 'Product #' . ($i + 1);

    $pcode = trim((string)($r['product_code'] ?? ''));
    $vids  = $r['variant_ids'] ?? [];
    $unlim = !empty($r['stock_unlimited']) ? 1 : 0;
    $count = (int)($r['stock_count'] ?? 0);

    if ($pcode === '') jsonResponse(false, $label . ': product missing.');
    if (!is_array($vids) || count($vids) === 0) {
        jsonResponse(false, $label . ': pick at least one variant.');
    }

    $vids = array_values(array_unique(array_map('intval', $vids)));
    $vids = array_values(array_filter($vids, fn($v) => $v > 0));

    if (count($vids) === 0) {
        jsonResponse(false, $label . ': no valid variants selected.');
    }

    if ($unlim) $count = 0;
    if ($count < 0) $count = 0;

    foreach ($vids as $vid) {
        $flat[] = [
            'product_code' => $pcode,
            'variant_id'   => $vid,
            'stock_unlimited' => $unlim,
            'stock_count'  => $count
        ];
    }
}

if (count($flat) === 0) jsonResponse(false, 'No valid selections.');

/* ---------------- VERIFY PRODUCTS + VARIANTS ---------------- */

try {

    $vcodes = array_values(array_unique(array_column($flat, 'product_code')));
    $vids   = array_values(array_unique(array_column($flat, 'variant_id')));

    $pPh = implode(',', array_fill(0, count($vcodes), '?'));
    $vPh = implode(',', array_fill(0, count($vids), '?'));

    $pStmt = $pdo->prepare("SELECT product_code FROM products WHERE product_code IN ($pPh)");
    $pStmt->execute($vcodes);
    $validProducts = $pStmt->fetchAll(PDO::FETCH_COLUMN);

    $vStmt = $pdo->prepare("SELECT id, product_code FROM product_variants WHERE id IN ($vPh)");
    $vStmt->execute($vids);
    $validVariants = [];
    foreach ($vStmt->fetchAll() as $v) {
        $validVariants[(int)$v['id']] = $v['product_code'];
    }

    foreach ($flat as $r) {
        if (!in_array($r['product_code'], $validProducts, true)) {
            jsonResponse(false, 'Product not found: ' . $r['product_code']);
        }
        if (!isset($validVariants[$r['variant_id']])) {
            jsonResponse(false, 'Variant not found: #' . $r['variant_id']);
        }
        if ($validVariants[$r['variant_id']] !== $r['product_code']) {
            jsonResponse(false, 'Variant does not belong to its product.');
        }
    }

} catch (PDOException $e) {
    jsonResponse(false, 'Server error while validating rows.');
}

/* ---------------- GENERATE MENU CODE ---------------- */

$menuCode = generateMenuCode($pdo);
if (!$menuCode) jsonResponse(false, 'Could not generate a menu code.');

/* ---------------- INSERT ---------------- */

try {

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO menus (menu_code, menu_name, start_at, end_at, status)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $menuCode,
        $name,
        $startObj->format('Y-m-d H:i:s'),
        $endObj->format('Y-m-d H:i:s'),
        $status
    ]);

    $menuId = (int) $pdo->lastInsertId();
    if ($menuId <= 0) throw new PDOException('Could not get menu id.');

    $link = $pdo->prepare(
        "INSERT INTO menu_products
            (menu_code, product_code, variant_id, stock_unlimited, stock_count)
         VALUES (?, ?, ?, ?, ?)"
    );

    foreach ($flat as $r) {
        $link->execute([
            $menuCode,
            $r['product_code'],
            $r['variant_id'],
            $r['stock_unlimited'],
            $r['stock_count']
        ]);
    }

    $pdo->commit();

    jsonResponse(true, 'Menu added successfully.', [
        'id'     => $menuId,
        'code'   => $menuCode,
        'status' => $status,
        'rows'   => $flat
    ]);

} catch (PDOException $e) {

    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(false, 'Failed to add menu: ' . $e->getMessage());
}