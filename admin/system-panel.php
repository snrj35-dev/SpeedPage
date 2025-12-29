<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
/**
 * System Panel (Admin)
 */

$startTime = microtime(true);
header('X-Content-Type-Options: nosniff');

$data = [];

/* System */
$data['system'] = [
    'OS' => PHP_OS_FAMILY,
    'Host' => gethostname(),
    'Time' => date('H:i:s')
];

/* Resources (Linux only logic, kept same) */
if (PHP_OS_FAMILY === 'Linux') {
    $diskTotal = disk_total_space('/');
    $diskFree = disk_free_space('/');
    $diskUsed = round((($diskTotal - $diskFree) / $diskTotal) * 100);

    $ramUsed = null;
    if (is_readable('/proc/meminfo')) {
        $mem = file('/proc/meminfo');
        foreach ($mem as $line) {
            if (str_starts_with($line, 'MemTotal'))
                $total = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT);
            if (str_starts_with($line, 'MemAvailable'))
                $avail = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT);
        }
        if (isset($total, $avail)) {
            $ramUsed = round((($total - $avail) / $total) * 100);
        }
    }

    $data['resources'] = ['disk' => $diskUsed, 'ram' => $ramUsed];
} else {
    // Windows fallback
    $data['resources'] = ['disk' => 0, 'ram' => 0];
}

/* Server */
$data['server'] = [
    'Web Server' => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI',
    'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? '-',
    'Protocol' => $_SERVER['SERVER_PROTOCOL'] ?? '-',
    'HTTPS' => (!empty($_SERVER['HTTPS']) ? 'Yes' : 'No'),
];

/* PHP Metrics */
$data['php'] = [
    'version' => PHP_VERSION,
    'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
    'execution_time' => round((microtime(true) - $startTime) * 1000, 2) . ' ms',
    'Memory Limit' => ini_get('memory_limit'),
    'Max Execution' => ini_get('max_execution_time') . 's',
    'Upload Max' => ini_get('upload_max_filesize'),
    'Post Max' => ini_get('post_max_size')
];

/* getrusage */
if (function_exists('getrusage')) {
    $ru = getrusage();
    $data['rusage'] = [
        'user_cpu' => $ru['ru_utime.tv_sec'] . 's',
        'sys_cpu' => $ru['ru_stime.tv_sec'] . 's',
        'max_rss' => $ru['ru_maxrss'] ?? null
    ];
}

/* PHP-FPM */
$data['fpm'] = function_exists('fastcgi_finish_request') ? 'PHP-FPM detected' : 'Not PHP-FPM';

/* GeoIP */
$data['geo'] = [
    'IP' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
    'Country' => $_SERVER['GEOIP_COUNTRY_NAME'] ?? 'N/A'
];

/* Sparkline fake data */
$data['spark'] = [
    'disk' => array_map(fn() => rand(40, 80), range(1, 10)),
    'ram' => array_map(fn() => rand(20, 70), range(1, 10))
];

/* PHP Extensions */
$data['extensions'] = get_loaded_extensions();

/* Database */
$data['database'] = [
    'MySQL' => (extension_loaded('pdo_mysql') || extension_loaded('mysqli')) ? 'Available' : 'Not Installed',
    'SQLite' => (extension_loaded('sqlite3') || extension_loaded('pdo_sqlite')) ? 'Available' : 'Not Installed'
];

/* Apache Modules */
if (function_exists('apache_get_modules')) {
    $data['apache_modules'] = apache_get_modules();
} else {
    $data['apache_modules'] = ['apache_get_modules() unavailable (using PHP-FPM/Nginx?)'];
}

/* Logs */
$phpLog = ini_get('error_log');
if ($phpLog && is_readable($phpLog)) {
    $data['logs']['php'] = file_get_contents($phpLog, false, null, max(0, filesize($phpLog) - 5000));
} else {
    $data['logs']['php'] = 'Log bulunamadı veya erişim yok';
}

/* Visitors (Mock or DB) */
$data['visitors'] = [];
if (file_exists(__DIR__ . "/visitors.db")) {
    $db_vis = new SQLite3(__DIR__ . "/visitors.db");
    $res = $db_vis->query("SELECT ip,time FROM visitors WHERE time > strftime('%s','now')-300");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $data['visitors'][] = $row;
    }
} else {
    // Placeholder
    $data['visitors'][] = ['ip' => $_SERVER['REMOTE_ADDR'], 'time' => date('H:i:s')];
}

/* File Permissions */
$criticalFiles = ['settings.php', 'admin/system-panel.php'];
foreach ($criticalFiles as $f) {
    $path = ROOT_DIR . $f;
    if (file_exists($path)) {
        $perm = substr(sprintf('%o', fileperms($path)), -3);
        $data['permissions'][$f] = $perm;
    } else {
        $data['permissions'][$f] = 'Dosya yok';
    }
}

/* PHP.ini Settings */
$data['php_ini'] = [
    'Memory Limit' => ini_get('memory_limit'),
    'Max Execution' => ini_get('max_execution_time') . 's',
    'Upload Max' => ini_get('upload_max_filesize'),
    'Post Max' => ini_get('post_max_size')
];

