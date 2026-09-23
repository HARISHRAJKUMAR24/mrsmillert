<?php
/* =========================================================
   MRS MILL@ — AJAX: UPDATE ORDER STATUS (admin)
   File: ./ajax/update-order-status.php
   Accepts: id, status, payment_status
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

$id     = (int)($_POST['id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$pay    = trim($_POST['payment_status'] ?? '');

$validOrderStatuses = ['pending', 'confirmed', 'processing', 'delivered', 'cancelled'];
$validPayStatuses   = ['unpaid', 'paid', 'failed'];

if ($id <= 0) {
    jsonResponse(false, 'Invalid order ID.');
}

if (!in_array($status, $validOrderStatuses, true)) {
    jsonResponse(false, 'Invalid order status.');
}

if (!in_array($pay, $validPayStatuses, true)) {
    jsonResponse(false, 'Invalid payment status.');
}

try {

    /* Verify */
    $chk = $pdo->prepare("SELECT id FROM orders WHERE id = ? LIMIT 1");
    $chk->execute([$id]);
    if (!$chk->fetch()) {
        jsonResponse(false, 'Order not found.');
    }

    /* If payment marked as paid, set paid_at */
    if ($pay === 'paid') {
        $up = $pdo->prepare(
            "UPDATE orders
             SET status = ?, payment_status = ?,
                 paid_at = COALESCE(paid_at, NOW())
             WHERE id = ?"
        );
    } else {
        $up = $pdo->prepare(
            "UPDATE orders
             SET status = ?, payment_status = ?,
                 paid_at = CASE WHEN ? = 'paid' THEN paid_at ELSE NULL END
             WHERE id = ?"
        );
        /* Simpler: just null it when not paid */
        $up = $pdo->prepare(
            "UPDATE orders
             SET status = ?, payment_status = ?,
                 paid_at = NULL
             WHERE id = ?"
        );
    }

    $up->execute([$status, $pay, $id]);

    jsonResponse(true, 'Order updated.', [
        'id'             => $id,
        'status'         => $status,
        'payment_status' => $pay
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to update order.');
}