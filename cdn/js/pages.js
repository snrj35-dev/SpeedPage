let editEditor = null;
let pageState = { page: 1, search: '' };

const STATIC_SNIPPETS = {
    // --- LAYOUT & GRIDS ---
    'grid2': `
    <div class="row">
        <div class="col-md-6">Sütun 1</div>
        <div class="col-md-6">Sütun 2</div>
    </div>`,

    'grid3': `
    <div class="row">
        <div class="col-md-4">1</div>
        <div class="col-md-4">2</div>
        <div class="col-md-4">3</div>
    </div>`,

    // --- CONTENT COMPONENTS ---
    'card': `
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h5 class="card-title">Kart Başlığı</h5>
            <p class="card-text">İçerik metni buraya gelecek.</p>
            <a href="#" class="btn btn-primary btn-sm">Git</a>
        </div>
    </div>`,

    'accordion': `
    <div class="accordion shadow-sm" id="accExample">
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                    Accordion Başlık
                </button>
            </h2>
            <div id="collapseOne" class="accordion-collapse collapse show">
                <div class="accordion-body">İçerik buraya...</div>
            </div>
        </div>
    </div>`,

    // --- MEDIA & INTERACTIVE ---
    'responsive_video': `
    <div class="ratio ratio-16x9">
        <iframe src="https://www.youtube.com/embed/VIDEO_ID" title="YouTube video" allowfullscreen></iframe>
    </div>`,

    'list_group': `
    <ul class="list-group shadow-sm">
        <li class="list-group-item d-flex justify-content-between align-items-center">
            Madde 1
            <span class="badge bg-primary rounded-pill">14</span>
        </li>
        <li class="list-group-item">Madde 2</li>
        <li class="list-group-item">Madde 3</li>
    </ul>`,

    // --- UI ELEMENTS ---
    'progress': `
    <div class="progress mb-3" style="height: 20px;">
        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 50%;">50%</div>
    </div>`,

    'spinner': '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Yükleniyor...</span></div>',
    'badge': '<span class="badge rounded-pill bg-primary">Yeni Etiket</span>',
    'alert': `
    <div class="alert alert-info border-0 shadow-sm d-flex align-items-center">
        <i class="fas fa-info-circle me-2"></i>
        <div><strong>Bilgi:</strong> Önemli bir duyuru buraya.</div>
    </div>`,

    // --- SYSTEM HOOKS ---
    'hook_header': '<?php run_hook(\'user_header\'); ?>',
    'hook_footer': '<?php run_hook(\'user_footer\'); ?>'
};

// --- MERKEZİ SNIPPET EKLEME FONKSİYONU ---
function insertSnippet(code) {
    const modalEl = document.getElementById('editModal');
    let targetEditor = newEditor;

    // Eğer edit modalı açıksa hedef editör editEditor'dür
    if (modalEl && modalEl.classList.contains('show') && editEditor) {
        targetEditor = editEditor;
    }

    // NULL Check added as per request
    if (!targetEditor) return;

    if (targetEditor) {
        // İmlecin olduğu yere kodu ekle
        targetEditor.replaceSelection(code);
        targetEditor.focus();
    }
}

function insertSnippetFromGlobal(id) {
    if (!id) return;

    // Önce statiklerden bak
    if (STATIC_SNIPPETS[id]) {
        insertSnippet(STATIC_SNIPPETS[id]);
        return;
    }

    // Sonra global veritabanından gelenlere bak
    if (typeof GLOBAL_SNIPPETS !== 'undefined') {
        const snippet = GLOBAL_SNIPPETS.find(s => s.id == id);
        if (snippet) {
            insertSnippet(snippet.code);
        }
    }
}

// --- SNIPPET MANAGEMENT JS ---
function manageSnippets(action, id = null) {
    const csrf = document.querySelector('input[name="csrf"]')?.value;
    if (action === 'add') {
        const title = document.getElementById('snip_title').value.trim();
        const code = document.getElementById('snip_code').value;
        if (!title || !code) return alert('Başlık ve Kod gereklidir!');

        const fd = new FormData();
        fd.append('snippet_action', 'add');
        fd.append('csrf', csrf);
        fd.append('title', title);
        fd.append('code', code);

        fetch('page-actions.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.ok) location.reload();
                else alert(res.error || 'Hata oluştu');
            });
    } else if (action === 'delete') {
        if (!confirm('Snippet silinecek, emin misiniz?')) return;
        const fd = new FormData();
        fd.append('snippet_action', 'delete');
        fd.append('csrf', csrf);
        fd.append('id', id);

        fetch('page-actions.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(res => {
                if (res.ok) location.reload();
            });
    }
}

