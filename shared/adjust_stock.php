<?php
// [SECTION: INCLUDES - logged-in admin OR staff can adjust stock]
require_once "../includes/auth.php";
require_login();
require_any_role(['admin', 'staff']);
require_once "../includes/config.php";

// [SECTION: READ INPUT]
$id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';
$amount = isset($_POST['amount']) ? (int)$_POST['amount'] : 1;

// Amount must be a sane positive whole number - default back to 1 if not
if ($amount < 1) {
    $amount = 1;
}
if ($amount > 100000) {
    $amount = 100000;
}

if ($id <= 0 || !in_array($action, ['increase', 'decrease'])) {
    header("Location: admin_and_staff_dashboard.php?msg=invalid");
    exit();
}

// [SECTION: LOOK UP THE ITEM]
$stmt = $conn->prepare("SELECT id, item_name, category, quantity FROM inventory WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();
$stmt->close();

if (!$item) {
    header("Location: admin_and_staff_dashboard.php?msg=notfound");
    exit();
}

// [SECTION: CALCULATE NEW QUANTITY]
$currentQty = (int)$item['quantity'];
if ($action === 'increase') {
    $newQty = $currentQty + $amount;
} else {
    // never allow quantity to go below zero
    $newQty = max(0, $currentQty - $amount);
}

// [SECTION: UPDATE + AUDIT LOG TOGETHER, AS ONE TRANSACTION]
// If the audit log insert failed after the quantity update succeeded
// (or vice versa), the two would fall out of sync - a stock change
// with no record of it. Wrapping both in a transaction means either
// BOTH succeed together, or NEITHER does.
$conn->begin_transaction();
$ok = true;

// [SECTION: UPDATE - SAVE NEW QUANTITY]
$update = $conn->prepare("UPDATE inventory SET quantity = ? WHERE id = ?");
$update->bind_param("ii", $newQty, $id);
$ok = $ok && $update->execute();
$update->close();

// [SECTION: AUDIT TRAIL - RECORD WHO CHANGED WHAT AND WHEN]
$adjustedById   = $_SESSION['admin_id'];
$adjustedByRole = $_SESSION['role'];
$adjustedByName = $_SESSION['admin_username'];
$log = $conn->prepare(
    "INSERT INTO stock_adjustments
        (item_id, item_name, category, action, quantity_before, quantity_after, adjusted_by_id, adjusted_by_role, adjusted_by_username)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$log->bind_param(
    "isssiiiss",
    $id, $item['item_name'], $item['category'], $action, $currentQty, $newQty, $adjustedById, $adjustedByRole, $adjustedByName
);
$ok = $ok && $log->execute();
$log->close();

if ($ok) {
    $conn->commit();
    $conn->close();
    // [SECTION: REDIRECT BACK TO THE DASHBOARD]
    header("Location: admin_and_staff_dashboard.php?msg=adjusted");
    exit();
} else {
    $conn->rollback();
    $conn->close();
    header("Location: admin_and_staff_dashboard.php?msg=dberror");
    exit();
}
?>
