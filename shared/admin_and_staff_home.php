<?php
// [SECTION: INCLUDES - require login before this page works]
require_once "../includes/auth.php";
require_login();
require_any_role(['admin', 'staff']);
require_once "../includes/config.php";

// [SECTION: OVERALL TOTALS]
$totalsResult = $conn->query("SELECT COUNT(*) AS total_items, COUNT(DISTINCT location) AS total_locations FROM inventory");
$totals = $totalsResult->fetch_assoc();

// [SECTION: SUMMARY STATS - low stock / expiring / expired, across everything]
$summaryResult = $conn->query("SELECT * FROM inventory");
$lowStockCount     = 0;
$expiringSoonCount = 0;
$expiredCount      = 0;
$needsAttention    = [];

while ($row = $summaryResult->fetch_assoc()) {
    $reasons = [];

    if ($row['quantity'] <= get_low_stock_threshold($row['category'])) {
        $lowStockCount++;
        $reasons[] = 'low';
    }

    if (!empty($row['expiry_date']) && in_array($row['category'], ["Medicine", "Consumable"])) {
        $daysLeft = (strtotime($row['expiry_date']) - time()) / 86400;
        if ($daysLeft < 0) {
            $expiredCount++;
            $reasons[] = 'expired';
        } elseif ($daysLeft <= 60) {
            $expiringSoonCount++;
            $reasons[] = 'expiring';
        }
    }

    if (!empty($reasons)) {
        $row['reasons'] = $reasons;
        $needsAttention[] = $row;
    }
}

// [SUBSECTION: SORT NEEDS ATTENTION - expired first, then low stock, then expiring soon]
usort($needsAttention, function ($a, $b) {
    $priority = function ($row) {
        if (in_array('expired', $row['reasons'])) return 0;
        if (in_array('low', $row['reasons'])) return 1;
        return 2;
    };
    return $priority($a) <=> $priority($b);
});
$needsAttention = array_slice($needsAttention, 0, 8);

// [SECTION: CATEGORY BREAKDOWN - item count per category]
$catBreakdown = $conn->query("SELECT category, COUNT(*) AS item_count FROM inventory GROUP BY category ORDER BY category ASC");
$catIcons = ['Medicine' => '💊', 'Consumable' => '🧰', 'Equipment' => '🩺', 'Linen' => '🧺', 'Training Model' => '🎓'];

// [SECTION: RECENT ACTIVITY - last 8 entries from the unified audit feed]
$recentSql = "
    SELECT created_at, adjusted_by_username AS performed_by, item_name, action,
        CONCAT(quantity_before, ' -> ', quantity_after) AS detail
    FROM stock_adjustments

    UNION ALL

    SELECT created_at, performed_by_username AS performed_by, item_name, action,
        COALESCE(detail, '') AS detail
    FROM item_actions_log

    ORDER BY created_at DESC
    LIMIT 8
";
$recentResult = $conn->query($recentSql);

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Home - Ward Stock</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="layout">
<!-- [SECTION: SIDEBAR NAVIGATION] -->
<?php $adminNavPath = "../admin_only/"; include "admin_and_staff_sidebar.php"; ?>

