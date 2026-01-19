// --- GLOBAL VARIABLES ---
let selectedFiles = [];
let fileListCache = [];
let diffDataCache = {};
let existingProviders = [];
let currentProviderKey = '';
let detailsModal; // Bootstrap modal instance

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

// --- PROVIDER & MODEL LOGIC ---

function loadProviders() {
    $.post('aisistem.php', { action: 'get_settings' }, (res) => {
        try {
            const data = (typeof res === 'object') ? res : JSON.parse(res);
            if (data.status === 'success') {
                existingProviders = data.providers;
                renderProviderSelect();
            }
        } catch (e) {
            console.error("Provider load error", e);
        }
    });
}

function renderProviderSelect() {
    const select = document.getElementById('ai-provider-select');
    if (!select) return;

    // Preserve selection if possible
    const currentVal = select.value || (existingProviders.length > 0 ? existingProviders[0].provider_key : '');

    select.innerHTML = '';

    existingProviders.forEach(p => {
        // Only show enabled providers in the main chat dropdown? Or show all but mark disabled?
        // Let's show all for now, maybe add (Disabled) text
        const opt = document.createElement('option');
        opt.value = p.provider_key;
        opt.textContent = p.provider_name + (p.is_enabled == 1 ? '' : ' (Kapalı)');
        if (p.provider_key === currentVal) opt.selected = true;
        select.appendChild(opt);
    });

    // Trigger change to load models
    select.dispatchEvent(new Event('change'));
}

function updateModelSelect(providerKey) {
    const modelSelect = document.getElementById('ai-model-select');
    if (!modelSelect) return;

    modelSelect.innerHTML = '';

    const provider = existingProviders.find(p => p.provider_key === providerKey);
    if (!provider || !provider.models) {
        modelSelect.innerHTML = '<option disabled>Model bulunamadı</option>';
        return;
    }

    let models = provider.models;
    if (typeof models === 'string') {
        try { models = JSON.parse(models); } catch (e) { models = []; }
    }

    if (!Array.isArray(models)) models = [];

    models.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.id;
        opt.textContent = m.name + (m.free ? ' (Ücretsiz)' : '');
        modelSelect.appendChild(opt);
    });
}

// --- DOM READY INIT ---

