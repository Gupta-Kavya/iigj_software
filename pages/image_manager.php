<?php include "assets/navbar.php"; ?>
<link rel="stylesheet" href="../css/image_manager.css">

<div id="page-wrapper">
    <div class="container-fluid image-manager-page">
        <div class="image-hero">
            <div>
                <h1><i class="fa fa-picture-o fa-fw"></i> Image Manager</h1>
                <p>Upload, review and delete report images for your branch. Choose the folder you want to manage.</p>
            </div>
        </div>

        <div class="image-grid-layout">
            <div class="image-card upload-card">
                <div class="image-card-head">
                    <div class="image-card-icon"><i class="fa fa-cloud-upload"></i></div>
                    <div>
                        <h3>Upload Images</h3>
                        <p>Use JPG/JPEG images. Certificate images should be named like <strong>12.jpg</strong>; symbol images can use the symbol name.</p>
                    </div>
                </div>
                <form id="image_upload_form" enctype="multipart/form-data">
                    <label for="select_file" class="drop-zone">
                        <i class="fa fa-upload"></i>
                        <span class="drop-title">Choose images to upload</span>
                        <span class="drop-help">You can select up to 100 JPG images at once.</span>
                    </label>
                    <input type="file" id="select_file" multiple accept="image/jpeg,image/jpg" />
                </form>
            </div>

            <div class="image-card tools-card">
                <div class="image-card-head">
                    <div class="image-card-icon"><i class="fa fa-sliders"></i></div>
                    <div>
                        <h3>Manage Images</h3>
                        <p>Search, select and remove old stone images from your account.</p>
                    </div>
                </div>
                <div class="tool-row">
                    <select id="image_folder" class="form-control image-folder">
                        <option value="st_images">Stone Images</option>
                        <option value="symbol_images">Symbol Images</option>
                        <option value="clarity_images">Clarity Images</option>
                        <option value="proportion_images">Proportion Images</option>
                    </select>
                    <input type="text" id="image_search" class="form-control image-search" placeholder="Search image name...">
                </div>
                <div class="tool-actions">
                    <button class="btn btn-light-soft" id="checkAllBtn" type="button">Select All</button>
                    <button class="btn btn-light-soft" id="clearSelectionBtn" type="button">Clear</button>
                </div>
                <div class="image-stat">
                    <span id="image_count">0 images</span>
                    <span id="selected_count">0 selected</span>
                </div>
            </div>
        </div>

        <form id="image_delete_form">
            <div class="image-card gallery-card">
                <div class="gallery-header">
                    <div>
                        <h3>Directory Images</h3>
                        <p>File names only for faster loading. Double-click a row to preview, download, or delete.</p>
                    </div>
                    <button type="submit" name="delete" id="deleteSelectedBtn" class="btn btn-danger-soft" disabled>
                        <i class="fa fa-trash"></i> Delete Selected
                    </button>
                </div>
                <div id="image_files" class="image-list">
                    <div class="image-empty">Loading images...</div>
                </div>
            </div>
        </form>

        <div id="image_preview_modal" class="modal fade modern-modal image-preview-modal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog image-preview-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
                        <h4 class="modal-title" id="previewImageTitle"><i class="fa fa-file-image-o"></i> Image Preview</h4>
                    </div>
                    <div class="modal-body image-preview-body">
                        <img id="previewImage" src="" alt="" />
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" id="previewDownloadBtn"><i class="fa fa-download"></i> Download</button>
                        <button type="button" class="btn btn-danger" id="previewDeleteBtn"><i class="fa fa-trash"></i> Delete</button>
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="file_upload_progress" class="modal" role="dialog" style="vertical-align: middle;">
            <div class="modal-dialog">
                <div class="modal-content modern-modal">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-cloud-upload"></i> Uploading Images</h4>
                    </div>
                    <div class="modal-body">
                        <div class="progress" id="progress_bar" style="display:none;">
                            <div class="progress-bar" id="progress_bar_process" role="progressbar" style="width:0%">0%</div>
                        </div>
                        <div id="uploaded_image"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <script src="../js/image_manager.js"></script>

        <?php include "assets/footer.php"; ?>
