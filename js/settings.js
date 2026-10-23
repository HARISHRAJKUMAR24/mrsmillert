/* =========================================================
   MRS MILL@ — UNIFIED SETTINGS UX
   File: ./js/settings.js
   Fixed: banner/offer slot re-binding after replace & remove
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    /* ---------- DOM ---------- */
    const form = document.getElementById("settingsForm");
    if (!form) return;

    const saveBtn = document.getElementById("saveBtn");
    const saveBtnText = document.getElementById("saveBtnText");

    /* Hidden remove flags */
    const removeFaviconFlag = document.getElementById("remove_favicon");
    const removeLogoFlag = document.getElementById("remove_logo");

    /* Branches */
    const branchRows = document.getElementById("branchRows");
    const addBranchBtn = document.getElementById("addBranchBtn");
    const branchesEmpty = document.getElementById("branchesEmpty");

    /* Favicon */
    const faviconInput = document.getElementById("favicon_image");
    const faviconZone = document.getElementById("faviconZone");
    const faviconPreview = document.getElementById("faviconPreview");
    const faviconWrap = document.getElementById("faviconWrap");
    const faviconPlaceholder = document.getElementById("faviconPlaceholder");
    const faviconReplaceBtn = document.getElementById("faviconReplaceBtn");
    const faviconRemoveBtn = document.getElementById("faviconRemoveBtn");
    const faviconNewNote = document.getElementById("faviconNewNote");
    const faviconRemovedNote = document.getElementById("faviconRemovedNote");

    /* Logo */
    const logoInput = document.getElementById("logo_image");
    const logoZone = document.getElementById("logoZone");
    const logoPreview = document.getElementById("logoPreview");
    const logoWrap = document.getElementById("logoWrap");
    const logoPlaceholder = document.getElementById("logoPlaceholder");
    const logoReplaceBtn = document.getElementById("logoReplaceBtn");
    const logoRemoveBtn = document.getElementById("logoRemoveBtn");
    const logoNewNote = document.getElementById("logoNewNote");
    const logoRemovedNote = document.getElementById("logoRemovedNote");

    /* Confirm favicon */
    const confirmFaviconOverlay = document.getElementById("confirmFaviconOverlay");
    const confirmFaviconCancel = document.getElementById("confirmFaviconCancel");
    const confirmFaviconRemove = document.getElementById("confirmFaviconRemove");

    /* Confirm logo */
    const confirmLogoOverlay = document.getElementById("confirmLogoOverlay");
    const confirmLogoCancel = document.getElementById("confirmLogoCancel");
    const confirmLogoRemove = document.getElementById("confirmLogoRemove");

    /* Payment / Tax */
    const keySecretInput = document.getElementById("razorpay_key_secret");
    const toggleSecret = document.getElementById("toggleSecret");
    const eyeIcon = document.getElementById("eyeIcon");
    const taxStatusInput = document.getElementById("tax_status");
    const taxToggleRow = document.getElementById("taxToggleRow");
    const taxToggleTitle = document.getElementById("taxToggleTitle");
    const taxToggleDesc = document.getElementById("taxToggleDesc");
    const taxFieldsWrap = document.getElementById("taxFieldsWrap");
    const taxTypeExclusive = document.getElementById("tax_type_exclusive");
    const taxTypeInclusive = document.getElementById("tax_type_inclusive");
    const taxRateInput = document.getElementById("tax_rate");
    const gstNumberInput = document.getElementById("gst_number");
    const gstHelp = document.getElementById("gstHelp");
    const gstBadge = document.getElementById("gstBadge");
    const gstRequiredMark = document.getElementById("gstRequiredMark");
    const gstOptionalTag = document.getElementById("gstOptionalTag");

    /* Modals */
    const errorOverlay = document.getElementById("errorOverlay");
    const errorTitle = document.getElementById("errorTitle");
    const errorText = document.getElementById("errorText");
    const errorOkBtn = document.getElementById("errorOkBtn");
    const successOverlay = document.getElementById("successOverlay");
    const successText = document.getElementById("successText");
    const successOkBtn = document.getElementById("successOkBtn");

    /* State */
    let branchCounter = 0;
    let removedBanners = {};

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

    function setLoading(isLoading) {
        if (!saveBtn || !saveBtnText) return;
        saveBtn.disabled = isLoading;
        saveBtnText.innerHTML = isLoading
            ? '<span class="btn-spinner"></span> Saving...'
            : 'Save All Settings';
    }

    /* =========================================
       MODALS
       ========================================= */
    function showError(message, title) {
        if (errorTitle) errorTitle.textContent = title || "Oops!";
        if (errorText) errorText.textContent = message || "Something went wrong.";
        if (errorOverlay) {
            errorOverlay.classList.add("show");
            errorOverlay.setAttribute("aria-hidden", "false");
        }
    }
    function closeError() {
        if (errorOverlay) {
            errorOverlay.classList.remove("show");
            errorOverlay.setAttribute("aria-hidden", "true");
        }
    }
    function closeSuccess() {
        if (successOverlay) {
            successOverlay.classList.remove("show");
            successOverlay.setAttribute("aria-hidden", "true");
        }
    }

    if (errorOkBtn) errorOkBtn.addEventListener("click", closeError);

    if (successOkBtn) successOkBtn.addEventListener("click", () => {
        closeSuccess();
        const activeTab = document.querySelector(".st-tab.active");
        if (activeTab && activeTab.dataset.tab) {
            try { sessionStorage.setItem("settingsActiveTab", activeTab.dataset.tab); } catch (e) { }
        }
        window.location.reload();
    });

    if (errorOverlay) {
        errorOverlay.addEventListener("click", e => {
            if (e.target === errorOverlay) closeError();
        });
    }
    if (successOverlay) {
        successOverlay.addEventListener("click", e => {
            if (e.target === successOverlay) closeSuccess();
        });
    }
    document.addEventListener("keydown", e => {
        if (e.key === "Escape") {
            if (errorOverlay?.classList.contains("show")) closeError();
            if (successOverlay?.classList.contains("show")) closeSuccess();
        }
    });

    /* =========================================
       TABS
       ========================================= */
    document.querySelectorAll(".st-tab").forEach(tab => {
        tab.addEventListener("click", function () {
            const t = this.dataset.tab;
            document.querySelectorAll(".st-tab").forEach(x => x.classList.remove("active"));
            document.querySelectorAll(".st-panel").forEach(p => p.classList.remove("active"));
            this.classList.add("active");
            const panel = document.getElementById("panel-" + t);
            if (panel) panel.classList.add("active");
        });
    });

    /* =========================================
       BRANCHES
       ========================================= */
    function refreshBranchesEmpty() {
        const rows = branchRows.querySelectorAll(".branch-row");
        branchesEmpty.style.display = rows.length === 0 ? "block" : "none";
    }

    function renumberBranches() {
        const rows = branchRows.querySelectorAll(".branch-row");
        rows.forEach((r, idx) => {
            const badge = r.querySelector(".branch-row-badge");
            if (badge) badge.innerHTML = `<i class="bi bi-geo-alt"></i> Branch #${idx + 1}`;
        });
    }

    function addBranchRow(data, scrollTo) {
        branchCounter++;
        data = data || {};

        const row = document.createElement("div");
        row.className = "branch-row";
        row.dataset.rid = String(branchCounter);

        row.innerHTML = `
            <div class="branch-row-head">
                <div class="branch-row-badge">
                    <i class="bi bi-geo-alt"></i> Branch #${branchCounter}
                </div>
                <button type="button" class="branch-row-remove" title="Remove branch">
                    <i class="bi bi-trash3"></i>
                </button>
            </div>

            <div class="branch-grid">
                <div>
                    <label class="field-label">Branch Name <span class="required">*</span></label>
                    <input type="text" class="branch-input js-branch-name"
                           value="${escapeHtml(data.branch_name || "")}"
                           maxlength="150" placeholder="Eg: Main Store">
                </div>
                <div>
                    <label class="field-label">Mobile</label>
                    <input type="text" class="branch-input js-branch-mobile"
                           value="${escapeHtml(data.branch_mobile || "")}"
                           maxlength="30" placeholder="Eg: +91 98765 43210">
                </div>
                <div class="branch-full">
                    <label class="field-label">Address <span class="required">*</span></label>
                    <textarea class="branch-textarea js-branch-address"
                              placeholder="Full address">${escapeHtml(data.branch_address || "")}</textarea>
                </div>
                <div class="branch-full">
                    <label class="field-label">Email</label>
                    <input type="email" class="branch-input js-branch-email"
                           value="${escapeHtml(data.branch_email || "")}"
                           maxlength="190" placeholder="Eg: branch@mrsmill.com">
                </div>
            </div>
        `;

        branchRows.appendChild(row);
        renumberBranches();
        refreshBranchesEmpty();

        if (scrollTo) {
            setTimeout(() => row.scrollIntoView({ behavior: "smooth", block: "center" }), 80);
        }
    }

    if (addBranchBtn) addBranchBtn.addEventListener("click", () => addBranchRow());

    if (branchRows) {
        branchRows.addEventListener("click", e => {
            const btn = e.target.closest(".branch-row-remove");
            if (!btn) return;
            const row = btn.closest(".branch-row");
            if (!row) return;
            row.style.transition = "opacity .18s ease, transform .18s ease";
            row.style.opacity = "0";
            row.style.transform = "translateY(-6px)";
            setTimeout(() => {
                row.remove();
                renumberBranches();
                refreshBranchesEmpty();
            }, 180);
        });
    }

    function collectBranches() {
        const rows = branchRows.querySelectorAll(".branch-row");
        const out = [];
        rows.forEach(r => {
            out.push({
                branch_name: (r.querySelector(".js-branch-name")?.value || "").trim(),
                branch_address: (r.querySelector(".js-branch-address")?.value || "").trim(),
                branch_mobile: (r.querySelector(".js-branch-mobile")?.value || "").trim(),
                branch_email: (r.querySelector(".js-branch-email")?.value || "").trim()
            });
        });
        return out;
    }

    /* =========================================
       IMAGE HELPERS
       ========================================= */
    function showImage(wrap, placeholder, img, url, isIco) {
        img.src = url;
        if (isIco) img.classList.add("ico");
        else img.classList.remove("ico");
        img.style.display = "block";
        wrap.style.display = "inline-block";
        placeholder.style.display = "none";
    }

    function hideImage(wrap, placeholder, img) {
        img.src = "";
        img.style.display = "none";
        wrap.style.display = "none";
        placeholder.style.display = "block";
    }

    function validateImageFile(file, label) {
        if (!file) return false;
        const okType = file.type.startsWith("image/") || /\.(ico|svg)$/i.test(file.name);
        if (!okType) {
            showError(label + ": please choose a valid image file.", "Invalid file");
            return false;
        }
        if (file.size > 3 * 1024 * 1024) {
            showError(label + ": image must be under 3 MB.", "Image too large");
            return false;
        }
        return true;
    }

    /* =========================================
       FAVICON
       ========================================= */
    if (faviconReplaceBtn) {
        faviconReplaceBtn.addEventListener("click", e => {
            e.preventDefault(); e.stopPropagation();
            faviconInput.click();
        });
    }

    if (faviconInput) {
        faviconInput.addEventListener("change", () => {
            const file = faviconInput.files[0];
            if (!file) return;
            if (!validateImageFile(file, "Favicon")) {
                faviconInput.value = "";
                return;
            }
            const reader = new FileReader();
            reader.onload = ev => {
                const isIco = /\.ico$/i.test(file.name);
                showImage(faviconWrap, faviconPlaceholder, faviconPreview, ev.target.result, isIco);
                if (removeFaviconFlag) removeFaviconFlag.value = "0";
                faviconRemovedNote?.classList.remove("show");
                faviconNewNote?.classList.add("show");
            };
            reader.readAsDataURL(file);
        });
    }

    if (faviconRemoveBtn) {
        faviconRemoveBtn.addEventListener("click", e => {
            e.preventDefault(); e.stopPropagation();
            confirmFaviconOverlay?.classList.add("show");
            confirmFaviconOverlay?.setAttribute("aria-hidden", "false");
        });
    }

    function closeFaviconConfirm() {
        confirmFaviconOverlay?.classList.remove("show");
        confirmFaviconOverlay?.setAttribute("aria-hidden", "true");
    }

    confirmFaviconCancel?.addEventListener("click", closeFaviconConfirm);
    confirmFaviconOverlay?.addEventListener("click", e => {
        if (e.target === confirmFaviconOverlay) closeFaviconConfirm();
    });

    confirmFaviconRemove?.addEventListener("click", () => {
        if (removeFaviconFlag) removeFaviconFlag.value = "1";
        if (faviconInput) faviconInput.value = "";
        hideImage(faviconWrap, faviconPlaceholder, faviconPreview);
        faviconNewNote?.classList.remove("show");
        faviconRemovedNote?.classList.add("show");
        closeFaviconConfirm();
    });

    /* =========================================
       LOGO
       ========================================= */
    if (logoReplaceBtn) {
        logoReplaceBtn.addEventListener("click", e => {
            e.preventDefault(); e.stopPropagation();
            logoInput.click();
        });
    }

    if (logoInput) {
        logoInput.addEventListener("change", () => {
            const file = logoInput.files[0];
            if (!file) return;
            if (!validateImageFile(file, "Logo")) {
                logoInput.value = "";
                return;
            }
            const reader = new FileReader();
            reader.onload = ev => {
                showImage(logoWrap, logoPlaceholder, logoPreview, ev.target.result, false);
                if (removeLogoFlag) removeLogoFlag.value = "0";
                logoRemovedNote?.classList.remove("show");
                logoNewNote?.classList.add("show");
            };
            reader.readAsDataURL(file);
        });
    }

    if (logoRemoveBtn) {
        logoRemoveBtn.addEventListener("click", e => {
            e.preventDefault(); e.stopPropagation();
            confirmLogoOverlay?.classList.add("show");
            confirmLogoOverlay?.setAttribute("aria-hidden", "false");
        });
    }

    function closeLogoConfirm() {
        confirmLogoOverlay?.classList.remove("show");
        confirmLogoOverlay?.setAttribute("aria-hidden", "true");
    }

    confirmLogoCancel?.addEventListener("click", closeLogoConfirm);
    confirmLogoOverlay?.addEventListener("click", e => {
        if (e.target === confirmLogoOverlay) closeLogoConfirm();
    });

    confirmLogoRemove?.addEventListener("click", () => {
        if (removeLogoFlag) removeLogoFlag.value = "1";
        if (logoInput) logoInput.value = "";
        hideImage(logoWrap, logoPlaceholder, logoPreview);
        logoNewNote?.classList.remove("show");
        logoRemovedNote?.classList.add("show");
        closeLogoConfirm();
    });

    /* =========================================
       DRAG HIGHLIGHT
       ========================================= */
    [faviconZone, logoZone].forEach(zone => {
        if (!zone) return;
        ["dragenter", "dragover"].forEach(ev => {
            zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.add("dragover"); });
        });
        ["dragleave", "drop"].forEach(ev => {
            zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.remove("dragover"); });
        });
    });

    /* =========================================================
       BANNER SLOTS (fixed)
       — input lives in the DOM permanently; we ONLY swap the
         visual parts (img, buttons) inside the slot, NOT the input
       ========================================================= */

    const BANNER_EMPTY_HTML = (n) => `
        <div class="banner-slot-empty">
            <i class="bi bi-cloud-arrow-up"></i>
            <strong>Click to upload</strong>
            <span>Banner ${n}</span>
        </div>`;

    const BANNER_IMAGE_HTML = (n, src) => `
        <img src="${src}" alt="Banner ${n}">
        <button type="button" class="banner-slot-remove" data-slot="${n}" title="Remove">
            <i class="bi bi-x-lg"></i>
        </button>
        <span class="banner-slot-change">
            <i class="bi bi-arrow-repeat"></i> Change
        </span>`;

    function renderBannerSlot(n) {
        const slot = document.getElementById("bannerSlot" + n);
        if (!slot) return;

        /* Wipe everything except the label + file input */
        const fileInput = document.getElementById("bannerFile" + n);
        Array.from(slot.children).forEach(child => {
            if (child === fileInput) return;
            if (child.classList && child.classList.contains("banner-slot-label")) return;
            child.remove();
        });

        /* Append empty placeholder (the input stays alive) */
        const wrap = document.createElement("div");
        wrap.innerHTML = BANNER_EMPTY_HTML(n);
        slot.appendChild(wrap.firstElementChild);

        slot.classList.remove("has-image");
    }

    function renderBannerSlotWithImage(n, src) {
        const slot = document.getElementById("bannerSlot" + n);
        if (!slot) return;

        /* Wipe everything except the label + file input */
        const fileInput = document.getElementById("bannerFile" + n);
        Array.from(slot.children).forEach(child => {
            if (child === fileInput) return;
            if (child.classList && child.classList.contains("banner-slot-label")) return;
            child.remove();
        });

        /* Append image + buttons */
        const wrap = document.createElement("div");
        wrap.innerHTML = BANNER_IMAGE_HTML(n, src);
        Array.from(wrap.children).forEach(c => slot.appendChild(c));

        slot.classList.add("has-image");

        /* Bind the remove button of the newly inserted UI */
        const removeBtn = slot.querySelector(".banner-slot-remove");
        if (removeBtn) {
            removeBtn.addEventListener("click", function (e) {
                e.stopPropagation();
                removedBanners[n] = true;
                const hidden = document.getElementById("remove_banner_" + n);
                if (hidden) hidden.value = "1";

                /* Clear the input */
                const input = document.getElementById("bannerFile" + n);
                if (input) input.value = "";

                renderBannerSlot(n);
            });
        }
    }

    function bindBannerSlot(n) {
        const slot = document.getElementById("bannerSlot" + n);
        if (!slot) return;

        const fileInput = document.getElementById("bannerFile" + n);

        /* Slot click → open file picker (input is ALIVE inside slot) */
        slot.addEventListener("click", function (e) {
            if (e.target.closest(".banner-slot-remove")) return;
            if (fileInput) fileInput.click();
        });

        /* Change → render preview */
        if (fileInput) {
            fileInput.addEventListener("change", function () {
                const file = this.files && this.files[0];
                if (!file) return;
                if (!validateImageFile(file, "Banner " + n)) {
                    this.value = "";
                    return;
                }
                const reader = new FileReader();
                reader.onload = function (evt) {
                    removedBanners[n] = false;
                    const hidden = document.getElementById("remove_banner_" + n);
                    if (hidden) hidden.value = "0";
                    renderBannerSlotWithImage(n, evt.target.result);
                };
                reader.readAsDataURL(file);
            });
        }

        /* Bind the remove button that may already exist (existing image) */
        const removeBtn = slot.querySelector(".banner-slot-remove");
        if (removeBtn && !removeBtn.dataset.bound) {
            removeBtn.dataset.bound = "1";
            removeBtn.addEventListener("click", function (e) {
                e.stopPropagation();
                removedBanners[n] = true;
                const hidden = document.getElementById("remove_banner_" + n);
                if (hidden) hidden.value = "1";
                if (fileInput) fileInput.value = "";
                renderBannerSlot(n);
            });
        }
    }

    for (let i = 1; i <= 4; i++) bindBannerSlot(i);


    /* =========================================================
       OFFER SLOTS (fixed)
       — same approach: input stays, only visual parts swap
       ========================================================= */

    const OFFER_EMPTY_HTML = (label, name) => `
        <div class="offer-slot-empty">
            <i class="bi bi-cloud-arrow-up"></i>
            <strong>Click to upload</strong>
            <span>${name}</span>
        </div>`;

    const OFFER_IMAGE_HTML = (label, src, key) => `
        <img src="${src}" alt="Offer">
        <button type="button" class="offer-slot-remove" data-slot="${key}" title="Remove">
            <i class="bi bi-x-lg"></i>
        </button>
        <span class="offer-slot-change">
            <i class="bi bi-arrow-repeat"></i> Change
        </span>`;

    function renderOfferSlot(key) {
        const slot = document.getElementById(key === "today" ? "offerSlotToday" : "offerSlotTomorrow");
        if (!slot) return;

        const fileInput = document.getElementById(key === "today" ? "offerFileToday" : "offerFileTomorrow");

        /* Wipe everything except label + input */
        Array.from(slot.children).forEach(child => {
            if (child === fileInput) return;
            if (child.classList && child.classList.contains("offer-slot-label")) return;
            child.remove();
        });

        /* Empty placeholder */
        const label = key === "today" ? "🔥 TODAY OFFER" : "🌅 TOMORROW OFFER";
        const name = key === "today" ? "Today Offer" : "Tomorrow Offer";
        const wrap = document.createElement("div");
        wrap.innerHTML = OFFER_EMPTY_HTML(label, name);
        slot.appendChild(wrap.firstElementChild);

        slot.classList.remove("has-image");
    }

    function renderOfferSlotWithImage(key, src) {
        const slot = document.getElementById(key === "today" ? "offerSlotToday" : "offerSlotTomorrow");
        if (!slot) return;

        const fileInput = document.getElementById(key === "today" ? "offerFileToday" : "offerFileTomorrow");

        /* Wipe everything except label + input */
        Array.from(slot.children).forEach(child => {
            if (child === fileInput) return;
            if (child.classList && child.classList.contains("offer-slot-label")) return;
            child.remove();
        });

        /* Image + buttons */
        const label = key === "today" ? "🔥 TODAY OFFER" : "🌅 TOMORROW OFFER";
        const wrap = document.createElement("div");
        wrap.innerHTML = OFFER_IMAGE_HTML(label, src, key);
        Array.from(wrap.children).forEach(c => slot.appendChild(c));

        slot.classList.add("has-image");

        /* Bind remove */
        const removeBtn = slot.querySelector(".offer-slot-remove");
        if (removeBtn) {
            removeBtn.addEventListener("click", function (e) {
                e.stopPropagation();
                const hidden = document.getElementById(key === "today" ? "remove_today_offer" : "remove_tomorrow_offer");
                if (hidden) hidden.value = "1";
                if (fileInput) fileInput.value = "";
                renderOfferSlot(key);
            });
        }
    }

    function bindOfferSlot(key) {
        const slot = document.getElementById(key === "today" ? "offerSlotToday" : "offerSlotTomorrow");
        if (!slot) return;

        const fileInput = document.getElementById(key === "today" ? "offerFileToday" : "offerFileTomorrow");

        slot.addEventListener("click", function (e) {
            if (e.target.closest(".offer-slot-remove")) return;
            if (fileInput) fileInput.click();
        });

        if (fileInput) {
            fileInput.addEventListener("change", function () {
                const file = this.files && this.files[0];
                if (!file) return;
                if (!validateImageFile(file, key === "today" ? "Today Offer" : "Tomorrow Offer")) {
                    this.value = "";
                    return;
                }
                const reader = new FileReader();
                reader.onload = function (evt) {
                    const hidden = document.getElementById(key === "today" ? "remove_today_offer" : "remove_tomorrow_offer");
                    if (hidden) hidden.value = "0";
                    renderOfferSlotWithImage(key, evt.target.result);
                };
                reader.readAsDataURL(file);
            });
        }

        /* Bind existing remove button if present */
        const removeBtn = slot.querySelector(".offer-slot-remove");
        if (removeBtn && !removeBtn.dataset.bound) {
            removeBtn.dataset.bound = "1";
            removeBtn.addEventListener("click", function (e) {
                e.stopPropagation();
                const hidden = document.getElementById(key === "today" ? "remove_today_offer" : "remove_tomorrow_offer");
                if (hidden) hidden.value = "1";
                if (fileInput) fileInput.value = "";
                renderOfferSlot(key);
            });
        }
    }

    bindOfferSlot("today");
    bindOfferSlot("tomorrow");

    /* =========================================
       SHOW/HIDE SECRET
       ========================================= */
    if (toggleSecret && keySecretInput && eyeIcon) {
        toggleSecret.addEventListener("click", function () {
            const isPwd = keySecretInput.type === "password";
            keySecretInput.type = isPwd ? "text" : "password";
            eyeIcon.className = isPwd ? "bi bi-eye-slash" : "bi bi-eye";
            toggleSecret.setAttribute("aria-label", isPwd ? "Hide secret" : "Show secret");
        });
    }

    /* =========================================
       GST VALIDATION
       ========================================= */
    function validateGST(gst) {
        gst = (gst || "").toUpperCase().trim();
        if (gst === "") return { valid: true, empty: true, message: "" };
        if (gst.length !== 15) return { valid: false, message: "GST must be 15 characters (currently " + gst.length + ")." };
        const re = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;
        if (!re.test(gst)) return { valid: false, message: "Invalid GST format. Example: 22AAAAA0000A1Z5" };
        const state = parseInt(gst.substring(0, 2), 10);
        if (isNaN(state) || state < 1 || state > 37) {
            return { valid: false, message: "Invalid state code (01–37)." };
        }
        return { valid: true, empty: false, message: "" };
    }

    function paintGstHint(result) {
        if (!gstHelp || !gstNumberInput) return;

        const taxEnabled = taxStatusInput ? taxStatusInput.checked : false;
        const baseText = '15-character GSTIN. Example: <strong>22AAAAA0000A1Z5</strong>';

        gstNumberInput.classList.remove("is-valid", "is-invalid", "is-required-empty");
        if (gstBadge) gstBadge.style.display = "none";

        if (result.empty) {
            if (taxEnabled) {
                gstHelp.innerHTML = '<i class="bi bi-exclamation-circle-fill" style="color:#b8893c;"></i> GST required when Tax is enabled.';
                gstHelp.style.color = "#b8893c";
                gstNumberInput.classList.add("is-required-empty");
                if (gstBadge) {
                    gstBadge.className = "gst-badge required";
                    gstBadge.innerHTML = '<i class="bi bi-exclamation-circle-fill"></i> REQUIRED';
                    gstBadge.style.display = "inline-flex";
                }
            } else {
                gstHelp.innerHTML = baseText;
                gstHelp.style.color = "#948c82";
            }
            return;
        }

        if (result.valid) {
            gstHelp.innerHTML = '<i class="bi bi-check-circle-fill" style="color:#2e7d32;"></i> Valid GST Number';
            gstHelp.style.color = "#2e7d32";
            gstNumberInput.classList.add("is-valid");
            if (gstBadge) {
                gstBadge.className = "gst-badge valid";
                gstBadge.innerHTML = '<i class="bi bi-check-circle-fill"></i> VALID';
                gstBadge.style.display = "inline-flex";
            }
            return;
        }

        gstHelp.innerHTML = '<i class="bi bi-exclamation-circle-fill" style="color:#b51f2c;"></i> ' + result.message;
        gstHelp.style.color = "#b51f2c";
        gstNumberInput.classList.add("is-invalid");
        if (gstBadge) {
            gstBadge.className = "gst-badge invalid";
            gstBadge.innerHTML = '<i class="bi bi-x-circle-fill"></i> INVALID';
            gstBadge.style.display = "inline-flex";
        }
    }

    if (gstNumberInput) {
        gstNumberInput.addEventListener("input", function () {
            const cleaned = this.value.toUpperCase().replace(/[^0-9A-Z]/g, "").slice(0, 15);
            if (cleaned !== this.value) this.value = cleaned;
            paintGstHint(validateGST(this.value));
        });
        gstNumberInput.addEventListener("blur", function () {
            paintGstHint(validateGST(this.value));
        });
    }

    /* =========================================
       TAX TOGGLE
       ========================================= */
    function applyTaxUIState() {
        if (!taxStatusInput) return;
        const enabled = taxStatusInput.checked;

        taxToggleRow?.classList.toggle("is-enabled", enabled);
        if (taxToggleTitle) taxToggleTitle.textContent = enabled ? "Tax Enabled" : "Tax Disabled";
        if (taxToggleDesc) {
            taxToggleDesc.textContent = enabled
                ? "Tax will be calculated on all orders. GST required."
                : "Tax will not be applied to any order.";
        }

        taxFieldsWrap?.classList.toggle("is-open", enabled);

        if (gstRequiredMark) gstRequiredMark.style.display = enabled ? "inline" : "none";
        if (gstOptionalTag) gstOptionalTag.style.display = enabled ? "none" : "inline";

        if (!enabled) {
            if (taxRateInput) taxRateInput.value = "0";
            if (taxTypeExclusive) taxTypeExclusive.checked = false;
            if (taxTypeInclusive) taxTypeInclusive.checked = false;
        }

        if (gstNumberInput) paintGstHint(validateGST(gstNumberInput.value));
    }

    if (taxStatusInput) taxStatusInput.addEventListener("change", applyTaxUIState);

    /* =========================================
       LOAD BRANCHES
       ========================================= */
    function loadBranches() {
        branchRows.innerHTML = "";
        branchCounter = 0;

        fetch(BASE_URL + "ajax/settings-get.php", { credentials: "same-origin" })
            .then(r => r.json().catch(() => null))
            .then(res => {
                const branches = (res && res.success && res.data && Array.isArray(res.data.branches))
                    ? res.data.branches
                    : [];

                if (branches.length > 0) {
                    branches.forEach(b => addBranchRow(b));
                } else {
                    addBranchRow();
                }
            })
            .catch(() => addBranchRow());
    }

    /* =========================================
       SUBMIT
       ========================================= */
    form.addEventListener("submit", function (e) {
        e.preventDefault();

        const username = document.getElementById("username").value.trim();
        const mobile = document.getElementById("mobile_number").value.trim();
        const email = document.getElementById("email_address").value.trim();
        const currency = document.getElementById("currency").value.trim() || "₹";
        const currencyCode = document.getElementById("currency_code").value.trim() || "INR";

        if (!username) return showError("Site name is required.", "Missing name");
        if (mobile && !/^[0-9+\-\s()]{6,20}$/.test(mobile)) return showError("Invalid mobile number.", "Invalid mobile");
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return showError("Invalid email.", "Invalid email");

        const branches = collectBranches();
        for (let i = 0; i < branches.length; i++) {
            const b = branches[i];
            const label = `Branch #${i + 1}`;
            const isEmpty = !b.branch_name && !b.branch_address && !b.branch_mobile && !b.branch_email;
            if (isEmpty) continue;
            if (!b.branch_name) return showError(`${label}: name required.`, "Missing name");
            if (!b.branch_address) return showError(`${label}: address required.`, "Missing address");
            if (b.branch_mobile && !/^[0-9+\-\s()]{6,20}$/.test(b.branch_mobile)) return showError(`${label}: invalid mobile.`, "Invalid mobile");
            if (b.branch_email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(b.branch_email)) return showError(`${label}: invalid email.`, "Invalid email");
        }

        const keyId = document.getElementById("razorpay_key_id").value.trim();
        const keySecret = keySecretInput.value.trim();
        const upiId = document.getElementById("upi_id").value.trim();
        const gstNumber = gstNumberInput ? gstNumberInput.value.trim().toUpperCase() : "";

        if (!keyId) return showError("Razorpay Key ID is required.", "Missing Key ID");
        if (!/^rzp_/i.test(keyId)) return showError('Key ID must start with "rzp_".', "Invalid Key ID");
        if (!keySecret) return showError("Razorpay Key Secret is required.", "Missing Key Secret");
        if (keySecret.length < 10) return showError("Key Secret too short.", "Invalid Key Secret");
        if (!upiId) return showError("UPI ID is required.", "Missing UPI ID");
        if (!/^[a-zA-Z0-9._-]{2,}@[a-zA-Z]{2,}$/.test(upiId)) {
            return showError("UPI ID looks invalid. Example: yourname@okhdfcbank", "Invalid UPI ID");
        }

        const taxEnabled = taxStatusInput ? taxStatusInput.checked : false;
        let taxType = "", taxRate = "0";

        if (taxEnabled) {
            if (taxTypeInclusive?.checked) taxType = "inclusive";
            else if (taxTypeExclusive?.checked) taxType = "exclusive";
            else return showError("Please choose tax type.", "Missing Tax Type");

            const rateStr = taxRateInput.value.trim();
            const rateNum = parseFloat(rateStr);
            if (rateStr === "" || isNaN(rateNum)) return showError("Tax rate required.", "Missing Tax Rate");
            if (rateNum < 0 || rateNum > 100) return showError("Tax rate must be 0–100.", "Invalid Tax Rate");
            taxRate = String(rateNum);

            if (!gstNumber) {
                paintGstHint({ valid: true, empty: true, message: "" });
                return showError("GST Number is required when Tax is enabled.", "GST Required");
            }
        }

        if (gstNumber !== "") {
            const gstResult = validateGST(gstNumber);
            if (!gstResult.valid) {
                paintGstHint(gstResult);
                return showError(gstResult.message, "Invalid GST Number");
            }
        }

        setLoading(true);

        const fd = new FormData(form);
        fd.set("branches", JSON.stringify(branches.filter(b =>
            b.branch_name || b.branch_address || b.branch_mobile || b.branch_email
        )));
        fd.set("tax_status", taxEnabled ? "1" : "0");
        fd.set("tax_type", taxType);
        fd.set("tax_rate", taxRate);
        fd.set("gst_number", gstNumber);

        fetch(BASE_URL + "ajax/settings-save.php", {
            method: "POST",
            body: fd,
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => ({ success: false, message: "Unexpected server response." })))
            .then(data => {
                setLoading(false);
                if (!data.success) {
                    showError(data.message || "Failed to save settings.", "Save failed");
                    return;
                }
                if (successText) successText.textContent = data.message || "Settings saved successfully.";
                successOverlay?.classList.add("show");
                successOverlay?.setAttribute("aria-hidden", "false");
            })
            .catch(() => {
                setLoading(false);
                showError("Unable to connect to server.", "Network error");
            });
    });

    /* =========================================
       INIT
       ========================================= */
    applyTaxUIState();
    loadBranches();
    if (gstNumberInput) paintGstHint(validateGST(gstNumberInput.value));

    try {
        const savedTab = sessionStorage.getItem("settingsActiveTab");
        if (savedTab) {
            sessionStorage.removeItem("settingsActiveTab");
            const tabBtn = document.querySelector('.st-tab[data-tab="' + savedTab + '"]');
            const panel = document.getElementById("panel-" + savedTab);
            if (tabBtn && panel) {
                document.querySelectorAll(".st-tab").forEach(x => x.classList.remove("active"));
                document.querySelectorAll(".st-panel").forEach(p => p.classList.remove("active"));
                tabBtn.classList.add("active");
                panel.classList.add("active");
            }
        }
    } catch (e) { }

})();