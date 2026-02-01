<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';
/** @var PDO $db */
global $db;

// 🚀 Load Module Hooks (and translations)
if (function_exists('sp_load_module_hooks')) {
    sp_load_module_hooks();
}

// ✅ Fetch Global Settings
$stSet = $db->query("SELECT `key`, `value` FROM settings");
$settings = $stSet->fetchAll(PDO::FETCH_KEY_PAIR);

// ✅ Active Theme Definition (Admin panel usually uses default or system default, but we define it for consistency)
if (!defined('ACTIVE_THEME')) {
    $defaultTheme = $settings['active_theme'] ?? 'default';
    $finalTheme = $defaultTheme;

    // Optional: Admin might want to see the site in their preferred theme too? 
    // The user asked for "Sabit Tanımı Kontrolü" in both, so let's apply it.
    if (isset($settings['allow_user_theme']) && $settings['allow_user_theme'] === '1' && !empty($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT preferred_theme FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $pref = $stmt->fetchColumn();
        if ($pref) {
            $finalTheme = (string) $pref;
        }
    }
    define('ACTIVE_THEME', $finalTheme);
}
// Kullanıcı bilgisi
$userQuery = $db->prepare("SELECT display_name, username, avatar_url FROM users WHERE id = ?");
$userQuery->execute([$_SESSION['user_id']]);
$currentUser = $userQuery->fetch(PDO::FETCH_ASSOC);

$finalName = $currentUser['display_name'] ?: ($currentUser['username'] ?: 'Kullanıcı');
$finalAvatar = $currentUser['avatar_url'] ?: 'fa-user';

// Hangi sayfa?
// Sanitized Input
$page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (isset($is_admin)) {
    // $is_admin comes from auth.php
    $default_page = $is_admin ? 'dashboard' : 'pages';
} else {
    $default_page = 'pages'; // Fallback
}
$page = $page ?? $default_page;

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
    CDN_URL . "js/admin.js",
    CDN_URL . "js/market.js"
];

