<?php
// [SECTION: INCLUDES - admin only]
require_once "../includes/auth.php";
require_login();
require_role('admin');
require_once "../includes/config.php";

// [SECTION: READ FILTERS FROM URL]
$search    = isset($_GET['search']) ? trim($_GET['search']) : "";
$actionF   = isset($_GET['action']) ? trim($_GET['action']) : "";
$dateFrom  = isset($_GET['date_from']) ? trim($_GET['date_from']) : "";
$dateTo    = isset($_GET['date_to']) ? trim($_GET['date_to']) : "";
$page      = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage   = 50;
$offset    = ($page - 1) * $perPage;

// [SECTION: BUILD THE UNIFIED FEED AS A SUBQUERY, THEN FILTER/PAGE IT]
// Combines staff quantity adjustments and admin create/edit/delete
// actions into one feed, sorted with the most recent first.
$baseSql = "
    SELECT
        created_at,
        adjusted_by_username AS performed_by,
        item_name,
        category,
        action,
        CONCAT(quantity_before, ' -> ', quantity_after) AS detail
    FROM stock_adjustments

    UNION ALL

    SELECT
        created_at,
        performed_by_username AS performed_by,
        item_name,
        category,
        action,
        COALESCE(detail, '') AS detail
    FROM item_actions_log
";

$sql = "SELECT * FROM ($baseSql) AS feed WHERE 1=1";
$params = [];
$types  = "";

// [SUBSECTION: SEARCH BY ITEM NAME OR PERFORMED BY]
if ($search !== "") {
    $sql .= " AND (item_name LIKE ? OR performed_by LIKE ?)";
    $likeTerm = "%$search%";
    $params[] = $likeTerm;
    $params[] = $likeTerm;
    $types   .= "ss";
}

// [SUBSECTION: FILTER BY ACTION TYPE]
if ($actionF !== "" && $actionF !== "All") {
    $sql .= " AND action = ?";
    $params[] = $actionF;
    $types   .= "s";
}

// [SUBSECTION: FILTER BY DATE RANGE]
if ($dateFrom !== "") {
    $sql .= " AND DATE(created_at) >= ?";
    $params[] = $dateFrom;
    $types   .= "s";
}
if ($dateTo !== "") {
    $sql .= " AND DATE(created_at) <= ?";
    $params[] = $dateTo;
    $types   .= "s";
}

// [SUBSECTION: COUNT TOTAL ROWS - for pagination]
$countStmt = $conn->prepare(str_replace("SELECT *", "SELECT COUNT(*) AS total", $sql));
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRows  = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = max(1, ceil($totalRows / $perPage));
$countStmt->close();

// [SUBSECTION: RUN THE PAGED QUERY]
$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types   .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Audit Log - Ward Stock</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
<!-- [SECTION: SIDEBAR NAVIGATION] -->
<?php $sharedNavPath = "../shared/"; include "../shared/admin_and_staff_sidebar.php"; ?>

<div class="container">
    <!-- [SECTION: PAGE HEADER] -->
    <header>
        <h1>Audit Log</h1>
        <p class="subtitle">Every stock adjustment and item change, by who and when</p>
    </header>

    <!-- [SECTION: TOOLBAR - search, action filter, date range] -->
    <div class="toolbar">
        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="Search item or username..."
                   value="<?= htmlspecialchars($search) ?>">

            <select name="action">
                <option value="All">All Actions</option>
                <option value="increase" <?= $actionF === 'increase' ? 'selected' : '' ?>>Stock Increase</option>
                <option value="decrease" <?= $actionF === 'decrease' ? 'selected' : '' ?>>Stock Decrease</option>
                <option value="created" <?= $actionF === 'created' ? 'selected' : '' ?>>Item Created</option>
                <option value="updated" <?= $actionF === 'updated' ? 'selected' : '' ?>>Item Updated</option>
                <option value="deleted" <?= $actionF === 'deleted' ? 'selected' : '' ?>>Item Deleted</option>
            </select>

            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" title="From date">
            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" title="To date">

            <button type="submit">Search</button>
            <a href="admin_audit_log.php" class="btn-clear">Clear</a>
        </form>
    </div>

    <!-- [SECTION: AUDIT LOG TABLE] -->
    <div class="table-scroll">
    <table>
        <thead>
            <tr>
                <th>Date &amp; Time</th>
                <th>Performed By</th>
                <th>Item</th>
                <th>Category</th>
                <th>Action</th>
                <th>Detail</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows === 0): ?>
            <!-- [SUBSECTION: EMPTY STATE] -->
            <tr><td colspan="6" class="empty">No activity found for these filters.</td></tr>
        <?php else: ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                    <td><?= htmlspecialchars($row['performed_by']) ?></td>
                    <td><?= htmlspecialchars($row['item_name']) ?></td>
                    <td><span class="badge"><?= htmlspecialchars($row['category']) ?></span></td>
                    <td>
                        <!-- [SUBSECTION: ACTION TAG - color-coded by type] -->
                        <?php if ($row['action'] === 'increase'): ?>
                            <span class="tag tag-increase">+ Stock Increase</span>
                        <?php elseif ($row['action'] === 'decrease'): ?>
                            <span class="tag tag-decrease">− Stock Decrease</span>
                        <?php elseif ($row['action'] === 'created'): ?>
                            <span class="tag tag-increase">Item Created</span>
                        <?php elseif ($row['action'] === 'updated'): ?>
                            <span class="tag tag-warn">Item Updated</span>
                        <?php elseif ($row['action'] === 'deleted'): ?>
                            <span class="tag tag-decrease">Item Deleted</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['detail']) ?></td>
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>

    <!-- [SECTION: PAGINATION] -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
            $qs = $_GET;
            for ($p = 1; $p <= $totalPages; $p++):
                $qs['page'] = $p;
                $pageUrl = "admin_audit_log.php?" . http_build_query($qs);
            ?>
                <a href="<?= htmlspecialchars($pageUrl) ?>" class="<?= $p === $page ? 'page-link active' : 'page-link' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
</div>
</body>
</html>
