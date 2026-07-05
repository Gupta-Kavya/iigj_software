<?php
require_once 'auth.php';
auth_require_login();
include("db_connect.php");
require_once 'master_data_helper.php';
require_once 'atm_config.php';

header('Content-Type: application/json');

$certi_no = isset($_POST['certi_no']) ? (int) $_POST['certi_no'] : 0;
if ($certi_no <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid certificate number.']);
    exit;
}

$user_id = auth_current_user_id();
$hasFormUserId = atm_table_has_column($conn, 'sm_form_data', 'user_id');
$scopeSql = user_branch_scope_sql($conn, $user_id, 'user_id');
$stmt = $hasFormUserId
    ? $conn->prepare("SELECT * FROM sm_form_data WHERE {$scopeSql} AND certi_no = ?")
    : $conn->prepare("SELECT * FROM sm_form_data WHERE certi_no = ?");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Unable to prepare search query.']);
    exit;
}
if ($hasFormUserId) {
    $stmt->bind_param('i', $certi_no);
} else {
    $stmt->bind_param('i', $certi_no);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows <= 0) {
    echo json_encode(['status' => 'not_found', 'message' => 'No report found for certificate number ' . $certi_no . '.']);
    exit;
}

$row = $result->fetch_assoc();
$stmt->close();

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function master_options($conn, $table, $column, $user_id)
{
    $allowed = [
        'sm_master_shape_cut' => 'shape_cut',
        'sm_master_colour' => 'colour',
        'sm_master_ri' => 'ri',
        'sm_master_magnification' => 'magni',
        'sm_master_stone_name' => 'stone_name',
    ];

    if (!isset($allowed[$table]) || $allowed[$table] !== $column) {
        return '';
    }

    $html = '';
    $rows = master_fetch_rows($conn, $table, [$column], $user_id, [$column], $column);
    foreach ($rows as $item) {
        $value = trim((string) ($item[$column] ?? ''));
        if ($value === '') {
            continue;
        }
        $html .= '<option value="' . e($value) . '"></option>';
    }
    return $html;
}

$shapeOptions = master_options($conn, 'sm_master_shape_cut', 'shape_cut', $user_id);
$colourOptions = master_options($conn, 'sm_master_colour', 'colour', $user_id);
$riOptions = master_options($conn, 'sm_master_ri', 'ri', $user_id);
$magniOptions = master_options($conn, 'sm_master_magnification', 'magni', $user_id);
$stoneOptions = master_options($conn, 'sm_master_stone_name', 'stone_name', $user_id);
$typeLabels = atm_report_type_labels();
$reportType = strtoupper((string) ($row['type'] ?? 'S'));
$reportTypeLabel = $typeLabels[$reportType] ?? $reportType;

$conn->close();

