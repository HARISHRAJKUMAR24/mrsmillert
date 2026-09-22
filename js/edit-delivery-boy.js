/* =========================================================
   MRS MILL@ — EDIT DELIVERY BOY
   File: ./js/edit-delivery-boy.js
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL =
        (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
            ? window.ADMIN_URL
            : "./";

    const form     = document.getElementById("boyForm");
    const saveBtn  = document.getElementById("saveBtn");
    const saveText = document.getElementById("saveBtnText");

    const successOverlay = document.getElementById("successOverlay");
    const successText    = document.getElementById("successText");
    const stayBtn        = document.getElementById("stayBtn");

    const errorOverlay = document.getElementById("errorOverlay");
    const errorText    = document.getElementById("errorText");
    const errorOkBtn   = document.getElementById("errorOkBtn");

    if (!form) return;

    function setLoading(isLoading) {
        saveBtn.disabled = isLoading;
        saveText.innerHTML = isLoading
            ? '<span class="btn-spinner"></span> Updating...'
            : 'Update Delivery Boy';
    }

    function showError(msg) {
        if (errorText) errorText.textContent = msg || "Please check the form.";
        if (errorOverlay) {
            errorOverlay.classList.add("show");
            errorOverlay.setAttribute("aria-hidden", "false");
            setTimeout(() => errorOkBtn && errorOkBtn.focus(), 60);
        }
    }

    function showSuccess(msg) {
        if (successText) successText.textContent = msg || "Saved successfully.";
        if (successOverlay) {
            successOverlay.classList.add("show");
            successOverlay.setAttribute("aria-hidden", "false");
        }
    }

    /* close handlers */
    if (errorOverlay) {
        errorOverlay.addEventListener("click", e => {
            if (e.target === errorOverlay) errorOverlay.classList.remove("show");
        });
    }
    if (errorOkBtn) {
        errorOkBtn.addEventListener("click", () => errorOverlay.classList.remove("show"));
    }
    if (successOverlay) {
        successOverlay.addEventListener("click", e => {
            if (e.target === successOverlay) successOverlay.classList.remove("show");
        });
    }
    if (stayBtn) {
        stayBtn.addEventListener("click", () => successOverlay.classList.remove("show"));
    }
    document.addEventListener("keydown", e => {
        if (e.key === "Escape") {
            errorOverlay?.classList.remove("show");
            successOverlay?.classList.remove("show");
        }
    });

    /* submit */
    form.addEventListener("submit", function (e) {

        e.preventDefault();

        const id      = Number(document.getElementById("boy_id").value || 0);
        const name    = document.getElementById("full_name").value.trim();
        const mobile  = document.getElementById("mobile_number").value.trim();
        const email   = document.getElementById("email_address").value.trim();
        const branchId= document.getElementById("branch_id").value.trim();
        const status  = document.getElementById("status").value;

        if (id <= 0)      return showError("Invalid delivery boy ID.");
        if (!name)        return showError("Full name is required.");
        if (name.length < 3)
            return showError("Full name must be at least 3 characters.");
        if (!mobile)      return showError("Mobile number is required.");
        if (!/^[0-9]{10,15}$/.test(mobile))
            return showError("Mobile number must be 10–15 digits.");
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))
            return showError("Please enter a valid email address.");
        if (!branchId)    return showError("Please select a branch.");

        setLoading(true);

        const fd = new FormData();
        fd.append("mode", "edit");
        fd.append("id", id);
        fd.append("full_name", name);
        fd.append("mobile_number", mobile);
        fd.append("email_address", email);
        fd.append("branch_id", branchId);
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
                    showSuccess(data.message || "Delivery boy updated.");
                } else {
                    showError(data.message || "Failed to update.");
                }
            })
            .catch(() => {
                setLoading(false);
                showError("Unable to connect to server.");
            });
    });

})();