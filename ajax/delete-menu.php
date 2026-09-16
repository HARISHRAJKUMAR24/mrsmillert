<?php
/* =========================================================
   MRS MILL@ — AJAX: DELETE MENU
   File: ./ajax/menu-delete.php
   Deletes the menu row + all its menu_products rows
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'Invalid menu ID.');

try {

    $stmt = $pdo->prepare("SELECT * FROM menus WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) jsonResponse(false, 'Menu not found.');

    $pdo->beginTransaction();

    $del1 = $pdo->prepare("DELETE FROM menu_products WHERE menu_code = ?");
    $del1->execute([$row['menu_code']]);

    $del2 = $pdo->prepare("DELETE FROM menus WHERE id = ?");
    $del2->execute([$id]);

    $pdo->commit();

    jsonResponse(true, 'Menu deleted successfully.');

} catch (PDOException $e) {

    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(false, 'Failed to delete menu: ' . $e->getMessage());
}