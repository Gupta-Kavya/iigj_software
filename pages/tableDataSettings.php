<?php include "assets/navbar.php"; ?>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <h1 class="page-header"><i class="fa fa-cog fa-fw"></i> REPORT SETTINGS</h1>
            </div>
        </div>

        <div class="row">
            <ul class="nav nav-tabs">
                <li role="presentation" class="active"><a href="settings.php">Card Builder</a></li>
                <li role="presentation"><a href="backPrintSettings.php">Back Image &amp; Print</a></li>
            </ul>

            <div class="panel" style="margin-top:10px;">
                <div class="panel-body">
                <form id="reportForm">
            <div class="form-group">
                <label for="reportNo">Report No:</label>
                <select class="form-control display-select" id="reportNo" data-target="#reportNo">
                    <option value="block">Block</option>
                    <option value="none">None</option>
                </select>
            </div>
            <div class="form-group">
                <label for="weight">Weight:</label>
                <select class="form-control display-select" id="weight" data-target="#weight">
                    <option value="block">Block</option>
                    <option value="none">None</option>
                </select>
            </div>
            <div class="form-group">
                <label for="shapeCut">Shape / Cut:</label>
                <select class="form-control display-select" id="shapeCut" data-target="#shapeCut">
                    <option value="block">Block</option>
                    <option value="none">None</option>
                </select>
            </div>
            <div class="form-group">
                <label for="dimension">Dimension:</label>
                <select class="form-control display-select" id="dimension" data-target="#dimension">
                    <option value="block">Block</option>
                    <option value="none">None</option>
                </select>
            </div>
            <div class="form-group">
                <label for="colour">Colour:</label>
                <select class="form-control display-select" id="colour" data-target="#colour">
                    <option value="block">Block</option>
                    <option value="none">None</option>
                </select>
            </div>
            <div class="form-group">
                <label for="refractiveIndex">Refractive Index:</label>
                <select class="form-control display-select" id="refractiveIndex" data-target="#refractiveIndex">
                    <option value="block">Block</option>
                    <option value="none">None</option>
                </select>
            </div>
            <div class="form-group">
                <label for="specificGravity">Specific Gravity:</label>
                <select class="form-control display-select" id="specificGravity" data-target="#specificGravity">
                    <option value="block">Block</option>
                    <option value="none">None</option>
                </select>
            </div>
            <div class="form-group">
                <label for="speciesGroup">Species / Group:</label>
                <select class="form-control display-select" id="speciesGroup" data-target="#speciesGroup">
                    <option value="block">Block</option>
                    <option value="none">None</option>
                </select>
            </div>
            <div class="form-group">
                <label for="remarks">Remarks:</label>
                <select class="form-control display-select" id="remarks" data-target="#remarks">
                    <option value="block">Block</option>
                    <option value="none">None</option>
                </select>
            </div>
            <button type="button" id="saveSettings" class="btn btn-primary">Save Settings</button>
        </form>
            </div>
        </div>
    </div>
    </div>  
</div>

<?php include "assets/footer.php"; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Load existing data from JSON file
    $.getJSON("settings.json", function(data) {
        if (data) {
            $('#reportNo').val(data.reportNo);
            $('#weight').val(data.weight);
            $('#shapeCut').val(data.shapeCut);
            $('#dimension').val(data.dimension);
            $('#colour').val(data.colour);
            $('#refractiveIndex').val(data.refractiveIndex);
            $('#specificGravity').val(data.specificGravity);
            $('#speciesGroup').val(data.speciesGroup);
            $('#remarks').val(data.remarks);
        }
    });

    // Save settings
    $('#saveSettings').click(function() {
        var settings = {
            reportNo: $('#reportNo').val(),
            weight: $('#weight').val(),
            shapeCut: $('#shapeCut').val(),
            dimension: $('#dimension').val(),
            colour: $('#colour').val(),
            refractiveIndex: $('#refractiveIndex').val(),
            specificGravity: $('#specificGravity').val(),
            speciesGroup: $('#speciesGroup').val(),
            remarks: $('#remarks').val()
        };

        $.post('save_settings.php', { settings: JSON.stringify(settings) }, function(response) {
            alert('Settings saved!');
        });
    });
});
</script>
