<?php
declare(strict_types=1);

// SpeedPage Installer (Beta 2.2)
// Localization & UX Improvements

// --- 0. Localization System ---
$langCode = 'tr'; // Default
if (isset($_GET['lang']) && in_array($_GET['lang'], ['tr', 'en'])) {
    $langCode = $_GET['lang'];
} elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    $langCode = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) === 'tr' ? 'tr' : 'en';
}

$txt = [
    'tr' => [
        'title' => '🚀 SpeedPage Kurulum',
        'site_settings' => 'Site Ayarları',
        'db_settings' => 'Veritabanı Ayarları',
        'site_name' => 'Site Adı',
        'admin_user' => 'Admin Kullanıcı Adı',
        'admin_pass' => 'Admin Şifre',
        'db_type' => 'Veritabanı Türü',
        'host' => 'MySQL Host',
        'dbname' => 'Veritabanı Adı',
        'dbuser' => 'Kullanıcı Adı',
        'dbpass' => 'Şifre',
        'install_btn' => 'Kurulumu Tamamla',
        'update_btn' => 'Ayarları Güncelle',
        'success_title' => 'Kurulum Başarılı!',
        'success_msg' => 'SpeedPage başarıyla kuruldu.',
        'security_warning' => 'GÜVENLİK UYARISI: Lütfen install.php dosyasını siliniz.',
        'go_site' => 'Siteye Git',
        'error' => 'Hata',
        'fill_all' => 'Lütfen tüm alanları doldurun.',
        'mysql_required' => 'MySQL seçildiğinde tüm veritabanı bilgileri zorunludur.',
        'write_error' => 'Klasör oluşturulamadı:',
        'db_conn_error' => 'Veritabanı bağlantı hatası:',
        'installed_warning' => 'Sistem zaten kurulu görünüyor (settings.php mevcut).',
        'installed_info' => 'Formu doldurarak ayarları DÜZELTEBİLİR veya GÜNCELLEYEBİLİRSİNİZ. Bu işlem mevcut settings.php dosyasını yeniden yazacaktır.',
        'reinstall_confirm' => 'Mevcut ayarların üzerine yazılmasını kabul ediyorum.',
        'test_connection' => 'Bağlantıyı Test Et',
        'test_success' => '✅ Bağlantı Başarılı!',
        'test_fail' => '❌ Hata: '
    ],
    'en' => [
        'title' => '🚀 SpeedPage Installer',
        'site_settings' => 'Site Settings',
        'db_settings' => 'Database Settings',
        'site_name' => 'Site Name',
        'admin_user' => 'Admin Username',
        'admin_pass' => 'Admin Password',
        'db_type' => 'Database Type',
        'host' => 'MySQL Host',
        'dbname' => 'Database Name',
        'dbuser' => 'Username',
        'dbpass' => 'Password',
        'install_btn' => 'Complete Installation',
        'update_btn' => 'Update Settings',
        'success_title' => 'Installation Successful!',
        'success_msg' => 'SpeedPage has been installed successfully.',
        'security_warning' => 'SECURITY WARNING: Please delete install.php file.',
        'go_site' => 'Go to Site',
        'error' => 'Error',
        'fill_all' => 'Please fill in all fields.',
        'mysql_required' => 'All database fields are required for MySQL.',
        'write_error' => 'Could not create directory:',
        'db_conn_error' => 'Database connection error:',
        'installed_warning' => 'System appears to be already installed (settings.php exists).',
        'installed_info' => 'You can OVERWRITE or UPDATE settings by filling this form. This will rewrite settings.php.',
        'reinstall_confirm' => 'I accept overwriting existing settings.',
        'test_connection' => 'Test Connection',
        'test_success' => '✅ Connection Successful!',
        'test_fail' => '❌ Error: '
    ]
];

$t = $txt[$langCode]; // Active language array

$message = '';
$error = '';
$installed = false;

// 1. Check Installation Status
$settingsPath = __DIR__ . '/settings.php';
if (file_exists($settingsPath)) {
    $content = file_get_contents($settingsPath);
    if (strpos($content, "'site_name'") !== false) {
        $installed = true;
    }
}

