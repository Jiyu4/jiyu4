<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireAdmin();

$msg   = '';
$error = '';
$users = $auth->getAllUsers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = $_POST['id']     ?? '';

    if ($action === 'toggle' && $id) {
        $target = $auth->getUserById($id);
        if ($target && $id !== $_SESSION['user_id']) {
            $auth->updateUser($id, ['active' => !($target['active'] ?? true)]);
            $msg = 'User status updated.';
        } else {
            $error = 'Cannot deactivate your own account.';
        }
    }

    if ($action === 'promote' && $id) {
        $target = $auth->getUserById($id);
        $newRole = ($target['role'] ?? 'staff') === 'admin' ? 'staff' : 'admin';
        $auth->updateUser($id, ['role' => $newRole]);
        $msg = 'User role updated to ' . $newRole . '.';
    }

    if ($action === 'delete' && $id) {
        if ($id === $_SESSION['user_id']) {
            $error = 'You cannot delete your own account.';
        } else {
            $auth->deleteUser($id);
            $msg = 'User deleted.';
        }
    }

    $users = $auth->getAllUsers();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users — <?= CLINIC_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">User Accounts</h1>
                <p class="page-subtitle"><?= count($users) ?> registered user(s)</p>
            </div>
            <a href="register.php" class="btn btn-primary">+ Add User</a>
        </div>

        <?php if ($msg):   ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="card">
            <?php if (empty($users)): ?>
                <div class="empty-state"><span class="empty-icon">👤</span><p>No users yet</p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr>
                            <th>Name</th><th>Email</th><th>Phone</th>
                            <th>Role</th><th>Status</th><th>Registered</th><th>Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($u['name']) ?></strong>
                                    <?php if ($u['id'] === $_SESSION['user_id']): ?>
                                        <span class="badge badge-confirmed" style="margin-left:6px">You</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['phone'] ?: '—') ?></td>
                                <td>
                                    <span class="badge <?= $u['role'] === 'admin' ? 'badge-completed' : 'badge-pending' ?>">
                                        <?= ucfirst($u['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= ($u['active'] ?? true) ? 'badge-completed' : 'badge-cancelled' ?>">
                                        <?= ($u['active'] ?? true) ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td><small><?= htmlspecialchars(substr($u['created_at'], 0, 10)) ?></small></td>
                                <td class="action-cell">
                                    <!-- Promote/Demote -->
                                    <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="action" value="promote">
                                            <input type="hidden" name="id"     value="<?= $u['id'] ?>">
                                            <button class="btn btn-xs btn-secondary"
                                                    title="<?= $u['role'] === 'admin' ? 'Demote to Staff' : 'Promote to Admin' ?>">
                                                <?= $u['role'] === 'admin' ? '⬇ Staff' : '⬆ Admin' ?>
                                            </button>
                                        </form>
                                        <!-- Activate/Deactivate -->
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id"     value="<?= $u['id'] ?>">
                                            <button class="btn btn-xs <?= ($u['active'] ?? true) ? 'btn-ghost' : 'btn-primary' ?>">
                                                <?= ($u['active'] ?? true) ? '🔒 Deactivate' : '🔓 Activate' ?>
                                            </button>
                                        </form>
                                        <!-- Delete -->
                                        <form method="POST" style="display:inline"
                                              onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($u['name'])) ?>?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id"     value="<?= $u['id'] ?>">
                                            <button class="btn btn-xs btn-danger">🗑 Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:var(--muted);font-size:12px">Current user</span>
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
