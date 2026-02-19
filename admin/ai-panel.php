<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';

// Admin check
if (isset($is_admin) && !$is_admin) {
    echo "<div class='alert alert-danger'>" . __('access_denied') . "</div>";
    return;
}
?>

<div class="row h-100 g-0">
    <script>
        const AI_CSRF_TOKEN = '<?= e($_SESSION['csrf']) ?>';
    </script>
    <!-- Left Panel: Chat -->
    <div class="col-md-9 d-flex flex-column h-100" style="min-height: calc(100vh - 120px);">
        <div class="card shadow-sm flex-grow-1 d-flex flex-column m-2 rounded-4 overflow-hidden border-0">
            <!-- Header -->
            <div class="card-header  py-3 d-flex justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <div class="icon-square bg-primary-subtle text-primary me-3 rounded-3 d-flex align-items-center justify-content-center"
                        style="width:40px;height:40px;">
                        <i class="fas fa-robot fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold" lang="ai_assistant_title"><?= __('ai_assistant_title') ?></h5>
                        <small class="text-muted" lang="ai_assistant_desc"><?= __('ai_assistant_desc') ?></small>
                    </div>
                </div>
                <div>
                    <span class="badge bg-success rounded-pill px-3 py-2" id="ai-status-badge">
                        <i class="fas fa-circle me-1 small"></i> Online
                    </span>
                </div>
            </div>

            <!-- Chat Area -->
            <div class="card-body p-0 d-flex flex-column position-relative bg-light">
                <div id="ai-chat-area" class="flex-grow-1 p-4 overflow-auto custom-scrollbar"
                    style="scroll-behavior: smooth;">
                    <!-- Thinking / Status Indicator -->
                    <div id="ai-thought-status" class="d-none position-absolute top-0 start-50 translate-middle-x mt-2 z-3">
                        <span class="badge bg-info text-white rounded-pill px-3 py-2 shadow-sm">
                            <i class="fas fa-cog fa-spin me-2"></i> <span id="status-text">Analiz ediliyor...</span>
                        </span>
                    </div>
                    
                    <!-- Welcome Message -->
                    <div class="text-center text-muted mt-5">
                        <div class="mb-4">
                            <i class="fas fa-magic fa-4x text-primary opacity-25"></i>
                        </div>
                        <h4 class="fw-bold mb-2" lang="ai_welcome"><?= __('ai_welcome') ?></h4>
                        <p lang="ai_welcome_sub"><?= __('ai_welcome_sub') ?></p>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="p-3 border-top">
                    <div id="selected-files-badges" class="mb-2 d-none d-flex gap-2 flex-wrap"></div>

                    <div class="input-group shadow-sm rounded-3">
                        <button class="btn btn-light border" type="button" id="btn-attach-file"
                            title="<?= __('ai_files') ?>">
                            <i class="fas fa-paperclip text-secondary"></i>
                        </button>
                        <textarea class="form-control border-0" id="ai-user-input" rows="1"
                            placeholder="<?= __('ai_new_msg_placeholder') ?>"
                            style="resize:none; padding-top:10px; min-height: 45px;"></textarea>
                        <button class="btn btn-primary px-4" type="button" id="btn-send-ai">
                            <i class="fas fa-paper-plane me-2"></i> <span class="d-none d-md-inline"
                                lang="send"><?= __('send') ?></span>
                        </button>
                        <button class="btn btn-danger px-3 d-none" type="button" id="btn-stop-ai" title="Durdur">
                            <i class="fas fa-stop"></i>
                        </button>
                    </div>
                    <div class="text-end mt-1 px-1">
                        <small class="text-muted" style="font-size: 0.75rem;"
                            lang="ai_send_hint"><?= __('ai_send_hint') ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Panel: Settings -->
    <div class="col-md-3 h-100">
        <div class="m-2 d-flex flex-column gap-3 h-100">

            <!-- Settings Card -->
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header border-0 fw-bold py-3">
                    <i class="fas fa-cogs me-2 text-secondary"></i> <span
                        lang="ai_settings"><?= __('ai_settings') ?></span>
                </div>
                <div class="card-body">

                    <!-- Provider Select -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">AI Sağlayıcı</label>
                        <select class="form-select form-select-sm" id="ai-provider-select">
                            <option value="gemini">Google Gemini</option>
                            <option value="openrouter">OpenRouter</option>
                        </select>
                    </div>

                    <!-- Persona Select -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Persona (Kişilik)</label>
                        <select class="form-select form-select-sm" id="ai-persona-select">
                            <option value="default">Genel Asistan</option>
                        </select>
                    </div>

                    <!-- Model Select -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Model</label>
                        <select class="form-select form-select-sm" id="ai-model-select">
                            <option value="">Model yükleniyor...</option>
                        </select>
                    </div>

                    <button class="btn btn-xs btn-outline-primary w-100" id="btn-manage-providers">
                        <i class="fas fa-key me-1"></i> <span lang="ai_manage_keys">Anahtarları Yönet</span>
                    </button>

                </div>
            </div>

            <!-- File Explorer Card -->
            <div class="card shadow-sm border-0 flex-grow-1 rounded-4 overflow-hidden" style="min-height: 300px;">
                <div class="card-header border-0 fw-bold py-3 d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-folder me-2 text-warning"></i> <span
                            lang="ai_files"><?= __('ai_files') ?></span></span>
                    <button class="btn btn-sm btn-light text-primary rounded-circle" id="btn-refresh-files"
                        title="<?= __('ai_files_refresh') ?>">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="p-2 border-bottom bg-light">
                    <input type="text" class="form-control form-control-sm" id="file-search-mini"
                        placeholder="<?= __('ai_files_search') ?>">
                </div>
                <div class="card-body p-0 overflow-auto custom-scrollbar" id="mini-file-browser">
                    <div class="text-center p-4 text-muted">
                        <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                        <div>Loading files...</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- File Browser Modal -->
