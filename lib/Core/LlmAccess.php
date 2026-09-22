<?php
namespace Qwiki\Core;

class LlmAccess {
    const KEY_PREFIX = 'qwk_llm_';
    const VALID_DOC_TYPES = ['markdown', 'html', 'pdf', 'gdoc'];

    /**
     * Retrieve all configured LLM access keys from qwiki.json
     *
     * @return array
     */
    public static function listKeys(): array {
        $config = Config::load();
        $keys = $config['llmKeys'] ?? [];
        if (!is_array($keys)) {
            return [];
        }

        $now = time();
        foreach ($keys as &$key) {
            $isExpired = !empty($key['expiresAt']) && strtotime($key['expiresAt']) < $now;
            $key['isExpired'] = $isExpired;
            if (($key['status'] ?? 'active') === 'revoked') {
                $key['effectiveStatus'] = 'revoked';
            } elseif ($isExpired) {
                $key['effectiveStatus'] = 'expired';
            } else {
                $key['effectiveStatus'] = 'active';
            }
        }
        unset($key);

        return $keys;
    }

    /**
     * Generate and store a new LLM access key
     *
     * @param string $name Name/purpose of key (e.g. "Claude Desktop")
     * @param string $category Scoped branch/category ID, or empty string for all categories
     * @param array $allowedTypes Permitted document types (defaults to ['markdown'])
     * @param string|null $expiresAt Optional expiration datetime string
     * @return array Result array with generated key record
     */
    public static function generateKey(string $name, string $category = '', array $allowedTypes = ['markdown'], ?string $expiresAt = null): array {
        $name = trim($name);
        if (empty($name)) {
            return ['success' => false, 'error' => 'A name or purpose for the key is required.'];
        }

        // Sanitize allowed types
        $cleanTypes = [];
        foreach ($allowedTypes as $type) {
            $t = strtolower(trim($type));
            if ($t === 'md') $t = 'markdown';
            if (in_array($t, self::VALID_DOC_TYPES)) {
                $cleanTypes[] = $t;
            }
        }
        if (empty($cleanTypes)) {
            $cleanTypes = ['markdown'];
        }
        $cleanTypes = array_values(array_unique($cleanTypes));

        // Sanitize expiry
        $expDate = null;
        if (!empty($expiresAt)) {
            $parsedTime = strtotime($expiresAt);
            if ($parsedTime !== false && $parsedTime > time()) {
                $expDate = date('Y-m-d H:i:s', $parsedTime);
            } elseif ($parsedTime !== false && $parsedTime <= time()) {
                return ['success' => false, 'error' => 'Expiration date must be in the future.'];
            }
        }

        try {
            $token = self::KEY_PREFIX . bin2hex(random_bytes(16));
            $keyId = 'key_' . bin2hex(random_bytes(6));
        } catch (\Throwable $e) {
            $token = self::KEY_PREFIX . md5(uniqid((string)mt_rand(), true));
            $keyId = 'key_' . substr(md5((string)mt_rand()), 0, 12);
        }

        $newRecord = [
            'id' => $keyId,
            'key' => $token,
            'name' => $name,
            'category' => trim($category),
            'allowedTypes' => $cleanTypes,
            'expiresAt' => $expDate,
            'createdAt' => date('Y-m-d H:i:s'),
            'lastUsedAt' => null,
            'status' => 'active'
        ];

        $config = Config::load();
        if (!isset($config['llmKeys']) || !is_array($config['llmKeys'])) {
            $config['llmKeys'] = [];
        }

        $config['llmKeys'][] = $newRecord;

        if (Config::save($config)) {
            return ['success' => true, 'key' => $newRecord];
        }

        return ['success' => false, 'error' => 'Failed to save configuration to qwiki.json.'];
    }

    /**
     * Revoke or reactivate an existing key
     *
     * @param string $keyId Key ID
     * @param string|null $targetStatus 'active' or 'revoked' (null toggles)
     * @return array
     */
    public static function revokeKey(string $keyId, ?string $targetStatus = null): array {
        $config = Config::load();
        $keys = $config['llmKeys'] ?? [];
        $found = false;
        $updatedStatus = 'revoked';

        foreach ($keys as &$key) {
            if (($key['id'] ?? '') === $keyId) {
                if ($targetStatus !== null) {
                    $key['status'] = in_array($targetStatus, ['active', 'revoked']) ? $targetStatus : 'revoked';
                } else {
                    $key['status'] = (($key['status'] ?? 'active') === 'active') ? 'revoked' : 'active';
                }
                $updatedStatus = $key['status'];
                $found = true;
                break;
            }
        }
        unset($key);

        if (!$found) {
            return ['success' => false, 'error' => 'Key not found.'];
        }

        $config['llmKeys'] = $keys;
        if (Config::save($config)) {
            return ['success' => true, 'status' => $updatedStatus];
        }

        return ['success' => false, 'error' => 'Failed to update key status.'];
    }

