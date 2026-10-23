<?php
require_once './config/config.php';
require_once './config/function.php';

/* =========================================================
   ADMIN ONLY
   ========================================================= */
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    header('Location: login.php');
    exit;
}
if (($_SESSION['admin_role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$settings = getSettings($pdo);

/* Banners (banner_1..4) — read from settings if they exist, else '' */
$banners = [];
for ($i = 1; $i <= 4; $i++) {
    $k = 'banner_' . $i;
    $banners[$i] = $settings[$k] ?? '';
}

/* Offer images */
$todayOffer    = $settings['today_offer_image']    ?? '';
$tomorrowOffer = $settings['tomorrow_offer_image'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

    <style>
        /* ==============================
           PAGE / HEADER
           ============================== */
        .st-page {
            padding: 30px 32px 60px;
        }

        .st-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .st-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 5px;
        }

        .st-title p {
            margin: 0;
            color: #817a71;
            font-size: 13px;
        }

        /* ==============================
           TABS
           ============================== */
        .st-tabs {
            display: flex;
            gap: 4px;
            background: #faf7f0;
            border: 1px solid #eee7dc;
            border-radius: 14px;
            padding: 5px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .st-tab {
            border: none;
            background: transparent;
            color: #817a71;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 11.5px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: .15s ease;
            font-family: inherit;
            white-space: nowrap;
        }

        .st-tab:hover {
            color: #302923;
        }

        .st-tab.active {
            background: #b51f2c;
            color: #fff;
            box-shadow: 0 4px 12px rgba(181, 31, 44, .22);
        }

        .st-tab i {
            font-size: 13px;
        }

        /* ==============================
           PANEL / CARD
           ============================== */
        .st-panel {
            display: none;
        }

        .st-panel.active {
            display: block;
            animation: stFade .25s ease;
        }

        @keyframes stFade {
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .st-card {
            background: #fff;
            border: 1px solid #eee7dc;
            border-radius: 20px;
            padding: 25px;
            max-width: 1000px;
        }

        .st-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
        }

        .st-card-icon {
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

        .st-card-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
        }

        .st-card-header span {
            display: block;
            margin-top: 3px;
            color: #817a71;
            font-size: 10px;
        }

        /* ==============================
           FORM CONTROLS
           ============================== */
        .st-form label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #4e4841;
            margin-bottom: 7px;
        }

        .required {
            color: #b51f2c;
        }

        .st-input,
        .st-textarea,
        .st-select {
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

        .st-input,
        .st-select {
            height: 45px;
        }

        .st-textarea {
            min-height: 80px;
            resize: vertical;
        }

        .st-select {
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

        .st-input:focus,
        .st-textarea:focus,
        .st-select:focus {
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

        .input-icon-wrap .st-input,
        .input-icon-wrap .st-select,
        .input-icon-wrap .st-textarea {
            padding-left: 40px;
        }

        .field-help {
            font-size: 9px;
            color: #948c82;
            margin-top: 6px;
            line-height: 1.5;
        }

        /* ==============================
           BRANCH ROWS
           ============================== */
        .branch-rows {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .branch-row {
            border: 1px solid #f0ebe4;
            background: #fffdf9;
            border-radius: 14px;
            padding: 14px;
            animation: inFade .25s ease;
        }

        @keyframes inFade {
            0% {
                opacity: 0;
                transform: translateY(-6px);
            }

            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .branch-row-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }

        .branch-row-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 10px;
            font-weight: 800;
            background: #f1e8df;
            color: #755d48;
            padding: 4px 9px;
            border-radius: 6px;
        }

        .branch-row-badge i {
            color: #b51f2c;
            font-size: 11px;
        }

        .branch-row-remove {
            background: #fff;
            border: 1px solid #f0d6d8;
            color: #b51f2c;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: .2s;
            font-size: 13px;
        }

        .branch-row-remove:hover {
            background: #fde6e6;
            border-color: #f5c0c0;
        }

        .branch-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
        }

        .branch-grid .field-label {
            font-size: 10px;
            font-weight: 700;
            color: #6f675f;
            margin-bottom: 5px;
            display: block;
        }

        .branch-full {
            grid-column: 1 / -1;
        }

        .branch-input {
            width: 100%;
            height: 42px;
            border: 1px solid #e8e1d8;
            background: #fff;
            border-radius: 10px;
            padding: 10px 12px;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            color: #292521;
            outline: none;
            transition: .2s ease;
        }

        .branch-input:focus {
            border-color: #d98a91;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .branch-textarea {
            width: 100%;
            min-height: 66px;
            border: 1px solid #e8e1d8;
            background: #fff;
            border-radius: 10px;
            padding: 10px 12px;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            color: #292521;
            outline: none;
            transition: .2s ease;
            resize: vertical;
        }

        .branch-textarea:focus {
            border-color: #d98a91;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .branches-empty {
            text-align: center;
            padding: 30px 20px;
            border: 2px dashed #e4ddd3;
            border-radius: 14px;
            color: #948c82;
            font-size: 11px;
            background: #fffdf9;
        }

        .branches-empty i {
            font-size: 30px;
            color: #d5cbbd;
            display: block;
            margin-bottom: 8px;
        }

        .add-branch-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #b51f2c;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 9px 14px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: .2s;
        }

        .add-branch-btn:hover {
            background: #8e1722;
        }

        /* ==============================
           BANNER SLOTS
           ============================== */
        .banner-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        @media (max-width: 760px) {
            .banner-grid {
                grid-template-columns: 1fr;
            }
        }

        .banner-slot {
            position: relative;
            border: 1.5px dashed #d8cdbf;
            border-radius: 14px;
            background: #fffdf9;
            overflow: hidden;
            transition: .18s ease;
            cursor: pointer;
            aspect-ratio: 16 / 7;
        }

        .banner-slot:hover {
            border-color: #d98a91;
            background: #fff;
        }

        .banner-slot.has-image {
            border-style: solid;
            border-color: #ece5da;
            background: #fff;
        }

        .banner-slot img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .banner-slot-empty {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #948c82;
            font-size: 12px;
            padding: 20px;
            text-align: center;
        }

        .banner-slot-empty i {
            font-size: 30px;
            color: #d5cbbd;
        }

        .banner-slot-empty strong {
            color: #6f675f;
            font-size: 12px;
        }

        .banner-slot-label {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(48, 41, 35, .78);
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 7px;
            letter-spacing: .5px;
            z-index: 2;
        }

        .banner-slot-remove {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            border: none;
            background: rgba(181, 31, 44, .92);
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            z-index: 3;
            transition: .15s ease;
        }

        .banner-slot-remove:hover {
            background: #8e1722;
        }

        .banner-slot-change {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(48, 41, 35, .85);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 8px;
            z-index: 2;
        }

        /* ==============================
           OFFER IMAGE SLOTS (NEW)
           ============================== */
        .offer-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 26px;
            padding-top: 22px;
            border-top: 1px solid #f0ebe4;
        }

        .offer-grid-title {
            grid-column: 1 / -1;
            font-size: 12px;
            font-weight: 800;
            color: #302923;
            margin-bottom: 4px;
        }

        .offer-grid-sub {
            grid-column: 1 / -1;
            font-size: 10px;
            color: #948c82;
            margin-bottom: 14px;
        }

        .offer-slot {
            position: relative;
            border: 1.5px dashed #d8cdbf;
            border-radius: 14px;
            background: #fffdf9;
            overflow: hidden;
            transition: .18s ease;
            cursor: pointer;
            aspect-ratio: 16 / 7;
        }

        .offer-slot:hover {
            border-color: #d98a91;
            background: #fff;
        }

        .offer-slot.has-image {
            border-style: solid;
            border-color: #ece5da;
            background: #fff;
        }

        .offer-slot img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .offer-slot-empty {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #948c82;
            font-size: 12px;
            padding: 20px;
            text-align: center;
        }

        .offer-slot-empty i {
            font-size: 30px;
            color: #d5cbbd;
        }

        .offer-slot-empty strong {
            color: #6f675f;
            font-size: 12px;
        }

        .offer-slot-label {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(48, 41, 35, .78);
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 7px;
            letter-spacing: .5px;
            z-index: 2;
        }

        .offer-slot-remove {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            border: none;
            background: rgba(181, 31, 44, .92);
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            z-index: 3;
            transition: .15s ease;
        }

        .offer-slot-remove:hover {
            background: #8e1722;
        }

        .offer-slot-change {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(48, 41, 35, .85);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 8px;
            z-index: 2;
        }

        @media (max-width: 760px) {
            .offer-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ==============================
           IMAGE BLOCKS (FAVICON / LOGO)
           ============================== */
        .img-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 760px) {
            .img-grid {
                grid-template-columns: 1fr;
            }
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
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
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
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border-radius: 8px;
            padding: 6px 11px;
            font-size: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: .2s ease;
            border: 1px solid transparent;
        }

        .img-action-btn.replace {
            background: #fff5f5;
            border-color: #f3c8cc;
            color: #b51f2c;
        }

        .img-action-btn.replace:hover {
            background: #fbe8e9;
        }

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

        .new-image-note.show {
            display: block;
        }

        .removed-image-note {
            color: #a02a2a;
            background: #fdeaea;
            border: 1px dashed #f3c8cc;
            align-items: center;
            gap: 6px;
        }

        .removed-image-note.show {
            display: flex;
        }

        /* ==============================
           TAX / GST
           ============================== */
        .gst-input {
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 700;
        }

        .gst-input.is-valid {
            border-color: #2e7d32 !important;
            background: #f6fbf7 !important;
        }

        .gst-input.is-invalid {
            border-color: #d98a91 !important;
            background: #fff7f8 !important;
        }

        .gst-input.is-required-empty {
            border-color: #f0b840 !important;
            background: #fffdf4 !important;
        }

        .gst-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 9.5px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 6px;
            margin-left: 6px;
        }

        .gst-badge.valid {
            background: #e8f6ea;
            color: #2e7d32;
        }

        .gst-badge.invalid {
            background: #fde6e6;
            color: #b51f2c;
        }

        .gst-badge.required {
            background: #fff7e0;
            color: #b8893c;
        }

        .tax-toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 14px 16px;
            background: #fffdf9;
            border: 1px solid #e8e1d8;
            border-radius: 12px;
            margin-bottom: 18px;
            transition: .25s ease;
        }

        .tax-toggle-row.is-enabled {
            background: linear-gradient(135deg, #f0fbf2 0%, #f7fdf8 100%);
            border-color: #c8e6c9;
        }

        .tax-toggle-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .tax-toggle-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #f3eefe;
            color: #7c3aed;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: .25s;
        }

        .tax-toggle-row.is-enabled .tax-toggle-icon {
            background: #e8f6ea;
            color: #2e7d32;
        }

        .tax-toggle-info h4 {
            margin: 0;
            font-size: 13px;
            font-weight: 700;
            color: #302923;
        }

        .tax-toggle-info p {
            margin: 2px 0 0;
            font-size: 10px;
            color: #948c82;
        }

        .mm-switch {
            position: relative;
            display: inline-block;
            width: 46px;
            height: 26px;
            flex-shrink: 0;
        }

        .mm-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .mm-switch-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: #d8d2c9;
            border-radius: 30px;
            transition: .25s;
        }

        .mm-switch-slider::before {
            content: "";
            position: absolute;
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background: #fff;
            border-radius: 50%;
            transition: .25s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .15);
        }

        .mm-switch input:checked+.mm-switch-slider {
            background: #2e7d32;
        }

        .mm-switch input:checked+.mm-switch-slider::before {
            transform: translateX(20px);
        }

        .tax-fields-wrap {
            overflow: hidden;
            max-height: 0;
            opacity: 0;
            transition: max-height .4s cubic-bezier(.2, .9, .3, 1),
                opacity .25s ease, margin-top .25s ease;
            margin-top: 0;
        }

        .tax-fields-wrap.is-open {
            max-height: 900px;
            opacity: 1;
            margin-top: 4px;
        }

        .tax-options {
            display: flex;
            gap: 12px;
            margin-bottom: 18px;
        }

        .tax-option {
            flex: 1;
            position: relative;
            cursor: pointer;
        }

        .tax-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .tax-option-box {
            border: 1.5px solid #e8e1d8;
            background: #fffdf9;
            border-radius: 12px;
            padding: 14px 14px 12px;
            transition: .2s ease;
            height: 100%;
        }

        .tax-option-box .tax-opt-head {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 700;
            color: #302923;
            margin-bottom: 5px;
        }

        .tax-option-box .tax-opt-head i {
            font-size: 15px;
            color: #b51f2c;
        }

        .tax-option-box p {
            margin: 0;
            font-size: 10px;
            color: #948c82;
            line-height: 1.5;
        }

        .tax-option input:checked+.tax-option-box {
            border-color: #b51f2c;
            background: #fff7f8;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .tax-option input:checked+.tax-option-box .tax-opt-head i {
            color: #b51f2c;
        }

        /* ==============================
           ACTIONS
           ============================== */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid #f0ebe4;
        }

        .btn-save {
            border: none;
            background: #b51f2c;
            color: #fff;
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

        /* ==============================
           MODALS
           ============================== */
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
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin: 0 auto 16px;
        }

        .mm-modal-icon.success {
            background: #e8f6ea;
            color: #2e7d32;
        }

        .mm-modal-icon.error {
            background: #fde6e6;
            color: #c62828;
        }

        .mm-modal-icon.danger {
            background: #fde6e6;
            color: #c62828;
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
        }

        .mm-btn-danger {
            background: #c62828;
            color: #fff;
        }

        .mm-btn-danger:hover {
            background: #a02020;
        }

        @media (max-width: 768px) {
            .st-page {
                padding: 20px 15px 30px;
            }

            .st-card {
                padding: 17px;
                border-radius: 17px;
            }

            .img-grid {
                grid-template-columns: 1fr;
            }

            .branch-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn-save {
                width: 100%;
                justify-content: center;
            }

            .tax-options {
                flex-direction: column;
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
                        <div class="admin-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></div>
                        <div class="admin-role">Admin</div>
                    </div>
                </div>
            </div>
        </header>


        <div class="st-page">

            <div class="st-header">
                <div class="st-title">
                    <h1>Website Settings</h1>
                    <p>Site info, branches, banners, offers, payment, tax and branding — all in one place.</p>
                </div>
            </div>


            <!-- TABS -->
            <div class="st-tabs">
                <button type="button" class="st-tab active" data-tab="general">
                    <i class="bi bi-sliders"></i> General
                </button>
                <button type="button" class="st-tab" data-tab="branches">
                    <i class="bi bi-geo-alt"></i> Branches
                </button>
                <button type="button" class="st-tab" data-tab="banners">
                    <i class="bi bi-images"></i> Banners
                </button>
                <button type="button" class="st-tab" data-tab="payment">
                    <i class="bi bi-credit-card"></i> Payment
                </button>
                <button type="button" class="st-tab" data-tab="tax">
                    <i class="bi bi-receipt"></i> Tax &amp; GST
                </button>
                <button type="button" class="st-tab" data-tab="branding">
                    <i class="bi bi-palette"></i> Branding
                </button>
            </div>


            <form id="settingsForm" enctype="multipart/form-data" novalidate>

                <input type="hidden" id="remove_favicon" name="remove_favicon" value="0">
                <input type="hidden" id="remove_logo" name="remove_logo" value="0">
                <input type="hidden" id="remove_banner_1" name="remove_banner_1" value="0">
                <input type="hidden" id="remove_banner_2" name="remove_banner_2" value="0">
                <input type="hidden" id="remove_banner_3" name="remove_banner_3" value="0">
                <input type="hidden" id="remove_banner_4" name="remove_banner_4" value="0">
                <input type="hidden" id="remove_today_offer" name="remove_today_offer" value="0">
                <input type="hidden" id="remove_tomorrow_offer" name="remove_tomorrow_offer" value="0">


                <!-- ============================================================
                     TAB 1: GENERAL
                     ============================================================ -->
                <div class="st-panel active" id="panel-general">
                    <div class="st-card">

                        <div class="st-card-header">
                            <div class="st-card-icon"><i class="bi bi-shop"></i></div>
                            <div>
                                <h3>Site Info</h3>
                                <span>Brand name, contact and currency.</span>
                            </div>
                        </div>

                        <div class="row g-3">

                            <div class="col-12 col-md-6">
                                <label>Site Name <span class="required">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-shop"></i>
                                    <input type="text" name="username" id="username"
                                        class="st-input" value="<?= htmlspecialchars($settings['username'] ?? '') ?>"
                                        maxlength="150" required>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label>Mobile Number</label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-telephone"></i>
                                    <input type="tel" name="mobile_number" id="mobile_number"
                                        class="st-input" value="<?= htmlspecialchars($settings['mobile_number'] ?? '') ?>"
                                        maxlength="30">
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label>Email Address</label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-envelope"></i>
                                    <input type="email" name="email_address" id="email_address"
                                        class="st-input" value="<?= htmlspecialchars($settings['email_address'] ?? '') ?>"
                                        maxlength="190">
                                </div>
                            </div>

                            <div class="col-12 col-md-3">
                                <label>Currency Symbol</label>
                                <input type="text" name="currency" id="currency"
                                    class="st-input" value="<?= htmlspecialchars($settings['currency'] ?? '₹') ?>"
                                    maxlength="10">
                            </div>

                            <div class="col-12 col-md-3">
                                <label>Currency Code</label>
                                <input type="text" name="currency_code" id="currency_code"
                                    class="st-input" value="<?= htmlspecialchars($settings['currency_code'] ?? 'INR') ?>"
                                    maxlength="10">
                            </div>

                        </div>

                    </div>
                </div>


                <!-- ============================================================
                     TAB 2: BRANCHES
                     ============================================================ -->
                <div class="st-panel" id="panel-branches">
                    <div class="st-card">

                        <div class="st-card-header">
                            <div class="st-card-icon"><i class="bi bi-geo-alt"></i></div>
                            <div>
                                <h3>Branch Addresses</h3>
                                <span>Add multiple branches with name, address, phone and email.</span>
                            </div>
                            <div style="margin-left:auto;">
                                <button type="button" class="add-branch-btn" id="addBranchBtn">
                                    <i class="bi bi-plus-lg"></i> Add Branch
                                </button>
                            </div>
                        </div>

                        <div class="branch-rows" id="branchRows"></div>

                        <div class="branches-empty" id="branchesEmpty">
                            <i class="bi bi-geo-alt"></i>
                            No branches added yet. Click <strong>Add Branch</strong> to start.
                        </div>

                    </div>
                </div>


                <!-- ============================================================
                     TAB 3: BANNERS + OFFERS
                     ============================================================ -->
                <div class="st-panel" id="panel-banners">
                    <div class="st-card">

                        <div class="st-card-header">
                            <div class="st-card-icon"><i class="bi bi-images"></i></div>
                            <div>
                                <h3>Website Banners &amp; Offers</h3>
                                <span>Upload up to 4 promotional banners and 2 offer images (1600×700 px recommended, max 3 MB).</span>
                            </div>
                        </div>

                        <div class="banner-grid">

                            <?php for ($i = 1; $i <= 4; $i++): ?>
                                <?php
                                $bannerVal = $banners[$i] ?? '';
                                $bannerUrl = !empty($bannerVal)
                                    ? ADMIN_URL . $bannerVal . '?v=' . time()
                                    : '';
                                ?>
                                <div class="banner-slot <?= $bannerUrl ? 'has-image' : '' ?>"
                                    data-slot="<?= $i ?>"
                                    id="bannerSlot<?= $i ?>">

                                    <span class="banner-slot-label">BANNER <?= $i ?></span>

                                    <input type="file"
                                        class="banner-file"
                                        id="bannerFile<?= $i ?>"
                                        name="banner_<?= $i ?>"
                                        accept="image/*"
                                        style="display:none;">

                                    <?php if ($bannerUrl): ?>
                                        <img src="<?= htmlspecialchars($bannerUrl) ?>"
                                            alt="Banner <?= $i ?>"
                                            id="bannerImg<?= $i ?>">

                                        <button type="button"
                                            class="banner-slot-remove"
                                            data-slot="<?= $i ?>"
                                            title="Remove banner">
                                            <i class="bi bi-x-lg"></i>
                                        </button>

                                        <span class="banner-slot-change">
                                            <i class="bi bi-arrow-repeat"></i> Change
                                        </span>
                                    <?php else: ?>
                                        <div class="banner-slot-empty" id="bannerEmpty<?= $i ?>">
                                            <i class="bi bi-cloud-arrow-up"></i>
                                            <strong>Click to upload</strong>
                                            <span>Banner <?= $i ?></span>
                                        </div>
                                    <?php endif; ?>

                                </div>
                            <?php endfor; ?>

                        </div>


                        <!-- ============================
                             OFFER IMAGES
                             ============================ -->
                        <div class="offer-grid">

                            <div class="offer-grid-title">
                                <i class="bi bi-fire" style="color:#b51f2c;"></i> Special Offer Images
                            </div>
                            <div class="offer-grid-sub">
                                These appear prominently on the home page — separate from regular banners.
                            </div>

                            <?php
                            $todayUrl    = !empty($todayOffer)    ? ADMIN_URL . $todayOffer    . '?v=' . time() : '';
                            $tomorrowUrl = !empty($tomorrowOffer) ? ADMIN_URL . $tomorrowOffer . '?v=' . time() : '';
                            ?>

                            <!-- TODAY OFFER -->
                            <div class="offer-slot <?= $todayUrl ? 'has-image' : '' ?>"
                                data-slot="today"
                                id="offerSlotToday">

                                <span class="offer-slot-label">🔥 TODAY OFFER</span>

                                <input type="file"
                                    class="offer-file"
                                    id="offerFileToday"
                                    name="today_offer_image"
                                    accept="image/*"
                                    style="display:none;">

                                <?php if ($todayUrl): ?>
                                    <img src="<?= htmlspecialchars($todayUrl) ?>"
                                        alt="Today Offer"
                                        id="offerImgToday">

                                    <button type="button"
                                        class="offer-slot-remove"
                                        data-slot="today"
                                        title="Remove offer">
                                        <i class="bi bi-x-lg"></i>
                                    </button>

                                    <span class="offer-slot-change">
                                        <i class="bi bi-arrow-repeat"></i> Change
                                    </span>
                                <?php else: ?>
                                    <div class="offer-slot-empty" id="offerEmptyToday">
                                        <i class="bi bi-cloud-arrow-up"></i>
                                        <strong>Click to upload</strong>
                                        <span>Today Offer</span>
                                    </div>
                                <?php endif; ?>

                            </div>

                            <!-- TOMORROW OFFER -->
                            <div class="offer-slot <?= $tomorrowUrl ? 'has-image' : '' ?>"
                                data-slot="tomorrow"
                                id="offerSlotTomorrow">

                                <span class="offer-slot-label">🌅 TOMORROW OFFER</span>

                                <input type="file"
                                    class="offer-file"
                                    id="offerFileTomorrow"
                                    name="tomorrow_offer_image"
                                    accept="image/*"
                                    style="display:none;">

                                <?php if ($tomorrowUrl): ?>
                                    <img src="<?= htmlspecialchars($tomorrowUrl) ?>"
                                        alt="Tomorrow Offer"
                                        id="offerImgTomorrow">

                                    <button type="button"
                                        class="offer-slot-remove"
                                        data-slot="tomorrow"
                                        title="Remove offer">
                                        <i class="bi bi-x-lg"></i>
                                    </button>

                                    <span class="offer-slot-change">
                                        <i class="bi bi-arrow-repeat"></i> Change
                                    </span>
                                <?php else: ?>
                                    <div class="offer-slot-empty" id="offerEmptyTomorrow">
                                        <i class="bi bi-cloud-arrow-up"></i>
                                        <strong>Click to upload</strong>
                                        <span>Tomorrow Offer</span>
                                    </div>
                                <?php endif; ?>

                            </div>

                        </div>

                    </div>
                </div>


                <!-- ============================================================
                     TAB 4: PAYMENT
                     ============================================================ -->
                <div class="st-panel" id="panel-payment">
                    <div class="st-card">

                        <div class="st-card-header">
                            <div class="st-card-icon"><i class="bi bi-credit-card"></i></div>
                            <div>
                                <h3>Payment Gateway</h3>
                                <span>Razorpay keys and UPI ID for online payments.</span>
                            </div>
                        </div>

                        <div class="row g-3">

                            <div class="col-12 col-md-6">
                                <label>Razorpay Key ID <span class="required">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-key"></i>
                                    <input type="text" name="razorpay_key_id" id="razorpay_key_id"
                                        class="st-input"
                                        value="<?= htmlspecialchars($settings['razorpay_key_id'] ?? '') ?>"
                                        placeholder="rzp_test_xxxxxxxxxxxxx"
                                        maxlength="255" autocomplete="off" spellcheck="false">
                                </div>
                                <p class="field-help">Your Razorpay API Key ID (starts with <strong>rzp_</strong>).</p>
                            </div>

                            <div class="col-12 col-md-6">
                                <label>Razorpay Key Secret <span class="required">*</span></label>
                                <div style="position:relative;">
                                    <input type="password" name="razorpay_key_secret" id="razorpay_key_secret"
                                        class="st-input"
                                        value="<?= htmlspecialchars($settings['razorpay_key_secret'] ?? '') ?>"
                                        placeholder="Your Key Secret"
                                        maxlength="255" autocomplete="new-password" spellcheck="false">
                                    <button type="button"
                                        style="position:absolute;right:12px;top:50%;transform:translateY(-50%);border:0;background:transparent;color:#aaa198;cursor:pointer;font-size:16px;padding:4px 6px;"
                                        id="toggleSecret" aria-label="Show secret">
                                        <i class="bi bi-eye" id="eyeIcon"></i>
                                    </button>
                                </div>
                                <p class="field-help">Your Razorpay Key Secret. Keep this private.</p>
                            </div>

                            <div class="col-12 col-md-6">
                                <label>UPI ID <span class="required">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-at"></i>
                                    <input type="text" name="upi_id" id="upi_id"
                                        class="st-input"
                                        value="<?= htmlspecialchars($settings['upi_id'] ?? '') ?>"
                                        placeholder="yourname@upi"
                                        maxlength="255" autocomplete="off" spellcheck="false">
                                </div>
                                <p class="field-help">UPI ID (VPA). Example: <strong>mrsmill@okhdfcbank</strong></p>
                            </div>

                        </div>

                    </div>
                </div>


                <!-- ============================================================
                     TAB 5: TAX & GST
                     ============================================================ -->
                <div class="st-panel" id="panel-tax">
                    <div class="st-card">

                        <div class="st-card-header">
                            <div class="st-card-icon"><i class="bi bi-receipt"></i></div>
                            <div>
                                <h3>Tax &amp; GST</h3>
                                <span>Enable tax calculation and GST compliance.</span>
                            </div>
                        </div>

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
                                <input type="checkbox" id="tax_status" name="tax_status"
                                    value="1" <?= (int)($settings['tax_status'] ?? 0) === 1 ? 'checked' : '' ?>>
                                <span class="mm-switch-slider"></span>
                            </label>
                        </div>

                        <div class="tax-fields-wrap" id="taxFieldsWrap">
                            <div style="padding: 4px 2px 4px;">

                                <div style="margin-bottom:18px;">
                                    <label>Tax Type <span class="required">*</span></label>
                                    <div class="tax-options">
                                        <label class="tax-option" for="tax_type_exclusive">
                                            <input type="radio"
                                                id="tax_type_exclusive"
                                                name="tax_type"
                                                value="exclusive"
                                                <?= ($settings['tax_type'] ?? 'exclusive') === 'exclusive' ? 'checked' : '' ?>>
                                            <div class="tax-option-box">
                                                <div class="tax-opt-head">
                                                    <i class="bi bi-plus-circle-fill"></i> Exclusive
                                                </div>
                                                <p>Tax added on top of product price.</p>
                                            </div>
                                        </label>

                                        <label class="tax-option" for="tax_type_inclusive">
                                            <input type="radio"
                                                id="tax_type_inclusive"
                                                name="tax_type"
                                                value="inclusive"
                                                <?= ($settings['tax_type'] ?? '') === 'inclusive' ? 'checked' : '' ?>>
                                            <div class="tax-option-box">
                                                <div class="tax-opt-head">
                                                    <i class="bi bi-check-circle-fill"></i> Inclusive
                                                </div>
                                                <p>Tax already included in price.</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div style="margin-bottom:18px;">
                                    <label>Tax Rate (%) <span class="required">*</span></label>
                                    <div class="input-icon-wrap">
                                        <i class="bi bi-percent"></i>
                                        <input type="number" name="tax_rate" id="tax_rate"
                                            class="st-input"
                                            value="<?= htmlspecialchars($settings['tax_rate'] ?? '0') ?>"
                                            placeholder="5" min="0" max="100" step="0.01" inputmode="decimal">
                                    </div>
                                    <p class="field-help">Value between <strong>0</strong> and <strong>100</strong>.</p>
                                </div>

                                <div style="margin-bottom:18px;">
                                    <label>
                                        GST Number
                                        <span class="required" id="gstRequiredMark" style="display:none;">*</span>
                                        <span style="color:#948c82;font-weight:600;font-size:10px;margin-left:4px;" id="gstOptionalTag">(optional)</span>
                                        <span class="gst-badge" id="gstBadge" style="display:none;"></span>
                                    </label>
                                    <div class="input-icon-wrap">
                                        <i class="bi bi-receipt-cutoff"></i>
                                        <input type="text" name="gst_number" id="gst_number"
                                            class="st-input gst-input"
                                            value="<?= htmlspecialchars($settings['gst_number'] ?? '') ?>"
                                            placeholder="22AAAAA0000A1Z5"
                                            maxlength="15" autocomplete="off" spellcheck="false">
                                    </div>
                                    <p class="field-help" id="gstHelp">
                                        15-character GSTIN. Example: <strong>22AAAAA0000A1Z5</strong>
                                    </p>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>


                <!-- ============================================================
                     TAB 6: BRANDING
                     ============================================================ -->
                <div class="st-panel" id="panel-branding">
                    <div class="st-card">

                        <div class="st-card-header">
                            <div class="st-card-icon"><i class="bi bi-palette"></i></div>
                            <div>
                                <h3>Branding</h3>
                                <span>Favicon is used in the browser tab, logo is shown in the app.</span>
                            </div>
                        </div>

                        <div class="img-grid">

                            <!-- FAVICON -->
                            <div class="img-block">
                                <div class="img-block-title">
                                    <i class="bi bi-star"></i> Favicon
                                </div>
                                <div class="img-block-sub">
                                    Square icon, 32×32 or 64×64 (ICO, PNG, SVG)
                                </div>

                                <div class="img-upload-zone" id="faviconZone">
                                    <input type="file" id="favicon_image" name="favicon_image"
                                        accept=".ico,.png,.svg,image/x-icon,image/png,image/svg+xml,image/jpeg,image/webp">

                                    <div class="img-preview-wrap" id="faviconWrap"
                                        style="<?= !empty($settings['favicon_image']) ? '' : 'display:none;' ?>">
                                        <img src="<?= !empty($settings['favicon_image']) ? htmlspecialchars(ADMIN_URL . $settings['favicon_image']) . '?v=' . time() : '' ?>"
                                            alt="Favicon"
                                            class="img-preview ico"
                                            id="faviconPreview"
                                            <?= !empty($settings['favicon_image']) ? '' : 'style="display:none;"' ?>>

                                        <div class="img-preview-actions">
                                            <button type="button" class="img-action-btn replace" id="faviconReplaceBtn">
                                                <i class="bi bi-arrow-repeat"></i> Replace
                                            </button>
                                            <button type="button" class="img-action-btn remove" id="faviconRemoveBtn">
                                                <i class="bi bi-trash"></i> Remove
                                            </button>
                                        </div>
                                    </div>

                                    <div class="img-placeholder" id="faviconPlaceholder"
                                        style="<?= !empty($settings['favicon_image']) ? 'display:none;' : '' ?>">
                                        <i class="bi bi-star"></i>
                                        <strong>Click or drag favicon</strong>
                                        <small>ICO, PNG, SVG — max 3 MB</small>
                                    </div>
                                </div>

                                <div class="new-image-note" id="faviconNewNote">
                                    <i class="bi bi-info-circle"></i> On save, the old favicon will be replaced.
                                </div>

                                <div class="removed-image-note" id="faviconRemovedNote">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    <span>Favicon will be removed on save.</span>
                                </div>
                            </div>


                            <!-- LOGO -->
                            <div class="img-block">
                                <div class="img-block-title">
                                    <i class="bi bi-image"></i> Logo
                                </div>
                                <div class="img-block-sub">
                                    Horizontal or square logo (PNG, SVG, JPG)
                                </div>

                                <div class="img-upload-zone" id="logoZone">
                                    <input type="file" id="logo_image" name="logo_image"
                                        accept=".png,.svg,.jpg,.jpeg,.webp,image/png,image/svg+xml,image/jpeg,image/webp">

                                    <div class="img-preview-wrap" id="logoWrap"
                                        style="<?= !empty($settings['logo_image']) ? '' : 'display:none;' ?>">
                                        <img src="<?= !empty($settings['logo_image']) ? htmlspecialchars(ADMIN_URL . $settings['logo_image']) . '?v=' . time() : '' ?>"
                                            alt="Logo"
                                            class="img-preview"
                                            id="logoPreview"
                                            <?= !empty($settings['logo_image']) ? '' : 'style="display:none;"' ?>>

                                        <div class="img-preview-actions">
                                            <button type="button" class="img-action-btn replace" id="logoReplaceBtn">
                                                <i class="bi bi-arrow-repeat"></i> Replace
                                            </button>
                                            <button type="button" class="img-action-btn remove" id="logoRemoveBtn">
                                                <i class="bi bi-trash"></i> Remove
                                            </button>
                                        </div>
                                    </div>

                                    <div class="img-placeholder" id="logoPlaceholder"
                                        style="<?= !empty($settings['logo_image']) ? 'display:none;' : '' ?>">
                                        <i class="bi bi-image"></i>
                                        <strong>Click or drag logo</strong>
                                        <small>PNG, SVG, JPG — max 3 MB</small>
                                    </div>
                                </div>

                                <div class="new-image-note" id="logoNewNote">
                                    <i class="bi bi-info-circle"></i> On save, the old logo will be replaced.
                                </div>

                                <div class="removed-image-note" id="logoRemovedNote">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    <span>Logo will be removed on save.</span>
                                </div>
                            </div>

                        </div>

                    </div>
                </div>


                <!-- FORM ACTIONS -->
                <div class="st-card" style="margin-top:20px;">
                    <div class="form-actions">
                        <button type="submit" class="btn-save" id="saveBtn">
                            <i class="bi bi-check-lg"></i>
                            <span id="saveBtnText">Save All Settings</span>
                        </button>
                    </div>
                </div>

            </form>

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

    <!-- ERROR -->
    <div class="mm-modal-overlay" id="errorOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog" aria-modal="true">
            <div class="mm-modal-icon error"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <h3 class="mm-modal-title" id="errorTitle">Oops!</h3>
            <p class="mm-modal-text" id="errorText">Something went wrong.</p>
            <div class="mm-modal-actions">
                <button type="button" class="mm-btn mm-btn-primary" id="errorOkBtn">
                    <i class="bi bi-check2"></i> Got it
                </button>
            </div>
        </div>
    </div>

    <!-- SUCCESS -->
    <div class="mm-modal-overlay" id="successOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog" aria-modal="true">
            <div class="mm-modal-icon success"><i class="bi bi-check-lg"></i></div>
            <h3 class="mm-modal-title">Settings Saved!</h3>
            <p class="mm-modal-text" id="successText">All settings have been updated successfully.</p>
            <div class="mm-modal-actions">
                <button type="button" class="mm-btn mm-btn-primary" id="successOkBtn">
                    <i class="bi bi-check2"></i> Done
                </button>
            </div>
        </div>
    </div>


    <script>
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL ?>js/main.js"></script>
    <script src="<?= ADMIN_URL ?>js/settings.js"></script>

</body>

</html>