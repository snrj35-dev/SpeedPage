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

    switch ($action) {
        case 'get_settings':
            $dbProviders = $ai->getProviders();
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
                    $providersMap[$key] = [
                        'provider_key' => $key,
                        'provider_name' => ($key === 'gemini' ? 'Google Gemini' : ($key === 'openrouter' ? 'OpenRouter' : ucfirst($key))),
                        'api_key' => '',
                        'is_enabled' => ($key === 'gemini' ? 1 : 0),
                        'models' => $models
                    ];
                }
            }

            $providers = array_values($providersMap);

            echo json_encode([
                'status' => 'success',
                'providers' => $providers
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
            $model = $jsonInput['model'] ?? 'gemini-1.5-flash';
            $files = $jsonInput['files'] ?? [];

            // Context Building
            $sysSnapshot = get_system_snapshot($db);
            $fileCtx = "";
            foreach ($files as $f) {
                $fileCtx .= read_relevant_code($f);
            }

            // Check for error context in prompt
            $errorKeywords = ['hata', 'error', 'bug', 'fix', 'sorun', 'çalışmıyor', 'patladı'];
            $isError = false;
            foreach ($errorKeywords as $kw) {
                if (stripos($prompt, $kw) !== false)
                    $isError = true;
            }
            if ($isError) {
                $snap = json_decode($sysSnapshot, true);
                if (!empty($snap['recent_errors'])) {
                    $fileCtx .= "\n\nRECENT ERRORS:\n" . json_encode($snap['recent_errors']);
                }
            }

            $systemPrompt = "You are SpeedPage Admin Assistant. Help the admin with coding, debugging and system management. Reply in Turkish.\n\n" .
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

            $response = $ai->chat($providerKey, $model, $prompt, $systemPrompt, $fileCtx);

            // Log
            if (isset($_SESSION['user_id'])) {
                $stmt = $db->prepare("INSERT INTO ai_logs (user_id, action_type, prompt, response) VALUES (?, 'chat', ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $prompt, $response]);
                $lastId = $db->lastInsertId();
            }

            echo json_encode(['status' => 'success', 'content' => $response, 'log_id' => $lastId ?? 0]);
            break;

        case 'apply_ai_patch':
            handleApplyPatch($db, $jsonInput);
            break;

        case 'list_files':
            handleListFiles();
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }

} catch (Throwable $e) {
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

    $fullPath = ROOT_DIR . $filePath;
    if (!file_exists($fullPath)) {
        throw new Exception(function_exists('__') ? __('file_not_found') : 'File not found');
    }

    // Backup
    $backupDir = __DIR__ . '/_backups/';
    if (!is_dir($backupDir))
        mkdir($backupDir, 0755, true);
    $backupFile = $backupDir . basename($filePath) . '.bak_' . date('Y-m-d_H-i-s');
    copy($fullPath, $backupFile);

    // Patch Logic
    $currentContent = file_get_contents($fullPath);
    if ($oldCode) {
        $normCurrent = str_replace("\r\n", "\n", $currentContent);
        $normOld = str_replace("\r\n", "\n", $oldCode);

        // Normalize spacing for better fuzzy match
        $trimCurrent = preg_replace('/\s+/', ' ', $normCurrent);
        $trimOld = preg_replace('/\s+/', ' ', $normOld);

        if (strpos($normCurrent, $normOld) === false) {
            if (strpos($normCurrent, trim($normOld)) !== false) {
                $normOld = trim($normOld);
            } else {
                throw new Exception('Patch Failed: Old code block not found exactly. Try manual edit.');
            }
        }
        $finalContent = str_replace($normOld, str_replace("\r\n", "\n", $newCode), $normCurrent);
    } else {
        throw new Exception('Safety Block: Old code context missing.');
    }

    // Syntax Check
    if (!check_syntax($finalContent, $error)) {
        throw new Exception('Syntax Error: ' . $error);
    }

    file_put_contents($fullPath, $finalContent);

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
    $full = ROOT_DIR . $file;
    if (!file_exists($full))
        return "[File Not Found: $file]";

    $content = file_get_contents($full);
    // Large File Handling
    if (strlen($content) > 30000) {
        return "\n--- FILE: $file (Truncated) ---\n" . substr($content, 0, 2000) . "\n... [Large file] ...";
    }
    return "\n--- FILE: $file ---\n" . $content;
}

function check_syntax(string $code, &$error): bool
{
    if (function_exists('exec')) {
        $tmp = tempnam(sys_get_temp_dir(), 'php_lint');
        file_put_contents($tmp, $code);
        exec("php -l " . escapeshellarg($tmp) . " 2>&1", $out, $ret);
        unlink($tmp);
        if ($ret !== 0) {
            $error = implode("\n", $out);
            return false;
        }
        return true;
    }
    return true; // Fallback
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
