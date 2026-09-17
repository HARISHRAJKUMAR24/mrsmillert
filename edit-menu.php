<?php
require_once './config/config.php';
require_once './config/function.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: menu.php');
    exit;
}

/* =========================================================
   LOAD PRODUCTS + VARIANTS
   ========================================================= */

$products = [];

try {

    $stmt = $pdo->query(
        "SELECT id, product_code, product_name
         FROM products
         WHERE status = 1
         ORDER BY product_name ASC"
    );
    $products = $stmt->fetchAll();

    $variantsByProduct = [];

    if (!empty($products)) {
        $codes = array_column($products, 'product_code');
        $placeholders = implode(',', array_fill(0, count($codes), '?'));

        $vStmt = $pdo->prepare(
            "SELECT id, product_code, quantity, quantity_unit, quantity_name, price
             FROM product_variants
             WHERE product_code IN ($placeholders)
               AND status = 1
             ORDER BY id ASC"
        );
        $vStmt->execute($codes);

        foreach ($vStmt->fetchAll() as $v) {
            $variantsByProduct[$v['product_code']][] = $v;
        }
    }

    foreach ($products as &$p) {
        $p['variants'] = $variantsByProduct[$p['product_code']] ?? [];
    }
} catch (PDOException $e) {
    $products = [];
}

/* =========================================================
   LOAD MENU + ITS PRODUCTS
   ========================================================= */

$menu = null;
$menuProducts = [];

