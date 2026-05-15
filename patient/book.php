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

$error = '';
$msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If patient record doesn't exist, create it first
    if (!$patient) {
        $auth->createLinkedPatient($user);
        $patient = $auth->getLinkedPatient((string)$user['id']);
    }

    if (!$patient) {
        $error = 'Could not find or create your patient record. Please contact the clinic.';
    } elseif (empty($_POST['date']) || empty($_POST['time']) || empty($_POST['reason'])) {
        $error = 'Date, time and reason are required.';
    } elseif ($_POST['date'] < date('Y-m-d')) {
        $error = 'Please choose a future date.';
    } else {
        $appt = $db->createAppointment([
            'patient_id' => $patient['id'],
            'user_id'    => $user['id'],
            'date'       => $_POST['date'],
            'time'       => $_POST['time'],
            'reason'     => trim($_POST['reason']),
            'doctor'     => trim($_POST['doctor'] ?? ''),
            'notes'      => trim($_POST['notes']  ?? ''),
        ]);
        header('Location: appointments.php?booked=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment — <?= CLINIC_NAME ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'nav.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Book an Appointment</h1>
                <p class="page-subtitle">Fill in the details below — the clinic will confirm your booking</p>
            </div>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="card form-card" style="max-width:680px">
            <div class="info-box" style="margin-bottom:20px">
                📋 Booking as: <strong><?= htmlspecialchars($user['name']) ?></strong>
                <?php if ($patient): ?> · Patient ID: <code><?= htmlspecialchars($patient['patient_id']) ?></code><?php endif; ?>
                <br><small>Your appointment will be set to <strong>Pending</strong> until confirmed by the clinic staff.</small>
            </div>

            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Preferred Date <span class="req">*</span></label>
                        <input type="date" name="date" required
                               min="<?= date('Y-m-d') ?>"
                               value="<?= htmlspecialchars($_POST['date'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Preferred Time <span class="req">*</span></label>
                        <input type="time" name="time" required
                               value="<?= htmlspecialchars($_POST['time'] ?? '09:00') ?>">
                    </div>
                    <div class="form-group form-full">
                        <label>Reason / Chief Complaint <span class="req">*</span></label>
                        <input type="text" name="reason" required
                               placeholder="e.g. Annual check-up, Fever, Follow-up"
                               value="<?= htmlspecialchars($_POST['reason'] ?? '') ?>">
                    </div>
                    <div class="form-group form-full">
                        <label>Preferred Doctor <small style="color:var(--muted)">(optional)</small></label>
                        <input type="text" name="doctor"
                               placeholder="Leave blank if no preference"
                               value="<?= htmlspecialchars($_POST['doctor'] ?? '') ?>">
                    </div>
                    <div class="form-group form-full">
                        <label>Additional Notes <small style="color:var(--muted)">(optional)</small></label>
                        <textarea name="notes" rows="3"
                                  placeholder="Any additional information the clinic should know…"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <a href="dashboard.php" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">📅 Submit Appointment Request</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
