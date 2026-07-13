<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'agreement_helper.php';
date_default_timezone_set('Asia/Kolkata');

agreement_table_ready($conn);

function agreement_report_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function agreement_report_date($value)
{
    if (!$value || $value === '0000-00-00') {
        return '';
    }
    $time = strtotime((string) $value);
    return $time ? date('d.m.Y', $time) : (string) $value;
}

function agreement_report_money($value)
{
    return number_format((float) $value, 2, '.', '');
}

function agreement_report_num($value)
{
    $value = trim((string) $value);
    return $value === '' ? '' : $value;
}

function agreement_report_active_amount($items)
{
    $total = 0.0;
    foreach ($items as $item) {
        if (strtolower(trim((string) ($item['row_status'] ?? 'active'))) === 'cancelled') {
            continue;
        }
        $total += (float) ($item['amount'] ?? 0);
    }
    return $total;
}

$today = date('Y-m-d');
$fromDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from_date'] ?? '')) ? $_GET['from_date'] : date('Y-m-01');
$toDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to_date'] ?? '')) ? $_GET['to_date'] : $today;
if ($fromDate > $toDate) {
    [$fromDate, $toDate] = [$toDate, $fromDate];
}
$reportMode = strtolower((string) ($_GET['report_type'] ?? 'summary'));
$reportMode = in_array($reportMode, ['summary', 'detailed', 'due', 'cancel'], true) ? $reportMode : 'summary';

$userId = auth_current_user_id();
$branchLocation = user_branch_location_for_user($conn, $userId);
$scopeTypes = '';
$scopeParams = [];
if ($branchLocation !== '') {
    $scopeSql = "(a.agreement_branch_location = ?
        OR (COALESCE(a.agreement_branch_location, '') = '' AND cc.branch_location = ?)
        OR (COALESCE(a.agreement_branch_location, '') = '' AND COALESCE(a.collection_center_id, 0) = 0 AND u.branch_location = ?))";
    $scopeTypes = 'sss';
    $scopeParams = [$branchLocation, $branchLocation, $branchLocation];
} else {
    $scopeSql = user_branch_scope_sql($conn, $userId, 'a.user_id');
}
$sql = "SELECT a.*, u.full_name, u.branch_location
    FROM sm_stone_agreements a
    LEFT JOIN sm_users u ON u.id = a.user_id
    LEFT JOIN sm_collection_centers cc ON cc.id = a.collection_center_id
    WHERE {$scopeSql}
        AND a.agreement_date BETWEEN ? AND ?
    ORDER BY a.agreement_date ASC, a.agreement_no ASC, a.id ASC";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    die('Unable to prepare agreement report.');
}
$bindTypes = $scopeTypes . 'ss';
$bindParams = array_merge($scopeParams, [$fromDate, $toDate]);
$bindRefs = [$bindTypes];
foreach ($bindParams as $key => &$value) {
    $bindRefs[] = &$value;
}
call_user_func_array([$stmt, 'bind_param'], $bindRefs);
$stmt->execute();
$result = $stmt->get_result();
$agreements = [];
while ($row = $result->fetch_assoc()) {
    $row['_items'] = agreement_get_items($conn, (int) $row['id'], $row);
    $agreements[] = $row;
}
$stmt->close();

$summary = [
    'cash' => 0.0,
    'cheque' => 0.0,
    'neft' => 0.0,
    'card' => 0.0,
    'tds' => 0.0,
    'due' => 0.0,
    'refund' => 0.0,
    'amount' => 0.0,
    'stones' => 0,
    'member' => 0,
    'non_member' => 0,
    'mou' => 0,
    'atm' => 0,
    'a4' => 0,
    'postcard' => 0,
    'cancel' => 0,
];

foreach ($agreements as $agreement) {
    $items = $agreement['_items'];
    $summary['cash'] += (float) ($agreement['payment_cash'] ?? 0);
    $summary['cheque'] += (float) ($agreement['payment_cheque'] ?? 0);
    $summary['neft'] += (float) ($agreement['payment_neft'] ?? 0);
    $summary['card'] += (float) ($agreement['payment_card'] ?? 0);
    $summary['tds'] += (float) ($agreement['payment_tds'] ?? 0);
    $summary['due'] += (float) ($agreement['due_amount'] ?? 0);
    $summary['refund'] += (float) ($agreement['refund_amount'] ?? 0);
    $summary['amount'] += agreement_report_active_amount($items);
    if (strtolower(trim((string) ($agreement['member_status'] ?? ''))) === 'member') {
        $summary['member']++;
    } else {
        $summary['non_member']++;
    }
    if (trim((string) ($agreement['mou_cdc'] ?? '')) !== '') {
        $summary['mou']++;
    }
    foreach ($items as $item) {
        $cancelled = strtolower(trim((string) ($item['row_status'] ?? 'active'))) === 'cancelled';
        if ($cancelled) {
            $summary['cancel']++;
            continue;
        }
        $summary['stones'] += (int) ($item['pcs'] ?? 0);
        $size = strtoupper(trim((string) ($item['a4_card'] ?? 'A4')));
        if (strpos($size, 'ATM') !== false) {
            $summary['atm'] += (int) ($item['pcs'] ?? 0);
        } elseif (strpos($size, 'POST') !== false) {
            $summary['postcard'] += (int) ($item['pcs'] ?? 0);
        } else {
            $summary['a4'] += (int) ($item['pcs'] ?? 0);
        }
    }
}

