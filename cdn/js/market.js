/**
 * SpeedPage Marketplace Logic
 */

let marketData = [];

async function loadMarket(type) {
    const listDiv = document.getElementById('market-list');
    const marketUrl = type === 'theme'
        ? 'https://raw.githubusercontent.com/snrj35-dev/SpeedPage-modul-theme/main/theme/market.json'
        : 'https://raw.githubusercontent.com/snrj35-dev/SpeedPage-modul-theme/main/modul/market.json';

    try {
        const resp = await fetch(marketUrl + '?v=' + Date.now());
        marketData = await resp.json();

        // Populate Authors
        const authors = [...new Set(marketData.map(item => item.author))];
        const authorSelect = document.getElementById('filterAuthor');
        if (authorSelect) {
            authorSelect.innerHTML = `<option value="all">${t('all')}</option>`;
            authors.forEach(a => {
                const opt = document.createElement('option');
                opt.value = a;
                opt.innerText = a;
                authorSelect.appendChild(opt);
            });
        }

        renderMarket(marketData, type);

        const countBadge = document.getElementById('market-count');
        if (countBadge) {
            countBadge.innerText = marketData.length;
            countBadge.style.display = 'inline-block';
        }
    } catch (err) {
        if (listDiv) {
            listDiv.innerHTML = `<div class="col-12 text-center text-danger py-5"><i class="fas fa-exclamation-triangle me-2"></i> Market yüklenemedi.</div>`;
        }
    }
}

function renderMarket(items, type) {
    const listDiv = document.getElementById('market-list');
    if (!listDiv) return;

    if (items.length === 0) {
        listDiv.innerHTML = `<div class="col-12 text-center text-muted py-5">${t('no_data')}</div>`;
        return;
    }

    listDiv.innerHTML = items.map(item => `
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100 theme-card">
                <div class="position-relative">
                    <img src="${item.image}" class="card-img-top" alt="${item.title}" style="height:180px; object-fit:cover;" loading="lazy">
                    ${item.featured ? '<span class="badge bg-warning text-dark position-absolute top-0 end-0 m-3 shadow-sm"><i class="fas fa-star me-1"></i> Featured</span>' : ''}
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title fw-bold mb-0">${item.title}</h5>
                        <span class="badge bg-light text-dark border rounded-pill">v${item.version}</span>
                    </div>
                    <p class="card-text text-muted small mb-3">${item.description}</p>
                    <div class="d-flex justify-content-between align-items-center mt-auto">
                        <span class="text-secondary small"><i class="fas fa-user-circle me-1"></i> ${item.author}</span>
                        <button onclick="installRemote('${item.download_url}', '${type}', '${item.id}')" class="btn btn-sm btn-primary rounded-pill px-3">
                            <i class="fas fa-download me-1"></i> ${t('install_and_update')}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function filterMarket(type) {
    const search = document.getElementById('marketSearch').value.toLowerCase();
    const author = document.getElementById('filterAuthor').value;
    const sort = document.getElementById('filterSort').value;

    let filtered = marketData.filter(item => {
        const matchesSearch = item.title.toLowerCase().includes(search) || item.description.toLowerCase().includes(search);
        const matchesAuthor = author === 'all' || item.author === author;
        return matchesSearch && matchesAuthor;
    });

    if (sort === 'newest') {
        filtered.sort((a, b) => new Date(b.date) - new Date(a.date));
    } else if (sort === 'featured') {
        filtered.sort((a, b) => (b.featured ? 1 : 0) - (a.featured ? 1 : 0));
    }

    renderMarket(filtered, type);
}

function installRemote(url, type, name) {
    if (!confirm(t('confirm_install'))) return;

    const btn = event.currentTarget;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> ${t('downloading')}`;

    let fd = new FormData();
    fd.append('action', 'remote_install');
    fd.append('url', url);
    fd.append('type', type);
    fd.append('name', name);
    fd.append('csrf', typeof csrfToken !== 'undefined' ? csrfToken : '');

    fetch('modul-func.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                alert(res.message);
                location.reload();
            } else {
                alert(res.message);
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        })
        .catch(err => {
            alert(t('install_error'));
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
}
