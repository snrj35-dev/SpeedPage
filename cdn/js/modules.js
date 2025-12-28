/* ============================
   ✅ MODÜL YÜKLEME / SİLME
   modules.js
============================ */

document.addEventListener('DOMContentLoaded', function(){
    // Modül yükleme formu
    const uploadForm = document.getElementById('uploadModuleForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e){
            e.preventDefault();
            const fd = new FormData(uploadForm);

            fetch('modul-func.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                alert(window.lang?.[res.message_key] || res.message || window.lang?.updatelang || res.status);
                if (res.status === 'success') location.reload();
            });
        });
    }

    // Modül toggle / silme işlemleri
    document.body.addEventListener('click', function(e){
        const toggleBtn = e.target.closest('.toggle-module-btn');
        if (toggleBtn) {
            const id = toggleBtn.dataset.id;
            const confirmMsg = toggleBtn.dataset.active === '1'
                ? (window.lang?.confirm_disable_module || 'Disable module?')
                : (window.lang?.confirm_enable_module || 'Enable module?');

            if (!confirm(confirmMsg)) return;

            fetch('modul-func.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'toggle', id })
            })
            .then(r => r.json())
            .then(res => {
                alert(window.lang?.[res.message_key] || res.message || window.lang?.updatelang || res.status);
                if (res.status === 'success') {
                    const tr = toggleBtn.closest('tr');
                    const statusCell = tr.querySelector('.module-status');
                    if (statusCell) {
                        statusCell.innerHTML = res.is_active
                            ? '<span lang="active"></span>'
                            : '<span lang="passive"></span>';
                    }
                    toggleBtn.dataset.active = res.is_active ? '1' : '0';
                    toggleBtn.textContent = res.is_active
                        ? (window.lang?.disable_module || 'Disable')
                        : (window.lang?.enable_module || 'Enable');
                }
            });

            return;
        }

        const btn = e.target.closest('.delete-module-btn');
        if (!btn) return;

        if (!confirm(window.lang?.confirm_module_delete || 'Are you sure?')) return;

        fetch('modul-func.php', {
            method: 'POST',
            body: new URLSearchParams({ action: 'delete', id: btn.dataset.id })
        })
        .then(r => r.json())
        .then(res => {
            alert(window.lang?.[res.message_key] || res.message || window.lang?.updatelang || res.status);
            location.reload();
        });
    });
});
