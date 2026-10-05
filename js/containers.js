/* =========================================================
   MRS MILL@ — CONTAINER MANAGEMENT (customer grouped)
   File: ./js/containers.js
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    /* DOM */
    const groupList    = document.getElementById("ctGroupList");
    const search       = document.getElementById("ctSearch");
    const refreshBtn   = document.getElementById("ctRefresh");
    const tabs         = document.querySelectorAll(".ct-tab");

    const pagWrap      = document.getElementById("ctPagination");
    const pagInfo      = document.getElementById("ctPagInfo");
    const pagControls  = document.getElementById("ctPagControls");

    const kpiTotal        = document.getElementById("kpiTotal");
    const kpiNotReceived  = document.getElementById("kpiNotReceived");
    const kpiPending      = document.getElementById("kpiPending");
    const kpiAmount       = document.getElementById("kpiAmount");
    const kpiRefunded     = document.getElementById("kpiRefunded");

    /* Modal */
    const modal        = document.getElementById("ctReceiveModal");
    const modalClose   = document.getElementById("ctModalClose");
    const modalSub     = document.getElementById("ctModalSub");
    const infoBox      = document.getElementById("ctInfoBox");
    const form         = document.getElementById("ctReceiveForm");
    const groupKeyInp  = document.getElementById("ctGroupKey");
    const receivedCnt  = document.getElementById("ctReceivedCount");
    const noteInp      = document.getElementById("ctNote");
    const qtyHint      = document.getElementById("ctQtyHint");
    const refundEl     = document.getElementById("ctRefundAmount");
    const fullBtn      = document.getElementById("ctFullBtn");
    const quickBtns    = document.querySelectorAll(".ct-quick-btn[data-qty]");
    const cancelBtn    = document.getElementById("ctCancel");
    const submitBtn    = document.getElementById("ctSubmit");
    const submitText   = document.getElementById("ctSubmitText");

    const toastWrap    = document.getElementById("ctToastWrap");

    /* STATE */
    const DEFAULT_PAGE_SIZE = 10;

    let allGroups     = [];    // array of { key, customer_id, customer_name, customer_mobile, orders: [...] }
    let filtered      = [];
    let currentPage   = 1;
    let pageSize      = DEFAULT_PAGE_SIZE;
    let activeStatus  = "all";
    let searchTerm    = "";
    let editingGroup  = null;

    /* HELPERS */
    function money(n) {
        const v = Number(n) || 0;
        return "₹" + v.toFixed(2).replace(/\.00$/, "");
    }

    function esc(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function fmtDate(s) {
        if (!s) return "—";
        try {
            const dt = new Date(s.replace(" ", "T"));
            if (isNaN(dt.getTime())) return s;
            return dt.toLocaleString("en-IN", {
                day: "2-digit", month: "short", year: "numeric",
                hour: "2-digit", minute: "2-digit"
            });
        } catch (e) { return s; }
    }

    function initials(name) {
        name = String(name || "").trim();
        if (!name) return "?";
        const parts = name.split(/\s+/);
        if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
        return name.substring(0, 2).toUpperCase();
    }

    function statusLabel(s) {
        if (s === "received") return "Received";
        if (s === "partial")  return "Partial";
        return "Not Received";
    }

    /* TOAST */
    function showToast(type, message, timeout) {
        if (!toastWrap) return;
        const icons = { success: "bi-check-lg", error: "bi-x-lg", info: "bi-info-lg" };
        const el = document.createElement("div");
        el.className = "ct-toast " + type;
        el.innerHTML = `
            <div class="ct-toast-icon"><i class="bi ${icons[type] || icons.info}"></i></div>
            <div class="ct-toast-body">${esc(message)}</div>
            <button type="button" class="ct-toast-close"><i class="bi bi-x"></i></button>
        `;
        toastWrap.appendChild(el);
        requestAnimationFrame(() => el.classList.add("show"));
        const close = () => {
            el.classList.remove("show");
            setTimeout(() => el.remove(), 300);
        };
        el.querySelector(".ct-toast-close").addEventListener("click", close);
        setTimeout(close, timeout || 3200);
    }

    /* =========================================
       GROUP DATA (by customer_id / mobile)
       ========================================= */
    function buildGroups(rows) {
        const map = new Map();

        rows.forEach(r => {
            /* Group key: customer_id if present, else mobile */
            const key = r.customer_id && Number(r.customer_id) > 0
                ? "cid_" + r.customer_id
                : "mob_" + (r.customer_mobile || "unknown");

            if (!map.has(key)) {
                map.set(key, {
                    key:               key,
                    customer_id:       r.customer_id || 0,
                    customer_name:     r.customer_name || "—",
                    customer_mobile:   r.customer_mobile || "",
                    orders:            [],
                    total_containers:  0,
                    received_containers: 0,
                    pending_containers:  0,
                    pending_amount:      0,
                    refunded_amount:     0,
                    status:            "received",
                });
            }

            const g = map.get(key);

            g.orders.push(r);
            g.total_containers    += Number(r.total_containers || 0);
            g.received_containers += Number(r.received_containers || 0);
            g.pending_containers  += Number(r.pending_containers || 0);
            g.pending_amount      += Number(r.pending_containers || 0) * Number(r.container_unit_amount || 0);
            g.refunded_amount     += Number(r.received_containers || 0) * Number(r.container_unit_amount || 0);
        });

        /* Compute group-level status */
        map.forEach(g => {
            if (g.pending_containers <= 0)                 g.status = "received";
            else if (g.received_containers > 0)            g.status = "partial";
            else                                            g.status = "not_received";
        });

        /* Convert to array */
        return Array.from(map.values());
    }

    /* =========================================
       KPI
       ========================================= */
    function updateKPIs() {
        const total       = allGroups.length;
        const notReceived = allGroups.filter(g => g.status === "not_received").length;
        const pending     = allGroups.reduce((s, g) => s + g.pending_containers, 0);
        const pendAmt     = allGroups.reduce((s, g) => s + g.pending_amount, 0);
        const refunded    = allGroups.reduce((s, g) => s + g.refunded_amount, 0);

        if (kpiTotal)       kpiTotal.textContent = total;
        if (kpiNotReceived) kpiNotReceived.textContent = notReceived;
        if (kpiPending)     kpiPending.textContent = pending;
        if (kpiAmount)      kpiAmount.textContent = money(pendAmt);
        if (kpiRefunded)    kpiRefunded.textContent = money(refunded);
    }

    /* =========================================
       RENDER GROUP
       ========================================= */
    function renderGroup(g) {
        const ordersHtml = g.orders.map(o => {
            const pending = Number(o.pending_containers || 0);
            const subInfo = pending > 0
                ? pending + " pending"
                : "all returned";

            const receivedAt = o.received_at
                ? fmtDate(o.received_at)
                : "—";

            return `
                <div class="ct-order">
                    <div>
                        <div class="ct-order-code">#${esc(o.order_code)}</div>
                        <div class="ct-order-time">${esc(fmtDate(o.created_at))}</div>
                    </div>

                    <div>
                        <div class="ct-order-count">${o.received_containers}/${o.total_containers}</div>
                        <div class="ct-order-count-sub">${esc(subInfo)}</div>
                    </div>

                    <div class="ct-order-amount">${money(o.container_amount)}</div>

                    <div>
                        <span class="ct-order-status ${o.status}">
                            ${esc(statusLabel(o.status))}
                        </span>
                        <div class="ct-order-time" style="margin-top:4px;">${esc(receivedAt)}</div>
                    </div>
                </div>
            `;
        }).join("");

        const canReceive = g.pending_containers > 0;

        const receiveBar = canReceive
            ? `
                <div class="ct-receive-bar">
                    <div class="ct-receive-bar-lbl">
                        Pending: <strong>${g.pending_containers} container(s)</strong>
                        · Refund value: <strong>${money(g.pending_amount)}</strong>
                    </div>
                    <button type="button" class="ct-action receive" data-receive-key="${esc(g.key)}">
                        <i class="bi bi-box2-heart"></i> Receive Containers
                    </button>
                </div>
            `
            : `
                <div class="ct-receive-bar" style="background:#e8f6ea;border-color:#a7c8a9;">
                    <div class="ct-receive-bar-lbl" style="color:#1b5e20;">
                        <i class="bi bi-check-circle-fill"></i>
                        All containers received · Total refunded <strong style="color:#1b5e20;">${money(g.refunded_amount)}</strong>
                    </div>
                </div>
            `;

        return `
            <div class="ct-group" data-key="${esc(g.key)}">
                <div class="ct-group-head" data-toggle="${esc(g.key)}">
                    <div class="ct-cust">
                        <div class="ct-cust-avatar">${esc(initials(g.customer_name))}</div>
                        <div style="min-width:0;">
                            <div class="ct-cust-name">${esc(g.customer_name)}</div>
                            <div class="ct-cust-mobile">${esc(g.customer_mobile || "—")}</div>
                        </div>
                    </div>

                    <div class="ct-stat-box">
                        <div class="ct-stat-label">Orders</div>
                        <div class="ct-stat-val">${g.orders.length}</div>
                    </div>

                    <div class="ct-stat-box">
                        <div class="ct-stat-label">Pending</div>
                        <div class="ct-stat-val ${g.pending_containers > 0 ? 'warn' : 'ok'}">${g.pending_containers}</div>
                    </div>

                    <div class="ct-stat-box">
                        <div class="ct-stat-label">Refund value</div>
                        <div class="ct-stat-val money">${money(g.pending_amount)}</div>
                    </div>

                    <div class="ct-group-status ${g.status}">
                        ${esc(statusLabel(g.status))}
                    </div>

                    <div class="ct-group-arrow">
                        <i class="bi bi-chevron-right"></i>
                    </div>
                </div>

                <div class="ct-group-body">
                    <div class="ct-orders">
                        ${ordersHtml}
                    </div>
                    ${receiveBar}
                </div>
            </div>
        `;
    }

    /* =========================================
       RENDER PAGE
       ========================================= */
    function renderPage() {
        const total = filtered.length;

        if (total === 0) {
            groupList.innerHTML = `
                <div class="ct-empty">
                    <i class="bi bi-box"></i>
                    <h3 style="margin:0 0 6px;font-family:'Playfair Display',serif;font-size:16px;color:#6f675f;">No container records</h3>
                    <p style="margin:0;font-size:12px;">No customers match this filter.</p>
                </div>`;
            if (pagWrap) pagWrap.style.display = "none";
            return;
        }

        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const start = (currentPage - 1) * pageSize;
        const end   = Math.min(start + pageSize, total);
        const slice = filtered.slice(start, end);

        groupList.innerHTML = slice.map(renderGroup).join("");

        if (pagInfo) {
            pagInfo.innerHTML =
                'Showing <strong>' + (start + 1) + '</strong>–<strong>' + end +
                '</strong> of <strong>' + total + '</strong>';
        }

        renderPaginationControls(totalPages);
        if (pagWrap) pagWrap.style.display = "flex";
    }

    function renderPaginationControls(totalPages) {
        if (!pagControls) return;
        if (totalPages <= 1) { pagControls.innerHTML = ""; return; }

        const html = [];
        html.push('<button type="button" data-page="' + (currentPage - 1) + '"' +
            (currentPage === 1 ? ' disabled' : '') +
            '><i class="bi bi-chevron-left"></i></button>');

        const delta = 1;
        const pages = [];
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - delta && i <= currentPage + delta)) {
                pages.push(i);
            }
        }
        let prev = 0;
        pages.forEach(p => {
            if (prev && p - prev > 1) {
                html.push('<span style="padding:0 4px;color:#b5aca2;">…</span>');
            }
            html.push('<button type="button" data-page="' + p + '"' +
                (p === currentPage ? ' class="active"' : '') +
                '>' + p + '</button>');
            prev = p;
        });

        html.push('<button type="button" data-page="' + (currentPage + 1) + '"' +
            (currentPage === totalPages ? ' disabled' : '') +
            '><i class="bi bi-chevron-right"></i></button>');

        pagControls.innerHTML = html.join("");
    }

    pagControls?.addEventListener("click", (e) => {
        const btn = e.target.closest("button[data-page]");
        if (!btn || btn.disabled) return;
        const page = Number(btn.dataset.page);
        if (!page || page === currentPage) return;
        currentPage = page;
        renderPage();
    });

    /* =========================================
       FILTER
       ========================================= */
    function applyFilter(resetPage) {
        const q = (searchTerm || "").trim().toLowerCase();

        filtered = allGroups.filter(g => {
            if (activeStatus !== "all" && g.status !== activeStatus) return false;
            if (!q) return true;

            const orderCodes = g.orders.map(o => o.order_code || "").join(" ");
            const hay = [
                g.customer_name,
                g.customer_mobile,
                orderCodes
            ].join(" ").toLowerCase();

            return hay.indexOf(q) !== -1;
        });

        if (resetPage !== false) currentPage = 1;
        renderPage();
    }

    tabs.forEach(tab => {
        tab.addEventListener("click", function () {
            tabs.forEach(t => t.classList.remove("active"));
            this.classList.add("active");
            activeStatus = this.dataset.status || "all";
            applyFilter(true);
        });
    });

    let searchTimer = null;
    search?.addEventListener("input", function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            searchTerm = this.value;
            applyFilter(true);
        }, 200);
    });

    /* =========================================
       LOAD
       ========================================= */
    function loadContainers() {
        groupList.innerHTML = `
            <div style="text-align:center;padding:40px;color:#948c82;">
                <span class="ct-spinner" style="border-color:#ece5da;border-top-color:#b51f2c;"></span>
            </div>`;
        if (pagWrap) pagWrap.style.display = "none";

        fetch(BASE_URL + "ajax/containers-list.php", { credentials: "same-origin" })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !Array.isArray(res.data)) {
                    groupList.innerHTML = `
                        <div style="text-align:center;padding:40px;color:#b51f2c;">
                            ${esc((res && res.message) || "Failed to load.")}
                        </div>`;
                    return;
                }

                allGroups = buildGroups(res.data);
                updateKPIs();
                applyFilter(true);
            })
            .catch(() => {
                groupList.innerHTML = `
                    <div style="text-align:center;padding:40px;color:#b51f2c;">
                        Unable to connect.
                    </div>`;
            });
    }

    refreshBtn?.addEventListener("click", function () {
        const icon = this.querySelector("i");
        if (icon) { icon.style.transition = "transform .5s"; icon.style.transform = "rotate(360deg)"; }
        setTimeout(() => { if (icon) icon.style.transform = "rotate(0deg)"; }, 500);
        loadContainers();
    });

    /* =========================================
       TOGGLE EXPAND
       ========================================= */
    groupList?.addEventListener("click", function (e) {
        /* Receive button click */
        const receiveBtn = e.target.closest("[data-receive-key]");
        if (receiveBtn) {
            const key = receiveBtn.dataset.receiveKey;
            const group = allGroups.find(g => g.key === key);
            if (!group) return;
            openReceiveModal(group);
            return;
        }

        /* Toggle expand */
        const head = e.target.closest("[data-toggle]");
        if (head) {
            const group = head.closest(".ct-group");
            if (!group) return;
            group.classList.toggle("open");
        }
    });

    /* =========================================
       MODAL
       ========================================= */
    function openReceiveModal(group) {
        editingGroup = group;

        groupKeyInp.value = group.key;

        infoBox.innerHTML = `
            <div class="ct-info-row">
                <span class="lbl">Customer</span>
                <span class="val">${esc(group.customer_name)}</span>
            </div>
            <div class="ct-info-row">
                <span class="lbl">Mobile</span>
                <span class="val">${esc(group.customer_mobile || "—")}</span>
            </div>
            <div class="ct-info-row">
                <span class="lbl">Total Orders</span>
                <span class="val">${group.orders.length}</span>
            </div>
            <div class="ct-info-row">
                <span class="lbl">Pending Containers</span>
                <span class="val">${group.pending_containers}</span>
            </div>
            <div class="ct-info-row">
                <span class="lbl">Refund Value</span>
                <span class="val">${money(group.pending_amount)}</span>
            </div>
        `;

        receivedCnt.max = group.pending_containers;
        receivedCnt.value = "";
        noteInp.value = "";

        updateQtyHint();

        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");
        setTimeout(() => receivedCnt.focus(), 100);
    }

    function closeReceiveModal() {
        modal.classList.remove("show");
        modal.setAttribute("aria-hidden", "true");
        editingGroup = null;
        submitBtn.disabled = false;
        submitText.textContent = "Mark Received";
    }

    modalClose?.addEventListener("click", closeReceiveModal);
    cancelBtn?.addEventListener("click", closeReceiveModal);
    modal?.addEventListener("click", e => {
        if (e.target === modal) closeReceiveModal();
    });
    document.addEventListener("keydown", e => {
        if (e.key === "Escape" && modal.classList.contains("show")) closeReceiveModal();
    });

    quickBtns.forEach(b => {
        b.addEventListener("click", () => {
            receivedCnt.value = b.dataset.qty;
            updateQtyHint();
        });
    });

    fullBtn?.addEventListener("click", () => {
        if (!editingGroup) return;
        receivedCnt.value = editingGroup.pending_containers;
        updateQtyHint();
    });

    receivedCnt?.addEventListener("input", updateQtyHint);

    function updateQtyHint() {
        if (!editingGroup) return;
        const v = parseInt(receivedCnt.value || "0", 10);
        const pending = Number(editingGroup.pending_containers);

        /* Per-unit value = pending amount / pending containers */
        const unitAmt = pending > 0
            ? (Number(editingGroup.pending_amount) / pending)
            : 0;

        if (!v || v <= 0) {
            qtyHint.textContent = "Enter a number between 1 and " + pending + ".";
            qtyHint.className = "hint";
            refundEl.textContent = "₹0";
            return;
        }

        if (v > pending) {
            qtyHint.textContent = "Too many — max " + pending + ".";
            qtyHint.className = "hint red";
            refundEl.textContent = money(pending * unitAmt);
            return;
        }

        qtyHint.textContent = "Will refund " + money(v * unitAmt) + " to wallet.";
        qtyHint.className = "hint green";
        refundEl.textContent = money(v * unitAmt);
    }

    /* =========================================
       RECEIVE SUBMIT
       ========================================= */
    form?.addEventListener("submit", function (e) {
        e.preventDefault();

        if (!editingGroup) return;

        const v = parseInt(receivedCnt.value || "0", 10);
        const pending = Number(editingGroup.pending_containers);

        if (!v || v <= 0) {
            showToast("error", "Enter a valid count.");
            return;
        }
        if (v > pending) {
            showToast("error", "Cannot exceed " + pending + ".");
            return;
        }

        submitBtn.disabled = true;
        submitText.innerHTML = '<span class="ct-spinner"></span> Processing...';

        const fd = new FormData();
        fd.append("group_key", editingGroup.key);
        fd.append("customer_id", editingGroup.customer_id || 0);
        fd.append("received_containers", v);
        fd.append("note", noteInp.value.trim());

        fetch(BASE_URL + "ajax/container-receive.php", {
            method: "POST",
            body: fd,
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success) {
                    showToast("error", (res && res.message) || "Failed to mark received.");
                    submitBtn.disabled = false;
                    submitText.textContent = "Mark Received";
                    return;
                }

                showToast("success", res.message || "Received successfully.");
                closeReceiveModal();
                loadContainers();
            })
            .catch(() => {
                showToast("error", "Unable to connect.");
                submitBtn.disabled = false;
                submitText.textContent = "Mark Received";
            });
    });

    /* =========================================
       INIT
       ========================================= */
    loadContainers();

})();