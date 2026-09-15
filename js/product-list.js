/* =========================================================
   MRS MILL@ — PRODUCT LIST UX
   File: ./js/product-list.js
   Client-side search + pagination + per-page + delete.
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    const tbody  = document.getElementById("productTbody");
    const search = document.getElementById("productSearch");

    const paginationWrap     = document.getElementById("productPagination");
    const paginationInfo     = document.getElementById("paginationInfo");
    const paginationControls = document.getElementById("paginationControls");
    const perPageSelect      = document.getElementById("perPageSelect");

    const modalOverlay   = document.getElementById("mmModalOverlay");
    const modalText      = document.getElementById("mmModalText");
    const modalCancel    = document.getElementById("mmModalCancel");
    const modalConfirm   = document.getElementById("mmModalConfirm");
    const toastContainer = document.getElementById("mmToastContainer");

    /* =========================================
       STATE
    ========================================= */

    const DEFAULT_PAGE_SIZE = 10;

    let pageSize        = DEFAULT_PAGE_SIZE;
    let allRows         = [];        // full list from server
    let filteredRows    = [];        // after search
    let currentPage     = 1;
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

    /* =========================================
       TOAST
    ========================================= */

    function showToast(type, message, timeout) {
        if (!toastContainer) return;

        const icons = { success: "bi-check-lg", error: "bi-x-lg", info: "bi-info-lg" };

        const el = document.createElement("div");
        el.className = "mm-toast " + type;

        el.innerHTML = `
            <div class="mm-toast-icon"><i class="bi ${icons[type] || icons.info}"></i></div>
            <div class="mm-toast-body">${escapeHtml(message)}</div>
            <button type="button" class="mm-toast-close"><i class="bi bi-x"></i></button>
        `;

        toastContainer.appendChild(el);
        requestAnimationFrame(() => el.classList.add("show"));

        const close = () => {
            el.classList.remove("show");
            setTimeout(() => el.remove(), 300);
        };

        el.querySelector(".mm-toast-close").addEventListener("click", close);
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
                ? `Delete "${name}"? This will also remove the image file and its apartment links.`
                : "This will remove the product, its image file, and its apartment links.";
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
        modalOverlay.addEventListener("click", e => {
            if (e.target === modalOverlay) closeModal();
        });
        document.addEventListener("keydown", e => {
            if (e.key === "Escape" && modalOverlay.classList.contains("show")) closeModal();
        });
    }

    if (modalConfirm) {
        modalConfirm.addEventListener("click", () => {
            const id = pendingDeleteId;
            if (!id) return;

            modalConfirm.disabled = true;
            modalConfirm.innerHTML = '<span class="mm-btn-spinner"></span> Deleting...';

            const formData = new FormData();
            formData.append("id", id);

            fetch(BASE_URL + "ajax/delete-product.php", {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            })
            .then(r => r.json().catch(() => ({ success: false, message: "Unexpected server response." })))
            .then(data => {
                if (data.success) {
                    closeModal();
                    showToast("success", data.message || "Product deleted.");

                    /* Remove locally, keep current page if possible */
                    allRows = allRows.filter(r => String(r.id) !== String(id));

                    const q = (search ? search.value : "").trim().toLowerCase();

                    if (!q) {
                        filteredRows = allRows.slice();
                    } else {
                        filteredRows = allRows.filter(function (p) {
                            const name = (p.product_name || "").toLowerCase();
                            const code = (p.product_code || "").toLowerCase();
                            const cat  = (p.category_name || "").toLowerCase();
                            return name.indexOf(q) !== -1 ||
                                   code.indexOf(q) !== -1 ||
                                   cat.indexOf(q) !== -1;
                        });
                    }

                    const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));
                    if (currentPage > totalPages) currentPage = totalPages;

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
       RENDER APARTMENT CHIPS
    ========================================= */

    function renderAptChips(apts) {
        if (!Array.isArray(apts) || apts.length === 0) {
            return '<span style="color:#b5aca2;font-size:10px;">—</span>';
        }

        const max = 4;
        const shown = apts.slice(0, max)
            .map(a => `<span class="apt-chip">${escapeHtml(a)}</span>`)
            .join("");
        const more = apts.length > max
            ? `<span class="apt-more">+${apts.length - max} more</span>`
            : "";

        return `<div class="apt-chips">${shown}${more}</div>`;
    }

    /* =========================================
       RENDER ROW
    ========================================= */

    function renderRow(p) {
        const active = Number(p.status) === 1;

        const img = p.image_url
            ? `<img src="${escapeHtml(p.image_url)}" alt="" class="prod-thumb" onerror="this.outerHTML='<div class=\\'prod-thumb-placeholder\\'><i class=\\'bi bi-image\\'></i></div>'">`
            : `<div class="prod-thumb-placeholder"><i class="bi bi-image"></i></div>`;

        return `
            <tr data-id="${p.id}">
                <td>${img}</td>
                <td>
                    <div class="prod-name">
                        ${escapeHtml(p.product_name)}
                        <span class="prod-code">#${escapeHtml(p.product_code)}</span>
                    </div>
                    <div class="prod-qty">
                        ${escapeHtml(p.quantity)} ${escapeHtml(p.quantity_unit)} · ${escapeHtml(p.quantity_name)}
                    </div>
                </td>
                <td>
                    <span class="prod-cat">${escapeHtml(p.category_name || "—")}</span>
                </td>
                <td>${renderAptChips(p.apartments)}</td>
                <td>
                    <span class="status-badge ${active ? "status-active" : "status-inactive"}">
                        <i class="bi bi-circle-fill"></i>
                        ${active ? "Active" : "Inactive"}
                    </span>
                </td>
                <td>
                    <div class="action-buttons">
                        <a class="table-action" title="Edit"
                           href="${BASE_URL}edit-product.php?id=${p.id}">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <button class="table-action delete"
                                title="Delete"
                                data-id="${p.id}"
                                data-name="${escapeHtml(p.product_name)}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }

    /* =========================================
       RENDER PAGE
    ========================================= */

    function renderPage() {
        const total = filteredRows.length;

        if (total === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="bi bi-box-seam"></i>
                            No products found.
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
        const end   = Math.min(start + pageSize, total);
        const slice = filteredRows.slice(start, end);

        tbody.innerHTML = slice.map(renderRow).join("");

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
        const out   = [];

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

            const card = document.querySelector(".product-list-card");
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
            filteredRows = allRows.filter(function (p) {
                const name = (p.product_name || "").toLowerCase();
                const code = (p.product_code || "").toLowerCase();
                const cat  = (p.category_name || "").toLowerCase();
                return name.indexOf(q) !== -1 ||
                       code.indexOf(q) !== -1 ||
                       cat.indexOf(q) !== -1;
            });
        }

        if (resetPage !== false) currentPage = 1;
        renderPage();
    }

    /* =========================================
       LOAD LIST (all rows once)
    ========================================= */

    function loadProducts() {
        tbody.innerHTML = `
            <tr class="loading-row">
                <td colspan="6"><span class="table-spinner"></span></td>
            </tr>
        `;

        if (paginationWrap) paginationWrap.style.display = "none";

        fetch(BASE_URL + "ajax/product-list.php", { credentials: "same-origin" })
            .then(r => r.json().catch(() => ({ success: false, message: "Unexpected server response." })))
            .then(data => {
                if (!data.success) {
                    tbody.innerHTML = `
                        <tr><td colspan="6">
                            <div class="empty-state">
                                <i class="bi bi-exclamation-triangle"></i>
                                ${escapeHtml(data.message || "Failed to load.")}
                            </div>
                        </td></tr>
                    `;
                    return;
                }

                allRows = Array.isArray(data.data) ? data.data : [];
                currentPage = 1;
                applyFilterAndRender();
            })
            .catch(() => {
                tbody.innerHTML = `
                    <tr><td colspan="6">
                        <div class="empty-state">
                            <i class="bi bi-wifi-off"></i>
                            Unable to connect to server.
                        </div>
                    </td></tr>
                `;
            });
    }

    /* =========================================
       SEARCH (client-side, debounced)
    ========================================= */

    let searchTimer = null;
    if (search) {
        search.addEventListener("input", () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => applyFilterAndRender(), 200);
        });
    }

    /* =========================================
       DELETE TRIGGER
    ========================================= */

    if (tbody) {
        tbody.addEventListener("click", e => {
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

    loadProducts();

})();