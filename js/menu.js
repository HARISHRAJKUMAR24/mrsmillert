/* =========================================================
   MRS MILL@ — MENU LIST
   File: ./js/menu.js
   - Live search + status filter
   - Client-side pagination with per-page selector
   - Delete with confirm
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    /* ---------------- DOM ---------------- */

    const searchInput = document.getElementById("searchInput");
    const statusFilter = document.getElementById("statusFilter");
    const tbody = document.getElementById("menuTbody");

    const paginationWrap = document.getElementById("menuPagination");
    const paginationInfo = document.getElementById("paginationInfo");
    const paginationControls = document.getElementById("paginationControls");
    const perPageSelect = document.getElementById("perPageSelect");

    const deleteOverlay = document.getElementById("deleteOverlay");
    const deleteText = document.getElementById("deleteText");
    const deleteCancel = document.getElementById("deleteCancel");
    const deleteConfirm = document.getElementById("deleteConfirm");

    const errorOverlay = document.getElementById("errorOverlay");
    const errorText = document.getElementById("errorText");
    const errorTitle = document.getElementById("errorTitle");
    const errorOkBtn = document.getElementById("errorOkBtn");

    const successOverlay = document.getElementById("successOverlay");
    const successText = document.getElementById("successText");
    const successOkBtn = document.getElementById("successOkBtn");

    if (!tbody) return;

    /* ---------------- STATE ---------------- */

    const DEFAULT_PAGE_SIZE = 10;

    let pageSize = DEFAULT_PAGE_SIZE;
    let allRows = [];        // all <tr> elements (kept in original order)
    let filteredRows = [];        // after search/filter
    let currentPage = 1;
    let currentDeleteId = null;

    /* ---------------- HELPERS ---------------- */

    function getDataRows() {
        return Array.from(tbody.querySelectorAll("tr"))
            .filter(tr => !tr.querySelector(".table-empty"));
    }

    /* ---------------- FILTER + PAGINATION ---------------- */

    function applyFilterAndRender(resetPage) {

        const q = (searchInput ? searchInput.value : "").toLowerCase().trim();
        const st = statusFilter ? statusFilter.value : "";

        filteredRows = allRows.filter(row => {
            const name = row.dataset.name || "";
            const code = row.dataset.code || "";
            const status = row.dataset.status || "";

            const matchQ = !q || name.includes(q) || code.includes(q);
            const matchSt = !st || status === st;

            return matchQ && matchSt;
        });

        if (resetPage !== false) currentPage = 1;
        renderPage();
    }

    function renderPage() {

        /* Hide all rows first */
        allRows.forEach(r => r.style.display = "none");

        const total = filteredRows.length;

        if (total === 0) {
            /* Hide pagination, show "nothing" state if not already */
            if (paginationWrap) paginationWrap.style.display = "none";

            /* Show a temporary empty notice if needed */
            let emptyRow = tbody.querySelector(".js-empty-filter");
            if (!emptyRow) {
                emptyRow = document.createElement("tr");
                emptyRow.className = "js-empty-filter";
                emptyRow.innerHTML = `
                    <td colspan="5">
                        <div class="table-empty">
                            <i class="bi bi-search"></i>
                            No menus match your filter.
                        </div>
                    </td>`;
                tbody.appendChild(emptyRow);
            } else {
                emptyRow.style.display = "";
            }
            return;
        }

        /* Remove empty-filter notice if present */
        const emptyRow = tbody.querySelector(".js-empty-filter");
        if (emptyRow) emptyRow.remove();

        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const start = (currentPage - 1) * pageSize;
        const end = Math.min(start + pageSize, total);

        filteredRows.slice(start, end).forEach(row => {
            row.style.display = "";
        });

        /* Pagination info */
        if (paginationInfo) {
            paginationInfo.innerHTML =
                'Showing <strong>' + (start + 1) + '</strong>–<strong>' + end +
                '</strong> of <strong>' + total + '</strong>';
        }

        renderPaginationControls(totalPages);
        if (paginationWrap) paginationWrap.style.display = "flex";
    }

    function renderPaginationControls(totalPages) {

        if (!paginationControls) return;

        if (totalPages <= 1) {
            paginationControls.innerHTML = "";
            return;
        }

        const html = [];

        html.push(
            '<button type="button" data-page="' + (currentPage - 1) + '"' +
            (currentPage === 1 ? ' disabled' : '') +
            ' title="Previous"><i class="bi bi-chevron-left"></i></button>'
        );

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

        html.push(
            '<button type="button" data-page="' + (currentPage + 1) + '"' +
            (currentPage === totalPages ? ' disabled' : '') +
            ' title="Next"><i class="bi bi-chevron-right"></i></button>'
        );

        paginationControls.innerHTML = html.join("");
    }

    function getPageList(current, total) {
        const delta = 1;
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

    if (paginationControls) {
        paginationControls.addEventListener("click", function (e) {
            const btn = e.target.closest("button[data-page]");
            if (!btn || btn.disabled) return;

            const page = Number(btn.dataset.page);
            if (!page || page === currentPage) return;

            currentPage = page;
            renderPage();

            const card = document.querySelector(".menu-card");
            if (card) {
                window.scrollTo({
                    top: card.offsetTop - 20,
                    behavior: "smooth"
                });
            }
        });
    }

    if (perPageSelect) {
        perPageSelect.value = String(pageSize);

        perPageSelect.addEventListener("change", function () {
            const v = Number(this.value);
            if (!v || v <= 0) return;

            pageSize = v;
            currentPage = 1;
            renderPage();
        });
    }

    /* ---------------- SEARCH / FILTER ---------------- */

    if (searchInput) {
        searchInput.addEventListener("input", function () {
            applyFilterAndRender(true);
        });
    }

    if (statusFilter) {
        statusFilter.addEventListener("change", function () {
            applyFilterAndRender(true);
        });
    }

    /* ---------------- MODALS ---------------- */

    function showError(message, title) {
        if (errorTitle) errorTitle.textContent = title || "Oops!";
        if (errorText) errorText.textContent = message || "Something went wrong.";
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

    function openDeleteModal(id, name) {
        currentDeleteId = id;
        if (deleteText) {
            deleteText.textContent = `Delete "${name}"? This will remove the menu and all its products.`;
        }
        deleteOverlay.classList.add("show");
        deleteOverlay.setAttribute("aria-hidden", "false");
    }

    function closeDeleteModal() {
        deleteOverlay.classList.remove("show");
        deleteOverlay.setAttribute("aria-hidden", "true");
        currentDeleteId = null;
    }

    if (deleteCancel) deleteCancel.addEventListener("click", closeDeleteModal);
    deleteOverlay.addEventListener("click", e => {
        if (e.target === deleteOverlay) closeDeleteModal();
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
        const name = btn.dataset.name || "this menu";
        if (!id) return;

        openDeleteModal(id, name);
    });

    if (deleteConfirm) {
        deleteConfirm.addEventListener("click", () => {
            if (!currentDeleteId) return;

            const id = currentDeleteId;
            deleteConfirm.disabled = true;
            deleteConfirm.innerHTML = '<span class="btn-spinner"></span> Deleting...';

            const formData = new FormData();
            formData.append("id", id);

            fetch(BASE_URL + "ajax/delete-menu.php", {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            })
                .then(r => r.json().catch(() => ({ success: false, message: "Unexpected server response." })))
                .then(data => {

                    deleteConfirm.disabled = false;
                    deleteConfirm.innerHTML = '<i class="bi bi-trash3"></i> Delete';

                    closeDeleteModal();

                    if (data.success) {

                        /* Remove row from DOM + from state */
                        const row = tbody.querySelector(`.js-delete-btn[data-id="${id}"]`)?.closest("tr");
                        if (row) {
                            row.style.transition = "opacity .2s, transform .2s";
                            row.style.opacity = "0";
                            row.style.transform = "translateX(-10px)";
                            setTimeout(() => {
                                row.remove();
                                allRows = getDataRows();
                                applyFilterAndRender(false);
                            }, 200);
                        }

                        if (successText) successText.textContent = data.message || "Menu deleted.";
                        successOverlay.classList.add("show");
                        successOverlay.setAttribute("aria-hidden", "false");
                    } else {
                        showError(data.message || "Failed to delete menu.", "Delete failed");
                    }
                })
                .catch(() => {
                    deleteConfirm.disabled = false;
                    deleteConfirm.innerHTML = '<i class="bi bi-trash3"></i> Delete';
                    closeDeleteModal();
                    showError("Unable to connect to server.", "Network error");
                });
        });
    }

    /* ---------------- INIT ---------------- */

    /* Collect all rows on first load */
    allRows = getDataRows();

    /* If no menus exist, hide pagination */
    if (allRows.length === 0) {
        if (paginationWrap) paginationWrap.style.display = "none";
    } else {
        applyFilterAndRender(true);
    }

})();