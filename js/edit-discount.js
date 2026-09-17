/* =========================================================
   MRS MILL@ — EDIT DISCOUNT UX
   File: ./js/edit-discount.js
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    const DISCOUNT_ID    = Number(window.DISCOUNT_ID || 0);
    const DISCOUNT_TYPE  = window.DISCOUNT_TYPE || "time";
    const DISCOUNT_SLOTS = Array.isArray(window.DISCOUNT_SLOTS) ? window.DISCOUNT_SLOTS : [];

    /* ---------------- DOM ---------------- */

    const form     = document.getElementById("discountForm");
    const saveBtn  = document.getElementById("saveBtn");
    const saveText = document.getElementById("saveBtnText");

    const timeRows   = document.getElementById("timeRows");
    const addTimeBtn = document.getElementById("addTimeBtn");

    const couponCode    = document.getElementById("coupon_code");
    const validFromDate = document.getElementById("valid_from_date");
    const validToDate   = document.getElementById("valid_to_date");
    const couponStartTime = document.getElementById("coupon_start_time");
    const couponStartAmPm = document.getElementById("coupon_start_ampm");
    const couponEndTime   = document.getElementById("coupon_end_time");
    const couponEndAmPm   = document.getElementById("coupon_end_ampm");
    const couponAmountType = document.getElementById("coupon_amount_type");
    const couponAmount     = document.getElementById("coupon_amount");
    const couponAmountIcon = document.getElementById("couponAmountIcon");
    const couponDelivery   = document.getElementById("coupon_delivery_enabled");
    const couponDelivWrap  = document.getElementById("couponDeliveryWrap");
    const couponDelivText  = document.getElementById("couponDeliveryText");

    const statusInput = document.getElementById("discount_status");
    const statusRow   = document.getElementById("statusToggleRow");
    const statusTitle = document.getElementById("statusToggleTitle");
    const statusDesc  = document.getElementById("statusToggleDesc");

    const errorOverlay = document.getElementById("errorOverlay");
    const errorText    = document.getElementById("errorText");
    const errorTitle   = document.getElementById("errorTitle");
    const errorOkBtn   = document.getElementById("errorOkBtn");

    const successOverlay = document.getElementById("successOverlay");
    const successText    = document.getElementById("successText");
    const stayBtn        = document.getElementById("stayBtn");

    if (!form) return;

    let rowCounter = 0;

    /* ---------------- HELPERS ---------------- */

    function setLoading(isLoading) {
        saveBtn.disabled = isLoading;
        saveText.innerHTML = isLoading
            ? '<span class="btn-spinner"></span> Updating...'
            : 'Update Discount';
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

    /* ---------------- COUPON TYPE ICON ---------------- */

    function updateCouponAmountIcon() {
        if (!couponAmountIcon) return;
        const t = couponAmountType ? couponAmountType.value : "fixed";
        couponAmountIcon.className = t === "percent" ? "bi bi-percent" : "bi bi-currency-rupee";
        if (couponAmount) {
            couponAmount.placeholder = t === "percent" ? "Eg: 10" : "Eg: 50";
            if (t === "percent") couponAmount.setAttribute("max", "100");
            else couponAmount.removeAttribute("max");
        }
    }
    if (couponAmountType) couponAmountType.addEventListener("change", updateCouponAmountIcon);

    /* ---------------- COUPON DELIVERY ---------------- */

    function applyCouponDeliveryUI() {
        if (!couponDelivery || !couponDelivWrap) return;
        const on = couponDelivery.checked;
        couponDelivWrap.classList.toggle("is-on", on);
        if (couponDelivText) couponDelivText.textContent = on ? "Delivery Enabled" : "Delivery Disabled";
    }
    if (couponDelivery) couponDelivery.addEventListener("change", applyCouponDeliveryUI);

    /* ---------------- STATUS ---------------- */

    function applyStatusUI() {
        if (!statusInput) return;
        const active = statusInput.checked;
        if (statusRow)   statusRow.classList.toggle("is-active", active);
        if (statusTitle) statusTitle.textContent = active ? "Active" : "Inactive";
        if (statusDesc)  statusDesc.textContent  = active
            ? "Discount will be applied."
            : "Discount will not be applied.";
    }
    if (statusInput) statusInput.addEventListener("change", applyStatusUI);

    /* ---------------- HELPERS ---------------- */

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

    /* ---------------- TIME ROW ---------------- */

    function addTimeRow(prefill) {
        rowCounter++;
        prefill = prefill || {};

        const row = document.createElement("div");
        row.className = "time-row";
        row.dataset.rid = String(rowCounter);

        const start = prefill.start_time || "09:00";
        const startA = prefill.start_ampm || "AM";
        const end    = prefill.end_time || "09:00";
        const endA   = prefill.end_ampm || "PM";
        const aType  = prefill.amount_type || "fixed";
        const amount = prefill.discount_amount !== undefined ? prefill.discount_amount : "";
        const del    = prefill.delivery_enabled === 0 ? 0 : 1;

        row.innerHTML = `
            <div class="time-row-head">
                <div class="time-row-badge">
                    <i class="bi bi-clock"></i> Slot #${rowCounter}
                </div>
                <button type="button" class="time-row-remove" title="Remove slot">
                    <i class="bi bi-trash3"></i>
                </button>
            </div>

            <div class="time-row-grid">
                <div>
                    <label class="field-label">Start Time</label>
                    <input type="time" class="time-input js-start-time" value="${start}">
                </div>
                <div>
                    <label class="field-label">AM/PM</label>
                    <select class="time-input js-start-ampm">
                        <option value="AM" ${startA === "AM" ? "selected" : ""}>AM</option>
                        <option value="PM" ${startA === "PM" ? "selected" : ""}>PM</option>
                    </select>
                </div>
                <div></div>
                <div>
                    <label class="field-label">End Time</label>
                    <input type="time" class="time-input js-end-time" value="${end}">
                </div>
                <div>
                    <label class="field-label">AM/PM</label>
                    <select class="time-input js-end-ampm">
                        <option value="AM" ${endA === "AM" ? "selected" : ""}>AM</option>
                        <option value="PM" ${endA === "PM" ? "selected" : ""}>PM</option>
                    </select>
                </div>
                <div></div>
            </div>

            <div class="slot-extra-grid">

                <div>
                    <label class="field-label">Amount Type</label>
                    <select class="time-input js-slot-amount-type">
                        <option value="fixed" ${aType === "fixed" ? "selected" : ""}>Fixed (₹)</option>
                        <option value="percent" ${aType === "percent" ? "selected" : ""}>Percentage (%)</option>
                    </select>
                </div>

                <div>
                    <label class="field-label">Amount</label>
                    <div class="input-icon-wrap">
                        <i class="bi ${aType === "percent" ? "bi-percent" : "bi-currency-rupee"} js-slot-amount-icon"></i>
                        <input type="number"
                               class="disc-input js-slot-amount"
                               value="${amount}"
                               placeholder="${aType === "percent" ? "Eg: 10" : "Eg: 50"}"
                               min="0"
                               step="0.01"
                               ${aType === "percent" ? 'max="100"' : ''}>
                    </div>
                </div>

                <div>
                    <label class="field-label">Delivery</label>
                    <div class="delivery-inline ${del ? 'is-on' : ''} js-delivery-wrap">
                        <span class="lbl">
                            <i class="bi bi-truck"></i>
                            <span class="js-delivery-text">${del ? 'Delivery Enabled' : 'Delivery Disabled'}</span>
                        </span>
                        <label class="mm-switch">
                            <input type="checkbox" class="js-slot-delivery" ${del ? 'checked' : ''}>
                            <span class="mm-switch-slider"></span>
                        </label>
                    </div>
                </div>

            </div>
        `;

        timeRows.appendChild(row);

        /* per-row delivery toggle */
        const cb   = row.querySelector(".js-slot-delivery");
        const wrap = row.querySelector(".js-delivery-wrap");
        const txt  = row.querySelector(".js-delivery-text");
        cb.addEventListener("change", () => {
            const on = cb.checked;
            wrap.classList.toggle("is-on", on);
            txt.textContent = on ? "Delivery Enabled" : "Delivery Disabled";
        });

        /* per-row amount type */
        const typeSel = row.querySelector(".js-slot-amount-type");
        const amountInput = row.querySelector(".js-slot-amount");
        const amountIcon  = row.querySelector(".js-slot-amount-icon");

        typeSel.addEventListener("change", () => {
            const t = typeSel.value;
            amountIcon.className = t === "percent"
                ? "bi bi-percent js-slot-amount-icon"
                : "bi bi-currency-rupee js-slot-amount-icon";
            amountInput.placeholder = t === "percent" ? "Eg: 10" : "Eg: 50";
            if (t === "percent") amountInput.setAttribute("max", "100");
            else amountInput.removeAttribute("max");
        });

        renumberTimeRows();
    }

    function renumberTimeRows() {
        const rows = timeRows.querySelectorAll(".time-row");
        rows.forEach((r, idx) => {
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

    /* ---------------- COLLECT ---------------- */

    function collectTimeRows() {
        const rows = timeRows.querySelectorAll(".time-row");
        const out = [];
        rows.forEach(r => {
            const st = r.querySelector(".js-start-time")?.value || "09:00";
            const sa = r.querySelector(".js-start-ampm")?.value || "AM";
            const et = r.querySelector(".js-end-time")?.value || "09:00";
            const ea = r.querySelector(".js-end-ampm")?.value || "PM";
            const aType = r.querySelector(".js-slot-amount-type")?.value || "fixed";
            const amt = (r.querySelector(".js-slot-amount")?.value || "").trim();
            const del = r.querySelector(".js-slot-delivery")?.checked ? 1 : 0;

            out.push({
                start_time: to24h(st, sa),
                end_time:   to24h(et, ea),
                amount_type: aType,
                discount_amount: amt,
                delivery_enabled: del
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
                if (s.start_time === s.end_time) { showError(`${label}: start and end time cannot be same.`, "Invalid slot"); return null; }
                if (s.discount_amount === "" || isNaN(Number(s.discount_amount)) || Number(s.discount_amount) < 0) {
                    showError(`${label}: invalid amount.`, "Invalid amount"); return null;
                }
                if (s.amount_type === "percent" && Number(s.discount_amount) > 100) {
                    showError(`${label}: percentage cannot exceed 100.`, "Invalid percent"); return null;
                }
            }
            return {
                discount_name: name,
                discount_type: "time",
                coupon_code: "",
                valid_from_date: "",
                valid_to_date: "",
                slots: slots.map(s => ({
                    start_time: s.start_time,
                    end_time: s.end_time,
                    amount_type: s.amount_type,
                    discount_amount: Number(s.discount_amount).toFixed(2),
                    delivery_enabled: s.delivery_enabled
                }))
            };
        }

        /* Coupon */
        const code = (couponCode.value || "").trim().toUpperCase();
        if (!code) { showError("Coupon code is required.", "Missing code"); return null; }
        if (!/^[A-Z0-9_-]{3,50}$/.test(code)) { showError("Invalid coupon code.", "Invalid code"); return null; }

        const from = validFromDate.value;
        const to   = validToDate.value;
        if (!from || !to) { showError("Dates are required.", "Missing dates"); return null; }
        if (new Date(to) < new Date(from)) { showError("Valid To must be after Valid From.", "Invalid dates"); return null; }

        const cStart = to24h(couponStartTime.value, couponStartAmPm.value);
        const cEnd   = to24h(couponEndTime.value,   couponEndAmPm.value);
        if (cStart === cEnd) { showError("Coupon start and end time cannot be same.", "Invalid time"); return null; }

        const amount = (couponAmount.value || "").trim();
        if (amount === "" || isNaN(Number(amount)) || Number(amount) < 0) {
            showError("Invalid coupon amount.", "Invalid amount"); return null;
        }
        const cType = couponAmountType ? couponAmountType.value : "fixed";
        if (cType === "percent" && Number(amount) > 100) {
            showError("Coupon percentage cannot exceed 100.", "Invalid percent"); return null;
        }

        return {
            discount_name: name,
            discount_type: "coupon",
            coupon_code: code,
            valid_from_date: from,
            valid_to_date: to,
            slots: [{
                start_time: cStart,
                end_time: cEnd,
                amount_type: cType,
                discount_amount: Number(amount).toFixed(2),
                delivery_enabled: couponDelivery.checked ? 1 : 0
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
        formData.append("slots", JSON.stringify(payload.slots));

        fetch(BASE_URL + "ajax/update-discount.php", {
            method: "POST",
            body: formData,
            credentials: "same-origin"
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

    updateCouponAmountIcon();
    applyCouponDeliveryUI();
    applyStatusUI();

    if (DISCOUNT_TYPE === "time") {
        if (DISCOUNT_SLOTS.length > 0) DISCOUNT_SLOTS.forEach(s => addTimeRow(s));
        else addTimeRow();
    }

})();