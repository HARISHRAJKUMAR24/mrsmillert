<?php
/* =========================================================
   MRS MILL@ — AJAX: UPDATE REFERRAL (referrer name/mobile)
   File: ./ajax/referrals-update.php
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.');
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) jsonResponse(false, 'Not logged in.');

$id              = (int)($_POST['id'] ?? 0);
$referrerName    = trim($_POST['referrer_name'] ?? '');
$referrerMobile  = trim($_POST['referrer_mobile'] ?? '');

if ($id <= 0) jsonResponse(false, 'Invalid referral ID.');

/* Clean mobile — digits only */
if ($referrerMobile !== '') {
    $referrerMobile = preg_replace('/\D+/', '', $referrerMobile);
    if (strlen($referrerMobile) < 10 || strlen($referrerMobile) > 15) {
        jsonResponse(false, 'Referrer mobile must be 10–15 digits.');
    }
}

try {
    $stmt = $pdo->prepare("SELECT * FROM customer_referrals WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) jsonResponse(false, 'Referral not found.');
} catch (PDOException $e) {
    jsonResponse(false, 'Server error.');
}

/* -------- Resolve referrer customer id -------- */
$refCustomerId = null;

try {
    if ($referrerMobile !== '') {
        $q = $pdo->prepare("SELECT id FROM customers WHERE mobile_number = ? LIMIT 1");
        $q->execute([$referrerMobile]);
        $found = $q->fetchColumn();
        if ($found) $refCustomerId = (int)$found;
    }
    if ($refCustomerId === null && $referrerName !== '') {
        $q = $pdo->prepare("SELECT id FROM customers WHERE full_name = ? LIMIT 1");
        $q->execute([$referrerName]);
        $found = $q->fetchColumn();
        if ($found) $refCustomerId = (int)$found;
    }
} catch (PDOException $e) { /* ignore */ }

try {
    /* Save updated referral */
    $pdo->prepare(
        "UPDATE customer_referrals
         SET referrer_name        = ?,
             referrer_mobile      = ?,
             referrer_customer_id = ?,
             updated_at = NOW()
         WHERE id = ?"
    )->execute([
        $referrerName !== '' ? $referrerName : null,
        $referrerMobile !== '' ? $referrerMobile : null,
        $refCustomerId,
        $id
    ]);

    /* Keep customers.referred_by_id pointing at the correct referrer (if changed) */
    $newCustomerId = (int)($row['customer_id'] ?? 0);
    if ($newCustomerId > 0) {
        $referredById = $refCustomerId ?: $id; // fallback to referral row id
        try {
            $pdo->prepare("UPDATE customers SET referred_by_id = ? WHERE id = ?")
                ->execute([$referredById, $newCustomerId]);
        } catch (PDOException $e) { /* ignore */ }
    }

    jsonResponse(true, 'Referral updated successfully.', [
        'id'                   => $id,
        'referrer_name'        => $referrerName,
        'referrer_mobile'      => $referrerMobile,
        'referrer_customer_id' => $refCustomerId
    ]);
} catch (PDOException $e) {
    jsonResponse(false, 'Failed to update: ' . $e->getMessage());
}