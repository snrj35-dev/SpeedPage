<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

/* ============================
   ✅ MODÜLLERİ ÇEK
============================ */
try {
    $modules = $db->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $modules = [];
}
?>

<div class="modules-panel container">

    <h4 class="mb-3">🧩 <span lang="module_management"></span></h4>

    <!-- ✅ MODÜL YÜKLEME -->
    <form id="uploadModuleForm" enctype="multipart/form-data" action="modul-func.php" method="post">
        <input type="hidden" name="action" value="upload">

        <div class="mb-2">
            <input type="file" name="module_zip" class="form-control">
        </div>

        <button type="submit" class="btn btn-primary" lang="upload"></button>
    </form>

    <hr>

    <!-- ✅ MODÜL LİSTESİ -->
    <table class="table table-bordered mt-3">
        <thead>
            <tr>
                <th lang="id"></th>
                <th lang="name"></th>
                <th lang="title"></th>
                <th lang="description"></th>
                <th lang="sluglabel"></th>
                <th lang="status"></th>
                <th lang="tabislem"></th>
            </tr>
        </thead>

        <tbody>
            <?php foreach($modules as $m): ?>
            <tr>
                <td data-label="ID"><?= $m['id'] ?></td>
                <td data-label="İsim"><?= htmlspecialchars($m['name']) ?></td>
                <td data-label="Başlık"><?= htmlspecialchars($m['title']) ?></td>
                <td data-label="Açıklama"><?= htmlspecialchars($m['description']) ?></td>
                <td data-label="Slug"><?= htmlspecialchars($m['page_slug']) ?></td>
                <td class="module-status" data-label="Durum"><?= $m['is_active'] ? '<span lang="active"></span>' : '<span lang="passive"></span>' ?></td>
                <td data-label="">
                    <button class="btn btn-sm btn-secondary toggle-module-btn"
                            data-id="<?= $m['id'] ?>" data-active="<?= $m['is_active'] ? '1' : '0' ?>">
                        <?= $m['is_active'] ? 'Devre Dışı Bırak' : 'Etkinleştir' ?>
                    </button>

                    <button class="btn btn-sm btn-danger delete-module-btn"
                            data-id="<?= $m['id'] ?>" lang="delete"></button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</div>

