<?php
require_once './config/config.php';
require_once './config/function.php';

/* ---------------- AUTH ---------------- */
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    header('Location: login.php');
    exit;
}
$isAdmin = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin');
$settings = getSettings($pdo);

/* =========================================================
   DATE RANGE
   ========================================================= */
$range = $_GET['range'] ?? 'today';
if (!in_array($range, ['today','yesterday','week','month','all','custom'], true)) {
    $range = 'today';
}

$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';

$dateFrom = null;
$dateTo   = null;

switch ($range) {
    case 'today':
        $dateFrom = date('Y-m-d 00:00:00');
        $dateTo   = date('Y-m-d 23:59:59');
        break;
    case 'yesterday':
        $dateFrom = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $dateTo   = date('Y-m-d 23:59:59', strtotime('-1 day'));
        break;
    case 'week':
        $dateFrom = date('Y-m-d 00:00:00', strtotime('-6 days'));
        $dateTo   = date('Y-m-d 23:59:59');
        break;
    case 'month':
        $dateFrom = date('Y-m-01 00:00:00');
        $dateTo   = date('Y-m-d 23:59:59');
        break;
    case 'custom':
        if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $dateFrom = $from . ' 00:00:00';
        }
        if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $dateTo = $to . ' 23:59:59';
        }
        break;
    case 'all':
    default:
        $dateFrom = null;
        $dateTo   = null;
        break;
}

$dateWhere  = "";
$dateParams = [];

if ($dateFrom !== null) {
    $dateWhere   .= " AND o.created_at >= ?";
    $dateParams[] = $dateFrom;
}
if ($dateTo !== null) {
    $dateWhere   .= " AND o.created_at <= ?";
    $dateParams[] = $dateTo;
}

/* =========================================================
   SORT
   ========================================================= */
$sort = $_GET['sort'] ?? 'qty';
if (!in_array($sort, ['qty', 'orders', 'revenue', 'name'], true)) {
    $sort = 'qty';
}

/* =========================================================
   LOAD & AGGREGATE
   ========================================================= */
$productStats = [];

try {
    $sql = "SELECT o.id AS order_id, o.products_json
            FROM orders o
            WHERE o.status <> 'cancelled'
            $dateWhere";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dateParams);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $items = json_decode($r['products_json'] ?? '[]', true);
        if (!is_array($items)) continue;

        foreach ($items as $p) {
            $pid   = (int)($p['product_id'] ?? 0);
            $code  = $p['code'] ?? '';
            $name  = $p['name'] ?? '';
            if ($name === '') continue;

            $key = ($pid > 0) ? ('p' . $pid) : ('c' . $code);

            $qty   = (int)($p['qty'] ?? 0);
            $price = (float)($p['price'] ?? 0);
            $line  = (float)($p['line_total'] ?? ($price * $qty));

            if (!isset($productStats[$key])) {
                $productStats[$key] = [
                    'product_id'    => $pid,
                    'code'          => $code,
                    'name'          => $name,
                    'image'         => $p['image'] ?? '',
                    'qty'           => 0,
                    'revenue'       => 0,
                    'order_ids'     => [],
                    'variants'      => [],
                ];
            }

            $productStats[$key]['qty']     += $qty;
            $productStats[$key]['revenue'] += $line;
            $productStats[$key]['order_ids'][$r['order_id']] = true;

            $vname = $p['variant_name'] ?? '';
            if ($vname !== '') {
                if (!isset($productStats[$key]['variants'][$vname])) {
                    $productStats[$key]['variants'][$vname] = ['qty' => 0, 'revenue' => 0];
                }
                $productStats[$key]['variants'][$vname]['qty']     += $qty;
                $productStats[$key]['variants'][$vname]['revenue'] += $line;
            }

            if ($productStats[$key]['image'] === '' && !empty($p['image'])) {
                $productStats[$key]['image'] = $p['image'];
            }
        }
    }
} catch (PDOException $e) {
    $productStats = [];
}

