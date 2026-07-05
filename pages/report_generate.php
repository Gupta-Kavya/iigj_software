<?php include "assets/navbar.php"; ?>

<style>
    .report-page { padding-bottom: 35px; }
    .report-hero {
        border-bottom: 1px solid #ececf1;
        margin: 0 0 24px;
        padding: 0 0 20px;
    }
    .report-hero h1 {
        border: 0;
        color: #171717;
        font-size: 26px;
        font-weight: 600;
        margin: 0 0 6px;
        padding: 0;
    }
    .report-hero p {
        color: #737373;
        font-size: 14px;
        margin: 0;
        max-width: 680px;
    }
    .report-grid {
        display: grid;
        gap: 16px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .report-card {
        background: #fff;
        border: 1px solid #ececf1;
        border-radius: 10px;
        overflow: hidden;
    }
    .report-card-header {
        align-items: center;
        border-bottom: 1px solid #ececf1;
        display: flex;
        gap: 14px;
        padding: 18px;
    }
    .report-icon {
        align-items: center;
        background: #f7f7f8;
        border-radius: 8px;
        color: #404040;
        display: flex;
        font-size: 20px;
        height: 44px;
        justify-content: center;
        width: 44px;
    }
    .report-card h3 {
        color: #171717;
        font-size: 16px;
        font-weight: 600;
        margin: 0 0 3px;
    }
    .report-card .subtitle {
        color: #737373;
        font-size: 13px;
        margin: 0;
    }
    .report-card-body { padding: 18px; }
    .report-field { margin-bottom: 15px; }
    .report-field label {
        color: #404040;
        display: block;
        font-size: 13px;
        font-weight: 500;
        margin-bottom: 7px;
    }
    .report-input {
        border: 1px solid #e5e5e5;
        border-radius: 8px;
        box-shadow: none;
        font-size: 14px;
        height: 44px;
        padding: 10px 12px;
        width: 100%;
    }
    .range-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: 1fr 1fr;
    }
    .output-mode-grid {
        display: grid;
        gap: 10px;
        grid-template-columns: 1fr 1fr;
        margin-bottom: 16px;
    }
    .output-mode {
        border: 1px solid #e5e5e5;
        border-radius: 10px;
        cursor: pointer;
        display: block;
        margin: 0;
        padding: 12px;
        transition: border-color .15s ease, background .15s ease;
    }
    .output-mode:hover { background: #fafafa; }
    .output-mode:has(input:checked) {
        background: #f5f7ff;
        border-color: #171717;
    }
    .output-mode input { margin-right: 7px; }
    .output-mode strong {
        color: #171717;
        font-size: 13px;
        font-weight: 600;
    }
    .output-mode small {
        color: #737373;
        display: block;
        font-size: 12px;
        font-weight: 400;
        line-height: 1.4;
        margin: 5px 0 0 22px;
    }
    .report-actions {
        align-items: center;
        display: flex;
        gap: 10px;
        margin-top: 16px;
    }
    .btn-report-primary {
        background: #171717;
        border: 0;
        border-radius: 8px;
        color: #fff;
        font-weight: 500;
        padding: 11px 18px;
    }
    .btn-report-primary:hover,
    .btn-report-primary:focus {
        background: #404040;
        color: #fff;
    }
    .btn-report-light {
        background: #fff;
        border: 1px solid #e5e5e5;
        border-radius: 8px;
        color: #404040;
        font-weight: 500;
        padding: 10px 16px;
    }
    .report-note {
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
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 8px;
    }
    .quick-chip {
        background: #f7f7f8;
        border: 1px solid #ececf1;
        border-radius: 999px;
        color: #404040;
        cursor: pointer;
        font-size: 12px;
        font-weight: 500;
        padding: 6px 10px;
    }
    .quick-chip:hover { background: #dbeafe; }
    .status-box {
        align-items: center;
        background: #fff7ed;
        border: 1px solid #fed7aa;
        border-radius: 14px;
        color: #9a3412;
        display: none;
        gap: 10px;
        margin: 18px 0;
        padding: 12px 14px;
    }
    .status-box img { height: 28px; width: 28px; }
    @media (max-width: 992px) {
        .report-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 520px) {
        .range-grid,
        .report-actions { display: block; }
        .report-actions .btn { margin-bottom: 8px; width: 100%; }
    }
</style>

<div id="page-wrapper">
    <div class="container-fluid report-page">
        <div class="report-hero">
            <h1><i class="fa fa-file-pdf-o fa-fw"></i> Generate Reports</h1>
            <p>Create single A4, postcard, or print-ready ATM card certificates. Choose the format, enter certificate number/range, and generate the print page.</p>
        </div>

        <div class="report-grid">
            <div class="report-card">
                <div class="report-card-header">
                    <div class="report-icon"><i class="fa fa-file-pdf-o"></i></div>
                    <div>
                        <h3>A4 Certificate</h3>
                        <p class="subtitle">Generate one full-size certificate PDF.</p>
                    </div>
                </div>
                <div class="report-card-body">
                    <form id="a4-form" method="POST" action="repo-backend-a4.php" target="_blank">
                        <div class="report-field">
                            <label for="a4-id">Certificate number</label>
                            <input type="number" name="a4-id" id="a4-id" min="1" class="report-input" placeholder="Example: 1024" required>
                        </div>
                        <div class="report-actions">
                            <button type="submit" class="btn btn-report-primary"><i class="fa fa-print"></i> Open A4 Print Page</button>
                            <button type="reset" class="btn btn-report-light">Reset</button>
                        </div>
                        <div class="report-note">Opens a full A4 landscape HTML certificate. Use the Print button there and choose landscape / actual size if your printer dialog asks.</div>
                    </form>
                </div>
            </div>

            <div class="report-card">
                <div class="report-card-header">
                    <div class="report-icon"><i class="fa fa-magic"></i></div>
                    <div>
                        <h3>Auto By Report Type</h3>
                        <p class="subtitle">Open A4, ATM Card, or Postcard based on feeding report type.</p>
                    </div>
                </div>
                <div class="report-card-body">
                    <form id="auto-report-form" method="GET" action="report-open-by-certificate.php" target="_blank">
                        <div class="report-field">
                            <label for="auto-certi-no">Certificate number</label>
                            <input type="number" name="certi_no" id="auto-certi-no" min="1" class="report-input" placeholder="Example: 1024" required>
                        </div>
                        <div class="report-actions">
                            <button type="submit" class="btn btn-report-primary"><i class="fa fa-print"></i> Open Saved Format</button>
                            <button type="reset" class="btn btn-report-light">Reset</button>
                        </div>
                        <div class="report-note">Uses the report format selected in Colour Stone Report Types.</div>
                    </form>
                </div>
            </div>

            <div class="report-card">
                <div class="report-card-header">
                    <div class="report-icon"><i class="fa fa-file-image-o"></i></div>
                    <div>
                        <h3>Postcard Certificate</h3>
                        <p class="subtitle">Generate one postcard-size certificate.</p>
                    </div>
                </div>
                <div class="report-card-body">
                    <form id="postcard-form" method="POST" action="repo-backend-postcard.php" target="_blank">
                        <div class="report-field">
                            <label for="postcard-id">Certificate number</label>
                            <input type="number" name="postcard-id" id="postcard-id" min="1" class="report-input" placeholder="Example: 1024" required>
                        </div>
                        <div class="report-actions">
                            <button type="submit" class="btn btn-report-primary"><i class="fa fa-print"></i> Open Postcard Print Page</button>
                            <button type="reset" class="btn btn-report-light">Reset</button>
                        </div>
                        <div class="report-note">Opens the saved postcard layout: portrait 100mm x 150mm or landscape 150mm x 100mm. Use actual size / 100% in the print dialog.</div>
                    </form>
                </div>
            </div>

            <div class="report-card">
                <div class="report-card-header">
                    <div class="report-icon"><i class="fa fa-id-card-o"></i></div>
                    <div>
                        <h3>ATM Card Certificates</h3>
                        <p class="subtitle">Generate card-size certificates in a print-aligned sheet.</p>
                    </div>
                </div>
                <div class="report-card-body">
                    <form id="atm-form" method="POST" action="repo-initiator-atm.php" target="_blank">
                        <div class="report-field">
                            <label>Print format</label>
                            <div class="output-mode-grid">
                                <label class="output-mode">
                                    <input type="radio" name="output_mode" value="sheet" checked>
                                    <strong>A4 Sheet</strong>
                                    <small>Up to 8 cards arranged on each A4 page.</small>
                                </label>
                                <label class="output-mode">
                                    <input type="radio" name="output_mode" value="pvc">
                                    <strong>PVC Card Pages</strong>
                                    <small>Exact CR80 size, one print page per certificate.</small>
                                </label>
                            </div>
                        </div>
                        <div class="range-grid">
                            <div class="report-field">
                                <label for="from">From certificate</label>
                                <input type="number" name="from" id="from" min="1" class="report-input" placeholder="Start no." required>
                            </div>
                            <div class="report-field">
                                <label for="to">To certificate</label>
                                <input type="number" name="to" id="to" min="1" class="report-input" placeholder="End no." required>
                            </div>
                        </div>
                        <div class="quick-row">
                            <span class="quick-chip" data-count="8">Next 8 cards</span>
                            <span class="quick-chip" data-count="16">Next 16 cards</span>
                            <span class="quick-chip" data-count="24">Next 24 cards</span>
                        </div>
                        <div class="report-actions">
                            <button type="submit" class="btn btn-report-primary" id="atm-submit"><i class="fa fa-print"></i> Generate A4 Card Sheet</button>
                            <button type="reset" class="btn btn-report-light">Reset</button>
                        </div>
                        <div class="report-note" id="atm-output-note">A4 Sheet uses your saved card layout and places up to 8 certificates on each page.</div>
                    </form>
                </div>
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
                ? "Creates an exact 85.60 × 53.98 mm CR80 page for each certificate. In the Zebra print dialog choose the matching card stock, Actual size / 100%, and no scaling."
                : "A4 Sheet uses your saved card layout and places up to 8 certificates on each page."
        );
    });

    $("#a4-form").on("submit", function (event) {
        var certiNo = parseInt($("#a4-id").val(), 10);
        if (!certiNo || certiNo < 1) {
            event.preventDefault();
            alert("Please enter a valid A4 certificate number.");
        }
    });

    $("#auto-report-form").on("submit", function (event) {
        var certiNo = parseInt($("#auto-certi-no").val(), 10);
        if (!certiNo || certiNo < 1) {
            event.preventDefault();
            alert("Please enter a valid certificate number.");
        }
    });

    $("#postcard-form").on("submit", function (event) {
        var certiNo = parseInt($("#postcard-id").val(), 10);
        if (!certiNo || certiNo < 1) {
            event.preventDefault();
            alert("Please enter a valid Postcard certificate number.");
        }
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
