/* =========================================================
   MRS MILL@ — MENU + APARTMENT ORDERS
   File: ./js/apartment-orders.js
   - Apartment vs Individual House segmented toggle
   - IH queries summary with apartment_id = -1
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    const IH_ID = "-1";

    /* DOM — Menu */
    const menuDdWrap    = document.getElementById("aoMenuDdWrap");
    const menuDdToggle  = document.getElementById("aoMenuDdToggle");
    const menuDdLabel   = document.getElementById("aoMenuDdLabel");
    const menuDdSearch  = document.getElementById("aoMenuDdSearch");
    const menuDdList    = document.getElementById("aoMenuDdList");
    const menuCodeInput = document.getElementById("aoMenuCode");

    /* DOM — Address type toggle */
    const segWrap   = document.getElementById("aoAddressSeg");
    const segBtns   = segWrap ? segWrap.querySelectorAll("button[data-type]") : [];
    const aptWrap   = document.getElementById("aoAptWrap");
    const ihNote    = document.getElementById("aoIHNote");

    /* DOM — Apartment */
    const aptDdWrap    = document.getElementById("aoAptDdWrap");
    const aptDdToggle  = document.getElementById("aoAptDdToggle");
    const aptDdLabel   = document.getElementById("aoAptDdLabel");
    const aptDdSearch  = document.getElementById("aoAptDdSearch");
    const aptDdList    = document.getElementById("aoAptDdList");
    const aptIdInput   = document.getElementById("aoApartmentId");
    const aptCodeInput = document.getElementById("aoApartmentCode");

    /* DOM — Date */
    const dateFilter = document.getElementById("aoDateFilter");
    const customWrap = document.getElementById("aoCustomWrap");
    const dateFrom   = document.getElementById("aoDateFrom");
    const dateTo     = document.getElementById("aoDateTo");

    /* DOM — KPIs + Results */
    const kpisWrap    = document.getElementById("aoKpis");
    const kpiOrders   = document.getElementById("kpiOrders");
    const kpiProducts = document.getElementById("kpiProducts");
    const kpiQty      = document.getElementById("kpiQty");
    const kpiAmount   = document.getElementById("kpiAmount");
    const resultsWrap = document.getElementById("aoResultsWrap");

    /* STATE */
    let menusCache      = [];
    let apartmentsCache = [];
    let loading         = false;
    let activeType      = "apartment"; /* 'apartment' | 'individual_house' */

    /* HELPERS */
    function esc(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function money(n) {
        const v = Number(n) || 0;
        return "₹" + v.toFixed(2).replace(/\.00$/, "");
    }

    function fmtDate(s) {
        if (!s) return "—";
        try {
            const dt = new Date(s.replace(" ", "T"));
            if (isNaN(dt.getTime())) return s;
            const dd  = String(dt.getDate()).padStart(2, "0");
            const mon = dt.toLocaleString("en-IN", { month: "short" });
            const yr  = dt.getFullYear();
            let h = dt.getHours();
            const mm = String(dt.getMinutes()).padStart(2, "0");
            const ampm = h >= 12 ? "PM" : "AM";
            h = h % 12;
            if (h === 0) h = 12;
            const hh = String(h).padStart(2, "0");
            return `${dd} ${mon} ${yr} · ${hh}:${mm} ${ampm}`;
        } catch (e) { return s; }
    }

    function statusClass(s) {
        const map = {
            pending:    "ao-status-pending",
            confirmed:  "ao-status-confirmed",
            processing: "ao-status-processing",
            delivered:  "ao-status-delivered",
            cancelled:  "ao-status-cancelled"
        };
        return map[s] || "ao-status-pending";
    }

    function payClass(s) {
        return s === "paid" ? "ao-pay-paid" : "ao-pay-unpaid";
    }

    /* =========================================
       ADDRESS TYPE TOGGLE
       ========================================= */
    function setActiveType(type) {
        activeType = type;

        segBtns.forEach(b => {
            b.classList.toggle("active", b.dataset.type === type);
        });

        if (type === "individual_house") {
            /* Hide apartment dropdown, show IH note */
            if (aptWrap) aptWrap.style.display = "none";
            if (ihNote)  ihNote.style.display = "flex";
            closeAptDd();

            /* Clear apartment selection; use sentinel */
            aptIdInput.value   = IH_ID;
            aptCodeInput.value = "INDIVIDUAL";

            /* If menu is selected → load results immediately */
            if (menuCodeInput.value) {
                loadOrders();
            } else {
                resetResults();
            }
        } else {
            /* Apartment mode → show dropdown */
            if (aptWrap) aptWrap.style.display = "";
            if (ihNote)  ihNote.style.display = "none";

            /* Reset selection */
            aptIdInput.value   = "";
            aptCodeInput.value = "";
            aptDdLabel.textContent = menuCodeInput.value
                ? "— Select apartment —"
                : "— Select menu first —";
            aptDdLabel.classList.add("placeholder");
            aptDdToggle.classList.remove("has-value");
            aptDdToggle.disabled = !menuCodeInput.value;

            resetResults();
        }
    }

    segBtns.forEach(btn => {
        btn.addEventListener("click", function () {
            if (btn.classList.contains("active")) return;
            setActiveType(btn.dataset.type);
        });
    });

    /* =========================================
       MENU DROPDOWN
       ========================================= */
    function renderMenuOptions(query) {
        if (!menuDdList) return;

        if (!menusCache.length) {
            menuDdList.innerHTML = `<div class="sd-empty">No menus available.</div>`;
            return;
        }

        const q = (query || "").trim().toLowerCase();
        const list = q
            ? menusCache.filter(m =>
                (m.menu_name || "").toLowerCase().includes(q) ||
                (m.menu_code || "").toLowerCase().includes(q))
            : menusCache;

        if (!list.length) {
            menuDdList.innerHTML = `<div class="sd-empty">No matches.</div>`;
            return;
        }

        menuDdList.innerHTML = list.slice(0, 60).map(m => {
            const selected = String(menuCodeInput.value) === String(m.menu_code);
            return `
                <div class="sd-option ${selected ? 'selected' : ''}"
                     data-code="${esc(m.menu_code)}"
                     data-name="${esc(m.menu_name)}">
                    <i class="bi bi-list-ul"></i>
                    <div class="name">${esc(m.menu_name)}</div>
                    <div class="meta">#${esc(m.menu_code || '')}</div>
                </div>
            `;
        }).join("");
    }

    function openMenuDd() {
        menuDdWrap.classList.add("open");
        closeAptDd();
        if (menuDdSearch) {
            menuDdSearch.value = "";
            setTimeout(() => menuDdSearch.focus(), 60);
        }
        renderMenuOptions("");
    }

    function closeMenuDd() {
        menuDdWrap.classList.remove("open");
    }

    menuDdToggle?.addEventListener("click", function (e) {
        e.stopPropagation();
        if (menuDdWrap.classList.contains("open")) closeMenuDd();
        else openMenuDd();
    });

    menuDdSearch?.addEventListener("input", function () {
        renderMenuOptions(this.value);
    });

    menuDdList?.addEventListener("click", function (e) {
        const opt = e.target.closest(".sd-option");
        if (!opt) return;

        menuCodeInput.value = opt.dataset.code;

        menuDdLabel.textContent = opt.dataset.name + " (#" + opt.dataset.code + ")";
        menuDdLabel.classList.remove("placeholder");
        menuDdToggle.classList.add("has-value");

        closeMenuDd();

        /* Reset apartment side */
        apartmentsCache    = [];
        aptDdToggle.disabled = false;

        aptIdInput.value   = "";
        aptCodeInput.value = "";
        aptDdLabel.textContent = "— Select apartment —";
        aptDdLabel.classList.add("placeholder");
        aptDdToggle.classList.remove("has-value");

        /* If IH is active, immediately load */
        if (activeType === "individual_house") {
            aptIdInput.value   = IH_ID;
            aptCodeInput.value = "INDIVIDUAL";
            loadOrders();
        } else {
            resetResults();
        }
    });

    /* =========================================
       APARTMENT DROPDOWN
       ========================================= */
    function renderAptOptions(query) {
        if (!aptDdList) return;

        if (!apartmentsCache.length) {
            aptDdList.innerHTML = `<div class="sd-empty">No apartments with orders in this menu.</div>`;
            return;
        }

        const q = (query || "").trim().toLowerCase();
        const list = q
            ? apartmentsCache.filter(a =>
                (a.apartment_name || "").toLowerCase().includes(q) ||
                (a.apartment_code || "").toLowerCase().includes(q))
            : apartmentsCache;

        if (!list.length) {
            aptDdList.innerHTML = `<div class="sd-empty">No matches.</div>`;
            return;
        }

        aptDdList.innerHTML = list.slice(0, 80).map(a => {
            const selected = String(aptIdInput.value) === String(a.id);
            const count    = a.order_count || 0;
            return `
                <div class="sd-option ${selected ? 'selected' : ''}"
                     data-id="${a.id}"
                     data-code="${esc(a.apartment_code)}"
                     data-name="${esc(a.apartment_name)}">
                    <i class="bi bi-building"></i>
                    <div class="name">${esc(a.apartment_name)}</div>
                    <div class="meta">${count} order${count === 1 ? '' : 's'}</div>
                </div>
            `;
        }).join("");
    }

    function openAptDd() {
        if (aptDdToggle.disabled) return;

        aptDdWrap.classList.add("open");
        closeMenuDd();

        if (aptDdSearch) {
            aptDdSearch.value = "";
            setTimeout(() => aptDdSearch.focus(), 60);
        }

        if (!apartmentsCache.length) {
            aptDdList.innerHTML = `<div class="sd-empty">Loading apartments…</div>`;

            ajaxCall("apartments_with_orders", {
                menu_code: menuCodeInput.value
            })
                .then(res => {
                    if (!res || !res.success || !Array.isArray(res.data)) {
                        aptDdList.innerHTML = `<div class="sd-empty">${esc((res && res.message) || 'Failed to load.')}</div>`;
                        return;
                    }
                    /* Keep only real apartments — IH is a toggle now */
                    apartmentsCache = res.data.filter(a => !a.is_individual && Number(a.id) > 0);
                    renderAptOptions("");
                })
                .catch(() => {
                    aptDdList.innerHTML = `<div class="sd-empty">Unable to connect.</div>`;
                });
        } else {
            renderAptOptions("");
        }
    }

    function closeAptDd() {
        aptDdWrap.classList.remove("open");
    }

    aptDdToggle?.addEventListener("click", function (e) {
        e.stopPropagation();
        if (aptDdToggle.disabled) return;
        if (aptDdWrap.classList.contains("open")) closeAptDd();
        else openAptDd();
    });

    aptDdSearch?.addEventListener("input", function () {
        renderAptOptions(this.value);
    });

    aptDdList?.addEventListener("click", function (e) {
        const opt = e.target.closest(".sd-option");
        if (!opt) return;

        aptIdInput.value   = opt.dataset.id;
        aptCodeInput.value = opt.dataset.code;

        aptDdLabel.textContent = opt.dataset.name;
        aptDdLabel.classList.remove("placeholder");
        aptDdToggle.classList.add("has-value");

        closeAptDd();
        loadOrders();
    });

    document.addEventListener("click", function (e) {
        if (!e.target.closest("#aoMenuDdWrap")) closeMenuDd();
        if (!e.target.closest("#aoAptDdWrap"))  closeAptDd();
    });

    /* =========================================
       AJAX
       ========================================= */
    function ajaxCall(action, params) {
        const p = new URLSearchParams();
        p.set("action", action);
        if (params) {
            Object.keys(params).forEach(k => {
                if (params[k] !== undefined && params[k] !== null) {
                    p.set(k, params[k]);
                }
            });
        }

        return fetch(BASE_URL + "ajax/menu-apartment-orders.php?" + p.toString(), {
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null));
    }

    /* =========================================
       DATE FILTER
       ========================================= */
    dateFilter?.addEventListener("change", function () {
        if (this.value === "custom") {
            customWrap.style.display = "grid";
        } else {
            customWrap.style.display = "none";
            if (menuCodeInput.value && aptIdInput.value) loadOrders();
        }
    });

    dateFrom?.addEventListener("change", function () {
        if (dateFilter.value === "custom" && dateTo.value) {
            if (menuCodeInput.value && aptIdInput.value) loadOrders();
        }
    });

    dateTo?.addEventListener("change", function () {
        if (dateFilter.value === "custom" && dateFrom.value) {
            if (menuCodeInput.value && aptIdInput.value) loadOrders();
        }
    });

    /* =========================================
       RESET
       ========================================= */
    function resetResults() {
        kpisWrap.style.display = "none";
        resultsWrap.innerHTML = `
            <div class="ao-empty">
                <i class="bi bi-funnel"></i>
                <h3>Pick a menu and address type</h3>
                <p>Orders will appear here grouped by product.</p>
            </div>`;
    }

    /* =========================================
       LOAD ORDERS
       ========================================= */
    function loadOrders() {
        const aptId    = aptIdInput.value;
        const menuCode = menuCodeInput.value;

        if (!aptId || !menuCode) return;
        if (loading) return;

        loading = true;

        resultsWrap.innerHTML = `
            <div class="ao-card">
                <div style="text-align:center;padding:50px 20px;">
                    <span class="ao-spinner"></span>
                </div>
            </div>
        `;
        kpisWrap.style.display = "none";

        const params = {
            menu_code:    menuCode,
            apartment_id: aptId,
            date:         dateFilter.value
        };

        if (dateFilter.value === "custom") {
            if (dateFrom.value) params.from = dateFrom.value;
            if (dateTo.value)   params.to   = dateTo.value;
        }

        ajaxCall("summary", params)
            .then(res => {
                loading = false;

                if (!res || !res.success) {
                    resultsWrap.innerHTML = `
                        <div class="ao-empty">
                            <i class="bi bi-exclamation-triangle"></i>
                            <h3>Failed to load</h3>
                            <p>${esc((res && res.message) || "Please try again.")}</p>
                        </div>`;
                    return;
                }

                renderSummary(res.data);
            })
            .catch(() => {
                loading = false;
                resultsWrap.innerHTML = `
                    <div class="ao-empty">
                        <i class="bi bi-wifi-off"></i>
                        <h3>Unable to connect</h3>
                        <p>Please check your network.</p>
                    </div>`;
            });
    }

    /* =========================================
       RENDER SUMMARY
       ========================================= */
    function renderSummary(data) {
        const products = Array.isArray(data.products) ? data.products : [];
        const orders   = Array.isArray(data.orders)   ? data.orders   : [];

        const aptName  = data.apartment_name || "Apartment";
        const aptCode  = data.apartment_code || "";
        const menuName = data.menu_name || "";
        const menuCode = data.menu_code || "";
        const isIH     = data.is_individual === true;

        if (data.kpis) {
            kpiOrders.textContent   = data.kpis.order_count   || 0;
            kpiProducts.textContent = data.kpis.product_count || 0;
            kpiQty.textContent      = data.kpis.total_qty     || 0;
            kpiAmount.textContent   = money(data.kpis.total_amount || 0);
            kpisWrap.style.display  = "grid";
        } else {
            kpisWrap.style.display = "none";
        }

        if (!products.length) {
            resultsWrap.innerHTML = `
                <div class="ao-empty">
                    <i class="bi bi-inbox"></i>
                    <h3>No orders found</h3>
                    <p>No ${isIH ? '<strong>Individual House</strong>' : '<strong>' + esc(aptName) + '</strong>'} orders in menu <strong>#${esc(menuCode)}</strong> during this date range.</p>
                </div>`;
            return;
        }

        /* ---- Products table ---- */
        const productsHtml = products.map(p => {
            const thumb = p.image
                ? `<img src="${esc(p.image)}" alt="" onerror="this.style.display='none';this.parentElement.innerHTML='📦';">`
                : '📦';

            const variantChips = (Array.isArray(p.variants) && p.variants.length)
                ? p.variants.map(v => `
                        <span class="ao-var-chip">
                            ${esc(v.name)}
                            <strong>${v.qty}</strong>
                        </span>
                    `).join('')
                : `<span style="color:#b5aca2;font-size:11px;">—</span>`;

            return `
                <tr>
                    <td style="width:70px;">
                        <div class="ao-prod-row-thumb">${thumb}</div>
                    </td>
                    <td>
                        <div class="ao-prod-cell-info">
                            <p class="ao-prod-cell-name">${esc(p.name)}</p>
                            <span class="ao-prod-cell-code">${p.code ? '#' + esc(p.code) : ''}</span>
                        </div>
                    </td>
                    <td>
                        <div class="ao-var-chips">${variantChips}</div>
                    </td>
                    <td style="width:100px;">
                        <div class="ao-qty-big">${p.qty}</div>
                        <div class="ao-qty-lbl">qty</div>
                    </td>
                </tr>
            `;
        }).join("");

        /* ---- Orders table ---- */
        const ordersHtml = orders.map(o => {
            const isOrderIH = (o.address_type || '') === 'individual_house';
            const addrCell  = isOrderIH
                ? `<div class="ao-order-name">
                        <span style="background:#eef7f0;color:#2e7d32;padding:3px 8px;border-radius:999px;font-size:10px;font-weight:800;display:inline-flex;align-items:center;gap:4px;">
                            <i class="bi bi-house-door"></i> Individual House
                        </span>
                   </div>
                   <div class="ao-order-time">${esc(o.division || '—')}</div>`
                : `<div class="ao-order-name">Div ${esc(o.division || '—')}</div>
                   <div class="ao-order-time">${esc(o.items_summary || '—')}</div>`;

            return `
                <tr>
                    <td>
                        <div class="ao-order-code">#${esc(o.order_code)}</div>
                        <div class="ao-order-time">${esc(fmtDate(o.created_at))}</div>
                    </td>
                    <td>
                        <div class="ao-order-name">${esc(o.customer_name)}</div>
                        <div class="ao-order-time">${esc(o.customer_mobile)}</div>
                    </td>
                    <td>${addrCell}</td>
                    <td>
                        <span class="ao-status-badge ${statusClass(o.status)}">${esc(o.status)}</span>
                    </td>
                    <td>
                        <span class="ao-pay-badge ${payClass(o.payment_status)}">${esc(o.payment_status)}</span>
                    </td>
                    <td style="text-align:right;">
                        <div class="ao-order-amt">${money(o.total_amount)}</div>
                    </td>
                </tr>
            `;
        }).join("");

        const titleIcon = isIH ? 'bi-house-door' : 'bi-box-seam';
        const titleCode = (!isIH && aptCode) ? '· #' + esc(aptCode) : '';

        resultsWrap.innerHTML = `
            <div class="ao-card">
                <div class="ao-card-head">
                    <h2 class="ao-card-title">
                        <i class="bi ${titleIcon}"></i>
                        ${esc(aptName)} ${titleCode}
                        ${menuName ? ' · ' + esc(menuName) : ''}
                    </h2>
                    <span class="ao-badge">
                        <i class="bi bi-bag"></i>
                        ${orders.length} order${orders.length === 1 ? '' : 's'}
                    </span>
                </div>

                <h3 style="margin:0 0 12px;font-size:14px;font-weight:800;color:#4e4841;">
                    <i class="bi bi-grid-3x3-gap-fill" style="color:#b51f2c;"></i>
                    Products Ordered in This Menu
                </h3>

                <div style="overflow-x:auto;border-radius:14px;border:1px solid #f0ebe4;">
                    <table class="ao-prod-table">
                        <thead>
                            <tr>
                                <th style="width:70px;">Image</th>
                                <th>Product</th>
                                <th>Variants (with qty)</th>
                                <th style="text-align:right;width:100px;">Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${productsHtml}
                        </tbody>
                    </table>
                </div>

                <div class="ao-orders-head">
                    <h4>
                        <i class="bi bi-receipt" style="color:#b51f2c;"></i>
                        All Orders
                    </h4>
                    <span class="ao-badge">
                        <i class="bi bi-list-ul"></i> ${orders.length}
                    </span>
                </div>

                <div style="overflow-x:auto;border-radius:14px;border:1px solid #f0ebe4;">
                    <table class="ao-table">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Address / Items</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th style="text-align:right;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${ordersHtml}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    /* =========================================
       INIT
       ========================================= */
    (function init() {
        menuDdList.innerHTML = `<div class="sd-empty">Loading menus…</div>`;

        ajaxCall("menus")
            .then(res => {
                if (!res || !res.success || !Array.isArray(res.data)) {
                    menuDdList.innerHTML = `<div class="sd-empty">Failed to load menus.</div>`;
                    return;
                }
                menusCache = res.data;
                renderMenuOptions("");
            })
            .catch(() => {
                menuDdList.innerHTML = `<div class="sd-empty">Unable to connect.</div>`;
            });
    })();

})();