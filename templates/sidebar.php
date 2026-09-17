<?php
/* Pull logo from settings table (id = 1). */
$sidebarLogo = getData('logo_image', 'settings', 'id = 1');

$sidebarLogoUrl = $sidebarLogo
    ? ADMIN_URL . $sidebarLogo
    : '';
?>
<aside class="sidebar" id="sidebar">

    <!-- LOGO AREA -->

    <div class="brand-area">

        <img
            src="<?= htmlspecialchars($sidebarLogoUrl) ?>"
            class="brand-logo"
            alt="Logo"
            onerror="this.style.display='none';">

        <!-- MOBILE CLOSE -->

        <button
            type="button"
            class="sidebar-close"
            onclick="toggleSidebar()"
            aria-label="Close menu">

            <i class="bi bi-x-lg"></i>

        </button>

    </div>


    <!-- MAIN MENU -->

    <div class="menu-title">
        Main Options
    </div>


    <nav class="sidebar-menu">

        <a href="index.php" class="active">
            <i class="bi bi-grid-1x2-fill"></i>
            Dashboard
        </a>

        <a href="menu.php">
            <i class="bi bi-list-ul"></i>
            Menu
        </a>

        <a href="#">
            <i class="bi bi-bag"></i>
            Orders
        </a>

        <a href="products.php">
            <i class="bi bi-box-seam"></i>
            Products
        </a>

        <a href="category.php">
            <i class="bi bi-tags"></i>
            Categories
        </a>

        <a href="#">
            <i class="bi bi-people"></i>
            Customers
        </a>

        <a href="#">
            <i class="bi bi-bar-chart"></i>
            Reports
        </a>

        <div class="menu-title px-2 pt-4">
            Management
        </div>

        <a href="apartment.php">
            <i class="bi bi-building"></i>
            Apartment
        </a>

        <a href="discounts.php">
            <i class="bi bi-percent"></i>
            Discounts
        </a>

        <a href="#">
            <i class="bi bi-truck"></i>
            Delivery
        </a>

        <a href="payment-settings.php">
            <i class="bi bi-credit-card"></i>
            Payments
        </a>

        <a href="settings.php">
            <i class="bi bi-gear"></i>
            Settings
        </a>

    </nav>

</aside>