<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=clinic_db', 'root', '');
    echo '✅ Connected successfully!';
} catch (PDOException $e) {
    echo '❌ ' . $e->getMessage();
}