$dueAgreements = array_values(array_filter($agreements, function ($agreement) {
    return (float) ($agreement['due_amount'] ?? 0) > 0;
}));
$dueTotal = 0.0;
foreach ($dueAgreements as $agreement) {
    $dueTotal += (float) ($agreement['due_amount'] ?? 0);
}

$cancelRows = [];
$cancelTotal = 0.0;
$cancelPcsTotal = 0;
foreach ($agreements as $agreement) {
    foreach ($agreement['_items'] as $item) {
        if (strtolower(trim((string) ($item['row_status'] ?? 'active'))) !== 'cancelled') {
            continue;
        }
        $cancelRows[] = ['agreement' => $agreement, 'item' => $item];
        $cancelTotal += (float) ($item['amount'] ?? 0);
        $cancelPcsTotal += (int) ($item['pcs'] ?? 0);
    }
}

$reportTitles = [
    'summary' => 'Total Collection Report',
    'detailed' => 'Total Collection Report',
    'due' => 'Due Report',
    'cancel' => 'Cancel Report',
];

if (strtolower((string) ($_GET['export'] ?? '')) === 'excel') {
    $safeMode = preg_replace('/[^a-z0-9_-]/i', '', $reportMode);
    $filename = 'agreement-' . $safeMode . '-report-' . $fromDate . '-to-' . $toDate . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, [$reportTitles[$reportMode] ?? 'Agreement Report']);
    fputcsv($out, ['Date Range', agreement_report_date($fromDate) . ' - ' . agreement_report_date($toDate)]);
    fputcsv($out, []);

    if ($reportMode === 'summary') {
        fputcsv($out, ['Ref.No', 'Date', 'Customer', 'Member/Non', 'MOU', 'Amount']);
        foreach ($agreements as $agreement) {
            fputcsv($out, [
                $agreement['agreement_no'] ?? '',
                agreement_report_date($agreement['agreement_date'] ?? ''),
                $agreement['customer_name'] ?? '',
                $agreement['member_status'] ?? '',
                trim((string) ($agreement['mou_cdc'] ?? '')) !== '' ? 'Yes' : '',
                agreement_report_money(agreement_report_active_amount($agreement['_items'])),
            ]);
        }
        fputcsv($out, ['', '', '', '', 'Total Amount', agreement_report_money($summary['amount'])]);
        fputcsv($out, []);
        fputcsv($out, ['Cash', 'Cheque', 'NEFT/UPI', 'Card', 'TDS', 'Due', 'Refund']);
        fputcsv($out, [
            agreement_report_money($summary['cash']),
            agreement_report_money($summary['cheque']),
            agreement_report_money($summary['neft']),
            agreement_report_money($summary['card']),
            agreement_report_money($summary['tds']),
            agreement_report_money($summary['due']),
            agreement_report_money($summary['refund']),
        ]);
        fputcsv($out, []);
        fputcsv($out, ['Total Stones Submitted', (int) $summary['stones']]);
        fputcsv($out, ['Member', (int) $summary['member']]);
        fputcsv($out, ['Non Member', (int) $summary['non_member']]);
        fputcsv($out, ['MOU/CDC', (int) $summary['mou']]);
        fputcsv($out, ['ATM Size', (int) $summary['atm']]);
        fputcsv($out, ['A4 Size', (int) $summary['a4']]);
        fputcsv($out, ['Postcard', (int) $summary['postcard']]);
        fputcsv($out, ['Cancel', (int) $summary['cancel']]);
    } elseif ($reportMode === 'due') {
        fputcsv($out, ['Agreement No', 'Date', 'Customer', 'Mobile No.', 'Member/Non', 'MOU', 'Total Amount', 'Paid Amount', 'Due Amount', 'Status']);
        foreach ($dueAgreements as $agreement) {
            $totalAmount = agreement_report_active_amount($agreement['_items']);
            $paidAmount = (float) ($agreement['payment_cash'] ?? 0)
                + (float) ($agreement['payment_cheque'] ?? 0)
                + (float) ($agreement['payment_neft'] ?? 0)
                + (float) ($agreement['payment_card'] ?? 0)
                + (float) ($agreement['payment_tds'] ?? 0);
            fputcsv($out, [
                $agreement['agreement_no'] ?? '',
                agreement_report_date($agreement['agreement_date'] ?? ''),
                $agreement['customer_name'] ?? '',
                $agreement['mobile_no'] ?? '',
                $agreement['member_status'] ?? '',
                trim((string) ($agreement['mou_cdc'] ?? '')) !== '' ? 'Yes' : '',
                agreement_report_money($totalAmount),
                agreement_report_money($paidAmount),
                agreement_report_money($agreement['due_amount'] ?? 0),
                agreement_status_label($agreement['agreement_status'] ?? 'IN_PROCESS'),
            ]);
        }
        fputcsv($out, ['', '', '', '', '', '', '', 'Total Due', agreement_report_money($dueTotal), '']);
    } elseif ($reportMode === 'cancel') {
        fputcsv($out, ['Agreement No', 'Date', 'Customer', 'Ref.No', 'Category', 'Size', 'Pcs', 'Amount', 'Reason']);
        foreach ($cancelRows as $row) {
            $agreement = $row['agreement'];
            $item = $row['item'];
            fputcsv($out, [
                $agreement['agreement_no'] ?? '',
                agreement_report_date($agreement['agreement_date'] ?? ''),
                $agreement['customer_name'] ?? '',
                $item['ref_no'] ?? '',
                $item['category'] ?? '',
                ($item['a4_card'] ?? '') ?: 'A4',
                (int) ($item['pcs'] ?? 0),
                agreement_report_money($item['amount'] ?? 0),
                ($item['row_cancel_reason'] ?? '') ?: 'Cancelled',
            ]);
        }
        fputcsv($out, ['', '', '', '', '', 'Total Cancelled', (int) $cancelPcsTotal, agreement_report_money($cancelTotal), '']);
    } else {
        fputcsv($out, ['Date', 'Ref.No', 'Category', 'Size', 'Nor/Urgent', 'Gross_wt', 'Stone_wt', 'Dia_Wt', 'Bead-Lnth', 'Pcs', 'Amount', 'Cancel']);
        foreach ($agreements as $agreement) {
            $agreementPcs = 0;
            $agreementAmount = 0.0;
            foreach ($agreement['_items'] as $item) {
                if (strtolower(trim((string) ($item['row_status'] ?? 'active'))) === 'cancelled') {
                    continue;
                }
                $agreementPcs += (int) ($item['pcs'] ?? 0);
                $agreementAmount += (float) ($item['amount'] ?? 0);
            }
            foreach ($agreement['_items'] as $item) {
                $cancelled = strtolower(trim((string) ($item['row_status'] ?? 'active'))) === 'cancelled';
                fputcsv($out, [
                    agreement_report_date($agreement['agreement_date'] ?? ''),
                    $item['ref_no'] ?? '',
                    $item['category'] ?? '',
                    ($item['a4_card'] ?? '') ?: 'A4',
                    ($agreement['category'] ?? '') === 'Urgent' ? 'Urgent' : 'Regular',
                    agreement_report_num($item['gross_wt'] ?? ''),
                    agreement_report_num($item['stone_wt'] ?? ''),
                    agreement_report_num($item['dia_wt'] ?? ''),
                    agreement_report_num($item['bead_length'] ?? ''),
                    (int) ($item['pcs'] ?? 0),
                    agreement_report_money($item['amount'] ?? 0),
                    $cancelled ? (($item['row_cancel_reason'] ?? '') ?: 'Cancelled') : '',
                ]);
            }
            fputcsv($out, ['', '', '', '', '', '', '', '', 'Total for Agreement No ' . ($agreement['agreement_no'] ?? ''), (int) $agreementPcs, agreement_report_money($agreementAmount), '']);
        }
    }
    fclose($out);
    exit;
}

