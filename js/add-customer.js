/* =========================================================
   MRS MILL@ — ADD CUSTOMER
   File: ./js/add-customer.js
   Searchable apartment + division, required password (min 3),
   optional initial wallet, POPUP-based success/error
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    /* DOM — Form */
    const form         = document.getElementById("customerForm");
    const saveBtn      = document.getElementById("saveBtn");
    const saveBtnText  = document.getElementById("saveBtnText");

    const nameInput    = document.getElementById("full_name");
    const mobileInput  = document.getElementById("mobile_number");
    const mobileHelp   = document.getElementById("mobileHelp");

    const passwordInput = document.getElementById("password");
    const passwordHelp  = document.getElementById("passwordHelp");

    /* Apartment */
    const aptDdWrap     = document.getElementById("acAptDdWrap");
    const aptDdToggle   = document.getElementById("acAptDdToggle");
    const aptDdLabel    = document.getElementById("acAptDdLabel");
    const aptDdSearch   = document.getElementById("acAptDdSearch");
    const aptDdList     = document.getElementById("acAptDdList");
    const aptIdInput    = document.getElementById("acApartmentId");
    const aptCodeInput  = document.getElementById("acApartmentCode");
    const aptNameInput  = document.getElementById("acApartmentName");

    /* Division */
    const divDdWrap     = document.getElementById("acDivDdWrap");
    const divDdToggle   = document.getElementById("acDivDdToggle");
    const divDdLabel    = document.getElementById("acDivDdLabel");
    const divDdSearch   = document.getElementById("acDivDdSearch");
    const divDdList     = document.getElementById("acDivDdList");
    const divInput      = document.getElementById("acDivision");
    const divChargeInput = document.getElementById("acDivisionCharge");
    const divHint       = document.getElementById("acDivisionHint");

    /* Wallet */
    const walletToggle  = document.getElementById("walletToggle");
    const walletFields  = document.getElementById("walletFields");
    const walletAmount  = document.getElementById("walletAmount");
    const walletNote    = document.getElementById("walletNote");
    const walletPreview = document.getElementById("walletPreview");

    /* Popups */
    const successOverlay = document.getElementById("successOverlay");
    const successText    = document.getElementById("successText");
    const successCode    = document.getElementById("successCode");

    const errorOverlay   = document.getElementById("errorOverlay");
    const errorText      = document.getElementById("errorText");
    const errorOkBtn     = document.getElementById("errorOkBtn");

    if (!form) return;

    /* STATE */
    let apartmentsCache = [];
    let divisionsCache  = [];
    let walletEnabled   = false;

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

    /* =========================================
       POPUPS
       ========================================= */
    function showSuccessPopup(message, code) {
        if (!successOverlay) return;

        if (successText) {
            successText.textContent = message || "Customer added successfully.";
        }

        if (successCode) {
            if (code) {
                successCode.textContent = "CODE: " + code;
                successCode.style.display = "inline-block";
            } else {
                successCode.style.display = "none";
            }
        }

        successOverlay.classList.add("show");
        successOverlay.setAttribute("aria-hidden", "false");
    }

    function closeSuccessPopup() {
        if (!successOverlay) return;
        successOverlay.classList.remove("show");
        successOverlay.setAttribute("aria-hidden", "true");
    }

    function showErrorPopup(message) {
        if (!errorOverlay) return;

        if (errorText) {
            errorText.textContent = message || "Please check the form and try again.";
        }

        errorOverlay.classList.add("show");
        errorOverlay.setAttribute("aria-hidden", "false");

        if (errorOkBtn) setTimeout(() => errorOkBtn.focus(), 60);
    }

    function closeErrorPopup() {
        if (!errorOverlay) return;
        errorOverlay.classList.remove("show");
        errorOverlay.setAttribute("aria-hidden", "true");
    }

    /* Close on backdrop click */
    successOverlay?.addEventListener("click", function (e) {
        if (e.target === successOverlay) closeSuccessPopup();
    });

    errorOverlay?.addEventListener("click", function (e) {
        if (e.target === errorOverlay) closeErrorPopup();
    });

    /* Close on ESC */
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            if (successOverlay?.classList.contains("show")) closeSuccessPopup();
            if (errorOverlay?.classList.contains("show")) closeErrorPopup();
        }
    });

    errorOkBtn?.addEventListener("click", closeErrorPopup);

    /* =========================================
       BUTTON STATE
       ========================================= */
    function setLoading(isLoading) {
        if (!saveBtn) return;
        saveBtn.disabled = isLoading;
        saveBtnText.innerHTML = isLoading
            ? '<span class="btn-spinner"></span> Saving...'
            : 'Save Customer';
    }

    /* =========================================
       MOBILE HINT
       ========================================= */
    mobileInput?.addEventListener("input", function () {
        this.value = this.value.replace(/[^0-9]/g, "").slice(0, 15);
        const m = this.value.trim();

        if (!mobileHelp) return;

        if (!m) {
            mobileHelp.textContent = "Enter a 10-digit mobile number.";
            mobileHelp.className = "field-help";
        } else if (m.length < 10) {
            mobileHelp.textContent = "Too short — needs at least 10 digits.";
            mobileHelp.className = "field-help";
        } else if (m.length > 15) {
            mobileHelp.textContent = "Too long — max 15 digits.";
            mobileHelp.className = "field-help error";
        } else {
            mobileHelp.textContent = "Looks good.";
            mobileHelp.className = "field-help success";
        }
    });

    /* =========================================
       PASSWORD HINT (min 3)
       ========================================= */
    passwordInput?.addEventListener("input", function () {
        const v = this.value;
        if (!passwordHelp) return;

        if (!v) {
            passwordHelp.textContent = "Password must be at least 3 characters.";
            passwordHelp.className = "field-help";
        } else if (v.length < 3) {
            passwordHelp.textContent = "Too short — minimum 3 characters.";
            passwordHelp.className = "field-help error";
        } else {
            passwordHelp.textContent = "Password is valid.";
            passwordHelp.className = "field-help success";
        }
    });

    /* =========================================
       LOAD APARTMENTS
       ========================================= */
    function loadApartments() {
        aptDdList.innerHTML = `<div class="sd-empty">Loading apartments…</div>`;

        fetch(BASE_URL + "ajax/manual-get-apartments.php", { credentials: "same-origin" })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !Array.isArray(res.data)) {
                    aptDdList.innerHTML = `<div class="sd-empty">Failed to load apartments.</div>`;
                    return;
                }
                apartmentsCache = res.data;
                renderAptOptions("");
            })
            .catch(() => {
                aptDdList.innerHTML = `<div class="sd-empty">Unable to connect.</div>`;
            });
    }

    /* =========================================
       APARTMENT DROPDOWN
       ========================================= */
    function renderAptOptions(query) {
        if (!aptDdList) return;

        if (!apartmentsCache.length) {
            aptDdList.innerHTML = `<div class="sd-empty">No apartments available.</div>`;
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
            aptDdList.innerHTML = `<div class="sd-empty">No apartments match "${esc(query)}".</div>`;
            return;
        }

        aptDdList.innerHTML = list.slice(0, 60).map(a => {
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
        aptNameInput.value = opt.dataset.name;

        aptDdLabel.textContent = opt.dataset.name;
        aptDdLabel.classList.remove("placeholder");
        aptDdToggle.classList.add("has-value");

        closeAptDd();

        const apt = apartmentsCache.find(a => Number(a.id) === Number(opt.dataset.id));
        if (apt) populateDivisions(apt);
    });

    /* =========================================
       DIVISION DROPDOWN
       ========================================= */
    function populateDivisions(apt) {
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
            return;
        }

        divisionsCache = apt.divisions;
        divDdToggle.disabled = false;

        divInput.value = "";
        divChargeInput.value = 0;
        divDdLabel.textContent = "— Type or select division —";
        divDdLabel.classList.add("placeholder");
        divDdToggle.classList.remove("has-value");
        divDdList.innerHTML = "";
        divHint.textContent = "";
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
            divDdList.innerHTML = `<div class="sd-empty">No divisions match "${esc(query)}".</div>`;
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

        const d = {
            division: opt.dataset.division,
            charge:   Number(opt.dataset.charge || 0)
        };

        divInput.value = String(d.division);
        divChargeInput.value = Number(d.charge);
        divDdLabel.textContent = "Division " + d.division + " · ₹" + Math.round(Number(d.charge));
        divDdLabel.classList.remove("placeholder");
        divDdToggle.classList.add("has-value");
        divHint.textContent = "Charge: ₹" + Math.round(Number(d.charge));

        closeDivDd();
    });

    document.addEventListener("click", function (e) {
        if (!e.target.closest("#acAptDdWrap")) closeAptDd();
        if (!e.target.closest("#acDivDdWrap")) closeDivDd();
    });

    /* =========================================
       WALLET TOGGLE
       ========================================= */
    walletToggle?.addEventListener("click", function () {
        walletEnabled = !walletEnabled;
        walletToggle.classList.toggle("on", walletEnabled);
        walletFields.classList.toggle("show", walletEnabled);

        if (walletEnabled) {
            walletAmount.focus();
        } else {
            walletAmount.value = "";
            walletNote.value = "";
            walletPreview.textContent = "₹0";
        }
    });

    walletAmount?.addEventListener("input", function () {
        const v = Number(this.value || 0);
        walletPreview.textContent = money(v > 0 ? v : 0);
    });

    /* =========================================
       SUBMIT
       ========================================= */
    form.addEventListener("submit", function (e) {
        e.preventDefault();

        const name     = nameInput.value.trim();
        const mobile   = mobileInput.value.trim();
        const password = passwordInput.value.trim();

        if (!name) {
            return showErrorPopup("Customer name is required.");
        }

        if (!/^[0-9]{10,15}$/.test(mobile)) {
            return showErrorPopup("Please enter a valid 10-digit mobile number.");
        }

        if (password.length < 3) {
            return showErrorPopup("Password must be at least 3 characters.");
        }

        let initWallet = 0;
        let initNote   = "";
        if (walletEnabled) {
            initWallet = parseFloat(walletAmount.value || "0");
            initNote   = walletNote.value.trim();
            if (!initWallet || initWallet <= 0) {
                return showErrorPopup("Enter a valid wallet amount or turn off the wallet toggle.");
            }
        }

        setLoading(true);

        const fd = new FormData();
        fd.append("id", "0");
        fd.append("full_name", name);
        fd.append("mobile_number", mobile);
        fd.append("password", password);
        fd.append("status", document.getElementById("status").value);
        fd.append("apartment_id", aptIdInput.value || "");
        fd.append("apartment_code", aptCodeInput.value || "");
        fd.append("apartment_name", aptNameInput.value || "");
        fd.append("division", divInput.value || "");
        fd.append("division_charge", divChargeInput.value || "0");
        fd.append("init_wallet", walletEnabled ? initWallet : "0");
        fd.append("init_wallet_note", initNote);

        fetch(BASE_URL + "ajax/add-customer-save.php", {
            method: "POST",
            body: fd,
            credentials: "same-origin"
        })
        .then(r => r.json().catch(() => ({
            success: false,
            message: "Unexpected server response."
        })))
        .then(data => {
            if (data.success) {
                const code = data.data && data.data.code ? data.data.code : "";

                showSuccessPopup(
                    data.message || "Customer added successfully.",
                    code
                );

                setLoading(false);
                form.reset();
                walletEnabled = false;
                walletToggle.classList.remove("on");
                walletFields.classList.remove("show");
                walletPreview.textContent = "₹0";

                /* Reset apartment + division */
                aptIdInput.value = "";
                aptCodeInput.value = "";
                aptNameInput.value = "";
                aptDdLabel.textContent = "— Type or select apartment —";
                aptDdLabel.classList.add("placeholder");
                aptDdToggle.classList.remove("has-value");

                divisionsCache = [];
                divInput.value = "";
                divChargeInput.value = 0;
                divDdToggle.disabled = true;
                divDdLabel.textContent = "Select apartment first";
                divDdLabel.classList.add("placeholder");
                divDdToggle.classList.remove("has-value");
                divDdList.innerHTML = "";
                divHint.textContent = "";

            } else {
                showErrorPopup(data.message || "Failed to save customer.");
                setLoading(false);
            }
        })
        .catch(() => {
            showErrorPopup("Unable to connect to server.");
            setLoading(false);
        });
    });

    /* =========================================
       INIT
       ========================================= */
    loadApartments();

})();