    /**
     * Delete an existing key completely
     *
     * @param string $keyId Key ID
     * @return array
     */
    public static function deleteKey(string $keyId): array {
        $config = Config::load();
        $keys = $config['llmKeys'] ?? [];
        $newKeys = [];
        $found = false;

        foreach ($keys as $key) {
            if (($key['id'] ?? '') === $keyId) {
                $found = true;
                continue;
            }
            $newKeys[] = $key;
        }

        if (!$found) {
            return ['success' => false, 'error' => 'Key not found.'];
        }

        $config['llmKeys'] = $newKeys;
        if (Config::save($config)) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Failed to delete key.'];
    }

    /**
     * Update expiry date for a key (e.g. extending or renewing)
     *
     * @param string $keyId Key ID
     * @param string|null $newExpiresAt Date string or null
     * @return array
     */
    public static function updateKeyExpiry(string $keyId, ?string $newExpiresAt): array {
        $expDate = null;
        if (!empty($newExpiresAt)) {
            $parsedTime = strtotime($newExpiresAt);
            if ($parsedTime === false || $parsedTime <= time()) {
                return ['success' => false, 'error' => 'Expiration date must be a valid future datetime.'];
            }
            $expDate = date('Y-m-d H:i:s', $parsedTime);
        }

        $config = Config::load();
        $keys = $config['llmKeys'] ?? [];
        $found = false;

        foreach ($keys as &$key) {
            if (($key['id'] ?? '') === $keyId) {
                $key['expiresAt'] = $expDate;
                if (($key['status'] ?? 'active') !== 'revoked') {
                    $key['status'] = 'active';
                }
                $found = true;
                break;
            }
        }
        unset($key);

        if (!$found) {
            return ['success' => false, 'error' => 'Key not found.'];
        }

        $config['llmKeys'] = $keys;
        if (Config::save($config)) {
            return ['success' => true, 'expiresAt' => $expDate];
        }

        return ['success' => false, 'error' => 'Failed to update key expiry.'];
    }

    /**
     * Extract API key from Authorization header or query parameter
     *
     * @return string
     */
    public static function extractKeyFromRequest(): string {
        // 1. Check HTTP Authorization header: "Bearer <token>"
        $authHeader = '';
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $authHeader = trim($headers['Authorization'] ?? $headers['authorization'] ?? '');
        }

        if (!empty($authHeader) && preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // 2. Check query string: ?key=... or ?token=...
        if (!empty($_GET['key'])) {
            return trim($_GET['key']);
        }
        if (!empty($_GET['token'])) {
            return trim($_GET['token']);
        }

        return '';
    }

    /**
     * Validate an incoming raw key string against active configured keys
     *
     * @param string|null $rawKey
     * @return array [valid => bool, code => int, error => string|null, key => array|null]
     */
    public static function validateKey(?string $rawKey = null): array {
        if ($rawKey === null) {
            $rawKey = self::extractKeyFromRequest();
        }
        $rawKey = trim($rawKey);

        if (empty($rawKey)) {
            return [
                'valid' => false,
                'code' => 401,
                'error' => 'API key is required. Provide via "Authorization: Bearer <key>" header or "?key=<key>" parameter.',
                'key' => null
            ];
        }

        $keys = self::listKeys();
        $matchedKey = null;

        foreach ($keys as $k) {
            if (!empty($k['key']) && hash_equals($k['key'], $rawKey)) {
                $matchedKey = $k;
                break;
            }
        }

        if (!$matchedKey) {
            return [
                'valid' => false,
                'code' => 401,
                'error' => 'Invalid API key provided.',
                'key' => null
            ];
        }

        if (($matchedKey['status'] ?? 'active') === 'revoked') {
            return [
                'valid' => false,
                'code' => 403,
                'error' => 'API key has been revoked by the administrator.',
                'key' => $matchedKey
            ];
        }

        if (!empty($matchedKey['expiresAt']) && strtotime($matchedKey['expiresAt']) < time()) {
            return [
                'valid' => false,
                'code' => 401,
                'error' => 'API key has expired. Please request an extension from the administrator.',
                'key' => $matchedKey
            ];
        }

        return [
            'valid' => true,
            'code' => 200,
            'error' => null,
            'key' => $matchedKey
        ];
    }

