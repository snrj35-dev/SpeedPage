let allTracks = [], userPlaylist = [], index = 0, isPlaying = false;
let isShuffle = false, repeatMode = 0, editingOriginal = null;
let currentSelectedIds = new Set(); // Düzenleme modunda seçili olanları tutmak için
const API_URL = `${BASE_PATH}page.php?page=musicbox`;
const PLAYLISTS_URL = `${BASE_PATH}media/playlists`;
const audio = document.getElementById('audio');
const playPauseBtn = document.getElementById('playPauseBtn');
const seekBar = document.getElementById('seekBar');
const newPlaylistModal = new bootstrap.Modal(document.getElementById('newPlaylistModal'));
const playerEl = document.querySelector('.player');

async function init() {
    await loadAllTracks(false);
    loadSavedPlaylists();
}

async function loadAllTracks(refresh = false) {
    const res = await fetch(`${API_URL}&action=fetch_all${refresh ? '&refresh=1' : ''}`);
    allTracks = await res.json();
    if (userPlaylist.length === 0) userPlaylist = [...allTracks];
    buildGenres();
    loadTrack(0);
}

function loadTrack(i) {
    index = i;
    const t = userPlaylist[index];
    if (!t) return;

    // Şarkı değişiminde ended olayının tekrar tetiklenmemesi için 
    // geçici olarak event listener'ı pasifize etmeye gerek kalmadan src'yi değiştiriyoruz.
    audio.pause();
    audio.src = t.src;

    document.getElementById('trackTitle').textContent = t.title;
    document.getElementById('trackArtist').textContent = t.artist;


    // Progress bar'ı sıfırla
    seekBar.value = 0;
    document.getElementById('currentTime').textContent = "0:00";
    updateDropdownList();
}

function updateDropdownList() {
    const listContainer = document.getElementById('currentTrackList');
    if (!listContainer) return;

    listContainer.innerHTML = userPlaylist.map((track, i) => `
        <button type="button" 
                onclick="jumpToTrack(${i})" 
                class="list-group-item list-group-item-action bg-transparent text-white border-secondary d-flex justify-content-between align-items-center ${i === index ? 'active-track' : ''}">
            <div class="text-truncate" style="max-width: 85%;">
                <small class="text-secondary me-2">${i + 1}.</small>
                <span class="text-secondary">${track.title}</span>
            </div>
            ${i === index ? '<i class="fa-solid fa-play fa-beat-fade text-success"></i>' : ''}
        </button>
    `).join('');

    // Buton başlığını güncelle
    const btn = document.getElementById('playlistDropdownBtn');
    if (btn) btn.innerHTML = `<i class="fa-solid fa-list-ul me-2"></i> Şu an çalan: ${index + 1} / ${userPlaylist.length}`;

    // Aktif şarkıyı listenin ortasına odakla
    const activeEl = listContainer.querySelector('.active-track');
    if (activeEl) {
        activeEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

// Belirli bir şarkıya atlama fonksiyonu
function jumpToTrack(i) {
    index = i;
    loadTrack(index);
    // Şarkı seçilince otomatik çalması için:
    audio.oncanplay = function () {
        play();
        audio.oncanplay = null;
    };
}
function play() {
    // Tarayıcıya şarkıyı yüklemesi için kısa bir süre tanıyalım
    const playPromise = audio.play();

    if (playPromise !== undefined) {
        playPromise.then(() => {
            isPlaying = true;
            playPauseBtn.innerHTML = '<i class="fa-solid fa-pause"></i>';
            playerEl.classList.add('playing');
            playerEl.classList.remove('paused');
        }).catch(error => {
            // Eğer yükleme henüz bitmediyse veya kullanıcı etkileşimi gerekliyse buraya düşer
            console.log("Oynatma bekletiliyor...");
            isPlaying = false;
            playPauseBtn.innerHTML = '<i class="fa-solid fa-play"></i>';
        });
    }
}

function togglePlay() {
    if (isPlaying) {
        audio.pause();
        playPauseBtn.innerHTML = '<i class="fa-solid fa-play"></i>';
        playerEl.classList.remove('playing');
        playerEl.classList.add('paused');
        isPlaying = false;
    } else {
        play();
    }
}

// Otomatik geçiş
audio.onended = function () {
    // timeout süresini biraz artırarak tarayıcının tamponu (buffer) boşaltmasını bekliyoruz
    setTimeout(() => {
        if (repeatMode === 2) {
            // Tek şarkı tekrarı
            audio.currentTime = 0;
            play();
        } else {
            nextTrack();
        }
    }, 250); // 100ms yerine 250ms daha güvenlidir
};

function nextTrack() {
    if (userPlaylist.length === 0) return;

    if (isShuffle) {
        index = Math.floor(Math.random() * userPlaylist.length);
    } else {
        index = (index + 1) % userPlaylist.length;
    }

    loadTrack(index);

    // loadTrack içinde src değiştikten sonra tarayıcının 
    // 'canplay' (çalınabilir) durumuna gelmesini bekleyip öyle play diyelim
    audio.oncanplay = function () {
        play();
        audio.oncanplay = null; // Sürekli tetiklenmemesi için temizliyoruz
    };
}

function prevTrack() {
    index = (index - 1 + userPlaylist.length) % userPlaylist.length;
    loadTrack(index);

    audio.oncanplay = function () {
        play();
        audio.oncanplay = null;
    };
}

// Playlist Yönetimi
async function loadSavedPlaylists() {
    const res = await fetch(`${API_URL}&action=list_playlists`);
    const list = await res.json();
    const container = document.getElementById('savedPlaylists');
    container.innerHTML = list.map(name => `
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span class="fw-bold text-info">${name.replace(/_/g, ' ')}</span>
            <div class="btn-group">
                <button onclick="playSavedPlaylist('${name}')" class="btn btn-sm btn-primary"><i class="fa-solid fa-play"></i> Aç</button>
                <button onclick="editPlaylist('${name}')" class="btn btn-sm btn-info text-white"><i class="fa-solid fa-pen"></i> Düzenle</button>
                <button onclick="deletePlaylist('${name}')" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i></button>
            </div>
        </li>
    `).join('');
}

// Playlisti başlatma fonksiyonu
async function playSavedPlaylist(name) {
    try {
        const res = await fetch(`${PLAYLISTS_URL}/${name}.json`);
        const tracks = await res.json();

        if (tracks && tracks.length > 0) {
            // Ana listeyi seçilen playlist ile değiştir
            userPlaylist = [...tracks];
            index = 0; // İlk şarkıdan başla

            loadTrack(index);

            // Tarayıcı yükleme garantisi için
            audio.load();

            // Oynatma sözü (promise) kontrolü
            const playPromise = audio.play();
            if (playPromise !== undefined) {
                playPromise.then(() => {
                    isPlaying = true;
                    playPauseBtn.innerHTML = '<i class="fa-solid fa-pause"></i>';
                }).catch(error => {
                    console.log("Oynatma engellendi: ", error);
                    isPlaying = false;
                    playPauseBtn.innerHTML = '<i class="fa-solid fa-play"></i>';
                });
            }
        }
    } catch (err) {
        console.error("Playlist yüklenirken hata oluştu:", err);
    }
}



async function editPlaylist(name) {
    editingOriginal = name;
    document.getElementById('playlistName').value = name.replace(/_/g, ' ');

    // Mevcut playlistteki şarkıları Set'e ekle
    const res = await fetch(`${PLAYLISTS_URL}/${name}.json`);
    const existingTracks = await res.json();
    currentSelectedIds = new Set(existingTracks.map(t => t.id));

    renderModalTracks();
    newPlaylistModal.show();
}

function renderModalTracks() {
    const filter = document.getElementById('modalGenreFilter').value;
    const container = document.getElementById('playlistTracks');
    const filtered = filter ? allTracks.filter(t => t.genre === filter) : allTracks;

    container.innerHTML = filtered.map(t => `
        <label class="list-group-item d-flex align-items-center gap-2">
            <input type="checkbox" value="${t.id}" class="track-check form-check-input" 
                ${currentSelectedIds.has(t.id) ? 'checked' : ''} 
                onchange="toggleTrackSelection('${t.id}', this.checked)">
            <span class="text-truncate small">${t.title}</span>
        </label>
    `).join('');
}

// Seçimleri hafızada tut (filtre değişse bile seçimler kaybolmaz)
function toggleTrackSelection(id, isChecked) {
    if (isChecked) currentSelectedIds.add(id);
    else currentSelectedIds.delete(id);
}

// Toplu Seçim/Temizle
document.getElementById('selectAllBtn').addEventListener('click', () => {
    document.querySelectorAll('.track-check').forEach(cb => {
        cb.checked = true;
        currentSelectedIds.add(cb.value);
    });
});

document.getElementById('clearSelectionBtn').addEventListener('click', () => {
    document.querySelectorAll('.track-check').forEach(cb => {
        cb.checked = false;
        currentSelectedIds.delete(cb.value);
    });
});

async function savePlaylist() {
    const name = document.getElementById('playlistName').value.trim();
    if (!name || currentSelectedIds.size === 0) return alert('Lütfen isim girin ve parça seçin');

    const tracks = allTracks.filter(t => currentSelectedIds.has(t.id));

    await fetch(`${API_URL}&action=save_playlist`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, tracks, original: editingOriginal })
    });

    newPlaylistModal.hide();
    loadSavedPlaylists();
}

