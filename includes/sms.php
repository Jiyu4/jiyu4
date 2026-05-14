<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class SMSGateway {

    private string $apiKey;
    private string $deviceId;
    private string $apiUrl;
    private JsonDB $db;

    public function __construct() {
        $this->apiKey   = TEXTBEE_API_KEY;
        $this->deviceId = TEXTBEE_DEVICE_ID;
        $this->apiUrl   = "https://api.textbee.dev/api/v1/gateway/devices/{$this->deviceId}/send-sms";
        $this->db       = new JsonDB();
    }

    // ══════════════════════════════════════════════════════
    //  CORE SEND
    //  POST https://api.textbee.dev/api/v1/gateway/devices/{deviceId}/send-sms
    //  Headers: x-api-key: YOUR_API_KEY
    //  Body:    { "recipients": ["+63..."], "message": "Hello" }
    // ══════════════════════════════════════════════════════

    public function send(string $phone, string $message): array {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error'   => 'TextBee is not configured. Add your API Key and Device ID in Settings.',
            ];
        }

        $payload = json_encode([
            'recipients' => [$phone],
            'message'    => $message,
        ]);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
            ],
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'error' => 'Connection error: ' . $curlError];
        }

        $body = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success'  => true,
                'status'   => 'sent',
                'response' => $body,
            ];
        }

        // TextBee error responses
        $errMsg = $body['message'] ?? $body['error'] ?? "TextBee returned HTTP {$httpCode}";
        return ['success' => false, 'error' => $errMsg, 'response' => $body];
    }

    // ══════════════════════════════════════════════════════
    //  APPOINTMENT REMINDER
    // ══════════════════════════════════════════════════════

    public function sendAppointmentReminder(string $appointmentId): array {
        $appt = $this->db->getAppointment($appointmentId);
        if (!$appt) {
            return ['success' => false, 'error' => 'Appointment not found'];
        }

        $patient = $this->db->getPatient($appt['patient_id']);
        if (!$patient || empty($patient['phone'])) {
            return ['success' => false, 'error' => 'Patient has no phone number on record'];
        }

        $date    = date('F j, Y', strtotime($appt['date']));
        $time    = date('g:i A', strtotime($appt['time']));
        $message = "Hello {$patient['first_name']}! Reminder from " . CLINIC_NAME . ": "
                 . "You have an appointment on {$date} at {$time}. "
                 . "Reason: {$appt['reason']}. "
                 . "Call " . CLINIC_PHONE . " to reschedule. Thank you!";

        $result = $this->send($patient['phone'], $message);

        $this->db->logSms([
            'type'           => 'reminder',
            'appointment_id' => $appointmentId,
            'patient_id'     => $patient['id'],
            'patient_name'   => $patient['first_name'] . ' ' . $patient['last_name'],
            'to'             => $patient['phone'],
            'message'        => $message,
            'success'        => $result['success'],
            'error'          => $result['error'] ?? null,
        ]);

        if ($result['success']) {
            $this->db->updateAppointment($appointmentId, ['sms_sent' => true]);
        }

        return $result;
    }

    // ══════════════════════════════════════════════════════
    //  CUSTOM MESSAGE TO A PATIENT
    // ══════════════════════════════════════════════════════

    public function sendCustom(string $patientId, string $message): array {
        $patient = $this->db->getPatient($patientId);
        if (!$patient || empty($patient['phone'])) {
            return ['success' => false, 'error' => 'Patient has no phone number on record'];
        }

        $result = $this->send($patient['phone'], $message);

        $this->db->logSms([
            'type'         => 'custom',
            'patient_id'   => $patient['id'],
            'patient_name' => $patient['first_name'] . ' ' . $patient['last_name'],
            'to'           => $patient['phone'],
            'message'      => $message,
            'success'      => $result['success'],
            'error'        => $result['error'] ?? null,
        ]);

        return $result;
    }

    // ══════════════════════════════════════════════════════
    //  STATUS / CONFIG HELPERS
    // ══════════════════════════════════════════════════════

    public function isConfigured(): bool {
        return $this->apiKey   !== 'YOUR_TEXTBEE_API_KEY'
            && $this->deviceId !== 'YOUR_TEXTBEE_DEVICE_ID'
            && !empty($this->apiKey)
            && !empty($this->deviceId);
    }

    public function getApiKey(): string  { return $this->apiKey; }
    public function getDeviceId(): string { return $this->deviceId; }

    // For backwards-compat with sms.php/settings.php status checks
    public function isReachable(): bool { return $this->isConfigured(); }
    public function getGatewayUrl(): string { return "https://api.textbee.dev"; }
}
