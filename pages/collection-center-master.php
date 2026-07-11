<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'user_branch_helper.php';

$userId = auth_current_user_id();
user_collection_center_ready($conn);
$message = '';
$messageType = 'success';

function collection_center_redirect($message = '', $type = 'success')
{
    $url = 'collection-center-master.php';
    if ($message !== '') {
        $url .= '?msg=' . urlencode($message) . '&type=' . urlencode($type);
    }
    header('Location: ' . $url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $branch = auth_is_super_admin()
            ? user_branch_location_clean($_POST['branch_location'] ?? '', $conn)
            : user_branch_location_for_user($conn, $userId);
        $name = substr(trim((string) ($_POST['center_name'] ?? '')), 0, 120);
        $code = user_collection_center_code_normalize($_POST['center_code'] ?? '');
        $active = !empty($_POST['active']) ? 1 : 0;
        if ($branch === '' || $name === '' || $code === '') {
            collection_center_redirect('Branch, center name and center code are required.', 'error');
        }
        if ($id > 0) {
            if (auth_is_super_admin()) {
                $stmt = $conn->prepare('UPDATE sm_collection_centers SET branch_location = ?, center_name = ?, center_code = ?, active = ?, updated_at = NOW() WHERE id = ?');
                $stmt->bind_param('sssii', $branch, $name, $code, $active, $id);
            } else {
                $stmt = $conn->prepare('UPDATE sm_collection_centers SET center_name = ?, center_code = ?, active = ?, updated_at = NOW() WHERE id = ? AND branch_location = ?');
                $stmt->bind_param('ssiis', $name, $code, $active, $id, $branch);
            }
        } else {
            $stmt = $conn->prepare('INSERT INTO sm_collection_centers (branch_location, center_name, center_code, active) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE center_name = VALUES(center_name), active = 1, updated_at = NOW()');
            $stmt->bind_param('sss', $branch, $name, $code);
        }
        if (!$stmt || !$stmt->execute()) {
            collection_center_redirect('Unable to save collection center. Code may already exist in this branch.', 'error');
        }
        if ($stmt) $stmt->close();
        collection_center_redirect('Collection center saved.');
    }
}

$message = trim((string) ($_GET['msg'] ?? ''));
$messageType = ($_GET['type'] ?? 'success') === 'error' ? 'danger' : 'success';
$branchFilter = auth_is_super_admin() ? user_branch_location_clean($_GET['branch'] ?? '', $conn) : user_branch_location_for_user($conn, $userId);
$where = '';
if ($branchFilter !== '') {
    $where = "WHERE branch_location = '" . $conn->real_escape_string($branchFilter) . "'";
}
$rows = [];
$result = @$conn->query("SELECT id, branch_location, center_name, center_code, active, updated_at FROM sm_collection_centers {$where} ORDER BY branch_location ASC, center_name ASC, center_code ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

include "assets/navbar.php";
?>
<div id="page-wrapper">
    <div class="container-fluid master-page">
        <div class="master-header">
            <h1><i class="fa fa-map-marker"></i> Collection Centers</h1>
            <p>Collection center code is used in report/ref no between agreement no and certificate no.</p>
        </div>
        <?php if ($message): ?><div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <div class="master-card">
            <div class="master-card-head"><h3>Add collection center</h3><p>Example: Johari Bazar with code J under Sitapura branch.</p></div>
            <div class="master-card-body">
                <form method="post" class="master-field-grid">
                    <input type="hidden" name="action" value="save">
                    <div class="master-field"><label>Branch Location</label>
                        <?php if (auth_is_super_admin()): ?>
                            <select class="form-control" name="branch_location"><?php echo user_branch_location_options($branchFilter, $conn, false); ?></select>
                        <?php else: ?>
                            <input class="form-control" value="<?php echo htmlspecialchars(user_branch_location_label($conn, $branchFilter)); ?>" readonly>
                        <?php endif; ?>
                    </div>
                    <div class="master-field span-2"><label>Center Name</label><input class="form-control" name="center_name" maxlength="120" required placeholder="Johari Bazar"></div>
                    <div class="master-field"><label>Code Letter</label><input class="form-control" name="center_code" maxlength="6" required placeholder="J"></div>
                    <div class="master-action-bar"><button class="btn btn-primary"><i class="fa fa-save"></i> Save Center</button></div>
                </form>
            </div>
        </div>
        <div class="master-card" style="margin-top:16px">
            <div class="master-card-head"><h3>Existing collection centers</h3><p>Deactivate a center instead of deleting it if old agreements used its code.</p></div>
            <div class="master-card-body">
                <?php if (auth_is_super_admin()): ?>
                    <form method="get" style="max-width:320px;margin-bottom:12px"><label>Filter branch</label><select class="form-control" name="branch" onchange="this.form.submit()"><option value="">All branches</option><?php echo user_branch_location_options($branchFilter, $conn, false); ?></select></form>
                <?php endif; ?>
                <div class="table-responsive"><table class="table table-hover">
                    <thead><tr><th>Branch</th><th>Collection Center</th><th>Code</th><th>Status</th><th>Save</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <form method="post">
                                <input type="hidden" name="action" value="save">
                                <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                <td>
                                    <?php if (auth_is_super_admin()): ?>
                                        <select class="form-control" name="branch_location"><?php echo user_branch_location_options($row['branch_location'] ?? '', $conn, false); ?></select>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars(user_branch_location_label($conn, $row['branch_location'] ?? '')); ?>
                                    <?php endif; ?>
                                </td>
                                <td><input class="form-control" name="center_name" value="<?php echo htmlspecialchars($row['center_name']); ?>" maxlength="120" required></td>
                                <td><input class="form-control" name="center_code" value="<?php echo htmlspecialchars($row['center_code']); ?>" maxlength="6" required></td>
                                <td><label style="font-weight:500"><input type="checkbox" name="active" value="1" <?php echo !empty($row['active']) ? 'checked' : ''; ?>> Active</label></td>
                                <td><button class="btn btn-primary btn-sm"><i class="fa fa-save"></i></button></td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted">No collection centers found.</td></tr><?php endif; ?>
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>
</div>
<?php include "assets/footer.php"; ?>