include 'assets/navbar.php';
$exportParams = [
    'from_date' => $fromDate,
    'to_date' => $toDate,
    'report_type' => $reportMode,
    'export' => 'excel',
];
$exportUrl = 'agreement-reports.php?' . http_build_query($exportParams);
?>
<style>
    .agreement-report-page {
        padding-bottom: 30px;
    }
    .report-head {
        align-items: flex-end;
        border-bottom: 1px solid #ececf1;
        display: flex;
        gap: 16px;
        justify-content: space-between;
        margin-bottom: 14px;
        padding-bottom: 12px;
    }
    .report-head h1 {
        border: 0;
        color: #171717;
        font-size: 22px;
        font-weight: 600;
        margin: 0 0 4px;
        padding: 0;
    }
    .report-head p {
        color: #737373;
        font-size: 13px;
        margin: 0;
    }
    .report-filter {
        align-items: end;
        background: #fff;
        border: 1px solid #ececf1;
        border-radius: 8px;
        display: grid;
        gap: 10px;
        grid-template-columns: 150px 150px 170px auto;
        margin-bottom: 14px;
        padding: 12px;
    }
    .report-filter label {
        color: #404040;
        display: block;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 4px;
    }
    .report-filter .form-control {
        border-radius: 6px;
        font-size: 13px;
        height: 34px;
    }
    .report-filter .btn {
        border-radius: 7px;
        min-height: 34px;
    }
    .report-sheet {
        background: #fff;
        border: 1px solid #d8d8df;
        color: #111;
        font-family: Arial, Helvetica, sans-serif;
        margin: 0 auto;
        max-width: 1120px;
        min-height: 640px;
        padding: 18px 22px;
    }
    .report-sheet,
    .report-sheet * {
        box-sizing: border-box;
    }
    .report-title {
        text-align: center;
    }
    .report-title h2 {
        font-size: 16px;
        font-weight: 800;
        margin: 0 0 4px;
        text-decoration: underline;
        text-transform: uppercase;
    }
    .report-title .range {
        font-size: 12px;
        font-weight: 700;
        text-decoration: underline;
    }
    .report-table {
        border-collapse: collapse;
        margin-top: 12px;
        table-layout: fixed;
        width: 100%;
    }
    .report-table th,
    .report-table td {
        border-bottom: 1px solid #222;
        font-size: 12px;
        line-height: 1.25;
        padding: 5px 6px;
        vertical-align: top;
        word-break: normal;
        overflow-wrap: anywhere;
    }
    .report-table th {
        font-weight: 700;
        text-align: left;
    }
    .report-table .num {
        text-align: right;
        white-space: nowrap;
    }
    .report-sheet-summary .report-table th:nth-child(1),
    .report-sheet-summary .report-table td:nth-child(1) {
        width: 15%;
    }
    .report-sheet-summary .report-table th:nth-child(2),
    .report-sheet-summary .report-table td:nth-child(2) {
        width: 12%;
    }
    .report-sheet-summary .report-table th:nth-child(3),
    .report-sheet-summary .report-table td:nth-child(3) {
        width: 33%;
    }
    .report-sheet-summary .report-table th:nth-child(4),
    .report-sheet-summary .report-table td:nth-child(4) {
        width: 14%;
    }
    .report-sheet-summary .report-table th:nth-child(5),
    .report-sheet-summary .report-table td:nth-child(5) {
        width: 10%;
    }
    .report-sheet-summary .report-table th:nth-child(6),
    .report-sheet-summary .report-table td:nth-child(6) {
        width: 16%;
    }
    .summary-payments {
        border-collapse: collapse;
        margin-top: 12px;
        width: 70%;
    }
    .summary-payments th,
    .summary-payments td {
        border: 1px solid #111;
        font-size: 12px;
        height: 28px;
        padding: 4px 8px;
        text-align: right;
    }
    .summary-payments th {
        font-weight: 500;
        text-align: center;
    }
    .summary-bottom {
        align-items: flex-start;
        display: flex;
        justify-content: space-between;
        margin-top: 8px;
    }
    .stones-total {
        font-size: 13px;
        font-weight: 700;
        margin: 28px 0 0 18px;
    }
    .stones-total strong {
        display: inline-block;
        font-size: 18px;
        margin-left: 18px;
    }
    .count-table {
        border-collapse: collapse;
        width: 235px;
    }
    .count-table td {
        border: 1px solid #111;
        font-size: 12px;
        font-weight: 700;
        padding: 6px 12px;
    }
    .count-table td:last-child {
        font-weight: 400;
        text-align: right;
        width: 72px;
    }
    .detail-table th,
    .detail-table td {
        border-bottom: 0;
        font-size: 10px;
        padding: 4px 5px;
    }
    .detail-table thead th {
        border-bottom: 1px solid #222;
        border-top: 1px solid #222;
    }
    .detail-table .category {
        max-width: 190px;
        overflow-wrap: anywhere;
    }
    .detail-table th:nth-child(1),
    .detail-table td:nth-child(1) {
        width: 7%;
    }
    .detail-table th:nth-child(2),
    .detail-table td:nth-child(2) {
        width: 10%;
    }
    .detail-table th:nth-child(3),
    .detail-table td:nth-child(3) {
        width: 19%;
    }
    .detail-table th:nth-child(4),
    .detail-table td:nth-child(4),
    .detail-table th:nth-child(5),
    .detail-table td:nth-child(5) {
        width: 7%;
    }
    .detail-table th:nth-child(6),
    .detail-table td:nth-child(6),
    .detail-table th:nth-child(7),
    .detail-table td:nth-child(7),
    .detail-table th:nth-child(8),
    .detail-table td:nth-child(8),
    .detail-table th:nth-child(9),
    .detail-table td:nth-child(9) {
        width: 7%;
    }
    .detail-table th:nth-child(10),
    .detail-table td:nth-child(10) {
        width: 5%;
    }
    .detail-table th:nth-child(11),
    .detail-table td:nth-child(11) {
        width: 7%;
    }
    .detail-table th:nth-child(12),
    .detail-table td:nth-child(12) {
        width: 10%;
    }
    .agreement-total td {
        border-bottom: 1px solid #222;
        border-top: 1px solid #222;
        font-weight: 800;
    }
    .agreement-total-label {
        white-space: nowrap;
    }
    .cancelled-line td {
        color: #7f1d1d;
    }
    .print-actions {
        text-align: right;
        margin-bottom: 10px;
    }
    @media (max-width: 800px) {
        .report-head,
        .summary-bottom {
            display: block;
        }
        .report-filter {
            grid-template-columns: 1fr;
        }
        .summary-payments {
            width: 100%;
        }
        .count-table {
            margin-top: 14px;
            width: 100%;
        }
    }
    @media print {
        @page {
            size: A4 landscape;
            margin: 8mm;
        }
        html,
        body {
            background: #fff !important;
            color: #000 !important;
            font-family: Arial, Helvetica, sans-serif !important;
            font-size: 9.5px !important;
            line-height: 1.2 !important;
            margin: 0 !important;
            padding: 0 !important;
            width: auto !important;
        }
        .navbar,
        .sidebar,
        .report-head,
        .report-filter,
        .print-actions,
        .app-sidebar-reveal {
            display: none !important;
        }
        #page-wrapper {
            background: #fff !important;
            border: 0 !important;
            margin: 0 !important;
            max-width: none !important;
            min-height: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
        .container-fluid,
        .agreement-report-page {
            margin: 0 !important;
            max-width: none !important;
            padding: 0 !important;
            width: 100% !important;
        }
        .report-sheet {
            border: 0;
            color: #000 !important;
            font-family: Arial, Helvetica, sans-serif !important;
            margin: 0 !important;
            max-width: none;
            min-height: 0;
            padding: 0 !important;
            width: 100% !important;
        }
        .report-title {
            margin-bottom: 5mm;
        }
        .report-title h2 {
            font-size: 12px !important;
            line-height: 1.15 !important;
            margin: 0 0 2mm !important;
        }
        .report-title .range {
            font-size: 9px !important;
            line-height: 1.15 !important;
        }
        .report-table {
            margin-top: 0 !important;
            table-layout: fixed !important;
            width: 100% !important;
        }
        .report-table thead {
            display: table-header-group;
        }
        .report-table tr {
            page-break-inside: avoid;
        }
        .report-table th,
        .report-table td {
            background: #fff !important;
            border-color: #000 !important;
            color: #000 !important;
            font-size: 8.5px !important;
            font-weight: 400 !important;
            line-height: 1.18 !important;
            padding: 2.2px 4px !important;
            vertical-align: top !important;
        }
        .report-table th {
            font-weight: 700 !important;
            text-align: left !important;
        }
        .report-table .num {
            text-align: right !important;
            white-space: nowrap !important;
        }
        .detail-table th,
        .detail-table td {
            font-size: 7.5px !important;
            line-height: 1.14 !important;
            padding: 2px 3px !important;
        }
        .agreement-total td {
            border-bottom: 1px solid #000 !important;
            border-top: 1px solid #000 !important;
            font-weight: 700 !important;
        }
        .agreement-total-label {
            white-space: nowrap !important;
            overflow-wrap: normal !important;
            word-break: normal !important;
        }
        .summary-payments {
            margin-top: 5mm !important;
            table-layout: fixed !important;
            width: 64% !important;
        }
        .summary-payments th,
        .summary-payments td {
            border: 1px solid #000 !important;
            color: #000 !important;
            font-size: 8.5px !important;
            height: 20px !important;
            line-height: 1.15 !important;
            padding: 2px 4px !important;
        }
        .summary-bottom {
            align-items: flex-start !important;
            display: flex !important;
            justify-content: space-between !important;
            margin-top: 4mm !important;
            width: 100% !important;
        }
        .stones-total {
            color: #000 !important;
            font-size: 9px !important;
            margin: 10mm 0 0 8mm !important;
        }
        .stones-total strong {
            font-size: 11px !important;
            margin-left: 8mm !important;
        }
        .count-table {
            border-collapse: collapse !important;
            width: 45mm !important;
        }
        .count-table td {
            border: 1px solid #000 !important;
            color: #000 !important;
            font-size: 8.5px !important;
            line-height: 1.15 !important;
            padding: 2.2px 5px !important;
        }
        .count-table td:last-child {
            width: 16mm !important;
        }
        .cancelled-line td {
            color: #000 !important;
        }
    }
