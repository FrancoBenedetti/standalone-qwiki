<?php
namespace Qwiki\Extension\WikiChat;

use Qwiki\Core\Auth;
use Qwiki\Core\Config;
use Qwiki\Core\ExtensionManager;
use Qwiki\Core\LlmAccess;

class WikiChatService {
    public const VALID_ACCESS_MODES = ['public', 'logged_in', 'admin_only'];
    public const MAX_TOOL_STEPS = 4;
    public const MAX_HISTORY_TURNS = 10;
    public const MAX_MESSAGE_CHARS = 4000;
    public const MAX_ACTIVE_DOC_CHARS = 12000;

    public static function getChatSettings(?array $config = null): array {
        if ($config === null) {
            $config = Config::load();
        }
        $raw = isset($config['wikiChat']) && is_array($config['wikiChat']) ? $config['wikiChat'] : [];
        $accessMode = $raw['accessMode'] ?? 'logged_in';
        if (!in_array($accessMode, self::VALID_ACCESS_MODES, true)) {
            $accessMode = 'logged_in';
        }

        return [
            'accessMode' => $accessMode,
            'llmKeyId' => trim((string)($raw['llmKeyId'] ?? '')),
            'llmKeyAuto' => array_key_exists('llmKeyAuto', $raw) ? !empty($raw['llmKeyAuto']) : true,
            'includeActiveDoc' => array_key_exists('includeActiveDoc', $raw) ? !empty($raw['includeActiveDoc']) : true,
            'systemPromptExtra' => trim((string)($raw['systemPromptExtra'] ?? '')),
        ];
    }

    public static function canCurrentUserAccessChat(?array $config = null): bool {
        if ($config === null) {
            $config = Config::load();
        }
        if (!ExtensionManager::getInstance()->isExtensionEnabled('wiki_chat')) {
            return false;
        }
        if (!Auth::canView($config)) {
            return false;
        }

        $settings = self::getChatSettings($config);
        switch ($settings['accessMode']) {
            case 'admin_only':
                return Auth::isAdmin();
            case 'logged_in':
                return Auth::isViewer() || Auth::isAdmin();
            case 'public':
                return true;
            default:
                return false;
        }
    }

    public static function resolveLlmCredentials(?string $username = null): array {
        $config = Config::load();
        $geminiConfig = $config['gemini'] ?? [];

        if ($username === null) {
            $currentUser = Auth::getCurrentUser();
            if (!empty($currentUser['username'])) {
                $username = $currentUser['username'];
            }
        }

        $envKey = getenv('GEMINI_API_KEY') ?: getenv('QWIKI_GEMINI_API_KEY');
        $isEnvKey = !empty($envKey) && trim((string)$envKey) !== '';
        $siteKey = $isEnvKey ? trim((string)$envKey) : trim((string)($geminiConfig['apiKey'] ?? ''));
        $siteModel = trim((string)($geminiConfig['model'] ?? 'gemini-3.8-flash'));
        if ($siteModel === '') {
            $siteModel = 'gemini-3.8-flash';
        }

        $userRecord = !empty($username) ? Auth::getUser($username) : null;
        $userKey = trim((string)($userRecord['geminiApiKey'] ?? ''));
        $userModel = trim((string)($userRecord['geminiModel'] ?? ''));

        $hasUserKey = $userKey !== '';
        $hasSiteKey = $siteKey !== '';

        $effectiveKey = $hasUserKey ? $userKey : $siteKey;
        $effectiveModel = ($hasUserKey && $userModel !== '') ? $userModel : $siteModel;

        $keySource = 'none';
        if ($hasUserKey) {
            $keySource = 'user';
        } elseif ($isEnvKey) {
            $keySource = 'env';
        } elseif ($hasSiteKey) {
            $keySource = 'site';
        }

        $maskedKey = '';
        if ($effectiveKey !== '') {
            $maskedKey = strlen($effectiveKey) > 10
                ? substr($effectiveKey, 0, 6) . '••••••••••••' . substr($effectiveKey, -4)
                : '••••••••';
        }

        return [
            'hasSiteKey' => $hasSiteKey,
            'apiKey' => $effectiveKey,
            'model' => $effectiveModel,
            'keySource' => $keySource,
            'maskedKey' => $maskedKey,
            'hasUserKey' => $hasUserKey,
        ];
    }