    /**
     * Update the lastUsedAt timestamp for a key
     *
     * @param string $keyId
     */
    public static function updateLastUsed(string $keyId): void {
        $config = Config::load();
        $keys = $config['llmKeys'] ?? [];
        $found = false;

        foreach ($keys as &$key) {
            if (($key['id'] ?? '') === $keyId) {
                // Throttle updates if updated within the last 60 seconds
                if (!empty($key['lastUsedAt']) && (time() - strtotime($key['lastUsedAt'])) < 60) {
                    return;
                }
                $key['lastUsedAt'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }
        unset($key);

        if ($found) {
            $config['llmKeys'] = $keys;
            Config::save($config);
        }
    }

    /**
     * Resolve effective node visibility inheriting from parent
     */
    public static function resolveEffectiveVisibility($nodeVisibility, $parentVisibility = 'public'): string {
        if ($parentVisibility === 'admin_only' || $nodeVisibility === 'admin_only') {
            return 'admin_only';
        }
        if ($parentVisibility === 'logged_in' || $nodeVisibility === 'logged_in') {
            return 'logged_in';
        }
        return $nodeVisibility ?? 'public';
    }

    /**
     * Check if a node is accessible with standard viewer permissions
     */
    public static function isNodeAccessible($node): bool {
        $vis = $node['visibility'] ?? 'public';
        if ($vis === 'admin_only') {
            return false;
        }
        return true;
    }

    /**
     * Detect item type based on file extension, URL, or explicit config type
     */
    public static function detectItemType(array $item): string {
        $file = $item['file'] ?? '';
        $url  = $item['url'] ?? '';
        $type = isset($item['type']) ? strtolower(trim($item['type'])) : '';

        if (!empty($file) && preg_match('/\.pdf($|\?|#)/i', $file)) {
            return 'pdf';
        }
        if (!empty($url) && preg_match('/\.pdf($|\?|#)/i', $url)) {
            return 'pdf';
        }
        if (!empty($url) && preg_match('/docs\.google\.com|drive\.google\.com/i', $url)) {
            return 'gdoc';
        }
        if (!empty($file) && preg_match('/\.(html|htm)($|\?|#)/i', $file)) {
            return 'html';
        }
        if (!empty($file) && preg_match('/\.(md|markdown)($|\?|#)/i', $file)) {
            return 'markdown';
        }

        if (!empty($type)) {
            if (in_array($type, ['pdf', 'pdf_document', 'pdf-document'])) return 'pdf';
            if (in_array($type, ['gdoc', 'googledoc', 'google-doc'])) return 'gdoc';
            if (in_array($type, ['html', 'htm'])) return 'html';
            if (in_array($type, ['md', 'markdown', 'text'])) return 'markdown';
        }

        return 'markdown';
    }

    /**
     * Check if document type is permitted under the key's allowed types
     */
    public static function isTypePermitted(string $docType, array $keyRecord): bool {
        $allowed = $keyRecord['allowedTypes'] ?? ['markdown'];
        $cleanType = strtolower($docType);
        if ($cleanType === 'md') $cleanType = 'markdown';
        return in_array($cleanType, $allowed);
    }

    /**
     * Build the authorized document tree for Mode 1
     *
     * @param array $keyRecord Validated key record
     * @param string|null $categoryFilter Optional category query filter
     * @return array Array of scoped document items with metadata
     */
    public static function buildDocumentTree(array $keyRecord, ?string $categoryFilter = null): array {
        $config = Config::load();
        $books = $config['books'] ?? [];
        $baseDir = Config::getBaseDir();
        $baseUrl = Config::getBaseUrl();

        $keyCategory = trim($keyRecord['category'] ?? '');
        $requestedCategory = trim($categoryFilter ?? '');

        // Determine effective root category scope
        $targetScopeId = '';
        if (!empty($keyCategory) && $keyCategory !== 'all') {
            $targetScopeId = $keyCategory;
        }

        $documents = [];

        $traverse = function($nodes, $bookId, $folderId, $parentVisibility, $inAuthorizedScope, $categoryBreadcrumb) use (&$traverse, &$documents, $keyRecord, $targetScopeId, $requestedCategory, $baseDir, $baseUrl) {
            foreach ($nodes as $node) {
                $nodeId = $node['id'] ?? $node['slug'] ?? '';
                $nodeTitle = $node['title'] ?? $nodeId;
                $effectiveVis = self::resolveEffectiveVisibility($node['visibility'] ?? null, $parentVisibility);

                if ($effectiveVis === 'admin_only') {
                    continue;
                }

                $currentBook = ($bookId === null) ? $nodeId : $bookId;
                $currentFolder = (isset($node['type']) && strtolower($node['type']) === 'folder') ? ($nodeId ?: $folderId) : $folderId;

                $currentBreadcrumb = array_merge($categoryBreadcrumb, [$nodeTitle]);

                // Check key-level scope entry
                $keyScopeEntered = false;
                if (!empty($targetScopeId)) {
                    if (strcasecmp((string)$nodeId, (string)$targetScopeId) === 0 || strcasecmp((string)$nodeTitle, (string)$targetScopeId) === 0) {
                        $keyScopeEntered = true;
                    }
                } else {
                    $keyScopeEntered = true;
                }

                $nowInScope = $inAuthorizedScope || $keyScopeEntered;

                $isFolder = (isset($node['type']) && strtolower($node['type']) === 'folder') || !empty($node['items']);

                if ($isFolder) {
                    if (!empty($node['items']) && is_array($node['items'])) {
                        $traverse($node['items'], $currentBook, $currentFolder, $effectiveVis, $nowInScope, $currentBreadcrumb);
                    }
                } else {
                    // Leaf document node
                    if (!$nowInScope) {
                        continue;
                    }

                    // Hyperlinks are external/pointers, skip from document analysis
                    if (($node['type'] ?? '') === 'link') {
                        continue;
                    }

                    // If user supplied a sub-category filter via query param, verify match
                    if (!empty($requestedCategory) && $requestedCategory !== 'all') {
                        $matchReq = (strcasecmp((string)$currentFolder, (string)$requestedCategory) === 0
                            || strcasecmp((string)$currentBook, (string)$requestedCategory) === 0);
                        if (!$matchReq) {
                            $breadcrumbMatch = false;
                            foreach ($currentBreadcrumb as $bc) {
                                if (strcasecmp((string)$bc, (string)$requestedCategory) === 0) {
                                    $breadcrumbMatch = true;
                                    break;
                                }
                            }
                            if (!$breadcrumbMatch) {
                                continue;
                            }
                        }
                    }

                    $resolvedType = self::detectItemType($node);
                    if (!self::isTypePermitted($resolvedType, $keyRecord)) {
                        continue;
                    }

                    $itemSlug = $node['slug'] ?? $node['id'] ?? '';
                    if (empty($itemSlug)) {
                        continue;
                    }

                    // File stats & mtime
                    $filePath = !empty($node['file']) ? $node['file'] : '';
                    $mtime = null;
                    $fileSize = 0;
                    $wordCount = 0;

                    if (!empty($filePath)) {
                        $safePath = Config::safePath($baseDir, $filePath);
                        if ($safePath && file_exists($safePath)) {
                            $mtime = filemtime($safePath);
                            $fileSize = filesize($safePath);

                            if ($resolvedType === 'markdown' || $resolvedType === 'html') {
                                $raw = @file_get_contents($safePath);
                                if ($raw !== false) {
                                    $cleanText = ($resolvedType === 'html') ? strip_tags($raw) : $raw;
                                    $wordCount = str_word_count($cleanText);
                                }
                            }
                        }
                    }

                    // Construct web URL
                    $webUrl = $baseUrl . urlencode($currentBook);
                    if ($currentFolder && $currentFolder !== $currentBook) {
                        $webUrl .= '/' . urlencode($currentFolder);
                    }
                    $webUrl .= '/' . urlencode($itemSlug);

                    // Construct direct API URL for Mode 2
                    $tokenParam = '?mode=doc&slug=' . urlencode($itemSlug);
                    $apiUrl = $baseUrl . 'api/llm.php' . $tokenParam;

                    $documents[] = [
                        'title' => $node['title'] ?? '',
                        'slug' => $itemSlug,
                        'description' => $node['description'] ?? $node['desc'] ?? '',
                        'type' => $resolvedType,
                        'category_id' => $currentFolder ?: $currentBook,
                        'breadcrumb' => $categoryBreadcrumb,
                        'url' => $webUrl,
                        'api_url' => $apiUrl,
                        'date_modified' => date('c', $mtime ?: time()),
                        'mtime' => $mtime ?: time(),
                        'size_bytes' => $fileSize,
                        'word_count' => $wordCount,
                        'estimated_tokens' => (int)ceil($fileSize / 4)
                    ];
                }
            }
        };

        $traverse($books, null, null, 'public', empty($targetScopeId), []);

        return $documents;
    }

    /**
     * Retrieve document content for Mode 2
     *
     * @param array $keyRecord Validated key record
     * @param string $slug Document slug
     * @param int $offset Byte offset for partial reading (default 0)
     * @param int $limit Maximum bytes to return (0 for all)
     * @return array [success => bool, code => int, error => string|null, document => array|null]
     */
    public static function retrieveDocument(array $keyRecord, string $slug, int $offset = 0, int $limit = 0): array {
        $slug = trim($slug);
        if (empty($slug)) {
            return ['success' => false, 'code' => 400, 'error' => 'Document slug is required for mode=doc.'];
        }

        // Get the scoped tree for this key
        $scopedDocs = self::buildDocumentTree($keyRecord);
        $matchedDocMeta = null;

        foreach ($scopedDocs as $doc) {
            if ($doc['slug'] === $slug) {
                $matchedDocMeta = $doc;
                break;
            }
        }

        if (!$matchedDocMeta) {
            // Check if document exists anywhere in the wiki to distinguish 404 from 403
            $config = Config::load();
            $fullChapter = Navigation::findChapterBySlug($config['books'] ?? [], $slug);

            if ($fullChapter) {
                $resolvedType = self::detectItemType($fullChapter);
                if (!self::isTypePermitted($resolvedType, $keyRecord)) {
                    return [
                        'success' => false,
                        'code' => 403,
                        'error' => "Document type '{$resolvedType}' is not permitted for this API key."
                    ];
                }
                return [
                    'success' => false,
                    'code' => 403,
                    'error' => 'Document access denied. The requested document is outside the authorized branch/category of this key.'
                ];
            }

            return [
                'success' => false,
                'code' => 404,
                'error' => "Document with slug '{$slug}' not found."
            ];
        }

        $baseDir = Config::getBaseDir();
        $baseUrl = Config::getBaseUrl();
        $config = Config::load();
        $chapter = Navigation::findChapterBySlug($config['books'] ?? [], $slug);

        $resolvedType = $matchedDocMeta['type'];
        $filePath = !empty($chapter['file']) ? $chapter['file'] : '';
        $content = '';
        $rawSize = 0;

        if ($resolvedType === 'markdown') {
            if (empty($filePath)) {
                return ['success' => false, 'code' => 404, 'error' => 'Markdown file path is not configured.'];
            }

            $safePath = Config::safePath($baseDir, $filePath);
            if (!$safePath || !file_exists($safePath)) {
                return ['success' => false, 'code' => 404, 'error' => 'Markdown source file does not exist on disk.'];
            }

            $content = file_get_contents($safePath);
            $rawSize = strlen($content);

            // Rewrite relative markdown links: [text](path) or ![alt](path)
            $content = preg_replace_callback('/(\[.*?\]\()([^\)]+)(\))/i', function($matches) use ($baseUrl) {
                $url = trim($matches[2]);
                if (!preg_match('/^(http|https|mailto|ftp|\/|#)/i', $url)) {
                    return $matches[1] . $baseUrl . $url . $matches[3];
                }
                return $matches[0];
            }, $content);

            // Rewrite relative HTML links: src="path" or href="path"
            $content = preg_replace_callback('/(src=["\']|href=["\'])([^"\']+)(["\'])/i', function($matches) use ($baseUrl) {
                $url = trim($matches[2]);
                if (!preg_match('/^(http|https|mailto|data:|\/|#)/i', $url)) {
                    return $matches[1] . $baseUrl . $url . $matches[3];
                }
                return $matches[0];
            }, $content);

        } elseif ($resolvedType === 'html') {
            $safePath = Config::safePath($baseDir, $filePath);
            if ($safePath && file_exists($safePath)) {
                $rawHtml = file_get_contents($safePath);
                $rawSize = strlen($rawHtml);
                $cleanHtml = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $rawHtml);
                $cleanHtml = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $cleanHtml);
                $content = trim(strip_tags($cleanHtml));
            } else {
                $content = 'HTML document content unavailable.';
            }
        } elseif ($resolvedType === 'pdf') {
            $pdfUrl = !empty($chapter['file']) ? $chapter['file'] : ($chapter['url'] ?? '');
            if (!preg_match('/^(http|https):\/\//i', $pdfUrl)) {
                $pdfUrl = $baseUrl . ltrim($pdfUrl, '/');
            }
            $content = "[Binary PDF Document]\nDownload URL: " . $pdfUrl;
            $rawSize = strlen($content);
        } elseif ($resolvedType === 'gdoc') {
            $gdocUrl = $chapter['url'] ?? '';
            $content = "[Google Document]\nURL: " . $gdocUrl;
            $rawSize = strlen($content);
        }

        // Apply offset and limit if specified
        $isTruncated = false;
        if ($offset > 0 || $limit > 0) {
            $totalLen = strlen($content);
            if ($offset >= $totalLen) {
                $content = '';
            } else {
                if ($limit > 0) {
                    $content = substr($content, $offset, $limit);
                    if (($offset + $limit) < $totalLen) {
                        $isTruncated = true;
                    }
                } else {
                    $content = substr($content, $offset);
                }
            }
        }

        $wordCount = str_word_count($content);
        $tokenEst = (int)ceil(strlen($content) / 4);

        return [
            'success' => true,
            'code' => 200,
            'document' => [
                'title' => $matchedDocMeta['title'],
                'slug' => $slug,
                'description' => $matchedDocMeta['description'],
                'type' => $resolvedType,
                'category' => $matchedDocMeta['category_id'],
                'breadcrumb' => $matchedDocMeta['breadcrumb'],
                'url' => $matchedDocMeta['url'],
                'date_modified' => $matchedDocMeta['date_modified'],
                'total_bytes' => $rawSize,
                'returned_bytes' => strlen($content),
                'word_count' => $wordCount,
                'estimated_tokens' => $tokenEst,
                'offset' => $offset,
                'is_truncated' => $isTruncated,
                'content' => $content
            ]
        ];
    }

