<?php
/* ---------------- ROLE ---------------- */
$isAdmin = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin');

$globalSearchUrl = rtrim(ADMIN_URL, '/') . '/ajax/global-search.php';
?>

<header class="topbar">

    <!-- MOBILE MENU -->
    <button class="mobile-menu" onclick="toggleSidebar()" aria-label="Open menu">
        <i class="bi bi-list"></i>
    </button>

    <!-- GLOBAL SEARCH -->
    <div class="global-search" id="globalSearch">

        <div class="global-search-input">

            <i class="bi bi-search search-main-icon"></i>

            <input
                type="text"
                id="globalSearchInput"
                placeholder="Search customers, phone, orders, products..."
                autocomplete="off"
                spellcheck="false">

            <span class="search-shortcut">
                <i class="bi bi-command"></i> K
            </span>

        </div>

        <!-- SEARCH SUGGESTIONS -->
        <div class="global-search-results" id="globalSearchResults">

            <div class="search-empty">
                <i class="bi bi-search"></i>
                <span>Search customers, orders or products</span>
                <small>Search by customer name, phone number, order ID or product</small>
            </div>

        </div>

    </div>

    <!-- RIGHT SIDE -->
    <div class="top-right">

        <button class="notification" type="button">
            <i class="bi bi-bell"></i>
            <span class="notification-dot"></span>
        </button>

        <!-- PROFILE DROPDOWN -->
        <div class="profile-dropdown-wrap">

            <div class="admin-profile" id="profileMenuBtn">

                <div class="admin-avatar">
                    <?= strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)) ?>
                </div>

                <div class="admin-profile-details">

                    <div class="admin-name">
                        <?= htmlspecialchars($_SESSION['admin_name'] ?? 'User') ?>
                    </div>

                    <div class="admin-role">
                        <?= $isAdmin ? 'Admin' : 'Staff' ?>
                    </div>

                </div>

                <i class="bi bi-chevron-down profile-chevron"></i>

            </div>

            <!-- PROFILE MENU -->
            <div class="profile-menu" id="profileMenu">

                <a href="<?= ADMIN_URL ?>settings.php" class="profile-menu-item">
                    <i class="bi bi-person"></i>
                    My Profile
                </a>

                <div class="profile-menu-divider"></div>

                <a href="<?= ADMIN_URL ?>logout.php" class="profile-menu-item logout">
                    <i class="bi bi-box-arrow-right"></i>
                    Logout
                </a>

            </div>

        </div>

    </div>

</header>


