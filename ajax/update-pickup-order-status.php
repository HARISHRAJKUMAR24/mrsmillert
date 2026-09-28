<?php
/* =========================================================
   MRS MILL@ — AJAX: UPDATE ORDER STATUS (admin panel)
   File: ./ajax/update-order-status.php
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}

$orderId = (int)($_POST['order_id'] ?? 0);
$status  = trim($_POST['status'] ?? '');

$allowed = ['pending', 'confirmed', 'processing', 'delivered', 'cancelled'];

if ($orderId <= 0) {
    jsonResponse(false, 'Invalid order ID.');
}

if (!in_array($status, $allowed, true)) {
    jsonResponse(false, 'Invalid status value.');
}

try {

    /* Ensure order exists */
    $check = $pdo->prepare("SELECT id, status FROM orders WHERE id = ? LIMIT 1");
    $check->execute([$orderId]);
    $row = $check->fetch();

    if (!$row) {
        jsonResponse(false, 'Order not found.');
    }

    /* Update status */
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $orderId]);

    jsonResponse(true, 'Status updated successfully.', [
        'order_id' => $orderId,
        'status'   => $status
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Database error: ' . $e->getMessage());
}