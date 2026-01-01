<?php
require_once 'settings.php';
session_start();

// --- 1. FONKSİYONLAR ---
function check_chmod($path)
{
    return is_writable(ROOT_DIR . $path) ? '<span class="text-success">Yazılabilir</span>' : '<span class="text-danger">Yazılamaz! (CHMOD 755/777 yapın)</span>';
}

// DB Sıfırlama İşlemi (Sadece POST ile)
$db_action_status = "";
if (isset($_POST['reset_db'])) {
    try {
        $db_file = DB_PATH;
        $db = new PDO("sqlite:" . $db_file);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $tables = ['logs', 'login_attempts', 'modules', 'module_assets', 'page_assets', 'theme_settings'];
        foreach ($tables as $t) {
            $db->exec("DELETE FROM $t");
        }
        $db->exec("DELETE FROM pages WHERE id > 1");
        $db->exec("DELETE FROM menus WHERE id > 1");
        $db->exec("DELETE FROM users WHERE id > 1");
        $db->exec("UPDATE sqlite_sequence SET seq = 0");
        $db = null;

        usleep(30000);
        $c = new PDO("sqlite:" . $db_file);
        $c->exec("VACUUM");
        $db_action_status = "success";
    } catch (Exception $e) {
        $db_action_status = "error: " . $e->getMessage();
    }
}

// --- 2. DOSYA ANALİZİ (HARİTA) ---
$protected_files = [
    '' => ['index.php', 'page.php', 'settings.php', 'onar.php', 'README.md', 'favicon.ico', 'manifest.json', 'service-worker.js'],
    'sayfalar' => ['home.php', 'index.php'],
    'modules' => ['index.php'],
    'admin/veritabanı' => ['data.db', 'index.php', '.htaccess'],
    'admin' => [
        'auth.php',
        'browser-islem.php',
        'browser-panel.php',
        'db.php',
        'index.php',
        'menu-panel.php',
        'modul-func.php',
        'modules-panel.php',
        'page-actions.php',
        'page-panel.php',
        'settings-edit.php',
        'settings-panel.php',
        'system-panel.php',
        'theme-settings.php',
        'themes-panel.php',
        'user-edit.php',
        'user-panel.php',
        'veripanel-content.php',
        'verislem.php'
    ],
    'php' => [
        'captcha_lib.php',
        'hooks.php',
        'index.php',
        'logger.php',
        'login.php',
        'logout.php',
        'menu-loader.php',
        'profile.php',
        'register.php',
        'theme-init.php',
        'user_auth.php'
    ],
    'themes/default' => ['functions.php', 'navbar.php', 'footer.php', 'sidebar.php', 'style.css', 'theme.json', 'settings.json'],
    'themes/midnight' => ['functions.php', 'navbar.php', 'footer.php', 'style.css', 'theme.json', 'settings.json'],
    'cdn/css' => ['all.min.css', 'bootstrap.min.css', 'pages.css', 'system.css', 'admin-core.css'],
    'cdn/js' => [
        'admin.js',
        'bootstrap.bundle.min.js',
        'browser.js',
        'chart.js',
        'dark.js',
        'dbpanel.js',
        'jquery-3.7.1.min.js',
        'lang.js',
        'menu.js',
        'modules.js',
        'pages.js',
        'router.js',
        'system.js',
        'user.js'
    ],
    'cdn/images' => ['icon-192.png', 'icon-512.png'],
    'cdn/lang' => ['en.json', 'tr.json']
];

$missing_files = [];
$extra_files = [];
foreach ($protected_files as $dir => $allowed) {
    // Normalise empty dir for root
    $dir_path = $dir === '' ? '' : $dir . '/';
    $full_path = ROOT_DIR . $dir;

    if (!is_dir($full_path)) {
        $missing_files[] = $dir . " (Klasör Eksik!)";
        continue;
    }

    $found = scandir($full_path);
    foreach ($found as $f) {
        if ($f === '.' || $f === '..')
            continue;
        // Ignore dynamic themes and modules not in the protected list
        if ($dir === 'themes' || $dir === 'modules')
            continue;

        if (!in_array($f, $allowed) && !is_dir($full_path . '/' . $f))
            $extra_files[] = $dir_path . $f;
    }
    foreach ($allowed as $a) {
        if (!file_exists($full_path . '/' . $a))
            $missing_files[] = $dir_path . $a;
    }
}
?>

