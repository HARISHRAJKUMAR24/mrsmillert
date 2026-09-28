/* =========================================================
   MRS MILL@ — PICKUP ORDERS LIST (admin panel)
   File: ./js/pickup-orders.js
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

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
    const toastWrap          = document.getElementById("toastWrap");

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

    const STATUS_OPTIONS = [
        { value: "pending",    label: "Pending" },
        { value: "confirmed",  label: "Confirmed" },
        { value: "processing", label: "Ready" },
        { value: "delivered",  label: "Picked Up" },
        { value: "cancelled",  label: "Cancelled" }
    ];

    /* HELPERS */
    function money(n) { return "₹" + (Number(n) || 0).toFixed(2); }

    function escapeHtml(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function formatDate(str) {
        if (!str) return "—";
        const d = new Date(str.replace(" ", "T"));
        if (isNaN(d.getTime())) return str;
        const dd  = String(d.getDate()).padStart(2, "0");
        const mon = d.toLocaleString("en-IN", { month: "short" });
        const yr  = d.getFullYear();
        const hh  = String(d.getHours()).padStart(2, "0");
        const mm  = String(d.getMinutes()).padStart(2, "0");
        return `${dd} ${mon} ${yr} · ${hh}:${mm}`;
    }

    function payStatusBadge(s) {
        const map = {
            paid:   "pay-paid",
            unpaid: "pay-unpaid",
            failed: "pay-failed"
        };
        return `<span class="payment-status ${map[s] || "pay-unpaid"}">${escapeHtml(s)}</span>`;
    }

    function showToast(msg, isError) {
        if (!toastWrap) return;
        const el = document.createElement("div");
        el.className = "toast" + (isError ? " error" : "");
        el.innerHTML = `<i class="bi bi-${isError ? "exclamation-circle" : "check-circle-fill"}"></i> ${escapeHtml(msg)}`;
        toastWrap.appendChild(el);

        setTimeout(() => {
            el.style.opacity = "0";
            el.style.transform = "translateX(100%)";
            el.style.transition = "all .3s ease";
            setTimeout(() => el.remove(), 300);
        }, 2800);
    }

    /* RENDER ROW */
    function renderRow(o) {
        const initial = (o.customer_name || "?").trim().charAt(0).toUpperCase();
        const branch  = o.pickup_branch_name || "No branch selected";

        const statusOptionsHtml = STATUS_OPTIONS.map(opt => {
            const sel = opt.value === o.status ? " selected" : "";
            return `<option value="${opt.value}"${sel}>${opt.label}</option>`;
        }).join("");

        return `
            <tr data-id="${o.id}">
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

                <td>
                    <div class="branch-pill">
                        <i class="bi bi-shop"></i> ${escapeHtml(branch)}
                    </div>
                </td>

                <td>${payStatusBadge(o.payment_status)}</td>

                <td><div class="amount-cell">${money(o.total_amount)}</div></td>

                <td>
                    <select class="status-select" data-id="${o.id}">
                        ${statusOptionsHtml}
                    </select>
                </td>

                <td style="text-align:right;">
                    <a href="${BASE_URL}order-view.php?id=${o.id}" class="row-view" title="View details">
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
                    <td colspan="7">
                        <div class="or-empty">
                            <i class="bi bi-shop-window"></i>
                            <h3>No pickup orders found</h3>
                            <p>Nothing matches your filter or search.</p>
                        </div>
                    </td>
                </tr>`;
            if (paginationWrap) paginationWrap.style.display = "none";
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
                    o.pickup_branch_name || ""
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
                <td colspan="7" style="text-align:center;padding:40px;color:#948c82;">
                    Loading pickup orders...
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

        const url = BASE_URL + "ajax/get-pickup-orders.php?" + params.toString();

        fetch(url, { credentials: "same-origin" })
            .then(r => r.json().catch(() => null))
            .then(res => {

                if (!res || !res.success || !Array.isArray(res.data)) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" style="text-align:center;padding:40px;color:#b51f2c;">
                                ${escapeHtml((res && res.message) || "Failed to load pickup orders.")}
                            </td>
                        </tr>`;
                    return;
                }

                allRows = res.data;
                currentPage = 1;
                applyFilterAndRender();
            })
            .catch(() => {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" style="text-align:center;padding:40px;color:#b51f2c;">
                            Unable to connect to server.
                        </td>
                    </tr>`;
            });
    }

    /* =========================================================
       STATUS CHANGE HANDLER (inline dropdown)
       ========================================================= */
    tbody.addEventListener("change", function (e) {
        const select = e.target.closest(".status-select");
        if (!select) return;

        const orderId = Number(select.dataset.id);
        const newStatus = select.value;
        if (!orderId || !newStatus) return;

        select.classList.add("saving");

        const body = new URLSearchParams();
        body.set("order_id", orderId);
        body.set("status", newStatus);

        fetch(BASE_URL + "ajax/update-pickup-order-status.php", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body: body.toString()
        })
        .then(r => r.json().catch(() => null))
        .then(res => {
            select.classList.remove("saving");

            if (!res || !res.success) {
                showToast((res && res.message) || "Failed to update status.", true);
                // reload to revert the dropdown
                loadOrders();
                return;
            }

            // Update local data
            const row = allRows.find(o => o.id === orderId);
            if (row) row.status = newStatus;

            // Update table stats in DOM (optional, simple reload instead)
            showToast("Order status updated.");
        })
        .catch(() => {
            select.classList.remove("saving");
            showToast("Network error. Please try again.", true);
            loadOrders();
        });
    });

    /* ROW CLICK → navigate */
    tbody.addEventListener("click", function (e) {
        /* Skip if clicked inside the view button or status dropdown */
        if (e.target.closest(".row-view") || e.target.closest(".status-select")) return;

        const tr = e.target.closest("tr[data-id]");
        if (!tr) return;

        /* We don't have data-href on pickup rows — build it */
        const id = tr.dataset.id;
        if (id) window.location.href = BASE_URL + "order-view.php?id=" + id;
    });

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