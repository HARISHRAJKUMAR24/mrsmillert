(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL) ? window.ADMIN_URL : "./";
    const PRODUCTS = Array.isArray(window.PRODUCTS) ? window.PRODUCTS : [];
    const RECENT_MENUS = Array.isArray(window.RECENT_MENUS) ? window.RECENT_MENUS : [];

    const form = document.getElementById("menuForm");
    const saveBtn = document.getElementById("saveBtn");
    const saveText = document.getElementById("saveBtnText");
    const addProductBtn = document.getElementById("addProductBtn");
    const addProductBtnBottom = document.getElementById("addProductBtnBottom");
    const prodRows = document.getElementById("prodRows");
    const prodEmpty = document.getElementById("prodEmpty");
    const menuNameInput = document.getElementById("menu_name");

    const statusInput = document.getElementById("menu_status");
    const statusRow = document.getElementById("statusToggleRow");
    const statusTitle = document.getElementById("statusToggleTitle");
    const statusDesc = document.getElementById("statusToggleDesc");

    const startDate = document.getElementById("start_date");
    const startTime = document.getElementById("start_time");
    const startAmPm = document.getElementById("start_ampm");
    const endDate = document.getElementById("end_date");
    const endTime = document.getElementById("end_time");
    const endAmPm = document.getElementById("end_ampm");
    const durationPreview = document.getElementById("durationPreview");

    const duplicateWrap = document.getElementById("duplicateWrap");
    const duplicateBtn = document.getElementById("duplicateBtn");
    const duplicateSearch = document.getElementById("duplicateSearch");
    const duplicateList = document.getElementById("duplicateList");

    const errorOverlay = document.getElementById("errorOverlay");
    const errorText = document.getElementById("errorText");
    const errorOkBtn = document.getElementById("errorOkBtn");
    const successOverlay = document.getElementById("successOverlay");
    const successText = document.getElementById("successText");

    if (!form) return;

    let rowCounter = 0;

    function escapeHtml(str) {
        return String(str ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function setLoading(isLoading) {
        saveBtn.disabled = isLoading;
        saveText.innerHTML = isLoading ? '<span class="btn-spinner"></span> Saving...' : 'Save Menu';
    }

    function showError(message) {
        if (errorText) errorText.textContent = message || "Something went wrong.";
        errorOverlay.classList.add("show");
        errorOverlay.setAttribute("aria-hidden", "false");
    }

    if (errorOkBtn) errorOkBtn.addEventListener("click", () => errorOverlay.classList.remove("show"));
    errorOverlay.addEventListener("click", e => { if (e.target === errorOverlay) errorOverlay.classList.remove("show"); });

    function applyStatusUI() {
        if (!statusInput) return;
        const active = statusInput.checked;
        if (statusRow) statusRow.classList.toggle("is-active", active);
        if (statusTitle) statusTitle.textContent = active ? "Active" : "Inactive";
        if (statusDesc) statusDesc.textContent = active ? "Menu will be visible to customers." : "Menu will be hidden from customers.";
    }
    if (statusInput) statusInput.addEventListener("change", applyStatusUI);

    function to24h(time12h, ampm) {
        if (!time12h) return "00:00";
        const parts = time12h.split(":");
        let h = parseInt(parts[0], 10) || 0;
        const m = parseInt(parts[1], 10) || 0;
        ampm = (ampm || "AM").toUpperCase();
        if (ampm === "AM") { if (h === 12) h = 0; } else { if (h !== 12) h += 12; }
        return String(h).padStart(2, "0") + ":" + String(m).padStart(2, "0");
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
        if (el) { el.addEventListener("input", updateDurationPreview); el.addEventListener("change", updateDurationPreview); }
    });

    function closeAllDropdowns(except) {
        document.querySelectorAll(".dd.open").forEach(dd => { if (dd !== except) dd.classList.remove("open"); });
    }

    document.addEventListener("click", e => {
        if (!e.target.closest(".dd")) closeAllDropdowns();
        if (!e.target.closest("#duplicateWrap")) duplicateWrap?.classList.remove("open");
    });

    /* =========================================================
       DUPLICATE MENU LOGIC
    ========================================================= */
    function renderDuplicateList(filter) {
        if (!duplicateList) return;
        filter = (filter || "").toLowerCase().trim();

        const filteredMenus = RECENT_MENUS.filter(m => {
            if (!filter) return true;
            const n = (m.menu_name || "").toLowerCase();
            const c = (m.menu_code || "").toLowerCase();
            return n.includes(filter) || c.includes(filter);
        });

        if (!filteredMenus.length) {
            duplicateList.innerHTML = `<div style="padding:20px;text-align:center;font-size:11px;color:#948c82;">No menus found.</div>`;
            return;
        }

        duplicateList.innerHTML = filteredMenus.map(m => {
            const isActive = Number(m.status) === 1;
            const statusColor = isActive ? '#2e7d32' : '#948c82';
            const statusBg = isActive ? '#e8f6ea' : '#f5f2ec';

            return `
                <div class="duplicate-item" data-id="${m.id}">
                    <div class="nm">
                        ${escapeHtml(m.menu_name)} 
                        <span class="when">${escapeHtml(m.label || "")}</span>
                        <span style="font-size:9px;font-weight:800;padding:2px 6px;border-radius:4px;background:${statusBg};color:${statusColor};text-transform:uppercase;">${escapeHtml(m.status_label || "")}</span>
                    </div>
                    <span style="font-size:10px;color:#948c82;">#${escapeHtml(m.menu_code)}</span>
                </div>
            `;
        }).join("");

        duplicateList.querySelectorAll(".duplicate-item").forEach(item => {
            item.addEventListener("click", function () {
                const id = Number(this.dataset.id);
                const menu = RECENT_MENUS.find(x => Number(x.id) === id);
                if (menu) applyDuplicateMenu(menu);
                duplicateWrap.classList.remove("open");
            });
        });
    }

    function applyDuplicateMenu(menu) {
        if (menuNameInput) menuNameInput.value = menu.menu_name + " (Copy)";
        if (statusInput) {
            statusInput.checked = Number(menu.status) === 1;
            applyStatusUI();
        }

        prodRows.innerHTML = "";
        rowCounter = 0;

        if (Array.isArray(menu.rows) && menu.rows.length > 0) {
            menu.rows.forEach(rowData => {
                const vData = (rowData.variant_data || []).map(v => ({
                    id: Number(v.id),
                    stock_unlimited: Number(v.stock_unlimited) || 0,
                    stock_count: Number(v.stock_count) || 0
                }));

                addProductRow({
                    product_code: rowData.product_code,
                    variant_data: vData
                });
            });
        } else {
            addProductRow();
        }

        if (window.sToast) {
            window.sToast("Menu duplicated! Review and click Save.", "success");
        }
    }

    if (duplicateBtn) {
        duplicateBtn.addEventListener("click", function (e) {
            e.preventDefault();
            const isOpen = duplicateWrap.classList.contains("open");
            duplicateWrap.classList.toggle("open", !isOpen);

            if (!isOpen) {
                if (duplicateSearch) {
                    duplicateSearch.value = "";
                    renderDuplicateList("");
                    setTimeout(() => duplicateSearch.focus(), 60);
                } else {
                    renderDuplicateList("");
                }
            }
        });
    }

    if (duplicateSearch) {
        duplicateSearch.addEventListener("input", function () {
            renderDuplicateList(this.value);
        });
    }

    /* =========================================================
       PRODUCT SELECTION / DROPDOWN LOGIC
    ========================================================= */
    function makeDropdown(config) {
        const dd = config.trigger.closest(".dd");
        function render() {
            const filter = config.searchInput ? config.searchInput.value : "";
            const items = config.getItems(filter) || [];
            if (!items.length) { config.listEl.innerHTML = `<div class="dd-empty">No results...</div>`; return; }
            config.listEl.innerHTML = items.map(it => `
                <div class="dd-item ${it.selected ? "selected" : ""}" data-id="${escapeHtml(String(it.id))}">
                    ${config.multi ? `<div class="check"><i class="bi bi-check-lg"></i></div>` : ``}
                    <div class="info"><div class="nm">${escapeHtml(it.label)}</div></div>
                    ${it.price !== undefined ? `<span class="price">₹${Number(it.price).toFixed(2)}</span>` : ""}
                </div>
            `).join("");
        }
        function open() {
            closeAllDropdowns(dd);
            dd.classList.add("open");
            if (config.searchInput) { config.searchInput.value = ""; render(); setTimeout(() => config.searchInput.focus(), 60); }
            else render();
        }
        function close() { dd.classList.remove("open"); }
        config.trigger.addEventListener("click", e => {
            e.stopPropagation();
            if (config.trigger.classList.contains("disabled")) return;
            if (dd.classList.contains("open")) close(); else open();
        });
        if (config.searchInput) config.searchInput.addEventListener("input", render);
        config.listEl.addEventListener("click", e => {
            e.stopPropagation();
            const item = e.target.closest(".dd-item");
            if (!item) return;
            config.onPick(item.dataset.id);
            if (!config.multi) close(); else render();
        });
        return { render, open, close };
    }

    function addProductRow(prefill) {
        rowCounter++;
        prefill = prefill || {};
        const row = document.createElement("div");
        row.className = "prod-row";
        row.dataset.rid = String(rowCounter);
        row.dataset.pcode = prefill.product_code || "";

        let vDataInit = Array.isArray(prefill.variant_data) ? prefill.variant_data : [];
        row.dataset.vdata = JSON.stringify(vDataInit);

        row.innerHTML = `
            <div class="prod-row-head">
                <div class="prod-row-badge"><i class="bi bi-box"></i> Product #${rowCounter}</div>
                <button type="button" class="prod-row-remove" title="Remove product"><i class="bi bi-trash3"></i></button>
            </div>
            <div class="prod-row-grid">
                <div>
                    <label class="field-label">Product <span class="required">*</span></label>
                    <div class="dd" data-kind="product">
                        <button type="button" class="dd-trigger" data-placeholder="Select product..."><span class="dd-placeholder">Select product...</span><i class="bi bi-chevron-down dd-arrow"></i></button>
                        <div class="dd-panel">
                            <div class="dd-search"><i class="bi bi-search"></i><input type="text" placeholder="Search products..."></div>
                            <div class="dd-list"></div>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="field-label">Variants <span class="required">*</span></label>
                    <div class="dd" data-kind="variant">
                        <button type="button" class="dd-trigger disabled" data-placeholder="Pick product first"><span class="dd-placeholder">Pick product first</span><i class="bi bi-chevron-down dd-arrow"></i></button>
                        <div class="dd-panel">
                            <div class="dd-search"><i class="bi bi-search"></i><input type="text" placeholder="Search variants..."></div>
                            <div class="dd-list"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="variant-stock-list" style="display:none;"></div>
        `;
        prodRows.appendChild(row);

        const prodDD = row.querySelector('.dd[data-kind="product"]');
        makeDropdown({
            trigger: prodDD.querySelector(".dd-trigger"),
            searchInput: prodDD.querySelector(".dd-search input"),
            listEl: prodDD.querySelector(".dd-list"),
            multi: false,
            getItems: (filter) => {
                filter = (filter || "").toLowerCase().trim();
                return PRODUCTS.filter(p => (!filter || String(p.product_name).toLowerCase().includes(filter) || String(p.product_code).toLowerCase().includes(filter)))
                    .map(p => ({ id: p.product_code, label: p.product_name, meta: "#" + p.product_code, selected: row.dataset.pcode === p.product_code }));
            },
            onPick: (pcode) => {
                const product = PRODUCTS.find(p => p.product_code === pcode);
                if (!product) return;
                row.dataset.pcode = pcode;
                row.dataset.vdata = "[]";
                renderProductTrigger(row);
                enableVariant(row, true);
                renderVariantChips(row);
                renderVariantStockUI(row);
                prodDD.classList.remove("open");
            }
        });

        const varDD = row.querySelector('.dd[data-kind="variant"]');
        makeDropdown({
            trigger: varDD.querySelector(".dd-trigger"),
            searchInput: varDD.querySelector(".dd-search input"),
            listEl: varDD.querySelector(".dd-list"),
            multi: true,
            getItems: (filter) => {
                filter = (filter || "").toLowerCase().trim();
                const pcode = row.dataset.pcode;
                if (!pcode) return [];
                const product = PRODUCTS.find(p => p.product_code === pcode);
                if (!product || !Array.isArray(product.variants)) return [];

                const selectedData = JSON.parse(row.dataset.vdata || "[]");
                const selectedIds = selectedData.map(v => Number(v.id));

                return product.variants.filter(v => !filter || String(v.quantity_name).toLowerCase().includes(filter))
                    .map(v => ({ id: v.id, label: v.quantity_name, price: v.price, selected: selectedIds.includes(Number(v.id)) }));
            },
            onPick: (vid) => {
                vid = Number(vid);
                let vData = JSON.parse(row.dataset.vdata || "[]");

                const exists = vData.find(v => Number(v.id) === vid);
                if (exists) {
                    vData = vData.filter(v => Number(v.id) !== vid);
                } else {
                    vData.push({ id: vid, stock_unlimited: 0, stock_count: 100 });
                }
                row.dataset.vdata = JSON.stringify(vData);
                renderVariantChips(row);
                renderVariantStockUI(row);
            }
        });

        varDD.querySelector(".dd-trigger").addEventListener("click", e => {
            if (e.currentTarget.classList.contains("disabled")) {
                e.preventDefault(); e.stopPropagation();
                showError("Please pick a product first.");
            }
        });

        if (prefill.product_code) {
            const product = PRODUCTS.find(p => p.product_code === prefill.product_code);
            if (product) {
                renderProductTrigger(row);
                enableVariant(row, true);

                let validData = vDataInit.filter(item => product.variants.some(v => Number(v.id) === Number(item.id)));
                row.dataset.vdata = JSON.stringify(validData);

                renderVariantChips(row);
                renderVariantStockUI(row);
            } else {
                row.dataset.pcode = ""; row.dataset.vdata = "[]";
            }
        }

        refreshEmpty();
    }

    function renderProductTrigger(row) {
        const product = PRODUCTS.find(p => p.product_code === row.dataset.pcode);
        if (!product) return;
        const trigger = row.querySelector('.dd[data-kind="product"] .dd-trigger');
        trigger.querySelectorAll(".dd-chip, .dd-placeholder").forEach(c => c.remove());

        const wrap = document.createElement("div");
        wrap.className = "dd-chips";
        wrap.innerHTML = `<span class="dd-chip">${escapeHtml(product.product_name)} <span class="meta" style="background:transparent;padding:0;">#${escapeHtml(product.product_code)}</span></span>`;
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
        const vData = JSON.parse(row.dataset.vdata || "[]");
        const trigger = row.querySelector('.dd[data-kind="variant"] .dd-trigger');
        trigger.querySelectorAll(".dd-chip, .dd-placeholder").forEach(c => c.remove());

        if (!pcode || vData.length === 0) {
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
        vData.forEach(item => {
            const v = (product.variants || []).find(x => Number(x.id) === Number(item.id));
            if (!v) return;
            const chip = document.createElement("span");
            chip.className = "dd-chip";
            chip.innerHTML = `${escapeHtml(v.quantity_name)} <span class="x" data-vid="${v.id}">×</span>`;
            chip.querySelector(".x").addEventListener("click", e => {
                e.stopPropagation();
                let arr = JSON.parse(row.dataset.vdata || "[]");
                arr = arr.filter(x => Number(x.id) !== Number(v.id));
                row.dataset.vdata = JSON.stringify(arr);
                renderVariantChips(row);
                renderVariantStockUI(row);
            });
            wrap.appendChild(chip);
        });
        trigger.insertBefore(wrap, trigger.firstChild);
    }

    function renderVariantStockUI(row) {
        const wrap = row.querySelector(".variant-stock-list");
        const pcode = row.dataset.pcode;
        const vData = JSON.parse(row.dataset.vdata || "[]");
        const product = PRODUCTS.find(p => p.product_code === pcode);

        if (!pcode || vData.length === 0 || !product) {
            wrap.style.display = "none";
            return;
        }

        wrap.style.display = "grid";
        wrap.innerHTML = vData.map(item => {
            const v = product.variants.find(x => Number(x.id) === Number(item.id));
            if (!v) return "";
            const isUnlimited = Number(item.stock_unlimited) === 1;

            return `
                <div class="variant-stock-card" data-vid="${v.id}">
                    <div class="variant-stock-header">
                        <div class="variant-stock-name">${escapeHtml(v.quantity_name)}</div>
                        <div class="variant-stock-price">₹${Number(v.price).toFixed(2)}</div>
                    </div>
                    <div class="variant-stock-controls">
                        <label class="switch-sm">
                            <input type="checkbox" class="js-var-unlimited" ${isUnlimited ? "checked" : ""}> Unlimited
                        </label>
                        <input type="number" class="js-var-count" value="${item.stock_count}" min="0" step="1" ${isUnlimited ? "disabled" : ""} placeholder="Qty">
                    </div>
                </div>
            `;
        }).join("");

        wrap.querySelectorAll(".variant-stock-card").forEach(el => {
            const vid = Number(el.dataset.vid);
            const chk = el.querySelector(".js-var-unlimited");
            const inp = el.querySelector(".js-var-count");

            function updateState() {
                let arr = JSON.parse(row.dataset.vdata || "[]");
                const idx = arr.findIndex(x => Number(x.id) === vid);
                if (idx !== -1) {
                    arr[idx].stock_unlimited = chk.checked ? 1 : 0;
                    arr[idx].stock_count = chk.checked ? 0 : (parseInt(inp.value) || 0);
                    row.dataset.vdata = JSON.stringify(arr);
                }
            }

            chk.addEventListener("change", () => {
                inp.disabled = chk.checked;
                if (chk.checked) inp.value = 0;
                updateState();
            });
            inp.addEventListener("input", updateState);
        });
    }

    function refreshEmpty() {
        prodEmpty.style.display = prodRows.querySelectorAll(".prod-row").length === 0 ? "block" : "none";
    }

    if (addProductBtn) addProductBtn.addEventListener("click", () => addProductRow());
    if (addProductBtnBottom) addProductBtnBottom.addEventListener("click", () => {
        addProductRow();
        const rows = prodRows.querySelectorAll(".prod-row");
        if (rows.length) rows[rows.length - 1].scrollIntoView({ behavior: "smooth", block: "center" });
    });

    prodRows.addEventListener("click", e => {
        const btn = e.target.closest(".prod-row-remove");
        if (!btn) return;
        btn.closest(".prod-row").remove();
        refreshEmpty();
    });

    function collectRows() {
        const rows = prodRows.querySelectorAll(".prod-row");
        const out = [];
        rows.forEach((r, idx) => {
            const pcode = r.dataset.pcode || "";
            const vData = JSON.parse(r.dataset.vdata || "[]");

            const variants = vData.map(v => ({
                variant_id: Number(v.id),
                stock_unlimited: Number(v.stock_unlimited),
                stock_count: Number(v.stock_count)
            }));

            out.push({ index: idx + 1, product_code: pcode, variants: variants });
        });
        return out;
    }

    function validateRows() {
        const rows = collectRows();
        if (rows.length === 0) { showError("Please add at least one product."); return null; }
        for (const r of rows) {
            if (!r.product_code) { showError("Product #" + r.index + ": please pick a product."); return null; }
            if (!r.variants || r.variants.length === 0) { showError("Product #" + r.index + ": pick at least one variant."); return null; }
        }
        return rows;
    }

    form.addEventListener("submit", e => {
        e.preventDefault();
        const name = menuNameInput.value.trim();
        if (!name) return showError("Menu name is required.");

        const s = getStartDateObj(), en = getEndDateObj();
        if (!s || !en) return showError("Please choose valid start/end times.");
        if (en.getTime() <= s.getTime()) return showError("End time must be after start time.");

        const rows = validateRows();
        if (!rows) return;

        function fmt(d) {
            return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0") + " " + String(d.getHours()).padStart(2, "0") + ":" + String(d.getMinutes()).padStart(2, "0") + ":00";
        }

        setLoading(true);
        const formData = new FormData();
        formData.append("menu_name", name);
        formData.append("menu_status", statusInput.checked ? "1" : "0");
        formData.append("start_at", fmt(s));
        formData.append("end_at", fmt(en));
        formData.append("rows", JSON.stringify(rows));

        fetch(BASE_URL + "ajax/add-menu.php", { method: "POST", body: formData, credentials: "same-origin" })
            .then(r => r.json().catch(() => ({ success: false, message: "Unexpected response." })))
            .then(data => {
                if (data.success) {
                    successText.textContent = data.message || "Menu saved successfully.";
                    successOverlay.classList.add("show");
                    successOverlay.setAttribute("aria-hidden", "false");
                    form.reset();
                    prodRows.innerHTML = "";
                    rowCounter = 0;
                    refreshEmpty();
                    addProductRow();
                    if (statusInput) statusInput.checked = true;
                    applyStatusUI();
                    updateDurationPreview();
                    setLoading(false);
                } else {
                    showError(data.message || "Failed to save menu.");
                    setLoading(false);
                }
            })
            .catch(() => { showError("Unable to connect to server."); setLoading(false); });
    });

    if (successOverlay) successOverlay.addEventListener("click", e => { if (e.target === successOverlay) successOverlay.classList.remove("show"); });

    // Initialize
    applyStatusUI();
    updateDurationPreview();
    addProductRow();

    // Render Duplicate List on load
    renderDuplicateList("");
})();