<style>
    /* =========================================================
   TOPBAR SEARCH LAYOUT
========================================================= */

    .topbar {
        display: flex;
        align-items: center;
        gap: 18px;
    }

    /* =========================================================
   GLOBAL SEARCH
========================================================= */

    .global-search {
        position: relative;
        flex: 1 1 440px;
        width: 100%;
        max-width: 460px;
        min-width: 180px;
        z-index: 1000;
    }

    .global-search-input {
        width: 100%;
        height: 43px;

        display: flex;
        align-items: center;
        gap: 11px;

        padding: 0 13px;

        background: #fff;
        border: 1px solid #eee7dc;
        border-radius: 9px;

        transition: border-color .2s ease, background .2s ease;
        box-sizing: border-box;
    }

    .global-search-input:focus-within {
        border-color: #c7a66a;
        background: #fff;
    }

    .global-search-input .search-main-icon {
        flex-shrink: 0;
        color: #948c82;
        font-size: 15px;
    }

    .global-search-input input {
        flex: 1;
        width: 100%;
        min-width: 0;

        border: 0;
        outline: 0;

        background: transparent;
        color: #302923;

        font-size: 12px;
        font-weight: 600;
    }

    .global-search-input input::placeholder {
        color: #aaa198;
        font-weight: 500;
    }

    .search-shortcut {
        display: flex;
        align-items: center;
        gap: 4px;

        flex-shrink: 0;

        padding: 4px 7px;

        border: 1px solid #eee7dc;
        border-radius: 5px;

        color: #948c82;
        font-size: 9px;
        font-weight: 700;
        white-space: nowrap;
    }

    /* =========================================================
   SEARCH DROPDOWN
========================================================= */

    .global-search-results {
        position: absolute;

        top: calc(100% + 9px);
        left: 0;
        right: 0;

        width: 100%;
        max-height: 430px;

        overflow-y: auto;
        overscroll-behavior: contain;

        background: #fff;
        border: 1px solid #eee7dc;
        border-radius: 11px;

        box-shadow: 0 12px 35px rgba(0, 0, 0, .10);

        display: none;
        padding: 7px;

        box-sizing: border-box;
        z-index: 1001;
    }

    .global-search-results.show {
        display: block;
    }

    /* GROUP TITLE */

    .search-group-title {
        display: flex;
        align-items: center;
        justify-content: space-between;

        padding: 11px 10px 7px;

        color: #a39a90;

        font-size: 9px;
        font-weight: 800;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    /* RESULT ITEM */

    .search-result-item {
        display: flex;
        align-items: center;
        gap: 11px;

        width: 100%;
        padding: 10px;

        border-radius: 7px;

        text-decoration: none;
        cursor: pointer;

        transition: background .15s ease;
        box-sizing: border-box;
    }

    .search-result-item:hover,
    .search-result-item.active {
        background: #faf7f0;
    }

    .search-result-icon {
        width: 36px;
        height: 36px;

        flex-shrink: 0;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 8px;

        background: #f7f3eb;
        color: #8c7045;

        font-size: 15px;
    }

    .search-result-info {
        flex: 1;
        min-width: 0;
    }

    .search-result-name {
        color: #302923;

        font-size: 12px;
        font-weight: 750;

        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .search-result-detail {
        margin-top: 4px;

        color: #999087;

        font-size: 10px;

        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .search-result-arrow {
        flex-shrink: 0;
        color: #b7aea3;
        font-size: 12px;
    }

    /* EMPTY STATE */

    .search-empty,
    .search-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;

        gap: 9px;
        padding: 27px 12px;

        color: #a39a90;

        font-size: 11px;
        text-align: center;
    }

    .search-empty i {
        font-size: 23px;
    }

    .search-empty small {
        max-width: 280px;
        color: #b0a79e;
        font-size: 10px;
        line-height: 1.6;
    }

    .search-loading i {
        font-size: 18px;
    }

    /* =========================================================
   TOP RIGHT
========================================================= */

    .top-right {
        display: flex;
        align-items: center;
        gap: 12px;

        flex-shrink: 0;
    }

    /* =========================================================
   PROFILE DROPDOWN
========================================================= */

    .profile-dropdown-wrap {
        position: relative;
        flex-shrink: 0;
    }

    .admin-profile {
        display: flex;
        align-items: center;
        gap: 9px;

        cursor: pointer;
        user-select: none;

        padding: 5px 10px;
        border-radius: 10px;

        transition: background .2s ease;
    }

    .admin-profile:hover {
        background: #faf7f0;
    }

    .admin-profile-details {
        min-width: 0;
    }

    .profile-chevron {
        flex-shrink: 0;
        font-size: 10px;
        margin-left: 4px;
        color: #948c82;
    }

    .profile-menu {
        position: absolute;

        top: calc(100% + 8px);
        right: 0;

        background: #fff;
        border: 1px solid #eee7dc;
        border-radius: 12px;

        box-shadow: 0 10px 30px rgba(0, 0, 0, .08);

        min-width: 180px;

        opacity: 0;
        visibility: hidden;
        transform: translateY(-8px);

        transition: all .2s ease;

        z-index: 1002;
        overflow: hidden;
    }

    .profile-dropdown-wrap.open .profile-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .profile-menu-item {
        display: flex;
        align-items: center;
        gap: 10px;

        padding: 12px 16px;

        font-size: 12px;
        font-weight: 700;

        color: #4e4841;
        text-decoration: none;

        transition: background .15s ease;
    }

    .profile-menu-item:hover {
        background: #fdfaf4;
        color: #302923;
    }

    .profile-menu-item.logout {
        color: #b51f2c;
    }

    .profile-menu-item.logout:hover {
        background: #fff5f5;
        color: #b51f2c;
    }

    .profile-menu-divider {
        height: 1px;
        background: #f0ebe4;
        margin: 4px 0;
    }

    /* =========================================================
   TABLET
========================================================= */

    @media (max-width: 900px) {

        .topbar {
            gap: 12px;
        }

        .global-search {
            flex: 1 1 auto;
            max-width: none;
        }

        .search-shortcut {
            display: none;
        }

    }

    /* =========================================================
   MOBILE
========================================================= */

    @media (max-width: 600px) {

        .topbar {
            gap: 8px;
        }

        .global-search {
            flex: 1 1 0;
            width: 100%;
            min-width: 0;
        }

        .global-search-input {
            height: 39px;
            padding: 0 10px;
            gap: 8px;
        }

        .global-search-input input {
            font-size: 11px;
        }

        .global-search-input input::placeholder {
            font-size: 10px;
        }

        .global-search-results {
            position: fixed;

            top: 65px;
            left: 10px;
            right: 10px;

            width: auto;
            max-height: 70vh;
        }

        .top-right {
            gap: 7px;
        }

        .admin-profile {
            gap: 5px;
            padding: 4px;
        }

        .admin-profile-details {
            display: none;
        }

        .profile-chevron {
            margin-left: 0;
        }

    }

    /* SMALL MOBILE */

    @media (max-width: 380px) {

        .global-search-input input::placeholder {
            font-size: 9px;
        }

        .global-search-input {
            gap: 6px;
        }

    }
</style>


<script>
    /* =========================================================
   MRS MILL@ — GLOBAL SEARCH
   Endpoint: ajax/global-search.php
========================================================= */

    (() => {

        "use strict";

        const BASE_URL = <?= json_encode(rtrim(ADMIN_URL, '/') . '/') ?>;

        const SEARCH_URL = BASE_URL + "ajax/global-search.php";

        const searchInput = document.getElementById("globalSearchInput");
        const searchResults = document.getElementById("globalSearchResults");
        const searchWrap = document.getElementById("globalSearch");

        if (!searchInput || !searchResults || !searchWrap) return;

        let searchTimer = null;
        let activeIndex = -1;
        let requestController = null;

        /* SHOW DROPDOWN */

        function showResults() {
            searchResults.classList.add("show");
        }

        /* HIDE DROPDOWN */

        function hideResults() {
            searchResults.classList.remove("show");
            activeIndex = -1;
        }

        /* ESCAPE HTML */

        function escapeHTML(value) {

            return String(value ?? "").replace(/[&<>"']/g, char => ({
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': "&quot;",
                "'": "&#039;"
            })[char]);

        }

        /* EMPTY STATE */

        function showMessage(message, icon = "bi-search") {

            searchResults.innerHTML = `
            <div class="search-empty">
                <i class="bi ${icon}"></i>
                <span>${escapeHTML(message)}</span>
            </div>
        `;

            showResults();

        }

        /* RENDER RESULTS */

        function renderResults(data) {

            const groups = [

                {
                    key: "customers",
                    title: "Customers",
                    icon: "bi-people"
                },

                {
                    key: "orders",
                    title: "Orders",
                    icon: "bi-receipt"
                },

                {
                    key: "products",
                    title: "Products",
                    icon: "bi-box-seam"
                }

            ];

            let html = "";
            let totalResults = 0;

            groups.forEach(group => {

                const items = Array.isArray(data[group.key]) ?
                    data[group.key] :
                    [];

                if (!items.length) return;

                totalResults += items.length;

                html += `
                <div class="search-group-title">
                    ${group.title}
                    <span>${items.length}</span>
                </div>
            `;

                items.forEach(item => {

                    if (!item.url) return;

                    html += `
                    <a
                        href="${escapeHTML(item.url)}"
                        class="search-result-item"
                    >

                        <div class="search-result-icon">
                            <i class="bi ${group.icon}"></i>
                        </div>

                        <div class="search-result-info">

                            <div class="search-result-name">
                                ${escapeHTML(item.name || "Untitled")}
                            </div>

                            <div class="search-result-detail">
                                ${escapeHTML(item.detail || "")}
                            </div>

                        </div>

                        <i class="bi bi-arrow-up-right search-result-arrow"></i>

                    </a>
                `;

                });

            });

            if (!totalResults) {

                showMessage(
                    "No matching customers, orders or products found."
                );

                return;

            }

            searchResults.innerHTML = html;
            activeIndex = -1;

            showResults();

        }

        /* PERFORM AJAX SEARCH */

        async function performSearch(query) {

            if (query.length < 2) {

                showMessage("Type at least 2 characters to search.");

                return;

            }

            if (requestController) {
                requestController.abort();
            }

            requestController = new AbortController();

            searchResults.innerHTML = `
            <div class="search-loading">
                <i class="bi bi-arrow-repeat"></i>
                Searching...
            </div>
        `;

            showResults();

            try {

                const response = await fetch(
                    SEARCH_URL + "?q=" + encodeURIComponent(query), {
                        method: "GET",
                        credentials: "same-origin",
                        signal: requestController.signal,
                        headers: {
                            "X-Requested-With": "XMLHttpRequest"
                        }
                    }
                );

                if (!response.ok) {

                    throw new Error("HTTP " + response.status);

                }

                const data = await response.json();

                if (searchInput.value.trim() !== query) return;

                if (data.success === false) {

                    showMessage(
                        data.message || "Search failed.",
                        "bi-exclamation-circle"
                    );

                    return;

                }

                renderResults(data);

            } catch (error) {

                if (error.name === "AbortError") return;

                console.error("Global search error:", error);

                showMessage(
                    "Unable to search. Please try again.",
                    "bi-exclamation-circle"
                );

            }

        }

        /* INPUT EVENT */

        searchInput.addEventListener("input", function() {

            clearTimeout(searchTimer);

            const query = this.value.trim();

            if (requestController) {
                requestController.abort();
            }

            searchTimer = setTimeout(() => {

                performSearch(query);

            }, 300);

        });

        /* KEYBOARD NAVIGATION */

        searchInput.addEventListener("keydown", function(e) {

            const items = searchResults.querySelectorAll(".search-result-item");

            if (e.key === "ArrowDown" && items.length) {

                e.preventDefault();

                activeIndex = Math.min(
                    activeIndex + 1,
                    items.length - 1
                );

            } else if (e.key === "ArrowUp" && items.length) {

                e.preventDefault();

                activeIndex = Math.max(
                    activeIndex - 1,
                    0
                );

            } else if (e.key === "Enter" && items.length) {

                e.preventDefault();

                const selected = items[
                    activeIndex >= 0 ? activeIndex : 0
                ];

                if (selected) {
                    window.location.href = selected.href;
                }

                return;

            } else if (e.key === "Escape") {

                hideResults();
                searchInput.blur();

                return;

            }

            items.forEach((item, index) => {

                item.classList.toggle(
                    "active",
                    index === activeIndex
                );

            });

            if (activeIndex >= 0 && items[activeIndex]) {

                items[activeIndex].scrollIntoView({
                    block: "nearest"
                });

            }

        });

        /* CLICK OUTSIDE */

        document.addEventListener("click", function(e) {

            if (!searchWrap.contains(e.target)) {
                hideResults();
            }

        });

        /* CTRL + K */

        document.addEventListener("keydown", function(e) {

            if (
                (e.ctrlKey || e.metaKey) &&
                e.key.toLowerCase() === "k"
            ) {

                e.preventDefault();

                searchInput.focus();
                searchInput.select();

            }

        });

    })();


    /* =========================================================
       PROFILE DROPDOWN
    ========================================================= */

    (() => {

        "use strict";

        const profileBtn = document.getElementById("profileMenuBtn");
        const profileWrap = document.querySelector(".profile-dropdown-wrap");

        if (!profileBtn || !profileWrap) return;

        profileBtn.addEventListener("click", function(e) {

            e.stopPropagation();

            profileWrap.classList.toggle("open");

        });

        document.addEventListener("click", function(e) {

            if (!profileWrap.contains(e.target)) {

                profileWrap.classList.remove("open");

            }

        });

    })();
</script>