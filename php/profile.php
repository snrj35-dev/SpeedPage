<?php
declare(strict_types=1);
require_once '../settings.php';
require_once '../admin/db.php';
require_once 'user_auth.php'; // already calls session_start()
require_once 'theme-init.php';
require_once 'menu-loader.php';
if (function_exists('sp_load_module_hooks')) {
    sp_load_module_hooks();
}
$stmt = $db->query("SELECT `key`, `value` FROM settings");
$config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$settings = $config;

$menus = getMenus('navbar');

$allow_username = $settings['allow_username_change'] ?? '0';
$allow_password = $settings['allow_password_change'] ?? '1';

$user_id = $_GET['id'] ?? $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] === 'admin');
$is_own_profile = ($_SESSION['user_id'] == $user_id);

$available_themes = [];
if ($settings['allow_user_theme'] == '1' || $is_admin) {
    $themeDir = ROOT_DIR . 'themes/';
    if (is_dir($themeDir)) {
        foreach (scandir($themeDir) as $folder) {
            if ($folder === '.' || $folder === '..')
                continue;
            if (file_exists($themeDir . $folder . '/theme.json')) {
                $tdata = json_decode(file_get_contents($themeDir . $folder . '/theme.json'), true);
                $available_themes[$folder] = $tdata['title'] ?? $tdata['name'] ?? $folder;
            }
        }
    }
}

// Permission Check: Admin can see everything, users can see their own profile and others' limited profile
$can_edit = ($is_own_profile || $is_admin);

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user)
    die('<div class="alert alert-danger small text-center"><span lang="user_not_found"></span></div>');

$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $display_name = trim($_POST['display_name']);
    $avatar_url = $_POST['avatar_url'] ?? 'fa-user';
    $preferred_theme = !empty($_POST['preferred_theme']) ? $_POST['preferred_theme'] : null;
    $bio = $_POST['bio'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $current_pass_input = $_POST['current_password'] ?? '';
    $new_username = trim($_POST['username'] ?? '');

    $update_fields = ["display_name = ?", "avatar_url = ?", "preferred_theme = ?", "bio = ?"];
    $params = [$display_name, $avatar_url, $preferred_theme, $bio];

    if (!empty($new_pass)) {
        if ($is_own_profile && !password_verify($current_pass_input, $user['password_hash'])) {
            $msg = 'danger|<span lang="invalid_current_password"></span>';
        } elseif (strlen($new_pass) < 6) {
            $msg = 'danger|<span lang="password_too_short"></span>';
        }
    }

    if (empty($msg) && ($allow_username === '1' || $is_admin) && !empty($new_username) && $new_username !== $user['username']) {
        $check = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->execute([$new_username, $user_id]);
        if (!$check->fetch()) {
            $update_fields[] = "username = ?";
            $params[] = $new_username;
        } else {
            $msg = 'danger|<span lang="username_taken"></span>';
        }
    }

    if (empty($msg) && ($allow_password === '1' || $is_admin) && !empty($new_pass)) {
        if (strlen($new_pass) >= 6) {
            $update_fields[] = "password_hash = ?";
            $params[] = password_hash($new_pass, PASSWORD_DEFAULT);
        }
    }

    if (empty($msg)) {
        $params[] = $user_id;
        $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
        $db->prepare($sql)->execute($params);
        if (function_exists('run_hook')) {
            run_hook('profile_handle_save', ['user_id' => $user_id, 'post_data' => $_POST]);
        }
        header("Location: " . BASE_URL . "php/profile.php?id=$user_id&status=success");
        exit;
    }
}
$pageTitle = "Profil - " . e($user['username']);
load_theme_part('header');
?>

<?php load_theme_part('navbar'); ?>

