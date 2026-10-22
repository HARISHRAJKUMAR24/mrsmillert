<?php
require_once './config/config.php';
require_once './config/function.php';

$houseId   = isset($_GET['id'])   ? (int) $_GET['id'] : 0;
$houseCode = isset($_GET['code']) ? trim($_GET['code']) : '';

if ($houseId <= 0 && $houseCode === '') {
    header('Location: apartment.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

    <style>
        .apartment-page { padding: 30px 32px 40px; }

        .apartment-header {
            display: flex; align-items: center; justify-content: space-between;
            gap: 20px; margin-bottom: 25px;
        }

        .apartment-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px; font-weight: 700; color: #302923;
            margin: 0 0 5px;
        }

        .apartment-title p { margin: 0; color: #817a71; font-size: 13px; }

        .apartment-form-card {
            background: #fff; border: 1px solid #eee7dc; border-radius: 20px;
            padding: 25px; max-width: 1000px;
        }

        .form-card-header {
            display: flex; align-items: center; gap: 12px; margin-bottom: 22px;
        }

        .form-card-icon {
            width: 42px; height: 42px; border-radius: 12px;
            background: #e8f1e8; color: #2e7d32;
            display: flex; align-items: center; justify-content: center; font-size: 18px;
        }

        .form-card-header h3 { margin: 0; font-size: 16px; font-weight: 700; }
        .form-card-header span {
            display: block; margin-top: 3px; color: #817a71; font-size: 10px;
        }

        .apt-code-badge {
            display: inline-block; background: #faf7f0;
            border: 1px dashed #d8c9b8; color: #6f5a3f;
            font-size: 10px; font-weight: 800; padding: 4px 10px;
            border-radius: 7px; letter-spacing: 1px; margin-left: auto;
        }

        .apartment-form label {
            display: block; font-size: 11px; font-weight: 700;
            color: #4e4841; margin-bottom: 7px;
        }

        .required { color: #b51f2c; }

        .apartment-input, .apartment-textarea {
            width: 100%; border: 1px solid #e8e1d8; background: #fffdf9;
            border-radius: 11px; padding: 11px 13px;
            font-family: "DM Sans", sans-serif; font-size: 12px;
            color: #292521; outline: none; transition: .2s ease;
        }

        .apartment-input { height: 45px; }
        .apartment-textarea { min-height: 95px; resize: vertical; }

        .apartment-input:focus, .apartment-textarea:focus {
            border-color: #d98a91; background: #fff;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .input-icon-wrap { position: relative; }

        .input-icon-wrap>i {
            position: absolute; left: 13px; top: 50%;
            transform: translateY(-50%); color: #aaa198;
            font-size: 15px; pointer-events: none;
        }

        .input-icon-wrap .apartment-input,
        .input-icon-wrap .apartment-textarea { padding-left: 40px; }

        .field-help {
            font-size: 9px; color: #948c82; margin-top: 6px; line-height: 1.5;
        }

        .btn-spinner {
            width: 14px; height: 14px;
            border: 2px solid rgba(255,255,255,.4);
            border-top-color: #fff; border-radius: 50%;
            animation: spin .7s linear infinite; display: inline-block;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .form-loading {
            text-align: center; padding: 60px 20px;
            color: #948c82; font-size: 12px;
        }

        .form-loading .table-spinner {
            width: 26px; height: 26px; border: 3px solid #eee7dc;
            border-top-color: #b51f2c; border-radius: 50%;
            animation: spin .7s linear infinite; display: inline-block;
            margin-bottom: 10px;
        }

        /* ============================================
           Password toggle
        ============================================ */
        .pw-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 30px;
            height: 30px;
            border: 0;
            background: transparent;
            color: #948c82;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: .15s ease;
            padding: 0;
            font-size: 14px;
        }
        .pw-toggle:hover {
            background: #fbe8e9;
            color: #b51f2c;
        }
        .input-icon-wrap:has(.pw-toggle) .apartment-input {
            padding-right: 44px;
        }

        /* Location + image */
        .loc-btn {
            margin-top: 10px; width: 100%; padding: 11px 14px;
            background: #fff5f5; border: 1.5px dashed #d98a91; color: #b51f2c;
            border-radius: 10px; font-size: 12px; font-weight: 700;
            cursor: pointer; display: inline-flex; align-items: center;
            justify-content: center; gap: 8px; transition: .15s ease;
            font-family: inherit;
        }
        .loc-btn:hover:not(:disabled) { background: #fbe8e9; border-style: solid; }
        .loc-btn:disabled { opacity: .6; cursor: wait; }
        .loc-btn.success {
            background: #e8f6ea; border-color: #a5d6a7;
            color: #2e7d32; border-style: solid;
        }

        .img-drop {
            border: 1.5px dashed #d8cdbf; border-radius: 12px;
            background: #fffdf9; overflow: hidden;
            transition: .15s ease; cursor: pointer;
        }
        .img-drop:hover { border-color: #d98a91; background: #fff; }
        .img-drop-empty {
            padding: 28px 16px; display: flex; flex-direction: column;
            align-items: center; gap: 8px;
            color: #948c82; font-size: 12px;
        }
        .img-drop-empty i { font-size: 26px; color: #d5cbbd; }
        .img-drop img {
            display: block; width: 100%; height: 180px; object-fit: cover;
        }

        /* Form actions */
        .form-actions {
            display: flex; justify-content: flex-end; gap: 10px;
            margin-top: 22px; padding-top: 20px;
            border-top: 1px solid #f0ebe4;
        }
        .btn-cancel {
            border: 1px solid #e4ddd3; background: #fff; color: #6f675f;
            border-radius: 10px; padding: 10px 17px;
            font-size: 11px; font-weight: 700; text-decoration: none;
        }
        .btn-save {
            border: none; background: #b51f2c; color: white;
            border-radius: 10px; padding: 10px 19px;
            font-size: 11px; font-weight: 700;
            display: inline-flex; align-items: center; gap: 7px;
            cursor: pointer; transition: .2s;
        }
        .btn-save:hover { background: #8e1722; }
        .btn-save:disabled { opacity: .7; cursor: not-allowed; }

        /* Success popup */
        .mm-modal-overlay {
            position: fixed; inset: 0;
            background: rgba(30, 25, 22, .55); backdrop-filter: blur(3px);
            display: flex; align-items: center; justify-content: center;
            padding: 20px; z-index: 9999;
            opacity: 0; visibility: hidden;
            transition: opacity .2s ease, visibility .2s ease;
        }
        .mm-modal-overlay.show { opacity: 1; visibility: visible; }
        .mm-modal {
            background: #fff; border-radius: 18px; padding: 30px 26px 24px;
            max-width: 420px; width: 100%; text-align: center;
            box-shadow: 0 30px 80px rgba(0,0,0,.25);
            transform: translateY(15px) scale(.96);
            transition: transform .25s cubic-bezier(.2,.9,.3,1.2);
        }
        .mm-modal-overlay.show .mm-modal { transform: translateY(0) scale(1); }
        .mm-modal-icon {
            width: 66px; height: 66px; border-radius: 50%;
            background: #e8f6ea; color: #2e7d32;
            display: flex; align-items: center; justify-content: center;
            font-size: 30px; margin: 0 auto 16px;
            animation: popIn .35s cubic-bezier(.2,.9,.3,1.4);
        }
        @keyframes popIn { 0% { transform: scale(.5); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
        .mm-modal-title { margin: 0 0 8px; font-size: 18px; font-weight: 800; color: #302923; }
        .mm-modal-text { margin: 0 0 20px; font-size: 12px; color: #756d65; line-height: 1.6; }
        .mm-modal-code {
            display: inline-block; background: #faf7f0;
            border: 1px dashed #d8c9b8; color: #6f5a3f;
            font-size: 11px; font-weight: 700; padding: 6px 12px;
            border-radius: 8px; margin-bottom: 20px; letter-spacing: 1px;
        }
        .mm-modal-actions { display: flex; gap: 10px; }
        .mm-btn {
            flex: 1; height: 44px; border-radius: 11px; border: none;
            font-size: 12px; font-weight: 700; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
            gap: 7px; text-decoration: none; transition: .2s ease;
        }
        .mm-btn-primary {
            background: #b51f2c; color: #fff;
            box-shadow: 0 8px 20px rgba(181, 31, 44, .22);
        }
        .mm-btn-primary:hover { background: #8e1722; color: #fff; }
        .mm-btn-ghost { background: #fff; border: 1px solid #e4ddd3; color: #6f675f; }
        .mm-btn-ghost:hover { background: #faf7f0; color: #302923; }

        @media (max-width: 768px) {
            .apartment-page { padding: 20px 15px 30px; }
            .apartment-header { flex-direction: column; align-items: flex-start; }
            .apartment-form-card { padding: 17px; border-radius: 17px; }
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


        <div class="apartment-page">

            <div class="apartment-header">
                <div class="apartment-title">
                    <h1>Edit Individual House</h1>
                    <p>Update customer info, address, map location, password and house photo.</p>
                </div>
            </div>


            <div class="apartment-form-card">

                <div class="form-card-header">
                    <div class="form-card-icon">
                        <i class="bi bi-house-door"></i>
                    </div>
                    <div>
                        <h3>Individual House Details</h3>
                        <span>Update customer info, address, location pin, and image.</span>
                    </div>
                    <span class="apt-code-badge" id="houseCodeBadge">—</span>
                </div>

                <div id="formLoading" class="form-loading">
                    <div class="table-spinner"></div>
                    <div>Loading house details...</div>
                </div>

                <form id="houseForm" class="apartment-form" novalidate style="display:none;">

                    <input type="hidden" id="house_id" value="">

                    <div class="row g-3">

                        <div class="col-12 col-md-6">
                            <label>Customer Name <span class="required">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="bi bi-person"></i>
                                <input type="text" id="houseName" class="apartment-input"
                                    placeholder="Full name" maxlength="150">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label>Mobile Number <span class="required">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="bi bi-telephone"></i>
                                <input type="tel" id="houseMobile" class="apartment-input"
                                    placeholder="10-digit mobile" maxlength="15" inputmode="numeric">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label>Password <span style="color:#948c82;font-weight:400;">(leave blank to keep current)</span></label>
                            <div class="input-icon-wrap">
                                <i class="bi bi-lock"></i>
                                <input type="password" id="housePassword" class="apartment-input"
                                    placeholder="New password (min 3 chars)"
                                    maxlength="100" autocomplete="new-password">
                                <button type="button" class="pw-toggle"
                                    data-target="housePassword" aria-label="Show password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <p class="field-help">Only fill this to change the password.</p>
                        </div>

                        <div class="col-12">
                            <label>Full Address <span class="required">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="bi bi-geo-alt"></i>
                                <textarea id="houseAddress" class="apartment-textarea"
                                    placeholder="Door No, Street, Area, City" maxlength="250"></textarea>
                            </div>
                        </div>

                        <div class="col-12">
                            <label>Google Maps Location <span class="required">*</span></label>
                            <div class="input-icon-wrap">
                                <i class="bi bi-pin-map"></i>
                                <input type="text" id="houseMap" class="apartment-input"
                                    placeholder="Paste Google Maps link OR tap the button below"
                                    maxlength="600">
                            </div>
                            <div style="display:flex; gap:8px; margin-top:8px; flex-wrap:wrap;">
                                <button type="button" class="loc-btn" id="houseLocBtn" style="margin-top:0; flex:1; min-width:180px;">
                                    <i class="bi bi-crosshair"></i>
                                    <span id="houseLocBtnText">Get My Current Location</span>
                                </button>
                                <button type="button" class="loc-btn" id="houseOpenMapBtn" style="margin-top:0; flex:1; min-width:180px;">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                    <span>Open Google Maps</span>
                                </button>
                            </div>
                            <p class="field-help" style="margin-top:6px;">
                                Paste any <strong>google.com/maps/...</strong> link, or click <em>Get My Current Location</em>.
                            </p>
                            <p class="mm-co-hint" id="houseLocHint"></p>
                        </div>

                        <div class="col-12">
                            <label>House Image (optional)</label>
                            <div class="img-drop" id="houseImgDrop">
                                <input type="file" id="houseImg" accept="image/*" style="display:none;">
                                <div id="houseImgPreview" class="img-drop-empty">
                                    <i class="bi bi-image"></i>
                                    <span>Tap to upload a photo of the house</span>
                                </div>
                            </div>
                            <p class="field-help">Helps our delivery partner find the house faster.</p>
                        </div>

                    </div>

                    <div class="form-actions">
                        <a href="apartment.php" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-save" id="saveBtn">
                            <i class="bi bi-check-lg"></i>
                            <span id="saveBtnText">Update House</span>
                        </button>
                    </div>

                </form>

            </div>

        </div>

    </main>


    <!-- SUCCESS POPUP -->
    <div class="mm-modal-overlay" id="successOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog" aria-modal="true" aria-labelledby="successTitle">
            <div class="mm-modal-icon">
                <i class="bi bi-check-lg"></i>
            </div>
            <h3 class="mm-modal-title" id="successTitle">House Updated!</h3>
            <p class="mm-modal-text" id="successText">
                Individual house has been updated successfully.
            </p>
            <div class="mm-modal-code" id="successCode" style="display:none;"></div>
            <div class="mm-modal-actions">
                <a href="apartment.php" class="mm-btn mm-btn-ghost">
                    <i class="bi bi-list-ul"></i> Back to List
                </a>
                <a href="#" id="editAgainBtn" class="mm-btn mm-btn-primary">
                    <i class="bi bi-pencil"></i> Stay Here
                </a>
            </div>
        </div>
    </div>


    <script>
        window.ADMIN_URL = "<?= ADMIN_URL; ?>";
        window.HOUSE_ID = <?= (int) $houseId; ?>;
        window.HOUSE_CODE = "<?= htmlspecialchars($houseCode, ENT_QUOTES); ?>";
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL; ?>js/main.js"></script>
    <script src="<?= ADMIN_URL; ?>js/edit-individual-house.js"></script>

</body>

</html>