<?php
declare(strict_types=1);
/**
 * Default Theme Functions & Hooks
 */

// 1. Favicon from Logo Hook
add_hook('head_start', function () {
    global $settings;
    if (!empty($settings['logo_url'])) {
        echo '<!-- Default Theme: Logo to Favicon -->
        <link rel="icon" type="image/png" href="' . e($settings['logo_url']) . '">';
    }
});

// 2. Dynamic Styles from Theme Settings
add_hook('head_end', function () {
    $headerColor = get_theme_setting('header_bg_color', '#1e85ecff');
    echo "<!-- Default Theme Settings CSS Injection -->
    <style>
        :root {
            --border-bg: $headerColor;
        }
        .navbar {border-bottom: 1px solid var(--border-bg) !important; }
    </style>";
});

// 3. Page Hero Hook (Content Start) - Glassmorphism Update
add_hook('content_start', function () {
    $enableHero = get_theme_setting('enable_hero_section', '1');
    if ($enableHero !== '1')
        return;

    global $pageTitle, $settings;
    $customTitle = get_theme_setting('hero_title', '');
    $finalHeroTitle = !empty($customTitle) ? $customTitle : $pageTitle;

    echo '<div class="hero-banner mb-5 d-flex align-items-center justify-content-center text-center">
        <div class="hero-glass">
            <h1 class="fw-black mb-2">' . e($finalHeroTitle) . '</h1>
            <p class="lead opacity-90 fw-medium m-0">' . e($settings['site_slogan'] ?? 'SpeedPage') . '</p>
        </div>
    </div>';
});

// 4. Social Share Hook (Content End) - Minimalist Update
add_hook('content_end', function () {
    $enableShare = get_theme_setting('enable_social_share', '1');
    if ($enableShare !== '1')
        return;

    $url = urlencode((isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
    echo '<div class="social-share-wrap mt-5 text-center">
        <h6 class="fw-bold mb-4 text-muted text-uppercase small" style="letter-spacing: 2px;" lang="share_title">' . __('share_title') . '</h6>
        <div class="d-flex justify-content-center gap-3">
            <a href="https://twitter.com/intent/tweet?url=' . $url . '" target="_blank" class="share-btn text-decoration-none" title="Twitter">
                <i class="fab fa-twitter"></i>
            </a>
            <a href="https://www.facebook.com/sharer/sharer.php?u=' . $url . '" target="_blank" class="share-btn text-decoration-none" title="Facebook">
                <i class="fab fa-facebook-f"></i>
            </a>
            <a href="https://wa.me/?text=' . $url . '" target="_blank" class="share-btn text-decoration-none" title="WhatsApp">
                <i class="fab fa-whatsapp"></i>
            </a>
            <a href="javascript:void(0)" onclick="navigator.clipboard.writeText(window.location.href); alert(\'' . __('link_copied') . '\')" class="share-btn text-decoration-none" title="Linki Kopyala">
                <i class="fas fa-link"></i>
            </a>
        </div>
    </div>';
});

// 5. Slogan Band Hook (Before Footer)
add_hook('before_footer', function () {
    global $settings;
    if (empty($settings['site_slogan']))
        return;

    echo '<div class="site-slogan-band py-5 text-center mt-5">
        <div class="container container-narrow">
            <h2 class="display-5 fw-black mb-0">' . e($settings['site_slogan']) . '</h2>
        </div>
    </div>';
});

// 6. Footer System Info Hook
add_hook('footer_end', function () {
    echo '<div class="text-center pb-5 opacity-40 small">
        <span lang="site_version"></span> | Default Theme
    </div>';
});
