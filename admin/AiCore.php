<?php
declare(strict_types=1);

class AiCore
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
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
    public function chat(string $providerKey, string $model, string $prompt, string $systemPrompt, string $context = ''): string
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
            'openrouter' => $this->callOpenRouter($provider['api_key'], $model, $prompt, $fullSystemUserPrompt),
            'openai' => $this->callOpenAI($provider['api_key'], $model, $prompt, $fullSystemUserPrompt), // Future proofing
            default => throw new Exception(__("ai_provider_unsupported"))
        };
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

    private function callOpenRouter(string $apiKey, string $model, string $userPrompt, string $systemInfo): string
    {
        $url = "https://openrouter.ai/api/v1/chat/completions";

        $data = [
            "model" => $model,
            "messages" => [
                ["role" => "system", "content" => $systemInfo],
                ["role" => "user", "content" => $userPrompt]
            ]
        ];

        $response = $this->curlRequest($url, $data, 'openrouter', $apiKey);
        $json = json_decode($response, true);

        if (isset($json['error'])) {
            throw new Exception("OpenRouter Error: " . ($json['error']['message'] ?? 'Unknown'));
        }

        return $json['choices'][0]['message']['content'] ?? throw new Exception("OpenRouter empty response");
    }

    private function callOpenAI(string $apiKey, string $model, string $userPrompt, string $systemInfo): string
    {
        $url = "https://api.openai.com/v1/chat/completions";

        $data = [
            "model" => $model,
            "messages" => [
                ["role" => "system", "content" => $systemInfo],
                ["role" => "user", "content" => $userPrompt]
            ]
        ];

        $response = $this->curlRequest($url, $data, 'openai', $apiKey);
        $json = json_decode($response, true);

        if (isset($json['error'])) {
            throw new Exception("OpenAI Error: " . ($json['error']['message'] ?? 'Unknown'));
        }

        return $json['choices'][0]['message']['content'] ?? throw new Exception("OpenAI empty response");
    }

    private function curlRequest(string $url, array $data, string $type, string $apiKey): string
    {
        $ch = curl_init($url);
        $headers = ["Content-Type: application/json"];

        if ($type === 'gemini') {
            // API key is in URL for Gemini usually, but strictly speaking we don't pass Bearer often.
        } else {
            $headers[] = "Authorization: Bearer $apiKey";
        }

        if ($type === 'openrouter') {
            $headers[] = "HTTP-Referer: https://speedpage.local"; // Required by OpenRouter
            $headers[] = "X-Title: SpeedPage Admin";
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false // For local dev
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new Exception("CURL Error: $err");
        }

        return $response;
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

        // Logs table (keep or ensure exists)
        if ($isSqlite) {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS ai_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER,
                    action_type TEXT,
                    prompt TEXT,
                    response TEXT,
                    file_path TEXT,
                    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
                );
            ");
        } else {
            $this->db->exec("
                 CREATE TABLE IF NOT EXISTS ai_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT,
                    action_type VARCHAR(50),
                    prompt LONGTEXT,
                    response LONGTEXT,
                    file_path VARCHAR(255),
                    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        }

        // Drop logic moved to manual cleanup to avoid locking
        // $this->db->exec("DROP TABLE IF EXISTS ai_settings");
    }
}
