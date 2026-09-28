<?php
require_once './config/config.php';
require_once './config/function.php';

/* ---------------- AUTH CHECK ---------------- */
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    header('Location: login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: orders.php');
    exit;
}

$order    = null;
$products = [];
$custOrders              = [];
$custTotalAmount         = 0;
$custTotalOrderCount     = 0;
$custTotalPaidOrders     = 0;
$custTotalUnpaidOrders   = 0;

/* ---------- 1. Fetch order ---------- */
try {
    $stmt = $pdo->prepare(
        "SELECT o.*, b.full_name AS boy_name, b.delivery_code AS boy_code
         FROM orders o
         LEFT JOIN delivery_boys b ON b.id = o.delivery_boy_id
         WHERE o.id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    $order = $stmt->fetch();

    if (!$order) {
        /* Try without delivery_boys join in case table missing */
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $order = $stmt->fetch();

        if ($order) {
            $order['boy_name'] = '—';
            $order['boy_code'] = '';
        }
    }
} catch (PDOException $e) {
    /* Last resort — try bare SELECT */
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if ($order) {
            $order['boy_name'] = '—';
            $order['boy_code'] = '';
        }
    } catch (PDOException $e2) {
        $order = null;
    }
}

if (!$order) {
    header('Location: orders.php');
    exit;
}

/* ---------- 2. Decode products ---------- */
$products = json_decode($order['products_json'] ?? '[]', true);
if (!is_array($products)) $products = [];

/* ---------- 3. Customer summary ---------- */
$customerMobile = $order['customer_mobile'] ?? '';

try {
    $custOrdersStmt = $pdo->prepare(
        "SELECT id, order_code, customer_name, created_at,
                total_amount, payment_status, status
         FROM orders
         WHERE customer_mobile = ?
         ORDER BY id DESC"
    );
    $custOrdersStmt->execute([$customerMobile]);
    $custOrders = $custOrdersStmt->fetchAll();
} catch (PDOException $e) {
    $custOrders = [];
}

/* ---------- 4. Compute summary ---------- */
foreach ($custOrders as $co) {
    $custTotalAmount += (float)($co['total_amount'] ?? 0);
    $custTotalOrderCount++;

    if (($co['payment_status'] ?? '') === 'paid') {
        $custTotalPaidOrders++;
    } else {
        $custTotalUnpaidOrders++;
    }
}

/* ---------- 5. Settings ---------- */
$settings = getSettings($pdo);
$siteName = $settings['username'] ?? 'Mrs Mill@';

/* ---------- 6. Back URL based on order type ---------- */
$backUrl  = 'orders.php';
$backText = 'Back to Orders';
if (!empty($order['delivery_mode']) && $order['delivery_mode'] === 'pickup') {
    $backUrl  = 'pickup-orders.php';
    $backText = 'Back to Pickup Orders';
}