// AJAX REQUEST -> Return JSON
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}
?>

<div class="container py-4">
    <h3><i class="fas fa-cogs"></i> <span lang="system">Sistem Paneli</span></h3>
    <div class="row">
        <!-- Ortadaki dashboard (JS Doldurur) -->
        <div class="col-md-9">
            <div class="row g-4" id="widgets"></div>
        </div>

        <!-- Sağ sidebar (accordion) - PHP Render Initial State for Speed -->
        <div class="col-md-3">
            <div id="sysSidebar">
                <div class="accordion" id="sysAccordion">

                    <!-- Extensions -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed text" type="button" data-bs-toggle="collapse"
                                data-bs-target="#ext">
                                <i class="fa-solid fa-puzzle-piece me-2"></i>
                                <span lang="php_extensions">PHP Eklentileri</span> (<?= count($data['extensions']) ?>)
                            </button>
                        </h2>
                        <div id="ext" class="accordion-collapse collapse" data-bs-parent="#sysAccordion">
                            <div class="accordion-body">
                                <?php foreach ($data['extensions'] as $e): ?>
                                    <span class="badge bg-info text-dark me-1 mb-1"><?= e($e) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Database -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed text" type="button" data-bs-toggle="collapse"
                                data-bs-target="#db">
                                <i class="fa-solid fa-database me-2"></i>
                                <span lang="databases">Veritabanları</span>
                            </button>
                        </h2>
                        <div id="db" class="accordion-collapse collapse" data-bs-parent="#sysAccordion">
                            <div class="accordion-body">
                                <?php foreach ($data['database'] as $k => $v): ?>
                                    <div class="d-flex justify-content-between">
                                        <span><?= e($k) ?></span>
                                        <span
                                            class="badge <?= $v === 'Available' ? 'bg-success' : 'bg-danger' ?>"><?= e($v) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Apache Modules -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed text" type="button" data-bs-toggle="collapse"
                                data-bs-target="#apache">
                                <i class="fa-solid fa-cubes me-2"></i>
                                <span lang="apache_modules">Apache Modülleri</span>
                                (<?= count($data['apache_modules']) ?>)
                            </button>
                        </h2>
                        <div id="apache" class="accordion-collapse collapse" data-bs-parent="#sysAccordion">
                            <div class="accordion-body">
                                <?php foreach ($data['apache_modules'] as $m): ?>
                                    <span class="badge bg-secondary me-1 mb-1"><?= e($m) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Logs -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed text-warning" type="button"
                                data-bs-toggle="collapse" data-bs-target="#logs">
                                <i class="fa-solid fa-file-lines me-2"></i>
                                <span lang="php_logs">PHP Logları</span>
                            </button>
                        </h2>
                        <div id="logs" class="accordion-collapse collapse" data-bs-parent="#sysAccordion">
                            <div class="accordion-body">
                                <pre class="small"
                                    style="color:var(--text); white-space:pre-wrap; max-height:300px; overflow:auto;"><?= e($data['logs']['php']) ?></pre>
                            </div>
                        </div>
                    </div>

                    <!-- Visitors -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed text" type="button" data-bs-toggle="collapse"
                                data-bs-target="#visitors">
                                <i class="fa-solid fa-users me-2"></i>
                                <span lang="active_visitors">Aktif Ziyaretçiler</span> (<?= count($data['visitors']) ?>)
                            </button>
                        </h2>
                        <div id="visitors" class="accordion-collapse collapse" data-bs-parent="#sysAccordion">
                            <div class="accordion-body">
                                <?php foreach ($data['visitors'] as $v): ?>
                                    <div class="d-flex justify-content-between">
                                        <span><?= e($v['ip']) ?></span>
                                        <span><?= e($v['time']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Permissions -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed text" type="button" data-bs-toggle="collapse"
                                data-bs-target="#perms">
                                <i class="fa-solid fa-lock me-2"></i>
                                <span lang="file_permissions">Dosya İzinleri</span>
                            </button>
                        </h2>
                        <div id="perms" class="accordion-collapse collapse" data-bs-parent="#sysAccordion">
                            <div class="accordion-body">
                                <?php foreach ($data['permissions'] as $file => $perm): ?>
                                    <div class="d-flex justify-content-between">
                                        <span><?= e($file) ?></span>
                                        <span
                                            class="badge <?= ($perm === '644' || $perm === '600') ? 'bg-success' : 'bg-danger' ?>"><?= e($perm) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- PHP INI -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed text" type="button" data-bs-toggle="collapse"
                                data-bs-target="#phpini">
                                <i class="fa-solid fa-gear me-2"></i>
                                <span lang="php_ini_settings">PHP Ayarları</span>
                            </button>
                        </h2>
                        <div id="phpini" class="accordion-collapse collapse" data-bs-parent="#sysAccordion">
                            <div class="accordion-body">
                                <?php foreach ($data['php_ini'] as $k => $v): ?>
                                    <div class="d-flex justify-content-between">
                                        <span><?= e($k) ?></span>
                                        <span><?= e($v) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>