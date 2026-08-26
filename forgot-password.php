<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/mail.php';

send_security_headers();

$reset = $_SESSION['password_reset'] ?? [];
$stage = !empty($reset['verified_at']) ? 'password' : (!empty($reset['user_id']) ? 'verify' : 'request');
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'request') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $genericMessage = 'If an active admin account uses that email, a verification code has been sent.';
        unset($_SESSION['password_reset']);
        $rateKey = 'password-reset:' . ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') . ':' . hash('sha256', $email);
        $rateCheck = rate_limit($rateKey, 3, 900);

        if (!$rateCheck['allowed'] || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = $genericMessage;
            $messageType = 'success';
        } else {
            $stmt = $conn->prepare('SELECT id, email FROM users WHERE email = :email AND role = :role LIMIT 1');
            $stmt->execute([':email' => $email, ':role' => 'admin']);
            $admin = $stmt->fetch();
            $sent = false;

            if ($admin) {
                $otp = (string) random_int(100000, 999999);
                $otpHash = password_hash($otp, PASSWORD_BCRYPT);
                $update = $conn->prepare('UPDATE users SET reset_otp = :otp, otp_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE), otp_attempts = 0, otp_requested_at = NOW() WHERE id = :id AND role = :role');
                $update->execute([':otp' => $otpHash, ':id' => $admin['id'], ':role' => 'admin']);

                try {
                    send_smtp_mail(
                        $admin['email'],
                        'Admin password reset code',
                        "Your Uttarakhand Ventures CRM password reset code is: {$otp}\n\nThis code expires in 10 minutes and can be used only once. If you did not request this, you can ignore this email."
                    );
                    $sent = true;
                } catch (Throwable $e) {
                    error_log('Password reset email failed: ' . $e->getMessage());
                    $conn->prepare('UPDATE users SET reset_otp = NULL, otp_expires_at = NULL, otp_attempts = 0, otp_requested_at = NULL WHERE id = :id')->execute([':id' => $admin['id']]);
                }
            }

            if ($sent) {
                $_SESSION['password_reset'] = ['user_id' => (int) $admin['id'], 'email' => $admin['email']];
                $stage = 'verify';
            } else {
                $stage = 'request';
            }
            $message = $genericMessage;
            $messageType = 'success';
        }
    } elseif ($action === 'verify' && !empty($reset['user_id'])) {
        $otp = trim((string) ($_POST['otp'] ?? ''));
        $stage = 'verify';
        if (!preg_match('/^\d{6}$/', $otp)) {
            $message = 'Enter the 6-digit verification code.';
            $messageType = 'danger';
        } else {
            $stmt = $conn->prepare('SELECT id, reset_otp, otp_expires_at, otp_attempts FROM users WHERE id = :id AND role = :role LIMIT 1');
            $stmt->execute([':id' => (int) $reset['user_id'], ':role' => 'admin']);
            $admin = $stmt->fetch();
            $attempts = (int) ($admin['otp_attempts'] ?? 5);
            $expired = !$admin || empty($admin['otp_expires_at']) || strtotime($admin['otp_expires_at']) < time();

            if (!$admin || $attempts >= 5 || $expired || empty($admin['reset_otp'])) {
                $message = 'This code is invalid or expired. Request a new code.';
                $messageType = 'danger';
            } else {
                $conn->prepare('UPDATE users SET otp_attempts = otp_attempts + 1 WHERE id = :id AND otp_attempts < 5')->execute([':id' => $admin['id']]);
                if (!password_verify($otp, $admin['reset_otp'])) {
                    $message = 'This code is invalid or expired. Request a new code.';
                    $messageType = 'danger';
                } else {
                    $_SESSION['password_reset']['verified_at'] = time();
                    $stage = 'password';
                    $message = 'Code verified. Choose a new password.';
                }
            }
        }
    } elseif ($action === 'reset' && !empty($reset['user_id']) && !empty($reset['verified_at'])) {
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $stage = 'password';
        if (time() - (int) $reset['verified_at'] > 600) {
            $message = 'Verification expired. Request a new code.';
            $messageType = 'danger';
            unset($_SESSION['password_reset']);
            $stage = 'request';
        } elseif (strlen($newPassword) < 8 || strlen($newPassword) > 128 || $newPassword !== $confirmPassword) {
            $message = 'Passwords must match and be between 8 and 128 characters.';
            $messageType = 'danger';
        } else {
            $stmt = $conn->prepare('UPDATE users SET password = :password, reset_otp = NULL, otp_expires_at = NULL, otp_attempts = 0, otp_requested_at = NULL WHERE id = :id AND role = :role');
            $stmt->execute([':password' => password_hash($newPassword, PASSWORD_BCRYPT), ':id' => (int) $reset['user_id'], ':role' => 'admin']);
            unset($_SESSION['password_reset']);
            redirect('/index.php?error=' . urlencode('Password reset successfully. Please sign in.'));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Admin Password - Uttarakhand Ventures CRM</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars(site_url('assets/images/favicon.svg'), ENT_QUOTES); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(site_url('assets/css/ui-modern.css'), ENT_QUOTES); ?>" rel="stylesheet">
    <style>
        :root { --primary:#4f46e5; --primary-light:#6366f1; --text:#0f172a; --muted:#64748b; --border:#e2e8f0; }
        * { box-sizing:border-box; }
        body { min-height:100vh; margin:0; display:flex; align-items:center; justify-content:center; padding:20px; font-family:'Inter','Segoe UI',system-ui,sans-serif; color:var(--text); background:#0f172a; }
        .reset-shell { width:min(460px,100%); padding:42px 38px; border-radius:24px; background:#fff; box-shadow:0 24px 70px rgba(0,0,0,.32); animation:fadeIn .4s ease both; }
        .brand { display:flex; align-items:center; gap:10px; color:var(--primary); font-weight:800; font-size:.8rem; letter-spacing:.06em; text-transform:uppercase; margin-bottom:30px; }
        .brand i { font-size:1.4rem; }
        h1 { margin:0; font-size:1.55rem; font-weight:800; }
        .sub { color:var(--muted); margin:8px 0 26px; font-size:.92rem; line-height:1.5; }
        label { display:block; margin-bottom:7px; font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
        input { width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:12px; font:inherit; margin-bottom:17px; }
        input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(79,70,229,.1); }
        button { width:100%; padding:13px; border:0; border-radius:13px; color:#fff; background:linear-gradient(135deg,var(--primary),var(--primary-light)); font:inherit; font-weight:700; cursor:pointer; }
        .alert { padding:11px 13px; border-radius:11px; margin-bottom:18px; font-size:.86rem; background:#ecfdf5; color:#047857; }
        .alert-danger { background:#fef2f2; color:#b91c1c; }
        .back { display:block; text-align:center; margin-top:20px; color:var(--primary); text-decoration:none; font-size:.86rem; font-weight:600; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }
        @media (max-width:480px) { .reset-shell { padding:32px 24px; } }
    </style>
</head>
<body>
    <main class="reset-shell">
        <div class="brand"><i class="bi bi-buildings"></i> Uttarakhand Ventures CRM</div>
        <h1><?php echo $stage === 'request' ? 'Reset admin password' : ($stage === 'verify' ? 'Verify your email' : 'Choose a new password'); ?></h1>
        <p class="sub"><?php echo $stage === 'request' ? 'Enter the admin account email to receive a one-time verification code.' : ($stage === 'verify' ? 'Enter the 6-digit code sent to your admin email.' : 'Your new password must be 8 to 128 characters.'); ?></p>
        <?php if ($message): ?><div class="alert<?php echo $messageType === 'danger' ? ' alert-danger' : ''; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

        <?php if ($stage === 'request'): ?>
            <form method="post">
                <?php echo csrf_field(); ?><input type="hidden" name="action" value="request">
                <label for="email">Admin email</label><input id="email" type="email" name="email" required autocomplete="email" maxlength="255">
                <button type="submit"><i class="bi bi-envelope me-1"></i> Send verification code</button>
            </form>
        <?php elseif ($stage === 'verify'): ?>
            <form method="post">
                <?php echo csrf_field(); ?><input type="hidden" name="action" value="verify">
                <label for="otp">Verification code</label><input id="otp" type="text" name="otp" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code">
                <button type="submit"><i class="bi bi-shield-check me-1"></i> Verify code</button>
            </form>
        <?php else: ?>
            <form method="post">
                <?php echo csrf_field(); ?><input type="hidden" name="action" value="reset">
                <label for="new_password">New password</label><input id="new_password" type="password" name="new_password" required minlength="8" maxlength="128" autocomplete="new-password">
                <label for="confirm_password">Confirm password</label><input id="confirm_password" type="password" name="confirm_password" required minlength="8" maxlength="128" autocomplete="new-password">
                <button type="submit"><i class="bi bi-key me-1"></i> Update password</button>
            </form>
        <?php endif; ?>
        <a class="back" href="<?php echo htmlspecialchars(site_url('index.php'), ENT_QUOTES); ?>"><i class="bi bi-arrow-left me-1"></i> Back to sign in</a>
    </main>
</body>
</html>
