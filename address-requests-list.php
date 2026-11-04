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
        /* ================= Page layout ================= */
        .ar-page {
            padding: 30px 32px 60px;
        }

        .ar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .ar-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 5px;
        }

        .ar-title p {
            margin: 0;
            color: #817a71;
            font-size: 13px;
        }

        .ar-refresh-btn {
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

        .ar-refresh-btn:hover {
            background: #faf7f0;
            color: #302923;
        }

        .ar-card {
            background: #fff;
            border: 1px solid #eee7dc;
            border-radius: 20px;
            padding: 22px;
        }

        .ar-list-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .ar-list-title h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #302923;
        }

        .ar-list-title span {
            display: block;
            margin-top: 4px;
            font-size: 10px;
            color: #817a71;
        }

        .ar-search {
            position: relative;
            width: 260px;
        }

        .ar-search i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa198;
            font-size: 13px;
            pointer-events: none;
        }

        .ar-search input {
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

        .ar-search input:focus {
            border-color: #d98a91;
            background: #fff;
        }

        /* ================= Filters ================= */
        .ar-filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 18px;
            padding: 12px 14px;
            background: #fdfaf4;
            border: 1px solid #f0ebe4;
            border-radius: 12px;
        }

        .ar-filters .ar-fgroup {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ar-filters label {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #938a80;
        }

        .ar-filters select,
        .ar-filters input[type=date] {
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

        .ar-filters select:focus,
        .ar-filters input[type=date]:focus {
            border-color: #d98a91;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        #arCustomRange {
            display: none;
        }

        /* ================= Accordion ================= */
        .ar-acc-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .ar-acc-item {
            background: #fff;
            border: 1.5px solid #eee7dc;
            border-radius: 14px;
            overflow: hidden;
            transition: .2s;
        }

        .ar-acc-item.open {
            border-color: #d98a91;
            box-shadow: 0 6px 20px rgba(181, 31, 44, .06);
        }

        .ar-acc-head {
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr 120px 130px 42px;
            gap: 12px;
            align-items: center;
            padding: 14px 16px;
            cursor: pointer;
            user-select: none;
            transition: background .15s;
        }

        .ar-acc-head:hover {
            background: #fffdf9;
        }

        .ar-acc-item.open .ar-acc-head {
            background: #fffdf9;
            border-bottom: 1px solid #f0ebe4;
        }

        .ar-acc-item.is-approved .ar-acc-head {
            border-left: 4px solid #2e6b3a;
        }

        .ar-acc-item.is-rejected .ar-acc-head {
            border-left: 4px solid #a02020;
        }

        .ar-acc-item.is-pending .ar-acc-head {
            border-left: 4px solid #d4a017;
        }

        .ar-acc-head .ar-col-label {
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .7px;
            color: #938a80;
            margin-bottom: 3px;
            display: none;
        }

        .ar-acc-customer {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .ar-avatar {
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

        .ar-cust-name {
            font-weight: 700;
            color: #302923;
            font-size: 12.5px;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }

        .ar-cust-mobile {
            font-size: 10.5px;
            color: #948c82;
            margin-top: 2px;
            white-space: nowrap;
        }

        .ar-apartment {
            font-weight: 700;
            color: #302923;
            font-size: 12px;
            display: block;
        }

        .ar-acc-chevron {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            border: 1.5px solid #eee7dc;
            background: #fff;
            color: #817a71;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: .25s;
            flex-shrink: 0;
            cursor: pointer;
        }

        .ar-acc-item.open .ar-acc-chevron {
            transform: rotate(180deg);
            background: #fbe8e9;
            color: #b51f2c;
            border-color: #f0c1c5;
        }

        .ar-date {
            font-size: 11px;
            color: #6f675f;
            font-weight: 600;
            white-space: nowrap;
        }

        .ar-date small {
            display: block;
            font-size: 10px;
            color: #948c82;
            font-weight: 500;
            margin-top: 2px;
        }

        .ar-acc-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            height: 26px;
            padding: 0 10px;
            border-radius: 99px;
            font-size: 10.5px;
            font-weight: 800;
            white-space: nowrap;
        }

        .ar-acc-status-pill .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
        }

        .ar-acc-status-pill.pending {
            background: #fff8e1;
            color: #a17f00;
        }

        .ar-acc-status-pill.pending .dot {
            background: #d4a017;
        }

        .ar-acc-status-pill.approved {
            background: #e8f1e8;
            color: #2e6b3a;
        }

        .ar-acc-status-pill.approved .dot {
            background: #2e6b3a;
        }

        .ar-acc-status-pill.rejected {
            background: #fdeaea;
            color: #a02020;
        }

        .ar-acc-status-pill.rejected .dot {
            background: #a02020;
        }

        .ar-acc-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height .3s cubic-bezier(.4, 0, .2, 1);
            background: #fff;
        }

        .ar-acc-item.open .ar-acc-body {
            max-height: 2200px;
        }

        .ar-acc-body-inner {
            padding: 16px 18px 18px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 22px;
            border-top: 1px solid #f7f2ec;
        }

        .ar-acc-detail {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }

        .ar-acc-detail-label {
            font-size: 9.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #948c82;
        }

        .ar-acc-detail-value {
            font-size: 12.5px;
            color: #302923;
            font-weight: 600;
            line-height: 1.5;
            word-break: break-word;
        }

        .ar-acc-detail-value a {
            color: #b51f2c;
            text-decoration: none;
            font-weight: 700;
        }

        .ar-acc-actions {
            grid-column: 1/-1;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding-top: 12px;
            border-top: 1px solid #f7f2ec;
            margin-top: 4px;
        }

        /* Mini previews in body */
        .ar-mini-map {
            grid-column: 1/-1;
            border: 1px solid #f0ebe4;
            border-radius: 10px;
            overflow: hidden;
        }

        .ar-mini-map iframe {
            width: 100%;
            height: 180px;
            border: 0;
            display: block;
        }

        .ar-mini-img {
            grid-column: 1/-1;
            border: 1px solid #f0ebe4;
            border-radius: 10px;
            overflow: hidden;
        }

        .ar-mini-img img {
            width: 100%;
            max-height: 220px;
            object-fit: contain;
            display: block;
        }

        .ar-mini-label {
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: .6px;
            color: #948c82;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        /* ================= Buttons ================= */
        .ar-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            height: 34px;
            padding: 0 14px;
            border-radius: 8px;
            border: 1.5px solid transparent;
            font-family: "DM Sans", sans-serif;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            transition: .15s;
            white-space: nowrap;
        }

        .ar-btn-approve {
            background: #e8f1e8;
            color: #2e6b3a;
            border-color: #a7c8a9;
        }

        .ar-btn-approve:hover {
            background: #2e6b3a;
            color: #fff;
            border-color: #2e6b3a;
        }

        .ar-btn-unapprove {
            background: #fff5e6;
            color: #a06000;
            border-color: #f0c98a;
        }

        .ar-btn-unapprove:hover {
            background: #a06000;
            color: #fff;
            border-color: #a06000;
        }

        .ar-call {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            height: 34px;
            padding: 0 14px;
            border-radius: 8px;
            border: 1.5px solid #eee7dc;
            background: #fff;
            color: #6f675f;
            font-family: "DM Sans", sans-serif;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            transition: .15s;
        }

        .ar-call:hover {
            background: #e8f1e8;
            border-color: #a7c8a9;
            color: #1b5e20;
        }

        .ar-whatsapp {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            height: 34px;
            padding: 0 14px;
            border-radius: 8px;
            border: 1.5px solid #c8e6c9;
            background: #e8f6ea;
            color: #1f7a3d;
            font-family: "DM Sans", sans-serif;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            transition: .15s;
        }

        .ar-whatsapp:hover {
            background: #25d366;
            border-color: #25d366;
            color: #fff;
        }

        /* ================= Misc ================= */
        .ar-empty {
            text-align: center;
            padding: 50px 20px;
            color: #948c82;
        }

        .ar-empty i {
            font-size: 42px;
            color: #d5cbbd;
            display: block;
            margin-bottom: 12px;
        }

        .ar-empty h3 {
            font-family: "Playfair Display", serif;
            font-size: 17px;
            color: #6f675f;
            margin: 0 0 6px;
            font-weight: 700;
        }

        .ar-empty p {
            margin: 0;
            font-size: 12px;
        }

        .ar-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid #f2ede5;
            flex-wrap: wrap;
        }

        .ar-pag-left {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .ar-pag-info {
            font-size: 11px;
            color: #817a71;
        }

        .ar-pag-info strong {
            color: #302923;
            font-weight: 700;
        }

        .ar-perpage {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            color: #817a71;
        }

        .ar-perpage select {
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

        .ar-pag-controls {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
        }

        .ar-pag-controls button {
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

        .ar-pag-controls button:hover:not(:disabled):not(.active) {
            background: #faf7f0;
        }

        .ar-pag-controls button.active {
            background: #b51f2c;
            border-color: #b51f2c;
            color: #fff;
            cursor: default;
        }

        .ar-pag-controls button:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .ar-pag-controls .ellipsis {
            min-width: 26px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #b5aca2;
            font-size: 12px;
            font-weight: 700;
        }

        .ar-toast-wrap {
            position: fixed;
            top: 22px;
            right: 22px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 10000;
            pointer-events: none;
        }

        .ar-toast {
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

        .ar-toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .ar-toast-icon {
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

        .ar-toast.success .ar-toast-icon {
            background: #4caf50;
        }

        .ar-toast.error .ar-toast-icon {
            background: #c62828;
        }

        .ar-toast-body {
            flex: 1;
            padding-top: 3px;
            line-height: 1.5;
        }

        .ar-toast-close {
            background: transparent;
            border: 0;
            color: #b5aca2;
            cursor: pointer;
            font-size: 14px;
            padding: 0 2px;
            line-height: 1;
        }

        /* ================= Modal ================= */
        .ar-modal-overlay {
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

        .ar-modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .ar-modal {
            background: #fff;
            border-radius: 18px;
            width: 100%;
            max-width: 680px;
            padding: 26px 24px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, .25);
            transform: translateY(15px) scale(.97);
            transition: .25s;
            margin: auto;
        }

        .ar-modal-overlay.show .ar-modal {
            transform: translateY(0) scale(1);
        }

        .ar-modal-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 18px;
        }

        .ar-modal-head h3 {
            margin: 0;
            font-family: "Playfair Display", serif;
            font-size: 20px;
            color: #302923;
            font-weight: 700;
        }

        .ar-modal-head p {
            margin: 4px 0 0;
            font-size: 12px;
            color: #817a71;
        }

        .ar-modal-close {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: transparent;
            border: 0;
            color: #948c82;
            cursor: pointer;
            font-size: 18px;
        }

        .ar-modal-close:hover {
            background: #faf7f0;
            color: #302923;
        }

        .ar-modal-body {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .ar-modal-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        @media (max-width:560px) {
            .ar-modal-row {
                grid-template-columns: 1fr;
            }
        }

        .ar-modal-field label {
            display: block;
            font-size: 10px;
            font-weight: 800;
            color: #4e4841;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .ar-modal-field input,
        .ar-modal-field select,
        .ar-modal-field textarea {
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

        .ar-modal-field textarea {
            min-height: 60px;
            resize: vertical;
        }

        .ar-modal-field input:focus,
        .ar-modal-field select:focus,
        .ar-modal-field textarea:focus {
            border-color: #d98a91;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .ar-modal-hint {
            font-size: 10.5px;
            color: #948c82;
            margin-top: 4px;
            line-height: 1.5;
        }

        .ar-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid #f0ebe4;
        }

        .ar-modal-btn {
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

        .ar-modal-btn-ghost {
            background: #fff;
            border: 1.5px solid #e4ddd3;
            color: #6f675f;
        }

        .ar-modal-btn-ghost:hover {
            background: #faf7f0;
        }

        .ar-modal-btn-primary {
            background: #2e6b3a;
            color: #fff;
        }

        .ar-modal-btn-primary:hover {
            background: #1f4f29;
        }

        .ar-modal-btn[disabled] {
            opacity: .6;
            cursor: not-allowed;
        }

        /* Map + image preview inside modal */
        .ar-preview {
            margin-top: 6px;
            border: 1px solid #f0ebe4;
            border-radius: 10px;
            overflow: hidden;
            background: #fdfaf4;
        }

        .ar-preview iframe {
            width: 100%;
            height: 180px;
            border: 0;
            display: block;
        }

        .ar-preview img {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            display: block;
        }

        .ar-preview-empty {
            padding: 12px;
            font-size: 11px;
            color: #948c82;
            text-align: center;
        }

        @media (max-width:900px) {
            .ar-acc-head {
                grid-template-columns: 1fr 42px;
                gap: 8px;
                padding: 12px 14px;
            }

            .ar-acc-head>*:nth-child(2),
            .ar-acc-head>*:nth-child(3),
            .ar-acc-head>*:nth-child(4),
            .ar-acc-head>*:nth-child(5) {
                display: none;
            }

            .ar-acc-body-inner {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width:768px) {
            .ar-page {
                padding: 20px 15px 40px;
            }

            .ar-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .ar-refresh-btn {
                width: 100%;
                justify-content: center;
            }

            .ar-card {
                padding: 17px;
                border-radius: 17px;
            }

            .ar-list-head {
                flex-direction: column;
                align-items: stretch;
            }

            .ar-search {
                width: 100%;
            }

            .ar-pagination {
                flex-direction: column;
                align-items: stretch;
            }

            .ar-pag-controls {
                justify-content: center;
            }
        }

        @media (max-width:600px) {
            .ar-acc-actions {
                flex-direction: column;
            }

            .ar-acc-actions .ar-btn,
            .ar-acc-actions .ar-call,
            .ar-acc-actions .ar-whatsapp {
                width: 100%;
                justify-content: center;
            }
        }

        @keyframes arSpin {
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

        <div class="ar-page">
            <div class="ar-header">
                <div class="ar-title">
                    <h1>Address Requests</h1>
                    <p>Customers requesting to be added to an apartment or division.</p>
                </div>
                <button type="button" class="ar-refresh-btn" id="arRefresh"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
            </div>

            <div class="ar-card">
                <div class="ar-list-head">
                    <div class="ar-list-title">
                        <h3>All Requests</h3>
                        <span>Loaded from the database</span>
                    </div>
                    <div class="ar-search">
                        <i class="bi bi-search"></i>
                        <input type="text" id="arSearch" placeholder="Search name, mobile, apartment...">
                    </div>
                </div>

                <!-- FILTERS -->
                <div class="ar-filters">
                    <div class="ar-fgroup">
                        <label><i class="bi bi-calendar3"></i> Date</label>
                        <select id="arFilterDate">
                            <option value="all">All time</option>
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="7d">Last 7 days</option>
                            <option value="30d">Last 30 days</option>
                            <option value="custom">Custom range…</option>
                        </select>
                    </div>
                    <div class="ar-fgroup" id="arCustomRange">
                        <label>From</label><input type="date" id="arDateFrom">
                        <label>To</label><input type="date" id="arDateTo">
                    </div>
                    <div class="ar-fgroup">
                        <label><i class="bi bi-funnel"></i> Status</label>
                        <select id="arFilterStatus">
                            <option value="all">All</option>
                            <option value="0">Pending</option>
                            <option value="1">Approved</option>
                            <option value="2">Rejected</option>
                        </select>
                    </div>
                </div>

                <div class="ar-acc-list" id="arTbody">
                    <div class="ar-acc-item" style="padding:40px;text-align:center;color:#948c82;border:1.5px dashed #e4ddd3;">
                        <span style="display:inline-block;width:22px;height:22px;border:3px solid #eee7dc;border-top-color:#b51f2c;border-radius:50%;animation:arSpin .7s linear infinite;"></span>
                    </div>
                </div>

                <div class="ar-pagination" id="arPagination" style="display:none;">
                    <div class="ar-pag-left">
                        <div class="ar-pag-info" id="arPagInfo">Showing <strong>0</strong>–<strong>0</strong> of <strong>0</strong></div>
                        <label class="ar-perpage">Show
                            <select id="arPerPage">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select> entries
                        </label>
                    </div>
                    <div class="ar-pag-controls" id="arPagControls"></div>
                </div>
            </div>
        </div>
    </main>

    <div class="ar-toast-wrap" id="arToastWrap"></div>

    <!-- APPROVE MODAL -->
    <div class="ar-modal-overlay" id="arApproveOverlay" aria-hidden="true">
        <div class="ar-modal" role="dialog" aria-modal="true">
            <div class="ar-modal-head">
                <div>
                    <h3>Approve & Create Customer</h3>
                    <p>Edit any detail, then click <strong>Approve</strong>. A customer account will be created.</p>
                </div>
                <button type="button" class="ar-modal-close" id="arApproveClose"><i class="bi bi-x-lg"></i></button>
            </div>

            <form id="arApproveForm">
                <input type="hidden" id="arReqId">
                <div class="ar-modal-body">
                    <div class="ar-modal-row">
                        <div class="ar-modal-field"><label>Customer Name</label><input type="text" id="arName" required maxlength="150"></div>
                        <div class="ar-modal-field"><label>Mobile Number</label><input type="text" id="arMobile" required maxlength="15" inputmode="numeric"></div>
                    </div>
                    <div class="ar-modal-row">
                        <div class="ar-modal-field"><label>Password (leave blank to keep existing)</label><input type="text" id="arPassword" maxlength="100" placeholder="e.g. 123456"></div>
                        <div class="ar-modal-field"><label>Request Type</label>
                            <select id="arReqType">
                                <option value="apartment">Apartment</option>
                                <option value="individual_house">Individual House</option>
                            </select>
                        </div>
                    </div>

                    <!-- ===== APARTMENT BLOCK ===== -->
                    <div id="arAptBlock">
                        <div class="ar-modal-row">
                            <div class="ar-modal-field">
                                <label>Apartment Name</label>
                                <input type="text" id="arApartment" maxlength="200" placeholder="Existing or new apartment name">
                                <p class="ar-modal-hint">If this name doesn't exist, a new apartment will be created.</p>
                            </div>
                            <div class="ar-modal-field">
                                <label>Branch</label>
                                <select id="arBranch">
                                    <option value="">— Select Branch —</option>
                                </select>
                            </div>
                        </div>
                        <div class="ar-modal-field">
                            <label>Apartment Address</label>
                            <textarea id="arApartmentAddress" maxlength="500" placeholder="Full apartment address (only used when creating a new apartment)"></textarea>
                        </div>
                        <div class="ar-modal-row">
                            <div class="ar-modal-field">
                                <label>Division / Block / Door / Floor</label>
                                <input type="text" id="arDivision" maxlength="100" placeholder="e.g. Blk A / Door 101 / Fl 2">
                                <p class="ar-modal-hint">This is added to the apartment's variant list.</p>
                            </div>
                            <div class="ar-modal-field">
                                <label>Delivery Charge (₹)</label>
                                <input type="number" id="arDeliveryCharge" min="0" step="0.01" placeholder="0.00">
                                <p class="ar-modal-hint">Saved per division on the apartment.</p>
                            </div>
                        </div>
                    </div>

                    <!-- ===== HOUSE BLOCK ===== -->
                    <div id="arHouseBlock" style="display:none;">
                        <div class="ar-modal-field">
                            <label>Full Address</label>
                            <textarea id="arHouseAddress" maxlength="250"></textarea>
                        </div>
                        <div class="ar-modal-row">
                            <div class="ar-modal-field">
                                <label>Google Maps Link</label>
                                <input type="text" id="arMapLink" maxlength="600" placeholder="https://maps.google.com/...">
                            </div>
                            <div class="ar-modal-field">
                                <label>Delivery Charge (₹)</label>
                                <input type="number" id="arHouseDeliveryCharge" min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        <div class="ar-modal-field">
                            <label>House Image URL / Path</label>
                            <input type="text" id="arHouseImage" maxlength="600" placeholder="uploads/houses/.../image.jpg">
                            <p class="ar-modal-hint">Leave blank to hide the image preview.</p>
                        </div>
                        <div id="arMapPreview" class="ar-preview" style="display:none;"></div>
                        <div id="arImagePreview" class="ar-preview" style="display:none;"></div>
                    </div>
                </div>

                <div class="ar-modal-actions">
                    <button type="button" class="ar-modal-btn ar-modal-btn-ghost" id="arApproveCancel">Cancel</button>
                    <button type="submit" class="ar-modal-btn ar-modal-btn-primary" id="arApproveSubmit">
                        <i class="bi bi-check-lg"></i> Approve &amp; Save
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
    <script src="<?= ADMIN_URL ?>js/address-requests.js"></script>
</body>

</html>