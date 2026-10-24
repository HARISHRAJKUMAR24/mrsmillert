/* =========================================================
   MRS MILL@ — EDIT DISCOUNT UX
   File: ./js/edit-discount.js
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL) ? window.ADMIN_URL : "./";

    const DISCOUNT_ID = Number(window.DISCOUNT_ID || 0);
    const DISCOUNT_TYPE = window.DISCOUNT_TYPE || "time";
    const DISCOUNT_SLOTS = Array.isArray(window.DISCOUNT_SLOTS) ? window.DISCOUNT_SLOTS : [];

    /* ---------------- DOM ---------------- */
    const form = document.getElementById("discountForm");
    const saveBtn = document.getElementById("saveBtn");
    const saveText = document.getElementById("saveBtnText");

    const timeRows = document.getElementById("timeRows");
    const addTimeBtn = document.getElementById("addTimeBtn");

    const customerRows = document.getElementById("customerRows");
    const addTierBtn = document.getElementById("addTierBtn");

    /* coupon */
    const couponCode = document.getElementById("coupon_code");
    const validFromDate = document.getElementById("valid_from_date");
    const validToDate = document.getElementById("valid_to_date");
    const couponStartTime = document.getElementById("coupon_start_time");
    const couponStartAmPm = document.getElementById("coupon_start_ampm");
    const couponEndTime = document.getElementById("coupon_end_time");
    const couponEndAmPm = document.getElementById("coupon_end_ampm");
    const couponAmountType = document.getElementById("coupon_amount_type");
    const couponAmount = document.getElementById("coupon_amount");
    const couponAmountIcon = document.getElementById("couponAmountIcon");
    const usageLimitInput = document.getElementById("usage_limit");  // ✅ NEW

    /* status */
    const statusInput = document.getElementById("discount_status");
    const statusRow = document.getElementById("statusToggleRow");
    const statusTitle = document.getElementById("statusToggleTitle");
    const statusDesc = document.getElementById("statusToggleDesc");

    /* modals */
    const errorOverlay = document.getElementById("errorOverlay");
    const errorText = document.getElementById("errorText");
    const errorTitle = document.getElementById("errorTitle");
    const errorOkBtn = document.getElementById("errorOkBtn");

    const successOverlay = document.getElementById("successOverlay");
    const successText = document.getElementById("successText");
    const stayBtn = document.getElementById("stayBtn");

    if (!form) return;

    let timeCounter = 0;
    let tierCounter = 0;

    /* ---------------- HELPERS ---------------- */
    function setLoading(isLoading) {
        saveBtn.disabled = isLoading;
        saveText.innerHTML = isLoading ? '<span class="btn-spinner"></span> Updating...' : 'Update Discount';
    }

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
    if (errorOkBtn) errorOkBtn.addEventListener("click", closeError);
    errorOverlay.addEventListener("click", e => { if (e.target === errorOverlay) closeError(); });

    /* ---------------- ICONS ---------------- */
    function updateAmountIcon(selectEl, iconEl, inputEl) {
        if (!iconEl) return;
        const t = selectEl ? selectEl.value : "fixed";
        iconEl.className = t === "percent" ? "bi bi-percent" : "bi bi-currency-rupee";
        if (inputEl) {
            inputEl.placeholder = t === "percent" ? "Eg: 10" : "Eg: 50";
            if (t === "percent") inputEl.setAttribute("max", "100");
            else inputEl.removeAttribute("max");
        }
    }

    if (couponAmountType) couponAmountType.addEventListener("change", () => updateAmountIcon(couponAmountType, couponAmountIcon, couponAmount));

    /* ---------------- STATUS ---------------- */
    function applyStatusUI() {
        if (!statusInput) return;
        const active = statusInput.checked;
        if (statusRow) statusRow.classList.toggle("is-active", active);
        if (statusTitle) statusTitle.textContent = active ? "Active" : "Inactive";
        if (statusDesc) statusDesc.textContent = active ? "Discount will be applied." : "Discount will not be applied.";
    }
    if (statusInput) statusInput.addEventListener("change", applyStatusUI);

    /* ---------------- TIME ROWS ---------------- */
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

    function addTimeRow(prefill) {
        timeCounter++;
        prefill = prefill || {};
        const row = document.createElement("div");
        row.className = "time-row";
        row.dataset.rid = String(timeCounter);

        const slotName = prefill.slot_name || ("Set " + timeCounter);
        const start = prefill.start_time || "09:00";
        const startA = prefill.start_ampm || "AM";
        const end = prefill.end_time || "09:00";
        const endA = prefill.end_ampm || "PM";
        const aType = prefill.amount_type || "fixed";
        const amount = prefill.discount_amount !== undefined ? prefill.discount_amount : "";

        row.innerHTML = `
            <div class="time-row-head">
                <div class="time-row-badge"><i class="bi bi-clock"></i> Slot #${timeCounter}</div>
                <button type="button" class="time-row-remove" title="Remove slot"><i class="bi bi-trash3"></i></button>
            </div>
            <div class="slot-name-grid">
                <label class="field-label">Slot Name <span class="required">*</span></label>
                <input type="text" class="time-input js-slot-name" value="${slotName}" maxlength="50" placeholder="Eg: Set 1">
            </div>
            <div class="time-row-grid">
                <div><label class="field-label">Start Time</label><input type="time" class="time-input js-start-time" value="${start}"></div>
                <div><label class="field-label">AM/PM</label><select class="time-input js-start-ampm"><option value="AM" ${startA === "AM" ? "selected" : ""}>AM</option><option value="PM" ${startA === "PM" ? "selected" : ""}>PM</option></select></div>
                <div></div>
                <div><label class="field-label">End Time</label><input type="time" class="time-input js-end-time" value="${end}"></div>
                <div><label class="field-label">AM/PM</label><select class="time-input js-end-ampm"><option value="AM" ${endA === "AM" ? "selected" : ""}>AM</option><option value="PM" ${endA === "PM" ? "selected" : ""}>PM</option></select></div>
                <div></div>
            </div>
            <div class="slot-extra-grid">
                <div>
                    <label class="field-label">Amount Type</label>
                    <select class="time-input js-slot-amount-type"><option value="fixed" ${aType === "fixed" ? "selected" : ""}>Fixed (₹)</option><option value="percent" ${aType === "percent" ? "selected" : ""}>Percentage (%)</option></select>
                </div>
                <div>
                    <label class="field-label">Amount</label>
                    <div class="input-icon-wrap">
                        <i class="bi ${aType === "percent" ? "bi-percent" : "bi-currency-rupee"} js-slot-amount-icon"></i>
                        <input type="number" class="disc-input js-slot-amount" value="${amount}" placeholder="${aType === "percent" ? "Eg: 10" : "Eg: 50"}" min="0" step="0.01" ${aType === "percent" ? 'max="100"' : ''}>
                    </div>
                </div>
            </div>
        `;

        timeRows.appendChild(row);

        const typeSel = row.querySelector(".js-slot-amount-type");
        const amountInput = row.querySelector(".js-slot-amount");
        const amountIcon = row.querySelector(".js-slot-amount-icon");
        typeSel.addEventListener("change", () => {
            const t = typeSel.value;
            amountIcon.className = t === "percent" ? "bi bi-percent js-slot-amount-icon" : "bi bi-currency-rupee js-slot-amount-icon";
            amountInput.placeholder = t === "percent" ? "Eg: 10" : "Eg: 50";
            if (t === "percent") amountInput.setAttribute("max", "100");
            else amountInput.removeAttribute("max");
        });

        renumberTimeRows();
    }

    function renumberTimeRows() {
        timeRows.querySelectorAll(".time-row").forEach((r, idx) => {
            const badge = r.querySelector(".time-row-badge");
            if (badge) badge.innerHTML = `<i class="bi bi-clock"></i> Slot #${idx + 1}`;
        });
    }

    if (addTimeBtn) addTimeBtn.addEventListener("click", () => addTimeRow());

    if (timeRows) {
        timeRows.addEventListener("click", e => {
            const btn = e.target.closest(".time-row-remove");
            if (!btn) return;
            const row = btn.closest(".time-row");
            if (!row) return;
            row.style.transition = "opacity .18s ease, transform .18s ease";
            row.style.opacity = "0";
            row.style.transform = "translateY(-6px)";
            setTimeout(() => { row.remove(); renumberTimeRows(); }, 180);
        });
    }

    /* ---------------- CUSTOMER TIERS ---------------- */
    function addTierRow(prefill) {
        tierCounter++;
        prefill = prefill || {};
        const row = document.createElement("div");
        row.className = "customer-row";
        row.dataset.rid = String(tierCounter);

        const tierName = prefill.slot_name || "Customer Tier " + tierCounter;
        const min = prefill.min_orders !== undefined ? prefill.min_orders : (tierCounter === 1 ? '0' : '');
        const max = prefill.max_orders !== undefined ? prefill.max_orders : (tierCounter === 1 ? '3' : '');
        const amountType = prefill.amount_type || "fixed";
        const amount = prefill.discount_amount !== undefined ? prefill.discount_amount : "";

        row.innerHTML = `
            <div class="customer-row-head">
                <div class="customer-row-badge"><i class="bi bi-person-badge"></i> Tier #${tierCounter}</div>
                <button type="button" class="customer-row-remove" title="Remove tier"><i class="bi bi-trash3"></i></button>
            </div>
            <div class="slot-name-grid">
                <label class="field-label">Tier Name <span class="required">*</span></label>
                <input type="text" class="time-input js-tier-name" value="${tierName}" maxlength="50" placeholder="Eg: New Customer">
            </div>
            <div class="customer-tier-grid">
                <div>
                    <label class="field-label">Min Orders <span class="required">*</span></label>
                    <input type="number" class="time-input js-tier-min" value="${min}" min="0" step="1" placeholder="0">
                </div>
                <div>
                    <label class="field-label">Max Orders <span class="required">*</span></label>
                    <input type="number" class="time-input js-tier-max" value="${max}" min="1" step="1" placeholder="9999">
                </div>
                <div>
                    <label class="field-label">Amount Type</label>
                    <select class="time-input js-tier-amount-type">
                        <option value="fixed" ${amountType === "fixed" ? "selected" : ""}>Fixed (₹)</option>
                        <option value="percent" ${amountType === "percent" ? "selected" : ""}>Percentage (%)</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Amount <span class="required">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="bi ${amountType === "percent" ? "bi-percent" : "bi-currency-rupee"} js-tier-amount-icon"></i>
                        <input type="number" class="disc-input js-tier-amount" value="${amount}" placeholder="Eg: 50" min="0" step="0.01" ${amountType === "percent" ? 'max="100"' : ''}>
                    </div>
                </div>
            </div>
        `;

        customerRows.appendChild(row);

        const typeSel = row.querySelector(".js-tier-amount-type");
        const amountInput = row.querySelector(".js-tier-amount");
        const amountIcon = row.querySelector(".js-tier-amount-icon");
        typeSel.addEventListener("change", () => {
            const t = typeSel.value;
            amountIcon.className = t === "percent" ? "bi bi-percent js-tier-amount-icon" : "bi bi-currency-rupee js-tier-amount-icon";
            amountInput.placeholder = t === "percent" ? "Eg: 10" : "Eg: 50";
            if (t === "percent") amountInput.setAttribute("max", "100");
            else amountInput.removeAttribute("max");
        });

        renumberTierRows();
    }

    function renumberTierRows() {
        customerRows.querySelectorAll(".customer-row").forEach((r, idx) => {
            const badge = r.querySelector(".customer-row-badge");
            if (badge) badge.innerHTML = `<i class="bi bi-person-badge"></i> Tier #${idx + 1}`;
        });
    }

    if (addTierBtn) addTierBtn.addEventListener("click", () => addTierRow());

    if (customerRows) {
        customerRows.addEventListener("click", e => {
            const btn = e.target.closest(".customer-row-remove");
            if (!btn) return;
            const row = btn.closest(".customer-row");
            if (!row) return;
            row.style.transition = "opacity .18s ease, transform .18s ease";
            row.style.opacity = "0";
            row.style.transform = "translateY(-6px)";
            setTimeout(() => { row.remove(); renumberTierRows(); }, 180);
        });
    }

    /* ---------------- COLLECT ---------------- */
    function collectTimeRows() {
        const rows = timeRows.querySelectorAll(".time-row");
        const out = [];
        rows.forEach(r => {
            const slotName = (r.querySelector(".js-slot-name")?.value || "").trim();
            const st = r.querySelector(".js-start-time")?.value || "09:00";
            const sa = r.querySelector(".js-start-ampm")?.value || "AM";
            const et = r.querySelector(".js-end-time")?.value || "09:00";
            const ea = r.querySelector(".js-end-ampm")?.value || "PM";
            const aType = r.querySelector(".js-slot-amount-type")?.value || "fixed";
            const amt = (r.querySelector(".js-slot-amount")?.value || "").trim();

            out.push({
                slot_name: slotName,
                start_time: to24h(st, sa),
                end_time: to24h(et, ea),
                amount_type: aType,
                discount_amount: amt,
                delivery_enabled: 1,
                min_orders: 0, max_orders: 9999
            });
        });
        return out;
    }

    function collectTierRows() {
        const rows = customerRows.querySelectorAll(".customer-row");
        const out = [];
        rows.forEach(r => {
            const tierName = (r.querySelector(".js-tier-name")?.value || "").trim();
            const min = r.querySelector(".js-tier-min")?.value || "0";
            const max = r.querySelector(".js-tier-max")?.value || "9999";
            const amountType = r.querySelector(".js-tier-amount-type")?.value || "fixed";
            const amount = (r.querySelector(".js-tier-amount")?.value || "").trim();

            out.push({
                slot_name: tierName || "Customer Tier",
                start_time: "00:00",
                end_time: "23:59",
                amount_type: amountType,
                discount_amount: amount,
                delivery_enabled: 1,
                min_orders: parseInt(min, 10),
                max_orders: parseInt(max, 10)
            });
        });
        return out;
    }

    /* ---------------- VALIDATION ---------------- */
    function validateForm() {
        const name = document.getElementById("discount_name").value.trim();
        if (!name) { showError("Discount name is required.", "Missing name"); return null; }

        if (DISCOUNT_TYPE === "time") {
            const slots = collectTimeRows();
            if (slots.length === 0) { showError("Please add at least one time slot.", "No slots"); return null; }
            for (let i = 0; i < slots.length; i++) {
                const s = slots[i];
                const label = `Slot #${i + 1}`;
                if (!s.slot_name) { showError(`${label}: please enter a slot name.`, "Missing slot name"); return null; }
                if (s.start_time === s.end_time) { showError(`${label}: start and end time cannot be same.`, "Invalid slot"); return null; }
                if (s.discount_amount === "" || isNaN(Number(s.discount_amount)) || Number(s.discount_amount) < 0) { showError(`${label}: invalid amount.`, "Invalid amount"); return null; }
                if (s.amount_type === "percent" && Number(s.discount_amount) > 100) { showError(`${label}: percentage cannot exceed 100.`, "Invalid percent"); return null; }
            }
            return {
                discount_name: name, discount_type: "time", coupon_code: "", valid_from_date: "", valid_to_date: "",
                usage_limit: 0,
                slots
            };
        }

        if (DISCOUNT_TYPE === "customer_type") {
            const tiers = collectTierRows();
            if (tiers.length === 0) { showError("Please add at least one customer tier.", "No tiers"); return null; }
            for (let i = 0; i < tiers.length; i++) {
                const t = tiers[i];
                const label = `Tier #${i + 1}`;
                if (!t.slot_name || t.slot_name === "Customer Tier") {
                    showError(`${label}: please enter a tier name.`, "Missing tier name");
                    return null;
                }
                if (isNaN(t.min_orders) || isNaN(t.max_orders) || t.min_orders < 0 || t.max_orders < t.min_orders) { showError(`${label}: Max must be >= Min.`, "Invalid range"); return null; }
                if (t.discount_amount === "" || isNaN(Number(t.discount_amount)) || Number(t.discount_amount) < 0) { showError(`${label}: invalid amount.`, "Invalid amount"); return null; }
                if (t.amount_type === "percent" && Number(t.discount_amount) > 100) { showError(`${label}: percentage cannot exceed 100.`, "Invalid percent"); return null; }
            }
            return {
                discount_name: name, discount_type: "customer_type", coupon_code: "", valid_from_date: "", valid_to_date: "",
                usage_limit: 0,
                slots: tiers
            };
        }

        /* Coupon */
        const code = (couponCode.value || "").trim().toUpperCase();
        if (!code) { showError("Coupon code is required.", "Missing code"); return null; }
        if (!/^[A-Z0-9_-]{3,50}$/.test(code)) { showError("Invalid coupon code.", "Invalid code"); return null; }

        const from = validFromDate.value;
        const to = validToDate.value;
        if (!from || !to) { showError("Dates are required.", "Missing dates"); return null; }
        if (new Date(to) < new Date(from)) { showError("Valid To must be after Valid From.", "Invalid dates"); return null; }

        const cStart = to24h(couponStartTime.value, couponStartAmPm.value);
        const cEnd = to24h(couponEndTime.value, couponEndAmPm.value);
        if (cStart === cEnd) { showError("Coupon start and end time cannot be same.", "Invalid time"); return null; }

        const amount = (couponAmount.value || "").trim();
        if (amount === "" || isNaN(Number(amount)) || Number(amount) < 0) { showError("Invalid coupon amount.", "Invalid amount"); return null; }
        const cType = couponAmountType ? couponAmountType.value : "fixed";
        if (cType === "percent" && Number(amount) > 100) { showError("Coupon percentage cannot exceed 100.", "Invalid percent"); return null; }

        const usageLimit = parseInt(usageLimitInput?.value || "0", 10) || 0;

        return {
            discount_name: name, discount_type: "coupon", coupon_code: code, valid_from_date: from, valid_to_date: to,
            usage_limit: usageLimit,
            slots: [{
                slot_name: "Coupon", start_time: cStart, end_time: cEnd,
                amount_type: cType, discount_amount: Number(amount).toFixed(2),
                delivery_enabled: 1, min_orders: 0, max_orders: 9999
            }]
        };
    }

    /* ---------------- SUBMIT ---------------- */
    form.addEventListener("submit", e => {
        e.preventDefault();

        const payload = validateForm();
        if (!payload) return;

        setLoading(true);

        const formData = new FormData();
        formData.append("id", DISCOUNT_ID);
        formData.append("discount_name", payload.discount_name);
        formData.append("discount_type", payload.discount_type);
        formData.append("coupon_code", payload.coupon_code);
        formData.append("valid_from_date", payload.valid_from_date);
        formData.append("valid_to_date", payload.valid_to_date);
        formData.append("discount_status", statusInput && statusInput.checked ? "1" : "0");
        formData.append("usage_limit", payload.usage_limit || 0);
        formData.append("slots", JSON.stringify(payload.slots));

        fetch(BASE_URL + "ajax/update-discount.php", {
            method: "POST", body: formData, credentials: "same-origin"
        })
            .then(r => r.json().catch(() => ({ success: false, message: "Unexpected server response." })))
            .then(data => {
                if (data.success) {
                    if (successText) successText.textContent = data.message || "Discount updated.";
                    successOverlay.classList.add("show");
                    successOverlay.setAttribute("aria-hidden", "false");
                    setLoading(false);
                } else {
                    showError(data.message || "Failed to update.", "Update failed");
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
    if (stayBtn) {
        stayBtn.addEventListener("click", () => {
            successOverlay.classList.remove("show");
            successOverlay.setAttribute("aria-hidden", "true");
        });
    }

    /* ---------------- INIT ---------------- */
    updateAmountIcon(couponAmountType, couponAmountIcon, couponAmount);
    applyStatusUI();

    if (DISCOUNT_TYPE === "time") {
        if (DISCOUNT_SLOTS.length > 0) DISCOUNT_SLOTS.forEach(s => addTimeRow(s));
        else addTimeRow();
    } else if (DISCOUNT_TYPE === "customer_type") {
        if (DISCOUNT_SLOTS.length > 0) DISCOUNT_SLOTS.forEach(s => addTierRow(s));
        else addTierRow();
    }

})();