<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.');
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) jsonResponse(false, 'Not logged in.');
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') jsonResponse(false, 'Admin access required.');

$id     = (int)($_POST['id'] ?? 0);
$status = isset($_POST['status']) ? (int)$_POST['status'] : -1;

if ($id <= 0) jsonResponse(false, 'Invalid staff ID.');
if ($status !== 0 && $status !== 1) jsonResponse(false, 'Status must be 0 or 1.');

try {
    $chk = $pdo->prepare("SELECT id FROM staff WHERE id = ? LIMIT 1");
    $chk->execute([$id]);
    if (!$chk->fetch()) jsonResponse(false, 'Staff not found.');

    $pdo->prepare("UPDATE staff SET status = ? WHERE id = ?")->execute([$status, $id]);

    jsonResponse(true, $status === 1 ? 'Staff activated.' : 'Staff deactivated.', [
        'id' => $id, 'status' => $status
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error.');
}