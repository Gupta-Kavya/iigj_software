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
        $stmt = $conn->prepare("SELECT id, full_name, email, password_hash, role, status FROM sm_users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Your account is not active. Please contact administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'] ?: 'user';
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
    <title>Login - SMARTLINK SOFT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/font-awesome.min.css" rel="stylesheet" type="text/css">
    <style>
        * { box-sizing: border-box; }
        body { background: #f7f7f8; color: #171717; font-family: "Inter", "Segoe UI", system-ui, sans-serif; min-height: 100vh; }
        .auth-wrap { align-items: center; display: flex; min-height: 100vh; padding: 28px; }
        .auth-shell { background: #fff; border: 1px solid #ececf1; border-radius: 16px; box-shadow: 0 18px 50px rgba(23,23,23,.08); display: grid; grid-template-columns: .9fr 1.1fr; margin: auto; max-width: 900px; min-height: 540px; overflow: hidden; width: 100%; }
        .auth-intro { background: #171717; color: #fff; display: flex; flex-direction: column; justify-content: space-between; padding: 42px; }
        .auth-brand { font-size: 20px; font-weight: 600; }
        .auth-intro-copy h2 { font-size: 29px; font-weight: 600; line-height: 1.25; margin: 0 0 12px; }
        .auth-intro-copy p { color: #bdbdbd; font-size: 14px; line-height: 1.7; margin: 0; }
        .auth-points { color: #d4d4d4; font-size: 12px; line-height: 1.8; margin-top: 22px; }
        .auth-points i { color: #fff; margin-right: 7px; }
        .auth-form-pane { align-items: center; display: flex; padding: 48px 54px; }
        .auth-card { margin: auto; max-width: 390px; width: 100%; }
        .auth-card h1 { color: #171717; font-size: 25px; font-weight: 600; margin: 0 0 7px; }
        .auth-card > p { color: #737373; font-size: 13px; margin-bottom: 26px; }
        .form-group label { color: #404040; font-size: 12px; font-weight: 600; margin-bottom: 7px; }
        .input-wrap { position: relative; }
        .input-wrap > i { color: #a3a3a3; left: 14px; position: absolute; top: 15px; }
        .form-control { border: 1px solid #e5e5e5; border-radius: 9px; box-shadow: none; height: 46px; padding-left: 40px; }
        .form-control:focus { border-color: #a3a3a3; box-shadow: 0 0 0 3px rgba(23,23,23,.06); }
        .btn-auth { background: #171717; border: 0; border-radius: 9px; color: #fff; font-weight: 500; height: 46px; margin-top: 5px; width: 100%; }
        .btn-auth:hover, .btn-auth:focus { background: #404040; color: #fff; }
        .auth-link { color: #737373; font-size: 13px; margin-top: 21px; text-align: center; }
        .auth-link a { color: #171717; font-weight: 600; }
        .alert { border-radius: 9px; font-size: 13px; }
        @media(max-width:760px){.auth-wrap{padding:16px}.auth-shell{display:block;max-width:480px}.auth-intro{display:none}.auth-form-pane{min-height:580px;padding:34px 28px}}
    </style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-shell">
        <section class="auth-intro">
            <div class="auth-brand"><i class="fa fa-diamond"></i> SMARTLINK SOFT</div>
            <div class="auth-intro-copy">
                <h2>Certificate operations, organized.</h2>
                <p>Manage laboratory records, card layouts, report generation, images and verification access from one workspace.</p>
                <div class="auth-points">
                    <div><i class="fa fa-check"></i> Private data for each account</div>
                    <div><i class="fa fa-check"></i> Custom ATM and A4 certificate layouts</div>
                    <div><i class="fa fa-check"></i> Controlled verification API access</div>
                </div>
            </div>
            <small style="color:#737373">Authorized laboratory use only</small>
        </section>
        <section class="auth-form-pane">
            <div class="auth-card">
                <h1>Welcome back</h1>
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
                <div class="auth-link">New here? <a href="signup.php">Create an account</a></div>
            </div>
        </section>
    </div>
</div>
</body>
</html>
