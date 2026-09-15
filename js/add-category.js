/* =========================================================
   MRS MILL@ — ADD CATEGORY UX
   File: ./js/add-category.js
   Errors shown in a popup modal
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    /* ---------------- DOM ---------------- */

    const form     = document.getElementById("categoryForm");
    const saveBtn  = document.getElementById("saveBtn");
    const saveText = document.getElementById("saveBtnText");

    const fileInput    = document.getElementById("category_image");
    const uploadZone   = document.getElementById("uploadZone");
    const placeholder  = document.getElementById("uploadPlaceholder");
    const previewWrap  = document.getElementById("previewWrap");
    const previewImg   = document.getElementById("previewImg");
    const removeBtn    = document.getElementById("removePreview");

    const errorOverlay = document.getElementById("errorOverlay");
    const errorTitle   = document.getElementById("errorTitle");
    const errorText    = document.getElementById("errorText");
    const errorOkBtn   = document.getElementById("errorOkBtn");

    const successOverlay = document.getElementById("successOverlay");
    const successText    = document.getElementById("successText");

    if (!form) return;

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
            if (e.key === "Escape" && errorOverlay.classList.contains("show")) {
                closeError();
            }
        });
    }

    /* ---------------- BUTTON STATE ---------------- */

    function setLoading(isLoading) {
        if (!saveBtn || !saveText) return;

        saveBtn.disabled = isLoading;
        saveText.innerHTML = isLoading
            ? '<span class="btn-spinner"></span> Saving...'
            : 'Save Category';
    }

    /* ---------------- FILE PREVIEW ---------------- */

    function showPreview(file) {
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

        const reader = new FileReader();
        reader.onload = function (e) {
            previewImg.src = e.target.result;
            placeholder.style.display = "none";
            previewWrap.style.display = "inline-block";
        };
        reader.readAsDataURL(file);
    }

    if (fileInput) {
        fileInput.addEventListener("change", function () {
            if (fileInput.files[0]) showPreview(fileInput.files[0]);
        });
    }

    if (removeBtn) {
        removeBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            fileInput.value = "";
            previewImg.src = "";
            previewWrap.style.display = "none";
            placeholder.style.display = "block";
        });
    }

    /* drag highlight */

    if (uploadZone) {
        ["dragenter", "dragover"].forEach(ev => {
            uploadZone.addEventListener(ev, e => {
                e.preventDefault();
                uploadZone.classList.add("dragover");
            });
        });

        ["dragleave", "drop"].forEach(ev => {
            uploadZone.addEventListener(ev, e => {
                e.preventDefault();
                uploadZone.classList.remove("dragover");
            });
        });
    }

    /* ---------------- SUBMIT ---------------- */

    form.addEventListener("submit", function (e) {

        e.preventDefault();

        const name   = document.getElementById("category_name").value.trim();
        const status = document.getElementById("status").value;

        if (!name) {
            return showError("Category name is required.", "Missing category name");
        }

        if (!fileInput.files[0]) {
            return showError("Category image is required.", "Image required");
        }

        setLoading(true);

        const formData = new FormData();
        formData.append("category_name", name);
        formData.append("status", status);
        formData.append("category_image", fileInput.files[0]);

        fetch(BASE_URL + "ajax/add-category.php", {
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
                    successText.textContent = data.message || "Category added successfully.";
                }

                successOverlay.classList.add("show");
                successOverlay.setAttribute("aria-hidden", "false");

                setLoading(false);
                form.reset();

                previewImg.src = "";
                previewWrap.style.display = "none";
                placeholder.style.display = "block";

            } else {
                showError(data.message || "Failed to save category.", "Save failed");
                setLoading(false);
            }
        })
        .catch(() => {
            showError("Unable to connect to server.", "Network error");
            setLoading(false);
        });
    });

    /* ---------------- SUCCESS POPUP ---------------- */

    if (successOverlay) {
        successOverlay.addEventListener("click", function (e) {
            if (e.target === successOverlay) {
                successOverlay.classList.remove("show");
                successOverlay.setAttribute("aria-hidden", "true");
            }
        });
    }

})();