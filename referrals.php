<?php
require_once './config/config.php';
require_once './config/function.php';

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    header('Location: login.php');
    exit;
}
requireAdmin();
$isAdmin = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin');
$settings = getSettings($pdo);
$siteName = $settings['username'] ?? 'Mrs Mill@';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include './includes/head.php'; ?>

    <style>
        .rf-page {
            padding: 30px 32px 60px;
        }

        .rf-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .rf-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 5px;
        }

        .rf-title p {
            margin: 0;
            color: #817a71;
            font-size: 13px;
        }

        .rf-refresh-btn {
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
            transition: .2s;
        }

        .rf-refresh-btn:hover {
            background: #faf7f0;
            color: #302923;
        }

        .rf-card {
            background: #fff;
            border: 1px solid #eee7dc;
            border-radius: 20px;
            padding: 22px;
        }

        .rf-list-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .rf-list-title h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #302923;
        }

        .rf-list-title span {
            display: block;
            margin-top: 4px;
            font-size: 10px;
            color: #817a71;
        }

        .rf-search {
            position: relative;
            width: 260px;
        }

        .rf-search i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa198;
            font-size: 13px;
            pointer-events: none;
        }

        .rf-search input {
            width: 100%;
            height: 40px;
            border: 1px solid #eee7dc;
            border-radius: 10px;
            padding: 0 12px 0 36px;
            outline: none;
            font-family: "DM Sans", sans-serif;
            font-size: 11px;
            background: #fffdf9;
            transition: .15s;
        }

        .rf-search input:focus {
            border-color: #d98a91;
            background: #fff;
        }

        /* Filters */
        .rf-filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 18px;
            padding: 12px 14px;
            background: #fdfaf4;
            border: 1px solid #f0ebe4;
            border-radius: 12px;
        }

        .rf-filters .rf-fgroup {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .rf-filters label {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #938a80;
        }

        .rf-filters select,
        .rf-filters input[type=date] {
            height: 34px;
            border: 1px solid #e8e1d8;
            border-radius: 8px;
            background: #fff;
            padding: 0 10px;
            font-family: inherit;
            font-size: 11.5px;
            color: #4c4640;
            outline: none;
            transition: .15s;
            min-width: 130px;
        }

        .rf-filters select:focus,
        .rf-filters input[type=date]:focus {
            border-color: #d98a91;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        #rfCustomRange {
            display: none;
        }

        /* Table */
        .rf-table-wrap {
            overflow-x: auto;
            border-radius: 12px;
        }

        .rf-table {
            width: 100%;
            border-collapse: collapse;
        }

        .rf-table th {
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

        .rf-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #f2ede5;
            font-size: 12px;
            color: #4c4640;
            vertical-align: middle;
        }

        .rf-table tbody tr:hover {
            background: #fffdf9;
        }

        .rf-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .rf-cust {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .rf-avatar {
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

        .rf-cust-name {
            font-weight: 700;
            color: #302923;
            font-size: 12.5px;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }

        .rf-cust-mobile {
            font-size: 10.5px;
            color: #948c82;
            margin-top: 2px;
            white-space: nowrap;
        }

        .rf-ref-name {
            font-weight: 700;
            color: #302923;
            font-size: 12px;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }

        .rf-ref-mobile {
            font-size: 10.5px;
            color: #948c82;
            margin-top: 2px;
            white-space: nowrap;
        }

        .rf-ref-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .04em;
            padding: 2px 7px;
            border-radius: 4px;
            background: #e8f1e8;
            color: #2e6b3a;
            margin-top: 3px;
        }

        .rf-date {
            font-size: 11px;
            color: #6f675f;
            font-weight: 600;
            white-space: nowrap;
        }

        .rf-date small {
            display: block;
            font-size: 10px;
            color: #948c82;
            font-weight: 500;
            margin-top: 2px;
        }

        .rf-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .rf-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            height: 32px;
            padding: 0 12px;
            border-radius: 8px;
            border: 1.5px solid transparent;
            font-family: "DM Sans", sans-serif;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            transition: .15s ease;
            white-space: nowrap;
        }

        .rf-btn-edit {
            background: #fff5e6;
            color: #a06000;
            border-color: #f0c98a;
        }

        .rf-btn-edit:hover {
            background: #a06000;
            color: #fff;
            border-color: #a06000;
        }

        .rf-btn-view {
            background: #e8f1e8;
            color: #2e6b3a;
            border-color: #a7c8a9;
        }

        .rf-btn-view:hover {
            background: #2e6b3a;
            color: #fff;
            border-color: #2e6b3a;
        }

        /* Empty */
        .rf-empty {
            text-align: center;
            padding: 50px 20px;
            color: #948c82;
        }

        .rf-empty i {
            font-size: 42px;
            color: #d5cbbd;
            display: block;
            margin-bottom: 12px;
        }

        .rf-empty h3 {
            font-family: "Playfair Display", serif;
            font-size: 17px;
            color: #6f675f;
            margin: 0 0 6px;
            font-weight: 700;
        }

        .rf-empty p {
            margin: 0;
            font-size: 12px;
        }

        /* Pagination */
        .rf-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid #f2ede5;
            flex-wrap: wrap;
        }

        .rf-pag-left {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .rf-pag-info {
            font-size: 11px;
            color: #817a71;
        }

        .rf-pag-info strong {
            color: #302923;
            font-weight: 700;
        }

        .rf-perpage {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            color: #817a71;
        }

        .rf-perpage select {
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
        }

        .rf-pag-controls {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
        }

        .rf-pag-controls button {
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
            transition: .2s;
        }

        .rf-pag-controls button:hover:not(:disabled):not(.active) {
            background: #faf7f0;
        }

        .rf-pag-controls button.active {
            background: #b51f2c;
            border-color: #b51f2c;
            color: #fff;
            cursor: default;
        }

        .rf-pag-controls button:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        /* Toast */
        .rf-toast-wrap {
            position: fixed;
            top: 22px;
            right: 22px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 10000;
            pointer-events: none;
        }

        .rf-toast {
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
            transition: .3s;
            pointer-events: auto;
        }

        .rf-toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .rf-toast-icon {
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

        .rf-toast.success .rf-toast-icon {
            background: #4caf50;
        }

        .rf-toast.error .rf-toast-icon {
            background: #c62828;
        }

        .rf-toast-body {
            flex: 1;
            padding-top: 3px;
            line-height: 1.5;
        }

        .rf-toast-close {
            background: transparent;
            border: 0;
            color: #b5aca2;
            cursor: pointer;
            font-size: 14px;
            padding: 0 2px;
            line-height: 1;
        }

        /* Modal */
        .rf-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(30, 25, 22, .55);
            backdrop-filter: blur(3px);
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 24px 20px;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: .2s;
            overflow-y: auto;
        }

        .rf-modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .rf-modal {
            background: #fff;
            border-radius: 18px;
            width: 100%;
            max-width: 560px;
            padding: 26px 24px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, .25);
            transform: translateY(15px) scale(.97);
            transition: .25s;
            margin: auto;
        }

        .rf-modal-overlay.show .rf-modal {
            transform: translateY(0) scale(1);
        }

        .rf-modal-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 18px;
        }

        .rf-modal-head h3 {
            margin: 0;
            font-family: "Playfair Display", serif;
            font-size: 20px;
            color: #302923;
            font-weight: 700;
        }

        .rf-modal-head p {
            margin: 4px 0 0;
            font-size: 12px;
            color: #817a71;
        }

        .rf-modal-close {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: transparent;
            border: 0;
            color: #948c82;
            cursor: pointer;
            font-size: 18px;
        }

        .rf-modal-close:hover {
            background: #faf7f0;
            color: #302923;
        }

        .rf-modal-body {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .rf-modal-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        @media (max-width: 560px) {
            .rf-modal-row {
                grid-template-columns: 1fr;
            }
        }

        .rf-modal-field label {
            display: block;
            font-size: 10px;
            font-weight: 800;
            color: #4e4841;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .rf-modal-field input {
            width: 100%;
            border: 1.5px solid #e8e1d8;
            background: #fffdf9;
            border-radius: 9px;
            padding: 9px 11px;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            color: #292521;
            outline: none;
            transition: .15s;
        }

        .rf-modal-field input:focus {
            border-color: #d98a91;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .rf-modal-field input[readonly] {
            background: #f7f2ec;
            color: #6f5a3f;
            cursor: not-allowed;
        }

        .rf-modal-hint {
            font-size: 10.5px;
            color: #948c82;
            margin-top: 4px;
            line-height: 1.5;
        }

        .rf-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid #f0ebe4;
        }

        .rf-modal-btn {
            border: 0;
            border-radius: 10px;
            padding: 10px 18px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            transition: .15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .rf-modal-btn-ghost {
            background: #fff;
            border: 1.5px solid #e4ddd3;
            color: #6f675f;
        }

        .rf-modal-btn-ghost:hover {
            background: #faf7f0;
        }

        .rf-modal-btn-primary {
            background: #2e6b3a;
            color: #fff;
        }

        .rf-modal-btn-primary:hover {
            background: #1f4f29;
        }

        .rf-modal-btn[disabled] {
            opacity: .6;
            cursor: not-allowed;
        }

        @media (max-width: 768px) {
            .rf-page {
                padding: 20px 15px 40px;
            }

            .rf-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .rf-refresh-btn {
                width: 100%;
                justify-content: center;
            }

            .rf-card {
                padding: 17px;
                border-radius: 17px;
            }

            .rf-list-head {
                flex-direction: column;
                align-items: stretch;
            }

            .rf-search {
                width: 100%;
            }

            .rf-pagination {
                flex-direction: column;
                align-items: stretch;
            }

            .rf-pag-controls {
                justify-content: center;
            }
        }

        @keyframes rfSpin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <?php include './templates/sidebar.php'; ?>

    <main class="main">
        <?php include './templates/header.php'; ?>

        <div class="rf-page">
            <div class="rf-header">
                <div class="rf-title">
                    <h1>Referrals</h1>
                    <p>Track who referred which customer. Edit referrer details if needed.</p>
                </div>
                <button type="button" class="rf-refresh-btn" id="rfRefresh">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>

            <div class="rf-card">
                <div class="rf-list-head">
                    <div class="rf-list-title">
                        <h3>All Referrals</h3>
                        <span>Loaded from the database</span>
                    </div>
                    <div class="rf-search">
                        <i class="bi bi-search"></i>
                        <input type="text" id="rfSearch" placeholder="Search name, mobile...">
                    </div>
                </div>

                <div class="rf-filters">
                    <div class="rf-fgroup">
                        <label><i class="bi bi-calendar3"></i> Date</label>
                        <select id="rfFilterDate">
                            <option value="all">All time</option>
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="7d">Last 7 days</option>
                            <option value="30d">Last 30 days</option>
                            <option value="custom">Custom range…</option>
                        </select>
                    </div>
                    <div class="rf-fgroup" id="rfCustomRange">
                        <label>From</label><input type="date" id="rfDateFrom">
                        <label>To</label><input type="date" id="rfDateTo">
                    </div>
                </div>

                <div class="rf-table-wrap">
                    <table class="rf-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Referred By</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="rfTbody">
                            <tr>
                                <td colspan="4" style="text-align:center;padding:40px;color:#948c82;">
                                    <span style="display:inline-block;width:22px;height:22px;border:3px solid #eee7dc;border-top-color:#b51f2c;border-radius:50%;animation:rfSpin .7s linear infinite;"></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="rf-pagination" id="rfPagination" style="display:none;">
                    <div class="rf-pag-left">
                        <div class="rf-pag-info" id="rfPagInfo">
                            Showing <strong>0</strong>–<strong>0</strong> of <strong>0</strong>
                        </div>
                        <label class="rf-perpage">
                            Show
                            <select id="rfPerPage">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            entries
                        </label>
                    </div>
                    <div class="rf-pag-controls" id="rfPagControls"></div>
                </div>
            </div>
        </div>
    </main>

    <div class="rf-toast-wrap" id="rfToastWrap"></div>

    <!-- EDIT MODAL -->
    <div class="rf-modal-overlay" id="rfEditOverlay" aria-hidden="true">
        <div class="rf-modal" role="dialog" aria-modal="true">
            <div class="rf-modal-head">
                <div>
                    <h3>Edit Referrer</h3>
                    <p>Update who referred this customer.</p>
                </div>
                <button type="button" class="rf-modal-close" id="rfEditClose"><i class="bi bi-x-lg"></i></button>
            </div>

            <form id="rfEditForm">
                <input type="hidden" id="rfEditId">
                <div class="rf-modal-body">
                    <div class="rf-modal-field">
                        <label>Customer (Referred)</label>
                        <input type="text" id="rfCustName" readonly>
                        <input type="hidden" id="rfCustMobile">
                        <p class="rf-modal-hint">The customer who was referred — read-only.</p>
                    </div>

                    <div class="rf-modal-row">
                        <div class="rf-modal-field">
                            <label>Referrer Name</label>
                            <input type="text" id="rfRefName" maxlength="150" placeholder="Name of the referrer">
                        </div>
                        <div class="rf-modal-field">
                            <label>Referrer Mobile</label>
                            <input type="text" id="rfRefMobile" maxlength="15" inputmode="numeric" placeholder="10-digit mobile">
                        </div>
                    </div>

                    <p class="rf-modal-hint">
                        If the referrer is an existing customer (matched by mobile or name), their account will be linked automatically.
                    </p>
                </div>

                <div class="rf-modal-actions">
                    <button type="button" class="rf-modal-btn rf-modal-btn-ghost" id="rfEditCancel">Cancel</button>
                    <button type="submit" class="rf-modal-btn rf-modal-btn-primary" id="rfEditSubmit">
                        <i class="bi bi-check-lg"></i> Save Referrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
        window.IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL ?>js/main.js"></script>
    <script src="<?= ADMIN_URL ?>js/referrals.js"></script>
</body>

</html>