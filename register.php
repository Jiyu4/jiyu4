<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$auth = new Auth();

if ($auth->isLoggedIn()) {
    $auth->redirectToDashboard();
}

$error  = '';
$values = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [
        'name'       => trim($_POST['name']       ?? ''),
        'email'      => trim($_POST['email']       ?? ''),
        'phone'      => trim($_POST['phone']       ?? ''),
        'dob'        => trim($_POST['dob']         ?? ''),
        'gender'     => trim($_POST['gender']      ?? ''),
        'address'    => trim($_POST['address']     ?? ''),
        'blood_type' => trim($_POST['blood_type']  ?? ''),
        'allergies'  => trim($_POST['allergies']   ?? ''),
        'password'   => $_POST['password']         ?? '',
        'confirm'    => $_POST['confirm']          ?? '',
    ];

    if (empty($values['name']) || empty($values['email']) || empty($values['password'])) {
        $error = 'Name, email and password are required.';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (empty($values['dob'])) {
        $error = 'Date of birth is required.';
    } elseif (empty($values['gender'])) {
        $error = 'Please select your gender.';
    } elseif (strlen($values['password']) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($values['password'] !== $values['confirm']) {
        $error = 'Passwords do not match.';
    } else {
        $user = $auth->createUser($values);
        if ($user === false) {
            $error = 'An account with that email already exists.';
        } else {
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
    <title>Patient Registration — <?= htmlspecialchars(CLINIC_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: var(--navy); display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; padding:24px 16px; }
        .reg-wrap { width:100%; max-width:560px; }
        .reg-card { background:#fff; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,.3); overflow:hidden; }
        .reg-hero  { background: linear-gradient(135deg, #0d8f7c 0%, #0f2240 100%); padding:28px 36px 22px; text-align:center; }
        .reg-hero .logo-icon { font-size:36px; display:block; margin-bottom:8px; }
        .reg-hero h1 { font-family:'DM Serif Display',serif; color:#fff; font-size:22px; margin:0 0 3px; }
        .reg-hero p  { color:rgba(255,255,255,.6); font-size:12.5px; margin:0; }
        .reg-body  { padding:28px 32px 32px; }
        .section-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); margin:20px 0 12px; padding-bottom:6px; border-bottom:1px solid var(--border); }
        .section-label:first-child { margin-top:0; }
        .form-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .form-full2 { grid-column:1/-1; }
        .fg { display:flex; flex-direction:column; gap:5px; }
        .fg label { font-size:13px; font-weight:500; color:var(--navy); }
        .fg input,.fg select,.fg textarea {
            padding:10px 13px; border:1.5px solid var(--border); border-radius:8px;
            font-size:14px; font-family:inherit; color:var(--text);
            transition:border-color .15s; width:100%;
        }
        .fg input:focus,.fg select:focus,.fg textarea:focus {
            outline:none; border-color:var(--teal); box-shadow:0 0 0 3px rgba(13,143,124,.1);
        }
        .req { color:var(--red); }
        .hint-box { background:var(--blue-light); border-radius:8px; padding:10px 14px; font-size:12.5px; color:#1e40af; margin-bottom:16px; }
        .alert { padding:12px 16px; border-radius:8px; font-size:13.5px; margin-bottom:16px; border-left:4px solid var(--red); background:var(--red-light); color:#7f1d1d; }
        .btn-register { width:100%; padding:13px; background:var(--teal); color:#fff; border:none; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; font-family:inherit; margin-top:20px; transition:background .15s; }
        .btn-register:hover { background:#0a7a6a; }
        .login-link { text-align:center; margin-top:16px; font-size:13.5px; color:var(--muted); }
        .login-link a { color:var(--teal); font-weight:500; }
    </style>
</head>
<body>
<div class="reg-wrap">
    <div class="reg-card">
        <div class="reg-hero">
            <span class="logo-icon">🏥</span>
            <h1>Patient Registration</h1>
            <p><?= htmlspecialchars(CLINIC_NAME) ?> · Book appointments online</p>
        </div>

        <div class="reg-body">
            <div class="hint-box">
                📋 Register to book and track your appointments at <?= htmlspecialchars(CLINIC_NAME) ?>.
                Your account gives you access to the <strong>patient portal</strong> only.
            </div>

            <?php if ($error): ?>
                <div class="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">

                <div class="section-label">Personal Information</div>
                <div class="form-grid2">
                    <div class="fg form-full2">
                        <label>Full Name <span class="req">*</span></label>
                        <input type="text" name="name" required autofocus
                               value="<?= htmlspecialchars($values['name'] ?? '') ?>"
                               placeholder="Juan dela Cruz">
                    </div>
                    <div class="fg">
                        <label>Date of Birth <span class="req">*</span></label>
                        <input type="date" name="dob" required
                               value="<?= htmlspecialchars($values['dob'] ?? '') ?>">
                    </div>
                    <div class="fg">
                        <label>Gender <span class="req">*</span></label>
                        <select name="gender" required>
                            <option value="">Select…</option>
                            <?php foreach (['Male','Female','Other'] as $g): ?>
                                <option value="<?= $g ?>" <?= ($values['gender']??'')===$g?'selected':'' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fg">
                        <label>Blood Type</label>
                        <select name="blood_type">
                            <option value="">Unknown</option>
                            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                                <option value="<?= $bt ?>" <?= ($values['blood_type']??'')===$bt?'selected':'' ?>><?= $bt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fg">
                        <label>Allergies</label>
                        <input type="text" name="allergies"
                               value="<?= htmlspecialchars($values['allergies'] ?? '') ?>"
                               placeholder="e.g. Penicillin, Peanuts">
                    </div>
                    <div class="fg form-full2">
                        <label>Home Address</label>
                        <input type="text" name="address"
                               value="<?= htmlspecialchars($values['address'] ?? '') ?>"
                               placeholder="Street, Barangay, City">
                    </div>
                </div>

                <div class="section-label">Contact & Login</div>
                <div class="form-grid2">
                    <div class="fg">
                        <label>Email Address <span class="req">*</span></label>
                        <input type="email" name="email" required
                               value="<?= htmlspecialchars($values['email'] ?? '') ?>"
                               placeholder="you@example.com">
                    </div>
                    <div class="fg">
                        <label>Phone <small style="color:var(--muted)">(for OTP login)</small></label>
                        <input type="tel" name="phone"
                               value="<?= htmlspecialchars($values['phone'] ?? '') ?>"
                               placeholder="09171234567">
                    </div>
                    <div class="fg">
                        <label>Password <span class="req">*</span></label>
                        <input type="password" name="password" required placeholder="Min 8 characters">
                    </div>
                    <div class="fg">
                        <label>Confirm Password <span class="req">*</span></label>
                        <input type="password" name="confirm" required placeholder="Repeat password">
                    </div>
                </div>

                <button type="submit" class="btn-register">✅ Create Patient Account</button>
            </form>

            <div class="login-link">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
