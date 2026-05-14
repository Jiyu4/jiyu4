<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/sms.php';

$auth = new Auth();
$auth->requireAdmin(); // settings = admin only
$gateway = new SMSGateway();
$msg     = '';

// Handle config save — writes back to config.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_config') {
    $configPath = __DIR__ . '/includes/config.php';
    $content    = file_get_contents($configPath);

    $replacements = [
        'CLINIC_NAME'       => $_POST['clinic_name']    ?? CLINIC_NAME,
        'CLINIC_PHONE'      => $_POST['clinic_phone']   ?? CLINIC_PHONE,
        'CLINIC_ADDRESS'    => $_POST['clinic_address'] ?? CLINIC_ADDRESS,
        'TEXTBEE_API_KEY'   => $_POST['textbee_api_key']   ?? TEXTBEE_API_KEY,
        'TEXTBEE_DEVICE_ID' => $_POST['textbee_device_id'] ?? TEXTBEE_DEVICE_ID,
    ];

    foreach ($replacements as $constant => $value) {
        $value   = addslashes($value);
        $content = preg_replace(
            "/define\('{$constant}',\s*'[^']*'\)/",
            "define('{$constant}', '{$value}')",
            $content
        );
    }

    if (file_put_contents($configPath, $content)) {
        $msg = 'Settings saved! Please reload the page to apply changes.';
    } else {
        $msg = 'ERROR: Could not write to config.php. Check file permissions.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — <?= CLINIC_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Settings</h1>
                <p class="page-subtitle">Clinic profile &amp; Twilio API configuration</p>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert <?= str_contains($msg, 'ERROR') ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="save_config">

            <!-- Clinic Info -->
            <div class="card" style="margin-bottom:24px">
                <div class="card-header"><h2 class="card-title">🏥 Clinic Information</h2></div>
                <div class="form-grid">
                    <div class="form-group form-full">
                        <label>Clinic Name</label>
                        <input type="text" name="clinic_name" value="<?= htmlspecialchars(CLINIC_NAME) ?>">
                    </div>
                    <div class="form-group">
                        <label>Clinic Phone</label>
                        <input type="text" name="clinic_phone" value="<?= htmlspecialchars(CLINIC_PHONE) ?>">
                    </div>
                    <div class="form-group">
                        <label>Clinic Address</label>
                        <input type="text" name="clinic_address" value="<?= htmlspecialchars(CLINIC_ADDRESS) ?>">
                    </div>
                </div>
            </div>

            <!-- TextBee Config -->
            <div class="card" style="margin-bottom:24px">
                <div class="card-header">
                    <h2 class="card-title">💬 TextBee SMS Configuration</h2>
                    <span class="badge <?= $gateway->isConfigured() ? 'badge-completed' : 'badge-cancelled' ?>">
                        <?= $gateway->isConfigured() ? 'Configured ✓' : 'Not Configured' ?>
                    </span>
                </div>

                <div class="info-box">
                    <strong>How to set up TextBee:</strong>
                    <ol>
                        <li>Register at <a href="https://textbee.dev" target="_blank">textbee.dev</a> (free plan available)</li>
                        <li>Install the <strong>TextBee app</strong> on your Android phone from <a href="https://textbee.dev/download" target="_blank">textbee.dev/download</a></li>
                        <li>Open the app → grant SMS permissions → it will register your device</li>
                        <li>Go to <a href="https://textbee.dev/dashboard" target="_blank">textbee.dev/dashboard</a> → copy your <strong>API Key</strong> and <strong>Device ID</strong></li>
                        <li>Paste them below and save</li>
                    </ol>
                </div>

                <div class="form-grid">
                    <div class="form-group form-full">
                        <label>API Key</label>
                        <input type="text" name="textbee_api_key"
                               value="<?= htmlspecialchars(TEXTBEE_API_KEY) ?>"
                               placeholder="Your TextBee API Key"
                               class="font-mono">
                        <small class="field-hint">Found at <strong>textbee.dev/dashboard</strong> → API Keys</small>
                    </div>
                    <div class="form-group form-full">
                        <label>Device ID</label>
                        <input type="text" name="textbee_device_id"
                               value="<?= htmlspecialchars(TEXTBEE_DEVICE_ID) ?>"
                               placeholder="Your registered Android device ID"
                               class="font-mono">
                        <small class="field-hint">Found at <strong>textbee.dev/dashboard</strong> → Devices → click your device</small>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">💾 Save Settings</button>
            </div>
        </form>

        <!-- Data Management -->
        <div class="card" style="margin-top:24px">
            <div class="card-header"><h2 class="card-title">🗂️ Data Files</h2></div>
            <p>JSON data files are stored in the <code>data/</code> directory:</p>
            <div class="detail-grid">
                <?php
                $files = [
                    'patients.json'     => 'Patient records',
                    'appointments.json' => 'Appointments',
                    'sms_log.json'      => 'SMS log',
                ];
                foreach ($files as $file => $label):
                    $path = __DIR__ . '/data/' . $file;
                    $size = file_exists($path) ? round(filesize($path) / 1024, 2) . ' KB' : 'Not created yet';
                    $count = 0;
                    if (file_exists($path)) {
                        $data = json_decode(file_get_contents($path), true);
                        $count = is_array($data) ? count($data) : 0;
                    }
                ?>
                <div class="detail-item">
                    <span class="detail-label"><?= $label ?></span>
                    <span><code><?= $file ?></code> — <?= $count ?> record(s), <?= $size ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</body>
</html>
