<?php
$settings = $settings ?? [];
$globalAssets = $globalAssets ?? ['css' => [], 'js' => []];
$metaDesc = $metaDesc ?? '';
$metaKeys = $metaKeys ?? '';
$pageTitle = $pageTitle ?? '';
$siteSlogan = $siteSlogan ?? '';
?>
<!DOCTYPE html>
<html lang="<?= e($settings['default_lang'] ?? 'tr') ?>">

<head>
    <?php if (function_exists('run_hook'))
        run_hook('head_start'); ?>
    <script>
        // Immediate theme check
        (function () {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
        const DEFAULT_SITE_LANG = "<?= e($settings['default_lang'] ?? 'tr') ?>";
        let BASE_PATH = '<?= e(BASE_PATH) ?>';
        if (!BASE_PATH.endsWith('/')) BASE_PATH += '/';
        const BASE_URL = "<?= e(BASE_URL) ?>";
        const FRIENDLY_URL = "<?= e($settings['friendly_url'] ?? '0') ?>"; // Critical for Router.js
        const CSRF_TOKEN = "<?= e($_SESSION['csrf'] ?? '') ?>";
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($metaDesc) ?>">
    <title><?= e($pageTitle . ($siteSlogan ? ' - ' . $siteSlogan : '')) ?></title>
    <meta name="keywords" content="<?= e($metaKeys) ?>">
    <link rel="stylesheet" href="<?= CDN_URL ?>css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CDN_URL ?>css/all.min.css">
    <!-- Frontend Core CSS Removed - Using Theme CSS -->
    <?php
    // Module Global CSS
    if (isset($globalAssets['css']) && is_array($globalAssets['css'])) {
        foreach ($globalAssets['css'] as $gCss) {
            $v = APP_VERSION;
            if (!str_starts_with($gCss, 'http')) {
                $f = ROOT_DIR . ltrim($gCss, '/');
                if (file_exists($f)) $v = (string)filemtime($f);
            }
            echo '<link rel="stylesheet" href="' . e($gCss) . '?v=' . $v . '">';
        }
    }

    // Theme CSS - Hybrid: use mtime for development/live flexibility
    $themeCss = "themes/" . ACTIVE_THEME . "/style.css";
    if (file_exists(ROOT_DIR . $themeCss)) {
        $mtime = (string)filemtime(ROOT_DIR . $themeCss);
        echo '<link rel="stylesheet" href="' . BASE_URL . $themeCss . '?v=' . $mtime . '">';
    }
    ?>
    <link rel="icon" href="<?= e(BASE_URL) ?>favicon.ico">
    <link rel="manifest" href="<?= e(BASE_URL) ?>pwa-manifest.php">
    <meta name="theme-color" content="<?= e($settings['theme_color'] ?? '#121212') ?>">
    <?php if (function_exists('run_hook'))
        run_hook('head_end'); ?>
</head>
<body>