    /**
     * Search documents within key's authorized scope (Mode 3)
     *
     * @param array $keyRecord Validated key record
     * @param string $query Search query
     * @param int $limit Maximum results to return
     * @return array
     */
    public static function searchDocuments(array $keyRecord, string $query, int $limit = 10): array {
        $query = strtolower(trim($query));
        if (empty($query)) {
            return [];
        }

        $scopedDocs = self::buildDocumentTree($keyRecord);
        $baseDir = Config::getBaseDir();
        $extManager = ExtensionManager::getInstance();
        $config = Config::load();

        $matches = [];
        foreach ($scopedDocs as $docMeta) {
            $slug = $docMeta['slug'];
            $title = strtolower($docMeta['title']);
            $desc = strtolower($docMeta['description']);

            $score = 0;
            $excerpt = '';

            // 1. Exact title match
            if ($title === $query) {
                $score += 100;
            } elseif (strpos($title, $query) !== false) {
                $score += 50;
            }

            // 2. Description match
            if (strpos($desc, $query) !== false) {
                $score += 30;
                $excerpt = $docMeta['description'];
            }

            // 3. Content match
            $chapter = Navigation::findChapterBySlug($config['books'] ?? [], $slug);
            if ($chapter) {
                $extracted = $extManager->extractSearchableText($chapter, $baseDir);
                if (!empty($extracted)) {
                    $pos = stripos($extracted, $query);
                    if ($pos !== false) {
                        $score += 15;
                        if (empty($excerpt)) {
                            $start = max(0, $pos - 60);
                            $excerpt = '...' . trim(substr($extracted, $start, 160)) . '...';
                        }
                    }
                }
            }

            if ($score > 0) {
                $matches[] = [
                    'title' => $docMeta['title'],
                    'slug' => $docMeta['slug'],
                    'description' => $docMeta['description'],
                    'type' => $docMeta['type'],
                    'url' => $docMeta['url'],
                    'api_url' => $docMeta['api_url'],
                    'relevance_score' => $score,
                    'excerpt' => $excerpt ?: $docMeta['description']
                ];
            }
        }

        // Sort by score descending
        usort($matches, function($a, $b) {
            return $b['relevance_score'] - $a['relevance_score'];
        });

        if ($limit > 0) {
            $matches = array_slice($matches, 0, $limit);
        }

        return $matches;
    }

