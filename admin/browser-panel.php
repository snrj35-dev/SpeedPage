<?php
declare(strict_types=1);

if (!defined('ROOT_DIR')) {
    // Parent should handle loading
    if (file_exists(__DIR__ . '/auth.php'))
        require_once __DIR__ . '/auth.php';
    if (file_exists(__DIR__ . '/../settings.php'))
        require_once __DIR__ . '/../settings.php';
}
?>
<div class="row align-items-center mb-4">
    <div class="col-md-6">
        <h4 class="fw-bold"><i class="fa fa-folder-tree text-primary me-2"></i><span
                lang="filebrowser"><?= __('filebrowser') ?></span></h4>
        <!-- Breadcrumb JS tarafından doldurulur -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb" id="br-breadcrumb"></ol>
        </nav>
    </div>
    <div class="col-md-6 text-md-end">
        <button class="btn btn-primary btn-sm shadow-sm" onclick="$('#br-upload').click()">
            <i class="fa fa-upload me-1"></i><span lang="br_upload_btn"><?= __('br_upload_btn') ?></span>
        </button>
        <button class="btn btn-success btn-sm shadow-sm" onclick="brCreateFile()">
            <i class="fa fa-plus me-1"></i><span lang="br_new_file"><?= __('br_new_file') ?></span>
        </button>
        <button class="btn btn-warning btn-sm text-white shadow-sm" onclick="brCreateFolder()">
            <i class="fa fa-folder-plus me-1"></i><span lang="br_new_folder"><?= __('br_new_folder') ?></span>
        </button>
        <input type="file" id="br-upload" hidden multiple>
    </div>
</div>

<div class="row g-3" id="br-grid"></div>

<?php if (!defined('NO_BROWSER_EDITOR')): ?>
    <div class="modal fade" id="br-modal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content shadow-lg border-0">
                <div id="br-editor-container"></div>
            </div>
        </div>
    </div>
<?php endif; ?>