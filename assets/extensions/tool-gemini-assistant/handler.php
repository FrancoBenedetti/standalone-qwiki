<?php
/**
 * Standalone Qwiki - Gemini AI Assistant Extension Handler
 * 
 * Provides outbound Gemini LLM integration for:
 * - Social media OpenGraph / Twitter metadata generation
 * - Tag & taxonomy suggestions
 * - Social post drafting (LinkedIn, X, Slack)
 * - Executive TL;DR & Markdown summaries
 * - Secure BYOK API Key configuration
 */

use Qwiki\Core\Auth;
use Qwiki\Core\Config;

if (!Auth::isAdmin()) {
    if (!headers_sent()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    return;
}

$baseDir = Config::getBaseDir();
$action = $action ?? ($_REQUEST['action'] ?? '');
$isDemo = Config::isDemoMode();
$params = array_merge($_GET, $_POST, is_array($requestData ?? null) ? $requestData : []);

if (!function_exists('gemini_json_reply')) {
    function gemini_json_reply(array $data, int $status = 200) {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (php_sapi_name() !== 'cli') {
            exit;
        }
    }
}

if (!function_exists('gemini_get_resolved_config')) {
    function gemini_get_resolved_config(): array {
        $config = Config::load();
        $geminiConfig = $config['gemini'] ?? [];

        // Check environment variable overrides first
        $envKey = getenv('GEMINI_API_KEY') ?: getenv('QWIKI_GEMINI_API_KEY');
        $isEnvKey = !empty($envKey);
        $apiKey = $isEnvKey ? trim($envKey) : trim($geminiConfig['apiKey'] ?? '');
        $model = trim($geminiConfig['model'] ?? 'gemini-2.5-flash');
        if (empty($model)) {
            $model = 'gemini-2.5-flash';
        }

        return [
            'apiKey' => $apiKey,
            'isEnvKey' => $isEnvKey,
            'model' => $model,
            'hasKey' => !empty($apiKey),
            'maskedKey' => !empty($apiKey) ? (substr($apiKey, 0, 6) . '••••••••••••' . substr($apiKey, -4)) : ''
        ];
    }
}

if (!function_exists('gemini_call_api')) {
    function gemini_call_api(string $apiKey, string $model, array $contents, ?array $generationConfig = null): array {
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Gemini API key is not configured. Please add your key in AI Assistant Settings.'];
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . urlencode($model) . ":generateContent?key=" . urlencode($apiKey);

        $payload = ['contents' => $contents];
        if (!empty($generationConfig)) {
            $payload['generationConfig'] = $generationConfig;
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: Standalone-Qwiki-Gemini-Assistant/1.0'
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'error' => 'Connection to Gemini API failed: ' . $curlError];
        }

        $resData = json_decode($response, true);

        if ($httpCode >= 400 || !empty($resData['error'])) {
            $errorMsg = $resData['error']['message'] ?? "Gemini API error (HTTP {$httpCode})";
            $status = $resData['error']['status'] ?? '';
            if ($status === 'RESOURCE_EXHAUSTED') {
                $errorMsg = 'Gemini API quota exceeded for this key. Please check your Google AI Studio quota.';
            } elseif ($status === 'INVALID_ARGUMENT' && stripos($errorMsg, 'API key') !== false) {
                $errorMsg = 'Invalid Gemini API key. Please check the key in AI Assistant Settings.';
            } elseif ($httpCode === 404) {
                $errorMsg = "Model '{$model}' was not found or is deprecated. Try gemini-2.5-flash or gemini-2.5-pro.";
            }
            return ['success' => false, 'error' => $errorMsg, 'http_code' => $httpCode];
        }

        $text = $resData['candidates'][0]['content']['parts'][0]['text'] ?? '';
        return ['success' => true, 'text' => $text, 'raw' => $resData];
    }
}

// -------------------------------------------------------------
// Helper to retrieve document content by slug or file
// -------------------------------------------------------------
if (!function_exists('gemini_find_document')) {
    function gemini_find_document($slug, $baseDir, array $books) {
        $found = null;
        $searchRecursive = function($items) use (&$searchRecursive, $slug, &$found) {
            foreach ($items as $item) {
                if (($item['slug'] ?? '') === $slug) {
                    $found = $item;
                    return;
                }
                if (!empty($item['items'])) {
                    $searchRecursive($item['items']);
                    if ($found) return;
                }
            }
        };

        foreach ($books as $book) {
            if (!empty($book['items'])) {
                $searchRecursive($book['items']);
                if ($found) break;
            }
        }

        if (!$found) return null;

        $content = '';
        if (!empty($found['file'])) {
            $filePath = $baseDir . '/' . $found['file'];
            if (file_exists($filePath)) {
                $content = @file_get_contents($filePath) ?: '';
            }
        }

        return [
            'item' => $found,
            'title' => $found['title'] ?? ucfirst($slug),
            'content' => $content,
            'description' => $found['description'] ?? '',
            'image' => $found['image'] ?? '',
            'tags' => $found['tags'] ?? []
        ];
    }
}

