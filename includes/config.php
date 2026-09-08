<?php
// ============================================
// [SECTION: DATABASE CONNECTION SETTINGS]
// Default XAMPP values: host=localhost, user=root, password="" (empty)
// Change these if your setup is different.
// ============================================

$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "ward_stock";

// [SECTION: CONNECT TO DATABASE]
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// [SECTION: CONNECTION ERROR CHECK]
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// [SECTION: CHARACTER ENCODING]
// Make sure special characters (emoji, etc.) are stored correctly
$conn->set_charset("utf8mb4");

// ============================================
// [SECTION: LOW STOCK THRESHOLDS - one place to edit]
// Different categories naturally carry different quantities.
// You'll always have far fewer Equipment units than Consumables,
// so a single shared threshold doesn't make sense across all of them.
// Edit the numbers below to adjust what counts as "low" per category.
// ============================================
$LOW_STOCK_THRESHOLDS = [
    'Medicine'       => 20,
    'Consumable'     => 20,
    'Equipment'      => 3,
    'Linen'          => 15,
    'Training Model' => 2,
];
$LOW_STOCK_DEFAULT_THRESHOLD = 20; // used if a category isn't listed above

// [SECTION: LOW STOCK HELPER FUNCTION]
// Call this instead of hardcoding a number - e.g.
// $row['quantity'] <= get_low_stock_threshold($row['category'])
function get_low_stock_threshold($category) {
    global $LOW_STOCK_THRESHOLDS, $LOW_STOCK_DEFAULT_THRESHOLD;
    return $LOW_STOCK_THRESHOLDS[$category] ?? $LOW_STOCK_DEFAULT_THRESHOLD;
}
?>
