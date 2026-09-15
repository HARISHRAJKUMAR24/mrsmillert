<?php
require_once './config/config.php';
require_once './config/function.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include './includes/head.php'; ?>

    <style>
        .category-page { padding: 30px 32px 40px; }

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

        .category-title p { margin: 0; color: #817a71; font-size: 13px; }

        .back-btn {
            display: inline-flex; align-items: center; gap: 8px;
            border: 1px solid #e4ddd3; background: #fff;
            color: #6f675f; padding: 10px 16px; border-radius: 10px;
            font-size: 11px; font-weight: 700; text-decoration: none;
        }

        .category-form-card {
            background: #fff;
            border: 1px solid #eee7dc;
            border-radius: 20px;
            padding: 25px;
            max-width: 780px;
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

        .category-form label {
            display: block; font-size: 11px; font-weight: 700;
            color: #4e4841; margin-bottom: 7px;
        }

        .required { color: #b51f2c; }

        .category-input {
            width: 100%; height: 45px;
            border: 1px solid #e8e1d8;
            background: #fffdf9; border-radius: 11px;
            padding: 11px 13px;
            font-family: "DM Sans", sans-serif; font-size: 12px;
            color: #292521; outline: none; transition: .2s ease;
        }

        .category-input:focus {
            border-color: #d98a91; background: #fff;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        /* IMAGE UPLOAD */

        .image-upload-zone {
            border: 2px dashed #e4ddd3;
            border-radius: 14px;
            padding: 22px;
            text-align: center;
            background: #fffdf9;
            cursor: pointer;
            transition: .2s ease;
            position: relative;
        }

        .image-upload-zone:hover,
        .image-upload-zone.dragover {
            border-color: #d98a91;
            background: #fff5f5;
        }

        .image-upload-zone input[type="file"] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer;
        }

        .upload-placeholder i {
            font-size: 40px; color: #d5cbbd; display: block; margin-bottom: 10px;
        }

        .upload-placeholder strong {
            display: block; font-size: 12px; color: #302923; margin-bottom: 4px;
        }

        .upload-placeholder small { color: #948c82; font-size: 10px; }

        .image-preview-wrap {
            position: relative; display: inline-block;
        }

        .image-preview {
            max-width: 100%; max-height: 220px;
            border-radius: 12px; border: 1px solid #eee7dc;
            display: block;
        }

        .remove-preview {
            position: absolute; top: -10px; right: -10px;
            width: 30px; height: 30px; border-radius: 50%;
            background: #b51f2c; color: #fff; border: 2px solid #fff;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            font-size: 13px;
        }

        .form-actions {
            display: flex; justify-content: flex-end; gap: 10px;
            margin-top: 22px; padding-top: 20px; border-top: 1px solid #f0ebe4;
        }

        .btn-cancel {
            border: 1px solid #e4ddd3; background: #fff;
            color: #6f675f; border-radius: 10px;
            padding: 10px 17px; font-size: 11px; font-weight: 700;
            text-decoration: none;
        }

        .btn-save {
            border: none; background: #b51f2c; color: white;
            border-radius: 10px; padding: 10px 19px;
            font-size: 11px; font-weight: 700;
            display: inline-flex; align-items: center; gap: 7px;
            cursor: pointer; transition: .2s;
        }

        .btn-save:hover { background: #8e1722; }
        .btn-save:disabled { opacity: .7; cursor: not-allowed; }

        .btn-spinner {
            width: 14px; height: 14px;
            border: 2px solid rgba(255,255,255,.4);
            border-top-color: #fff; border-radius: 50%;
            animation: spin .7s linear infinite; display: inline-block;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* MODAL */

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

        .mm-btn-primary {
            background: #b51f2c; color: #fff;
            box-shadow: 0 8px 20px rgba(181, 31, 44, .22);
        }

        .mm-btn-primary:hover { background: #8e1722; color: #fff; }

        .mm-btn-ghost {
            background: #fff; border: 1px solid #e4ddd3; color: #6f675f;
        }

        .mm-btn-ghost:hover { background: #faf7f0; color: #302923; }

        @media (max-width: 768px) {
            .category-page { padding: 20px 15px 30px; }
            .category-header { flex-direction: column; align-items: flex-start; }
            .category-form-card { padding: 17px; border-radius: 17px; }
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


        <div class="category-page">

            <div class="category-header">
                <div class="category-title">
                    <h1>Add Category</h1>
                    <p>Create a new category with a name and image.</p>
                </div>

                <a href="list.php" class="back-btn">
                    <i class="bi bi-arrow-left"></i>
                    Back to List
                </a>
            </div>


            <div class="category-form-card">

                <div class="form-card-header">
                    <div class="form-card-icon">
                        <i class="bi bi-tag"></i>
                    </div>
                    <div>
                        <h3>Category Details</h3>
                        <span>All fields marked with * are required.</span>
                    </div>
                </div>

                <form id="categoryForm" class="category-form" method="POST" novalidate>

                    <div style="margin-bottom:18px;">
                        <label>Category Name <span class="required">*</span></label>
                        <input type="text"
                               name="category_name"
                               id="category_name"
                               class="category-input"
                               placeholder="Eg: Millet Snacks"
                               maxlength="150"
                               required>
                    </div>

                    <div style="margin-bottom:18px;">
                        <label>Status</label>
                        <select name="status" id="status" class="category-input">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>

                    <div style="margin-bottom:18px;">
                        <label>Category Image <span class="required">*</span></label>

                        <div class="image-upload-zone" id="uploadZone">
                            <input type="file"
                                   name="category_image"
                                   id="category_image"
                                   accept="image/jpeg,image/png,image/webp,image/gif">

                            <div class="upload-placeholder" id="uploadPlaceholder">
                                <i class="bi bi-cloud-arrow-up"></i>
                                <strong>Click or drag image here</strong>
                                <small>JPG, PNG, WEBP or GIF — max 3 MB</small>
                            </div>

                            <div class="image-preview-wrap" id="previewWrap" style="display:none;">
                                <img src="" alt="" class="image-preview" id="previewImg">
                                <button type="button" class="remove-preview" id="removePreview" title="Remove">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="list.php" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-save" id="saveBtn">
                            <i class="bi bi-check-lg"></i>
                            <span id="saveBtnText">Save Category</span>
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
            <div class="mm-modal-icon success"><i class="bi bi-check-lg"></i></div>
            <h3 class="mm-modal-title">Category Added!</h3>
            <p class="mm-modal-text" id="successText">
                Your category has been saved successfully.
            </p>
            <div class="mm-modal-actions">
                <a href="add.php" class="mm-btn mm-btn-ghost">
                    <i class="bi bi-plus-lg"></i> Add Another
                </a>
                <a href="category.php" class="mm-btn mm-btn-primary">
                    <i class="bi bi-list-ul"></i> View List
                </a>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL; ?>js/main.js"></script>
    <script src="<?= ADMIN_URL; ?>js/add-category.js"></script>

</body>
</html>