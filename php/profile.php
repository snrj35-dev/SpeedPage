<?php
require_once '../settings.php';
require_once '../admin/db.php';
require_once 'user_auth.php'; // user_auth already calls session_start() and checks auth

// 1. SİSTEM AYARLARINI ÇEK
$stmt = $db->query("SELECT `key`, `value` FROM settings");
$config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$allow_username = $config['allow_username_change'] ?? '0';
$allow_password = $config['allow_password_change'] ?? '1';

// 2. KULLANICIYI BELİRLE
$user_id = $_GET['id'] ?? $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] === 'admin');
$is_own_profile = ($_SESSION['user_id'] == $user_id);

// Yetki Kontrolü: Sadece admin veya profil sahibi görebilir (Opsiyonel: Profili herkese açık yapabilirsin)
if (!$is_own_profile && !$is_admin) {
    die('<div class="alert alert-danger small text-center"><span lang="profile_no_permission"></span></div>');
}

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user)
    die('<div class="alert alert-danger small text-center"><span lang="user_not_found"></span></div>');

// 3. GÜNCELLEME İŞLEMİ
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $display_name = trim($_POST['display_name']);
    $avatar_url = $_POST['avatar_url'] ?? 'fa-user';
    $new_pass = $_POST['new_password'] ?? '';
    $current_pass_input = $_POST['current_password'] ?? ''; // Eski şifre alanı
    $new_username = trim($_POST['username'] ?? '');
    $update_fields = ["display_name = ?", "avatar_url = ?"];
    $params = [$display_name, $avatar_url];

    // Şifre Değiştirme Mantığı
    if (!empty($new_pass)) {
        // 1. Kendi profilini değiştiriyorsa eski şifreyi doğrula (Admin başkasını değiştirirken sormaz)
        if ($is_own_profile && !password_verify($current_pass_input, $user['password_hash'])) {
            $msg = 'danger|<span lang="invalid_current_password"></span>';
        } elseif (strlen($new_pass) < 6) {
            $msg = 'danger|<span lang="password_too_short"></span>';
        }
    }
    // Kullanıcı adı değiştirme izni (Admin ise her zaman değiştirebilir)
    if (($allow_username === '1' || $is_admin) && !empty($new_username) && $new_username !== $user['username']) {
        // Çakışma kontrolü
        $check = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->execute([$new_username, $user_id]);
        if (!$check->fetch()) {
            $update_fields[] = "username = ?";
            $params[] = $new_username;
        } else {
            $msg = 'danger|<span lang="username_taken"></span>';
        }
    }

    // Şifre değiştirme izni
    if (($allow_password === '1' || $is_admin) && !empty($new_pass)) {
        if (strlen($new_pass) >= 6) {
            $update_fields[] = "password_hash = ?";
            $params[] = password_hash($new_pass, PASSWORD_DEFAULT);
        } else {
            $msg = 'danger|<span lang="password_too_short"></span>';
        }
    }

    if (empty($msg)) {
        $params[] = $user_id;
        $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
        $db->prepare($sql)->execute($params);
        header("Location: profile.php?id=$user_id&status=success");
        exit;
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
        const CSRF_TOKEN = '<?= $_SESSION['csrf'] ?>';
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Düzenle - <?= e($user['username']) ?></title>
    <link rel="stylesheet" href="<?= CDN_URL ?>css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= CDN_URL ?>css/all.min.css">
    <link rel="stylesheet" href="<?= CDN_URL ?>css/style.css">
</head>

<body class="bg-dark text-light">

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">

                <?php if (isset($_GET['status'])): ?>
                    <div class="alert alert-success small"><span lang="profile_updated_success"></span></div>
                <?php endif; ?>

                <?php if ($msg):
                    $m = explode('|', $msg); ?>
                    <div class="alert alert-<?= $m[0] ?> small"><?= $m[1] ?></div>
                <?php endif; ?>

                <div class="card bg-secondary bg-opacity-10 border-0 shadow-lg rounded-4">
                    <div class="card-body p-4">
                        <form method="POST">
                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                            <div class="text-center mb-4">
                                <div class="profile-icon-big text-primary">
                                    <i class="fas <?= e($user['avatar_url'] ?? 'fa-user') ?>"></i>
                                </div>
                                <h4 class="mb-0"><?= e($user['username']) ?></h4>
                                <small class="text-muted"><?= strtoupper($user['role']) ?></small>
                            </div>

                            <hr class="opacity-10">
                            <?php
                            $icons = [
                                // İnsan & Karakter
                                'fa-user' => 'Standart',
                                'fa-user-ninja' => 'Ninja',
                                'fa-user-astronaut' => 'Astronot',
                                'fa-user-tie' => 'İş Adamı',
                                'fa-user-secret' => 'Ajan',
                                'fa-user-graduate' => 'Mezun',

                                // Fantastik & Eğlence
                                'fa-ghost' => 'Hayalet',
                                'fa-robot' => 'Robot',
                                'fa-skull' => 'Kafatası',
                                'fa-dragon' => 'Ejderha',
                                'fa-mask' => 'Maske',
                                'fa-gamepad' => 'Oyuncu',

                                // Hayvanlar
                                'fa-cat' => 'Kedi',
                                'fa-dog' => 'Köpek',
                                'fa-crow' => 'Karga',
                                'fa-hippo' => 'Su Aygırı',
                                'fa-otter' => 'Su Samuru',

                                // Diğer Semboller
                                'fa-bolt' => 'Şimşek',
                                'fa-fire' => 'Ateş',
                                'fa-rocket' => 'Roket',
                                'fa-anchor' => 'Çapa',
                                'fa-code' => 'Yazılımcı'
                            ];
                            ?>
                            <label class="form-label small text-uppercase fw-bold opacity-50 mb-3"
                                lang="choose_profile_icon"></label>
                            <div class="row row-cols-4 row-cols-sm-6 g-2 mb-4 overflow-auto"
                                style="max-height: 250px; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 15px;">
                                <?php foreach ($icons as $class => $name): ?>
                                    <div class="col text-center">
                                        <input type="radio" class="btn-check" name="avatar_url" id="<?= $class ?>"
                                            value="<?= $class ?>" <?= ($user['avatar_url'] == $class) ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-primary border-0 w-100 p-3 rounded-3 shadow-sm"
                                            for="<?= $class ?>" title="<?= $name ?>">
                                            <i class="fas <?= $class ?> fa-xl d-block mb-1"></i>
                                            <small style="font-size: 0.6rem; display: block;margin-top: 10px;"
                                                class="text-truncate"><?= $name ?></small>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small" lang="display_name_label"></label>
                                <input type="text" name="display_name" class="form-control bg-dark text-white border-0"
                                    value="<?= e($user['display_name']) ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small" lang="username_label"></label>
                                <input type="text" name="username" class="form-control bg-dark text-white border-0"
                                    value="<?= e($user['username']) ?>" <?= ($allow_username === '1' || $is_admin) ? '' : 'readonly opacity-50' ?>>
                                <?php if ($allow_username === '0' && !$is_admin): ?>
                                    <small class="text-warning" style="font-size: 0.7rem;"><span
                                            lang="username_change_disabled"></span></small>
                                <?php endif; ?>
                            </div>
                            <?php if (($allow_password === '1' || $is_admin)): ?>
                                <div
                                    class="p-3 bg-dark bg-opacity-50 rounded-3 mb-4 border border-secondary border-opacity-25 mt-4">
                                    <h6 class="mb-3 text-primary" style="font-size: 0.8rem; letter-spacing: 1px;">
                                        <i class="fa-solid fa-shield-halved me-2"></i><span lang="security_settings"></span>
                                    </h6>

                                    <?php if ($is_own_profile): ?>
                                        <div class="mb-3">
                                            <label class="form-label small opacity-75" lang="current_password_label"></label>
                                            <input type="password" name="current_password"
                                                class="form-control bg-dark text-white border-secondary border-opacity-25"
                                                data-placeholder="current_password_placeholder">
                                        </div>
                                    <?php endif; ?>

                                    <div class="mb-2">
                                        <label class="form-label small opacity-75" lang="new_password_label"></label>
                                        <input type="password" name="new_password"
                                            class="form-control bg-dark text-white border-secondary border-opacity-25"
                                            data-placeholder="new_password_placeholder">
                                        <small class="text-muted" style="font-size:0.65rem;"><span
                                                lang="only_fill_if_want"></span></small>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning border-0 small opacity-75 mt-3">
                                    <i class="fa-solid fa-lock me-2"></i> <span lang="password_change_disabled"></span>
                                </div>
                            <?php endif; ?>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg rounded-3">
                                    <i class="fa-solid fa-save me-2"></i> <span lang="save_changes"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <a href="../index.php" class="text-white-50 text-decoration-none small"><i
                            class="fa-solid fa-arrow-left"></i> <span lang="return_home"></span></a>
                </div>

            </div>
        </div>
    </div>
    <script src="<?= CDN_URL ?>js/bootstrap.bundle.min.js"></script>
    <script src="<?= CDN_URL ?>js/dark.js"></script>
    <script src="<?= CDN_URL ?>js/lang.js"></script>
</body>

</html>