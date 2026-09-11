<?php
require_once './config/config.php';
require_once './config/function.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <?php include './includes/head.php'; ?>

</head>


<body>


    <!-- =====================================================
     SIDEBAR
===================================================== -->

    <?php include './templates/sidebar.php'; ?>



    <!-- =====================================================
     MAIN
===================================================== -->

    <main class="main">


        <!-- =================================================
         TOPBAR
    ================================================= -->

        <header class="topbar">


            <!-- MOBILE MENU -->

            <button
                class="mobile-menu"
                onclick="toggleSidebar()"
                aria-label="Open menu">

                <i class="bi bi-list"></i>

            </button>



            <!-- SEARCH -->

            <div class="search-box">

                <i class="bi bi-search"></i>

                <input
                    type="text"
                    placeholder="Search orders, products...">

            </div>



            <!-- TOP RIGHT -->

            <div class="top-right">


                <!-- NOTIFICATION -->

                <button class="notification">

                    <i class="bi bi-bell"></i>

                    <span class="notification-dot"></span>

                </button>



                <!-- ADMIN -->

                <div class="admin-profile">

                    <div class="admin-avatar">

                        A

                    </div>


                    <div>

                        <div class="admin-name">

                            Admin

                        </div>

                        <div class="admin-role">

                            Store Manager

                        </div>

                    </div>

                </div>


            </div>

        </header>



        <!-- =================================================
         CONTENT
    ================================================= -->

        <div class="content">


            <!-- PAGE HEADING -->

            <div class="page-heading">

                <h1>
                    Good Morning, Admin 👋
                </h1>

                <p>
                    Here's what's happening with your fresh products today.
                </p>

            </div>



            <!-- =================================================
             HERO
        ================================================= -->

            <section class="fresh-hero">


                <div class="hero-content">


                    <div class="hero-label">

                        <i class="bi bi-leaf-fill"></i>

                        FRESH FROM OUR KITCHEN

                    </div>


                    <h2>

                        Healthy products.<br>

                        Happy customers.

                    </h2>


                    <p>

                        Your store is growing beautifully.
                        Keep your products fresh, your customers happy
                        and your orders moving.

                    </p>



                    <!-- HERO STATS -->

                    <div class="hero-stats">


                        <div class="hero-stat">

                            <strong>
                                ₹48,920
                            </strong>

                            <span>
                                Today's Sales
                            </span>

                        </div>


                        <div class="hero-divider"></div>


                        <div class="hero-stat">

                            <strong>
                                126
                            </strong>

                            <span>
                                Orders Today
                            </span>

                        </div>


                        <div class="hero-divider"></div>


                        <div class="hero-stat">

                            <strong>
                                94%
                            </strong>

                            <span>
                                Fresh Stock
                            </span>

                        </div>


                    </div>

                </div>



                <div class="hero-decoration"></div>


                <div class="hero-decoration-two">
                    🌿
                </div>


            </section>



            <!-- =================================================
             KPI CARDS
        ================================================= -->

            <div class="row g-3 mb-4">


                <!-- REVENUE -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="kpi-card">


                        <div class="kpi-top">

                            <div class="kpi-icon red">

                                <i class="bi bi-currency-rupee"></i>

                            </div>


                            <span class="trend up">

                                <i class="bi bi-arrow-up"></i>

                                12.5%

                            </span>

                        </div>


                        <div class="kpi-label">
                            Total Revenue
                        </div>


                        <div class="kpi-value">
                            ₹4,82,650
                        </div>


                    </div>

                </div>



                <!-- ORDERS -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="kpi-card">


                        <div class="kpi-top">

                            <div class="kpi-icon green">

                                <i class="bi bi-bag-check"></i>

                            </div>


                            <span class="trend up">

                                <i class="bi bi-arrow-up"></i>

                                8.4%

                            </span>

                        </div>


                        <div class="kpi-label">
                            Total Orders
                        </div>


                        <div class="kpi-value">
                            1,284
                        </div>


                    </div>

                </div>



                <!-- PRODUCTS -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="kpi-card">


                        <div class="kpi-top">

                            <div class="kpi-icon gold">

                                <i class="bi bi-box-seam"></i>

                            </div>


                            <span class="trend up">

                                <i class="bi bi-arrow-up"></i>

                                4.8%

                            </span>

                        </div>


                        <div class="kpi-label">
                            Products
                        </div>


                        <div class="kpi-value">
                            86
                        </div>


                    </div>

                </div>



                <!-- CUSTOMERS -->

                <div class="col-12 col-sm-6 col-xl-3">

                    <div class="kpi-card">


                        <div class="kpi-top">

                            <div class="kpi-icon brown">

                                <i class="bi bi-people"></i>

                            </div>


                            <span class="trend up">

                                <i class="bi bi-arrow-up"></i>

                                15.2%

                            </span>

                        </div>


                        <div class="kpi-label">
                            Customers
                        </div>


                        <div class="kpi-value">
                            3,642
                        </div>


                    </div>

                </div>


            </div>



            <!-- =================================================
             REVENUE + ORDER STATUS
        ================================================= -->

            <div class="row g-3 mb-4">


                <!-- REVENUE -->

                <div class="col-12 col-xl-8">

                    <div class="section-card">


                        <div class="section-title">


                            <div>

                                <h3>
                                    Revenue Overview
                                </h3>

                                <span>
                                    Monthly sales performance
                                </span>

                            </div>


                            <select
                                class="form-select form-select-sm"
                                style="width:100px;font-size:10px;">

                                <option>
                                    2026
                                </option>

                                <option>
                                    2025
                                </option>

                            </select>


                        </div>



                        <div class="chart-area">


                            <div class="chart-grid">

                                <div class="chart-grid-line"></div>

                                <div class="chart-grid-line"></div>

                                <div class="chart-grid-line"></div>

                                <div class="chart-grid-line"></div>

                                <div class="chart-grid-line"></div>

                            </div>



                            <div class="bar-wrap">

                                <div
                                    class="bar"
                                    style="height:42%;"></div>

                                <div class="bar-label">
                                    Jan
                                </div>

                            </div>


                            <div class="bar-wrap">

                                <div
                                    class="bar"
                                    style="height:57%;"></div>

                                <div class="bar-label">
                                    Feb
                                </div>

                            </div>


                            <div class="bar-wrap">

                                <div
                                    class="bar"
                                    style="height:48%;"></div>

                                <div class="bar-label">
                                    Mar
                                </div>

                            </div>


                            <div class="bar-wrap">

                                <div
                                    class="bar"
                                    style="height:69%;"></div>

                                <div class="bar-label">
                                    Apr
                                </div>

                            </div>


                            <div class="bar-wrap">

                                <div
                                    class="bar"
                                    style="height:62%;"></div>

                                <div class="bar-label">
                                    May
                                </div>

                            </div>


                            <div class="bar-wrap">

                                <div
                                    class="bar"
                                    style="height:78%;"></div>

                                <div class="bar-label">
                                    Jun
                                </div>

                            </div>


                            <div class="bar-wrap">

                                <div
                                    class="bar"
                                    style="height:71%;"></div>

                                <div class="bar-label">
                                    Jul
                                </div>

                            </div>


                            <div class="bar-wrap">

                                <div
                                    class="bar"
                                    style="height:88%;"></div>

                                <div class="bar-label">
                                    Aug
                                </div>

                            </div>


                            <div class="bar-wrap">

                                <div
                                    class="bar"
                                    style="height:94%;"></div>

                                <div class="bar-label">
                                    Sep
                                </div>

                            </div>


                        </div>

                    </div>

                </div>



                <!-- ORDER STATUS -->

                <div class="col-12 col-xl-4">

                    <div class="section-card">


                        <div class="section-title">


                            <div>

                                <h3>
                                    Order Status
                                </h3>

                                <span>
                                    Today's orders
                                </span>

                            </div>


                            <a href="#" class="view-link">
                                View All
                            </a>


                        </div>



                        <div class="status-layout">


                            <div class="donut">


                                <div class="donut-center">

                                    <strong>
                                        126
                                    </strong>

                                    <span>
                                        Total Orders
                                    </span>

                                </div>


                            </div>



                            <div class="status-list">


                                <div class="status-row">

                                    <span class="status-dot green"></span>

                                    Delivered

                                    <strong>
                                        72
                                    </strong>

                                </div>


                                <div class="status-row">

                                    <span class="status-dot red"></span>

                                    Processing

                                    <strong>
                                        26
                                    </strong>

                                </div>


                                <div class="status-row">

                                    <span class="status-dot gold"></span>

                                    Pending

                                    <strong>
                                        18
                                    </strong>

                                </div>


                                <div class="status-row">

                                    <span class="status-dot gray"></span>

                                    Cancelled

                                    <strong>
                                        10
                                    </strong>

                                </div>


                            </div>


                        </div>


                    </div>

                </div>


            </div>



            <!-- =================================================
             STOCK + BEST SELLERS
        ================================================= -->

            <div class="row g-3 mb-4">


                <!-- STOCK -->

                <div class="col-12 col-xl-6">

                    <div class="section-card">


                        <div class="section-title">


                            <div>

                                <h3>
                                    Freshness & Stock
                                </h3>

                                <span>
                                    Product inventory health
                                </span>

                            </div>


                            <a href="#" class="view-link">
                                Manage Stock
                            </a>


                        </div>



                        <!-- PRODUCT 1 -->

                        <div class="product-stock">


                            <div class="product-image">
                                🌾
                            </div>


                            <div class="product-info">

                                <div class="product-name">
                                    Premium Millet Mix
                                </div>

                                <div class="product-meta">
                                    124 packs available
                                </div>

                            </div>


                            <div class="stock-status">

                                <span class="stock-badge fresh">
                                    FRESH
                                </span>

                            </div>


                        </div>



                        <!-- PRODUCT 2 -->

                        <div class="product-stock">


                            <div class="product-image">
                                🫘
                            </div>


                            <div class="product-info">

                                <div class="product-name">
                                    Ragi Health Mix
                                </div>

                                <div class="product-meta">
                                    86 packs available
                                </div>

                            </div>


                            <div class="stock-status">

                                <span class="stock-badge fresh">
                                    FRESH
                                </span>

                            </div>


                        </div>



                        <!-- PRODUCT 3 -->

                        <div class="product-stock">


                            <div class="product-image">
                                🌱
                            </div>


                            <div class="product-info">

                                <div class="product-name">
                                    Multi Millet Flour
                                </div>

                                <div class="product-meta">
                                    38 packs available
                                </div>

                            </div>


                            <div class="stock-status">

                                <span class="stock-badge low">
                                    LOW STOCK
                                </span>

                            </div>


                        </div>



                        <!-- PRODUCT 4 -->

                        <div class="product-stock">


                            <div class="product-image">
                                🌾
                            </div>


                            <div class="product-info">

                                <div class="product-name">
                                    Kambu Flour
                                </div>

                                <div class="product-meta">
                                    12 packs available
                                </div>

                            </div>


                            <div class="stock-status">

                                <span class="stock-badge critical">
                                    RESTOCK
                                </span>

                            </div>


                        </div>


                    </div>

                </div>



                <!-- BEST SELLERS -->

                <div class="col-12 col-xl-6">

                    <div class="section-card">


                        <div class="section-title">


                            <div>

                                <h3>
                                    Best Selling Products
                                </h3>

                                <span>
                                    Top performing products
                                </span>

                            </div>


                            <a href="#" class="view-link">
                                View Products
                            </a>


                        </div>



                        <!-- BEST 1 -->

                        <div class="best-product">


                            <div class="rank">
                                01
                            </div>


                            <div class="best-image">
                                🌾
                            </div>


                            <div class="best-info">

                                <strong>
                                    Premium Millet Mix
                                </strong>

                                <span>
                                    428 orders
                                </span>

                            </div>


                            <div class="best-sales">
                                ₹86,400
                            </div>


                        </div>



                        <!-- BEST 2 -->

                        <div class="best-product">


                            <div class="rank">
                                02
                            </div>


                            <div class="best-image">
                                🫘
                            </div>


                            <div class="best-info">

                                <strong>
                                    Ragi Health Mix
                                </strong>

                                <span>
                                    364 orders
                                </span>

                            </div>


                            <div class="best-sales">
                                ₹68,240
                            </div>


                        </div>



                        <!-- BEST 3 -->

                        <div class="best-product">


                            <div class="rank">
                                03
                            </div>


                            <div class="best-image">
                                🌱
                            </div>


                            <div class="best-info">

                                <strong>
                                    Multi Millet Flour
                                </strong>

                                <span>
                                    286 orders
                                </span>

                            </div>


                            <div class="best-sales">
                                ₹52,680
                            </div>


                        </div>



                        <!-- BEST 4 -->

                        <div class="best-product">


                            <div class="rank">
                                04
                            </div>


                            <div class="best-image">
                                🌾
                            </div>


                            <div class="best-info">

                                <strong>
                                    Kambu Flour
                                </strong>

                                <span>
                                    198 orders
                                </span>

                            </div>


                            <div class="best-sales">
                                ₹38,920
                            </div>


                        </div>


                    </div>

                </div>


            </div>



            <!-- =================================================
             RECENT ORDERS + QUICK ACTIONS
        ================================================= -->

            <div class="row g-3">


                <!-- RECENT ORDERS -->

                <div class="col-12 col-xl-9">

                    <div class="section-card">


                        <div class="section-title">


                            <div>

                                <h3>
                                    Recent Orders
                                </h3>

                                <span>
                                    Latest customer purchases
                                </span>

                            </div>


                            <a href="#" class="view-link">
                                View All Orders
                            </a>


                        </div>



                        <div class="table-responsive">


                            <table class="orders-table">


                                <thead>

                                    <tr>

                                        <th>
                                            Customer
                                        </th>

                                        <th>
                                            Order ID
                                        </th>

                                        <th>
                                            Product
                                        </th>

                                        <th>
                                            Amount
                                        </th>

                                        <th>
                                            Date
                                        </th>

                                        <th>
                                            Status
                                        </th>

                                    </tr>

                                </thead>



                                <tbody>


                                    <!-- ORDER 1 -->

                                    <tr>


                                        <td>

                                            <div class="customer">


                                                <div class="customer-avatar">
                                                    RK
                                                </div>


                                                <div>

                                                    <div class="customer-name">
                                                        Ramesh Kumar
                                                    </div>

                                                    <div class="customer-phone">
                                                        +91 98XXXXXX21
                                                    </div>

                                                </div>


                                            </div>

                                        </td>


                                        <td>
                                            #MM10284
                                        </td>


                                        <td>
                                            Millet Mix × 2
                                        </td>


                                        <td>
                                            <strong>
                                                ₹1,240
                                            </strong>
                                        </td>


                                        <td>
                                            11 Sep 2026
                                        </td>


                                        <td>

                                            <span class="order-status status-completed">
                                                Delivered
                                            </span>

                                        </td>


                                    </tr>



                                    <!-- ORDER 2 -->

                                    <tr>


                                        <td>

                                            <div class="customer">


                                                <div class="customer-avatar">
                                                    PS
                                                </div>


                                                <div>

                                                    <div class="customer-name">
                                                        Priya S
                                                    </div>

                                                    <div class="customer-phone">
                                                        +91 94XXXXXX65
                                                    </div>

                                                </div>


                                            </div>

                                        </td>


                                        <td>
                                            #MM10283
                                        </td>


                                        <td>
                                            Ragi Mix × 3
                                        </td>


                                        <td>
                                            <strong>
                                                ₹890
                                            </strong>
                                        </td>


                                        <td>
                                            11 Sep 2026
                                        </td>


                                        <td>

                                            <span class="order-status status-processing">
                                                Processing
                                            </span>

                                        </td>


                                    </tr>



                                    <!-- ORDER 3 -->

                                    <tr>


                                        <td>

                                            <div class="customer">


                                                <div class="customer-avatar">
                                                    AM
                                                </div>


                                                <div>

                                                    <div class="customer-name">
                                                        Arun M
                                                    </div>

                                                    <div class="customer-phone">
                                                        +91 88XXXXXX43
                                                    </div>

                                                </div>


                                            </div>

                                        </td>


                                        <td>
                                            #MM10282
                                        </td>


                                        <td>
                                            Kambu Flour × 1
                                        </td>


                                        <td>
                                            <strong>
                                                ₹450
                                            </strong>
                                        </td>


                                        <td>
                                            11 Sep 2026
                                        </td>


                                        <td>

                                            <span class="order-status status-pending">
                                                Pending
                                            </span>

                                        </td>


                                    </tr>



                                    <!-- ORDER 4 -->

                                    <tr>


                                        <td>

                                            <div class="customer">


                                                <div class="customer-avatar">
                                                    SV
                                                </div>


                                                <div>

                                                    <div class="customer-name">
                                                        Siva V
                                                    </div>

                                                    <div class="customer-phone">
                                                        +91 90XXXXXX18
                                                    </div>

                                                </div>


                                            </div>

                                        </td>


                                        <td>
                                            #MM10281
                                        </td>


                                        <td>
                                            Multi Millet × 2
                                        </td>


                                        <td>
                                            <strong>
                                                ₹760
                                            </strong>
                                        </td>


                                        <td>
                                            10 Sep 2026
                                        </td>


                                        <td>

                                            <span class="order-status status-completed">
                                                Delivered
                                            </span>

                                        </td>


                                    </tr>


                                </tbody>


                            </table>


                        </div>


                    </div>

                </div>



                <!-- QUICK ACTIONS -->

                <div class="col-12 col-xl-3">

                    <div class="section-card">


                        <div class="section-title">

                            <div>

                                <h3>
                                    Quick Actions
                                </h3>

                                <span>
                                    Manage store
                                </span>

                            </div>

                        </div>



                        <a href="#" class="quick-action">


                            <div class="quick-icon">

                                <i class="bi bi-plus-lg"></i>

                            </div>


                            <div>

                                <strong>
                                    Add Product
                                </strong>

                                <span>
                                    Create new product
                                </span>

                            </div>


                        </a>



                        <a href="#" class="quick-action">


                            <div class="quick-icon">

                                <i class="bi bi-bag-plus"></i>

                            </div>


                            <div>

                                <strong>
                                    New Order
                                </strong>

                                <span>
                                    Create manual order
                                </span>

                            </div>


                        </a>



                        <a href="#" class="quick-action">


                            <div class="quick-icon">

                                <i class="bi bi-tag"></i>

                            </div>


                            <div>

                                <strong>
                                    Create Offer
                                </strong>

                                <span>
                                    Promote your products
                                </span>

                            </div>


                        </a>



                        <a href="#" class="quick-action">


                            <div class="quick-icon">

                                <i class="bi bi-bar-chart-line"></i>

                            </div>


                            <div>

                                <strong>
                                    View Reports
                                </strong>

                                <span>
                                    Check sales performance
                                </span>

                            </div>


                        </a>



                        <a href="#" class="quick-action">


                            <div class="quick-icon">

                                <i class="bi bi-gear"></i>

                            </div>


                            <div>

                                <strong>
                                    Store Settings
                                </strong>

                                <span>
                                    Manage your store
                                </span>

                            </div>


                        </a>


                    </div>

                </div>


            </div>


        </div>

    </main>
    


    <!-- =====================================================
     JAVASCRIPT
===================================================== -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= ADMIN_URL; ?>js/main.js"></script>


</body>

</html>