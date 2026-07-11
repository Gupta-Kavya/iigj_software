(function () {
    "use strict";

    var form = document.getElementById("cstone_feed");
    if (!form) return;

    var submitButton = document.getElementById("cstone_submit") || document.getElementById("form_submit");
    var uploadInput = document.getElementById("cstone_upload_image") || document.getElementById("upload_image");
    var fetchedImage = document.getElementById("fetched_image");
    var imageBox = document.getElementById("cstone_image_box");
    var cameraModal = $("#cstone_camera_modal").length ? $("#cstone_camera_modal") : $("#camera_modal");
    var cropModal = $("#cstone_crop_modal").length ? $("#cstone_crop_modal") : $("#cam_crop_modal");
    var video = document.getElementById("cstone_video") || document.getElementById("video");
    var cameraSelect = document.getElementById("cstone_camera_select") || document.getElementById("camera_select");
    var cropImage = document.getElementById("cstone_crop_image") || document.getElementById("cam_snap_image");
    var cameraStream = null;
    var cameraCropper = null;
    var selectedCameraId = "";
    var bookingUnlocked = false;
    var bookingRequestId = 0;
    var requiresBookingUnlock = !$("#certi_display").prop("readonly");
    var editExistingReportId = 0;
    var submitDefaultHtml = submitButton ? submitButton.innerHTML : '<i class="fa fa-check"></i> Submit Form';
    var feedType = (form.getAttribute("data-feed-type") || $("input[name='report_type']").val() || "S").toUpperCase();
    var feedLabel = form.getAttribute("data-feed-label") || (feedType === "P" ? "Pearl" : "Colour stone");
    var feedLabelLower = feedLabel.toLowerCase();

    function toast(type, message) {
        if (window.AppToast && AppToast[type]) {
            AppToast[type](message);
        } else {
            alert(message);
        }
    }

    function uploadToken() {
        var field = document.getElementById("upload_token");
        if (!field.value) {
            var random = window.crypto && crypto.getRandomValues
                ? Array.from(crypto.getRandomValues(new Uint32Array(2))).map(function (v) { return v.toString(36); }).join("")
                : Math.random().toString(36).slice(2);
            field.value = "s" + Date.now().toString(36) + random;
        }
        return field.value;
    }

    function showPreview(html) {
        fetchedImage.innerHTML = html || "";
        if (imageBox) {
            imageBox.classList.toggle("has-image", !!fetchedImage.querySelector("img"));
        }
    }

    function resetImage() {
        fetchedImage.innerHTML = "";
        if (imageBox) imageBox.classList.remove("has-image");
    }

    function setLocalDate() {
        if (!$("#date").val()) {
            var now = new Date();
            var local = new Date(now.getTime() - now.getTimezoneOffset() * 60000);
            $("#date").val(local.toISOString().slice(0, 10));
        }
    }

    function setSelectDescription(selectId, targetId, onlyWhenEmpty) {
        var select = document.getElementById(selectId);
        var option = select && select.options[select.selectedIndex];
        var desc = option ? option.getAttribute("data-description") || "" : "";
        var target = $("#" + targetId);
        if (!onlyWhenEmpty || !target.val()) {
            target.val(desc);
        }
    }

    function setTreatment(selectId, inputId) {
        setSelectDescription(selectId, inputId, false);
    }

    function isOthersSpeciesMode() {
        return $("input[name='species_mode']:checked").val() === "Others" || $.trim($("#stone_name").val()).toLowerCase() === "others";
    }

    function syncSpeciesMode() {
        var othersMode = isOthersSpeciesMode();
        if ($.trim($("#stone_name").val()).toLowerCase() === "others") {
            $("input[name='species_mode'][value='Others']").prop("checked", true);
            othersMode = true;
        }
        $("label[for='stone_name']").text(othersMode ? "Others" : "Variety");
        var groupField = $("#species_grp");
        var groupWrap = groupField.closest(".cstone-field");
        if (othersMode) {
            groupField.val("").prop("disabled", true);
            groupWrap.hide();
        } else {
            groupField.prop("disabled", !!groupField.data("cstoneBookingLocked"));
            groupWrap.show();
        }
    }

    function syncTests() {
        var selected = [];
        $("input[name='tests[]']:checked").each(function () {
            selected.push(this.value);
        });
        $("#test_carried_out").val(selected.join(", "));
    }

    function syncReportTypeText() {
        var selectedText = $("#report_type_id option:selected").text();
        if ($("#report_type_id").val()) {
            $("#report_type_text").val($.trim(selectedText));
        }
        var selectedFormat = $("#report_type_id option:selected").data("format") || "a4";
        $("#report_format").val(selectedFormat);
    }

    function bookingControlSelector() {
        return $(form).find("input, select, textarea, button")
            .not("#agreement_no,#certi_display")
            .not("[type='hidden']")
            .not("[type='reset']");
    }

    function setBookingLock(locked) {
        bookingUnlocked = !locked;
        bookingControlSelector().each(function () {
            var control = $(this);
            if (locked) {
                if (!control.prop("disabled")) {
                    control.data("cstoneBookingLocked", true).prop("disabled", true);
                }
            } else if (control.data("cstoneBookingLocked")) {
                control.prop("disabled", false).removeData("cstoneBookingLocked");
            }
        });
        $(".cstone-section, .cstone-aside, .cstone-action-bar").toggleClass("cstone-locked", locked);
        if (!locked) {
            syncSpeciesMode();
        }
    }

    function clearBookingState() {
        bookingRequestId++;
        bookingUnlocked = false;
        setEditMode(null);
        $("#assigned_certificate_no").text("");
        $("#assigned_certificate_result").hide();
        setBookingLock(true);
    }

    function resetAfterSave() {
        form.reset();
        document.getElementById("upload_token").value = "";
        $("#certi_display").val("");
        $("#certi_no").val("");
        clearBookingState();
        setLocalDate();
        syncSpeciesMode();
        resetImage();
        $("#agreement_no").focus();
    }

    function openGeneratedReport(certiNo) {
        if (!certiNo) return;
        window.open("report-open-by-certificate.php?certi_no=" + encodeURIComponent(certiNo), "_blank");
    }

    function bookingKeys() {
        return {
            agreementNo: $.trim($("#agreement_no").val()),
            certiNo: $.trim($("#certi_display").val())
        };
    }

    function fillFromBooking(booking) {
        if (!booking) return;
        setEditMode(null);
        $("#certi_no").val(booking.certi_no || "");
        $("#assigned_certificate_no").text((booking.ref_no || booking.report_no || "") + " / " + booking.certi_no);
        $("#assigned_certificate_result").css("display", "flex");
        $("#item_desc").val(booking.particulars || "");
        $("#gross_weight").val(booking.gross_wt || "");
        $("#gross_unit").val(booking.gross_wt_unit || "ct");
        $("#colour").val(booking.color || "");
        $("#stone_pcs").val(booking.pcs || "");
        $("#stone_weight_1").val(booking.stone_wt || "");
        $("#stone_weight_2").val("");
        $("#dimension").val("");
        syncStoneWeightUnits(booking.stone_wt_unit || "ct");
        $("#length_tested").val(booking.bead_length || "");
    }

    function setEditMode(report) {
        editExistingReportId = report && report.id ? Number(report.id) || 0 : 0;
        if (!submitButton) return;
        submitButton.innerHTML = editExistingReportId ? '<i class="fa fa-check"></i> Update Form' : submitDefaultHtml;
    }

    function fillFromExistingReport(data) {
        if (!data) return;
        $("#agreement_no").val(data.ag_no || "");
        $("#certi_no").val(data.certi_no || "");
        $("#certi_display").val(data.certi_no || "");
        $("#assigned_certificate_no").text((data.report_no || "") + " / " + (data.certi_no || ""));
        $("#assigned_certificate_result").css("display", "flex");
        $("#date").val(data.date || "");
        $("#item_desc").val(data.desc1 || data.desc || "");
        $("#gross_weight").val(data.gross_wt || "");
        $("#gross_unit").val(data.unit_grs || "ct");
        syncStoneWeightUnits(data.unit_stn || "ct");
        $("#colour").val(data.color || "");
        $("#shape_cut").val(data.shape_cut || "");
        $("#stone_pcs").val(data.testd_pcs || data.pcs || data.tot_stone || "");
        $("#tested_pcs_remark").val(data.tpremark || data.rem1 || "");
        for (var i = 1; i <= 5; i++) {
            $("#stone_weight_" + i).val(data["stone_wt" + i] || (i === 1 ? data.stone_wt || "" : ""));
            $("#measurement_" + i).val(data["dime" + i] || (i === 1 ? data.dimension || "" : ""));
        }
        $("#length_tested").val(data.bead_lenth || "");
        $("#ri").val(data.ri || data.ref_index || "");
        $("#specefic_grav").val(data.sg || data.spe_gravit || "");
        $("#magnification").val(data.magni || data.magnification || "");
        $("#optic_char").val(data.optic || data.optic_char || "");
        var mode = data.title_rem || "Species/Variety";
        $("input[name='species_mode'][value='" + (mode === "Others" ? "Others" : "Species/Variety") + "']").prop("checked", true);
        $("#stone_name").val(data.variety || "");
        $("#species_grp").val(data.stone_name || data.spe_group || "");
        syncSpeciesMode();
        $("#comments").val(data.comment || "");
        $("#origin").val(data.origin || "");
        $("#treatment_comment_title").val(data.title_rem1 || "");
        $("#treatment_comment_title_2").val(data.title_rem2 || "");
        $("#treatment_comment_desc").val(data.trtcoment1 || "");
        $("#treatment_comment_desc_2").val(data.trtcoment2 || "");
        $("#ebay_prod_no").val(data.productno || "");
        if (data.report_typ) {
            $("#report_type_id").val(data.report_typ);
            syncReportTypeText();
        } else if (data.category) {
            $("#report_type_text").val(data.category);
        }
        ["tri","tsg","tmag","tuvf","tabs","tirs","tedxrf","tlrs","tuvnir","tlaicpms","txray","tuvimg"].forEach(function (column) {
            $("input[data-column='" + column + "']").prop("checked", Number(data[column] || 0) === 1);
        });
        syncTests();
        setEditMode(data);
    }

    function syncStoneWeightUnits(value) {
        var unit = value || "ct";
        $(".cstone-weight-unit").val(unit);
    }

    function fetchBooking(silent) {
        if (!requiresBookingUnlock) return;
        var keys = bookingKeys();
        if (!keys.agreementNo || !keys.certiNo) {
            clearBookingState();
            return;
        }
        var requestId = ++bookingRequestId;
        setBookingLock(true);
        $.ajax({
            url: "cstone-booking-fetch.php",
            method: "POST",
            dataType: "json",
            data: { agreement_no: keys.agreementNo, certi_no: keys.certiNo, report_type: feedType }
        }).done(function (response) {
            if (requestId !== bookingRequestId) return;
            if (!response || response.status !== "success") {
                if (!silent) toast("error", response && response.message ? response.message : "Booked report not found.");
                clearBookingState();
                return;
            }
            if (response.existing_other_type) {
                clearBookingState();
                toast("warning", "This certificate is already generated in another feeding type. Please open it from the correct feeding page.");
                return;
            }
            if (response.existing_report) {
                fillFromExistingReport(response.existing_report);
                setBookingLock(false);
                fetchImage(true);
                toast("warning", "This certificate is already generated. Loaded for editing.");
            } else if (!silent) {
                fillFromBooking(response.booking);
                setBookingLock(false);
                fetchImage(true);
                toast("success", "Booked report details loaded.");
            } else {
                fillFromBooking(response.booking);
                setBookingLock(false);
                fetchImage(true);
            }
        }).fail(function (xhr) {
            if (requestId !== bookingRequestId) return;
            var message = "Unable to fetch booked report.";
            try { message = JSON.parse(xhr.responseText).message || message; } catch (error) {}
            clearBookingState();
            if (!silent) toast("error", message);
        });
    }

    function validate() {
        $(".cstone-field").removeClass("has-error");
        var required = [
            ["agreement_no", "Agreement No"],
            ["certi_display", "Certi No"],
            ["report_type_id", "Report Type"],
            ["date", "Date"],
            ["colour", "Colour"],
            ["shape_cut", "Shape/Cut"],
            ["stone_weight_1", "Stone Weight 1"],
            ["stone_name", "Variety"]
        ];
        for (var i = 0; i < required.length; i++) {
            var field = document.getElementById(required[i][0]);
            if (!field || !String(field.value || "").trim()) {
                $(field).closest(".cstone-field").addClass("has-error");
                field.focus();
                toast("error", "Please complete " + required[i][1] + ".");
                return false;
            }
        }
        return true;
    }

    function stoneNameMaster() {
        var stoneName = $.trim($("#stone_name").val());
        syncSpeciesMode();
        if (isOthersSpeciesMode()) return;
        if (!stoneName) return;
        $.ajax({
            url: "fetch_master_details.php",
            method: "POST",
            dataType: "json",
            data: { master_stone_name: stoneName }
        }).done(function (data) {
            if (!data || data.status === "not_found") return;
            if (isOthersSpeciesMode()) return;
            if (!$("#species_grp").val() && data.group) $("#species_grp").val(data.group);
            if (!$("#shape_cut").val() && data.shape_cut) $("#shape_cut").val(data.shape_cut);
            if (!$("#colour").val() && data.colour) $("#colour").val(data.colour);
            if (!$("#origin").val() && data.origin) $("#origin").val(data.origin);
        });
    }

    form.addEventListener("submit", function (event) {
        event.preventDefault();
        if (!validate()) return;
        var keys = bookingKeys();
        if (requiresBookingUnlock && (!keys.agreementNo || !keys.certiNo || !bookingUnlocked)) {
            toast("error", "Enter agreement no and certificate no, then wait for booked details to load.");
            return;
        }
        syncReportTypeText();
        syncTests();

        var data = new FormData(form);
        data.set("upload_token", uploadToken());
        data.set("edit_existing_report", editExistingReportId ? "1" : "0");
        data.set("edit_existing_report_id", editExistingReportId ? String(editExistingReportId) : "");
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';

        $.ajax({
            url: "process-cstone-form.php",
            method: "POST",
            data: data,
            processData: false,
            contentType: false,
            dataType: "json"
        }).done(function (response) {
            if (!response || response.status !== "success") {
                toast("error", response && response.message ? response.message : "Unable to save " + feedLabelLower + " report.");
                return;
            }
            $("#cstone_saved_certificate, #assigned_certificate_no").text(response.certi_no);
            $("#cstone_saved_report").text(response.report_no);
            $("#certi_display").val(response.certi_no);
            $("#cstone_save_result, #assigned_certificate_result").css("display", "flex");
            var savedVerb = response.action === "updated" ? "updated" : "saved";
            toast("success", feedLabel + " report " + savedVerb + ". Certificate " + response.certi_no + " / Report " + response.report_no);
            var askMessage = feedLabel + " report " + savedVerb + " for certificate " + response.certi_no + ". Generate this report now?";
            if (window.AppConfirm && typeof AppConfirm.show === "function") {
                AppConfirm.show(askMessage, {
                    title: "Generate report?",
                    confirmText: "Generate Report",
                    cancelText: "Exit"
                }).then(function (confirmed) {
                    if (confirmed) {
                        openGeneratedReport(response.certi_no);
                    }
                    resetAfterSave();
                });
            } else if (window.confirm(askMessage)) {
                openGeneratedReport(response.certi_no);
                resetAfterSave();
            } else {
                resetAfterSave();
            }
        }).fail(function (xhr) {
            var message = "Unable to save " + feedLabelLower + " report.";
            try { message = JSON.parse(xhr.responseText).message || message; } catch (error) {}
            toast("error", message);
        }).always(function () {
            submitButton.disabled = false;
            submitButton.innerHTML = editExistingReportId ? '<i class="fa fa-check"></i> Update Form' : submitDefaultHtml;
        });
    });

    uploadInput.addEventListener("change", function () {
        if (!this.files || !this.files[0]) return;
        var data = new FormData();
        data.append("image_file", this.files[0]);
        data.append("certino", "");
        data.append("upload_token", uploadToken());
        $.ajax({
            url: "upload.php",
            method: "POST",
            data: data,
            processData: false,
            contentType: false
        }).done(function (html) {
            ($("#cstone_folder_modal").length ? $("#cstone_folder_modal") : $("#up_from_folder")).modal("hide");
            showPreview(html);
            toast("success", "Stone image attached.");
        }).fail(function (xhr) {
            toast("error", xhr.responseText || "Unable to upload image.");
        });
        this.value = "";
    });

    function fetchImage(silent) {
        var keys = bookingKeys();
        $.ajax({
            url: "image_fetch.php",
            method: "POST",
            data: { input: keys.certiNo || "", upload_token: uploadToken() }
        }).done(function (html) {
            showPreview(html);
        }).fail(function () {
            if (!silent) toast("error", "Unable to fetch image.");
        });
    }

    function copyCertificate() {
        var copyInput = $("#cstone_copy_certificate").length ? $("#cstone_copy_certificate") : $("#field_copier_field");
        var copyBlock = $("#cstone_copy_block").length ? $("#cstone_copy_block") : $(".field_copier");
        var certificate = $.trim(copyInput.val());
        if (!certificate) {
            copyBlock.addClass("has-error");
            copyInput.focus();
            toast("error", "Please enter a certificate number.");
            return;
        }
        $.ajax({
            url: "field-cpier.php",
            method: "POST",
            dataType: "json",
            data: { field_no: certificate, report_type: feedType }
        }).done(function (data) {
            if (!data) {
                toast("error", "Certificate not found.");
                return;
            }
            copyBlock.removeClass("has-error");
            $("#agreement_no").val(data.ag_no || "");
            $("#date").val(data.date || "");
            $("#item_desc").val(data.desc1 || "");
            $("#gross_weight").val(data.gross_wt || "");
        $("#gross_unit").val(data.unit_grs || "ct");
            syncStoneWeightUnits(data.unit_stn || "ct");
            $("#colour").val(data.color || "");
            $("#shape_cut").val(data.shape_cut || "");
            $("#stone_pcs").val(data.testd_pcs || data.pcs || "");
            $("#tested_pcs_remark").val(data.tpremark || "");
            for (var i = 1; i <= 5; i++) {
                var weightValue = data["stone_wt" + i] || "";
                $("#stone_weight_" + i).val(weightValue);
                $("#measurement_" + i).val(data["dime" + i] || "");
            }
            $("#length_tested").val(data.bead_lenth || "");
            $("#ri").val(data.ri || data.ref_index || "");
            $("#specefic_grav").val(data.sg || data.spe_gravit || "");
            $("#optic_char").val(data.optic || data.optic_char || "");
            $("#stone_name").val(data.variety || data.stone_name || "");
            $("#species_grp").val(data.stone_name || data.spe_group || "");
            syncSpeciesMode();
            $("#comments").val(data.comment || "");
            $("#origin").val(data.origin || "");
            $("#treatment_comment_desc").val(data.trtcoment1 || "");
            $("#treatment_comment_desc_2").val(data.trtcoment2 || "");
            $("#ebay_prod_no").val(data.productno || "");
            ["tri","tsg","tmag","tuvf","tabs","tirs","tedxrf","tlrs","tuvnir","tlaicpms","txray","tuvimg"].forEach(function (column) {
                $("input[data-column='" + column + "']").prop("checked", Number(data[column] || 0) === 1);
            });
            syncTests();
            toast("success", "Fields copied from certificate " + certificate + ".");
        }).fail(function () {
            toast("error", "Unable to copy certificate fields.");
        });
    }

    function stopCamera() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(function (track) { track.stop(); });
            cameraStream = null;
        }
        if (video) video.srcObject = null;
    }

    async function listCameras() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return;
        var devices = await navigator.mediaDevices.enumerateDevices();
        var cameras = devices.filter(function (device) { return device.kind === "videoinput"; });
        cameraSelect.innerHTML = "";
        cameras.forEach(function (camera, index) {
            var option = document.createElement("option");
            option.value = camera.deviceId;
            option.textContent = camera.label || "Camera " + (index + 1);
            cameraSelect.appendChild(option);
        });
        cameraSelect.disabled = cameras.length <= 1;
        selectedCameraId = cameraSelect.value || selectedCameraId;
    }

    async function startCamera(deviceId) {
        stopCamera();
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            toast("error", "Camera access requires HTTPS or localhost.");
            cameraModal.modal("hide");
            return;
        }
        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({ video: deviceId ? { deviceId: { exact: deviceId } } : { facingMode: { ideal: "environment" } }, audio: false });
            video.srcObject = cameraStream;
            await video.play();
            await listCameras();
        } catch (error) {
            toast("error", "Unable to open the selected camera.");
            cameraModal.modal("hide");
        }
    }

    $("#treatment_comment_title").on("change", function () { setTreatment("treatment_comment_title", "treatment_comment_desc"); });
    $("#treatment_comment_title_2").on("change", function () { setTreatment("treatment_comment_title_2", "treatment_comment_desc_2"); });
    $("#general_comment_title").on("change", function () { setSelectDescription("general_comment_title", "comments", false); });
    $("#stone_name").on("change blur", stoneNameMaster);
    $("input[name='species_mode']").on("change", syncSpeciesMode);
    $("#agreement_no, #certi_display").on("input", function () {
        if (requiresBookingUnlock) clearBookingState();
    }).on("change blur", function () {
        fetchBooking(false);
    });
    $("#report_type_id").on("change", syncReportTypeText);
    $("input[name='tests[]']").on("change", syncTests);
    $("#cstone_fetch_image, #fetch_image").on("click", fetchImage);
    $("#cstone_copy_button").on("click", copyCertificate);
    $(".cstone-weight-unit").on("change", function () {
        syncStoneWeightUnits(this.value);
    });
    syncSpeciesMode();
    document.addEventListener("keydown", function (event) {
        if (event.keyCode === 119) {
            event.preventDefault();
            fetchImage();
        }
    });

    cameraModal.on("shown.bs.modal", function () { startCamera(selectedCameraId); });
    cameraModal.on("hidden.bs.modal", stopCamera);
    cameraSelect.addEventListener("change", function () {
        selectedCameraId = this.value;
        startCamera(selectedCameraId);
    });
    var captureButton = document.getElementById("cstone_capture") || document.getElementById("take-picture-button");
    captureButton.addEventListener("click", function () {
        if (!video.videoWidth) {
            toast("warning", "Camera is still starting.");
            return;
        }
        var canvas = document.createElement("canvas");
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext("2d").drawImage(video, 0, 0);
        cropImage.src = canvas.toDataURL("image/jpeg", 0.92);
        stopCamera();
        cameraModal.modal("hide");
        cropModal.modal("show");
    });
    cropModal.on("shown.bs.modal", function () {
        if (cameraCropper) cameraCropper.destroy();
        cameraCropper = new Cropper(cropImage, { aspectRatio: 1, viewMode: 3, autoCropArea: 1 });
    });
    cropModal.on("hidden.bs.modal", function () {
        if (cameraCropper) cameraCropper.destroy();
        cameraCropper = null;
    });
    var cropSaveButton = document.getElementById("cstone_crop_save") || document.getElementById("crop-button");
    cropSaveButton.addEventListener("click", function () {
        if (!cameraCropper) return;
        var croppedData = cameraCropper.getCroppedCanvas({ width: 800, height: 800 }).toDataURL("image/jpeg", 0.9);
        $.post("save_image.php", { croppedData: croppedData, certino: "", upload_token: uploadToken() }).done(function (html) {
            cropModal.modal("hide");
            showPreview(html);
            toast("success", "Stone image captured.");
        }).fail(function (xhr) {
            toast("error", xhr.responseText || "Unable to save camera image.");
        });
    });

    form.addEventListener("reset", function () {
        window.setTimeout(function () {
            $("#certi_display").val("");
            $("#certi_no").val("");
            clearBookingState();
            setLocalDate();
            resetImage();
        }, 0);
    });

    window.stoneNameMaster = stoneNameMaster;
    if (requiresBookingUnlock) setBookingLock(true);
    setLocalDate();
})();
