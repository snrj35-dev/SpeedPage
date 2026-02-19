<?php
declare(strict_types=1);

ob_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/AiCore.php';
ob_clean();

/** @var PDO $db */
/** @var bool $is_admin */

// Config
ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
set_time_limit(300);
ini_set('memory_limit', '512M');

// Auth Check
if (!isset($is_admin) || !$is_admin) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => function_exists('__') ? __('access_denied') : 'Access Denied']);
    exit;
}

// Default Models (Reference)
$defaultModels = [
    'gemini' => [
        ['id' => 'gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash', 'free' => true],
        ['id' => 'gemini-2.5-pro', 'name' => 'Gemini 2.5 Pro', 'free' => false],
        ['id' => 'gemini-2.0-flash', 'name' => 'Gemini 2.0 Flash', 'free' => false],
    ],
    'openrouter' => [
        ['id' => 'allenai/molmo-2-8b:free', 'name' => 'molmo', 'free' => true],
        ['id' => 'meta-llama/llama-3.2-3b-instruct:free', 'name' => 'Llama 3.2 3B (Free)', 'free' => true],
        ['id' => 'anthropic/claude-3.5-sonnet', 'name' => 'Claude 3.5 Sonnet', 'free' => false],
        ['id' => 'openai/gpt-4o', 'name' => 'GPT-4o', 'free' => false],
    ],
    'openai' => [
        ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o Mini', 'free' => false],
        ['id' => 'gpt-4o', 'name' => 'GPT-4o', 'free' => false],
    ],
    'ollama' => [
        ['id' => 'llama3', 'name' => 'Llama 3', 'free' => true],
        ['id' => 'mistral', 'name' => 'Mistral', 'free' => true],
        ['id' => 'codellama', 'name' => 'CodeLlama', 'free' => true],
    ],
    'anthropic' => [
        ['id' => 'claude-3-5-sonnet-20240620', 'name' => 'Claude 3.5 Sonnet', 'free' => false],
        ['id' => 'claude-3-opus-20240229', 'name' => 'Claude 3 Opus', 'free' => false],
        ['id' => 'claude-3-haiku-20240307', 'name' => 'Claude 3 Haiku', 'free' => false],
    ]
];

// Input Handling
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true) ?? [];
$action = $_POST['action'] ?? ($_GET['action'] ?? ($jsonInput['action'] ?? ''));

if (!$action) {
    echo json_encode(['status' => 'error', 'message' => 'Action required']);
    exit;
}

/* ======================================================
   ROUTING
   ====================================================== */

