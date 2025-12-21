<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';
// Mevcut ayarları çek
$stmt = $db->query("SELECT `key`, `value` FROM settings");
$config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>



            <div class="card p-3 mb-4">
                
                    
                    <?php if(isset($_GET['status']) && $_GET['status'] == 'ok'): ?>
                        <div class="alert alert-success">Ayarlar başarıyla kaydedildi!</div>
                    <?php endif; ?>

                    <form action="settings-edit.php" method="POST">
                        
                        <ul class="nav nav-tabs mb-4" id="settingsTab" role="tablist">
                             <li class="nav-item">
                                <button class="nav-link active" id="site-tab" data-bs-toggle="tab" data-bs-target="#site-settings" type="button">
                                    <i class="fas fa-globe me-1"></i> Site Genel
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" id="user-tab" data-bs-toggle="tab" data-bs-target="#user-settings" type="button">
                                    <i class="fas fa-users me-1"></i> Üyelik Ayarları
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="settingsTabContent">
                            
                            <div class="tab-pane fade show active p-3 mb-4" id="site-settings">

                                <!-- ✅ Site Durumu -->
                                <div class="mb-4 border-bottom pb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="fw-bold mb-0">Site Durumu</h6>
                                            <small>Siteyi herkese açabilir veya sadece giriş yapmış kullanıcılara görünür yapabilirsiniz.</small>
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
                                            <h6 class="fw-bold mb-0">Site İsmi</h6>
                                            <small>Tarayıcı sekmesinde ve bazı alanlarda görünecek site adını belirleyin.</small>
                                        </div>
                                        <div style="width: 250px;">
                                            <input type="text" class="form-control" name="site_name"
                                                value="<?= htmlspecialchars($config['site_name'] ?? '') ?>"
                                                placeholder="Örn: SpeedPage">
                                        </div>
                                    </div>
                                </div>
                                <!-- ✅ Site Sloganı -->
                                <div class="mb-4 border-bottom pb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="fw-bold mb-0">Site Sloganı</h6>
                                            <small>Ana sayfada ve bazı alanlarda görünecek kısa slogan.</small>
                                        </div>
                                        <div style="width: 250px;">
                                            <input type="text" class="form-control" name="site_slogan"
                                                value="<?= htmlspecialchars($config['site_slogan'] ?? '') ?>"
                                                placeholder="Örn: Hızlı. Hafif. Modern.">
                                        </div>
                                    </div>
                                </div>

                                <!-- ✅ Meta Description -->
                                <div class="mb-4 border-bottom pb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="fw-bold mb-0">Meta Açıklaması</h6>
                                            <small>Arama motorlarında görünen kısa açıklama.</small>
                                        </div>
                                        <div style="width: 250px;">
                                            <input type="text" class="form-control" name="meta_description"
                                                value="<?= htmlspecialchars($config['meta_description'] ?? '') ?>"
                                                placeholder="Örn: SpeedPage ile hızlı web deneyimi.">
                                        </div>
                                    </div>
                                </div>

                                <!-- ✅ Logo URL -->
                                <div class="mb-4 border-bottom pb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="fw-bold mb-0">Logo URL</h6>
                                            <small>Site logosu için bir görsel URL'si girin.</small>
                                        </div>
                                        <div style="width: 250px;">
                                            <input type="text" class="form-control" name="logo_url"
                                                value="<?= htmlspecialchars($config['logo_url'] ?? '') ?>"
                                                placeholder="Örn: https://site.com/logo.png">
                                        </div>
                                    </div>
                                </div>

                            </div>



                            <div class="tab-pane fade p-3 mb-4" id="user-settings">
                                <div class="mb-4 border-bottom pb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="fw-bold mb-0">Yeni Kayıt Sistemi</h6>
                                            <small>Ziyaretçilerin hesap oluşturmasına izin ver.</small>
                                        </div>
                                        <div class="form-check form-switch fs-4">
                                            <input class="form-check-input" type="checkbox" name="registration_enabled" value="1" 
                                                <?= ($config['registration_enabled'] ?? '0') == '1' ? 'checked' : '' ?>>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="fw-bold mb-0">E-posta Aktivasyonu</h6>
                                            <small>Kayıt sonrası e-posta onayı zorunlu olsun.</small>
                                        </div>
                                        <div class="form-check form-switch fs-4">
                                            <input class="form-check-input" type="checkbox" name="email_verification" value="1"
                                                <?= ($config['email_verification'] ?? '0') == '1' ? 'checked' : '' ?>>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i> Değişiklikleri Uygula
                            </button>
                        </div>
                    </form>
            </div>
       
