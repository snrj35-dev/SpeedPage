// browser.js
let brPath = ''; // Mevcut dizin yolu
let brOpenFile = ''; // Düzenlenen dosya yolu

// Selection Mode Object
window.browserSelectMode = {
    active: false,
    targetId: null,
    onSelect: null // Optional callback
};

/**
 * Klasör içeriğini yükler
 * @param {string} path - Yüklenecek dizin yolu
 */
function brLoad(path = '') {
    brPath = path; // Kök dizine atma sorununu çözen kritik satır
    brUpdateBreadcrumb(path);

    $.get('?page=browser&api=1', { path: path }, (files) => {
        let html = '';
        files.forEach(f => {
            const isZip = f.ext === 'zip';
            let icon = 'fa-file text-secondary';
            if (f.type === 'dir') icon = 'fa-folder text-warning';
            else {
                // Extension based icons
                const extMap = {
                    'zip': 'fa-file-zipper text-success',
                    'rar': 'fa-file-zipper text-success',
                    'png': 'fa-file-image text-danger',
                    'jpg': 'fa-file-image text-danger',
                    'jpeg': 'fa-file-image text-danger',
                    'gif': 'fa-file-image text-danger',
                    'php': 'fa-file-code text-primary',
                    'html': 'fa-file-code text-primary',
                    'js': 'fa-brands fa-js text-warning',
                    'css': 'fa-brands fa-css3-alt text-info',
                    'json': 'fa-file-code text-secondary',
                    'sql': 'fa-database text-secondary',
                    'txt': 'fa-file-lines text-secondary',
                    'pdf': 'fa-file-pdf text-danger'
                };
                if (extMap[f.ext]) icon = extMap[f.ext];
            }

            html += `
            <div class="col-6 col-md-3 col-xl-2">
                <div class="browser-card shadow-sm h-100" onclick="brAction('${f.path}', '${f.type}', '${f.ext}')" style="cursor:pointer">
                    <div class="browser-dots dropdown" onclick="event.stopPropagation()">
                        <button class="btn btn-light btn-sm" data-bs-toggle="dropdown"><i class="fa fa-ellipsis-v"></i></button>
                        <ul class="dropdown-menu shadow border-0">
                            <li><a class="dropdown-item" href="?page=browser&zip=${encodeURIComponent(f.path)}"><i class="fa fa-download me-2 text-primary"></i><span lang="br_download">İndir</span></a></li>
                            ${isZip ? `<li><a class="dropdown-item" onclick="brUnzip('${f.path}')"><i class="fa fa-file-zipper me-2 text-success"></i><span lang="br_unzip">Çıkar</span></a></li>` : ''}
                            <li><a class="dropdown-item" onclick="brRename('${f.path}','${f.name}')"><i class="fa fa-pen me-2 text-info"></i><span lang="br_rename">Ad Değiştir</span></a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" onclick="brDelete('${f.path}')"><i class="fa fa-trash me-2"></i><span lang="br_delete">Sil</span></a></li>
                        </ul>
                    </div>
                    <div class="browser-icon" style="font-size: 3.5rem;">
                        <i class="fa ${icon}"></i>
                    </div>
                    <div class="p-2 text-center text-truncate small fw-bold text-dark">${f.name}</div>
                </div>
            </div>`;
        });
        $('#br-grid').html(html || `<div class="text-center p-5 w-100 opacity-50"><span lang="br_empty">Klasör Boş</span></div>`);

        if (typeof window.lang !== 'undefined') {
            updateContent(window.lang);
        }
    });
}

function brAction(path, type, ext) {
    if (type === 'dir') {
        brLoad(path);
    } else {
        // Selection Logic
        if (window.browserSelectMode && window.browserSelectMode.active) {
            if (window.browserSelectMode.targetId) {
                const targetEl = document.getElementById(window.browserSelectMode.targetId);
                if (targetEl) {
                    // media/ klasöründen sonrasını al
                    let relativePath = path.includes('media/') ? 'media/' + path.split('media/').pop() : path;
                    targetEl.value = relativePath;
                    targetEl.dispatchEvent(new Event('change')); // Önizlemeyi tetikle
                    targetEl.dispatchEvent(new Event('input'));
                }
            }

            // #browserModal Modalını Kapat
            const modalEl = document.getElementById('browserModal');
            if (modalEl) {
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            }

            // Seçim modunu sıfırla
            window.browserSelectMode.active = false;
        } else {
            brEdit(path, ext);
        }
    }
}

// Alias
const brOpen = brAction;

function brUnzip(p) {
    if (confirm("Dosyalar bu klasöre çıkartılacak. Onaylıyor musunuz?")) {
        $.post('?page=browser&unzip=1', { path: p, csrf: CSRF_TOKEN }, (r) => {
            if (r.ok) brLoad(brPath);
            else alert("Zip açılırken bir hata oluştu.");
        });
    }
}

