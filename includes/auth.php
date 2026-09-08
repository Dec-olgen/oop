<?php
// ============================================
// [SECTION: AUTH HELPER]
// Include this at the very top of any page that should require login.
// It must run before any HTML is echoed (session_start rule).
// ============================================

// [SECTION: START SESSION]
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// [SECTION: LOGIN CHECK FUNCTION]
// Call this on any page that only logged-in admins should see.
// Pages calling this always live one folder deep (shared/ or
// admin_only/), so the redirect goes up one level to reach login.php.
function require_login() {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: ../login.php");
        exit();
    }
}

// [SECTION: ROLE CHECK FUNCTION]
// Call this AFTER require_login() on pages that should only be
// reachable by a specific role, e.g. require_role('admin').
// Blocks direct URL access even if someone is logged in as staff.
function require_role($requiredRole) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $requiredRole) {
        access_denied();
    }
}

// [SECTION: MULTI-ROLE CHECK FUNCTION]
// Same idea, but for pages more than one role is allowed to reach,
// e.g. require_any_role(['admin', 'staff']) on the shared inventory pages.
function require_any_role($allowedRoles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
        access_denied();
    }
}

// [SECTION: SHARED ACCESS DENIED PAGE]
// Also only ever triggered from shared/ or admin_only/ pages,
// so these paths go up one level too.
function access_denied() {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          <title>Access Denied - Ward Stock</title>
          <link rel="stylesheet" href="../assets/css/style.css"></head><body>
          <div class="auth-wrap"><div class="auth-card">
            <div class="sidebar-brand auth-brand">
                <span class="sidebar-logo">🚫</span>
                <span class="sidebar-title" style="color:#dc2626;">Access Denied</span>
            </div>
            <p style="margin:10px 0 18px; color:#374151; font-size:14px;">
                Your account does not have permission to view this page.
            </p>
            <a href="../logout.php" class="btn btn-add" style="display:inline-block;">Log Out</a>
          </div></div></body></html>';
    exit();
}
?>
