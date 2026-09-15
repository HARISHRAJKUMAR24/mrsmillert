<?php
require_once './config/config.php';
require_once './config/function.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include './includes/head.php'; ?>

    <style>
        .ps-page { padding: 30px 32px 40px; }

        .ps-header {
            display: flex; align-items: center; justify-content: space-between;
            gap: 20px; margin-bottom: 25px;
        }

        .ps-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px; font-weight: 700;
            color: #302923; margin: 0 0 5px;
        }

        .ps-title p { margin: 0; color: #817a71; font-size: 13px; }

        .ps-form-card {
            background: #fff; border: 1px solid #eee7dc;
            border-radius: 20px; padding: 25px; max-width: 780px;
        }

        .form-card-header {
            display: flex; align-items: center; gap: 12px; margin-bottom: 22px;
        }

        .form-card-icon {
            width: 42px; height: 42px; border-radius: 12px;
            background: #e7f0fb; color: #1565c0;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
        }

        .form-card-header h3 { margin: 0; font-size: 16px; font-weight: 700; }
        .form-card-header span { display: block; margin-top: 3px; color: #817a71; font-size: 10px; }

        .section-label {
            font-size: 10px; font-weight: 800;
            text-transform: uppercase; letter-spacing: 1px;
            color: #b51f2c;
            margin: 22px 0 14px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #f0ebe4;
        }

        .section-label:first-of-type { margin-top: 0; }

        .ps-form label {
            display: block; font-size: 11px; font-weight: 700;
            color: #4e4841; margin-bottom: 7px;
        }

        .required { color: #b51f2c; }

        .ps-input {
            width: 100%; height: 45px;
            border: 1px solid #e8e1d8; background: #fffdf9;
            border-radius: 11px; padding: 11px 13px;
            font-family: "DM Sans", sans-serif; font-size: 12px;
            color: #292521; outline: none; transition: .2s ease;
        }

        .ps-input:focus {
            border-color: #d98a91; background: #fff;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .input-icon-wrap { position: relative; }
        .input-icon-wrap > i {
            position: absolute; left: 13px; top: 50%;
            transform: translateY(-50%); color: #aaa198;
            font-size: 15px; pointer-events: none;
        }
        .input-icon-wrap .ps-input { padding-left: 40px; }

        .input-with-eye { position: relative; }

        .eye-btn {
            position: absolute; right: 12px; top: 50%;
            transform: translateY(-50%);
            border: 0; background: transparent;
            color: #aaa198; cursor: pointer;
            font-size: 16px; padding: 4px 6px;
        }

        .eye-btn:hover { color: #b51f2c; }

        .field-help {
            font-size: 9px;
            color: #948c82;
            margin-top: 6px;
            line-height: 1.5;
        }

        .info-banner {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 12px 14px;
            background: #eef4fd;
            border: 1px solid #cfe0f5;
            border-radius: 11px;
            color: #1d5cb8;
            font-size: 11px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .info-banner i { color: #1565c0; margin-top: 1px; }

        .info-banner a {
            color: #1565c0;
            font-weight: 700;
            text-decoration: underline;
        }

        /* ---------- TAX TOGGLE ---------- */

        .tax-toggle-row {
            display: flex; align-items: center; justify-content: space-between;
            gap: 14px;
            padding: 14px 16px;
            background: #fffdf9;
            border: 1px solid #e8e1d8;
            border-radius: 12px;
            margin-bottom: 18px;
        }

        .tax-toggle-info { display: flex; align-items: center; gap: 12px; }

        .tax-toggle-icon {
            width: 38px; height: 38px; border-radius: 10px;
            background: #f3eefe; color: #7c3aed;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
            transition: .25s;
        }

        .tax-toggle-row.is-enabled .tax-toggle-icon {
            background: #e8f6ea; color: #2e7d32;
        }

        .tax-toggle-info h4 { margin: 0; font-size: 13px; font-weight: 700; color: #302923; }
        .tax-toggle-info p  { margin: 2px 0 0; font-size: 10px; color: #948c82; }

        .mm-switch { position: relative; display: inline-block; width: 46px; height: 26px; flex-shrink: 0; }
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

        /* ---------- TAX TYPE OPTIONS ---------- */

        .tax-options { display: flex; gap: 12px; margin-bottom: 18px; }

        .tax-option {
            flex: 1; position: relative; cursor: pointer;
        }

        .tax-option input { position: absolute; opacity: 0; pointer-events: none; }

        .tax-option-box {
            border: 1.5px solid #e8e1d8;
            background: #fffdf9;
            border-radius: 12px;
            padding: 14px 14px 12px;
            transition: .2s ease;
            height: 100%;
        }

        .tax-option-box .tax-opt-head {
            display: flex; align-items: center; gap: 8px;
            font-size: 12px; font-weight: 700; color: #302923;
            margin-bottom: 5px;
        }

        .tax-option-box .tax-opt-head i { font-size: 15px; color: #b51f2c; }

        .tax-option-box p {
            margin: 0; font-size: 10px; color: #948c82; line-height: 1.5;
        }

        .tax-option input:checked + .tax-option-box {
            border-color: #b51f2c;
            background: #fff7f8;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .tax-option input:checked + .tax-option-box .tax-opt-head i { color: #b51f2c; }

        /* ---------- DISABLED STATE ---------- */

        .tax-fields.is-disabled {
            opacity: .5;
            pointer-events: none;
            filter: grayscale(.4);
            transition: .25s;
        }

        /* ---------- ACTIONS ---------- */

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
            border-top-color: #1565c0;
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

        @media (max-width: 768px) {
            .ps-page { padding: 20px 15px 30px; }
            .ps-form-card { padding: 17px; border-radius: 17px; }
            .form-actions { flex-direction: column-reverse; }
            .btn-save { width: 100%; justify-content: center; }
            .tax-options { flex-direction: column; }
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


        <div class="ps-page">

            <div class="ps-header">
                <div class="ps-title">
                    <h1>Payment Settings</h1>
                    <p>Configure Razorpay keys, UPI ID and Tax settings for online payments.</p>
                </div>
            </div>


            <div class="ps-form-card">

                <div class="form-card-header">
                    <div class="form-card-icon">
                        <i class="bi bi-credit-card-2-front"></i>
                    </div>
                    <div>
                        <h3>Payment & Tax Configuration</h3>
                        <span>All fields are required to enable online payments.</span>
                    </div>
                </div>

                <div class="info-banner">
                    <i class="bi bi-info-circle-fill"></i>
                    <div>
                        Get your API keys from the
                        <a href="https://dashboard.razorpay.com/app/keys" target="_blank" rel="noopener">
                            Razorpay Dashboard → Settings → API Keys
                        </a>.
                        Use <strong>Test Mode</strong> keys for testing and <strong>Live Mode</strong> keys for production.
                    </div>
                </div>

                <div id="formLoading" class="form-loading">
                    <div class="table-spinner"></div>
                    <div>Loading payment settings...</div>
                </div>

                <form id="paymentForm" class="ps-form" method="POST" novalidate style="display:none;">

                    <!-- ==================== RAZORPAY ==================== -->

                    <div class="section-label">Razorpay</div>

                    <div style="margin-bottom:18px;">
                        <label>Razorpay Key ID <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="bi bi-key"></i>
                            <input type="text"
                                   id="razorpay_key_id"
                                   class="ps-input"
                                   placeholder="rzp_test_xxxxxxxxxxxxx"
                                   maxlength="255"
                                   autocomplete="off"
                                   spellcheck="false"
                                   required>
                        </div>
                        <div class="field-help">
                            Your Razorpay API Key ID from the dashboard. Starts with <strong>rzp_</strong>.
                        </div>
                    </div>

                    <div style="margin-bottom:18px;">
                        <label>Razorpay Key Secret <span class="required">*</span></label>
                        <div class="input-with-eye">
                            <input type="password"
                                   id="razorpay_key_secret"
                                   class="ps-input"
                                   placeholder="Your Key Secret"
                                   maxlength="255"
                                   autocomplete="new-password"
                                   spellcheck="false"
                                   required>
                            <button type="button"
                                    class="eye-btn"
                                    id="toggleSecret"
                                    aria-label="Show secret">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                        <div class="field-help">
                            Your Razorpay API Key Secret from the dashboard. Keep this private.
                        </div>
                    </div>

                    <!-- ==================== UPI ==================== -->

                    <div class="section-label">UPI</div>

                    <div style="margin-bottom:18px;">
                        <label>UPI ID <span class="required">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="bi bi-at"></i>
                            <input type="text"
                                   id="upi_id"
                                   class="ps-input"
                                   placeholder="yourname@upi"
                                   maxlength="255"
                                   autocomplete="off"
                                   spellcheck="false"
                                   required>
                        </div>
                        <div class="field-help">
                            Your UPI ID / VPA. Example: <strong>mrsmill@okhdfcbank</strong>
                        </div>
                    </div>

                    <!-- ==================== TAX ==================== -->

                    <div class="section-label">Tax Settings</div>

                    <!-- Toggle: Enable / Disable Tax -->
                    <div class="tax-toggle-row" id="taxToggleRow">
                        <div class="tax-toggle-info">
                            <div class="tax-toggle-icon" id="taxToggleIcon">
                                <i class="bi bi-receipt"></i>
                            </div>
                            <div>
                                <h4 id="taxToggleTitle">Tax Disabled</h4>
                                <p id="taxToggleDesc">Tax will not be applied to any order.</p>
                            </div>
                        </div>
                        <label class="mm-switch" for="tax_status">
                            <input type="checkbox" id="tax_status" name="tax_status">
                            <span class="mm-switch-slider"></span>
                        </label>
                    </div>

                    <!-- Tax Type + Rate (disabled when toggle is off) -->
                    <div class="tax-fields is-disabled" id="taxFields">

                        <div style="margin-bottom:18px;">
                            <label>Tax Type <span class="required">*</span></label>
                            <div class="tax-options">

                                <label class="tax-option" for="tax_type_exclusive">
                                    <input type="radio"
                                           id="tax_type_exclusive"
                                           name="tax_type"
                                           value="exclusive">
                                    <div class="tax-option-box">
                                        <div class="tax-opt-head">
                                            <i class="bi bi-plus-circle-fill"></i>
                                            Exclusive
                                        </div>
                                        <p>Tax is added on top of the product price at checkout.</p>
                                    </div>
                                </label>

                                <label class="tax-option" for="tax_type_inclusive">
                                    <input type="radio"
                                           id="tax_type_inclusive"
                                           name="tax_type"
                                           value="inclusive">
                                    <div class="tax-option-box">
                                        <div class="tax-opt-head">
                                            <i class="bi bi-check-circle-fill"></i>
                                            Inclusive
                                        </div>
                                        <p>Tax is already included inside the product price.</p>
                                    </div>
                                </label>

                            </div>
                            <div class="field-help">
                                Choose how tax should be calculated for your products.
                            </div>
                        </div>

                        <div style="margin-bottom:18px;">
                            <label>Tax Rate (%) <span class="required">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="bi bi-percent"></i>
                                <input type="number"
                                       id="tax_rate"
                                       class="ps-input"
                                       placeholder="5"
                                       min="0"
                                       max="100"
                                       step="0.01"
                                       inputmode="decimal">
                            </div>
                            <div class="field-help">
                                Enter a value between <strong>0</strong> and <strong>100</strong>. Example: <strong>5</strong>, <strong>12</strong>, <strong>18</strong>.
                            </div>
                        </div>

                    </div>

                    <!-- ==================== SINGLE SAVE BUTTON ==================== -->

                    <div class="form-actions">
                        <button type="submit" class="btn-save" id="saveBtn">
                            <i class="bi bi-check-lg"></i>
                            <span id="saveBtnText">Save Payment Settings</span>
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
            <h3 class="mm-modal-title">Settings Saved!</h3>
            <p class="mm-modal-text" id="successText">Your payment settings have been saved successfully.</p>
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
    <script src="<?= ADMIN_URL; ?>js/payment-settings.js"></script>

</body>
</html>