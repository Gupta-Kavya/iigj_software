<?php
require_once 'super_admin_common.php';
require_once 'agreement_helper.php';
require_once 'customer_helper.php';

agreement_table_ready($conn);
customer_master_table_ready($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_allowed_ip') {
    if (!super_admin_verify_csrf()) {
        super_admin_redirect('super-admin.php', 'Your session expired. Refresh and try again.', 'error');
    }
    auth_allowed_ip_table_ready($conn);
    $branchScope = strtoupper(trim((string) ($_POST['ip_branch_location'] ?? 'ALL')));
    if (!in_array($branchScope, ['ALL', 'SUPER_ADMIN'], true)) {
        $branchScope = user_branch_location_clean($branchScope, $conn);
    }
    if ($branchScope === '') $branchScope = 'ALL';
    $ipPattern = substr(trim((string) ($_POST['ip_pattern'] ?? '')), 0, 80);
    $ipLabel = substr(trim((string) ($_POST['ip_label'] ?? '')), 0, 150);
    if ($ipPattern === '') {
        super_admin_redirect('super-admin.php', 'Enter an IP address or pattern.', 'error');
    }
    $stmt = $conn->prepare('INSERT INTO sm_allowed_ips (branch_location, ip_pattern, label, active, created_at) VALUES (?, ?, ?, 1, NOW())');
    if (!$stmt) {
        super_admin_redirect('super-admin.php', 'Unable to save allowed IP: ' . $conn->error, 'error');
    }
    $stmt->bind_param('sss', $branchScope, $ipPattern, $ipLabel);
    $ok = $stmt->execute();
    $error = $stmt->error;
    $stmt->close();
    super_admin_redirect('super-admin.php', $ok ? 'Allowed IP saved.' : 'Unable to save allowed IP: ' . $error, $ok ? 'success' : 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_allowed_ip') {
    if (!super_admin_verify_csrf()) {
        super_admin_redirect('super-admin.php', 'Your session expired. Refresh and try again.', 'error');
    }
    auth_allowed_ip_table_ready($conn);
    $ipId = max(0, (int) ($_POST['ip_id'] ?? 0));
    $active = !empty($_POST['active']) ? 1 : 0;
    if ($ipId <= 0) {
        super_admin_redirect('super-admin.php', 'Invalid allowed IP record.', 'error');
    }
    $stmt = $conn->prepare('UPDATE sm_allowed_ips SET active = ?, updated_at = NOW() WHERE id = ?');
    if (!$stmt) {
        super_admin_redirect('super-admin.php', 'Unable to update allowed IP: ' . $conn->error, 'error');
    }
    $stmt->bind_param('ii', $active, $ipId);
    $ok = $stmt->execute();
    $error = $stmt->error;
    $stmt->close();
    super_admin_redirect('super-admin.php', $ok ? 'Allowed IP updated.' : 'Unable to update allowed IP: ' . $error, $ok ? 'success' : 'error');
}

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
    $locationAddress = substr(trim((string) ($_POST['location_address'] ?? '')), 0, 1000);
    $locationPhone = substr(trim((string) ($_POST['location_phone'] ?? '')), 0, 120);
    $locationEmail = substr(trim((string) ($_POST['location_email'] ?? '')), 0, 150);
    $locationWebsite = substr(trim((string) ($_POST['location_website'] ?? '')), 0, 150);
    $locationCin = substr(trim((string) ($_POST['location_cin'] ?? '')), 0, 80);
    $locationGst = substr(trim((string) ($_POST['location_gst'] ?? '')), 0, 80);
    if ($locationCode === '' || $locationName === '') {
        super_admin_redirect('super-admin.php', 'Enter location name and code.', 'error');
    }
    $stmt = $conn->prepare('INSERT INTO sm_branch_locations (code, name, address, phone, email, website, cin_no, gst_no, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE name = VALUES(name), address = VALUES(address), phone = VALUES(phone), email = VALUES(email), website = VALUES(website), cin_no = VALUES(cin_no), gst_no = VALUES(gst_no), active = 1, updated_at = NOW()');
    if (!$stmt) {
        super_admin_redirect('super-admin.php', 'Unable to save location: ' . $conn->error, 'error');
    }
    $stmt->bind_param('ssssssss', $locationCode, $locationName, $locationAddress, $locationPhone, $locationEmail, $locationWebsite, $locationCin, $locationGst);
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
    $branchLocation = user_branch_location_clean($_POST['branch_location'] ?? '', $conn);
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $role = ($_POST['role'] ?? 'user') === 'super_admin' ? 'super_admin' : 'user';

    if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        super_admin_redirect('super-admin.php', 'Enter a valid name, email and password of at least 8 characters.', 'error');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO sm_users (full_name, branch_location, email, phone, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, "active", NOW())');
    if (!$stmt) {
        super_admin_redirect('super-admin.php', 'Unable to create user: ' . $conn->error, 'error');
    }
    $stmt->bind_param('ssssss', $fullName, $branchLocation, $email, $phone, $hash, $role);
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
    $where[] = '(u.full_name LIKE ? OR u.branch_location LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
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
$stmt = $conn->prepare("SELECT u.id, u.full_name, u.branch_location, u.email, u.phone, u.email_verified_at, u.role, u.status, u.last_login_at, u.created_at, u.updated_at,
        {$reportCountSql},
        (SELECT COUNT(*) FROM sm_customer_master c WHERE c.user_id = u.id) AS customer_count,
        (SELECT COUNT(*) FROM sm_stone_agreements a WHERE a.user_id = u.id) AS agreement_count,
        (SELECT COUNT(*) FROM sm_stone_agreements a WHERE a.user_id = u.id AND a.delivered = 1) AS delivered_count,
        (SELECT COALESCE(SUM(a.testing_charges),0) FROM sm_stone_agreements a WHERE a.user_id = u.id) AS testing_total,
        (SELECT COALESCE(SUM(a.due_amount),0) FROM sm_stone_agreements a WHERE a.user_id = u.id) AS due_total,
        (SELECT COALESCE(SUM(a.refund_amount),0) FROM sm_stone_agreements a WHERE a.user_id = u.id) AS refund_total,
        (SELECT MAX(a.created_at) FROM sm_stone_agreements a WHERE a.user_id = u.id) AS last_agreement_at,
        (SELECT COUNT(*) FROM sm_form_masters m WHERE m.user_id = u.id) AS booked_count,
        (SELECT COUNT(*) FROM sm_form_masters m WHERE m.user_id = u.id AND m.status = 'booked') AS pending_booking_count,
        (SELECT COUNT(*) FROM sm_form_masters m WHERE m.user_id = u.id AND m.status = 'generated') AS generated_booking_count,
        (SELECT COUNT(*) FROM sm_form_masters m WHERE m.user_id = u.id AND m.status = 'cancelled') AS cancelled_booking_count
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
auth_allowed_ip_table_ready($conn);
$allowedIpRows = [];
$allowedIpResult = @$conn->query('SELECT id, branch_location, ip_pattern, label, active, created_at, updated_at FROM sm_allowed_ips ORDER BY active DESC, branch_location ASC, id DESC');
if ($allowedIpResult) {
    while ($row = $allowedIpResult->fetch_assoc()) {
        $allowedIpRows[] = $row;
    }
}
$branchLocationRows = [];
$branchResult = @$conn->query('SELECT code, name, address, phone, email, website, cin_no, gst_no, active FROM sm_branch_locations ORDER BY name, code');
if ($branchResult) {
    while ($row = $branchResult->fetch_assoc()) {
        $branchLocationRows[] = $row;
    }
}
$pages = max(1, (int) ceil($totalUsers / $perPage));
$flash = super_admin_take_flash();
include 'assets/navbar.php';
?>
<style>
.sa-page{padding-bottom:38px}.sa-head{align-items:flex-end;display:flex;justify-content:space-between;margin-bottom:18px}.sa-head h1{border:0;color:#171717;font-size:26px;font-weight:600;margin:0 0 5px;padding:0}.sa-head p{color:#737373;margin:0}.sa-head-badge{background:#171717;border-radius:999px;color:#fff;font-size:11px;font-weight:600;padding:7px 11px}.sa-stats{display:grid;gap:14px;grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:17px}.sa-stat{background:#fff;border:1px solid #ececf1;border-radius:8px;padding:16px}.sa-stat-icon{align-items:center;background:#f5f5f5;border-radius:8px;color:#404040;display:flex;height:34px;justify-content:center;margin-bottom:12px;width:34px}.sa-stat span{color:#737373;display:block;font-size:12px}.sa-stat strong{color:#171717;display:block;font-size:22px;margin:4px 0}.sa-card{background:#fff;border:1px solid #ececf1;border-radius:8px;overflow:hidden}.sa-card-head{align-items:center;border-bottom:1px solid #ececf1;display:flex;justify-content:space-between;padding:15px 17px}.sa-card-head h3{font-size:15px;font-weight:600;margin:0}.sa-filters{display:grid;gap:10px;grid-template-columns:minmax(220px,1fr) 150px auto;padding:15px 17px}.sa-filters .form-control,.sa-filters .btn{border-radius:8px;min-height:40px}.sa-table-wrap{overflow-x:auto}.sa-table{margin:0;min-width:860px}.sa-table th{background:#f7f7f8;color:#616161;font-size:10px;text-transform:uppercase;white-space:nowrap}.sa-table td{font-size:11.5px;line-height:1.35;vertical-align:middle}.sa-user strong{display:block;font-size:12px}.sa-user span,.sa-muted{color:#8a8a8a;display:block;font-size:10.5px}.sa-mini{display:grid;gap:2px}.sa-usage{display:flex;flex-wrap:wrap;gap:5px}.sa-usage span{background:#f7f7f8;border:1px solid #ececf1;border-radius:999px;color:#525252;font-size:10px;padding:4px 7px;white-space:nowrap}.sa-pill{border-radius:999px;display:inline-block;font-size:10px;font-weight:600;margin:0 3px 4px 0;padding:4px 8px}.sa-active{background:#dcfce7;color:#166534}.sa-inactive{background:#fee2e2;color:#991b1b}.sa-admin{background:#ede9fe;color:#5b21b6}.sa-regular{background:#f3f4f6;color:#525252}.sa-btn{border:1px solid #e5e5e5;border-radius:7px;color:#404040;display:inline-block;padding:6px 9px}.sa-btn:hover{background:#f7f7f8;color:#171717;text-decoration:none}.sa-pagination{align-items:center;border-top:1px solid #ececf1;display:flex;justify-content:space-between;padding:13px 17px}.sa-pages{display:flex;gap:5px}.sa-pages a{border:1px solid #e5e5e5;border-radius:6px;color:#404040;padding:5px 9px}.sa-pages a.active{background:#171717;color:#fff}.modern-modal .modal-content{border:0;border-radius:10px;overflow:hidden}.modern-modal .modal-header,.modern-modal .modal-footer{border-color:#ececf1}@media(max-width:900px){.sa-stats{grid-template-columns:repeat(2,1fr)}.sa-filters{grid-template-columns:1fr 1fr}}@media(max-width:600px){.sa-stats,.sa-filters{grid-template-columns:1fr}.sa-head-badge{display:none}}
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
<section class="sa-card" style="margin-bottom:17px">
    <div class="sa-card-head"><h3>Allowed IP Addresses</h3><span class="sa-muted">Current IP: <?php echo htmlspecialchars(auth_client_ip() ?: 'Unknown'); ?></span></div>
    <div style="padding:15px 17px">
        <form method="post" class="row">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(super_admin_csrf()); ?>">
            <input type="hidden" name="action" value="save_allowed_ip">
            <div class="col-sm-3 form-group">
                <label>Apply To</label>
                <select class="form-control" name="ip_branch_location">
                    <option value="ALL">All branches / users</option>
                    <option value="SUPER_ADMIN">Super Admin only</option>
                    <?php foreach ($branchLocations as $code => $label): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3 form-group"><label>IP Address / Pattern</label><input class="form-control" name="ip_pattern" maxlength="80" placeholder="<?php echo htmlspecialchars(auth_client_ip() ?: '192.168.1.*'); ?>" required></div>
            <div class="col-sm-4 form-group"><label>Label</label><input class="form-control" name="ip_label" maxlength="150" placeholder="Sitapura office / Delhi static IP"></div>
            <div class="col-sm-2 form-group"><label>&nbsp;</label><button class="btn btn-primary btn-block"><i class="fa fa-plus"></i> Add IP</button></div>
        </form>
        <div class="sa-muted" style="margin:0 0 10px">Detected IPs: <?php echo htmlspecialchars(implode(', ', auth_client_ip_candidates()) ?: 'Not available'); ?></div>
        <div class="sa-muted" style="margin:0 0 10px">Supports exact IP like 103.10.20.30, wildcard like 192.168.1.*, or IPv4 CIDR like 192.168.1.0/24. If no active IPs are configured, access remains open.</div>
        <div class="table-responsive">
            <table class="table table-hover" style="margin-bottom:0;min-width:820px">
                <thead><tr><th>Scope</th><th>IP / Pattern</th><th>Label</th><th>Status</th><th>Created</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($allowedIpRows as $ipRow): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ipRow['branch_location'] ?: 'ALL'); ?></td>
                            <td><strong><?php echo htmlspecialchars($ipRow['ip_pattern']); ?></strong></td>
                            <td><?php echo htmlspecialchars($ipRow['label'] ?: '-'); ?></td>
                            <td><span class="sa-pill <?php echo !empty($ipRow['active']) ? 'sa-active' : 'sa-inactive'; ?>"><?php echo !empty($ipRow['active']) ? 'Active' : 'Disabled'; ?></span></td>
                            <td><?php echo htmlspecialchars(super_admin_format_date($ipRow['created_at'], false)); ?></td>
                            <td>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(super_admin_csrf()); ?>">
                                    <input type="hidden" name="action" value="toggle_allowed_ip">
                                    <input type="hidden" name="ip_id" value="<?php echo (int) $ipRow['id']; ?>">
                                    <input type="hidden" name="active" value="<?php echo !empty($ipRow['active']) ? '0' : '1'; ?>">
                                    <button class="btn btn-default btn-xs"><?php echo !empty($ipRow['active']) ? 'Disable' : 'Enable'; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$allowedIpRows): ?><tr><td colspan="6" class="text-center text-muted" style="padding:18px">No allowed IPs configured yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<section class="sa-card" style="margin-bottom:17px">
    <div class="sa-card-head"><h3>Branch Locations</h3></div>
    <div style="padding:15px 17px">
        <form method="post" class="row">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(super_admin_csrf()); ?>">
            <input type="hidden" name="action" value="save_location">
            <div class="col-sm-3 form-group"><label>Location Name</label><input class="form-control" name="location_name" maxlength="120" placeholder="Example: Mumbai" required></div>
            <div class="col-sm-2 form-group"><label>Location Code</label><input class="form-control" name="location_code" maxlength="60" placeholder="MUMBAI"></div>
            <div class="col-sm-7 form-group"><label>Company / Branch Address</label><input class="form-control" name="location_address" maxlength="1000" placeholder="Full address shown on agreement"></div>
            <div class="col-sm-3 form-group"><label>Phone</label><input class="form-control" name="location_phone" maxlength="120" placeholder="+91-..."></div>
            <div class="col-sm-3 form-group"><label>Email</label><input class="form-control" name="location_email" maxlength="150" placeholder="branch@example.com"></div>
            <div class="col-sm-2 form-group"><label>Website</label><input class="form-control" name="location_website" maxlength="150" placeholder="example.com"></div>
            <div class="col-sm-2 form-group"><label>CIN</label><input class="form-control" name="location_cin" maxlength="80"></div>
            <div class="col-sm-2 form-group"><label>GST No.</label><input class="form-control" name="location_gst" maxlength="80"></div>
            <div class="col-sm-2 form-group"><label>&nbsp;</label><button class="btn btn-primary btn-block"><i class="fa fa-plus"></i> Add</button></div>
        </form>
        <div class="table-responsive" style="margin-top:10px">
            <table class="table table-hover" style="margin-bottom:0;min-width:1100px">
                <thead><tr><th>Code</th><th>Name</th><th>Address</th><th>Phone</th><th>Email</th><th>Website</th><th>CIN</th><th>GST</th><th>Save</th></tr></thead>
                <tbody>
                    <?php foreach ($branchLocationRows as $branchRow): ?>
                        <tr>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(super_admin_csrf()); ?>">
                                <input type="hidden" name="action" value="save_location">
                                <td><input class="form-control" name="location_code" value="<?php echo htmlspecialchars($branchRow['code']); ?>" maxlength="60" readonly></td>
                                <td><input class="form-control" name="location_name" value="<?php echo htmlspecialchars($branchRow['name']); ?>" maxlength="120" required></td>
                                <td><input class="form-control" name="location_address" value="<?php echo htmlspecialchars($branchRow['address'] ?? ''); ?>" maxlength="1000"></td>
                                <td><input class="form-control" name="location_phone" value="<?php echo htmlspecialchars($branchRow['phone'] ?? ''); ?>" maxlength="120"></td>
                                <td><input class="form-control" name="location_email" value="<?php echo htmlspecialchars($branchRow['email'] ?? ''); ?>" maxlength="150"></td>
                                <td><input class="form-control" name="location_website" value="<?php echo htmlspecialchars($branchRow['website'] ?? ''); ?>" maxlength="150"></td>
                                <td><input class="form-control" name="location_cin" value="<?php echo htmlspecialchars($branchRow['cin_no'] ?? ''); ?>" maxlength="80"></td>
                                <td><input class="form-control" name="location_gst" value="<?php echo htmlspecialchars($branchRow['gst_no'] ?? ''); ?>" maxlength="80"></td>
                                <td><button class="btn btn-primary btn-sm"><i class="fa fa-save"></i></button></td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$branchLocationRows): ?><tr><td colspan="9" class="text-muted text-center">No locations found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<section class="sa-card"><div class="sa-card-head"><h3>Branch / user accounts</h3><button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createUserModal"><i class="fa fa-user-plus"></i> Create user</button></div>
