<?php
require_once __DIR__ . '/auth.php';
$_nav_auth = new Auth();
$_nav_user = $_nav_auth->currentUser();
$_nav_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<nav class="sidebar">
    <div class="sidebar-logo">
        <span class="logo-icon">🏥</span>
        <div>
            <span class="logo-name"><?= htmlspecialchars(CLINIC_NAME) ?></span>
            <span class="logo-tagline">Clinical System</span>
        </div>
    </div>

    <?php
    $navItems = [
        ['href' => 'index.php',        'icon' => '📊', 'label' => 'Dashboard',    'page' => 'index'],
        ['href' => 'patients.php',     'icon' => '👥', 'label' => 'Patients',     'page' => 'patients'],
        ['href' => 'appointments.php', 'icon' => '📅', 'label' => 'Appointments', 'page' => 'appointments'],
        ['href' => 'sms.php',          'icon' => '💬', 'label' => 'SMS',          'page' => 'sms'],
    ];
    if ($_nav_auth->isAdmin()) {
        $navItems[] = ['href' => 'users.php',    'icon' => '👤', 'label' => 'Users',    'page' => 'users'];
        $navItems[] = ['href' => 'settings.php', 'icon' => '⚙️', 'label' => 'Settings', 'page' => 'settings'];
    }
    ?>

    <ul class="nav-list">
        <?php foreach ($navItems as $item): ?>
            <li>
                <a href="<?= $item['href'] ?>" class="nav-link <?= $_nav_page === $item['page'] ? 'active' : '' ?>">
                    <span class="nav-icon"><?= $item['icon'] ?></span>
                    <span><?= $item['label'] ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="sidebar-user">
        <div class="sidebar-user-info">
            <span class="sidebar-user-avatar"><?= strtoupper(substr($_nav_user['name'] ?? 'U', 0, 1)) ?></span>
            <div>
                <span class="sidebar-user-name"><?= htmlspecialchars($_nav_user['name'] ?? 'User') ?></span>
                <span class="sidebar-user-role"><?= ucfirst($_nav_user['role'] ?? '') ?></span>
            </div>
        </div>
        <a href="logout.php" class="sidebar-logout" title="Sign out">⏻</a>
    </div>
</nav>
