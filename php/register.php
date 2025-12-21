<?php
require_once '../settings.php';
require_once '../admin/db.php';
session_start();

// 1. AYARLARI KONTROL ET
try {
    $stmt = $db->query("SELECT `key`, `value` FROM settings");
    $config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $config = ['registration_enabled' => '1']; // Hata durumunda varsayılan açık
}

$registration_enabled = $config['registration_enabled'] ?? '1';

// Eğer kayıtlar kapalıysa kullanıcıyı yönlendir veya durdur
if ($registration_enabled === '0') {
    die("Kayıt işlemleri şu anda kapalıdır. <a href='../index.php'>Geri Dön</a>");
}

$error_message = '';
$success_message = '';

// 2. KAYIT İŞLEMİ
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['kullanici_adi'] ?? '');
    $password = $_POST['parola'] ?? '';
    $password_confirm = $_POST['parola_tekrar'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = "Lütfen tüm alanları doldurun.";
    } elseif ($password !== $password_confirm) {
        $error_message = "Şifreler birbiriyle eşleşmiyor.";
    } elseif (strlen($password) < 6) {
        $error_message = "Şifre en az 6 karakter olmalıdır.";
    } else {
        try {
            // Kullanıcı adı kontrolü
            $check = $db->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetch()) {
                $error_message = "Bu kullanıcı adı zaten alınmış.";
            } else {
                // Şifreyi hashle ve kaydet
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $role = 'user'; // Varsayılan rol
                $is_active = ($config['email_verification'] === '1') ? 0 : 1; // Aktivasyon gerekliyse pasif aç

                $ins = $db->prepare("INSERT INTO users (username, password_hash, role, is_active) VALUES (?, ?, ?, ?)");
                $ins->execute([$username, $hash, $role, $is_active]);

                $success_message = "Kayıt başarılı! Şimdi giriş yapabilirsiniz.";
                // İstersen burada direkt login.php'ye yönlendirebilirsin
            }
        } catch (Exception $e) {
            $error_message = "Bir hata oluştu: " . $e->getMessage();
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
    <title lang="register_title">Kayıt Ol - SpeedPage</title>

    <link rel="stylesheet" href="<?= CDN_URL ?>css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CDN_URL ?>css/all.min.css">
    <link rel="stylesheet" href="<?= CDN_URL ?>css/style.css">
</head>

<body>

<div class="container min-vh-100 d-flex align-items-center justify-content-center">
    <div class="col-12 col-sm-10 col-md-6 col-lg-4">

        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-body p-4 p-md-5">

                <h3 class="text-center mb-4 fw-bold">
                    <i class="fa-solid fa-user-plus me-2"></i>
                    <span lang="register"></span>
                </h3>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger py-2 text-center small">
                        <?= $error_message ?>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success py-2 text-center small">
                        <?= $success_message ?><br>
                        <a href="login.php" class="alert-link" lang="login"></a>
                    </div>
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

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg rounded-3" lang="register_button"></button>

                        <a href="login.php"
                           class="btn btn-link text-white-50 btn-sm text-decoration-none text-center"
                           lang="already_have_account"></a>
                    </div>

                </form>

            </div>
        </div>

    </div>
</div>

<script src="<?= CDN_URL ?>js/bootstrap.bundle.min.js"></script>
<script src="<?= CDN_URL ?>js/dark.js"></script>
<script src="<?= CDN_URL ?>js/lang.js"></script>
</body>
</html>
