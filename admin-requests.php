<?php
require_once './config/config.php';
require_once './config/function.php';

if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    header('Location: login.php');
    exit;
}

$settings = getSettings($pdo);

/* ---------------- FILTERS ---------------- */
$statusFilter = trim($_GET['status'] ?? 'pending');
$dateFilter   = trim($_GET['date'] ?? 'all');

$where = [];
$params = [];

// Status Filter
if ($statusFilter === 'pending') {
    $where[] = "r.status = 'pending'";
} elseif ($statusFilter === 'approved') {
    $where[] = "r.status = 'approved'";
} elseif ($statusFilter === 'rejected') {
    $where[] = "r.status = 'rejected'";
} else {
    $statusFilter = 'all';
}

// Date Filter
if ($dateFilter === 'today') {
    $where[] = "DATE(r.created_at) = CURDATE()";
} elseif ($dateFilter === 'yesterday') {
    $where[] = "DATE(r.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
} elseif ($dateFilter === 'week') {
    $where[] = "r.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)";
} else {
    $dateFilter = 'all';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Fetch Requests
$requests = [];
try {
    $stmt = $pdo->prepare(
        "SELECT r.*, b.full_name AS boy_name, b.delivery_code 
         FROM delivery_boy_requests r
         LEFT JOIN delivery_boys b ON b.id = r.delivery_boy_id
         $whereSql
         ORDER BY r.id DESC"
    );
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include './includes/head.php'; ?>
    <style>
        .ar-page {
            padding: 30px 32px 40px;
        }

        .ar-head {
            margin-bottom: 25px;
        }

        .ar-head h1 {
            font-family: "Playfair Display", serif;
            font-size: 28px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 5px;
        }

        .ar-head p {
            margin: 0;
            color: #817a71;
            font-size: 13px;
        }

        /* Filters */
        .ar-filters {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .ar-tabs {
            display: flex;
            background: #fdfaf4;
            border: 1.5px solid #ece5da;
            border-radius: 11px;
            padding: 4px;
            gap: 4px;
            flex-wrap: wrap;
        }

        .ar-tab {
            border: none;
            background: transparent;
            padding: 8px 14px;
            font-family: "DM Sans", sans-serif;
            font-size: 11.5px;
            font-weight: 700;
            color: #6f675f;
            border-radius: 8px;
            cursor: pointer;
            transition: .2s ease;
            white-space: nowrap;
        }

        .ar-tab:hover {
            color: #b51f2c;
        }

        .ar-tab.active {
            background: #fff;
            color: #b51f2c;
            box-shadow: 0 4px 12px rgba(48, 41, 35, .08);
        }

        .ar-date-filter {
            position: relative;
            display: inline-flex;
            align-items: center;
            height: 40px;
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 11px;
            padding: 0 30px 0 34px;
            transition: .2s ease;
        }

        .ar-date-filter:focus-within {
            border-color: #b51f2c;
            box-shadow: 0 0 0 4px rgba(181, 31, 44, .08);
        }

        .ar-date-filter>i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0a79c;
            font-size: 14px;
            pointer-events: none;
        }

        .ar-date-filter select {
            appearance: none;
            -webkit-appearance: none;
            border: none;
            background: transparent;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            font-weight: 700;
            color: #4e4841;
            outline: none;
            cursor: pointer;
            padding-right: 18px;
            height: 100%;
        }

        .ar-date-filter::after {
            content: "";
            position: absolute;
            right: 11px;
            top: 50%;
            width: 8px;
            height: 8px;
            border-right: 2px solid #b0a79c;
            border-bottom: 2px solid #b0a79c;
            transform: translateY(-70%) rotate(45deg);
            pointer-events: none;
        }

        .ar-table-wrap {
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 18px;
            overflow: hidden;
        }

        .ar-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .ar-table th {
            text-align: left;
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            color: #948c82;
            background: #fdfaf4;
            padding: 14px 16px;
            border-bottom: 1.5px solid #ece5da;
        }

        .ar-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f5efe5;
            color: #302923;
            vertical-align: middle;
        }

        .ar-table tr:hover {
            background: #fffaf5;
        }

        .ar-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .ar-badge.container {
            background: #fdf1e2;
            color: #a35a0e;
        }

        .ar-badge.credit {
            background: #e8f6ea;
            color: #1f7a3d;
        }

        .ar-badge.debit {
            background: #fbeaea;
            color: #b51f2c;
        }

        .ar-badge.status-pending {
            background: #fdf1e2;
            color: #a35a0e;
        }

        .ar-badge.status-approved {
            background: #e8f6ea;
            color: #1f7a3d;
        }

        .ar-badge.status-rejected {
            background: #fbeaea;
            color: #b51f2c;
        }

        .ar-btn {
            height: 34px;
            padding: 0 14px;
            border-radius: 9px;
            border: none;
            font-family: "DM Sans", sans-serif;
            font-size: 11.5px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: .15s;
        }

        .ar-btn.approve {
            background: #1f7a3d;
            color: #fff;
        }

        .ar-btn.approve:hover {
            background: #155c2c;
        }

        .ar-btn.reject {
            background: #fff;
            border: 1.5px solid #ece5da;
            color: #b51f2c;
        }

        .ar-btn.reject:hover {
            background: #fbeaea;
            border-color: #f1c8cc;
        }

        .ar-btn.locked {
            background: #f4efe8;
            border: 1.5px solid #d5cbbd;
            color: #948c82;
            cursor: not-allowed;
        }

        /* Modal */
        .ar-modal-overlay {
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
            transition: .22s;
        }

        .ar-modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .ar-modal {
            background: #fff;
            border-radius: 20px;
            width: 100%;
            max-width: 480px;
            padding: 24px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, .28);
            transform: translateY(15px) scale(.97);
            transition: transform .25s;
        }

        .ar-modal-overlay.show .ar-modal {
            transform: translateY(0) scale(1);
        }

        .ar-modal h3 {
            font-family: "Playfair Display", serif;
            font-size: 20px;
            margin: 0 0 10px;
        }

        .ar-modal textarea {
            width: 100%;
            height: 80px;
            border: 1.5px solid #ece5da;
            border-radius: 10px;
            padding: 10px;
            font-family: inherit;
            font-size: 13px;
            margin-bottom: 15px;
            resize: none;
            outline: none;
        }

        .ar-modal textarea:focus {
            border-color: #b51f2c;
        }

        .ar-modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .ar-toast {
            position: fixed;
            top: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(-20px);
            padding: 12px 24px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            background: #1f7a3d;
            z-index: 99999;
            opacity: 0;
            transition: .25s;
            pointer-events: none;
        }

        .ar-toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        .ar-toast.error {
            background: #b51f2c;
        }
    </style>
