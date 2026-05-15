<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/sms.php';

$auth    = new Auth();
$gateway = new SMSGateway();

// Already logged in → redirect
if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$next    = $_GET['next'] ?? 'index.php';
$tab     = $_POST['tab'] ?? $_GET['tab'] ?? 'password';
$error   = '';
$success = '';
$otpSent = false;
$otpPhone= '';

if (isset($_GET['registered'])) $success = 'Account created! Please sign in.';
if (isset($_GET['bye']))        $success = 'You have been signed out.';

// ── Handle POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Email + Password login
    if ($_POST['tab'] === 'password') {
        $result = $auth->loginWithPassword(
            trim($_POST['email'] ?? ''),
            $_POST['password'] ?? ''
        );
        if ($result['success']) {
            $auth->redirectToDashboard();
        }
        $error = $result['error'];
    }

    // OTP Step 1 — request code
    if ($_POST['tab'] === 'otp' && !empty($_POST['request_otp'])) {
        $phone  = trim($_POST['phone'] ?? '');
        $result = $auth->generateOtp($phone);
        if ($result['success']) {
            $smsResult = $gateway->send(
                $result['phone'],
                CLINIC_NAME . ' — Your login code is: ' . $result['code'] . '. Valid for 10 minutes.'
            );
            if ($smsResult['success']) {
                $otpSent  = true;
                $otpPhone = $phone;
                $success  = 'OTP sent to ' . $phone . '. Enter it below.';
            } else {
                $error = 'Could not send OTP SMS: ' . $smsResult['error'];
            }
        } else {
            $error = $result['error'];
        }
        $tab = 'otp';
    }

    // OTP Step 2 — verify code
    if ($_POST['tab'] === 'otp' && !empty($_POST['verify_otp'])) {
        $result = $auth->verifyOtp(
            trim($_POST['otp_phone'] ?? ''),
            trim($_POST['otp_code']  ?? '')
        );
        if ($result['success']) {
            $auth->redirectToDashboard();
        }
        $error    = $result['error'];
        $otpSent  = true;
        $otpPhone = trim($_POST['otp_phone'] ?? '');
        $tab      = 'otp';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= htmlspecialchars(CLINIC_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: var(--navy); display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
        .login-wrap { width:100%; max-width:440px; padding:24px; }
        .login-card { background:#fff; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,.3); overflow:hidden; }
        .login-hero  { background: linear-gradient(135deg, #0d8f7c 0%, #0f2240 100%); padding:36px 36px 28px; text-align:center; }
        .login-hero .logo-icon { font-size:44px; display:block; margin-bottom:10px; }
        .login-hero h1 { font-family:'DM Serif Display',serif; color:#fff; font-size:26px; margin:0 0 4px; }
        .login-hero p  { color:rgba(255,255,255,.6); font-size:13px; margin:0; }
        .login-body  { padding:32px 36px 36px; }
        .tab-bar     { display:flex; gap:0; margin-bottom:28px; border:1.5px solid var(--border); border-radius:8px; overflow:hidden; }
        .tab-btn     { flex:1; padding:10px; background:none; border:none; font-family:inherit; font-size:13.5px; font-weight:500; cursor:pointer; color:var(--muted); transition:all .15s; }
        .tab-btn.active { background:var(--navy); color:#fff; }
        .form-group  { margin-bottom:18px; }
        .form-group label { display:block; font-size:13px; font-weight:500; color:var(--navy); margin-bottom:6px; }
        .form-group input { width:100%; padding:11px 14px; border:1.5px solid var(--border); border-radius:8px; font-size:14px; font-family:inherit; transition:border-color .15s; }
        .form-group input:focus { outline:none; border-color:var(--teal); box-shadow:0 0 0 3px rgba(13,143,124,.1); }
        .btn-login   { width:100%; padding:13px; background:var(--teal); color:#fff; border:none; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; font-family:inherit; transition:background .15s; }
        .btn-login:hover { background:#0a7a6a; }
        .btn-login:disabled { opacity:.5; cursor:not-allowed; }
        .divider     { text-align:center; color:var(--muted); font-size:12px; margin:20px 0; position:relative; }
        .divider::before,.divider::after { content:''; position:absolute; top:50%; width:42%; height:1px; background:var(--border); }
        .divider::before { left:0; } .divider::after { right:0; }
        .register-link { text-align:center; margin-top:20px; font-size:13.5px; color:var(--muted); }
        .register-link a { color:var(--teal); font-weight:500; }
        .otp-badge   { background:var(--teal-light); color:var(--teal); border-radius:8px; padding:10px 14px; font-size:13px; margin-bottom:18px; }
        .resend-link { font-size:12px; color:var(--muted); text-align:center; margin-top:10px; }
        .resend-link a { color:var(--teal); cursor:pointer; }
        .alert { padding:12px 16px; border-radius:8px; font-size:13.5px; margin-bottom:20px; }
        .alert-error   { background:var(--red-light); color:#7f1d1d; border-left:4px solid var(--red); }
        .alert-success { background:var(--green-light); color:#14532d; border-left:4px solid var(--green); }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="login-hero">
            <span class="logo-icon">🏥</span>
            <h1><?= htmlspecialchars(CLINIC_NAME) ?></h1>
            <p>Clinical Management System</p>
        </div>

        <div class="login-body">
            <!-- Tab bar -->
            <div class="tab-bar">
                <button type="button" class="tab-btn <?= $tab === 'password' ? 'active' : '' ?>"
                        onclick="switchTab('password')">🔑 Password</button>
                <button type="button" class="tab-btn <?= $tab === 'otp' ? 'active' : '' ?>"
                        onclick="switchTab('otp')">📱 OTP via SMS</button>
            </div>

            <?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <!-- ── Tab 1: Email + Password ── -->
            <div id="tab-password" <?= $tab !== 'password' ? 'style="display:none"' : '' ?>>
                <form method="POST">
                    <input type="hidden" name="tab" value="password">
                    <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
                    <div class="form-group">
                        <label>Email address</label>
                        <input type="email" name="email" required autofocus
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               placeholder="you@example.com">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required placeholder="••••••••">
                    </div>
                    <button type="submit" class="btn-login">Sign In</button>
                </form>
            </div>

            <!-- ── Tab 2: OTP ── -->
            <div id="tab-otp" <?= $tab !== 'otp' ? 'style="display:none"' : '' ?>>

                <?php if (!$otpSent): ?>
                <!-- Step 1: Enter phone -->
                <form method="POST">
                    <input type="hidden" name="tab" value="otp">
                    <input type="hidden" name="request_otp" value="1">
                    <div class="form-group">
                        <label>Phone number linked to your account</label>
                        <input type="tel" name="phone" required
                               placeholder="e.g. 09171234567 or +639171234567"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn-login"
                        <?= !$gateway->isReachable() ? 'disabled' : '' ?>>
                        📤 Send OTP Code
                    </button>
                    <?php if (!$gateway->isReachable()): ?>
                        <p style="font-size:12px;color:var(--red);text-align:center;margin-top:8px">
                            ⚠️ TextBee SMS is not configured. OTP login unavailable.
                        </p>
                    <?php endif; ?>
                </form>

                <?php else: ?>
                <!-- Step 2: Enter OTP code -->
                <div class="otp-badge">
                    📱 A 6-digit code was sent to <strong><?= htmlspecialchars($otpPhone) ?></strong>
                </div>
                <form method="POST">
                    <input type="hidden" name="tab" value="otp">
                    <input type="hidden" name="verify_otp" value="1">
                    <input type="hidden" name="otp_phone" value="<?= htmlspecialchars($otpPhone) ?>">
                    <div class="form-group">
                        <label>Enter 6-digit OTP code</label>
                        <input type="text" name="otp_code" required autofocus
                               maxlength="6" placeholder="000000"
                               pattern="\d{6}"
                               style="font-size:24px;letter-spacing:8px;text-align:center">
                    </div>
                    <button type="submit" class="btn-login">✅ Verify &amp; Sign In</button>
                </form>
                <div class="resend-link">
                    Didn't receive it?
                    <a onclick="document.getElementById('resend-form').submit()">Resend OTP</a>
                </div>
                <form id="resend-form" method="POST" style="display:none">
                    <input type="hidden" name="tab" value="otp">
                    <input type="hidden" name="request_otp" value="1">
                    <input type="hidden" name="phone" value="<?= htmlspecialchars($otpPhone) ?>">
                </form>
                <?php endif; ?>

            </div>

            <div class="register-link">
                Don't have an account? <a href="register.php">Register here</a>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.getElementById('tab-password').style.display = tab === 'password' ? '' : 'none';
    document.getElementById('tab-otp').style.display      = tab === 'otp'      ? '' : 'none';
    document.querySelectorAll('.tab-btn').forEach((b, i) => {
        b.classList.toggle('active', (i === 0) === (tab === 'password'));
    });
}
</script>
</body>
</html>
