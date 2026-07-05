<?php include "assets/navbar.php"; ?>

<div id="page-wrapper">
    <div class="container-fluid master-page">
        <div class="master-header">
            <h1><i class="fa fa-plus-circle"></i> Add Master Details</h1>
            <p>Add dropdown values for stone names, colours, shape/cut, RI, and magnification.</p>
        </div>

        <div class="master-card">
            <div class="master-card-head">
                <h3>Choose master type</h3>
                <p>Select what you want to add, fill the fields, then save.</p>
            </div>
            <div class="master-card-body">
                <div class="master-field master-type-select">
                    <label for="select_master_menu">Master type</label>
                    <select id="select_master_menu" class="form-control" onchange="fetchForm();">
                        <option value="" disabled selected>Choose master menu</option>
                        <option value="stone_name_master">Stone Name Master</option>
                        <option value="shape_cut_master">Shape / Cut Master</option>
                        <option value="colour_master">Colour Master</option>
                        <option value="ri_master">Refractive Index Master</option>
                        <option value="magni_master">Magnification Master</option>
                    </select>
                </div>

                <div id="master_panel" style="display:none;">
                    <div class="master-field-grid" id="stone_name_master" style="display:none;">
                        <div class="master-field">
                            <label for="stone_name">Stone name</label>
                            <input type="text" id="stone_name" class="form-control" placeholder="e.g. Ruby">
                        </div>
                        <div class="master-field">
                            <label for="group">Species / group</label>
                            <input type="text" id="group" class="form-control" placeholder="e.g. Corundum">
                        </div>
                    </div>

                    <div class="master-field single-col-field" id="shape_cut" style="display:none;">
                        <label for="shape_cutt">Shape / cut</label>
                        <input type="text" id="shape_cutt" class="form-control" placeholder="e.g. Oval / Faceted">
                    </div>

                    <div class="master-field single-col-field" id="Colour" style="display:none;">
                        <label for="colour">Colour</label>
                        <input type="text" id="colour" class="form-control" placeholder="e.g. Red">
                    </div>

                    <div class="master-field single-col-field" id="Refractive_Index" style="display:none;">
                        <label for="ri">Refractive index</label>
                        <input type="text" id="ri" class="form-control" placeholder="e.g. 1.762 - 1.770">
                    </div>

                    <div class="master-field single-col-field" id="Magnification" style="display:none;">
                        <label for="magni">Magnification</label>
                        <input type="text" id="magni" class="form-control" placeholder="e.g. 10x">
                    </div>

                    <div class="master-action-bar">
                        <button type="button" class="btn btn-primary" id="save_master"><i class="fa fa-save"></i> Save Master</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.master-page .single-col-field {
    margin-bottom: 14px;
}
</style>

<script>
    function hideAllMasterFields() {
        $("#stone_name_master, #shape_cut, #Colour, #Refractive_Index, #Magnification").hide();
    }

    function clearMasterFields() {
        $("#stone_name, #group, #shape_cutt, #colour, #ri, #magni").val("");
    }

    function fetchForm() {
        var selected = $("#select_master_menu").val();
        hideAllMasterFields();
        clearMasterFields();

        if (!selected) {
            $("#master_panel").hide();
            return;
        }

        $("#master_panel").show();

        if (selected === "stone_name_master") {
            $("#stone_name_master").show();
        } else if (selected === "shape_cut_master") {
            $("#shape_cut").show();
        } else if (selected === "colour_master") {
            $("#Colour").show();
        } else if (selected === "ri_master") {
            $("#Refractive_Index").show();
        } else if (selected === "magni_master") {
            $("#Magnification").show();
        }
    }

    $("#save_master").click(function () {
        var stone_name = $("#stone_name").val().trim();
        var group = $("#group").val().trim();
        var shape_cutt = $("#shape_cutt").val().trim();
        var colour = $("#colour").val().trim();
        var ri = $("#ri").val().trim();
        var magni = $("#magni").val().trim();
        var data = {};

        if (stone_name !== "" || group !== "") {
            if (stone_name === "" || group === "") {
                AppToast.error("Please enter both stone name and species / group.");
                return;
            }
            data = { stone_name: stone_name, group: group };
        } else if (shape_cutt !== "") {
            data = { shape_cutt: shape_cutt };
        } else if (colour !== "") {
            data = { colour: colour };
        } else if (ri !== "") {
            data = { ri: ri };
        } else if (magni !== "") {
            data = { magni: magni };
        } else {
            AppToast.error("Please fill the required field before saving.");
            return;
        }

        var $btn = $("#save_master");
        $btn.prop("disabled", true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: "add_master_initiator.php",
            type: "POST",
            dataType: "json",
            data: data,
            success: function (response) {
                if (response.status === "success") {
                    AppToast.success(response.message || "Master data saved.");
                    clearMasterFields();
                } else {
                    AppToast.error(response.message || "Unable to save master data.");
                }
            },
            error: function () {
                AppToast.error("Unable to save master data. Please try again.");
            },
            complete: function () {
                $btn.prop("disabled", false).html('<i class="fa fa-save"></i> Save Master');
            }
        });
    });
</script>

<?php include "assets/footer.php"; ?>
