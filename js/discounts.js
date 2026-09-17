/* =========================================================
   MRS MILL@ — DISCOUNT LIST
   File: ./js/discounts.js
   - Live search + type/status filter
   - Delete with confirm
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    /* ---------------- DOM ---------------- */

    const searchInput  = document.getElementById("searchInput");
    const typeFilter   = document.getElementById("typeFilter");
    const statusFilter = document.getElementById("statusFilter");
    const tbody        = document.getElementById("discTbody");

    const deleteOverlay = document.getElementById("deleteOverlay");
    const deleteText    = document.getElementById("deleteText");
    const deleteCancel  = document.getElementById("deleteCancel");
    const deleteConfirm = document.getElementById("deleteConfirm");

    const errorOverlay = document.getElementById("errorOverlay");
    const errorText    = document.getElementById("errorText");
    const errorTitle   = document.getElementById("errorTitle");
    const errorOkBtn   = document.getElementById("errorOkBtn");

    const successOverlay = document.getElementById("successOverlay");
    const successText    = document.getElementById("successText");
    const successOkBtn   = document.getElementById("successOkBtn");

    if (!tbody) return;

    let pendingDeleteId = null;

    /* ---------------- FILTER ---------------- */

    function applyFilters() {
        const q   = (searchInput?.value || "").toLowerCase().trim();
        const typ = typeFilter?.value || "";
        const st  = statusFilter?.value || "";

        tbody.querySelectorAll("tr").forEach(row => {
            if (!row.dataset.name) return;

            const name   = row.dataset.name || "";
            const code   = row.dataset.code || "";
            const coupon = row.dataset.coupon || "";
            const rowType = row.dataset.type || "";
            const rowStatus = row.dataset.status || "";

            const matchQ = !q || name.includes(q) || code.includes(q) || coupon.includes(q);
            const matchT = !typ || rowType === typ;
            const matchS = !st || rowStatus === st;

            row.style.display = (matchQ && matchT && matchS) ? "" : "none";
        });
    }

    if (searchInput)  searchInput.addEventListener("input", applyFilters);
    if (typeFilter)   typeFilter.addEventListener("change", applyFilters);
    if (statusFilter) statusFilter.addEventListener("change", applyFilters);

    /* ---------------- MODALS ---------------- */

    function showError(message, title) {
        if (errorTitle) errorTitle.textContent = title || "Oops!";
        if (errorText)  errorText.textContent  = message || "Something went wrong.";
        errorOverlay.classList.add("show");
        errorOverlay.setAttribute("aria-hidden", "false");
    }
    function closeError() {
        errorOverlay.classList.remove("show");
        errorOverlay.setAttribute("aria-hidden", "true");
    }
    function closeSuccess() {
        successOverlay.classList.remove("show");
        successOverlay.setAttribute("aria-hidden", "true");
    }
    function openDelete(id, name) {
        pendingDeleteId = id;
        if (deleteText) deleteText.textContent =
            `Delete "${name}"? This will remove the discount and all its time slots.`;
        deleteOverlay.classList.add("show");
        deleteOverlay.setAttribute("aria-hidden", "false");
    }
    function closeDelete() {
        deleteOverlay.classList.remove("show");
        deleteOverlay.setAttribute("aria-hidden", "true");
        pendingDeleteId = null;
    }

    if (deleteCancel) deleteCancel.addEventListener("click", closeDelete);
    deleteOverlay.addEventListener("click", e => {
        if (e.target === deleteOverlay) closeDelete();
    });

    if (errorOkBtn) errorOkBtn.addEventListener("click", closeError);
    errorOverlay.addEventListener("click", e => {
        if (e.target === errorOverlay) closeError();
    });

    if (successOkBtn) successOkBtn.addEventListener("click", closeSuccess);
    successOverlay.addEventListener("click", e => {
        if (e.target === successOverlay) closeSuccess();
    });

    /* ---------------- DELETE ---------------- */

    tbody.addEventListener("click", e => {
        const btn = e.target.closest(".js-delete-btn");
        if (!btn) return;
        const id = Number(btn.dataset.id || 0);
        const name = btn.dataset.name || "this discount";
        if (!id) return;
        openDelete(id, name);
    });

    if (deleteConfirm) {
        deleteConfirm.addEventListener("click", () => {
            if (!pendingDeleteId) return;

            const id = pendingDeleteId;
            deleteConfirm.disabled = true;
            deleteConfirm.innerHTML = '<span class="btn-spinner"></span> Deleting...';

            const formData = new FormData();
            formData.append("id", id);

            fetch(BASE_URL + "ajax/delete-discount.php", {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            })
            .then(r => r.json().catch(() => ({ success: false, message: "Unexpected server response." })))
            .then(data => {
                deleteConfirm.disabled = false;
                deleteConfirm.innerHTML = '<i class="bi bi-trash3"></i> Delete';
                closeDelete();

                if (data.success) {
                    const row = tbody.querySelector(`.js-delete-btn[data-id="${id}"]`)?.closest("tr");
                    if (row) {
                        row.style.transition = "opacity .2s, transform .2s";
                        row.style.opacity = "0";
                        row.style.transform = "translateX(-10px)";
                        setTimeout(() => row.remove(), 200);
                    }
                    if (successText) successText.textContent = data.message || "Discount deleted.";
                    successOverlay.classList.add("show");
                    successOverlay.setAttribute("aria-hidden", "false");
                } else {
                    showError(data.message || "Failed to delete.", "Delete failed");
                }
            })
            .catch(() => {
                deleteConfirm.disabled = false;
                deleteConfirm.innerHTML = '<i class="bi bi-trash3"></i> Delete';
                closeDelete();
                showError("Unable to connect to server.", "Network error");
            });
        });
    }

})();