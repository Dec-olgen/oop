<?php
// [SECTION: INCLUDES - starts the session so we can destroy it]
require_once "includes/auth.php";

// [SECTION: DESTROY SESSION]
$_SESSION = [];
session_destroy();

// [SECTION: REDIRECT TO LOGIN]
header("Location: login.php");
exit();
?>
