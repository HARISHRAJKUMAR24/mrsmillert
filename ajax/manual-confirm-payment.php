<?php
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
if ($orderId <= 0) jsonResponse(false, 'Invalid order.');

try {
    $chk = $pdo->prepare("SELECT id, order_code, payment_status FROM orders WHERE id = ? LIMIT 1");
    $chk->execute([$orderId]);
    $order = $chk->fetch(PDO::FETCH_ASSOC);

    if (!$order) jsonResponse(false, 'Order not found.');

    if ($order['payment_status'] === 'paid') {
        jsonResponse(true, 'Already paid.', ['order_code' => $order['order_code']]);
    }

    $up = $pdo->prepare(
        "UPDATE orders
         SET payment_status = 'paid',
             status         = 'confirmed',
             paid_at        = NOW()
         WHERE id = ?"
    );
    $up->execute([$orderId]);

    jsonResponse(true, 'Payment confirmed.', [
        'order_id'   => $orderId,
        'order_code' => $order['order_code'],
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error.');
}