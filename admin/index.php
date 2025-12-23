<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

$pages = $db->query("SELECT * FROM pages ORDER BY sort_order, slug")->fetchAll(PDO::FETCH_ASSOC);
$userQuery = $db->prepare("SELECT display_name, username, avatar_url FROM users WHERE id = ?");
        $userQuery->execute([$_SESSION['user_id']]);
        $currentUser = $userQuery->fetch();

        // Görünen isim önceliği: display_name > username > 'Kullanıcı'
        $finalName = $currentUser['display_name'] ?: ($currentUser['username'] ?: 'Kullanıcı');
        $finalAvatar = $currentUser['avatar_url'] ?: 'fa-user';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <script>
        const BASE_PATH = '<?= BASE_PATH ?>'; 
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title lang="page_title"></title>
    <link rel="stylesheet" href="<?= CDN_URL ?>css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CDN_URL ?>css/all.min.css">
    <link rel="stylesheet" href="<?= CDN_URL ?>css/style.css">
</head>
<body>

<nav class="navbar px-4 py-3 border-bottom">
    <span class="fw-bold" lang="page_title"></span>

    <div class="ms-auto d-flex align-items-center gap-3">
        <a href="<?= BASE_URL ?>php/profile.php?id=<?= $_SESSION['user_id'] ?>" 
           class="d-flex align-items-center text-decoration-none me-3 transition-hover">
            
            <div class="nav-avatar-circle bg-primary text-white d-flex align-items-center justify-content-center me-2 shadow-sm" 
                 style="width: 35px; height: 35px; border-radius: 50%; font-size: 14px;">
                <i class="fas <?= htmlspecialchars($finalAvatar) ?>"></i>
            </div>
            
            <span class="fw-bold text d-none d-sm-inline"> 
                <?= htmlspecialchars($finalName) ?> 
            </span>
        </a>
        <a href="../index.php" class="btn btn-sm btn-outline-success"> <i class="fa-solid fa-home"></i></a>
        <a href="../php/logout.php" class="btn btn-sm btn-outline-danger"> <i class="fa-solid fa-right-from-bracket"></i></a>
        <button id="theme-toggle" onclick="toggleTheme()"><i class="fas fa-moon"></i></button>
    </div>
</nav>

<!-- TAB MENÜ -->
<ul class="nav nav-tabs mt-4 px-4" id="adminTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active"
                data-bs-toggle="tab"
                data-bs-target="#settings"
                type="button">
            <i class="fas fa-file-alt"></i> <span lang="settings"></span>
        </button>
    </li>

    <li class="nav-item">
        <button class="nav-link"
                data-bs-toggle="tab"
                data-bs-target="#pages"
                type="button">
            <i class="fas fa-file"></i> <span lang="pages"></span>
        </button>
    </li>

    <li class="nav-item">
        <button class="nav-link"
                data-bs-toggle="tab"
                data-bs-target="#menu"
                type="button">
            <i class="fas fa-clipboard-list"></i> <span lang="menu_management"></span>
        </button>
    </li>

    <li class="nav-item">
        <button class="nav-link"
                data-bs-toggle="tab"
                data-bs-target="#modules"
                type="button">
            <i class="fas fa-puzzle-piece"></i> <span lang="modules"></span>
        </button>
    </li>

    <?php if (!empty($is_admin) && $is_admin): ?>
    <li class="nav-item">
      <button class="nav-link"
          data-bs-toggle="tab"
          data-bs-target="#users"
          type="button">
        <i class="fas fa-users"></i> <span lang="users"></span>
      </button>
    </li>
    <?php endif; ?>

    <li class="nav-item">
        <button class="nav-link"
                data-bs-toggle="tab"
                data-bs-target="#dbpanel"
                type="button">
            <i class="fas fa-database"></i> <span lang="database"></span>
        </button>
    </li>
</ul>

<!-- TAB CONTENT -->
<div class="tab-content">
      <!--  Genel AYARLAR -->
    <div class="tab-pane fade show active p-4" id="settings">
    <?php require __DIR__ . "/settings-panel.php"; ?>
    </div>

    <!--  SAYFALAR -->
    <div class="tab-pane fade p-4" id="pages">
    <?php require __DIR__ . "/page-panel.php"; ?>
    </div>

    <!--  MENÜ PANELİ -->
    <div class="tab-pane fade p-4" id="menu" style="min-height:400px;">
        <?php require __DIR__ . "/menu-panel.php"; ?>
    </div>

    <!--  MODÜLLER --> 
     <div class="tab-pane fade p-4" id="modules"> 
           <?php require __DIR__ . "/modules-panel.php"; ?> 
     </div>

    <?php if (!empty($is_admin) && $is_admin): ?>
    <!--  USERS -->
    <div class="tab-pane fade p-4" id="users">
      <?php require __DIR__ . "/user-panel.php"; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($is_admin) && $is_admin): ?>
    <!--  DB PANEL -->
    <div class="tab-pane fade p-4" id="dbpanel">
        <?php require __DIR__ . "/veripanel-content.php"; ?>
    </div>
    <?php endif; ?>
    
</div>

<!-- ✏️ EDIT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" lang="edit_page"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="editForm">

          <input type="hidden" name="old_slug" id="old_slug">

          <div class="mb-3">
            <label class="form-label" lang="editslug"></label>
            <input type="text" name="slug" id="edit_slug" class="form-control">
          </div>

          <div class="mb-3">
            <label class="form-label" lang="titleedit"></label>
            <input type="text" name="title" id="edit_title" class="form-control">
          </div>

          <div class="mb-3">
            <label class="form-label" lang="description"></label>
            <input type="text" name="description" id="edit_description" class="form-control">
          </div>

          <div class="mb-3">
            <label class="form-label" lang="icon"></label>
            <input type="text" name="icon" id="edit_icon" class="form-control">
          </div>

          <!-- ✅ aktif → is_active -->
          <div class="mb-3">
            <label class="form-label" lang="active"></label>
            <select name="is_active" id="edit_is_active" class="form-select">
                <option value="1" lang="active"></option>
                <option value="0" lang="passive"></option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label" lang="cssedit"></label>
            <input type="text" name="css" id="edit_css" class="form-control">
          </div>

          <div class="mb-3">
            <label class="form-label" lang="jsedit"></label>
            <input type="text" name="js" id="edit_js" class="form-control">
          </div>

          <div class="mb-3">
            <label class="form-label" lang="htmlcontent"></label>
            <textarea name="content" id="edit_content" rows="10" class="form-control"></textarea>
          </div>

        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal" lang="exitlang"></button>
        <button class="btn btn-primary" id="saveEdit" lang="savelang"></button>
      </div>
    </div>
  </div>
</div>
<footer class="copyrightnote">
    SpeedPage 0.1 Alpha
</footer>
<script src="<?= CDN_URL ?>js/jquery-3.7.1.min.js"></script>
<script src="<?= CDN_URL ?>js/bootstrap.bundle.min.js"></script>
<script src="<?= CDN_URL ?>js/dark.js"></script>
<script src="<?= CDN_URL ?>js/admin.js"></script>
<script src="<?= CDN_URL ?>js/lang.js"></script>
</body>
</html>

