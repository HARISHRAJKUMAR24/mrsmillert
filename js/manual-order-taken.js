/* =========================================================
   MRS MILL@ — MANUAL ORDER (admin panel)
   File: ./js/manual-order-taken.js
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    /* ---------- DOM ---------- */
    const mobileInput    = document.getElementById("moMobile");
    const nameInput      = document.getElementById("moName");
    const mobileHint     = document.getElementById("moMobileHint");

    const modeRadios     = document.querySelectorAll('input[name="moMode"]');
    const deliveryBlock  = document.getElementById("moDeliveryBlock");
    const pickupBlock    = document.getElementById("moPickupBlock");
    const branchSelect   = document.getElementById("moBranch");

    /* Apartment searchable dropdown */
    const aptDdWrap      = document.getElementById("moAptDdWrap");
    const aptDdToggle    = document.getElementById("moAptDdToggle");
    const aptDdLabel     = document.getElementById("moAptDdLabel");
    const aptDdSearch    = document.getElementById("moAptDdSearch");
    const aptDdList      = document.getElementById("moAptDdList");
    const aptIdInput     = document.getElementById("moApartmentId");
    const aptCodeInput   = document.getElementById("moApartmentCode");

    /* Division searchable dropdown */
    const divDdWrap      = document.getElementById("moDivDdWrap");
    const divDdToggle    = document.getElementById("moDivDdToggle");
    const divDdLabel     = document.getElementById("moDivDdLabel");
    const divDdSearch    = document.getElementById("moDivDdSearch");
    const divDdList      = document.getElementById("moDivDdList");
    const divInput       = document.getElementById("moDivision");
    const divChargeInput = document.getElementById("moDivisionCharge");
    const divHint        = document.getElementById("moDivisionHint");

    const tabButtons     = document.querySelectorAll(".mo-tab");
    const productSearch  = document.getElementById("moProductSearch");
    const productGrid    = document.getElementById("moProducts");

    const cartList       = document.getElementById("moCartList");
    const cartCount      = document.getElementById("moCartCount");
    const subtotalEl     = document.getElementById("moSubtotal");
    const chargeEl       = document.getElementById("moCharge");
    const chargeLabel    = document.getElementById("moChargeLabel");
    const totalEl        = document.getElementById("moTotal");
    const placeBtn       = document.getElementById("moPlaceBtn");
    const resetBtn       = document.getElementById("moResetBtn");

    /* Variant modal */
    const varOverlay     = document.getElementById("moVarOverlay");
    const varThumb       = document.getElementById("moVarThumb");
    const varName        = document.getElementById("moVarName");
    const varCode        = document.getElementById("moVarCode");
    const varList        = document.getElementById("moVarList");
    const varCancel      = document.getElementById("moVarCancel");
    const varAdd         = document.getElementById("moVarAdd");

    /* Popup */
    const popupOverlay   = document.getElementById("moPopupOverlay");
    const popupIcon      = document.getElementById("moPopupIcon");
    const popupTitle     = document.getElementById("moPopupTitle");
    const popupText      = document.getElementById("moPopupText");
    const popupCode      = document.getElementById("moPopupCode");
    const popupClose     = document.getElementById("moPopupClose");

    /* ---------- STATE ---------- */
    let apartmentsCache   = [];
    let divisionsCache    = [];
    let productsCache     = [];
    let currentTab        = "menu";
    let currentProduct    = null;
    let selectedVariant   = null;
    let cart              = [];
    let currentMode       = "delivery";

    /* ---------- Helpers ---------- */
    function money(n) { return "₹" + Math.round(Number(n) || 0); }

    function esc(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    /* =====================================================
       MOBILE LOOKUP
       ===================================================== */
    let lookupTimer = null;
    let lastMobile  = "";

    function lookupMobile(m) {
        if (!m || m.length < 10 || m === lastMobile) return;
        lastMobile = m;

        fetch(BASE_URL + "ajax/manual-lookup-customer.php?mobile=" + encodeURIComponent(m), {
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !res.data || !res.data.found) {
                    mobileHint.textContent = "New customer — enter name manually.";
                    mobileHint.classList.remove("success");
                    return;
                }

                if (res.data.name && !nameInput.value.trim()) {
                    nameInput.value = res.data.name;
                }

                if (res.data.apartment_id && res.data.apartment_id > 0) {
                    const dRadio = document.querySelector('input[name="moMode"][value="delivery"]');
                    if (dRadio) {
                        dRadio.checked = true;
                        setMode("delivery");
                    }

                    aptIdInput.value = res.data.apartment_id;
                    aptCodeInput.value = res.data.apartment_code || "";

                    /* Update apartment dropdown label */
                    if (aptDdLabel) {
                        aptDdLabel.textContent = res.data.apartment_name || "—";
                        aptDdLabel.classList.remove("placeholder");
                        aptDdToggle.classList.add("has-value");
                    }

                    /* Populate divisions and preselect */
                    const apt = apartmentsCache.find(a => Number(a.id) === Number(res.data.apartment_id));
                    if (apt) populateDivisions(apt, res.data.division || "");

                    mobileHint.textContent = "Welcome back! Details auto-filled.";
                    mobileHint.classList.add("success");
                } else {
                    mobileHint.textContent = "Welcome back, " + (res.data.name || "");
                    mobileHint.classList.add("success");
                }
            })
            .catch(() => {});
    }

    mobileInput?.addEventListener("input", function () {
        this.value = this.value.replace(/[^0-9]/g, "").slice(0, 15);
        clearTimeout(lookupTimer);
        lastMobile = "";
        const m = this.value.trim();
        if (m.length >= 10) {
            lookupTimer = setTimeout(() => lookupMobile(m), 350);
        }
    });

    mobileInput?.addEventListener("blur", function () {
        const m = this.value.trim();
        if (m.length >= 10) {
            clearTimeout(lookupTimer);
            lookupMobile(m);
        }
    });

    /* =====================================================
       MODE TOGGLE
       ===================================================== */
    function setMode(mode) {
        currentMode = mode;

        if (mode === "pickup") {
            deliveryBlock.style.display = "none";
            pickupBlock.style.display = "block";
            aptIdInput.value = "";
            aptCodeInput.value = "";
            divInput.value = "";
            divChargeInput.value = 0;
            divDdList.innerHTML = "";
            divisionsCache = [];
            divDdToggle.disabled = true;
            divDdLabel.textContent = "Select apartment first";
            divDdLabel.classList.add("placeholder");
            divDdToggle.classList.remove("has-value");
            divHint.textContent = "";
        } else {
            deliveryBlock.style.display = "block";
            pickupBlock.style.display = "none";
            branchSelect.value = "";
        }

        updateTotals();
    }

    modeRadios.forEach(r => {
        r.addEventListener("change", function () {
            if (this.checked) setMode(this.value);
        });
    });

    /* =====================================================
       APARTMENTS — LOAD
       ===================================================== */
    function loadApartments() {
        fetch(BASE_URL + "ajax/manual-get-apartments.php", { credentials: "same-origin" })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (res && res.success && Array.isArray(res.data)) {
                    apartmentsCache = res.data;
                }
            })
            .catch(() => {});
    }
    loadApartments();

    /* =====================================================
       APARTMENTS — SEARCHABLE DROPDOWN
       ===================================================== */
    function renderAptOptions(query) {
        if (!aptDdList) return;

        if (apartmentsCache.length === 0) {
            aptDdList.innerHTML = `<div class="sd-empty">Loading apartments…</div>`;
            return;
        }

        const q = (query || "").trim().toLowerCase();
        const list = q
            ? apartmentsCache.filter(a =>
                (a.apartment_name || "").toLowerCase().includes(q) ||
                (a.apartment_code || "").toLowerCase().includes(q) ||
                (a.apartment_address || "").toLowerCase().includes(q))
            : apartmentsCache;

        if (!list.length) {
            aptDdList.innerHTML = `<div class="sd-empty">No apartments found.</div>`;
            return;
        }

        aptDdList.innerHTML = list.slice(0, 50).map(a => {
            const selected = Number(aptIdInput.value) === Number(a.id);
            return `
                <div class="sd-option ${selected ? 'selected' : ''}"
                     data-id="${a.id}"
                     data-code="${esc(a.apartment_code)}"
                     data-name="${esc(a.apartment_name)}">
                    <i class="bi bi-building"></i>
                    <div class="name">${esc(a.apartment_name)}</div>
                    <div class="meta">#${esc(a.apartment_code || '')}</div>
                </div>
            `;
        }).join("");
    }

    function openAptDd() {
        aptDdWrap.classList.add("open");
        closeDivDd();
        if (aptDdSearch) {
            aptDdSearch.value = "";
            setTimeout(() => aptDdSearch.focus(), 60);
        }
        renderAptOptions("");
    }

    function closeAptDd() {
        aptDdWrap.classList.remove("open");
    }

    aptDdToggle?.addEventListener("click", function (e) {
        e.stopPropagation();
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

        const apt = apartmentsCache.find(a => Number(a.id) === Number(opt.dataset.id));
        if (apt) populateDivisions(apt, "");
    });

    /* =====================================================
       DIVISIONS — SEARCHABLE DROPDOWN
       ===================================================== */
    function populateDivisions(apt, preselect) {
        if (!apt || !Array.isArray(apt.divisions) || !apt.divisions.length) {
            divisionsCache = [];
            divDdToggle.disabled = true;
            divDdLabel.textContent = "No divisions available";
            divDdLabel.classList.add("placeholder");
            divDdToggle.classList.remove("has-value");
            divDdList.innerHTML = "";
            divInput.value = "";
            divChargeInput.value = 0;
            divHint.textContent = "";
            updateTotals();
            return;
        }

        divisionsCache = apt.divisions;
        divDdToggle.disabled = false;

        /* Auto-select if preselect provided */
        if (preselect) {
            const match = divisionsCache.find(d => String(d.division) === String(preselect));
            if (match) {
                selectDivision(match);
                return;
            }
        }

        /* Otherwise reset */
        divInput.value = "";
        divChargeInput.value = 0;
        divDdLabel.textContent = "— Select division —";
        divDdLabel.classList.add("placeholder");
        divDdToggle.classList.remove("has-value");
        divDdList.innerHTML = "";
        divHint.textContent = "";
        updateTotals();
    }

    function renderDivOptions(query) {
        if (!divDdList) return;

        if (!divisionsCache.length) {
            divDdList.innerHTML = `<div class="sd-empty">No divisions available.</div>`;
            return;
        }

        const q = (query || "").trim().toLowerCase();
        const list = q
            ? divisionsCache.filter(d =>
                String(d.division).toLowerCase().includes(q) ||
                ("division " + d.division).toLowerCase().includes(q))
            : divisionsCache;

        if (!list.length) {
            divDdList.innerHTML = `<div class="sd-empty">No divisions found.</div>`;
            return;
        }

        divDdList.innerHTML = list.map(d => {
            const selected = String(divInput.value) === String(d.division);
            return `
                <div class="sd-option ${selected ? 'selected' : ''}"
                     data-division="${esc(d.division)}"
                     data-charge="${Number(d.charge)}">
                    <i class="bi bi-grid-3x3-gap"></i>
                    <div class="name">Division ${esc(d.division)}</div>
                    <div class="meta">₹${Math.round(Number(d.charge))}</div>
                </div>
            `;
        }).join("");
    }

    function openDivDd() {
        if (divDdToggle.disabled) return;
        divDdWrap.classList.add("open");
        closeAptDd();
        if (divDdSearch) {
            divDdSearch.value = "";
            setTimeout(() => divDdSearch.focus(), 60);
        }
        renderDivOptions("");
    }

    function closeDivDd() {
        divDdWrap.classList.remove("open");
    }

    divDdToggle?.addEventListener("click", function (e) {
        e.stopPropagation();
        if (divDdToggle.disabled) return;
        if (divDdWrap.classList.contains("open")) closeDivDd();
        else openDivDd();
    });

    divDdSearch?.addEventListener("input", function () {
        renderDivOptions(this.value);
    });

    divDdList?.addEventListener("click", function (e) {
        const opt = e.target.closest(".sd-option");
        if (!opt) return;

        selectDivision({
            division: opt.dataset.division,
            charge:   Number(opt.dataset.charge || 0)
        });

        closeDivDd();
    });

    function selectDivision(d) {
        divInput.value = String(d.division);
        divChargeInput.value = Number(d.charge);
        divDdLabel.textContent = "Division " + d.division + " · ₹" + Math.round(Number(d.charge));
        divDdLabel.classList.remove("placeholder");
        divDdToggle.classList.add("has-value");
        divHint.textContent = "Charge: ₹" + Math.round(Number(d.charge));
        updateTotals();
    }

    /* ---------- Close on outside click ---------- */
    document.addEventListener("click", function (e) {
        if (!e.target.closest("#moAptDdWrap")) closeAptDd();
        if (!e.target.closest("#moDivDdWrap")) closeDivDd();
    });

    /* =====================================================
       PRODUCTS
       ===================================================== */
    function loadProducts(tab) {
        productGrid.innerHTML = `<div class="mo-prod-empty"><i class="bi bi-hourglass-split"></i>Loading products...</div>`;

        fetch(BASE_URL + "ajax/manual-get-products.php?tab=" + encodeURIComponent(tab), {
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !Array.isArray(res.data)) {
                    productGrid.innerHTML = `<div class="mo-prod-empty"><i class="bi bi-exclamation-circle"></i>Failed to load products.</div>`;
                    return;
                }
                productsCache = res.data;
                renderProducts(productsCache);
            })
            .catch(() => {
                productGrid.innerHTML = `<div class="mo-prod-empty"><i class="bi bi-wifi-off"></i>Unable to connect.</div>`;
            });
    }

    function renderProducts(list) {
        if (!list.length) {
            productGrid.innerHTML = `<div class="mo-prod-empty"><i class="bi bi-box"></i>No products found.</div>`;
            return;
        }

        productGrid.innerHTML = list.map(p => {
            const thumb = p.product_image
                ? `<img src="${esc(p.product_image)}" alt="" onerror="this.parentElement.innerHTML='<i class=\\'bi bi-image\\'></i>';">`
                : `<i class="bi bi-image"></i>`;

            return `
                <div class="mo-prod" data-id="${p.id}">
                    <div class="mo-prod-thumb">${thumb}</div>
                    <div class="mo-prod-info">
                        <p class="mo-prod-name">${esc(p.product_name)}</p>
                        <p class="mo-prod-meta">#${esc(p.product_code)}</p>
                    </div>
                    <div class="mo-prod-price">${money(p.min_price)}</div>
                </div>
            `;
        }).join("");
    }

    tabButtons.forEach(t => {
        t.addEventListener("click", function () {
            tabButtons.forEach(x => x.classList.remove("active"));
            this.classList.add("active");
            currentTab = this.dataset.tab;
            productSearch.value = "";
            loadProducts(currentTab);
        });
    });

    productSearch?.addEventListener("input", function () {
        const q = this.value.trim().toLowerCase();
        const filtered = q
            ? productsCache.filter(p =>
                (p.product_name || "").toLowerCase().includes(q) ||
                (p.product_code || "").toLowerCase().includes(q))
            : productsCache;
        renderProducts(filtered);
    });

    productGrid?.addEventListener("click", function (e) {
        const card = e.target.closest(".mo-prod");
        if (!card) return;
        const id = Number(card.dataset.id);
        const product = productsCache.find(p => Number(p.id) === id);
        if (!product) return;
        openVariantPicker(product);
    });

    /* =====================================================
       VARIANT PICKER
       ===================================================== */
    function openVariantPicker(product) {
        currentProduct = product;
        selectedVariant = null;

        varThumb.innerHTML = product.product_image
            ? `<img src="${esc(product.product_image)}" alt="">`
            : `<i class="bi bi-box"></i>`;
        varName.textContent = product.product_name;
        varCode.textContent = "#" + product.product_code;

        varList.innerHTML = `<div style="padding:20px;text-align:center;color:#948c82;font-size:12px;">Loading variants...</div>`;
        varOverlay.classList.add("show");

        fetch(BASE_URL + "ajax/manual-get-variants.php?id=" + encodeURIComponent(product.id), {
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !res.data) {
                    varList.innerHTML = `<div style="padding:20px;text-align:center;color:#b51f2c;font-size:12px;">Failed to load variants.</div>`;
                    return;
                }

                currentProduct = res.data;

                if (!Array.isArray(res.data.variants) || !res.data.variants.length) {
                    varList.innerHTML = `<div style="padding:20px;text-align:center;color:#948c82;font-size:12px;">No variants available.</div>`;
                    varAdd.disabled = true;
                    return;
                }

                varAdd.disabled = false;

                varList.innerHTML = res.data.variants.map(v => {
                    const qtyLabel = v.quantity_name || (v.quantity + " " + v.quantity_unit);
                    return `
                        <div class="mo-var" data-vid="${v.id}">
                            <div class="mo-var-radio"></div>
                            <div class="mo-var-info">
                                <div class="mo-var-name">${esc(qtyLabel)}</div>
                                <div class="mo-var-meta">${esc(v.quantity)} ${esc(v.quantity_unit)}</div>
                            </div>
                            <div class="mo-var-price">${money(v.price)}</div>
                        </div>
                    `;
                }).join("");
            })
            .catch(() => {
                varList.innerHTML = `<div style="padding:20px;text-align:center;color:#b51f2c;font-size:12px;">Unable to connect.</div>`;
            });
    }

    varList?.addEventListener("click", function (e) {
        const opt = e.target.closest(".mo-var");
        if (!opt) return;

        varList.querySelectorAll(".mo-var").forEach(o => o.classList.remove("selected"));
        opt.classList.add("selected");

        const vid = Number(opt.dataset.vid);
        selectedVariant = (currentProduct.variants || []).find(v => Number(v.id) === vid) || null;
    });

    varCancel?.addEventListener("click", () => {
        varOverlay.classList.remove("show");
        currentProduct = null;
        selectedVariant = null;
    });

    varOverlay?.addEventListener("click", (e) => {
        if (e.target === varOverlay) {
            varOverlay.classList.remove("show");
            currentProduct = null;
            selectedVariant = null;
        }
    });

    varAdd?.addEventListener("click", function () {
        if (!currentProduct || !selectedVariant) {
            alert("Please select a variant.");
            return;
        }

        const key = "p" + currentProduct.id + "_v" + selectedVariant.id;
        const existing = cart.find(c => c.key === key);

        if (existing) {
            existing.qty += 1;
        } else {
            cart.push({
                key:           key,
                product_id:    currentProduct.id,
                code:          currentProduct.product_code,
                name:          currentProduct.product_name,
                image:         currentProduct.image || "",
                variant_id:    selectedVariant.id,
                variant_name:  selectedVariant.quantity_name || "",
                variant_qty:   selectedVariant.quantity + " " + selectedVariant.quantity_unit,
                price:         Number(selectedVariant.price),
                qty:           1,
            });
        }

        varOverlay.classList.remove("show");
        currentProduct = null;
        selectedVariant = null;
        renderCart();
    });

    /* =====================================================
       CART
       ===================================================== */
    function renderCart() {
        const count = cart.reduce((s, c) => s + c.qty, 0);
        cartCount.textContent = count;

        if (!cart.length) {
            cartList.innerHTML = `
                <div class="mo-cart-empty">
                    <i class="bi bi-basket"></i>
                    No products added yet.
                </div>`;
            updateTotals();
            return;
        }

        cartList.innerHTML = cart.map(c => {
            const thumb = c.image
                ? `<img src="${esc(c.image)}" alt="" onerror="this.parentElement.innerHTML='<i class=\\'bi bi-image\\'></i>';">`
                : `<i class="bi bi-image"></i>`;

            return `
                <div class="mo-cart-item" data-key="${esc(c.key)}">
                    <div class="mo-cart-thumb">${thumb}</div>
                    <div class="mo-cart-info">
                        <p class="mo-cart-name">${esc(c.name)}</p>
                        <p class="mo-cart-meta">
                            ${esc(c.variant_name || "")} · <strong>${money(c.price)}</strong>
                        </p>
                    </div>
                    <div class="mo-cart-qty">
                        <button type="button" data-act="dec"><i class="bi bi-dash"></i></button>
                        <span>${c.qty}</span>
                        <button type="button" data-act="inc"><i class="bi bi-plus"></i></button>
                    </div>
                    <button type="button" class="mo-cart-remove" data-act="rm">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            `;
        }).join("");

        updateTotals();
    }

    cartList?.addEventListener("click", function (e) {
        const btn = e.target.closest("[data-act]");
        if (!btn) return;

        const item = btn.closest(".mo-cart-item");
        if (!item) return;

        const key = item.dataset.key;
        const c = cart.find(x => x.key === key);
        if (!c) return;

        const act = btn.dataset.act;
        if (act === "inc") c.qty += 1;
        if (act === "dec") c.qty = Math.max(1, c.qty - 1);
        if (act === "rm") cart = cart.filter(x => x.key !== key);

        renderCart();
    });

    /* =====================================================
       TOTALS
       ===================================================== */
    function updateTotals() {
        const subtotal = cart.reduce((s, c) => s + c.price * c.qty, 0);
        let charge = 0;

        if (currentMode === "delivery") {
            charge = Number(divChargeInput.value || 0);
            chargeLabel.textContent = "Delivery charge";
        } else {
            charge = 0;
            chargeLabel.textContent = "Pickup charge";
        }

        const total = subtotal + charge;

        subtotalEl.textContent = money(subtotal);
        chargeEl.textContent   = money(charge);
        totalEl.textContent    = money(total);

        placeBtn.disabled = !cart.length;
    }

    /* =====================================================
       PLACE ORDER
       ===================================================== */
    placeBtn?.addEventListener("click", function () {
        const name   = nameInput.value.trim();
        const mobile = mobileInput.value.trim();

        if (!/^[0-9]{10,15}$/.test(mobile)) { showPopup("error", "Invalid Mobile", "Please enter a valid 10-digit mobile."); return; }
        if (name.length < 2) { showPopup("error", "Name Required", "Please enter the customer name."); return; }
        if (!cart.length) { showPopup("error", "Cart Empty", "Please add at least one product."); return; }

        const mode = document.querySelector('input[name="moMode"]:checked')?.value || "delivery";

        if (mode === "delivery") {
            if (!aptIdInput.value) { showPopup("error", "Apartment Required", "Please select an apartment."); return; }
            if (!divInput.value) { showPopup("error", "Division Required", "Please select a division."); return; }
        } else {
            if (!branchSelect.value) { showPopup("error", "Branch Required", "Please select a pickup branch."); return; }
        }

        placeBtn.disabled = true;
        placeBtn.innerHTML = '<span class="btn-spinner" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:asSpin .7s linear infinite;display:inline-block;"></span> Placing...';

        const charge = mode === "delivery" ? Number(divChargeInput.value || 0) : 0;

        const fd = new FormData();
        fd.append("customer_name", name);
        fd.append("customer_mobile", mobile);
        fd.append("delivery_mode", mode);
        fd.append("apartment_id", aptIdInput.value || 0);
        fd.append("apartment_code", aptCodeInput.value || "");
        fd.append("division", divInput.value || "");
        fd.append("division_charge", charge);
        fd.append("pickup_branch_id", branchSelect.value || 0);
        fd.append("products", JSON.stringify(cart.map(c => ({
            product_id:   c.product_id,
            code:         c.code,
            name:         c.name,
            image:        c.image,
            variant_id:   c.variant_id,
            variant_name: c.variant_name,
            variant_qty:  c.variant_qty,
            price:        c.price,
            qty:          c.qty,
        }))));

        fetch(BASE_URL + "ajax/manual-place-order.php", {
            method: "POST",
            body: fd,
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                placeBtn.disabled = false;
                placeBtn.innerHTML = '<i class="bi bi-check-lg"></i> Place Order';

                if (!res || !res.success) {
                    showPopup("error", "Failed", (res && res.message) || "Failed to place order.");
                    return;
                }

                showPopup("success", "Order Placed!", "The order has been created successfully.", res.data.order_code);
                resetForm();
            })
            .catch(() => {
                placeBtn.disabled = false;
                placeBtn.innerHTML = '<i class="bi bi-check-lg"></i> Place Order';
                showPopup("error", "Connection Error", "Unable to connect to server.");
            });
    });

    /* =====================================================
       RESET
       ===================================================== */
    resetBtn?.addEventListener("click", resetForm);

    function resetForm() {
        mobileInput.value = "";
        nameInput.value = "";

        aptIdInput.value = "";
        aptCodeInput.value = "";
        if (aptDdLabel) {
            aptDdLabel.textContent = "— Select apartment —";
            aptDdLabel.classList.add("placeholder");
            aptDdToggle.classList.remove("has-value");
        }
        if (aptDdSearch) aptDdSearch.value = "";

        divInput.value = "";
        divChargeInput.value = 0;
        divDdList.innerHTML = "";
        divisionsCache = [];
        divDdToggle.disabled = true;
        divDdLabel.textContent = "Select apartment first";
        divDdLabel.classList.add("placeholder");
        divDdToggle.classList.remove("has-value");
        divHint.textContent = "";

        branchSelect.value = "";

        cart = [];
        renderCart();

        document.querySelector('input[name="moMode"][value="delivery"]').checked = true;
        setMode("delivery");

        mobileHint.textContent = "Type a mobile to auto-fill the customer.";
        mobileHint.classList.remove("success");
    }

    /* =====================================================
       POPUP
       ===================================================== */
    function showPopup(type, title, text, code) {
        popupIcon.classList.toggle("error", type === "error");
        popupIcon.innerHTML = type === "error"
            ? '<i class="bi bi-exclamation-lg"></i>'
            : '<i class="bi bi-check-lg"></i>';

        popupTitle.textContent = title;
        popupText.textContent = text;

        if (code) {
            popupCode.textContent = "ORDER: " + code;
            popupCode.style.display = "inline-block";
        } else {
            popupCode.style.display = "none";
        }

        popupOverlay.classList.add("show");
    }

    popupClose?.addEventListener("click", () => {
        popupOverlay.classList.remove("show");
    });

    popupOverlay?.addEventListener("click", (e) => {
        if (e.target === popupOverlay) popupOverlay.classList.remove("show");
    });

    /* =====================================================
       INIT
       ===================================================== */
    setMode("delivery");
    loadProducts("menu");
    renderCart();

})();