try {
    // Init Core & DB
    $ai = new AiCore($db);
    $ai->ensureTables();

    // Register Tools
    $ai->registerTool('list_files_by_size', function($args) {
        $files = glob(ROOT_DIR . '/*.*');
        usort($files, function($a, $b) { return filesize($b) - filesize($a); });
        return array_slice($files, 0, 5);
    });

    $ai->registerTool('search_in_files', function($args) {
        $query = $args['query'] ?? '';
        if (empty($query)) return [];
        $results = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT_DIR, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['php', 'js', 'css'])) {
                $content = file_get_contents($file->getPathname());
                if (strpos($content, $query) !== false) {
                    $results[] = str_replace(ROOT_DIR, '', $file->getPathname());
                }
            }
            if (count($results) > 10) break;
        }
        return $results;
    });

    $ai->registerTool('get_db_stats', function($args) use ($db) {
        $stats = [];
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $t) $stats[$t] = "N/A (SQLite)";
        } else {
            $res = $db->query("SELECT TABLE_NAME, ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS SIZE_MB FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
            foreach ($res as $row) $stats[$row['TABLE_NAME']] = $row['SIZE_MB'] . " MB";
        }
        return $stats;
    });

    switch ($action) {
        case 'get_settings':
            $dbProviders = $ai->getProviders();
            $dbPersonas = $ai->getPersonas();

            // Default Personas Seed
            if (empty($dbPersonas)) {
                $defaults = [
                    ['key' => 'general', 'name' => 'Genel Asistan', 'prompt' => 'Oyunun kuralı: Kibar, teknik ve çözüm odaklı ol.'],
                    ['key' => 'sql_expert', 'name' => 'SQL Uzmanı', 'prompt' => 'Sen bir veritabanı mimarısın. Sorguları optimize et ve şemaya sadık kal.'],
                    ['key' => 'frontend_wizard', 'name' => 'Frontend Sihirbazı', 'prompt' => 'Modern, estetik ve responsive CSS/JS çözümleri üret.'],
                ];
                foreach ($defaults as $d) {
                    $db->prepare("INSERT INTO ai_personas (persona_key, persona_name, system_prompt) VALUES (?, ?, ?)")->execute([$d['key'], $d['name'], $d['prompt']]);
                }
                $dbPersonas = $ai->getPersonas();
            }

            $providersMap = [];

            // 1. Load from DB
            foreach ($dbProviders as $p) {
                $p['models'] = json_decode($p['models'] ?? '[]', true);
                // Fallback to default models if DB models are empty
                if (empty($p['models']) && isset($defaultModels[$p['provider_key']])) {
                    $p['models'] = $defaultModels[$p['provider_key']];
                }
                $providersMap[$p['provider_key']] = $p;
            }

            // 2. Merge Defaults if missing
            foreach ($defaultModels as $key => $models) {
                if (!isset($providersMap[$key])) {
                    $providerName = match ($key) {
                        'gemini' => 'Google Gemini',
                        'openrouter' => 'OpenRouter',
                        'openai' => 'OpenAI',
                        default => ucfirst($key)
                    };
                    $providersMap[$key] = [
                        'provider_key' => $key,
                        'provider_name' => $providerName,
                        'api_key' => '',
                        'is_enabled' => ($key === 'gemini' ? 1 : 0),
                        'models' => $models
                    ];
                }
            }

            $providers = array_values($providersMap);

            echo json_encode([
                'status' => 'success',
                'providers' => $providers,
                'personas' => $dbPersonas
            ]);
            break;

        case 'save_provider':
            $key = $_POST['provider_key'] ?? '';
            $name = $_POST['provider_name'] ?? ucfirst($key);
            $apiKey = $_POST['api_key'] ?? '';
            $enabled = (bool) ($_POST['is_enabled'] ?? 0);

            // Preserve models if not sent, or accept new list
            $existing = $ai->getProvider($key);
            $models = $existing ? json_decode($existing['models'], true) : ($defaultModels[$key] ?? []);

            // If models sent via JSON (e.g. from a manage models modal)
            if (isset($_POST['models'])) {
                $models = json_decode($_POST['models'], true);
            }

            $ai->saveProvider($key, $name, $apiKey, $models, $enabled);
            echo json_encode(['status' => 'success', 'message' => function_exists('__') ? __('settings_saved') : 'Saved']);
            break;

        case 'chat':
            $prompt = $jsonInput['prompt'] ?? '';
            $providerKey = $jsonInput['provider'] ?? 'gemini';
            $model = trim((string)($jsonInput['model'] ?? ''));
            $files = $jsonInput['files'] ?? [];
            $stream = (bool)($jsonInput['stream'] ?? false);

            // Model fallback: if frontend sends empty model, use provider's first configured model.
            if ($model === '') {
                $providerConfig = $ai->getProvider($providerKey);
                $providerModels = [];
                if ($providerConfig && !empty($providerConfig['models'])) {
                    $providerModels = json_decode((string)$providerConfig['models'], true) ?: [];
                }
                if (empty($providerModels) && isset($defaultModels[$providerKey])) {
                    $providerModels = $defaultModels[$providerKey];
                }
                if (!empty($providerModels[0]['id'])) {
                    $model = (string)$providerModels[0]['id'];
                } else {
                    $model = 'gemini-2.5-flash';
                }
            }

            // Context Building
            $sysSnapshot = get_system_snapshot($db);
            $fileCtx = "";
            foreach ($files as $f) {
                $fileCtx .= read_relevant_code($f);
                
                $deps = $ai->detectDependencies(ROOT_DIR . $f);
                if (!empty($deps)) {
                    $fileCtx .= "\n\nDEPENDENCIES FOR $f:\n";
                    foreach ($deps as $dep) {
                        $depPath = ROOT_DIR . $dep;
                        if (file_exists($depPath)) {
                            $fileCtx .= "- $dep: (Found in project)\n";
                        }
                    }
                }
            }

            // RAG: Dynamic Context Fetching (if prompt implies searching)
            if (strpos(strtolower($prompt), 'bul') !== false || strpos(strtolower($prompt), 'search') !== false) {
                // Heuristic: Extract potential keywords (very basic)
                $keywords = array_filter(explode(' ', $prompt), function($w) { return strlen($w) > 4; });
                if (!empty($keywords)) {
                    $fileCtx .= "\n\nAUTO-FETCHED CONTEXT:\n" . $ai->fetchContext(ROOT_DIR, $keywords);
                }
            }

            // Check for error context
            $errorKeywords = ['hata', 'error', 'bug', 'fix', 'sorun', 'çalışmıyor', 'patladı'];
            $isError = false;
            foreach ($errorKeywords as $kw) {
                if (stripos($prompt, $kw) !== false) $isError = true;
            }
            if ($isError) {
                $snap = json_decode($sysSnapshot, true);
                if (!empty($snap['recent_errors'])) {
                    $fileCtx .= "\n\nRECENT ERRORS:\n" . json_encode($snap['recent_errors']);
                }
            }

            $personaKey = $jsonInput['persona'] ?? 'default';
            $persona = $ai->getPersona($personaKey);
            $baseSystemPrompt = $persona ? $persona['system_prompt'] : "You are SpeedPage Admin Assistant. Help the admin with coding, debugging and system management. Reply in Turkish.";

            $systemPrompt = $baseSystemPrompt . "\n\n" .
                "MANDATORY PROCESS:\n" .
                "1. PLAN: First, describe your plan and what you are going to do.\n" .
                "2. EXECUTE: Write the code changes using the [PATCH] structure.\n" .
                "3. VERIFY: Briefly explain why this change is safe.\n\n" .
                "CODE CHANGE FORMAT (IMPORTANT):\n" .
                "If proposing code changes, YOU MUST USE THE FOLLOWING FORMAT EXACTLY:\n" .
                "1. Explain the change.\n" .
                "2. Use this block structure:\n" .
                "[PATCH: path/to/file.php]\n" .
                "[OLD]\n" .
                "(Exact block of code to be replaced)\n" .
                "[/OLD]\n" .
                "[NEW]\n" .
                "(New code block)\n" .
                "[/NEW]\n\n" .
                "SYSTEM STATUS SNAPSHOT:\n```json\n" . $sysSnapshot . "\n```";

            if ($stream) {
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');
                header('X-Accel-Buffering: no'); // For Nginx
                
                if (ob_get_level()) ob_end_clean();
                
                $fullResponse = "";
                $chatReturn = $ai->chat($providerKey, $model, $prompt, $systemPrompt, $fileCtx, function($chunk) use (&$fullResponse) {
                    // Extract content from OpenAI/OpenRouter stream format (data: ...)
                    if (strpos($chunk, 'data: ') !== false) {
                        $lines = explode("\n", $chunk);
                        foreach ($lines as $line) {
                            if (strpos($line, 'data: ') === 0) {
                                $data = substr($line, 6);
                                if ($data === '[DONE]') break;
                                $json = json_decode($data, true);
                                $content = '';
                                if (isset($json['choices'][0]['delta']['content'])) {
                                    $content = $json['choices'][0]['delta']['content']; // OpenAI / OpenRouter
                                } elseif (isset($json['message']['content'])) {
                                    $content = $json['message']['content']; // Ollama
                                } elseif (isset($json['delta']['text'])) {
                                    $content = $json['delta']['text']; // Anthropic
                                }
                                
                                if ($content) {
                                    $fullResponse .= $content;
                                    echo "data: " . json_encode(['content' => $content]) . "\n\n";
                                    flush();
                                }
                            }
                        }
                    }
                });

                // Fallback for providers/endpoints that return non-SSE content on stream requests.
                if ($fullResponse === '' && is_string($chatReturn) && $chatReturn !== '') {
                    $fullResponse = $chatReturn;
                    echo "data: " . json_encode(['content' => $chatReturn]) . "\n\n";
                    flush();
                }

                // Finalize Log
                if (isset($_SESSION['user_id'])) {
                    $inTokens = $ai->estimateTokens($prompt . $systemPrompt . $fileCtx);
                    $outTokens = $ai->estimateTokens($fullResponse);
                    $ai->logAction((int)$_SESSION['user_id'], $providerKey, $model, 'chat', $prompt, $fullResponse, $inTokens, $outTokens);
                }
                exit;
            } else {
                $response = $ai->chat($providerKey, $model, $prompt, $systemPrompt, $fileCtx);
                if (isset($_SESSION['user_id'])) {
                    $inTokens = $ai->estimateTokens($prompt . $systemPrompt . $fileCtx);
                    $outTokens = $ai->estimateTokens($response);
                    $ai->logAction((int)$_SESSION['user_id'], $providerKey, $model, 'chat', $prompt, $response, $inTokens, $outTokens);
                    $lastId = $db->lastInsertId();
                }
                echo json_encode(['status' => 'success', 'content' => $response, 'log_id' => $lastId ?? 0]);
            }
            break;

        case 'apply_ai_patch':
            check_csrf(); // Ensure CSRF check is here too
            handleApplyPatch($db, $jsonInput);
            break;

        case 'list_files':
            handleListFiles();
            break;

        case 'get_schema':
            $schema = $ai->getDatabaseSchema();
            echo json_encode(['status' => 'success', 'schema' => $schema]);
            break;

        case 'index_project':
            $structure = $ai->generateProjectStructure(ROOT_DIR);
            echo json_encode(['status' => 'success', 'message' => 'Project indexed', 'file_count' => count($structure['files'])]);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }

} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $uid = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
            $msg = 'AI Sistem Error [' . ($action ?? 'unknown') . ']: ' . $e->getMessage();
            $db->prepare("INSERT INTO logs (user_id, action_type, message, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)")
               ->execute([$uid, 'system_error', $msg, $ip, $ua]);
        } catch (Throwable $ignore) {
            // no-op
        }
    }

    if (ob_get_length())
        ob_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
