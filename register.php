<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$auth = new Auth();

if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error   = '';
$success = '';
$values  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [
        'name'     => trim($_POST['name']     ?? ''),
        'email'    => trim($_POST['email']    ?? ''),
        'phone'    => trim($_POST['phone']    ?? ''),
        'password' => $_POST['password']      ?? '',
        'confirm'  => $_POST['confirm']       ?? '',
    ];

    // Validate
    if (empty($values['name']) || empty($values['email']) || empty($values['password'])) {
        $error = 'Name, email and password are required.';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($values['password']) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($values['password'] !== $values['confirm']) {
        $error = 'Passwords do not match.';
    } else {
        $user = $auth->createUser($values);
        if ($user === false) {
            $error = 'An account with that email already exists.';
        } else {
            // Auto-login after register
            header('Location: login.php?registered=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — <?= htmlspecialchars(CLINIC_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: var(--navy); display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; padding:24px; }
        .login-wrap { width:100%; max-width:480px; }
        .login-card { background:#fff; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,.3); overflow:hidden; }
        .login-hero  { background: linear-gradient(135deg, #0d8f7c 0%, #0f2240 100%); padding:32px 36px 24px; text-align:center; }
        .login-hero .logo-icon { font-size:40px; display:block; margin-bottom:10px; }
        .login-hero h1 { font-family:'DM Serif Display',serif; color:#fff; font-size:24px; margin:0 0 4px; }
        .login-hero p  { color:rgba(255,255,255,.6); font-size:13px; margin:0; }
        .login-body  { padding:28px 36px 36px; }
        .form-group  { margin-bottom:16px; }
        .form-group label { display:block; font-size:13px; font-weight:500; color:var(--navy); margin-bottom:6px; }
        .form-group input { width:100%; padding:11px 14px; border:1.5px solid var(--border); border-radius:8px; font-size:14px; font-family:inherit; transition:border-color .15s; }
        .form-group input:focus { outline:none; border-color:var(--teal); box-shadow:0 0 0 3px rgba(13,143,124,.1); }
        .form-row    { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .btn-login   { width:100%; padding:13px; background:var(--teal); color:#fff; border:none; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; font-family:inherit; transition:background .15s; margin-top:6px; }
        .btn-login:hover { background:#0a7a6a; }
        .login-link  { text-align:center; margin-top:18px; font-size:13.5px; color:var(--muted); }
        .login-link a { color:var(--teal); font-weight:500; }
        .alert { padding:12px 16px; border-radius:8px; font-size:13.5px; margin-bottom:18px; }
        .alert-error { background:var(--red-light); color:#7f1d1d; border-left:4px solid var(--red); }
        .badge-role  { display:inline-block; background:var(--amber-light); color:var(--amber); padding:2px 10px; border-radius:999px; font-size:11px; font-weight:600; }
        .hint-box    { background:var(--blue-light); border-radius:8px; padding:10px 14px; font-size:12.5px; color:#1e40af; margin-bottom:18px; }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="login-hero">
            <span class="logo-icon">🏥</span>
            <h1>Create Account</h1>
            <p><?= htmlspecialchars(CLINIC_NAME) ?> · Clinical System</p>
        </div>

        <div class="login-body">

            <?php $users = $auth->getAllUsers(); ?>
            <?php if (empty($users)): ?>
                <div class="hint-box">
                    👑 You are the <strong>first user</strong> — your account will automatically be set as <strong>Administrator</strong>.
                </div>
            <?php else: ?>
                <div class="hint-box">
                    New accounts are created as <strong>Staff</strong> role. An admin can promote you later.
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Full Name <span style="color:var(--red)">*</span></label>
                    <input type="text" name="name" required autofocus
                           value="<?= htmlspecialchars($values['name'] ?? '') ?>"
                           placeholder="Dr. Juan dela Cruz">
                </div>
                <div class="form-group">
                    <label>Email Address <span style="color:var(--red)">*</span></label>
                    <input type="email" name="email" required
                           value="<?= htmlspecialchars($values['email'] ?? '') ?>"
                           placeholder="you@example.com">
                </div>
                <div class="form-group">
                    <label>Phone Number <small style="color:var(--muted)">(for OTP login)</small></label>
                    <input type="tel" name="phone"
                           value="<?= htmlspecialchars($values['phone'] ?? '') ?>"
                           placeholder="09171234567 or +639171234567">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Password <span style="color:var(--red)">*</span></label>
                        <input type="password" name="password" required
                               placeholder="Min 8 characters">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password <span style="color:var(--red)">*</span></label>
                        <input type="password" name="confirm" required placeholder="Repeat password">
                    </div>
                </div>
                <button type="submit" class="btn-login">🏥 Create Account</button>
            </form>

            <div class="login-link">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
