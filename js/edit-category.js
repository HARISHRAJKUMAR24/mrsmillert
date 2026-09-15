/* =========================================================
   MRS MILL@ — EDIT CATEGORY UX
   File: ./js/edit-category.js
   Errors shown in popup modal (same as Add Category)
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
        ? window.ADMIN_URL
        : "./";

    const CATEGORY_ID = Number(window.CATEGORY_ID || 0);

    /* ---------------- DOM ---------------- */

    const form        = document.getElementById("categoryForm");
    const formLoading = document.getElementById("formLoading");
    const saveBtn     = document.getElementById("saveBtn");
    const saveText    = document.getElementById("saveBtnText");

    const fileInput    = document.getElementById("category_image");
    const uploadZone   = document.getElementById("uploadZone");
    const placeholder  = document.getElementById("uploadPlaceholder");
    const previewWrap  = document.getElementById("previewWrap");
    const previewImg   = document.getElementById("previewImg");
    const replaceBtn   = document.getElementById("replaceBtn");
    const removeBtn    = document.getElementById("removeImageBtn");

    const newNote      = document.getElementById("newImageNote");
    const removedNote  = document.getElementById("removedImageNote");
    const removeFlag   = document.getElementById("remove_image");

    const confirmOverlay = document.getElementById("confirmOverlay");
    const confirmCancel  = document.getElementById("confirmCancel");
    const confirmRemove  = document.getElementById("confirmRemove");

    const errorOverlay = document.getElementById("errorOverlay");
    const errorTitle   = document.getElementById("errorTitle");
    const errorText    = document.getElementById("errorText");
    const errorOkBtn   = document.getElementById("errorOkBtn");

    const successOverlay = document.getElementById("successOverlay");
    const successText    = document.getElementById("successText");
    const stayBtn        = document.getElementById("stayBtn");

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
            ? '<span class="btn-spinner"></span> Updating...'
            : 'Update Category';
    }

    function setHasImageState(hasImage) {
        if (!previewWrap || !placeholder) return;

        if (hasImage) {
            previewWrap.style.display = "inline-block";
            placeholder.style.display = "none";
        } else {
            previewWrap.style.display = "none";
            placeholder.style.display = "block";
            if (previewImg) previewImg.src = "";
        }
    }

    function resetNotes() {
        if (newNote) newNote.classList.remove("show");
        if (removedNote) removedNote.classList.remove("show");
    }

    /* ---------------- LOAD ---------------- */

    function loadCategory() {

        fetch(BASE_URL + "ajax/category-get.php?id=" + CATEGORY_ID, {
            credentials: "same-origin"
        })
        .then(r => r.json().catch(() => ({
            success: false,
            message: "Unexpected server response."
        })))
        .then(data => {

            if (!data.success || !data.data) {
                if (formLoading) {
                    formLoading.innerHTML =
                        '<div style="color:#d71920;font-size:12px;">' +
                        (data.message || "Category not found.") +
                        '</div>';
                }
                return;
            }

            const c = data.data;

            document.getElementById("category_id").value   = c.id;
            document.getElementById("category_name").value = c.category_name || "";
            document.getElementById("status").value        = String(c.status ?? 1);

            if (removeFlag) removeFlag.value = "0";

            if (c.image_url) {
                previewImg.src = c.image_url;
                setHasImageState(true);
            } else {
                setHasImageState(false);
            }

            resetNotes();

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

    /* ---------------- REPLACE ---------------- */

    if (replaceBtn && fileInput) {
        replaceBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            fileInput.click();
        });
    }

    /* ---------------- REMOVE ---------------- */

    if (removeBtn) {
        removeBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (confirmOverlay) {
                confirmOverlay.classList.add("show");
                confirmOverlay.setAttribute("aria-hidden", "false");
            }
        });
    }

    function closeConfirm() {
        if (!confirmOverlay) return;
        confirmOverlay.classList.remove("show");
        confirmOverlay.setAttribute("aria-hidden", "true");
    }

    if (confirmCancel) confirmCancel.addEventListener("click", closeConfirm);

    if (confirmOverlay) {
        confirmOverlay.addEventListener("click", function (e) {
            if (e.target === confirmOverlay) closeConfirm();
        });
    }

    if (confirmRemove) {
        confirmRemove.addEventListener("click", function () {

            if (removeFlag) removeFlag.value = "1";
            if (fileInput) fileInput.value = "";

            setHasImageState(false);

            resetNotes();
            if (removedNote) removedNote.classList.add("show");

            closeConfirm();
        });
    }

    /* ---------------- NEW IMAGE ---------------- */

    if (fileInput) {
        fileInput.addEventListener("change", function () {
            if (!fileInput.files[0]) return;

            const file = fileInput.files[0];

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
                setHasImageState(true);

                if (removeFlag) removeFlag.value = "0";

                resetNotes();
                if (newNote) newNote.classList.add("show");
            };
            reader.readAsDataURL(file);
        });
    }

    /* ---------------- DRAG ---------------- */

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

        const id     = Number(document.getElementById("category_id").value || 0);
        const name   = document.getElementById("category_name").value.trim();
        const status = document.getElementById("status").value;
        const removeImg = removeFlag ? removeFlag.value : "0";

        if (id <= 0) {
            return showError("Invalid category ID.", "Error");
        }
        if (!name) {
            return showError("Category name is required.", "Missing category name");
        }

        const hasNewFile = fileInput && fileInput.files && fileInput.files[0];
        const hasPreview = previewWrap && previewWrap.style.display !== "none";

        if (removeImg === "1" && !hasNewFile) {
            return showError("Category must have an image. Please upload one.", "Image required");
        }

        if (!hasPreview && !hasNewFile) {
            return showError("Category image is required.", "Image required");
        }

        setLoading(true);

        const formData = new FormData();
        formData.append("id", id);
        formData.append("category_name", name);
        formData.append("status", status);
        formData.append("remove_image", removeImg);

        if (hasNewFile) {
            formData.append("category_image", fileInput.files[0]);
        }

        fetch(BASE_URL + "ajax/update-category.php", {
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
                    successText.textContent = data.message || "Category updated successfully.";
                }

                if (successOverlay) {
                    successOverlay.classList.add("show");
                    successOverlay.setAttribute("aria-hidden", "false");
                }

                setLoading(false);

                if (fileInput) fileInput.value = "";
                if (removeFlag) removeFlag.value = "0";
                resetNotes();

            } else {
                showError(data.message || "Failed to update category.", "Update failed");
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

    if (stayBtn) {
        stayBtn.addEventListener("click", function () {
            if (successOverlay) {
                successOverlay.classList.remove("show");
                successOverlay.setAttribute("aria-hidden", "true");
            }
            loadCategory();
        });
    }

    /* ---------------- INIT ---------------- */

    loadCategory();

})();