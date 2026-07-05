<?php
require_once 'super_admin_common.php';
$targetId = max(0, (int) ($_GET['id'] ?? 0));
if ($targetId <= 0) {
    super_admin_redirect('super-admin.php', 'User not found.', 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!super_admin_verify_csrf()) {
        super_admin_redirect('super-admin-user.php?id=' . $targetId, 'Your session expired. Refresh and try again.', 'error');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $companyName = trim((string) ($_POST['company_name'] ?? ''));
        $branchLocation = user_branch_location_clean($_POST['branch_location'] ?? '', $conn);
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $gstNumber = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) ($_POST['gst_number'] ?? '')));
        $role = ($_POST['role'] ?? 'user') === 'super_admin' ? 'super_admin' : 'user';
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            super_admin_redirect('super-admin-user.php?id=' . $targetId, 'Enter a valid name and email.', 'error');
        }
        $stmt = $conn->prepare('UPDATE sm_users SET full_name=?, company_name=?, branch_location=?, email=?, phone=?, gst_number=?, role=?, status=?, updated_at=NOW() WHERE id=?');
        if (!$stmt) super_admin_redirect('super-admin-user.php?id=' . $targetId, 'Unable to update user: ' . $conn->error, 'error');
        $stmt->bind_param('ssssssssi', $fullName, $companyName, $branchLocation, $email, $phone, $gstNumber, $role, $status, $targetId);
        $ok = $stmt->execute();
        $message = $ok ? 'User profile updated.' : 'Unable to update user: ' . $stmt->error;
        $stmt->close();
        super_admin_redirect('super-admin-user.php?id=' . $targetId, $message, $ok ? 'success' : 'error');
    }
    if ($action === 'reset_password') {
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 8) {
            super_admin_redirect('super-admin-user.php?id=' . $targetId, 'Password must be at least 8 characters.', 'error');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE sm_users SET password_hash=?, updated_at=NOW() WHERE id=?');
        $stmt->bind_param('si', $hash, $targetId);
        $ok = $stmt->execute();
        $stmt->close();
        super_admin_redirect('super-admin-user.php?id=' . $targetId, $ok ? 'Password reset.' : 'Unable to reset password.', $ok ? 'success' : 'error');
    }
}

