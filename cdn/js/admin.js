/* ============================
   ✅ SAYFA OLUŞTURMA FORMU
============================ */
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('sayfaOlusturFormu');
    const sonucMesajiDiv = document.getElementById('sonucMesaji');

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(r => r.text())
            .then(data => {
                sonucMesajiDiv.innerHTML = data;
            })
            .catch(() => {
                sonucMesajiDiv.innerHTML = '<span class="warning" lang="sunucuerror"></span>';
            });
        });
    }
});

/* ============================
   ✅ MENÜ EKLEME ALANI GÖSTER/GİZLE
============================ */
document.addEventListener('DOMContentLoaded', function(){
    const chk = document.getElementById('addToMenu');
    const fields = document.getElementById('addToMenuFields');
    if(!chk || !fields) return;

    chk.addEventListener('change', ()=>{
        fields.style.display = chk.checked ? 'block' : 'none';
    });
});

/* ============================
   ✅ SON AKTİF TAB'I HATIRLA
============================ */
document.addEventListener('DOMContentLoaded', function(){
    const tabEl = document.getElementById('adminTabs');
    if(!tabEl) return;

    const last = localStorage.getItem('admin_last_tab');
    if(last){
        const btn = tabEl.querySelector(`[data-bs-target="${last}"]`);
        if(btn) btn.click();
    }

    tabEl.querySelectorAll('button[data-bs-toggle="tab"]').forEach(b=>{
        b.addEventListener('shown.bs.tab', ev=>{
            localStorage.setItem('admin_last_tab', ev.target.getAttribute('data-bs-target'));
        });
    });
});

/* ============================
   ✅ SAYFA DÜZENLEME MODALI
============================ */
const modal = new bootstrap.Modal(document.getElementById('editModal'));

document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const slug = btn.dataset.slug;

        fetch('page-edit-ajax.php?slug=' + slug)
            .then(r => r.json())
            .then(data => {
                document.getElementById('old_slug').value = slug;
                document.getElementById('edit_slug').value = data.slug;
                document.getElementById('edit_title').value = data.title;
                document.getElementById('edit_css').value = data.css.join(', ');
                document.getElementById('edit_js').value = data.js.join(', ');
                document.getElementById('edit_content').value = data.content;
                document.getElementById('edit_description').value = data.description || '';
                document.getElementById('edit_icon').value = data.icon || '';
                document.getElementById('edit_is_active').value = data.is_active ? '1' : '0';

                modal.show();
            });
    });
});

/* ============================
   ✅ SAYFA KAYDET
============================ */
document.getElementById('saveEdit').addEventListener('click', () => {
    const form = new FormData(document.getElementById('editForm'));

    fetch('page-edit-ajax.php', {
        method: 'POST',
        body: form
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert('✔️ ' + lang.updatelang);
            location.reload();
        } else {
            alert(res.error);
        }
    });
});

/* ============================
   ✅ SAYFA SİL
============================ */
document.querySelectorAll(".delete-btn").forEach(btn => {
    btn.addEventListener("click", () => {
        if (!confirm(lang.pagedel)) return;
        window.location = "page-delete.php?slug=" + btn.dataset.slug;
    });
});

/* ============================
   ✅ DB PANELİ (veripanel)
============================ */
let tableName="", columns=[];

function loadTables(){
    $.getJSON("verislem.php?action=tables", t=>{
        const items = t.map(x=>`<li class="list-group-item list-group-item-action" onclick="selectAndClose('${x}')">${x}</li>`).join('');
        document.querySelectorAll('.tables-list').forEach(el => el.innerHTML = items);
    });
}

// Mobilde tablo seçince listeyi kapatması için yardımcı fonksiyon
function selectAndClose(tableName) {
    loadTable(tableName);
    // Mobildeki collapse menüyü kapat
    const collapseEl = document.getElementById('tablesCollapse');
    if (collapseEl && window.getComputedStyle(collapseEl).display !== 'none') {
        const bsCollapse = bootstrap.Collapse.getInstance(collapseEl) || new bootstrap.Collapse(collapseEl);
        bsCollapse.hide();
    }
}

function loadTable(t){
    tableName=t;
    $("#title").text(t);

    $.getJSON("verislem.php?action=columns&table="+t, c=>columns=c);

    $.getJSON("verislem.php?action=rows&table="+t, rows=>{
        if(!rows.length){$("#content").html(window.lang?.no_data || 'No data');return;}

        let th = Object.keys(rows[0]).map(k=>`<th>${k}</th>`).join("");

        let tr = rows.map(r=>{
            let td = Object.entries(r).map(([k,v])=>{
                if(k==="id") return `<td>${v}</td>`;
                return `<td contenteditable onblur="update(${r.id},'${k}',this.innerText)">${v}</td>`;
            }).join("");

            return `<tr>${td}<td>
                <button class="btn btn-danger btn-sm" onclick="del(${r.id})">
                <i class="fa fa-trash"></i></button>
            </td></tr>`;
        }).join("");

        $("#content").html(`
            <table class="table table-bordered table-sm">
                <thead><tr>${th}<th>${window.lang?.tabislem || 'Actions'}</th></tr></thead>
                <tbody>${tr}</tbody>
            </table>
        `);
    });
}

