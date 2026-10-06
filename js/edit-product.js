/* =========================================================
   MRS MILL@ — EDIT PRODUCT UX
   File: ./js/edit-product.js
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    const PRODUCT_ID = Number(window.PRODUCT_ID || 0);
    const BACK_URL = window.BACK_URL || (BASE_URL + "products.php");

    const form = document.getElementById("productForm");
    const formLoading = document.getElementById("formLoading");
    const saveBtn = document.getElementById("saveBtn");
    const saveText = document.getElementById("saveBtnText");
    const prodBadge = document.getElementById("prodCodeBadge");

    const fileInput = document.getElementById("product_image");
    const imgZone = document.getElementById("imgZone");
    const imgPlacehold = document.getElementById("imgPlaceholder");
    const imgWrap = document.getElementById("imgPreviewWrap");
    const imgPreview = document.getElementById("imgPreview");
    const imgReplace = document.getElementById("imgReplaceBtn");
    const imgRemove = document.getElementById("imgRemoveBtn");
    const newNote = document.getElementById("newImageNote");
    const removedNote = document.getElementById("removedImageNote");
    const removeFlag = document.getElementById("remove_image");

    const addVariantBtn = document.getElementById("addVariantBtn");
    const variantsList = document.getElementById("variantsList");
    const variantsEmpty = document.getElementById("variantsEmpty");

    const statusInput = document.getElementById("product_status");
    const statusRow = document.getElementById("statusToggleRow");
    const statusTitle = document.getElementById("statusToggleTitle");
    const statusDesc = document.getElementById("statusToggleDesc");

    const confirmOverlay = document.getElementById("confirmOverlay");
    const confirmCancel = document.getElementById("confirmCancel");
    const confirmRemove = document.getElementById("confirmRemove");

    const errorOverlay = document.getElementById("errorOverlay");
    const errorText = document.getElementById("errorText");
    const errorTitle = document.getElementById("errorTitle");
    const errorOkBtn = document.getElementById("errorOkBtn");

    const successOverlay = document.getElementById("successOverlay");
    const successText = document.getElementById("successText");
    const stayBtn = document.getElementById("stayBtn");

    if (!form) return;

    let variantCounter = 0;

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
            ? '<span class="btn-spinner"></span> Updating...'
            : 'Update Product';
    }

    /* ---------------- STATUS TOGGLE ---------------- */

    function applyStatusUI() {
        if (!statusInput) return;
        const active = statusInput.checked;
        if (statusRow) statusRow.classList.toggle("is-active", active);
        if (statusTitle) statusTitle.textContent = active ? "Active" : "Inactive";
        if (statusDesc) {
            statusDesc.textContent = active
                ? "Product will be visible to customers."
                : "Product will be hidden from customers.";
        }
    }

    if (statusInput) statusInput.addEventListener("change", applyStatusUI);

    /* ---------------- ERROR ---------------- */

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
    errorOverlay.addEventListener("click", e => {
        if (e.target === errorOverlay) closeError();
    });

    /* ---------------- IMAGE ---------------- */

    function showPreviewFromFile(file) {
        const reader = new FileReader();
        reader.onload = e => {
            imgPreview.src = e.target.result;
            imgWrap.style.display = "inline-block";
            imgPlacehold.style.display = "none";
        };
        reader.readAsDataURL(file);
    }

    function showPreviewFromUrl(url) {
        imgPreview.src = url;
        imgWrap.style.display = "inline-block";
        imgPlacehold.style.display = "none";
    }

    function hidePreview() {
        imgPreview.src = "";
        imgWrap.style.display = "none";
        imgPlacehold.style.display = "block";
    }

    function resetNotes() {
        if (newNote) newNote.classList.remove("show");
        if (removedNote) removedNote.classList.remove("show");
    }

    if (fileInput) {
        fileInput.addEventListener("change", () => {
            const file = fileInput.files[0];
            if (!file) return;

            if (!file.type.startsWith("image/")) {
                showError("Please choose a valid image file.", "Invalid file");
                fileInput.value = "";
                return;
            }

            if (file.size > 3 * 1024 * 1024) {
                showError("Image must be under 3 MB.", "Image too large");
                fileInput.value = "";
                return;
            }

            showPreviewFromFile(file);
            if (removeFlag) removeFlag.value = "0";
            resetNotes();
            if (newNote) newNote.classList.add("show");
        });
    }

    if (imgReplace) {
        imgReplace.addEventListener("click", e => {
            e.preventDefault();
            e.stopPropagation();
            fileInput.click();
        });
    }

    if (imgRemove) {
        imgRemove.addEventListener("click", e => {
            e.preventDefault();
            e.stopPropagation();
            confirmOverlay.classList.add("show");
            confirmOverlay.setAttribute("aria-hidden", "false");
        });
    }

    function closeConfirm() {
        confirmOverlay.classList.remove("show");
        confirmOverlay.setAttribute("aria-hidden", "true");
    }

    if (confirmCancel) confirmCancel.addEventListener("click", closeConfirm);
    confirmOverlay.addEventListener("click", e => {
        if (e.target === confirmOverlay) closeConfirm();
    });

    if (confirmRemove) {
        confirmRemove.addEventListener("click", () => {
            if (removeFlag) removeFlag.value = "1";
            if (fileInput) fileInput.value = "";
            hidePreview();
            resetNotes();
            if (removedNote) removedNote.classList.add("show");
            closeConfirm();
        });
    }

    if (imgZone) {
        ["dragenter", "dragover"].forEach(ev => {
            imgZone.addEventListener(ev, e => {
                e.preventDefault();
                imgZone.classList.add("dragover");
            });
        });
        ["dragleave", "drop"].forEach(ev => {
            imgZone.addEventListener(ev, e => {
                e.preventDefault();
                imgZone.classList.remove("dragover");
            });
        });
    }

    /* ---------------- VARIANTS ---------------- */

    const UNIT_OPTIONS = [
        { v: "liter", t: "Liter" },
        { v: "milliliter", t: "Milliliter" },
        { v: "gram", t: "Gram" },
        { v: "kilogram", t: "Kilogram" },
        { v: "plate", t: "Plate" },
        { v: "packet", t: "Packet" },
        { v: "bucket", t: "Bucket" }
    ];

    function buildUnitOptions(selectedVal) {
        return UNIT_OPTIONS.map(function (u) {
            const sel = (u.v === selectedVal) ? "selected" : "";
            return `<option value="${u.v}" ${sel}>${u.t}</option>`;
        }).join("");
    }

    function refreshVariantsEmpty() {
        const rows = variantsList.querySelectorAll(".variant-row");
        variantsEmpty.style.display = rows.length === 0 ? "block" : "none";
    }

    function renumberVariants() {
        const rows = variantsList.querySelectorAll(".variant-row");
        rows.forEach(function (r, idx) {
            const badge = r.querySelector(".variant-badge");
            if (badge) {
                badge.innerHTML = `<i class="bi bi-layers-half"></i> Variant #${idx + 1}`;
            }
        });
    }

    function addVariantRow(data) {
        variantCounter++;
        data = data || {};

        const contEnabled = Number(data.container_enabled || 0) === 1;
        const contPrice = data.container_price !== undefined && data.container_price !== null
            ? Number(data.container_price)
            : "";

        /* ✅ REAL DB ID if provided, else a temp "new_N" */
        const rowVid = (data.id !== undefined && data.id !== null && Number(data.id) > 0)
            ? String(data.id)
            : "new_" + variantCounter;

        const row = document.createElement("div");
        row.className = "variant-row";
        row.dataset.vid = rowVid;
        row.dataset.isNew = rowVid.indexOf("new_") === 0 ? "1" : "0";

        row.innerHTML = `
            <input type="hidden" class="js-variant-id" value="${escapeHtml(rowVid)}">

            <div class="variant-row-head">
                <div class="variant-badge">
                    <i class="bi bi-layers-half"></i> Variant #${variantCounter}
                </div>
                <button type="button" class="variant-remove-btn" title="Remove variant">
                    <i class="bi bi-trash3"></i>
                </button>
            </div>

            <div class="variant-grid">
                <div>
                    <label class="field-label">Quantity <span class="required">*</span></label>
                    <input type="number"
                           class="variant-input js-quantity"
                           placeholder="Eg: 500"
                           min="0"
                           step="0.01"
                           inputmode="decimal"
                           value="${escapeHtml(data.quantity || "")}">
                </div>
                <div>
                    <label class="field-label">Unit <span class="required">*</span></label>
                    <select class="variant-input js-unit">
                        <option value="">Unit</option>
                        ${buildUnitOptions(data.quantity_unit || "")}
                    </select>
                </div>
            </div>

            <div class="variant-grid">
                <div>
                    <label class="field-label">Quantity Name <span class="required">*</span></label>
                    <input type="text"
                           class="variant-input js-qname"
                           placeholder="Eg: 500g pack"
                           maxlength="100"
                           value="${escapeHtml(data.quantity_name || "")}">
                </div>
                <div>
                    <label class="field-label">Price (₹) <span class="required">*</span></label>
                    <input type="number"
                           class="variant-input js-price"
                           placeholder="Eg: 120"
                           min="0"
                           step="0.01"
                           inputmode="decimal"
                           value="${escapeHtml(data.price || "")}">
                </div>
            </div>

            <div class="container-box js-container-box ${contEnabled ? "active" : ""}">

                <div class="container-toggle-row">
                    <div class="container-toggle-info">
                        <i class="bi bi-box2-heart"></i>
                        <div>
                            <h5>
                                Container Box
                                <span class="optional-tag">Optional</span>
                            </h5>
                            <p>Add a container charge that is added to the variant price.</p>
                        </div>
                    </div>

                    <label class="mm-switch-sm">
                        <input type="checkbox" class="js-container-toggle" ${contEnabled ? "checked" : ""}>
                        <span class="mm-switch-slider"></span>
                    </label>
                </div>

                <div class="container-fields">
                    <label class="field-label">Container Price (₹) <span class="required">*</span></label>
                    <input type="number"
                           class="variant-input js-container-price"
                           placeholder="Eg: 50"
                           min="0"
                           step="0.01"
                           inputmode="decimal"
                           value="${contEnabled && contPrice !== '' ? escapeHtml(String(contPrice)) : ''}">
                </div>

            </div>
        `;

        variantsList.appendChild(row);

        const toggle = row.querySelector(".js-container-toggle");
        const box = row.querySelector(".js-container-box");
        const contInp = row.querySelector(".js-container-price");

        toggle.addEventListener("change", function () {
            box.classList.toggle("active", toggle.checked);
            if (toggle.checked) {
                setTimeout(() => contInp.focus(), 100);
            } else {
                contInp.value = "";
            }
        });

        renumberVariants();
        refreshVariantsEmpty();
    }

    if (addVariantBtn) {
        addVariantBtn.addEventListener("click", () => addVariantRow());
    }

    if (variantsList) {
        variantsList.addEventListener("click", e => {
            const btn = e.target.closest(".variant-remove-btn");
            if (!btn) return;

            const row = btn.closest(".variant-row");
            if (!row) return;

            row.style.transition = "opacity .18s ease, transform .18s ease";
            row.style.opacity = "0";
            row.style.transform = "translateY(-6px)";

            setTimeout(() => {
                row.remove();
                renumberVariants();
                refreshVariantsEmpty();
            }, 180);
        });
    }

    function collectVariants() {
        const rows = variantsList.querySelectorAll(".variant-row");
        const out = [];

        for (let i = 0; i < rows.length; i++) {
            const r = rows[i];

            const containerToggle = r.querySelector(".js-container-toggle");
            const containerOn = !!(containerToggle && containerToggle.checked);
            const containerPrice = containerOn
                ? (r.querySelector(".js-container-price")?.value || "").trim()
                : "";

            /* ✅ REAL DB ID (or "new_N") */
            const vid = (r.querySelector(".js-variant-id")?.value || r.dataset.vid || "").trim();

            out.push({
                id: vid,
                index: i + 1,
                quantity: (r.querySelector(".js-quantity")?.value || "").trim(),
                quantity_unit: (r.querySelector(".js-unit")?.value || "").trim(),
                quantity_name: (r.querySelector(".js-qname")?.value || "").trim(),
                price: (r.querySelector(".js-price")?.value || "").trim(),
                container_enabled: containerOn ? 1 : 0,
                container_price: containerPrice
            });
        }
        return out;
    }

    function validateVariants() {
        const variants = collectVariants();

        if (variants.length === 0) {
            showError("Please add at least one quantity variant.", "No variants");
            return null;
        }

        for (const v of variants) {
            const label = "Variant #" + v.index;

            if (v.quantity === "" || isNaN(Number(v.quantity)) || Number(v.quantity) <= 0) {
                showError(label + ": please enter a valid quantity.", "Invalid quantity");
                return null;
            }
            if (!v.quantity_unit) {
                showError(label + ": please choose a unit.", "Unit required");
                return null;
            }
            if (!v.quantity_name) {
                showError(label + ": quantity name is required.", "Missing quantity name");
                return null;
            }
            if (v.price === "" || isNaN(Number(v.price)) || Number(v.price) < 0) {
                showError(label + ": please enter a valid price.", "Invalid price");
                return null;
            }

            if (v.container_enabled) {
                if (v.container_price === "" || isNaN(Number(v.container_price)) || Number(v.container_price) < 0) {
                    showError(label + ": please enter a valid container price.", "Invalid container price");
                    return null;
                }
            }
        }
        return variants;
    }

    /* ---------------- LOAD PRODUCT ---------------- */

    function loadProduct() {

        fetch(BASE_URL + "ajax/product-get.php?id=" + PRODUCT_ID, {
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => ({ success: false, message: "Unexpected server response." })))
            .then(data => {

                if (!data.success || !data.data) {
                    formLoading.innerHTML = '<div style="color:#d71920;font-size:12px;">' +
                        (data.message || "Product not found.") + '</div>';
                    return;
                }

                const p = data.data;

                document.getElementById("product_id").value = p.id;
                document.getElementById("product_name").value = p.product_name || "";
                document.getElementById("category_id").value = p.category_id || "";

                if (prodBadge) prodBadge.textContent = "#" + (p.product_code || "");

                if (removeFlag) removeFlag.value = "0";

                const active = String(p.status) === "1";
                if (statusInput) statusInput.checked = active;
                applyStatusUI();

                if (p.image_url) {
                    showPreviewFromUrl(p.image_url);
                } else {
                    hidePreview();
                }
                resetNotes();

                variantsList.innerHTML = "";
                variantCounter = 0;

                if (Array.isArray(p.variants) && p.variants.length > 0) {
                    p.variants.forEach(v => addVariantRow({
                        id: v.id,                              // ✅ pass real id
                        quantity: v.quantity,
                        quantity_unit: v.quantity_unit,
                        quantity_name: v.quantity_name,
                        price: v.price,
                        container_enabled: v.container_enabled || 0,
                        container_price: v.container_price || ""
                    }));
                } else {
                    addVariantRow();
                }
                refreshVariantsEmpty();

                formLoading.style.display = "none";
                form.style.display = "";

            })
            .catch(() => {
                formLoading.innerHTML = '<div style="color:#d71920;font-size:12px;">Unable to connect to server.</div>';
            });
    }

    /* ---------------- SUBMIT ---------------- */

    form.addEventListener("submit", e => {

        e.preventDefault();

        const id = Number(document.getElementById("product_id").value || 0);
        const name = document.getElementById("product_name").value.trim();
        const categoryId = Number(document.getElementById("category_id").value);
        const removeImg = removeFlag ? removeFlag.value : "0";

        if (id <= 0) return showError("Invalid product ID.", "Error");
        if (!name) return showError("Product name is required.", "Missing name");
        if (categoryId <= 0) return showError("Please choose a category.", "Category required");

        const variants = validateVariants();
        if (!variants) return;

        const hasPreview = imgWrap.style.display !== "none";
        const hasNewFile = fileInput.files && fileInput.files[0];

        if (removeImg === "1" && !hasNewFile) {
            return showError("Product must have an image. Please upload one.", "Image required");
        }
        if (!hasPreview && !hasNewFile) {
            return showError("Please upload a product image.", "Image required");
        }

        const status = (statusInput && statusInput.checked) ? "1" : "0";

        setLoading(true);

        const formData = new FormData();
        formData.append("id", id);
        formData.append("product_name", name);
        formData.append("category_id", categoryId);
        formData.append("product_status", status);
        formData.append("remove_image", removeImg);
        formData.append("back", BACK_URL);

        /* ✅ SEND id + data */
        formData.append("variants", JSON.stringify(variants.map(v => ({
            id: v.id,                                          // ← FIXED
            quantity: parseFloat(v.quantity).toFixed(2),
            quantity_unit: v.quantity_unit,
            quantity_name: v.quantity_name,
            price: parseFloat(v.price).toFixed(2),
            container_enabled: v.container_enabled ? 1 : 0,
            container_price: v.container_price !== "" ? parseFloat(v.container_price).toFixed(2) : "0.00"
        }))));

        if (hasNewFile) {
            formData.append("product_image", fileInput.files[0]);
        }

        fetch(BASE_URL + "ajax/update-product.php", {
            method: "POST",
            body: formData,
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => ({ success: false, message: "Unexpected server response." })))
            .then(data => {

                if (data.success) {
                    if (successText) successText.textContent = data.message || "Product updated.";
                    successOverlay.classList.add("show");
                    successOverlay.setAttribute("aria-hidden", "false");
                    setLoading(false);
                    fileInput.value = "";
                    if (removeFlag) removeFlag.value = "0";
                    resetNotes();
                } else {
                    showError(data.message || "Failed to update.", "Save failed");
                    setLoading(false);
                }
            })
            .catch(() => {
                showError("Unable to connect to server.", "Network error");
                setLoading(false);
            });
    });

    function goBackToList() {
        window.location.href = BACK_URL;
    }

    if (successOverlay) {
        successOverlay.addEventListener("click", e => {
            if (e.target === successOverlay) {
                successOverlay.classList.remove("show");
                successOverlay.setAttribute("aria-hidden", "true");
                goBackToList();
            }
        });
    }

    if (stayBtn) {
        stayBtn.addEventListener("click", () => {
            successOverlay.classList.remove("show");
            successOverlay.setAttribute("aria-hidden", "true");
            loadProduct();
        });
    }

    /* INIT */
    applyStatusUI();
    loadProduct();

})();