<?php
/* ==================================
   SIDEBAR CONFIGURATION
================================== */

$sidebarLogo = getData('logo_image', 'settings', 'id = 1');

$sidebarLogoUrl = !empty($sidebarLogo)
    ? ADMIN_URL . $sidebarLogo
    : '';

$isAdminUser = (
    isset($_SESSION['admin_role']) &&
    $_SESSION['admin_role'] === 'admin'
);

$currentPage = basename(
    parse_url($_SERVER['PHP_SELF'] ?? '', PHP_URL_PATH)
);

/* ==================================
   SIDEBAR LINK HELPER
================================== */

$sidebarLink = function ($file, $label, $icon) use ($currentPage) {
    $active = ($currentPage === $file);

    return '
        <a href="' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '"
           class="sidebar-link' . ($active ? ' active' : '') . '"
           ' . ($active ? 'aria-current="page"' : '') . '>
            <i class="bi ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '"></i>
            <span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>
        </a>';
};

/* ==================================
   DROPDOWN GROUP HELPER
================================== */

$sidebarGroup = function (
    $id,
    $title,
    $icon,
    $pages,
    $links
) use ($currentPage) {

    $isGroupActive = in_array($currentPage, $pages, true);

    return '
        <div class="sidebar-group ' . ($isGroupActive ? 'is-open' : '') . '">

            <button
                type="button"
                class="sidebar-group-toggle ' . ($isGroupActive ? 'active' : '') . '"
                aria-expanded="' . ($isGroupActive ? 'true' : 'false') . '"
                aria-controls="submenu-' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">

                <span class="group-label">
                    <i class="bi ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '"></i>
                    <span>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span>
                </span>

                <i class="bi bi-chevron-down group-arrow"></i>
            </button>

            <div
                class="sidebar-submenu"
                id="submenu-' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"
                aria-hidden="' . ($isGroupActive ? 'false' : 'true') . '">

                <div class="submenu-inner">
                    ' . $links . '
                </div>
            </div>

        </div>';
};
?>

