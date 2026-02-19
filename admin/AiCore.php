<?php
declare(strict_types=1);

class AiCore
{
    private PDO $db;
    private array $tools = [];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Register a tool (function)
     */
    public function registerTool(string $name, callable $callback, array $params = []): void
    {
        $this->tools[$name] = [
            'callback' => $callback,
            'params' => $params
        ];
    }

    /**
     * Execute a registered tool
     */
    public function callTool(string $name, array $args): mixed
    {
        if (!isset($this->tools[$name])) {
            throw new Exception("Tool not found: $name");
        }
        return call_user_func($this->tools[$name]['callback'], $args);
    }

    /**
     * Get all configured providers
     */
    public function getProviders(): array
    {
        try {
            // Check if table exists first to avoid errors during setup phase
            $stmt = $this->db->query("SELECT * FROM ai_providers ORDER BY is_enabled DESC, provider_name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get a specific provider settings
     */
    public function getProvider(string $provider): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM ai_providers WHERE provider_key = ?");
        $stmt->execute([$provider]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Save or Update a provider
     */
    public function saveProvider(string $key, string $name, string $apiKey, array $models, bool $isEnabled): void
    {
        $modelsJson = json_encode($models, JSON_UNESCAPED_UNICODE);

        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "INSERT OR REPLACE INTO ai_providers (provider_key, provider_name, api_key, models, is_enabled) VALUES (?, ?, ?, ?, ?)";
        } else {
            $sql = "INSERT INTO ai_providers (provider_key, provider_name, api_key, models, is_enabled) VALUES (?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE provider_name=VALUES(provider_name), api_key=VALUES(api_key), models=VALUES(models), is_enabled=VALUES(is_enabled)";
        }

        $this->db->prepare($sql)->execute([$key, $name, $apiKey, $modelsJson, $isEnabled ? 1 : 0]);
    }

    /**
     * Send prompt to the selected provider
     */
    public function chat(string $providerKey, string $model, string $prompt, string $systemPrompt, string $context = '', ?callable $onChunk = null): string
    {
        $provider = $this->getProvider($providerKey);
        if (!$provider) {
            throw new Exception(__("ai_provider_not_found"));
        }

        if (empty($provider['api_key'])) {
            throw new Exception(__("ai_key_missing") . " ($providerKey)");
        }

        $fullSystemUserPrompt = "Role: $systemPrompt\n\nContext:\n$context";

        return match ($providerKey) {
            'gemini' => $this->callGemini($provider['api_key'], $model, $prompt, $fullSystemUserPrompt),
            'openrouter' => $this->callOpenRouter($provider['api_key'], $model, $prompt, $fullSystemUserPrompt, $onChunk),
            'openai' => $this->callOpenAI($provider['api_key'], $model, $prompt, $fullSystemUserPrompt, $onChunk),
            'ollama' => $this->callOllama($provider['api_key'], $model, $prompt, $fullSystemUserPrompt, $onChunk),
            'anthropic' => $this->callAnthropic($provider['api_key'], $model, $prompt, $fullSystemUserPrompt, $onChunk),
            default => throw new Exception(__("ai_provider_unsupported"))
        };
    }

    public function callAnthropic(string $apiKey, string $model, string $userPrompt, string $systemInfo, ?callable $onChunk = null): string
    {
        $url = "https://api.anthropic.com/v1/messages";

        $data = [
            "model" => $model,
            "max_tokens" => 4096,
            "messages" => [
                ["role" => "user", "content" => $userPrompt]
            ],
            "system" => $systemInfo,
            "stream" => $onChunk ? true : false
        ];

        // Anthropic requires specific headers
        $customHeaders = [
            "x-api-key: $apiKey",
            "anthropic-version: 2023-06-01"
        ];

        $response = $this->curlRequest($url, $data, 'anthropic', $apiKey, $onChunk, $customHeaders);
        if ($onChunk) return '';

        $json = json_decode($response, true);
        return $json['content'][0]['text'] ?? throw new Exception("Anthropic empty response");
    }

    public function callOllama(string $apiUrl, string $model, string $userPrompt, string $systemInfo, ?callable $onChunk = null): string
    {
        $url = $apiUrl ?: "http://localhost:11434/api/chat";

        $data = [
            "model" => $model,
            "stream" => $onChunk ? true : false,
            "messages" => [
                ["role" => "system", "content" => $systemInfo],
                ["role" => "user", "content" => $userPrompt]
            ]
        ];

        $response = $this->curlRequest($url, $data, 'ollama', '', $onChunk);
        if ($onChunk) return '';

        $json = json_decode($response, true);
        return $json['message']['content'] ?? '';
    }

    private function callGemini(string $apiKey, string $model, string $userPrompt, string $systemInfo): string
    {
        // Gemini API usually puts system instructions differently or we just merge them.
        // Simple approach: Merge into one prompt parts

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;

        $finalPrompt = $systemInfo . "\n\nUser Instruction:\n" . $userPrompt;

        $data = [
            "contents" => [
                ["parts" => [["text" => $finalPrompt]]]
            ]
        ];

        $response = $this->curlRequest($url, $data, 'gemini', $apiKey);

        $json = json_decode($response, true);

        if (isset($json['error'])) {
            throw new Exception("Gemini API Error: " . ($json['error']['message'] ?? 'Unknown'));
        }

        return $json['candidates'][0]['content']['parts'][0]['text'] ?? throw new Exception("Gemini empty response");
    }

    public function callOpenRouter(string $apiKey, string $model, string $userPrompt, string $systemInfo, ?callable $onChunk = null): string
    {
        $url = "https://openrouter.ai/api/v1/chat/completions";

        $data = [
            "model" => $model,
            "stream" => $onChunk ? true : false,
            "messages" => [
                ["role" => "system", "content" => $systemInfo],
                ["role" => "user", "content" => $userPrompt]
            ]
        ];

        $response = $this->curlRequest($url, $data, 'openrouter', $apiKey, $onChunk);
        if ($onChunk) return '';

        $json = json_decode($response, true);
        if (isset($json['error'])) {
            throw new Exception("OpenRouter Error: " . ($json['error']['message'] ?? 'Unknown'));
        }

        return $json['choices'][0]['message']['content'] ?? throw new Exception("OpenRouter empty response");
    }

    public function callOpenAI(string $apiKey, string $model, string $userPrompt, string $systemInfo, ?callable $onChunk = null): string
    {
        $url = "https://api.openai.com/v1/chat/completions";
        $effectiveOnChunk = $onChunk;

        $data = [
            "model" => $model,
            "stream" => ($effectiveOnChunk !== null),
            "messages" => [
                ["role" => "system", "content" => $systemInfo],
                ["role" => "user", "content" => $userPrompt]
            ]
        ];

        $response = $this->curlRequest($url, $data, 'openai', $apiKey, $effectiveOnChunk);
        if ($effectiveOnChunk !== null) return '';

        $json = json_decode($response, true);
        if (is_array($json) && isset($json['error'])) {
            throw new Exception("OpenAI Error: " . ($json['error']['message'] ?? 'Unknown'));
        }

        $content = '';
        if (is_array($json)) {
            $content = (string) (
                $json['choices'][0]['message']['content']
                ?? $json['choices'][0]['delta']['content']
                ?? $json['choices'][0]['text']
                ?? $json['message']['content']
                ?? $json['output_text']
                ?? ''
            );
        }

        if ($content !== '') {
            return $content;
        }

        // Some local proxies may return plain text or non-standard JSON.
        $raw = trim($response);
        if ($raw !== '') {
            return $raw;
        }

        throw new Exception("OpenAI empty response");
    }

    private function curlRequest(string $url, array $data, string $type, string $apiKey, ?callable $onChunk = null, array $customHeaders = []): string
    {
        $ch = curl_init($url);
        $headers = ["Content-Type: application/json"];

        if ($type === 'gemini') {
            // API key is in URL for Gemini usually
        } elseif ($type === 'anthropic') {
            $headers = array_merge($headers, $customHeaders);
        } else {
            if ($apiKey) $headers[] = "Authorization: Bearer $apiKey";
        }

        if ($type === 'openrouter') {
            $headers[] = "HTTP-Referer: https://speedpage.local";
            $headers[] = "X-Title: SpeedPage Admin";
        }

        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false
        ];

        if ($onChunk) {
            $options[CURLOPT_RETURNTRANSFER] = false;
            $options[CURLOPT_WRITEFUNCTION] = function($ch, $chunk) use ($onChunk) {
                $onChunk($chunk);
                return strlen($chunk);
            };
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new Exception("CURL Error: $err");
        }

        if (is_string($response)) {
            return $response;
        }

        // In streamed mode curl_exec commonly returns true; no full body is available.
        return '';
    }

    /**
     * Get all personas
     */
    public function getPersonas(): array
    {
        try {
            $stmt = $this->db->query("SELECT * FROM ai_personas ORDER BY persona_name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get a specific persona
     */
    public function getPersona(string $key): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM ai_personas WHERE persona_key = ?");
        $stmt->execute([$key]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Scan project and generate structure JSON
     */
    public function generateProjectStructure(string $rootDir): array
    {
        $structure = [
            'last_updated' => date('Y-m-d H:i:s'),
            'files' => []
        ];

        $directory = new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($directory);

        $excludeDirs = ['.git', 'node_modules', 'vendor', '_internal_storage'];
        $allowedExts = ['php', 'js', 'css', 'sql', 'html', 'json', 'md'];

        foreach ($iterator as $info) {
            $path = $info->getPathname();
            $relativePath = str_replace([$rootDir, '\\'], ['', '/'], $path);
            $relativePath = ltrim($relativePath, '/');

            // Skip excluded dirs
            $skip = false;
            foreach ($excludeDirs as $ex) {
                if (strpos($relativePath, $ex) !== false) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            if ($info->isFile() && in_array($info->getExtension(), $allowedExts)) {
                $fileData = [
                    'path' => $relativePath,
                    'size' => $info->getSize(),
                    'classes' => [],
                    'methods' => []
                ];

                // Simple regex-based analysis for PHP files
                if ($info->getExtension() === 'php') {
                    $content = file_get_contents($path);
                    if (preg_match_all('/class\s+([a-zA-Z0-9_]+)/', $content, $matches)) {
                        $fileData['classes'] = $matches[1];
                    }
                    if (preg_match_all('/function\s+([a-zA-Z0-9_]+)\s*\(/', $content, $matches)) {
                        $fileData['methods'] = $matches[1];
                    }
                }

                $structure['files'][] = $fileData;
            }
        }

        $jsonPath = $rootDir . '/project_structure.json';
        file_put_contents($jsonPath, json_encode($structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $structure;
    }

    /**
     * Get database schema metadata
     */
    public function getDatabaseSchema(): array
    {
        $schema = [];
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $tables = $this->db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $columns = $this->db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
                $schema[$table] = $columns;
            }
        } else {
            $tables = $this->db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $columns = $this->db->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
                $schema[$table] = $columns;
            }
        }

        return $schema;
    }

    /**
     * Detect require/include dependencies in a file
     */
    public function detectDependencies(string $filePath): array
    {
        if (!file_exists($filePath)) return [];
        $content = file_get_contents($filePath);
        $deps = [];

        // Simple regex for require/include
        if (preg_match_all('/(?:require|include)(?:_once)?\s*[\'"](.*?)[\'"]/', $content, $matches)) {
            foreach ($matches[1] as $dep) {
                // Basic normalization (very limited, project specific)
                $dep = str_replace(['__DIR__', 'ROOT_DIR', '.', '/../'], '', $dep);
                $dep = ltrim($dep, '/\\');
                if ($dep) $deps[] = $dep;
            }
        }
        return array_unique($deps);
    }

    /**
     * Fetch context from multiple files or search project
     */
    public function fetchContext(string $rootDir, array $queries): string
    {
        $context = "";
        $allFiles = [];
        
        // 1. Load project structure if exists
        $structurePath = $rootDir . '/project_structure.json';
        if (file_exists($structurePath)) {
            $structure = json_decode(file_get_contents($structurePath), true);
            $allFiles = $structure['files'] ?? [];
        }

        foreach ($queries as $query) {
            $query = strtolower(trim($query));
            if (empty($query)) continue;

            $foundCount = 0;
            foreach ($allFiles as $file) {
                $path = $file['path'];
                // Search in path, classes, or methods
                $match = (strpos(strtolower($path), $query) !== false);
                if (!$match && !empty($file['classes'])) {
                    foreach ($file['classes'] as $c) if (strpos(strtolower($c), $query) !== false) $match = true;
                }
                
                if ($match) {
                    $fullPath = $rootDir . '/' . $path;
                    if (file_exists($fullPath)) {
                        $content = file_get_contents($fullPath);
                        // Truncate if too large
                        if (strlen($content) > 10000) $content = substr($content, 0, 10000) . "\n... [Truncated]";
                        $context .= "\n--- CONTEXT FILE: $path ---\n" . $content . "\n";
                        $foundCount++;
                    }
                }
                if ($foundCount > 5) break; // Limit per query to avoid context explosion
            }
        }

        return $context;
    }

    /**
     * Roughly estimate tokens (4 chars ~= 1 token)
     */
    public function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Log an AI action
     */
    public function logAction(int $userId, string $provider, string $model, string $actionType, string $prompt, string $response, int $inputTokens = 0, int $outputTokens = 0, string $filePath = ''): void
    {
        $sql = "INSERT INTO ai_logs (user_id, provider_key, model_id, action_type, prompt, response, input_tokens, output_tokens, file_path) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $this->db->prepare($sql)->execute([$userId, $provider, $model, $actionType, $prompt, $response, $inputTokens, $outputTokens, $filePath]);
    }

    public function ensureTables(): void
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $isSqlite = ($driver === 'sqlite');

        // New Table: ai_providers
        if ($isSqlite) {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS ai_providers (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    provider_key TEXT UNIQUE,
                    provider_name TEXT,
                    api_key TEXT,
                    models TEXT, -- JSON
                    is_enabled INTEGER DEFAULT 1
                );
            ");
        } else {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS ai_providers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    provider_key VARCHAR(50) UNIQUE,
                    provider_name VARCHAR(100),
                    api_key TEXT,
                    models LONGTEXT,
                    is_enabled TINYINT(1) DEFAULT 1
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        }

        // New Table: ai_personas
        if ($isSqlite) {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS ai_personas (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    persona_key TEXT UNIQUE,
                    persona_name TEXT,
                    system_prompt TEXT,
                    is_default INTEGER DEFAULT 0
                );
            ");
        } else {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS ai_personas (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    persona_key VARCHAR(50) UNIQUE,
                    persona_name VARCHAR(100),
                    system_prompt TEXT,
                    is_default TINYINT(1) DEFAULT 0
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        }

        // Logs table - Enhanced with tokens
        if ($isSqlite) {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS ai_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER,
                    provider_key TEXT,
                    model_id TEXT,
                    action_type TEXT,
                    prompt TEXT,
                    response TEXT,
                    input_tokens INTEGER DEFAULT 0,
                    output_tokens INTEGER DEFAULT 0,
                    file_path TEXT,
                    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
                );
            ");
            // Add columns if they don't exist (handle update)
            try { $this->db->exec("ALTER TABLE ai_logs ADD COLUMN input_tokens INTEGER DEFAULT 0"); } catch (PDOException $e) {}
            try { $this->db->exec("ALTER TABLE ai_logs ADD COLUMN output_tokens INTEGER DEFAULT 0"); } catch (PDOException $e) {}
            try { $this->db->exec("ALTER TABLE ai_logs ADD COLUMN provider_key TEXT"); } catch (PDOException $e) {}
            try { $this->db->exec("ALTER TABLE ai_logs ADD COLUMN model_id TEXT"); } catch (PDOException $e) {}
        } else {
            $this->db->exec("
                 CREATE TABLE IF NOT EXISTS ai_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT,
                    provider_key VARCHAR(50),
                    model_id VARCHAR(100),
                    action_type VARCHAR(50),
                    prompt LONGTEXT,
                    response LONGTEXT,
                    input_tokens INT DEFAULT 0,
                    output_tokens INT DEFAULT 0,
                    file_path VARCHAR(255),
                    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            // Add columns if they don't exist (MySQL syntax)
            try { $this->db->exec("ALTER TABLE ai_logs ADD COLUMN input_tokens INT DEFAULT 0"); } catch (PDOException $e) {}
            try { $this->db->exec("ALTER TABLE ai_logs ADD COLUMN output_tokens INT DEFAULT 0"); } catch (PDOException $e) {}
            try { $this->db->exec("ALTER TABLE ai_logs ADD COLUMN provider_key VARCHAR(50)"); } catch (PDOException $e) {}
            try { $this->db->exec("ALTER TABLE ai_logs ADD COLUMN model_id VARCHAR(100)"); } catch (PDOException $e) {}
        }

        // Drop logic moved to manual cleanup to avoid locking
        // $this->db->exec("DROP TABLE IF EXISTS ai_settings");
    }
}
