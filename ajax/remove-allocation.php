<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$boyId = (int)($_POST['delivery_boy_id'] ?? 0);
$code  = trim((string)($_POST['apartment_code'] ?? ''));

if ($boyId <= 0 || $code === '') jsonResponse(false, 'Invalid parameters.');

try {
    $stmt = $pdo->prepare(
        "DELETE FROM apartment_delivery_boys
         WHERE delivery_boy_id = ? AND apartment_code = ?"
    );
    $stmt->execute([$boyId, $code]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(false, 'Allocation not found.');
    }

    /* New count for the boy */
    $cntStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM apartment_delivery_boys WHERE delivery_boy_id = ?"
    );
    $cntStmt->execute([$boyId]);
    $newCount = (int)$cntStmt->fetchColumn();

    jsonResponse(true, 'Removed.', ['new_count' => $newCount]);
} catch (PDOException $e) {
    jsonResponse(false, 'Server error.');
}