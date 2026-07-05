<?php
require_once 'auth.php';
auth_require_login();
header('Location: settings.php', true, 302);
exit;