// --- DYNAMİK SAYFA LİSTELEME ---
function loadPages(page = 1, search = '') {
    pageState.page = page;
    pageState.search = search;
    const body = document.getElementById('pageTableBody');
    if (!body) return;

    fetch(`page-actions.php?list_pages=1&page=${page}&search=${encodeURIComponent(search)}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                body.innerHTML = '<tr><td colspan="5" class="text-center py-4">Hata oluştu</td></tr>';
                return;
            }
            const pages = res.pages || [];
            if (pages.length === 0) {
                body.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">Sayfa bulunamadı</td></tr>';
            } else {
                body.innerHTML = pages.map(p => `
                    <tr>
                        <td class="text-center">${p.icon ? `<i class="${p.icon} text-muted"></i>` : '-'}</td>
                        <td class="fw-bold text-dark">/${escapeHtml(p.slug)}</td>
                        <td>${escapeHtml(p.title)} <br><small class="text-muted">${escapeHtml(p.description || '')}</small></td>
                        <td class="text-center">
                            ${p.is_active ? '<span class="badge-active">Aktif</span>' : '<span class="badge-passive">Pasif</span>'}
                        </td>
                        <td class="text-end" style="padding-right: 20px;">
                            <div class="btn-group">
                                ${p.is_module ? `
                                    <button class="btn btn-action btn-outline-secondary" onclick="alert('Modül sayfasıdır.')"><i class="fas fa-puzzle-piece"></i></button>
                                ` : `
                                    <button class="btn btn-action btn-outline-primary edit-page-btn" data-slug="${p.slug}"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-action btn-outline-danger delete-page-btn" data-slug="${p.slug}"><i class="fas fa-trash"></i></button>
                                `}
                            </div>
                        </td>
                    </tr>
                `).join('');

                // Event listener'ları tekrar bağla (Dinamik içerik için delegation daha iyi ama şimdilik manuel)
                bindPageActions();
            }
            renderPagePagination(res.total_pages, res.current_page);
        });
}

function renderPagePagination(totalPages, currentPage) {
    const nav = document.getElementById('pagePagination');
    if (!nav) return;
    if (totalPages <= 1) { nav.innerHTML = ''; return; }

    let html = '<ul class="pagination pagination-sm">';
    for (let i = 1; i <= totalPages; i++) {
        html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
            <a class="page-link" href="#" onclick="event.preventDefault(); loadPages(${i}, pageState.search)">${i}</a>
        </li>`;
    }
    html += '</ul>';
    nav.innerHTML = html;
}

function bindPageActions() {
    // Edit butonu
    document.querySelectorAll('.edit-page-btn').forEach(btn => {
        btn.onclick = () => openEditModal(btn.dataset.slug);
    });
    // Delete butonu
    document.querySelectorAll('.delete-page-btn').forEach(btn => {
        btn.onclick = () => {
            if (confirm('Emin misiniz?')) {
                window.location = "page-actions.php?action=delete&slug=" + btn.dataset.slug;
            }
        };
    });
}

// Edit Modal Açma Mantığı (Eski event listener'dan buraya taşındı)
function openEditModal(slug) {
    const modalEl = document.getElementById('editModal');
    let editModal = bootstrap.Modal.getOrCreateInstance(modalEl);

    fetch(`page-actions.php?get_slug=${slug}`)
        .then(r => r.json()).then(res => {
            if (!res.success) return alert(res.error || 'Hata');
            document.getElementById('old_slug').value = slug;
            document.getElementById('edit_slug').value = res.slug;
            document.getElementById('edit_title').value = res.title;
            document.getElementById('edit_description').value = res.description || '';
            document.getElementById('edit_icon').value = res.icon || '';
            document.getElementById('edit_is_active').value = res.is_active;

            const content = res.content || '';
            document.getElementById('edit_content').value = content;
            if (editEditor) {
                editEditor.setValue(content);
                setTimeout(() => editEditor.refresh(), 200);
            }

            // Image preview
            const imgPreview = document.getElementById('edit_image_preview');
            const imgContainer = document.getElementById('edit_image_preview_container');
            if (res.featured_image) {
                imgPreview.src = res.featured_image;
                imgContainer.style.display = 'block';
            } else {
                imgPreview.src = '';
                imgContainer.style.display = 'none';
            }

            const currentCss = res.css ? res.css.split(',').map(s => s.trim()) : [];
            const currentJs = res.js ? res.js.split(',').map(s => s.trim()) : [];
            const allCss = JSON.parse(document.getElementById('allCssFiles').textContent || '[]');
            const allJs = JSON.parse(document.getElementById('allJsFiles').textContent || '[]');
            renderEditAssets(allCss, currentCss, 'edit_css_list', 'assets_css[]');
            renderEditAssets(allJs, currentJs, 'edit_js_list', 'assets_js[]');

            const customCss = currentCss.filter(p => !allCss.includes(p));
            const customJs = currentJs.filter(p => !allJs.includes(p));
            document.getElementById('edit_custom_css').value = customCss.join(', ');
            document.getElementById('edit_custom_js').value = customJs.join(', ');

            editModal.show();
        });
}

