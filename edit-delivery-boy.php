<?php
require_once './config/config.php';
require_once './config/function.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: delivery-boys.php');
    exit;
}

/* Fetch boy */
$stmt = $pdo->prepare(
    "SELECT id, delivery_code, full_name, mobile_number, email_address,
            branch_id, status
     FROM delivery_boys
     WHERE id = ?
     LIMIT 1"
);
$stmt->execute([$id]);
$boy = $stmt->fetch();

if (!$boy) {
    header('Location: delivery-boys.php');
    exit;
}

/* Fetch branches */
$branchStmt = $pdo->query(
    "SELECT id, branch_name FROM settings_branches ORDER BY branch_name ASC"
);
$branches = $branchStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

    <style>
        /* =====================================================
           DELIVERY BOY EDIT PAGE
        ===================================================== */

        .boy-page { padding: 30px 32px 40px; }

        .boy-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .boy-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 5px;
        }

        .boy-title p {
            margin: 0;
            color: #817a71;
            font-size: 13px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 7px;
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

        .btn-back:hover { background: #faf7f0; color: #302923; }

        .boy-card {
            background: #fff;
            border: 1px solid #eee7dc;
            border-radius: 20px;
            padding: 25px;
            max-width: 1000px;
        }

        .card-head {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
        }

        .card-icon {
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

        .card-head h3 { margin: 0; font-size: 16px; font-weight: 700; }

        .card-head span {
            display: block;
            margin-top: 3px;
            color: #817a71;
            font-size: 10px;
        }

        .code-badge {
            display: inline-block;
            background: #faf7f0;
            border: 1px dashed #d8c9b8;
            color: #6f5a3f;
            font-size: 10px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 7px;
            letter-spacing: 1px;
            margin-left: auto;
        }

        .form-group { margin-bottom: 0; }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #4e4841;
            margin-bottom: 7px;
        }

        .required { color: #b51f2c; }

        .boy-input,
        .boy-select {
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
            height: 45px;
        }

        .boy-select {
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

        .boy-input:focus,
        .boy-select:focus {
            border-color: #d98a91;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .input-icon-wrap { position: relative; }

        .input-icon-wrap > i {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa198;
            font-size: 15px;
            pointer-events: none;
        }

        .input-icon-wrap .boy-input,
        .input-icon-wrap .boy-select {
            padding-left: 40px;
        }

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

        .btn-cancel:hover { background: #faf7f0; color: #302923; }

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

        .btn-save:hover { background: #8e1722; }
        .btn-save:disabled { opacity: .7; cursor: not-allowed; }

        .btn-spinner {
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, .4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            display: inline-block;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ---- POPUPS (same as add) ---- */
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

        .mm-modal-overlay.show { opacity: 1; visibility: visible; }

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

        .mm-modal-icon.error {
            background: #fdecec;
            color: #b51f2c;
        }

        @keyframes popIn {
            0%   { transform: scale(.5); opacity: 0; }
            100% { transform: scale(1);  opacity: 1; }
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

        .mm-modal-actions { display: flex; gap: 10px; }

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
            font-family: "DM Sans", sans-serif;
        }

        .mm-btn-primary {
            background: #b51f2c;
            color: #fff;
            box-shadow: 0 8px 20px rgba(181, 31, 44, .22);
        }

        .mm-btn-primary:hover { background: #8e1722; color: #fff; }

        .mm-btn-ghost {
            background: #fff;
            border: 1px solid #e4ddd3;
            color: #6f675f;
        }

        .mm-btn-ghost:hover { background: #faf7f0; color: #302923; }

        @media (max-width: 768px) {
            .boy-page { padding: 20px 15px 30px; }
            .boy-header { flex-direction: column; align-items: flex-start; }
            .boy-card { padding: 17px; border-radius: 17px; }
            .form-actions { flex-direction: column-reverse; }
            .btn-cancel, .btn-save { width: 100%; justify-content: center; }
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


        <div class="boy-page">

            <div class="boy-header">

                <div class="boy-title">
                    <h1>Edit Delivery Boy</h1>
                    <p>Update delivery boy details and status.</p>
                </div>



            </div>


            <div class="boy-card">

                <div class="card-head">
                    <div class="card-icon">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <div>
                        <h3>Delivery Boy Details</h3>
                        <span>Update info. Password stays the same unless reset separately.</span>
                    </div>
                    <span class="code-badge">#<?= htmlspecialchars($boy['delivery_code']) ?></span>
                </div>


                <form id="boyForm" novalidate>

                    <input type="hidden" id="boy_id" value="<?= (int)$boy['id'] ?>">

                    <div class="row g-3">

                        <!-- FULL NAME -->
                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label>Full Name <span class="required">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-person"></i>
                                    <input type="text"
                                        id="full_name"
                                        class="boy-input"
                                        value="<?= htmlspecialchars($boy['full_name']) ?>"
                                        maxlength="150">
                                </div>
                            </div>
                        </div>

                        <!-- MOBILE -->
                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label>Mobile Number <span class="required">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-phone"></i>
                                    <input type="text"
                                        id="mobile_number"
                                        class="boy-input"
                                        value="<?= htmlspecialchars($boy['mobile_number']) ?>"
                                        maxlength="15">
                                </div>
                            </div>
                        </div>

                        <!-- EMAIL -->
                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label>Email Address</label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-envelope"></i>
                                    <input type="email"
                                        id="email_address"
                                        class="boy-input"
                                        value="<?= htmlspecialchars($boy['email_address'] ?? '') ?>"
                                        maxlength="190"
                                        placeholder="(optional)">
                                </div>
                            </div>
                        </div>

                        <!-- BRANCH -->
                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label>Branch <span class="required">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="bi bi-shop"></i>
                                    <select id="branch_id" class="boy-select">
                                        <option value="">— Select Branch —</option>
                                        <?php foreach ($branches as $br): ?>
                                            <option value="<?= (int)$br['id'] ?>"
                                                <?= ((int)$boy['branch_id'] === (int)$br['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($br['branch_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- STATUS -->
                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label>Status</label>
                                <select id="status" class="boy-select">
                                    <option value="1" <?= (int)$boy['status'] === 1 ? 'selected' : '' ?>>Active</option>
                                    <option value="0" <?= (int)$boy['status'] === 0 ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                        </div>

                    </div>


                    <div class="form-actions">

                        <a href="delivery-boys.php" class="btn-cancel">Cancel</a>

                        <button type="submit" class="btn-save" id="saveBtn">
                            <i class="bi bi-check-lg"></i>
                            <span id="saveBtnText">Update Delivery Boy</span>
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </main>


    <!-- SUCCESS POPUP -->
    <div class="mm-modal-overlay" id="successOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog" aria-modal="true">

            <div class="mm-modal-icon">
                <i class="bi bi-check-lg"></i>
            </div>

            <h3 class="mm-modal-title">Delivery Boy Updated!</h3>

            <p class="mm-modal-text" id="successText">
                Changes saved successfully.
            </p>

            <div class="mm-modal-actions">
                <a href="delivery-boys.php" class="mm-btn mm-btn-ghost">
                    <i class="bi bi-list-ul"></i>
                    Back to List
                </a>
                <button type="button" class="mm-btn mm-btn-primary" id="stayBtn">
                    <i class="bi bi-pencil"></i>
                    Stay Here
                </button>
            </div>

        </div>
    </div>


    <!-- ERROR POPUP -->
    <div class="mm-modal-overlay" id="errorOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog" aria-modal="true">

            <div class="mm-modal-icon error">
                <i class="bi bi-exclamation-lg"></i>
            </div>

            <h3 class="mm-modal-title">Oops! Something's missing</h3>

            <p class="mm-modal-text" id="errorText">
                Please check the form and try again.
            </p>

            <div class="mm-modal-actions">
                <button type="button"
                        class="mm-btn mm-btn-primary"
                        id="errorOkBtn">
                    <i class="bi bi-check2"></i>
                    Got it
                </button>
            </div>

        </div>
    </div>


    <script>
        window.ADMIN_URL = "<?= ADMIN_URL; ?>";
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL; ?>js/main.js"></script>
    <script src="<?= ADMIN_URL; ?>js/edit-delivery-boy.js"></script>

</body>

</html>