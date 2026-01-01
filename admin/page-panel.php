<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

try {
    $pages = $db->query("SELECT * FROM pages ORDER BY sort_order, slug")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $pages = [];
}
?>

<div class="page-panel">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800" lang="pages"></h1>
        <button class="btn btn-primary btn-sm shadow-sm" type="button" data-bs-toggle="collapse"
            data-bs-target="#newSection" aria-expanded="false">
            <i class="fas fa-plus fa-sm text-white-50"></i> <span lang="new_create_page"></span>
        </button>
    </div>
    <div id="newSection" class="collapse card shadow border-left-primary mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center bg-light">
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
            <form id="sayfaOlusturFormu" action="page-actions.php" method="POST">
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
                            <label class="form-label" lang="htmlcontent">İçerik (HTML/PHP)</label>
                            <textarea name="icerik" id="new_content" rows="12" class="form-control code-font"
                                style="font-family: monospace;"></textarea>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-tasarim" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label" lang="icon_label">İkon
                                    (FontAwesome)</label><input type="text" name="icon" class="form-control"
                                    placeholder="fas fa-home"></div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" lang="active">Durum</label>
                                <select name="aktif" class="form-select">
                                    <option value="1" selected lang="active">Aktif</option>
                                    <option value="0" lang="passive">Pasif</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3"><label class="form-label" lang="cssedit">CSS (Virgülle ayır)</label><textarea
                                name="css" class="form-control" rows="2"></textarea></div>
                        <div class="mb-3"><label class="form-label" lang="jsedit">JS (Virgülle ayır)</label><textarea
                                name="js" class="form-control" rows="2"></textarea></div>
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
                                <div class="col-md-4 mb-3"><label class="form-label" lang="menu_icon_placeholder">Menü
                                        İkonu</label><input type="text" name="menu_icon" class="form-control"></div>
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
                    <tbody>
                        <?php foreach ($pages as $p): ?>
                            <tr>
                                <td class="text-center"><?php if ($p['icon']): ?><i
                                            class="fa <?= e($p['icon']) ?> text-muted"></i><?php else: ?>-<?php endif; ?>
                                </td>
                                <td class="fw-bold text-dark">/<?= e($p['slug']) ?></td>
                                <td><?= e($p['title']) ?> <br><small class="text-muted"><?= e($p['description']) ?></small>
                                </td>
                                <td class="text-center">
                                    <?= $p['is_active'] ? '<span class="badge-active" lang="yes">Aktif</span>' : '<span class="badge-passive" lang="no">Pasif</span>' ?>
                                </td>
                                <td class="text-end" style="padding-right: 20px;">
                                    <button class="btn btn-action btn-outline-primary edit-btn"
                                        data-slug="<?= e($p['slug']) ?>" title="Düzenle"><i
                                            class="fas fa-edit"></i></button>
                                    <?php if ($is_admin): ?>
                                        <button class="btn btn-action btn-outline-danger delete-btn"
                                            data-slug="<?= e($p['slug']) ?>" title="Sil"><i class="fas fa-trash"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
                <form id="editForm">
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
                                    lang="description">Açıklaması</label><input type="text" name="description"
                                    id="edit_description" class="form-control"></div>
                            <div class="col-md-4 mb-3"><label class="form-label" lang="active">Durum</label><select
                                    name="is_active" id="edit_is_active" class="form-select">
                                    <option value="1" lang="yes"></option>
                                    <option value="0" lang="no"></option>
                                </select></div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm p-3 mb-3">
                        <label class="form-label" lang="htmlcontent">İçerik (HTML)</label>
                        <textarea name="content" id="edit_content" rows="15" class="form-control code-font"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm p-3"><label class="form-label"
                                    lang="displaypage">Görünüm</label><input type="text" name="icon" id="edit_icon"
                                    class="form-control mb-2" placeholder="İkon"><input type="text" name="css"
                                    id="edit_css" class="form-control" placeholder="CSS"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm p-3"><label class="form-label"
                                    lang="jsedit">JavaScript</label><textarea name="js" id="edit_js"
                                    class="form-control" rows="3" placeholder="JS"></textarea></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 shadow-sm">
                <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" lang="vazgecland">Vazgeç</button>
                <button class="btn btn-primary px-5 shadow-sm" id="saveEdit"><i class="fas fa-check me-2"></i> <span
                        lang="savelang"></span></button>
            </div>
        </div>
    </div>
</div>