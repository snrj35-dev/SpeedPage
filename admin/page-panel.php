<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

/** @var PDO $db */
global $db;

try {
    $allowPagePhp = false;
    $pages = $db->query("SELECT * FROM pages ORDER BY sort_order, slug")->fetchAll(PDO::FETCH_ASSOC);

    // Menüleri Çek
    $menus = $db->query("
    SELECT m.*, p.slug
    FROM menus m
    LEFT JOIN pages p ON p.id = m.page_id
    ORDER BY m.sort_order
")->fetchAll(PDO::FETCH_ASSOC);

    $locRows = $db->query("SELECT menu_id, location FROM menu_locations")->fetchAll(PDO::FETCH_ASSOC);
    $menuLocations = [];
    foreach ($locRows as $r) {
        $menuLocations[$r['menu_id']][] = $r['location'];
    }
    // Custom Snippets
    $customSnippets = $db->query("SELECT * FROM custom_snippets ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);
    try {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $keyColumn = ($driver === 'mysql') ? '`key`' : 'key';
        $stmtPhp = $db->prepare("SELECT value FROM settings WHERE {$keyColumn} = ? LIMIT 1");
        $stmtPhp->execute(['allow_page_php']);
        $allowPagePhp = ((string) $stmtPhp->fetchColumn() === '1');
    } catch (Throwable) {
        $allowPagePhp = false;
    }
    ?>
    <script>
        var GLOBAL_SNIPPETS = <?= json_encode($customSnippets, JSON_THROW_ON_ERROR) ?>;
    </script>
    <?php
} catch (Throwable $e) {
    if (function_exists('sp_log')) {
        sp_log("Page Panel Error: " . $e->getMessage(), 'error');
    }
    $pages = [];
    $menus = [];
    $menuLocations = [];
    $customSnippets = [];
    $allowPagePhp = false;
}

// Asset Taraması
$cssFiles = glob(__DIR__ . '/../cdn/css/*.css') ?: [];
$jsFiles = glob(__DIR__ . '/../cdn/js/*.js') ?: [];
?>

<div class="page-panel">
    <div class="row align-items-center mb-4">
        <div class="col">
            <h4><i class="fas fa-folder-open"></i> <span lang="content_management"><?= __('content_management') ?></span></h4>
        </div>
        <div class="col-auto">
            <div class="input-group" style="max-width: 250px;">
                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="pageListSearch" class="form-control border-start-0 ps-0 form-control-sm" placeholder="<?= __('search') ?>" lang="search">
            </div>
        </div>
        <div class="col-auto">
            <div class="btn-group shadow-sm">
                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#newSection">
                    <i class="fas fa-plus"></i> <span lang="newpage"></span>
                </button>
                <button class="btn btn-success btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#newMenuSection">
                    <i class="fas fa-stream"></i> <span lang="new_menu"><?= __('new_menu') ?></span>
                </button>
                <button class="btn btn-warning btn-sm text-white" type="button" data-bs-toggle="modal" data-bs-target="#snippetModal">
                    <i class="fas fa-code"></i> <span lang="snippet_management">Snippet</span>
                </button>
            </div>
        </div>
    </div>

    <!-- YENİ MENÜ EKLEME PANELİ -->
    <div class="collapse mb-4" id="newMenuSection">
        <div class="card p-4 shadow-sm border-0">
            <h5 class="mb-3 text-success"><i class="fas fa-stream"></i> <span lang="add_to_menu">Yeni Menü Ekle</span>
            </h5>
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label small text-muted" lang="title"><?= __('title') ?></label>
                    <input id="m_title" class="form-control" data-placeholder="menu_title_placeholder" placeholder="<?= __('menu_title_placeholder') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted" lang="icon">İkon</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-icons" id="m_icon_preview"></i></span>
                        <input id="m_icon" class="form-control check-icon" placeholder="fas fa-home">
                        <button class="btn btn-outline-secondary icon-picker-btn" type="button"
                            data-target="#m_icon">🔍</button>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted" lang="page_singular">Sayfa</label>
                    <select id="m_page" class="form-select">
                        <option value="">(Harici Link)</option>
                        <?php foreach ($pages as $p): ?>
                            <option value="<?= e((string) $p['id']) ?>"><?= e($p['slug']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted" lang="external_url_placeholder">Harici URL</label>
                    <input id="m_url" class="form-control" placeholder="https://...">
                </div>
                <div class="col-md-1">
                    <label class="form-label small text-muted" lang="order"><?= __('order') ?></label>
                    <input id="m_order" type="number" class="form-control" value="1">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button class="btn btn-success w-100" onclick="MenuManager.add()"><i
                            class="fas fa-save"></i></button>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-12">
                    <label class="form-label small text-muted me-2" lang="menu_locations">Görünecek Yerler:</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="m_loc_nav" value="navbar" checked>
                        <label class="form-check-label" for="m_loc_nav" lang="navbar">Navbar</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="m_loc_footer" value="footer">
                        <label class="form-check-label" for="m_loc_footer" lang="footer">Footer</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="m_loc_sidebar" value="sidebar">
                        <label class="form-check-label" for="m_loc_sidebar" lang="sidebar">Sidebar</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="newSection" class="collapse card shadow border-left-primary mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-plus-circle me-1"></i> <span
                    lang="new_create_page"></span></h6>
            <ul class="nav nav-pills card-header-pills" id="pills-tab" role="tablist">
                <li class="nav-item"><a class="nav-link active py-1 px-3" data-bs-toggle="pill" href="#tab-genel"
                        lang="pagegenel"></a></li>
                <li class="nav-item"><a class="nav-link py-1 px-3" data-bs-toggle="pill" href="#tab-tasarim"
                        lang="pagetasarim"></a></li>
                <li class="nav-item"><a class="nav-link py-1 px-3" data-bs-toggle="pill" href="#tab-menu"
                        lang="pagemenu"></a></li>
            </ul>
        </div>
        <div class="card-body">
            <form id="sayfaOlusturFormu" action="page-actions.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                <input type="hidden" name="mode" value="create">

                <div class="tab-content" id="pills-tabContent">
                    <div class="tab-pane fade show active" id="tab-genel" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" lang="sluglabel">Slug (URL)</label>
                                <div class="input-group"><span class="input-group-text">/</span><input type="text"
                                        name="slug" class="form-control" placeholder="sayfa-adi" required></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" lang="title">Sayfa Başlığı</label>
                                <input type="text" name="title" class="form-control" placeholder="Görünecek Başlık">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" lang="description">Açıklama (SEO)</label>
                            <input type="text" name="description" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" lang="featured_image">Öne Çıkan Görsel</label>
                            <div class="input-group">
                                <input type="text" name="featured_image" id="new_featured_image"
                                    class="form-control" placeholder="URL...">
                                <button type="button" class="btn btn-outline-secondary"
                                    onclick="openMediaBrowser('new_featured_image', 'media/galeri')">
                                    <i class="fas fa-folder-open"></i> <span lang="select">Seç</span>
                                </button>
                            </div>
                            <div id="new_image_preview_container" class="mt-2" style="display:none;">
                                <img src="" id="new_image_preview" class="img-thumbnail shadow-sm"
                                    style="max-height: 150px;">
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0" style="display:none;"></label>
                                <select class="form-select form-select-sm shadow-sm rounded-pill px-3 ms-auto"
                                    onchange="insertSnippetFromGlobal(this.value); this.selectedIndex=0;"
                                    style="width: auto; min-width: 180px;">
                                    <option value=""><i class="fas fa-layer-group me-1"></i> Hızlı Bileşen Ekle</option>
                                    <optgroup label="Layout & Izgara">
                                        <option value="grid2">2 Sütun (50/50)</option>
                                        <option value="grid3">3 Sütun</option>
                                        <option value="card">Modern Kart (Shadow)</option>
                                    </optgroup>

                                    <optgroup label="İnteraktif Bileşenler">
                                        <option value="accordion">Açılır Panel (Accordion)</option>
                                        <option value="list_group">Rozetli Liste Grubu</option>
                                    </optgroup>

                                    <optgroup label="Medya & Durum">
                                        <option value="responsive_video">YouTube Video (16:9)</option>
                                        <option value="progress">İlerleme Çubuğu</option>
                                        <option value="spinner">Yükleme İkonu</option>
                                        <option value="badge">Yeni Etiketi</option>
                                        <option value="alert">Bilgi Kutusu (Alert)</option>
                                    </optgroup>

                                    <optgroup label="Sistem (Hooks)">
                                        <option value="hook_header">Tema Üst Bilgi (Header)</option>
                                        <option value="hook_footer">Tema Alt Bilgi (Footer)</option>
                                    </optgroup>
                                    <?php if (!empty($customSnippets)): ?>
                                        <optgroup label="Özel Snippets">
                                            <?php foreach ($customSnippets as $cs): ?>
                                                <option value="<?= $cs['id'] ?>"><?= e($cs['title']) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <?php if (empty($allowPagePhp)): ?>
                            <div class="alert alert-warning mt-3">
                                <strong>Güvenlik Modu:</strong> Bu alanda PHP kodu kaydedilemez.
                            </div>
                        <?php endif; ?>
                        <textarea name="icerik" id="new_content" rows="18" class="form-control code-font shadow-sm"
                            style="font-family: 'Fira Code', monospace; border-radius: 12px; border: 1px solid #e0e0e0;"></textarea>
                    </div>

                    <div class="tab-pane fade" id="tab-tasarim" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" lang="icon_label">İkon (FontAwesome)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-icons"
                                            id="main_icon_preview"></i></span>
                                    <input type="text" name="icon" id="main_icon_input" class="form-control check-icon"
                                        placeholder="fas fa-home">
                                    <button class="btn btn-outline-secondary icon-picker-btn" type="button"
                                        data-target="#main_icon_input">🔍 Seç</button>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" lang="active">Durum</label>
                                <select name="aktif" class="form-select">
                                    <option value="1" selected lang="active">Aktif</option>
                                    <option value="0" lang="passive">Pasif</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <!-- CSS Seçimi -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">CSS Dosyaları</label>
                                <div class="card p-2" style="max-height: 150px; overflow-y: auto;">
                                    <?php foreach ($cssFiles as $f):
                                        $c = basename($f); ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="assets_css[]"
                                                value="<?= $c ?>" id="css_<?= $c ?>">
                                            <label class="form-check-label" for="css_<?= $c ?>"><?= $c ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="text" name="custom_css" class="form-control mt-2"
                                    placeholder="Özel CSS Yolu (virgülle ayır)">
                            </div>

                            <!-- JS Seçimi -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">JS Dosyaları</label>
                                <div class="card p-2" style="max-height: 150px; overflow-y: auto;">
                                    <?php foreach ($jsFiles as $f):
                                        $j = basename($f); ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="assets_js[]"
                                                value="<?= $j ?>" id="js_<?= $j ?>">
                                            <label class="form-check-label" for="js_<?= $j ?>"><?= $j ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="text" name="custom_js" class="form-control mt-2"
                                    placeholder="Özel JS Yolu (virgülle ayır)">
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-menu" role="tabpanel">
                        <div class="menu-box p-4">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="addToMenu" name="add_to_menu"
                                    value="1" style="width: 40px; height: 20px;">
                                <label class="form-check-label ms-2 fw-bold" for="addToMenu" lang="add_to_menu">Menüye
                                    Ekle</label>
                            </div>
                            <div id="addToMenuFields" style="display:none;" class="row border-top pt-3 mt-3">
                                <div class="col-md-4 mb-3"><label class="form-label" lang="menu_title_placeholder">Menü
                                        Adı</label><input type="text" name="menu_title" class="form-control"></div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label" lang="menu_icon_placeholder">Menü İkonu</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-icons"
                                                id="menu_icon_preview"></i></span>
                                        <input type="text" name="menu_icon" id="menu_icon_input"
                                            class="form-control check-icon">
                                        <button class="btn btn-outline-secondary icon-picker-btn" type="button"
                                            data-target="#menu_icon_input">🔍</button>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3"><label class="form-label"
                                        lang="menu_order">Sıralama</label><input type="number" name="menu_order"
                                        class="form-control" value="1"></div>
                                <div class="col-12"><label class="form-label" lang="menu_locations">Görünecek
                                        Yerler</label>
                                    <div class="d-flex flex-wrap gap-3 mt-2">
                                        <div class="form-check"><input class="form-check-input" type="checkbox"
                                                name="menu_locations[]" value="navbar" id="loc_nav" checked><label
                                                class="form-check-label" for="loc_nav">Navbar</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox"
                                                name="menu_locations[]" value="footer" id="loc_footer"><label
                                                class="form-check-label" for="loc_footer">Footer</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox"
                                                name="menu_locations[]" value="sidebar" id="loc_sidebar"><label
                                                class="form-check-label" for="loc_sidebar">Sidebar</label></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 border-top pt-3 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary px-5 shadow-sm"><i class="fas fa-save me-2"></i> <span
                            lang="createlang"></span></button>
                </div>
        </form>
        <div id="sonucMesaji" class="mt-3"></div>
    </div>
</div>

<div class="card">
    <div class="card-header py-3">
        <h6 class="m-0"><i class="fas fa-list"></i> <span lang="pageslist"></span></h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle m-0">
                <thead>
                    <tr>
                        <th width="50" class="text-center">#</th>
                        <th lang="sluglabel">Slug</th>
                        <th lang="title">Başlık</th>
                        <th lang="active" class="text-center">Durum</th>
                        <th class="text-end" style="padding-right: 20px;" lang="pageislem">İşlemler</th>
                    </tr>
                </thead>
                <tbody id="pageTableBody">
                    <!-- Dinamik olarak dolacak -->
                    <tr>
                        <td colspan="5" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status"></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<nav id="pagePagination" class="d-flex justify-content-center mt-3"></nav>

<!-- MENÜ LİSTESİ -->
<div class="card mt-4 border-left-success">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 text-success"><i class="fas fa-stream"></i> <span lang="menu_management"><?= __('menu_management') ?></span></h6>
        <small class="text-muted" lang="drag_drop_soon"><?= __('drag_drop_soon') ?></small>
    </div>
    <div class="card-body p-3">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead class="table-light">
                    <tr>
                        <th lang="icon_title">İkon/Başlık</th>
                        <th lang="link">Bağlantı</th>
                        <th lang="order">Sıra</th>
                        <th lang="location">Konum</th>
                        <th lang="status">Durum</th>
                        <th lang="action">İşlem</th>
                    </tr>
                </thead>
                <tbody id="menuTableBody">
                    <?php foreach ($menus as $m): ?>
                        <tr class="menu-row" data-id="<?= e($m['id']) ?>">
                            <td>
                                <div class="input-group input-group-sm mb-1">
                                    <span class="input-group-text"><i
                                            class="<?= e($m['icon']) ?> check-icon-preview"></i></span>
                                    <input class="form-control check-icon" data-field="icon" value="<?= e($m['icon']) ?>"
                                        disabled placeholder="İkon">
                                    <button class="btn btn-outline-secondary icon-picker-btn" type="button"
                                        disabled>🔍</button>
                                </div>
                                <input class="form-control form-control-sm" data-field="title" value="<?= e($m['title']) ?>"
                                    disabled placeholder="Başlık">
                            </td>
                            <td>
                                <select class="form-select form-select-sm mb-1" data-field="page_id" disabled>
                                    <option value="">(Harici Link)</option>
                                    <?php foreach ($pages as $p): ?>
                                        <option value="<?= e((string)$p['id']) ?>" <?= ($m['page_id'] == $p['id']) ? 'selected' : '' ?>>
                                            /<?= e($p['slug']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input class="form-control form-control-sm" data-field="external_url"
                                    value="<?= e($m['external_url']) ?>" disabled placeholder="URL">
                            </td>
                            <td width="70">
                                <input type="number" class="form-control form-control-sm text-center" data-field="order_no"
                                    value="<?= e($m['sort_order']) ?>" disabled>
                            </td>
                            <td>
                                <select class="form-select form-select-sm" data-field="locations" multiple disabled
                                    style="height: 60px;">
                                    <?php $locs = $menuLocations[$m['id']] ?? []; ?>
                                    <option value="navbar" <?= in_array('navbar', $locs) ? 'selected' : '' ?>>Navbar</option>
                                    <option value="footer" <?= in_array('footer', $locs) ? 'selected' : '' ?>>Footer</option>
                                    <option value="sidebar" <?= in_array('sidebar', $locs) ? 'selected' : '' ?>>Sidebar
                                    </option>
                                </select>
                            </td>
                            <td width="100">
                                <select class="form-select form-select-sm" data-field="aktif" disabled>
                                    <option value="1" <?= $m['is_active'] ? 'selected' : '' ?>>Aktif</option>
                                    <option value="0" <?= !$m['is_active'] ? 'selected' : '' ?>>Pasif</option>
                                </select>
                            </td>
                            <td width="100" class="text-end">
                                <button class="btn btn-outline-primary btn-sm mb-1" onclick="MenuManager.edit(this)"><i
                                        class="fas fa-edit"></i></button>
                                <button class="btn btn-outline-danger btn-sm"
                                    onclick="MenuManager.del(<?= e($m['id']) ?>)"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Browser Modal dahil ediliyor (Dosya: admin/browser-panel.php) -->
<div class="modal fade" id="browserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-folder-open me-2"></i> <span lang="file_selector">Dosya Seçici</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <?php 
                if (!defined('NO_BROWSER_EDITOR')) define('NO_BROWSER_EDITOR', true);
                include __DIR__ . '/browser-panel.php'; 
                ?>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" lang="cancel">İptal</button>
            </div>
        </div>
    </div>
</div>

</div>

<!-- ICON PICKER MODAL -->
<div class="modal fade" id="iconPickerModal" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" lang="select_icon"><?= __('select_icon') ?></h5>
                <input type="text" id="iconSearch" class="form-control form-control-sm ms-3" data-placeholder="search" placeholder="<?= __('search') ?>"
                    style="width: 250px;">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap gap-2 justify-content-center" id="iconGrid">
                    <!-- Icons will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i> <span lang="edit_page"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                    <input type="hidden" name="mode" value="edit">
                    <input type="hidden" name="old_slug" id="old_slug">

                    <div class="card border-0 shadow-sm p-3 mb-3">
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label" lang="sluglabel">Slug
                                    (URL)</label><input type="text" name="slug" id="edit_slug"
                                    class="form-control fw-bold border-primary"></div>
                            <div class="col-md-6 mb-3"><label class="form-label" lang="title">Başlık</label><input
                                    type="text" name="title" id="edit_title" class="form-control"></div>
                            <div class="col-md-8 mb-3"><label class="form-label"
                                    lang="description"><?= __('description') ?></label><input type="text" name="description"
                                    id="edit_description" class="form-control"></div>
                            <div class="col-md-4 mb-3"><label class="form-label" lang="active">Durum</label><select
                                    name="is_active" id="edit_is_active" class="form-select">
                                    <option value="1" lang="yes"></option>
                                    <option value="0" lang="no"></option>
                                </select></div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label" lang="featured_image">Öne Çıkan Görsel</label>
                                <div class="input-group">
                                    <input type="text" name="featured_image" id="edit_featured_image"
                                        class="form-control" placeholder="URL...">
                                    <button type="button" class="btn btn-outline-secondary"
                                        onclick="openMediaBrowser('edit_featured_image', 'media/galeri')">
                                        <i class="fas fa-folder-open"></i> <span lang="select">Seç</span>
                                    </button>
                                </div>
                                <div id="edit_image_preview_container" class="mt-2" style="display:none;">
                                    <img src="" id="edit_image_preview" class="img-thumbnail shadow-sm"
                                        style="max-height: 150px;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm p-4 mb-3" style="border-radius: 12px;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="form-label mb-0" style="display:none;"></label>
                            <div class="ms-auto" id="editSnippetMenu" style="width: auto; min-width: 250px;">
                                <!-- Snippet Select Will be loaded here -->
                            </div>
                        </div>
                        <?php if (empty($allowPagePhp)): ?>
                            <div class="alert alert-warning">
                                <strong>Güvenlik Modu:</strong> PHP içerik kaydı kapalıdır.
                            </div>
                        <?php endif; ?>
                        <textarea name="content" id="edit_content" rows="18" class="form-control code-font shadow-sm"
                            style="border-radius: 12px; border: 1px solid #e0e0e0;"></textarea>
                    </div>
                    <!-- Snippetlar JS'de CUSTOM_SNIPPETS globali üzerinden yönetilecek -->

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm p-3">
                                <label class="form-label" lang="displaypage">Görünüm</label>

                                <div class="input-group mb-2">
                                    <span class="input-group-text"><i class="fas fa-icons"
                                            id="edit_icon_preview"></i></span>
                                    <input type="text" name="icon" id="edit_icon" class="form-control check-icon"
                                        placeholder="İkon">
                                    <button class="btn btn-outline-secondary icon-picker-btn" type="button"
                                        data-target="#edit_icon">🔍</button>
                                </div>

                                <label class="small text-muted">CSS Assets</label>
                                <div class="edit-assets-list mb-2" id="edit_css_list"
                                    style="max-height:100px;overflow-y:auto;border:1px solid #ddd;">
                                    <!-- CSS Checkboxları JS ile doldurulacak -->
                                </div>
                                <input type="text" name="custom_css" id="edit_custom_css" class="form-control"
                                    placeholder="Özel CSS Path">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm p-3">
                                <label class="form-label" lang="jsedit">JavaScript</label>
                                <div class="edit-assets-list mb-2" id="edit_js_list"
                                    style="max-height:100px;overflow-y:auto;border:1px solid #ddd;">
                                    <!-- JS Checkboxları JS ile doldurulacak -->
                                </div>
                                <textarea name="js" id="edit_custom_js" class="form-control" rows="2"
                                    placeholder="Özel JS Path"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden Template for JS population -->
                    <script id="allCssFiles"
                        type="application/json"><?= json_encode(array_map('basename', $cssFiles)) ?></script>
                    <script id="allJsFiles"
                        type="application/json"><?= json_encode(array_map('basename', $jsFiles)) ?></script>
                </form>
            </div>
            <div class="modal-footer border-0 shadow-sm">
                <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" lang="vazgecland">Vazgeç</button>
                <button class="btn btn-primary px-5 shadow-sm" id="saveEdit"><i class="fas fa-check me-2"></i> <span
                        lang="savelang"></span></button>
            </div>
        </div>
    </div>
</div> <!-- editModal end -->

<!-- SNIPPET MANAGEMENT MODAL -->
<div class="modal fade" id="snippetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold"><i class="fas fa-code me-2"></i> <span lang="snippet_management">Snippet
                        Yönetimi</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Ekleme Formu -->
                <div class="card p-3 mb-4 border-0 bg-light shadow-sm">
                    <h6 class="fw-bold mb-3" lang="add_new_snippet"><?= __('add_new_snippet') ?></h6>
                    <div class="row g-2">
                        <div class="col-md-5">
                            <input type="text" id="snip_title" class="form-control" data-placeholder="snippet_title" placeholder="<?= __('title') ?>">
                        </div>
                        <div class="col-md-5">
                            <textarea id="snip_code" class="form-control" data-placeholder="snippet_code" placeholder="<?= __('code') ?>"
                                rows="1"></textarea>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary w-100" onclick="manageSnippets('add')" lang="add"><?= __('add') ?></button>
                        </div>
                    </div>
                </div>

                <!-- Liste -->
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th lang="title">Başlık</th>
                                <th lang="code">Kod</th>
                                <th class="text-end" lang="action">İşlem</th>
                            </tr>
                        </thead>
                        <tbody id="snippet_list_body">
                            <?php if (empty($customSnippets)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted" lang="no_records"><?= __('no_records') ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($customSnippets as $cs): ?>
                                    <tr>
                                        <td class="fw-bold"><?= e($cs['title']) ?></td>
                                        <td><code><?= e(mb_strimwidth($cs['code'], 0, 30, "...")) ?></code></td>
                                        <td class="text-end">
                                            <button class="btn btn-outline-danger btn-sm"
                                                onclick="manageSnippets('delete', <?= $cs['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Sortable.js ile Menü Sıralaması
document.addEventListener('DOMContentLoaded', function() {
    const menuTableBody = document.getElementById('menuTableBody');
    
    if (menuTableBody && typeof Sortable !== 'undefined') {
        const sortable = new Sortable(menuTableBody, {
            animation: 150,
            handle: '.menu-row', // Tüm satır sürüklenebilir
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            onEnd: function(evt) {
                // Sıralama değiştiğinde
                const menuIds = [];
                const rows = menuTableBody.querySelectorAll('.menu-row');
                
                rows.forEach((row, index) => {
                    const menuId = row.getAttribute('data-id');
                    menuIds.push({
                        id: menuId,
                        order: index + 1
                    });
                });
                
                // AJAX ile sıralamayı kaydet
                fetch('page-actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        mode: 'reorder_menus',
                        csrf: '<?= $_SESSION['csrf'] ?>',
                        menus: menuIds
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.ok) {
                        // Başarılı - görsel feedback
                        evt.item.style.backgroundColor = '#d4edda';
                        setTimeout(() => {
                            evt.item.style.backgroundColor = '';
                        }, 500);
                    } else {
                        console.error('Sıralama kaydedilemedi:', data.error);
                        alert(t('error_occurred') || 'Hata oluştu!');
                    }
                })
                .catch(error => {
                    console.error('AJAX hatası:', error);
                    alert(t('error_occurred') || 'Bağlantı hatası!');
                });
            }
        });
        
        // Sürüklenirken görsel efektler için CSS
        const style = document.createElement('style');
        style.textContent = `
            .sortable-ghost {
                opacity: 0.4;
                background-color: #f8f9fa;
            }
            .sortable-drag {
                cursor: move;
            }
            #menuTableBody .menu-row {
                cursor: move;
            }
            #menuTableBody .menu-row:hover {
                background-color: #f8f9fa;
            }
        `;
        document.head.appendChild(style);
    } else {
        console.warn('Sortable.js yüklenmedi veya menuTableBody bulunamadı!');
    }
});
</script>
