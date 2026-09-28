<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

$menuId = (int)($_GET['menu_id'] ?? 0);
if ($menuId <= 0) jsonResponse(false, 'Invalid menu.');

try {
    /* Fetch the menu's start/end */
    $mStmt = $pdo->prepare(
        "SELECT id, menu_code, menu_name, start_at, end_at
         FROM menus WHERE id = ? LIMIT 1"
    );
    $mStmt->execute([$menuId]);
    $menu = $mStmt->fetch(PDO::FETCH_ASSOC);
    if (!$menu) jsonResponse(false, 'Menu not found.');

    /* Apartments with pending orders placed during this menu's window */
    $stmt = $pdo->prepare(
        "SELECT o.apartment_code,
                MAX(o.apartment_name) AS apartment_name,
                COUNT(*) AS pending_count
         FROM orders o
         WHERE o.apartment_code IS NOT NULL
           AND o.apartment_code <> ''
           AND o.status <> 'cancelled'
           AND o.delivery_status = 'disabled'
           AND o.created_at BETWEEN ? AND ?
         GROUP BY o.apartment_code
         ORDER BY apartment_name ASC"
    );
    $stmt->execute([$menu['start_at'], $menu['end_at']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse(true, 'OK', $rows);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load apartments.');
}