<?php
/**
 * Shared Theme Initialization Logic
 */

// 1. Identify Active Theme
if (!defined('ACTIVE_THEME')) {
    global $db, $settings;

    $defaultTheme = $settings['active_theme'] ?? 'default';
    $finalTheme = $defaultTheme;

    // Check if user theme choice is allowed
    if (isset($settings['allow_user_theme']) && $settings['allow_user_theme'] == '1' && !empty($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT preferred_theme FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $pref = $stmt->fetchColumn();
        if ($pref) {
            $finalTheme = $pref;
        }
    }
    define('ACTIVE_THEME', $finalTheme);

    // 1.1 Load Theme Functions
    $functionsFile = ROOT_DIR . "themes/" . ACTIVE_THEME . "/functions.php";
    if (file_exists($functionsFile)) {
        require_once $functionsFile;
    }
}

// 2. Global Part Loader
if (!function_exists('load_theme_part')) {
    function load_theme_part($part)
    {
        global $db, $menus, $settings, $currentUser;

        $themeFile = ROOT_DIR . "themes/" . ACTIVE_THEME . "/$part.php";
        if (file_exists($themeFile)) {
            include $themeFile;
        } else {
            // Fallback to default
            include ROOT_DIR . "themes/default/$part.php";
        }
    }
}

// 3. Theme Setting Getter
if (!function_exists('get_theme_setting')) {
    function get_theme_setting($key, $default = '')
    {
        global $db;
        static $themeSettings = null;

        if ($themeSettings === null) {
            try {
                $stmt = $db->prepare("SELECT setting_key, setting_value FROM theme_settings WHERE theme_name = ?");
                $stmt->execute([ACTIVE_THEME]);
                $themeSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (Exception $e) {
                $themeSettings = [];
            }
        }

        return $themeSettings[$key] ?? $default;
    }
}
