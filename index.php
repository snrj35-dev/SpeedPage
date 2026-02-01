<?php
declare(strict_types=1);

// index.php en üst kısım
// 1. Veritabanı dosyası YOKSA ve kurulum dosyası VARSA kuruluma yönlendir
if (!file_exists(__DIR__ . '/admin/_internal_storage/data_secure/data.db')) {
    if (file_exists(__DIR__ . '/install.php')) {
        header("Location: install.php");
        exit;
    } else {
        // Hem veritabanı yok hem install.php silinmişse kullanıcıya bir uyarı göster
        http_response_code(500);
        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head><meta charset="UTF-8"><title>Sistem Hatası</title><link rel="stylesheet" href="cdn/css/bootstrap.min.css"></head>
        <body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh;">
            <div class="alert alert-danger shadow-sm py-4 px-5 rounded-4">
                <h4 class="fw-bold">Sistem Kurulu Değil</h4>
                <p class="mb-0">Veritabanı ve kurulum dosyası (install.php) bulunamadı. Lütfen dosyaları kontrol edin.</p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

ob_start();
require_once 'settings.php';
require_once 'admin/db.php';
/** @var PDO $db */
global $db;

// 🚀 Load Module Hooks
if (function_exists('sp_load_module_hooks')) {
    sp_load_module_hooks();
}

// 📦 Fetch Global Module Assets
$globalAssets = ['css' => [], 'js' => []];
try {
    $stmtG = $db->query("SELECT ma.type, ma.path FROM module_assets ma 
                         JOIN modules m ON ma.module_id = m.id 
                         WHERE m.is_active = 1 AND ma.location = 'global' 
                         ORDER BY ma.load_order ASC");
    $gRaw = $stmtG->fetchAll(PDO::FETCH_ASSOC);
    foreach ($gRaw as $gr) {
        $path = $gr['path'];
        // Path logic (same as other places)
        if (!str_starts_with($path, 'http')) {
            $path = BASE_URL . $path . '?v=' . APP_VERSION;
        }
        $globalAssets[$gr['type']][] = $path;
    }
} catch (Throwable $e) { }

// Load Settings
$stmt = $db->query("SELECT `key`, `value` FROM settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// 1. FORCE PROTOCOL (HTTP / HTTPS)
if (isset($settings['site_protocol']) && !empty($_SERVER['HTTP_HOST'])) {
    $expected_proto = $settings['site_protocol']; // 'http' or 'https'
    $current_proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';

    // Redirect if protocol mismatch (be careful on local)
    if ($expected_proto !== $current_proto && $_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['REMOTE_ADDR'] !== '127.0.0.1') {
        header("Location: " . $expected_proto . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Enable errors for admin
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

require_once 'php/menu-loader.php';
require_once 'php/theme-init.php';

if (function_exists('run_hook')) {
    run_hook('init');
}

/**
 * Redirect Check
 * If site_public is '0' and user is not logged in, redirect to login.
 */
if (isset($settings['site_public']) && $settings['site_public'] == '0' && empty($_SESSION['user_id'])) {
    ob_clean();
    header("Location: " . BASE_URL . "php/login.php?maintenance=1");
    exit;
}

// Load Navbar Menus
$menus = getMenus('navbar');

// Dynamic Page Title
$siteName = $settings['site_name'] ?? 'SpeedPage';
$pageTitle = $siteName;

$pageInput = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (!empty($pageInput)) {
    // Validate slug
    if (preg_match('/^[a-z0-9_-]+$/', $pageInput)) {
        // Title update for initial shell
        $pageTitle .= " | " . ucfirst($pageInput);
    }
}

$siteSlogan = $settings['site_slogan'] ?? '';
$metaDesc = $settings['meta_description'] ?? '';
$metaKeys = $settings['meta_keywords'] ?? '';

?>
<?php
load_theme_part('header');
?>


    <?php if (file_exists(__DIR__ . '/install.php')): ?>
        <div
            style="background: #dc3545; color: white; padding: 10px; text-align: center; font-weight: bold; position: sticky; top: 0; z-index: 9999;">
            <?= __('security_install_file_warning') ?>
        </div>
    <?php endif; ?>


    <?php if (function_exists('run_hook'))
        run_hook('before_navbar'); ?>

    <?php
    // Load Navbar
    load_theme_part('navbar');
    ?>

    <?php if (function_exists('run_hook'))
        run_hook('after_navbar'); ?>
    <?php if (function_exists('run_hook'))
        run_hook('before_content'); ?>

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
                    <?php if (function_exists('run_hook'))
                        run_hook('content_start'); ?>
                    <div id="page-content"></div>
                    <?php if (function_exists('run_hook'))
                        run_hook('content_end'); ?>
                </div>

                <?php if ($sidebarPos === 'right'): ?>
                    <div class="col-md-3 d-none d-md-block">
                        <?php load_theme_part('sidebar'); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php if (function_exists('run_hook'))
                run_hook('content_start'); ?>
            <div id="page-content"></div>
            <?php if (function_exists('run_hook'))
                run_hook('content_end'); ?>
        <?php endif; ?>
    </main>
    <?php if (function_exists('run_hook'))
        run_hook('after_content'); ?>
    <?php if (function_exists('run_hook'))
        run_hook('before_footer'); ?>

    <?php load_theme_part('footer'); ?>

    <?php if (function_exists('run_hook'))
        run_hook('after_footer'); ?>
    <?php if (function_exists('run_hook'))
        run_hook('footer_end'); ?>
