<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$requestId = (int)($_POST['request_id'] ?? 0);
$action    = trim($_POST['action'] ?? ''); // 'approve', 'reject', 'unapprove'
$adminNote = trim($_POST['admin_note'] ?? '');

if ($requestId <= 0) jsonResponse(false, 'Invalid request ID.');
if (!in_array($action, ['approve', 'reject', 'unapprove'], true)) jsonResponse(false, 'Invalid action.');

try {
    $pdo->beginTransaction();

    // Fetch the request
    $stmt = $pdo->prepare("SELECT * FROM delivery_boy_requests WHERE id = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$requestId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        $pdo->rollBack();
        jsonResponse(false, 'Request not found.');
    }

    // ---------------------------------------------------------
    // LOGIC FOR UNAPPROVE (REVERSE)
    // ---------------------------------------------------------
    if ($action === 'unapprove') {
        if ($req['status'] !== 'approved') {
            $pdo->rollBack();
            jsonResponse(false, 'Only approved requests can be unapproved.');
        }

        // 5-Hour Lock Check
        $updatedTs = strtotime($req['updated_at']);
        if ($updatedTs && (time() - $updatedTs) > (5 * 3600)) {
            $pdo->rollBack();
            jsonResponse(false, 'Action locked. This request was approved more than 5 hours ago and cannot be changed.');
        }

        // Reverse the logic based on request type
        $customerId = (int)$req['customer_id'];
        $amount     = (float)$req['amount'];
        $containers = (int)$req['container_count'];
        $reqType    = $req['request_type'];
        $boyId      = (int)$req['delivery_boy_id'];

        $cs = $pdo->prepare("SELECT id, full_name, mobile_number, wallet_balance FROM customers WHERE id = ? LIMIT 1 FOR UPDATE");
        $cs->execute([$customerId]);
        $cust = $cs->fetch(PDO::FETCH_ASSOC);

        if (!$cust) {
            $pdo->rollBack();
            jsonResponse(false, 'Customer not found.');
        }

        $before = (float)$cust['wallet_balance'];
        $after  = $before;
        $txnType = 'credit';
        $logNote = 'Admin unapproved request #' . $requestId;

        if ($reqType === 'container_return') {
            // Reverse container returns (take back from received_containers)
            // We find the containers that were updated today/5hrs and reverse them
            $remaining = $containers;
            
            $oStmt = $pdo->prepare(
                "SELECT oc.* FROM order_containers oc
                 INNER JOIN orders o ON o.id = oc.order_id
                 WHERE o.customer_id = (SELECT customer_id FROM customers WHERE id = ?)
                   AND o.delivery_boy_id = ?
                   AND o.status <> 'cancelled'
                   AND oc.received_containers > 0
                 ORDER BY oc.id DESC FOR UPDATE"
            );
            $oStmt->execute([$customerId, $boyId]);
            $rows = $oStmt->fetchAll(PDO::FETCH_ASSOC);

            $totalReversedRefund = 0.0;

            foreach ($rows as $r) {
                if ($remaining <= 0) break;
                
                $take = min((int)$r['received_containers'], $remaining);
                if ($take <= 0) continue;

                $newReceived = (int)$r['received_containers'] - $take;
                $newStatus = ($newReceived <= 0) ? 'not_received' : 'partial';
                if ($newReceived >= (int)$r['total_containers']) $newStatus = 'received';

                $total = (int)$r['total_containers'];
                $unitAmt = $total > 0 ? ((float)$r['container_amount'] / $total) : 0;
                $totalReversedRefund += ($unitAmt * $take);

                $pdo->prepare(
                    "UPDATE order_containers SET received_containers = ?, status = ?, updated_at = NOW() WHERE id = ?"
                )->execute([$newReceived, $newStatus, $r['id']]);

                $remaining -= $take;
            }

            // Deduct the refund amount from wallet
            $amount = $totalReversedRefund;
            $after = $before - $amount;
            $txnType = 'debit';
            $logNote = 'Reversed container return (' . $containers . ' pcs) - Request #' . $requestId;

        } elseif ($reqType === 'wallet_credit') {
            // Reverse credit -> deduct
            $after = $before - $amount;
            $txnType = 'debit';
        } elseif ($reqType === 'wallet_debit') {
            // Reverse debit -> credit
            $after = $before + $amount;
            $txnType = 'credit';
        }

        // Update Wallet if needed
        if ($after != $before) {
            if ($after < 0) {
                $pdo->rollBack();
                jsonResponse(false, 'Cannot unapprove. Customer wallet balance is too low (₹' . number_format($before, 2) . ').');
            }

            $pdo->prepare("UPDATE customers SET wallet_balance = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$after, $customerId]);

            // Log Transaction
            $txnCode = 'TXN' . date('ymd') . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $log = $pdo->prepare(
                "INSERT INTO customer_wallet_transactions
                    (customer_id, customer_name, customer_mobile, txn_code, txn_type, amount, balance_before, balance_after, source, note, created_by_id, created_by_name, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'admin', ?, ?, ?, NOW())"
            );
            $log->execute([
                $customerId, $cust['full_name'], $cust['mobile_number'], $txnCode, $txnType, $amount, $before, $after, $logNote, $_SESSION['admin_id'], $_SESSION['admin_name'] ?? 'Admin'
            ]);
        }

        // Update Request Status back to Pending
        $upd = $pdo->prepare("UPDATE delivery_boy_requests SET status = 'pending', admin_note = ?, updated_at = NOW() WHERE id = ?");
        $upd->execute(['Unapproved by Admin. ' . $adminNote, $requestId]);

        $pdo->commit();
        jsonResponse(true, 'Request unapproved and data reversed.');
        exit;
    }

    // ---------------------------------------------------------
    // LOGIC FOR APPROVE / REJECT
    // ---------------------------------------------------------
    if ($req['status'] !== 'pending') {
        $pdo->rollBack();
        jsonResponse(false, 'This request has already been processed.');
    }

    $newStatus = $action === 'approve' ? 'approved' : 'rejected';

    // 1. Update Request Status
    $upd = $pdo->prepare("UPDATE delivery_boy_requests SET status = ?, admin_note = ?, updated_at = NOW() WHERE id = ?");
    $upd->execute([$newStatus, $adminNote !== '' ? $adminNote : null, $requestId]);

    // 2. If Approved, Process the logic
    if ($action === 'approve') {
        $customerId = (int)$req['customer_id'];
        $amount     = (float)$req['amount'];
        $containers = (int)$req['container_count'];
        $reqType    = $req['request_type'];
        $boyId      = (int)$req['delivery_boy_id'];

        // Get current customer wallet
        $cs = $pdo->prepare("SELECT id, full_name, mobile_number, wallet_balance FROM customers WHERE id = ? LIMIT 1 FOR UPDATE");
        $cs->execute([$customerId]);
        $cust = $cs->fetch(PDO::FETCH_ASSOC);

        if (!$cust) {
            $pdo->rollBack();
            jsonResponse(false, 'Customer not found.');
        }

        $before = (float)$cust['wallet_balance'];
        $after  = $before;

        if ($reqType === 'container_return') {
            $remaining = $containers;
            $totalRefund = 0.0;
            
            $oStmt = $pdo->prepare(
                "SELECT oc.* FROM order_containers oc
                 INNER JOIN orders o ON o.id = oc.order_id
                 WHERE o.customer_id = (SELECT customer_id FROM customers WHERE id = ?)
                   AND o.delivery_boy_id = ?
                   AND o.status <> 'cancelled'
                   AND (oc.total_containers - oc.received_containers) > 0
                 ORDER BY oc.id ASC FOR UPDATE"
            );
            $oStmt->execute([$customerId, $boyId]);
            $rows = $oStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $r) {
                if ($remaining <= 0) break;
                $rowPending = (int)$r['total_containers'] - (int)$r['received_containers'];
                if ($rowPending <= 0) continue;

                $take = min($rowPending, $remaining);
                $total = (int)$r['total_containers'];
                $unitAmt = $total > 0 ? ((float)$r['container_amount'] / $total) : 0;
                $refund = round($unitAmt * $take, 2);
                $totalRefund += $refund;
                $newReceived = (int)$r['received_containers'] + $take;
                $newStatus = ($newReceived >= $total) ? 'received' : 'partial';

                $pdo->prepare(
                    "UPDATE order_containers SET received_containers = ?, status = ?, received_at = NOW(), received_by_id = ?, received_by_name = 'Admin Approved' WHERE id = ?"
                )->execute([$newReceived, $newStatus, $_SESSION['admin_id'], $r['id']]);

                $remaining -= $take;
            }

            $amount = $totalRefund;
            $after  = $before + $amount;
            
        } elseif ($reqType === 'wallet_credit') {
            $after = $before + $amount;
        } elseif ($reqType === 'wallet_debit') {
            if ($amount > $before) {
                $pdo->rollBack();
                jsonResponse(false, 'Insufficient wallet balance for this debit.');
            }
            $after = $before - $amount;
        }

        // Update Wallet Balance
        if ($after != $before) {
            $pdo->prepare("UPDATE customers SET wallet_balance = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$after, $customerId]);

            // Log Transaction
            $txnType = ($after > $before) ? 'credit' : 'debit';
            $txnCode = 'TXN' . date('ymd') . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $note = 'Admin approved request #' . $requestId;
            if ($reqType === 'container_return') $note = 'Container return (' . $containers . ' pcs) via request #' . $requestId;

            $log = $pdo->prepare(
                "INSERT INTO customer_wallet_transactions
                    (customer_id, customer_name, customer_mobile, txn_code, txn_type, amount, balance_before, balance_after, source, note, created_by_id, created_by_name, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'admin', ?, ?, ?, NOW())"
            );
            $log->execute([
                $customerId, $cust['full_name'], $cust['mobile_number'], $txnCode, $txnType, $amount, $before, $after, $note, $_SESSION['admin_id'], $_SESSION['admin_name'] ?? 'Admin'
            ]);
        }
    }

    $pdo->commit();
    jsonResponse(true, 'Request ' . $newStatus . ' successfully.');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(false, 'Server error: ' . $e->getMessage());
}