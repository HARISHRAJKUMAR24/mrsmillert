<?php
require_once './config/config.php';
require_once './config/function.php';

/* ---------------- AUTH CHECK ---------------- */
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    header('Location: login.php');
    exit;
}

$settings = getSettings($pdo);
$isAdmin = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin');
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

    <style>
        .ao-page { padding: 30px 32px 40px; }

        .ao-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .ao-header h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 5px;
        }
        .ao-header p { margin: 0; color: #817a71; font-size: 13px; }

        .ao-filter-card {
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 20px;
            padding: 22px;
            margin-bottom: 22px;
        }

        .ao-filter-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: "Playfair Display", serif;
            font-size: 16px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 16px;
        }
        .ao-filter-title i {
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

        .ao-filter-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            align-items: end;
        }
        @media (max-width: 900px) { .ao-filter-row { grid-template-columns: 1fr; } }

        .ao-field { position: relative; }
        .ao-field label {
            display: block;
            font-size: 11px;
            font-weight: 800;
            color: #4e4841;
            margin-bottom: 7px;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .ao-field label .req { color: #b51f2c; }

        /* Searchable dropdown */
        .sd-wrap { position: relative; }
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
        .sd-toggle:hover { border-color: #d98a91; }
        .sd-wrap.open .sd-toggle {
            border-color: #b51f2c;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(181,31,44,.08);
        }
        .sd-toggle > i.lead {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0a79c;
            font-size: 15px;
            pointer-events: none;
        }
        .sd-toggle > i.caret {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0a79c;
            font-size: 12px;
            pointer-events: none;
            transition: transform .2s;
        }
        .sd-wrap.open .sd-toggle > i.caret { transform: translateY(-50%) rotate(180deg); }
        .sd-toggle .placeholder { color: #b8afa3; font-weight: 500; }
        .sd-toggle.has-value { color: #292521; font-weight: 700; }
        .sd-toggle:disabled {
            opacity: .55;
            cursor: not-allowed;
            background: #f7f2ec;
        }

        .sd-menu {
            position: absolute;
            top: calc(100% + 6px);
            left: 0; right: 0;
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 14px;
            box-shadow: 0 20px 40px rgba(48,41,35,.14);
            z-index: 30;
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
        }
        .sd-search input:focus { border-color: #d98a91; background: #fff; }
        .sd-search > i {
            position: absolute;
            left: 22px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0a79c;
            font-size: 13px;
            pointer-events: none;
        }

        .sd-list {
            max-height: 280px;
            overflow-y: auto;
            padding: 6px;
        }
        .sd-list::-webkit-scrollbar { width: 6px; }
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
        .sd-option:hover { background: #fbe8e9; color: #b51f2c; }
        .sd-option.selected { background: #b51f2c; color: #fff; }
        .sd-option.selected i { color: #fff; }
        .sd-option.selected .meta { color: rgba(255,255,255,.75); }
        .sd-option > i { color: #b0a79c; font-size: 14px; flex-shrink: 0; }
        .sd-option .name {
            flex: 1; min-width: 0; font-weight: 700;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .sd-option .meta {
            font-size: 10.5px; color: #948c82;
            font-weight: 600; white-space: nowrap;
        }
        .sd-empty {
            padding: 18px; text-align: center;
            font-size: 12px; color: #948c82;
        }

        /* Date filter */
        .ao-date-filter {
            position: relative;
            display: inline-flex;
            align-items: center;
            width: 100%;
            height: 46px;
            background: #fffdf9;
            border: 1.5px solid #ece5da;
            border-radius: 12px;
            padding: 0 40px 0 42px;
            transition: .2s ease;
        }
        .ao-date-filter:focus-within {
            border-color: #b51f2c;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(181,31,44,.08);
        }
        .ao-date-filter > i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0a79c;
            font-size: 15px;
            pointer-events: none;
        }
        .ao-date-filter select {
            appearance: none;
            -webkit-appearance: none;
            border: none;
            background: transparent;
            font-family: "DM Sans", sans-serif;
            font-size: 13px;
            font-weight: 600;
            color: #292521;
            outline: none;
            cursor: pointer;
            width: 100%;
            height: 100%;
            padding-right: 18px;
        }
        .ao-date-filter::after {
            content: "";
            position: absolute;
            right: 14px;
            top: 50%;
            width: 8px;
            height: 8px;
            border-right: 2px solid #b0a79c;
            border-bottom: 2px solid #b0a79c;
            transform: translateY(-70%) rotate(45deg);
            pointer-events: none;
        }

        .ao-date-custom {
            display: flex;
            align-items: center;
            gap: 8px;
            height: 46px;
            background: #fffdf9;
            border: 1.5px solid #ece5da;
            border-radius: 12px;
            padding: 0 14px;
            font-size: 12px;
            color: #817a71;
            font-weight: 600;
        }
        .ao-date-custom input[type="date"] {
            border: none;
            background: transparent;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            color: #302923;
            outline: none;
            cursor: pointer;
            font-weight: 700;
        }

        /* KPIs */
        .ao-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }
        .ao-kpi {
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 16px;
            padding: 16px 18px;
        }
        .ao-kpi-label {
            font-size: 10px;
            font-weight: 800;
            color: #948c82;
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 6px;
        }
        .ao-kpi-value {
            font-family: "Playfair Display", serif;
            font-size: 24px;
            font-weight: 700;
            color: #302923;
            line-height: 1.1;
        }
        .ao-kpi-value.money { color: #b51f2c; }

        /* Results card */
        .ao-card {
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 20px;
            padding: 22px;
            margin-bottom: 22px;
        }

        .ao-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        .ao-card-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: "Playfair Display", serif;
            font-size: 16px;
            font-weight: 700;
            color: #302923;
            margin: 0;
        }
        .ao-card-title i {
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
        .ao-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 999px;
            background: #fdf1e2;
            color: #a35a0e;
            font-size: 11px;
            font-weight: 800;
        }

        /* =====================================================
           PRODUCT LIST — TABLE STYLE
           ===================================================== */
        .ao-prod-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
        }
        .ao-prod-table thead th {
            text-align: left;
            padding: 12px 14px;
            background: #fdfaf4;
            color: #8a7f73;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.1px;
            text-transform: uppercase;
            border-bottom: 1px solid #f0ebe4;
            white-space: nowrap;
        }
        .ao-prod-table tbody td {
            padding: 14px;
            font-size: 12.5px;
            color: #4e4841;
            vertical-align: middle;
            border-bottom: 1px solid #f4efe8;
        }
        .ao-prod-table tbody tr:last-child td { border-bottom: 0; }
        .ao-prod-table tbody tr:hover { background: #fffcf5; }

        .ao-prod-row-thumb {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            background: #f7efe3;
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #b0a79c;
            border: 1px solid #f0ebe4;
        }
        .ao-prod-row-thumb img { width: 100%; height: 100%; object-fit: cover; }

        .ao-prod-cell {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }
        .ao-prod-cell-info { min-width: 0; }
        .ao-prod-cell-name {
            font-size: 13px;
            font-weight: 800;
            color: #302923;
            margin: 0 0 3px;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .ao-prod-cell-code {
            font-size: 10.5px;
            color: #948c82;
            font-weight: 700;
            letter-spacing: .3px;
        }

        .ao-var-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .ao-var-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 8px;
            background: #fdf1e2;
            color: #a35a0e;
            font-size: 10.5px;
            font-weight: 700;
            white-space: nowrap;
        }
        .ao-var-chip strong {
            background: #b8893c;
            color: #fff;
            padding: 1px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 800;
        }

        .ao-qty-big {
            font-family: "Playfair Display", serif;
            font-size: 22px;
            font-weight: 700;
            color: #b51f2c;
            line-height: 1;
            text-align: right;
        }
        .ao-qty-lbl {
            font-size: 9.5px;
            color: #948c82;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            text-align: right;
            margin-top: 3px;
        }

        /* Orders table */
        .ao-orders-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 22px 0 12px;
            padding-top: 22px;
            border-top: 1.5px dashed #f0ebe4;
            flex-wrap: wrap;
        }
        .ao-orders-head h4 {
            margin: 0;
            font-family: "Playfair Display", serif;
            font-size: 15px;
            font-weight: 700;
            color: #302923;
        }

        .ao-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 720px;
        }
        .ao-table thead th {
            text-align: left;
            padding: 12px 14px;
            background: #fdfaf4;
            color: #8a7f73;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.1px;
            text-transform: uppercase;
            border-bottom: 1px solid #f0ebe4;
            white-space: nowrap;
        }
        .ao-table tbody td {
            padding: 12px 14px;
            font-size: 12px;
            color: #4e4841;
            vertical-align: middle;
            border-bottom: 1px solid #f4efe8;
        }
        .ao-table tbody tr:last-child td { border-bottom: 0; }
        .ao-table tbody tr:hover { background: #fffcf5; }

        .ao-order-code { font-weight: 800; color: #b51f2c; font-size: 12.5px; }
        .ao-order-time { font-size: 10.5px; color: #948c82; margin-top: 2px; }
        .ao-order-name { font-weight: 700; color: #302923; font-size: 12px; }
        .ao-order-amt {
            font-family: "Playfair Display", serif;
            font-weight: 700;
            color: #b51f2c;
            font-size: 13.5px;
        }

        .ao-status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: .3px;
            text-transform: uppercase;
        }
        .ao-status-pending    { background: #fdf1e2; color: #a35a0e; }
        .ao-status-confirmed  { background: #e8f1e8; color: #2e7d32; }
        .ao-status-processing { background: #e5eefb; color: #1565c0; }
        .ao-status-delivered  { background: #e8f6ea; color: #1b5e20; }
        .ao-status-cancelled  { background: #fdeaea; color: #b51f2c; }

        .ao-pay-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 9.5px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .ao-pay-paid   { background: #e8f6ea; color: #1b5e20; }
        .ao-pay-unpaid { background: #fff4d6; color: #8a6a1e; }

        /* Empty state */
        .ao-empty {
            text-align: center;
            padding: 60px 20px;
            color: #948c82;
        }
        .ao-empty i {
            font-size: 50px;
            color: #ece5da;
            display: block;
            margin-bottom: 12px;
        }
        .ao-empty h3 {
            font-family: "Playfair Display", serif;
            font-size: 17px;
            color: #6f675f;
            margin: 0 0 6px;
            font-weight: 700;
        }
        .ao-empty p { margin: 0; font-size: 12.5px; }

        .ao-spinner {
            width: 22px;
            height: 22px;
            border: 3px solid #ece5da;
            border-top-color: #b51f2c;
            border-radius: 50%;
            animation: aoSpin .7s linear infinite;
            display: inline-block;
        }
        @keyframes aoSpin { to { transform: rotate(360deg); } }

        @media (max-width: 768px) {
            .ao-page { padding: 20px 15px 40px; }
            .ao-card { padding: 17px; border-radius: 17px; }
            .ao-filter-card { padding: 17px; border-radius: 17px; }
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


        <div class="ao-page">

            <div class="ao-header">
                <div>
                    <h1>Menu Apartment Orders</h1>
                    <p>Pick a menu and apartment to see what products were ordered.</p>
                </div>
            </div>


            <!-- Filter card -->
            <div class="ao-filter-card">

                <h2 class="ao-filter-title">
                    <i class="bi bi-funnel"></i>
                    Filter
                </h2>

                <div class="ao-filter-row">

                    <div class="ao-field">
                        <label>Menu <span class="req">*</span></label>

                        <div class="sd-wrap" id="aoMenuDdWrap">
                            <button type="button" class="sd-toggle" id="aoMenuDdToggle">
                                <i class="bi bi-list-ul lead"></i>
                                <span class="placeholder" id="aoMenuDdLabel">— Select menu —</span>
                                <i class="bi bi-chevron-down caret"></i>
                            </button>

                            <div class="sd-menu">
                                <div class="sd-search">
                                    <i class="bi bi-search"></i>
                                    <input type="text" id="aoMenuDdSearch"
                                           placeholder="Search menu..." autocomplete="off">
                                </div>
                                <div class="sd-list" id="aoMenuDdList"></div>
                            </div>
                        </div>

                        <input type="hidden" id="aoMenuCode">
                    </div>

                    <div class="ao-field">
                        <label>Apartment <span class="req">*</span></label>

                        <div class="sd-wrap" id="aoAptDdWrap">
                            <button type="button" class="sd-toggle" id="aoAptDdToggle" disabled>
                                <i class="bi bi-building lead"></i>
                                <span class="placeholder" id="aoAptDdLabel">— Select menu first —</span>
                                <i class="bi bi-chevron-down caret"></i>
                            </button>

                            <div class="sd-menu">
                                <div class="sd-search">
                                    <i class="bi bi-search"></i>
                                    <input type="text" id="aoAptDdSearch"
                                           placeholder="Search apartment..." autocomplete="off">
                                </div>
                                <div class="sd-list" id="aoAptDdList"></div>
                            </div>
                        </div>

                        <input type="hidden" id="aoApartmentId">
                        <input type="hidden" id="aoApartmentCode">
                    </div>

                    <div class="ao-field">
                        <label>Date</label>
                        <div class="ao-date-filter">
                            <i class="bi bi-calendar3"></i>
                            <select id="aoDateFilter">
                                <option value="all" selected>All Time</option>
                                <option value="today">Today</option>
                                <option value="yesterday">Yesterday</option>
                                <option value="week">Last 7 Days</option>
                                <option value="month">This Month</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                    </div>

                </div>

                <div class="ao-filter-row" id="aoCustomWrap" style="display:none;margin-top:14px;">
                    <div class="ao-field" style="grid-column: span 3;">
                        <label>Custom Date Range</label>
                        <div class="ao-date-custom" style="width:100%;">
                            <input type="date" id="aoDateFrom" style="flex:1;">
                            <span>to</span>
                            <input type="date" id="aoDateTo" style="flex:1;">
                        </div>
                    </div>
                </div>

            </div>


            <!-- KPIs -->
            <div class="ao-kpis" id="aoKpis" style="display:none;">
                <div class="ao-kpi">
                    <div class="ao-kpi-label">Total Orders</div>
                    <div class="ao-kpi-value" id="kpiOrders">0</div>
                </div>
                <div class="ao-kpi">
                    <div class="ao-kpi-label">Products</div>
                    <div class="ao-kpi-value" id="kpiProducts">0</div>
                </div>
                <div class="ao-kpi">
                    <div class="ao-kpi-label">Total Qty</div>
                    <div class="ao-kpi-value" id="kpiQty">0</div>
                </div>
                <div class="ao-kpi">
                    <div class="ao-kpi-label">Total Value</div>
                    <div class="ao-kpi-value money" id="kpiAmount">₹0</div>
                </div>
            </div>


            <!-- Results -->
            <div id="aoResultsWrap">
                <div class="ao-empty">
                    <i class="bi bi-funnel"></i>
                    <h3>Pick a menu and apartment</h3>
                    <p>Orders will appear here grouped by product.</p>
                </div>
            </div>

        </div>

    </main>


    <script>
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL ?>js/main.js"></script>
    <script src="<?= ADMIN_URL ?>js/apartment-orders.js"></script>

</body>

</html>