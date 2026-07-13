<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'agreement_helper.php';
require_once 'customer_helper.php';
require_once 'atm_config.php';
require_once 'rate_helper.php';
require_once 'user_branch_helper.php';
date_default_timezone_set('Asia/Kolkata');

$userId = auth_current_user_id();
agreement_table_ready($conn);
customer_master_table_ready($conn);
rate_master_table_ready($conn);
user_branch_location_ready($conn);
user_collection_center_ready($conn);
$nextAgreementNo = agreement_next_no($conn, $userId);
$nextCertificateInfo = atm_next_certificate_number($conn, $userId);
$nextCertificateNo = (int) ($nextCertificateInfo['certi_no'] ?? 1);
$locationName = user_branch_location_for_user($conn, $userId);
$defaultCollectionCenter = user_collection_center_for_user($conn, $userId);
$locationLetter = user_collection_center_code_normalize($defaultCollectionCenter['center_code'] ?? '') ?: ($locationName !== '' ? strtoupper(substr($locationName, 0, 1)) : 'S');
$today = date('Y-m-d');
$now = date('H:i');
$delivery_date = date('Y-m-d', strtotime('+2 day'));
include "assets/navbar.php";
?>
<style>
    .agreement-page {
        padding-bottom: 24px
    }

    .agreement-head {
        border-bottom: 1px solid #ececf1;
        margin-bottom: 12px;
        padding-bottom: 10px
    }

    .agreement-head h1 {
        border: 0;
        color: #171717;
        font-size: 21px;
        font-weight: 600;
        margin: 0 0 5px;
        padding: 0
    }

    .agreement-head p {
        color: #737373;
        font-size: 13px;
        margin: 0
    }

    .agreement-layout {
        display: block;
        width: 100%
    }

    .agreement-main {
        display: flex;
        flex-direction: column;
        gap: 10px;
        width: 100%
    }

    .agreement-card {
        background: #fff;
        border: 1px solid #ececf1;
        border-radius: 8px;
        overflow: visible
    }

    .agreement-card-head {
        align-items: center;
        border-bottom: 1px solid #ececf1;
        display: flex;
        gap: 10px;
        justify-content: space-between;
        padding: 9px 12px
    }

    .agreement-card-head h3 {
        font-size: 14px;
        font-weight: 600;
        margin: 0
    }

    .agreement-card-body {
        padding: 10px 12px
    }

    .agreement-grid {
        display: grid;
        gap: 8px 10px;
        grid-template-columns: repeat(6, minmax(0, 1fr))
    }

    .agreement-field {
        position: relative
    }

    .agreement-field label {
        color: #404040;
        display: block;
        font-size: 11px;
        font-weight: 500;
        margin-bottom: 3px
    }

    .agreement-field .form-control {
        border-radius: 6px;
        box-shadow: none;
        font-size: 12px;
        height: 32px;
        min-height: 32px;
        padding: 5px 8px
    }

    .agreement-field textarea.form-control {
        height: auto;
        min-height: 54px;
        overflow-wrap: anywhere;
        resize: vertical;
        white-space: pre-wrap
    }

    .agreement-field.full {
        grid-column: 1/-1
    }

    .agreement-field.span2 {
        grid-column: span 2
    }

    .agreement-field.span3 {
        grid-column: span 3
    }

    .agreement-checks {
        align-items: center;
        display: flex;
        gap: 10px;
        min-height: 32px
    }

    .agreement-checks label {
        font-size: 12px;
        font-weight: 500;
        margin: 0
    }

    .customer-suggest {
        background: #fff;
        border: 1px solid #d8d8df;
        border-radius: 8px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, .16);
        display: none;
        left: 0;
        max-height: 220px;
        overflow: auto;
        position: absolute;
        right: 0;
        top: 100%;
        z-index: 30
    }

    .customer-suggest.active {
        display: block
    }

    .customer-option {
        border-bottom: 1px solid #f1f1f3;
        cursor: pointer;
        padding: 7px 9px
    }

    .customer-option:last-child {
        border-bottom: 0
    }

    .customer-option:hover,
    .customer-option.active {
        background: #f7f7f8
    }

    .customer-option strong {
        color: #171717;
        display: block;
        font-size: 13px
    }

    .customer-option span {
        color: #737373;
        display: block;
        font-size: 11px;
        margin-top: 2px;
        overflow-wrap: anywhere
    }

    .agreement-table-wrap {
        overflow-x: auto
    }

    .agreement-items {
        min-width: 1150px;
        margin: 0
    }

    .agreement-items th {
        background: #f7f7f8;
        color: #404040;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap
    }

    .agreement-items td,
    .agreement-items th {
        border-color: #e5e5e5 !important;
        padding: 4px !important;
        vertical-align: middle !important
    }

    .agreement-items .form-control {
        border-radius: 6px;
        font-size: 11px;
        height: 28px;
        padding: 3px 6px
    }

    .agreement-items .agreement-unit-field {
        display: grid;
        gap: 3px;
        grid-template-columns: minmax(54px, 1fr) 50px
    }

    .agreement-items .unit-select {
        padding-left: 4px;
        padding-right: 4px
    }

    .agreement-items .agreement-unit-field.fixed-unit {
        grid-template-columns: minmax(54px, 1fr) 34px
    }

    .agreement-items .unit-addon {
        align-items: center;
        background: #f7f7f8;
        border: 1px solid #d4d4d4;
        border-radius: 6px;
        color: #404040;
        display: flex;
        font-size: 11px;
        height: 28px;
        justify-content: center
    }

    .agreement-items .topup-cell {
        text-align: center
    }

    .agreement-items .topup-input {
        height: 16px;
        margin: 0;
        width: 16px
    }

    .agreement-items .row-no {
        color: #737373;
        font-size: 12px;
        text-align: center;
        width: 38px
    }

    .agreement-items tr.agreement-row-cancelled {
        background: #fff5f5
    }

    .agreement-items tr.agreement-row-cancelled td:not(:last-child) {
        color: #7f1d1d;
        text-decoration: line-through;
        text-decoration-thickness: 1.5px
    }

    .agreement-items tr.agreement-row-cancelled input:not([type='hidden']),
    .agreement-items tr.agreement-row-cancelled select {
        background: #fff1f2;
        color: #7f1d1d;
        pointer-events: none;
        text-decoration: line-through
    }

    .agreement-items .remove-row {
        border-radius: 6px;
        height: 28px;
        padding: 4px 8px
    }

    .agreement-items .cancel-row,
    .agreement-items .undo-cancel-row {
        border-radius: 6px;
        height: 28px;
        padding: 4px 8px
    }

    .agreement-items-summary {
        align-items: end;
        display: flex;
        justify-content: flex-end;
        padding: 8px 12px
    }

    .agreement-items-summary label {
        color: #404040;
        display: block;
        font-size: 12px;
        font-weight: 600;
        margin: 0 0 5px
    }

    .agreement-items-summary .form-control {
        border-radius: 8px;
        font-weight: 700;
        text-align: center;
        width: 120px
    }

    .agreement-actions {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: flex-end;
        margin-top: 16px;
        padding-top: 4px
    }

    .agreement-actions .btn {
        border-radius: 8px;
        min-height: 40px;
        min-width: 118px
    }

    .agreement-save {
        background: #171717;
        border-color: #171717;
        color: #fff
    }

    .agreement-save:hover,
    .agreement-save:focus {
        background: #404040;
        color: #fff
    }

    .agreement-status {
        align-items: center;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 8px;
        color: #166534;
        display: none;
        gap: 8px;
        padding: 10px 12px
    }

    .agreement-edit-bar {
        align-items: end;
        background: #fff;
        border: 1px solid #ececf1;
        border-radius: 8px;
        display: grid;
        gap: 10px;
        grid-template-columns: minmax(150px, 190px) auto minmax(170px, 220px) auto 1fr;
        margin-bottom: 10px;
        padding: 10px 12px
    }

    .agreement-edit-bar label {
        color: #404040;
        display: block;
        font-size: 11px;
        font-weight: 600;
        margin: 0 0 3px
    }

    .agreement-edit-bar .btn {
        border-radius: 8px;
        min-height: 32px
    }

    .agreement-edit-note {
        align-self: center;
        color: #737373;
        font-size: 12px
    }

    .amount-grid {
        grid-template-columns: repeat(6, minmax(0, 1fr))
    }

    .signature-box {
        border: 1px dashed #d4d4d4;
        border-radius: 8px;
        color: #737373;
        font-size: 12px;
        min-height: 58px;
        padding: 8px
    }

    .signature-mode {
        align-items: center;
        display: flex;
        gap: 12px;
        margin-bottom: 6px
    }

    .signature-mode label {
        align-items: center;
        display: flex;
        gap: 6px;
        margin: 0
    }

    .signature-pad-wrap {
        display: none
    }

    .signature-pad-wrap.active {
        display: block
    }

    .signature-pad {
        background: #fff;
        border: 1px solid #cfcfcf;
        border-radius: 8px;
        display: block;
        height: 110px;
        touch-action: none;
        width: 100%
    }

    .signature-tools {
        align-items: center;
        display: flex;
        gap: 8px;
        justify-content: space-between;
        margin-top: 6px
    }

    .signature-tools small {
        color: #737373;
        line-height: 1.35
    }

    .signature-tools .btn {
        border-radius: 7px
    }

    @media(max-width:1100px) {
        .agreement-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }

        .amount-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }
    }

    @media(max-width:620px) {

        .agreement-grid,
        .amount-grid {
            grid-template-columns: 1fr
        }

        .agreement-field.span2,
        .agreement-field.span3 {
            grid-column: 1/-1
        }

        .agreement-actions {
            align-items: stretch;
            flex-direction: column;
            justify-content: stretch
        }

        .agreement-edit-bar {
            grid-template-columns: 1fr
        }

        .agreement-actions .btn {
            width: 100%
        }

    }