ob_start();
?>
<div class="edit-form-card">
    <div class="edit-form-head">
        <div>
            <h3><i class="fa fa-file-text-o"></i> Editing Certificate #<?php echo e($row['certi_no']); ?></h3>
            <p>Report No: <?php echo e($row['report_no']); ?> · Type: <?php echo e($reportTypeLabel); ?></p>
        </div>
        <span class="edit-badge">Loaded</span>
    </div>
    <form id="edit-report-form" method="POST" action="edit_saver.php">
        <input type="hidden" name="certi_no" value="<?php echo e($row['certi_no']); ?>">
        <input type="hidden" name="report_type" value="<?php echo e($row['type'] ?: 'S'); ?>">
        <div class="edit-form-grid">
            <div class="form-group">
                <label>Certificate Number</label>
                <input type="text" class="form-control" value="<?php echo e($row['certi_no']); ?>" readonly>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" class="form-control" name="date" value="<?php echo e($row['date']); ?>">
            </div>
            <div class="form-group">
                <label><?php echo $reportType === 'J' ? 'Gross Weight' : 'Weight'; ?></label>
                <input type="text" class="form-control" name="stone_wt" value="<?php echo e($row['stone_wt']); ?>">
            </div>
            <div class="form-group">
                <label>Stone Name</label>
                <input type="text" class="form-control" name="stone_name" value="<?php echo e($row['stone_name']); ?>" list="edit_stone_name_master" autocomplete="off">
            </div>
            <div class="form-group">
                <label><?php echo $reportType === 'J' ? 'Shape' : 'Shape / Cut'; ?></label>
                <input type="text" class="form-control" name="shape_cut" value="<?php echo e($row['shape_cut']); ?>" list="edit_shape_cut_master" autocomplete="off">
            </div>
            <div class="form-group">
                <label>Dimension</label>
                <input type="text" class="form-control" name="dimension" value="<?php echo e($row['dimension']); ?>">
            </div>
            <div class="form-group">
                <label><?php echo $reportType === 'J' ? 'Color' : 'Colour'; ?></label>
                <input type="text" class="form-control" name="color" value="<?php echo e($row['color']); ?>" list="edit_colour_master" autocomplete="off">
            </div>
            <div class="form-group">
                <label>Optic Character</label>
                <input type="text" class="form-control" name="optic_char" value="<?php echo e($row['optic_char']); ?>">
            </div>
            <div class="form-group">
                <label>Hardness</label>
                <input type="text" class="form-control" name="hardness" value="<?php echo e($row['hardness']); ?>">
            </div>
            <div class="form-group">
                <label>Specific Gravity</label>
                <input type="text" class="form-control" name="spe_gravit" value="<?php echo e($row['spe_gravit']); ?>">
            </div>
            <div class="form-group">
                <label>Refractive Index</label>
                <input type="text" class="form-control" name="ref_index" value="<?php echo e($row['ref_index']); ?>" list="edit_ri_master" autocomplete="off">
            </div>
            <div class="form-group">
                <label>Magnification</label>
                <input type="text" class="form-control" name="magni" value="<?php echo e($row['magni']); ?>" list="edit_magni_master" autocomplete="off">
            </div>
            <div class="form-group">
                <label>Species / Group</label>
                <input type="text" class="form-control" name="spe_group" value="<?php echo e($row['spe_group']); ?>">
            </div>
            <div class="form-group">
                <label>Origin</label>
                <input type="text" class="form-control" name="origin" value="<?php echo e($row['origin']); ?>">
            </div>
            <div class="form-group">
                <label>Issued To</label>
                <input type="text" class="form-control" name="issued_to" value="<?php echo e($row['issued_to']); ?>">
            </div>
            <?php if ($reportType === 'D'): ?>
            <div class="form-group"><label>Diamond Weight</label><input type="text" class="form-control" name="dia_wt" value="<?php echo e($row['dia_wt']); ?>"></div>
            <div class="form-group"><label>Clarity</label><input type="text" class="form-control" name="clarity" value="<?php echo e($row['clarity']); ?>"></div>
            <div class="form-group"><label>Finish</label><input type="text" class="form-control" name="finish" value="<?php echo e($row['finish']); ?>"></div>
            <div class="form-group"><label>Cut Grade</label><input type="text" class="form-control" name="cut" value="<?php echo e($row['cut']); ?>"></div>
            <div class="form-group"><label>Table</label><input type="text" class="form-control" name="table_value" value="<?php echo e($row['table']); ?>"></div>
            <div class="form-group"><label>Crown Height</label><input type="text" class="form-control" name="crown" value="<?php echo e($row['crown']); ?>"></div>
            <div class="form-group"><label>Girdle</label><input type="text" class="form-control" name="girdle" value="<?php echo e($row['girdle']); ?>"></div>
            <div class="form-group"><label>Pavilion Depth</label><input type="text" class="form-control" name="pav_depth" value="<?php echo e($row['pav_depth']); ?>"></div>
            <div class="form-group"><label>Table Depth</label><input type="text" class="form-control" name="tab_depth" value="<?php echo e($row['tab_depth']); ?>"></div>
            <div class="form-group"><label>Fluorescence</label><input type="text" class="form-control" name="flurance" value="<?php echo e($row['flurance']); ?>"></div>
            <?php elseif ($reportType === 'J'): ?>
            <div class="form-group full-span"><label>Description</label><textarea class="form-control" name="description" rows="3"><?php echo e($row['desc']); ?></textarea></div>
            <div class="form-group"><label>Diamond Weight</label><input type="text" class="form-control" name="dia_wt" value="<?php echo e($row['dia_wt']); ?>"></div>
            <div class="form-group"><label>Diamond Pcs</label><input type="text" class="form-control" name="stone_pcs" value="<?php echo e($row['stone_pcs']); ?>"></div>
            <div class="form-group"><label>Clarity</label><input type="text" class="form-control" name="clarity" value="<?php echo e($row['clarity']); ?>"></div>
            <div class="form-group"><label>Cut</label><input type="text" class="form-control" name="cut" value="<?php echo e($row['cut']); ?>"></div>
            <?php elseif ($reportType === 'R'): ?>
            <div class="form-group full-span"><label>Description</label><textarea class="form-control" name="description" rows="3"><?php echo e($row['desc']); ?></textarea></div>
            <div class="form-group"><label>Face</label><input type="text" class="form-control" name="faces" value="<?php echo e($row['faces']); ?>"></div>
            <div class="form-group full-span"><label>Test Carried Out</label><textarea class="form-control" name="test_carried_out" rows="3"><?php echo e($row['rem2']); ?></textarea></div>
            <div class="form-group full-span"><label>Remarks</label><textarea class="form-control" name="remarks" rows="3"><?php echo e($row['rem1']); ?></textarea></div>
            <?php endif; ?>
            <div class="form-group full-span">
                <label><?php echo $reportType === 'R' ? 'Specific Comments' : 'Comments'; ?></label>
                <textarea class="form-control" name="comment" rows="3"><?php echo e($row['comment']); ?></textarea>
            </div>
        </div>
        <div class="edit-form-actions">
            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Changes</button>
            <button type="button" class="btn btn-default" id="clear-edit-form">Clear</button>
        </div>
        <datalist id="edit_stone_name_master"><?php echo $stoneOptions; ?></datalist>
        <datalist id="edit_shape_cut_master"><?php echo $shapeOptions; ?></datalist>
        <datalist id="edit_colour_master"><?php echo $colourOptions; ?></datalist>
        <datalist id="edit_ri_master"><?php echo $riOptions; ?></datalist>
        <datalist id="edit_magni_master"><?php echo $magniOptions; ?></datalist>
    </form>
</div>
<?php
$html = ob_get_clean();
echo json_encode(['status' => 'success', 'html' => $html]);
?>
