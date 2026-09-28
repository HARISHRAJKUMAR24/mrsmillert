/* =========================================================
   MRS MILL@ — ALLOCATE APARTMENTS (card view + popup)
   File: ./js/allocate-apartments.js
   ========================================================= */

(function () {
    "use strict";

    const ADMIN_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL) ? window.ADMIN_URL : "./";
    const BOYS = Array.isArray(window.DELIVERY_BOYS) ? window.DELIVERY_BOYS : [];

    /* ---------- DOM ---------- */
    const grid = document.getElementById("boyGrid");
    const boySearch = document.getElementById("boySearchInput");

    const overlay = document.getElementById("acOverlay");
    const modalAvatar = document.getElementById("acModalAvatar");
    const modalTitle = document.getElementById("acModalTitle");
    const modalSub = document.getElementById("acModalSub");
    const modalClose = document.getElementById("acModalClose");
    const modalTabs = document.querySelectorAll(".ac-tab");
    const availTab = document.getElementById("acAvailableTab");
    const allocTab = document.getElementById("acAllocatedTab");
    const availList = document.getElementById("acAvailList");
    const allocList = document.getElementById("acAllocList");
    const aptSearch = document.getElementById("aptSearchInput");
    const allocCount = document.getElementById("acAllocatedCount");
    const foot = document.getElementById("acFoot");
    const cancelBtn = document.getElementById("acCancel");
    const saveBtn = document.getElementById("acSave");
    const saveText = document.getElementById("acSaveText");
    const selectedPill = document.getElementById("acSelectedPill");
    const selectedText = document.getElementById("acSelectedText");
    const toast = document.getElementById("acToast");

    let currentBoy = null;
    let availableApts = [];
    let allocatedApts = [];
    let selectedCodes = new Set();

    /* ---------- Helpers ---------- */
    function showToast(msg, type) {
        if (!toast) return;
        toast.textContent = msg;
        toast.className = "ac-toast show" + (type === "error" ? " error" : "");
        clearTimeout(toast._t);
        toast._t = setTimeout(() => (toast.className = "ac-toast"), 2400);
    }

    function esc(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function initial(name) {
        return (String(name || "?").trim().charAt(0) || "?").toUpperCase();
    }

    /* =========================================================
       RENDER BOY CARDS
       ========================================================= */
    function renderBoyCards(query) {
        if (!grid) return;

        if (!BOYS.length) {
            grid.innerHTML = `
                <div class="ac-empty">
                    <i class="bi bi-person-badge"></i>
                    <h3>No delivery boys</h3>
                    <p>Add delivery boys first to allocate apartments.</p>
                </div>`;
            return;
        }

        const q = (query || "").trim().toLowerCase();
        const list = q
            ? BOYS.filter(b =>
                (b.full_name || "").toLowerCase().includes(q) ||
                (b.mobile_number || "").toLowerCase().includes(q) ||
                (b.delivery_code || "").toLowerCase().includes(q))
            : BOYS;

        if (!list.length) {
            grid.innerHTML = `
                <div class="ac-empty">
                    <i class="bi bi-search"></i>
                    <h3>No matches</h3>
                    <p>No delivery boys found for "${esc(query)}".</p>
                </div>`;
            return;
        }

        grid.innerHTML = list.map(b => {
            const count = Number(b.apt_count || 0);
            const has = count > 0;
            return `
                <div class="ac-card" data-id="${b.id}">
                    <div class="ac-card-head">
                        <div class="ac-avatar">${initial(b.full_name)}</div>
                        <div class="ac-card-info">
                            <p class="ac-card-name">${esc(b.full_name)}</p>
                            <p class="ac-card-meta">${esc(b.delivery_code || '')} · ${esc(b.mobile_number || '')}</p>
                        </div>
                    </div>
                    <div class="ac-card-foot">
                        <span class="ac-count ${has ? 'has' : 'none'}">
                            <i class="bi ${has ? 'bi-building-check' : 'bi-building'}"></i>
                            ${count} ${count === 1 ? 'apartment' : 'apartments'}
                        </span>
                        <span class="ac-card-go">
                            <i class="bi bi-arrow-right"></i>
                        </span>
                    </div>
                </div>
            `;
        }).join("");
    }

    /* Boy search — LIVE filter */
    boySearch?.addEventListener("input", function () {
        renderBoyCards(this.value);
    });

    /* Click a card → open modal */
    grid?.addEventListener("click", function (e) {
        const card = e.target.closest(".ac-card");
        if (!card) return;
        const id = Number(card.dataset.id);
        const boy = BOYS.find(b => Number(b.id) === id);
        if (!boy) return;
        openModal(boy);
    });

    /* =========================================================
       OPEN / CLOSE MODAL
       ========================================================= */
    function openModal(boy) {
        currentBoy = boy;

        modalAvatar.textContent = initial(boy.full_name);
        modalTitle.textContent = boy.full_name;
        modalSub.textContent = `${boy.delivery_code || ''} · ${boy.mobile_number || ''}`;

        selectedCodes = new Set();
        updateSelectedPill();

        switchTab("available");
        if (aptSearch) aptSearch.value = "";

        availableApts = [];
        allocatedApts = [];

        availList.innerHTML = `<div class="ac-empty-inline"><i class="bi bi-hourglass-split"></i>Loading...</div>`;
        allocList.innerHTML = `<div class="ac-empty-inline"><i class="bi bi-hourglass-split"></i>Loading...</div>`;
        allocCount.textContent = "0";

        overlay.classList.add("show");
        overlay.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";

        loadBoyApartments(boy.id);
    }

    function closeModal() {
        overlay.classList.remove("show");
        overlay.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
        currentBoy = null;
        selectedCodes = new Set();
    }

    modalClose?.addEventListener("click", closeModal);
    cancelBtn?.addEventListener("click", closeModal);
    overlay?.addEventListener("click", e => {
        if (e.target === overlay) closeModal();
    });
    document.addEventListener("keydown", e => {
        if (e.key === "Escape" && overlay.classList.contains("show")) closeModal();
    });

    /* =========================================================
       TABS
       ========================================================= */
    function switchTab(tab) {
        modalTabs.forEach(t => t.classList.toggle("active", t.dataset.tab === tab));

        if (tab === "available") {
            availTab.style.display = "";
            allocTab.style.display = "none";
            foot.style.display = "flex";
        } else {
            availTab.style.display = "none";
            allocTab.style.display = "";
            foot.style.display = "none";
        }
    }

    modalTabs.forEach(tab => {
        tab.addEventListener("click", () => switchTab(tab.dataset.tab));
    });

    /* =========================================================
       LOAD APARTMENTS FOR A BOY
       ========================================================= */
    function loadBoyApartments(boyId) {
        fetch(ADMIN_URL + "ajax/get-apartments-for-boy.php?boy_id=" + encodeURIComponent(boyId), {
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !res.data) {
                    availList.innerHTML = `<div class="ac-empty-inline"><i class="bi bi-exclamation-circle"></i>Failed to load.</div>`;
                    allocList.innerHTML = `<div class="ac-empty-inline"><i class="bi bi-exclamation-circle"></i>Failed to load.</div>`;
                    return;
                }

                availableApts = Array.isArray(res.data.available) ? res.data.available : [];
                allocatedApts = Array.isArray(res.data.allocated) ? res.data.allocated : [];

                renderAvailable();
                renderAllocated();

                allocCount.textContent = allocatedApts.length;
            })
            .catch(() => {
                availList.innerHTML = `<div class="ac-empty-inline"><i class="bi bi-wifi-off"></i>Unable to connect.</div>`;
                allocList.innerHTML = `<div class="ac-empty-inline"><i class="bi bi-wifi-off"></i>Unable to connect.</div>`;
            });
    }

    /* =========================================================
       RENDER AVAILABLE LIST
       ========================================================= */
    function renderAvailable() {
        if (!availList) return;

        if (!availableApts.length) {
            availList.innerHTML = `
                <div class="ac-empty-inline">
                    <i class="bi bi-check2-circle"></i>
                    All apartments are already allocated to this delivery boy.
                </div>`;
            return;
        }

        availList.innerHTML = availableApts.map(a => {
            const checked = selectedCodes.has(a.apartment_code);
            return `
                <div class="ac-item ${checked ? 'is-checked' : ''}"
                     data-code="${esc(a.apartment_code)}">
                    <div class="ac-check"></div>
                    <div class="ac-item-info">
                        <p class="ac-item-name">${esc(a.apartment_name)}</p>
                        <p class="ac-item-code">#${esc(a.apartment_code)}</p>
                    </div>
                </div>
            `;
        }).join("");
    }

    /* =========================================================
       RENDER ALLOCATED LIST
       ========================================================= */
    function renderAllocated() {
        if (!allocList) return;

        if (!allocatedApts.length) {
            allocList.innerHTML = `
                <div class="ac-empty-inline">
                    <i class="bi bi-inbox"></i>
                    No apartments allocated yet to this delivery boy.
                </div>`;
            return;
        }

        allocList.innerHTML = allocatedApts.map(a => `
            <div class="ac-item is-existing">
                <div class="ac-existing-icon"><i class="bi bi-check-lg"></i></div>
                <div class="ac-item-info">
                    <p class="ac-item-name">${esc(a.apartment_name)}</p>
                    <p class="ac-item-code">#${esc(a.apartment_code)}</p>
                </div>
                <button type="button" class="ac-modal-close js-remove-alloc"
                        data-code="${esc(a.apartment_code)}"
                        title="Remove"
                        style="width:30px;height:30px;font-size:12px;background:#fff;border:1.5px solid #f0d6d8;color:#b51f2c;">
                    <i class="bi bi-trash3"></i>
                </button>
            </div>
        `).join("");
    }

    /* =========================================================
       TOGGLE AVAILABLE CHECKBOX
       ========================================================= */
    availList?.addEventListener("click", function (e) {
        const item = e.target.closest(".ac-item");
        if (!item) return;

        const code = item.dataset.code;
        if (!code) return;

        if (selectedCodes.has(code)) {
            selectedCodes.delete(code);
            item.classList.remove("is-checked");
        } else {
            selectedCodes.add(code);
            item.classList.add("is-checked");
        }
        updateSelectedPill();
    });

    function updateSelectedPill() {
        const n = selectedCodes.size;
        if (selectedPill && selectedText) {
            if (n > 0) {
                selectedPill.style.display = "inline-flex";
                selectedText.textContent = n + " selected";
            } else {
                selectedPill.style.display = "none";
            }
        }
    }

    /* =========================================================
       SEARCH APARTMENTS IN MODAL — LIVE filter
       ========================================================= */
    aptSearch?.addEventListener("input", function () {
        const q = this.value.trim().toLowerCase();
        availList.querySelectorAll(".ac-item").forEach(it => {
            const name = it.querySelector(".ac-item-name")?.textContent.toLowerCase() || "";
            const code = it.querySelector(".ac-item-code")?.textContent.toLowerCase() || "";
            const hay = name + " " + code;
            it.style.display = (!q || hay.includes(q)) ? "" : "none";
        });
    });

    /* =========================================================
       SAVE — allocate selected
       ========================================================= */
    saveBtn?.addEventListener("click", function () {
        if (!currentBoy) return;

        if (selectedCodes.size === 0) {
            showToast("Please tick at least one apartment.", "error");
            return;
        }

        saveBtn.disabled = true;
        saveText.innerHTML = '<span class="btn-spinner"></span> Allocating...';

        const fd = new FormData();
        fd.append("delivery_boy_id", currentBoy.id);
        fd.append("apartment_codes", JSON.stringify([...selectedCodes]));

        fetch(ADMIN_URL + "ajax/allocate-apartments.php", {
            method: "POST",
            body: fd,
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                saveBtn.disabled = false;
                saveText.textContent = "Allocate Selected";

                if (!res || !res.success) {
                    showToast((res && res.message) || "Failed to allocate.", "error");
                    return;
                }

                showToast(res.message || "Allocated successfully.");

                /* Refresh the boy's apartment counts on the card */
                if (res.data && typeof res.data.new_count === "number") {
                    const boy = BOYS.find(b => Number(b.id) === Number(currentBoy.id));
                    if (boy) boy.apt_count = res.data.new_count;
                    renderBoyCards(boySearch ? boySearch.value : "");
                }

                /* Reload the modal lists */
                selectedCodes = new Set();
                updateSelectedPill();
                loadBoyApartments(currentBoy.id);
            })
            .catch(() => {
                saveBtn.disabled = false;
                saveText.textContent = "Allocate Selected";
                showToast("Unable to connect.", "error");
            });
    });

    /* =========================================================
       REMOVE ALLOCATION (from Allocated tab)
       — no confirm dialog, deletes immediately
       ========================================================= */
    allocList?.addEventListener("click", function (e) {
        const btn = e.target.closest(".js-remove-alloc");
        if (!btn || !currentBoy) return;

        const code = btn.dataset.code;
        if (!code) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="btn-spinner"></span>';

        const fd = new FormData();
        fd.append("delivery_boy_id", currentBoy.id);
        fd.append("apartment_code", code);

        fetch(ADMIN_URL + "ajax/remove-allocation.php", {
            method: "POST",
            body: fd,
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success) {
                    showToast((res && res.message) || "Failed to remove.", "error");
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-trash3"></i>';
                    return;
                }

                showToast("Removed.");

                if (res.data && typeof res.data.new_count === "number") {
                    const boy = BOYS.find(b => Number(b.id) === Number(currentBoy.id));
                    if (boy) boy.apt_count = res.data.new_count;
                    renderBoyCards(boySearch ? boySearch.value : "");
                }

                loadBoyApartments(currentBoy.id);
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-trash3"></i>';
                showToast("Unable to connect.", "error");
            });
    });

    /* =========================================================
       INIT
       ========================================================= */
    renderBoyCards("");

})();