const content = document.getElementById("page-content");
const loader = document.getElementById("page-loader");

function loadPage(page = "home", push = true) {
    loader.classList.remove("d-none");

    fetch(`page.php?page=${page}`)
        .then(res => res.json())
        .then(data => {
            content.innerHTML = data.html;
            injectAssets(data.assets);
            loader.classList.add("d-none");

            if (push) {
                history.pushState({ page }, "", `?page=${page}`);
            }
        })
        .catch(() => {
            content.innerHTML = `<div class="alert alert-danger">Sayfa yüklenemedi</div>`;
            loader.classList.add("d-none");
        });
}

window.addEventListener("popstate", e => {
    if (e.state?.page) loadPage(e.state.page, false);
});

document.addEventListener("click", e => {
    const link = e.target.closest("[data-page]");
    if (!link) return;

    e.preventDefault();
    loadPage(link.dataset.page);
});

const urlParams = new URLSearchParams(window.location.search);
loadPage(urlParams.get("page") || "home", false);

let loadedJS = new Set();
let loadedCSS = new Set();

function injectAssets(assets) {

    // ✅ CSS dosyaları
    (assets.css || []).forEach(css => {
        if (loadedCSS.has(css)) return;
        loadedCSS.add(css);

        const link = document.createElement("link");
        link.rel = "stylesheet";

        // ✅ BASE_PATH ekle
        link.href = css.startsWith("/") 
            ? BASE_PATH + css.substring(1)
            : BASE_PATH + css;

        document.head.appendChild(link);
    });

    // ✅ JS dosyaları
    (assets.js || []).forEach(js => {
        if (loadedJS.has(js)) return;
        loadedJS.add(js);

        const script = document.createElement("script");

        // ✅ BASE_PATH ekle
        script.src = js.startsWith("/") 
            ? BASE_PATH + js.substring(1)
            : BASE_PATH + js;

        script.defer = true;
        document.body.appendChild(script);
    });
}


