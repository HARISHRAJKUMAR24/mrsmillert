/* =========================================================
   MRS MILL@ — ADDRESS REQUESTS (accordion + filters + branch + delivery + previews)
   File: ./js/address-requests.js   v2026-11-03g
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL) ? window.ADMIN_URL : "./";

    const listEl = document.getElementById("arTbody");
    const search = document.getElementById("arSearch");
    const refreshBtn = document.getElementById("arRefresh");
    const paginationWrap = document.getElementById("arPagination");
    const pagInfo = document.getElementById("arPagInfo");
    const pagControls = document.getElementById("arPagControls");
    const perPageSelect = document.getElementById("arPerPage");
    const toastWrap = document.getElementById("arToastWrap");

    const filterDate = document.getElementById("arFilterDate");
    const filterStatus = document.getElementById("arFilterStatus");
    const customRange = document.getElementById("arCustomRange");
    const dateFrom = document.getElementById("arDateFrom");
    const dateTo = document.getElementById("arDateTo");

    /* Modal elements */
    const approveOverlay = document.getElementById("arApproveOverlay");
    const approveClose = document.getElementById("arApproveClose");
    const approveCancel = document.getElementById("arApproveCancel");
    const approveForm = document.getElementById("arApproveForm");
    const approveSubmit = document.getElementById("arApproveSubmit");
    const reqIdEl = document.getElementById("arReqId");
    const nameEl = document.getElementById("arName");
    const mobileEl = document.getElementById("arMobile");
    const pwdEl = document.getElementById("arPassword");
    const typeEl = document.getElementById("arReqType");
    const aptBlock = document.getElementById("arAptBlock");
    const aptEl = document.getElementById("arApartment");
    const aptAddrEl = document.getElementById("arApartmentAddress");
    const branchEl = document.getElementById("arBranch");
    const divEl = document.getElementById("arDivision");
    const deliveryEl = document.getElementById("arDeliveryCharge");
    const houseBlock = document.getElementById("arHouseBlock");
    const houseAddrEl = document.getElementById("arHouseAddress");
    const mapEl = document.getElementById("arMapLink");
    const houseChargeEl = document.getElementById("arHouseDeliveryCharge");
    const houseImgEl = document.getElementById("arHouseImage");
    const mapPreview = document.getElementById("arMapPreview");
    const imgPreview = document.getElementById("arImagePreview");

    const DEFAULT_PAGE_SIZE = 10;
    let pageSize = DEFAULT_PAGE_SIZE;
    let allRows = [];
    let filteredRows = [];
    let currentPage = 1;
    let allBranches = [];

    const openIds = new Set();

    const STATUS_LABELS = { "0": "Pending", "1": "Approved", "2": "Rejected" };
    const STATUS_CLASS = { "0": "pending", "1": "approved", "2": "rejected" };

    /* -------- helpers -------- */
    function esc(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }
    function initials(name) {
        name = String(name || "").trim();
        if (!name) return "?";
        const parts = name.split(/\s+/);
        return parts.length >= 2
            ? (parts[0][0] + parts[1][0]).toUpperCase()
            : name.substring(0, 2).toUpperCase();
    }
    function formatDate(s) {
        if (!s) return { d: "—", t: "" };
        try {
            const dt = new Date(s.replace(" ", "T"));
            return {
                d: dt.toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" }),
                t: dt.toLocaleTimeString("en-IN", { hour: "2-digit", minute: "2-digit" })
            };
        } catch (e) { return { d: s, t: "" }; }
    }
    function waLink(mobile, name) {
        let d = String(mobile || "").replace(/\D/g, "");
        if (!d) return "#";
        if (d.length === 10) d = "91" + d;
        return "https://wa.me/" + d + "?text=" + encodeURIComponent(
            "Hello " + (name || "") + ", this is Mrs Mill@. Regarding your address request"
        );
    }
    function showToast(type, message, timeout) {
        if (!toastWrap) return;
        const icons = { success: "bi-check-lg", error: "bi-x-lg", info: "bi-info-lg" };
        const el = document.createElement("div");
        el.className = "ar-toast " + type;
        el.innerHTML = `
            <div class="ar-toast-icon"><i class="bi ${icons[type] || icons.info}"></i></div>
            <div class="ar-toast-body">${esc(message)}</div>
            <button type="button" class="ar-toast-close"><i class="bi bi-x"></i></button>
        `;
        toastWrap.appendChild(el);
        requestAnimationFrame(() => el.classList.add("show"));
        const close = () => { el.classList.remove("show"); setTimeout(() => el.remove(), 300); };
        el.querySelector(".ar-toast-close").addEventListener("click", close);
        setTimeout(close, timeout || 3200);
    }
    /* Convert a maps link into an embed URL for iframe preview */
    function mapsEmbedUrl(url) {
        if (!url) return "";
        if (/google\.com\/maps\/embed/i.test(url)) return url;
        const m = url.match(/[?&]q=([-0-9.]+),([-0-9.]+)/);
        if (m) return `https://maps.google.com/maps?q=${m[1]},${m[2]}&z=15&output=embed`;
        const pm = url.match(/place\/([^\/]+)/);
        if (pm) return `https://maps.google.com/maps?q=${encodeURIComponent(pm[1])}&output=embed`;
        return url.includes("output=embed") ? url : url + (url.includes("?") ? "&" : "?") + "output=embed";
    }

    /* -------- date filters -------- */
    function startOfDay(d) { const x = new Date(d); x.setHours(0, 0, 0, 0); return x; }
    function endOfDay(d) { const x = new Date(d); x.setHours(23, 59, 59, 999); return x; }
    function getDateRange() {
        const v = filterDate ? filterDate.value : "all";
        const now = new Date();
        if (v === "all") return null;
        if (v === "today") return [startOfDay(now), endOfDay(now)];
        if (v === "yesterday") { const y = new Date(now); y.setDate(y.getDate() - 1); return [startOfDay(y), endOfDay(y)]; }
        if (v === "7d") { const s = new Date(now); s.setDate(s.getDate() - 6); return [startOfDay(s), endOfDay(now)]; }
        if (v === "30d") { const s = new Date(now); s.setDate(s.getDate() - 29); return [startOfDay(s), endOfDay(now)]; }
        if (v === "custom") {
            const from = dateFrom && dateFrom.value ? startOfDay(new Date(dateFrom.value)) : null;
            const to = dateTo && dateTo.value ? endOfDay(new Date(dateTo.value)) : null;
            if (from && to) return [from, to];
            if (from) return [from, endOfDay(now)];
            if (to) return [startOfDay(new Date(0)), to];
        }
        return null;
    }
    function parseRowDate(s) {
        if (!s) return null;
        const d = new Date(s.replace(" ", "T"));
        return isNaN(d) ? null : d;
    }

    /* -------- render -------- */
    function statusPill(st) {
        const cls = STATUS_CLASS[String(st)] || "pending";
        const label = STATUS_LABELS[String(st)] || "Unknown";
        return `<span class="ar-acc-status-pill ${cls}"><span class="dot"></span>${esc(label)}</span>`;
    }
    function actionButtons(r) {
        const tel = String(r.customer_mobile || "").replace(/\D/g, "");
        const callHref = tel ? `tel:${tel}` : "#";
        const waHref = waLink(r.customer_mobile, r.customer_name);
        const approved = Number(r.status) === 1;
        const approveBtn = approved
            ? `<button type="button" class="ar-btn ar-btn-unapprove" data-action="unapprove" data-id="${r.id}"><i class="bi bi-arrow-counterclockwise"></i> Unapprove</button>`
            : `<button type="button" class="ar-btn ar-btn-approve" data-action="approve" data-id="${r.id}"><i class="bi bi-check2-circle"></i> Approve</button>`;
        return `${approveBtn}
            <a class="ar-call" href="${callHref}"><i class="bi bi-telephone-fill"></i> Call</a>
            <a class="ar-whatsapp" href="${waHref}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a>`;
    }

    function renderCard(r) {
        const dt = formatDate(r.created_at);
        const st = String(r.status ?? "0");
        const typeLabel = r.request_type === "individual_house" ? "Individual House" : "Apartment";
        const isOpen = openIds.has(String(r.id));
        const stateClass = st === "1" ? "is-approved" : st === "2" ? "is-rejected" : "is-pending";
        const charge = Number(r.delivery_charge || 0);

        const mapEmbed = r.map_link ? mapsEmbedUrl(r.map_link) : "";

        return `
            <div class="ar-acc-item ${isOpen ? "open" : ""} ${stateClass}" data-id="${r.id}">
                <div class="ar-acc-head" data-toggle="${r.id}">
                    <div class="ar-acc-customer">
                        <div class="ar-avatar">${esc(initials(r.customer_name))}</div>
                        <div style="min-width:0;">
                            <span class="ar-cust-name">${esc(r.customer_name)}</span>
                            <div class="ar-cust-mobile">${esc(r.customer_mobile)}</div>
                        </div>
                    </div>
                    <div class="ar-acc-col"><div class="ar-col-label">Requested</div><span class="ar-apartment" style="font-size:12px;">${esc(r.requested_apartment || "—")}</span></div>
                    <div class="ar-acc-col"><div class="ar-col-label">Division</div><span style="font-size:12px;color:#6f675f;font-weight:600;">${esc(r.requested_division || "—")}</span></div>
                    <div class="ar-acc-col"><div class="ar-col-label">Date</div><div class="ar-date">${esc(dt.d)}${dt.t ? `<small>${esc(dt.t)}</small>` : ""}</div></div>
                    <div class="ar-acc-col"><div class="ar-col-label">Status</div>${statusPill(st)}</div>
                    <button type="button" class="ar-acc-chevron" data-toggle="${r.id}" aria-label="Toggle"><i class="bi bi-chevron-down"></i></button>
                </div>

                <div class="ar-acc-body">
                    <div class="ar-acc-body-inner">
                        <div class="ar-acc-detail"><div class="ar-acc-detail-label">Type</div><div class="ar-acc-detail-value">${esc(typeLabel)}</div></div>
                        <div class="ar-acc-detail"><div class="ar-acc-detail-label">Mobile</div><div class="ar-acc-detail-value">${esc(r.customer_mobile || "—")}</div></div>

                        ${typeLabel === "Apartment" ? `
                            <div class="ar-acc-detail"><div class="ar-acc-detail-label">Apartment</div><div class="ar-acc-detail-value">${esc(r.requested_apartment || "—")}</div></div>
                            <div class="ar-acc-detail"><div class="ar-acc-detail-label">Division</div><div class="ar-acc-detail-value">${esc(r.requested_division || "—")}</div></div>
                            <div class="ar-acc-detail"><div class="ar-acc-detail-label">Delivery Charge</div><div class="ar-acc-detail-value">₹${charge.toFixed(2)}</div></div>
                        ` : `
                            <div class="ar-acc-detail" style="grid-column:1/-1;"><div class="ar-acc-detail-label">Full Address</div><div class="ar-acc-detail-value">${esc(r.requested_apartment || "—")}</div></div>
                            <div class="ar-acc-detail"><div class="ar-acc-detail-label">Delivery Charge</div><div class="ar-acc-detail-value">₹${charge.toFixed(2)}</div></div>
                        `}

                        <!-- MAP LINK (for both types) -->
                        ${r.map_link ? `<div class="ar-acc-detail" style="grid-column:1/-1;"><div class="ar-acc-detail-label">Google Maps</div><div class="ar-acc-detail-value"><a href="${esc(r.map_link)}" target="_blank" rel="noopener"><i class="bi bi-geo-alt-fill"></i> Open location</a></div></div>` : ""}
                        
                        <!-- MINI MAP EMBED (for both types) -->
                        ${mapEmbed ? `<div class="ar-mini-map" style="grid-column:1/-1;"><iframe src="${esc(mapEmbed)}" loading="lazy"></iframe></div>` : ""}

                        <!-- HOUSE IMAGE (for both types) -->
                        ${r.house_image ? `<div class="ar-mini-img" style="grid-column:1/-1;"><img src="${esc(r.house_image)}" alt="House" loading="lazy"></div>` : ""}

                        <div class="ar-acc-detail"><div class="ar-acc-detail-label">Requested On</div><div class="ar-acc-detail-value">${esc(dt.d)}${dt.t ? " · " + esc(dt.t) : ""}</div></div>
                        ${r.approved_at ? `<div class="ar-acc-detail"><div class="ar-acc-detail-label">Approved On</div><div class="ar-acc-detail-value">${esc(formatDate(r.approved_at).d)}</div></div>` : ""}
                        ${r.created_customer_id ? `<div class="ar-acc-detail"><div class="ar-acc-detail-label">Customer ID</div><div class="ar-acc-detail-value">#${r.created_customer_id}</div></div>` : ""}

                        <div class="ar-acc-actions">${actionButtons(r)}</div>
                    </div>
                </div>
            </div>
        `;
    }

    function renderPage() {
        const total = filteredRows.length;
        if (total === 0) {
            listEl.innerHTML = `<div class="ar-empty" style="padding:50px 20px;text-align:center;">
                <i class="bi bi-inbox"></i><h3>No requests found</h3>
                <p>Try adjusting your search or filters.</p></div>`;
            if (paginationWrap) paginationWrap.style.display = "none";
            return;
        }
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;
        const start = (currentPage - 1) * pageSize;
        const end = Math.min(start + pageSize, total);
        listEl.innerHTML = filteredRows.slice(start, end).map(renderCard).join("");

        if (pagInfo) pagInfo.innerHTML = 'Showing <strong>' + (start + 1) + '</strong>–<strong>' + end + '</strong> of <strong>' + total + '</strong>';
        renderPaginationControls(totalPages);
        if (paginationWrap) paginationWrap.style.display = "flex";
    }

    function renderPaginationControls(totalPages) {
        if (!pagControls) return;
        if (totalPages <= 1) { pagControls.innerHTML = ""; return; }
        const html = [];
        html.push(`<button data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''}><i class="bi bi-chevron-left"></i></button>`);
        getPageList(currentPage, totalPages).forEach(p => {
            if (p === "...") html.push('<span class="ellipsis">…</span>');
            else html.push(`<button data-page="${p}" ${p === currentPage ? 'class="active"' : ''}>${p}</button>`);
        });
        html.push(`<button data-page="${currentPage + 1}" ${currentPage === totalPages ? 'disabled' : ''}><i class="bi bi-chevron-right"></i></button>`);
        pagControls.innerHTML = html.join("");
    }
    function getPageList(current, total) {
        const delta = 1, range = [], out = [];
        for (let i = 1; i <= total; i++) {
            if (i === 1 || i === total || (i >= current - delta && i <= current + delta)) range.push(i);
        }
        let prev = 0;
        range.forEach(i => { if (prev && i - prev > 1) out.push("..."); out.push(i); prev = i; });
        return out;
    }

    if (pagControls) pagControls.addEventListener("click", e => {
        const btn = e.target.closest("button[data-page]");
        if (!btn || btn.disabled) return;
        const page = Number(btn.dataset.page);
        if (!page || page === currentPage) return;
        currentPage = page;
        renderPage();
        if (listEl) window.scrollTo({ top: listEl.offsetTop - 80, behavior: "smooth" });
    });

    if (perPageSelect) {
        perPageSelect.value = String(pageSize);
        perPageSelect.addEventListener("change", function () {
            const v = Number(this.value); if (!v) return;
            pageSize = v; currentPage = 1; renderPage();
        });
    }

    function applyFilterAndRender(resetPage) {
        const q = (search ? search.value : "").trim().toLowerCase();
        const stVal = filterStatus ? filterStatus.value : "all";
        const range = getDateRange();

        filteredRows = allRows.filter(r => {
            if (q) {
                const hay = [r.customer_name, r.customer_mobile, r.requested_apartment, r.requested_division, r.request_type].join(" ").toLowerCase();
                if (hay.indexOf(q) === -1) return false;
            }
            if (stVal !== "all" && String(r.status) !== stVal) return false;
            if (range) {
                const d = parseRowDate(r.created_at);
                if (!d) return false;
                if (d < range[0] || d > range[1]) return false;
            }
            return true;
        });
        if (resetPage !== false) currentPage = 1;
        renderPage();
    }

    let searchTimer = null;
    if (search) search.addEventListener("input", () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => applyFilterAndRender(), 200);
    });
    if (filterStatus) filterStatus.addEventListener("change", () => applyFilterAndRender());
    if (filterDate) filterDate.addEventListener("change", () => {
        if (filterDate.value === "custom") { if (customRange) customRange.style.display = "flex"; }
        else { if (customRange) customRange.style.display = "none"; applyFilterAndRender(); }
    });
    if (dateFrom) dateFrom.addEventListener("change", () => applyFilterAndRender());
    if (dateTo) dateTo.addEventListener("change", () => applyFilterAndRender());

    /* -------- load -------- */
    function populateBranches(branches) {
        if (!branchEl) return;
        allBranches = branches || [];
        branchEl.innerHTML = `<option value="">— Select Branch —</option>` +
            allBranches.map(b => `<option value="${b.id}">${esc(b.branch_name)}</option>`).join("");
    }
    function loadRequests() {
        listEl.innerHTML = `<div class="ar-acc-item" style="padding:40px;text-align:center;color:#948c82;border:1.5px dashed #e4ddd3;">
            <span style="display:inline-block;width:22px;height:22px;border:3px solid #eee7dc;border-top-color:#b51f2c;border-radius:50%;animation:arSpin .7s linear infinite;"></span>
        </div>`;
        if (paginationWrap) paginationWrap.style.display = "none";

        fetch(BASE_URL + "ajax/address-requests-list.php", { credentials: "same-origin" })
            .then(r => r.json().catch(() => ({ success: false, message: "Unexpected server response." })))
            .then(data => {
                if (!data.success) {
                    listEl.innerHTML = `<div class="ar-empty" style="padding:50px 20px;text-align:center;">
                        <i class="bi bi-exclamation-triangle"></i><h3>Failed to load</h3>
                        <p>${esc(data.message || "Please try again.")}</p></div>`;
                    return;
                }
                allRows = Array.isArray(data.data.requests) ? data.data.requests : [];
                populateBranches(data.data.branches || []);
                currentPage = 1;
                applyFilterAndRender();
            })
            .catch(() => {
                listEl.innerHTML = `<div class="ar-empty" style="padding:50px 20px;text-align:center;">
                    <i class="bi bi-wifi-off"></i><h3>Unable to connect</h3>
                    <p>Please check your network.</p></div>`;
            });
    }

    if (refreshBtn) refreshBtn.addEventListener("click", () => {
        const icon = refreshBtn.querySelector("i");
        if (icon) { icon.style.transition = "transform .5s"; icon.style.transform = "rotate(360deg)"; }
        setTimeout(() => { if (icon) icon.style.transform = "rotate(0deg)"; }, 500);
        openIds.clear();
        loadRequests();
    });

    /* -------- accordion + actions -------- */
    document.addEventListener("click", function (e) {
        const toggle = e.target.closest("[data-toggle]");
        if (toggle && !e.target.closest("[data-action]") && !e.target.closest("a")) {
            const id = String(toggle.dataset.toggle);
            const card = toggle.closest(".ar-acc-item");
            if (!card) return;
            const willOpen = !card.classList.contains("open");
            card.classList.toggle("open", willOpen);
            if (willOpen) openIds.add(id); else openIds.delete(id);
            return;
        }
        const btn = e.target.closest("[data-action]");
        if (!btn) return;
        const action = btn.dataset.action;
        const id = btn.dataset.id;
        if (!id) return;

        if (action === "approve") {
            const row = allRows.find(x => String(x.id) === String(id));
            if (row) openApproveModal(row);
            return;
        }
        if (action === "unapprove") {
            if (!confirm("Unapprove this request? Status goes back to Pending. Apartment and customer will NOT be deleted.")) return;
            const fd = new FormData();
            fd.append("id", id);
            fd.append("action", "unapprove");
            btn.disabled = true;
            fetch(BASE_URL + "ajax/update-address-request-status.php", {
                method: "POST", body: fd, credentials: "same-origin"
            })
                .then(r => r.json().catch(() => ({ success: false, message: "Unexpected response." })))
                .then(res => {
                    btn.disabled = false;
                    if (!res.success) return showToast("error", res.message || "Failed.");
                    showToast("success", res.message || "Unapproved.");
                    openIds.delete(String(id));
                    loadRequests();
                })
                .catch(() => { btn.disabled = false; showToast("error", "Unable to connect."); });
        }
    });

    /* -------- approve modal -------- */
    function renderMapPreview() {
        if (!mapPreview) return;
        const url = mapEl.value.trim();
        if (!url) { mapPreview.style.display = "none"; mapPreview.innerHTML = ""; return; }
        const embed = mapsEmbedUrl(url);
        mapPreview.innerHTML = `<iframe src="${esc(embed)}" loading="lazy"></iframe>`;
        mapPreview.style.display = "block";
    }
    function renderImagePreview() {
        if (!imgPreview) return;
        const url = houseImgEl.value.trim();
        if (!url) { imgPreview.style.display = "none"; imgPreview.innerHTML = ""; return; }
        imgPreview.innerHTML = `<img src="${esc(url)}" alt="House">`;
        imgPreview.style.display = "block";
    }

    function openApproveModal(r) {
        reqIdEl.value = r.id;
        nameEl.value = r.customer_name || "";
        mobileEl.value = r.customer_mobile || "";
        pwdEl.value = "";
        typeEl.value = r.request_type || "apartment";
        aptEl.value = r.requested_apartment || "";
        aptAddrEl.value = "";
        branchEl.value = r.created_branch_id ? String(r.created_branch_id) : "";
        divEl.value = r.requested_division || "";
        deliveryEl.value = r.delivery_charge ? Number(r.delivery_charge).toFixed(2) : "";
        houseAddrEl.value = r.request_type === 'individual_house' ? (r.requested_apartment || "") : "";
        mapEl.value = r.map_link || "";
        houseChargeEl.value = r.delivery_charge ? Number(r.delivery_charge).toFixed(2) : "";
        houseImgEl.value = r.house_image || "";

        toggleModalType();
        renderMapPreview();
        renderImagePreview();

        approveOverlay.classList.add("show");
        approveOverlay.setAttribute("aria-hidden", "false");
    }
    function closeApproveModal() {
        approveOverlay.classList.remove("show");
        approveOverlay.setAttribute("aria-hidden", "true");
    }
    function toggleModalType() {
        const t = typeEl.value;
        if (t === 'individual_house') {
            aptBlock.style.display = "none";
            houseBlock.style.display = "";
            if (!houseAddrEl.value && aptEl.value) houseAddrEl.value = aptEl.value;
        } else {
            aptBlock.style.display = "";
            houseBlock.style.display = "none";
        }
    }
    typeEl.addEventListener("change", toggleModalType);
    if (mapEl) mapEl.addEventListener("input", renderMapPreview);
    if (houseImgEl) houseImgEl.addEventListener("input", renderImagePreview);

    if (approveClose) approveClose.addEventListener("click", closeApproveModal);
    if (approveCancel) approveCancel.addEventListener("click", closeApproveModal);
    if (approveOverlay) approveOverlay.addEventListener("click", e => {
        if (e.target === approveOverlay) closeApproveModal();
    });
    document.addEventListener("keydown", e => {
        if (e.key === "Escape" && approveOverlay.classList.contains("show")) closeApproveModal();
    });

    if (approveForm) approveForm.addEventListener("submit", function (e) {
        e.preventDefault();

        const id = Number(reqIdEl.value);
        const name = nameEl.value.trim();
        const mobile = mobileEl.value.trim();
        const password = pwdEl.value.trim();
        const type = typeEl.value;
        const apt = aptEl.value.trim();
        const aptAddr = aptAddrEl.value.trim();
        const branchId = branchEl.value;
        const div = divEl.value.trim();
        const houseAddr = houseAddrEl.value.trim();
        const mapLink = mapEl.value.trim();
        const houseCharge = houseChargeEl.value.trim();
        const deliveryCharge = type === 'individual_house' ? houseCharge : deliveryEl.value.trim();
        const houseImg = houseImgEl.value.trim();

        if (name.length < 3) return showToast("error", "Name must be at least 3 characters.");
        if (!/^[0-9]{10,15}$/.test(mobile)) return showToast("error", "Mobile must be 10–15 digits.");
        if (type === 'apartment' && !apt) return showToast("error", "Apartment name is required.");
        if (type === 'individual_house' && !houseAddr) return showToast("error", "Full address is required.");

        const fd = new FormData();
        fd.append("id", id);
        fd.append("action", "approve");
        fd.append("customer_name", name);
        fd.append("customer_mobile", mobile);
        if (password) fd.append("password", password);
        fd.append("request_type", type);
        fd.append("requested_apartment", type === 'apartment' ? apt : houseAddr);
        fd.append("apartment_address", aptAddr);
        if (branchId) fd.append("branch_id", branchId);
        fd.append("requested_division", div);
        fd.append("map_link", mapLink);       /* SEND MAP LINK */
        fd.append("house_image", houseImg);   /* SEND HOUSE IMAGE */
        fd.append("delivery_charge", deliveryCharge || "0");

        approveSubmit.disabled = true;
        const oldHtml = approveSubmit.innerHTML;
        approveSubmit.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid #fff8;border-top-color:#fff;border-radius:50%;animation:arSpin .7s linear infinite;"></span> Saving…';

        fetch(BASE_URL + "ajax/update-address-request-status.php", {
            method: "POST", body: fd, credentials: "same-origin"
        })
            .then(r => r.json().catch(() => ({ success: false, message: "Unexpected response." })))
            .then(res => {
                approveSubmit.disabled = false;
                approveSubmit.innerHTML = oldHtml;
                if (!res.success) return showToast("error", res.message || "Failed.");
                showToast("success", res.message || "Approved.");
                closeApproveModal();
                loadRequests();
            })
            .catch(() => {
                approveSubmit.disabled = false;
                approveSubmit.innerHTML = oldHtml;
                showToast("error", "Unable to connect.");
            });
    });

    loadRequests();
})();