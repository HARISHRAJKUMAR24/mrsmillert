/* =========================================================
   MRS MILL@ — ADD DELIVERY BOY
   File: ./js/add-delivery-boy.js
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL =
        (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
            ? window.ADMIN_URL
            : "./";

    const form       = document.getElementById("boyForm");
    const saveBtn    = document.getElementById("saveBtn");
    const saveText   = document.getElementById("saveBtnText");

    const pwdInput   = document.getElementById("password");
    const pwdToggle  = document.getElementById("pwdToggle");
    const pwdEyeIcon = document.getElementById("pwdEyeIcon");

    const successOverlay = document.getElementById("successOverlay");
    const successText    = document.getElementById("successText");
    const successCode    = document.getElementById("successCode");

    const errorOverlay = document.getElementById("errorOverlay");
    const errorText    = document.getElementById("errorText");
    const errorOkBtn   = document.getElementById("errorOkBtn");

    if (!form) return;

    /* =========================================
       PASSWORD TOGGLE
    ========================================= */
    if (pwdToggle && pwdInput && pwdEyeIcon) {
        pwdToggle.addEventListener("click", function () {
            const isPwd = pwdInput.type === "password";
            pwdInput.type = isPwd ? "text" : "password";
            pwdEyeIcon.className = isPwd ? "bi bi-eye-slash" : "bi bi-eye";
        });
    }

    /* =========================================
       LOADING
    ========================================= */
    function setLoading(isLoading) {
        saveBtn.disabled = isLoading;
        saveText.innerHTML = isLoading
            ? '<span class="btn-spinner"></span> Saving...'
            : 'Save Delivery Boy';
    }

    /* =========================================
       POPUPS
    ========================================= */
    function showError(msg) {
        if (errorText) errorText.textContent = msg || "Please check the form.";
        if (errorOverlay) {
            errorOverlay.classList.add("show");
            errorOverlay.setAttribute("aria-hidden", "false");
            setTimeout(() => errorOkBtn && errorOkBtn.focus(), 60);
        }
    }

    function showSuccess(msg, code) {
        if (successText) successText.textContent = msg || "Saved successfully.";

        if (successCode) {
            if (code) {
                successCode.textContent = "CODE: " + code;
                successCode.style.display = "inline-block";
            } else {
                successCode.style.display = "none";
            }
        }

        if (successOverlay) {
            successOverlay.classList.add("show");
            successOverlay.setAttribute("aria-hidden", "false");
        }
    }

    /* close error on click / esc */
    if (errorOverlay) {
        errorOverlay.addEventListener("click", e => {
            if (e.target === errorOverlay) {
                errorOverlay.classList.remove("show");
            }
        });
    }
    if (errorOkBtn) {
        errorOkBtn.addEventListener("click", () => {
            errorOverlay.classList.remove("show");
        });
    }
    document.addEventListener("keydown", e => {
        if (e.key === "Escape" && errorOverlay?.classList.contains("show")) {
            errorOverlay.classList.remove("show");
        }
    });

    /* =========================================
       SUBMIT
    ========================================= */
    form.addEventListener("submit", function (e) {

        e.preventDefault();

        const full_name    = document.getElementById("full_name").value.trim();
        const mobile       = document.getElementById("mobile_number").value.trim();
        const email        = document.getElementById("email_address").value.trim();
        const branchId     = document.getElementById("branch_id").value.trim();
        const password     = pwdInput.value.trim();
        const status       = document.getElementById("status").value;

        if (!full_name)   return showError("Full name is required.");
        if (full_name.length < 3)
            return showError("Full name must be at least 3 characters.");

        if (!mobile)      return showError("Mobile number is required.");
        if (!/^[0-9]{10,15}$/.test(mobile))
            return showError("Mobile number must be 10–15 digits.");

        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))
            return showError("Please enter a valid email address.");

        if (!branchId)    return showError("Please select a branch.");

        if (!password)    return showError("Password is required.");
        if (password.length < 6)
            return showError("Password must be at least 6 characters.");

        setLoading(true);

        const fd = new FormData();
        fd.append("mode", "add");
        fd.append("full_name", full_name);
        fd.append("mobile_number", mobile);
        fd.append("email_address", email);
        fd.append("branch_id", branchId);
        fd.append("password", password);
        fd.append("status", status);

        fetch(BASE_URL + "ajax/save-delivery-boy.php", {
            method: "POST",
            body: fd,
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => ({
                success: false,
                message: "Unexpected server response."
            })))
            .then(data => {

                setLoading(false);

                if (data.success) {
                    const code = data.data && data.data.code ? data.data.code : "";
                    showSuccess(data.message || "Delivery boy added.", code);
                    form.reset();
                } else {
                    showError(data.message || "Failed to save.");
                }
            })
            .catch(() => {
                setLoading(false);
                showError("Unable to connect to server.");
            });
    });

})();