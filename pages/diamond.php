<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'master_data_helper.php';
require_once 'atm_config.php';
include "assets/navbar.php";

$diamondUserId = auth_current_user_id();
cstone_report_type_master_ready($conn);
$diamondReportTypes = cstone_report_type_rows($conn, $diamondUserId, true, 'D');
@$conn->query("CREATE TABLE IF NOT EXISTS `sm_master_symbols` (`id` int(11) NOT NULL AUTO_INCREMENT, `user_id` int(11) NOT NULL DEFAULT 1, `symbol_name` varchar(120) NOT NULL, `active` tinyint(1) NOT NULL DEFAULT 1, PRIMARY KEY (`id`), KEY `idx_symbol_user_name` (`user_id`,`symbol_name`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$symbolCount = @$conn->query("SELECT COUNT(*) AS total FROM sm_master_symbols");
$symbolRow = $symbolCount ? $symbolCount->fetch_assoc() : null;
if ((int) ($symbolRow['total'] ?? 0) === 0) {
    $defaults = ['ABRADED CULET', 'ABRADED FACET-EDGE', 'BEARDED GIRDLE', 'BRUISE', 'BRUTING LINE', 'BURN MARK', 'CAVITY', 'CHIP EXTERNAL', 'CHIP INTERNAL', 'CLEAVAGE', 'CLOUDS', 'CRYSTAL', 'DARK INCLUSION', 'FEATHER', 'GRAIN CENTRE', 'GROUP OF PIN POINTS', 'KNOT', 'LASER DRILLING'];
    $stmt = $conn->prepare("INSERT INTO sm_master_symbols (user_id, symbol_name, active) VALUES (1, ?, 1)");
    if ($stmt) { foreach ($defaults as $symbol) { $stmt->bind_param('s', $symbol); $stmt->execute(); } $stmt->close(); }
}
$symbolRows = [];
$symbolResult = @$conn->query("SELECT symbol_name FROM sm_master_symbols WHERE " . master_scope_sql($diamondUserId) . " AND active = 1 ORDER BY symbol_name ASC, id ASC");
if ($symbolResult) {
    $seenSymbols = [];
    while ($row = $symbolResult->fetch_assoc()) {
        $key = strtolower(trim((string) $row['symbol_name']));
        if ($key !== '' && !isset($seenSymbols[$key])) { $seenSymbols[$key] = true; $symbolRows[] = $row; }
    }
}
?>
<link href="../css/cropper.min.css" rel="stylesheet">
<style>
.diamond-page{padding-bottom:24px}.diamond-head{border-bottom:1px solid #ececf1;margin-bottom:14px;padding-bottom:12px}.diamond-head h1{border:0;color:#171717;font-size:24px;font-weight:600;margin:0 0 4px;padding:0}.diamond-head p{color:#737373;font-size:13px;margin:0}.diamond-layout{align-items:start;display:grid;gap:10px;grid-template-columns:minmax(0,1fr) 340px}.diamond-main{display:flex;flex-direction:column;gap:8px;min-width:0}.diamond-card,.diamond-actions{background:#fff;border:1px solid #ececf1;border-radius:8px;overflow:hidden}.diamond-card-head{border-bottom:1px solid #ececf1;padding:8px 9px}.diamond-card-head h3{font-size:13px;font-weight:600;margin:0}.diamond-card-body{padding:9px}.diamond-grid{display:grid;gap:7px 8px;grid-template-columns:repeat(4,minmax(0,1fr))}.diamond-field label{color:#404040;display:block;font-size:11px;font-weight:500;margin-bottom:2px}.diamond-field .form-control{border-radius:6px;box-shadow:none;font-size:12px;height:28px;min-height:28px;padding:4px 7px}.diamond-field textarea.form-control{height:auto;min-height:46px;resize:vertical}.diamond-field .form-control:disabled{background:#f7f7f8;color:#737373;cursor:not-allowed}.diamond-field.has-error .form-control{border-color:#dc2626}.diamond-field.full{grid-column:1/-1}.diamond-field.span-2{grid-column:span 2}.diamond-actions{align-items:center;display:flex;flex-wrap:wrap;gap:10px;padding:10px 12px}.diamond-actions .btn{border-radius:8px;min-height:36px}.diamond-save{background:#171717;border-color:#171717;color:#fff}.diamond-side{display:flex;flex-direction:column;gap:8px;position:sticky;top:76px}.image-card{background:#fff;border:1px solid #ececf1;border-radius:8px;overflow:hidden}.image-card h4{border-bottom:1px solid #ececf1;font-size:12px;font-weight:600;margin:0;padding:8px 9px}.image-stage{align-items:center;background:#f7f7f8;display:flex;justify-content:center;min-height:118px;overflow:hidden}.image-stage img{height:auto!important;max-height:150px;object-fit:contain;width:100%!important}.image-empty{color:#a3a3a3;font-size:12px;text-align:center}.image-empty i{display:block;font-size:26px;margin-bottom:6px}.image-tools{display:grid;gap:7px;grid-template-columns:1fr 1fr 1fr;padding:8px}.image-tools .btn{border-radius:7px;font-size:12px;padding:5px}.symbol-list{border:1px solid #d4d4d4;border-radius:6px;max-height:190px;overflow:auto}.symbol-row{align-items:center;border-bottom:1px solid #eee;display:grid;gap:8px;grid-template-columns:minmax(0,1fr) 44px;padding:4px 7px}.symbol-row:last-child{border-bottom:0}.symbol-row label{font-size:12px;font-weight:500;margin:0}.symbol-row input{height:16px;margin:0}.test-grid{display:grid;gap:7px 12px;grid-template-columns:repeat(3,minmax(0,1fr))}.test-row{align-items:center;display:flex;gap:7px;font-size:12px;font-weight:500;margin:0}.test-row input{height:16px;margin:0;width:16px}.save-result{align-items:center;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;color:#166534;display:none;gap:8px;padding:9px 11px}@media(max-width:1050px){.diamond-layout{grid-template-columns:1fr}.diamond-side{position:relative;top:auto}}@media(max-width:700px){.diamond-grid,.test-grid{grid-template-columns:1fr}.diamond-field.span-2{grid-column:auto}.diamond-actions{align-items:stretch;flex-direction:column}.diamond-actions .btn{width:100%}}
</style>
<div id="page-wrapper"><div class="container-fluid diamond-page">
<div class="diamond-head"><h1><i class="fa fa-diamond"></i> Diamond Grading Feeding</h1><p>Enter agreement and certificate details, then feed diamond grading data.</p></div>
<form id="diamond_feed" autocomplete="off">
<input type="hidden" name="report_type" value="D"><input type="hidden" name="upload_token" id="upload_token"><input type="hidden" name="upload_token_proportion" id="upload_token_proportion"><input type="hidden" name="upload_token_clarity" id="upload_token_clarity"><input type="hidden" name="report_type_text" id="report_type_text"><input type="hidden" name="report_format" id="report_format" value="a4"><input type="hidden" name="weight" id="weight"><input type="hidden" name="diamond_symbols_json" id="diamond_symbols_json"><input type="hidden" name="symbol1" id="symbol1"><input type="hidden" name="symbol2" id="symbol2"><input type="hidden" name="symbol3" id="symbol3">
<div class="diamond-layout"><div class="diamond-main">
<div class="save-result" id="diamond_save_result"><i class="fa fa-check-circle"></i><span>Last saved: Certificate <strong id="diamond_saved_certificate"></strong> / Report <strong id="diamond_saved_report"></strong></span></div>
<section class="diamond-card"><div class="diamond-card-head"><h3>Report Details</h3></div><div class="diamond-card-body"><div class="diamond-grid">
<div class="diamond-field"><label for="agreement_no">Ag. No</label><input class="form-control" type="number" min="0" name="agreement_no" id="agreement_no"></div>
<div class="diamond-field"><label for="certi_display">Certi No</label><input class="form-control" type="number" min="1" name="certificate_no" id="certi_display"></div>
<div class="diamond-field"><label for="date">Date *</label><input class="form-control" type="date" name="date" id="date" required></div>
<div class="diamond-field"><label for="report_type_id">Report Type *</label><select class="form-control" name="report_type_id" id="report_type_id" required><option value="">Select report type</option><?php foreach ($diamondReportTypes as $row): ?><option value="<?php echo (int) $row['id']; ?>" data-format="<?php echo htmlspecialchars($row['report_format'] ?? 'a4'); ?>"><?php echo htmlspecialchars($row['report_name']); ?></option><?php endforeach; ?></select></div>
<div class="diamond-field span-2"><label for="description">Item Description</label><input class="form-control" name="description" id="description"></div>
<div class="diamond-field"><label for="dia_wt">Carat Weight *</label><input class="form-control" name="dia_wt" id="dia_wt" required></div>
<div class="diamond-field"><label for="stone_name">Stone Name</label><input class="form-control" name="stone_name" id="stone_name" value="Diamond" list="diamond_stone_name_master"></div>
</div></div></section>
<section class="diamond-card"><div class="diamond-card-head"><h3>Grading Details</h3></div><div class="diamond-card-body"><div class="diamond-grid">
<div class="diamond-field span-2"><label for="shape_cut">Shape And Cut *</label><input class="form-control" name="shape_cut" id="shape_cut" list="diamond_shape_cut_master" required></div>
<div class="diamond-field span-2"><label for="dimension">Measurement *</label><input class="form-control" name="dimension" id="dimension" required></div>
<div class="diamond-field"><label for="cut">Cut</label><input class="form-control" name="cut" id="cut"></div>
<div class="diamond-field"><label for="symmetry">Symmetry</label><input class="form-control" name="symmetry" id="symmetry"></div>
<div class="diamond-field"><label for="finish">Polish</label><input class="form-control" name="finish" id="finish"></div>
<div class="diamond-field"><label for="colour">Color *</label><input class="form-control" name="colour" id="colour" list="diamond_colour_master" required></div>
<div class="diamond-field"><label for="table_value">Table Size</label><input class="form-control" name="table_value" id="table_value"></div>
<div class="diamond-field"><label for="crown">Crown Height</label><input class="form-control" name="crown" id="crown"></div>
<div class="diamond-field"><label for="pav_depth">Pavilion Depth</label><input class="form-control" name="pav_depth" id="pav_depth"></div>
<div class="diamond-field"><label for="girdle">Girdle Thickness</label><input class="form-control" name="girdle" id="girdle"></div>
<div class="diamond-field"><label for="culet">Culet</label><input class="form-control" name="culet" id="culet"></div>
<div class="diamond-field"><label for="flurance">Fluorescence</label><input class="form-control" name="flurance" id="flurance"></div>
<div class="diamond-field"><label for="clarity">Clarity</label><input class="form-control" name="clarity" id="clarity"></div>
<div class="diamond-field full"><label for="comments">General Comments</label><textarea class="form-control" rows="3" name="comments" id="comments"></textarea></div>
</div></div></section>
<section class="diamond-card"><div class="diamond-card-head"><h3>Symbols</h3></div><div class="diamond-card-body"><div class="symbol-list" id="symbol_list"><?php foreach ($symbolRows as $row): $symbol = (string) $row['symbol_name']; ?><div class="symbol-row"><label><?php echo htmlspecialchars($symbol); ?></label><input type="checkbox" class="diamond-symbol" value="<?php echo htmlspecialchars($symbol); ?>"></div><?php endforeach; ?></div><small class="help-block">Select maximum 3 symbols. Place symbol image files in <code>user_data/[branch]/symbol_images</code> or <code>assets/symbol_images</code>.</small></div></section>
<section class="diamond-card"><div class="diamond-card-head"><h3>Test Carried Out</h3></div><div class="diamond-card-body"><div class="test-grid">
<label class="test-row"><input type="checkbox" class="diamond-test" name="tri" id="tri" value="1"> RI</label>
<label class="test-row"><input type="checkbox" class="diamond-test" name="tsg" id="tsg" value="1"> SG</label>
<label class="test-row"><input type="checkbox" class="diamond-test" name="tmag" id="tmag" value="1"> Magnification</label>
<label class="test-row"><input type="checkbox" class="diamond-test" name="tuvf" id="tuvf" value="1"> UV Fluorescence</label>
<label class="test-row"><input type="checkbox" class="diamond-test" name="tabs" id="tabs" value="1"> ABS Spectrum</label>
<label class="test-row"><input type="checkbox" class="diamond-test" name="tirs" id="tirs" value="1"> IR Spectrum</label>
<label class="test-row"><input type="checkbox" class="diamond-test" name="tedxrf" id="tedxrf" value="1"> EDXRF</label>
<label class="test-row"><input type="checkbox" class="diamond-test" name="tlrs" id="tlrs" value="1"> LRS</label>
<label class="test-row"><input type="checkbox" class="diamond-test" name="tuvnir" id="tuvnir" value="1"> UV-VIS-NIR</label>
<label class="test-row"><input type="checkbox" class="diamond-test" name="tlaicpms" id="tlaicpms" value="1"> LA-ICPMS</label>
<label class="test-row"><input type="checkbox" class="diamond-test" name="txray" id="txray" value="1"> X-Radiography</label>
<label class="test-row"><input type="checkbox" class="diamond-test" name="tuvimg" id="tuvimg" value="1"> UV-Imaging</label>
</div></div></section>
<div class="diamond-actions"><button type="submit" class="btn diamond-save" id="diamond_submit"><i class="fa fa-check"></i> Submit Form</button><button type="reset" class="btn btn-default"><i class="fa fa-refresh"></i> Reset</button></div>
</div><aside class="diamond-side">
<?php foreach ([['stone','Stone Image'], ['proportion','Proportion Image'], ['clarity','Clarity Image']] as $imageBox): ?>
<div class="image-card" data-image-type="<?php echo $imageBox[0]; ?>"><h4><?php echo $imageBox[1]; ?></h4><div class="image-stage"><div class="image-empty" id="<?php echo $imageBox[0]; ?>_image_empty"><i class="fa fa-picture-o"></i>No image selected</div><div id="<?php echo $imageBox[0]; ?>_image_preview"></div></div><div class="image-tools"><button class="btn btn-danger image-fetch" type="button" data-type="<?php echo $imageBox[0]; ?>"><i class="fa fa-download"></i></button><button class="btn btn-default image-upload-button" type="button" data-type="<?php echo $imageBox[0]; ?>"><i class="fa fa-folder-open-o"></i></button><button class="btn btn-default image-camera-button" type="button" data-type="<?php echo $imageBox[0]; ?>"><i class="fa fa-camera"></i></button></div><input type="file" accept="image/jpeg,image/png" id="<?php echo $imageBox[0]; ?>_upload_image" style="display:none"></div>
<?php endforeach; ?>
</aside></div></form></div></div>
<datalist id="diamond_stone_name_master"><?php foreach (master_fetch_rows($conn, 'sm_master_stone_name', ['stone_name'], auth_current_user_id(), ['stone_name'], 'stone_name') as $row) { echo '<option value="' . htmlspecialchars($row['stone_name']) . '">'; } ?></datalist>
<datalist id="diamond_shape_cut_master"><?php foreach (master_fetch_rows($conn, 'sm_master_shape_cut', ['shape_cut'], auth_current_user_id(), ['shape_cut'], 'shape_cut') as $row) { echo '<option value="' . htmlspecialchars($row['shape_cut']) . '">'; } ?></datalist>
<datalist id="diamond_colour_master"><?php foreach (master_fetch_rows($conn, 'sm_master_colour', ['colour'], auth_current_user_id(), ['colour'], 'colour') as $row) { echo '<option value="' . htmlspecialchars($row['colour']) . '">'; } ?></datalist>
<div id="diamond_camera_modal" class="modal modern-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4>Get Frame and Snap Image</h4></div><div class="modal-body"><label>Select Camera</label><select id="diamond_camera_select" class="form-control"><option>Detecting cameras...</option></select><br><video id="diamond_video" autoplay playsinline style="width:100%;background:#111"></video></div><div class="modal-footer"><button class="btn btn-primary" type="button" id="diamond_capture">Crop Image</button></div></div></div></div>
<div id="diamond_crop_modal" class="modal modern-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button class="close" data-dismiss="modal">&times;</button><h4>Crop and Save Image</h4></div><div class="modal-body"><img id="diamond_crop_image" alt="Captured diamond" style="display:block;max-width:100%;width:100%"></div><div class="modal-footer"><button class="btn btn-primary" type="button" id="diamond_crop_save">Crop Image</button></div></div></div></div>
<script src="../js/cropper.min.js"></script><script src="../js/diamond_form.js"></script>
<?php include "assets/footer.php"; ?>
