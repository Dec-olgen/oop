<?php
// [SECTION: INCLUDES - admin only]
require_once "../includes/auth.php";
require_login();
require_role('admin');
require_once "../includes/config.php";

// [SECTION: READ INPUT]
$id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$role = isset($_POST['role']) ? $_POST['role'] : '';

// Only these two tables exist - guards against a tampered form value
if ($id <= 0 || !in_array($role, ['admin', 'staff'])) {
    header("Location: admin_accounts.php");
    exit();
}
$table = $role === 'admin' ? 'admins' : 'staff';

// [SECTION: SAFETY CHECK - CAN'T ACT ON YOUR OWN ACCOUNT]
// Must match BOTH id and role - an admin and a staff account can
// share the same id number now that they live in separate tables.
if ($id === (int)$_SESSION['admin_id'] && $role === $_SESSION['role']) {
    header("Location: admin_accounts.php?msg=self");
    exit();
}

// [SECTION: LOOK UP THE TARGET ACCOUNT]
$stmt = $conn->prepare("SELECT id, username, is_active FROM $table WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$target = $result->fetch_assoc();
$stmt->close();

if (!$target) {
    header("Location: admin_accounts.php");
    exit();
}

$newStatus = (int)$target['is_active'] === 1 ? 0 : 1;

// [SECTION: SAFETY CHECK - CAN'T DEACTIVATE THE LAST ACTIVE ADMIN]
if ($newStatus === 0 && $role === 'admin') {
    $countResult = $conn->query("SELECT COUNT(*) AS total FROM admins WHERE is_active = 1");
    $activeAdmins = $countResult->fetch_assoc()['total'];
    if ($activeAdmins <= 1) {
        header("Location: admin_accounts.php?msg=lastadmin");
        exit();
    }
}

// [SECTION: STATUS UPDATE + ACTIVITY LOG TOGETHER, AS ONE TRANSACTION]
$conn->begin_transaction();
$ok = true;

// [SECTION: UPDATE - FLIP THE STATUS]
$update = $conn->prepare("UPDATE $table SET is_active = ? WHERE id = ?");
$update->bind_param("ii", $newStatus, $id);
$ok = $ok && $update->execute();
$update->close();

// [SECTION: ACTIVITY LOG - RECORD WHO CHANGED THIS ACCOUNT'S STATUS]
$performedById   = $_SESSION['admin_id'];
$performedByRole = $_SESSION['role'];
$performedByName = $_SESSION['admin_username'];
$logAction       = $newStatus === 1 ? 'reactivated' : 'deactivated';
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
    // [SECTION: REDIRECT WITH RESULT]
    header("Location: admin_accounts.php?msg=" . ($newStatus === 1 ? "activated" : "deactivated"));
    exit();
} else {
    $conn->rollback();
    $conn->close();
    header("Location: admin_accounts.php?msg=dberror");
    exit();
}
?>
