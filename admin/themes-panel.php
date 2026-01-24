<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

/** @var PDO $db */
global $db;

/**
 * Get available themes from the themes directory.
 * @return array
 */
function get_available_themes(): array
{
    $themes = [];
    $themeDir = ROOT_DIR . 'themes/';
    if (!is_dir($themeDir)) {
        return $themes;
    }

    $folders = scandir($themeDir);
    if ($folders === false) {
        return $themes;
    }

    foreach ($folders as $folder) {
        if ($folder === '.' || $folder === '..') {
            continue;
        }

        $jsonPath = $themeDir . $folder . '/theme.json';
        if (file_exists($jsonPath)) {
            $content = file_get_contents($jsonPath);
            if ($content) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $data['folder'] = $folder;
                    $themes[$folder] = $data;
                }
            }
        }
    }
    return $themes;
}

$themes = get_available_themes();
// Admin always looks at system-wide active theme
$activeTheme = $settings['active_theme'] ?? 'default';
?>

<div class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 fw-bold text-primary"><i class="fas fa-palette me-2"></i> <span lang="themes">Tema
                Yönetimi</span></h4>
        <ul class="nav nav-pills" id="themeTab" role="tablist">
            <li class="nav-item">
                <button class="nav-link active fw-bold" id="my-themes-tab" data-bs-toggle="pill"
                    data-bs-target="#my-themes" type="button"><i class="fas fa-th-large me-1"></i> <span
                        lang="my_themes">Temalarım</span></button>
            </li>
            <li class="nav-item ms-2">
                <button class="nav-link fw-bold position-relative" id="market-tab" data-bs-toggle="pill"
                    data-bs-target="#market" type="button" onclick="loadMarket('theme')">
                    <i class="fas fa-shopping-cart me-1"></i> <span lang="marketplace">Market</span>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                        id="market-count" style="display:none">0</span>
                </button>
            </li>
        </ul>
    </div>

    <div class="tab-content" id="themeTabContent">
        <!-- My Themes Tab -->
        <div class="tab-pane fade show active" id="my-themes" role="tabpanel">
            <!-- Theme Upload -->
            <div class="card shadow-sm border-0 mb-4 rounded-4">
                <div class="card-body p-4">
                    <h6 class="card-title fw-bold mb-3" lang="theme_upload_zip">Tema Yükle (.zip)</h6>
                    <form id="uploadThemeForm" onsubmit="uploadTheme(event)">
                        <div class="input-group">
                            <input type="file" class="form-control" name="module_zip" required accept=".zip">
                            <button class="btn btn-primary" type="submit" lang="upload">Yükle</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Existing Theme List Content -->
            <!-- Theme List -->
            <div class="row">
                <?php foreach ($themes as $key => $t): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card <?= ($activeTheme === $key) ? 'border-primary shadow' : '' ?> h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-0"><?= e($t['title'] ?? $t['name'] ?? $key) ?></h5>
                                    <?php if ($activeTheme === $key): ?>
                                        <span class="badge bg-primary px-2 py-1" style="font-size:0.7em"
                                            lang="active_badge">Aktif</span>
                                    <?php endif; ?>
                                </div>
                                <p class="card-text text-muted small mb-2">
                                    v<?= e($t['version'] ?? '1.0') ?> • <?= e($t['author'] ?? 'Bilinmiyor') ?>
                                </p>
                                <p class="card-text small mb-4"><?= e($t['description'] ?? '') ?></p>

                                <div class="d-grid gap-2">
                                    <?php
                                    $is_system_active = ($key === ($settings['active_theme'] ?? 'default'));
                                    if (!$is_system_active): ?>
                                        <button class="btn btn-sm btn-outline-success" onclick="activateTheme('<?= e($key) ?>')"
                                            lang="activate">Aktifleştir</button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-success" disabled>
                                            <i class="fas fa-check-circle me-1"></i> <span
                                                lang="currently_using">Kullanılıyor</span>
                                        </button>
                                    <?php endif; ?>

                                    <?php if (!empty($t['has_settings'])): ?>
                                        <a href="index.php?page=theme-settings&t=<?= e($key) ?>"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-cog me-1"></i> <span lang="settings">Ayarlar</span>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($key === 'default'): ?>
                                        <button class="btn btn-sm btn-outline-info" onclick="copyTheme('<?= e($key) ?>')">
                                            <i class="fas fa-copy me-1"></i> <span lang="copy_default">Varsayılanı
                                                Kopyala</span>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteTheme('<?= e($key) ?>')">
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
    </div>

    <!-- Market Tab -->
    <div class="tab-pane fade" id="market" role="tabpanel">
        <div class="card shadow-sm border-0 mb-4 rounded-4">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i
                                    class="fas fa-search text-muted"></i></span>
                            <input type="text" id="marketSearch" class="form-control border-start-0 ps-0"
                                placeholder="<?= e(__('search_market')) ?>" onkeyup="filterMarket('theme')">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="filterAuthor" class="form-select" onchange="filterMarket('theme')">
                            <option value="all" lang="all">Tüm Yazarlar</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="filterSort" class="form-select" onchange="filterMarket('theme')">
                            <option value="newest" lang="newest">En Yeni</option>
                            <option value="featured" lang="featured">Öne Çıkan</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div id="market-list" class="row">
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
            </div>
        </div>
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
                    <input type="text" id="newThemeSlug" class="form-control"
                        placeholder="<?= e(__('theme_slug_placeholder')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" lang="theme_display_name">Tema Başlığı (Görünen İsim)</label>
                    <input type="text" id="newThemeTitle" class="form-control"
                        placeholder="<?= e(__('theme_title_placeholder')) ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" lang="cancel">İptal</button>
                <button type="button" class="btn btn-primary" onclick="confirmCopyTheme()" lang="copy">Kopyala</button>
            </div>
        </div>
    </div>
</div>


<script>
    const csrfToken = '<?= $_SESSION['csrf'] ?>';

    function uploadTheme(e) {
        e.preventDefault();
        let formData = new FormData(document.getElementById('uploadThemeForm'));
        formData.append('action', 'upload');
        formData.append('csrf', csrfToken);

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
                    alert("<?= __('upload_error') ?>: " + res.message);
                }
            })
            .catch(err => alert("<?= __('upload_error') ?>"));
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
        formData.append('csrf', csrfToken);

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
                    alert("<?= __('operation_failed') ?>: " + res.message);
                }
            });
    }

    function deleteTheme(themeName) {
        if (!confirm(themeName + " <?= __('confirm_theme_delete_title') ?>")) return;

        let formData = new FormData();
        formData.append('action', 'delete_theme');
        formData.append('theme_name', themeName);
        formData.append('csrf', csrfToken);

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
                    alert("<?= __('operation_failed') ?>: " + res.message);
                }
            });
    }

    function activateTheme(themeName) {
        if (!confirm("<?= __('confirm_theme_activate') ?>")) return;

        let formData = new FormData();
        formData.append('action', 'activate_theme');
        formData.append('theme_name', themeName);
        formData.append('csrf', csrfToken);

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
                    alert("<?= __('operation_failed') ?>: " + res.message);
                }
            });
    }
</script>