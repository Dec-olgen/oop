<?php
// [SECTION: INCLUDES]
require_once "../includes/auth.php";
require_login();
require_role('admin');
require_once "../includes/config.php";

$errors = [];
$success = false;

// [SECTION: HANDLE FORM SUBMISSION]
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];
    $role     = $_POST['role'] ?? '';

    // [SUBSECTION: VALIDATION]
    if ($username === "" || strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters.";
    }
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }
    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }
    // Only these two roles are allowed - guards against a tampered form value
    if (!in_array($role, ["admin", "staff"])) {
        $errors[] = "Please choose a valid role.";
    }

    // [SUBSECTION: CHECK USERNAME NOT ALREADY TAKEN - IN EITHER TABLE]
    if (empty($errors)) {
        $check = $conn->prepare("SELECT id FROM admins WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->store_result();
        $taken = $check->num_rows > 0;
        $check->close();

        if (!$taken) {
            $check = $conn->prepare("SELECT id FROM staff WHERE username = ?");
            $check->bind_param("s", $username);
            $check->execute();
            $check->store_result();
            $taken = $check->num_rows > 0;
            $check->close();
        }

        if ($taken) {
            $errors[] = "That username is already taken.";
        }
    }

    // [SUBSECTION: CREATE - INSERT INTO THE MATCHING TABLE]
    if (empty($errors)) {
        // NOTE: plain-text password storage - for local testing only.
        $hash  = $password;
        $table = $role === 'admin' ? 'admins' : 'staff';

        // [SUBSECTION: ACCOUNT + ACTIVITY LOG TOGETHER, AS ONE TRANSACTION]
        // If the log insert failed after the account was created (or vice
        // versa), the two would fall out of sync. Wrapping both in a
        // transaction means either BOTH succeed together, or NEITHER does.
        $conn->begin_transaction();
        $ok = true;

        $stmt  = $conn->prepare("INSERT INTO $table (username, password_hash) VALUES (?, ?)");
        $stmt->bind_param("ss", $username, $hash);
        $ok = $ok && $stmt->execute();
        $stmt->close();

        // [SUBSECTION: ACTIVITY LOG - RECORD WHO CREATED THIS ACCOUNT]
        $performedById   = $_SESSION['admin_id'];
        $performedByRole = $_SESSION['role'];
        $performedByName = $_SESSION['admin_username'];
        $logAction       = 'created';
        $log = $conn->prepare(
            "INSERT INTO account_activity_log (target_username, target_role, action, performed_by_id, performed_by_role, performed_by_username)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $log->bind_param("sssiss", $username, $role, $logAction, $performedById, $performedByRole, $performedByName);
        $ok = $ok && $log->execute();
        $log->close();

        if ($ok) {
            $conn->commit();
            $success = true;
        } else {
            $conn->rollback();
            $errors[] = "Something went wrong creating this account. Nothing was saved - please try again.";
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
<title>Create Account - Ward Stock</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
<!-- [SECTION: SIDEBAR NAVIGATION] -->
<?php $sharedNavPath = "../shared/"; include "../shared/admin_and_staff_sidebar.php"; ?>

<div class="container">
    <!-- [SECTION: PAGE HEADER] -->
    <header>
        <h1>Create Account</h1>
        <p class="subtitle">Add a new Admin or Staff login</p>
    </header>

    <!-- [SECTION: SUCCESS / ERROR MESSAGES] -->
    <?php if ($success): ?>
        <div class="alert alert-success">Account created successfully.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- [SECTION: CREATE ACCOUNT FORM] -->
    <form method="POST" class="form-card">
        <label>Username</label>
        <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <label>Confirm Password</label>
        <input type="password" name="confirm_password" required>

        <!-- [SUBSECTION: ROLE SELECTION] -->
        <label>Account Type</label>
        <select name="role" required>
            <option value="">Choose a role...</option>
            <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin (full access)</option>
            <option value="staff" <?= (($_POST['role'] ?? '') === 'staff') ? 'selected' : '' ?>>Staff (no account management or audit log access)</option>
        </select>

        <div class="form-actions">
            <button type="submit" class="btn btn-add">Create Account</button>
            <a href="admin_and_staff_dashboard.php" class="btn btn-clear">Cancel</a>
        </div>
    </form>
</div>
</div>
<script src="../assets/js/script.js"></script>
</body>
</html>
