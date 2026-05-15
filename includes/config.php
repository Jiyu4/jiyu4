<?php
// ============================================================
//  CLINIC CONFIGURATION
// ============================================================

define('CLINIC_NAME',    'MediCare Clinic');
define('CLINIC_PHONE',   '+63XXXXXXXXXX');
define('CLINIC_ADDRESS', '123 Health Street, Medical City');

// ============================================================
//  MYSQL DATABASE CONFIGURATION
//  Local:  use 127.0.0.1, root, your local password, db name
//  Render: paste the values from your Render MySQL add-on
//          (or use PlanetScale / FreeSQLDatabase / Railway)
// ============================================================

define('DB_HOST',     getenv('DB_HOST')     ?: '127.0.0.1');
define('DB_PORT',     getenv('DB_PORT')     ?: '3306');
define('DB_NAME',     getenv('DB_NAME')     ?: 'clinic_db');
define('DB_USER',     getenv('DB_USER')     ?: 'root');
define('DB_PASS',     getenv('DB_PASS')     ?: '');
define('DB_CHARSET',  'utf8mb4');

// ============================================================
//  TEXTBEE SMS CONFIGURATION
//  Sign up at https://textbee.dev and install the Android app.
//  Get your API Key and Device ID from https://textbee.dev/dashboard
// ============================================================

define('TEXTBEE_API_KEY',   getenv('deb4df3d-12f6-4ea2-85ad-52020fa9b982')   ?: 'deb4df3d-12f6-4ea2-85ad-52020fa9b982');
define('TEXTBEE_DEVICE_ID', getenv('6a054d3e9b9db0a6fe6a723c') ?: '6a054d3e9b9db0a6fe6a723c');

// ============================================================
//  TIMEZONE
// ============================================================
date_default_timezone_set('Asia/Manila');

// ============================================================
//  ERROR REPORTING  (set to 0 in production)
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
