<?php
// [SECTION: INCLUDES - require login before this page works]
require_once "../includes/auth.php";
require_login();
require_any_role(['admin', 'staff']);
require_once "../includes/config.php";

$errors = [];

// [SECTION: HANDLE FORM SUBMISSION]
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // [SUBSECTION: COLLECT FORM INPUT]
    $item_name   = trim($_POST['item_name']);
    $sku         = trim($_POST['sku']);
    $category    = trim($_POST['category']);
    $quantity    = trim($_POST['quantity']);
    $unit        = trim($_POST['unit']);
    $brand_name  = trim($_POST['brand_name']);
    $expiry_date = trim($_POST['expiry_date']);
    $location    = trim($_POST['location']);

    // [SUBSECTION: BASIC VALIDATION]
    if ($item_name === "") $errors[] = "Item name is required.";
    if ($category === "")  $errors[] = "Category is required.";
    if ($quantity === "" || !is_numeric($quantity) || $quantity < 0) $errors[] = "Quantity must be a valid non-negative number.";
    if ($unit === "") $errors[] = "Unit is required.";

    // [SUBSECTION: CATEGORY-SPECIFIC RULE - Medicine/Consumable need an expiry date]
    $expiryRequiredCategories = ["Medicine", "Consumable"];
    if (in_array($category, $expiryRequiredCategories) && $expiry_date === "") {
        $errors[] = "Expiry date is required for Medicine and Consumable items.";
    }

    if (empty($errors)) {
        // Empty optional fields are stored as NULL instead of blank text
        // SKU only applies to Equipment / Training Model - force it to
        // NULL for any other category, even if one was submitted anyway
        // (e.g. a tampered request bypassing the JS field hiding).
        $skuApplicableCategories = ["Equipment", "Training Model"];
        $sku = (in_array($category, $skuApplicableCategories) && $sku !== "") ? $sku : null;
        $expiry_date = $expiry_date === "" ? null : $expiry_date;
        $brand_name  = $brand_name === "" ? null : $brand_name;
        $location    = $location === "" ? null : $location;

        // [SUBSECTION: CREATE + AUDIT LOG TOGETHER, AS ONE TRANSACTION]
        // If the log insert failed after the item was created (or vice
        // versa), the two would fall out of sync. Wrapping both in a
        // transaction means either BOTH succeed together, or NEITHER does.
        $conn->begin_transaction();
        $ok = true;

        // [SUBSECTION: CREATE - INSERT NEW ITEM INTO DATABASE]
        $sql = "INSERT INTO inventory (item_name, sku, category, quantity, unit, brand_name, expiry_date, location)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssissss", $item_name, $sku, $category, $quantity, $unit, $brand_name, $expiry_date, $location);
        $ok = $ok && $stmt->execute();
        $newItemId = $conn->insert_id;
        $stmt->close();

        // [SUBSECTION: AUDIT TRAIL - RECORD WHO CREATED THIS ITEM]
        $performedById   = $_SESSION['admin_id'];
        $performedByRole = $_SESSION['role'];
        $performedByName = $_SESSION['admin_username'];
        $logAction       = 'created';
        $detail          = "Quantity: $quantity $unit" . ($location ? "; Location: $location" : "");
        $log = $conn->prepare(
            "INSERT INTO item_actions_log (item_id, item_name, category, action, detail, performed_by_id, performed_by_role, performed_by_username)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $log->bind_param("issssiss", $newItemId, $item_name, $category, $logAction, $detail, $performedById, $performedByRole, $performedByName);
        $ok = $ok && $log->execute();
        $log->close();

        if ($ok) {
            $conn->commit();
            $conn->close();
            // [SUBSECTION: REDIRECT AFTER SUCCESSFUL SAVE]
            header("Location: admin_and_staff_dashboard.php?msg=created");
            exit();
        } else {
            $conn->rollback();
            $errors[] = "Something went wrong saving this item. Nothing was added - please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Item - Ward Stock</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
<!-- [SECTION: SIDEBAR NAVIGATION] -->
<?php $adminNavPath = "../admin_only/"; include "admin_and_staff_sidebar.php"; ?>

<div class="container">
    <!-- [SECTION: PAGE HEADER] -->
    <header>
        <h1>Add New Item</h1>
        <p class="subtitle">Add New Inventory Item</p>
    </header>

    <!-- [SECTION: VALIDATION ERROR MESSAGES] -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- [SECTION: ADD ITEM FORM] -->
    <form method="POST" class="form-card">
        <!-- [SUBSECTION: ITEM NAME] -->
        <label>Item Name *</label>
        <input type="text" name="item_name" value="<?= htmlspecialchars($_POST['item_name'] ?? '') ?>" required>

        <!-- [SUBSECTION: SKU - only shown for Equipment / Training Model] -->
        <div id="sku-field">
            <label>SKU</label>
            <input type="text" name="sku" placeholder="e.g. EQP-1001, TRN-2001" value="<?= htmlspecialchars($_POST['sku'] ?? '') ?>">
        </div>

        <!-- [SUBSECTION: CATEGORY] -->
        <label>Category *</label>
        <input type="text" id="category-input" name="category" list="category-list" value="<?= htmlspecialchars($_POST['category'] ?? '') ?>" required>
        <datalist id="category-list">
            <option value="Medicine">
            <option value="Consumable">
            <option value="Equipment">
            <option value="Linen">
            <option value="Training Model">
        </datalist>

        <!-- [SUBSECTION: QUANTITY + UNIT] -->
        <div class="row">
            <div>
                <label>Quantity *</label>
                <input type="number" name="quantity" min="0" value="<?= htmlspecialchars($_POST['quantity'] ?? '0') ?>" required>
            </div>
            <div>
                <label>Unit *</label>
                <input type="text" name="unit" placeholder="e.g. pcs, boxes, tablets" value="<?= htmlspecialchars($_POST['unit'] ?? '') ?>" required>
            </div>
        </div>

        <!-- [SUBSECTION: BRAND NAME] -->
        <label>Brand Name</label>
        <input type="text" name="brand_name" value="<?= htmlspecialchars($_POST['brand_name'] ?? '') ?>">

        <!-- [SUBSECTION: EXPIRY DATE + LOCATION] -->
        <div class="row">
            <div>
                <label id="expiry-label">Expiry Date</label>
                <input type="date" id="expiry-input" name="expiry_date" value="<?= htmlspecialchars($_POST['expiry_date'] ?? '') ?>">
            </div>
            <div>
                <label>Location / Shelf</label>
                <input type="text" name="location" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
            </div>
        </div>

        <!-- [SUBSECTION: FORM ACTIONS] -->
        <div class="form-actions">
            <button type="submit" class="btn btn-add">Save Item</button>
            <a href="admin_and_staff_dashboard.php" class="btn btn-clear">Cancel</a>
        </div>
    </form>
</div>
</div>
<script src="../assets/js/script.js"></script>
</body>
</html>
