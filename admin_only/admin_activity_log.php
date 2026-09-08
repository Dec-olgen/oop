<?php
// [SECTION: INCLUDES - admin only]
require_once "../includes/auth.php";
require_login();
require_role('admin');
require_once "../includes/config.php";

// [SECTION: READ FILTERS FROM URL]
$search   = isset($_GET['search']) ? trim($_GET['search']) : "";
$actionF  = isset($_GET['action']) ? trim($_GET['action']) : "";
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : "";
$dateTo   = isset($_GET['date_to']) ? trim($_GET['date_to']) : "";
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage  = 50;
$offset   = ($page - 1) * $perPage;

// [SECTION: BUILD THE FILTERED QUERY]
$sql = "SELECT * FROM account_activity_log WHERE 1=1";
$params = [];
$types  = "";

// [SUBSECTION: SEARCH BY TARGET USERNAME OR PERFORMED BY]
if ($search !== "") {
    $sql .= " AND (target_username LIKE ? OR performed_by_username LIKE ?)";
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
<title>Activity Log - Ward Stock</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
<!-- [SECTION: SIDEBAR NAVIGATION] -->
<?php $sharedNavPath = "../shared/"; include "../shared/admin_and_staff_sidebar.php"; ?>

<div class="container">
    <!-- [SECTION: PAGE HEADER] -->
    <header>
        <h1>Activity Log</h1>
        <p class="subtitle">Account changes - created, password reset, deactivated, reactivated, deleted</p>
    </header>

    <!-- [SECTION: TOOLBAR - search, action filter, date range] -->
    <div class="toolbar">
        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="Search username..."
                   value="<?= htmlspecialchars($search) ?>">

            <select name="action">
                <option value="All">All Actions</option>
                <option value="created" <?= $actionF === 'created' ? 'selected' : '' ?>>Account Created</option>
                <option value="password_reset" <?= $actionF === 'password_reset' ? 'selected' : '' ?>>Password Reset</option>
                <option value="deactivated" <?= $actionF === 'deactivated' ? 'selected' : '' ?>>Deactivated</option>
                <option value="reactivated" <?= $actionF === 'reactivated' ? 'selected' : '' ?>>Reactivated</option>
                <option value="deleted" <?= $actionF === 'deleted' ? 'selected' : '' ?>>Deleted</option>
            </select>

            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" title="From date">
            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" title="To date">

            <button type="submit">Search</button>
            <a href="admin_activity_log.php" class="btn-clear">Clear</a>
        </form>
    </div>

    <!-- [SECTION: ACTIVITY LOG TABLE] -->
    <div class="table-scroll">
    <table>
        <thead>
            <tr>
                <th>Date &amp; Time</th>
                <th>Account</th>
                <th>Role</th>
                <th>Action</th>
                <th>Performed By</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows === 0): ?>
            <!-- [SUBSECTION: EMPTY STATE] -->
            <tr><td colspan="5" class="empty">No account activity found for these filters.</td></tr>
        <?php else: ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                    <td><?= htmlspecialchars($row['target_username']) ?></td>
                    <td><span class="badge"><?= htmlspecialchars(ucfirst($row['target_role'])) ?></span></td>
                    <td>
                        <!-- [SUBSECTION: ACTION TAG - color-coded by type] -->
                        <?php if ($row['action'] === 'created'): ?>
                            <span class="tag tag-increase">Account Created</span>
                        <?php elseif ($row['action'] === 'password_reset'): ?>
                            <span class="tag tag-warn">Password Reset</span>
                        <?php elseif ($row['action'] === 'deactivated'): ?>
                            <span class="tag tag-decrease">Deactivated</span>
                        <?php elseif ($row['action'] === 'reactivated'): ?>
                            <span class="tag tag-increase">Reactivated</span>
                        <?php elseif ($row['action'] === 'deleted'): ?>
                            <span class="tag tag-decrease">Deleted</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['performed_by_username']) ?> (<?= htmlspecialchars(ucfirst($row['performed_by_role'])) ?>)</td>
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
                $pageUrl = "admin_activity_log.php?" . http_build_query($qs);
            ?>
                <a href="<?= htmlspecialchars($pageUrl) ?>" class="<?= $p === $page ? 'page-link active' : 'page-link' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
</div>
</body>
</html>
