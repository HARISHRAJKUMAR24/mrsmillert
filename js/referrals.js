/* =========================================================
   MRS MILL@ — REFERRALS (Admin)
   File: ./js/referrals.js
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL) ? window.ADMIN_URL : "./";

    const tbody          = document.getElementById("rfTbody");
    const search         = document.getElementById("rfSearch");
    const refreshBtn     = document.getElementById("rfRefresh");
    const paginationWrap = document.getElementById("rfPagination");
    const pagInfo        = document.getElementById("rfPagInfo");
    const pagControls    = document.getElementById("rfPagControls");
    const perPageSelect  = document.getElementById("rfPerPage");
    const toastWrap      = document.getElementById("rfToastWrap");

    const filterDate   = document.getElementById("rfFilterDate");
    const customRange  = document.getElementById("rfCustomRange");
    const dateFrom     = document.getElementById("rfDateFrom");
    const dateTo       = document.getElementById("rfDateTo");

    const editOverlay  = document.getElementById("rfEditOverlay");
    const editClose    = document.getElementById("rfEditClose");
    const editCancel   = document.getElementById("rfEditCancel");
    const editForm     = document.getElementById("rfEditForm");
    const editSubmit   = document.getElementById("rfEditSubmit");
    const editIdEl     = document.getElementById("rfEditId");
    const custNameEl   = document.getElementById("rfCustName");
    const custMobileEl = document.getElementById("rfCustMobile");
    const refNameEl    = document.getElementById("rfRefName");
    const refMobileEl  = document.getElementById("rfRefMobile");

    const DEFAULT_PAGE_SIZE = 10;
    let pageSize     = DEFAULT_PAGE_SIZE;
    let allRows      = [];
    let filteredRows = [];
    let currentPage  = 1;

    /* -------- helpers -------- */
    function esc(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }
    function initials(name) {
        name = String(name || "").trim();
        if (!name) return "?";
        const parts = name.split(/\s+/);
        return parts.length >= 2
            ? (parts[0][0] + parts[1][0]).toUpperCase()
            : name.substring(0, 2).toUpperCase();
    }
    function formatDate(s) {
        if (!s) return { d: "—", t: "" };
        try {
            const dt = new Date(s.replace(" ", "T"));
            return {
                d: dt.toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" }),
                t: dt.toLocaleTimeString("en-IN", { hour: "2-digit", minute: "2-digit" })
            };
        } catch (e) { return { d: s, t: "" }; }
    }
    function showToast(type, message, timeout) {
        if (!toastWrap) return;
        const icons = { success: "bi-check-lg", error: "bi-x-lg", info: "bi-info-lg" };
        const el = document.createElement("div");
        el.className = "rf-toast " + type;
        el.innerHTML = `
            <div class="rf-toast-icon"><i class="bi ${icons[type] || icons.info}"></i></div>
            <div class="rf-toast-body">${esc(message)}</div>
            <button type="button" class="rf-toast-close"><i class="bi bi-x"></i></button>
        `;
        toastWrap.appendChild(el);
        requestAnimationFrame(() => el.classList.add("show"));
        const close = () => { el.classList.remove("show"); setTimeout(() => el.remove(), 300); };
        el.querySelector(".rf-toast-close").addEventListener("click", close);
        setTimeout(close, timeout || 3200);
    }

    /* -------- date filter -------- */
    function startOfDay(d) { const x = new Date(d); x.setHours(0,0,0,0); return x; }
    function endOfDay(d)   { const x = new Date(d); x.setHours(23,59,59,999); return x; }
    function getDateRange() {
        const v = filterDate ? filterDate.value : "all";
        const now = new Date();
        if (v === "all") return null;
        if (v === "today") return [startOfDay(now), endOfDay(now)];
        if (v === "yesterday") { const y = new Date(now); y.setDate(y.getDate()-1); return [startOfDay(y), endOfDay(y)]; }
        if (v === "7d")  { const s = new Date(now); s.setDate(s.getDate()-6);  return [startOfDay(s), endOfDay(now)]; }
        if (v === "30d") { const s = new Date(now); s.setDate(s.getDate()-29); return [startOfDay(s), endOfDay(now)]; }
        if (v === "custom") {
            const from = dateFrom && dateFrom.value ? startOfDay(new Date(dateFrom.value)) : null;
            const to   = dateTo   && dateTo.value   ? endOfDay(new Date(dateTo.value))     : null;
            if (from && to) return [from, to];
            if (from) return [from, endOfDay(now)];
            if (to)   return [startOfDay(new Date(0)), to];
        }
        return null;
    }
    function parseRowDate(s) {
        if (!s) return null;
        const d = new Date(s.replace(" ", "T"));
        return isNaN(d) ? null : d;
    }

    /* -------- render -------- */
    function renderRow(r) {
        const dt = formatDate(r.created_at);
        const refName   = r.referrer_name || r.referrer_customer_name || "";
        const refMobile = r.referrer_mobile || r.referrer_customer_mobile || "";
        const isLinked  = Number(r.referrer_customer_id || 0) > 0;

        return `
            <tr data-id="${r.id}">
                <td>
                    <div class="rf-cust">
                        <div class="rf-avatar">${esc(initials(r.customer_name))}</div>
                        <div style="min-width:0;">
                            <span class="rf-cust-name">${esc(r.customer_name || "—")}</span>
                            <div class="rf-cust-mobile">${esc(r.customer_mobile || "—")}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="rf-ref-name">${esc(refName || "—")}</span>
                    <div class="rf-ref-mobile">${esc(refMobile || "")}</div>
                    ${isLinked ? `<span class="rf-ref-badge"><i class="bi bi-link-45deg"></i> Linked customer</span>` : ""}
                </td>
                <td>
                    <div class="rf-date">
                        ${esc(dt.d)}
                        ${dt.t ? `<small>${esc(dt.t)}</small>` : ""}
                    </div>
                </td>
                <td>
                    <div class="rf-actions">
                        <button type="button" class="rf-btn rf-btn-edit" data-action="edit" data-id="${r.id}">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }

    function renderPage() {
        const total = filteredRows.length;
        if (total === 0) {
            tbody.innerHTML = `<tr><td colspan="4"><div class="rf-empty">
                <i class="bi bi-inbox"></i><h3>No referrals found</h3>
                <p>Try adjusting your search or filters.</p></div></td></tr>`;
            if (paginationWrap) paginationWrap.style.display = "none";
            return;
        }
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;
        const start = (currentPage - 1) * pageSize;
        const end = Math.min(start + pageSize, total);
        tbody.innerHTML = filteredRows.slice(start, end).map(renderRow).join("");

        if (pagInfo) pagInfo.innerHTML =
            'Showing <strong>' + (start + 1) + '</strong>–<strong>' + end + '</strong> of <strong>' + total + '</strong>';
        renderPaginationControls(totalPages);
        if (paginationWrap) paginationWrap.style.display = "flex";
    }

    function renderPaginationControls(totalPages) {
        if (!pagControls) return;
        if (totalPages <= 1) { pagControls.innerHTML = ""; return; }
        const html = [];
        html.push(`<button data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''}><i class="bi bi-chevron-left"></i></button>`);
        getPageList(currentPage, totalPages).forEach(p => {
            if (p === "...") html.push('<span class="ellipsis">…</span>');
            else html.push(`<button data-page="${p}" ${p === currentPage ? 'class="active"' : ''}>${p}</button>`);
        });
        html.push(`<button data-page="${currentPage + 1}" ${currentPage === totalPages ? 'disabled' : ''}><i class="bi bi-chevron-right"></i></button>`);
        pagControls.innerHTML = html.join("");
    }

    function getPageList(current, total) {
        const delta = 1, range = [], out = [];
        for (let i = 1; i <= total; i++) {
            if (i === 1 || i === total || (i >= current - delta && i <= current + delta)) range.push(i);
        }
        let prev = 0;
        range.forEach(i => { if (prev && i - prev > 1) out.push("..."); out.push(i); prev = i; });
        return out;
    }

    if (pagControls) pagControls.addEventListener("click", e => {
        const btn = e.target.closest("button[data-page]");
        if (!btn || btn.disabled) return;
        const page = Number(btn.dataset.page);
        if (!page || page === currentPage) return;
        currentPage = page;
        renderPage();
        if (tbody) window.scrollTo({ top: tbody.offsetTop - 80, behavior: "smooth" });
    });

    if (perPageSelect) {
        perPageSelect.value = String(pageSize);
        perPageSelect.addEventListener("change", function () {
            const v = Number(this.value); if (!v) return;
            pageSize = v; currentPage = 1; renderPage();
        });
    }

    function applyFilterAndRender(resetPage) {
        const q = (search ? search.value : "").trim().toLowerCase();
        const range = getDateRange();

        filteredRows = allRows.filter(r => {
            if (q) {
                const hay = [
                    r.customer_name, r.customer_mobile,
                    r.referrer_name, r.referrer_mobile,
                    r.referrer_customer_name, r.referrer_customer_mobile
                ].join(" ").toLowerCase();
                if (hay.indexOf(q) === -1) return false;
            }
            if (range) {
                const d = parseRowDate(r.created_at);
                if (!d) return false;
                if (d < range[0] || d > range[1]) return false;
            }
            return true;
        });
        if (resetPage !== false) currentPage = 1;
        renderPage();
    }

    let searchTimer = null;
    if (search) search.addEventListener("input", () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => applyFilterAndRender(), 200);
    });
    if (filterDate) filterDate.addEventListener("change", () => {
        if (filterDate.value === "custom") { if (customRange) customRange.style.display = "flex"; }
        else { if (customRange) customRange.style.display = "none"; applyFilterAndRender(); }
    });
    if (dateFrom) dateFrom.addEventListener("change", () => applyFilterAndRender());
    if (dateTo)   dateTo.addEventListener("change",   () => applyFilterAndRender());

    /* -------- load -------- */
    function loadReferrals() {
        tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;padding:40px;color:#948c82;">
            <span style="display:inline-block;width:22px;height:22px;border:3px solid #eee7dc;border-top-color:#b51f2c;border-radius:50%;animation:rfSpin .7s linear infinite;"></span>
        </td></tr>`;
        if (paginationWrap) paginationWrap.style.display = "none";

        fetch(BASE_URL + "ajax/referrals-list.php", { credentials: "same-origin" })
            .then(r => r.json().catch(() => ({ success: false, message: "Unexpected server response." })))
            .then(data => {
                if (!data.success) {
                    tbody.innerHTML = `<tr><td colspan="4"><div class="rf-empty">
                        <i class="bi bi-exclamation-triangle"></i><h3>Failed to load</h3>
                        <p>${esc(data.message || "Please try again.")}</p></div></td></tr>`;
                    return;
                }
                allRows = Array.isArray(data.data) ? data.data : [];
                currentPage = 1;
                applyFilterAndRender();
            })
            .catch(() => {
                tbody.innerHTML = `<tr><td colspan="4"><div class="rf-empty">
                    <i class="bi bi-wifi-off"></i><h3>Unable to connect</h3>
                    <p>Please check your network.</p></div></td></tr>`;
            });
    }

    if (refreshBtn) refreshBtn.addEventListener("click", () => {
        const icon = refreshBtn.querySelector("i");
        if (icon) { icon.style.transition = "transform .5s"; icon.style.transform = "rotate(360deg)"; }
        setTimeout(() => { if (icon) icon.style.transform = "rotate(0deg)"; }, 500);
        loadReferrals();
    });

    /* -------- edit modal -------- */
    function openEditModal(r) {
        editIdEl.value = r.id;
        custNameEl.value = (r.customer_name || "") + (r.customer_mobile ? " · " + r.customer_mobile : "");
        custMobileEl.value = r.customer_mobile || "";

        /* prefer fresh referrer_customer_* if linked, else use stored fields */
        const linkedName = r.referrer_customer_name || "";
        const linkedMobile = r.referrer_customer_mobile || "";

        refNameEl.value   = linkedName   || r.referrer_name   || "";
        refMobileEl.value = linkedMobile || r.referrer_mobile || "";

        editOverlay.classList.add("show");
        editOverlay.setAttribute("aria-hidden", "false");
        setTimeout(() => refNameEl.focus(), 100);
    }
    function closeEditModal() {
        editOverlay.classList.remove("show");
        editOverlay.setAttribute("aria-hidden", "true");
    }

    if (editClose)  editClose.addEventListener("click", closeEditModal);
    if (editCancel) editCancel.addEventListener("click", closeEditModal);
    if (editOverlay) editOverlay.addEventListener("click", e => {
        if (e.target === editOverlay) closeEditModal();
    });
    document.addEventListener("keydown", e => {
        if (e.key === "Escape" && editOverlay.classList.contains("show")) closeEditModal();
    });

    /* digits only on mobile input */
    if (refMobileEl) {
        refMobileEl.addEventListener("input", function () {
            this.value = this.value.replace(/[^0-9]/g, "").slice(0, 15);
        });
    }

    if (editForm) editForm.addEventListener("submit", function (e) {
        e.preventDefault();

        const id = Number(editIdEl.value);
        const name = refNameEl.value.trim();
        const mobile = refMobileEl.value.trim();

        if (!id) return showToast("error", "Invalid referral ID.");
        if (name === "" && mobile === "") return showToast("error", "Enter a referrer name or mobile.");
        if (mobile !== "" && !/^[0-9]{10,15}$/.test(mobile)) {
            return showToast("error", "Mobile must be 10–15 digits.");
        }

        editSubmit.disabled = true;
        const oldHtml = editSubmit.innerHTML;
        editSubmit.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid #fff8;border-top-color:#fff;border-radius:50%;animation:rfSpin .7s linear infinite;"></span> Saving…';

        const fd = new FormData();
        fd.append("id", id);
        fd.append("referrer_name", name);
        fd.append("referrer_mobile", mobile);

        fetch(BASE_URL + "ajax/referrals-update.php", {
            method: "POST", body: fd, credentials: "same-origin"
        })
            .then(r => r.json().catch(() => ({ success: false, message: "Unexpected response." })))
            .then(res => {
                editSubmit.disabled = false;
                editSubmit.innerHTML = oldHtml;
                if (!res.success) return showToast("error", res.message || "Failed.");
                showToast("success", res.message || "Referral updated.");
                closeEditModal();
                loadReferrals();
            })
            .catch(() => {
                editSubmit.disabled = false;
                editSubmit.innerHTML = oldHtml;
                showToast("error", "Unable to connect.");
            });
    });

    /* -------- action clicks -------- */
    document.addEventListener("click", function (e) {
        const btn = e.target.closest("[data-action]");
        if (!btn) return;
        const action = btn.dataset.action;
        const id = btn.dataset.id;
        if (!id) return;

        if (action === "edit") {
            const row = allRows.find(x => String(x.id) === String(id));
            if (row) openEditModal(row);
        }
    });

    /* -------- init -------- */
    loadReferrals();

})();