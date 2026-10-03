<?php
require_once './config/config.php';
require_once './config/function.php';

/* ---------------- AUTH (admin + staff allowed) ---------------- */
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    header('Location: login.php');
    exit;
}

$settings = getSettings($pdo);
$siteName = $settings['username'] ?? 'Mrs Mill@';

/* ---------------- FETCH MENUS ---------------- */
$menus = [];
try {
    $menus = $pdo->query(
        "SELECT id, menu_code, menu_name, start_at, end_at, status
         FROM menus
         ORDER BY id DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

/* ---------------- FETCH DELIVERY BOYS ---------------- */
$boys = [];
try {
    $boys = $pdo->query(
        "SELECT id, delivery_code, full_name, mobile_number
         FROM delivery_boys
         WHERE status = 1
         ORDER BY full_name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

$menusJson = json_encode($menus, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$boysJson  = json_encode($boys,  JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

    <style>
        .as-page {
            padding: 30px 32px 60px;
        }

        .as-header {
            margin-bottom: 22px;
        }

        .as-header h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 5px;
        }

        .as-header p {
            margin: 0;
            color: #817a71;
            font-size: 13px;
        }

        /* =====================================================
           STICKY FORM CARD
           ===================================================== */
        .as-card {
            background: #fff;
            border: 1.5px solid #eee7dc;
            border-radius: 20px;
            padding: 20px 22px;
            margin-bottom: 22px;
            position: sticky;
            top: 76px;
            z-index: 40;
            box-shadow: 0 6px 22px rgba(48, 41, 35, .06);
        }

        .as-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .as-field label {
            display: block;
            font-size: 11px;
            font-weight: 800;
            color: #4e4841;
            margin-bottom: 7px;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .as-field label .req {
            color: #b51f2c;
        }

        /* =====================================================
           SEARCHABLE DROPDOWN
           ===================================================== */
        .sd-wrap {
            position: relative;
        }

        .sd-toggle {
            width: 100%;
            height: 46px;
            border: 1.5px solid #ece5da;
            background: #fffdf9;
            border-radius: 12px;
            padding: 0 40px 0 42px;
            font-family: "DM Sans", sans-serif;
            font-size: 13px;
            font-weight: 600;
            color: #292521;
            text-align: left;
            cursor: pointer;
            outline: none;
            transition: .2s ease;
            display: flex;
            align-items: center;
            position: relative;
        }

        .sd-toggle:hover {
            border-color: #d98a91;
        }

        .sd-toggle:focus,
        .sd-wrap.open .sd-toggle {
            border-color: #b51f2c;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(181, 31, 44, .08);
        }

        .sd-toggle>i.lead {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0a79c;
            font-size: 15px;
            pointer-events: none;
        }

        .sd-toggle>i.caret {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0a79c;
            font-size: 12px;
            pointer-events: none;
            transition: transform .2s;
        }

        .sd-wrap.open .sd-toggle>i.caret {
            transform: translateY(-50%) rotate(180deg);
        }

        .sd-toggle .placeholder {
            color: #b8afa3;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sd-toggle.has-value {
            color: #292521;
            font-weight: 700;
        }

        .sd-menu {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 14px;
            box-shadow: 0 20px 40px rgba(48, 41, 35, .14);
            z-index: 50;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-6px);
            transition: .18s ease;
            overflow: hidden;
        }

        .sd-wrap.open .sd-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .sd-search {
            position: relative;
            padding: 10px;
            border-bottom: 1px solid #f0ebe4;
            background: #fdfaf4;
        }

        .sd-search input {
            width: 100%;
            height: 38px;
            border: 1.5px solid #ece5da;
            background: #fff;
            border-radius: 9px;
            padding: 0 12px 0 34px;
            font-family: "DM Sans", sans-serif;
            font-size: 12.5px;
            color: #292521;
            outline: none;
            transition: .15s ease;
        }

        .sd-search input:focus {
            border-color: #d98a91;
            background: #fff;
        }

        .sd-search>i {
            position: absolute;
            left: 22px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0a79c;
            font-size: 13px;
            pointer-events: none;
        }

        .sd-list {
            max-height: 260px;
            overflow-y: auto;
            padding: 6px;
        }

        .sd-list::-webkit-scrollbar {
            width: 6px;
        }

        .sd-list::-webkit-scrollbar-thumb {
            background: #e0d8cd;
            border-radius: 4px;
        }

        .sd-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 9px;
            font-size: 12.5px;
            color: #4e4841;
            cursor: pointer;
            transition: .12s ease;
        }

        .sd-option:hover {
            background: #fbe8e9;
            color: #b51f2c;
        }

        .sd-option.selected {
            background: #b51f2c;
            color: #fff;
        }

        .sd-option.selected i {
            color: #fff;
        }

        .sd-option.selected .meta {
            color: rgba(255, 255, 255, .75);
        }

        .sd-option>i {
            color: #b0a79c;
            font-size: 14px;
            flex-shrink: 0;
        }

        .sd-option .name {
            flex: 1;
            min-width: 0;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sd-option .meta {
            font-size: 10.5px;
            color: #948c82;
            font-weight: 600;
            white-space: nowrap;
        }

        .sd-empty {
            padding: 18px;
            text-align: center;
            font-size: 12px;
            color: #948c82;
        }

        /* =====================================================
           APARTMENT MULTI-SELECT
           ===================================================== */
        .as-apt-box {
            background: #fff;
            border: 1.5px solid #eee7dc;
            border-radius: 16px;
            padding: 16px 18px 18px;
            margin-bottom: 22px;
        }

        .as-apt-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .as-apt-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .as-apt-title h3 {
            font-family: "Playfair Display", serif;
            font-size: 15px;
            font-weight: 700;
            color: #302923;
            margin: 0;
        }

        .as-apt-title .req {
            color: #b51f2c;
            font-size: 13px;
        }

        .as-apt-actions {
            display: flex;
            gap: 8px;
        }

        .as-mini-btn {
            height: 30px;
            padding: 0 12px;
            border-radius: 8px;
            border: 1.5px solid #ece5da;
            background: #fff;
            color: #6f675f;
            font-family: "DM Sans", sans-serif;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: .15s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .as-mini-btn:hover {
            background: #fbe8e9;
            border-color: #d98a91;
            color: #b51f2c;
        }

        .as-apt-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 340px;
            overflow-y: auto;
            padding-right: 4px;
            background: #fdfaf4;
            border: 1.5px solid #ece5da;
            border-radius: 12px;
            padding: 10px;
        }

        .as-apt-list::-webkit-scrollbar {
            width: 6px;
        }

        .as-apt-list::-webkit-scrollbar-thumb {
            background: #e0d8cd;
            border-radius: 4px;
        }

        .as-apt-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            border: 1.5px solid #ece5da;
            border-radius: 11px;
            background: #fff;
            cursor: pointer;
            transition: .15s ease;
            user-select: none;
        }

        .as-apt-item:hover {
            border-color: #d98a91;
            background: #fff5f5;
        }

        .as-apt-item.is-checked {
            border-color: #b51f2c;
            background: #fff5f5;
        }

        .as-apt-check {
            width: 20px;
            height: 20px;
            border-radius: 6px;
            border: 2px solid #d5cbbd;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            position: relative;
            transition: .15s ease;
        }

        .as-apt-item.is-checked .as-apt-check {
            background: #b51f2c;
            border-color: #b51f2c;
        }

        .as-apt-check::after {
            content: "";
            width: 5px;
            height: 9px;
            border: solid #fff;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg) translate(-1px, -1px);
            opacity: 0;
            transition: .15s ease;
        }

        .as-apt-item.is-checked .as-apt-check::after {
            opacity: 1;
        }

        .as-apt-info {
            flex: 1;
            min-width: 0;
        }

        .as-apt-name {
            font-size: 12.5px;
            font-weight: 700;
            color: #302923;
            margin: 0;
            line-height: 1.3;
        }

        .as-apt-meta {
            font-size: 10.5px;
            color: #948c82;
            margin: 3px 0 0;
            font-weight: 700;
            letter-spacing: .3px;
        }

        .as-apt-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #fdf1e2;
            color: #a35a0e;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .04em;
            white-space: nowrap;
        }

        .as-apt-count-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 999px;
            background: #fdf1e2;
            color: #a35a0e;
            font-size: 11px;
            font-weight: 800;
        }

        /* =====================================================
           PREVIEW
           ===================================================== */
        .as-preview-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .as-preview-title {
            font-family: "Playfair Display", serif;
            font-size: 18px;
            font-weight: 700;
            color: #302923;
            margin: 0;
        }

        .as-preview-sub {
            font-size: 12px;
            color: #817a71;
            margin: 4px 0 0;
        }

        .as-badge {
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

        .as-table-wrap {
            background: #fff;
            border: 1.5px solid #eee7dc;
            border-radius: 16px;
            overflow: hidden;
        }

        .as-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        .as-table thead th {
            text-align: left;
            padding: 14px 16px;
            background: #fdfaf4;
            color: #8a7f73;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            border-bottom: 1px solid #f0ebe4;
            white-space: nowrap;
        }

        .as-table tbody td {
            padding: 14px 16px;
            font-size: 12.5px;
            color: #4e4841;
            border-bottom: 1px solid #f4efe8;
            vertical-align: middle;
        }

        .as-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .as-table tbody tr:hover {
            background: #fffcf5;
        }

        .as-code {
            font-weight: 800;
            color: #b51f2c;
            font-size: 12.5px;
            display: block;
        }

        .as-time {
            font-size: 10.5px;
            color: #948c82;
            margin-top: 2px;
        }

        .as-name {
            font-weight: 700;
            color: #302923;
            display: block;
        }

        .as-mobile {
            font-size: 11px;
            color: #948c82;
            margin-top: 2px;
        }

        .as-boy {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 800;
        }

        .as-boy.has {
            background: #e8f6ea;
            color: #1b5e20;
        }

        .as-boy.none {
            background: #fbeaea;
            color: #b51f2c;
        }

        .as-amount {
            font-family: "Playfair Display", serif;
            font-weight: 700;
            color: #b51f2c;
            font-size: 13.5px;
        }

        .as-empty {
            padding: 60px 20px;
            text-align: center;
            color: #948c82;
            background: #fff;
            border: 1.5px dashed #ece5da;
            border-radius: 16px;
        }

        .as-empty svg {
            width: 48px;
            height: 48px;
            color: #ece5da;
            margin-bottom: 10px;
        }

        .as-empty h3 {
            font-family: "Playfair Display", serif;
            font-size: 17px;
            color: #6f675f;
            margin: 0 0 6px;
            font-weight: 700;
        }

        .as-empty p {
            margin: 0;
            font-size: 12.5px;
        }

        /* =====================================================
           ACTIONS
           ===================================================== */
        .as-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding-top: 16px;
            border-top: 1.5px dashed #f0ebe4;
        }

        .as-btn {
            height: 46px;
            padding: 0 22px;
            border-radius: 12px;
            border: none;
            font-family: "DM Sans", sans-serif;
            font-size: 12.5px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: .2s ease;
        }

        .as-btn-primary {
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
            box-shadow: 0 10px 24px rgba(181, 31, 44, .22);
        }

        .as-btn-primary:hover:not(:disabled) {
            transform: translateY(-1px);
        }

        .as-btn-primary:disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        .as-btn-ghost {
            background: #fff;
            border: 1.5px solid #e4ddd3;
            color: #6f675f;
        }

        .as-btn-ghost:hover {
            background: #faf7f0;
            color: #302923;
        }

        .as-btn svg {
            width: 15px;
            height: 15px;
        }

        .btn-spinner {
            width: 14px;
            height: 14px;
            border: 2px solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: asSpin .7s linear infinite;
            display: inline-block;
        }

        @keyframes asSpin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Toast */
        .as-toast {
            position: fixed;
            top: 84px;
            left: 50%;
            transform: translateX(-50%) translateY(-10px);
            padding: 10px 18px;
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 700;
            color: #fff;
            background: #1f7a3d;
            box-shadow: 0 10px 26px rgba(0, 0, 0, .18);
            z-index: 99999;
            opacity: 0;
            pointer-events: none;
            transition: opacity .25s ease, transform .25s ease;
            white-space: nowrap;
        }

        .as-toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        .as-toast.error {
            background: #b51f2c;
        }

        @media (max-width: 992px) {
            .as-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .as-page {
                padding: 20px 15px 40px;
            }

            .as-card {
                position: static;
            }

            .as-actions {
                flex-direction: column-reverse;
            }

            .as-btn {
                width: 100%;
            }

            .as-table-wrap {
                overflow-x: auto;
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


        <div class="as-page">

            <div class="as-header">
                <h1>Reassign Orders — Boy to Boy</h1>
                <p>Pick a menu and the current delivery boy, select apartments, then move his orders to another boy.</p>
            </div>


            <!-- =========================================================
                 STICKY FORM CARD
                 ========================================================= -->
            <div class="as-card">

                <div class="as-row">

                    <!-- ============== MENU — SEARCHABLE ============== -->
                    <div class="as-field">
                        <label>Menu <span class="req">*</span></label>

                        <div class="sd-wrap" id="menuDdWrap">
                            <button type="button" class="sd-toggle" id="menuDdToggle">
                                <i class="bi bi-list-ul lead"></i>
                                <span class="placeholder" id="menuDdLabel">— Select menu —</span>
                                <i class="bi bi-chevron-down caret"></i>
                            </button>

                            <div class="sd-menu">
                                <div class="sd-search">
                                    <i class="bi bi-search"></i>
                                    <input type="text" id="menuDdSearch" placeholder="Search menu..." autocomplete="off">
                                </div>
                                <div class="sd-list" id="menuDdList"></div>
                            </div>
                        </div>
                        <input type="hidden" id="asMenu" value="">
                    </div>


                    <!-- ============== FROM BOY — SEARCHABLE ============== -->
                    <div class="as-field">
                        <label>From Delivery Boy <span class="req">*</span></label>

                        <div class="sd-wrap" id="fromBoyDdWrap">
                            <button type="button" class="sd-toggle" id="fromBoyDdToggle">
                                <i class="bi bi-person-dash lead"></i>
                                <span class="placeholder" id="fromBoyDdLabel">— Select current boy —</span>
                                <i class="bi bi-chevron-down caret"></i>
                            </button>

                            <div class="sd-menu">
                                <div class="sd-search">
                                    <i class="bi bi-search"></i>
                                    <input type="text" id="fromBoyDdSearch" placeholder="Search current boy..." autocomplete="off">
                                </div>
                                <div class="sd-list" id="fromBoyDdList"></div>
                            </div>
                        </div>
                        <input type="hidden" id="asFromBoy" value="">
                    </div>


                    <!-- ============== TO BOY — SEARCHABLE ============== -->
                    <div class="as-field">
                        <label>Assign To <span class="req">*</span></label>

                        <div class="sd-wrap" id="boyDdWrap">
                            <button type="button" class="sd-toggle" id="boyDdToggle">
                                <i class="bi bi-person-badge lead"></i>
                                <span class="placeholder" id="boyDdLabel">— Select new boy —</span>
                                <i class="bi bi-chevron-down caret"></i>
                            </button>

                            <div class="sd-menu">
                                <div class="sd-search">
                                    <i class="bi bi-search"></i>
                                    <input type="text" id="boyDdSearch" placeholder="Search delivery boy..." autocomplete="off">
                                </div>
                                <div class="sd-list" id="boyDdList"></div>
                            </div>
                        </div>
                        <input type="hidden" id="asBoy" value="">
                    </div>

                </div>


                <!-- Actions row -->
                <div class="as-actions">
                    <button type="button" class="as-btn as-btn-ghost" id="asReset">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 12a9 9 0 1 0 3-6.7" />
                            <path d="M3 3v6h6" />
                        </svg>
                        Reset
                    </button>
                    <button type="button" class="as-btn as-btn-primary" id="asAssign" disabled>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6L9 17l-5-5" />
                        </svg>
                        <span id="asAssignText">Assign Orders</span>
                    </button>
                </div>

            </div>


            <!-- Apartment multi-select -->
            <div class="as-apt-box">

                <div class="as-apt-head">
                    <div class="as-apt-title">
                        <h3>Apartments <span class="req">*</span></h3>
                        <span class="as-apt-count-pill" id="asAptCount">0 selected</span>
                    </div>

                    <div class="as-apt-actions">
                        <button type="button" class="as-mini-btn" id="asSelectAll">
                            <i class="bi bi-check2-square"></i> Select All
                        </button>
                        <button type="button" class="as-mini-btn" id="asClearAll">
                            <i class="bi bi-x-square"></i> Clear
                        </button>
                    </div>
                </div>

                <div class="as-apt-list" id="asAptList">
                    <div class="as-empty" style="padding:30px 20px;border:0;background:transparent;">
                        <h3 style="font-size:14px;">Select a menu and from-boy first</h3>
                        <p style="font-size:11.5px;">Apartments with this boy's pending orders will appear here.</p>
                    </div>
                </div>

            </div>


            <!-- Preview -->
            <div class="as-preview-head" id="asPreviewHead" style="display:none;">
                <div>
                    <h2 class="as-preview-title">Matching Orders</h2>
                    <p class="as-preview-sub" id="asPreviewSub">—</p>
                </div>
                <span class="as-badge" id="asCountBadge">0 orders</span>
            </div>

            <div id="asPreviewWrap">
                <div class="as-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <h3>Pick a menu, a from-boy and apartments</h3>
                    <p>This boy's pending orders will show up here for review before moving them.</p>
                </div>
            </div>

        </div>

    </main>


    <div class="as-toast" id="asToast"></div>


    <!-- GLOBALS -->
    <script>
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
        window.MENUS = <?= $menusJson ?>;
        window.DELIVERY_BOYS = <?= $boysJson ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL ?>js/main.js"></script>
    <script src="<?= ADMIN_URL ?>js/urgency-assign-orders.js"></script>


</body>

</html>