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
    var customerTimer = null;
    var customerResults = [];
    var rateList = [];
    var rateConditionRules = [];
    var rateOptionsHtml = '<option value="">Select category</option>';
    var colourOptionsHtml = '';

    function rowTemplate(index) {
        var name = "items[" + index + "]";
        var unitOptions = '<option value="ct" selected>ct</option><option value="gms">gms</option><option value="kg">kg</option>';
        return '<tr>' +
            '<td class="row-no"></td>' +
            '<td><input class="form-control ref-input" name="' + name + '[ref_no]" readonly></td>' +
            '<td><select class="form-control rate-category" name="' + name + '[category]">' + rateOptionsHtml + '</select></td>' +
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
            '<td><input class="form-control money-input discount-input" name="' + name + '[discount_amount]" readonly><input type="hidden" class="discount-percent-input" name="' + name + '[discount_percent]"></td>' +
            '<td><input class="form-control money-input amount-input" name="' + name + '[amount]"></td>' +
            '<td><button type="button" class="btn btn-default remove-row" title="Remove row"><i class="fa fa-trash-o"></i></button></td>' +
        '</tr>';
    }

    function renumberRows() {
        var agreementNo = parseInt(form.dataset.agreementNo || "0", 10) || 0;
        var nextCertiNo = parseInt(form.dataset.nextCertiNo || "1", 10) || 1;
        var locationLetter = (form.dataset.locationLetter || "S").toUpperCase().charAt(0) || "S";
        Array.prototype.forEach.call(body.querySelectorAll("tr"), function (row, index) {
            row.querySelector(".row-no").textContent = index + 1;
            var ref = row.querySelector(".ref-input");
            if (ref) ref.value = agreementNo + locationLetter + (nextCertiNo + index);
        });
    }

    function addRow(focus) {
        var index = Date.now().toString(36) + Math.floor(Math.random() * 1000);
        var holder = document.createElement("tbody");
        holder.innerHTML = rowTemplate(index);
        body.appendChild(holder.firstChild);
        renumberRows();
        if (focus) body.querySelector("tr:last-child input").focus();
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
    }

    function openAgreementPrint(printUrl) {
        if (!printUrl) return;
        var opened = window.open(printUrl, "_blank");
        if (!opened) {
            window.location.href = printUrl;
        }
    }

    function validateRowsBeforeSubmit() {
        var rows = Array.prototype.slice.call(body.querySelectorAll("tr"));
        if (!rows.length) {
            AppToast.error("Add at least one stone detail row.");
            return false;
        }
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
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
            var value = parseFloat(String(input.value).replace(/[^0-9.\-]/g, ""));
            if (!isNaN(value)) total += value;
        });
        document.getElementById("testing_charges").value = total.toFixed(2);
        updateDueAmount();
    }

    function updatePcsTotal() {
        var total = 0;
        Array.prototype.forEach.call(body.querySelectorAll(".pcs-input"), function (input) {
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
        var balance = charges - paid;
        $("#due_amount").val(Math.max(0, balance).toFixed(2));
        $("#refund_amount").val(Math.max(0, -balance).toFixed(2));
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

    function numericInput(row, name) {
        var input = row.querySelector('[name$="[' + name + ']"]');
        var value = input ? parseFloat(String(input.value || "").replace(/[^0-9.\-]/g, "")) : 0;
        return isNaN(value) ? 0 : value;
    }

    function rateCodeValue(rate) {
        return parseInt(String(rate && rate.rate_code ? rate.rate_code : "").replace(/[^0-9\-]/g, ""), 10) || 0;
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

        var discountPercent = mouDiscountPercent();
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
        rateOptionsHtml = '<option value="">Select category</option>' + rateList.map(function (rate) {
            return '<option value="' + escapeHtml(rate.description) + '" data-rate-id="' + rate.id + '" data-rate-code="' + escapeHtml(rate.rate_code) + '">' + escapeHtml(rate.description) + '</option>';
        }).join("");
        Array.prototype.forEach.call(body.querySelectorAll(".rate-category"), function (select) {
            var oldValue = select.value;
            select.innerHTML = rateOptionsHtml;
            select.value = oldValue;
        });
    }

    function rateById(id) {
        id = parseInt(id || "0", 10);
        for (var i = 0; i < rateList.length; i++) {
            if (parseInt(rateList[i].id, 10) === id) return rateList[i];
        }
        return null;
    }

    function applyRateToRow(row) {
        var select = row.querySelector(".rate-category");
        if (!select) return;
        var option = select.options[select.selectedIndex];
        var rate = rateById(option ? option.getAttribute("data-rate-id") : 0);
        var value = selectedRate(rate);
        var formatted = value > 0 ? value.toFixed(2) : "";
        var rateInput = row.querySelector(".rate-input");
        if (rateInput) rateInput.value = formatted;
        calculateRowAmount(row, true);
    }

    function selectedRateForRow(row) {
        var select = row.querySelector(".rate-category");
        if (!select) return null;
        var option = select.options[select.selectedIndex];
        return rateById(option ? option.getAttribute("data-rate-id") : 0);
    }

    function calculateRowAmount(row, showWarning) {
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
    }

    function loadRates() {
        $.getJSON("rate-list.php")
            .done(function (response) {
                rateList = response && response.rates ? response.rates : [];
                buildRateOptions();
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

    document.getElementById("add_agreement_row").addEventListener("click", function () {
        if (!requireSelectedCustomer()) return;
        addRow(true);
    });

    body.addEventListener("click", function (event) {
        var button = event.target.closest(".remove-row");
        if (!button) return;
        if (body.querySelectorAll("tr").length === 1) {
            button.closest("tr").querySelectorAll("input").forEach(function (input) {
                if (input.classList.contains("ref-input")) return;
                if (input.type === "checkbox") input.checked = false;
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
        updatePcsTotal();
        updateTestingCharges();
    });

    body.addEventListener("input", function (event) {
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

    form.addEventListener("submit", function (event) {
        event.preventDefault();
        if (!document.getElementById("customer_name").value.trim()) {
            AppToast.error("Customer name is required.");
            document.getElementById("customer_name").focus();
            return;
        }
        if (!requireSelectedCustomer()) return;
        if (!hasItemRow()) {
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
                if (response.agreement_no) {
                    var nextAgreementNo = parseInt(response.agreement_no, 10) + 1;
                    form.dataset.agreementNo = String(nextAgreementNo);
                    $("#agreement_serial_no").val(nextAgreementNo);
                }
                if (response.next_certificate_no) {
                    form.dataset.nextCertiNo = String(parseInt(response.next_certificate_no, 10) || 1);
                    renumberRows();
                }
                AppToast.success("Agreement saved. Serial #" + response.agreement_no + ".");
                var printUrl = response.print_url || ("agreement-print.php?id=" + response.id);
                var askMessage = "Agreement saved. Generate agreement now?";
                if (window.AppConfirm && typeof AppConfirm.show === "function") {
                    AppConfirm.show(askMessage, {
                        title: "Generate agreement?",
                        confirmText: "Generate Agreement",
                        cancelText: "Exit"
                    }).then(function (confirmed) {
                        if (confirmed) {
                            openAgreementPrint(printUrl);
                        }
                        resetAgreementForm();
                    });
                } else if (window.confirm(askMessage)) {
                    openAgreementPrint(printUrl);
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
                submit.innerHTML = '<i class="fa fa-check"></i> Save Agreement';
            });
    });

    form.addEventListener("reset", function () {
        window.setTimeout(function () {
            body.innerHTML = "";
            buildRateOptions();
            loadColours();
            updatePcsTotal();
            $("#agreement_status").hide();
            $("#customer_suggest").removeClass("active").empty();
            if (customerIdInput) customerIdInput.value = "";
            $("#agreement_serial_no").val(form.dataset.agreementNo || "");
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
