<?php
// [SECTION: INCLUDES - admin only]
require_once "../includes/auth.php";
require_login();
require_role('admin');
require_once "../includes/config.php";

// [SECTION: READ WHICH ACCOUNT TO RESET]
$id   = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$role = isset($_GET['role']) ? $_GET['role'] : (isset($_POST['role']) ? $_POST['role'] : '');

// Only these two tables exist - guards against a tampered URL/form value
if ($id <= 0 || !in_array($role, ['admin', 'staff'])) {
    header("Location: admin_accounts.php");
    exit();
}
$table = $role === 'admin' ? 'admins' : 'staff';

// [SECTION: LOOK UP THE TARGET ACCOUNT - needed to show their username]
$stmt = $conn->prepare("SELECT id, username FROM $table WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$target = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$target) {
    header("Location: admin_accounts.php");
    exit();
}

$errors = [];

// [SECTION: HANDLE FORM SUBMISSION]
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    // [SUBSECTION: VALIDATION]
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }
    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }

    // [SUBSECTION: UPDATE - SAVE THE NEW PASSWORD]
    if (empty($errors)) {
        // NOTE: plain-text password storage - for local testing only.
        $hash = $password;

        // [SUBSECTION: PASSWORD UPDATE + ACTIVITY LOG TOGETHER, AS ONE TRANSACTION]
        $conn->begin_transaction();
        $ok = true;

        $update = $conn->prepare("UPDATE $table SET password_hash = ? WHERE id = ?");
        $update->bind_param("si", $hash, $id);
        $ok = $ok && $update->execute();
        $update->close();

        // [SUBSECTION: ACTIVITY LOG - RECORD WHO RESET THIS ACCOUNT'S PASSWORD]
        $performedById   = $_SESSION['admin_id'];
        $performedByRole = $_SESSION['role'];
        $performedByName = $_SESSION['admin_username'];
        $logAction       = 'password_reset';
        $log = $conn->prepare(
            "INSERT INTO account_activity_log (target_username, target_role, action, performed_by_id, performed_by_role, performed_by_username)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $log->bind_param("sssiss", $target['username'], $role, $logAction, $performedById, $performedByRole, $performedByName);
        $ok = $ok && $log->execute();
        $log->close();

        if ($ok) {
            $conn->commit();
            $conn->close();
            header("Location: admin_accounts.php?msg=passwordreset");
            exit();
        } else {
            $conn->rollback();
            $errors[] = "Something went wrong resetting this password. Nothing was changed - please try again.";
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password - Ward Stock</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
<!-- [SECTION: SIDEBAR NAVIGATION] -->
<?php $sharedNavPath = "../shared/"; include "../shared/admin_and_staff_sidebar.php"; ?>

<div class="container">
    <!-- [SECTION: PAGE HEADER] -->
    <header>
        <h1>Reset Password</h1>
        <p class="subtitle">
            Set a new password for
            <strong><?= htmlspecialchars($target['username']) ?></strong>
            (<?= htmlspecialchars(ucfirst($role)) ?>)
        </p>
    </header>

    <!-- [SECTION: VALIDATION ERROR MESSAGES] -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- [SECTION: RESET PASSWORD FORM] -->
    <form method="POST" class="form-card">
        <input type="hidden" name="id" value="<?= (int)$target['id'] ?>">
        <input type="hidden" name="role" value="<?= htmlspecialchars($role) ?>">

        <label>New Password</label>
        <input type="password" name="password" required>

        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" required>

        <div class="form-actions">
            <button type="submit" class="btn btn-add">Reset Password</button>
            <a href="admin_accounts.php" class="btn btn-clear">Cancel</a>
        </div>
    </form>
</div>
</div>
</body>
</html>
