<?php
require_once './config/config.php';
require_once './config/function.php';

/* =========================================================
   LOAD MENUS + COUNTS + TIMING STATUS
   ========================================================= */

$menus = [];

try {

    $stmt = $pdo->query(
        "SELECT m.id,
                m.menu_code,
                m.menu_name,
                m.start_at,
                m.end_at,
                m.created_at,
                (SELECT COUNT(DISTINCT product_code)
                   FROM menu_products
                  WHERE menu_code = m.menu_code) AS product_count,
                (SELECT COUNT(*)
                   FROM menu_products
                  WHERE menu_code = m.menu_code) AS variant_count
         FROM menus m
         ORDER BY m.id DESC"
    );

    $menus = $stmt->fetchAll();

} catch (PDOException $e) {
    $menus = [];
}

/* =========================================================
   HELPERS
   ========================================================= */

function menuTimingStatus(string $startAt, string $endAt): array
{
    $now   = time();
    $start = strtotime($startAt);
    $end   = strtotime($endAt);

    if ($now < $start) {
        return ['key' => 'upcoming', 'label' => 'Upcoming'];
    }
    if ($now > $end) {
        return ['key' => 'expired', 'label' => 'Expired'];
    }
    return ['key' => 'live', 'label' => 'Live'];
}

function fmtDateTime(string $dbDate): string
{
    $ts = strtotime($dbDate);
    if (!$ts) return $dbDate;
    return date('d M Y, h:i A', $ts);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

    <style>
        .menu-page { padding: 30px 32px 40px; }

        .menu-header {
            display: flex; align-items: center; justify-content: space-between;
            gap: 20px; margin-bottom: 25px; flex-wrap: wrap;
        }

        .menu-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px; font-weight: 700;
            color: #302923; margin: 0 0 5px;
        }
        .menu-title p { margin: 0; color: #817a71; font-size: 13px; }

        .btn-add {
            display: inline-flex; align-items: center; gap: 8px;
            background: #b51f2c; color: #fff;
            border: none; border-radius: 10px;
            padding: 11px 18px;
            font-size: 12px; font-weight: 700;
            text-decoration: none;
            transition: .2s;
        }
        .btn-add:hover { background: #8e1722; color: #fff; }

        .menu-card {
            background: #fff; border: 1px solid #eee7dc;
            border-radius: 20px; padding: 22px;
        }

        .menu-toolbar {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 18px; flex-wrap: wrap;
        }
        .menu-search {
            position: relative; flex: 1; min-width: 220px;
        }
        .menu-search i {
            position: absolute; left: 13px; top: 50%;
            transform: translateY(-50%); color: #aaa198; font-size: 14px;
        }
        .menu-search input {
            width: 100%; height: 42px;
            border: 1px solid #e8e1d8; background: #fffdf9;
            border-radius: 11px; padding: 0 12px 0 38px;
            font-family: "DM Sans", sans-serif; font-size: 12px;
            color: #292521; outline: none; transition: .2s ease;
        }
        .menu-search input:focus {
            border-color: #d98a91; background: #fff;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .menu-filter {
            height: 42px;
            border: 1px solid #e8e1d8; background: #fffdf9;
            border-radius: 11px; padding: 0 12px;
            font-family: "DM Sans", sans-serif; font-size: 12px;
            color: #292521; outline: none; cursor: pointer;
        }
        .menu-filter:focus {
            border-color: #d98a91; background: #fff;
        }

        /* ---------- TABLE ---------- */

        .menu-table-wrap {
            border: 1px solid #f0ebe4; border-radius: 14px;
            overflow: hidden;
        }

        .menu-table {
            width: 100%; border-collapse: collapse;
            font-family: "DM Sans", sans-serif;
        }

        .menu-table thead {
            background: #faf7f0;
        }
        .menu-table th {
            text-align: left;
            font-size: 10px; font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #6f675f;
            padding: 12px 14px;
            border-bottom: 1px solid #f0ebe4;
            white-space: nowrap;
        }
        .menu-table td {
            font-size: 12px; color: #302923;
            padding: 14px;
            border-bottom: 1px solid #f5efe6;
            vertical-align: middle;
        }
        .menu-table tr:last-child td { border-bottom: none; }
        .menu-table tbody tr:hover { background: #fffaf9; }

        .menu-name-cell {
            display: flex; flex-direction: column; gap: 4px;
            max-width: 320px;
        }
        .menu-name-main {
            font-weight: 800; font-size: 12.5px; color: #302923;
        }
        .menu-code-tag {
            display: inline-block;
            font-size: 9px; font-weight: 700;
            background: #faf7f0; color: #6f5a3f;
            padding: 2px 7px; border-radius: 5px;
            letter-spacing: .5px;
            width: fit-content;
        }

        .menu-counts {
            display: flex; flex-direction: column; gap: 4px;
        }
        .menu-count-item {
            font-size: 11px; color: #6f675f;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .menu-count-item i {
            color: #b51f2c; font-size: 11px;
        }
        .menu-count-item strong {
            color: #302923; font-weight: 800;
        }

        .menu-dates {
            display: flex; flex-direction: column; gap: 5px;
            min-width: 180px;
        }
        .menu-date-line {
            font-size: 11px; color: #4e4841;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .menu-date-line i { color: #b51f2c; font-size: 11px; }
        .menu-date-line.start i { color: #2e7d32; }
        .menu-date-line.end   i { color: #c62828; }

        .status-pill {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 10px; font-weight: 800;
            padding: 5px 10px;
            border-radius: 20px;
            letter-spacing: .3px;
            text-transform: uppercase;
        }
        .status-pill.live {
            background: #e8f6ea; color: #2e7d32;
            border: 1px solid #b6e0bd;
        }
        .status-pill.upcoming {
            background: #eef4fd; color: #1565c0;
            border: 1px solid #cfe0f5;
        }
        .status-pill.expired {
            background: #fde6e6; color: #c62828;
            border: 1px solid #f3c8cc;
        }
        .status-pill .dot {
            width: 7px; height: 7px; border-radius: 50%;
            background: currentColor;
        }
        .status-pill.live .dot {
            box-shadow: 0 0 0 3px rgba(46, 125, 50, .2);
            animation: pulse 1.6s infinite;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 3px rgba(46, 125, 50, .2); }
            50%      { box-shadow: 0 0 0 6px rgba(46, 125, 50, 0); }
        }

        .row-actions {
            display: flex; align-items: center; gap: 8px;
            justify-content: flex-end;
        }
        .row-btn {
            width: 34px; height: 34px;
            border-radius: 9px;
            display: inline-flex; align-items: center; justify-content: center;
            border: 1px solid transparent;
            font-size: 14px;
            cursor: pointer; transition: .2s;
            text-decoration: none;
            background: #fff;
        }
        .row-btn.edit {
            color: #1565c0;
            border-color: #cfe0f5;
        }
        .row-btn.edit:hover {
            background: #eef4fd; color: #0d47a1;
        }
        .row-btn.delete {
            color: #b51f2c;
            border-color: #f0d6d8;
        }
        .row-btn.delete:hover {
            background: #fde6e6; color: #c62828;
        }

        .table-empty {
            text-align: center; padding: 50px 20px;
            color: #948c82; font-size: 12px;
        }
        .table-empty i {
            font-size: 40px; color: #d5cbbd;
            display: block; margin-bottom: 12px;
        }

        /* modal */
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
        .mm-modal-icon.danger  { background: #fde6e6; color: #c62828; }

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
        .mm-btn-ghost {
            background: #fff; border: 1px solid #e4ddd3; color: #6f675f;
        }
        .mm-btn-ghost:hover { background: #faf7f0; }
        .mm-btn-danger { background: #c62828; color: #fff; }
        .mm-btn-danger:hover { background: #a02020; }

        @media (max-width: 768px) {
            .menu-page { padding: 20px 15px 30px; }
            .menu-header { flex-direction: column; align-items: flex-start; }
            .menu-card { padding: 15px; border-radius: 16px; }
            .menu-table th,
            .menu-table td { padding: 10px; }
            .menu-table th:nth-child(3),
            .menu-table td:nth-child(3) { display: none; }
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


        <div class="menu-page">

            <div class="menu-header">
                <div class="menu-title">
                    <h1>Menus</h1>
                    <p>All created menus with their product & variant counts.</p>
                </div>

                <a href="add-menu.php" class="btn-add">
                    <i class="bi bi-plus-lg"></i> Add Menu
                </a>
            </div>


            <div class="menu-card">

                <div class="menu-toolbar">

                    <div class="menu-search">
                        <i class="bi bi-search"></i>
                        <input type="text"
                               id="searchInput"
                               placeholder="Search by name or code...">
                    </div>

                    <select id="statusFilter" class="menu-filter">
                        <option value="">All Status</option>
                        <option value="live">Live</option>
                        <option value="upcoming">Upcoming</option>
                        <option value="expired">Expired</option>
                    </select>

                </div>


                <div class="menu-table-wrap">

                    <table class="menu-table" id="menuTable">
                        <thead>
                            <tr>
                                <th>Menu</th>
                                <th>Products</th>
                                <th>Time Window</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="menuTbody">
                            <?php if (empty($menus)): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="table-empty">
                                            <i class="bi bi-journal-x"></i>
                                            No menus yet. Click <strong>Add Menu</strong> to create one.
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($menus as $m):

                                    $t = menuTimingStatus($m['start_at'], $m['end_at']);
                                    ?>
                                    <tr
                                        data-code="<?= htmlspecialchars(strtolower($m['menu_code'])) ?>"
                                        data-name="<?= htmlspecialchars(strtolower($m['menu_name'])) ?>"
                                        data-status="<?= $t['key'] ?>"
                                    >
                                        <td>
                                            <div class="menu-name-cell">
                                                <div class="menu-name-main">
                                                    <?= htmlspecialchars($m['menu_name']) ?>
                                                </div>
                                                <span class="menu-code-tag">
                                                    #<?= htmlspecialchars($m['menu_code']) ?>
                                                </span>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="menu-counts">
                                                <span class="menu-count-item">
                                                    <i class="bi bi-box-seam"></i>
                                                    <strong><?= (int) $m['product_count'] ?></strong> products
                                                </span>
                                                <span class="menu-count-item">
                                                    <i class="bi bi-layers"></i>
                                                    <strong><?= (int) $m['variant_count'] ?></strong> variants
                                                </span>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="menu-dates">
                                                <span class="menu-date-line start">
                                                    <i class="bi bi-play-fill"></i>
                                                    <?= fmtDateTime($m['start_at']) ?>
                                                </span>
                                                <span class="menu-date-line end">
                                                    <i class="bi bi-stop-fill"></i>
                                                    <?= fmtDateTime($m['end_at']) ?>
                                                </span>
                                            </div>
                                        </td>

                                        <td>
                                            <span class="status-pill <?= $t['key'] ?>">
                                                <span class="dot"></span>
                                                <?= $t['label'] ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="row-actions">
                                                <a href="edit-menu.php?id=<?= (int) $m['id'] ?>"
                                                   class="row-btn edit"
                                                   title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button"
                                                        class="row-btn delete js-delete-btn"
                                                        data-id="<?= (int) $m['id'] ?>"
                                                        data-name="<?= htmlspecialchars($m['menu_name']) ?>"
                                                        title="Delete">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                </div>

            </div>

        </div>

    </main>


    <!-- DELETE CONFIRM -->
    <div class="mm-modal-overlay" id="deleteOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog" aria-modal="true">
            <div class="mm-modal-icon danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <h3 class="mm-modal-title">Delete Menu?</h3>
            <p class="mm-modal-text" id="deleteText">
                This will permanently remove the menu and its products.
            </p>
            <div class="mm-modal-actions">
                <button type="button" class="mm-btn mm-btn-ghost" id="deleteCancel">Cancel</button>
                <button type="button" class="mm-btn mm-btn-danger" id="deleteConfirm">
                    <i class="bi bi-trash3"></i> Delete
                </button>
            </div>
        </div>
    </div>


    <!-- ERROR -->
    <div class="mm-modal-overlay" id="errorOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog" aria-modal="true">
            <div class="mm-modal-icon error">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
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
            <div class="mm-modal-icon success">
                <i class="bi bi-check-lg"></i>
            </div>
            <h3 class="mm-modal-title">Deleted!</h3>
            <p class="mm-modal-text" id="successText">Menu deleted successfully.</p>
            <div class="mm-modal-actions">
                <button type="button" class="mm-btn mm-btn-primary" id="successOkBtn">
                    <i class="bi bi-check2"></i> Done
                </button>
            </div>
        </div>
    </div>


    <script>
        window.ADMIN_URL = "<?= ADMIN_URL; ?>";
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL; ?>js/main.js"></script>
    <script src="<?= ADMIN_URL; ?>js/menu.js"></script>

</body>

</html>