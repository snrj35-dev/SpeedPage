<?php
declare(strict_types=1);

require_once '../settings.php';
require_once '../admin/db.php';
require_once 'theme-init.php';
require_once 'menu-loader.php';
/** @var PDO $db */
global $db;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$stmt = $db->query("SELECT `key`, `value` FROM settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$menus = getMenus('navbar');

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $max_attempts = (int) ($settings['max_login_attempts'] ?? 5);
    $block_minutes = (int) ($settings['login_block_duration'] ?? 15);
    $block_time = $block_minutes * 60;

    $stmt = $db->prepare("SELECT attempts, last_attempt FROM login_attempts WHERE ip_address = ?");
    $stmt->execute([$ip]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($attempt && $attempt['attempts'] >= $max_attempts) {
        if (time() - $attempt['last_attempt'] < $block_time) {
            $remaining = (int) ceil(($block_time - (time() - $attempt['last_attempt'])) / 60);
            $msg = function_exists('__') ? __('err_too_many_attempts', $remaining) : "Too many attempts. Wait $remaining min.";
            $error_message = '<div class="text-danger">' . e($msg) . '</div>';
        } else {
            $db->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);
        }
    }

    if (empty($error_message) && !empty($settings['login_captcha']) && $settings['login_captcha'] == '1') {
        require_once 'captcha_lib.php';
        if (!Captcha::verify($_POST['captcha_selection'] ?? '')) {
            $msg = function_exists('__') ? __('err_captcha_fail') : 'Captcha verification failed.';
            $error_message = '<div class="text-danger">' . e($msg) . '</div>';
        }
    }

    if (empty($error_message)) {
        $username = trim(filter_input(INPUT_POST, 'kullanici_adi', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
        $password = $_POST['parola'] ?? '';

        if ($username === '' || $password === '') {
            $error_message = '<div class="text-danger" lang="errlogin"></div>';
        } else {
            try {
                $stmt = $db->prepare("SELECT id, username, password_hash, is_active, role FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && ($user['is_active'] ?? 0) && password_verify($password, $user['password_hash'])) {
                    $db->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'] ?? 'user';

                    if (function_exists('sp_log')) {
                        sp_log("Başarılı giriş", "login_success");
                    }

                    header("Location: " . ($_SESSION['role'] === 'admin' ? BASE_URL . 'admin/index.php' : BASE_URL));
                    exit;
                } else {
                    $time = time();
                    // Database compatible update/insert flow
                    $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ?");
                    $stmt->execute([$ip]);
                    $exists = $stmt->fetchColumn() > 0;

                    if ($exists) {
                        $db->prepare("UPDATE login_attempts SET attempts = attempts + 1, last_attempt = ? WHERE ip_address = ?")
                            ->execute([$time, $ip]);
                    } else {
                        $db->prepare("INSERT INTO login_attempts (ip_address, attempts, last_attempt) VALUES (?, 1, ?)")
                            ->execute([$ip, $time]);
                    }

                    $error_message = '<div class="text-danger" lang="errlogin"></div>';
                    if (function_exists('sp_log')) {
                        sp_log("Hatalı giriş denemesi: $username", "login_fail");
                    }
                }
            } catch (Exception $e) {
                // $error_message = '<div class="text-danger" lang="errdata"></div>';
                // Using fallback for stricter type compliance if e() expects string
                $error_message = '<div class="text-danger" lang="errdata"></div>';
            }
        }
    }
}
$pageTitle = (function_exists('__') ? __('login_title') : 'Giriş Yap') . ' - ' . ($settings['site_name'] ?? 'SpeedPage');
load_theme_part('header');
?>

    <?php load_theme_part('navbar'); ?>

    <main class="container py-5 min-vh-100 d-flex align-items-center justify-content-center">
        <div class="col-12 col-sm-10 col-md-6 col-lg-4">
            <div class="card rounded-4">
                <div class="card-body p-4 p-md-5">
                    <h3 class="text-center mb-4 fw-bold">
                        <i class="fa-solid fa-user-lock me-2"></i>
                        <span lang="login"></span>
                    </h3>

                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger text-center py-2"><?= $error_message ?></div>
                    <?php endif; ?>

                    <form method="POST">
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
                            <div class="mb-4 unselectable">
                                <?php
                                require_once 'captcha_lib.php';
                                $cpt = Captcha::generate();
                                echo "<style>" . $cpt['css'] . "</style>";
                                ?>
                                <div class="text-center mb-2">
                                    <span class="badge bg-secondary mb-2" lang="captcha_active"></span>
                                    <div class="small" id="cpt-instruction"></div>
                                </div>
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
                                    function updateCaptchaInstruction() {
                                        if (!window.lang) return;
                                        const targetName = window.lang['<?= $cpt['target_key'] ?>'] || '<?= $cpt['target_key'] ?>';
                                        const template = window.lang['captcha_instruction'] || "Please select: %s";
                                        document.getElementById('cpt-instruction').innerHTML = template.replace('%s', `<strong class='text-primary'>${targetName}</strong>`);
                                    }
                                    setInterval(() => { if (window.lang && document.getElementById('cpt-instruction').innerHTML == "") updateCaptchaInstruction(); }, 500);
                                </script>
                            </div>
                        <?php endif; ?>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg rounded-3" lang="login_button"></button>
                        </div>
                    </form>

                    <?php if (($settings['registration_enabled'] ?? '0') == '1'): ?>
                        <div class="text-center mt-3">
                            <a href="<?= BASE_URL ?>php/register.php" class="btn btn-link btn-sm text-decoration-none"
                                lang="create_account"></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php load_theme_part('footer'); ?>