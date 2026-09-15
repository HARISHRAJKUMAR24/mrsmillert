/* =========================================================
   MRS MILL@ — PAYMENT SETTINGS UX
   File: ./js/payment-settings.js
   Load + save Razorpay Key ID, Key Secret, UPI ID & Tax
   in ONE submit
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    /* ---------------- DOM ---------------- */

    const form        = document.getElementById("paymentForm");
    const formLoading = document.getElementById("formLoading");
    const saveBtn     = document.getElementById("saveBtn");
    const saveText    = document.getElementById("saveBtnText");

    const keyIdInput     = document.getElementById("razorpay_key_id");
    const keySecretInput = document.getElementById("razorpay_key_secret");
    const upiIdInput     = document.getElementById("upi_id");

    const toggleSecret = document.getElementById("toggleSecret");
    const eyeIcon      = document.getElementById("eyeIcon");

    /* ---- Tax ---- */
    const taxStatusInput    = document.getElementById("tax_status");
    const taxToggleRow      = document.getElementById("taxToggleRow");
    const taxToggleTitle    = document.getElementById("taxToggleTitle");
    const taxToggleDesc     = document.getElementById("taxToggleDesc");
    const taxFields         = document.getElementById("taxFields");
    const taxTypeExclusive  = document.getElementById("tax_type_exclusive");
    const taxTypeInclusive  = document.getElementById("tax_type_inclusive");
    const taxRateInput      = document.getElementById("tax_rate");

    /* ---- Modals ---- */
    const errorOverlay = document.getElementById("errorOverlay");
    const errorTitle   = document.getElementById("errorTitle");
    const errorText    = document.getElementById("errorText");
    const errorOkBtn   = document.getElementById("errorOkBtn");

    const successOverlay = document.getElementById("successOverlay");
    const successText    = document.getElementById("successText");
    const successOkBtn   = document.getElementById("successOkBtn");

    if (!form) return;

    /* ---------------- ERROR / SUCCESS ---------------- */

    function showError(message, title) {
        if (errorTitle) errorTitle.textContent = title || "Oops!";
        if (errorText)  errorText.textContent  = message || "Something went wrong.";
        if (errorOverlay) {
            errorOverlay.classList.add("show");
            errorOverlay.setAttribute("aria-hidden", "false");
        }
    }

    function closeError() {
        if (errorOverlay) {
            errorOverlay.classList.remove("show");
            errorOverlay.setAttribute("aria-hidden", "true");
        }
    }

    function closeSuccess() {
        if (successOverlay) {
            successOverlay.classList.remove("show");
            successOverlay.setAttribute("aria-hidden", "true");
        }
    }

    if (errorOkBtn) errorOkBtn.addEventListener("click", closeError);
    if (successOkBtn) successOkBtn.addEventListener("click", closeSuccess);

    if (errorOverlay) {
        errorOverlay.addEventListener("click", e => {
            if (e.target === errorOverlay) closeError();
        });
        document.addEventListener("keydown", e => {
            if (e.key === "Escape" && errorOverlay.classList.contains("show")) closeError();
        });
    }

    if (successOverlay) {
        successOverlay.addEventListener("click", e => {
            if (e.target === successOverlay) closeSuccess();
        });
    }

    /* ---------------- BUTTON ---------------- */

    function setLoading(isLoading) {
        if (!saveBtn || !saveText) return;
        saveBtn.disabled = isLoading;
        saveText.innerHTML = isLoading
            ? '<span class="btn-spinner"></span> Saving...'
            : 'Save Payment Settings';
    }

    /* ---------------- SHOW / HIDE SECRET ---------------- */

    if (toggleSecret && keySecretInput && eyeIcon) {
        toggleSecret.addEventListener("click", function () {
            const isPwd = keySecretInput.type === "password";
            keySecretInput.type = isPwd ? "text" : "password";
            eyeIcon.className = isPwd ? "bi bi-eye-slash" : "bi bi-eye";
            toggleSecret.setAttribute("aria-label", isPwd ? "Hide secret" : "Show secret");
        });
    }

    /* ---------------- TAX: ENABLE / DISABLE ---------------- */

    function applyTaxUIState() {
        if (!taxStatusInput) return;

        const enabled = taxStatusInput.checked;

        /* Toggle row visual */
        if (taxToggleRow) {
            taxToggleRow.classList.toggle("is-enabled", enabled);
        }

        /* Title + description */
        if (taxToggleTitle) {
            taxToggleTitle.textContent = enabled ? "Tax Enabled" : "Tax Disabled";
        }
        if (taxToggleDesc) {
            taxToggleDesc.textContent = enabled
                ? "Tax will be calculated on all orders."
                : "Tax will not be applied to any order.";
        }

        /* Fields enable/disable */
        if (taxFields) {
            taxFields.classList.toggle("is-disabled", !enabled);
        }

        /* When disabled → force rate to 0 and uncheck types */
        if (!enabled) {
            if (taxRateInput) taxRateInput.value = "0";
            if (taxTypeExclusive) taxTypeExclusive.checked = false;
            if (taxTypeInclusive) taxTypeInclusive.checked = false;
        }
    }

    if (taxStatusInput) {
        taxStatusInput.addEventListener("change", applyTaxUIState);
    }

    /* ---------------- LOAD ---------------- */

    function loadSettings() {

        fetch(BASE_URL + "ajax/payment-settings-get.php", {
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
                        (data.message || "Settings not found.") +
                        '</div>';
                }
                return;
            }

            /* Razorpay */
            keyIdInput.value     = data.data.razorpay_key_id || "";
            keySecretInput.value = data.data.razorpay_key_secret || "";

            /* UPI */
            if (upiIdInput) upiIdInput.value = data.data.upi_id || "";

            /* Tax */
            const taxEnabled = String(data.data.tax_status) === "1";
            if (taxStatusInput) taxStatusInput.checked = taxEnabled;

            const taxType = (data.data.tax_type || "exclusive").toLowerCase();
            if (taxType === "inclusive" && taxTypeInclusive) {
                taxTypeInclusive.checked = true;
            } else if (taxTypeExclusive) {
                taxTypeExclusive.checked = true;
            }

            if (taxRateInput) {
                const rate = parseFloat(data.data.tax_rate);
                taxRateInput.value = isNaN(rate) ? "0" : String(rate);
            }

            /* Apply UI state after populating */
            applyTaxUIState();

            if (formLoading) formLoading.style.display = "none";
            form.style.display = "";

        })
        .catch(() => {
            if (formLoading) {
                formLoading.innerHTML =
                    '<div style="color:#d71920;font-size:12px;">Unable to connect to server.</div>';
            }
        });
    }

    /* ---------------- SUBMIT (SINGLE SAVE) ---------------- */

    form.addEventListener("submit", function (e) {

        e.preventDefault();

        const keyId     = keyIdInput.value.trim();
        const keySecret = keySecretInput.value.trim();
        const upiId     = upiIdInput ? upiIdInput.value.trim() : "";

        /* ---- Razorpay Key ID ---- */
        if (!keyId) {
            return showError("Razorpay Key ID is required.", "Missing Key ID");
        }
        if (!/^rzp_/i.test(keyId)) {
            return showError('Razorpay Key ID must start with "rzp_".', "Invalid Key ID");
        }
        if (keyId.length < 10) {
            return showError("Razorpay Key ID looks too short.", "Invalid Key ID");
        }

        /* ---- Razorpay Key Secret ---- */
        if (!keySecret) {
            return showError("Razorpay Key Secret is required.", "Missing Key Secret");
        }
        if (keySecret.length < 10) {
            return showError("Razorpay Key Secret looks too short.", "Invalid Key Secret");
        }

        /* ---- UPI ID ---- */
        if (!upiId) {
            return showError("UPI ID is required.", "Missing UPI ID");
        }
        if (!/^[a-zA-Z0-9._-]{2,}@[a-zA-Z]{2,}$/.test(upiId)) {
            return showError(
                "UPI ID looks invalid. Example: yourname@okhdfcbank",
                "Invalid UPI ID"
            );
        }

        /* ---- Tax ---- */
        const taxEnabled = taxStatusInput ? taxStatusInput.checked : false;
        let taxType = "";
        let taxRate = "0";

        if (taxEnabled) {

            /* Tax type required when enabled */
            if (taxTypeInclusive && taxTypeInclusive.checked) {
                taxType = "inclusive";
            } else if (taxTypeExclusive && taxTypeExclusive.checked) {
                taxType = "exclusive";
            } else {
                return showError(
                    "Please choose Inclusive or Exclusive tax type.",
                    "Missing Tax Type"
                );
            }

            /* Tax rate required + valid when enabled */
            const rateStr = taxRateInput ? taxRateInput.value.trim() : "";
            const rateNum = parseFloat(rateStr);

            if (rateStr === "" || isNaN(rateNum)) {
                return showError("Tax Rate is required when Tax is enabled.", "Missing Tax Rate");
            }
            if (rateNum < 0 || rateNum > 100) {
                return showError("Tax Rate must be between 0 and 100.", "Invalid Tax Rate");
            }

            taxRate = String(rateNum);

        } else {
            /* Disabled → force 0 */
            taxType = "";
            taxRate = "0";
        }

        setLoading(true);

        const formData = new FormData();
        formData.append("razorpay_key_id", keyId);
        formData.append("razorpay_key_secret", keySecret);
        formData.append("upi_id", upiId);
        formData.append("tax_status", taxEnabled ? "1" : "0");
        formData.append("tax_type", taxType);
        formData.append("tax_rate", taxRate);

        fetch(BASE_URL + "ajax/update-payment-settings.php", {
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

                if (successText) {
                    successText.textContent =
                        data.message || "Payment settings saved successfully.";
                }

                if (successOverlay) {
                    successOverlay.classList.add("show");
                    successOverlay.setAttribute("aria-hidden", "false");
                }

                setLoading(false);

            } else {
                showError(data.message || "Failed to save payment settings.", "Save failed");
                setLoading(false);
            }
        })
        .catch(() => {
            showError("Unable to connect to server.", "Network error");
            setLoading(false);
        });
    });

    /* ---------------- INIT ---------------- */

    applyTaxUIState();
    loadSettings();

})();