<!DOCTYPE html>
<html lang="tr" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="<?= CDN_URL ?>css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CDN_URL ?>css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>themes/default/style.css">
    <title>Onarım Merkezi - SpeedPage</title>
    <style>
        body {
            background: #0f172a !important;
            color: #f1f5f9 !important;
        }

        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 1.5rem;
        }

        .card-header {
            background: rgba(255, 255, 255, 0.05) !important;
            color: #fff !important;
            border-bottom: 1px solid #334155;
            padding: 1.25rem;
        }

        .table {
            color: #cbd5e1;
        }

        .text-success {
            color: #4ade80 !important;
        }

        .text-danger {
            color: #f87171 !important;
        }

        .btn-danger {
            background: #ef4444;
            border: none;
            border-radius: 1rem;
            padding: 0.8rem;
            font-weight: 600;
        }

        .btn-outline-secondary {
            border-color: #475569;
            color: #94a3b8;
            border-radius: 1rem;
        }
    </style>
</head>

<body class="p-4">

    <div class="container py-5" style="max-width: 1000px;">
        <div class="text-center mb-5">
            <h1 class="fw-bold mb-2">🛠️ SpeedPage Onarım Merkezi</h1>
            <p class="opacity-50">Sistem bütünlüğünü kontrol edin ve kritik onarımları gerçekleştirin.</p>
        </div>

        <?php if ($db_action_status === "success"): ?>
            <div class="alert alert-success">✅ Veritabanı başarıyla sıfırlandı!</div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-md-7">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-bold">🔍 Sistem Analizi</div>
                    <div class="card-body">
                        <table class="table table-sm small">
                            <tr>
                                <td>PHP Versiyonu</td>
                                <td><?= PHP_VERSION ?> (Min 8.0)</td>
                            </tr>
                            <tr>
                                <td>Veritabanı Yazma</td>
                                <td><?= check_chmod('admin/veritabanı') ?></td>
                            </tr>
                            <tr>
                                <td>Sayfalar Yazma</td>
                                <td><?= check_chmod('sayfalar') ?></td>
                            </tr>
                            <tr>
                                <td>Modüller Yazma</td>
                                <td><?= check_chmod('modules') ?></td>
                            </tr>
                            <tr class="table-info">
                                <td>Tanımlı URL</td>
                                <td><code><?= BASE_URL ?></code></td>
                            </tr>
                        </table>

                        <h6 class="mt-4 fw-bold text-primary">Dosya Durumu</h6>
                        <?php if (empty($missing_files) && empty($extra_files)): ?>
                            <p class="text-success small"><i class="fa fa-check-circle"></i> Dosya yapısı mükemmel.</p>
                        <?php else: ?>
                            <div style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($missing_files as $m): ?>
                                    <div class="text-danger small"><i class="fa fa-times"></i> Eksik: <?= $m ?></div>
                                <?php endforeach; ?>
                                <?php foreach ($extra_files as $x): ?>
                                    <div class="text-warning small"><i class="fa fa-info-circle"></i> Fazlalık: <?= $x ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="card shadow-sm border-danger h-100">
                    <div class="card-header bg-danger text-white fw-bold">⚙️ Tehlikeli Bölge</div>
                    <div class="card-body text-center">
                        <p class="small text-muted">Aşağıdaki işlem veritabanındaki tüm test verilerini, logları ve
                            eklenen sayfaları siler.</p>

                        <form method="POST" onsubmit="return confirm('Tüm veriler silinecek! Emin misiniz?');">
                            <button type="submit" name="reset_db" class="btn btn-danger w-100 mb-2">
                                <i class="fa fa-trash-can me-2"></i> Fabrika Ayarlarına Dön
                            </button>
                        </form>

                        <a href="index.php" class="btn btn-outline-secondary w-100">
                            <i class="fa fa-arrow-left me-2"></i> Panele Geri Dön
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 text-center">
            <small class="text-muted">SpeedPage Onarım Aracı - Bu dosyayı işiniz bittiğinde sunucudan silin.</small>
        </div>
    </div>

</body>

</html>