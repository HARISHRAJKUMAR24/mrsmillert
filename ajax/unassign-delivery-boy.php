<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$orderId = (int)($_POST['order_id'] ?? 0);

if ($orderId <= 0) {
    jsonResponse(false, 'Invalid order.');
}

try {
    $chk = $pdo->prepare("SELECT id, delivery_boy_id FROM orders WHERE id = ? LIMIT 1");
    $chk->execute([$orderId]);
    $order = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$order) jsonResponse(false, 'Order not found.');
    if (empty($order['delivery_boy_id'])) jsonResponse(false, 'No delivery boy assigned to this order.');

    $upd = $pdo->prepare("UPDATE orders SET delivery_boy_id = NULL, updated_at = NOW() WHERE id = ?");
    $upd->execute([$orderId]);

    jsonResponse(true, 'Delivery boy unassigned.', [
        'order_id' => $orderId,
    ]);
} catch (PDOException $e) {
    jsonResponse(false, 'Server error.');
}