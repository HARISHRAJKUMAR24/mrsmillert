/* =========================================================
   MRS MILL@ — ADD CUSTOMER (v2026-11-14c)
   Fixed map preview to use proper Google Maps embed URL
   ========================================================= */

(function () {
    "use strict";

    const BASE_URL = (typeof window.ADMIN_URL === "string" && window.ADMIN_URL) ? window.ADMIN_URL : "./";

    const form = document.getElementById("customerForm");
    const saveBtn = document.getElementById("saveBtn");
    const saveBtnText = document.getElementById("saveBtnText");

    const nameInput = document.getElementById("full_name");
    const mobileInput = document.getElementById("mobile_number");
    const mobileHelp = document.getElementById("mobileHelp");

    const passwordInput = document.getElementById("password");
    const passwordHelp = document.getElementById("passwordHelp");

    const addrTypeApt = document.getElementById("addrTypeApt");
    const addrTypeHouse = document.getElementById("addrTypeHouse");
    const aptBlock = document.getElementById("aptBlock");
    const houseBlock = document.getElementById("houseBlock");

    const aptDdWrap = document.getElementById("acAptDdWrap");
    const aptDdToggle = document.getElementById("acAptDdToggle");
    const aptDdLabel = document.getElementById("acAptDdLabel");
    const aptDdSearch = document.getElementById("acAptDdSearch");
    const aptDdList = document.getElementById("acAptDdList");
    const aptIdInput = document.getElementById("acApartmentId");
    const aptCodeInput = document.getElementById("acApartmentCode");
    const aptNameInput = document.getElementById("acApartmentName");

    const aptFullAddress = document.getElementById("aptFullAddress");
    const aptMap = document.getElementById("aptMap");
    const aptLocBtn = document.getElementById("aptLocBtn");
    const aptLocBtnText = document.getElementById("aptLocBtnText");
    const aptLocHint = document.getElementById("aptLocHint");
    const aptOpenMapBtn = document.getElementById("aptOpenMapBtn");
    const aptMapPreview = document.getElementById("aptMapPreview");

    const aptImgDrop = document.getElementById("aptImgDrop");
    const aptImg = document.getElementById("aptImg");
    const aptImgPreview = document.getElementById("aptImgPreview");

    const divDdWrap = document.getElementById("acDivDdWrap");
    const divDdToggle = document.getElementById("acDivDdToggle");
    const divDdLabel = document.getElementById("acDivDdLabel");
    const divDdSearch = document.getElementById("acDivDdSearch");
    const divDdList = document.getElementById("acDivDdList");
    const divInput = document.getElementById("acDivision");
    const divChargeInput = document.getElementById("acDivisionCharge");
    const divHint = document.getElementById("acDivisionHint");

    const houseAddress = document.getElementById("houseAddress");
    const houseMap = document.getElementById("houseMap");
    const houseLocBtn = document.getElementById("houseLocBtn");
    const houseLocBtnText = document.getElementById("houseLocBtnText");
    const houseLocHint = document.getElementById("houseLocHint");
    const houseOpenMapBtn = document.getElementById("houseOpenMapBtn");
    const houseChargeInput = document.getElementById("houseDeliveryCharge");
    const houseImgDrop = document.getElementById("houseImgDrop");
    const houseImg = document.getElementById("houseImg");
    const houseImgPreview = document.getElementById("houseImgPreview");

    const walletToggle = document.getElementById("walletToggle");
    const walletFields = document.getElementById("walletFields");
    const walletAmount = document.getElementById("walletAmount");
    const walletNote = document.getElementById("walletNote");
    const walletPreview = document.getElementById("walletPreview");

    const successOverlay = document.getElementById("successOverlay");
    const successText = document.getElementById("successText");
    const successCode = document.getElementById("successCode");
    const waShareBtn = document.getElementById("waShareBtn");
    const errorOverlay = document.getElementById("errorOverlay");
    const errorText = document.getElementById("errorText");
    const errorOkBtn = document.getElementById("errorOkBtn");

    if (!form) return;

    let apartmentsCache = [];
    let divisionsCache = [];
    let walletEnabled = false;
    let addressType = "apartment";
    let houseMapLink = "";
    let aptMapLink = "";
    let houseImgData = "";
    let aptImgData = "";
    let houseImgFile = null;   /* shrunk file sent to server */
    let aptImgFile = null;
    let aptBusy = false;
    let houseBusy = false;

    function esc(s) {
        return String(s == null ? "" : s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }
    function money(n) { return "₹" + (Number(n) || 0).toFixed(2).replace(/\.00$/, ""); }
    function isValidMapLink(url) { return url ? /(google\.[a-z.]+\/maps|maps\.google\.|maps\.app\.goo\.gl|goo\.gl\/maps)/i.test(url) : false; }

    // ✅ Corrected Map Embed generator
    function getEmbedUrl(url) {
        if (!url) return "";
        url = url.trim();
        if (/google\.com\/maps\/embed/i.test(url)) return url; // Already an embed link

        // Extract lat,lng if present (handles both ?q=lat,lng and ?q=lat,lng&z=15)
        const coords = url.match(/[?&]q=([-0-9.]+),([-0-9.]+)/);
        if (coords) {
            return `https://maps.google.com/maps?q=${coords[1]},${coords[2]}&t=&z=16&ie=UTF8&iwloc=&output=embed`;
        }

        // Extract place name
        const place = url.match(/place\/([^\/]+)/);
        if (place) {
            return `https://maps.google.com/maps?q=${encodeURIComponent(place[1])}&t=&z=16&ie=UTF8&iwloc=&output=embed`;
        }

        // Fallback for random links
        return url.includes("?") ? url + "&output=embed" : url + "?output=embed";
    }

    function showSuccessPopup(message, code, mobile, password, name) {
        if (!successOverlay) return;
        if (successText) successText.textContent = message || "Customer added successfully.";
        if (successCode) {
            if (code) { successCode.textContent = "Customer ID: " + code; successCode.style.display = "inline-block"; }
            else successCode.style.display = "none";
        }
        if (waShareBtn) {
            const waNumber = String(mobile || "").replace(/[^0-9]/g, "");
            const waMsgText = `Hello ${name},\n\nHere are your login details for Mrs Mill@:\n\nMobile: ${mobile}\nPassword: ${password}\n\nPlease keep this safe.`;
            waShareBtn.href = `https://wa.me/${waNumber}?text=${encodeURIComponent(waMsgText)}`;
            waShareBtn.style.display = "inline-flex";
        }
        successOverlay.classList.add("show");
        successOverlay.setAttribute("aria-hidden", "false");
    }
    function closeSuccessPopup() { if (successOverlay) { successOverlay.classList.remove("show"); successOverlay.setAttribute("aria-hidden", "true"); } }
    function showErrorPopup(message) {
        if (!errorOverlay) return;
        if (errorText) errorText.textContent = message || "Please check the form and try again.";
        errorOverlay.classList.add("show");
        errorOverlay.setAttribute("aria-hidden", "false");
        if (errorOkBtn) setTimeout(() => errorOkBtn.focus(), 60);
    }
    function closeErrorPopup() { if (errorOverlay) { errorOverlay.classList.remove("show"); errorOverlay.setAttribute("aria-hidden", "true"); } }

    successOverlay?.addEventListener("click", e => { if (e.target === successOverlay) closeSuccessPopup(); });
    errorOverlay?.addEventListener("click", e => { if (e.target === errorOverlay) closeErrorPopup(); });
    document.addEventListener("keydown", e => {
        if (e.key === "Escape") {
            if (successOverlay?.classList.contains("show")) closeSuccessPopup();
            if (errorOverlay?.classList.contains("show")) closeErrorPopup();
        }
    });
    errorOkBtn?.addEventListener("click", closeErrorPopup);

    function setLoading(isLoading) {
        if (!saveBtn) return;
        saveBtn.disabled = isLoading;
        saveBtnText.innerHTML = isLoading ? '<span class="btn-spinner"></span> Saving...' : 'Save Customer';
    }

    function setAddressType(type) {
        addressType = type;
        addrTypeApt.classList.toggle("active", type === "apartment");
        addrTypeHouse.classList.toggle("active", type === "individual_house");
        aptBlock.style.display = type === "apartment" ? "" : "none";
        houseBlock.style.display = type === "individual_house" ? "" : "none";
    }
    addrTypeApt?.addEventListener("click", () => setAddressType("apartment"));
    addrTypeHouse?.addEventListener("click", () => setAddressType("individual_house"));

    mobileInput?.addEventListener("input", function () {
        this.value = this.value.replace(/[^0-9]/g, "").slice(0, 15);
        const m = this.value.trim();
        if (!mobileHelp) return;
        if (!m) { mobileHelp.textContent = "Enter a 10-digit mobile number."; mobileHelp.className = "field-help"; }
        else if (m.length < 10) { mobileHelp.textContent = "Too short — needs at least 10 digits."; mobileHelp.className = "field-help"; }
        else { mobileHelp.textContent = "Looks good."; mobileHelp.className = "field-help success"; }
    });

    passwordInput?.addEventListener("input", function () {
        const v = this.value;
        if (!passwordHelp) return;
        if (!v) { passwordHelp.textContent = "Password must be at least 3 characters."; passwordHelp.className = "field-help"; }
        else if (v.length < 3) { passwordHelp.textContent = "Too short — minimum 3 characters."; passwordHelp.className = "field-help error"; }
        else { passwordHelp.textContent = "Password is valid."; passwordHelp.className = "field-help success"; }
    });

    function loadApartments() {
        aptDdList.innerHTML = `<div class="sd-empty">Loading apartments…</div>`;
        fetch(BASE_URL + "ajax/manual-get-apartments.php", { credentials: "same-origin" })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !Array.isArray(res.data)) {
                    aptDdList.innerHTML = `<div class="sd-empty">Failed to load apartments.</div>`;
                    return;
                }
                apartmentsCache = res.data;
                renderAptOptions("");
            })
            .catch(() => { aptDdList.innerHTML = `<div class="sd-empty">Unable to connect.</div>`; });
    }

    function renderAptOptions(query) {
        if (!aptDdList) return;
        if (!apartmentsCache.length) { aptDdList.innerHTML = `<div class="sd-empty">No apartments available.</div>`; return; }
        const q = (query || "").trim().toLowerCase();
        const list = q ? apartmentsCache.filter(a =>
            (a.apartment_name || "").toLowerCase().includes(q) ||
            (a.apartment_code || "").toLowerCase().includes(q) ||
            (a.apartment_address || "").toLowerCase().includes(q)) : apartmentsCache;
        if (!list.length) { aptDdList.innerHTML = `<div class="sd-empty">No apartments match "${esc(query)}".</div>`; return; }

        aptDdList.innerHTML = list.slice(0, 60).map(a => {
            const selected = Number(aptIdInput.value) === Number(a.id);
            return `<div class="sd-option ${selected ? 'selected' : ''}" data-id="${a.id}" data-code="${esc(a.apartment_code)}" data-name="${esc(a.apartment_name)}">
                <i class="bi bi-building"></i>
                <div class="name">${esc(a.apartment_name)}</div>
                <div class="meta">#${esc(a.apartment_code || '')}</div>
            </div>`;
        }).join("");
    }

    function openAptDd() {
        aptDdWrap.classList.add("open");
        closeDivDd();
        if (aptDdSearch) { aptDdSearch.value = ""; setTimeout(() => aptDdSearch.focus(), 60); }
        renderAptOptions("");
    }
    function closeAptDd() { aptDdWrap.classList.remove("open"); }

    aptDdToggle?.addEventListener("click", e => {
        e.stopPropagation();
        if (aptDdWrap.classList.contains("open")) closeAptDd(); else openAptDd();
    });
    aptDdSearch?.addEventListener("input", function () { renderAptOptions(this.value); });
    aptDdList?.addEventListener("click", e => {
        const opt = e.target.closest(".sd-option");
        if (!opt) return;
        aptIdInput.value = opt.dataset.id;
        aptCodeInput.value = opt.dataset.code;
        aptNameInput.value = opt.dataset.name;
        aptDdLabel.textContent = opt.dataset.name;
        aptDdLabel.classList.remove("placeholder");
        aptDdToggle.classList.add("has-value");
        closeAptDd();
        const apt = apartmentsCache.find(a => Number(a.id) === Number(opt.dataset.id));
        if (apt) populateDivisions(apt);
    });

    function divLabelText(d) {
        if (d.division_label) return d.division_label;
        const parts = [];
        if (d.block) parts.push("Blk " + d.block);
        if (d.door_no) parts.push("Door " + d.door_no);
        if (d.floor) parts.push("Fl " + d.floor);
        return parts.length ? parts.join(" · ") : ("Division " + (d.division || ""));
    }
    function divValueText(d) {
        if (d.division_value) return d.division_value;
        const parts = [];
        if (d.block) parts.push("Blk " + d.block);
        if (d.door_no) parts.push("Door " + d.door_no);
        if (d.floor) parts.push("Fl " + d.floor);
        return parts.length ? parts.join(" / ") : String(d.division || "");
    }
    function populateDivisions(apt) {
        if (!apt || !Array.isArray(apt.divisions) || !apt.divisions.length) {
            divisionsCache = [];
            divDdToggle.disabled = true;
            divDdLabel.textContent = "No divisions available";
            divDdLabel.classList.add("placeholder");
            divDdToggle.classList.remove("has-value");
            divDdList.innerHTML = "";
            divInput.value = "";
            divChargeInput.value = 0;
            divHint.textContent = "";
            return;
        }
        divisionsCache = apt.divisions;
        divDdToggle.disabled = false;
        divInput.value = "";
        divChargeInput.value = 0;
        divDdLabel.textContent = "— Type or select division —";
        divDdLabel.classList.add("placeholder");
        divDdToggle.classList.remove("has-value");
        divDdList.innerHTML = "";
        divHint.textContent = "";
    }
    function renderDivOptions(query) {
        if (!divDdList) return;
        if (!divisionsCache.length) { divDdList.innerHTML = `<div class="sd-empty">No divisions available.</div>`; return; }
        const q = (query || "").trim().toLowerCase();
        const list = q ? divisionsCache.filter(d => {
            const label = divLabelText(d).toLowerCase();
            const value = divValueText(d).toLowerCase();
            return label.includes(q) || value.includes(q);
        }) : divisionsCache;
        if (!list.length) { divDdList.innerHTML = `<div class="sd-empty">No divisions match "${esc(query)}".</div>`; return; }

        divDdList.innerHTML = list.map(d => {
            const v = divValueText(d);
            const l = divLabelText(d);
            const selected = String(divInput.value) === v;
            return `<div class="sd-option ${selected ? 'selected' : ''}" data-division="${esc(v)}" data-label="${esc(l)}" data-charge="${Number(d.charge || 0)}">
                <i class="bi bi-grid-3x3-gap"></i>
                <div class="name">${esc(l)}</div>
                <div class="meta">₹${Math.round(Number(d.charge || 0))}</div>
            </div>`;
        }).join("");
    }
    function openDivDd() {
        if (divDdToggle.disabled) return;
        divDdWrap.classList.add("open");
        closeAptDd();
        if (divDdSearch) { divDdSearch.value = ""; setTimeout(() => divDdSearch.focus(), 60); }
        renderDivOptions("");
    }
    function closeDivDd() { divDdWrap.classList.remove("open"); }
    divDdToggle?.addEventListener("click", e => {
        e.stopPropagation();
        if (divDdToggle.disabled) return;
        if (divDdWrap.classList.contains("open")) closeDivDd(); else openDivDd();
    });
    divDdSearch?.addEventListener("input", function () { renderDivOptions(this.value); });
    divDdList?.addEventListener("click", e => {
        const opt = e.target.closest(".sd-option");
        if (!opt) return;
        divInput.value = opt.dataset.division;
        divChargeInput.value = Number(opt.dataset.charge || 0);
        divDdLabel.textContent = opt.dataset.label + " · ₹" + Math.round(Number(opt.dataset.charge || 0));
        divDdLabel.classList.remove("placeholder");
        divDdToggle.classList.add("has-value");
        divHint.textContent = "Charge: ₹" + Math.round(Number(opt.dataset.charge || 0));
        closeDivDd();
    });
    document.addEventListener("click", e => {
        if (!e.target.closest("#acAptDdWrap")) closeAptDd();
        if (!e.target.closest("#acDivDdWrap")) closeDivDd();
    });

    if (aptMap) {
        aptMap.addEventListener("input", function () {
            const v = this.value.trim();
            aptMapLink = v;
            if (aptLocHint) {
                if (!v) { aptLocHint.textContent = ""; aptLocHint.className = "field-help"; }
                else if (isValidMapLink(v)) { aptLocHint.textContent = "✓ Google Maps link accepted."; aptLocHint.className = "field-help success"; }
                else { aptLocHint.textContent = "This doesn't look like a Google Maps link."; aptLocHint.className = "field-help error"; }
            }
            if (aptMapPreview) {
                if (!v) { aptMapPreview.style.display = "none"; aptMapPreview.innerHTML = ""; }
                else { aptMapPreview.innerHTML = `<iframe src="${esc(getEmbedUrl(v))}" loading="lazy"></iframe>`; aptMapPreview.style.display = "block"; }
            }
        });
    }
    if (aptOpenMapBtn) aptOpenMapBtn.addEventListener("click", () => window.open("https://www.google.com/maps", "_blank", "noopener,noreferrer"));
    if (aptLocBtn) {
        aptLocBtn.addEventListener("click", function () {
            if (!navigator.geolocation) {
                if (aptLocHint) { aptLocHint.textContent = "Browser doesn't support location."; aptLocHint.className = "field-help error"; }
                return;
            }
            aptLocBtn.disabled = true;
            if (aptLocBtnText) aptLocBtnText.textContent = "Fetching location…";
            if (aptLocHint) { aptLocHint.textContent = "Please allow location permission."; aptLocHint.className = "field-help"; }
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    const lat = pos.coords.latitude, lng = pos.coords.longitude;
                    const link = "https://www.google.com/maps?q=" + lat + "," + lng;
                    aptMapLink = link;
                    if (aptMap) aptMap.value = link;
                    aptLocBtn.disabled = false;
                    aptLocBtn.classList.add("success");
                    if (aptLocBtnText) aptLocBtnText.textContent = "Location Pinned ✓";
                    if (aptLocHint) { aptLocHint.textContent = "Location saved."; aptLocHint.className = "field-help success"; }
                    if (aptMapPreview) { aptMapPreview.innerHTML = `<iframe src="${esc(getEmbedUrl(link))}" loading="lazy"></iframe>`; aptMapPreview.style.display = "block"; }
                },
                function (err) {
                    aptLocBtn.disabled = false;
                    if (aptLocBtnText) aptLocBtnText.textContent = "Get My Current Location";
                    let msg = "Unable to fetch location.";
                    if (err.code === 1) msg = "Permission denied. Paste a link manually.";
                    else if (err.code === 2) msg = "Location unavailable. Paste a link manually.";
                    else if (err.code === 3) msg = "Timed out. Paste a link manually.";
                    if (aptLocHint) { aptLocHint.textContent = msg; aptLocHint.className = "field-help error"; }
                },
                { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
            );
        });
    }

    if (houseMap) {
        houseMap.addEventListener("input", function () {
            const v = this.value.trim();
            houseMapLink = v;
            if (!houseLocHint) return;
            if (!v) { houseLocHint.textContent = ""; houseLocHint.className = "field-help"; return; }
            if (isValidMapLink(v)) { houseLocHint.textContent = "✓ Google Maps link accepted."; houseLocHint.className = "field-help success"; }
            else { houseLocHint.textContent = "This doesn't look like a Google Maps link."; houseLocHint.className = "field-help error"; }
        });
    }
    if (houseOpenMapBtn) houseOpenMapBtn.addEventListener("click", () => window.open("https://www.google.com/maps", "_blank", "noopener,noreferrer"));
    if (houseLocBtn) {
        houseLocBtn.addEventListener("click", function () {
            if (!navigator.geolocation) {
                if (houseLocHint) { houseLocHint.textContent = "Browser doesn't support location."; houseLocHint.className = "field-help error"; }
                return;
            }
            houseLocBtn.disabled = true;
            if (houseLocBtnText) houseLocBtnText.textContent = "Fetching location…";
            if (houseLocHint) { houseLocHint.textContent = "Please allow location permission."; houseLocHint.className = "field-help"; }
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    const lat = pos.coords.latitude, lng = pos.coords.longitude;
                    const link = "https://www.google.com/maps?q=" + lat + "," + lng;
                    houseMapLink = link;
                    if (houseMap) houseMap.value = link;
                    houseLocBtn.disabled = false;
                    houseLocBtn.classList.add("success");
                    if (houseLocBtnText) houseLocBtnText.textContent = "Location Pinned ✓";
                    if (houseLocHint) { houseLocHint.textContent = "Location saved."; houseLocHint.className = "field-help success"; }
                },
                function (err) {
                    houseLocBtn.disabled = false;
                    if (houseLocBtnText) houseLocBtnText.textContent = "Get My Current Location";
                    let msg = "Unable to fetch location.";
                    if (err.code === 1) msg = "Permission denied. Paste a link manually.";
                    else if (err.code === 2) msg = "Location unavailable. Paste a link manually.";
                    else if (err.code === 3) msg = "Timed out. Paste a link manually.";
                    if (houseLocHint) { houseLocHint.textContent = msg; houseLocHint.className = "field-help error"; }
                },
                { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
            );
        });
    }

    /* Shrink the photo in the browser so the server never rejects it as too large */
    function compressImage(file) {
        return new Promise(resolve => {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = function () {
                const scale = Math.min(1, 1280 / Math.max(img.width, img.height));
                const canvas = document.createElement("canvas");
                canvas.width = Math.max(1, Math.round(img.width * scale));
                canvas.height = Math.max(1, Math.round(img.height * scale));
                const ctx = canvas.getContext("2d");
                ctx.fillStyle = "#fff";
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                URL.revokeObjectURL(url);
                canvas.toBlob(blob => {
                    resolve(blob ? new File([blob], "house.jpg", { type: "image/jpeg" }) : file);
                }, "image/jpeg", 0.8);
            };
            img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
            img.src = url;
        });
    }

    if (aptImgDrop && aptImg) {
        aptImgDrop.addEventListener("click", function (e) {
            if (e.target === aptImg) return;
            if (aptBusy) return;
            aptBusy = true;
            try { aptImg.value = ""; aptImg.click(); }
            finally { setTimeout(() => { aptBusy = false; }, 400); }
        });
        aptImg.addEventListener("click", e => e.stopPropagation());
        aptImg.addEventListener("change", function () {
            const file = this.files && this.files[0];
            if (!file) return;
            if (!/^image\//.test(file.type)) { showErrorPopup("Please choose an image file."); return; }
            if (file.size > 15 * 1024 * 1024) { showErrorPopup("Image must be under 15 MB."); return; }
            aptImgFile = compressImage(file);
            const reader = new FileReader();
            reader.onload = function (evt) {
                aptImgData = evt.target.result;
                if (aptImgPreview) {
                    aptImgPreview.classList.remove("img-drop-empty");
                    aptImgPreview.innerHTML = '<img src="' + aptImgData + '" alt="Apartment preview">';
                }
            };
            reader.readAsDataURL(file);
        });
    }

    if (houseImgDrop && houseImg) {
        houseImgDrop.addEventListener("click", function (e) {
            if (e.target === houseImg) return;
            if (houseBusy) return;
            houseBusy = true;
            try { houseImg.value = ""; houseImg.click(); }
            finally { setTimeout(() => { houseBusy = false; }, 400); }
        });
        houseImg.addEventListener("click", e => e.stopPropagation());
        houseImg.addEventListener("change", function () {
            const file = this.files && this.files[0];
            if (!file) return;
            if (!/^image\//.test(file.type)) { showErrorPopup("Please choose an image file."); return; }
            if (file.size > 15 * 1024 * 1024) { showErrorPopup("Image must be under 15 MB."); return; }
            houseImgFile = compressImage(file);
            const reader = new FileReader();
            reader.onload = function (evt) {
                houseImgData = evt.target.result;
                if (houseImgPreview) {
                    houseImgPreview.classList.remove("img-drop-empty");
                    houseImgPreview.innerHTML = '<img src="' + houseImgData + '" alt="House preview">';
                }
            };
            reader.readAsDataURL(file);
        });
    }

    walletToggle?.addEventListener("click", function () {
        walletEnabled = !walletEnabled;
        walletToggle.classList.toggle("on", walletEnabled);
        walletFields.classList.toggle("show", walletEnabled);
        if (walletEnabled) walletAmount.focus();
        else { walletAmount.value = ""; walletNote.value = ""; walletPreview.textContent = "₹0"; }
    });
    walletAmount?.addEventListener("input", function () {
        const v = Number(this.value || 0);
        walletPreview.textContent = money(v > 0 ? v : 0);
    });

    form.addEventListener("submit", function (e) {
        e.preventDefault();
        const name = nameInput.value.trim();
        const mobile = mobileInput.value.trim();
        const password = passwordInput.value.trim();
        if (!name) return showErrorPopup("Customer name is required.");
        if (!/^[0-9]{10,15}$/.test(mobile)) return showErrorPopup("Please enter a valid mobile number (10–15 digits).");
        if (password.length < 3) return showErrorPopup("Password must be at least 3 characters.");

        let addressJson = {};
        if (addressType === "apartment") {
            const aptId = aptIdInput.value;
            const aptCode = aptCodeInput.value;
            const aptName = aptNameInput.value;
            const div = divInput.value;
            const charge = Number(divChargeInput.value || 0);
            const fullAddr = aptFullAddress ? aptFullAddress.value.trim() : "";
            const mapVal = aptMap ? aptMap.value.trim() : "";

            if (!aptId) return showErrorPopup("Please select an apartment.");
            if (!div) return showErrorPopup("Please select a block / floor / door.");
            if (!fullAddr) return showErrorPopup("Apartment full address is required.");
            if (mapVal && !isValidMapLink(mapVal)) return showErrorPopup("The Google Maps link doesn't look valid.");

            let block = "", door = "", floor = "";
            const parts = div.split("/").map(s => s.trim());
            parts.forEach(p => {
                if (/^Blk\s/i.test(p)) block = p.replace(/^Blk\s+/i, "").trim();
                else if (/^Door\s/i.test(p)) door = p.replace(/^Door\s+/i, "").trim();
                else if (/^Fl\s/i.test(p)) floor = p.replace(/^Fl\s+/i, "").trim();
            });

            addressJson = {
                apartment_id: aptId,
                apartment_code: aptCode,
                apartment_name: aptName,
                block: block,
                floor: floor,
                door_no: door,
                division_label: div,
                charge: charge,
                full_address: fullAddr,
                map_link: mapVal
            };
        } else {
            const addr = (houseAddress.value || "").trim();
            const mapVal = (houseMap.value || "").trim();
            const chargeVal = houseChargeInput ? parseFloat(houseChargeInput.value || "0") : 0;

            if (!addr) return showErrorPopup("Please enter the full address.");
            if (mapVal && !isValidMapLink(mapVal)) return showErrorPopup("The Google Maps link doesn't look valid.");
            if (isNaN(chargeVal) || chargeVal < 0) return showErrorPopup("Delivery charge must be 0 or more.");

            addressJson = { full_address: addr, map_link: mapVal, delivery_charge: chargeVal };
        }

        let initWallet = 0, initNote = "";
        if (walletEnabled) {
            initWallet = parseFloat(walletAmount.value || "0");
            initNote = walletNote.value.trim();
            if (!initWallet || initWallet <= 0) return showErrorPopup("Enter a valid wallet amount or turn off the wallet toggle.");
        }

        setLoading(true);
        const fd = new FormData();
        fd.append("id", "0");
        fd.append("full_name", name);
        fd.append("mobile_number", mobile);
        fd.append("password", password);
        fd.append("status", document.getElementById("status").value);
        fd.append("address_type", addressType);
        fd.append("address_json", JSON.stringify(addressJson));
        fd.append("init_wallet", walletEnabled ? initWallet : "0");
        fd.append("init_wallet_note", initNote);

        /* Send the image as a file (not base64 text) to avoid 413 error */
        const imgFile = addressType === "apartment" ? aptImgFile : houseImgFile;

        Promise.resolve(imgFile)
            .then(file => {
                if (file) fd.append("house_image", file);
                return fetch(BASE_URL + "ajax/add-customer-save.php", { method: "POST", body: fd, credentials: "same-origin" });
            })
            .then(r => r.json().catch(() => ({
                success: false,
                message: r.status === 413
                    ? "Image is too large for the server."
                    : "Unexpected server response (HTTP " + r.status + ")."
            })))
            .then(data => {
                if (data.success) {
                    const code = data.data && data.data.code ? data.data.code : "";
                    showSuccessPopup(data.message || "Customer added successfully.", code, mobile, password, name);
                    setLoading(false);
                    form.reset();
                    walletEnabled = false;
                    walletToggle.classList.remove("on");
                    walletFields.classList.remove("show");
                    walletPreview.textContent = "₹0";
                    aptIdInput.value = ""; aptCodeInput.value = ""; aptNameInput.value = "";
                    aptDdLabel.textContent = "— Type or select apartment —"; aptDdLabel.classList.add("placeholder"); aptDdToggle.classList.remove("has-value");
                    if (aptFullAddress) aptFullAddress.value = "";
                    if (aptMap) aptMap.value = "";
                    if (aptLocHint) { aptLocHint.textContent = ""; aptLocHint.className = "field-help"; }
                    if (aptLocBtn) { aptLocBtn.disabled = false; aptLocBtn.classList.remove("success"); }
                    if (aptLocBtnText) aptLocBtnText.textContent = "Get My Current Location";
                    if (aptMapPreview) { aptMapPreview.style.display = "none"; aptMapPreview.innerHTML = ""; }
                    aptMapLink = ""; aptImgData = ""; aptImgFile = null;
                    if (aptImg) aptImg.value = "";
                    if (aptImgPreview) { aptImgPreview.classList.add("img-drop-empty"); aptImgPreview.innerHTML = '<i class="bi bi-image"></i><span>Tap to upload a photo of the apartment building / flat</span>'; }
                    divisionsCache = []; divInput.value = ""; divChargeInput.value = 0; divDdToggle.disabled = true; divDdLabel.textContent = "Select apartment first"; divDdLabel.classList.add("placeholder"); divDdToggle.classList.remove("has-value"); divDdList.innerHTML = ""; divHint.textContent = "";

                    // House resets
                    houseMapLink = ""; houseImgData = ""; houseImgFile = null;
                    if (houseMap) houseMap.value = "";
                    if (houseChargeInput) houseChargeInput.value = "0";
                    if (houseLocBtn) { houseLocBtn.disabled = false; houseLocBtn.classList.remove("success"); }
                    if (houseLocBtnText) houseLocBtnText.textContent = "Get My Current Location";
                    if (houseLocHint) { houseLocHint.textContent = ""; houseLocHint.className = "field-help"; }
                    if (houseImgPreview) { houseImgPreview.classList.add("img-drop-empty"); houseImgPreview.innerHTML = '<i class="bi bi-image"></i><span>Tap to upload a photo of the house</span>'; }
                    if (houseAddress) houseAddress.value = "";

                    setAddressType("apartment");
                } else {
                    showErrorPopup(data.message || "Failed to save customer.");
                    setLoading(false);
                }
            })
            .catch(() => { showErrorPopup("Unable to connect to server."); setLoading(false); });
    });

    loadApartments();
    setAddressType("apartment");
})();