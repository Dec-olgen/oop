<?php
// [SECTION: DETERMINE CURRENT PAGE - used to highlight active nav link]
$current = basename($_SERVER['PHP_SELF']);
// [SECTION: GET LOGGED-IN USER'S NAME AND ROLE]
$adminName = $_SESSION['admin_username'] ?? 'User';
$isAdmin   = ($_SESSION['role'] ?? '') === 'admin';
// [SECTION: NAV PATH PREFIXES]
// This sidebar is included from both shared/ and admin_only/ pages,
// which sit in different folders - so the including page must set
// these before the include, since the correct prefix depends on
// where THAT page lives, not where this sidebar file lives.
$sharedNavPath = $sharedNavPath ?? '';
$adminNavPath  = $adminNavPath ?? '';
// [SECTION: COLLAPSED STATE - remembered across page loads via cookie]
// Desktop-only feature; mobile always uses the full-screen hamburger
// menu regardless of this setting.
$isCollapsed = isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] === '1';
?>
<!-- [SECTION: SIDEBAR CONTAINER] -->
<aside class="sidebar<?= $isCollapsed ? ' collapsed' : '' ?>">
    <!-- [SUBSECTION: MOBILE MENU TOGGLE - hidden checkbox drives a pure-CSS
         open/close, no JavaScript needed. Must come before .sidebar-menu
         in the HTML for the CSS sibling selector below to work. -->
    <input type="checkbox" id="sidebar-toggle" class="sidebar-toggle-checkbox">

    <!-- [SUBSECTION: TOPBAR - brand + hamburger button (mobile) + collapse
         button (desktop) - each only shows on its own screen size] -->
    <div class="sidebar-topbar">
        <div class="sidebar-brand">
            <span class="sidebar-logo">🏥</span>
            <span class="sidebar-title sidebar-label">Ward Stock</span>
        </div>
        <button type="button" id="sidebar-collapse-btn" class="sidebar-collapse-btn" aria-label="Collapse sidebar">
            <?= $isCollapsed ? '›' : '‹' ?>
        </button>
        <label for="sidebar-toggle" class="sidebar-toggle-btn" aria-label="Menu">☰</label>
    </div>

    <!-- [SUBSECTION: COLLAPSIBLE MENU - nav + footer together, this is
         what the hamburger button shows/hides on mobile -->
    <div class="sidebar-menu">
        <!-- [SUBSECTION: CLOSE BUTTON - only visible on mobile, since the
             menu becomes a full-screen overlay there and covers the
             hamburger button underneath it] -->
        <label for="sidebar-toggle" class="sidebar-close-btn">✕ Close Menu</label>

        <!-- [SUBSECTION: NAVIGATION LINKS]
             Each link separates its icon from its label so the label can
             be hidden when collapsed while the icon stays put. The
             data-tooltip attribute is what shows the little popup label
             on hover when collapsed (see script.js). -->
        <nav class="sidebar-nav">
            <a href="<?= $sharedNavPath ?>admin_and_staff_home.php" class="<?= $current === 'admin_and_staff_home.php' ? 'active' : '' ?>" data-tooltip="Home">
                <span class="sidebar-icon">🏠</span><span class="sidebar-label">Home</span>
            </a>
            <a href="<?= $sharedNavPath ?>admin_and_staff_create.php" class="<?= $current === 'admin_and_staff_create.php' ? 'active' : '' ?>" data-tooltip="Add Item">
                <span class="sidebar-icon">➕</span><span class="sidebar-label">Add Item</span>
            </a>
            <!-- [SUBSECTION: CATEGORY QUICK FILTERS] -->
            <a href="<?= $sharedNavPath ?>admin_and_staff_dashboard.php?category=Medicine" data-tooltip="Medicine">
                <span class="sidebar-icon">💊</span><span class="sidebar-label">Medicine</span>
            </a>
            <a href="<?= $sharedNavPath ?>admin_and_staff_dashboard.php?category=Consumable" data-tooltip="Consumables">
                <span class="sidebar-icon">🧰</span><span class="sidebar-label">Consumables</span>
            </a>
            <a href="<?= $sharedNavPath ?>admin_and_staff_dashboard.php?category=Equipment" data-tooltip="Equipment">
                <span class="sidebar-icon">🩺</span><span class="sidebar-label">Equipment</span>
            </a>
            <a href="<?= $sharedNavPath ?>admin_and_staff_dashboard.php?category=Linen" data-tooltip="Linen">
                <span class="sidebar-icon">🧺</span><span class="sidebar-label">Linen</span>
            </a>
            <a href="<?= $sharedNavPath ?>admin_and_staff_dashboard.php?category=Training+Model" data-tooltip="Training Model">
                <span class="sidebar-icon">🎓</span><span class="sidebar-label">Training Model</span>
            </a>

            <!-- [SUBSECTION: ADMIN-ONLY LINKS - hidden entirely for staff] -->
            <?php if ($isAdmin): ?>
                <a href="<?= $adminNavPath ?>admin_register.php" class="<?= $current === 'admin_register.php' ? 'active' : '' ?>" data-tooltip="Create Account">
                    <span class="sidebar-icon">🛡️</span><span class="sidebar-label">Create Account</span>
                </a>
                <a href="<?= $adminNavPath ?>admin_accounts.php" class="<?= $current === 'admin_accounts.php' ? 'active' : '' ?>" data-tooltip="Manage Accounts">
                    <span class="sidebar-icon">👥</span><span class="sidebar-label">Manage Accounts</span>
                </a>

                <!-- [SUBSECTION: LOGS SECTION - groups the three log pages together] -->
                <div class="sidebar-section-label">📁 Logs</div>
                <a href="<?= $adminNavPath ?>admin_audit_log.php" class="sidebar-sublink <?= $current === 'admin_audit_log.php' ? 'active' : '' ?>" data-tooltip="Audit Log">
                    <span class="sidebar-icon">📜</span><span class="sidebar-label">Audit Log</span>
                </a>
                <a href="<?= $adminNavPath ?>admin_activity_log.php" class="sidebar-sublink <?= $current === 'admin_activity_log.php' ? 'active' : '' ?>" data-tooltip="Activity Log">
                    <span class="sidebar-icon">🕵️</span><span class="sidebar-label">Activity Log</span>
                </a>
                <a href="<?= $adminNavPath ?>admin_login_log.php" class="sidebar-sublink <?= $current === 'admin_login_log.php' ? 'active' : '' ?>" data-tooltip="Login Log">
                    <span class="sidebar-icon">🔑</span><span class="sidebar-label">Login Log</span>
                </a>
            <?php endif; ?>
        </nav>

        <!-- [SUBSECTION: LOGGED-IN USER + LOGOUT] -->
        <div class="sidebar-footer">
            <div class="sidebar-user" data-tooltip="<?= htmlspecialchars($adminName) ?> (<?= $isAdmin ? 'Admin' : 'Staff' ?>)">
                <span class="sidebar-icon">👤</span><span class="sidebar-label"><?= htmlspecialchars($adminName) ?> (<?= $isAdmin ? 'Admin' : 'Staff' ?>)</span>
            </div>
            <a href="../logout.php" class="sidebar-logout" data-tooltip="Log Out">
                <span class="sidebar-icon">🚪</span><span class="sidebar-label">Log Out</span>
            </a>
        </div>
    </div>
</aside>
