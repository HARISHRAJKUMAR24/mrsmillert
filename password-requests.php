<?php
require_once './config/config.php';
require_once './config/function.php';

// Ensure only logged-in admin can access
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

/* =========================================================
   1. AUTO-DELETE DATA OLDER THAN 1 MONTH (30 DAYS)
   ========================================================= */
try {
    $pdo->exec("DELETE FROM password_reset_requests WHERE created_at < (NOW() - INTERVAL 1 MONTH)");
} catch (PDOException $e) {
    // Silently fail if table doesn't exist yet
}

/* =========================================================
   2. AJAX HANDLER FOR STATUS UPDATE
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    $status = (int)($_POST['status'] ?? 0);

    try {
        $stmt = $pdo->prepare("UPDATE password_reset_requests SET status = ?, resolved_at = IF(? = 1, NOW(), NULL), resolved_by_id = ? WHERE id = ?");
        $stmt->execute([$status, $status, $_SESSION['admin_id'] ?? 0, $id]);
        echo json_encode(['success' => true, 'message' => 'Status updated successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

/* =========================================================
   3. FETCH REQUESTS & CUSTOMER DETAILS
   ========================================================= */
$requests = [];
try {
    $stmt = $pdo->query(
        "SELECT r.*, c.id as customer_db_id, c.full_name as customer_full_name
         FROM password_reset_requests r
         LEFT JOIN customers c ON r.mobile_number = c.mobile_number
         ORDER BY r.id DESC"
    );
    $requests = $stmt->fetchAll();
} catch (PDOException $e) {
    $requests = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include './includes/head.php'; ?>
    <style>
        .page-container {
            padding: 30px 32px 40px;
        }

        .page-header {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header h1 {
            font-family: "Playfair Display", serif;
            font-size: 29px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 5px;
        }

        .page-header p {
            margin: 0;
            color: #817a71;
            font-size: 13px;
        }

        .card {
            background: #fff;
            border: 1px solid #eee7dc;
            border-radius: 20px;
            padding: 22px;
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            gap: 12px;
            margin-bottom: 18px;
            flex-wrap: wrap;
            align-items: center;
        }

        .toolbar input,
        .toolbar select {
            height: 40px;
            border: 1px solid #e8e1d8;
            background: #fffdf9;
            border-radius: 10px;
            padding: 0 12px;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            color: #292521;
            outline: none;
            transition: .2s;
        }

        .toolbar input:focus,
        .toolbar select:focus {
            border-color: #d98a91;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .toolbar input {
            flex: 1;
            min-width: 200px;
        }

        .toolbar select {
            cursor: pointer;
            min-width: 130px;
            font-weight: 600;
        }

        /* Table */
        .table-wrap {
            border: 1px solid #f0ebe4;
            border-radius: 14px;
            overflow: hidden;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-family: "DM Sans", sans-serif;
            min-width: 800px;
        }

        thead {
            background: #faf7f0;
        }

        th {
            text-align: left;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #6f675f;
            padding: 12px 14px;
            border-bottom: 1px solid #f0ebe4;
            white-space: nowrap;
        }

        td {
            font-size: 12px;
            color: #302923;
            padding: 14px;
            border-bottom: 1px solid #f5efe6;
            vertical-align: middle;
        }

        tbody tr:hover {
            background: #fffaf9;
        }

        /* Status Badges */
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-pending {
            background: #fff8e1;
            color: #8a6d00;
            border: 1px solid #ffe0a3;
        }

        .badge-resolved {
            background: #e8f6ea;
            color: #2e7d32;
            border: 1px solid #b6e0bd;
        }

        /* Inline status select */
        .status-select {
            height: 32px;
            border-radius: 8px;
            border: 1px solid #e8e1d8;
            font-family: inherit;
            font-size: 11px;
            font-weight: 700;
            padding: 0 24px 0 10px;
            outline: none;
            cursor: pointer;
            appearance: none;
            background: #fffdf9 url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 16 16'><path fill='%23817a71' d='M8 11L3 6h10z'/></svg>") no-repeat right 8px center;
            transition: .2s;
        }

        .status-select:focus {
            border-color: #d98a91;
        }

        .status-select.pending {
            border-color: #ffe0a3;
            background-color: #fff8e1;
            color: #8a6d00;
        }

        .status-select.resolved {
            border-color: #b6e0bd;
            background-color: #e8f6ea;
            color: #2e7d32;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #b51f2c;
            color: #fff;
            padding: 7px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 700;
            transition: .2s;
            border: none;
            cursor: pointer;
        }

        .btn-primary:hover {
            background: #8e1722;
            color: #fff;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #948c82;
            font-size: 12px;
        }

        .empty-state i {
            font-size: 40px;
            color: #d5cbbd;
            display: block;
            margin-bottom: 12px;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            font-size: 11px;
            color: #817a71;
        }

        .pagination span strong {
            color: #302923;
        }

        .page-btns {
            display: flex;
            gap: 4px;
        }

        .page-btns button {
            min-width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid #eee7dc;
            background: #fff;
            color: #6f675f;
            font-weight: 700;
            cursor: pointer;
            transition: .2s;
        }

        .page-btns button:hover:not(:disabled):not(.active) {
            background: #faf7f0;
        }

        .page-btns button.active {
            background: #b51f2c;
            border-color: #b51f2c;
            color: #fff;
            cursor: default;
        }

        .page-btns button:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        /* =====================================================
           CUSTOM MODALS (Copied from menu.js style)
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
            transition: .2s;
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
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin: 0 auto 16px;
            animation: popIn .35s cubic-bezier(.2, .9, .3, 1.4);
        }

        .mm-modal-icon.success {
            background: #e8f6ea;
            color: #2e7d32;
        }

        .mm-modal-icon.error {
            background: #fde6e6;
            color: #c62828;
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
        }

        .mm-btn-primary:hover {
            background: #8e1722;
            color: #fff;
        }
    </style>
</head>

<body>
    <?php include './templates/sidebar.php'; ?>
    <main class="main">
        <?php include './templates/header.php'; ?>

        <div class="page-container">
            <div class="page-header">
                <div>
                    <h1>Password Reset Requests</h1>
                    <p>Customers who forgot their password. Click a row to open their profile and reset it.</p>
                </div>
            </div>

            <div class="card">
                <!-- Toolbar -->
                <div class="toolbar">
                    <input type="text" id="searchInput" placeholder="Search by mobile or name...">
                    <select id="dateFilter">
                        <option value="all">All Dates</option>
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="7days">Last 7 Days</option>
                        <option value="30days">Last 30 Days</option>
                    </select>
                    <select id="statusFilter">
                        <option value="all">All Status</option>
                        <option value="0">Pending</option>
                        <option value="1">Resolved</option>
                    </select>
                </div>

                <!-- Table -->
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Requested At</th>
                                <th>Mobile Number</th>
                                <th>Customer Name</th>
                                <th>Status</th>
                                <th>Change Status</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <i class="bi bi-shield-check"></i>
                                            No password reset requests found.
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($requests as $r):
                                    $statusKey = $r['status'] == 1 ? 'resolved' : 'pending';
                                    $statusText = $r['status'] == 1 ? 'Resolved' : 'Pending';
                                ?>
                                    <tr data-search="<?= strtolower(htmlspecialchars($r['mobile_number'] . ' ' . $r['customer_full_name'])) ?>"
                                        data-date="<?= date('Y-m-d', strtotime($r['created_at'])) ?>"
                                        data-status="<?= $r['status'] ?>">
                                        <td><?= date('d M Y, h:i A', strtotime($r['created_at'])) ?></td>
                                        <td><strong style="font-size: 13px;"><?= htmlspecialchars($r['mobile_number']) ?></strong></td>
                                        <td>
                                            <?= $r['customer_full_name'] ? htmlspecialchars($r['customer_full_name']) : '<span style="color:#b5aca2;">Not registered yet</span>' ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $statusKey ?> js-status-badge"><?= $statusText ?></span>
                                        </td>
                                        <td>
                                            <select class="status-select <?= $statusKey ?> js-status-change" data-id="<?= $r['id'] ?>">
                                                <option value="0" <?= $r['status'] == 0 ? 'selected' : '' ?>>Pending</option>
                                                <option value="1" <?= $r['status'] == 1 ? 'selected' : '' ?>>Resolved</option>
                                            </select>
                                        </td>
                                        <td style="text-align:right;">
                                            <?php if ($r['customer_db_id']): ?>
                                                <a href="edit-customer.php?id=<?= (int)$r['customer_db_id'] ?>&reset=1" class="btn-primary">
                                                    <i class="bi bi-person-gear"></i> Manage
                                                </a>
                                            <?php else: ?>
                                                <span style="font-size: 11px; color: #948c82;">No account</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination" id="paginationWrap" style="display:none;">
                    <span id="pageInfo">Showing 0 of 0</span>
                    <div class="page-btns" id="pageBtns"></div>
                </div>
            </div>
        </div>
    </main>

    <!-- ================= CUSTOM SUCCESS MODAL ================= -->
    <div class="mm-modal-overlay" id="successOverlay" aria-hidden="true">
        <div class="mm-modal" role="dialog" aria-modal="true">
            <div class="mm-modal-icon success"><i class="bi bi-check-lg"></i></div>
            <h3 class="mm-modal-title" id="successTitle">Done!</h3>
            <p class="mm-modal-text" id="successText">Saved successfully.</p>
            <div class="mm-modal-actions">
                <button type="button" class="mm-btn mm-btn-primary" id="successOkBtn">
                    <i class="bi bi-check2"></i> Got it
                </button>
            </div>
        </div>
    </div>

    <!-- ================= CUSTOM ERROR MODAL ================= -->
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elements
            const searchInput = document.getElementById('searchInput');
            const dateFilter = document.getElementById('dateFilter');
            const statusFilter = document.getElementById('statusFilter');
            const tableBody = document.getElementById('tableBody');
            const paginationWrap = document.getElementById('paginationWrap');
            const pageInfo = document.getElementById('pageInfo');
            const pageBtns = document.getElementById('pageBtns');

            // Modals
            const successOverlay = document.getElementById('successOverlay');
            const successTitle = document.getElementById('successTitle');
            const successText = document.getElementById('successText');
            const successOkBtn = document.getElementById('successOkBtn');

            const errorOverlay = document.getElementById('errorOverlay');
            const errorTitle = document.getElementById('errorTitle');
            const errorText = document.getElementById('errorText');
            const errorOkBtn = document.getElementById('errorOkBtn');

            let allRows = Array.from(tableBody.querySelectorAll('tr[data-search]'));
            let filteredRows = [...allRows];
            let currentPage = 1;
            const rowsPerPage = 10;

            // Modal Functions
            function showSuccess(title, text) {
                successTitle.textContent = title || "Done!";
                successText.textContent = text || "Saved successfully.";
                successOverlay.classList.add("show");
                successOverlay.setAttribute("aria-hidden", "false");
            }

            function showError(title, text) {
                errorTitle.textContent = title || "Oops!";
                errorText.textContent = text || "Something went wrong.";
                errorOverlay.classList.add("show");
                errorOverlay.setAttribute("aria-hidden", "false");
            }

            // Close Buttons
            if (successOkBtn) successOkBtn.addEventListener("click", () => {
                successOverlay.classList.remove("show");
                successOverlay.setAttribute("aria-hidden", "true");
            });
            if (successOverlay) successOverlay.addEventListener("click", e => {
                if (e.target === successOverlay) {
                    successOverlay.classList.remove("show");
                    successOverlay.setAttribute("aria-hidden", "true");
                }
            });

            if (errorOkBtn) errorOkBtn.addEventListener("click", () => {
                errorOverlay.classList.remove("show");
                errorOverlay.setAttribute("aria-hidden", "true");
            });
            if (errorOverlay) errorOverlay.addEventListener("click", e => {
                if (e.target === errorOverlay) {
                    errorOverlay.classList.remove("show");
                    errorOverlay.setAttribute("aria-hidden", "true");
                }
            });

            // 🔹 Date Logic
            function getDateTargets() {
                const now = new Date();
                const today = new Date(now.getFullYear(), now.getMonth(), now.getDate()).toISOString().split('T')[0];

                const yesterdayDate = new Date(now);
                yesterdayDate.setDate(yesterdayDate.getDate() - 1);
                const yesterday = new Date(yesterdayDate.getFullYear(), yesterdayDate.getMonth(), yesterdayDate.getDate()).toISOString().split('T')[0];

                return {
                    today,
                    yesterday
                };
            }

            function filterTable() {
                const search = searchInput.value.toLowerCase().trim();
                const dateVal = dateFilter.value;
                const statusVal = statusFilter.value;
                const {
                    today,
                    yesterday
                } = getDateTargets();

                filteredRows = allRows.filter(row => {
                    // Search filter
                    const textMatch = row.dataset.search.includes(search);
                    if (!textMatch) return false;

                    // Status filter
                    if (statusVal !== 'all' && row.dataset.status !== statusVal) return false;

                    // Date filter
                    const rowDate = row.dataset.date;
                    if (dateVal === 'today' && rowDate !== today) return false;
                    if (dateVal === 'yesterday' && rowDate !== yesterday) return false;
                    if (dateVal === '7days') {
                        const d = new Date(rowDate);
                        const diff = (new Date() - d) / (1000 * 60 * 60 * 24);
                        if (diff > 7) return false;
                    }
                    if (dateVal === '30days') {
                        const d = new Date(rowDate);
                        const diff = (new Date() - d) / (1000 * 60 * 60 * 24);
                        if (diff > 30) return false;
                    }
                    return true;
                });

                currentPage = 1;
                renderTable();
            }

            function renderTable() {
                allRows.forEach(r => r.style.display = 'none');

                if (filteredRows.length === 0) {
                    tableBody.innerHTML = `<tr><td colspan="6"><div class="empty-state"><i class="bi bi-search"></i>No results match your filters.</div></td></tr>`;
                    paginationWrap.style.display = 'none';
                    return;
                }

                // Remove empty state if exists
                const emptyRow = tableBody.querySelector('.empty-state');
                if (emptyRow) emptyRow.closest('tr').remove();

                const startIdx = (currentPage - 1) * rowsPerPage;
                const endIdx = startIdx + rowsPerPage;
                const paginatedRows = filteredRows.slice(startIdx, endIdx);

                // Re-append filtered rows in the correct order
                filteredRows.forEach(row => row.style.display = 'none');
                paginatedRows.forEach(row => {
                    row.style.display = '';
                    tableBody.appendChild(row);
                });

                // Update Pagination info
                paginationWrap.style.display = 'flex';
                pageInfo.innerHTML = `Showing <strong>${startIdx + 1}</strong> to <strong>${Math.min(endIdx, filteredRows.length)}</strong> of <strong>${filteredRows.length}</strong> requests`;

                // Render buttons
                const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
                pageBtns.innerHTML = '';

                const prevBtn = document.createElement('button');
                prevBtn.textContent = '‹';
                prevBtn.disabled = currentPage === 1;
                prevBtn.onclick = () => {
                    currentPage--;
                    renderTable();
                };
                pageBtns.appendChild(prevBtn);

                for (let i = 1; i <= totalPages; i++) {
                    if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                        const btn = document.createElement('button');
                        btn.textContent = i;
                        if (i === currentPage) btn.classList.add('active');
                        btn.onclick = () => {
                            currentPage = i;
                            renderTable();
                        };
                        pageBtns.appendChild(btn);
                    } else if (i === currentPage - 3 || i === currentPage + 3) {
                        const ellipsis = document.createElement('span');
                        ellipsis.textContent = '...';
                        ellipsis.style.padding = '0 4px';
                        pageBtns.appendChild(ellipsis);
                    }
                }

                const nextBtn = document.createElement('button');
                nextBtn.textContent = '›';
                nextBtn.disabled = currentPage === totalPages;
                nextBtn.onclick = () => {
                    currentPage++;
                    renderTable();
                };
                pageBtns.appendChild(nextBtn);
            }

            searchInput.addEventListener('input', filterTable);
            dateFilter.addEventListener('change', filterTable);
            statusFilter.addEventListener('change', filterTable);

            // 🔹 AJAX Status Change
            document.querySelectorAll('.js-status-change').forEach(select => {
                select.addEventListener('change', function() {
                    const id = this.dataset.id;
                    const newStatus = this.value;
                    const oldValue = this.getAttribute('data-old-value');
                    const badge = this.closest('tr').querySelector('.js-status-badge');

                    this.disabled = true; // Disable while saving

                    const fd = new FormData();
                    fd.append('action', 'update_status');
                    fd.append('id', id);
                    fd.append('status', newStatus);

                    fetch('', {
                            method: 'POST',
                            body: fd
                        })
                        .then(res => res.json())
                        .then(res => {
                            this.disabled = false;

                            if (res.success) {
                                // Update Badge
                                if (newStatus == '1') {
                                    badge.className = 'badge badge-resolved js-status-badge';
                                    badge.textContent = 'Resolved';
                                    this.className = 'status-select resolved js-status-change';
                                } else {
                                    badge.className = 'badge badge-pending js-status-badge';
                                    badge.textContent = 'Pending';
                                    this.className = 'status-select pending js-status-change';
                                }

                                // Update the row data attribute
                                this.closest('tr').dataset.status = newStatus;

                                // ✅ SHOW CUSTOM SUCCESS MODAL INSTEAD OF TOAST
                                showSuccess("Status Updated", res.message);

                                filterTable(); // Re-filter if status filter is active
                            } else {
                                // ✅ SHOW CUSTOM ERROR MODAL INSTEAD OF TOAST
                                showError("Update Failed", res.message);
                                this.value = oldValue; // revert
                            }
                        })
                        .catch(() => {
                            this.disabled = false;
                            // ✅ SHOW CUSTOM ERROR MODAL INSTEAD OF TOAST
                            showError("Network Error", "Unable to connect to server. Try again.");
                            this.value = oldValue;
                        });
                });

                // Store original value to handle revert
                select.setAttribute('data-old-value', select.value);
            });

            // Initialize
            if (allRows.length > 0) renderTable();
        });
    </script>
</body>

</html>