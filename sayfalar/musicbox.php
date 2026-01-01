<?php
// --- BACKEND MANTIĞI (API) ---
$dir_media = ROOT_DIR . 'media/music';
$dir_data = ROOT_DIR . 'media/playlists';

// Klasörleri oluştur ve oluşturulamazsa hata vermemesi için @ kullan veya hata kontrolü yap
if (!is_dir($dir_media)) {
    if (!mkdir($dir_media, 0755, true)) {
        die("Hata: 'music' klasörü oluşturulamadı. İzinleri kontrol edin.");
    }
}

if (!is_dir($dir_data)) {
    if (!mkdir($dir_data, 0755, true)) {
        die("Hata: 'playlists' klasörü oluşturulamadı. İzinleri kontrol edin.");
    }
}
$master_cache = $dir_data . '/playlist.json';

if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];

    if ($action === 'fetch_all') {
        if (isset($_GET['refresh']) || !file_exists($master_cache)) {
            $playlist = [];
            $allowed = '/\.(mp3|wav|ogg)$/i';

            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir_media, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if ($file->isFile() && preg_match($allowed, $file->getFilename())) {
                    // music klasöründen itibaren relative path
                    $rel = substr($file->getPathname(), strlen($dir_media));
                    $rel = ltrim(str_replace('\\', '/', $rel), '/');

                    // genre = alt klasör adı (music/rock, music/pop vs.)
                    $sub = str_replace('\\', '/', substr($file->getPath(), strlen($dir_media)));
                    $genre = trim($sub, '/') ?: 'All';

                    $playlist[] = [
                        'id' => md5($file->getPathname()), // full path md5 daha güvenli
                        'title' => pathinfo($file->getFilename(), PATHINFO_FILENAME),
                        'artist' => 'Unknown',
                        'src' => 'media/music/' . implode('/', array_map('rawurlencode', explode('/', $rel))),
                        'genre' => $genre
                    ];
                }
            }
            file_put_contents(
                $master_cache,
                json_encode($playlist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }
        echo file_get_contents($master_cache);
        exit;
    }


    if ($action === 'list_playlists') {
        $list = [];
        foreach (scandir($dir_data) as $f) {
            if (in_array($f, ['.', '..', 'playlist.json']))
                continue;
            if (pathinfo($f, PATHINFO_EXTENSION) === 'json') {
                $list[] = pathinfo($f, PATHINFO_FILENAME);
            }
        }
        echo json_encode(array_values($list));
        exit;
    }

    if ($action === 'save_playlist') {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = preg_replace('/[^\\p{L}0-9_ ]/u', '', $input['name'] ?? '');
        $safe_name = str_replace(' ', '_', trim($name));
        if (!$safe_name) {
            echo json_encode(['ok' => false]);
            exit;
        }

        if (!empty($input['original']) && $input['original'] !== $safe_name) {
            $old = $dir_data . '/' . str_replace(' ', '_', $input['original']) . '.json';
            if (file_exists($old))
                unlink($old);
        }

        $path = $dir_data . '/' . $safe_name . '.json';
        file_put_contents($path, json_encode($input['tracks'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'delete_playlist') {
        $input = json_decode(file_get_contents('php://input'), true);
        $safe_name = str_replace(' ', '_', $input['name']);
        $path = $dir_data . '/' . $safe_name . '.json';
        if (file_exists($path))
            unlink($path);
        echo json_encode(['ok' => true]);
        exit;
    }
}
?>

<main class="container py-4">
    <section class="player card mx-auto mb-4 shadow-lg">
        <div class="p-4 text-center">
            <i id="trackCover" class="fa-solid fa-music fa-5x mb-3 text-primary"></i>
            <h5 id="trackTitle" class="text-truncate mb-1">Yükleniyor...</h5>
            <p id="trackArtist" class="text-secondary small mb-4">—</p>

            <audio id="audio"></audio>

            <div class="controls d-flex justify-content-center align-items-center gap-3 mb-4">
                <button id="shuffleBtn" class="btn btn-outline-secondary btn-sm"><i
                        class="fa-solid fa-shuffle"></i></button>
                <button id="prevBtn" class="btn btn-outline-secondary"><i
                        class="fa-solid fa-backward-step"></i></button>
                <button id="playPauseBtn" class="btn btn-primary btn-lg rounded-circle"><i
                        class="fa-solid fa-play"></i></button>
                <button id="nextBtn" class="btn btn-outline-secondary"><i class="fa-solid fa-forward-step"></i></button>
                <button id="repeatBtn" class="btn btn-outline-secondary btn-sm"><i
                        class="fa-solid fa-redo"></i></button>
            </div>

            <div class="timeline">
                <div class="d-flex justify-content-between small text-secondary mb-1">
                    <span id="currentTime">0:00</span>
                    <span id="duration">0:00</span>
                </div>
                <input id="seekBar" type="range" min="0" max="100" value="0" class="form-range">
            </div>

        </div>
        <div class="accordion accordion-flush mt-3" id="playerAccordion">
            <div class="accordion-item bg-transparent border-0">
                <h2 class="accordion-header">
                    <button id="playlistDropdownBtn"
                        class="accordion-button collapsed bg-dark text-white shadow-none border-bottom border-secondary"
                        type="button" data-bs-toggle="collapse" data-bs-target="#collapseList"
                        style="border-radius: 15px;">
                        <i class="fa-solid fa-list-ul me-2"></i> Şarkı Listesi
                    </button>
                </h2>
                <div id="collapseList" class="accordion-collapse collapse" data-bs-parent="#playerAccordion">
                    <div class="accordion-body p-0">
                        <div id="currentTrackList" class="list-group list-group-flush"
                            style="max-height: 250px; overflow-y: auto;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="card bg-dark border-secondary shadow">
        <div class="card-header d-flex justify-content-between align-items-center border-secondary">
            <h5 class="playlistler">Playlists</h5>
            <button id="newPlaylistBtn" class="btn btn-sm btn-success">Yeni Playlist</button>
        </div>
        <ul id="savedPlaylists" class="list-group list-group-flush"></ul>
    </div>
</main>

<div class="modal fade" id="newPlaylistModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Playlist Oluştur / Düzenle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-7">
                        <label class="small text-secondary">Playlist Adı</label>
                        <input id="playlistName" class="form-control" placeholder="Örn: Favorilerim">
                    </div>
                    <div class="col-md-5">
                        <label class="small text-secondary">Klasöre Göre Filtrele</label>
                        <select id="modalGenreFilter" class="form-select"></select>
                    </div>
                </div>
                <div class="d-flex gap-2 mb-2">
                    <button id="selectAllBtn" class="btn btn-sm btn-outline-info">Görünenlerin Tümünü Seç</button>
                    <button id="clearSelectionBtn" class="btn btn-sm btn-outline-warning">Seçimi Temizle</button>
                </div>
                <div id="playlistTracks" class="list-group border border-secondary p-1"></div>
            </div>
            <div class="modal-footer border-secondary">
                <button id="savePlaylistBtn" class="btn btn-primary w-100 py-2">Değişiklikleri Kaydet</button>
            </div>
        </div>
    </div>
</div>

<footer class="text-center py-5">
    <button id="refreshBtn" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-sync"></i> Medya Kitaplığını
        Tara</button>
</footer>