</style>
<div id="page-wrapper">
    <div class="container-fluid agreement-page">
        <div class="agreement-head">
            <h1><i class="fa fa-file-text-o"></i> Stone Agreement</h1>
            <p>Create the customer agreement first, then print it for signature before certificate feeding.
            </p>
        </div>
        <div class="agreement-layout">
            <div class="agreement-main">
                <div class="agreement-status" id="agreement_status"><i class="fa fa-check-circle"></i><span>Saved
                        agreement <strong id="saved_agreement_no"></strong>.</span><a id="saved_agreement_print"
                        href="#" target="_blank">Open print view</a></div>
                <div class="agreement-edit-bar">
                    <div><label for="edit_agreement_lookup">Edit Agreement No.</label><input class="form-control" id="edit_agreement_lookup" inputmode="numeric" placeholder="Enter agreement no"></div>
                    <button type="button" class="btn btn-default" id="load_agreement_edit"><i class="fa fa-pencil"></i> Load Edit</button>
                    <div><label for="agreement_status_select">Agreement Status</label><select class="form-control" id="agreement_status_select" disabled><?php foreach (agreement_status_options() as $statusCode => $statusLabel): ?><option value="<?php echo agreement_h($statusCode); ?>"><?php echo agreement_h($statusLabel); ?></option><?php endforeach; ?></select></div>
                    <button type="button" class="btn btn-default" id="update_agreement_status" disabled><i class="fa fa-whatsapp"></i> Update Status</button>
                    <div class="agreement-edit-note" id="agreement_edit_note">Load an old agreement here to update it.</div>
                </div>
                <form id="agreement_form" autocomplete="off" data-agreement-no="<?php echo (int) $nextAgreementNo; ?>"
                    data-next-certi-no="<?php echo (int) $nextCertificateNo; ?>"
                    data-location-letter="<?php echo agreement_h($locationLetter); ?>"
                    data-location-name="<?php echo agreement_h($locationName); ?>">
                    <input type="hidden" name="edit_agreement_id" id="edit_agreement_id">
                    <input type="hidden" name="edit_agreement_no" id="edit_agreement_no">
                    <section class="agreement-card">
                        <div class="agreement-card-head">
                            <h3>Customer & Agreement</h3>
                        </div>
                        <div class="agreement-card-body">
                            <div class="agreement-grid">
                                <div class="agreement-field"><label>Agreement Serial No.</label><input
                                        class="form-control" id="agreement_serial_no"
                                        value="<?php echo (int) $nextAgreementNo; ?>" readonly></div>
                                <div class="agreement-field"><label for="docket_no">Docket No.</label><input
                                        class="form-control" name="docket_no" id="docket_no"></div>
                                <div class="agreement-field"><label for="collection_center_id">Collection Center</label><select
                                        class="form-control" name="collection_center_id" id="collection_center_id"><?php echo user_collection_center_options($conn, $userId, (int) ($defaultCollectionCenter['id'] ?? 0)); ?></select></div>
                                <div class="agreement-field"><label for="agreement_date">Date</label><input
                                        class="form-control" type="date" name="agreement_date" id="agreement_date"
                                        value="<?php echo agreement_h($today); ?>"></div>
                                <div class="agreement-field"><label for="agreement_time">Time</label><input
                                        class="form-control" type="time" name="agreement_time" id="agreement_time"
                                        value="<?php echo agreement_h($now); ?>"></div>
                                <div class="agreement-field span2"><label for="customer_name">Customer Name
                                        *</label><div class="input-group"><input class="form-control" name="customer_name" id="customer_name"
                                        autocomplete="off" required><span class="input-group-btn"><button class="btn btn-default" type="button" id="add_customer_button" title="Add customer"><i class="fa fa-plus"></i></button></span></div>
                                    <input type="hidden" name="customer_master_id" id="customer_master_id">
                                    <div class="customer-suggest" id="customer_suggest"></div>
                                </div>
                                <div class="agreement-field span1"><label for="depositor_name">Name of
                                        Depositor</label><input class="form-control" name="depositor_name"
                                        id="depositor_name"></div>
                                <div class="agreement-field span2 "><label>Membership</label>
                                    <div class="agreement-checks form-control"><label><input type="radio" name="member_status"
                                                value="Non Member" checked> Non Member</label><label><input type="radio"
                                                name="member_status" value="Member"> Member</label></div>
                                </div>
                                <div class="agreement-field"><label for="mou_cdc">MOU / CDC</label><select
                                        class="form-control" name="mou_cdc"
                                        id="mou_cdc"><?php echo agreement_mou_options(''); ?></select></div>
                                <div class="agreement-field"><label for="category">Category</label><select
                                        class="form-control" name="category" id="category">
                                        <option value="Regular" selected>Regular</option>
                                        <option value="Urgent">Urgent</option>
                                    </select></div>
                                <div class="agreement-field"><label for="gst_no">GST No.</label><input
                                        class="form-control" name="gst_no" id="gst_no"></div>
                                <div class="agreement-field span2"><label for="address">Address</label><textarea
                                        class="form-control" name="address" id="address"></textarea></div>
                                <div class="agreement-field"><label for="mobile_no">Mobile No.</label><input
                                        class="form-control" name="mobile_no" id="mobile_no"></div>
                                <div class="agreement-field"><label for="email">Email</label><input class="form-control"
                                        type="email" name="email" id="email"></div>
                                <div class="agreement-field"><label for="id_no">Govt ID No.</label><input
                                        class="form-control" name="id_no" id="id_no"></div>
                                <div class="agreement-field"><label for="delivery_date">Delivery Date</label><input
                                        class="form-control" type="date" name="delivery_date" id="delivery_date"
                                        value="<?php echo agreement_h($delivery_date); ?>"></div>
                                <div class="agreement-field"><label for="delivery_time">Delivery Time</label><input
                                        class="form-control" type="time" name="delivery_time" id="delivery_time"
                                        value="05:00">
                                    <!-- <div class="agreement-field"><label>Delivery Status</label>
                                        <div class="agreement-checks"><label><input type="checkbox" name="delivered"
                                                    value="1"> Delivered</label></div>
                                    </div> -->
                                </div>
                            </div>
                    </section>
                    <datalist id="colour_master_options"></datalist>
                    <datalist id="rate_category_options"></datalist>
                    <section class="agreement-card">
                        <div class="agreement-card-head">
                            <h3>Stone Details</h3><div><button type="button" class="btn btn-default btn-sm"
                                id="add_rate_category_button"><i class="fa fa-tags"></i> Add Category</button> <button type="button" class="btn btn-default btn-sm"
                                id="add_agreement_row"><i class="fa fa-plus"></i> Add Row</button></div>
                        </div>
                        <div class="agreement-table-wrap">
                            <table class="table table-bordered agreement-items">
                                <thead>
                                    <tr>
                                        <th>SNO</th>
                                        <th>Ref No</th>
                                        <th>Category</th>
                                        <th>Particulars</th>
                                        <th>Color</th>
                                        <th>Gross Wt.</th>
                                        <th>Stone Wt.</th>
                                        <th>Dia Wt.</th>
                                        <th>Beads Length</th>
                                        <th>Pcs</th>
                                        <th>A4/Card</th>
                                        <th>Topup</th>
                                        <th>Rate</th>
                                        <th>Discount</th>
                                        <th>Amount</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="agreement_items_body"></tbody>
                            </table>
                        </div>
                    </section>
                    <section class="agreement-card">
                        <div class="agreement-card-head">
                            <h3>Payment & Preparation</h3>
                        </div>
                        <div class="agreement-card-body">
                            <div class="agreement-grid amount-grid">
                                <div class="agreement-field"><label for="testing_charges">Testing Charges</label><input
                                        class="form-control money-field" name="testing_charges" id="testing_charges"
                                        value="0.00" readonly></div>
                                <div class="agreement-field"><label for="pcs_total_display">Total PCS</label><input
                                        class="form-control" id="pcs_total_display" value="0" readonly></div>
                                <div class="agreement-field"><label for="payment_cash">Cash</label><input
                                        class="form-control money-field" name="payment_cash" id="payment_cash"></div>
                                <div class="agreement-field"><label for="payment_cheque">Cheque</label><input
                                        class="form-control money-field" name="payment_cheque" id="payment_cheque">
                                </div>
                                <div class="agreement-field"><label for="payment_neft">NEFT/UPI</label><input
                                        class="form-control money-field" name="payment_neft" id="payment_neft"></div>
                                <div class="agreement-field"><label for="payment_card">Card</label><input
                                        class="form-control money-field" name="payment_card" id="payment_card"></div>
                                <div class="agreement-field"><label for="payment_tds">TDS</label><input
                                        class="form-control money-field" name="payment_tds" id="payment_tds"></div>
                                <div class="agreement-field"><label for="cheque_no">Cheque No.</label><input
                                        class="form-control" name="cheque_no" id="cheque_no"></div>
                                <div class="agreement-field"><label for="due_amount">Due Amount</label><input
                                        class="form-control money-field" name="due_amount" id="due_amount" readonly>
                                </div>
                                <div class="agreement-field"><label for="refund_amount">Refund Amount</label><input
                                        class="form-control money-field" name="refund_amount" id="refund_amount"
                                        readonly></div>
                                <div class="agreement-field"><label for="prepared_by">Prepared By</label><input
                                        class="form-control" readonly name="prepared_by" id="prepared_by"
                                        value="<?php echo agreement_h(auth_current_user_name()); ?>"></div>
                                <div class="agreement-field span3"><label for="remarks">Remarks</label><input
                                        class="form-control" name="remarks" id="remarks"></div>
                                <div class="agreement-field span2"><label>Customer Signature</label>
                                    <div class="signature-box">
                                        <div class="signature-mode">
                                            <label><input type="radio" name="signature_mode" value="manual" checked>
                                                Manual Signature</label>
                                            <label><input type="radio" name="signature_mode" value="esign">
                                                E-Signature</label>
                                        </div>
                                        <input type="hidden" name="customer_signature" id="customer_signature">
                                        <div id="manual_signature_note">Signature line will remain blank for signing on
                                            printed agreement.</div>
                                        <div class="signature-pad-wrap" id="signature_pad_wrap">
                                            <canvas id="signature_pad" class="signature-pad" tabindex="0"></canvas>
                                            <div class="signature-tools"><small>Customer can sign here using mouse,
                                                    touch screen, or signature pad.</small><button type="button"
                                                    class="btn btn-default btn-sm" id="clear_signature"><i
                                                        class="fa fa-eraser"></i> Clear</button></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <div class="agreement-actions"><button type="submit" class="btn agreement-save"
                            id="agreement_submit"><i class="fa fa-check"></i> Save Agreement</button><button
                            type="reset" class="btn btn-default"><i class="fa fa-refresh"></i> New</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal modern-modal" id="customer_add_modal" tabindex="-1" role="dialog" aria-labelledby="customerAddTitle">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="customer_add_form">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title" id="customerAddTitle">Add Customer</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-sm-6 form-group"><label>Customer Name *</label><input class="form-control" name="customer_name" id="new_customer_name" required></div>
                        <div class="col-sm-6 form-group"><label>Name of Depositor</label><input class="form-control" name="depositor_name" id="new_depositor_name"></div>
                        <div class="col-sm-6 form-group"><label>Mobile No.</label><input class="form-control" name="mobile_no" id="new_mobile_no"></div>
                        <div class="col-sm-6 form-group"><label>Email</label><input class="form-control" type="email" name="email" id="new_email"></div>
                        <div class="col-sm-6 form-group"><label>GST No.</label><input class="form-control" name="gst_no" id="new_gst_no"></div>
                        <div class="col-sm-6 form-group"><label>Govt ID No.</label><input class="form-control" name="id_no" id="new_id_no"></div>
                        <div class="col-sm-6 form-group"><label>Membership</label><select class="form-control" name="member_status" id="new_member_status"><option value="Non Member">Non Member</option><option value="Member">Member</option></select></div>
                        <div class="col-sm-6 form-group"><label>MOU / CDC</label><select class="form-control" name="mou_cdc" id="new_mou_cdc"><?php echo agreement_mou_options(''); ?></select></div>
                        <div class="col-sm-12 form-group"><label>Address</label><textarea class="form-control" name="address" id="new_address" rows="3"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="customer_add_submit"><i class="fa fa-save"></i> Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal modern-modal" id="rate_category_modal" tabindex="-1" role="dialog" aria-labelledby="rateCategoryTitle">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="rate_category_form">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title" id="rateCategoryTitle">Add Agreement Category</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-sm-12 form-group"><label>Category Name *</label><input class="form-control" name="description" id="new_rate_description" maxlength="255" required></div>
                        <div class="col-sm-4 form-group"><label>Member Rate</label><input class="form-control" name="rate_member" id="new_rate_member" inputmode="decimal" value="0"></div>
                        <div class="col-sm-4 form-group"><label>Non-Member Rate</label><input class="form-control" name="rate_non_member" id="new_rate_non_member" inputmode="decimal" value="0"></div>
                        <div class="col-sm-4 form-group"><label>CDC Discount</label><div class="form-control agreement-checks"><label><input type="checkbox" name="cdc" id="new_rate_cdc" value="Y"> Yes</label></div></div>
                        <div class="col-sm-12 form-group"><label>Remark</label><input class="form-control" name="remark" id="new_rate_remark" maxlength="255"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="rate_category_submit"><i class="fa fa-save"></i> Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script
    src="../js/agreement.js?v=<?php echo urlencode((string) @filemtime(__DIR__ . '/../js/agreement.js')); ?>"></script>
<?php include "assets/footer.php"; ?>
