const content = document.getElementById("page-content");
const loader = document.getElementById("page-loader");
// Dinamik URL oluşturucu (Ayara göre link formatını belirler)
function getUrl(page) {
    if (typeof FRIENDLY_URL !== 'undefined' && FRIENDLY_URL === "1") {
        // Eğer panelden Friendly URL aktifse: /new/home
        return BASE_PATH + page;
    } else {
        // Kapalıysa veya .htaccess yoksa: /new/?page=home
        return BASE_PATH + "?page=" + page;
    }
}
function loadPage(page = "home", push = true) {
    loader.classList.remove("d-none");

    fetch(`page.php?page=${page}`)
        .then(res => res.json())
        .then(data => {
            content.innerHTML = data.html;
            injectAssets(data.assets);
            loader.classList.add("d-none");

            if (push) {
                history.pushState({ page }, "", getUrl(page));
            }
        })
        .catch(() => {
            content.innerHTML = `<div class="alert alert-danger">Sayfa yüklenemedi</div>`;
            loader.classList.add("d-none");
        });
}
window.addEventListener("DOMContentLoaded", () => {
    const currentPath = window.location.pathname;
    const urlParams = new URLSearchParams(window.location.search);
    let initialPage = "home";

    // 1. ÖNCE: URL'de ?page= var mı bak (Bu her zaman öncelikli olmalı)
    if (urlParams.has("page")) {
        initialPage = urlParams.get("page");
    }
    // 2. SONRA: Eğer Friendly URL aktifse path'den yakala
    else if (typeof FRIENDLY_URL !== 'undefined' && FRIENDLY_URL === "1") {
        const cleanPath = currentPath.replace(BASE_PATH, "").replace(/^\/+|\/+$/g, "");
        // index.php dosyasını veya boş yolu ele
        if (cleanPath !== "" && cleanPath !== "index.php") {
            initialPage = cleanPath;
        }
    }

    // Yakalanan sayfayı yükle
    loadPage(initialPage, false);
});
window.addEventListener("popstate", e => {
    if (e.state?.page) loadPage(e.state.page, false);
});

document.addEventListener("click", e => {
    const link = e.target.closest("[data-page]");
    if (!link) return;

    e.preventDefault();
    const targetPage = link.dataset.page;
    loadPage(link.dataset.page);
});


let loadedJS = new Set();
let loadedCSS = new Set();

function injectAssets(assets) {

    // ✅ CSS dosyaları
    (assets.css || []).forEach(css => {
        let finalPath = (css.includes('modules/') || css.includes('themes/') || css.includes('http'))
            ? css
            : 'cdn/css/' + css;

        if (!finalPath.startsWith("http") && !finalPath.startsWith("/")) {
            finalPath = BASE_PATH + finalPath;
        }

        if (loadedCSS.has(finalPath)) return;
        loadedCSS.add(finalPath);

        const link = document.createElement("link");
        link.rel = "stylesheet";
        link.href = finalPath;
        document.head.appendChild(link);
    });

    // ✅ JS dosyaları
    (assets.js || []).forEach(js => {
        let finalPath = (js.includes('modules/') || js.includes('themes/') || js.includes('http'))
            ? js
            : 'cdn/js/' + js;

        if (!finalPath.startsWith("http") && !finalPath.startsWith("/")) {
            finalPath = BASE_PATH + finalPath;
        }

        if (loadedJS.has(finalPath)) return;
        loadedJS.add(finalPath);

        const script = document.createElement("script");
        script.src = finalPath;
        script.defer = true;
        document.body.appendChild(script);
    });
}


