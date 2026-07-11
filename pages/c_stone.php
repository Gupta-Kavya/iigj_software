<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'master_data_helper.php';
require_once 'atm_config.php';
include "assets/navbar.php";

function cstone_page_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function cstone_page_treatment_ready($conn)
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

function cstone_page_treatment_rows($conn, $userId)
{
    cstone_page_treatment_ready($conn);
    $rows = [];
    $sql = "SELECT comment_title, description FROM sm_master_treatment_comment WHERE " . master_scope_sql($userId) . " AND active = 1 ORDER BY CASE WHEN user_id = " . (int) $userId . " THEN 0 ELSE 1 END, comment_title, id";
    $result = @$conn->query($sql);
    if (!$result) {
        return $rows;
    }
    $seen = [];
    while ($row = $result->fetch_assoc()) {
        $key = strtolower(trim((string) $row['comment_title']));
        if ($key === '' || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $rows[] = $row;
    }
    return $rows;
}

function cstone_page_general_comment_ready($conn)
{
    @$conn->query("CREATE TABLE IF NOT EXISTS `sm_master_general_comment` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL DEFAULT 1,
        `comment_title` varchar(120) NOT NULL,
        `description` varchar(500) DEFAULT NULL,
        `active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_general_comment_user` (`user_id`, `active`, `comment_title`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function cstone_page_general_comment_rows($conn, $userId)
{
    cstone_page_general_comment_ready($conn);
    $rows = [];
    $sql = "SELECT comment_title, description FROM sm_master_general_comment WHERE " . master_scope_sql($userId) . " AND active = 1 ORDER BY CASE WHEN user_id = " . (int) $userId . " THEN 0 ELSE 1 END, comment_title, id";
    $result = @$conn->query($sql);
    if (!$result) {
        return $rows;
    }
    $seen = [];
    while ($row = $result->fetch_assoc()) {
        $key = strtolower(trim((string) $row['comment_title']));
        if ($key === '' || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $rows[] = $row;
    }
    return $rows;
}

$cstoneUserId = auth_current_user_id();
$cstoneTreatmentRows = cstone_page_treatment_rows($conn, $cstoneUserId);
$cstoneGeneralCommentRows = cstone_page_general_comment_rows($conn, $cstoneUserId);
cstone_report_type_master_ready($conn);
$cstoneFeedType = defined('CSTONE_FEED_TYPE') ? strtoupper((string) CSTONE_FEED_TYPE) : 'S';
$cstoneFeedType = in_array($cstoneFeedType, ['S', 'P'], true) ? $cstoneFeedType : 'S';
$cstoneFeedLabel = defined('CSTONE_FEED_LABEL') ? (string) CSTONE_FEED_LABEL : ($cstoneFeedType === 'P' ? 'Pearl' : 'Colour Stone');
$cstoneFeedTitle = $cstoneFeedLabel . ' Feeding';
$cstoneReportTypeRows = cstone_report_type_rows($conn, $cstoneUserId, true, $cstoneFeedType);
?>

<style>
    .image_area { position: relative; }
    img { display: block; max-width: 100%; }
    .preview, #campreview {
        overflow: hidden;
        width: 160px;
        height: 160px;
        margin: 10px;
        border: 1px solid #ececf1;
        border-radius: 8px;
    }
    .overlay {
        position: absolute;
        bottom: 10px;
        left: 0;
        right: 0;
        background-color: rgba(255, 255, 255, 0.5);
        overflow: hidden;
        height: 0;
        transition: .5s ease;
        width: 100%;
    }
    .image_area:hover .overlay { height: 50%; cursor: pointer; }
    .text {
        color: #333;
        font-size: 20px;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
    }

    .cstone-page { padding-bottom: 24px; }

    .cstone-header {
        border-bottom: 1px solid #ececf1;
        margin-bottom: 14px;
        padding-bottom: 12px;
    }

    .cstone-header h1 {
        border: 0;
        font-size: 24px;
        font-weight: 600;
        margin: 0 0 4px;
        padding: 0;
    }

    .cstone-header p {
        color: #737373;
        font-size: 13px;
        margin: 0;
    }

    .cstone-layout {
        align-items: start;
        display: grid;
        gap: 10px;
        grid-template-columns: minmax(0, 1fr) 300px;
    }

    .cstone-main {
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-width: 0;
    }

    .cstone-cert-strip {
        align-items: center;
        background: #fff;
        border: 1px solid #ececf1;
        border-radius: 8px;
        display: grid;
        gap: 10px;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        padding: 9px 10px;
    }

    .cstone-section {
        background: #fff;
        border: 1px solid #ececf1;
        border-radius: 8px;
        padding: 9px;
    }

    .cstone-section-title {
        align-items: center;
        border-bottom: 1px solid #ececf1;
        color: #171717;
        display: flex;
        font-size: 13px;
        font-weight: 600;
        gap: 8px;
        margin: -9px -9px 8px;
        padding: 8px 9px;
    }

    .cstone-section-title i {
        color: #737373;
        font-size: 13px;
    }

    .cstone-field-grid {
        display: grid;
        gap: 7px 8px;
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }

    .cstone-field-grid.single-col {
        grid-template-columns: 1fr;
    }

    .cstone-field-grid.two-col {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .cstone-field-grid.three-col {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .cstone-field-grid .span-2 { grid-column: span 2; }
    .cstone-field-grid .span-3 { grid-column: span 3; }
    .cstone-field-grid .span-4 { grid-column: 1 / -1; }
    .cstone-cert-strip .span-4 { grid-column: 1 / -1; }

    .cstone-field label {
        color: #404040;
        display: block;
        font-size: 11px;
        font-weight: 500;
        margin-bottom: 2px;
    }

    .cstone-field .form-control {
        border-radius: 6px;
        font-size: 12px;
        height: 28px;
        min-height: 28px;
        padding: 4px 7px;
        width: 100%;
    }

    .cstone-field.has-error .form-control {
        border-color: #dc2626;
    }

    .cstone-field.has-error label {
        color: #dc2626;
    }

    .cstone-field textarea.form-control {
        height: auto;
        min-height: 46px;
        padding-top: 5px;
        padding-bottom: 5px;
        resize: vertical;
    }

    .cstone-field textarea.cstone-compact-textarea {
        min-height: 42px;
    }

    .cstone-field .input-group .form-control:first-child {
        border-bottom-left-radius: 8px;
        border-top-left-radius: 8px;
    }

    .cstone-field .input-group-btn .form-control {
        border-bottom-right-radius: 8px;
        border-top-right-radius: 8px;
        min-width: 62px;
    }

    .certi-live-status {
        color: #737373;
        display: block;
        font-size: 11px;
        margin-top: 5px;
    }

    .certi-live-status.synced { color: #15803d; }
    .certi-live-status.updated { color: #b45309; font-weight: 600; }
    .certificate-result {
        align-items: center;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 8px;
        color: #166534;
        display: none;
        gap: 9px;
        margin-top: 8px;
        padding: 9px 11px;
    }
    .certificate-result strong { color: #14532d; font-size: 15px; }

    .cstone-action-bar {
        align-items: center;
        background: #fff;
        border: 1px solid #ececf1;
        border-radius: 10px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        padding: 10px 12px;
    }

    .cstone-action-bar .btn {
        min-height: 36px;
    }

    .cstone-submit-btn {
        background: #171717;
        border-color: #171717;
        color: #fff;
    }

    .cstone-submit-btn:hover,
    .cstone-submit-btn:focus {
        background: #404040;
        border-color: #404040;
        color: #fff;
    }

    .cstone-action-bar .btn-spacer {
        flex: 1;
    }

    .cstone-aside {
        position: sticky;
        top: 76px;
    }

    .cstone-image-card {
        background: #fff;
        border: 1px solid #ececf1;
        border-radius: 10px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 14px;
    }

    .cstone-image-card .section-title {
        align-items: center;
        color: #171717;
        display: flex;
        font-size: 14px;
        font-weight: 600;
        gap: 8px;
        margin: 0;
    }

    .cstone-image-card .section-title i { color: #737373; }

    .cstone-page #st_image_canvas { display: none; }

    .cstone-page #fetched_image {
        align-items: center;
        background: #f7f7f8;
        border: 1px dashed #d4d4d4;
        border-radius: 8px;
        display: flex;
        justify-content: center;
        min-height: 170px;
        overflow: hidden;
        width: 100%;
    }

    .cstone-page #fetched_image:empty::before {
        color: #a3a3a3;
        content: "No image loaded";
        font-size: 13px;
    }

    .cstone-page #fetched_image:not(:empty) {
        background: #fff;
        border-style: solid;
        border-color: #ececf1;
    }

    .cstone-page #fetched_image img {
        border-radius: 6px;
        height: auto !important;
        max-height: 220px;
        object-fit: contain;
        width: 100% !important;
    }

    .cstone-page #fetch_image {
        min-height: 40px;
        width: 100%;
    }

    .cstone-image-actions {
        display: grid;
        gap: 8px;
        grid-template-columns: 1fr 1fr;
    }

    .cstone-radio-inline {
        align-items: center;
        border: 1px solid #ececf1;
        border-radius: 6px;
        display: grid;
        gap: 8px;
        grid-template-columns: minmax(0, 1.35fr) minmax(0, .8fr);
        height: 28px;
        min-height: 28px;
        padding: 4px 7px;
    }

    .cstone-radio-inline label {
        align-items: center;
        display: flex;
        gap: 4px;
        min-width: 0;
        white-space: nowrap;
    }

    .cstone-radio-inline label,
    .cstone-test-grid label {
        font-size: 11px;
        font-weight: 500;
        margin: 0;
    }

    .cstone-test-grid {
        display: grid;
        gap: 5px 8px;
        grid-template-columns: repeat(6, minmax(0, 1fr));
    }

    .cstone-measure-grid {
        gap: 5px 7px;
        grid-template-columns: repeat(6, minmax(0, 1fr));
    }

    .cstone-measure-grid .cstone-field label {
        font-size: 11px;
        margin-bottom: 2px;
    }

    .cstone-measure-grid .form-control {
        height: 27px;
        min-height: 27px;
        padding: 3px 6px;
    }

    .cstone-stone-weight-control {
        display: grid;
        gap: 4px;
        grid-template-columns: minmax(0, 1fr) 58px;
    }

    .cstone-stone-weight-control .form-control {
        min-width: 0;
    }

    .cstone-stone-weight-control select.form-control {
        padding-left: 4px;
        padding-right: 4px;
    }

    .cstone-test-grid label {
        align-items: center;
        display: flex;
        gap: 6px;
    }

    .cstone-image-actions .btn {
        font-size: 12px;
        min-height: 38px;
        padding: 8px 10px;
        white-space: normal;
    }

    .cstone-page .alert-warning {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 8px;
        color: #92400e;
        font-size: 12px;
        line-height: 1.45;
        margin: 0;
        padding: 10px 12px;
    }

    .cstone-page .alert-warning ul {
        margin: 8px 0 0;
        padding-left: 18px;
    }

    .cstone-copy-block {
        border-top: 1px solid #ececf1;
        margin-top: 4px;
        padding-top: 14px;
    }

    .cstone-copy-block label {
        color: #404040;
        display: block;
        font-size: 12px;
        font-weight: 500;
        margin-bottom: 6px;
    }

    .copy-field-row {
        display: grid;
        gap: 8px;
        grid-template-columns: 1fr auto;
    }

    .copy-field-row .form-control {
        border-radius: 8px;
        min-height: 38px;
        width: 100% !important;
    }

    .copy-field-row .btn {
        min-height: 38px;
        white-space: nowrap;
    }

    #field_copier_validation {
        color: #dc2626;
        font-size: 12px;
        grid-column: 1 / -1;
        min-height: 16px;
    }

    @media (max-width: 1100px) {
        .cstone-layout {
            grid-template-columns: 1fr;
        }

        .cstone-aside {
            position: static;
        }
    }

    @media (max-width: 640px) {
        .cstone-cert-strip,
        .cstone-field-grid,
        .cstone-field-grid.two-col,
        .cstone-field-grid.three-col,
        .cstone-test-grid,
        .cstone-image-actions {
            grid-template-columns: 1fr;
        }

        .cstone-field-grid .span-2,
        .cstone-field-grid .span-3,
        .cstone-field-grid .span-4 {
            grid-column: auto;
        }

        .cstone-action-bar {
            flex-direction: column;
            align-items: stretch;
        }

        .cstone-action-bar .btn-spacer {
            display: none;
        }
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid cstone-page">
        <div class="cstone-header">
            <h1><i class="fa fa-diamond"></i> <?php echo cstone_page_h($cstoneFeedTitle); ?></h1>
            <p>Enter certificate details, attach the stone image, and submit to save the <?php echo cstone_page_h(strtolower($cstoneFeedLabel)); ?> report.</p>
        </div>

        <form id="cstone_feed" name="form_stone" data-feed-type="<?php echo cstone_page_h($cstoneFeedType); ?>" data-feed-label="<?php echo cstone_page_h($cstoneFeedLabel); ?>">
            <input type="hidden" name="report_type" value="<?php echo cstone_page_h($cstoneFeedType); ?>">
            <input type="hidden" name="upload_token" id="upload_token" value="">
            <input type="hidden" name="certi_no" id="certi_no" value="">
            <input type="hidden" name="test_carried_out" id="test_carried_out" value="">

            <div class="cstone-layout">
                <div class="cstone-main">

                    <div class="cstone-cert-strip">
                        <div class="cstone-field">
                            <label for="agreement_no">Agreement No</label>
                            <input class="form-control" type="number" min="0" name="agreement_no" id="agreement_no">
                        </div>
                        <div class="cstone-field">
                            <label for="certi_display">Certi No</label>
                            <input class="form-control" type="number" min="1" name="certificate_no" id="certi_display">
                        </div>
                        <div class="cstone-field" id="error_class_date">
                            <label for="date">Date</label>
                            <input class="form-control" type="date" name="date" id="date">
                        </div>
                        <div class="cstone-field">
                            <label for="report_type_text">Report Type</label>
                            <select class="form-control" name="report_type_id" id="report_type_id">
                                <option value="">Select report type</option>
                                <?php foreach ($cstoneReportTypeRows as $row): ?>
                                    <option value="<?php echo (int) $row['id']; ?>" data-format="<?php echo cstone_page_h($row['report_format'] ?? 'a4'); ?>"><?php echo cstone_page_h($row['report_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="report_type_text" id="report_type_text" value="">
                            <input type="hidden" name="report_format" id="report_format" value="a4">
                        </div>
                        <div class="certificate-result span-4" id="assigned_certificate_result">
                            <i class="fa fa-check-circle"></i>
                            <span>Booked ref / saved certificate: <strong id="assigned_certificate_no"></strong></span>
                        </div>
                    </div>

                    <div class="cstone-section">
                        <div class="cstone-section-title"><i class="fa fa-tag"></i> Basic Details</div>
                        <div class="cstone-field-grid">
                            <div class="cstone-field span-2">
                                <label for="item_desc">Item Desc.</label>
                                <textarea class="form-control" rows="2" name="item_desc" id="item_desc"></textarea>
                            </div>
                            <div class="cstone-field">
                                <label for="gross_weight">Gross Weight</label>
                                <div class="input-group">
                                    <input class="form-control" type="text" name="gross_weight" id="gross_weight">
                                    <span class="input-group-btn">
                                        <select class="form-control" name="gross_unit" id="gross_unit">
                                            <option>ct</option>
                                            <option>gm</option>
                                            <option>pcs</option>
                                        </select>
                                    </span>
                                </div>
                            </div>
                            <div class="cstone-field" id="error_class_colour">
                                <label for="colour">Colour</label>
                                <input class="form-control" type="text" name="colour" id="colour" list="colour_master" autocomplete="off">
                            </div>
                            <div class="cstone-field" id="error_class_shape_cut">
                                <label for="shape_cut">Shape/Cut</label>
                                <input class="form-control" type="text" name="shape_cut" id="shape_cut" list="shpe_cut" autocomplete="off">
                            </div>
                            <div class="cstone-field">
                                <label for="stone_pcs">Tested Pcs</label>
                                <input class="form-control" type="number" min="0" name="stone_pcs" id="stone_pcs">
                            </div>
                            <div class="cstone-field span-2">
                                <label for="tested_pcs_remark">Remark For Tested Pcs</label>
                                <input class="form-control" type="text" name="tested_pcs_remark" id="tested_pcs_remark">
                            </div>
                        </div>
                    </div>

                    <datalist id="stone_namee">
                        <?php
                        include("db_connect.php");
                        foreach (master_fetch_rows($conn, 'sm_master_stone_name', ['stone_name'], auth_current_user_id(), ['stone_name'], 'stone_name') as $row) {
                            echo '<option value="' . htmlspecialchars($row['stone_name']) . '">';
                        }
                        $conn->close();
                        ?>
                    </datalist>

                    <div class="cstone-section">
                        <div class="cstone-section-title"><i class="fa fa-arrows"></i> Stone Weights &amp; Measurements</div>
                        <div class="cstone-field-grid cstone-measure-grid">
                            <div class="cstone-field" id="error_class_stone_weight">
                                <label for="stone_weight_1">Stone Wt. 1</label>
                                <div class="cstone-stone-weight-control">
                                    <input type="text" class="form-control" placeholder="0.00" id="stone_weight_1" name="stone_weight_1">
                                    <select class="form-control cstone-weight-unit" name="stone_weight_unit_1" id="stone_weight_unit_1">
                                        <option value="ct">ct</option>
                                        <option value="gms">gms</option>
                                        <option value="kg">kg</option>
                                        <option value="pcs">pcs</option>
                                    </select>
                                </div>
                            </div>
                            <div class="cstone-field">
                                <label for="measurement_1">Measurement 1</label>
                                <input class="form-control" type="text" name="measurement_1" id="measurement_1" autocomplete="off">
                            </div>
                            <div class="cstone-field">
                                <label for="stone_weight_2">Stone Wt. 2</label>
                                <div class="cstone-stone-weight-control">
                                    <input class="form-control" type="text" name="stone_weight_2" id="stone_weight_2" placeholder="0.00">
                                    <select class="form-control cstone-weight-unit" name="stone_weight_unit_2" id="stone_weight_unit_2">
                                        <option value="ct">ct</option>
                                        <option value="gms">gms</option>
                                        <option value="kg">kg</option>
                                        <option value="pcs">pcs</option>
                                    </select>
                                </div>
                            </div>
                            <div class="cstone-field">
                                <label for="measurement_2">Measurement 2</label>
                                <input class="form-control" type="text" name="measurement_2" id="measurement_2" autocomplete="off">
                            </div>
                            <?php for ($i = 3; $i <= 5; $i++): ?>
                                <div class="cstone-field">
                                    <label for="stone_weight_<?php echo $i; ?>">Stone Wt. <?php echo $i; ?></label>
                                    <div class="cstone-stone-weight-control">
                                        <input class="form-control" type="text" name="stone_weight_<?php echo $i; ?>" id="stone_weight_<?php echo $i; ?>">
                                        <select class="form-control cstone-weight-unit" name="stone_weight_unit_<?php echo $i; ?>" id="stone_weight_unit_<?php echo $i; ?>">
                                            <option value="ct">ct</option>
                                            <option value="gms">gms</option>
                                            <option value="kg">kg</option>
                                            <option value="pcs">pcs</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="cstone-field">
                                    <label for="measurement_<?php echo $i; ?>">Measurement <?php echo $i; ?></label>
                                    <input class="form-control" type="text" name="measurement_<?php echo $i; ?>" id="measurement_<?php echo $i; ?>">
                                </div>
                            <?php endfor; ?>
                            <div class="cstone-field">
                                <label for="length_tested">Length Tested</label>
                                <input class="form-control" type="text" name="length_tested" id="length_tested">
                            </div>
                        </div>
                    </div>

                    <datalist id="shpe_cut">
                        <?php
                        include("db_connect.php");
                        foreach (master_fetch_rows($conn, 'sm_master_shape_cut', ['shape_cut'], auth_current_user_id(), ['shape_cut'], 'shape_cut') as $row) {
                            echo '<option value="' . htmlspecialchars($row['shape_cut']) . '">';
                        }
                        $conn->close();
                        ?>
                    </datalist>

                    <datalist id="colour_master">
                        <?php
                        include("db_connect.php");
                        foreach (master_fetch_rows($conn, 'sm_master_colour', ['colour'], auth_current_user_id(), ['colour'], 'colour') as $row) {
                            echo '<option value="' . htmlspecialchars($row['colour']) . '">';
                        }
                        $conn->close();
                        ?>
                    </datalist>

                    <div class="cstone-section">
                        <div class="cstone-section-title"><i class="fa fa-flask"></i> Identification &amp; Tests</div>
                        <div class="cstone-field-grid">
                            <div class="cstone-field">
                                <label for="optic_char">Optic Character</label>
                                <input class="form-control" type="text" name="optic_char" id="optic_char">
                            </div>
                            <div class="cstone-field">
                                <label for="ri">Refractive Index</label>
                                <input class="form-control" type="text" name="ri" id="ri" list="ri_master" autocomplete="off">
                            </div>
                            <div class="cstone-field">
                                <label for="specefic_grav">Specific Gravity</label>
                                <input class="form-control" type="text" name="specefic_grav" id="specefic_grav">
                            </div>
                            <div class="cstone-field">
                                <label for="magnification">Magnification</label>
                                <input class="form-control" type="text" name="magnification" id="magnification" list="magni_master">
                            </div>
                            <div class="cstone-field">
                                <label for="hardness">Hardness</label>
                                <input class="form-control" type="text" name="hardness" id="hardness">
                            </div>
                            <div class="cstone-field span-2">
                                <label>Mode</label>
                                <div class="cstone-radio-inline">
                                    <label><input type="radio" name="species_mode" value="Species/Variety" checked> Species/Variety</label>
                                    <label><input type="radio" name="species_mode" value="Others"> Others</label>
                                </div>
                            </div>
                            <div class="cstone-field" id="error_class_stone_name">
                                <label for="stone_name">Variety</label>
                                <input class="form-control" type="text" name="stone_name" id="stone_name" list="stone_namee" onchange="stoneNameMaster()" autocomplete="off">
                            </div>
                            <div class="cstone-field">
                                <label for="species_grp">Group/Species</label>
                                <input class="form-control" type="text" name="species_grp" id="species_grp">
                            </div>
                            <div class="cstone-field">
                                <label for="origin">Origin</label>
                                <input class="form-control" type="text" name="origin" id="origin">
                            </div>
                            <!-- <div class="cstone-field">
                                <label for="ebay_prod_no">Ebay Prod No.</label> 
                                <input class="form-control" type="text" name="ebay_prod_no" id="ebay_prod_no">
                            </div> -->
                            <div class="cstone-field span-4">
                                <label>Test Carried Out</label>
                                <div class="cstone-test-grid">
                                    <label><input type="checkbox" name="tests[]" value="RI" data-column="tri"> RI</label>
                                    <label><input type="checkbox" name="tests[]" value="SG" data-column="tsg"> S G</label>
                                    <label><input type="checkbox" name="tests[]" value="MAGNIFICATION" data-column="tmag"> Magnification</label>
                                    <label><input type="checkbox" name="tests[]" value="UV FLUORESCENCE" data-column="tuvf"> UV Fluorescence</label>
                                    <label><input type="checkbox" name="tests[]" value="ABS SPECTRUM" data-column="tabs"> ABS Spectrum</label>
                                    <label><input type="checkbox" name="tests[]" value="IR SPECTRUM" data-column="tirs"> IR Spectrum</label>
                                    <label><input type="checkbox" name="tests[]" value="EDXRF" data-column="tedxrf"> EDXRF</label>
                                    <label><input type="checkbox" name="tests[]" value="LRS" data-column="tlrs"> LRS</label>
                                    <label><input type="checkbox" name="tests[]" value="UV-VIS-NIR" data-column="tuvnir"> UV-VIS-NIR</label>
                                    <label><input type="checkbox" name="tests[]" value="LA-ICPMS" data-column="tlaicpms"> LA-ICPMS</label>
                                    <label><input type="checkbox" name="tests[]" value="X-RADIOGRAPHY" data-column="txray"> X-Radiography</label>
                                    <label><input type="checkbox" name="tests[]" value="UV-IMAGING" data-column="tuvimg"> UV-Imaging</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <datalist id="ri_master">
                        <?php
                        include("db_connect.php");
                        foreach (master_fetch_rows($conn, 'sm_master_ri', ['ri'], auth_current_user_id(), ['ri'], 'ri') as $row) {
                            echo '<option value="' . htmlspecialchars($row['ri']) . '">';
                        }
                        $conn->close();
                        ?>
                    </datalist>

                    <datalist id="magni_master">
                        <?php
                        include("db_connect.php");
                        foreach (master_fetch_rows($conn, 'sm_master_magnification', ['magni'], auth_current_user_id(), ['magni'], 'magni') as $row) {
                            echo '<option value="' . htmlspecialchars($row['magni']) . '">';
                        }
                        $conn->close();
                        ?>
                    </datalist>

                    <div class="cstone-section">
                        <div class="cstone-section-title"><i class="fa fa-comment-o"></i> Treatment Comments &amp; Remarks</div>
                        <div class="cstone-field-grid two-col">
                            <div class="cstone-field">
                                <label for="treatment_comment_title">Treatment Comment 1</label>
                                <select class="form-control" name="treatment_comment_title" id="treatment_comment_title">
                                    <option value="">Select comment</option>
                                    <?php foreach ($cstoneTreatmentRows as $row): ?>
                                        <option value="<?php echo cstone_page_h($row['comment_title']); ?>" data-description="<?php echo cstone_page_h($row['description'] ?? ''); ?>"><?php echo cstone_page_h($row['comment_title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="cstone-field">
                                <label for="treatment_comment_desc">Description 1</label>
                                <textarea class="form-control cstone-compact-textarea" rows="2" name="treatment_comment_desc" id="treatment_comment_desc"></textarea>
                            </div>
                            <div class="cstone-field">
                                <label for="treatment_comment_title_2">Treatment Comment 2</label>
                                <select class="form-control" name="treatment_comment_title_2" id="treatment_comment_title_2">
                                    <option value="">Select comment</option>
                                    <?php foreach ($cstoneTreatmentRows as $row): ?>
                                        <option value="<?php echo cstone_page_h($row['comment_title']); ?>" data-description="<?php echo cstone_page_h($row['description'] ?? ''); ?>"><?php echo cstone_page_h($row['comment_title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="cstone-field">
                                <label for="treatment_comment_desc_2">Description 2</label>
                                <textarea class="form-control cstone-compact-textarea" rows="2" name="treatment_comment_desc_2" id="treatment_comment_desc_2"></textarea>
                            </div>
                            <div class="cstone-field">
                                <label for="general_comment_title">General Comment</label>
                                <select class="form-control" name="general_comment_title" id="general_comment_title">
                                    <option value="">Select comment</option>
                                    <?php foreach ($cstoneGeneralCommentRows as $row): ?>
                                        <option value="<?php echo cstone_page_h($row['comment_title']); ?>" data-description="<?php echo cstone_page_h($row['description'] ?? ''); ?>"><?php echo cstone_page_h($row['comment_title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="cstone-field span-3" id="error_class_comments">
                                <label for="comments">Description</label>
                                <textarea class="form-control cstone-compact-textarea" rows="2" id="comments" name="comments"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="cstone-action-bar">
                        <button class="btn cstone-submit-btn" id="form_submit" type="submit"><i class="fa fa-check"></i> Submit Form</button>
                        <button type="reset" class="btn btn-default"><i class="fa fa-refresh"></i> Reset</button>
                        <span class="btn-spacer"></span>
                        <button class="btn btn-default" type="button" data-toggle="modal" data-target="#up_from_folder"><i class="fa fa-folder-open-o"></i> Upload Image</button>
                        <button class="btn btn-default" type="button" id="snap_from_camera" data-toggle="modal" data-target="#camera_modal"><i class="fa fa-camera"></i> Camera</button>
                    </div>
                </div>

                <aside class="cstone-aside">
                    <div class="cstone-image-card">
                        <div class="section-title"><i class="fa fa-picture-o"></i> Stone Image</div>
                        <canvas id="st_image_canvas"></canvas>
                        <div id="fetched_image"></div>
                        <button class="btn btn-danger btn-block" type="button" id="fetch_image"><i class="fa fa-download"></i> Fetch Image</button>
                        <div class="cstone-image-actions">
                            <button class="btn btn-default" type="button" data-toggle="modal" data-target="#up_from_folder"><i class="fa fa-folder-open-o"></i> Upload</button>
                            <button class="btn btn-default" type="button" data-toggle="modal" data-target="#camera_modal"><i class="fa fa-camera"></i> Camera</button>
                        </div>
                        <!-- <div class="alert alert-warning">
                            <strong>Image from folder</strong>
                            <ul>
                                <li>Click <strong>Fetch Image</strong> or press <kbd>F8</kbd> to load the uploaded image.</li>
                            </ul>
                        </div> -->
                    </div>
                </aside>
            </div>
        </form>
    </div>
</div>

<!--upload from folder modal -->
<!-- Modal -->
<div id="up_from_folder" class="modal modern-modal" role="dialog">
    <div class="modal-dialog">

        <!-- Modal content-->
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Select Image</h4>
            </div>
            <div class="modal-body">
                <div class="panel panel-default">
                    <input type="file" alt="" id="upload_image" class="image" name="image" accept=".jpg">
                </div>
                <span class="img-instruction">Please select only image <strong>(.jpg)</strong> file.</span>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">Close</button>
            </div>
        </div>

    </div>
</div>

<!--upload from folder modal -->


<!--upload from camera modal -->

<!-- Modal -->
<div id="camera_modal" class="modal modern-modal" role="dialog">
    <div class="modal-dialog">

        <!-- Modal content-->
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Get Frame and Snap Image</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="camera_select">Select Camera</label>
                    <select id="camera_select" class="form-control">
                        <option value="">Detecting cameras...</option>
                    </select>
                    <small class="help-block">If multiple cameras are connected, choose the camera before taking the picture.</small>
                </div>
                <div class="panel panel-default">
                    <div id="video-container">
                        <video id="video" autoplay playsinline style="width:100%;"></video>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="take-picture-button">Crop Image</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>

    </div>
</div>

<!--upload from camera modal -->


<!--upload from camera cropper modal -->

<div id="cam_crop_modal" class="modal modern-modal" role="dialog">
    <div class="modal-dialog">

        <!-- Modal content-->
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Crop and Save Image</h4>
            </div>

            <div class="modal-body">


                <div class="img-container">
                    <div class="row">
                        <div class="col-md-8">
                            <img src="" id="cam_snap_image" style="max-width:100%; width:100%; margin:auto;"
                                width="100%" />

                        </div>
                        <div class="col-md-4">
                            <img alt="" id="campreview" style="border-radius:20px;">
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="crop-button">Crop Image</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>

    </div>
</div>

<!--upload from camera cropper modal -->

<!-- upload image from cropper modal -->

<div class="modal modern-modal" id="up_crop_modal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Crop Image Before Upload</h4>
            </div>
            <div class="modal-body">
                <div class="img-container">
                    <div class="row">
                        <div class="col-md-8">
                            <img src="" id="sample_image" />
                        </div>
                        <div class="col-md-4">
                            <div class="preview" style="border-radius:20px;"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="crop" class="btn btn-primary">Crop</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            </div>

        </div>

    </div>

</div>
<!-- upload image from cropper modal -->



<script src="../js/cropper.min.js"></script>
<script src="../js/cstone_form.js"></script>
<script>
    if (false) {
    // field copier script

    $(document).ready(function () {
        $("#field_copier_button").click(function () {
            let field_no = $("#field_copier_field").val();
            if (field_no == "") {
                alert("Please fill the certificate number.")
                $('.field_copier').addClass("has-error");
                $('#field_copier_field').focus();
            } else {
                $.ajax({
                    url: "field-cpier.php",
                    type: "POST",
                    data: {
                        field_no: field_no,
                    },
                    success: function (response) {
                        let data = JSON.parse(response);

                        if(JSON.parse(response) == false){
                            alert("This certificate number is not available in our database.");
                        }else{

                        $("#date").val(data.date);
                        $("#shape_cut").val(data.shape_cut);
                        $("#dimension").val(data.dimension);
                        $("#colour").val(data.color);
                        $("#optic_char").val(data.optic_char);
                        $("#specefic_grav").val(data.spe_gravit);
                        $("#ri").val(data.ref_index);
                        $("#magnification").val(data.magni);
                        $("#species_grp").val(data.spe_group);
                        $("#stone_name").val(data.stone_name);
                        $("#weight").val(data.stone_wt);
                        $("#origin").val(data.origin);
                        $("#issued_to").val(data.issued_to || "");
                        $("#comments").val(data.comment || data.rem1 || data.rem2 || "");
                        $("#hardness").val(data.hardness);
                        }
                     
                    },
                    error: function (xhr, status, error) {
                        // Handle error
                        console.log("Error: " + error);
                    },
                });
            }
        });
    });


    // function to fetch automatic varietyon select of stone name

    let stoneNameMaster = () => {
        let master_stone_name = $("#stone_name").val();

        $.ajax({
            url: 'fetch_master_details.php',
            method: 'POST',
            data: { master_stone_name: master_stone_name },
            dataType: 'json',
            success: function (response) {
                // Populate the second input field with the retrieved value
                $('#species_grp').val(response.group);
            },
            error: function () {
                console.log('Error occurred during Ajax request.');
            }
        });

    }

    }
</script>
<?php include "assets/footer.php"; ?>
