<?php
declare(strict_types=1);
/**
 * Default Theme Navbar
 */
$logo = trim($settings['logo_url'] ?? '');
$siteName = trim($settings['site_name'] ?? 'SpeedPage');
?>
<nav class="navbar px-4 py-3 shadow-sm sticky-top">
    <!-- Mobile Menu Button -->
    <button id="mobile-menu-toggle" class="btn btn-sm d-lg-none me-3 shadow-none border-0 bg-transparent">
        <i class="fas fa-bars fs-4"></i>
    </button>

    <!-- Brand / Logo -->
    <a class="navbar-brand d-flex align-items-center text-decoration-none me-4" href="<?= BASE_URL ?>">
        <?php if ($logo): ?>
            <img src="<?= e($logo) ?>" alt="<?= e($siteName) ?>" style="height: 32px; object-fit: contain;" class="me-2">
        <?php endif; ?>
        <span class="fw-bold h5 mb-0"><?= e($siteName) ?></span>
    </a>

    <!-- Menüler (Desktop) -->
    <div class="d-none d-lg-flex align-items-center">
        <?php foreach ($menus as $m): ?>
            <?php
            if ($m['external_url']) {
                $url = $m['external_url'];
            } else {
                $url = ($settings['friendly_url'] === '1') ? (BASE_PATH . $m['slug']) : (BASE_URL . 'index.php?page=' . $m['slug']);
            }
            ?>
            <a href="<?= e($url) ?>" class="nav-link px-3 fw-medium">
                <?php if (!empty($m['icon'])): ?>
                    <i class="fa <?= e($m['icon']) ?> me-1 small opacity-75"></i>
                <?php endif; ?>
                <?= e($m['title']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Sağ Taraf -->
    <div class="ms-auto d-flex align-items-center gap-2 gap-md-3">
        <?php if (empty($_SESSION['user_id'])): ?>
            <a href="<?= BASE_URL ?>php/login.php"
                class="btn btn-outline-primary btn-sm rounded-pill px-3 d-none d-sm-inline-block"><span
                    lang="login"></span></a>
            <?php if (!empty($settings['registration_enabled']) && $settings['registration_enabled'] == '1'): ?>
                <a href="<?= BASE_URL ?>php/register.php"
                    class="btn btn-primary btn-sm rounded-pill px-3 d-none d-sm-inline-block"><span
                        lang="create_account"></span></a>
            <?php endif; ?>
        <?php else: ?>
            <?php
            $userQuery = $db->prepare("SELECT display_name, username, avatar_url FROM users WHERE id = ?");
            $userQuery->execute([$_SESSION['user_id']]);
            $currentUser = $userQuery->fetch();
            $finalName = $currentUser['display_name'] ?: ($currentUser['username'] ?: 'Kullanıcı');
            $finalAvatar = $currentUser['avatar_url'] ?: 'fa-user';
            ?>
            <div class="user-profile-nav d-flex align-items-center">
                <a href="<?= BASE_URL ?>php/profile.php?id=<?= $_SESSION['user_id'] ?>"
                    class="d-flex align-items-center text-decoration-none me-2">
                    <div class="nav-avatar-circle bg-primary text-white d-flex align-items-center justify-content-center me-md-2 shadow-sm"
                        style="width: 32px; height: 32px; border-radius: 50%; font-size: 12px;">
                        <i class="fas <?= e($finalAvatar) ?>"></i>
                    </div>
                    <span class="fw-bold d-none d-md-inline small"><?= e($finalName) ?></span>
                </a>
                <div class="vr mx-2 opacity-25 d-none d-sm-block" style="height: 20px;"></div>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="<?= e(BASE_URL) ?>admin/index.php" class="btn btn-sm rounded-circle me-1 me-md-2"
                        title="Yönetim Paneli"><i class="fa-solid fa-gauge-high"></i></a>
                <?php endif; ?>
                <a href="<?= e(BASE_URL) ?>php/logout.php" class="btn btn-sm rounded-circle text-danger" title="Çıkış Yap"><i
                        class="fa-solid fa-right-from-bracket"></i></a>
            </div>
        <?php endif; ?>

        <button id="theme-toggle" class="btn btn-sm rounded-pill px-2"><i class="fas fa-moon"></i></button>

        <select id="lang-select" class="form-select form-select-sm border-0 rounded-pill px-2 px-md-3"
            style="width: auto;">
            <option value="tr">TR</option>
            <option value="en">EN</option>
        </select>
    </div>
</nav>

<!-- Mobile Sidebar -->
<div id="mobile-sidebar" class="mobile-sidebar shadow">
    <div class="sidebar-header d-flex align-items-center p-3 border-bottom">
        <button id="mobile-menu-close" class="btn btn-sm me-3 shadow-none border-0 bg-transparent text-dark">
            <i class="fas fa-times fs-4"></i>
        </button>
        <span class="fw-bold h5 mb-0">
            <?= e($siteName) ?>
        </span>
    </div>
    <div class="sidebar-body p-3">
        <?php foreach ($menus as $m): ?>
            <?php
            // Eğer harici bir link değilse, sistem ayarına göre link oluştur
            if ($m['external_url']) {
                $url = $m['external_url'];
            } else {
                // PHP tarafında da Friendly URL kontrolü
                $url = ($settings['friendly_url'] === '1') ? (BASE_PATH . $m['slug']) : (BASE_URL . 'index.php?page=' . $m['slug']);
            }
            ?>
            <a href="<?= e($url) ?>" class="nav-link-mobile">
                <i class="fa <?= !empty($m['icon']) ? e($m['icon']) : 'fa-link' ?>"></i>
                <span>
                    <?= e($m['title']) ?>
                </span>
            </a>
        <?php endforeach; ?>

        <hr class="my-3 opacity-10">

        <?php if (empty($_SESSION['user_id'])): ?>
            <a href="<?= BASE_URL ?>php/login.php" class="nav-link-mobile">
                <i class="fas fa-sign-in-alt"></i>
                <span lang="login"></span>
            </a>
            <?php if (!empty($settings['registration_enabled']) && $settings['registration_enabled'] == '1'): ?>
                <a href="<?= BASE_URL ?>php/register.php" class="nav-link-mobile">
                    <i class="fas fa-user-plus"></i>
                    <span lang="create_account"></span>
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<div id="mobile-sidebar-overlay" class="mobile-sidebar-overlay"></div>
<?php /* JS moved to cdn/js/navbar.js */ ?>
