<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.');

/* Admin + staff both allowed */
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

$id     = (int)($_POST['id'] ?? 0);
$status = (int)($_POST['status'] ?? 0);

if ($id <= 0) jsonResponse(false, 'Invalid ID.');
if (!in_array($status, [0, 1, 2], true)) {
    jsonResponse(false, 'Invalid status value.');
}

try {
    /* Ensure the status column exists */
    try {
        $pdo->exec(
            "ALTER TABLE customer_address_requests
             ADD COLUMN status TINYINT(1) NOT NULL DEFAULT 0"
        );
    } catch (PDOException $e) { /* ignore */ }

    $stmt = $pdo->prepare(
        "UPDATE customer_address_requests
         SET status = ?, updated_at = NOW()
         WHERE id = ?"
    );
    $stmt->execute([$status, $id]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(false, 'Request not found or status unchanged.');
    }

    $labels = [0 => 'Not Called', 1 => 'Called', 2 => 'Follow-up'];

    jsonResponse(true, 'Status updated to ' . $labels[$status], [
        'id'     => $id,
        'status' => $status,
        'label'  => $labels[$status]
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error.');
}