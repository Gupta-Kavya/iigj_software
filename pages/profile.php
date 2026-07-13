<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'atm_config.php';
require_once 'user_branch_helper.php';

$userId = auth_current_user_id();
user_branch_location_ready($conn);
if (!isset($_SESSION['profile_csrf'])) {
    $_SESSION['profile_csrf'] = bin2hex(random_bytes(24));
}
$csrfToken = $_SESSION['profile_csrf'];

function profile_redirect($message, $type = 'success')
{
    $_SESSION['profile_flash'] = ['message' => $message, 'type' => $type];
    header('Location: profile.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        profile_redirect('Your session expired. Refresh the page and try again.', 'error');
    }

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'update_profile') {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $branchLocation = auth_is_super_admin()
            ? user_branch_location_clean($_POST['branch_location'] ?? '', $conn)
            : user_branch_location_for_user($conn, $userId);
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));

        if ($fullName === '' || strlen($fullName) > 120) {
            profile_redirect('Please enter a valid full name.', 'error');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            profile_redirect('Please enter a valid email address.', 'error');
        }
        if (strlen($phone) > 30) {
            profile_redirect('Phone number is too long.', 'error');
        }

        $check = $conn->prepare('SELECT id FROM sm_users WHERE email = ? AND id <> ? LIMIT 1');
        $check->bind_param('si', $email, $userId);
        $check->execute();
        $emailExists = $check->get_result()->fetch_assoc();
        $check->close();
        if ($emailExists) {
            profile_redirect('That email address is already used by another account.', 'error');
        }

        $stmt = $conn->prepare('UPDATE sm_users SET full_name = ?, branch_location = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?');
        if (!$stmt) {
            profile_redirect('Unable to prepare the profile update.', 'error');
        }
        $stmt->bind_param('ssssi', $fullName, $branchLocation, $email, $phone, $userId);
        $saved = $stmt->execute();
        $stmt->close();
        if (!$saved) {
            profile_redirect('Unable to update your profile.', 'error');
        }

        $_SESSION['user_name'] = $fullName;
        $_SESSION['user_email'] = $email;
        profile_redirect('Profile details updated successfully.');
    }

    if ($action === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        $stmt = $conn->prepare('SELECT password_hash FROM sm_users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$account || !password_verify($currentPassword, $account['password_hash'])) {
            profile_redirect('The current password is incorrect.', 'error');
        }
        if (strlen($newPassword) < 8) {
            profile_redirect('The new password must be at least 8 characters.', 'error');
        }
        if ($newPassword !== $confirmPassword) {
            profile_redirect('The new password and confirmation do not match.', 'error');
        }
        if (password_verify($newPassword, $account['password_hash'])) {
            profile_redirect('Choose a password different from your current password.', 'error');
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $conn->prepare('UPDATE sm_users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
        $update->bind_param('si', $passwordHash, $userId);
        $saved = $update->execute();
        $update->close();
        if (!$saved) {
            profile_redirect('Unable to change your password.', 'error');
        }
        session_regenerate_id(true);
        profile_redirect('Password changed successfully.');
    }

    profile_redirect('Unknown profile action.', 'error');
}

$stmt = $conn->prepare('SELECT id, full_name, branch_location, email, phone, status, last_login_at, created_at, updated_at FROM sm_users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) {
    header('Location: logout.php');
    exit;
}

$reportCount = 0;
$imageCount = 0;
$reportScopeSql = user_branch_location_scope_sql($conn, $userId, 'location');
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM sm_form_data WHERE {$reportScopeSql}");
$stmt->execute();
$reportCount = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

$imageDir = atm_user_image_dir('st_images');
if (is_dir($imageDir)) {
    foreach (['jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG'] as $ext) {
        $images = glob($imageDir . '/*.' . $ext);
        $imageCount += is_array($images) ? count($images) : 0;
    }
}

$flash = $_SESSION['profile_flash'] ?? null;
unset($_SESSION['profile_flash']);
include 'assets/navbar.php';
?>
<style>
.profile-page{padding-bottom:36px}.profile-hero{align-items:center;background:linear-gradient(135deg,#171717,#343434);border-radius:14px;color:#fff;display:flex;gap:18px;margin-bottom:18px;padding:25px}.profile-avatar{align-items:center;background:#fff;border-radius:14px;color:#171717;display:flex;font-size:29px;font-weight:600;height:70px;justify-content:center;width:70px}.profile-hero h1{border:0;color:#fff;font-size:23px;margin:0 0 5px;padding:0}.profile-hero p{color:#d4d4d4;margin:0}.profile-status{background:#dcfce7;border-radius:999px;color:#166534;font-size:11px;font-weight:600;margin-left:auto;padding:6px 10px;text-transform:capitalize}.profile-stats{display:grid;gap:14px;grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:18px}.profile-stat{background:#fff;border:1px solid #ececf1;border-radius:10px;padding:16px}.profile-stat span{color:#737373;display:block;font-size:12px;margin-bottom:5px}.profile-stat strong{color:#171717;font-size:19px}.profile-grid{display:grid;gap:18px;grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr)}.profile-card{background:#fff;border:1px solid #ececf1;border-radius:10px;overflow:hidden}.profile-card-head{border-bottom:1px solid #ececf1;padding:16px 18px}.profile-card-head h3{font-size:15px;font-weight:600;margin:0 0 4px}.profile-card-head p{color:#737373;font-size:12px;margin:0}.profile-card-body{padding:18px}.profile-form-grid{display:grid;gap:14px 16px;grid-template-columns:repeat(2,minmax(0,1fr))}.profile-field.full{grid-column:1/-1}.profile-field label{color:#404040;font-size:12px;font-weight:600;margin-bottom:6px}.profile-field .form-control{border-radius:8px;box-shadow:none;min-height:42px}.profile-field .form-control:focus{border-color:#a3a3a3;box-shadow:0 0 0 3px rgba(23,23,23,.06)}.profile-actions{border-top:1px solid #ececf1;margin-top:18px;padding-top:16px}.profile-actions .btn{border-radius:8px;min-height:40px}.profile-primary{background:#171717;border-color:#171717;color:#fff}.profile-primary:hover,.profile-primary:focus{background:#404040;color:#fff}.account-list{margin:0}.account-row{align-items:center;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;padding:11px 0}.account-row:first-child{padding-top:0}.account-row:last-child{border:0;padding-bottom:0}.account-row span{color:#737373;font-size:12px}.account-row strong{color:#262626;font-size:12px;text-align:right}.password-meter{background:#f7f7f8;border-radius:8px;color:#737373;font-size:11px;line-height:1.5;margin-top:12px;padding:10px}@media(max-width:900px){.profile-grid{grid-template-columns:1fr}}@media(max-width:620px){.profile-stats,.profile-form-grid{grid-template-columns:1fr}.profile-field.full{grid-column:auto}.profile-hero{align-items:flex-start}.profile-status{display:none}}
</style>
<div id="page-wrapper"><div class="container-fluid profile-page">
    <div class="profile-hero">
        <div class="profile-avatar"><?php echo htmlspecialchars(strtoupper(substr($user['full_name'], 0, 1))); ?></div>
        <div><h1><?php echo htmlspecialchars((string) $user['full_name']); ?></h1><p><?php echo htmlspecialchars((string) ($user['branch_location'] ?: 'Branch account')); ?> · <?php echo htmlspecialchars((string) $user['email']); ?></p></div>
        <span class="profile-status"><?php echo htmlspecialchars($user['status']); ?></span>
    </div>
    <div class="profile-stats">
        <div class="profile-stat"><span>Total reports</span><strong><?php echo number_format($reportCount); ?></strong></div>
        <div class="profile-stat"><span>Saved report images</span><strong><?php echo number_format($imageCount); ?></strong></div>
        <div class="profile-stat"><span>Member since</span><strong><?php echo $user['created_at'] ? date('M Y', strtotime($user['created_at'])) : '—'; ?></strong></div>
    </div>
    <div class="profile-grid">
        <section class="profile-card">
            <div class="profile-card-head"><h3>Profile details</h3><p>These details identify your account and branch.</p></div>
            <div class="profile-card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="profile-form-grid">
                        <div class="profile-field"><label for="full_name">Full name *</label><input class="form-control" id="full_name" name="full_name" maxlength="120" value="<?php echo htmlspecialchars($user['full_name']); ?>" required></div>
                        <div class="profile-field"><label for="branch_location">Branch Location</label><select class="form-control" id="branch_location" name="branch_location" <?php echo auth_is_super_admin() ? '' : 'disabled'; ?>><?php echo user_branch_location_options($user['branch_location'] ?? '', $conn); ?></select></div>
                        <div class="profile-field"><label for="email">Email address *</label><input class="form-control" type="email" id="email" name="email" maxlength="150" value="<?php echo htmlspecialchars($user['email']); ?>" required></div>
                        <div class="profile-field"><label for="phone">Phone number</label><input class="form-control" id="phone" name="phone" maxlength="30" value="<?php echo htmlspecialchars((string) $user['phone']); ?>"></div>
                    </div>
                    <div class="profile-actions"><button class="btn profile-primary" type="submit"><i class="fa fa-save"></i> Save profile</button></div>
                </form>
            </div>
        </section>
        <div>
            <section class="profile-card">
                <div class="profile-card-head"><h3>Change password</h3><p>Confirm your current password before setting a new one.</p></div>
                <div class="profile-card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="action" value="change_password">
                        <div class="profile-field"><label for="current_password">Current password</label><input class="form-control" type="password" id="current_password" name="current_password" autocomplete="current-password" required></div>
                        <div class="profile-field" style="margin-top:13px"><label for="new_password">New password</label><input class="form-control" type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password" required></div>
                        <div class="profile-field" style="margin-top:13px"><label for="confirm_password">Confirm new password</label><input class="form-control" type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required></div>
                        <div class="password-meter">Use at least 8 characters. A mix of letters, numbers and symbols is recommended.</div>
                        <div class="profile-actions"><button class="btn btn-default" type="submit"><i class="fa fa-lock"></i> Change password</button></div>
                    </form>
                </div>
            </section>
            <section class="profile-card" style="margin-top:18px">
                <div class="profile-card-head"><h3>Account information</h3></div>
                <div class="profile-card-body"><div class="account-list">
                    <div class="account-row"><span>Account ID</span><strong>#<?php echo (int) $user['id']; ?></strong></div>
                    <div class="account-row"><span>Last login</span><strong><?php echo $user['last_login_at'] ? date('d M Y, h:i A', strtotime($user['last_login_at'])) : 'Not available'; ?></strong></div>
                    <div class="account-row"><span>Last updated</span><strong><?php echo $user['updated_at'] ? date('d M Y, h:i A', strtotime($user['updated_at'])) : 'Not available'; ?></strong></div>
                </div></div>
            </section>
        </div>
    </div>
</div></div>
<?php if ($flash): ?>
<script>
$(function(){
    var message=<?php echo json_encode((string) $flash['message']); ?>;
    var type=<?php echo json_encode((string) $flash['type']); ?>;
    if(window.AppToast){(AppToast[type]||AppToast.info)(message);}
});
</script>
<?php endif; ?>
<?php include 'assets/footer.php'; ?>
