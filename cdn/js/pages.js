document.addEventListener('DOMContentLoaded', function() {
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
                    const bsCollapse = bootstrap.Collapse.getInstance(collapseElement) || new bootstrap.Collapse(collapseElement, {toggle: false});
                    
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

    // 2. EDİT MODAL AÇILIŞ (Verileri Doldurma)
    const modalEl = document.getElementById('editModal');
    let editModal = modalEl ? new bootstrap.Modal(modalEl) : null;

    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const slug = btn.dataset.slug;
            fetch(`page-actions.php?get_slug=${slug}`)
                .then(r => r.json()).then(res => {
                    if(!res.success) return;
                    document.getElementById('old_slug').value = slug;
                    document.getElementById('edit_slug').value = res.slug;
                    document.getElementById('edit_title').value = res.title;
                    document.getElementById('edit_description').value = res.description || '';
                    document.getElementById('edit_icon').value = res.icon || '';
                    document.getElementById('edit_is_active').value = res.is_active;
                    document.getElementById('edit_css').value = res.css;
                    document.getElementById('edit_js').value = res.js;
                    document.getElementById('edit_content').value = res.content;
                    editModal.show();
                });
        });
    });

    // 3. EDİT KAYDET
    document.getElementById('saveEdit')?.addEventListener('click', () => {
        const editFormData = new FormData(document.getElementById('editForm'));
        fetch('page-actions.php', { method: 'POST', body: editFormData })
            .then(r => r.json()).then(res => {
                if (res.success) { alert('Güncellendi'); location.reload(); }
            });
    });

    // 4. SİLME
    document.querySelectorAll(".delete-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            if (confirm('Emin misiniz?')) window.location = "page-actions.php?action=delete&slug=" + btn.dataset.slug;
        });
    });

    // Menü Göster/Gizle
    const chk = document.getElementById('addToMenu');
    if (chk) {
        chk.addEventListener('change', () => {
            document.getElementById('addToMenuFields').style.display = chk.checked ? 'block' : 'none';
        });
    }
});