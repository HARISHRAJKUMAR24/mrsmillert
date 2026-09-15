<?php
require_once './config/config.php';
require_once './config/function.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include './includes/head.php'; ?>

    <style>
        .category-page {
            padding: 30px 32px 40px;
        }

        .category-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .category-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 5px;
        }

        .category-title p {
            margin: 0;
            color: #817a71;
            font-size: 13px;
        }

        .add-category-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            background: #b51f2c;
            color: #fff;
            padding: 12px 18px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 8px 20px rgba(181, 31, 44, .16);
            transition: .2s ease;
        }

        .add-category-btn:hover {
            background: #8e1722;
            color: #fff;
            transform: translateY(-2px);
        }

        .category-list-card {
            background: #fff;
            border: 1px solid #eee7dc;
            border-radius: 20px;
            padding: 22px;
        }

        .list-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 20px;
        }

        .list-title h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
        }

        .list-title span {
            display: block;
            margin-top: 4px;
            font-size: 10px;
            color: #817a71;
        }

        .category-search {
            position: relative;
            width: 250px;
        }

        .category-search i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa198;
            font-size: 13px;
        }

        .category-search input {
            width: 100%;
            height: 40px;
            border: 1px solid #eee7dc;
            border-radius: 10px;
            padding: 0 12px 0 36px;
            outline: none;
            font-size: 11px;
            background: #fffdf9;
        }

        .category-search input:focus {
            border-color: #d98a91;
            background: #fff;
        }

        .category-table {
            width: 100%;
            border-collapse: collapse;
        }

        .category-table th {
            background: #faf7f0;
            color: #938a80;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .7px;
            padding: 12px;
            border-bottom: 1px solid #eee7dc;
            text-align: left;
            white-space: nowrap;
        }

        .category-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #f2ede5;
            font-size: 11px;
            color: #4c4640;
            vertical-align: middle;
        }

        .category-table tbody tr:hover {
            background: #fffdf9;
        }

        .cat-thumb {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid #eee7dc;
            background: #faf7f0;
            display: block;
        }

        .cat-name {
            font-weight: 700;
            color: #302923;
            font-size: 12px;
        }

        .cat-slug {
            font-size: 9px;
            color: #999188;
            margin-top: 3px;
            font-family: monospace;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 8px;
            border-radius: 20px;
            font-size: 8px;
            font-weight: 700;
        }

        .status-active {
            background: #e8f1e8;
            color: #52745b;
        }

        .status-inactive {
            background: #f4ecec;
            color: #8b5a5a;
        }

        .status-badge i {
            font-size: 7px;
        }

        .action-buttons {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .table-action {
            width: 31px;
            height: 31px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #eee7dc;
            background: #fff;
            color: #817a71;
            cursor: pointer;
            text-decoration: none;
            transition: .2s ease;
        }

        .table-action:hover {
            background: #fbe8e9;
            border-color: #f1c8cc;
            color: #b51f2c;
        }

        .table-action.delete:hover {
            background: #fde6e6;
            border-color: #f5c0c0;
            color: #c62828;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #948c82;
            font-size: 12px;
        }

        .empty-state i {
            font-size: 40px;
            color: #d5cbbd;
            display: block;
            margin-bottom: 12px;
        }

        .loading-row td {
            text-align: center;
            padding: 40px;
            color: #948c82;
        }

        .table-spinner {
            width: 22px;
            height: 22px;
            border: 3px solid #eee7dc;
            border-top-color: #b51f2c;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            display: inline-block;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* =====================================================
           PAGINATION
        ===================================================== */

        .category-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid #f2ede5;
            flex-wrap: wrap;
        }

        .pagination-left {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .pagination-info {
            font-size: 11px;
            color: #817a71;
        }

        .pagination-info strong {
            color: #302923;
            font-weight: 700;
        }

        .pagination-perpage {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            color: #817a71;
        }

        .pagination-perpage select {
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

        .pagination-perpage select:hover {
            border-color: #e4ddd3;
            background-color: #fff;
        }

        .pagination-perpage select:focus {
            border-color: #d98a91;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .pagination-controls button {
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

        .pagination-controls button:hover:not(:disabled):not(.active) {
            background: #faf7f0;
            border-color: #e4ddd3;
            color: #302923;
        }

        .pagination-controls button.active {
            background: #b51f2c;
            border-color: #b51f2c;
            color: #fff;
            box-shadow: 0 4px 12px rgba(181, 31, 44, .22);
            cursor: default;
        }

        .pagination-controls button:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .pagination-controls .page-ellipsis {
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

        .pagination-controls i {
            font-size: 12px;
        }

        /* MODAL + TOAST reuse */

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
            padding: 28px 26px 22px;
            max-width: 400px;
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
            width: 62px;
            height: 62px;
            border-radius: 50%;
            background: #fde6e6;
            color: #c62828;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin: 0 auto 16px;
        }

        .mm-modal-title {
            margin: 0 0 8px;
            font-size: 17px;
            font-weight: 800;
            color: #302923;
        }

        .mm-modal-text {
            margin: 0 0 22px;
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
            transition: .2s ease;
        }

        .mm-btn-cancel {
            background: #fff;
            border: 1px solid #e4ddd3;
            color: #6f675f;
        }

        .mm-btn-danger {
            background: #b51f2c;
            color: #fff;
            box-shadow: 0 8px 20px rgba(181, 31, 44, .22);
        }

        .mm-btn-danger:hover {
            background: #8e1722;
        }

        .mm-btn-danger:disabled {
            opacity: .7;
            cursor: not-allowed;
        }

        .mm-btn-spinner {
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, .4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: mmSpin .7s linear infinite;
            display: inline-block;
        }

        @keyframes mmSpin {
            to {
                transform: rotate(360deg);
            }
        }

        .mm-toast-container {
            position: fixed;
            top: 22px;
            right: 22px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 10000;
            pointer-events: none;
        }

        .mm-toast {
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

        .mm-toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .mm-toast-icon {
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

        .mm-toast-body {
            flex: 1;
            padding-top: 3px;
            line-height: 1.5;
        }

        .mm-toast-close {
            background: transparent;
            border: 0;
            color: #b5aca2;
            cursor: pointer;
            font-size: 14px;
            padding: 0 2px;
            line-height: 1;
        }

        .mm-toast.success .mm-toast-icon {
            background: #4caf50;
        }

        .mm-toast.error .mm-toast-icon {
            background: #c62828;
        }

        .mm-toast.info .mm-toast-icon {
            background: #3b82f6;
        }

        @media (max-width: 768px) {
            .category-page {
                padding: 20px 15px 30px;
            }

            .category-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .add-category-btn {
                width: 100%;
                justify-content: center;
            }

            .category-list-card {
                padding: 17px;
                border-radius: 17px;
            }

            .list-header {
                flex-direction: column;
                align-items: stretch;
            }

            .category-search {
                width: 100%;
            }

            .table-responsive {
                overflow-x: auto;
            }

            .category-table {
                min-width: 750px;
            }

            .category-pagination {
                flex-direction: column;
                align-items: stretch;
            }

            .pagination-left {
                justify-content: center;
            }

            .pagination-info {
                text-align: center;
            }

            .pagination-controls {
                justify-content: center;
                flex-wrap: wrap;
            }
        }

        @media (max-width: 480px) {
            .mm-toast-container {
                top: 14px;
                right: 14px;
                left: 14px;
            }

            .mm-toast {
                min-width: 0;
                width: 100%;
            }

            .pagination-controls button {
                min-width: 30px;
                height: 30px;
                font-size: 10px;
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


        <div class="category-page">

            <div class="category-header">
                <div class="category-title">
                    <h1>Categories</h1>
                    <p>Manage product categories and their images.</p>
                </div>

                <a href="add-category.php" class="add-category-btn">
                    <i class="bi bi-plus-lg"></i>
                    Add Category
                </a>
            </div>


            <div class="category-list-card">

                <div class="list-header">
                    <div class="list-title">
                        <h3>Category List</h3>
                        <span>All categories loaded from the database</span>
                    </div>

                    <div class="category-search">
                        <i class="bi bi-search"></i>
                        <input type="text" id="categorySearch" placeholder="Search category...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="category-table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="categoryTbody">
                            <tr class="loading-row">
                                <td colspan="4">
                                    <span class="table-spinner"></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>


                <!-- =====================================================
                     PAGINATION
                ====================================================== -->

                <div class="category-pagination" id="categoryPagination" style="display:none;">

                    <div class="pagination-left">

                        <div class="pagination-info" id="paginationInfo">
                            Showing <strong>0</strong>–<strong>0</strong> of <strong>0</strong>
                        </div>

                        <label class="pagination-perpage">
                            Show
                            <select id="perPageSelect">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            entries
                        </label>

                    </div>

                    <div class="pagination-controls" id="paginationControls">
                        <!-- filled by JS -->
                    </div>

                </div>

            </div>

        </div>

    </main>


    <!-- MODAL -->
    <div class="mm-modal-overlay" id="mmModalOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog" aria-modal="true">
            <div class="mm-modal-icon">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <h3 class="mm-modal-title">Delete Category?</h3>
            <p class="mm-modal-text" id="mmModalText">
                This action cannot be undone. The category and its image will be removed.
            </p>
            <div class="mm-modal-actions">
                <button type="button" class="mm-btn mm-btn-cancel" id="mmModalCancel">Cancel</button>
                <button type="button" class="mm-btn mm-btn-danger" id="mmModalConfirm">
                    <i class="bi bi-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>

    <!-- TOAST -->
    <div class="mm-toast-container" id="mmToastContainer"></div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL; ?>js/main.js"></script>
    <script src="<?= ADMIN_URL; ?>js/category-list.js"></script>

</body>

</html>