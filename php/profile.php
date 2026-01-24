<?php
declare(strict_types=1);
require_once '../settings.php';
require_once '../admin/db.php';
require_once 'user_auth.php'; // already calls session_start()
require_once 'theme-init.php';
require_once 'menu-loader.php';

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
    $new_pass = $_POST['new_password'] ?? '';
    $current_pass_input = $_POST['current_password'] ?? '';
    $new_username = trim($_POST['username'] ?? '');

    $update_fields = ["display_name = ?", "avatar_url = ?", "preferred_theme = ?"];
    $params = [$display_name, $avatar_url, $preferred_theme];

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
        header("Location: " . BASE_URL . "php/profile.php?id=$user_id&status=success");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - <?= e($user['username']) ?></title>
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
        const BASE_PATH = '<?= BASE_PATH ?>';
        const BASE_URL = "<?= BASE_URL ?>"; 
    </script>
    <link rel="stylesheet" href="<?= CDN_URL ?>css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CDN_URL ?>css/all.min.css">
    <?php
    $themeCss = "themes/" . ACTIVE_THEME . "/style.css";
    if (file_exists(ROOT_DIR . $themeCss)) {
        echo '<link rel="stylesheet" href="' . BASE_URL . $themeCss . '?v=' . filemtime(ROOT_DIR . $themeCss) . '">';
    }
    ?>
</head>

<body>

    <?php load_theme_part('navbar'); ?>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <?php if (isset($_GET['status'])): ?>
                    <div class="alert alert-success small"><span lang="profile_updated_success"></span></div><?php endif; ?>
                <?php if ($msg):
                    $m = explode('|', $msg); ?>
                    <div class="alert alert-<?= $m[0] ?> small"><?= $m[1] ?></div><?php endif; ?>

                <div class="card rounded-4">
                    <div class="card-body p-4 p-md-5">
                        <form method="POST">
                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                            <div class="text-center mb-4">
                                <div class="profile-icon-big text-primary mb-3">
                                    <i class="fas <?= e($user['avatar_url'] ?? 'fa-user') ?> fa-2x"></i>
                                </div>
                                <h4 class="mb-0"><?= e($user['username']) ?></h4>
                                <small class="text-muted text-uppercase"><?= e($user['role']) ?></small>
                                <?php if (!$can_edit): ?>
                                    <div class="mt-2"><span class="badge bg-secondary opacity-75">Salt Okunur Profil</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($can_edit): ?>

                                <div class="mb-3">
                                    <label class="form-label small" lang="choose_profile_icon"></label>
                                    <div class="d-flex flex-wrap gap-2 p-3 bg-secondary bg-opacity-10 rounded-3 overflow-auto"
                                        style="max-height:160px;">
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

                                <div class="mb-3">
                                    <label class="form-label small" lang="display_name_label"></label>
                                    <input type="text" name="display_name" class="form-control"
                                        value="<?= e($user['display_name']) ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small" lang="username_label"></label>
                                    <input type="text" name="username" class="form-control"
                                        value="<?= e($user['username']) ?>" <?= ($allow_username || $is_admin) ? '' : 'disabled' ?>>
                                </div>

                                <?php if ($settings['allow_user_theme'] == '1' || $is_admin): ?>
                                    <div class="mb-3">
                                        <label class="form-label small">Tercih Edilen Tema</label>
                                        <select name="preferred_theme" class="form-select">
                                            <option value="">Sistem Varsayılanı</option>
                                            <?php foreach ($available_themes as $folder => $title): ?>
                                                <option value="<?= e($folder) ?>" <?= ($user['preferred_theme'] == $folder) ? 'selected' : '' ?>><?= e($title) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <div class="p-3 bg-secondary bg-opacity-10 rounded-3 mb-4">
                                    <h6 class="small fw-bold mb-3"><i class="fas fa-lock me-2"></i><span
                                            lang="security_settings"></span></h6>
                                    <?php if ($is_own_profile): ?>
                                        <div class="mb-3">
                                            <label class="form-label small opacity-75" lang="current_password_label"></label>
                                            <input type="password" name="current_password" class="form-control">
                                        </div>
                                    <?php endif; ?>
                                    <div class="mb-0">
                                        <label class="form-label small opacity-75" lang="new_password_label"></label>
                                        <input type="password" name="new_password" class="form-control">
                                        <small class="text-muted" style="font-size:0.6rem;"
                                            lang="only_fill_if_want"></small>
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg rounded-3"><i
                                            class="fas fa-save me-2"></i><span lang="save_changes"></span></button>
                                </div>
                            <?php else: ?>
                                <div class="mb-3">
                                    <label class="form-label small opacity-75" lang="display_name_label"></label>
                                    <div class="form-control bg-light"><?= e($user['display_name']) ?></div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small opacity-75" lang="username_label"></label>
                                    <div class="form-control bg-light"><?= e($user['username']) ?></div>
                                </div>
                                <div class="alert alert-info small mt-4">
                                    <i class="fas fa-info-circle me-2"></i> Bu profili sadece görüntüleyebilirsiniz.
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php load_theme_part('footer'); ?>

    <script src="<?= CDN_URL ?>js/bootstrap.bundle.min.js"></script>
    <script src="<?= CDN_URL ?>js/dark.js"></script>
    <script src="<?= CDN_URL ?>js/lang.js"></script>
</body>

</html>