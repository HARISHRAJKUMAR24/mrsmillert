/* =========================================================
   MRS MILL@ — REASSIGN ORDERS (Boy → Boy)
   File: ./js/urgency-assign-orders.js
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    const MENUS = Array.isArray(window.MENUS) ? window.MENUS : [];
    const BOYS = Array.isArray(window.DELIVERY_BOYS) ? window.DELIVERY_BOYS : [];

    /* ---------- DOM: Menu ---------- */
    const menuWrap = document.getElementById("menuDdWrap");
    const menuToggle = document.getElementById("menuDdToggle");
    const menuLabel = document.getElementById("menuDdLabel");
    const menuSearch = document.getElementById("menuDdSearch");
    const menuList = document.getElementById("menuDdList");
    const menuInput = document.getElementById("asMenu");

    /* ---------- DOM: From Boy ---------- */
    const fromBoyWrap = document.getElementById("fromBoyDdWrap");
    const fromBoyToggle = document.getElementById("fromBoyDdToggle");
    const fromBoyLabel = document.getElementById("fromBoyDdLabel");
    const fromBoySearch = document.getElementById("fromBoyDdSearch");
    const fromBoyList = document.getElementById("fromBoyDdList");
    const fromBoyInput = document.getElementById("asFromBoy");

    /* ---------- DOM: To Boy ---------- */
    const boyWrap = document.getElementById("boyDdWrap");
    const boyToggle = document.getElementById("boyDdToggle");
    const boyLabel = document.getElementById("boyDdLabel");
    const boySearch = document.getElementById("boyDdSearch");
    const boyList = document.getElementById("boyDdList");
    const boyInput = document.getElementById("asBoy");

    /* ---------- DOM: Other ---------- */
    const aptList = document.getElementById("asAptList");
    const aptCountEl = document.getElementById("asAptCount");
    const selectAllBtn = document.getElementById("asSelectAll");
    const clearAllBtn = document.getElementById("asClearAll");

    const previewHead = document.getElementById("asPreviewHead");
    const previewSub = document.getElementById("asPreviewSub");
    const countBadge = document.getElementById("asCountBadge");
    const previewWrap = document.getElementById("asPreviewWrap");

    const resetBtn = document.getElementById("asReset");
    const assignBtn = document.getElementById("asAssign");
    const assignText = document.getElementById("asAssignText");

    const toastWrap = document.getElementById("asToastWrap");

    /* ---------- STATE ---------- */
    let currentOrders = [];
    let selectedMenuId = 0;
    let selectedMenuCode = "";
    let selectedApts = new Set();
    let apartmentsCache = [];
    let isAssigning = false;

    /* ---------- Helpers ---------- */
    function esc(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function money(n) {
        return "₹" + Math.round(Number(n) || 0);
    }

    function formatDate(s) {
        if (!s) return "—";
        try {
            return new Date(s.replace(" ", "T")).toLocaleString("en-IN", {
                dateStyle: "medium",
                timeStyle: "short"
            });
        } catch (e) { return s; }
    }

    /* =====================================================
       ADMIN PANEL TOAST
       ===================================================== */
    function showToast(type, message, timeout) {
        if (!toastWrap) return;

        const icons = {
            success: "bi-check-lg",
            error: "bi-x-lg",
            info: "bi-info-lg"
        };

        const el = document.createElement("div");
        el.className = "as-toast " + type;
        el.innerHTML = `
            <div class="as-toast-icon"><i class="bi ${icons[type] || icons.info}"></i></div>
            <div class="as-toast-body">${esc(message)}</div>
            <button type="button" class="as-toast-close" aria-label="Close">
                <i class="bi bi-x"></i>
            </button>
        `;

        toastWrap.appendChild(el);
        requestAnimationFrame(() => el.classList.add("show"));

        const close = () => {
            el.classList.remove("show");
            setTimeout(() => el.remove(), 300);
        };

        el.querySelector(".as-toast-close").addEventListener("click", close);
        setTimeout(close, timeout || 3200);
    }

    /* =====================================================
       ASSIGN BUTTON LOADING
       ===================================================== */
    function setAssignLoading(isLoading) {
        isAssigning = !!isLoading;
        if (!assignBtn) return;

        assignBtn.disabled = isLoading;

        if (isLoading) {
            assignText.innerHTML = '<span class="btn-spinner"></span> Assigning…';
        } else {
            updateAssignButton();
        }
    }

    function updateAptCount() {
        if (!aptCountEl) return;
        aptCountEl.textContent = selectedApts.size + " selected";
    }

    function resetPreview() {
        currentOrders = [];
        if (previewHead) previewHead.style.display = "none";
        if (assignBtn) assignBtn.disabled = true;
        previewWrap.innerHTML = `
            <div class="as-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <h3>Pick a menu, a from-boy and apartments</h3>
                <p>This boy's pending orders will show up here for review before moving them.</p>
            </div>`;
    }

    /* =====================================================
       MENU DROPDOWN
       ===================================================== */
    function renderMenuList(query) {
        if (!menuList) return;

        if (MENUS.length === 0) {
            menuList.innerHTML = `<div class="sd-empty">No menus available.</div>`;
            return;
        }

        const q = (query || "").trim().toLowerCase();
        const list = q
            ? MENUS.filter(m =>
                (m.menu_name || "").toLowerCase().includes(q) ||
                (m.menu_code || "").toLowerCase().includes(q))
            : MENUS;

        if (list.length === 0) {
            menuList.innerHTML = `<div class="sd-empty">No matches.</div>`;
            return;
        }

        menuList.innerHTML = list.map(m => {
            const selected = Number(m.id) === Number(selectedMenuId);
            return `
                <div class="sd-option ${selected ? 'selected' : ''}" data-id="${m.id}">
                    <i class="bi bi-list-ul"></i>
                    <div class="name">${esc(m.menu_name)}</div>
                    <div class="meta">#${esc(m.menu_code)}</div>
                </div>
            `;
        }).join("");
    }

    function openMenuDd() {
        menuWrap.classList.add("open");
        closeFromBoyDd();
        closeBoyDd();
        if (menuSearch) {
            menuSearch.value = "";
            setTimeout(() => menuSearch.focus(), 60);
        }
        renderMenuList("");
    }

    function closeMenuDd() {
        menuWrap.classList.remove("open");
    }

    menuToggle?.addEventListener("click", function (e) {
        e.stopPropagation();
        if (menuWrap.classList.contains("open")) closeMenuDd();
        else openMenuDd();
    });

    menuSearch?.addEventListener("input", function () {
        renderMenuList(this.value);
    });

    menuList?.addEventListener("click", function (e) {
        const opt = e.target.closest(".sd-option");
        if (!opt) return;

        const id = Number(opt.dataset.id);
        const menu = MENUS.find(m => Number(m.id) === id);
        if (!menu) return;

        selectedMenuId = id;
        selectedMenuCode = menu.menu_code || "";

        menuInput.value = String(id);
        menuLabel.textContent = menu.menu_name + " (#" + (menu.menu_code || "") + ")";
        menuLabel.classList.remove("placeholder");
        menuToggle.classList.add("has-value");

        closeMenuDd();
        maybeLoadApartments();
    });

    /* =====================================================
       FROM BOY DROPDOWN
       ===================================================== */
    function renderFromBoyList(query) {
        if (!fromBoyList) return;

        if (BOYS.length === 0) {
            fromBoyList.innerHTML = `<div class="sd-empty">No delivery boys available.</div>`;
            return;
        }

        const q = (query || "").trim().toLowerCase();
        const list = q
            ? BOYS.filter(b =>
                (b.full_name || "").toLowerCase().includes(q) ||
                (b.delivery_code || "").toLowerCase().includes(q) ||
                (b.mobile_number || "").toLowerCase().includes(q))
            : BOYS;

        if (list.length === 0) {
            fromBoyList.innerHTML = `<div class="sd-empty">No matches.</div>`;
            return;
        }

        const currentVal = String(fromBoyInput.value || "");

        fromBoyList.innerHTML = list.map(b => {
            const selected = String(b.id) === currentVal;
            return `
                <div class="sd-option ${selected ? 'selected' : ''}" data-id="${b.id}">
                    <i class="bi bi-person-dash"></i>
                    <div class="name">${esc(b.full_name)}</div>
                    <div class="meta">#${esc(b.delivery_code || '')} · ${esc(b.mobile_number || '')}</div>
                </div>
            `;
        }).join("");
    }

    function openFromBoyDd() {
        fromBoyWrap.classList.add("open");
        closeMenuDd();
        closeBoyDd();
        if (fromBoySearch) {
            fromBoySearch.value = "";
            setTimeout(() => fromBoySearch.focus(), 60);
        }
        renderFromBoyList("");
    }

    function closeFromBoyDd() {
        if (fromBoyWrap) fromBoyWrap.classList.remove("open");
    }

    fromBoyToggle?.addEventListener("click", function (e) {
        e.stopPropagation();
        if (fromBoyWrap.classList.contains("open")) closeFromBoyDd();
        else openFromBoyDd();
    });

    fromBoySearch?.addEventListener("input", function () {
        renderFromBoyList(this.value);
    });

    fromBoyList?.addEventListener("click", function (e) {
        const opt = e.target.closest(".sd-option");
        if (!opt) return;

        const id = Number(opt.dataset.id);
        const boy = BOYS.find(b => Number(b.id) === id);
        if (!boy) return;

        fromBoyInput.value = String(id);
        fromBoyLabel.textContent = boy.full_name + " (#" + (boy.delivery_code || "") + ")";
        fromBoyLabel.classList.remove("placeholder");
        fromBoyToggle.classList.add("has-value");

        closeFromBoyDd();

        selectedApts.clear();
        apartmentsCache = [];
        updateAptCount();
        resetPreview();

        aptList.innerHTML = `
            <div class="as-empty" style="padding:30px 20px;border:0;background:transparent;">
                <h3 style="font-size:14px;">Loading apartments…</h3>
                <p style="font-size:11.5px;">Please wait.</p>
            </div>`;

        maybeLoadApartments();
    });

    /* =====================================================
       TO BOY DROPDOWN
       ===================================================== */
    function renderBoyList(query) {
        if (!boyList) return;

        if (BOYS.length === 0) {
            boyList.innerHTML = `<div class="sd-empty">No delivery boys available.</div>`;
            return;
        }

        const q = (query || "").trim().toLowerCase();
        const list = q
            ? BOYS.filter(b =>
                (b.full_name || "").toLowerCase().includes(q) ||
                (b.delivery_code || "").toLowerCase().includes(q) ||
                (b.mobile_number || "").toLowerCase().includes(q))
            : BOYS;

        if (list.length === 0) {
            boyList.innerHTML = `<div class="sd-empty">No matches.</div>`;
            return;
        }

        const fromId = Number(fromBoyInput.value || 0);

        boyList.innerHTML = list.map(b => {
            const selected = Number(b.id) === Number(boyInput.value || 0);
            const isSameAsFrom = fromId > 0 && Number(b.id) === fromId;
            return `
                <div class="sd-option ${selected ? 'selected' : ''}" data-id="${b.id}">
                    <i class="bi bi-person-badge"></i>
                    <div class="name">${esc(b.full_name)}</div>
                    <div class="meta">
                        ${isSameAsFrom
                    ? '<span style="color:#b51f2c;">same as from</span>'
                    : '#' + esc(b.delivery_code || '') + ' · ' + esc(b.mobile_number || '')}
                    </div>
                </div>
            `;
        }).join("");
    }

    function openBoyDd() {
        boyWrap.classList.add("open");
        closeMenuDd();
        closeFromBoyDd();
        if (boySearch) {
            boySearch.value = "";
            setTimeout(() => boySearch.focus(), 60);
        }
        renderBoyList("");
    }

    function closeBoyDd() {
        boyWrap.classList.remove("open");
    }

    boyToggle?.addEventListener("click", function (e) {
        e.stopPropagation();
        if (boyWrap.classList.contains("open")) closeBoyDd();
        else openBoyDd();
    });

    boySearch?.addEventListener("input", function () {
        renderBoyList(this.value);
    });

    boyList?.addEventListener("click", function (e) {
        const opt = e.target.closest(".sd-option");
        if (!opt) return;

        const id = Number(opt.dataset.id);
        const boy = BOYS.find(b => Number(b.id) === id);
        if (!boy) return;

        boyInput.value = String(id);
        boyLabel.textContent = boy.full_name + " (#" + (boy.delivery_code || "") + ")";
        boyLabel.classList.remove("placeholder");
        boyToggle.classList.add("has-value");

        closeBoyDd();
        updateAssignButton();
    });

    document.addEventListener("click", function (e) {
        if (!e.target.closest("#menuDdWrap")) closeMenuDd();
        if (!e.target.closest("#fromBoyDdWrap")) closeFromBoyDd();
        if (!e.target.closest("#boyDdWrap")) closeBoyDd();
    });

    renderMenuList("");
    renderFromBoyList("");
    renderBoyList("");

    /* =====================================================
       LOAD APARTMENTS
       ===================================================== */
    function maybeLoadApartments() {
        if (!selectedMenuId) return;
        const fromBoyId = Number(fromBoyInput.value || 0);
        if (fromBoyId <= 0) return;

        loadApartmentsForMenu();
    }

    function loadApartmentsForMenu() {
        selectedApts.clear();
        apartmentsCache = [];
        updateAptCount();
        resetPreview();

        if (!selectedMenuId) return;

        const fromBoyId = Number(fromBoyInput.value || 0);
        if (fromBoyId <= 0) return;

        aptList.innerHTML = `
            <div style="text-align:center;padding:30px 20px;color:#948c82;font-size:12px;">
                <span class="btn-spinner" style="border-color:#ece5da;border-top-color:#b51f2c;"></span>
                Loading apartments…
            </div>`;

        const params = new URLSearchParams();
        params.set("menu_id", selectedMenuId);
        params.set("from_boy_id", fromBoyId);

        fetch(BASE_URL + "ajax/get-menu-apartments.php?" + params.toString(), {
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !Array.isArray(res.data)) {
                    aptList.innerHTML = `
                        <div class="as-empty" style="padding:30px 20px;border:0;background:transparent;">
                            <h3 style="font-size:14px;color:#b51f2c;">Failed to load apartments</h3>
                            <p style="font-size:11.5px;">${esc((res && res.message) || "Please try again.")}</p>
                        </div>`;
                    return;
                }

                apartmentsCache = res.data;
                renderApartmentList();
            })
            .catch(() => {
                aptList.innerHTML = `
                    <div class="as-empty" style="padding:30px 20px;border:0;background:transparent;">
                        <h3 style="font-size:14px;color:#b51f2c;">Unable to connect</h3>
                        <p style="font-size:11.5px;">Please check your network.</p>
                    </div>`;
            });
    }

    function renderApartmentList() {
        if (!aptList) return;

        if (apartmentsCache.length === 0) {
            aptList.innerHTML = `
                <div class="as-empty" style="padding:30px 20px;border:0;background:transparent;">
                    <h3 style="font-size:14px;">No pending orders</h3>
                    <p style="font-size:11.5px;">This delivery boy has no pending orders in this menu.</p>
                </div>`;
            return;
        }

        aptList.innerHTML = apartmentsCache.map(a => {
            const checked = selectedApts.has(a.apartment_code);
            return `
                <div class="as-apt-item ${checked ? 'is-checked' : ''}"
                     data-code="${esc(a.apartment_code)}"
                     data-name="${esc(a.apartment_name)}">
                    <div class="as-apt-check"></div>
                    <div class="as-apt-info">
                        <p class="as-apt-name">${esc(a.apartment_name)}</p>
                        <p class="as-apt-meta">#${esc(a.apartment_code)}</p>
                    </div>
                    <span class="as-apt-pill">
                        <i class="bi bi-hourglass-split"></i>
                        ${a.pending_count} pending
                    </span>
                </div>
            `;
        }).join("");

        updateAptCount();
    }

    aptList?.addEventListener("click", function (e) {
        const item = e.target.closest(".as-apt-item");
        if (!item) return;

        const code = item.dataset.code;
        if (!code) return;

        if (selectedApts.has(code)) {
            selectedApts.delete(code);
            item.classList.remove("is-checked");
        } else {
            selectedApts.add(code);
            item.classList.add("is-checked");
        }

        updateAptCount();
        loadOrdersForSelectedApts();
    });

    selectAllBtn?.addEventListener("click", () => {
        apartmentsCache.forEach(a => selectedApts.add(a.apartment_code));
        aptList.querySelectorAll(".as-apt-item").forEach(el => el.classList.add("is-checked"));
        updateAptCount();
        loadOrdersForSelectedApts();
    });

    clearAllBtn?.addEventListener("click", () => {
        selectedApts.clear();
        aptList.querySelectorAll(".as-apt-item").forEach(el => el.classList.remove("is-checked"));
        updateAptCount();
        resetPreview();
    });

    /* =====================================================
       LOAD ORDERS
       ===================================================== */
    function loadOrdersForSelectedApts() {
        if (selectedApts.size === 0 || !selectedMenuId) {
            resetPreview();
            return;
        }

        const fromBoyId = Number(fromBoyInput.value || 0);
        if (fromBoyId <= 0) {
            resetPreview();
            return;
        }

        previewHead.style.display = "flex";
        previewSub.textContent = `Loading ${selectedApts.size} apartment(s)…`;
        countBadge.textContent = "…";
        previewWrap.innerHTML = `
            <div class="as-empty">
                <span class="btn-spinner" style="border-color:#ece5da;border-top-color:#b51f2c;width:24px;height:24px;border-width:3px;"></span>
                <h3>Loading orders…</h3>
            </div>`;
        assignBtn.disabled = true;

        const params = new URLSearchParams();
        params.set("menu_id", selectedMenuId);
        params.set("apartment_codes", JSON.stringify([...selectedApts]));
        params.set("from_boy_id", String(fromBoyId));

        fetch(BASE_URL + "ajax/get-orders-for-menu-apartment.php?" + params.toString(), {
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !Array.isArray(res.data)) {
                    previewWrap.innerHTML = `
                        <div class="as-empty">
                            <h3>Failed to load orders</h3>
                            <p>${esc((res && res.message) || "Server error")}</p>
                        </div>`;
                    return;
                }

                currentOrders = res.data;
                renderPreview();
            })
            .catch(() => {
                previewWrap.innerHTML = `
                    <div class="as-empty">
                        <h3>Unable to connect</h3>
                        <p>Please check your network.</p>
                    </div>`;
            });
    }

    function renderPreview() {
        const count = currentOrders.length;
        countBadge.textContent = count + (count === 1 ? " order" : " orders");

        const fromBoy = BOYS.find(b => String(b.id) === String(fromBoyInput.value));
        const fromName = fromBoy ? fromBoy.full_name : "this boy";

        previewSub.textContent = `${selectedApts.size} apartment${selectedApts.size === 1 ? "" : "s"} · ${count} pending order${count === 1 ? "" : "s"} from ${fromName}`;

        if (count === 0) {
            previewWrap.innerHTML = `
                <div class="as-empty">
                    <h3>No pending orders</h3>
                    <p>${esc(fromName)} has no pending orders in the selected apartments.</p>
                </div>`;
            assignBtn.disabled = true;
            return;
        }

        previewWrap.innerHTML = `
            <div class="as-table-wrap">
                <table class="as-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Apartment</th>
                            <th>Current Boy</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${currentOrders.map(o => {
            return `
                                <tr>
                                    <td>
                                        <span class="as-code">#${esc(o.order_code)}</span>
                                        <span class="as-time">${esc(formatDate(o.created_at))}</span>
                                    </td>
                                    <td>
                                        <span class="as-name">${esc(o.customer_name)}</span>
                                        <span class="as-mobile">${esc(o.customer_mobile)}</span>
                                    </td>
                                    <td>
                                        <span class="as-name" style="font-size:12px;">${esc(o.apartment_name || "—")}</span>
                                        <span class="as-mobile">Div ${esc(o.division || "—")}</span>
                                    </td>
                                    <td>
                                        <span class="as-boy has">
                                            <i class="bi bi-person-fill"></i> ${esc(o.boy_name || "Assigned")}
                                        </span>
                                    </td>
                                    <td><span class="as-amount">${money(o.total_amount)}</span></td>
                                </tr>`;
        }).join("")}
                    </tbody>
                </table>
            </div>`;

        updateAssignButton();
    }

    function updateAssignButton() {
        if (isAssigning) return;

        const fromId = Number(fromBoyInput.value || 0);
        const toId = Number(boyInput.value || 0);
        const hasOrder = currentOrders.length > 0;

        if (fromId > 0 && toId > 0 && hasOrder) {
            if (fromId === toId) {
                assignBtn.disabled = true;
                assignText.textContent = "From and To boy are same";
                return;
            }
            assignBtn.disabled = false;
            const boy = BOYS.find(b => Number(b.id) === toId);
            const clean = boy ? boy.full_name : "boy";
            assignText.textContent = `Move ${currentOrders.length} order${currentOrders.length === 1 ? "" : "s"} to ${clean}`;
        } else {
            assignBtn.disabled = true;
            assignText.textContent = "Assign Orders";
        }
    }

    /* =====================================================
       RESET
       ===================================================== */
    resetBtn?.addEventListener("click", () => {
        selectedMenuId = 0;
        selectedMenuCode = "";
        menuInput.value = "";
        menuLabel.textContent = "— Select menu —";
        menuLabel.classList.add("placeholder");
        menuToggle.classList.remove("has-value");

        if (fromBoyInput) fromBoyInput.value = "";
        if (fromBoyLabel) {
            fromBoyLabel.textContent = "— Select current boy —";
            fromBoyLabel.classList.add("placeholder");
        }
        if (fromBoyToggle) fromBoyToggle.classList.remove("has-value");

        boyInput.value = "";
        boyLabel.textContent = "— Select new boy —";
        boyLabel.classList.add("placeholder");
        boyToggle.classList.remove("has-value");

        selectedApts.clear();
        apartmentsCache = [];
        updateAptCount();
        aptList.innerHTML = `
            <div class="as-empty" style="padding:30px 20px;border:0;background:transparent;">
                <h3 style="font-size:14px;">Select a menu and from-boy first</h3>
                <p style="font-size:11.5px;">Apartments with this boy's pending orders will appear here.</p>
            </div>`;

        resetPreview();
        renderMenuList("");
        renderFromBoyList("");
        renderBoyList("");
    });

    /* =====================================================
       ASSIGN / REASSIGN
       ===================================================== */
    assignBtn?.addEventListener("click", () => {
        if (isAssigning) return;

        const fromId = Number(fromBoyInput.value || 0);
        const toId = Number(boyInput.value || 0);

        if (fromId <= 0) {
            showToast("error", "Please select a current delivery boy.");
            return;
        }
        if (toId <= 0) {
            showToast("error", "Please select a new delivery boy.");
            return;
        }
        if (fromId === toId) {
            showToast("error", "From and To boy cannot be the same.");
            return;
        }
        if (currentOrders.length === 0) {
            showToast("error", "No orders to assign.");
            return;
        }

        setAssignLoading(true);

        const fd = new FormData();
        fd.append("delivery_boy_id", toId);
        fd.append("from_boy_id", fromId);
        fd.append("menu_id", selectedMenuId);
        fd.append("apartment_codes", JSON.stringify([...selectedApts]));
        fd.append("order_ids", JSON.stringify(currentOrders.map(o => o.id)));

        fetch(BASE_URL + "ajax/reassign-orders.php", {
            method: "POST",
            body: fd,
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                /* ALWAYS reset loading — even on success */
                setAssignLoading(false);

                if (!res || !res.success) {
                    showToast("error", (res && res.message) || "Failed to reassign.");
                    return;
                }

                showToast("success", res.message || "Orders reassigned successfully.");

                /* Reload apartments so the moved orders disappear */
                loadApartmentsForMenu();
            })
            .catch(() => {
                setAssignLoading(false);
                showToast("error", "Unable to connect.");
            });
    });

    /* ---------- INIT ---------- */
    resetPreview();
    updateAptCount();

})();