<style>
    /* ==================================
   SIDEBAR STYLING
================================== */

    .sidebar {
        --sidebar-red: #e31e24;
        --sidebar-red-dark: #c8171d;
        --sidebar-text: #4b5563;
        --sidebar-muted: #9299a5;

        position: fixed;
        top: 0;
        left: 0;
        z-index: 1050;

        display: flex;
        flex-direction: column;

        width: 260px;
        height: 100vh;
        height: 100dvh;

        background: #fff;
        border-right: 1px solid #f0f0f2;
        box-shadow: 5px 0 24px rgba(20, 20, 30, .035);

        overflow: hidden;
        transition: transform .3s ease;
    }

    .sidebar * {
        box-sizing: border-box;
    }

    .brand-area {
        min-height: 78px;
        padding: 18px 20px;

        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;

        flex-shrink: 0;
        border-bottom: 1px solid #f5f5f6;
    }

    .brand-logo {
        display: block;
        max-width: 175px;
        max-height: 44px;
        width: auto;
        height: auto;
        object-fit: contain;
        object-position: left center;
    }

    .sidebar-close {
        display: none;
        align-items: center;
        justify-content: center;

        width: 34px;
        height: 34px;

        border: 0;
        border-radius: 8px;
        background: #f7f7f8;
        color: #333;
        cursor: pointer;
    }

    .menu-title {
        flex-shrink: 0;
        padding: 19px 20px 9px;

        color: var(--sidebar-muted);
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 1.2px;
        text-transform: uppercase;
    }

    .sidebar-menu {
        flex: 1;
        min-height: 0;
        padding: 0 12px 24px;

        overflow-y: auto;
        overflow-x: hidden;
        scrollbar-width: thin;
        scrollbar-color: #e5e7eb transparent;
    }

    .sidebar-menu::-webkit-scrollbar {
        width: 5px;
    }

    .sidebar-menu::-webkit-scrollbar-thumb {
        background: #e5e7eb;
        border-radius: 20px;
    }

    /* Regular links and dropdown buttons */
    .sidebar-link,
    .sidebar-group-toggle {
        display: flex;
        align-items: center;
        gap: 12px;

        width: 100%;
        min-height: 43px;
        padding: 10px 12px;

        border: 0;
        border-radius: 9px;
        background: transparent;

        color: var(--sidebar-text);
        font-family: inherit;
        font-size: 13px;
        font-weight: 500;
        line-height: 1.4;
        text-align: left;
        text-decoration: none;

        cursor: pointer;

        transition:
            color .2s ease,
            background .2s ease;
    }

    .sidebar-link>i,
    .group-label>i {
        width: 19px;
        min-width: 19px;
        font-size: 17px;
        text-align: center;
    }

    .sidebar-link>span,
    .group-label>span {
        flex: 1;
        min-width: 0;
    }

    .sidebar-link:hover,
    .sidebar-group-toggle:hover {
        color: var(--sidebar-red);
        background: #fff1f1;
    }

    /* Red active page */
    .sidebar-link.active {
        color: #fff;
        background: var(--sidebar-red);
        font-weight: 700;
        box-shadow: 0 4px 10px rgba(227, 30, 36, .17);
    }

    .sidebar-link.active>i {
        color: #fff;
    }

    /* Dropdown groups */
    .sidebar-group {
        margin-bottom: 3px;
    }

    .sidebar-group-toggle {
        justify-content: space-between;
        font-weight: 600;
    }

    .group-label {
        display: flex;
        align-items: center;
        flex: 1;
        min-width: 0;
        gap: 12px;
    }

    .group-arrow {
        color: #9ca3af;
        font-size: 12px;
        transition: transform .28s ease, color .2s ease;
    }

    .sidebar-group.is-open>.sidebar-group-toggle {
        color: var(--sidebar-red);
    }

    .sidebar-group.is-open>.sidebar-group-toggle .group-arrow {
        color: var(--sidebar-red);
        transform: rotate(180deg);
    }

    /* Smooth expand and collapse */
    .sidebar-submenu {
        display: grid;
        grid-template-rows: 0fr;
        opacity: 0;
        visibility: hidden;

        transition:
            grid-template-rows .28s ease,
            opacity .22s ease,
            visibility .28s ease;
    }

    .sidebar-group.is-open>.sidebar-submenu {
        grid-template-rows: 1fr;
        opacity: 1;
        visibility: visible;
    }

    .submenu-inner {
        min-height: 0;
        padding-left: 15px;
        margin-left: 20px;
        overflow: hidden;
        border-left: 1px solid #ededf0;
    }

    .sidebar-group.is-open .submenu-inner {
        padding-top: 5px;
        padding-bottom: 5px;
    }

    .sidebar-submenu .sidebar-link {
        min-height: 39px;
        padding: 9px 10px;
        gap: 10px;
        border-radius: 8px;
        font-size: 12.5px;
    }

    .sidebar-submenu .sidebar-link>i {
        font-size: 15px;
    }

    /* Keyboard accessibility */
    .sidebar-link:focus-visible,
    .sidebar-group-toggle:focus-visible,
    .sidebar-close:focus-visible {
        outline: 2px solid var(--sidebar-red);
        outline-offset: 2px;
    }

    /* Mobile sidebar */
    .sidebar-overlay {
        display: none;
    }

    @media (max-width: 991.98px) {
        .sidebar {
            width: 280px;
            max-width: 86vw;
            transform: translateX(-105%);
        }

        .sidebar.sidebar-open {
            transform: translateX(0);
        }

        .sidebar-close {
            display: flex;
            flex-shrink: 0;
        }

        .sidebar-overlay {
            position: fixed;
            inset: 0;
            z-index: 1040;

            display: block;
            background: rgba(15, 23, 42, .42);

            opacity: 0;
            visibility: hidden;
            pointer-events: none;

            transition: opacity .3s ease, visibility .3s ease;
        }

        .sidebar-overlay.visible {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }
    }

    @media (prefers-reduced-motion: reduce) {

        .sidebar,
        .sidebar *,
        .sidebar-overlay {
            transition-duration: .01ms !important;
        }
    }