/* Finalize order count */
$products = [];
foreach ($productStats as $k => $p) {
    $p['orders'] = count($p['order_ids']);
    unset($p['order_ids']);
    $products[$k] = $p;
}

/* Sort */
usort($products, function ($a, $b) use ($sort) {
    switch ($sort) {
        case 'orders':  return $b['orders']  <=> $a['orders'];
        case 'revenue': return $b['revenue'] <=> $a['revenue'];
        case 'name':    return strcasecmp($a['name'], $b['name']);
        case 'qty':
        default:        return $b['qty'] <=> $a['qty'];
    }
});

/* KPI */
$kpi = [
    'products' => count($products),
    'qty'      => 0,
    'orders'   => 0,
    'revenue'  => 0,
];

foreach ($products as $p) {
    $kpi['qty']     += $p['qty'];
    $kpi['revenue'] += $p['revenue'];
}

try {
    $sql = "SELECT COUNT(*) FROM orders o WHERE o.status <> 'cancelled' $dateWhere";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dateParams);
    $kpi['orders'] = (int)$stmt->fetchColumn();
} catch (PDOException $e) {}

/* HELPERS */
function rupees($n) {
    return '₹' . number_format((int)round($n));
}

$rangeLabels = [
    'today'     => 'Today',
    'yesterday' => 'Yesterday',
    'week'      => 'Last 7 Days',
    'month'     => 'This Month',
    'custom'    => 'Custom Range',
    'all'       => 'All Time',
];
$activeLabel = $rangeLabels[$range] ?? 'Today';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include './includes/head.php'; ?>

    <style>
        .so-page { padding: 24px 26px 60px; }

        .so-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .so-header h1 {
            font-family: "Playfair Display", serif;
            font-size: 26px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 4px;
        }
        .so-header p { margin: 0; color: #817a71; font-size: 12.5px; }

        .so-range-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            background: #fdf1e2;
            color: #a35a0e;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .04em;
        }

        /* Filter Bar */
        .so-filters {
            background: #fff;
            border: 1.5px solid #eee7dc;
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }

        .so-tabs {
            display: inline-flex;
            background: #fdfaf4;
            border: 1.5px solid #ece5da;
            border-radius: 11px;
            padding: 4px;
            gap: 4px;
            flex-wrap: wrap;
        }

        .so-tab {
            border: none;
            background: transparent;
            padding: 8px 14px;
            font-family: "DM Sans", sans-serif;
            font-size: 11.5px;
            font-weight: 700;
            color: #6f675f;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
            transition: .15s ease;
        }
        .so-tab:hover { color: #b51f2c; }
        .so-tab.active {
            background: #fff;
            color: #b51f2c;
            box-shadow: 0 4px 12px rgba(48, 41, 35, .08);
        }

        .so-custom {
            display: none;
            align-items: center;
            gap: 8px;
            font-size: 11.5px;
            color: #817a71;
            font-weight: 600;
        }
        .so-custom.show { display: inline-flex; }

        .so-custom input[type="date"] {
            height: 36px;
            border: 1.5px solid #ece5da;
            background: #fff;
            border-radius: 9px;
            padding: 0 10px;
            font-family: "DM Sans", sans-serif;
            font-size: 11.5px;
            font-weight: 700;
            color: #302923;
            outline: none;
        }
        .so-custom input[type="date"]:focus {
            border-color: #b51f2c;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .08);
        }

        .so-apply {
            height: 36px;
            padding: 0 16px;
            border: none;
            border-radius: 9px;
            background: #b51f2c;
            color: #fff;
            font-family: "DM Sans", sans-serif;
            font-size: 11.5px;
            font-weight: 800;
            cursor: pointer;
        }
        .so-apply:hover { background: #8e1722; }

        /* KPI */
        .so-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }
        .so-kpi {
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 16px;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .so-kpi-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .so-kpi-icon.red    { background: #fdeaea; color: #b51f2c; }
        .so-kpi-icon.green  { background: #e8f6ea; color: #1b5e20; }
        .so-kpi-icon.blue   { background: #e5eefb; color: #1565c0; }
        .so-kpi-icon.gold   { background: #fdf1e2; color: #b8893c; }
        .so-kpi-label {
            font-size: 10.5px;
            font-weight: 800;
            color: #948c82;
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 3px;
        }
        .so-kpi-value {
            font-family: "DM Sans", sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: #302923;
            line-height: 1.1;
        }
        .so-kpi-value.red   { color: #b51f2c; }
        .so-kpi-value.green { color: #1b5e20; }
        .so-kpi-value.blue  { color: #1565c0; }
        .so-kpi-value.gold  { color: #b8893c; }

        /* CARD */
        .so-card {
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 20px;
            padding: 22px;
        }

        .so-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .so-card-title h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #302923;
        }
        .so-card-title span {
            display: block;
            margin-top: 4px;
            font-size: 10.5px;
            color: #817a71;
        }

        .so-search {
            position: relative;
            width: 260px;
        }
        .so-search input {
            width: 100%;
            height: 40px;
            border: 1.5px solid #ece5da;
            background: #fffdf9;
            border-radius: 11px;
            padding: 0 12px 0 38px;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            color: #292521;
            outline: none;
        }
        .so-search input:focus {
            border-color: #b51f2c;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(181,31,44,.08);
        }
        .so-search > i {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0a79c;
            font-size: 14px;
            pointer-events: none;
        }

        /* =====================================================
           BILL / RECEIPT CARD LIST
           ===================================================== */
        .so-bills {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .so-bill {
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 14px;
            padding: 16px 20px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 18px;
            align-items: center;
            position: relative;
            overflow: hidden;
            transition: border-color .18s ease, box-shadow .18s ease;
        }

        .so-bill::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(180deg, #b51f2c 0%, #8e1722 100%);
        }

        .so-bill:hover {
            border-color: #d98a91;
            box-shadow: 0 10px 26px rgba(48, 41, 35, .06);
        }

        /* Dashed receipt edge */
        .so-bill::after {
            content: "";
            position: absolute;
            top: 8px;
            bottom: 8px;
            right: 0;
            width: 0;
            border-right: 1px dashed #f0ebe4;
        }

        /* Rank badge */
        .so-bill-rank {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #fdf1e2 0%, #fbe3c4 100%);
            color: #a35a0e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Playfair Display", serif;
            font-size: 18px;
            font-weight: 800;
            flex-shrink: 0;
            box-shadow: 0 6px 14px rgba(184, 137, 60, .15);
        }

        /* Product info (middle) */
        .so-bill-main {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .so-bill-thumb {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            background: #f7efe3;
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #b0a79c;
            font-size: 22px;
        }
        .so-bill-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .so-bill-info {
            min-width: 0;
            flex: 1;
        }

        .so-bill-name {
            font-size: 14px;
            font-weight: 800;
            color: #302923;
            margin: 0 0 3px;
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .so-bill-code {
            font-size: 11px;
            color: #948c82;
            font-weight: 600;
            letter-spacing: .02em;
        }

        .so-bill-variants {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 7px;
        }
        .so-var-chip {
            font-size: 10px;
            font-weight: 800;
            padding: 3px 9px;
            border-radius: 6px;
            background: #f4efe8;
            color: #6f5a3f;
            letter-spacing: .03em;
            border: 1px solid #ece5da;
        }

        /* Right — amount / totals */
        .so-bill-right {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-left: 20px;
            border-left: 1.5px dashed #f0ebe4;
            flex-shrink: 0;
        }

        .so-bill-stat {
            text-align: center;
            min-width: 78px;
        }
        .so-bill-stat-label {
            font-size: 9.5px;
            font-weight: 800;
            color: #948c82;
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 5px;
        }
        .so-bill-stat-value {
            font-family: "DM Sans", sans-serif;
            font-size: 20px;
            font-weight: 800;
            color: #302923;
            line-height: 1;
        }
        .so-bill-stat-value.red { color: #b51f2c; }
        .so-bill-stat-value.blue { color: #1565c0; }

        .so-bill-revenue {
            font-family: "Playfair Display", serif;
            font-size: 22px;
            font-weight: 800;
            color: #1b5e20;
            letter-spacing: -.3px;
            text-align: right;
            min-width: 110px;
        }
        .so-bill-revenue-label {
            font-size: 9.5px;
            font-weight: 800;
            color: #948c82;
            text-transform: uppercase;
            letter-spacing: .08em;
            text-align: right;
            margin-bottom: 5px;
        }

        /* Sort buttons */
        .so-sorts {
            display: inline-flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .so-sort-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            height: 34px;
            padding: 0 12px;
            border-radius: 9px;
            border: 1.5px solid #ece5da;
            background: #fff;
            color: #6f675f;
            font-family: "DM Sans", sans-serif;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: .15s ease;
        }
        .so-sort-btn:hover {
            border-color: #d98a91;
            color: #b51f2c;
        }
        .so-sort-btn.active {
            background: #b51f2c;
            border-color: #b51f2c;
            color: #fff;
        }

        /* Empty */
        .so-empty {
            text-align: center;
            padding: 70px 20px;
            color: #948c82;
        }
        .so-empty i {
            font-size: 42px;
            color: #ece5da;
            display: block;
            margin-bottom: 12px;
        }
        .so-empty h3 {
            font-family: "Playfair Display", serif;
            font-size: 18px;
            font-weight: 700;
            color: #6f675f;
            margin: 0 0 8px;
        }
        .so-empty p { font-size: 12px; margin: 0; }

        /* Bottom total receipt */
        .so-grand {
            margin-top: 22px;
            padding: 20px 24px;
            border-radius: 14px;
            background: linear-gradient(135deg, #fdfaf4 0%, #fbe8e9 100%);
            border: 1.5px dashed #e0d3bc;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .so-grand-lbl {
            font-family: "Playfair Display", serif;
            font-size: 15px;
            font-weight: 700;
            color: #6f5a3f;
            letter-spacing: .02em;
        }
        .so-grand-val {
            font-family: "Playfair Display", serif;
            font-size: 30px;
            font-weight: 800;
            color: #b51f2c;
            letter-spacing: -.5px;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .so-bill {
                grid-template-columns: auto 1fr;
                row-gap: 14px;
            }
            .so-bill-right {
                grid-column: 1 / -1;
                border-left: 0;
                border-top: 1.5px dashed #f0ebe4;
                padding-left: 0;
                padding-top: 14px;
                justify-content: space-between;
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .so-page { padding: 18px 14px 40px; }
            .so-card { padding: 16px; border-radius: 16px; }
            .so-filters { flex-direction: column; align-items: stretch; }
            .so-tabs { justify-content: center; }
            .so-search { width: 100%; }
            .so-card-head { flex-direction: column; align-items: stretch; }
            .so-bill { padding: 14px 16px; }
            .so-bill-stat { min-width: 60px; }
            .so-bill-stat-value { font-size: 17px; }
            .so-bill-revenue { font-size: 18px; min-width: 90px; }
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
                <input type="text" placeholder="Search...">
            </div>
            <div class="top-right">
                <button class="notification">
                    <i class="bi bi-bell"></i>
                    <span class="notification-dot"></span>
                </button>
                <div class="admin-profile">
                    <div class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)) ?></div>
                    <div>
                        <div class="admin-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'User') ?></div>
                        <div class="admin-role"><?= $isAdmin ? 'Admin' : 'Staff' ?></div>
                    </div>
                </div>
            </div>
        </header>


        <div class="so-page">

            <!-- HEADER -->
            <div class="so-header">
                <div>
                    <h1>Stock Orders</h1>
                    <p>See how many orders each product received — bill view.</p>
                </div>
                <span class="so-range-badge">
                    <i class="bi bi-calendar3"></i>
                    <?= htmlspecialchars($activeLabel) ?>
                </span>
            </div>


            <!-- FILTERS -->
            <form class="so-filters" method="GET" id="soFilterForm">
                <div class="so-tabs">
                    <a href="?range=today"     class="so-tab <?= $range === 'today'     ? 'active' : '' ?>">Today</a>
                    <a href="?range=yesterday" class="so-tab <?= $range === 'yesterday' ? 'active' : '' ?>">Yesterday</a>
                    <a href="?range=week"      class="so-tab <?= $range === 'week'      ? 'active' : '' ?>">Last 7 Days</a>
                    <a href="?range=month"     class="so-tab <?= $range === 'month'     ? 'active' : '' ?>">This Month</a>
                    <a href="?range=all"       class="so-tab <?= $range === 'all'       ? 'active' : '' ?>">All Time</a>
                    <a href="#" class="so-tab <?= $range === 'custom' ? 'active' : '' ?>" id="soCustomTab">Custom</a>
                </div>

                <div class="so-custom <?= $range === 'custom' ? 'show' : '' ?>" id="soCustomBox">
                    <input type="hidden" name="range" value="<?= $range === 'custom' ? 'custom' : '' ?>" id="soRangeInput">
                    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
                    <span>to</span>
                    <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
                    <button type="submit" class="so-apply">Apply</button>
                </div>
            </form>


            <!-- KPIs -->
            <div class="so-kpis">
                <div class="so-kpi">
                    <div class="so-kpi-icon gold"><i class="bi bi-box-seam"></i></div>
                    <div>
                        <div class="so-kpi-label">Products Sold</div>
                        <div class="so-kpi-value gold"><?= (int)$kpi['products'] ?></div>
                    </div>
                </div>

                <div class="so-kpi">
                    <div class="so-kpi-icon red"><i class="bi bi-bag-check"></i></div>
                    <div>
                        <div class="so-kpi-label">Total Units</div>
                        <div class="so-kpi-value red"><?= number_format($kpi['qty']) ?></div>
                    </div>
                </div>

                <div class="so-kpi">
                    <div class="so-kpi-icon blue"><i class="bi bi-receipt"></i></div>
                    <div>
                        <div class="so-kpi-label">Total Orders</div>
                        <div class="so-kpi-value blue"><?= number_format($kpi['orders']) ?></div>
                    </div>
                </div>

                <div class="so-kpi">
                    <div class="so-kpi-icon green"><i class="bi bi-currency-rupee"></i></div>
                    <div>
                        <div class="so-kpi-label">Total Revenue</div>
                        <div class="so-kpi-value green"><?= rupees($kpi['revenue']) ?></div>
                    </div>
                </div>
            </div>


            <!-- CARD -->
            <div class="so-card">

                <div class="so-card-head">
                    <div class="so-card-title">
                        <h3>Product-wise Orders</h3>
                        <span>Showing <?= count($products) ?> product<?= count($products) === 1 ? '' : 's' ?> · <?= htmlspecialchars($activeLabel) ?></span>
                    </div>

                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                        <div class="so-sorts">
                            <a href="?range=<?= $range ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&sort=qty"
                               class="so-sort-btn <?= $sort === 'qty' ? 'active' : '' ?>">
                                <i class="bi bi-sort-down"></i> Quantity
                            </a>
                            <a href="?range=<?= $range ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&sort=orders"
                               class="so-sort-btn <?= $sort === 'orders' ? 'active' : '' ?>">
                                <i class="bi bi-sort-down"></i> Orders
                            </a>
                            <a href="?range=<?= $range ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&sort=revenue"
                               class="so-sort-btn <?= $sort === 'revenue' ? 'active' : '' ?>">
                                <i class="bi bi-sort-down"></i> Revenue
                            </a>
                            <a href="?range=<?= $range ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&sort=name"
                               class="so-sort-btn <?= $sort === 'name' ? 'active' : '' ?>">
                                <i class="bi bi-sort-alpha-down"></i> Name
                            </a>
                        </div>

                        <div class="so-search">
                            <i class="bi bi-search"></i>
                            <input type="text" id="soSearch" placeholder="Search product..." autocomplete="off">
                        </div>
                    </div>
                </div>

                <?php if (empty($products)): ?>

                    <div class="so-empty">
                        <i class="bi bi-inbox"></i>
                        <h3>No product orders in this range</h3>
                        <p>Try a different date range.</p>
                    </div>

                <?php else: ?>

                    <div class="so-bills" id="soBills">
                        <?php $rank = 1; foreach ($products as $p): ?>
                            <div class="so-bill"
                                 data-search="<?= htmlspecialchars(strtolower($p['name'] . ' ' . $p['code'])) ?>">

                                <div class="so-bill-rank"><?= $rank++ ?></div>

                                <div class="so-bill-main">
                                    <div class="so-bill-thumb">
                                        <?php if (!empty($p['image'])): ?>
                                            <img src="<?= htmlspecialchars($p['image']) ?>" alt=""
                                                onerror="this.style.display='none';this.parentElement.innerHTML='<i class=\'bi bi-image\'></i>';">
                                        <?php else: ?>
                                            <i class="bi bi-image"></i>
                                        <?php endif; ?>
                                    </div>

                                    <div class="so-bill-info">
                                        <p class="so-bill-name"><?= htmlspecialchars($p['name']) ?></p>
                                        <p class="so-bill-code">#<?= htmlspecialchars($p['code']) ?></p>

                                        <?php if (!empty($p['variants'])): ?>
                                            <div class="so-bill-variants">
                                                <?php foreach ($p['variants'] as $vn => $vd): ?>
                                                    <span class="so-var-chip">
                                                        <?= htmlspecialchars($vn) ?>: <?= (int)$vd['qty'] ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="so-bill-right">
                                    <div class="so-bill-stat">
                                        <div class="so-bill-stat-label">Orders</div>
                                        <div class="so-bill-stat-value blue"><?= (int)$p['orders'] ?></div>
                                    </div>

                                    <div class="so-bill-stat">
                                        <div class="so-bill-stat-label">Units</div>
                                        <div class="so-bill-stat-value red"><?= (int)$p['qty'] ?></div>
                                    </div>

                                    <div>
                                        <div class="so-bill-revenue-label">Revenue</div>
                                        <div class="so-bill-revenue"><?= rupees($p['revenue']) ?></div>
                                    </div>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="so-grand">
                        <div class="so-grand-lbl">
                            <i class="bi bi-receipt-cutoff"></i>
                            Grand Total — <?= htmlspecialchars($activeLabel) ?>
                        </div>
                        <div class="so-grand-val"><?= rupees($kpi['revenue']) ?></div>
                    </div>

                <?php endif; ?>

            </div>

        </div>

    </main>


    <script>
        (function () {
            var tab = document.getElementById("soCustomTab");
            var box = document.getElementById("soCustomBox");
            var rangeInput = document.getElementById("soRangeInput");
            if (!tab || !box) return;

            tab.addEventListener("click", function (e) {
                e.preventDefault();
                box.classList.add("show");
                rangeInput.value = "custom";
            });
        })();

        (function () {
            var input = document.getElementById("soSearch");
            var wrap = document.getElementById("soBills");
            if (!input || !wrap) return;

            input.addEventListener("input", function () {
                var q = this.value.trim().toLowerCase();
                var rows = wrap.querySelectorAll(".so-bill");

                rows.forEach(function (row) {
                    var hay = row.getAttribute("data-search") || "";
                    row.style.display = !q || hay.indexOf(q) !== -1 ? "" : "none";
                });
            });
        })();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL ?>js/main.js"></script>

</body>

</html>