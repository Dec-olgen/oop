<?php
// [SECTION: INCLUDES - require login before this page works]
require_once "../includes/auth.php";
require_login();
require_any_role(['admin', 'staff']);
require_once "../includes/config.php";

// [SECTION: GET ITEM ID FROM URL]
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if ($id <= 0) {
    header("Location: admin_and_staff_dashboard.php");
    exit();
}

// [SECTION: LOOK UP THE ITEM - needed to show what's about to be deleted]
$stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    $conn->close();
    header("Location: admin_and_staff_dashboard.php?msg=notfound");
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
<title>Confirm Delete - Ward Stock</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
<!-- [SECTION: SIDEBAR NAVIGATION] -->
<?php $adminNavPath = "../admin_only/"; include "admin_and_staff_sidebar.php"; ?>

<div class="container">
    <!-- [SECTION: PAGE HEADER] -->
    <header>
        <h1>Confirm Delete</h1>
        <p class="subtitle">This action is permanent and cannot be undone</p>
    </header>

    <?php if ($error !== ""): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- [SECTION: ITEM SUMMARY - what's about to be deleted] -->
    <div class="confirm-card">
        <p><strong>Item:</strong> <?= htmlspecialchars($item['item_name']) ?></p>
        <p><strong>Category:</strong> <?= htmlspecialchars($item['category']) ?></p>
        <p><strong>Quantity:</strong> <?= (int)$item['quantity'] ?> <?= htmlspecialchars($item['unit']) ?></p>
        <p><strong>Location:</strong> <?= htmlspecialchars($item['location'] ?? '-') ?></p>
    </div>

    <!-- [SECTION: TYPE-TO-CONFIRM FORM]
         The real safeguard - a JS popup can be bypassed, but this
         requires an explicit, deliberate action typed on this page,
         and the target file also checks it server-side. -->
    <form method="POST" action="admin_and_staff_delete.php" class="form-card">
        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">

        <label>Type <strong>DELETE</strong> to confirm</label>
        <input type="text" name="confirm_text" placeholder="DELETE" autocomplete="off" required>

        <div class="form-actions">
            <button type="submit" class="btn btn-delete">Permanently Delete Item</button>
            <a href="admin_and_staff_dashboard.php" class="btn btn-clear">Cancel</a>
        </div>
    </form>
</div>
</div>
</body>
</html>
