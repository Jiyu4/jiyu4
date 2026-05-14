<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class TwilioSMS {

    private string $accountSid;
    private string $authToken;
    private string $fromNumber;
    private JsonDB $db;

    public function __construct() {
        $this->accountSid = TWILIO_ACCOUNT_SID;
        $this->authToken  = TWILIO_AUTH_TOKEN;
        $this->fromNumber = TWILIO_FROM_NUMBER;
        $this->db         = new JsonDB();
    }

    /**
     * Send a raw SMS message to a phone number.
     */
    public function send(string $to, string $message): array {
        // Guard: check credentials are configured
        if ($this->accountSid === 'YOUR_TWILIO_ACCOUNT_SID') {
            return [
                'success' => false,
                'error'   => 'Twilio is not configured. Please update includes/config.php with your credentials.',
            ];
        }

        $url  = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";
        $data = http_build_query([
            'To'   => $to,
            'From' => $this->fromNumber,
            'Body' => $message,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => "{$this->accountSid}:{$this->authToken}",
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'error' => 'cURL error: ' . $curlError];
        }

        $body = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'sid'     => $body['sid'] ?? null,
                'status'  => $body['status'] ?? 'sent',
            ];
        }

        return [
            'success' => false,
            'error'   => $body['message'] ?? 'Unknown Twilio error (HTTP ' . $httpCode . ')',
            'code'    => $body['code'] ?? null,
        ];
    }

    /**
     * Send an appointment reminder SMS.
     */
    public function sendAppointmentReminder(string $appointmentId): array {
        $appt = $this->db->getAppointment($appointmentId);
        if (!$appt) {
            return ['success' => false, 'error' => 'Appointment not found'];
        }

        $patient = $this->db->getPatient($appt['patient_id']);
        if (!$patient || empty($patient['phone'])) {
            return ['success' => false, 'error' => 'Patient phone number not found'];
        }

        $date    = date('F j, Y', strtotime($appt['date']));
        $time    = date('g:i A', strtotime($appt['time']));
        $message = "Hello {$patient['first_name']}! This is a reminder from " . CLINIC_NAME . ". "
                 . "You have an appointment on {$date} at {$time}. "
                 . "Reason: {$appt['reason']}. "
                 . "Please call " . CLINIC_PHONE . " to reschedule if needed. Thank you!";

        $result = $this->send($patient['phone'], $message);

        // Log it
        $this->db->logSms([
            'type'           => 'reminder',
            'appointment_id' => $appointmentId,
            'patient_id'     => $patient['id'],
            'patient_name'   => $patient['first_name'] . ' ' . $patient['last_name'],
            'to'             => $patient['phone'],
            'message'        => $message,
            'success'        => $result['success'],
            'error'          => $result['error'] ?? null,
            'twilio_sid'     => $result['sid'] ?? null,
        ]);

        // Mark SMS sent on appointment
        if ($result['success']) {
            $this->db->updateAppointment($appointmentId, ['sms_sent' => true]);
        }

        return $result;
    }

    /**
     * Send a custom SMS to a patient.
     */
    public function sendCustom(string $patientId, string $message): array {
        $patient = $this->db->getPatient($patientId);
        if (!$patient || empty($patient['phone'])) {
            return ['success' => false, 'error' => 'Patient phone number not found'];
        }

        $result = $this->send($patient['phone'], $message);

        $this->db->logSms([
            'type'        => 'custom',
            'patient_id'  => $patient['id'],
            'patient_name'=> $patient['first_name'] . ' ' . $patient['last_name'],
            'to'          => $patient['phone'],
            'message'     => $message,
            'success'     => $result['success'],
            'error'       => $result['error'] ?? null,
            'twilio_sid'  => $result['sid'] ?? null,
        ]);

        return $result;
    }

    /**
     * Check if Twilio credentials are configured.
     */
    public function isConfigured(): bool {
        return $this->accountSid !== 'YOUR_TWILIO_ACCOUNT_SID'
            && $this->authToken  !== 'YOUR_TWILIO_AUTH_TOKEN'
            && $this->fromNumber !== 'YOUR_TWILIO_PHONE_NUMBER';
    }
}
