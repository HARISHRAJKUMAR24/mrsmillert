<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.');
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) jsonResponse(false, 'Not logged in.');
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') jsonResponse(false, 'Admin access required.');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'Invalid staff ID.');

if ($id === (int)$_SESSION['admin_id']) {
    jsonResponse(false, 'You cannot delete your own account.');
}

try {
    $chk = $pdo->prepare("SELECT id FROM staff WHERE id = ? LIMIT 1");
    $chk->execute([$id]);
    if (!$chk->fetch()) jsonResponse(false, 'Staff not found.');

    $pdo->prepare("DELETE FROM staff WHERE id = ?")->execute([$id]);

    jsonResponse(true, 'Staff deleted successfully.');

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to delete staff.');
}