document.addEventListener('DOMContentLoaded', function () {
    // 1. OLUŞTURMA
    const form = document.getElementById('sayfaOlusturFormu');
    const sonucMesajiDiv = document.getElementById('sonucMesaji'); // Değişkeni tanımladığımızdan emin olalım

    if (form) {
        form.addEventListener('submit', e => {
            e.preventDefault();

            fetch('page-actions.php', {
                method: 'POST',
                body: new FormData(form)
            })
                .then(r => r.text())
                .then(data => {
                    // Mesajı ekrana bas
                    sonucMesajiDiv.innerHTML = data;

                    // Eğer işlem başarılıysa (PHP tarafı ✅ döndürüyorsa)
                    if (data.includes('✅')) {
                        setTimeout(() => {
                            const collapseElement = document.getElementById('newSection');
                            // Bootstrap 5 için Collapse örneğini al veya oluştur
                            const bsCollapse = bootstrap.Collapse.getInstance(collapseElement) || new bootstrap.Collapse(collapseElement, { toggle: false });

                            bsCollapse.hide(); // Paneli kapat
                            form.reset();      // Formu temizle

                            // İsteğe bağlı: Sayfayı yenilemek yerine listeyi güncelleyebilirsin 
                            // ama en garantisi kısa süre sonra sayfayı yenilemektir:
                            // location.reload(); 
                        }, 2000);
                    }
                })
                .catch(err => {
                    sonucMesajiDiv.innerHTML = "Bir hata oluştu!";
                    console.error(err);
                });
        });
    }


    // --- FEATURED IMAGE PREVIEW HANDLER ---
    function bindImagePreview(inputId, previewId, containerId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        const container = document.getElementById(containerId);

        if (input && preview && container) {
            const updateImage = () => {
                let val = input.value.trim();
                if (val) {
                    // Eğer yol cdn/ veya media/ gibi yerel bir yol ise admin dışına çık
                    if (!val.startsWith('http') && !val.startsWith('../')) {
                        preview.src = '../' + val;
                    } else {
                        preview.src = val;
                    }
                    container.style.display = 'block';
                } else {
                    container.style.display = 'none';
                    preview.src = '';
                }
            };

            input.addEventListener('change', updateImage);
            input.addEventListener('input', updateImage);

            // Browser'dan seçim yapıldığında input.value değiştiği için bazen event tetiklenmez
            // Bu yüzden küçük bir interval veya MutationObserver yerine seçim anında 
            // elle tetiklemek en garantisidir ama şimdilik manuel tetikleme ekleyelim:
            if (input.value) updateImage();
        }
    }

    // Bind Create & Edit Inputs
    bindImagePreview('new_featured_image', 'new_image_preview', 'new_image_preview_container');
    bindImagePreview('edit_featured_image', 'edit_image_preview', 'edit_image_preview_container');


    // --- CODEMIRROR KONFIGURASYONU ---
    const cmConfig = {
        lineNumbers: true,
        mode: "application/x-httpd-php", // PHP + HTML Mixed
        theme: "dracula",
        lineWrapping: true,
        autoCloseTags: true,
        matchBrackets: true
    };

    // Editors already declared in global scope

    // 1. Yeni Sayfa Editörü
    if (document.getElementById('new_content')) {
        newEditor = CodeMirror.fromTextArea(document.getElementById('new_content'), cmConfig);
        newEditor.setSize("100%", 500);

        // Form gönderilmeden önce textarea'yı güncelle
        newEditor.on('change', (cm) => {
            document.getElementById('new_content').value = cm.getValue();
        });

        // Tab açıldığında refresh gerekebilir
        const tabGenel = document.querySelector('a[href="#tab-genel"]');
        if (tabGenel) {
            tabGenel.addEventListener('shown.bs.tab', () => newEditor.refresh());
        }

        // Collapse (Yeni Ekle Paneli) açıldığında refresh ŞART
        const newSection = document.getElementById('newSection');
        if (newSection) {
            newSection.addEventListener('shown.bs.collapse', () => {
                newEditor.refresh();
            });
        }
    }

    // --- SNIPPET SİSTEMİ ---
    const snippetPreview = document.getElementById('snippetPreview');

    // Event Delegation for Snippets
    document.addEventListener('click', e => {
        const btn = e.target.closest('.snippet-btn');
        if (!btn) return;

        const code = btn.dataset.code;
        insertSnippet(code);
    });

    // --- SLUG OLUŞTURUCU ---
    const titleInput = document.querySelector('input[name="title"]');
    const slugInput = document.querySelector('input[name="slug"]');

    if (titleInput && slugInput) {
        titleInput.addEventListener('input', () => {
            if (!slugInput.value || slugInput.dataset.manual !== "true") {
                slugInput.value = titleInput.value
                    .toLowerCase()
                    .replace(/[^a-z0-9 -]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-');
            }
        });
        slugInput.addEventListener('change', () => { slugInput.dataset.manual = "true"; });
    }

    // 2. EDİT MODAL AÇILIŞ (Verileri Doldurma)
    const modalEl = document.getElementById('editModal');
    let editModal = modalEl ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;

    // Editör init
    if (document.getElementById('edit_content')) {
        // Modal açılmadan önce init etme, açılınca init ederiz veya varolanı doldururuz
        // En temizi: İlk açılışta init etmek.
        modalEl.addEventListener('shown.bs.modal', function () {
            if (!editEditor) {
                editEditor = CodeMirror.fromTextArea(document.getElementById('edit_content'), cmConfig);
                editEditor.setSize("100%", 500);
                editEditor.on('change', (cm) => {
                    document.getElementById('edit_content').value = cm.getValue();
                });
            } else {
                editEditor.setValue(document.getElementById('edit_content').value);
                editEditor.refresh();
            }
        });
    }

    // Asset Listelerini Oluşturma Yardımcısı
    function renderEditAssets(allFiles, selectedFiles, listId, inputName) {
        const container = document.getElementById(listId);
        container.innerHTML = '';
        allFiles.forEach(f => {
            const checked = selectedFiles.includes(f) ? 'checked' : '';
            const html = `
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="${inputName}" value="${f}" id="edit_${listId}_${f}" ${checked}>
                    <label class="form-check-label" for="edit_${listId}_${f}">${f}</label>
                </div>`;
            container.insertAdjacentHTML('beforeend', html);
        });
    }

    /* 
        Dinamik tabloya geçildi, bu kısım loadPages içinde bindPageActions() ile yönetiliyor.
        document.querySelectorAll('.edit-btn').forEach(btn => {
            ...
        });
    */

    // --- ICON PICKER & LIVE PREVIEW SYSTEM ---
    const iconList = [
        "fas fa-home", "fas fa-user", "fas fa-cog", "fas fa-envelope", "fas fa-bell", "fas fa-search", "fas fa-bars", "fas fa-trash",
        "fas fa-edit", "fas fa-plus", "fas fa-minus", "fas fa-check", "fas fa-times", "fas fa-power-off", "fas fa-sign-out-alt",
        "fas fa-list", "fas fa-th", "fas fa-table", "fas fa-chart-bar", "fas fa-chart-pie", "fas fa-chart-line", "fas fa-file",
        "fas fa-file-alt", "fas fa-file-image", "fas fa-file-pdf", "fas fa-folder", "fas fa-folder-open", "fas fa-cloud", "fas fa-download",
        "fas fa-upload", "fas fa-image", "fas fa-camera", "fas fa-video", "fas fa-music", "fas fa-play", "fas fa-pause", "fas fa-stop",
        "fas fa-forward", "fas fa-backward", "fas fa-volume-up", "fas fa-volume-down", "fas fa-volume-mute", "fas fa-map-marker-alt",
        "fas fa-location-arrow", "fas fa-calendar", "fas fa-calendar-alt", "fas fa-clock", "fas fa-history", "fas fa-link", "fas fa-unlink",
        "fas fa-heart", "fas fa-star", "fas fa-thumbs-up", "fas fa-thumbs-down", "fas fa-comment", "fas fa-comments", "fas fa-quote-left",
        "fas fa-quote-right", "fas fa-angle-left", "fas fa-angle-right", "fas fa-angle-up", "fas fa-angle-down", "fas fa-arrow-left",
        "fas fa-arrow-right", "fas fa-arrow-up", "fas fa-arrow-down", "fas fa-chevron-left", "fas fa-chevron-right", "fas fa-chevron-up",
        "fas fa-chevron-down", "fas fa-sync", "fas fa-redo", "fas fa-undo", "fas fa-wifi", "fas fa-signal", "fas fa-battery-full",
        "fas fa-lock", "fas fa-unlock", "fas fa-key", "fas fa-shield-alt", "fas fa-exclamation-triangle", "fas fa-info-circle",
        "fas fa-question-circle", "fas fa-robot", "fas fa-rocket", "fas fa-bug", "fas fa-code", "fas fa-terminal", "fas fa-database",
        "fas fa-server", "fas fa-desktop", "fas fa-laptop", "fas fa-mobile-alt", "fas fa-tablet-alt", "fas fa-headphones"
    ];

    let currentIconInput = null;
    const iconModalEl = document.getElementById('iconPickerModal');
    const iconModal = iconModalEl ? bootstrap.Modal.getOrCreateInstance(iconModalEl) : null;
    const iconGrid = document.getElementById('iconGrid');
    const iconSearch = document.getElementById('iconSearch');

    // Live Preview Logic
    document.querySelectorAll('.check-icon').forEach(input => {
        input.addEventListener('input', () => {
            const previewId = input.id.replace('_input', '_preview');

            let pId = null;
            if (input.id === 'edit_icon') pId = 'edit_icon_preview';
            else if (input.id.includes('_input')) pId = input.id.replace('_input', '_preview');

            const previewEl = document.getElementById(pId);
            if (previewEl) {
                previewEl.className = input.value || 'fas fa-icons';
            }
        });
    });

    // Pick Button Logic
    function bindIconPickers() {
        document.querySelectorAll('.icon-picker-btn').forEach(btn => {
            // Prevent duplicate listeners
            if (btn.dataset.bound === "true") return;
            btn.dataset.bound = "true";

            btn.addEventListener('click', () => {
                if (btn.dataset.target) {
                    currentIconInput = document.querySelector(btn.dataset.target);
                } else {
                    // Table row fallback: sibling input
                    currentIconInput = btn.parentNode.querySelector('input');
                }
                renderIcons(iconList);
                if (iconSearch) iconSearch.value = '';
                if (iconModal) iconModal.show();
            });
        });
    }
    bindIconPickers(); // Init

    // Render Icons
    function renderIcons(list) {
        if (!iconGrid) return;
        iconGrid.innerHTML = '';
        list.forEach(icon => {
            const btn = document.createElement('button');
            btn.className = 'btn btn-outline-secondary m-1';
            btn.style.width = '50px';
            btn.style.height = '50px';
            btn.innerHTML = `<i class="${icon} fa-lg"></i>`;
            btn.onclick = () => {
                if (currentIconInput) {
                    currentIconInput.value = icon;
                    currentIconInput.dispatchEvent(new Event('input')); // Trigger preview
                }
                if (iconModal) iconModal.hide();
            };
            iconGrid.appendChild(btn);
        });
    }

    // Search Logic
    if (iconSearch) {
        iconSearch.addEventListener('input', (e) => {
            const val = e.target.value.toLowerCase();
            const filtered = iconList.filter(i => i.includes(val));
            renderIcons(filtered);
        });
    }
    // 3. EDİT KAYDET
    document.getElementById('saveEdit')?.addEventListener('click', () => {
        const editFormData = new FormData(document.getElementById('editForm'));
        fetch('page-actions.php', { method: 'POST', body: editFormData })
            .then(r => r.json()).then(res => {
                if (res.success) { alert('Güncellendi'); location.reload(); }
            });
    });

    /* 
    Dinamik tabloya geçildi.
    document.querySelectorAll(".delete-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            if (confirm('Emin misiniz?')) window.location = "page-actions.php?action=delete&slug=" + btn.dataset.slug;
        });
    });
    */

    // Arama Event Listener
    const pageSearchInput = document.getElementById('pageListSearch');
    if (pageSearchInput) {
        let pageDebounce;
        pageSearchInput.addEventListener('input', e => {
            clearTimeout(pageDebounce);
            pageDebounce = setTimeout(() => {
                loadPages(1, e.target.value);
            }, 500);
        });
    }

    // İlk yükleme
    if (document.getElementById('pageTableBody')) {
        loadPages();
    }

    // Menü Göster/Gizle
    const chk = document.getElementById('addToMenu');
    if (chk) {
        chk.addEventListener('change', () => {
            document.getElementById('addToMenuFields').style.display = chk.checked ? 'block' : 'none';
        });
    }
});

// --- MENU MANAGER ---
const MenuManager = {
    csrf: () => document.querySelector('input[name="csrf"]')?.value,

    add() {
        const title = document.getElementById('m_title').value.trim();
        if (!title) return alert('Başlık giriniz');

        const params = new URLSearchParams();
        params.append('menu_action', 'add');
        params.append('csrf', this.csrf());
        params.append('title', title);
        params.append('icon', document.getElementById('m_icon').value.trim());
        params.append('page_id', document.getElementById('m_page').value);
        params.append('external_url', document.getElementById('m_url').value.trim());
        params.append('order_no', document.getElementById('m_order').value);

        ['m_loc_nav', 'm_loc_footer', 'm_loc_sidebar'].forEach(id => {
            const el = document.getElementById(id);
            if (el.checked) params.append('locations[]', el.value);
        });

        fetch('page-actions.php', { method: 'POST', body: params })
            .then(r => r.json())
            .then(res => {
                if (res.ok) location.reload();
                else alert(res.error || 'Hata');
            });
    },

    del(id) {
        if (!confirm('Silmek istediğinize emin misiniz?')) return;
        const params = new URLSearchParams();
        params.append('menu_action', 'delete');
        params.append('csrf', this.csrf());
        params.append('id', id);
        fetch('page-actions.php', { method: 'POST', body: params })
            .then(r => r.json()).then(res => { if (res.ok) location.reload(); });
    },

    edit(btn) {
        const row = btn.closest('.menu-row');
        row.classList.add('table-warning');
        row.querySelectorAll('input, select, button').forEach(el => {
            el.dataset.old = el.value || '';
            el.disabled = false;
        });

        // Pickers auto-bound by global init or dataset

        const cell = btn.closest('td');
        cell.innerHTML = `
            <button class="btn btn-success btn-sm mb-1" onclick="MenuManager.save(this)"><i class="fas fa-save"></i></button>
            <button class="btn btn-secondary btn-sm" onclick="MenuManager.cancel(this)"><i class="fas fa-times"></i></button>
        `;
    },

    cancel(btn) {
        const row = btn.closest('.menu-row');
        row.classList.remove('table-warning');
        row.querySelectorAll('input, select, button').forEach(el => {
            if (el.dataset.old !== undefined) el.value = el.dataset.old;
            el.disabled = true;
        });

        const id = row.dataset.id;
        const cell = btn.closest('td');
        cell.innerHTML = `
            <button class="btn btn-outline-primary btn-sm mb-1" onclick="MenuManager.edit(this)"><i class="fas fa-edit"></i></button>
            <button class="btn btn-outline-danger btn-sm" onclick="MenuManager.del(${id})"><i class="fas fa-trash"></i></button>
        `;
    },

    save(btn) {
        const row = btn.closest('.menu-row');
        const id = row.dataset.id;
        const params = new URLSearchParams();
        params.append('menu_action', 'update');
        params.append('csrf', this.csrf());
        params.append('id', id);

        row.querySelectorAll('[data-field]').forEach(el => {
            if (el.multiple) {
                Array.from(el.selectedOptions).forEach(o => params.append(el.dataset.field + '[]', o.value));
            } else {
                params.append(el.dataset.field, el.value);
            }
        });

        fetch('page-actions.php', { method: 'POST', body: params })
            .then(r => r.json())
            .then(res => {
                if (res.ok) location.reload();
                else alert(res.error || 'Hata');
            });
    }
};