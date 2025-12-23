<?php
ob_start();
require_once 'settings.php';
require_once 'admin/db.php';
require_once 'php/menu-loader.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** * ✅ PDO::FETCH_KEY_PAIR kullanımı:
 * Bu metod, SELECT ile çekilen ilk sütunu ('key') anahtar, 
 * ikinci sütunu ('value') ise değer yaparak doğrudan bir dizi oluşturur.
 */
$q = $db->query("SELECT `key`, `value` FROM settings");
$settings = $q->fetchAll(PDO::FETCH_KEY_PAIR);

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
if (!empty($_GET['page'])) { $pageTitle .= " | " . ucfirst($_GET['page']); }
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <script>
        let BASE_PATH = '<?= BASE_PATH ?>';
        if (!BASE_PATH.endsWith('/')) BASE_PATH += '/';
        const BASE_URL = "<?= BASE_URL ?>"; 
     </script>
    <meta charset="UTF-8">
    <meta name="description" content="<?= htmlspecialchars($settings['meta_description'] ?? '') ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= CDN_URL ?>css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CDN_URL ?>css/all.min.css">
    <link rel="stylesheet" href="<?= CDN_URL ?>css/style.css">
    <link rel="icon" href="favicon.ico">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#121212">
</head>
<body>

<nav class="navbar px-4 py-3">
    <?php foreach ($menus as $m): ?>
        <?php
            $url = $m['external_url']
                ? $m['external_url']
                : '?page=' . $m['slug'];
        ?>
        <a href="<?= htmlspecialchars($url) ?>" class="me-3">
            <?php if (!empty($m['icon'])): ?>
                <i class="fa <?= htmlspecialchars($m['icon']) ?>"></i>
            <?php endif; ?>
            <?= htmlspecialchars($m['title']) ?>
        </a>
    <?php endforeach; ?>

    <div class="ms-auto d-flex align-items-center gap-3">

   <?php if (empty($_SESSION['user_id'])): ?> 
    <!-- Login --> 
     <a href="<?= BASE_URL ?>php/login.php" class="btn btn-outline-primary btn-sm"><span lang="login"></span></a>
    <!-- Register --> 
    <?php if (!empty($settings['registration_enabled']) && $settings['registration_enabled'] == '1'): ?>
        <a href="<?= BASE_URL ?>php/register.php" class="btn btn-primary btn-sm"><span lang="create_account"></span></a> 
    <?php endif; ?> 
    <?php else: ?> 
    <?php 
        // Kullanıcı adını belirle 
        $displayName = 'Kullanıcı'; 
        if (!empty($_SESSION['username'])) { $displayName = $_SESSION['username']; } 
        elseif (!empty($_SESSION['name'])) { $displayName = $_SESSION['name']; } 
        elseif (!empty($_SESSION['email'])) { $displayName = $_SESSION['email']; } 
        // Veritabanından güncel kullanıcı bilgilerini çekelim
        $userQuery = $db->prepare("SELECT display_name, username, avatar_url FROM users WHERE id = ?");
        $userQuery->execute([$_SESSION['user_id']]);
        $currentUser = $userQuery->fetch();

        // Görünen isim önceliği: display_name > username > 'Kullanıcı'
        $finalName = $currentUser['display_name'] ?: ($currentUser['username'] ?: 'Kullanıcı');
        $finalAvatar = $currentUser['avatar_url'] ?: 'fa-user';
        
        
        ?> 
        <div class="user-profile-nav d-flex align-items-center">
        <a href="<?= BASE_URL ?>php/profile.php?id=<?= $_SESSION['user_id'] ?>" 
           class="d-flex align-items-center text-decoration-none me-3 transition-hover">
            
            <div class="nav-avatar-circle bg-primary text-white d-flex align-items-center justify-content-center me-2 shadow-sm" 
                 style="width: 35px; height: 35px; border-radius: 50%; font-size: 14px;">
                <i class="fas <?= htmlspecialchars($finalAvatar) ?>"></i>
            </div>
            
            <span class="fw-bold text d-none d-sm-inline"> 
                <?= htmlspecialchars($finalName) ?> 
            </span>
        </a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="<?= BASE_URL ?>admin/index.php" class="btn btn-warning btn-sm px-3 rounded-pill me-2 shadow-sm" title="Yönetim Paneli">
                <i class="fa-solid fa-gauge-high"></i>
                <span class="visually-hidden" lang="admin_panel"></span>
            </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>php/logout.php" class="btn btn-outline-danger btn-sm px-3 rounded-pill">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
    <?php endif; ?>
    <!-- Tema butonu -->
    <button id="theme-toggle" onclick="toggleTheme()"><i class="fas fa-moon"></i></button>

    <!-- Dil seçimi -->
    <select id="lang-select">
        <option value="tr">TR</option>
        <option value="en">EN</option>
    </select>

</div>

</nav>
<?php
// ✅ Slogan boşsa varsayılan metin
$slogan = trim($settings['site_slogan'] ?? '');
if ($slogan === '') {
    $slogan = "Sitemize Hoş Geldiniz!";
}

// ✅ Logo varsa gösterilecek
$logo = trim($settings['logo_url'] ?? '');
?>

<?php if ($slogan || $logo): ?>
<div class="container-fluid px-4 mt-3">
    <div class="row align-items-center g-3">

        <!-- ✅ Slogan sütunu -->
        <div class="col-md-6 col-12">
            <h4 class="m-0" style="font-weight:600; font-size:1.3rem;">
                <?= htmlspecialchars($slogan) ?>
            </h4>
        </div>

        <!-- ✅ Logo sütunu (sadece logo varsa görünür) -->
        <?php if (!empty($logo)): ?>
        <div class="col-md-6 col-12 text-md-end text-center">
            <img src="<?= htmlspecialchars($logo) ?>"
                 alt="Logo"
                 style="height:40px; object-fit:contain;">
        </div>
        <?php endif; ?>

    </div>
</div>
<?php endif; ?>



<main id="app" class="container-fluid py-4">
    <div id="page-loader" class="text-center py-5 d-none">
        <div class="spinner-border text-secondary"></div>
    </div>

    <div id="page-content"></div>
</main>
<footer class="copyrightnote">
    <span lang="site_version">SpeedPage 0.1 Alpha</span>
</footer>

<script src="<?= CDN_URL ?>js/router.js"></script>
<script src="<?= CDN_URL ?>js/bootstrap.bundle.min.js"></script>
<script src="<?= CDN_URL ?>js/dark.js"></script>
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

