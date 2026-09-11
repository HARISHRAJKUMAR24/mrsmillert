/* =========================================================
   MRS MILL@ — EDIT APARTMENT UX
   File: ./js/edit-apartment.js
   Loads apartment by id/code, edits divisions, saves via AJAX
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL =
        (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
            ? window.ADMIN_URL
            : "./";

    const APARTMENT_ID = Number(window.APARTMENT_ID || 0);
    const APARTMENT_CODE = String(window.APARTMENT_CODE || "");

    /* =========================================
       DOM
    ========================================= */

    const form = document.getElementById("apartmentForm");
    const formLoading = document.getElementById("formLoading");
    const alertBox = document.getElementById("formAlert");
    const saveBtn = document.getElementById("saveBtn");
    const saveText = document.getElementById("saveBtnText");
    const aptCodeBadge = document.getElementById("aptCodeBadge");

    const divisionRows = document.getElementById("divisionRows");
    const addDivBtn = document.getElementById("addDivisionBtn");
    const emptyState = document.getElementById("divisionsEmpty");

    const successOverlay = document.getElementById("successOverlay");
    const successText = document.getElementById("successText");
    const successCode = document.getElementById("successCode");
    const editAgainBtn = document.getElementById("editAgainBtn");

    if (!form) return;

    /* =========================================
       ALERT
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
            successText.textContent = message || "Apartment updated successfully.";
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
                successOverlay.classList.contains("show")) closeSuccessPopup();
        });
    }

    if (editAgainBtn) {
        editAgainBtn.addEventListener("click", function (e) {
            e.preventDefault();
            closeSuccessPopup();
        });
    }

    /* =========================================
       BUTTON STATE
    ========================================= */

    function setLoading(isLoading) {
        saveBtn.disabled = isLoading;
        saveText.innerHTML = isLoading
            ? '<span class="btn-spinner"></span> Updating...'
            : 'Update Apartment';
    }

    /* =========================================
       DIVISION ROW
    ========================================= */

    function escapeAttr(str) {
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/"/g, "&quot;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");
    }

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
            if (inputs.length) inputs[inputs.length - 1].focus();
        });
    }

    /* =========================================
       COLLECT DIVISIONS
    ========================================= */

    function collectDivisions() {

        const rows = divisionRows.querySelectorAll(".division-row");
        const out = [];
        const seen = {};

        for (let i = 0; i < rows.length; i++) {

            const row = rows[i];
            const division = row.querySelector(".division-name").value.trim();
            const charge = row.querySelector(".division-charge").value.trim();

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

            out.push({ division: division, charge: Number(charge) });
        }

        return { ok: true, divisions: out };
    }

    /* =========================================
       LOAD APARTMENT
    ========================================= */

    function loadApartment() {

        const params = APARTMENT_ID > 0
            ? "id=" + encodeURIComponent(APARTMENT_ID)
            : "code=" + encodeURIComponent(APARTMENT_CODE);

        fetch(BASE_URL + "ajax/get-apartment.php?" + params, {
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => ({
                success: false,
                message: "Unexpected server response."
            })))
            .then(data => {

                if (!data.success || !data.data) {

                    if (formLoading) {
                        formLoading.innerHTML =
                            '<div style="color:#d71920;font-size:12px;">' +
                            (data.message || "Apartment not found.") +
                            '</div>';
                    }
                    return;
                }

                const apt = data.data;

                document.getElementById("apartment_id").value = apt.id;
                document.getElementById("apartment_name").value = apt.apartment_name || "";
                document.getElementById("apartment_address").value = apt.apartment_address || "";
                document.getElementById("status").value = String(apt.status ?? 1);

                if (aptCodeBadge) {
                    aptCodeBadge.textContent = "#" + (apt.apartment_code || "");
                }

                // fill divisions
                divisionRows.innerHTML = "";
                const divs = Array.isArray(apt.divisions) ? apt.divisions : [];

                if (divs.length === 0) {
                    addDivisionRow();
                } else {
                    divs.forEach(function (d) {
                        addDivisionRow(d.division, d.charge);
                    });
                }

                refreshEmptyState();

                if (formLoading) formLoading.style.display = "none";
                form.style.display = "";

            })
            .catch(() => {
                if (formLoading) {
                    formLoading.innerHTML =
                        '<div style="color:#d71920;font-size:12px;">' +
                        'Unable to connect to server.' +
                        '</div>';
                }
            });
    }

    /* =========================================
       SUBMIT
    ========================================= */

    form.addEventListener("submit", function (e) {

        e.preventDefault();
        clearError();

        const id = Number(document.getElementById("apartment_id").value || 0);
        const name = document.getElementById("apartment_name").value.trim();
        const address = document.getElementById("apartment_address").value.trim();
        const status = document.getElementById("status").value;

        if (id <= 0) return showError("Invalid apartment ID.");
        if (!name) return showError("Apartment name is required.");
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
        formData.append("id", id);
        formData.append("apartment_name", name);
        formData.append("apartment_address", address);
        formData.append("status", status);
        formData.append("divisions", JSON.stringify(collected.divisions));

        fetch(BASE_URL + "ajax/update-apartment.php", {
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
                        data.message || "Apartment updated successfully.",
                        code
                    );

                    setLoading(false);

                } else {

                    showError(data.message || "Failed to update.");
                    setLoading(false);
                }
            })
            .catch(() => {
                showError("Unable to connect to server.");
                setLoading(false);
            });
    });

    /* =========================================
       INIT
    ========================================= */

    loadApartment();

})();