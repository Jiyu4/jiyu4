<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

$auth = new Auth();
$auth->requireLogin();
if ($auth->isAdmin()) { header('Location: ../index.php'); exit; }

$db      = new JsonDB();
$user    = $auth->currentUser();
$patient = $auth->getLinkedPatient((string)$user['id']);
$msg     = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        // Update user account
        $auth->updateUser((string)$user['id'], [
            'name'       => trim($_POST['name']       ?? $user['name']),
            'phone'      => trim($_POST['phone']       ?? $user['phone']),
            'dob'        => trim($_POST['dob']         ?? ''),
            'gender'     => trim($_POST['gender']      ?? ''),
            'address'    => trim($_POST['address']     ?? ''),
            'blood_type' => trim($_POST['blood_type']  ?? ''),
            'allergies'  => trim($_POST['allergies']   ?? ''),
        ]);

        // Also sync to patient record
        if ($patient) {
            $nameParts = explode(' ', trim($_POST['name'] ?? $user['name']), 2);
            $db->updatePatient((string)$patient['id'], [
                'first_name' => $nameParts[0],
                'last_name'  => $nameParts[1] ?? '',
                'phone'      => trim($_POST['phone']      ?? ''),
                'dob'        => trim($_POST['dob']        ?? $patient['dob']),
                'gender'     => trim($_POST['gender']     ?? $patient['gender']),
                'address'    => trim($_POST['address']    ?? ''),
                'blood_type' => trim($_POST['blood_type'] ?? ''),
                'allergies'  => trim($_POST['allergies']  ?? ''),
            ]);
        }

        $msg  = 'Profile updated successfully.';
        $user = $auth->currentUser();
        $patient = $auth->getLinkedPatient((string)$user['id']);
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $auth->updateUser((string)$user['id'], ['password' => $new]);
            $msg = 'Password changed successfully.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — <?= CLINIC_NAME ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'nav.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">My Profile</h1>
                <p class="page-subtitle">Update your personal information</p>
            </div>
        </div>

        <?php if ($msg):   ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="dashboard-grid">
            <!-- Personal Info -->
            <div class="card form-card">
                <div class="card-header"><h2 class="card-title">👤 Personal Information</h2></div>
                <form method="POST" style="margin-top:16px">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-grid">
                        <div class="form-group form-full">
                            <label>Full Name</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="dob" value="<?= htmlspecialchars($patient['dob'] ?? $user['dob'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender">
                                <option value="">Select…</option>
                                <?php foreach (['Male','Female','Other'] as $g): ?>
                                    <option value="<?= $g ?>" <?= ($patient['gender']??$user['gender']??'')===$g?'selected':'' ?>><?= $g ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Blood Type</label>
                            <select name="blood_type">
                                <option value="">Unknown</option>
                                <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                                    <option value="<?= $bt ?>" <?= ($patient['blood_type']??$user['blood_type']??'')===$bt?'selected':'' ?>><?= $bt ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group form-full">
                            <label>Address</label>
                            <input type="text" name="address" value="<?= htmlspecialchars($patient['address'] ?? $user['address'] ?? '') ?>">
                        </div>
                        <div class="form-group form-full">
                            <label>Allergies</label>
                            <input type="text" name="allergies"
                                   value="<?= htmlspecialchars($patient['allergies'] ?? $user['allergies'] ?? '') ?>"
                                   placeholder="e.g. Penicillin, None">
                        </div>
                        <div class="form-group form-full">
                            <label>Email <small style="color:var(--muted)">(cannot be changed)</small></label>
                            <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled style="background:#f8fafc;color:var(--muted)">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                    </div>
                </form>
            </div>

            <!-- Change Password -->
            <div class="card form-card">
                <div class="card-header"><h2 class="card-title">🔑 Change Password</h2></div>
                <form method="POST" style="margin-top:16px">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group" style="margin-bottom:14px">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group" style="margin-bottom:14px">
                        <label>New Password</label>
                        <input type="password" name="new_password" required placeholder="Min 8 characters">
                    </div>
                    <div class="form-group" style="margin-bottom:14px">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-secondary">🔒 Change Password</button>
                    </div>
                </form>

                <?php if ($patient): ?>
                <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border)">
                    <h3 style="font-size:13px;color:var(--muted);margin-bottom:12px">PATIENT RECORD</h3>
                    <div class="detail-grid" style="padding:0">
                        <div class="detail-item">
                            <span class="detail-label">Patient ID</span>
                            <span><code><?= htmlspecialchars($patient['patient_id']) ?></code></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Registered</span>
                            <span><?= htmlspecialchars(substr($patient['created_at'],0,10)) ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
