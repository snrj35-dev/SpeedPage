<?php
declare(strict_types=1);

/**
 * System Panel (Admin)
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';

/** @var PDO $db */
global $db;

$startTime = microtime(true);

$data = [];

// 0. Otomatik Temizlik ve Logları Çek
if (function_exists('sp_log_cleanup')) {
    sp_log_cleanup(30); // 30 günden eski logları sil
}

// Logları Çek
$logs = [];
try {
    if (function_exists('ensure_log_table')) {
        ensure_log_table();
    }
    $logs = $db->query("
        SELECT l.*, u.username 
        FROM logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        ORDER BY l.id DESC 
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    if (function_exists('sp_log')) {
        sp_log('System Panel Logs Error: ' . $e->getMessage(), 'system_error');
    }
    $logs = [];
}

/* System */
$data['system'] = [
    'OS' => PHP_OS_FAMILY,
    'Host' => gethostname(),
    'Time' => date('H:i:s')
];

/* Resources (Linux only logic, kept same) */
if (PHP_OS_FAMILY === 'Linux') {
    $diskTotal = disk_total_space('/') ?: 1; // Prevent div by zero
    $diskFree = disk_free_space('/');
    $diskUsed = round((($diskTotal - $diskFree) / $diskTotal) * 100);

    $ramUsed = 0;
    if (is_readable('/proc/meminfo')) {
        $mem = file('/proc/meminfo');
        $total = 0;
        $avail = 0;
        foreach ($mem as $line) {
            if (str_starts_with($line, 'MemTotal'))
                $total = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT);
            if (str_starts_with($line, 'MemAvailable'))
                $avail = (int) filter_var($line, FILTER_SANITIZE_NUMBER_INT);
        }
        if ($total > 0) {
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
    'HTTPS' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'Yes' : 'No'),
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
    $data['logs']['php'] = __('no_logs');
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
    $data['visitors'][] = ['ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 'time' => date('H:i:s')];
}

/* File Permissions */
$criticalFiles = ['settings.php', 'admin/system-panel.php'];
foreach ($criticalFiles as $f) {
    $path = ROOT_DIR . $f;
    if (file_exists($path)) {
        $perm = substr(sprintf('%o', fileperms($path)), -3);
        $data['permissions'][$f] = $perm;
    } else {
        $data['permissions'][$f] = __('no_file');
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
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
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
                                <span lang="php_extensions"></span> (<?= count($data['extensions']) ?>)
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
                                <span lang="databases"></span>
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
                                <span lang="apache_modules"></span>
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
                                <span lang="php_logs"></span>
                            </button>
                        </h2>
                        <div id="logs" class="accordion-collapse collapse" data-bs-parent="#sysAccordion">
                            <div class="accordion-body">
                                <pre class="small"
                                    style="color:var(--text); white-space:pre-wrap; max-height:300px; overflow:auto;"><?= e((string) $data['logs']['php']) ?></pre>
                            </div>
                        </div>
                    </div>

                    <!-- Visitors -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed text" type="button" data-bs-toggle="collapse"
                                data-bs-target="#visitors">
                                <i class="fa-solid fa-users me-2"></i>
                                <span lang="active_visitors"></span> (<?= count($data['visitors']) ?>)
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
                                <span lang="file_permissions"></span>
                            </button>
                        </h2>
                        <div id="perms" class="accordion-collapse collapse" data-bs-parent="#sysAccordion">
                            <div class="accordion-body">
                                <?php foreach ($data['permissions'] as $file => $perm): ?>
                                    <div class="d-flex justify-content-between">
                                        <span><?= e($file) ?></span>
                                        <span
                                            class="badge <?= ($perm === '644' || $perm === '600') ? 'bg-success' : 'bg-danger' ?>"><?= e((string) $perm) ?></span>
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
                                <span lang="php_ini_settings"></span>
                            </button>
                        </h2>
                        <div id="phpini" class="accordion-collapse collapse" data-bs-parent="#sysAccordion">
                            <div class="accordion-body">
                                <?php foreach ($data['php_ini'] as $k => $v): ?>
                                    <div class="d-flex justify-content-between">
                                        <span><?= e($k) ?></span>
                                        <span><?= e((string) $v) ?></span>
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

<!-- LOG & AUDIT TABLOSU -->
<div class="card mt-4 shadow-sm">
    <div class="card-header bg-light d-flex justify-content-between align-items-center py-3">
        <h6 class="m-0 text-primary fw-bold">
            <i class="fas fa-history me-2"></i>
            <span lang="audit_logs">Denetim Logları</span>
        </h6>
        <button class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="confirmClearLogs()">
            <i class="fas fa-trash-alt me-1"></i> <span lang="clear">Temizle</span>
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="bg-light">
                    <tr>
                        <th width="50">#</th>
                        <th width="120" lang="date">Tarih</th>
                        <th lang="action_type">İşlem</th>
                        <th lang="user">Kullanıcı</th>
                        <th lang="ip">IP</th>
                        <th lang="message">Mesaj</th>
                        <th width="80" class="text-end" lang="details">Detay</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-3 text-muted" lang="no_logs">Kayıt bulunamadı.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= e((string) $log['id']) ?></td>
                                <td><?= e($log['created_at']) ?></td>
                                <td>
                                    <?php
                                    $badges = [
                                        'login_fail' => 'bg-danger',
                                        'login_success' => 'bg-success',
                                        'page_edit' => 'bg-primary',
                                        'settings_update' => 'bg-warning text-dark',
                                        'system_error' => 'bg-dark',
                                        'php_exception' => 'bg-danger'
                                    ];
                                    $bg = $badges[$log['action_type']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?= $bg ?>"><?= e($log['action_type']) ?></span>
                                </td>
                                <td class="fw-bold"><?= e($log['username'] ?? 'Ziyaretçi') ?></td>
                                <td class="text-muted small"><?= e($log['ip_address']) ?></td>
                                <td><?= e($log['message']) ?></td>
                                <td class="text-end">
                                    <?php if (!empty($log['old_data']) || !empty($log['new_data'])): ?>
                                        <button class="btn btn-sm btn-outline-info"
                                            onclick='viewLogDetails(<?= json_encode($log, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>

<!-- LOG DETAY MODAL -->
<div class="modal fade" id="logDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-search me-2"></i> <span lang="log_details">Log Detayı</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 text-danger" lang="old_data">Eski Veri</h6>
                        <pre id="logOld" class="bg-body border p-3 rounded"
                            style="max-height: 400px; overflow: auto;"></pre>
                    </div>
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 text-success" lang="new_data">Yeni Veri</h6>
                        <pre id="logNew" class="bg-body border p-3 rounded"
                            style="max-height: 400px; overflow: auto;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function viewLogDetails(log) {
        const formatJSON = (str) => {
            if (!str) return "{}";
            try {
                return JSON.stringify(JSON.parse(str), null, 4);
            } catch (e) {
                return str;
            }
        };

        document.getElementById('logOld').textContent = formatJSON(log.old_data);
        document.getElementById('logNew').textContent = formatJSON(log.new_data);

        const modal = new bootstrap.Modal(document.getElementById('logDetailModal'));
        modal.show();
    }

    function confirmClearLogs() {
        if (!confirm("<?= __('confirm_clear_logs') ?>")) return;

        const formData = new FormData();
        formData.append('action', 'clear_logs');
        formData.append('csrf', '<?= $_SESSION['csrf'] ?>');

        fetch('modul-func.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert("<?= __('generic_error') ?>");
            });
    }
</script>