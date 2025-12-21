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

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                // store role in session for authorization
                $_SESSION['role'] = $user['role'] ?? 'user';

                header("Location: ../admin/index.php");
                exit;

            } else {
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
                    <span lang="site_maintenance">Site şu anda bakım modunda. Sadece giriş yapmış kullanıcılar erişebilir.</span>
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
                            <input type="text" id="kullanici_adi" name="kullanici_adi"
                                   class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="parola" class="form-label" lang="password"></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" id="parola" name="parola"
                                   class="form-control" required>
                        </div>
                    </div>

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

