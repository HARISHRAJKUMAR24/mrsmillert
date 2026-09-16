<?php
require_once './config/config.php';
require_once './config/function.php';

// require_once __DIR__ . '/../auth.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

    <style>
        /* =====================================================
           APARTMENT ADD PAGE
        ===================================================== */

        .apartment-page {
            padding: 30px 32px 40px;
        }

        .apartment-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .apartment-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 5px;
        }

        .apartment-title p {
            margin: 0;
            color: #817a71;
            font-size: 13px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #e4ddd3;
            background: #fff;
            color: #6f675f;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            transition: .2s;
        }

        .back-btn:hover {
            background: #faf7f0;
            color: #302923;
        }

        .apartment-form-card {
            background: #fff;
            border: 1px solid #eee7dc;
            border-radius: 20px;
            padding: 25px;
            max-width: 1000px;
        }

        .form-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
        }

        .form-card-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: #fbe8e9;
            color: #b51f2c;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .form-card-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
        }

        .form-card-header span {
            display: block;
            margin-top: 3px;
            color: #817a71;
            font-size: 10px;
        }

        .apartment-form label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #4e4841;
            margin-bottom: 7px;
        }

        .required {
            color: #b51f2c;
        }

        .apartment-input,
        .apartment-textarea {
            width: 100%;
            border: 1px solid #e8e1d8;
            background: #fffdf9;
            border-radius: 11px;
            padding: 11px 13px;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            color: #292521;
            outline: none;
            transition: .2s ease;
        }

        .apartment-input {
            height: 45px;
        }

        .apartment-textarea {
            min-height: 95px;
            resize: vertical;
        }

        .apartment-input:focus,
        .apartment-textarea:focus {
            border-color: #d98a91;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .input-icon-wrap {
            position: relative;
        }

        .input-icon-wrap>i {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa198;
            font-size: 15px;
            pointer-events: none;
        }

        .input-icon-wrap .apartment-input {
            padding-left: 40px;
        }

        .field-help {
            font-size: 9px;
            color: #948c82;
            margin-top: 6px;
            line-height: 1.5;
        }

        .alert-box {
            display: none;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 12px;
        }

        .alert-box.show {
            display: block;
        }

        .alert-box.error {
            background: #fff1f1;
            border: 1px solid #ffd2d2;
            color: #d71920;
        }

        .btn-spinner {
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, .4);
            border-top-color: #fff;
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
           DIVISIONS SECTION
        ===================================================== */

        .divisions-section {
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid #f0ebe4;
        }

        .divisions-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        /* ---- Inline title + asterisk, subtitle below ---- */
        .divisions-header-title {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .divisions-header-title>i {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: #fbe8e9;
            color: #b51f2c;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .divisions-header-title .title-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .divisions-header-title h4 {
            margin: 0;
            font-size: 13px;
            font-weight: 800;
            color: #302923;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            line-height: 1.2;
        }

        .divisions-header-title h4 .required {
            color: #b51f2c;
            font-weight: 800;
            font-size: 13px;
            line-height: 1;
        }

        .divisions-header-title span.subtitle {
            display: block;
            font-size: 10px;
            color: #817a71;
            margin: 0;
            line-height: 1.4;
        }

        .add-division-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px dashed #d98a91;
            background: #fff5f5;
            color: #b51f2c;
            padding: 9px 14px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: .2s ease;
        }

        .add-division-btn:hover {
            background: #fbe8e9;
            border-style: solid;
        }

        .division-rows {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .division-row {
            display: grid;
            grid-template-columns: 1fr 200px 42px;
            gap: 10px;
            align-items: center;
            background: #fffdf9;
            border: 1px solid #f0ebe4;
            border-radius: 12px;
            padding: 12px;
            animation: fadeSlide .25s ease;
        }

        @keyframes fadeSlide {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .division-field {
            position: relative;
        }

        .division-field>i {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa198;
            font-size: 14px;
            pointer-events: none;
        }

        .division-field input {
            width: 100%;
            height: 42px;
            border: 1px solid #e8e1d8;
            background: #fff;
            border-radius: 10px;
            padding: 0 12px 0 38px;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            color: #292521;
            outline: none;
            transition: .2s ease;
        }

        .division-field.charge input {
            padding-left: 32px;
        }

        .division-field input:focus {
            border-color: #d98a91;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .division-field.charge .rupee {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #817a71;
            font-size: 13px;
            font-weight: 700;
            pointer-events: none;
        }

        .remove-division {
            width: 42px;
            height: 42px;
            border: 1px solid #f0d6d8;
            background: #fff;
            color: #b51f2c;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: .2s ease;
        }

        .remove-division:hover {
            background: #fde6e6;
            border-color: #f1c8cc;
        }

        .remove-division:disabled {
            opacity: .35;
            cursor: not-allowed;
        }

        .divisions-empty {
            text-align: center;
            padding: 22px;
            border: 1px dashed #e4ddd3;
            border-radius: 12px;
            color: #948c82;
            font-size: 11px;
            background: #fdfaf4;
        }

        .divisions-empty i {
            font-size: 22px;
            color: #d5cbbd;
            display: block;
            margin-bottom: 8px;
        }

        @media (max-width: 620px) {
            .division-row {
                grid-template-columns: 1fr;
            }

            .remove-division {
                width: 100%;
            }
        }


        /* =====================================================
           FORM ACTIONS
        ===================================================== */

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid #f0ebe4;
        }

        .btn-cancel {
            border: 1px solid #e4ddd3;
            background: #fff;
            color: #6f675f;
            border-radius: 10px;
            padding: 10px 17px;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
        }

        .btn-save {
            border: none;
            background: #b51f2c;
            color: white;
            border-radius: 10px;
            padding: 10px 19px;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            cursor: pointer;
            transition: .2s;
        }

        .btn-save:hover {
            background: #8e1722;
        }

        .btn-save:disabled {
            opacity: .7;
            cursor: not-allowed;
        }


        /* =====================================================
           SUCCESS POPUP
        ===================================================== */

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
            transition: opacity .2s ease, visibility .2s ease;
        }

        .mm-modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .mm-modal {
            background: #fff;
            border-radius: 18px;
            padding: 30px 26px 24px;
            max-width: 420px;
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
            width: 66px;
            height: 66px;
            border-radius: 50%;
            background: #e8f6ea;
            color: #2e7d32;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin: 0 auto 16px;
            animation: popIn .35s cubic-bezier(.2, .9, .3, 1.4);
        }

        @keyframes popIn {
            0% {
                transform: scale(.5);
                opacity: 0;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .mm-modal-title {
            margin: 0 0 8px;
            font-size: 18px;
            font-weight: 800;
            color: #302923;
        }

        .mm-modal-text {
            margin: 0 0 20px;
            font-size: 12px;
            color: #756d65;
            line-height: 1.6;
        }

        .mm-modal-code {
            display: inline-block;
            background: #faf7f0;
            border: 1px dashed #d8c9b8;
            color: #6f5a3f;
            font-size: 11px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            letter-spacing: 1px;
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
            text-decoration: none;
            transition: .2s ease;
        }

        .mm-btn-primary {
            background: #b51f2c;
            color: #fff;
            box-shadow: 0 8px 20px rgba(181, 31, 44, .22);
        }

        .mm-btn-primary:hover {
            background: #8e1722;
            color: #fff;
        }

        .mm-btn-ghost {
            background: #fff;
            border: 1px solid #e4ddd3;
            color: #6f675f;
        }

        .mm-btn-ghost:hover {
            background: #faf7f0;
            color: #302923;
        }


        @media (max-width: 768px) {
            .apartment-page {
                padding: 20px 15px 30px;
            }

            .apartment-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .apartment-form-card {
                padding: 17px;
                border-radius: 17px;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn-cancel,
            .btn-save {
                width: 100%;
                justify-content: center;
            }
        }
    </style>

</head>

<body>

    <?php include './templates/sidebar.php'; ?>

    <main class="main">

        <header class="topbar">

            <button class="mobile-menu"
                onclick="toggleSidebar()"
                aria-label="Open menu">
                <i class="bi bi-list"></i>
            </button>

            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text"
                    placeholder="Search orders, products...">
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


        <div class="apartment-page">

            <!-- PAGE HEADER -->

            <div class="apartment-header">

                <div class="apartment-title">
                    <h1>Add Apartment</h1>
                    <p>Create an apartment and add its divisions with delivery charges.</p>
                </div>



            </div>


            <!-- FORM CARD -->

            <div class="apartment-form-card">

                <div class="form-card-header">
                    <div class="form-card-icon">
                        <i class="bi bi-building-add"></i>
                    </div>
                    <div>
                        <h3>Apartment Details</h3>
                        <span>Add apartment info, then add each division with its delivery charge.</span>
                    </div>
                </div>

                <div id="formAlert" class="alert-box"></div>

                <form id="apartmentForm"
                    class="apartment-form"
                    method="POST"
                    novalidate>

                    <div class="row g-3">

                        <!-- NAME -->
                        <div class="col-12 col-md-6">
                            <label>
                                Apartment Name <span class="required">*</span>
                            </label>
                            <div class="input-icon-wrap">
                                <i class="bi bi-building"></i>
                                <input type="text"
                                    name="apartment_name"
                                    id="apartment_name"
                                    class="apartment-input"
                                    placeholder="Eg: Green Valley Apartments"
                                    required>
                            </div>
                        </div>

                        <!-- STATUS -->
                        <div class="col-12 col-md-6">
                            <label>Status</label>
                            <select name="status"
                                id="status"
                                class="apartment-input">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>

                        <!-- ADDRESS -->
                        <div class="col-12">
                            <label>
                                Apartment Address <span class="required">*</span>
                            </label>
                            <div class="input-icon-wrap">
                                <textarea name="apartment_address"
                                    id="apartment_address"
                                    class="apartment-textarea"
                                    placeholder="Enter complete apartment address..."
                                    required></textarea>
                            </div>
                        </div>

                    </div>


                    <!-- =================================================
                         DIVISIONS (click to add)
                    ================================================== -->

                    <div class="divisions-section">

                        <div class="divisions-header">

                            <div class="divisions-header-title">
                                <i class="bi bi-grid-3x3-gap"></i>
                                <div class="title-text">
                                    <h4>Apartment Divisions <span class="required">*</span></h4>
                                    <span class="subtitle">Each division has its own delivery charge.</span>
                                </div>
                            </div>

                            <button type="button"
                                class="add-division-btn"
                                id="addDivisionBtn">
                                <i class="bi bi-plus-lg"></i>
                                Add Division
                            </button>

                        </div>

                        <div class="division-rows" id="divisionRows">
                            <!-- filled by JS -->
                        </div>

                        <div class="divisions-empty" id="divisionsEmpty">
                            <i class="bi bi-grid"></i>
                            No divisions added yet. Click <strong>Add Division</strong> to begin.
                        </div>

                    </div>


                    <!-- BUTTONS -->

                    <div class="form-actions">

                        <a href="apartment.php" class="btn-cancel">Cancel</a>

                        <button type="submit"
                            class="btn-save"
                            id="saveBtn">
                            <i class="bi bi-check-lg"></i>
                            <span id="saveBtnText">Save Apartment</span>
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </main>


    <!-- =========================================================
         SUCCESS POPUP
    ========================================================== -->

    <div class="mm-modal-overlay" id="successOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog" aria-modal="true" aria-labelledby="successTitle">

            <div class="mm-modal-icon">
                <i class="bi bi-check-lg"></i>
            </div>

            <h3 class="mm-modal-title" id="successTitle">
                Apartment Added!
            </h3>

            <p class="mm-modal-text" id="successText">
                Your apartment has been saved successfully.
            </p>

            <div class="mm-modal-code" id="successCode" style="display:none;"></div>

            <div class="mm-modal-actions">

                <a href="add-apartment.php" class="mm-btn mm-btn-ghost">
                    <i class="bi bi-plus-lg"></i>
                    Add Another
                </a>

                <a href="apartment.php" class="mm-btn mm-btn-primary">
                    <i class="bi bi-list-ul"></i>
                    View List
                </a>

            </div>

        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL; ?>js/main.js"></script>
    <script src="<?= ADMIN_URL; ?>js/add-apartment.js"></script>

</body>

</html>