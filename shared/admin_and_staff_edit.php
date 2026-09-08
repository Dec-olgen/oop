<?php
// [SECTION: INCLUDES - require login before this page works]
require_once "../includes/auth.php";
require_login();
require_any_role(['admin', 'staff']);
require_once "../includes/config.php";

// [SECTION: GET ITEM ID FROM URL OR FORM]
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if ($id <= 0) {
    header("Location: admin_and_staff_dashboard.php");
    exit();
}

$errors = [];

// [SECTION: UPDATE - HANDLE FORM SUBMISSION]
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

        // [SUBSECTION: FETCH THE ITEM'S CURRENT VALUES - needed to show what changed]
        $lookup = $conn->prepare("SELECT * FROM inventory WHERE id = ?");
        $lookup->bind_param("i", $id);
        $lookup->execute();
        $oldItem = $lookup->get_result()->fetch_assoc();
        $lookup->close();

        // [SUBSECTION: BUILD A LIST OF WHAT ACTUALLY CHANGED]
        // Compared field by field so the audit log can show
        // "Quantity: 40 -> 35" instead of just "item was updated".
        $fieldLabels = [
            'item_name'   => 'Item Name',
            'sku'         => 'SKU',
            'category'    => 'Category',
            'quantity'    => 'Quantity',
            'unit'        => 'Unit',
            'brand_name'  => 'Brand Name',
            'expiry_date' => 'Expiry Date',
            'location'    => 'Location',
        ];
        $newValues = [
            'item_name' => $item_name, 'sku' => $sku, 'category' => $category,
            'quantity' => $quantity, 'unit' => $unit, 'brand_name' => $brand_name,
            'expiry_date' => $expiry_date, 'location' => $location,
        ];
        $changes = [];
        foreach ($fieldLabels as $field => $label) {
            $oldVal = $oldItem[$field] ?? '';
            $newVal = $newValues[$field] ?? '';
            // Normalize NULL/empty so "no value" doesn't falsely look like a change
            $oldVal = $oldVal === null ? '' : (string)$oldVal;
            $newVal = $newVal === null ? '' : (string)$newVal;
            if ($oldVal !== $newVal) {
                $oldDisplay = $oldVal === '' ? '(none)' : $oldVal;
                $newDisplay = $newVal === '' ? '(none)' : $newVal;
                $changes[] = "$label: $oldDisplay -> $newDisplay";
            }
        }
        $detail = empty($changes) ? 'No fields changed' : implode('; ', $changes);

        // [SUBSECTION: UPDATE + AUDIT LOG TOGETHER, AS ONE TRANSACTION]
        // If the audit log insert failed after the update succeeded (or
        // vice versa), the two would fall out of sync - a stock change
        // with no record of it, or a log entry for a change that never
        // actually saved. Wrapping both in a transaction means either
        // BOTH succeed together, or NEITHER does.
        $conn->begin_transaction();
        $ok = true;

        $sql = "UPDATE inventory
                SET item_name = ?, sku = ?, category = ?, quantity = ?, unit = ?, brand_name = ?, expiry_date = ?, location = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssissssi", $item_name, $sku, $category, $quantity, $unit, $brand_name, $expiry_date, $location, $id);
        $ok = $ok && $stmt->execute();
        $stmt->close();

        $performedById   = $_SESSION['admin_id'];
        $performedByRole = $_SESSION['role'];
        $performedByName = $_SESSION['admin_username'];
        $logAction       = 'updated';
        $log = $conn->prepare(
            "INSERT INTO item_actions_log (item_id, item_name, category, action, detail, performed_by_id, performed_by_role, performed_by_username)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $log->bind_param("issssiss", $id, $item_name, $category, $logAction, $detail, $performedById, $performedByRole, $performedByName);
        $ok = $ok && $log->execute();
        $log->close();

        if ($ok) {
            $conn->commit();
            $conn->close();
            // [SUBSECTION: REDIRECT AFTER SUCCESSFUL SAVE]
            header("Location: admin_and_staff_dashboard.php?msg=updated");
            exit();
        } else {
            $conn->rollback();
            $errors[] = "Something went wrong saving this item. Nothing was changed - please try again.";
        }
    }
}

// [SECTION: READ - LOAD THE ITEM TO PRE-FILL THE FORM]
$stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();

if (!$item) {
    header("Location: admin_and_staff_dashboard.php");
    exit();
}

// If the form was resubmitted with errors, keep what the user typed
$display = $_SERVER["REQUEST_METHOD"] === "POST" ? $_POST : $item;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Item - Ward Stock</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
<!-- [SECTION: SIDEBAR NAVIGATION] -->
<?php $adminNavPath = "../admin_only/"; include "admin_and_staff_sidebar.php"; ?>

<div class="container">
    <!-- [SECTION: PAGE HEADER] -->
    <header>
        <h1>Edit Item</h1>
        <p class="subtitle">Edit Inventory Item</p>
    </header>

    <!-- [SECTION: VALIDATION ERROR MESSAGES] -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- [SECTION: EDIT ITEM FORM] -->
    <form method="POST" class="form-card">
        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">

        <!-- [SUBSECTION: ITEM NAME] -->
        <label>Item Name *</label>
        <input type="text" name="item_name" value="<?= htmlspecialchars($display['item_name']) ?>" required>

        <!-- [SUBSECTION: SKU - only shown for Equipment / Training Model] -->
        <div id="sku-field">
            <label>SKU</label>
            <input type="text" name="sku" placeholder="e.g. EQP-1001, TRN-2001" value="<?= htmlspecialchars($display['sku'] ?? '') ?>">
        </div>

        <!-- [SUBSECTION: CATEGORY] -->
        <label>Category *</label>
        <input type="text" id="category-input" name="category" list="category-list" value="<?= htmlspecialchars($display['category']) ?>" required>
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
                <input type="number" name="quantity" min="0" value="<?= htmlspecialchars($display['quantity']) ?>" required>
            </div>
            <div>
                <label>Unit *</label>
                <input type="text" name="unit" value="<?= htmlspecialchars($display['unit']) ?>" required>
            </div>
        </div>

        <!-- [SUBSECTION: BRAND NAME] -->
        <label>Brand Name</label>
        <input type="text" name="brand_name" value="<?= htmlspecialchars($display['brand_name'] ?? '') ?>">

        <!-- [SUBSECTION: EXPIRY DATE + LOCATION] -->
        <div class="row">
            <div>
                <label id="expiry-label">Expiry Date</label>
                <input type="date" id="expiry-input" name="expiry_date" value="<?= htmlspecialchars($display['expiry_date'] ?? '') ?>">
            </div>
            <div>
                <label>Location / Shelf</label>
                <input type="text" name="location" value="<?= htmlspecialchars($display['location'] ?? '') ?>">
            </div>
        </div>

        <!-- [SUBSECTION: FORM ACTIONS] -->
        <div class="form-actions">
            <button type="submit" class="btn btn-add">Update Item</button>
            <a href="admin_and_staff_dashboard.php" class="btn btn-clear">Cancel</a>
        </div>
    </form>
</div>
</div>
<script src="../assets/js/script.js"></script>
</body>
</html>
<?php $stmt->close(); $conn->close(); ?>
