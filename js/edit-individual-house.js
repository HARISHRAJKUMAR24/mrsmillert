/* =========================================================
   MRS MILL@ — EDIT INDIVIDUAL HOUSE
   File: ./js/edit-individual-house.js
   Now also supports optional password change (blank = keep).
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL =
        (typeof window.ADMIN_URL === "string" && window.ADMIN_URL)
            ? window.ADMIN_URL
            : "./";

    const HOUSE_ID   = Number(window.HOUSE_ID || 0);
    const HOUSE_CODE = String(window.HOUSE_CODE || "");

    /* ---------- DOM ---------- */
    const form        = document.getElementById("houseForm");
    const formLoading = document.getElementById("formLoading");
    const saveBtn     = document.getElementById("saveBtn");
    const saveText    = document.getElementById("saveBtnText");
    const codeBadge   = document.getElementById("houseCodeBadge");

    const nameInput     = document.getElementById("houseName");
    const mobileInput   = document.getElementById("houseMobile");
    const passwordInput = document.getElementById("housePassword");
    const addressInput  = document.getElementById("houseAddress");
    const mapInput      = document.getElementById("houseMap");
    const locBtn        = document.getElementById("houseLocBtn");
    const locBtnText    = document.getElementById("houseLocBtnText");
    const locHint       = document.getElementById("houseLocHint");
    const openMapBtn    = document.getElementById("houseOpenMapBtn");
    const imgDrop       = document.getElementById("houseImgDrop");
    const imgInput      = document.getElementById("houseImg");
    const imgPreview    = document.getElementById("houseImgPreview");

    const successOverlay = document.getElementById("successOverlay");
    const successText    = document.getElementById("successText");
    const successCode    = document.getElementById("successCode");
    const editAgainBtn   = document.getElementById("editAgainBtn");

    if (!form) return;

    let houseMapLink    = "";
    let houseImgData    = "";
    let houseImgPath    = "";
    let houseImgRemoved = false;

    /* ---------- PASSWORD EYE TOGGLE ---------- */
    document.addEventListener("click", function (e) {
        const btn = e.target.closest(".pw-toggle");
        if (!btn) return;
        const targetId = btn.getAttribute("data-target");
        const input = document.getElementById(targetId);
        if (!input) return;
        if (input.type === "password") {
            input.type = "text";
            btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
        } else {
            input.type = "password";
            btn.innerHTML = '<i class="bi bi-eye"></i>';
        }
    });

    /* ---------- ERROR POPUP ---------- */
    let errorOverlay = null;
    let errorText = null;

    function buildErrorPopup() {
        if (errorOverlay) return;
        errorOverlay = document.createElement("div");
        errorOverlay.className = "mm-modal-overlay";
        errorOverlay.setAttribute("aria-hidden", "true");
        errorOverlay.id = "errorOverlay";
        errorOverlay.innerHTML = `
            <div class="mm-modal" role="dialog" aria-modal="true">
                <div class="mm-modal-icon mm-modal-icon-error">
                    <i class="bi bi-exclamation-lg"></i>
                </div>
                <h3 class="mm-modal-title">Oops! Something's missing</h3>
                <p class="mm-modal-text" id="errorText">Please check the form and try again.</p>
                <div class="mm-modal-actions">
                    <button type="button" class="mm-btn mm-btn-danger" id="errorOkBtn">
                        <i class="bi bi-check2"></i> Got it
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(errorOverlay);
        errorText = errorOverlay.querySelector("#errorText");
        errorOverlay.addEventListener("click", e => {
            if (e.target === errorOverlay) closeErrorPopup();
        });
        document.addEventListener("keydown", e => {
            if (e.key === "Escape" && errorOverlay.classList.contains("show")) closeErrorPopup();
        });
        errorOverlay.querySelector("#errorOkBtn").addEventListener("click", closeErrorPopup);

        if (!document.getElementById("mmErrorStyles")) {
            const style = document.createElement("style");
            style.id = "mmErrorStyles";
            style.textContent = `
                .mm-modal-icon-error { background: #fdecec; color: #b51f2c; }
                .mm-btn-danger { background: #b51f2c; color: #fff; box-shadow: 0 8px 20px rgba(181,31,44,.22); }
                .mm-btn-danger:hover { background: #8e1722; color: #fff; }
            `;
            document.head.appendChild(style);
        }
    }

    function showError(msg) {
        buildErrorPopup();
        if (errorText) errorText.textContent = msg || "Please check the form and try again.";
        errorOverlay.classList.add("show");
        errorOverlay.setAttribute("aria-hidden", "false");
        const ok = errorOverlay.querySelector("#errorOkBtn");
        if (ok) setTimeout(() => ok.focus(), 60);
    }
    function closeErrorPopup() {
        if (!errorOverlay) return;
        errorOverlay.classList.remove("show");
        errorOverlay.setAttribute("aria-hidden", "true");
    }

    /* ---------- SUCCESS POPUP ---------- */
    function showSuccessPopup(message, code) {
        if (!successOverlay) return;
        if (successText) successText.textContent = message || "House updated successfully.";
        if (successCode) {
            if (code) {
                successCode.textContent = "CODE: " + code;
                successCode.style.display = "inline-block";
            } else {
                successCode.style.display = "none";
            }
        }
        successOverlay.classList.add("show");
        successOverlay.setAttribute("aria-hidden", "false");
    }
    function closeSuccessPopup() {
        if (!successOverlay) return;
        successOverlay.classList.remove("show");
        successOverlay.setAttribute("aria-hidden", "true");
    }
    if (successOverlay) {
        successOverlay.addEventListener("click", e => {
            if (e.target === successOverlay) closeSuccessPopup();
        });
        document.addEventListener("keydown", e => {
            if (e.key === "Escape" && successOverlay.classList.contains("show")) closeSuccessPopup();
        });
    }
    if (editAgainBtn) {
        editAgainBtn.addEventListener("click", e => {
            e.preventDefault();
            closeSuccessPopup();
        });
    }

    /* ---------- HELPERS ---------- */
    function isValidMapLink(url) {
        if (!url) return false;
        return /(google\.[a-z.]+\/maps|maps\.google\.|maps\.app\.goo\.gl|goo\.gl\/maps)/i.test(url);
    }

    function setLoading(isLoading) {
        if (saveBtn) saveBtn.disabled = isLoading;
        if (saveText) saveText.innerHTML = isLoading
            ? '<span class="btn-spinner"></span> Updating...'
            : 'Update House';
    }

    /* ---------- MOBILE: digits only ---------- */
    if (mobileInput) {
        mobileInput.addEventListener("input", function () {
            this.value = this.value.replace(/[^0-9]/g, "").slice(0, 15);
        });
    }

    /* ---------- MAP link manual paste ---------- */
    if (mapInput) {
        mapInput.addEventListener("input", function () {
            const v = this.value.trim();
            houseMapLink = v;
            if (!locHint) return;
            if (!v) { locHint.textContent = ""; locHint.style.color = ""; return; }
            if (isValidMapLink(v)) {
                locHint.textContent = "✓ Google Maps link accepted.";
                locHint.style.color = "#2e7d32";
            } else {
                locHint.textContent = "This doesn't look like a Google Maps link.";
                locHint.style.color = "#b51f2c";
            }
        });
    }

    /* ---------- Open Google Maps ---------- */
    if (openMapBtn) {
        openMapBtn.addEventListener("click", function () {
            window.open("https://www.google.com/maps", "_blank", "noopener,noreferrer");
        });
    }

    /* ---------- Location pin ---------- */
    if (locBtn) {
        locBtn.addEventListener("click", function () {
            if (!navigator.geolocation) {
                if (locHint) {
                    locHint.textContent = "Browser doesn't support location. Paste a link manually.";
                    locHint.style.color = "#b51f2c";
                }
                return;
            }
            locBtn.disabled = true;
            if (locBtnText) locBtnText.textContent = "Fetching location…";
            if (locHint) { locHint.textContent = "Please allow location permission."; locHint.style.color = ""; }

            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;
                    const link = "https://www.google.com/maps?q=" + lat + "," + lng;
                    houseMapLink = link;
                    if (mapInput) mapInput.value = link;
                    locBtn.disabled = false;
                    locBtn.classList.add("success");
                    if (locBtnText) locBtnText.textContent = "Location Pinned ✓";
                    if (locHint) { locHint.textContent = "Google Maps location saved."; locHint.style.color = "#2e7d32"; }
                },
                function (err) {
                    locBtn.disabled = false;
                    if (locBtnText) locBtnText.textContent = "Get My Current Location";
                    let msg = "Unable to fetch location. Paste a link manually.";
                    if (err.code === 1) msg = "Permission denied. Paste a link manually.";
                    else if (err.code === 2) msg = "Location unavailable. Paste a link manually.";
                    else if (err.code === 3) msg = "Timed out. Paste a link manually.";
                    if (locHint) { locHint.textContent = msg; locHint.style.color = "#b51f2c"; }
                },
                { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
            );
        });
    }

    /* ---------- Image picker ---------- */
    if (imgDrop) {
        imgDrop.addEventListener("click", () => { if (imgInput) imgInput.click(); });
    }

    if (imgInput) {
        imgInput.addEventListener("change", function () {
            const file = this.files && this.files[0];
            if (!file) return;
            if (!/^image\//.test(file.type)) { showError("Please choose an image file."); return; }
            if (file.size > 3 * 1024 * 1024) { showError("Image must be under 3 MB."); return; }
            const reader = new FileReader();
            reader.onload = function (evt) {
                houseImgData = evt.target.result;
                houseImgRemoved = false;
                if (imgPreview) {
                    imgPreview.classList.remove("img-drop-empty");
                    imgPreview.innerHTML = '<img src="' + houseImgData + '" alt="House preview">';
                }
            };
            reader.readAsDataURL(file);
        });
    }

    /* ---------- LOAD HOUSE ---------- */
    function loadHouse() {
        const params = HOUSE_ID > 0
            ? "id=" + encodeURIComponent(HOUSE_ID)
            : "code=" + encodeURIComponent(HOUSE_CODE);

        fetch(BASE_URL + "ajax/get-individual-house.php?" + params, {
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => ({ success: false, message: "Unexpected server response." })))
            .then(data => {
                if (!data.success || !data.data) {
                    if (formLoading) {
                        formLoading.innerHTML =
                            '<div style="color:#d71920;font-size:12px;">' +
                            (data.message || "House not found.") + '</div>';
                    }
                    return;
                }

                const h = data.data;

                document.getElementById("house_id").value = h.id;
                if (nameInput)     nameInput.value     = h.customer_name || "";
                if (mobileInput)   mobileInput.value   = h.mobile_number || "";
                if (addressInput)  addressInput.value  = h.full_address || "";
                if (mapInput)      mapInput.value      = h.map_link || "";
                /* Do NOT pre-fill password — leave blank to keep current */
                if (passwordInput) passwordInput.value = "";
                houseMapLink = h.map_link || "";

                if (codeBadge) codeBadge.textContent = "#" + (h.house_code || "");

                houseImgPath = h.house_image || "";
                if (houseImgPath && imgPreview) {
                    imgPreview.classList.remove("img-drop-empty");
                    imgPreview.innerHTML = '<img src="' + BASE_URL + houseImgPath + '" alt="House">';
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

    /* ---------- SUBMIT ---------- */
    form.addEventListener("submit", function (e) {
        e.preventDefault();

        const id       = Number(document.getElementById("house_id").value || 0);
        const name     = (nameInput && nameInput.value || "").trim();
        const mobile   = (mobileInput && mobileInput.value || "").trim();
        const password = (passwordInput && passwordInput.value || "").trim();
        const address  = (addressInput && addressInput.value || "").trim();
        const mapVal   = (mapInput && mapInput.value || "").trim();

        if (id <= 0) { showError("Invalid house ID."); return; }
        if (name.length < 3) { showError("Customer name must be at least 3 characters."); nameInput.focus(); return; }
        if (!/^[0-9]{10,15}$/.test(mobile)) { showError("Mobile must be 10–15 digits."); mobileInput.focus(); return; }
        if (password !== "" && password.length < 3) { showError("Password must be at least 3 characters."); passwordInput.focus(); return; }
        if (!address) { showError("Full address is required."); addressInput.focus(); return; }
        if (!mapVal) { showError("Please pin the location or paste a Google Maps link."); mapInput.focus(); return; }
        if (!isValidMapLink(mapVal)) { showError("Invalid Google Maps link."); mapInput.focus(); return; }

        houseMapLink = mapVal;
        setLoading(true);

        const fd = new FormData();
        fd.append("id", id);
        fd.append("customer_name", name);
        fd.append("mobile_number", mobile);
        fd.append("full_address", address);
        fd.append("map_link", houseMapLink);

        /* Only send password if the user typed one */
        if (password !== "") {
            fd.append("password", password);
        }

        if (houseImgData)    fd.append("house_image_data", houseImgData);
        if (houseImgRemoved) fd.append("remove_image", "1");

        fetch(BASE_URL + "ajax/update-individual-house.php", {
            method: "POST",
            body: fd,
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => ({ success: false, message: "Unexpected server response." })))
            .then(data => {
                setLoading(false);
                if (!data.success) {
                    showError(data.message || "Failed to update.");
                    return;
                }
                showSuccessPopup(
                    data.message || "House updated successfully.",
                    data.data && data.data.house_code ? data.data.house_code : ""
                );
                if (passwordInput) passwordInput.value = "";
            })
            .catch(() => {
                setLoading(false);
                showError("Unable to connect to server.");
            });
    });

    /* ---------- INIT ---------- */
    loadHouse();

})();