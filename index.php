<?php
require_once './config/config.php';
require_once './config/function.php';

/* ---------------- AUTH ---------------- */
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    header('Location: login.php');
    exit;
}

/* ---------------- ROLE ---------------- */
$isAdmin = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin');

$settings = getSettings($pdo);
$siteName = $settings['username'] ?? 'Mrs Mill@';

/* =========================================================
   YEAR FILTER (for chart)
   ========================================================= */
$thisYear   = (int)date('Y');
$chartYear  = (int)($_GET['year'] ?? $thisYear);
$yearOptions = [$thisYear, $thisYear - 1, $thisYear - 2];

/* =========================================================
   KPI STATS
   ========================================================= */
$kpi = [
    'revenue'   => 0,
    'orders'    => 0,
    'products'  => 0,
    'customers' => 0,
    'today_rev' => 0,
    'today_ord' => 0,
];

try {
    $row = $pdo->query(
        "SELECT COALESCE(SUM(total_amount),0) AS revenue, COUNT(*) AS cnt
         FROM orders
         WHERE status <> 'cancelled'"
    )->fetch(PDO::FETCH_ASSOC);
    $kpi['revenue'] = (float)$row['revenue'];
    $kpi['orders']  = (int)$row['cnt'];

    $row = $pdo->query(
        "SELECT COALESCE(SUM(total_amount),0) AS revenue, COUNT(*) AS cnt
         FROM orders
         WHERE status <> 'cancelled'
           AND DATE(created_at) = CURDATE()"
    )->fetch(PDO::FETCH_ASSOC);
    $kpi['today_rev'] = (float)$row['revenue'];
    $kpi['today_ord'] = (int)$row['cnt'];

    $kpi['products']  = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 1")->fetchColumn();
    $kpi['customers'] = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE status = 1")->fetchColumn();
} catch (PDOException $e) {
}

/* =========================================================
   MONTHLY REVENUE (only needed for admin chart)
   ========================================================= */
$monthlyRevenue = [];
$maxRevenue     = 1;

if ($isAdmin) {
    try {
        $stmt = $pdo->prepare(
            "SELECT MONTH(created_at) AS m,
                    COALESCE(SUM(total_amount),0) AS revenue
             FROM orders
             WHERE status <> 'cancelled'
               AND YEAR(created_at) = ?
             GROUP BY MONTH(created_at)"
        );
        $stmt->execute([$chartYear]);
        $byMonth = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $byMonth[(int)$r['m']] = (float)$r['revenue'];
        }

        for ($m = 1; $m <= 12; $m++) {
            $monthlyRevenue[] = [
                'mon'     => date('M', mktime(0, 0, 0, $m, 1)),
                'revenue' => $byMonth[$m] ?? 0,
            ];
        }
    } catch (PDOException $e) {
    }

    foreach ($monthlyRevenue as $m) {
        if ($m['revenue'] > $maxRevenue) $maxRevenue = $m['revenue'];
    }
}

/* =========================================================
   ORDER STATUS COUNTS
   ========================================================= */
$orderStatus = [
    'delivered'  => 0,
    'processing' => 0,
    'pending'    => 0,
    'cancelled'  => 0,
];

try {
    $rows = $pdo->query(
        "SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $s = strtolower($r['status']);
        if (isset($orderStatus[$s])) {
            $orderStatus[$s] = (int)$r['cnt'];
        } elseif ($s === 'confirmed') {
            $orderStatus['processing'] += (int)$r['cnt'];
        }
    }
} catch (PDOException $e) {
}

$totalOrdersAll = array_sum($orderStatus);
if ($totalOrdersAll < 1) $totalOrdersAll = 1;

$donutDelivered  = round(($orderStatus['delivered']  / $totalOrdersAll) * 100);
$donutProcessing = round(($orderStatus['processing'] / $totalOrdersAll) * 100);
$donutPending    = round(($orderStatus['pending']    / $totalOrdersAll) * 100);
$donutCancelled  = 100 - $donutDelivered - $donutProcessing - $donutPending;
if ($donutCancelled < 0) $donutCancelled = 0;

/* =========================================================
   RECENT ORDERS
   ========================================================= */
