<?php
require_once '../config/config.php';
require_once '../config/function.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid menu ID.']);
    exit;
}

if (!in_array($status, ['enabled', 'disabled'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid delivery status.']);
    exit;
}

try {

    // Fetch the menu
    $stmt = $pdo->prepare("SELECT id, start_at, end_at FROM menus WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $menu = $stmt->fetch();

    if (!$menu) {
        echo json_encode(['success' => false, 'message' => 'Menu not found.']);
        exit;
    }

    // Block toggling if expired
    $now   = time();
    $end   = strtotime($menu['end_at']);
    if ($end !== false && $now > $end) {
        echo json_encode([
            'success' => false,
            'message' => 'This menu has expired. Delivery status can only be changed on live or upcoming menus.'
        ]);
        exit;
    }

    // Update
    $upd = $pdo->prepare("UPDATE menus SET delivery_status = ?, updated_at = NOW() WHERE id = ?");
    $upd->execute([$status, $id]);

    echo json_encode([
        'success' => true,
        'message' => $status === 'enabled'
            ? 'Delivery enabled for this menu.'
            : 'Delivery disabled for this menu.',
        'delivery_status' => $status
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}