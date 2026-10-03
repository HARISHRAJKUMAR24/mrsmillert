<?php
require_once './config/config.php';
require_once './config/function.php';

/* AUTH */
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    header('Location: login.php');
    exit;
}

/* ADMIN ONLY */
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: staff.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$staff = $stmt->fetch();

if (!$staff) { header('Location: staff.php'); exit; }

$settings = getSettings($pdo);
$siteName = $settings['username'] ?? 'Mrs Mill@';
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

    <style>
        /* Reuse from add-staff.php — same class names */
        .as-page { padding: 30px 32px 40px; }
        .as-header { display: flex; align-items: center; justify-content: space-between; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; }
        .as-title h1 { font-family: "Playfair Display", serif; font-size: 29px; font-weight: 700; color: #302923; margin: 0 0 5px; }
        .as-title p { margin: 0; color: #817a71; font-size: 13px; }
        .btn-back { display: inline-flex; align-items: center; gap: 7px; border: 1.5px solid #e4ddd3; background: #fff; color: #6f675f; padding: 10px 16px; border-radius: 11px; font-size: 11.5px; font-weight: 700; text-decoration: none; }
        .btn-back:hover { background: #faf7f0; color: #302923; }
        .as-card { background: #fff; border: 1px solid #eee7dc; border-radius: 20px; padding: 25px; max-width: 760px; }
        .as-card-head { display: flex; align-items: center; gap: 12px; margin-bottom: 22px; }
        .as-card-head-icon { width: 42px; height: 42px; border-radius: 12px; background: #fbe8e9; color: #b51f2c; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .as-card-head h3 { margin: 0; font-size: 16px; font-weight: 700; }
        .as-card-head span { display: block; margin-top: 3px; color: #817a71; font-size: 10px; }
        .code-badge { margin-left: auto; display: inline-block; background: #faf7f0; border: 1px dashed #d8c9b8; color: #6f5a3f; font-size: 10px; font-weight: 800; padding: 5px 11px; border-radius: 7px; letter-spacing: 1px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 11px; font-weight: 700; color: #4e4841; margin-bottom: 7px; }
        .form-group label .required { color: #b51f2c; }
        .input-wrap { position: relative; }
        .input-wrap > i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #aaa198; font-size: 15px; pointer-events: none; }
        .as-input { width: 100%; height: 45px; border: 1px solid #e8e1d8; background: #fffdf9; border-radius: 11px; padding: 11px 13px 11px 40px; font-family: "DM Sans", sans-serif; font-size: 12px; color: #292521; outline: none; transition: .2s ease; }
        .as-input:focus { border-color: #d98a91; background: #fff; box-shadow: 0 0 0 3px rgba(181,31,44,.06); }
        .status-row { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 14px 16px; background: #fffdf9; border: 1px solid #e8e1d8; border-radius: 12px; margin-bottom: 16px; }
        .status-row.is-active .status-icon { background: #e8f6ea; color: #2e7d32; }
        .status-icon { width: 38px; height: 38px; border-radius: 10px; background: #fde6e6; color: #c62828; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: .25s; }
        .status-info { display: flex; align-items: center; gap: 12px; }
        .status-info h4 { margin: 0; font-size: 13px; font-weight: 700; color: #302923; }
        .status-info p { margin: 2px 0 0; font-size: 10px; color: #948c82; }
        .switch { position: relative; display: inline-block; width: 46px; height: 26px; flex-shrink: 0; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .switch-slider { position: absolute; cursor: pointer; inset: 0; background: #d8d2c9; border-radius: 30px; transition: .25s; }
        .switch-slider::before { content: ""; position: absolute; height: 20px; width: 20px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: .25s; box-shadow: 0 2px 4px rgba(0,0,0,.15); }
        .switch input:checked + .switch-slider { background: #2e7d32; }
        .switch input:checked + .switch-slider::before { transform: translateX(20px); }
        .form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px; padding-top: 20px; border-top: 1px solid #f0ebe4; }
        .btn-cancel { border: 1px solid #e4ddd3; background: #fff; color: #6f675f; border-radius: 10px; padding: 10px 17px; font-size: 11px; font-weight: 700; text-decoration: none; }
        .btn-cancel:hover { background: #faf7f0; color: #302923; }
        .btn-save { border: none; background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%); color: #fff; border-radius: 10px; padding: 10px 19px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 7px; cursor: pointer; box-shadow: 0 8px 20px rgba(181,31,44,.22); }
        .btn-save:disabled { opacity: .7; cursor: not-allowed; }
        .btn-spinner { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; display: inline-block; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .mm-modal-overlay { position: fixed; inset: 0; background: rgba(30,25,22,.55); backdrop-filter: blur(3px); display: flex; align-items: center; justify-content: center; padding: 20px; z-index: 99999; opacity: 0; visibility: hidden; transition: .2s; }
        .mm-modal-overlay.show { opacity: 1; visibility: visible; }
        .mm-modal { background: #fff; border-radius: 18px; padding: 30px 26px 24px; max-width: 420px; width: 100%; text-align: center; box-shadow: 0 30px 80px rgba(0,0,0,.25); transform: translateY(15px) scale(.96); transition: transform .25s; }
        .mm-modal-overlay.show .mm-modal { transform: translateY(0) scale(1); }
        .mm-modal-icon { width: 66px; height: 66px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 30px; margin: 0 auto 16px; }
        .mm-modal-icon.success { background: #e8f6ea; color: #2e7d32; }
        .mm-modal-icon.error   { background: #fde6e6; color: #c62828; }
        .mm-modal-title { margin: 0 0 8px; font-size: 18px; font-weight: 800; color: #302923; }
        .mm-modal-text { margin: 0 0 20px; font-size: 12px; color: #756d65; line-height: 1.6; }
        .mm-modal-actions { display: flex; gap: 10px; }
        .mm-btn { flex: 1; height: 44px; border-radius: 11px; border: none; font-size: 12px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 7px; text-decoration: none; transition: .2s; font-family: "DM Sans", sans-serif; }
        .mm-btn-primary { background: #b51f2c; color: #fff; }
        .mm-btn-ghost { background: #fff; border: 1.5px solid #e4ddd3; color: #6f675f; }
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
                <input type="text" placeholder="Search staff...">
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


        <div class="as-page">

            <div class="as-header">
                <div class="as-title">
                    <h1>Edit Staff</h1>
                    <p>Update staff details, role or status.</p>
                </div>

                <a href="staff.php" class="btn-back">
                    <i class="bi bi-arrow-left"></i>
                    Back to List
                </a>
            </div>


            <div class="as-card">

                <div class="as-card-head">
                    <div class="as-card-head-icon">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <div>
                        <h3>Staff Details</h3>
                        <span>Changes save on Update.</span>
                    </div>
                    <span class="code-badge">#<?= htmlspecialchars($staff['staff_code']) ?></span>
                </div>


                <form id="staffForm" novalidate>

                    <input type="hidden" id="staff_id" value="<?= (int)$staff['id'] ?>">

                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <div class="input-wrap">
                            <i class="bi bi-person"></i>
                            <input type="text" id="full_name" class="as-input"
                                   value="<?= htmlspecialchars($staff['full_name']) ?>"
                                   maxlength="150">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Mobile Number <span class="required">*</span></label>
                        <div class="input-wrap">
                            <i class="bi bi-phone"></i>
                            <input type="text" id="mobile_number" class="as-input"
                                   value="<?= htmlspecialchars($staff['mobile_number']) ?>"
                                   maxlength="15" inputmode="numeric">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <div class="input-wrap">
                            <i class="bi bi-envelope"></i>
                            <input type="email" id="email_address" class="as-input"
                                   value="<?= htmlspecialchars($staff['email_address'] ?? '') ?>"
                                   maxlength="190" placeholder="(optional)">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Role <span class="required">*</span></label>
                        <select id="role" class="as-input" style="padding-left:13px;">
                            <option value="staff" <?= $staff['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                            <option value="admin" <?= $staff['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>

                    <div class="status-row <?= (int)$staff['status'] === 1 ? 'is-active' : '' ?>" id="statusRow">
                        <div class="status-info">
                            <div class="status-icon" id="statusIcon">
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                            <div>
                                <h4 id="statusTitle"><?= (int)$staff['status'] === 1 ? 'Active' : 'Inactive' ?></h4>
                                <p id="statusDesc"><?= (int)$staff['status'] === 1 ? 'Staff can log in.' : 'Staff cannot log in.' ?></p>
                            </div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="status" <?= (int)$staff['status'] === 1 ? 'checked' : '' ?>>
                            <span class="switch-slider"></span>
                        </label>
                    </div>

                    <div class="form-actions">
                        <a href="staff.php" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-save" id="saveBtn">
                            <i class="bi bi-check-lg"></i>
                            <span id="saveBtnText">Update Staff</span>
                        </button>
                    </div>

                </form>

            </div>

        </div>

    </main>


    <div class="mm-modal-overlay" id="successOverlay" aria-hidden="true">
        <div class="mm-modal">
            <div class="mm-modal-icon success">
                <i class="bi bi-check-lg"></i>
            </div>
            <h3 class="mm-modal-title">Staff Updated!</h3>
            <p class="mm-modal-text" id="successText">Changes saved successfully.</p>
            <div class="mm-modal-actions">
                <a href="staff.php" class="mm-btn mm-btn-primary">
                    <i class="bi bi-list-ul"></i> Back to List
                </a>
            </div>
        </div>
    </div>


    <div class="mm-modal-overlay" id="errorOverlay" aria-hidden="true">
        <div class="mm-modal">
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


    <script>
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL ?>js/main.js"></script>
    <script src="<?= ADMIN_URL ?>js/edit-staff.js"></script>

</body>

</html>