try {

    $stmt = $pdo->prepare("SELECT * FROM menus WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $menu = $stmt->fetch();

    if (!$menu) {
        header('Location: menu.php');
        exit;
    }

    $stmt2 = $pdo->prepare(
        "SELECT product_code, variant_id, stock_unlimited, stock_count
         FROM menu_products
         WHERE menu_code = ?
         ORDER BY id ASC"
    );
    $stmt2->execute([$menu['menu_code']]);
    $menuProducts = $stmt2->fetchAll();
} catch (PDOException $e) {
    header('Location: menu.php');
    exit;
}

/* Group menu products by product_code → array of variants */
$grouped = [];
foreach ($menuProducts as $mp) {
    $grouped[$mp['product_code']][] = $mp;
}

/* Start/End for the date+time inputs */
$startTs = strtotime($menu['start_at']);
$endTs   = strtotime($menu['end_at']);

$startDateVal = date('Y-m-d', $startTs);
$endDateVal   = date('Y-m-d', $endTs);

function to12h(string $dbDateTime): array
{
    $ts = strtotime($dbDateTime);
    $h24 = (int) date('H', $ts);
    $m   = (int) date('i', $ts);
    $ampm = $h24 >= 12 ? 'PM' : 'AM';
    $h12  = $h24 % 12;
    if ($h12 === 0) $h12 = 12;
    return [sprintf('%02d:%02d', $h12, $m), $ampm];
}
[$startTime12, $startAmPm] = to12h($menu['start_at']);
[$endTime12,   $endAmPm]   = to12h($menu['end_at']);

/* Status */
$menuStatus = isset($menu['status']) ? (int)$menu['status'] : 1;
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

    <style>
        .menu-page { padding: 30px 32px 40px; }

        .menu-header {
            display: flex; align-items: center; justify-content: space-between;
            gap: 20px; margin-bottom: 25px;
        }

        .menu-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px; font-weight: 700;
            color: #302923; margin: 0 0 5px;
        }
        .menu-title p { margin: 0; color: #817a71; font-size: 13px; }

        .back-btn {
            display: inline-flex; align-items: center; gap: 8px;
            border: 1px solid #e4ddd3; background: #fff; color: #6f675f;
            padding: 10px 16px; border-radius: 10px;
            font-size: 11px; font-weight: 700; text-decoration: none;
        }

        .menu-form-card {
            background: #fff; border: 1px solid #eee7dc;
            border-radius: 20px; padding: 25px; max-width: 1100px;
            margin-bottom: 24px;
        }

        .form-card-header {
            display: flex; align-items: center; gap: 12px; margin-bottom: 22px;
        }
        .form-card-icon {
            width: 42px; height: 42px; border-radius: 12px;
            background: #fbe8e9; color: #b51f2c;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
        }
        .form-card-header h3 { margin: 0; font-size: 16px; font-weight: 700; }
        .form-card-header span { display: block; margin-top: 3px; color: #817a71; font-size: 10px; }

        .menu-code-tag {
            margin-left: auto;
            display: inline-block;
            background: #faf7f0; border: 1px dashed #d8c9b8;
            color: #6f5a3f; font-size: 10px; font-weight: 800;
            padding: 4px 10px; border-radius: 7px; letter-spacing: 1px;
        }

        .menu-form label {
            display: block; font-size: 11px; font-weight: 700;
            color: #4e4841; margin-bottom: 7px;
        }
        .required { color: #b51f2c; }

        .menu-input {
            width: 100%; height: 45px;
            border: 1px solid #e8e1d8; background: #fffdf9;
            border-radius: 11px; padding: 11px 13px;
            font-family: "DM Sans", sans-serif; font-size: 12px;
            color: #292521; outline: none; transition: .2s ease;
        }
        .menu-input:focus {
            border-color: #d98a91; background: #fff;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .input-icon-wrap { position: relative; }
        .input-icon-wrap > i {
            position: absolute; left: 13px; top: 50%;
            transform: translateY(-50%); color: #aaa198;
            font-size: 15px; pointer-events: none;
        }
        .input-icon-wrap .menu-input { padding-left: 40px; }

        /* =====================================================
           STATUS TOGGLE
        ===================================================== */

        .status-toggle-row {
            display: flex; align-items: center; justify-content: space-between;
            gap: 14px;
            padding: 14px 16px;
            background: #fffdf9;
            border: 1px solid #e8e1d8;
            border-radius: 12px;
            margin-top: 16px;
        }
        .status-toggle-info { display: flex; align-items: center; gap: 12px; }
        .status-toggle-icon {
            width: 38px; height: 38px; border-radius: 10px;
            background: #fde6e6; color: #c62828;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
            transition: .25s;
        }
        .status-toggle-row.is-active .status-toggle-icon {
            background: #e8f6ea; color: #2e7d32;
        }
        .status-toggle-info h4 { margin: 0; font-size: 13px; font-weight: 700; color: #302923; }
        .status-toggle-info p { margin: 2px 0 0; font-size: 10px; color: #948c82; }

        .mm-switch {
            position: relative; display: inline-block;
            width: 46px; height: 26px; flex-shrink: 0;
        }
        .mm-switch input { opacity: 0; width: 0; height: 0; }
        .mm-switch-slider {
            position: absolute; cursor: pointer; inset: 0;
            background: #d8d2c9; border-radius: 30px; transition: .25s;
        }
        .mm-switch-slider::before {
            content: ""; position: absolute;
            height: 20px; width: 20px; left: 3px; bottom: 3px;
            background: #fff; border-radius: 50%;
            transition: .25s; box-shadow: 0 2px 4px rgba(0,0,0,.15);
        }
        .mm-switch input:checked + .mm-switch-slider { background: #2e7d32; }
        .mm-switch input:checked + .mm-switch-slider::before { transform: translateX(20px); }

        /* TIME WINDOW */
        .datetime-section { margin-top: 6px; padding-top: 20px; }

        .section-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; margin-bottom: 14px; flex-wrap: wrap;
        }
        .section-head h4 {
            margin: 0; font-size: 13px; font-weight: 800; color: #302923;
            display: flex; align-items: center; gap: 8px;
        }
        .section-head h4 i {
            width: 30px; height: 30px; border-radius: 9px;
            background: #fbe8e9; color: #b51f2c;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
        }
        .section-head p {
            margin: 4px 0 0 38px;
            font-size: 10px; color: #817a71;
        }

        .dt-grid {
            display: grid;
            grid-template-columns: 1fr 150px 110px;
            gap: 10px; margin-bottom: 14px;
        }
        .dt-grid .menu-input { height: 42px; padding: 10px 12px; }
        #durationPreview { font-size: 10px; color: #2e7d32; padding: 6px 0; }

        /* PRODUCTS */
        .products-section {
            margin-top: 22px; padding-top: 20px;
            border-top: 1px solid #f0ebe4;
        }

        .add-product-btn {
            display: inline-flex; align-items: center; gap: 6px;
            background: #b51f2c; color: #fff;
            border: none; border-radius: 10px;
            padding: 9px 15px;
            font-size: 11px; font-weight: 700;
            cursor: pointer; transition: .2s;
        }
        .add-product-btn:hover { background: #8e1722; }

        .add-product-btn-bottom {
            display: flex; align-items: center; justify-content: center;
            gap: 8px;
            margin-top: 14px;
            padding: 14px;
            width: 100%;
            background: #fffaf9;
            border: 2px dashed #e4ddd3;
            border-radius: 12px;
            color: #b51f2c;
            font-size: 12px; font-weight: 800;
            cursor: pointer;
            transition: .2s;
        }
        .add-product-btn-bottom:hover {
            background: #fff5f5;
            border-color: #d98a91;
        }
        .add-product-btn-bottom i { font-size: 16px; }

        .prod-rows {
            display: flex; flex-direction: column; gap: 14px;
            margin-top: 14px;
        }

        .prod-row {
            border: 1px solid #f0ebe4;
            background: #fffdf9;
            border-radius: 14px;
            padding: 16px;
            animation: prodIn .25s ease;
        }
        @keyframes prodIn {
            0% { transform: translateY(-6px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }

        .prod-row-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 10px; margin-bottom: 14px;
        }
        .prod-row-badge {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 10px; font-weight: 800;
            background: #f1e8df; color: #755d48;
            padding: 4px 9px; border-radius: 6px;
            letter-spacing: .3px;
        }
        .prod-row-badge i { color: #b51f2c; font-size: 11px; }

        .prod-row-remove {
            background: #fff;
            border: 1px solid #f0d6d8;
            color: #b51f2c;
            width: 30px; height: 30px;
            border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer; transition: .2s;
            font-size: 13px;
        }
        .prod-row-remove:hover {
            background: #fde6e6; border-color: #f5c0c0;
            color: #c62828;
        }

        .prod-row-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px; margin-bottom: 12px;
        }
        .prod-row .field-label {
            font-size: 10px; font-weight: 700; color: #6f675f;
            margin-bottom: 5px; display: block;
        }

        .dd { position: relative; }
        .dd-trigger {
            width: 100%; min-height: 42px;
            border: 1px solid #e8e1d8; background: #fff;
            border-radius: 10px; padding: 8px 34px 8px 12px;
            font-family: "DM Sans", sans-serif; font-size: 12px;
            color: #292521; outline: none; transition: .2s ease;
            text-align: left; cursor: pointer;
            display: flex; align-items: center; flex-wrap: wrap; gap: 5px;
            position: relative;
        }
        .dd-trigger:hover { border-color: #d5cbbd; }
        .dd.open .dd-trigger,
        .dd-trigger:focus {
            border-color: #d98a91;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }
        .dd-trigger.disabled {
            background: #f5f2ec; color: #a09a90; cursor: not-allowed;
        }
        .dd-placeholder { color: #a8a09a; font-size: 12px; }
        .dd-arrow {
            position: absolute; right: 12px; top: 50%;
            transform: translateY(-50%);
            color: #aaa198; font-size: 13px; transition: .2s;
        }
        .dd.open .dd-arrow { transform: translateY(-50%) rotate(180deg); }

        .dd-chips { display: flex; flex-wrap: wrap; gap: 5px; }
        .dd-chip {
            background: #fff5f5; color: #b51f2c;
            border: 1px solid #f3c8cc;
            padding: 3px 8px; border-radius: 6px;
            font-size: 10px; font-weight: 700;
            display: inline-flex; align-items: center; gap: 5px;
        }
        .dd-chip .x { cursor: pointer; font-size: 11px; line-height: 1; opacity: .8; }
        .dd-chip .x:hover { opacity: 1; }

        .dd-panel {
            position: absolute; top: calc(100% + 6px); left: 0; right: 0;
            background: #fff;
            border: 1px solid #e8e1d8;
            border-radius: 12px;
            box-shadow: 0 18px 50px rgba(0, 0, 0, .12);
            z-index: 60; overflow: hidden;
            display: none;
        }
        .dd.open .dd-panel { display: block; }

        .dd-search {
            padding: 10px;
            border-bottom: 1px solid #f0ebe4;
            position: relative;
        }
        .dd-search i {
            position: absolute; left: 22px; top: 50%;
            transform: translateY(-50%);
            color: #aaa198; font-size: 13px;
        }
        .dd-search input {
            width: 100%; height: 38px;
            border: 1px solid #e8e1d8; background: #fffdf9;
            border-radius: 9px; padding: 0 12px 0 34px;
            font-family: "DM Sans", sans-serif; font-size: 12px;
            outline: none; transition: .2s;
        }
        .dd-search input:focus {
            border-color: #d98a91; background: #fff;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .dd-list {
            max-height: 240px; overflow-y: auto;
            padding: 6px;
        }
        .dd-list::-webkit-scrollbar { width: 6px; }
        .dd-list::-webkit-scrollbar-thumb {
            background: #e4ddd3; border-radius: 6px;
        }

        .dd-item {
            padding: 9px 12px;
            border-radius: 8px;
            font-size: 11px;
            color: #302923;
            cursor: pointer;
            display: flex; align-items: center; gap: 10px;
            transition: .15s;
        }
        .dd-item:hover { background: #fffaf9; }
        .dd-item.selected { background: #fff5f5; }

        .dd-item .check {
            width: 16px; height: 16px;
            border: 1.5px solid #d5cbbd;
            border-radius: 5px;
            flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 10px;
            transition: .15s;
        }
        .dd-item.selected .check {
            background: #b51f2c; border-color: #b51f2c;
        }
        .dd-item .check i { opacity: 0; }
        .dd-item.selected .check i { opacity: 1; }

        .dd-item .info { flex: 1; min-width: 0; }
        .dd-item .nm { font-weight: 700; }

        .dd-item .meta {
            font-size: 9px; color: #6f5a3f;
            background: #faf7f0;
            padding: 2px 6px; border-radius: 5px;
        }
        .dd-item .price {
            color: #b51f2c; font-weight: 800; font-size: 10px;
        }

        .dd-empty {
            padding: 20px 14px;
            text-align: center;
            color: #948c82; font-size: 11px;
        }
        .dd-empty i {
            font-size: 22px; color: #d5cbbd;
            display: block; margin-bottom: 6px;
        }

        .stock-mode-group {
            display: flex; gap: 12px;
            align-items: flex-end;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed #f0ebe4;
        }
        .stock-mode-box { flex: 1; }
        .stock-count-box { flex: 1; }

        .custom-check {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 10px 14px;
            background: #fff;
            border: 1px solid #e8e1d8;
            border-radius: 10px;
            font-size: 11px; font-weight: 700; color: #302923;
            cursor: pointer; user-select: none;
            transition: .2s;
            width: 100%; height: 42px;
            box-sizing: border-box;
        }
        .custom-check:hover { border-color: #d5cbbd; }
        .custom-check input { display: none; }
        .custom-check .box {
            width: 20px; height: 20px;
            border: 1.5px solid #d5cbbd;
            border-radius: 6px;
            display: inline-flex; align-items: center; justify-content: center;
            background: #fff; color: #fff;
            font-size: 12px; transition: .18s; flex-shrink: 0;
        }
        .custom-check .box i { opacity: 0; transition: .18s; line-height: 1; }
        .custom-check input:checked + .box {
            background: #2e7d32; border-color: #2e7d32;
        }
        .custom-check input:checked + .box i { opacity: 1; }
        .custom-check.checked {
            background: #f0f9f1; border-color: #b6e0bd;
        }
        .custom-check-text { display: inline-block; line-height: 1; white-space: nowrap; }

        .stock-count-box input {
            width: 100%; height: 42px;
            border: 1px solid #e8e1d8; background: #fff;
            border-radius: 10px; padding: 10px 12px;
            font-family: "DM Sans", sans-serif; font-size: 12px;
            color: #292521; outline: none; transition: .2s ease;
        }
        .stock-count-box input:focus {
            border-color: #d98a91;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }
        .stock-count-box input:disabled {
            background: #f5f2ec; color: #a09a90; cursor: not-allowed;
        }

        .prod-empty {
            text-align: center; padding: 30px 20px;
            border: 2px dashed #e4ddd3; border-radius: 14px;
            color: #948c82; font-size: 11px; background: #fffdf9;
        }
        .prod-empty i {
            font-size: 30px; color: #d5cbbd;
            display: block; margin-bottom: 8px;
        }

        .form-actions {
            display: flex; justify-content: flex-end; gap: 10px;
            margin-top: 22px; padding-top: 20px; border-top: 1px solid #f0ebe4;
        }
        .btn-cancel {
            border: 1px solid #e4ddd3; background: #fff; color: #6f675f;
            border-radius: 10px; padding: 10px 17px;
            font-size: 11px; font-weight: 700; text-decoration: none;
        }
        .btn-save {
            border: none; background: #b51f2c; color: #fff;
            border-radius: 10px; padding: 10px 19px;
            font-size: 11px; font-weight: 700;
            display: inline-flex; align-items: center; gap: 7px;
            cursor: pointer; transition: .2s;
        }
        .btn-save:hover { background: #8e1722; }
        .btn-save:disabled { opacity: .7; cursor: not-allowed; }

        .btn-spinner {
            width: 14px; height: 14px;
            border: 2px solid rgba(255, 255, 255, .4);
            border-top-color: #fff; border-radius: 50%;
            animation: spin .7s linear infinite; display: inline-block;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .form-loading {
            text-align: center; padding: 60px 20px;
            color: #948c82; font-size: 12px;
        }
        .form-loading .table-spinner {
            width: 26px; height: 26px;
            border: 3px solid #eee7dc; border-top-color: #b51f2c;
            border-radius: 50%; animation: spin .7s linear infinite;
            display: inline-block; margin-bottom: 10px;
        }

        .mm-modal-overlay {
            position: fixed; inset: 0;
            background: rgba(30, 25, 22, .55);
            backdrop-filter: blur(3px);
            display: flex; align-items: center; justify-content: center;
            padding: 20px; z-index: 9999;
            opacity: 0; visibility: hidden; transition: .2s;
        }
        .mm-modal-overlay.show { opacity: 1; visibility: visible; }
        .mm-modal {
            background: #fff; border-radius: 18px;
            padding: 30px 26px 24px;
            max-width: 420px; width: 100%; text-align: center;
            box-shadow: 0 30px 80px rgba(0, 0, 0, .25);
            transform: translateY(15px) scale(.96);
            transition: transform .25s cubic-bezier(.2, .9, .3, 1.2);
        }
        .mm-modal-overlay.show .mm-modal { transform: translateY(0) scale(1); }
        .mm-modal-icon {
            width: 66px; height: 66px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 30px; margin: 0 auto 16px;
            animation: popIn .35s cubic-bezier(.2, .9, .3, 1.4);
        }
        .mm-modal-icon.success { background: #e8f6ea; color: #2e7d32; }
        .mm-modal-icon.error   { background: #fde6e6; color: #c62828; }

        @keyframes popIn {
            0% { transform: scale(.5); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .mm-modal-title { margin: 0 0 8px; font-size: 18px; font-weight: 800; color: #302923; }
        .mm-modal-text { margin: 0 0 20px; font-size: 12px; color: #756d65; line-height: 1.6; }
        .mm-modal-actions { display: flex; gap: 10px; }
        .mm-btn {
            flex: 1; height: 44px; border-radius: 11px; border: none;
            font-size: 12px; font-weight: 700; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
            gap: 7px; text-decoration: none; transition: .2s ease;
        }
        .mm-btn-primary { background: #b51f2c; color: #fff; }
        .mm-btn-primary:hover { background: #8e1722; color: #fff; }
        .mm-btn-ghost {
            background: #fff; border: 1px solid #e4ddd3; color: #6f675f;
        }
        .mm-btn-ghost:hover { background: #faf7f0; }

        @media (max-width: 768px) {
            .menu-page { padding: 20px 15px 30px; }
            .menu-header { flex-direction: column; align-items: flex-start; }
            .menu-form-card { padding: 17px; border-radius: 17px; }
            .dt-grid { grid-template-columns: 1fr; }
            .prod-row-grid { grid-template-columns: 1fr; }
            .stock-mode-group { flex-direction: column; align-items: stretch; }
            .form-actions { flex-direction: column-reverse; }
            .btn-cancel, .btn-save { width: 100%; justify-content: center; }
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


        <div class="menu-page">

            <div class="menu-header">
                <div class="menu-title">
                    <h1>Edit Menu</h1>
                    <p>Update name, status, time window, products and stock.</p>
                </div>

                <a href="menu.php" class="back-btn">
                    <i class="bi bi-arrow-left"></i>
                    Back to List
                </a>
            </div>


            <div class="menu-form-card">

                <div class="form-card-header">
                    <div class="form-card-icon">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <div>
                        <h3>Menu Details</h3>
                        <span>Changes save on Update.</span>
                    </div>
                    <span class="menu-code-tag" id="menuCodeTag">
                        #<?= htmlspecialchars($menu['menu_code']) ?>
                    </span>
                </div>

                <div id="formLoading" class="form-loading" style="display:none;">
                    <div class="table-spinner"></div>
                    <div>Loading...</div>
                </div>

                <form id="menuForm" class="menu-form" method="POST" novalidate>

                    <input type="hidden" id="menu_id" value="<?= (int)$menu['id'] ?>">

                    <div class="row g-3">

                        <div class="col-12">
                            <label>Menu Name <span class="required">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="bi bi-journal-text"></i>
                                <input type="text"
                                    id="menu_name"
                                    class="menu-input"
                                    maxlength="150"
                                    value="<?= htmlspecialchars($menu['menu_name']) ?>"
                                    required>
                            </div>
                        </div>

                    </div>


                    <!-- ==================== STATUS TOGGLE ==================== -->

                    <div class="status-toggle-row <?= $menuStatus === 1 ? 'is-active' : '' ?>" id="statusToggleRow">
                        <div class="status-toggle-info">
                            <div class="status-toggle-icon" id="statusToggleIcon">
                                <i class="bi <?= $menuStatus === 1 ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                            </div>
                            <div>
                                <h4 id="statusToggleTitle"><?= $menuStatus === 1 ? 'Active' : 'Inactive' ?></h4>
                                <p id="statusToggleDesc"><?= $menuStatus === 1
                                    ? 'Menu will be visible to customers.'
                                    : 'Menu will be hidden from customers.' ?></p>
                            </div>
                        </div>
                        <label class="mm-switch" for="menu_status">
                            <input type="checkbox" id="menu_status" name="menu_status" <?= $menuStatus === 1 ? 'checked' : '' ?>>
                            <span class="mm-switch-slider"></span>
                        </label>
                    </div>


                    <!-- ==================== TIME WINDOW ==================== -->

                    <div class="datetime-section">

                        <div class="section-head">
                            <div>
                                <h4><i class="bi bi-calendar-range"></i> Time Window <span class="required">*</span></h4>
                                <p>Common for the whole menu.</p>
                            </div>
                        </div>

                        <label>From Date & Time <span class="required">*</span></label>
                        <div class="dt-grid">
                            <input type="date" id="start_date" class="menu-input"
                                value="<?= htmlspecialchars($startDateVal) ?>">
                            <input type="time" id="start_time" class="menu-input"
                                value="<?= htmlspecialchars($startTime12) ?>">
                            <select id="start_ampm" class="menu-input">
                                <option value="AM" <?= $startAmPm === 'AM' ? 'selected' : '' ?>>AM</option>
                                <option value="PM" <?= $startAmPm === 'PM' ? 'selected' : '' ?>>PM</option>
                            </select>
                        </div>

                        <label>To Date & Time <span class="required">*</span></label>
                        <div class="dt-grid">
                            <input type="date" id="end_date" class="menu-input"
                                value="<?= htmlspecialchars($endDateVal) ?>">
                            <input type="time" id="end_time" class="menu-input"
                                value="<?= htmlspecialchars($endTime12) ?>">
                            <select id="end_ampm" class="menu-input">
                                <option value="AM" <?= $endAmPm === 'AM' ? 'selected' : '' ?>>AM</option>
                                <option value="PM" <?= $endAmPm === 'PM' ? 'selected' : '' ?>>PM</option>
                            </select>
                        </div>

                        <div id="durationPreview"></div>

                    </div>


                    <!-- ==================== PRODUCTS ==================== -->

                    <div class="products-section">

                        <div class="section-head">
                            <div>
                                <h4><i class="bi bi-box-seam"></i> Products <span class="required">*</span></h4>
                                <p>Add / change products and their variants with stock.</p>
                            </div>
                            <button type="button" class="add-product-btn" id="addProductBtn">
                                <i class="bi bi-plus-lg"></i> Add Product
                            </button>
                        </div>

                        <div class="prod-rows" id="prodRows"></div>

                        <div class="prod-empty" id="prodEmpty">
                            <i class="bi bi-box"></i>
                            No products added yet. Click <strong>Add Product</strong> to start.
                        </div>

                        <button type="button" class="add-product-btn-bottom" id="addProductBtnBottom">
                            <i class="bi bi-plus-circle"></i>
                            Add Another Product
                        </button>

                    </div>


                    <div class="form-actions">
                        <a href="menu.php" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-save" id="saveBtn">
                            <i class="bi bi-check-lg"></i>
                            <span id="saveBtnText">Update Menu</span>
                        </button>
                    </div>

                </form>

            </div>

        </div>

    </main>


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
            <h3 class="mm-modal-title">Menu Updated!</h3>
            <p class="mm-modal-text" id="successText">Menu updated successfully.</p>
            <div class="mm-modal-actions">
                <a href="menu.php" class="mm-btn mm-btn-ghost">
                    <i class="bi bi-list-ul"></i> Back to List
                </a>
                <button type="button" class="mm-btn mm-btn-primary" id="stayBtn">
                    <i class="bi bi-pencil"></i> Stay Here
                </button>
            </div>
        </div>
    </div>


    <script>
        window.ADMIN_URL = "<?= ADMIN_URL; ?>";
        window.PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
        window.MENU_ID = <?= (int)$menu['id'] ?>;
        window.MENU_ROWS = <?= json_encode(array_map(function ($pcode, $items) {
                                return [
                                    'product_code' => $pcode,
                                    'variant_ids'  => array_map(fn($i) => (int)$i['variant_id'], $items),
                                    'stock_unlimited' => (int)$items[0]['stock_unlimited'],
                                    'stock_count'  => (int)$items[0]['stock_count']
                                ];
                            }, array_keys($grouped), array_values($grouped)), JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL; ?>js/main.js"></script>
    <script src="<?= ADMIN_URL; ?>js/edit-menu.js"></script>

</body>

</html>