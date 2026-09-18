/* =========================================================
   MRS MILL@ — SETTINGS UX
   File: ./js/settings.js
   Store details, multi-branch addresses, favicon/logo.
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    /* ---------------- DOM ---------------- */

    const form = document.getElementById("settingsForm");
    const formLoading = document.getElementById("formLoading");
    const saveBtn = document.getElementById("saveBtn");
    const saveText = document.getElementById("saveBtnText");

    const removeFaviconFlag = document.getElementById("remove_favicon");
    const removeLogoFlag = document.getElementById("remove_logo");

    /* branches */
    const branchRows = document.getElementById("branchRows");
    const addBranchBtn = document.getElementById("addBranchBtn");
    const addBranchBtnBottom = document.getElementById("addBranchBtnBottom");
    const branchesEmpty = document.getElementById("branchesEmpty");

    /* favicon */
    const faviconZone = document.getElementById("faviconZone");
    const faviconInput = document.getElementById("favicon_image");
    const faviconPlaceholder = document.getElementById("faviconPlaceholder");
    const faviconWrap = document.getElementById("faviconWrap");
    const faviconPreview = document.getElementById("faviconPreview");
    const faviconReplaceBtn = document.getElementById("faviconReplaceBtn");
    const faviconRemoveBtn = document.getElementById("faviconRemoveBtn");
    const faviconNewNote = document.getElementById("faviconNewNote");
    const faviconRemovedNote = document.getElementById("faviconRemovedNote");

    /* logo */
    const logoZone = document.getElementById("logoZone");
    const logoInput = document.getElementById("logo_image");
    const logoPlaceholder = document.getElementById("logoPlaceholder");
    const logoWrap = document.getElementById("logoWrap");
    const logoPreview = document.getElementById("logoPreview");
    const logoReplaceBtn = document.getElementById("logoReplaceBtn");
    const logoRemoveBtn = document.getElementById("logoRemoveBtn");
    const logoNewNote = document.getElementById("logoNewNote");
    const logoRemovedNote = document.getElementById("logoRemovedNote");

    /* confirm favicon */
    const confirmFaviconOverlay = document.getElementById("confirmFaviconOverlay");
    const confirmFaviconCancel = document.getElementById("confirmFaviconCancel");
    const confirmFaviconRemove = document.getElementById("confirmFaviconRemove");

    /* confirm logo */
    const confirmLogoOverlay = document.getElementById("confirmLogoOverlay");
    const confirmLogoCancel = document.getElementById("confirmLogoCancel");
    const confirmLogoRemove = document.getElementById("confirmLogoRemove");

    /* error + success */
    const errorOverlay = document.getElementById("errorOverlay");
    const errorTitle = document.getElementById("errorTitle");
    const errorText = document.getElementById("errorText");
    const errorOkBtn = document.getElementById("errorOkBtn");

    const successOverlay = document.getElementById("successOverlay");
    const successText = document.getElementById("successText");
    const successOkBtn = document.getElementById("successOkBtn");

    if (!form) return;

    let branchCounter = 0;

    /* ---------------- ERROR / SUCCESS ---------------- */

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
    if (successOkBtn) successOkBtn.addEventListener("click", closeSuccess);

    if (errorOverlay) {
        errorOverlay.addEventListener("click", e => {
            if (e.target === errorOverlay) closeError();
        });
        document.addEventListener("keydown", e => {
            if (e.key === "Escape" && errorOverlay.classList.contains("show")) closeError();
        });
    }

    if (successOverlay) {
        successOverlay.addEventListener("click", e => {
            if (e.target === successOverlay) closeSuccess();
        });
    }

    /* ---------------- LOADING ---------------- */

    function setLoading(isLoading) {
        if (!saveBtn || !saveText) return;
        saveBtn.disabled = isLoading;
        saveText.innerHTML = isLoading
            ? '<span class="btn-spinner"></span> Saving...'
            : 'Save Settings';
    }

    /* ---------------- HELPERS ---------------- */

    function escapeHtml(str) {
        return String(str ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    /* ---------------- IMAGE HELPERS ---------------- */

    function showImage(wrap, placeholder, img, url, isIco) {
        img.src = url;
        if (isIco) img.classList.add("ico");
        else img.classList.remove("ico");
        wrap.style.display = "inline-block";
        placeholder.style.display = "none";
    }

    function hideImage(wrap, placeholder, img) {
        img.src = "";
        wrap.style.display = "none";
        placeholder.style.display = "block";
    }

    function validateImageFile(file, label) {
        if (!file) return false;

        const okType = file.type.startsWith("image/") ||
            /\.(ico|svg)$/i.test(file.name);

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

    /* =========================================================
       BRANCHES
       ========================================================= */

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
                    <input type="text"
                           class="branch-input js-branch-name"
                           value="${escapeHtml(data.branch_name || "")}"
                           maxlength="150"
                           placeholder="Eg: Main Store">
                </div>
                <div>
                    <label class="field-label">Mobile</label>
                    <input type="text"
                           class="branch-input js-branch-mobile"
                           value="${escapeHtml(data.branch_mobile || "")}"
                           maxlength="30"
                           placeholder="Eg: +91 98765 43210">
                </div>

                <div class="branch-full">
                    <label class="field-label">Address <span class="required">*</span></label>
                    <textarea class="branch-textarea js-branch-address"
                              placeholder="Full address">${escapeHtml(data.branch_address || "")}</textarea>
                </div>

                <div class="branch-full">
                    <label class="field-label">Email</label>
                    <input type="email"
                           class="branch-input js-branch-email"
                           value="${escapeHtml(data.branch_email || "")}"
                           maxlength="190"
                           placeholder="Eg: branch@mrsmill.com">
                </div>
            </div>
        `;

        branchRows.appendChild(row);
        renumberBranches();
        refreshBranchesEmpty();

        if (scrollTo) {
            setTimeout(() => {
                row.scrollIntoView({ behavior: "smooth", block: "center" });
            }, 80);
        }
    }

    if (addBranchBtn) {
        addBranchBtn.addEventListener("click", () => addBranchRow());
    }
    if (addBranchBtnBottom) {
        addBranchBtnBottom.addEventListener("click", () => addBranchRow({}, true));
    }

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

    /* ---------------- LOAD SETTINGS ---------------- */

    function loadSettings() {

        fetch(BASE_URL + "ajax/settings-get.php", { credentials: "same-origin" })
            .then(r => r.json().catch(() => ({ success: false, message: "Unexpected server response." })))
            .then(data => {

                if (!data.success || !data.data) {
                    if (formLoading) {
                        formLoading.innerHTML =
                            '<div style="color:#d71920;font-size:12px;">' +
                            (data.message || "Settings not found.") +
                            '</div>';
                    }
                    return;
                }

                const s = data.data;

                document.getElementById("username").value = s.username || "";
                document.getElementById("mobile_number").value = s.mobile_number || "";
                document.getElementById("email_address").value = s.email_address || "";
                document.getElementById("currency").value = s.currency || "₹";
                document.getElementById("currency_code").value = s.currency_code || "INR";

                if (removeFaviconFlag) removeFaviconFlag.value = "0";
                if (removeLogoFlag) removeLogoFlag.value = "0";

                if (s.favicon_url) {
                    const isIco = /\.ico(\?|$)/i.test(s.favicon_url);
                    showImage(faviconWrap, faviconPlaceholder, faviconPreview, s.favicon_url, isIco);
                } else {
                    hideImage(faviconWrap, faviconPlaceholder, faviconPreview);
                }

                if (s.logo_url) {
                    showImage(logoWrap, logoPlaceholder, logoPreview, s.logo_url, false);
                } else {
                    hideImage(logoWrap, logoPlaceholder, logoPreview);
                }

                faviconNewNote.classList.remove("show");
                faviconRemovedNote.classList.remove("show");
                logoNewNote.classList.remove("show");
                logoRemovedNote.classList.remove("show");

                /* Branches */
                branchRows.innerHTML = "";
                branchCounter = 0;

                const branches = Array.isArray(s.branches) ? s.branches : [];
                if (branches.length > 0) {
                    branches.forEach(b => addBranchRow(b));
                } else {
                    addBranchRow();  /* one empty row by default */
                }

                if (formLoading) formLoading.style.display = "none";
                form.style.display = "";

            })
            .catch(() => {
                if (formLoading) {
                    formLoading.innerHTML =
                        '<div style="color:#d71920;font-size:12px;">Unable to connect to server.</div>';
                }
            });
    }

    /* ---------------- FAVICON ---------------- */

    if (faviconReplaceBtn) {
        faviconReplaceBtn.addEventListener("click", e => {
            e.preventDefault();
            e.stopPropagation();
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
                faviconRemovedNote.classList.remove("show");
                faviconNewNote.classList.add("show");
            };
            reader.readAsDataURL(file);
        });
    }

    if (faviconRemoveBtn) {
        faviconRemoveBtn.addEventListener("click", e => {
            e.preventDefault();
            e.stopPropagation();
            confirmFaviconOverlay.classList.add("show");
            confirmFaviconOverlay.setAttribute("aria-hidden", "false");
        });
    }

    function closeFaviconConfirm() {
        confirmFaviconOverlay.classList.remove("show");
        confirmFaviconOverlay.setAttribute("aria-hidden", "true");
    }

    if (confirmFaviconCancel) confirmFaviconCancel.addEventListener("click", closeFaviconConfirm);
    confirmFaviconOverlay.addEventListener("click", e => {
        if (e.target === confirmFaviconOverlay) closeFaviconConfirm();
    });

    if (confirmFaviconRemove) {
        confirmFaviconRemove.addEventListener("click", () => {
            if (removeFaviconFlag) removeFaviconFlag.value = "1";
            if (faviconInput) faviconInput.value = "";
            hideImage(faviconWrap, faviconPlaceholder, faviconPreview);
            faviconNewNote.classList.remove("show");
            faviconRemovedNote.classList.add("show");
            closeFaviconConfirm();
        });
    }

    /* ---------------- LOGO ---------------- */

    if (logoReplaceBtn) {
        logoReplaceBtn.addEventListener("click", e => {
            e.preventDefault();
            e.stopPropagation();
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
                logoRemovedNote.classList.remove("show");
                logoNewNote.classList.add("show");
            };
            reader.readAsDataURL(file);
        });
    }

    if (logoRemoveBtn) {
        logoRemoveBtn.addEventListener("click", e => {
            e.preventDefault();
            e.stopPropagation();
            confirmLogoOverlay.classList.add("show");
            confirmLogoOverlay.setAttribute("aria-hidden", "false");
        });
    }

    function closeLogoConfirm() {
        confirmLogoOverlay.classList.remove("show");
        confirmLogoOverlay.setAttribute("aria-hidden", "true");
    }

    if (confirmLogoCancel) confirmLogoCancel.addEventListener("click", closeLogoConfirm);
    confirmLogoOverlay.addEventListener("click", e => {
        if (e.target === confirmLogoOverlay) closeLogoConfirm();
    });

    if (confirmLogoRemove) {
        confirmLogoRemove.addEventListener("click", () => {
            if (removeLogoFlag) removeLogoFlag.value = "1";
            if (logoInput) logoInput.value = "";
            hideImage(logoWrap, logoPlaceholder, logoPreview);
            logoNewNote.classList.remove("show");
            logoRemovedNote.classList.add("show");
            closeLogoConfirm();
        });
    }

    /* ---------------- DRAG HIGHLIGHT ---------------- */

    [faviconZone, logoZone].forEach(zone => {
        if (!zone) return;
        ["dragenter", "dragover"].forEach(ev => {
            zone.addEventListener(ev, e => {
                e.preventDefault();
                zone.classList.add("dragover");
            });
        });
        ["dragleave", "drop"].forEach(ev => {
            zone.addEventListener(ev, e => {
                e.preventDefault();
                zone.classList.remove("dragover");
            });
        });
    });

    /* ---------------- SUBMIT ---------------- */

    form.addEventListener("submit", e => {

        e.preventDefault();

        const username = document.getElementById("username").value.trim();
        const mobileNumber = document.getElementById("mobile_number").value.trim();
        const emailAddress = document.getElementById("email_address").value.trim();
        const currency = document.getElementById("currency").value.trim() || "₹";
        const currencyCode = document.getElementById("currency_code").value.trim() || "INR";

        if (!username) {
            return showError("Username is required.", "Missing username");
        }

        if (mobileNumber && !/^[0-9+\-\s()]{6,20}$/.test(mobileNumber)) {
            return showError("Please enter a valid mobile number.", "Invalid mobile");
        }

        if (emailAddress && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailAddress)) {
            return showError("Please enter a valid email address.", "Invalid email");
        }

        /* Validate branches */
        const branches = collectBranches();
        for (let i = 0; i < branches.length; i++) {
            const b = branches[i];
            const label = `Branch #${i + 1}`;

            const isEmpty = !b.branch_name && !b.branch_address && !b.branch_mobile && !b.branch_email;
            if (isEmpty) continue;

            if (!b.branch_name) return showError(`${label}: branch name is required.`, "Missing branch name");
            if (!b.branch_address) return showError(`${label}: branch address is required.`, "Missing branch address");
            if (b.branch_mobile && !/^[0-9+\-\s()]{6,20}$/.test(b.branch_mobile)) {
                return showError(`${label}: invalid mobile number.`, "Invalid mobile");
            }
            if (b.branch_email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(b.branch_email)) {
                return showError(`${label}: invalid email address.`, "Invalid email");
            }
        }

        setLoading(true);

        const formData = new FormData();
        formData.append("username", username);
        formData.append("mobile_number", mobileNumber);
        formData.append("email_address", emailAddress);
        formData.append("currency", currency);
        formData.append("currency_code", currencyCode);

        if (removeFaviconFlag) formData.append("remove_favicon", removeFaviconFlag.value);
        if (removeLogoFlag) formData.append("remove_logo", removeLogoFlag.value);

        if (faviconInput.files[0]) formData.append("favicon_image", faviconInput.files[0]);
        if (logoInput.files[0]) formData.append("logo_image", logoInput.files[0]);

        const cleanBranches = branches.filter(b =>
            b.branch_name || b.branch_address || b.branch_mobile || b.branch_email
        );
        formData.append("branches", JSON.stringify(cleanBranches));

        fetch(BASE_URL + "ajax/update-settings.php", {
            method: "POST",
            body: formData,
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => ({ success: false, message: "Unexpected server response." })))
            .then(data => {

                if (data.success) {

                    if (successText) {
                        successText.textContent = data.message || "Settings updated successfully.";
                    }

                    successOverlay.classList.add("show");
                    successOverlay.setAttribute("aria-hidden", "false");

                    setLoading(false);

                    faviconInput.value = "";
                    logoInput.value = "";
                    if (removeFaviconFlag) removeFaviconFlag.value = "0";
                    if (removeLogoFlag) removeLogoFlag.value = "0";

                    faviconNewNote.classList.remove("show");
                    faviconRemovedNote.classList.remove("show");
                    logoNewNote.classList.remove("show");
                    logoRemovedNote.classList.remove("show");

                } else {
                    showError(data.message || "Failed to save settings.", "Save failed");
                    setLoading(false);
                }
            })
            .catch(() => {
                showError("Unable to connect to server.", "Network error");
                setLoading(false);
            });
    });

    /* ---------------- INIT ---------------- */

    loadSettings();

})();