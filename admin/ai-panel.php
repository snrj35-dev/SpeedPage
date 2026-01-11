<?php
// Admin yetkisi kontrolü
if (!$is_admin) {
    echo "<div class='alert alert-danger'>Yetkisiz erişim.</div>";
    return;
}
?>

<div class="row h-100 g-0">
    <!-- Sol Panel: AI Chat -->
    <div class="col-md-9 d-flex flex-column h-100" style="min-height: calc(100vh - 120px);">
        <div class="card shadow-sm flex-grow-1 d-flex flex-column m-2" style="border-radius: 12px; overflow: hidden;">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <div class="icon-square bg-light text-primary me-3 rounded-3"
                        style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-robot fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold" lang="ai_assistant_title">AI Asistan</h5>
                        <small class="text-muted" lang="ai_assistant_desc">Sistem yöneticisi yardımcısı</small>
                    </div>
                </div>
                <div>
                    <span class="badge bg-success rounded-pill" id="ai-status-badge">Online</span>
                </div>
            </div>

            <div class="card-body p-0 d-flex flex-column position-relative bg-light">
                <!-- Chat Area -->
                <div id="ai-chat-area" class="flex-grow-1 p-4 overflow-auto" style="scroll-behavior: smooth;">
                    <!-- Mesajlar buraya gelecek -->
                    <div class="text-center text-muted mt-5">
                        <i class="fas fa-magic fa-3x mb-3 text-primary opacity-50"></i>
                        <h4 lang="ai_welcome">Merhaba, size nasıl yardımcı olabilirim?</h4>
                        <p lang="ai_welcome_sub">Dosya analizi, hata giderme veya kod yazma konusunda bana
                            danışabilirsiniz.</p>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="p-3 bg-white border-top">
                    <!-- Dosya Seçimi Göstergesi -->
                    <div id="selected-files-badges" class="mb-2 d-none">
                        <!-- Seçili dosyalar JS ile buraya eklenecek -->
                    </div>

                    <div class="input-group">
                        <button class="btn btn-outline-secondary" type="button" id="btn-attach-file" title="Dosya Ekle">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <textarea class="form-control" id="ai-user-input" rows="1" placeholder="Mesajınızı yazın..."
                            style="resize:none; padding-top:10px;"></textarea>
                        <button class="btn btn-primary px-4" type="button" id="btn-send-ai">
                            <i class="fas fa-paper-plane"></i> <span class="d-none d-md-inline"
                                lang="send">Gönder</span>
                        </button>
                    </div>
                    <div class="text-end mt-1">
                        <small class="text-muted" style="font-size: 0.75rem;">Enter: Gönder | Shift+Enter: Yeni
                            Satır</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sağ Panel: Ayarlar ve Dosyalar -->
    <div class="col-md-3 h-100">
        <div class="m-2 d-flex flex-column gap-3">

            <!-- Model & API Ayarları -->
            <div class="card shadow-sm border-0" style="border-radius: 12px;">
                <div class="card-header border-0 fw-bold py-3">
                    <i class="fas fa-cogs me-2 text-secondary"></i> <span lang="settings">Ayarlar</span>
                </div>
                <div class="card-body">
                    <!-- API Base URL -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">API Endpoint URL</label>
                        <input type="text" class="form-control form-control-sm" id="ai-api-url"
                            placeholder="https://openrouter.ai/api/v1">
                        <div class="form-text" style="font-size: 0.7rem;">Varsayılan: OpenRouter. OpenAI uyumlu tüm
                            servisler desteklenir.</div>
                    </div>

                    <!-- API Key Durumu -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">API Anahtarı</label>
                        <div class="input-group input-group-sm">
                            <input type="password" class="form-control" id="ai-api-key" placeholder="sk-...">
                            <button class="btn btn-outline-primary" id="btn-save-api"><i
                                    class="fas fa-save"></i></button>
                        </div>
                        <div id="api-key-status" class="form-text text-danger d-none"><i
                                class="fas fa-exclamation-circle"></i> Kayıtlı değil!</div>
                    </div>

                    <!-- Model Seçimi -->
                    <div class="mb-3">
                        <label
                            class="form-label small fw-bold text-muted d-flex justify-content-between align-items-center">
                            Model
                            <button class="btn btn-xs btn-link text-decoration-none p-0" id="btn-manage-models"
                                style="font-size:0.8rem;">
                                <i class="fas fa-edit"></i> Düzenle
                            </button>
                        </label>
                        <select class="form-select form-select-sm" id="ai-model-select">
                            <option value="" disabled selected>Yükleniyor...</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Dosya Gezgini (Mini) -->
            <div class="card shadow-sm border-0 flex-grow-1" style="border-radius: 12px; height: calc(100vh - 350px);">
                <div class="card-header border-0 fw-bold py-3 d-flex justify-content-between">
                    <span><i class="fas fa-folder me-2 text-warning"></i> <span lang="files">Dosyalar</span></span>
                    <button class="btn btn-sm btn-light text-primary" id="btn-refresh-files"><i
                            class="fas fa-sync-alt"></i></button>
                </div>
                <div class="p-2 border-bottom">
                    <input type="text" class="form-control form-control-sm" id="file-search-mini"
                        placeholder="Dosya ara...">
                </div>
                <div class="card-body p-0 overflow-auto" id="mini-file-browser">
                    <div class="text-center p-3 text-muted">Dosyalar yükleniyor...</div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Dosya Ekleme Yardımcısı Modal -->
