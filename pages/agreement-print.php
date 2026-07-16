<?php
require __DIR__ . '/agreement-print-exact.php';
exit;

require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'agreement_helper.php';
require_once 'user_branch_helper.php';

$id = max(0, (int) ($_GET['id'] ?? 0));
$userId = auth_current_user_id();
if ($id <= 0 || !agreement_table_ready($conn)) {
    http_response_code(404);
    die('Agreement not found.');
}

$sql = "SELECT a.*, u.full_name, u.branch_location, u.email AS user_email, u.phone AS user_phone
    FROM sm_stone_agreements a
    LEFT JOIN sm_users u ON u.id = a.user_id
    WHERE a.id = ?";
$types = 'i';
$params = [$id];
if (!auth_is_super_admin()) {
    $sql .= ' AND a.user_id = ?';
    $types .= 'i';
    $params[] = $userId;
}
$sql .= ' LIMIT 1';
$stmt = $conn->prepare($sql);
$refs = [$types];
foreach ($params as $key => &$value)
    $refs[] = &$value;
call_user_func_array([$stmt, 'bind_param'], $refs);
$stmt->execute();
$agreement = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$agreement) {
    http_response_code(404);
    die('Agreement not found.');
}

$items = agreement_get_items($conn, (int) $agreement['id'], $agreement);