function fmtNoDec($n)
{
    return number_format((float)$n, 0, '.', '');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

    <style>
        .ov-page { padding: 30px 32px 40px; }

        .ov-header {
            display: flex; align-items: center; justify-content: space-between;
            gap: 20px; margin-bottom: 25px; flex-wrap: wrap;
        }

        .ov-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 26px; font-weight: 700;
            color: #302923; margin: 0 0 5px;
        }

        .ov-title p { margin: 0; color: #817a71; font-size: 13px; }

        .ov-back {
            display: inline-flex; align-items: center; gap: 7px;
            border: 1.5px solid #e4ddd3; background: #fff;
            color: #6f675f; padding: 10px 16px;
            border-radius: 11px; font-size: 11.5px;
            font-weight: 700; text-decoration: none;
            transition: .2s ease;
        }

        .ov-back:hover { background: #faf7f0; color: #302923; }

        .ov-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 20px;
            align-items: start;
        }

        .ov-card {
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 20px;
            padding: 22px;
            margin-bottom: 18px;
        }

        .ov-card:last-child { margin-bottom: 0; }

        .ov-card-title {
            display: flex; align-items: center;
            gap: 10px; margin-bottom: 14px;
        }

        .ov-card-title i {
            width: 34px; height: 34px;
            border-radius: 10px;
            background: #fbe8e9; color: #b51f2c;
            display: flex; align-items: center;
            justify-content: center; font-size: 15px;
            flex-shrink: 0;
        }

        .ov-card-title h3 {
            margin: 0;
            font-family: "Playfair Display", serif;
            font-size: 15px; font-weight: 700;
            color: #302923;
        }

        .ov-card-title span {
            display: block; font-size: 10px;
            color: #948c82; margin-top: 2px;
        }

        .ov-cust {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .ov-cust-item {
            background: #fdfaf4;
            border: 1px solid #f0ebe4;
            border-radius: 11px;
            padding: 10px 14px;
        }

        .ov-cust-item span {
            font-size: 9.5px; font-weight: 800;
            color: #948c82; letter-spacing: .5px;
            text-transform: uppercase;
            display: block; margin-bottom: 3px;
        }

        .ov-cust-item strong {
            font-size: 12.5px; font-weight: 700;
            color: #302923; display: block;
            word-break: break-word;
        }

        .ov-products { display: flex; flex-direction: column; gap: 8px; }

        .ov-product {
            display: flex; align-items: center;
            gap: 12px; padding: 10px 12px;
            background: #fffdf9;
            border: 1px solid #f0ebe4;
            border-radius: 11px;
        }

        .ov-product-thumb {
            width: 46px; height: 46px;
            border-radius: 10px; background: #f7efe3;
            display: flex; align-items: center;
            justify-content: center;
            flex-shrink: 0; overflow: hidden;
            font-size: 20px;
        }

        .ov-product-thumb img { width: 100%; height: 100%; object-fit: cover; }

        .ov-product-info { flex: 1; min-width: 0; }

        .ov-product-name {
            font-size: 12.5px; font-weight: 700;
            color: #302923; margin-bottom: 2px;
        }

        .ov-product-meta {
            font-size: 10.5px; color: #948c82;
            font-weight: 600;
        }

        .ov-product-meta strong { color: #b51f2c; font-weight: 800; }

        .ov-product-total {
            font-family: "Playfair Display", serif;
            font-size: 14px; font-weight: 700;
            color: #b51f2c; white-space: nowrap;
        }

        .ov-totals {
            background: #fdfaf4;
            border: 1px solid #f0ebe4;
            border-radius: 12px;
            padding: 14px 16px;
            margin-top: 12px;
        }

        .ov-total-row {
            display: flex; justify-content: space-between;
            align-items: center; font-size: 12px;
            color: #6f675f; margin-bottom: 6px;
        }

        .ov-total-row strong { color: #302923; font-weight: 700; }

        .ov-total-row.grand {
            margin-top: 10px; padding-top: 10px;
            border-top: 1.5px dashed #e4ddd3;
            font-size: 14px;
        }

        .ov-total-row.grand strong {
            font-family: "Playfair Display", serif;
            font-size: 19px; color: #b51f2c;
        }

        /* CUSTOMER SUMMARY */
        .ov-cust-summary {
            background: linear-gradient(135deg, #fff9f0 0%, #fdf4e5 100%);
            border: 1.5px solid #e8d5a8;
            border-radius: 20px;
            padding: 20px 22px;
            margin-bottom: 18px;
        }

        .ov-cust-summary-head {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 16px; flex-wrap: wrap;
        }

        .ov-cust-summary-head .icon {
            width: 40px; height: 40px;
            border-radius: 11px;
            background: linear-gradient(135deg, #b8893c 0%, #8f6620 100%);
            color: #fff;
            display: flex; align-items: center;
            justify-content: center; font-size: 18px;
            flex-shrink: 0;
            box-shadow: 0 6px 14px rgba(184,137,60,.3);
        }

        .ov-cust-summary-head h3 {
            margin: 0;
            font-family: "Playfair Display", serif;
            font-size: 16px; font-weight: 700;
            color: #302923;
        }

        .ov-cust-summary-head p {
            margin: 2px 0 0;
            font-size: 11px; color: #8a6a1e;
            font-weight: 600;
        }

        .ov-cust-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 14px;
        }

        .ov-cust-summary-item {
            background: #fff;
            border: 1px solid #f0e2c0;
            border-radius: 11px;
            padding: 10px 12px;
            text-align: center;
        }

        .ov-cust-summary-item span {
            font-size: 9px; font-weight: 800;
            color: #948c82; letter-spacing: .4px;
            text-transform: uppercase; display: block;
            margin-bottom: 4px;
        }

        .ov-cust-summary-item strong {
            font-family: "Playfair Display", serif;
            font-size: 20px; color: #b8893c;
        }

        .ov-cust-summary-item.green strong { color: #2e7d32; }
        .ov-cust-summary-item.red strong   { color: #b51f2c; }
        .ov-cust-summary-item.blue strong  { color: #1565c0; }
        .ov-cust-summary-item.gold strong  { color: #b8893c; }

        /* ORDER HISTORY */
        .ov-cust-orders {
            background: #fff;
            border: 1px solid #f0e2c0;
            border-radius: 12px;
            overflow: hidden;
        }

        .ov-cust-orders-head {
            padding: 10px 14px;
            background: #fdf5e8;
            font-size: 10.5px; font-weight: 800;
            color: #8a6a1e;
            letter-spacing: .5px;
            text-transform: uppercase;
            border-bottom: 1px solid #f0e2c0;
        }

        .ov-cust-order-row {
            display: grid;
            grid-template-columns: 1.4fr repeat(3, 1fr);
            gap: 10px;
            padding: 12px 14px;
            border-bottom: 1px solid #f5efe6;
            align-items: center;
            font-size: 11.5px;
            transition: background .15s ease;
            text-decoration: none;
            color: inherit;
        }

        .ov-cust-order-row:last-child { border-bottom: 0; }
        .ov-cust-order-row:hover { background: #fffaf0; }

        .ov-cust-order-row.current {
            background: #fff7e7;
            border-left: 3px solid #b8893c;
        }

        .ov-cust-order-code {
            font-weight: 800;
            color: #b51f2c;
            font-size: 12.5px;
        }

        .ov-cust-order-code small {
            display: block;
            font-size: 10px;
            color: #948c82;
            font-weight: 600;
            margin-top: 2px;
        }

        .ov-cust-order-stat { text-align: center; }

        .ov-cust-order-stat span {
            display: block;
            font-size: 9px;
            color: #948c82;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .3px;
            margin-bottom: 2px;
        }

        .ov-cust-order-stat strong {
            font-size: 13px;
            color: #302923;
            font-weight: 800;
        }

        .ov-cust-order-stat.green strong { color: #2e7d32; }

        .ov-cust-order-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: .4px;
            text-transform: uppercase;
        }

        .ov-cust-order-badge.paid   { background: #e8f6ea; color: #1b5e20; }
        .ov-cust-order-badge.unpaid { background: #fff4d6; color: #8a6a1e; }
        .ov-cust-order-badge.failed { background: #fdeaea; color: #b51f2c; }

        /* PAGINATION */
        .ov-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-top: 1px solid #f0e2c0;
            background: #fffdf8;
            flex-wrap: wrap;
        }

        .ov-pagination-info {
            font-size: 11px;
            color: #8a6a1e;
        }

        .ov-pagination-info strong {
            color: #6f5a3f;
            font-weight: 700;
        }

        .ov-pagination-controls {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .ov-pagination-controls button {
            min-width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid #f0e2c0;
            background: #fff;
            color: #8a6a1e;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: .15s ease;
            padding: 0 8px;
            font-family: inherit;
        }

        .ov-pagination-controls button:hover:not(:disabled):not(.active) {
            background: #fff7e7;
            border-color: #e8d5a8;
            color: #6f5a3f;
        }

        .ov-pagination-controls button.active {
            background: #b8893c;
            border-color: #b8893c;
            color: #fff;
            cursor: default;
        }

        .ov-pagination-controls button:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .ov-pagination-controls .page-ellipsis {
            min-width: 24px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #c9b78f;
            font-size: 12px;
            font-weight: 700;
            user-select: none;
        }

        .ov-pagination-controls i { font-size: 11px; }

        /* Order status */
        .ov-status-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 6px;
        }

        .ov-status-field label {
            display: block;
            font-size: 10.5px; font-weight: 800;
            color: #948c82; letter-spacing: .5px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .ov-status-field select {
            width: 100%; height: 42px;
            border: 1.5px solid #ece5da;
            background: #fff; border-radius: 11px;
            padding: 0 32px 0 12px;
            font-family: "DM Sans", sans-serif;
            font-size: 12px; font-weight: 700;
            color: #302923; outline: none;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23817a71' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 13px;
            transition: .2s ease;
        }

        .ov-save-bar {
            position: sticky;
            bottom: 0;
            margin-top: 20px;
           
            padding: 16px 0 4px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            z-index: 50;
        }

        .ov-save-btn {
            height: 50px;
            min-width: 260px;
            border: none;
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
            border-radius: 12px;
            font-family: "DM Sans", sans-serif;
            font-size: 13px; font-weight: 700;
            letter-spacing: .3px;
            cursor: pointer;
            transition: .2s ease;
            box-shadow: 0 12px 28px rgba(181,31,44,.28);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 0 26px;
        }

        .ov-save-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 16px 34px rgba(181,31,44,.36);
        }

        .ov-save-btn:disabled {
            opacity: .7;
            cursor: not-allowed;
        }

        #mmToast {
            position: fixed; bottom: 26px; left: 50%;
            transform: translateX(-50%) translateY(20px);
            padding: 12px 22px; border-radius: 12px;
            font-size: 12px; font-weight: 700;
            font-family: "DM Sans", sans-serif;
            color: #fff; background: #2e7d32;
            box-shadow: 0 15px 40px rgba(0,0,0,.25);
            z-index: 99999; opacity: 0;
            pointer-events: none;
            transition: opacity .25s ease, transform .25s ease;
            max-width: 90vw; text-align: center;
        }

        #mmToast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        #mmToast.error { background: #b51f2c; }

        @media (max-width: 1024px) {
            .ov-layout { grid-template-columns: 1fr; }
            .ov-cust-summary-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .ov-page { padding: 20px 15px 30px; }
            .ov-header { flex-direction: column; align-items: flex-start; }
            .ov-cust, .ov-status-row { grid-template-columns: 1fr; }
            .ov-card { padding: 17px; border-radius: 16px; }
            .ov-cust-summary-grid { grid-template-columns: repeat(2, 1fr); }

            .ov-cust-order-row {
                grid-template-columns: 1fr 1fr 1fr;
                gap: 8px;
            }

            .ov-save-bar { justify-content: stretch; }
            .ov-save-btn { width: 100%; min-width: 0; }

            .ov-pagination { flex-direction: column; }
            .ov-pagination-info { text-align: center; }
            .ov-pagination-controls { justify-content: center; flex-wrap: wrap; }
        }

        @media (max-width: 480px) {
            .ov-cust-summary-grid { grid-template-columns: 1fr 1fr; }
            .ov-save-btn { height: 46px; font-size: 12.5px; }
            .ov-cust-order-row { grid-template-columns: 1fr 1fr; }
        }
    </style>

</head>

<body>

    <?php include './templates/sidebar.php'; ?>

    <main class="main">

        <header class="topbar">
            <button class="mobile-menu" onclick="toggleSidebar()" aria-label="Open menu">
                <i class="bi bi-list"></i>
            </button>
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" placeholder="Search orders, products...">
            </div>
            <div class="top-right">
                <button class="notification">
                    <i class="bi bi-bell"></i>
                    <span class="notification-dot"></span>
                </button>
                <div class="admin-profile">
                    <div class="admin-avatar">A</div>
                    <div>
                        <div class="admin-name">Admin</div>
                        <div class="admin-role">Store Manager</div>
                    </div>
                </div>
            </div>
        </header>


        <div class="ov-page">

            <div class="ov-header">

                <div class="ov-title">
                    <h1>Order #<?= htmlspecialchars($order['order_code']) ?></h1>
                    <p>Placed <?= date('d M Y · h:i A', strtotime($order['created_at'])) ?></p>
                </div>

                <a href="<?= $backUrl ?>" class="ov-back">
                    <i class="bi bi-arrow-left"></i>
                    <?= $backText ?>
                </a>

            </div>


            <div class="ov-layout">

                <!-- LEFT -->
                <div>

                    <!-- CUSTOMER SUMMARY -->
                    <div class="ov-cust-summary">

                        <div class="ov-cust-summary-head">
                            <div class="icon">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div>
                                <h3><?= htmlspecialchars($order['customer_name']) ?> · <?= htmlspecialchars($customerMobile) ?></h3>
                                <p>Total <?= $custTotalOrderCount ?> order<?= $custTotalOrderCount !== 1 ? 's' : '' ?> from this customer</p>
                            </div>
                        </div>

                        <div class="ov-cust-summary-grid">
                            <div class="ov-cust-summary-item gold">
                                <span>Total Orders</span>
                                <strong><?= $custTotalOrderCount ?></strong>
                            </div>
                            <div class="ov-cust-summary-item green">
                                <span>Paid Orders</span>
                                <strong><?= $custTotalPaidOrders ?></strong>
                            </div>
                            <div class="ov-cust-summary-item red">
                                <span>Unpaid Orders</span>
                                <strong><?= $custTotalUnpaidOrders ?></strong>
                            </div>
                            <div class="ov-cust-summary-item blue">
                                <span>Total Value ₹</span>
                                <strong>₹<?= fmtNoDec($custTotalAmount) ?></strong>
                            </div>
                        </div>

                        <div class="ov-cust-orders">

                            <div class="ov-cust-orders-head">
                                <i class="bi bi-list-ul"></i>
                                Order History
                            </div>

                            <div id="orderHistoryList">
                                <?php foreach ($custOrders as $co):
                                    $isCurrent = ((int)$co['id'] === (int)$order['id']);
                                    $badgeCls  = 'ov-cust-order-badge ' . strtolower($co['payment_status']);
                                ?>
                                    <a href="order-view.php?id=<?= (int)$co['id'] ?>"
                                       class="ov-cust-order-row <?= $isCurrent ? 'current' : '' ?>"
                                       data-order-row>

                                        <div class="ov-cust-order-code">
                                            #<?= htmlspecialchars($co['order_code']) ?>
                                            <?php if ($isCurrent): ?>
                                                <span style="font-size:9px;color:#b8893c;">· Current</span>
                                            <?php endif; ?>
                                            <small>
                                                <?= date('d M Y · h:i A', strtotime($co['created_at'])) ?>
                                            </small>
                                        </div>

                                        <div class="ov-cust-order-stat green">
                                            <span>Payment</span>
                                            <strong>
                                                <span class="<?= $badgeCls ?>">
                                                    <?= ucfirst($co['payment_status']) ?>
                                                </span>
                                            </strong>
                                        </div>

                                        <div class="ov-cust-order-stat">
                                            <span>Status</span>
                                            <strong><?= ucfirst($co['status']) ?></strong>
                                        </div>

                                        <div class="ov-cust-order-stat">
                                            <span>Total ₹</span>
                                            <strong>₹<?= fmtNoDec($co['total_amount']) ?></strong>
                                        </div>

                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <!-- PAGINATION -->
                            <div class="ov-pagination" id="historyPagination" style="display:none;">
                                <div class="ov-pagination-info" id="historyPaginationInfo">
                                    Showing <strong>0</strong>–<strong>0</strong> of <strong>0</strong>
                                </div>
                                <div class="ov-pagination-controls" id="historyPaginationControls"></div>
                            </div>

                        </div>

                    </div>


                    <!-- CUSTOMER -->
                    <div class="ov-card">
                        <div class="ov-card-title">
                            <i class="bi bi-person-badge"></i>
                            <div>
                                <h3>Customer Details</h3>
                                <span>Who placed this order</span>
                            </div>
                        </div>

                        <div class="ov-cust">
                            <div class="ov-cust-item">
                                <span>Name</span>
                                <strong><?= htmlspecialchars($order['customer_name']) ?></strong>
                            </div>
                            <div class="ov-cust-item">
                                <span>Mobile</span>
                                <strong><?= htmlspecialchars($order['customer_mobile']) ?></strong>
                            </div>
                            <div class="ov-cust-item">
                                <span><?= ($order['delivery_mode'] === 'pickup') ? 'Pickup Branch' : 'Apartment' ?></span>
                                <strong>
                                    <?php if ($order['delivery_mode'] === 'pickup'): ?>
                                        <?= htmlspecialchars($order['pickup_branch_name'] ?: '—') ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars($order['apartment_name'] ?: '—') ?>
                                        <?php if (!empty($order['apartment_code'])): ?>
                                            (<?= htmlspecialchars($order['apartment_code']) ?>)
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </strong>
                            </div>
                            <div class="ov-cust-item">
                                <span><?= ($order['delivery_mode'] === 'pickup') ? 'Order Mode' : 'Division' ?></span>
                                <strong>
                                    <?php if ($order['delivery_mode'] === 'pickup'): ?>
                                        <span style="background:#fdf1e2;color:#a35a0e;padding:4px 10px;border-radius:999px;font-size:10px;font-weight:800;display:inline-flex;align-items:center;gap:4px;">
                                            <i class="bi bi-shop"></i> Pickup
                                        </span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($order['division'] ?: '—') ?>
                                    <?php endif; ?>
                                </strong>
                            </div>
                            <div class="ov-cust-item">
                                <span>Delivery Boy</span>
                                <strong>
                                    <?= htmlspecialchars($order['boy_name'] ?: '—') ?>
                                    <?php if (!empty($order['boy_code'])): ?>
                                        <span style="font-size:10px;color:#948c82;font-weight:600;">
                                            #<?= htmlspecialchars($order['boy_code']) ?>
                                        </span>
                                    <?php endif; ?>
                                </strong>
                            </div>
                            <div class="ov-cust-item">
                                <span>Payment Status</span>
                                <strong><?= htmlspecialchars(ucfirst($order['payment_status'])) ?></strong>
                            </div>
                        </div>
                    </div>


                    <!-- PRODUCTS -->
                    <div class="ov-card">
                        <div class="ov-card-title">
                            <i class="bi bi-box-seam"></i>
                            <div>
                                <h3>Products</h3>
                                <span>Everything in this order</span>
                            </div>
                        </div>

                        <div class="ov-products">

                            <?php if (empty($products)): ?>

                                <div style="text-align:center;padding:20px;color:#948c82;font-size:12px;">
                                    No products in this order.
                                </div>

                            <?php else: ?>

                                <?php foreach ($products as $p):

                                    $thumb = !empty($p['image'])
                                        ? '<img src="' . htmlspecialchars($p['image']) . '" alt="" onerror="this.style.display=\'none\';this.parentElement.innerHTML=\'📦\';">'
                                        : '📦';

                                    $variantLine = !empty($p['variant_name'])
                                        ? ' · ' . htmlspecialchars($p['variant_name']) .
                                          (!empty($p['variant_qty']) ? ' (' . htmlspecialchars($p['variant_qty']) . ')' : '')
                                        : '';

                                    $lineTotal = $p['line_total'] ?? ($p['price'] * $p['qty']);
                                ?>

                                    <div class="ov-product">
                                        <div class="ov-product-thumb"><?= $thumb ?></div>
                                        <div class="ov-product-info">
                                            <div class="ov-product-name">
                                                <?= htmlspecialchars($p['name'] ?? '') ?>
                                            </div>
                                            <div class="ov-product-meta">
                                                #<?= htmlspecialchars($p['code'] ?? '') ?><?= $variantLine ?>
                                                · <strong>₹<?= fmtNoDec($p['price']) ?></strong>
                                                × <?= (int)$p['qty'] ?>
                                            </div>
                                        </div>
                                        <div class="ov-product-total">
                                            ₹<?= fmtNoDec($lineTotal) ?>
                                        </div>
                                    </div>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </div>

                        <div class="ov-totals">
                            <div class="ov-total-row">
                                <span>Subtotal</span>
                                <strong>₹<?= fmtNoDec($order['subtotal']) ?></strong>
                            </div>
                            <div class="ov-total-row">
                                <span><?= ($order['delivery_mode'] === 'pickup') ? 'Delivery Charge (Free)' : 'Delivery Charge' ?></span>
                                <strong>₹<?= fmtNoDec($order['division_charge']) ?></strong>
                            </div>
                            <div class="ov-total-row grand">
                                <span>Total <?= ($order['payment_status'] === 'paid') ? 'Paid' : 'Amount' ?></span>
                                <strong>₹<?= fmtNoDec($order['total_amount']) ?></strong>
                            </div>
                        </div>
                    </div>

                </div>


                <!-- RIGHT -->
                <div>

                    <div class="ov-card">
                        <div class="ov-card-title">
                            <i class="bi bi-sliders"></i>
                            <div>
                                <h3>Order Status</h3>
                                <span>Update state</span>
                            </div>
                        </div>

                        <div class="ov-status-row">
                            <div class="ov-status-field">
                                <label>Order Status</label>
                                <select id="ovOrderStatus">
                                    <?php
                                    $isPickup = ($order['delivery_mode'] === 'pickup');
                                    $orderStatuses = [
                                        'pending'    => 'Pending',
                                        'confirmed'  => 'Confirmed',
                                        'processing' => $isPickup ? 'Ready' : 'Processing',
                                        'delivered'  => $isPickup ? 'Picked Up' : 'Delivered',
                                        'cancelled'  => 'Cancelled'
                                    ];
                                    foreach ($orderStatuses as $k => $label):
                                    ?>
                                        <option value="<?= $k ?>" <?= $order['status'] === $k ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="ov-status-field">
                                <label>Payment Status</label>
                                <select id="ovPayStatus">
                                    <?php
                                    $payStatuses = [
                                        'unpaid' => 'Unpaid',
                                        'paid'   => 'Paid',
                                        'failed' => 'Failed'
                                    ];
                                    foreach ($payStatuses as $k => $label):
                                    ?>
                                        <option value="<?= $k ?>" <?= $order['payment_status'] === $k ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                </div>

            </div>


            <div class="ov-save-bar">
                <button type="button" class="ov-save-btn" id="saveAllBtn">
                    <i class="bi bi-check-lg"></i>
                    <span id="saveAllText">Save Changes</span>
                </button>
            </div>

        </div>

    </main>


    <script>
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
        window.ORDER_ID = <?= (int)$order['id'] ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL ?>js/main.js"></script>
    <script src="<?= ADMIN_URL ?>js/order-view.js"></script>

</body>

</html>