<div class="container">
    <!-- [SECTION: PAGE HEADER] -->
    <header>
        <h1>Home</h1>
        <p class="subtitle">Ward Stock at a glance</p>
    </header>

    <!-- [SECTION: SUMMARY BANNER] -->
    <div class="summary-banner">
        <div class="summary-chip">
            <span class="summary-count"><?= (int)$totals['total_items'] ?></span>
            <span class="summary-label">Total Items</span>
        </div>
        <div class="summary-chip">
            <span class="summary-count"><?= (int)$totals['total_locations'] ?></span>
            <span class="summary-label">Locations</span>
        </div>
        <div class="summary-chip <?= $lowStockCount > 0 ? 'summary-chip-warn' : '' ?>">
            <span class="summary-count"><?= $lowStockCount ?></span>
            <span class="summary-label">Low Stock</span>
        </div>
        <div class="summary-chip <?= $expiringSoonCount > 0 ? 'summary-chip-warn' : '' ?>">
            <span class="summary-count"><?= $expiringSoonCount ?></span>
            <span class="summary-label">Expiring Soon</span>
        </div>
        <div class="summary-chip <?= $expiredCount > 0 ? 'summary-chip-danger' : '' ?>">
            <span class="summary-count"><?= $expiredCount ?></span>
            <span class="summary-label">Expired</span>
        </div>
    </div>

    <!-- [SECTION: QUICK ACTIONS] -->
    <div class="quick-actions">
        <a href="admin_and_staff_create.php" class="btn btn-add">+ Add Item</a>
        <a href="admin_and_staff_dashboard.php" class="btn btn-clear">📋 View Full Inventory</a>
        <?php if ($isAdmin): ?>
            <a href="../admin_only/admin_register.php" class="btn btn-clear">🛡️ Create Account</a>
        <?php endif; ?>
    </div>

    <!-- [SECTION: DASHBOARD GRID - needs attention + recent activity side by side] -->
    <div class="dashboard-grid">
        <!-- [SUBSECTION: NEEDS ATTENTION] -->
        <div class="dashboard-card">
            <h2>⚠️ Needs Attention</h2>
            <?php if (empty($needsAttention)): ?>
                <p class="empty" style="padding:12px 0;">Nothing needs attention right now.</p>
            <?php else: ?>
                <?php foreach ($needsAttention as $row): ?>
                    <div class="attention-item">
                        <span>
                            <?= htmlspecialchars($row['item_name']) ?>
                            <span class="badge"><?= htmlspecialchars($row['category']) ?></span>
                        </span>
                        <span>
                            <?php if (in_array('expired', $row['reasons'])): ?>
                                <span class="tag tag-expired">Expired</span>
                            <?php endif; ?>
                            <?php if (in_array('low', $row['reasons'])): ?>
                                <span class="tag tag-low">Low Stock</span>
                            <?php endif; ?>
                            <?php if (in_array('expiring', $row['reasons'])): ?>
                                <span class="tag tag-warn">Expiring Soon</span>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
                <a href="admin_and_staff_dashboard.php" class="dashboard-card-link">View all in Inventory →</a>
            <?php endif; ?>
        </div>

        <!-- [SUBSECTION: RECENT ACTIVITY] -->
        <div class="dashboard-card">
            <h2>🕒 Recent Activity</h2>
            <?php if ($recentResult->num_rows === 0): ?>
                <p class="empty" style="padding:12px 0;">No activity recorded yet.</p>
            <?php else: ?>
                <?php while ($row = $recentResult->fetch_assoc()): ?>
                    <div class="activity-item">
                        <span>
                            <strong><?= htmlspecialchars($row['performed_by']) ?></strong>
                            <?php if ($row['action'] === 'increase'): ?>
                                increased
                            <?php elseif ($row['action'] === 'decrease'): ?>
                                decreased
                            <?php elseif ($row['action'] === 'created'): ?>
                                created
                            <?php elseif ($row['action'] === 'updated'): ?>
                                updated
                            <?php elseif ($row['action'] === 'deleted'): ?>
                                deleted
                            <?php endif; ?>
                            <?= htmlspecialchars($row['item_name']) ?>
                        </span>
                        <span class="activity-time"><?= htmlspecialchars($row['created_at']) ?></span>
                    </div>
                <?php endwhile; ?>
                <?php if ($isAdmin): ?>
                    <a href="../admin_only/admin_audit_log.php" class="dashboard-card-link">View full Audit Log →</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- [SECTION: CATEGORY BREAKDOWN] -->
    <div class="dashboard-card" style="margin-top: 20px;">
        <h2>📦 By Category</h2>
        <div class="category-breakdown">
            <?php while ($row = $catBreakdown->fetch_assoc()):
                $icon = $catIcons[$row['category']] ?? '📦';
            ?>
                <a href="admin_and_staff_dashboard.php?category=<?= urlencode($row['category']) ?>" class="category-chip">
                    <?= $icon ?> <?= htmlspecialchars($row['category']) ?> (<?= (int)$row['item_count'] ?>)
                </a>
            <?php endwhile; ?>
        </div>
    </div>
</div>
</div>

</body>
</html>
