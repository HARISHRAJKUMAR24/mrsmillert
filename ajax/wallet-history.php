<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

/* ---------- AUTH ---------- */
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

/* ---------- INPUT ---------- */
$customerId = (int)($_GET['customer_id'] ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = (int)($_GET['per_page'] ?? 10);
$filter     = trim($_GET['filter'] ?? ''); // '', 'credit', 'debit'

if ($customerId <= 0) {
    jsonResponse(false, 'Invalid customer.');
}

if ($perPage < 1)    $perPage = 10;
if ($perPage > 200)  $perPage = 200;

$allowedFilters = ['', 'credit', 'debit'];
if (!in_array($filter, $allowedFilters, true)) $filter = '';

$offset = ($page - 1) * $perPage;

try {
    /* Ensure table exists */
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

    /* ---------- Counts ---------- */
    $where = "customer_id = ?";
    $params = [$customerId];
    if ($filter !== '') {
        $where .= " AND txn_type = ?";
        $params[] = $filter;
    }

    $cStmt = $pdo->prepare("SELECT COUNT(*) FROM customer_wallet_transactions WHERE $where");
    $cStmt->execute($params);
    $totalCount = (int)$cStmt->fetchColumn();

    /* ---------- Totals (always for the whole customer, not the current filter) ---------- */
    $tStmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN txn_type = 'credit' THEN amount ELSE 0 END), 0) AS total_credit,
            COALESCE(SUM(CASE WHEN txn_type = 'debit'  THEN amount ELSE 0 END), 0) AS total_debit,
            COUNT(*) AS total_entries
         FROM customer_wallet_transactions
         WHERE customer_id = ?"
    );
    $tStmt->execute([$customerId]);
    $totals = $tStmt->fetch(PDO::FETCH_ASSOC);

    /* ---------- Page rows ---------- */
    $sql = "SELECT id, txn_code, txn_type, amount,
                   balance_before, balance_after,
                   source, note, created_by_name, created_at
            FROM customer_wallet_transactions
            WHERE $where
            ORDER BY id DESC
            LIMIT $perPage OFFSET $offset";
    $rStmt = $pdo->prepare($sql);
    $rStmt->execute($params);
    $rows = $rStmt->fetchAll(PDO::FETCH_ASSOC);

    $out = array_map(function ($r) {
        return [
            'id'              => (int)$r['id'],
            'txn_code'        => $r['txn_code'],
            'txn_type'        => $r['txn_type'],
            'amount'          => (float)$r['amount'],
            'balance_before'  => (float)$r['balance_before'],
            'balance_after'   => (float)$r['balance_after'],
            'source'          => $r['source'],
            'note'            => $r['note'],
            'created_by_name' => $r['created_by_name'],
            'created_at'      => $r['created_at'],
        ];
    }, $rows);

    $totalPages = max(1, (int)ceil($totalCount / $perPage));

    jsonResponse(true, 'OK', [
        'transactions' => $out,
        'total_credit' => round((float)$totals['total_credit'], 2),
        'total_debit'  => round((float)$totals['total_debit'], 2),
        'total_entries'=> (int)$totals['total_entries'],

        /* pagination meta */
        'page'         => $page,
        'per_page'     => $perPage,
        'total_count'  => $totalCount,
        'total_pages'  => $totalPages,
        'filter'       => $filter,
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load history.');
}