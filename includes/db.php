<?php
require_once __DIR__ . '/config.php';

class JsonDB {

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
                <p>Check your DB_HOST, DB_NAME, DB_USER, DB_PASS in <code>includes/config.php</code> or environment variables.</p>
            </div>');
        }
    }

    public function getPdo(): PDO { return $this->pdo; }

    // ══════════════════════════════════════════════════════
    //  PATIENTS
    // ══════════════════════════════════════════════════════

    public function getAllPatients(): array {
        $stmt = $this->pdo->query(
            'SELECT * FROM patients ORDER BY last_name ASC, first_name ASC'
        );
        return $stmt->fetchAll();
    }

    public function getPatient(string $id): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM patients WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function searchPatients(string $query): array {
        $like = '%' . $query . '%';
        $stmt = $this->pdo->prepare(
            'SELECT * FROM patients
             WHERE CONCAT(first_name," ",last_name) LIKE ?
                OR phone      LIKE ?
                OR patient_id LIKE ?
             ORDER BY last_name ASC'
        );
        $stmt->execute([$like, $like, $like]);
        return $stmt->fetchAll();
    }

    public function createPatient(array $data): array {
        // Auto-generate patient_id
        $count = $this->pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();
        $patientId = 'PT-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        $stmt = $this->pdo->prepare(
            'INSERT INTO patients
             (patient_id, first_name, last_name, dob, gender, phone, email, address, blood_type, allergies, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $patientId,
            trim($data['first_name']),
            trim($data['last_name']),
            $data['dob'],
            $data['gender'],
            $data['phone'],
            $data['email']      ?? '',
            $data['address']    ?? '',
            $data['blood_type'] ?? '',
            $data['allergies']  ?? '',
            $data['notes']      ?? '',
        ]);

        return $this->getPatient((string) $this->pdo->lastInsertId());
    }

    public function updatePatient(string $id, array $data): bool {
        $fields = ['first_name','last_name','dob','gender','phone','email','address','blood_type','allergies','notes'];
        $sets   = [];
        $vals   = [];
        foreach ($fields as $f) {
            if (isset($data[$f])) {
                $sets[] = "{$f} = ?";
                $vals[] = $data[$f];
            }
        }
        if (empty($sets)) return false;
        $sets[] = 'updated_at = NOW()';
        $vals[] = $id;
        $stmt = $this->pdo->prepare('UPDATE patients SET ' . implode(', ', $sets) . ' WHERE id = ?');
        return $stmt->execute($vals);
    }

    public function deletePatient(string $id): bool {
        $stmt = $this->pdo->prepare('DELETE FROM patients WHERE id = ?');
        return $stmt->execute([$id]);
    }

    // ══════════════════════════════════════════════════════
    //  APPOINTMENTS
    // ══════════════════════════════════════════════════════

    public function getAllAppointments(): array {
        $stmt = $this->pdo->query(
            'SELECT a.*, CONCAT(p.first_name," ",p.last_name) AS patient_name
             FROM appointments a
             LEFT JOIN patients p ON p.id = a.patient_id
             ORDER BY a.date ASC, a.time ASC'
        );
        return $stmt->fetchAll();
    }

    public function getAppointment(string $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, CONCAT(p.first_name," ",p.last_name) AS patient_name
             FROM appointments a
             LEFT JOIN patients p ON p.id = a.patient_id
             WHERE a.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getTodayAppointments(): array {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, CONCAT(p.first_name," ",p.last_name) AS patient_name
             FROM appointments a
             LEFT JOIN patients p ON p.id = a.patient_id
             WHERE a.date = CURDATE()
             ORDER BY a.time ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getPatientAppointments(string $patientId): array {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM appointments WHERE patient_id = ? ORDER BY date DESC, time DESC'
        );
        $stmt->execute([$patientId]);
        return $stmt->fetchAll();
    }

    public function createAppointment(array $data): array {
        $count  = $this->pdo->query('SELECT COUNT(*) FROM appointments')->fetchColumn();
        $apptId = 'APT-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        $stmt = $this->pdo->prepare(
            'INSERT INTO appointments (appt_id, patient_id, user_id, date, time, reason, doctor, notes, status, sms_sent)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $apptId,
            $data['patient_id'],
            $data['user_id'] ?? null,
            $data['date'],
            $data['time'],
            $data['reason'],
            $data['doctor'] ?? '',
            $data['notes']  ?? '',
            'pending',
            0,
        ]);

        return $this->getAppointment((string) $this->pdo->lastInsertId());
    }

    public function updateAppointment(string $id, array $data): bool {
        $fields = ['date','time','reason','doctor','notes','status'];
        $sets   = [];
        $vals   = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "{$f} = ?";
                $vals[] = $data[$f];
            }
        }
        if (array_key_exists('sms_sent', $data)) {
            $sets[] = 'sms_sent = ?';
            $vals[] = $data['sms_sent'] ? 1 : 0;
        }
        if (empty($sets)) return false;
        $sets[] = 'updated_at = NOW()';
        $vals[] = $id;
        $stmt = $this->pdo->prepare('UPDATE appointments SET ' . implode(', ', $sets) . ' WHERE id = ?');
        return $stmt->execute($vals);
    }

    public function deleteAppointment(string $id): bool {
        $stmt = $this->pdo->prepare('DELETE FROM appointments WHERE id = ?');
        return $stmt->execute([$id]);
    }

    // ══════════════════════════════════════════════════════
    //  SMS LOG
    // ══════════════════════════════════════════════════════

    public function logSms(array $data): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sms_log (type, appointment_id, patient_id, patient_name, `to`, message, success, error)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $data['type']           ?? 'custom',
            $data['appointment_id'] ?? null,
            $data['patient_id']     ?? null,
            $data['patient_name']   ?? '',
            $data['to']             ?? '',
            $data['message']        ?? '',
            ($data['success'] ?? false) ? 1 : 0,
            $data['error']          ?? null,
        ]);
    }

    public function getSmsLogs(): array {
        $stmt = $this->pdo->query('SELECT * FROM sms_log ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    public function getRecentPendingAppointments(int $limit = 5): array {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, CONCAT(p.first_name," ",p.last_name) AS patient_name
             FROM appointments a
             LEFT JOIN patients p ON p.id = a.patient_id
             WHERE a.status = "pending"
             ORDER BY a.date ASC, a.time ASC
             LIMIT ?'
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    // ══════════════════════════════════════════════════════
    //  STATS
    // ══════════════════════════════════════════════════════

    public function getStats(): array {
        return [
            'total_patients' => $this->pdo->query(
                'SELECT COUNT(*) FROM patients'
            )->fetchColumn(),

            'today_appointments' => $this->pdo->query(
                'SELECT COUNT(*) FROM appointments WHERE date = CURDATE()'
            )->fetchColumn(),

            'pending_appointments' => $this->pdo->query(
                'SELECT COUNT(*) FROM appointments WHERE status = "pending"'
            )->fetchColumn(),

            'completed_appointments' => $this->pdo->query(
                'SELECT COUNT(*) FROM appointments WHERE status = "completed"'
            )->fetchColumn(),
        ];
    }
}
