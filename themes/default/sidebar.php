<?php
declare(strict_types=1);
/**
 * Default Theme Sidebar - Professional Update
 */
global $db, $settings;

// İstatistikleri Çek
$countPages = $db->query("SELECT COUNT(id) FROM pages WHERE is_active = 1")->fetchColumn();
$countUsers = $db->query("SELECT COUNT(id) FROM users")->fetchColumn();

// Son Eklenen Sayfalar
$stmt = $db->query("SELECT title, slug FROM pages WHERE is_active = 1 ORDER BY id DESC LIMIT 5");
$recentPages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hakkımızda metni (Theme settings)
$aboutText = get_theme_setting('custom_sidebar_text', 'SpeedPage CMS ile hayalinizdeki siteyi kurun.');
?>
<aside class="sidebar d-flex flex-column gap-4">

    <!-- Hakkımızda Bölümü -->
    <div class="sidebar-card">
        <h6 class="fw-bold mb-3 d-flex align-items-center text-uppercase small" style="letter-spacing: 1px;">
            <i class="fas fa-info-circle me-2 text-primary"></i> <span lang="about_us"><?= __('about_us') ?></span>
        </h6>
        <p class="small text-muted mb-0 leading-relaxed"><?= nl2br(e($aboutText)) ?></p>
    </div>

    <!-- İstatistikler -->
    <div class="sidebar-card">
        <h6 class="fw-bold mb-3 d-flex align-items-center text-uppercase small" style="letter-spacing: 1px;">
            <i class="fas fa-chart-pie me-2 text-success"></i> <span lang="site_status"><?= __('site_status') ?></span>
        </h6>
        <div class="row g-3">
            <div class="col-12">
                <div class="d-flex align-items-center justify-content-between p-3 rounded-4">
                    <span class="small fw-bold text-muted" lang="page_singular"><?= __('page_singular') ?></span>
                    <span class="stat-badge"><?= $countPages ?></span>
                </div>
            </div>
            <div class="col-12">
                <div class="d-flex align-items-center justify-content-between p-3 rounded-4">
                    <span class="small fw-bold text-muted" lang="user_singular"><?= __('user_singular') ?></span>
                    <span class="stat-badge"><?= $countUsers ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Son Sayfalar -->
    <div class="sidebar-card">
        <h6 class="fw-bold mb-3 d-flex align-items-center text-uppercase small" style="letter-spacing: 1px;">
            <i class="fas fa-sparkles me-2 text-warning"></i> <span lang="recent_pages"><?= __('recent_pages') ?></span>
        </h6>
        <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
            <?php foreach ($recentPages as $p): ?>
                <?php $url = ($settings['friendly_url'] === '1') ? (BASE_PATH . $p['slug']) : (BASE_URL . 'index.php?page=' . $p['slug']); ?>
                <li>
                    <a href="<?= e($url) ?>"
                        class="text-decoration-none d-flex align-items-center p-2 rounded-3 hover-opacity-100 transition-all border-bottom border-light">
                        <i class="fas fa-arrow-right me-3 text-primary opacity-50" style="font-size: 0.7rem;"></i>
                        <span class="small fw-medium"><?= e($p['title']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

</aside>