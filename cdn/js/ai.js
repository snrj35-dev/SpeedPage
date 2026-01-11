// --- GLOBAL VARIABLES ---
let selectedFiles = [];
let fileListCache = [];
let diffDataCache = {};
let allModelsCache = [];
let customModelsCache = [];
let manageModal; // Bootstrap modal instance

// --- UTILS ---

// Markdown Parser
const parseMarkdown = (text) => {
    if (!text) return '';
    if (typeof text !== 'string') text = String(text);

    if (typeof marked !== 'undefined') {
        try {
            return marked.parse(text);
        } catch (e) {
            console.error("Marked parse error:", e);
            return text;
        }
    }
    return text.replace(/\n/g, '<br>');
};

// --- FILE BROWSER FUNCTIONS ---

function renderFileList(files) {
    const fileBrowser = document.getElementById('mini-file-browser');
    if (!fileBrowser) return;

    fileBrowser.innerHTML = '';
    if (files.length === 0) {
        fileBrowser.innerHTML = '<div class="text-muted p-2 text-center small">Dosya yok.</div>';
        return;
    }

    files.forEach(file => {
        const div = document.createElement('div');
        div.className = 'mini-file-item';
        div.innerHTML = `<i class="fas fa-file-code mini-file-icon"></i> <span class="text-truncate">${file}</span>`;
        div.onclick = () => toggleFileSelection(file, div);

        if (selectedFiles.includes(file)) {
            div.classList.add('active');
        }

        fileBrowser.appendChild(div);
    });
}

function toggleFileSelection(file, el) {
    if (selectedFiles.includes(file)) {
        selectedFiles = selectedFiles.filter(f => f !== file);
        el.classList.remove('active');
    } else {
        selectedFiles.push(file);
        el.classList.add('active');
    }
    updateFileBadges();
}

function updateFileBadges() {
    const fileBadges = document.getElementById('selected-files-badges');
    const fileSearch = document.getElementById('file-search-mini');

    if (!fileBadges) return;
    fileBadges.innerHTML = '';

    if (selectedFiles.length > 0) {
        fileBadges.classList.remove('d-none');
        selectedFiles.forEach(file => {
            const span = document.createElement('span');
            span.className = 'file-badge';
            span.innerHTML = `${file.split('/').pop()} <button class="btn-close" data-file="${file}"></button>`;
            span.querySelector('.btn-close').addEventListener('click', (e) => {
                const f = e.target.getAttribute('data-file');
                selectedFiles = selectedFiles.filter(item => item !== f);
                updateFileBadges();

                const item = Array.from(document.querySelectorAll('.mini-file-item')).find(el => el.textContent.includes(f));
                if (item) item.classList.remove('active');

                if (fileSearch) {
                    renderFileList(fileSearch.value ? fileListCache.filter(x => x.toLowerCase().includes(fileSearch.value.toLowerCase())) : fileListCache);
                } else {
                    renderFileList(fileListCache);
                }
            });
            fileBadges.appendChild(span);
        });
    } else {
        fileBadges.classList.add('d-none');
    }
}

// --- CHAT FUNCTIONS ---

