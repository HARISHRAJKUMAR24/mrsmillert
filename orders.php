<?php
require_once './config/config.php';
require_once './config/function.php';

/* ---------------- AUTH CHECK ---------------- */
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    header('Location: login.php');
    exit;
}

$settings = getSettings($pdo);
$siteName = $settings['username'] ?? 'Mrs Mill@';

/* ---------------- STATS ---------------- */
$stats = [
    'total'    => 0,
    'paid'     => 0,
    'unpaid'   => 0,
    'today'    => 0,
    'revenue'  => 0,
];

try {
    $s = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) AS paid,
            SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) AS unpaid,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today,
            SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) AS revenue
         FROM orders"
    )->fetch();

    if ($s) {
        $stats['total']   = (int)$s['total'];
        $stats['paid']    = (int)$s['paid'];
        $stats['unpaid']  = (int)$s['unpaid'];
        $stats['today']   = (int)$s['today'];
        $stats['revenue'] = (float)$s['revenue'];
    }

} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

    <style>
        .or-page { padding: 30px 32px 40px; }

        .or-header {
            display: flex; align-items: center; justify-content: space-between;
            gap: 20px; margin-bottom: 25px; flex-wrap: wrap;
        }

        .or-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px; font-weight: 700;
            color: #302923; margin: 0 0 5px;
        }

        .or-title p { margin: 0; color: #817a71; font-size: 13px; }

        .or-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px; margin-bottom: 22px;
        }

        .or-stat {
            background: #fff; border: 1.5px solid #ece5da;
            border-radius: 16px; padding: 16px 18px;
            transition: .25s ease;
        }

        .or-stat:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(48,41,35,.08); }

        .or-stat-label {
            font-size: 10.5px; font-weight: 700;
            color: #948c82; letter-spacing: .5px;
            text-transform: uppercase; margin-bottom: 6px;
        }

        .or-stat-value {
            font-family: "Playfair Display", serif;
            font-size: 24px; font-weight: 700;
            color: #302923; line-height: 1;
        }

        .or-stat-sub { font-size: 10.5px; color: #948c82; margin-top: 6px; }
        .or-stat.red .or-stat-value { color: #b51f2c; }
        .or-stat.green .or-stat-value { color: #2e7d32; }
        .or-stat.gold .or-stat-value { color: #b8893c; }

        /* Filters */
        .or-filters {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 18px; flex-wrap: wrap;
        }

        .or-tabs {
            display: flex; background: #fdfaf4;
            border: 1.5px solid #ece5da; border-radius: 11px;
            padding: 4px; gap: 4px;
        }

        .or-tab {
            border: none; background: transparent;
            padding: 8px 14px; font-family: "DM Sans", sans-serif;
            font-size: 11.5px; font-weight: 700;
            color: #6f675f; border-radius: 8px;
            cursor: pointer; transition: .2s ease;
            white-space: nowrap;
        }

        .or-tab:hover { color: #b51f2c; }
        .or-tab.active { background: #fff; color: #b51f2c; box-shadow: 0 4px 12px rgba(48,41,35,.08); }

        .or-search {
            flex: 1; min-width: 220px; max-width: 340px;
            position: relative;
        }

        .or-search input {
            width: 100%; height: 40px;
            border: 1.5px solid #ece5da; background: #fff;
            border-radius: 11px; padding: 0 14px 0 40px;
            font-family: "DM Sans", sans-serif; font-size: 12.5px;
            color: #292521; outline: none; transition: .2s ease;
        }

        .or-search input:focus {
            border-color: #b51f2c;
            box-shadow: 0 0 0 4px rgba(181,31,44,.08);
        }

        .or-search > i {
            position: absolute; left: 14px; top: 50%;
            transform: translateY(-50%); color: #b0a79c;
            font-size: 14px; pointer-events: none;
        }

        /* Table */
        .or-card {
            background: #fff; border: 1px solid #eee7dc;
            border-radius: 20px; padding: 6px; overflow: hidden;
        }

        .or-table-wrap { overflow-x: auto; border-radius: 16px; }

        .or-table {
            width: 100%; border-collapse: collapse;
            min-width: 900px;
        }

        .or-table thead th {
            text-align: left; padding: 14px 16px;
            background: #fdfaf4; color: #8a7f73;
            font-size: 10px; font-weight: 800;
            letter-spacing: 1.2px; text-transform: uppercase;
            border-bottom: 1px solid #f0ebe4; white-space: nowrap;
        }

        .or-table tbody tr {
            transition: background .2s ease;
            border-bottom: 1px solid #f4efe8;
        }

        .or-table tbody tr:last-child { border-bottom: 0; }
        .or-table tbody tr:hover { background: #fffcf5; }

        .or-table tbody td {
            padding: 14px 16px; font-size: 12px;
            color: #4e4841; vertical-align: middle;
        }

        .order-cell { display: flex; align-items: center; gap: 12px; }

        .order-avatar {
            width: 40px; height: 40px;
            border-radius: 11px;
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff; display: flex; align-items: center;
            justify-content: center; font-size: 15px;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(181,31,44,.2);
        }

        .order-info { min-width: 0; }

        .order-code { font-weight: 800; color: #b51f2c; font-size: 12.5px; }

        .order-time { font-size: 10px; color: #948c82; margin-top: 2px; }

        .cust-cell { min-width: 160px; }

        .cust-name {
            font-weight: 700; color: #302923;
            font-size: 12.5px;
            white-space: nowrap; overflow: hidden;
            text-overflow: ellipsis; max-width: 180px;
        }

        .cust-meta { font-size: 10.5px; color: #948c82; margin-top: 3px; }
        .cust-meta i { color: #b51f2c; margin-right: 4px; font-size: 10px; }

        .order-status, .payment-status {
            display: inline-block; padding: 5px 11px;
            border-radius: 20px; font-size: 10px;
            font-weight: 800; letter-spacing: .4px;
            white-space: nowrap;
        }

        .status-pending    { background: #fdf1e2; color: #a35a0e; }
        .status-confirmed  { background: #e8f1e8; color: #2e7d32; }
        .status-processing { background: #e5eefb; color: #1565c0; }
        .status-delivered  { background: #e8f6ea; color: #1b5e20; }
        .status-cancelled  { background: #fdeaea; color: #b51f2c; }

        .pay-paid   { background: #e8f6ea; color: #1b5e20; }
        .pay-unpaid { background: #fff4d6; color: #8a6a1e; }
        .pay-failed { background: #fdeaea; color: #b51f2c; }

        .amount-cell {
            font-family: "Playfair Display", serif;
            font-weight: 700; color: #b51f2c;
            font-size: 14px; white-space: nowrap;
        }

        .row-view {
            width: 32px; height: 32px;
            border-radius: 9px; background: #faf7f0;
            border: 1.5px solid #ece5da; color: #6f675f;
            display: inline-flex; align-items: center;
            justify-content: center; font-size: 13px;
            transition: .2s ease;
            text-decoration: none;
        }

        .or-table tbody tr:hover .row-view {
            background: #b51f2c; border-color: #b51f2c; color: #fff;
        }

        .or-empty {
            text-align: center; padding: 60px 20px; color: #948c82;
        }

        .or-empty i { font-size: 50px; color: #ece5da; display: block; margin-bottom: 12px; }
        .or-empty h3 { margin: 0 0 5px; font-size: 15px; color: #6f675f; font-weight: 700; }
        .or-empty p { margin: 0; font-size: 12px; }

        /* Pagination */
        .or-pagination {
            display: flex; align-items: center; justify-content: space-between;
            gap: 15px; margin-top: 18px;
            padding: 16px 20px 6px;
            border-top: 1px solid #f2ede5; flex-wrap: wrap;
        }

        .pagination-left { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }

        .pagination-info { font-size: 11px; color: #817a71; }
        .pagination-info strong { color: #302923; font-weight: 700; }

        .pagination-perpage {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 11px; color: #817a71;
        }

        .pagination-perpage select {
            height: 32px; border: 1px solid #eee7dc;
            border-radius: 8px; background: #fffdf9;
            padding: 0 26px 0 10px; font-family: inherit;
            font-size: 11px; font-weight: 700;
            color: #4c4640; cursor: pointer; outline: none;
            appearance: none; -webkit-appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 16 16'><path fill='%23817a71' d='M8 11L3 6h10z'/></svg>");
            background-repeat: no-repeat;
            background-position: right 9px center;
            transition: .2s ease;
        }

        .pagination-controls { display: flex; align-items: center; gap: 4px; }

        .pagination-controls button {
            min-width: 34px; height: 34px; border-radius: 9px;
            border: 1px solid #eee7dc; background: #fff;
            color: #6f675f; font-size: 11px; font-weight: 700;
            cursor: pointer; display: inline-flex;
            align-items: center; justify-content: center;
            transition: .2s ease; padding: 0 8px;
            font-family: inherit;
        }

        .pagination-controls button:hover:not(:disabled):not(.active) {
            background: #faf7f0; border-color: #e4ddd3; color: #302923;
        }

        .pagination-controls button.active {
            background: #b51f2c; border-color: #b51f2c; color: #fff;
            box-shadow: 0 4px 12px rgba(181,31,44,.22);
            cursor: default;
        }

        .pagination-controls button:disabled { opacity: .4; cursor: not-allowed; }

        .pagination-controls .page-ellipsis {
            min-width: 26px; height: 34px;
            display: inline-flex; align-items: center;
            justify-content: center; color: #b5aca2;
            font-size: 12px; font-weight: 700;
            user-select: none;
        }

        .pagination-controls i { font-size: 12px; }

        @media (max-width: 1024px) {
            .or-stats { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .or-page { padding: 20px 15px 30px; }
            .or-stats { grid-template-columns: repeat(2, 1fr); }
            .or-pagination { flex-direction: column; align-items: stretch; }
            .pagination-left { justify-content: center; }
            .pagination-info { text-align: center; }
            .pagination-controls { justify-content: center; flex-wrap: wrap; }
        }

        @media (max-width: 480px) {
            .or-stats { grid-template-columns: 1fr; }
            .pagination-controls button { min-width: 30px; height: 30px; font-size: 10px; }
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


        <div class="or-page">

            <div class="or-header">
                <div class="or-title">
                    <h1>Orders</h1>
                    <p>Click a row to view full details.</p>
                </div>
            </div>


            <div class="or-stats">

                <div class="or-stat">
                    <div class="or-stat-label">Total Orders</div>
                    <div class="or-stat-value"><?= $stats['total'] ?></div>
                    <div class="or-stat-sub">All time</div>
                </div>

                <div class="or-stat green">
                    <div class="or-stat-label">Paid</div>
                    <div class="or-stat-value"><?= $stats['paid'] ?></div>
                    <div class="or-stat-sub">₹<?= number_format($stats['revenue'], 2) ?> collected</div>
                </div>

                <div class="or-stat red">
                    <div class="or-stat-label">Unpaid</div>
                    <div class="or-stat-value"><?= $stats['unpaid'] ?></div>
                    <div class="or-stat-sub">Awaiting payment</div>
                </div>

                <div class="or-stat">
                    <div class="or-stat-label">Today</div>
                    <div class="or-stat-value"><?= $stats['today'] ?></div>
                    <div class="or-stat-sub">New orders</div>
                </div>

            </div>


            <div class="or-filters">

                <div class="or-tabs">
                    <button class="or-tab active" data-filter="all">All</button>
                    <button class="or-tab" data-filter="paid">Paid</button>
                    <button class="or-tab" data-filter="unpaid">Unpaid</button>
                    <button class="or-tab" data-filter="today">Today</button>
                </div>

                <div class="or-search">
                    <i class="bi bi-search"></i>
                    <input type="text" id="orSearch" placeholder="Search order code, name, mobile...">
                </div>

            </div>


            <div class="or-card">

                <div class="or-table-wrap">

                    <table class="or-table" id="orTable">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Delivery Boy</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Amount</th>
                                <th style="text-align:right;">View</th>
                            </tr>
                        </thead>
                        <tbody id="orTbody">
                            <tr>
                                <td colspan="7" style="text-align:center;padding:40px;color:#948c82;">
                                    Loading orders...
                                </td>
                            </tr>
                        </tbody>
                    </table>

                </div>


                <div class="or-pagination" id="orPagination" style="display:none;">
                    <div class="pagination-left">
                        <div class="pagination-info" id="paginationInfo">
                            Showing <strong>0</strong>–<strong>0</strong> of <strong>0</strong>
                        </div>
                        <label class="pagination-perpage">
                            Show
                            <select id="perPageSelect">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            entries
                        </label>
                    </div>
                    <div class="pagination-controls" id="paginationControls"></div>
                </div>

            </div>

        </div>

    </main>


    <script>
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL ?>js/main.js"></script>
    <script src="<?= ADMIN_URL ?>js/orders.js"></script>

</body>

</html>