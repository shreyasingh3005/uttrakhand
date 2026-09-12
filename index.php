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
    <link href="<?php echo htmlspecialchars(site_url('assets/css/ui-modern.css'), ENT_QUOTES); ?>" rel="stylesheet">
    <style>
        :root {
            --primary: #ea580c;
            --primary-light: #f97316;
            --primary-dark: #c2410c;
            --accent: #d97706;
            --accent-light: #f59e0b;
            --surface: #161f30;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --border: rgba(255,255,255,.12);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            color: var(--text);
            background: #0b0f19;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 10% 20%, rgba(234,88,12,0.18) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 90% 80%, rgba(249,115,22,0.12) 0%, transparent 60%),
                radial-gradient(ellipse 50% 40% at 50% 50%, rgba(251,146,60,0.08) 0%, transparent 50%);
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
            width: min(1128px, 94vw);
            min-height: 664px;
            margin: 56px auto;
            border: 1px solid rgba(255,255,255,.14);
            border-radius: 22px;
            overflow: hidden;
            backdrop-filter: blur(20px);
            box-shadow: 0 32px 80px rgba(0,0,0,0.4);
            display: grid;
            grid-template-columns: 1.35fr 1fr;
            position: relative;
            z-index: 1;
        }
        .promo-pane {
            position: relative;
            padding: 54px 48px 46px;
            color: #e2e8f0;
            background: linear-gradient(160deg, #0f172a 0%, #111827 100%);
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
            background: radial-gradient(circle, rgba(234,88,12,0.15) 0%, transparent 70%);
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
            font-size: clamp(1.8rem, 3.1vw, 2.45rem);
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
            background: #161f30;
            padding: 48px 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card { width: 100%; max-width: 400px; }
        .login-title { font-size: 1.65rem; color: #f8fafc; font-weight: 800; letter-spacing: -0.03em; }
        .login-sub { color: var(--text-muted); margin-top: 8px; margin-bottom: 28px; font-size: 0.95rem; }
        .form-label { font-weight: 600; font-size: 0.8rem; color: #f8fafc; margin-bottom: 7px; letter-spacing: 0; display: block; }
        .form-group { margin-bottom: 18px; }
        .role-switch {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px;
            padding: 4px;
            margin-bottom: 28px;
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 12px;
            background: rgba(11,15,25,.42);
        }
        .role-option {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 39px;
            border-radius: 9px;
            color: #cbd5e1;
            font-size: .82rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s ease, color .2s ease, box-shadow .2s ease;
        }
        .role-option input { position: absolute; opacity: 0; pointer-events: none; }
        .role-option:has(input:checked) { background: #1e293b; color: #fff; box-shadow: 0 3px 8px rgba(0,0,0,.2); }
        .role-option:has(input:checked) i { color: #fb923c; }
        .field-shell { position: relative; }
        .field-shell > i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; z-index: 1; pointer-events: none; }
        .field-shell .form-control { padding-left: 44px; }
        .field-shell .form-control:focus + i { color: #fb923c; }
        .password-shell > i { left: 16px; }
        .password-shell .form-control { padding-right: 42px; }
        .password-shell .password-eye { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; }
        .form-control, .form-select {
            width: 100%;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,.14);
            padding: 12px 16px;
            font-size: 0.92rem;
            transition: all 0.2s ease;
            font-family: inherit;
            background: #1e293b;
            color: #f8fafc;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(234,88,12,0.25);
            outline: none;
            background: #1e293b;
        }
        .btn-login {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border: 0;
            border-radius: 12px;
            padding: 13px;
            color: #fff;
            font-weight: 700;
            width: 100%;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            font-family: inherit;
            position: relative;
            overflow: hidden;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(234,88,12,0.4); }
        .btn-login:active { transform: translateY(0); }
        .alert { border-radius: 12px; border: 1px solid rgba(248,113,113,.25); background: rgba(127,29,29,.24); color: #fca5a5; padding: 12px 16px; font-size: 0.86rem; display: flex; align-items: center; gap: 8px; margin-bottom: 18px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
        .login-card { animation: fadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1); }
        .security-note { margin-top: 52px; text-align: center; color: #94a3b8; font-size: .72rem; }
        .security-note i { color: #10b981; margin-right: 5px; }
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
                <h1 class="promo-title">Smarter Bookings, <span style="color:#fed7aa;">Seamless Quotations</span></h1>
                <p class="promo-copy">Comprehensive CRM engineered for Uttarakhand hotel properties, automated rate matrices, agent inquiry locks, and instant WhatsApp deals.</p>
                <ul class="promo-list">
                    <li><i class="bi bi-check2-circle"></i> Multi-room query matching with meal plans (EP, CP, MAP, AP)</li>
                    <li><i class="bi bi-check2-circle"></i> WhatsApp-ready quotation generator with UV-#### tracking</li>
                    <li><i class="bi bi-check2-circle"></i> Agent inquiry protection &amp; automated expiration timers</li>
                </ul>
            </div>
            <div class="promo-foot">Professional dashboard environment for Admin and Employee access.</div>
        </section>

        <section class="auth-pane">
            <div class="login-card">
                <h2 class="login-title">Welcome Back</h2>
                <p class="login-sub">Please enter your credentials to access your portal.</p>

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
                        <div class="role-switch">
                            <label class="role-option"><input type="radio" name="login_type" value="admin" checked required><i class="bi bi-shield-fill-check"></i> Administrator</label>
                            <label class="role-option"><input type="radio" name="login_type" value="employee" required><i class="bi bi-person-badge-fill"></i> Employee</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Username or ID <span style="color:#ef4444;">*</span></label>
                        <div class="field-shell">
                            <input type="text" class="form-control" name="username" required placeholder="Enter your username" autocomplete="username" maxlength="100">
                            <i class="bi bi-person"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password <span style="color:#ef4444;">*</span><a href="<?php echo htmlspecialchars(site_url('forgot-password.php'), ENT_QUOTES); ?>" style="float:right;color:#fb923c;text-decoration:none;font-size:.76rem;">Forgot Password?</a></label>
                        <div class="field-shell password-shell">
                            <input type="password" class="form-control" name="password" required placeholder="Enter your account password" autocomplete="current-password" maxlength="128">
                            <i class="bi bi-lock"></i>
                            <i class="bi bi-eye password-eye"></i>
                        </div>
                    </div>
                    <button class="btn-login" type="submit">
                        <i class="bi bi-box-arrow-in-right me-1"></i>
                        Sign In to Dashboard
                    </button>
                </form>
                <div class="security-note"><i class="bi bi-shield-check"></i>2026 Uttarakhand Ventures CRM &bull; Enterprise Secure Sign-In</div>
            </div>
        </section>
    </div>
</body>
</html>
