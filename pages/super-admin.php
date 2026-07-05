<?php
require_once 'super_admin_common.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_waapi') {
    if (!super_admin_verify_csrf()) {
        super_admin_redirect('super-admin.php', 'Your session expired. Refresh and try again.', 'error');
    }
    $ok = waapi_save_settings($conn, $_POST['waapi_instance_id'] ?? '', $_POST['waapi_api_key'] ?? '');
    super_admin_redirect('super-admin.php', $ok ? 'WhatsApp API settings saved.' : 'Unable to save WhatsApp API settings.', $ok ? 'success' : 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_location') {
    if (!super_admin_verify_csrf()) {
        super_admin_redirect('super-admin.php', 'Your session expired. Refresh and try again.', 'error');
    }
    $locationName = trim((string) ($_POST['location_name'] ?? ''));
    $locationCode = user_branch_location_normalize($_POST['location_code'] ?? $locationName);
    if ($locationCode === '' || $locationName === '') {
        super_admin_redirect('super-admin.php', 'Enter location name and code.', 'error');
    }
    $stmt = $conn->prepare('INSERT INTO sm_branch_locations (code, name, active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE name = VALUES(name), active = 1, updated_at = NOW()');
    if (!$stmt) {
        super_admin_redirect('super-admin.php', 'Unable to save location: ' . $conn->error, 'error');
    }
    $stmt->bind_param('ss', $locationCode, $locationName);
    $ok = $stmt->execute();
    $error = $stmt->error;
    $stmt->close();
    super_admin_redirect('super-admin.php', $ok ? 'Branch location saved.' : 'Unable to save location: ' . $error, $ok ? 'success' : 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_user') {
    if (!super_admin_verify_csrf()) {
        super_admin_redirect('super-admin.php', 'Your session expired. Refresh and try again.', 'error');
    }

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $companyName = trim((string) ($_POST['company_name'] ?? ''));
    $branchLocation = user_branch_location_clean($_POST['branch_location'] ?? '', $conn);
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $gstNumber = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) ($_POST['gst_number'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $role = ($_POST['role'] ?? 'user') === 'super_admin' ? 'super_admin' : 'user';

    if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        super_admin_redirect('super-admin.php', 'Enter a valid name, email and password of at least 8 characters.', 'error');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO sm_users (full_name, company_name, branch_location, email, phone, gst_number, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "active", NOW())');
    if (!$stmt) {
        super_admin_redirect('super-admin.php', 'Unable to create user: ' . $conn->error, 'error');
    }
    $stmt->bind_param('ssssssss', $fullName, $companyName, $branchLocation, $email, $phone, $gstNumber, $hash, $role);
    if (!$stmt->execute()) {
        $message = stripos($stmt->error, 'duplicate') !== false ? 'Email already exists.' : 'Unable to create user: ' . $stmt->error;
        $stmt->close();
        super_admin_redirect('super-admin.php', $message, 'error');
    }
    $stmt->close();
    super_admin_redirect('super-admin.php', 'User account created.');
}

$search = trim((string) ($_GET['q'] ?? ''));
$status = in_array($_GET['status'] ?? '', ['active', 'inactive'], true) ? $_GET['status'] : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [];
$types = '';
$params = [];
if ($search !== '') {
    $like = '%' . $search . '%';
    $where[] = '(u.full_name LIKE ? OR u.company_name LIKE ? OR u.branch_location LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $types .= 'sssss';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($status !== '') {
    $where[] = 'u.status = ?';
    $types .= 's';
    $params[] = $status;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$totalUsers = 0;
$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM sm_users u {$whereSql}");
if ($countStmt) {
    if ($types !== '') super_admin_bind_params($countStmt, $types, $params);
    $countStmt->execute();
    $totalUsers = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $countStmt->close();
}

$queryParams = $params;
$queryTypes = $types . 'ii';
$queryParams[] = $perPage;
$queryParams[] = $offset;
$users = [];
$hasFormUserId = false;
$formUserColumn = @$conn->query("SHOW COLUMNS FROM sm_form_data LIKE 'user_id'");
if ($formUserColumn && $formUserColumn->num_rows > 0) {
    $hasFormUserId = true;
}
$reportCountSql = $hasFormUserId
    ? '(SELECT COUNT(*) FROM sm_form_data f WHERE CAST(f.user_id AS UNSIGNED) = u.id) AS report_count'
    : '0 AS report_count';
$stmt = $conn->prepare("SELECT u.id, u.full_name, u.company_name, u.branch_location, u.email, u.phone, u.gst_number, u.role, u.status, u.last_login_at, u.created_at,
        {$reportCountSql}
    FROM sm_users u
    {$whereSql}
    ORDER BY u.created_at DESC, u.id DESC
    LIMIT ? OFFSET ?");
if ($stmt) {
    super_admin_bind_params($stmt, $queryTypes, $queryParams);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $users[] = $row;
    $stmt->close();
}

$summary = [
    'total' => (int) ($conn->query("SELECT COUNT(*) AS total FROM sm_users")->fetch_assoc()['total'] ?? 0),
    'active' => (int) ($conn->query("SELECT COUNT(*) AS total FROM sm_users WHERE status='active'")->fetch_assoc()['total'] ?? 0),
    'inactive' => (int) ($conn->query("SELECT COUNT(*) AS total FROM sm_users WHERE status='inactive'")->fetch_assoc()['total'] ?? 0),
    'reports' => (int) ($conn->query("SELECT COUNT(*) AS total FROM sm_form_data")->fetch_assoc()['total'] ?? 0),
];
$waapiSettings = waapi_get_settings($conn);
$branchLocations = user_branch_locations($conn, false);
$pages = max(1, (int) ceil($totalUsers / $perPage));
$flash = super_admin_take_flash();
include 'assets/navbar.php';
?>
<style>
.sa-page{padding-bottom:38px}.sa-head{align-items:flex-end;display:flex;justify-content:space-between;margin-bottom:18px}.sa-head h1{border:0;color:#171717;font-size:26px;font-weight:600;margin:0 0 5px;padding:0}.sa-head p{color:#737373;margin:0}.sa-head-badge{background:#171717;border-radius:999px;color:#fff;font-size:11px;font-weight:600;padding:7px 11px}.sa-stats{display:grid;gap:14px;grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:17px}.sa-stat{background:#fff;border:1px solid #ececf1;border-radius:8px;padding:16px}.sa-stat-icon{align-items:center;background:#f5f5f5;border-radius:8px;color:#404040;display:flex;height:34px;justify-content:center;margin-bottom:12px;width:34px}.sa-stat span{color:#737373;display:block;font-size:12px}.sa-stat strong{color:#171717;display:block;font-size:22px;margin:4px 0}.sa-card{background:#fff;border:1px solid #ececf1;border-radius:8px;overflow:hidden}.sa-card-head{align-items:center;border-bottom:1px solid #ececf1;display:flex;justify-content:space-between;padding:15px 17px}.sa-card-head h3{font-size:15px;font-weight:600;margin:0}.sa-filters{display:grid;gap:10px;grid-template-columns:minmax(220px,1fr) 150px auto;padding:15px 17px}.sa-filters .form-control,.sa-filters .btn{border-radius:8px;min-height:40px}.sa-table-wrap{overflow-x:auto}.sa-table{margin:0;min-width:980px}.sa-table th{background:#f7f7f8;color:#616161;font-size:11px;text-transform:uppercase}.sa-table td{font-size:12px;vertical-align:middle}.sa-user strong{display:block;font-size:12px}.sa-user span{color:#8a8a8a;font-size:11px}.sa-pill{border-radius:999px;display:inline-block;font-size:10px;font-weight:600;padding:4px 8px}.sa-active{background:#dcfce7;color:#166534}.sa-inactive{background:#fee2e2;color:#991b1b}.sa-admin{background:#ede9fe;color:#5b21b6}.sa-regular{background:#f3f4f6;color:#525252}.sa-btn{border:1px solid #e5e5e5;border-radius:7px;color:#404040;display:inline-block;padding:6px 9px}.sa-btn:hover{background:#f7f7f8;color:#171717;text-decoration:none}.sa-pagination{align-items:center;border-top:1px solid #ececf1;display:flex;justify-content:space-between;padding:13px 17px}.sa-pages{display:flex;gap:5px}.sa-pages a{border:1px solid #e5e5e5;border-radius:6px;color:#404040;padding:5px 9px}.sa-pages a.active{background:#171717;color:#fff}.modern-modal .modal-content{border:0;border-radius:10px;overflow:hidden}.modern-modal .modal-header,.modern-modal .modal-footer{border-color:#ececf1}@media(max-width:900px){.sa-stats{grid-template-columns:repeat(2,1fr)}.sa-filters{grid-template-columns:1fr 1fr}}@media(max-width:600px){.sa-stats,.sa-filters{grid-template-columns:1fr}.sa-head-badge{display:none}}
</style>
<div id="page-wrapper"><div class="container-fluid sa-page">
<div class="sa-head"><div><h1><i class="fa fa-shield"></i> Super Admin</h1><p>Manage branch/location user accounts for this customized installation.</p></div><span class="sa-head-badge">One-time custom software</span></div>
<?php if ($flash): ?><div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : 'success'; ?>"><?php echo htmlspecialchars($flash['message']); ?></div><?php endif; ?>
<div class="sa-stats">
 <div class="sa-stat"><div class="sa-stat-icon"><i class="fa fa-users"></i></div><span>Total users</span><strong><?php echo number_format($summary['total']); ?></strong></div>
 <div class="sa-stat"><div class="sa-stat-icon"><i class="fa fa-check"></i></div><span>Active users</span><strong><?php echo number_format($summary['active']); ?></strong></div>
 <div class="sa-stat"><div class="sa-stat-icon"><i class="fa fa-ban"></i></div><span>Inactive users</span><strong><?php echo number_format($summary['inactive']); ?></strong></div>
 <div class="sa-stat"><div class="sa-stat-icon"><i class="fa fa-file-text-o"></i></div><span>Total reports</span><strong><?php echo number_format($summary['reports']); ?></strong></div>
</div>
<section class="sa-card" style="margin-bottom:17px"><div class="sa-card-head"><h3>WhatsApp API Settings</h3></div><div style="padding:15px 17px"><form method="post"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(super_admin_csrf()); ?>"><input type="hidden" name="action" value="save_waapi"><div class="row"><div class="col-sm-4 form-group"><label>WaAPI Instance ID</label><input class="form-control" name="waapi_instance_id" value="<?php echo htmlspecialchars($waapiSettings['instance_id']); ?>" placeholder="Example: 12345"></div><div class="col-sm-8 form-group"><label>WaAPI API Key</label><input class="form-control" type="password" name="waapi_api_key" value="<?php echo htmlspecialchars($waapiSettings['api_key']); ?>" placeholder="Bearer token / API key"></div></div><button class="btn btn-primary"><i class="fa fa-whatsapp"></i> Save WhatsApp Settings</button></form></div></section>
<section class="sa-card" style="margin-bottom:17px"><div class="sa-card-head"><h3>Branch Locations</h3></div><div style="padding:15px 17px"><form method="post" class="row"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(super_admin_csrf()); ?>"><input type="hidden" name="action" value="save_location"><div class="col-sm-4 form-group"><label>Location Name</label><input class="form-control" name="location_name" maxlength="120" placeholder="Example: Mumbai" required></div><div class="col-sm-3 form-group"><label>Location Code</label><input class="form-control" name="location_code" maxlength="60" placeholder="Example: MUMBAI"></div><div class="col-sm-2 form-group"><label>&nbsp;</label><button class="btn btn-primary btn-block"><i class="fa fa-plus"></i> Add</button></div><div class="col-sm-12"><?php foreach ($branchLocations as $code => $label): ?><span class="sa-pill sa-regular" style="margin:0 6px 6px 0"><?php echo htmlspecialchars($label); ?> <span class="text-muted">(<?php echo htmlspecialchars($code); ?>)</span></span><?php endforeach; ?><?php if (!$branchLocations): ?><span class="text-muted">No locations found.</span><?php endif; ?></div></form></div></section>
<section class="sa-card"><div class="sa-card-head"><h3>Branch / user accounts</h3><button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createUserModal"><i class="fa fa-user-plus"></i> Create user</button></div>
<form class="sa-filters" method="get"><input class="form-control" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, branch, email, phone"><select class="form-control" name="status"><option value="">All statuses</option><option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option><option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option></select><button class="btn btn-default">Filter</button></form>
<div class="sa-table-wrap"><table class="table table-hover sa-table"><thead><tr><th>User / Branch</th><th>Contact</th><th>Role</th><th>Status</th><th>Reports</th><th>Last login</th><th>Created</th><th></th></tr></thead><tbody>
<?php foreach ($users as $user): ?><tr>
<td class="sa-user"><strong><?php echo htmlspecialchars($user['full_name']); ?></strong><span><?php echo htmlspecialchars($user['company_name'] ?: 'No branch/company name'); ?><?php echo !empty($user['branch_location']) ? ' · ' . htmlspecialchars($user['branch_location']) : ''; ?></span></td>
<td><?php echo htmlspecialchars($user['email']); ?><br><span class="text-muted"><?php echo htmlspecialchars($user['phone'] ?: 'No phone'); ?></span></td>
<td><span class="sa-pill <?php echo $user['role'] === 'super_admin' ? 'sa-admin' : 'sa-regular'; ?>"><?php echo $user['role'] === 'super_admin' ? 'Super Admin' : 'User'; ?></span></td>
<td><span class="sa-pill <?php echo $user['status'] === 'active' ? 'sa-active' : 'sa-inactive'; ?>"><?php echo htmlspecialchars($user['status']); ?></span></td>
<td><?php echo number_format((int) $user['report_count']); ?></td>
<td><?php echo htmlspecialchars(super_admin_format_date($user['last_login_at'])); ?></td>
<td><?php echo htmlspecialchars(super_admin_format_date($user['created_at'], false)); ?></td>
<td><a class="sa-btn" href="super-admin-user.php?id=<?php echo (int) $user['id']; ?>">Open</a></td>
</tr><?php endforeach; ?>
<?php if (!$users): ?><tr><td colspan="8" class="text-center text-muted" style="padding:24px">No users found.</td></tr><?php endif; ?>
</tbody></table></div>
<div class="sa-pagination"><span><?php echo number_format($totalUsers); ?> users</span><div class="sa-pages"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?php echo $i === $page ? 'active' : ''; ?>" href="?q=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a><?php endfor; ?></div></div>
</section>
</div></div>
<div class="modal modern-modal" id="createUserModal" role="dialog"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><button class="close" type="button" data-dismiss="modal">&times;</button><h4 class="modal-title">Create User Account</h4></div><div class="modal-body"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(super_admin_csrf()); ?>"><input type="hidden" name="action" value="create_user"><div class="row"><div class="col-sm-6 form-group"><label>Full name *</label><input class="form-control" name="full_name" maxlength="120" required></div><div class="col-sm-6 form-group"><label>Branch / Company</label><input class="form-control" name="company_name" maxlength="150"></div><div class="col-sm-6 form-group"><label>Branch Location</label><select class="form-control" name="branch_location"><?php echo user_branch_location_options('', $conn); ?></select></div><div class="col-sm-6 form-group"><label>Email *</label><input class="form-control" type="email" name="email" maxlength="150" required></div><div class="col-sm-6 form-group"><label>Phone</label><input class="form-control" name="phone" maxlength="30"></div><div class="col-sm-6 form-group"><label>GSTIN</label><input class="form-control" name="gst_number" maxlength="20"></div><div class="col-sm-6 form-group"><label>Initial password *</label><input class="form-control" type="password" name="password" minlength="8" required autocomplete="new-password"></div><div class="col-sm-6 form-group"><label>Role</label><select class="form-control" name="role"><option value="user">User</option><option value="super_admin">Super Admin</option></select></div></div></div><div class="modal-footer"><button class="btn btn-default" type="button" data-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit"><i class="fa fa-user-plus"></i> Create account</button></div></form></div></div></div>
<?php include 'assets/footer.php'; ?>
