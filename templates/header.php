<?php /* ---------------- ROLE ---------------- */
$isAdmin = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin');?>        
        <header class="topbar">
            <button class="mobile-menu" onclick="toggleSidebar()" aria-label="Open menu">
                <i class="bi bi-list"></i>
            </button>

            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" placeholder="Search orders, products...">
            </div>

            <div class="top-right">
                <button class="notification">
                    <i class="bi bi-bell"></i>
                    <span class="notification-dot"></span>
                </button>

                <!-- PROFILE DROPDOWN WRAP -->
                <div class="profile-dropdown-wrap">
                    <div class="admin-profile" id="profileMenuBtn">
                        <div class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)) ?></div>
                        <div>
                            <div class="admin-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'User') ?></div>
                            <div class="admin-role"><?= $isAdmin ? 'Admin' : 'Staff' ?></div>
                        </div>
                        <i class="bi bi-chevron-down" style="font-size: 10px; margin-left: 4px; color: #948c82;"></i>
                    </div>

                    <!-- DROPDOWN MENU -->
                    <div class="profile-menu" id="profileMenu">
                        <a href="<?= ADMIN_URL ?>settings.php" class="profile-menu-item">
                            <i class="bi bi-person"></i> My Profile
                        </a>
                        <div class="profile-menu-divider"></div>
                        <a href="<?= ADMIN_URL ?>logout.php" class="profile-menu-item logout">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>
        <style>
            /* Profile Dropdown */
            .profile-dropdown-wrap {
                position: relative;
            }

            .admin-profile {
                cursor: pointer;
                user-select: none;
                transition: background 0.2s;
                padding: 5px 10px;
                border-radius: 10px;
            }

            .admin-profile:hover {
                background: #faf7f0;
            }

            .profile-menu {
                position: absolute;
                top: calc(100% + 8px);
                right: 0;
                background: #fff;
                border: 1px solid #eee7dc;
                border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                min-width: 180px;
                opacity: 0;
                visibility: hidden;
                transform: translateY(-8px);
                transition: all 0.2s ease;
                z-index: 100;
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
                transition: background 0.15s ease;
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
        </style>
        <script>
            // Toggle Profile Dropdown
            const profileBtn = document.getElementById("profileMenuBtn");
            const profileWrap = document.querySelector(".profile-dropdown-wrap");

            if (profileBtn && profileWrap) {
                profileBtn.addEventListener("click", function(e) {
                    e.stopPropagation();
                    profileWrap.classList.toggle("open");
                });

                // Close when clicking outside
                document.addEventListener("click", function(e) {
                    if (!profileWrap.contains(e.target)) {
                        profileWrap.classList.remove("open");
                    }
                });
            }
        </script>