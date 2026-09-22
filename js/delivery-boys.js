/* =========================================================
   MRS MILL@ — DELIVERY BOYS LIST
   File: ./js/delivery-boys.js
   - Live status change
   - Live search
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL =
        (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
            ? window.ADMIN_URL
            : "./";

    const table = document.getElementById("dbTable");
    const searchInput = document.getElementById("dbSearch");

    if (!table) return;

    /* =========================================
       STATUS CHANGE
    ========================================= */
    table.querySelectorAll("select.boy-status").forEach(function (sel) {

        sel.addEventListener("change", function () {

            const boyId = sel.dataset.id;
            const newVal = sel.value;
            const oldVal = sel.dataset.status;
            const wrap = sel.closest(".status-select");

            sel.disabled = true;
            wrap.dataset.status = newVal;
            sel.dataset.status = newVal;

            const fd = new FormData();
            fd.append("id", boyId);
            fd.append("status", newVal);

            fetch(BASE_URL + "ajax/toggle-delivery-boy-status.php", {
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
                        showToast(data.message || "Status updated.");
                    } else {
                        sel.value = oldVal;
                        sel.dataset.status = oldVal;
                        wrap.dataset.status = oldVal;
                        showToast(data.message || "Failed to update.", "error");
                    }
                    sel.disabled = false;
                })
                .catch(() => {
                    sel.value = oldVal;
                    sel.dataset.status = oldVal;
                    wrap.dataset.status = oldVal;
                    sel.disabled = false;
                    showToast("Unable to connect to server.", "error");
                });
        });
    });

    /* =========================================
       TOAST
    ========================================= */
    function showToast(message, type) {

        let toast = document.getElementById("mmToast");
        if (!toast) {
            toast = document.createElement("div");
            toast.id = "mmToast";
            document.body.appendChild(toast);
        }

        toast.textContent = message;
        toast.className = "show" + (type === "error" ? " error" : "");

        clearTimeout(toast._timer);
        toast._timer = setTimeout(() => { toast.className = ""; }, 2600);
    }

    /* =========================================
       LIVE SEARCH
    ========================================= */
    if (searchInput) {
        searchInput.addEventListener("input", function () {

            const q = searchInput.value.trim().toLowerCase();
            const rows = table.querySelectorAll("tbody tr");

            rows.forEach(function (row) {
                const haystack = (row.dataset.search || "").toLowerCase();
                row.style.display = !q || haystack.includes(q) ? "" : "none";
            });
        });
    }

})();