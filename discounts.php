<?php
require_once './config/config.php';
require_once './config/function.php';

/* =========================================================
   LOAD DISCOUNTS + SLOT SUMMARY
   ========================================================= */

$discounts = [];

try {

    $stmt = $pdo->query(
        "SELECT d.id,
                d.discount_code,
                d.discount_name,
                d.discount_type,
                d.coupon_code,
                d.valid_from_date,
                d.valid_to_date,
                d.status,
                d.created_at,
                (SELECT COUNT(*) FROM discount_times WHERE discount_code = d.discount_code) AS slot_count,
                (SELECT MIN(discount_amount) FROM discount_times WHERE discount_code = d.discount_code) AS min_amount,
                (SELECT MAX(discount_amount) FROM discount_times WHERE discount_code = d.discount_code) AS max_amount,
                (SELECT amount_type FROM discount_times WHERE discount_code = d.discount_code LIMIT 1) AS amount_type_sample,
                (SELECT MIN(start_time) FROM discount_times WHERE discount_code = d.discount_code) AS first_start,
                (SELECT MAX(end_time)   FROM discount_times WHERE discount_code = d.discount_code) AS last_end
         FROM discounts d
         ORDER BY d.id DESC"
    );

    $discounts = $stmt->fetchAll();

    /* Load slot names for each discount (limited to first 4 per discount) */
    $slotsByCode = [];

    if (!empty($discounts)) {
        $codes = array_column($discounts, 'discount_code');
        $placeholders = implode(',', array_fill(0, count($codes), '?'));

        $sStmt = $pdo->prepare(
            "SELECT discount_code, slot_name, start_time, end_time, amount_type, discount_amount
             FROM discount_times
             WHERE discount_code IN ($placeholders)
             ORDER BY id ASC"
        );
        $sStmt->execute($codes);

        foreach ($sStmt->fetchAll() as $row) {
            $slotsByCode[$row['discount_code']][] = $row;
        }
    }

    foreach ($discounts as &$d) {
        $d['slots'] = $slotsByCode[$d['discount_code']] ?? [];
    }
    unset($d);
} catch (PDOException $e) {
    $discounts = [];
}

/* =========================================================
   HELPERS
   ========================================================= */

function fmtTime(string $t): string
{
    $ts = strtotime($t);
    if (!$ts) return $t;
    return date('h:i A', $ts);
}

function fmtDate(string $d): string
{
    $ts = strtotime($d);
    if (!$ts) return $d;
    return date('d M Y', $ts);
}