function agreement_print_html($agreement, $items, $forPdf = false)
{
    $branchDetails = user_branch_location_details($GLOBALS['conn'], $agreement['branch_location'] ?? '');
    $labName = trim((string) ($branchDetails['name'] ?? '')) ?: 'IIGJ RLC';
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="utf-8">
        <title>Stone Agreement <?php echo agreement_h($agreement['agreement_no']); ?></title>
        <style>
            * {
                box-sizing: border-box
            }

            body {
                background: #e5e5e5;
                color: #111;
                font-family: Arial, Helvetica, sans-serif;
                font-size: 12px;
                line-height: 1.35;
                margin: 0;
                padding: 18px
            }

            .actions {
                display: flex;
                gap: 10px;
                justify-content: flex-end;
                margin: 0 auto 12px;
                max-width: 1120px
            }

            .actions a,
            .actions button {
                background: #111;
                border: 1px solid #111;
                border-radius: 4px;
                color: #fff;
                cursor: pointer;
                font-weight: 700;
                padding: 8px 13px;
                text-decoration: none
            }

            .page {
                background: #fff;
                border: 1.5px solid #111;
                margin: auto;
                max-width: 1120px;
                min-height: 760px;
                padding: 14px
            }

            .top-table {
                border-bottom: 1.5px solid #111;
                border-collapse: collapse;
                margin-bottom: 10px;
                padding-bottom: 10px;
                width: 100%
            }

            .top-table td {
                padding: 0 0 10px;
                vertical-align: top
            }

            .brand h1 {
                font-size: 20px;
                letter-spacing: .02em;
                margin: 0 0 3px;
                text-transform: uppercase
            }

            .brand p {
                margin: 0
            }

            .title {
                text-align: right;
                width: 250px
            }

            .title h2 {
                font-size: 18px;
                margin: 0 0 7px;
                text-transform: uppercase
            }

            .meta-table {
                border-collapse: collapse;
                width: 100%
            }

            .meta-table td {
                padding: 2px 0
            }

            .meta-table td:first-child {
                font-weight: 700;
                text-align: left;
                width: 88px
            }

            .line {
                border-bottom: 1px solid #111;
                min-height: 18px;
                padding: 1px 4px
            }

            .section {
                margin-top: 10px
            }

            .box-title {
                background: #f2f2f2;
                border: 1px solid #111;
                border-bottom: 0;
                font-size: 11px;
                font-weight: 700;
                padding: 5px 7px;
                text-transform: uppercase
            }

            .detail-table,
            .detail-table table {
                border-collapse: collapse;
                width: 100%
            }

            .detail-table td {
                border: 1px solid #111;
                padding: 0;
                vertical-align: top
            }

            .detail-table table td {
                border: 0
            }

            .label {
                background: #fafafa;
                border-right: 1px solid #111 !important;
                font-weight: 700;
                padding: 5px 7px;
                width: 118px
            }

            .value {
                padding: 5px 7px
            }

            .stone-table {
                border-collapse: collapse;
                width: 100%
            }

            .stone-table th,
            .stone-table td {
                border: 1px solid #111;
                padding: 5px 6px;
                vertical-align: top
            }

            .stone-table th {
                background: #f2f2f2;
                font-size: 10px;
                text-transform: uppercase
            }

            .stone-table td {
                height: 24px
            }

            .num {
                text-align: right
            }

            .center {
                text-align: center
            }

            .summary-table {
                border-collapse: collapse;
                width: 100%
            }

            .summary-table>tbody>tr>td {
                padding: 0 10px 0 0;
                vertical-align: top
            }

            .summary-table>tbody>tr>td:last-child {
                padding-right: 0;
                width: 360px
            }

            .terms {
                border: 1px solid #111;
                padding: 8px 10px
            }

            .terms h3 {
                font-size: 11px;
                margin: 0 0 5px;
                text-transform: uppercase
            }

            .terms ol {
                margin: 0;
                padding-left: 17px
            }

            .terms li {
                margin-bottom: 3px
            }

            .pay-table {
                border-collapse: collapse;
                width: 100%
            }

            .pay-table td {
                border: 1px solid #111;
                padding: 5px 7px
            }

            .pay-table td:first-child {
                background: #fafafa;
                font-weight: 700
            }

            .sign-table {
                border-collapse: separate;
                border-spacing: 12px 0;
                margin-top: 18px;
                width: 100%
            }

            .sign-box {
                border-top: 1px solid #111;
                padding-top: 7px;
                text-align: center
            }

            .muted {
                color: #555
            }

            .print-note {
                font-size: 10px;
                margin-top: 8px;
                text-align: center
            }

            .no-break {
                page-break-inside: avoid
            }

            @media print {
                body {
                    background: #fff;
                    padding: 0
                }

                .actions {
                    display: none
                }

                .page {
                    border: 0;
                    max-width: none;
                    padding: 10mm
                }

                .top,
                .stone-table th,
                .box-title,
                .label,
                .pay-table td:first-child {
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact
                }
            }
        </style>
    </head>

    <body>
        <?php if (!$forPdf): ?>
            <div class="actions"><a href="agreement.php">New agreement</a><a
                    href="agreement-print.php?id=<?php echo (int) $agreement['id']; ?>&pdf=1">Download PDF</a><button
                    onclick="window.print()">Print</button></div><?php endif; ?>
        <div class="page">
            <table class="top-table">
                <tr>
                    <td class="brand">
                        <h1><?php echo agreement_h($labName); ?></h1>
                        <p>Stone testing receipt and customer agreement</p>
                        <p class="muted">Generated by laboratory software for submitted stone articles.</p>
                    </td>
                    <td class="title">
                        <h2>Stone Agreement</h2>
                        <table class="meta-table">
                            <tr>
                                <td>Serial No.</td>
                                <td>
                                    <div class="line"><?php echo (int) $agreement['agreement_no']; ?></div>
                                </td>
                            </tr>
                            <tr>
                                <td>Docket No.</td>
                                <td>
                                    <div class="line"><?php echo agreement_h($agreement['docket_no']); ?></div>
                                </td>
                            </tr>
                            <tr>
                                <td>Date</td>
                                <td>
                                    <div class="line">
                                        <?php echo agreement_h(agreement_date_display($agreement['agreement_date'])); ?>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>Time</td>
                                <td>
                                    <div class="line"><?php echo agreement_h($agreement['agreement_time']); ?></div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <div class="section no-break">
                <div class="box-title">Customer Details</div>
                <table class="detail-table">
                    <tr>
                        <td>
                            <table>
                                <tr>
                                    <td class="label">Customer</td>
                                    <td class="value"><?php echo agreement_h($agreement['customer_name']); ?></td>
                                </tr>
                            </table>
                        </td>
                        <td>
                            <table>
                                <tr>
                                    <td class="label">Depositor</td>
                                    <td class="value"><?php echo agreement_h($agreement['depositor_name']); ?></td>
                                </tr>
                            </table>
                        </td>
                        <td>
                            <table>
                                <tr>
                                    <td class="label">Membership</td>
                                    <td class="value"><?php echo agreement_h($agreement['member_status']); ?></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table>
                                <tr>
                                    <td class="label">MOU / CDC</td>
                                    <td class="value"><?php echo agreement_h($agreement['mou_cdc']); ?></td>
                                </tr>
                            </table>
                        </td>
                        <td>
                            <table>
                                <tr>
                                    <td class="label">Category</td>
                                    <td class="value"><?php echo agreement_h($agreement['category']); ?></td>
                                </tr>
                            </table>
                        </td>
                        <td>
                            <table>
                                <tr>
                                    <td class="label">GST No.</td>
                                    <td class="value"><?php echo agreement_h($agreement['gst_no']); ?></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table>
                                <tr>
                                    <td class="label">Mobile</td>
                                    <td class="value"><?php echo agreement_h($agreement['mobile_no']); ?></td>
                                </tr>
                            </table>
                        </td>
                        <td>
                            <table>
                                <tr>
                                    <td class="label">Email</td>
                                    <td class="value"><?php echo agreement_h($agreement['email']); ?></td>
                                </tr>
                            </table>
                        </td>
                        <td>
                            <table>
                                <tr>
                                    <td class="label">ID No.</td>
                                    <td class="value"><?php echo agreement_h($agreement['id_no']); ?></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3">
                            <table>
                                <tr>
                                    <td class="label">Address</td>
                                    <td class="value"><?php echo nl2br(agreement_h($agreement['address'])); ?></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table>
                                <tr>
                                    <td class="label">Delivery Date</td>
                                    <td class="value">
                                        <?php echo agreement_h(agreement_date_display($agreement['delivery_date'])); ?></td>
                                </tr>
                            </table>
                        </td>
                        <td>
                            <table>
                                <tr>
                                    <td class="label">Delivery Time</td>
                                    <td class="value"><?php echo agreement_h($agreement['delivery_time']); ?></td>
                                </tr>
                            </table>
                        </td>
                        <td>
                            <table>
                                <tr>
                                    <td class="label">Delivered</td>
                                    <td class="value"><?php echo !empty($agreement['delivered']) ? 'Yes' : 'No'; ?></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="section">
                <table class="stone-table">
                    <thead>
                        <tr>
                            <th style="width:34px">SNO</th>
                            <th>Ref No</th>
                            <th>Category</th>
                            <th>Particulars</th>
                            <th>Color</th>
                            <th>Gross Wt.</th>
                            <th>Stone Wt.</th>
                            <th>Dia Wt.</th>
                            <th>Pcs</th>
                            <th>Rate</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $index => $item): ?>
                            <tr>
                                <td class="center"><?php echo $index + 1; ?></td>
                                <td><?php echo agreement_h($item['ref_no'] ?? ''); ?></td>
                                <td><?php echo agreement_h(agreement_rate_category_display($conn, $item['category'] ?? '')); ?></td>
                                <td><?php echo agreement_h($item['particulars'] ?? ''); ?></td>
                                <td><?php echo agreement_h($item['color'] ?? ''); ?></td>
                                <td class="num"><?php echo agreement_h(agreement_weight_display($item['gross_wt'] ?? '', $item['gross_wt_unit'] ?? 'ct')); ?></td>
                                <td class="num"><?php echo agreement_h(agreement_weight_display($item['stone_wt'] ?? '', $item['stone_wt_unit'] ?? 'ct')); ?></td>
                                <td class="num"><?php echo agreement_h(agreement_weight_display($item['dia_wt'] ?? '', $item['dia_wt_unit'] ?? 'ct')); ?></td>
                                <td class="center"><?php echo agreement_h($item['pcs'] ?? ''); ?></td>
                                <td class="num"><?php echo agreement_h($item['rate'] ?? ''); ?></td>
                                <td class="num"><?php echo agreement_h($item['amount'] ?? ''); ?></td>
                            </tr><?php endforeach; ?>
                        <?php for ($i = count($items); $i < 6; $i++): ?>
                            <tr>
                                <td class="center"><?php echo $i + 1; ?></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr><?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <table class="section summary-table no-break">
                <tr>
                    <td>
                        <div class="terms">
                            <h3>Agreement Declaration</h3>
                            <ol>
                                <li>The customer/depositor confirms that the stone articles listed above have been submitted
                                    for laboratory testing.</li>
                                <li>Testing results are based on the article condition and information available at the time
                                    of examination.</li>
                                <li>The depositor is responsible for collecting the article against this agreement and valid
                                    identification.</li>
                                <li>Any dispute must be raised with the laboratory before delivery acknowledgement.</li>
                            </ol>
                            <?php if (!empty($agreement['remarks'])): ?>
                                <p><strong>Remarks:</strong> <?php echo agreement_h($agreement['remarks']); ?></p>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <table class="pay-table">
                            <tr>
                                <td>Total PCS</td>
                                <td class="num"><?php echo (int) $agreement['pcs_total']; ?></td>
                            </tr>
                            <tr>
                                <td>Total Testing Charges</td>
                                <td class="num"><?php echo agreement_h(agreement_money($agreement['testing_charges'])); ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Cash</td>
                                <td class="num"><?php echo agreement_h(agreement_money($agreement['payment_cash'])); ?></td>
                            </tr>
                            <tr>
                                <td>Cheque</td>
                                <td class="num"><?php echo agreement_h(agreement_money($agreement['payment_cheque'])); ?>
                                </td>
                            </tr>
                            <tr>
                                <td>NEFT</td>
                                <td class="num"><?php echo agreement_h(agreement_money($agreement['payment_neft'])); ?></td>
                            </tr>
                            <tr>
                                <td>Card / TDS</td>
                                <td class="num">
                                    <?php echo agreement_h(agreement_money((float) $agreement['payment_card'] + (float) $agreement['payment_tds'])); ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Cheque No.</td>
                                <td><?php echo agreement_h($agreement['cheque_no']); ?></td>
                            </tr>
                            <tr>
                                <td>Due / Refund</td>
                                <td class="num"><?php echo agreement_h(agreement_money($agreement['due_amount'])); ?> /
                                    <?php echo agreement_h(agreement_money($agreement['refund_amount'])); ?></td>
                            </tr>
                            <tr>
                                <td>Prepared By</td>
                                <td><?php echo agreement_h($agreement['prepared_by']); ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <table class="sign-table no-break">
                <tr>
                    <td class="sign-box">Customer / Depositor Signature</td>
                    <td class="sign-box">Lab Receiver Signature</td>
                    <td class="sign-box">Authorised Signatory</td>
                </tr>
            </table>
            <div class="print-note">This agreement must be signed before certificate generation or delivery of submitted
                articles.</div>
        </div>
    </body>

    </html>
    <?php
    return ob_get_clean();
}

$html = agreement_print_html($agreement, $items, !empty($_GET['pdf']));

if (!empty($_GET['pdf'])) {
    require_once 'assets/vendor/autoload.php';
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L',
        'margin_left' => 8,
        'margin_right' => 8,
        'margin_top' => 8,
        'margin_bottom' => 8,
        'tempDir' => __DIR__ . '/assets/vendor/mpdf/mpdf/tmp',
    ]);
    $mpdf->SetTitle('Stone Agreement ' . $agreement['agreement_no']);
    $mpdf->SetAuthor('IIGJ');
    $mpdf->WriteHTML($html);
    $mpdf->Output('stone-agreement-' . (int) $agreement['agreement_no'] . '.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

echo $html;
?>
