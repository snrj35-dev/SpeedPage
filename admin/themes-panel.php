<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

// Helper function to get available themes
function get_available_themes()
{
    $themes = [];
    $themeDir = ROOT_DIR . 'themes/';
    if (!is_dir($themeDir))
        return $themes;

    $folders = scandir($themeDir);
    foreach ($folders as $folder) {
        if ($folder === '.' || $folder === '..')
            continue;

        $jsonPath = $themeDir . $folder . '/theme.json';
        if (file_exists($jsonPath)) {
            $data = json_decode(file_get_contents($jsonPath), true);
            $data['folder'] = $folder; // Store folder name
            $themes[$folder] = $data;
        }
    }
    return $themes;
}

$themes = get_available_themes();
// ✅ Admin always looks at system-wide active theme for the "Active" badge/button
$activeTheme = $settings['active_theme'] ?? 'default';

?>

<div class="container">
    <h4 class="mb-3"><i class="fas fa-palette"></i> <span lang="themes">Tema Yönetimi</span></h4>

    <!-- Theme Upload -->
    <div class="card mb-4">
        <div class="card-body">
            <h6 class="card-title" lang="theme_upload_zip"><?= __('theme_upload_zip') ?></h6>
            <form id="uploadThemeForm" onsubmit="uploadTheme(event)">
                <div class="input-group">
                    <input type="file" class="form-control" name="module_zip" required>
                    <button class="btn btn-outline-primary" type="submit" lang="upload"><?= __('upload') ?></button>
                </div>
                <small class="text-muted" lang="theme_upload_help"><?= __('theme_upload_help') ?></small>
            </form>
        </div>
    </div>

    <!-- Theme List -->
    <div class="row">
        <?php foreach ($themes as $key => $t): ?>
            <div class="col-md-4 mb-4">
                <div class="card <?= ($activeTheme === $key) ? 'border-primary shadow' : '' ?> h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title mb-0"><?= e($t['title'] ?? $t['name']) ?></h5>
                            <?php if ($activeTheme === $key): ?>
                                <span class="badge bg-primary px-2 py-1" style="font-size:0.7em"
                                    lang="active_badge"><?= __('active_badge') ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="card-text text-muted small mb-2">
                            v<?= e($t['version']) ?> • <?= e($t['author'] ?? 'Bilinmiyor') ?>
                        </p>
                        <p class="card-text small mb-4"><?= e($t['description'] ?? '') ?></p>

                        <div class="d-grid gap-2">
                            <?php
                            $is_system_active = ($key === ($settings['active_theme'] ?? 'default'));
                            if (!$is_system_active): ?>
                                <button class="btn btn-sm btn-outline-success" onclick="activateTheme('<?= $key ?>')"
                                    lang="activate"><?= __('activate') ?></button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-success" disabled>
                                    <i class="fas fa-check-circle me-1"></i> <span
                                        lang="currently_using"><?= __('currently_using') ?></span>
                                </button>
                            <?php endif; ?>

                            <?php if (!empty($t['has_settings'])): ?>
                                <a href="index.php?page=theme-settings&t=<?= e($key) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-cog me-1"></i> <span lang="settings"><?= __('settings') ?></span>
                                </a>
                            <?php endif; ?>

                            <?php if ($key === 'default'): ?>
                                <button class="btn btn-sm btn-outline-info" onclick="copyTheme('<?= $key ?>')">
                                    <i class="fas fa-copy me-1"></i> <span lang="copy_default">Varsayılanı Kopyala</span>
                                </button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteTheme('<?= $key ?>')">
                                    <i class="fas fa-trash-alt me-1"></i> <span lang="delete">Sil</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Simple Modal for Theme Copy -->
<div class="modal fade" id="themeCopyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" lang="create_new_theme">Yeni Tema Oluştur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" lang="theme_folder_name">Tema Klasör İsmi (slug)</label>
                    <input type="text" id="newThemeSlug" class="form-control" data-placeholder="theme_slug_placeholder">
                </div>
                <div class="mb-3">
                    <label class="form-label" lang="theme_display_name">Tema Başlığı (Görünen İsim)</label>
                    <input type="text" id="newThemeTitle" class="form-control"
                        data-placeholder="theme_title_placeholder">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" lang="vazgecland">İptal</button>
                <button type="button" class="btn btn-primary" onclick="confirmCopyTheme()" lang="copy">Kopyala</button>
            </div>
        </div>
    </div>
</div>


<script>
    function uploadTheme(e) {
        e.preventDefault();
        let formData = new FormData(document.getElementById('uploadThemeForm'));
        formData.append('action', 'upload');
        formData.append('csrf', '<?= $_SESSION['csrf'] ?>'); // Basic CSRF protection injection since we are in PHP context

        // Use fetch or existing logic. Since modul-func wraps both, we use it.
        // NOTE: modul-func checks for "module_zip" name in file input.

        fetch('modul-func.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    alert(res.message);
                    location.reload();
                } else {
                    alert("Hata: " + res.message);
                }
            })
            .catch(err => alert("Yükleme hatası"));
    }

    function copyTheme(source) {
        let modal = new bootstrap.Modal(document.getElementById('themeCopyModal'));
        modal.show();
    }

    function confirmCopyTheme() {
        let newName = document.getElementById('newThemeSlug').value;
        let newTitle = document.getElementById('newThemeTitle').value;

        if (!newName) return alert("<?= __('enter_theme_slug') ?>");

        let formData = new FormData();
        formData.append('action', 'duplicate_theme');
        formData.append('source', 'default');
        formData.append('new_name', newName);
        formData.append('new_title', newTitle);
        formData.append('csrf', '<?= $_SESSION['csrf'] ?>');

        fetch('modul-func.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    alert(res.message);
                    location.reload();
                } else {
                    alert("Hata: " + res.message);
                }
            });
    }

    function deleteTheme(themeName) {
        if (!confirm(themeName + " <?= __('confirm_theme_delete_title') ?>")) return;

        let formData = new FormData();
        formData.append('action', 'delete_theme');
        formData.append('theme_name', themeName);
        formData.append('csrf', '<?= $_SESSION['csrf'] ?>');

        fetch('modul-func.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    alert(res.message);
                    location.reload();
                } else {
                    alert("Hata: " + res.message);
                }
            });
    }

    function activateTheme(themeName) {
        if (!confirm("<?= __('confirm_theme_activate') ?>")) return;

        let formData = new FormData();
        formData.append('action', 'activate_theme');
        formData.append('theme_name', themeName);
        formData.append('csrf', '<?= $_SESSION['csrf'] ?>');

        fetch('modul-func.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    alert(res.message);
                    location.reload();
                } else {
                    alert("Hata: " + res.message);
                }
            });
    }
</script>