</head>

<body>

    <?php include './templates/sidebar.php'; ?>

    <main class="main">
        <header class="topbar">
            <button class="mobile-menu" onclick="toggleSidebar()" aria-label="Open menu"><i class="bi bi-list"></i></button>
            <div class="search-box"><i class="bi bi-search"></i><input type="text" placeholder="Search..."></div>
            <div class="top-right">
                <div class="admin-profile">
                    <div class="admin-avatar">A</div>
                    <div>
                        <div class="admin-name">Admin</div>
                        <div class="admin-role">Manager</div>
                    </div>
                </div>
            </div>
        </header>

        <div class="ar-page">
            <div class="ar-head">
                <h1>Delivery Boy Requests</h1>
                <p>Review and approve container returns or wallet adjustments requested by delivery staff.</p>
            </div>

            <!-- FILTERS -->
            <div class="ar-filters">
                <div class="ar-tabs">
                    <button class="ar-tab <?= $statusFilter === 'pending' ? 'active' : '' ?>" data-filter="pending">Pending</button>
                    <button class="ar-tab <?= $statusFilter === 'approved' ? 'active' : '' ?>" data-filter="approved">Approved</button>
                    <button class="ar-tab <?= $statusFilter === 'rejected' ? 'active' : '' ?>" data-filter="rejected">Rejected</button>
                    <button class="ar-tab <?= $statusFilter === 'all' ? 'active' : '' ?>" data-filter="all">All</button>
                </div>

                <div class="ar-date-filter">
                    <i class="bi bi-calendar3"></i>
                    <select id="dateFilter">
                        <option value="all" <?= $dateFilter === 'all' ? 'selected' : '' ?>>All Dates</option>
                        <option value="today" <?= $dateFilter === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="yesterday" <?= $dateFilter === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                        <option value="week" <?= $dateFilter === 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                    </select>
                </div>
            </div>

            <?php if (empty($requests)): ?>
                <div style="background:#fff; border:1.5px solid #ece5da; border-radius:18px; padding:60px; text-align:center; color:#948c82;">
                    <i class="bi bi-inbox" style="font-size:40px; color:#ece5da; display:block; margin-bottom:10px;"></i>
                    <h3 style="margin:0 0 5px; font-size:16px; font-weight:700;">No requests found</h3>
                    <p style="margin:0; font-size:13px;">Nothing matches your filter or search.</p>
                </div>
            <?php else: ?>
                <div class="ar-table-wrap">
                    <table class="ar-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Delivery Boy</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Details</th>
                                <th>Status</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $r):
                                $typeLabel = 'Unknown';
                                $typeClass = '';
                                if ($r['request_type'] === 'container_return') {
                                    $typeLabel = 'Container Return';
                                    $typeClass = 'container';
                                }
                                if ($r['request_type'] === 'wallet_credit') {
                                    $typeLabel = 'Wallet Add';
                                    $typeClass = 'credit';
                                }
                                if ($r['request_type'] === 'wallet_debit') {
                                    $typeLabel = 'Wallet Deduct';
                                    $typeClass = 'debit';
                                }

                                $details = '';
                                if ($r['request_type'] === 'container_return') $details = $r['container_count'] . ' pcs';
                                else $details = '₹' . number_format($r['amount'], 2);

                                $statusCls = 'status-' . $r['status'];

                                // 5-hour lock check
                                $isLocked = false;
                                if ($r['status'] === 'approved' && !empty($r['updated_at'])) {
                                    $updatedTs = strtotime($r['updated_at']);
                                    if ($updatedTs && (time() - $updatedTs) > (5 * 3600)) {
                                        $isLocked = true;
                                    }
                                }
                            ?>
                                <tr id="row-<?= $r['id'] ?>">
                                    <td>
                                        <div style="font-weight:700; color:#302923;"><?= date('d M Y', strtotime($r['created_at'])) ?></div>
                                        <div style="font-size:10.5px; color:#948c82;"><?= date('h:i A', strtotime($r['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight:700; color:#b51f2c;"><?= htmlspecialchars($r['boy_name'] ?: 'Unknown') ?></div>
                                        <div style="font-size:10.5px; color:#948c82;">#<?= htmlspecialchars($r['delivery_code'] ?: '-') ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight:700;"><?= htmlspecialchars($r['customer_name']) ?></div>
                                        <div style="font-size:10.5px; color:#948c82;"><?= htmlspecialchars($r['customer_mobile']) ?></div>
                                    </td>
                                    <td><span class="ar-badge <?= $typeClass ?>"><?= $typeLabel ?></span></td>
                                    <td style="font-weight:800; color:#302923;">
                                        <?= $details ?>
                                        <?php if ($r['note']): ?>
                                            <div style="font-size:10.5px; color:#948c82; font-weight:600; margin-top:4px;"><?= htmlspecialchars($r['note']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="ar-badge <?= $statusCls ?>"><?= ucfirst($r['status']) ?></span></td>
                                    <td>
                                        <div style="display:flex; gap:6px; justify-content:flex-end;">
                                            <?php if ($r['status'] === 'pending'): ?>
                                                <button type="button" class="ar-btn reject" onclick="openActionModal(<?= $r['id'] ?>, 'reject')">
                                                    <i class="bi bi-x-lg"></i> Reject
                                                </button>
                                                <button type="button" class="ar-btn approve" onclick="openActionModal(<?= $r['id'] ?>, 'approve')">
                                                    <i class="bi bi-check-lg"></i> Approve
                                                </button>
                                            <?php elseif ($r['status'] === 'approved'): ?>
                                                <?php if ($isLocked): ?>
                                                    <button type="button" class="ar-btn locked" disabled title="Action locked (5 hours passed)">
                                                        <i class="bi bi-lock-fill"></i> Locked
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="ar-btn reject" onclick="openActionModal(<?= $r['id'] ?>, 'unapprove')">
                                                        <i class="bi bi-arrow-counterclockwise"></i> Unapprove
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="font-size:11px; color:#948c82; font-weight:600; font-style:italic;">
                                                    Rejected
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Action Modal -->
    <div class="ar-modal-overlay" id="actionModal">
        <div class="ar-modal">
            <h3 id="modalTitle">Approve Request?</h3>
            <p style="font-size:13px; color:#817a71; margin-bottom:15px;" id="modalDesc">This action cannot be undone.</p>

            <input type="hidden" id="actionReqId" value="">
            <input type="hidden" id="actionType" value="">

            <label style="display:block; font-size:11px; font-weight:800; color:#302923; margin-bottom:6px; text-transform:uppercase;">Admin Note (Optional)</label>
            <textarea id="actionNote" placeholder="Add a reason or remark..."></textarea>

            <div class="ar-modal-actions">
                <button type="button" class="ar-btn reject" onclick="closeActionModal()">Cancel</button>
                <button type="button" class="ar-btn approve" id="confirmBtn" onclick="submitAction()">Confirm</button>
            </div>
        </div>
    </div>

    <div class="ar-toast" id="arToast"></div>
    <script>
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
    </script>
    <script src="<?= ADMIN_URL ?>js/main.js"></script>

    <script>
        const modal = document.getElementById('actionModal');
        const reqIdInp = document.getElementById('actionReqId');
        const typeInp = document.getElementById('actionType');
        const noteInp = document.getElementById('actionNote');
        const title = document.getElementById('modalTitle');
        const desc = document.getElementById('modalDesc');
        const confirmBtn = document.getElementById('confirmBtn');
        const toast = document.getElementById('arToast');

        function openActionModal(id, type) {
            reqIdInp.value = id;
            typeInp.value = type;
            noteInp.value = '';

            if (type === 'approve') {
                title.textContent = 'Approve Request?';
                desc.textContent = 'This will immediately process the wallet/container changes.';
                confirmBtn.style.background = '#1f7a3d';
                confirmBtn.textContent = 'Yes, Approve';
            } else if (type === 'unapprove') {
                title.textContent = 'Unapprove Request?';
                desc.textContent = 'This will reverse the wallet/container changes and move the request back to Pending.';
                confirmBtn.style.background = '#b8893c';
                confirmBtn.textContent = 'Yes, Unapprove';
            } else {
                title.textContent = 'Reject Request?';
                desc.textContent = 'The delivery boy will be notified that this was rejected.';
                confirmBtn.style.background = '#b51f2c';
                confirmBtn.textContent = 'Yes, Reject';
            }
            modal.classList.add('show');
        }

        function closeActionModal() {
            modal.classList.remove('show');
        }

        function showToast(msg, isError) {
            toast.textContent = msg;
            toast.className = 'ar-toast show' + (isError ? ' error' : '');
            setTimeout(() => toast.className = 'ar-toast', 3000);
        }

        function submitAction() {
            const id = reqIdInp.value;
            const type = typeInp.value;
            const note = noteInp.value.trim();

            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Processing...';

            const fd = new FormData();
            fd.append('request_id', id);
            fd.append('action', type);
            fd.append('admin_note', note);

            fetch(window.ADMIN_URL + 'ajax/admin-process-request.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                })
                .then(r => r.json().catch(() => null))
                .then(res => {
                    confirmBtn.disabled = false;
                    if (!res || !res.success) {
                        showToast((res && res.message) || 'Failed.', true);
                        confirmBtn.textContent = type === 'approve' ? 'Yes, Approve' : (type === 'unapprove' ? 'Yes, Unapprove' : 'Yes, Reject');
                        return;
                    }
                    showToast(res.message);
                    closeActionModal();

                    // Reload page to show updated state
                    setTimeout(() => window.location.reload(), 600);
                })
                .catch(() => {
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = type === 'approve' ? 'Yes, Approve' : (type === 'unapprove' ? 'Yes, Unapprove' : 'Yes, Reject');
                    showToast('Unable to connect.', true);
                });
        }

        // Filter logic
        document.querySelectorAll('.ar-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const status = this.dataset.filter;
                const date = document.getElementById('dateFilter').value;
                window.location.href = `?status=${status}&date=${date}`;
            });
        });

        document.getElementById('dateFilter').addEventListener('change', function() {
            const date = this.value;
            const activeTab = document.querySelector('.ar-tab.active');
            const status = activeTab ? activeTab.dataset.filter : 'pending';
            window.location.href = `?status=${status}&date=${date}`;
        });
    </script>
</body>

</html>