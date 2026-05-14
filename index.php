<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';

$auth = new Auth();
$auth->requireLogin();
$db = new JsonDB();
$stats = $db->getStats();
$todayAppointments = $db->getTodayAppointments();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediCare Clinic — Dashboard</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Dashboard</h1>
                <p class="page-subtitle"><?= date('l, F j, Y') ?></p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card stat-blue">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <span class="stat-number"><?= $stats['total_patients'] ?></span>
                    <span class="stat-label">Total Patients</span>
                </div>
            </div>
            <div class="stat-card stat-green">
                <div class="stat-icon">📅</div>
                <div class="stat-info">
                    <span class="stat-number"><?= $stats['today_appointments'] ?></span>
                    <span class="stat-label">Today's Appointments</span>
                </div>
            </div>
            <div class="stat-card stat-amber">
                <div class="stat-icon">⏳</div>
                <div class="stat-info">
                    <span class="stat-number"><?= $stats['pending_appointments'] ?></span>
                    <span class="stat-label">Pending</span>
                </div>
            </div>
            <div class="stat-card stat-teal">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <span class="stat-number"><?= $stats['completed_appointments'] ?></span>
                    <span class="stat-label">Completed</span>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Today's Schedule</h2>
                    <a href="appointments.php?action=new" class="btn btn-sm btn-primary">+ New</a>
                </div>
                <?php if (empty($todayAppointments)): ?>
                    <div class="empty-state">
                        <span class="empty-icon">🗓️</span>
                        <p>No appointments scheduled for today</p>
                    </div>
                <?php else: ?>
                    <div class="appointment-list">
                        <?php foreach ($todayAppointments as $appt): ?>
                            <div class="appt-item">
                                <div class="appt-time"><?= htmlspecialchars($appt['time']) ?></div>
                                <div class="appt-info">
                                    <strong><?= htmlspecialchars($appt['patient_name']) ?></strong>
                                    <span><?= htmlspecialchars($appt['reason']) ?></span>
                                </div>
                                <span class="badge badge-<?= $appt['status'] ?>"><?= ucfirst($appt['status']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Quick Actions</h2>
                </div>
                <div class="quick-actions">
                    <a href="patients.php?action=new" class="quick-action-btn">
                        <span>➕</span> Register Patient
                    </a>
                    <a href="appointments.php?action=new" class="quick-action-btn">
                        <span>📅</span> Book Appointment
                    </a>
                    <a href="patients.php" class="quick-action-btn">
                        <span>🔍</span> Search Patients
                    </a>
                    <a href="appointments.php" class="quick-action-btn">
                        <span>📋</span> All Appointments
                    </a>
                    <a href="sms.php" class="quick-action-btn">
                        <span>💬</span> Send SMS Reminder
                    </a>
                    <a href="settings.php" class="quick-action-btn">
                        <span>⚙️</span> Settings
                    </a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