function addForm(){
    if(!tableName){
        alert(window.lang?.select_table_first || 'Please select a table first');
        return;
    }

    if(!columns.length){
        alert(window.lang?.columns_missing || 'Could not fetch table columns');
        return;
    }

    let f = columns
        .filter(c => c.pk == 0)
        .map(c => `<input class="form-control mb-1" name="${c.name}" placeholder="${c.name}">`)
        .join("");

    $("#content").prepend(`
        <div class="card p-2 mb-2">
            <b>${window.lang?.add_record || 'New Record'}</b>
            ${f}
            <button class="btn btn-success btn-sm mt-2" onclick="insert(this)">${window.lang?.save_changes || 'Save'}</button>
        </div>
    `);
}

function insert(btn){
    let data={};
    $(btn).parent().find("input").each(function(){data[this.name]=this.value});

    fetch("verislem.php?action=insert",{
        method:"POST",
        headers:{"Content-Type":"application/json"},
        body:JSON.stringify({table:tableName,data:data})
    }).then(()=>loadTable(tableName));
}

function update(id,col,val){
    $.post("verislem.php?action=update",{table:tableName,id,col,val});
}

function del(id){
    if(confirm(window.lang?.confirm_delete_generic || 'Delete?'))
        $.post("verislem.php?action=delete",{table:tableName,id},()=>loadTable(tableName));
}

function runSQL(){
    let q = $("#sql").val().trim();
    if(!q){
        alert(window.lang?.sql_empty || 'SQL is empty');
        return;
    }

    $.post("verislem.php?action=sql",{sql:q},res=>{
        $("#sqlResult").text(JSON.stringify(res,null,2));
    },"json");
}

function exportSQL(){
    $.getJSON("verislem.php?action=export_sql",res=>{
        $("#sqlResult").text(res.sql);
    });
}

function importSQL(){
    let sql = $("#importSql").val().trim();
    if(!sql){
        alert(window.lang?.sql_empty || 'SQL is empty');
        return;
    }

    if(!confirm(window.lang?.import_confirm || 'This operation may MODIFY the database. Are you sure?')){
        return;
    }

    fetch("verislem.php?action=import_sql",{
        method:"POST",
        headers:{"Content-Type":"application/json"},
        body:JSON.stringify({sql:sql})
    })
    .then(r=>r.json())
        .then(res=>{
        if(res.ok){
            alert(window.lang?.import_complete || 'Import completed');
            loadTables();
        }else{
            alert((window.lang?.errdata || 'Error: ') + res.error);
        }
    });
}

function downloadSQLFile(){
    fetch('verislem.php?action=export_sql_file')
        .then(r => r.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'backup.sql';
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(url);
        })
        .catch(err => alert((window.lang?.errdata||'Error: ') + err));
}

function uploadSqlFile(form){
    const f = form.querySelector('input[name="sql_file"]');
    if(!f || !f.files || !f.files[0]){ alert('Lütfen bir .sql dosyası seçin'); return false; }
    if (!confirm('Yükleme veritabanını değiştirecektir. Devam edilsin mi?')) return false;

    const fd = new FormData(form);

    fetch('verislem.php?action=import_file', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) { alert('Yükleme tamamlandı'); loadTables(); }
            else alert((window.lang?.errdata||'Error: ') + (res.error||''));
        })
        .catch(err => alert((window.lang?.errdata||'Error: ') + err));

    return false; // prevent default form submit
}

loadTables();
// Load users when users tab activated or on demand
function loadUsers(){
    fetch('user-edit.php')
        .then(r=>r.json())
        .then(res=>{
            const body = document.getElementById('usersTableBody');
            if(!res.ok){ body.innerHTML = '<tr><td colspan="6">'+(window.lang?.no_data||'No data')+'</td></tr>'; return; }
            const users = res.users || [];
            body.innerHTML = users.map(u=>{
                return `<tr>
                    <td data-label="ID">${u.id}</td>
                    <td data-label="Kullanıcı">${escapeHtml(u.username)}</td>
                    <td data-label="Rol">${escapeHtml(u.role)}</td>
                    <td data-label="Tarih">${escapeHtml(u.created_at)}</td>
                    <td data-label="Durum">${u.is_active? (window.lang?.yes||'Yes') : (window.lang?.no||'No')}</td>
                    <td data-label="İşlem">
                        <button class="btn btn-sm btn-primary" onclick="openUserModal(${u.id})">✏️</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteUser(${u.id})">🗑</button>
                    </td>
                </tr>`;
            }).join('') || '<tr><td colspan="6">'+(window.lang?.no_data||'No data')+'</td></tr>';
        });
}

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

    // load users initially if users tab exists
    const usersTabBtn = document.querySelector('[data-bs-target="#users"]');
    if (usersTabBtn){
        usersTabBtn.addEventListener('shown.bs.tab', ()=> loadUsers());
    }
});