// -------------------------------------------------------------
// 1. GET SETTINGS
// -------------------------------------------------------------
if ($action === 'ext_gemini_get_settings') {
    $cfg = gemini_get_resolved_config();
    gemini_json_reply([
        'success' => true,
        'hasKey' => $cfg['hasKey'],
        'maskedKey' => $cfg['maskedKey'],
        'isEnvKey' => $cfg['isEnvKey'],
        'model' => $cfg['model'],
        'isDemo' => $isDemo
    ]);
    return;
}

// -------------------------------------------------------------
// 2. SAVE SETTINGS
// -------------------------------------------------------------
if ($action === 'ext_gemini_save_settings') {
    if ($isDemo) {
        gemini_json_reply(['success' => false, 'error' => 'Settings modifications are disabled in Demo Mode.'], 403);
        return;
    }

    $rawKey = trim($params['apiKey'] ?? '');
    $model = trim($params['model'] ?? 'gemini-2.5-flash');
    if (empty($model)) {
        $model = 'gemini-2.5-flash';
    }

    $config = Config::load();
    if (!isset($config['gemini']) || !is_array($config['gemini'])) {
        $config['gemini'] = [];
    }

    // Preserve existing key if placeholder was sent
    if ($rawKey !== '' && strpos($rawKey, '••••') === false) {
        $config['gemini']['apiKey'] = $rawKey;
    } elseif ($rawKey === '') {
        $config['gemini']['apiKey'] = '';
    }

    $config['gemini']['model'] = $model;
    Config::save($config);

    $cfg = gemini_get_resolved_config();
    gemini_json_reply([
        'success' => true,
        'hasKey' => $cfg['hasKey'],
        'maskedKey' => $cfg['maskedKey'],
        'isEnvKey' => $cfg['isEnvKey'],
        'model' => $cfg['model']
    ]);
    return;
}

// -------------------------------------------------------------
// 3. TEST CONNECTION
// -------------------------------------------------------------
if ($action === 'ext_gemini_test_connection') {
    $testKey = trim($params['apiKey'] ?? '');
    $cfg = gemini_get_resolved_config();
    
    // If not a new key, use configured key
    if (empty($testKey) || strpos($testKey, '••••') !== false) {
        $testKey = $cfg['apiKey'];
    }

    $testModel = trim($params['model'] ?? $cfg['model']);
    if (empty($testModel)) {
        $testModel = 'gemini-2.5-flash';
    }

    if (empty($testKey)) {
        if ($isDemo) {
            gemini_json_reply([
                'success' => true,
                'message' => 'Demo Mode: API key is simulated. Connected to ' . htmlspecialchars($testModel) . '.',
                'model' => $testModel
            ]);
            return;
        }
        gemini_json_reply(['success' => false, 'error' => 'Please provide a Gemini API key to test.']);
        return;
    }

    $contents = [
        [
            'parts' => [
                ['text' => 'Respond with the exact word: OK']
            ]
        ]
    ];

    $res = gemini_call_api($testKey, $testModel, $contents, ['temperature' => 0.1]);
    if (!$res['success']) {
        gemini_json_reply(['success' => false, 'error' => $res['error']]);
        return;
    }

    gemini_json_reply([
        'success' => true,
        'message' => 'Successfully connected to Google Gemini (' . htmlspecialchars($testModel) . ')!',
        'model' => $testModel
    ]);
    return;
}

