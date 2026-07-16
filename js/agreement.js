(function () {
    "use strict";
    var form = document.getElementById("agreement_form");
    var body = document.getElementById("agreement_items_body");
    var submit = document.getElementById("agreement_submit");
    var signatureCanvas = document.getElementById("signature_pad");
    var signatureInput = document.getElementById("customer_signature");
    var signatureWrap = document.getElementById("signature_pad_wrap");
    var manualNote = document.getElementById("manual_signature_note");
    var clearSignature = document.getElementById("clear_signature");
    var signatureContext = signatureCanvas ? signatureCanvas.getContext("2d") : null;
    var signatureDrawing = false;
    var signatureHasInk = false;
    var customerInput = document.getElementById("customer_name");
    var customerIdInput = document.getElementById("customer_master_id");
    var customerSuggest = document.getElementById("customer_suggest");
    var addCustomerButton = document.getElementById("add_customer_button");
    var addCustomerForm = document.getElementById("customer_add_form");
    var addCustomerSubmit = document.getElementById("customer_add_submit");
    var addRateCategoryButton = document.getElementById("add_rate_category_button");
    var rateCategoryForm = document.getElementById("rate_category_form");
    var rateCategorySubmit = document.getElementById("rate_category_submit");
    var customerTimer = null;
    var customerResults = [];
    var rateList = [];
    var rateConditionRules = [];
    var rateOptionsHtml = '';
    var colourOptionsHtml = '';
    var freshAgreementNo = form ? String(form.dataset.agreementNo || "") : "";
    var freshNextCertiNo = form ? String(form.dataset.nextCertiNo || "1") : "1";
    var suppressResetHandler = false;

    function selectedCollectionCode() {
        var select = document.getElementById("collection_center_id");
        var option = select && select.options[select.selectedIndex];
        var code = option ? String(option.getAttribute("data-code") || "") : "";
        code = code.toUpperCase().replace(/[^A-Z0-9]/g, "").charAt(0);
        return code || (form.dataset.locationLetter || "S").toUpperCase().charAt(0) || "S";
    }

    function rowTemplate(index) {
        var name = "items[" + index + "]";
        var unitOptions = '<option value="ct" selected>ct</option><option value="gms">gms</option><option value="kg">kg</option>';
        return '<tr>' +
            '<td><span class="row-no"></span><input type="hidden" class="row-status-input" name="' + name + '[row_status]" value="active"><input type="hidden" class="row-cancel-reason-input" name="' + name + '[row_cancel_reason]" value=""></td>' +
            '<td><input class="form-control ref-input" name="' + name + '[ref_no]" readonly></td>' +
            '<td><input class="form-control rate-category" list="rate_category_options" name="' + name + '[category]" autocomplete="off"></td>' +
            '<td><input class="form-control" name="' + name + '[particulars]"></td>' +
            '<td><input class="form-control colour-input" list="colour_master_options" name="' + name + '[color]"></td>' +
            '<td><div class="agreement-unit-field"><input class="form-control" name="' + name + '[gross_wt]"><select class="form-control unit-select" name="' + name + '[gross_wt_unit]">' + unitOptions + '</select></div></td>' +
            '<td><div class="agreement-unit-field"><input class="form-control" name="' + name + '[stone_wt]"><select class="form-control unit-select" name="' + name + '[stone_wt_unit]">' + unitOptions + '</select></div></td>' +
            '<td><div class="agreement-unit-field fixed-unit"><input class="form-control" name="' + name + '[dia_wt]"><span class="unit-addon">ct</span><input type="hidden" name="' + name + '[dia_wt_unit]" value="ct"></div></td>' +
            '<td><input class="form-control" name="' + name + '[bead_length]"></td>' +
            '<td><input class="form-control pcs-input" value = "1" name="' + name + '[pcs]" inputmode="numeric"></td>' +
            '<td><select class="form-control" name="' + name + '[a4_card]"><option value="A4" selected>A4</option><option value="ATM Card">ATM Card</option><option value="Postcard">Postcard</option></select></td>' +
            '<td class="topup-cell"><input class="topup-input" type="checkbox" name="' + name + '[topup]" value="1" title="Apply top-up"></td>' +
            '<td><input class="form-control money-input rate-input" name="' + name + '[rate]"></td>' +
            '<td><input class="form-control money-input discount-input" name="' + name + '[discount_amount]" disabled><input type="hidden" class="discount-percent-input" name="' + name + '[discount_percent]"></td>' +
            '<td><input class="form-control money-input amount-input" name="' + name + '[amount]"></td>' +
            '<td><button type="button" class="btn btn-default remove-row row-action" title="Remove row"><i class="fa fa-trash-o"></i></button></td>' +
        '</tr>';
    }

    function isRowCancelled(row) {
        var status = row ? row.querySelector(".row-status-input") : null;
        return status && String(status.value || "").toLowerCase() === "cancelled";
    }

    function syncRowActionButtons() {
        var editing = form && form.dataset.editMode === "1";
        Array.prototype.forEach.call(body.querySelectorAll("tr"), function (row) {
            var button = row.querySelector(".row-action");
            if (!button) return;
            if (editing) {
                if (isRowCancelled(row)) {
                    button.className = "btn btn-warning row-action undo-cancel-row";
                    button.title = "Undo row cancellation";
                    button.innerHTML = '<i class="fa fa-undo"></i>';
                } else {
                    button.className = "btn btn-danger row-action cancel-row";
                    button.title = "Cancel row";
                    button.innerHTML = '<i class="fa fa-ban"></i>';
                }
            } else {
                button.className = "btn btn-default row-action remove-row";
                button.title = "Remove row";
                button.innerHTML = '<i class="fa fa-trash-o"></i>';
            }
        });
    }

    function syncRowState(row) {
        if (!row) return;
        var cancelled = isRowCancelled(row);
        row.classList.toggle("agreement-row-cancelled", cancelled);
        Array.prototype.forEach.call(row.querySelectorAll("input:not([type='hidden']), textarea"), function (input) {
            if (input.classList.contains("ref-input")) return;
            input.readOnly = cancelled;
            input.tabIndex = cancelled ? -1 : 0;
        });
        Array.prototype.forEach.call(row.querySelectorAll("select, .topup-input"), function (control) {
            control.tabIndex = cancelled ? -1 : 0;
        });
        syncRowActionButtons();
    }

    function renumberRows() {
        var agreementNo = parseInt(form.dataset.agreementNo || "0", 10) || 0;
        var nextCertiNo = parseInt(form.dataset.nextCertiNo || "1", 10) || 1;
        var locationLetter = selectedCollectionCode();
        Array.prototype.forEach.call(body.querySelectorAll("tr"), function (row, index) {
            row.querySelector(".row-no").textContent = index + 1;
            var ref = row.querySelector(".ref-input");
            if (form.dataset.editMode === "1" && ref && ref.value) return;
            if (ref) ref.value = agreementNo + locationLetter + (nextCertiNo + index);
        });
    }

    function addRow(focus) {
        var index = Date.now().toString(36) + Math.floor(Math.random() * 1000);
        var holder = document.createElement("tbody");
        holder.innerHTML = rowTemplate(index);
        var row = holder.firstChild;
        body.appendChild(row);
        renumberRows();
        syncRowState(row);
        updatePcsTotal();
        if (focus) body.querySelector("tr:last-child input").focus();
        return row;
    }

    function setInputValue(selector, value) {
        var el = document.querySelector(selector);
        if (el) el.value = value == null ? "" : String(value);
    }

    function setSelectValue(select, value) {
        if (!select) return;
        value = value == null ? "" : String(value);
        if (select.tagName && select.tagName.toLowerCase() !== "select") {
            select.value = value;
            return;
        }
        if (value !== "" && !Array.prototype.some.call(select.options, function (option) { return option.value === value; })) {
            var option = document.createElement("option");
            option.value = value;
            option.textContent = value;
            select.appendChild(option);
        }
        select.value = value;
    }

    function populateAgreementRow(row, item) {
        item = item || {};
        [
            "ref_no", "particulars", "color", "gross_wt", "stone_wt", "dia_wt", "bead_length", "pcs",
            "rate", "discount_amount", "discount_percent", "amount"
        ].forEach(function (key) {
            var input = row.querySelector('[name$="[' + key + ']"]');
            if (input) input.value = item[key] == null ? "" : String(item[key]);
        });
        setSelectValue(row.querySelector('[name$="[category]"]'), item.category || "");
        setSelectValue(row.querySelector('[name$="[gross_wt_unit]"]'), item.gross_wt_unit || "ct");
        setSelectValue(row.querySelector('[name$="[stone_wt_unit]"]'), item.stone_wt_unit || "ct");
        setSelectValue(row.querySelector('[name$="[a4_card]"]'), item.a4_card || "A4");
        var topup = row.querySelector('[name$="[topup]"]');
        if (topup) topup.checked = String(item.topup || "") === "1";
        var rowStatus = row.querySelector(".row-status-input");
        if (rowStatus) rowStatus.value = String(item.row_status || "").toLowerCase() === "cancelled" ? "cancelled" : "active";
        var cancelReason = row.querySelector(".row-cancel-reason-input");
        if (cancelReason) cancelReason.value = item.row_cancel_reason == null ? "" : String(item.row_cancel_reason);
        if (!row.querySelector('[name$="[pcs]"]').value) row.querySelector('[name$="[pcs]"]').value = "1";
        syncRowState(row);
    }

    function setEditMode(agreement) {
        var editing = !!agreement;
        form.dataset.editMode = editing ? "1" : "0";
        $("#edit_agreement_id").val(editing ? agreement.id : "");
        $("#edit_agreement_no").val(editing ? agreement.agreement_no : "");
        $("#agreement_status_select").prop("disabled", !editing).val(editing ? (agreement.agreement_status || "IN_PROCESS") : "IN_PROCESS");
        $("#update_agreement_status").prop("disabled", !editing);
        $("#agreement_edit_note").text(editing ? ("Editing agreement #" + agreement.agreement_no + ". Click New to leave edit mode.") : "Load an old agreement here to update it.");
        submit.innerHTML = editing ? '<i class="fa fa-save"></i> Update Agreement' : '<i class="fa fa-check"></i> Save Agreement';
        syncRowActionButtons();
    }

    function populateAgreementForm(agreement) {
        if (!agreement) return;
        suppressResetHandler = true;
        form.reset();
        suppressResetHandler = false;
        body.innerHTML = "";
        form.dataset.agreementNo = String(agreement.agreement_no || "");
        form.dataset.nextCertiNo = String(agreement.first_certificate_no || 1);
        if (agreement.collection_center_code) {
            form.dataset.locationLetter = String(agreement.collection_center_code || "S").toUpperCase().charAt(0) || "S";
        }
        $("#agreement_serial_no").val(agreement.agreement_no || "");
        setSelectValue(document.getElementById("collection_center_id"), agreement.collection_center_id || "");
        $("#customer_master_id").val(agreement.customer_master_id || "");
        setInputValue("#docket_no", agreement.docket_no);
        setInputValue("#customer_name", agreement.customer_name);
        setInputValue("#depositor_name", agreement.depositor_name);
        setInputValue("#gst_no", agreement.gst_no);
        setInputValue("#address", agreement.address);
        setInputValue("#mobile_no", agreement.mobile_no);
        setInputValue("#email", agreement.email);
        setInputValue("#id_no", agreement.id_no);
        setInputValue("#agreement_date", agreement.agreement_date);
        setInputValue("#agreement_time", agreement.agreement_time);
        setInputValue("#delivery_date", agreement.delivery_date);
        setInputValue("#delivery_time", agreement.delivery_time);
        setInputValue("#testing_charges", agreement.testing_charges);
        setInputValue("#payment_cash", agreement.payment_cash);
        setInputValue("#payment_cheque", agreement.payment_cheque);
        setInputValue("#payment_neft", agreement.payment_neft);
        setInputValue("#payment_card", agreement.payment_card);
        setInputValue("#payment_tds", agreement.payment_tds);
        setInputValue("#cheque_no", agreement.cheque_no);
        setInputValue("#due_amount", agreement.due_amount);
        setInputValue("#refund_amount", agreement.refund_amount);
        setInputValue("#prepared_by", agreement.prepared_by);
        setInputValue("#remarks", agreement.remarks);
        $("#category").val(agreement.category || "Regular");
        $("#mou_cdc").val(mouTierCode(agreement.mou_cdc || ""));
        $("input[name='member_status'][value='" + (agreement.member_status === "Member" ? "Member" : "Non Member") + "']").prop("checked", true);
        $("input[name='signature_mode'][value='" + (agreement.signature_mode === "esign" ? "esign" : "manual") + "']").prop("checked", true);
        clearSignaturePad();
        refreshSignatureMode();
        if (agreement.signature_mode === "esign" && agreement.customer_signature) {
            loadSignatureImage(agreement.customer_signature);
        }
        (agreement.items || []).forEach(function (item) {
            populateAgreementRow(addRow(false), item);
        });
        if (!body.querySelector("tr")) addRow(false);
        setEditMode(agreement);
        Array.prototype.forEach.call(body.querySelectorAll("tr"), syncRowState);
        renumberRows();
        refreshAllRates();
        updatePcsTotal();
        updateTestingCharges();
        $("#agreement_status").hide();
    }

    function customerSelected() {
        return !!(customerIdInput && customerIdInput.value && customerInput && customerInput.value.trim());
    }

    function requireSelectedCustomer() {
        if (customerSelected()) return true;
        AppToast.error("Please select customer name from the list first.");
        if (customerInput) {
            customerInput.focus();
            searchCustomers(customerInput.value.trim());
        }
        return false;
    }

    function hasItemRow() {
        return Array.prototype.some.call(body.querySelectorAll("tr"), function (row) {
            if (isRowCancelled(row)) return false;
            return Array.prototype.some.call(row.querySelectorAll("input,select"), function (input) {
                if (input.classList.contains("ref-input")) return false;
                if (input.type === "checkbox") return input.checked;
                if (input.name && input.name.indexOf("[a4_card]") !== -1 && input.value.trim() === "A4") return false;
                return input.value.trim() !== "";
            });
        });
    }

    function resetAgreementForm() {
        form.reset();
        form.dataset.locationLetter = selectedCollectionCode();
    }

    function openAgreementPrint(printUrl) {
        if (!printUrl) return;
        var opened = window.open(printUrl, "_blank");
        if (!opened) {
            window.location.href = printUrl;
        }
    }

    function showAgreementSavedActions(printUrl, labelsUrl) {
        return new Promise(function (resolve) {
            var old = document.getElementById("agreement_action_modal");
            if (old) old.parentNode.removeChild(old);
            var modal = document.createElement("div");
            modal.id = "agreement_action_modal";
            modal.innerHTML = '' +
                '<div class="agreement-action-backdrop"></div>' +
                '<div class="agreement-action-box" role="dialog" aria-modal="true" aria-labelledby="agreement_action_title">' +
                '<h3 id="agreement_action_title">Agreement saved</h3>' +
                '<p>You can generate agreement and labels one by one. Click Exit when finished.</p>' +
                '<div class="agreement-action-buttons">' +
                '<button type="button" class="btn btn-primary" data-action="agreement">Generate Agreement</button>' +
                '<button type="button" class="btn btn-default" data-action="labels">Generate Labels</button>' +
                '<button type="button" class="btn btn-default" data-action="exit">Exit</button>' +
                '</div>' +
                '</div>';
            var style = document.createElement("style");
            style.textContent = '#agreement_action_modal{position:fixed;inset:0;z-index:10050;display:flex;align-items:center;justify-content:center}.agreement-action-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.48)}.agreement-action-box{position:relative;background:#fff;border-radius:10px;box-shadow:0 24px 70px rgba(15,23,42,.25);max-width:420px;padding:20px;width:calc(100% - 32px)}.agreement-action-box h3{font-size:18px;font-weight:600;margin:0 0 6px}.agreement-action-box p{color:#525252;font-size:13px;margin:0 0 16px}.agreement-action-buttons{display:grid;gap:10px;grid-template-columns:1fr}.agreement-action-buttons .btn{border-radius:8px;min-height:42px;font-weight:600}@media(min-width:560px){.agreement-action-buttons{grid-template-columns:1fr 1fr 90px}}';
            modal.appendChild(style);
            document.body.appendChild(modal);
            var escHandler = null;
            function close(action) {
                if (escHandler) document.removeEventListener("keydown", escHandler);
                if (modal.parentNode) modal.parentNode.removeChild(modal);
                resolve(action || "exit");
            }
            modal.addEventListener("click", function (event) {
                var button = event.target.closest("button[data-action]");
                if (button) {
                    var action = button.getAttribute("data-action");
                    if (action === "agreement") {
                        openAgreementPrint(printUrl);
                        return;
                    }
                    if (action === "labels") {
                        openAgreementPrint(labelsUrl);
                        return;
                    }
                    close(action);
                }
                if (event.target.className === "agreement-action-backdrop") close("exit");
            });
            escHandler = function (event) {
                if (event.key === "Escape") {
                    close("exit");
                }
            };
            document.addEventListener("keydown", escHandler);
            var first = modal.querySelector("button[data-action='agreement']");
            if (first) first.focus();
        }).then(function (action) {
            return action;
        });
    }

    function agreementSummaryValue(selector) {
        return $.trim($(selector).val() || "");
    }

    function agreementPaymentValue(selector) {
        var value = parseFloat(String($(selector).val() || "").replace(/[^0-9.\-]/g, ""));
        return isNaN(value) ? "0.00" : value.toFixed(2);
    }

    function agreementRowCount() {
        return body ? body.querySelectorAll("tr").length : 0;
    }

    function agreementFirstLastRef() {
        var refs = Array.prototype.map.call(body.querySelectorAll(".ref-input"), function (input) {
            return $.trim(input.value || "");
        }).filter(Boolean);
        if (!refs.length) return "";
        return refs.length === 1 ? refs[0] : refs[0] + " to " + refs[refs.length - 1];
    }

    function selectedCollectionLabel() {
        var select = document.getElementById("collection_center_id");
        var option = select && select.options[select.selectedIndex];
        return option ? $.trim(option.textContent || option.innerText || "") : "";
    }

    function confirmAgreementSave() {
        var isEdit = form.dataset.editMode === "1";
        var rows = [
            ["Mode", isEdit ? "Update existing agreement" : "New agreement"],
            ["Agreement No.", $("#agreement_serial_no").val() || form.dataset.agreementNo || ""],
            ["Branch Location", form.dataset.locationName || ""],
            ["Collection Center", selectedCollectionLabel()],
            ["Ref No.", agreementFirstLastRef()],
            ["Customer", agreementSummaryValue("#customer_name")],
            ["Depositor", agreementSummaryValue("#depositor_name")],
            ["Mobile", agreementSummaryValue("#mobile_no")],
            ["Rows / Total PCS", agreementRowCount() + " rows / " + ($("#pcs_total_display").val() || "0") + " active pcs"],
            ["Testing Charges", agreementPaymentValue("#testing_charges")],
            ["Paid", (moneyValue("#payment_cash") + moneyValue("#payment_cheque") + moneyValue("#payment_neft") + moneyValue("#payment_card") + moneyValue("#payment_tds")).toFixed(2)],
            ["Due", agreementPaymentValue("#due_amount")],
            ["Delivery", [agreementSummaryValue("#delivery_date"), agreementSummaryValue("#delivery_time")].filter(Boolean).join(" ")]
        ];
        if (!window.Promise) {
            return { then: function (next) { next(window.confirm("Confirm save agreement #" + ($("#agreement_serial_no").val() || form.dataset.agreementNo || "") + "?")); } };
        }
        return new Promise(function (resolve) {
            var old = document.getElementById("agreement_confirm_modal");
            if (old) old.parentNode.removeChild(old);
            var modal = document.createElement("div");
            modal.id = "agreement_confirm_modal";
            modal.innerHTML = '' +
                '<div class="agreement-confirm-backdrop"></div>' +
                '<div class="agreement-confirm-box" role="dialog" aria-modal="true" aria-labelledby="agreement_confirm_title">' +
                '<h3 id="agreement_confirm_title">Confirm Agreement Details</h3>' +
                '<p>Please check these important details before saving.</p>' +
                '<div class="agreement-confirm-table-wrap"><table class="agreement-confirm-table"><tbody>' +
                rows.map(function (row) {
                    return '<tr><th>' + escapeHtml(row[0]) + '</th><td>' + escapeHtml(row[1] || "-") + '</td></tr>';
                }).join("") +
                '</tbody></table></div>' +
                '<div class="agreement-confirm-actions">' +
                '<button type="button" class="btn btn-default" data-action="cancel">Check Again</button>' +
                '<button type="button" class="btn btn-primary" data-action="save"><i class="fa fa-save"></i> Save Agreement</button>' +
                '</div>' +
                '</div>';
            var style = document.createElement("style");
            style.textContent = '#agreement_confirm_modal{position:fixed;inset:0;z-index:10060;display:flex;align-items:center;justify-content:center}.agreement-confirm-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.5)}.agreement-confirm-box{position:relative;background:#fff;border-radius:10px;box-shadow:0 24px 70px rgba(15,23,42,.25);max-width:560px;padding:18px;width:calc(100% - 32px)}.agreement-confirm-box h3{font-size:17px;font-weight:600;margin:0 0 5px}.agreement-confirm-box p{color:#525252;font-size:12px;margin:0 0 12px}.agreement-confirm-table-wrap{border:1px solid #ececf1;border-radius:8px;overflow:hidden}.agreement-confirm-table{border-collapse:collapse;margin:0;width:100%}.agreement-confirm-table th,.agreement-confirm-table td{border-bottom:1px solid #ececf1;font-size:12px;padding:7px 9px;vertical-align:top}.agreement-confirm-table tr:last-child th,.agreement-confirm-table tr:last-child td{border-bottom:0}.agreement-confirm-table th{background:#f7f7f8;color:#404040;font-weight:600;width:34%}.agreement-confirm-table td{color:#171717;font-weight:500;overflow-wrap:anywhere}.agreement-confirm-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:14px}.agreement-confirm-actions .btn{border-radius:8px;min-height:38px;min-width:120px}@media(max-width:520px){.agreement-confirm-actions{flex-direction:column-reverse}.agreement-confirm-actions .btn{width:100%}}';
            modal.appendChild(style);
            document.body.appendChild(modal);
            var escHandler = null;
            function close(value) {
                if (escHandler) document.removeEventListener("keydown", escHandler);
                if (modal.parentNode) modal.parentNode.removeChild(modal);
                resolve(!!value);
            }
            modal.addEventListener("click", function (event) {
                var button = event.target.closest("button[data-action]");
                if (button) {
                    close(button.getAttribute("data-action") === "save");
                    return;
                }
                if (event.target.className === "agreement-confirm-backdrop") close(false);
            });
            escHandler = function (event) {
                if (event.key === "Escape") close(false);
            };
            document.addEventListener("keydown", escHandler);
            var save = modal.querySelector("button[data-action='save']");
            if (save) save.focus();
        });
    }

    function promptAgreementRowCancel() {
        return new Promise(function (resolve) {
            if (!document.getElementById("agreement_cancel_reason_style")) {
                var style = document.createElement("style");
                style.id = "agreement_cancel_reason_style";
                style.textContent = '#agreement_cancel_reason_modal{position:fixed;inset:0;z-index:10070;display:flex;align-items:center;justify-content:center;padding:14px}#agreement_cancel_reason_modal .cancel-reason-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.62)}#agreement_cancel_reason_modal .cancel-reason-box{position:relative;z-index:1;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 18px 48px rgba(15,23,42,.28);max-width:360px;padding:13px 14px;width:min(360px,100%)}#agreement_cancel_reason_modal .cancel-reason-box h3{color:#171717;font-size:15px;font-weight:600;line-height:1.25;margin:0 0 4px;padding:0;border:0}#agreement_cancel_reason_modal .cancel-reason-box p{color:#525252;font-size:11.5px;line-height:1.35;margin:0 0 9px}#agreement_cancel_reason_modal .cancel-reason-box label{color:#262626;display:block;font-size:11.5px;font-weight:600;margin:8px 0 5px}#agreement_cancel_reason_modal .cancel-reason-box textarea{background:#fff;border:1px solid #cbd5e1;border-radius:6px;box-shadow:none;color:#171717;font-size:12px;line-height:1.35;min-height:68px;padding:7px 8px;resize:vertical;width:100%}#agreement_cancel_reason_modal .cancel-reason-box textarea:focus{border-color:#64748b;box-shadow:0 0 0 2px rgba(100,116,139,.15);outline:0}#agreement_cancel_reason_modal .cancel-reason-error{display:none;color:#dc2626;font-size:11.5px;margin-top:5px}#agreement_cancel_reason_modal .cancel-reason-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:11px}#agreement_cancel_reason_modal .cancel-reason-actions .btn{border-radius:6px;min-height:32px;min-width:88px;padding:5px 10px}@media(max-width:430px){#agreement_cancel_reason_modal .cancel-reason-actions{flex-direction:column-reverse}#agreement_cancel_reason_modal .cancel-reason-actions .btn{width:100%}}';
                document.head.appendChild(style);
            }
            var overlay = document.createElement("div");
            overlay.id = "agreement_cancel_reason_modal";
            overlay.innerHTML =
                '<div class="cancel-reason-backdrop"></div>' +
                '<div class="cancel-reason-box" role="dialog" aria-modal="true">' +
                    '<h3>Cancel row?</h3>' +
                    '<p>This row will stay in the record, deduct from totals, and the customer will be notified after saving.</p>' +
                    '<label>Cancellation reason *</label>' +
                    '<textarea class="form-control" rows="3" maxlength="300" placeholder="Example: Stone withdrawn by customer"></textarea>' +
                    '<div class="cancel-reason-error">Please enter cancellation reason.</div>' +
                    '<div class="cancel-reason-actions">' +
                        '<button type="button" class="btn btn-default" data-action="keep">Keep Row</button>' +
                        '<button type="button" class="btn btn-danger" data-action="cancel">Cancel Row</button>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(overlay);
            var textarea = overlay.querySelector("textarea");
            var error = overlay.querySelector(".cancel-reason-error");
            function close(value) {
                if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                resolve(value);
            }
            overlay.addEventListener("click", function (event) {
                var button = event.target.closest("button[data-action]");
                if (!button) {
                    if (event.target === overlay || event.target.classList.contains("cancel-reason-backdrop")) close(null);
                    return;
                }
                if (button.getAttribute("data-action") === "keep") {
                    close(null);
                    return;
                }
                var reason = String(textarea.value || "").trim();
                if (!reason) {
                    error.style.display = "block";
                    textarea.focus();
                    return;
                }
                close(reason);
            });
            window.setTimeout(function () { textarea.focus(); }, 0);
        });
    }

    function validateRowsBeforeSubmit() {
        var rows = Array.prototype.slice.call(body.querySelectorAll("tr"));
        if (!rows.length) {
            AppToast.error("Add at least one stone detail row.");
            return false;
        }
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            if (isRowCancelled(row)) continue;
            var category = row.querySelector(".rate-category");
            if (!category || !category.value) {
                AppToast.error("Select category for stone row " + (i + 1) + ".");
                if (category) category.focus();
                return false;
            }
            var rate = selectedRateForRow(row);
            if (!rate) {
                AppToast.error("Selected category is not available in rate master.");
                if (category) category.focus();
                return false;
            }
            var result = agreementAmountForRow(row, rate);
            if (result.warning) {
                AppToast.error(result.warning + " Row " + (i + 1) + ".");
                var target = result.warning.toLowerCase().indexOf("bead") !== -1
                    ? row.querySelector('[name$="[bead_length]"]')
                    : row.querySelector('[name$="[pcs]"]');
                if (target) target.focus();
                return false;
            }
        }
        return true;
    }

    function updateTestingCharges() {
        var total = 0;
        Array.prototype.forEach.call(body.querySelectorAll(".amount-input"), function (input) {
            if (isRowCancelled(input.closest("tr"))) return;
            var value = parseFloat(String(input.value).replace(/[^0-9.\-]/g, ""));
            if (!isNaN(value)) total += value;
        });
        document.getElementById("testing_charges").value = total.toFixed(2);
        updateDueAmount();
    }

    function updatePcsTotal() {
        var total = 0;
        Array.prototype.forEach.call(body.querySelectorAll(".pcs-input"), function (input) {
            if (isRowCancelled(input.closest("tr"))) return;
            var value = parseInt(String(input.value).replace(/[^0-9\-]/g, ""), 10);
            if (!isNaN(value) && value > 0) total += value;
        });
        $("#pcs_total_display").val(total);
    }

    function moneyValue(selector) {
        var value = parseFloat(String($(selector).val() || "").replace(/[^0-9.\-]/g, ""));
        return isNaN(value) ? 0 : value;
    }

    function updateDueAmount() {
        var charges = moneyValue("#testing_charges");
        var paid = moneyValue("#payment_cash") + moneyValue("#payment_cheque") + moneyValue("#payment_neft") + moneyValue("#payment_card") + moneyValue("#payment_tds");
        var cancelledAmount = 0;
        Array.prototype.forEach.call(body.querySelectorAll(".amount-input"), function (input) {
            if (!isRowCancelled(input.closest("tr"))) return;
            var value = parseFloat(String(input.value).replace(/[^0-9.\-]/g, ""));
            if (!isNaN(value) && value > 0) cancelledAmount += value;
        });
        var balance = charges - paid;
        $("#due_amount").val(Math.max(0, balance).toFixed(2));
        $("#refund_amount").val(Math.max(cancelledAmount, -balance, 0).toFixed(2));
    }

    function escapeHtml(value) {
        return $("<div>").text(value == null ? "" : String(value)).html();
    }

    function memberStatus() {
        var selected = form.querySelector("input[name='member_status']:checked");
        return selected ? selected.value : "Non Member";
    }

    function selectedRate(rate) {
        if (!rate) return 0;
        return memberStatus() === "Member" ? Number(rate.rate_member || 0) : Number(rate.rate_non_member || 0);
    }

    function mouTierCode(value) {
        value = String(value || "").toUpperCase().replace(/[^A-Z]/g, "");
        if (value.indexOf("PLATINUM") !== -1) return "PLATINUM";
        if (value.indexOf("GOLD") !== -1) return "GOLD";
        if (value.indexOf("SILVER") !== -1) return "SILVER";
        return "";
    }

    function mouDiscountPercent() {
        switch (mouTierCode($("#mou_cdc").val())) {
            case "SILVER": return 20;
            case "GOLD": return 25;
            case "PLATINUM": return 30;
            default: return 0;
        }
    }

    function rateAllowsCdcDiscount(rate) {
        return String(rate && rate.cdc ? rate.cdc : "").trim().toUpperCase() === "Y";
    }

    function numericInput(row, name) {
        var input = row.querySelector('[name$="[' + name + ']"]');
        var value = input ? parseFloat(String(input.value || "").replace(/[^0-9.\-]/g, "")) : 0;
        return isNaN(value) ? 0 : value;
    }

    function rateCodeValue(rate) {
        return parseInt(String(rate && rate.rate_code ? rate.rate_code : "").replace(/[^0-9\-]/g, ""), 10) || 0;
    }

    function normalizeCategory(value) {
        return String(value || "").replace(/\s+/g, " ").trim().toUpperCase();
    }

    function currentLocationName() {
        return String(form.dataset.locationName || "ALL").toUpperCase();
    }

    function matchingRules(rateCode) {
        var location = currentLocationName();
        return rateConditionRules.filter(function (rule) {
            var ruleCode = String(rule.rate_code || "").toUpperCase();
            var ruleLocation = String(rule.branch_location || "ALL").toUpperCase();
            return Number(rule.active) === 1 && ruleCode === String(rateCode).toUpperCase() && (ruleLocation === "ALL" || ruleLocation === location);
        }).sort(function (a, b) {
            return Number(a.priority || 0) - Number(b.priority || 0);
        });
    }

    function agreementAmountForRow(row, rate) {
        var rateCode = rateCodeValue(rate);
        var rateValue = numericInput(row, "rate");
        var pcs = numericInput(row, "pcs");
        var chargeablePcs = Math.max(1, pcs);
        var diaWt = numericInput(row, "dia_wt");
        var beadLength = numericInput(row, "bead_length");
        var chargeableBeadLength = beadLength;
        var amount = chargeablePcs * rateValue;
        var warning = "";
        var topupInput = row.querySelector('[name$="[topup]"]');
        var topupSelected = !!(topupInput && topupInput.checked);

        matchingRules(rateCode).forEach(function (rule) {
            var value1 = Number(rule.value1 || 0);
            switch (rule.rule_type) {
                case "minimum_pcs":
                    if (pcs < value1) warning = "Minimum Qty For Packet Lot is " + parseInt(value1, 10) + ".";
                    break;
                case "minimum_bead_length":
                    if (beadLength < value1) {
                        warning = "Minimum bead length is " + value1 + ".";
                        chargeableBeadLength = value1;
                    }
                    break;
                case "amount_pcs_rate":
                    amount = chargeablePcs * rateValue;
                    break;
                case "amount_bead_length_10":
                    amount = rateValue * (chargeableBeadLength / 10);
                    break;
                case "amount_dia_weight":
                    amount = rateValue * diaWt;
                    break;
                case "amount_min_dia_weight":
                    amount = rateValue * Math.max(diaWt, value1);
                    break;
                case "minimum_amount_fixed":
                    if (amount < value1) amount = value1;
                    break;
                case "minimum_amount_rate":
                    if (amount < value1) amount = rateValue;
                    break;
                case "diamond_topup_after_first":
                    if (!topupSelected) break;
                    var wholeCarats = Math.floor(diaWt);
                    var fraction = diaWt - wholeCarats;
                    var extraAmount = fraction === 0 ? (wholeCarats - 1) * value1 : wholeCarats * value1;
                    amount += Math.max(0, extraAmount);
                    break;
            }
        });

        var discountPercent = rateAllowsCdcDiscount(rate) ? mouDiscountPercent() : 0;
        var discountAmount = discountPercent > 0 ? Math.round((amount * discountPercent / 100) * 100) / 100 : 0;
        return {
            grossAmount: Math.max(0, amount),
            discountPercent: discountPercent,
            discountAmount: Math.max(0, discountAmount),
            amount: Math.max(0, amount - discountAmount),
            warning: warning
        };
    }

    function buildRateOptions() {
        rateOptionsHtml = rateList.map(function (rate) {
            var label = rate.rate_code ? rate.description + " (Code: " + rate.rate_code + ")" : rate.description;
            return '<option value="' + escapeHtml(rate.description) + '" label="' + escapeHtml(label) + '"></option>';
        }).join("");
        $("#rate_category_options").html(rateOptionsHtml);
    }

    function rateById(id) {
        id = parseInt(id || "0", 10);
        for (var i = 0; i < rateList.length; i++) {
            if (parseInt(rateList[i].id, 10) === id) return rateList[i];
        }
        return null;
    }

    function rateByDescription(description) {
        var wanted = normalizeCategory(description);
        if (!wanted) return null;
        for (var i = 0; i < rateList.length; i++) {
            if (normalizeCategory(rateList[i].description) === wanted) return rateList[i];
        }
        return null;
    }

    function applyRateToRow(row) {
        if (isRowCancelled(row)) return;
        var select = row.querySelector(".rate-category");
        if (!select) return;
        var rate = rateByDescription(select.value);
        var value = selectedRate(rate);
        var formatted = value > 0 ? value.toFixed(2) : "";
        var rateInput = row.querySelector(".rate-input");
        if (rateInput) rateInput.value = formatted;
        calculateRowAmount(row, true);
    }

    function selectedRateForRow(row) {
        var select = row.querySelector(".rate-category");
        if (!select) return null;
        return rateByDescription(select.value);
    }

    function calculateRowAmount(row, showWarning) {
        if (isRowCancelled(row)) {
            updateTestingCharges();
            return;
        }
        var amountInput = row.querySelector(".amount-input");
        var discountInput = row.querySelector(".discount-input");
        var discountPercentInput = row.querySelector(".discount-percent-input");
        if (!amountInput) return;
        var rate = selectedRateForRow(row);
        if (!rate) {
            amountInput.value = "";
            if (discountInput) discountInput.value = "";
            if (discountPercentInput) discountPercentInput.value = "";
            updateTestingCharges();
            return;
        }
        var result = agreementAmountForRow(row, rate);
        if (discountInput) {
            discountInput.value = result.discountAmount > 0 ? result.discountAmount.toFixed(2) + " (" + result.discountPercent + "%)" : "";
        }
        if (discountPercentInput) discountPercentInput.value = result.discountPercent > 0 ? result.discountPercent.toFixed(2) : "";
        amountInput.value = result.amount > 0 ? result.amount.toFixed(2) : "";
        if (showWarning && result.warning) AppToast.error(result.warning);
        updateTestingCharges();
    }

    function refreshAllRates() {
        Array.prototype.forEach.call(body.querySelectorAll("tr"), applyRateToRow);
        syncRowActionButtons();
    }

    function loadRates() {
        $.getJSON("rate-list.php")
            .done(function (response) {
                rateList = response && response.rates ? response.rates : [];
                buildRateOptions();
                refreshAllRates();
            });
    }

    function openRateCategoryModal() {
        if (!rateCategoryForm) return;
        rateCategoryForm.reset();
        $("#new_rate_member,#new_rate_non_member").val("0");
        $("#rate_category_modal").modal("show");
        window.setTimeout(function () { $("#new_rate_description").focus(); }, 250);
    }

    function saveRateCategory(event) {
        event.preventDefault();
        var description = $.trim($("#new_rate_description").val());
        if (!description) {
            AppToast.error("Category name is required.");
            $("#new_rate_description").focus();
            return;
        }
        var oldHtml = rateCategorySubmit ? rateCategorySubmit.innerHTML : "";
        if (rateCategorySubmit) {
            rateCategorySubmit.disabled = true;
            rateCategorySubmit.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
        }
        $.ajax({
            url: "rate-save.php",
            method: "POST",
            data: $(rateCategoryForm).serialize(),
            dataType: "json"
        }).done(function (response) {
            if (!response || response.status !== "success") {
                AppToast.error(response && response.message ? response.message : "Unable to save category.");
                return;
            }
            var saved = response.rate || null;
            if (saved) {
                var existingIndex = -1;
                for (var i = 0; i < rateList.length; i++) {
                    if (parseInt(rateList[i].id, 10) === parseInt(saved.id, 10)) existingIndex = i;
                }
                if (existingIndex >= 0) rateList[existingIndex] = saved;
                else rateList.push(saved);
                rateList.sort(function (a, b) {
                    return String(a.description || "").localeCompare(String(b.description || ""));
                });
                buildRateOptions();
                var focused = body.querySelector(".rate-category:focus");
                if (focused && !focused.value) {
                    focused.value = saved.description || "";
                    applyRateToRow(focused.closest("tr"));
                }
            } else {
                loadRates();
            }
            $("#rate_category_modal").modal("hide");
            AppToast.success("Category saved.");
        }).fail(function (xhr) {
            var message = "Unable to save category.";
            try { message = JSON.parse(xhr.responseText).message || message; } catch (error) {}
            AppToast.error(message);
        }).always(function () {
            if (rateCategorySubmit) {
                rateCategorySubmit.disabled = false;
                rateCategorySubmit.innerHTML = oldHtml || '<i class="fa fa-save"></i> Save Category';
            }
        });
    }

    function loadRateConditions() {
        $.getJSON("rate-condition-list.php")
            .done(function (response) {
                rateConditionRules = response && response.rules ? response.rules : [];
                refreshAllRates();
            });
    }

    function buildColourOptions(colours) {
        colourOptionsHtml = (colours || []).map(function (colour) {
            return '<option value="' + escapeHtml(colour) + '"></option>';
        }).join("");
        $("#colour_master_options").html(colourOptionsHtml);
    }

    function loadColours() {
        $.getJSON("colour-list.php")
            .done(function (response) {
                buildColourOptions(response && response.colours ? response.colours : []);
            });
    }

    function hideCustomerSuggest() {
        if (customerSuggest) customerSuggest.classList.remove("active");
    }

    function customerMeta(customer) {
        var parts = [];
        if (customer.mobile_no) parts.push(customer.mobile_no);
        if (customer.email) parts.push(customer.email);
        if (customer.gst_no) parts.push("GST: " + customer.gst_no);
        return parts.join(" | ");
    }

    function renderCustomerSuggest(customers) {
        if (!customerSuggest) return;
        customerResults = customers || [];
        if (!customerResults.length) {
            customerSuggest.innerHTML = '<div class="customer-option"><span>No matching customer found</span></div>';
            customerSuggest.classList.add("active");
            return;
        }
        customerSuggest.innerHTML = customerResults.map(function (customer, index) {
            return '<div class="customer-option" data-index="' + index + '">' +
                '<strong>' + $("<div>").text(customer.customer_name || "").html() + '</strong>' +
                '<span>' + $("<div>").text(customerMeta(customer)).html() + '</span>' +
            '</div>';
        }).join("");
        customerSuggest.classList.add("active");
    }

    function searchCustomers(query) {
        if (!customerSuggest) return;
        $.getJSON("customer-search.php", { q: query || "" })
            .done(function (response) {
                renderCustomerSuggest(response && response.customers ? response.customers : []);
            })
            .fail(function () {
                hideCustomerSuggest();
            });
    }

    function selectCustomer(customer) {
        if (!customer) return;
        if (customerIdInput) customerIdInput.value = customer.id || "";
        $("#customer_name").val(customer.customer_name || "");
        $("#depositor_name").val(customer.depositor_name || customer.customer_name || "");
        $("#address").val(customer.address || "");
        $("#mobile_no").val(customer.mobile_no || "");
        $("#email").val(customer.email || "");
        $("#id_no").val(customer.id_no || "");
        $("#gst_no").val(customer.gst_no || "");
        $("#mou_cdc").val(mouTierCode(customer.mou_cdc || ""));
        if (customer.member_status === "Member") {
            $("input[name='member_status'][value='Member']").prop("checked", true);
        } else {
            $("input[name='member_status'][value='Non Member']").prop("checked", true);
        }
        if (!body.querySelector("tr")) addRow(false);
        refreshAllRates();
        hideCustomerSuggest();
    }

    function openCustomerModal() {
        if (!addCustomerForm) return;
        addCustomerForm.reset();
        $("#new_customer_name").val($("#customer_name").val() || "");
        $("#new_depositor_name").val($("#depositor_name").val() || "");
        $("#new_mobile_no").val($("#mobile_no").val() || "");
        $("#new_email").val($("#email").val() || "");
        $("#new_gst_no").val($("#gst_no").val() || "");
        $("#new_id_no").val($("#id_no").val() || "");
        $("#new_address").val($("#address").val() || "");
        $("#new_member_status").val($("input[name='member_status']:checked").val() || "Non Member");
        $("#new_mou_cdc").val($("#mou_cdc").val() || "");
        $("#customer_add_modal").modal("show");
        window.setTimeout(function () { $("#new_customer_name").focus(); }, 250);
    }

    function saveCustomerFromModal(event) {
        event.preventDefault();
        var name = $.trim($("#new_customer_name").val());
        if (!name) {
            AppToast.error("Customer name is required.");
            $("#new_customer_name").focus();
            return;
        }
        var oldHtml = addCustomerSubmit ? addCustomerSubmit.innerHTML : "";
        if (addCustomerSubmit) {
            addCustomerSubmit.disabled = true;
            addCustomerSubmit.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
        }
        $.ajax({
            url: "customer-save.php",
            method: "POST",
            data: $(addCustomerForm).serialize(),
            dataType: "json"
        }).done(function (response) {
            if (!response || response.status !== "success") {
                AppToast.error(response && response.message ? response.message : "Unable to save customer.");
                return;
            }
            selectCustomer(response.customer);
            hideCustomerSuggest();
            $("#customer_add_modal").modal("hide");
            AppToast.success("Customer saved and selected.");
        }).fail(function (xhr) {
            var message = "Unable to save customer.";
            try { message = JSON.parse(xhr.responseText).message || message; } catch (error) {}
            AppToast.error(message);
        }).always(function () {
            if (addCustomerSubmit) {
                addCustomerSubmit.disabled = false;
                addCustomerSubmit.innerHTML = oldHtml || '<i class="fa fa-save"></i> Save Customer';
            }
        });
    }

    function signatureMode() {
        var selected = form.querySelector("input[name='signature_mode']:checked");
        return selected ? selected.value : "manual";
    }

    function resizeSignaturePad() {
        if (!signatureCanvas || !signatureContext) return;
        var ratio = Math.max(window.devicePixelRatio || 1, 1);
        var rect = signatureCanvas.getBoundingClientRect();
        var oldImage = signatureHasInk ? signatureCanvas.toDataURL("image/png") : "";
        signatureCanvas.width = Math.max(1, Math.round(rect.width * ratio));
        signatureCanvas.height = Math.max(1, Math.round(rect.height * ratio));
        signatureContext.setTransform(ratio, 0, 0, ratio, 0, 0);
        signatureContext.lineCap = "round";
        signatureContext.lineJoin = "round";
        signatureContext.lineWidth = 2.2;
        signatureContext.strokeStyle = "#111";
        if (oldImage) {
            var image = new Image();
            image.onload = function () {
                signatureContext.drawImage(image, 0, 0, rect.width, rect.height);
            };
            image.src = oldImage;
        }
    }

    function clearSignaturePad() {
        if (!signatureCanvas || !signatureContext) return;
        signatureContext.clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
        signatureHasInk = false;
        signatureInput.value = "";
    }

    function loadSignatureImage(dataUrl) {
        dataUrl = String(dataUrl || "");
        if (!signatureCanvas || !signatureContext || !/^data:image\/png;base64,/i.test(dataUrl)) {
            return;
        }
        signatureInput.value = dataUrl;
        signatureHasInk = true;
        window.setTimeout(function () {
            resizeSignaturePad();
            var rect = signatureCanvas.getBoundingClientRect();
            var image = new Image();
            image.onload = function () {
                var ratio = Math.max(window.devicePixelRatio || 1, 1);
                var naturalWidth = Math.max(1, image.naturalWidth / ratio);
                var naturalHeight = Math.max(1, image.naturalHeight / ratio);
                var scale = Math.min(1, rect.width / naturalWidth, rect.height / naturalHeight);
                var drawWidth = naturalWidth * scale;
                var drawHeight = naturalHeight * scale;
                var drawX = Math.max(0, (rect.width - drawWidth) / 2);
                var drawY = Math.max(0, (rect.height - drawHeight) / 2);
                signatureContext.clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
                signatureContext.drawImage(image, drawX, drawY, drawWidth, drawHeight);
                signatureHasInk = true;
                signatureInput.value = dataUrl;
            };
            image.src = dataUrl;
        }, 40);
    }

    function signatureToTrimmedImage() {
        var width = signatureCanvas.width;
        var height = signatureCanvas.height;
        var pixels = signatureContext.getImageData(0, 0, width, height).data;
        var top = height;
        var left = width;
        var right = 0;
        var bottom = 0;
        for (var y = 0; y < height; y++) {
            for (var x = 0; x < width; x++) {
                if (pixels[((y * width + x) * 4) + 3] > 0) {
                    if (x < left) left = x;
                    if (x > right) right = x;
                    if (y < top) top = y;
                    if (y > bottom) bottom = y;
                }
            }
        }
        if (right <= left || bottom <= top) return "";
        var padding = Math.round(16 * Math.max(window.devicePixelRatio || 1, 1));
        left = Math.max(0, left - padding);
        top = Math.max(0, top - padding);
        right = Math.min(width, right + padding);
        bottom = Math.min(height, bottom + padding);
        var trimmed = document.createElement("canvas");
        trimmed.width = right - left;
        trimmed.height = bottom - top;
        trimmed.getContext("2d").drawImage(signatureCanvas, left, top, trimmed.width, trimmed.height, 0, 0, trimmed.width, trimmed.height);
        return trimmed.toDataURL("image/png");
    }

    function pointFromEvent(event) {
        var source = event.touches && event.touches.length ? event.touches[0] : event;
        var rect = signatureCanvas.getBoundingClientRect();
        return { x: source.clientX - rect.left, y: source.clientY - rect.top };
    }

    function startSignature(event) {
        if (signatureMode() !== "esign") return;
        event.preventDefault();
        signatureDrawing = true;
        var point = pointFromEvent(event);
        signatureContext.beginPath();
        signatureContext.moveTo(point.x, point.y);
    }

    function drawSignature(event) {
        if (!signatureDrawing) return;
        event.preventDefault();
        var point = pointFromEvent(event);
        signatureContext.lineTo(point.x, point.y);
        signatureContext.stroke();
        signatureHasInk = true;
    }

    function stopSignature() {
        signatureDrawing = false;
    }

    function refreshSignatureMode() {
        var esign = signatureMode() === "esign";
        if (signatureWrap) signatureWrap.classList.toggle("active", esign);
        if (manualNote) manualNote.style.display = esign ? "none" : "";
        if (!esign) clearSignaturePad();
        window.setTimeout(resizeSignaturePad, 0);
    }

    if (signatureCanvas && signatureContext) {
        signatureCanvas.addEventListener("mousedown", startSignature);
        signatureCanvas.addEventListener("mousemove", drawSignature);
        document.addEventListener("mouseup", stopSignature);
        signatureCanvas.addEventListener("touchstart", startSignature, { passive: false });
        signatureCanvas.addEventListener("touchmove", drawSignature, { passive: false });
        document.addEventListener("touchend", stopSignature);
        window.addEventListener("resize", resizeSignaturePad);
        if (clearSignature) clearSignature.addEventListener("click", clearSignaturePad);
        Array.prototype.forEach.call(form.querySelectorAll("input[name='signature_mode']"), function (input) {
            input.addEventListener("change", refreshSignatureMode);
        });
        refreshSignatureMode();
    }

    if (customerInput && customerSuggest) {
        customerInput.addEventListener("focus", function () {
            searchCustomers(customerInput.value.trim());
        });
        customerInput.addEventListener("input", function () {
            if (customerIdInput && customerIdInput.value) {
                customerIdInput.value = "";
                body.innerHTML = "";
                updatePcsTotal();
                updateTestingCharges();
            }
            window.clearTimeout(customerTimer);
            customerTimer = window.setTimeout(function () {
                searchCustomers(customerInput.value.trim());
            }, 180);
        });
        customerSuggest.addEventListener("mousedown", function (event) {
            var option = event.target.closest(".customer-option");
            if (!option || option.dataset.index === undefined) return;
            event.preventDefault();
            selectCustomer(customerResults[parseInt(option.dataset.index, 10)]);
        });
        document.addEventListener("mousedown", function (event) {
            if (event.target === customerInput || customerSuggest.contains(event.target)) return;
            hideCustomerSuggest();
        });
    }

    if (addCustomerButton) {
        addCustomerButton.addEventListener("click", openCustomerModal);
    }
    if (addCustomerForm) {
        addCustomerForm.addEventListener("submit", saveCustomerFromModal);
    }

    $("#load_agreement_edit").on("click", function () {
        var agreementNo = $.trim($("#edit_agreement_lookup").val());
        if (!agreementNo) {
            AppToast.error("Enter agreement number to edit.");
            $("#edit_agreement_lookup").focus();
            return;
        }
        var button = this;
        var oldHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Loading...';
        $.getJSON("agreement-load.php", { agreement_no: agreementNo })
            .done(function (response) {
                if (!response || response.status !== "success" || !response.agreement) {
                    AppToast.error(response && response.message ? response.message : "Agreement not found.");
                    return;
                }
                populateAgreementForm(response.agreement);
                AppToast.success("Agreement #" + response.agreement.agreement_no + " loaded for editing.");
            })
            .fail(function (xhr) {
                var message = "Unable to load agreement.";
                try { message = JSON.parse(xhr.responseText).message || message; } catch (error) {}
                AppToast.error(message);
            })
            .always(function () {
                button.disabled = false;
                button.innerHTML = oldHtml;
            });
    });

    $("#edit_agreement_lookup").on("keydown", function (event) {
        if (event.key === "Enter") {
            event.preventDefault();
            $("#load_agreement_edit").trigger("click");
        }
    });

    $("#update_agreement_status").on("click", function () {
        var agreementId = $("#edit_agreement_id").val();
        var status = $("#agreement_status_select").val();
        if (!agreementId) {
            AppToast.error("Load an agreement before updating status.");
            return;
        }
        var button = this;
        var oldHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sending...';
        $.ajax({
            url: "agreement-status-update.php",
            method: "POST",
            dataType: "json",
            data: { agreement_id: agreementId, agreement_status: status }
        }).done(function (response) {
            if (!response || (response.status !== "success" && response.status !== "partial" && response.status !== "warning")) {
                AppToast.error(response && response.message ? response.message : "Unable to update agreement status.");
                return;
            }
            if (response.agreement_status) {
                $("#agreement_status_select").val(response.agreement_status);
            }
            if (response.delivery_date) $("#delivery_date").val(response.delivery_date);
            if (response.delivery_time) $("#delivery_time").val(response.delivery_time);
            var message = response.message || "Agreement status updated.";
            if (response.status === "warning") AppToast.info(message);
            else if (response.status === "partial") AppToast.info(message);
            else AppToast.success(message);
        }).fail(function (xhr) {
            var message = "Unable to update agreement status.";
            try { message = JSON.parse(xhr.responseText).message || message; } catch (error) {}
            AppToast.error(message);
        }).always(function () {
            button.disabled = false;
            button.innerHTML = oldHtml;
            if (!$("#edit_agreement_id").val()) button.disabled = true;
        });
    });

    document.getElementById("add_agreement_row").addEventListener("click", function () {
        if (!requireSelectedCustomer()) return;
        addRow(true);
    });

    body.addEventListener("click", function (event) {
        var cancelButton = event.target.closest(".cancel-row");
        var undoButton = event.target.closest(".undo-cancel-row");
        if (cancelButton || undoButton) {
            var statusRow = (cancelButton || undoButton).closest("tr");
            var statusInput = statusRow ? statusRow.querySelector(".row-status-input") : null;
            if (!statusInput) return;
            if (cancelButton) {
                promptAgreementRowCancel().then(function (reason) {
                    if (!reason) return;
                    statusInput.value = "cancelled";
                    var reasonInput = statusRow.querySelector(".row-cancel-reason-input");
                    if (reasonInput) reasonInput.value = reason;
                    AppToast.info("Row cancelled. Save agreement to update records.");
                    syncRowState(statusRow);
                    updatePcsTotal();
                    updateTestingCharges();
                });
                return;
            } else {
                statusInput.value = "active";
                var undoReasonInput = statusRow.querySelector(".row-cancel-reason-input");
                if (undoReasonInput) undoReasonInput.value = "";
                AppToast.info("Row restored. Save agreement to update records.");
            }
            syncRowState(statusRow);
            updatePcsTotal();
            updateTestingCharges();
            return;
        }
        var button = event.target.closest(".remove-row");
        if (!button) return;
        if (form.dataset.editMode === "1") return;
        if (body.querySelectorAll("tr").length === 1) {
            button.closest("tr").querySelectorAll("input").forEach(function (input) {
                if (input.classList.contains("ref-input")) return;
                if (input.type === "checkbox") input.checked = false;
                else if (input.classList.contains("pcs-input")) input.value = "1";
                else input.value = "";
            });
            button.closest("tr").querySelectorAll("select").forEach(function (select) {
                if (select.name.indexOf("[a4_card]") !== -1) select.value = "A4";
                else if (select.name.indexOf("_unit]") !== -1) select.value = "ct";
                else select.value = "";
            });
            updatePcsTotal();
            updateTestingCharges();
            return;
        }
        button.closest("tr").remove();
        renumberRows();
        syncRowActionButtons();
        updatePcsTotal();
        updateTestingCharges();
    });

    body.addEventListener("input", function (event) {
        if (event.target.classList.contains("rate-category")) {
            applyRateToRow(event.target.closest("tr"));
        }
        if (event.target.classList.contains("amount-input")) updateTestingCharges();
        if (event.target.classList.contains("pcs-input")) updatePcsTotal();
        if (
            event.target.classList.contains("pcs-input") ||
            event.target.classList.contains("rate-input") ||
            event.target.name.indexOf("[dia_wt]") !== -1 ||
            event.target.name.indexOf("[bead_length]") !== -1
        ) {
            calculateRowAmount(event.target.closest("tr"), false);
        }
    });

    $("#testing_charges,#payment_cash,#payment_cheque,#payment_neft,#payment_card,#payment_tds").on("input", updateDueAmount);
    $("#mou_cdc").on("change", refreshAllRates);
    $("#collection_center_id").on("change", function () {
        form.dataset.locationLetter = selectedCollectionCode();
        if (form.dataset.editMode !== "1") {
            renumberRows();
        }
    });

    body.addEventListener("change", function (event) {
        if (event.target.classList.contains("rate-category")) {
            applyRateToRow(event.target.closest("tr"));
        }
        if (event.target.classList.contains("topup-input")) {
            calculateRowAmount(event.target.closest("tr"), false);
        }
    });

    Array.prototype.forEach.call(form.querySelectorAll("input[name='member_status']"), function (input) {
        input.addEventListener("change", refreshAllRates);
    });

    if (addRateCategoryButton) addRateCategoryButton.addEventListener("click", openRateCategoryModal);
    if (rateCategoryForm) rateCategoryForm.addEventListener("submit", saveRateCategory);

    form.addEventListener("submit", function (event) {
        event.preventDefault();
        if (!document.getElementById("customer_name").value.trim()) {
            AppToast.error("Customer name is required.");
            document.getElementById("customer_name").focus();
            return;
        }
        if (!requireSelectedCustomer()) return;
        if (!hasItemRow() && form.dataset.editMode !== "1") {
            AppToast.error("Add at least one stone detail row.");
            var firstInput = body.querySelector("input,select");
            if (firstInput) firstInput.focus();
            return;
        }
        if (!validateRowsBeforeSubmit()) return;
        if (signatureMode() === "esign" && !signatureHasInk) {
            AppToast.error("Please draw the customer signature or choose Manual Signature.");
            signatureCanvas.focus();
            return;
        }
        confirmAgreementSave().then(function (confirmed) {
            if (!confirmed) return;
            if (signatureMode() === "esign" && signatureCanvas && signatureHasInk) {
                signatureInput.value = signatureToTrimmedImage();
            } else {
                signatureInput.value = "";
            }
            var data = new FormData(form);
            submit.disabled = true;
            submit.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
            $.ajax({ url: "agreement-save.php", method: "POST", data: data, processData: false, contentType: false, dataType: "json" })
                .done(function (response) {
                    if (!response || response.status !== "success") {
                        AppToast.error(response && response.message ? response.message : "Unable to save agreement.");
                        return;
                    }
                    $("#saved_agreement_no").text("#" + response.agreement_no);
                    $("#saved_agreement_print").attr("href", response.print_url);
                    $("#agreement_status").css("display", "flex");
                    if (response.agreement_no && !response.edit_mode) {
                        var nextAgreementNo = parseInt(response.agreement_no, 10) + 1;
                        freshAgreementNo = String(nextAgreementNo);
                        form.dataset.agreementNo = String(nextAgreementNo);
                        $("#agreement_serial_no").val(nextAgreementNo);
                    }
                    if (response.next_certificate_no) {
                        freshNextCertiNo = String(parseInt(response.next_certificate_no, 10) || 1);
                        form.dataset.nextCertiNo = String(parseInt(response.next_certificate_no, 10) || 1);
                        renumberRows();
                    }
                    AppToast.success((response.edit_mode ? "Agreement updated. Serial #" : "Agreement saved. Serial #") + response.agreement_no + ".");
                    if (response.row_cancellation_message) {
                        if (response.row_cancellation_whatsapp && response.row_cancellation_whatsapp.ok) {
                            AppToast.success(response.row_cancellation_message);
                        } else {
                            AppToast.warning(response.row_cancellation_message);
                        }
                    }
                    var printUrl = response.print_url || ("agreement-print.php?id=" + response.id);
                    var labelsUrl = response.labels_url || ("agreement-labels-print.php?id=" + response.id);
                    if (window.Promise) {
                        showAgreementSavedActions(printUrl, labelsUrl).then(resetAgreementForm);
                    } else if (window.confirm("Agreement saved. Generate agreement now?")) {
                        openAgreementPrint(printUrl);
                        resetAgreementForm();
                    } else if (window.confirm("Generate labels now?")) {
                        openAgreementPrint(labelsUrl);
                        resetAgreementForm();
                    } else {
                        resetAgreementForm();
                    }
                })
                .fail(function (xhr) {
                    var message = "Unable to save agreement.";
                    try { message = JSON.parse(xhr.responseText).message || message; } catch (error) {}
                    AppToast.error(message);
                })
                .always(function () {
                    submit.disabled = false;
                    submit.innerHTML = form.dataset.editMode === "1" ? '<i class="fa fa-save"></i> Update Agreement' : '<i class="fa fa-check"></i> Save Agreement';
                });
        });
    });

    form.addEventListener("reset", function () {
        if (suppressResetHandler) return;
        window.setTimeout(function () {
            body.innerHTML = "";
            buildRateOptions();
            loadColours();
            form.dataset.editMode = "0";
            form.dataset.agreementNo = freshAgreementNo || form.dataset.agreementNo || "";
            form.dataset.nextCertiNo = freshNextCertiNo || form.dataset.nextCertiNo || "1";
            form.dataset.locationLetter = selectedCollectionCode();
            setEditMode(null);
            updatePcsTotal();
            $("#agreement_status").hide();
            $("#customer_suggest").removeClass("active").empty();
            if (customerIdInput) customerIdInput.value = "";
            $("#agreement_serial_no").val(form.dataset.agreementNo || "");
            $("#edit_agreement_lookup").val("");
            $("input[name='signature_mode'][value='manual']").prop("checked", true);
            clearSignaturePad();
            refreshSignatureMode();
            var now = new Date();
            var local = new Date(now.getTime() - now.getTimezoneOffset() * 60000);
            $("#agreement_date").val(local.toISOString().slice(0, 10));
            $("#agreement_time").val(local.toISOString().slice(11, 16));
            $("#category").val("Regular");
            renumberRows();
            updateDueAmount();
        }, 0);
    });

    loadRates();
    loadRateConditions();
    loadColours();
    updatePcsTotal();
    updateDueAmount();
})();