<main class="container py-5">
    <?php if (function_exists('run_hook')) run_hook('profile_top', ['user' => $user, 'is_admin' => $is_admin]); ?>
    
    <div class="card rounded-4 border-0 shadow-sm mb-4 overflow-hidden">
        <div class="p-4 d-flex align-items-center bg-primary text-white" style="background: linear-gradient(45deg, #4e73df, #224abe);">
            <div class="avatar-lg bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow" style="width:80px; height:80px; font-size: 2.5rem;">
                <i class="fas <?= e($user['avatar_url'] ?? 'fa-user') ?>"></i>
            </div>
            <div class="ms-4">
                <h3 class="fw-bold mb-0"><?= e($user['display_name'] ?? $user['username']) ?></h3>
                <p class="opacity-75 mb-0">@<?= e($user['username']) ?></p>
                <small class="text-uppercase opacity-75"><?= e($user['role']) ?></small>
                <div class="mt-2" id="headerBadges">
                    <?php if (function_exists('run_hook')) run_hook('profile_header_badges', ['user' => $user, 'is_admin' => $is_admin]); ?>
                </div>
            </div>
        </div>
        
        <?php if ($can_edit): ?>
        <div class="bg-white border-top px-4 py-2 d-flex align-items-center justify-content-between">
            <ul class="nav nav-pills gap-2" id="profileTabs">
                <li class="nav-item">
                    <a class="nav-link active px-4 rounded-pill" href="javascript:void(0)" onclick="switchTab('overview', this)">
                        <i class="fas fa-th-large me-2"></i>Genel Bakış
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-4 rounded-pill" href="javascript:void(0)" onclick="switchTab('settings', this)">
                        <i class="fas fa-cog me-2"></i>Ayarlar
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['status'])): ?>
        <div class="alert alert-success small"><span lang="profile_updated_success"></span></div>
    <?php endif; ?>
    <?php if ($msg):
        $m = explode('|', $msg); ?>
        <div class="alert alert-<?= $m[0] ?> small"><?= $m[1] ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-12 transition-all" id="contentCol">
            
            <div id="view-overview">
                <?php if (function_exists('run_hook')) run_hook('profile_bottom', ['user' => $user, 'is_admin' => $is_admin]); ?>
                
                <?php if ($user['bio']): ?>
                <div class="card rounded-4 border-0 shadow-sm p-4 mt-3">
                    <h5 class="fw-bold"><i class="fas fa-info-circle me-2 text-primary"></i>Hakkında</h5>
                    <p class="text-muted"><?= nl2br(e($user['bio'])) ?></p>
                </div>
                <?php else: ?>
                <div id="defaultAboutCard" class="card rounded-4 border-0 shadow-sm p-4 mt-3 d-none">
                    <h5 class="fw-bold"><i class="fas fa-info-circle me-2 text-primary"></i>Hakkında</h5>
                    <p class="text-muted">Bu kullanıcı hakkında henüz ek bir bilgi yok.</p>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($can_edit): ?>
            <div id="view-settings" class="d-none">
                <div class="card rounded-4 border-0 shadow-sm p-4">
                    <h5 class="fw-bold mb-4">Profil Ayarları</h5>
                    
                    <form method="POST" class="row g-3" enctype="multipart/form-data">
                        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">

                        <div class="col-12">
                            <label class="form-label small fw-bold" lang="choose_profile_icon"></label>
                            <div class="d-flex flex-wrap gap-2 p-3 bg-secondary bg-opacity-10 rounded-3 overflow-auto" style="max-height:160px;">
                                <?php
                                $icons = ['fa-user', 'fa-user-ninja', 'fa-user-astronaut', 'fa-user-tie', 'fa-user-secret', 'fa-ghost', 'fa-robot', 'fa-skull', 'fa-dragon', 'fa-cat', 'fa-dog', 'fa-rocket', 'fa-code'];
                                foreach ($icons as $ico): ?>
                                    <div class="avatar-selection">
                                        <input type="radio" class="btn-check" name="avatar_url" id="ico-<?= $ico ?>"
                                            value="<?= $ico ?>" <?= ($user['avatar_url'] == $ico) ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-primary border-0" for="ico-<?= $ico ?>"><i
                                                class="fas <?= $ico ?> fa-lg"></i></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold" lang="display_name_label"></label>
                            <input type="text" name="display_name" class="form-control rounded-3" value="<?= e($user['display_name']) ?>" required>
                        </div>

                        <?php if ($allow_username === '1' || $is_admin): ?>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold" lang="username_label"></label>
                            <input type="text" name="username" class="form-control rounded-3" value="<?= e($user['username']) ?>">
                        </div>
                        <?php endif; ?>

                        <div class="col-12">
                            <label class="form-label small fw-bold">Biyografi</label>
                            <textarea name="bio" class="form-control rounded-3" rows="3"><?= e($user['bio']) ?></textarea>
                        </div>

                        <?php if (function_exists('run_hook')) run_hook('profile_form_middle', ['user' => $user, 'is_admin' => $is_admin]); ?>

                        <?php if ($settings['allow_user_theme'] == '1' || $is_admin): ?>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tercih Edilen Tema</label>
                            <select name="preferred_theme" class="form-select rounded-3">
                                <option value="">Sistem Varsayılanı</option>
                                <?php foreach ($available_themes as $folder => $title): ?>
                                    <option value="<?= e($folder) ?>" <?= ($user['preferred_theme'] == $folder) ? 'selected' : '' ?>><?= e($title) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <hr class="my-4">
                        <h6 class="fw-bold text-danger"><i class="fas fa-lock me-2"></i><span lang="security_settings"></span></h6>
                        
                        <?php if ($is_own_profile): ?>
                        <div class="col-md-6">
                            <label class="form-label small" lang="current_password_label"></label>
                            <input type="password" name="current_password" class="form-control rounded-3">
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-6">
                            <label class="form-label small" lang="new_password_label"></label>
                            <input type="password" name="new_password" class="form-control rounded-3">
                            <small class="text-muted" style="font-size:0.6rem;" lang="only_fill_if_want"></small>
                        </div>

                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-primary rounded-pill px-5"><i class="fas fa-save me-2"></i><span lang="save_changes"></span></button>
                            <?php if (function_exists('run_hook')) run_hook('profile_form_buttons', ['user' => $user, 'is_admin' => $is_admin]); ?>
                        </div>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="card rounded-4 border-0 shadow-sm p-4">
                <div class="mb-3">
                    <label class="form-label small opacity-75" lang="display_name_label"></label>
                    <div class="form-control bg-light"><?= e($user['display_name']) ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label small opacity-75" lang="username_label"></label>
                    <div class="form-control bg-light"><?= e($user['username']) ?></div>
                </div>
                <?php if (function_exists('run_hook')) run_hook('profile_readonly_view', ['user' => $user, 'is_admin' => $is_admin]); ?>
                <div class="alert alert-info small mt-4">
                    <i class="fas fa-info-circle me-2"></i> Bu profili sadece görüntüleyebilirsiniz.
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-md-4 d-none" id="sidebarCol">
            <?php if (function_exists('run_hook')) run_hook('profile_sidebar', ['user' => $user, 'is_admin' => $is_admin]); ?>
        </div>
    </div>
