<?php
/* =========================================================
   MRS MILL@ — AJAX: UPDATE MENU
   File: ./ajax/menu-update.php
   - Updates menu name + time
   - Deletes all menu_products for this menu, re-inserts
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$id      = (int) ($_POST['id'] ?? 0);
$name    = trim($_POST['menu_name'] ?? '');
$startAt = trim($_POST['start_at'] ?? '');
$endAt   = trim($_POST['end_at'] ?? '');
$rowsRaw = $_POST['rows'] ?? '[]';

$rows = json_decode($rowsRaw, true);

if ($id <= 0)     jsonResponse(false, 'Invalid menu ID.');
if ($name === '') jsonResponse(false, 'Menu name is required.');

$startObj = DateTime::createFromFormat('Y-m-d H:i:s', $startAt);
$endObj   = DateTime::createFromFormat('Y-m-d H:i:s', $endAt);

if (!$startObj) jsonResponse(false, 'Invalid start date/time.');
if (!$endObj)   jsonResponse(false, 'Invalid end date/time.');
if ($endObj <= $startObj) jsonResponse(false, 'End must be after start.');

if (!is_array($rows) || count($rows) === 0) {
    jsonResponse(false, 'Please add at least one product.');
}

/* ---------------- FLATTEN ---------------- */

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

    if (count($vids) === 0) jsonResponse(false, $label . ': no valid variants.');

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

/* ---------------- VERIFY ---------------- */

try {

    $menuStmt = $pdo->prepare("SELECT * FROM menus WHERE id = ? LIMIT 1");
    $menuStmt->execute([$id]);
    $existing = $menuStmt->fetch();
    if (!$existing) jsonResponse(false, 'Menu not found.');

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
    jsonResponse(false, 'Server error while validating.');
}

/* ---------------- UPDATE ---------------- */

try {

    $pdo->beginTransaction();

    /* 1) menus */
    $stmt = $pdo->prepare(
        "UPDATE menus
         SET menu_name = ?, start_at = ?, end_at = ?
         WHERE id = ?"
    );
    $stmt->execute([
        $name,
        $startObj->format('Y-m-d H:i:s'),
        $endObj->format('Y-m-d H:i:s'),
        $id
    ]);

    /* 2) delete existing menu_products */
    $del = $pdo->prepare("DELETE FROM menu_products WHERE menu_code = ?");
    $del->execute([$existing['menu_code']]);

    /* 3) re-insert */
    $ins = $pdo->prepare(
        "INSERT INTO menu_products
            (menu_code, product_code, variant_id, stock_unlimited, stock_count)
         VALUES (?, ?, ?, ?, ?)"
    );
    foreach ($flat as $r) {
        $ins->execute([
            $existing['menu_code'],
            $r['product_code'],
            $r['variant_id'],
            $r['stock_unlimited'],
            $r['stock_count']
        ]);
    }

    $pdo->commit();

    jsonResponse(true, 'Menu updated successfully.', [
        'id'   => $id,
        'code' => $existing['menu_code'],
        'rows' => $flat
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(false, 'Failed to update menu: ' . $e->getMessage());
}