// ---------------------------------------------------------
// POST REQUEST HANDLER
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ajax Connection Test
    if (isset($_POST['action']) && $_POST['action'] === 'test_connection') {
        try {
            $dsn = "mysql:host=" . $_POST['db_host'] . ";charset=utf8mb4";
            new PDO($dsn, $_POST['db_user'], $_POST['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            die($t['test_success']);
        } catch (Exception $e) {
            die($t['test_fail'] . $e->getMessage());
        }
    }

    try {
        $siteName = filter_input(INPUT_POST, 'site_name', FILTER_SANITIZE_SPECIAL_CHARS);
        $adminUser = filter_input(INPUT_POST, 'admin_user', FILTER_SANITIZE_SPECIAL_CHARS);
        $adminPass = $_POST['admin_pass'] ?? '';

        $dbDriver = $_POST['db_driver'] ?? 'sqlite';

        if (!$siteName || !$adminUser || !$adminPass) {
            throw new Exception($t['fill_all']);
        }

        // MySQL Validations
        $dbHost = $_POST['db_host'] ?? 'localhost';
        $dbName = $_POST['db_name'] ?? 'speedpage';
        $dbUser = $_POST['db_user'] ?? 'root';
        $dbPass = $_POST['db_pass'] ?? '';

        if ($dbDriver === 'mysql' && (!$dbHost || !$dbName || !$dbUser)) {
            throw new Exception($t['mysql_required']);
        }

        // --- 1. Veritabanı Klasörü & Dosyalar
        $dbDir = __DIR__ . '/admin/veritabanı';
        if (!is_dir($dbDir)) {
            if (!mkdir($dbDir, 0755, true)) {
                throw new Exception($t['write_error'] . " $dbDir");
            }
        }
        // Güvenlik Dosyaları
        if (!file_exists($dbDir . '/index.html'))
            file_put_contents($dbDir . '/index.html', '');
        if (!file_exists($dbDir . '/.htaccess'))
            file_put_contents($dbDir . '/.htaccess', 'Deny from all');

        // --- 2. Veritabanı Bağlantısı
        $pdo = null;

        if ($dbDriver === 'sqlite') {
            $dbPath = $dbDir . '/data.db';
            $dsn = 'sqlite:' . $dbPath;
            $pdo = new PDO($dsn);
            $pdo->exec("PRAGMA journal_mode = WAL;");
        } elseif ($dbDriver === 'mysql') {
            $dsnNoDB = "mysql:host=$dbHost;charset=utf8mb4";
            try {
                $tempPdo = new PDO($dsnNoDB, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                unset($tempPdo);
            } catch (PDOException $e) {
                throw new Exception($t['db_conn_error'] . " " . $e->getMessage());
            }

            $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass);
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // --- 3. SQL Şeması
        // SQL string defined cleanly with double quotes to avoid HEREDOC issues
        $sql = "
-- SpeedPage Agnostic Backup

CREATE TABLE IF NOT EXISTS pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    description TEXT,
    icon TEXT,
    is_active INTEGER DEFAULT 1,
    sort_order INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    featured_image TEXT, 
    content LONGTEXT
);

CREATE TABLE IF NOT EXISTS menus (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_id INTEGER,
    parent_id INTEGER DEFAULT NULL,
    title TEXT NOT NULL,
    icon TEXT,
    external_url TEXT,
    sort_order INTEGER DEFAULT 0,
    is_active INTEGER DEFAULT 1,
    FOREIGN KEY (page_id) REFERENCES pages(id)
);

CREATE TABLE IF NOT EXISTS menu_locations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    menu_id INTEGER NOT NULL,
    location TEXT NOT NULL,
    FOREIGN KEY (menu_id) REFERENCES menus(id)
);

CREATE TABLE IF NOT EXISTS modules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    version TEXT DEFAULT '1.0',
    description TEXT,
    page_slug TEXT,
    is_active INTEGER DEFAULT 1,
    installed_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS page_assets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    path TEXT NOT NULL,
    load_order INTEGER DEFAULT 0,
    FOREIGN KEY (page_id) REFERENCES pages(id)
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT DEFAULT 'admin',
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    display_name TEXT, 
    avatar_url TEXT DEFAULT 'fa-code', 
    bio TEXT, 
    preferred_theme TEXT DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS module_assets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    module_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    path TEXT NOT NULL,
    load_order INTEGER DEFAULT 0,
    FOREIGN KEY (module_id) REFERENCES modules(id)
);

CREATE TABLE IF NOT EXISTS settings ( key TEXT PRIMARY KEY, value TEXT );

CREATE TABLE IF NOT EXISTS login_attempts (
        ip_address TEXT PRIMARY KEY,
        attempts INTEGER DEFAULT 0,
        last_attempt INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                action_type TEXT NOT NULL, 
                message TEXT,
                ip_address TEXT,
                user_agent TEXT,
                old_data TEXT NULL,
                new_data TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );

CREATE TABLE IF NOT EXISTS theme_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    theme_name TEXT NOT NULL,
    setting_key TEXT NOT NULL,
    setting_value TEXT,
    UNIQUE(theme_name, setting_key)
);