</style>

<div id="page-wrapper">
    <div class="container-fluid agreement-report-page">
        <div class="report-head">
            <div>
                <h1><i class="fa fa-table fa-fw"></i> Agreement Reports</h1>
                <p>Generate summary or detailed agreement reports by date range.</p>
            </div>
            <div>
                <a class="btn btn-success" href="<?php echo agreement_report_h($exportUrl); ?>"><i class="fa fa-file-excel-o"></i> Export Excel</a>
                <button type="button" class="btn btn-default" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
            </div>
        </div>

        <form class="report-filter" method="get">
            <div>
                <label for="from_date">Date From</label>
                <input class="form-control" type="date" id="from_date" name="from_date" value="<?php echo agreement_report_h($fromDate); ?>">
            </div>
            <div>
                <label for="to_date">Date To</label>
                <input class="form-control" type="date" id="to_date" name="to_date" value="<?php echo agreement_report_h($toDate); ?>">
            </div>
            <div>
                <label for="report_type">Report Type</label>
                <select class="form-control" id="report_type" name="report_type">
                    <option value="summary" <?php echo $reportMode === 'summary' ? 'selected' : ''; ?>>Summary Report</option>
                    <option value="detailed" <?php echo $reportMode === 'detailed' ? 'selected' : ''; ?>>Detailed Report</option>
                    <option value="due" <?php echo $reportMode === 'due' ? 'selected' : ''; ?>>Due Report</option>
                    <option value="cancel" <?php echo $reportMode === 'cancel' ? 'selected' : ''; ?>>Cancel Report</option>
                </select>
            </div>
            <div>
                <button class="btn btn-primary" type="submit"><i class="fa fa-search"></i> View Report</button>
            </div>
        </form>

        <div class="print-actions">
            <a class="btn btn-success btn-sm" href="<?php echo agreement_report_h($exportUrl); ?>"><i class="fa fa-file-excel-o"></i> Export Excel</a>
            <button type="button" class="btn btn-default btn-sm" onclick="window.print()"><i class="fa fa-print"></i> Print Report</button>
        </div>

        <div class="report-sheet report-sheet-<?php echo agreement_report_h($reportMode); ?>">
            <div class="report-title">
                <h2><?php echo agreement_report_h($reportTitles[$reportMode] ?? 'Total Collection Report'); ?></h2>
                <div class="range"><?php echo agreement_report_h(agreement_report_date($fromDate) . '-' . agreement_report_date($toDate)); ?></div>
            </div>

            <?php if ($reportMode === 'summary'): ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Ref.No</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Member/Non</th>
                            <th>MOU</th>
                            <th class="num">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agreements as $agreement): ?>
                            <tr>
                                <td><?php echo agreement_report_h($agreement['agreement_no'] ?? ''); ?></td>
                                <td><?php echo agreement_report_h(agreement_report_date($agreement['agreement_date'] ?? '')); ?></td>
                                <td><?php echo agreement_report_h($agreement['customer_name'] ?? ''); ?></td>
                                <td><?php echo agreement_report_h($agreement['member_status'] ?? ''); ?></td>
                                <td><?php echo trim((string) ($agreement['mou_cdc'] ?? '')) !== '' ? 'Yes' : ''; ?></td>
                                <td class="num"><?php echo agreement_report_h(agreement_report_money(agreement_report_active_amount($agreement['_items']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td colspan="5"></td>
                            <td class="num"><?php echo agreement_report_h(agreement_report_money($summary['amount'])); ?></td>
                        </tr>
                    </tbody>
                </table>
                <table class="summary-payments">
                    <thead>
                        <tr>
                            <th>CASH</th>
                            <th>CHEQUE</th>
                            <th>NEFT/UPI</th>
                            <th>CARD</th>
                            <th>TDS</th>
                            <th>DUE</th>
                            <th>REFUND</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo agreement_report_h(agreement_report_money($summary['cash'])); ?></td>
                            <td><?php echo agreement_report_h(agreement_report_money($summary['cheque'])); ?></td>
                            <td><?php echo agreement_report_h(agreement_report_money($summary['neft'])); ?></td>
                            <td><?php echo agreement_report_h(agreement_report_money($summary['card'])); ?></td>
                            <td><?php echo agreement_report_h(agreement_report_money($summary['tds'])); ?></td>
                            <td><?php echo agreement_report_h(agreement_report_money($summary['due'])); ?></td>
                            <td><?php echo agreement_report_h(agreement_report_money($summary['refund'])); ?></td>
                        </tr>
                    </tbody>
                </table>
                <div class="summary-bottom">
                    <div class="stones-total">Total Stones Submitted <strong><?php echo (int) $summary['stones']; ?></strong></div>
                    <table class="count-table">
                        <tr><td>Member</td><td><?php echo (int) $summary['member']; ?></td></tr>
                        <tr><td>Non Member</td><td><?php echo (int) $summary['non_member']; ?></td></tr>
                        <tr><td>MOU/CDC</td><td><?php echo (int) $summary['mou']; ?></td></tr>
                        <tr><td>ATM SIZE</td><td><?php echo (int) $summary['atm']; ?></td></tr>
                        <tr><td>A4 SIZE</td><td><?php echo (int) $summary['a4']; ?></td></tr>
                        <?php if ($summary['postcard'] > 0): ?><tr><td>POSTCARD</td><td><?php echo (int) $summary['postcard']; ?></td></tr><?php endif; ?>
                        <tr><td>CANCEL</td><td><?php echo (int) $summary['cancel']; ?></td></tr>
                    </table>
                </div>
            <?php elseif ($reportMode === 'due'): ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Agreement No</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Mobile No.</th>
                            <th>Member/Non</th>
                            <th>MOU</th>
                            <th class="num">Total Amount</th>
                            <th class="num">Paid Amount</th>
                            <th class="num">Due Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dueAgreements as $agreement): ?>
                            <?php
                            $totalAmount = agreement_report_active_amount($agreement['_items']);
                            $paidAmount = (float) ($agreement['payment_cash'] ?? 0)
                                + (float) ($agreement['payment_cheque'] ?? 0)
                                + (float) ($agreement['payment_neft'] ?? 0)
                                + (float) ($agreement['payment_card'] ?? 0)
                                + (float) ($agreement['payment_tds'] ?? 0);
                            ?>
                            <tr>
                                <td><?php echo agreement_report_h($agreement['agreement_no'] ?? ''); ?></td>
                                <td><?php echo agreement_report_h(agreement_report_date($agreement['agreement_date'] ?? '')); ?></td>
                                <td><?php echo agreement_report_h($agreement['customer_name'] ?? ''); ?></td>
                                <td><?php echo agreement_report_h($agreement['mobile_no'] ?? ''); ?></td>
                                <td><?php echo agreement_report_h($agreement['member_status'] ?? ''); ?></td>
                                <td><?php echo trim((string) ($agreement['mou_cdc'] ?? '')) !== '' ? 'Yes' : ''; ?></td>
                                <td class="num"><?php echo agreement_report_h(agreement_report_money($totalAmount)); ?></td>
                                <td class="num"><?php echo agreement_report_h(agreement_report_money($paidAmount)); ?></td>
                                <td class="num"><strong><?php echo agreement_report_h(agreement_report_money($agreement['due_amount'] ?? 0)); ?></strong></td>
                                <td><?php echo agreement_report_h(agreement_status_label($agreement['agreement_status'] ?? 'IN_PROCESS')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="agreement-total">
                            <td colspan="8" class="num">Total Due</td>
                            <td class="num"><?php echo agreement_report_h(agreement_report_money($dueTotal)); ?></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
                <?php if (!$dueAgreements): ?>
                    <div style="padding:24px;text-align:center;color:#737373;font-size:13px;">No due agreements found in this date range.</div>
                <?php endif; ?>
            <?php elseif ($reportMode === 'cancel'): ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Agreement No</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Ref.No</th>
                            <th>Category</th>
                            <th>Size</th>
                            <th class="num">Pcs</th>
                            <th class="num">Amount</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cancelRows as $row): ?>
                            <?php
                            $agreement = $row['agreement'];
                            $item = $row['item'];
                            ?>
                            <tr class="cancelled-line">
                                <td><?php echo agreement_report_h($agreement['agreement_no'] ?? ''); ?></td>
                                <td><?php echo agreement_report_h(agreement_report_date($agreement['agreement_date'] ?? '')); ?></td>
                                <td><?php echo agreement_report_h($agreement['customer_name'] ?? ''); ?></td>
                                <td><?php echo agreement_report_h($item['ref_no'] ?? ''); ?></td>
                                <td><?php echo agreement_report_h($item['category'] ?? ''); ?></td>
                                <td><?php echo agreement_report_h(($item['a4_card'] ?? '') ?: 'A4'); ?></td>
                                <td class="num"><?php echo (int) ($item['pcs'] ?? 0); ?></td>
                                <td class="num"><?php echo agreement_report_h(agreement_report_money($item['amount'] ?? 0)); ?></td>
                                <td><?php echo agreement_report_h(($item['row_cancel_reason'] ?? '') ?: 'Cancelled'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="agreement-total">
                            <td colspan="6" class="num">Total Cancelled</td>
                            <td class="num"><?php echo (int) $cancelPcsTotal; ?></td>
                            <td class="num"><?php echo agreement_report_h(agreement_report_money($cancelTotal)); ?></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
                <?php if (!$cancelRows): ?>
                    <div style="padding:24px;text-align:center;color:#737373;font-size:13px;">No cancelled entries found in this date range.</div>
                <?php endif; ?>
            <?php else: ?>
                <table class="report-table detail-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Ref.No</th>
                            <th>Category</th>
                            <th>Size</th>
                            <th>Nor/Urgent</th>
                            <th class="num">Gross_wt</th>
                            <th class="num">Stone_wt</th>
                            <th class="num">Dia_Wt</th>
                            <th class="num">Bead-Lnth</th>
                            <th class="num">Pcs</th>
                            <th class="num">Amount</th>
                            <th>Cancel</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agreements as $agreement): ?>
                            <?php
                            $agreementPcs = 0;
                            $agreementAmount = 0.0;
                            foreach ($agreement['_items'] as $item) {
                                if (strtolower(trim((string) ($item['row_status'] ?? 'active'))) === 'cancelled') {
                                    continue;
                                }
                                $agreementPcs += (int) ($item['pcs'] ?? 0);
                                $agreementAmount += (float) ($item['amount'] ?? 0);
                            }
                            ?>
                            <?php foreach ($agreement['_items'] as $item): ?>
                                <?php $cancelled = strtolower(trim((string) ($item['row_status'] ?? 'active'))) === 'cancelled'; ?>
                                <tr class="<?php echo $cancelled ? 'cancelled-line' : ''; ?>">
                                    <td><?php echo agreement_report_h(agreement_report_date($agreement['agreement_date'] ?? '')); ?></td>
                                    <td><?php echo agreement_report_h($item['ref_no'] ?? ''); ?></td>
                                    <td class="category"><?php echo agreement_report_h($item['category'] ?? ''); ?></td>
                                    <td><?php echo agreement_report_h(($item['a4_card'] ?? '') ?: 'A4'); ?></td>
                                    <td><?php echo agreement_report_h(($agreement['category'] ?? '') === 'Urgent' ? 'Urgent' : 'Regular'); ?></td>
                                    <td class="num"><?php echo agreement_report_h(agreement_report_num($item['gross_wt'] ?? '')); ?></td>
                                    <td class="num"><?php echo agreement_report_h(agreement_report_num($item['stone_wt'] ?? '')); ?></td>
                                    <td class="num"><?php echo agreement_report_h(agreement_report_num($item['dia_wt'] ?? '')); ?></td>
                                    <td class="num"><?php echo agreement_report_h(agreement_report_num($item['bead_length'] ?? '')); ?></td>
                                    <td class="num"><?php echo agreement_report_h((int) ($item['pcs'] ?? 0)); ?></td>
                                    <td class="num"><?php echo agreement_report_h(agreement_report_money($item['amount'] ?? 0)); ?></td>
                                    <td><?php echo $cancelled ? agreement_report_h(($item['row_cancel_reason'] ?? '') ?: 'Cancelled') : ''; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="agreement-total">
                                <td colspan="9" class="agreement-total-label num">Total for Agreement No&nbsp;&nbsp;<?php echo agreement_report_h($agreement['agreement_no'] ?? ''); ?></td>
                                <td class="num"><?php echo (int) $agreementPcs; ?></td>
                                <td class="num"><?php echo agreement_report_h(agreement_report_money($agreementAmount)); ?></td>
                                <td></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'assets/footer.php'; ?>