</style>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">

    <!-- ==================================
         BRAND / LOGO
    ================================== -->

    <div class="brand-area">

        <?php if (!empty($sidebarLogoUrl)): ?>

            <img
                src="<?= htmlspecialchars($sidebarLogoUrl, ENT_QUOTES, 'UTF-8') ?>"
                class="brand-logo"
                alt="Logo"
                onerror="this.style.display='none';">

        <?php else: ?>

            <span style="font-size:20px;font-weight:800;color:#e31e24;">
                Admin Panel
            </span>

        <?php endif; ?>

        <button
            type="button"
            class="sidebar-close"
            onclick="toggleSidebar(false)"
            aria-label="Close menu">

            <i class="bi bi-x-lg"></i>

        </button>

    </div>


    <div class="menu-title">Main Menu</div>

    <nav class="sidebar-menu" aria-label="Admin navigation">

        <!-- ==================================
             DASHBOARD
        ================================== -->

        <?= $sidebarLink(
            'index.php',
            'Dashboard',
            'bi-grid-1x2-fill'
        ); ?>


        <!-- ==================================
             SALES & ORDERS
        ================================== -->

        <?php

        $orderPages = [
            'orders.php',
            'pickup-orders.php',
            'manual-order-taken.php'
        ];

        $orderLinks =
            $sidebarLink(
                'orders.php',
                'All Orders',
                'bi-bag'
            )
            . $sidebarLink(
                'pickup-orders.php',
                'Pickup Orders',
                'bi-shop'
            )
            . $sidebarLink(
                'manual-order-taken.php',
                'Manual Order Taken',
                'bi-cart-plus'
            );

        echo $sidebarGroup(
            'orders',
            'Sales & Orders',
            'bi-receipt',
            $orderPages,
            $orderLinks
        );

        ?>


        <!-- ==================================
             PRODUCT CATALOGUE
        ================================== -->

        <?php

        $cataloguePages = [
            'menu.php',
            'products.php',
            'category.php',
            'containers.php'
        ];

        $catalogueLinks =
            $sidebarLink(
                'menu.php',
                'Menu',
                'bi-list-ul'
            )
            . $sidebarLink(
                'products.php',
                'Products',
                'bi-box-seam'
            )
            . $sidebarLink(
                'category.php',
                'Categories',
                'bi-tags'
            )
            . $sidebarLink(
                'containers.php',
                'Containers',
                'bi-boxes'
            );

        echo $sidebarGroup(
            'catalogue',
            'Catalogue',
            'bi-grid',
            $cataloguePages,
            $catalogueLinks
        );

        ?>


        <!-- ==================================
             CUSTOMERS
        ================================== -->

        <?php

        $customerPages = [
            'customers.php'
        ];

        $customerLinks =
            $sidebarLink(
                'customers.php',
                'Customers',
                'bi-people'
            );

        echo $sidebarGroup(
            'customers',
            'Customers',
            'bi-people',
            $customerPages,
            $customerLinks
        );

        ?>


        <!-- ==================================
             APARTMENTS & DELIVERY
        ================================== -->

        <?php

        $deliveryPages = [
            'apartment.php',
            'delivery-boys.php',
            'order-allocations.php',
            'urgency-assign-orders.php',
            'address-requests-list.php'
        ];

        $deliveryLinks =
            $sidebarLink(
                'apartment.php',
                'Apartment',
                'bi-building'
            )
            . $sidebarLink(
                'delivery-boys.php',
                'Delivery Boys',
                'bi-person-walking'
            )
            . $sidebarLink(
                'order-allocations.php',
                'Apartment Allocate',
                'bi-person-check'
            )
            . $sidebarLink(
                'urgency-assign-orders.php',
                'Urgency Swap Orders',
                'bi-alarm-fill'
            );

        if ($isAdminUser) {

            $deliveryLinks .= $sidebarLink(
                'address-requests-list.php',
                'Apartment Requests',
                'bi-building-add'
            );
        }

        echo $sidebarGroup(
            'delivery',
            'Apartments & Delivery',
            'bi-truck',
            $deliveryPages,
            $deliveryLinks
        );

        ?>


        <!-- ==================================
             REPORTS
        ================================== -->

        <?php if ($isAdminUser): ?>

            <?= $sidebarLink(
                'reports.php',
                'Reports',
                'bi-bar-chart'
            ); ?>

        <?php endif; ?>




        <!-- ==================================
             ADMIN MANAGEMENT
        ================================== -->

        <?php if ($isAdminUser): ?>

            <?php
            $managementPages = [
                'discounts.php',
                'payment-settings.php',
                'staff.php',
                'settings.php',
                'website-settings.php'
            ];

            $managementLinks =
                $sidebarLink(
                    'discounts.php',
                    'Discounts',
                    'bi-percent'
                )
                . $sidebarLink(
                    'payment-settings.php',
                    'Payments',
                    'bi-credit-card'
                )
                . $sidebarLink(
                    'staff.php',
                    'Staff',
                    'bi-person-vcard'
                )
                . $sidebarLink(
                    'website-settings.php',
                    'Website Settings',
                    'bi-globe'
                )
                . $sidebarLink(
                    'settings.php',
                    'Settings',
                    'bi-gear'
                );

            echo $sidebarGroup(
                'management',
                'Management',
                'bi-sliders',
                $managementPages,
                $managementLinks
            );
            ?>

        <?php endif; ?>


    </nav>

