(function () {
    "use strict";

    var form = document.getElementById("jewellery_feed");
    if (!form) return;

    var submit = document.getElementById("jewel_submit");
    var upload = document.getElementById("jewel_upload_image");
    var preview = document.getElementById("fetched_image");
    var empty = document.getElementById("jewel_image_empty");
    var cameraModal = $("#jewel_camera_modal");
    var cropModal = $("#jewel_crop_modal");
    var video = document.getElementById("jewel_video");
    var cameraSelect = document.getElementById("jewel_camera_select");
    var cropImage = document.getElementById("jewel_crop_image");
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
            field.value = "j" + Date.now().toString(36) + Math.random().toString(36).slice(2);
        }
        return field.value;
    }

    function show(html) {
        preview.innerHTML = html || "";
        if (empty) empty.style.display = preview.querySelector("img") ? "none" : "block";
    }

    function resetImage() {
        preview.innerHTML = "";
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
                    control.data("jewelBookingLocked", true).prop("disabled", true);
                }
            } else if (control.data("jewelBookingLocked")) {
                control.prop("disabled", false).removeData("jewelBookingLocked");
            }
        });
        $(".jewel-card, .jewel-actions").toggleClass("jewel-locked", locked);
    }

    function clearBookingState() {
        requestId++;
        setEditMode(null);
        setBookingLock(true);
    }

    function syncReportType() {
        var option = $("#report_type_id option:selected");
        $("#report_type_text").val($.trim(option.text()));
        $("#report_format").val(option.data("format") || "postcard");
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
        $("#description").val(booking.particulars || "");
        $("#weight").val(booking.gross_wt || "");
        $("#colour").val(booking.color || "");
        $("#dia_wt").val(booking.dia_wt || "");
        $("#stone_wt1").val(booking.stone_wt || "");
    }

    function fillFromExisting(row) {
        if (!row) return;
        $("#agreement_no").val(row.ag_no || "");
        $("#certi_display").val(row.certi_no || "");
        $("#date").val(row.date || "");
        $("#description").val(row.desc1 || row.desc || "");
        $("#metal_type").val(row.gold_purit || "");
        $("#weight").val(row.gross_wt || row.stone_wt || "");
        $("#comments").val(row.comment || "");
        $("#dia_wt").val(row.dia_wt || "");
        $("#shape_cut").val(row.shape_cut || "");
        $("#colour").val(row.color || "");
        $("#clarity").val(row.clarity || "");
        $("#finish").val(row.finish || "");
        $("#stone_name").val(row.stone_name || "");
        for (var i = 1; i <= 7; i++) {
            $("#cr" + i).val(row["cr" + i] || "");
            $("#stone_wt" + i).val(row["stone_wt" + i] || "");
            $("#cs" + i).val(row["cs" + i] || "");
        }
        setReportType(row.report_typ || "", row.category || "");
        setEditMode(row);
    }

    function bookingKeys() {
        return {
            agreementNo: $.trim($("#agreement_no").val()),
            certiNo: $.trim($("#certi_display").val())
        };
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
            data: { agreement_no: keys.agreementNo, certi_no: keys.certiNo, report_type: "J" }
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
                toast("warning", "This jewellery certificate is already generated. Loaded for editing.");
            } else {
                fillFromBooking(response.booking);
                setBookingLock(false);
                fetchImage(true);
                if (!silent) toast("success", "Booked jewellery details loaded.");
            }
        }).fail(function (xhr) {
            if (activeRequest !== requestId) return;
            var message = "Unable to fetch booked certificate.";
            try { message = JSON.parse(xhr.responseText).message || message; } catch (error) {}
            clearBookingState();
            if (!silent) toast("error", message);
        });
    }

    function stoneNameMaster() {
        var stoneName = $.trim($("#stone_name").val());
        if (!stoneName) return;
        $.ajax({
            url: "fetch_master_details.php",
            method: "POST",
            dataType: "json",
            data: { master_stone_name: stoneName }
        }).done(function (data) {
            if (!data || data.status === "not_found") return;
            if (!$("#shape_cut").val() && data.shape_cut) $("#shape_cut").val(data.shape_cut);
            if (!$("#colour").val() && data.colour) $("#colour").val(data.colour);
            if (!$("#comments").val() && data.comment) $("#comments").val(data.comment);
        });
    }

    function valid() {
        $(".jewel-field").removeClass("has-error");
        var keys = bookingKeys();
        if (!keys.agreementNo || !keys.certiNo || !bookingUnlocked) {
            toast("error", "Enter agreement no and certificate no, then wait for booked details to load.");
            return false;
        }
        var required = ["date", "report_type_id", "description", "weight"];
        for (var i = 0; i < required.length; i++) {
            var field = document.getElementById(required[i]);
            if (!field || !String(field.value || "").trim()) {
                $(field).closest(".jewel-field").addClass("has-error");
                if (field) field.focus();
                toast("error", "Please complete " + $(field).closest(".jewel-field").find("label").text().replace(" *", "") + ".");
                return false;
            }
        }
        return true;
    }

    function fetchImage(silent) {
        var keys = bookingKeys();
        $.post("image_fetch.php", { input: keys.certiNo || "", upload_token: token() }).done(show).fail(function () {
            if (!silent) toast("error", "Unable to fetch image.");
        });
    }

    function openGeneratedReport(certiNo) {
        if (!certiNo) return;
        window.open("report-open-by-certificate.php?certi_no=" + encodeURIComponent(certiNo), "_blank");
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
                toast("error", response && response.message ? response.message : "Unable to save jewellery report.");
                return;
            }
            var verb = response.action === "updated" ? "updated" : "saved";
            $("#jewel_saved_certificate").text(response.certi_no);
            $("#jewel_saved_report").text(response.report_no);
            $("#jewel_save_result").css("display", "flex");
            toast("success", "Jewellery report " + verb + ". Certificate " + response.certi_no + " / Report " + response.report_no);
            var askMessage = "Jewellery report " + verb + " for certificate " + response.certi_no + ". Generate this report now?";
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
            var message = "Unable to save jewellery report.";
            try { message = JSON.parse(xhr.responseText).message || message; } catch (error) {}
            toast("error", message);
        }).always(function () {
            submit.disabled = !bookingUnlocked;
            submit.innerHTML = editExistingReportId ? '<i class="fa fa-check"></i> Update Form' : defaultSubmitHtml;
        });
    });

    upload.addEventListener("change", function () {
        if (!this.files || !this.files[0]) return;
        var data = new FormData();
        data.append("image_file", this.files[0]);
        data.append("certino", "");
        data.append("upload_token", token());
        $.ajax({ url: "upload.php", method: "POST", data: data, processData: false, contentType: false }).done(function (html) {
            $("#jewel_folder_modal").modal("hide");
            show(html);
            toast("success", "Jewellery image attached.");
        }).fail(function (xhr) {
            toast("error", xhr.responseText || "Unable to upload image.");
        });
        this.value = "";
    });

    $("#agreement_no, #certi_display").on("input", clearBookingState).on("change blur", function () { fetchBooking(false); });
    $("#stone_name").on("change blur", stoneNameMaster);
    $("#report_type_id").on("change", syncReportType);
    $("#jewel_fetch_image").on("click", fetchImage);
    document.addEventListener("keydown", function (event) {
        if (event.keyCode === 119) {
            event.preventDefault();
            fetchImage();
        }
    });

    function stop() {
        if (stream) {
            stream.getTracks().forEach(function (track) { track.stop(); });
            stream = null;
        }
        video.srcObject = null;
    }

    async function cameras() {
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

    async function start(id) {
        stop();
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: id ? { deviceId: { exact: id } } : { facingMode: { ideal: "environment" } }, audio: false });
            video.srcObject = stream;
            await video.play();
            await cameras();
        } catch (error) {
            toast("error", "Unable to open the selected camera.");
            cameraModal.modal("hide");
        }
    }

    cameraModal.on("shown.bs.modal", function () { start(deviceId); }).on("hidden.bs.modal", stop);
    cameraSelect.addEventListener("change", function () { deviceId = this.value; start(deviceId); });
    document.getElementById("jewel_capture").addEventListener("click", function () {
        if (!video.videoWidth) {
            toast("warning", "Camera is still starting.");
            return;
        }
        var canvas = document.createElement("canvas");
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext("2d").drawImage(video, 0, 0);
        cropImage.src = canvas.toDataURL("image/jpeg", 0.92);
        stop();
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
    document.getElementById("jewel_crop_save").addEventListener("click", function () {
        if (!cropper) return;
        $.post("save_image.php", { croppedData: cropper.getCroppedCanvas({ width: 800, height: 800 }).toDataURL("image/jpeg", 0.9), certino: "", upload_token: token() }).done(function (html) {
            cropModal.modal("hide");
            show(html);
            toast("success", "Jewellery image captured.");
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
