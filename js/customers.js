/* =========================================================
   MRS MILL@ — CUSTOMERS LIST
   File: ./js/customers.js
   Fetch + render customers with search, pagination, per-page
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    /* DOM */
    const tbody          = document.getElementById("cuTbody");
    const search         = document.getElementById("cuSearch");
    const refreshBtn     = document.getElementById("cuRefreshBtn");

    const paginationWrap = document.getElementById("cuPagination");
    const pagInfo        = document.getElementById("cuPagInfo");
    const pagControls    = document.getElementById("cuPagControls");
    const perPageSelect  = document.getElementById("cuPerPage");

    const kpiTotal       = document.getElementById("kpiTotal");
    const kpiActive      = document.getElementById("kpiActive");
    const kpiWallet      = document.getElementById("kpiWallet");
    const kpiWithWallet  = document.getElementById("kpiWithWallet");

    const toastWrap      = document.getElementById("cuToastWrap");

    /* STATE */
    const DEFAULT_PAGE_SIZE = 10;
    let pageSize     = DEFAULT_PAGE_SIZE;
    let allRows      = [];
    let filteredRows = [];
    let currentPage  = 1;

    /* HELPERS */
    function esc(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function initials(name) {
        name = String(name || "").trim();
        if (!name) return "?";
        const parts = name.split(/\s+/);
        if (parts.length >= 2) {
            return (parts[0][0] + parts[1][0]).toUpperCase();
        }
        return name.substring(0, 2).toUpperCase();
    }

    function money(n) {
        const v = Number(n) || 0;
        return "₹" + v.toFixed(2).replace(/\.00$/, "");
    }

    /* TOAST */
    function showToast(type, message, timeout) {
        if (!toastWrap) return;
        const icons = { success: "bi-check-lg", error: "bi-x-lg", info: "bi-info-lg" };
        const el = document.createElement("div");
        el.className = "cu-toast " + type;
        el.innerHTML = `
            <div class="cu-toast-icon"><i class="bi ${icons[type] || icons.info}"></i></div>
            <div class="cu-toast-body">${esc(message)}</div>
        `;
        toastWrap.appendChild(el);
        requestAnimationFrame(() => el.classList.add("show"));
        setTimeout(() => {
            el.classList.remove("show");
            setTimeout(() => el.remove(), 300);
        }, timeout || 3200);
    }

    /* RENDER ROW */
    function renderRow(c) {
        const active = Number(c.status) === 1;
        const wallet = Number(c.wallet_balance) || 0;

        return `
            <tr data-id="${c.id}">
                <td>
                    <div class="cu-cust">
                        <div class="cu-avatar">${esc(initials(c.full_name))}</div>
                        <div>
                            <span class="cu-cust-name">${esc(c.full_name)}</span>
                            <div class="cu-cust-mobile">${esc(c.mobile_number)}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="cu-apt">${esc(c.apartment_name || "—")}</span>
                    <div class="cu-div">#${esc(c.apartment_code || "—")}</div>
                </td>
                <td>
                    <span class="cu-apt" style="font-weight:600;">${esc(c.division || "—")}</span>
                    <div class="cu-div">₹${Number(c.division_charge || 0).toFixed(0)}</div>
                </td>
                <td>
                    <span class="cu-wallet ${wallet > 0 ? 'pos' : 'zero'}">
                        <i class="bi bi-wallet2"></i> ${money(wallet)}
                    </span>
                </td>
                <td>
                    <span class="cu-status ${active ? 'active' : 'inactive'}">
                        <i class="bi bi-circle-fill" style="font-size:6px;"></i>
                        ${active ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td>
                    <div class="cu-actions">
                        <a class="cu-action" href="edit-customer.php?id=${c.id}">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                    </div>
                </td>
            </tr>
        `;
    }

    /* RENDER PAGE */
    function renderPage() {
        const total = filteredRows.length;

        if (total === 0) {
            tbody.innerHTML = `
                <tr><td colspan="6">
                    <div class="cu-empty">
                        <i class="bi bi-people"></i>
                        <h3>No customers found</h3>
                        <p>Try adjusting your search or add a new customer.</p>
                    </div>
                </td></tr>`;
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

        if (pagInfo) {
            pagInfo.innerHTML =
                'Showing <strong>' + (start + 1) + '</strong>–<strong>' + end +
                '</strong> of <strong>' + total + '</strong>';
        }

        renderPaginationControls(totalPages);
        if (paginationWrap) paginationWrap.style.display = "flex";
    }

    /* PAGINATION */
    function renderPaginationControls(totalPages) {
        if (!pagControls) return;
        if (totalPages <= 1) { pagControls.innerHTML = ""; return; }

        const html = [];
        html.push('<button type="button" data-page="' + (currentPage - 1) + '"' +
            (currentPage === 1 ? ' disabled' : '') +
            '><i class="bi bi-chevron-left"></i></button>');

        const pages = getPageList(currentPage, totalPages);
        pages.forEach(function (p) {
            if (p === "...") html.push('<span class="ellipsis">…</span>');
            else html.push(
                '<button type="button" data-page="' + p + '"' +
                (p === currentPage ? ' class="active"' : '') +
                '>' + p + '</button>'
            );
        });

        html.push('<button type="button" data-page="' + (currentPage + 1) + '"' +
            (currentPage === totalPages ? ' disabled' : '') +
            '><i class="bi bi-chevron-right"></i></button>');

        pagControls.innerHTML = html.join("");
    }

    function getPageList(current, total) {
        const delta = 1;
        const range = [];
        const out = [];
        for (let i = 1; i <= total; i++) {
            if (i === 1 || i === total || (i >= current - delta && i <= current + delta)) {
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

    if (pagControls) {
        pagControls.addEventListener("click", function (e) {
            const btn = e.target.closest("button[data-page]");
            if (!btn || btn.disabled) return;
            const page = Number(btn.dataset.page);
            if (!page || page === currentPage) return;
            currentPage = page;
            renderPage();
            const card = document.querySelector(".cu-card");
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

    /* KPI */
    function updateKPIs() {
        const total      = allRows.length;
        const active     = allRows.filter(c => Number(c.status) === 1).length;
        const walletSum  = allRows.reduce((s, c) => s + (Number(c.wallet_balance) || 0), 0);
        const withWallet = allRows.filter(c => (Number(c.wallet_balance) || 0) > 0).length;

        if (kpiTotal)      kpiTotal.textContent = total;
        if (kpiActive)     kpiActive.textContent = active;
        if (kpiWallet)     kpiWallet.textContent = money(walletSum);
        if (kpiWithWallet) kpiWithWallet.textContent = withWallet;
    }

    /* FILTER */
    function applyFilterAndRender(resetPage) {
        const q = (search ? search.value : "").trim().toLowerCase();

        if (!q) {
            filteredRows = allRows.slice();
        } else {
            filteredRows = allRows.filter(function (c) {
                const n  = (c.full_name || "").toLowerCase();
                const m  = (c.mobile_number || "").toLowerCase();
                const a  = (c.apartment_name || "").toLowerCase();
                const ac = (c.apartment_code || "").toLowerCase();
                const d  = (c.division || "").toLowerCase();
                return n.indexOf(q) !== -1 || m.indexOf(q) !== -1 ||
                       a.indexOf(q) !== -1 || ac.indexOf(q) !== -1 ||
                       d.indexOf(q) !== -1;
            });
        }

        if (resetPage !== false) currentPage = 1;
        renderPage();
    }

    /* LOAD */
    function loadCustomers() {
        tbody.innerHTML = `
            <tr><td colspan="6" style="text-align:center;padding:40px;color:#948c82;">
                <span style="display:inline-block;width:22px;height:22px;border:3px solid #eee7dc;border-top-color:#b51f2c;border-radius:50%;animation:cuSpin .7s linear infinite;"></span>
            </td></tr>`;
        if (paginationWrap) paginationWrap.style.display = "none";

        fetch(BASE_URL + "ajax/customers-list.php", { credentials: "same-origin" })
            .then(r => r.json().catch(() => ({ success: false, message: "Unexpected response." })))
            .then(data => {
                if (!data.success) {
                    tbody.innerHTML = `
                        <tr><td colspan="6">
                            <div class="cu-empty">
                                <i class="bi bi-exclamation-triangle"></i>
                                <h3>Failed to load</h3>
                                <p>${esc(data.message || "Please try again.")}</p>
                            </div>
                        </td></tr>`;
                    return;
                }
                allRows = Array.isArray(data.data) ? data.data : [];
                updateKPIs();
                currentPage = 1;
                applyFilterAndRender();
            })
            .catch(() => {
                tbody.innerHTML = `
                    <tr><td colspan="6">
                        <div class="cu-empty">
                            <i class="bi bi-wifi-off"></i>
                            <h3>Unable to connect</h3>
                            <p>Please check your network.</p>
                        </div>
                    </td></tr>`;
            });
    }

    /* SEARCH */
    let searchTimer = null;
    if (search) {
        search.addEventListener("input", function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => applyFilterAndRender(), 200);
        });
    }

    /* REFRESH */
    if (refreshBtn) {
        refreshBtn.addEventListener("click", function () {
            const icon = this.querySelector("i");
            if (icon) { icon.style.transition = "transform .5s"; icon.style.transform = "rotate(360deg)"; }
            setTimeout(() => { if (icon) icon.style.transform = "rotate(0deg)"; }, 500);
            loadCustomers();
        });
    }

    /* INIT */
    loadCustomers();

})();