</main>

<script>
// JS ile Sayfa Düzeni Kontrolü
document.addEventListener("DOMContentLoaded", function() {
    const sidebar = document.getElementById('sidebarCol');
    const content = document.getElementById('contentCol');
    const wall = document.querySelector('#moduleWallContainer') || document.querySelector('[id^="social-feed"]');

    // Sidebar'da içerik var mı?
    if (sidebar && sidebar.innerText.trim().length > 0) {
        sidebar.classList.remove('d-none');
        content.classList.replace('col-12', 'col-md-8');
    }

    // Modül yoksa ve duvar boşsa "Hakkında" kartını göster
    if (!wall || wall.innerText.trim().length === 0) {
        document.getElementById('defaultAboutCard')?.classList.remove('d-none');
    }
});

function switchTab(tabName, element) {
    document.getElementById('view-overview').classList.add('d-none');
    const settingsView = document.getElementById('view-settings');
    if(settingsView) settingsView.classList.add('d-none');
    
    document.getElementById('view-' + tabName).classList.remove('d-none');
    
    document.querySelectorAll('#profileTabs .nav-link').forEach(link => link.classList.remove('active'));
    element.classList.add('active');
}
</script>

<style>
.transition-all { transition: all 0.3s ease; }
.nav-link { color: #6c757d; font-weight: 500; border: 1px solid transparent; }
.nav-link.active { background-color: #f8f9fa !important; color: #4e73df !important; border-color: #dee2e6 !important; }
</style>

<?php load_theme_part('footer'); ?>
