<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

$boyId       = (int)($_POST['delivery_boy_id'] ?? 0);   /* NEW boy (TO) */
$fromBoyId   = (int)($_POST['from_boy_id'] ?? 0);       /* CURRENT boy (FROM) */
$orderIdsRaw = $_POST['order_ids'] ?? '[]';
$orderIds    = json_decode($orderIdsRaw, true);

if ($boyId <= 0)     jsonResponse(false, 'Please select the new delivery boy.');
if ($fromBoyId <= 0) jsonResponse(false, 'Please select the current delivery boy.');
if ($boyId === $fromBoyId) jsonResponse(false, 'From and To boy cannot be the same.');
if (!is_array($orderIds) || count($orderIds) === 0) {
    jsonResponse(false, 'No orders selected.');
}

/* Clean + dedupe */
$ids = [];
foreach ($orderIds as $id) {
    $id = (int)$id;
    if ($id > 0 && !in_array($id, $ids, true)) $ids[] = $id;
}
if (empty($ids)) jsonResponse(false, 'Invalid order list.');

try {
    /* Verify target delivery boy is active */
    $bCheck = $pdo->prepare(
        "SELECT id, full_name FROM delivery_boys WHERE id = ? AND status = 1 LIMIT 1"
    );
    $bCheck->execute([$boyId]);
    $toBoy = $bCheck->fetch(PDO::FETCH_ASSOC);
    if (!$toBoy) jsonResponse(false, 'Target delivery boy not found or inactive.');

    /* Also verify the from boy exists (extra safety) */
    $fCheck = $pdo->prepare(
        "SELECT id, full_name FROM delivery_boys WHERE id = ? LIMIT 1"
    );
    $fCheck->execute([$fromBoyId]);
    $fromBoy = $fCheck->fetch(PDO::FETCH_ASSOC);
    if (!$fromBoy) jsonResponse(false, 'Current delivery boy not found.');

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    /*
       Reassign ONLY orders that currently belong to FROM boy.
       This prevents accidentally moving someone else's orders.
       Skips cancelled / delivered orders.
    */
    $sql = "UPDATE orders
            SET delivery_boy_id = ?,
                updated_at = NOW()
            WHERE id IN ($placeholders)
              AND delivery_boy_id = ?
              AND status <> 'cancelled'
              AND status <> 'delivered'";

    $upd = $pdo->prepare($sql);
    $upd->execute(array_merge([$boyId], $ids, [$fromBoyId]));

    $updatedCount = $upd->rowCount();

    if ($updatedCount === 0) {
        jsonResponse(false, 'No orders were updated. They may have been changed by someone else.');
    }

    $msg = $updatedCount . ' order' . ($updatedCount === 1 ? '' : 's')
         . ' moved from ' . $fromBoy['full_name']
         . ' to ' . $toBoy['full_name'] . '.';

    jsonResponse(true, $msg, [
        'updated_count' => $updatedCount,
        'from_boy_id'   => $fromBoyId,
        'from_boy_name' => $fromBoy['full_name'],
        'to_boy_id'     => $boyId,
        'to_boy_name'   => $toBoy['full_name']
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error.');
}