function appendMessage(role, content) {
    console.log('Mesaj Basılıyor:', content); // DEBUG LOG

    const chatArea = document.getElementById('ai-chat-area');
    if (!chatArea) return;

    if (role === 'ai') {
        // Regex to find [PATCH: ...][OLD]...[/OLD][NEW]...[/NEW]
        // Handles optional surrounding code blocks (```) that AI might mistakenly add.
        const patchRegex = /(?:```\w*\s*)?\[PATCH:\s*(.*?)\]\s*\[OLD\]([\s\S]*?)\[\/OLD\]\s*\[NEW\]([\s\S]*?)\[\/NEW\]\s*(?:```)?/g;

        content = content.replace(patchRegex, (match, filePath, oldCode, newCode) => {
            const id = 'diff-' + Math.random().toString(36).substr(2, 9);
            diffDataCache[id] = {
                file: filePath.trim(),
                old: oldCode.trim(),
                new: newCode.trim()
            };
            // Return raw HTML placeholder. 
            // Note: If parseMarkdown runs later, it needs to handle this DIV gracefully.
            // We ensure this DIV is separated by newlines to often help markdown parsers treat it as block HTML.
            return `\n\n<div class="card my-2 border-primary">
                        <div class="card-body p-2 d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-file-code me-2"></i> ${filePath.trim()}</span>
                            <button class="btn btn-primary btn-sm btn-view-diff" onclick="openDiffModal('${id}')">
                                <i class="fas fa-exchange-alt"></i> Değişikliği İncele
                            </button>
                        </div>
                    </div>\n\n`;
        });
    }

    const div = document.createElement('div');
    div.className = `chat-message ${role}`;

    if (role === 'ai') {
        div.innerHTML = `
            <div class="avatar"><i class="fas fa-robot"></i></div>
            <div class="message-content">${parseMarkdown(content)}</div>
        `;

        // Copy buttons
        div.querySelectorAll('pre').forEach(pre => {
            const btnWrapper = document.createElement('div');
            btnWrapper.className = 'code-actions-header';
            const copyBtn = document.createElement('button');
            copyBtn.className = 'code-btn';
            copyBtn.innerHTML = '<i class="far fa-copy"></i> Kopyala';
            copyBtn.onclick = () => {
                const code = pre.querySelector('code').innerText;
                navigator.clipboard.writeText(code);
                copyBtn.innerHTML = '<i class="fas fa-check"></i> Kopyalandı';
                setTimeout(() => copyBtn.innerHTML = '<i class="far fa-copy"></i> Kopyala', 2000);
            };
            btnWrapper.appendChild(copyBtn);
            pre.insertBefore(btnWrapper, pre.firstChild);
        });
    } else {
        div.innerHTML = `<div class="message-content">${content.replace(/\n/g, '<br>')}</div>`; // User message simple formatting
    }

    chatArea.appendChild(div);
    chatArea.scrollTop = chatArea.scrollHeight;
}

function openDiffModal(id) {
    const data = diffDataCache[id];
    if (!data) return;

    const diffTitle = document.getElementById('diff-modal-title');
    const diffOldCode = document.getElementById('diff-old-code');
    const diffNewCode = document.getElementById('diff-new-code');
    const btnApplyPatch = document.querySelector('#modal-diff-view .btn-success');

    // Store current data on the button for access
    if (btnApplyPatch) btnApplyPatch.dataset.currentDiffId = id;

    if (diffTitle) diffTitle.textContent = 'Değişiklik: ' + data.file;
    if (diffOldCode) diffOldCode.textContent = data.old;
    if (diffNewCode) diffNewCode.textContent = data.new;

    if (btnApplyPatch) {
        btnApplyPatch.disabled = false;
        btnApplyPatch.innerHTML = '<i class="fas fa-check"></i> Değişiklikleri Dosyaya Yaz';
    }

    if (typeof hljs !== 'undefined') {
        if (diffOldCode) { delete diffOldCode.dataset.highlighted; hljs.highlightElement(diffOldCode); }
        if (diffNewCode) { delete diffNewCode.dataset.highlighted; hljs.highlightElement(diffNewCode); }
    }

    const modalEl = document.getElementById('modal-diff-view');
    if (modalEl && typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}

// --- MODEL FUNCTIONS ---

function renderCustomModels() {
    const customModelList = document.getElementById('custom-model-list');
    if (!customModelList) return;

    customModelList.innerHTML = '';
    if (customModelsCache.length === 0) {
        customModelList.innerHTML = '<div class="text-center text-muted small p-2">Henüz özel model eklenmedi.</div>';
        return;
    }

    customModelsCache.forEach((model, index) => {
        const item = document.createElement('div');
        item.className = 'list-group-item d-flex justify-content-between align-items-center';
        item.innerHTML = `
            <div>
                <strong>${model.name}</strong><br>
                <small class="text-muted">${model.id}</small>
            </div>
            <button class="btn btn-sm btn-outline-danger btn-delete-model" data-index="${index}">
                <i class="fas fa-trash"></i>
            </button>
        `;
        customModelList.appendChild(item);
    });

    document.querySelectorAll('.btn-delete-model').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const idx = e.currentTarget.getAttribute('data-index');
            customModelsCache.splice(idx, 1);
            renderCustomModels();
        });
    });
}