</aside>


<script>
    (function() {
        'use strict';

        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');

        if (!sidebar || !overlay) return;

        /* ==================================
           DROPDOWN ACCORDION
        ================================== */

        const groups = sidebar.querySelectorAll('.sidebar-group');

        groups.forEach(function(group) {

            const button = group.querySelector('.sidebar-group-toggle');
            const submenu = group.querySelector('.sidebar-submenu');

            if (!button || !submenu) return;

            button.addEventListener('click', function() {

                const shouldOpen = !group.classList.contains('is-open');

                /* Close other dropdowns */
                groups.forEach(function(otherGroup) {

                    if (otherGroup === group) return;

                    otherGroup.classList.remove('is-open');

                    const otherButton =
                        otherGroup.querySelector('.sidebar-group-toggle');

                    const otherSubmenu =
                        otherGroup.querySelector('.sidebar-submenu');

                    if (otherButton) {
                        otherButton.setAttribute(
                            'aria-expanded',
                            'false'
                        );
                    }

                    if (otherSubmenu) {
                        otherSubmenu.setAttribute(
                            'aria-hidden',
                            'true'
                        );
                    }
                });

                /* Toggle selected dropdown */
                group.classList.toggle('is-open', shouldOpen);

                button.setAttribute(
                    'aria-expanded',
                    String(shouldOpen)
                );

                submenu.setAttribute(
                    'aria-hidden',
                    String(!shouldOpen)
                );

            });

        });


        /* ==================================
           MOBILE SIDEBAR OPEN / CLOSE
        ================================== */

        window.toggleSidebar = function(forceOpen) {

            const isMobile = window.matchMedia(
                '(max-width: 991.98px)'
            ).matches;

            if (!isMobile) return;

            const shouldOpen = typeof forceOpen === 'boolean' ?
                forceOpen :
                !sidebar.classList.contains('sidebar-open');

            sidebar.classList.toggle(
                'sidebar-open',
                shouldOpen
            );

            overlay.classList.toggle(
                'visible',
                shouldOpen
            );

            document.body.style.overflow =
                shouldOpen ? 'hidden' : '';

        };


        /* Close when clicking outside */
        overlay.addEventListener('click', function() {
            window.toggleSidebar(false);
        });


        /* Close using Escape key */
        document.addEventListener('keydown', function(event) {

            if (event.key === 'Escape') {
                window.toggleSidebar(false);
            }

        });


        /* Close mobile menu after navigation */
        sidebar.querySelectorAll('.sidebar-link').forEach(function(link) {

            link.addEventListener('click', function() {
                window.toggleSidebar(false);
            });

        });


        /* Reset overlay after resizing to desktop */
        window.addEventListener('resize', function() {

            if (window.innerWidth > 991.98) {

                sidebar.classList.remove('sidebar-open');
                overlay.classList.remove('visible');
                document.body.style.overflow = '';

            }

        });

    })();
</script>