<?php
require_once '../settings.php';
require_once '../admin/db.php';
session_start();
$settings = [];
$q = $db->query("SELECT * FROM settings");
foreach ($q as $row) {
    $settings[$row['key']] = $row['value'];
}

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // 1. Brute Force Protection
    $ip = $_SERVER['REMOTE_ADDR'];
    $max_attempts = (int) ($settings['max_login_attempts'] ?? 5);
    $block_minutes = (int) ($settings['login_block_duration'] ?? 15);
    $block_time = $block_minutes * 60; // Dakikayı saniyeye çevir

    $stmt = $db->prepare("SELECT attempts, last_attempt FROM login_attempts WHERE ip_address = ?");
    $stmt->execute([$ip]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($attempt && $attempt['attempts'] >= $max_attempts) {
        if (time() - $attempt['last_attempt'] < $block_time) {
            $remaining = ceil(($block_time - (time() - $attempt['last_attempt'])) / 60);
            $error_message = '<div class="text-danger">Çok fazla başarısız giriş denemesi. Lütfen ' . $remaining . ' dakika sonra tekrar deneyin.</div>';
        } else {
            // Süre dolmuş, sayacı sıfırla (veritabanından silmeye gerek yok, güncelleme yapacağız)
            $db->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);
        }
    }

    // 2. Captcha Kontrolü
    if (empty($error_message) && !empty($settings['login_captcha']) && $settings['login_captcha'] == '1') {
        require_once 'captcha_lib.php';
        if (!Captcha::verify($_POST['captcha_selection'] ?? '')) {
            $error_message = '<div class="text-danger">Güvenlik doğrulaması başarısız.<br>Lütfen doğru ikonları seçiniz.</div>';
        }
    }

    if (empty($error_message)) {


        $username = trim($_POST['kullanici_adi'] ?? '');
        $password = $_POST['parola'] ?? '';

        if ($username === '' || $password === '') {
            $error_message = '<div class="text-danger" lang="errlogin"></div>';
        } else {
            try {
                // Yeni tablo: users
                $stmt = $db->prepare("SELECT id, username, password_hash, is_active, role FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {

                    // Başarılı giriş -> Hatalı denemeleri temizle
                    $db->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'] ?? 'user';

                    // ROL KONTROLÜ VE YÖNLENDİRME
                    if ($_SESSION['role'] === 'admin') {
                        // Admin ise admin paneline git
                        header("Location: ../admin/index.php");
                    } else {
                        // Normal kullanıcı ise sitenin ana sayfasına git
                        header("Location: ../index.php");
                    }
                    exit;

                } else {
                    // Başarısız giriş -> Deneme sayısını artır
                    $time = time();
                    $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, attempts, last_attempt) VALUES (?, 1, ?) 
                                      ON CONFLICT(ip_address) DO UPDATE SET attempts = attempts + 1, last_attempt = ?");
                    $stmt->execute([$ip, $time, $time]);

                    $error_message = '<div class="text-danger" lang="errlogin"></div>';
                }

            } catch (Exception $e) {
                // Log the detailed error for debugging
                error_log("[login.php] DB error: " . $e->getMessage());

                // If running on localhost, show the detailed message to help debugging
                if (defined('BASE_URL') && strpos(BASE_URL, 'localhost') !== false) {
                    $error_message = '<div class="text-danger">Veritabanı Hatası: ' . htmlspecialchars($e->getMessage()) . '</div>';
                } else {
                    $error_message = '<div class="text-danger" lang="errdata"></div>';
                }
            }
        }
    }
}
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SpeedPage Login</title>
    <link rel="stylesheet" href="<?= CDN_URL ?>css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CDN_URL ?>css/all.min.css">
    <link rel="icon" href="../favicon.ico">
    <link rel="stylesheet" href="<?= CDN_URL ?>css/style.css">
    <meta name="theme-color" content="#121212">
</head>

