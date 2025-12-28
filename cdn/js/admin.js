/* ============================
   ✅ GLOBAL ADMIN JS
   Tüm panellerde ortak kullanılacak
============================ */

/**
 * Global alert helper
 * @param {string} msg - Gösterilecek mesaj
 * @param {string} type - Bootstrap alert tipi (info, success, danger, warning)
 */
function showAlert(msg, type="info"){
    const div = document.createElement('div');
    div.className = `alert alert-${type}`;
    div.textContent = msg;
    document.body.prepend(div);
    setTimeout(()=>div.remove(), 3000);
}

/**
 * Global hata yakalama
 * Konsolda detaylı bilgi + ekranda kısa uyarı
 */
window.addEventListener("error", e=>{
    console.warn("⚠️ JS Error:", e.message, "Kaynak:", e.filename, "Satır:", e.lineno);
    showAlert("⚠️ JS Error: " + e.message, "danger");
});

/**
 * Dil helper (lang.js ile entegre)
 * @param {string} key - Dil dosyası anahtarı
 * @param {string} fallback - Anahtar yoksa kullanılacak varsayılan metin
 */
function t(key, fallback=""){
    return window.lang?.[key] || fallback || key;
}

/**
 * Input kontrol (XSS uyarısı)
 * Potansiyel sorunlu inputları konsolda bildirir
 */
function checkInput(str){
    if(/<|>/.test(str)){
        console.warn("⚠️ Potansiyel XSS girişimi:", str);
    }
    return str;
}
