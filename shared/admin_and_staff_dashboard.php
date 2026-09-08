<?php
// [SECTION: INCLUDES - require login before this page works]
require_once "../includes/auth.php";
require_login();
require_any_role(['admin', 'staff']);
require_once "../includes/config.php";

// [SECTION: READ FILTERS FROM URL]
$search   = isset($_GET['search']) ? trim($_GET['search']) : "";
$category = isset($_GET['category']) ? trim($_GET['category']) : "";
$location = isset($_GET['location']) ? trim($_GET['location']) : "";

// [SECTION: BUILD THE SEARCH/FILTER QUERY]
$sql = "SELECT * FROM inventory WHERE 1=1";
$params = [];
$types  = "";

// [SUBSECTION: SEARCH BY ITEM NAME, SKU, OR BRAND NAME]
if ($search !== "") {
    $sql .= " AND (item_name LIKE ? OR sku LIKE ? OR brand_name LIKE ?)";
    $likeTerm = "%$search%";
    $params[] = $likeTerm;
    $params[] = $likeTerm;
    $params[] = $likeTerm;
    $types   .= "sss";
}

// [SUBSECTION: FILTER BY CATEGORY]
if ($category !== "" && $category !== "All") {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types   .= "s";
}

// [SUBSECTION: FILTER BY LOCATION]
if ($location !== "" && $location !== "All") {
    if ($location === "None") {
        $sql .= " AND (location IS NULL OR location = '')";
    } else {
        $sql .= " AND location = ?";
        $params[] = $location;
        $types   .= "s";
    }
}

// [SUBSECTION: SORT ORDER - always grouped by location]
$sql .= " ORDER BY location ASC, item_name ASC";

// [SECTION: RUN THE QUERY - READ]
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// [SECTION: SPLIT RESULTS INTO EXPIRED vs EVERYTHING ELSE]
// Expired Medicine/Consumable items get pulled out and shown in
// their own warning section at the top of the table.
$expiryTrackedCategories = ["Medicine", "Consumable"];
$expiredItems = [];
$otherItems   = [];

while ($row = $result->fetch_assoc()) {
    $isExpired = false;
    if (!empty($row['expiry_date']) && in_array($row['category'], $expiryTrackedCategories)) {
        $daysLeft  = (strtotime($row['expiry_date']) - time()) / 86400;
        $isExpired = $daysLeft < 0;
    }
    if ($isExpired) {
        $expiredItems[] = $row;
    } else {
        $otherItems[] = $row;
    }
}

// [SECTION: SUMMARY STATS - across the WHOLE inventory, ignoring filters]
// These counts always reflect everything in stock, not just the
// current search/filter results, so the banner stays a reliable
// at-a-glance health check no matter what's being searched for.
$summaryResult = $conn->query("SELECT category, quantity, expiry_date FROM inventory");
$lowStockCount     = 0;
$expiringSoonCount = 0;
$expiredCount      = 0;

while ($sRow = $summaryResult->fetch_assoc()) {
    if ($sRow['quantity'] <= get_low_stock_threshold($sRow['category'])) {
        $lowStockCount++;
    }
    if (!empty($sRow['expiry_date']) && in_array($sRow['category'], ["Medicine", "Consumable"])) {
        $sDaysLeft = (strtotime($sRow['expiry_date']) - time()) / 86400;
        if ($sDaysLeft < 0) {
            $expiredCount++;
        } elseif ($sDaysLeft <= 60) {
            $expiringSoonCount++;
        }
    }
}

// [SECTION: PAGINATE THE NON-EXPIRED ITEMS]
// Expired items stay fully visible as a warning section regardless of
// page - they're meant to be unmissable, not tucked behind pagination.
$page      = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage   = 50;
$totalOtherItems = count($otherItems);
$totalPages       = max(1, ceil($totalOtherItems / $perPage));
$page             = min($page, $totalPages); // don't allow paging past the end
$offset           = ($page - 1) * $perPage;
$pagedOtherItems  = array_slice($otherItems, $offset, $perPage);

// [SECTION: DATA FOR FILTER DROPDOWNS]
// [SUBSECTION: DISTINCT LOCATIONS]
$locations = $conn->query("SELECT DISTINCT location FROM inventory WHERE location IS NOT NULL AND location != '' ORDER BY location ASC");