    /**
     * Render document tree as llms.txt compatible Markdown format
     */
    public static function renderLlmsTxt(array $treeDocs, string $wikiTitle, string $siteDescription = ''): string {
        $out = "# " . $wikiTitle . "\n\n";
        if (!empty($siteDescription)) {
            $out .= "> " . $siteDescription . "\n\n";
        }
        $out .= "This index contains documentation pages available for AI agents and LLMs.\n\n";
        $out .= "## Available Documents\n\n";

        foreach ($treeDocs as $doc) {
            $title = $doc['title'] ?? 'Untitled';
            $apiUrl = $doc['api_url'] ?? '';
            $desc = $doc['description'] ?? '';
            $type = strtoupper($doc['type'] ?? 'MD');

            $line = "- [{$title}]({$apiUrl}) [{$type}]";
            if (!empty($desc)) {
                $line .= ": {$desc}";
            }
            $out .= $line . "\n";
        }

        return $out;
    }

    /**
     * Generate OpenAPI 3.0.3 specification for ChatGPT Actions / Claude Custom Tools
     */
    public static function getOpenApiSpec(string $baseUrl): array {
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Standalone Qwiki LLM & Agent API',
                'description' => 'API for autonomous AI agents, LLMs, and RAG pipelines to explore document hierarchy and retrieve content.',
                'version' => Config::VERSION
            ],
            'servers' => [
                ['url' => rtrim($baseUrl, '/')]
            ],
            'paths' => [
                '/api/llm.php' => [
                    'get' => [
                        'summary' => 'Execute LLM queries, tree inspection, and document retrieval',
                        'operationId' => 'qwikiLlmQuery',
                        'parameters' => [
                            [
                                'name' => 'mode',
                                'in' => 'query',
                                'required' => true,
                                'description' => 'Operation mode: "tree" to list documents, "doc" to fetch document contents, "search" to query documents.',
                                'schema' => [
                                    'type' => 'string',
                                    'enum' => ['tree', 'doc', 'search']
                                ]
                            ],
                            [
                                'name' => 'slug',
                                'in' => 'query',
                                'required' => false,
                                'description' => 'Document slug (required for mode=doc).',
                                'schema' => ['type' => 'string']
                            ],
                            [
                                'name' => 'q',
                                'in' => 'query',
                                'required' => false,
                                'description' => 'Search term (required for mode=search).',
                                'schema' => ['type' => 'string']
                            ],
                            [
                                'name' => 'category',
                                'in' => 'query',
                                'required' => false,
                                'description' => 'Optional category ID to filter tree or search.',
                                'schema' => ['type' => 'string']
                            ],
                            [
                                'name' => 'format',
                                'in' => 'query',
                                'required' => false,
                                'description' => 'Output format for mode=tree ("json" or "llms.txt").',
                                'schema' => ['type' => 'string', 'default' => 'json']
                            ],
                            [
                                'name' => 'offset',
                                'in' => 'query',
                                'required' => false,
                                'description' => 'Character/byte offset for partial reading (mode=doc).',
                                'schema' => ['type' => 'integer', 'default' => 0]
                            ],
                            [
                                'name' => 'limit',
                                'in' => 'query',
                                'required' => false,
                                'description' => 'Max bytes/characters to return (mode=doc).',
                                'schema' => ['type' => 'integer']
                            ]
                        ],
                        'security' => [
                            ['ApiKeyAuth' => []]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Successful operation',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['type' => 'object']
                                    ],
                                    'text/markdown' => [
                                        'schema' => ['type' => 'string']
                                    ]
                                ]
                            ],
                            '401' => ['description' => 'Unauthorized or expired API key'],
                            '403' => ['description' => 'Forbidden - document outside authorized branch or type'],
                            '404' => ['description' => 'Document not found']
                        ]
                    ]
                ]
            ],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'qwk_llm_*'
                    ]
                ]
            ]
        ];
    }
}
