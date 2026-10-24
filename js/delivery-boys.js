/* =========================================================
   MRS MILL@ — DELIVERY BOYS LIST
   File: ./js/delivery-boys.js
   - Live status change
   - Live search
   - Click row to open popup (profile + pending orders + requests)
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL) ? window.ADMIN_URL : "./";

    const table       = document.getElementById("dbTable");
    const searchInput = document.getElementById("dbSearch");

    const modal       = document.getElementById("dbModal");
    const modalClose  = document.getElementById("dbModalClose");
    const modalAvatar = document.getElementById("dbModalAvatar");
    const modalName   = document.getElementById("dbModalName");
    const modalMeta   = document.getElementById("dbModalMeta");
    const modalBody   = document.getElementById("dbModalBody");

    const BOYS     = Array.isArray(window.BOYS_DATA) ? window.BOYS_DATA : [];
    const PENDING  = window.PENDING_BY_BOY || {};
    const REQUESTS = window.REQUESTS_BY_BOY || {};

    if (!table) return;

    /* =========================================
       HELPERS
    ========================================= */
    function escHtml(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function moneyFmt(n) {
        return "₹" + Math.round(Number(n) || 0);
    }

    function fmtDateTime(s) {
        if (!s) return "—";
        try {
            var dt = new Date(s.replace(" ", "T"));
            if (isNaN(dt.getTime())) return s;
            return dt.toLocaleString("en-IN", {
                day: "2-digit", month: "short", year: "numeric",
                hour: "2-digit", minute: "2-digit"
            });
        } catch (e) { return s; }
    }

    function showToast(message, type) {
        let toast = document.getElementById("mmToast");
        if (!toast) {
            toast = document.createElement("div");
            toast.id = "mmToast";
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.className = "show" + (type === "error" ? " error" : "");
        clearTimeout(toast._timer);
        toast._timer = setTimeout(() => { toast.className = ""; }, 2600);
    }

    /* =========================================
       STATUS CHANGE
    ========================================= */
    table.querySelectorAll("select.boy-status").forEach(function (sel) {
        sel.addEventListener("change", function () {
            const boyId = sel.dataset.id;
            const newVal = sel.value;
            const oldVal = sel.dataset.status;
            const wrap = sel.closest(".status-select");

            sel.disabled = true;
            wrap.dataset.status = newVal;
            sel.dataset.status = newVal;

            const fd = new FormData();
            fd.append("id", boyId);
            fd.append("status", newVal);

            fetch(BASE_URL + "ajax/toggle-delivery-boy-status.php", {
                method: "POST",
                body: fd,
                credentials: "same-origin"
            })
            .then(r => r.json().catch(() => ({ success: false, message: "Unexpected server response." })))
            .then(data => {
                if (data.success) {
                    showToast(data.message || "Status updated.");
                } else {
                    sel.value = oldVal;
                    sel.dataset.status = oldVal;
                    wrap.dataset.status = oldVal;
                    showToast(data.message || "Failed to update.", "error");
                }
                sel.disabled = false;
            })
            .catch(() => {
                sel.value = oldVal;
                sel.dataset.status = oldVal;
                wrap.dataset.status = oldVal;
                sel.disabled = false;
                showToast("Unable to connect to server.", "error");
            });
        });
    });

    /* =========================================
       LIVE SEARCH
    ========================================= */
    if (searchInput) {
        searchInput.addEventListener("input", function () {
            const q = searchInput.value.trim().toLowerCase();
            table.querySelectorAll("tbody tr").forEach(function (row) {
                const haystack = (row.dataset.search || "").toLowerCase();
                row.style.display = !q || haystack.includes(q) ? "" : "none";
            });
        });
    }

    /* =========================================
       POPUP
    ========================================= */
    function renderRequestItem(r) {
        const isContainer = r.request_type === "container_return";
        const isCredit    = r.request_type === "wallet_credit";

        let typeLabel = "Container";
        let typeCls = "container";
        if (r.request_type === "wallet_credit") { typeLabel = "Add Money"; typeCls = "credit"; }
        if (r.request_type === "wallet_debit")  { typeLabel = "Deduct";    typeCls = "debit"; }

        const amountOrCount = isContainer
            ? (r.container_count + " pcs")
            : moneyFmt(r.amount);

        const statusCls = String(r.status || "").toLowerCase();

        return `
            <div class="db-req-item">
                <div class="db-req-left">
                    <span class="db-req-type ${typeCls}">${typeLabel}</span>
                    <div class="db-req-meta">
                        ${escHtml(r.customer_name || "")} · ${escHtml(r.customer_mobile || "")}
                        <br>
                        ${escHtml(fmtDateTime(r.created_at))}
                        ${r.note ? '<br><em style="font-style:normal;color:#6f5a3f;">' + escHtml(r.note) + '</em>' : ''}
                        ${r.admin_note ? '<br><span style="color:#948c82;">Admin: ' + escHtml(r.admin_note) + '</span>' : ''}
                    </div>
                </div>
                <div style="text-align:right;">
                    <div class="db-req-amount">${escHtml(amountOrCount)}</div>
                    <span class="db-req-status ${statusCls}">${escHtml(r.status || "")}</span>
                </div>
            </div>
        `;
    }

    function renderPendingOrder(o) {
        return `
            <div class="db-order-item">
                <div class="db-order-info">
                    <div class="db-order-code">#${escHtml(o.order_code || "")}</div>
                    <div class="db-order-meta">
                        ${escHtml(o.customer_name || "—")} · ${escHtml(o.customer_mobile || "")}
                        <br>
                        ${escHtml(fmtDateTime(o.created_at))}
                    </div>
                </div>
                <div class="db-order-amount">${moneyFmt(o.total_amount)}</div>
            </div>
        `;
    }

    function openPopup(boy) {
        if (!modal || !boy) return;

        const boyId = Number(boy.id);
        const initial = String(boy.full_name || "D").charAt(0).toUpperCase();

        modalAvatar.textContent = initial;
        modalName.textContent = boy.full_name || "Delivery Boy";
        modalMeta.innerHTML = `<strong>#${escHtml(boy.delivery_code || "")}</strong> · ${escHtml(boy.mobile_number || "")}`;

        const pending  = Array.isArray(PENDING[boyId])  ? PENDING[boyId]  : [];
        const requests = Array.isArray(REQUESTS[boyId]) ? REQUESTS[boyId] : [];

        const totalPendingAmount = pending.reduce((s, o) => s + Number(o.total_amount || 0), 0);

        let html = '';

        /* --- Profile section --- */
        html += `
            <div class="db-modal-section">
                <h4 class="db-modal-title"><i class="bi bi-person-badge"></i> Profile</h4>
                <div class="db-info-grid">
                    <div class="db-info-tile">
                        <div class="lbl">Delivery Code</div>
                        <div class="val">#${escHtml(boy.delivery_code || "—")}</div>
                    </div>
                    <div class="db-info-tile">
                        <div class="lbl">Mobile</div>
                        <div class="val">${escHtml(boy.mobile_number || "—")}</div>
                    </div>
                    <div class="db-info-tile">
                        <div class="lbl">Email</div>
                        <div class="val">${escHtml(boy.email_address || "—")}</div>
                    </div>
                    <div class="db-info-tile">
                        <div class="lbl">Branch</div>
                        <div class="val">${escHtml(boy.branch_name || "Not assigned")}</div>
                    </div>
                    <div class="db-info-tile">
                        <div class="lbl">Status</div>
                        <div class="val">${Number(boy.status) === 1 ? '<span style="color:#2e7d32;">Active</span>' : '<span style="color:#b51f2c;">Inactive</span>'}</div>
                    </div>
                    <div class="db-info-tile">
                        <div class="lbl">Last Login</div>
                        <div class="val">${escHtml(fmtDateTime(boy.last_login_at))}</div>
                    </div>
                </div>
            </div>
        `;

        /* --- Pending Deliveries --- */
        html += `
            <div class="db-modal-section">
                <h4 class="db-modal-title"><i class="bi bi-truck"></i> Pending Deliveries (${pending.length})</h4>
                ${pending.length
                    ? `<div class="db-order-list">${pending.map(renderPendingOrder).join("")}</div>
                       <div style="margin-top:10px;padding:10px 14px;background:#fff8e6;border:1.5px solid #f0dca0;border-radius:10px;font-size:11.5px;font-weight:700;color:#8a6a1e;">
                           Total pending value: <strong>${moneyFmt(totalPendingAmount)}</strong>
                       </div>`
                    : `<div class="db-empty-section">
                           <i class="bi bi-check-circle"></i>
                           No pending deliveries
                       </div>`
                }
            </div>
        `;

        /* --- Requests --- */
        html += `
            <div class="db-modal-section">
                <h4 class="db-modal-title"><i class="bi bi-inbox"></i> Requests (${requests.length})</h4>
                ${requests.length
                    ? `<div class="db-req-list">${requests.map(renderRequestItem).join("")}</div>`
                    : `<div class="db-empty-section">
                           <i class="bi bi-inbox"></i>
                           No requests from this delivery boy
                       </div>`
                }
            </div>
        `;

        modalBody.innerHTML = html;

        modal.classList.add("show");
        document.body.style.overflow = "hidden";
    }

    function closePopup() {
        if (!modal) return;
        modal.classList.remove("show");
        document.body.style.overflow = "";
    }

    modalClose && modalClose.addEventListener("click", closePopup);
    modal && modal.addEventListener("click", function (e) {
        if (e.target === modal) closePopup();
    });
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && modal && modal.classList.contains("show")) closePopup();
    });

    /* Click row → open popup */
    table.querySelectorAll("tbody tr").forEach(function (row) {
        row.addEventListener("click", function (e) {
            /* Don't open if clicking edit link or status dropdown */
            if (e.target.closest("a") || e.target.closest("select")) return;

            const id = Number(this.dataset.id);
            const boy = BOYS.find(b => Number(b.id) === id);
            if (boy) openPopup(boy);
        });
    });

})();