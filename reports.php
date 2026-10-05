<?php
require_once './config/config.php';
require_once './config/function.php';

/* ---------------- AUTH ---------------- */
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    header('Location: login.php');
    exit;
}

/* ADMIN ONLY */
requireAdmin();

$settings = getSettings($pdo);
$siteName = $settings['username'] ?? 'Mrs Mill@';

/* GST settings for display */
$gstNumber = trim($settings['gst_number'] ?? '');
$taxStatus = (int)($settings['tax_status'] ?? 0);
$taxRate   = (float)($settings['tax_rate'] ?? 0);
$taxType   = $settings['tax_type'] ?? 'exclusive';

/* ---------------- DATE RANGE ---------------- */
$range  = $_GET['range']  ?? 'today';
$from   = $_GET['from']   ?? '';
$to     = $_GET['to']     ?? '';

$dateFrom = null;
$dateTo   = null;

switch ($range) {
    case 'today':
        $dateFrom = date('Y-m-d 00:00:00');
        $dateTo   = date('Y-m-d 23:59:59');
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

/* ---------------- MAIN STATS ---------------- */
$stats = [
    'revenue'    => 0,
    'orders'     => 0,
    'delivery'   => 0,
    'pickup'     => 0,
    'delivered'  => 0,
    'pending'    => 0,
    'paid'       => 0,
    'unpaid'     => 0,
    /* Tax */
    'tax_total'  => 0,
    'net_sales'  => 0,
    'tax_orders' => 0,
];

try {
    $sql = "SELECT
                COUNT(*) AS total_orders,
                SUM(total_amount) AS revenue,
                SUM(subtotal) AS subtotal_sum,
                SUM(CASE WHEN tax_amount IS NULL OR tax_amount = 0 THEN 0 ELSE tax_amount END) AS tax_sum,
                SUM(CASE WHEN tax_amount > 0 THEN 1 ELSE 0 END) AS tax_orders,
                SUM(CASE WHEN delivery_mode = 'delivery' THEN 1 ELSE 0 END) AS delivery_count,
                SUM(CASE WHEN delivery_mode = 'pickup'   THEN 1 ELSE 0 END) AS pickup_count,
                SUM(CASE WHEN delivery_status = 'enabled' THEN 1 ELSE 0 END) AS delivered_count,
                SUM(CASE WHEN delivery_status = 'disabled' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN payment_status = 'paid'   THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) AS unpaid_count
            FROM orders o
            WHERE o.status <> 'cancelled'
            $dateWhere";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dateParams);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $stats['revenue']    = (float)($row['revenue'] ?? 0);
        $stats['orders']     = (int)($row['total_orders'] ?? 0);
        $stats['delivery']   = (int)($row['delivery_count'] ?? 0);
        $stats['pickup']     = (int)($row['pickup_count'] ?? 0);
        $stats['delivered']  = (int)($row['delivered_count'] ?? 0);
        $stats['pending']    = (int)($row['pending_count'] ?? 0);
        $stats['paid']       = (int)($row['paid_count'] ?? 0);
        $stats['unpaid']     = (int)($row['unpaid_count'] ?? 0);

        $stats['tax_total']  = (float)($row['tax_sum'] ?? 0);
        $stats['tax_orders'] = (int)($row['tax_orders'] ?? 0);

        /* Net sales = revenue − tax (for inclusive tax, this equals subtotal;
           for exclusive tax, subtotal is already without tax) */
        $stats['net_sales']  = max(0, $stats['revenue'] - $stats['tax_total']);
    }
} catch (PDOException $e) {
}

/* ---------------- MODE BREAKDOWN ---------------- */
$modeStats = [
    'delivery' => ['count' => 0, 'revenue' => 0, 'tax' => 0],
    'pickup'   => ['count' => 0, 'revenue' => 0, 'tax' => 0],
];

try {
    $sql = "SELECT delivery_mode,
                COUNT(*) AS cnt,
                SUM(total_amount) AS rev,
                SUM(CASE WHEN tax_amount IS NULL OR tax_amount = 0 THEN 0 ELSE tax_amount END) AS tax_sum
            FROM orders o
            WHERE o.status <> 'cancelled'
            $dateWhere
            GROUP BY delivery_mode";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dateParams);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $m = $r['delivery_mode'];
        if (isset($modeStats[$m])) {
            $modeStats[$m]['count']   = (int)$r['cnt'];
            $modeStats[$m]['revenue'] = (float)$r['rev'];
            $modeStats[$m]['tax']     = (float)$r['tax_sum'];
        }
    }
} catch (PDOException $e) {
}

/* ---------------- TAX BREAKDOWN BY TYPE ---------------- */
$taxBreakdown = [
    'exclusive' => ['count' => 0, 'tax' => 0, 'revenue' => 0],
    'inclusive' => ['count' => 0, 'tax' => 0, 'revenue' => 0],
];

try {
    $sql = "SELECT tax_type,
                COUNT(*) AS cnt,
                SUM(CASE WHEN tax_amount IS NULL OR tax_amount = 0 THEN 0 ELSE tax_amount END) AS tax_sum,
                SUM(total_amount) AS rev
            FROM orders o
            WHERE o.status <> 'cancelled'
              AND tax_amount > 0
            $dateWhere
            GROUP BY tax_type";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dateParams);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $t = strtolower($r['tax_type'] ?? 'exclusive');
        if (!isset($taxBreakdown[$t])) $t = 'exclusive';
        $taxBreakdown[$t]['count']   = (int)$r['cnt'];
        $taxBreakdown[$t]['tax']     = (float)$r['tax_sum'];
        $taxBreakdown[$t]['revenue'] = (float)$r['rev'];
    }
} catch (PDOException $e) {
}

