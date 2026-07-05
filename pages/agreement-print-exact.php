<?php
require_once 'auth.php';
if (!defined('AGREEMENT_PRINT_EXACT_LIBRARY')) {
    auth_require_login();
}
require_once 'db_connect.php';
require_once 'agreement_helper.php';

function agreement_exact_amount_words($amount)
{
    $amount = (int) round((float) $amount);
    if ($amount <= 0) {
        return 'Zero Only';
    }
    if (class_exists('NumberFormatter')) {
        $formatter = new NumberFormatter('en_IN', NumberFormatter::SPELLOUT);
        $words = preg_replace('/\s+/', ' ', str_replace('-', ' ', $formatter->format($amount)));
        return ucwords(trim($words)) . ' Only';
    }
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $underHundred = function ($number) use ($ones, $tens) {
        if ($number < 20)
            return $ones[$number];
        return trim($tens[(int) ($number / 10)] . ' ' . $ones[$number % 10]);
    };
    $underThousand = function ($number) use ($ones, $underHundred) {
        $hundreds = (int) ($number / 100);
        $rest = $number % 100;
        return trim(($hundreds ? $ones[$hundreds] . ' Hundred ' : '') . ($rest ? $underHundred($rest) : ''));
    };
    $parts = [];
    foreach ([10000000 => 'Crore', 100000 => 'Lakh', 1000 => 'Thousand'] as $value => $label) {
        if ($amount >= $value) {
            $parts[] = $underThousand((int) ($amount / $value)) . ' ' . $label;
            $amount %= $value;
        }
    }
    if ($amount > 0)
        $parts[] = $underThousand($amount);
    return trim(implode(' ', $parts)) . ' Only';
}

function agreement_exact_asset($file, $forPdf = false)
{
    $path = __DIR__ . '/assets/' . $file;
    if (!is_file($path))
        return '';
    return $forPdf ? str_replace('\\', '/', $path) : 'assets/' . rawurlencode($file);
}

function agreement_exact_mpdf_temp_dir()
{
    $dir = __DIR__ . '/assets/vendor/mpdf/mpdf/tmp';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    return $dir;
}

function agreement_exact_no($agreement)
{
    return 'IIGJRL C/' . str_pad((string) (int) ($agreement['agreement_no'] ?? 0), 5, '0', STR_PAD_LEFT);
}

function agreement_exact_date($value)
{
    if (!$value || $value === '0000-00-00')
        return '';
    $time = strtotime((string) $value);
    return $time ? date('d.m.Y', $time) : (string) $value;
}

function agreement_wrap_text($value, $limit = 28)
{
    $lines = preg_split('/\R/', (string) $value);
    $wrapped = [];
    foreach ($lines as $line) {
        $wrapped[] = wordwrap(trim($line), $limit, "\n", true);
    }
    return nl2br(agreement_h(implode("\n", $wrapped)));
}

function agreement_exact_load($conn, $id, $userId = 0, $allowAnyUser = false)
{
    if ($id <= 0 || !agreement_table_ready($conn)) {
        return null;
    }

    $sql = "SELECT a.*, u.full_name, u.company_name, u.email AS user_email, u.phone AS user_phone, u.gst_number AS user_gst
        FROM sm_stone_agreements a
        LEFT JOIN sm_users u ON u.id = a.user_id
        WHERE a.id = ?";
    $types = 'i';
    $params = [$id];
    if (!$allowAnyUser) {
        $sql .= ' AND ' . user_branch_scope_sql($conn, $userId, 'a.user_id');
    }
    $sql .= ' LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $refs = [$types];
    foreach ($params as $key => &$value) {
        $refs[] = &$value;
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $agreement = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$agreement) {
        return null;
    }
    $agreement['_items'] = agreement_get_items($conn, (int) $agreement['id'], $agreement);
    return $agreement;
}

