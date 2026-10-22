/* =========================================================
   MRS MILL@ — MANUAL ORDER (admin panel)
   File: ./js/manual-order-taken.js
   Apartment + individual house — auto-fetched, read-only.
   NEW customers blocked.

   Delivery boy override:
     - Set window.FORCED_DELIVERY_BOY_ID = <id>   (preferred)
     - Or window.SELECTED_BOY_ID = <id>           (legacy)
     - Or SEND 0 → server auto-assigns by address
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    /* ---------- DOM ---------- */
    const mobileInput = document.getElementById("moMobile");
    const nameInput = document.getElementById("moName");
    const mobileHint = document.getElementById("moMobileHint");

    const modeRadios = document.querySelectorAll('input[name="moMode"]');
    const deliveryBlock = document.getElementById("moDeliveryBlock");
    const pickupBlock = document.getElementById("moPickupBlock");
    const branchSelect = document.getElementById("moBranch");

    const payModeRadios = document.querySelectorAll('input[name="moPayMode"]');
    const walletPanel = document.getElementById("moWalletPanel");
    const walletBalance = document.getElementById("moWalletBalance");
    const walletCust = document.getElementById("moWalletCust");
    const walletStatus = document.getElementById("moWalletStatus");

    /* Apartment/House sub-blocks */
    const apartmentFields = document.getElementById("moApartmentFields");
    const houseFields = document.getElementById("moHouseFields");
    const houseAddressEl = document.getElementById("moHouseAddress");
    const houseMetaEl = document.getElementById("moHouseMeta");

    /* Apartment read-only vs manual */
    const aptReadOnly = document.getElementById("moAptReadOnly");
    const aptReadOnlyAddress = document.getElementById("moAptReadOnlyAddress");
    const aptReadOnlyMeta = document.getElementById("moAptReadOnlyMeta");
    const aptManual = document.getElementById("moAptManual");

    /* Apartment dropdown */
    const aptDdWrap = document.getElementById("moAptDdWrap");
    const aptDdToggle = document.getElementById("moAptDdToggle");
    const aptDdLabel = document.getElementById("moAptDdLabel");
    const aptDdSearch = document.getElementById("moAptDdSearch");
    const aptDdList = document.getElementById("moAptDdList");
    const aptIdInput = document.getElementById("moApartmentId");
    const aptCodeInput = document.getElementById("moApartmentCode");

    /* Division */
    const divDdWrap = document.getElementById("moDivDdWrap");
    const divDdToggle = document.getElementById("moDivDdToggle");
    const divDdLabel = document.getElementById("moDivDdLabel");
    const divDdSearch = document.getElementById("moDivDdSearch");
    const divDdList = document.getElementById("moDivDdList");
    const divInput = document.getElementById("moDivision");
    const divChargeInput = document.getElementById("moDivisionCharge");
    const divHint = document.getElementById("moDivisionHint");

    /* Products */
    const tabButtons = document.querySelectorAll(".mo-tab");
    const productSearch = document.getElementById("moProductSearch");
    const productGrid = document.getElementById("moProducts");

    /* Cart */
    const cartList = document.getElementById("moCartList");
    const cartCount = document.getElementById("moCartCount");
    const subtotalEl = document.getElementById("moSubtotal");
    const chargeEl = document.getElementById("moCharge");
    const chargeLabel = document.getElementById("moChargeLabel");
    const totalEl = document.getElementById("moTotal");
    const placeBtn = document.getElementById("moPlaceBtn");
    const resetBtn = document.getElementById("moResetBtn");

    const taxRow = document.getElementById("moTaxRow");
    const taxEl = document.getElementById("moTax");
    const taxLabelEl = document.getElementById("moTaxLabel");

    /* Variant modal */
    const varOverlay = document.getElementById("moVarOverlay");
    const varThumb = document.getElementById("moVarThumb");
    const varName = document.getElementById("moVarName");
    const varCode = document.getElementById("moVarCode");
    const varList = document.getElementById("moVarList");
    const varCancel = document.getElementById("moVarCancel");
    const varAdd = document.getElementById("moVarAdd");

    /* Popup */
    const popupOverlay = document.getElementById("moPopupOverlay");
    const popupIcon = document.getElementById("moPopupIcon");
    const popupTitle = document.getElementById("moPopupTitle");
    const popupText = document.getElementById("moPopupText");
    const popupCode = document.getElementById("moPopupCode");
    const popupClose = document.getElementById("moPopupClose");

    /* QR */
    const qrOverlay = document.getElementById("moQrOverlay");
    const qrCodeLabel = document.getElementById("moQrCode");
    const qrAmount = document.getElementById("moQrAmount");
    const qrBox = document.getElementById("moQrBox");
    const qrCancel = document.getElementById("moQrCancel");
    const qrConfirm = document.getElementById("moQrConfirm");
    const qrConfirmText = document.getElementById("moQrConfirmText");

    /* ---------- STATE ---------- */
    let apartmentsCache = [];
    let divisionsCache = [];
    let productsCache = [];
    let currentTab = "menu";
    let currentProduct = null;
    let selectedVariant = null;
    let cart = [];
    let currentMode = "delivery";
    let currentPayMode = "qr";
    let currentWallet = 0;
    let walletCustomer = null;

    /* { addressType, apartmentId, apartmentCode, apartmentName, division, divisionCharge, fullAddress, mapLink, houseImage } */
    let savedCustomerAddress = null;
    let customerConfirmed = false;

    let pendingQrOrderId = 0;
    let pendingQrOrderCode = "";
    let qrInstance = null;

    let lookupTimer = null;
    let lookupAbort = null;
    let lookupReqId = 0;
    let lastMobile = "";

    /* ---------- TAX ---------- */
    const TAX = (window.TAX_SETTINGS && typeof window.TAX_SETTINGS === "object")
        ? {
            status: Number(window.TAX_SETTINGS.status) || 0,
            rate: Number(window.TAX_SETTINGS.rate) || 0,
            type: (window.TAX_SETTINGS.type === "inclusive") ? "inclusive" : "exclusive"
        }
        : { status: 0, rate: 0, type: "exclusive" };

    /* ---------- Helpers ---------- */
    function money(n) {
        return "₹" + Math.round(Number(n) || 0);
    }

    function esc(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function calcTax(subtotal) {
        if (!TAX || Number(TAX.status) !== 1 || Number(TAX.rate) <= 0) {
            return { amount: 0, rate: 0, type: "exclusive", active: false };
        }
        const rate = Number(TAX.rate);
        const type = (TAX.type === "inclusive") ? "inclusive" : "exclusive";
        let amount = 0;
        if (type === "inclusive") {
            amount = subtotal - (subtotal / (1 + (rate / 100)));
        } else {
            amount = subtotal * (rate / 100);
        }
        return { amount: Math.round(amount), rate: rate, type: type, active: true };
    }

    /* =====================================================
       SWITCH UI VIEWS
    ===================================================== */
    function showHouseFields(fullAddress, mapLink, houseImage) {
        if (apartmentFields) apartmentFields.style.display = "none";
        if (houseFields) houseFields.style.display = "";

        if (houseAddressEl) houseAddressEl.textContent = fullAddress || "No address on file";

        if (houseMetaEl) {
            const bits = [];
            if (mapLink) {
                bits.push(`<a href="${esc(mapLink)}" target="_blank" rel="noopener noreferrer" class="map-link">
                    <i class="bi bi-geo-alt-fill"></i> View on Map
                </a>`);
            } else {
                bits.push(`<span class="no-map"><i class="bi bi-geo"></i> No map saved</span>`);
            }
            if (houseImage) {
                bits.push(`<a href="${esc(BASE_URL + houseImage)}" target="_blank" rel="noopener noreferrer" class="img-link">
                    <i class="bi bi-image-fill"></i> View House Photo
                </a>`);
            }
            houseMetaEl.innerHTML = bits.join("");
        }
    }

    function showApartmentReadOnly(apartmentName, apartmentCode, divisionLabel, divisionCharge, mapLink) {
        if (apartmentFields) apartmentFields.style.display = "";
        if (houseFields) houseFields.style.display = "none";

        if (aptReadOnly) aptReadOnly.style.display = "";
        if (aptManual) aptManual.style.display = "none";

        if (aptReadOnlyAddress) {
            aptReadOnlyAddress.textContent = apartmentName || "—";
        }

        if (aptReadOnlyMeta) {
            const bits = [];
            if (apartmentCode) {
                bits.push(`<span class="no-map" style="background:#faf7f0;border-color:#e4ddd3;color:#6f5a3f;">
                    <i class="bi bi-hash"></i> ${esc(apartmentCode)}
                </span>`);
            }
            if (divisionLabel) {
                bits.push(`<span class="no-map" style="background:#fdf7ec;border-color:#e8d5a8;color:#8a6a1e;">
                    <i class="bi bi-grid-3x3-gap"></i> ${esc(divisionLabel)}
                </span>`);
            }
            if (divisionCharge > 0) {
                bits.push(`<span class="no-map" style="background:#e8f6ea;border-color:#a5d6a7;color:#2e7d32;">
                    <i class="bi bi-truck"></i> Delivery ₹${Math.round(divisionCharge)}
                </span>`);
            }
            if (mapLink) {
                bits.push(`<a href="${esc(mapLink)}" target="_blank" rel="noopener noreferrer" class="map-link">
                    <i class="bi bi-geo-alt-fill"></i> View on Map
                </a>`);
            }
            aptReadOnlyMeta.innerHTML = bits.join("");
        }
    }

    function showApartmentManual() {
        if (apartmentFields) apartmentFields.style.display = "";
        if (houseFields) houseFields.style.display = "none";

        if (aptReadOnly) aptReadOnly.style.display = "none";
        if (aptManual) aptManual.style.display = "";
    }

    /* =====================================================
       RESET ADDRESS BLOCK
    ===================================================== */
    function resetAddressBlock() {
        if (aptIdInput) aptIdInput.value = "";
        if (aptCodeInput) aptCodeInput.value = "";

        if (aptDdLabel) {
            aptDdLabel.textContent = "— Select apartment —";
            aptDdLabel.classList.add("placeholder");
            aptDdToggle.classList.remove("has-value");
        }
        if (aptDdSearch) aptDdSearch.value = "";

        if (divInput) divInput.value = "";
        if (divChargeInput) divChargeInput.value = 0;
        divisionsCache = [];

        if (divDdToggle) divDdToggle.disabled = true;
        if (divDdLabel) {
            divDdLabel.textContent = "Select apartment first";
            divDdLabel.classList.add("placeholder");
            divDdToggle.classList.remove("has-value");
        }
        if (divDdList) divDdList.innerHTML = "";
        if (divHint) divHint.textContent = "";

        if (aptReadOnly) aptReadOnly.style.display = "none";
        if (aptManual) aptManual.style.display = "none";
        if (houseFields) houseFields.style.display = "none";
        if (apartmentFields) apartmentFields.style.display = "";

        customerConfirmed = false;
    }

    /* =====================================================
       CLEAR CUSTOMER DATA
    ===================================================== */
    function clearCustomerData() {
        if (nameInput) nameInput.value = "";

        currentWallet = 0;
        walletCustomer = null;

        if (currentPayMode === "wallet") {
            if (walletBalance) walletBalance.textContent = money(0);
            if (walletCust) walletCust.textContent = "—";
            updateWalletStatus();
        }

        savedCustomerAddress = null;
        resetAddressBlock();

        if (mobileHint) {
            mobileHint.textContent = "Type a mobile to load the customer.";
            mobileHint.classList.remove("success");
        }

        updateTotals();
    }

    /* =====================================================
       MOBILE LOOKUP — blocks new customers
    ===================================================== */
    function lookupMobile(m) {
        if (!m || m.length < 10) return;
        if (m === lastMobile) return;
        lastMobile = m;

        if (lookupAbort) {
            try { lookupAbort.abort(); } catch (e) { }
            lookupAbort = null;
        }

        const reqId = ++lookupReqId;
        const controller = new AbortController();
        lookupAbort = controller;

        fetch(BASE_URL + "ajax/manual-lookup-customer.php?mobile=" + encodeURIComponent(m), {
            credentials: "same-origin",
            signal: controller.signal
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (reqId !== lookupReqId) return;
                if (mobileInput.value.trim() !== m) return;

                /* NEW CUSTOMER — BLOCK */
                if (!res || !res.success || !res.data || !res.data.found) {
                    nameInput.value = "";
                    currentWallet = 0;
                    walletCustomer = null;
                    savedCustomerAddress = null;
                    customerConfirmed = false;

                    if (currentPayMode === "wallet") {
                        walletBalance.textContent = money(0);
                        walletCust.textContent = "—";
                        updateWalletStatus();
                    }

                    resetAddressBlock();

                    mobileHint.textContent = "❌ New number — customer must register first.";
                    mobileHint.style.color = "#b51f2c";
                    mobileHint.classList.remove("success");

                    updateTotals();
                    return;
                }

                /* FOUND */
                customerConfirmed = true;
                mobileHint.style.color = "";
                if (res.data.name) nameInput.value = res.data.name;

                currentWallet = Number(res.data.wallet_balance || 0);
                walletCustomer = {
                    id: res.data.id || 0,
                    name: res.data.name || "",
                    mobile: m
                };

                /* Force delivery mode */
                const dRadio = document.querySelector('input[name="moMode"][value="delivery"]');
                if (dRadio && !dRadio.checked) {
                    dRadio.checked = true;
                    setMode("delivery");
                }

                if (res.data.address_type === "individual_house") {
                    aptIdInput.value = "";
                    aptCodeInput.value = "";
                    divInput.value = "";
                    divChargeInput.value = 0;
                    divisionsCache = [];

                    showHouseFields(
                        res.data.full_address || "",
                        res.data.map_link || "",
                        res.data.house_image || ""
                    );

                    savedCustomerAddress = {
                        addressType: "individual_house",
                        fullAddress: res.data.full_address || "",
                        mapLink: res.data.map_link || "",
                        houseImage: res.data.house_image || ""
                    };

                    mobileHint.textContent = "🏠 Individual House — address loaded.";
                    mobileHint.classList.add("success");
                    updateTotals();

                } else if (res.data.apartment_id > 0) {
                    /* Apartment read-only */
                    showApartmentReadOnly(
                        res.data.apartment_name || "",
                        res.data.apartment_code || "",
                        res.data.division || "",
                        Number(res.data.division_charge || 0),
                        ""
                    );

                    /* Store internal values for submit */
                    aptIdInput.value = res.data.apartment_id;
                    aptCodeInput.value = res.data.apartment_code || "";
                    divInput.value = res.data.division || "";
                    divChargeInput.value = Number(res.data.division_charge || 0);

                    savedCustomerAddress = {
                        addressType: "apartment",
                        apartmentId: res.data.apartment_id,
                        apartmentCode: res.data.apartment_code || "",
                        apartmentName: res.data.apartment_name || "",
                        division: res.data.division || "",
                        divisionCharge: Number(res.data.division_charge || 0)
                    };

                    mobileHint.textContent = "🏢 Apartment — details loaded (read only).";
                    mobileHint.classList.add("success");
                    updateTotals();

                } else {
                    /* Known but no address */
                    showApartmentManual();
                    resetAddressBlock();
                    customerConfirmed = true;
                    mobileHint.textContent = "⚠ Customer found but no address on file.";
                    mobileHint.style.color = "#b8893c";
                    mobileHint.classList.add("success");
                    savedCustomerAddress = null;
                }

                if (currentPayMode === "wallet") {
                    walletBalance.textContent = money(currentWallet);
                    walletCust.textContent = res.data.name || m;
                    updateWalletStatus();
                }

                updateTotals();
            })
            .catch(err => {
                if (err && err.name === "AbortError") return;
            });
    }

    /* =====================================================
       MOBILE INPUT LISTENERS
    ===================================================== */
    mobileInput?.addEventListener("input", function () {
        this.value = this.value.replace(/[^0-9]/g, "").slice(0, 15);

        clearTimeout(lookupTimer);

        if (lookupAbort) {
            try { lookupAbort.abort(); } catch (e) { }
            lookupAbort = null;
        }
        lookupReqId++;
        lastMobile = "";

        clearCustomerData();

        const m = this.value.trim();
        if (m.length >= 10) {
            lookupTimer = setTimeout(() => lookupMobile(m), 350);
        }
    });

    mobileInput?.addEventListener("paste", function () {
        const self = this;
        setTimeout(() => {
            self.value = self.value.replace(/[^0-9]/g, "").slice(0, 15);
            clearTimeout(lookupTimer);

            if (lookupAbort) {
                try { lookupAbort.abort(); } catch (e) { }
                lookupAbort = null;
            }
            lookupReqId++;
            lastMobile = "";

            clearCustomerData();

            const m = self.value.trim();
            if (m.length >= 10) {
                lookupTimer = setTimeout(() => lookupMobile(m), 350);
            }
        }, 0);
    });

    mobileInput?.addEventListener("blur", function () {
        const m = this.value.trim();
        if (m.length >= 10 && m !== lastMobile) {
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

            divisionsCache = [];

        } else {
            deliveryBlock.style.display = "block";
            pickupBlock.style.display = "none";
            branchSelect.value = "";

            if (savedCustomerAddress && savedCustomerAddress.addressType === "individual_house") {
                showHouseFields(
                    savedCustomerAddress.fullAddress || "",
                    savedCustomerAddress.mapLink || "",
                    savedCustomerAddress.houseImage || ""
                );
                aptIdInput.value = "";
                aptCodeInput.value = "";
                divInput.value = "";
                divChargeInput.value = 0;
                divisionsCache = [];

            } else if (savedCustomerAddress && savedCustomerAddress.addressType === "apartment") {
                showApartmentReadOnly(
                    savedCustomerAddress.apartmentName || "",
                    savedCustomerAddress.apartmentCode || "",
                    savedCustomerAddress.division || "",
                    Number(savedCustomerAddress.divisionCharge || 0),
                    ""
                );

                aptIdInput.value = savedCustomerAddress.apartmentId;
                aptCodeInput.value = savedCustomerAddress.apartmentCode;
                divInput.value = savedCustomerAddress.division || "";
                divChargeInput.value = Number(savedCustomerAddress.divisionCharge || 0);

            } else {
                resetAddressBlock();
            }
        }

        updateTotals();
    }

    modeRadios.forEach(r => {
        r.addEventListener("change", function () {
            if (this.checked) setMode(this.value);
        });
    });

    /* =====================================================
       PAYMENT MODE
    ===================================================== */
    function setPayMode(mode) {
        currentPayMode = mode;

        if (mode === "wallet") {
            walletPanel.style.display = "block";
            walletBalance.textContent = money(currentWallet);
            walletCust.textContent = walletCustomer ? walletCustomer.name : "—";
            updateWalletStatus();
        } else {
            walletPanel.style.display = "none";
            if (walletStatus) walletStatus.className = "mo-wallet-status";
        }

        updateTotals();
    }

    payModeRadios.forEach(r => {
        r.addEventListener("change", function () {
            if (this.checked) setPayMode(this.value);
        });
    });

    /* =====================================================
       WALLET STATUS
    ===================================================== */
    function updateWalletStatus() {
        if (!walletStatus) return;

        if (currentPayMode !== "wallet") {
            walletStatus.className = "mo-wallet-status";
            return;
        }

        if (!walletCustomer || !walletCustomer.id) {
            walletStatus.className = "mo-wallet-status info";
            walletStatus.innerHTML = '<i class="bi bi-info-circle-fill"></i> Enter a valid mobile number to load wallet.';
            return;
        }

        const subtotal = cart.reduce((s, c) => s + (c.price * c.qty), 0);
        const charge = currentMode === "delivery" ? Number(divChargeInput.value || 0) : 0;
        const tax = calcTax(subtotal);
        const total = (tax.type === "inclusive" && tax.active)
            ? subtotal + charge
            : subtotal + charge + tax.amount;

        if (currentWallet <= 0) {
            walletStatus.className = "mo-wallet-status error";
            walletStatus.innerHTML = '<i class="bi bi-x-circle-fill"></i> No wallet balance available.';
            return;
        }

        if (currentWallet < total) {
            walletStatus.className = "mo-wallet-status error";
            walletStatus.innerHTML = '<i class="bi bi-x-circle-fill"></i> Insufficient balance. Need ' +
                money(total) + ' but only ' + money(currentWallet) + ' available.';
            return;
        }

        walletStatus.className = "mo-wallet-status success";
        walletStatus.innerHTML = '<i class="bi bi-check-circle-fill"></i> Wallet has enough balance. ' +
            money(total) + ' will be deducted.';
    }

    /* =====================================================
       APARTMENTS (manual picker for fallback)
    ===================================================== */
    function loadApartments() {
        fetch(BASE_URL + "ajax/manual-get-apartments.php", { credentials: "same-origin" })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (res && res.success && Array.isArray(res.data)) {
                    apartmentsCache = res.data;
                }
            })
            .catch(() => { });
    }
    loadApartments();

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

        aptIdInput.value = opt.dataset.id;
        aptCodeInput.value = opt.dataset.code;
        aptDdLabel.textContent = opt.dataset.name;
        aptDdLabel.classList.remove("placeholder");
        aptDdToggle.classList.add("has-value");

        closeAptDd();

        const apt = apartmentsCache.find(a => Number(a.id) === Number(opt.dataset.id));
        if (apt) populateDivisions(apt, "");

        if (savedCustomerAddress) {
            savedCustomerAddress.addressType = "apartment";
            savedCustomerAddress.apartmentId = Number(opt.dataset.id);
            savedCustomerAddress.apartmentCode = opt.dataset.code || "";
            savedCustomerAddress.apartmentName = opt.dataset.name || "";
        }

        updateTotals();
    });

    /* =====================================================
       DIVISIONS
    ===================================================== */
    function divLabelText(d) {
        if (d.division_label) return d.division_label;
        const parts = [];
        if (d.block) parts.push("Blk " + d.block);
        if (d.door_no) parts.push("Door " + d.door_no);
        if (d.floor) parts.push("Fl " + d.floor);
        return parts.length ? parts.join(" · ") : ("Division " + (d.division || ""));
    }
    function divValueText(d) {
        if (d.division_value) return d.division_value;
        const parts = [];
        if (d.block) parts.push("Blk " + d.block);
        if (d.door_no) parts.push("Door " + d.door_no);
        if (d.floor) parts.push("Fl " + d.floor);
        return parts.length ? parts.join(" / ") : String(d.division || "");
    }

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

        if (preselect) {
            const match = divisionsCache.find(d => divValueText(d) === String(preselect));
            if (match) {
                selectDivision({
                    division: divValueText(match),
                    label: divLabelText(match),
                    charge: Number(match.charge || 0)
                });
                return;
            }
        }

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
            ? divisionsCache.filter(d => {
                const label = divLabelText(d).toLowerCase();
                const value = divValueText(d).toLowerCase();
                return label.includes(q) || value.includes(q);
            })
            : divisionsCache;

        if (!list.length) {
            divDdList.innerHTML = `<div class="sd-empty">No divisions found.</div>`;
            return;
        }

        divDdList.innerHTML = list.map(d => {
            const v = divValueText(d);
            const l = divLabelText(d);
            const selected = String(divInput.value) === v;
            return `
                <div class="sd-option ${selected ? 'selected' : ''}"
                     data-division="${esc(v)}"
                     data-label="${esc(l)}"
                     data-charge="${Number(d.charge || 0)}">
                    <i class="bi bi-grid-3x3-gap"></i>
                    <div class="name">${esc(l)}</div>
                    <div class="meta">₹${Math.round(Number(d.charge || 0))}</div>
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
            label: opt.dataset.label || opt.dataset.division,
            charge: Number(opt.dataset.charge || 0)
        });

        closeDivDd();
    });

    function selectDivision(d) {
        divInput.value = String(d.division || "");
        divChargeInput.value = Number(d.charge || 0);
        divDdLabel.textContent = (d.label || d.division) + " · ₹" + Math.round(Number(d.charge || 0));
        divDdLabel.classList.remove("placeholder");
        divDdToggle.classList.add("has-value");
        divHint.textContent = "Charge: ₹" + Math.round(Number(d.charge || 0));

        if (savedCustomerAddress) {
            savedCustomerAddress.addressType = "apartment";
            savedCustomerAddress.division = String(d.division || "");
            savedCustomerAddress.divisionCharge = Number(d.charge || 0);
        }

        updateTotals();
    }

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

                    const containerBadge = (Number(v.container_enabled) === 1 && Number(v.container_price) > 0)
                        ? `<span style="display:inline-flex;align-items:center;gap:4px;font-size:9.5px;font-weight:700;color:#b8893c;background:#fdf7ec;border:1px dashed #e8d5a8;border-radius:5px;padding:2px 6px;margin-top:5px;">
                               <i class="bi bi-box2-heart"></i> Container +${money(v.container_price)}
                           </span>`
                        : "";

                    return `
                        <div class="mo-var" data-vid="${v.id}">
                            <div class="mo-var-radio"></div>
                            <div class="mo-var-info">
                                <div class="mo-var-name">${esc(qtyLabel)}</div>
                                <div class="mo-var-meta">${esc(v.quantity)} ${esc(v.quantity_unit)}</div>
                                ${containerBadge}
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
                key: key,
                product_id: currentProduct.id,
                code: currentProduct.product_code,
                name: currentProduct.product_name,
                image: currentProduct.image || "",
                variant_id: selectedVariant.id,
                variant_name: selectedVariant.quantity_name || "",
                variant_qty: selectedVariant.quantity + " " + selectedVariant.quantity_unit,
                price: Number(selectedVariant.price),
                qty: 1,
                container_enabled: Number(selectedVariant.container_enabled || 0),
                container_price: Number(selectedVariant.container_price || 0),
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

            const containerBadge = (c.container_enabled && c.container_price > 0)
                ? `<span style="display:inline-flex;align-items:center;gap:4px;font-size:9.5px;font-weight:700;color:#b8893c;background:#fdf7ec;border-radius:5px;padding:2px 6px;margin-top:4px;">
                       <i class="bi bi-box2-heart"></i> Container +${money(c.container_price)} × ${c.qty}
                   </span>`
                : "";

            return `
                <div class="mo-cart-item" data-key="${esc(c.key)}">
                    <div class="mo-cart-thumb">${thumb}</div>
                    <div class="mo-cart-info">
                        <p class="mo-cart-name">${esc(c.name)}</p>
                        <p class="mo-cart-meta">
                            ${esc(c.variant_name || "")} · <strong>${money(c.price)}</strong>
                        </p>
                        ${containerBadge}
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

        const tax = calcTax(subtotal);

        let total = 0;
        if (tax.active) {
            if (tax.type === "inclusive") {
                total = subtotal + charge;
            } else {
                total = subtotal + charge + tax.amount;
            }
        } else {
            total = subtotal + charge;
        }

        subtotalEl.textContent = money(subtotal);
        chargeEl.textContent = money(charge);
        totalEl.textContent = money(total);

        if (taxRow && taxEl && taxLabelEl) {
            if (tax.active) {
                taxLabelEl.textContent = "Tax (" + tax.rate + "%)" +
                    (tax.type === "inclusive" ? " · incl." : "");
                taxEl.textContent = money(tax.amount);
                taxRow.style.display = "flex";
            } else {
                taxRow.style.display = "none";
            }
        }

        const containerTotalAmount = cart.reduce((s, c) => {
            if (c.container_enabled && c.container_price > 0) {
                return s + (c.container_price * c.qty);
            }
            return s;
        }, 0);

        const containerCount = cart.reduce((s, c) => {
            if (c.container_enabled && c.container_price > 0) {
                return s + c.qty;
            }
            return s;
        }, 0);

        let containerEl = document.getElementById("moContainerSummary");
        if (!containerEl) {
            containerEl = document.createElement("div");
            containerEl.id = "moContainerSummary";
            containerEl.className = "mo-totals-row";
            containerEl.style.cssText = "color:#b8893c;font-weight:700;border-top:1px dashed #e4ddd3;margin-top:8px;padding-top:10px;";
            chargeEl.parentElement.parentElement.insertBefore(containerEl, chargeEl.parentElement.parentElement.querySelector(".grand"));
        }

        if (containerCount > 0) {
            containerEl.style.display = "flex";
            containerEl.innerHTML =
                '<span><i class="bi bi-box2-heart"></i> Containers (' + containerCount + ')</span>' +
                '<strong style="color:#b8893c;">' + money(containerTotalAmount) + '</strong>';
        } else {
            containerEl.style.display = "none";
        }

        let canPlace = cart.length > 0 && customerConfirmed;

        if (currentPayMode === "wallet") {
            const walletOk =
                walletCustomer &&
                walletCustomer.id &&
                currentWallet >= total;

            canPlace = canPlace && walletOk;
            updateWalletStatus();
        }

        placeBtn.disabled = !canPlace;
    }

    /* =====================================================
       PLACE ORDER
    ===================================================== */
    placeBtn?.addEventListener("click", function () {
        const name = nameInput.value.trim();
        const mobile = mobileInput.value.trim();

        if (!/^[0-9]{10,15}$/.test(mobile)) { showPopup("error", "Invalid Mobile", "Please enter a valid 10-digit mobile."); return; }
        if (name.length < 2) { showPopup("error", "Name Required", "Please enter the customer name."); return; }
        if (!cart.length) { showPopup("error", "Cart Empty", "Please add at least one product."); return; }
        if (!customerConfirmed) { showPopup("error", "Customer Not Found", "This mobile is not registered. Customer must register first."); return; }

        const mode = document.querySelector('input[name="moMode"]:checked')?.value || "delivery";
        const isIndividualHouse = savedCustomerAddress && savedCustomerAddress.addressType === "individual_house";

        if (mode === "delivery") {
            if (isIndividualHouse) {
                if (!savedCustomerAddress.fullAddress) {
                    showPopup("error", "Address Missing", "Individual house address not loaded. Re-enter mobile number.");
                    return;
                }
            } else {
                if (!aptIdInput.value) { showPopup("error", "Apartment Required", "Please select an apartment."); return; }
                if (!divInput.value) { showPopup("error", "Division Required", "Please select a division."); return; }
            }
        } else {
            if (!branchSelect.value) { showPopup("error", "Branch Required", "Please select a pickup branch."); return; }
        }

        if (currentPayMode === "wallet") {
            if (!walletCustomer || !walletCustomer.id) {
                showPopup("error", "Customer Required", "Please enter a mobile number with a wallet account.");
                return;
            }

            const subtotal = cart.reduce((s, c) => s + (c.price * c.qty), 0);
            const charge = mode === "delivery" ? Number(divChargeInput.value || 0) : 0;
            const tax = calcTax(subtotal);
            const total = tax.active && tax.type === "inclusive"
                ? subtotal + charge
                : subtotal + charge + tax.amount;

            if (currentWallet < total) {
                showPopup("error", "Insufficient Balance",
                    "Wallet balance is " + money(currentWallet) +
                    " but the order total is " + money(total) +
                    ". Please choose QR payment.");
                return;
            }
        }

        placeBtn.disabled = true;
        placeBtn.innerHTML = '<span class="btn-spinner" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:asSpin .7s linear infinite;display:inline-block;"></span> Placing...';

        const charge = mode === "delivery" ? Number(divChargeInput.value || 0) : 0;

        const fd = new FormData();
        fd.append("customer_name", name);
        fd.append("customer_mobile", mobile);
        fd.append("delivery_mode", mode);

        if (isIndividualHouse && mode === "delivery") {
            fd.append("address_type", "individual_house");
            fd.append("apartment_id", "0");
            fd.append("apartment_code", "");
            fd.append("division", "");
            fd.append("division_charge", "0");
            fd.append("full_address", savedCustomerAddress.fullAddress || "");
            fd.append("map_link", savedCustomerAddress.mapLink || "");
        } else {
            fd.append("address_type", "apartment");
            fd.append("apartment_id", aptIdInput.value || 0);
            fd.append("apartment_code", aptCodeInput.value || "");
            fd.append("division", divInput.value || "");
            fd.append("division_charge", charge);
            fd.append("full_address", "");
            fd.append("map_link", "");
        }

        fd.append("pickup_branch_id", branchSelect.value || 0);
        fd.append("payment_method", currentPayMode);

        /* =========================================================
           DELIVERY BOY ID — send the real primary key from
           delivery_boys.id (e.g. 3 = DB000002)

           Priority:
             1. window.FORCED_DELIVERY_BOY_ID   (preferred)
             2. window.SELECTED_BOY_ID          (legacy)
             3. 0 → server auto-assigns by address
           ========================================================= */
        const forcedBoyId =
            Number(window.FORCED_DELIVERY_BOY_ID || 0) ||
            Number(window.SELECTED_BOY_ID || 0) ||
            0;

        fd.append("delivery_boy_id", forcedBoyId);

        fd.append("products", JSON.stringify(cart.map(c => ({
            product_id: c.product_id,
            code: c.code,
            name: c.name,
            image: c.image,
            variant_id: c.variant_id,
            variant_name: c.variant_name,
            variant_qty: c.variant_qty,
            price: c.price,
            qty: c.qty,
            container_enabled: c.container_enabled || 0,
            container_price: c.container_price || 0,
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

                if (res.data.payment_method === "wallet") {
                    const w = res.data.wallet || {};
                    showPopup(
                        "success",
                        "Order Placed!",
                        "Paid " + money(res.data.total) + " from wallet. New balance: " + money(w.balance_after || 0) + ".",
                        res.data.order_code
                    );
                    resetForm();
                } else {
                    openQrPopup(
                        res.data.order_id,
                        res.data.order_code,
                        res.data.total,
                        res.data.upi_string
                    );
                    resetFormFieldsOnly();
                }
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

        resetAddressBlock();
        branchSelect.value = "";

        cart = [];
        renderCart();

        document.querySelector('input[name="moMode"][value="delivery"]').checked = true;
        setMode("delivery");

        currentPayMode = "qr";
        currentWallet = 0;
        walletCustomer = null;
        savedCustomerAddress = null;
        customerConfirmed = false;
        document.querySelector('input[name="moPayMode"][value="qr"]').checked = true;
        setPayMode("qr");

        walletBalance.textContent = "₹0";
        walletCust.textContent = "—";
        walletStatus.className = "mo-wallet-status";

        mobileHint.textContent = "Type a mobile to load the customer.";
        mobileHint.classList.remove("success");
        mobileHint.style.color = "";

        lastMobile = "";
        lookupReqId++;
    }

    function resetFormFieldsOnly() {
        resetForm();
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
       QR POPUP
    ===================================================== */
    function openQrPopup(orderId, orderCode, total, upiString) {
        pendingQrOrderId = orderId;
        pendingQrOrderCode = orderCode;

        if (qrCodeLabel) qrCodeLabel.textContent = orderCode;
        if (qrAmount) qrAmount.textContent = "₹" + Math.round(Number(total) || 0);

        if (qrBox) {
            const logoUrl = window.QR_LOGO_URL || "";
            const logoInner = logoUrl
                ? `<img src="${logoUrl}" alt="Logo" style="max-width:100%;max-height:100%;object-fit:contain;border-radius:8px;display:block;">`
                : `<span style="color:#b51f2c;font-family:'Playfair Display',serif;font-weight:700;font-size:24px;">M</span>`;

            qrBox.innerHTML = `
                <div id="moQrLogoHolder" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:52px;height:52px;background:#fff;border-radius:12px;display:flex;align-items:center;justify-content:center;padding:5px;box-shadow:0 2px 10px rgba(0,0,0,.1);border:2px solid #fff;z-index:3;pointer-events:none;">
                    ${logoInner}
                </div>
            `;
        }

        if (typeof QRCode !== "undefined" && qrBox && upiString) {
            try {
                const holder = document.createElement("div");
                holder.style.width = "100%";
                holder.style.height = "100%";
                holder.style.display = "flex";
                holder.style.alignItems = "center";
                holder.style.justifyContent = "center";
                qrBox.insertBefore(holder, qrBox.firstChild);

                qrInstance = new QRCode(holder, {
                    text: upiString,
                    width: 200,
                    height: 200,
                    colorDark: "#302923",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            } catch (e) {
                console.warn("QR error:", e);
            }
        }

        qrOverlay.classList.add("show");
    }

    function closeQrPopup() {
        if (!qrOverlay) return;
        qrOverlay.classList.remove("show");
        pendingQrOrderId = 0;
        pendingQrOrderCode = "";
        qrInstance = null;
    }

    qrCancel?.addEventListener("click", closeQrPopup);
    qrOverlay?.addEventListener("click", e => {
        if (e.target === qrOverlay) closeQrPopup();
    });

    qrConfirm?.addEventListener("click", function () {
        if (!pendingQrOrderId) return;

        qrConfirm.disabled = true;
        qrConfirmText.innerHTML = '<span class="btn-spinner" style="width:12px;height:12px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:asSpin .7s linear infinite;display:inline-block;"></span> Confirming...';

        const fd = new FormData();
        fd.append("order_id", pendingQrOrderId);

        fetch(BASE_URL + "ajax/manual-confirm-payment.php", {
            method: "POST",
            body: fd,
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                qrConfirm.disabled = false;
                qrConfirmText.textContent = "Confirm Payment";

                if (!res || !res.success) {
                    showPopup("error", "Failed", (res && res.message) || "Could not confirm payment.");
                    return;
                }

                const code = pendingQrOrderCode;
                closeQrPopup();
                showPopup("success", "Payment Confirmed!",
                    "Order " + code + " is now marked as paid.", code);
                resetForm();
            })
            .catch(() => {
                qrConfirm.disabled = false;
                qrConfirmText.textContent = "Confirm Payment";
                showPopup("error", "Connection Error", "Unable to connect.");
            });
    });

    /* =====================================================
       INIT
    ===================================================== */
    setMode("delivery");
    setPayMode("qr");
    loadProducts("menu");
    renderCart();

})();