// -------------------------------------------------------------
// 4. GENERATE SOCIAL & METADATA (OG Title, Desc, Tags, Blurb)
// -------------------------------------------------------------
if ($action === 'ext_gemini_generate_meta') {
    $slug = trim($params['slug'] ?? '');
    $docTitle = trim($params['title'] ?? '');
    $content = trim($params['content'] ?? '');

    $config = Config::load();
    if (!empty($slug) && empty($content)) {
        $doc = gemini_find_document($slug, $baseDir, $config['books'] ?? []);
        if ($doc) {
            $docTitle = !empty($docTitle) ? $docTitle : $doc['title'];
            $content = $doc['content'];
        }
    }

    if (empty($content) && empty($docTitle)) {
        gemini_json_reply(['success' => false, 'error' => 'Document title or content is required to generate metadata.']);
        return;
    }

    $cfg = gemini_get_resolved_config();

    // Fallback in demo mode if no key configured
    if ($isDemo && empty($cfg['apiKey'])) {
        $cleanSnippet = preg_replace('/[#*_>`~=-]/', '', strip_tags($content));
        $cleanSnippet = trim(preg_replace('/\s+/', ' ', $cleanSnippet));
        $mockDesc = (mb_strlen($cleanSnippet) > 150) ? mb_substr($cleanSnippet, 0, 147) . '...' : ($cleanSnippet ?: 'Documentation guide for ' . $docTitle);
        gemini_json_reply([
            'success' => true,
            'isMock' => true,
            'metadata' => [
                'description' => $mockDesc,
                'tags' => ['Guide', 'Documentation', 'Reference', 'Qwiki'],
                'social_blurb' => "🚀 Explore the documentation for '{$docTitle}'!\n\nKey takeaways inside.\n\n#Documentation #DevTools #Guide",
                'headline_suggestions' => [
                    "Mastering {$docTitle}: Complete Overview",
                    "Everything You Need to Know About {$docTitle}"
                ]
            ]
        ]);
        return;
    }

    if (empty($cfg['apiKey'])) {
        gemini_json_reply(['success' => false, 'error' => 'Gemini API key is not configured. Go to the Settings tab in AI Assistant to configure your key.']);
        return;
    }

    // Truncate input content to ~20,000 characters to keep prompt fast and token-efficient
    if (mb_strlen($content) > 20000) {
        $content = mb_substr($content, 0, 20000) . "\n...[truncated]";
    }

    $prompt = "You are an expert technical documentation editor and social media strategist.\n";
    $prompt .= "Analyze the following documentation page and generate high-impact social media & SEO metadata.\n\n";
    $prompt .= "Document Title: {$docTitle}\n";
    $prompt .= "Document Content:\n{$content}\n\n";
    $prompt .= "Requirements:\n";
    $prompt .= "1. 'description': Crisp, engaging summary strictly under 160 characters (ideal for OpenGraph and Twitter cards). Do not cut off mid-sentence.\n";
    $prompt .= "2. 'tags': 3 to 6 highly relevant topic/technology keyword tags.\n";
    $prompt .= "3. 'social_blurb': A ready-to-share social post (for X/Twitter or LinkedIn) featuring a hook, 2-3 bullet highlights, and 2-3 relevant hashtags.\n";
    $prompt .= "4. 'headline_suggestions': 2 alternative catchy but professional titles.\n";

    $contents = [
        ['parts' => [['text' => $prompt]]]
    ];

    $generationConfig = [
        'temperature' => 0.7,
        'responseMimeType' => 'application/json',
        'responseSchema' => [
            'type' => 'OBJECT',
            'properties' => [
                'description' => ['type' => 'STRING'],
                'tags' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                'social_blurb' => ['type' => 'STRING'],
                'headline_suggestions' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']]
            ],
            'required' => ['description', 'tags', 'social_blurb']
        ]
    ];

    $res = gemini_call_api($cfg['apiKey'], $cfg['model'], $contents, $generationConfig);
    if (!$res['success']) {
        gemini_json_reply(['success' => false, 'error' => $res['error']]);
        return;
    }

    $parsed = json_decode($res['text'], true);
    if (!is_array($parsed)) {
        // Fallback regex attempt if raw JSON was wrapped
        if (preg_match('/\{[\s\S]*\}/', $res['text'], $m)) {
            $parsed = json_decode($m[0], true);
        }
    }

    if (!is_array($parsed) || empty($parsed['description'])) {
        gemini_json_reply(['success' => false, 'error' => 'Failed to parse structured metadata from Gemini response.', 'raw' => $res['text']]);
        return;
    }

    gemini_json_reply([
        'success' => true,
        'metadata' => [
            'description' => trim($parsed['description']),
            'tags' => array_values(array_filter(array_map('trim', $parsed['tags'] ?? []))),
            'social_blurb' => trim($parsed['social_blurb'] ?? ''),
            'headline_suggestions' => array_values(array_filter(array_map('trim', $parsed['headline_suggestions'] ?? [])))
        ]
    ]);
    return;
}

