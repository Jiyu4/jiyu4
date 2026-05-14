<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/sms.php';

$auth = new Auth();
$auth->requireLogin();
$db        = new JsonDB();
$gateway   = new SMSGateway();
$patients  = $db->getAllPatients();
$smsLogs   = $db->getSmsLogs();
$preselect = $_GET['patient_id'] ?? '';
$msg       = '';
$error     = '';
$isUp      = $gateway->isReachable();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['sms_type'] ?? '';

    if ($type === 'custom') {
        if (empty($_POST['patient_id']) || empty($_POST['message'])) {
            $error = 'Please select a patient and enter a message.';
        } else {
            $result = $gateway->sendCustom($_POST['patient_id'], $_POST['message']);
            $msg    = $result['success'] ? 'SMS sent successfully!' : 'Failed: ' . $result['error'];
        }
    }

    if ($type === 'bulk_reminder') {
        $appts  = $db->getTodayAppointments();
        $sent   = 0;
        $failed = 0;
        foreach ($appts as $a) {
            if (!$a['sms_sent']) {
                $r = $gateway->sendAppointmentReminder($a['id']);
                $r['success'] ? $sent++ : $failed++;
            }
        }
        $msg = "Bulk send complete: {$sent} sent, {$failed} failed.";
    }

    $smsLogs = $db->getSmsLogs();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS — <?= CLINIC_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">SMS Messaging</h1>
                <p class="page-subtitle">Send messages via your local SMS Gateway</p>
            </div>
        </div>

        <!-- TextBee status banner -->
        <div class="alert <?= $isUp ? 'alert-success' : 'alert-error' ?>">
            <?php if ($isUp): ?>
                ✅ TextBee is <strong>configured</strong> and ready to send SMS via
                <a href="https://textbee.dev/dashboard" target="_blank">textbee.dev</a>.
                Device ID: <code><?= htmlspecialchars($gateway->getDeviceId()) ?></code>
            <?php else: ?>
                ❌ TextBee is <strong>not configured</strong>. Go to
                <a href="settings.php">Settings</a> and enter your <strong>API Key</strong> and <strong>Device ID</strong>.
                <br><small>Get them from <a href="https://textbee.dev/dashboard" target="_blank">textbee.dev/dashboard</a> after installing the Android app.</small>
            <?php endif; ?>
        </div>

        <?php if ($msg):  ?>
            <div class="alert <?= str_contains($msg,'fail')||str_contains($msg,'Failed') ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="dashboard-grid">

            <!-- Custom SMS -->
            <div class="card">
                <div class="card-header"><h2 class="card-title">✉️ Send Custom SMS</h2></div>
                <div style="padding:20px">
                    <form method="POST">
                        <input type="hidden" name="sms_type" value="custom">
                        <div class="form-group" style="margin-bottom:14px">
                            <label>Patient</label>
                            <select name="patient_id" required>
                                <option value="">Select patient…</option>
                                <?php foreach ($patients as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= $preselect === $p['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['first_name'].' '.$p['last_name'].' ('.$p['phone'].')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:14px">
                            <label>Message</label>
                            <textarea name="message" rows="5" maxlength="1000"
                                placeholder="Type your message here…" required></textarea>
                            <small class="field-hint">Max 1,000 characters</small>
                        </div>
                        <button type="submit" class="btn btn-primary"
                            <?= !$isUp ? 'disabled title="Gateway offline"' : '' ?>>
                            📤 Send SMS
                        </button>
                    </form>
                </div>
            </div>

            <!-- Bulk Reminders -->
            <div class="card">
                <div class="card-header"><h2 class="card-title">📅 Today's Bulk Reminders</h2></div>
                <div style="padding:20px">
                    <?php $todayAppts = $db->getTodayAppointments(); ?>
                    <?php $unsent = array_filter($todayAppts, fn($a) => !$a['sms_sent']); ?>

                    <p style="margin-bottom:14px">
                        Today has <strong><?= count($todayAppts) ?></strong> appointment(s).
                        <strong><?= count($unsent) ?></strong> reminder(s) not yet sent.
                    </p>

                    <?php if (!empty($todayAppts)): ?>
                        <div class="appointment-list" style="margin-bottom:16px;border:1px solid var(--border);border-radius:8px;overflow:hidden">
                            <?php foreach ($todayAppts as $a): ?>
                                <div class="appt-item">
                                    <div class="appt-time"><?= htmlspecialchars($a['time']) ?></div>
                                    <div class="appt-info">
                                        <strong><?= htmlspecialchars($a['patient_name']) ?></strong>
                                        <span><?= htmlspecialchars($a['reason']) ?></span>
                                    </div>
                                    <?= $a['sms_sent']
                                        ? '<span class="badge badge-completed">Sent ✓</span>'
                                        : '<span class="badge badge-pending">Pending</span>' ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="padding:24px">
                            <span class="empty-icon">📅</span><p>No appointments today</p>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="sms_type" value="bulk_reminder">
                        <button type="submit" class="btn btn-secondary"
                            <?= (!$isUp || empty($unsent)) ? 'disabled' : '' ?>>
                            📨 Send All Pending Reminders (<?= count($unsent) ?>)
                        </button>
                    </form>
                </div>
            </div>

        </div>

        <!-- SMS Log -->
        <div class="card" style="margin-top:24px">
            <div class="card-header">
                <h2 class="card-title">📋 SMS Log</h2>
                <span class="badge badge-pending"><?= count($smsLogs) ?> total</span>
            </div>
            <?php if (empty($smsLogs)): ?>
                <div class="empty-state"><span class="empty-icon">📭</span><p>No messages sent yet</p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr>
                            <th>Date / Time</th><th>Patient</th><th>Phone</th>
                            <th>Type</th><th>Message</th><th>Status</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($smsLogs as $log): ?>
                            <tr>
                                <td><small><?= htmlspecialchars($log['created_at']) ?></small></td>
                                <td><?= htmlspecialchars($log['patient_name'] ?? '—') ?></td>
                                <td><code><?= htmlspecialchars($log['to'] ?? '—') ?></code></td>
                                <td><?= ucfirst(htmlspecialchars($log['type'] ?? '')) ?></td>
                                <td>
                                    <span title="<?= htmlspecialchars($log['message'] ?? '') ?>">
                                        <?= htmlspecialchars(mb_substr($log['message'] ?? '', 0, 55)) ?>…
                                    </span>
                                </td>
                                <td>
                                    <?php if ($log['success']): ?>
                                        <span class="badge badge-completed">✓ Sent</span>
                                    <?php else: ?>
                                        <span class="badge badge-cancelled"
                                              title="<?= htmlspecialchars($log['error'] ?? '') ?>">
                                            ✗ Failed
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