function discountRuleStatus(array $d): array
{
    /* 1) Inactive if toggled off */
    if ((int)$d['status'] !== 1) {
        return ['key' => 'inactive', 'label' => 'Inactive'];
    }

    /* 2) Coupon — check date range */
    if ($d['discount_type'] === 'coupon') {

        $now  = time();
        $from = strtotime($d['valid_from_date'] . ' 00:00:00');
        $to   = strtotime($d['valid_to_date'] . ' 23:59:59');

        if ($now < $from) return ['key' => 'upcoming', 'label' => 'Upcoming'];
        if ($now > $to)   return ['key' => 'expired',  'label' => 'Expired'];

        /* Also check time window of the coupon slot */
        $slots = $d['slots'] ?? [];
        if (!empty($slots)) {
            $slot = $slots[0];
            $st   = strtotime($slot['start_time']);
            $et   = strtotime($slot['end_time']);
            $cur  = strtotime(date('H:i:s'));

            if ($cur >= $st && $cur <= $et) {
                return ['key' => 'active', 'label' => 'Live'];
            }
            return ['key' => 'upcoming', 'label' => 'Off Time'];
        }

        return ['key' => 'active', 'label' => 'Active'];
    }

    /* 3) Time-based — check if any slot is live right now */
    $slots = $d['slots'] ?? [];

    if (empty($slots)) {
        return ['key' => 'inactive', 'label' => 'No Slots'];
    }

    $cur = strtotime(date('H:i:s'));
    $liveNow = false;

    foreach ($slots as $s) {
        $st = strtotime($s['start_time']);
        $et = strtotime($s['end_time']);

        if ($st <= $et) {
            /* Same day: start < end */
            if ($cur >= $st && $cur <= $et) {
                $liveNow = true;
                break;
            }
        } else {
            /* Cross-midnight: e.g. 22:00 → 02:00 */
            if ($cur >= $st || $cur <= $et) {
                $liveNow = true;
                break;
            }
        }
    }

    return $liveNow
        ? ['key' => 'active', 'label' => 'Live']
        : ['key' => 'upcoming', 'label' => 'Off Time'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

    <style>
        .disc-page {
            padding: 30px 32px 40px;
        }

        .disc-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .disc-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 5px;
        }

        .disc-title p {
            margin: 0;
            color: #817a71;
            font-size: 13px;
        }

        .btn-add {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #b51f2c;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 11px 18px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: .2s;
        }

        .btn-add:hover {
            background: #8e1722;
            color: #fff;
        }

        .disc-card {
            background: #fff;
            border: 1px solid #eee7dc;
            border-radius: 20px;
            padding: 22px;
        }

        .disc-toolbar {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .disc-search {
            position: relative;
            flex: 1;
            min-width: 220px;
        }

        .disc-search i {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa198;
            font-size: 14px;
        }

        .disc-search input {
            width: 100%;
            height: 42px;
            border: 1px solid #e8e1d8;
            background: #fffdf9;
            border-radius: 11px;
            padding: 0 12px 0 38px;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            color: #292521;
            outline: none;
            transition: .2s ease;
        }

        .disc-search input:focus {
            border-color: #d98a91;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .disc-filter {
            height: 42px;
            border: 1px solid #e8e1d8;
            background: #fffdf9;
            border-radius: 11px;
            padding: 0 12px;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            color: #292521;
            outline: none;
            cursor: pointer;
        }

        .disc-filter:focus {
            border-color: #d98a91;
            background: #fff;
        }

        /* TABLE */
        .disc-table-wrap {
            border: 1px solid #f0ebe4;
            border-radius: 14px;
            overflow-x: auto;
        }

        .disc-table {
            width: 100%;
            border-collapse: collapse;
            font-family: "DM Sans", sans-serif;
            min-width: 1000px;
        }

        .disc-table thead {
            background: #faf7f0;
        }

        .disc-table th {
            text-align: left;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #6f675f;
            padding: 12px 14px;
            border-bottom: 1px solid #f0ebe4;
            white-space: nowrap;
        }

        .disc-table td {
            font-size: 12px;
            color: #302923;
            padding: 14px;
            border-bottom: 1px solid #f5efe6;
            vertical-align: top;
        }

        .disc-table tr:last-child td {
            border-bottom: none;
        }

        .disc-table tbody tr:hover {
            background: #fffaf9;
        }

        .disc-name-cell {
            display: flex;
            flex-direction: column;
            gap: 4px;
            max-width: 260px;
        }

        .disc-name-main {
            font-weight: 800;
            font-size: 12.5px;
            color: #302923;
        }

        .disc-code-tag {
            display: inline-block;
            font-size: 9px;
            font-weight: 700;
            background: #faf7f0;
            color: #6f5a3f;
            padding: 2px 7px;
            border-radius: 5px;
            letter-spacing: .5px;
            width: fit-content;
        }

        .type-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
            font-weight: 800;
            padding: 4px 9px;
            border-radius: 20px;
            letter-spacing: .3px;
            text-transform: uppercase;
        }

        .type-pill.time {
            background: #eef4fd;
            color: #1565c0;
            border: 1px solid #cfe0f5;
        }

        .type-pill.coupon {
            background: #f3eefe;
            color: #7c3aed;
            border: 1px solid #d9c9f7;
        }

        .type-pill i {
            font-size: 11px;
        }

        .coupon-code-text {
            display: inline-block;
            font-size: 11px;
            font-weight: 800;
            background: #fff5f5;
            color: #b51f2c;
            border: 1px solid #f3c8cc;
            padding: 3px 8px;
            border-radius: 6px;
            letter-spacing: .5px;
        }

        .disc-amount {
            font-weight: 800;
            color: #b51f2c;
            font-size: 12px;
        }

        .disc-amount small {
            color: #948c82;
            font-weight: 700;
            font-size: 9px;
            margin-left: 3px;
        }

        .slot-list {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        /* Slot pills with names */
        .slot-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f1e8df;
            color: #755d48;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            width: fit-content;
            max-width: 100%;
        }

        .slot-pill .nm {
            color: #b51f2c;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .3px;
            font-size: 9px;
        }

        .slot-pill .tm {
            color: #6f5a3f;
            font-weight: 700;
        }

        .slot-pill .am {
            background: #fff;
            color: #b51f2c;
            padding: 1px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 800;
        }

        .slot-more {
            font-size: 9px;
            color: #948c82;
            font-style: italic;
            padding-left: 4px;
        }

        .slot-line {
            font-size: 10px;
            color: #4e4841;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .slot-line i {
            color: #b51f2c;
            font-size: 10px;
        }

        .slot-count-badge {
            font-size: 9px;
            font-weight: 800;
            background: #f1e8df;
            color: #755d48;
            padding: 2px 7px;
            border-radius: 5px;
            display: inline-block;
            width: fit-content;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 10px;
            font-weight: 800;
            padding: 5px 10px;
            border-radius: 20px;
            letter-spacing: .3px;
            text-transform: uppercase;
        }

        .status-pill.active {
            background: #e8f6ea;
            color: #2e7d32;
            border: 1px solid #b6e0bd;
        }

        .status-pill.upcoming {
            background: #fff8e6;
            color: #a06a00;
            border: 1px solid #f0dca0;
        }

        .status-pill.expired {
            background: #fde6e6;
            color: #c62828;
            border: 1px solid #f3c8cc;
        }

        .status-pill.inactive {
            background: #f5f2ec;
            color: #6f675f;
            border: 1px solid #e4ddd3;
        }

        .status-pill .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
        }

        .status-pill.active .dot {
            box-shadow: 0 0 0 3px rgba(46, 125, 50, .2);
            animation: pulse 1.6s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                box-shadow: 0 0 0 3px rgba(46, 125, 50, .2);
            }

            50% {
                box-shadow: 0 0 0 6px rgba(46, 125, 50, 0);
            }
        }

        .row-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: flex-end;
        }

        .row-btn {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
            font-size: 14px;
            cursor: pointer;
            transition: .2s;
            text-decoration: none;
            background: #fff;
        }

        .row-btn.edit {
            color: #1565c0;
            border-color: #cfe0f5;
        }

        .row-btn.edit:hover {
            background: #eef4fd;
            color: #0d47a1;
        }

        .row-btn.delete {
            color: #b51f2c;
            border-color: #f0d6d8;
        }

        .row-btn.delete:hover {
            background: #fde6e6;
            color: #c62828;
        }

        .table-empty {
            text-align: center;
            padding: 50px 20px;
            color: #948c82;
            font-size: 12px;
        }

        .table-empty i {
            font-size: 40px;
            color: #d5cbbd;
            display: block;
            margin-bottom: 12px;
        }

        /* MODALS */
        .mm-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(30, 25, 22, .55);
            backdrop-filter: blur(3px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: .2s;
        }

        .mm-modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .mm-modal {
            background: #fff;
            border-radius: 18px;
            padding: 30px 26px 24px;
            max-width: 420px;
            width: 100%;
            text-align: center;
            box-shadow: 0 30px 80px rgba(0, 0, 0, .25);
            transform: translateY(15px) scale(.96);
            transition: transform .25s cubic-bezier(.2, .9, .3, 1.2);
        }

        .mm-modal-overlay.show .mm-modal {
            transform: translateY(0) scale(1);
        }

        .mm-modal-icon {
            width: 66px;
            height: 66px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin: 0 auto 16px;
            animation: popIn .35s cubic-bezier(.2, .9, .3, 1.4);
        }

        .mm-modal-icon.success {
            background: #e8f6ea;
            color: #2e7d32;
        }

        .mm-modal-icon.error {
            background: #fde6e6;
            color: #c62828;
        }

        .mm-modal-icon.danger {
            background: #fde6e6;
            color: #c62828;
        }

        @keyframes popIn {
            0% {
                transform: scale(.5);
                opacity: 0;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .mm-modal-title {
            margin: 0 0 8px;
            font-size: 18px;
            font-weight: 800;
            color: #302923;
        }

        .mm-modal-text {
            margin: 0 0 20px;
            font-size: 12px;
            color: #756d65;
            line-height: 1.6;
        }

        .mm-modal-actions {
            display: flex;
            gap: 10px;
        }

        .mm-btn {
            flex: 1;
            height: 44px;
            border-radius: 11px;
            border: none;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            text-decoration: none;
            transition: .2s ease;
        }

        .mm-btn-primary {
            background: #b51f2c;
            color: #fff;
        }

        .mm-btn-primary:hover {
            background: #8e1722;
            color: #fff;
        }

        .mm-btn-ghost {
            background: #fff;
            border: 1px solid #e4ddd3;
            color: #6f675f;
        }

        .mm-btn-ghost:hover {
            background: #faf7f0;
        }

        .mm-btn-danger {
            background: #c62828;
            color: #fff;
        }

        .mm-btn-danger:hover {
            background: #a02020;
        }

        .btn-spinner {
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, .4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            display: inline-block;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 768px) {
            .disc-page {
                padding: 20px 15px 30px;
            }

            .disc-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .disc-card {
                padding: 15px;
                border-radius: 16px;
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


        <div class="disc-page">

            <div class="disc-header">
                <div class="disc-title">
                    <h1>Discounts</h1>
                    <p>All time-based and coupon-based discounts.</p>
                </div>

                <a href="add-discount.php" class="btn-add">
                    <i class="bi bi-plus-lg"></i> Add Discount
                </a>
            </div>


            <div class="disc-card">

                <div class="disc-toolbar">
                    <div class="disc-search">
                        <i class="bi bi-search"></i>
                        <input type="text"
                            id="searchInput"
                            placeholder="Search by name, code or coupon...">
                    </div>

                    <select id="typeFilter" class="disc-filter">
                        <option value="">All Types</option>
                        <option value="time">Time Based</option>
                        <option value="coupon">Coupon Code</option>
                    </select>

                    <select id="statusFilter" class="disc-filter">
                        <option value="">All Status</option>
                        <option value="active">Live</option>
                        <option value="inactive">Inactive</option>
                        <option value="upcoming">Off Time</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>


                <div class="disc-table-wrap">

                    <table class="disc-table">
                        <thead>
                            <tr>
                                <th>Discount</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Slots / Coupon</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>

                        <tbody id="discTbody">

                            <?php if (empty($discounts)): ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="table-empty">
                                            <i class="bi bi-ticket-perforated"></i>
                                            No discounts yet. Click <strong>Add Discount</strong> to create one.
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($discounts as $d):

                                    $s = discountRuleStatus($d);
                                    $isCoupon = $d['discount_type'] === 'coupon';

                                    $minAmt = (float)$d['min_amount'];
                                    $maxAmt = (float)$d['max_amount'];
                                    $sampleType = $d['amount_type_sample'] ?? 'fixed';

                                    /* Display amount summary */
                                    if ($minAmt === $maxAmt) {
                                        $amtLabel = $sampleType === 'percent'
                                            ? number_format($minAmt, 0) . '%'
                                            : '₹' . number_format($minAmt, 2);
                                    } else {
                                        if ($sampleType === 'percent') {
                                            $amtLabel = number_format($minAmt, 0) . '% – ' . number_format($maxAmt, 0) . '%';
                                        } else {
                                            $amtLabel = '₹' . number_format($minAmt, 0) . ' – ₹' . number_format($maxAmt, 0);
                                        }
                                    }
                                ?>
                                    <tr
                                        data-code="<?= htmlspecialchars(strtolower($d['discount_code'])) ?>"
                                        data-name="<?= htmlspecialchars(strtolower($d['discount_name'])) ?>"
                                        data-coupon="<?= htmlspecialchars(strtolower($d['coupon_code'] ?? '')) ?>"
                                        data-type="<?= htmlspecialchars($d['discount_type']) ?>"
                                        data-status="<?= htmlspecialchars($s['key']) ?>">
                                        <td>
                                            <div class="disc-name-cell">
                                                <div class="disc-name-main">
                                                    <?= htmlspecialchars($d['discount_name']) ?>
                                                </div>
                                                <span class="disc-code-tag">
                                                    #<?= htmlspecialchars($d['discount_code']) ?>
                                                </span>
                                            </div>
                                        </td>

                                        <td>
                                            <?php if ($isCoupon): ?>
                                                <span class="type-pill coupon">
                                                    <i class="bi bi-ticket-detailed"></i> Coupon
                                                </span>
                                            <?php else: ?>
                                                <span class="type-pill time">
                                                    <i class="bi bi-clock-history"></i> Time
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div class="disc-amount">
                                                <?= htmlspecialchars($amtLabel) ?>
                                                <?php if ($sampleType === 'percent'): ?>
                                                    <small>%</small>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <td>
                                            <?php if ($isCoupon): ?>
                                                <div class="slot-list">
                                                    <span class="coupon-code-text">
                                                        <?= htmlspecialchars($d['coupon_code'] ?? '—') ?>
                                                    </span>
                                                    <span class="slot-line">
                                                        <i class="bi bi-calendar-range"></i>
                                                        <?= fmtDate($d['valid_from_date']) ?>
                                                        → <?= fmtDate($d['valid_to_date']) ?>
                                                    </span>
                                                    <?php if (!empty($d['slots']) && $d['slots'][0]['start_time']): ?>
                                                        <span class="slot-line">
                                                            <i class="bi bi-clock"></i>
                                                            <?= fmtTime($d['slots'][0]['start_time']) ?>
                                                            – <?= fmtTime($d['slots'][0]['end_time']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="slot-list">
                                                    <span class="slot-count-badge">
                                                        <?= (int)$d['slot_count'] ?> slot<?= (int)$d['slot_count'] === 1 ? '' : 's' ?>
                                                    </span>

                                                    <?php
                                                    /* Show up to 3 named slot pills */
                                                    $shownSlots = array_slice($d['slots'], 0, 3);
                                                    foreach ($shownSlots as $slot):
                                                    ?>
                                                        <span class="slot-pill">
                                                            <span class="nm"><?= htmlspecialchars($slot['slot_name'] ?? 'Set') ?></span>
                                                            <span class="tm">
                                                                <?= fmtTime($slot['start_time']) ?> – <?= fmtTime($slot['end_time']) ?>
                                                            </span>
                                                            <span class="am">
                                                                <?= $slot['amount_type'] === 'percent'
                                                                    ? number_format((float)$slot['discount_amount'], 0) . '%'
                                                                    : '₹' . number_format((float)$slot['discount_amount'], 0) ?>
                                                            </span>
                                                        </span>
                                                    <?php endforeach; ?>

                                                    <?php if (count($d['slots']) > 3): ?>
                                                        <span class="slot-more">
                                                            + <?= count($d['slots']) - 3 ?> more…
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="status-pill <?= $s['key'] ?>">
                                                <span class="dot"></span>
                                                <?= $s['label'] ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="row-actions">
                                                <a href="edit-discount.php?id=<?= (int)$d['id'] ?>"
                                                    class="row-btn edit"
                                                    title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button"
                                                    class="row-btn delete js-delete-btn"
                                                    data-id="<?= (int)$d['id'] ?>"
                                                    data-name="<?= htmlspecialchars($d['discount_name']) ?>"
                                                    title="Delete">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                        </tbody>
                    </table>

                </div>

            </div>

        </div>

    </main>


    <!-- DELETE CONFIRM -->
    <div class="mm-modal-overlay" id="deleteOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog" aria-modal="true">
            <div class="mm-modal-icon danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <h3 class="mm-modal-title">Delete Discount?</h3>
            <p class="mm-modal-text" id="deleteText">
                This will permanently remove the discount and all its time slots.
            </p>
            <div class="mm-modal-actions">
                <button type="button" class="mm-btn mm-btn-ghost" id="deleteCancel">Cancel</button>
                <button type="button" class="mm-btn mm-btn-danger" id="deleteConfirm">
                    <i class="bi bi-trash3"></i> Delete
                </button>
            </div>
        </div>
    </div>


    <!-- ERROR -->
    <div class="mm-modal-overlay" id="errorOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog" aria-modal="true">
            <div class="mm-modal-icon error">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <h3 class="mm-modal-title" id="errorTitle">Oops!</h3>
            <p class="mm-modal-text" id="errorText">Something went wrong.</p>
            <div class="mm-modal-actions">
                <button type="button" class="mm-btn mm-btn-primary" id="errorOkBtn">Got it</button>
            </div>
        </div>
    </div>


    <!-- SUCCESS -->
    <div class="mm-modal-overlay" id="successOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog" aria-modal="true">
            <div class="mm-modal-icon success">
                <i class="bi bi-check-lg"></i>
            </div>
            <h3 class="mm-modal-title">Deleted!</h3>
            <p class="mm-modal-text" id="successText">Discount deleted successfully.</p>
            <div class="mm-modal-actions">
                <button type="button" class="mm-btn mm-btn-primary" id="successOkBtn">
                    <i class="bi bi-check2"></i> Done
                </button>
            </div>
        </div>
    </div>




    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL; ?>js/main.js"></script>
    <script src="<?= ADMIN_URL; ?>js/discounts.js"></script>

</body>

</html>