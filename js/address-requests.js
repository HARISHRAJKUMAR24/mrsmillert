/* =========================================================
   MRS MILL@ — ADDRESS REQUESTS LIST
   File: ./js/address-requests.js
   - Client-side search + pagination + per-page
   - Status dropdown (0 = Not Called, 1 = Called, 2 = Rejected)
   - Call button opens tel: link
   - WhatsApp button opens wa.me chat
   - Calls status update endpoint
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    const tbody          = document.getElementById("arTbody");
    const search         = document.getElementById("arSearch");
    const refreshBtn     = document.getElementById("arRefresh");

    const paginationWrap = document.getElementById("arPagination");
    const pagInfo        = document.getElementById("arPagInfo");
    const pagControls    = document.getElementById("arPagControls");
    const perPageSelect  = document.getElementById("arPerPage");

    const toastWrap      = document.getElementById("arToastWrap");

    /* =========================================
       STATE
    ========================================= */
    const DEFAULT_PAGE_SIZE = 10;

    let pageSize     = DEFAULT_PAGE_SIZE;
    let allRows      = [];
    let filteredRows = [];
    let currentPage  = 1;

    /* Status labels */
    const STATUS_LABELS = {
        "0": "Not Called",
        "1": "Called",
        "2": "Rejected"
    };

    /* =========================================
       HELPERS
    ========================================= */
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

    function formatDate(s) {
        if (!s) return { d: "—", t: "" };
        try {
            const dt = new Date(s.replace(" ", "T"));
            const d = dt.toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" });
            const t = dt.toLocaleTimeString("en-IN", { hour: "2-digit", minute: "2-digit" });
            return { d, t };
        } catch (e) {
            return { d: s, t: "" };
        }
    }

    /* WhatsApp — strips non-digits, adds country code 91 for India if missing */
    function waLink(mobile, name) {
        let digits = String(mobile || "").replace(/\D/g, "");

        if (!digits) return "#";

        /* Add 91 (India) if it's a 10-digit number without country code */
        if (digits.length === 10) {
            digits = "91" + digits;
        }

        /* Pre-filled message */
        const msg = encodeURIComponent(
            "Hello " + (name || "") + ", this is Mrs Mill@. Regarding your address request"
        );

        return "https://wa.me/" + digits + "?text=" + msg;
    }

    /* =========================================
       TOAST
    ========================================= */
    function showToast(type, message, timeout) {
        if (!toastWrap) return;

        const icons = { success: "bi-check-lg", error: "bi-x-lg", info: "bi-info-lg" };

        const el = document.createElement("div");
        el.className = "ar-toast " + type;
        el.innerHTML = `
            <div class="ar-toast-icon"><i class="bi ${icons[type] || icons.info}"></i></div>
            <div class="ar-toast-body">${esc(message)}</div>
            <button type="button" class="ar-toast-close"><i class="bi bi-x"></i></button>
        `;

        toastWrap.appendChild(el);
        requestAnimationFrame(() => el.classList.add("show"));

        const close = () => {
            el.classList.remove("show");
            setTimeout(() => el.remove(), 300);
        };

        el.querySelector(".ar-toast-close").addEventListener("click", close);
        setTimeout(close, timeout || 3200);
    }

    /* =========================================
       STATUS DROPDOWN
       ========================================= */
    function statusBtnHtml(status) {
        const s = String(status);
        const label = STATUS_LABELS[s] || "Unknown";
        return `
            <button type="button" class="ar-status-btn" data-status="${s}">
                ${esc(label)}
                <i class="bi bi-chevron-down caret"></i>
            </button>
        `;
    }

    function statusMenuHtml(currentStatus) {
        const options = ["0", "1", "2"];
        return options.map(s => `
            <div class="ar-status-opt ${String(currentStatus) === s ? 'active' : ''}" data-status="${s}">
                <span class="dot"></span>
                ${esc(STATUS_LABELS[s])}
            </div>
        `).join("");
    }

    /* =========================================
       RENDER ROW
       ========================================= */
    function renderRow(r) {
        const dt  = formatDate(r.created_at);
        const st  = String(r.status ?? "0");
        const tel = String(r.customer_mobile || "").replace(/\D/g, "");

        const callHref = tel ? `tel:${tel}` : "#";
        const waHref   = waLink(r.customer_mobile, r.customer_name);

        return `
            <tr data-id="${r.id}">
                <td>
                    <div class="ar-customer">
                        <div class="ar-avatar">${esc(initials(r.customer_name))}</div>
                        <div>
                            <span class="ar-cust-name">${esc(r.customer_name)}</span>
                            <div class="ar-cust-mobile">${esc(r.customer_mobile)}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="ar-apartment">${esc(r.requested_apartment || "—")}</span>
                </td>
                <td>
                    <span class="ar-apartment" style="font-weight:600;">${esc(r.requested_division || "—")}</span>
                </td>
                <td>
                    <div class="ar-date">
                        ${esc(dt.d)}
                        ${dt.t ? `<small>${esc(dt.t)}</small>` : ""}
                    </div>
                </td>
                <td>
                    <div class="ar-status" data-id="${r.id}" data-status="${st}">
                        ${statusBtnHtml(st)}
                        <div class="ar-status-menu">
                            ${statusMenuHtml(st)}
                        </div>
                    </div>
                </td>
                <td>
                    <div class="ar-actions">
                        <a class="ar-call" href="${callHref}" title="Call customer">
                            <i class="bi bi-telephone-fill"></i>
                            Call
                        </a>
                        <a class="ar-whatsapp" href="${waHref}" target="_blank" rel="noopener" title="WhatsApp customer">
                            <i class="bi bi-whatsapp"></i>
                            WhatsApp
                        </a>
                    </div>
                </td>
            </tr>
        `;
    }

    /* =========================================
       RENDER PAGE
       ========================================= */
    function renderPage() {
        const total = filteredRows.length;

        if (total === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6">
                        <div class="ar-empty">
                            <i class="bi bi-inbox"></i>
                            <h3>No requests found</h3>
                            <p>Try adjusting your search or refresh the list.</p>
                        </div>
                    </td>
                </tr>
            `;
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

    /* =========================================
       PAGINATION CONTROLS
       ========================================= */
    function renderPaginationControls(totalPages) {
        if (!pagControls) return;

        if (totalPages <= 1) {
            pagControls.innerHTML = "";
            return;
        }

        const html = [];

        html.push(
            '<button type="button" data-page="' + (currentPage - 1) + '"' +
            (currentPage === 1 ? ' disabled' : '') +
            '><i class="bi bi-chevron-left"></i></button>'
        );

        const pages = getPageList(currentPage, totalPages);

        pages.forEach(function (p) {
            if (p === "...") {
                html.push('<span class="ellipsis">…</span>');
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
            '><i class="bi bi-chevron-right"></i></button>'
        );

        pagControls.innerHTML = html.join("");
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

    if (pagControls) {
        pagControls.addEventListener("click", function (e) {
            const btn = e.target.closest("button[data-page]");
            if (!btn || btn.disabled) return;

            const page = Number(btn.dataset.page);
            if (!page || page === currentPage) return;

            currentPage = page;
            renderPage();

            const card = document.querySelector(".ar-card");
            if (card) {
                window.scrollTo({ top: card.offsetTop - 20, behavior: "smooth" });
            }
        });
    }

    /* =========================================
       PER-PAGE
       ========================================= */
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

    /* =========================================
       FILTER
       ========================================= */
    function applyFilterAndRender(resetPage) {
        const q = (search ? search.value : "").trim().toLowerCase();

        if (!q) {
            filteredRows = allRows.slice();
        } else {
            filteredRows = allRows.filter(function (r) {
                const name = (r.customer_name || "").toLowerCase();
                const mob  = (r.customer_mobile || "").toLowerCase();
                const apt  = (r.requested_apartment || "").toLowerCase();
                const div  = (r.requested_division || "").toLowerCase();
                return name.indexOf(q) !== -1 ||
                       mob.indexOf(q) !== -1 ||
                       apt.indexOf(q) !== -1 ||
                       div.indexOf(q) !== -1;
            });
        }

        if (resetPage !== false) currentPage = 1;
        renderPage();
    }

    /* =========================================
       LOAD LIST
       ========================================= */
    function loadRequests() {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align:center;padding:40px;color:#948c82;">
                    <span style="display:inline-block;width:22px;height:22px;border:3px solid #eee7dc;border-top-color:#b51f2c;border-radius:50%;animation:arSpin .7s linear infinite;"></span>
                </td>
            </tr>
        `;

        if (paginationWrap) paginationWrap.style.display = "none";

        fetch(BASE_URL + "ajax/address-requests-list.php", {
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => ({ success: false, message: "Unexpected server response." })))
            .then(data => {
                if (!data.success) {
                    tbody.innerHTML = `
                        <tr><td colspan="6">
                            <div class="ar-empty">
                                <i class="bi bi-exclamation-triangle"></i>
                                <h3>Failed to load</h3>
                                <p>${esc(data.message || "Please try again.")}</p>
                            </div>
                        </td></tr>
                    `;
                    return;
                }

                allRows = Array.isArray(data.data) ? data.data : [];
                currentPage = 1;
                applyFilterAndRender();
            })
            .catch(() => {
                tbody.innerHTML = `
                    <tr><td colspan="6">
                        <div class="ar-empty">
                            <i class="bi bi-wifi-off"></i>
                            <h3>Unable to connect</h3>
                            <p>Please check your network.</p>
                        </div>
                    </td></tr>
                `;
            });
    }

    /* =========================================
       SEARCH (debounced)
       ========================================= */
    let searchTimer = null;
    if (search) {
        search.addEventListener("input", function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                applyFilterAndRender();
            }, 200);
        });
    }

    /* =========================================
       REFRESH BUTTON
       ========================================= */
    if (refreshBtn) {
        refreshBtn.addEventListener("click", function () {
            const icon = this.querySelector("i");
            if (icon) icon.style.transition = "transform .5s";
            if (icon) icon.style.transform = "rotate(360deg)";

            setTimeout(() => {
                if (icon) icon.style.transform = "rotate(0deg)";
            }, 500);

            loadRequests();
        });
    }

    /* =========================================
       STATUS DROPDOWN — OPEN / CLOSE / SELECT
       ========================================= */
    document.addEventListener("click", function (e) {
        const btn = e.target.closest(".ar-status-btn");
        if (btn) {
            e.stopPropagation();
            const wrap = btn.closest(".ar-status");
            const wasOpen = wrap.classList.contains("open");

            document.querySelectorAll(".ar-status.open").forEach(el => {
                el.classList.remove("open");
            });

            if (!wasOpen) wrap.classList.add("open");
            return;
        }

        const opt = e.target.closest(".ar-status-opt");
        if (opt) {
            e.stopPropagation();
            const wrap = opt.closest(".ar-status");
            const id   = wrap.dataset.id;
            const st   = opt.dataset.status;

            wrap.classList.remove("open");
            updateStatus(id, st, wrap);
            return;
        }

        document.querySelectorAll(".ar-status.open").forEach(el => {
            el.classList.remove("open");
        });
    });

    /* =========================================
       UPDATE STATUS
       ========================================= */
    function updateStatus(id, status, wrap) {
        const fd = new FormData();
        fd.append("id", id);
        fd.append("status", status);

        fetch(BASE_URL + "ajax/update-address-request-status.php", {
            method: "POST",
            body: fd,
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success) {
                    showToast("error", (res && res.message) || "Failed to update status.");
                    return;
                }

                const row = allRows.find(r => String(r.id) === String(id));
                if (row) row.status = Number(status);

                wrap.dataset.status = String(status);
                const btn = wrap.querySelector(".ar-status-btn");
                if (btn) {
                    btn.dataset.status = String(status);
                    btn.innerHTML = `${esc(STATUS_LABELS[String(status)] || '')}
                                     <i class="bi bi-chevron-down caret"></i>`;
                }

                wrap.querySelectorAll(".ar-status-opt").forEach(o => {
                    o.classList.toggle("active", o.dataset.status === String(status));
                });

                showToast("success", "Status updated to " + (STATUS_LABELS[String(status)] || status));
            })
            .catch(() => {
                showToast("error", "Unable to connect.");
            });
    }

    /* =========================================
       INIT
       ========================================= */
    loadRequests();

})();