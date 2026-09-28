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

/* ---------------- BRANCHES ---------------- */
$branches = [];
try {
    $branches = $pdo->query(
        "SELECT id, branch_name FROM settings_branches ORDER BY branch_name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$branchesJson = json_encode($branches, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

    <style>
        .mo-page { padding: 24px 26px 60px; }

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
        .mo-header p { margin: 0; color: #817a71; font-size: 12.5px; }

        .mo-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 20px;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .mo-layout { grid-template-columns: 1fr; }
        }

        /* =====================================================
           CARDS
           ===================================================== */
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

        /* =====================================================
           FORM FIELDS
           ===================================================== */
        .mo-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        @media (max-width: 620px) { .mo-grid-2 { grid-template-columns: 1fr; } }

        .mo-field { margin-bottom: 14px; position: relative; }
        .mo-field:last-child { margin-bottom: 0; }

        .mo-field label {
            display: block;
            font-size: 11px;
            font-weight: 800;
            color: #4e4841;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .mo-field label .req { color: #b51f2c; }

        .mo-input-wrap { position: relative; }

        .mo-input-wrap > i {
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
        .mo-hint.success { color: #1b5e20; }

        /* =====================================================
           SEARCHABLE DROPDOWN
           ===================================================== */
        .sd-wrap { position: relative; }

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
        .sd-toggle:hover { border-color: #d98a91; }
        .sd-wrap.open .sd-toggle {
            border-color: #b51f2c;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(181, 31, 44, .08);
        }
        .sd-toggle > i.lead {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0a79c;
            font-size: 15px;
            pointer-events: none;
        }
        .sd-toggle > i.caret {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0a79c;
            font-size: 12px;
            pointer-events: none;
            transition: transform .2s;
        }
        .sd-wrap.open .sd-toggle > i.caret {
            transform: translateY(-50%) rotate(180deg);
        }
        .sd-toggle .placeholder { color: #b8afa3; font-weight: 500; }
        .sd-toggle.has-value { color: #292521; font-weight: 700; }
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
        .sd-search > i {
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
        .sd-list::-webkit-scrollbar { width: 6px; }
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
        .sd-option.selected i { color: #fff; }
        .sd-option.selected .meta { color: rgba(255,255,255,.75); }

        .sd-option > i {
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

        /* =====================================================
           MODE TOGGLE
           ===================================================== */
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
            position: absolute; opacity: 0; pointer-events: none;
        }
        .mo-mode-radio {
            width: 20px; height: 20px;
            border-radius: 50%;
            border: 2px solid #d5cbbd;
            flex-shrink: 0;
            position: relative;
            transition: .15s ease;
        }
        .mo-mode-option input:checked ~ .mo-mode-radio { border-color: #b51f2c; }
        .mo-mode-option input:checked ~ .mo-mode-radio::after {
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
        .mo-mode-body { flex: 1; min-width: 0; }
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

        /* =====================================================
           PRODUCT PICKER
           ===================================================== */
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
        .mo-tab:hover { color: #b51f2c; }
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
        .mo-search > i {
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
        @media (max-width: 620px) { .mo-products { grid-template-columns: 1fr; } }

        .mo-products::-webkit-scrollbar { width: 6px; }
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
        .mo-prod-thumb img { width: 100%; height: 100%; object-fit: cover; }

        .mo-prod-info { flex: 1; min-width: 0; }
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

        /* =====================================================
           CART
           ===================================================== */
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
        .mo-cart-body::-webkit-scrollbar { width: 6px; }
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
        .mo-cart-item:last-child { margin-bottom: 0; }

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
        .mo-cart-thumb img { width: 100%; height: 100%; object-fit: cover; }

        .mo-cart-info { flex: 1; min-width: 0; }
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
        .mo-cart-meta strong { color: #b51f2c; font-weight: 800; }

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
        .mo-cart-remove:hover { background: #fde6e6; }

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
        .mo-totals-row strong { color: #302923; font-weight: 800; }

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
        .mo-place-btn:hover:not(:disabled) { transform: translateY(-1px); }
        .mo-place-btn:disabled { opacity: .55; cursor: not-allowed; }

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
        .mo-reset-btn:hover { background: #faf7f0; color: #302923; }

        /* =====================================================
           VARIANT MODAL
           ===================================================== */
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
        .mo-modal-overlay.show { opacity: 1; visibility: visible; }

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
        .mo-modal-overlay.show .mo-modal { transform: translateY(0) scale(1); }

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
        .mo-modal-thumb img { width: 100%; height: 100%; object-fit: cover; }

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
        .mo-var:hover { border-color: #d98a91; background: #fff5f5; }
        .mo-var.selected {
            border-color: #b51f2c;
            background: #fff5f5;
        }

        .mo-var-radio {
            width: 20px; height: 20px;
            border-radius: 50%;
            border: 2px solid #d5cbbd;
            flex-shrink: 0;
            position: relative;
        }
        .mo-var.selected .mo-var-radio { border-color: #b51f2c; }
        .mo-var.selected .mo-var-radio::after {
            content: "";
            position: absolute;
            inset: 3px;
            background: #b51f2c;
            border-radius: 50%;
        }

        .mo-var-info { flex: 1; min-width: 0; }
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
        .mo-btn-primary:hover:not(:disabled) { transform: translateY(-1px); }
        .mo-btn-primary:disabled { opacity: .55; cursor: not-allowed; }
        .mo-btn-ghost {
            background: #fff;
            border: 1.5px solid #e4ddd3;
            color: #6f675f;
        }
        .mo-btn-ghost:hover { background: #faf7f0; color: #302923; }

        /* =====================================================
           SUCCESS POPUP
           ===================================================== */
        .mo-popup-overlay {
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
        .mo-popup-overlay.show { opacity: 1; visibility: visible; }

        .mo-popup {
            background: #fff;
            border-radius: 20px;
            padding: 30px 26px 24px;
            max-width: 400px;
            width: 100%;
            text-align: center;
            box-shadow: 0 30px 80px rgba(0, 0, 0, .25);
            transform: translateY(15px) scale(.96);
            transition: transform .25s cubic-bezier(.2, .9, .3, 1.2);
        }
        .mo-popup-overlay.show .mo-popup { transform: translateY(0) scale(1); }

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
        }
        .mo-popup-icon.error {
            background: #fdecec;
            color: #b51f2c;
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

        /* =====================================================
           RESPONSIVE
           ===================================================== */
        @media (max-width: 640px) {
            .mo-page { padding: 18px 14px 40px; }
            .mo-card { padding: 16px; border-radius: 16px; }
            .mo-cart { position: static; }
            .mo-products { max-height: none; }
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
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                    <polyline points="9 22 9 12 15 12 15 22"/>
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
                                    <path d="M3 9l1-5h16l1 5"/>
                                    <path d="M4 9v11h16V9"/>
                                    <path d="M9 22V12h6v10"/>
                                </svg>
                            </label>
                        </div>

                        <!-- Address (delivery) -->
                        <div id="moDeliveryBlock" style="margin-top:16px;">
                            <div class="mo-grid-2">

                                <!-- APARTMENT — SEARCHABLE -->
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

                                <!-- DIVISION — SEARCHABLE -->
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


    <!-- ================= POPUP (success / error) ================= -->
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


    <!-- ================= GLOBALS ================= -->
    <script>
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
        window.BRANCHES = <?= $branchesJson ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL ?>js/main.js"></script>
    <script src="<?= ADMIN_URL ?>js/manual-order-taken.js"></script>

</body>

</html>