// -------------------------------------------------------------
// 5. GENERATE EXECUTIVE TL;DR / DOCUMENT SUMMARY
// -------------------------------------------------------------
if ($action === 'ext_gemini_summarize') {
    $slug = trim($params['slug'] ?? '');
    $docTitle = trim($params['title'] ?? '');
    $content = trim($params['content'] ?? '');

    $config = Config::load();
    if (!empty($slug) && empty($content)) {
        $doc = gemini_find_document($slug, $baseDir, $config['books'] ?? []);
        if ($doc) {
            $docTitle = !empty($docTitle) ? $docTitle : $doc['title'];
            $content = $doc['content'];
        }
    }

    if (empty($content)) {
        gemini_json_reply(['success' => false, 'error' => 'Document content is empty or could not be found.']);
        return;
    }

    $cfg = gemini_get_resolved_config();

    if ($isDemo && empty($cfg['apiKey'])) {
        $tldr = "> **TL;DR:** This document explains the essential features, architecture, and workflows of {$docTitle}. Designed for quick onboarding and practical reference.\n";
        gemini_json_reply([
            'success' => true,
            'isMock' => true,
            'tldr' => $tldr
        ]);
        return;
    }

    if (empty($cfg['apiKey'])) {
        gemini_json_reply(['success' => false, 'error' => 'Gemini API key is not configured.']);
        return;
    }

    if (mb_strlen($content) > 20000) {
        $content = mb_substr($content, 0, 20000) . "\n...[truncated]";
    }

    $prompt = "You are an expert documentation technical writer.\n";
    $prompt .= "Read the following document and generate an Executive TL;DR summary callout block formatted in clean GitHub-style Markdown.\n";
    $prompt .= "Format:\n> **TL;DR:** [2-3 concise sentences summarizing the core purpose, what readers will learn, and key prerequisites or outcomes].\n\n";
    $prompt .= "Title: {$docTitle}\n";
    $prompt .= "Content:\n{$content}\n";

    $contents = [
        ['parts' => [['text' => $prompt]]]
    ];

    $res = gemini_call_api($cfg['apiKey'], $cfg['model'], $contents, ['temperature' => 0.4]);
    if (!$res['success']) {
        gemini_json_reply(['success' => false, 'error' => $res['error']]);
        return;
    }

    gemini_json_reply([
        'success' => true,
        'tldr' => trim($res['text'])
    ]);
    return;
}

// -------------------------------------------------------------
// 6. APPLY METADATA DIRECTLY TO DOCUMENT IN QWIKI.JSON
// -------------------------------------------------------------
if ($action === 'ext_gemini_apply_meta') {
    if ($isDemo) {
        gemini_json_reply(['success' => false, 'error' => 'Modifying documents is restricted in Demo Mode.'], 403);
        return;
    }

    $slug = trim($params['slug'] ?? '');
    $description = trim($params['description'] ?? '');
    $rawTags = $params['tags'] ?? [];

    if (empty($slug)) {
        gemini_json_reply(['success' => false, 'error' => 'Document slug is required.']);
        return;
    }

    $tags = [];
    if (is_array($rawTags)) {
        $tags = array_values(array_filter(array_map('trim', $rawTags)));
    } elseif (is_string($rawTags)) {
        $tags = array_values(array_filter(array_map('trim', explode(',', $rawTags))));
    }

    $config = Config::load();
    $found = false;

    $updateRecursive = function(&$items) use (&$updateRecursive, $slug, $description, $tags, &$found) {
        foreach ($items as &$item) {
            if (($item['slug'] ?? '') === $slug) {
                $item['description'] = $description;
                if (!empty($tags)) {
                    $item['tags'] = $tags;
                }
                $found = true;
                return;
            }
            if (!empty($item['items'])) {
                $updateRecursive($item['items']);
                if ($found) return;
            }
        }
    };

    if (!empty($config['books'])) {
        $updateRecursive($config['books']);
    }

    if (!$found) {
        gemini_json_reply(['success' => false, 'error' => "Document with slug '{$slug}' not found."]);
        return;
    }

    Config::save($config);
    gemini_json_reply([
        'success' => true,
        'message' => 'Metadata successfully applied and saved to document!',
        'slug' => $slug,
        'description' => $description,
        'tags' => $tags
    ]);
    return;
}

gemini_json_reply(['success' => false, 'error' => "Unknown action '{$action}'."], 400);
