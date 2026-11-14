<?php
require_once './config/config.php';
require_once './config/function.php';

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    header('Location: login.php');
    exit;
}
$isAdmin = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin');
$settings = getSettings($pdo);
requireAdmin();

/* LOAD PRODUCTS + VARIANTS */
$products = [];
try {
    $stmt = $pdo->query("SELECT id, product_code, product_name, product_image FROM products WHERE status = 1 ORDER BY product_name ASC");
    $products = $stmt->fetchAll();
    $variantsByProduct = [];
    if (!empty($products)) {
        $codes = array_column($products, 'product_code');
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $vStmt = $pdo->prepare("SELECT id, product_code, quantity, quantity_unit, quantity_name, price FROM product_variants WHERE product_code IN ($placeholders) AND status = 1 ORDER BY id ASC");
        $vStmt->execute($codes);
        foreach ($vStmt->fetchAll() as $v) {
            $variantsByProduct[$v['product_code']][] = $v;
        }
    }
    foreach ($products as &$p) {
        $p['variants']  = $variantsByProduct[$p['product_code']] ?? [];
        $p['image_url'] = $p['product_image'] ? ADMIN_URL . $p['product_image'] : '';
    }
} catch (PDOException $e) {
    $products = [];
}

/* LOAD RECENT MENUS */
$recentMenus = [];
try {
    $stmt = $pdo->query("SELECT m.id, m.menu_code, m.menu_name, m.start_at, m.end_at FROM menus m ORDER BY m.id DESC LIMIT 20");
    $recentMenus = $stmt->fetchAll();
    if (!empty($recentMenus)) {
        $codes = array_column($recentMenus, 'menu_code');
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $rStmt = $pdo->prepare("SELECT menu_code, product_code, variant_id, stock_unlimited, stock_count FROM menu_products WHERE menu_code IN ($placeholders) ORDER BY id ASC");
        $rStmt->execute($codes);
        $menuRows = $rStmt->fetchAll();

        $liveVariantsByProduct = [];
        if (!empty($menuRows)) {
            $pCodes = array_unique(array_column($menuRows, 'product_code'));
            $vPh = implode(',', array_fill(0, count($pCodes), '?'));
            $liveStmt = $pdo->prepare("SELECT id, product_code FROM product_variants WHERE product_code IN ($vPh) AND status = 1 ORDER BY id ASC");
            $liveStmt->execute($pCodes);
            foreach ($liveStmt->fetchAll() as $v) {
                $liveVariantsByProduct[$v['product_code']][] = (int)$v['id'];
            }
        }

        $grouped = [];
        $orphanCursor = [];
        foreach ($menuRows as $row) {
            $menuCode = $row['menu_code'];
            $pcode = $row['product_code'];
            $storedId = (int)$row['variant_id'];
            $liveIds = $liveVariantsByProduct[$pcode] ?? [];
            if (empty($liveIds)) continue;
            $resolvedId = in_array($storedId, $liveIds, true) ? $storedId : $liveIds[($orphanCursor[$menuCode . '|' . $pcode] = ($orphanCursor[$menuCode . '|' . $pcode] ?? 0) + 1) % count($liveIds)];
            $key = $menuCode . '|' . $pcode;
            if (!isset($grouped[$key])) {
                $grouped[$key] = ['menu_code' => $menuCode, 'product_code' => $pcode, 'variant_data' => []];
            }
            $grouped[$key]['variant_data'][$resolvedId] = ['id' => $resolvedId, 'stock_unlimited' => (int)$row['stock_unlimited'], 'stock_count' => (int)$row['stock_count']];
        }
        foreach ($recentMenus as &$m) {
            $rows = [];
            foreach ($grouped as $g) {
                if ($g['menu_code'] === $m['menu_code']) $rows[] = ['product_code' => $g['product_code'], 'variant_data' => array_values($g['variant_data'])];
            }
            $m['rows'] = $rows;
        }
        unset($m);
    }
} catch (PDOException $e) {
    $recentMenus = [];
}

