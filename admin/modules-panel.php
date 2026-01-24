<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

/** @var PDO $db */
global $db;

$modules = [];
try {
    $modules = $db->query("SELECT * FROM modules ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    if (function_exists('sp_log')) {
        sp_log("Modules fetch error: " . $e->getMessage(), "system_error");
    }
}
?>

<div class="container py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-primary mb-0">
            <i class="fas fa-puzzle-piece me-2"></i> <span lang="module_management">Modül Yönetimi</span>
        </h4>
        <ul class="nav nav-pills" id="moduleTab" role="tablist">
            <li class="nav-item">
                <button class="nav-link active fw-bold" id="my-modules-tab" data-bs-toggle="pill"
                    data-bs-target="#my-modules" type="button"><i class="fas fa-cubes me-1"></i> <span
                        lang="installed_modules">Yüklü Modüller</span></button>
            </li>
            <li class="nav-item ms-2">
                <button class="nav-link fw-bold position-relative" id="market-tab" data-bs-toggle="pill"
                    data-bs-target="#market" type="button" onclick="loadMarket('module')">
                    <i class="fas fa-shopping-bag me-1"></i> <span lang="marketplace">Market</span>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                        id="market-count" style="display:none">0</span>
                </button>
            </li>
        </ul>
    </div>

    <div class="tab-content" id="moduleTabContent">
        <!-- My Modules Tab -->
        <div class="tab-pane fade show active" id="my-modules" role="tabpanel">
            <!-- Upload Card -->
            <div class="card shadow-sm border-0 mb-4 rounded-4">
                <div class="card-body p-4">
                    <form id="uploadModuleForm" onsubmit="uploadModule(event)">
                        <label class="form-label fw-bold"><i class="fas fa-file-archive me-1"></i> <span
                                lang="upload_module_zip">Modül Yükle (.zip)</span></label>
                        <div class="input-group">
                            <input type="file" name="module_zip" class="form-control" required accept=".zip">
                            <button type="submit" class="btn btn-primary px-4" lang="upload">
                                <i class="fas fa-upload me-1"></i> Yükle
                            </button>
                        </div>
                        <div class="form-text mt-2 text-muted">module.json veya theme.json içeren .zip dosyaları.</div>
                    </form>
                </div>
            </div>

            <!-- Modules List -->
            <div class="card shadow border-0 rounded-4 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-secondary">
                            <tr>
                                <th class="ps-4" lang="id">ID</th>
                                <th lang="module_info">Modül Bilgisi</th>
                                <th lang="sluglabel">Slug</th>
                                <th lang="version">Versiyon</th>
                                <th lang="status">Durum</th>
                                <th class="text-end pe-4" lang="tabislem">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($modules)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted" lang="no_data">Veri yok</td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($modules as $m): ?>
                                <tr>
                                    <td class="ps-4 text-muted">#<?= e((string) $m['id']) ?></td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold text-dark"><?= e($m['title'] ?? $m['name']) ?></span>
                                            <small class="text-muted"><?= e($m['description']) ?></small>
                                        </div>
                                    </td>
                                    <td><span
                                            class="badge bg-light text-dark border font-monospace"><?= e($m['page_slug']) ?></span>
                                    </td>
                                    <td><span
                                            class="badge bg-secondary rounded-pill">v<?= e($m['version'] ?? '1.0') ?></span>
                                    </td>
                                    <td>
                                        <?php if ($m['is_active']): ?>
                                            <span
                                                class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3"
                                                lang="active">Aktif</span>
                                        <?php else: ?>
                                            <span
                                                class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3"
                                                lang="passive">Pasif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group">
                                            <button
                                                class="btn btn-sm <?= $m['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?> rounded-pill me-2 px-3"
                                                onclick="toggleModule(<?= $m['id'] ?>)">
                                                <?php if ($m['is_active']): ?>
                                                    <i class="fas fa-pause me-1"></i> <span lang="disable_module">Devre
                                                        Dışı</span>
                                                <?php else: ?>
                                                    <i class="fas fa-play me-1"></i> <span
                                                        lang="enable_module">Etkinleştir</span>
                                                <?php endif; ?>
                                            </button>

                                            <?php if (isset($is_admin) && $is_admin): ?>
                                                <button class="btn btn-sm btn-outline-danger rounded-pill px-3"
                                                    onclick="deleteModule(<?= $m['id'] ?>, '<?= e($m['name']) ?>')">
                                                    <i class="fas fa-trash-alt me-1"></i> <span lang="delete">Sil</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
                                    placeholder="<?= e(__('search_market')) ?>" onkeyup="filterMarket('module')">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select id="filterAuthor" class="form-select" onchange="filterMarket('module')">
                                <option value="all" lang="all">Tüm Yazarlar</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="filterSort" class="form-select" onchange="filterMarket('module')">
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
</div>

<script>
    const csrfToken = '<?= $_SESSION['csrf'] ?>';

    function uploadModule(e) {
        e.preventDefault();
        let formData = new FormData(document.getElementById('uploadModuleForm'));
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
                    alert('Error: ' + res.message);
                }
            })
            .catch(err => alert('Upload failed: ' + err));
    }

    function toggleModule(id) {
        let formData = new FormData();
        formData.append('action', 'toggle');
        formData.append('id', id);
        formData.append('csrf', csrfToken);

        fetch('modul-func.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    location.reload();
                } else {
                    alert(res.message);
                }
            });
    }

    function deleteModule(id, name) {
        if (!confirm("<?= __('confirm_module_delete') ?> (" + name + ")")) return;

        let formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        formData.append('csrf', csrfToken);

        fetch('modul-func.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    alert(res.message);
                    location.reload();
                } else {
                    alert(res.message);
                }
            });
    }
</script>