<?php
// [SECTION: INCLUDES]
require_once "includes/auth.php";
require_once "includes/config.php";

$errors = [];

// [SECTION: REDIRECT IF ALREADY LOGGED IN]
if (isset($_SESSION['admin_id'])) {
    header("Location: shared/admin_and_staff_home.php");
    exit();
}

// [SECTION: HANDLE LOGIN FORM SUBMISSION]
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // [SUBSECTION: VALIDATION]
    if ($username === "" || $password === "") {
        $errors[] = "Please enter both username and password.";
    } else {
        // [SUBSECTION: READ - LOOK UP ACCOUNT IN THE ADMINS TABLE FIRST]
        $stmt = $conn->prepare("SELECT id, username, password_hash, is_active FROM admins WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $accountRole = 'admin';

        // [SUBSECTION: IF NOT FOUND THERE, CHECK THE STAFF TABLE]
        if (!$account) {
            $stmt = $conn->prepare("SELECT id, username, password_hash, is_active FROM staff WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $account = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $accountRole = 'staff';
        }

        // [SUBSECTION: VERIFY PASSWORD AND START SESSION]
        // NOTE: plain-text password comparison - for local testing only.
        // Passwords are stored as-is in the database, not hashed.
        $loginSuccess = false;
        $loginRole    = $account ? $accountRole : null; // null if username wasn't found at all

        if ($account && $password === $account['password_hash']) {
            if ((int)$account['is_active'] === 0) {
                $errors[] = "This account has been deactivated. Contact an administrator.";
            } else {
                $loginSuccess = true;
            }
        } else {
            $errors[] = "Invalid username or password.";
        }

        // [SUBSECTION: LOG THIS ATTEMPT - successful or not]
        $log = $conn->prepare("INSERT INTO login_log (username, role, success) VALUES (?, ?, ?)");
        $successFlag = $loginSuccess ? 1 : 0;
        $log->bind_param("ssi", $username, $loginRole, $successFlag);
        $log->execute();
        $log->close();

        if ($loginSuccess) {
            $_SESSION['admin_id']       = $account['id'];
            $_SESSION['admin_username'] = $account['username'];
            $_SESSION['role']           = $accountRole;
            $conn->close();
            header("Location: shared/admin_and_staff_home.php");
            exit();
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
<title>Log In - Ward Stock</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<!-- [SECTION: AUTH CARD LAYOUT] -->
<div class="auth-wrap">
    <div class="auth-card">
        <!-- [SUBSECTION: BRAND/LOGO] -->
        <div class="sidebar-brand auth-brand">
            <span class="sidebar-logo">🏥</span>
            <span class="sidebar-title" style="color:#0f766e;">Ward Stock</span>
        </div>
        <h2>Log In</h2>

        <!-- [SUBSECTION: ERROR MESSAGES] -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- [SUBSECTION: LOGIN FORM] -->
        <form method="POST" class="form-card">
            <label>Username</label>
            <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <div class="form-actions">
                <button type="submit" class="btn btn-add">Log In</button>
            </div>
        </form>

        <p class="auth-switch">Accounts are created by an administrator.</p>
    </div>
</div>
</body>
</html>
