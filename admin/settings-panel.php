<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';
// Mevcut ayarları çek
$stmt = $db->query("SELECT `key`, `value` FROM settings");
$config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>



<div class="card p-3 mb-4">
    <?php if (isset($_GET['status']) && $_GET['status'] == 'ok'): ?>
        <div class="alert alert-success" lang="settings_saved"></div>
    <?php endif; ?>
    <form action="settings-edit.php" method="POST">
        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
        <ul class="nav nav-tabs mb-4" id="settingsTab" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="site-tab" data-bs-toggle="tab" data-bs-target="#site-settings"
                    type="button">
                    <i class="fas fa-globe me-1"></i> <span lang="site_general"></span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="user-tab" data-bs-toggle="tab" data-bs-target="#user-settings"
                    type="button">
                    <i class="fas fa-users me-1"></i> <span lang="membership_settings"></span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security-settings"
                    type="button">
                    <i class="fas fa-shield-alt me-1"></i> <span lang="site_security_tab"></span>
                </button>
            </li>
        </ul>
        <div class="tab-content" id="settingsTabContent">
            <div class="tab-pane fade show active p-3 mb-4" id="site-settings">
                <!-- ✅ Site Durumu -->
                <div class="mb-4 border-bottom pb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold mb-0" lang="site_status"></h6>
                            <small lang="site_status_desc"></small>
                        </div>
                        <div class="form-check form-switch fs-4">
                            <input class="form-check-input" type="checkbox" name="site_public" value="1"
                                <?= ($config['site_public'] ?? '1') == '1' ? 'checked' : '' ?>>
                        </div>
                    </div>
                </div>
                <!-- ✅ Site İsmi -->
                <div class="mb-4 border-bottom pb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold mb-0" lang="site_name"></h6>
                            <small lang="site_name_desc"></small>
                        </div>
                        <div style="width: 250px;">
                            <input type="text" class="form-control" name="site_name"
                                value="<?= e($config['site_name'] ?? '') ?>" placeholder="Örn: SpeedPage">
                        </div>
                    </div>
                </div>
                <!-- ✅ Site Sloganı -->
                <div class="mb-4 border-bottom pb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold mb-0" lang="site_slogan"></h6>
                            <small lang="site_slogan_desc"></small>
                        </div>
                        <div style="width: 250px;">
                            <input type="text" class="form-control" name="site_slogan"
                                value="<?= e($config['site_slogan'] ?? '') ?>" placeholder="Örn: Hızlı. Hafif. Modern.">
                        </div>
                    </div>
                </div>
                <!-- ✅ Meta Description -->
                <div class="mb-4 border-bottom pb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold mb-0" lang="meta_description"></h6>
                            <small lang="meta_description_desc"></small>
                        </div>
                        <div style="width: 250px;">
                            <input type="text" class="form-control" name="meta_description"
                                value="<?= e($config['meta_description'] ?? '') ?>"
                                placeholder="Örn: SpeedPage ile hızlı web deneyimi.">
                        </div>
                    </div>
                </div>
                <!-- ✅ Logo URL -->
                <div class="mb-4 border-bottom pb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold mb-0" lang="logo_url"></h6>
                            <small lang="logo_url_desc"></small>
                        </div>
                        <div style="width: 250px;">
                            <input type="text" class="form-control" name="logo_url"
                                value="<?= e($config['logo_url'] ?? '') ?>"
                                placeholder="Örn: https://site.com/logo.png">
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade p-3 mb-4" id="user-settings">
                <!-- ✅ Yeni Kayıt Sistemi -->
                <div class="mb-4 border-bottom pb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold mb-0" lang="registration_system"></h6>
                            <small lang="registration_system_desc"></small>
                        </div>
                        <div class="form-check form-switch fs-4">
                            <input class="form-check-input" type="checkbox" name="registration_enabled" value="1"
                                <?= ($config['registration_enabled'] ?? '0') == '1' ? 'checked' : '' ?>>
                        </div>
                    </div>
                </div>

                <!-- ✅ E-posta Aktivasyonu -->
                <div class="mb-4 border-bottom pb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold mb-0" lang="email_activation"></h6>
                            <small lang="email_activation_desc"></small>
                        </div>
                        <div class="form-check form-switch fs-4">
                            <input class="form-check-input" type="checkbox" name="email_verification" value="1"
                                <?= ($config['email_verification'] ?? '0') == '1' ? 'checked' : '' ?>>
                        </div>
                    </div>
                </div>

                <!-- ✅ Kullanıcı Adı Değişikliği -->
                <div class="mb-4 border-bottom pb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold mb-0" lang="username_change"></h6>
                            <small lang="username_change_desc"></small>
                        </div>
                        <div class="form-check form-switch fs-4">
                            <input class="form-check-input" type="checkbox" name="allow_username_change" value="1"
                                <?= ($config['allow_username_change'] ?? '0') == '1' ? 'checked' : '' ?>>
                        </div>
                    </div>
                </div>

                <!-- ✅ Şifre Değişikliği -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold mb-0" lang="password_change"></h6>
                            <small lang="password_change_desc"></small>
                        </div>
                        <div class="form-check form-switch fs-4">
                            <input class="form-check-input" type="checkbox" name="allow_password_change" value="1"
                                <?= ($config['allow_password_change'] ?? '1') == '1' ? 'checked' : '' ?>>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade p-3 mb-4" id="security-settings">
                <!-- ✅ Session Duration -->
                <div class="mb-4 border-bottom pb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold mb-0" lang="session_duration"></h6>
                            <small lang="session_duration_desc"></small>
                        </div>
                        <div style="width: 250px;">
                            <input type="number" class="form-control" name="session_duration"
                                value="<?= e($config['session_duration'] ?? '3600') ?>">
                        </div>
                    </div>
                </div>

                <!-- ✅ Login Captcha -->
                <div class="mb-4 border-bottom pb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold mb-0" lang="login_captcha"></h6>
                            <small lang="login_captcha_desc"></small>
                        </div>
                        <div class="form-check form-switch fs-4">
                            <input class="form-check-input" type="checkbox" name="login_captcha" value="1"
                                <?= ($config['login_captcha'] ?? '0') == '1' ? 'checked' : '' ?>>
                        </div>
                    </div>
                </div>

                <!-- ✅ Max Login Attempts -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold mb-0" lang="max_login_attempts"></h6>
                            <small lang="max_login_attempts_desc"></small>
                        </div>
                        <div style="width: 250px;">
                            <input type="number" class="form-control" name="max_login_attempts"
                                value="<?= e($config['max_login_attempts'] ?? '5') ?>">
                        </div>
                    </div>
                </div>

                <!-- ✅ Login Block Duration -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold mb-0" lang="login_block_duration"></h6>
                            <small lang="login_block_duration_desc"></small>
                        </div>
                        <div style="width: 250px;">
                            <input type="number" class="form-control" name="login_block_duration"
                                value="<?= e($config['login_block_duration'] ?? '15') ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <hr>
        <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save me-2"></i> <span lang="apply_changes"></span>
            </button>
        </div>
    </form>
</div>