    public static function resolveWikiLlmKey(?array $config = null, bool $autoCreate = true): array {
        if ($config === null) {
            $config = Config::load();
        }
        $settings = self::getChatSettings($config);
        $targetId = $settings['llmKeyId'];
        $isAuto = $settings['llmKeyAuto'];

        $keys = LlmAccess::listKeys();
        $found = null;
        if ($targetId !== '') {
            foreach ($keys as $k) {
                if (($k['id'] ?? '') === $targetId) {
                    $found = $k;
                    break;
                }
            }
        }

        if ($found !== null) {
            $status = $found['status'] ?? 'active';
            $expired = !empty($found['expiresAt']) && strtotime($found['expiresAt']) <= time();
            if ($status === 'active' && !$expired) {
                return ['success' => true, 'key' => $found, 'error' => null];
            }
            if (!$isAuto) {
                $reason = $expired
                    ? 'The configured Wiki LLM Access Key has expired.'
                    : 'The configured Wiki LLM Access Key has been revoked.';
                return ['success' => false, 'key' => null, 'error' => $reason];
            }
        }

        if (!$autoCreate) {
            return ['success' => false, 'key' => null, 'error' => 'No active Wiki LLM Access Key is configured.'];
        }

        $gen = LlmAccess::generateKey(
            'Wiki Chat Assistant',
            '',
            ['markdown', 'html', 'pdf', 'gdoc', 'form']
        );
        if (empty($gen['success']) || empty($gen['key'])) {
            return [
                'success' => false,
                'key' => null,
                'error' => $gen['error'] ?? 'Failed to auto-provision Wiki LLM Access Key.'
            ];
        }

        $freshConfig = Config::load();
        if (!isset($freshConfig['wikiChat']) || !is_array($freshConfig['wikiChat'])) {
            $freshConfig['wikiChat'] = [];
        }
        $freshConfig['wikiChat']['llmKeyId'] = $gen['key']['id'];
        $freshConfig['wikiChat']['llmKeyAuto'] = true;
        Config::save($freshConfig);

        return ['success' => true, 'key' => $gen['key'], 'error' => null];
    }

    private static function buildVisibilityMap(?array $books = null): array {
        if ($books === null) {
            $config = Config::load();
            $books = $config['books'] ?? [];
        }
        $map = [];
        $walk = function (array $nodes, string $parentVis) use (&$walk, &$map) {
            foreach ($nodes as $node) {
                $effVis = LlmAccess::resolveEffectiveVisibility($node['visibility'] ?? null, $parentVis);
                if (!empty($node['slug'])) {
                    $map[$node['slug']] = $effVis;
                }
                if (!empty($node['id']) && empty($node['slug'])) {
                    // Folder/book node; children inherit $effVis
                }
                if (!empty($node['items']) && is_array($node['items'])) {
                    $walk($node['items'], $effVis);
                }
                if (!empty($node['chapters']) && is_array($node['chapters'])) {
                    $walk($node['chapters'], $effVis);
                }
                if (!empty($node['folders']) && is_array($node['folders'])) {
                    $walk($node['folders'], $effVis);
                }
            }
        };
        $walk($books, 'public');
        return $map;
    }

    public static function filterDocsForCaller(array $docs, bool $isAdmin, bool $isViewer): array {
        if ($isAdmin || $isViewer) {
            return array_values($docs);
        }
        $visMap = self::buildVisibilityMap();
        $filtered = [];
        foreach ($docs as $doc) {
            $slug = $doc['slug'] ?? '';
            $vis = $visMap[$slug] ?? 'public';
            if ($vis === 'public') {
                $filtered[] = $doc;
            }
        }
        return $filtered;
    }

