<?php
require_once './config/config.php';
require_once './config/function.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include './includes/head.php'; ?>

    <style>
        .settings-page { padding: 30px 32px 40px; }

        .settings-header {
            display: flex; align-items: center; justify-content: space-between;
            gap: 20px; margin-bottom: 25px;
        }

        .settings-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px; font-weight: 700;
            color: #302923; margin: 0 0 5px;
        }

        .settings-title p { margin: 0; color: #817a71; font-size: 13px; }

        .settings-form-card {
            background: #fff; border: 1px solid #eee7dc;
            border-radius: 20px; padding: 25px; max-width: 900px;
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

        .settings-form label {
            display: block; font-size: 11px; font-weight: 700;
            color: #4e4841; margin-bottom: 7px;
        }

        .required { color: #b51f2c; }

        .settings-input {
            width: 100%; height: 45px;
            border: 1px solid #e8e1d8; background: #fffdf9;
            border-radius: 11px; padding: 11px 13px;
            font-family: "DM Sans", sans-serif; font-size: 12px;
            color: #292521; outline: none; transition: .2s ease;
        }

        .settings-input:focus {
            border-color: #d98a91; background: #fff;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .input-icon-wrap { position: relative; }
        .input-icon-wrap > i {
            position: absolute; left: 13px; top: 50%;
            transform: translateY(-50%); color: #aaa198;
            font-size: 15px; pointer-events: none;
        }
        .input-icon-wrap .settings-input { padding-left: 40px; }

        .section-divider {
            margin: 26px 0 20px; padding-top: 22px;
            border-top: 1px solid #f0ebe4;
            display: flex; align-items: center; gap: 10px;
        }

        .section-divider i {
            width: 32px; height: 32px; border-radius: 10px;
            background: #fbe8e9; color: #b51f2c;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
        }

        .section-divider h4 {
            margin: 0; font-size: 13px; font-weight: 800; color: #302923;
        }

        .section-divider span {
            display: block; font-size: 10px; color: #817a71;
            margin-top: 2px;
        }

        /* IMAGE BLOCKS */

        .img-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .img-block {
            background: #fffdf9;
            border: 1px solid #f0ebe4;
            border-radius: 14px;
            padding: 16px;
        }

        .img-block-title {
            font-size: 11px;
            font-weight: 800;
            color: #302923;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .img-block-sub {
            font-size: 10px;
            color: #948c82;
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .img-upload-zone {
            position: relative;
            border: 2px dashed #e4ddd3;
            border-radius: 12px;
            padding: 18px;
            text-align: center;
            background: #fff;
            cursor: pointer;
            transition: .2s ease;
            min-height: 160px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .img-upload-zone:hover,
        .img-upload-zone.dragover {
            border-color: #d98a91;
            background: #fff5f5;
        }

        .img-upload-zone input[type="file"] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer;
        }

        .img-placeholder i {
            font-size: 32px;
            color: #d5cbbd;
            display: block;
            margin-bottom: 8px;
        }

        .img-placeholder strong {
            display: block;
            font-size: 11px;
            color: #302923;
            margin-bottom: 3px;
        }

        .img-placeholder small {
            color: #948c82;
            font-size: 9px;
        }

        .img-preview-wrap {
            position: relative;
            display: inline-block;
            max-width: 100%;
        }

        .img-preview {
            max-width: 100%;
            max-height: 140px;
            border-radius: 10px;
            border: 1px solid #eee7dc;
            background: #fff;
            display: block;
            padding: 6px;
        }

        .img-preview.ico {
            width: 64px;
            height: 64px;
            object-fit: contain;
            padding: 8px;
        }

        .img-preview-actions {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .img-action-btn {
            display: inline-flex; align-items: center; gap: 5px;
            border-radius: 8px; padding: 6px 11px;
            font-size: 10px; font-weight: 700;
            cursor: pointer; transition: .2s ease;
            border: 1px solid transparent;
        }

        .img-action-btn.replace {
            background: #fff5f5;
            border-color: #f3c8cc;
            color: #b51f2c;
        }

        .img-action-btn.replace:hover { background: #fbe8e9; }

        .img-action-btn.remove {
            background: #fff;
            border-color: #f0d6d8;
            color: #b51f2c;
        }

        .img-action-btn.remove:hover {
            background: #fde6e6;
            border-color: #f5c0c0;
            color: #c62828;
        }

        .new-image-note,
        .removed-image-note {
            font-size: 9px;
            padding: 6px 10px;
            border-radius: 7px;
            margin-top: 8px;
            display: none;
        }

        .new-image-note {
            color: #6f5a3f;
            background: #fdf5e8;
            border: 1px dashed #e8d2a8;
        }
        .new-image-note.show { display: block; }

        .removed-image-note {
            color: #a02a2a;
            background: #fdeaea;
            border: 1px dashed #f3c8cc;
            align-items: center;
            gap: 6px;
        }
        .removed-image-note.show { display: flex; }

        /* ACTIONS */

        .form-actions {
            display: flex; justify-content: flex-end; gap: 10px;
            margin-top: 22px; padding-top: 20px; border-top: 1px solid #f0ebe4;
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
            border: 2px solid rgba(255,255,255,.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            display: inline-block;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .form-loading {
            text-align: center; padding: 60px 20px;
            color: #948c82; font-size: 12px;
        }

        .form-loading .table-spinner {
            width: 26px; height: 26px;
            border: 3px solid #eee7dc;
            border-top-color: #b51f2c;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            display: inline-block; margin-bottom: 10px;
        }

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
        .mm-modal-icon.danger  { background: #fde6e6; color: #c62828; }

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
        .mm-btn-ghost { background: #fff; border: 1px solid #e4ddd3; color: #6f675f; }
        .mm-btn-ghost:hover { background: #faf7f0; }
        .mm-btn-danger { background: #c62828; color: #fff; }
        .mm-btn-danger:hover { background: #a02020; }

        @media (max-width: 768px) {
            .settings-page { padding: 20px 15px 30px; }
            .settings-form-card { padding: 17px; border-radius: 17px; }
            .img-grid { grid-template-columns: 1fr; }
            .form-actions { flex-direction: column-reverse; }
            .btn-save { width: 100%; justify-content: center; }
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


        <div class="settings-page">

            <div class="settings-header">
                <div class="settings-title">
                    <h1>Settings</h1>
                    <p>Manage store details, currency, favicon and logo.</p>
                </div>
            </div>


            <div class="settings-form-card">

                <div class="form-card-header">
                    <div class="form-card-icon">
                        <i class="bi bi-gear"></i>
                    </div>
                    <div>
                        <h3>Store Configuration</h3>
                        <span>Update your store's contact details and branding.</span>
                    </div>
                </div>

                <div id="formLoading" class="form-loading">
                    <div class="table-spinner"></div>
                    <div>Loading settings...</div>
                </div>

                <form id="settingsForm" class="settings-form" method="POST" novalidate style="display:none;">

                    <input type="hidden" id="remove_favicon" value="0">
                    <input type="hidden" id="remove_logo" value="0">

                    <div class="row g-3">

                        <div class="col-12 col-md-6">
                            <label>Username <span class="required">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="bi bi-person"></i>
                                <input type="text"
                                       id="username"
                                       class="settings-input"
                                       placeholder="Eg: Mrs Mill@"
                                       maxlength="150"
                                       required>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label>Mobile Number</label>
                            <div class="input-icon-wrap">
                                <i class="bi bi-telephone"></i>
                                <input type="text"
                                       id="mobile_number"
                                       class="settings-input"
                                       placeholder="Eg: +91 98765 43210"
                                       maxlength="30">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label>Email Address</label>
                            <div class="input-icon-wrap">
                                <i class="bi bi-envelope"></i>
                                <input type="email"
                                       id="email_address"
                                       class="settings-input"
                                       placeholder="Eg: hello@mrsmill.com"
                                       maxlength="190">
                            </div>
                        </div>

                        <div class="col-12 col-md-3">
                            <label>Currency Symbol</label>
                            <input type="text"
                                   id="currency"
                                   class="settings-input"
                                   placeholder="Eg: ₹"
                                   maxlength="10">
                        </div>

                        <div class="col-12 col-md-3">
                            <label>Currency Code</label>
                            <input type="text"
                                   id="currency_code"
                                   class="settings-input"
                                   placeholder="Eg: INR"
                                   maxlength="10">
                        </div>

                    </div>


                    <!-- BRANDING -->

                    <div class="section-divider">
                        <i class="bi bi-palette"></i>
                        <div>
                            <h4>Branding</h4>
                            <span>Favicon is used in the browser tab, logo is shown in the app.</span>
                        </div>
                    </div>


                    <div class="img-grid">

                        <!-- FAVICON -->

                        <div class="img-block">
                            <div class="img-block-title">
                                <i class="bi bi-star"></i>
                                Favicon
                            </div>
                            <div class="img-block-sub">
                                Square icon, 32×32 or 64×64 (ICO, PNG, SVG)
                            </div>

                            <div class="img-upload-zone" id="faviconZone">
                                <input type="file" id="favicon_image"
                                       accept=".ico,.png,.svg,image/x-icon,image/png,image/svg+xml,image/jpeg,image/webp">

                                <div class="img-preview-wrap" id="faviconWrap" style="display:none;">
                                    <img src="" alt="" class="img-preview ico" id="faviconPreview">

                                    <div class="img-preview-actions">
                                        <button type="button" class="img-action-btn replace" id="faviconReplaceBtn">
                                            <i class="bi bi-arrow-repeat"></i> Replace
                                        </button>
                                        <button type="button" class="img-action-btn remove" id="faviconRemoveBtn">
                                            <i class="bi bi-trash"></i> Remove
                                        </button>
                                    </div>
                                </div>

                                <div class="img-placeholder" id="faviconPlaceholder">
                                    <i class="bi bi-star"></i>
                                    <strong>Click or drag favicon</strong>
                                    <small>ICO, PNG, SVG — max 3 MB</small>
                                </div>
                            </div>

                            <div class="new-image-note" id="faviconNewNote">
                                <i class="bi bi-info-circle"></i>
                                On save, the old favicon will be replaced.
                            </div>

                            <div class="removed-image-note" id="faviconRemovedNote">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span>Favicon will be removed on save.</span>
                            </div>
                        </div>


                        <!-- LOGO -->

                        <div class="img-block">
                            <div class="img-block-title">
                                <i class="bi bi-image"></i>
                                Logo
                            </div>
                            <div class="img-block-sub">
                                Horizontal or square logo (PNG, SVG, JPG)
                            </div>

                            <div class="img-upload-zone" id="logoZone">
                                <input type="file" id="logo_image"
                                       accept=".png,.svg,.jpg,.jpeg,.webp,image/png,image/svg+xml,image/jpeg,image/webp">

                                <div class="img-preview-wrap" id="logoWrap" style="display:none;">
                                    <img src="" alt="" class="img-preview" id="logoPreview">

                                    <div class="img-preview-actions">
                                        <button type="button" class="img-action-btn replace" id="logoReplaceBtn">
                                            <i class="bi bi-arrow-repeat"></i> Replace
                                        </button>
                                        <button type="button" class="img-action-btn remove" id="logoRemoveBtn">
                                            <i class="bi bi-trash"></i> Remove
                                        </button>
                                    </div>
                                </div>

                                <div class="img-placeholder" id="logoPlaceholder">
                                    <i class="bi bi-image"></i>
                                    <strong>Click or drag logo</strong>
                                    <small>PNG, SVG, JPG — max 3 MB</small>
                                </div>
                            </div>

                            <div class="new-image-note" id="logoNewNote">
                                <i class="bi bi-info-circle"></i>
                                On save, the old logo will be replaced.
                            </div>

                            <div class="removed-image-note" id="logoRemovedNote">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span>Logo will be removed on save.</span>
                            </div>
                        </div>

                    </div>


                    <!-- ACTIONS -->

                    <div class="form-actions">
                        <button type="submit" class="btn-save" id="saveBtn">
                            <i class="bi bi-check-lg"></i>
                            <span id="saveBtnText">Save Settings</span>
                        </button>
                    </div>

                </form>

            </div>

        </div>

    </main>


    <!-- CONFIRM REMOVE FAVICON -->

    <div class="mm-modal-overlay" id="confirmFaviconOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog" aria-modal="true">
            <div class="mm-modal-icon danger"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <h3 class="mm-modal-title">Remove Favicon?</h3>
            <p class="mm-modal-text">This will delete the current favicon when you save.</p>
            <div class="mm-modal-actions">
                <button type="button" class="mm-btn mm-btn-ghost" id="confirmFaviconCancel">Cancel</button>
                <button type="button" class="mm-btn mm-btn-danger" id="confirmFaviconRemove">
                    <i class="bi bi-trash"></i> Remove
                </button>
            </div>
        </div>
    </div>


    <!-- CONFIRM REMOVE LOGO -->

    <div class="mm-modal-overlay" id="confirmLogoOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog" aria-modal="true">
            <div class="mm-modal-icon danger"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <h3 class="mm-modal-title">Remove Logo?</h3>
            <p class="mm-modal-text">This will delete the current logo when you save.</p>
            <div class="mm-modal-actions">
                <button type="button" class="mm-btn mm-btn-ghost" id="confirmLogoCancel">Cancel</button>
                <button type="button" class="mm-btn mm-btn-danger" id="confirmLogoRemove">
                    <i class="bi bi-trash"></i> Remove
                </button>
            </div>
        </div>
    </div>


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
            <h3 class="mm-modal-title">Settings Saved!</h3>
            <p class="mm-modal-text" id="successText">Your settings have been updated successfully.</p>
            <div class="mm-modal-actions">
                <button type="button" class="mm-btn mm-btn-primary" id="successOkBtn">
                    <i class="bi bi-check2"></i> Done
                </button>
            </div>
        </div>
    </div>


    <script>
        window.ADMIN_URL = "<?= ADMIN_URL; ?>";
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL; ?>js/main.js"></script>
    <script src="<?= ADMIN_URL; ?>js/settings.js"></script>

</body>
</html>