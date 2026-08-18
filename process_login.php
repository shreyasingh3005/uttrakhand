<?php
/**
 * Login Handler — Uttarakhand Ventures CRM
 * Includes: rate limiting, session regeneration, secure redirects
 */
session_start();
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/security.php';

send_security_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/index.php');
}

// Verify CSRF
verify_csrf();

$loginType = $_POST['login_type'] ?? '';
$username  = sanitize_string($_POST['username'] ?? '');
$password  = $_POST['password'] ?? '';

// Input validation
$errors = validate_required($_POST, [
    'username'   => ['required' => true, 'label' => 'Username', 'min_length' => 2, 'max_length' => 100],
    'password'   => ['required' => true, 'label' => 'Password', 'min_length' => 1, 'max_length' => 128],
    'login_type' => ['required' => true, 'label' => 'Login type', 'pattern' => '/^(admin|employee)$/'],
]);

if (!empty($errors)) {
    $first = reset($errors);
    redirect('/index.php?error=' . urlencode($first));
}

// Rate limiting
$rateCheck = check_login_rate_limit($username);
if (!$rateCheck['allowed']) {
    redirect('/index.php?error=' . urlencode($rateCheck['message']));
}

// Look up user
$stmt = $conn->prepare('SELECT id, username, email, password, role FROM users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !verify_password($password, $user['password'])) {
    record_failed_login($username);
    redirect('/index.php?error=Invalid username or password.');
}

if ($user['role'] !== $loginType) {
    redirect('/index.php?error=Selected login type does not match this account.');
}

// Regenerate session ID to prevent fixation
session_regenerate_id(true);

$_SESSION['user_id']   = $user['id'];
$_SESSION['username']  = $user['username'];
$_SESSION['email']     = $user['email'];
$_SESSION['role']      = $user['role'];
$_SESSION['login_at']  = time();

try {
    $conn->prepare('UPDATE users SET is_logged_in = 1, last_login_at = NOW() WHERE id = :id')
         ->execute([':id' => $user['id']]);
} catch (PDOException $e) {
    // Non-blocking
}

if ($user['role'] === 'admin') {
    redirect('/dashboard.php');
} else {
    redirect('/employee-dashboard.php');
}
