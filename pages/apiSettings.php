<?php
require_once 'auth.php';
auth_require_login();
require_once 'db_connect.php';
require_once 'api/api_common.php';

$userId = auth_current_user_id();
$message = '';
$messageType = 'success';
$newApiKey = '';
$migrationReady = false;
$tableCheck = $conn->query("SHOW TABLES LIKE 'sm_api_settings'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $logTableCheck = $conn->query("SHOW TABLES LIKE 'sm_api_usage_logs'");
    $migrationReady = $logTableCheck && $logTableCheck->num_rows > 0;
}
if (!$migrationReady) {
    $message = 'API database setup is incomplete. Run database_api_verification_update.sql in phpMyAdmin.';
    $messageType = 'danger';
}

if (!isset($_SESSION['api_settings_csrf'])) {
    $_SESSION['api_settings_csrf'] = bin2hex(random_bytes(24));
}
$csrfToken = $_SESSION['api_settings_csrf'];

function api_settings_url_pattern($url)
{
    $url = trim((string) $url);
    if ($url === '' || strpos($url, '{report_no}') !== false) {
        return $url;
    }
    return $url . (strpos($url, '?') === false ? '?' : '&') . 'report_no={report_no}';
}

function api_settings_save_qr_url($conn, $userId, $urlPattern)
{
    $stmt = $conn->prepare('INSERT INTO sm_qr_settings (user_id, url_pattern, created_at, updated_at) VALUES (?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE url_pattern = VALUES(url_pattern), updated_at = NOW()');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('is', $userId, $urlPattern);
    $saved = $stmt->execute();
    $stmt->close();
    return $saved;
}

if ($migrationReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        $message = 'Your session expired. Refresh and try again.';
        $messageType = 'danger';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'generate' || $action === 'regenerate') {
            $newApiKey = 'sl_live_' . bin2hex(random_bytes(32));
            $keyHash = hash('sha256', $newApiKey);
            $keyPrefix = substr($newApiKey, 0, 16);
            $defaultFields = json_encode(api_default_public_fields());
            $stmt = $conn->prepare('INSERT INTO sm_api_settings (user_id, api_key_hash, api_key_prefix, public_fields, key_created_at, key_revoked_at, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NULL, NOW(), NOW()) ON DUPLICATE KEY UPDATE api_key_hash = VALUES(api_key_hash), api_key_prefix = VALUES(api_key_prefix), public_fields = COALESCE(public_fields, VALUES(public_fields)), key_created_at = NOW(), key_revoked_at = NULL, updated_at = NOW()');
            if ($stmt) {
                $stmt->bind_param('isss', $userId, $keyHash, $keyPrefix, $defaultFields);
                $stmt->execute();
                $stmt->close();
                $message = $action === 'regenerate' ? 'API key regenerated. The old key is now invalid.' : 'API key generated.';
            } else {
                $message = 'Unable to generate the API key.';
                $messageType = 'danger';
            }
        } elseif ($action === 'revoke') {
            $stmt = $conn->prepare('UPDATE sm_api_settings SET api_key_hash = NULL, api_key_prefix = NULL, key_revoked_at = NOW(), updated_at = NOW() WHERE user_id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $stmt->close();
            }
            $message = 'API key revoked. Connected websites can no longer access certificate data.';
        } elseif ($action === 'save_settings') {
            $verificationUrl = trim((string) ($_POST['verification_url'] ?? ''));
            $urlForValidation = str_replace('{report_no}', 'R1', $verificationUrl);
            if ($verificationUrl !== '' && !filter_var($urlForValidation, FILTER_VALIDATE_URL)) {
                $message = 'Please enter a valid verification website URL.';
                $messageType = 'danger';
            } else {
                $fields = api_normalize_public_fields($_POST['public_fields'] ?? []);
                if (!$fields) {
                    $fields = ['certi_no'];
                }
                $fieldsJson = json_encode($fields);
                $stmt = $conn->prepare('INSERT INTO sm_api_settings (user_id, verification_url, public_fields, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE verification_url = VALUES(verification_url), public_fields = VALUES(public_fields), updated_at = NOW()');
                if ($stmt) {
                    $stmt->bind_param('iss', $userId, $verificationUrl, $fieldsJson);
                    $stmt->execute();
                    $stmt->close();
                    $qrPattern = api_settings_url_pattern($verificationUrl);
                    if ($qrPattern !== '' && !api_settings_save_qr_url($conn, $userId, $qrPattern)) {
                        $message = 'Settings saved, but the QR URL was not updated. Run database_qr_settings_update.sql.';
                        $messageType = 'warning';
                    } else {
                        $message = 'Verification website and visible fields saved.';
                    }
                }
            }
        } elseif ($action === 'test') {
            $testKey = trim((string) ($_POST['test_api_key'] ?? ''));
            $reportNo = trim((string) ($_POST['test_report_no'] ?? ''));
            $keySettings = api_find_settings_by_key($conn, $testKey);
            if (!$keySettings || (int) $keySettings['user_id'] !== $userId) {
                $message = 'Test failed: invalid or revoked API key.';
                $messageType = 'danger';
                api_log_usage($conn, $userId, '/settings/api-test', 'POST', $reportNo !== '' ? $reportNo : null, 401);
            } elseif ($reportNo === '' || strlen($reportNo) > 100) {
                $message = 'Enter a valid report number.';
                $messageType = 'danger';
            } else {
                $testData = api_fetch_certificate($conn, $userId, $reportNo, $keySettings['public_fields']);
                if ($testData === null) {
                    $message = 'API authentication succeeded, but that report was not found.';
                    $messageType = 'warning';
                    api_log_usage($conn, $userId, '/settings/api-test', 'POST', $reportNo, 404);
                } else {
                    $message = 'API connection successful. Report ' . $reportNo . ' returned ' . count($testData) . ' visible fields.';
                    api_log_usage($conn, $userId, '/settings/api-test', 'POST', $reportNo, 200);
                }
            }
        }
    }
}

