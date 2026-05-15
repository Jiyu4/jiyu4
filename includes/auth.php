<?php
require_once __DIR__ . '/config.php';

class Auth {

    private PDO $pdo;

    public function __construct() {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:40px;color:#7f1d1d;background:#fee2e2;border-radius:8px;margin:40px auto;max-width:600px">
                <h2>⚠️ Database Connection Failed</h2>
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
                <p>Check your DB credentials in <code>includes/config.php</code> or environment variables.</p>
            </div>');
        }
        $this->startSession();
    }

    private function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('CLINIC_SESS');
            session_start();
        }
    }

    private function normalizePhone(string $phone): string {
        $phone = trim($phone);
        if (str_starts_with($phone, '+')) {
            return '+' . preg_replace('/\D/', '', substr($phone, 1));
        }
        return preg_replace('/\D/', '', $phone);
    }

    // ══════════════════════════════════════════════════════
    //  USER MANAGEMENT
    // ══════════════════════════════════════════════════════

    public function getAllUsers(): array {
        return $this->pdo->query('SELECT * FROM users ORDER BY created_at ASC')->fetchAll();
    }

    public function getUserByEmail(string $email): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public function getUserByPhone(string $phone): ?array {
        $phone = $this->normalizePhone($phone);
        $stmt  = $this->pdo->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);
        return $stmt->fetch() ?: null;
    }

    public function getUserById(string $id): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Self-registration — always creates a PATIENT account.
     * Also creates a linked record in the patients table.
     * First-ever user becomes admin (for initial setup only).
     */
    public function createUser(array $data): array|false {
        if ($this->getUserByEmail($data['email'])) return false;

        // First user ever = admin, everyone else = patient
        $count = $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $role  = ($count == 0) ? 'admin' : 'patient';

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, phone, password, role, dob, gender, address, blood_type, allergies, active)
             VALUES (?,?,?,?,?,?,?,?,?,?,1)'
        );
        $stmt->execute([
            trim($data['name']),
            strtolower(trim($data['email'])),
            $this->normalizePhone($data['phone'] ?? ''),
            password_hash($data['password'], PASSWORD_BCRYPT),
            $role,
            $data['dob']        ?? null,
            $data['gender']     ?? null,
            $data['address']    ?? '',
            $data['blood_type'] ?? '',
            $data['allergies']  ?? '',
        ]);

        $userId = (string) $this->pdo->lastInsertId();
        $user   = $this->getUserById($userId);

        // If patient, auto-create a patient record linked to this user
        if ($role === 'patient' && $user) {
            $this->createLinkedPatient($user);
        }

        return $user;
    }

    /**
     * Create a patient record linked to a user account.
     */
    public function createLinkedPatient(array $user): void {
        // Check if already exists
        $stmt = $this->pdo->prepare('SELECT id FROM patients WHERE user_id = ? LIMIT 1');
        $stmt->execute([$user['id']]);
        if ($stmt->fetch()) return; // already linked

        $count     = $this->pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();
        $patientId = 'PT-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        $nameParts = explode(' ', $user['name'], 2);
        $firstName = $nameParts[0];
        $lastName  = $nameParts[1] ?? '';

        $stmt = $this->pdo->prepare(
            'INSERT INTO patients (patient_id, user_id, first_name, last_name, dob, gender, phone, email, address, blood_type, allergies)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $patientId,
            $user['id'],
            $firstName,
            $lastName,
            $user['dob']        ?? date('Y-m-d'),
            $user['gender']     ?? 'Other',
            $user['phone']      ?? '',
            $user['email'],
            $user['address']    ?? '',
            $user['blood_type'] ?? '',
            $user['allergies']  ?? '',
        ]);
    }

    /**
     * Get the patient record linked to a user.
     */
    public function getLinkedPatient(string $userId): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM patients WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public function updateUser(string $id, array $data): bool {
        $allowed = ['name','phone','password','role','active','dob','gender','address','blood_type','allergies'];
        $sets = []; $vals = [];
        foreach ($allowed as $f) {
            if (!array_key_exists($f, $data)) continue;
            if ($f === 'password') {
                if (empty($data[$f])) continue;
                $sets[] = 'password = ?';
                $vals[] = password_hash($data[$f], PASSWORD_BCRYPT);
            } elseif ($f === 'phone') {
                $sets[] = 'phone = ?';
                $vals[] = $this->normalizePhone($data[$f]);
            } elseif ($f === 'active') {
                $sets[] = 'active = ?';
                $vals[] = $data[$f] ? 1 : 0;
            } else {
                $sets[] = "{$f} = ?";
                $vals[] = $data[$f];
            }
        }
        if (empty($sets)) return false;
        $sets[] = 'updated_at = NOW()';
        $vals[] = $id;
        $stmt = $this->pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?');
        return $stmt->execute($vals);
    }

    public function deleteUser(string $id): bool {
        return $this->pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }

    // ══════════════════════════════════════════════════════
    //  LOGIN — PASSWORD
    // ══════════════════════════════════════════════════════

    public function loginWithPassword(string $email, string $password): array {
        $user = $this->getUserByEmail($email);
        if (!$user)                     return ['success'=>false,'error'=>'No account found with that email.'];
        if (!($user['active']??true))   return ['success'=>false,'error'=>'Your account has been deactivated.'];
        if (!password_verify($password, $user['password']))
                                        return ['success'=>false,'error'=>'Incorrect password.'];
        $this->startUserSession($user);
        return ['success'=>true,'user'=>$user];
    }

    // ══════════════════════════════════════════════════════
    //  LOGIN — OTP
    // ══════════════════════════════════════════════════════

    public function generateOtp(string $phone): array {
        $phone = $this->normalizePhone($phone);
        $user  = $this->getUserByPhone($phone);
        if (!$user)                    return ['success'=>false,'error'=>'No account found with that phone number.'];
        if (!($user['active']??true))  return ['success'=>false,'error'=>'Your account has been deactivated.'];

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM otp_codes WHERE phone=? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)'
        );
        $stmt->execute([$phone]);
        if ($stmt->fetchColumn() >= 3) return ['success'=>false,'error'=>'Too many OTP requests. Wait 10 minutes.'];

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->pdo->prepare(
            'INSERT INTO otp_codes (phone, code, expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE))'
        )->execute([$phone, password_hash($code, PASSWORD_BCRYPT)]);

        return ['success'=>true,'code'=>$code,'phone'=>$phone,'user'=>$user];
    }

    public function verifyOtp(string $phone, string $code): array {
        $phone = $this->normalizePhone($phone);
        $stmt  = $this->pdo->prepare(
            'SELECT * FROM otp_codes WHERE phone=? AND used=0 AND expires_at>NOW() ORDER BY created_at DESC'
        );
        $stmt->execute([$phone]);
        foreach ($stmt->fetchAll() as $otp) {
            if (!password_verify($code, $otp['code'])) continue;
            $this->pdo->prepare('UPDATE otp_codes SET used=1 WHERE id=?')->execute([$otp['id']]);
            $user = $this->getUserByPhone($phone);
            if (!$user) return ['success'=>false,'error'=>'User not found.'];
            $this->startUserSession($user);
            return ['success'=>true,'user'=>$user];
        }
        return ['success'=>false,'error'=>'Invalid or expired OTP code.'];
    }

    // ══════════════════════════════════════════════════════
    //  SESSION
    // ══════════════════════════════════════════════════════

    private function startUserSession(array $user): void {
        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['logged_in']  = true;
        $_SESSION['login_at']   = date('Y-m-d H:i:s');
    }

    public function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);
        }
        session_destroy();
    }

    public function isLoggedIn(): bool {
        return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
    }

    /** Redirect based on role after login */
    public function redirectToDashboard(): void {
        $role = $_SESSION['user_role'] ?? 'patient';
        if ($role === 'patient') {
            header('Location: patient/dashboard.php');
        } else {
            header('Location: index.php');
        }
        exit;
    }

    public function requireLogin(): void {
        if (!$this->isLoggedIn()) {
            header('Location: /login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }

    public function requireAdmin(): void {
        $this->requireLogin();
        if (!in_array($_SESSION['user_role']??'', ['admin','staff'])) {
            header('Location: patient/dashboard.php');
            exit;
        }
    }

    public function requirePatient(): void {
        $this->requireLogin();
        // Admin/staff can also view patient pages; only block if not logged in
    }

    public function currentUser(): ?array {
        if (!$this->isLoggedIn()) return null;
        return $this->getUserById((string)$_SESSION['user_id']);
    }

    public function isAdmin(): bool {
        return in_array($_SESSION['user_role']??'', ['admin','staff']);
    }

    public function isPatient(): bool {
        return ($_SESSION['user_role']??'') === 'patient';
    }

    public function getRole(): string {
        return $_SESSION['user_role'] ?? 'patient';
    }

    public function cleanExpiredOtps(): void {
        $this->pdo->exec('DELETE FROM otp_codes WHERE expires_at < NOW()');
    }
}
