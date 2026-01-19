<?php
declare(strict_types=1);
// Global Hooks System

$hooks = [];

/**
 * Add a new hook listener
 *
 * @param string   $tag       Hook location/name (e.g. 'head_start')
 * @param callable $callback  Function to call
 * @param int      $priority  Execution order (lower runs first)
 */
function add_hook(string $tag, callable $callback, int $priority = 10): void
{
    global $hooks;
    $hooks[$tag][] = [
        'callback' => $callback,
        'priority' => $priority
    ];
}

/**
 * Run all hooks listeners for a tag
 *
 * @param string $tag   Hook location/name
 * @param mixed  $data  Optional data to pass to the callback
 * @return mixed
 */
function run_hook(string $tag, mixed $data = null): mixed
{
    global $hooks;
    if (isset($hooks[$tag])) {
        // Sort by priority
        usort($hooks[$tag], function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });

        foreach ($hooks[$tag] as $hook) {
            if (is_callable($hook['callback'])) {
                // Return value of callback is used as data for the next hook
                $result = call_user_func($hook['callback'], $data);
                if ($result !== null) {
                    $data = $result;
                }
            }
        }
    }
    return $data;
}

// AI Bug Reporter Hook
add_hook('footer_end', function () {
    global $db;

    // 1. Auth Check (Admin?)
    $is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
    $is_debug = defined('DEBUG') && DEBUG;

    if (!$is_admin && !$is_debug) {
        return;
    }

    // 2. AI API Key Check
    $apiKey = '';
    if (!$db) {
        $db_path = defined('DB_PATH') ? DB_PATH : __DIR__ . '/../admin/veritabanı/data.db';
        if (file_exists($db_path)) {
            try {
                $db = new PDO("sqlite:" . $db_path);
            } catch (Exception $e) {
                // Silent fail
            }
        }
    }

    if ($db) {
        try {
            // NEW: Check ai_providers table
            $stmt = $db->query("SELECT count(*) FROM ai_providers WHERE is_enabled = 1 AND api_key != '' AND api_key IS NOT NULL");
            $count = $stmt->fetchColumn();
            $apiKey = ($count > 0) ? 'yes' : '';
        } catch (Exception $e) {
            // Fallback: Check if old table exists just in case (optional, but good for transition)
            try {
                $stmt = $db->query("SELECT value_text FROM ai_settings WHERE key_name = 'api_key'");
                $apiKey = $stmt->fetchColumn();
            } catch (Exception $e2) {
            }
        }
    }

    // Convert false to empty string if needed
    if (!$apiKey)
        $apiKey = '';

    // If no API key and not debug, return
    if (!$apiKey && !$is_debug) {
        return;
    }

    // 3. Admin Path
    $adminUrl = (defined('BASE_URL') ? BASE_URL : '/admin/');

    // If BASE_URL doesn't contain /admin/ (not in admin panel)
    if (strpos($adminUrl, '/admin/') === false) {
        $adminUrl .= 'admin/';
    }

    $adminUrl .= 'index.php?page=aipanel&auto_analyze=1';

    // Using specialized hardcoded strings for DevTool (or use translations if preferred)
    // Rule says: use translations. I will wrap them.
    $title = function_exists('__') ? __('ai_bug_report_title', 'AI Hata Raporla') : 'AI Hata Raporla';
    $jsErrorTitle = function_exists('__') ? __('ai_js_error_title', 'JavaScript Hatası Yakalandı!') : 'JavaScript Hatası Yakalandı!';
    $criticalErrorTitle = function_exists('__') ? __('ai_critical_error_title', 'Kritik Hata: Boş Sayfa (Beyaz Sayfa) algılandı!') : 'Kritik Hata: Boş Sayfa (Beyaz Sayfa) algılandı!';
    $confirmMsg = function_exists('__') ? __('ai_confirm_msg', 'Sistem hatası/özeti yakalandı. AI Paneline aktarılsın mı?') : 'Sistem hatası/özeti yakalandı. AI Paneline aktarılsın mı?';

    ?>
    <style>
        #ai-bug-reporter-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 999999;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #0d6efd;
            color: white;
            border: 4px solid #fff;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        #ai-bug-reporter-btn:hover {
            transform: scale(1.1) rotate(15deg);
            background: #0b5ed7;
        }

        .critical-error-pulse {
            animation: pulse-red 2s infinite;
            background: #dc3545 !important;
        }

        @keyframes pulse-red {
            0% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
            }

            70% {
                box-shadow: 0 0 0 15px rgba(220, 53, 69, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
            }
        }
    </style>

    <div id="ai-bug-reporter-btn" title="<?php echo htmlspecialchars($title); ?>">
        <i class="fas fa-robot fa-lg"></i>
    </div>

    <script>
        (function () {
            const btn = document.getElementById('ai-bug-reporter-btn');
            if (!btn) return;

            const ADMIN_URL = '<?= $adminUrl ?>';
            const TITLES = {
                jsError: "<?= htmlspecialchars($jsErrorTitle) ?>",
                criticalError: "<?= htmlspecialchars($criticalErrorTitle) ?>",
                confirm: "<?= htmlspecialchars($confirmMsg) ?>"
            };

            // 1. Catch JS Errors
            if (!window.aiConsoleErrors) window.aiConsoleErrors = [];
            window.addEventListener('error', function (e) {
                window.aiConsoleErrors.push({
                    message: e.message,
                    file: e.filename,
                    line: e.lineno,
                    col: e.colno,
                    stack: e.error ? e.error.stack : ''
                });
                btn.classList.add('critical-error-pulse');
                btn.title = TITLES.jsError;
            });

            // 2. Click Event
            btn.addEventListener('click', function () {
                const reportData = {
                    url: window.location.href,
                    html_summary: document.body ? document.body.innerText.substring(0, 5000) : 'Body unreadable',
                    errors: window.aiConsoleErrors,
                    timestamp: new Date().toISOString()
                };

                localStorage.setItem('ai_bug_report', JSON.stringify(reportData));

                if (confirm(TITLES.confirm)) {
                    window.location.href = ADMIN_URL;
                }
            });

            // 3. Smart White Screen Check (delayed)
            window.addEventListener('load', function () {
                setTimeout(() => {
                    const textLength = document.body ? document.body.innerText.trim().length : 0;
                    // If page is less than 50 chars and no JS errors, turn red
                    if (textLength < 50 && window.aiConsoleErrors.length === 0) {
                        btn.classList.add('critical-error-pulse');
                        btn.title = TITLES.criticalError;
                    }
                }, 1000); // 1 second delay
            });
        })();
    </script>
    <?php
});
?>