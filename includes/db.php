<?php
require_once __DIR__ . '/config.php';

class JsonDB {

    // ── helpers ────────────────────────────────────────────

    private function read(string $file): array {
        if (!file_exists($file)) return [];
        $json = file_get_contents($file);
        return json_decode($json, true) ?? [];
    }

    private function write(string $file, array $data): bool {
        $dir = dirname($file);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }

    private function generateId(): string {
        return uniqid('', true);
    }

    // ══════════════════════════════════════════════════════
    //  PATIENTS
    // ══════════════════════════════════════════════════════

    public function getAllPatients(): array {
        $patients = $this->read(PATIENTS_FILE);
        usort($patients, fn($a, $b) => strcmp($a['last_name'], $b['last_name']));
        return $patients;
    }

    public function getPatient(string $id): ?array {
        foreach ($this->read(PATIENTS_FILE) as $p) {
            if ($p['id'] === $id) return $p;
        }
        return null;
    }

    public function searchPatients(string $query): array {
        $query = strtolower(trim($query));
        return array_values(array_filter(
            $this->read(PATIENTS_FILE),
            fn($p) => str_contains(strtolower($p['first_name'] . ' ' . $p['last_name']), $query)
                   || str_contains($p['phone'] ?? '', $query)
                   || str_contains($p['patient_id'] ?? '', $query)
        ));
    }

    public function createPatient(array $data): array {
        $patients = $this->read(PATIENTS_FILE);
        $patient = [
            'id'          => $this->generateId(),
            'patient_id'  => 'PT-' . str_pad(count($patients) + 1, 5, '0', STR_PAD_LEFT),
            'first_name'  => trim($data['first_name']),
            'last_name'   => trim($data['last_name']),
            'dob'         => $data['dob'],
            'gender'      => $data['gender'],
            'phone'       => $data['phone'],
            'email'       => $data['email'] ?? '',
            'address'     => $data['address'] ?? '',
            'blood_type'  => $data['blood_type'] ?? '',
            'allergies'   => $data['allergies'] ?? '',
            'notes'       => $data['notes'] ?? '',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];
        $patients[] = $patient;
        $this->write(PATIENTS_FILE, $patients);
        return $patient;
    }

    public function updatePatient(string $id, array $data): bool {
        $patients = $this->read(PATIENTS_FILE);
        foreach ($patients as &$p) {
            if ($p['id'] === $id) {
                foreach (['first_name','last_name','dob','gender','phone','email','address','blood_type','allergies','notes'] as $f) {
                    if (isset($data[$f])) $p[$f] = $data[$f];
                }
                $p['updated_at'] = date('Y-m-d H:i:s');
                return $this->write(PATIENTS_FILE, $patients);
            }
        }
        return false;
    }

    public function deletePatient(string $id): bool {
        $patients = array_values(array_filter(
            $this->read(PATIENTS_FILE),
            fn($p) => $p['id'] !== $id
        ));
        return $this->write(PATIENTS_FILE, $patients);
    }

    // ══════════════════════════════════════════════════════
    //  APPOINTMENTS
    // ══════════════════════════════════════════════════════

    public function getAllAppointments(): array {
        $appts = $this->read(APPOINTMENTS_FILE);
        usort($appts, fn($a, $b) => strcmp($a['date'] . $a['time'], $b['date'] . $b['time']));
        return $appts;
    }

    public function getAppointment(string $id): ?array {
        foreach ($this->read(APPOINTMENTS_FILE) as $a) {
            if ($a['id'] === $id) return $a;
        }
        return null;
    }

    public function getTodayAppointments(): array {
        $today = date('Y-m-d');
        $appts = array_filter(
            $this->read(APPOINTMENTS_FILE),
            fn($a) => $a['date'] === $today
        );
        usort($appts, fn($a, $b) => strcmp($a['time'], $b['time']));
        return array_values($appts);
    }

    public function getPatientAppointments(string $patientId): array {
        return array_values(array_filter(
            $this->read(APPOINTMENTS_FILE),
            fn($a) => $a['patient_id'] === $patientId
        ));
    }

    public function createAppointment(array $data): array {
        $appointments = $this->read(APPOINTMENTS_FILE);
        $patient = $this->getPatient($data['patient_id']);
        $appointment = [
            'id'           => $this->generateId(),
            'appt_id'      => 'APT-' . str_pad(count($appointments) + 1, 5, '0', STR_PAD_LEFT),
            'patient_id'   => $data['patient_id'],
            'patient_name' => ($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''),
            'date'         => $data['date'],
            'time'         => $data['time'],
            'reason'       => $data['reason'],
            'doctor'       => $data['doctor'] ?? '',
            'notes'        => $data['notes'] ?? '',
            'status'       => 'pending',
            'sms_sent'     => false,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ];
        $appointments[] = $appointment;
        $this->write(APPOINTMENTS_FILE, $appointments);
        return $appointment;
    }

    public function updateAppointment(string $id, array $data): bool {
        $appointments = $this->read(APPOINTMENTS_FILE);
        foreach ($appointments as &$a) {
            if ($a['id'] === $id) {
                foreach (['date','time','reason','doctor','notes','status','sms_sent'] as $f) {
                    if (array_key_exists($f, $data)) $a[$f] = $data[$f];
                }
                $a['updated_at'] = date('Y-m-d H:i:s');
                return $this->write(APPOINTMENTS_FILE, $appointments);
            }
        }
        return false;
    }

    public function deleteAppointment(string $id): bool {
        $appointments = array_values(array_filter(
            $this->read(APPOINTMENTS_FILE),
            fn($a) => $a['id'] !== $id
        ));
        return $this->write(APPOINTMENTS_FILE, $appointments);
    }

    // ══════════════════════════════════════════════════════
    //  SMS LOG
    // ══════════════════════════════════════════════════════

    public function logSms(array $data): void {
        $logs = $this->read(SMS_LOG_FILE);
        $logs[] = array_merge($data, [
            'id'         => $this->generateId(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->write(SMS_LOG_FILE, $logs);
    }

    public function getSmsLogs(): array {
        $logs = $this->read(SMS_LOG_FILE);
        usort($logs, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return $logs;
    }

    // ══════════════════════════════════════════════════════
    //  STATS
    // ══════════════════════════════════════════════════════

    public function getStats(): array {
        $patients     = $this->read(PATIENTS_FILE);
        $appointments = $this->read(APPOINTMENTS_FILE);
        $today        = date('Y-m-d');

        return [
            'total_patients'        => count($patients),
            'today_appointments'    => count(array_filter($appointments, fn($a) => $a['date'] === $today)),
            'pending_appointments'  => count(array_filter($appointments, fn($a) => $a['status'] === 'pending')),
            'completed_appointments'=> count(array_filter($appointments, fn($a) => $a['status'] === 'completed')),
        ];
    }
}
