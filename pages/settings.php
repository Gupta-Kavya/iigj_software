<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'atm_config.php';
$builderType = atm_report_type($_GET['type'] ?? 'S');
$builderAllowedTypes = array_keys(atm_report_type_labels($conn));
$positions = atm_read_positions($builderType);
$fieldSettings = atm_read_json(atm_layout_file($builderType, 'settings'), atm_default_fields());
$fieldDefinitions = atm_field_definitions($builderType);
$builderFieldKeys = atm_builder_field_keys($builderType);
$printSettings = atm_read_json(atm_user_file('atm-print-settings.json'), atm_default_print_settings());
$frontImage = isset($printSettings['frontImage']) ? trim((string) $printSettings['frontImage']) : '';
$frontImagePath = $frontImage !== '' ? __DIR__ . '/' . $frontImage : '';
$frontImageUrl = ($frontImage !== '' && is_file($frontImagePath)) ? htmlspecialchars($frontImage) . '?v=' . filemtime($frontImagePath) : '';
$frontBackgroundStyle = $frontImageUrl !== '' ? "background-image:url('" . $frontImageUrl . "');" : '';
include "assets/navbar.php";
$builderBackupStatus = $_GET['builder_backup'] ?? '';
?>
<link rel="stylesheet" href="../css/jquery-ui.min.css">
<style>
@font-face{font-family:'Arial Nova Cond Light';src:url('font-proxy.php?font=arialnova') format('truetype');font-weight:300;font-style:normal;font-display:swap}
@font-face{font-family:'Arial Nova Cond Light';src:url('font-proxy.php?font=arialnova') format('truetype');font-weight:400;font-style:normal;font-display:swap}
@font-face{font-family:'Arial Nova Cond Light';src:url('font-proxy.php?font=arialnova') format('truetype');font-weight:700;font-style:normal;font-display:swap}
.atm-builder-page { overflow-x:hidden; }
.atm-builder-page .builder-shell { display:grid; grid-template-columns:minmax(0,calc(100% - 370px)) 350px; gap:20px; align-items:start; max-width:100%; }
.atm-preview-card { position: sticky; top: 84px; z-index: 5; }
.atm-preview-card,.atm-tools-card{background:#fff;border:1px solid #ececf1;border-radius:10px;overflow:hidden;min-width:0}
.atm-card-head{padding:16px 18px;border-bottom:1px solid #ececf1}
.atm-card-head h3{margin:0;font-size:16px;font-weight:600;color:#171717}.atm-card-head p{margin:4px 0 0;color:#737373;font-size:13px}
.atm-preview-wrap{padding:26px;overflow:auto;background:#f7f7f8;text-align:center;max-width:100%;box-sizing:border-box}
#canvas{width:546.14173228px;height:346.96062992px;position:relative;<?php echo $frontBackgroundStyle; ?>background-color:#fff;background-repeat:no-repeat;background-position:center center;background-size:cover;border:1px solid #e5e5e5;border-radius:12px;box-shadow:0 18px 45px rgba(15,23,42,.12);display:inline-block;vertical-align:top;transform-origin:top center}
.draggable{border:1px dashed #737373;position:absolute;background:rgba(255,255,255,.32);overflow:hidden;box-sizing:border-box;border-radius:6px;cursor:move;color:inherit}
.draggable img{display:block;width:100%;height:100%;object-fit:contain}.ui-resizable-handle{background:rgba(64,64,64,.85);border-radius:999px}
.atm-extra-image.atm-extra-selected{border-color:#dc2626;background:rgba(254,226,226,.55)}
.atm-field-item{padding:2px 5px;white-space:normal}.atm-field-label{display:inline-block;font-weight:300;vertical-align:top}.atm-field-value{display:inline-block;overflow-wrap:anywhere;white-space:normal;font-weight:300;vertical-align:top}.atm-draggable-active{border-color:#2563eb;background:rgba(219,234,254,.58)}.atm-group-selected{border-color:#16a34a;background:rgba(220,252,231,.62)}.atm-draggable-active.atm-group-selected{border-color:#2563eb;background:rgba(219,234,254,.72)}
.atm-tools-card{max-height:calc(100vh - 96px);overflow-y:auto;padding:16px}.tool-section{border-bottom:1px solid #eef2f7;padding-bottom:14px;margin-bottom:14px}.tool-section:last-child{border-bottom:0;margin-bottom:0}
.atm-tools-card::-webkit-scrollbar{width:8px}.atm-tools-card::-webkit-scrollbar-track{background:#f1f5f9;border-radius:999px}.atm-tools-card::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:999px}.atm-tools-card::-webkit-scrollbar-thumb:hover{background:#94a3b8}
.tool-section h4{font-size:13px;font-weight:600;text-transform:none;color:#737373;margin:0 0 10px}.tool-section p{color:#737373;font-size:12px;line-height:1.45;margin:4px 0 10px}
.control-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.control-grid label,.single-control label,.field-list label{font-size:12px;color:#404040;font-weight:500;margin-bottom:4px;display:block}
.control-grid .form-control,.single-control .form-control{border-radius:8px;min-height:40px}.single-control{margin-bottom:10px}
.field-list{max-height:260px;overflow:auto;border:1px solid #ececf1;border-radius:8px;background:#f7f7f8;padding:10px}.field-row{align-items:center;display:grid;grid-template-columns:minmax(0,1fr) 78px;gap:8px;margin-bottom:8px}.field-row:last-child{margin-bottom:0}.field-row label{cursor:pointer;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.field-row select{height:34px;padding:4px 8px}.field-row.is-selected label{color:#2563eb;font-weight:700}
.position-tabs{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px}.btn-wide{width:100%;min-height:42px;border-radius:8px;font-weight:500}.mini-actions{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px}.btn-mini{border-radius:8px;font-size:12px;min-height:36px;padding:7px 9px}.selected-count{background:#eef2ff;border-radius:999px;color:#3730a3;display:inline-block;font-size:12px;font-weight:600;margin-left:6px;padding:2px 8px}
.settings-tabs{margin-bottom:14px}.save-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}.status-note{background:#f7f7f8;border:1px solid #ececf1;border-radius:8px;color:#737373;font-size:12px;line-height:1.45;margin-bottom:12px;padding:10px}
@media(max-width:1100px){.atm-builder-page .builder-shell{grid-template-columns:minmax(0,1fr) 330px;gap:14px}.atm-tools-card{padding:14px}.atm-preview-wrap{padding:18px;text-align:left}#canvas{width:481.88976378px;height:306.14173228px}}
@media(max-width:900px){.control-grid,.save-row{grid-template-columns:1fr}.atm-builder-page .builder-shell{grid-template-columns:minmax(0,1fr) 310px;gap:12px}#canvas{width:417.63779528px;height:265.32283465px}}
</style>

<div id="page-wrapper">
    <div class="container-fluid atm-builder-page">
        <div class="row">
            <div class="col-lg-12">
                <h1 class="page-header"><i class="fa fa-id-card-o"></i> ATM Card Builder</h1>
                <div style="max-width:300px;margin:-12px 0 16px;">
                    <label for="atmBuilderType" style="font-size:12px;color:#525252;">Certificate design</label>
                    <select id="atmBuilderType" class="form-control">
                        <?php foreach (atm_report_type_labels($conn) as $typeCode => $typeLabel): ?>
                            <?php if (!in_array($typeCode, $builderAllowedTypes, true)) continue; ?>
                            <option value="<?php echo htmlspecialchars($typeCode); ?>" <?php echo $builderType === $typeCode ? 'selected' : ''; ?>><?php echo htmlspecialchars($typeLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="row">
            <ul class="nav nav-tabs settings-tabs">
                <li role="presentation" class="active"><a href="settings.php?type=<?php echo $builderType; ?>">Card Builder</a></li>
                <li role="presentation"><a href="backPrintSettings.php">Card Images &amp; Print</a></li>
                <li role="presentation"><a href="a4Settings.php?type=<?php echo $builderType; ?>">A4 Builder</a></li>
                <li role="presentation"><a href="postcardSettings.php?type=<?php echo $builderType; ?>">Postcard Builder</a></li>
            </ul>
            <?php if ($builderBackupStatus): ?>
                <div class="alert alert-<?php echo $builderBackupStatus === 'imported' ? 'success' : 'danger'; ?>">
                    <?php
                    echo htmlspecialchars($builderBackupStatus === 'imported'
                        ? 'Builder backup imported successfully.'
                        : 'Unable to import builder backup. Please choose a valid ATM builder backup file.');
                    ?>
                </div>
            <?php endif; ?>

            <div class="builder-shell">
                <div class="atm-preview-card">
                    <div class="atm-card-head">
                        <h3>Live ATM Card Layout</h3>
                        <p>Drag and resize individual fields, stone image and QR code. Save after adjusting the layout.</p>
                    </div>
                    <div class="atm-preview-wrap">
                            <div id="canvas" class="customcard">
                                <?php foreach ($positions['fields'] as $key => $field): if (!in_array($key, $builderFieldKeys, true)) continue; ?>
                                    <div id="atmField_<?php echo htmlspecialchars($key); ?>" class="draggable atm-field-item" data-key="<?php echo htmlspecialchars($key); ?>">
                                        <span class="atm-field-label"><?php echo htmlspecialchars($field['label']); ?></span>
                                        <span class="atm-field-value"><?php echo (($field['valueType'] ?? '') === 'tick') ? '&#10003;' : ': Sample'; ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <div id="gemstone" class="draggable" style = "vertical-align:center;display:flex;align-items:center;justify-content:center;">
                                    Gemstone Image
                                </div>
                                <div id="qrcode" class="draggable">
                                    <div style="width:100%;height:100%;background:repeating-linear-gradient(45deg,#111 0,#111 3px,#fff 3px,#fff 6px);display:flex;align-items:center;justify-content:center;"><strong style="background:#fff;padding:2px;">QR</strong></div>
                                </div>
                                <?php foreach (($positions['additionalImages'] ?? []) as $extraImage): $extraPath = __DIR__ . '/' . ($extraImage['src'] ?? ''); $extraSrc = is_file($extraPath) ? ($extraImage['src'] . '?v=' . filemtime($extraPath)) : ($extraImage['src'] ?? ''); ?>
                                    <div class="draggable atm-extra-image" data-id="<?php echo htmlspecialchars($extraImage['id'] ?? ''); ?>">
                                        <img src="<?php echo htmlspecialchars($extraSrc); ?>" alt="<?php echo htmlspecialchars($extraImage['label'] ?? 'Extra Image'); ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                    </div>
                </div>

                <div class="atm-tools-card">
                    <div class="tool-section">
                                    <h4>Individual Fields</h4>
                                    <p>Select any field, then drag/resize it on the card or adjust exact position below.</p>
                                    <div class="field-list">
                                        <?php foreach ($positions['fields'] as $key => $field): if (!in_array($key, $builderFieldKeys, true)) continue; ?>
                                            <div class="field-row" data-key="<?php echo htmlspecialchars($key); ?>">
                                                <label><?php echo htmlspecialchars($field['label']); ?></label>
                                                <select class="form-control atm-field-display" data-key="<?php echo htmlspecialchars($key); ?>">
                                                    <option value="block">Show</option>
                                                    <option value="none">Hide</option>
                                                </select>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                    </div>
                    <div class="tool-section">
                                    <h4>Selected Field Position</h4>
                                    <p><span id="selectedFieldName">Select a field from the preview or list.</span> <span class="selected-count" id="atmSelectedCount">0 selected</span></p>
                                    <div class="control-grid">
                                        <div><label for="fieldTop">Y Axis</label><input type="number" id="fieldTop" class="form-control"></div>
                                        <div><label for="fieldLeft">X Axis</label><input type="number" id="fieldLeft" class="form-control"></div>
                                        <div><label for="fieldWidth">Width</label><input type="number" id="fieldWidth" class="form-control" min="20" max="321"></div>
                                        <div><label for="fieldHeight">Height</label><input type="number" id="fieldHeight" class="form-control" min="6" max="204"></div>
                                    </div>
                                    <p style="margin-top:10px;">Ctrl/Shift-click multiple fields, then drag any selected field to move them together.</p>
                                    <div class="control-grid">
                                        <div><label for="atmNudgeX">Move X By</label><input type="number" id="atmNudgeX" class="form-control" step="1" value="0"></div>
                                        <div><label for="atmNudgeY">Move Y By</label><input type="number" id="atmNudgeY" class="form-control" step="1" value="0"></div>
                                    </div>
                                    <div class="mini-actions">
                                        <button type="button" id="moveAtmSelected" class="btn btn-default btn-mini">Move Selected</button>
                                        <button type="button" id="selectAllAtmFields" class="btn btn-default btn-mini">Select Visible Fields</button>
                                    </div>
                                    <div class="mini-actions">
                                        <button type="button" id="clearAtmSelection" class="btn btn-default btn-mini">Clear Multi Select</button>
                                        <button type="button" id="alignAtmSelectedLeft" class="btn btn-default btn-mini">Align Left</button>
                                    </div>
                    </div>
                    <div class="tool-section">
                                    <h4>Selected Field Style</h4>
                                    <p>These settings apply only to the selected field.</p>
                                    <div class="single-control">
                                        <label for="fieldLabelText">Label Name</label>
                                        <input type="text" id="fieldLabelText" class="form-control" placeholder="Custom label">
                                    </div>
                                    <div class="control-grid">
                                        <div><label for="fieldShowLabel">Label Display</label><select id="fieldShowLabel" class="form-control"><option value="block">Show</option><option value="none">Hide</option></select></div>
                                        <div><label for="fieldShowColon">Colon Display</label><select id="fieldShowColon" class="form-control"><option value="block">Show</option><option value="none">Hide</option></select></div>
                                        <div><label for="fieldLabelFontWeight">Label Weight</label><select id="fieldLabelFontWeight" class="form-control"><option value="normal">Regular</option><option value="bold">Bold</option></select></div>
                                        <div><label for="fieldFontWeight">Value Weight</label><select id="fieldFontWeight" class="form-control"><option value="normal">Regular</option><option value="bold">Bold</option></select></div>
                                        <div><label for="fieldFontSize">Font Size</label><input type="number" id="fieldFontSize" class="form-control" min="4" max="18" step="1" placeholder="Default"></div>
                                        <div><label for="fieldLabelWidth">Label Width</label><input type="number" id="fieldLabelWidth" class="form-control" min="0" max="180" step="1" placeholder="Default"></div>
                                        <div><label for="fieldLabelFontColor">Label Color</label><input type="color" id="fieldLabelFontColor" class="form-control" value="#000000"></div>
                                        <div><label for="fieldValueFontColor">Value Color</label><input type="color" id="fieldValueFontColor" class="form-control" value="#000000"></div>
                                        <div><label for="fieldLabelAlign">Label Align</label><select id="fieldLabelAlign" class="form-control"><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select></div>
                                        <div><label for="fieldValueAlign">Value Align</label><select id="fieldValueAlign" class="form-control"><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select></div>
                                    </div>
                                    <button type="button" id="clearFieldStyle" class="btn btn-default btn-wide" style="margin-top:10px;">Use Global Text Style</button>
                    </div>
                    <div class="tool-section">
                                    <h4>Text Style</h4>
                                    <p>Default style for fields that do not have their own custom style.</p>
                                    <div class="control-grid">
                                        <div><label for="tableFontSize">Font Size</label><input type="number" id="tableFontSize" name="tableFontSize" min="4" max="18" step="1" class="form-control"/></div>
                                        <div><label for="tableLabelWidth">Label Width</label><input type="number" id="tableLabelWidth" min="20" max="180" step="1" class="form-control"/></div>
                                        <div><label for="tableFontFamily">Font</label><select id="tableFontFamily" class="form-control"><option value="Arial">Arial</option><option value="Arial Nova Cond Light">Arial Nova Cond Light</option><option value="Calibri">Calibri</option><option value="Times New Roman">Times New Roman</option><option value="Verdana">Verdana</option></select></div>
                                        <div><label for="tableFontColor">Font Color</label><input type="color" id="tableFontColor" class="form-control" value="#000000"></div>
                                    </div>
                    </div>
                    <div class="tool-section">
                                    <h4>Stone Image</h4>
                                    <div class="single-control">
                                    <label>Display</label>
                                    <select id="gemstoneDisplay" class="form-control">
                                        <option value="none">None</option>
                                        <option value="block">Block</option>
                                    </select>
                                    </div>
                                    <div class="control-grid">
                                    <div><label for="gemstoneTop">Y Axis</label>
                                    <input type="number" id="gemstoneTop" class="form-control">
                                    </div><div><label for="gemstoneLeft">X Axis</label>
                                    <input type="number" id="gemstoneLeft" class="form-control">
                                    </div><div><label for="gemstoneSize">Size</label>
                                    <input type="number" id="gemstoneSize" class="form-control" min="10" max="204">
                                    </div></div>
                    </div>
                    <div class="tool-section">
                                    <h4>QR Code</h4>
                                    <div class="single-control">
                                    <label>Display</label>
                                    <select id="qrcodeDisplay" class="form-control">
                                        <option value="none">None</option>
                                        <option value="block">Block</option>
                                    </select>
                                    </div>
                                    <div class="control-grid">
                                    <div><label for="qrcodeTop">Y Axis</label>
                                    <input type="number" id="qrcodeTop" class="form-control">
                                    </div><div><label for="qrcodeLeft">X Axis</label>
                                    <input type="number" id="qrcodeLeft" class="form-control">
                                    </div><div><label for="qrcodeSize">Size</label>
                                    <input type="number" id="qrcodeSize" class="form-control" min="10" max="204">
                                    </div></div>
                    </div>
                    <div class="tool-section">
                                    <h4>Additional Images</h4>
                                    <p>Upload any logo, stamp or extra image. Click it on the card to select, drag/resize, then save.</p>
                                    <input type="file" id="extraImageUpload" accept="image/jpeg,image/png" class="form-control">
                                    <div class="mini-actions">
                                        <button type="button" id="uploadExtraImage" class="btn btn-default btn-mini">Add Image</button>
                                        <button type="button" id="deleteExtraImage" class="btn btn-default btn-mini">Delete Selected</button>
                                    </div>
                                    <div id="extraImagesList" class="field-list" style="margin-top:10px;max-height:130px;"></div>
                    </div>
                    <div class="tool-section">
                                    <h4>Backup &amp; Restore</h4>
                                    <p>Download this ATM builder layout as a backup, or import a saved backup into the selected certificate design.</p>
                                    <a class="btn btn-default btn-wide" href="builder-settings-backup.php?action=export&amp;kind=atm&amp;type=<?php echo urlencode($builderType); ?>"><i class="fa fa-download"></i> Download Backup</a>
                                    <form method="post" enctype="multipart/form-data" action="builder-settings-backup.php" style="margin-top:10px;">
                                        <input type="hidden" name="action" value="import">
                                        <input type="hidden" name="kind" value="atm">
                                        <input type="hidden" name="type" value="<?php echo htmlspecialchars($builderType); ?>">
                                        <input type="file" name="backup_file" accept="application/json,.json" class="form-control" required>
                                        <button type="submit" class="btn btn-primary btn-wide" style="margin-top:10px;"><i class="fa fa-upload"></i> Import Backup</button>
                                    </form>
                    </div>
                    <div class="status-note">Changes are previewed instantly. Save after changing the card layout, font, image or QR position.</div>
                    <div class="save-row">
                        <button id="savePositions" class="btn btn-primary btn-wide">Save ATM Layout</button>
                        <button type="button" id="resetPreview" class="btn btn-default btn-wide">Refresh Preview</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../js/jquery-ui.min.js"></script>
<script>
var atmPositions = <?php echo json_encode($positions, JSON_UNESCAPED_SLASHES); ?>;
var atmBuilderType = <?php echo json_encode($builderType); ?>;

$(function () {
    var baseCanvasWidth = 321.25984252;
    var editScale = $("#canvas").outerWidth() / baseCanvasWidth;
    var selectedFieldKey = null;
    var selectedFieldKeys = [];
    var groupDragStart = {};
    var primaryDragStart = null;
    var selectedExtraImageId = null;

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function fieldElement(key) {
        return $("#atmField_" + key);
    }

    function visibleFieldKeys() {
        return $(".atm-field-item:visible").map(function () {
            return String($(this).data("key"));
        }).get();
    }

    $(".draggable").draggable({
        containment: "#canvas",
        start: function (event, ui) {
            if ($(this).hasClass("atm-field-item")) {
                var key = String($(this).data("key"));
                if (selectedFieldKeys.indexOf(key) === -1) {
                    selectField(key, event.ctrlKey || event.shiftKey || event.metaKey);
                } else if (selectedFieldKey !== key) {
                    setFieldSelection(selectedFieldKeys, key);
                    $("#selectedFieldName").text("Selected: " + $(".field-row[data-key='" + key + "'] label").text());
                    syncSelectedFieldInputs();
                    syncSelectedFieldStyleInputs();
                }
                groupDragStart = {};
                $.each(selectedFieldKeys, function (_, fieldKey) {
                    var $field = fieldElement(fieldKey);
                    if ($field.length && $field.is(":visible")) {
                        groupDragStart[fieldKey] = $field.position();
                    }
                });
                primaryDragStart = { left: ui.position.left, top: ui.position.top };
            }
        },
        drag: function (event, ui) {
            if ($(this).hasClass("atm-field-item")) {
                var activeKey = String($(this).data("key"));
                if (selectedFieldKeys.length > 1 && primaryDragStart) {
                    var dx = ui.position.left - primaryDragStart.left;
                    var dy = ui.position.top - primaryDragStart.top;
                    $.each(selectedFieldKeys, function (_, fieldKey) {
                        if (fieldKey === activeKey || !groupDragStart[fieldKey]) return;
                        var $field = fieldElement(fieldKey);
                        var maxLeft = $("#canvas").innerWidth() - $field.outerWidth();
                        var maxTop = $("#canvas").innerHeight() - $field.outerHeight();
                        $field.css({
                            left: clamp(groupDragStart[fieldKey].left + dx, 0, maxLeft),
                            top: clamp(groupDragStart[fieldKey].top + dy, 0, maxTop)
                        });
                    });
                }
                syncSelectedFieldInputs();
            } else {
                updateMediaInputs();
            }
        },
        stop: function () {
            if ($(this).hasClass("atm-field-item")) {
                groupDragStart = {};
                primaryDragStart = null;
                syncSelectedFieldInputs();
            } else {
                updateMediaInputs();
            }
        }
    });

    $(".atm-field-item").resizable({
        containment: "#canvas",
        resize: function () {
            selectField($(this).data("key"));
            syncSelectedFieldInputs();
        }
    });

    $("#gemstone, #qrcode").resizable({
        containment: "#canvas",
        handles: "se",
        resize: function (event, ui) {
            var newSize = Math.max(ui.size.width, ui.size.height);
            ui.size.width = newSize;
            ui.size.height = newSize;
            ui.element.css({ width: newSize, height: newSize });
            ui.element.find("img").css({ width: "100%", height: "100%" });
            updateMediaInputs();
        }
    });

    function showMessage(type, message) {
        if (window.AppToast && typeof AppToast[type] === "function") {
            AppToast[type](message);
        } else {
            alert(message);
        }
    }

    function numericValue(selector, fallback) {
        var value = parseFloat($(selector).val());
        return isNaN(value) ? fallback : value;
    }

    function toView(value) {
        return Math.round(parseFloat(value || 0) * editScale);
    }

    function toModel(value) {
        return Math.round(parseFloat(value || 0) / editScale);
    }

    function alignValue(value) {
        return value === "center" || value === "right" ? value : "left";
    }

    function getBoxWidth(element) {
        return Math.round(element.outerWidth());
    }

    function getBoxHeight(element) {
        return Math.round(element.outerHeight());
    }

    function applyTypography() {
        $(".atm-field-item").each(function () {
            var key = $(this).data("key");
            var field = atmPositions.fields && atmPositions.fields[key] ? atmPositions.fields[key] : {};
            var fontSize = parseFloat(field.fontSize || $("#tableFontSize").val() || 6);
            var labelWidth = parseFloat(field.labelWidth || $("#tableLabelWidth").val() || 75);
            var color = field.fontColor || $("#tableFontColor").val();
            var labelColor = field.labelFontColor || field.fontColor || $("#tableFontColor").val();
            var valueColor = field.valueFontColor || field.fontColor || $("#tableFontColor").val();
            var labelViewWidth = field.showLabel === "none" ? 0 : toView(labelWidth);
            var valueViewWidth = Math.max(10, $(this).innerWidth() - labelViewWidth - toView(4) - 10);
            $(this).css({
                fontSize: (fontSize * editScale) + "px",
                lineHeight: Math.max((fontSize + 1) * editScale, Math.round(fontSize * 1.25 * editScale)) + "px",
                fontFamily: $("#tableFontFamily").val(),
                color: color,
                fontWeight: "300"
            });
            $(this).find(".atm-field-label")
                .text(field.label || $(this).find(".atm-field-label").text())
                .css({
                    width: labelViewWidth + "px",
                    display: field.showLabel === "none" ? "none" : "inline-block",
                    textAlign: alignValue(field.labelAlign),
                    color: labelColor,
                    fontWeight: field.labelFontWeight === "bold" ? "700" : "300",
                    textShadow: field.labelFontWeight === "bold" ? "0.35px 0 currentColor" : "none"
                });
            var previewValue = field.valueType === "tick" ? "✓" : ((field.showLabel === "none" || field.showColon === "none" ? "" : ": ") + "Sample");
            $(this).find(".atm-field-value").text(previewValue).css({
                width: valueViewWidth + "px",
                textAlign: alignValue(field.valueAlign),
                color: valueColor,
                fontFamily: field.valueType === "tick" ? "'Segoe UI Symbol','Arial Unicode MS','DejaVu Sans',Arial,sans-serif" : "inherit",
                fontWeight: field.fontWeight === "bold" ? "700" : "300",
                textShadow: field.fontWeight === "bold" ? "0.35px 0 currentColor" : "none"
            });
        });
    }

    function setFieldSelection(keys, activeKey) {
        var clean = [];
        $.each(keys || [], function (_, key) {
            key = String(key);
            if (fieldElement(key).length && clean.indexOf(key) === -1) clean.push(key);
        });
        if (!clean.length && activeKey && fieldElement(activeKey).length) clean = [String(activeKey)];
        selectedFieldKeys = clean;
        selectedFieldKey = activeKey && clean.indexOf(String(activeKey)) !== -1 ? String(activeKey) : (clean[0] || null);
        $(".atm-field-item").removeClass("atm-draggable-active atm-group-selected");
        $(".field-row").removeClass("is-selected");
        $.each(selectedFieldKeys, function (_, key) {
            fieldElement(key).addClass("atm-group-selected");
            $(".field-row[data-key='" + key + "']").addClass("is-selected");
        });
        if (selectedFieldKey) fieldElement(selectedFieldKey).addClass("atm-draggable-active");
        $("#atmSelectedCount").text(selectedFieldKeys.length + " selected");
    }

    function selectField(key, additive) {
        key = String(key);
        if (!fieldElement(key).length) return;
        if (additive) {
            var keys = selectedFieldKeys.slice();
            var index = keys.indexOf(key);
            if (index === -1) keys.push(key);
            else if (keys.length > 1) keys.splice(index, 1);
            setFieldSelection(keys, key);
        } else {
            setFieldSelection([key], key);
        }
        $("#selectedFieldName").text("Selected: " + $(".field-row[data-key='" + key + "'] label").text());
        syncSelectedFieldInputs();
        syncSelectedFieldStyleInputs();
    }

    function syncSelectedFieldInputs() {
        if (!selectedFieldKey) return;
        var $field = $("#atmField_" + selectedFieldKey);
        var pos = $field.position();
        $("#fieldTop").val(toModel(pos.top));
        $("#fieldLeft").val(toModel(pos.left));
        $("#fieldWidth").val(toModel(getBoxWidth($field)));
        $("#fieldHeight").val(toModel(getBoxHeight($field)));
    }

    function applySelectedFieldInputs() {
        if (!selectedFieldKey) return;
        $("#atmField_" + selectedFieldKey).css({
            top: toView(numericValue("#fieldTop", 0)) + "px",
            left: toView(numericValue("#fieldLeft", 0)) + "px",
            width: toView(numericValue("#fieldWidth", 80)) + "px",
            height: toView(numericValue("#fieldHeight", 10)) + "px"
        });
        syncSelectedFieldInputs();
    }

    function moveSelectedFields(dxModel, dyModel) {
        var dx = toView(dxModel || 0);
        var dy = toView(dyModel || 0);
        $.each(selectedFieldKeys, function (_, key) {
            var $field = fieldElement(key);
            if (!$field.length || !$field.is(":visible")) return;
            var pos = $field.position();
            var maxLeft = $("#canvas").innerWidth() - $field.outerWidth();
            var maxTop = $("#canvas").innerHeight() - $field.outerHeight();
            $field.css({
                left: clamp(pos.left + dx, 0, maxLeft),
                top: clamp(pos.top + dy, 0, maxTop)
            });
        });
        syncSelectedFieldInputs();
    }

    function alignSelectedFieldsLeft() {
        if (selectedFieldKeys.length < 2 || !selectedFieldKey) return;
        var $active = fieldElement(selectedFieldKey);
        if (!$active.length) return;
        var left = $active.position().left;
        $.each(selectedFieldKeys, function (_, key) {
            var $field = fieldElement(key);
            if ($field.length && $field.is(":visible")) $field.css({ left: left });
        });
        syncSelectedFieldInputs();
    }

    function syncSelectedFieldStyleInputs() {
        if (!selectedFieldKey) return;
        var field = atmPositions.fields && atmPositions.fields[selectedFieldKey] ? atmPositions.fields[selectedFieldKey] : {};
        $("#fieldLabelText").val(field.label || $(".field-row[data-key='" + selectedFieldKey + "'] label").text());
        $("#fieldShowLabel").val(field.showLabel === "none" ? "none" : "block");
        $("#fieldShowColon").val(field.showColon === "none" ? "none" : "block");
        $("#fieldLabelFontWeight").val(field.labelFontWeight === "bold" ? "bold" : "normal");
        $("#fieldFontWeight").val(field.fontWeight === "bold" ? "bold" : "normal");
        $("#fieldFontSize").val(field.fontSize || "");
        $("#fieldLabelWidth").val(field.labelWidth || "");
        $("#fieldLabelFontColor").val(field.labelFontColor || field.fontColor || $("#tableFontColor").val() || "#000000");
        $("#fieldValueFontColor").val(field.valueFontColor || field.fontColor || $("#tableFontColor").val() || "#000000");
        $("#fieldLabelAlign").val(alignValue(field.labelAlign));
        $("#fieldValueAlign").val(alignValue(field.valueAlign));
    }

    function applySelectedFieldStyleInputs() {
        if (!selectedFieldKey) return;
        if (!atmPositions.fields) atmPositions.fields = {};
        if (!atmPositions.fields[selectedFieldKey]) atmPositions.fields[selectedFieldKey] = {};
        var field = atmPositions.fields[selectedFieldKey];
        field.label = $("#fieldLabelText").val().trim() || $(".field-row[data-key='" + selectedFieldKey + "'] label").text();
        field.showLabel = $("#fieldShowLabel").val();
        field.showColon = $("#fieldShowColon").val();
        field.labelFontWeight = $("#fieldLabelFontWeight").val() === "bold" ? "bold" : "normal";
        field.fontWeight = $("#fieldFontWeight").val() === "bold" ? "bold" : "normal";
        field.fontSize = $("#fieldFontSize").val() ? numericValue("#fieldFontSize", "") : null;
        field.labelWidth = $("#fieldLabelWidth").val() ? numericValue("#fieldLabelWidth", "") : null;
        field.labelFontColor = $("#fieldLabelFontColor").val();
        field.valueFontColor = $("#fieldValueFontColor").val();
        field.labelAlign = alignValue($("#fieldLabelAlign").val());
        field.valueAlign = alignValue($("#fieldValueAlign").val());
        $("#atmField_" + selectedFieldKey).find(".atm-field-label").text(field.label);
        $(".field-row[data-key='" + selectedFieldKey + "'] label").text(field.label);
        applyTypography();
    }

    function updateMediaInputs() {
        var gemstonePos = $("#gemstone").position();
        $("#gemstoneTop").val(toModel(gemstonePos.top));
        $("#gemstoneLeft").val(toModel(gemstonePos.left));
        $("#gemstoneSize").val(toModel(getBoxWidth($("#gemstone"))));

        var qrcodePos = $("#qrcode").position();
        $("#qrcodeTop").val(toModel(qrcodePos.top));
        $("#qrcodeLeft").val(toModel(qrcodePos.left));
        $("#qrcodeSize").val(toModel(getBoxWidth($("#qrcode"))));
    }

    function extraImageById(id) {
        var found = null;
        $.each(atmPositions.additionalImages || [], function (_, img) {
            if (String(img.id) === String(id)) found = img;
        });
        return found;
    }

    function extraImageUrl(src) {
        return src ? src + (src.indexOf("?") === -1 ? "?v=" : "&v=") + Date.now() : "";
    }

    function selectExtraImage(id) {
        selectedExtraImageId = id ? String(id) : null;
        $(".atm-extra-image").removeClass("atm-extra-selected");
        if (selectedExtraImageId) $(".atm-extra-image[data-id='" + selectedExtraImageId + "']").addClass("atm-extra-selected");
        renderExtraImageList();
    }

    function initExtraImageBox(el) {
        $(el).draggable({ containment: "#canvas" }).resizable({ containment: "#canvas" }).on("click", function (event) {
            event.stopPropagation();
            selectExtraImage($(this).data("id"));
        });
    }

    function renderExtraImages() {
        $(".atm-extra-image").remove();
        $.each(atmPositions.additionalImages || [], function (_, img) {
            var box = $('<div class="draggable atm-extra-image" data-id=""></div>').attr("data-id", img.id).append($("<img>", { src: extraImageUrl(img.src), alt: img.label || "Extra Image" }));
            $("#canvas").append(box);
            box.css({
                left: toView(img.x || 0) + "px",
                top: toView(img.y || 0) + "px",
                width: toView(img.w || 40) + "px",
                height: toView(img.h || 40) + "px",
                display: img.display || "block"
            });
            initExtraImageBox(box);
        });
        renderExtraImageList();
    }

    function renderExtraImageList() {
        var list = $("#extraImagesList").empty();
        var images = atmPositions.additionalImages || [];
        if (!images.length) {
            list.append('<div class="text-muted" style="font-size:12px;">No additional images added.</div>');
            return;
        }
        $.each(images, function (index, img) {
            var row = $('<div class="field-row"></div>');
            row.append($("<label></label>").text(img.label || ("Image " + (index + 1))));
            var btn = $('<button type="button" class="btn btn-default btn-mini">Select</button>').toggleClass("btn-primary", String(img.id) === String(selectedExtraImageId));
            btn.on("click", function () { selectExtraImage(img.id); });
            row.append(btn);
            list.append(row);
        });
    }

    function applyPositions(data) {
        $("#tableFontSize").val(data.table.fontSize || 6);
        $("#tableLabelWidth").val(data.table.labelWidth || 75);
        $("#tableFontFamily").val(data.table.fontFamily || "Arial");
        $("#tableFontColor").val(data.table.fontColor || "#000000");

        $.each(data.fields || {}, function (key, field) {
            var $field = $("#atmField_" + key);
            if (!$field.length) return;
            if (!atmPositions.fields) atmPositions.fields = {};
            atmPositions.fields[key] = $.extend({}, atmPositions.fields[key] || {}, field);
            $field.css({
                top: toView(field.y) + "px",
                left: toView(field.x) + "px",
                width: toView(field.w) + "px",
                height: toView(field.h) + "px",
                display: field.display === "none" ? "none" : "block"
            });
            $(".atm-field-display[data-key='" + key + "']").val(field.display === "none" ? "none" : "block");
            $(".field-row[data-key='" + key + "'] label").text(field.label || $(".field-row[data-key='" + key + "'] label").text());
        });

        $("#gemstone").css({
            top: toView(data.gemstone.top) + "px",
            left: toView(data.gemstone.left) + "px",
            width: toView(data.gemstone.width) + "px",
            height: toView(data.gemstone.height) + "px",
            display: data.gemstone.display
        });
        $("#gemstoneDisplay").val(data.gemstone.display);

        $("#qrcode").css({
            top: toView(data.qrcode.top) + "px",
            left: toView(data.qrcode.left) + "px",
            width: toView(data.qrcode.width) + "px",
            height: toView(data.qrcode.height) + "px",
            display: data.qrcode.display
        });
        $("#qrcodeDisplay").val(data.qrcode.display);

        applyTypography();
        updateMediaInputs();
        atmPositions.additionalImages = data.additionalImages || [];
        renderExtraImages();
        selectField($(".atm-field-item:visible").first().data("key") || $(".atm-field-item").first().data("key"));
    }

    function collectPositions() {
        var positions = {
            table: {
                top: 0,
                left: 0,
                width: 0,
                height: 0,
                display: "block",
                fontSize: $("#tableFontSize").val(),
                rowSpacing: 0,
                labelWidth: $("#tableLabelWidth").val(),
                fontFamily: $("#tableFontFamily").val(),
                fontColor: $("#tableFontColor").val()
            },
            fields: {},
            gemstone: {
                top: toModel($("#gemstone").position().top),
                left: toModel($("#gemstone").position().left),
                width: toModel(getBoxWidth($("#gemstone"))),
                height: toModel(getBoxHeight($("#gemstone"))),
                display: $("#gemstoneDisplay").val()
            },
            qrcode: {
                top: toModel($("#qrcode").position().top),
                left: toModel($("#qrcode").position().left),
                width: toModel(getBoxWidth($("#qrcode"))),
                height: toModel(getBoxHeight($("#qrcode"))),
                display: $("#qrcodeDisplay").val()
            },
            additionalImages: []
        };

        $(".atm-field-item").each(function () {
            var key = $(this).data("key");
            var pos = $(this).position();
            positions.fields[key] = {
                label: (atmPositions.fields && atmPositions.fields[key] && atmPositions.fields[key].label) ? atmPositions.fields[key].label : $(this).find(".atm-field-label").text(),
                valueType: (atmPositions.fields && atmPositions.fields[key]) ? (atmPositions.fields[key].valueType || "") : "",
                display: $(".atm-field-display[data-key='" + key + "']").val(),
                showLabel: (atmPositions.fields && atmPositions.fields[key] && atmPositions.fields[key].showLabel) ? atmPositions.fields[key].showLabel : "block",
                showColon: (atmPositions.fields && atmPositions.fields[key] && atmPositions.fields[key].showColon === "none") ? "none" : "block",
                labelFontWeight: (atmPositions.fields && atmPositions.fields[key] && atmPositions.fields[key].labelFontWeight === "bold") ? "bold" : "normal",
                fontWeight: (atmPositions.fields && atmPositions.fields[key] && atmPositions.fields[key].fontWeight === "bold") ? "bold" : "normal",
                fontSize: (atmPositions.fields && atmPositions.fields[key]) ? (atmPositions.fields[key].fontSize || "") : "",
                fontColor: (atmPositions.fields && atmPositions.fields[key]) ? (atmPositions.fields[key].fontColor || "") : "",
                labelFontColor: (atmPositions.fields && atmPositions.fields[key]) ? (atmPositions.fields[key].labelFontColor || "") : "",
                valueFontColor: (atmPositions.fields && atmPositions.fields[key]) ? (atmPositions.fields[key].valueFontColor || "") : "",
                labelWidth: (atmPositions.fields && atmPositions.fields[key]) ? (atmPositions.fields[key].labelWidth || "") : "",
                labelAlign: (atmPositions.fields && atmPositions.fields[key]) ? alignValue(atmPositions.fields[key].labelAlign) : "left",
                valueAlign: (atmPositions.fields && atmPositions.fields[key]) ? alignValue(atmPositions.fields[key].valueAlign) : "left",
                x: toModel(pos.left),
                y: toModel(pos.top),
                w: toModel(getBoxWidth($(this))),
                h: toModel(getBoxHeight($(this)))
            };
        });

        $(".atm-extra-image").each(function (index) {
            var id = String($(this).data("id"));
            var img = extraImageById(id);
            if (!img) return;
            var pos = $(this).position();
            positions.additionalImages.push({
                id: id,
                label: img.label || ("Image " + (index + 1)),
                src: img.src,
                display: $(this).css("display") === "none" ? "none" : "block",
                x: toModel(pos.left),
                y: toModel(pos.top),
                w: toModel(getBoxWidth($(this))),
                h: toModel(getBoxHeight($(this)))
            });
        });

        return positions;
    }

    applyPositions(atmPositions);

    $(".atm-field-item").on("click", function (event) {
        selectField($(this).data("key"), event.ctrlKey || event.shiftKey || event.metaKey);
    });

    $(".field-row label").on("click", function (event) {
        selectField($(this).closest(".field-row").data("key"), event.ctrlKey || event.shiftKey || event.metaKey);
    });

    $(".atm-field-display").on("change", function () {
        var key = $(this).data("key");
        $("#atmField_" + key).toggle($(this).val() !== "none");
        if ($(this).val() !== "none") {
            selectField(key);
        }
    });

    $("#fieldTop, #fieldLeft, #fieldWidth, #fieldHeight").on("input change", applySelectedFieldInputs);
    $("#moveAtmSelected").on("click", function () {
        moveSelectedFields(numericValue("#atmNudgeX", 0), numericValue("#atmNudgeY", 0));
    });
    $("#selectAllAtmFields").on("click", function () {
        var keys = visibleFieldKeys();
        if (keys.length) {
            selectField(keys[0]);
            setFieldSelection(keys, keys[0]);
        }
    });
    $("#clearAtmSelection").on("click", function () {
        if (selectedFieldKey) setFieldSelection([selectedFieldKey], selectedFieldKey);
    });
    $("#alignAtmSelectedLeft").on("click", alignSelectedFieldsLeft);
    $("#fieldLabelText, #fieldShowLabel, #fieldShowColon, #fieldLabelFontWeight, #fieldFontWeight, #fieldFontSize, #fieldLabelWidth, #fieldLabelFontColor, #fieldValueFontColor, #fieldLabelAlign, #fieldValueAlign").on("input change", applySelectedFieldStyleInputs);
    $("#clearFieldStyle").on("click", function () {
        if (!selectedFieldKey || !atmPositions.fields || !atmPositions.fields[selectedFieldKey]) return;
        atmPositions.fields[selectedFieldKey].fontSize = null;
        atmPositions.fields[selectedFieldKey].fontColor = "";
        atmPositions.fields[selectedFieldKey].labelFontColor = "";
        atmPositions.fields[selectedFieldKey].valueFontColor = "";
        atmPositions.fields[selectedFieldKey].labelWidth = null;
        atmPositions.fields[selectedFieldKey].showLabel = "block";
        atmPositions.fields[selectedFieldKey].showColon = "block";
        atmPositions.fields[selectedFieldKey].labelFontWeight = "normal";
        atmPositions.fields[selectedFieldKey].fontWeight = "normal";
        atmPositions.fields[selectedFieldKey].labelAlign = "left";
        atmPositions.fields[selectedFieldKey].valueAlign = "left";
        syncSelectedFieldStyleInputs();
        applyTypography();
    });
    $("#tableFontSize, #tableLabelWidth, #tableFontFamily, #tableFontColor").on("input change", applyTypography);

    $("#gemstoneTop, #gemstoneLeft, #gemstoneDisplay, #gemstoneSize").on("input change", function () {
        $("#gemstone").css({
            top: toView(numericValue("#gemstoneTop", 0)) + "px",
            left: toView(numericValue("#gemstoneLeft", 0)) + "px",
            width: toView(numericValue("#gemstoneSize", 40)) + "px",
            height: toView(numericValue("#gemstoneSize", 40)) + "px",
            display: $("#gemstoneDisplay").val()
        });
        $("#gemstone img").css({ width: "100%", height: "100%" });
        updateMediaInputs();
    });

    $("#qrcodeTop, #qrcodeLeft, #qrcodeDisplay, #qrcodeSize").on("input change", function () {
        $("#qrcode").css({
            top: toView(numericValue("#qrcodeTop", 0)) + "px",
            left: toView(numericValue("#qrcodeLeft", 0)) + "px",
            width: toView(numericValue("#qrcodeSize", 32)) + "px",
            height: toView(numericValue("#qrcodeSize", 32)) + "px",
            display: $("#qrcodeDisplay").val()
        });
        updateMediaInputs();
    });

    $("#uploadExtraImage").on("click", function () {
        var file = $("#extraImageUpload")[0].files[0];
        if (!file) {
            showMessage("error", "Please select an image first.");
            return;
        }
        var form = new FormData();
        form.append("image", file);
        form.append("builder", "atm");
        form.append("report_type", atmBuilderType);
        $.ajax({
            url: "report-builder-image-upload.php",
            method: "POST",
            data: form,
            processData: false,
            contentType: false,
            dataType: "json"
        }).done(function (response) {
            if (response.status !== "success") {
                showMessage("error", response.message || "Unable to upload image.");
                return;
            }
            if (!atmPositions.additionalImages) atmPositions.additionalImages = [];
            var image = response.image;
            image.x = 20;
            image.y = 20;
            image.w = 40;
            image.h = 40;
            image.display = "block";
            atmPositions.additionalImages.push(image);
            renderExtraImages();
            selectExtraImage(image.id);
            $("#extraImageUpload").val("");
            showMessage("success", "Image added. Drag it on the card and save layout.");
        }).fail(function () {
            showMessage("error", "Unable to upload image.");
        });
    });

    $("#deleteExtraImage").on("click", function () {
        if (!selectedExtraImageId) return;
        atmPositions.additionalImages = $.grep(atmPositions.additionalImages || [], function (image) {
            return String(image.id) !== String(selectedExtraImageId);
        });
        selectedExtraImageId = null;
        renderExtraImages();
    });

    $("#savePositions").click(function () {
        var $btn = $(this);
        $btn.prop("disabled", true).text("Saving...");
        $.ajax({ url: "save_positions.php", method: "POST", dataType: "json", data: { report_type: atmBuilderType, positions: JSON.stringify(collectPositions()) } })
            .done(function (response) {
                if (response.status === "success") showMessage("success", "ATM layout saved successfully.");
                else showMessage("error", response.message || "Unable to save positions.");
            })
            .fail(function () { showMessage("error", "Unable to save positions."); })
            .always(function () { $btn.prop("disabled", false).text("Save ATM Layout"); });
    });

    $("#resetPreview").on("click", function () {
        $.getJSON("user_settings_json.php?file=positions&report_type=" + encodeURIComponent(atmBuilderType), function (data) {
            if (data) applyPositions(data);
        });
    });
    $("#atmBuilderType").on("change", function () {
        window.location.href = "settings.php?type=" + encodeURIComponent(this.value);
    });
});

</script>
<?php include "assets/footer.php"; ?>