$recentOrders = [];
try {
    $stmt = $pdo->query(
        "SELECT o.order_code, o.customer_name, o.customer_mobile,
                o.total_amount, o.status, o.products_json, o.created_at
         FROM orders o
         WHERE o.status <> 'cancelled'
         ORDER BY o.id DESC
         LIMIT 4"
    );
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

/* =========================================================
   TOP SELLING PRODUCTS (admin only)
   ========================================================= */
$bestProducts = [];
if ($isAdmin) {
    try {
        $rows = $pdo->query(
            "SELECT products_json FROM orders WHERE status <> 'cancelled'"
        )->fetchAll(PDO::FETCH_ASSOC);

        $agg = [];
        foreach ($rows as $r) {
            $items = json_decode($r['products_json'] ?? '[]', true);
            if (!is_array($items)) continue;
            foreach ($items as $p) {
                $name = $p['name'] ?? '';
                if ($name === '') continue;
                $qty   = (int)($p['qty'] ?? 0);
                $total = (float)($p['line_total'] ?? (($p['price'] ?? 0) * $qty));
                $image = $p['image'] ?? '';

                if (!isset($agg[$name])) {
                    $agg[$name] = ['qty' => 0, 'revenue' => 0, 'image' => $image];
                }
                $agg[$name]['qty']     += $qty;
                $agg[$name]['revenue'] += $total;
                if ($agg[$name]['image'] === '' && $image !== '') {
                    $agg[$name]['image'] = $image;
                }
            }
        }

        uasort($agg, fn($a, $b) => $b['qty'] <=> $a['qty']);
        $bestProducts = array_slice($agg, 0, 4, true);
    } catch (PDOException $e) {
    }
}

/* =========================================================
   STOCK PRODUCTS (with image)
   ========================================================= */
$stockProducts = [];
try {
    $stmt = $pdo->query(
        "SELECT p.product_name, p.product_image,
                (SELECT COUNT(*) FROM product_variants v
                 WHERE v.product_code = p.product_code AND v.status = 1) AS variant_count
         FROM products p
         WHERE p.status = 1
         ORDER BY p.id DESC
         LIMIT 4"
    );
    $stockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

/* =========================================================
   HELPERS
   ========================================================= */
function rupees($n)
{
    return '₹' . number_format((int)round($n));
}

function initials($name)
{
    $name = trim((string)$name);
    if ($name === '') return '?';
    $parts = preg_split('/\s+/', $name);
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

function greeting()
{
    $h = (int)date('G');
    if ($h < 12) return 'Good Morning';
    if ($h < 17) return 'Good Afternoon';
    return 'Good Evening';
}

function firstProductSummary($json)
{
    $items = json_decode($json ?? '[]', true);
    if (!is_array($items) || count($items) === 0) return '—';
    $p     = $items[0];
    $name  = $p['name'] ?? 'Item';
    $qty   = (int)($p['qty'] ?? 1);
    $extra = count($items) > 1 ? ' +' . (count($items) - 1) : '';
    return $name . ' × ' . $qty . $extra;
}

function statusClass($status)
{
    switch (strtolower($status)) {
        case 'delivered':
            return 'status-completed';
        case 'processing':
        case 'confirmed':
            return 'status-processing';
        case 'pending':
            return 'status-pending';
        case 'cancelled':
            return 'status-cancelled';
        default:
            return 'status-pending';
    }
}

function productImg($img)
{
    if (empty($img)) return '';
    return ADMIN_URL . $img;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include './includes/head.php'; ?>

    <style>
        /* Small additions for real product images */
        .product-image,
        .best-image {
            overflow: hidden;
            padding: 0 !important;
        }

        .product-image img,
        .best-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            border-radius: inherit;
        }

        .product-image .no-img,
        .best-image .no-img {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #b0a79c;
            font-size: 18px;
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
                    <div class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)) ?></div>
                    <div>
                        <div class="admin-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'User') ?></div>
                        <div class="admin-role"><?= $isAdmin ? 'Admin' : 'Staff' ?></div>
                    </div>
                </div>
            </div>
        </header>


        <div class="content">

            <?php
            $displayName = $_SESSION['admin_name'] ?? 'User';
            $roleLabel   = $isAdmin ? 'Admin' : 'Staff';
            $firstName   = explode(' ', trim($displayName))[0];
            ?>

            <div class="page-heading">
                <h1><?= htmlspecialchars(greeting()) ?>, <?= htmlspecialchars($firstName) ?> 👋</h1>
                <p>
                    <?php if ($isAdmin): ?>
                        Here's what's happening with your fresh products today.
                    <?php else: ?>
                        Here's your work overview for today.
                    <?php endif; ?>
                </p>
            </div>


            <!-- HERO -->
            <section class="fresh-hero">
                <div class="hero-content">
                    <div class="hero-label">
                        <i class="bi bi-leaf-fill"></i>
                        FRESH FROM OUR KITCHEN
                    </div>
                    <h2>
                        Healthy products.<br>
                        Happy customers.
                    </h2>
                    <p>
                        <?php if ($isAdmin): ?>
                            Your store is growing beautifully.
                            Keep your products fresh, your customers happy
                            and your orders moving.
                        <?php else: ?>
                            Keep orders moving and customers happy.
                            Every delivery you handle matters.
                        <?php endif; ?>
                    </p>

                    <div class="hero-stats">
                        <div class="hero-stat">
                            <strong><?= rupees($kpi['today_rev']) ?></strong>
                            <span>Today's Sales</span>
                        </div>
                        <div class="hero-divider"></div>
                        <div class="hero-stat">
                            <strong><?= $kpi['today_ord'] ?></strong>
                            <span>Orders Today</span>
                        </div>
                    </div>
                </div>

                <div class="hero-decoration"></div>
                <div class="hero-decoration-two">🌿</div>
            </section>


            <!-- KPI CARDS -->
            <div class="row g-3 mb-4">

                <?php if ($isAdmin): ?>
                    <!-- Total Revenue — ADMIN ONLY -->
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="kpi-card">
                            <div class="kpi-top">
                                <div class="kpi-icon red"><i class="bi bi-currency-rupee"></i></div>
                                <span class="trend up"><i class="bi bi-arrow-up"></i> 12.5%</span>
                            </div>
                            <div class="kpi-label">Total Revenue</div>
                            <div class="kpi-value"><?= rupees($kpi['revenue']) ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Total Orders — everyone -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="kpi-card">
                        <div class="kpi-top">
                            <div class="kpi-icon green"><i class="bi bi-bag-check"></i></div>
                            <span class="trend up"><i class="bi bi-arrow-up"></i> 8.4%</span>
                        </div>
                        <div class="kpi-label">Total Orders</div>
                        <div class="kpi-value"><?= number_format($kpi['orders']) ?></div>
                    </div>
                </div>

                <!-- Products — everyone -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="kpi-card">
                        <div class="kpi-top">
                            <div class="kpi-icon gold"><i class="bi bi-box-seam"></i></div>
                            <span class="trend up"><i class="bi bi-arrow-up"></i> 4.8%</span>
                        </div>
                        <div class="kpi-label">Products</div>
                        <div class="kpi-value"><?= $kpi['products'] ?></div>
                    </div>
                </div>

                <!-- Customers — everyone -->
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="kpi-card">
                        <div class="kpi-top">
                            <div class="kpi-icon brown"><i class="bi bi-people"></i></div>
                            <span class="trend up"><i class="bi bi-arrow-up"></i> 15.2%</span>
                        </div>
                        <div class="kpi-label">Customers</div>
                        <div class="kpi-value"><?= number_format($kpi['customers']) ?></div>
                    </div>
                </div>

            </div>


            <!-- REVENUE + ORDER STATUS -->
            <div class="row g-3 mb-4">

                <?php if ($isAdmin): ?>
                    <!-- Revenue Overview chart — ADMIN ONLY -->
                    <div class="col-12 col-xl-8">
                        <div class="section-card">
                            <div class="section-title">
                                <div>
                                    <h3>Revenue Overview</h3>
                                    <span>Monthly sales performance</span>
                                </div>

                                <form method="GET" id="yearForm" style="margin:0;">
                                    <select name="year" class="form-select form-select-sm"
                                        style="width:100px;font-size:10px;"
                                        onchange="document.getElementById('yearForm').submit();">
                                        <?php foreach ($yearOptions as $y): ?>
                                            <option value="<?= $y ?>" <?= $y === $chartYear ? 'selected' : '' ?>>
                                                <?= $y ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>

                            <div class="chart-area">
                                <div class="chart-grid">
                                    <div class="chart-grid-line"></div>
                                    <div class="chart-grid-line"></div>
                                    <div class="chart-grid-line"></div>
                                    <div class="chart-grid-line"></div>
                                    <div class="chart-grid-line"></div>
                                </div>

                                <?php foreach ($monthlyRevenue as $m):
                                    $h = $maxRevenue > 0 ? round(($m['revenue'] / $maxRevenue) * 100) : 0;
                                    if ($h < 3) $h = 3;
                                ?>
                                    <div class="bar-wrap">
                                        <div class="bar" style="height:<?= $h ?>%;" title="<?= rupees($m['revenue']) ?>"></div>
                                        <div class="bar-label"><?= htmlspecialchars($m['mon']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>


                <!-- Order Status — everyone. Wider if chart hidden -->
                <div class="col-12 <?= $isAdmin ? 'col-xl-4' : 'col-xl-12' ?>">
                    <div class="section-card">
                        <div class="section-title">
                            <div>
                                <h3>Order Status</h3>
                                <span>All-time orders</span>
                            </div>
                            <a href="<?= ADMIN_URL ?>orders.php" class="view-link">View All</a>
                        </div>

                        <div class="status-layout">

                            <?php
                            $d1 = $donutDelivered;
                            $d2 = $d1 + $donutProcessing;
                            $d3 = $d2 + $donutPending;

                            $donutStyle =
                                "background: conic-gradient(" .
                                "#2e7d32 0% {$d1}%, " .
                                "#1565c0 {$d1}% {$d2}%, " .
                                "#b8893c {$d2}% {$d3}%, " .
                                "#c8bfb4 {$d3}% 100%);";
                            ?>

                            <div class="donut" style="<?= $donutStyle ?>">
                                <div class="donut-center">
                                    <strong><?= array_sum($orderStatus) ?></strong>
                                    <span>Total Orders</span>
                                </div>
                            </div>

                            <div class="status-list">
                                <div class="status-row">
                                    <span class="status-dot green"></span>
                                    Delivered
                                    <strong><?= $orderStatus['delivered'] ?></strong>
                                </div>

                                <div class="status-row">
                                    <span class="status-dot red"></span>
                                    Processing
                                    <strong><?= $orderStatus['processing'] ?></strong>
                                </div>

                                <div class="status-row">
                                    <span class="status-dot gold"></span>
                                    Pending
                                    <strong><?= $orderStatus['pending'] ?></strong>
                                </div>

                                <div class="status-row">
                                    <span class="status-dot gray"></span>
                                    Cancelled
                                    <strong><?= $orderStatus['cancelled'] ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>


            <!-- STOCK + BEST SELLERS -->
            <div class="row g-3 mb-4">

                <!-- Freshness & Stock — everyone. Wider when best sellers hidden -->
                <div class="col-12 <?= $isAdmin ? 'col-xl-6' : 'col-xl-12' ?>">
                    <div class="section-card">
                        <div class="section-title">
                            <div>
                                <h3>Freshness & Stock</h3>
                                <span>Product inventory health</span>
                            </div>
                            <a href="<?= ADMIN_URL ?>products.php" class="view-link">Manage Stock</a>
                        </div>

                        <?php if (empty($stockProducts)): ?>
                            <div style="padding:30px 20px;text-align:center;color:#948c82;font-size:12px;">
                                No products yet.
                            </div>
                        <?php else: ?>
                            <?php foreach ($stockProducts as $sp):
                                $varCount  = (int)$sp['variant_count'];
                                $badge     = $varCount >= 2 ? 'fresh' : ($varCount === 1 ? 'low' : 'critical');
                                $badgeText = $varCount >= 2 ? 'FRESH' : ($varCount === 1 ? 'LOW STOCK' : 'RESTOCK');
                                $img       = productImg($sp['product_image']);
                            ?>
                                <div class="product-stock">

                                    <div class="product-image">
                                        <?php if ($img): ?>
                                            <img src="<?= htmlspecialchars($img) ?>" alt=""
                                                onerror="this.style.display='none';this.parentElement.innerHTML='<div class=\'no-img\'><i class=\'bi bi-image\'></i></div>';">
                                        <?php else: ?>
                                            <div class="no-img"><i class="bi bi-image"></i></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="product-info">
                                        <div class="product-name"><?= htmlspecialchars($sp['product_name']) ?></div>
                                        <div class="product-meta"><?= $varCount ?> variant<?= $varCount === 1 ? '' : 's' ?> available</div>
                                    </div>

                                    <div class="stock-status">
                                        <span class="stock-badge <?= $badge ?>"><?= $badgeText ?></span>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>


                <?php if ($isAdmin): ?>
                    <!-- Best Selling Products — ADMIN ONLY -->
                    <div class="col-12 col-xl-6">
                        <div class="section-card">
                            <div class="section-title">
                                <div>
                                    <h3>Best Selling Products</h3>
                                    <span>Top performing products</span>
                                </div>
                                <a href="<?= ADMIN_URL ?>products.php" class="view-link">View Products</a>
                            </div>

                            <?php if (empty($bestProducts)): ?>
                                <div style="padding:30px 20px;text-align:center;color:#948c82;font-size:12px;">
                                    No sales data yet.
                                </div>
                            <?php else: ?>
                                <?php $rank = 1;
                                foreach ($bestProducts as $name => $bp):
                                    $bImg = !empty($bp['image']) ? $bp['image'] : '';
                                ?>
                                    <div class="best-product">

                                        <div class="rank"><?= str_pad((string)$rank, 2, '0', STR_PAD_LEFT) ?></div>

                                        <div class="best-image">
                                            <?php if ($bImg): ?>
                                                <img src="<?= htmlspecialchars($bImg) ?>" alt=""
                                                    onerror="this.style.display='none';this.parentElement.innerHTML='<div class=\'no-img\'><i class=\'bi bi-image\'></i></div>';">
                                            <?php else: ?>
                                                <div class="no-img"><i class="bi bi-image"></i></div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="best-info">
                                            <strong><?= htmlspecialchars($name) ?></strong>
                                            <span><?= (int)$bp['qty'] ?> orders</span>
                                        </div>

                                        <div class="best-sales"><?= rupees($bp['revenue']) ?></div>

                                    </div>
                                <?php $rank++;
                                endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>


            <!-- RECENT ORDERS + QUICK ACTIONS -->
            <div class="row g-3">

                <div class="col-12 col-xl-9">
                    <div class="section-card">
                        <div class="section-title">
                            <div>
                                <h3>Recent Orders</h3>
                                <span>Latest customer purchases</span>
                            </div>
                            <a href="<?= ADMIN_URL ?>orders.php" class="view-link">View All Orders</a>
                        </div>

                        <div class="table-responsive">
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Order ID</th>
                                        <th>Product</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentOrders)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align:center;padding:40px;color:#948c82;">
                                                No orders yet.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentOrders as $o): ?>
                                            <tr>
                                                <td>
                                                    <div class="customer">
                                                        <div class="customer-avatar"><?= htmlspecialchars(initials($o['customer_name'])) ?></div>
                                                        <div>
                                                            <div class="customer-name"><?= htmlspecialchars($o['customer_name']) ?></div>
                                                            <div class="customer-phone"><?= htmlspecialchars($o['customer_mobile']) ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>#<?= htmlspecialchars($o['order_code']) ?></td>
                                                <td><?= htmlspecialchars(firstProductSummary($o['products_json'])) ?></td>
                                                <td><strong><?= rupees($o['total_amount']) ?></strong></td>
                                                <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                                                <td>
                                                    <span class="order-status <?= statusClass($o['status']) ?>">
                                                        <?= htmlspecialchars(ucfirst($o['status'])) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>


                <div class="col-12 col-xl-3">
                    <div class="section-card">
                        <div class="section-title">
                            <div>
                                <h3>Quick Actions</h3>
                                <span>Manage store</span>
                            </div>
                        </div>

                        <?php if ($isAdmin): ?>
                            <!-- Add Product — ADMIN ONLY -->
                            <a href="<?= ADMIN_URL ?>add-product.php" class="quick-action">
                                <div class="quick-icon"><i class="bi bi-plus-lg"></i></div>
                                <div>
                                    <strong>Add Product</strong>
                                    <span>Create new product</span>
                                </div>
                            </a>
                        <?php endif; ?>

                        <!-- New Order — everyone (staff helps take orders) -->
                        <a href="<?= ADMIN_URL ?>manual-order-taken.php" class="quick-action">
                            <div class="quick-icon"><i class="bi bi-bag-plus"></i></div>
                            <div>
                                <strong>New Order</strong>
                                <span>Create manual order</span>
                            </div>
                        </a>

                        <?php if ($isAdmin): ?>
                            <!-- Create Offer — ADMIN ONLY -->
                            <a href="<?= ADMIN_URL ?>discounts.php" class="quick-action">
                                <div class="quick-icon"><i class="bi bi-tag"></i></div>
                                <div>
                                    <strong>Create Offer</strong>
                                    <span>Promote your products</span>
                                </div>
                            </a>

                            <!-- Reports — ADMIN ONLY -->
                            <a href="<?= ADMIN_URL ?>report.php" class="quick-action">
                                <div class="quick-icon"><i class="bi bi-bar-chart-line"></i></div>
                                <div>
                                    <strong>View Reports</strong>
                                    <span>Check sales performance</span>
                                </div>
                            </a>

                            <!-- Store Settings — ADMIN ONLY -->
                            <a href="<?= ADMIN_URL ?>settings.php" class="quick-action">
                                <div class="quick-icon"><i class="bi bi-gear"></i></div>
                                <div>
                                    <strong>Store Settings</strong>
                                    <span>Manage your store</span>
                                </div>
                            </a>
                        <?php endif; ?>

                    </div>
                </div>

            </div>

        </div>

    </main>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL ?>js/main.js"></script>

</body>

</html>