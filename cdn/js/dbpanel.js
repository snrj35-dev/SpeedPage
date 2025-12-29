/* ============================
   ✅ DB PANELİ (veripanel)
   dbpanel.js
============================ */

let tableName = "", columns = [];

// Tabloları yükle
function loadTables() {
    $.getJSON("verislem.php?action=tables", t => {
        const items = t.map(x => `<li class="list-group-item list-group-item-action" onclick="selectAndClose('${x}')">${x}</li>`).join('');
        document.querySelectorAll('.tables-list').forEach(el => el.innerHTML = items);
    });
}

// Mobilde tablo seçince listeyi kapat
function selectAndClose(tableName) {
    loadTable(tableName);
    const collapseEl = document.getElementById('tablesCollapse');
    if (collapseEl && window.getComputedStyle(collapseEl).display !== 'none') {
        const bsCollapse = bootstrap.Collapse.getInstance(collapseEl) || new bootstrap.Collapse(collapseEl);
        bsCollapse.hide();
    }
}

// Tabloyu yükle
function loadTable(t) {
    tableName = t;
    $("#title").text(t);

    $.getJSON("verislem.php?action=columns&table=" + t, c => columns = c);

    $.getJSON("verislem.php?action=rows&table=" + t, rows => {
        if (!rows.length) { $("#content").html(window.lang?.no_data || 'No data'); return; }

        let th = Object.keys(rows[0]).map(k => `<th>${k}</th>`).join("");

        let tr = rows.map(r => {
            let td = Object.entries(r).map(([k, v]) => {
                if (k === "id") return `<td>${v}</td>`;
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

// Yeni kayıt formu
function addForm() {
    if (!tableName) {
        alert(window.lang?.select_table_first || 'Please select a table first');
        return;
    }

    if (!columns.length) {
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

// Kayıt ekle
function insert(btn) {
    let data = {};
    $(btn).parent().find("input").each(function () { data[this.name] = this.value });

    fetch("verislem.php?action=insert", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ table: tableName, data: data, csrf: CSRF_TOKEN })
    }).then(() => loadTable(tableName));
}

// Kayıt güncelle
function update(id, col, val) {
    $.post("verislem.php?action=update", { table: tableName, id, col, val, csrf: CSRF_TOKEN });
}

// Kayıt sil
function del(id) {
    if (confirm(window.lang?.confirm_delete_generic || 'Delete?'))
        $.post("verislem.php?action=delete", { table: tableName, id, csrf: CSRF_TOKEN }, () => loadTable(tableName));
}

// SQL çalıştır
function runSQL() {
    let q = $("#sql").val().trim();
    if (!q) {
        alert(window.lang?.sql_empty || 'SQL is empty');
        return;
    }

    $.post("verislem.php?action=sql", { sql: q, csrf: CSRF_TOKEN }, res => {
        $("#sqlResult").text(JSON.stringify(res, null, 2));
    }, "json");
}

// SQL export
function exportSQL() {
    $.getJSON("verislem.php?action=export_sql", res => {
        $("#sqlResult").text(res.sql);
    });
}

// SQL import
function importSQL() {
    let sql = $("#importSql").val().trim();
    if (!sql) {
        alert(window.lang?.sql_empty || 'SQL is empty');
        return;
    }

    if (!confirm(window.lang?.import_confirm || 'This operation may MODIFY the database. Are you sure?')) {
        return;
    }

    fetch("verislem.php?action=import_sql", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ sql: sql, csrf: CSRF_TOKEN })
    })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                alert(window.lang?.import_complete || 'Import completed');
                loadTables();
            } else {
                alert((window.lang?.errdata || 'Error: ') + res.error);
            }
        });
}

// SQL dosya indir
function downloadSQLFile() {
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
        .catch(err => alert((window.lang?.errdata || 'Error: ') + err));
}

// SQL dosya yükle
function uploadSqlFile(form) {
    const f = form.querySelector('input[name="sql_file"]');
    if (!f || !f.files || !f.files[0]) {
        alert(window.lang?.select_sql_file || 'Please select a .sql file');
        return false;
    }
    if (!confirm(window.lang?.confirm_upload_db || 'This upload will MODIFY the database. Continue?')) return false;

    const fd = new FormData(form);

    fetch('verislem.php?action=import_file', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                alert(window.lang?.upload_complete || 'Upload completed');
                loadTables();
            }
            else alert((window.lang?.errdata || 'Error: ') + (res.error || ''));
        })
        .catch(err => alert((window.lang?.errdata || 'Error: ') + err));

    return false; // prevent default form submit
}

// İlk yükleme
document.addEventListener('DOMContentLoaded', loadTables);
