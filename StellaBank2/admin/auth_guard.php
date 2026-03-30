<?php
// ── Admin Authentication Guard ────────────────────────────────
// Include this file at the very top of every admin page.
// Automatically redirects to the admin login if the session is missing.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Convenience globals available in every admin page after this include
$adminId   = (int)    $_SESSION['admin_id'];
$adminName =          $_SESSION['admin_name'];
$adminRole =          $_SESSION['admin_role'];

/**
 * Call this inside any endpoint that only superadmins may reach.
 * Sends a 403 JSON response and halts execution for non-superadmins.
 */
function requireSuperAdmin() {
    if ($_SESSION['admin_role'] !== 'superadmin') {
        http_response_code(403);
        header("Content-Type: application/json");
        echo json_encode(["error" => "Forbidden: superadmin role required."]);
        exit();
    }
}
?>
