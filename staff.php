<?php
require_once './config/config.php';
require_once './config/function.php';

/* AUTH CHECK */
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    header('Location: login.php');
    exit;
}

/* ADMIN ONLY */
requireAdmin();

$settings = getSettings($pdo);
$siteName = $settings['username'] ?? 'Mrs Mill@';
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

    <style>
        .st-page { padding: 30px 32px 40px; }

        .st-header {
            display: flex; align-items: center; justify-content: space-between;
            gap: 20px; margin-bottom: 25px; flex-wrap: wrap;
        }

        .st-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px; font-weight: 700;
            color: #302923; margin: 0 0 5px;
        }

        .st-title p { margin: 0; color: #817a71; font-size: 13px; }

        .btn-add {
            display: inline-flex; align-items: center; gap: 8px;
            background: #b51f2c; color: #fff;
            border: none; border-radius: 10px;
            padding: 11px 18px;
            font-size: 12px; font-weight: 700;
            text-decoration: none;
            transition: .2s ease;
            box-shadow: 0 8px 20px rgba(181,31,44,.18);
        }

        .btn-add:hover { background: #8e1722; color: #fff; transform: translateY(-1px); }

        .st-card {
            background: #fff;
            border: 1px solid #eee7dc;
            border-radius: 20px;
            padding: 6px;
            overflow: hidden;
        }

        .st-table-wrap { overflow-x: auto; border-radius: 16px; }

        .st-table {
            width: 100%; border-collapse: collapse;
            min-width: 900px;
        }

        .st-table thead th {
            text-align: left; padding: 14px 16px;
            background: #fdfaf4; color: #8a7f73;
            font-size: 10px; font-weight: 800;
            letter-spacing: 1.2px; text-transform: uppercase;
            border-bottom: 1px solid #f0ebe4;
            white-space: nowrap;
        }

        .st-table tbody tr {
            border-bottom: 1px solid #f4efe8;
            transition: background .2s ease;
        }

        .st-table tbody tr:last-child { border-bottom: 0; }
        .st-table tbody tr:hover { background: #fffcf5; }

        .st-table tbody td {
            padding: 14px 16px;
            font-size: 12px; color: #4e4841;
            vertical-align: middle;
        }

        .staff-cell { display: flex; align-items: center; gap: 12px; }

        .staff-avatar {
            width: 42px; height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-family: "Playfair Display", serif;
            font-weight: 700; font-size: 17px;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(181,31,44,.2);
        }

        .staff-info { min-width: 0; }

        .staff-name {
            font-weight: 700; color: #302923;
            font-size: 12.5px;
            white-space: nowrap; overflow: hidden;
            text-overflow: ellipsis; max-width: 200px;
        }

        .staff-code {
            font-size: 10px; color: #948c82;
            font-weight: 600; margin-top: 2px;
            letter-spacing: .4px;
        }

        .mobile-cell { font-weight: 600; color: #4e4841; white-space: nowrap; }
        .mobile-cell i { color: #b51f2c; margin-right: 5px; font-size: 12px; }

        .role-badge {
            display: inline-block;
            padding: 5px 11px;
            border-radius: 20px;
            font-size: 10px; font-weight: 800;
            letter-spacing: .4px; text-transform: uppercase;
        }
        .role-badge.admin {
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
        }
        .role-badge.staff {
            background: #fdf1e2;
            color: #a35a0e;
        }

        .status-select { position: relative; display: inline-flex; align-items: center; }

        .status-select select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            height: 34px;
            padding: 0 32px 0 30px;
            border-radius: 20px;
            font-family: "DM Sans", sans-serif;
            font-size: 11px; font-weight: 800;
            letter-spacing: .4px;
            cursor: pointer;
            outline: none;
            border: 1.5px solid transparent;
            transition: .2s ease;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23817a71' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
        }

        .status-select::before {
            content: "";
            position: absolute;
            left: 12px; top: 50%;
            transform: translateY(-50%);
            width: 7px; height: 7px;
            border-radius: 50%;
            pointer-events: none;
            background: var(--dot-color, #b51f2c);
        }

        .status-select[data-status="1"] { --dot-color: #2e7d32; }
        .status-select[data-status="0"] { --dot-color: #b51f2c; }

        .status-select select[data-status="1"] {
            background-color: #e8f6ea; color: #2e7d32; border-color: #cbe7ce;
        }
        .status-select select[data-status="0"] {
            background-color: #fdecea; color: #b51f2c; border-color: #f4cdcd;
        }

        .status-select select:disabled { opacity: .7; cursor: wait; }

        .row-actions { display: flex; gap: 6px; align-items: center; justify-content: flex-end; }

        .btn-icon {
            width: 34px; height: 34px;
            border-radius: 10px;
            border: 1.5px solid #ece5da;
            background: #fff; color: #6f675f;
            display: inline-flex; align-items: center;
            justify-content: center;
            font-size: 14px;
            cursor: pointer; transition: .2s ease;
            text-decoration: none;
        }

        .btn-icon.edit:hover {
            border-color: #52745b; color: #52745b; background: #e8f1e8;
        }
        .btn-icon.delete:hover {
            border-color: #f0d6d8; color: #c62828; background: #fde6e6;
        }
        .btn-icon:disabled { opacity: .4; cursor: not-allowed; }

        .st-empty { text-align: center; padding: 60px 20px; color: #948c82; }
        .st-empty i { font-size: 50px; color: #ece5da; display: block; margin-bottom: 12px; }
        .st-empty h3 { margin: 0 0 5px; font-size: 15px; color: #6f675f; font-weight: 700; }
        .st-empty p { margin: 0; font-size: 12px; }

        #mmToast {
            position: fixed; bottom: 26px; left: 50%;
            transform: translateX(-50%) translateY(20px);
            padding: 12px 22px; border-radius: 12px;
            font-size: 12px; font-weight: 700;
            font-family: "DM Sans", sans-serif;
            color: #fff; background: #2e7d32;
            box-shadow: 0 15px 40px rgba(0,0,0,.25);
            z-index: 99999; opacity: 0;
            pointer-events: none;
            transition: opacity .25s ease, transform .25s ease;
        }

        #mmToast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        #mmToast.error { background: #b51f2c; }

        .mm-modal-overlay {
            position: fixed; inset: 0;
            background: rgba(30,25,22,.55);
            backdrop-filter: blur(3px);
            display: flex; align-items: center; justify-content: center;
            padding: 20px; z-index: 99999;
            opacity: 0; visibility: hidden; transition: .25s ease;
        }

        .mm-modal-overlay.show { opacity: 1; visibility: visible; }

        .mm-modal {
            background: #fff; border-radius: 18px;
            padding: 30px 26px 24px;
            max-width: 420px; width: 100%; text-align: center;
            box-shadow: 0 30px 80px rgba(0,0,0,.25);
            transform: translateY(15px) scale(.96);
            transition: transform .25s cubic-bezier(.2,.9,.3,1.2);
        }

        .mm-modal-overlay.show .mm-modal { transform: translateY(0) scale(1); }

        .mm-modal-icon {
            width: 66px; height: 66px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 30px; margin: 0 auto 16px;
            animation: popIn .35s cubic-bezier(.2,.9,.3,1.4);
        }

        .mm-modal-icon.danger { background: #fde6e6; color: #c62828; }

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
            font-family: "DM Sans", sans-serif;
        }

        .mm-btn-ghost {
            background: #fff; border: 1.5px solid #e4ddd3; color: #6f675f;
        }
        .mm-btn-danger {
            background: #c62828; color: #fff;
            box-shadow: 0 8px 20px rgba(198,40,40,.22);
        }
        .mm-btn-danger:hover { background: #a02020; }
        .mm-btn:disabled { opacity: .7; cursor: not-allowed; }

        .btn-spinner {
            width: 14px; height: 14px;
            border: 2px solid rgba(255,255,255,.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            display: inline-block;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 768px) {
            .st-page { padding: 20px 15px 30px; }
            .st-header { flex-direction: column; align-items: flex-start; }
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
                <input type="text" placeholder="Search staff...">
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


        <div class="st-page">

            <div class="st-header">
                <div class="st-title">
                    <h1>Staff Management</h1>
                    <p>Only admins can create, edit or remove staff accounts.</p>
                </div>

                <a href="add-staff.php" class="btn-add">
                    <i class="bi bi-person-plus"></i>
                    Add Staff
                </a>
            </div>


            <div class="st-card">

                <div class="st-table-wrap">
                    <table class="st-table">
                        <thead>
                            <tr>
                                <th>Staff</th>
                                <th>Mobile</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="staffTbody">
                            <tr>
                                <td colspan="6" style="text-align:center;padding:40px;color:#948c82;">
                                    Loading staff...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>

        </div>

    </main>


    <!-- DELETE CONFIRM -->
    <div class="mm-modal-overlay" id="delOverlay" aria-hidden="true">
        <div class="mm-modal">
            <div class="mm-modal-icon danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <h3 class="mm-modal-title">Delete Staff?</h3>
            <p class="mm-modal-text" id="delText">
                This will permanently remove the staff account.
            </p>
            <div class="mm-modal-actions">
                <button type="button" class="mm-btn mm-btn-ghost" id="delCancel">Cancel</button>
                <button type="button" class="mm-btn mm-btn-danger" id="delConfirm">
                    <i class="bi bi-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>


    <script>
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL ?>js/main.js"></script>
    <script src="<?= ADMIN_URL ?>js/staff.js"></script>

</body>

</html>