/* =========================================================
   MRS MILL@ — STAFF LIST (admin only)
   File: ./js/staff.js
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    const tbody = document.getElementById("staffTbody");

    const delOverlay = document.getElementById("delOverlay");
    const delText    = document.getElementById("delText");
    const delCancel  = document.getElementById("delCancel");
    const delConfirm = document.getElementById("delConfirm");

    if (!tbody) return;

    let deleteId = 0;

    function showToast(msg, type) {
        let t = document.getElementById("mmToast");
        if (!t) {
            t = document.createElement("div");
            t.id = "mmToast";
            document.body.appendChild(t);
        }
        t.textContent = msg;
        t.className = "show" + (type === "error" ? " error" : "");
        clearTimeout(t._timer);
        t._timer = setTimeout(() => t.className = "", 2600);
    }

    function escapeHtml(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function loadStaff() {
        tbody.innerHTML = `
            <tr><td colspan="6" style="text-align:center;padding:40px;color:#948c82;">
                Loading...
            </td></tr>`;

        fetch(BASE_URL + "ajax/get-staff.php", { credentials: "same-origin" })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !Array.isArray(res.data)) {
                    tbody.innerHTML = `
                        <tr><td colspan="6" style="text-align:center;padding:40px;color:#b51f2c;">
                            Failed to load staff.
                        </td></tr>`;
                    return;
                }

                if (!res.data.length) {
                    tbody.innerHTML = `
                        <tr><td colspan="6">
                            <div class="st-empty">
                                <i class="bi bi-people"></i>
                                <h3>No staff yet</h3>
                                <p>Click Add Staff to create the first account.</p>
                            </div>
                        </td></tr>`;
                    return;
                }

                tbody.innerHTML = res.data.map(s => {
                    const initial = (s.full_name || "?").charAt(0).toUpperCase();
                    const status = Number(s.status);
                    const roleCls = s.role === 'admin' ? 'admin' : 'staff';

                    return `
                        <tr data-id="${s.id}">
                            <td>
                                <div class="staff-cell">
                                    <div class="staff-avatar">${escapeHtml(initial)}</div>
                                    <div class="staff-info">
                                        <div class="staff-name">${escapeHtml(s.full_name)}</div>
                                        <div class="staff-code">#${escapeHtml(s.staff_code || '')}</div>
                                    </div>
                                </div>
                            </td>
                            <td><div class="mobile-cell"><i class="bi bi-telephone-fill"></i>${escapeHtml(s.mobile_number)}</div></td>
                            <td>${escapeHtml(s.email_address || '—')}</td>
                            <td><span class="role-badge ${roleCls}">${escapeHtml(s.role)}</span></td>
                            <td>
                                <div class="status-select" data-status="${status}">
                                    <select class="staff-status" data-id="${s.id}" data-status="${status}">
                                        <option value="1" ${status === 1 ? 'selected' : ''}>Active</option>
                                        <option value="0" ${status === 0 ? 'selected' : ''}>Inactive</option>
                                    </select>
                                </div>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="${BASE_URL}edit-staff.php?id=${s.id}" class="btn-icon edit" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn-icon delete js-del-btn"
                                            data-id="${s.id}"
                                            data-name="${escapeHtml(s.full_name)}"
                                            title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                }).join("");
            })
            .catch(() => {
                tbody.innerHTML = `
                    <tr><td colspan="6" style="text-align:center;padding:40px;color:#b51f2c;">
                        Unable to connect.
                    </td></tr>`;
            });
    }

    tbody.addEventListener("change", function (e) {
        const sel = e.target.closest(".staff-status");
        if (!sel) return;

        const id = Number(sel.dataset.id);
        const newVal = sel.value;
        const oldVal = sel.dataset.status;
        const wrap = sel.closest(".status-select");

        sel.disabled = true;
        wrap.dataset.status = newVal;
        sel.dataset.status = newVal;

        const fd = new FormData();
        fd.append("id", id);
        fd.append("status", newVal);

        fetch(BASE_URL + "ajax/toggle-staff-status.php", {
            method: "POST", body: fd, credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (res && res.success) {
                    showToast(res.message || "Status updated.");
                } else {
                    sel.value = oldVal;
                    sel.dataset.status = oldVal;
                    wrap.dataset.status = oldVal;
                    showToast((res && res.message) || "Failed.", "error");
                }
                sel.disabled = false;
            })
            .catch(() => {
                sel.value = oldVal;
                sel.dataset.status = oldVal;
                wrap.dataset.status = oldVal;
                sel.disabled = false;
                showToast("Unable to connect.", "error");
            });
    });

    tbody.addEventListener("click", function (e) {
        const btn = e.target.closest(".js-del-btn");
        if (!btn) return;

        deleteId = Number(btn.dataset.id);
        const name = btn.dataset.name || "this staff";

        if (delText) delText.textContent = `Delete "${name}"? This cannot be undone.`;
        delOverlay.classList.add("show");
        delOverlay.setAttribute("aria-hidden", "false");
    });

    function closeDel() {
        delOverlay.classList.remove("show");
        delOverlay.setAttribute("aria-hidden", "true");
        deleteId = 0;
    }

    if (delCancel) delCancel.addEventListener("click", closeDel);
    if (delOverlay) delOverlay.addEventListener("click", e => {
        if (e.target === delOverlay) closeDel();
    });

    if (delConfirm) {
        delConfirm.addEventListener("click", function () {
            if (!deleteId) return;

            delConfirm.disabled = true;
            delConfirm.innerHTML = '<span class="btn-spinner"></span> Deleting...';

            const fd = new FormData();
            fd.append("id", deleteId);

            fetch(BASE_URL + "ajax/delete-staff.php", {
                method: "POST", body: fd, credentials: "same-origin"
            })
                .then(r => r.json().catch(() => null))
                .then(res => {
                    delConfirm.disabled = false;
                    delConfirm.innerHTML = '<i class="bi bi-trash"></i> Delete';

                    if (res && res.success) {
                        showToast(res.message || "Staff deleted.");
                        closeDel();
                        loadStaff();
                    } else {
                        showToast((res && res.message) || "Failed to delete.", "error");
                    }
                })
                .catch(() => {
                    delConfirm.disabled = false;
                    delConfirm.innerHTML = '<i class="bi bi-trash"></i> Delete';
                    showToast("Unable to connect.", "error");
                });
        });
    }

    loadStaff();

})();