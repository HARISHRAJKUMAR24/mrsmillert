<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

$boyId = (int)($_GET['boy_id'] ?? 0);
if ($boyId <= 0) jsonResponse(false, 'Invalid delivery boy.');

try {
    /* Verify boy exists and is active */
    $bCheck = $pdo->prepare("SELECT id FROM delivery_boys WHERE id = ? AND status = 1 LIMIT 1");
    $bCheck->execute([$boyId]);
    if (!$bCheck->fetch()) jsonResponse(false, 'Delivery boy not found.');

    /* ---- Available apartments ----
       Only apartments that are NOT allocated to ANY delivery boy.
       Once an apartment is taken by someone, it disappears for everyone. */
    $availStmt = $pdo->prepare(
        "SELECT a.apartment_code, a.apartment_name, a.apartment_address
         FROM apartments a
         WHERE a.status = 1
           AND NOT EXISTS (
               SELECT 1 FROM apartment_delivery_boys adb
               WHERE adb.apartment_code = a.apartment_code
           )
         ORDER BY a.apartment_name ASC"
    );
    $availStmt->execute();
    $available = $availStmt->fetchAll(PDO::FETCH_ASSOC);

    /* ---- Allocated apartments (already mapped to THIS boy) ---- */
    $allocStmt = $pdo->prepare(
        "SELECT a.apartment_code, a.apartment_name, a.apartment_address,
                adb.allocated_at, adb.allocated_by
         FROM apartment_delivery_boys adb
         INNER JOIN apartments a ON a.apartment_code = adb.apartment_code
         WHERE adb.delivery_boy_id = ?
           AND a.status = 1
         ORDER BY a.apartment_name ASC"
    );
    $allocStmt->execute([$boyId]);
    $allocated = $allocStmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse(true, 'OK', [
        'available' => $available,
        'allocated' => $allocated,
    ]);
} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load apartments.');
}