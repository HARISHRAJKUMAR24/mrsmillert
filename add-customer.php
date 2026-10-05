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
        /* =====================================================
           CUSTOMER ADD PAGE
        ===================================================== */

        .customer-page {
            padding: 30px 32px 40px;
        }

        .customer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .customer-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 5px;
        }

        .customer-title p {
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

        .customer-form-card {
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

        .customer-form label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #4e4841;
            margin-bottom: 7px;
        }

        .required {
            color: #b51f2c;
        }

        .customer-input,
        .customer-select,
        .customer-textarea {
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

        .customer-input,
        .customer-select {
            height: 45px;
        }

        .customer-select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding-right: 40px;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23aaa198' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            cursor: pointer;
        }

        .customer-input:focus,
        .customer-select:focus,
        .customer-textarea:focus {
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

        .input-icon-wrap .customer-input,
        .input-icon-wrap .customer-select {
            padding-left: 40px;
        }

        .field-help {
            font-size: 9px;
            color: #948c82;
            margin-top: 6px;
            line-height: 1.5;
        }

        .field-help.success {
            color: #1b5e20;
        }

        .field-help.error {
            color: #b51f2c;
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
           SEARCHABLE DROPDOWN
        ===================================================== */

        .sd-wrap {
            position: relative;
        }

        .sd-toggle {
            width: 100%;
            height: 45px;
            border: 1px solid #e8e1d8;
            background: #fffdf9;
            border-radius: 11px;
            padding: 0 40px 0 40px;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
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

        .sd-toggle:hover {
            border-color: #d98a91;
        }

        .sd-wrap.open .sd-toggle {
            border-color: #d98a91;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .sd-toggle>i.lead {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa198;
            font-size: 15px;
            pointer-events: none;
        }

        .sd-toggle>i.caret {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa198;
            font-size: 12px;
            pointer-events: none;
            transition: transform .2s;
        }

        .sd-wrap.open .sd-toggle>i.caret {
            transform: translateY(-50%) rotate(180deg);
        }

        .sd-toggle .placeholder {
            color: #b8afa3;
            font-weight: 500;
        }

        .sd-toggle.has-value {
            color: #292521;
            font-weight: 700;
        }

        .sd-toggle:disabled {
            opacity: .7;
            background: #f7f2ec;
            cursor: not-allowed;
        }

        .sd-menu {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #e8e1d8;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(48, 41, 35, .14);
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
            border: 1px solid #e8e1d8;
            background: #fff;
            border-radius: 9px;
            padding: 0 12px 0 34px;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            color: #292521;
            outline: none;
        }

        .sd-search input:focus {
            border-color: #d98a91;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .sd-search>i {
            position: absolute;
            left: 22px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa198;
            font-size: 13px;
            pointer-events: none;
        }

        .sd-list {
            max-height: 260px;
            overflow-y: auto;
            padding: 6px;
        }

        .sd-list::-webkit-scrollbar {
            width: 6px;
        }

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
            font-size: 12px;
            color: #4e4841;
            cursor: pointer;
            transition: .12s ease;
        }

        .sd-option:hover {
            background: #fbe8e9;
            color: #b51f2c;
        }

        .sd-option.selected {
            background: #b51f2c;
            color: #fff;
        }

        .sd-option.selected i {
            color: #fff;
        }

        .sd-option.selected .meta {
            color: rgba(255, 255, 255, .75);
        }

        .sd-option>i {
            color: #aaa198;
            font-size: 14px;
            flex-shrink: 0;
        }

        .sd-option .name {
            flex: 1;
            min-width: 0;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sd-option .meta {
            font-size: 10px;
            color: #948c82;
            font-weight: 600;
            white-space: nowrap;
        }

        .sd-empty {
            padding: 18px;
            text-align: center;
            font-size: 11px;
            color: #948c82;
        }


        /* =====================================================
           WALLET SECTION
        ===================================================== */

        .wallet-section {
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid #f0ebe4;
        }

        .wallet-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .wallet-header-title {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .wallet-header-title>i {
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

        .wallet-header-title .title-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .wallet-header-title h4 {
            margin: 0;
            font-size: 13px;
            font-weight: 800;
            color: #302923;
            line-height: 1.2;
        }

        .wallet-header-title span.subtitle {
            display: block;
            font-size: 10px;
            color: #817a71;
            margin: 0;
            line-height: 1.4;
        }

        .wallet-toggle-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fffdf9;
            border: 1px dashed #d8c9b8;
            border-radius: 10px;
            padding: 8px 14px;
        }

        .wallet-toggle {
            position: relative;
            width: 40px;
            height: 22px;
            background: #d5cbbd;
            border-radius: 999px;
            cursor: pointer;
            transition: .2s ease;
            flex-shrink: 0;
        }

        .wallet-toggle::after {
            content: "";
            position: absolute;
            top: 3px;
            left: 3px;
            width: 16px;
            height: 16px;
            background: #fff;
            border-radius: 50%;
            box-shadow: 0 2px 5px rgba(0, 0, 0, .15);
            transition: .2s ease;
        }

        .wallet-toggle.on {
            background: #b51f2c;
        }

        .wallet-toggle.on::after {
            left: 21px;
        }

        .wallet-toggle-label {
            font-size: 11px;
            font-weight: 700;
            color: #4e4841;
        }

        .wallet-fields {
            display: none;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 14px;
            padding: 14px;
            background: #fdfaf4;
            border: 1px solid #f0ebe4;
            border-radius: 12px;
            animation: fadeSlide .25s ease;
        }

        .wallet-fields.show {
            display: grid;
        }

        @media (max-width: 620px) {
            .wallet-fields {
                grid-template-columns: 1fr;
            }
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

        .wallet-preview {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background: #fff5f5;
            border: 1px solid #f1c8cc;
            border-radius: 10px;
            margin-top: 12px;
        }

        .wallet-preview i {
            color: #b51f2c;
            font-size: 16px;
        }

        .wallet-preview .label {
            font-size: 10px;
            color: #817a71;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .wallet-preview .value {
            font-family: "Playfair Display", serif;
            font-size: 18px;
            font-weight: 700;
            color: #b51f2c;
            margin-left: auto;
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
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-cancel:hover {
            background: #faf7f0;
            color: #302923;
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
           POPUP (success + error)
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

        .mm-modal-icon-error {
            background: #fdecec;
            color: #b51f2c;
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

        .mm-btn-danger {
            background: #b51f2c;
            color: #fff;
            box-shadow: 0 8px 20px rgba(181, 31, 44, .22);
        }

        .mm-btn-danger:hover {
            background: #8e1722;
            color: #fff;
        }


        @media (max-width: 768px) {
            .customer-page {
                padding: 20px 15px 30px;
            }

            .customer-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .customer-form-card {
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
                    <div class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)) ?></div>
                    <div>
                        <div class="admin-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'User') ?></div>
                        <div class="admin-role"><?= $isAdmin ? 'Admin' : 'Staff' ?></div>
                    </div>
                </div>
            </div>

        </header>


        <div class="customer-page">

            <!-- PAGE HEADER -->

            <div class="customer-header">

                <div class="customer-title">
                    <h1>Add Customer</h1>
                    <p>Create a customer account with login details and optional wallet balance.</p>
                </div>

                

            </div>


            <!-- FORM CARD -->

            <div class="customer-form-card">

                <div class="form-card-header">
                    <div class="form-card-icon">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <div>
                        <h3>Customer Details</h3>
                        <span>Enter the customer's information below.</span>
                    </div>
                </div>

                <form id="customerForm"
                    class="customer-form"
                    method="POST"
                    novalidate>

                    <div class="row g-3">

                        <!-- FULL NAME -->
                        <div class="col-12 col-md-6">
                            <label>
                                Full Name <span class="required">*</span>
                            </label>
                            <div class="input-icon-wrap">
                                <i class="bi bi-person"></i>
                                <input type="text"
                                    name="full_name"
                                    id="full_name"
                                    class="customer-input"
                                    placeholder="Eg: Ravi Kumar"
                                    maxlength="150"
                                    required>
                            </div>
                        </div>

                        <!-- MOBILE -->
                        <div class="col-12 col-md-6">
                            <label>
                                Mobile Number <span class="required">*</span>
                            </label>
                            <div class="input-icon-wrap">
                                <i class="bi bi-telephone"></i>
                                <input type="tel"
                                    name="mobile_number"
                                    id="mobile_number"
                                    class="customer-input"
                                    placeholder="10-digit mobile"
                                    maxlength="15"
                                    inputmode="numeric"
                                    required>
                            </div>
                            <p class="field-help" id="mobileHelp">Enter a 10-digit mobile number.</p>
                        </div>

                        <!-- PASSWORD -->
                        <div class="col-12 col-md-6">
                            <label>
                                Password <span class="required">*</span>
                            </label>
                            <div class="input-icon-wrap">
                                <i class="bi bi-key"></i>
                                <input type="text"
                                    name="password"
                                    id="password"
                                    class="customer-input"
                                    placeholder="Minimum 3 characters"
                                    minlength="3"
                                    required>
                            </div>
                            <p class="field-help" id="passwordHelp">Password must be at least 3 characters.</p>
                        </div>

                        <!-- STATUS -->
                        <div class="col-12 col-md-6">
                            <label>Status</label>
                            <select name="status"
                                id="status"
                                class="customer-select">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>

                    </div>


                    <!-- =================================================
                         ADDRESS & DIVISION
                    ================================================== -->

                    <div class="divisions-section" style="margin-top:22px;">

                        <div class="divisions-header">
                            <div class="divisions-header-title">
                                <i class="bi bi-house-door"></i>
                                <div class="title-text">
                                    <h4>Address & Division</h4>
                                    <span class="subtitle">Type to search apartment and division.</span>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3" style="margin-top:4px;">

                            <!-- APARTMENT SEARCHABLE -->
                            <div class="col-12 col-md-6">
                                <label>Apartment</label>

                                <div class="sd-wrap" id="acAptDdWrap">
                                    <button type="button" class="sd-toggle" id="acAptDdToggle">
                                        <i class="bi bi-building lead"></i>
                                        <span class="placeholder" id="acAptDdLabel">— Type or select apartment —</span>
                                        <i class="bi bi-chevron-down caret"></i>
                                    </button>

                                    <div class="sd-menu">
                                        <div class="sd-search">
                                            <i class="bi bi-search"></i>
                                            <input type="text" id="acAptDdSearch"
                                                placeholder="Search apartment..." autocomplete="off">
                                        </div>
                                        <div class="sd-list" id="acAptDdList"></div>
                                    </div>
                                </div>

                                <input type="hidden" id="acApartmentId" name="apartment_id" value="">
                                <input type="hidden" id="acApartmentCode" name="apartment_code" value="">
                                <input type="hidden" id="acApartmentName" name="apartment_name" value="">
                            </div>

                            <!-- DIVISION SEARCHABLE -->
                            <div class="col-12 col-md-6">
                                <label>Division</label>

                                <div class="sd-wrap" id="acDivDdWrap">
                                    <button type="button" class="sd-toggle" id="acDivDdToggle" disabled>
                                        <i class="bi bi-grid-3x3-gap lead"></i>
                                        <span class="placeholder" id="acDivDdLabel">Select apartment first</span>
                                        <i class="bi bi-chevron-down caret"></i>
                                    </button>

                                    <div class="sd-menu">
                                        <div class="sd-search">
                                            <i class="bi bi-search"></i>
                                            <input type="text" id="acDivDdSearch"
                                                placeholder="Search division..." autocomplete="off">
                                        </div>
                                        <div class="sd-list" id="acDivDdList"></div>
                                    </div>
                                </div>

                                <input type="hidden" id="acDivision" name="division" value="">
                                <input type="hidden" id="acDivisionCharge" name="division_charge" value="0">
                                <p class="field-help" id="acDivisionHint"></p>
                            </div>

                        </div>

                    </div>


                    <!-- =================================================
                         INITIAL WALLET
                    ================================================== -->

                    <div class="wallet-section">

                        <div class="wallet-header">

                            <div class="wallet-header-title">
                                <i class="bi bi-wallet2"></i>
                                <div class="title-text">
                                    <h4>Initial Wallet Balance</h4>
                                    <span class="subtitle">Optionally add money now — customer starts at ₹0 otherwise.</span>
                                </div>
                            </div>

                            <div class="wallet-toggle-wrap">
                                <div class="wallet-toggle" id="walletToggle"></div>
                                <span class="wallet-toggle-label">Add wallet money</span>
                            </div>

                        </div>

                        <div class="wallet-fields" id="walletFields">

                            <div>
                                <label>Amount (₹)</label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-currency-rupee"></i>
                                    <input type="number"
                                        id="walletAmount"
                                        class="customer-input"
                                        min="1"
                                        step="0.01"
                                        placeholder="0.00">
                                </div>
                            </div>

                            <div>
                                <label>Note (optional)</label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-chat-left-text"></i>
                                    <input type="text"
                                        id="walletNote"
                                        class="customer-input"
                                        maxlength="250"
                                        placeholder="e.g. Cash received at counter">
                                </div>
                            </div>

                            <div class="wallet-preview" style="grid-column: 1 / -1;">
                                <i class="bi bi-wallet2"></i>
                                <span class="label">Wallet Preview</span>
                                <span class="value" id="walletPreview">₹0</span>
                            </div>

                        </div>

                    </div>


                    <!-- BUTTONS -->

                    <div class="form-actions">

                        <a href="customers.php" class="btn-cancel">
                            <i class="bi bi-x-lg"></i>
                            Cancel
                        </a>

                        <button type="submit"
                            class="btn-save"
                            id="saveBtn">
                            <i class="bi bi-check-lg"></i>
                            <span id="saveBtnText">Save Customer</span>
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
                Customer Added!
            </h3>

            <p class="mm-modal-text" id="successText">
                The customer account has been created successfully.
            </p>

            <div class="mm-modal-code" id="successCode" style="display:none;"></div>

            <div class="mm-modal-actions">

                <a href="add-customer.php" class="mm-btn mm-btn-ghost">
                    <i class="bi bi-plus-lg"></i>
                    Add Another
                </a>

                <a href="customers.php" class="mm-btn mm-btn-primary">
                    <i class="bi bi-list-ul"></i>
                    View List
                </a>

            </div>

        </div>
    </div>


    <!-- =========================================================
         ERROR POPUP (injected by JS, but keeping HTML for safety)
    ========================================================== -->

    <div class="mm-modal-overlay" id="errorOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog" aria-modal="true" aria-labelledby="errorTitle">

            <div class="mm-modal-icon mm-modal-icon-error">
                <i class="bi bi-exclamation-lg"></i>
            </div>

            <h3 class="mm-modal-title" id="errorTitle">
                Oops! Something's missing
            </h3>

            <p class="mm-modal-text" id="errorText">
                Please check the form and try again.
            </p>

            <div class="mm-modal-actions">
                <button type="button"
                    class="mm-btn mm-btn-danger"
                    id="errorOkBtn">
                    <i class="bi bi-check2"></i>
                    Got it
                </button>
            </div>

        </div>
    </div>


    <script>
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL ?>js/main.js"></script>
    <script src="<?= ADMIN_URL ?>js/add-customer.js"></script>

</body>

</html>