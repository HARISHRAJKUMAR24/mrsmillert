<?php
/* =========================================================
   MRS MILL@ — AJAX: RECEIVE CONTAINERS (admin)
   File: ./ajax/container-receive.php

   Accepts (POST):
     - group_key            : "cid_<id>" or "mob_<mobile>"
     - customer_id          : int (0 if grouping by mobile)
     - received_containers  : int (>0)
     - note                 : string (optional)

   Effects:
     - Increments order_containers.received_containers (oldest first)
     - Sets status = partial | received
     - Credits customers.wallet_balance
     - Logs into customer_wallet_transactions
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

$groupKey   = trim($_POST['group_key'] ?? '');
$customerId = (int)($_POST['customer_id'] ?? 0);
$received   = (int)($_POST['received_containers'] ?? 0);
$note       = trim($_POST['note'] ?? '');

if ($groupKey === '' && $customerId <= 0) jsonResponse(false, 'Invalid request.');
if ($received <= 0)                        jsonResponse(false, 'Enter a valid count.');

/* ---------- Resolve customer ID from group_key if missing ---------- */
if ($customerId <= 0 && strpos($groupKey, 'cid_') === 0) {
    $customerId = (int)substr($groupKey, 4);
}

$mobile = '';
if (strpos($groupKey, 'mob_') === 0) {
    $mobile = substr($groupKey, 4);
}

/* If still no customer id but we have a mobile, look it up */
if ($customerId <= 0 && $mobile !== '') {
    $cs = $pdo->prepare("SELECT id FROM customers WHERE mobile_number = ? LIMIT 1");
    $cs->execute([$mobile]);
    $found = (int)$cs->fetchColumn();
    if ($found > 0) $customerId = $found;
}

if ($customerId <= 0) {
    jsonResponse(false, 'Cannot resolve customer for this group.');
}

try {

    $pdo->beginTransaction();

    /* ---------- Fetch pending rows for this customer ---------- */
    $stmt = $pdo->prepare(
        "SELECT oc.*
         FROM order_containers oc
         WHERE oc.customer_id = ?
           AND (oc.total_containers - oc.received_containers) > 0
         ORDER BY oc.id ASC
         FOR UPDATE"
    );
    $stmt->execute([$customerId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        $pdo->rollBack();
        jsonResponse(false, 'No pending containers for this customer.');
    }

    /* ---------- Validate total ---------- */
    $totalPending = 0;
    foreach ($rows as $r) {
        $totalPending += ((int)$r['total_containers'] - (int)$r['received_containers']);
    }

    if ($received > $totalPending) {
        $pdo->rollBack();
        jsonResponse(false, 'Cannot receive more than ' . $totalPending . ' pending.');
    }

    /* ---------- Distribute across rows (oldest first) ---------- */
    $remaining   = $received;
    $totalRefund = 0.0;
    $affected    = [];

    foreach ($rows as $r) {
        if ($remaining <= 0) break;

        $rowPending = (int)$r['total_containers'] - (int)$r['received_containers'];
        if ($rowPending <= 0) continue;

        $take = min($rowPending, $remaining);

        $total = (int)$r['total_containers'];
        $unitAmt = $total > 0 ? ((float)$r['container_amount'] / $total) : 0;
        $refund  = round($unitAmt * $take, 2);
        $totalRefund += $refund;

        $newReceived = (int)$r['received_containers'] + $take;
        $newStatus   = ($newReceived >= $total) ? 'received' : 'partial';

        $upd = $pdo->prepare(
            "UPDATE order_containers
             SET received_containers = ?,
                 status              = ?,
                 received_at         = NOW(),
                 received_by_id      = ?,
                 received_by_name    = ?,
                 note                = ?,
                 updated_at          = NOW()
             WHERE id = ?"
        );
        $upd->execute([
            $newReceived,
            $newStatus,
            (int)$_SESSION['admin_id'],
            $_SESSION['admin_name'] ?? 'Admin',
            $note !== '' ? $note : ($r['note'] ?? null),
            (int)$r['id'],
        ]);

        $affected[] = [
            'order_id' => (int)$r['order_id'],
            'received' => $take,
            'refund'   => $refund,
        ];

        $remaining -= $take;
    }

    /* ---------- Credit wallet ---------- */
    $walletInfo = null;

    if ($totalRefund > 0) {
        $cStmt = $pdo->prepare(
            "SELECT id, full_name, mobile_number, wallet_balance
             FROM customers WHERE id = ? LIMIT 1 FOR UPDATE"
        );
        $cStmt->execute([$customerId]);
        $cust = $cStmt->fetch(PDO::FETCH_ASSOC);

        if (!$cust) {
            $pdo->rollBack();
            jsonResponse(false, 'Customer not found.');
        }

        $before = (float)$cust['wallet_balance'];
        $after  = round($before + $totalRefund, 2);

        $updC = $pdo->prepare(
            "UPDATE customers
             SET wallet_balance = ?, updated_at = NOW()
             WHERE id = ?"
        );
        $updC->execute([$after, $customerId]);

        /* Generate txn code */
        do {
            $txnCode = 'TXN' . date('ymd') . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $chk = $pdo->prepare("SELECT 1 FROM customer_wallet_transactions WHERE txn_code = ? LIMIT 1");
            $chk->execute([$txnCode]);
        } while ($chk->fetchColumn());

        $log = $pdo->prepare(
            "INSERT INTO customer_wallet_transactions
                (customer_id, customer_name, customer_mobile, txn_code,
                 txn_type, amount, balance_before, balance_after,
                 source, note, created_by_id, created_by_name, created_at)
             VALUES (?, ?, ?, ?, 'credit', ?, ?, ?, 'refund', ?, ?, ?, NOW())"
        );
        $log->execute([
            $customerId,
            $cust['full_name'],
            $cust['mobile_number'],
            $txnCode,
            $totalRefund,
            $before,
            $after,
            'Container return (' . $received . ' pcs)',
            (int)$_SESSION['admin_id'],
            $_SESSION['admin_name'] ?? 'Admin',
        ]);

        $walletInfo = [
            'balance_before' => $before,
            'balance_after'  => $after,
            'amount'         => $totalRefund,
        ];
    }

    $pdo->commit();

    $msg = $received . ' container' . ($received === 1 ? '' : 's') .
           ' received · ₹' . number_format($totalRefund, 2) . ' credited to wallet.';

    jsonResponse(true, $msg, [
        'received'        => $received,
        'refund_amount'   => $totalRefund,
        'affected_orders' => $affected,
        'wallet'          => $walletInfo,
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(false, 'Server error: ' . $e->getMessage());
}