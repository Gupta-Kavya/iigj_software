<?php
require_once 'auth.php';
if (auth_is_logged_in()) {
    header('Location: index.php');
    exit;
}

define('AUTH_ALLOW_PUBLIC', true);
require_once 'db_connect.php';
require_once 'smtp_mailer.php';

$error = '';
$success = '';
$step = 'form';
$accountCreated = false;
$isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest' || !empty($_POST['ajax']);
$pending = $_SESSION['signup_pending'] ?? null;
if (is_array($pending) && !empty($pending['email']) && !empty($pending['expires_at']) && time() < (int) $pending['expires_at']) {
    $step = 'otp';
}

if (empty($_SESSION['signup_csrf'])) {
    $_SESSION['signup_csrf'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['signup_csrf'];

function signup_old($key, $fallback = '')
{
    if (isset($_POST[$key])) return (string) $_POST[$key];
    if (!empty($_SESSION['signup_pending'][$key])) return (string) $_SESSION['signup_pending'][$key];
    return $fallback;
}

function signup_send_otp($conn, $email, $name, $otp)
{
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
    $safeName = htmlspecialchars($name ?: 'User', ENT_QUOTES, 'UTF-8');
    $subject = 'Verify your SMARTLINK SOFT email';
    $body = '<div style="font-family:Arial,sans-serif;background:#f6f7fb;padding:24px">'
        . '<div style="max-width:520px;margin:auto;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:26px">'
        . '<h2 style="margin:0 0 10px;color:#111">Email verification</h2>'
        . '<p style="color:#525252">Hello ' . $safeName . ', use this OTP to complete your SMARTLINK SOFT signup.</p>'
        . '<div style="font-size:32px;font-weight:800;letter-spacing:8px;background:#111;color:#fff;border-radius:12px;padding:18px;text-align:center;margin:20px 0">' . $safeOtp . '</div>'
        . '<p style="color:#737373;font-size:13px">This OTP is valid for 10 minutes. If you did not request this, you can ignore this email.</p>'
        . '</div></div>';
    return smtp_mailer_send($conn, $email, $name, $subject, $body, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session expired. Refresh and try again.';
    } elseif (($_POST['action'] ?? '') === 'change_email') {
        unset($_SESSION['signup_pending']);
        $pending = null;
        $step = 'form';
        $success = 'You can enter a different email now.';
    } elseif (($_POST['action'] ?? '') === 'resend_otp') {
        if (!$pending || empty($pending['email'])) {
            $error = 'Please fill the signup form again.';
            $step = 'form';
        } elseif (!empty($pending['last_sent_at']) && time() - (int) $pending['last_sent_at'] < 45) {
            $error = 'Please wait a few seconds before requesting another OTP.';
            $step = 'otp';
        } else {
            $otp = (string) random_int(100000, 999999);
            $pending['otp_hash'] = password_hash($otp, PASSWORD_DEFAULT);
            $pending['expires_at'] = time() + 600;
            $pending['last_sent_at'] = time();
            $pending['attempts'] = 0;
            $_SESSION['signup_pending'] = $pending;
            [$sent, $mailMessage] = signup_send_otp($conn, $pending['email'], $pending['full_name'], $otp);
            if ($sent) {
                $success = 'A new OTP has been sent to your email.';
            } else {
                $error = 'Unable to send OTP: ' . $mailMessage;
            }
            $step = 'otp';
        }
    } elseif (($_POST['action'] ?? '') === 'verify_otp') {
        $pending = $_SESSION['signup_pending'] ?? null;
        $otpInput = preg_replace('/\D+/', '', (string) ($_POST['otp'] ?? ''));
        if (!$pending || empty($pending['email']) || time() > (int) ($pending['expires_at'] ?? 0)) {
            unset($_SESSION['signup_pending']);
            $error = 'OTP expired. Please signup again.';
            $step = 'form';
        } elseif (($pending['attempts'] ?? 0) >= 5) {
            unset($_SESSION['signup_pending']);
            $error = 'Too many wrong attempts. Please signup again.';
            $step = 'form';
        } elseif ($otpInput === '' || !password_verify($otpInput, (string) $pending['otp_hash'])) {
            $_SESSION['signup_pending']['attempts'] = (int) ($pending['attempts'] ?? 0) + 1;
            $error = 'Invalid OTP. Please check your email and try again.';
            $step = 'otp';
        } else {
            $email = strtolower(trim((string) $pending['email']));
            $check = $conn->prepare('SELECT id FROM sm_users WHERE email = ? LIMIT 1');
            $check->bind_param('s', $email);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ($exists) {
                unset($_SESSION['signup_pending']);
                $error = 'An account with this email already exists. Please login.';
                $step = 'form';
            } else {
                $passwordHash = password_hash((string) $pending['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare('INSERT INTO sm_users (full_name, company_name, email, phone, gst_number, password_hash, email_verified_at, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), "active", NOW())');
                $stmt->bind_param('ssssss', $pending['full_name'], $pending['company_name'], $email, $pending['phone'], $pending['gst_number'], $passwordHash);
                if ($stmt->execute()) {
                    unset($_SESSION['signup_pending']);
                    $pending = null;
                    $success = 'Email verified and account created successfully. You can login now.';
                    $accountCreated = true;
                    $step = 'form';
                } else {
                    $error = 'Unable to create account. Please try again.';
                    $step = 'otp';
                }
                $stmt->close();
            }
        }
    } else {
    $fullName = trim($_POST['full_name'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $gstNumber = app_normalize_gst($_POST['gst_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($fullName === '' || $email === '' || $password === '') {
        $error = 'Please fill all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Password and confirm password do not match.';
    } elseif (!app_valid_gst($gstNumber)) {
        $error = 'Please enter a valid GSTIN or leave it blank.';
    } else {
        $check = $conn->prepare('SELECT id FROM sm_users WHERE email = ? LIMIT 1');
        $check->bind_param('s', $email);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();

        if ($exists) {
            $error = 'An account with this email already exists.';
        } else {
            $otp = (string) random_int(100000, 999999);
            $_SESSION['signup_pending'] = [
                'full_name' => $fullName,
                'company_name' => $companyName,
                'email' => $email,
                'phone' => $phone,
                'gst_number' => $gstNumber,
                'password' => $password,
                'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
                'expires_at' => time() + 600,
                'last_sent_at' => time(),
                'attempts' => 0,
            ];
            $pending = $_SESSION['signup_pending'];
            [$sent, $mailMessage] = signup_send_otp($conn, $email, $fullName, $otp);
            if ($sent) {
                $success = 'We sent a 6-digit OTP to your email. Enter it below to create your account.';
                $step = 'otp';
            } else {
                unset($_SESSION['signup_pending']);
                $error = 'Unable to send OTP: ' . $mailMessage;
                $step = 'form';
            }
        }
    }
    }
}

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => $error === '',
        'message' => $error !== '' ? $error : $success,
        'type' => $error !== '' ? 'error' : 'success',
        'step' => $step,
        'email' => is_array($pending) ? ($pending['email'] ?? '') : '',
        'account_created' => $accountCreated,
        'csrf_token' => $csrfToken,
        'login_url' => 'login.php',
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Signup - SMARTLINK SOFT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/font-awesome.min.css" rel="stylesheet" type="text/css">
    <link href="../css/app-toast.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { background: radial-gradient(circle at top left, rgba(23,23,23,.08), transparent 32%), #f5f6f8; font-family: "Inter", "Segoe UI", system-ui, sans-serif; min-height: 100vh; }
        .auth-wrap { align-items: center; display: flex; min-height: 100vh; padding: 26px; }
        .auth-shell { display: grid; grid-template-columns: .85fr 1.15fr; margin: auto; max-width: 1040px; width: 100%; }
        .auth-panel { background: linear-gradient(145deg, #111, #2b2b2b); border-radius: 24px 0 0 24px; color: #fff; min-height: 660px; overflow: hidden; padding: 42px; position: relative; }
        .auth-panel:after { background: rgba(255,255,255,.08); border-radius: 999px; bottom: -120px; content: ""; height: 280px; position: absolute; right: -100px; width: 280px; }
        .brand { align-items: center; display: flex; gap: 11px; font-size: 18px; font-weight: 700; letter-spacing: .02em; }
        .brand i { align-items: center; background: #fff; border-radius: 12px; color: #111; display: flex; height: 42px; justify-content: center; width: 42px; }
        .panel-copy { bottom: 42px; left: 42px; position: absolute; right: 42px; z-index: 1; }
        .panel-copy h2 { color: #fff; font-size: 34px; font-weight: 700; letter-spacing: -.04em; line-height: 1.12; margin: 0 0 14px; }
        .panel-copy p { color: #d4d4d4; font-size: 14px; line-height: 1.8; margin: 0; }
        .auth-card { background: #fff; border: 1px solid #ececf1; border-left: 0; border-radius: 0 24px 24px 0; box-shadow: 0 24px 70px rgba(15,23,42,.12); min-height: 660px; padding: 40px; width: 100%; }
        .auth-card h1 { color: #171717; font-size: 28px; font-weight: 700; letter-spacing: -.03em; margin: 0 0 8px; }
        .auth-card p { color: #737373; line-height: 1.6; margin-bottom: 24px; }
        .stepper { display: flex; gap: 10px; margin-bottom: 24px; }
        .step { align-items: center; background: #f5f5f5; border-radius: 999px; color: #737373; display: inline-flex; font-size: 12px; font-weight: 700; gap: 8px; padding: 8px 12px; }
        .step.active { background: #171717; color: #fff; }
        .step span { align-items: center; background: rgba(255,255,255,.18); border-radius: 50%; display: flex; height: 22px; justify-content: center; width: 22px; }
        label { color: #262626; font-size: 12px; font-weight: 700; }
        .form-control { border: 1px solid #e5e5e5; border-radius: 12px; box-shadow: none; height: 46px; }
        .form-control:focus { border-color: #171717; box-shadow: 0 0 0 4px rgba(23,23,23,.07); }
        .form-grid { display: grid; gap: 14px; grid-template-columns: 1fr 1fr; }
        .form-grid .wide { grid-column: 1 / -1; }
        .btn-auth { background: #171717; border: 0; border-radius: 12px; color: #fff; font-weight: 700; height: 48px; width: 100%; }
        .btn-auth:hover, .btn-auth:focus { background: #000; color: #fff; }
        .btn-soft { background: #fff; border: 1px solid #dedede; border-radius: 12px; color: #262626; font-weight: 700; height: 44px; }
        .auth-link { margin-top: 18px; text-align: center; color: #737373; }
        .auth-link a { color: #171717; font-weight: 700; }
        .otp-box { background: #fafafa; border: 1px solid #ececec; border-radius: 18px; padding: 22px; }
        .otp-input { font-size: 28px; font-weight: 800; height: 60px; letter-spacing: 12px; text-align: center; }
        .verify-mail { color: #171717; font-weight: 800; overflow-wrap: anywhere; }
        @media (max-width: 900px) {
            .auth-shell { grid-template-columns: 1fr; max-width: 620px; }
            .auth-panel { border-radius: 24px 24px 0 0; min-height: 260px; }
            .auth-card { border-left: 1px solid #ececf1; border-radius: 0 0 24px 24px; min-height: auto; }
            .panel-copy { margin-top: 70px; position: relative; bottom: auto; left: auto; right: auto; }
        }
        @media (max-width: 560px) {
            .auth-wrap { padding: 12px; }
            .auth-card, .auth-panel { padding: 26px; }
            .form-grid { grid-template-columns: 1fr; }
            .otp-input { letter-spacing: 7px; }
        }
    </style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-shell">
    <aside class="auth-panel">
        <div class="brand"><i class="fa fa-diamond"></i> SMARTLINK SOFT</div>
        <div class="panel-copy">
            <h2>Create verified laboratory accounts.</h2>
            <p>Email OTP verification keeps fake signups away and ensures every account and certificate belongs to the correct user.</p>
        </div>
    </aside>
    <main class="auth-card">
        <div class="stepper">
            <div class="step <?php echo $step === 'form' ? 'active' : ''; ?>" id="stepDetails"><span>1</span> Details</div>
            <div class="step <?php echo $step === 'otp' ? 'active' : ''; ?>" id="stepOtp"><span>2</span> Verify email</div>
        </div>
        <h1 id="signupTitle"><?php echo $step === 'otp' ? 'Verify your email' : 'Create account'; ?></h1>
        <p id="signupIntro"><?php echo $step === 'otp' ? 'Enter the 6-digit OTP sent to your email. Your account will be created only after verification.' : 'Start with a verified account for your laboratory certificate software.'; ?></p>
        <div class="otp-box" id="otpPanel" style="<?php echo $step === 'otp' && is_array($pending) ? '' : 'display:none'; ?>">
            <p style="margin-bottom:14px">OTP sent to <span class="verify-mail" id="otpEmail"><?php echo htmlspecialchars(is_array($pending) ? ($pending['email'] ?? '') : ''); ?></span></p>
            <form method="post" id="otpForm" data-ajax-signup="1">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="verify_otp">
                <div class="form-group">
                    <label for="otp">6-digit OTP</label>
                    <input type="text" class="form-control otp-input" name="otp" id="otp" maxlength="6" inputmode="numeric" autocomplete="one-time-code" required autofocus>
                </div>
                <button type="submit" class="btn btn-auth">Verify & Create Account</button>
            </form>
            <div style="display:flex;gap:10px;margin-top:12px">
                <form method="post" id="resendOtpForm" data-ajax-signup="1" style="flex:1"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>"><input type="hidden" name="action" value="resend_otp"><button class="btn btn-soft btn-block" type="submit">Resend OTP</button></form>
                <form method="post" id="changeEmailForm" data-ajax-signup="1" style="flex:1"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>"><input type="hidden" name="action" value="change_email"><button class="btn btn-soft btn-block" type="submit">Change Email</button></form>
            </div>
        </div>
        <form method="post" id="signupForm" data-ajax-signup="1" style="<?php echo $step === 'otp' && is_array($pending) ? 'display:none' : ''; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="send_otp">
            <div class="form-grid">
            <div class="form-group">
                <label for="full_name">Full name *</label>
                <input type="text" class="form-control" name="full_name" id="full_name" value="<?php echo htmlspecialchars(signup_old('full_name')); ?>" required>
            </div>
            <div class="form-group">
                <label for="company_name">Company / Laboratory name</label>
                <input type="text" class="form-control" name="company_name" id="company_name" value="<?php echo htmlspecialchars(signup_old('company_name')); ?>">
            </div>
            <div class="form-group wide">
                <label for="email">Email address *</label>
                <input type="email" class="form-control" name="email" id="email" value="<?php echo htmlspecialchars(signup_old('email')); ?>" required>
            </div>
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="text" class="form-control" name="phone" id="phone" value="<?php echo htmlspecialchars(signup_old('phone')); ?>">
            </div>
            <div class="form-group">
                <label for="gst_number">GSTIN</label>
                <input type="text" class="form-control" name="gst_number" id="gst_number" maxlength="20" placeholder="Optional" value="<?php echo htmlspecialchars(signup_old('gst_number')); ?>">
            </div>
            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" class="form-control" name="password" id="password" minlength="6" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm password *</label>
                <input type="password" class="form-control" name="confirm_password" id="confirm_password" minlength="6" required>
            </div>
            </div>
            <button type="submit" class="btn btn-auth"><i class="fa fa-envelope-o"></i> Send OTP</button>
        </form>
        <div class="auth-link">Already have an account? <a href="login.php">Login</a></div>
    </main>
    </div>
</div>
<script src="../js/app-toast.js"></script>
<script>
(function () {
    var signupForm = document.getElementById('signupForm');
    var otpPanel = document.getElementById('otpPanel');
    var otpForm = document.getElementById('otpForm');
    var otpInput = document.getElementById('otp');
    var otpEmail = document.getElementById('otpEmail');
    var title = document.getElementById('signupTitle');
    var intro = document.getElementById('signupIntro');
    var stepDetails = document.getElementById('stepDetails');
    var stepOtp = document.getElementById('stepOtp');

    function toast(type, message) {
        if (!message) return;
        if (window.AppToast && AppToast[type]) AppToast[type](message);
        else alert(message);
    }

    function setStep(step, email) {
        var otp = step === 'otp';
        signupForm.style.display = otp ? 'none' : '';
        otpPanel.style.display = otp ? '' : 'none';
        stepDetails.classList.toggle('active', !otp);
        stepOtp.classList.toggle('active', otp);
        title.textContent = otp ? 'Verify your email' : 'Create account';
        intro.textContent = otp
            ? 'Enter the 6-digit OTP sent to your email. Your account will be created only after verification.'
            : 'Start with a verified account for your laboratory certificate software.';
        if (email) otpEmail.textContent = email;
        if (otp && otpInput) {
            otpInput.value = '';
            window.setTimeout(function () { otpInput.focus(); }, 80);
        }
    }

    function setBusy(form, busy) {
        var buttons = form.querySelectorAll('button');
        buttons.forEach(function (button) {
            button.disabled = busy;
            if (busy) {
                button.dataset.oldText = button.innerHTML;
                button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Please wait';
            } else if (button.dataset.oldText) {
                button.innerHTML = button.dataset.oldText;
                delete button.dataset.oldText;
            }
        });
    }

    function submitAjax(form) {
        var data = new FormData(form);
        data.append('ajax', '1');
        setBusy(form, true);
        fetch('signup.php', {
            method: 'POST',
            body: data,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (response) { return response.json(); })
            .then(function (result) {
                toast(result.type || (result.ok ? 'success' : 'error'), result.message || '');
                if (result.csrf_token) {
                    document.querySelectorAll('input[name="csrf_token"]').forEach(function (input) {
                        input.value = result.csrf_token;
                    });
                }
                if (result.step) setStep(result.step, result.email || '');
                if (result.account_created) {
                    signupForm.reset();
                    window.setTimeout(function () {
                        window.location.href = result.login_url || 'login.php';
                    }, 1400);
                }
            })
            .catch(function () {
                toast('error', 'Unable to process request. Please try again.');
            })
            .finally(function () {
                setBusy(form, false);
            });
    }

    document.querySelectorAll('form[data-ajax-signup="1"]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            submitAjax(form);
        });
    });
})();
</script>
</body>
</html>
