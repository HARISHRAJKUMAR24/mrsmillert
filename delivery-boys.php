<?php
require_once './config/config.php';
require_once './config/function.php';

/* ---------------- FETCH ALL DELIVERY BOYS ---------------- */
$stmt = $pdo->query(
    "SELECT b.id, b.delivery_code, b.full_name, b.mobile_number,
            b.email_address, b.status, b.last_login_at, b.created_at,
            b.branch_id, s.branch_name
     FROM delivery_boys b
     LEFT JOIN settings_branches s ON s.id = b.branch_id
     ORDER BY b.id DESC"
);
$boys = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

    <style>
        /* =====================================================
           DELIVERY BOYS LIST PAGE
        ===================================================== */

        .db-page { padding: 30px 32px 40px; }

        .db-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .db-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 5px;
        }

        .db-title p {
            margin: 0;
            color: #817a71;
            font-size: 13px;
        }

        .btn-add {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #b51f2c;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 11px 18px;
            font-size: 11.5px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: .2s;
            box-shadow: 0 8px 20px rgba(181, 31, 44, .18);
        }

        .btn-add:hover {
            background: #8e1722;
            color: #fff;
            transform: translateY(-1px);
        }

        .db-card {
            background: #fff;
            border: 1px solid #eee7dc;
            border-radius: 20px;
            padding: 6px;
            overflow: hidden;
        }

        /* =====================================================
           TABLE
        ===================================================== */

        .db-table-wrap {
            overflow-x: auto;
            border-radius: 16px;
        }

        .db-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        .db-table thead th {
            text-align: left;
            padding: 14px 16px;
            background: #fdfaf4;
            color: #8a7f73;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            border-bottom: 1px solid #f0ebe4;
            white-space: nowrap;
        }

        .db-table tbody tr {
            transition: background .2s ease;
            border-bottom: 1px solid #f4efe8;
        }

        .db-table tbody tr:last-child { border-bottom: 0; }
        .db-table tbody tr:hover { background: #fffcf5; }

        .db-table tbody td {
            padding: 14px 16px;
            font-size: 12px;
            color: #4e4841;
            vertical-align: middle;
        }

        /* ---- BOY cell ---- */
        .boy-cell {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 220px;
        }

        .boy-avatar {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Playfair Display", serif;
            font-weight: 700;
            font-size: 17px;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(181, 31, 44, .22);
        }

        .boy-info { min-width: 0; }

        .boy-name {
            font-weight: 700;
            color: #302923;
            font-size: 12.5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }

        .boy-code {
            font-size: 10px;
            color: #948c82;
            font-weight: 600;
            margin-top: 2px;
            letter-spacing: .4px;
        }

        /* ---- MOBILE ---- */
        .mobile-cell {
            font-weight: 600;
            color: #4e4841;
            white-space: nowrap;
        }

        .mobile-cell i {
            color: #b51f2c;
            margin-right: 5px;
            font-size: 12px;
        }

        /* ---- BRANCH chip ---- */
        .branch-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #faf7f0;
            border: 1px solid #eee7dc;
            color: #6f5a3f;
            font-size: 10.5px;
            font-weight: 700;
            padding: 5px 10px;
            border-radius: 20px;
            white-space: nowrap;
        }

        .branch-chip i { font-size: 11px; color: #b8893c; }

        .branch-chip.empty {
            color: #aaa198;
            border-style: dashed;
        }

        /* ---- STATUS DROPDOWN ---- */
        .status-select {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .status-select select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            height: 34px;
            padding: 0 32px 0 30px;
            border-radius: 20px;
            font-family: "DM Sans", sans-serif;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .4px;
            cursor: pointer;
            outline: none;
            border: 1.5px solid transparent;
            transition: .2s ease;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23817a71' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
        }

        .status-select::before {
            content: "";
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 7px;
            height: 7px;
            border-radius: 50%;
            pointer-events: none;
            background: var(--dot-color, #b51f2c);
        }

        .status-select[data-status="1"] { --dot-color: #2e7d32; }
        .status-select[data-status="0"] { --dot-color: #b51f2c; }

        .status-select select[data-status="1"] {
            background-color: #e8f6ea;
            color: #2e7d32;
            border-color: #cbe7ce;
        }
        .status-select select[data-status="1"]:hover { border-color: #8fce97; }

        .status-select select[data-status="0"] {
            background-color: #fdecea;
            color: #b51f2c;
            border-color: #f4cdcd;
        }
        .status-select select[data-status="0"]:hover { border-color: #e8a5a5; }

        .status-select select:focus {
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .08);
        }

        .status-select select:disabled {
            opacity: .7;
            cursor: wait;
        }

        /* ---- ACTIONS ---- */
        .row-actions {
            display: flex;
            gap: 6px;
            align-items: center;
            justify-content: flex-end;
        }

        .btn-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            border: 1.5px solid #ece5da;
            background: #fff;
            color: #6f675f;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            cursor: pointer;
            transition: .2s ease;
            text-decoration: none;
        }

        .btn-icon.edit:hover {
            border-color: #52745b;
            color: #52745b;
            background: #e8f1e8;
        }

        /* ---- EMPTY STATE ---- */
        .db-empty {
            text-align: center;
            padding: 60px 20px;
            color: #948c82;
        }

        .db-empty i {
            font-size: 50px;
            color: #ece5da;
            display: block;
            margin-bottom: 12px;
        }

        .db-empty h3 {
            margin: 0 0 5px;
            font-size: 15px;
            color: #6f675f;
            font-weight: 700;
        }

        .db-empty p { margin: 0; font-size: 12px; }

        /* ---- TOAST ---- */
        #mmToast {
            position: fixed;
            bottom: 26px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            padding: 12px 22px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
            font-family: "DM Sans", sans-serif;
            color: #fff;
            background: #2e7d32;
            box-shadow: 0 15px 40px rgba(0,0,0,.25);
            z-index: 99999;
            opacity: 0;
            pointer-events: none;
            transition: opacity .25s ease, transform .25s ease;
            max-width: 90vw;
            text-align: center;
        }

        #mmToast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        #mmToast.error { background: #b51f2c; }

        @media (max-width: 768px) {
            .db-page { padding: 20px 15px 30px; }
            .db-header { flex-direction: column; align-items: flex-start; }
            .db-card { padding: 4px; border-radius: 16px; }
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
                <input type="text"
                    id="dbSearch"
                    placeholder="Search by name, code or mobile...">
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


        <div class="db-page">

            <div class="db-header">

                <div class="db-title">
                    <h1>Delivery Boys</h1>
                    <p>Manage all delivery boys — add new, edit details or change status.</p>
                </div>

                <a href="add-delivery-boy.php" class="btn-add">
                    <i class="bi bi-plus-lg"></i>
                    Add Delivery Boy
                </a>

            </div>


            <div class="db-card">

                <?php if (empty($boys)): ?>

                    <div class="db-empty">
                        <i class="bi bi-people"></i>
                        <h3>No delivery boys yet</h3>
                        <p>Click <strong>Add Delivery Boy</strong> to get started.</p>
                    </div>

                <?php else: ?>

                    <div class="db-table-wrap">

                        <table class="db-table" id="dbTable">

                            <thead>
                                <tr>
                                    <th>Delivery Boy</th>
                                    <th>Mobile</th>
                                    <th>Branch</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($boys as $b): ?>

                                    <?php
                                        $initial = strtoupper(substr($b['full_name'] ?: 'D', 0, 1));
                                        $status  = (int) $b['status'];

                                        $lastLogin = '—';
                                        if (!empty($b['last_login_at'])) {
                                            $lastLogin = date('d M Y · h:i A', strtotime($b['last_login_at']));
                                        }
                                    ?>

                                    <tr data-id="<?= (int)$b['id'] ?>"
                                        data-search="<?= htmlspecialchars(strtolower(
                                            ($b['full_name'] ?? '') . ' ' .
                                            ($b['delivery_code'] ?? '') . ' ' .
                                            ($b['mobile_number'] ?? '')
                                        ), ENT_QUOTES) ?>">

                                        <td>
                                            <div class="boy-cell">
                                                <div class="boy-avatar"><?= htmlspecialchars($initial) ?></div>
                                                <div class="boy-info">
                                                    <div class="boy-name"><?= htmlspecialchars($b['full_name']) ?></div>
                                                    <div class="boy-code">#<?= htmlspecialchars($b['delivery_code']) ?></div>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="mobile-cell">
                                                <i class="bi bi-telephone-fill"></i>
                                                <?= htmlspecialchars($b['mobile_number']) ?>
                                            </div>
                                        </td>

                                        <td>
                                            <?php if (!empty($b['branch_name'])): ?>
                                                <span class="branch-chip">
                                                    <i class="bi bi-shop"></i>
                                                    <?= htmlspecialchars($b['branch_name']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="branch-chip empty">
                                                    <i class="bi bi-dash-circle"></i>
                                                    Not assigned
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div class="status-select" data-status="<?= $status ?>">
                                                <select class="boy-status"
                                                        data-id="<?= (int)$b['id'] ?>"
                                                        data-status="<?= $status ?>">
                                                    <option value="1" <?= $status === 1 ? 'selected' : '' ?>>Active</option>
                                                    <option value="0" <?= $status === 0 ? 'selected' : '' ?>>Inactive</option>
                                                </select>
                                            </div>
                                        </td>

                                        <td style="color:#817a71;font-size:11px;">
                                            <?= htmlspecialchars($lastLogin) ?>
                                        </td>

                                        <td>
                                            <div class="row-actions">
                                                <a href="edit-delivery-boy.php?id=<?= (int)$b['id'] ?>"
                                                   class="btn-icon edit"
                                                   title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            </div>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </main>


    <script>
        window.ADMIN_URL = "<?= ADMIN_URL; ?>";
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL; ?>js/main.js"></script>
    <script src="<?= ADMIN_URL; ?>js/delivery-boys.js"></script>

</body>

</html>