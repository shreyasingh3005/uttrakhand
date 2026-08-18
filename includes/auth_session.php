<?php
/**
 * Session security helpers — Uttarakhand Ventures CRM
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login() {
    if (empty($_SESSION['user_id'])) {
        redirect('/index.php');
    }
    // Enforce session timeout (8 hours)
    if (isset($_SESSION['login_at']) && time() - $_SESSION['login_at'] > 28800) {
        session_unset();
        session_destroy();
        redirect('/index.php?error=Session expired. Please log in again.');
    }
}

function require_role($role) {
    require_login();
    if (($_SESSION['role'] ?? '') !== $role) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><title>Access Denied</title><style>body{font-family:Inter,system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f8fafc;color:#0f172a;}h1{font-size:1.5rem;}</style></head><body><h1>Access Denied — You do not have permission to view this page.</h1></body></html>';
        exit();
    }
}

function require_role_any(array $roles) {
    require_login();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><title>Access Denied</title><style>body{font-family:Inter,system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f8fafc;color:#0f172a;}h1{font-size:1.5rem;}</style></head><body><h1>Access Denied — You do not have permission to view this page.</h1></body></html>';
        exit();
    }
}

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user_role(): string {
    return $_SESSION['role'] ?? '';
}