function agreement_exact_html($agreement, $items, $forPdf = false, $hideActions = false)
{
    $leftLogo = agreement_exact_asset('agreement-gjepc.svg', $forPdf);
    $rightLogo = agreement_exact_asset('agreement-iigj.png', $forPdf);
    $dateText = agreement_exact_date($agreement['agreement_date']);
    $timeText = trim((string) ($agreement['agreement_time'] ?? ''));
    $deliveryText = trim(agreement_exact_date($agreement['delivery_date']) . ($agreement['delivery_time'] ? ' - ' . $agreement['delivery_time'] : ''));
    $totalCharges = (float) ($agreement['testing_charges'] ?? 0);
    $amountWords = 'Rs. ' . agreement_exact_amount_words($totalCharges);
    $signatureImage = '';
    if (($agreement['signature_mode'] ?? '') === 'esign' && preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=\r\n]+$/', (string) ($agreement['customer_signature'] ?? ''))) {
        $signatureImage = (string) $agreement['customer_signature'];
    }
    $bodyClass = trim(($forPdf ? 'pdf ' : '') . ($hideActions ? 'send-copy ' : '') . (count($items) > 7 ? 'long' : ''));
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="utf-8">
        <title>Agreement <?php echo agreement_h(agreement_exact_no($agreement)); ?></title>
        <?php if (!$forPdf && !$hideActions): ?>
            <link href="../css/app-toast.css" rel="stylesheet">
            <script src="../js/app-toast.js"></script>
        <?php endif; ?>
        <style>
            <?php if ($forPdf): ?>
                body {
                    color: #000;
                    font-family: Arial, Helvetica, sans-serif;
                    font-size: 11px;
                    line-height: 1.18;
                    margin: 0;
                    padding: 0
                }

                .actions {
                    display: none
                }

                .sheet {
                    margin: 0;
                    padding: 0
                }

                .copy {
                    text-align: right;
                    font-size: 13px;
                    margin-bottom: 3px
                }

                table {
                    border-collapse: collapse
                }

                .head-table {
                    width: 100%
                }

                .head-table td {
                    vertical-align: middle
                }

                .logo-left {
                    width: 165px
                }

                .logo-right {
                    width: 175px;
                    text-align: right
                }

                .logo-left img {
                    height: 42px
                }

                .logo-right img {
                    height: 52px
                }

                .title {
                    text-align: center
                }

                .title h1 {
                    font-family: Georgia, "Times New Roman", serif;
                    font-size: 20px;
                    line-height: 1;
                    margin: 0;
                    text-decoration: underline
                }

                .addr {
                    text-align: center;
                    font-size: 12.5px;
                    line-height: 1.25;
                    margin-top: 5px
                }

                .cin {
                    text-align: center;
                    font-family: Georgia, "Times New Roman", serif;
                    font-size: 14px;
                    margin-top: 6px
                }

                .rule {
                    border-top: 1.5px solid #000;
                    margin-top: 4px
                }

                .meta {
                    font-size: 13px;
                    margin-top: 5px;
                    width: 100%;
                    
                }

                .meta td {
                    padding: 2px 2px
                }

                .meta-label {
                    font-family: Georgia, "Times New Roman", serif;
                    white-space: nowrap
                }

                .meta-value {
                    font-weight: 700;
                    white-space: nowrap
                }

                .right {
                    text-align: right
                }

                .intro {
                    font-size: 12px;
                    line-height: 1.38;
                    margin: 18px 12px 6px
                }

                .party {
                    font-size: 12px;
                    margin-left: 10px;
                    table-layout: fixed;
                    width: 95%
                }

                .party td {
                    padding: 2px 4px;
                    vertical-align: top
                }

                .party .label {
                    width: 122px
                }

                .party .left-value {
                    width: 275px
                }

                .party .right-label {
                    width: 125px
                }

                .party .right-value {
                    width: 185px
                }

                .party .value {
                    font-weight: 700
                }

                .party .boxlabel {
                    font-family: Georgia, "Times New Roman", serif;
                    font-size: 13px
                }

                .checkbox {
                    border: 1px solid #000;
                    color: #122cff;
                    font-size: 14px;
                    font-weight: 700;
                    padding: 1px 7px
                }

                .check-table {
                    width: auto
                }

                .check-table td {
                    padding: 0 7px 0 0;
                    vertical-align: middle
                }

                .mou-label {
                    line-height: 1.05;
                    vertical-align: middle
                }

                .items {
                    margin-top: 10px;
                    width: 100%
                }

                .items th {
                    border-bottom: 1.2px solid #000;
                    border-top: 1.2px solid #000;
                    font-size: 10.5px;
                    font-weight: 400;
                    padding: 4px 2px;
                    text-align: center
                }

                .items td {
                    border-bottom: 1.2px solid #000;
                    border-left: 0.5px solid #000;
                    font-size: 8.5px;
                    padding: 3px 2px;
                    vertical-align: top
                }

                .items td:first-child {
                    border-left: 0;
                    text-align: center;
                    font-weight: 700;
                    width: 30px
                }

                .items .ref {
                    width: 72px
                }

                .items .category {
                    width: 118px
                }

                .items .particulars {
                    width: 76px
                }

                .items .colour {
                    width: 48px
                }

                .items .weight {
                    width: 52px
                }

                .items .diamond {
                    width: 52px
                }

                .items .beads {
                    width: 48px
                }

                .items .pcs {
                    width: 28px;
                    text-align: center
                }

                .items .card {
                    width: 30px;
                    text-align: center;
                    font-size: 11px
                }

                .items .amount {
                    width: 58px;
                    font-size: 10px;
                    font-weight: 700
                }

                .items .discount {
                    width: 54px;
                    font-size: 9px
                }

                .bottom {
                    margin-top: 18px
                }

                .bottom-rule {
                    border-top: 1.5px solid #000;
                    margin-bottom: 6px
                }

                .payment-title,
                .summary-table {
                    width: 100%
                }

                .payment-title td,
                .summary-table td {
                    font-size: 12px;
                    padding: 0 3px 4px;
                    vertical-align: bottom
                }

                .payment-title .underline {
                    text-decoration: underline
                }

                .payment-title .total-label {
                    font-weight: 700
                }

                .payment-title .amount {
                    font-size: 14px;
                    font-weight: 700
                }

                .summary-table {
                    border-collapse: collapse;
                    margin-bottom: 5px;
                    table-layout: fixed
                }

                .summary-table td {
                    border: 0.8px solid #000;
                    line-height: 1.2;
                    padding: 3px 4px;
                    vertical-align: middle
                }

                .summary-table .payment-heading {
                    font-weight: 700;
                    text-decoration: underline;
                    width: 18%
                }

                .summary-table .stone-label {
                    font-weight: 700;
                    width: 22%
                }

                .summary-table .stone-value {
                    font-weight: 700;
                    text-align: center;
                    width: 8%
                }

                .summary-table .charges-label {
                    text-align: left;
                    width: 34%;
                    font-size: 11px;
                }

                .summary-table .charges-value {
                    font-size: 13px;
                    font-weight: 700;
                    text-align: right;
                    width: 18%
                }

                .summary-table .words-label {
                    font-weight: 700;
                    text-align: left
                }

                .summary-table .words-value {
                    font-weight: 700;
                    text-align: left
                }

                .pay-layout {
                    width: 100%
                }

                .pay-layout td {
                    vertical-align: top
                }

                .pay-left {
                    width: 100%
                }

                .pay-right {
                    font-size: 12px;
                    font-weight: 700;
                    line-height: 1.25;
                    padding: 3px 0 0 12px
                }

                .pay-table {
                    width: 100%
                }

                .pay-table th,
                .pay-table td {
                    border: 0.8px solid #000;
                    font-size: 11px;
                    font-weight: 400;
                    padding: 2px 3px;
                    text-align: center
                }

                .pay-table td {
                    text-align: left
                }

                .cheque-invoice {
                    margin-top: 5px;
                    width: 100%
                }

                .cheque-invoice td {
                    border: 0.8px solid #000;
                    font-size: 12px;
                    padding: 3px
                }

                .invoice-box {
                    border-color: #bdbdbd !important;
                    text-align: center
                }

                .office {
                    font-size: 8px;
                    font-weight: 700;
                    text-align: right
                }

                .condition {
                    text-align: center;
                    font-size: 12px;
                    margin-top: 10px
                }

                .smallbox {
                    border: 1px solid #000;
                    padding: 1px 11px
                }

                .signs {
                    margin-top: 22px;
                    width: 100%
                }

                .signs td {
                    text-align: center;
                    width: 50%
                }

                .sign-line {
                    border-top: 1.2px solid #000;
                    margin: 0 auto 7px;
                    width: 210px
                }

                .sign-label {
                    font-size: 16px;
                    font-weight: 700
                }

                .for-iigj {
                    font-size: 14px
                }

                .for-iigj strong {
                    font-size: 21px
                }

                .docno {
                    text-align: center;
                    font-size: 10px;
                    margin-top: 7px
                }

            <?php else: ?>
                @page {
                    size: A4;
                    margin: 8mm 7mm
                }

                * {
                    box-sizing: border-box
                }

                body {
                    background: #eee;
                    color: #000;
                    font-family: Arial, Helvetica, sans-serif;
                    font-size: 13px;
                    line-height: 1.22;
                    margin: 0;
                    padding: 14px
                }

                .actions {
                    margin: 0 auto 10px;
                    max-width: 794px;
                    text-align: right
                }

                .actions a,
                .actions button {
                    background: #111;
                    border: 1px solid #111;
                    border-radius: 3px;
                    color: #fff;
                    cursor: pointer;
                    font-size: 12px;
                    font-weight: 700;
                    margin-left: 6px;
                    padding: 7px 11px;
                    text-decoration: none
                }

                .sheet {
                    background: #fff;
                    display: flex;
                    flex-direction: column;
                    margin: 0 auto;
                    max-width: 794px;
                    <?php echo $forPdf ? 'padding:0;position:static;' : 'min-height:1123px;padding:6px 12px 10px;position:relative;'; ?>
                }

                .copy {
                    text-align: left;
                    font-size: 10px;
                    margin-bottom: 3px
                }

                .head-table {
                    border-collapse: collapse;
                    width: 100%
                }

                .head-table td {
                    vertical-align: middle
                }

                .logo-left {
                    width: 180px
                }

                .logo-right {
                    width: 190px;
                    text-align: right
                }

                .logo-left img {
                    max-height: 58px;
                    max-width: 160px
                }

                .logo-right img {
                    max-height: 55px;
                    max-width: 170px
                }

                .title {
                    text-align: center
                }

                .title h1 {
                    font-family: Georgia, 'Times New Roman', serif;
                    font-size: 25px;
                    line-height: 1;
                    margin: 0;
                    text-decoration: underline
                }

                .addr {
                    text-align: center;
                    font-size: 15px;
                    line-height: 1.35;
                    margin: 6px 0 0
                }

                .cin {
                    text-align: center;
                    font-family: 'Courier New', Courier, monospace;
                    font-size: 17px;
                    line-height: 1.15;
                    margin-top: 8px
                }

                .rule {
                    border-top: 1.7px solid #000;
                    margin-top: 4px
                }

                .meta {
                    border-collapse: collapse;
                    width: 100%;
                    font-size: 15px;
                    margin-top: 6px
                }

                .meta td {
                    padding: 2px 3px
                }

                .meta-label {
                    font-family: Georgia, "Times New Roman", serif;
                    white-space: nowrap
                }

                .meta-value {
                    font-weight: 700;
                    white-space: nowrap
                }

                .right {
                    text-align: right
                }

                .intro {
                    font-size: 14px;
                    line-height: 1.48;
                    margin: 22px 14px 6px
                }

                .party {
                    border-collapse: collapse;
                    font-size: 14px;
                    margin-left: 12px;
                    width: 95%
                }

                .party td {
                    padding: 2px 4px;
                    vertical-align: top
                }

                .party .label {
                    width: 132px
                }

                .party .value {
                    font-weight: 700
                }

                .party .plain {
                    font-weight: 400
                }

                .party .boxlabel {
                    font-family: Georgia, 'Times New Roman', serif;
                    font-size: 13px
                }

                .check-row {
                    white-space: nowrap
                }

                .mou-label {
                    display: inline-block;
                    line-height: 1.05;
                    text-align: left;
                    vertical-align: middle;
                    width: 46px
                }

                .checkbox {
                    border: 2px solid #000;
                    display: inline-block;
                    height: 30px;
                    line-height: 26px;
                    text-align: center;
                    vertical-align: middle;
                    width: 32px
                }

                .checkbox.checked {
                    color: #122cff;
                    font-family: DejaVu Sans, Arial, sans-serif;
                    font-size: 26px;
                    font-weight: 700
                }

                .items {
                    border-collapse: collapse;
                    margin-top: 12px;
                    width: 100%
                }

                .items th {
                    border-bottom: 2px solid #000;
                    border-top: 2px solid #000;
                    font-size: 13px;
                    font-weight: 400;
                    padding: 5px 3px;
                    text-align: center
                }

                .items td {
                    border-bottom: 2px solid #000;
                    border-left: 1px solid #000;
                    font-size: 10px;
                    height: 31px;
                    padding: 4px 3px;
                    vertical-align: top
                }

                .items td:first-child {
                    border-left: 0;
                    text-align: center;
                    font-weight: 700;
                    width: 38px
                }

                .items .ref {
                    width: 87px
                }

                .items .category {
                    width: 135px
                }

                .items .particulars {
                    width: 88px
                }

                .items .colour {
                    width: 62px
                }

                .items .weight {
                    width: 64px
                }

                .items .diamond {
                    width: 62px
                }

                .items .beads {
                    width: 55px
                }

                .items .pcs {
                    width: 36px;
                    text-align: center
                }

                .items .card {
                    width: 36px;
                    text-align: center;
                    font-size: 14px
                }

                .items .amount {
                    width: 66px;
                    font-size: 12px;
                    font-weight: 700
                }

                .items .discount {
                    width: 58px;
                    font-size: 10px
                }

                .bottom {
                    margin-top: 18px;
                    position: static
                }

                .bottom-rule {
                    border-top: 2px solid #000;
                    margin-bottom: 7px
                }

                .payment-title,
                .summary-table {
                    border-collapse: collapse;
                    width: 100%
                }

                .payment-title td,
                .summary-table td {
                    font-size: 15px;
                    padding: 0 4px 5px;
                    vertical-align: bottom
                }

                .payment-title .underline {
                    text-decoration: underline
                }

                .payment-title .total-label {
                    font-weight: 700
                }

                .payment-title .amount {
                    font-size: 17px;
                    font-weight: 700
                }

                .summary-table {
                    margin-bottom: 6px;
                    table-layout: fixed
                }

                .summary-table td {
                    border: 1px solid #000;
                    font-size: 13px;
                    line-height: 1.2;
                    padding: 4px 5px;
                    vertical-align: middle
                }

                .summary-table .payment-heading {
                    font-weight: 700;
                    text-decoration: underline;
                    width: 18%
                }

                .summary-table .stone-label {
                    font-weight: 700;
                    width: 22%
                }

                .summary-table .stone-value {
                    font-weight: 700;
                    text-align: center;
                    width: 8%
                }

                .summary-table .charges-label {
                    text-align: left;
                    width: 34%;
                    font-size: 11px;
                }

                .summary-table .charges-value {
                    font-size: 15px;
                    font-weight: 700;
                    text-align: right;
                    width: 18%
                }

                .summary-table .words-label {
                    font-weight: 700;
                    text-align: left
                }

                .summary-table .words-value {
                    font-weight: 700;
                    text-align: left
                }

                .pay-layout {
                    border-collapse: collapse;
                    width: 100%
                }

                .pay-layout>tbody>tr>td {
                    vertical-align: top
                }

                .pay-left {
                    width: 100%
                }

                .pay-right {
                    font-size: 14px;
                    font-weight: 700;
                    line-height: 1.28;
                    padding: 4px 0 0 14px
                }

                .pay-table {
                    border-collapse: collapse;
                    width: 100%
                }

                .pay-table th,
                .pay-table td {
                    border: 1px solid #000;
                    font-size: 14px;
                    font-weight: 400;
                    height: 24px;
                    padding: 3px 4px;
                    text-align: center
                }

                .pay-table td {
                    text-align: left
                }

                .cheque-invoice {
                    border-collapse: separate;
                    border-spacing: 0 6px;
                    margin-top: 0;
                    width: 100%
                }

                .cheque-invoice td {
                    border: 1px solid #000;
                    font-size: 14px;
                    height: 28px;
                    padding: 4px
                }

                .invoice-box {
                    border-color: #bdbdbd !important;
                    text-align: center
                }

                .office {
                    font-size: 9px;
                    font-weight: 700;
                    text-align: right
                }

                .condition {
                    text-align: center;
                    font-size: 14px;
                    margin-top: 12px
                }

                .smallbox {
                    border: 2px solid #000;
                    display: inline-block;
                    height: 24px;
                    margin: 0 8px;
                    vertical-align: middle;
                    width: 32px
                }

                .signs {
                    border-collapse: collapse;
                    margin-top: 26px;
                    width: 100%
                }

                .signs td {
                    font-size: 14px;
                    text-align: center;
                    vertical-align: bottom;
                    width: 50%
                }

                .sign-line {
                    border-top: 1.5px solid #000;
                    margin: 0 auto 8px;
                    width: 230px
                }

                .sign-label {
                    font-size: 18px;
                    font-weight: 700
                }

                .for-iigj {
                    font-size: 16px
                }

                .for-iigj strong {
                    font-size: 19px
                }

                .docno {
                    text-align: center;
                    font-size: 12px;
                    margin-top: 8px
                }

                .no-break {
                    page-break-inside: avoid
                }

                body.pdf {
                    background: #fff;
                    font-size: 12.5px;
                    padding: 0
                }

                .pdf .actions {
                    display: none
                }

                .pdf .sheet {
                    max-width: none;
                    min-height: 0;
                    padding: 0;
                    position: static
                }

                .long .sheet {
                    display: block;
                    min-height: 0;
                    position: static
                }

                .pdf .copy {
                    font-size: 14px
                }

                .pdf .cin {
                    font-size: 16px
                }

                .pdf .bottom,
                .long .bottom {
                    bottom: auto;
                    left: auto;
                    margin-top: 18px;
                    position: static;
                    right: auto
                }

                .pdf .items,
                .long .items {
                    page-break-inside: auto
                }

                .pdf .items tr,
                .long .items tr {
                    page-break-inside: avoid;
                    page-break-after: auto
                }

                .pdf .no-break,
                .long .no-break {
                    page-break-inside: avoid
                }

                <?php if ($forPdf): ?>
                    body {
                        background: #fff !important;
                        font-size: 12.5px !important;
                        padding: 0 !important
                    }

                    .actions {
                        display: none !important
                    }

                    .sheet {
                        display: block !important;
                        max-width: none !important;
                        min-height: 0 !important;
                        height: auto !important;
                        padding: 0 !important;
                        position: static !important
                    }

                    .bottom {
                        bottom: auto !important;
                        left: auto !important;
                        margin-top: 18px !important;
                        position: static !important;
                        right: auto !important
                    }

                    .copy {
                        font-size: 14px !important
                    }

                    .cin {
                        font-size: 16px !important
                    }

                    .items {
                        page-break-inside: auto !important
                    }

                    .items tr {
                        page-break-inside: avoid !important;
                        page-break-after: auto !important
                    }

                    .no-break {
                        page-break-inside: avoid !important
                    }

                <?php else: ?>
                    @media print {
                        body {
                            background: #fff;
                            padding: 0
                        }

                        .actions {
                            display: none
                        }

                        .sheet {
                            display: flex;
                            flex-direction: column;
                            max-width: none;
                            min-height: 280mm;
                            padding: 0;
                            box-shadow: none
                        }

                        .bottom {
                            margin-top: auto;
                            position: static
                        }

                        .payment-title td,
                        .summary-table td {
                            font-size: 13px;
                            padding-bottom: 3px
                        }

                        .payment-title .amount,
                        .summary-table .charges-value {
                            font-size: 15px
                        }

                        .summary-table td {
                            line-height: 1.12;
                            padding: 3px 4px
                        }

                        .pay-table th,
                        .pay-table td {
                            font-size: 11px;
                            height: 21px;
                            line-height: 1.05;
                            padding: 2px 2px;
                            overflow-wrap: anywhere;
                            word-break: break-word
                        }

                        .cheque-invoice td {
                            font-size: 12px;
                            height: 24px;
                            padding: 3px
                        }

                        .pay-right {
                            font-size: 12px;
                            line-height: 1.22;
                            padding-left: 12px;
                            padding-top: 2px
                        }

                        .long .sheet {
                            display: block;
                            min-height: 0;
                            position: static
                        }

                        .long .bottom {
                            bottom: auto;
                            left: auto;
                            position: static;
                            right: auto
                        }
                    }

                <?php endif; ?>
                <?php if (!$forPdf): ?>
                    .party {
                        margin-left: 12px;
                        table-layout: fixed;
                        width: 93%
                    }

                    .party .label {
                        width: 138px
                    }

                    .party .left-value {
                        width: 305px
                    }

                    .party .right-label {
                        width: 128px
                    }

                    .party .right-value {
                        width: 190px
                    }

                    .party td {
                        height: 24px
                    }

                    .party .address-cell {
                        line-height: 1.35
                    }

                    .party .check-row {
                        padding-left: 0
                    }

                <?php endif; ?>
            <?php endif; ?>
            .items,
            .payment-title,
            .summary-table,
            .pay-table,
            .cheque-invoice {
                table-layout: fixed
            }

            .items {
                page-break-inside: auto;
                table-layout: fixed
            }

            .items tr {
                page-break-after: auto;
                page-break-inside: avoid
            }

            .items td:first-child {
                width: 4%
            }

            .items .ref {
                width: 14%
            }

            .items .category {
                width: 13%
            }

            .items .particulars {
                width: 15%
            }

            .items .colour {
                width: 7%
            }

            .items .weight,
            .items .diamond {
                width: 7%
            }

            .items .beads {
                width: 6%
            }

            .items .pcs {
                width: 4%
            }

            .items .card {
                width: 5%
            }

            .items .topup {
                width: 5%
            }

            .items .discount {
                width: 7%
            }

            .items .amount {
                width: 8%
            }

            .items .ref,
            .items .category,
            .items .particulars {
                font-size: 9px;
                line-height: 1.18
            }

            .bottom {
                clear: both;
                page-break-inside: avoid
            }

            body:not(.pdf):not(.long) .bottom {
                margin-top: auto;
                position: static
            }

            .send-copy .actions {
                display: none
            }

            .send-copy {
                background: #fff;
                padding: 0
            }

            .pdf .bottom,
            .long .bottom {
                bottom: auto;
                left: auto;
                margin-top: 18px;
                position: static;
                right: auto
            }

            .meta {
                border-collapse: collapse;
                border-top: 2px solid #000;
                margin: 6px auto 0;
                table-layout: fixed;
                width: calc(100% - 28px)
            }

            .meta td {
                font-size: 16px;
                line-height: 1.25;
                vertical-align: baseline;
                white-space: nowrap
            }

            .meta-agreement-label {
                text-align: left;
                width: 15%
            }

            .meta-agreement-value {
                font-weight: 700;
                text-align: left;
                overflow: hidden;
                text-overflow: clip;
                width: 45%
            }

            .meta-date-label,
            .meta-time-label {
                text-align: left;
                width: 7%
            }

            .meta-date-value {
                font-weight: 700;
                text-align: left;
                width: 13%
            }

            .meta-time-value {
                font-weight: 700;
                text-align: left;
                width: 13%
            }

            .date-label {
                font-family: Georgia, "Times New Roman", serif;
                font-size: 17px
            }

            .party {
                border-collapse: collapse;
                font-size: 14px;
                margin: 8px 14px 0;
                table-layout: fixed;
                width: calc(100% - 28px)
            }

            .party td {
                line-height: 1.22;
                padding: 3px 0;
                vertical-align: top
            }

            .party .label {
                font-weight: 400
            }

            .party .left-value {
                padding-left: 4px
            }

            .party .right-label {
                padding-left: 0;
                text-align: left;
                width: 108px
            }

            .party .right-value {
                text-align: left;
                width: 155px
            }

            .party .right-label,
            .party .right-value {
                font-size: 14px;
                white-space: nowrap
            }

            .wrap,
            .party td,
            .items td,
            .payment-title td,
            .summary-table td,
            .pay-table th,
            .pay-table td,
            .cheque-invoice td,
            .pay-right {
                max-width: 100%;
                overflow-wrap: anywhere;
                word-break: break-word;
                white-space: normal
            }

            .party .right-label,
            .party .right-value {
                overflow-wrap: normal;
                word-break: normal;
                white-space: nowrap
            }

            .party .check-row,
            .party .check-row td,
            .party .mou-label {
                overflow-wrap: normal;
                word-break: normal;
                white-space: nowrap
            }

            .check-table {
                border-collapse: collapse;
                width: 100%
            }

            .check-table td {
                height: auto !important;
                line-height: 1;
                padding: 0 !important;
                vertical-align: middle
            }

            .check-table .first-check {
                width: 48px
            }

            .check-table .second-check {
                width: 137px
            }

            .check-text {
                display: inline-block;
                line-height: 1;
                margin-right: 8px;
                text-align: left;
                vertical-align: middle;
                width: 72px
            }

            .check-table .checkbox {
                margin: 0;
                vertical-align: middle
            }

            .status-check-cell {
                padding-left: 0 !important;
                padding-right: 0 !important
            }

            .status-check-table {
                border-collapse: collapse;
                table-layout: fixed;
                width: 100%
            }

            .status-check-table td {
                font-size: 14px;
                height: auto !important;
                line-height: 1.1;
                overflow: hidden;
                padding: 0 4px 0 0 !important;
                vertical-align: middle;
                white-space: nowrap
            }

            .status-check-table .status-label {
                width: 108px
            }

            .status-check-table .status-box {
                width: 34px
            }

            .status-check-table .wide-label {
                width: 70px
            }

            .status-check-table .checkbox {
                height: 30px;
                line-height: 26px;
                width: 32px
            }

            .amount-words {
                display: block;
                line-height: 1.22;
                max-width: 100%;
                overflow-wrap: anywhere;
                white-space: normal
            }

            .signs {
                table-layout: fixed;
                width: 100%
            }

            .signs td {
                vertical-align: bottom;
                width: 50%
            }

            .sign-left {
                text-align: left !important
            }

            .sign-right {
                text-align: right !important
            }

            .sign-left .sign-line {
                margin-left: 0;
                margin-right: auto
            }

            .sign-right .sign-line {
                margin-left: auto;
                margin-right: 0
            }

            .signature-image {
                display: block;
                height: auto;
                max-height: 42px;
                max-width: 170px;
                object-fit: contain;
                margin: 0 0 2px 30px
            }

            .title .header-title {
                font-family: Georgia, "Times New Roman", serif;
                font-size: 25px;
                font-weight: 700;
                line-height: .95;
                margin: 0;
                text-align: center;
                text-decoration: underline
            }

            .agreement-copy {
                font-size: 12px;
                letter-spacing: .2px;
                margin-bottom: 8px;
                text-align: left;
                text-transform: uppercase
            }

            .header-intro {
                font-size: 13px;
                line-height: 1.45;
                margin: 24px 18px 4px
            }

            .intro-table {
                border-collapse: collapse;
                margin: 24px 14px 0px;
                table-layout: fixed;
                width: calc(100% - 28px)
            }

            .intro-table td {
                font-size: 13px;
                line-height: 1.45;
                padding: 0;
                text-align: left;
                vertical-align: top;
                white-space: normal;
                word-break: normal
            }

            .meta,
            .intro-table,
            .party {
                box-sizing: border-box;
                margin-left: 0;
                margin-right: 0;
                width: 100%
            }

            .meta {
                margin-top: 6px
            }

            .intro-table {
                margin-top: 24px;
                margin-bottom: 0
            }

            .party {
                margin-top: 8px
            }

            .items {
                margin-left: 0;
                margin-right: 0;
                margin-top: 10px;
                width: 100%
            }
        </style>
    </head>

    <body class="<?php echo agreement_h($bodyClass); ?>">
        <?php if (!$forPdf && !$hideActions): ?>
            <div class="actions">
                <a href="agreement.php">New agreement</a>
                <button type="button" id="send_whatsapp" data-id="<?php echo (int) $agreement['id']; ?>">Send WhatsApp</button>
                <button type="button" onclick="window.print()">Print</button>
            </div>
        <?php endif; ?>
        <div class="sheet">
            <div class="agreement-copy">CUSTOMER COPY</div>
            <table class="head-table">
                <tr>
                    <td class="logo-left">
                        <?php if ($leftLogo): ?><img src="<?php echo agreement_h($leftLogo); ?>" alt="GJEPC"><?php endif; ?>
                    </td>
                    <td class="title">
                        <h1 class="header-title">Agreement Cum<br>Acknowledgement Receipt</h1>
                    </td>
                    <td class="logo-right">
                        <?php if ($rightLogo): ?><img src="<?php echo agreement_h($rightLogo); ?>"
                                alt="IIGJ"><?php endif; ?>
                    </td>
                </tr>
            </table>
            <div class="addr">SP-111, R.K. Derewala Tower, KGK Campus, Near SEZ Phase 1,Sitapura Industrial Area,
                Jaipur-302022<br>
                +91-141-2770995, 2941470, | Email: Info@iigjrlc.org&nbsp; | Web:- iigjrlc.org</div>
            <div class="cin">CIN : U73100MH2019NPL328412&nbsp; | &nbsp;GST NO: 08AHPPG9551N1JZ</div>
            <table class="meta">
                <colgroup>
                    <col style="width:15%">
                    <col style="width:45%">
                    <col style="width:7%">
                    <col style="width:13%">
                    <col style="width:7%">
                    <col style="width:13%">
                </colgroup>
                <tr>
                    <td class="meta-agreement-label date-label">Agreement No.</td>
                    <td class="meta-agreement-value"><?php echo agreement_h(agreement_exact_no($agreement)); ?></td>
                    <td class="meta-date-label date-label">Date:-</td>
                    <td class="meta-date-value"><?php echo agreement_h($dateText); ?></td>
                    <td class="meta-time-label date-label">Time:-</td>
                    <td class="meta-time-value"><?php echo agreement_h($timeText); ?></td>
                </tr>
            </table>
            <table class="intro-table">
                <tr>
                    <td>I/We hereby agree that any gem material or other goods (here in after called simple 'the goods')
                        hereafter deposited by me/us on my/our behalf with the IIGL RLC shall be deemed to have been upon
                        the acceptance of terms and conditions overleaf:</td>
                </tr>
            </table>
            <table class="party">
                <colgroup>
                    <col style="width:135px">
                    <col style="width:330px">
                    <col style="width:108px">
                    <col style="width:155px">
                </colgroup>
                <tr>
                    <td class="label">Name</td>
                    <td class="left-value value wrap"><?php echo agreement_wrap_text($agreement['customer_name'], 34); ?>
                    </td>
                    <td class="right-label">Del. Dt.&amp; Time</td>
                    <td class="right-value value wrap"><?php echo agreement_wrap_text($deliveryText, 20); ?></td>
                </tr>
                <tr>
                    <td class="label">Name of Depositor</td>
                    <td class="left-value value wrap"><?php echo agreement_wrap_text($agreement['depositor_name'], 34); ?>
                    </td>
                    <td class="right-label">ID Proof.</td>
                    <td class="right-value value wrap"><?php echo agreement_wrap_text($agreement['id_no'] ?? '', 18); ?>
                    </td>
                </tr>
                <tr>
                    <td class="label"></td>
                    <td class="left-value value wrap address-cell" rowspan="2">
                        <?php echo agreement_wrap_text($agreement['address'], 40); ?></td>
                    <td class="check-row status-check-cell" colspan="2">
                        <table class="status-check-table">
                            <colgroup>
                                <col style="width:108px">
                                <col style="width:34px">
                                <col style="width:70px">
                                <col style="width:34px">
                            </colgroup>
                            <tr>
                                <td class="boxlabel status-label">Urgent</td>
                                <td class="status-box"><span
                                        class="checkbox <?php echo ($agreement['category'] ?? '') === 'Urgent' ? 'checked' : ''; ?>"><?php echo ($agreement['category'] ?? '') === 'Urgent' ? '&#10003;' : ''; ?></span>
                                </td>
                                <td class="boxlabel status-label wide-label">Regular</td>
                                <td class="status-box"><span
                                        class="checkbox <?php echo ($agreement['category'] ?? '') !== 'Urgent' ? 'checked' : ''; ?>"><?php echo ($agreement['category'] ?? '') !== 'Urgent' ? '&#10003;' : ''; ?></span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td class="check-row status-check-cell" colspan="2">
                        <table class="status-check-table">
                            <colgroup>
                                <col style="width:108px">
                                <col style="width:34px">
                                <col style="width:70px">
                                <col style="width:34px">
                            </colgroup>
                            <tr>
                                <td class="boxlabel status-label">Member</td>
                                <td class="status-box"><span
                                        class="checkbox <?php echo $agreement['member_status'] === 'Member' ? 'checked' : ''; ?>"><?php echo $agreement['member_status'] === 'Member' ? '&#10003;' : ''; ?></span>
                                </td>
                                <td class="boxlabel status-label wide-label">MOU/CDC</td>
                                <td class="status-box"><span
                                        class="checkbox <?php echo trim((string) $agreement['mou_cdc']) !== '' ? 'checked' : ''; ?>"><?php echo trim((string) $agreement['mou_cdc']) !== '' ? '&#10003;' : ''; ?></span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td class="label">Mobile No.</td>
                    <td class="left-value wrap"><?php echo agreement_wrap_text($agreement['mobile_no'], 32); ?></td>
                    <td class="right-label"></td>
                    <td class="right-value"></td>
                </tr>
                <tr>
                    <td class="label">GST No.</td>
                    <td class="left-value wrap"><?php echo agreement_wrap_text($agreement['gst_no'], 32); ?></td>
                    <td class="right-label"></td>
                    <td class="right-value"></td>
                </tr>
            </table>
            <table class="items">
                <thead>
                    <tr>
                        <th width="5%" style="font-size: 12px;">S.No</th>
                        <th style="font-size: 12px;">Ref. No.</th>
                        <th style="font-size: 12px;">Category</th>
                        <th width="10%" style="font-size: 12px;">Particulars</th>
                        <th style="font-size: 12px;">Colour</th>
                        <th style="font-size: 12px;">Gross<br>Weight</th>
                        <th style="font-size: 12px;">Stone<br>Weight</th>
                        <th style="font-size: 12px;">Diamond<br>Weight</th>
                        <th style="font-size: 12px;">Beads<br>Length</th>
                        <th style="font-size: 12px;">Pcs</th>
                        <th style="font-size: 12px;">A4/<br>Card</th>
                        <!-- <th style="font-size: 12px;">Topup<br>(Y/N)</th> -->
                        <!-- <th style="font-size: 12px;">Discount</th> -->
                        <th style="font-size: 12px;">Estimated<br>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $index => $item): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td class="ref wrap"><?php echo agreement_wrap_text($item['ref_no'] ?? '', 22); ?></td>
                            <td class="category wrap"><?php echo agreement_wrap_text($item['category'] ?? '', 24); ?></td>
                            <td class="particulars wrap"><?php echo agreement_wrap_text($item['particulars'] ?? '', 24); ?></td>
                            <td class="colour wrap"><?php echo agreement_wrap_text($item['color'] ?? '', 12); ?></td>
                            <td class="weight wrap">
                                <?php echo agreement_wrap_text(agreement_weight_display($item['gross_wt'] ?? '', $item['gross_wt_unit'] ?? 'ct'), 12); ?>
                            </td>
                            <td class="weight wrap">
                                <?php echo agreement_wrap_text(agreement_weight_display($item['stone_wt'] ?? '', $item['stone_wt_unit'] ?? 'ct'), 12); ?>
                            </td>
                            <td class="diamond wrap">
                                <?php echo agreement_wrap_text(agreement_weight_display($item['dia_wt'] ?? '', $item['dia_wt_unit'] ?? 'ct'), 12); ?>
                            </td>
                            <td class="beads wrap"><?php echo agreement_wrap_text($item['bead_length'] ?? '', 10); ?></td>
                            <td class="pcs wrap"><?php echo agreement_wrap_text($item['pcs'] ?? '', 8); ?></td>
                            <td class="card wrap"><?php echo agreement_wrap_text(($item['a4_card'] ?? '') ?: 'A4', 6); ?></td>
                            <!-- <td class="topup wrap"><?php echo !empty($item['topup']) ? 'Yes' : 'No'; ?></td> -->
                            <!-- <td class="discount wrap"><?php
                            $discountAmount = (float) ($item['discount_amount'] ?? 0);
                            $discountPercent = (float) ($item['discount_percent'] ?? 0);
                            echo $discountAmount > 0 ? agreement_wrap_text(agreement_money($discountAmount) . ' (' . rtrim(rtrim(number_format($discountPercent, 2, '.', ''), '0'), '.') . '%)', 12) : '';
                            ?></td> -->
                            <td class="amount wrap"><?php echo agreement_wrap_text($item['amount'] ?? '', 10); ?></td>
                        </tr><?php endforeach; ?>
                </tbody>
            </table>
            <div class="bottom no-break">
                <div class="bottom-rule"></div>
                <table class="summary-table">
                    <tr>
                        <td class="payment-heading">Payment Details:-</td>
                        <td class="stone-label">Total Stone Submitted</td>
                        <td class="stone-value"><?php echo (int) $agreement['pcs_total']; ?></td>
                        <td class="charges-label">Estimated Total Testing Charges (inclusive tax)</td>
                        <td class="charges-value">Rs.&nbsp;<?php echo agreement_h(agreement_money($totalCharges)); ?></td>
                    </tr>
                    <tr>
                        <td class="words-label">Amount in Words</td>
                        <td class="words-value" colspan="4">
                            <span class="amount-words"><?php echo agreement_wrap_text($amountWords, 88); ?></span>
                        </td>
                    </tr>
                </table>
                <table class="pay-layout">
                    <tr>
                        <td class="pay-left">
                            <table class="pay-table">
                                <tr>
                                    <th>Cash</th>
                                    <th>Cheque</th>
                                    <th>NEFT/UPI</th>
                                    <th>Card</th>
                                    <th>TDS</th>
                                    <th>Due</th>
                                    <th>Refund</th>
                                </tr>
                                <tr>
                                    <td><?php echo agreement_h(agreement_money($agreement['payment_cash'])); ?></td>
                                    <td><?php echo agreement_h(agreement_money($agreement['payment_cheque'])); ?></td>
                                    <td><?php echo agreement_h(agreement_money($agreement['payment_neft'])); ?></td>
                                    <td><?php echo agreement_h(agreement_money($agreement['payment_card'])); ?></td>
                                    <td><?php echo agreement_h(agreement_money($agreement['payment_tds'])); ?></td>
                                    <td><?php echo agreement_h(agreement_money($agreement['due_amount'])); ?></td>
                                    <td><?php echo agreement_h(agreement_money($agreement['refund_amount'])); ?></td>
                                </tr>
                            </table>
                            <table class="cheque-invoice">
                                <tr>
                                    <td style="width:180px">Cheque No.</td>
                                    <td class="wrap"><?php echo agreement_wrap_text($agreement['cheque_no'], 32); ?></td>
                                </tr>
                                <tr>
                                    <td class="invoice-box" colspan="2">INVOICE NO&nbsp;&nbsp;&nbsp;&nbsp;
                                        ..............................<div class="office">For Office Use</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <div class="condition">Sample Condition&nbsp; OK <span class="smallbox"></span> Damage <span
                        class="smallbox"></span> / ......................</div>
                <table class="signs">
                    <tr>
                        <td class="sign-left">
                            <?php if ($signatureImage): ?><img class="signature-image"
                                    src="<?php echo agreement_h($signatureImage); ?>" alt="Customer signature"><?php endif; ?>
                            <div class="sign-line"></div>
                            <div class="sign-label">Sign of Depositor</div>
                        </td>
                        <td class="sign-right">
                            <div class="sign-line"></div>
                            <div class="for-iigj">For&nbsp; <strong>IIGJ RLC</strong></div>
                        </td>
                    </tr>
                </table>
                <div class="docno">(Doc.No.- IIGJLJ/7.1/F01, Issue Date- July 01, 2025, Issue No.- 01, Amendment No. 00,
                    Amendment Date-00)</div>
            </div>
        </div>
        <?php if (!$forPdf && !$hideActions): ?>
            <script>
                (function () {
                    var button = document.getElementById("send_whatsapp");
                    if (!button) return;
                    button.addEventListener("click", function () {
                        var oldText = button.textContent;
                        button.disabled = true;
                        button.textContent = "Sending...";
                        var data = new FormData();
                        data.append("id", button.getAttribute("data-id"));
                        fetch("agreement-whatsapp-send.php", { method: "POST", body: data, credentials: "same-origin" })
                            .then(function (response) { return response.json().catch(function () { return {}; }).then(function (json) { return { ok: response.ok, json: json }; }); })
                            .then(function (result) {
                                if (!result.ok || !result.json || (result.json.status !== "success" && result.json.status !== "partial")) {
                                    throw new Error(result.json && result.json.message ? result.json.message : "Unable to send WhatsApp.");
                                }
                                if (window.AppToast) {
                                    (result.json.status === "partial" ? AppToast.info : AppToast.success)(result.json.message || "Agreement sent on WhatsApp.");
                                }
                            })
                            .catch(function (error) {
                                if (window.AppToast) AppToast.error(error.message || "Unable to send WhatsApp.");
                            })
                            .finally(function () {
                                button.disabled = false;
                                button.textContent = oldText;
                            });
                    });
                })();
            </script>
        <?php endif; ?>
    </body>

    </html>
    <?php
    return ob_get_clean();
}

if (!defined('AGREEMENT_PRINT_EXACT_LIBRARY')) {
    $id = max(0, (int) ($_GET['id'] ?? 0));
    $agreement = agreement_exact_load($conn, $id, auth_current_user_id(), auth_is_super_admin());
    if (!$agreement) {
        http_response_code(404);
        die('Agreement not found.');
    }
    $items = $agreement['_items'];
    unset($agreement['_items']);
    $html = agreement_exact_html($agreement, $items, !empty($_GET['pdf']));

    if (!empty($_GET['pdf'])) {
        require_once 'assets/vendor/autoload.php';
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 7,
            'margin_right' => 7,
            'margin_top' => 8,
            'margin_bottom' => 8,
            'tempDir' => agreement_exact_mpdf_temp_dir(),
        ]);
        $mpdf->SetTitle('Agreement ' . agreement_exact_no($agreement));
        $mpdf->SetAuthor('IIGJ RLC');
        $mpdf->WriteHTML($html);
        $mpdf->Output('agreement-' . (int) $agreement['agreement_no'] . '.pdf', \Mpdf\Output\Destination::INLINE);
        exit;
    }

    echo $html;
}
?>
