<?php
require_once './config/config.php';
require_once './config/function.php';

/* ---------------- AUTH ---------------- */
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    header('Location: login.php');
    exit;
}

$settings = getSettings($pdo);
$siteName = $settings['username'] ?? 'Mrs Mill@';

/* =========================================================
   HELPERS
   ========================================================= */
function dbDateRange(string $filter, string $from, string $to, string $col = 'o.created_at'): array
{
    $sql = '';
    $params = [];
    if ($filter === 'today')     $sql = "DATE($col) = CURDATE()";
    elseif ($filter === 'yesterday') $sql = "DATE($col) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
    elseif ($filter === 'week')  $sql = "$col >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)";
    elseif ($filter === 'month') $sql = "YEAR($col) = YEAR(CURDATE()) AND MONTH($col) = MONTH(CURDATE())";
    elseif ($filter === 'custom') {
        $parts = [];
        if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $parts[] = "DATE($col) >= ?";
            $params[] = $from;
        }
        if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $parts[] = "DATE($col) <= ?";
            $params[] = $to;
        }
        $sql = $parts ? implode(' AND ', $parts) : '';
    }
    return [$sql, $params];
}

/* =========================================================
   GLOBAL FILTERS (affect the summary numbers on each card)
   ========================================================= */
$gFilter = $_GET['filter'] ?? 'all';
$gFrom   = $_GET['from']   ?? '';
$gTo     = $_GET['to']     ?? '';

[$dateSql, $dateParams] = dbDateRange($gFilter, $gFrom, $gTo, 'o.created_at');

/* =========================================================
   DELIVERY BOYS + AGGREGATE COUNTS
   ========================================================= */
$boys = [];
try {
    $where = "WHERE b.status = 1";
    if ($dateSql !== '') $where .= " AND $dateSql";

    $sql = "SELECT
                b.id,
                b.delivery_code,
                b.full_name,
                b.mobile_number,
                b.profile_image,
                COUNT(o.id) AS total_orders,
                SUM(CASE WHEN o.status = 'delivered'  THEN 1 ELSE 0 END) AS delivered_orders,
                SUM(CASE WHEN o.status = 'cancelled'  THEN 1 ELSE 0 END) AS cancelled_orders,
                SUM(CASE WHEN o.status NOT IN ('delivered','cancelled') THEN 1 ELSE 0 END) AS pending_orders,
                SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END) AS revenue
            FROM delivery_boys b
            LEFT JOIN orders o
                ON o.delivery_boy_id = b.id
               AND o.delivery_mode = 'delivery'
            $where
            GROUP BY b.id
            ORDER BY b.full_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($dateParams);
    $boys = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $boys = [];
}

/* Overall totals for the page header */
$overall = [
    'boys'      => count($boys),
    'pending'   => 0,
    'delivered' => 0,
    'cancelled' => 0,
    'revenue'   => 0.0,
];
foreach ($boys as $b) {
    $overall['pending']   += (int)$b['pending_orders'];
    $overall['delivered'] += (int)$b['delivered_orders'];
    $overall['cancelled'] += (int)$b['cancelled_orders'];
    $overall['revenue']   += (float)$b['revenue'];
}

/* =========================================================
   AJAX: return one delivery boy's order history
   ========================================================= */
