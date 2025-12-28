/* ============================
   ✅ KULLANICI PANELİ
   user.js
============================ */

// Kullanıcıları listele
function loadUsers(){
    fetch('user-edit.php')
        .then(r=>r.json())
        .then(res=>{
            const body = document.getElementById('usersTableBody');
            if(!res.ok){
                body.innerHTML = '<tr><td colspan="6">'+(window.lang?.no_data||'No data')+'</td></tr>';
                return;
            }
            const users = res.users || [];
            body.innerHTML = users.map(u=>{
                return `<tr>
                    <td data-label="ID">${u.id}</td>
                    <td data-label="Kullanıcı">${escapeHtml(u.username)}</td>
                    <td data-label="Rol">${escapeHtml(u.role)}</td>
                    <td data-label="Tarih">${escapeHtml(u.created_at)}</td>
                    <td data-label="Durum">${u.is_active? (window.lang?.yes||'Yes') : (window.lang?.no||'No')}</td>
                    <td data-label="İşlem">
                        <button class="btn btn-sm btn-primary" onclick="openUserModal(${u.id})"> <i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-danger" onclick="deleteUser(${u.id})"> <i class="fas fa-trash"></i></button>
                    </td>
                </tr>`;
            }).join('') || '<tr><td colspan="6">'+(window.lang?.no_data||'No data')+'</td></tr>';
        });
}

// Kullanıcı modal aç
function openUserModal(id){
    const modalEl = document.getElementById('userModal');
    const modal = new bootstrap.Modal(modalEl);

    document.getElementById('userForm').reset();
    document.getElementById('user_id').value = '';
    document.getElementById('user_password').value = '';

    if (id){
        fetch('user-edit.php?id='+id)
            .then(r=>r.json())
            .then(res=>{
                if (res.ok && res.user){
                    const u = res.user;
                    document.getElementById('user_id').value = u.id;
                    document.getElementById('user_username').value = u.username;
                    document.getElementById('user_role').value = u.role;
                    document.getElementById('user_active').value = u.is_active ? '1' : '0';
                    document.getElementById('userModalTitle').textContent = window.lang?.edit_user || 'Edit User';
                    modal.show();
                } else {
                    alert(window.lang?.ersayfa2 || 'Not found');
                }
            });
    } else {
        document.getElementById('userModalTitle').textContent = window.lang?.add_user || 'Add User';
        modal.show();
    }
}

// Kullanıcı sil
function deleteUser(id){
    if (!confirm(window.lang?.confirm_module_delete || 'Are you sure?')) return;
    const fd = new FormData(); 
    fd.append('action','delete'); 
    fd.append('id', id);

    fetch('user-edit.php', { method: 'POST', body: fd })
        .then(r=>r.json())
        .then(res=>{
            if (res.ok){ 
                loadUsers(); 
                alert(window.lang?.module_deleted || 'Deleted'); 
            }
            else alert((window.lang?.errdata || 'Error: ')+ (res.error||''));
        });
}

// HTML escape helper
function escapeHtml(s){ 
    return String(s)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;')
        .replace(/'/g,'&#039;'); 
}

// DOM hazır olduğunda eventler
document.addEventListener('DOMContentLoaded', function(){
    const addBtn = document.getElementById('addUserBtn');
    if (addBtn) addBtn.addEventListener('click', ()=> openUserModal());

    const saveBtn = document.getElementById('saveUserBtn');
    if (saveBtn) saveBtn.addEventListener('click', ()=>{
        const form = document.getElementById('userForm');
        const fd = new FormData(form);
        const id = fd.get('id');
        const action = id ? 'update' : 'create';
        fd.append('action', action);

        fetch('user-edit.php', { method: 'POST', body: fd })
            .then(r=>r.json())
            .then(res=>{
                if (res.ok){
                    const modal = bootstrap.Modal.getInstance(document.getElementById('userModal'));
                    modal.hide();
                    loadUsers();
                    alert(window.lang?.updatelang || 'Updated');
                } else {
                    alert((window.lang?.errdata || 'Error: ') + (res.error || ''));
                }
            });
    });

    // Sayfa açıldığında kullanıcıları yükle
    const usersTableBody = document.getElementById('usersTableBody');
    if (usersTableBody) {
        loadUsers();
    }
});
