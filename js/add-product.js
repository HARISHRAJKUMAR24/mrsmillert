/* =========================================================
   MRS MILL@ — ADD PRODUCT UX
   File: ./js/add-product.js
   Features:
   - Collapsible Apartment Management box
   - All apartments preselected by default
   - Product image upload
   - MULTI QUANTITY VARIANTS (quantity + unit + name + price)
   - Active / Inactive status toggle
   - All errors shown in popup modal
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    const APARTMENTS = Array.isArray(window.APARTMENTS) ? window.APARTMENTS : [];

    /* ---------------- DOM ---------------- */

    const form     = document.getElementById("productForm");
    const saveBtn  = document.getElementById("saveBtn");
    const saveText = document.getElementById("saveBtnText");

    /* image */
    const fileInput    = document.getElementById("product_image");
    const imgZone      = document.getElementById("imgZone");
    const imgPlacehold = document.getElementById("imgPlaceholder");
    const imgWrap      = document.getElementById("imgPreviewWrap");
    const imgPreview   = document.getElementById("imgPreview");
    const imgReplace   = document.getElementById("imgReplaceBtn");
    const imgRemove    = document.getElementById("imgRemoveBtn");

    /* variants */
    const addVariantBtn = document.getElementById("addVariantBtn");
    const variantsList  = document.getElementById("variantsList");
    const variantsEmpty = document.getElementById("variantsEmpty");

    /* status */
    const statusInput    = document.getElementById("product_status");
    const statusRow      = document.getElementById("statusToggleRow");
    const statusTitle    = document.getElementById("statusToggleTitle");
    const statusDesc     = document.getElementById("statusToggleDesc");

    /* apartments */
    const aptBox      = document.getElementById("apartmentsBox");
    const aptHead     = document.getElementById("apartmentsHead");
    const aptBody     = document.getElementById("apartmentsBody");
    const aptList     = document.getElementById("aptList");
    const aptSearch   = document.getElementById("aptSearchInput");
    const countLabel  = document.getElementById("selectedCount");
    const selectAllBtn = document.getElementById("selectAllBtn");
    const selectAllTxt = document.getElementById("selectAllText");

    /* popups */
    const errorOverlay = document.getElementById("errorOverlay");
    const errorText    = document.getElementById("errorText");
    const errorTitle   = document.getElementById("errorTitle");
    const errorOkBtn   = document.getElementById("errorOkBtn");

    const successOverlay = document.getElementById("successOverlay");
    const successText    = document.getElementById("successText");

    if (!form) return;

    /* ---------------- STATE ---------------- */

    const selected = new Set();
    let variantCounter = 0;

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
            : 'Save Product';
    }

    function updateCount() {
        if (!countLabel) return;
        countLabel.textContent = selected.size + " selected";

        if (selectAllBtn && selectAllTxt) {
            if (selected.size === APARTMENTS.length && APARTMENTS.length > 0) {
                selectAllBtn.classList.add("all-active");
                selectAllTxt.textContent = "Deselect All";
            } else {
                selectAllBtn.classList.remove("all-active");
                selectAllTxt.textContent = "Select All";
            }
        }
    }

    /* ---------------- STATUS TOGGLE ---------------- */

    function applyStatusUI() {
        if (!statusInput) return;

        const active = statusInput.checked;

        if (statusRow) {
            statusRow.classList.toggle("is-active", active);
        }
        if (statusTitle) {
            statusTitle.textContent = active ? "Active" : "Inactive";
        }
        if (statusDesc) {
            statusDesc.textContent = active
                ? "Product will be visible to customers."
                : "Product will be hidden from customers.";
        }
    }

    if (statusInput) {
        statusInput.addEventListener("change", applyStatusUI);
    }

    /* ---------------- ERROR POPUP ---------------- */

    function showError(message, title) {
        if (errorTitle) errorTitle.textContent = title || "Oops!";
        if (errorText)  errorText.textContent  = message || "Something went wrong.";
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

    if (errorOkBtn) errorOkBtn.addEventListener("click", closeError);

    if (errorOverlay) {
        errorOverlay.addEventListener("click", function (e) {
            if (e.target === errorOverlay) closeError();
        });
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape" && errorOverlay.classList.contains("show")) closeError();
        });
    }

    /* ---------------- IMAGE ---------------- */

    function showImagePreview(file) {
        const reader = new FileReader();
        reader.onload = function (e) {
            imgPreview.src = e.target.result;
            imgWrap.style.display = "inline-block";
            imgPlacehold.style.display = "none";
        };
        reader.readAsDataURL(file);
    }

    function resetImage() {
        if (fileInput) fileInput.value = "";
        if (imgPreview) imgPreview.src = "";
        if (imgWrap) imgWrap.style.display = "none";
        if (imgPlacehold) imgPlacehold.style.display = "block";
    }

    if (fileInput) {
        fileInput.addEventListener("change", function () {
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

            showImagePreview(file);
        });
    }

    if (imgReplace) {
        imgReplace.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            fileInput.click();
        });
    }

    if (imgRemove) {
        imgRemove.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            resetImage();
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
        { v: "liter",      t: "Liter" },
        { v: "milliliter", t: "Milliliter" },
        { v: "gram",       t: "Gram" },
        { v: "kilogram",   t: "Kilogram" },
        { v: "plate",      t: "Plate" },
        { v: "packet",     t: "Packet" },
        { v: "bucket",     t: "Bucket" }
    ];

    function buildUnitOptions(selectedVal) {
        return UNIT_OPTIONS.map(function (u) {
            const sel = (u.v === selectedVal) ? "selected" : "";
            return `<option value="${u.v}" ${sel}>${u.t}</option>`;
        }).join("");
    }

    function refreshVariantsEmpty() {
        const rows = variantsList.querySelectorAll(".variant-row");
        if (rows.length === 0) {
            variantsEmpty.style.display = "block";
        } else {
            variantsEmpty.style.display = "none";
        }
    }

    function addVariantRow() {
        variantCounter++;

        const row = document.createElement("div");
        row.className = "variant-row";
        row.dataset.vid = String(variantCounter);

        row.innerHTML = `
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
                           inputmode="decimal">
                </div>
                <div>
                    <label class="field-label">Unit <span class="required">*</span></label>
                    <select class="variant-input js-unit">
                        <option value="">Unit</option>
                        ${buildUnitOptions("")}
                    </select>
                </div>
            </div>

            <div class="variant-grid">
                <div>
                    <label class="field-label">Quantity Name <span class="required">*</span></label>
                    <input type="text"
                           class="variant-input js-qname"
                           placeholder="Eg: 500g pack"
                           maxlength="100">
                </div>
                <div>
                    <label class="field-label">Price (₹) <span class="required">*</span></label>
                    <input type="number"
                           class="variant-input js-price"
                           placeholder="Eg: 120"
                           min="0"
                           step="0.01"
                           inputmode="decimal">
                </div>
            </div>
        `;

        variantsList.appendChild(row);

        renumberVariants();
        refreshVariantsEmpty();
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

    if (addVariantBtn) {
        addVariantBtn.addEventListener("click", function () {
            addVariantRow();
        });
    }

    if (variantsList) {
        variantsList.addEventListener("click", function (e) {
            const btn = e.target.closest(".variant-remove-btn");
            if (!btn) return;

            const row = btn.closest(".variant-row");
            if (!row) return;

            row.style.transition = "opacity .18s ease, transform .18s ease";
            row.style.opacity = "0";
            row.style.transform = "translateY(-6px)";

            setTimeout(function () {
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
            const qty   = (r.querySelector(".js-quantity")?.value || "").trim();
            const unit  = (r.querySelector(".js-unit")?.value || "").trim();
            const qname = (r.querySelector(".js-qname")?.value || "").trim();
            const price = (r.querySelector(".js-price")?.value || "").trim();

            out.push({
                index: i + 1,
                quantity: qty,
                quantity_unit: unit,
                quantity_name: qname,
                price: price
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
        }

        return variants;
    }

    /* ---------------- COLLAPSIBLE BOX ---------------- */

    if (aptHead && aptBox) {
        aptHead.addEventListener("click", function (e) {

            if (e.target.closest(".select-all-btn")) return;
            if (e.target.closest(".apt-search")) return;

            aptBox.classList.toggle("collapsed");
        });
    }

    /* ---------------- APARTMENTS ---------------- */

    function renderAptList(filter) {

        filter = (filter || "").toLowerCase().trim();

        const items = APARTMENTS.filter(function (a) {
            if (!filter) return true;
            return (
                String(a.apartment_name).toLowerCase().includes(filter) ||
                String(a.apartment_code).toLowerCase().includes(filter)
            );
        });

        if (items.length === 0) {
            aptList.innerHTML = `
                <div class="apt-empty">
                    <i class="bi bi-building"></i>
                    No apartments found.
                </div>
            `;
            return;
        }

        aptList.innerHTML = items.map(function (a) {

            const isSelected = selected.has(Number(a.id));

            const divisionsHtml = Array.isArray(a.divisions) && a.divisions.length
                ? a.divisions.map(function (d) {
                    return `
                        <span class="apt-division-chip">
                            ${escapeHtml(d.division)}
                            <em>₹${Number(d.charge || 0).toFixed(0)}</em>
                        </span>
                    `;
                }).join("")
                : '<span class="apt-division-chip">No divisions</span>';

            return `
                <div class="apt-item ${isSelected ? "selected" : ""}"
                     data-id="${Number(a.id)}">

                    <div class="apt-tick">
                        <i class="bi bi-check-lg"></i>
                    </div>

                    <div class="apt-main">
                        <div class="apt-name">
                            ${escapeHtml(a.apartment_name)}
                            <span class="apt-code">#${escapeHtml(a.apartment_code)}</span>
                        </div>
                        <div class="apt-divisions">
                            ${divisionsHtml}
                        </div>
                    </div>
                </div>
            `;
        }).join("");
    }

    if (aptList) {
        aptList.addEventListener("click", function (e) {

            const item = e.target.closest(".apt-item");
            if (!item) return;

            const id = Number(item.dataset.id);
            if (!id) return;

            if (selected.has(id)) {
                selected.delete(id);
                item.classList.remove("selected");
            } else {
                selected.add(id);
                item.classList.add("selected");
            }

            updateCount();
        });
    }

    if (selectAllBtn) {
        selectAllBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (selected.size === APARTMENTS.length && APARTMENTS.length > 0) {
                selected.clear();
            } else {
                APARTMENTS.forEach(a => selected.add(Number(a.id)));
            }

            renderAptList(aptSearch ? aptSearch.value : "");
            updateCount();
        });
    }

    let searchTimer = null;
    if (aptSearch) {
        aptSearch.addEventListener("input", function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                renderAptList(aptSearch.value);
            }, 150);
        });
        aptSearch.addEventListener("click", function (e) {
            e.stopPropagation();
        });
    }

    /* ---------------- SUBMIT ---------------- */

    form.addEventListener("submit", function (e) {

        e.preventDefault();

        const name       = document.getElementById("product_name").value.trim();
        const categoryId = Number(document.getElementById("category_id").value);

        if (!name)             return showError("Product name is required.", "Missing product name");
        if (categoryId <= 0)   return showError("Please choose a category.", "Category required");
        if (!fileInput.files[0])
                               return showError("Please upload a product image.", "Image required");

        /* ---- VARIANTS ---- */
        const variants = validateVariants();
        if (!variants) return;

        if (selected.size === 0)
            return showError("Please select at least one apartment.", "No apartments selected");

        /* ---- STATUS ---- */
        const status = (statusInput && statusInput.checked) ? "1" : "0";

        setLoading(true);

        const formData = new FormData();
        formData.append("product_name", name);
        formData.append("category_id", categoryId);
        formData.append("product_status", status);
        formData.append("apartment_ids", JSON.stringify(Array.from(selected)));
        formData.append("variants", JSON.stringify(variants.map(v => ({
            quantity: parseFloat(v.quantity).toFixed(2),
            quantity_unit: v.quantity_unit,
            quantity_name: v.quantity_name,
            price: parseFloat(v.price).toFixed(2)
        }))));
        formData.append("product_image", fileInput.files[0]);

        fetch(BASE_URL + "ajax/add-product.php", {
            method: "POST",
            body: formData,
            credentials: "same-origin"
        })
        .then(r => r.json().catch(() => ({
            success: false,
            message: "Unexpected server response."
        })))
        .then(data => {

            if (data.success) {

                if (successText) {
                    successText.textContent = data.message || "Product added successfully.";
                }

                successOverlay.classList.add("show");
                successOverlay.setAttribute("aria-hidden", "false");

                setLoading(false);
                form.reset();
                resetImage();

                /* reset variants */
                variantsList.innerHTML = "";
                variantCounter = 0;
                refreshVariantsEmpty();

                /* reset apartments */
                selected.clear();
                APARTMENTS.forEach(a => selected.add(Number(a.id)));
                renderAptList(aptSearch ? aptSearch.value : "");
                updateCount();

                /* reset status to Active */
                if (statusInput) statusInput.checked = true;
                applyStatusUI();

            } else {
                showError(data.message || "Failed to save product.", "Save failed");
                setLoading(false);
            }
        })
        .catch(() => {
            showError("Unable to connect to server.", "Network error");
            setLoading(false);
        });
    });

    if (successOverlay) {
        successOverlay.addEventListener("click", function (e) {
            if (e.target === successOverlay) {
                successOverlay.classList.remove("show");
                successOverlay.setAttribute("aria-hidden", "true");
            }
        });
    }

    /* ---------------- INIT ---------------- */

    APARTMENTS.forEach(a => selected.add(Number(a.id)));

    renderAptList("");
    updateCount();

    /* Start with 1 empty variant */
    addVariantRow();

    /* Apply initial status UI */
    applyStatusUI();

})();