if (($_GET['action'] ?? '') === 'history') {
    header('Content-Type: application/json; charset=utf-8');

    $boyId  = (int)($_GET['boy_id'] ?? 0);
    $filter = $_GET['filter'] ?? 'all';
    $status = $_GET['status'] ?? 'all';
    $q      = trim($_GET['q'] ?? '');

    if ($boyId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid delivery boy.']);
        exit;
    }

    [$dateSql, $dateParams] = dbDateRange($filter, '', '', 'o.created_at');

    $where  = ["o.delivery_boy_id = ?", "o.delivery_mode = 'delivery'"];
    $params = [$boyId];

    if ($dateSql !== '') {
        $where[] = $dateSql;
        $params = array_merge($params, $dateParams);
    }

    if (in_array($status, ['pending', 'confirmed', 'processing', 'delivered', 'cancelled'], true)) {
        $where[] = "o.status = ?";
        $params[] = $status;
    }

    if ($q !== '') {
        $where[] = "(o.order_code LIKE ? OR c.full_name LIKE ? OR c.mobile_number LIKE ? OR o.address_json LIKE ?)";
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like);
    }

    $sql = "SELECT
                o.id,
                o.order_code,
                o.total_amount,
                o.status,
                o.payment_status,
                o.created_at,
                o.address_type,
                o.address_json,
                c.full_name     AS customer_name,
                c.mobile_number AS customer_mobile
            FROM orders o
            LEFT JOIN customers c ON c.customer_id = o.customer_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY o.id DESC
            LIMIT 500";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            $json = json_decode($r['address_json'] ?? '', true) ?: [];
            if ($r['address_type'] === 'individual_house') {
                $label = $json['full_address'] ?? 'Individual House';
            } else {
                $label = ($json['apartment_name'] ?? 'Apartment');
                if (!empty($json['division_label'])) $label .= ' · ' . $json['division_label'];
            }

            $out[] = [
                'id'              => (int)$r['id'],
                'order_code'      => $r['order_code'],
                'customer_name'   => $r['customer_name']   ?: '—',
                'customer_mobile' => $r['customer_mobile'] ?: '—',
                'addr_label'      => $label,
                'total_amount'    => (float)$r['total_amount'],
                'status'          => $r['status'],
                'payment_status'  => $r['payment_status'],
                'created_at'      => date('d M Y · h:i A', strtotime($r['created_at'])),
            ];
        }

        echo json_encode(['success' => true, 'data' => $out]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Server error.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include './includes/head.php'; ?>

    <style>
        /* ---------- Page shell ---------- */
        .dbr-page {
            padding: 28px 30px 40px;
        }

        .dbr-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .dbr-head h1 {
            font-family: "Playfair Display", serif;
            font-size: 27px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 4px;
        }

        .dbr-head p {
            margin: 0;
            font-size: 12.5px;
            color: #817a71;
        }

        /* ---------- Stats ---------- */
        .dbr-stats {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .dbr-stat {
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 14px;
            padding: 14px 16px;
        }

        .dbr-stat-label {
            font-size: 10.5px;
            font-weight: 800;
            color: #948c82;
            letter-spacing: .5px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .dbr-stat-value {
            font-family: "Playfair Display", serif;
            font-size: 22px;
            font-weight: 700;
            color: #302923;
            line-height: 1;
        }

        .dbr-stat.green .dbr-stat-value {
            color: #2e7d32;
        }

        .dbr-stat.red .dbr-stat-value {
            color: #b51f2c;
        }

        /* ---------- Global filter bar ---------- */
        .dbr-filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 18px;
        }

        .dbr-tabs {
            display: inline-flex;
            background: #fdfaf4;
            border: 1.5px solid #ece5da;
            border-radius: 11px;
            padding: 4px;
            gap: 4px;
            flex-wrap: wrap;
        }

        .dbr-tab {
            border: none;
            background: transparent;
            padding: 8px 14px;
            font-family: "DM Sans", sans-serif;
            font-size: 11.5px;
            font-weight: 700;
            color: #6f675f;
            border-radius: 8px;
            cursor: pointer;
            white-space: nowrap;
            transition: .15s ease;
        }

        .dbr-tab:hover {
            color: #b51f2c;
        }

        .dbr-tab.active {
            background: #fff;
            color: #b51f2c;
            box-shadow: 0 4px 12px rgba(48, 41, 35, .08);
        }

        .dbr-daterange {
            display: none;
            align-items: center;
            gap: 8px;
            height: 40px;
            padding: 0 10px;
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 11px;
            font-size: 11.5px;
            color: #817a71;
        }

        .dbr-daterange.show {
            display: inline-flex;
        }

        .dbr-daterange input[type="date"] {
            border: none;
            background: transparent;
            font-family: inherit;
            font-size: 11.5px;
            font-weight: 700;
            color: #302923;
            cursor: pointer;
            outline: none;
        }

        /* ---------- Boy cards grid ---------- */
        .dbr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 14px;
        }

        .dbr-card {
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 16px;
            padding: 16px 18px;
            cursor: pointer;
            transition: .18s ease;
            display: flex;
            flex-direction: column;
            gap: 12px;
            position: relative;
            overflow: hidden;
        }

        .dbr-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #b51f2c 0%, #8e1722 100%);
            opacity: 0;
            transition: .2s;
        }

        .dbr-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 26px -10px rgba(48, 41, 35, .15);
            border-color: #d98a91;
        }

        .dbr-card:hover::before {
            opacity: 1;
        }

        .dbr-card-head {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .dbr-avatar {
            width: 46px;
            height: 46px;
            border-radius: 13px;
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 800;
            flex-shrink: 0;
            box-shadow: 0 6px 16px rgba(181, 31, 44, .22);
            overflow: hidden;
        }

        .dbr-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .dbr-name {
            font-size: 14px;
            font-weight: 800;
            color: #302923;
            margin: 0;
            line-height: 1.3;
        }

        .dbr-code {
            font-size: 10.5px;
            color: #948c82;
            font-weight: 700;
            margin-top: 2px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .dbr-code i {
            color: #b51f2c;
            font-size: 10px;
        }

        .dbr-card-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }

        .dbr-kv {
            background: #fdfaf4;
            border-radius: 10px;
            padding: 8px 10px;
            text-align: center;
        }

        .dbr-kv-lbl {
            font-size: 9px;
            font-weight: 800;
            color: #948c82;
            letter-spacing: .4px;
            text-transform: uppercase;
        }

        .dbr-kv-val {
            font-size: 15px;
            font-weight: 800;
            color: #302923;
            margin-top: 2px;
            line-height: 1;
        }

        .dbr-kv.k-pending .dbr-kv-val {
            color: #a35a0e;
        }

        .dbr-kv.k-delivered .dbr-kv-val {
            color: #2e7d32;
        }

        .dbr-kv.k-cancel .dbr-kv-val {
            color: #b51f2c;
        }

        .dbr-card-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 10px;
            border-top: 1px solid #f5efe5;
            font-size: 11px;
            color: #817a71;
        }

        .dbr-card-foot strong {
            color: #b51f2c;
            font-weight: 800;
        }

        .dbr-card-go {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #f7f2ec;
            color: #6f675f;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            transition: .2s;
        }

        .dbr-card:hover .dbr-card-go {
            background: #b51f2c;
            color: #fff;
            transform: translateX(3px);
        }

        /* ---------- Popup (modal) ---------- */
        .dbr-overlay {
            position: fixed;
            inset: 0;
            background: rgba(48, 41, 35, .5);
            backdrop-filter: blur(3px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
            opacity: 0;
            transition: opacity .2s ease;
        }

        .dbr-overlay.show {
            display: flex;
            opacity: 1;
        }

        .dbr-modal {
            background: #fff;
            border-radius: 18px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(0, 0, 0, .25);
            transform: scale(.95);
            transition: transform .22s cubic-bezier(.3, 1.2, .5, 1);
            display: flex;
            flex-direction: column;
        }

        .dbr-overlay.show .dbr-modal {
            transform: scale(1);
        }

        .dbr-modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 20px;
            background: linear-gradient(135deg, #fbeaea 0%, #f5d6d8 100%);
            border-bottom: 1px solid #f1c8cc;
        }

        .dbr-modal-head-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .dbr-modal-head-title {
            font-family: "Playfair Display", serif;
            font-size: 17px;
            font-weight: 700;
            color: #302923;
            margin: 0;
        }

        .dbr-modal-head-sub {
            font-size: 11px;
            color: #8a7f73;
            font-weight: 700;
            margin-top: 2px;
        }

        .dbr-close {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            border: 1.5px solid #e4ddd3;
            background: #fff;
            color: #6f675f;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: .15s;
        }

        .dbr-close:hover {
            color: #b51f2c;
            border-color: #d98a91;
        }

        .dbr-modal-tools {
            display: flex;
            gap: 8px;
            padding: 12px 20px;
            border-bottom: 1px solid #f5efe5;
            flex-wrap: wrap;
            align-items: center;
            background: #fdfaf4;
        }

        .dbr-modal-tools .dbr-tabs {
            background: #fff;
        }

        .dbr-modal-body {
            overflow-y: auto;
            padding: 14px 20px 20px;
            flex: 1;
        }

        /* Order list inside modal */
        .dbr-order {
            display: grid;
            grid-template-columns: 40px 1fr auto;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1.5px solid #f1ece4;
            margin-bottom: 8px;
            cursor: pointer;
            transition: .15s ease;
        }

        .dbr-order:hover {
            background: #fffcf5;
            border-color: #ecd8b4;
        }

        .dbr-order-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 800;
        }

        .dbr-order-code {
            font-size: 12.5px;
            font-weight: 800;
            color: #b51f2c;
            margin: 0;
        }

        .dbr-order-cust {
            font-size: 12.5px;
            font-weight: 700;
            color: #302923;
            margin: 2px 0 0;
        }

        .dbr-order-meta {
            font-size: 10.5px;
            color: #948c82;
            margin: 3px 0 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .dbr-order-meta i {
            color: #b51f2c;
            margin-right: 3px;
        }

        .dbr-order-side {
            text-align: right;
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: flex-end;
        }

        .dbr-order-amt {
            font-family: "Playfair Display", serif;
            font-size: 14px;
            font-weight: 700;
            color: #b51f2c;
        }

        .dbr-order-date {
            font-size: 10.5px;
            color: #948c82;
        }

        .dbr-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .4px;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .status-pending {
            background: #fdf1e2;
            color: #a35a0e;
        }

        .status-confirmed {
            background: #e8f1e8;
            color: #2e7d32;
        }

        .status-processing {
            background: #e5eefb;
            color: #1565c0;
        }

        .status-delivered {
            background: #e8f6ea;
            color: #1b5e20;
        }

        .status-cancelled {
            background: #fdeaea;
            color: #b51f2c;
        }

        .dbr-modal-empty {
            text-align: center;
            padding: 40px 20px;
            color: #948c82;
        }

        .dbr-modal-empty i {
            font-size: 40px;
            color: #ece5da;
            display: block;
            margin-bottom: 8px;
        }

        .dbr-spinner {
            text-align: center;
            padding: 40px 20px;
            color: #948c82;
            font-size: 12px;
        }

        .dbr-spinner i {
            font-size: 28px;
            display: block;
            margin-bottom: 10px;
            color: #ece5da;
        }

        @media (max-width: 1024px) {
            .dbr-stats {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 640px) {
            .dbr-page {
                padding: 18px 14px 30px;
            }

            .dbr-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .dbr-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <?php include './templates/sidebar.php'; ?>

    <main class="main">
        <?php include './templates/header.php'; ?>

        <div class="dbr-page">

            <div class="dbr-head">
                <div>
                    <h1>Delivery Boys — Report</h1>
                    <p>Click any delivery boy to see their full order history.</p>
                </div>
            </div>

            <!-- Global stats -->
            <div class="dbr-stats">
                <div class="dbr-stat">
                    <div class="dbr-stat-label">Boys</div>
                    <div class="dbr-stat-value"><?= $overall['boys'] ?></div>
                </div>
                <div class="dbr-stat green">
                    <div class="dbr-stat-label">Delivered</div>
                    <div class="dbr-stat-value"><?= $overall['delivered'] ?></div>
                </div>
                <div class="dbr-stat red">
                    <div class="dbr-stat-label">Pending</div>
                    <div class="dbr-stat-value"><?= $overall['pending'] ?></div>
                </div>
                <div class="dbr-stat red">
                    <div class="dbr-stat-label">Cancelled</div>
                    <div class="dbr-stat-value"><?= $overall['cancelled'] ?></div>
                </div>
                <div class="dbr-stat">
                    <div class="dbr-stat-label">Revenue</div>
                    <div class="dbr-stat-value">₹<?= number_format($overall['revenue'], 0) ?></div>
                </div>
            </div>

            <!-- Global filter -->
            <form method="get" id="dbrGlobalForm" class="dbr-filters">
                <div class="dbr-tabs" id="dbrGlobalTabs">
                    <button type="button" class="dbr-tab <?= $gFilter === 'all'       ? 'active' : '' ?>" data-filter="all">All</button>
                    <button type="button" class="dbr-tab <?= $gFilter === 'today'     ? 'active' : '' ?>" data-filter="today">Today</button>
                    <button type="button" class="dbr-tab <?= $gFilter === 'yesterday' ? 'active' : '' ?>" data-filter="yesterday">Yesterday</button>
                    <button type="button" class="dbr-tab <?= $gFilter === 'week'      ? 'active' : '' ?>" data-filter="week">Last 7 Days</button>
                    <button type="button" class="dbr-tab <?= $gFilter === 'month'     ? 'active' : '' ?>" data-filter="month">This Month</button>
                    <button type="button" class="dbr-tab <?= $gFilter === 'custom'    ? 'active' : '' ?>" data-filter="custom">Custom</button>
                </div>

                <div class="dbr-daterange <?= $gFilter === 'custom' ? 'show' : '' ?>" id="dbrGlobalRange">
                    <input type="date" name="from" value="<?= htmlspecialchars($gFrom) ?>">
                    <span>to</span>
                    <input type="date" name="to" value="<?= htmlspecialchars($gTo) ?>">
                </div>

                <input type="hidden" name="filter" id="dbrGlobalFilter" value="<?= htmlspecialchars($gFilter) ?>">
            </form>

            <!-- Card grid -->
            <?php if (empty($boys)): ?>

                <div class="dbr-modal-empty" style="background:#fff;border:1.5px dashed #ece5da;border-radius:18px;padding:60px 20px;">
                    <i class="bi bi-people"></i>
                    <h3 style="font-family:'Playfair Display',serif;font-size:18px;color:#302923;margin:0 0 6px;">No delivery boys</h3>
                    <p style="margin:0;">Add delivery boys in the Delivery Boys module first.</p>
                </div>

            <?php else: ?>

                <div class="dbr-grid">
                    <?php foreach ($boys as $b):
                        $initial = strtoupper(substr(trim($b['full_name'] ?: '?'), 0, 1));
                        $revenue = (float)$b['revenue'];
                    ?>
                        <div class="dbr-card" data-boy-id="<?= (int)$b['id'] ?>">
                            <div class="dbr-card-head">
                                <div class="dbr-avatar">
                                    <?php if (!empty($b['profile_image'])): ?>
                                        <img src="<?= ADMIN_URL . htmlspecialchars($b['profile_image']) ?>" alt="">
                                    <?php else: ?>
                                        <?= htmlspecialchars($initial) ?>
                                    <?php endif; ?>
                                </div>
                                <div style="min-width:0;">
                                    <p class="dbr-name"><?= htmlspecialchars($b['full_name']) ?></p>
                                    <div class="dbr-code">
                                        <i class="bi bi-person-badge"></i>
                                        #<?= htmlspecialchars($b['delivery_code']) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="dbr-card-stats">
                                <div class="dbr-kv k-pending">
                                    <div class="dbr-kv-lbl">Pending</div>
                                    <div class="dbr-kv-val"><?= (int)$b['pending_orders'] ?></div>
                                </div>
                                <div class="dbr-kv k-delivered">
                                    <div class="dbr-kv-lbl">Delivered</div>
                                    <div class="dbr-kv-val"><?= (int)$b['delivered_orders'] ?></div>
                                </div>
                                <div class="dbr-kv k-cancel">
                                    <div class="dbr-kv-lbl">Cancel</div>
                                    <div class="dbr-kv-val"><?= (int)$b['cancelled_orders'] ?></div>
                                </div>
                            </div>

                            <div class="dbr-card-foot">
                                <span>Total <strong><?= (int)$b['total_orders'] ?></strong> · ₹<?= number_format($revenue, 0) ?></span>
                                <span class="dbr-card-go"><i class="bi bi-arrow-right"></i></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </div>
    </main>

    <!-- ================= POPUP ================= -->
    <div class="dbr-overlay" id="dbrOverlay">
        <div class="dbr-modal" role="dialog" aria-modal="true">

            <div class="dbr-modal-head">
                <div class="dbr-modal-head-left">
                    <div class="dbr-avatar" id="dbrModalAvatar">?</div>
                    <div>
                        <h3 class="dbr-modal-head-title" id="dbrModalName">Delivery Boy</h3>
                        <div class="dbr-modal-head-sub" id="dbrModalSub">#—</div>
                    </div>
                </div>
                <button class="dbr-close" id="dbrClose" aria-label="Close"><i class="bi bi-x-lg"></i></button>
            </div>

            <div class="dbr-modal-tools">
                <div class="dbr-tabs" id="dbrModalTabs">
                    <button type="button" class="dbr-tab active" data-filter="all">All</button>
                    <button type="button" class="dbr-tab" data-filter="today">Today</button>
                    <button type="button" class="dbr-tab" data-filter="yesterday">Yesterday</button>
                    <button type="button" class="dbr-tab" data-filter="week">Last 7 Days</button>
                    <button type="button" class="dbr-tab" data-filter="month">This Month</button>
                </div>

                <select class="dbr-tab" id="dbrModalStatus"
                    style="border:1.5px solid #ece5da;background:#fff;border-radius:9px;height:34px;padding:0 10px;font-size:11.5px;font-weight:700;color:#4e4841;cursor:pointer;">
                    <option value="all">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="processing">Processing</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>

                <input type="text" id="dbrModalSearch" placeholder="Search order, name, mobile…"
                    style="flex:1;min-width:180px;height:34px;border:1.5px solid #ece5da;border-radius:9px;padding:0 10px;font-size:11.5px;outline:none;">
            </div>

            <div class="dbr-modal-body" id="dbrModalBody">
                <div class="dbr-spinner"><i class="bi bi-hourglass-split"></i>Loading orders…</div>
            </div>

        </div>
    </div>

    <script>
        window.BASE_URL = "<?= BASE_URL ?>";
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
    </script>

    <script>
        (function() {
            "use strict";

            /* ---------------------------
               GLOBAL FILTER (page-level)
            ---------------------------- */
            const globalForm = document.getElementById('dbrGlobalForm');
            const globalTabs = document.querySelectorAll('#dbrGlobalTabs .dbr-tab');
            const globalHidden = document.getElementById('dbrGlobalFilter');
            const globalRange = document.getElementById('dbrGlobalRange');

            globalTabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const v = tab.dataset.filter;
                    globalHidden.value = v;
                    globalTabs.forEach(t => t.classList.toggle('active', t === tab));
                    globalRange.classList.toggle('show', v === 'custom');
                    if (v !== 'custom') globalForm.submit();
                });
            });
            globalRange.querySelectorAll('input').forEach(inp => {
                inp.addEventListener('change', () => {
                    const [f, t] = globalRange.querySelectorAll('input');
                    if (f.value && t.value) globalForm.submit();
                });
            });

            /* ---------------------------
               POPUP (per-boy history)
            ---------------------------- */
            const overlay = document.getElementById('dbrOverlay');
            const closeBtn = document.getElementById('dbrClose');
            const modalName = document.getElementById('dbrModalName');
            const modalSub = document.getElementById('dbrModalSub');
            const modalAvat = document.getElementById('dbrModalAvatar');
            const modalBody = document.getElementById('dbrModalBody');
            const modalTabs = document.querySelectorAll('#dbrModalTabs .dbr-tab');
            const modalStatus = document.getElementById('dbrModalStatus');
            const modalSearch = document.getElementById('dbrModalSearch');

            let currentBoyId = 0;
            let currentFilter = 'all';

            function openOverlay() {
                overlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            }

            function closeOverlay() {
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }

            closeBtn.addEventListener('click', closeOverlay);
            overlay.addEventListener('click', e => {
                if (e.target === overlay) closeOverlay();
            });
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape' && overlay.classList.contains('show')) closeOverlay();
            });

            function statusClass(s) {
                return 'status-' + (s || 'pending');
            }

            function renderOrders(rows) {
                if (!rows || !rows.length) {
                    modalBody.innerHTML = `
                        <div class="dbr-modal-empty">
                            <i class="bi bi-inbox"></i>
                            No orders found for this filter.
                        </div>`;
                    return;
                }
                let html = '';
                rows.forEach(r => {
                    const initial = (r.customer_name || '?').trim().charAt(0).toUpperCase();
                    const dateStr = r.created_at || '';
                    html += `
                        <div class="dbr-order"
                             onclick="window.location.href='${window.ADMIN_URL}order-view.php?id=${r.id}'">
                            <div class="dbr-order-avatar">${initial}</div>
                            <div>
                                <p class="dbr-order-code">#${r.order_code}</p>
                                <p class="dbr-order-cust">${r.customer_name || '—'}</p>
                                <p class="dbr-order-meta">
                                    <span><i class="bi bi-telephone-fill"></i>${r.customer_mobile || '—'}</span>
                                    <span><i class="bi bi-geo-alt-fill"></i>${r.addr_label || ''}</span>
                                </p>
                            </div>
                            <div class="dbr-order-side">
                                <span class="dbr-badge ${statusClass(r.status)}">${r.status}</span>
                                <span class="dbr-order-amt">₹${Math.round(Number(r.total_amount) || 0)}</span>
                                <span class="dbr-order-date">${dateStr}</span>
                            </div>
                        </div>`;
                });
                modalBody.innerHTML = html;
            }

            function loadOrders() {
                if (!currentBoyId) return;
                modalBody.innerHTML = `<div class="dbr-spinner"><i class="bi bi-hourglass-split"></i>Loading orders…</div>`;

                const params = new URLSearchParams();
                params.set('action', 'history');
                params.set('boy_id', currentBoyId);
                params.set('filter', currentFilter);
                params.set('status', modalStatus.value);
                params.set('q', modalSearch.value.trim());

                fetch('delivery-boys-report.php?' + params.toString(), {
                        credentials: 'same-origin'
                    })
                    .then(r => r.json().catch(() => null))
                    .then(res => {
                        if (!res || !res.success) {
                            modalBody.innerHTML = `<div class="dbr-modal-empty"><i class="bi bi-wifi-off"></i>${(res && res.message) || 'Failed to load.'}</div>`;
                            return;
                        }
                        renderOrders(res.data || []);
                    })
                    .catch(() => {
                        modalBody.innerHTML = `<div class="dbr-modal-empty"><i class="bi bi-wifi-off"></i>Unable to connect.</div>`;
                    });
            }

            document.querySelectorAll('.dbr-card').forEach(card => {
                card.addEventListener('click', () => {
                    currentBoyId = Number(card.dataset.boyId);
                    currentFilter = 'all';

                    // Read name/code from the card
                    const nameEl = card.querySelector('.dbr-name');
                    const codeEl = card.querySelector('.dbr-code');
                    modalName.textContent = nameEl ? nameEl.textContent : 'Delivery Boy';
                    modalSub.textContent = codeEl ? codeEl.textContent.trim() : '';
                    modalAvat.textContent = (nameEl ? nameEl.textContent : '?').trim().charAt(0).toUpperCase();

                    // Reset tools
                    modalTabs.forEach((t, i) => t.classList.toggle('active', i === 0));
                    modalStatus.value = 'all';
                    modalSearch.value = '';

                    openOverlay();
                    loadOrders();
                });
            });

            modalTabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    currentFilter = tab.dataset.filter;
                    modalTabs.forEach(t => t.classList.toggle('active', t === tab));
                    loadOrders();
                });
            });
            modalStatus.addEventListener('change', loadOrders);

            let t = null;
            modalSearch.addEventListener('input', () => {
                clearTimeout(t);
                t = setTimeout(loadOrders, 250);
            });

        })();
    </script>

</body>

</html>