function brUpdateBreadcrumb(path) {
    let parts = path.split('/').filter(p => p !== '');
    let html = `<li class="breadcrumb-item"><a href="javascript:void(0)" onclick="brLoad('')">Kök</a></li>`;
    let base = '';
    parts.forEach((p, i) => {
        base += (i === 0 ? '' : '/') + p;
        html += `<li class="breadcrumb-item ${i === parts.length - 1 ? 'active' : ''}"><a href="javascript:void(0)" onclick="brLoad('${base}')">${p}</a></li>`;
    });
    $('#br-breadcrumb').html(html);
}

function brEdit(p, e) {
    brOpenFile = p;
    const editable = ['txt', 'json', 'php', 'js', 'css', 'html', 'md', 'log'];
    if (!editable.includes(e)) return;

    $.get('?page=browser&read=' + encodeURIComponent(p), r => {
        $('#br-editor-container').html(`
            <div class="p-3 bg-dark d-flex justify-content-between align-items-center rounded-top">
                <span class="text-white small">${p}</span>
                <button class="btn btn-primary btn-sm" onclick="brSave()"><i class="fa fa-save me-1"></i> Kaydet (Ctrl+S)</button>
            </div>
            <textarea id="br-editor" class="form-control bg-dark text-white border-0 p-3" style="height:500px; font-family:monospace; resize:none;">${r.content}</textarea>
        `);
        // new bootstrap.Modal('#br-modal').show(); // Original line
    });

    const modalEl = document.getElementById('br-modal');
    if (modalEl) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
}

function brSave() {
    // save=1 parametresi GET olarak gönderilirken içerik POST ile gönderilir
    $.post('?page=browser&save=1', { file: brOpenFile, content: $('#br-editor').val(), csrf: CSRF_TOKEN }, (res) => {
        alert((window.lang && window.lang.br_saved) ? window.lang.br_saved : "Kaydedildi!");
    });
}

function brDelete(p) {
    const msg = (window.lang && window.lang.br_confirm_delete) ? window.lang.br_confirm_delete : "Emin misiniz?";
    if (confirm(msg)) {
        $.post('?page=browser&delete=1', { path: p, csrf: CSRF_TOKEN }, (res) => {
            if (res && res.error) alert(res.error);
            brLoad(brPath);
        }, 'json');
    }
}

function brCreateFile() {
    let n = prompt("Dosya Adı:");
    if (n) {
        $.post('?page=browser&touch=1', { path: brPath, name: n, csrf: CSRF_TOKEN }, (res) => {
            if (res && res.ok) brLoad(brPath);
            else alert(res.error || "Dosya oluşturulamadı.");
        }, 'json');
    }
}

function brCreateFolder() {
    let n = prompt("Klasör Adı:");
    if (n) {
        $.post('?page=browser&mkdir=1', { path: brPath, name: n, csrf: CSRF_TOKEN }, (res) => {
            if (res && res.ok) brLoad(brPath);
            else alert(res.error || "Klasör oluşturulamadı.");
        }, 'json');
    }
}

function brRename(p, n) {
    let x = prompt("Yeni Ad:", n);
    if (x) $.post('?page=browser&rename=1', { old: p, new: x, csrf: CSRF_TOKEN }, () => brLoad(brPath));
}

// Ctrl+S Dinleyicisi
$(document).keydown(function (e) {
    if ((e.ctrlKey || e.metaKey) && e.which === 83 && $('#br-editor').is(':visible')) {
        e.preventDefault();
        brSave();
    }
});

// Dosya Yükleme
$('#br-upload').off('change').on('change', e => {
    let files = [...e.target.files];
    if (files.length === 0) return;

    let uploadNext = (index) => {
        if (index >= files.length) {
            brLoad(brPath);
            return;
        }

        let f = files[index];
        let fd = new FormData();
        fd.append('path', brPath);
        fd.append('f', f);
        fd.append('csrf', CSRF_TOKEN);

        $.ajax({
            url: '?page=browser&upload=1',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: (res) => {
                if (res && res.ok) {
                    uploadNext(index + 1);
                } else {
                    alert((res.error || "Yükleme hatası") + ": " + f.name);
                    uploadNext(index + 1);
                }
            },
            error: () => {
                alert("Sunucu hatası: " + f.name);
                uploadNext(index + 1);
            }
        });
    };

    uploadNext(0);
});

// --- MEDIA BROWSER HELPER ---
function openMediaBrowser(targetId, initialPath = '') {
    if (typeof window.browserSelectMode === 'undefined') {
        window.browserSelectMode = { active: false, targetId: null };
    }

    window.browserSelectMode.active = true;
    window.browserSelectMode.targetId = targetId;

    // Modalı aç
    const modalEl = document.getElementById('browserModal');
    if (modalEl) {
        let modal = bootstrap.Modal.getInstance(modalEl);
        if (!modal) {
            modal = new bootstrap.Modal(modalEl);
        }
        modal.show();
        if (typeof brLoad === 'function') {
            // Eğer bir başlangıç yolu belirtilmişse onu kullan, yoksa mevcut brPath ile devam et
            brLoad(initialPath || brPath || '');
        }
    } else {
        alert('Browser modal bulunamadı!');
    }
}

$(document).ready(() => {
    // Sadece bir kez çağrılması yeterli
    if (typeof brPath === 'undefined' || brPath === '') {
        brLoad('');
    }
});