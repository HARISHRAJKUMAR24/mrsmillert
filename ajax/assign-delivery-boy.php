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
$boyId   = (int)($_POST['delivery_boy_id'] ?? 0);

if ($orderId <= 0 || $boyId <= 0) {
    jsonResponse(false, 'Invalid order or delivery boy.');
}

try {
    // Verify order and boy exist
    $chk = $pdo->prepare("SELECT id FROM orders WHERE id = ? LIMIT 1");
    $chk->execute([$orderId]);
    if (!$chk->fetch()) jsonResponse(false, 'Order not found.');

    $chk2 = $pdo->prepare("SELECT id, full_name FROM delivery_boys WHERE id = ? AND status = 1 LIMIT 1");
    $chk2->execute([$boyId]);
    $boy = $chk2->fetch(PDO::FETCH_ASSOC);
    if (!$boy) jsonResponse(false, 'Delivery boy not found or inactive.');

    // Assign
    $upd = $pdo->prepare("UPDATE orders SET delivery_boy_id = ?, updated_at = NOW() WHERE id = ?");
    $upd->execute([$boyId, $orderId]);

    jsonResponse(true, 'Assigned to ' . $boy['full_name'] . '.', [
        'order_id'        => $orderId,
        'delivery_boy_id' => $boyId,
        'delivery_boy'    => $boy['full_name'],
    ]);
} catch (PDOException $e) {
    jsonResponse(false, 'Server error.');
}