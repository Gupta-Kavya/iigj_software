(function () {
    "use strict";

    var form = document.getElementById("diamond_screening_feed");
    if (!form) return;

    var submit = document.getElementById("ds_submit");
    var upload = document.getElementById("ds_upload_image");
    var preview = document.getElementById("fetched_image");
    var empty = document.getElementById("ds_image_empty");
    var cameraModal = $("#ds_camera_modal");
    var cropModal = $("#ds_crop_modal");
    var video = document.getElementById("ds_video");
    var cameraSelect = document.getElementById("ds_camera_select");
    var cropImage = document.getElementById("ds_crop_image");
    var stream = null;
    var cropper = null;
    var deviceId = "";
    var requestId = 0;
    var bookingUnlocked = false;
    var editExistingReportId = 0;
    var defaultSubmitHtml = submit ? submit.innerHTML : '<i class="fa fa-check"></i> Submit Form';

    function toast(type, message) {
        if (window.AppToast && AppToast[type]) {
            AppToast[type](message);
        } else {
            alert(message);
        }
    }

    function token() {
        var field = document.getElementById("upload_token");
        if (!field.value) {
            field.value = "ds" + Date.now().toString(36) + Math.random().toString(36).slice(2);
        }
        return field.value;
    }

    function showImage(html) {
        if (!preview) return;
        preview.innerHTML = html || "";
        if (empty) empty.style.display = preview.querySelector("img") ? "none" : "block";
    }

    function resetImage() {
        if (preview) preview.innerHTML = "";
        if (empty) empty.style.display = "block";
    }

    function localDate() {
        if (!$("#date").val()) {
            var now = new Date();
            var local = new Date(now.getTime() - now.getTimezoneOffset() * 60000);
            $("#date").val(local.toISOString().slice(0, 10));
        }
    }

    function bookingControls() {
        return $(form).find("input, select, textarea, button")
            .not("#agreement_no,#certi_display")
            .not("[type='hidden']")
            .not("[type='reset']");
    }

    function setEditMode(row) {
        editExistingReportId = row && row.id ? Number(row.id) || 0 : 0;
        if (submit) {
            submit.innerHTML = editExistingReportId ? '<i class="fa fa-check"></i> Update Form' : defaultSubmitHtml;
        }
    }

    function setBookingLock(locked) {
        bookingUnlocked = !locked;
        bookingControls().each(function () {
            var control = $(this);
            if (locked) {
                if (!control.prop("disabled")) {
                    control.data("dsBookingLocked", true).prop("disabled", true);
                }
            } else if (control.data("dsBookingLocked")) {
                control.prop("disabled", false).removeData("dsBookingLocked");
            }
        });
    }

    function clearBookingState() {
        requestId++;
        setEditMode(null);
        setBookingLock(true);
    }

    function bookingKeys() {
        return {
            agreementNo: $.trim($("#agreement_no").val()),
            certiNo: $.trim($("#certi_display").val())
        };
    }

    function syncReportType() {
        var option = $("#report_type_id option:selected");
        $("#report_type_text").val($.trim(option.text()));
        $("#report_format").val(option.data("format") || "a4");
    }

    function setReportType(id, name) {
        if (id && $("#report_type_id option[value='" + id + "']").length) {
            $("#report_type_id").val(id);
        } else if (name) {
            $("#report_type_id option").filter(function () {
                return $.trim($(this).text()).toLowerCase() === $.trim(name).toLowerCase();
            }).prop("selected", true);
        }
        syncReportType();
    }

    function fillFromBooking(booking) {
        if (!booking) return;
        setEditMode(null);
        $("#agreement_no").val(booking.agreement_no || "");
        $("#certi_display").val(booking.certi_no || "");
        $("#shape_cut").val(booking.particulars || "");
        $("#total_weight").val(booking.dia_wt || booking.stone_wt || booking.gross_wt || "");
        $("#total_pcs").val(booking.pcs || "");
    }

    function fillFromExisting(row) {
        if (!row) return;
        $("#agreement_no").val(row.ag_no || "");
        $("#certi_display").val(row.certi_no || "");
        $("#date").val(row.date || "");
        $("#shape_cut").val(row.shape_cut || "");
        $("#total_weight").val(row.dia_wt || row.stone_wt || row.gross_wt || "");
        $("#total_pcs").val(row.pcs || row.testd_pcs || row.stone_pcs || row.faces || "");
        $("#nat_dia_wt").val(row.nat_dia_wt || "");
        $("#syn_dia_wt").val(row.syn_dia_wt || "");
        $("#ref_dia_wt").val(row.ref_dia_wt || "");
        $("#non_dia_wt").val(row.non_dia_wt || "");
        $("#nat_dia_pc").val(row.nat_dia_pc || "");
        $("#syn_dia_pc").val(row.syn_dia_pc || "");
        $("#ref_dia_pc").val(row.ref_dia_pc || "");
        $("#non_dia_pc").val(row.non_dia_pc || "");
        setReportType(row.report_typ || "", row.category || "");
        setEditMode(row);
    }

    function fetchBooking(silent) {
        var keys = bookingKeys();
        if (!keys.agreementNo || !keys.certiNo) {
            clearBookingState();
            return;
        }
        var activeRequest = ++requestId;
        setBookingLock(true);
        $.ajax({
            url: "cstone-booking-fetch.php",
            method: "POST",
            dataType: "json",
            data: { agreement_no: keys.agreementNo, certi_no: keys.certiNo, report_type: "DS" }
        }).done(function (response) {
            if (activeRequest !== requestId) return;
            if (!response || response.status !== "success") {
                clearBookingState();
                if (!silent) toast("error", response && response.message ? response.message : "Booked certificate not found.");
                return;
            }
            if (response.existing_other_type) {
                clearBookingState();
                toast("warning", "This certificate is already generated in another feeding type. Please open it from the correct feeding page.");
                return;
            }
            if (response.existing_report) {
                fillFromExisting(response.existing_report);
                setBookingLock(false);
                fetchImage(true);
                toast("success", "Saved diamond screening details loaded for editing.");
            } else {
                fillFromBooking(response.booking);
                setBookingLock(false);
                fetchImage(true);
                if (!silent) toast("success", "Booked diamond screening details loaded.");
            }
        }).fail(function (xhr) {
            if (activeRequest !== requestId) return;
            var message = "Unable to fetch booked certificate.";
            try { message = JSON.parse(xhr.responseText).message || message; } catch (error) {}
            clearBookingState();
            if (!silent) toast("error", message);
        });
    }

    function valid() {
        $(".ds-field").removeClass("has-error");
        var keys = bookingKeys();
        if (!keys.agreementNo || !keys.certiNo || !bookingUnlocked) {
            toast("error", "Enter agreement no and certificate no, then wait for booked details to load.");
            return false;
        }
        var required = ["date", "report_type_id"];
        for (var i = 0; i < required.length; i++) {
            var field = document.getElementById(required[i]);
            if (!field || !String(field.value || "").trim()) {
                $(field).closest(".ds-field").addClass("has-error");
                if (field) field.focus();
                toast("error", "Please complete " + $(field).closest(".ds-field").find("label").text().replace(" *", "") + ".");
                return false;
            }
        }
        return true;
    }

    function openGeneratedReport(certiNo) {
        if (!certiNo) return;
        window.open("report-open-by-certificate.php?certi_no=" + encodeURIComponent(certiNo), "_blank");
    }

    function openGeneratedLabel(certiNo) {
        if (!certiNo) return;
        window.open("diamond-screening-label-print.php?certi_no=" + encodeURIComponent(certiNo), "_blank");
    }

    function showAfterSaveOptions(response, verb) {
        var certiNo = response && response.certi_no ? response.certi_no : "";
        var reportNo = response && response.report_no ? response.report_no : "";
        var modal = $("#ds_after_save_modal");
        if (!modal.length) {
            var askMessage = "Diamond screening report " + verb + " for certificate " + certiNo + ". Generate this report now?";
            if (window.AppConfirm && typeof AppConfirm.show === "function") {
                AppConfirm.show(askMessage, { title: "Generate report?", confirmText: "Generate Report", cancelText: "Exit" }).then(function (confirmed) {
                    if (confirmed) openGeneratedReport(certiNo);
                    resetAfterSave();
                });
            } else if (window.confirm(askMessage)) {
                openGeneratedReport(certiNo);
                resetAfterSave();
            } else {
                resetAfterSave();
            }
            return;
        }
        $("#ds_after_save_certificate").text(certiNo);
        $("#ds_after_save_report").text(reportNo);
        modal.data("certiNo", certiNo);
        modal.modal({ backdrop: "static", keyboard: true });
        modal.modal("show");
    }

    function fetchImage(silent) {
        var keys = bookingKeys();
        $.post("image_fetch.php", { input: keys.certiNo || "", upload_token: token() }).done(showImage).fail(function () {
            if (!silent) toast("error", "Unable to fetch image.");
        });
    }

    function resetAfterSave() {
        form.reset();
        document.getElementById("upload_token").value = "";
        setEditMode(null);
        clearBookingState();
        localDate();
        resetImage();
        $("#agreement_no").focus();
    }

    form.addEventListener("submit", function (event) {
        event.preventDefault();
        if (!valid()) return;
        syncReportType();
        var data = new FormData(form);
        data.set("upload_token", token());
        data.set("edit_existing_report", editExistingReportId ? "1" : "0");
        data.set("edit_existing_report_id", editExistingReportId ? String(editExistingReportId) : "");
        data.set("weight", $("#total_weight").val());
        data.set("dia_wt", $("#total_weight").val());
        submit.disabled = true;
        submit.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
        $.ajax({
            url: "process-form.php",
            method: "POST",
            data: data,
            processData: false,
            contentType: false,
            dataType: "json"
        }).done(function (response) {
            if (!response || response.status !== "success") {
                toast("error", response && response.message ? response.message : "Unable to save diamond screening report.");
                return;
            }
            var verb = response.action === "updated" ? "updated" : "saved";
            $("#ds_saved_certificate").text(response.certi_no);
            $("#ds_saved_report").text(response.report_no);
            $("#ds_save_result").css("display", "flex");
            toast("success", "Diamond screening report " + verb + ". Certificate " + response.certi_no + " / Report " + response.report_no);
            showAfterSaveOptions(response, verb);
        }).fail(function (xhr) {
            var message = "Unable to save diamond screening report.";
            try { message = JSON.parse(xhr.responseText).message || message; } catch (error) {}
            toast("error", message);
        }).always(function () {
            submit.disabled = !bookingUnlocked;
            submit.innerHTML = editExistingReportId ? '<i class="fa fa-check"></i> Update Form' : defaultSubmitHtml;
        });
    });

    $("#agreement_no, #certi_display").on("input", clearBookingState).on("change blur", function () { fetchBooking(false); });
    $("#report_type_id").on("change", syncReportType);
    $("#ds_fetch_image").on("click", fetchImage);
    $("#ds_after_save_certificate_btn").on("click", function () {
        var certiNo = $("#ds_after_save_modal").data("certiNo");
        openGeneratedReport(certiNo);
        $("#ds_after_save_modal").modal("hide");
    });
    $("#ds_after_save_label_btn").on("click", function () {
        var certiNo = $("#ds_after_save_modal").data("certiNo");
        openGeneratedLabel(certiNo);
        $("#ds_after_save_modal").modal("hide");
    });
    $("#ds_after_save_exit_btn").on("click", function () {
        $("#ds_after_save_modal").modal("hide");
    });
    $("#ds_after_save_modal").on("hidden.bs.modal", function () {
        resetAfterSave();
    });
    if (upload) {
        upload.addEventListener("change", function () {
            if (!this.files || !this.files[0]) return;
            var data = new FormData();
            data.append("image_file", this.files[0]);
            data.append("certino", "");
            data.append("upload_token", token());
            $.ajax({ url: "upload.php", method: "POST", data: data, processData: false, contentType: false }).done(function (html) {
                $("#ds_folder_modal").modal("hide");
                showImage(html);
                toast("success", "Stone image attached.");
            }).fail(function (xhr) {
                toast("error", xhr.responseText || "Unable to upload image.");
            });
            this.value = "";
        });
    }
    document.addEventListener("keydown", function (event) {
        if (event.keyCode === 119) {
            event.preventDefault();
            fetchImage();
        }
    });

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(function (track) { track.stop(); });
            stream = null;
        }
        if (video) video.srcObject = null;
    }

    async function loadCameras() {
        if (!navigator.mediaDevices || !cameraSelect) return;
        var devices = await navigator.mediaDevices.enumerateDevices();
        var cameraDevices = devices.filter(function (device) { return device.kind === "videoinput"; });
        cameraSelect.innerHTML = "";
        cameraDevices.forEach(function (camera, index) {
            var option = document.createElement("option");
            option.value = camera.deviceId;
            option.textContent = camera.label || "Camera " + (index + 1);
            cameraSelect.appendChild(option);
        });
        cameraSelect.disabled = cameraDevices.length <= 1;
        deviceId = cameraSelect.value || deviceId;
    }

    async function startCamera(id) {
        stopCamera();
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            toast("error", "Camera is not available in this browser.");
            cameraModal.modal("hide");
            return;
        }
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: id ? { deviceId: { exact: id } } : { facingMode: { ideal: "environment" } }, audio: false });
            video.srcObject = stream;
            await video.play();
            await loadCameras();
        } catch (error) {
            toast("error", "Unable to open the selected camera.");
            cameraModal.modal("hide");
        }
    }

    cameraModal.on("shown.bs.modal", function () { startCamera(deviceId); }).on("hidden.bs.modal", stopCamera);
    if (cameraSelect) {
        cameraSelect.addEventListener("change", function () {
            deviceId = this.value;
            startCamera(deviceId);
        });
    }
    $("#ds_capture").on("click", function () {
        if (!video || !video.videoWidth) {
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
        if (cropper) cropper.destroy();
        cropper = new Cropper(cropImage, { aspectRatio: 1, viewMode: 3, autoCropArea: 1 });
    }).on("hidden.bs.modal", function () {
        if (cropper) cropper.destroy();
        cropper = null;
    });
    $("#ds_crop_save").on("click", function () {
        if (!cropper) return;
        $.post("save_image.php", { croppedData: cropper.getCroppedCanvas({ width: 800, height: 800 }).toDataURL("image/jpeg", 0.9), certino: "", upload_token: token() }).done(function (html) {
            cropModal.modal("hide");
            showImage(html);
            toast("success", "Stone image captured.");
        }).fail(function (xhr) {
            toast("error", xhr.responseText || "Unable to save camera image.");
        });
    });
    form.addEventListener("reset", function () {
        window.setTimeout(function () {
            $("#certi_display").val("");
            document.getElementById("upload_token").value = "";
            clearBookingState();
            localDate();
            resetImage();
        }, 0);
    });

    clearBookingState();
    localDate();
})();
