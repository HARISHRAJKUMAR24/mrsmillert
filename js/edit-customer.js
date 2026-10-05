/* =========================================================
   MRS MILL@ — EDIT CUSTOMER
   File: ./js/edit-customer.js
   Same toast popup style as Add Customer (centered, green ✓ / red ✕)
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    const CUSTOMER_ID = window.CUSTOMER_ID || 0;

    /* Form */
    const form          = document.getElementById("customerForm");
    const saveBtn       = document.getElementById("saveBtn");
    const saveBtnText   = document.getElementById("saveBtnText");

    const nameInput     = document.getElementById("full_name");
    const mobileInput   = document.getElementById("mobile_number");
    const mobileHelp    = document.getElementById("mobileHelp");

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
    const divDdWrap      = document.getElementById("acDivDdWrap");
    const divDdToggle    = document.getElementById("acDivDdToggle");
    const divDdLabel     = document.getElementById("acDivDdLabel");
    const divDdSearch    = document.getElementById("acDivDdSearch");
    const divDdList      = document.getElementById("acDivDdList");
    const divInput       = document.getElementById("acDivision");
    const divChargeInput = document.getElementById("acDivisionCharge");
    const divHint        = document.getElementById("acDivisionHint");

    /* Wallet */
    const walletDisplay  = document.getElementById("walletDisplay");
    const wForm          = document.getElementById("walletForm");
    const wTxnType       = document.getElementById("wTxnType");
    const wAmount        = document.getElementById("wAmount");
    const wNote          = document.getElementById("wNote");
    const wSubmitBtn     = document.getElementById("walletSubmitBtn");
    const wSubmitText    = document.getElementById("walletSubmitText");
    const wRefreshBtn    = document.getElementById("walletRefreshHistory");
    const walletHistory  = document.getElementById("walletHistory");
    const historyPagination = document.getElementById("historyPagination");
    const sumCredit      = document.getElementById("sumCredit");
    const sumDebit       = document.getElementById("sumDebit");
    const sumEntries     = document.getElementById("sumEntries");

    /* Delete popup */
    const deleteOverlay    = document.getElementById("deleteOverlay");
    const deleteCancelBtn  = document.getElementById("deleteCancelBtn");
    const deleteConfirmBtn = document.getElementById("deleteConfirmBtn");
    const deleteBtn        = document.getElementById("deleteCustomerBtn");

    /* Success / error popups */
    const successOverlay = document.getElementById("successOverlay");
    const successText    = document.getElementById("successText");
    const successOkBtn   = document.getElementById("successOkBtn");

    const errorOverlay   = document.getElementById("errorOverlay");
    const errorText      = document.getElementById("errorText");
    const errorOkBtn     = document.getElementById("errorOkBtn");

    if (!form) return;

    let apartmentsCache = [];
    let divisionsCache  = [];
    let currentBalance  = 0;
    let historyLoaded   = false;

    let hxCurrentPage = 1;
    let hxPerPage     = 10;
    let hxFilter      = "";
    let hxTotalPages  = 1;

    try {
        const raw = (walletDisplay?.textContent || "").replace(/[^\d.]/g, "");
        currentBalance = parseFloat(raw) || 0;
    } catch (e) { currentBalance = 0; }

    /* =========================================
       HELPERS
       ========================================= */
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

    /* =========================================
       TOAST POPUP — same style as Add Customer
       ========================================= */
    let successTimer = null;

    function showSuccessPopup(message) {
        if (!successOverlay) return;

        if (successText) successText.textContent = message || "Success.";

        successOverlay.classList.add("show");
        successOverlay.setAttribute("aria-hidden", "false");

        /* Auto-dismiss after 2.4s */
        clearTimeout(successTimer);
        successTimer = setTimeout(closeSuccessPopup, 2400);
    }

    function closeSuccessPopup() {
        if (!successOverlay) return;
        successOverlay.classList.remove("show");
        successOverlay.setAttribute("aria-hidden", "true");
        clearTimeout(successTimer);
    }

    function showErrorPopup(message) {
        if (!errorOverlay) return;

        if (errorText) errorText.textContent = message || "Please check the form and try again.";

        errorOverlay.classList.add("show");
        errorOverlay.setAttribute("aria-hidden", "false");

        if (errorOkBtn) setTimeout(() => errorOkBtn.focus(), 60);
    }

    function closeErrorPopup() {
        if (!errorOverlay) return;
        errorOverlay.classList.remove("show");
        errorOverlay.setAttribute("aria-hidden", "true");
    }

    successOkBtn?.addEventListener("click", closeSuccessPopup);
    errorOkBtn?.addEventListener("click", closeErrorPopup);

    successOverlay?.addEventListener("click", e => {
        if (e.target === successOverlay) closeSuccessPopup();
    });
    errorOverlay?.addEventListener("click", e => {
        if (e.target === errorOverlay) closeErrorPopup();
    });

    /* =========================================
       TABS
       ========================================= */
    document.querySelectorAll(".tab-btn").forEach(btn => {
        btn.addEventListener("click", function () {
            const tab = this.dataset.tab;

            document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
            document.querySelectorAll(".tab-panel").forEach(p => p.classList.remove("active"));

            this.classList.add("active");
            const panel = document.getElementById("panel-" + tab);
            if (panel) panel.classList.add("active");

            if (tab === "wallet" && !historyLoaded) {
                historyLoaded = true;
                loadWalletHistory();
            }
        });
    });

    /* =========================================
       DELETE POPUP
       ========================================= */
    function openDeletePopup() {
        deleteOverlay?.classList.add("show");
        deleteOverlay?.setAttribute("aria-hidden", "false");
    }
    function closeDeletePopup() {
        deleteOverlay?.classList.remove("show");
        deleteOverlay?.setAttribute("aria-hidden", "true");
    }

    deleteOverlay?.addEventListener("click", e => {
        if (e.target === deleteOverlay) closeDeletePopup();
    });
    deleteCancelBtn?.addEventListener("click", closeDeletePopup);

    document.addEventListener("keydown", e => {
        if (e.key !== "Escape") return;
        if (successOverlay?.classList.contains("show")) closeSuccessPopup();
        if (errorOverlay?.classList.contains("show")) closeErrorPopup();
        if (deleteOverlay?.classList.contains("show")) closeDeletePopup();
    });

    /* =========================================
       CUSTOMER FORM STATE
       ========================================= */
    function setCustomerLoading(isLoading) {
        if (!saveBtn) return;
        saveBtn.disabled = isLoading;
        saveBtnText.innerHTML = isLoading
            ? '<span class="btn-spinner"></span> Saving...'
            : 'Update Customer';
    }

    /* =========================================
       HINTS
       ========================================= */
    mobileInput?.addEventListener("input", function () {
        this.value = this.value.replace(/[^0-9]/g, "").slice(0, 15);
        const m = this.value.trim();
        if (!mobileHelp) return;
        if (m.length < 10) {
            mobileHelp.textContent = "Needs at least 10 digits.";
            mobileHelp.className = "field-help";
        } else if (m.length > 15) {
            mobileHelp.textContent = "Max 15 digits.";
            mobileHelp.className = "field-help error";
        } else {
            mobileHelp.textContent = "Looks good.";
            mobileHelp.className = "field-help success";
        }
    });

    passwordInput?.addEventListener("input", function () {
        const v = this.value;
        if (!passwordHelp) return;
        if (!v) {
            passwordHelp.textContent = "Leave blank to keep the current password.";
            passwordHelp.className = "field-help";
        } else if (v.length < 3) {
            passwordHelp.textContent = "Too short — minimum 3 characters.";
            passwordHelp.className = "field-help error";
        } else {
            passwordHelp.textContent = "New password will be set on save.";
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

                const curId = Number(aptIdInput.value || 0);
                if (curId > 0) {
                    const apt = apartmentsCache.find(a => Number(a.id) === curId);
                    if (apt) {
                        divisionsCache = apt.divisions || [];
                        if (divisionsCache.length) divDdToggle.disabled = false;
                    }
                }
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
    function closeAptDd() { aptDdWrap.classList.remove("open"); }

    aptDdToggle?.addEventListener("click", function (e) {
        e.stopPropagation();
        if (aptDdWrap.classList.contains("open")) closeAptDd();
        else openAptDd();
    });

    aptDdSearch?.addEventListener("input", function () { renderAptOptions(this.value); });

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
        if (apt) populateDivisions(apt, "");
    });

    /* =========================================
       DIVISION DROPDOWN
       ========================================= */
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
            return;
        }
        divisionsCache = apt.divisions;
        divDdToggle.disabled = false;

        if (preselect) {
            const match = divisionsCache.find(d => String(d.division) === String(preselect));
            if (match) {
                divInput.value = String(match.division);
                divChargeInput.value = Number(match.charge);
                divDdLabel.textContent = "Division " + match.division + " · ₹" + Math.round(Number(match.charge));
                divDdLabel.classList.remove("placeholder");
                divDdToggle.classList.add("has-value");
                divHint.textContent = "Charge: ₹" + Math.round(Number(match.charge));
                return;
            }
        }

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
    function closeDivDd() { divDdWrap.classList.remove("open"); }

    divDdToggle?.addEventListener("click", function (e) {
        e.stopPropagation();
        if (divDdToggle.disabled) return;
        if (divDdWrap.classList.contains("open")) closeDivDd();
        else openDivDd();
    });

    divDdSearch?.addEventListener("input", function () { renderDivOptions(this.value); });

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
       SAVE CUSTOMER → TOAST
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
        if (password !== "" && password.length < 3) {
            return showErrorPopup("New password must be at least 3 characters.");
        }

        setCustomerLoading(true);

        const fd = new FormData();
        fd.append("id", String(CUSTOMER_ID));
        fd.append("full_name", name);
        fd.append("mobile_number", mobile);
        fd.append("password", password);
        fd.append("status", document.getElementById("status").value);
        fd.append("apartment_id", aptIdInput.value || "");
        fd.append("apartment_code", aptCodeInput.value || "");
        fd.append("apartment_name", aptNameInput.value || "");
        fd.append("division", divInput.value || "");
        fd.append("division_charge", divChargeInput.value || "0");

        fetch(BASE_URL + "ajax/add-customer-save.php", {
            method: "POST",
            body: fd,
            credentials: "same-origin"
        })
        .then(r => r.json().catch(() => ({ success: false, message: "Unexpected server response." })))
        .then(data => {
            setCustomerLoading(false);
            if (data.success) {
                showSuccessPopup(data.message || "Customer updated successfully.");
            } else {
                showErrorPopup(data.message || "Failed to update customer.");
            }
        })
        .catch(() => {
            setCustomerLoading(false);
            showErrorPopup("Unable to connect to server.");
        });
    });

    /* =========================================
       DELETE
       ========================================= */
    deleteBtn?.addEventListener("click", openDeletePopup);

    deleteConfirmBtn?.addEventListener("click", function () {
        deleteConfirmBtn.disabled = true;
        deleteConfirmBtn.innerHTML = '<span class="btn-spinner"></span> Deleting...';

        const fd = new FormData();
        fd.append("id", String(CUSTOMER_ID));

        fetch(BASE_URL + "ajax/customer-delete.php", {
            method: "POST",
            body: fd,
            credentials: "same-origin"
        })
        .then(r => r.json().catch(() => null))
        .then(res => {
            if (!res || !res.success) {
                closeDeletePopup();
                showErrorPopup((res && res.message) || "Delete failed.");
                deleteConfirmBtn.disabled = false;
                deleteConfirmBtn.innerHTML = '<i class="bi bi-trash"></i> Delete';
                return;
            }
            window.location.href = "customers.php";
        })
        .catch(() => {
            closeDeletePopup();
            showErrorPopup("Unable to connect.");
            deleteConfirmBtn.disabled = false;
            deleteConfirmBtn.innerHTML = '<i class="bi bi-trash"></i> Delete';
        });
    });

    /* =========================================
       WALLET TABS + PRESETS
       ========================================= */
    document.querySelectorAll(".wallet-tab").forEach(tab => {
        tab.addEventListener("click", function () {
            setWalletTab(this.dataset.tab);
        });
    });

    function setWalletTab(tab) {
        wTxnType.value = tab;
        document.querySelectorAll(".wallet-tab").forEach(t => {
            t.classList.toggle("active", t.dataset.tab === tab);
        });
        wSubmitText.textContent = "Save Wallet";
    }

    document.querySelectorAll(".wallet-preset").forEach(p => {
        p.addEventListener("click", function () {
            wAmount.value = this.dataset.amt;
            wAmount.focus();
        });
    });

    /* =========================================
       SAVE WALLET → TOAST
       ========================================= */
    wForm?.addEventListener("submit", function (e) {
        e.preventDefault();

        const type = wTxnType.value;
        const amt = parseFloat(wAmount.value || "0");
        const note = wNote.value.trim();

        if (!amt || amt <= 0) {
            return showErrorPopup("Enter a valid amount.");
        }

        wSubmitBtn.disabled = true;
        wSubmitText.innerHTML = '<span class="btn-spinner"></span> Saving...';

        const fd = new FormData();
        fd.append("customer_id", String(CUSTOMER_ID));
        fd.append("txn_type", type);
        fd.append("amount", amt);
        fd.append("note", note);

        fetch(BASE_URL + "ajax/wallet-update.php", {
            method: "POST",
            body: fd,
            credentials: "same-origin"
        })
        .then(r => r.json().catch(() => null))
        .then(res => {
            wSubmitBtn.disabled = false;
            wSubmitText.innerHTML = '<i class="bi bi-check-lg"></i> Save Wallet';

            if (!res || !res.success) {
                showErrorPopup((res && res.message) || "Wallet update failed.");
                return;
            }

            if (res.data && typeof res.data.balance_after !== "undefined") {
                currentBalance = Number(res.data.balance_after);
                walletDisplay.textContent = money(currentBalance);
            }

            wAmount.value = "";
            wNote.value = "";

            showSuccessPopup(res.message || "Wallet updated successfully.");

            hxCurrentPage = 1;
            historyLoaded = true;
            loadWalletHistory();
        })
        .catch(() => {
            wSubmitBtn.disabled = false;
            wSubmitText.innerHTML = '<i class="bi bi-check-lg"></i> Save Wallet';
            showErrorPopup("Unable to connect.");
        });
    });

    wRefreshBtn?.addEventListener("click", () => {
        hxCurrentPage = 1;
        loadWalletHistory();
    });

    /* =========================================
       WALLET HISTORY
       ========================================= */
    function loadWalletHistory() {
        if (!walletHistory) return;

        walletHistory.innerHTML = `
            <div class="history-empty">
                <i class="bi bi-hourglass-split"></i>
                <h4>Loading transactions…</h4>
                <p>Please wait</p>
            </div>`;

        if (historyPagination) historyPagination.innerHTML = "";

        const params = new URLSearchParams();
        params.set("customer_id", CUSTOMER_ID);
        params.set("page", hxCurrentPage);
        params.set("per_page", hxPerPage);
        if (hxFilter) params.set("filter", hxFilter);

        fetch(BASE_URL + "ajax/wallet-history.php?" + params.toString(), {
            credentials: "same-origin"
        })
        .then(r => r.json().catch(() => null))
        .then(res => {
            if (!res || !res.success) {
                walletHistory.innerHTML = `
                    <div class="history-empty">
                        <i class="bi bi-exclamation-triangle"></i>
                        <h4>Failed to load</h4>
                        <p>${esc((res && res.message) || "Please try again.")}</p>
                    </div>`;
                return;
            }

            let rows         = [];
            let totalCredit  = 0;
            let totalDebit   = 0;
            let totalEntries = 0;
            let totalPages   = 1;

            if (Array.isArray(res.data)) {
                rows = res.data;
                rows.forEach(t => {
                    if (t.txn_type === "credit") totalCredit += Number(t.amount) || 0;
                    else                          totalDebit  += Number(t.amount) || 0;
                });
                totalEntries = rows.length;
                totalPages   = 1;
            } else if (res.data && typeof res.data === "object") {
                rows         = Array.isArray(res.data.transactions) ? res.data.transactions : [];
                totalCredit  = Number(res.data.total_credit) || 0;
                totalDebit   = Number(res.data.total_debit)  || 0;
                totalEntries = Number(res.data.total_entries) || Number(res.data.total_count) || 0;
                totalPages   = Number(res.data.total_pages)   || 1;
            }

            hxTotalPages = totalPages;

            if (sumCredit)  sumCredit.textContent  = "+" + money(totalCredit);
            if (sumDebit)   sumDebit.textContent   = "−" + money(totalDebit);
            if (sumEntries) sumEntries.textContent = totalEntries;

            if (rows.length === 0) {
                walletHistory.innerHTML = `
                    <div class="history-empty">
                        <i class="bi bi-inbox"></i>
                        <h4>No transactions yet</h4>
                        <p>Start by adding money to the wallet.</p>
                    </div>`;
                if (historyPagination) historyPagination.innerHTML = "";
                return;
            }

            walletHistory.innerHTML = `
                <table class="wallet-history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Balance</th>
                            <th>Note</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows.map(t => {
                            const isCredit = t.txn_type === "credit";
                            return `
                                <tr>
                                    <td>
                                        <div style="font-weight:700;color:#302923;font-size:11px;">
                                            ${esc(fmtDate(t.created_at))}
                                        </div>
                                        <div style="font-size:9px;color:#948c82;margin-top:2px;">
                                            ${esc(t.txn_code || "")}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="wallet-txn-badge ${isCredit ? 'credit' : 'debit'}">
                                            <i class="bi bi-${isCredit ? 'arrow-down' : 'arrow-up'}"></i>
                                            ${isCredit ? 'Credit' : 'Debit'}
                                        </span>
                                    </td>
                                    <td class="${isCredit ? 'wallet-amount-credit' : 'wallet-amount-debit'}">
                                        ${isCredit ? '+' : '−'} ${money(t.amount)}
                                    </td>
                                    <td class="wallet-balance-cell">${money(t.balance_after)}</td>
                                    <td>
                                        <div style="font-size:11px;color:#4c4640;">${esc(t.note || "—")}</div>
                                        <div style="font-size:9px;color:#948c82;margin-top:2px;">${esc(t.source || "")}</div>
                                    </td>
                                    <td>
                                        <div style="font-size:11px;font-weight:700;color:#4c4640;">
                                            ${esc(t.created_by_name || "—")}
                                        </div>
                                    </td>
                                </tr>`;
                        }).join("")}
                    </tbody>
                </table>`;

            renderHistoryPagination();
        })
        .catch(() => {
            walletHistory.innerHTML = `
                <div class="history-empty">
                    <i class="bi bi-wifi-off"></i>
                    <h4>Unable to connect</h4>
                    <p>Please check your network.</p>
                </div>`;
        });
    }

    /* =========================================
       HISTORY PAGINATION
       ========================================= */
    function renderHistoryPagination() {
        if (!historyPagination) return;

        if (hxTotalPages <= 1) {
            historyPagination.innerHTML = "";
            return;
        }

        const pages = getPageList(hxCurrentPage, hxTotalPages);

        const btnHtml = (page, label, disabled, active) => `
            <button type="button" class="hx-page-btn ${active ? 'active' : ''}"
                    data-page="${page}" ${disabled ? 'disabled' : ''}>
                ${label}
            </button>`;

        const html = [];
        html.push(btnHtml(hxCurrentPage - 1, '<i class="bi bi-chevron-left"></i>', hxCurrentPage === 1, false));

        pages.forEach(p => {
            if (p === "...") html.push('<span class="hx-page-ellipsis">…</span>');
            else html.push(btnHtml(p, p, false, p === hxCurrentPage));
        });

        html.push(btnHtml(hxCurrentPage + 1, '<i class="bi bi-chevron-right"></i>', hxCurrentPage === hxTotalPages, false));

        historyPagination.innerHTML = `
            <div class="hx-pagination">
                <div class="hx-pagination-info">
                    Page <strong>${hxCurrentPage}</strong> of <strong>${hxTotalPages}</strong>
                </div>
                <div class="hx-pagination-controls">
                    ${html.join("")}
                </div>
            </div>`;
    }

    function getPageList(current, total) {
        const delta = 1;
        const range = [];
        const out   = [];

        for (let i = 1; i <= total; i++) {
            if (i === 1 || i === total || (i >= current - delta && i <= current + delta)) {
                range.push(i);
            }
        }

        let prev = 0;
        range.forEach(i => {
            if (prev && i - prev > 1) out.push("...");
            out.push(i);
            prev = i;
        });

        return out;
    }

    historyPagination?.addEventListener("click", function (e) {
        const btn = e.target.closest(".hx-page-btn");
        if (!btn || btn.disabled) return;

        const page = Number(btn.dataset.page);
        if (!page || page === hxCurrentPage) return;
        if (page < 1 || page > hxTotalPages) return;

        hxCurrentPage = page;
        loadWalletHistory();
    });

    /* =========================================
       INIT
       ========================================= */
    loadApartments();

})();