<body>

    <div class="container min-vh-100 d-flex align-items-center justify-content-center">
        <div class="col-12 col-sm-10 col-md-6 col-lg-4">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-body p-4 p-md-5">
                    <?php if (!empty($_GET['maintenance'])): ?>
                        <div class="alert alert-warning text-center py-2 small">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>
                            <span lang="site_maintenance">Site şu anda bakım modunda. Sadece giriş yapmış kullanıcılar
                                erişebilir.</span>
                        </div>
                    <?php endif; ?>

                    <h3 class="text-center mb-4 fw-bold">
                        <i class="fa-solid fa-user-lock me-2"></i>
                        <span lang="login"></span>
                    </h3>

                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger text-center py-2">
                            <?= $error_message ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php">

                        <div class="mb-3">
                            <label for="kullanici_adi" class="form-label" lang="user_name"></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                <input type="text" id="kullanici_adi" name="kullanici_adi" class="form-control"
                                    required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="parola" class="form-label" lang="password"></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" id="parola" name="parola" class="form-control" required>
                            </div>
                        </div>

                        <?php if (!empty($settings['login_captcha']) && $settings['login_captcha'] == '1'): ?>
                            <?php
                            require_once 'captcha_lib.php';
                            $cpt = Captcha::generate();
                            ?>
                            <div class="mb-4 unselectable">
                                <style>
                                    <?= $cpt['css'] ?>
                                    .cpt-grid {
                                        display: grid;
                                        grid-template-columns: repeat(3, 1fr);
                                        gap: 10px;
                                        max-width: 240px;
                                        margin: 15px auto;
                                    }

                                    .cpt-item {
                                        width: 70px;
                                        height: 70px;
                                        background: rgba(var(--bs-body-color-rgb), 0.05);
                                        border: 2px solid transparent;
                                        border-radius: 12px;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        font-size: 28px;
                                        cursor: pointer;
                                        transition: all 0.2s;
                                    }

                                    .cpt-item:hover {
                                        background: rgba(var(--bs-primary-rgb), 0.1);
                                        transform: scale(1.05);
                                    }

                                    .cpt-item.selected {
                                        border-color: var(--bs-primary);
                                        background-color: rgba(var(--bs-primary-rgb), 0.15);
                                        color: var(--bs-primary);
                                        box-shadow: 0 0 10px rgba(var(--bs-primary-rgb), 0.2);
                                    }

                                    .unselectable {
                                        -webkit-user-select: none;
                                        user-select: none;
                                    }
                                </style>

                                <div class="text-center mb-2">
                                    <span class="badge bg-secondary mb-2" lang="captcha_active">Güvenlik</span>
                                    <div class="small" id="cpt-instruction"></div>
                                </div>

                                <div class="cpt-grid">
                                    <?php foreach ($cpt['grid'] as $item): ?>
                                        <div class="cpt-item" onclick="toggleCaptcha(this, '<?= $item['id'] ?>')">
                                            <i class="<?= $item['class'] ?>"></i>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="captcha_selection" id="captcha_selection" value="">

                                <script>
                                    let selectedIcons = [];
                                    function toggleCaptcha(el, id) {
                                        el.classList.toggle('selected');
                                        if (selectedIcons.includes(id)) {
                                            selectedIcons = selectedIcons.filter(item => item !== id);
                                        } else {
                                            selectedIcons.push(id);
                                        }
                                        document.getElementById('captcha_selection').value = selectedIcons.join(',');
                                    }

                                    // Localized Captcha Instructions with proper concatenation
                                    function updateCaptchaInstruction() {
                                        if (!window.lang) return;

                                        const targetKey = "<?= $cpt['target_key'] ?>";
                                        const targetName = window.lang[targetKey] || targetKey;
                                        const template = window.lang['captcha_instruction'] || "Please select icons for %s.";

                                        // %s placeholder replacement
                                        const text = template.replace('%s', `<strong class='text-primary'>${targetName}</strong>`);

                                        const el = document.getElementById('cpt-instruction');
                                        if (el) el.innerHTML = text;
                                    }

                                    // Watch for language changes
                                    const langSelector = document.getElementById('lang-select');
                                    if (langSelector) {
                                        langSelector.addEventListener('change', () => setTimeout(updateCaptchaInstruction, 200));
                                    }

                                    // Poll until language is loaded
                                    const langCheckInterval = setInterval(() => {
                                        if (window.lang && document.getElementById('cpt-instruction')) {
                                            updateCaptchaInstruction();
                                            clearInterval(langCheckInterval);
                                        }
                                    }, 100);
                                </script>
                            </div>
                        <?php endif; ?>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg rounded-3" lang="login_button">
                                <i class="fa-solid fa-right-to-bracket me-2"></i>
                            </button>
                        </div>

                    </form>
                    <?php if (!empty($settings['registration_enabled']) && $settings['registration_enabled'] == '1'): ?>
                        <div class="text-center mt-3">
                            <a href="<?= BASE_URL ?>php/register.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fa-solid fa-user-plus me-1"></i>
                                <span lang="create_account"></span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= CDN_URL ?>js/bootstrap.bundle.min.js"></script>
    <script src="<?= CDN_URL ?>js/dark.js"></script>
    <script src="<?= CDN_URL ?>js/lang.js"></script>
</body>

</html>