document.addEventListener('DOMContentLoaded', () => {

    // Elements
    const chatArea = document.getElementById('ai-chat-area');
    const userInput = document.getElementById('ai-user-input');
    const sendBtn = document.getElementById('btn-send-ai');

    const providerSelect = document.getElementById('ai-provider-select');
    const modelSelect = document.getElementById('ai-model-select');
    const btnManageProviders = document.getElementById('btn-manage-providers');

    const fileBrowser = document.getElementById('mini-file-browser');
    const fileSearch = document.getElementById('file-search-mini');
    const btnAttach = document.getElementById('btn-attach-file');
    const refreshBtn = document.getElementById('btn-refresh-files');

    // Init Providers
    loadProviders();
    loadFiles();

    // Provider Change Event
    if (providerSelect) {
        providerSelect.addEventListener('change', () => {
            currentProviderKey = providerSelect.value;
            updateModelSelect(currentProviderKey);
        });
    }

    // Refresh Files
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
                fileBrowser.innerHTML = '<div class="text-danger p-2">Listeleme hatası.</div>';
            }
        });
    }
    if (refreshBtn) refreshBtn.addEventListener('click', loadFiles);

    // File Search Listener
    if (fileSearch) {
        fileSearch.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            const filtered = fileListCache.filter(f => f.toLowerCase().includes(term));
            renderFileList(filtered);
        });
    }

    // --- MANAGE PROVIDERS MODAL ---

    const modalEl = document.getElementById('modal-provider-details');
    const btnSaveProvider = document.getElementById('btn-save-provider');
    const btnAddModelRow = document.getElementById('btn-add-model-row');
    const editModelList = document.getElementById('edit-model-list');

    if (btnManageProviders) {
        btnManageProviders.addEventListener('click', () => {
            // Open modal for the CURRENT selected provider
            const pKey = providerSelect.value;
            const p = existingProviders.find(x => x.provider_key === pKey);
            if (!p) return;

            openProviderModal(p);
        });
    }

    function openProviderModal(provider) {
        if (!modalEl) return;

        document.getElementById('provider-modal-title').textContent = provider.provider_name + ' Ayarları';
        document.getElementById('edit-provider-key').value = provider.provider_key;
        document.getElementById('edit-api-key').value = provider.api_key || '';
        document.getElementById('edit-is-enabled').checked = (provider.is_enabled == 1);

        // Render Models
        editModelList.innerHTML = '';

        let models = provider.models || [];
        if (typeof models === 'string') {
            try { models = JSON.parse(models); } catch (e) { models = []; }
        }

        models.forEach(m => addModelRow(m.id, m.name, m.free));

        if (typeof bootstrap !== 'undefined') {
            detailsModal = new bootstrap.Modal(modalEl);
            detailsModal.show();
        }
    }

    function addModelRow(id = '', name = '', free = false) {
        const div = document.createElement('div');
        div.className = 'd-flex gap-2 align-items-center mb-1';
        div.innerHTML = `
            <input type="text" class="form-control form-control-sm" placeholder="Model ID" value="${id}" data-type="id">
            <input type="text" class="form-control form-control-sm" placeholder="Görünen İsim" value="${name}" data-type="name">
            <div class="form-check" title="Ücretsiz mi?">
                <input class="form-check-input" type="checkbox" ${free ? 'checked' : ''} data-type="free">
            </div>
            <button class="btn btn-sm btn-outline-danger btn-remove-row"><i class="fas fa-times"></i></button>
        `;
        div.querySelector('.btn-remove-row').addEventListener('click', () => div.remove());
        editModelList.appendChild(div);
    }

    if (btnAddModelRow) {
        btnAddModelRow.addEventListener('click', () => addModelRow());
    }

    if (btnSaveProvider) {
        btnSaveProvider.addEventListener('click', () => {
            const key = document.getElementById('edit-provider-key').value;
            const apiKey = document.getElementById('edit-api-key').value.trim();
            const isEnabled = document.getElementById('edit-is-enabled').checked ? 1 : 0;

            // Gather models
            const models = [];
            Array.from(editModelList.children).forEach(row => {
                const id = row.querySelector('[data-type="id"]').value.trim();
                const name = row.querySelector('[data-type="name"]').value.trim();
                const free = row.querySelector('[data-type="free"]').checked;
                if (id && name) {
                    models.push({ id, name, free });
                }
            });

            $.post('aisistem.php', {
                action: 'save_provider',
                provider_key: key,
                api_key: apiKey,
                is_enabled: isEnabled,
                models: JSON.stringify(models)
            }, (res) => {
                let data = {};
                try { data = typeof res === 'object' ? res : JSON.parse(res); } catch (e) { }

                if (data.status === 'success') {
                    alert('Kaydedildi!');
                    if (detailsModal) detailsModal.hide();
                    loadProviders(); // Refresh UI
                } else {
                    alert('Hata: ' + (data.message || 'Bilinmeyen'));
                }
            });
        });
    }


    // --- CHAT LOGIC ---

    if (sendBtn) {
        sendBtn.addEventListener('click', () => {
            const text = userInput.value.trim();
            if (!text) return;

            appendMessage('user', text);
            userInput.value = '';

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
                provider: providerSelect ? providerSelect.value : 'gemini',
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
                        let data;
                        if (typeof response === 'object') {
                            data = response;
                        } else {
                            data = JSON.parse(response);
                        }

                        if (data.status === 'success') {
                            if (data.content) {
                                appendMessage('ai', data.content);
                            } else {
                                appendMessage('ai', '_(Boş yanıt)_');
                            }
                        } else {
                            appendMessage('ai', `**Hata:** ${data.message || 'Bilinmeyen hata.'}`);
                        }
                    } catch (e) {
                        appendMessage('ai', `**Sistem Hatası:** ${e.message}`);
                    }
                },
                error: function (xhr, status, error) {
                    const lDiv = document.getElementById(loadingId);
                    if (lDiv) lDiv.remove();
                    appendMessage('ai', `**Hata:** Sunucu bağlantı hatası (${xhr.status}).`);
                }
            });
        });
    }

    // Auto-resize textarea
    if (userInput) {
        userInput.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        userInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendBtn.click();
            }
        });
    }

    // --- APPLY PATCH DELEGATED ---
    const btnApplyPatch = document.querySelector('#modal-diff-view .btn-success');
    if (btnApplyPatch) {
        btnApplyPatch.addEventListener('click', () => {
            const id = btnApplyPatch.dataset.currentDiffId;
            const currentDiffData = diffDataCache[id];
            if (!currentDiffData) return;

            if (!confirm('DİKKAT: Bu değişiklik dosyaya yazılacak. Yedek alınacak ancak yine de emin misiniz?')) return;

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
                } else {
                    alert('HATA: ' + (data ? data.message : 'Bilinmeyen hata'));
                }
            });
        });
    }
});

// Expose openDiffModal globally
window.openDiffModal = openDiffModal;
