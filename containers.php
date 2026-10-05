<?php
require_once './config/config.php';
require_once './config/function.php';

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    header('Location: login.php');
    exit;
}
$isAdmin = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin');
$settings = getSettings($pdo);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include './includes/head.php'; ?>

    <style>
        .ct-page { padding: 24px 26px 60px; }

        .ct-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .ct-header h1 {
            font-family: "Playfair Display", serif;
            font-size: 26px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 4px;
        }
        .ct-header p { margin: 0; color: #817a71; font-size: 12.5px; }

        .ct-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }
        .ct-kpi {
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 16px;
            padding: 16px 18px;
        }
        .ct-kpi-label {
            font-size: 10px;
            font-weight: 800;
            color: #948c82;
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 6px;
        }
        .ct-kpi-value {
            font-family: "Playfair Display", serif;
            font-size: 24px;
            font-weight: 700;
            color: #302923;
            line-height: 1.1;
        }
        .ct-kpi-value.warn { color: #b8893c; }
        .ct-kpi-value.money { color: #b51f2c; }
        .ct-kpi-value.ok { color: #1b5e20; }

        .ct-card {
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 20px;
            padding: 22px;
        }

        .ct-list-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .ct-list-title h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #302923;
        }
        .ct-list-title span {
            display: block;
            margin-top: 4px;
            font-size: 10px;
            color: #817a71;
        }

        .ct-filters {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .ct-tabs {
            display: inline-flex;
            background: #fdfaf4;
            border: 1.5px solid #ece5da;
            border-radius: 11px;
            padding: 4px;
            gap: 4px;
        }
        .ct-tab {
            border: none;
            background: transparent;
            padding: 8px 14px;
            font-family: "DM Sans", sans-serif;
            font-size: 11.5px;
            font-weight: 700;
            color: #6f675f;
            border-radius: 8px;
            cursor: pointer;
            transition: .18s ease;
            white-space: nowrap;
        }
        .ct-tab:hover { color: #b51f2c; }
        .ct-tab.active {
            background: #fff;
            color: #b51f2c;
            box-shadow: 0 4px 12px rgba(48, 41, 35, .08);
        }

        .ct-search {
            position: relative;
            width: 250px;
        }
        .ct-search input {
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
            transition: .15s ease;
        }
        .ct-search input:focus {
            border-color: #b51f2c;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(181,31,44,.08);
        }
        .ct-search > i {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0a79c;
            font-size: 14px;
            pointer-events: none;
        }

        /* Customer group cards */
        .ct-group-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .ct-group {
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 16px;
            overflow: hidden;
            transition: .2s ease;
        }
        .ct-group:hover {
            border-color: #d98a91;
            box-shadow: 0 8px 22px rgba(48, 41, 35, .06);
        }
        .ct-group.open {
            border-color: #b51f2c;
            box-shadow: 0 12px 28px rgba(181,31,44,.08);
        }

        .ct-group-head {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            gap: 16px;
            align-items: center;
            padding: 16px 18px;
            cursor: pointer;
            user-select: none;
            transition: .15s ease;
        }
        .ct-group-head:hover { background: #fffdf9; }

        .ct-cust {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }
        .ct-cust-avatar {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 800;
            flex-shrink: 0;
            box-shadow: 0 6px 14px rgba(181,31,44,.2);
        }
        .ct-cust-name {
            font-weight: 800;
            color: #302923;
            font-size: 13.5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .ct-cust-mobile {
            font-size: 11.5px;
            color: #948c82;
            margin-top: 3px;
            font-weight: 600;
        }

        .ct-stat-box {
            text-align: center;
            min-width: 80px;
        }
        .ct-stat-label {
            font-size: 9.5px;
            font-weight: 800;
            color: #948c82;
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 4px;
        }
        .ct-stat-val {
            font-family: "Playfair Display", serif;
            font-size: 16px;
            font-weight: 700;
            color: #302923;
        }
        .ct-stat-val.warn { color: #b8893c; }
        .ct-stat-val.money { color: #b51f2c; }
        .ct-stat-val.ok { color: #1b5e20; }

        .ct-group-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .4px;
            white-space: nowrap;
        }
        .ct-group-status.not_received { background: #fdf1e2; color: #a35a0e; }
        .ct-group-status.partial      { background: #e5eefb; color: #1565c0; }
        .ct-group-status.received     { background: #e8f6ea; color: #1b5e20; }

        .ct-group-arrow {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #f7f2ec;
            color: #6f675f;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            transition: .2s ease;
            flex-shrink: 0;
        }
        .ct-group.open .ct-group-arrow {
            background: #b51f2c;
            color: #fff;
            transform: rotate(90deg);
        }

        /* Expanded orders */
        .ct-group-body {
            display: none;
            border-top: 1.5px dashed #f0ebe4;
            background: #fdfaf4;
            padding: 12px 14px;
        }
        .ct-group.open .ct-group-body {
            display: block;
        }

        .ct-orders {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 12px;
        }

        .ct-order {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            gap: 12px;
            align-items: center;
            background: #fff;
            border: 1px solid #f0ebe4;
            border-radius: 11px;
            padding: 12px 14px;
        }
        .ct-order-code {
            font-weight: 800;
            color: #b51f2c;
            font-size: 12.5px;
        }
        .ct-order-time {
            font-size: 10.5px;
            color: #948c82;
            margin-top: 2px;
        }

        .ct-order-count {
            font-weight: 800;
            color: #302923;
            font-size: 12px;
        }
        .ct-order-count-sub {
            font-size: 10px;
            color: #948c82;
            margin-top: 2px;
        }

        .ct-order-amount {
            font-family: "Playfair Display", serif;
            font-size: 13px;
            font-weight: 700;
            color: #b51f2c;
        }

        .ct-order-status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 9.5px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .ct-order-status.not_received { background: #fdf1e2; color: #a35a0e; }
        .ct-order-status.partial      { background: #e5eefb; color: #1565c0; }
        .ct-order-status.received     { background: #e8f6ea; color: #1b5e20; }

        /* Receive bar (inside expanded area) */
        .ct-receive-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            padding: 12px 14px;
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 11px;
        }
        .ct-receive-bar-lbl {
            font-size: 12px;
            font-weight: 700;
            color: #4e4841;
            flex: 1;
            min-width: 0;
        }
        .ct-receive-bar-lbl strong { color: #b51f2c; }

        .ct-action {
            height: 38px;
            padding: 0 16px;
            border-radius: 10px;
            border: 1.5px solid #eee7dc;
            background: #fff;
            color: #6f675f;
            font-family: "DM Sans", sans-serif;
            font-size: 11.5px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: .15s ease;
            white-space: nowrap;
        }
        .ct-action:hover {
            background: #fbe8e9;
            border-color: #f1c8cc;
            color: #b51f2c;
        }
        .ct-action.receive {
            background: linear-gradient(135deg, #1f7a3d 0%, #14532d 100%);
            color: #fff;
            border: 0;
            box-shadow: 0 4px 12px rgba(31,122,61,.22);
        }
        .ct-action.receive:hover {
            background: linear-gradient(135deg, #166534 0%, #0f3d1f 100%);
            color: #fff;
        }
        .ct-action:disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        .ct-empty {
            text-align: center;
            padding: 60px 20px;
            color: #948c82;
        }
        .ct-empty i { font-size: 40px; color: #ece5da; display: block; margin-bottom: 10px; }

        /* Pagination */
        .ct-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid #f2ede5;
            flex-wrap: wrap;
        }
        .ct-pag-info { font-size: 11px; color: #817a71; }
        .ct-pag-info strong { color: #302923; font-weight: 700; }
        .ct-pag-controls { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
        .ct-pag-controls button {
            min-width: 34px;
            height: 34px;
            border-radius: 9px;
            border: 1px solid #eee7dc;
            background: #fff;
            color: #6f675f;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 8px;
            font-family: inherit;
        }
        .ct-pag-controls button:hover:not(:disabled):not(.active) {
            background: #faf7f0;
            border-color: #e4ddd3;
            color: #302923;
        }
        .ct-pag-controls button.active {
            background: #b51f2c;
            border-color: #b51f2c;
            color: #fff;
            cursor: default;
        }
        .ct-pag-controls button:disabled { opacity: .4; cursor: not-allowed; }

        /* Modal */
        .ct-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(30, 25, 22, .55);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: .22s ease;
            overflow-y: auto;
        }
        .ct-modal-overlay.show { opacity: 1; visibility: visible; }

        .ct-modal {
            background: #fff;
            border-radius: 20px;
            padding: 26px;
            max-width: 520px;
            width: 100%;
            box-shadow: 0 30px 80px rgba(0,0,0,.28);
            transform: translateY(15px) scale(.96);
            transition: transform .25s cubic-bezier(.2,.9,.3,1.2);
            position: relative;
            margin: auto;
        }
        .ct-modal-overlay.show .ct-modal { transform: translateY(0) scale(1); }

        .ct-modal-close {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 1.5px solid #eee7dc;
            background: #fff;
            color: #6f675f;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .ct-modal-close:hover { background: #fbe8e9; border-color: #f1c8cc; color: #b51f2c; }

        .ct-modal-head {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }
        .ct-modal-icon {
            width: 46px;
            height: 46px;
            border-radius: 13px;
            background: linear-gradient(135deg, #1f7a3d 0%, #14532d 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
            box-shadow: 0 8px 20px rgba(31,122,61,.25);
        }
        .ct-modal-head h3 {
            margin: 0;
            font-family: "Playfair Display", serif;
            font-size: 19px;
            font-weight: 700;
            color: #302923;
        }
        .ct-modal-head p {
            margin: 4px 0 0;
            font-size: 12px;
            color: #817a71;
        }

        .ct-info-box {
            background: #fdfaf4;
            border: 1.5px solid #ece5da;
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 16px;
        }
        .ct-info-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 5px 0;
            font-size: 12px;
        }
        .ct-info-row .lbl { color: #948c82; font-weight: 600; }
        .ct-info-row .val { color: #302923; font-weight: 700; text-align: right; }

        .ct-field { margin-bottom: 14px; }
        .ct-field label {
            display: block;
            font-size: 10.5px;
            font-weight: 800;
            color: #4e4841;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .ct-field input,
        .ct-field textarea {
            width: 100%;
            height: 44px;
            border: 1.5px solid #ece5da;
            background: #fffdf9;
            border-radius: 11px;
            padding: 0 14px;
            font-family: "DM Sans", sans-serif;
            font-size: 13px;
            font-weight: 600;
            color: #292521;
            outline: none;
            transition: .15s ease;
        }
        .ct-field textarea {
            height: auto;
            min-height: 70px;
            padding: 12px 14px;
            resize: vertical;
        }
        .ct-field input:focus,
        .ct-field textarea:focus {
            border-color: #b51f2c;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(181,31,44,.08);
        }
        .ct-field .hint {
            font-size: 11px;
            color: #948c82;
            margin-top: 6px;
            font-weight: 600;
        }
        .ct-field .hint.green { color: #1b5e20; }
        .ct-field .hint.red { color: #b51f2c; }

        .ct-quick-btns {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .ct-quick-btn {
            height: 28px;
            padding: 0 12px;
            border-radius: 8px;
            border: 1.5px solid #ece5da;
            background: #fff;
            font-family: "DM Sans", sans-serif;
            font-size: 11px;
            font-weight: 800;
            color: #6f675f;
            cursor: pointer;
            transition: .15s ease;
        }
        .ct-quick-btn:hover {
            background: #fbe8e9;
            border-color: #f1c8cc;
            color: #b51f2c;
        }

        .ct-modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .ct-modal-btn {
            flex: 1;
            height: 46px;
            border-radius: 11px;
            border: none;
            font-family: "DM Sans", sans-serif;
            font-size: 12.5px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            transition: .18s ease;
        }
        .ct-modal-btn.primary {
            background: linear-gradient(135deg, #1f7a3d 0%, #14532d 100%);
            color: #fff;
            box-shadow: 0 8px 20px rgba(31,122,61,.25);
        }
        .ct-modal-btn.primary:hover:not(:disabled) { transform: translateY(-1px); }
        .ct-modal-btn.primary:disabled { opacity: .55; cursor: not-allowed; }
        .ct-modal-btn.ghost {
            background: #fff;
            border: 1.5px solid #e4ddd3;
            color: #6f675f;
        }
        .ct-modal-btn.ghost:hover { background: #faf7f0; }

        .ct-spinner {
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: ctSpin .7s linear infinite;
            display: inline-block;
        }
        @keyframes ctSpin { to { transform: rotate(360deg); } }

        /* Toast */
        .ct-toast-wrap {
            position: fixed;
            top: 22px;
            right: 22px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 10000;
            pointer-events: none;
        }
        .ct-toast {
            min-width: 260px;
            max-width: 360px;
            background: #fff;
            border-radius: 12px;
            padding: 13px 15px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 12px;
            color: #302923;
            border: 1px solid #eee7dc;
            box-shadow: 0 14px 34px rgba(0,0,0,.14);
            transform: translateX(120%);
            opacity: 0;
            transition: transform .3s cubic-bezier(.2,.9,.3,1.2), opacity .3s ease;
            pointer-events: auto;
        }
        .ct-toast.show { transform: translateX(0); opacity: 1; }
        .ct-toast-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            flex-shrink: 0;
            color: #fff;
        }
        .ct-toast.success .ct-toast-icon { background: #4caf50; }
        .ct-toast.error   .ct-toast-icon { background: #c62828; }
        .ct-toast.info    .ct-toast-icon { background: #3b82f6; }
        .ct-toast-body { flex: 1; padding-top: 3px; line-height: 1.5; }
        .ct-toast-close {
            background: transparent;
            border: 0;
            color: #b5aca2;
            cursor: pointer;
            font-size: 14px;
            padding: 0 2px;
            line-height: 1;
        }

        @media (max-width: 768px) {
            .ct-page { padding: 18px 14px 40px; }
            .ct-card { padding: 17px; border-radius: 17px; }
            .ct-list-head { flex-direction: column; align-items: stretch; }
            .ct-filters { flex-direction: column; align-items: stretch; }
            .ct-search { width: 100%; }

            .ct-group-head {
                grid-template-columns: 1fr auto;
                row-gap: 10px;
            }
            .ct-stat-box { text-align: left; }
            .ct-stat-label { text-align: left; }

            .ct-order {
                grid-template-columns: 1fr auto;
                row-gap: 8px;
            }

            .ct-pagination { flex-direction: column; align-items: stretch; }
            .ct-pag-info, .ct-pag-controls { text-align: center; justify-content: center; }
        }

        @media (max-width: 480px) {
            .ct-toast-wrap { top: 14px; right: 14px; left: 14px; }
            .ct-toast { min-width: 0; width: 100%; }
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

        <div class="ct-page">

            <div class="ct-header">
                <div>
                    <h1>Container Management</h1>
                    <p>Grouped by customer — expand to see all their orders and receive containers.</p>
                </div>
                <button type="button" class="ct-action" id="ctRefresh" style="height:42px;padding:0 16px;font-size:11.5px;">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>

            <div class="ct-kpis">
                <div class="ct-kpi">
                    <div class="ct-kpi-label">Customers</div>
                    <div class="ct-kpi-value" id="kpiTotal">0</div>
                </div>
                <div class="ct-kpi">
                    <div class="ct-kpi-label">Not Received</div>
                    <div class="ct-kpi-value warn" id="kpiNotReceived">0</div>
                </div>
                <div class="ct-kpi">
                    <div class="ct-kpi-label">Pending Containers</div>
                    <div class="ct-kpi-value warn" id="kpiPending">0</div>
                </div>
                <div class="ct-kpi">
                    <div class="ct-kpi-label">Pending Amount</div>
                    <div class="ct-kpi-value money" id="kpiAmount">₹0</div>
                </div>
                <div class="ct-kpi">
                    <div class="ct-kpi-label">Refunded Amount</div>
                    <div class="ct-kpi-value ok" id="kpiRefunded">₹0</div>
                </div>
            </div>

            <div class="ct-card">

                <div class="ct-list-head">
                    <div class="ct-list-title">
                        <h3>Container Records by Customer</h3>
                        <span>Click any customer row to view their orders</span>
                    </div>

                    <div class="ct-filters">
                        <div class="ct-tabs" id="ctTabs">
                            <button type="button" class="ct-tab active" data-status="all">All</button>
                            <button type="button" class="ct-tab" data-status="not_received">Not Received</button>
                            <button type="button" class="ct-tab" data-status="partial">Partial</button>
                            <button type="button" class="ct-tab" data-status="received">Received</button>
                        </div>

                        <div class="ct-search">
                            <i class="bi bi-search"></i>
                            <input type="text" id="ctSearch" placeholder="Search customer, mobile, order...">
                        </div>
                    </div>
                </div>

                <div id="ctGroupList" class="ct-group-list">
                    <div style="text-align:center;padding:40px;color:#948c82;">
                        <span class="ct-spinner" style="border-color:#ece5da;border-top-color:#b51f2c;"></span>
                    </div>
                </div>

                <div class="ct-pagination" id="ctPagination" style="display:none;">
                    <div class="ct-pag-info" id="ctPagInfo">Showing <strong>0</strong>–<strong>0</strong> of <strong>0</strong></div>
                    <div class="ct-pag-controls" id="ctPagControls"></div>
                </div>

            </div>

        </div>
    </main>


    <!-- ============ RECEIVE MODAL ============ -->
    <div class="ct-modal-overlay" id="ctReceiveModal" aria-hidden="true">
        <div class="ct-modal">
            <button type="button" class="ct-modal-close" id="ctModalClose">
                <i class="bi bi-x-lg"></i>
            </button>

            <div class="ct-modal-head">
                <div class="ct-modal-icon">
                    <i class="bi bi-box2-heart"></i>
                </div>
                <div>
                    <h3>Receive Containers</h3>
                    <p id="ctModalSub">Refund will be credited to the customer's wallet.</p>
                </div>
            </div>

            <div class="ct-info-box" id="ctInfoBox"></div>

            <form id="ctReceiveForm" autocomplete="off">
                <input type="hidden" id="ctGroupKey" value="">

                <div class="ct-field">
                    <label>Received Containers <span style="color:#b51f2c;">*</span></label>
                    <input type="number" id="ctReceivedCount" min="1" step="1" required placeholder="0">
                    <div class="ct-quick-btns">
                        <button type="button" class="ct-quick-btn" data-qty="1">1</button>
                        <button type="button" class="ct-quick-btn" data-qty="2">2</button>
                        <button type="button" class="ct-quick-btn" data-qty="5">5</button>
                        <button type="button" class="ct-quick-btn" id="ctFullBtn">Full</button>
                    </div>
                    <div class="hint" id="ctQtyHint">—</div>
                </div>

                <div class="ct-field">
                    <label>Note (optional)</label>
                    <textarea id="ctNote" maxlength="250" placeholder="e.g. returned in good condition"></textarea>
                </div>

                <div class="ct-info-box" style="background:#e8f6ea;border-color:#a7c8a9;margin-top:8px;">
                    <div class="ct-info-row">
                        <span class="lbl" style="color:#1b5e20;">Refund to wallet</span>
                        <span class="val" id="ctRefundAmount" style="color:#1b5e20;">₹0</span>
                    </div>
                </div>

                <div class="ct-modal-actions">
                    <button type="button" class="ct-modal-btn ghost" id="ctCancel">Cancel</button>
                    <button type="submit" class="ct-modal-btn primary" id="ctSubmit">
                        <i class="bi bi-check-lg"></i>
                        <span id="ctSubmitText">Mark Received</span>
                    </button>
                </div>
            </form>
        </div>
    </div>


    <div class="ct-toast-wrap" id="ctToastWrap"></div>

    <script>
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
        window.IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL ?>js/main.js"></script>
    <script src="<?= ADMIN_URL ?>js/containers.js"></script>

</body>

</html>