// Sayfa bazlı CSS/JS
$pageAssets = [
    'dashboard' => ['css' => [], 'js' => [CDN_URL . "js/chart.js"]],
    'settings' => ['css' => [], 'js' => []],
    'pages' => [
        'css' => [
            CDN_URL . "css/pages.css",
            CDN_URL . "css/codemirror.min.css",
            CDN_URL . "css/dracula.min.css"
        ],
        'js' => [
            CDN_URL . "js/codemirror/codemirror.min.js",
            CDN_URL . "js/codemirror/mode/xml.min.js",
            CDN_URL . "js/codemirror/mode/javascript.min.js",
            CDN_URL . "js/codemirror/mode/css.min.js",
            CDN_URL . "js/codemirror/mode/htmlmixed.min.js",
            CDN_URL . "js/codemirror/mode/clike.min.js",
            CDN_URL . "js/codemirror/mode/php.min.js",
            CDN_URL . "js/highlight.min.js",
            CDN_URL . "js/pages.js",
            CDN_URL . "js/Sortable.min.js",
            CDN_URL . "js/browser.js"
        ]
    ],
    // 'menu' routu kaldırıldı, Pages ile birleşti.
    'modules' => ['css' => [], 'js' => [CDN_URL . "js/modules.js"]],
    'themes' => ['css' => [], 'js' => [CDN_URL . "js/themes.js"]],
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

// 🚀 Dynamic Asset Loading (DB)
try {
    $stmtPage = $db->prepare("SELECT id FROM pages WHERE slug = ? AND is_active = 1");
    $stmtPage->execute([$page]);
    $pId = $stmtPage->fetchColumn();

    if ($pId) {
        $stmtAssets = $db->prepare("SELECT type, path FROM page_assets WHERE page_id = ? ORDER BY load_order ASC");
        $stmtAssets->execute([$pId]);
        $assets = $stmtAssets->fetchAll(PDO::FETCH_ASSOC);

        foreach ($assets as $ast) {
            // Check if path is absolute URL (http/https) or local
            $fullPath = (strpos($ast['path'], 'http') === 0)
                ? $ast['path']
                : BASE_URL . $ast['path'];

            if ($ast['type'] === 'css') {
                $currentAssets['css'][] = $fullPath;
            } elseif ($ast['type'] === 'js') {
                $currentAssets['js'][] = $fullPath;
            }
        }
    }
} catch (Throwable $e) {
    // Silent fail
}

// 🛠 Modül Admin Paneli Assetlerini Yükle
try {
    // Mevcut sayfa bir modülün admin sayfası mı?
    $stmtMod = $db->prepare("SELECT id FROM modules WHERE page_slug = ? AND is_active = 1");
    $stmtMod->execute([$page]);
    $moduleId = $stmtMod->fetchColumn();

    if ($moduleId) {
        $stmtModAssets = $db->prepare("SELECT type, path FROM module_assets 
                                       WHERE module_id = ? AND (location = 'admin' OR location = 'global')
                                       ORDER BY load_order ASC");
        $stmtModAssets->execute([$moduleId]);
        $mAssets = $stmtModAssets->fetchAll(PDO::FETCH_ASSOC);

        foreach ($mAssets as $ma) {
            $fPath = (strpos($ma['path'], 'http') === 0) ? $ma['path'] : BASE_URL . $ma['path'];
            if ($ma['type'] === 'css') {
                $currentAssets['css'][] = $fPath;
            } else {
                $currentAssets['js'][] = $fPath;
            }
        }
    }
} catch (Throwable $e) {
    // Hata durumunda sessiz kal
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($settings['default_lang'] ?? 'tr') ?>">

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
        const DEFAULT_SITE_LANG = "<?= htmlspecialchars($settings['default_lang'] ?? 'tr') ?>";
        const BASE_PATH = '<?= e(BASE_PATH) ?>';
        const BASE_URL = '<?= e(BASE_URL) ?>';
    </script>
    <!-- Global CSS -->
    <?php foreach ($globalCss as $css): 
        $v = APP_VERSION;
        if (!str_starts_with($css, 'http')) {
            $f = ROOT_DIR . ltrim(str_replace(BASE_URL, '', $css), '/');
            if (file_exists($f)) $v = (string)filemtime($f);
        }
    ?>
        <link rel="stylesheet" href="<?= e($css) ?>?v=<?= $v ?>">
    <?php endforeach; ?>

    <!-- Sayfa özel CSS -->
    <?php foreach ($currentAssets['css'] as $css): 
        $v = APP_VERSION;
        if (!str_starts_with($css, 'http')) {
            $f = ROOT_DIR . ltrim(str_replace(BASE_URL, '', $css), '/');
            if (file_exists($f)) $v = (string)filemtime($f);
        }
    ?>
        <link rel="stylesheet" href="<?= e($css) ?>?v=<?= $v ?>">
    <?php endforeach; ?>
</head>

<body>

    <nav class="navbar px-4 border-bottom">
        <div class="ms-auto d-flex align-items-center gap-3">
            <a href="<?= BASE_URL ?>php/profile.php?id=<?= $_SESSION['user_id'] ?>"
                class="d-flex align-items-center text-decoration-none me-3 transition-hover">
                <div class="nav-avatar-circle bg-primary text-white d-flex align-items-center justify-content-center me-2 shadow-sm"
                    style="width: 35px; height: 35px; border-radius: 50%; font-size: 14px;">
                    <i class="fas <?= e($finalAvatar) ?>"></i>
                </div>
                <span class="fw-bold text d-sm-inline"><?= e($finalName) ?></span>
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



    <!-- Mobile Tab Bar -->
    <div class="adm-mobile-tab-bar d-lg-none">
        <a href="index.php?page=<?= $default_page ?>"
            class="adm-tab-item <?= $page === $default_page ? 'active' : '' ?>">
            <i class="fa-solid fa-house"></i>
            <span lang="home">Ana Sayfa</span>
        </a>
        <a href="index.php?page=pages" class="adm-tab-item <?= $page === 'pages' ? 'active' : '' ?>">
            <i class="fas fa-file"></i>
            <span lang="pages">Sayfalar</span>
        </a>
        <a href="#" class="adm-tab-item" id="admMobileMenuBtn">
            <i class="fa-solid fa-bars"></i>
            <span lang="menu">Menü</span>
        </a>

    </div>

    <!-- Sidebar -->
    <div class="adm-sidebar" id="admSidebar">
        <div class="adm-sidebar-header">
            <i class="fa-solid fa-fire text-warning adm-nav-icon"></i>
            <span class="adm-nav-text fw-bold"><?= e($settings['site_name'] ?? 'SpeedPage') ?></span>
        </div>

        <nav class="adm-nav-list mt-3">
            <?php if (isset($is_admin) && $is_admin): ?>
                <a href="index.php?page=dashboard" class="adm-nav-item-link <?= $page === 'dashboard' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt adm-nav-icon"></i>
                    <span class="adm-nav-text" lang="dashboard">Dashboard</span>
                </a>

            <?php endif; ?>

            <?php if (isset($is_admin) && $is_admin): ?>
                <a href="index.php?page=settings" class="adm-nav-item-link <?= $page === 'settings' ? 'active' : '' ?>">
                    <i class="fas fa-cog adm-nav-icon"></i>
                    <span class="adm-nav-text" lang="settings">Ayarlar</span>
                </a>
            <?php endif; ?>

            <a href="index.php?page=pages" class="adm-nav-item-link <?= $page === 'pages' ? 'active' : '' ?>">
                <i class="fas fa-file adm-nav-icon"></i>
                <span class="adm-nav-text" lang="pagesmenu">Sayfalar ve Menüler</span>
            </a>

            <a href="index.php?page=modules" class="adm-nav-item-link <?= $page === 'modules' ? 'active' : '' ?>">
                <i class="fas fa-puzzle-piece adm-nav-icon"></i>
                <span class="adm-nav-text" lang="modules">Modüller</span>
            </a>

            <a href="index.php?page=themes"
                class="adm-nav-item-link <?= ($page === 'themes' || $page === 'theme-settings') ? 'active' : '' ?>">
                <i class="fas fa-palette adm-nav-icon"></i>
                <span class="adm-nav-text" lang="themes">Temalar</span>
            </a>

            <?php if (!empty($is_admin) && $is_admin): ?>
                <a href="index.php?page=users" class="adm-nav-item-link <?= $page === 'users' ? 'active' : '' ?>">
                    <i class="fas fa-users adm-nav-icon"></i>
                    <span class="adm-nav-text" lang="users">Kullanıcılar</span>
                </a>

                <a href="index.php?page=aipanel" class="adm-nav-item-link <?= $page === 'aipanel' ? 'active' : '' ?>">
                    <i class="fas fa-robot adm-nav-icon"></i>
                    <span class="adm-nav-text" lang="ai_panel">AI Panel</span>
                </a>

                <a href="index.php?page=browser" class="adm-nav-item-link <?= $page === 'browser' ? 'active' : '' ?>">
                    <i class="fas fa-folder-open adm-nav-icon"></i>
                    <span class="adm-nav-text" lang="filebrowser">Dosya Tarayıcı</span>
                </a>

                <a href="index.php?page=system" class="adm-nav-item-link <?= $page === 'system' ? 'active' : '' ?>">
                    <i class="fas fa-history adm-nav-icon"></i>
                    <span class="adm-nav-text" lang="system">Hata Kayıtları</span>
                </a>

                <a href="index.php?page=dbpanel" class="adm-nav-item-link <?= $page === 'dbpanel' ? 'active' : '' ?>">
                    <i class="fas fa-database adm-nav-icon"></i>
                    <span class="adm-nav-text" lang="database">Veritabanı</span>
                </a>

                <a href="index.php?page=migration" class="adm-nav-item-link <?= $page === 'migration' ? 'active' : '' ?>">
                    <i class="fas fa-exchange-alt adm-nav-icon"></i>
                    <span class="adm-nav-text" lang="migration_tool">Migration</span>
                </a>
            <?php endif; ?>

            <?php
            // Dynamic Module Admin Menus
            try {
                $modMenus = $db->query("SELECT name, admin_menu_title, admin_menu_url, admin_menu_icon, page_slug, permissions FROM modules WHERE is_active=1 AND admin_menu_url IS NOT NULL AND admin_menu_url != ''")->fetchAll(PDO::FETCH_ASSOC);
                if ($modMenus) {
                    echo '<div class="adm-nav-divider"></div>';
                    echo '<div class="adm-nav-section-title">Modüller</div>';
                    $userRole = $_SESSION['role'] ?? 'guest';
                    foreach ($modMenus as $mm) {
                        // Permission Check
                        $perms = !empty($mm['permissions']) ? json_decode($mm['permissions'], true) : [];
                        if (!empty($perms) && !in_array($userRole, $perms)) continue;

                        $mPage = $mm['page_slug'];
                        $activeClass = ($page === $mPage) ? 'active' : '';
                        $mIcon = $mm['admin_menu_icon'] ?? 'fa-puzzle-piece';
                        $mTitle = $mm['admin_menu_title'] ?? $mm['name'];
                        echo '<a href="index.php?page=' . e($mPage) . '" class="adm-nav-item-link ' . $activeClass . '">';
                        echo '<i class="fas ' . e($mIcon) . ' adm-nav-icon"></i>';
                        echo '<span class="adm-nav-text">' . e($mTitle) . '</span>';
                        echo '</a>';
                    }
                }
            } catch (Throwable $e) {
                // Ignore
            }
            ?>
        </nav>

        <div class="border-top border-secondary">
            <a href="../php/logout.php" class="adm-nav-item-link">
                <i class="fa-solid fa-right-from-bracket adm-nav-icon"></i>
                <span class="adm-nav-text" lang="logout">Çıkış</span>
            </a>
        </div>
    </div>

    <!-- İçerik -->
    <div class="container p-4 adm-main-content">
        <?php
        switch ($page) {
            case 'dashboard':
                if (isset($is_admin) && $is_admin)
                    require __DIR__ . "/dashboard-panel.php";
                else
                    echo "<div class='alert alert-danger'>" . e('Yetkisiz erişim.') . "</div>";
                break;
            case 'settings':
                if (isset($is_admin) && $is_admin)
                    require __DIR__ . "/settings-panel.php";
                else
                    echo "<div class='alert alert-danger'>Yetkisiz erişim.</div>";
                break;
            case 'pages':
                require __DIR__ . "/page-panel.php";
                break;
            // case 'menu': removed (merged)
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
            case 'browser':
                if (!empty($is_admin) && $is_admin)
                    require __DIR__ . "/browser-panel.php";
                break;
            case 'system':
                if (!empty($is_admin) && $is_admin)
                    require __DIR__ . "/system-panel.php";
                break;
            case 'dbpanel':
                if (!empty($is_admin) && $is_admin)
                    require __DIR__ . "/veripanel-content.php";
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
                // Check if it matches an active module
                $stmtMod = $db->prepare("SELECT name, admin_menu_url, permissions FROM modules WHERE page_slug = ? AND is_active = 1");
                $stmtMod->execute([$page]);
                $modInfo = $stmtMod->fetch(PDO::FETCH_ASSOC);

                if ($modInfo && !empty($modInfo['admin_menu_url'])) {
                    // --- PERMISSION CHECK ---
                    $perms = !empty($modInfo['permissions']) ? json_decode($modInfo['permissions'], true) : [];
                    $userRole = $_SESSION['role'] ?? 'guest';
                    if (!empty($perms) && !in_array($userRole, $perms)) {
                        echo "<div class='alert alert-danger shadow-sm rounded-4 py-4 text-center'>
                            <i class='fas fa-lock fs-1 mb-3 text-danger'></i>
                            <h4 class='fw-bold'>" . __('access_denied') . "</h4>
                            <p class='text-muted'>" . __('module_permission_error') . "</p>
                        </div>";
                    } else {
                        $modFile = ROOT_DIR . "modules/" . $modInfo['name'] . "/" . $modInfo['admin_menu_url'];
                        if (file_exists($modFile)) {
                            require $modFile;
                        } else {
                            echo "<div class='alert alert-danger'>Modül dosyası bulunamadı: " . e($modFile) . "</div>";
                        }
                    }
                } else {
                    echo "<p>Sayfa bulunamadı (404).</p>";
                }
        }

        ?>
    </div>

    <footer class="copyrightnote">
        <span lang="site_version"></span>
    </footer>

    <!-- Global JS -->
    <?php foreach ($globalJs as $js): 
        $v = APP_VERSION;
        if (!str_starts_with($js, 'http')) {
            $f = ROOT_DIR . ltrim(str_replace(BASE_URL, '', $js), '/');
            if (file_exists($f)) $v = (string)filemtime($f);
        }
    ?>
        <script src="<?= e($js) ?>?v=<?= $v ?>"></script>
    <?php endforeach; ?>

    <!-- Sayfa özel JS -->
    <?php foreach ($currentAssets['js'] as $js): 
        $v = APP_VERSION;
        if (!str_starts_with($js, 'http')) {
            $f = ROOT_DIR . ltrim(str_replace(BASE_URL, '', $js), '/');
            if (file_exists($f)) $v = (string)filemtime($f);
        }
    ?>
        <script src="<?= e($js) ?>?v=<?= $v ?>"></script>
    <?php endforeach; ?>
    <?php 
        $langJsV = APP_VERSION;
        $lFile = ROOT_DIR . 'cdn/js/lang.js';
        if (file_exists($lFile)) $langJsV = (string)filemtime($lFile);
    ?>
    <script src="<?= e(CDN_URL) ?>js/lang.js?v=<?= $langJsV ?>" defer></script>
    <?php if (function_exists('run_hook'))
        run_hook('footer_end'); ?>
</body>

</html>