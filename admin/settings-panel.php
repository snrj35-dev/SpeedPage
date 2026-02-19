<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

/** @var PDO $db */
global $db;

$stmt = $db->query("SELECT `key`, `value` FROM settings");
$config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Mevcut dilleri tara
$languages = [];
if (defined('LANG_DIR') && is_dir(LANG_DIR)) {
    foreach (scandir(LANG_DIR) as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
            $languages[] = pathinfo($file, PATHINFO_FILENAME);
        }
    }
}
?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-soft py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold text-primary">
            <i class="fas fa-sliders-h me-2"></i> <span lang="system_settings">Sistem Ayarları</span>
        </h5>
        <?php if (isset($_GET['status']) && $_GET['status'] == 'ok'): ?>
            <span class="badge bg-success p-2 animate__animated animate__fadeIn" lang="successfully_saved">Başarıyla
                Kaydedildi!</span>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <form action="settings-edit.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
            <input type="hidden" name="active_tab" id="active_tab" value="<?= e($_GET['tab'] ?? 'genel') ?>">

            <ul class="nav nav-pills mb-4 bg-soft p-2 rounded-3" id="settingsTab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#genel">
                        <i class="fas fa-home me-2"></i><span lang="site_general">Genel</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#dil-sekme">
                        <i class="fas fa-language me-2"></i><span lang="language_region">Dil & Bölge</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#seo-sekme">
                        <i class="fas fa-search me-2"></i><span lang="seo_url">SEO & URL</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#guvenlik">
                        <i class="fas fa-shield-alt me-2"></i><span lang="security">Güvenlik</span>
                    </button>
                </li>
            </ul>

            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const activeTabId = document.getElementById('active_tab').value;
                    const tabBtn = document.querySelector(`[data-bs-target="#${activeTabId}"]`);
                    if (tabBtn) {
                        bootstrap.Tab.getOrCreateInstance(tabBtn).show();
                    }

                    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(btn => {
                        btn.addEventListener('shown.bs.tab', (e) => {
                            const targetId = e.target.getAttribute('data-bs-target').replace('#', '');
                            document.getElementById('active_tab').value = targetId;
                            const newUrl = new URL(window.location);
                            newUrl.searchParams.set('tab', targetId);
                            window.history.replaceState({}, '', newUrl);
                        });
                    });
                });
            </script>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="genel">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold" lang="site_name">Site İsmi</label>
                            <input type="text" class="form-control" name="site_name"
                                value="<?= e($config['site_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" lang="site_slogan">Site Sloganı</label>
                            <input type="text" class="form-control" name="site_slogan"
                                value="<?= e($config['site_slogan'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" lang="site_protocol">Site Protokolü</label>
                            <select class="form-select" name="site_protocol">
                                <option value="http" <?= ($config['site_protocol'] ?? 'http') == 'http' ? 'selected' : '' ?>>http://</option>
                                <option value="https" <?= ($config['site_protocol'] ?? 'http') == 'https' ? 'selected' : '' ?>>https://</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" lang="site_status">Site Durumu</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="site_public" value="1"
                                    <?= ($config['site_public'] ?? '1') == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" lang="site_public">Site Herkese Açık</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold" lang="logo_url">Logo URL</label>
                            <input type="text" class="form-control" name="logo_url"
                                value="<?= e($config['logo_url'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="dil-sekme">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold" lang="default_system_lang">Varsayılan Sistem Dili</label>
                            <select class="form-select" name="default_lang">
                                <option value="" lang="system_lang_auto">Sistem Dili (Otomatik)</option>
                                <?php foreach ($languages as $lang): ?>
                                    <option value="<?= $lang ?>" <?= ($config['default_lang'] ?? 'tr') == $lang ? 'selected' : '' ?>>
                                        <?= strtoupper($lang) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" lang="upload_new_lang">Yeni Dil Yükle (.json)</label>
                            <input type="file" name="new_lang_file" class="form-control" accept=".json">
                            <small class="text-muted" lang="only_json_accepted">Sadece .json dosyaları kabul
                                edilir.</small>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="seo-sekme">
                    <div class="mb-3">
                        <label class="form-label fw-bold" lang="meta_description">Meta Açıklaması</label>
                        <textarea class="form-control" name="meta_description"
                            rows="3"><?= e($config['meta_description'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" lang="meta_keywords">Meta Anahtar Kelimeler</label>
                        <input type="text" class="form-control" name="meta_keywords"
                            value="<?= e($config['meta_keywords'] ?? '') ?>" placeholder="kelime1, kelime2, ...">
                    </div>
                    <div class="form-check form-switch mb-3">
                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-danger d-flex align-items-center" role="alert">
                                <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
                                <div>
                                    <strong lang="system_file_write_error">Sistem Dosyası Yazılamadı!</strong><br>
                                    <?php if ($_GET['error'] === 'perm_error'): ?>
                                        <span lang="perm_error_msg">Sunucu ana dizinine yazma izni yok.</span>
                                    <?php else: ?>
                                        <span lang="htaccess_error_msg">.htaccess dosyası oluşturulurken teknik bir hata
                                            oluştu.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <input class="form-check-input" type="checkbox" name="friendly_url" value="1"
                            <?= ($config['friendly_url'] ?? '0') == '1' ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" lang="friendly_url">SEO Dostu URL</label>
                        <div class="small text mb-2" lang="friendly_url_desc">Site link yapısını /?page=x yerine /x
                            formatına çevirir.</div>
                        <div class="alert alert-warning small py-2">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span lang="htaccess_warning">Not: Bu özellik sunucunuzda .htaccess dosyası
                                oluşturacaktır.</span>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="guvenlik">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="fw-bold border-bottom pb-2" lang="membership_permissions">Üyelik & İzinler</h6>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="registration_enabled" value="1"
                                    <?= ($config['registration_enabled'] ?? '0') == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" lang="registration_enabled">Üye Kaydı Açık</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="email_verification" value="1"
                                    <?= ($config['email_verification'] ?? '0') == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" lang="email_verification_required">E-posta Doğrulaması
                                    Gerekli</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="allow_username_change" value="1"
                                    <?= ($config['allow_username_change'] ?? '0') == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" lang="allow_username_change">Kullanıcı Adı Değişikliğine
                                    İzin Ver</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="allow_password_change" value="1"
                                    <?= ($config['allow_password_change'] ?? '0') == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" lang="allow_password_change">Şifre Değişikliğine İzin
                                    Ver</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="allow_user_theme" value="1"
                                    <?= ($config['allow_user_theme'] ?? '0') == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" lang="allow_user_theme">Kullanıcı Tema Seçimine İzin
                                    Ver</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="allow_page_php" value="1"
                                    <?= ($config['allow_page_php'] ?? '0') == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label">Sayfa İçeriğinde PHP Çalıştırmaya İzin Ver (Riskli)</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="allow_module_php_scripts" value="1"
                                    <?= ($config['allow_module_php_scripts'] ?? '0') == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label">Modül install/migration/uninstall PHP script çalıştırmaya izin ver (Çok Riskli)</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="login_captcha" value="1"
                                    <?= ($config['login_captcha'] ?? '0') == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" lang="login_captcha_required">Girişte Captcha
                                    Zorunlu</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold border-bottom pb-2" lang="session_limitations">Oturum & Kısıtlamalar</h6>
                            <div class="mb-3">
                                <label class="form-label small fw-bold" lang="session_duration">Oturum Süresi</label>
                                <input type="number" class="form-control form-control-sm" name="session_duration"
                                    value="<?= e($config['session_duration'] ?? '3600') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold" lang="max_login_attempts">Maksimum Giriş
                                    Denemesi</label>
                                <input type="number" class="form-control form-control-sm" name="max_login_attempts"
                                    value="<?= e($config['max_login_attempts'] ?? '5') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold" lang="block_duration">Blok Süresi</label>
                                <input type="number" class="form-control form-control-sm" name="login_block_duration"
                                    value="<?= e($config['login_block_duration'] ?? '15') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary px-5 shadow-sm">
                    <i class="fas fa-save me-2"></i> <span lang="save_all_changes">Tüm Değişiklikleri Kaydet</span>
                </button>
            </div>
        </form>
    </div>
</div>
