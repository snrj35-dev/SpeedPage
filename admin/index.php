<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

// ✅ Fetch Global Settings
$stSet = $db->query("SELECT `key`, `value` FROM settings");
$settings = $stSet->fetchAll(PDO::FETCH_KEY_PAIR);

// ✅ Active Theme Definition (Admin panel usually uses default or system default, but we define it for consistency)
if (!defined('ACTIVE_THEME')) {
    $defaultTheme = $settings['active_theme'] ?? 'default';
    $finalTheme = $defaultTheme;

    // Optional: Admin might want to see the site in their preferred theme too? 
    // The user asked for "Sabit Tanımı Kontrolü" in both, so let's apply it.
    if (isset($settings['allow_user_theme']) && $settings['allow_user_theme'] == '1' && !empty($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT preferred_theme FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $pref = $stmt->fetchColumn();
        if ($pref) {
            $finalTheme = $pref;
        }
    }
    define('ACTIVE_THEME', $finalTheme);
}
// Kullanıcı bilgisi
$userQuery = $db->prepare("SELECT display_name, username, avatar_url FROM users WHERE id = ?");
$userQuery->execute([$_SESSION['user_id']]);
$currentUser = $userQuery->fetch();

$finalName = $currentUser['display_name'] ?: ($currentUser['username'] ?: 'Kullanıcı');
$finalAvatar = $currentUser['avatar_url'] ?: 'fa-user';

// Hangi sayfa?
$default_page = $is_admin ? 'settings' : 'pages';
$page = $_GET['page'] ?? $default_page;
if ($page === 'browser') {
    require_once __DIR__ . '/browser-islem.php';
}
// Global CSS/JS
$globalCss = [
    CDN_URL . "css/bootstrap.min.css",
    CDN_URL . "css/all.min.css",
    CDN_URL . "css/admin-core.css"
];
$globalJs = [
    CDN_URL . "js/jquery-3.7.1.min.js",
    CDN_URL . "js/bootstrap.bundle.min.js",
    CDN_URL . "js/dark.js",
    CDN_URL . "js/admin.js"
];

// Sayfa bazlı CSS/JS
$pageAssets = [
    'settings' => ['css' => [], 'js' => []],
    'pages' => ['css' => [CDN_URL . "css/pages.css"], 'js' => [CDN_URL . "js/pages.js"]],
    'menu' => ['css' => [], 'js' => [CDN_URL . "js/menu.js"]],
    'modules' => ['css' => [], 'js' => [CDN_URL . "js/modules.js"]],
    'users' => ['css' => [], 'js' => [CDN_URL . "js/user.js"]],
    'dbpanel' => ['css' => [], 'js' => [CDN_URL . "js/dbpanel.js"]],
    'browser' => ['css' => [], 'js' => [CDN_URL . "js/browser.js"]],
    'system' => ['css' => [CDN_URL . "css/system.css"], 'js' => [CDN_URL . "js/system.js", CDN_URL . "js/chart.js"]],
    'aipanel' => [
        'css' => [
            CDN_URL . "css/ai.css",
            CDN_URL . "css/highlight-dark.min.css" // Local Highlight.js Theme
        ],
        'js' => [
            CDN_URL . "js/highlight.min.js", // Local Highlight.js
            CDN_URL . "js/marked.min.js",    // Local Marked.js
            CDN_URL . "js/ai.js"
        ]
    ],
];


$currentAssets = $pageAssets[$page] ?? ['css' => [], 'js' => []];
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title lang="page_title"></title>
    <script>
        // Immediate theme check
        (function () {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
        const BASE_PATH = '<?= e(BASE_PATH) ?>';
        const CSRF_TOKEN = '<?= e($_SESSION['csrf']) ?>';
    </script>
    <!-- Global CSS -->
    <?php foreach ($globalCss as $css): ?>
        <link rel="stylesheet" href="<?= $css ?>?v=<?= time() ?>">
    <?php endforeach; ?>

    <!-- Sayfa özel CSS -->
    <?php foreach ($currentAssets['css'] as $css): ?>
        <link rel="stylesheet" href="<?= $css ?>?v=<?= time() ?>">
    <?php endforeach; ?>
</head>

<body>

    <nav class="navbar px-4 py-3 border-bottom">
        <span class="fw-bold" lang="page_title"></span>
        <div class="ms-auto d-flex align-items-center gap-3">
            <a href="<?= BASE_URL ?>php/profile.php?id=<?= $_SESSION['user_id'] ?>"
                class="d-flex align-items-center text-decoration-none me-3 transition-hover">
                <div class="nav-avatar-circle bg-primary text-white d-flex align-items-center justify-content-center me-2 shadow-sm"
                    style="width: 35px; height: 35px; border-radius: 50%; font-size: 14px;">
                    <i class="fas <?= e($finalAvatar) ?>"></i>
                </div>
                <span class="fw-bold text d-none d-sm-inline"><?= e($finalName) ?></span>
            </a>
            <a href="../index.php" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-home"></i></a>
            <a href="../php/logout.php" class="btn btn-sm btn-outline-danger"><i
                    class="fa-solid fa-right-from-bracket"></i></a>
            <button id="theme-toggle" class="btn btn-sm btn-outline-secondary"><i class="fas fa-moon"></i></button>
            <select id="lang-select" class="form-select form-select-sm border-0 rounded-pill px-2 px-md-3"
                style="width: auto;">
                <option value="tr">TR</option>
                <option value="en">EN</option>
            </select>
        </div>
    </nav>



    <!-- Menü -->
    <div class="admin-nav-container border-bottom sticky-top shadow-sm" style="top: 71px; z-index: 1020;">
        <nav class="nav admin-nav-scroll px-3">
            <?php if ($is_admin): ?>
                <a href="index.php?page=settings" class="nav-link <?= $page === 'settings' ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i> <span lang="settings"></span>
                </a>
            <?php endif; ?>
            <a href="index.php?page=pages" class="nav-link <?= $page === 'pages' ? 'active' : '' ?>">
                <i class="fas fa-file"></i> <span lang="pages"></span>
            </a>
            <a href="index.php?page=menu" class="nav-link <?= $page === 'menu' ? 'active' : '' ?>">
                <i class="fas fa-bars"></i> <span lang="menu_management"></span>
            </a>
            <a href="index.php?page=modules" class="nav-link <?= $page === 'modules' ? 'active' : '' ?>">
                <i class="fas fa-puzzle-piece"></i> <span lang="modules"></span>
            </a>
            <a href="index.php?page=themes"
                class="nav-link <?= ($page === 'themes' || $page === 'theme-settings') ? 'active' : '' ?>">
                <i class="fas fa-palette"></i> <span lang="themes"></span>
            </a>
            <?php if (!empty($is_admin) && $is_admin): ?>
                <a href="index.php?page=users" class="nav-link <?= $page === 'users' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i> <span lang="users"></span>
                </a>
                <a href="index.php?page=aipanel" class="nav-link <?= $page === 'aipanel' ? 'active' : '' ?>">
                    <i class="fas fa-robot"></i> <span lang="ai_panel">AI Panel</span>
                </a>
                <a href="index.php?page=dbpanel" class="nav-link <?= $page === 'dbpanel' ? 'active' : '' ?>">
                    <i class="fas fa-database"></i> <span lang="database"></span>
                </a>
                <a href="index.php?page=browser" class="nav-link <?= $page === 'browser' ? 'active' : '' ?>">
                    <i class="fas fa-folder-open"></i> <span lang="filebrowser"></span>
                </a>
                <a href="index.php?page=system" class="nav-link <?= $page === 'system' ? 'active' : '' ?>">
                    <i class="fas fa-microchip"></i> <span lang="system"></span>
                </a>
            <?php endif; ?>
        </nav>
    </div>

    <!-- İçerik -->
    <div class="container p-4">
        <?php
        switch ($page) {
            case 'settings':
                if ($is_admin)
                    require __DIR__ . "/settings-panel.php";
                else
                    echo "<div class='alert alert-danger'>Yetkisiz erişim.</div>";
                break;
            case 'pages':
                require __DIR__ . "/page-panel.php";
                break;
            case 'menu':
                require __DIR__ . "/menu-panel.php";
                break;
            case 'modules':
                require __DIR__ . "/modules-panel.php";
                break;
            case 'themes':
                require __DIR__ . "/themes-panel.php";
                break;
            case 'theme-settings':
                require __DIR__ . "/theme-settings.php";
                break;
            case 'users':
                if (!empty($is_admin) && $is_admin)
                    require __DIR__ . "/user-panel.php";
                break;
            case 'dbpanel':
                if (!empty($is_admin) && $is_admin)
                    require __DIR__ . "/veripanel-content.php";
                break;
            case 'browser':
                if (!empty($is_admin) && $is_admin)
                    require __DIR__ . "/browser-panel.php";
                break;
            case 'system':
                if (!empty($is_admin) && $is_admin)
                    require __DIR__ . "/system-panel.php";
                break;
            case 'migration':
                if (!empty($is_admin) && $is_admin)
                    require __DIR__ . "/migration-wizard.php";
                break;
            case 'aipanel':
                if (!empty($is_admin) && $is_admin)
                    require __DIR__ . "/ai-panel.php";
                break;
            default:
                echo "<p>Sayfa bulunamadı.</p>";
        }
        ?>
    </div>

    <footer class="copyrightnote">
        <span lang="site_version"></span>
    </footer>

    <!-- Global JS -->
    <?php foreach ($globalJs as $js): ?>
        <script src="<?= $js ?>"></script>
    <?php endforeach; ?>

    <!-- Sayfa özel JS -->
    <?php foreach ($currentAssets['js'] as $js): ?>
        <script src="<?= $js ?>"></script>
    <?php endforeach; ?>
    <script src="<?= CDN_URL ?>js/lang.js"></script>
</body>

</html>