<div class="modal fade" id="modal-file-browser" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Dosya Seç</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Full tree view here -->
            </div>
        </div>
    </div>
</div>

<!-- Model Yönetimi Modal -->
<div class="modal fade" id="modal-manage-models" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Model Yönetimi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2" style="font-size: 0.85rem;">
                    <i class="fas fa-info-circle"></i> OpenRouter ID'lerini girerek kendi modellerinizi
                    ekleyebilirsiniz. Varsayılan modeller silinemez.
                </div>
                <div id="custom-model-list" class="list-group mb-3">
                    <!-- JS ile doldurulacak -->
                </div>
                <hr>
                <h6>Yeni Model Ekle</h6>
                <div class="input-group mb-2">
                    <input type="text" class="form-control" id="new-model-id" placeholder="ID (örn: google/gemini-pro)">
                    <input type="text" class="form-control" id="new-model-name" placeholder="Görünen İsim">
                </div>
                <button class="btn btn-success w-100" id="btn-add-model"><i class="fas fa-plus"></i> Ekle</button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="btn-save-custom-models">Kaydet ve Kapat</button>
            </div>
        </div>
    </div>
</div>

<!-- Diff Görüntüleme Modalı -->
<div class="modal fade" id="modal-diff-view" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="diff-modal-title">Değişiklik İnceleme</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="container-fluid h-100">
                    <div class="row h-100">
                        <div class="col-6 p-0 border-end border-secondary h-100 d-flex flex-column">
                            <div class="bg-danger text-white p-2 text-center small fw-bold">ESKİ KOD
                                (Silinecek)</div>
                            <pre
                                class="m-0 h-100 overflow-auto"><code class="language-php h-100" id="diff-old-code" style="border-radius:0;"></code></pre>
                        </div>
                        <div class="col-6 p-0 h-100 d-flex flex-column">
                            <div class="bg-success text-white p-2 text-center small fw-bold">YENİ KOD
                                (Eklenecek)</div>
                            <pre
                                class="m-0 h-100 overflow-auto"><code class="language-php h-100" id="diff-new-code" style="border-radius:0;"></code></pre>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-dark border-top-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-success" disabled title="Otomatik uygulama henüz aktif değil">
                    <i class="fas fa-check"></i> Değişiklikleri Dosyaya Yaz
                </button>
            </div>
        </div>
    </div>
</div>