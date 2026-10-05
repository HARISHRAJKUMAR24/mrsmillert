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
        .cu-page { padding: 30px 32px 60px; }
        .cu-header { display: flex; align-items: center; justify-content: space-between; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; }
        .cu-title h1 { font-family: "Playfair Display", serif; font-size: 29px; font-weight: 700; color: #302923; margin: 0 0 5px; }
        .cu-title p { margin: 0; color: #817a71; font-size: 13px; }
        .cu-header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .cu-btn { display: inline-flex; align-items: center; gap: 8px; height: 44px; padding: 0 18px; border-radius: 12px; font-family: "DM Sans", sans-serif; font-size: 12px; font-weight: 800; cursor: pointer; text-decoration: none; border: none; transition: .2s ease; white-space: nowrap; }
        .cu-btn-primary { background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%); color: #fff; box-shadow: 0 8px 20px rgba(181, 31, 44, .18); }
        .cu-btn-primary:hover { color: #fff; transform: translateY(-1px); }
        .cu-btn-ghost { background: #fff; border: 1.5px solid #eee7dc; color: #6f675f; }
        .cu-btn-ghost:hover { background: #faf7f0; color: #302923; }
        .cu-btn i { font-size: 14px; }

        .cu-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 22px; }
        .cu-kpi { background: #fff; border: 1px solid #eee7dc; border-radius: 16px; padding: 16px 18px; }
        .cu-kpi-label { font-size: 10px; font-weight: 800; color: #948c82; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 6px; }
        .cu-kpi-value { font-family: "Playfair Display", serif; font-size: 24px; font-weight: 700; color: #302923; line-height: 1.1; }
        .cu-kpi-value.money { color: #b51f2c; }

        .cu-card { background: #fff; border: 1px solid #eee7dc; border-radius: 20px; padding: 22px; }
        .cu-list-head { display: flex; align-items: center; justify-content: space-between; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .cu-list-title h3 { margin: 0; font-size: 16px; font-weight: 700; color: #302923; }
        .cu-list-title span { display: block; margin-top: 4px; font-size: 10px; color: #817a71; }

        .cu-search { position: relative; width: 260px; }
        .cu-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #aaa198; font-size: 13px; pointer-events: none; }
        .cu-search input { width: 100%; height: 40px; border: 1px solid #eee7dc; border-radius: 10px; padding: 0 12px 0 36px; outline: none; font-family: "DM Sans", sans-serif; font-size: 11px; background: #fffdf9; transition: .15s ease; }
        .cu-search input:focus { border-color: #d98a91; background: #fff; }

        .cu-table-wrap { overflow: visible; }
        .cu-table { width: 100%; border-collapse: collapse; }
        .cu-table th { background: #faf7f0; color: #938a80; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px; padding: 12px 10px; border-bottom: 1px solid #eee7dc; text-align: left; white-space: nowrap; }
        .cu-table td { padding: 12px 10px; border-bottom: 1px solid #f2ede5; font-size: 12px; color: #4c4640; vertical-align: middle; }
        .cu-table tbody tr:hover { background: #fffdf9; }
        .cu-table tbody tr:last-child td { border-bottom: 0; }

        .cu-cust { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .cu-avatar { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; flex-shrink: 0; }
        .cu-cust-name { font-weight: 700; color: #302923; font-size: 12px; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px; }
        .cu-cust-mobile { font-size: 10.5px; color: #948c82; margin-top: 2px; }

        .cu-apt { font-weight: 700; color: #302923; font-size: 11.5px; display: block; }
        .cu-div { font-size: 10.5px; color: #948c82; margin-top: 2px; }

        .cu-wallet { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 800; white-space: nowrap; }
        .cu-wallet.pos { background: #e8f1e8; color: #52745b; }
        .cu-wallet.zero { background: #f4ecec; color: #8b5a5a; }

        .cu-status { display: inline-flex; align-items: center; gap: 5px; padding: 5px 9px; border-radius: 999px; font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; }
        .cu-status.active { background: #e8f1e8; color: #52745b; }
        .cu-status.inactive { background: #f4ecec; color: #8b5a5a; }

        .cu-actions { display: flex; gap: 5px; flex-wrap: wrap; }
        .cu-action { height: 32px; padding: 0 11px; border-radius: 8px; border: 1.5px solid #eee7dc; background: #fff; color: #6f675f; font-family: "DM Sans", sans-serif; font-size: 10.5px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: .15s ease; text-decoration: none; white-space: nowrap; }
        .cu-action:hover { background: #fbe8e9; border-color: #f1c8cc; color: #b51f2c; }
        .cu-action.money:hover { background: #e8f1e8; border-color: #a7c8a9; color: #1b5e20; }
        .cu-action.delete:hover { background: #fde6e6; border-color: #f5c0c0; color: #c62828; }
        .cu-action i { font-size: 11px; }

        .cu-empty { text-align: center; padding: 50px 20px; color: #948c82; }
        .cu-empty i { font-size: 42px; color: #d5cbbd; display: block; margin-bottom: 12px; }
        .cu-empty h3 { font-family: "Playfair Display", serif; font-size: 17px; color: #6f675f; margin: 0 0 6px; font-weight: 700; }
        .cu-empty p { margin: 0; font-size: 12px; }

        .cu-pagination { display: flex; align-items: center; justify-content: space-between; gap: 15px; margin-top: 18px; padding-top: 16px; border-top: 1px solid #f2ede5; flex-wrap: wrap; }
        .cu-pag-left { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .cu-pag-info { font-size: 11px; color: #817a71; }
        .cu-pag-info strong { color: #302923; font-weight: 700; }
        .cu-perpage { display: inline-flex; align-items: center; gap: 8px; font-size: 11px; color: #817a71; }
        .cu-perpage select { height: 32px; border: 1px solid #eee7dc; border-radius: 8px; background: #fffdf9; padding: 0 26px 0 10px; font-family: inherit; font-size: 11px; font-weight: 700; color: #4c4640; cursor: pointer; outline: none; appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 16 16'><path fill='%23817a71' d='M8 11L3 6h10z'/></svg>"); background-repeat: no-repeat; background-position: right 9px center; }
        .cu-pag-controls { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
        .cu-pag-controls button { min-width: 34px; height: 34px; border-radius: 9px; border: 1px solid #eee7dc; background: #fff; color: #6f675f; font-size: 11px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; padding: 0 8px; font-family: inherit; }
        .cu-pag-controls button:hover:not(:disabled):not(.active) { background: #faf7f0; border-color: #e4ddd3; color: #302923; }
        .cu-pag-controls button.active { background: #b51f2c; border-color: #b51f2c; color: #fff; cursor: default; }
        .cu-pag-controls button:disabled { opacity: .4; cursor: not-allowed; }
        .cu-pag-controls .ellipsis { min-width: 26px; height: 34px; display: inline-flex; align-items: center; justify-content: center; color: #b5aca2; font-size: 12px; font-weight: 700; }

        .cu-toast-wrap { position: fixed; top: 22px; right: 22px; display: flex; flex-direction: column; gap: 10px; z-index: 10000; pointer-events: none; }
        .cu-toast { min-width: 260px; max-width: 360px; background: #fff; border-radius: 12px; padding: 13px 15px; display: flex; align-items: flex-start; gap: 10px; font-size: 12px; color: #302923; border: 1px solid #eee7dc; box-shadow: 0 14px 34px rgba(0, 0, 0, .14); transform: translateX(120%); opacity: 0; transition: transform .3s cubic-bezier(.2, .9, .3, 1.2), opacity .3s ease; pointer-events: auto; }
        .cu-toast.show { transform: translateX(0); opacity: 1; }
        .cu-toast-icon { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0; color: #fff; }
        .cu-toast.success .cu-toast-icon { background: #4caf50; }
        .cu-toast.error .cu-toast-icon { background: #c62828; }
        .cu-toast-body { flex: 1; padding-top: 3px; line-height: 1.5; }

        @media (max-width: 768px) {
            .cu-page { padding: 20px 15px 40px; }
            .cu-header { flex-direction: column; align-items: flex-start; }
            .cu-header-actions { width: 100%; }
            .cu-btn { flex: 1; justify-content: center; }
            .cu-card { padding: 17px; border-radius: 17px; }
            .cu-list-head { flex-direction: column; align-items: stretch; }
            .cu-search { width: 100%; }
            .cu-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .cu-table { min-width: 900px; }
            .cu-pagination { flex-direction: column; align-items: stretch; }
            .cu-pag-left { justify-content: center; }
            .cu-pag-controls { justify-content: center; }
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

        <div class="cu-page">

            <div class="cu-header">
                <div class="cu-title">
                    <h1>Customers</h1>
                    <p>Manage customer accounts, wallet balance and transaction history.</p>
                </div>
                <div class="cu-header-actions">
                    <button type="button" class="cu-btn cu-btn-ghost" id="cuRefreshBtn">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <a href="add-customer.php" class="cu-btn cu-btn-primary">
                        <i class="bi bi-person-plus"></i> Add Customer
                    </a>
                </div>
            </div>

            <div class="cu-kpis">
                <div class="cu-kpi">
                    <div class="cu-kpi-label">Total Customers</div>
                    <div class="cu-kpi-value" id="kpiTotal">0</div>
                </div>
                <div class="cu-kpi">
                    <div class="cu-kpi-label">Active</div>
                    <div class="cu-kpi-value" id="kpiActive">0</div>
                </div>
                <div class="cu-kpi">
                    <div class="cu-kpi-label">Total Wallet Balance</div>
                    <div class="cu-kpi-value money" id="kpiWallet">₹0</div>
                </div>
                <div class="cu-kpi">
                    <div class="cu-kpi-label">With Wallet Balance</div>
                    <div class="cu-kpi-value" id="kpiWithWallet">0</div>
                </div>
            </div>

            <div class="cu-card">
                <div class="cu-list-head">
                    <div class="cu-list-title">
                        <h3>All Customers</h3>
                        <span>Loaded from the database</span>
                    </div>
                    <div class="cu-search">
                        <i class="bi bi-search"></i>
                        <input type="text" id="cuSearch" placeholder="Search name, mobile, apartment...">
                    </div>
                </div>

                <div class="cu-table-wrap">
                    <table class="cu-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Apartment</th>
                                <th>Division</th>
                                <th>Wallet</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="cuTbody">
                            <tr>
                                <td colspan="6" style="text-align:center;padding:40px;color:#948c82;">
                                    <span style="display:inline-block;width:22px;height:22px;border:3px solid #eee7dc;border-top-color:#b51f2c;border-radius:50%;animation:cuSpin .7s linear infinite;"></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="cu-pagination" id="cuPagination" style="display:none;">
                    <div class="cu-pag-left">
                        <div class="cu-pag-info" id="cuPagInfo">
                            Showing <strong>0</strong>–<strong>0</strong> of <strong>0</strong>
                        </div>
                        <label class="cu-perpage">
                            Show
                            <select id="cuPerPage">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            entries
                        </label>
                    </div>
                    <div class="cu-pag-controls" id="cuPagControls"></div>
                </div>
            </div>

        </div>
    </main>

    <div class="cu-toast-wrap" id="cuToastWrap"></div>

    <script>
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL ?>js/main.js"></script>
    <script src="<?= ADMIN_URL ?>js/customers.js"></script>
</body>
</html>