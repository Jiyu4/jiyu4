# MediCare Clinic System

A PHP + JSON flat-file clinic records and appointment management system with Twilio SMS integration.

## Features
- Patient registration and records management
- Appointment booking and scheduling
- Status tracking (pending / confirmed / completed / cancelled / no-show)
- SMS reminders via Twilio API (individual + bulk)
- SMS delivery log
- In-browser settings panel to configure Twilio credentials

## Requirements
- PHP 8.0+
- PHP `curl` extension enabled
- Apache/Nginx with write access to the `data/` folder

## Installation

1. **Copy** the entire `clinic/` folder to your web server's document root (e.g. `htdocs/clinic/` or `/var/www/html/clinic/`)

2. **Set permissions** so PHP can write to the data directory:
   ```bash
   chmod 755 data/
   chmod 644 data/*.json
   ```

3. **Open** `includes/config.php` and fill in your details:
   ```php
   define('CLINIC_NAME', 'Your Clinic Name');
   define('TWILIO_ACCOUNT_SID', 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
   define('TWILIO_AUTH_TOKEN',  'your_auth_token_here');
   define('TWILIO_FROM_NUMBER', '+1XXXXXXXXXX');
   ```
   Or use the built-in **Settings** page to enter them via the browser.

4. **Visit** `http://localhost/clinic/` in your browser.

## Twilio Setup
1. Sign up at https://www.twilio.com (free trial available)
2. From the Console Dashboard, copy your **Account SID** and **Auth Token**
3. Under Phone Numbers → Manage → Active Numbers, copy your number
4. Paste all three into Settings or `includes/config.php`

## File Structure
```
clinic/
├── index.php              Dashboard
├── patients.php           Patient records (list / view / create / edit / delete)
├── appointments.php       Appointments (list / book / edit / delete)
├── sms.php                SMS center + log
├── settings.php           Clinic & Twilio configuration
├── assets/
│   └── style.css          Stylesheet
├── includes/
│   ├── config.php         ← PUT YOUR API KEYS HERE
│   ├── db.php             JSON database class
│   ├── twilio.php         Twilio SMS helper
│   └── nav.php            Sidebar navigation
└── data/                  JSON flat-file storage (auto-created)
    ├── patients.json
    ├── appointments.json
    └── sms_log.json
```

## Notes
- The `data/` directory is protected by `.htaccess` to block direct web access.
- No MySQL required — all data stored as JSON files.
- For production, consider adding authentication/login protection.
