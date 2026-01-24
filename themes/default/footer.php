<?php
declare(strict_types=1);
/**
 * Default Theme Footer
 */
// Sütunları tanımla, varsayılan başlıkları boş bırak ki kullanıcı girmedikçe görünmesin
$footer_cols = [
    ['title' => get_theme_setting('footer_col1_title', ''), 'links' => get_theme_setting('footer_col1_links', '')],
    ['title' => get_theme_setting('footer_col2_title', ''), 'links' => get_theme_setting('footer_col2_links', '')],
    ['title' => get_theme_setting('footer_col3_title', ''), 'links' => get_theme_setting('footer_col3_links', '')],
    ['title' => get_theme_setting('footer_col4_title', ''), 'links' => get_theme_setting('footer_col4_links', '')],
];

// Sadece başlığı girilmiş olan sütunları filtrele
$active_cols = array_filter($footer_cols, function ($col) {
    return !empty(trim($col['title']));
});

$logo = trim($settings['logo_url'] ?? '');
$siteName = trim($settings['site_name'] ?? 'SpeedPage');
$footerText = get_theme_setting('footer_text', __('footer_text_default'));
$copyright = get_theme_setting('footer_copyright', __('copyright_default'));

// Sütun genişliğini aktif sütun sayısına göre ayarla (Maksimum 4 aktif sütun + 1 logo sütunu)
$col_count = count($active_cols);
$lg_col_class = "col-lg-2";
if ($col_count > 0 && $col_count <= 2) {
    $lg_col_class = "col-lg-3";
}
?>

<footer class="bd-footer py-5 mt-5 border-top bg-light">
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-3 mb-4">
                <a class="d-inline-flex align-items-center mb-2 link-dark text-decoration-none" href="<?= BASE_URL ?>"
                    aria-label="<?= e($siteName) ?>">
                    <?php if ($logo): ?>
                        <img src="<?= e($logo) ?>" alt="<?= e($siteName) ?>" style="height: 32px;" class="me-2">
                    <?php else: ?>
                        <i class="fas fa-bolt text-primary me-2 fs-3"></i>
                    <?php endif; ?>
                    <span class="fs-5 fw-bold"><?= e($siteName) ?></span>
                </a>
                <ul class="list-unstyled small text-muted">
                    <li class="mb-2"><?= e($footerText) ?></li>
                    <li class="mb-2"><?= e($copyright) ?></li>
                    <li class="mb-2 opacity-50" style="font-size: 0.75rem;"><span lang="site_version"></span></li>
                </ul>
            </div>

            <?php
            $first = true;
            foreach ($active_cols as $col):
                $offset_class = ($first && $col_count <= 3) ? 'offset-lg-1' : '';
                $first = false;
                ?>
                <div class="col-6 <?= $lg_col_class ?> <?= $offset_class ?> mb-3">
                    <h5 class="fw-bold mb-3"><?= e($col['title']) ?></h5>
                    <ul class="list-unstyled">
                        <?php
                        $lines = explode("\n", str_replace("\r", "", $col['links']));
                        foreach ($lines as $line):
                            if (empty(trim($line)))
                                continue;
                            $parts = explode("|", $line);
                            $title = trim($parts[0] ?? '');
                            $url = trim($parts[1] ?? '#');
                            if (!$title)
                                continue;
                            ?>
                            <li class="mb-2"><a href="<?= e($url) ?>"
                                    class="text-decoration-none text-muted"><?= e($title) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</footer>