function deleteUser(id){
    if (!confirm(window.lang?.confirm_module_delete || 'Are you sure?')) return;
    const fd = new FormData(); fd.append('action','delete'); fd.append('id', id);
    fetch('user-edit.php', { method: 'POST', body: fd })
        .then(r=>r.json())
        .then(res=>{
            if (res.ok){ loadUsers(); alert(window.lang?.module_deleted || 'Deleted'); }
            else alert((window.lang?.errdata || 'Error: ')+ (res.error||''));
        });
}

function escapeHtml(s){ return String(s)
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;')
    .replace(/'/g,'&#039;'); }
/* ============================
   ✅ MENÜ PANELİ (Yeni Sistem)
============================ */
const MenuEdit = {

    add() {
        const params = new URLSearchParams();

        params.append("action", "add");
        params.append("title", m_title.value.trim());
        params.append("icon", m_icon.value.trim());
        params.append("page_id", m_page.value || "");
        params.append("external_url", m_url.value.trim());
        params.append("order_no", m_order.value);

        // Çoklu konum
        Array.from(document.getElementById('m_locations').selectedOptions)
            .forEach(o => params.append("locations[]", o.value));

        fetch("menu-panel.php", {
            method: "POST",
            body: params
        }).then(() => location.reload());
    },

    del(id) {
        if (!confirm(window.lang?.confirm_delete_generic || 'Delete?')) return;

        fetch("menu-panel.php", {
            method: "POST",
            body: new URLSearchParams({ action: "delete", id })
        }).then(() => location.reload());
    },

    edit(btn) {
        const row = btn.closest('.menu-row');
        row.classList.add('editing');

        row.querySelectorAll('[data-field]').forEach(i => {
            i.dataset.old = i.value;
            i.disabled = false;
        });

        btn.outerHTML = `
            <button class="btn btn-success btn-sm" onclick="MenuEdit.save(this)">💾</button>
            <button class="btn btn-secondary btn-sm" onclick="MenuEdit.cancel(this)">❌</button>
        `;
    },

    cancel(btn) {
        const row = btn.closest('.menu-row');

        row.querySelectorAll('[data-field]').forEach(i => {
            i.value = i.dataset.old;
            i.disabled = true;
        });

        row.classList.remove('editing');
    },

    save(btn) {
        const row = btn.closest('.menu-row');
        const id = row.dataset.id;

        const params = new URLSearchParams();
        params.append("action", "update");
        params.append("id", id);

        // Tüm alanları POST et
        row.querySelectorAll('[data-field]').forEach(i => {
            params.append(i.dataset.field, i.value);
            i.disabled = true;
        });

        // Çoklu konum
        const locSelect = row.querySelector('[data-field="locations"]');
        if (locSelect) {
            Array.from(locSelect.selectedOptions)
                .forEach(o => params.append("locations[]", o.value));
        }

        fetch("menu-panel.php", {
            method: "POST",
            body: params
        })
        .then(r => r.json())
        .then(res => {
            if (res.ok) location.reload();
            else alert((window.lang?.errdata || 'Error: ') + res.error);
        });
    }
};

/* ============================
   ✅ MODÜL YÜKLEME / SİLME
============================ */
document.addEventListener('DOMContentLoaded', function(){
    const uploadForm = document.getElementById('uploadModuleForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e){
            e.preventDefault();
            const fd = new FormData(uploadForm);

            fetch('modul-func.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                alert(res.message);
                if (res.status === 'success') location.reload();
            });
        });
    }

    document.body.addEventListener('click', function(e){
        const toggleBtn = e.target.closest('.toggle-module-btn');
        if (toggleBtn) {
            const id = toggleBtn.dataset.id;
            if (!confirm(toggleBtn.dataset.active === '1' ? 'Bu modülü devre dışı bırakmak istiyor musunuz?' : 'Bu modülü etkinleştirmek istiyor musunuz?')) return;

            fetch('modul-func.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'toggle', id })
            })
            .then(r => r.json())
            .then(res => {
                alert(res.message);
                if (res.status === 'success') {
                    const tr = toggleBtn.closest('tr');
                    const statusCell = tr.querySelector('.module-status');
                    if (statusCell) statusCell.innerHTML = res.is_active ? '<span lang="active"></span>' : '<span lang="passive"></span>';
                    toggleBtn.dataset.active = res.is_active ? '1' : '0';
                    toggleBtn.textContent = res.is_active ? 'Devre Dışı Bırak' : 'Etkinleştir';
                }
            });

            return;
        }

        const btn = e.target.closest('.delete-module-btn');
        if (!btn) return;

        if (!confirm('Bu modülü silmek istediğine emin misin?')) return;

        fetch('modul-func.php', {
            method: 'POST',
            body: new URLSearchParams({ action: 'delete', id: btn.dataset.id })
        })
        .then(r => r.json())
        .then(res => {
            alert(res.message);
            location.reload();
        });
    });
});

