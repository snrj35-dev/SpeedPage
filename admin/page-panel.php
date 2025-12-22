<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

/* ============================
  SAYFA LİSTESİ
============================ */
try {
    $pages = $db->query("SELECT * FROM pages ORDER BY sort_order, slug")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $pages = [];
}
?>

<div class="page-panel">

<!--  SAYFA LİSTESİ -->
<div class="card p-3 mb-4">
    <h4 class="mb-3"> <i class="fas fa-file-alt"></i> <span lang="pages"></span> </h4>

    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th lang="icon"></th>
                <th lang="sluglabel">Slug</th>
                <th lang="title"></th>
                <th lang="description"></th>
                <th lang="active"></th>
                <th lang="tabislem">İşlem</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pages as $p):
            $slug = $p['slug'];
            $title = $p['title'] ?? '';
            $description = $p['description'] ?? '';
            $icon = $p['icon'] ?? '';
            $aktif = isset($p['is_active']) 
                ? ($p['is_active'] ? '<span lang="yes"></span>' : '<span lang="no"></span>')
                : '—';
        ?>
            <tr>
                <td data-label="İkon"><?php if($icon): ?><i class="fa <?= htmlspecialchars($icon) ?>"></i><?php endif; ?></td>
                <td data-label="Slug"><?= htmlspecialchars($slug) ?></td>
                <td data-label="Başlık"><?= htmlspecialchars($title) ?></td>
                <td data-label="Açıklama"><?= htmlspecialchars($description) ?></td>
                <td data-label="Durum"><?= $aktif ?></td>
                <td data-label="">
                    <button class="btn btn-sm btn-primary edit-btn"
                            data-slug="<?= htmlspecialchars($slug) ?>">
                        <i class="fas fa-edit"></i> <span lang="edit_page"></span>
                    </button>

                    <button class="btn btn-sm btn-danger delete-btn"
                            data-slug="<?= htmlspecialchars($slug) ?>">
                        <i class="fas fa-trash"></i> <span lang="delete_page"></span>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- YENİ SAYFA OLUŞTUR -->
<div class="card p-3">
    <h4 class="mb-3" lang="new_create_page"></h4>

    <form id="sayfaOlusturFormu" action="page-create.php" method="POST">

        <div class="mb-3">
            <label class="form-label" lang="sluglabel"></label>
            <input type="text" name="slug" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label" lang="title"></label>
            <input type="text" name="title" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label" lang="description"></label>
            <input type="text" name="description" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label" lang="icon_label"></label>
            <input type="text" name="icon" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label" lang="active"></label>
            <select name="aktif" class="form-select">
                <option value="1" selected lang="active"></option>
                <option value="0" lang="passive"></option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label" lang="cssedit"></label>
            <input type="text" name="css" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label" lang="jsedit"></label>
            <input type="text" name="js" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label" lang="htmlcontent"></label>
            <textarea name="icerik" rows="8" class="form-control">
<div class="container">
    <h1>Başlık</h1>
</div>
            </textarea>
        </div>

        <!-- Yeni menü sistemi -->
        <div class="mb-3 border p-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="addToMenu" name="add_to_menu">
                <label class="form-check-label" for="addToMenu" lang="add_to_menu"></label>
            </div>

            <div id="addToMenuFields" style="display:none; margin-top:10px;">
                <div class="mb-2">
                    <input type="text" name="menu_title" class="form-control" lang="menu_title_placeholder">
                </div>
                <div class="mb-2">
                    <input type="text" name="menu_icon" class="form-control" lang="menu_icon_placeholder">
                </div>
                <div class="mb-2">
                    <input type="number" name="menu_order" class="form-control" value="1" lang="menu_order">
                </div>

                <label class="form-label" lang="menu_locations"></label>
                <select name="menu_locations[]" class="form-select" multiple>
                    <option value="navbar" lang="navbar"></option>
                    <option value="footer" lang="footer"></option>
                    <option value="sidebar" lang="sidebar"></option>
                    <option value="home" lang="home"></option>
                </select>
            </div>
        </div>

        <button class="btn btn-success" lang="createlang"></button>
    </form>

    <div id="sonucMesaji" class="mt-3"></div>
</div>

</div>