function menuRecencyLabel(string $createdAt): string
{
    $ts = strtotime($createdAt);
    if (!$ts) return '';
    $todayTs = strtotime(date('Y-m-d'));
    $yesterdayTs = strtotime('-1 day', $todayTs);
    $dateOnly = strtotime(date('Y-m-d', $ts));
    if ($dateOnly === $todayTs) return 'Today';
    if ($dateOnly === $yesterdayTs) return 'Yesterday';
    return date('d/m/Y', $ts);
}
foreach ($recentMenus as &$m) {
    $m['label'] = menuRecencyLabel($m['start_at'] ?? date('Y-m-d'));
}
unset($m);
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include './includes/head.php'; ?>
    <style>
        .menu-page {
            padding: 30px 32px 40px;
        }

        .menu-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .menu-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 5px;
        }

        .menu-title p {
            margin: 0;
            color: #817a71;
            font-size: 13px;
        }

        .duplicate-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .duplicate-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            border: 1px solid #e4ddd3;
            color: #6f675f;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: .2s;
        }

        .duplicate-dd {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 320px;
            background: #fff;
            border: 1px solid #e8e1d8;
            border-radius: 12px;
            box-shadow: 0 18px 50px rgba(0, 0, 0, .12);
            z-index: 90;
            display: none;
            max-height: 320px;
            overflow: hidden;
        }

        .duplicate-wrap.open .duplicate-dd {
            display: block;
        }

        .duplicate-dd-search {
            padding: 10px;
            border-bottom: 1px solid #f0ebe4;
            position: relative;
        }

        .duplicate-dd-search i {
            position: absolute;
            left: 22px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa198;
            font-size: 13px;
        }

        .duplicate-dd-search input {
            width: 100%;
            height: 36px;
            border: 1px solid #e8e1d8;
            background: #fffdf9;
            border-radius: 9px;
            padding: 0 12px 0 34px;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            outline: none;
        }

        .duplicate-dd-list {
            max-height: 240px;
            overflow-y: auto;
            padding: 6px;
        }

        .duplicate-item {
            padding: 10px 12px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            gap: 3px;
            transition: .15s;
        }

        .duplicate-item:hover {
            background: #fffaf9;
        }

        .duplicate-item .nm {
            font-size: 12px;
            font-weight: 700;
            color: #302923;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .duplicate-item .nm .when {
            font-size: 9px;
            font-weight: 700;
            background: #fff5f5;
            color: #b51f2c;
            padding: 2px 6px;
            border-radius: 5px;
            text-transform: uppercase;
        }

        .menu-form-card {
            background: #fff;
            border: 1px solid #eee7dc;
            border-radius: 20px;
            padding: 25px;
            max-width: 1100px;
            margin-bottom: 24px;
        }

        .form-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
        }

        .form-card-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: #fbe8e9;
            color: #b51f2c;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .form-card-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
        }

        .form-card-header span {
            display: block;
            margin-top: 3px;
            color: #817a71;
            font-size: 10px;
        }

        .menu-form label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #4e4841;
            margin-bottom: 7px;
        }

        .required {
            color: #b51f2c;
        }

        .menu-input {
            width: 100%;
            height: 45px;
            border: 1px solid #e8e1d8;
            background: #fffdf9;
            border-radius: 11px;
            padding: 11px 13px;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            color: #292521;
            outline: none;
        }

        .input-icon-wrap {
            position: relative;
        }

        .input-icon-wrap>i {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa198;
            font-size: 15px;
            pointer-events: none;
        }

        .input-icon-wrap .menu-input {
            padding-left: 40px;
        }

        .status-toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 14px 16px;
            background: #fffdf9;
            border: 1px solid #e8e1d8;
            border-radius: 12px;
            margin-top: 16px;
        }

        .status-toggle-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .status-toggle-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #fde6e6;
            color: #c62828;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .status-toggle-row.is-active .status-toggle-icon {
            background: #e8f6ea;
            color: #2e7d32;
        }

        .mm-switch {
            position: relative;
            display: inline-block;
            width: 46px;
            height: 26px;
            flex-shrink: 0;
        }

        .mm-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .mm-switch-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: #d8d2c9;
            border-radius: 30px;
            transition: .25s;
        }

        .mm-switch-slider::before {
            content: "";
            position: absolute;
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background: #fff;
            border-radius: 50%;
            transition: .25s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .15);
        }

        .mm-switch input:checked+.mm-switch-slider {
            background: #2e7d32;
        }

        .mm-switch input:checked+.mm-switch-slider::before {
            transform: translateX(20px);
        }

        .datetime-section {
            margin-top: 6px;
            padding-top: 20px;
        }

        .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .section-head h4 {
            margin: 0;
            font-size: 13px;
            font-weight: 800;
            color: #302923;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dt-grid {
            display: grid;
            grid-template-columns: 1fr 150px 110px;
            gap: 10px;
            margin-bottom: 14px;
        }

        .products-section {
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid #f0ebe4;
        }

        .add-product-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #b51f2c;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 9px 15px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }

        .prod-rows {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-top: 14px;
        }

        .prod-row {
            border: 1px solid #f0ebe4;
            background: #fffdf9;
            border-radius: 14px;
            padding: 16px;
        }

        .prod-row-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 14px;
        }

        .prod-row-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        .add-product-btn-bottom {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            margin-top: 16px;
            padding: 18px;
            background: #fffdf9;
            border: 2px dashed #e4ddd3;
            border-radius: 14px;
            color: #6f675f;
            font-family: "DM Sans", sans-serif;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            transition: all .2s ease;
        }

        .add-product-btn-bottom i {
            font-size: 18px;
            color: #b51f2c;
            transition: transform .2s ease;
        }

        .add-product-btn-bottom:hover {
            background: #fff5f5;
            border-color: #d98a91;
            color: #b51f2c;
        }

        .add-product-btn-bottom:hover i {
            transform: scale(1.1) rotate(90deg);
        }

        .prod-row-remove {
            width: 32px;
            height: 32px;
            border: 1px solid #f0d6d8;
            background: #fff;
            color: #b51f2c;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: .2s;
            font-size: 13px;
        }

        .prod-row-remove:hover {
            background: #fde6e6;
            border-color: #f1c8cc;
            color: #c62828;
        }

        .dd {
            position: relative;
        }

        .dd-trigger {
            width: 100%;
            min-height: 42px;
            border: 1px solid #e8e1d8;
            background: #fff;
            border-radius: 10px;
            padding: 8px 34px 8px 12px;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            color: #292521;
            outline: none;
            text-align: left;
            cursor: pointer;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 5px;
        }

        .dd-trigger.disabled {
            background: #f5f2ec;
            color: #a09a90;
            cursor: not-allowed;
        }

        .dd-arrow {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa198;
            font-size: 13px;
        }

        .dd-panel {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #e8e1d8;
            border-radius: 12px;
            box-shadow: 0 18px 50px rgba(0, 0, 0, .12);
            z-index: 60;
            overflow: hidden;
            display: none;
        }

        .dd.open .dd-panel {
            display: block;
        }

        .dd-search {
            padding: 10px;
            border-bottom: 1px solid #f0ebe4;
            position: relative;
        }

        .dd-search i {
            position: absolute;
            left: 22px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa198;
            font-size: 13px;
        }

        .dd-search input {
            width: 100%;
            height: 38px;
            border: 1px solid #e8e1d8;
            background: #fffdf9;
            border-radius: 9px;
            padding: 0 12px 0 34px;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            outline: none;
        }

        .dd-list {
            max-height: 240px;
            overflow-y: auto;
            padding: 6px;
        }

        .dd-item {
            padding: 9px 12px;
            border-radius: 8px;
            font-size: 11px;
            color: #302923;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dd-item:hover {
            background: #fffaf9;
        }

        .dd-item.selected {
            background: #fff5f5;
        }

        .dd-item .check {
            width: 16px;
            height: 16px;
            border: 1.5px solid #d5cbbd;
            border-radius: 5px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 10px;
        }

        .dd-item.selected .check {
            background: #b51f2c;
            border-color: #b51f2c;
        }

        .dd-item .check i {
            opacity: 0;
        }

        .dd-item.selected .check i {
            opacity: 1;
        }

        .dd-item .info {
            flex: 1;
            min-width: 0;
        }

        .dd-item .nm {
            font-weight: 700;
        }

        .dd-item .price {
            color: #b51f2c;
            font-weight: 800;
            font-size: 10px;
        }

        .dd-chip {
            background: #fff5f5;
            color: #b51f2c;
            border: 1px solid #f3c8cc;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .dd-chip .x {
            cursor: pointer;
            font-size: 12px;
            opacity: .8;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid #f0ebe4;
        }

        .btn-cancel {
            border: 1px solid #e4ddd3;
            background: #fff;
            color: #6f675f;
            border-radius: 10px;
            padding: 10px 17px;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
        }

        .btn-save {
            border: none;
            background: #b51f2c;
            color: #fff;
            border-radius: 10px;
            padding: 10px 19px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }

        .variant-stock-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 16px;
        }

        @media (max-width: 600px) {
            .variant-stock-list {
                grid-template-columns: 1fr;
            }
        }

        .variant-stock-card {
            background: #fff;
            border: 1.5px solid #f0ebe4;
            border-radius: 12px;
            padding: 14px;
            transition: .2s;
        }

        .variant-stock-card:hover {
            border-color: #d98a91;
            box-shadow: 0 4px 12px rgba(181, 31, 44, 0.05);
        }

        .variant-stock-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .variant-stock-name {
            font-size: 13px;
            font-weight: 800;
            color: #302923;
        }

        .variant-stock-price {
            font-size: 11px;
            color: #b51f2c;
            font-weight: 700;
            background: #fff5f5;
            padding: 2px 8px;
            border-radius: 6px;
        }

        .variant-stock-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #fffdf9;
            padding: 8px;
            border-radius: 8px;
            border: 1px solid #f0ebe4;
        }

        .variant-stock-controls input[type="number"] {
            width: 100%;
            height: 36px;
            border: 1px solid #e8e1d8;
            border-radius: 8px;
            padding: 0 10px;
            font-size: 12px;
            font-weight: 700;
            color: #302923;
            outline: none;
        }

        .variant-stock-controls input[type="number"]:focus {
            border-color: #d98a91;
        }

        .variant-stock-controls input[type="number"]:disabled {
            background: #f5f2ec;
            color: #a09a90;
        }

        .switch-sm {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 700;
            color: #6f675f;
            cursor: pointer;
            white-space: nowrap;
            margin: 0;
        }

        .switch-sm input {
            width: 16px;
            height: 16px;
            accent-color: #2e7d32;
            cursor: pointer;
        }

        .prod-empty {
            text-align: center;
            padding: 30px 20px;
            border: 2px dashed #e4ddd3;
            border-radius: 14px;
            color: #948c82;
            font-size: 11px;
            background: #fffdf9;
        }

        /* ===== SUCCESS / ERROR MODAL CSS ===== */
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
            transition: opacity .2s ease, visibility .2s ease;
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
            color: #302923;
        }
    </style>
