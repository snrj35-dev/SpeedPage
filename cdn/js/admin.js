/* ============================
   ✅ GLOBAL ADMIN JS
   Tüm panellerde ortak kullanılacak
============================ */

/**
 * Global alert helper
 * @param {string} msg - Gösterilecek mesaj
 * @param {string} type - Bootstrap alert tipi (info, success, danger, warning)
 */
function showAlert(msg, type = "info") {
    const div = document.createElement('div');
    div.className = `alert alert-${type}`;
    div.textContent = msg;
    document.body.prepend(div);
    setTimeout(() => div.remove(), 3000);
}

/**
 * Global hata yakalama
 * Konsolda detaylı bilgi + ekranda kısa uyarı
 */
window.addEventListener("error", e => {
    console.warn("⚠️ JS Error:", e.message, "Kaynak:", e.filename, "Satır:", e.lineno);
    showAlert("⚠️ JS Error: " + e.message, "danger");
});

/**
 * Dil helper (lang.js ile entegre)
 * @param {string} key - Dil dosyası anahtarı
 * @param {string} fallback - Anahtar yoksa kullanılacak varsayılan metin
 */
function t(key, fallback = "") {
    return window.lang?.[key] || fallback || key;
}

/**
 * Input kontrol (XSS uyarısı)
 * Potansiyel sorunlu inputları konsolda bildirir
 */
function checkInput(str) {
    if (/<|>/.test(str)) {
        console.warn("⚠️ Potansiyel XSS girişimi:", str);
    }
    return str;
}

/* ============================
   ADMIN SIDEBAR TOGGLE
============================ */

/**
 * Mobil menüyü aç/kapat
 */
function toggleAdminMobileMenu() {
    const sidebar = document.getElementById('admSidebar');
    if (sidebar) {
        sidebar.classList.toggle('adm-mobile-open');
    }
}

/**
 * Sidebar event listeners
 */
document.addEventListener('DOMContentLoaded', function () {
    // Mobil menü butonu
    const menuBtn = document.getElementById('admMobileMenuBtn');
    if (menuBtn) {
        menuBtn.addEventListener('click', function (e) {
            e.preventDefault();
            toggleAdminMobileMenu();
        });
    }

    // Dışarı tıklandığında mobilde sidebar'ı kapat
    document.addEventListener('click', function (event) {
        const sidebar = document.getElementById('admSidebar');
        const tabBar = document.querySelector('.adm-mobile-tab-bar');

        // Eğer ekran mobilse ve tıklanan yer sidebar veya tab-bar değilse kapat
        if (window.innerWidth <= 991 && sidebar && tabBar) {
            if (!sidebar.contains(event.target) && !tabBar.contains(event.target)) {
                sidebar.classList.remove('adm-mobile-open');
            }
        }
    });
});
