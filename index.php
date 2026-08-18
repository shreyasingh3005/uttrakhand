<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/security.php';

send_security_headers();

    if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') { redirect('/dashboard.php'); }
    if ($_SESSION['role'] === 'employee') { redirect('/employee-dashboard.php'); }
}
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Uttarakhand Ventures CRM</title>
    <meta name="description" content="Login to Uttarakhand Ventures CRM to manage hotel bookings, agents, and payments.">
    <link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars(site_url('assets/images/favicon.svg'), ENT_QUOTES); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --primary-dark: #4338ca;
            --accent: #06b6d4;
            --accent-light: #22d3ee;
            --surface: #ffffff;
            --text: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            color: var(--text);
            background: #0f172a;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 10% 20%, rgba(79,70,229,0.25) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 90% 80%, rgba(6,182,212,0.2) 0%, transparent 60%),
                radial-gradient(ellipse 50% 40% at 50% 50%, rgba(99,102,241,0.1) 0%, transparent 50%);
            pointer-events: none;
        }
        body::after {
            content: "";
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            mask-image: radial-gradient(circle at center, black 30%, transparent 80%);
            pointer-events: none;
        }
        .login-shell {
            width: min(1100px, 94vw);
            margin: 40px auto;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 28px;
            overflow: hidden;
            backdrop-filter: blur(20px);
            box-shadow: 0 32px 80px rgba(0,0,0,0.4);
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            min-height: 640px;
            position: relative;
            z-index: 1;
        }
        .promo-pane {
            position: relative;
            padding: 48px;
            color: #e2e8f0;
            background: linear-gradient(160deg, rgba(15,23,42,0.95) 0%, rgba(30,41,59,0.9) 100%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
        }
        .promo-pane::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(79,70,229,0.15) 0%, transparent 70%);
            pointer-events: none;
        }
        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            font-size: 0.88rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #94a3b8;
            position: relative;
            z-index: 1;
        }
        .brand-badge .brand-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff;
            font-size: 18px;
        }
        .promo-title {
            font-size: clamp(1.8rem, 3.5vw, 2.8rem);
            line-height: 1.15;
            margin: 32px 0 16px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.03em;
            position: relative;
            z-index: 1;
        }
        .promo-copy {
            max-width: 44ch;
            font-size: 1rem;
            color: #94a3b8;
            line-height: 1.7;
            position: relative;
            z-index: 1;
        }
        .promo-list {
            margin: 32px 0 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 12px;
            max-width: 440px;
            position: relative;
            z-index: 1;
        }
        .promo-list li {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 14px;
            background: rgba(255,255,255,0.05);
            color: #e2e8f0;
            font-size: 0.9rem;
            border: 1px solid rgba(255,255,255,0.06);
            transition: all 0.2s ease;
        }
        .promo-list li:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.12);
        }
        .promo-list i { color: var(--accent-light); font-size: 1.1rem; }
        .promo-foot { color: #64748b; font-size: 0.84rem; position: relative; z-index: 1; }
        .auth-pane {
            background: var(--surface);
            padding: 48px 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card { width: 100%; max-width: 400px; }
        .login-title { font-size: 1.6rem; color: var(--text); font-weight: 800; letter-spacing: -0.03em; }
        .login-sub { color: var(--text-muted); margin-top: 8px; margin-bottom: 28px; font-size: 0.95rem; }
        .form-label { font-weight: 600; font-size: 0.8rem; color: var(--text); margin-bottom: 7px; text-transform: uppercase; letter-spacing: 0.04em; display: block; }
        .form-group { margin-bottom: 18px; }
        .form-control, .form-select {
            width: 100%;
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 12px 16px;
            font-size: 0.92rem;
            transition: all 0.2s ease;
            font-family: inherit;
            background: #fff;
            color: var(--text);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
            outline: none;
        }
        .btn-login {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border: 0;
            border-radius: 14px;
            padding: 14px;
            color: #fff;
            font-weight: 700;
            width: 100%;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            font-family: inherit;
            position: relative;
            overflow: hidden;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(79,70,229,0.35); }
        .btn-login:active { transform: translateY(0); }
        .alert { border-radius: 12px; border: 1px solid rgba(239,68,68,0.15); background: #fef2f2; color: #991b1b; padding: 12px 16px; font-size: 0.86rem; display: flex; align-items: center; gap: 8px; margin-bottom: 18px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
        .login-card { animation: fadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1); }
        @media (max-width: 980px) {
            .login-shell { grid-template-columns: 1fr; min-height: unset; margin: 20px auto; }
            .promo-pane { padding: 36px 28px; }
            .auth-pane { padding: 32px 28px; }
        }
        @media (max-width: 540px) {
            .login-shell { width: min(96vw, 500px); border-radius: 20px; margin: 14px auto; }
            .promo-pane { padding: 28px 20px; }
            .auth-pane { padding: 24px 20px; }
            .promo-pane { display: none; }
            .login-card { max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="login-shell">
        <section class="promo-pane">
            <div>
                <div class="brand-badge">
                    <span class="brand-icon"><i class="bi bi-buildings"></i></span>
                    UTTARAKHAND VENTURES CRM
                </div>
                <h1 class="promo-title">Manage bookings faster with a cleaner control hub.</h1>
                <p class="promo-copy">One place for agents, hotel inventory, and payment tracking so your team can close bookings without jumping across screens.</p>
                <ul class="promo-list">
                    <li><i class="bi bi-check2-circle"></i> Live booking workflow with payment tracking</li>
                    <li><i class="bi bi-check2-circle"></i> Agent and company level record management</li>
                    <li><i class="bi bi-check2-circle"></i> Share-ready booking copy for WhatsApp</li>
                </ul>
            </div>
            <div class="promo-foot">Professional dashboard environment for Admin and Employee access.</div>
        </section>

        <section class="auth-pane">
            <div class="login-card">
                <h2 class="login-title">Sign In</h2>
                <p class="login-sub">Enter your credentials to continue.</p>

                <?php if ($error): ?>
                    <div class="alert">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars(site_url('process_login.php'), ENT_QUOTES); ?>">
                    <?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label class="form-label">Login Type</label>
                        <select class="form-select" name="login_type" required>
                            <option value="admin">Admin Login</option>
                            <option value="employee">Employee Login</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" required placeholder="Enter username" autocomplete="username" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required placeholder="Enter password" autocomplete="current-password" maxlength="128">
                    </div>
                    <button class="btn-login" type="submit">
                        <i class="bi bi-box-arrow-in-right me-1"></i>
                        Sign In
                    </button>
                </form>
            </div>
        </section>
    </div>
</body>
</html>