    private static function isSlugAccessibleForCaller(string $slug, bool $isAdmin, bool $isViewer): bool {
        if ($isAdmin || $isViewer) {
            return true;
        }
        $visMap = self::buildVisibilityMap();
        $vis = $visMap[$slug] ?? 'public';
        return $vis === 'public';
    }

    public static function buildSystemPrompt(
        array $keyRecord,
        ?string $activeSlug,
        bool $includeActiveDoc,
        bool $isAdmin,
        bool $isViewer
    ): array {
        $config = Config::load();
        $settings = self::getChatSettings($config);
        $wikiTitle = $config['title'] ?? 'Standalone Qwiki';
        $siteDesc = $config['siteDescription'] ?? '';

        $rawDocs = LlmAccess::buildDocumentTree($keyRecord);
        $scopedDocs = self::filterDocsForCaller($rawDocs, $isAdmin, $isViewer);
        $llmsTxt = LlmAccess::renderLlmsTxt($scopedDocs, $wikiTitle, $siteDesc);

        $baseUrl = Config::getBaseUrl();
        $apiEndpoint = rtrim($baseUrl, '/') . '/api/llm.php';
        $keyName = $keyRecord['name'] ?? 'Wiki Chat Assistant';
        $keyScope = !empty($keyRecord['category']) ? $keyRecord['category'] : 'All categories';
        $allowedTypes = !empty($keyRecord['allowedTypes']) && is_array($keyRecord['allowedTypes'])
            ? implode(', ', $keyRecord['allowedTypes'])
            : 'markdown';

        $prompt = "You are the AI Documentation Assistant for \"{$wikiTitle}\".\n"
            . "Answer user questions accurately using ONLY the wiki content provided below or retrieved via your tools.\n\n"
            . "## Wiki API & Key Context\n"
            . "- Wiki LLM API Endpoint: `{$apiEndpoint}` (supports `mode=tree`, `mode=search`, `mode=doc`, `mode=comments`, `mode=talk`)\n"
            . "- Assigned Scoped Key: `{$keyName}` (Category Scope: {$keyScope}; Permitted Types: {$allowedTypes})\n"
            . "- You have function tools (`qwiki_list_tree`, `qwiki_search_documents`, `qwiki_get_document`) backed directly by this key.\n\n"
            . "## Citation & Accuracy Rules\n"
            . "1. Always cite source chapters using relative Markdown links with their slug (e.g., `[Chapter Title](slug)`).\n"
            . "2. If the answer is not in the currently active chapter, use `qwiki_search_documents` or `qwiki_get_document` to read relevant chapters from the index below.\n"
            . "3. If the information cannot be found in the wiki, clearly state that it is not covered in the available wiki documentation.\n\n"
            . "## Scoped Wiki Document Index (`llms.txt`)\n"
            . $llmsTxt . "\n";

        $activeDoc = null;
        if ($includeActiveDoc && !empty($activeSlug) && self::isSlugAccessibleForCaller($activeSlug, $isAdmin, $isViewer)) {
            $docRes = LlmAccess::retrieveDocument($keyRecord, $activeSlug, 0, self::MAX_ACTIVE_DOC_CHARS, false, false);
            if (!empty($docRes['success']) && !empty($docRes['document'])) {
                $doc = $docRes['document'];
                $activeDoc = [
                    'title' => $doc['title'] ?? $activeSlug,
                    'slug' => $doc['slug'] ?? $activeSlug,
                    'url' => $doc['url'] ?? '',
                ];
                $contentText = (string)($doc['content'] ?? '');
                if (strlen($contentText) > self::MAX_ACTIVE_DOC_CHARS) {
                    $contentText = substr($contentText, 0, self::MAX_ACTIVE_DOC_CHARS) . "\n...[truncated]";
                }
                $prompt .= "\n## Currently Open Chapter\n"
                    . "- Title: " . ($doc['title'] ?? '') . "\n"
                    . "- Slug: " . ($doc['slug'] ?? $activeSlug) . "\n"
                    . "- URL: " . ($doc['url'] ?? '') . "\n\n"
                    . "```markdown\n" . $contentText . "\n```\n";
            }
        }

        if ($settings['systemPromptExtra'] !== '') {
            $prompt .= "\n## Additional Administrator Instructions\n" . $settings['systemPromptExtra'] . "\n";
        }

        return [
            'prompt' => $prompt,
            'activeDoc' => $activeDoc,
            'treeCount' => count($scopedDocs),
        ];
    }

