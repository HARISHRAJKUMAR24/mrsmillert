<?php
/* =========================================================
   MRS MILL@ — AJAX: TOGGLE DELIVERY BOY STATUS
   File: ./ajax/toggle-boy-status.php
   Accepts: id, status (0 or 1)
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$id     = isset($_POST['id'])     ? (int) $_POST['id']     : 0;
$status = isset($_POST['status']) ? (int) $_POST['status'] : -1;

if ($id <= 0) {
    jsonResponse(false, 'Invalid delivery boy ID.');
}

if ($status !== 0 && $status !== 1) {
    jsonResponse(false, 'Status must be 0 or 1.');
}

try {
    $check = $pdo->prepare("SELECT id FROM delivery_boys WHERE id = ? LIMIT 1");
    $check->execute([$id]);

    if (!$check->fetch()) {
        jsonResponse(false, 'Delivery boy not found.');
    }

    $stmt = $pdo->prepare("UPDATE delivery_boys SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    jsonResponse(
        true,
        $status === 1 ? 'Delivery boy activated.' : 'Delivery boy deactivated.',
        ['id' => $id, 'status' => $status]
    );

} catch (PDOException $e) {
    jsonResponse(false, 'Server error. Please try again.');
}