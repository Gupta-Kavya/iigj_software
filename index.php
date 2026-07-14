<?php
session_start();

$target = isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0
    ? 'pages/index.php'
    : 'pages/login.php';

header('Location: ' . $target);
exit;
