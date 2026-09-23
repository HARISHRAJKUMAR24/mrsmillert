<?php
/* =========================================================
   MRS MILL@ — AJAX: UPDATE CONTAINERS (admin)
   File: ./ajax/update-containers.php
   Accepts: order_id, containers[] (JSON)
   Each: { id, qty_issued, qty_returned, amount_refunded,
           refund_pending, refund_mode }
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

$orderId    = (int)($_POST['order_id'] ?? 0);
$containers = json_decode($_POST['containers'] ?? '[]', true);

if ($orderId <= 0) {
    jsonResponse(false, 'Invalid order ID.');
}

if (!is_array($containers) || !count($containers)) {
    jsonResponse(false, 'No containers to update.');
}

$validModes = ['none', 'full', 'half', 'custom'];

try {

    $chk = $pdo->prepare("SELECT id FROM orders WHERE id = ? LIMIT 1");
    $chk->execute([$orderId]);
    if (!$chk->fetch()) {
        jsonResponse(false, 'Order not found.');
    }

    $pdo->beginTransaction();

    $update = $pdo->prepare(
        "UPDATE order_containers
         SET qty_issued      = ?,
             qty_returned    = ?,
             amount_refunded = ?,
             refund_pending  = ?,
             refund_mode     = ?,
             status          = ?,
             returned_at     = CASE
                                WHEN ? > 0 AND ? >= ? THEN COALESCE(returned_at, NOW())
                                ELSE returned_at
                               END
         WHERE id = ? AND order_id = ?"
    );

    $balancePending = 0;

    foreach ($containers as $c) {
        $cid      = (int)($c['id'] ?? 0);
        $issued   = max(0, (int)($c['qty_issued'] ?? 0));
        $returned = max(0, (int)($c['qty_returned'] ?? 0));
        $refunded = max(0, (float)($c['amount_refunded'] ?? 0));
        $pending  = max(0, (float)($c['refund_pending'] ?? 0));
        $mode     = trim((string)($c['refund_mode'] ?? 'none'));

        if ($cid <= 0) continue;
        if ($returned > $issued) $returned = $issued;
        if (!in_array($mode, $validModes, true)) $mode = 'none';

        if ($returned === 0) {
            $status = 'issued';
        } elseif ($returned < $issued) {
            $status = 'partial';
        } else {
            $status = 'returned';
        }

        $update->execute([
            $issued,
            $returned,
            $refunded,
            $pending,
            $mode,
            $status,
            $returned,
            $returned,
            $issued,
            $cid,
            $orderId
        ]);

        $balancePending += ($issued - $returned);
    }

    $pdo->prepare("UPDATE orders SET containers_balance = ? WHERE id = ?")
        ->execute([$balancePending, $orderId]);

    $pdo->commit();

    jsonResponse(true, 'Containers updated successfully.', [
        'order_id'           => $orderId,
        'containers_balance' => $balancePending
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(false, 'Failed to update containers: ' . $e->getMessage());
}