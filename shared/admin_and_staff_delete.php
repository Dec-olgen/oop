<?php
// [SECTION: INCLUDES]
require_once "../includes/auth.php";
require_login();
require_any_role(['admin', 'staff']);
require_once "../includes/config.php";

// [SECTION: ONLY ACCEPT POST - a GET request alone can never delete anything]
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: admin_and_staff_dashboard.php");
    exit();
}

// [SECTION: GET ITEM ID + CONFIRMATION TEXT FROM THE FORM]
$id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$confirmText = isset($_POST['confirm_text']) ? trim($_POST['confirm_text']) : '';

// [SECTION: REAL SERVER-SIDE CONFIRMATION CHECK]
// This is the actual safeguard - a JS popup can be bypassed by
// disabling JS or crafting a request directly, but this check runs
// here regardless of how the request arrived, and rejects anything
// that doesn't include the exact typed confirmation.
if ($id <= 0 || $confirmText !== 'DELETE') {
    header("Location: admin_and_staff_delete_confirm.php?id=$id&msg=confirmneeded");
    exit();
}

if ($id > 0) {
    // [SUBSECTION: LOOK UP THE ITEM FIRST - needed for the audit log]
    $lookup = $conn->prepare("SELECT item_name, category, quantity FROM inventory WHERE id = ?");
    $lookup->bind_param("i", $id);
    $lookup->execute();
    $lookupResult = $lookup->get_result();
    $itemToDelete = $lookupResult->fetch_assoc();
    $lookup->close();

    if ($itemToDelete) {
        // [SECTION: DELETE + AUDIT LOG TOGETHER, AS ONE TRANSACTION]
        // If the log insert failed after the item was deleted (or vice
        // versa), the two would fall out of sync. Wrapping both in a
        // transaction means either BOTH succeed together, or NEITHER does.
        $conn->begin_transaction();
        $ok = true;

        // [SECTION: DELETE - REMOVE ITEM FROM DATABASE]
        $stmt = $conn->prepare("DELETE FROM inventory WHERE id = ?");
        $stmt->bind_param("i", $id);
        $ok = $ok && $stmt->execute();
        $stmt->close();

        // [SUBSECTION: AUDIT TRAIL - RECORD WHO DELETED THIS ITEM]
        $performedById   = $_SESSION['admin_id'];
        $performedByRole = $_SESSION['role'];
        $performedByName = $_SESSION['admin_username'];
        $logAction       = 'deleted';
        $detail          = "Quantity at deletion: " . (int)$itemToDelete['quantity'];
        $log = $conn->prepare(
            "INSERT INTO item_actions_log (item_id, item_name, category, action, detail, performed_by_id, performed_by_role, performed_by_username)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $log->bind_param("issssiss", $id, $itemToDelete['item_name'], $itemToDelete['category'], $logAction, $detail, $performedById, $performedByRole, $performedByName);
        $ok = $ok && $log->execute();
        $log->close();

        if ($ok) {
            $conn->commit();
            $conn->close();
            header("Location: admin_and_staff_dashboard.php?msg=deleted");
            exit();
        } else {
            $conn->rollback();
            $conn->close();
            header("Location: admin_and_staff_dashboard.php?msg=dberror");
            exit();
        }
    }
}

// [SECTION: REDIRECT BACK TO INVENTORY LIST - covers invalid/missing id]
$conn->close();
header("Location: admin_and_staff_dashboard.php?msg=notfound");
exit();
?>
