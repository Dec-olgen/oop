<?php
// [SECTION: INCLUDES - admin only]
require_once "../includes/auth.php";
require_login();
require_role('admin');
require_once "../includes/config.php";

// [SECTION: READ WHICH ACCOUNT TO DELETE]
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$role = isset($_GET['role']) ? $_GET['role'] : '';

if ($id <= 0 || !in_array($role, ['admin', 'staff'])) {
    header("Location: admin_accounts.php");
    exit();
}
$table = $role === 'admin' ? 'admins' : 'staff';

// [SECTION: SAFETY CHECK - CAN'T DELETE YOUR OWN ACCOUNT]
if ($id === (int)$_SESSION['admin_id'] && $role === $_SESSION['role']) {
    header("Location: admin_accounts.php?msg=self");
    exit();
}

// [SECTION: LOOK UP THE TARGET ACCOUNT - needed to show who's about to be deleted]
$stmt = $conn->prepare("SELECT id, username, created_at FROM $table WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$target = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$target) {
    $conn->close();
    header("Location: admin_accounts.php");
    exit();
}

$error = "";
if (isset($_GET['msg']) && $_GET['msg'] === 'confirmneeded') {
    $error = "You must type DELETE exactly to confirm.";
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Confirm Delete Account - Ward Stock</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
<!-- [SECTION: SIDEBAR NAVIGATION] -->
<?php $sharedNavPath = "../shared/"; include "../shared/admin_and_staff_sidebar.php"; ?>

<div class="container">
    <!-- [SECTION: PAGE HEADER] -->
    <header>
        <h1>Confirm Delete Account</h1>
        <p class="subtitle">This action is permanent and cannot be undone</p>
    </header>

    <?php if ($error !== ""): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- [SECTION: ACCOUNT SUMMARY - who's about to be deleted] -->
    <div class="confirm-card">
        <p><strong>Username:</strong> <?= htmlspecialchars($target['username']) ?></p>
        <p><strong>Role:</strong> <?= htmlspecialchars(ucfirst($role)) ?></p>
        <p><strong>Created:</strong> <?= htmlspecialchars($target['created_at']) ?></p>
    </div>

    <!-- [SECTION: TYPE-TO-CONFIRM FORM]
         The real safeguard - a JS popup can be bypassed, but this
         requires an explicit, deliberate action typed on this page,
         and the target file also checks it server-side. -->
    <form method="POST" action="delete_account.php" class="form-card">
        <input type="hidden" name="id" value="<?= (int)$target['id'] ?>">
        <input type="hidden" name="role" value="<?= htmlspecialchars($role) ?>">

        <label>Type <strong>DELETE</strong> to confirm</label>
        <input type="text" name="confirm_text" placeholder="DELETE" autocomplete="off" required>

        <div class="form-actions">
            <button type="submit" class="btn btn-delete">Permanently Delete Account</button>
            <a href="admin_accounts.php" class="btn btn-clear">Cancel</a>
        </div>
    </form>
</div>
</div>
</body>
</html>
