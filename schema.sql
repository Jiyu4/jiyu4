-- ============================================================
--  MediCare Clinic — MySQL Database Schema
--  Run once:  mysql -u root -p clinic_db < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS clinic_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE clinic_db;

-- ── Users (admin / staff / patient) ──────────────────────────

CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    phone       VARCHAR(30)  DEFAULT '',
    password    VARCHAR(255) NOT NULL,
    role        ENUM('admin','staff','patient') NOT NULL DEFAULT 'patient',
    -- Extra fields for patient role
    dob         DATE         DEFAULT NULL,
    gender      ENUM('Male','Female','Other') DEFAULT NULL,
    address     TEXT         DEFAULT NULL,
    blood_type  VARCHAR(5)   DEFAULT '',
    allergies   TEXT         DEFAULT NULL,
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_role  (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Patients (admin-managed records) ─────────────────────────

CREATE TABLE IF NOT EXISTS patients (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id  VARCHAR(20)  NOT NULL UNIQUE,
    user_id     INT UNSIGNED DEFAULT NULL,   -- links to users table if self-registered
    first_name  VARCHAR(100) NOT NULL,
    last_name   VARCHAR(100) NOT NULL,
    dob         DATE         NOT NULL,
    gender      ENUM('Male','Female','Other') NOT NULL,
    phone       VARCHAR(30)  NOT NULL,
    email       VARCHAR(150) DEFAULT '',
    address     TEXT         DEFAULT NULL,
    blood_type  VARCHAR(5)   DEFAULT '',
    allergies   TEXT         DEFAULT NULL,
    notes       TEXT         DEFAULT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_last_name  (last_name),
    INDEX idx_phone      (phone),
    INDEX idx_patient_id (patient_id),
    INDEX idx_user_id    (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Appointments ─────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS appointments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appt_id     VARCHAR(20)  NOT NULL UNIQUE,
    patient_id  INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED DEFAULT NULL,   -- the patient's user account if linked
    date        DATE         NOT NULL,
    time        TIME         NOT NULL,
    reason      VARCHAR(255) NOT NULL,
    doctor      VARCHAR(150) DEFAULT '',
    notes       TEXT         DEFAULT NULL,
    status      ENUM('pending','confirmed','completed','cancelled','no-show') NOT NULL DEFAULT 'pending',
    sms_sent    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL,
    INDEX idx_date       (date),
    INDEX idx_status     (status),
    INDEX idx_patient_id (patient_id),
    INDEX idx_user_id    (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── OTP Codes ─────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS otp_codes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone       VARCHAR(30)  NOT NULL,
    code        VARCHAR(255) NOT NULL,
    used        TINYINT(1)   NOT NULL DEFAULT 0,
    expires_at  DATETIME     NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone   (phone),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SMS Log ───────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS sms_log (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type           VARCHAR(20)  NOT NULL DEFAULT 'custom',
    appointment_id INT UNSIGNED DEFAULT NULL,
    patient_id     INT UNSIGNED DEFAULT NULL,
    patient_name   VARCHAR(200) DEFAULT '',
    `to`           VARCHAR(30)  NOT NULL,
    message        TEXT         NOT NULL,
    success        TINYINT(1)   NOT NULL DEFAULT 0,
    error          TEXT         DEFAULT NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_patient_id (patient_id),
    INDEX idx_created    (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
