<?php
/* Pull logo from settings table (id = 1). */
$sidebarLogo = getData('logo_image', 'settings', 'id = 1');

$sidebarLogoUrl = $sidebarLogo
    ? ADMIN_URL . $sidebarLogo
    : '';

/* Check if current user is admin */
$isAdminUser = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin');
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

        <a href="orders.php">
            <i class="bi bi-bag"></i>
            Orders
        </a>

        <a href="pickup-orders.php">
            <i class="bi bi-shop"></i>
            Pickup Orders
        </a>

        <a href="products.php">
            <i class="bi bi-box-seam"></i>
            Products
        </a>

        <a href="category.php">
            <i class="bi bi-tags"></i>
            Categories
        </a>

        <a href="customers.php">
            <i class="bi bi-people"></i>
            Customers
        </a>

        <a href="containers.php">
            <i class="bi bi-boxes"></i>
            Containers
        </a>

        <?php if ($isAdminUser): ?>
            <a href="reports.php">
                <i class="bi bi-bar-chart"></i>
                Reports
            </a>
        <?php endif; ?>

        <a href="manual-order-taken.php">
            <i class="bi bi-cart-plus"></i>
            Manual Order Taken
        </a>

        <a href="urgency-assign-orders.php">
            <i class="bi bi-alarm-fill"></i>
            Urgency Swap Orders
        </a>
        
        <?php if ($isAdminUser): ?>
            <a href="address-requests-list.php">
                <i class="bi bi-building-add"></i>
                Apartment Requests
            </a>
        <?php endif; ?>


        <div class="menu-title px-2 pt-4">
            Management
        </div>

        <a href="apartment.php">
            <i class="bi bi-building"></i>
            Apartment
        </a>

        <a href="delivery-boys.php">
            <i class="bi bi-person-walking"></i>
            Delivery Boys
        </a>

        <a href="order-allocations.php">
            <i class="bi bi-person-check me-1"></i>
            Apartment Allocate
        </a>

        <?php if ($isAdminUser): ?>
            <a href="discounts.php">
                <i class="bi bi-percent"></i>
                Discounts
            </a>
        <?php endif; ?>


        <?php if ($isAdminUser): ?>
            <a href="payment-settings.php">
                <i class="bi bi-credit-card"></i>
                Payments
            </a>

            <a href="staff.php">
                <i class="bi bi-person-vcard"></i>
                Staff
            </a>

            <a href="settings.php">
                <i class="bi bi-gear"></i>
                Settings
            </a>
        <?php endif; ?>

    </nav>

</aside>