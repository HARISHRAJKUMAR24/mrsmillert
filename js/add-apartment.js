/* =========================================================
   MRS MILL@ — ADD APARTMENT UX
   File: ./js/add-apartment.js
   Multi-division rows, each with its own delivery charge
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL =
        (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
            ? window.ADMIN_URL
            : "./";

    const form         = document.getElementById("apartmentForm");
    const alertBox     = document.getElementById("formAlert");
    const saveBtn      = document.getElementById("saveBtn");
    const saveText     = document.getElementById("saveBtnText");

    const divisionRows = document.getElementById("divisionRows");
    const addDivBtn    = document.getElementById("addDivisionBtn");
    const emptyState   = document.getElementById("divisionsEmpty");

    const successOverlay = document.getElementById("successOverlay");
    const successText    = document.getElementById("successText");
    const successCode    = document.getElementById("successCode");

    if (!form) return;

    /* =========================================
       ERROR ALERT
    ========================================= */

    function showError(message) {
        alertBox.className = "alert-box show error";
        alertBox.textContent = message;
        alertBox.scrollIntoView({ behavior: "smooth", block: "center" });
    }

    function clearError() {
        alertBox.className = "alert-box";
        alertBox.textContent = "";
    }

    /* =========================================
       SUCCESS POPUP
    ========================================= */

    function showSuccessPopup(message, code) {

        if (!successOverlay) return;

        if (successText) {
            successText.textContent = message || "Apartment added successfully.";
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

    if (successOverlay) {

        successOverlay.addEventListener("click", function (e) {
            if (e.target === successOverlay) closeSuccessPopup();
        });

        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape" &&
                successOverlay.classList.contains("show")) {
                closeSuccessPopup();
            }
        });
    }

    /* =========================================
       BUTTON STATE
    ========================================= */

    function setLoading(isLoading) {
        saveBtn.disabled = isLoading;
        saveText.innerHTML = isLoading
            ? '<span class="btn-spinner"></span> Saving...'
            : 'Save Apartment';
    }

    /* =========================================
       DIVISION ROW
    ========================================= */

    function createDivisionRow(division, charge) {

        const row = document.createElement("div");
        row.className = "division-row";

        row.innerHTML = `
            <div class="division-field">
                <i class="bi bi-grid-3x3-gap"></i>
                <input type="text"
                       class="division-name"
                       placeholder="Division (Eg: A, B, 101)"
                       maxlength="30"
                       value="${escapeAttr(division || "")}">
            </div>

            <div class="division-field charge">
                <span class="rupee">₹</span>
                <input type="number"
                       class="division-charge"
                       placeholder="0.00"
                       min="0"
                       step="0.01"
                       value="${escapeAttr(charge || "")}">
            </div>

            <button type="button"
                    class="remove-division"
                    title="Remove">
                <i class="bi bi-x-lg"></i>
            </button>
        `;

        row.querySelector(".remove-division")
            .addEventListener("click", function () {
                row.remove();
                refreshEmptyState();
            });

        return row;
    }

    function escapeAttr(str) {
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/"/g, "&quot;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");
    }

    function addDivisionRow(division, charge) {
        divisionRows.appendChild(
            createDivisionRow(division || "", charge || "")
        );
        refreshEmptyState();
    }

    function refreshEmptyState() {
        const count = divisionRows.querySelectorAll(".division-row").length;
        if (emptyState) {
            emptyState.style.display = count === 0 ? "block" : "none";
        }
    }

    if (addDivBtn) {
        addDivBtn.addEventListener("click", function () {
            addDivisionRow();
            const inputs = divisionRows.querySelectorAll(".division-name");
            if (inputs.length) {
                inputs[inputs.length - 1].focus();
            }
        });
    }

    /* =========================================
       COLLECT DIVISIONS
    ========================================= */

    function collectDivisions() {

        const rows = divisionRows.querySelectorAll(".division-row");
        const out  = [];
        const seen = {};

        for (let i = 0; i < rows.length; i++) {

            const row      = rows[i];
            const division = row.querySelector(".division-name").value.trim();
            const charge   = row.querySelector(".division-charge").value.trim();

            if (!division) {
                return { ok: false, message: "Division name is required for row " + (i + 1) + "." };
            }

            if (charge === "" || isNaN(charge) || Number(charge) < 0) {
                return { ok: false, message: "Valid delivery charge is required for row " + (i + 1) + "." };
            }

            const key = division.toLowerCase();
            if (seen[key]) {
                return { ok: false, message: 'Duplicate division "' + division + '".' };
            }
            seen[key] = true;

            out.push({
                division: division,
                charge: Number(charge)
            });
        }

        return { ok: true, divisions: out };
    }

    /* =========================================
       SUBMIT
    ========================================= */

    form.addEventListener("submit", function (e) {

        e.preventDefault();
        clearError();

        const name    = document.getElementById("apartment_name").value.trim();
        const address = document.getElementById("apartment_address").value.trim();
        const status  = document.getElementById("status").value;

        if (!name)    return showError("Apartment name is required.");
        if (!address) return showError("Apartment address is required.");

        const collected = collectDivisions();

        if (!collected.ok) {
            return showError(collected.message);
        }

        if (collected.divisions.length === 0) {
            return showError("Please add at least one division with a charge.");
        }

        setLoading(true);

        const formData = new FormData();
        formData.append("apartment_name", name);
        formData.append("apartment_address", address);
        formData.append("status", status);
        formData.append("divisions", JSON.stringify(collected.divisions));

        fetch(BASE_URL + "ajax/add-appartment.php", {
            method: "POST",
            body: formData,
            credentials: "same-origin"
        })
        .then(r => r.json().catch(() => ({
            success: false,
            message: "Unexpected server response."
        })))
        .then(data => {

            if (data.success) {

                const code = data.data && data.data.code
                    ? data.data.code
                    : "";

                showSuccessPopup(
                    data.message || "Apartment added successfully.",
                    code
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

    /* =========================================
       INIT: start with 1 row
    ========================================= */

    addDivisionRow();
    refreshEmptyState();

})();