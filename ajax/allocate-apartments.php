<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$boyId       = (int)($_POST['delivery_boy_id'] ?? 0);
$codesRaw    = $_POST['apartment_codes'] ?? '[]';
$codes       = json_decode($codesRaw, true);

if ($boyId <= 0) jsonResponse(false, 'Please select a delivery boy.');
if (!is_array($codes) || count($codes) === 0) {
    jsonResponse(false, 'Please select at least one apartment.');
}

/* Clean codes */
$cleanCodes = [];
foreach ($codes as $c) {
    $c = trim((string)$c);
    if ($c !== '' && !in_array($c, $cleanCodes, true)) $cleanCodes[] = $c;
}
if (empty($cleanCodes)) jsonResponse(false, 'No valid apartments selected.');

try {
    /* Verify boy */
    $bCheck = $pdo->prepare(
        "SELECT id FROM delivery_boys WHERE id = ? AND status = 1 LIMIT 1"
    );
    $bCheck->execute([$boyId]);
    if (!$bCheck->fetch()) jsonResponse(false, 'Delivery boy not found.');

    /* Verify codes exist */
    $in = implode(',', array_fill(0, count($cleanCodes), '?'));
    $aCheck = $pdo->prepare(
        "SELECT apartment_code, apartment_name
         FROM apartments
         WHERE status = 1 AND apartment_code IN ($in)"
    );
    $aCheck->execute($cleanCodes);
    $validApts = $aCheck->fetchAll(PDO::FETCH_ASSOC);
    if (empty($validApts)) jsonResponse(false, 'No valid apartments found.');

    $allocatedBy = $_SESSION['admin_username'] ?? $_SESSION['admin_name'] ?? 'Admin';

    $pdo->beginTransaction();

    $ins = $pdo->prepare(
        "INSERT IGNORE INTO apartment_delivery_boys
            (apartment_code, delivery_boy_id, allocated_by)
         VALUES (?, ?, ?)"
    );

    $inserted = 0;
    foreach ($validApts as $a) {
        $ins->execute([$a['apartment_code'], $boyId, $allocatedBy]);
        $inserted += $ins->rowCount();
    }

    /* Fetch new total count for this boy */
    $cntStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM apartment_delivery_boys WHERE delivery_boy_id = ?"
    );
    $cntStmt->execute([$boyId]);
    $newCount = (int)$cntStmt->fetchColumn();

    $pdo->commit();

    if ($inserted === 0) {
        jsonResponse(true, 'These apartments were already allocated to this delivery boy.', [
            'new_count' => $newCount
        ]);
    }

    jsonResponse(true, $inserted . ' apartment(s) allocated.', [
        'new_count' => $newCount
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(false, 'Server error.');
}