/* =========================================================
   MRS MILL@ — ORDERS LIST (admin panel)
   File: ./js/orders.js
   + 12-hour time format
   + Row checkboxes (select-all with indeterminate state)
   + Bulk action bar
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

    /* Checkbox + bulk */
    const selectAllBox = document.getElementById("orSelectAll");
    const bulkBar      = document.getElementById("orBulkBar");
    const bulkCountEl  = document.getElementById("orBulkCount");
    const bulkClear    = document.getElementById("orBulkClear");
    const bulkSample   = document.getElementById("orBulkSample");

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

    /* Track selected order IDs across pages */
    const selectedIds = new Set();

    /* HELPERS */
    function money(n) { return "₹" + Math.round(Number(n) || 0); }

    function escapeHtml(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    /* ✅ 12-hour format with AM/PM */
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
        const map = {
            paid:   "pay-paid",
            unpaid: "pay-unpaid",
            failed: "pay-failed"
        };
        return `<span class="payment-status ${map[s] || "pay-unpaid"}">${escapeHtml(s)}</span>`;
    }

    /* RENDER ROW */
    function renderRow(o) {
        const initial  = (o.customer_name || "?").trim().charAt(0).toUpperCase();
        const isPickup = o.delivery_mode === "pickup";
        const isChecked = selectedIds.has(String(o.id));

        let modeCell = "";

        if (isPickup) {
            modeCell = `
                <div class="mode-pill mode-pickup">
                    <i class="bi bi-shop"></i> Pickup
                </div>
                <div class="cust-meta" style="margin-top:4px;">
                    ${escapeHtml(o.pickup_branch_name || "No branch")}
                </div>`;
        } else {
            modeCell = `
                <div class="mode-pill mode-delivery">
                    <i class="bi bi-truck"></i> Delivery
                </div>
                <div class="cust-meta" style="margin-top:4px;">
                    ${escapeHtml(o.apartment_name || "—")}
                    ${o.division ? " · Div " + escapeHtml(o.division) : ""}
                </div>`;
        }

        return `
            <tr data-id="${o.id}" class="${isChecked ? 'is-selected' : ''}" data-href="${BASE_URL}order-view.php?id=${o.id}">
                <td class="col-check">
                    <div class="or-check ${isChecked ? 'checked' : ''}"
                         data-check-id="${o.id}"></div>
                </td>

                <td>
                    <div class="order-cell">
                        <div class="order-avatar">${escapeHtml(initial)}</div>
                        <div class="order-info">
                            <div class="order-code">#${escapeHtml(o.order_code)}</div>
                            <div class="order-time">${escapeHtml(formatDate(o.created_at))}</div>
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

                <td>
                    ${isPickup
                        ? `<span style="color:#948c82;font-size:11px;">—</span>`
                        : `<div class="cust-name">${escapeHtml(o.boy_name || "—")}</div>
                           ${o.boy_code ? `<div class="cust-meta">#${escapeHtml(o.boy_code)}</div>` : ""}`
                    }
                </td>

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

    /* PAGINATION */
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

        if (totalPages <= 1) {
            paginationControls.innerHTML = "";
            return;
        }

        const html = [];

        html.push(
            '<button type="button" data-page="' + (currentPage - 1) + '"' +
            (currentPage === 1 ? ' disabled' : '') +
            ' title="Previous"><i class="bi bi-chevron-left"></i></button>'
        );

        getPageList(currentPage, totalPages).forEach(function (p) {
            if (p === "...") {
                html.push('<span class="page-ellipsis">…</span>');
            } else {
                html.push(
                    '<button type="button" data-page="' + p + '"' +
                    (p === currentPage ? ' class="active"' : '') +
                    '>' + p + '</button>'
                );
            }
        });

        html.push(
            '<button type="button" data-page="' + (currentPage + 1) + '"' +
            (currentPage === totalPages ? ' disabled' : '') +
            ' title="Next"><i class="bi bi-chevron-right"></i></button>'
        );

        paginationControls.innerHTML = html.join("");
    }

    function getPageList(current, total) {
        const delta = 1;
        const range = [];
        const out   = [];

        for (let i = 1; i <= total; i++) {
            if (i === 1 || i === total ||
                (i >= current - delta && i <= current + delta)) {
                range.push(i);
            }
        }

        let prev = 0;
        range.forEach(function (i) {
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
            if (card) {
                window.scrollTo({
                    top: card.offsetTop - 20,
                    behavior: "smooth"
                });
            }
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

    /* FILTER */
    function applyFilterAndRender(resetPage) {
        const q = (searchTerm || "").trim().toLowerCase();

        if (!q) {
            filteredRows = allRows.slice();
        } else {
            filteredRows = allRows.filter(function (o) {
                const hay = [
                    o.order_code || "",
                    o.customer_name || "",
                    o.customer_mobile || "",
                    o.boy_name || "",
                    o.boy_code || "",
                    o.pickup_branch_name || "",
                    o.apartment_name || ""
                ].join(" ").toLowerCase();
                return hay.indexOf(q) !== -1;
            });
        }

        if (resetPage !== false) currentPage = 1;
        renderPage();
    }

    /* LOAD */
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
        params.set("date",   activeDate);
        params.set("q",      "");

        if (activeDate === "custom") {
            if (dateFrom && dateFrom.value) params.set("from", dateFrom.value);
            if (dateTo   && dateTo.value)   params.set("to",   dateTo.value);
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

                /* Drop selections that no longer exist */
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

    /* =========================================================
       CHECKBOX LOGIC
       ========================================================= */
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

        if (checked === 0) {
            /* none */
        } else if (checked === total) {
            selectAllBox.classList.add("checked");
        } else {
            selectAllBox.classList.add("indeterminate");
        }
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

    /* Toggle on checkbox click */
    tbody.addEventListener("click", function (e) {

        /* Checkbox click */
        const checkbox = e.target.closest(".or-check[data-check-id]");
        if (checkbox) {
            e.stopPropagation();
            toggleRowCheckbox(checkbox);
            return;
        }

        /* Row click → navigate unless clicking view link or checkbox */
        if (e.target.closest(".row-view")) return;

        const tr = e.target.closest("tr[data-id]");
        if (!tr) return;

        const href = tr.dataset.href;
        if (href) window.location.href = href;
    });

    /* Select all in header */
    if (selectAllBox) {
        selectAllBox.addEventListener("click", function () {

            const checkboxes = tbody.querySelectorAll(".or-check[data-check-id]");
            const total = checkboxes.length;
            const checked = tbody.querySelectorAll(".or-check.checked[data-check-id]").length;

            const shouldSelectAll = checked < total;  /* if not all selected → select all */

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

    /* Bulk clear */
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

    /* Sample bulk action — replace with your real action */
    if (bulkSample) {
        bulkSample.addEventListener("click", function () {
            const ids = Array.from(selectedIds);
            if (!ids.length) return;

            /* Example: show a confirm or fire an AJAX call */
            alert("Bulk action on " + ids.length + " order(s):\n\n" + ids.join(", "));

            /* TODO: replace with real AJAX call, e.g.
               fetch(BASE_URL + 'ajax/bulk-orders-action.php', { ... })
            */
        });
    }

    /* FILTER TABS */
    tabs.forEach(tab => {
        tab.addEventListener("click", function () {
            tabs.forEach(t => t.classList.remove("active"));
            tab.classList.add("active");
            activeFilter = tab.dataset.filter || "all";
            currentPage = 1;
            loadOrders();
        });
    });

    /* DATE FILTER */
    if (dateSelect) {
        dateSelect.addEventListener("change", function () {
            activeDate = this.value || "all";

            if (dateCustom) {
                dateCustom.style.display = (activeDate === "custom") ? "flex" : "none";
            }

            if (activeDate !== "custom" ||
                (dateFrom && dateFrom.value && dateTo && dateTo.value)) {
                currentPage = 1;
                loadOrders();
            }
        });
    }

    if (dateFrom) {
        dateFrom.addEventListener("change", function () {
            if (activeDate === "custom" && dateTo && dateTo.value) {
                currentPage = 1;
                loadOrders();
            }
        });
    }
    if (dateTo) {
        dateTo.addEventListener("change", function () {
            if (activeDate === "custom" && dateFrom && dateFrom.value) {
                currentPage = 1;
                loadOrders();
            }
        });
    }

    /* SEARCH */
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

    /* INIT */
    loadOrders();

})();