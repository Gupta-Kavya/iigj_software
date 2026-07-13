<?php
require_once 'super_admin_common.php';
require_once 'agreement_helper.php';
require_once 'customer_helper.php';

agreement_table_ready($conn);
customer_master_table_ready($conn);
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
        $branchLocation = user_branch_location_clean($_POST['branch_location'] ?? '', $conn);
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $role = ($_POST['role'] ?? 'user') === 'super_admin' ? 'super_admin' : 'user';
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            super_admin_redirect('super-admin-user.php?id=' . $targetId, 'Enter a valid name and email.', 'error');
        }
        $stmt = $conn->prepare('UPDATE sm_users SET full_name=?, branch_location=?, email=?, phone=?, role=?, status=?, updated_at=NOW() WHERE id=?');
        if (!$stmt) super_admin_redirect('super-admin-user.php?id=' . $targetId, 'Unable to update user: ' . $conn->error, 'error');
        $stmt->bind_param('ssssssi', $fullName, $branchLocation, $email, $phone, $role, $status, $targetId);
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
    'agreements' => 0,
    'delivered_agreements' => 0,
    'cancelled_agreements' => 0,
    'customers' => 0,
    'bookings' => 0,
    'pending_bookings' => 0,
    'generated_bookings' => 0,
    'cancelled_bookings' => 0,
    'testing_total' => 0.0,
    'due_total' => 0.0,
    'refund_total' => 0.0,
    'last_agreement' => null,
];
$recent = [];
$recentAgreements = [];
$recentCustomers = [];
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
$agreementStmt = $conn->prepare("SELECT COUNT(*) AS agreements,
        SUM(CASE WHEN delivered = 1 THEN 1 ELSE 0 END) AS delivered_agreements,
        SUM(CASE WHEN agreement_status = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled_agreements,
        COALESCE(SUM(testing_charges),0) AS testing_total,
        COALESCE(SUM(due_amount),0) AS due_total,
        COALESCE(SUM(refund_amount),0) AS refund_total,
        MAX(created_at) AS last_agreement
    FROM sm_stone_agreements WHERE user_id = ?");
if ($agreementStmt) {
    $agreementStmt->bind_param('i', $targetId);
    $agreementStmt->execute();
    $row = $agreementStmt->get_result()->fetch_assoc();
    $stats['agreements'] = (int) ($row['agreements'] ?? 0);
    $stats['delivered_agreements'] = (int) ($row['delivered_agreements'] ?? 0);
    $stats['cancelled_agreements'] = (int) ($row['cancelled_agreements'] ?? 0);
    $stats['testing_total'] = (float) ($row['testing_total'] ?? 0);
    $stats['due_total'] = (float) ($row['due_total'] ?? 0);
    $stats['refund_total'] = (float) ($row['refund_total'] ?? 0);
    $stats['last_agreement'] = $row['last_agreement'] ?? null;
    $agreementStmt->close();
}
$bookingStmt = $conn->prepare("SELECT COUNT(*) AS bookings,
        SUM(CASE WHEN status = 'booked' THEN 1 ELSE 0 END) AS pending_bookings,
        SUM(CASE WHEN status = 'generated' THEN 1 ELSE 0 END) AS generated_bookings,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_bookings
    FROM sm_form_masters WHERE user_id = ?");
if ($bookingStmt) {
    $bookingStmt->bind_param('i', $targetId);
    $bookingStmt->execute();
    $row = $bookingStmt->get_result()->fetch_assoc();
    $stats['bookings'] = (int) ($row['bookings'] ?? 0);
    $stats['pending_bookings'] = (int) ($row['pending_bookings'] ?? 0);
    $stats['generated_bookings'] = (int) ($row['generated_bookings'] ?? 0);
    $stats['cancelled_bookings'] = (int) ($row['cancelled_bookings'] ?? 0);
    $bookingStmt->close();
}
$customerStmt = $conn->prepare('SELECT COUNT(*) AS customers FROM sm_customer_master WHERE user_id = ?');
if ($customerStmt) {
    $customerStmt->bind_param('i', $targetId);
    $customerStmt->execute();
    $stats['customers'] = (int) ($customerStmt->get_result()->fetch_assoc()['customers'] ?? 0);
    $customerStmt->close();
}
$agreementRecentStmt = $conn->prepare('SELECT agreement_no, customer_name, agreement_status, pcs_total, testing_charges, due_amount, created_at FROM sm_stone_agreements WHERE user_id = ? ORDER BY id DESC LIMIT 10');
if ($agreementRecentStmt) {
    $agreementRecentStmt->bind_param('i', $targetId);
    $agreementRecentStmt->execute();
    $result = $agreementRecentStmt->get_result();
    while ($row = $result->fetch_assoc()) $recentAgreements[] = $row;
    $agreementRecentStmt->close();
}
$customerRecentStmt = $conn->prepare('SELECT customer_name, mobile_no, email, member_status, mou_cdc, created_at FROM sm_customer_master WHERE user_id = ? ORDER BY id DESC LIMIT 10');
if ($customerRecentStmt) {
    $customerRecentStmt->bind_param('i', $targetId);
    $customerRecentStmt->execute();
    $result = $customerRecentStmt->get_result();
    while ($row = $result->fetch_assoc()) $recentCustomers[] = $row;
    $customerRecentStmt->close();
}
$flash = super_admin_take_flash();
include 'assets/navbar.php';
?>
<style>
.su-page{padding-bottom:38px}.su-head{display:flex;justify-content:space-between;gap:16px;margin-bottom:18px}.su-head h1{border:0;font-size:25px;font-weight:600;margin:0 0 5px;padding:0}.su-head p{color:#737373;margin:0}.su-grid{display:grid;gap:17px;grid-template-columns:1fr 360px}.su-card{background:#fff;border:1px solid #ececf1;border-radius:8px;overflow:hidden}.su-card-head{border-bottom:1px solid #ececf1;padding:15px 17px}.su-card-head h3{font-size:15px;font-weight:600;margin:0}.su-body{padding:17px}.su-stats{display:grid;gap:12px;grid-template-columns:repeat(6,1fr);margin-bottom:17px}.su-stat{background:#f7f7f8;border:1px solid #ececf1;border-radius:8px;padding:12px}.su-stat span{color:#737373;display:block;font-size:11px;text-transform:uppercase}.su-stat strong{display:block;font-size:20px;margin-top:5px}.su-form-grid{display:grid;gap:12px;grid-template-columns:1fr 1fr}.su-form-grid .full{grid-column:1/-1}.su-detail-grid{display:grid;gap:8px;grid-template-columns:1fr 1fr}.su-detail{background:#f7f7f8;border:1px solid #ececf1;border-radius:7px;padding:9px}.su-detail span{color:#737373;display:block;font-size:10px;text-transform:uppercase}.su-detail strong{display:block;font-size:12px;margin-top:3px;overflow-wrap:anywhere}.su-table{margin:0}.su-table th{background:#f7f7f8;color:#737373;font-size:11px;text-transform:uppercase}.su-table td{font-size:12px;vertical-align:middle}.su-pill{border-radius:999px;display:inline-block;font-size:10px;font-weight:600;padding:4px 8px}.su-active{background:#dcfce7;color:#166534}.su-inactive{background:#fee2e2;color:#991b1b}.su-admin{background:#ede9fe;color:#5b21b6}.su-regular{background:#f3f4f6;color:#525252}@media(max-width:1200px){.su-stats{grid-template-columns:repeat(3,1fr)}}@media(max-width:1000px){.su-grid{grid-template-columns:1fr}.su-stats,.su-form-grid,.su-detail-grid{grid-template-columns:1fr}}
</style>
<div id="page-wrapper"><div class="container-fluid su-page">
<div class="su-head"><div><h1><i class="fa fa-user"></i> <?php echo htmlspecialchars($user['full_name']); ?></h1><p><?php echo htmlspecialchars($user['branch_location'] ?: 'Branch user account'); ?></p></div><a class="btn btn-default" href="super-admin.php"><i class="fa fa-arrow-left"></i> Back</a></div>
<?php if ($flash): ?><div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : 'success'; ?>"><?php echo htmlspecialchars($flash['message']); ?></div><?php endif; ?>
<div class="su-stats"><div class="su-stat"><span>Agreements</span><strong><?php echo number_format($stats['agreements']); ?></strong></div><div class="su-stat"><span>Reports</span><strong><?php echo number_format($stats['reports']); ?></strong></div><div class="su-stat"><span>Bookings</span><strong><?php echo number_format($stats['bookings']); ?></strong></div><div class="su-stat"><span>Customers</span><strong><?php echo number_format($stats['customers']); ?></strong></div><div class="su-stat"><span>Images</span><strong><?php echo number_format($stats['images']); ?></strong></div><div class="su-stat"><span>Due Amount</span><strong><?php echo number_format($stats['due_total'], 2); ?></strong></div></div>
<div class="su-grid">
<section class="su-card"><div class="su-card-head"><h3>Account details</h3></div><div class="su-body"><form method="post"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(super_admin_csrf()); ?>"><input type="hidden" name="action" value="update_profile"><div class="su-form-grid"><div class="form-group"><label>Full name</label><input class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required></div><div class="form-group"><label>Branch Location</label><select class="form-control" name="branch_location"><?php echo user_branch_location_options($user['branch_location'] ?? '', $conn); ?></select></div><div class="form-group"><label>Email</label><input class="form-control" type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required></div><div class="form-group"><label>Phone</label><input class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>"></div><div class="form-group"><label>Role</label><select class="form-control" name="role"><option value="user" <?php echo $user['role']==='user'?'selected':''; ?>>User</option><option value="super_admin" <?php echo $user['role']==='super_admin'?'selected':''; ?>>Super Admin</option></select></div><div class="form-group"><label>Status</label><select class="form-control" name="status"><option value="active" <?php echo $user['status']==='active'?'selected':''; ?>>Active</option><option value="inactive" <?php echo $user['status']==='inactive'?'selected':''; ?>>Inactive</option></select></div></div><button class="btn btn-primary"><i class="fa fa-save"></i> Save changes</button></form></div></section>
<aside class="su-card"><div class="su-card-head"><h3>Full user details</h3></div><div class="su-body"><div class="su-detail-grid"><div class="su-detail"><span>User ID</span><strong>#<?php echo (int) $user['id']; ?></strong></div><div class="su-detail"><span>Status</span><strong><?php echo htmlspecialchars($user['status']); ?></strong></div><div class="su-detail"><span>Role</span><strong><?php echo $user['role']==='super_admin'?'Super Admin':'User'; ?></strong></div><div class="su-detail"><span>Email Verified</span><strong><?php echo $user['email_verified_at'] ? htmlspecialchars(super_admin_format_date($user['email_verified_at'])) : 'Not verified'; ?></strong></div><div class="su-detail"><span>Created</span><strong><?php echo htmlspecialchars(super_admin_format_date($user['created_at'])); ?></strong></div><div class="su-detail"><span>Updated</span><strong><?php echo htmlspecialchars(super_admin_format_date($user['updated_at'])); ?></strong></div><div class="su-detail"><span>Last Login</span><strong><?php echo htmlspecialchars(super_admin_format_date($user['last_login_at'])); ?></strong></div><div class="su-detail"><span>Last Agreement</span><strong><?php echo htmlspecialchars(super_admin_format_date($stats['last_agreement'])); ?></strong></div><div class="su-detail"><span>Last Report</span><strong><?php echo htmlspecialchars(super_admin_format_date($stats['last_report'])); ?></strong></div><div class="su-detail"><span>Testing Total</span><strong><?php echo number_format($stats['testing_total'], 2); ?></strong></div><div class="su-detail"><span>Refund Total</span><strong><?php echo number_format($stats['refund_total'], 2); ?></strong></div><div class="su-detail"><span>Delivered Agreements</span><strong><?php echo number_format($stats['delivered_agreements']); ?></strong></div><div class="su-detail"><span>Cancelled Agreements</span><strong><?php echo number_format($stats['cancelled_agreements']); ?></strong></div><div class="su-detail"><span>Pending Bookings</span><strong><?php echo number_format($stats['pending_bookings']); ?></strong></div><div class="su-detail"><span>Generated Bookings</span><strong><?php echo number_format($stats['generated_bookings']); ?></strong></div><div class="su-detail"><span>Cancelled Bookings</span><strong><?php echo number_format($stats['cancelled_bookings']); ?></strong></div></div><hr><form method="post"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(super_admin_csrf()); ?>"><input type="hidden" name="action" value="reset_password"><div class="form-group"><label>New password</label><input class="form-control" type="password" name="password" minlength="8" required></div><button class="btn btn-default"><i class="fa fa-key"></i> Reset password</button></form></div></aside>
</div>
<section class="su-card" style="margin-top:17px"><div class="su-card-head"><h3>Recent reports</h3></div><div class="table-responsive"><table class="table table-hover su-table"><thead><tr><th>Cert No</th><th>Report No</th><th>Stone</th><th>Type</th><th>Created</th></tr></thead><tbody><?php foreach ($recent as $row): ?><tr><td><?php echo htmlspecialchars($row['certi_no']); ?></td><td><?php echo htmlspecialchars($row['report_no']); ?></td><td><?php echo htmlspecialchars($row['stone_name']); ?></td><td><?php echo htmlspecialchars(super_admin_report_type($row['type'])); ?></td><td><?php echo htmlspecialchars(super_admin_format_date($row['created_at'])); ?></td></tr><?php endforeach; ?><?php if (!$recent): ?><tr><td colspan="5" class="text-center text-muted" style="padding:22px">No reports yet.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="su-card" style="margin-top:17px"><div class="su-card-head"><h3>Recent agreements</h3></div><div class="table-responsive"><table class="table table-hover su-table"><thead><tr><th>Agreement No</th><th>Customer</th><th>Status</th><th>PCS</th><th>Testing</th><th>Due</th><th>Created</th></tr></thead><tbody><?php foreach ($recentAgreements as $row): ?><tr><td><?php echo htmlspecialchars($row['agreement_no']); ?></td><td><?php echo htmlspecialchars($row['customer_name']); ?></td><td><?php echo htmlspecialchars($row['agreement_status']); ?></td><td><?php echo number_format((int) $row['pcs_total']); ?></td><td><?php echo number_format((float) $row['testing_charges'], 2); ?></td><td><?php echo number_format((float) $row['due_amount'], 2); ?></td><td><?php echo htmlspecialchars(super_admin_format_date($row['created_at'])); ?></td></tr><?php endforeach; ?><?php if (!$recentAgreements): ?><tr><td colspan="7" class="text-center text-muted" style="padding:22px">No agreements yet.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="su-card" style="margin-top:17px"><div class="su-card-head"><h3>Recent customers</h3></div><div class="table-responsive"><table class="table table-hover su-table"><thead><tr><th>Customer</th><th>Mobile</th><th>Email</th><th>Membership</th><th>MOU / CDC</th><th>Created</th></tr></thead><tbody><?php foreach ($recentCustomers as $row): ?><tr><td><?php echo htmlspecialchars($row['customer_name']); ?></td><td><?php echo htmlspecialchars($row['mobile_no']); ?></td><td><?php echo htmlspecialchars($row['email']); ?></td><td><?php echo htmlspecialchars($row['member_status']); ?></td><td><?php echo htmlspecialchars($row['mou_cdc']); ?></td><td><?php echo htmlspecialchars(super_admin_format_date($row['created_at'])); ?></td></tr><?php endforeach; ?><?php if (!$recentCustomers): ?><tr><td colspan="6" class="text-center text-muted" style="padding:22px">No customers yet.</td></tr><?php endif; ?></tbody></table></div></section>
</div></div>
<?php include 'assets/footer.php'; ?>
