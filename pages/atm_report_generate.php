<?php include "assets/navbar.php"; ?>

<style>
    .atm-generate-wrap { padding-bottom: 35px; }
    .atm-generate-card {
        background: #fff;
        border: 1px solid #ececf1;
        border-radius: 10px;
        margin-top: 16px;
        max-width: 760px;
        overflow: hidden;
    }
    .atm-generate-head {
        border-bottom: 1px solid #ececf1;
        padding: 20px 22px;
    }
    .atm-generate-head h1 {
        border: 0;
        color: #171717;
        font-size: 22px;
        font-weight: 600;
        margin: 0 0 6px;
        padding: 0;
    }
    .atm-generate-head p {
        color: #737373;
        margin: 0;
    }
    .atm-generate-body { padding: 22px; }
    .range-grid {
        display: grid;
        gap: 14px;
        grid-template-columns: 1fr 1fr;
    }
    .output-mode-grid {
        display: grid;
        gap: 10px;
        grid-template-columns: 1fr 1fr;
        margin-bottom: 18px;
    }
    .output-mode {
        border: 1px solid #e5e5e5;
        border-radius: 10px;
        cursor: pointer;
        display: block;
        margin: 0;
        padding: 12px;
    }
    .output-mode:has(input:checked) {
        background: #f5f7ff;
        border-color: #171717;
    }
    .output-mode input { margin-right: 7px; }
    .output-mode strong { color: #171717; font-size: 13px; }
    .output-mode small { color: #737373; display: block; font-size: 12px; font-weight: 400; line-height: 1.4; margin: 5px 0 0 22px; }
    .atm-field label {
        color: #344054;
        display: block;
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 7px;
    }
    .atm-field input {
        border: 1px solid #d0d5dd;
        border-radius: 12px;
        box-shadow: none;
        height: 44px;
        padding: 10px 12px;
        width: 100%;
    }
    .atm-actions {
        display: flex;
        gap: 10px;
        margin-top: 18px;
    }
    .btn-atm-primary {
        background: #171717;
        border: 0;
        border-radius: 8px;
        color: #fff;
        font-weight: 500;
        padding: 11px 18px;
    }
    .btn-atm-light {
        background: #fff;
        border: 1px solid #e5e5e5;
        border-radius: 8px;
        color: #404040;
        font-weight: 500;
        padding: 10px 16px;
    }
    .atm-note {
        background: #f7f7f8;
        border: 1px solid #ececf1;
        border-radius: 10px;
        color: #737373;
        font-size: 13px;
        line-height: 1.45;
        margin-top: 16px;
        padding: 12px 14px;
    }
    .quick-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
    }
    .quick-chip {
        background: #eef2ff;
        border: 1px solid #dbe4ff;
        border-radius: 999px;
        color: #1e40af;
        cursor: pointer;
        font-size: 12px;
        font-weight: 700;
        padding: 6px 10px;
    }
    @media (max-width: 620px) {
        .range-grid,
        .atm-actions { display: block; }
        .atm-actions .btn { margin-bottom: 8px; width: 100%; }
    }
</style>

<div id="page-wrapper">
    <div class="container-fluid atm-generate-wrap">
        <div class="atm-generate-card">
            <div class="atm-generate-head">
                <h1><i class="fa fa-id-card-o fa-fw"></i> ATM Report Generate</h1>
                <p>Create print-ready ATM certificate sheets from a certificate number range.</p>
            </div>
            <div class="atm-generate-body">
                <form action="repo-initiator-atm.php" method="post" target="_blank" id="atm-form">
                    <div class="output-mode-grid">
                        <label class="output-mode">
                            <input type="radio" name="output_mode" value="sheet" checked>
                            <strong>A4 Sheet</strong>
                            <small>Up to 8 cards per A4 page.</small>
                        </label>
                        <label class="output-mode">
                            <input type="radio" name="output_mode" value="pvc">
                            <strong>PVC Card Pages</strong>
                            <small>CR80 page for each selected certificate.</small>
                        </label>
                    </div>
                    <div class="range-grid">
                        <div class="atm-field">
                            <label for="from">From certificate</label>
                            <input type="number" name="from" id="from" min="1" placeholder="Start no." required>
                        </div>
                        <div class="atm-field">
                            <label for="to">To certificate</label>
                            <input type="number" name="to" id="to" min="1" placeholder="End no." required>
                        </div>
                    </div>
                    <div class="quick-row">
                        <span class="quick-chip" data-count="8">Next 8 cards</span>
                        <span class="quick-chip" data-count="16">Next 16 cards</span>
                        <span class="quick-chip" data-count="24">Next 24 cards</span>
                    </div>
                    <div class="atm-actions">
                        <button type="submit" class="btn btn-atm-primary" id="atm-submit"><i class="fa fa-print"></i> Generate A4 Card Sheet</button>
                        <button type="reset" class="btn btn-atm-light">Reset</button>
                    </div>
                    <div class="atm-note" id="atm-output-note">
                        A4 Sheet places up to 8 certificates on each page.
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $(".quick-chip").on("click", function () {
        var from = parseInt($("#from").val(), 10);
        var count = parseInt($(this).data("count"), 10);
        if (!from || from < 1) {
            $("#from").focus();
            return;
        }
        $("#to").val(from + count - 1);
    });

    $("input[name='output_mode']").on("change", function () {
        var pvcMode = $(this).val() === "pvc";
        $("#atm-submit").html(
            pvcMode
                ? '<i class="fa fa-print"></i> Generate PVC Card Pages'
                : '<i class="fa fa-print"></i> Generate A4 Card Sheet'
        );
        $("#atm-output-note").text(
            pvcMode
                ? "Creates one exact 85.60 × 53.98 mm CR80 page per certificate. Print at Actual size / 100% with the Zebra card stock selected."
                : "A4 Sheet places up to 8 certificates on each page."
        );
    });

    $("#atm-form").on("submit", function (event) {
        var from = parseInt($("#from").val(), 10);
        var to = parseInt($("#to").val(), 10);
        if (!from || !to || to < from) {
            event.preventDefault();
            alert("Please enter a valid ATM certificate range.");
        }
    });
</script>

<?php include "assets/footer.php"; ?>
