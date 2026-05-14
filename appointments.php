<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/sms.php';

$auth = new Auth();
$auth->requireLogin();
$db      = new JsonDB();
$gateway = new SMSGateway();
$action = $_GET['action'] ?? 'list';
$id     = $_GET['id'] ?? null;
$msg    = '';
$error  = '';

// ── Handle POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create') {
        $required = ['patient_id','date','time','reason'];
        $missing  = array_filter($required, fn($f) => empty($_POST[$f]));
        if ($missing) {
            $error = 'Please fill in all required fields.';
        } else {
            $appt = $db->createAppointment($_POST);
            // Optionally send SMS
            if (!empty($_POST['send_sms'])) {
                $gateway->sendAppointmentReminder($appt['id']);
            }
            header('Location: appointments.php?msg=created');
            exit;
        }
    }

    if ($postAction === 'update' && $id) {
        $db->updateAppointment($id, $_POST);
        header('Location: appointments.php?msg=updated');
        exit;
    }

    if ($postAction === 'delete' && $id) {
        $db->deleteAppointment($id);
        header('Location: appointments.php?msg=deleted');
        exit;
    }

    if ($postAction === 'send_reminder' && $id) {
        $result = $gateway->sendAppointmentReminder($id);
        $msg    = $result['success'] ? 'SMS reminder sent!' : 'SMS failed: ' . $result['error'];
    }

    if ($postAction === 'status' && $id) {
        $db->updateAppointment($id, ['status' => $_POST['status']]);
        header('Location: appointments.php?msg=updated');
        exit;
    }
}

if (isset($_GET['msg'])) {
    $msgs = ['created'=>'Appointment booked.','updated'=>'Appointment updated.','deleted'=>'Appointment deleted.'];
    $msg  = $msgs[$_GET['msg']] ?? $msg;
}

$appt     = $id ? $db->getAppointment($id) : null;
$patients = $db->getAllPatients();

// Filters
$filterDate   = $_GET['date']   ?? '';
$filterStatus = $_GET['status'] ?? '';
$appointments = $db->getAllAppointments();

if ($filterDate)   $appointments = array_values(array_filter($appointments, fn($a) => $a['date'] === $filterDate));
if ($filterStatus) $appointments = array_values(array_filter($appointments, fn($a) => $a['status'] === $filterStatus));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments — <?= CLINIC_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <main class="main-content">
        <?php if ($action === 'new' || $action === 'edit'): ?>
        <!-- FORM -->
        <div class="page-header">
            <div>
                <h1 class="page-title"><?= $action === 'new' ? 'Book Appointment' : 'Edit Appointment' ?></h1>
                <p class="page-subtitle"><a href="appointments.php">← Back to Appointments</a></p>
            </div>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="card form-card">
            <form method="POST" action="appointments.php<?= $id ? '?id='.$id : '' ?>">
                <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'create' ?>">
                <div class="form-grid">
                    <div class="form-group form-full">
                        <label>Patient <span class="req">*</span></label>
                        <select name="patient_id" required>
                            <option value="">Select patient…</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= $p['id'] ?>"
                                    <?= (($appt['patient_id'] ?? $_GET['patient_id'] ?? '') === $p['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['patient_id'] . ' — ' . $p['first_name'] . ' ' . $p['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date <span class="req">*</span></label>
                        <input type="date" name="date" required value="<?= htmlspecialchars($appt['date'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="form-group">
                        <label>Time <span class="req">*</span></label>
                        <input type="time" name="time" required value="<?= htmlspecialchars($appt['time'] ?? '09:00') ?>">
                    </div>
                    <div class="form-group form-full">
                        <label>Reason / Chief Complaint <span class="req">*</span></label>
                        <input type="text" name="reason" required value="<?= htmlspecialchars($appt['reason'] ?? '') ?>" placeholder="e.g. Annual check-up, Fever">
                    </div>
                    <div class="form-group">
                        <label>Doctor / Physician</label>
                        <input type="text" name="doctor" value="<?= htmlspecialchars($appt['doctor'] ?? '') ?>" placeholder="Dr. ">
                    </div>
                    <?php if ($action === 'edit'): ?>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <?php foreach (['pending','confirmed','completed','cancelled','no-show'] as $s): ?>
                                <option value="<?= $s ?>" <?= ($appt['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="form-group form-full">
                        <label>Notes</label>
                        <textarea name="notes" rows="3"><?= htmlspecialchars($appt['notes'] ?? '') ?></textarea>
                    </div>
                    <?php if ($action === 'new' && $gateway->isReachable()): ?>
                    <div class="form-group form-full">
                        <label class="checkbox-label">
                            <input type="checkbox" name="send_sms" value="1">
                            Send SMS confirmation to patient
                        </label>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="form-actions">
                    <a href="appointments.php" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <?= $action === 'new' ? 'Book Appointment' : 'Save Changes' ?>
                    </button>
                </div>
            </form>
        </div>

        <?php else: ?>
        <!-- LIST -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Appointments</h1>
                <p class="page-subtitle"><?= count($appointments) ?> shown</p>
            </div>
            <a href="appointments.php?action=new" class="btn btn-primary">+ Book Appointment</a>
        </div>

        <?php if ($msg): ?>
            <div class="alert <?= str_contains($msg, 'fail') || str_contains($msg, 'Failed') ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <form class="filter-bar" method="GET">
            <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>">
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach (['pending','confirmed','completed','cancelled','no-show'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="appointments.php" class="btn btn-ghost">Reset</a>
        </form>

        <div class="card">
            <?php if (empty($appointments)): ?>
                <div class="empty-state"><span class="empty-icon">📅</span><p>No appointments found</p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr>
                            <th>ID</th><th>Patient</th><th>Date</th><th>Time</th><th>Reason</th><th>Doctor</th><th>Status</th><th>SMS</th><th>Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($appointments as $a): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($a['appt_id']) ?></code></td>
                                <td>
                                    <?php $pt = $db->getPatient($a['patient_id']); ?>
                                    <?php if ($pt): ?>
                                        <a href="patients.php?action=view&id=<?= $pt['id'] ?>"><?= htmlspecialchars($a['patient_name']) ?></a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($a['patient_name']) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($a['date']) ?></td>
                                <td><?= htmlspecialchars($a['time']) ?></td>
                                <td><?= htmlspecialchars($a['reason']) ?></td>
                                <td><?= htmlspecialchars($a['doctor'] ?: '—') ?></td>
                                <td><span class="badge badge-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
                                <td><?= $a['sms_sent'] ? '✅' : '—' ?></td>
                                <td class="action-cell">
                                    <a href="appointments.php?action=edit&id=<?= $a['id'] ?>" class="btn btn-xs btn-ghost">Edit</a>
                                    <form method="POST" action="appointments.php?id=<?= $a['id'] ?>" style="display:inline">
                                        <input type="hidden" name="action" value="send_reminder">
                                        <button type="submit" class="btn btn-xs btn-secondary" title="Send SMS reminder">💬</button>
                                    </form>
                                    <form method="POST" action="appointments.php?id=<?= $a['id'] ?>" style="display:inline" onsubmit="return confirm('Delete appointment?')">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-xs btn-danger">✕</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
