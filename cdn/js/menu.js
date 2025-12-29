/* ============================
   ✅ MENÜ PANELİ (Yeni Sistem)
   menu.js
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
        params.append("csrf", CSRF_TOKEN); // Add CSRF

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
            body: new URLSearchParams({ action: "delete", id, csrf: CSRF_TOKEN })
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
            <button class="btn btn-success btn-sm" onclick="MenuEdit.save(this)">
                <i class="fas fa-save"></i>
            </button>
            <button class="btn btn-secondary btn-sm" onclick="MenuEdit.cancel(this)">
                <i class="fas fa-times"></i>
            </button>
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
        params.append("csrf", CSRF_TOKEN);

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
