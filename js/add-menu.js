/* =========================================================
   MRS MILL@ — ADD MENU UX
   File: ./js/add-menu.js
   - Common time window
   - Multiple product rows
   - Each row: product dropdown (with search) + variant MULTI-select
   - Stock: unlimited OR count
   - Duplicate recent menu
   - Bottom "Add Another Product" button
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    const PRODUCTS     = Array.isArray(window.PRODUCTS) ? window.PRODUCTS : [];
    const RECENT_MENUS = Array.isArray(window.RECENT_MENUS) ? window.RECENT_MENUS : [];

    /* ---------------- DOM ---------------- */

    const form     = document.getElementById("menuForm");
    const saveBtn  = document.getElementById("saveBtn");
    const saveText = document.getElementById("saveBtnText");

    const addProductBtn       = document.getElementById("addProductBtn");
    const addProductBtnBottom = document.getElementById("addProductBtnBottom");
    const prodRows            = document.getElementById("prodRows");
    const prodEmpty           = document.getElementById("prodEmpty");

    const menuNameInput = document.getElementById("menu_name");

    const startDate = document.getElementById("start_date");
    const startTime = document.getElementById("start_time");
    const startAmPm = document.getElementById("start_ampm");
    const endDate   = document.getElementById("end_date");
    const endTime   = document.getElementById("end_time");
    const endAmPm   = document.getElementById("end_ampm");
    const durationPreview = document.getElementById("durationPreview");

    const duplicateWrap   = document.getElementById("duplicateWrap");
    const duplicateBtn    = document.getElementById("duplicateBtn");
    const duplicateSearch = document.getElementById("duplicateSearch");
    const duplicateList   = document.getElementById("duplicateList");

    const errorOverlay = document.getElementById("errorOverlay");
    const errorText    = document.getElementById("errorText");
    const errorTitle   = document.getElementById("errorTitle");
    const errorOkBtn   = document.getElementById("errorOkBtn");

    const successOverlay = document.getElementById("successOverlay");
    const successText    = document.getElementById("successText");

    if (!form) return;

    /* ---------------- STATE ---------------- */

    let rowCounter = 0;

    /* ---------------- HELPERS ---------------- */

    function escapeHtml(str) {
        return String(str ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function setLoading(isLoading) {
        saveBtn.disabled = isLoading;
        saveText.innerHTML = isLoading
            ? '<span class="btn-spinner"></span> Saving...'
            : 'Save Menu';
    }

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

    if (errorOkBtn) errorOkBtn.addEventListener("click", closeError);
    errorOverlay.addEventListener("click", e => {
        if (e.target === errorOverlay) closeError();
    });

    /* ---------------- DATE / TIME ---------------- */

    function to24h(time12h, ampm) {
        if (!time12h) return "00:00";
        const parts = time12h.split(":");
        let h = parseInt(parts[0], 10) || 0;
        const m = parseInt(parts[1], 10) || 0;
        ampm = (ampm || "AM").toUpperCase();
        if (ampm === "AM") { if (h === 12) h = 0; }
        else { if (h !== 12) h += 12; }
        return String(h).padStart(2, "0") + ":" + String(m).padStart(2, "0");
    }

    function to12hFrom24(hhmm) {
        if (!hhmm) return ["09:00", "AM"];
        const parts = hhmm.split(":");
        let h = parseInt(parts[0], 10) || 0;
        const m = parseInt(parts[1], 10) || 0;
        const ampm = h >= 12 ? "PM" : "AM";
        let h12 = h % 12;
        if (h12 === 0) h12 = 12;
        return [String(h12).padStart(2, "0") + ":" + String(m).padStart(2, "0"), ampm];
    }

    function getStartDateObj() {
        if (!startDate || !startTime) return null;
        const t = to24h(startTime.value, startAmPm ? startAmPm.value : "AM");
        const d = new Date(startDate.value + "T" + t + ":00");
        return isNaN(d.getTime()) ? null : d;
    }

    function getEndDateObj() {
        if (!endDate || !endTime) return null;
        const t = to24h(endTime.value, endAmPm ? endAmPm.value : "PM");
        const d = new Date(endDate.value + "T" + t + ":00");
        return isNaN(d.getTime()) ? null : d;
    }

    function updateDurationPreview() {
        if (!durationPreview) return;
        const s = getStartDateObj();
        const e = getEndDateObj();
        if (!s || !e) { durationPreview.textContent = ""; return; }

        const diff = e.getTime() - s.getTime();
        if (diff <= 0) {
            durationPreview.style.color = "#c62828";
            durationPreview.textContent = "⚠ End must be after start.";
            return;
        }
        durationPreview.style.color = "#2e7d32";
        const mins = Math.floor(diff / 60000);
        const d = Math.floor(mins / 1440);
        const h = Math.floor((mins % 1440) / 60);
        const m = mins % 60;
        const parts = [];
        if (d) parts.push(d + "d");
        if (h) parts.push(h + "h");
        if (m) parts.push(m + "m");
        durationPreview.textContent = "Duration: " + parts.join(" ");
    }

    [startDate, startTime, startAmPm, endDate, endTime, endAmPm].forEach(el => {
        if (!el) return;
        el.addEventListener("input", updateDurationPreview);
        el.addEventListener("change", updateDurationPreview);
    });

    /* =========================================================
       GENERIC DROPDOWN
       ========================================================= */

    function closeAllDropdowns(except) {
        document.querySelectorAll(".dd.open").forEach(dd => {
            if (dd !== except) dd.classList.remove("open");
        });
    }

    document.addEventListener("click", e => {
        if (!e.target.closest(".dd")) closeAllDropdowns();
        if (!e.target.closest("#duplicateWrap")) {
            duplicateWrap?.classList.remove("open");
        }
    });

    function makeDropdown(config) {
        const dd = config.trigger.closest(".dd");

        function render() {
            const filter = config.searchInput ? config.searchInput.value : "";
            const items  = config.getItems(filter) || [];

            if (!items.length) {
                config.listEl.innerHTML = `
                    <div class="dd-empty">
                        <i class="bi bi-search"></i>
                        No results for "${escapeHtml(filter || "")}"
                    </div>`;
                return;
            }

            config.listEl.innerHTML = items.map(it => `
                <div class="dd-item ${it.selected ? "selected" : ""}"
                     data-id="${escapeHtml(String(it.id))}">
                    ${config.multi ? `<div class="check"><i class="bi bi-check-lg"></i></div>` : ``}
                    <div class="info">
                        <div class="nm">${escapeHtml(it.label)}</div>
                    </div>
                    ${it.meta ? `<span class="meta">${escapeHtml(it.meta)}</span>` : ""}
                    ${it.price !== undefined ? `<span class="price">₹${Number(it.price).toFixed(2)}</span>` : ""}
                </div>
            `).join("");
        }

        function open() {
            closeAllDropdowns(dd);
            dd.classList.add("open");
            if (config.searchInput) {
                config.searchInput.value = "";
                render();
                setTimeout(() => config.searchInput.focus(), 60);
            } else {
                render();
            }
        }

        function close() { dd.classList.remove("open"); }

        config.trigger.addEventListener("click", e => {
            e.stopPropagation();
            if (config.trigger.classList.contains("disabled")) return;
            if (dd.classList.contains("open")) close();
            else open();
        });

        if (config.searchInput) {
            config.searchInput.addEventListener("input", render);
            config.searchInput.addEventListener("click", e => e.stopPropagation());
        }

        config.listEl.addEventListener("click", e => {
            e.stopPropagation();
            const item = e.target.closest(".dd-item");
            if (!item) return;
            const id = item.dataset.id;

            config.onPick(id);

            if (!config.multi) close();
            else render();
        });

        return { render, open, close };
    }

    /* =========================================================
       PRODUCT ROW
       ========================================================= */

    function addProductRow(prefill) {
        rowCounter++;

        prefill = prefill || {};

        const row = document.createElement("div");
        row.className = "prod-row";
        row.dataset.rid = String(rowCounter);
        row.dataset.pcode = prefill.product_code || "";
        row.dataset.vids  = JSON.stringify(prefill.variant_ids || []);

        row.innerHTML = `
            <div class="prod-row-head">
                <div class="prod-row-badge">
                    <i class="bi bi-box"></i> Product #${rowCounter}
                </div>
                <button type="button" class="prod-row-remove" title="Remove product">
                    <i class="bi bi-trash3"></i>
                </button>
            </div>

            <div class="prod-row-grid">

                <div>
                    <label class="field-label">Product <span class="required">*</span></label>
                    <div class="dd" data-kind="product">
                        <button type="button" class="dd-trigger" data-placeholder="Select product...">
                            <span class="dd-placeholder">Select product...</span>
                            <i class="bi bi-chevron-down dd-arrow"></i>
                        </button>
                        <div class="dd-panel">
                            <div class="dd-search">
                                <i class="bi bi-search"></i>
                                <input type="text" placeholder="Search products...">
                            </div>
                            <div class="dd-list"></div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="field-label">Variants <span class="required">*</span></label>
                    <div class="dd" data-kind="variant">
                        <button type="button" class="dd-trigger disabled" data-placeholder="Pick product first">
                            <span class="dd-placeholder">Pick product first</span>
                            <i class="bi bi-chevron-down dd-arrow"></i>
                        </button>
                        <div class="dd-panel">
                            <div class="dd-search">
                                <i class="bi bi-search"></i>
                                <input type="text" placeholder="Search variants...">
                            </div>
                            <div class="dd-list"></div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="stock-mode-group">

                <div class="stock-mode-box">
                    <label class="field-label">Stock</label>
                    <label class="custom-check">
                        <input type="checkbox" class="js-stock-unlimited" ${prefill.stock_unlimited ? "checked" : ""}>
                        <span class="box"><i class="bi bi-check-lg"></i></span>
                        <span class="custom-check-text">Unlimited stock</span>
                    </label>
                </div>

                <div class="stock-count-box">
                    <label class="field-label">Stock Count</label>
                    <input type="number"
                           class="js-stock-count"
                           value="${prefill.stock_count !== undefined ? prefill.stock_count : 100}"
                           min="0"
                           step="1"
                           placeholder="Eg: 100">
                </div>

            </div>
        `;

        prodRows.appendChild(row);

        /* --- Product dropdown --- */
        const prodDD = row.querySelector('.dd[data-kind="product"]');
        const prodTrigger = prodDD.querySelector(".dd-trigger");
        const prodSearch  = prodDD.querySelector(".dd-search input");
        const prodList    = prodDD.querySelector(".dd-list");

        makeDropdown({
            trigger: prodTrigger,
            searchInput: prodSearch,
            listEl: prodList,
            multi: false,
            getItems: (filter) => {
                filter = (filter || "").toLowerCase().trim();
                return PRODUCTS
                    .filter(p => {
                        if (!filter) return true;
                        const name = String(p.product_name || "").toLowerCase();
                        const code = String(p.product_code || "").toLowerCase();
                        return name.includes(filter) || code.includes(filter);
                    })
                    .map(p => ({
                        id: p.product_code,
                        label: p.product_name,
                        meta: "#" + p.product_code,
                        selected: row.dataset.pcode === p.product_code
                    }));
            },
            onPick: (pcode) => {
                const product = PRODUCTS.find(p => p.product_code === pcode);
                if (!product) return;

                row.dataset.pcode = pcode;
                row.dataset.vids = "[]";

                renderProductTrigger(row);
                enableVariant(row, true);
                prodDD.classList.remove("open");
            }
        });

        /* --- Variant dropdown --- */
        const varDD = row.querySelector('.dd[data-kind="variant"]');
        const varTrigger = varDD.querySelector(".dd-trigger");
        const varSearch  = varDD.querySelector(".dd-search input");
        const varList    = varDD.querySelector(".dd-list");

        makeDropdown({
            trigger: varTrigger,
            searchInput: varSearch,
            listEl: varList,
            multi: true,
            getItems: (filter) => {
                filter = (filter || "").toLowerCase().trim();
                const pcode = row.dataset.pcode;
                if (!pcode) return [];

                const product = PRODUCTS.find(p => p.product_code === pcode);
                if (!product || !Array.isArray(product.variants)) return [];

                const selectedVids = JSON.parse(row.dataset.vids || "[]").map(Number);

                return product.variants
                    .filter(v => {
                        if (!filter) return true;
                        return String(v.quantity_name || "").toLowerCase().includes(filter);
                    })
                    .map(v => ({
                        id: v.id,
                        label: v.quantity_name,
                        price: v.price,
                        selected: selectedVids.includes(Number(v.id))
                    }));
            },
            onPick: (vid) => {
                vid = Number(vid);
                let vids = JSON.parse(row.dataset.vids || "[]").map(Number);

                if (vids.includes(vid)) vids = vids.filter(x => x !== vid);
                else vids.push(vid);
                row.dataset.vids = JSON.stringify(vids);

                renderVariantChips(row);
            }
        });

        varTrigger.addEventListener("click", e => {
            if (varTrigger.classList.contains("disabled")) {
                e.preventDefault();
                e.stopPropagation();
                showError("Please pick a product first.", "No product selected");
            }
        });

        /* --- Stock toggle --- */
        const unlimitedCb = row.querySelector(".js-stock-unlimited");
        const stockCount  = row.querySelector(".js-stock-count");
        const checkLabel  = row.querySelector(".custom-check");

        function applyStockUI() {
            const unlimited = unlimitedCb.checked;
            stockCount.disabled = unlimited;
            checkLabel.classList.toggle("checked", unlimited);
            if (unlimited) {
                stockCount.value = "0";
            } else if (stockCount.value === "0") {
                stockCount.value = "100";
            }
        }
        unlimitedCb.addEventListener("change", applyStockUI);
        applyStockUI();

        /* Prefill */
        if (prefill.product_code) {
            const product = PRODUCTS.find(p => p.product_code === prefill.product_code);
            if (product) {
                renderProductTrigger(row);
                enableVariant(row, true);
                renderVariantChips(row);
            }
        }

        refreshEmpty();
    }

    function renderProductTrigger(row) {
        const pcode = row.dataset.pcode;
        const product = PRODUCTS.find(p => p.product_code === pcode);
        if (!product) return;

        const trigger = row.querySelector('.dd[data-kind="product"] .dd-trigger');
        trigger.querySelectorAll(".dd-chip, .dd-placeholder").forEach(c => c.remove());

        const wrap = document.createElement("div");
        wrap.className = "dd-chips";
        wrap.innerHTML = `
            <span class="dd-chip">
                ${escapeHtml(product.product_name)}
                <span class="meta" style="background:transparent;padding:0;">#${escapeHtml(product.product_code)}</span>
            </span>
        `;
        trigger.insertBefore(wrap, trigger.firstChild);
    }

    function enableVariant(row, on) {
        const trigger = row.querySelector('.dd[data-kind="variant"] .dd-trigger');
        if (on) {
            trigger.classList.remove("disabled");
            const ph = trigger.querySelector(".dd-placeholder");
            if (ph) ph.textContent = "Select variants...";
        } else {
            trigger.classList.add("disabled");
        }
    }

    function renderVariantChips(row) {
        const pcode = row.dataset.pcode;
        const vids  = JSON.parse(row.dataset.vids || "[]").map(Number);
        const trigger = row.querySelector('.dd[data-kind="variant"] .dd-trigger');

        trigger.querySelectorAll(".dd-chip, .dd-placeholder").forEach(c => c.remove());

        if (!pcode || vids.length === 0) {
            const span = document.createElement("span");
            span.className = "dd-placeholder";
            span.textContent = pcode ? "Select variants..." : "Pick product first";
            trigger.appendChild(span);
            return;
        }

        const product = PRODUCTS.find(p => p.product_code === pcode);
        if (!product) return;

        const wrap = document.createElement("div");
        wrap.className = "dd-chips";

        vids.forEach(vid => {
            const v = (product.variants || []).find(x => Number(x.id) === vid);
            if (!v) return;
            const chip = document.createElement("span");
            chip.className = "dd-chip";
            chip.innerHTML = `
                ${escapeHtml(v.quantity_name)}
                <span class="x" data-vid="${vid}">×</span>
            `;
            chip.querySelector(".x").addEventListener("click", e => {
                e.stopPropagation();
                let arr = JSON.parse(row.dataset.vids || "[]").map(Number);
                arr = arr.filter(x => x !== vid);
                row.dataset.vids = JSON.stringify(arr);
                renderVariantChips(row);
            });
            wrap.appendChild(chip);
        });

        trigger.insertBefore(wrap, trigger.firstChild);
    }

    function renumberRows() {
        const rows = prodRows.querySelectorAll(".prod-row");
        rows.forEach((r, idx) => {
            const badge = r.querySelector(".prod-row-badge");
            if (badge) badge.innerHTML = `<i class="bi bi-box"></i> Product #${idx + 1}`;
        });
    }

    function refreshEmpty() {
        const rows = prodRows.querySelectorAll(".prod-row");
        prodEmpty.style.display = rows.length === 0 ? "block" : "none";
    }

    if (addProductBtn) {
        addProductBtn.addEventListener("click", () => addProductRow());
    }
    if (addProductBtnBottom) {
        addProductBtnBottom.addEventListener("click", () => {
            addProductRow();
            /* Scroll to the newly added row smoothly */
            const rows = prodRows.querySelectorAll(".prod-row");
            const last = rows[rows.length - 1];
            if (last) {
                last.scrollIntoView({ behavior: "smooth", block: "center" });
            }
        });
    }

    if (prodRows) {
        prodRows.addEventListener("click", e => {
            const btn = e.target.closest(".prod-row-remove");
            if (!btn) return;
            const row = btn.closest(".prod-row");
            if (!row) return;

            row.style.transition = "opacity .18s ease, transform .18s ease";
            row.style.opacity = "0";
            row.style.transform = "translateY(-6px)";

            setTimeout(() => {
                row.remove();
                renumberRows();
                refreshEmpty();
            }, 180);
        });
    }

    /* =========================================================
       DUPLICATE MENU DROPDOWN
       ========================================================= */

    function renderDuplicateList(filter) {
        filter = (filter || "").toLowerCase().trim();

        const items = RECENT_MENUS.filter(m => {
            if (!filter) return true;
            const n = String(m.menu_name || "").toLowerCase();
            const c = String(m.menu_code || "").toLowerCase();
            return n.includes(filter) || c.includes(filter);
        });

        if (!items.length) {
            duplicateList.innerHTML = `<div class="duplicate-empty">No menus found.</div>`;
            return;
        }

        duplicateList.innerHTML = items.map(m => `
            <div class="duplicate-item" data-menu-id="${Number(m.id)}">
                <div class="nm">
                    ${escapeHtml(m.menu_name)}
                    <span class="when">${escapeHtml(m.label || "")}</span>
                </div>
                <div class="meta-line">
                    #${escapeHtml(m.menu_code)}
                    &nbsp;·&nbsp;
                    ${Number(m.rows?.length || 0)} product(s)
                </div>
            </div>
        `).join("");
    }

    if (duplicateBtn) {
        duplicateBtn.addEventListener("click", e => {
            e.stopPropagation();
            duplicateWrap.classList.toggle("open");
            if (duplicateWrap.classList.contains("open")) {
                duplicateSearch.value = "";
                renderDuplicateList("");
                setTimeout(() => duplicateSearch.focus(), 60);
            }
        });
    }

    if (duplicateSearch) {
        duplicateSearch.addEventListener("input", () => renderDuplicateList(duplicateSearch.value));
        duplicateSearch.addEventListener("click", e => e.stopPropagation());
    }

    if (duplicateList) {
        duplicateList.addEventListener("click", e => {
            const item = e.target.closest(".duplicate-item");
            if (!item) return;

            const menuId = Number(item.dataset.menuId);
            const menu = RECENT_MENUS.find(x => Number(x.id) === menuId);
            if (!menu) return;

            /* Prefill name as "<name> (copy)" */
            menuNameInput.value = menu.menu_name + " (copy)";

            /* Prefill time window from the source menu */
            const s = new Date(menu.start_at.replace(" ", "T"));
            const e2 = new Date(menu.end_at.replace(" ", "T"));

            if (!isNaN(s.getTime())) {
                startDate.value = s.getFullYear() + "-" +
                    String(s.getMonth() + 1).padStart(2, "0") + "-" +
                    String(s.getDate()).padStart(2, "0");

                const [sh, sa] = to12hFrom24(
                    String(s.getHours()).padStart(2, "0") + ":" +
                    String(s.getMinutes()).padStart(2, "0")
                );
                startTime.value = sh;
                startAmPm.value = sa;
            }

            if (!isNaN(e2.getTime())) {
                endDate.value = e2.getFullYear() + "-" +
                    String(e2.getMonth() + 1).padStart(2, "0") + "-" +
                    String(e2.getDate()).padStart(2, "0");

                const [eh, ea] = to12hFrom24(
                    String(e2.getHours()).padStart(2, "0") + ":" +
                    String(e2.getMinutes()).padStart(2, "0")
                );
                endTime.value = eh;
                endAmPm.value = ea;
            }

            /* Rebuild product rows */
            prodRows.innerHTML = "";
            rowCounter = 0;

            const rows = Array.isArray(menu.rows) ? menu.rows : [];
            if (rows.length === 0) {
                addProductRow();
            } else {
                rows.forEach(r => addProductRow(r));
            }

            refreshEmpty();
            updateDurationPreview();

            /* Close dropdown */
            duplicateWrap.classList.remove("open");

            /* Scroll to the form top */
            document.querySelector(".menu-form-card")?.scrollIntoView({ behavior: "smooth", block: "start" });
        });
    }

    /* =========================================================
       COLLECT / VALIDATE / SUBMIT
       ========================================================= */

    function collectRows() {
        const rows = prodRows.querySelectorAll(".prod-row");
        const out = [];

        rows.forEach((r, idx) => {
            const pcode = r.dataset.pcode || "";
            const vids  = JSON.parse(r.dataset.vids || "[]").map(Number);

            const unlimited = r.querySelector(".js-stock-unlimited")?.checked ? 1 : 0;
            const stockRaw  = r.querySelector(".js-stock-count")?.value || "0";
            const stock     = parseInt(stockRaw, 10);

            out.push({
                index: idx + 1,
                product_code: pcode,
                variant_ids: vids,
                stock_unlimited: unlimited,
                stock_count: isNaN(stock) ? 0 : stock
            });
        });

        return out;
    }

    function validateRows() {
        const rows = collectRows();

        if (rows.length === 0) {
            showError("Please add at least one product.", "No products");
            return null;
        }

        for (const r of rows) {
            const label = "Product #" + r.index;
            if (!r.product_code) {
                showError(label + ": please pick a product.", "Product missing");
                return null;
            }
            if (!r.variant_ids || r.variant_ids.length === 0) {
                showError(label + ": pick at least one variant.", "Variant missing");
                return null;
            }
            if (!r.stock_unlimited && r.stock_count < 0) {
                showError(label + ": stock count must be 0 or greater.", "Invalid stock");
                return null;
            }
        }

        return rows;
    }

    form.addEventListener("submit", e => {
        e.preventDefault();

        const name = menuNameInput.value.trim();
        if (!name) return showError("Menu name is required.", "Missing menu name");

        const s  = getStartDateObj();
        const en = getEndDateObj();

        if (!s)  return showError("Please choose a valid start date/time.", "Invalid start");
        if (!en) return showError("Please choose a valid end date/time.", "Invalid end");
        if (en.getTime() <= s.getTime())
            return showError("End date/time must be after start.", "Invalid time range");

        const rows = validateRows();
        if (!rows) return;

        function fmt(d) {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, "0");
            const da = String(d.getDate()).padStart(2, "0");
            const h = String(d.getHours()).padStart(2, "0");
            const mi = String(d.getMinutes()).padStart(2, "0");
            return `${y}-${m}-${da} ${h}:${mi}:00`;
        }

        setLoading(true);

        const formData = new FormData();
        formData.append("menu_name", name);
        formData.append("start_at", fmt(s));
        formData.append("end_at", fmt(en));
        formData.append("rows", JSON.stringify(rows));

        fetch(BASE_URL + "ajax/add-menu.php", {
            method: "POST",
            body: formData,
            credentials: "same-origin"
        })
        .then(r => r.json().catch(() => ({ success: false, message: "Unexpected server response." })))
        .then(data => {
            if (data.success) {
                if (successText)
                    successText.textContent = data.message || "Menu saved successfully.";

                successOverlay.classList.add("show");
                successOverlay.setAttribute("aria-hidden", "false");

                setLoading(false);
                form.reset();
                prodRows.innerHTML = "";
                rowCounter = 0;
                refreshEmpty();
                updateDurationPreview();
                addProductRow();
            } else {
                showError(data.message || "Failed to save menu.", "Save failed");
                setLoading(false);
            }
        })
        .catch(() => {
            showError("Unable to connect to server.", "Network error");
            setLoading(false);
        });
    });

    if (successOverlay) {
        successOverlay.addEventListener("click", e => {
            if (e.target === successOverlay) {
                successOverlay.classList.remove("show");
                successOverlay.setAttribute("aria-hidden", "true");
            }
        });
    }

    /* ---------------- INIT ---------------- */

    updateDurationPreview();
    addProductRow();

})();