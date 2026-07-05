<?php include 'assets/navbar.php'; ?>
<style>
.edit-page { padding-bottom: 35px; }
.edit-hero {
    border-bottom: 1px solid #ececf1;
    margin: 0 0 24px;
    padding: 0 0 20px;
}
.edit-hero h1 { border: 0; color: #171717; font-size: 26px; font-weight: 600; margin: 0 0 6px; padding: 0; }
.edit-hero p { color: #737373; margin: 0; }
.edit-search-card,
.edit-form-card {
    background: #fff;
    border: 1px solid #ececf1;
    border-radius: 10px;
    margin-bottom: 16px;
    overflow: hidden;
}
.edit-search-card { padding: 20px; }
.edit-search-grid {
    align-items: end;
    display: grid;
    gap: 12px;
    grid-template-columns: minmax(220px, 1fr) auto;
}
.edit-search-grid label {
    color: #404040;
    display: block;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 7px;
}
.edit-search-grid .form-control {
    border-radius: 8px;
    height: 44px;
}
.edit-search-grid .btn {
    border-radius: 8px;
    font-weight: 500;
    height: 44px;
    min-width: 145px;
}
.edit-status {
    border-radius: 10px;
    display: none;
    margin-top: 14px;
    padding: 12px 14px;
}
.edit-status.info { background: #f7f7f8; border: 1px solid #ececf1; color: #404040; }
.edit-status.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
.edit-status.error { background: #fff1f2; border: 1px solid #fecdd3; color: #be123c; }
.edit-form-head {
    align-items: center;
    border-bottom: 1px solid #ececf1;
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 18px;
}
.edit-form-head h3 {
    color: #171717;
    font-size: 16px;
    font-weight: 600;
    margin: 0;
}
.edit-form-head p {
    color: #737373;
    font-size: 12px;
    margin: 4px 0 0;
}
.edit-badge {
    background: #f0fdf4;
    border-radius: 999px;
    color: #15803d;
    font-size: 12px;
    font-weight: 500;
    padding: 6px 10px;
}
.edit-form-card form { padding: 20px; }
.edit-form-grid {
    display: grid;
    gap: 14px;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.edit-form-grid label {
    color: #404040;
    font-size: 12px;
    font-weight: 500;
}
.edit-form-grid .form-control {
    border-radius: 8px;
    min-height: 42px;
}
.edit-form-grid textarea.form-control {
    min-height: 86px;
    resize: vertical;
}
.full-span { grid-column: 1 / -1; }
.edit-form-actions {
    display: flex;
    gap: 10px;
    margin-top: 16px;
}
.edit-form-actions .btn {
    border-radius: 8px;
    font-weight: 500;
    min-height: 42px;
}
@media (max-width: 992px) {
    .edit-form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 640px) {
    .edit-search-grid,
    .edit-form-grid { grid-template-columns: 1fr; }
    .edit-search-grid .btn,
    .edit-form-actions .btn { width: 100%; }
    .edit-form-actions { display: block; }
}
</style>

<div id="page-wrapper">
    <div class="container-fluid edit-page">
        <div class="edit-hero">
            <h1><i class="fa fa-edit"></i> Edit Reports</h1>
            <p>Search a certificate number, update report details, and save instantly without page reload.</p>
        </div>

        <div class="edit-search-card">
            <form id="edit-search-form">
                <div class="edit-search-grid">
                    <div>
                        <label for="certi_no">Certificate Number</label>
                        <input type="number" class="form-control" placeholder="Example: 1024" id="certi_no" min="1" required>
                    </div>
                    <button class="btn btn-primary" type="submit" id="verify"><i class="fa fa-search"></i> Find Report</button>
                </div>
            </form>
            <div id="edit-status" class="edit-status"></div>
        </div>

        <div id="edit-forms"></div>
    </div>
</div>

<script>
function showEditStatus(type, message) {
    $("#edit-status")
        .removeClass("info success error")
        .addClass(type)
        .html(message)
        .show();
}

$("#edit-search-form").on("submit", function (event) {
    event.preventDefault();
    var certiNo = parseInt($("#certi_no").val(), 10);
    if (!certiNo || certiNo < 1) {
        showEditStatus("error", "Please enter a valid certificate number.");
        return;
    }

    $("#verify").prop("disabled", true).html('<i class="fa fa-spinner fa-spin"></i> Searching...');
    showEditStatus("info", "Searching certificate #" + certiNo + "...");
    $("#edit-forms").empty();

    $.ajax({
        url: "edit_initiator.php",
        type: "POST",
        dataType: "json",
        data: { certi_no: certiNo },
        success: function (response) {
            if (response.status === "success") {
                $("#edit-forms").html(response.html);
                showEditStatus("success", "Report loaded successfully.");
            } else {
                showEditStatus("error", response.message || "Report not found.");
            }
        },
        error: function () {
            showEditStatus("error", "Unable to load the report. Please try again.");
        },
        complete: function () {
            $("#verify").prop("disabled", false).html('<i class="fa fa-search"></i> Find Report');
        }
    });
});

$(document).on("submit", "#edit-report-form", function (event) {
    event.preventDefault();
    var form = $(this);
    var button = form.find('button[type="submit"]');
    button.prop("disabled", true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
    showEditStatus("info", "Saving report changes...");

    $.ajax({
        url: form.attr("action"),
        type: "POST",
        dataType: "json",
        data: form.serialize(),
        success: function (response) {
            if (response.status === "success") {
                showEditStatus("success", response.message || "Report updated successfully.");
            } else {
                showEditStatus("error", response.message || "Unable to save report.");
            }
        },
        error: function () {
            showEditStatus("error", "Unable to save report. Please try again.");
        },
        complete: function () {
            button.prop("disabled", false).html('<i class="fa fa-save"></i> Save Changes');
        }
    });
});

$(document).on("change input", '#edit-report-form input[name="stone_name"]', function () {
    var stoneName = $.trim($(this).val());
    if (!stoneName) return;

    $.ajax({
        url: "fetch_master_details.php",
        type: "POST",
        dataType: "json",
        data: { master_stone_name: stoneName },
        success: function (response) {
            if (response && response.group) {
                $('#edit-report-form input[name="spe_group"]').val(response.group);
            }
        }
    });
});

$(document).on("click", "#clear-edit-form", function () {
    $("#edit-forms").empty();
    $("#certi_no").val("").focus();
    showEditStatus("info", "Search another certificate number.");
});
</script>

<?php include 'assets/footer.php'; ?>
