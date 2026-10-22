<?php
/* =========================================================
   MRS MILL@ — AJAX: UPDATE PICKUP ORDER STATUS (admin)
   File: ./ajax/update-pickup-order-status.php
   Accepts (POST):
     - order_id : int
     - status   : one of pending|confirmed|processing|delivered|cancelled
   Only pickup orders can be updated via this endpoint.
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

$orderId = (int)($_POST['order_id'] ?? 0);
$status  = trim($_POST['status'] ?? '');

$validStatuses = ['pending', 'confirmed', 'processing', 'delivered', 'cancelled'];

if ($orderId <= 0) {
    jsonResponse(false, 'Invalid order ID.');
}

if (!in_array($status, $validStatuses, true)) {
    jsonResponse(false, 'Invalid status.');
}

try {

    /* Verify order exists AND is a pickup order */
    $chk = $pdo->prepare(
        "SELECT id, delivery_mode FROM orders WHERE id = ? LIMIT 1"
    );
    $chk->execute([$orderId]);
    $row = $chk->fetch();

    if (!$row) {
        jsonResponse(false, 'Order not found.');
    }

    if ($row['delivery_mode'] !== 'pickup') {
        jsonResponse(false, 'Only pickup orders can be updated here.');
    }

    /* Update status */
    $up = $pdo->prepare(
        "UPDATE orders SET status = ? WHERE id = ? AND delivery_mode = 'pickup'"
    );
    $up->execute([$status, $orderId]);

    jsonResponse(true, 'Order status updated.', [
        'id'     => $orderId,
        'status' => $status,
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to update status: ' . $e->getMessage());
}