<?php
/* =========================================================
   MRS MILL@ — AJAX: RESET DELIVERY BOY PASSWORD
   File: ./ajax/reset-boy-password.php
   Accepts: id, password
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$id       = isset($_POST['id'])       ? (int) $_POST['id']           : 0;
$password = isset($_POST['password']) ? trim($_POST['password'])     : '';

if ($id <= 0) {
    jsonResponse(false, 'Invalid delivery boy ID.');
}

if (strlen($password) < 6) {
    jsonResponse(false, 'Password must be at least 6 characters long.');
}

try {

    /* Verify the boy exists */
    $check = $pdo->prepare(
        "SELECT id FROM delivery_boys WHERE id = ? LIMIT 1"
    );
    $check->execute([$id]);

    if (!$check->fetch()) {
        jsonResponse(false, 'Delivery boy not found.');
    }

    /* Hash and update */
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        "UPDATE delivery_boys
         SET password_hash = ?, token = NULL
         WHERE id = ?"
    );
    $stmt->execute([$hash, $id]);

    jsonResponse(true, 'Password reset successfully.', ['id' => $id]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error. Please try again.');
}