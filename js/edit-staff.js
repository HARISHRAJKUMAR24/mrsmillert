/* =========================================================
   MRS MILL@ — EDIT STAFF (admin only)
   File: ./js/edit-staff.js
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    const form = document.getElementById("staffForm");
    const saveBtn = document.getElementById("saveBtn");
    const saveText = document.getElementById("saveBtnText");

    const statusRow = document.getElementById("statusRow");
    const statusInput = document.getElementById("status");
    const statusTitle = document.getElementById("statusTitle");
    const statusDesc = document.getElementById("statusDesc");

    const successOverlay = document.getElementById("successOverlay");
    const successText = document.getElementById("successText");
    const errorOverlay = document.getElementById("errorOverlay");
    const errorText = document.getElementById("errorText");
    const errorTitle = document.getElementById("errorTitle");
    const errorOkBtn = document.getElementById("errorOkBtn");

    if (!form) return;

    function showError(msg, title) {
        if (errorTitle) errorTitle.textContent = title || "Oops!";
        if (errorText) errorText.textContent = msg || "Something went wrong.";
        errorOverlay.classList.add("show");
        errorOverlay.setAttribute("aria-hidden", "false");
    }

    function closeError() {
        errorOverlay.classList.remove("show");
        errorOverlay.setAttribute("aria-hidden", "true");
    }

    if (errorOkBtn) errorOkBtn.addEventListener("click", closeError);
    if (errorOverlay) errorOverlay.addEventListener("click", e => {
        if (e.target === errorOverlay) closeError();
    });

    function setLoading(isLoading) {
        saveBtn.disabled = isLoading;
        saveText.innerHTML = isLoading
            ? '<span class="btn-spinner"></span> Updating...'
            : 'Update Staff';
    }

    function applyStatusUI() {
        const active = statusInput.checked;
        statusRow.classList.toggle("is-active", active);
        statusTitle.textContent = active ? "Active" : "Inactive";
        statusDesc.textContent = active ? "Staff can log in." : "Staff cannot log in.";
    }
    statusInput.addEventListener("change", applyStatusUI);

    const mobileInput = document.getElementById("mobile_number");
    if (mobileInput) {
        mobileInput.addEventListener("input", function () {
            mobileInput.value = mobileInput.value.replace(/[^0-9]/g, "").slice(0, 15);
        });
    }

    form.addEventListener("submit", function (e) {
        e.preventDefault();

        const id = Number(document.getElementById("staff_id").value || 0);
        const fullName = document.getElementById("full_name").value.trim();
        const mobile = mobileInput.value.trim();
        const email = document.getElementById("email_address").value.trim();
        const role = document.getElementById("role").value;
        const status = statusInput.checked ? "1" : "0";

        if (id <= 0) return showError("Invalid staff ID.");
        if (!fullName) return showError("Full name is required.", "Missing name");
        if (fullName.length < 3) return showError("Full name must be at least 3 characters.", "Invalid name");
        if (!mobile) return showError("Mobile number is required.", "Missing mobile");
        if (!/^[0-9]{10,15}$/.test(mobile)) return showError("Mobile must be 10–15 digits.", "Invalid mobile");
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return showError("Enter a valid email.", "Invalid email");

        setLoading(true);

        const fd = new FormData();
        fd.append("id", id);
        fd.append("full_name", fullName);
        fd.append("mobile_number", mobile);
        fd.append("email_address", email);
        fd.append("role", role);
        fd.append("status", status);

        fetch(BASE_URL + "ajax/update-staff.php", {
            method: "POST", body: fd, credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                setLoading(false);
                if (res && res.success) {
                    if (successText) successText.textContent = res.message || "Staff updated.";
                    successOverlay.classList.add("show");
                    successOverlay.setAttribute("aria-hidden", "false");
                } else {
                    showError((res && res.message) || "Failed to update.", "Update failed");
                }
            })
            .catch(() => {
                setLoading(false);
                showError("Unable to connect.", "Network error");
            });
    });

    applyStatusUI();

})();