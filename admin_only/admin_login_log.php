<?php
// [SECTION: INCLUDES - admin only]
require_once "../includes/auth.php";
require_login();
require_role('admin');
require_once "../includes/config.php";

// [SECTION: READ FILTERS FROM URL]
$search   = isset($_GET['search']) ? trim($_GET['search']) : "";
$statusF  = isset($_GET['status']) ? trim($_GET['status']) : "";
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : "";
$dateTo   = isset($_GET['date_to']) ? trim($_GET['date_to']) : "";
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage  = 50;
$offset   = ($page - 1) * $perPage;

// [SECTION: BUILD THE FILTERED QUERY]
$sql = "SELECT * FROM login_log WHERE 1=1";
$params = [];
$types  = "";

// [SUBSECTION: SEARCH BY USERNAME]
if ($search !== "") {
    $sql .= " AND username LIKE ?";
    $params[] = "%$search%";
    $types   .= "s";
}

// [SUBSECTION: FILTER BY SUCCESS / FAILED]
if ($statusF === "success") {
    $sql .= " AND success = 1";
} elseif ($statusF === "failed") {
    $sql .= " AND success = 0";
}

// [SUBSECTION: FILTER BY DATE RANGE]
if ($dateFrom !== "") {
    $sql .= " AND DATE(attempted_at) >= ?";
    $params[] = $dateFrom;
    $types   .= "s";
}
if ($dateTo !== "") {
    $sql .= " AND DATE(attempted_at) <= ?";
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
$sql .= " ORDER BY attempted_at DESC LIMIT ? OFFSET ?";
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
<title>Login Log - Ward Stock</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
<!-- [SECTION: SIDEBAR NAVIGATION] -->
<?php $sharedNavPath = "../shared/"; include "../shared/admin_and_staff_sidebar.php"; ?>

<div class="container">
    <!-- [SECTION: PAGE HEADER] -->
    <header>
        <h1>Login Log</h1>
        <p class="subtitle">Every login attempt, successful or not</p>
    </header>

    <!-- [SECTION: TOOLBAR - search, status filter, date range] -->
    <div class="toolbar">
        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="Search username..."
                   value="<?= htmlspecialchars($search) ?>">

            <select name="status">
                <option value="">All Attempts</option>
                <option value="success" <?= $statusF === 'success' ? 'selected' : '' ?>>Successful Only</option>
                <option value="failed" <?= $statusF === 'failed' ? 'selected' : '' ?>>Failed Only</option>
            </select>

            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" title="From date">
            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" title="To date">

            <button type="submit">Search</button>
            <a href="admin_login_log.php" class="btn-clear">Clear</a>
        </form>
    </div>

    <!-- [SECTION: LOGIN LOG TABLE] -->
    <div class="table-scroll">
    <table>
        <thead>
            <tr>
                <th>Date &amp; Time</th>
                <th>Username</th>
                <th>Role</th>
                <th>Result</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows === 0): ?>
            <!-- [SUBSECTION: EMPTY STATE] -->
            <tr><td colspan="4" class="empty">No login attempts found for these filters.</td></tr>
        <?php else: ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['attempted_at']) ?></td>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td>
                        <?= $row['role'] ? '<span class="badge">' . htmlspecialchars(ucfirst($row['role'])) . '</span>' : '<span class="empty" style="padding:0;">-</span>' ?>
                    </td>
                    <td>
                        <?php if ((int)$row['success'] === 1): ?>
                            <span class="tag tag-increase">Success</span>
                        <?php else: ?>
                            <span class="tag tag-decrease">Failed</span>
                        <?php endif; ?>
                    </td>
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
                $pageUrl = "admin_login_log.php?" . http_build_query($qs);
            ?>
                <a href="<?= htmlspecialchars($pageUrl) ?>" class="<?= $p === $page ? 'page-link active' : 'page-link' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
</div>
</body>
</html>
