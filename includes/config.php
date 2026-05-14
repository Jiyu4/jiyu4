<?php
// ============================================================
//  CLINIC CONFIGURATION
// ============================================================

define('CLINIC_NAME',    'MediCare Clinic');
define('CLINIC_PHONE',   '+63XXXXXXXXXX');
define('CLINIC_ADDRESS', '123 Health Street, Medical City');

// ============================================================
//  TEXTBEE SMS CONFIGURATION
//  Sign up at https://textbee.dev and install the Android app.
//  Get your API Key and Device ID from https://textbee.dev/dashboard
// ============================================================

define('TEXTBEE_API_KEY',   'deb4df3d-12f6-4ea2-85ad-52020fa9b982');   // from textbee.dev/dashboard
define('TEXTBEE_DEVICE_ID', '6a054d3e9b9db0a6fe6a723c'); // from textbee.dev/dashboard

// ============================================================
//  DATABASE PATHS  (JSON flat-file storage)
// ============================================================

define('DB_PATH',           __DIR__ . '/../data/');
define('PATIENTS_FILE',     DB_PATH . 'patients.json');
define('APPOINTMENTS_FILE', DB_PATH . 'appointments.json');
define('SMS_LOG_FILE',      DB_PATH . 'sms_log.json');

// ============================================================
//  TIMEZONE
// ============================================================
date_default_timezone_set('Asia/Manila');

// ============================================================
//  ERROR REPORTING  (set to 0 in production)
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
