
<?php
/* =========================================================
   MRS MILL@ — GLOBAL AJAX SEARCH
   File: ./ajax/global-search.php

   Searches:
   - Customers: Name / Phone
   - Orders: Order ID / Order Number
   - Products: Product Name

   Uses PDO from config/config.php
========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* -----------------------------------------
   VALIDATE REQUEST
----------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.',
        'customers' => [],
        'orders' => [],
        'products' => []
    ]);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection unavailable.',
        'customers' => [],
        'orders' => [],
        'products' => []
    ]);
    exit;
}

$query = trim($_GET['q'] ?? '');

$result = [
    'success' => true,
    'customers' => [],
    'orders' => [],
    'products' => []
];

if (mb_strlen($query) < 2) {
    echo json_encode($result);
    exit;
}

$query = mb_substr($query, 0, 100);

/* -----------------------------------------
   HELPERS
----------------------------------------- */

function searchTableColumns(PDO $pdo, string $table): array
{
    // Only allow known table names.
    $allowedTables = [
        'customers', 'customer', 'users',
        'orders', 'products', 'product'
    ];

    if (!in_array($table, $allowedTables, true)) {
        return [];
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
        $columns = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[] = $column['Field'];
        }

        return $columns;

    } catch (PDOException $e) {
        return [];
    }
}

function findSearchColumn(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }

    return null;
}

function searchAdminUrl(string $page, int $id): string
{
    $base = defined('ADMIN_URL') ? ADMIN_URL : './';

    return rtrim($base, '/') . '/' . $page . '?id=' . $id;
}

function searchRecords(
    PDO $pdo,
    string $table,
    array $searchColumns,
    string $query,
    int $limit = 5
): array {

    $columns = searchTableColumns($pdo, $table);

    if (!$columns || !$searchColumns) {
        return [];
    }

    $validSearchColumns = [];

    foreach ($searchColumns as $column) {
        if (in_array($column, $columns, true)) {
            $validSearchColumns[] = $column;
        }
    }

    if (!$validSearchColumns) {
        return [];
    }

    $conditions = [];
    $params = [];

    foreach ($validSearchColumns as $column) {
        $conditions[] = "`$column` LIKE ?";
        $params[] = '%' . $query . '%';
    }

    $idColumn = findSearchColumn($columns, [
        'id',
        'customer_id',
        'product_id',
        'order_id'
    ]);

    if (!$idColumn) {
        return [];
    }

    $sql = "SELECT * FROM `$table`
            WHERE (" . implode(' OR ', $conditions) . ")
            ORDER BY `$idColumn` DESC
            LIMIT " . (int)$limit;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        return [];
    }
}

/* -----------------------------------------
   ADMIN BASE URL
----------------------------------------- */

try {

    /* =====================================
       1. CUSTOMERS
    ===================================== */

    $customerTables = ['customers', 'customer', 'users'];

    foreach ($customerTables as $table) {

        $columns = searchTableColumns($pdo, $table);

        if (!$columns) {
            continue;
        }

        $nameColumn = findSearchColumn($columns, [
            'customer_name',
            'full_name',
            'name',
            'username'
        ]);

        $phoneColumn = findSearchColumn($columns, [
            'phone',
            'phone_number',
            'mobile',
            'mobile_number',
            'contact_number'
        ]);

        $searchColumns = array_filter([
            $nameColumn,
            $phoneColumn
        ]);

        $records = searchRecords(
            $pdo,
            $table,
            $searchColumns,
            $query,
            5
        );

        foreach ($records as $row) {

            $id = (int)($row['id'] ?? $row['customer_id'] ?? 0);

            if ($id <= 0) {
                continue;
            }

            $name = $nameColumn
                ? ($row[$nameColumn] ?? 'Customer')
                : 'Customer';

            $phone = $phoneColumn
                ? ($row[$phoneColumn] ?? '')
                : '';

            $result['customers'][] = [
                'name' => (string)$name,
                'detail' => 'Phone: ' . (string)$phone,
                'url' => searchAdminUrl('edit-customer.php', $id)
            ];
        }

        if (count($result['customers']) >= 5) {
            break;
        }
    }

    /* =====================================
       2. ORDERS
    ===================================== */

    $orderTables = ['orders'];

    foreach ($orderTables as $table) {

        $columns = searchTableColumns($pdo, $table);

        if (!$columns) {
            continue;
        }

        $orderNumberColumn = findSearchColumn($columns, [
            'order_number',
            'order_code',
            'order_no',
            'order_id'
        ]);

        $idColumn = findSearchColumn($columns, [
            'id',
            'order_id'
        ]);

        $searchColumns = array_filter([
            $orderNumberColumn,
            $idColumn
        ]);

        $records = searchRecords(
            $pdo,
            $table,
            $searchColumns,
            $query,
            5
        );

        foreach ($records as $row) {

            $id = (int)($row['id'] ?? $row['order_id'] ?? 0);

            if ($id <= 0) {
                continue;
            }

            $orderNumber = $orderNumberColumn
                ? ($row[$orderNumberColumn] ?? $id)
                : $id;

            $result['orders'][] = [
                'name' => 'Order #' . $orderNumber,
                'detail' => 'View order details',
                'url' => searchAdminUrl('order-view.php', $id)
            ];
        }

        break;
    }

    /* =====================================
       3. PRODUCTS
    ===================================== */

    $productTables = ['products', 'product'];

    foreach ($productTables as $table) {

        $columns = searchTableColumns($pdo, $table);

        if (!$columns) {
            continue;
        }

        $nameColumn = findSearchColumn($columns, [
            'product_name',
            'name',
            'title',
            'item_name'
        ]);

        if (!$nameColumn) {
            continue;
        }

        $records = searchRecords(
            $pdo,
            $table,
            [$nameColumn],
            $query,
            5
        );

        foreach ($records as $row) {

            $id = (int)($row['id'] ?? $row['product_id'] ?? 0);

            if ($id <= 0) {
                continue;
            }

            $result['products'][] = [
                'name' => (string)($row[$nameColumn] ?? 'Product'),
                'detail' => 'Edit product',
                'url' => searchAdminUrl('edit-product.php', $id)
            ];
        }

        break;
    }

    echo json_encode(
        $result,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Search failed.',
        'customers' => [],
        'orders' => [],
        'products' => []
    ]);
}
