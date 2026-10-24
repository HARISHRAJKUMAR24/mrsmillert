/* =========================================================
   MRS MILL@ — ORDERS LIST (admin panel)
   File: ./js/orders.js
   ========================================================= */

(function () {
    "use strict";

    let BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";
    if (!BASE_URL.endsWith("/")) BASE_URL += "/";

    /* DOM */
    const tbody       = document.getElementById("orTbody");
    const searchInput = document.getElementById("orSearch");
    const tabs        = document.querySelectorAll(".or-tab");
    const dateSelect  = document.getElementById("orDateFilter");
    const dateFrom    = document.getElementById("orDateFrom");
    const dateTo      = document.getElementById("orDateTo");
    const dateCustom  = document.getElementById("orDateCustom");

    const paginationWrap     = document.getElementById("orPagination");
    const paginationInfo     = document.getElementById("paginationInfo");
    const paginationControls = document.getElementById("paginationControls");
    const perPageSelect      = document.getElementById("perPageSelect");

    const selectAllBox = document.getElementById("orSelectAll");
    const bulkBar      = document.getElementById("orBulkBar");
    const bulkCountEl  = document.getElementById("orBulkCount");
    const bulkClear    = document.getElementById("orBulkClear");
    const bulkSample   = document.getElementById("orBulkSample");

    /* Notes modal */
    const noteOverlay  = document.getElementById("orNoteOverlay");
    const noteClose    = document.getElementById("orNoteClose");
    const noteCloseBtn = document.getElementById("orNoteCloseBtn");
    const noteCodeEl   = document.getElementById("orNoteCode");
    const noteTextEl   = document.getElementById("orNoteText");
    const noteBodyEl   = document.getElementById("orNoteBody");
    const noteViewBtn  = document.getElementById("orNoteViewBtn");

    /* Assign modal */
    const assignOverlay = document.getElementById("orAssignOverlay");
    const assignClose   = document.getElementById("orAssignClose");
    const assignCancel  = document.getElementById("orAssignCancel");
    const assignCode    = document.getElementById("orAssignCode");
    const assignBody    = document.getElementById("orAssignBody");

    /* Toast */
    const toastEl = document.getElementById("orToast");

    if (!tbody) return;

    /* STATE */
    const DEFAULT_PAGE_SIZE = 10;
    let pageSize     = DEFAULT_PAGE_SIZE;
    let allRows      = [];
    let filteredRows = [];
    let currentPage  = 1;
    let activeFilter = "all";
    let activeDate   = "all";
    let searchTerm   = "";
    let assigningOrderId = 0;

    const selectedIds = new Set();

    /* HELPERS */
    function money(n) { return "₹" + Math.round(Number(n) || 0); }

    function escapeHtml(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function showToast(msg, isError) {
        if (!toastEl) return;
        toastEl.textContent = msg;
        toastEl.className = "or-toast show" + (isError ? " error" : "");
        clearTimeout(toastEl._t);
        toastEl._t = setTimeout(() => (toastEl.className = "or-toast"), 3000);
    }

    function formatDate(str) {
        if (!str) return "—";
        const d = new Date(str.replace(" ", "T"));
        if (isNaN(d.getTime())) return str;
        const dd  = String(d.getDate()).padStart(2, "0");
        const mon = d.toLocaleString("en-IN", { month: "short" });
        const yr  = d.getFullYear();
        let hours = d.getHours();
        const mins = String(d.getMinutes()).padStart(2, "0");
        const ampm = hours >= 12 ? "PM" : "AM";
        hours = hours % 12;
        if (hours === 0) hours = 12;
        const hh = String(hours).padStart(2, "0");
        return `${dd} ${mon} ${yr} · ${hh}:${mins} ${ampm}`;
    }

    function orderStatusBadge(s) {
        const map = {
            pending:    "status-pending",
            confirmed:  "status-confirmed",
            processing: "status-processing",
            delivered:  "status-delivered",
            cancelled:  "status-cancelled"
        };
        return `<span class="order-status ${map[s] || "status-pending"}">${escapeHtml(s)}</span>`;
    }

    function payStatusBadge(s) {
        const map = { paid: "pay-paid", unpaid: "pay-unpaid", failed: "pay-failed" };
        return `<span class="payment-status ${map[s] || "pay-unpaid"}">${escapeHtml(s)}</span>`;
    }

    /* RENDER ROW */
    function renderRow(o) {
        const initial   = (o.customer_name || "?").trim().charAt(0).toUpperCase();
        const isPickup  = o.delivery_mode === "pickup";
        const isHouse   = o.address_type === "individual_house";
        const isChecked = selectedIds.has(String(o.id));
        const hasNote   = !!(o.order_note && String(o.order_note).trim() !== "");
        const hasBoy    = !!(o.boy_name && String(o.boy_name).trim() !== "" && o.boy_name !== "—");

        let modeCell = "";
        if (isPickup) {
            modeCell = `
                <div class="mode-pill mode-pickup"><i class="bi bi-shop"></i> Pickup</div>
                <div class="cust-meta" style="margin-top:4px;">${escapeHtml(o.pickup_branch_name || "No branch")}</div>`;
        } else if (isHouse) {
            modeCell = `
                <div class="mode-pill mode-delivery"><i class="bi bi-truck"></i> Delivery</div>
                <div class="mode-pill mode-house" style="margin-top:4px;" title="${escapeHtml(o.full_address || '')}">
                    <i class="bi bi-house-door"></i> Individual House
                </div>`;
        } else {
            modeCell = `
                <div class="mode-pill mode-delivery"><i class="bi bi-truck"></i> Delivery</div>
                <div class="cust-meta" style="margin-top:4px;">
                    ${escapeHtml(o.apartment_name || "—")}
                    ${o.division ? " · Div " + escapeHtml(o.division) : ""}
                </div>`;
        }

        const noteBtnHtml = hasNote
            ? `<div class="order-note-btn" data-note-btn="1" data-note-order="${o.id}">
                   <i class="bi bi-chat-left-text"></i> Notes
               </div>`
            : ``;

        /* Delivery boy cell — either show name or assign button */
        let boyCell = "";
        if (isPickup) {
            boyCell = `<span style="color:#948c82;font-size:11px;">—</span>`;
        } else if (hasBoy) {
            boyCell = `
                <div class="cust-name">${escapeHtml(o.boy_name)}</div>
                ${o.boy_code ? `<div class="cust-meta">#${escapeHtml(o.boy_code)}</div>` : ""}`;
        } else {
            boyCell = `
                <button type="button" class="assign-boy-btn js-assign-boy"
                    data-order-id="${o.id}"
                    data-order-code="${escapeHtml(o.order_code)}">
                    <i class="bi bi-person-plus"></i> Assign
                </button>`;
        }

        return `
            <tr data-id="${o.id}" class="${isChecked ? 'is-selected' : ''}" data-href="${BASE_URL}order-view.php?id=${o.id}">
                <td class="col-check">
                    <div class="or-check ${isChecked ? 'checked' : ''}" data-check-id="${o.id}"></div>
                </td>

                <td>
                    <div class="order-cell">
                        <div class="order-avatar">${escapeHtml(initial)}</div>
                        <div class="order-info">
                            <div class="order-code">#${escapeHtml(o.order_code)}</div>
                            <div class="order-time">${escapeHtml(formatDate(o.created_at))}</div>
                            ${noteBtnHtml}
                        </div>
                    </div>
                </td>

                <td>
                    <div class="cust-cell">
                        <div class="cust-name">${escapeHtml(o.customer_name)}</div>
                        <div class="cust-meta">
                            <i class="bi bi-telephone-fill"></i>
                            ${escapeHtml(o.customer_mobile)}
                        </div>
                    </div>
                </td>

                <td>${modeCell}</td>
                <td>${boyCell}</td>
                <td>${orderStatusBadge(o.status)}</td>
                <td>${payStatusBadge(o.payment_status)}</td>
                <td><div class="amount-cell">${money(o.total_amount)}</div></td>

                <td style="text-align:right;">
                    <a href="${BASE_URL}order-view.php?id=${o.id}" class="row-view">
                        <i class="bi bi-eye"></i>
                    </a>
                </td>
            </tr>
        `;
    }

    /* =========================================================
       NOTES POPUP
       ========================================================= */
    function openNotePopup(order) {
        if (!noteOverlay) return;
        if (noteCodeEl) noteCodeEl.textContent = "#" + (order.order_code || "");
        if (noteTextEl) {
            const note = String(order.order_note || "").trim();
            noteTextEl.textContent = note;
            noteTextEl.style.display = note ? "block" : "none";
        }
        if (noteBodyEl) {
            const existingEmpty = noteBodyEl.querySelector(".or-note-empty");
            if (existingEmpty) existingEmpty.remove();
            const note = String(order.order_note || "").trim();
            if (!note) {
                const emptyDiv = document.createElement("div");
                emptyDiv.className = "or-note-empty";
                emptyDiv.innerHTML = `<i class="bi bi-inbox"></i>No instructions on this order.`;
                noteBodyEl.appendChild(emptyDiv);
            }
        }
        if (noteViewBtn) noteViewBtn.href = BASE_URL + "order-view.php?id=" + order.id;
        noteOverlay.classList.add("show");
        document.body.style.overflow = "hidden";
    }

    function closeNotePopup() {
        if (!noteOverlay) return;
        noteOverlay.classList.remove("show");
        document.body.style.overflow = "";
    }

    if (noteClose) noteClose.addEventListener("click", closeNotePopup);
    if (noteCloseBtn) noteCloseBtn.addEventListener("click", closeNotePopup);
    if (noteOverlay) noteOverlay.addEventListener("click", e => { if (e.target === noteOverlay) closeNotePopup(); });
    document.addEventListener("keydown", e => {
        if (e.key === "Escape" && noteOverlay && noteOverlay.classList.contains("show")) closeNotePopup();
    });

    /* =========================================================
       ASSIGN DELIVERY BOY
       ========================================================= */
    function openAssignModal(orderId, orderCode) {
        assigningOrderId = orderId;
        if (assignCode) assignCode.textContent = "#" + orderCode;
        assignBody.innerHTML = `<div class="or-assign-empty"><i class="bi bi-hourglass-split"></i> Loading delivery boys…</div>`;
        assignOverlay.classList.add("show");
        document.body.style.overflow = "hidden";

        fetch(BASE_URL + "ajax/get-delivery-boys-list.php", { credentials: "same-origin" })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !Array.isArray(res.data) || !res.data.length) {
                    assignBody.innerHTML = `<div class="or-assign-empty"><i class="bi bi-person-x"></i>No delivery boys available.</div>`;
                    return;
                }

                assignBody.innerHTML = `
                    <div class="or-boy-list">
                        ${res.data.map(b => `
                            <div class="or-boy-item" data-boy-id="${b.id}" data-boy-name="${escapeHtml(b.full_name)}">
                                <div class="or-boy-avatar">${escapeHtml((b.full_name || "?").trim().charAt(0).toUpperCase())}</div>
                                <div class="or-boy-info">
                                    <p class="or-boy-name">${escapeHtml(b.full_name)}</div>
                                    <div class="or-boy-code">#${escapeHtml(b.delivery_code)} · ${escapeHtml(b.mobile_number)}</div>
                                </div>
                                <div class="or-boy-pick">Select →</div>
                            </div>
                        `).join("")}
                    </div>
                `;

                assignBody.querySelectorAll(".or-boy-item").forEach(item => {
                    item.addEventListener("click", function() {
                        const boyId = this.dataset.boyId;
                        const boyName = this.dataset.boyName;
                        assignBoy(assigningOrderId, boyId, boyName);
                    });
                });
            })
            .catch(() => {
                assignBody.innerHTML = `<div class="or-assign-empty"><i class="bi bi-wifi-off"></i>Unable to load delivery boys.</div>`;
            });
    }

    function assignBoy(orderId, boyId, boyName) {
        const fd = new FormData();
        fd.append("order_id", orderId);
        fd.append("delivery_boy_id", boyId);

        showToast("Assigning " + boyName + "…");

        fetch(BASE_URL + "ajax/assign-delivery-boy.php", {
            method: "POST",
            body: fd,
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success) {
                    showToast((res && res.message) || "Failed to assign.", true);
                    return;
                }
                showToast(res.message || "Delivery boy assigned!");
                closeAssignModal();
                loadOrders();
            })
            .catch(() => {
                showToast("Unable to connect.", true);
            });
    }

    function closeAssignModal() {
        if (!assignOverlay) return;
        assignOverlay.classList.remove("show");
        document.body.style.overflow = "";
        assigningOrderId = 0;
    }

    if (assignClose) assignClose.addEventListener("click", closeAssignModal);
    if (assignCancel) assignCancel.addEventListener("click", closeAssignModal);
    if (assignOverlay) assignOverlay.addEventListener("click", e => { if (e.target === assignOverlay) closeAssignModal(); });
    document.addEventListener("keydown", e => {
        if (e.key === "Escape" && assignOverlay && assignOverlay.classList.contains("show")) closeAssignModal();
    });

    /* =========================================================
       PAGINATION
       ========================================================= */
    function renderPage() {
        const total = filteredRows.length;

        if (total === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9">
                        <div class="or-empty">
                            <i class="bi bi-inbox"></i>
                            <h3>No orders found</h3>
                            <p>Nothing matches your filter or search.</p>
                        </div>
                    </td>
                </tr>`;
            if (paginationWrap) paginationWrap.style.display = "none";
            syncSelectAllState();
            return;
        }

        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const start = (currentPage - 1) * pageSize;
        const end   = Math.min(start + pageSize, total);
        const slice = filteredRows.slice(start, end);

        tbody.innerHTML = slice.map(renderRow).join("");

        if (paginationInfo) {
            paginationInfo.innerHTML =
                'Showing <strong>' + (start + 1) + '</strong>–<strong>' + end +
                '</strong> of <strong>' + total + '</strong>';
        }

        renderPaginationControls(totalPages);
        if (paginationWrap) paginationWrap.style.display = "flex";
        syncSelectAllState();
    }

    function renderPaginationControls(totalPages) {
        if (!paginationControls) return;
        if (totalPages <= 1) { paginationControls.innerHTML = ""; return; }
        const html = [];
        html.push('<button type="button" data-page="' + (currentPage - 1) + '"' +
            (currentPage === 1 ? ' disabled' : '') + '><i class="bi bi-chevron-left"></i></button>');

        getPageList(currentPage, totalPages).forEach(p => {
            if (p === "...") {
                html.push('<span class="page-ellipsis">…</span>');
            } else {
                html.push('<button type="button" data-page="' + p + '"' +
                    (p === currentPage ? ' class="active"' : '') + '>' + p + '</button>');
            }
        });

        html.push('<button type="button" data-page="' + (currentPage + 1) + '"' +
            (currentPage === totalPages ? ' disabled' : '') + '><i class="bi bi-chevron-right"></i></button>');
        paginationControls.innerHTML = html.join("");
    }

    function getPageList(current, total) {
        const delta = 1;
        const range = [];
        const out = [];
        for (let i = 1; i <= total; i++) {
            if (i === 1 || i === total || (i >= current - delta && i <= current + delta)) range.push(i);
        }
        let prev = 0;
        range.forEach(i => {
            if (prev && i - prev > 1) out.push("...");
            out.push(i);
            prev = i;
        });
        return out;
    }

    if (paginationControls) {
        paginationControls.addEventListener("click", function (e) {
            const btn = e.target.closest("button[data-page]");
            if (!btn || btn.disabled) return;
            const page = Number(btn.dataset.page);
            if (!page || page === currentPage) return;
            currentPage = page;
            renderPage();
            const card = document.querySelector(".or-card");
            if (card) window.scrollTo({ top: card.offsetTop - 20, behavior: "smooth" });
        });
    }

    if (perPageSelect) {
        perPageSelect.value = String(pageSize);
        perPageSelect.addEventListener("change", function () {
            const v = Number(this.value);
            if (!v || v <= 0) return;
            pageSize = v;
            currentPage = 1;
            renderPage();
        });
    }

    function applyFilterAndRender(resetPage) {
        const q = (searchTerm || "").trim().toLowerCase();
        if (!q) {
            filteredRows = allRows.slice();
        } else {
            filteredRows = allRows.filter(function (o) {
                const hay = [
                    o.order_code || "", o.customer_name || "", o.customer_mobile || "",
                    o.boy_name || "", o.boy_code || "", o.pickup_branch_name || "",
                    o.apartment_name || "", o.full_address || "", o.address_type || "",
                    o.order_note || ""
                ].join(" ").toLowerCase();
                return hay.indexOf(q) !== -1;
            });
        }
        if (resetPage !== false) currentPage = 1;
        renderPage();
    }

    function loadOrders() {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" style="text-align:center;padding:40px;color:#948c82;">
                    Loading orders...
                </td>
            </tr>`;
        if (paginationWrap) paginationWrap.style.display = "none";

        const params = new URLSearchParams();
        params.set("filter", activeFilter);
        params.set("date", activeDate);
        params.set("q", "");
        if (activeDate === "custom") {
            if (dateFrom && dateFrom.value) params.set("from", dateFrom.value);
            if (dateTo && dateTo.value) params.set("to", dateTo.value);
        }

        const url = BASE_URL + "ajax/get-orders.php?" + params.toString();

        fetch(url, { credentials: "same-origin" })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !Array.isArray(res.data)) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="9" style="text-align:center;padding:40px;color:#b51f2c;">
                                ${escapeHtml((res && res.message) || "Failed to load orders.")}
                            </td>
                        </tr>`;
                    syncSelectAllState();
                    return;
                }
                allRows = res.data;
                const validIds = new Set(allRows.map(r => String(r.id)));
                Array.from(selectedIds).forEach(id => {
                    if (!validIds.has(id)) selectedIds.delete(id);
                });
                updateBulkBar();
                currentPage = 1;
                applyFilterAndRender();
            })
            .catch(() => {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" style="text-align:center;padding:40px;color:#b51f2c;">
                            Unable to connect to server.
                        </td>
                    </tr>`;
                syncSelectAllState();
            });
    }

    function updateBulkBar() {
        if (!bulkBar) return;
        const n = selectedIds.size;
        if (bulkCountEl) bulkCountEl.textContent = n;
        bulkBar.classList.toggle("show", n > 0);
    }

    function syncSelectAllState() {
        if (!selectAllBox) return;
        const checkboxes = tbody.querySelectorAll(".or-check[data-check-id]");
        const total = checkboxes.length;
        const checked = tbody.querySelectorAll(".or-check.checked[data-check-id]").length;
        selectAllBox.classList.remove("checked", "indeterminate");
        if (total === 0) return;
        if (checked === total) selectAllBox.classList.add("checked");
        else if (checked > 0) selectAllBox.classList.add("indeterminate");
    }

    function toggleRowCheckbox(checkboxEl) {
        const id = checkboxEl.dataset.checkId;
        if (!id) return;
        if (selectedIds.has(String(id))) {
            selectedIds.delete(String(id));
            checkboxEl.classList.remove("checked");
            const tr = checkboxEl.closest("tr");
            if (tr) tr.classList.remove("is-selected");
        } else {
            selectedIds.add(String(id));
            checkboxEl.classList.add("checked");
            const tr = checkboxEl.closest("tr");
            if (tr) tr.classList.add("is-selected");
        }
        updateBulkBar();
        syncSelectAllState();
    }

    /* EVENT DELEGATION */
    tbody.addEventListener("click", function (e) {

        /* Assign button */
        const assignBtn = e.target.closest(".js-assign-boy");
        if (assignBtn) {
            e.stopPropagation();
            e.preventDefault();
            openAssignModal(
                Number(assignBtn.dataset.orderId),
                assignBtn.dataset.orderCode
            );
            return;
        }

        /* Notes button */
        const noteBtn = e.target.closest(".order-note-btn");
        if (noteBtn) {
            e.stopPropagation();
            e.preventDefault();
            const orderId = noteBtn.dataset.noteOrder;
            const order = allRows.find(r => String(r.id) === String(orderId));
            if (order) openNotePopup(order);
            return;
        }

        /* Checkbox */
        const checkbox = e.target.closest(".or-check[data-check-id]");
        if (checkbox) {
            e.stopPropagation();
            toggleRowCheckbox(checkbox);
            return;
        }

        /* Row click → navigate */
        if (e.target.closest(".row-view")) return;
        const tr = e.target.closest("tr[data-id]");
        if (!tr) return;
        if (tr.dataset.href) window.location.href = tr.dataset.href;
    });

    if (selectAllBox) {
        selectAllBox.addEventListener("click", function () {
            const checkboxes = tbody.querySelectorAll(".or-check[data-check-id]");
            const total = checkboxes.length;
            const checked = tbody.querySelectorAll(".or-check.checked[data-check-id]").length;
            const shouldSelectAll = checked < total;

            checkboxes.forEach(cb => {
                const id = String(cb.dataset.checkId);
                const tr = cb.closest("tr");
                if (shouldSelectAll) {
                    if (!selectedIds.has(id)) {
                        selectedIds.add(id);
                        cb.classList.add("checked");
                        if (tr) tr.classList.add("is-selected");
                    }
                } else {
                    selectedIds.delete(id);
                    cb.classList.remove("checked");
                    if (tr) tr.classList.remove("is-selected");
                }
            });
            updateBulkBar();
            syncSelectAllState();
        });
    }

    if (bulkClear) {
        bulkClear.addEventListener("click", function () {
            selectedIds.clear();
            tbody.querySelectorAll(".or-check[data-check-id]").forEach(cb => {
                cb.classList.remove("checked");
                const tr = cb.closest("tr");
                if (tr) tr.classList.remove("is-selected");
            });
            updateBulkBar();
            syncSelectAllState();
        });
    }

    if (bulkSample) {
        bulkSample.addEventListener("click", function () {
            const ids = Array.from(selectedIds);
            if (!ids.length) return;
            alert("Bulk action on " + ids.length + " order(s):\n\n" + ids.join(", "));
        });
    }

    tabs.forEach(tab => {
        tab.addEventListener("click", function () {
            tabs.forEach(t => t.classList.remove("active"));
            tab.classList.add("active");
            activeFilter = tab.dataset.filter || "all";
            currentPage = 1;
            loadOrders();
        });
    });

    if (dateSelect) {
        dateSelect.addEventListener("change", function () {
            activeDate = this.value || "all";
            if (dateCustom) dateCustom.style.display = (activeDate === "custom") ? "flex" : "none";
            if (activeDate !== "custom" || (dateFrom && dateFrom.value && dateTo && dateTo.value)) {
                currentPage = 1;
                loadOrders();
            }
        });
    }

    if (dateFrom) {
        dateFrom.addEventListener("change", function () {
            if (activeDate === "custom" && dateTo && dateTo.value) { currentPage = 1; loadOrders(); }
        });
    }
    if (dateTo) {
        dateTo.addEventListener("change", function () {
            if (activeDate === "custom" && dateFrom && dateFrom.value) { currentPage = 1; loadOrders(); }
        });
    }

    let searchTimer = null;
    if (searchInput) {
        searchInput.addEventListener("input", function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                searchTerm = searchInput.value;
                applyFilterAndRender();
            }, 200);
        });
    }

    loadOrders();

})();