</head>

<body>
    <?php include './templates/sidebar.php'; ?>
    <main class="main">
        <?php include './templates/header.php'; ?>
        <div class="menu-page">
            <div class="menu-header">
                <div class="menu-title">
                    <h1>Add Menu</h1>
                    <p>Create a menu, pick products, and set stock per variant.</p>
                </div>
                <div class="duplicate-wrap" id="duplicateWrap">
                    <button type="button" class="duplicate-btn" id="duplicateBtn"><i class="bi bi-files"></i> Duplicate recent menu <i class="bi bi-chevron-down"></i></button>
                    <div class="duplicate-dd">
                        <div class="duplicate-dd-search"><i class="bi bi-search"></i><input type="text" id="duplicateSearch" placeholder="Search menus..."></div>
                        <div class="duplicate-dd-list" id="duplicateList"></div>
                    </div>
                </div>
            </div>

            <div class="menu-form-card">
                <div class="form-card-header">
                    <div class="form-card-icon"><i class="bi bi-journal-text"></i></div>
                    <div>
                        <h3>Menu Details</h3><span>All fields marked with * are required.</span>
                    </div>
                </div>

                <form id="menuForm" class="menu-form" method="POST" novalidate>
                    <div class="row g-3">
                        <div class="col-12">
                            <label>Menu Name <span class="required">*</span></label>
                            <div class="input-icon-wrap"><i class="bi bi-journal-text"></i><input type="text" id="menu_name" class="menu-input" placeholder="Eg: Breakfast Menu" maxlength="150" required></div>
                        </div>
                    </div>

                    <div class="status-toggle-row is-active" id="statusToggleRow">
                        <div class="status-toggle-info">
                            <div class="status-toggle-icon" id="statusToggleIcon"><i class="bi bi-check-circle-fill"></i></div>
                            <div>
                                <h4 id="statusToggleTitle">Active</h4>
                                <p id="statusToggleDesc">Menu will be visible to customers.</p>
                            </div>
                        </div>
                        <label class="mm-switch" for="menu_status"><input type="checkbox" id="menu_status" name="menu_status" checked><span class="mm-switch-slider"></span></label>
                    </div>

                    <div class="datetime-section">
                        <div class="section-head">
                            <div>
                                <h4><i class="bi bi-calendar-range"></i> Time Window <span class="required">*</span></h4>
                            </div>
                        </div>
                        <label>From Date & Time <span class="required">*</span></label>
                        <div class="dt-grid">
                            <input type="date" id="start_date" class="menu-input" value="<?= htmlspecialchars($today) ?>">
                            <input type="time" id="start_time" class="menu-input" value="09:00">
                            <select id="start_ampm" class="menu-input">
                                <option value="AM" selected>AM</option>
                                <option value="PM">PM</option>
                            </select>
                        </div>
                        <label>To Date & Time <span class="required">*</span></label>
                        <div class="dt-grid">
                            <input type="date" id="end_date" class="menu-input" value="<?= htmlspecialchars($tomorrow) ?>">
                            <input type="time" id="end_time" class="menu-input" value="09:00">
                            <select id="end_ampm" class="menu-input">
                                <option value="AM">AM</option>
                                <option value="PM" selected>PM</option>
                            </select>
                        </div>
                        <div id="durationPreview"></div>
                    </div>

                    <div class="products-section">
                        <div class="section-head">
                            <div>
                                <h4><i class="bi bi-box-seam"></i> Products <span class="required">*</span></h4>
                            </div>
                            <button type="button" class="add-product-btn" id="addProductBtn"><i class="bi bi-plus-lg"></i> Add Product</button>
                        </div>
                        <div class="prod-rows" id="prodRows"></div>
                        <div class="prod-empty" id="prodEmpty"><i class="bi bi-box"></i> No products added yet.</div>
                        <button type="button" class="add-product-btn-bottom" id="addProductBtnBottom"><i class="bi bi-plus-circle"></i> Add Another Product</button>
                    </div>

                    <div class="form-actions">
                        <a href="menu.php" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-save" id="saveBtn"><i class="bi bi-check-lg"></i> <span id="saveBtnText">Save Menu</span></button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- ERROR POPUP -->
    <div class="mm-modal-overlay" id="errorOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog">
            <div class="mm-modal-icon error"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <h3 class="mm-modal-title">Oops!</h3>
            <p class="mm-modal-text" id="errorText">Something went wrong.</p>
            <div class="mm-modal-actions"><button type="button" class="mm-btn mm-btn-primary" id="errorOkBtn">Got it</button></div>
        </div>
    </div>

    <!-- ✅ SUCCESS POPUP -->
    <div class="mm-modal-overlay" id="successOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog">
            <div class="mm-modal-icon success"><i class="bi bi-check-lg"></i></div>
            <h3 class="mm-modal-title">Menu Added!</h3>
            <p class="mm-modal-text" id="successText">Menu saved successfully.</p>
            <div class="mm-modal-actions">
                <a href="add-menu.php" class="mm-btn mm-btn-ghost"><i class="bi bi-plus-lg"></i> Add Another</a>
                <a href="menu.php" class="mm-btn mm-btn-primary"><i class="bi bi-list-ul"></i> View Menus</a>
            </div>
        </div>
    </div>

    <script>
        window.ADMIN_URL = "<?= ADMIN_URL; ?>";
        window.PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE); ?>;
        window.RECENT_MENUS = <?= json_encode($recentMenus, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL; ?>js/main.js"></script>
    <script src="<?= ADMIN_URL ?>js/add-menu.js"></script>
</body>

</html>