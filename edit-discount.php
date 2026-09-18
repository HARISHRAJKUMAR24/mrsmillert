<?php
require_once './config/config.php';
require_once './config/function.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: discounts.php');
    exit;
}

/* Load discount */
$discount = null;
$slots = [];

try {

    $stmt = $pdo->prepare("SELECT * FROM discounts WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $discount = $stmt->fetch();

    if (!$discount) {
        header('Location: discounts.php');
        exit;
    }

    $sStmt = $pdo->prepare(
        "SELECT * FROM discount_times WHERE discount_code = ? ORDER BY id ASC"
    );
    $sStmt->execute([$discount['discount_code']]);
    $slots = $sStmt->fetchAll();

} catch (PDOException $e) {
    header('Location: discounts.php');
    exit;
}

/* Convert HH:MM:SS to 12h + AM/PM */
function to12h(string $t): array
{
    $ts = strtotime($t);
    if (!$ts) return ['09:00', 'AM'];
    $h = (int)date('H', $ts);
    $m = (int)date('i', $ts);
    $ampm = $h >= 12 ? 'PM' : 'AM';
    $h12  = $h % 12; if ($h12 === 0) $h12 = 12;
    return [sprintf('%02d:%02d', $h12, $m), $ampm];
}

$isCoupon = $discount['discount_type'] === 'coupon';
$couponSlot = $isCoupon ? ($slots[0] ?? null) : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

    <style>
        .disc-page { padding: 30px 32px 40px; }

        .disc-header {
            display: flex; align-items: center; justify-content: space-between;
            gap: 20px; margin-bottom: 25px; flex-wrap: wrap;
        }
        .disc-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px; font-weight: 700;
            color: #302923; margin: 0 0 5px;
        }
        .disc-title p { margin: 0; color: #817a71; font-size: 13px; }

        .back-btn {
            display: inline-flex; align-items: center; gap: 8px;
            border: 1px solid #e4ddd3; background: #fff; color: #6f675f;
            padding: 10px 16px; border-radius: 10px;
            font-size: 11px; font-weight: 700; text-decoration: none;
        }

        .disc-card {
            background: #fff; border: 1px solid #eee7dc;
            border-radius: 20px; padding: 25px; max-width: 1100px;
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

        .disc-code-tag {
            margin-left: auto;
            display: inline-block;
            background: #faf7f0; border: 1px dashed #d8c9b8;
            color: #6f5a3f; font-size: 10px; font-weight: 800;
            padding: 4px 10px; border-radius: 7px; letter-spacing: 1px;
        }

        .disc-form label {
            display: block; font-size: 11px; font-weight: 700;
            color: #4e4841; margin-bottom: 7px;
        }
        .required { color: #b51f2c; }

        .disc-input {
            width: 100%; height: 45px;
            border: 1px solid #e8e1d8; background: #fffdf9;
            border-radius: 11px; padding: 11px 13px;
            font-family: "DM Sans", sans-serif; font-size: 12px;
            color: #292521; outline: none; transition: .2s ease;
        }
        .disc-input:focus {
            border-color: #d98a91; background: #fff;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .input-icon-wrap { position: relative; }
        .input-icon-wrap > i {
            position: absolute; left: 13px; top: 50%;
            transform: translateY(-50%); color: #aaa198;
            font-size: 15px; pointer-events: none;
        }
        .input-icon-wrap .disc-input { padding-left: 40px; }

        /* TYPE */
        .disc-type-row { display: flex; gap: 12px; margin-bottom: 22px; }
        .disc-type-option { flex: 1; position: relative; cursor: pointer; }
        .disc-type-option input { position: absolute; opacity: 0; pointer-events: none; }
        .disc-type-box {
            border: 1.5px solid #e8e1d8; background: #fffdf9;
            border-radius: 12px; padding: 14px 14px 12px;
            transition: .2s ease; height: 100%;
        }
        .disc-type-option input:checked + .disc-type-box {
            border-color: #b51f2c; background: #fff7f8;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }
        .disc-type-box .mode-head {
            display: flex; align-items: center; gap: 8px;
            font-size: 12px; font-weight: 700; color: #302923;
            margin-bottom: 5px;
        }
        .disc-type-box .mode-head i { font-size: 15px; color: #b51f2c; }
        .disc-type-box p { margin: 0; font-size: 10px; color: #948c82; line-height: 1.5; }

        /* SECTIONS */
        .section {
            margin-top: 22px; padding-top: 20px;
            border-top: 1px solid #f0ebe4;
        }
        .section-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; margin-bottom: 14px; flex-wrap: wrap;
        }
        .section-head h4 {
            margin: 0; font-size: 13px; font-weight: 800; color: #302923;
            display: flex; align-items: center; gap: 8px;
        }
        .section-head h4 i {
            width: 30px; height: 30px; border-radius: 9px;
            background: #fbe8e9; color: #b51f2c;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
        }
        .section-head p {
            margin: 4px 0 0 38px;
            font-size: 10px; color: #817a71;
        }

        /* TIME ROWS */
        .time-rows { display: flex; flex-direction: column; gap: 12px; }
        .time-row {
            border: 1px solid #f0ebe4;
            background: #fffdf9;
            border-radius: 14px;
            padding: 14px;
            animation: inFade .25s ease;
        }
        @keyframes inFade {
            0% { transform: translateY(-6px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }
        .time-row-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 10px; margin-bottom: 12px;
        }
        .time-row-badge {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 10px; font-weight: 800;
            background: #f1e8df; color: #755d48;
            padding: 4px 9px; border-radius: 6px;
        }
        .time-row-badge i { color: #b51f2c; font-size: 11px; }

        .time-row-remove {
            background: #fff;
            border: 1px solid #f0d6d8;
            color: #b51f2c;
            width: 30px; height: 30px;
            border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer; transition: .2s;
            font-size: 13px;
        }
        .time-row-remove:hover {
            background: #fde6e6; border-color: #f5c0c0;
            color: #c62828;
        }

        /* SLOT NAME */
        .slot-name-grid {
            margin-bottom: 12px;
        }
        .slot-name-grid .field-label {
            font-size: 10px; font-weight: 700; color: #6f675f;
            margin-bottom: 5px; display: block;
        }

        .time-row-grid {
            display: grid;
            grid-template-columns: 1fr 130px 100px 1fr 130px 100px;
            gap: 10px;
            align-items: center;
            margin-bottom: 12px;
        }
        .time-row-grid .field-label {
            font-size: 10px; font-weight: 700; color: #6f675f;
            margin-bottom: 5px; display: block;
        }

        .time-input {
            width: 100%; height: 42px;
            border: 1px solid #e8e1d8; background: #fff;
            border-radius: 10px; padding: 10px 12px;
            font-family: "DM Sans", sans-serif; font-size: 12px;
            color: #292521; outline: none; transition: .2s ease;
        }
        .time-input:focus {
            border-color: #d98a91;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .slot-extra-grid {
            display: grid;
            grid-template-columns: 160px 1fr 1fr;
            gap: 10px;
            padding-top: 12px;
            border-top: 1px dashed #f0ebe4;
        }

        .delivery-inline {
            display: flex; align-items: center; justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            background: #fff;
            border: 1px solid #e8e1d8;
            border-radius: 10px;
            height: 42px;
            box-sizing: border-box;
        }
        .delivery-inline.is-on { background: #f0f9f1; border-color: #b6e0bd; }
        .delivery-inline .lbl {
            font-size: 11px; font-weight: 700; color: #302923;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .delivery-inline .lbl i { color: #c62828; font-size: 13px; }
        .delivery-inline.is-on .lbl i { color: #2e7d32; }

        .mm-switch {
            position: relative; display: inline-block;
            width: 46px; height: 26px; flex-shrink: 0;
        }
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

        .add-time-btn {
            display: flex; align-items: center; justify-content: center;
            gap: 8px;
            margin-top: 12px; padding: 12px; width: 100%;
            background: #fffaf9;
            border: 2px dashed #e4ddd3;
            border-radius: 12px;
            color: #b51f2c;
            font-size: 11px; font-weight: 800;
            cursor: pointer; transition: .2s;
        }
        .add-time-btn:hover { background: #fff5f5; border-color: #d98a91; }

        /* COUPON */
        .coupon-fields { display: none; }
        .coupon-fields.show { display: block; }
        .coupon-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        /* STATUS */
        .status-toggle-row {
            display: flex; align-items: center; justify-content: space-between;
            gap: 14px;
            padding: 14px 16px;
            background: #fffdf9;
            border: 1px solid #e8e1d8;
            border-radius: 12px;
            margin-top: 16px;
        }
        .status-toggle-info { display: flex; align-items: center; gap: 12px; }
        .status-toggle-icon {
            width: 38px; height: 38px; border-radius: 10px;
            background: #fde6e6; color: #c62828;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; transition: .25s;
        }
        .status-toggle-row.is-active .status-toggle-icon {
            background: #e8f6ea; color: #2e7d32;
        }
        .status-toggle-info h4 { margin: 0; font-size: 13px; font-weight: 700; color: #302923; }
        .status-toggle-info p { margin: 2px 0 0; font-size: 10px; color: #948c82; }

        /* ACTIONS */
        .form-actions {
            display: flex; justify-content: flex-end; gap: 10px;
            margin-top: 22px; padding-top: 20px; border-top: 1px solid #f0ebe4;
        }
        .btn-cancel {
            border: 1px solid #e4ddd3; background: #fff; color: #6f675f;
            border-radius: 10px; padding: 10px 17px;
            font-size: 11px; font-weight: 700; text-decoration: none;
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
            border: 2px solid rgba(255, 255, 255, .4);
            border-top-color: #fff; border-radius: 50%;
            animation: spin .7s linear infinite; display: inline-block;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

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
        .mm-btn-ghost { background: #fff; border: 1px solid #e4ddd3; color: #6f675f; }
        .mm-btn-ghost:hover { background: #faf7f0; }

        @media (max-width: 900px) {
            .time-row-grid { grid-template-columns: 1fr 1fr 1fr; }
            .slot-extra-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .disc-page { padding: 20px 15px 30px; }
            .disc-header { flex-direction: column; align-items: flex-start; }
            .disc-card { padding: 17px; border-radius: 17px; }
            .disc-type-row { flex-direction: column; }
            .coupon-grid { grid-template-columns: 1fr; }
            .time-row-grid { grid-template-columns: 1fr; }
            .slot-extra-grid { grid-template-columns: 1fr; }
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


        <div class="disc-page">

            <div class="disc-header">
                <div class="disc-title">
                    <h1>Edit Discount</h1>
                    <p>Update name, type, time slots and amount.</p>
                </div>

                <a href="discounts.php" class="back-btn">
                    <i class="bi bi-arrow-left"></i>
                    Back to List
                </a>
            </div>


            <div class="disc-card">

                <div class="form-card-header">
                    <div class="form-card-icon">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <div>
                        <h3>Discount Details</h3>
                        <span>Changes save on Update.</span>
                    </div>
                    <span class="disc-code-tag">
                        #<?= htmlspecialchars($discount['discount_code']) ?>
                    </span>
                </div>

                <form id="discountForm" class="disc-form" method="POST" novalidate>

                    <input type="hidden" id="discount_id" value="<?= (int)$discount['id'] ?>">

                    <div class="row g-3">
                        <div class="col-12">
                            <label>Discount Name <span class="required">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="bi bi-tag"></i>
                                <input type="text"
                                    id="discount_name"
                                    class="disc-input"
                                    maxlength="150"
                                    value="<?= htmlspecialchars($discount['discount_name']) ?>"
                                    required>
                            </div>
                        </div>
                    </div>


                    <!-- TYPE -->
                    <div class="section">
                        <div class="section-head">
                            <div>
                                <h4><i class="bi bi-sliders"></i> Discount Type <span class="required">*</span></h4>
                                <p>Type cannot be changed after creation. Change by creating a new one.</p>
                            </div>
                        </div>

                        <div class="disc-type-row">

                            <label class="disc-type-option" for="disc_type_time">
                                <input type="radio" id="disc_type_time" name="discount_type" value="time"
                                    <?= !$isCoupon ? 'checked disabled' : 'disabled' ?>>
                                <div class="disc-type-box" style="opacity:<?= $isCoupon ? '0.5' : '1' ?>;">
                                    <div class="mode-head">
                                        <i class="bi bi-clock-history"></i> Time Based
                                    </div>
                                    <p>Runs automatically in one or more time slots.</p>
                                </div>
                            </label>

                            <label class="disc-type-option" for="disc_type_coupon">
                                <input type="radio" id="disc_type_coupon" name="discount_type" value="coupon"
                                    <?= $isCoupon ? 'checked disabled' : 'disabled' ?>>
                                <div class="disc-type-box" style="opacity:<?= $isCoupon ? '1' : '0.5' ?>;">
                                    <div class="mode-head">
                                        <i class="bi bi-ticket-detailed"></i> Coupon Code
                                    </div>
                                    <p>Customer enters a coupon code with date range.</p>
                                </div>
                            </label>

                        </div>
                    </div>


                    <!-- TIME SLOTS -->
                    <div class="section" id="timeSection" style="<?= $isCoupon ? 'display:none;' : '' ?>">
                        <div class="section-head">
                            <div>
                                <h4><i class="bi bi-clock"></i> Time Slots <span class="required">*</span></h4>
                                <p>Each slot has its own <strong>name</strong> (Set 1, Set 2...), <strong>amount type</strong>, <strong>amount</strong> and <strong>delivery</strong>.</p>
                            </div>
                        </div>

                        <div class="time-rows" id="timeRows"></div>

                        <button type="button" class="add-time-btn" id="addTimeBtn">
                            <i class="bi bi-plus-circle"></i>
                            Add Time Slot
                        </button>
                    </div>


                    <!-- COUPON -->
                    <div class="section coupon-fields <?= $isCoupon ? 'show' : '' ?>" id="couponSection">
                        <div class="section-head">
                            <div>
                                <h4><i class="bi bi-ticket-perforated"></i> Coupon Details <span class="required">*</span></h4>
                                <p>Coupon code + date range + time window + amount type + amount + delivery.</p>
                            </div>
                        </div>

                        <div class="coupon-grid" style="margin-bottom:12px;">
                            <div>
                                <label>Coupon Code <span class="required">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-hash"></i>
                                    <input type="text"
                                        id="coupon_code"
                                        class="disc-input"
                                        maxlength="50"
                                        value="<?= htmlspecialchars($discount['coupon_code'] ?? '') ?>"
                                        autocomplete="off"
                                        spellcheck="false">
                                </div>
                            </div>
                            <div></div>
                            <div>
                                <label>Valid From Date <span class="required">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-calendar-event"></i>
                                    <input type="date"
                                        id="valid_from_date"
                                        class="disc-input"
                                        value="<?= htmlspecialchars($discount['valid_from_date'] ?? '') ?>">
                                </div>
                            </div>
                            <div>
                                <label>Valid To Date <span class="required">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-calendar-event"></i>
                                    <input type="date"
                                        id="valid_to_date"
                                        class="disc-input"
                                        value="<?= htmlspecialchars($discount['valid_to_date'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <?php
                        $cStart = $couponSlot ? to12h($couponSlot['start_time']) : ['09:00', 'AM'];
                        $cEnd   = $couponSlot ? to12h($couponSlot['end_time'])   : ['09:00', 'PM'];
                        $cType  = $couponSlot ? $couponSlot['amount_type']        : 'fixed';
                        $cAmt   = $couponSlot ? $couponSlot['discount_amount']    : '';
                        $cDel   = $couponSlot ? (int)$couponSlot['delivery_enabled'] : 1;
                        ?>

                        <label>Coupon Time Window <span class="required">*</span></label>
                        <div class="time-row-grid" style="margin-bottom:12px;">
                            <div>
                                <label class="field-label">Start Time</label>
                                <input type="time" id="coupon_start_time" class="time-input" value="<?= htmlspecialchars($cStart[0]) ?>">
                            </div>
                            <div>
                                <label class="field-label">AM/PM</label>
                                <select id="coupon_start_ampm" class="time-input">
                                    <option value="AM" <?= $cStart[1] === 'AM' ? 'selected' : '' ?>>AM</option>
                                    <option value="PM" <?= $cStart[1] === 'PM' ? 'selected' : '' ?>>PM</option>
                                </select>
                            </div>
                            <div></div>
                            <div>
                                <label class="field-label">End Time</label>
                                <input type="time" id="coupon_end_time" class="time-input" value="<?= htmlspecialchars($cEnd[0]) ?>">
                            </div>
                            <div>
                                <label class="field-label">AM/PM</label>
                                <select id="coupon_end_ampm" class="time-input">
                                    <option value="AM" <?= $cEnd[1] === 'AM' ? 'selected' : '' ?>>AM</option>
                                    <option value="PM" <?= $cEnd[1] === 'PM' ? 'selected' : '' ?>>PM</option>
                                </select>
                            </div>
                            <div></div>
                        </div>

                        <div class="slot-extra-grid">
                            <div>
                                <label>Amount Type <span class="required">*</span></label>
                                <select id="coupon_amount_type" class="time-input">
                                    <option value="fixed" <?= $cType === 'fixed' ? 'selected' : '' ?>>Fixed (₹)</option>
                                    <option value="percent" <?= $cType === 'percent' ? 'selected' : '' ?>>Percentage (%)</option>
                                </select>
                            </div>

                            <div>
                                <label>Amount <span class="required">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="bi <?= $cType === 'percent' ? 'bi-percent' : 'bi-currency-rupee' ?>" id="couponAmountIcon"></i>
                                    <input type="number"
                                        id="coupon_amount"
                                        class="disc-input"
                                        value="<?= htmlspecialchars((string)$cAmt) ?>"
                                        min="0"
                                        step="0.01"
                                        <?= $cType === 'percent' ? 'max="100"' : '' ?>>
                                </div>
                            </div>

                            <div>
                                <label>Delivery</label>
                                <div class="delivery-inline <?= $cDel ? 'is-on' : '' ?>" id="couponDeliveryWrap">
                                    <span class="lbl">
                                        <i class="bi bi-truck"></i>
                                        <span id="couponDeliveryText"><?= $cDel ? 'Delivery Enabled' : 'Delivery Disabled' ?></span>
                                    </span>
                                    <label class="mm-switch" for="coupon_delivery_enabled">
                                        <input type="checkbox" id="coupon_delivery_enabled" <?= $cDel ? 'checked' : '' ?>>
                                        <span class="mm-switch-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- STATUS -->
                    <div class="status-toggle-row <?= (int)$discount['status'] === 1 ? 'is-active' : '' ?>" id="statusToggleRow">
                        <div class="status-toggle-info">
                            <div class="status-toggle-icon" id="statusToggleIcon">
                                <i class="bi <?= (int)$discount['status'] === 1 ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                            </div>
                            <div>
                                <h4 id="statusToggleTitle"><?= (int)$discount['status'] === 1 ? 'Active' : 'Inactive' ?></h4>
                                <p id="statusToggleDesc"><?= (int)$discount['status'] === 1
                                    ? 'Discount will be applied.'
                                    : 'Discount will not be applied.' ?></p>
                            </div>
                        </div>
                        <label class="mm-switch" for="discount_status">
                            <input type="checkbox" id="discount_status" <?= (int)$discount['status'] === 1 ? 'checked' : '' ?>>
                            <span class="mm-switch-slider"></span>
                        </label>
                    </div>


                    <div class="form-actions">
                        <a href="discounts.php" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-save" id="saveBtn">
                            <i class="bi bi-check-lg"></i>
                            <span id="saveBtnText">Update Discount</span>
                        </button>
                    </div>

                </form>

            </div>

        </div>

    </main>


    <!-- ERROR -->
    <div class="mm-modal-overlay" id="errorOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog" aria-modal="true">
            <div class="mm-modal-icon error"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <h3 class="mm-modal-title" id="errorTitle">Oops!</h3>
            <p class="mm-modal-text" id="errorText">Something went wrong.</p>
            <div class="mm-modal-actions">
                <button type="button" class="mm-btn mm-btn-primary" id="errorOkBtn">Got it</button>
            </div>
        </div>
    </div>


    <!-- SUCCESS -->
    <div class="mm-modal-overlay" id="successOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog" aria-modal="true">
            <div class="mm-modal-icon success"><i class="bi bi-check-lg"></i></div>
            <h3 class="mm-modal-title">Discount Updated!</h3>
            <p class="mm-modal-text" id="successText">Discount updated successfully.</p>
            <div class="mm-modal-actions">
                <a href="discounts.php" class="mm-btn mm-btn-ghost">
                    <i class="bi bi-list-ul"></i> Back to List
                </a>
                <button type="button" class="mm-btn mm-btn-primary" id="stayBtn">
                    <i class="bi bi-pencil"></i> Stay Here
                </button>
            </div>
        </div>
    </div>


    <script>
        window.ADMIN_URL = "<?= ADMIN_URL; ?>";
        window.DISCOUNT_ID = <?= (int)$discount['id'] ?>;
        window.DISCOUNT_TYPE = "<?= htmlspecialchars($discount['discount_type']) ?>";
        window.DISCOUNT_SLOTS = <?= json_encode(array_map(function ($s) {
            $st = to12h($s['start_time']);
            $et = to12h($s['end_time']);
            return [
                'slot_name'        => $s['slot_name'] ?? 'Set',
                'start_time'       => $st[0],
                'start_ampm'       => $st[1],
                'end_time'         => $et[0],
                'end_ampm'         => $et[1],
                'amount_type'      => $s['amount_type'],
                'discount_amount'  => (float)$s['discount_amount'],
                'delivery_enabled' => (int)$s['delivery_enabled']
            ];
        }, $slots), JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL; ?>js/main.js"></script>
    <script src="<?= ADMIN_URL; ?>js/edit-discount.js"></script>

</body>

</html>