/* ---------------- RECENT TAXED ORDERS ---------------- */
$taxedOrders = [];
try {
    $sql = "SELECT o.order_code, o.customer_name, o.customer_mobile,
                o.subtotal, o.tax_amount, o.tax_rate, o.tax_type,
                o.total_amount, o.created_at
            FROM orders o
            WHERE o.status <> 'cancelled'
              AND o.tax_amount > 0
            $dateWhere
            ORDER BY o.created_at DESC
            LIMIT 8";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dateParams);
    $taxedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

/* ---------------- TOP DELIVERY BOYS ---------------- */
$topBoys = [];
try {
    $sql = "SELECT b.id, b.full_name, b.delivery_code,
                COUNT(o.id) AS order_count,
                SUM(CASE WHEN o.delivery_status = 'enabled' THEN 1 ELSE 0 END) AS delivered_count,
                SUM(o.total_amount) AS revenue
            FROM orders o
            INNER JOIN delivery_boys b ON b.id = o.delivery_boy_id
            WHERE o.status <> 'cancelled'
              AND o.delivery_mode = 'delivery'
              $dateWhere
            GROUP BY b.id
            ORDER BY order_count DESC
            LIMIT 8";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dateParams);
    $topBoys = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

/* ---------------- TOP APARTMENTS ---------------- */
$topApartments = [];
try {
    $sql = "SELECT o.apartment_code, o.apartment_name,
                COUNT(*) AS order_count,
                SUM(o.total_amount) AS revenue
            FROM orders o
            WHERE o.status <> 'cancelled'
              AND o.delivery_mode = 'delivery'
              AND o.apartment_code IS NOT NULL
              AND o.apartment_code <> ''
              $dateWhere
            GROUP BY o.apartment_code
            ORDER BY order_count DESC
            LIMIT 8";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dateParams);
    $topApartments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

/* ---------------- TOP PRODUCTS ---------------- */
$topProducts = [];
try {
    $sql = "SELECT o.products_json, o.total_amount
            FROM orders o
            WHERE o.status <> 'cancelled'
            $dateWhere";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dateParams);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $productStats = [];

    foreach ($rows as $r) {
        $items = json_decode($r['products_json'] ?? '[]', true);
        if (!is_array($items)) continue;

        foreach ($items as $p) {
            $name = $p['name'] ?? '';
            if ($name === '') continue;

            $qty   = (int)($p['qty'] ?? 0);
            $total = (float)($p['line_total'] ?? (($p['price'] ?? 0) * $qty));

            if (!isset($productStats[$name])) {
                $productStats[$name] = ['qty' => 0, 'revenue' => 0];
            }
            $productStats[$name]['qty']     += $qty;
            $productStats[$name]['revenue'] += $total;
        }
    }

    uasort($productStats, function ($a, $b) {
        return $b['qty'] <=> $a['qty'];
    });

    $topProducts = array_slice($productStats, 0, 10, true);
} catch (PDOException $e) {
}

/* ---------------- HELPERS ---------------- */
function rupees($n)
{
    return '₹' . number_format((int)round($n));
}

/* Label for active range */
$rangeLabels = [
    'today'  => 'Today',
    'week'   => 'Last 7 Days',
    'month'  => 'This Month',
    'custom' => 'Custom Range',
    'all'    => 'All Time',
];
$activeLabel = $rangeLabels[$range] ?? 'Today';

/* Bar chart data (modes) */
$maxModeCount = max(1, $modeStats['delivery']['count'], $modeStats['pickup']['count']);
$deliveryH    = (int)round(($modeStats['delivery']['count'] / $maxModeCount) * 100);
$pickupH      = (int)round(($modeStats['pickup']['count']   / $maxModeCount) * 100);
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

    <style>
        .rp-page {
            padding: 30px 32px 60px;
        }

        .rp-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .rp-header h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 5px;
        }

        .rp-header p {
            margin: 0;
            color: #817a71;
            font-size: 13px;
        }

        .rp-header-right {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* GST badge in header */
        .rp-gst-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: linear-gradient(135deg, #fff7e7 0%, #fdf1e2 100%);
            border: 1.5px solid #f3dca5;
            color: #8a6a1e;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .04em;
        }

        .rp-gst-badge i {
            font-size: 13px;
            color: #b8893c;
        }

        .rp-gst-badge strong {
            color: #6f5a3f;
            font-family: "DM Sans", monospace;
            letter-spacing: 1.2px;
        }

        /* =====================================================
           FILTER BAR
           ===================================================== */
        .rp-filters {
            background: #fff;
            border: 1.5px solid #eee7dc;
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }

        .rp-tabs {
            display: inline-flex;
            background: #fdfaf4;
            border: 1.5px solid #ece5da;
            border-radius: 11px;
            padding: 4px;
            gap: 4px;
            flex-wrap: wrap;
        }

        .rp-tab {
            border: none;
            background: transparent;
            padding: 8px 14px;
            font-family: "DM Sans", sans-serif;
            font-size: 11.5px;
            font-weight: 700;
            color: #6f675f;
            border-radius: 8px;
            cursor: pointer;
            transition: .2s ease;
            text-decoration: none;
            white-space: nowrap;
        }

        .rp-tab:hover {
            color: #b51f2c;
        }

        .rp-tab.active {
            background: #fff;
            color: #b51f2c;
            box-shadow: 0 4px 12px rgba(48, 41, 35, .08);
        }

        .rp-custom {
            display: none;
            align-items: center;
            gap: 8px;
            font-size: 11.5px;
            color: #817a71;
            font-weight: 600;
        }

        .rp-custom.show {
            display: inline-flex;
        }

        .rp-custom input[type="date"] {
            height: 36px;
            border: 1.5px solid #ece5da;
            background: #fff;
            border-radius: 9px;
            padding: 0 10px;
            font-family: "DM Sans", sans-serif;
            font-size: 11.5px;
            color: #302923;
            font-weight: 700;
            cursor: pointer;
            outline: none;
        }

        .rp-custom input[type="date"]:focus {
            border-color: #b51f2c;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .08);
        }

        .rp-apply-btn {
            height: 36px;
            padding: 0 16px;
            border-radius: 9px;
            border: none;
            background: #b51f2c;
            color: #fff;
            font-family: "DM Sans", sans-serif;
            font-size: 11.5px;
            font-weight: 800;
            cursor: pointer;
            transition: .2s ease;
        }

        .rp-apply-btn:hover {
            background: #8e1722;
        }

        .rp-range-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 999px;
            background: #fdf1e2;
            color: #a35a0e;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .04em;
        }

        /* =====================================================
           STAT CARDS
           ===================================================== */
        .rp-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 22px;
        }

        .rp-stat {
            background: #fff;
            border: 1.5px solid #eee7dc;
            border-radius: 18px;
            padding: 18px 20px;
            transition: .25s ease;
            position: relative;
            overflow: hidden;
        }

        .rp-stat:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 30px rgba(48, 41, 35, .08);
        }

        .rp-stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: #fdf1e2;
            color: #b8893c;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin-bottom: 14px;
        }

        .rp-stat-icon.green {
            background: #e8f6ea;
            color: #1b5e20;
        }

        .rp-stat-icon.blue {
            background: #e5eefb;
            color: #1565c0;
        }

        .rp-stat-icon.red {
            background: #fdeaea;
            color: #b51f2c;
        }

        .rp-stat-icon.gold {
            background: #fdf1e2;
            color: #b8893c;
        }

        .rp-stat-label {
            font-size: 10.5px;
            font-weight: 800;
            color: #948c82;
            text-transform: uppercase;
            letter-spacing: .8px;
            margin-bottom: 6px;
        }

        .rp-stat-value {
            font-family: 'DM Sans', sans-serif;
            font-size: 26px;
            font-weight: 700;
            color: #302923;
            line-height: 1.1;
            margin-bottom: 4px;
        }

        .rp-stat-value.red {
            color: #b51f2c;
        }

        .rp-stat-value.green {
            color: #1b5e20;
        }

        .rp-stat-value.gold {
            color: #b8893c;
        }

        .rp-stat-sub {
            font-size: 11px;
            color: #948c82;
            font-weight: 600;
        }

        /* =====================================================
           SECTION CARDS
           ===================================================== */
        .rp-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
            margin-bottom: 22px;
        }

        .rp-card {
            background: #fff;
            border: 1.5px solid #eee7dc;
            border-radius: 18px;
            padding: 20px 22px;
        }

        .rp-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .rp-card-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: "Playfair Display", serif;
            font-size: 17px;
            font-weight: 700;
            color: #302923;
            margin: 0;
        }

        .rp-card-title i {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            background: #fbe8e9;
            color: #b51f2c;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .rp-card-title.gold i {
            background: #fdf1e2;
            color: #b8893c;
        }

        .rp-card-title.green i {
            background: #e8f6ea;
            color: #1b5e20;
        }

        /* =====================================================
           TAX KPI STRIP inside tax card
           ===================================================== */
        .rp-tax-kpis {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 18px;
        }

        .rp-tax-kpi {
            padding: 14px 16px;
            border-radius: 12px;
            background: linear-gradient(135deg, #fffaf0 0%, #fff5e3 100%);
            border: 1.5px solid #f3dca5;
        }

        .rp-tax-kpi.green {
            background: linear-gradient(135deg, #f6fbf7 0%, #eef8f0 100%);
            border-color: #c8e6c9;
        }

        .rp-tax-kpi-label {
            font-size: 10px;
            font-weight: 800;
            color: #948c82;
            text-transform: uppercase;
            letter-spacing: .07em;
            margin-bottom: 4px;
        }

        .rp-tax-kpi-value {
            font-family: "DM Sans", sans-serif;
            font-size: 20px;
            font-weight: 800;
            color: #b8893c;
            line-height: 1.15;
        }

        .rp-tax-kpi.green .rp-tax-kpi-value {
            color: #1b5e20;
        }

        .rp-tax-kpi-sub {
            font-size: 10.5px;
            color: #948c82;
            font-weight: 600;
            margin-top: 3px;
        }

        /* Tax type rows */
        .rp-tax-type-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 16px;
        }

        .rp-tax-type-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            background: #fffdf9;
            border: 1px solid #f0ebe4;
        }

        .rp-tax-type-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .04em;
            padding: 3px 9px;
            border-radius: 6px;
        }

        .rp-tax-type-pill.exclusive {
            background: #fdeaea;
            color: #b51f2c;
        }

        .rp-tax-type-pill.inclusive {
            background: #e8f6ea;
            color: #1b5e20;
        }

        .rp-tax-type-row .count {
            font-size: 11px;
            color: #948c82;
            font-weight: 700;
        }

        .rp-tax-type-row .amt {
            font-family: "DM Sans", sans-serif;
            font-size: 14px;
            font-weight: 800;
            color: #b8893c;
        }

        /* Taxed orders list */
        .rp-tax-order-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 320px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .rp-tax-order-list::-webkit-scrollbar {
            width: 6px;
        }

        .rp-tax-order-list::-webkit-scrollbar-thumb {
            background: #e0d8cd;
            border-radius: 4px;
        }

        .rp-tax-order {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border: 1px solid #f0ebe4;
            border-radius: 11px;
            background: #fffdf9;
            transition: .15s ease;
        }

        .rp-tax-order:hover {
            border-color: #d98a91;
            background: #fff5f5;
        }

        .rp-tax-order-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: #fdf1e2;
            color: #b8893c;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }

        .rp-tax-order-info {
            flex: 1;
            min-width: 0;
        }

        .rp-tax-order-code {
            font-size: 12px;
            font-weight: 800;
            color: #302923;
            margin: 0;
            letter-spacing: .04em;
        }

        .rp-tax-order-meta {
            font-size: 10.5px;
            color: #948c82;
            margin: 2px 0 0;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .rp-tax-order-right {
            text-align: right;
            flex-shrink: 0;
        }

        .rp-tax-order-tax {
            font-family: "DM Sans", sans-serif;
            font-size: 13px;
            font-weight: 800;
            color: #b8893c;
            margin: 0;
        }

        .rp-tax-order-type {
            font-size: 9.5px;
            color: #948c82;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin: 2px 0 0;
        }

        /* Empty state */
        .rp-empty {
            padding: 40px 20px;
            text-align: center;
            color: #948c82;
            font-size: 12.5px;
        }

        .rp-empty i {
            font-size: 36px;
            color: #ece5da;
            display: block;
            margin-bottom: 10px;
        }

        /* =====================================================
           MODE CHART (existing)
           ===================================================== */
        .rp-chart {
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            gap: 20px;
            height: 220px;
            padding: 10px 0 0;
            margin-bottom: 12px;
            border-bottom: 1px dashed #ece5da;
        }

        .rp-chart-col {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            height: 100%;
            max-width: 130px;
            position: relative;
        }

        .rp-chart-value {
            font-family: 'DM Sans', sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: #302923;
            margin-bottom: 8px;
            line-height: 1;
        }

        .rp-chart-bar {
            width: 100%;
            border-radius: 12px 12px 0 0;
            position: relative;
            transition: height 1s cubic-bezier(.2, .8, .3, 1.1);
            height: 0;
            min-height: 6px;
            box-shadow: 0 8px 20px rgba(48, 41, 35, .12);
        }

        .rp-chart-bar.delivery {
            background: linear-gradient(180deg, #4a90e2 0%, #1565c0 100%);
        }

        .rp-chart-bar.pickup {
            background: linear-gradient(180deg, #f7c95c 0%, #b8893c 100%);
        }

        .rp-chart-bar::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 12px 12px 0 0;
            background: linear-gradient(180deg, rgba(255, 255, 255, .35) 0%, rgba(255, 255, 255, 0) 40%);
            pointer-events: none;
        }

        .rp-chart-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding-top: 10px;
            font-size: 11.5px;
            font-weight: 800;
            color: #302923;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .rp-chart-label .sub {
            font-size: 10.5px;
            color: #948c82;
            font-weight: 600;
            text-transform: none;
            letter-spacing: 0;
        }

        .rp-mode-foot {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .rp-mode-foot-item {
            flex: 1;
            padding: 12px 14px;
            border-radius: 12px;
            background: #fdfaf4;
            border: 1px solid #f0ebe4;
            text-align: center;
        }

        .rp-mode-foot-item .lbl {
            font-size: 10.5px;
            font-weight: 800;
            color: #948c82;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .rp-mode-foot-item .val {
            font-family: 'DM Sans', sans-serif;
            font-size: 16px;
            font-weight: 700;
            color: #302923;
            margin-top: 4px;
        }

        /* =====================================================
           PAYMENT BARS
           ===================================================== */
        .rp-pay-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px dashed #f0ebe4;
        }

        .rp-pay-row:last-child {
            border-bottom: 0;
        }

        .rp-pay-label {
            min-width: 90px;
            font-size: 12px;
            font-weight: 800;
        }

        .rp-pay-label.paid {
            color: #1b5e20;
        }

        .rp-pay-label.unpaid {
            color: #a35a0e;
        }

        .rp-pay-bar-wrap {
            flex: 1;
            height: 12px;
            background: #f5efe5;
            border-radius: 999px;
            overflow: hidden;
            position: relative;
        }

        .rp-pay-bar {
            height: 100%;
            border-radius: 999px;
            transition: width .9s cubic-bezier(.2, .8, .3, 1);
            box-shadow: 0 4px 10px rgba(48, 41, 35, .08);
        }

        .rp-pay-bar.paid {
            background: linear-gradient(90deg, #1f7a3d, #2e7d32);
        }

        .rp-pay-bar.unpaid {
            background: linear-gradient(90deg, #b8893c, #d9a44a);
        }

        .rp-pay-count {
            font-size: 12px;
            font-weight: 800;
            color: #302923;
            min-width: 40px;
            text-align: right;
        }

        /* =====================================================
           TOP PRODUCTS / BOYS / APARTMENTS (existing)
           ===================================================== */
        .rp-prod-list,
        .rp-boy-list,
        .rp-apt-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .rp-prod {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border: 1px solid #f0ebe4;
            border-radius: 11px;
            background: #fffdf9;
            transition: .15s ease;
        }

        .rp-prod:hover,
        .rp-boy:hover,
        .rp-apt:hover {
            border-color: #d98a91;
            background: #fff5f5;
        }

        .rp-prod-rank {
            width: 26px;
            height: 26px;
            border-radius: 8px;
            background: #fbe8e9;
            color: #b51f2c;
            font-size: 11px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .rp-prod:nth-child(1) .rp-prod-rank {
            background: #b8893c;
            color: #fff;
        }

        .rp-prod:nth-child(2) .rp-prod-rank {
            background: #c9a86a;
            color: #fff;
        }

        .rp-prod:nth-child(3) .rp-prod-rank {
            background: #d9c08a;
            color: #fff;
        }

        .rp-prod-info {
            flex: 1;
            min-width: 0;
        }

        .rp-prod-name {
            font-size: 12.5px;
            font-weight: 700;
            color: #302923;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .rp-prod-meta {
            font-size: 11px;
            color: #948c82;
            margin: 2px 0 0;
            font-weight: 600;
        }

        .rp-prod-rev {
            font-family: "Playfair Display", serif;
            font-size: 13.5px;
            font-weight: 700;
            color: #b51f2c;
            white-space: nowrap;
        }

        .rp-boy {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid #f0ebe4;
            border-radius: 11px;
            background: #fffdf9;
            transition: .15s ease;
        }

        .rp-boy-avatar {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 800;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(181, 31, 44, .2);
        }

        .rp-boy-info {
            flex: 1;
            min-width: 0;
        }

        .rp-boy-name {
            font-size: 12.5px;
            font-weight: 700;
            color: #302923;
            margin: 0;
        }

        .rp-boy-meta {
            font-size: 11px;
            color: #948c82;
            margin: 2px 0 0;
            font-weight: 600;
        }

        .rp-boy-stats {
            text-align: right;
        }

        .rp-boy-count {
            font-family: "Playfair Display", serif;
            font-size: 15px;
            font-weight: 700;
            color: #b51f2c;
            margin: 0;
        }

        .rp-boy-delivered {
            font-size: 10.5px;
            color: #1b5e20;
            font-weight: 800;
            margin: 2px 0 0;
        }

        .rp-apt {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid #f0ebe4;
            border-radius: 11px;
            background: #fffdf9;
            transition: .15s ease;
        }

        .rp-apt-icon {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            background: #fdf1e2;
            color: #a35a0e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .rp-apt-info {
            flex: 1;
            min-width: 0;
        }

        .rp-apt-name {
            font-size: 12.5px;
            font-weight: 700;
            color: #302923;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .rp-apt-meta {
            font-size: 11px;
            color: #948c82;
            margin: 2px 0 0;
            font-weight: 600;
        }

        .rp-apt-stats {
            text-align: right;
        }

        .rp-apt-count {
            font-family: "Playfair Display", serif;
            font-size: 15px;
            font-weight: 700;
            color: #b51f2c;
            margin: 0;
        }

        .rp-apt-rev {
            font-size: 10.5px;
            color: #948c82;
            font-weight: 700;
            margin: 2px 0 0;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .rp-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .rp-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .rp-page {
                padding: 20px 15px 40px;
            }

            .rp-stats {
                grid-template-columns: 1fr;
            }

            .rp-filters {
                flex-direction: column;
                align-items: stretch;
            }

            .rp-tabs {
                justify-content: center;
            }

            .rp-chart {
                height: 180px;
                gap: 12px;
            }

            .rp-tax-kpis {
                grid-template-columns: 1fr;
            }
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
                    <div class="admin-avatar">A</div>
                    <div>
                        <div class="admin-name">Admin</div>
                        <div class="admin-role">Store Manager</div>
                    </div>
                </div>
            </div>
        </header>


        <div class="rp-page">

            <div class="rp-header">
                <div>
                    <h1>Reports</h1>
                    <p>Overview of orders, delivery, revenue and GST.</p>
                </div>

                <div class="rp-header-right">
                    <?php if ($gstNumber !== ''): ?>
                        <span class="rp-gst-badge">
                            <i class="bi bi-receipt-cutoff"></i>
                            GSTIN: <strong><?= htmlspecialchars($gstNumber) ?></strong>
                        </span>
                    <?php endif; ?>

                    <span class="rp-range-label">
                        <i class="bi bi-calendar3"></i>
                        <?= htmlspecialchars($activeLabel) ?>
                    </span>
                </div>
            </div>


            <!-- ================= FILTERS ================= -->
            <form class="rp-filters" method="GET" id="rpFilterForm">

                <div class="rp-tabs">
                    <a href="?range=today" class="rp-tab <?= $range === 'today'  ? 'active' : '' ?>">Today</a>
                    <a href="?range=week" class="rp-tab <?= $range === 'week'   ? 'active' : '' ?>">Last 7 Days</a>
                    <a href="?range=month" class="rp-tab <?= $range === 'month'  ? 'active' : '' ?>">This Month</a>
                    <a href="?range=all" class="rp-tab <?= $range === 'all'    ? 'active' : '' ?>">All Time</a>
                    <a href="#" class="rp-tab <?= $range === 'custom' ? 'active' : '' ?>" id="rpCustomTab">Custom</a>
                </div>

                <div class="rp-custom <?= $range === 'custom' ? 'show' : '' ?>" id="rpCustomBox">
                    <input type="hidden" name="range" value="<?= $range === 'custom' ? 'custom' : '' ?>" id="rpRangeInput">
                    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
                    <span>to</span>
                    <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
                    <button type="submit" class="rp-apply-btn">Apply</button>
                </div>

            </form>


            <!-- ================= STAT CARDS ================= -->
            <div class="rp-stats">

                <div class="rp-stat">
                    <div class="rp-stat-icon green">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <div class="rp-stat-label">Total Revenue</div>
                    <div class="rp-stat-value green"><?= rupees($stats['revenue']) ?></div>
                    <div class="rp-stat-sub">From <?= $stats['orders'] ?> order<?= $stats['orders'] === 1 ? '' : 's' ?></div>
                </div>

                <div class="rp-stat">
                    <div class="rp-stat-icon gold">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <div class="rp-stat-label">GST Collected</div>
                    <div class="rp-stat-value gold"><?= rupees($stats['tax_total']) ?></div>
                    <div class="rp-stat-sub">
                        <?= $stats['tax_orders'] ?> order<?= $stats['tax_orders'] === 1 ? '' : 's' ?>
                        <?= $taxStatus === 1 ? ' · ' . (int)$taxRate . '% ' . htmlspecialchars($taxType) : ' · tax disabled' ?>
                    </div>
                </div>

                <div class="rp-stat">
                    <div class="rp-stat-icon">
                        <i class="bi bi-bag-check"></i>
                    </div>
                    <div class="rp-stat-label">Total Orders</div>
                    <div class="rp-stat-value"><?= $stats['orders'] ?></div>
                    <div class="rp-stat-sub"><?= $stats['delivered'] ?> delivered · <?= $stats['pending'] ?> pending</div>
                </div>

                <div class="rp-stat">
                    <div class="rp-stat-icon blue">
                        <i class="bi bi-truck"></i>
                    </div>
                    <div class="rp-stat-label">Delivery / Pickup</div>
                    <div class="rp-stat-value"><?= $stats['delivery'] ?> / <?= $stats['pickup'] ?></div>
                    <div class="rp-stat-sub">
                        <?= rupees($modeStats['delivery']['revenue']) ?> · <?= rupees($modeStats['pickup']['revenue']) ?>
                    </div>
                </div>

            </div>


            <!-- ================= GST / TAX BREAKDOWN ================= -->
            <div class="rp-grid">

                <!-- Tax summary card -->
                <div class="rp-card">
                    <div class="rp-card-head">
                        <h2 class="rp-card-title gold">
                            <i class="bi bi-percent"></i>
                            GST Summary
                        </h2>
                    </div>

                    <div class="rp-tax-kpis">
                        <div class="rp-tax-kpi">
                            <div class="rp-tax-kpi-label">Total GST Collected</div>
                            <div class="rp-tax-kpi-value"><?= rupees($stats['tax_total']) ?></div>
                            <div class="rp-tax-kpi-sub">
                                From <?= $stats['tax_orders'] ?> taxed order<?= $stats['tax_orders'] === 1 ? '' : 's' ?>
                            </div>
                        </div>

                        <div class="rp-tax-kpi green">
                            <div class="rp-tax-kpi-label">Net Sales (excl. GST)</div>
                            <div class="rp-tax-kpi-value"><?= rupees($stats['net_sales']) ?></div>
                            <div class="rp-tax-kpi-sub">Revenue minus GST</div>
                        </div>
                    </div>

                    <?php if ($taxStatus === 1): ?>
                        <div style="padding:10px 12px;border-radius:10px;background:#fdf7ec;border:1px dashed #e8d5a8;margin-bottom:16px;font-size:11px;color:#8a6a1e;font-weight:700;">
                            <i class="bi bi-info-circle"></i>
                            Tax is currently <strong>ENABLED</strong> at
                            <strong><?= (int)$taxRate ?>%</strong>
                            (<strong><?= htmlspecialchars(ucfirst($taxType)) ?></strong>).
                            <?php if ($gstNumber !== ''): ?>
                                GSTIN: <strong><?= htmlspecialchars($gstNumber) ?></strong>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="padding:10px 12px;border-radius:10px;background:#f4efe8;border:1px dashed #d8c9b8;margin-bottom:16px;font-size:11px;color:#6f5a3f;font-weight:700;">
                            <i class="bi bi-pause-circle"></i>
                            Tax is currently <strong>DISABLED</strong>. No GST is being applied to new orders.
                        </div>
                    <?php endif; ?>

                    <!-- Breakdown by tax type -->
                    <div class="rp-tax-type-list">
                        <div class="rp-tax-type-row">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <span class="rp-tax-type-pill exclusive">
                                    <i class="bi bi-plus-circle-fill"></i> EXCLUSIVE
                                </span>
                                <span class="count"><?= (int)$taxBreakdown['exclusive']['count'] ?> order<?= $taxBreakdown['exclusive']['count'] === 1 ? '' : 's' ?></span>
                            </div>
                            <span class="amt"><?= rupees($taxBreakdown['exclusive']['tax']) ?></span>
                        </div>

                        <div class="rp-tax-type-row">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <span class="rp-tax-type-pill inclusive">
                                    <i class="bi bi-check-circle-fill"></i> INCLUSIVE
                                </span>
                                <span class="count"><?= (int)$taxBreakdown['inclusive']['count'] ?> order<?= $taxBreakdown['inclusive']['count'] === 1 ? '' : 's' ?></span>
                            </div>
                            <span class="amt"><?= rupees($taxBreakdown['inclusive']['tax']) ?></span>
                        </div>
                    </div>
                </div>


                <!-- Recent taxed orders -->
                <div class="rp-card">
                    <div class="rp-card-head">
                        <h2 class="rp-card-title">
                            <i class="bi bi-receipt-cutoff"></i>
                            Recent Taxed Orders
                        </h2>
                    </div>

                    <?php if (empty($taxedOrders)): ?>
                        <div class="rp-empty">
                            <i class="bi bi-inbox"></i>
                            No taxed orders in this range.
                        </div>
                    <?php else: ?>
                        <div class="rp-tax-order-list">
                            <?php foreach ($taxedOrders as $t): ?>
                                <div class="rp-tax-order">
                                    <div class="rp-tax-order-icon">
                                        <i class="bi bi-receipt"></i>
                                    </div>
                                    <div class="rp-tax-order-info">
                                        <p class="rp-tax-order-code"><?= htmlspecialchars($t['order_code']) ?></p>
                                        <p class="rp-tax-order-meta">
                                            <?= htmlspecialchars($t['customer_name']) ?> ·
                                            Subtotal <?= rupees($t['subtotal']) ?> ·
                                            Total <?= rupees($t['total_amount']) ?>
                                        </p>
                                    </div>
                                    <div class="rp-tax-order-right">
                                        <p class="rp-tax-order-tax">+<?= rupees($t['tax_amount']) ?></p>
                                        <p class="rp-tax-order-type">
                                            <?= (int)round((float)$t['tax_rate']) ?>% <?= htmlspecialchars($t['tax_type']) ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>


            <!-- ================= MODE + PAYMENT ================= -->
            <div class="rp-grid">

                <!-- Mode breakdown with animated bar chart -->
                <div class="rp-card">
                    <div class="rp-card-head">
                        <h2 class="rp-card-title">
                            <i class="bi bi-bar-chart-line"></i>
                            Order Modes
                        </h2>
                    </div>

                    <div class="rp-chart" id="rpModeChart">
                        <div class="rp-chart-col">
                            <div class="rp-chart-value"><?= $modeStats['delivery']['count'] ?></div>
                            <div class="rp-chart-bar delivery"
                                data-h="<?= $deliveryH ?>"
                                style="height: 0;"></div>
                            <div class="rp-chart-label">
                                <span>Delivery</span>
                                <span class="sub"><?= rupees($modeStats['delivery']['revenue']) ?></span>
                            </div>
                        </div>
                        <div class="rp-chart-col">
                            <div class="rp-chart-value"><?= $modeStats['pickup']['count'] ?></div>
                            <div class="rp-chart-bar pickup"
                                data-h="<?= $pickupH ?>"
                                style="height: 0;"></div>
                            <div class="rp-chart-label">
                                <span>Pickup</span>
                                <span class="sub"><?= rupees($modeStats['pickup']['revenue']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="rp-mode-foot">
                        <div class="rp-mode-foot-item">
                            <div class="lbl">Delivery Revenue</div>
                            <div class="val"><?= rupees($modeStats['delivery']['revenue']) ?></div>
                        </div>
                        <div class="rp-mode-foot-item">
                            <div class="lbl">Delivery GST</div>
                            <div class="val" style="color:#b8893c;"><?= rupees($modeStats['delivery']['tax']) ?></div>
                        </div>
                        <div class="rp-mode-foot-item">
                            <div class="lbl">Pickup Revenue</div>
                            <div class="val"><?= rupees($modeStats['pickup']['revenue']) ?></div>
                        </div>
                        <div class="rp-mode-foot-item">
                            <div class="lbl">Pickup GST</div>
                            <div class="val" style="color:#b8893c;"><?= rupees($modeStats['pickup']['tax']) ?></div>
                        </div>
                    </div>
                </div>


                <!-- Payment breakdown -->
                <div class="rp-card">
                    <div class="rp-card-head">
                        <h2 class="rp-card-title green">
                            <i class="bi bi-credit-card"></i>
                            Payment Status
                        </h2>
                    </div>

                    <?php
                    $totalPay = max(1, $stats['paid'] + $stats['unpaid']);
                    $paidPct   = round(($stats['paid']   / $totalPay) * 100);
                    $unpaidPct = round(($stats['unpaid'] / $totalPay) * 100);
                    ?>

                    <div class="rp-pay-row">
                        <span class="rp-pay-label paid">Paid</span>
                        <div class="rp-pay-bar-wrap">
                            <div class="rp-pay-bar paid" data-w="<?= $paidPct ?>" style="width: 0;"></div>
                        </div>
                        <span class="rp-pay-count"><?= $stats['paid'] ?></span>
                    </div>

                    <div class="rp-pay-row">
                        <span class="rp-pay-label unpaid">Unpaid</span>
                        <div class="rp-pay-bar-wrap">
                            <div class="rp-pay-bar unpaid" data-w="<?= $unpaidPct ?>" style="width: 0;"></div>
                        </div>
                        <span class="rp-pay-count"><?= $stats['unpaid'] ?></span>
                    </div>

                </div>

            </div>


            <!-- ================= TOP PRODUCTS + TOP BOYS ================= -->
            <div class="rp-grid">

                <!-- Top products -->
                <div class="rp-card">
                    <div class="rp-card-head">
                        <h2 class="rp-card-title">
                            <i class="bi bi-trophy"></i>
                            Top Products
                        </h2>
                    </div>

                    <?php if (empty($topProducts)): ?>
                        <div class="rp-empty">
                            <i class="bi bi-inbox"></i>
                            No product sales in this range.
                        </div>
                    <?php else: ?>
                        <div class="rp-prod-list">
                            <?php $rank = 1;
                            foreach ($topProducts as $name => $p): ?>
                                <div class="rp-prod">
                                    <div class="rp-prod-rank"><?= $rank ?></div>
                                    <div class="rp-prod-info">
                                        <p class="rp-prod-name"><?= htmlspecialchars($name) ?></p>
                                        <p class="rp-prod-meta"><?= (int)$p['qty'] ?> sold</p>
                                    </div>
                                    <div class="rp-prod-rev"><?= rupees($p['revenue']) ?></div>
                                </div>
                            <?php $rank++;
                            endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>


                <!-- Top delivery boys -->
                <div class="rp-card">
                    <div class="rp-card-head">
                        <h2 class="rp-card-title">
                            <i class="bi bi-people"></i>
                            Top Delivery Boys
                        </h2>
                    </div>

                    <?php if (empty($topBoys)): ?>
                        <div class="rp-empty">
                            <i class="bi bi-inbox"></i>
                            No delivery activity in this range.
                        </div>
                    <?php else: ?>
                        <div class="rp-boy-list">
                            <?php foreach ($topBoys as $b):
                                $initial = strtoupper(substr($b['full_name'] ?: '?', 0, 1));
                            ?>
                                <div class="rp-boy">
                                    <div class="rp-boy-avatar"><?= htmlspecialchars($initial) ?></div>
                                    <div class="rp-boy-info">
                                        <p class="rp-boy-name"><?= htmlspecialchars($b['full_name']) ?></p>
                                        <p class="rp-boy-meta">#<?= htmlspecialchars($b['delivery_code']) ?> · <?= rupees($b['revenue']) ?></p>
                                    </div>
                                    <div class="rp-boy-stats">
                                        <p class="rp-boy-count"><?= (int)$b['order_count'] ?></p>
                                        <p class="rp-boy-delivered"><?= (int)$b['delivered_count'] ?> delivered</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>


            <!-- ================= TOP APARTMENTS ================= -->
            <div class="rp-card">
                <div class="rp-card-head">
                    <h2 class="rp-card-title">
                        <i class="bi bi-buildings"></i>
                        Top Apartments
                    </h2>
                </div>

                <?php if (empty($topApartments)): ?>
                    <div class="rp-empty">
                        <i class="bi bi-inbox"></i>
                        No delivery activity in this range.
                    </div>
                <?php else: ?>
                    <div class="rp-apt-list">
                        <?php foreach ($topApartments as $a): ?>
                            <div class="rp-apt">
                                <div class="rp-apt-icon">
                                    <i class="bi bi-building"></i>
                                </div>
                                <div class="rp-apt-info">
                                    <p class="rp-apt-name"><?= htmlspecialchars($a['apartment_name'] ?: '—') ?></p>
                                    <p class="rp-apt-meta">#<?= htmlspecialchars($a['apartment_code'] ?: '') ?></p>
                                </div>
                                <div class="rp-apt-stats">
                                    <p class="rp-apt-count"><?= (int)$a['order_count'] ?></p>
                                    <p class="rp-apt-rev"><?= rupees($a['revenue']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </main>


    <script>
        /* Custom tab toggle */
        (function() {
            var tab = document.getElementById("rpCustomTab");
            var box = document.getElementById("rpCustomBox");
            var rangeInput = document.getElementById("rpRangeInput");
            if (!tab || !box) return;

            tab.addEventListener("click", function(e) {
                e.preventDefault();
                box.classList.add("show");
                rangeInput.value = "custom";
            });
        })();

        /* Animate bar chart when it scrolls into view */
        (function() {
            var chart = document.getElementById("rpModeChart");
            if (!chart) return;

            function animate() {
                chart.querySelectorAll(".rp-chart-bar").forEach(function(bar) {
                    var h = Number(bar.dataset.h || 0);
                    bar.style.height = h + "%";
                });
            }

            if ("IntersectionObserver" in window) {
                var io = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            setTimeout(animate, 60);
                            io.disconnect();
                        }
                    });
                }, {
                    threshold: 0.2
                });
                io.observe(chart);
            } else {
                setTimeout(animate, 200);
            }
        })();

        /* Animate payment bars */
        (function() {
            var bars = document.querySelectorAll(".rp-pay-bar");
            if (!bars.length) return;

            function animate() {
                bars.forEach(function(b) {
                    var w = Number(b.dataset.w || 0);
                    b.style.width = w + "%";
                });
            }

            if ("IntersectionObserver" in window) {
                var io = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            setTimeout(animate, 80);
                            io.disconnect();
                        }
                    });
                }, {
                    threshold: 0.1
                });
                bars.forEach(function(b) {
                    io.observe(b);
                });
            } else {
                setTimeout(animate, 200);
            }
        })();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL ?>js/main.js"></script>

</body>

</html>