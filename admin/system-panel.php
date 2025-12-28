<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
/**
 * System Panel (Admin)
 * Ultimate PHP Server Dashboard (modüler versiyon)
 */

$startTime = microtime(true);
header('X-Content-Type-Options: nosniff');

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $data = [];

    /* System */
    $data['system'] = [
        'OS'   => PHP_OS_FAMILY,
        'Host' => gethostname(),
        'Time' => date('H:i:s')
    ];

    /* Resources (Linux) */
    if (PHP_OS_FAMILY === 'Linux') {
        $diskTotal = disk_total_space('/');
        $diskFree  = disk_free_space('/');
        $diskUsed  = round((($diskTotal - $diskFree) / $diskTotal) * 100);

        $ramUsed = null;
        if (is_readable('/proc/meminfo')) {
            $mem = file('/proc/meminfo');
            foreach ($mem as $line) {
                if (str_starts_with($line, 'MemTotal')) $total = (int)filter_var($line, FILTER_SANITIZE_NUMBER_INT);
                if (str_starts_with($line, 'MemAvailable')) $avail = (int)filter_var($line, FILTER_SANITIZE_NUMBER_INT);
            }
            if (isset($total, $avail)) {
                $ramUsed = round((($total - $avail) / $total) * 100);
            }
        }

        $data['resources'] = [
            'disk' => $diskUsed,
            'ram'  => $ramUsed
        ];
    }

    /* Server */
    $data['server'] = [
        'Web Server'    => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI',
        'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? '-',
        'Protocol'      => $_SERVER['SERVER_PROTOCOL'] ?? '-',
        'HTTPS'         => (!empty($_SERVER['HTTPS']) ? 'Yes' : 'No'),
    ];

    /* PHP Metrics */
    $data['php'] = [
        'version'        => PHP_VERSION,
        'memory_peak'    => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
        'execution_time' => round((microtime(true) - $startTime) * 1000, 2) . ' ms',
        'Memory Limit'   => ini_get('memory_limit'),
        'Max Execution'  => ini_get('max_execution_time') . 's',
        'Upload Max'     => ini_get('upload_max_filesize'),
        'Post Max'       => ini_get('post_max_size')
    ];

    /* getrusage */
    if (function_exists('getrusage')) {
        $ru = getrusage();
        $data['rusage'] = [
            'user_cpu' => $ru['ru_utime.tv_sec'] . 's',
            'sys_cpu'  => $ru['ru_stime.tv_sec'] . 's',
            'max_rss'  => $ru['ru_maxrss'] ?? null
        ];
    }

    /* PHP-FPM */
    $data['fpm'] = function_exists('fastcgi_finish_request') ? 'PHP-FPM detected' : 'Not PHP-FPM';

    /* GeoIP */
    $data['geo'] = [
        'IP'      => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
        'Country' => $_SERVER['GEOIP_COUNTRY_NAME'] ?? 'N/A'
    ];

    /* Sparkline data */
    $data['spark'] = [
        'disk' => array_map(fn() => rand(40, 80), range(1,10)),
        'ram'  => array_map(fn() => rand(20, 70), range(1,10))
    ];

    /* PHP Extensions */
    $data['extensions'] = get_loaded_extensions();

    /* Database */
    $data['database'] = [
        'MySQL'  => (extension_loaded('pdo_mysql') || extension_loaded('mysqli')) ? 'Available' : 'Not Installed',
        'SQLite' => (extension_loaded('sqlite3') || extension_loaded('pdo_sqlite')) ? 'Available' : 'Not Installed'
    ];

    /* Apache Modules */
    if (function_exists('apache_get_modules')) {
        $data['apache_modules'] = apache_get_modules();
    } else {
        $data['apache_modules'] = ['apache_get_modules() not available'];
    }
    /* Logs */
    $phpLog = ini_get('error_log');
    if ($phpLog && is_readable($phpLog)) {
        $data['logs']['php'] = file_get_contents($phpLog, false, null, max(0, filesize($phpLog)-5000));
    } else {
        $data['logs']['php'] = 'Log bulunamadı veya erişim yok';
    }

    /* Visitors (örnek: SQLite tablo varsa) */
    $data['visitors'] = [];
    if (file_exists(__DIR__."/visitors.db")) {
        $db = new SQLite3(__DIR__."/visitors.db");
        $res = $db->query("SELECT ip,time FROM visitors WHERE time > strftime('%s','now')-300");
        while($row = $res->fetchArray(SQLITE3_ASSOC)){
            $data['visitors'][] = $row;
        }
    } else {
        $data['visitors'][] = ['ip'=>'-', 'time'=>'No data'];
    }

    /* File Permissions */
    $criticalFiles = ['settings.php','admin/system-panel.php'];
    foreach($criticalFiles as $f){
        $path = ROOT_DIR.$f;
        if(file_exists($path)){
            $perm = substr(sprintf('%o', fileperms($path)), -3);
            $data['permissions'][$f] = $perm;
        } else {
            $data['permissions'][$f] = 'Dosya yok';
        }
    }


    /* PHP.ini Settings */
    $data['php_ini'] = [
        'Memory Limit'   => ini_get('memory_limit'),
        'Max Execution'  => ini_get('max_execution_time').'s',
        'Upload Max'     => ini_get('upload_max_filesize'),
        'Post Max'       => ini_get('post_max_size')
    ];


    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}
?>

<div class="container py-4">
    <h3><i class="fas fa-cogs"></i> <span lang="system"></span></h3>
    <div class="row">
        <!-- Ortadaki dashboard -->
        <div class="col-md-9">
            <div class="row g-4" id="widgets"></div>
        </div>

        <!-- Sağ sidebar (accordion) -->
        <div class="col-md-3">
            <div id="sysSidebar"></div>
        </div>
    </div>
</div>
