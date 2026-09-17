/* =========================================================
   MRS MILL@ — ADD DISCOUNT UX
   File: ./js/add-discount.js
   - Type: time / coupon
   - Per-slot: start/end time + amount type + amount + delivery
   - Amount type: fixed (₹) OR percent (%)
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    /* ---------------- DOM ---------------- */

    const form     = document.getElementById("discountForm");
    const saveBtn  = document.getElementById("saveBtn");
    const saveText = document.getElementById("saveBtnText");

    const typeTime   = document.getElementById("disc_type_time");
    const typeCoupon = document.getElementById("disc_type_coupon");

    const timeSection   = document.getElementById("timeSection");
    const couponSection = document.getElementById("couponSection");

    const timeRows   = document.getElementById("timeRows");
    const addTimeBtn = document.getElementById("addTimeBtn");

    /* coupon */
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

    /* status */
    const statusInput = document.getElementById("discount_status");
    const statusRow   = document.getElementById("statusToggleRow");
    const statusTitle = document.getElementById("statusToggleTitle");
    const statusDesc  = document.getElementById("statusToggleDesc");

    /* modals */
    const errorOverlay = document.getElementById("errorOverlay");
    const errorText    = document.getElementById("errorText");
    const errorTitle   = document.getElementById("errorTitle");
    const errorOkBtn   = document.getElementById("errorOkBtn");

    const successOverlay = document.getElementById("successOverlay");
    const successText    = document.getElementById("successText");

    if (!form) return;

    let timeCounter = 0;

    /* ---------------- HELPERS ---------------- */

    function setLoading(isLoading) {
        saveBtn.disabled = isLoading;
        saveText.innerHTML = isLoading
            ? '<span class="btn-spinner"></span> Saving...'
            : 'Save Discount';
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

    /* ---------------- TYPE SWITCH ---------------- */

    function applyTypeUI() {
        const isCoupon = typeCoupon && typeCoupon.checked;
        if (timeSection)   timeSection.style.display = isCoupon ? "none" : "";
        if (couponSection) couponSection.classList.toggle("show", isCoupon);
    }
    if (typeTime)   typeTime.addEventListener("change", applyTypeUI);
    if (typeCoupon) typeCoupon.addEventListener("change", applyTypeUI);

    /* ---------------- COUPON AMOUNT TYPE ICON ---------------- */

    function updateCouponAmountIcon() {
        if (!couponAmountIcon) return;
        const t = couponAmountType ? couponAmountType.value : "fixed";
        couponAmountIcon.className = t === "percent" ? "bi bi-percent" : "bi bi-currency-rupee";
        if (couponAmount) {
            couponAmount.placeholder = t === "percent" ? "Eg: 10" : "Eg: 50";
            if (t === "percent") {
                couponAmount.setAttribute("max", "100");
            } else {
                couponAmount.removeAttribute("max");
            }
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

    function addTimeRow() {
        timeCounter++;

        const row = document.createElement("div");
        row.className = "time-row";
        row.dataset.rid = String(timeCounter);

        row.innerHTML = `
            <div class="time-row-head">
                <div class="time-row-badge">
                    <i class="bi bi-clock"></i> Slot #${timeCounter}
                </div>
                <button type="button" class="time-row-remove" title="Remove slot">
                    <i class="bi bi-trash3"></i>
                </button>
            </div>

            <div class="time-row-grid">
                <div>
                    <label class="field-label">Start Time</label>
                    <input type="time" class="time-input js-start-time" value="09:00">
                </div>
                <div>
                    <label class="field-label">AM/PM</label>
                    <select class="time-input js-start-ampm">
                        <option value="AM" selected>AM</option>
                        <option value="PM">PM</option>
                    </select>
                </div>
                <div></div>
                <div>
                    <label class="field-label">End Time</label>
                    <input type="time" class="time-input js-end-time" value="09:00">
                </div>
                <div>
                    <label class="field-label">AM/PM</label>
                    <select class="time-input js-end-ampm">
                        <option value="AM">AM</option>
                        <option value="PM" selected>PM</option>
                    </select>
                </div>
                <div></div>
            </div>

            <div class="slot-extra-grid">

                <div>
                    <label class="field-label">Amount Type</label>
                    <select class="time-input js-slot-amount-type">
                        <option value="fixed" selected>Fixed (₹)</option>
                        <option value="percent">Percentage (%)</option>
                    </select>
                </div>

                <div>
                    <label class="field-label">Amount</label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-currency-rupee js-slot-amount-icon"></i>
                        <input type="number"
                               class="disc-input js-slot-amount"
                               placeholder="Eg: 50"
                               min="0"
                               step="0.01">
                    </div>
                </div>

                <div>
                    <label class="field-label">Delivery</label>
                    <div class="delivery-inline is-on js-delivery-wrap">
                        <span class="lbl">
                            <i class="bi bi-truck"></i>
                            <span class="js-delivery-text">Delivery Enabled</span>
                        </span>
                        <label class="mm-switch">
                            <input type="checkbox" class="js-slot-delivery" checked>
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

        /* per-row amount type icon + max */
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

    if (addTimeBtn) addTimeBtn.addEventListener("click", addTimeRow);

    if (timeRows) {
        timeRows.addEventListener("click", e => {
            const btn = e.target.closest(".time-row-remove");
            if (!btn) return;
            const row = btn.closest(".time-row");
            if (!row) return;

            row.style.transition = "opacity .18s ease, transform .18s ease";
            row.style.opacity = "0";
            row.style.transform = "translateY(-6px)";

            setTimeout(() => {
                row.remove();
                renumberTimeRows();
            }, 180);
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
            const amountType = r.querySelector(".js-slot-amount-type")?.value || "fixed";
            const amount = (r.querySelector(".js-slot-amount")?.value || "").trim();
            const delivery = r.querySelector(".js-slot-delivery")?.checked ? 1 : 0;

            out.push({
                start_time: to24h(st, sa),
                end_time:   to24h(et, ea),
                amount_type: amountType,
                discount_amount: amount,
                delivery_enabled: delivery
            });
        });

        return out;
    }

    /* ---------------- VALIDATION ---------------- */

    function validateForm() {
        const name = document.getElementById("discount_name").value.trim();
        if (!name) { showError("Discount name is required.", "Missing name"); return null; }

        const type = (typeCoupon && typeCoupon.checked) ? "coupon" : "time";

        if (type === "time") {
            const slots = collectTimeRows();
            if (slots.length === 0) {
                showError("Please add at least one time slot.", "No time slots");
                return null;
            }
            for (let i = 0; i < slots.length; i++) {
                const s = slots[i];
                const label = `Slot #${i + 1}`;
                if (s.start_time === s.end_time) {
                    showError(`${label}: start and end time cannot be same.`, "Invalid slot");
                    return null;
                }
                if (s.discount_amount === "" || isNaN(Number(s.discount_amount)) || Number(s.discount_amount) < 0) {
                    showError(`${label}: please enter a valid amount.`, "Invalid amount");
                    return null;
                }
                if (s.amount_type === "percent" && Number(s.discount_amount) > 100) {
                    showError(`${label}: percentage cannot exceed 100.`, "Invalid percentage");
                    return null;
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
        if (!code) { showError("Coupon code is required.", "Missing coupon code"); return null; }
        if (!/^[A-Z0-9_-]{3,50}$/.test(code)) {
            showError("Coupon code must be 3-50 letters/numbers/underscore/dash.", "Invalid coupon code");
            return null;
        }

        const from = validFromDate.value;
        const to   = validToDate.value;
        if (!from || !to) { showError("Valid from/to date are required.", "Missing dates"); return null; }
        if (new Date(to) < new Date(from)) {
            showError("Valid To must be after Valid From.", "Invalid date range");
            return null;
        }

        const cStart = to24h(couponStartTime.value, couponStartAmPm.value);
        const cEnd   = to24h(couponEndTime.value,   couponEndAmPm.value);
        if (cStart === cEnd) {
            showError("Coupon start and end time cannot be same.", "Invalid time");
            return null;
        }

        const amount = (couponAmount.value || "").trim();
        if (amount === "" || isNaN(Number(amount)) || Number(amount) < 0) {
            showError("Please enter a valid coupon amount.", "Invalid amount");
            return null;
        }

        const couponType = couponAmountType ? couponAmountType.value : "fixed";
        if (couponType === "percent" && Number(amount) > 100) {
            showError("Coupon percentage cannot exceed 100.", "Invalid percentage");
            return null;
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
                amount_type: couponType,
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
        formData.append("discount_name", payload.discount_name);
        formData.append("discount_type", payload.discount_type);
        formData.append("coupon_code", payload.coupon_code);
        formData.append("valid_from_date", payload.valid_from_date);
        formData.append("valid_to_date", payload.valid_to_date);
        formData.append("discount_status", statusInput && statusInput.checked ? "1" : "0");
        formData.append("slots", JSON.stringify(payload.slots));

        fetch(BASE_URL + "ajax/add-discount.php", {
            method: "POST",
            body: formData,
            credentials: "same-origin"
        })
        .then(r => r.json().catch(() => ({ success: false, message: "Unexpected server response." })))
        .then(data => {
            if (data.success) {
                if (successText) successText.textContent = data.message || "Discount saved.";
                successOverlay.classList.add("show");
                successOverlay.setAttribute("aria-hidden", "false");

                setLoading(false);
                form.reset();
                timeRows.innerHTML = "";
                timeCounter = 0;

                typeTime.checked = true;
                statusInput.checked = true;
                applyTypeUI();
                applyStatusUI();
                applyCouponDeliveryUI();
                updateCouponAmountIcon();
                addTimeRow();
            } else {
                showError(data.message || "Failed to save discount.", "Save failed");
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

    applyTypeUI();
    applyStatusUI();
    applyCouponDeliveryUI();
    updateCouponAmountIcon();
    addTimeRow();

})();