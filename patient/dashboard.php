<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

$auth = new Auth();
$auth->requireLogin();

// Redirect admin/staff to admin dashboard
if ($auth->isAdmin()) {
    header('Location: ../index.php'); exit;
}

$db      = new JsonDB();
$user    = $auth->currentUser();
$patient = $auth->getLinkedPatient((string)$user['id']);

// Get this patient's appointments
$myAppts      = $patient ? $db->getPatientAppointments((string)$patient['id']) : [];
$upcoming     = array_filter($myAppts, fn($a) => $a['date'] >= date('Y-m-d') && !in_array($a['status'],['cancelled','no-show']));
$past         = array_filter($myAppts, fn($a) => $a['date'] < date('Y-m-d') || in_array($a['status'],['completed','cancelled']));
usort($upcoming, fn($a,$b) => strcmp($a['date'].$a['time'], $b['date'].$b['time']));
$nextAppt     = reset($upcoming);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Portal — <?= CLINIC_NAME ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'nav.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Hello, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>! 👋</h1>
                <p class="page-subtitle"><?= date('l, F j, Y') ?> · Patient Portal</p>
            </div>
            <a href="book.php" class="btn btn-primary">+ Book Appointment</a>
        </div>

        <!-- Stats -->
        <div class="stats-grid" style="grid-template-columns:repeat(3,1fr)">
            <div class="stat-card stat-blue">
                <div class="stat-icon">📅</div>
                <div class="stat-info">
                    <span class="stat-number"><?= count($myAppts) ?></span>
                    <span class="stat-label">Total Appointments</span>
                </div>
            </div>
            <div class="stat-card stat-amber">
                <div class="stat-icon">⏳</div>
                <div class="stat-info">
                    <span class="stat-number"><?= count($upcoming) ?></span>
                    <span class="stat-label">Upcoming</span>
                </div>
            </div>
            <div class="stat-card stat-teal">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <span class="stat-number"><?= count(array_filter($myAppts,fn($a)=>$a['status']==='completed')) ?></span>
                    <span class="stat-label">Completed</span>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Next Appointment -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">📌 Next Appointment</h2>
                    <a href="appointments.php" class="btn btn-sm btn-ghost">View All</a>
                </div>
                <?php if ($nextAppt): ?>
                    <div style="padding:24px">
                        <div style="background:var(--teal-light);border-radius:12px;padding:20px;text-align:center;margin-bottom:16px">
                            <div style="font-size:32px;font-weight:700;color:var(--teal);font-family:'DM Serif Display',serif">
                                <?= date('F j', strtotime($nextAppt['date'])) ?>
                            </div>
                            <div style="font-size:18px;color:var(--teal);font-weight:600">
                                <?= date('g:i A', strtotime($nextAppt['time'])) ?>
                            </div>
                            <div style="font-size:13px;color:var(--muted);margin-top:4px">
                                <?= date('Y', strtotime($nextAppt['date'])) ?> · <?= date('l', strtotime($nextAppt['date'])) ?>
                            </div>
                        </div>
                        <div class="detail-grid" style="padding:0">
                            <div class="detail-item">
                                <span class="detail-label">Reason</span>
                                <span><?= htmlspecialchars($nextAppt['reason']) ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Status</span>
                                <span class="badge badge-<?= $nextAppt['status'] ?>"><?= ucfirst($nextAppt['status']) ?></span>
                            </div>
                            <?php if ($nextAppt['doctor']): ?>
                            <div class="detail-item form-full">
                                <span class="detail-label">Doctor</span>
                                <span><?= htmlspecialchars($nextAppt['doctor']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="empty-icon">📅</span>
                        <p>No upcoming appointments</p>
                        <a href="book.php" class="btn btn-primary" style="margin-top:12px">Book Now</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- My Info -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">👤 My Information</h2>
                    <a href="profile.php" class="btn btn-sm btn-ghost">Edit</a>
                </div>
                <div style="padding:20px 24px">
                    <?php if ($patient): ?>
                        <div class="detail-grid" style="padding:0">
                            <div class="detail-item">
                                <span class="detail-label">Patient ID</span>
                                <span><code><?= htmlspecialchars($patient['patient_id']) ?></code></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Blood Type</span>
                                <span><?= htmlspecialchars($patient['blood_type'] ?: '—') ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Date of Birth</span>
                                <span><?= htmlspecialchars($patient['dob'] ?: '—') ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Gender</span>
                                <span><?= htmlspecialchars($patient['gender'] ?: '—') ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Phone</span>
                                <span><?= htmlspecialchars($patient['phone'] ?: '—') ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Email</span>
                                <span><?= htmlspecialchars($user['email']) ?></span>
                            </div>
                            <div class="detail-item" style="grid-column:1/-1">
                                <span class="detail-label">Allergies</span>
                                <span><?= htmlspecialchars($patient['allergies'] ?: 'None known') ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state"><p>No patient record found. Contact the clinic.</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent appointments -->
        <?php if (!empty($myAppts)): ?>
        <div class="card" style="margin-top:20px">
            <div class="card-header">
                <h2 class="card-title">🕒 Recent Appointments</h2>
                <a href="appointments.php" class="btn btn-sm btn-ghost">See All</a>
            </div>
            <div class="appointment-list">
                <?php foreach (array_slice(array_reverse(array_values($myAppts)), 0, 5) as $a): ?>
                    <div class="appt-item">
                        <div class="appt-time" style="width:75px;font-size:12px">
                            <?= htmlspecialchars($a['date']) ?><br>
                            <?= date('g:i A', strtotime($a['time'])) ?>
                        </div>
                        <div class="appt-info">
                            <strong><?= htmlspecialchars($a['reason']) ?></strong>
                            <span><?= htmlspecialchars($a['doctor'] ?: 'Doctor TBA') ?></span>
                        </div>
                        <span class="badge badge-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
