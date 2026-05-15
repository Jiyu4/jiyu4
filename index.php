<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';

$auth = new Auth();
$auth->requireAdmin();

$db               = new JsonDB();
$stats            = $db->getStats();
$todayAppointments = $db->getTodayAppointments();
$pendingAppts     = $db->getRecentPendingAppointments(5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — <?= CLINIC_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Admin Dashboard</h1>
                <p class="page-subtitle"><?= date('l, F j, Y') ?> · Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?></p>
            </div>
            <a href="appointments.php?action=new" class="btn btn-primary">+ Book Appointment</a>
        </div>

        <!-- Stats -->
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
            <!-- Today's Schedule -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">📅 Today's Schedule</h2>
                    <a href="appointments.php" class="btn btn-sm btn-ghost">View All</a>
                </div>
                <?php if (empty($todayAppointments)): ?>
                    <div class="empty-state">
                        <span class="empty-icon">🗓️</span>
                        <p>No appointments today</p>
                    </div>
                <?php else: ?>
                    <div class="appointment-list">
                        <?php foreach ($todayAppointments as $appt): ?>
                            <div class="appt-item">
                                <div class="appt-time"><?= date('g:i A', strtotime($appt['time'])) ?></div>
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

            <!-- Pending Appointments -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">⏳ Pending Approvals</h2>
                    <a href="appointments.php?status=pending" class="btn btn-sm btn-ghost">View All</a>
                </div>
                <?php if (empty($pendingAppts)): ?>
                    <div class="empty-state">
                        <span class="empty-icon">✅</span>
                        <p>No pending appointments</p>
                    </div>
                <?php else: ?>
                    <div class="appointment-list">
                        <?php foreach ($pendingAppts as $appt): ?>
                            <div class="appt-item">
                                <div class="appt-time" style="font-size:11px;width:70px">
                                    <?= htmlspecialchars($appt['date']) ?><br>
                                    <?= date('g:i A', strtotime($appt['time'])) ?>
                                </div>
                                <div class="appt-info">
                                    <strong><?= htmlspecialchars($appt['patient_name']) ?></strong>
                                    <span><?= htmlspecialchars($appt['reason']) ?></span>
                                </div>
                                <div style="display:flex;gap:4px;flex-shrink:0">
                                    <form method="POST" action="appointments.php?id=<?= $appt['id'] ?>">
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="status" value="confirmed">
                                        <button class="btn btn-xs btn-primary">✓ Confirm</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card" style="margin-top:20px">
            <div class="card-header"><h2 class="card-title">⚡ Quick Actions</h2></div>
            <div class="quick-actions" style="grid-template-columns:repeat(6,1fr)">
                <a href="patients.php?action=new"  class="quick-action-btn"><span>➕</span> Add Patient</a>
                <a href="appointments.php?action=new" class="quick-action-btn"><span>📅</span> Book Appt</a>
                <a href="patients.php"             class="quick-action-btn"><span>🔍</span> Search</a>
                <a href="appointments.php"         class="quick-action-btn"><span>📋</span> All Appts</a>
                <a href="sms.php"                  class="quick-action-btn"><span>💬</span> Send SMS</a>
                <a href="users.php"                class="quick-action-btn"><span>👤</span> Users</a>
            </div>
        </div>
    </main>
</body>
</html>
