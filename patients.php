<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';

$auth = new Auth();
$auth->requireLogin();
$db     = new JsonDB();
$action = $_GET['action'] ?? 'list';
$id     = $_GET['id'] ?? null;
$msg    = '';
$error  = '';

// ── Handle POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create') {
        $required = ['first_name','last_name','dob','gender','phone'];
        $missing  = array_filter($required, fn($f) => empty($_POST[$f]));
        if ($missing) {
            $error = 'Please fill in all required fields.';
        } else {
            $patient = $db->createPatient($_POST);
            header('Location: patients.php?msg=created');
            exit;
        }
    }

    if ($postAction === 'update' && $id) {
        $db->updatePatient($id, $_POST);
        header('Location: patients.php?msg=updated');
        exit;
    }

    if ($postAction === 'delete' && $id) {
        $db->deletePatient($id);
        header('Location: patients.php?msg=deleted');
        exit;
    }
}

if (isset($_GET['msg'])) {
    $msgs = ['created'=>'Patient registered successfully.','updated'=>'Patient updated.','deleted'=>'Patient deleted.'];
    $msg  = $msgs[$_GET['msg']] ?? '';
}

$patient  = $id ? $db->getPatient($id) : null;
$search   = $_GET['q'] ?? '';
$patients = $search ? $db->searchPatients($search) : $db->getAllPatients();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients — <?= CLINIC_NAME ?></title>
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
                <h1 class="page-title"><?= $action === 'new' ? 'Register Patient' : 'Edit Patient' ?></h1>
                <p class="page-subtitle"><a href="patients.php">← Back to Patients</a></p>
            </div>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="card form-card">
            <form method="POST" action="patients.php<?= $id ? '?id='.$id : '' ?>">
                <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'create' ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name <span class="req">*</span></label>
                        <input type="text" name="first_name" required value="<?= htmlspecialchars($patient['first_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Last Name <span class="req">*</span></label>
                        <input type="text" name="last_name" required value="<?= htmlspecialchars($patient['last_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Date of Birth <span class="req">*</span></label>
                        <input type="date" name="dob" required value="<?= htmlspecialchars($patient['dob'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Gender <span class="req">*</span></label>
                        <select name="gender" required>
                            <option value="">Select…</option>
                            <?php foreach (['Male','Female','Other'] as $g): ?>
                                <option value="<?= $g ?>" <?= ($patient['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Phone <span class="req">*</span></label>
                        <input type="tel" name="phone" required value="<?= htmlspecialchars($patient['phone'] ?? '') ?>" placeholder="+63 912 345 6789">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($patient['email'] ?? '') ?>">
                    </div>
                    <div class="form-group form-full">
                        <label>Address</label>
                        <input type="text" name="address" value="<?= htmlspecialchars($patient['address'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Blood Type</label>
                        <select name="blood_type">
                            <option value="">Unknown</option>
                            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                                <option value="<?= $bt ?>" <?= ($patient['blood_type'] ?? '') === $bt ? 'selected' : '' ?>><?= $bt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Allergies</label>
                        <input type="text" name="allergies" value="<?= htmlspecialchars($patient['allergies'] ?? '') ?>" placeholder="e.g. Penicillin, Peanuts">
                    </div>
                    <div class="form-group form-full">
                        <label>Notes</label>
                        <textarea name="notes" rows="3"><?= htmlspecialchars($patient['notes'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <a href="patients.php" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <?= $action === 'new' ? 'Register Patient' : 'Save Changes' ?>
                    </button>
                </div>
            </form>
        </div>

        <?php elseif ($action === 'view' && $patient): ?>
        <!-- VIEW PATIENT -->
        <div class="page-header">
            <div>
                <h1 class="page-title"><?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></h1>
                <p class="page-subtitle"><?= htmlspecialchars($patient['patient_id']) ?> · <a href="patients.php">← Back</a></p>
            </div>
            <div class="page-actions">
                <a href="appointments.php?action=new&patient_id=<?= $patient['id'] ?>" class="btn btn-primary">📅 Book Appointment</a>
                <a href="sms.php?patient_id=<?= $patient['id'] ?>" class="btn btn-secondary">💬 Send SMS</a>
                <a href="patients.php?action=edit&id=<?= $patient['id'] ?>" class="btn btn-ghost">✏️ Edit</a>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Patient Information</h2></div>
                <div class="detail-grid">
                    <div class="detail-item"><span class="detail-label">Full Name</span><span><?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></span></div>
                    <div class="detail-item"><span class="detail-label">Date of Birth</span><span><?= htmlspecialchars($patient['dob']) ?></span></div>
                    <div class="detail-item"><span class="detail-label">Gender</span><span><?= htmlspecialchars($patient['gender']) ?></span></div>
                    <div class="detail-item"><span class="detail-label">Blood Type</span><span><?= htmlspecialchars($patient['blood_type'] ?: '—') ?></span></div>
                    <div class="detail-item"><span class="detail-label">Phone</span><span><?= htmlspecialchars($patient['phone']) ?></span></div>
                    <div class="detail-item"><span class="detail-label">Email</span><span><?= htmlspecialchars($patient['email'] ?: '—') ?></span></div>
                    <div class="detail-item form-full"><span class="detail-label">Address</span><span><?= htmlspecialchars($patient['address'] ?: '—') ?></span></div>
                    <div class="detail-item form-full"><span class="detail-label">Allergies</span><span><?= htmlspecialchars($patient['allergies'] ?: 'None known') ?></span></div>
                    <div class="detail-item form-full"><span class="detail-label">Notes</span><span><?= htmlspecialchars($patient['notes'] ?: '—') ?></span></div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h2 class="card-title">Appointment History</h2></div>
                <?php $appts = $db->getPatientAppointments($patient['id']); ?>
                <?php if (empty($appts)): ?>
                    <div class="empty-state"><span class="empty-icon">📅</span><p>No appointments yet</p></div>
                <?php else: ?>
                    <div class="appointment-list">
                        <?php foreach (array_reverse($appts) as $a): ?>
                            <div class="appt-item">
                                <div class="appt-time"><?= htmlspecialchars($a['date']) ?><br><small><?= htmlspecialchars($a['time']) ?></small></div>
                                <div class="appt-info">
                                    <strong><?= htmlspecialchars($a['reason']) ?></strong>
                                    <span><?= htmlspecialchars($a['doctor'] ?: 'No doctor assigned') ?></span>
                                </div>
                                <span class="badge badge-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php else: ?>
        <!-- LIST -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Patients</h1>
                <p class="page-subtitle"><?= count($patients) ?> record<?= count($patients) !== 1 ? 's' : '' ?></p>
            </div>
            <a href="patients.php?action=new" class="btn btn-primary">+ Register Patient</a>
        </div>

        <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <div class="card">
            <form class="search-bar" method="GET">
                <input type="text" name="q" placeholder="Search by name or phone…" value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if ($search): ?><a href="patients.php" class="btn btn-ghost">Clear</a><?php endif; ?>
            </form>

            <?php if (empty($patients)): ?>
                <div class="empty-state"><span class="empty-icon">👥</span><p>No patients found</p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr>
                            <th>ID</th><th>Name</th><th>DOB</th><th>Gender</th><th>Phone</th><th>Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($patients as $p): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($p['patient_id']) ?></code></td>
                                <td><a href="patients.php?action=view&id=<?= $p['id'] ?>"><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></a></td>
                                <td><?= htmlspecialchars($p['dob']) ?></td>
                                <td><?= htmlspecialchars($p['gender']) ?></td>
                                <td><?= htmlspecialchars($p['phone']) ?></td>
                                <td class="action-cell">
                                    <a href="patients.php?action=view&id=<?= $p['id'] ?>" class="btn btn-xs">View</a>
                                    <a href="patients.php?action=edit&id=<?= $p['id'] ?>" class="btn btn-xs btn-ghost">Edit</a>
                                    <form method="POST" action="patients.php?id=<?= $p['id'] ?>" style="display:inline" onsubmit="return confirm('Delete this patient?')">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-xs btn-danger">Delete</button>
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
