(function () {
    "use strict";

    var form = document.getElementById("diamond_feed");
    if (!form) return;

    var submit = document.getElementById("diamond_submit");
    var cameraModal = $("#diamond_camera_modal");
    var cropModal = $("#diamond_crop_modal");
    var video = document.getElementById("diamond_video");
    var cameraSelect = document.getElementById("diamond_camera_select");
    var cropImage = document.getElementById("diamond_crop_image");
    var stream = null;
    var cropper = null;
    var deviceId = "";
    var activeImageType = "stone";
    var requestId = 0;
    var bookingUnlocked = false;
    var editExistingReportId = 0;
    var internalReset = false;
    var defaultSubmitHtml = submit ? submit.innerHTML : '<i class="fa fa-check"></i> Submit Form';
    var tokenFieldByType = { stone: "upload_token", proportion: "upload_token_proportion", clarity: "upload_token_clarity" };

    function toast(type, message) {
        if (window.AppToast && AppToast[type]) AppToast[type](message); else alert(message);
    }

    function localDate() {
        if (!$("#date").val()) {
            var now = new Date();
            var local = new Date(now.getTime() - now.getTimezoneOffset() * 60000);
            $("#date").val(local.toISOString().slice(0, 10));
        }
    }

    function token(type) {
        type = tokenFieldByType[type] ? type : "stone";
        var field = document.getElementById(tokenFieldByType[type]);
        if (!field.value) field.value = "d" + type.charAt(0) + Date.now().toString(36) + Math.random().toString(36).slice(2);
        return field.value;
    }

    function preview(type) { return document.getElementById(type + "_image_preview"); }
    function empty(type) { return document.getElementById(type + "_image_empty"); }
    function showImage(type, html) {
        var box = preview(type), emptyBox = empty(type);
        if (!box) return;
        box.innerHTML = html || "";
        if (emptyBox) emptyBox.style.display = box.querySelector("img") ? "none" : "block";
    }
    function resetImages() {
        ["stone", "proportion", "clarity"].forEach(function (type) { showImage(type, ""); });
    }

    function bookingControls() {
        return $(form).find("input, select, textarea, button").not("#agreement_no,#certi_display").not("[type='hidden']").not("[type='reset']");
    }
    function setEditMode(row) {
        editExistingReportId = row && row.id ? Number(row.id) || 0 : 0;
        if (submit) submit.innerHTML = editExistingReportId ? '<i class="fa fa-check"></i> Update Form' : defaultSubmitHtml;
    }
    function setBookingLock(locked) {
        bookingUnlocked = !locked;
        bookingControls().each(function () {
            var control = $(this);
            if (locked) {
                if (!control.prop("disabled")) control.data("diamondBookingLocked", true).prop("disabled", true);
            } else if (control.data("diamondBookingLocked")) {
                control.prop("disabled", false).removeData("diamondBookingLocked");
            }
        });
    }
    function clearBookingState() {
        requestId++;
        setEditMode(null);
        setBookingLock(true);
    }
    function bookingKeys() {
        return { agreementNo: $.trim($("#agreement_no").val()), certiNo: $.trim($("#certi_display").val()) };
    }
    function syncReportType() {
        var option = $("#report_type_id option:selected");
        $("#report_type_text").val($.trim(option.text()));
        $("#report_format").val(option.data("format") || "a4");
    }
    function setReportType(id, name) {
        if (id && $("#report_type_id option[value='" + id + "']").length) $("#report_type_id").val(id);
        else if (name) {
            $("#report_type_id option").filter(function () { return $.trim($(this).text()).toLowerCase() === $.trim(name).toLowerCase(); }).prop("selected", true);
        }
        syncReportType();
    }
    function fillFromBooking(booking) {
        if (!booking) return;
        setEditMode(null);
        $("#agreement_no").val(booking.agreement_no || "");
        $("#certi_display").val(booking.certi_no || "");
        $("#description").val(booking.particulars || "");
        $("#dia_wt").val(booking.dia_wt || booking.stone_wt || booking.gross_wt || "");
        $("#shape_cut").val(booking.particulars || "");
    }
    function fillSymbols(row) {
        var selected = [];
        if (row.diamond_symbols_json) {
            try {
                var parsed = JSON.parse(row.diamond_symbols_json);
                if (Array.isArray(parsed)) selected = parsed.filter(Boolean).slice(0, 3);
            } catch (error) {}
        }
        if (!selected.length) selected = [row.WS1 || row.ws1 || "", row.WS2 || row.ws2 || "", row.WS3 || row.ws3 || ""].filter(Boolean);
        $(".diamond-symbol").prop("checked", false).each(function () { this.checked = selected.indexOf(this.value) !== -1; });
        syncSymbols();
    }
    function truthy(value) {
        return value === true || value === 1 || value === "1" || value === "yes" || value === "on" || value === "true";
    }
    function fillTests(row) {
        ["tri", "tsg", "tmag", "tuvf", "tabs", "tirs", "tedxrf", "tlrs", "tuvnir", "tlaicpms", "txray", "tuvimg"].forEach(function (name) {
            $("#" + name).prop("checked", truthy(row[name]));
        });
    }
    function fillFromExisting(row) {
        if (!row) return;
        $("#agreement_no").val(row.ag_no || "");
        $("#certi_display").val(row.certi_no || "");
        $("#date").val(row.date || "");
        $("#description").val(row.desc1 || row.desc || "");
        $("#dia_wt").val(row.dia_wt || row.diawt1 || row.stone_wt || row.stone_wt1 || "");
        $("#stone_name").val(row.stone_name || "Diamond");
        $("#shape_cut").val(row.shape_cut || "");
        $("#dimension").val(row.dimension || row.dime1 || "");
        $("#cut").val(row.cut || row.cutgrade || "");
        $("#symmetry").val(row.symmetry || "");
        $("#finish").val(row.finish || "");
        $("#colour").val(row.color || "");
        $("#table_value").val(row.table_size || row.table || "");
        $("#crown").val(row.crown || "");
        $("#pav_depth").val(row.pav_depth || row.pavi_depth || "");
        $("#girdle").val(row.girdle || "");
        $("#culet").val(row.culet || "");
        $("#flurance").val(row.flurence || row.flurance || "");
        $("#clarity").val(row.clarity || "");
        $("#comments").val(row.comment || "");
        setReportType(row.report_typ || "", row.category || "");
        fillSymbols(row);
        fillTests(row);
        setEditMode(row);
        fetchImage("stone", true);
        fetchImage("proportion", true);
        fetchImage("clarity", true);
    }
    function fetchBooking(silent) {
        var keys = bookingKeys();
        if (!keys.agreementNo || !keys.certiNo) { clearBookingState(); return; }
        var activeRequest = ++requestId;
        setBookingLock(true);
        $.ajax({ url: "cstone-booking-fetch.php", method: "POST", dataType: "json", data: { agreement_no: keys.agreementNo, certi_no: keys.certiNo, report_type: "D" } }).done(function (response) {
            if (activeRequest !== requestId) return;
            if (!response || response.status !== "success") { clearBookingState(); if (!silent) toast("error", response && response.message ? response.message : "Booked certificate not found."); return; }
            if (response.existing_other_type) { clearBookingState(); toast("warning", "This certificate is already generated in another feeding type. Please open it from the correct feeding page."); return; }
            if (response.existing_report) { fillFromExisting(response.existing_report); setBookingLock(false); toast("success", "Saved diamond grading details loaded for editing."); }
            else { fillFromBooking(response.booking); setBookingLock(false); fetchImage("stone", true); fetchImage("proportion", true); fetchImage("clarity", true); if (!silent) toast("success", "Booked diamond grading details loaded."); }
        }).fail(function (xhr) {
            if (activeRequest !== requestId) return;
            var message = "Unable to fetch booked certificate.";
            try { message = JSON.parse(xhr.responseText).message || message; } catch (error) {}
            clearBookingState(); if (!silent) toast("error", message);
        });
    }
    function syncSymbols(changed) {
        var checked = $(".diamond-symbol:checked").map(function () { return this.value; }).get();
        if (checked.length > 3) {
            if (changed) changed.checked = false;
            toast("warning", "You can select maximum 3 symbols.");
            checked = $(".diamond-symbol:checked").map(function () { return this.value; }).get();
        }
        for (var i = 1; i <= 3; i++) $("#symbol" + i).val(checked[i - 1] || "");
        $("#diamond_symbols_json").val(JSON.stringify(checked.slice(0, 3)));
    }
    function valid() {
        $(".diamond-field").removeClass("has-error");
        var keys = bookingKeys();
        if (!keys.agreementNo || !keys.certiNo || !bookingUnlocked) { toast("error", "Enter agreement no and certificate no, then wait for booked details to load."); return false; }
        var required = ["date", "report_type_id", "dia_wt", "shape_cut", "dimension", "colour"];
        for (var i = 0; i < required.length; i++) {
            var field = document.getElementById(required[i]);
            if (!field || !String(field.value || "").trim()) { $(field).closest(".diamond-field").addClass("has-error"); if (field) field.focus(); toast("error", "Please complete " + $(field).closest(".diamond-field").find("label").text().replace(" *", "") + "."); return false; }
        }
        return true;
    }
    function fetchImage(type, silent) {
        var keys = bookingKeys();
        $.post("image_fetch.php", { input: keys.certiNo || "", upload_token: token(type), image_type: type }).done(function (html) { showImage(type, html); }).fail(function () { if (!silent) toast("error", "Unable to fetch image."); });
    }
    function uploadImage(type, file) {
        var data = new FormData();
        data.append("image_file", file);
        data.append("certino", "");
        data.append("upload_token", token(type));
        data.append("image_type", type);
        $.ajax({ url: "upload.php", method: "POST", data: data, processData: false, contentType: false }).done(function (html) { showImage(type, html); toast("success", "Image attached."); }).fail(function (xhr) { toast("error", xhr.responseText || "Unable to upload image."); });
    }
    function openGeneratedReport(certiNo) { if (certiNo) window.open("report-open-by-certificate.php?certi_no=" + encodeURIComponent(certiNo), "_blank"); }
    function resetAfterSave() {
        internalReset = true;
        form.reset();
        internalReset = false;
        ["upload_token", "upload_token_proportion", "upload_token_clarity"].forEach(function (id) { document.getElementById(id).value = ""; });
        $("#stone_name").val("Diamond");
        $(".diamond-symbol").prop("checked", false);
        $(".diamond-test").prop("checked", false);
        syncSymbols();
        resetImages();
        setEditMode(null); clearBookingState(); localDate(); $("#agreement_no").focus();
    }

    form.addEventListener("submit", function (event) {
        event.preventDefault();
        if (!valid()) return;
        syncReportType(); syncSymbols(); $("#weight").val($("#dia_wt").val());
        var data = new FormData(form);
        data.set("upload_token", token("stone"));
        data.set("upload_token_proportion", token("proportion"));
        data.set("upload_token_clarity", token("clarity"));
        data.set("edit_existing_report", editExistingReportId ? "1" : "0");
        data.set("edit_existing_report_id", editExistingReportId ? String(editExistingReportId) : "");
        submit.disabled = true; submit.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
        $.ajax({ url: "process-form.php", method: "POST", data: data, processData: false, contentType: false, dataType: "json" }).done(function (response) {
            if (!response || response.status !== "success") { toast("error", response && response.message ? response.message : "Unable to save diamond grading report."); return; }
            var verb = response.action === "updated" ? "updated" : "saved";
            $("#diamond_saved_certificate").text(response.certi_no); $("#diamond_saved_report").text(response.report_no); $("#diamond_save_result").css("display", "flex");
            toast("success", "Diamond grading report " + verb + ". Certificate " + response.certi_no + " / Report " + response.report_no);
            var askMessage = "Diamond grading report " + verb + " for certificate " + response.certi_no + ". Generate this report now?";
            if (window.AppConfirm && typeof AppConfirm.show === "function") {
                AppConfirm.show(askMessage, { title: "Generate report?", confirmText: "Generate Report", cancelText: "Exit" }).then(function (confirmed) { if (confirmed) openGeneratedReport(response.certi_no); resetAfterSave(); });
            } else if (window.confirm(askMessage)) { openGeneratedReport(response.certi_no); resetAfterSave(); } else resetAfterSave();
        }).fail(function (xhr) { var message = "Unable to save diamond grading report."; try { message = JSON.parse(xhr.responseText).message || message; } catch (error) {} toast("error", message); }).always(function () { submit.disabled = !bookingUnlocked; submit.innerHTML = editExistingReportId ? '<i class="fa fa-check"></i> Update Form' : defaultSubmitHtml; });
    });

    $("#agreement_no, #certi_display").on("input", clearBookingState).on("change blur", function () { fetchBooking(false); });
    $("#report_type_id").on("change", syncReportType);
    $(".diamond-symbol").on("change", function () { syncSymbols(this); });
    $(".image-fetch").on("click", function () { fetchImage($(this).data("type") || "stone", false); });
    $(".image-upload-button").on("click", function () { var type = $(this).data("type") || "stone"; document.getElementById(type + "_upload_image").click(); });
    ["stone", "proportion", "clarity"].forEach(function (type) { var input = document.getElementById(type + "_upload_image"); if (input) input.addEventListener("change", function () { if (this.files && this.files[0]) uploadImage(type, this.files[0]); this.value = ""; }); });
    $(".image-camera-button").on("click", function () { activeImageType = $(this).data("type") || "stone"; cameraModal.modal("show"); });

    function stopCamera() { if (stream) { stream.getTracks().forEach(function (track) { track.stop(); }); stream = null; } if (video) video.srcObject = null; }
    async function cameras() { var devices = await navigator.mediaDevices.enumerateDevices(); var cams = devices.filter(function (d) { return d.kind === "videoinput"; }); cameraSelect.innerHTML = ""; cams.forEach(function (cam, i) { var opt = document.createElement("option"); opt.value = cam.deviceId; opt.textContent = cam.label || "Camera " + (i + 1); cameraSelect.appendChild(opt); }); cameraSelect.disabled = cams.length <= 1; deviceId = cameraSelect.value || deviceId; }
    async function startCamera(id) { stopCamera(); try { stream = await navigator.mediaDevices.getUserMedia({ video: id ? { deviceId: { exact: id } } : { facingMode: { ideal: "environment" } }, audio: false }); video.srcObject = stream; await video.play(); await cameras(); } catch (error) { toast("error", "Unable to open the selected camera."); cameraModal.modal("hide"); } }
    cameraModal.on("shown.bs.modal", function () { startCamera(deviceId); }).on("hidden.bs.modal", stopCamera);
    cameraSelect.addEventListener("change", function () { deviceId = this.value; startCamera(deviceId); });
    document.getElementById("diamond_capture").addEventListener("click", function () { if (!video.videoWidth) { toast("warning", "Camera is still starting."); return; } var canvas = document.createElement("canvas"); canvas.width = video.videoWidth; canvas.height = video.videoHeight; canvas.getContext("2d").drawImage(video, 0, 0); cropImage.src = canvas.toDataURL("image/jpeg", 0.92); stopCamera(); cameraModal.modal("hide"); cropModal.modal("show"); });
    cropModal.on("shown.bs.modal", function () { if (cropper) cropper.destroy(); cropper = new Cropper(cropImage, { aspectRatio: 1, viewMode: 3, autoCropArea: 1 }); }).on("hidden.bs.modal", function () { if (cropper) cropper.destroy(); cropper = null; });
    document.getElementById("diamond_crop_save").addEventListener("click", function () { if (!cropper) return; $.post("save_image.php", { croppedData: cropper.getCroppedCanvas({ width: 800, height: 800 }).toDataURL("image/jpeg", 0.9), certino: "", upload_token: token(activeImageType), image_type: activeImageType }).done(function (html) { cropModal.modal("hide"); showImage(activeImageType, html); toast("success", "Image captured."); }).fail(function (xhr) { toast("error", xhr.responseText || "Unable to save camera image."); }); });
    form.addEventListener("reset", function () {
        if (internalReset) return;
        window.setTimeout(function () {
            ["upload_token", "upload_token_proportion", "upload_token_clarity"].forEach(function (id) { document.getElementById(id).value = ""; });
            $("#stone_name").val("Diamond");
            $(".diamond-symbol").prop("checked", false);
            $(".diamond-test").prop("checked", false);
            syncSymbols();
            resetImages();
            setEditMode(null);
            clearBookingState();
            localDate();
        }, 0);
    });

    clearBookingState(); localDate(); syncSymbols();
})();
