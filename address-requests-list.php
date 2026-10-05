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
$siteName = $settings['username'] ?? 'Mrs Mill@';
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

    <style>
        .ar-page {
            padding: 30px 32px 60px;
        }

        .ar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .ar-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 5px;
        }

        .ar-title p {
            margin: 0;
            color: #817a71;
            font-size: 13px;
        }

        .ar-refresh-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1.5px solid #eee7dc;
            background: #fff;
            color: #6f675f;
            padding: 11px 16px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: .2s ease;
        }

        .ar-refresh-btn:hover {
            background: #faf7f0;
            color: #302923;
        }

        .ar-refresh-btn i {
            font-size: 14px;
        }

        /* =====================================================
           CARD
           ===================================================== */
        .ar-card {
            background: #fff;
            border: 1px solid #eee7dc;
            border-radius: 20px;
            padding: 22px;
        }

        .ar-list-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .ar-list-title h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #302923;
        }

        .ar-list-title span {
            display: block;
            margin-top: 4px;
            font-size: 10px;
            color: #817a71;
        }

        .ar-search {
            position: relative;
            width: 260px;
        }

        .ar-search i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa198;
            font-size: 13px;
            pointer-events: none;
        }

        .ar-search input {
            width: 100%;
            height: 40px;
            border: 1px solid #eee7dc;
            border-radius: 10px;
            padding: 0 12px 0 36px;
            outline: none;
            font-family: "DM Sans", sans-serif;
            font-size: 11px;
            background: #fffdf9;
            transition: .15s ease;
        }

        .ar-search input:focus {
            border-color: #d98a91;
            background: #fff;
        }

        /* =====================================================
           TABLE — no inner scroll on desktop
           ===================================================== */
        .ar-table-wrap {
            /* No max-height, no inner scrollbar */
            overflow: visible;
            border-radius: 12px;
        }

        .ar-table {
            width: 100%;
            border-collapse: collapse;
            /* Fluid width — fits card */
            table-layout: auto;
        }

        .ar-table th {
            background: #faf7f0;
            color: #938a80;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .7px;
            padding: 11px 10px;
            border-bottom: 1px solid #eee7dc;
            text-align: left;
            white-space: nowrap;
        }

        .ar-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #f2ede5;
            font-size: 12px;
            color: #4c4640;
            vertical-align: middle;
        }

        .ar-table tbody tr:hover {
            background: #fffdf9;
        }

        .ar-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .ar-customer {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .ar-avatar {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .ar-cust-name {
            font-weight: 700;
            color: #302923;
            font-size: 12px;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 160px;
        }

        .ar-cust-mobile {
            font-size: 10.5px;
            color: #948c82;
            margin-top: 2px;
            white-space: nowrap;
        }

        .ar-apartment {
            font-weight: 700;
            color: #302923;
            font-size: 12px;
            display: block;
        }

        .ar-division {
            font-size: 10.5px;
            color: #948c82;
            margin-top: 2px;
        }

        .ar-date {
            font-size: 11px;
            color: #6f675f;
            font-weight: 600;
            white-space: nowrap;
        }

        .ar-date small {
            display: block;
            font-size: 10px;
            color: #948c82;
            font-weight: 500;
            margin-top: 2px;
        }

        /* =====================================================
           STATUS DROPDOWN
           ===================================================== */
        .ar-status {
            position: relative;
            display: inline-block;
            min-width: 130px;
        }

        .ar-status-btn {
            width: 100%;
            height: 34px;
            border-radius: 8px;
            border: 1.5px solid transparent;
            padding: 0 30px 0 12px;
            font-family: "DM Sans", sans-serif;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            text-align: left;
            transition: .15s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            outline: none;
            position: relative;
        }

        .ar-status-btn i.caret {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 10px;
            pointer-events: none;
        }

        .ar-status-btn:hover {
            opacity: .9;
        }

        .ar-status-btn[data-status="1"] {
            background: #e8f1e8;
            color: #52745b;
        }

        .ar-status-btn[data-status="0"] {
            background: #f4ecec;
            color: #8b5a5a;
        }

        .ar-status-btn[data-status="2"] {
            background: #eef4fd;
            color: #1d5cb8;
        }

        .ar-status-menu {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            min-width: 100%;
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 10px;
            box-shadow: 0 14px 30px rgba(48, 41, 35, .12);
            padding: 4px;
            z-index: 100;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-4px);
            transition: .15s ease;
        }

        .ar-status.open .ar-status-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .ar-status-opt {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 7px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: .12s ease;
            white-space: nowrap;
        }

        .ar-status-opt:hover {
            background: #faf7f0;
        }

        .ar-status-opt .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .ar-status-opt[data-status="1"] .dot { background: #52745b; }
        .ar-status-opt[data-status="0"] .dot { background: #8b5a5a; }
        .ar-status-opt[data-status="2"] .dot { background: #1d5cb8; }

        .ar-status-opt.active {
            background: #fbe8e9;
        }

        /* =====================================================
           CALL BUTTON
           ===================================================== */
        .ar-call {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            height: 34px;
            padding: 0 14px;
            border-radius: 8px;
            border: 1.5px solid #eee7dc;
            background: #fff;
            color: #6f675f;
            font-family: "DM Sans", sans-serif;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            transition: .15s ease;
            white-space: nowrap;
        }

        .ar-call:hover {
            background: #e8f1e8;
            border-color: #a7c8a9;
            color: #1b5e20;
        }

        .ar-call i {
            font-size: 12px;
        }

        /* =====================================================
           EMPTY STATE
           ===================================================== */
        .ar-empty {
            text-align: center;
            padding: 50px 20px;
            color: #948c82;
        }

        .ar-empty i {
            font-size: 42px;
            color: #d5cbbd;
            display: block;
            margin-bottom: 12px;
        }

        .ar-empty h3 {
            font-family: "Playfair Display", serif;
            font-size: 17px;
            color: #6f675f;
            margin: 0 0 6px;
            font-weight: 700;
        }

        .ar-empty p {
            margin: 0;
            font-size: 12px;
        }

        /* =====================================================
           PAGINATION
           ===================================================== */
        .ar-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid #f2ede5;
            flex-wrap: wrap;
        }

        .ar-pag-left {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .ar-pag-info {
            font-size: 11px;
            color: #817a71;
        }

        .ar-pag-info strong {
            color: #302923;
            font-weight: 700;
        }

        .ar-perpage {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            color: #817a71;
        }

        .ar-perpage select {
            height: 32px;
            border: 1px solid #eee7dc;
            border-radius: 8px;
            background: #fffdf9;
            padding: 0 26px 0 10px;
            font-family: inherit;
            font-size: 11px;
            font-weight: 700;
            color: #4c4640;
            cursor: pointer;
            outline: none;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 16 16'><path fill='%23817a71' d='M8 11L3 6h10z'/></svg>");
            background-repeat: no-repeat;
            background-position: right 9px center;
            transition: .2s ease;
        }

        .ar-perpage select:focus {
            border-color: #d98a91;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .ar-pag-controls {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
        }

        .ar-pag-controls button {
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
            transition: .2s ease;
            padding: 0 8px;
            font-family: inherit;
        }

        .ar-pag-controls button:hover:not(:disabled):not(.active) {
            background: #faf7f0;
            border-color: #e4ddd3;
            color: #302923;
        }

        .ar-pag-controls button.active {
            background: #b51f2c;
            border-color: #b51f2c;
            color: #fff;
            box-shadow: 0 4px 12px rgba(181, 31, 44, .22);
            cursor: default;
        }

        .ar-pag-controls button:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .ar-pag-controls .ellipsis {
            min-width: 26px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #b5aca2;
            font-size: 12px;
            font-weight: 700;
            user-select: none;
        }

        /* =====================================================
           TOAST
           ===================================================== */
        .ar-toast-wrap {
            position: fixed;
            top: 22px;
            right: 22px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 10000;
            pointer-events: none;
        }

        .ar-toast {
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
            box-shadow: 0 14px 34px rgba(0, 0, 0, .14);
            transform: translateX(120%);
            opacity: 0;
            transition: transform .3s cubic-bezier(.2, .9, .3, 1.2), opacity .3s ease;
            pointer-events: auto;
        }

        .ar-toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .ar-toast-icon {
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

        .ar-toast.success .ar-toast-icon { background: #4caf50; }
        .ar-toast.error   .ar-toast-icon { background: #c62828; }
        .ar-toast.info    .ar-toast-icon { background: #3b82f6; }

        .ar-toast-body {
            flex: 1;
            padding-top: 3px;
            line-height: 1.5;
        }

        .ar-toast-close {
            background: transparent;
            border: 0;
            color: #b5aca2;
            cursor: pointer;
            font-size: 14px;
            padding: 0 2px;
            line-height: 1;
        }

        /* =====================================================
           MOBILE — allow horizontal scroll only when needed
           ===================================================== */
        @media (max-width: 768px) {
            .ar-page { padding: 20px 15px 40px; }

            .ar-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .ar-refresh-btn { width: 100%; justify-content: center; }

            .ar-card { padding: 17px; border-radius: 17px; }

            .ar-list-head {
                flex-direction: column;
                align-items: stretch;
            }

            .ar-search { width: 100%; }

            /* Only on mobile: allow horizontal scroll if table too wide */
            .ar-table-wrap {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .ar-table {
                min-width: 760px;
            }

            .ar-pagination {
                flex-direction: column;
                align-items: stretch;
            }

            .ar-pag-left { justify-content: center; }
            .ar-pag-info { text-align: center; }
            .ar-pag-controls { justify-content: center; }
        }

        @media (max-width: 480px) {
            .ar-toast-wrap {
                top: 14px;
                right: 14px;
                left: 14px;
            }

            .ar-toast {
                min-width: 0;
                width: 100%;
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
                    <div class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)) ?></div>
                    <div>
                        <div class="admin-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'User') ?></div>
                        <div class="admin-role"><?= $isAdmin ? 'Admin' : 'Staff' ?></div>
                    </div>
                </div>
            </div>
        </header>


        <div class="ar-page">

            <div class="ar-header">
                <div class="ar-title">
                    <h1>Address Requests</h1>
                    <p>Customers requesting to be added to an apartment or division.</p>
                </div>

                <button type="button" class="ar-refresh-btn" id="arRefresh">
                    <i class="bi bi-arrow-clockwise"></i>
                    Refresh
                </button>
            </div>


            <div class="ar-card">

                <div class="ar-list-head">
                    <div class="ar-list-title">
                        <h3>All Requests</h3>
                        <span>Loaded from the database</span>
                    </div>

                    <div class="ar-search">
                        <i class="bi bi-search"></i>
                        <input type="text" id="arSearch" placeholder="Search name, mobile, apartment...">
                    </div>
                </div>

                <div class="ar-table-wrap">
                    <table class="ar-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Requested Apartment</th>
                                <th>Division</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="arTbody">
                            <tr>
                                <td colspan="6" style="text-align:center;padding:40px;color:#948c82;">
                                    <span style="display:inline-block;width:22px;height:22px;border:3px solid #eee7dc;border-top-color:#b51f2c;border-radius:50%;animation:arSpin .7s linear infinite;"></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>


                <!-- PAGINATION -->
                <div class="ar-pagination" id="arPagination" style="display:none;">

                    <div class="ar-pag-left">

                        <div class="ar-pag-info" id="arPagInfo">
                            Showing <strong>0</strong>–<strong>0</strong> of <strong>0</strong>
                        </div>

                        <label class="ar-perpage">
                            Show
                            <select id="arPerPage">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            entries
                        </label>

                    </div>

                    <div class="ar-pag-controls" id="arPagControls"></div>

                </div>

            </div>

        </div>

    </main>


    <div class="ar-toast-wrap" id="arToastWrap"></div>


    <script>
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
        window.IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL ?>js/main.js"></script>
    <script src="<?= ADMIN_URL ?>js/address-requests.js"></script>

</body>

</html>