CREATE TABLE IF NOT EXISTS ai_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                action_type TEXT,
                prompt TEXT,
                response TEXT,
                file_path TEXT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            );

CREATE TABLE IF NOT EXISTS custom_snippets (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, code TEXT NOT NULL);

CREATE TABLE IF NOT EXISTS ai_providers (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    provider_key TEXT UNIQUE,
                    provider_name TEXT,
                    api_key TEXT,
                    models TEXT,
                    is_enabled INTEGER DEFAULT 1
                );
";

        if ($dbDriver === 'mysql') {
            $sql = str_replace('INTEGER PRIMARY KEY AUTOINCREMENT', 'INT PRIMARY KEY AUTO_INCREMENT', $sql);
            $sql = str_replace('LONGTEXT', 'LONGTEXT', $sql);
            $sql = str_replace('DATETIME DEFAULT CURRENT_TIMESTAMP', 'DATETIME DEFAULT CURRENT_TIMESTAMP', $sql);
        }

        // Execute SQL logic (split by ; for safety/fallback)
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            $queries = explode(';', $sql);
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    try {
                        $pdo->exec($query);
                    } catch (Exception $ex) {
                    }
                }
            }
        }

        // --- 4. Veri Girişi / Güncelleme
        // Settings Insert/Update Logic
        $defaultSettings = [
            'site_name' => $siteName,
            'registration_enabled' => '1',
            'active_theme' => 'default',
            'site_public' => '1',
            'session_duration' => '3600',
            'max_login_attempts' => '5',
            'login_block_duration' => '15',
            'default_lang' => $langCode,
        ];

        foreach ($defaultSettings as $key => $val) {
            // Upsert Logic manually for broader support
            $check = $pdo->prepare("SELECT 1 FROM settings WHERE " . ($dbDriver === 'mysql' ? "`key`" : "key") . " = ?");
            $check->execute([$key]);
            if ($check->fetch()) {
                $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE " . ($dbDriver === 'mysql' ? "`key`" : "key") . " = ?");
                $stmt->execute([$val, $key]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO settings (" . ($dbDriver === 'mysql' ? "`key`" : "key") . ", value) VALUES (?, ?)");
                $stmt->execute([$key, $val]);
            }
        }
        // --- Tema Varsayılan Ayarları (Array Yapısı) ---
        $defaultThemeSettings = [
            'header_bg_color' => '#0080FF',
            'enable_hero_section' => '1',
            'hero_title' => '',
            'show_sidebar' => '1',
            'sidebar_position' => 'right',
            'custom_sidebar_text' => 'SpeedPage, modern ve hızlı bir CMS çözümüdür.',
            'footer_copyright' => '© 2026 SpeedPage',
            'footer_text' => 'Designed and built with all the love in the world by the SpeedPage team.',
            'footer_col1_title' => 'Links',
            'footer_col1_links' => "Home|/\n",
            'footer_col3_title' => 'Projects',
            'footer_col3_links' => "SpeedPage|https://github.com/snrj35-dev/SpeedPage\n",
            'enable_social_share' => '1'
        ];

        foreach ($defaultThemeSettings as $key => $val) {
            // 1. Önce bu ayarın 'default' teması için var olup olmadığını kontrol et
            $checkTheme = $pdo->prepare("SELECT 1 FROM theme_settings WHERE theme_name = 'default' AND setting_key = ?");
            $checkTheme->execute([$key]);

            if ($checkTheme->fetch()) {
                // 2. Varsa güncelle (Özellikle reinstall durumlarında faydalıdır)
                $stmt = $pdo->prepare("UPDATE theme_settings SET setting_value = ? WHERE theme_name = 'default' AND setting_key = ?");
                $stmt->execute([$val, $key]);
            } else {
                // 3. Yoksa yeni kayıt olarak ekle
                $stmt = $pdo->prepare("INSERT INTO theme_settings (theme_name, setting_key, setting_value) VALUES ('default', ?, ?)");
                $stmt->execute([$key, $val]);
            }
        }
        // Admin User (Upsert)
        $checkUser = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $checkUser->execute([$adminUser]);
        $existingUser = $checkUser->fetchColumn();

        if ($existingUser) {
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([password_hash($adminPass, PASSWORD_DEFAULT), $existingUser]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, display_name) VALUES (?, ?, 'admin', ?)");
            $stmt->execute([$adminUser, password_hash($adminPass, PASSWORD_DEFAULT), $adminUser]);
        }

        // --- 5. Settings.php Güncelleme
        if (!file_exists($settingsPath))
            touch($settingsPath);
        $currentSettings = file_get_contents($settingsPath);

        // Basic Structure Check
        if (strlen($currentSettings) < 50) {
            $currentSettings = "<?php\ndeclare(strict_types=1);\n\n// SpeedPage Settings \n\ndefine('BASE_PATH', '/');\ndefine('BASE_URL', 'http://localhost/');\ndefine('CDN_URL', BASE_URL . 'cdn/');\ndefine('ROOT_DIR', __DIR__ . '/');\n";
        }

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
        $basePath = rtrim(str_replace('\\', '/', $scriptPath), '/') . '/';

        // Update Constants
        $currentSettings = preg_replace("/define\('BASE_PATH',\s*'.*?'\);/", "define('BASE_PATH', '$basePath');", $currentSettings);
        $currentSettings = preg_replace("/define\('BASE_URL',\s*'.*?'(\s*\.\s*BASE_PATH)?\);/", "define('BASE_URL', '$protocol://$host' . BASE_PATH);", $currentSettings);

        // Prep DB Config
        $dbConfigBlock = "\n" .
            "// ---------------------------------------------\n" .
            "// 3. Veritabanı Ayarları (Installer Update)\n" .
            "// ---------------------------------------------\n" .
            "if (!defined('DB_TYPE')) define('DB_TYPE', '$dbDriver');";

        if ($dbDriver === 'mysql') {
            $dbConfigBlock .= "\n" .
                "if (!defined('DB_HOST')) define('DB_HOST', '$dbHost');\n" .
                "if (!defined('DB_NAME')) define('DB_NAME', '$dbName');\n" .
                "if (!defined('DB_USER')) define('DB_USER', '$dbUser');\n" .
                "if (!defined('DB_PASS')) define('DB_PASS', '$dbPass');\n" .
                "if (!defined('DB_PORT')) define('DB_PORT', 3306);";
        } else {
            $dbConfigBlock .= "\n" .
                "if (!defined('DB_PATH')) define('DB_PATH', __DIR__ . '/admin/veritabanı/data.db');";
        }

        if (strpos($currentSettings, '// 3. Veritabanı Ayarları') !== false) {
            $currentSettings = preg_replace('/(\/\/ 3\. Veritabanı Ayarları)(.*)/s', '', $currentSettings);
        }

        $currentSettings = rtrim($currentSettings);
        if (substr($currentSettings, -2) === '?>') {
            $currentSettings = substr($currentSettings, 0, -2);
        }

        $finalSettings = $currentSettings . "\n\n" . $dbConfigBlock . "\n";
        file_put_contents($settingsPath, $finalSettings);

        $message = $t['success_title'];

    } catch (Exception $e) {
        $error = $t['error'] . ": " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $langCode ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $t['title'] ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .install-box {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
        }

        h1 {
            margin-top: 0;
            color: #1a73e8;
            text-align: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .lang-switch {
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .lang-switch a {
            text-decoration: none;
            color: #666;
            margin: 0 5px;
        }

        .lang-switch a.active {
            color: #1a73e8;
            font-weight: bold;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
        }

        input[type="text"],
        input[type="password"],
        select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 1rem;
        }

        button {
            width: 100%;
            padding: 0.75rem;
            background: #1a73e8;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background 0.2s;
        }

        button:hover {
            background: #1557b0;
        }

        button.update-btn {
            background: #f59e0b;
        }

        button.update-btn:hover {
            background: #d97706;
        }

        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .alert-error {
            background: #fde8e8;
            color: #c81e1e;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #def7ec;
            color: #03543f;
            border: 1px solid #bcf0da;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .db-toggle {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .db-option {
            flex: 1;
            text-align: center;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
        }

        .db-option.active {
            background: #e8f0fe;
            border-color: #1a73e8;
            color: #1a73e8;
            font-weight: 600;
        }

        .hidden {
            display: none;
        }

        .section-title {
            font-size: 0.85rem;
            text-transform: uppercase;
            color: #666;
            margin: 1.5rem 0 0.5rem 0;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
    </style>
</head>

<body>

    <div class="install-box">
        <h1><?= $t['title'] ?></h1>

        <div class="lang-switch">
            <a href="?lang=tr" class="<?= $langCode === 'tr' ? 'active' : '' ?>">Türkçe</a> |
            <a href="?lang=en" class="<?= $langCode === 'en' ? 'active' : '' ?>">English</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($message) ?><br><br>
                <strong><?= $t['security_warning'] ?></strong><br><br>
                <a href="index.php" style="color: inherit; font-weight: bold;"><?= $t['go_site'] ?> &rarr;</a>
            </div>
        <?php else: ?>

            <?php if ($installed): ?>
                <div class="alert alert-warning">
                    <strong><?= $t['installed_warning'] ?></strong><br>
                    <?= $t['installed_info'] ?>
                </div>
            <?php endif; ?>

            <form method="post" id="installForm">
                <div class="form-group">
                    <label><?= $t['db_type'] ?></label>
                    <div class="db-toggle">
                        <div class="db-option active" onclick="selectDB('sqlite')" id="opt-sqlite">SQLite</div>
                        <div class="db-option" onclick="selectDB('mysql')" id="opt-mysql">MySQL</div>
                    </div>
                    <input type="hidden" name="db_driver" id="db_driver" value="sqlite">
                </div>

                <div id="mysql-fields" class="hidden">
                    <div class="form-group">
                        <label><?= $t['host'] ?></label>
                        <input type="text" name="db_host" value="localhost">
                    </div>
                    <div class="form-group">
                        <label><?= $t['dbname'] ?></label>
                        <input type="text" name="db_name" value="speedpage">
                    </div>
                    <div class="form-group">
                        <label><?= $t['dbuser'] ?></label>
                        <input type="text" name="db_user" placeholder="root">
                    </div>
                    <div class="form-group">
                        <label><?= $t['dbpass'] ?></label>
                        <input type="text" name="db_pass" placeholder="">
                    </div>
                    <div class="form-group">
                        <button type="button" id="test-btn" onclick="testMySQL()"
                            style="background: #6c757d; margin-top: 5px;"><?= $t['test_connection'] ?></button>
                    </div>
                </div>

                <div class="section-title"><?= $t['site_settings'] ?></div>

                <div class="form-group">
                    <label for="site_name"><?= $t['site_name'] ?></label>
                    <input type="text" id="site_name" name="site_name" value="SpeedPage" required>
                </div>

                <div class="form-group">
                    <label for="admin_user"><?= $t['admin_user'] ?></label>
                    <input type="text" id="admin_user" name="admin_user" placeholder="admin" required>
                </div>

                <div class="form-group">
                    <label for="admin_pass"><?= $t['admin_pass'] ?></label>
                    <input type="password" id="admin_pass" name="admin_pass" required>
                </div>

                <?php if ($installed): ?>
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="checkbox" required name="confirm_reinstall" value="1">
                            <span style="font-weight:normal; font-size:0.9rem;"><?= $t['reinstall_confirm'] ?></span>
                        </label>
                    </div>
                    <button type="submit" class="update-btn"><?= $t['update_btn'] ?></button>
                <?php else: ?>
                    <button type="submit"><?= $t['install_btn'] ?></button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

    <script>
        function selectDB(type) {
            document.getElementById('db_driver').value = type;

            document.getElementById('opt-sqlite').classList.toggle('active', type === 'sqlite');
            document.getElementById('opt-mysql').classList.toggle('active', type === 'mysql');

            const mysqlFields = document.getElementById('mysql-fields');
            if (type === 'mysql') {
                mysqlFields.classList.remove('hidden');
                mysqlFields.querySelectorAll('input').forEach(i => i.required = true);
            } else {
                mysqlFields.classList.add('hidden');
                mysqlFields.querySelectorAll('input').forEach(i => i.required = false);
            }
        }

        async function testMySQL() {
            const formData = new FormData(document.getElementById('installForm'));
            formData.append('action', 'test_connection');

            const btn = document.getElementById('test-btn');
            const originalText = btn.innerText;
            btn.innerText = "...";

            try {
                const response = await fetch('install.php?lang=<?= $langCode ?>', { method: 'POST', body: formData });
                const result = await response.text();
                alert(result);
            } catch (e) {
                alert("Connection check failed.");
            } finally {
                btn.innerText = originalText;
            }
        }
    </script>

</body>

</html>