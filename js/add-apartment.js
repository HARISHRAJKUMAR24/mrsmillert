/* =========================================================
   MRS MILL@ — ADD APARTMENT UX (Apartment only)
   File: ./js/add-apartment.js
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL =
        (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
            ? window.ADMIN_URL
            : "./";

    /* ========== SUCCESS POPUP ========== */
    const successOverlay = document.getElementById("successOverlay");
    const successText = document.getElementById("successText");
    const successCode = document.getElementById("successCode");

    function showSuccessPopup(message, code) {
        if (!successOverlay) return;
        if (successText) successText.textContent = message || "Saved successfully.";
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
    if (successOverlay) {
        successOverlay.addEventListener("click", (e) => {
            if (e.target === successOverlay) closeSuccessPopup();
        });
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && successOverlay.classList.contains("show")) closeSuccessPopup();
        });
    }

    /* ========== ERROR POPUP ========== */
    let errorOverlay = null;
    let errorText = null;

    function buildErrorPopup() {
        if (errorOverlay) return;
        errorOverlay = document.createElement("div");
        errorOverlay.className = "mm-modal-overlay";
        errorOverlay.setAttribute("aria-hidden", "true");
        errorOverlay.id = "errorOverlay";
        errorOverlay.innerHTML = `
            <div class="mm-modal" role="dialog" aria-modal="true">
                <div class="mm-modal-icon mm-modal-icon-error">
                    <i class="bi bi-exclamation-lg"></i>
                </div>
                <h3 class="mm-modal-title">Oops! Something's missing</h3>
                <p class="mm-modal-text" id="errorText">Please check the form and try again.</p>
                <div class="mm-modal-actions">
                    <button type="button" class="mm-btn mm-btn-danger" id="errorOkBtn">
                        <i class="bi bi-check2"></i> Got it
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(errorOverlay);
        errorText = errorOverlay.querySelector("#errorText");
        errorOverlay.addEventListener("click", (e) => {
            if (e.target === errorOverlay) closeErrorPopup();
        });
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && errorOverlay.classList.contains("show")) closeErrorPopup();
        });
        errorOverlay.querySelector("#errorOkBtn").addEventListener("click", closeErrorPopup);

        if (!document.getElementById("mmErrorStyles")) {
            const style = document.createElement("style");
            style.id = "mmErrorStyles";
            style.textContent = `
                .mm-modal-icon-error { background: #fdecec; color: #b51f2c; }
                .mm-btn-danger { background: #b51f2c; color: #fff; box-shadow: 0 8px 20px rgba(181,31,44,.22); }
                .mm-btn-danger:hover { background: #8e1722; color: #fff; }
            `;
            document.head.appendChild(style);
        }
    }

    function showError(message) {
        buildErrorPopup();
        if (errorText) errorText.textContent = message || "Please check the form and try again.";
        errorOverlay.classList.add("show");
        errorOverlay.setAttribute("aria-hidden", "false");
        const ok = errorOverlay.querySelector("#errorOkBtn");
        if (ok) setTimeout(() => ok.focus(), 60);
    }
    function closeErrorPopup() {
        if (!errorOverlay) return;
        errorOverlay.classList.remove("show");
        errorOverlay.setAttribute("aria-hidden", "true");
    }

    function escapeAttr(str) {
        return String(str || "")
            .replace(/&/g, "&amp;")
            .replace(/"/g, "&quot;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");
    }

    /* ========== APARTMENT FORM ========== */
    const form = document.getElementById("apartmentForm");
    const saveBtn = document.getElementById("saveBtn");
    const saveText = document.getElementById("saveBtnText");
    const divisionRows = document.getElementById("divisionRows");
    const addDivBtn = document.getElementById("addDivisionBtn");
    const emptyState = document.getElementById("divisionsEmpty");

    if (!form) return;

    function setLoading(isLoading) {
        saveBtn.disabled = isLoading;
        saveText.innerHTML = isLoading
            ? '<span class="btn-spinner"></span> Saving...'
            : 'Save Apartment';
    }

    function getFirstVariantCharge() {
        const firstCharge = divisionRows.querySelector(".variant-charge");
        if (!firstCharge) return "";
        const val = firstCharge.value.trim();
        return val === "" ? "" : val;
    }

    function createDivisionRow(data) {
        data = data || {};
        const row = document.createElement("div");
        row.className = "division-row";
        row.innerHTML = `
            <div class="division-field">
                <i class="bi bi-signpost-2"></i>
                <input type="text" class="variant-block" placeholder="Block (Eg: A, B)" maxlength="30" value="${escapeAttr(data.block)}">
            </div>
            <div class="division-field">
                <i class="bi bi-door-closed"></i>
                <input type="text" class="variant-door" placeholder="Door No (Eg: 101)" maxlength="30" value="${escapeAttr(data.door_no)}">
            </div>
            <div class="division-field">
                <i class="bi bi-layers"></i>
                <input type="text" class="variant-floor" placeholder="Floor (Eg: 1, G)" maxlength="30" value="${escapeAttr(data.floor)}">
            </div>
            <div class="division-field charge">
                <span class="rupee">₹</span>
                <input type="number" class="variant-charge" placeholder="0.00" min="0" step="0.01" value="${escapeAttr(data.charge)}">
            </div>
            <button type="button" class="remove-division" title="Remove"><i class="bi bi-x-lg"></i></button>
        `;
        row.querySelector(".remove-division").addEventListener("click", () => {
            row.remove();
            refreshEmptyState();
        });
        return row;
    }

    function addDivisionRow(data) {
        data = data || {};
        if (data.charge === undefined || data.charge === null || data.charge === "") {
            data.charge = getFirstVariantCharge();
        }
        divisionRows.appendChild(createDivisionRow(data));
        refreshEmptyState();
    }

    function refreshEmptyState() {
        const count = divisionRows.querySelectorAll(".division-row").length;
        if (emptyState) emptyState.style.display = count === 0 ? "block" : "none";
    }

    if (addDivBtn) {
        addDivBtn.addEventListener("click", () => {
            addDivisionRow();
            const inputs = divisionRows.querySelectorAll(".variant-block");
            if (inputs.length) inputs[inputs.length - 1].focus();
        });
    }

    function collectDivisions() {
        const rows = divisionRows.querySelectorAll(".division-row");
        const out = [];
        const seen = {};
        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const block  = row.querySelector(".variant-block").value.trim();
            const door   = row.querySelector(".variant-door").value.trim();
            const floor  = row.querySelector(".variant-floor").value.trim();
            const charge = row.querySelector(".variant-charge").value.trim();

            if (!block) return { ok: false, message: "Block name is required for variant row " + (i + 1) + "." };
            if (!door)  return { ok: false, message: "Door No is required for variant row " + (i + 1) + "." };
            if (!floor) return { ok: false, message: "Floor is required for variant row " + (i + 1) + "." };
            if (charge === "" || isNaN(charge) || Number(charge) < 0) {
                return { ok: false, message: "Valid delivery charge is required for variant row " + (i + 1) + "." };
            }

            const key = (block + "|" + door + "|" + floor).toLowerCase();
            if (seen[key]) return { ok: false, message: 'Duplicate variant: Block "' + block + '", Door No "' + door + '", Floor "' + floor + '".' };
            seen[key] = true;

            out.push({ block, door_no: door, floor, charge: Number(charge) });
        }
        return { ok: true, divisions: out };
    }

    form.addEventListener("submit", function (e) {
        e.preventDefault();

        const branchId = document.getElementById("branch_id").value.trim();
        const name = document.getElementById("apartment_name").value.trim();
        const address = document.getElementById("apartment_address").value.trim();
        const status = document.getElementById("status").value;

        if (!branchId) return showError("Please select a branch.");
        if (!name) return showError("Apartment name is required.");
        if (!address) return showError("Apartment address is required.");

        const collected = collectDivisions();
        if (!collected.ok) return showError(collected.message);
        if (collected.divisions.length === 0) return showError("Please add at least one variant with a charge.");

        setLoading(true);
        const formData = new FormData();
        formData.append("branch_id", branchId);
        formData.append("apartment_name", name);
        formData.append("apartment_address", address);
        formData.append("status", status);
        formData.append("divisions", JSON.stringify(collected.divisions));

        fetch(BASE_URL + "ajax/add-appartment.php", {
            method: "POST", body: formData, credentials: "same-origin"
        })
            .then(r => r.json().catch(() => ({ success: false, message: "Unexpected server response." })))
            .then(data => {
                if (data.success) {
                    showSuccessPopup(
                        data.message || "Apartment added successfully.",
                        data.data && data.data.code ? data.data.code : ""
                    );
                    setLoading(false);
                    form.reset();
                    divisionRows.innerHTML = "";
                    refreshEmptyState();
                } else {
                    showError(data.message || "Failed to save.");
                    setLoading(false);
                }
            })
            .catch(() => {
                showError("Unable to connect to server.");
                setLoading(false);
            });
    });

    addDivisionRow();
    refreshEmptyState();

})();