<form class="sa-filters" method="get"><input class="form-control" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, branch, email, phone"><select class="form-control" name="status"><option value="">All statuses</option><option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option><option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option></select><button class="btn btn-default">Filter</button></form>
<div class="sa-table-wrap"><table class="table table-hover sa-table"><thead><tr><th>User / Branch</th><th>Contact</th><th>Access</th><th>Activity</th><th>Last Login</th><th></th></tr></thead><tbody>
<?php foreach ($users as $user): ?><tr>
<td class="sa-user"><strong><?php echo htmlspecialchars($user['full_name']); ?></strong><span><?php echo htmlspecialchars($user['branch_location'] ?: 'No branch assigned'); ?></span></td>
<td class="sa-mini"><span><?php echo htmlspecialchars($user['email']); ?></span><span class="sa-muted"><?php echo htmlspecialchars($user['phone'] ?: 'No phone'); ?></span></td>
<td><span class="sa-pill <?php echo $user['role'] === 'super_admin' ? 'sa-admin' : 'sa-regular'; ?>"><?php echo $user['role'] === 'super_admin' ? 'Super Admin' : 'User'; ?></span><br><span class="sa-pill <?php echo $user['status'] === 'active' ? 'sa-active' : 'sa-inactive'; ?>"><?php echo htmlspecialchars($user['status']); ?></span></td>
<td><div class="sa-usage"><span>Agreements: <?php echo number_format((int) $user['agreement_count']); ?></span><span>Reports: <?php echo number_format((int) $user['report_count']); ?></span><span>Customers: <?php echo number_format((int) $user['customer_count']); ?></span></div></td>
<td><?php echo htmlspecialchars(super_admin_format_date($user['last_login_at'])); ?></td>
<td><a class="sa-btn" href="super-admin-user.php?id=<?php echo (int) $user['id']; ?>">Open</a></td>
</tr><?php endforeach; ?>
<?php if (!$users): ?><tr><td colspan="6" class="text-center text-muted" style="padding:24px">No users found.</td></tr><?php endif; ?>
</tbody></table></div>
<div class="sa-pagination"><span><?php echo number_format($totalUsers); ?> users</span><div class="sa-pages"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?php echo $i === $page ? 'active' : ''; ?>" href="?q=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a><?php endfor; ?></div></div>
</section>
</div></div>
<div class="modal modern-modal" id="createUserModal" role="dialog"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><button class="close" type="button" data-dismiss="modal">&times;</button><h4 class="modal-title">Create User Account</h4></div><div class="modal-body"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(super_admin_csrf()); ?>"><input type="hidden" name="action" value="create_user"><div class="row"><div class="col-sm-6 form-group"><label>Full name *</label><input class="form-control" name="full_name" maxlength="120" required></div><div class="col-sm-6 form-group"><label>Branch Location</label><select class="form-control" name="branch_location"><?php echo user_branch_location_options('', $conn); ?></select></div><div class="col-sm-6 form-group"><label>Email *</label><input class="form-control" type="email" name="email" maxlength="150" required></div><div class="col-sm-6 form-group"><label>Phone</label><input class="form-control" name="phone" maxlength="30"></div><div class="col-sm-6 form-group"><label>Initial password *</label><input class="form-control" type="password" name="password" minlength="8" required autocomplete="new-password"></div><div class="col-sm-6 form-group"><label>Role</label><select class="form-control" name="role"><option value="user">User</option><option value="super_admin">Super Admin</option></select></div></div></div><div class="modal-footer"><button class="btn btn-default" type="button" data-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit"><i class="fa fa-user-plus"></i> Create account</button></div></form></div></div></div>
<?php include 'assets/footer.php'; ?>