$settings = ['api_key_hash' => null, 'api_key_prefix' => null, 'verification_url' => '', 'public_fields' => json_encode(api_default_public_fields()), 'key_created_at' => null, 'key_revoked_at' => null];
$logs = [];
if ($migrationReady) {
    $stmt = $conn->prepare('SELECT * FROM sm_api_settings WHERE user_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $settings = array_merge($settings, $row);
        }
        $stmt->close();
    }
    $stmt = $conn->prepare('SELECT endpoint, request_method, report_no, status_code, ip_address, created_at FROM sm_api_usage_logs WHERE user_id = ? ORDER BY id DESC LIMIT 50');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        $stmt->close();
    } else {
        $message = 'API usage history could not be loaded: ' . $conn->error;
        $messageType = 'danger';
    }
}

$fieldDefinitions = api_certificate_field_definitions();
$selectedFields = api_normalize_public_fields($settings['public_fields']);
$apiActive = !empty($settings['api_key_hash']) && empty($settings['key_revoked_at']);
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$apiEndpoint = $scheme . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/api/v1/certificate.php?report_no={report_no}';
include 'assets/navbar.php';
?>
<style>
.api-page{padding-bottom:35px}.api-hero{margin-bottom:18px}.api-hero h1{border:0;color:#171717;font-size:26px;font-weight:600;margin:0 0 6px;padding:0}.api-hero p{color:#737373;margin:0;max-width:760px}.api-grid{display:grid;gap:16px;grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr)}.api-card{background:#fff;border:1px solid #ececf1;border-radius:10px;overflow:hidden}.api-card-head{border-bottom:1px solid #ececf1;padding:16px 18px}.api-card-head h3{font-size:16px;font-weight:600;margin:0 0 4px}.api-card-head p{color:#737373;font-size:13px;margin:0}.api-card-body{padding:18px}.api-status{align-items:center;display:flex;gap:9px;margin-bottom:14px}.api-dot{background:#ef4444;border-radius:50%;height:9px;width:9px}.api-status.active .api-dot{background:#22c55e}.api-key-box{background:#111827;border-radius:8px;color:#f9fafb;font-family:monospace;font-size:13px;overflow-wrap:anywhere;padding:13px}.api-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.api-actions form{margin:0}.api-fields-toolbar{align-items:center;display:flex;gap:8px;justify-content:space-between;margin-bottom:9px}.api-fields-toolbar span{color:#737373;font-size:12px}.api-field-actions{display:flex;gap:6px}.api-field-actions button{background:#fff;border:1px solid #e5e5e5;border-radius:6px;color:#404040;font-size:11px;padding:4px 8px}.api-fields{background:#f7f7f8;border:1px solid #ececf1;border-radius:9px;display:grid;gap:4px;grid-template-columns:repeat(3,minmax(0,1fr));max-height:190px;overflow-y:auto;padding:7px}.api-field{align-items:center;background:#fff;border:1px solid transparent;border-radius:6px;display:flex;font-size:12px;font-weight:500;gap:7px;margin:0;min-height:34px;padding:6px 8px}.api-field:hover{border-color:#d4d4d4}.api-field:has(input:checked){background:#eef2ff;border-color:#c7d2fe;color:#3730a3}.api-field input{margin:0}.api-form-group{margin-bottom:16px}.api-form-group label{color:#404040;font-size:13px;font-weight:600;margin-bottom:7px}.api-form-group .form-control{border-radius:8px;min-height:42px}.api-help{color:#737373;font-size:12px;line-height:1.5;margin-top:6px}.api-endpoint{background:#f7f7f8;border:1px solid #ececf1;border-radius:8px;font-family:monospace;font-size:12px;overflow-wrap:anywhere;padding:11px}.api-table-wrap{overflow-x:auto}.api-table{margin:0}.api-table th{background:#f7f7f8;color:#525252;font-size:12px}.api-table td{font-size:13px;vertical-align:middle}.status-code{border-radius:999px;display:inline-block;font-size:11px;font-weight:600;padding:3px 8px}.status-ok{background:#dcfce7;color:#166534}.status-warn{background:#fef3c7;color:#92400e}.status-error{background:#fee2e2;color:#991b1b}.new-key-panel{background:#fff;border:1px solid #ececf1;border-left:3px solid #16a34a;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.06);margin-bottom:16px;padding:16px}.new-key-panel-head{align-items:center;display:flex;gap:10px;margin-bottom:11px}.new-key-panel-head i{align-items:center;background:#f0fdf4;border-radius:8px;color:#16a34a;display:flex;font-size:14px;height:32px;justify-content:center;width:32px}.new-key-panel-head strong{color:#171717;margin:0}.new-key-panel-head span{color:#737373;display:block;font-size:12px;margin-top:2px}.api-setup-required{align-items:center;background:#fff;border:1px solid #ececf1;border-left:3px solid #dc2626;border-radius:10px;display:flex;gap:12px;padding:16px}.api-setup-required i{align-items:center;background:#fef2f2;border-radius:8px;color:#dc2626;display:flex;height:34px;justify-content:center;width:34px}.api-setup-required strong{color:#171717;display:block}.api-setup-required span{color:#737373;font-size:12px}@media(max-width:960px){.api-grid{grid-template-columns:1fr}}@media(max-width:700px){.api-fields{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:480px){.api-fields{grid-template-columns:1fr}}
</style>
<div id="page-wrapper"><div class="container-fluid api-page">
<div class="api-hero"><h1><i class="fa fa-key fa-fw"></i> API &amp; Verification</h1><p>Connect an authorized verification website and control which certificate fields it can receive.</p></div>
<?php if (!$migrationReady): ?>
<div class="api-setup-required"><i class="fa fa-database"></i><div><strong>Database setup required</strong><span>Run database_api_verification_update.sql in phpMyAdmin before using API access.</span></div></div>
<?php else: ?>
<?php if ($newApiKey): ?><div class="new-key-panel"><div class="new-key-panel-head"><i class="fa fa-check"></i><div><strong>API key ready</strong><span>Copy it now. For security, the complete key is shown only once.</span></div></div><div class="api-key-box" id="newApiKey"><?php echo htmlspecialchars($newApiKey); ?></div><button type="button" class="btn btn-primary btn-sm" id="copyApiKey" style="margin-top:10px"><i class="fa fa-copy"></i> Copy API Key</button></div><?php endif; ?>
<div class="api-grid">
<div class="api-card"><div class="api-card-head"><h3>API Key</h3><p>Keep this key on the verification website’s server.</p></div><div class="api-card-body">
<div class="api-status <?php echo $apiActive ? 'active' : ''; ?>"><span class="api-dot"></span><strong><?php echo $apiActive ? 'API access active' : 'API access inactive'; ?></strong></div>
<div class="api-key-box"><?php echo $apiActive ? htmlspecialchars($settings['api_key_prefix']) . '••••••••••••••••' : 'No active API key'; ?></div>
<?php if ($settings['key_created_at']): ?><p class="api-help">Created: <?php echo htmlspecialchars($settings['key_created_at']); ?></p><?php endif; ?>
<div class="api-actions"><form method="post" id="<?php echo $apiActive ? 'regenerateApiKeyForm' : 'generateApiKeyForm'; ?>"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>"><input type="hidden" name="action" value="<?php echo $apiActive ? 'regenerate' : 'generate'; ?>"><button type="submit" class="btn btn-primary"><i class="fa fa-refresh"></i> <?php echo $apiActive ? 'Regenerate Key' : 'Generate API Key'; ?></button></form>
<?php if ($apiActive): ?><form method="post" id="revokeApiKeyForm"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>"><input type="hidden" name="action" value="revoke"><button type="submit" class="btn btn-danger"><i class="fa fa-ban"></i> Revoke Key</button></form><?php endif; ?></div>
<hr><label>API endpoint</label><div class="api-endpoint"><?php echo htmlspecialchars($apiEndpoint); ?></div><p class="api-help">Send <code>X-API-Key: your_key</code> as an HTTP header.</p>
</div></div>
<div class="api-card"><div class="api-card-head"><h3>Test API Connection</h3><p>Validate a key against one report.</p></div><div class="api-card-body"><form method="post"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>"><input type="hidden" name="action" value="test"><div class="api-form-group"><label>API Key</label><input type="password" class="form-control" name="test_api_key" placeholder="Paste sl_live_... key" required></div><div class="api-form-group"><label>Report Number</label><input type="text" maxlength="100" class="form-control" name="test_report_no" placeholder="Example: R123" required></div><button class="btn btn-default"><i class="fa fa-plug"></i> Test Connection</button></form></div></div>
</div>
<div class="api-card" style="margin-top:16px"><div class="api-card-head"><h3>Verification Website &amp; Visible Fields</h3><p>The QR opens this website; the API returns only checked fields.</p></div><div class="api-card-body"><form method="post"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>"><input type="hidden" name="action" value="save_settings"><div class="api-form-group"><label>Verification Website URL</label><input type="text" class="form-control" name="verification_url" value="<?php echo htmlspecialchars((string) $settings['verification_url']); ?>" placeholder="https://clientwebsite.com/verify?report_no={report_no}"><p class="api-help">Use <code>{report_no}</code>. It is added automatically if omitted.</p></div><div class="api-form-group"><div class="api-fields-toolbar"><span><strong>Fields available through the API</strong> · <span id="selectedFieldCount"><?php echo count($selectedFields); ?></span> selected</span><div class="api-field-actions"><button type="button" id="selectAllFields">Select all</button><button type="button" id="clearFields">Clear</button></div></div><div class="api-fields"><?php foreach ($fieldDefinitions as $field => $definition): ?><label class="api-field"><input type="checkbox" name="public_fields[]" value="<?php echo htmlspecialchars($field); ?>" <?php echo in_array($field, $selectedFields, true) ? 'checked' : ''; ?>> <?php echo htmlspecialchars($definition['label']); ?></label><?php endforeach; ?></div></div><button class="btn btn-primary"><i class="fa fa-save"></i> Save Verification Settings</button></form></div></div>
<div class="api-card" style="margin-top:16px"><div class="api-card-head"><h3>API Usage History</h3><p>Latest 50 requests.</p></div><div class="api-table-wrap"><table class="table table-hover api-table"><thead><tr><th>Date</th><th>Endpoint</th><th>Report No.</th><th>Status</th><th>IP</th></tr></thead><tbody><?php if (!$logs): ?><tr><td colspan="5" class="text-center text-muted" style="padding:24px">No API requests yet.</td></tr><?php else: foreach ($logs as $log): $status = (int) $log['status_code']; ?><tr><td><?php echo htmlspecialchars($log['created_at']); ?></td><td><code><?php echo htmlspecialchars($log['request_method'] . ' ' . $log['endpoint']); ?></code></td><td><?php echo $log['report_no'] !== null ? htmlspecialchars($log['report_no']) : '—'; ?></td><td><span class="status-code <?php echo $status < 300 ? 'status-ok' : ($status < 500 ? 'status-warn' : 'status-error'); ?>"><?php echo $status; ?></span></td><td><?php echo htmlspecialchars($log['ip_address'] ?: '—'); ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div>
<?php endif; ?>
</div></div>
<script>
(function () {
    var toastMessage = <?php echo json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    var toastType = <?php echo json_encode($messageType === 'danger' ? 'error' : $messageType); ?>;
    if (toastMessage && window.AppToast) {
        var toastMethod = typeof AppToast[toastType] === "function" ? toastType : "info";
        AppToast[toastMethod](toastMessage);
    }

    function updateFieldCount() {
        $("#selectedFieldCount").text($(".api-fields input[type='checkbox']:checked").length);
    }

    $(".api-fields input[type='checkbox']").on("change", updateFieldCount);
    $("#selectAllFields").on("click", function () {
        $(".api-fields input[type='checkbox']").prop("checked", true).trigger("change");
    });
    $("#clearFields").on("click", function () {
        $(".api-fields input[type='checkbox']").prop("checked", false).trigger("change");
    });

    function legacyCopy(text) {
        var area = document.createElement("textarea");
        area.value = text;
        area.setAttribute("readonly", "");
        area.style.position = "fixed";
        area.style.opacity = "0";
        document.body.appendChild(area);
        area.focus();
        area.select();
        area.setSelectionRange(0, area.value.length);
        var copied = false;
        try {
            copied = document.execCommand("copy");
        } catch (error) {
            copied = false;
        }
        document.body.removeChild(area);
        return copied;
    }

    $("#copyApiKey").on("click", function () {
        var $button = $(this);
        var key = $.trim($("#newApiKey").text());
        var copyPromise = navigator.clipboard && window.isSecureContext
            ? navigator.clipboard.writeText(key).then(function () { return true; }).catch(function () { return legacyCopy(key); })
            : Promise.resolve(legacyCopy(key));

        copyPromise.then(function (copied) {
            if (copied) {
                $button.html('<i class="fa fa-check"></i> Copied');
                AppToast.success("API key copied to clipboard.");
            } else {
                AppToast.error("Copy failed. Select the API key and copy it manually.");
            }
        });
    });

    $("#revokeApiKeyForm").on("submit", function (event) {
        event.preventDefault();
        var form = this;
        AppConfirm.show("The connected verification website will stop working immediately.", {
            title: "Revoke API key?",
            confirmText: "Revoke Key",
            cancelText: "Keep Key",
            danger: true
        }).then(function (confirmed) {
            if (confirmed) {
                form.submit();
            }
        });
    });

    $("#regenerateApiKeyForm").on("submit", function (event) {
        event.preventDefault();
        var form = this;
        AppConfirm.show("The current API key will stop working as soon as the new key is created.", {
            title: "Regenerate API key?",
            confirmText: "Regenerate",
            cancelText: "Cancel",
            danger: true
        }).then(function (confirmed) {
            if (confirmed) {
                form.submit();
            }
        });
    });
})();
</script>
<?php include 'assets/footer.php'; ?>
