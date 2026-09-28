<?php
require_once './config/config.php';
require_once './config/function.php';

/* ---------------- FETCH ACTIVE DELIVERY BOYS with their allocated apartment count ---------------- */
$deliveryBoys = [];
try {
    $stmt = $pdo->query(
        "SELECT db.id, db.delivery_code, db.full_name, db.mobile_number,
                (SELECT COUNT(*) FROM apartment_delivery_boys adb
                 WHERE adb.delivery_boy_id = db.id) AS apt_count
         FROM delivery_boys db
         WHERE db.status = 1
         ORDER BY db.full_name ASC"
    );
    $deliveryBoys = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $deliveryBoys = [];
}

$boysJson = json_encode($deliveryBoys, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include './includes/head.php'; ?>

    <style>
        /* =====================================================
           ALLOCATE APARTMENTS — CARD VIEW
           ===================================================== */
        .ac-page { padding: 24px 26px 60px; }

        .ac-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }
        .ac-head h1 {
            font-family: "Playfair Display", serif;
            font-size: 26px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 4px;
        }
        .ac-head p {
            margin: 0;
            font-size: 12.5px;
            color: #817a71;
        }

        /* Search box */
        .ac-search {
            position: relative;
            min-width: 260px;
        }
        .ac-search input {
            width: 100%;
            height: 42px;
            border: 1.5px solid #ece5da;
            background: #fffdf9;
            border-radius: 11px;
            padding: 0 14px 0 40px;
            font-family: "DM Sans", sans-serif;
            font-size: 12.5px;
            color: #292521;
            outline: none;
            transition: .2s ease;
        }
        .ac-search input:focus {
            border-color: #b51f2c;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(181, 31, 44, .08);
        }
        .ac-search > i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0a79c;
            font-size: 15px;
            pointer-events: none;
        }

        /* =====================================================
           CARDS GRID
           ===================================================== */
        .ac-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 16px;
        }

        .ac-card {
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 18px;
            padding: 18px;
            cursor: pointer;
            transition: .2s ease;
            display: flex;
            flex-direction: column;
            gap: 14px;
            position: relative;
            overflow: hidden;
        }

        .ac-card::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #b51f2c 0%, #8e1722 100%);
            opacity: 0;
            transition: opacity .2s ease;
        }

        .ac-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 30px -10px rgba(48, 41, 35, .15);
            border-color: #d98a91;
        }

        .ac-card:hover::before {
            opacity: 1;
        }

        .ac-card-head {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .ac-avatar {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 800;
            flex-shrink: 0;
            box-shadow: 0 8px 20px rgba(181, 31, 44, .25);
        }

        .ac-card-info { flex: 1; min-width: 0; }

        .ac-card-name {
            font-size: 14px;
            font-weight: 800;
            color: #302923;
            margin: 0;
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ac-card-meta {
            font-size: 11px;
            color: #948c82;
            margin: 3px 0 0;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ac-card-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 12px;
            border-top: 1px solid #f5efe5;
        }

        .ac-count {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 11px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .ac-count.has { background: #e7f6ec; color: #1f7a3d; }
        .ac-count.none { background: #fbeaea; color: #b51f2c; }
        .ac-count i { font-size: 11px; }

        .ac-card-go {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #f7f2ec;
            color: #6f675f;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            transition: .2s ease;
        }

        .ac-card:hover .ac-card-go {
            background: #b51f2c;
            color: #fff;
            transform: translateX(3px);
        }

        /* Empty state */
        .ac-empty {
            text-align: center;
            padding: 60px 20px;
            color: #948c82;
            background: #fff;
            border: 1.5px dashed #ece5da;
            border-radius: 18px;
        }
        .ac-empty i {
            font-size: 44px;
            color: #ece5da;
            display: block;
            margin-bottom: 10px;
        }
        .ac-empty h3 {
            font-family: "Playfair Display", serif;
            font-size: 18px;
            color: #302923;
            margin: 0 0 6px;
        }
        .ac-empty p { font-size: 12.5px; margin: 0; }

        /* =====================================================
           ALLOCATE MODAL
           ===================================================== */
        .ac-overlay {
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
        .ac-overlay.show { opacity: 1; visibility: visible; }

        .ac-modal {
            background: #fff;
            border-radius: 22px;
            width: 100%;
            max-width: 560px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 30px 80px rgba(0, 0, 0, .28);
            transform: translateY(15px) scale(.97);
            transition: transform .25s cubic-bezier(.2, .9, .3, 1.2);
        }
        .ac-overlay.show .ac-modal { transform: translateY(0) scale(1); }

        .ac-modal-head {
            padding: 22px 22px 16px;
            border-bottom: 1.5px solid #f0ebe4;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-shrink: 0;
        }

        .ac-modal-title-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .ac-modal-avatar {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
            flex-shrink: 0;
            box-shadow: 0 6px 16px rgba(181, 31, 44, .25);
        }

        .ac-modal-head h3 {
            margin: 0 0 3px;
            font-family: "Playfair Display", serif;
            font-size: 18px;
            font-weight: 700;
            color: #302923;
        }

        .ac-modal-head p {
            margin: 0;
            font-size: 11.5px;
            color: #948c82;
            font-weight: 600;
        }

        .ac-modal-close {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            border: 0;
            background: #f7f2ec;
            color: #6f675f;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: .18s ease;
            flex-shrink: 0;
            font-size: 14px;
        }
        .ac-modal-close:hover { background: #fbeaea; color: #b51f2c; }

        .ac-tabs {
            display: flex;
            gap: 4px;
            padding: 12px 22px 0;
            border-bottom: 1.5px solid #f0ebe4;
            flex-shrink: 0;
        }

        .ac-tab {
            border: none;
            background: transparent;
            padding: 10px 14px;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            font-weight: 700;
            color: #6f675f;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -1.5px;
            transition: .15s ease;
        }
        .ac-tab:hover { color: #b51f2c; }
        .ac-tab.active {
            color: #b51f2c;
            border-bottom-color: #b51f2c;
        }

        .ac-modal-body {
            padding: 16px 22px 22px;
            overflow-y: auto;
            flex: 1;
        }

        /* Search inside modal */
        .ac-modal-search {
            position: relative;
            margin-bottom: 14px;
        }
        .ac-modal-search input {
            width: 100%;
            height: 40px;
            border: 1.5px solid #ece5da;
            background: #fffdf9;
            border-radius: 10px;
            padding: 0 14px 0 38px;
            font-family: "DM Sans", sans-serif;
            font-size: 12.5px;
            color: #292521;
            outline: none;
        }
        .ac-modal-search input:focus {
            border-color: #b51f2c;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }
        .ac-modal-search > i {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0a79c;
            font-size: 14px;
            pointer-events: none;
        }

        /* Apartment rows */
        .ac-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 380px;
            overflow-y: auto;
            padding-right: 4px;
        }
        .ac-list::-webkit-scrollbar { width: 6px; }
        .ac-list::-webkit-scrollbar-thumb {
            background: #e0d8cd;
            border-radius: 4px;
        }

        .ac-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            border: 1.5px solid #ece5da;
            border-radius: 12px;
            background: #fff;
            cursor: pointer;
            transition: .15s ease;
            user-select: none;
        }
        .ac-item:hover { border-color: #d98a91; background: #fff5f5; }
        .ac-item.is-checked {
            border-color: #b51f2c;
            background: #fff5f5;
        }

        .ac-check {
            width: 20px;
            height: 20px;
            border-radius: 6px;
            border: 2px solid #d5cbbd;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: .15s ease;
            position: relative;
        }
        .ac-item.is-checked .ac-check {
            background: #b51f2c;
            border-color: #b51f2c;
        }
        .ac-check::after {
            content: "";
            width: 5px;
            height: 9px;
            border: solid #fff;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg) translate(-1px, -1px);
            opacity: 0;
            transition: .15s ease;
        }
        .ac-item.is-checked .ac-check::after { opacity: 1; }

        .ac-item-info { flex: 1; min-width: 0; }
        .ac-item-name {
            font-size: 12.5px;
            font-weight: 700;
            color: #302923;
            margin: 0;
            line-height: 1.3;
        }
        .ac-item-code {
            font-size: 10.5px;
            color: #948c82;
            margin: 3px 0 0;
            font-weight: 700;
            letter-spacing: .3px;
        }

        /* Existing allocated row */
        .ac-item.is-existing {
            border-color: #d6ecd9;
            background: #f3fbf5;
            cursor: default;
        }
        .ac-item.is-existing:hover { background: #f3fbf5; border-color: #d6ecd9; }

        .ac-existing-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #1f7a3d;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            flex-shrink: 0;
        }

        .ac-empty-inline {
            padding: 40px 20px;
            text-align: center;
            color: #948c82;
            font-size: 12px;
        }
        .ac-empty-inline i {
            font-size: 32px;
            color: #ece5da;
            display: block;
            margin-bottom: 8px;
        }

        /* Modal footer */
        .ac-modal-foot {
            padding: 16px 22px 20px;
            border-top: 1.5px solid #f0ebe4;
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }

        .ac-mbtn {
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
        .ac-mbtn-primary {
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
            box-shadow: 0 10px 22px rgba(181, 31, 44, .22);
        }
        .ac-mbtn-primary:hover:not(:disabled) { transform: translateY(-1px); }
        .ac-mbtn-primary:disabled { opacity: .65; cursor: not-allowed; }

        .ac-mbtn-ghost {
            flex: 0 0 120px;
            background: #fff;
            border: 1.5px solid #e4ddd3;
            color: #6f675f;
        }
        .ac-mbtn-ghost:hover { background: #faf7f0; color: #302923; }

        /* Selected pill inside modal header */
        .ac-selected-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 6px;
            padding: 3px 10px;
            border-radius: 999px;
            background: #e7f6ec;
            color: #1f7a3d;
            font-size: 10.5px;
            font-weight: 800;
        }

        /* Toast */
        .ac-toast {
            position: fixed;
            top: 84px;
            left: 50%;
            transform: translateX(-50%) translateY(-10px);
            padding: 10px 18px;
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 700;
            color: #fff;
            background: #1f7a3d;
            box-shadow: 0 10px 26px rgba(0, 0, 0, .18);
            z-index: 99999;
            opacity: 0;
            pointer-events: none;
            transition: opacity .25s ease, transform .25s ease;
            white-space: nowrap;
        }
        .ac-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .ac-toast.error { background: #b51f2c; }

        .btn-spinner {
            width: 13px;
            height: 13px;
            border: 2px solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: acSpin .7s linear infinite;
            display: inline-block;
        }
        @keyframes acSpin { to { transform: rotate(360deg); } }

        @media (max-width: 640px) {
            .ac-page { padding: 18px 14px 40px; }
            .ac-search { min-width: 100%; }
            .ac-modal-foot { flex-direction: column-reverse; }
            .ac-mbtn { flex: 1 1 auto; }
            .ac-mbtn-ghost { flex: 1 1 auto; width: 100%; }
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


        <div class="ac-page">

            <div class="ac-head">
                <div>
                    <h1>Allocate Apartments</h1>
                    <p>Click a delivery boy card to view and assign apartments.</p>
                </div>

                <div class="ac-search">
                    <i class="bi bi-search"></i>
                    <input type="text" id="boySearchInput" placeholder="Search delivery boy...">
                </div>
            </div>

            <div class="ac-grid" id="boyGrid">
                <!-- Filled by JS -->
            </div>

        </div>
    </main>


    <!-- ================= ALLOCATE MODAL ================= -->
    <div class="ac-overlay" id="acOverlay" aria-hidden="true">
        <div class="ac-modal" role="dialog" aria-modal="true">

            <div class="ac-modal-head">
                <div class="ac-modal-title-wrap">
                    <div class="ac-modal-avatar" id="acModalAvatar">H</div>
                    <div>
                        <h3 id="acModalTitle">Delivery Boy</h3>
                        <p id="acModalSub">—</p>
                        <div class="ac-selected-pill" id="acSelectedPill" style="display:none;">
                            <i class="bi bi-check-circle-fill"></i>
                            <span id="acSelectedText">0 selected</span>
                        </div>
                    </div>
                </div>
                <button type="button" class="ac-modal-close" id="acModalClose">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="ac-tabs">
                <button type="button" class="ac-tab active" data-tab="available">
                    Available
                </button>
                <button type="button" class="ac-tab" data-tab="allocated">
                    Allocated (<span id="acAllocatedCount">0</span>)
                </button>
            </div>

            <div class="ac-modal-body">

                <div id="acAvailableTab">
                    <div class="ac-modal-search">
                        <i class="bi bi-search"></i>
                        <input type="text" id="aptSearchInput" placeholder="Search apartment by name or code..." autocomplete="off">
                    </div>
                    <div class="ac-list" id="acAvailList"></div>
                </div>

                <div id="acAllocatedTab" style="display:none;">
                    <div class="ac-list" id="acAllocList"></div>
                </div>

            </div>

            <div class="ac-modal-foot" id="acFoot">
                <button type="button" class="ac-mbtn ac-mbtn-ghost" id="acCancel">Cancel</button>
                <button type="button" class="ac-mbtn ac-mbtn-primary" id="acSave">
                    <i class="bi bi-check-lg"></i>
                    <span id="acSaveText">Allocate Selected</span>
                </button>
            </div>

        </div>
    </div>


    <div class="ac-toast" id="acToast"></div>


    <!-- ================= GLOBALS ================= -->
    <script>
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
        window.DELIVERY_BOYS = <?= $boysJson ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL ?>js/main.js"></script>
       <script src="<?= ADMIN_URL ?>js/order-allocations.js"></script>

</body>

</html>