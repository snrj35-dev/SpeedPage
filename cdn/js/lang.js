const LANG_KEY = 'user_language';

// BASE_PATH PHP tarafından global tanımlı olabilir; değilse fallback kullan
let BASE_JSON_PATH;
if (typeof BASE_PATH !== 'undefined') { 
    BASE_JSON_PATH = BASE_PATH + 'cdn/lang/'; 
}    
else { 
    BASE_JSON_PATH = BASE_URL + 'cdn/lang/'; 
}

// Path normalize
if (!BASE_JSON_PATH.endsWith('/')) {
    BASE_JSON_PATH += '/';
}

// Varsayılan dil
let currentLang = localStorage.getItem(LANG_KEY) || 'tr';

// Dil seçici
const langSelect = document.getElementById('lang-select');

// JSON çeviri dosyasını yükle
async function loadTranslation(lang) {
    try {
        const response = await fetch(`${BASE_JSON_PATH}${lang}.json`);

        if (!response.ok) {
            throw new Error(`Çeviri dosyası (${lang}.json) yüklenemedi`);
        }

        return await response.json();
    } catch (error) {
        console.error("Çeviri yükleme hatası:", error);
        return {};
    }
}

// HTML içeriğini güncelle
function updateContent(translations) {

    // ✅ SPA uyumu: her çağrıda yeniden seç
    document.querySelectorAll('[lang]').forEach(element => {
        const key = element.getAttribute('lang');
        if (translations[key]) {
            element.textContent = translations[key];
        }
    });

    // Placeholder desteği: data-placeholder kullanılarak input/textarea'lara çevrilmiş placeholder verilir
    document.querySelectorAll('[data-placeholder]').forEach(element => {
        const key = element.getAttribute('data-placeholder');
        if (translations[key]) {
            element.setAttribute('placeholder', translations[key]);
        }
    });

    // Eğer option gibi öğeler için lang attribute kullanıldıysa onların içeriğini de güncelle
    document.querySelectorAll('option[lang]').forEach(element => {
        const key = element.getAttribute('lang');
        if (translations[key]) {
            element.textContent = translations[key];
        }
    });

    document.documentElement.lang = currentLang;
}

// Dil değiştir
async function setLanguage(lang) {
    currentLang = lang;
    localStorage.setItem(LANG_KEY, lang);

    const translations = await loadTranslation(lang);

    // ✅ global değişken
    window.lang = translations;

    updateContent(translations);

    if (langSelect) {
        langSelect.value = lang;
    }
}

// Dil seçici
if (langSelect) {
    langSelect.addEventListener('change', e => {
        setLanguage(e.target.value);
    });
}

// İlk yükleme
setLanguage(currentLang);

