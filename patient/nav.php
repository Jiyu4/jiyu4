<?php
require_once __DIR__ . '/../includes/auth.php';
$_nav_auth = new Auth();
$_nav_user = $_nav_auth->currentUser();
$_nav_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<nav class="sidebar">
    <div class="sidebar-logo">
        <span class="logo-icon">🏥</span>
        <div>
            <span class="logo-name"><?= htmlspecialchars(CLINIC_NAME) ?></span>
            <span class="logo-tagline">Patient Portal</span>
        </div>
    </div>

    <ul class="nav-list">
        <li>
            <a href="dashboard.php" class="nav-link <?= $_nav_page==='dashboard'?'active':'' ?>">
                <span class="nav-icon">🏠</span><span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="book.php" class="nav-link <?= $_nav_page==='book'?'active':'' ?>">
                <span class="nav-icon">📅</span><span>Book Appointment</span>
            </a>
        </li>
        <li>
            <a href="appointments.php" class="nav-link <?= $_nav_page==='appointments'?'active':'' ?>">
                <span class="nav-icon">📋</span><span>My Appointments</span>
            </a>
        </li>
        <li>
            <a href="profile.php" class="nav-link <?= $_nav_page==='profile'?'active':'' ?>">
                <span class="nav-icon">👤</span><span>My Profile</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-user">
        <div class="sidebar-user-info">
            <span class="sidebar-user-avatar"><?= strtoupper(substr($_nav_user['name']??'P',0,1)) ?></span>
            <div>
                <span class="sidebar-user-name"><?= htmlspecialchars($_nav_user['name']??'Patient') ?></span>
                <span class="sidebar-user-role">Patient</span>
            </div>
        </div>
        <a href="../logout.php" class="sidebar-logout" title="Sign out">⏻</a>
    </div>
</nav>