exit;


/* ---------------- HELPERS ---------------- */

function handleApplyPatch(PDO $db, array $jsonInput): void
{
    $filePath = $_POST['file_path'] ?? ($jsonInput['file_path'] ?? '');
    $oldCode = $_POST['old_code'] ?? ($jsonInput['old_code'] ?? '');
    $newCode = $_POST['new_code'] ?? ($jsonInput['new_code'] ?? '');

    if (!$filePath || !$newCode) {
        throw new Exception(function_exists('__') ? __('missing_parameters') : 'Missing Params');
    }

    $fullPath = realpath(ROOT_DIR . $filePath);
    if (!$fullPath || strpos($fullPath, realpath(ROOT_DIR)) !== 0) {
        throw new Exception(function_exists('__') ? __('file_access_denied') : 'Access Denied: Path Traversal');
    }

    if (!file_exists($fullPath)) {
        throw new Exception(function_exists('__') ? __('file_not_found') : 'File not found');
    }

    // Patch Logic
    $currentContent = file_get_contents($fullPath);
    if ($oldCode) {
        $normCurrent = str_replace("\r\n", "\n", $currentContent);
        $normOld = str_replace("\r\n", "\n", $oldCode);
        $normNew = str_replace("\r\n", "\n", $newCode);

        // Normalize spacing for better fuzzy match
        $trimOld = preg_replace('/\s+/', ' ', $normOld);
        $trimNew = preg_replace('/\s+/', ' ', $normNew);
        if (trim((string)$trimOld) === trim((string)$trimNew)) {
            throw new Exception('No-op patch: old and new code are identical.');
        }

        if (strpos($normCurrent, $normOld) === false) {
            if (strpos($normCurrent, trim($normOld)) !== false) {
                $normOld = trim($normOld);
            } else {
                throw new Exception('Patch Failed: Old code block not found exactly. Try manual edit.');
            }
        }
        $finalContent = str_replace($normOld, $normNew, $normCurrent);
    } else {
        throw new Exception('Safety Block: Old code context missing.');
    }

    if ($finalContent === $currentContent) {
        throw new Exception('No-op patch: file content unchanged.');
    }

    // Syntax Check
    if (!check_syntax($finalContent, $error)) {
        throw new Exception('Syntax Error: ' . $error);
    }

    // Backup
    $backupDir = STORAGE_DIR . 'ai_backups/';
    if (!is_dir($backupDir))
        mkdir($backupDir, 0755, true);
    $backupFile = $backupDir . basename($filePath) . '.bak_' . date('Y-m-d_H-i-s');
    if (!@copy($fullPath, $backupFile)) {
        throw new Exception('Backup write failed: cannot create backup file.');
    }

    $originalModeRaw = @fileperms($fullPath);
    $originalMode = ($originalModeRaw !== false) ? ($originalModeRaw & 0777) : null;
    $temporaryModeApplied = false;
    $restorePermError = null;

    try {
        if (!is_writable($fullPath)) {
            if ($originalMode === null) {
                throw new Exception('Patch write failed: cannot read current file permissions.');
            }

            // Temporary elevation: keep existing bits, only add owner/group write.
            $temporaryMode = $originalMode | 0200 | 0020;
            if (!@chmod($fullPath, $temporaryMode)) {
                throw new Exception('Patch write failed: target file is not writable and temporary chmod failed.');
            }

            clearstatcache(true, $fullPath);
            if (!is_writable($fullPath)) {
                throw new Exception('Patch write failed: target file still not writable after temporary chmod.');
            }
            $temporaryModeApplied = true;
        }

        $writeRes = @file_put_contents($fullPath, $finalContent, LOCK_EX);
        if ($writeRes === false) {
            throw new Exception('Patch write failed: file_put_contents returned false.');
        }
    } finally {
        if ($temporaryModeApplied && $originalMode !== null) {
            if (!@chmod($fullPath, $originalMode)) {
                $restorePermError = 'Patch applied but failed to restore original file permissions.';
            } else {
                clearstatcache(true, $fullPath);
            }
        }
    }

    if ($restorePermError !== null) {
        throw new Exception($restorePermError);
    }

    echo json_encode(['status' => 'success', 'message' => 'Patch applied', 'backup_path' => $backupFile]);
    cleanup_backups($backupDir);
}

