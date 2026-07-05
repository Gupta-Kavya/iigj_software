<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'master_data_helper.php';
include "assets/navbar.php";

$userId = auth_current_user_id();

function cstone_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function cstone_treatment_master_ready($conn)
{
    @$conn->query("CREATE TABLE IF NOT EXISTS `sm_master_treatment_comment` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL DEFAULT 1,
        `comment_title` varchar(120) NOT NULL,
        `description` varchar(500) DEFAULT NULL,
        `active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_treatment_comment_user` (`user_id`, `active`, `comment_title`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function cstone_treatment_rows($conn, $userId)
{
    cstone_treatment_master_ready($conn);
    $rows = [];
    $sql = "SELECT id, user_id, comment_title, description FROM sm_master_treatment_comment
            WHERE " . master_scope_sql($userId) . " AND active = 1
            ORDER BY CASE WHEN user_id = " . (int) $userId . " THEN 0 ELSE 1 END, comment_title, id";
    $result = @$conn->query($sql);
    if (!$result) {
        return $rows;
    }
    $seen = [];
    while ($row = $result->fetch_assoc()) {
        $key = strtolower(trim((string) $row['comment_title']));
        if ($key !== '' && isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $rows[] = $row;
    }
    return $rows;
}

$stoneRows = master_fetch_rows($conn, 'sm_master_stone_name', ['stone_name', 'group'], $userId, ['stone_name'], 'stone_name');
$shapeRows = master_fetch_rows($conn, 'sm_master_shape_cut', ['shape_cut'], $userId, ['shape_cut'], 'shape_cut');
$colourRows = master_fetch_rows($conn, 'sm_master_colour', ['colour'], $userId, ['colour'], 'colour');
$riRows = master_fetch_rows($conn, 'sm_master_ri', ['ri'], $userId, ['ri'], 'ri');
$magniRows = master_fetch_rows($conn, 'sm_master_magnification', ['magni'], $userId, ['magni'], 'magni');
$treatmentRows = cstone_treatment_rows($conn, $userId);
?>
<link href="../css/cropper.min.css" rel="stylesheet">
<style>
.cstone-page{padding-bottom:28px}.cstone-head{border-bottom:1px solid #dfe4ea;margin-bottom:14px;padding-bottom:12px}.cstone-head h1{border:0;color:#1f2933;font-size:22px;font-weight:600;margin:0 0 4px;padding:0}.cstone-head p{color:#667085;font-size:12px;margin:0}
.cstone-shell{background:#e8ebee;border:1px solid #9fb5c8;box-shadow:0 1px 2px rgba(15,23,42,.08);padding:10px}.cstone-form{display:grid;gap:8px;grid-template-columns:minmax(0,1fr) 430px}.cstone-left,.cstone-right{display:flex;flex-direction:column;gap:8px;min-width:0}
.cstone-row{align-items:center;display:grid;gap:6px 8px}.cstone-row.top{grid-template-columns:110px 120px 62px 110px 56px 100px}.cstone-row.item{align-items:start;grid-template-columns:110px minmax(0,1fr)}.cstone-row.weight{grid-template-columns:110px 94px 34px 86px minmax(0,1fr)}.cstone-row.two{grid-template-columns:110px minmax(0,1fr)}.cstone-row.tested{grid-template-columns:110px 86px 70px minmax(0,1fr)}.cstone-row.stone-line{grid-template-columns:110px 18px 86px 34px 88px minmax(0,1fr)}.cstone-row.radio-row{grid-template-columns:110px minmax(0,1fr)}.cstone-label{color:#111827;font-size:12px;font-weight:500;line-height:1.15;margin:0;text-transform:uppercase}.cstone-red{color:#ff0000}.cstone-form .form-control{border:1px solid #aab7c4;border-radius:0;box-shadow:none;font-size:12px;height:25px;padding:2px 6px}.cstone-form textarea.form-control{height:auto;min-height:58px;resize:vertical}.cstone-form select.form-control{padding:2px 5px}.cstone-field.has-error .form-control,.cstone-checks.has-error{border-color:#dc2626}
.cstone-title-box{align-self:center;background:#fff;border:1px solid #9da6af;color:#f00;font-size:18px;font-weight:700;letter-spacing:.5px;margin:2px 0 8px;padding:7px 12px;text-align:center;width:160px}.treatment-head{color:#f00;font-size:12px;text-align:center;text-transform:uppercase}.treatment-select{display:grid;gap:4px;grid-template-columns:250px minmax(0,1fr)}.cstone-comments{height:82px!important}.cstone-tests{border:1px solid #606b75;margin-top:4px;padding:8px 10px}.cstone-tests legend{border:0;color:#f00;font-size:12px;margin:0 0 5px;text-align:center;width:auto}.cstone-check-grid{display:grid;gap:6px 20px;grid-template-columns:1fr 1fr}.cstone-check{align-items:center;display:flex;font-size:12px;font-weight:600;gap:5px;margin:0}.cstone-check input{margin:0}
.image-box{align-items:center;background:#f7f7f8;border:1px solid #606b75;display:flex;height:166px;justify-content:center;margin-left:auto;overflow:hidden;position:relative;width:210px}.image-box:before,.image-box:after{background:#777;content:"";height:1px;left:8px;position:absolute;right:8px;top:50%;transform:rotate(38deg)}.image-box:after{transform:rotate(-38deg)}.image-box img{height:100%;object-fit:contain;position:relative;width:100%;z-index:2}.image-empty{color:#6b7280;font-size:12px;position:relative;z-index:1}.image-box.has-image:before,.image-box.has-image:after,.image-box.has-image .image-empty{display:none}.image-tools{display:grid;gap:7px;grid-template-columns:1fr 1fr;margin-left:auto;width:210px}.image-tools .btn,.cstone-actions .btn{border-radius:4px;font-size:12px;min-height:32px}.copy-row{display:grid;gap:7px;grid-template-columns:1fr auto;margin-left:auto;width:210px}.copy-row .form-control{height:32px}.save-result{align-items:center;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;color:#166534;display:none;gap:8px;margin-bottom:10px;padding:9px 11px}
.cstone-actions{align-items:center;display:flex;flex-wrap:wrap;gap:10px;margin-top:10px}.cstone-save{background:#171717;border-color:#171717;color:#fff}.cstone-save:hover,.cstone-save:focus{background:#404040;color:#fff}.camera-select-wrap{margin-bottom:12px}
@media(max-width:1160px){.cstone-form{grid-template-columns:1fr}.cstone-right{max-width:none}.image-box,.image-tools,.copy-row{margin-left:0;width:100%}}@media(max-width:760px){.cstone-row.top,.cstone-row.weight,.cstone-row.tested,.cstone-row.stone-line,.treatment-select,.cstone-check-grid{grid-template-columns:1fr}.cstone-label{margin-top:4px}.cstone-actions{align-items:stretch;flex-direction:column}.cstone-actions .btn{width:100%}}
</style>
<div id="page-wrapper"><div class="container-fluid cstone-page">
    <div class="cstone-head"><h1><i class="fa fa-diamond"></i> Colour Stone Feeding</h1><p>Testing detail entry mapped to Colour Stone fields in sm_form_data.</p></div>
    <div class="save-result" id="cstone_save_result"><i class="fa fa-check-circle"></i><span>Saved certificate <strong id="cstone_saved_certificate"></strong> / report <strong id="cstone_saved_report"></strong></span></div>
    <form id="cstone_feed" class="cstone-shell" autocomplete="off">
        <input type="hidden" name="report_type" value="S"><input type="hidden" name="upload_token" id="upload_token" value=""><input type="hidden" name="certi_no" id="certi_no" value=""><input type="hidden" name="test_carried_out" id="test_carried_out" value="">
        <div class="cstone-form">
            <div class="cstone-left">
                <div class="cstone-row top"><label class="cstone-label" for="agreement_no">Agreement No</label><input class="form-control" type="number" min="0" name="agreement_no" id="agreement_no"><label class="cstone-label" for="certi_display">Certi No</label><input class="form-control" type="text" id="certi_display" readonly value="Auto"><label class="cstone-label" for="date">Date</label><input class="form-control" type="date" name="date" id="date"></div>
                <div class="cstone-row item"><label class="cstone-label" for="item_desc">Item Desc.</label><textarea class="form-control" name="item_desc" id="item_desc" rows="2"></textarea></div>
                <div class="cstone-row weight"><label class="cstone-label" for="gross_weight">Gross Weight</label><input class="form-control" type="text" name="gross_weight" id="gross_weight"><label class="cstone-label" for="gross_unit">Unit</label><select class="form-control" name="gross_unit" id="gross_unit"><option>ct</option><option>gm</option><option>pcs</option></select><span></span></div>
                <div class="cstone-row two cstone-field" id="error_class_colour"><label class="cstone-label" for="colour">Colour</label><input class="form-control" type="text" name="colour" id="colour" list="colour_master" autocomplete="off"></div>
                <div class="cstone-row two cstone-field" id="error_class_shape_cut"><label class="cstone-label" for="shape_cut">Shape/Cut</label><input class="form-control" type="text" name="shape_cut" id="shape_cut" list="shape_cut_master" autocomplete="off"></div>
                <div class="cstone-row tested"><label class="cstone-label" for="stone_pcs">Tested Pcs</label><input class="form-control" type="number" min="0" name="stone_pcs" id="stone_pcs"><label class="cstone-label cstone-red" for="tested_pcs_remark">Remark For<br>Tested Pcs</label><input class="form-control" type="text" name="tested_pcs_remark" id="tested_pcs_remark"></div>
                <?php for ($i = 1; $i <= 5; $i++): ?><div class="cstone-row stone-line <?php echo $i === 1 ? 'cstone-field' : ''; ?>" id="<?php echo $i === 1 ? 'error_class_stone_weight' : ''; ?>"><label class="cstone-label" for="stone_weight_<?php echo $i; ?>">Stone Weight</label><span class="cstone-red"><?php echo $i; ?>.</span><input class="form-control stone-weight-input" type="text" name="stone_weight_<?php echo $i; ?>" id="stone_weight_<?php echo $i; ?>"><span>ct</span><label class="cstone-label" for="measurement_<?php echo $i; ?>">Measurement</label><input class="form-control" type="text" name="measurement_<?php echo $i; ?>" id="measurement_<?php echo $i; ?>"></div><?php endfor; ?>
                <div class="cstone-row two"><label class="cstone-label" for="length_tested">Length Tested</label><input class="form-control" type="text" name="length_tested" id="length_tested"></div>
                <div class="cstone-row two"><label class="cstone-label" for="ri">R.I.</label><input class="form-control" type="text" name="ri" id="ri" list="ri_master" autocomplete="off"></div>
                <div class="cstone-row two"><label class="cstone-label" for="specefic_grav">S.G.</label><input class="form-control" type="text" name="specefic_grav" id="specefic_grav"></div>
                <div class="cstone-row two"><label class="cstone-label" for="optic_char">Optic Char.</label><input class="form-control" type="text" name="optic_char" id="optic_char"></div>
                <div class="cstone-row radio-row"><span></span><div class="well well-sm" style="margin:0;padding:5px 8px"><label class="radio-inline"><input type="radio" name="species_mode" value="Species/Variety" checked> Species/Variety</label><label class="radio-inline"><input type="radio" name="species_mode" value="Others"> Others</label></div></div>
                <div class="cstone-row two cstone-field" id="error_class_variety"><label class="cstone-label" for="stone_name">Variety</label><input class="form-control" type="text" name="stone_name" id="stone_name" list="stone_name_master" autocomplete="off"></div>
                <div class="cstone-row two"><label class="cstone-label" for="species_grp">Group/Species</label><input class="form-control" type="text" name="species_grp" id="species_grp"></div>
                <div class="cstone-row two"><label class="cstone-label" for="comments">Comments</label><textarea class="form-control" rows="2" name="comments" id="comments"></textarea></div>
                <div class="cstone-row two"><label class="cstone-label" for="origin">Origin</label><input class="form-control" type="text" name="origin" id="origin"></div>
            </div>
            <div class="cstone-right">
                <div class="cstone-row" style="grid-template-columns:72px minmax(0,1fr)"><label class="cstone-label cstone-red" for="report_type_text">Report<br>Type</label><input class="form-control" type="text" name="report_type_text" id="report_type_text" value="COLOUR STONE"></div>
                <div class="treatment-head">Treatment Comments</div>
                <div class="treatment-select"><select class="form-control" name="treatment_comment_title" id="treatment_comment_title"><option value="">Select comment</option><?php foreach ($treatmentRows as $row): ?><option value="<?php echo cstone_h($row['comment_title']); ?>" data-description="<?php echo cstone_h($row['description'] ?? ''); ?>"><?php echo cstone_h($row['comment_title']); ?></option><?php endforeach; ?></select><input class="form-control" type="text" name="treatment_comment_desc" id="treatment_comment_desc"></div>
                <textarea class="form-control cstone-comments" name="treatment_long_comment" id="treatment_long_comment"></textarea>
                <div class="treatment-select"><select class="form-control" name="treatment_comment_title_2" id="treatment_comment_title_2"><option value="">Select comment</option><?php foreach ($treatmentRows as $row): ?><option value="<?php echo cstone_h($row['comment_title']); ?>" data-description="<?php echo cstone_h($row['description'] ?? ''); ?>"><?php echo cstone_h($row['comment_title']); ?></option><?php endforeach; ?></select><input class="form-control" type="text" name="treatment_comment_desc_2" id="treatment_comment_desc_2"></div>
                <textarea class="form-control cstone-comments" name="treatment_long_comment_2" id="treatment_long_comment_2"></textarea>
                <div class="cstone-title-box">COLOUR STONE</div>
                <fieldset class="cstone-tests"><legend>Test Carried Out</legend><div class="cstone-check-grid">
                    <label class="cstone-check"><input type="checkbox" name="tests[]" value="RI" data-column="tri"> RI</label><label class="cstone-check"><input type="checkbox" name="tests[]" value="EDXRF" data-column="tedxrf"> EDXRF</label>
                    <label class="cstone-check"><input type="checkbox" name="tests[]" value="SG" data-column="tsg"> S G</label><label class="cstone-check"><input type="checkbox" name="tests[]" value="LRS" data-column="tlrs"> LRS</label>
                    <label class="cstone-check"><input type="checkbox" name="tests[]" value="MAGNIFICATION" data-column="tmag"> MAGNIFICATION</label><label class="cstone-check"><input type="checkbox" name="tests[]" value="UV-VIS-NIR" data-column="tuvnir"> UV-VIS-NIR</label>
                    <label class="cstone-check"><input type="checkbox" name="tests[]" value="UV FLUORESCENCE" data-column="tuvf"> UV FLUORESCENCE</label><label class="cstone-check"><input type="checkbox" name="tests[]" value="LA-ICPMS" data-column="tlaicpms"> LA-ICPMS</label>
                    <label class="cstone-check"><input type="checkbox" name="tests[]" value="ABS SPECTRUM" data-column="tabs"> ABS SPECTRUM</label><label class="cstone-check"><input type="checkbox" name="tests[]" value="X-RADIOGRAPHY" data-column="txray"> X-RADIOGRAPHY</label>
                    <label class="cstone-check"><input type="checkbox" name="tests[]" value="IR SPECTRUM" data-column="tirs"> IR SPECTRUM</label><label class="cstone-check"><input type="checkbox" name="tests[]" value="UV-IMAGING" data-column="tuvimg"> UV-IMAGING</label>
                </div></fieldset>
                <div class="image-box" id="cstone_image_box"><div class="image-empty">Stone Image</div><div id="fetched_image"></div></div>
                <div class="cstone-row" style="grid-template-columns:78px minmax(0,1fr);margin-left:auto;width:210px"><label class="cstone-label cstone-red" for="ebay_prod_no">Ebay Prod No.</label><input class="form-control" type="text" name="ebay_prod_no" id="ebay_prod_no"></div>
                <div class="image-tools"><button class="btn btn-default" type="button" data-toggle="modal" data-target="#cstone_folder_modal"><i class="fa fa-folder-open-o"></i> Upload</button><button class="btn btn-default" type="button" data-toggle="modal" data-target="#cstone_camera_modal"><i class="fa fa-camera"></i> Camera</button></div>
                <div class="copy-row" id="cstone_copy_block"><input class="form-control" type="number" min="1" id="cstone_copy_certificate" placeholder="Copy certificate no"><button class="btn btn-primary" type="button" id="cstone_copy_button">Copy</button></div>
            </div>
        </div>
        <div class="cstone-actions"><button class="btn cstone-save" type="submit" id="cstone_submit"><i class="fa fa-check"></i> Submit Form</button><button class="btn btn-default" type="reset"><i class="fa fa-refresh"></i> Reset</button><button class="btn btn-danger" type="button" id="cstone_fetch_image"><i class="fa fa-download"></i> Fetch Image</button></div>
    </form>
</div></div>
<datalist id="stone_name_master"><?php foreach ($stoneRows as $row) { echo '<option value="' . cstone_h($row['stone_name']) . '">'; } ?></datalist>
<datalist id="shape_cut_master"><?php foreach ($shapeRows as $row) { echo '<option value="' . cstone_h($row['shape_cut']) . '">'; } ?></datalist>
<datalist id="colour_master"><?php foreach ($colourRows as $row) { echo '<option value="' . cstone_h($row['colour']) . '">'; } ?></datalist>
<datalist id="ri_master"><?php foreach ($riRows as $row) { echo '<option value="' . cstone_h($row['ri']) . '">'; } ?></datalist>
<datalist id="magni_master"><?php foreach ($magniRows as $row) { echo '<option value="' . cstone_h($row['magni']) . '">'; } ?></datalist>
<div id="cstone_folder_modal" class="modal modern-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Select Image</h4></div><div class="modal-body"><input type="file" id="cstone_upload_image" accept="image/jpeg,image/png" class="form-control"><span class="help-block">Select a JPG or PNG stone image.</span></div><div class="modal-footer"><button type="button" class="btn btn-primary" data-dismiss="modal">Close</button></div></div></div></div>
<div id="cstone_camera_modal" class="modal modern-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Get Frame and Snap Image</h4></div><div class="modal-body"><div class="camera-select-wrap"><label for="cstone_camera_select">Select Camera</label><select id="cstone_camera_select" class="form-control"><option value="">Detecting cameras...</option></select></div><video id="cstone_video" autoplay playsinline style="width:100%;background:#111"></video></div><div class="modal-footer"><button type="button" class="btn btn-primary" id="cstone_capture">Crop Image</button><button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div></div></div></div>
<div id="cstone_crop_modal" class="modal modern-modal" role="dialog"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Crop and Save Image</h4></div><div class="modal-body"><img id="cstone_crop_image" alt="Captured stone" style="display:block;max-width:100%;width:100%"></div><div class="modal-footer"><button type="button" class="btn btn-primary" id="cstone_crop_save">Crop Image</button><button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div></div></div></div>
<script src="../js/cropper.min.js"></script><script src="../js/cstone_form.js"></script>
<?php $conn->close(); include "assets/footer.php"; ?>
