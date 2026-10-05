<?php
require_once './config/config.php';
require_once './config/function.php';

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    header('Location: login.php');
    exit;
}
$isAdmin = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin');
$settings = getSettings($pdo);

/* Customer ID */
$customerId = (int)($_GET['id'] ?? 0);
if ($customerId <= 0) {
    header('Location: customers.php');
    exit;
}

/* Fetch customer */
$customer = null;
try {
    $stmt = $pdo->prepare(
        "SELECT id, full_name, mobile_number, apartment_id, apartment_code,
                apartment_name, division, division_charge, wallet_balance,
                profile_completed, status, last_login_at, created_at, updated_at
         FROM customers WHERE id = ? LIMIT 1"
    );
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

if (!$customer) {
    header('Location: customers.php');
    exit;
}

/* Initials */
$parts = preg_split('/\s+/', trim($customer['full_name']));
$initials = strtoupper(
    count($parts) >= 2
        ? $parts[0][0] . $parts[1][0]
        : substr($customer['full_name'], 0, 2)
);
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

    <style>
        /* =====================================================
           CUSTOMER EDIT PAGE
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
            flex-wrap: wrap;
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

        .cust-chip {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            border: 1px solid #eee7dc;
            border-radius: 12px;
            padding: 8px 14px;
        }

        .cust-chip .avatar {
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

        .cust-chip .name {
            font-size: 12.5px;
            font-weight: 800;
            color: #302923;
        }

        .cust-chip .mobile {
            font-size: 10.5px;
            color: #948c82;
            font-weight: 600;
            margin-top: 2px;
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

        /* Tabs */
        .tabs-wrap {
            display: flex;
            gap: 4px;
            background: #fff;
            border: 1px solid #eee7dc;
            border-radius: 14px;
            padding: 5px;
            margin-bottom: 20px;
            max-width: 1000px;
        }

        .tab-btn {
            flex: 1;
            height: 44px;
            border-radius: 10px;
            border: none;
            background: transparent;
            font-family: "DM Sans", sans-serif;
            font-size: 12.5px;
            font-weight: 800;
            color: #817a71;
            cursor: pointer;
            transition: .18s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }

        .tab-btn:hover {
            color: #302923;
            background: #faf7f0;
        }

        .tab-btn.active {
            background: #b51f2c;
            color: #fff;
            box-shadow: 0 6px 16px rgba(181, 31, 44, .22);
        }

        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
            animation: tabFade .25s ease;
        }

        @keyframes tabFade {
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Card */
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
            color: #302923;
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

        /* Searchable dropdown */
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

        /* Form actions */
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid #f0ebe4;
            flex-wrap: wrap;
        }

        .actions-right {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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

        .btn-delete {
            border: 1px solid #f5c0c0;
            background: #fff;
            color: #c62828;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: .2s;
        }

        .btn-delete:hover {
            background: #fde6e6;
        }

        /* Wallet tab */
        .wallet-balance-box {
            background: linear-gradient(135deg, #fdfaf4 0%, #fff5f5 100%);
            border: 1px solid #ece5da;
            border-radius: 16px;
            padding: 20px 22px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .wallet-balance-box .label {
            font-size: 10px;
            font-weight: 800;
            color: #948c82;
            text-transform: uppercase;
            letter-spacing: .07em;
        }

        .wallet-balance-box .value {
            font-family: "Playfair Display", serif;
            font-size: 28px;
            font-weight: 700;
            color: #b51f2c;
            margin-top: 4px;
            line-height: 1.1;
        }

        .wallet-balance-box .side {
            text-align: right;
        }

        .wallet-balance-box .side .name {
            font-size: 13px;
            font-weight: 800;
            color: #302923;
            margin-top: 4px;
        }

        .wallet-balance-box .side .mobile {
            font-size: 11px;
            color: #948c82;
            font-weight: 600;
            margin-top: 2px;
        }

        .wallet-card {
            background: #fffdf9;
            border: 1px solid #ece5da;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 22px;
        }

        .wallet-card h4 {
            margin: 0 0 14px;
            font-size: 13px;
            font-weight: 800;
            color: #302923;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .wallet-card h4 i {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: #fbe8e9;
            color: #b51f2c;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
        }

        .wallet-tabs {
            display: flex;
            gap: 4px;
            background: #faf7f0;
            border-radius: 10px;
            padding: 4px;
            margin-bottom: 16px;
        }

        .wallet-tab {
            flex: 1;
            height: 38px;
            border-radius: 8px;
            border: none;
            background: transparent;
            font-family: "DM Sans", sans-serif;
            font-size: 11.5px;
            font-weight: 800;
            color: #817a71;
            cursor: pointer;
            transition: .15s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .wallet-tab.active {
            background: #fff;
            color: #302923;
            box-shadow: 0 2px 8px rgba(48, 41, 35, .06);
        }

        .wallet-tab.credit.active {
            color: #1b5e20;
        }

        .wallet-tab.debit.active {
            color: #b51f2c;
        }

        .wallet-presets {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .wallet-preset {
            height: 30px;
            padding: 0 12px;
            border-radius: 8px;
            border: 1px solid #e8e1d8;
            background: #fff;
            font-family: "DM Sans", sans-serif;
            font-size: 11px;
            font-weight: 800;
            color: #6f675f;
            cursor: pointer;
            transition: .15s ease;
        }

        .wallet-preset:hover {
            background: #fbe8e9;
            border-color: #f1c8cc;
            color: #b51f2c;
        }

        .wallet-submit-btn {
            width: 38%;
            height: 44px;
            border-radius: 11px;
            border: none;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            transition: .2s ease;
            margin-top: 14px;
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
            box-shadow: 0 8px 20px rgba(181, 31, 44, .22);
        }
#walletForm {
    width: 100%;
    display: flex;
    flex-direction: column;
}

#walletForm .wallet-submit-btn {
    align-self: flex-end;
}
        .wallet-submit-btn:hover:not(:disabled) {
            transform: translateY(-1px);
        }

        .wallet-submit-btn:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        /* History section */
        .history-section {
            background: #fff;
            border: 1px solid #eee7dc;
            border-radius: 16px;
            overflow: hidden;
        }

        .history-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 20px;
            border-bottom: 1px solid #f0ebe4;
            background: #fdfaf4;
            flex-wrap: wrap;
        }

        .history-header h4 {
            margin: 0;
            font-size: 13px;
            font-weight: 800;
            color: #302923;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .history-header h4 i {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: #fbe8e9;
            color: #b51f2c;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
        }

        .history-refresh {
            height: 32px;
            padding: 0 12px;
            border-radius: 8px;
            border: 1px solid #e8e1d8;
            background: #fff;
            color: #6f675f;
            font-family: "DM Sans", sans-serif;
            font-size: 10.5px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: .15s ease;
        }

        .history-refresh:hover {
            background: #fbe8e9;
            border-color: #f1c8cc;
            color: #b51f2c;
        }

        .history-summary {
            display: flex;
            gap: 10px;
            padding: 14px 20px;
            background: #fdfaf4;
            border-bottom: 1px solid #f0ebe4;
            flex-wrap: wrap;
        }

        .history-summary .item {
            flex: 1;
            min-width: 100px;
        }

        .history-summary .item .lbl {
            font-size: 9.5px;
            font-weight: 800;
            color: #948c82;
            text-transform: uppercase;
            letter-spacing: .07em;
        }

        .history-summary .item .val {
            font-family: "Playfair Display", serif;
            font-size: 16px;
            font-weight: 700;
            margin-top: 2px;
        }

        .history-summary .item .val.credit {
            color: #1b5e20;
        }

        .history-summary .item .val.debit {
            color: #b51f2c;
        }

        .history-summary .item .val.neutral {
            color: #302923;
        }

        .history-table-wrap {
            max-height: 420px;
            overflow-y: auto;
        }

        .history-table-wrap::-webkit-scrollbar {
            width: 6px;
        }

        .history-table-wrap::-webkit-scrollbar-thumb {
            background: #e0d8cd;
            border-radius: 4px;
        }

        .wallet-history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .wallet-history-table th {
            background: #faf7f0;
            color: #938a80;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .7px;
            padding: 10px 14px;
            border-bottom: 1px solid #eee7dc;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 2;
        }

        .wallet-history-table td {
            padding: 11px 14px;
            border-bottom: 1px solid #f2ede5;
            font-size: 11.5px;
            color: #4c4640;
            vertical-align: middle;
        }

        .wallet-history-table tr:last-child td {
            border-bottom: 0;
        }

        .wallet-txn-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 9.5px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .wallet-txn-badge.credit {
            background: #e8f1e8;
            color: #52745b;
        }

        .wallet-txn-badge.debit {
            background: #fbeaea;
            color: #b51f2c;
        }

        .wallet-amount-credit {
            color: #1b5e20;
            font-weight: 800;
        }

        .wallet-amount-debit {
            color: #b51f2c;
            font-weight: 800;
        }

        .wallet-balance-cell {
            font-family: "Playfair Display", serif;
            font-weight: 700;
            color: #302923;
        }

        /* Pagination */
        .hx-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 20px;
            border-top: 1px solid #f0ebe4;
            background: #fdfaf4;
            flex-wrap: wrap;
        }

        .hx-pagination-info {
            font-size: 11px;
            color: #817a71;
        }

        .hx-pagination-info strong {
            color: #302923;
            font-weight: 800;
        }

        .hx-pagination-controls {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
        }

        .hx-page-btn {
            min-width: 32px;
            height: 32px;
            padding: 0 9px;
            border-radius: 8px;
            border: 1px solid #e8e1d8;
            background: #fff;
            color: #6f675f;
            font-family: "DM Sans", sans-serif;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: .15s ease;
        }

        .hx-page-btn:hover:not(:disabled):not(.active) {
            background: #fbe8e9;
            border-color: #f1c8cc;
            color: #b51f2c;
        }

        .hx-page-btn.active {
            background: #b51f2c;
            border-color: #b51f2c;
            color: #fff;
            cursor: default;
            box-shadow: 0 4px 10px rgba(181, 31, 44, .22);
        }

        .hx-page-btn:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .hx-page-ellipsis {
            min-width: 22px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #b5aca2;
            font-size: 12px;
            font-weight: 700;
        }

        .history-empty {
            padding: 50px 20px;
            text-align: center;
            color: #948c82;
            font-size: 12px;
        }

        .history-empty i {
            font-size: 40px;
            color: #d5cbbd;
            display: block;
            margin-bottom: 10px;
        }

        .history-empty h4 {
            font-family: "Playfair Display", serif;
            font-size: 15px;
            color: #6f675f;
            margin: 0 0 5px;
            font-weight: 700;
        }

        .history-empty p {
            margin: 0;
            font-size: 11px;
        }

        /* =====================================================
           POPUPS (success, error, delete, toast)
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

        .mm-modal-icon-info {
            background: #e6f0fd;
            color: #3b82f6;
        }

        .mm-modal-icon-success {
            background: #e8f6ea;
            color: #2e7d32;
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

        /* Responsive */
        @media (max-width: 768px) {
            .customer-page {
                padding: 20px 15px 30px;
            }

            .customer-header {
                flex-direction: column;
                align-items: stretch;
                gap: 14px;
            }

            .customer-header>div:last-child {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
                align-items: center;
            }

            .cust-chip {
                flex: 1;
                min-width: 0;
            }

            .back-btn {
                flex-shrink: 0;
            }

            .customer-form-card {
                padding: 17px;
                border-radius: 17px;
            }

            .form-card-header {
                margin-bottom: 18px;
            }

            .form-card-icon {
                width: 38px;
                height: 38px;
                font-size: 16px;
            }

            .form-card-header h3 {
                font-size: 15px;
            }

            .tabs-wrap {
                flex-direction: row;
                padding: 4px;
            }

            .tab-btn {
                height: 40px;
                font-size: 11.5px;
                padding: 0 8px;
            }

            .tab-btn i {
                font-size: 13px;
            }

            .form-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .form-actions>div:first-child {
                order: 2;
            }

            .actions-right {
                flex-direction: row;
                justify-content: flex-end;
                flex-wrap: wrap;
                gap: 8px;
            }

            .btn-cancel,
            .btn-save,
            .btn-delete {
                width: auto;
                padding: 10px 16px;
                font-size: 11px;
            }

            .wallet-balance-box {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
                padding: 16px;
            }

            .wallet-balance-box .side {
                text-align: left;
            }

            .wallet-balance-box .value {
                font-size: 24px;
            }

            .wallet-card {
                padding: 16px;
            }

            .wallet-card h4 {
                font-size: 12.5px;
            }

            .wallet-tab {
                font-size: 11px;
                height: 36px;
            }

            .wallet-preset {
                font-size: 10.5px;
                height: 28px;
                padding: 0 10px;
            }

            .wallet-submit-btn {
                font-size: 11.5px;
            }

            .history-header {
                padding: 14px 16px;
            }

            .history-header h4 {
                font-size: 12.5px;
            }

            .history-summary {
                padding: 12px 16px;
                gap: 8px;
            }

            .history-summary .item {
                min-width: 80px;
            }

            .history-summary .item .val {
                font-size: 14px;
            }

            .wallet-history-table th,
            .wallet-history-table td {
                padding: 9px 10px;
                font-size: 10.5px;
            }

            .hx-pagination {
                padding: 10px 16px;
                gap: 8px;
            }

            .hx-page-btn {
                min-width: 30px;
                height: 30px;
                font-size: 10.5px;
                padding: 0 7px;
            }
        }

        @media (max-width: 480px) {
            .customer-page {
                padding: 16px 12px 30px;
            }

            .customer-form-card {
                padding: 14px;
            }

            .customer-title h1 {
                font-size: 22px;
            }

            .customer-title p {
                font-size: 11.5px;
            }

            .cust-chip {
                padding: 6px 10px;
            }

            .cust-chip .avatar {
                width: 32px;
                height: 32px;
                font-size: 11px;
            }

            .cust-chip .name {
                font-size: 11.5px;
            }

            .cust-chip .mobile {
                font-size: 10px;
            }

            .back-btn {
                padding: 8px 12px;
                font-size: 10.5px;
            }

            .back-btn i {
                font-size: 12px;
            }

            .tab-btn {
                height: 36px;
                font-size: 10.5px;
                gap: 4px;
            }

            .tab-btn i {
                font-size: 12px;
            }

            .btn-cancel,
            .btn-save,
            .btn-delete {
                padding: 9px 14px;
                font-size: 10.5px;
                gap: 5px;
            }

            .wallet-history-table th:nth-child(5),
            .wallet-history-table td:nth-child(5) {
                display: none;
            }

            .wallet-history-table th,
            .wallet-history-table td {
                padding: 8px 8px;
                font-size: 10px;
            }

            .hx-page-btn {
                min-width: 28px;
                height: 28px;
                font-size: 10px;
                padding: 0 6px;
            }

            .hx-pagination-controls {
                gap: 3px;
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
                <input type="text" placeholder="Search orders, products...">
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

            <div class="customer-header">
                <div class="customer-title">
                    <h1>Edit Customer</h1>
                    <p>Update customer details and manage wallet balance.</p>
                </div>

                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <div class="cust-chip">
                        <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                        <div>
                            <div class="name"><?= htmlspecialchars($customer['full_name']) ?></div>
                            <div class="mobile"><?= htmlspecialchars($customer['mobile_number']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABS -->
            <div class="tabs-wrap">
                <button type="button" class="tab-btn active" data-tab="details">
                    <i class="bi bi-person-gear"></i> Customer Details
                </button>
                <button type="button" class="tab-btn" data-tab="wallet">
                    <i class="bi bi-wallet2"></i> Wallet
                </button>
            </div>

            <!-- TAB 1 — DETAILS -->
            <div class="tab-panel active" id="panel-details">
                <div class="customer-form-card">
                    <div class="form-card-header">
                        <div class="form-card-icon"><i class="bi bi-person-gear"></i></div>
                        <div>
                            <h3>Customer Details</h3>
                            <span>Modify the customer's information below.</span>
                        </div>
                    </div>

                    <form id="customerForm" class="customer-form" method="POST" novalidate>
                        <input type="hidden" name="id" value="<?= (int)$customer['id'] ?>">

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label>Full Name <span class="required">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-person"></i>
                                    <input type="text" name="full_name" id="full_name"
                                        class="customer-input"
                                        value="<?= htmlspecialchars($customer['full_name']) ?>"
                                        maxlength="150" required>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label>Mobile Number <span class="required">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-telephone"></i>
                                    <input type="tel" name="mobile_number" id="mobile_number"
                                        class="customer-input"
                                        value="<?= htmlspecialchars($customer['mobile_number']) ?>"
                                        maxlength="15" inputmode="numeric" required>
                                </div>
                                <p class="field-help success" id="mobileHelp">Looks good.</p>
                            </div>

                            <div class="col-12 col-md-6">
                                <label>New Password
                                    <span style="font-size:9px;color:#948c82;font-weight:600;text-transform:none;letter-spacing:0;">
                                        (leave blank to keep current)
                                    </span>
                                </label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-key"></i>
                                    <input type="text" name="password" id="password"
                                        class="customer-input"
                                        placeholder="Enter new password to change"
                                        minlength="3">
                                </div>
                                <p class="field-help" id="passwordHelp">Leave blank to keep the current password.</p>
                            </div>

                            <div class="col-12 col-md-6">
                                <label>Status</label>
                                <select name="status" id="status" class="customer-select">
                                    <option value="1" <?= (int)$customer['status'] === 1 ? 'selected' : '' ?>>Active</option>
                                    <option value="0" <?= (int)$customer['status'] === 0 ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                        </div>

                        <!-- ADDRESS -->
                        <div style="margin-top:22px;">
                            <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:14px;">
                                <i class="bi bi-house-door" style="width:34px;height:34px;border-radius:10px;background:#fbe8e9;color:#b51f2c;display:inline-flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;margin-top:2px;"></i>
                                <div>
                                    <h4 style="margin:0;font-size:13px;font-weight:800;color:#302923;">Address & Division</h4>
                                    <span style="display:block;font-size:10px;color:#817a71;margin-top:2px;">Type to search apartment and division.</span>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label>Apartment</label>
                                    <div class="sd-wrap" id="acAptDdWrap">
                                        <button type="button" class="sd-toggle <?= !empty($customer['apartment_name']) ? 'has-value' : '' ?>" id="acAptDdToggle">
                                            <i class="bi bi-building lead"></i>
                                            <span class="<?= !empty($customer['apartment_name']) ? '' : 'placeholder' ?>" id="acAptDdLabel">
                                                <?= !empty($customer['apartment_name'])
                                                    ? htmlspecialchars($customer['apartment_name'])
                                                    : '— Type or select apartment —' ?>
                                            </span>
                                            <i class="bi bi-chevron-down caret"></i>
                                        </button>
                                        <div class="sd-menu">
                                            <div class="sd-search">
                                                <i class="bi bi-search"></i>
                                                <input type="text" id="acAptDdSearch" placeholder="Search apartment..." autocomplete="off">
                                            </div>
                                            <div class="sd-list" id="acAptDdList"></div>
                                        </div>
                                    </div>
                                    <input type="hidden" id="acApartmentId" name="apartment_id" value="<?= (int)($customer['apartment_id'] ?? 0) ?>">
                                    <input type="hidden" id="acApartmentCode" name="apartment_code" value="<?= htmlspecialchars($customer['apartment_code'] ?? '') ?>">
                                    <input type="hidden" id="acApartmentName" name="apartment_name" value="<?= htmlspecialchars($customer['apartment_name'] ?? '') ?>">
                                </div>

                                <div class="col-12 col-md-6">
                                    <label>Division</label>
                                    <div class="sd-wrap" id="acDivDdWrap">
                                        <button type="button" class="sd-toggle <?= !empty($customer['division']) ? 'has-value' : '' ?>" id="acDivDdToggle" <?= empty($customer['apartment_id']) ? 'disabled' : '' ?>>
                                            <i class="bi bi-grid-3x3-gap lead"></i>
                                            <span class="<?= !empty($customer['division']) ? '' : 'placeholder' ?>" id="acDivDdLabel">
                                                <?php
                                                if (!empty($customer['division'])) {
                                                    echo 'Division ' . htmlspecialchars($customer['division'])
                                                        . ' · ₹' . number_format((float)$customer['division_charge'], 0);
                                                } elseif (empty($customer['apartment_id'])) {
                                                    echo 'Select apartment first';
                                                } else {
                                                    echo '— Type or select division —';
                                                }
                                                ?>
                                            </span>
                                            <i class="bi bi-chevron-down caret"></i>
                                        </button>
                                        <div class="sd-menu">
                                            <div class="sd-search">
                                                <i class="bi bi-search"></i>
                                                <input type="text" id="acDivDdSearch" placeholder="Search division..." autocomplete="off">
                                            </div>
                                            <div class="sd-list" id="acDivDdList"></div>
                                        </div>
                                    </div>
                                    <input type="hidden" id="acDivision" name="division" value="<?= htmlspecialchars($customer['division'] ?? '') ?>">
                                    <input type="hidden" id="acDivisionCharge" name="division_charge" value="<?= htmlspecialchars($customer['division_charge'] ?? '0') ?>">
                                    <p class="field-help" id="acDivisionHint">
                                        <?= !empty($customer['division']) ? 'Charge: ₹' . number_format((float)$customer['division_charge'], 0) : '' ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- ACTIONS -->
                        <div class="form-actions">
                            <div>
                                <?php if ($isAdmin): ?>
                                    <button type="button" class="btn-delete" id="deleteCustomerBtn">
                                        <i class="bi bi-trash"></i> Delete Customer
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="actions-right">
                                <a href="customers.php" class="btn-cancel">
                                    <i class="bi bi-x-lg"></i> Cancel
                                </a>
                                <button type="submit" class="btn-save" id="saveBtn">
                                    <i class="bi bi-check-lg"></i>
                                    <span id="saveBtnText">Update Customer</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>


            <!-- TAB 2 — WALLET -->
            <div class="tab-panel" id="panel-wallet">
                <div class="customer-form-card">

                    <div class="wallet-balance-box">
                        <div>
                            <div class="label">Current Balance</div>
                            <div class="value" id="walletDisplay">
                                ₹<?= number_format((float)$customer['wallet_balance'], 2) ?>
                            </div>
                        </div>
                        <div class="side">
                            <div class="label">Customer</div>
                            <div class="name"><?= htmlspecialchars($customer['full_name']) ?></div>
                            <div class="mobile"><?= htmlspecialchars($customer['mobile_number']) ?></div>
                        </div>
                    </div>

                    <div class="wallet-card">
                        <h4><i class="bi bi-wallet2"></i> Manage Wallet</h4>

                        <div class="wallet-tabs">
                            <button type="button" class="wallet-tab credit active" data-tab="credit">
                                <i class="bi bi-plus-circle"></i> Add Money
                            </button>
                            <button type="button" class="wallet-tab debit" data-tab="debit">
                                <i class="bi bi-dash-circle"></i> Deduct Money
                            </button>
                        </div>

                        <form id="walletForm" autocomplete="off">
                            <input type="hidden" id="wTxnType" value="credit">

                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label>Amount (₹) <span class="required">*</span></label>
                                    <div class="input-icon-wrap">
                                        <i class="bi bi-currency-rupee"></i>
                                        <input type="number" id="wAmount" class="customer-input"
                                            min="1" step="0.01" placeholder="0.00" required>
                                    </div>
                                    <div class="wallet-presets">
                                        <button type="button" class="wallet-preset" data-amt="100">₹100</button>
                                        <button type="button" class="wallet-preset" data-amt="500">₹500</button>
                                        <button type="button" class="wallet-preset" data-amt="1000">₹1000</button>
                                        <button type="button" class="wallet-preset" data-amt="2000">₹2000</button>
                                        <button type="button" class="wallet-preset" data-amt="5000">₹5000</button>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label>Note (optional)</label>
                                    <div class="input-icon-wrap">
                                        <i class="bi bi-chat-left-text"></i>
                                        <input type="text" id="wNote" class="customer-input"
                                            maxlength="250" placeholder="e.g. Cash received, refund...">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="wallet-submit-btn" id="walletSubmitBtn">
                                <i class="bi bi-check-lg"></i>
                                <span id="walletSubmitText">Save Wallet</span>
                            </button>
                        </form>
                    </div>

                    <div class="history-section">
                        <div class="history-header">
                            <h4><i class="bi bi-clock-history"></i> Transaction History</h4>
                            <button type="button" class="history-refresh" id="walletRefreshHistory">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>

                        <div class="history-summary">
                            <div class="item">
                                <div class="lbl">Credit</div>
                                <div class="val credit" id="sumCredit">+₹0</div>
                            </div>
                            <div class="item">
                                <div class="lbl">Debit</div>
                                <div class="val debit" id="sumDebit">−₹0</div>
                            </div>
                            <div class="item">
                                <div class="lbl">Entries</div>
                                <div class="val neutral" id="sumEntries">0</div>
                            </div>
                        </div>

                        <div class="history-table-wrap" id="walletHistory">
                            <div class="history-empty">
                                <i class="bi bi-hourglass-split"></i>
                                <h4>Loading transactions…</h4>
                                <p>Please wait</p>
                            </div>
                        </div>

                        <div id="historyPagination"></div>
                    </div>

                </div>
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
            <h3 class="mm-modal-title" id="successTitle">Success</h3>
            <p class="mm-modal-text" id="successText">Operation completed successfully.</p>
            <div class="mm-modal-actions">
                <button type="button" class="mm-btn mm-btn-primary" id="successOkBtn">
                    <i class="bi bi-check2"></i> OK
                </button>
            </div>
        </div>
    </div>


    <!-- =========================================================
         ERROR POPUP
         ========================================================== -->
    <div class="mm-modal-overlay" id="errorOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog" aria-modal="true" aria-labelledby="errorTitle">
            <div class="mm-modal-icon mm-modal-icon-error">
                <i class="bi bi-exclamation-lg"></i>
            </div>
            <h3 class="mm-modal-title" id="errorTitle">Oops! Something's wrong</h3>
            <p class="mm-modal-text" id="errorText">Please check the form and try again.</p>
            <div class="mm-modal-actions">
                <button type="button" class="mm-btn mm-btn-danger" id="errorOkBtn">
                    <i class="bi bi-check2"></i> Got it
                </button>
            </div>
        </div>
    </div>


    <!-- =========================================================
         DELETE CONFIRM POPUP
         ========================================================== -->
    <div class="mm-modal-overlay" id="deleteOverlay" aria-hidden="true">
        <div class="mm-modal">
            <div class="mm-modal-icon mm-modal-icon-error">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <h3 class="mm-modal-title">Delete Customer?</h3>
            <p class="mm-modal-text">This will permanently remove the customer and wallet history.</p>
            <div class="mm-modal-actions">
                <button type="button" class="mm-btn mm-btn-ghost" id="deleteCancelBtn">
                    <i class="bi bi-x-lg"></i> Cancel
                </button>
                <button type="button" class="mm-btn mm-btn-danger" id="deleteConfirmBtn">
                    <i class="bi bi-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>


    <script>
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
        window.CUSTOMER_ID = <?= (int)$customer['id'] ?>;
        window.CUSTOMER_NAME = "<?= htmlspecialchars($customer['full_name'], ENT_QUOTES) ?>";
        window.CUSTOMER_MOBILE = "<?= htmlspecialchars($customer['mobile_number'], ENT_QUOTES) ?>";
        window.IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL ?>js/main.js"></script>
    <script src="<?= ADMIN_URL ?>js/edit-customer.js"></script>

</body>

</html>