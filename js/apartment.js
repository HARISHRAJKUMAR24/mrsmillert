/* =========================================================
   MRS MILL@ — APARTMENT LIST UX
   File: ./js/apartment.js
   Shows all divisions per apartment, each with its charge
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL =
        (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
            ? window.ADMIN_URL
            : "./";

    const tbody = document.getElementById("apartmentTbody");
    const search = document.getElementById("apartmentSearch");

    const modalOverlay = document.getElementById("mmModalOverlay");
    const modalText = document.getElementById("mmModalText");
    const modalCancel = document.getElementById("mmModalCancel");
    const modalConfirm = document.getElementById("mmModalConfirm");
    const toastContainer = document.getElementById("mmToastContainer");

    let pendingDeleteId = null;

    /* =========================================
       HELPERS
    ========================================= */

    function escapeHtml(str) {
        return String(str ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function money(n) {
        return "₹" + Number(n || 0).toFixed(2);
    }

    /* =========================================
       TOAST
    ========================================= */

    function showToast(type, message, timeout) {

        if (!toastContainer) {
            console.log("[" + type + "] " + message);
            return;
        }

        const icons = {
            success: "bi-check-lg",
            error: "bi-x-lg",
            info: "bi-info-lg"
        };

        const el = document.createElement("div");
        el.className = "mm-toast " + type;

        el.innerHTML = `
            <div class="mm-toast-icon">
                <i class="bi ${icons[type] || icons.info}"></i>
            </div>
            <div class="mm-toast-body">${escapeHtml(message)}</div>
            <button type="button" class="mm-toast-close" aria-label="Close">
                <i class="bi bi-x"></i>
            </button>
        `;

        toastContainer.appendChild(el);

        requestAnimationFrame(() => el.classList.add("show"));

        const close = function () {
            el.classList.remove("show");
            setTimeout(() => el.remove(), 300);
        };

        el.querySelector(".mm-toast-close")
            .addEventListener("click", close);

        setTimeout(close, timeout || 3200);
    }

    /* =========================================
       MODAL
    ========================================= */

    function openModal(id, name) {

        if (!modalOverlay) return;

        pendingDeleteId = id;

        if (modalText) {
            modalText.textContent = name
                ? 'Delete "' + name + '"? This action cannot be undone.'
                : "This action cannot be undone. The apartment will be permanently removed.";
        }

        modalOverlay.classList.add("show");
        modalOverlay.setAttribute("aria-hidden", "false");
    }

    function closeModal() {

        if (!modalOverlay) return;

        modalOverlay.classList.remove("show");
        modalOverlay.setAttribute("aria-hidden", "true");
        pendingDeleteId = null;

        if (modalConfirm) {
            modalConfirm.disabled = false;
            modalConfirm.innerHTML = '<i class="bi bi-trash"></i> Delete';
        }
    }

    if (modalCancel) modalCancel.addEventListener("click", closeModal);

    if (modalOverlay) {

        modalOverlay.addEventListener("click", function (e) {
            if (e.target === modalOverlay) closeModal();
        });

        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape" &&
                modalOverlay.classList.contains("show")) closeModal();
        });
    }

    if (modalConfirm) {

        modalConfirm.addEventListener("click", function () {

            const id = pendingDeleteId;
            if (!id) return;

            modalConfirm.disabled = true;
            modalConfirm.innerHTML =
                '<span class="mm-btn-spinner"></span> Deleting...';

            const formData = new FormData();
            formData.append("id", id);

            fetch(BASE_URL + "ajax/delete-apartment.php", {
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
                        closeModal();
                        showToast("success", data.message || "Apartment deleted.");
                        loadApartments(search ? search.value.trim() : "");
                    } else {
                        showToast("error", data.message || "Delete failed.");
                        modalConfirm.disabled = false;
                        modalConfirm.innerHTML = '<i class="bi bi-trash"></i> Delete';
                    }
                })
                .catch(() => {
                    showToast("error", "Unable to connect to server.");
                    modalConfirm.disabled = false;
                    modalConfirm.innerHTML = '<i class="bi bi-trash"></i> Delete';
                });
        });
    }

    /* =========================================
       RENDER DIVISION TAGS
    ========================================= */

    function renderDivisions(divisions) {

        if (!Array.isArray(divisions) || divisions.length === 0) {
            return '<span style="color:#b5aca2;font-size:10px;">—</span>';
        }

        return divisions.map(function (d) {
            const name = escapeHtml(d.division || "");
            const charge = money(d.charge || 0);
            return `
                <div class="division-tag">
                    <span class="division-tag-name">${name}</span>
                    <span class="division-tag-charge">${charge}</span>
                </div>
            `;
        }).join("");
    }

    /* =========================================
       RENDER ROW
    ========================================= */

    function renderRow(a) {

        const active = Number(a.status) === 1;

        return `
            <tr data-id="${a.id}">
                <td>
                    <div class="apartment-name-wrap">
                        <div class="apartment-icon">
                            <i class="bi bi-building"></i>
                        </div>
                        <div>
                            <div class="apartment-name">${escapeHtml(a.apartment_name)}</div>
                            <div class="apartment-id">#${escapeHtml(a.apartment_code)}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="division-tags">
                        ${renderDivisions(a.divisions)}
                    </div>
                </td>
                <td>
                    <div class="address-text">${escapeHtml(a.apartment_address)}</div>
                </td>
                <td>
                    <span class="status-badge ${active ? "status-active" : "status-inactive"}">
                        <i class="bi bi-circle-fill"></i>
                        ${active ? "Active" : "Inactive"}
                    </span>
                </td>
                <td>
                    <div class="action-buttons">
                       <a class="table-action" title="Edit"
                        href="${BASE_URL}/edit-apartment.php?id=${a.id}">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <button class="table-action delete"
                                title="Delete"
                                data-id="${a.id}"
                                data-name="${escapeHtml(a.apartment_name)}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }

    /* =========================================
       RENDER LIST
    ========================================= */

    function renderList(rows) {

        if (!rows || rows.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <i class="bi bi-building"></i>
                            No apartments found.
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = rows.map(renderRow).join("");
    }

    /* =========================================
       LOAD LIST
    ========================================= */

    function loadApartments(query) {

        tbody.innerHTML = `
            <tr class="loading-row">
                <td colspan="5">
                    <span class="table-spinner"></span>
                </td>
            </tr>
        `;

        const url = BASE_URL + "ajax/apartment.php"
            + (query ? "?search=" + encodeURIComponent(query) : "");

        fetch(url, { credentials: "same-origin" })
            .then(r => r.json().catch(() => ({
                success: false,
                message: "Unexpected server response."
            })))
            .then(data => {

                if (!data.success) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    ${escapeHtml(data.message || "Failed to load.")}
                                </div>
                            </td>
                        </tr>
                    `;
                    return;
                }

                renderList(data.data || []);
            })
            .catch(() => {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <i class="bi bi-wifi-off"></i>
                                Unable to connect to server.
                            </div>
                        </td>
                    </tr>
                `;
            });
    }

    /* =========================================
       SEARCH
    ========================================= */

    let searchTimer = null;

    if (search) {
        search.addEventListener("input", function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                loadApartments(search.value.trim());
            }, 300);
        });
    }

    /* =========================================
       DELETE TRIGGER
    ========================================= */

    tbody.addEventListener("click", function (e) {

        const btn = e.target.closest(".table-action.delete");
        if (!btn) return;

        const id = btn.dataset.id;
        const name = btn.dataset.name || "";

        if (!id) return;

        openModal(id, name);
    });

    /* =========================================
       INIT
    ========================================= */

    loadApartments("");

})();