<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'a4_config.php';

$builderType = atm_report_type($_GET['type'] ?? 'S');
$builderAllowedTypes = array_keys(atm_report_type_labels($conn));
$settings = a4_read_settings($builderType);
$builderFieldKeys = atm_builder_field_keys($builderType);
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['background_image'])) {
    if ($_FILES['background_image']['error'] === UPLOAD_ERR_OK) {
        $info = @getimagesize($_FILES['background_image']['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        if ($info && isset($extensions[$info['mime']])) {
            $extension = $extensions[$info['mime']];
            if (preg_match('/^CS([0-9]+)$/', $builderType, $match)) {
                $backgroundName = 'a4-background-colour-stone-type-' . (int) $match[1] . '.';
            } else {
                $backgroundName = $builderType === 'D' ? 'a4-background-diamond.' : ($builderType === 'J' ? 'a4-background-jewellery.' : ($builderType === 'R' ? 'a4-background-rudraksha.' : 'a4-background.'));
            }
            $relativePath = atm_user_asset_relative($backgroundName . $extension);
            $targetPath = __DIR__ . '/' . $relativePath;
            if (!is_dir(dirname($targetPath))) @mkdir(dirname($targetPath), 0775, true);
            if (move_uploaded_file($_FILES['background_image']['tmp_name'], $targetPath)) {
                $settings['backgroundImage'] = $relativePath;
                file_put_contents(a4_settings_file($builderType), json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
                $message = 'A4 background image saved.';
            } else {
                $message = 'Unable to save uploaded image.';
                $messageType = 'danger';
            }
        } else {
            $message = 'Please upload a valid JPG or PNG image.';
            $messageType = 'danger';
        }
    }
}

include "assets/navbar.php";
$bgUrl = a4_background_url($settings);
$bgStyle = $bgUrl !== '' ? "background-image:url('" . $bgUrl . "');" : '';
$fontOptions = a4_font_options();
?>
<link rel="stylesheet" href="../css/jquery-ui.min.css">
<style>
@font-face{font-family:'Arial Nova Cond Light';src:url('font-proxy.php?font=arialnova') format('truetype');font-weight:300;font-style:normal;font-display:swap}
@font-face{font-family:'Arial Nova Cond Light';src:url('font-proxy.php?font=arialnova') format('truetype');font-weight:400;font-style:normal;font-display:swap}
@font-face{font-family:'Arial Nova Cond Light';src:url('font-proxy.php?font=arialnova') format('truetype');font-weight:700;font-style:normal;font-display:swap}
<?php echo a4_font_face_css(); ?>
.a4-builder-page .builder-shell { display:grid; grid-template-columns: minmax(0, 1fr) 360px; gap:20px; align-items:start; }
.a4-preview-card { position: sticky; top: 84px; z-index: 5; min-width:0; }
.a4-preview-card,.a4-tools-card{background:#fff;border:1px solid #ececf1;border-radius:10px;overflow:hidden}
.a4-card-head{padding:16px 18px;border-bottom:1px solid #ececf1}
.a4-card-head h3{margin:0;font-size:16px;font-weight:600;color:#171717}.a4-card-head p{margin:4px 0 0;color:#737373;font-size:13px}
.a4-preview-wrap{padding:12px;overflow:auto;background:#f7f7f8;max-width:100%;max-height:calc(100vh - 155px);box-sizing:border-box}
#a4Canvas{width:842px;height:595px;position:relative;<?php echo $bgStyle; ?>background-color:#fff;background-position:center center;background-size:cover;background-repeat:no-repeat;border:1px solid #e5e5e5;border-radius:8px;box-shadow:none;transform-origin:top left}
.a4-draggable{position:absolute;border:0;outline:1px dashed #737373;background:rgba(255,255,255,.45);border-radius:6px;overflow:hidden;box-sizing:border-box;cursor:move;padding:3px 6px;color:inherit}
.a4-field{padding:0;border-radius:2px}
.a4-field.a4-tick-field{overflow:visible;background:rgba(255,255,255,.2);padding:0;min-width:6px;min-height:6px}
.a4-draggable.a4-selected{outline-color:#2563eb;background:rgba(219,234,254,.58)}
.a4-draggable.a4-group-selected{outline-color:#16a34a;background:rgba(220,252,231,.62)}
.a4-draggable.a4-selected.a4-group-selected{outline-color:#2563eb;background:rgba(219,234,254,.72)}
.a4-draggable.hidden-item{opacity:.25}.a4-label{font-weight:300;display:inline-block;vertical-align:top}.a4-value{display:inline-block;font-weight:300;vertical-align:top;white-space:normal;overflow-wrap:anywhere}
#a4Stone img,.a4-extra-image img{width:100%;height:100%;object-fit:contain;display:block}.qr-placeholder{width:100%;height:100%;background:repeating-linear-gradient(45deg,#111 0,#111 4px,#fff 4px,#fff 8px);display:flex;align-items:center;justify-content:center}.qr-placeholder span{background:#fff;padding:3px 6px;font-weight:600}
.a4-extra-image.a4-extra-selected{outline-color:#dc2626;background:rgba(254,226,226,.55)}
.a4-tools-card{max-height:none;overflow:visible;padding:18px}.tool-section{border-bottom:1px solid #ececf1;padding-bottom:14px;margin-bottom:14px}.tool-section:last-child{border-bottom:0;margin-bottom:0}
.a4-tools-card::-webkit-scrollbar{width:8px}.a4-tools-card::-webkit-scrollbar-track{background:#f1f5f9;border-radius:999px}.a4-tools-card::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:999px}.a4-tools-card::-webkit-scrollbar-thumb:hover{background:#94a3b8}
.tool-section h4{font-size:13px;font-weight:600;text-transform:none;color:#737373;margin:0 0 10px}.control-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.control-grid label,.field-list label{font-size:12px;color:#404040;font-weight:500;margin-bottom:4px}.field-list{max-height:260px;overflow:auto;border:1px solid #ececf1;border-radius:8px;padding:10px;background:#f7f7f8}.field-row{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px}.field-row select{width:92px}
.builder-help{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;color:#64748b;font-size:12px;line-height:1.45;margin:0 0 10px;padding:9px 10px}
.mini-actions{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px}.mini-actions.three{grid-template-columns:repeat(3,1fr)}
.btn-mini{border-radius:8px;font-size:12px;min-height:36px;padding:7px 9px}
.selected-count{background:#eef2ff;border-radius:999px;color:#3730a3;display:inline-block;font-size:12px;font-weight:600;margin-left:6px;padding:2px 8px}
.save-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}.btn-wide{width:100%;min-height:42px;border-radius:8px;font-weight:500}
@media(max-width:900px){.a4-builder-page .builder-shell{grid-template-columns:1fr}.a4-preview-card{position:relative;top:auto}.a4-tools-card{max-height:none;overflow:visible}}
</style>

<div id="page-wrapper"><div class="container-fluid a4-builder-page">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><i class="fa fa-file-pdf-o"></i> A4 Report Builder</h1></div></div>
    <div style="max-width:300px;margin:-12px 0 16px;"><label for="a4BuilderType" style="font-size:12px;color:#525252;">Certificate design</label><select id="a4BuilderType" class="form-control"><?php foreach (atm_report_type_labels($conn) as $typeCode => $typeLabel): ?><?php if (!in_array($typeCode, $builderAllowedTypes, true)) continue; ?><option value="<?php echo htmlspecialchars($typeCode); ?>" <?php echo $builderType === $typeCode ? 'selected' : ''; ?>><?php echo htmlspecialchars($typeLabel); ?></option><?php endforeach; ?></select></div>
    <ul class="nav nav-tabs settings-tabs">
        <li><a href="settings.php?type=<?php echo $builderType; ?>">ATM Card Builder</a></li>
        <li><a href="backPrintSettings.php">ATM Images &amp; Print</a></li>
        <li class="active"><a href="a4Settings.php?type=<?php echo $builderType; ?>">A4 Builder</a></li>
        <li><a href="postcardSettings.php?type=<?php echo $builderType; ?>">Postcard Builder</a></li>
    </ul>
    <?php if ($message): ?><div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <div class="builder-shell">
        <div class="a4-preview-card">
            <div class="a4-card-head"><h3>Live A4 Layout</h3><p>Drag and resize fields, stone image and QR code. Save when finished.</p></div>
            <div class="a4-preview-wrap">
                <div id="a4Canvas">
                    <?php foreach ($settings['fields'] as $key => $field): if (!in_array($key, $builderFieldKeys, true)) continue; ?>
                        <div class="a4-draggable a4-field <?php echo (($field['valueType'] ?? '') === 'tick') ? 'a4-tick-field' : ''; ?>" id="field_<?php echo htmlspecialchars($key); ?>" data-key="<?php echo htmlspecialchars($key); ?>">
                            <span class="a4-label"><?php echo htmlspecialchars($field['label']); ?></span>
                            <span class="a4-value"><?php echo (($field['valueType'] ?? '') === 'tick') ? '&#10003;' : ': Sample'; ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="a4-draggable" id="a4Stone"><img src="assets/st_images/2.jpg" alt="Stone"></div>
                    <div class="a4-draggable" id="a4Qr"><div class="qr-placeholder"><span>QR</span></div></div>
                    <?php foreach (($settings['additionalImages'] ?? []) as $extraImage): $extraPath = __DIR__ . '/' . ($extraImage['src'] ?? ''); $extraSrc = is_file($extraPath) ? ($extraImage['src'] . '?v=' . filemtime($extraPath)) : ($extraImage['src'] ?? ''); ?>
                        <div class="a4-draggable a4-extra-image" data-id="<?php echo htmlspecialchars($extraImage['id'] ?? ''); ?>">
                            <img src="<?php echo htmlspecialchars($extraSrc); ?>" alt="<?php echo htmlspecialchars($extraImage['label'] ?? 'Extra Image'); ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="a4-tools-card">
            <div class="tool-section">
                <h4>Background Image</h4>
                <div class="control-grid" style="margin-bottom:10px;">
                    <div style="grid-column:1 / -1;">
                        <label for="a4Orientation">Page Orientation</label>
                        <select id="a4Orientation" class="form-control">
                            <option value="landscape">Landscape</option>
                            <option value="portrait">Portrait</option>
                        </select>
                    </div>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <input type="file" name="background_image" accept="image/jpeg,image/png" class="form-control">
                    <button class="btn btn-primary btn-wide" style="margin-top:10px;">Upload Background</button>
                </form>
            </div>
            <div class="tool-section">
                <h4>Text Style</h4>
                <div class="control-grid">
                    <div><label>Font</label><select id="fontFamily" class="form-control"><?php foreach ($fontOptions as $fontOption): ?><option value="<?php echo htmlspecialchars($fontOption['family']); ?>"><?php echo htmlspecialchars($fontOption['label']); ?></option><?php endforeach; ?></select></div>
                    <div><label>Font Size</label><input type="number" id="fontSize" class="form-control" min="8" max="40"></div>
                    <div><label>Font Color</label><input type="color" id="fontColor" class="form-control"></div>
                    <div><label>Label Width</label><input type="number" id="labelWidth" class="form-control" min="50" max="350"></div>
                    <div style="grid-column:1 / -1;"><label>Upload TTF Font</label><input type="file" id="fontUpload" accept=".ttf,font/ttf" class="form-control"></div>
                    <div style="grid-column:1 / -1;"><button type="button" id="uploadFont" class="btn btn-default btn-mini">Add Font</button></div>
                </div>
            </div>
            <div class="tool-section">
                <h4>Fields</h4>
                <div class="field-list">
                    <?php foreach ($settings['fields'] as $key => $field): if (!in_array($key, $builderFieldKeys, true)) continue; ?>
                        <div class="field-row">
                            <label><?php echo htmlspecialchars($field['label']); ?></label>
                            <select class="form-control field-display" data-key="<?php echo htmlspecialchars($key); ?>">
                                <option value="block">Show</option>
                                <option value="none">Hide</option>
                            </select>
                        </div>
                    <?php endforeach; ?>
                    <div class="field-row"><label>Stone Image</label><select class="form-control" id="stoneDisplay"><option value="block">Show</option><option value="none">Hide</option></select></div>
                    <div class="field-row"><label>QR Code</label><select class="form-control" id="qrDisplay"><option value="block">Show</option><option value="none">Hide</option></select></div>
                </div>
            </div>
            <div class="tool-section">
                <h4>Additional Images</h4>
                <p class="builder-help">Upload any logo, stamp or extra image, then drag and resize it on the report.</p>
                <input type="file" id="extraImageUpload" accept="image/jpeg,image/png" class="form-control">
                <div class="mini-actions">
                    <button type="button" id="uploadExtraImage" class="btn btn-default btn-mini">Add Image</button>
                    <button type="button" id="deleteExtraImage" class="btn btn-default btn-mini">Delete Selected</button>
                </div>
                <div id="extraImagesList" class="field-list" style="margin-top:10px;max-height:130px;"></div>
            </div>
            <div class="tool-section">
                <h4>Selected Field Style</h4>
                <div class="control-grid">
                    <div style="grid-column:1 / -1;"><label>Label Name</label><input type="text" id="a4FieldLabelText" class="form-control"></div>
                    <div style="grid-column:1 / -1;"><label>Field Font</label><select id="a4FieldFontFamily" class="form-control"><option value="">Use Global Font</option><?php foreach ($fontOptions as $fontOption): ?><option value="<?php echo htmlspecialchars($fontOption['family']); ?>"><?php echo htmlspecialchars($fontOption['label']); ?></option><?php endforeach; ?></select></div>
                    <div><label>Label Display</label><select id="a4FieldShowLabel" class="form-control"><option value="block">Show</option><option value="none">Hide</option></select></div>
                    <div><label>Colon Display</label><select id="a4FieldShowColon" class="form-control"><option value="block">Show</option><option value="none">Hide</option></select></div>
                    <div><label>Label Weight</label><select id="a4FieldLabelFontWeight" class="form-control"><option value="normal">Regular</option><option value="bold">Bold</option></select></div>
                    <div><label>Value Weight</label><select id="a4FieldFontWeight" class="form-control"><option value="normal">Regular</option><option value="bold">Bold</option></select></div>
                    <div><label>Font Size</label><input type="number" id="a4FieldFontSize" class="form-control" min="8" max="40" placeholder="Default"></div>
                    <div><label>Font Color</label><input type="color" id="a4FieldFontColor" class="form-control" value="#000000"></div>
                    <div><label>Label Width</label><input type="number" id="a4FieldLabelWidth" class="form-control" min="0" max="350" placeholder="Default"></div>
                    <div><label>Label Align</label><select id="a4FieldLabelAlign" class="form-control"><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select></div>
                    <div><label>Value Align</label><select id="a4FieldValueAlign" class="form-control"><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select></div>
                </div>
                <button type="button" id="clearA4FieldStyle" class="btn btn-default btn-wide" style="margin-top:10px;">Use Global Text Style</button>
            </div>
            <div class="tool-section">
                <h4>Manual Position <span class="selected-count" id="a4SelectedCount">1 selected</span></h4>
                <p class="builder-help">Click a field to edit exact position. Ctrl/Shift-click multiple fields, then drag any selected field or use nudge to move them together.</p>
                <div class="control-grid">
                    <div><label>X Axis</label><input type="number" id="a4FieldX" class="form-control" step="1"></div>
                    <div><label>Y Axis</label><input type="number" id="a4FieldY" class="form-control" step="1"></div>
                    <div><label>Width</label><input type="number" id="a4FieldW" class="form-control" min="1" step="1"></div>
                    <div><label>Height</label><input type="number" id="a4FieldH" class="form-control" min="1" step="1"></div>
                </div>
                <button type="button" id="applyA4FieldPosition" class="btn btn-primary btn-wide" style="margin-top:10px;">Apply Position to Active Field</button>
                <div class="control-grid" style="margin-top:10px;">
                    <div><label>Move X By</label><input type="number" id="a4NudgeX" class="form-control" step="1" value="0"></div>
                    <div><label>Move Y By</label><input type="number" id="a4NudgeY" class="form-control" step="1" value="0"></div>
                </div>
                <div class="mini-actions">
                    <button type="button" id="nudgeA4Selected" class="btn btn-default btn-mini">Move Selected</button>
                    <button type="button" id="selectAllA4Fields" class="btn btn-default btn-mini">Select Visible Fields</button>
                </div>
                <div class="mini-actions">
                    <button type="button" id="clearA4Selection" class="btn btn-default btn-mini">Clear Multi Select</button>
                    <button type="button" id="alignA4SelectedLeft" class="btn btn-default btn-mini">Align Left</button>
                </div>
            </div>
            <div class="save-row">
                <button type="button" id="saveA4Settings" class="btn btn-primary btn-wide">Save A4 Layout</button>
                <button type="button" id="resetA4Defaults" class="btn btn-default btn-wide">Reset Preview</button>
            </div>
        </div>
    </div>
</div></div>

<script src="../js/jquery-ui.min.js"></script>
<script>
var a4Settings = <?php echo json_encode($settings, JSON_UNESCAPED_SLASHES); ?>;
var a4Defaults = <?php echo json_encode(a4_default_settings($builderType), JSON_UNESCAPED_SLASHES); ?>;
var a4BuilderType = <?php echo json_encode($builderType); ?>;
var a4FontOptions = <?php echo json_encode($fontOptions, JSON_UNESCAPED_SLASHES); ?>;
var baseW = 1122, baseH = 794, viewW = 842, viewH = 595;
var selectedA4FieldKey = null;
var selectedA4FieldKeys = [];
var a4GroupDragStart = {};
var a4PrimaryDragStart = null;
var selectedExtraImageId = null;
function sx(v){ return v * viewW / baseW; } function sy(v){ return v * viewH / baseH; }
function ux(v){ return Math.round(v * baseW / viewW); } function uy(v){ return Math.round(v * baseH / viewH); }
function clamp(v,min,max){ return Math.max(min, Math.min(max, v)); }
function a4FieldElement(key){ return $("#field_"+key); }
function visibleA4FieldKeys(){
    return $(".a4-field:visible").map(function(){ return String($(this).data("key")); }).get();
}
function applyA4Orientation(){
    var orientation = a4Settings.orientation === "portrait" ? "portrait" : "landscape";
    if (orientation === "portrait") {
        baseW = 794; baseH = 1122; viewW = 595; viewH = 842;
    } else {
        baseW = 1122; baseH = 794; viewW = 842; viewH = 595;
    }
    $("#a4Canvas").css({width:viewW+"px",height:viewH+"px"});
    $("#a4Orientation").val(orientation);
}
function a4FieldFontSize(f){ return f.fontSize || $("#fontSize").val() || 15; }
function a4FieldFontColor(f){ return f.fontColor || $("#fontColor").val() || "#000000"; }
function a4FieldLabelWidth(f){ return f.labelWidth || $("#labelWidth").val() || 140; }
function a4FieldFontFamily(f){ return f.fontFamily || $("#fontFamily").val() || "Arial"; }
function a4AlignValue(value){ return value === "center" || value === "right" ? value : "left"; }
function appendA4FontOption(font){
    if (!font || !font.family) return;
    var label = font.label || font.family;
    if (!$("#fontFamily option[value='"+font.family.replace(/'/g, "\\'")+"']").length) {
        $("#fontFamily").append($("<option></option>").val(font.family).text(label));
        $("#a4FieldFontFamily").append($("<option></option>").val(font.family).text(label));
    }
    if (font.src && !$("style[data-font-family='"+font.family.replace(/'/g, "\\'")+"']").length) {
        $("<style></style>").attr("data-font-family", font.family).text("@font-face{font-family:'"+font.family.replace(/'/g, "\\'")+"';src:url('"+font.src+"?v="+Date.now()+"') format('truetype');font-weight:300 700;font-style:normal;font-display:swap}").appendTo("head");
    }
}
function applyA4FieldStyle(key){
    var f = a4Settings.fields[key], el = $("#field_"+key);
    if (!f || !el.length) return;
    var isTick = f.valueType === "tick";
    el.toggleClass("a4-tick-field", isTick);
    var manualFontSize = parseFloat(f.fontSize || 0);
    var tickFitSize = Math.max(6, Math.min(el.innerWidth(), el.innerHeight()) * 0.9);
    var boxFontSize = isTick ? (manualFontSize > 0 ? Math.min(manualFontSize, tickFitSize) : tickFitSize) : Math.min(a4FieldFontSize(f), Math.max(6, el.innerHeight() - 2));
    el.css({fontSize:boxFontSize+"px",lineHeight:isTick ? el.innerHeight()+"px" : Math.max(boxFontSize * 1.15, boxFontSize + 1)+"px",color:a4FieldFontColor(f),fontFamily:a4FieldFontFamily(f),fontWeight:"300"});
    var labelW = f.showLabel === "none" ? 0 : sx(a4FieldLabelWidth(f));
    var gap = f.showLabel === "none" ? 0 : sx(12);
    var valueW = isTick ? Math.max(4, el.innerWidth()) : Math.max(10, el.innerWidth() - labelW - gap);
    el.find(".a4-label").text(f.label || "").css({width:labelW+"px",display:f.showLabel === "none" ? "none" : "inline-block",textAlign:a4AlignValue(f.labelAlign),fontWeight:f.labelFontWeight === "bold" ? "700" : "300",textShadow:f.labelFontWeight === "bold" ? "0.35px 0 currentColor" : "none"});
    el.find(".a4-value").text(f.valueType === "tick" ? "✓" : ((f.showLabel === "none" || f.showColon === "none" ? "" : ": ") + "Sample")).css({width:valueW+"px",textAlign:a4AlignValue(f.valueAlign),fontFamily:f.valueType === "tick" ? "'Segoe UI Symbol','Arial Unicode MS','DejaVu Sans',Arial,sans-serif" : "inherit",fontWeight:f.fontWeight === "bold" ? "700" : "300",textShadow:f.fontWeight === "bold" ? "0.35px 0 currentColor" : "none"});
    if (!isTick) {
        var boxW = Math.max(4, el.innerWidth());
        var desiredLabelW = f.showLabel === "none" ? 0 : sx(a4FieldLabelWidth(f));
        var showPreviewLabel = f.showLabel !== "none" && boxW >= desiredLabelW + sx(12) + 24;
        var previewLabelW = showPreviewLabel ? desiredLabelW : 0;
        var previewGap = showPreviewLabel ? sx(12) : 0;
        var previewValueW = Math.max(10, boxW - previewLabelW - previewGap);
        el.find(".a4-label").css({width:previewLabelW+"px",display:showPreviewLabel ? "inline-block" : "none"});
        el.find(".a4-value").text((showPreviewLabel && f.showColon !== "none" ? ": " : "") + "Sample").css({width:previewValueW+"px"});
    }
    applyA4TickSizing(key);
}
function applyA4TickSizing(key){
    var f = a4Settings.fields[key], el = $("#field_"+key);
    if (!f || f.valueType !== "tick" || !el.length) return;
    var manualFontSize = parseFloat(f.fontSize || 0);
    var tickFitSize = Math.max(6, Math.min(el.innerWidth(), el.innerHeight()) * 0.9);
    var size = manualFontSize > 0 ? Math.min(manualFontSize, tickFitSize) : tickFitSize;
    el.addClass("a4-tick-field").css({fontSize:size+"px",lineHeight:el.innerHeight()+"px"});
    el.find(".a4-label").hide();
    el.find(".a4-value").text("\u2713").css({width:Math.max(4, el.innerWidth())+"px",height:el.innerHeight()+"px",lineHeight:el.innerHeight()+"px",textAlign:"center"});
}
function extraImageById(id){
    var found = null;
    $.each(a4Settings.additionalImages || [], function(_, img){ if(String(img.id) === String(id)) found = img; });
    return found;
}
function extraImageUrl(src){
    return src ? src + (src.indexOf("?") === -1 ? "?v=" : "&v=") + Date.now() : "";
}
function selectExtraImage(id){
    selectedExtraImageId = id ? String(id) : null;
    $(".a4-extra-image").removeClass("a4-extra-selected");
    if (selectedExtraImageId) $(".a4-extra-image[data-id='"+selectedExtraImageId+"']").addClass("a4-extra-selected");
    renderExtraImageList();
}
function initExtraImageBox(el){
    $(el).draggable({containment:"#a4Canvas"}).resizable({containment:"#a4Canvas"}).on("click", function(event){
        event.stopPropagation();
        selectExtraImage($(this).data("id"));
    });
}
function renderExtraImages(){
    $(".a4-extra-image").remove();
    $.each(a4Settings.additionalImages || [], function(_, img){
        var box = $('<div class="a4-draggable a4-extra-image" data-id=""></div>').attr("data-id", img.id).append($("<img>", {src: extraImageUrl(img.src), alt: img.label || "Extra Image"}));
        $("#a4Canvas").append(box);
        box.css({left:sx(img.x || 0),top:sy(img.y || 0),width:sx(img.w || 120),height:sy(img.h || 80),display:(img.display || "block")});
        initExtraImageBox(box);
    });
    renderExtraImageList();
}
function renderExtraImageList(){
    var list = $("#extraImagesList").empty();
    var images = a4Settings.additionalImages || [];
    if (!images.length) { list.append('<div class="text-muted" style="font-size:12px;">No additional images added.</div>'); return; }
    $.each(images, function(index, img){
        var row = $('<div class="field-row"></div>');
        row.append($("<label></label>").text(img.label || ("Image " + (index + 1))));
        var btn = $('<button type="button" class="btn btn-default btn-mini">Select</button>').toggleClass("btn-primary", String(img.id) === String(selectedExtraImageId));
        btn.on("click", function(){ selectExtraImage(img.id); });
        row.append(btn);
        list.append(row);
    });
}
function syncA4FieldGeometry(key){
    var el = a4FieldElement(key), f = a4Settings.fields[key];
    if (!el.length || !f) return;
    var p = el.position();
    f.x = ux(p.left);
    f.y = uy(p.top);
    f.w = ux(el.outerWidth());
    f.h = uy(el.outerHeight());
}
function updateA4PositionInputs(){
    var key = selectedA4FieldKey;
    $("#a4SelectedCount").text((selectedA4FieldKeys.length || 0) + " selected");
    if (!key || !a4Settings.fields[key]) {
        $("#a4FieldX,#a4FieldY,#a4FieldW,#a4FieldH").val("");
        return;
    }
    syncA4FieldGeometry(key);
    var f = a4Settings.fields[key];
    $("#a4FieldX").val(Math.round(f.x || 0));
    $("#a4FieldY").val(Math.round(f.y || 0));
    $("#a4FieldW").val(Math.round(f.w || 0));
    $("#a4FieldH").val(Math.round(f.h || 0));
}
function setA4Selection(keys, activeKey){
    var clean = [];
    $.each(keys || [], function(_, key){
        key = String(key);
        if (a4Settings.fields[key] && clean.indexOf(key) === -1) clean.push(key);
    });
    if (!clean.length && activeKey && a4Settings.fields[activeKey]) clean = [String(activeKey)];
    selectedA4FieldKeys = clean;
    selectedA4FieldKey = activeKey && clean.indexOf(String(activeKey)) !== -1 ? String(activeKey) : (clean[0] || null);
    $(".a4-field").removeClass("a4-selected a4-group-selected");
    $.each(selectedA4FieldKeys, function(_, key){ a4FieldElement(key).addClass("a4-group-selected"); });
    if (selectedA4FieldKey) a4FieldElement(selectedA4FieldKey).addClass("a4-selected");
    updateA4PositionInputs();
}
function selectA4Field(key, additive){
    key = String(key);
    if (!a4Settings.fields[key]) return;
    if (additive) {
        var keys = selectedA4FieldKeys.slice();
        var index = keys.indexOf(key);
        if (index === -1) keys.push(key);
        else if (keys.length > 1) keys.splice(index, 1);
        setA4Selection(keys, key);
    } else {
        setA4Selection([key], key);
    }
    var f = a4Settings.fields[key] || {};
    $("#a4FieldLabelText").val(f.label || "");
    $("#a4FieldShowLabel").val(f.showLabel === "none" ? "none" : "block");
    $("#a4FieldShowColon").val(f.showColon === "none" ? "none" : "block");
    $("#a4FieldLabelFontWeight").val(f.labelFontWeight === "bold" ? "bold" : "normal");
    $("#a4FieldFontWeight").val(f.fontWeight === "bold" ? "bold" : "normal");
    $("#a4FieldFontFamily").val(f.fontFamily || "");
    $("#a4FieldFontSize").val(f.fontSize || "");
    $("#a4FieldFontColor").val(f.fontColor || $("#fontColor").val() || "#000000");
    $("#a4FieldLabelWidth").val(f.labelWidth || "");
    $("#a4FieldLabelAlign").val(a4AlignValue(f.labelAlign));
    $("#a4FieldValueAlign").val(a4AlignValue(f.valueAlign));
}
function applyA4FieldPosition(){
    var key = selectedA4FieldKey;
    if (!key || !a4Settings.fields[key]) return;
    var x = parseFloat($("#a4FieldX").val());
    var y = parseFloat($("#a4FieldY").val());
    var w = parseFloat($("#a4FieldW").val());
    var h = parseFloat($("#a4FieldH").val());
    if (isNaN(x) || isNaN(y) || isNaN(w) || isNaN(h) || w <= 0 || h <= 0) return;
    var el = a4FieldElement(key);
    el.css({left:sx(x),top:sy(y),width:sx(w),height:sy(h)});
    syncA4FieldGeometry(key);
    updateA4PositionInputs();
}
function moveA4Selected(dxUnits, dyUnits){
    var dx = sx(dxUnits || 0), dy = sy(dyUnits || 0);
    $.each(selectedA4FieldKeys, function(_, key){
        var el = a4FieldElement(key);
        if (!el.length || !el.is(":visible")) return;
        var p = el.position();
        var maxLeft = viewW - el.outerWidth();
        var maxTop = viewH - el.outerHeight();
        el.css({left:clamp(p.left + dx, 0, maxLeft), top:clamp(p.top + dy, 0, maxTop)});
        syncA4FieldGeometry(key);
    });
    updateA4PositionInputs();
}
function alignA4SelectedLeft(){
    if (selectedA4FieldKeys.length < 2) return;
    var active = a4FieldElement(selectedA4FieldKey);
    if (!active.length) return;
    var left = active.position().left;
    $.each(selectedA4FieldKeys, function(_, key){
        var el = a4FieldElement(key);
        if (!el.length || !el.is(":visible")) return;
        el.css({left:left});
        syncA4FieldGeometry(key);
    });
    updateA4PositionInputs();
}
function applySelectedA4FieldStyle(){
    if (!selectedA4FieldKey) return;
    var f = a4Settings.fields[selectedA4FieldKey];
    f.label = $("#a4FieldLabelText").val().trim() || f.label;
    f.showLabel = $("#a4FieldShowLabel").val();
    f.showColon = $("#a4FieldShowColon").val();
    f.labelFontWeight = $("#a4FieldLabelFontWeight").val() === "bold" ? "bold" : "normal";
    f.fontWeight = $("#a4FieldFontWeight").val() === "bold" ? "bold" : "normal";
    f.fontFamily = $("#a4FieldFontFamily").val() || "";
    f.fontSize = $("#a4FieldFontSize").val() ? parseFloat($("#a4FieldFontSize").val()) : null;
    f.fontColor = $("#a4FieldFontColor").val();
    f.labelWidth = $("#a4FieldLabelWidth").val() ? parseFloat($("#a4FieldLabelWidth").val()) : null;
    f.labelAlign = a4AlignValue($("#a4FieldLabelAlign").val());
    f.valueAlign = a4AlignValue($("#a4FieldValueAlign").val());
    applyA4FieldStyle(selectedA4FieldKey);
    $(".field-display[data-key='"+selectedA4FieldKey+"']").closest(".field-row").find("label").text(f.label);
}
function applyA4Settings(){
    applyA4Orientation();
    $("#fontFamily").val(a4Settings.fontFamily || "Arial");
    $("#fontSize").val(a4Settings.fontSize || 15);
    $("#fontColor").val(a4Settings.fontColor || "#000000");
    $("#labelWidth").val(a4Settings.labelWidth || 140);
    $(".a4-draggable").css({fontFamily:$("#fontFamily").val(),fontSize:$("#fontSize").val()+"px",color:$("#fontColor").val()});
    $(".a4-label").css({width:sx($("#labelWidth").val())+"px"});
    $.each(a4Settings.fields, function(key, f){
        var el = $("#field_"+key);
        el.css({left:sx(f.x),top:sy(f.y),width:sx(f.w),height:sy(f.h),display:f.display});
        $(".field-display[data-key='"+key+"']").val(f.display || "block");
        $(".field-display[data-key='"+key+"']").closest(".field-row").find("label").text(f.label || "");
        applyA4FieldStyle(key);
    });
    $("#a4Stone").css({left:sx(a4Settings.stoneImage.x),top:sy(a4Settings.stoneImage.y),width:sx(a4Settings.stoneImage.w),height:sy(a4Settings.stoneImage.h),display:a4Settings.stoneImage.display});
    $("#a4Qr").css({left:sx(a4Settings.qrCode.x),top:sy(a4Settings.qrCode.y),width:sx(a4Settings.qrCode.w),height:sy(a4Settings.qrCode.h),display:a4Settings.qrCode.display});
    $("#stoneDisplay").val(a4Settings.stoneImage.display || "block"); $("#qrDisplay").val(a4Settings.qrCode.display || "block");
    renderExtraImages();
}
function collectA4Settings(){
    var data = {orientation:$("#a4Orientation").val(),backgroundImage:a4Settings.backgroundImage,fontFamily:$("#fontFamily").val(),fontSize:$("#fontSize").val(),fontColor:$("#fontColor").val(),labelWidth:$("#labelWidth").val(),fields:{},stoneImage:{},qrCode:{},additionalImages:[]};
    $(".a4-field").each(function(){ var key=$(this).data("key"), p=$(this).position(), f=a4Settings.fields[key]; data.fields[key]={label:f.label,column:f.column,valueType:f.valueType || "",display:$(".field-display[data-key='"+key+"']").val(),showLabel:f.showLabel || "block",showColon:f.showColon === "none" ? "none" : "block",labelFontWeight:f.labelFontWeight === "bold" ? "bold" : "normal",fontWeight:f.fontWeight === "bold" ? "bold" : "normal",fontFamily:f.fontFamily || "",fontSize:f.fontSize || "",fontColor:f.fontColor || "",labelWidth:f.labelWidth || "",labelAlign:a4AlignValue(f.labelAlign),valueAlign:a4AlignValue(f.valueAlign),x:ux(p.left),y:uy(p.top),w:ux($(this).outerWidth()),h:uy($(this).outerHeight())}; });
    var sp=$("#a4Stone").position(); data.stoneImage={display:$("#stoneDisplay").val(),x:ux(sp.left),y:uy(sp.top),w:ux($("#a4Stone").outerWidth()),h:uy($("#a4Stone").outerHeight())};
    var qp=$("#a4Qr").position(); data.qrCode={display:$("#qrDisplay").val(),x:ux(qp.left),y:uy(qp.top),w:ux($("#a4Qr").outerWidth()),h:uy($("#a4Qr").outerHeight())};
    $(".a4-extra-image").each(function(index){
        var id = String($(this).data("id"));
        var img = extraImageById(id);
        if (!img) return;
        var p = $(this).position();
        data.additionalImages.push({id:id,label:img.label || ("Image " + (index + 1)),src:img.src,display:$(this).css("display")==="none"?"none":"block",x:ux(p.left),y:uy(p.top),w:ux($(this).outerWidth()),h:uy($(this).outerHeight())});
    });
    return data;
}
$(function(){
    $(".a4-draggable").draggable({
        containment:"#a4Canvas",
        start:function(event, ui){
            var el = $(this);
            if (!el.hasClass("a4-field")) return;
            var key = String(el.data("key"));
            if (selectedA4FieldKeys.indexOf(key) === -1) selectA4Field(key, event.ctrlKey || event.shiftKey || event.metaKey);
            a4GroupDragStart = {};
            $.each(selectedA4FieldKeys, function(_, fieldKey){
                var fieldEl = a4FieldElement(fieldKey);
                if (!fieldEl.length || !fieldEl.is(":visible")) return;
                a4GroupDragStart[fieldKey] = fieldEl.position();
            });
            a4PrimaryDragStart = {left: ui.position.left, top: ui.position.top};
        },
        drag:function(event, ui){
            var el = $(this);
            if (!el.hasClass("a4-field") || selectedA4FieldKeys.length < 2 || !a4PrimaryDragStart) return;
            var activeKey = String(el.data("key"));
            var dx = ui.position.left - a4PrimaryDragStart.left;
            var dy = ui.position.top - a4PrimaryDragStart.top;
            $.each(selectedA4FieldKeys, function(_, fieldKey){
                if (fieldKey === activeKey || !a4GroupDragStart[fieldKey]) return;
                var fieldEl = a4FieldElement(fieldKey);
                var maxLeft = viewW - fieldEl.outerWidth();
                var maxTop = viewH - fieldEl.outerHeight();
                fieldEl.css({
                    left: clamp(a4GroupDragStart[fieldKey].left + dx, 0, maxLeft),
                    top: clamp(a4GroupDragStart[fieldKey].top + dy, 0, maxTop)
                });
            });
        },
        stop:function(){
            if ($(this).hasClass("a4-field")) {
                $.each(selectedA4FieldKeys, function(_, key){ syncA4FieldGeometry(key); });
                updateA4PositionInputs();
            }
            a4GroupDragStart = {};
            a4PrimaryDragStart = null;
        }
    }).resizable({
        containment:"#a4Canvas",
        minWidth:6,
        minHeight:6,
        resize:function(){
            if ($(this).hasClass("a4-field")) applyA4FieldStyle(String($(this).data("key")));
        },
        stop:function(){
            if ($(this).hasClass("a4-field")) {
                syncA4FieldGeometry(String($(this).data("key")));
                applyA4FieldStyle(String($(this).data("key")));
                updateA4PositionInputs();
            }
        }
    });
    applyA4Settings();
    selectA4Field($(".a4-field").first().data("key"));
    $(".a4-field").on("click", function(event){ selectA4Field($(this).data("key"), event.ctrlKey || event.shiftKey || event.metaKey); });
    $(".field-row label").on("click", function(){ var key=$(this).siblings(".field-display").data("key"); if(key) selectA4Field(key); });
    $("#fontFamily,#fontSize,#fontColor,#labelWidth").on("input change", function(){ $(".a4-draggable").css({fontFamily:$("#fontFamily").val()}); $.each(a4Settings.fields, function(key){ applyA4FieldStyle(key); }); });
    $("#a4Orientation").on("change", function(){ a4Settings.orientation=$(this).val(); applyA4Settings(); });
    $("#a4FieldLabelText,#a4FieldShowLabel,#a4FieldShowColon,#a4FieldLabelFontWeight,#a4FieldFontWeight,#a4FieldFontFamily,#a4FieldFontSize,#a4FieldFontColor,#a4FieldLabelWidth,#a4FieldLabelAlign,#a4FieldValueAlign").on("input change", applySelectedA4FieldStyle);
    $("#applyA4FieldPosition").on("click", applyA4FieldPosition);
    $("#a4FieldX,#a4FieldY,#a4FieldW,#a4FieldH").on("change", applyA4FieldPosition).on("keydown", function(event){ if(event.key === "Enter"){ event.preventDefault(); applyA4FieldPosition(); } });
    $("#nudgeA4Selected").on("click", function(){ moveA4Selected(parseFloat($("#a4NudgeX").val()) || 0, parseFloat($("#a4NudgeY").val()) || 0); });
    $("#selectAllA4Fields").on("click", function(){ var keys = visibleA4FieldKeys(); if(keys.length) { selectA4Field(keys[0]); setA4Selection(keys, keys[0]); } });
    $("#clearA4Selection").on("click", function(){ if(selectedA4FieldKey) setA4Selection([selectedA4FieldKey], selectedA4FieldKey); });
    $("#alignA4SelectedLeft").on("click", alignA4SelectedLeft);
    $("#clearA4FieldStyle").on("click", function(){ if(!selectedA4FieldKey) return; var f=a4Settings.fields[selectedA4FieldKey]; f.showLabel="block"; f.showColon="block"; f.labelFontWeight="normal"; f.fontWeight="normal"; f.fontFamily=""; f.fontSize=null; f.fontColor=""; f.labelWidth=null; f.labelAlign="left"; f.valueAlign="left"; selectA4Field(selectedA4FieldKey); applyA4FieldStyle(selectedA4FieldKey); });
    $(".field-display").on("change", function(){ $("#field_"+$(this).data("key")).toggle($(this).val() !== "none"); updateA4PositionInputs(); });
    $("#stoneDisplay").on("change", function(){ $("#a4Stone").toggle($(this).val() !== "none"); });
    $("#qrDisplay").on("change", function(){ $("#a4Qr").toggle($(this).val() !== "none"); });
    $("#uploadFont").on("click", function(){
        var file = $("#fontUpload")[0].files[0];
        if (!file) { alert("Please select a TTF font first."); return; }
        var form = new FormData();
        form.append("font", file);
        form.append("label", file.name.replace(/\.[^.]+$/, ""));
        $.ajax({url:"report-builder-font-upload.php",method:"POST",data:form,processData:false,contentType:false,dataType:"json"}).done(function(resp){
            if (resp.status !== "success") { alert(resp.message || "Unable to upload font."); return; }
            appendA4FontOption(resp.font);
            $("#fontFamily").val(resp.font.family).trigger("change");
            $("#fontUpload").val("");
            alert("Font added. You can now use it globally or for the selected field.");
        }).fail(function(){ alert("Unable to upload font."); });
    });
    $("#uploadExtraImage").on("click", function(){
        var file = $("#extraImageUpload")[0].files[0];
        if (!file) { alert("Please select an image first."); return; }
        var form = new FormData();
        form.append("image", file);
        form.append("builder", "a4");
        form.append("report_type", a4BuilderType);
        $.ajax({url:"report-builder-image-upload.php",method:"POST",data:form,processData:false,contentType:false,dataType:"json"}).done(function(resp){
            if (resp.status !== "success") { alert(resp.message || "Unable to upload image."); return; }
            if (!a4Settings.additionalImages) a4Settings.additionalImages = [];
            var img = resp.image;
            img.x = 40; img.y = 40; img.w = 120; img.h = 80; img.display = "block";
            a4Settings.additionalImages.push(img);
            renderExtraImages();
            selectExtraImage(img.id);
            $("#extraImageUpload").val("");
        }).fail(function(){ alert("Unable to upload image."); });
    });
    $("#deleteExtraImage").on("click", function(){
        if (!selectedExtraImageId) return;
        a4Settings.additionalImages = $.grep(a4Settings.additionalImages || [], function(img){ return String(img.id) !== String(selectedExtraImageId); });
        selectedExtraImageId = null;
        renderExtraImages();
    });
    $("#saveA4Settings").on("click", function(){ $.post("save_a4_settings.php", {report_type:a4BuilderType,settings:JSON.stringify(collectA4Settings())}, function(resp){ alert(resp.message || "A4 settings saved."); }, "json").fail(function(){ alert("Unable to save A4 settings."); }); });
    $("#a4BuilderType").on("change", function(){ window.location.href="a4Settings.php?type="+encodeURIComponent(this.value); });
    $("#resetA4Defaults").on("click", function(){
        AppConfirm.show("Reset preview to default A4 layout? Click Save A4 Layout after checking it.", {
            title: "Reset layout",
            confirmText: "Reset",
            danger: true
        }).then(function(confirmed){
            if (confirmed) {
                a4Settings = $.extend(true, {}, a4Defaults);
                applyA4Settings();
            }
        });
    });
});
</script>
<?php include "assets/footer.php"; ?>
