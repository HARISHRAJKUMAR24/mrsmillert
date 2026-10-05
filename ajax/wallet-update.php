<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

/* ---------- METHOD + AUTH ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

/* ---------- INPUT ---------- */
$customerId = (int)($_POST['customer_id'] ?? 0);
$txnType    = trim($_POST['txn_type'] ?? '');
$amount     = (float)($_POST['amount'] ?? 0);
$note       = trim($_POST['note'] ?? '');

if ($customerId <= 0) {
    jsonResponse(false, 'Invalid customer.');
}

if (!in_array($txnType, ['credit', 'debit'], true)) {
    jsonResponse(false, 'Invalid transaction type.');
}

if ($amount <= 0) {
    jsonResponse(false, 'Amount must be greater than 0.');
}

$amount = round($amount, 2);

/* ---------- PROCESS ---------- */
try {
    /* -------- Safety: ensure tables/columns exist -------- */
    try {
        $pdo->exec(
            "ALTER TABLE customers
             ADD COLUMN wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00
             AFTER division_charge"
        );
    } catch (PDOException $e) { /* already exists */ }

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS customer_wallet_transactions (
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
                KEY customer_id (customer_id),
                KEY txn_type (txn_type),
                KEY created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    } catch (PDOException $e) { /* ignore */ }

    /* -------- Lock customer row -------- */
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT id, full_name, mobile_number, wallet_balance
         FROM customers
         WHERE id = ? LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute([$customerId]);
    $cust = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cust) {
        $pdo->rollBack();
        jsonResponse(false, 'Customer not found.');
    }

    $balanceBefore = (float)$cust['wallet_balance'];

    /* -------- Prevent overdraft -------- */
    if ($txnType === 'debit' && $amount > $balanceBefore) {
        $pdo->rollBack();
        jsonResponse(
            false,
            'Insufficient balance. Available: ₹' . number_format($balanceBefore, 2)
        );
    }

    $balanceAfter = $txnType === 'credit'
        ? $balanceBefore + $amount
        : $balanceBefore - $amount;

    /* -------- Update customer balance -------- */
    $upd = $pdo->prepare(
        "UPDATE customers
         SET wallet_balance = ?, updated_at = NOW()
         WHERE id = ?"
    );
    $upd->execute([$balanceAfter, $customerId]);

    /* -------- Generate txn code -------- */
    $txnCode = 'TXN' . date('ymd') . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);

    /* -------- Actor info -------- */
    $actorName = $_SESSION['admin_name'] ?? 'System';
    $actorId   = (int)($_SESSION['admin_id'] ?? 0);
    $source    = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin')
        ? 'admin'
        : 'staff';

    /* -------- Insert transaction row -------- */
    $ins = $pdo->prepare(
        "INSERT INTO customer_wallet_transactions
            (customer_id, customer_name, customer_mobile, txn_code,
             txn_type, amount, balance_before, balance_after,
             source, note, created_by_id, created_by_name, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $ins->execute([
        $customerId,
        $cust['full_name'],
        $cust['mobile_number'],
        $txnCode,
        $txnType,
        $amount,
        $balanceBefore,
        $balanceAfter,
        $source,
        $note !== '' ? $note : null,
        $actorId,
        $actorName
    ]);

    $pdo->commit();

    /* -------- Response -------- */
    $msg = $txnType === 'credit'
        ? '₹' . number_format($amount, 2) . ' added. New balance: ₹' . number_format($balanceAfter, 2)
        : '₹' . number_format($amount, 2) . ' deducted. New balance: ₹' . number_format($balanceAfter, 2);

    jsonResponse(true, $msg, [
        'txn_code'       => $txnCode,
        'balance_before' => $balanceBefore,
        'balance_after'  => $balanceAfter,
        'amount'         => $amount,
        'txn_type'       => $txnType,
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(false, 'Server error.');
}