const content = document.getElementById("page-content");
const loader = document.getElementById("page-loader");

// Dinamik URL oluşturucu (Ayara göre link formatını belirler)
function getUrl(page) {
    if (typeof FRIENDLY_URL !== 'undefined' && FRIENDLY_URL === "1") {
        return BASE_PATH + page;
    } else {
        return BASE_PATH + "?page=" + page;
    }
}

function loadPage(page = "home", push = true) {
    if (!loader || !content) return; // Router pasifse işlem yapma

    loader.classList.remove("d-none");

    const [cleanSlug, hash] = page.split('#');

    fetch(`page.php?page=${cleanSlug}`)
        .then(res => res.json())
        .then(data => {
            content.innerHTML = data.html;

            // ✅ SPA Language Sync: Merge and update translations
            if (data.translations && typeof window.lang !== 'undefined') {
                window.lang = { ...window.lang, ...data.translations };
                if (typeof updateContent === 'function') {
                    updateContent(window.lang);
                }
            }

            injectAssets(data.assets);
            loader.classList.add("d-none");

            if (push) {
                history.pushState({ page }, "", getUrl(page));
            }

            const targetHash = hash ? '#' + hash : window.location.hash;
            if (targetHash && targetHash.length > 1) {
                setTimeout(() => {
                    try {
                        const el = document.querySelector(targetHash);
                        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    } catch (e) { console.warn("Invalid hash selector:", targetHash); }
                }, 600);
            }
        })
        .catch(() => {
            content.innerHTML = `<div class="alert alert-danger">Sayfa yüklenemedi</div>`;
            loader.classList.add("d-none");
        });
}

window.addEventListener("DOMContentLoaded", () => {
    if (!loader || !content) return; // Router pasifse başlatma

    const currentPath = window.location.pathname;
    const urlParams = new URLSearchParams(window.location.search);
    let initialPage = "home";
    let fullParams = window.location.search;

    if (urlParams.has("page")) {
        initialPage = urlParams.get("page");
    } else if (typeof FRIENDLY_URL !== 'undefined' && FRIENDLY_URL === "1") {
        const cleanPath = currentPath.replace(BASE_PATH, "").replace(/^\/+|\/+$/g, "");
        if (cleanPath !== "" && cleanPath !== "index.php") {
            initialPage = cleanPath;
            fullParams = "";
        }
    }

    const target = fullParams ? initialPage + fullParams.replace('?page=' + initialPage, '').replace('page=' + initialPage, '') : initialPage;
    const finalTarget = (target + window.location.hash).replace('?&', '?').replace('&&', '&').replace(/\?$/, '');

    loadPage(finalTarget, false);

    if (window.location.hash) {
        setTimeout(() => {
            const el = document.querySelector(window.location.hash);
            if (el) el.scrollIntoView();
        }, 800);
    }
});

window.addEventListener("popstate", e => {
    if (e.state?.page) loadPage(e.state.page, false);
});

document.addEventListener("click", e => {
    const link = e.target.closest("[data-page]");
    if (!link || !loader || !content) return;

    e.preventDefault();
    loadPage(link.dataset.page);
});

let loadedJS = new Set();
let loadedCSS = new Set();

function injectAssets(assets) {
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


