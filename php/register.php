<?php
require_once '../settings.php';
require_once '../admin/db.php';
require_once 'theme-init.php';
require_once 'menu-loader.php';

session_start();

$q = $db->query("SELECT `key`, `value` FROM settings");
$config = $q->fetchAll(PDO::FETCH_KEY_PAIR);
$settings = $config;

$menus = getMenus('navbar');

if (($config['registration_enabled'] ?? '1') === '0') {
    die('<div class="alert alert-warning text-center small"><span lang="registration_closed"></span> <a href="../index.php"><span lang="back_to_home"></span></a></div>');
}

$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $ip = $_SERVER['REMOTE_ADDR'];
    $max_attempts = (int) ($config['max_login_attempts'] ?? 5);
    $block_minutes = (int) ($config['login_block_duration'] ?? 15);
    $block_time = $block_minutes * 60;

    $stmt = $db->prepare("SELECT attempts, last_attempt FROM login_attempts WHERE ip_address = ?");
    $stmt->execute([$ip]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($attempt && $attempt['attempts'] >= $max_attempts) {
        if (time() - $attempt['last_attempt'] < $block_time) {
            $remaining = ceil(($block_time - (time() - $attempt['last_attempt'])) / 60);
            $error_message = 'Çok fazla deneme. Lütfen ' . $remaining . ' dakika sonra tekrar deneyin.';
        } else {
            $db->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);
        }
    }

    if (empty($error_message) && !empty($config['login_captcha']) && $config['login_captcha'] == '1') {
        require_once 'captcha_lib.php';
        if (!Captcha::verify($_POST['captcha_selection'] ?? '')) {
            $error_message = 'Güvenlik doğrulaması başarısız.';
        }
    }

    if (empty($error_message)) {
        $username = trim($_POST['kullanici_adi'] ?? '');
        $password = $_POST['parola'] ?? '';
        $password_confirm = $_POST['parola_tekrar'] ?? '';

        if (empty($username) || empty($password)) {
            $error_message = '<span lang="fill_all_fields"></span>';
        } elseif ($password !== $password_confirm) {
            $error_message = '<span lang="password_mismatch"></span>';
        } elseif (strlen($password) < 6) {
            $error_message = '<span lang="password_too_short"></span>';
        } else {
            try {
                $check = $db->prepare("SELECT id FROM users WHERE username = ?");
                $check->execute([$username]);
                if ($check->fetch()) {
                    $error_message = '<span lang="username_taken"></span>';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $is_active = ($config['email_verification'] === '1') ? 0 : 1;
                    $ins = $db->prepare("INSERT INTO users (username, display_name, password_hash, role, is_active, avatar_url) VALUES (?, ?, ?, 'user', ?, 'fa-user')");
                    $ins->execute([$username, $username, $hash, $is_active]);
                    $success_message = '<span lang="registration_success"></span>';
                    header("Refresh: 2; url=login.php");
                }
            } catch (Exception $e) {
                $error_message = "Hata: " . $e->getMessage();
            }
        }
        if (!empty($error_message) && empty($success_message)) {
            $time = time();
            $db->prepare("INSERT INTO login_attempts (ip_address, attempts, last_attempt) VALUES (?, 1, ?) 
                            ON CONFLICT(ip_address) DO UPDATE SET attempts = attempts + 1, last_attempt = ?")->execute([$ip, $time, $time]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - <?= e($settings['site_name'] ?? 'SpeedPage') ?></title>
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
        const BASE_PATH = '<?= BASE_PATH ?>';
        const BASE_URL = "<?= BASE_URL ?>"; 
    </script>
    <link rel="stylesheet" href="<?= CDN_URL ?>css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CDN_URL ?>css/all.min.css">
    <?php
    $themeCss = "themes/" . ACTIVE_THEME . "/style.css";
    if (file_exists(ROOT_DIR . $themeCss)) {
        echo '<link rel="stylesheet" href="' . BASE_URL . $themeCss . '?v=' . filemtime(ROOT_DIR . $themeCss) . '">';
    }
    ?>
</head>

<body>

    <?php load_theme_part('navbar'); ?>

    <main class="container py-5 min-vh-100 d-flex align-items-center justify-content-center">
        <div class="col-12 col-sm-10 col-md-6 col-lg-4">
            <div class="card rounded-4">
                <div class="card-body p-4 p-md-5">
                    <h3 class="text-center mb-4 fw-bold">
                        <i class="fa-solid fa-user-plus me-2"></i>
                        <span lang="register"></span>
                    </h3>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger py-2 text-center small"><?= $error_message ?></div>
                    <?php endif; ?>
                    <?php if ($success_message): ?>
                        <div class="alert alert-success py-2 text-center small"><?= $success_message ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label" lang="user_name"></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                <input type="text" name="kullanici_adi" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" lang="password"></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" name="parola" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" lang="password_repeat"></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-shield-halved"></i></span>
                                <input type="password" name="parola_tekrar" class="form-control" required>
                            </div>
                        </div>

                        <?php if (!empty($config['login_captcha']) && $config['login_captcha'] == '1'): ?>
                            <div class="mb-4 unselectable">
                                <?php
                                require_once 'captcha_lib.php';
                                $cpt = Captcha::generate();
                                echo "<style>" . $cpt['css'] . "</style>";
                                ?>
                                <div class="cpt-grid d-flex flex-wrap justify-content-center gap-2 mb-3">
                                    <?php foreach ($cpt['grid'] as $item): ?>
                                        <div class="cpt-item p-3 border rounded cursor-pointer"
                                            style="width:60px; height:60px; display:flex; align-items:center; justify-content:center; font-size:1.5rem;"
                                            onclick="toggleCaptcha(this, '<?= $item['id'] ?>')">
                                            <i class="<?= $item['class'] ?>"></i>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="captcha_selection" id="captcha_selection">
                                <script>
                                    let selectedIcons = [];
                                    function toggleCaptcha(el, id) {
                                        el.classList.toggle('selected');
                                        el.classList.toggle('border-primary'); el.classList.toggle('bg-primary-subtle');
                                        if (selectedIcons.includes(id)) selectedIcons = selectedIcons.filter(i => i !== id);
                                        else selectedIcons.push(id);
                                        document.getElementById('captcha_selection').value = selectedIcons.join(',');
                                    }
                                </script>
                            </div>
                        <?php endif; ?>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg rounded-3"
                                lang="register_button"></button>
                            <a href="login.php" class="btn btn-link btn-sm text-decoration-none text-center"
                                lang="already_have_account"></a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <?php load_theme_part('footer'); ?>

    <script src="<?= CDN_URL ?>js/bootstrap.bundle.min.js"></script>
    <script src="<?= CDN_URL ?>js/dark.js"></script>
    <script src="<?= CDN_URL ?>js/lang.js"></script>
</body>

</html>