// --- DOM READY INIT ---

document.addEventListener('DOMContentLoaded', () => {

    // Elements
    const chatArea = document.getElementById('ai-chat-area');
    const userInput = document.getElementById('ai-user-input');
    const sendBtn = document.getElementById('btn-send-ai');
    const apiKeyInput = document.getElementById('ai-api-key');
    const saveApiBtn = document.getElementById('btn-save-api');
    const modelSelect = document.getElementById('ai-model-select');
    const fileBrowser = document.getElementById('mini-file-browser');
    const fileSearch = document.getElementById('file-search-mini');
    const apiKeyStatus = document.getElementById('api-key-status');
    const btnAttach = document.getElementById('btn-attach-file');

    // Refresh files btn
    const refreshBtn = document.getElementById('btn-refresh-files');
    if (refreshBtn) refreshBtn.addEventListener('click', loadFiles);

    // File Search Listener
    if (fileSearch) {
        fileSearch.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            const filtered = fileListCache.filter(f => f.toLowerCase().includes(term));
            renderFileList(filtered);
        });
    }

    // --- AUTO ANALYZE BUG REPORT ---
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('auto_analyze') === '1') {
        const reportRaw = localStorage.getItem('ai_bug_report');
        if (reportRaw && userInput && sendBtn) {
            try {
                const report = JSON.parse(reportRaw);
                localStorage.removeItem('ai_bug_report');

                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?page=aipanel';
                window.history.pushState({ path: newUrl }, '', newUrl);

                let prompt = `🚨 **HATA Raporu Analizi**\n\n` +
                    `**Sayfa:** ${report.url}\n` +
                    `**Zaman:** ${report.timestamp}\n\n` +
                    `**Konsol Hataları:**\n` +
                    (report.errors.length ? '```\n' + report.errors.join('\n') + '\n```' : '_Konsolda hata görünmüyor._') +
                    `\n\n**Sayfa İçeriği Özeti:**\n` +
                    '```\n' + report.html.substring(0, 1000) + '...\n```\n\n' +
                    `Bu sayfada bir sorun tespit ettim. Yukarıdaki verilere ve sistem loglarına (snapshot) dayanarak hatanın kaynağını ve çözümünü bulabilir misin?`;

                setTimeout(() => {
                    userInput.value = prompt;
                    userInput.style.height = 'auto';
                    userInput.style.height = (userInput.scrollHeight) + 'px';
                    sendBtn.click();
                }, 500);

            } catch (e) {
                console.error("Rapor parse hatası", e);
            }
        }
    }

    // Auto-resize textarea
    if (userInput) {
        userInput.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
            if (this.value === '') this.style.height = 'auto';
        });

        userInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendBtn.click();
            }
        });
    }

    // --- API & SETTINGS ---
    function loadSettings() {
        $.post('aisistem.php', { action: 'get_settings' }, (res) => {
            try {
                const data = (typeof res === 'object') ? res : JSON.parse(res);
                if (data.status === 'success') {
                    if (apiKeyInput) apiKeyInput.value = data.api_key;

                    const apiUrlInput = document.getElementById('ai-api-url');
                    if (apiUrlInput) apiUrlInput.value = data.api_url || 'https://openrouter.ai/api/v1';

                    if (!data.api_key && apiKeyStatus) {
                        apiKeyStatus.classList.remove('d-none');
                    } else if (apiKeyStatus) {
                        apiKeyStatus.classList.add('d-none');
                    }
                    // ... (rest of models logic)
                    if (modelSelect) {
                        modelSelect.innerHTML = '';
                        allModelsCache = data.system_models || [];
                        if (data.custom_models_raw) {
                            customModelsCache = data.custom_models_raw;
                        }

                        allModelsCache.forEach(m => {
                            const opt = document.createElement('option');
                            opt.value = m.id;
                            opt.textContent = m.name;
                            if (m.id === data.selected_model) opt.selected = true;
                            modelSelect.appendChild(opt);
                        });
                    }
                }
            } catch (e) {
                console.error("Settings load error", e);
            }
        });
    }

    if (saveApiBtn) {
        saveApiBtn.addEventListener('click', () => {
            const key = apiKeyInput.value.trim();
            const url = document.getElementById('ai-api-url') ? document.getElementById('ai-api-url').value.trim() : '';
            const model = modelSelect ? modelSelect.value : '';

            $.post('aisistem.php', {
                action: 'save_settings',
                api_key: key,
                api_url: url,
                model: model
            }, (res) => {
                const data = (typeof res === 'object') ? res : JSON.parse(res);
                if (data.status === 'success') {
                    if (apiKeyStatus) apiKeyStatus.classList.add('d-none');
                    alert('Ayarlar kaydedildi.');
                }
            });
        });
    }

    if (modelSelect) {
        modelSelect.addEventListener('change', () => {
            const model = modelSelect.value;
            $.post('aisistem.php', { action: 'save_settings', model: model }, () => {
                console.log('Model tercihi güncellendi.');
            });
        });
    }

    function loadFiles() {
        if (!fileBrowser) return;
        fileBrowser.innerHTML = '<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i></div>';
        $.post('aisistem.php', { action: 'list_files' }, (res) => {
            try {
                const data = (typeof res === 'object') ? res : JSON.parse(res);
                if (data.status === 'success') {
                    fileListCache = data.files;
                    renderFileList(fileListCache);
                }
            } catch (e) {
                console.error("File list error", e);
                fileBrowser.innerHTML = '<div class="text-danger p-2">Listeleme hatası.</div>';
            }
        });
    }

    // --- CHAT LOGIC ---

    if (sendBtn) {
        sendBtn.addEventListener('click', () => {
            const text = userInput.value.trim();
            if (!text) return;

            appendMessage('user', text); // Global function
            userInput.value = '';
            userInput.style.height = 'auto';

            const loadingId = 'loading-' + Date.now();
            const loadingDiv = document.createElement('div');
            loadingDiv.id = loadingId;
            loadingDiv.className = 'chat-message ai';
            loadingDiv.innerHTML = `<div class="avatar"><i class="fas fa-robot"></i></div><div class="message-content"><i class="fas fa-circle-notch fa-spin"></i> Yanıt oluşturuluyor...</div>`;
            chatArea.appendChild(loadingDiv);
            chatArea.scrollTop = chatArea.scrollHeight;

            const payload = {
                action: 'chat',
                prompt: text,
                files: selectedFiles,
                model: modelSelect ? modelSelect.value : ''
            };

            $.ajax({
                url: 'aisistem.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(payload),
                success: function (response) {
                    const lDiv = document.getElementById(loadingId);
                    if (lDiv) lDiv.remove();

                    try {
                        console.log("[DEBUG] AI Raw Response:", response);

                        let data;
                        if (typeof response === 'object') {
                            data = response;
                        } else {
                            data = JSON.parse(response);
                        }

                        if (data.status === 'success') {
                            if (data.log_id) {
                                $.ajax({
                                    url: 'aisistem.php',
                                    type: 'POST',
                                    data: { action: 'get_response_text', log_id: data.log_id },
                                    dataType: 'text',
                                    success: function (rawText) {
                                        appendMessage('ai', rawText);
                                    },
                                    error: function (xhr, st, err) {
                                        console.error("Metin çekme hatası:", err);
                                        appendMessage('ai', '**Sistem Hatası:** Yanıt metni çekilemedi.');
                                    }
                                });
                            }
                            else if (data.content) {
                                appendMessage('ai', data.content);
                            } else {
                                appendMessage('ai', '_(Boş yanıt)_');
                            }
                        } else {
                            appendMessage('ai', `**Hata:** ${data.message || 'Bilinmeyen hata.'}`);
                        }
                    } catch (e) {
                        console.error("AI Logic Error:", e);
                        appendMessage('ai', `**Sistem Hatası:** ${e.message}`);
                    }
                },
                error: function (xhr, status, error) {
                    const lDiv = document.getElementById(loadingId);
                    if (lDiv) lDiv.remove();
                    console.error("AJAX Error:", status, error);
                    appendMessage('ai', `**Hata:** Sunucu bağlantı hatası (${xhr.status}).`);
                }
            });
        });
    }

    // --- MODEL MANAGEMENT MODAL ---
    const manageModelsBtn = document.getElementById('btn-manage-models');
    const modalManageModels = document.getElementById('modal-manage-models');
    const btnAddModel = document.getElementById('btn-add-model');
    const btnSaveCustomModels = document.getElementById('btn-save-custom-models');
    const newModelId = document.getElementById('new-model-id');
    const newModelName = document.getElementById('new-model-name');

    if (manageModelsBtn) {
        manageModelsBtn.addEventListener('click', () => {
            if (typeof bootstrap !== 'undefined') {
                manageModal = new bootstrap.Modal(modalManageModels);
                renderCustomModels();
                manageModal.show();
            }
        });
    }

    if (btnAddModel) {
        btnAddModel.addEventListener('click', () => {
            const id = newModelId.value.trim();
            const name = newModelName.value.trim();
            if (!id || !name) { alert('Lütfen ID ve İsim girin.'); return; }

            if (customModelsCache.some(m => m.id === id) || allModelsCache.some(m => m.id === id)) {
                alert('Bu ID zaten listede var.'); return;
            }

            customModelsCache.push({ id: id, name: name, free: false });
            newModelId.value = ''; newModelName.value = '';
            renderCustomModels();
        });
    }

    if (btnSaveCustomModels) {
        btnSaveCustomModels.addEventListener('click', () => {
            $.post('aisistem.php', {
                action: 'save_models',
                models: JSON.stringify(customModelsCache)
            }, (res) => {
                let data;
                try { data = (typeof res === 'object') ? res : JSON.parse(res); } catch (e) { }
                if (data && data.status === 'success') {
                    alert('Modeller güncellendi!');
                    if (manageModal) manageModal.hide();
                    loadSettings();
                } else {
                    alert('Hata oluştu.');
                }
            });
        });
    }

    // Apply Patch Listener (Delegated because it's inside a modal usually, or button exists always? It exists always in modal)
    const btnApplyPatch = document.querySelector('#modal-diff-view .btn-success');
    if (btnApplyPatch) {
        btnApplyPatch.addEventListener('click', () => {
            const id = btnApplyPatch.dataset.currentDiffId;
            const currentDiffData = diffDataCache[id];

            if (!currentDiffData) return;

            if (!confirm('DİKKAT: Bu değişiklik dosyaya yazılacak. Yedek alınacak ancak yine de emin misiniz?')) {
                return;
            }

            btnApplyPatch.disabled = true;
            btnApplyPatch.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uygulanıyor...';

            $.post('aisistem.php', {
                action: 'apply_ai_patch',
                file_path: currentDiffData.file,
                old_code: currentDiffData.old,
                new_code: currentDiffData.new
            }, (res) => {
                btnApplyPatch.disabled = false;
                btnApplyPatch.innerHTML = '<i class="fas fa-check"></i> Değişiklikleri Dosyaya Yaz';

                let data;
                try { data = (typeof res === 'object') ? res : JSON.parse(res); } catch (e) { }

                if (data && data.status === 'success') {
                    alert(`BAŞARILI!\n${data.message}\nYedek: ${data.backup_path}`);
                    const modalEl = document.getElementById('modal-diff-view');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();

                    /* 
                    // Sayfa yenileme iptal edildi (Chat geçmişi kaybolmasın diye)
                    const currentPage = new URLSearchParams(window.location.search).get('page') || 'home';
                    if (typeof window.loadPage === 'function') {
                        // window.loadPage(currentPage);
                    } else {
                        // location.reload();
                    }
                    */
                } else {
                    alert('HATA: ' + (data ? data.message : 'Bilinmeyen hata'));
                }
            });
        });
    }

    // Init
    loadSettings();
    loadFiles();
});
