<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

$groupKey = trim($_POST['group_key'] ?? '');
$customerId = (int)($_POST['customer_id'] ?? 0);
$received = (int)($_POST['received_containers'] ?? 0);
$note     = trim($_POST['note'] ?? '');

if ($groupKey === '' && $customerId <= 0) jsonResponse(false, 'Invalid request.');
if ($received <= 0) jsonResponse(false, 'Enter a valid count.');

try {
    /* Ensure required schema pieces */
    try {
        $pdo->exec("ALTER TABLE customers ADD COLUMN wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER division_charge");
    } catch (PDOException $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS customer_wallet_transactions (
            id INT(11) NOT NULL AUTO_INCREMENT,
            customer_id INT(11) NOT NULL,
            customer_name VARCHAR(150) NOT NULL,
            customer_mobile VARCHAR(30) NOT NULL,
            txn_code VARCHAR(30) NOT NULL,
            txn_type ENUM('credit','debit') NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            balance_before DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            balance_after DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            source ENUM('admin','staff','system','order','refund') NOT NULL DEFAULT 'admin',
            note VARCHAR(255) DEFAULT NULL,
            created_by_id INT(11) DEFAULT NULL,
            created_by_name VARCHAR(150) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            PRIMARY KEY (id),
            UNIQUE KEY txn_code (txn_code),
            KEY customer_id (customer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    } catch (PDOException $e) {}

    $pdo->beginTransaction();

    /* Build query to select all pending container rows for this customer */
    if ($customerId > 0) {
        $stmt = $pdo->prepare(
            "SELECT * FROM order_containers
             WHERE customer_id = ?
               AND (total_containers - received_containers) > 0
             ORDER BY id ASC
             FOR UPDATE"
        );
        $stmt->execute([$customerId]);
    } else {
        /* Fallback: group by mobile if no customer_id */
        $mobile = '';
        if (strpos($groupKey, 'mob_') === 0) {
            $mobile = substr($groupKey, 4);
        }

        if ($mobile === '') {
            $pdo->rollBack();
            jsonResponse(false, 'Cannot resolve customer.');
        }

        $stmt = $pdo->prepare(
            "SELECT oc.* FROM order_containers oc
             LEFT JOIN orders o ON o.id = oc.order_id
             WHERE o.customer_mobile = ?
               AND (oc.total_containers - oc.received_containers) > 0
             ORDER BY oc.id ASC
             FOR UPDATE"
        );
        $stmt->execute([$mobile]);
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        $pdo->rollBack();
        jsonResponse(false, 'No pending containers for this customer.');
    }

    /* Compute total pending */
    $totalPending = 0;
    foreach ($rows as $r) {
        $totalPending += ((int)$r['total_containers'] - (int)$r['received_containers']);
    }

    if ($received > $totalPending) {
        $pdo->rollBack();
        jsonResponse(false, 'Cannot receive more than ' . $totalPending . ' pending.');
    }

    /* Distribute received count across orders (oldest first) */
    $remaining = $received;
    $totalRefund = 0;
    $affected = [];

    foreach ($rows as $r) {
        if ($remaining <= 0) break;

        $rowPending = (int)$r['total_containers'] - (int)$r['received_containers'];
        if ($rowPending <= 0) continue;

        $take = min($rowPending, $remaining);

        /* Per-unit value */
        $total = (int)$r['total_containers'];
        $unitAmt = $total > 0 ? ((float)$r['container_amount'] / $total) : 0;

        $refund = round($unitAmt * $take, 2);
        $totalRefund += $refund;

        $newReceived = (int)$r['received_containers'] + $take;
        $newStatus = ($newReceived >= $total) ? 'received' : 'partial';

        $upd = $pdo->prepare(
            "UPDATE order_containers
             SET received_containers = ?,
                 status = ?,
                 received_at = NOW(),
                 received_by_id = ?,
                 received_by_name = ?,
                 note = ?,
                 updated_at = NOW()
             WHERE id = ?"
        );
        $upd->execute([
            $newReceived,
            $newStatus,
            (int)$_SESSION['admin_id'],
            $_SESSION['admin_name'] ?? 'Admin',
            $note !== '' ? $note : $r['note'],
            (int)$r['id'],
        ]);

        $affected[] = [
            'order_id' => (int)$r['order_id'],
            'received' => $take,
            'refund'   => $refund,
        ];

        $remaining -= $take;
    }

    /* Determine customer id */
    $finalCustomerId = $customerId;
    if ($finalCustomerId <= 0) {
        foreach ($rows as $r) {
            if (!empty($r['customer_id'])) { $finalCustomerId = (int)$r['customer_id']; break; }
        }
    }

    /* Credit the customer wallet */
    $walletInfo = null;
    if ($totalRefund > 0 && $finalCustomerId > 0) {

        $cStmt = $pdo->prepare(
            "SELECT id, full_name, mobile_number, wallet_balance
             FROM customers WHERE id = ? LIMIT 1 FOR UPDATE"
        );
        $cStmt->execute([$finalCustomerId]);
        $cust = $cStmt->fetch(PDO::FETCH_ASSOC);

        if ($cust) {
            $before = (float)$cust['wallet_balance'];
            $after  = $before + $totalRefund;

            $updC = $pdo->prepare(
                "UPDATE customers
                 SET wallet_balance = ?, updated_at = NOW()
                 WHERE id = ?"
            );
            $updC->execute([$after, $finalCustomerId]);

            try {
                $txnCode = 'TXN' . date('ymd') . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);

                $log = $pdo->prepare(
                    "INSERT INTO customer_wallet_transactions
                        (customer_id, customer_name, customer_mobile, txn_code,
                         txn_type, amount, balance_before, balance_after,
                         source, note, created_by_id, created_by_name, created_at)
                     VALUES (?, ?, ?, ?, 'credit', ?, ?, ?, 'refund', ?, ?, ?, NOW())"
                );
                $log->execute([
                    $finalCustomerId,
                    $cust['full_name'],
                    $cust['mobile_number'],
                    $txnCode,
                    $totalRefund,
                    $before,
                    $after,
                    'Container return (' . $received . ' pcs)',
                    (int)$_SESSION['admin_id'],
                    $_SESSION['admin_name'] ?? 'Admin'
                ]);
            } catch (PDOException $e) { /* ignore */ }

            $walletInfo = [
                'balance_before' => $before,
                'balance_after'  => $after,
                'amount'         => $totalRefund,
            ];
        }
    }

    $pdo->commit();

    $msg = $received . ' container' . ($received === 1 ? '' : 's') .
           ' received · ₹' . number_format($totalRefund, 2) .
           ' refunded to ' . ($walletInfo ? 'wallet' : 'customer') . '.';

    jsonResponse(true, $msg, [
        'received'        => $received,
        'refund_amount'   => $totalRefund,
        'affected_orders' => $affected,
        'wallet'          => $walletInfo,
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(false, 'Server error.');
}