// [SECTION: FLASH MESSAGE - shown after create/update/delete redirects here]
$msg = isset($_GET['msg']) ? $_GET['msg'] : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ward Stock - Medical Inventory</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="layout">
<!-- [SECTION: SIDEBAR NAVIGATION] -->
<?php $adminNavPath = "../admin_only/"; include "admin_and_staff_sidebar.php"; ?>

<div class="container">
    <!-- [SECTION: PAGE HEADER] -->
    <header>
        <h1>Inventory</h1>
        <p class="subtitle">Medical Inventory Management</p>
    </header>

    <!-- [SECTION: FLASH MESSAGES] -->
    <?php if ($msg === "created"): ?>
        <div class="alert alert-success">Item added successfully.</div>
    <?php elseif ($msg === "updated"): ?>
        <div class="alert alert-success">Item updated successfully.</div>
    <?php elseif ($msg === "deleted"): ?>
        <div class="alert alert-success">Item deleted successfully.</div>
    <?php elseif ($msg === "adjusted"): ?>
        <div class="alert alert-success">Quantity updated.</div>
    <?php elseif ($msg === "notfound" || $msg === "invalid"): ?>
        <div class="alert alert-error">Something went wrong. Please try again.</div>
    <?php elseif ($msg === "dberror"): ?>
        <div class="alert alert-error">Something went wrong saving that change. Nothing was modified - please try again.</div>
    <?php endif; ?>

    <!-- [SECTION: SUMMARY BANNER - low stock / expiring / expired counts] -->
    <div class="summary-banner">
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

    <!-- [SECTION: TOOLBAR - search and location filter]
         Category filtering already lives in the sidebar (one link per
         category), and Add Item is already there too, so the toolbar
         doesn't need to repeat either of them. -->
    <div class="toolbar">
        <form method="GET" class="search-form">
            <!-- [SUBSECTION: SEARCH INPUT] -->
            <input type="text" name="search" placeholder="Search item, SKU, or brand name..."
                   value="<?= htmlspecialchars($search) ?>">

            <!-- [SUBSECTION: LOCATION FILTER] -->
            <select name="location">
                <option value="All">All Locations</option>
                <?php while ($row = $locations->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($row['location']) ?>"
                        <?= ($location === $row['location']) ? "selected" : "" ?>>
                        <?= htmlspecialchars($row['location']) ?>
                    </option>
                <?php endwhile; ?>
                <option value="None" <?= ($location === "None") ? "selected" : "" ?>>No Location Set</option>
            </select>

            <button type="submit">Search</button>
            <a href="admin_and_staff_dashboard.php" class="btn-clear">Clear</a>
        </form>
    </div>

    <!-- [SECTION: INVENTORY TABLE] -->
    <div class="table-scroll">
    <table>
        <!-- [SUBSECTION: TABLE HEADER] -->
        <thead>
            <tr>
                <th>Item Name</th>
                <th>SKU</th>
                <th>Category</th>
                <th>Quantity</th>
                <th>Unit</th>
                <th>Brand Name</th>
                <th>Expiry Date</th>
                <th>Location</th>
                <th>Adjust</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($expiredItems) && empty($otherItems)): ?>
            <!-- [SUBSECTION: EMPTY STATE] -->
            <tr><td colspan="10" class="empty">No items found.</td></tr>
        <?php else: ?>

            <!-- [SUBSECTION: EXPIRED ITEMS GROUP] -->
            <?php if (!empty($expiredItems)): ?>
                <tr class="section-divider section-divider-expired">
                    <td colspan="10">⚠️ Expired Medicine &amp; Consumables (<?= count($expiredItems) ?>)</td>
                </tr>
                <?php foreach ($expiredItems as $row):
                    $lowStock = $row['quantity'] <= get_low_stock_threshold($row['category']);
                ?>
                    <tr class="row-expired <?= $lowStock ? 'row-low' : '' ?>">
                        <td><?= htmlspecialchars($row['item_name']) ?></td>
                        <td><?= htmlspecialchars($row['sku'] ?? '-') ?></td>
                        <td><span class="badge"><?= htmlspecialchars($row['category']) ?></span></td>
                        <td><?= (int)$row['quantity'] ?> <?= $lowStock ? '<span class="tag tag-low">Low</span>' : '' ?></td>
                        <td><?= htmlspecialchars($row['unit']) ?></td>
                        <td><?= htmlspecialchars($row['brand_name'] ?? '-') ?></td>
                        <td>
                            <?= htmlspecialchars($row['expiry_date']) ?>
                            <span class="tag tag-expired">Expired</span>
                        </td>
                        <td><?= htmlspecialchars($row['location'] ?? '-') ?></td>
                        <td class="qty-adjust">
                            <form method="POST" action="adjust_stock.php" class="qty-form-inline">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <input type="number" name="amount" min="1" value="1" class="qty-amount-input">
                                <button type="submit" name="action" value="decrease" class="btn-qty btn-qty-minus" <?= $row['quantity'] <= 0 ? 'disabled' : '' ?>>−</button>
                                <button type="submit" name="action" value="increase" class="btn-qty btn-qty-plus">+</button>
                            </form>
                        </td>
                        <td class="actions">
                            <a href="admin_and_staff_edit.php?id=<?= $row['id'] ?>" class="btn btn-edit">Edit</a>
                            <a href="admin_and_staff_delete_confirm.php?id=<?= $row['id'] ?>" class="btn btn-delete">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <tr class="section-divider section-divider-normal">
                    <td colspan="10">All Other Items (grouped by location)</td>
                </tr>
            <?php endif; ?>

            <!-- [SUBSECTION: REMAINING ITEMS, GROUPED BY LOCATION] -->
            <?php $currentLocation = null; ?>
            <?php foreach ($pagedOtherItems as $row):

                $rowLocation = $row['location'] !== null && $row['location'] !== '' ? $row['location'] : 'No Location Set';
                // [LOCATION DIVIDER - shown whenever the location changes]
                if ($rowLocation !== $currentLocation):
                    $currentLocation = $rowLocation;
            ?>
                    <tr class="section-divider section-divider-location">
                        <td colspan="10">📍 <?= htmlspecialchars($currentLocation) ?></td>
                    </tr>
            <?php endif;
                $lowStock = $row['quantity'] <= get_low_stock_threshold($row['category']);
                $expiring = false;
                if (!empty($row['expiry_date'])) {
                    $daysLeft = (strtotime($row['expiry_date']) - time()) / 86400;
                    $expiring = $daysLeft <= 60 && $daysLeft >= 0;
                }
            ?>
                <tr class="<?= $lowStock ? 'row-low' : '' ?> <?= $expiring ? 'row-expiring' : '' ?>">
                    <td><?= htmlspecialchars($row['item_name']) ?></td>
                    <td><?= htmlspecialchars($row['sku'] ?? '-') ?></td>
                    <td><span class="badge"><?= htmlspecialchars($row['category']) ?></span></td>
                    <td><?= (int)$row['quantity'] ?> <?= $lowStock ? '<span class="tag tag-low">Low</span>' : '' ?></td>
                    <td><?= htmlspecialchars($row['unit']) ?></td>
                    <td><?= htmlspecialchars($row['brand_name'] ?? '-') ?></td>
                    <td>
                        <?= $row['expiry_date'] ? htmlspecialchars($row['expiry_date']) : '-' ?>
                        <?= $expiring ? '<span class="tag tag-warn">Expiring soon</span>' : '' ?>
                    </td>
                    <td><?= htmlspecialchars($row['location'] ?? '-') ?></td>
                    <td class="qty-adjust">
                        <form method="POST" action="adjust_stock.php" class="qty-form-inline">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <input type="number" name="amount" min="1" value="1" class="qty-amount-input">
                            <button type="submit" name="action" value="decrease" class="btn-qty btn-qty-minus" <?= $row['quantity'] <= 0 ? 'disabled' : '' ?>>−</button>
                            <button type="submit" name="action" value="increase" class="btn-qty btn-qty-plus">+</button>
                        </form>
                    </td>
                    <td class="actions">
                        <a href="admin_and_staff_edit.php?id=<?= $row['id'] ?>" class="btn btn-edit">Edit</a>
                        <a href="admin_and_staff_delete_confirm.php?id=<?= $row['id'] ?>" class="btn btn-delete">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>

        <?php endif; ?>
        </tbody>
    </table>
    </div>

    <!-- [SECTION: PAGINATION - applies to the non-expired items list] -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
            $qs = $_GET;
            for ($p = 1; $p <= $totalPages; $p++):
                $qs['page'] = $p;
                $pageUrl = "admin_and_staff_dashboard.php?" . http_build_query($qs);
            ?>
                <a href="<?= htmlspecialchars($pageUrl) ?>" class="<?= $p === $page ? 'page-link active' : 'page-link' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
</div>

<script src="../assets/js/script.js"></script>
</body>
</html>
<?php $stmt->close(); $conn->close(); ?>
