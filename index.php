<?php
ob_start();
require_once 'settings.php';
require_once 'admin/db.php';

$q = $db->query("SELECT `key`, `value` FROM settings");
$settings = $q->fetchAll(PDO::FETCH_KEY_PAIR);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Admin için hata gösterimini aç (Geliştirme kolaylığı)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

require_once 'php/menu-loader.php';
require_once 'php/theme-init.php';
run_hook('init');

/**
 * ✅ Yönlendirme Kontrolü
 * site_public değeri '0' ise ve kullanıcı giriş yapmamışsa yönlendir.
 */
if (isset($settings['site_public']) && $settings['site_public'] == '0' && empty($_SESSION['user_id'])) {
    ob_clean();
    header("Location: " . BASE_URL . "php/login.php?maintenance=1");
    exit;
}

// ✅ Navbar menülerini yükle
$menus = getMenus('navbar');
// ✅ Dinamik sayfa başlığı 
$pageTitle = $settings['site_name'] ?? 'Site';
if (!empty($_GET['page'])) {
    $slug = $_GET['page'];
    $pageTitle .= " | " . ucfirst($slug);

    // 404 Logging
    // Check if page file exists in SAYFALAR_DIR
    // Note: This relies on the convention that page slug = filename.php
    $pageFile = SAYFALAR_DIR . $slug . '.php';
    if (!file_exists($pageFile) && $slug !== 'settings' && $slug !== 'admin') {
        // Check if it's a specialized route or just missing
        // For now, log as 404
        sp_log("Sayfa bulunamadı (404): $slug", "page_not_found");
    }
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <?php run_hook('head_start'); ?>
    <script>
        // Immediate theme check
        (function () {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
        let BASE_PATH = '<?= BASE_PATH ?>';
        if (!BASE_PATH.endsWith('/')) BASE_PATH += '/';
        const BASE_URL = "<?= BASE_URL ?>"; 
    </script>
    <meta charset="UTF-8">
    <meta name="description" content="<?= htmlspecialchars($settings['meta_description'] ?? '') ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle . ($settings['site_slogan'] ? ' - ' . $settings['site_slogan'] : '')) ?>
    </title>
    <link rel="stylesheet" href="<?= CDN_URL ?>css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CDN_URL ?>css/all.min.css">
    <!-- Frontend Core CSS Removed - Using Theme CSS -->
    <?php
    // Theme CSS
    $themeCss = "themes/" . ACTIVE_THEME . "/style.css";
    if (file_exists(ROOT_DIR . $themeCss)) {
        echo '<link rel="stylesheet" href="' . BASE_URL . $themeCss . '?v=' . filemtime(ROOT_DIR . $themeCss) . '">';
    }
    ?>
    <link rel="icon" href="favicon.ico">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#121212">
    <?php run_hook('head_end'); ?>
</head>

<body>

    <?php run_hook('before_navbar'); ?>

    <?php


    // Load Navbar
    load_theme_part('navbar');
    ?>

    <?php run_hook('after_navbar'); ?>
    <?php run_hook('before_content'); ?>

    <main id="app" class="container py-4 h-100">
        <div id="page-loader" class="text-center py-5 d-none">
            <div class="spinner-border text-secondary"></div>
        </div>

        <?php
        $showSidebar = get_theme_setting('show_sidebar', '0');
        $sidebarPos = get_theme_setting('sidebar_position', 'left');
        ?>

        <?php if ($showSidebar == '1'): ?>
            <div class="row g-4">
                <?php if ($sidebarPos === 'left'): ?>
                    <div class="col-md-3 d-none d-md-block">
                        <?php load_theme_part('sidebar'); ?>
                    </div>
                <?php endif; ?>

                <div class="col-md-9 col-12">
                    <?php run_hook('content_start'); ?>
                    <div id="page-content"></div>
                    <?php run_hook('content_end'); ?>
                </div>

                <?php if ($sidebarPos === 'right'): ?>
                    <div class="col-md-3 d-none d-md-block">
                        <?php load_theme_part('sidebar'); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php run_hook('content_start'); ?>
            <div id="page-content"></div>
            <?php run_hook('content_end'); ?>
        <?php endif; ?>
    </main>
    <?php run_hook('after_content'); ?>
    <?php run_hook('before_footer'); ?>

    <?php load_theme_part('footer'); ?>

    <?php run_hook('after_footer'); ?>
    <?php run_hook('footer_end'); ?>

    <script src="<?= CDN_URL ?>js/router.js"></script>
    <script src="<?= CDN_URL ?>js/bootstrap.bundle.min.js"></script>
    <?php if (ACTIVE_THEME !== 'fantastik'): ?>
        <script src="<?= CDN_URL ?>js/dark.js"></script>
    <?php endif; ?>
    <script src="<?= CDN_URL ?>js/lang.js"></script>

    <script>
        if ("serviceWorker" in navigator) {
            navigator.serviceWorker.register("<?= BASE_PATH ?>service-worker.js")
                .then(() => console.log("PWA aktif ✔️"))
                .catch(err => console.log("SW hata:", err));
        }
    </script>

</body>

</html>