async function deletePlaylist(name) {
    if (!confirm('Bu playlisti silmek istediğinize emin misiniz?')) return;
    await fetch(`${API_URL}&action=delete_playlist`, {
        method: 'POST',
        body: JSON.stringify({ name })
    });
    loadSavedPlaylists();
}

function buildGenres() {
    const genres = [...new Set(allTracks.map(t => t.genre))].sort();
    const sel = document.getElementById('modalGenreFilter');
    sel.innerHTML = '<option value="">Tüm Klasörler</option>' +
        genres.map(g => `<option value="${g}">${g}</option>`).join('');
}

// Event Listeners
playPauseBtn.addEventListener('click', togglePlay);
document.getElementById('nextBtn').addEventListener('click', nextTrack);
document.getElementById('prevBtn').addEventListener('click', prevTrack);
document.getElementById('refreshBtn').addEventListener('click', () => loadAllTracks(true));
document.getElementById('savePlaylistBtn').addEventListener('click', savePlaylist);
document.getElementById('modalGenreFilter').addEventListener('change', renderModalTracks);

document.getElementById('newPlaylistBtn').addEventListener('click', () => {
    editingOriginal = null;
    document.getElementById('playlistName').value = '';
    currentSelectedIds = new Set();
    renderModalTracks();
    newPlaylistModal.show();
});

audio.addEventListener('timeupdate', () => {
    if (audio.duration) {
        seekBar.value = (audio.currentTime / audio.duration) * 100;
        document.getElementById('currentTime').textContent = formatTime(audio.currentTime);
        document.getElementById('duration').textContent = formatTime(audio.duration);
    }
});

seekBar.addEventListener('input', () => audio.currentTime = (seekBar.value / 100) * audio.duration);

function formatTime(s) {
    const m = Math.floor(s / 60);
    const sec = Math.floor(s % 60);
    return `${m}:${sec.toString().padStart(2, '0')}`;
}

init();
