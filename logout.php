<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->logout();
header('Location: login.php?bye=1');
exit;
