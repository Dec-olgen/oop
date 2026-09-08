<?php
// [SECTION: INCLUDES - admin only]
require_once "../includes/auth.php";
require_login();
require_role('admin');
require_once "../includes/config.php";

// [SECTION: FETCH ALL ACCOUNTS - FROM BOTH TABLES]
$sql = "
    SELECT id, username, is_active, created_at, 'admin' AS role FROM admins
    UNION ALL
    SELECT id, username, is_active, created_at, 'staff' AS role FROM staff
    ORDER BY role ASC, username ASC
";
$result = $conn->query($sql);
$accounts = [];
while ($row = $result->fetch_assoc()) {
    $accounts[] = $row;
}

// [SECTION: LOOK UP EACH ACCOUNT'S LAST SUCCESSFUL LOGIN]
// One query for everyone, then matched up in PHP by username+role,
// instead of running a separate query per row.
$lastLoginMap = [];
$loginResult = $conn->query("SELECT username, role, MAX(attempted_at) AS last_login FROM login_log WHERE success = 1 GROUP BY username, role");
while ($loginRow = $loginResult->fetch_assoc()) {
    $lastLoginMap[$loginRow['username'] . '|' . $loginRow['role']] = $loginRow['last_login'];
}

$conn->close();

// [SECTION: FLASH MESSAGE]
$msg = isset($_GET['msg']) ? $_GET['msg'] : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Accounts - Ward Stock</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
<!-- [SECTION: SIDEBAR NAVIGATION] -->
<?php $sharedNavPath = "../shared/"; include "../shared/admin_and_staff_sidebar.php"; ?>

<div class="container">
    <!-- [SECTION: PAGE HEADER] -->
    <header>
        <h1>Manage Accounts</h1>
        <p class="subtitle">Deactivate or delete Admin and Staff logins</p>
    </header>

    <!-- [SECTION: FLASH MESSAGES] -->
    <?php if ($msg === "deactivated"): ?>
        <div class="alert alert-success">Account deactivated. That user can no longer log in.</div>
    <?php elseif ($msg === "activated"): ?>
        <div class="alert alert-success">Account reactivated.</div>
    <?php elseif ($msg === "deleted"): ?>
        <div class="alert alert-success">Account deleted.</div>
    <?php elseif ($msg === "passwordreset"): ?>
        <div class="alert alert-success">Password reset successfully.</div>
    <?php elseif ($msg === "self"): ?>
        <div class="alert alert-error">You can't deactivate or delete your own account while logged in as it.</div>
    <?php elseif ($msg === "lastadmin"): ?>
        <div class="alert alert-error">You can't remove the last remaining admin account.</div>
    <?php elseif ($msg === "dberror"): ?>
        <div class="alert alert-error">Something went wrong saving that change. Nothing was modified - please try again.</div>
    <?php endif; ?>

    <!-- [SECTION: ACCOUNTS TABLE] -->
    <div class="table-scroll">
    <table>
        <thead>
            <tr>
                <th>Username</th>
                <th>Role</th>
                <th>Status</th>
                <th>Created</th>
                <th>Last Login</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($accounts)): ?>
            <tr><td colspan="6" class="empty">No accounts found.</td></tr>
        <?php else: ?>
            <?php foreach ($accounts as $row):
                $isSelf = ($row['id'] == $_SESSION['admin_id'] && $row['role'] === $_SESSION['role']);
                $lastLogin = $lastLoginMap[$row['username'] . '|' . $row['role']] ?? null;
            ?>
                <tr>
                    <td><?= htmlspecialchars($row['username']) ?> <?= $isSelf ? '<span class="tag tag-warn">You</span>' : '' ?></td>
                    <td><span class="badge"><?= htmlspecialchars(ucfirst($row['role'])) ?></span></td>
                    <td>
                        <?php if ((int)$row['is_active'] === 1): ?>
                            <span class="tag tag-increase">Active</span>
                        <?php else: ?>
                            <span class="tag tag-decrease">Deactivated</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                    <td><?= $lastLogin ? htmlspecialchars($lastLogin) : '<span class="empty" style="padding:0;">Never</span>' ?></td>
                    <td class="actions">
                        <a href="reset_password.php?id=<?= $row['id'] ?>&role=<?= $row['role'] ?>" class="btn btn-edit">Reset Password</a>
                        <?php if (!$isSelf): ?>
                            <form method="POST" action="toggle_account_status.php" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <input type="hidden" name="role" value="<?= $row['role'] ?>">
                                <?php if ((int)$row['is_active'] === 1): ?>
                                    <button type="submit" class="btn btn-clear">Deactivate</button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-add">Activate</button>
                                <?php endif; ?>
                            </form>
                            <a href="delete_account_confirm.php?id=<?= $row['id'] ?>&role=<?= $row['role'] ?>" class="btn btn-delete">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
</div>
</body>
</html>
