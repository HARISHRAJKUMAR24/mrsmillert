/* =========================================================
   MRS MILL@ — APARTMENT LIST UX
   File: ./js/apartment.js
   Shows all divisions per apartment, each with its charge.
   Client-side pagination + per-page selector + search + delete + toast.
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL =
        (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
            ? window.ADMIN_URL
            : "./";

    const tbody = document.getElementById("apartmentTbody");
    const search = document.getElementById("apartmentSearch");
    const paginationWrap = document.getElementById("apartmentPagination");
    const paginationInfo = document.getElementById("paginationInfo");
    const paginationControls = document.getElementById("paginationControls");
    const perPageSelect = document.getElementById("perPageSelect");

    const modalOverlay = document.getElementById("mmModalOverlay");
    const modalText = document.getElementById("mmModalText");
    const modalCancel = document.getElementById("mmModalCancel");
    const modalConfirm = document.getElementById("mmModalConfirm");
    const toastContainer = document.getElementById("mmToastContainer");

    /* =========================================
       STATE
    ========================================= */

    const DEFAULT_PAGE_SIZE = 10;

    let pageSize = DEFAULT_PAGE_SIZE;
    let allRows = [];        // full list from server
    let filteredRows = [];        // after search
    let currentPage = 1;
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
       MODAL (delete confirm)
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

                        /* Remove locally, then re-render — KEEP current page */
                        allRows = allRows.filter(r => String(r.id) !== String(id));

                        /*
                         * Recompute filtered list WITHOUT resetting the page.
                         * Then clamp the page if the current page became empty
                         * (e.g. you deleted the last item on the last page).
                         */
                        const q = (search ? search.value : "").trim().toLowerCase();

                        if (!q) {
                            filteredRows = allRows.slice();
                        } else {
                            filteredRows = allRows.filter(function (a) {
                                const name = (a.apartment_name || "").toLowerCase();
                                const code = (a.apartment_code || "").toLowerCase();
                                const address = (a.apartment_address || "").toLowerCase();
                                return name.indexOf(q) !== -1 ||
                                    code.indexOf(q) !== -1 ||
                                    address.indexOf(q) !== -1;
                            });
                        }

                        const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));

                        if (currentPage > totalPages) {
                            currentPage = totalPages;
                        }

                        renderPage();

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
       RENDER LIST (current page)
    ========================================= */

    function renderPage() {

        const total = filteredRows.length;

        if (total === 0) {

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

            if (paginationWrap) paginationWrap.style.display = "none";
            return;
        }

        const totalPages = Math.max(1, Math.ceil(total / pageSize));

        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const start = (currentPage - 1) * pageSize;
        const end = Math.min(start + pageSize, total);
        const slice = filteredRows.slice(start, end);

        tbody.innerHTML = slice.map(renderRow).join("");

        /* update pagination info */
        if (paginationInfo) {
            paginationInfo.innerHTML =
                'Showing <strong>' + (start + 1) + '</strong>–<strong>' + end +
                '</strong> of <strong>' + total + '</strong>';
        }

        renderPaginationControls(totalPages);

        if (paginationWrap) paginationWrap.style.display = "flex";
    }

    /* =========================================
       PAGINATION CONTROLS
    ========================================= */

    function renderPaginationControls(totalPages) {

        if (!paginationControls) return;

        /* Hide controls if only one page */
        if (totalPages <= 1) {
            paginationControls.innerHTML = "";
            return;
        }

        const html = [];

        /* Prev */
        html.push(
            '<button type="button" data-page="' + (currentPage - 1) + '"' +
            (currentPage === 1 ? ' disabled' : '') +
            ' title="Previous"><i class="bi bi-chevron-left"></i></button>'
        );

        /* Page numbers with ellipsis (compact) */
        const pages = getPageList(currentPage, totalPages);

        pages.forEach(function (p) {
            if (p === "...") {
                html.push('<span class="page-ellipsis">…</span>');
            } else {
                html.push(
                    '<button type="button" data-page="' + p + '"' +
                    (p === currentPage ? ' class="active"' : '') +
                    '>' + p + '</button>'
                );
            }
        });

        /* Next */
        html.push(
            '<button type="button" data-page="' + (currentPage + 1) + '"' +
            (currentPage === totalPages ? ' disabled' : '') +
            ' title="Next"><i class="bi bi-chevron-right"></i></button>'
        );

        paginationControls.innerHTML = html.join("");
    }

    /* Compact page list:  1 … 4 5 6 … 20 */
    function getPageList(current, total) {

        const delta = 1;                 // pages on each side of current
        const range = [];
        const out = [];

        for (let i = 1; i <= total; i++) {
            if (i === 1 || i === total ||
                (i >= current - delta && i <= current + delta)) {
                range.push(i);
            }
        }

        let prev = 0;
        range.forEach(function (i) {
            if (prev && i - prev > 1) out.push("...");
            out.push(i);
            prev = i;
        });

        return out;
    }

    /* Click handler for pagination buttons */
    if (paginationControls) {
        paginationControls.addEventListener("click", function (e) {

            const btn = e.target.closest("button[data-page]");
            if (!btn || btn.disabled) return;

            const page = Number(btn.dataset.page);
            if (!page || page === currentPage) return;

            currentPage = page;
            renderPage();

            /* Scroll the table back to top smoothly */
            const card = document.querySelector(".apartment-list-card");
            if (card) {
                window.scrollTo({
                    top: card.offsetTop - 20,
                    behavior: "smooth"
                });
            }
        });
    }

    /* =========================================
       PER-PAGE SELECTOR
    ========================================= */

    if (perPageSelect) {

        /* Ensure the select reflects current pageSize */
        perPageSelect.value = String(pageSize);

        perPageSelect.addEventListener("change", function () {

            const v = Number(this.value);

            if (!v || v <= 0) return;

            pageSize = v;
            currentPage = 1;
            renderPage();

        });
    }

    /* =========================================
       FILTER + RENDER
    ========================================= */

    function applyFilterAndRender(resetPage) {

        const q = (search ? search.value : "").trim().toLowerCase();

        if (!q) {
            filteredRows = allRows.slice();
        } else {
            filteredRows = allRows.filter(function (a) {
                const name = (a.apartment_name || "").toLowerCase();
                const code = (a.apartment_code || "").toLowerCase();
                const address = (a.apartment_address || "").toLowerCase();
                return name.indexOf(q) !== -1 ||
                    code.indexOf(q) !== -1 ||
                    address.indexOf(q) !== -1;
            });
        }

        if (resetPage !== false) currentPage = 1;

        renderPage();
    }

    /* =========================================
       LOAD LIST
    ========================================= */

    function loadApartments() {

        tbody.innerHTML = `
            <tr class="loading-row">
                <td colspan="5">
                    <span class="table-spinner"></span>
                </td>
            </tr>
        `;

        if (paginationWrap) paginationWrap.style.display = "none";

        fetch(BASE_URL + "ajax/apartment.php", {
            credentials: "same-origin"
        })
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

                allRows = Array.isArray(data.data) ? data.data : [];
                currentPage = 1;
                applyFilterAndRender();
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
       SEARCH (client-side, instant)
    ========================================= */

    let searchTimer = null;

    if (search) {
        search.addEventListener("input", function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                applyFilterAndRender();
            }, 200);
        });
    }

    /* =========================================
       DELETE TRIGGER
    ========================================= */

    if (tbody) {
        tbody.addEventListener("click", function (e) {

            const btn = e.target.closest(".table-action.delete");
            if (!btn) return;

            const id = btn.dataset.id;
            const name = btn.dataset.name || "";

            if (!id) return;

            openModal(id, name);
        });
    }

    /* =========================================
       INIT
    ========================================= */

    loadApartments();

})();