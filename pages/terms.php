<?php
define('AUTH_ALLOW_TERMS_PENDING', true);
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';

$userId = auth_current_user_id();
$error = '';
$migrationReady = false;
$check = $conn->query("SHOW TABLES LIKE 'sm_terms_acceptances'");
$migrationReady = $check && $check->num_rows > 0;
$next = auth_safe_next($_GET['next'] ?? ($_SESSION['post_terms_redirect'] ?? 'index.php'));

if (auth_terms_accepted()) {
    header('Location: ' . $next);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $migrationReady) {
    $next = auth_safe_next($_POST['next'] ?? 'index.php');
    if (!isset($_POST['responsible_use'], $_POST['accuracy_duty'], $_POST['terms_acceptance'])) {
        $error = 'Please confirm all acknowledgements before continuing.';
    } else {
        $version = SMARTLINK_TERMS_VERSION;
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $stmt = $conn->prepare(
            'INSERT INTO sm_terms_acceptances (user_id, terms_version, accepted_at, ip_address, user_agent)
             VALUES (?, ?, NOW(), ?, ?)
             ON DUPLICATE KEY UPDATE accepted_at = NOW(), ip_address = VALUES(ip_address), user_agent = VALUES(user_agent)'
        );
        if ($stmt) {
            $stmt->bind_param('isss', $userId, $version, $ip, $agent);
            if ($stmt->execute()) {
                $_SESSION['terms_version'] = SMARTLINK_TERMS_VERSION;
                unset($_SESSION['post_terms_redirect']);
                $stmt->close();
                header('Location: ' . $next);
                exit;
            }
            $stmt->close();
        }
        $error = 'Unable to record acceptance. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Responsible Use Terms - SMARTLINK SOFT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/font-awesome.min.css" rel="stylesheet">
    <style>
        *{box-sizing:border-box}body{background:#f7f7f8;color:#171717;font-family:"Inter","Segoe UI",sans-serif;margin:0}.terms-page{margin:auto;max-width:980px;padding:28px}.steps{align-items:center;display:flex;justify-content:center;margin-bottom:20px}.step{align-items:center;color:#a3a3a3;display:flex;font-size:12px;font-weight:600;gap:7px}.step-number{align-items:center;border:1px solid #d4d4d4;border-radius:50%;display:flex;height:26px;justify-content:center;width:26px}.step.done,.step.active{color:#171717}.step.done .step-number{background:#171717;border-color:#171717;color:#fff}.step.active .step-number{border-color:#171717}.step-line{background:#d4d4d4;height:1px;margin:0 10px;width:52px}.terms-shell{background:#fff;border:1px solid #ececf1;border-radius:14px;box-shadow:0 12px 40px rgba(23,23,23,.06);overflow:hidden}.terms-head{border-bottom:1px solid #ececf1;padding:24px 28px}.terms-head h1{font-size:23px;font-weight:600;margin:0 0 7px}.terms-head p{color:#737373;font-size:13px;line-height:1.55;margin:0}.terms-body{display:grid;gap:22px;grid-template-columns:minmax(0,1fr) 300px;padding:24px 28px}.terms-scroll{border:1px solid #ececf1;border-radius:10px;height:430px;overflow-y:auto;padding:5px 20px 18px}.terms-section{border-bottom:1px solid #f0f0f0;padding:16px 0}.terms-section:last-child{border-bottom:0}.terms-section h3{font-size:14px;font-weight:600;margin:0 0 7px}.terms-section p,.terms-section li{color:#616161;font-size:12.5px;line-height:1.65}.terms-section ul{margin-bottom:0;padding-left:18px}.accept-panel{background:#f7f7f8;border:1px solid #ececf1;border-radius:10px;padding:17px}.accept-panel h3{font-size:14px;font-weight:600;margin:0 0 12px}.accept-check{align-items:flex-start;background:#fff;border:1px solid #ececf1;border-radius:8px;cursor:pointer;display:flex;font-size:12px;gap:9px;line-height:1.5;margin-bottom:9px;padding:10px}.accept-check input{margin-top:3px}.terms-actions{display:grid;gap:8px;margin-top:14px}.btn-accept{background:#171717;border:0;border-radius:8px;color:#fff;font-weight:500;min-height:42px}.btn-accept:hover{background:#404040;color:#fff}.btn-decline{background:#fff;border:1px solid #e5e5e5;border-radius:8px;color:#525252;min-height:40px}.legal-note{color:#8a8a8a;font-size:11px;line-height:1.5;margin-top:12px}.alert{border-radius:9px;font-size:12px}@media(max-width:800px){.terms-body{grid-template-columns:1fr}.terms-scroll{height:360px}.terms-page{padding:16px}.step-line{width:24px}.terms-head,.terms-body{padding:20px}}
    </style>
</head>
<body>
<div class="terms-page">
    <div class="steps">
        <div class="step done"><span class="step-number"><i class="fa fa-check"></i></span> Sign in</div><span class="step-line"></span>
        <div class="step active"><span class="step-number">2</span> Accept terms</div><span class="step-line"></span>
        <div class="step"><span class="step-number">3</span> Workspace</div>
    </div>
    <div class="terms-shell">
        <div class="terms-head">
            <h1>Responsible Use Terms</h1>
            <p>Please review and accept these conditions before using the certificate software. Version <?php echo htmlspecialchars(SMARTLINK_TERMS_VERSION); ?>.</p>
        </div>
        <div class="terms-body">
            <div>
                <?php if (!$migrationReady): ?><div class="alert alert-danger">Run <strong>database_terms_acceptance_update.sql</strong> in phpMyAdmin before continuing.</div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <div class="terms-scroll">
                    <section class="terms-section"><h3>1. Authorized and lawful use</h3><p>You may use this software only for lawful laboratory, record-management and certificate-generation activities for which you are properly authorized. You must comply with applicable consumer protection, fraud, privacy, professional and industry requirements.</p></section>
                    <section class="terms-section"><h3>2. No false or fraudulent certificates</h3><p>You must not create, alter, publish or distribute fabricated, misleading or fraudulent certificates, results, QR verification records or laboratory identities. You must not impersonate another laboratory, professional, organization or certificate issuer.</p></section>
                    <section class="terms-section"><h3>3. Your testing and accuracy responsibility</h3><p>SMARTLINK SOFT provides software tools only. It does not inspect gemstones, perform laboratory testing, validate measurements, approve conclusions or certify authenticity. You and your laboratory are solely responsible for:</p><ul><li>performing appropriate tests and professional review;</li><li>entering complete and accurate data;</li><li>authorizing every certificate before issue;</li><li>correcting, withdrawing or revoking inaccurate certificates; and</li><li>maintaining supporting records and quality-control procedures.</li></ul></section>
                    <section class="terms-section"><h3>4. No endorsement or guarantee</h3><p>The availability of a generated certificate, QR code or verification result does not mean SMARTLINK SOFT has reviewed, endorsed or guaranteed the stone, test result, laboratory or certificate. You must not represent otherwise.</p></section>
                    <section class="terms-section"><h3>5. Account and credential security</h3><p>You are responsible for activity performed through your account, API keys and connected websites. Keep credentials confidential, provide access only to authorized personnel, revoke compromised API keys promptly and notify the software provider of suspected unauthorized access.</p></section>
                    <section class="terms-section"><h3>6. Data, privacy and permissions</h3><p>You must have a lawful basis and all necessary permissions to collect, store, process, display and share certificate, client, image and personal data. Do not upload information you are not authorized to process. You are responsible for notices, consent, retention and responses to data-rights requests applicable to your operations.</p></section>
                    <section class="terms-section"><h3>7. Verification websites and third parties</h3><p>You are responsible for websites, printers, QR destinations, APIs and other third-party services you configure. Protect API keys on the server and do not expose them in QR codes, browser code or public URLs.</p></section>
                    <section class="terms-section"><h3>8. Availability, backups and security</h3><p>No software service is guaranteed to be uninterrupted or error-free. Maintain independent backups and verify critical reports before printing or distribution. Do not attempt to bypass security, access another user’s data, introduce malicious code or disrupt the service.</p></section>
                    <section class="terms-section"><h3>9. Suspension and termination</h3><p>Access may be suspended or terminated for suspected fraud, unlawful use, security abuse, breach of these terms or risk to users, the service or third parties. Relevant records may be preserved where reasonably required for security, legal claims or compliance.</p></section>
                    <section class="terms-section"><h3>10. Liability and indemnity</h3><p>To the maximum extent permitted by applicable law, SMARTLINK SOFT is not responsible for losses arising from inaccurate data, improper testing, fraudulent certificates, unauthorized account use, third-party systems, printing errors, business interruption or decisions made using certificates. Your organization remains responsible for claims arising from its certificates, data and use of the software.</p></section>
                    <section class="terms-section"><h3>11. Changes and governing documents</h3><p>These terms may be updated when legal, security or product requirements change. A new version may require renewed acceptance. Your commercial agreement, privacy notice and applicable law may contain additional obligations and prevail where legally required.</p></section>
                </div>
            </div>
            <aside class="accept-panel">
                <h3>Your acknowledgement</h3>
                <form method="post">
                    <input type="hidden" name="next" value="<?php echo htmlspecialchars($next); ?>">
                    <label class="accept-check"><input type="checkbox" name="responsible_use" value="1" required><span>I will not create or assist with false, fabricated, misleading or fraudulent certificates.</span></label>
                    <label class="accept-check"><input type="checkbox" name="accuracy_duty" value="1" required><span>I understand that my laboratory is responsible for testing, accuracy, authorization and issued results.</span></label>
                    <label class="accept-check"><input type="checkbox" name="terms_acceptance" value="1" required><span>I have read and agree to these Responsible Use Terms.</span></label>
                    <div class="terms-actions">
                        <button type="submit" class="btn btn-accept" <?php echo !$migrationReady ? 'disabled' : ''; ?>>Accept and continue</button>
                        <a href="logout.php" class="btn btn-decline">Decline and sign out</a>
                    </div>
                </form>
                <p class="legal-note">This is a product-protection draft, not legal advice. Have qualified counsel adapt it to your company, contracts and jurisdiction before production use.</p>
            </aside>
        </div>
    </div>
</div>
</body>
</html>
