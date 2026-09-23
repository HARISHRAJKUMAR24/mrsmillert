/* =========================================================
   MRS MILL@ — ORDER VIEW (admin panel)
   File: ./js/order-view.js
   - Save order status + payment status
   - Order History pagination (5 rows per page)
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    const ORDER_ID = Number(window.ORDER_ID || 0);

    /* Order status */
    const orderStatusSel = document.getElementById("ovOrderStatus");
    const payStatusSel   = document.getElementById("ovPayStatus");

    /* Save button */
    const saveAllBtn  = document.getElementById("saveAllBtn");
    const saveAllText = document.getElementById("saveAllText");

    /* History pagination */
    const historyList      = document.getElementById("orderHistoryList");
    const historyPagination = document.getElementById("historyPagination");
    const historyInfo       = document.getElementById("historyPaginationInfo");
    const historyControls   = document.getElementById("historyPaginationControls");

    const HISTORY_PER_PAGE = 5;

    let historyRows  = [];
    let historyPage  = 1;

    /* Toast */
    function showToast(msg, type) {
        let toast = document.getElementById("mmToast");
        if (!toast) {
            toast = document.createElement("div");
            toast.id = "mmToast";
            document.body.appendChild(toast);
        }
        toast.textContent = msg;
        toast.className = "show" + (type === "error" ? " error" : "");
        clearTimeout(toast._timer);
        toast._timer = setTimeout(() => toast.className = "", 2600);
    }

    /* ---------- PAGINATION HELPERS ---------- */
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

    function renderHistoryControls(totalPages) {
        if (!historyControls) return;

        if (totalPages <= 1) {
            historyControls.innerHTML = "";
            return;
        }

        const html = [];

        html.push(
            '<button type="button" data-page="' + (historyPage - 1) + '"' +
            (historyPage === 1 ? ' disabled' : '') +
            ' title="Previous"><i class="bi bi-chevron-left"></i></button>'
        );

        getPageList(historyPage, totalPages).forEach(function (p) {
            if (p === "...") {
                html.push('<span class="page-ellipsis">…</span>');
            } else {
                html.push(
                    '<button type="button" data-page="' + p + '"' +
                    (p === historyPage ? ' class="active"' : '') +
                    '>' + p + '</button>'
                );
            }
        });

        html.push(
            '<button type="button" data-page="' + (historyPage + 1) + '"' +
            (historyPage === totalPages ? ' disabled' : '') +
            ' title="Next"><i class="bi bi-chevron-right"></i></button>'
        );

        historyControls.innerHTML = html.join("");
    }

    function renderHistoryPage() {

        if (!historyList) return;

        const total = historyRows.length;

        if (total === 0) {
            if (historyPagination) historyPagination.style.display = "none";
            return;
        }

        const totalPages = Math.max(1, Math.ceil(total / HISTORY_PER_PAGE));

        if (historyPage > totalPages) historyPage = totalPages;
        if (historyPage < 1) historyPage = 1;

        const start = (historyPage - 1) * HISTORY_PER_PAGE;
        const end   = Math.min(start + HISTORY_PER_PAGE, total);

        historyRows.forEach((r, i) => {
            r.style.display = (i >= start && i < end) ? "" : "none";
        });

        if (historyInfo) {
            historyInfo.innerHTML =
                'Showing <strong>' + (start + 1) + '</strong>–<strong>' + end +
                '</strong> of <strong>' + total + '</strong>';
        }

        renderHistoryControls(totalPages);

        if (historyPagination) {
            historyPagination.style.display = total > HISTORY_PER_PAGE ? "flex" : "none";
        }
    }

    if (historyList) {
        historyRows = Array.from(historyList.querySelectorAll("[data-order-row]"));

        if (historyRows.length > HISTORY_PER_PAGE) {
            historyPage = 1;
            renderHistoryPage();
        }
    }

    if (historyControls) {
        historyControls.addEventListener("click", function (e) {
            const btn = e.target.closest("button[data-page]");
            if (!btn || btn.disabled) return;

            const page = Number(btn.dataset.page);
            if (!page || page === historyPage) return;

            historyPage = page;
            renderHistoryPage();

            const card = document.querySelector(".ov-cust-orders");
            if (card) {
                window.scrollTo({
                    top: card.offsetTop - 20,
                    behavior: "smooth"
                });
            }
        });
    }

    /* ---------- SAVE ORDER STATUS ---------- */
    if (saveAllBtn) {
        saveAllBtn.addEventListener("click", function () {

            if (ORDER_ID <= 0) return;

            saveAllBtn.disabled = true;
            saveAllText.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...';

            const fd = new FormData();
            fd.append("id", ORDER_ID);
            fd.append("status", orderStatusSel.value);
            fd.append("payment_status", payStatusSel.value);

            fetch(BASE_URL + "ajax/update-order-status.php", {
                method: "POST",
                body: fd,
                credentials: "same-origin"
            })
                .then(r => r.json().catch(() => null))
                .then(res => {
                    saveAllBtn.disabled = false;
                    saveAllText.innerHTML = '<i class="bi bi-check-lg"></i> Save Changes';

                    if (res && res.success) {
                        showToast(res.message || "Order updated.");
                        setTimeout(() => window.location.reload(), 700);
                    } else {
                        showToast((res && res.message) || "Failed to update.", "error");
                    }
                })
                .catch(() => {
                    saveAllBtn.disabled = false;
                    saveAllText.innerHTML = '<i class="bi bi-check-lg"></i> Save Changes';
                    showToast("Unable to connect.", "error");
                });
        });
    }

})();