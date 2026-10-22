<?php
/* =========================================================
   MRS MILL@ — AJAX: ALLOCATE APARTMENTS TO DELIVERY BOY
   File: ./ajax/allocate-apartments.php

   Accepts (POST):
     - delivery_boy_id : int
     - apartment_codes : JSON array of strings
                         (real codes like APT001, and/or
                          IH_<hex> pseudo-codes for individual houses)

   Real codes are verified against `apartments`.
   IH_ codes are only format-validated — they are synthetic
   (derived from full_address) and do not exist in `apartments`.
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$boyId    = (int)($_POST['delivery_boy_id'] ?? 0);
$codesRaw = $_POST['apartment_codes'] ?? '[]';
$codes    = json_decode($codesRaw, true);

if ($boyId <= 0) {
    jsonResponse(false, 'Please select a delivery boy.');
}

if (!is_array($codes) || count($codes) === 0) {
    jsonResponse(false, 'Please select at least one apartment.');
}

/* ---------- Clean codes ---------- */
$cleanCodes = [];
foreach ($codes as $c) {
    $c = trim((string)$c);
    if ($c !== '' && !in_array($c, $cleanCodes, true)) {
        $cleanCodes[] = $c;
    }
}

if (empty($cleanCodes)) {
    jsonResponse(false, 'No valid apartments selected.');
}

/* ---------- Split into real codes vs IH pseudo-codes ---------- */
$realCodes = [];
$ihCodes   = [];

foreach ($cleanCodes as $c) {
    if (strpos($c, 'IH_') === 0) {
        $ihCodes[] = $c;
    } else {
        $realCodes[] = $c;
    }
}

try {
    /* ---------- Verify delivery boy ---------- */
    $bCheck = $pdo->prepare(
        "SELECT id FROM delivery_boys WHERE id = ? AND status = 1 LIMIT 1"
    );
    $bCheck->execute([$boyId]);
    if (!$bCheck->fetch()) {
        jsonResponse(false, 'Delivery boy not found.');
    }

    /* ---------- Collect valid codes ---------- */
    $validCodes = [];

    /* Real apartment codes — must exist and be active */
    if (!empty($realCodes)) {
        $in = implode(',', array_fill(0, count($realCodes), '?'));
        $aCheck = $pdo->prepare(
            "SELECT apartment_code
             FROM apartments
             WHERE status = 1 AND apartment_code IN ($in)"
        );
        $aCheck->execute($realCodes);
        while ($r = $aCheck->fetch(PDO::FETCH_ASSOC)) {
            $validCodes[] = $r['apartment_code'];
        }
    }

    /* IH pseudo-codes — validate format only: IH_ + 6–32 hex chars */
    foreach ($ihCodes as $c) {
        if (preg_match('/^IH_[a-f0-9]{6,32}$/i', $c)) {
            $validCodes[] = $c;
        }
    }

    if (empty($validCodes)) {
        jsonResponse(false, 'No valid apartments found.');
    }

    /* ---------- Allocate ---------- */
    $allocatedBy = $_SESSION['admin_username'] ?? $_SESSION['admin_name'] ?? 'Admin';

    $pdo->beginTransaction();

    $ins = $pdo->prepare(
        "INSERT IGNORE INTO apartment_delivery_boys
            (apartment_code, delivery_boy_id, allocated_by)
         VALUES (?, ?, ?)"
    );

    $inserted = 0;
    foreach ($validCodes as $code) {
        $ins->execute([$code, $boyId, $allocatedBy]);
        $inserted += $ins->rowCount();
    }

    /* ---------- New total count for this boy ---------- */
    $cntStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM apartment_delivery_boys WHERE delivery_boy_id = ?"
    );
    $cntStmt->execute([$boyId]);
    $newCount = (int)$cntStmt->fetchColumn();

    $pdo->commit();

    if ($inserted === 0) {
        jsonResponse(true, 'These apartments were already allocated to this delivery boy.', [
            'new_count' => $newCount
        ]);
    }

    jsonResponse(true, $inserted . ' apartment(s) allocated.', [
        'new_count' => $newCount
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(false, 'Server error: ' . $e->getMessage());
}