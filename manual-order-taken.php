<?php
require_once './config/config.php';
require_once './config/function.php';

/* ---------------- AUTH ---------------- */
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    header('Location: login.php');
    exit;
}

$settings = getSettings($pdo);
$siteName = $settings['username'] ?? 'Mrs Mill@';
$QRlogoUrl = !empty($settings['favicon_image']) ? ADMIN_URL . $settings['favicon_image'] : '';

/* ---------------- BRANCHES ---------------- */
$branches = [];
try {
    $branches = $pdo->query(
        "SELECT id, branch_name FROM settings_branches ORDER BY branch_name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

$branchesJson = json_encode($branches, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

/* Tax settings to expose to JS */
$taxStatus = (int)($settings['tax_status'] ?? 0);
$taxRate   = (float)($settings['tax_rate'] ?? 0);
$taxType   = $settings['tax_type'] ?? 'exclusive';
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

    <style>
        .mo-page {
            padding: 24px 26px 60px;
        }

        .mo-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .mo-header h1 {
            font-family: "Playfair Display", serif;
            font-size: 26px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 4px;
        }

        .mo-header p {
            margin: 0;
            color: #817a71;
            font-size: 12.5px;
        }

        .mo-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 20px;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .mo-layout {
                grid-template-columns: 1fr;
            }
        }

        /* CARDS */
        .mo-card {
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 20px;
            padding: 22px;
            margin-bottom: 20px;
        }

        .mo-card-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: "Playfair Display", serif;
            font-size: 16px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 16px;
        }

        .mo-card-title i {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            background: #fbe8e9;
            color: #b51f2c;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        /* FORM FIELDS */
        .mo-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        @media (max-width: 620px) {
            .mo-grid-2 {
                grid-template-columns: 1fr;
            }
        }

        .mo-field {
            margin-bottom: 14px;
            position: relative;
        }

        .mo-field:last-child {
            margin-bottom: 0;
        }

        .mo-field label {
            display: block;
            font-size: 11px;
            font-weight: 800;
            color: #4e4841;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .mo-field label .req {
            color: #b51f2c;
        }

        .mo-input-wrap {
            position: relative;
        }

        .mo-input-wrap>i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0a79c;
            font-size: 15px;
            pointer-events: none;
        }

        .mo-input,
        .mo-select {
            width: 100%;
            height: 46px;
            border: 1.5px solid #ece5da;
            background: #fffdf9;
            border-radius: 12px;
            padding: 0 14px 0 42px;
            font-family: "DM Sans", sans-serif;
            font-size: 13px;
            font-weight: 600;
            color: #292521;
            outline: none;
            transition: .2s ease;
        }

        .mo-input:focus,
        .mo-select:focus {
            border-color: #b51f2c;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(181, 31, 44, .08);
        }

        .mo-input:disabled {
            opacity: .7;
            background: #f7f2ec;
            cursor: not-allowed;
        }

        .mo-select {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23948c82' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 14px;
            cursor: pointer;
        }

        .mo-hint {
            font-size: 11px;
            color: #948c82;
            margin: 6px 0 0;
            font-weight: 600;
        }

        .mo-hint.success {
            color: #1b5e20;
        }

        /* SEARCHABLE DROPDOWN */
        .sd-wrap {
            position: relative;
        }

        .sd-toggle {
            width: 100%;
            height: 46px;
            border: 1.5px solid #ece5da;
            background: #fffdf9;
            border-radius: 12px;
            padding: 0 40px 0 42px;
            font-family: "DM Sans", sans-serif;
            font-size: 13px;
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
            border-color: #b51f2c;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(181, 31, 44, .08);
        }

        .sd-toggle>i.lead {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0a79c;
            font-size: 15px;
            pointer-events: none;
        }

        .sd-toggle>i.caret {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0a79c;
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
            border: 1.5px solid #ece5da;
            border-radius: 14px;
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
            border: 1.5px solid #ece5da;
            background: #fff;
            border-radius: 9px;
            padding: 0 12px 0 34px;
            font-family: "DM Sans", sans-serif;
            font-size: 12.5px;
            color: #292521;
            outline: none;
            transition: .15s ease;
        }

        .sd-search input:focus {
            border-color: #d98a91;
            background: #fff;
        }

        .sd-search>i {
            position: absolute;
            left: 22px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0a79c;
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
            font-size: 12.5px;
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
            color: #b0a79c;
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
            font-size: 10.5px;
            color: #948c82;
            font-weight: 600;
            white-space: nowrap;
        }

        .sd-empty {
            padding: 18px;
            text-align: center;
            font-size: 12px;
            color: #948c82;
        }

        /* MODE TOGGLE */
        .mo-mode-wrap {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .mo-mode-option {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px;
            border: 1.5px solid #ece5da;
            background: #fffdf9;
            border-radius: 12px;
            cursor: pointer;
            transition: .18s ease;
            user-select: none;
        }

        .mo-mode-option:hover {
            border-color: #d98a91;
            background: #fff5f5;
        }

        .mo-mode-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .mo-mode-radio {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #d5cbbd;
            flex-shrink: 0;
            position: relative;
            transition: .15s ease;
        }

        .mo-mode-option input:checked~.mo-mode-radio {
            border-color: #b51f2c;
        }

        .mo-mode-option input:checked~.mo-mode-radio::after {
            content: "";
            position: absolute;
            inset: 3px;
            background: #b51f2c;
            border-radius: 50%;
        }

        .mo-mode-option:has(input:checked) {
            border-color: #b51f2c;
            background: #fff5f5;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .mo-mode-body {
            flex: 1;
            min-width: 0;
        }

        .mo-mode-title {
            font-size: 12.5px;
            font-weight: 800;
            color: #302923;
            margin: 0;
        }

        .mo-mode-sub {
            font-size: 10.5px;
            color: #948c82;
            margin: 3px 0 0;
            font-weight: 600;
        }

        .mo-mode-option svg.icon {
            width: 16px;
            height: 16px;
            color: #b51f2c;
            flex-shrink: 0;
        }

        /* PRODUCT PICKER */
        .mo-tabs {
            display: flex;
            background: #fdfaf4;
            border: 1.5px solid #ece5da;
            border-radius: 11px;
            padding: 4px;
            gap: 4px;
            margin-bottom: 14px;
        }

        .mo-tab {
            flex: 1;
            border: none;
            background: transparent;
            padding: 9px 14px;
            font-family: "DM Sans", sans-serif;
            font-size: 11.5px;
            font-weight: 700;
            color: #6f675f;
            border-radius: 8px;
            cursor: pointer;
            transition: .18s ease;
        }

        .mo-tab:hover {
            color: #b51f2c;
        }

        .mo-tab.active {
            background: #fff;
            color: #b51f2c;
            box-shadow: 0 4px 12px rgba(48, 41, 35, .08);
        }

        .mo-search {
            position: relative;
            margin-bottom: 14px;
        }

        .mo-search input {
            width: 100%;
            height: 44px;
            border: 1.5px solid #ece5da;
            background: #fffdf9;
            border-radius: 12px;
            padding: 0 14px 0 42px;
            font-family: "DM Sans", sans-serif;
            font-size: 13px;
            color: #292521;
            outline: none;
            transition: .2s ease;
        }

        .mo-search input:focus {
            border-color: #b51f2c;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(181, 31, 44, .08);
        }

        .mo-search>i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0a79c;
            font-size: 15px;
            pointer-events: none;
        }

        .mo-products {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            max-height: 480px;
            overflow-y: auto;
            padding-right: 4px;
        }

        @media (max-width: 620px) {
            .mo-products {
                grid-template-columns: 1fr;
            }
        }

        .mo-products::-webkit-scrollbar {
            width: 6px;
        }

        .mo-products::-webkit-scrollbar-thumb {
            background: #e0d8cd;
            border-radius: 4px;
        }

        .mo-prod {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border: 1.5px solid #ece5da;
            border-radius: 12px;
            background: #fffdf9;
            cursor: pointer;
            transition: .15s ease;
        }

        .mo-prod:hover {
            border-color: #d98a91;
            background: #fff5f5;
            transform: translateY(-1px);
        }

        .mo-prod-thumb {
            width: 48px;
            height: 48px;
            border-radius: 11px;
            background: #f7efe3;
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #b0a79c;
            font-size: 20px;
        }

        .mo-prod-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .mo-prod-info {
            flex: 1;
            min-width: 0;
        }

        .mo-prod-name {
            font-size: 12.5px;
            font-weight: 700;
            color: #302923;
            margin: 0;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .mo-prod-meta {
            font-size: 10.5px;
            color: #948c82;
            margin: 3px 0 0;
            font-weight: 600;
        }

        .mo-prod-price {
            font-family: "Playfair Display", serif;
            font-size: 13px;
            font-weight: 800;
            color: #b51f2c;
            white-space: nowrap;
        }

        .mo-prod-empty {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px 20px;
            color: #948c82;
            font-size: 12px;
        }

        .mo-prod-empty i {
            font-size: 32px;
            color: #ece5da;
            display: block;
            margin-bottom: 8px;
        }

        /* CART */
        .mo-cart {
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 20px;
            overflow: hidden;
            position: sticky;
            top: 84px;
        }

        .mo-cart-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 20px;
            border-bottom: 1.5px solid #f0ebe4;
            background: #fff;
        }

        .mo-cart-head h3 {
            margin: 0;
            font-family: "Playfair Display", serif;
            font-size: 16px;
            font-weight: 700;
            color: #302923;
        }

        .mo-cart-count {
            background: #b51f2c;
            color: #fff;
            font-size: 10.5px;
            font-weight: 800;
            padding: 3px 9px;
            border-radius: 999px;
            letter-spacing: .04em;
        }

        .mo-cart-body {
            max-height: 340px;
            overflow-y: auto;
            padding: 12px;
        }

        .mo-cart-body::-webkit-scrollbar {
            width: 6px;
        }

        .mo-cart-body::-webkit-scrollbar-thumb {
            background: #e0d8cd;
            border-radius: 4px;
        }

        .mo-cart-empty {
            text-align: center;
            padding: 40px 20px;
            color: #948c82;
            font-size: 12px;
        }

        .mo-cart-empty i {
            font-size: 32px;
            color: #ece5da;
            display: block;
            margin-bottom: 10px;
        }

        .mo-cart-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border: 1px solid #f0ebe4;
            border-radius: 11px;
            background: #fffdf9;
            margin-bottom: 8px;
        }

        .mo-cart-item:last-child {
            margin-bottom: 0;
        }

        .mo-cart-thumb {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: #f7efe3;
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #b0a79c;
        }

        .mo-cart-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .mo-cart-info {
            flex: 1;
            min-width: 0;
        }

        .mo-cart-name {
            font-size: 11.5px;
            font-weight: 700;
            color: #302923;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mo-cart-meta {
            font-size: 10.5px;
            color: #948c82;
            margin: 3px 0 0;
            font-weight: 600;
        }

        .mo-cart-meta strong {
            color: #b51f2c;
            font-weight: 800;
        }

        .mo-cart-qty {
            display: flex;
            align-items: center;
            gap: 4px;
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 8px;
            padding: 2px;
        }

        .mo-cart-qty button {
            width: 22px;
            height: 22px;
            border: 0;
            background: transparent;
            color: #6f675f;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
        }

        .mo-cart-qty button:hover {
            background: #fbe8e9;
            color: #b51f2c;
        }

        .mo-cart-qty span {
            font-size: 11px;
            font-weight: 800;
            color: #302923;
            min-width: 18px;
            text-align: center;
        }

        .mo-cart-remove {
            width: 26px;
            height: 26px;
            border: 1.5px solid #f0d6d8;
            background: #fff;
            color: #b51f2c;
            border-radius: 7px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
        }

        .mo-cart-remove:hover {
            background: #fde6e6;
        }

        .mo-cart-foot {
            padding: 16px 18px 18px;
            border-top: 1.5px solid #f0ebe4;
            background: #fdfaf4;
        }

        .mo-totals-row {
            display: flex;
            justify-content: space-between;
            font-size: 12.5px;
            color: #6f675f;
            padding: 4px 0;
            font-weight: 600;
        }

        .mo-totals-row strong {
            color: #302923;
            font-weight: 800;
        }

        .mo-totals-row.grand {
            font-size: 15px;
            margin-top: 8px;
            padding-top: 10px;
            border-top: 1.5px dashed #e4ddd3;
        }

        .mo-totals-row.grand strong {
            color: #b51f2c;
            font-size: 18px;
        }

        .mo-place-btn {
            width: 100%;
            height: 48px;
            margin-top: 14px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
            font-family: "DM Sans", sans-serif;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: .2s ease;
            box-shadow: 0 10px 24px rgba(181, 31, 44, .22);
        }

        .mo-place-btn:hover:not(:disabled) {
            transform: translateY(-1px);
        }

        .mo-place-btn:disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        .mo-reset-btn {
            width: 100%;
            height: 42px;
            margin-top: 8px;
            border: 1.5px solid #e4ddd3;
            border-radius: 12px;
            background: #fff;
            color: #6f675f;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: .15s ease;
        }

        .mo-reset-btn:hover {
            background: #faf7f0;
            color: #302923;
        }

        /* MODALS */
        .mo-modal-overlay {
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
            transition: .22s ease;
        }

        .mo-modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .mo-modal {
            background: #fff;
            border-radius: 20px;
            padding: 24px;
            max-width: 440px;
            width: 100%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 30px 80px rgba(0, 0, 0, .25);
            transform: translateY(15px) scale(.96);
            transition: transform .25s cubic-bezier(.2, .9, .3, 1.2);
        }

        .mo-modal-overlay.show .mo-modal {
            transform: translateY(0) scale(1);
        }

        .mo-modal-head {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
        }

        .mo-modal-thumb {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            background: #f7efe3;
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #b0a79c;
            font-size: 24px;
        }

        .mo-modal-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .mo-modal-head h3 {
            margin: 0 0 3px;
            font-size: 14px;
            font-weight: 800;
            color: #302923;
            line-height: 1.3;
        }

        .mo-modal-head span {
            font-size: 11px;
            color: #948c82;
            font-weight: 600;
        }

        .mo-var-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 320px;
            overflow-y: auto;
        }

        .mo-var {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border: 1.5px solid #ece5da;
            border-radius: 12px;
            cursor: pointer;
            transition: .15s ease;
            background: #fffdf9;
        }

        .mo-var:hover {
            border-color: #d98a91;
            background: #fff5f5;
        }

        .mo-var.selected {
            border-color: #b51f2c;
            background: #fff5f5;
        }

        .mo-var-radio {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #d5cbbd;
            flex-shrink: 0;
            position: relative;
        }

        .mo-var.selected .mo-var-radio {
            border-color: #b51f2c;
        }

        .mo-var.selected .mo-var-radio::after {
            content: "";
            position: absolute;
            inset: 3px;
            background: #b51f2c;
            border-radius: 50%;
        }

        .mo-var-info {
            flex: 1;
            min-width: 0;
        }

        .mo-var-name {
            font-size: 12.5px;
            font-weight: 700;
            color: #302923;
        }

        .mo-var-meta {
            font-size: 10.5px;
            color: #948c82;
            margin-top: 2px;
            font-weight: 600;
        }

        .mo-var-price {
            font-family: "Playfair Display", serif;
            font-size: 14px;
            font-weight: 800;
            color: #b51f2c;
        }

        .mo-modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .mo-btn {
            flex: 1;
            height: 46px;
            border-radius: 12px;
            border: none;
            font-family: "DM Sans", sans-serif;
            font-size: 12.5px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: .2s ease;
        }

        .mo-btn-primary {
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
            box-shadow: 0 8px 20px rgba(181, 31, 44, .22);
        }

        .mo-btn-primary:hover:not(:disabled) {
            transform: translateY(-1px);
        }

        .mo-btn-primary:disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        .mo-btn-ghost {
            background: #fff;
            border: 1.5px solid #e4ddd3;
            color: #6f675f;
        }

        .mo-btn-ghost:hover {
            background: #faf7f0;
            color: #302923;
        }

        /* POPUP */
        .mo-popup-overlay {
            position: fixed;
            inset: 0;
            background: rgba(30, 25, 22, .65);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: .22s ease;
        }

        .mo-popup-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .mo-popup {
            background: #fff;
            border-radius: 22px;
            padding: 30px 26px 24px;
            max-width: 400px;
            width: 100%;
            text-align: center;
            box-shadow: 0 30px 80px rgba(0, 0, 0, .3);
            transform: translateY(15px) scale(.96);
            transition: transform .28s cubic-bezier(.2, .9, .3, 1.2);
            max-height: 92vh;
            overflow-y: auto;
        }

        .mo-popup-overlay.show .mo-popup {
            transform: translateY(0) scale(1);
        }

        .mo-popup-icon {
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

        .mo-popup-icon.error {
            background: #fdecec;
            color: #b51f2c;
        }

        .mo-popup-icon.qr {
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
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

        .mo-popup-title {
            margin: 0 0 8px;
            font-family: "Playfair Display", serif;
            font-size: 19px;
            font-weight: 700;
            color: #302923;
        }

        .mo-popup-text {
            margin: 0 0 20px;
            font-size: 12.5px;
            color: #756d65;
            line-height: 1.6;
        }

        .mo-popup-code {
            display: inline-block;
            background: #faf7f0;
            border: 1px dashed #d8c9b8;
            color: #6f5a3f;
            font-size: 11.5px;
            font-weight: 700;
            padding: 8px 14px;
            border-radius: 8px;
            margin-bottom: 18px;
            letter-spacing: 1.2px;
        }

        .mo-popup-actions {
            display: flex;
            gap: 10px;
        }

        /* WALLET PANEL */
        .mo-wallet-box {
            background: #fdfaf4;
            border: 1.5px solid #ece5da;
            border-radius: 14px;
            padding: 16px 18px;
            margin-top: 16px;
        }

        .mo-wallet-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .mo-wallet-lbl {
            font-size: 10px;
            font-weight: 800;
            color: #948c82;
            text-transform: uppercase;
            letter-spacing: .07em;
            margin-bottom: 4px;
        }

        .mo-wallet-val {
            font-family: "Playfair Display", serif;
            font-size: 22px;
            font-weight: 700;
            color: #b51f2c;
            line-height: 1.1;
        }

        .mo-wallet-cust {
            font-size: 12.5px;
            font-weight: 800;
            color: #302923;
            text-align: right;
        }

        .mo-wallet-status {
            margin-top: 12px;
            padding: 11px 14px;
            border-radius: 11px;
            font-size: 12px;
            font-weight: 700;
            display: none;
            align-items: center;
            gap: 8px;
        }

        .mo-wallet-status.info {
            background: #f4efe8;
            color: #6f5a3f;
            display: flex;
        }

        .mo-wallet-status.success {
            background: #e8f6ea;
            color: #1b5e20;
            display: flex;
        }

        .mo-wallet-status.error {
            background: #fdecec;
            color: #b51f2c;
            display: flex;
        }

        /* TAX ROW */
        .mo-totals-row.tax-row strong {
            color: #b8893c;
        }

        @media (max-width: 640px) {
            .mo-page {
                padding: 18px 14px 40px;
            }

            .mo-card {
                padding: 16px;
                border-radius: 16px;
            }

            .mo-cart {
                position: static;
            }

            .mo-products {
                max-height: none;
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
                    <div class="admin-avatar">A</div>
                    <div>
                        <div class="admin-name">Admin</div>
                        <div class="admin-role">Store Manager</div>
                    </div>
                </div>
            </div>
        </header>


        <div class="mo-page">

            <div class="mo-header">
                <div>
                    <h1>Create Manual Order</h1>
                    <p>Place an order on behalf of a customer.</p>
                </div>
            </div>


            <div class="mo-layout">

                <!-- ================= LEFT ================= -->
                <div>

                    <!-- CUSTOMER -->
                    <div class="mo-card">
                        <h2 class="mo-card-title">
                            <i class="bi bi-person-badge"></i>
                            Customer
                        </h2>

                        <div class="mo-grid-2">
                            <div class="mo-field">
                                <label>Mobile Number <span class="req">*</span></label>
                                <div class="mo-input-wrap">
                                    <i class="bi bi-telephone"></i>
                                    <input type="tel" id="moMobile" class="mo-input"
                                        placeholder="10-digit mobile" maxlength="15" inputmode="numeric">
                                </div>
                                <p class="mo-hint" id="moMobileHint">Type a mobile to auto-fill the customer.</p>
                            </div>

                            <div class="mo-field">
                                <label>Full Name <span class="req">*</span></label>
                                <div class="mo-input-wrap">
                                    <i class="bi bi-person"></i>
                                    <input type="text" id="moName" class="mo-input"
                                        placeholder="Customer name" maxlength="150">
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- DELIVERY MODE -->
                    <div class="mo-card">
                        <h2 class="mo-card-title">
                            <i class="bi bi-truck"></i>
                            Order Mode
                        </h2>

                        <div class="mo-mode-wrap">
                            <label class="mo-mode-option">
                                <input type="radio" name="moMode" value="delivery" checked>
                                <span class="mo-mode-radio"></span>
                                <div class="mo-mode-body">
                                    <p class="mo-mode-title">Home Delivery</p>
                                    <p class="mo-mode-sub">Send to an apartment</p>
                                </div>
                                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                                    <polyline points="9 22 9 12 15 12 15 22" />
                                </svg>
                            </label>

                            <label class="mo-mode-option">
                                <input type="radio" name="moMode" value="pickup">
                                <span class="mo-mode-radio"></span>
                                <div class="mo-mode-body">
                                    <p class="mo-mode-title">Store Pickup</p>
                                    <p class="mo-mode-sub">Pick from a branch</p>
                                </div>
                                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 9l1-5h16l1 5" />
                                    <path d="M4 9v11h16V9" />
                                    <path d="M9 22V12h6v10" />
                                </svg>
                            </label>
                        </div>

                        <!-- Address (delivery) -->
                        <div id="moDeliveryBlock" style="margin-top:16px;">
                            <div class="mo-grid-2">

                                <div class="mo-field">
                                    <label>Apartment <span class="req">*</span></label>

                                    <div class="sd-wrap" id="moAptDdWrap">
                                        <button type="button" class="sd-toggle" id="moAptDdToggle">
                                            <i class="bi bi-building lead"></i>
                                            <span class="placeholder" id="moAptDdLabel">— Select apartment —</span>
                                            <i class="bi bi-chevron-down caret"></i>
                                        </button>

                                        <div class="sd-menu">
                                            <div class="sd-search">
                                                <i class="bi bi-search"></i>
                                                <input type="text" id="moAptDdSearch"
                                                    placeholder="Search apartment..." autocomplete="off">
                                            </div>
                                            <div class="sd-list" id="moAptDdList"></div>
                                        </div>
                                    </div>

                                    <input type="hidden" id="moApartmentId">
                                    <input type="hidden" id="moApartmentCode">
                                </div>

                                <div class="mo-field">
                                    <label>Division <span class="req">*</span></label>

                                    <div class="sd-wrap" id="moDivDdWrap">
                                        <button type="button" class="sd-toggle" id="moDivDdToggle" disabled>
                                            <i class="bi bi-grid-3x3-gap lead"></i>
                                            <span class="placeholder" id="moDivDdLabel">Select apartment first</span>
                                            <i class="bi bi-chevron-down caret"></i>
                                        </button>

                                        <div class="sd-menu">
                                            <div class="sd-search">
                                                <i class="bi bi-search"></i>
                                                <input type="text" id="moDivDdSearch"
                                                    placeholder="Search division..." autocomplete="off">
                                            </div>
                                            <div class="sd-list" id="moDivDdList"></div>
                                        </div>
                                    </div>

                                    <input type="hidden" id="moDivision">
                                    <input type="hidden" id="moDivisionCharge" value="0">

                                    <p class="mo-hint" id="moDivisionHint"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Branch (pickup) -->
                        <div id="moPickupBlock" style="margin-top:16px;display:none;">
                            <div class="mo-field">
                                <label>Pickup Branch <span class="req">*</span></label>
                                <div class="mo-input-wrap">
                                    <i class="bi bi-shop"></i>
                                    <select id="moBranch" class="mo-select">
                                        <option value="">— Select branch —</option>
                                        <?php foreach ($branches as $b): ?>
                                            <option value="<?= (int)$b['id'] ?>">
                                                <?= htmlspecialchars($b['branch_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- PAYMENT METHOD -->
                    <div class="mo-card">
                        <h2 class="mo-card-title">
                            <i class="bi bi-credit-card"></i>
                            Payment Method
                        </h2>

                        <div class="mo-mode-wrap">
                            <label class="mo-mode-option">
                                <input type="radio" name="moPayMode" value="qr" checked>
                                <span class="mo-mode-radio"></span>
                                <div class="mo-mode-body">
                                    <p class="mo-mode-title">QR Payment</p>
                                    <p class="mo-mode-sub">Customer scans &amp; pays</p>
                                </div>
                                <i class="bi bi-qr-code" style="font-size:18px;color:#b51f2c;"></i>
                            </label>

                            <label class="mo-mode-option">
                                <input type="radio" name="moPayMode" value="wallet">
                                <span class="mo-mode-radio"></span>
                                <div class="mo-mode-body">
                                    <p class="mo-mode-title">Wallet Payment</p>
                                    <p class="mo-mode-sub">Deduct from wallet</p>
                                </div>
                                <i class="bi bi-wallet2" style="font-size:18px;color:#b51f2c;"></i>
                            </label>
                        </div>

                        <!-- WALLET PANEL -->
                        <div class="mo-wallet-box" id="moWalletPanel" style="display:none;">
                            <div class="mo-wallet-row">
                                <div>
                                    <div class="mo-wallet-lbl">Wallet Balance</div>
                                    <div class="mo-wallet-val" id="moWalletBalance">₹0</div>
                                </div>
                                <div>
                                    <div class="mo-wallet-lbl" style="text-align:right;">Customer</div>
                                    <div class="mo-wallet-cust" id="moWalletCust">—</div>
                                </div>
                            </div>

                            <div class="mo-wallet-status" id="moWalletStatus"></div>
                        </div>
                    </div>


                    <!-- PRODUCTS -->
                    <div class="mo-card">
                        <h2 class="mo-card-title">
                            <i class="bi bi-box-seam"></i>
                            Add Products
                        </h2>

                        <div class="mo-tabs">
                            <button type="button" class="mo-tab active" data-tab="menu">Menu Products</button>
                            <button type="button" class="mo-tab" data-tab="all">All Products</button>
                        </div>

                        <div class="mo-search">
                            <i class="bi bi-search"></i>
                            <input type="text" id="moProductSearch" placeholder="Search product..." autocomplete="off">
                        </div>

                        <div class="mo-products" id="moProducts">
                            <div class="mo-prod-empty">
                                <i class="bi bi-hourglass-split"></i>
                                Loading products...
                            </div>
                        </div>
                    </div>

                </div>


                <!-- ================= RIGHT ================= -->
                <aside class="mo-cart">

                    <div class="mo-cart-head">
                        <h3>Order Summary</h3>
                        <span class="mo-cart-count" id="moCartCount">0</span>
                    </div>

                    <div class="mo-cart-body" id="moCartList">
                        <div class="mo-cart-empty">
                            <i class="bi bi-basket"></i>
                            No products added yet.
                        </div>
                    </div>

                    <div class="mo-cart-foot">
                        <div class="mo-totals-row">
                            <span>Subtotal</span>
                            <strong id="moSubtotal">₹0</strong>
                        </div>
                        <div class="mo-totals-row">
                            <span id="moChargeLabel">Delivery charge</span>
                            <strong id="moCharge">₹0</strong>
                        </div>

                        <!-- Tax row (only shown when tax enabled) -->
                        <div class="mo-totals-row tax-row" id="moTaxRow" style="display:none;">
                            <span id="moTaxLabel">Tax</span>
                            <strong id="moTax">₹0</strong>
                        </div>

                        <div class="mo-totals-row grand">
                            <span>Total</span>
                            <strong id="moTotal">₹0</strong>
                        </div>

                        <button type="button" class="mo-place-btn" id="moPlaceBtn" disabled>
                            <i class="bi bi-check-lg"></i>
                            Place Order
                        </button>

                        <button type="button" class="mo-reset-btn" id="moResetBtn">
                            <i class="bi bi-arrow-counterclockwise"></i>
                            Reset
                        </button>
                    </div>

                </aside>

            </div>

        </div>

    </main>


    <!-- ================= VARIANT MODAL ================= -->
    <div class="mo-modal-overlay" id="moVarOverlay" aria-hidden="true">
        <div class="mo-modal">
            <div class="mo-modal-head">
                <div class="mo-modal-thumb" id="moVarThumb">
                    <i class="bi bi-box"></i>
                </div>
                <div>
                    <h3 id="moVarName">Product</h3>
                    <span id="moVarCode">#PRD000</span>
                </div>
            </div>

            <div class="mo-var-list" id="moVarList"></div>

            <div class="mo-modal-actions">
                <button type="button" class="mo-btn mo-btn-ghost" id="moVarCancel">Cancel</button>
                <button type="button" class="mo-btn mo-btn-primary" id="moVarAdd">
                    <i class="bi bi-cart-plus"></i> Add to Order
                </button>
            </div>
        </div>
    </div>


    <!-- ================= SUCCESS / ERROR POPUP ================= -->
    <div class="mo-popup-overlay" id="moPopupOverlay" aria-hidden="true">
        <div class="mo-popup">
            <div class="mo-popup-icon" id="moPopupIcon">
                <i class="bi bi-check-lg"></i>
            </div>
            <h3 class="mo-popup-title" id="moPopupTitle">Order Placed!</h3>
            <p class="mo-popup-text" id="moPopupText">The order has been created successfully.</p>
            <div class="mo-popup-code" id="moPopupCode" style="display:none;"></div>
            <div class="mo-popup-actions">
                <a href="<?= ADMIN_URL ?>orders.php" class="mo-btn mo-btn-primary">
                    <i class="bi bi-list-ul"></i> View Orders
                </a>
                <button type="button" class="mo-btn mo-btn-ghost" id="moPopupClose">
                    <i class="bi bi-x-lg"></i> Close
                </button>
            </div>
        </div>
    </div>


    <!-- ================= QR PAYMENT POPUP ================= -->
    <div class="mo-popup-overlay" id="moQrOverlay" aria-hidden="true">
        <div class="mo-popup" style="max-width:420px;">
            <div class="mo-popup-icon qr">
                <i class="bi bi-qr-code"></i>
            </div>
            <h3 class="mo-popup-title">Scan &amp; Pay</h3>
            <p class="mo-popup-text" style="margin-bottom:14px;">
                Ask the customer to scan this QR to pay
            </p>

            <div class="mo-popup-code" id="moQrCode" style="display:inline-block;margin-bottom:14px;">ORDER</div>

            <div style="font-family:'DM Sans',sans-serif;font-size:32px;font-weight:800;color:#b51f2c;margin-bottom:16px;line-height:1;letter-spacing:-.5px;" id="moQrAmount">
                ₹0.00
            </div>

            <div style="width:220px;height:220px;margin:0 auto 16px;background:#fff;padding:12px;border:2px solid #ece5da;border-radius:16px;box-shadow:0 10px 30px rgba(48,41,35,.1);display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;" id="moQrBox">
                <div id="moQrLogoHolder" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:52px;height:52px;background:#fff;border-radius:12px;display:flex;align-items:center;justify-content:center;padding:5px;box-shadow:0 2px 10px rgba(0,0,0,.1);border:2px solid #fff;z-index:3;pointer-events:none;">
                    <?php if ($QRlogoUrl): ?>
                        <img src="<?= htmlspecialchars($QRlogoUrl) ?>" alt="Logo" style="max-width:100%;max-height:100%;object-fit:contain;border-radius:8px;display:block;">
                    <?php else: ?>
                        <span style="color:#b51f2c;font-family:'Playfair Display',serif;font-weight:700;font-size:24px;">M</span>
                    <?php endif; ?>
                </div>
            </div>

            <div style="background:#fff7e7;border:1px solid #f3dca5;color:#8a6a1e;font-size:11.5px;font-weight:600;padding:10px 14px;border-radius:10px;margin-bottom:16px;line-height:1.5;text-align:left;">
                <i class="bi bi-info-circle"></i>
                Once paid, tap <strong>Confirm Payment</strong> to mark this order as paid.
            </div>

            <div class="mo-popup-actions">
                <button type="button" class="mo-btn mo-btn-ghost" id="moQrCancel">
                    <i class="bi bi-x-lg"></i> Cancel
                </button>
                <button type="button" class="mo-btn mo-btn-primary" id="moQrConfirm">
                    <i class="bi bi-check-lg"></i>
                    <span id="moQrConfirmText">Confirm Payment</span>
                </button>
            </div>
        </div>
    </div>


    <!-- ================= GLOBALS ================= -->
    <script>
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
        window.BRANCHES = <?= $branchesJson ?>;
        window.QR_LOGO_URL = "<?= htmlspecialchars($QRlogoUrl, ENT_QUOTES) ?>";
        window.TAX_SETTINGS = {
            status: <?= (int)$taxStatus ?>,
            rate: <?= (float)$taxRate ?>,
            type: "<?= htmlspecialchars($taxType) ?>"
        };
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL ?>js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script src="<?= ADMIN_URL ?>js/manual-order-taken.js"></script>
<script>
    
    </script>
</body>

</html>