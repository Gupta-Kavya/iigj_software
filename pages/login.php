<?php
require_once 'auth.php';
if (auth_is_logged_in()) {
    header('Location: ' . (auth_terms_accepted() ? 'index.php' : 'terms.php'));
    exit;
}

define('AUTH_ALLOW_PUBLIC', true);
require_once 'db_connect.php';

$error = '';
$next = auth_safe_next($_GET['next'] ?? 'index.php');
if (isset($_GET['account']) && $_GET['account'] === 'inactive') {
    $error = 'Your account is inactive. Please contact the administrator.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $next = auth_safe_next($_POST['next'] ?? 'index.php');

    if ($email === '' || $password === '') {
        $error = 'Please enter email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, email, password_hash, role, status, branch_location FROM sm_users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Your account is not active. Please contact administrator.';
        } elseif (empty(($ipStatus = auth_allowed_ip_status($conn, $user['branch_location'] ?? '', $user['role'] ?? ''))['allowed'])) {
            $error = 'Access denied from this IP address (' . ($ipStatus['ip'] ?? '') . '). Contact super admin.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'] ?: 'user';
            $_SESSION['branch_location'] = $user['branch_location'] ?? '';
            unset($_SESSION['terms_version']);

            $termsStmt = @$conn->prepare('SELECT terms_version FROM sm_terms_acceptances WHERE user_id = ? AND terms_version = ? LIMIT 1');
            if ($termsStmt) {
                $termsVersion = SMARTLINK_TERMS_VERSION;
                $loggedInUserId = (int) $_SESSION['user_id'];
                $termsStmt->bind_param('is', $loggedInUserId, $termsVersion);
                $termsStmt->execute();
                if ($termsStmt->get_result()->fetch_assoc()) {
                    $_SESSION['terms_version'] = SMARTLINK_TERMS_VERSION;
                }
                $termsStmt->close();
            }

            $update = $conn->prepare('UPDATE sm_users SET last_login_at = NOW() WHERE id = ?');
            $update->bind_param('i', $_SESSION['user_id']);
            $update->execute();
            $update->close();

            if (auth_terms_accepted()) {
                header('Location: ' . $next);
            } else {
                $_SESSION['post_terms_redirect'] = $next;
                header('Location: terms.php');
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - IIGJ RLC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/font-awesome.min.css" rel="stylesheet" type="text/css">
    <style>
        * { box-sizing: border-box; }
        body { background: #f7f7f8; color: #171717; font-family: "Inter", "Segoe UI", system-ui, sans-serif; min-height: 100vh; }
        .auth-wrap { align-items: center; display: flex; min-height: 100vh; padding: 18px; }
        .auth-shell { background: #fff; border: 1px solid #ececf1; border-radius: 12px; box-shadow: 0 14px 36px rgba(23,23,23,.08); margin: auto; max-width: 390px; min-height: 410px; overflow: hidden; width: 100%; }
        .auth-form-pane { align-items: center; display: flex; min-height: 410px; padding: 30px 36px; }
        .auth-card { margin: auto; max-width: 318px; width: 100%; }
        .auth-logo { display: block; height: 54px; margin: 0 auto 17px; object-fit: contain; width: 128px; }
        .auth-card h1 { color: #171717; font-size: 21px; font-weight: 600; margin: 0 0 5px; }
        .auth-card > p { color: #737373; font-size: 12px; margin-bottom: 18px; text-align: center; }
        .form-group { margin-bottom: 13px; }
        .form-group label { color: #404040; font-size: 11px; font-weight: 600; margin-bottom: 5px; }
        .input-wrap { position: relative; }
        .input-wrap > i { color: #a3a3a3; font-size: 12px; left: 12px; position: absolute; top: 12px; }
        .form-control { border: 1px solid #e5e5e5; border-radius: 7px; box-shadow: none; font-size: 13px; height: 38px; padding-left: 34px; }
        .form-control:focus { border-color: #a3a3a3; box-shadow: 0 0 0 3px rgba(23,23,23,.06); }
        .btn-auth { background: #171717; border: 0; border-radius: 7px; color: #fff; font-size: 13px; font-weight: 500; height: 38px; margin-top: 3px; width: 100%; }
        .btn-auth:hover, .btn-auth:focus { background: #404040; color: #fff; }
        .alert { border-radius: 7px; font-size: 12px; padding: 9px 11px; }
        @media(max-width:760px){.auth-wrap{padding:14px}.auth-shell{max-width:390px}.auth-form-pane{min-height:410px;padding:28px 24px}}
    </style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-shell">
        <section class="auth-form-pane">
            <div class="auth-card">
                <img class="auth-logo" src="assets/agreement-gjepc.svg" alt="GJEPC">
                <h1 style="text-align:center">Welcome back</h1>
                <p>Enter your account details to continue.</p>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <form method="post">
                    <input type="hidden" name="next" value="<?php echo htmlspecialchars($next); ?>">
                    <div class="form-group">
                        <label for="email">Email address</label>
                        <div class="input-wrap"><i class="fa fa-envelope-o"></i><input type="email" class="form-control" name="email" id="email" required autofocus autocomplete="email"></div>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrap"><i class="fa fa-lock"></i><input type="password" class="form-control" name="password" id="password" required autocomplete="current-password"></div>
                    </div>
                    <button type="submit" class="btn btn-auth">Sign in <i class="fa fa-arrow-right" style="margin-left:6px"></i></button>
                </form>
            </div>
        </section>
    </div>
</div>
</body>
</html>
