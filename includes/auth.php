<?php
require_once __DIR__ . '/config.php';

class Auth {

    private string $usersFile;
    private string $otpFile;
    private string $sessionFile;

    public function __construct() {
        $this->usersFile   = DB_PATH . 'users.json';
        $this->otpFile     = DB_PATH . 'otp.json';
        $this->sessionFile = DB_PATH . 'sessions.json';
        $this->startSession();
    }

    // ── Session ─────────────────────────────────────────────

    private function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('CLINIC_SESS');
            session_start();
        }
    }

    // ── File helpers ────────────────────────────────────────

    private function read(string $file): array {
        if (!file_exists($file)) return [];
        return json_decode(file_get_contents($file), true) ?? [];
    }

    private function write(string $file, array $data): void {
        $dir = dirname($file);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    }

    // ══════════════════════════════════════════════════════
    //  USER MANAGEMENT
    // ══════════════════════════════════════════════════════

    public function getAllUsers(): array {
        return $this->read($this->usersFile);
    }

    public function getUserByEmail(string $email): ?array {
        foreach ($this->read($this->usersFile) as $u) {
            if (strtolower($u['email']) === strtolower($email)) return $u;
        }
        return null;
    }

    public function getUserByPhone(string $phone): ?array {
        $phone = $this->normalizePhone($phone);
        foreach ($this->read($this->usersFile) as $u) {
            if ($this->normalizePhone($u['phone'] ?? '') === $phone) return $u;
        }
        return null;
    }

    public function getUserById(string $id): ?array {
        foreach ($this->read($this->usersFile) as $u) {
            if ($u['id'] === $id) return $u;
        }
        return null;
    }

    public function createUser(array $data): array|false {
        // Check duplicates
        if ($this->getUserByEmail($data['email'])) return false;

        $users  = $this->read($this->usersFile);
        $isFirst = empty($users); // first user becomes admin

        $user = [
            'id'         => uniqid('usr_', true),
            'name'       => trim($data['name']),
            'email'      => strtolower(trim($data['email'])),
            'phone'      => $this->normalizePhone($data['phone'] ?? ''),
            'password'   => password_hash($data['password'], PASSWORD_BCRYPT),
            'role'       => $isFirst ? 'admin' : 'staff',
            'active'     => true,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $users[] = $user;
        $this->write($this->usersFile, $users);
        return $user;
    }

    public function updateUser(string $id, array $data): bool {
        $users = $this->read($this->usersFile);
        foreach ($users as &$u) {
            if ($u['id'] !== $id) continue;
            if (!empty($data['name']))     $u['name']  = trim($data['name']);
            if (!empty($data['phone']))    $u['phone'] = $this->normalizePhone($data['phone']);
            if (!empty($data['password'])) $u['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
            if (isset($data['active']))    $u['active'] = (bool)$data['active'];
            if (!empty($data['role']))     $u['role']   = $data['role'];
            $u['updated_at'] = date('Y-m-d H:i:s');
            $this->write($this->usersFile, $users);
            return true;
        }
        return false;
    }

    public function deleteUser(string $id): bool {
        $users = array_values(array_filter(
            $this->read($this->usersFile),
            fn($u) => $u['id'] !== $id
        ));
        $this->write($this->usersFile, $users);
        return true;
    }

    // ══════════════════════════════════════════════════════
    //  EMAIL + PASSWORD LOGIN
    // ══════════════════════════════════════════════════════

    public function loginWithPassword(string $email, string $password): array {
        $user = $this->getUserByEmail($email);

        if (!$user) {
            return ['success' => false, 'error' => 'No account found with that email.'];
        }
        if (!($user['active'] ?? true)) {
            return ['success' => false, 'error' => 'Your account has been deactivated.'];
        }
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'error' => 'Incorrect password.'];
        }

        $this->startUserSession($user);
        return ['success' => true, 'user' => $user];
    }

    // ══════════════════════════════════════════════════════
    //  OTP LOGIN
    // ══════════════════════════════════════════════════════

    public function generateOtp(string $phone): array {
        $phone = $this->normalizePhone($phone);
        $user  = $this->getUserByPhone($phone);

        if (!$user) {
            return ['success' => false, 'error' => 'No account found with that phone number.'];
        }
        if (!($user['active'] ?? true)) {
            return ['success' => false, 'error' => 'Your account has been deactivated.'];
        }

        // Rate limit: max 3 OTPs per 10 minutes
        $otps = $this->read($this->otpFile);
        $recent = array_filter($otps, fn($o) =>
            $o['phone'] === $phone &&
            strtotime($o['created_at']) > time() - 600
        );
        if (count($recent) >= 3) {
            return ['success' => false, 'error' => 'Too many OTP requests. Please wait 10 minutes.'];
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP (expires in 10 min)
        $otps[] = [
            'id'         => uniqid(),
            'phone'      => $phone,
            'code'       => password_hash($code, PASSWORD_BCRYPT),
            'used'       => false,
            'expires_at' => date('Y-m-d H:i:s', time() + 600),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $this->write($this->otpFile, $otps);

        return ['success' => true, 'code' => $code, 'phone' => $phone, 'user' => $user];
    }

    public function verifyOtp(string $phone, string $code): array {
        $phone = $this->normalizePhone($phone);
        $otps  = $this->read($this->otpFile);
        $now   = time();

        foreach ($otps as &$otp) {
            if ($otp['phone'] !== $phone)       continue;
            if ($otp['used'])                   continue;
            if (strtotime($otp['expires_at']) < $now) continue;
            if (!password_verify($code, $otp['code'])) continue;

            // Valid — mark used
            $otp['used'] = true;
            $this->write($this->otpFile, $otps);

            $user = $this->getUserByPhone($phone);
            if (!$user) return ['success' => false, 'error' => 'User not found.'];

            $this->startUserSession($user);
            return ['success' => true, 'user' => $user];
        }

        return ['success' => false, 'error' => 'Invalid or expired OTP code.'];
    }

    // ══════════════════════════════════════════════════════
    //  SESSION MANAGEMENT
    // ══════════════════════════════════════════════════════

    private function startUserSession(array $user): void {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email']= $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_at']  = date('Y-m-d H:i:s');
    }

    public function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public function isLoggedIn(): bool {
        return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
    }

    public function requireLogin(): void {
        if (!$this->isLoggedIn()) {
            header('Location: login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }

    public function requireAdmin(): void {
        $this->requireLogin();
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            header('Location: index.php?error=unauthorized');
            exit;
        }
    }

    public function currentUser(): ?array {
        if (!$this->isLoggedIn()) return null;
        return $this->getUserById($_SESSION['user_id']);
    }

    public function isAdmin(): bool {
        return ($_SESSION['user_role'] ?? '') === 'admin';
    }

    // ══════════════════════════════════════════════════════
    //  HELPERS
    // ══════════════════════════════════════════════════════

    private function normalizePhone(string $phone): string {
        // Strip all non-digit except leading +
        $phone = trim($phone);
        if (str_starts_with($phone, '+')) {
            return '+' . preg_replace('/\D/', '', substr($phone, 1));
        }
        return preg_replace('/\D/', '', $phone);
    }

    public function cleanExpiredOtps(): void {
        $otps = array_values(array_filter(
            $this->read($this->otpFile),
            fn($o) => strtotime($o['expires_at']) > time() - 3600
        ));
        $this->write($this->otpFile, $otps);
    }
}