function handleListFiles(): void
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(ROOT_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $allowed = ['php', 'js', 'css', 'sql', 'html', 'json', 'md'];

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            if (in_array($file->getExtension(), $allowed)) {
                $path = $file->getPathname();
                if (strpos($path, '.git') === false && strpos($path, 'node_modules') === false && strpos($path, 'vendor') === false) {
                    $files[] = str_replace([ROOT_DIR, '\\'], ['', '/'], $path);
                }
            }
        }
    }
    echo json_encode(['status' => 'success', 'files' => array_values($files)]);
}

function get_system_snapshot(PDO $db): string
{
    $snap = ['info' => 'Snapshot'];
    try {
        $stmt = $db->query("SELECT id, message, old_data, created_at FROM logs WHERE action_type='system_error' ORDER BY id DESC LIMIT 5");
        $snap['recent_errors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
    }
    return json_encode($snap);
}

function read_relevant_code(string $file): string
{
    $root = realpath(ROOT_DIR);
    $full = realpath($root . '/' . ltrim($file, '/'));
    
    if (!$full || strpos($full, $root) !== 0 || !file_exists($full)) {
        return "[File Not Found or Access Denied: $file]";
    }

    $content = file_get_contents($full);
    // Large File Handling
    if (strlen($content) > 30000) {
        return "\n--- FILE: $file (Truncated) ---\n" . substr($content, 0, 2000) . "\n... [Large file] ...";
    }
    return "\n--- FILE: $file ---\n" . $content;
}

function check_syntax(string $code, &$error): bool
{
    // --- 1. DANGEROUS FUNCTIONS CHECK ---
    $dangerous = ['exec', 'shell_exec', 'system', 'passthru', 'eval', 'proc_open', 'popen', 'base64_decode', 'assert'];
    foreach ($dangerous as $func) {
        if (preg_match('/\b' . $func . '\s*\(/i', $code)) {
            $error = "Dangerous function detected: $func. AI patches cannot use system execution functions for security.";
            return false;
        }
    }

    // --- 2. PHP LINT CHECK ---
    if (function_exists('exec')) {
        $tmp = tempnam(sys_get_temp_dir(), 'php_lint');
        if (!$tmp) return true; 
        file_put_contents($tmp, $code);

        $phpPath = 'php';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            if (!@exec("php -v")) {
                if (file_exists('C:\\xampp\\php\\php.exe')) {
                    $phpPath = 'C:\\xampp\\php\\php.exe';
                }
            }
        }

        exec(escapeshellarg($phpPath) . " -l " . escapeshellarg($tmp) . " 2>&1", $out, $ret);
        unlink($tmp);

        if ($ret !== 0) {
            $error = implode("\n", $out);
            return false;
        }
    }
    return true;
}

function cleanup_backups(string $dir): void
{
    $files = glob($dir . '*');
    $now = time();
    foreach ($files as $f) {
        if (is_file($f) && ($now - filemtime($f) >= 604800))
            @unlink($f);
    }
}
