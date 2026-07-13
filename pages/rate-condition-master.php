<?php
require_once 'auth.php';
auth_require_login();
auth_require_super_admin();
require_once 'db_connect.php';
require_once 'rate_condition_helper.php';
require_once 'rate_helper.php';
require_once 'user_branch_helper.php';

rate_condition_table_ready($conn);
rate_master_table_ready($conn);

if (!isset($_SESSION['rate_condition_csrf'])) {
    $_SESSION['rate_condition_csrf'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['rate_condition_csrf'];

function rate_condition_redirect($message, $type = 'success')
{
    $_SESSION['rate_condition_flash'] = ['message' => $message, 'type' => $type];
    header('Location: rate-condition-master.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['csrf_token'] ?? ''))) {
        rate_condition_redirect('Your session expired. Refresh and try again.', 'error');
    }
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'delete') {
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $stmt = $conn->prepare('DELETE FROM sm_rate_conditions WHERE id = ?');
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        rate_condition_redirect($ok ? 'Rate condition deleted.' : 'Unable to delete condition.', $ok ? 'success' : 'error');
    }

    $id = max(0, (int) ($_POST['id'] ?? 0));
    $rateMasterId = max(0, (int) ($_POST['rate_master_id'] ?? 0));
    $rateCode = rate_condition_code($_POST['rate_code'] ?? '');
    if ($rateMasterId > 0) {
        $rateStmt = $conn->prepare('SELECT rate_code FROM sm_rate_master WHERE id = ? LIMIT 1');
        if ($rateStmt) {
            $rateStmt->bind_param('i', $rateMasterId);
            $rateStmt->execute();
            $rateRow = $rateStmt->get_result()->fetch_assoc();
            $rateStmt->close();
            $rateCode = rate_condition_code($rateRow['rate_code'] ?? $rateCode);
        }
    }
    $branchLocationRaw = (string) ($_POST['branch_location'] ?? 'ALL');
    $branchLocation = strtoupper(trim($branchLocationRaw)) === 'ALL' ? 'ALL' : user_branch_location_clean($branchLocationRaw, $conn);
    $ruleType = (string) ($_POST['rule_type'] ?? '');
    $value1 = rate_condition_number($_POST['value1'] ?? 0);
    $value2 = rate_condition_number($_POST['value2'] ?? 0);
    $priority = (int) ($_POST['priority'] ?? 100);
    $active = !empty($_POST['active']) ? 1 : 0;
    $notes = substr(trim((string) ($_POST['notes'] ?? '')), 0, 255);

    if ($rateCode === '' || !array_key_exists($ruleType, rate_condition_types())) {
        rate_condition_redirect('Enter rate code and valid rule type.', 'error');
    }
    if ($branchLocation === '') {
        rate_condition_redirect('Select a valid branch location.', 'error');
    }

    if ($id > 0) {
        $stmt = $conn->prepare('UPDATE sm_rate_conditions SET rate_code=?, branch_location=?, rule_type=?, value1=?, value2=?, priority=?, active=?, notes=?, updated_at=NOW() WHERE id=?');
        $stmt->bind_param('sssddiisi', $rateCode, $branchLocation, $ruleType, $value1, $value2, $priority, $active, $notes, $id);
    } else {
        $stmt = $conn->prepare('INSERT INTO sm_rate_conditions (rate_code, branch_location, rule_type, value1, value2, priority, active, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('sssddiis', $rateCode, $branchLocation, $ruleType, $value1, $value2, $priority, $active, $notes);
    }
    $ok = $stmt && $stmt->execute();
    $error = $stmt ? $stmt->error : $conn->error;
    if ($stmt) $stmt->close();
    rate_condition_redirect($ok ? 'Rate condition saved.' : 'Unable to save condition: ' . $error, $ok ? 'success' : 'error');
}

$rules = rate_condition_list($conn, false);
$ruleTypes = rate_condition_types();
$rateRows = [];
$rateByCode = [];
$rateResult = $conn->query('SELECT id, description, rate_code, rate_member, rate_non_member FROM sm_rate_master ORDER BY description ASC');
if ($rateResult) {
    while ($row = $rateResult->fetch_assoc()) {
        $rateRows[] = $row;
        $code = rate_condition_code($row['rate_code'] ?? '');
        if ($code !== '' && !isset($rateByCode[$code])) {
            $rateByCode[$code] = $row;
        }
    }
}
$flash = $_SESSION['rate_condition_flash'] ?? null;
unset($_SESSION['rate_condition_flash']);
include 'assets/navbar.php';
?>
<style>
.rc-page{padding-bottom:38px}.rc-head{border-bottom:1px solid #ececf1;margin-bottom:18px;padding-bottom:15px}.rc-head h1{border:0;color:#171717;font-size:24px;font-weight:600;margin:0 0 5px;padding:0}.rc-head p{color:#737373;margin:0}.rc-grid{display:grid;gap:16px;grid-template-columns:380px minmax(0,1fr)}.rc-card{background:#fff;border:1px solid #ececf1;border-radius:8px;overflow:hidden}.rc-card-head{border-bottom:1px solid #ececf1;padding:14px 16px}.rc-card-head h3{font-size:15px;font-weight:600;margin:0}.rc-body{padding:16px}.rc-field{margin-bottom:12px}.rc-field label{color:#404040;font-size:12px;font-weight:600}.rc-field .form-control{border-radius:8px;box-shadow:none;min-height:38px}.rc-row{display:grid;gap:10px;grid-template-columns:1fr 1fr}.rc-actions{display:flex;gap:8px}.rc-primary{background:#171717;border-color:#171717;color:#fff}.rc-primary:hover,.rc-primary:focus{background:#404040;color:#fff}.rc-table{margin:0;min-width:980px}.rc-table th{background:#f7f7f8;color:#616161;font-size:11px;text-transform:uppercase}.rc-table td{font-size:12px;vertical-align:middle}.rc-pill{border-radius:999px;display:inline-block;font-size:10px;font-weight:600;padding:4px 8px}.rc-on{background:#dcfce7;color:#166534}.rc-off{background:#fee2e2;color:#991b1b}.rc-muted{color:#737373;font-size:11px}.rc-table-wrap{overflow-x:auto}@media(max-width:1050px){.rc-grid{grid-template-columns:1fr}}
</style>
<div id="page-wrapper"><div class="container-fluid rc-page">
    <div class="rc-head"><h1><i class="fa fa-sliders"></i> Rate Condition Master</h1><p>Create calculation rules by category and branch location.</p></div>
    <?php if ($flash): ?><div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : 'success'; ?>"><?php echo htmlspecialchars($flash['message']); ?></div><?php endif; ?>
    <div class="rc-grid">
        <section class="rc-card">
            <div class="rc-card-head"><h3 id="form_title">Add Condition</h3></div>
            <div class="rc-body">
                <form method="post" id="condition_form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" id="condition_id">
                    <div class="rc-row">
                        <div class="rc-field"><label>Category *</label><select class="form-control" name="rate_master_id" id="rate_master_id" required><option value="">Select category</option><?php foreach ($rateRows as $rate): ?><option value="<?php echo (int) $rate['id']; ?>" data-rate-code="<?php echo htmlspecialchars($rate['rate_code']); ?>"><?php echo htmlspecialchars($rate['description']); ?><?php echo trim((string) $rate['rate_code']) !== '' ? ' (Code: ' . htmlspecialchars($rate['rate_code']) . ')' : ''; ?></option><?php endforeach; ?></select><input type="hidden" name="rate_code" id="rate_code"></div>
                        <div class="rc-field"><label>Branch Location</label><select class="form-control" name="branch_location" id="branch_location"><option value="ALL">All</option><?php echo user_branch_location_options('', $conn, false); ?></select></div>
                    </div>
                    <div class="rc-field"><label>Rule Type *</label><select class="form-control" name="rule_type" id="rule_type" required><option value="">Select rule</option><?php foreach ($ruleTypes as $value => $label): ?><option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option><?php endforeach; ?></select></div>
                    <div class="rc-row">
                        <div class="rc-field"><label>Value 1</label><input class="form-control" name="value1" id="value1" value="0"></div>
                        <div class="rc-field"><label>Value 2</label><input class="form-control" name="value2" id="value2" value="0"></div>
                    </div>
                    <div class="rc-row">
                        <div class="rc-field"><label>Priority</label><input class="form-control" type="number" name="priority" id="priority" value="100"></div>
                        <div class="rc-field"><label>Status</label><div><label><input type="checkbox" name="active" id="active" value="1" checked> Active</label></div></div>
                    </div>
                    <div class="rc-field"><label>Notes</label><input class="form-control" name="notes" id="notes" maxlength="255"></div>
                    <div class="rc-actions"><button class="btn rc-primary"><i class="fa fa-save"></i> Save</button><button class="btn btn-default" type="button" id="reset_form"><i class="fa fa-refresh"></i> New</button></div>
                </form>
            </div>
        </section>
        <section class="rc-card">
            <div class="rc-card-head"><h3>Saved Conditions</h3></div>
            <div class="rc-table-wrap"><table class="table table-hover rc-table"><thead><tr><th>Category</th><th>Location</th><th>Rule</th><th>Value 1</th><th>Priority</th><th>Status</th><th>Notes</th><th></th></tr></thead><tbody>
                <?php foreach ($rules as $rule): ?><tr data-rule='<?php echo htmlspecialchars(json_encode($rule), ENT_QUOTES, 'UTF-8'); ?>'>
                    <?php $rateInfo = $rateByCode[rate_condition_code($rule['rate_code'])] ?? null; ?>
                    <td><strong><?php echo htmlspecialchars($rateInfo['description'] ?? ('Rate Code ' . $rule['rate_code'])); ?></strong><div class="rc-muted">Code: <?php echo htmlspecialchars($rule['rate_code']); ?></div></td>
                    <td><?php echo htmlspecialchars($rule['branch_location']); ?></td>
                    <td><?php echo htmlspecialchars($ruleTypes[$rule['rule_type']] ?? $rule['rule_type']); ?><div class="rc-muted"><?php echo htmlspecialchars($rule['rule_type']); ?></div></td>
                    <td><?php echo htmlspecialchars(number_format((float) $rule['value1'], 3, '.', '')); ?></td>
                    <td><?php echo (int) $rule['priority']; ?></td>
                    <td><span class="rc-pill <?php echo $rule['active'] ? 'rc-on' : 'rc-off'; ?>"><?php echo $rule['active'] ? 'Active' : 'Inactive'; ?></span></td>
                    <td><?php echo htmlspecialchars((string) $rule['notes']); ?></td>
                    <td><button class="btn btn-default btn-xs edit-rule" type="button">Edit</button> <form method="post" style="display:inline" onsubmit="return confirm('Delete this condition?')"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $rule['id']; ?>"><button class="btn btn-danger btn-xs">Delete</button></form></td>
                </tr><?php endforeach; ?>
                <?php if (!$rules): ?><tr><td colspan="8" class="text-center text-muted" style="padding:24px">No rate conditions found.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>
    </div>
</div></div>
<script>
$(function(){
    function resetForm(){
        $("#form_title").text("Add Condition");
        $("#condition_form")[0].reset();
        $("#condition_id").val("");
        $("#rate_code").val("");
        $("#active").prop("checked", true);
        $("#priority").val("100");
    }
    $("#rate_master_id").on("change", function(){
        $("#rate_code").val($(this).find("option:selected").data("rate-code") || "");
    });
    $("#reset_form").on("click", resetForm);
    $(".edit-rule").on("click", function(){
        var rule = $(this).closest("tr").data("rule");
        $("#form_title").text("Edit Condition");
        $("#condition_id").val(rule.id);
        $("#rate_code").val(rule.rate_code);
        var matched = $("#rate_master_id option").filter(function(){ return String($(this).data("rate-code") || "").toUpperCase() === String(rule.rate_code || "").toUpperCase(); }).first();
        $("#rate_master_id").val(matched.length ? matched.val() : "");
        $("#branch_location").val(rule.branch_location);
        $("#rule_type").val(rule.rule_type);
        $("#value1").val(rule.value1);
        $("#value2").val(rule.value2);
        $("#priority").val(rule.priority);
        $("#active").prop("checked", Number(rule.active) === 1);
        $("#notes").val(rule.notes || "");
        window.scrollTo({ top: 0, behavior: "smooth" });
    });
});
</script>
<?php include 'assets/footer.php'; ?>
