<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

$auth = new Auth();
$auth->requireLogin();
if ($auth->isAdmin()) { header('Location: ../appointments.php'); exit; }

$db      = new JsonDB();
$user    = $auth->currentUser();
$patient = $auth->getLinkedPatient((string)$user['id']);
$appts   = $patient ? $db->getPatientAppointments((string)$patient['id']) : [];
usort($appts, fn($a,$b) => strcmp($b['date'].$b['time'], $a['date'].$a['time']));

$msg = '';
if (isset($_GET['booked'])) $msg = '✅ Appointment request submitted! The clinic will confirm it shortly.';

// Allow patient to cancel a pending appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['cancel_id'])) {
    $apptId = $_POST['cancel_id'];
    // Verify it belongs to this patient
    $appt = $db->getAppointment($apptId);
    if ($appt && $patient && (string)$appt['patient_id'] === (string)$patient['id'] && $appt['status'] === 'pending') {
        $db->updateAppointment($apptId, ['status' => 'cancelled']);
        $msg = 'Appointment cancelled.';
        $appts = $db->getPatientAppointments((string)$patient['id']);
        usort($appts, fn($a,$b) => strcmp($b['date'].$b['time'], $a['date'].$a['time']));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments — <?= CLINIC_NAME ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'nav.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">My Appointments</h1>
                <p class="page-subtitle"><?= count($appts) ?> total appointment(s)</p>
            </div>
            <a href="book.php" class="btn btn-primary">+ Book New</a>
        </div>

        <?php if ($msg): ?>
            <div class="alert <?= str_starts_with($msg,'✅') ? 'alert-success' : 'alert-success' ?>">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <?php if (empty($appts)): ?>
                <div class="empty-state">
                    <span class="empty-icon">📅</span>
                    <p>You have no appointments yet</p>
                    <a href="book.php" class="btn btn-primary" style="margin-top:12px">Book Your First Appointment</a>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr>
                            <th>Appt ID</th><th>Date</th><th>Time</th><th>Reason</th>
                            <th>Doctor</th><th>Status</th><th>Action</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($appts as $a): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($a['appt_id']) ?></code></td>
                                <td><?= htmlspecialchars($a['date']) ?></td>
                                <td><?= date('g:i A', strtotime($a['time'])) ?></td>
                                <td><?= htmlspecialchars($a['reason']) ?></td>
                                <td><?= htmlspecialchars($a['doctor'] ?: '—') ?></td>
                                <td><span class="badge badge-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
                                <td>
                                    <?php if ($a['status'] === 'pending'): ?>
                                        <form method="POST" onsubmit="return confirm('Cancel this appointment?')">
                                            <input type="hidden" name="cancel_id" value="<?= $a['id'] ?>">
                                            <button class="btn btn-xs btn-danger">✕ Cancel</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:var(--muted);font-size:12px">—</span>
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
