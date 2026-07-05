<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'atm_config.php';

$userId = auth_current_user_id();
cstone_report_type_master_ready($conn);
$scopeSql = user_branch_scope_sql($conn, $userId, 'user_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $name = substr(trim((string) ($_POST['report_name'] ?? '')), 0, 160);
        $format = strtolower(trim((string) ($_POST['report_format'] ?? 'a4')));
        $format = in_array($format, ['a4', 'atm', 'postcard'], true) ? $format : 'a4';
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $active = !empty($_POST['active']) ? 1 : 0;
        if ($name !== '') {
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE sm_colour_stone_report_types SET report_name = ?, report_format = ?, active = ?, updated_at = NOW() WHERE id = ? AND {$scopeSql}");
                $stmt->bind_param('ssii', $name, $format, $active, $id);
            } else {
                $stmt = $conn->prepare('INSERT INTO sm_colour_stone_report_types (user_id, report_name, report_format, active) VALUES (?, ?, ?, 1)');
                $stmt->bind_param('iss', $userId, $name, $format);
            }
            $stmt->execute();
            $stmt->close();
        }
        header('Location: colour-report-type-master.php');
        exit;
    }
}

$rows = cstone_report_type_rows($conn, $userId, false);
include "assets/navbar.php";
?>
<div id="page-wrapper">
    <div class="container-fluid master-page">
        <div class="master-header">
            <h1><i class="fa fa-file-text-o"></i> Colour Stone Report Types</h1>
            <p>Create report type options for Colour Stone feeding and separate builder formats.</p>
        </div>
        <div class="master-card">
            <div class="master-card-head"><h3>Add report type</h3><p>After saving, it appears in Colour Stone feeding and the selected report builder.</p></div>
            <div class="master-card-body">
                <form method="post" class="master-field-grid">
                    <input type="hidden" name="action" value="save">
                    <div class="master-field span-2"><label>Report type name</label><input class="form-control" name="report_name" maxlength="160" required></div>
                    <div class="master-field"><label>Report format</label><select class="form-control" name="report_format"><option value="a4">A4</option><option value="atm">ATM Card</option><option value="postcard">Postcard</option></select></div>
                    <div class="master-action-bar"><button class="btn btn-primary"><i class="fa fa-save"></i> Save Report Type</button></div>
                </form>
            </div>
        </div>
        <div class="master-card" style="margin-top:16px">
            <div class="master-card-head"><h3>Existing report types</h3><p>Use Card Builder / A4 Builder / Postcard Builder to edit each format.</p></div>
            <div class="master-card-body">
                <div class="table-responsive"><table class="table table-hover">
                    <thead><tr><th>Report Type</th><th>Builder Code</th><th>Report Format</th><th>Status</th><th>ATM Builder</th><th>A4 Builder</th><th>Postcard Builder</th><th>Save</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): $canEdit = in_array((int) $row['user_id'], user_branch_user_ids($conn, $userId), true); $code = cstone_report_type_code($row['id']); ?>
                        <tr>
                            <form method="post">
                                <input type="hidden" name="action" value="save">
                                <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                <td><input class="form-control" name="report_name" value="<?php echo htmlspecialchars($row['report_name']); ?>" <?php echo $canEdit ? '' : 'readonly'; ?>></td>
                                <td><?php echo htmlspecialchars($code); ?></td>
                                <td><select class="form-control" name="report_format" <?php echo $canEdit ? '' : 'disabled'; ?>><option value="a4" <?php echo ($row['report_format'] ?? 'a4') === 'a4' ? 'selected' : ''; ?>>A4</option><option value="atm" <?php echo ($row['report_format'] ?? '') === 'atm' ? 'selected' : ''; ?>>ATM Card</option><option value="postcard" <?php echo ($row['report_format'] ?? '') === 'postcard' ? 'selected' : ''; ?>>Postcard</option></select></td>
                                <td><label style="font-weight:500"><input type="checkbox" name="active" value="1" <?php echo !empty($row['active']) ? 'checked' : ''; ?> <?php echo $canEdit ? '' : 'disabled'; ?>> Active</label></td>
                                <td><a class="btn btn-default btn-sm" href="settings.php?type=<?php echo urlencode($code); ?>">ATM</a></td>
                                <td><a class="btn btn-default btn-sm" href="a4Settings.php?type=<?php echo urlencode($code); ?>">A4</a></td>
                                <td><a class="btn btn-default btn-sm" href="postcardSettings.php?type=<?php echo urlencode($code); ?>">Postcard</a></td>
                                <td><button class="btn btn-primary btn-sm" <?php echo $canEdit ? '' : 'disabled'; ?>><i class="fa fa-save"></i></button></td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted">No report types added yet.</td></tr><?php endif; ?>
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>
</div>
<?php include "assets/footer.php"; ?>
