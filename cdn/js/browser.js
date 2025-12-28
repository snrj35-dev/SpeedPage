// browser.js
let brPath = ''; // Mevcut dizin yolu
let brOpenFile = ''; // Düzenlenen dosya yolu

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
            const icon = f.type === 'dir' ? 'fa-folder text-warning' : 'fa-file-alt text-muted';
            
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
                    <div class="browser-icon"><i class="fa ${icon}"></i></div>
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
    if (type === 'dir') brLoad(path);
    else brEdit(path, ext);
}

function brUnzip(p) {
    if(confirm("Dosyalar bu klasöre çıkartılacak. Onaylıyor musunuz?")) {
        $.post('?page=browser&unzip=1', { path: p }, (r) => {
            if(r.ok) brLoad(brPath);
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
        html += `<li class="breadcrumb-item ${i===parts.length-1?'active':''}"><a href="javascript:void(0)" onclick="brLoad('${base}')">${p}</a></li>`;
    });
    $('#br-breadcrumb').html(html);
}

function brEdit(p, e) {
    brOpenFile = p;
    const editable = ['txt','json','php','js','css','html','md','log'];
    if (!editable.includes(e)) return;

    $.get('?page=browser&read=' + encodeURIComponent(p), r => {
        $('#br-editor-container').html(`
            <div class="p-3 bg-dark d-flex justify-content-between align-items-center rounded-top">
                <span class="text-white small">${p}</span>
                <button class="btn btn-primary btn-sm" onclick="brSave()"><i class="fa fa-save me-1"></i> Kaydet (Ctrl+S)</button>
            </div>
            <textarea id="br-editor" class="form-control bg-dark text-white border-0 p-3" style="height:500px; font-family:monospace; resize:none;">${r.content}</textarea>
        `);
        new bootstrap.Modal('#br-modal').show();
    });
}

function brSave() {
    // save=1 parametresi GET olarak gönderilirken içerik POST ile gönderilir
    $.post('?page=browser&save=1', { file: brOpenFile, content: $('#br-editor').val() }, (res) => {
        alert((window.lang && window.lang.br_saved) ? window.lang.br_saved : "Kaydedildi!");
    });
}

function brDelete(p) { 
    const msg = (window.lang && window.lang.br_confirm_delete) ? window.lang.br_confirm_delete : "Emin misiniz?";
    if(confirm(msg)) $.get('?page=browser&delete='+encodeURIComponent(p), () => brLoad(brPath)); 
}

function brCreateFile() { 
    let n = prompt("Dosya Adı:"); 
    if(n) $.post('?page=browser&touch=1', {path:brPath, name:n}, () => brLoad(brPath)); 
}

function brCreateFolder() { 
    let n = prompt("Klasör Adı:"); 
    if(n) $.post('?page=browser&mkdir=1', {path:brPath, name:n}, () => brLoad(brPath)); 
}

function brRename(p, n) { 
    let x = prompt("Yeni Ad:", n); 
    if(x) $.post('?page=browser&rename=1', {old:p, new:x}, () => brLoad(brPath)); 
}

// Ctrl+S Dinleyicisi
$(document).keydown(function(e) {
    if ((e.ctrlKey || e.metaKey) && e.which === 83 && $('#br-editor').is(':visible')) {
        e.preventDefault(); 
        brSave();
    }
});

// Dosya Yükleme
$('#br-upload').change(e => {
    let fd = new FormData();
    [...e.target.files].forEach(f => {
        fd.append('path', brPath); 
        fd.append('f', f);
        $.ajax({ 
            url:'?page=browser&upload=1', 
            method:'POST', 
            data:fd, 
            processData:false, 
            contentType:false, 
            success:() => brLoad(brPath) 
        });
    });
});

$(document).ready(() => brLoad(''));