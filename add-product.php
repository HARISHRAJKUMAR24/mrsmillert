<?php
require_once './config/config.php';
require_once './config/function.php';

/* =========================================================
   LOAD CATEGORIES
   ========================================================= */

$categories = [];

try {
    $stmt = $pdo->query(
        "SELECT id, category_name
         FROM categories
         WHERE status = 1
         ORDER BY category_name ASC"
    );
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

/* =========================================================
   LOAD APARTMENTS
   ========================================================= */

$apartments = [];

try {
    $stmt = $pdo->query(
        "SELECT id, apartment_code, apartment_name, divisions
         FROM apartments
         WHERE status = 1
         ORDER BY apartment_name ASC"
    );

    $apartments = $stmt->fetchAll();

    foreach ($apartments as &$a) {
        $a['divisions'] = decodeDivisions($a['divisions'] ?? '');
    }
} catch (PDOException $e) {
    $apartments = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

    <style>
        /* =====================================================
           PRODUCT ADD PAGE
        ===================================================== */

        .product-page { padding: 30px 32px 40px; }

        .product-header {
            display: flex; align-items: center; justify-content: space-between;
            gap: 20px; margin-bottom: 25px;
        }

        .product-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px; font-weight: 700;
            color: #302923; margin: 0 0 5px;
        }

        .product-title p { margin: 0; color: #817a71; font-size: 13px; }

        .back-btn {
            display: inline-flex; align-items: center; gap: 8px;
            border: 1px solid #e4ddd3; background: #fff; color: #6f675f;
            padding: 10px 16px; border-radius: 10px;
            font-size: 11px; font-weight: 700; text-decoration: none;
        }

        .product-form-card {
            background: #fff; border: 1px solid #eee7dc;
            border-radius: 20px; padding: 25px; max-width: 1000px;
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

        .product-form label {
            display: block; font-size: 11px; font-weight: 700;
            color: #4e4841; margin-bottom: 7px;
        }

        .required { color: #b51f2c; }

        .product-input {
            width: 100%; height: 45px;
            border: 1px solid #e8e1d8; background: #fffdf9;
            border-radius: 11px; padding: 11px 13px;
            font-family: "DM Sans", sans-serif; font-size: 12px;
            color: #292521; outline: none; transition: .2s ease;
        }

        .product-input:focus {
            border-color: #d98a91; background: #fff;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .input-icon-wrap { position: relative; }
        .input-icon-wrap > i {
            position: absolute; left: 13px; top: 50%;
            transform: translateY(-50%); color: #aaa198;
            font-size: 15px; pointer-events: none;
        }
        .input-icon-wrap .product-input { padding-left: 40px; }


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
            margin-bottom: 18px;
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

        .status-toggle-info h4 {
            margin: 0; font-size: 13px; font-weight: 700; color: #302923;
        }
        .status-toggle-info p {
            margin: 2px 0 0; font-size: 10px; color: #948c82;
        }

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


        /* =====================================================
           VARIANTS SECTION
        ===================================================== */

        .variants-section {
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid #f0ebe4;
        }

        .variants-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; margin-bottom: 14px; flex-wrap: wrap;
        }

        .variants-head h4 {
            margin: 0; font-size: 13px; font-weight: 800; color: #302923;
            display: flex; align-items: center; gap: 8px;
        }

        .variants-head h4 i {
            width: 30px; height: 30px; border-radius: 9px;
            background: #fbe8e9; color: #b51f2c;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
        }

        .variants-head h4 .required { margin-left: 2px; }

        .variants-head p {
            margin: 4px 0 0 38px;
            font-size: 10px; color: #817a71;
        }

        .add-variant-btn {
            display: inline-flex; align-items: center; gap: 6px;
            background: #b51f2c; color: #fff;
            border: none; border-radius: 10px;
            padding: 9px 15px;
            font-size: 11px; font-weight: 700;
            cursor: pointer; transition: .2s;
        }

        .add-variant-btn:hover { background: #8e1722; }

        .variants-list {
            display: flex; flex-direction: column; gap: 12px;
        }

        .variant-row {
            border: 1px solid #f0ebe4;
            background: #fffdf9;
            border-radius: 14px;
            padding: 14px 14px 12px;
            position: relative;
            animation: variantIn .25s ease;
        }

        @keyframes variantIn {
            0% { transform: translateY(-6px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }

        .variant-row-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 10px; margin-bottom: 12px;
        }

        .variant-badge {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 10px; font-weight: 800;
            background: #f1e8df; color: #755d48;
            padding: 4px 9px; border-radius: 6px;
            letter-spacing: .3px;
        }

        .variant-badge i { color: #b51f2c; font-size: 11px; }

        .variant-remove-btn {
            background: #fff;
            border: 1px solid #f0d6d8;
            color: #b51f2c;
            width: 28px; height: 28px;
            border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer; transition: .2s;
            font-size: 13px;
        }

        .variant-remove-btn:hover {
            background: #fde6e6; border-color: #f5c0c0;
            color: #c62828;
        }

        .variant-grid {
            display: grid;
            grid-template-columns: 1fr 150px;
            gap: 10px;
            margin-bottom: 10px;
        }

        .variant-grid .field-label {
            font-size: 10px; font-weight: 700; color: #6f675f;
            margin-bottom: 5px; display: block;
        }

        .variant-input {
            width: 100%; height: 42px;
            border: 1px solid #e8e1d8; background: #fff;
            border-radius: 10px; padding: 10px 12px;
            font-family: "DM Sans", sans-serif; font-size: 12px;
            color: #292521; outline: none; transition: .2s ease;
        }

        .variant-input:focus {
            border-color: #d98a91; background: #fff;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .variants-empty {
            text-align: center; padding: 30px 20px;
            border: 2px dashed #e4ddd3; border-radius: 14px;
            color: #948c82; font-size: 11px; background: #fffdf9;
        }

        .variants-empty i {
            font-size: 30px; color: #d5cbbd;
            display: block; margin-bottom: 8px;
        }


        /* IMAGE UPLOAD */

        .img-upload-zone {
            position: relative;
            border: 2px dashed #e4ddd3; border-radius: 14px;
            padding: 22px; text-align: center;
            background: #fffdf9; cursor: pointer;
            transition: .2s ease;
        }

        .img-upload-zone:hover,
        .img-upload-zone.dragover {
            border-color: #d98a91; background: #fff5f5;
        }

        .img-upload-zone input[type="file"] {
            position: absolute; inset: 0;
            opacity: 0; cursor: pointer;
        }

        .img-placeholder i {
            font-size: 40px; color: #d5cbbd;
            display: block; margin-bottom: 10px;
        }

        .img-placeholder strong {
            display: block; font-size: 12px;
            color: #302923; margin-bottom: 4px;
        }

        .img-placeholder small { color: #948c82; font-size: 10px; }

        .img-preview-wrap { position: relative; display: inline-block; }

        .img-preview {
            max-width: 100%; max-height: 220px;
            border-radius: 12px; border: 1px solid #eee7dc; display: block;
        }

        .img-preview-actions {
            display: flex; justify-content: center; gap: 8px; margin-top: 12px;
        }

        .img-action-btn {
            display: inline-flex; align-items: center; gap: 6px;
            border-radius: 9px; padding: 8px 14px;
            font-size: 10px; font-weight: 700;
            cursor: pointer; border: 1px solid transparent;
            transition: .2s ease;
        }

        .img-action-btn.replace {
            background: #fff5f5; border-color: #f3c8cc; color: #b51f2c;
        }

        .img-action-btn.remove {
            background: #fff; border-color: #f0d6d8; color: #b51f2c;
        }

        .img-action-btn.remove:hover {
            background: #fde6e6; border-color: #f5c0c0; color: #c62828;
        }


        /* =====================================================
           APARTMENT MANAGEMENT (Collapsible)
        ===================================================== */

        .apartments-section {
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid #f0ebe4;
        }

        .apartments-box {
            border: 1px solid #f0ebe4;
            border-radius: 14px;
            background: #fffdf9;
            overflow: hidden;
            transition: .2s ease;
        }

        .apartments-box.collapsed .apartments-body { display: none; }
        .apartments-box.collapsed { background: #fff; }

        .apartments-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; padding: 14px 16px;
            cursor: pointer; user-select: none;
            transition: .2s ease; flex-wrap: wrap;
        }

        .apartments-head:hover { background: #fffaf9; }

        .apartments-head-title {
            display: flex; align-items: center; gap: 10px; min-width: 0;
        }

        .apartments-head-title i.box-icon {
            width: 34px; height: 34px; border-radius: 10px;
            background: #fbe8e9; color: #b51f2c;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; flex-shrink: 0;
        }

        .apartments-head-title h4 {
            margin: 0; font-size: 13px; font-weight: 800; color: #302923;
        }

        .apartments-head-title h4 .required {
            color: #b51f2c; margin-left: 2px;
            display: inline !important; white-space: nowrap;
        }

        .apartments-head-title span {
            display: block; font-size: 10px; color: #817a71; margin-top: 2px;
        }

        .apartments-head-actions {
            display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        }

        .apartments-selected-count {
            font-size: 10px; font-weight: 700;
            background: #e8f1e8; color: #52745b;
            padding: 6px 10px; border-radius: 20px; white-space: nowrap;
        }

        .toggle-chevron {
            width: 30px; height: 30px; border-radius: 50%;
            background: #fff; border: 1px solid #e4ddd3;
            display: flex; align-items: center; justify-content: center;
            color: #6f675f; font-size: 14px; flex-shrink: 0;
            transition: transform .25s ease, background .2s ease;
        }

        .apartments-box.collapsed .toggle-chevron { transform: rotate(-90deg); }
        .apartments-head:hover .toggle-chevron {
            background: #faf7f0; border-color: #d5cbbd;
        }

        .apartments-body { padding: 0 16px 16px; }

        .select-all-row {
            display: flex; align-items: center; justify-content: flex-end;
            margin-bottom: 10px;
        }

        .select-all-btn {
            display: inline-flex; align-items: center; gap: 6px;
            border: 1px solid #e4ddd3; background: #fff; color: #6f675f;
            padding: 7px 13px; border-radius: 10px;
            font-size: 10px; font-weight: 700; cursor: pointer;
            transition: .2s ease;
        }

        .select-all-btn:hover {
            background: #faf7f0; color: #302923; border-color: #d5cbbd;
        }

        .select-all-btn.all-active {
            background: #fde6e6; border-color: #f5c0c0; color: #c62828;
        }

        .apt-search { position: relative; margin-bottom: 12px; }
        .apt-search i {
            position: absolute; left: 13px; top: 50%;
            transform: translateY(-50%); color: #aaa198; font-size: 14px;
        }
        .apt-search input {
            width: 100%; height: 42px;
            border: 1px solid #e8e1d8; background: #fff;
            border-radius: 11px; padding: 0 12px 0 38px;
            font-family: "DM Sans", sans-serif; font-size: 12px;
            color: #292521; outline: none; transition: .2s ease;
        }
        .apt-search input:focus {
            border-color: #d98a91; background: #fff;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .apt-list {
            max-height: 380px; overflow-y: auto;
            border: 1px solid #f0ebe4; border-radius: 12px;
            background: #fff; padding: 8px;
            display: flex; flex-direction: column; gap: 8px;
        }

        .apt-list::-webkit-scrollbar { width: 8px; }
        .apt-list::-webkit-scrollbar-track { background: transparent; }
        .apt-list::-webkit-scrollbar-thumb { background: #e4ddd3; border-radius: 8px; }

        .apt-item {
            border: 1px solid #f0ebe4; background: #fff;
            border-radius: 12px; padding: 12px 14px;
            cursor: pointer; display: flex; align-items: flex-start; gap: 12px;
            transition: .18s ease; user-select: none;
        }
        .apt-item:hover { border-color: #d98a91; background: #fffaf9; }
        .apt-item.selected {
            border-color: #b51f2c; background: #fff5f5;
            box-shadow: 0 4px 14px rgba(181, 31, 44, .08);
        }

        .apt-tick {
            width: 22px; height: 22px; border-radius: 6px;
            border: 2px solid #d5cbbd; background: #fff; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; color: #fff; transition: .18s ease; margin-top: 1px;
        }
        .apt-tick i { opacity: 0; transition: .18s ease; }
        .apt-item.selected .apt-tick {
            background: #b51f2c; border-color: #b51f2c;
        }
        .apt-item.selected .apt-tick i { opacity: 1; }

        .apt-main { flex: 1; min-width: 0; }
        .apt-name {
            font-size: 12px; font-weight: 800; color: #302923;
            display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        }

        .apt-code {
            font-size: 9px; font-weight: 700;
            background: #faf7f0; color: #6f5a3f;
            padding: 2px 7px; border-radius: 5px; letter-spacing: .5px;
        }

        .apt-divisions {
            display: flex; flex-wrap: wrap; gap: 6px;
            margin-top: 10px; padding-top: 10px;
            border-top: 1px dashed #f0ebe4;
        }

        .apt-division-chip {
            display: inline-flex; align-items: center; gap: 5px;
            background: #f1e8df; color: #755d48;
            font-size: 10px; font-weight: 700;
            padding: 4px 8px; border-radius: 8px;
        }

        .apt-division-chip em {
            font-style: normal; background: #fff; color: #b51f2c;
            padding: 1px 6px; border-radius: 5px;
            font-size: 9px; font-weight: 800;
        }

        .apt-empty {
            text-align: center; padding: 30px 20px;
            color: #948c82; font-size: 11px;
        }
        .apt-empty i {
            font-size: 30px; color: #d5cbbd;
            display: block; margin-bottom: 8px;
        }


        /* ACTIONS */

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


        /* MODALS */

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
            .product-page { padding: 20px 15px 30px; }
            .product-header { flex-direction: column; align-items: flex-start; }
            .product-form-card { padding: 17px; border-radius: 17px; }
            .variant-grid { grid-template-columns: 1fr; }
            .form-actions { flex-direction: column-reverse; }
            .btn-cancel, .btn-save { width: 100%; justify-content: center; }
            .apartments-head { padding: 12px 14px; }
            .apartments-body { padding: 0 14px 14px; }
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


        <div class="product-page">

            <div class="product-header">
                <div class="product-title">
                    <h1>Add Product</h1>
                    <p>Add a new product, its quantity variants and which apartments can deliver it.</p>
                </div>

            </div>


            <div class="product-form-card">

                <div class="form-card-header">
                    <div class="form-card-icon">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div>
                        <h3>Product Details</h3>
                        <span>All fields marked with * are required.</span>
                    </div>
                </div>

                <form id="productForm" class="product-form" method="POST" novalidate>

                    <div class="row g-3">

                        <div class="col-12">
                            <label>Product Name <span class="required">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="bi bi-bag"></i>
                                <input type="text"
                                    id="product_name"
                                    class="product-input"
                                    placeholder="Eg: Organic Millet Flour"
                                    maxlength="150"
                                    required>
                            </div>
                        </div>

                        <div class="col-12">
                            <label>Category <span class="required">*</span></label>
                            <select id="category_id" class="product-input" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>">
                                        <?= htmlspecialchars($c['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($categories)): ?>
                                <div style="font-size:10px;color:#b51f2c;margin-top:6px;">
                                    <i class="bi bi-exclamation-circle"></i>
                                    No categories found. Please add one first.
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- ==================== STATUS TOGGLE ==================== -->

                        <div class="col-12">
                            <div class="status-toggle-row is-active" id="statusToggleRow">
                                <div class="status-toggle-info">
                                    <div class="status-toggle-icon" id="statusToggleIcon">
                                        <i class="bi bi-check-circle-fill"></i>
                                    </div>
                                    <div>
                                        <h4 id="statusToggleTitle">Active</h4>
                                        <p id="statusToggleDesc">Product will be visible to customers.</p>
                                    </div>
                                </div>
                                <label class="mm-switch" for="product_status">
                                    <input type="checkbox" id="product_status" name="product_status" checked>
                                    <span class="mm-switch-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label>Product Image <span class="required">*</span></label>

                            <div class="img-upload-zone" id="imgZone">
                                <input type="file"
                                    id="product_image"
                                    accept="image/jpeg,image/png,image/webp,image/gif">

                                <div class="img-placeholder" id="imgPlaceholder">
                                    <i class="bi bi-cloud-arrow-up"></i>
                                    <strong>Click or drag image here</strong>
                                    <small>JPG, PNG, WEBP or GIF — max 3 MB</small>
                                </div>

                                <div class="img-preview-wrap" id="imgPreviewWrap" style="display:none;">
                                    <img src="" alt="" class="img-preview" id="imgPreview">

                                    <div class="img-preview-actions">
                                        <button type="button" class="img-action-btn replace" id="imgReplaceBtn">
                                            <i class="bi bi-arrow-repeat"></i> Replace
                                        </button>
                                        <button type="button" class="img-action-btn remove" id="imgRemoveBtn">
                                            <i class="bi bi-trash"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>


                    <!-- ==================== QUANTITY VARIANTS ==================== -->

                    <div class="variants-section">

                        <div class="variants-head">
                            <div>
                                <h4>
                                    <i class="bi bi-layers"></i>
                                    Quantity Variants<span class="required">*</span>
                                </h4>
                                <p>Add one or more variants (e.g. 500g → ₹120, 1kg → ₹220).</p>
                            </div>

                            <button type="button" class="add-variant-btn" id="addVariantBtn">
                                <i class="bi bi-plus-lg"></i> Add Variant
                            </button>
                        </div>

                        <div class="variants-list" id="variantsList">
                            <!-- JS renders variant rows here -->
                        </div>

                        <div class="variants-empty" id="variantsEmpty">
                            <i class="bi bi-layers"></i>
                            No variants added yet. Click <strong>Add Variant</strong> to create one.
                        </div>

                    </div>


                    <!-- APARTMENT MANAGEMENT (COLLAPSIBLE) -->

                    <div class="apartments-section">

                        <div class="apartments-box" id="apartmentsBox">

                            <div class="apartments-head" id="apartmentsHead">

                                <div class="apartments-head-title">
                                    <i class="bi bi-buildings box-icon"></i>
                                    <div>
                                        <h4>
                                            Apartment Management<span class="required">*</span>
                                        </h4>
                                        <span>Choose which apartments can deliver this product.</span>
                                    </div>
                                </div>

                                <div class="apartments-head-actions">
                                    <div class="apartments-selected-count" id="selectedCount">
                                        0 selected
                                    </div>

                                    <div class="toggle-chevron" id="toggleChevron">
                                        <i class="bi bi-chevron-down"></i>
                                    </div>
                                </div>

                            </div>

                            <div class="apartments-body" id="apartmentsBody">

                                <div class="select-all-row">
                                    <button type="button"
                                        class="select-all-btn"
                                        id="selectAllBtn">
                                        <i class="bi bi-check2-square"></i>
                                        <span id="selectAllText">Deselect All</span>
                                    </button>
                                </div>

                                <div class="apt-search">
                                    <i class="bi bi-search"></i>
                                    <input type="text"
                                        id="aptSearchInput"
                                        placeholder="Search apartment by name or code...">
                                </div>

                                <div class="apt-list" id="aptList"></div>

                            </div>

                        </div>

                    </div>


                    <div class="form-actions">
                        <a href="list.php" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-save" id="saveBtn">
                            <i class="bi bi-check-lg"></i>
                            <span id="saveBtnText">Save Product</span>
                        </button>
                    </div>

                </form>

            </div>

        </div>

    </main>


    <!-- ERROR POPUP -->

    <div class="mm-modal-overlay" id="errorOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog" aria-modal="true">
            <div class="mm-modal-icon error">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <h3 class="mm-modal-title" id="errorTitle">Oops!</h3>
            <p class="mm-modal-text" id="errorText">Something went wrong.</p>
            <div class="mm-modal-actions">
                <button type="button" class="mm-btn mm-btn-primary" id="errorOkBtn">
                    <i class="bi bi-check2"></i> Got it
                </button>
            </div>
        </div>
    </div>


    <!-- SUCCESS POPUP -->

    <div class="mm-modal-overlay" id="successOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog" aria-modal="true">
            <div class="mm-modal-icon success">
                <i class="bi bi-check-lg"></i>
            </div>
            <h3 class="mm-modal-title">Product Added!</h3>
            <p class="mm-modal-text" id="successText">Your product has been saved successfully.</p>
            <div class="mm-modal-actions">
                <a href="add-product.php" class="mm-btn mm-btn-ghost">
                    <i class="bi bi-plus-lg"></i> Add Another
                </a>
                <a href="products.php" class="mm-btn mm-btn-primary">
                    <i class="bi bi-list-ul"></i> View Products
                </a>
            </div>
        </div>
    </div>


    <script>
        window.ADMIN_URL = "<?= ADMIN_URL; ?>";
        window.APARTMENTS = <?= json_encode($apartments, JSON_UNESCAPED_UNICODE); ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL; ?>js/main.js"></script>
    <script src="<?= ADMIN_URL; ?>js/add-product.js"></script>

</body>

</html>