$stmt = $conn->prepare('SELECT * FROM sm_users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $targetId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) super_admin_redirect('super-admin.php', 'User not found.', 'error');

$stats = [
    'reports' => 0,
    'images' => super_admin_user_image_count($targetId),
    'last_report' => null,
];
$recent = [];
$hasFormUserId = false;
$formUserColumn = @$conn->query("SHOW COLUMNS FROM sm_form_data LIKE 'user_id'");
if ($formUserColumn && $formUserColumn->num_rows > 0) {
    $hasFormUserId = true;
}
if ($hasFormUserId) {
    $statStmt = $conn->prepare('SELECT COUNT(*) AS reports, MAX(created_at) AS last_report FROM sm_form_data WHERE CAST(user_id AS UNSIGNED)=?');
    if ($statStmt) {
        $statStmt->bind_param('i', $targetId);
        $statStmt->execute();
        $row = $statStmt->get_result()->fetch_assoc();
        $stats['reports'] = (int) ($row['reports'] ?? 0);
        $stats['last_report'] = $row['last_report'] ?? null;
        $statStmt->close();
    }
    $recentStmt = $conn->prepare('SELECT certi_no, report_no, stone_name, type, created_at FROM sm_form_data WHERE CAST(user_id AS UNSIGNED)=? ORDER BY id DESC LIMIT 10');
    if ($recentStmt) {
        $recentStmt->bind_param('i', $targetId);
        $recentStmt->execute();
        $result = $recentStmt->get_result();
        while ($row = $result->fetch_assoc()) $recent[] = $row;
        $recentStmt->close();
    }
}
$flash = super_admin_take_flash();
include 'assets/navbar.php';
?>
<style>
.su-page{padding-bottom:38px}.su-head{display:flex;justify-content:space-between;gap:16px;margin-bottom:18px}.su-head h1{border:0;font-size:25px;font-weight:600;margin:0 0 5px;padding:0}.su-head p{color:#737373;margin:0}.su-grid{display:grid;gap:17px;grid-template-columns:1fr 360px}.su-card{background:#fff;border:1px solid #ececf1;border-radius:8px;overflow:hidden}.su-card-head{border-bottom:1px solid #ececf1;padding:15px 17px}.su-card-head h3{font-size:15px;font-weight:600;margin:0}.su-body{padding:17px}.su-stats{display:grid;gap:12px;grid-template-columns:repeat(3,1fr);margin-bottom:17px}.su-stat{background:#f7f7f8;border:1px solid #ececf1;border-radius:8px;padding:12px}.su-stat span{color:#737373;display:block;font-size:11px;text-transform:uppercase}.su-stat strong{display:block;font-size:20px;margin-top:5px}.su-form-grid{display:grid;gap:12px;grid-template-columns:1fr 1fr}.su-form-grid .full{grid-column:1/-1}.su-table{margin:0}.su-table th{background:#f7f7f8;color:#737373;font-size:11px;text-transform:uppercase}.su-pill{border-radius:999px;display:inline-block;font-size:10px;font-weight:600;padding:4px 8px}.su-active{background:#dcfce7;color:#166534}.su-inactive{background:#fee2e2;color:#991b1b}.su-admin{background:#ede9fe;color:#5b21b6}.su-regular{background:#f3f4f6;color:#525252}@media(max-width:1000px){.su-grid{grid-template-columns:1fr}.su-stats,.su-form-grid{grid-template-columns:1fr}}
</style>
<div id="page-wrapper"><div class="container-fluid su-page">
<div class="su-head"><div><h1><i class="fa fa-user"></i> <?php echo htmlspecialchars($user['full_name']); ?></h1><p><?php echo htmlspecialchars($user['company_name'] ?: 'Branch/user account'); ?></p></div><a class="btn btn-default" href="super-admin.php"><i class="fa fa-arrow-left"></i> Back</a></div>
<?php if ($flash): ?><div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : 'success'; ?>"><?php echo htmlspecialchars($flash['message']); ?></div><?php endif; ?>
<div class="su-stats"><div class="su-stat"><span>Reports</span><strong><?php echo number_format($stats['reports']); ?></strong></div><div class="su-stat"><span>Images</span><strong><?php echo number_format($stats['images']); ?></strong></div><div class="su-stat"><span>Last report</span><strong><?php echo htmlspecialchars(super_admin_format_date($stats['last_report'], false)); ?></strong></div></div>
<div class="su-grid">
<section class="su-card"><div class="su-card-head"><h3>Account details</h3></div><div class="su-body"><form method="post"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(super_admin_csrf()); ?>"><input type="hidden" name="action" value="update_profile"><div class="su-form-grid"><div class="form-group"><label>Full name</label><input class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required></div><div class="form-group"><label>Branch / Company</label><input class="form-control" name="company_name" value="<?php echo htmlspecialchars($user['company_name']); ?>"></div><div class="form-group"><label>Branch Location</label><select class="form-control" name="branch_location"><?php echo user_branch_location_options($user['branch_location'] ?? '', $conn); ?></select></div><div class="form-group"><label>Email</label><input class="form-control" type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required></div><div class="form-group"><label>Phone</label><input class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>"></div><div class="form-group"><label>GSTIN</label><input class="form-control" name="gst_number" value="<?php echo htmlspecialchars($user['gst_number']); ?>"></div><div class="form-group"><label>Role</label><select class="form-control" name="role"><option value="user" <?php echo $user['role']==='user'?'selected':''; ?>>User</option><option value="super_admin" <?php echo $user['role']==='super_admin'?'selected':''; ?>>Super Admin</option></select></div><div class="form-group"><label>Status</label><select class="form-control" name="status"><option value="active" <?php echo $user['status']==='active'?'selected':''; ?>>Active</option><option value="inactive" <?php echo $user['status']==='inactive'?'selected':''; ?>>Inactive</option></select></div></div><button class="btn btn-primary"><i class="fa fa-save"></i> Save changes</button></form></div></section>
<aside class="su-card"><div class="su-card-head"><h3>Reset password</h3></div><div class="su-body"><form method="post"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(super_admin_csrf()); ?>"><input type="hidden" name="action" value="reset_password"><div class="form-group"><label>New password</label><input class="form-control" type="password" name="password" minlength="8" required></div><button class="btn btn-default"><i class="fa fa-key"></i> Reset password</button></form><hr><p><span class="su-pill <?php echo $user['status']==='active'?'su-active':'su-inactive'; ?>"><?php echo htmlspecialchars($user['status']); ?></span> <span class="su-pill <?php echo $user['role']==='super_admin'?'su-admin':'su-regular'; ?>"><?php echo $user['role']==='super_admin'?'Super Admin':'User'; ?></span></p><p class="text-muted">Created <?php echo htmlspecialchars(super_admin_format_date($user['created_at'])); ?><br>Last login <?php echo htmlspecialchars(super_admin_format_date($user['last_login_at'])); ?></p></div></aside>
</div>
<section class="su-card" style="margin-top:17px"><div class="su-card-head"><h3>Recent reports</h3></div><div class="table-responsive"><table class="table table-hover su-table"><thead><tr><th>Cert No</th><th>Report No</th><th>Stone</th><th>Type</th><th>Created</th></tr></thead><tbody><?php foreach ($recent as $row): ?><tr><td><?php echo htmlspecialchars($row['certi_no']); ?></td><td><?php echo htmlspecialchars($row['report_no']); ?></td><td><?php echo htmlspecialchars($row['stone_name']); ?></td><td><?php echo htmlspecialchars(super_admin_report_type($row['type'])); ?></td><td><?php echo htmlspecialchars(super_admin_format_date($row['created_at'])); ?></td></tr><?php endforeach; ?><?php if (!$recent): ?><tr><td colspan="5" class="text-center text-muted" style="padding:22px">No reports yet.</td></tr><?php endif; ?></tbody></table></div></section>
</div></div>
<?php include 'assets/footer.php'; ?>
