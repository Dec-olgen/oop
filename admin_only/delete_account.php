<?php
// [SECTION: INCLUDES - admin only]
require_once "../includes/auth.php";
require_login();
require_role('admin');
require_once "../includes/config.php";

// [SECTION: ONLY ACCEPT POST - a GET request alone can never delete anything]
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: admin_accounts.php");
    exit();
}

// [SECTION: READ INPUT]
$id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$role        = isset($_POST['role']) ? $_POST['role'] : '';
$confirmText = isset($_POST['confirm_text']) ? trim($_POST['confirm_text']) : '';

// Only these two tables exist - guards against a tampered form value
if ($id <= 0 || !in_array($role, ['admin', 'staff'])) {
    header("Location: admin_accounts.php");
    exit();
}
$table = $role === 'admin' ? 'admins' : 'staff';

// [SECTION: REAL SERVER-SIDE CONFIRMATION CHECK]
// This is the actual safeguard - a JS popup can be bypassed by
// disabling JS or crafting a request directly, but this check runs
// here regardless of how the request arrived, and rejects anything
// that doesn't include the exact typed confirmation.
if ($confirmText !== 'DELETE') {
    header("Location: delete_account_confirm.php?id=$id&role=$role&msg=confirmneeded");
    exit();
}

// [SECTION: SAFETY CHECK - CAN'T DELETE YOUR OWN ACCOUNT]
// Must match BOTH id and role - an admin and a staff account can
// share the same id number now that they live in separate tables.
if ($id === (int)$_SESSION['admin_id'] && $role === $_SESSION['role']) {
    header("Location: admin_accounts.php?msg=self");
    exit();
}

// [SECTION: LOOK UP THE TARGET ACCOUNT]
$stmt = $conn->prepare("SELECT id, username FROM $table WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$target = $result->fetch_assoc();
$stmt->close();

if (!$target) {
    header("Location: admin_accounts.php");
    exit();
}

// [SECTION: SAFETY CHECK - CAN'T DELETE THE LAST ADMIN ACCOUNT]
if ($role === 'admin') {
    $countResult = $conn->query("SELECT COUNT(*) AS total FROM admins");
    $totalAdmins = $countResult->fetch_assoc()['total'];
    if ($totalAdmins <= 1) {
        header("Location: admin_accounts.php?msg=lastadmin");
        exit();
    }
}

// [SECTION: DELETE + ACTIVITY LOG TOGETHER, AS ONE TRANSACTION]
$conn->begin_transaction();
$ok = true;

// [SECTION: ACTIVITY LOG - RECORD THIS DELETION BEFORE THE ROW IS GONE]
$performedById   = $_SESSION['admin_id'];
$performedByRole = $_SESSION['role'];
$performedByName = $_SESSION['admin_username'];
$logAction       = 'deleted';
$log = $conn->prepare(
    "INSERT INTO account_activity_log (target_username, target_role, action, performed_by_id, performed_by_role, performed_by_username)
     VALUES (?, ?, ?, ?, ?, ?)"
);
$log->bind_param("sssiss", $target['username'], $role, $logAction, $performedById, $performedByRole, $performedByName);
$ok = $ok && $log->execute();
$log->close();

// [SECTION: DELETE - REMOVE THE ACCOUNT]
// Note: this does not delete their past entries in the audit/activity
// logs - those keep the username as plain text so the history stays intact.
$delete = $conn->prepare("DELETE FROM $table WHERE id = ?");
$delete->bind_param("i", $id);
$ok = $ok && $delete->execute();
$delete->close();

if ($ok) {
    $conn->commit();
    $conn->close();
    // [SECTION: REDIRECT WITH RESULT]
    header("Location: admin_accounts.php?msg=deleted");
    exit();
} else {
    $conn->rollback();
    $conn->close();
    header("Location: admin_accounts.php?msg=dberror");
    exit();
}
?>