<div class="modal fade" id="modal-file-browser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold" lang="ai_files"><?= __('ai_files') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Full tree view here injected by JS -->
                <div class="p-4 text-center text-muted">Loading tree...</div>
            </div>
        </div>
    </div>
</div>

<!-- Provider Details Modal -->
<div class="modal fade" id="modal-provider-details" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="provider-modal-title">Provider Ayarları</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit-provider-key">

                <div class="mb-3">
                    <label class="form-label small fw-bold">API Key</label>
                    <input type="password" class="form-control" id="edit-api-key">
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="edit-is-enabled">
                    <label class="form-check-label" for="edit-is-enabled">Bu Sağlayıcıyı Aktif Et</label>
                </div>

                <hr>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold m-0">Modeller</h6>
                    <button class="btn btn-xs btn-outline-success" id="btn-add-model-row"><i
                            class="fas fa-plus"></i></button>
                </div>

                <div id="edit-model-list" class="d-flex flex-column gap-2" style="max-height: 200px; overflow-y: auto;">
                    <!-- JS Injected -->
                </div>

            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-primary rounded-pill px-4" id="btn-save-provider">
                    Kaydet
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Diff View Modal -->
<div class="modal fade" id="modal-diff-view" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content border-0">
            <div class="modal-header bg-dark text-white border-bottom-0 py-2">
                <h5 class="modal-title fs-6" id="diff-modal-title" lang="ai_diff_view"><?= __('ai_diff_view') ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="container-fluid h-100">
                    <div class="row h-100 g-0">
                        <div class="col-6 border-end border-secondary h-100 d-flex flex-column bg-light">
                            <div class="bg-danger bg-opacity-10 text-danger p-2 text-center small fw-bold border-bottom border-danger-subtle"
                                lang="ai_diff_old"><?= __('ai_diff_old') ?></div>
                            <pre
                                class="m-0 h-100 overflow-auto p-3"><code class="language-php h-100" id="diff-old-code"></code></pre>
                        </div>
                        <div class="col-6 h-100 d-flex flex-column bg-light">
                            <div class="bg-success bg-opacity-10 text-success p-2 text-center small fw-bold border-bottom border-success-subtle"
                                lang="ai_diff_new"><?= __('ai_diff_new') ?></div>
                            <pre
                                class="m-0 h-100 overflow-auto p-3"><code class="language-php h-100" id="diff-new-code"></code></pre>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-dark border-top-0 py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal"
                    lang="ai_modal_close"><?= __('ai_modal_close') ?></button>
                <button type="button" class="btn btn-sm btn-success" id="btn-apply-diff"
                    lang="ai_diff_apply">
                    <i class="fas fa-check me-1"></i> <?= __('ai_diff_apply') ?>
                </button>
            </div>
        </div>
    </div>
</div>
