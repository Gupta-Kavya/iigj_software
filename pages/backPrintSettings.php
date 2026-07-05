<?php
require_once 'atm_config.php';
require_once 'db_connect.php';
auth_block_demo_action('Back print settings changes', 'back-print-settings.php');

$configFile = atm_user_file('atm-print-settings.json');
$printSettings = atm_read_json($configFile, atm_default_print_settings());
$qrSettings = atm_read_qr_settings($conn);
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $printSettings['includeBack'] = isset($_POST['include_back']);
    $printSettings['backAlignment'] = isset($_POST['back_alignment']) && $_POST['back_alignment'] === 'mirror'
        ? 'mirror' : 'same';
    $qrSettings['urlPattern'] = trim((string) ($_POST['qr_url_pattern'] ?? $qrSettings['urlPattern']));

    $uploadFields = [
        'front_image' => ['setting' => 'frontImage', 'prefix' => 'atm-front', 'label' => 'Front'],
        'back_image' => ['setting' => 'backImage', 'prefix' => 'atm-back', 'label' => 'Back'],
    ];

    foreach ($uploadFields as $fieldName => $uploadConfig) {
        if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
            $message = $uploadConfig['label'] . ' image upload did not complete.';
            $messageType = 'danger';
            break;
        } elseif ($_FILES[$fieldName]['size'] > 8 * 1024 * 1024) {
            $message = 'The image must be smaller than 8 MB.';
            $messageType = 'danger';
            break;
        }

        $imageInfo = @getimagesize($_FILES[$fieldName]['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        if (!$imageInfo || !isset($extensions[$imageInfo['mime']])) {
            $message = 'Please choose a valid JPG or PNG image.';
            $messageType = 'danger';
            break;
        }

        $extension = $extensions[$imageInfo['mime']];
        $relativePath = atm_user_asset_relative($uploadConfig['prefix'] . '.' . $extension);
        $targetPath = __DIR__ . '/' . $relativePath;
        if (!is_dir(dirname($targetPath))) {
            @mkdir(dirname($targetPath), 0775, true);
        }
        if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $targetPath)) {
            $oldImage = isset($printSettings[$uploadConfig['setting']]) ? __DIR__ . '/' . $printSettings[$uploadConfig['setting']] : '';
            if ($oldImage && strpos(basename($oldImage), $uploadConfig['prefix'] . '.') === 0 && $oldImage !== $targetPath && is_file($oldImage)) {
                @unlink($oldImage);
            }
            $printSettings[$uploadConfig['setting']] = $relativePath;
            $message = 'Card image and print settings saved.';
        } else {
            $message = 'The server could not save the uploaded image.';
            $messageType = 'danger';
            break;
        }
    }

    if (!atm_save_qr_settings($conn, $qrSettings['urlPattern'])) {
        $message = 'QR URL setting could not be saved. Please check database migration.';
        $messageType = 'danger';
    }

    if (!$message) {
        $message = 'Print settings saved.';
    }

    if ($messageType === 'success') {
        $saved = file_put_contents($configFile, json_encode($printSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        if ($saved === false) {
            $message = 'The server could not save the print settings.';
            $messageType = 'danger';
        }
    }
}

include "assets/navbar.php";
$frontImage = isset($printSettings['frontImage']) ? trim((string) $printSettings['frontImage']) : '';
$frontImagePath = $frontImage !== '' ? __DIR__ . '/' . $frontImage : '';
$backImage = isset($printSettings['backImage']) ? trim((string) $printSettings['backImage']) : '';
$backImagePath = $backImage !== '' ? __DIR__ . '/' . $backImage : '';
$frontImageUrl = ($frontImage !== '' && is_file($frontImagePath)) ? htmlspecialchars($frontImage) . '?v=' . filemtime($frontImagePath) : '';
$backImageUrl = ($backImage !== '' && is_file($backImagePath)) ? htmlspecialchars($backImage) . '?v=' . filemtime($backImagePath) : '';
?>
<style>
.atm-preview { width: 321px; height: 204px; max-width: 100%; border: 1px solid #bbb; border-radius: 8px; object-fit: cover; background: #f5f5f5; }
.atm-preview-empty{align-items:center;background:#fff;border:1px dashed #cbd5e1;border-radius:8px;color:#94a3b8;display:flex;height:204px;justify-content:center;max-width:100%;text-align:center;width:321px}
.setting-help { margin-top: 8px; color: #666; }
.preview-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
@media (max-width: 900px) { .preview-grid { grid-template-columns: 1fr; } }
</style>
<div id="page-wrapper"><div class="container-fluid">
    <div class="row"><div class="col-lg-12"><h1 class="page-header"><i class="fa fa-cog fa-fw"></i> REPORT SETTINGS</h1></div></div>
    <ul class="nav nav-tabs">
        <li><a href="settings.php">Card Builder</a></li>
        <li class="active"><a href="backPrintSettings.php">Card Images &amp; Print</a></li>
        <li><a href="a4Settings.php">A4 Builder</a></li>
        <li><a href="postcardSettings.php">Postcard Builder</a></li>
    </ul>
    <div class="panel panel-default" style="margin-top:10px;"><div class="panel-body">
        <?php if ($message): ?><div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <div class="row">
            <div class="col-md-6">
                <h4>Current ATM front</h4>
                <?php if ($frontImageUrl): ?><img class="atm-preview" id="frontPreview" src="<?php echo $frontImageUrl; ?>" alt="ATM certificate front preview"><?php else: ?><div class="atm-preview-empty" id="frontPreviewEmpty">No front image uploaded</div><img class="atm-preview" id="frontPreview" src="" alt="ATM certificate front preview" style="display:none;"><?php endif; ?>
                <h4>Current ATM back</h4>
                <?php if ($backImageUrl): ?><img class="atm-preview" id="backPreview" src="<?php echo $backImageUrl; ?>" alt="ATM certificate back preview"><?php else: ?><div class="atm-preview-empty" id="backPreviewEmpty">No back image uploaded</div><img class="atm-preview" id="backPreview" src="" alt="ATM certificate back preview" style="display:none;"><?php endif; ?>
                <p class="setting-help">The image fills the standard 85.60 × 53.98 mm card area.</p>
            </div>
            <div class="col-md-6">
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group"><label for="front_image">Choose a new front image</label>
                        <input type="file" name="front_image" id="front_image" accept="image/jpeg,image/png" class="form-control">
                        <p class="help-block">This front design is used in Card Builder preview and ATM certificate printing.</p>
                    </div>
                    <div class="form-group"><label for="back_image">Choose a new back image</label>
                        <input type="file" name="back_image" id="back_image" accept="image/jpeg,image/png" class="form-control">
                        <p class="help-block">JPG or PNG, maximum 8 MB. A 1012 × 638 image (or the same ratio) gives the cleanest result.</p>
                    </div>
                    <div class="checkbox"><label><input type="checkbox" name="include_back" value="1" <?php echo $printSettings['includeBack'] ? 'checked' : ''; ?>> Include aligned back pages in ATM PDF</label></div>
                    <div class="form-group"><label for="back_alignment">Duplex back alignment</label>
                        <select class="form-control" name="back_alignment" id="back_alignment">
                            <option value="same" <?php echo $printSettings['backAlignment'] === 'same' ? 'selected' : ''; ?>>Same grid position</option>
                            <option value="mirror" <?php echo $printSettings['backAlignment'] === 'mirror' ? 'selected' : ''; ?>>Mirror left and right columns</option>
                        </select>
                        <p class="help-block">Use “Same grid position” first. Choose mirror only if your duplex printer reverses the card columns.</p>
                    </div>
                    <div class="alert alert-info">
                        The QR verification website and API access are managed from
                        <a href="apiSettings.php"><strong>API &amp; Verification</strong></a>.
                    </div>
                    <button type="submit" class="btn btn-primary">Save Card Images &amp; Print Settings</button>
                </form>
            </div>
        </div>
    </div></div>
</div></div>
<script>
$('#front_image').on('change', function () {
    if (this.files && this.files[0]) { $('#frontPreviewEmpty').hide(); $('#frontPreview').attr('src', URL.createObjectURL(this.files[0])).show(); }
});
$('#back_image').on('change', function () {
    if (this.files && this.files[0]) { $('#backPreviewEmpty').hide(); $('#backPreview').attr('src', URL.createObjectURL(this.files[0])).show(); }
});
</script>
<?php include "assets/footer.php"; ?>