    public static function getGeminiToolsDeclaration(): array {
        return [
            [
                'name' => 'qwiki_search_documents',
                'description' => 'Search wiki chapters by keyword across titles, descriptions, and document text.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'query' => [
                            'type' => 'STRING',
                            'description' => 'Keyword or phrase to search for in the wiki.'
                        ],
                        'limit' => [
                            'type' => 'INTEGER',
                            'description' => 'Maximum number of search results to return (default 8).'
                        ]
                    ],
                    'required' => ['query']
                ]
            ],
            [
                'name' => 'qwiki_get_document',
                'description' => 'Retrieve the full or paginated text content of a specific wiki chapter by its slug.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'slug' => [
                            'type' => 'STRING',
                            'description' => 'The chapter slug from the document index or search results.'
                        ],
                        'offset' => [
                            'type' => 'INTEGER',
                            'description' => 'Optional byte offset for reading long documents in chunks.'
                        ],
                        'limit' => [
                            'type' => 'INTEGER',
                            'description' => 'Optional maximum bytes to return (default 12000).'
                        ],
                        'include_comments' => [
                            'type' => 'BOOLEAN',
                            'description' => 'Whether to include reader discussion comments.'
                        ],
                        'include_talk' => [
                            'type' => 'BOOLEAN',
                            'description' => 'Whether to include editorial talk page notes.'
                        ]
                    ],
                    'required' => ['slug']
                ]
            ],
            [
                'name' => 'qwiki_list_tree',
                'description' => 'List available wiki chapters, optionally filtered by category or book name.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'category' => [
                            'type' => 'STRING',
                            'description' => 'Optional category/book name or "all".'
                        ]
                    ]
                ]
            ]
        ];
    }

    public static function executeToolCall(
        string $name,
        array $args,
        array $keyRecord,
        bool $isAdmin,
        bool $isViewer
    ): array {
        switch ($name) {
            case 'qwiki_list_tree':
                $category = isset($args['category']) ? trim((string)$args['category']) : '';
                $rawDocs = LlmAccess::buildDocumentTree($keyRecord, $category);
                $docs = self::filterDocsForCaller($rawDocs, $isAdmin, $isViewer);
                return [
                    'result' => [
                        'success' => true,
                        'count' => count($docs),
                        'documents' => array_map(function ($d) {
                            return [
                                'title' => $d['title'] ?? '',
                                'slug' => $d['slug'] ?? '',
                                'description' => $d['description'] ?? '',
                                'type' => $d['type'] ?? 'markdown',
                                'url' => $d['url'] ?? '',
                            ];
                        }, $docs)
                    ],
                    'source' => null
                ];

            case 'qwiki_search_documents':
                $query = trim((string)($args['query'] ?? ''));
                $limit = isset($args['limit']) ? max(1, min(20, (int)$args['limit'])) : 8;
                $rawMatches = LlmAccess::searchDocuments($keyRecord, $query, $limit * 2);
                $matches = array_slice(self::filterDocsForCaller($rawMatches, $isAdmin, $isViewer), 0, $limit);
                return [
                    'result' => [
                        'success' => true,
                        'query' => $query,
                        'count' => count($matches),
                        'matches' => $matches
                    ],
                    'source' => null
                ];

            case 'qwiki_get_document':
                $slug = trim((string)($args['slug'] ?? ''));
                if ($slug === '' || !self::isSlugAccessibleForCaller($slug, $isAdmin, $isViewer)) {
                    return [
                        'result' => [
                            'success' => false,
                            'code' => 403,
                            'error' => 'Document is not accessible for the current caller.'
                        ],
                        'source' => null
                    ];
                }
                $offset = isset($args['offset']) ? max(0, (int)$args['offset']) : 0;
                $limit = isset($args['limit']) && (int)$args['limit'] > 0 ? (int)$args['limit'] : self::MAX_ACTIVE_DOC_CHARS;
                $includeComments = !empty($args['include_comments']);
                $includeTalk = !empty($args['include_talk']);

                $res = LlmAccess::retrieveDocument($keyRecord, $slug, $offset, $limit, $includeComments, $includeTalk);
                $source = null;
                if (!empty($res['success']) && !empty($res['document'])) {
                    $source = [
                        'title' => $res['document']['title'] ?? $slug,
                        'slug' => $res['document']['slug'] ?? $slug,
                        'url' => $res['document']['url'] ?? '',
                    ];
                }
                return [
                    'result' => $res,
                    'source' => $source
                ];

            default:
                return [
                    'result' => [
                        'success' => false,
                        'error' => "Unsupported tool '{$name}'."
                    ],
                    'source' => null
                ];
        }
    }

    public static function runChatTurn(
        string $message,
        array $history = [],
        ?string $activeSlug = null,
        bool $includeActiveDoc = true,
        ?callable $httpTransport = null
    ): array {
        $config = Config::load();
        if (!self::canCurrentUserAccessChat($config)) {
            return ['success' => false, 'code' => 403, 'error' => 'You do not have permission to access Wiki AI Chat.'];
        }

        $message = trim($message);
        if ($message === '') {
            return ['success' => false, 'code' => 400, 'error' => 'Message cannot be empty.'];
        }
        if (mb_strlen($message) > self::MAX_MESSAGE_CHARS) {
            $message = mb_substr($message, 0, self::MAX_MESSAGE_CHARS);
        }

        $keyRes = self::resolveWikiLlmKey($config, true);
        if (empty($keyRes['success']) || empty($keyRes['key'])) {
            return [
                'success' => false,
                'code' => 403,
                'error' => $keyRes['error'] ?? 'No active Wiki LLM Access Key is available.'
            ];
        }
        $keyRecord = $keyRes['key'];

        $isAdmin = Auth::isAdmin();
        $isViewer = Auth::isViewer();

        // Demo mode deterministic fallback if no real API key is configured
        $creds = self::resolveLlmCredentials();
        if (Config::isDemoMode() && empty($creds['apiKey']) && $httpTransport === null) {
            $matches = array_slice(
                self::filterDocsForCaller(LlmAccess::searchDocuments($keyRecord, $message, 5), $isAdmin, $isViewer),
                0,
                3
            );
            $reply = "Demo Mode: Here are the most relevant wiki chapters for your query:\n\n";
            $sources = [];
            if (empty($matches)) {
                $reply .= "No exact keyword matches were found. Try asking about installation, features, or configuration.";
            } else {
                foreach ($matches as $m) {
                    $reply .= "- [{$m['title']}]({$m['slug']}): {$m['excerpt']}\n";
                    $sources[] = ['title' => $m['title'], 'slug' => $m['slug'], 'url' => $m['url']];
                }
            }
            return [
                'success' => true,
                'reply' => $reply,
                'sources' => $sources,
                'tool_calls' => [],
                'model_used' => 'demo-mode',
                'key_source' => 'demo',
            ];
        }

        if (empty($creds['apiKey']) && $httpTransport === null) {
            return ['success' => false, 'code' => 403, 'error' => 'No Gemini API key is configured.'];
        }

        $promptInfo = self::buildSystemPrompt($keyRecord, $activeSlug, $includeActiveDoc, $isAdmin, $isViewer);
        $sysPrompt = $promptInfo['prompt'];

        $contents = [];
        $trimmedHistory = array_slice($history, -(self::MAX_HISTORY_TURNS * 2));
        foreach ($trimmedHistory as $turn) {
            if (!is_array($turn)) continue;
            $role = ($turn['role'] ?? '') === 'model' ? 'model' : 'user';
            $text = trim((string)($turn['text'] ?? ''));
            if ($text === '') continue;
            if (mb_strlen($text) > self::MAX_MESSAGE_CHARS) {
                $text = mb_substr($text, 0, self::MAX_MESSAGE_CHARS);
            }
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $text]]
            ];
        }
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $message]]
        ];

        $cleanModel = preg_replace('#^models/#', '', trim($creds['model'] ?: 'gemini-3.8-flash')) ?: 'gemini-3.8-flash';
        if ($cleanModel === 'gemini-3.8-flash') {
            $modelsToTry = ['gemini-3.8-flash', 'gemini-3.5-flash', 'gemini-2.5-flash'];
        } elseif ($cleanModel === 'gemini-2.5-flash') {
            $modelsToTry = ['gemini-2.5-flash', 'gemini-3.8-flash', 'gemini-3.5-flash', 'gemini-2.5-flash-lite'];
        } elseif ($cleanModel === 'gemini-2.5-pro') {
            $modelsToTry = ['gemini-2.5-pro', 'gemini-3.8-flash', 'gemini-3.5-flash', 'gemini-2.5-flash'];
        } elseif ($cleanModel === 'gemini-3.5-flash') {
            $modelsToTry = ['gemini-3.5-flash', 'gemini-3.8-flash', 'gemini-2.5-flash'];
        } else {
            $modelsToTry = [$cleanModel, 'gemini-3.8-flash', 'gemini-3.5-flash', 'gemini-2.5-flash'];
        }
        $modelsToTry = array_values(array_unique($modelsToTry));
        $model = $modelsToTry[0];

        $apiKey = $creds['apiKey'];
        $tools = [['functionDeclarations' => self::getGeminiToolsDeclaration()]];
        $sourcesMap = [];
        if (!empty($promptInfo['activeDoc'])) {
            $ad = $promptInfo['activeDoc'];
            $sourcesMap[$ad['slug']] = $ad;
        }
        $toolCallsLog = [];

        $transport = $httpTransport ?? function (string $url, array $payload) {
            return self::callGeminiHttp($url, $payload);
        };

        for ($step = 0; $step < self::MAX_TOOL_STEPS; $step++) {
            $payload = [
                'systemInstruction' => ['parts' => [['text' => $sysPrompt]]],
                'contents' => $contents,
                'tools' => $tools,
                'generationConfig' => ['temperature' => 0.3],
            ];

            $candidatesToAttempt = ($step === 0) ? $modelsToTry : [$model];
            $resp = null;
            $httpCode = 0;
            $body = [];

            foreach ($candidatesToAttempt as $candidateModel) {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/" . rawurlencode($candidateModel) . ":generateContent?key=" . rawurlencode($apiKey);
                $resp = $transport($url, $payload);
                $httpCode = (int)($resp['httpCode'] ?? 0);
                $body = $resp['body'] ?? [];
                $errText = (string)($body['error']['message'] ?? '');
                $isModelUnavailable = ($httpCode === 404) || (stripos($errText, 'no longer available') !== false);
                if (!$isModelUnavailable) {
                    $model = $candidateModel;
                    break;
                }
            }

            if ($httpCode >= 400 || empty($body['candidates'][0]['content'])) {
                $errMsg = $body['error']['message'] ?? ($resp['error'] ?? "Gemini API error (HTTP {$httpCode})");
                return ['success' => false, 'code' => $httpCode ?: 502, 'error' => $errMsg];
            }

            $candidateContent = $body['candidates'][0]['content'];
            $parts = $candidateContent['parts'] ?? [];
            $functionCalls = [];
            $textParts = [];

            foreach ($parts as $part) {
                if (!empty($part['functionCall']) && is_array($part['functionCall'])) {
                    $functionCalls[] = $part['functionCall'];
                } elseif (isset($part['text'])) {
                    $textParts[] = (string)$part['text'];
                }
            }

            if (empty($functionCalls)) {
                $replyText = trim(implode("\n", $textParts));
                if ($replyText === '') {
                    $replyText = 'I could not generate a response for that query.';
                }
                return [
                    'success' => true,
                    'reply' => $replyText,
                    'sources' => array_values($sourcesMap),
                    'tool_calls' => $toolCallsLog,
                    'model_used' => $model,
                    'key_source' => $creds['keySource'],
                ];
            }

            $contents[] = [
                'role' => 'model',
                'parts' => $parts
            ];

            $responseParts = [];
            foreach ($functionCalls as $fc) {
                $fnName = (string)($fc['name'] ?? '');
                $fnArgs = isset($fc['args']) && is_array($fc['args']) ? $fc['args'] : [];
                $exec = self::executeToolCall($fnName, $fnArgs, $keyRecord, $isAdmin, $isViewer);

                $toolCallsLog[] = [
                    'name' => $fnName,
                    'args' => $fnArgs,
                    'success' => !empty($exec['result']['success'])
                ];
                if (!empty($exec['source']) && !empty($exec['source']['slug'])) {
                    $sourcesMap[$exec['source']['slug']] = $exec['source'];
                }

                $responseParts[] = [
                    'functionResponse' => [
                        'name' => $fnName,
                        'response' => $exec['result']
                    ]
                ];
            }

            $contents[] = [
                'role' => 'user',
                'parts' => $responseParts
            ];
        }

        return [
            'success' => true,
            'reply' => 'I reached the maximum number of tool lookup steps for this turn. Please try narrowing your question or specifying a chapter.',
            'sources' => array_values($sourcesMap),
            'tool_calls' => $toolCallsLog,
            'model_used' => $model,
            'key_source' => $creds['keySource'],
        ];
    }

    private static function callGeminiHttp(string $url, array $payload): array {
        if (!function_exists('curl_init')) {
            return ['httpCode' => 500, 'body' => [], 'error' => 'PHP cURL extension is required.'];
        }
        $attempt = function (?int $ipResolve) use ($url, $payload): array {
            $ch = curl_init($url);
            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            ];
            if ($ipResolve !== null) {
                $opts[CURLOPT_IPRESOLVE] = $ipResolve;
            }
            curl_setopt_array($ch, $opts);
            $raw = curl_exec($ch);
            $curlErrno = curl_errno($ch);
            $curlErr = curl_error($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ['raw' => $raw, 'errno' => $curlErrno, 'err' => $curlErr, 'httpCode' => $httpCode];
        };

        $res = $attempt(null);
        if ($res['raw'] === false && in_array($res['errno'], [7, 28], true) && defined('CURL_IPRESOLVE_V4')) {
            $retry = $attempt(CURL_IPRESOLVE_V4);
            if ($retry['raw'] !== false || $retry['httpCode'] > 0) {
                $res = $retry;
            }
        }

        if ($res['raw'] === false) {
            return ['httpCode' => 0, 'body' => [], 'error' => $res['err'] ?: 'Network request failed'];
        }
        $decoded = json_decode($res['raw'], true);
        return [
            'httpCode' => $res['httpCode'],
            'body' => is_array($decoded) ? $decoded : [],
            'error' => null
        ];
    }
}
