<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

$boyId   = (int)($_POST['delivery_boy_id'] ?? 0);
$orderIdsRaw = $_POST['order_ids'] ?? '[]';
$orderIds    = json_decode($orderIdsRaw, true);

if ($boyId <= 0) jsonResponse(false, 'Please select a delivery boy.');
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
    /* Verify delivery boy is active */
    $bCheck = $pdo->prepare(
        "SELECT id, full_name FROM delivery_boys WHERE id = ? AND status = 1 LIMIT 1"
    );
    $bCheck->execute([$boyId]);
    $boy = $bCheck->fetch(PDO::FETCH_ASSOC);
    if (!$boy) jsonResponse(false, 'Delivery boy not found or inactive.');

    /* Update all matching orders that are still pending (not cancelled, not delivered) */
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sql = "UPDATE orders
            SET delivery_boy_id = ?,
                updated_at = NOW()
            WHERE id IN ($placeholders)
              AND status <> 'cancelled'
              AND delivery_status = 'disabled'";

    $upd = $pdo->prepare($sql);
    $upd->execute(array_merge([$boyId], $ids));

    $updatedCount = $upd->rowCount();

    if ($updatedCount === 0) {
        jsonResponse(false, 'No orders were updated (all may already be delivered or cancelled).');
    }

    jsonResponse(true, $updatedCount . ' order' . ($updatedCount === 1 ? '' : 's') . ' assigned to ' . $boy['full_name'] . '.', [
        'updated_count' => $updatedCount,
        'boy_id'        => $boyId,
        'boy_name'      => $boy['full_name']
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error.');
}