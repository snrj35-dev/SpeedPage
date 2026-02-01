/* ============================
   ✅ TEMA YÜKLEME / SİLME / AKTİFLEŞTİRME
   themes.js
============================ */

function uploadTheme(e) {
    e.preventDefault();
    let formData = new FormData(document.getElementById('uploadThemeForm'));
    formData.append('action', 'upload');
    formData.append('csrf', CSRF_TOKEN);

    fetch('theme-func.php', {
        method: 'POST',
        body: formData
    })
        .then(r => r.json())
        .then(res => {
            alert(window.lang?.[res.message_key] || res.message || window.lang?.updatelang || res.status);
            if (res.status === 'success') location.reload();
        })
        .catch(err => alert("Upload error"));
}

function copyTheme(source) {
    let modalElement = document.getElementById('themeCopyModal');
    if (modalElement) {
        let modal = new bootstrap.Modal(modalElement);
        modal.show();
    }
}

function confirmCopyTheme() {
    let newName = document.getElementById('newThemeSlug').value;
    let newTitle = document.getElementById('newThemeTitle').value;

    if (!newName) return alert(window.lang?.enter_theme_slug || "Enter theme slug");

    let formData = new FormData();
    formData.append('action', 'duplicate_theme');
    formData.append('source', 'default');
    formData.append('new_name', newName);
    formData.append('new_title', newTitle);
    formData.append('csrf', CSRF_TOKEN);

    fetch('theme-func.php', {
        method: 'POST',
        body: formData
    })
        .then(r => r.json())
        .then(res => {
            alert(window.lang?.[res.message_key] || res.message || window.lang?.updatelang || res.status);
            if (res.status === 'success') location.reload();
        });
}

function deleteTheme(themeName) {
    if (!confirm(themeName + " " + (window.lang?.confirm_theme_delete_title || "Delete theme?"))) return;

    let formData = new FormData();
    formData.append('action', 'delete_theme');
    formData.append('theme_name', themeName);
    formData.append('csrf', CSRF_TOKEN);

    fetch('theme-func.php', {
        method: 'POST',
        body: formData
    })
        .then(r => r.json())
        .then(res => {
            alert(window.lang?.[res.message_key] || res.message || window.lang?.updatelang || res.status);
            if (res.status === 'success') location.reload();
        });
}

function activateTheme(themeName) {
    if (!confirm(window.lang?.confirm_theme_activate || "Activate theme?")) return;

    let formData = new FormData();
    formData.append('action', 'activate_theme');
    formData.append('theme_name', themeName);
    formData.append('csrf', CSRF_TOKEN);

    fetch('theme-func.php', {
        method: 'POST',
        body: formData
    })
        .then(r => r.json())
        .then(res => {
            alert(window.lang?.[res.message_key] || res.message || window.lang?.updatelang || res.status);
            if (res.status === 'success') location.reload();
        });
}
