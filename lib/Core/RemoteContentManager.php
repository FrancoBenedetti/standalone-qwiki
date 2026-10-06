<?php
namespace Qwiki\Core;

class RemoteContentManager {

    /**
     * Cache TTL in seconds (default: 1 hour).
     */
    const DEFAULT_TTL = 3600;

    /**
     * Validate URL scheme and protect against Server-Side Request Forgery (SSRF).
     *
     * @param string $url The remote URL to validate
     * @return bool True if the URL is safe to fetch
     */
    public static function validateUrlSafety(string $url): bool {
        $trimmed = trim($url);
        if (empty($trimmed)) {
            return false;
        }

        $parts = parse_url($trimmed);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host']);

        // Check test bypass mode if enabled explicitly in testing environments
        if (defined('QWIKI_TEST_ALLOW_LOCALHOST') && QWIKI_TEST_ALLOW_LOCALHOST) {
            return true;
        }

        // Block literal localhost and link-local hostnames
        if ($host === 'localhost' || $host === 'localhost.localdomain' || substr($host, -6) === '.local') {
            return false;
        }

        // Resolve DNS to IP address
        $ip = gethostbyname($host);
        if (!$ip || ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP))) {
            // DNS resolution failed or invalid host
            return false;
        }

        // Validate IP is not private, loopback, or reserved
        return self::isPublicIp($ip);
    }

    /**
     * Check if an IP address is a public, non-reserved, non-loopback address.
     *
     * @param string $ip
     * @return bool
     */
    public static function isPublicIp(string $ip): bool {
        // FILTER_FLAG_NO_PRIV_RANGE: Rejects RFC 1918 (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16) and RFC 4193
        // FILTER_FLAG_NO_RES_RANGE: Rejects loopback (127.0.0.0/8, ::1), link-local (169.254.0.0/16), broadcast, etc.
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        return filter_var($ip, FILTER_VALIDATE_IP, $flags) !== false;
    }

    /**
     * Resolve the filesystem cache file path for a given remote URL.
     *
     * @param string $url
     * @return string
     */
    public static function getCacheFilePath(string $url): string {
        $baseDir = Config::getBaseDir();
        $cacheDir = $baseDir . '/uploads/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $hash = md5($url);
        return $cacheDir . '/remote_' . $hash . '.json';
    }

    /**
     * Fetch a remote document via its sharelink, utilizing local caching and fallback.
     *
     * @param string $url Remote share URL
     * @param int $ttl Cache validity in seconds
     * @param bool $forceRefresh Force a fresh network fetch
     * @return array Array containing content, metadata, and status
     */
    public static function fetchRemoteDocument(string $url, int $ttl = self::DEFAULT_TTL, bool $forceRefresh = false): array {
        $cacheFile = self::getCacheFilePath($url);
        $cachedData = null;

        if (file_exists($cacheFile)) {
            $raw = @file_get_contents($cacheFile);
            if ($raw) {
                $cachedData = @json_decode($raw, true);
            }
        }

        $now = time();
        if (!$forceRefresh && $cachedData && is_array($cachedData) && !empty($cachedData['success'])) {
            $cachedAt = (int)($cachedData['cached_at'] ?? 0);
            if (($now - $cachedAt) < $ttl) {
                $cachedData['is_stale'] = false;
                $cachedData['from_cache'] = true;
                return $cachedData;
            }
        }

        // Safety verification
        if (!self::validateUrlSafety($url)) {
            if ($cachedData && is_array($cachedData) && !empty($cachedData['success'])) {
                $cachedData['is_stale'] = true;
                $cachedData['warning'] = 'Remote URL failed security validation. Serving last known cached copy.';
                return $cachedData;
            }
            return [
                'success' => false,
                'error' => 'Security check failed: Remote URL points to a forbidden or private address.',
                'content' => '',
                'title' => ''
            ];
        }

        // Construct request URL: ensure &format=json is present
        $requestUrl = $url;
        if (strpos($requestUrl, 'format=json') === false) {
            $requestUrl .= (strpos($requestUrl, '?') !== false) ? '&format=json' : '?format=json';
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 6,
                'header' => "Accept: application/json\r\nUser-Agent: Qwiki-Federated-Reader/1.0\r\n",
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ]);

        $response = @file_get_contents($requestUrl, false, $ctx);

        if ($response !== false) {
            $json = @json_decode($response, true);
            if (is_array($json) && !empty($json['success'])) {
                $originUrl = $json['origin'] ?? self::extractOriginFromUrl($url);
                $rawContent = $json['content'] ?? '';
                
                // Rewrite relative image paths to origin base URL
                $rewrittenContent = self::rewriteRelativeAssets($rawContent, $originUrl);

                $payload = [
                    'success' => true,
                    'title' => $json['title'] ?? '',
                    'slug' => $json['slug'] ?? '',
                    'type' => $json['type'] ?? 'markdown',
                    'content' => $rewrittenContent,
                    'description' => $json['description'] ?? '',
                    'origin' => $originUrl,
                    'remote_last_modified' => $json['lastModified'] ?? null,
                    'cached_at' => $now,
                    'is_stale' => false
                ];

                @file_put_contents($cacheFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return $payload;
            }
        }

        // Network or parse error: fallback to stale cache if available
        if ($cachedData && is_array($cachedData) && !empty($cachedData['success'])) {
            $cachedData['is_stale'] = true;
            $cachedData['warning'] = 'Origin wiki is unreachable or returned an invalid response. Displaying cached version.';
            return $cachedData;
        }

        return [
            'success' => false,
            'error' => 'Unable to reach the origin wiki sharelink, and no cached copy is available.',
            'content' => '',
            'title' => ''
        ];
    }

    /**
     * Extract protocol and host from a URL to use as origin base.
     *
     * @param string $url
     * @return string
     */
    public static function extractOriginFromUrl(string $url): string {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';
        
        // If path points to an index.php or directory, include directory path
        $dir = rtrim(dirname($path), '/\\');
        if ($dir === '.' || $dir === '') {
            $dir = '';
        }

        return $scheme . '://' . $host . $port . $dir;
    }

    /**
     * Rewrite relative image references in Markdown and HTML to point to origin wiki.
     *
     * @param string $content
     * @param string $originUrl
     * @return string
     */
    public static function rewriteRelativeAssets(string $content, string $originUrl): string {
        if (empty($originUrl) || empty($content)) {
            return $content;
        }
        $originUrl = rtrim($originUrl, '/');

        // 1. Rewrite Markdown images: ![alt](relative/path)
        // Matches anything not starting with http://, https://, //, or data:
        $content = preg_replace_callback(
            '/!\[(.*?)\]\(((?!https?:\/\/|\/\/|data:)(.*?))\)/i',
            function ($matches) use ($originUrl) {
                $alt = $matches[1];
                $src = ltrim($matches[2], './');
                return "![" . $alt . "](" . $originUrl . '/' . $src . ")";
            },
            $content
        );

        // 2. Rewrite HTML <img> tags: <img ... src="relative/path" ...>
        $content = preg_replace_callback(
            '/<img\b([^>]*?)\bsrc=["\']((?!https?:\/\/|\/\/|data:)[^"\']+)["\']([^>]*?)>/i',
            function ($matches) use ($originUrl) {
                $before = $matches[1];
                $src = ltrim($matches[2], './');
                $after = $matches[3];
                return '<img' . $before . 'src="' . htmlspecialchars($originUrl . '/' . $src) . '"' . $after . '>';
            },
            $content
        );

        return $content;
    }

    /**
     * Render the remote document with provenance banner and native styling.
     *
     * @param array $chapter Chapter configuration node
     * @param array $config Global wiki configuration
     * @return string Rendered HTML
     */
    public static function renderRemotePage(array $chapter, array $config): string {
        $url = $chapter['url'] ?? '';
        $cacheTtl = (int)($chapter['cacheTtl'] ?? self::DEFAULT_TTL);
        $slug = $chapter['slug'] ?? '';
        $isAdmin = Auth::isAdmin();

        if (empty($url)) {
            return "<div class='alert warning'>Remote document error: No sharelink URL configured.</div>";
        }

        $result = self::fetchRemoteDocument($url, $cacheTtl);

        $html = '';

        // Provenance & Single-Source-of-Truth Banner
        $origin = htmlspecialchars($result['origin'] ?? self::extractOriginFromUrl($url));
        $cachedTimeStr = !empty($result['cached_at']) ? date('Y-m-d H:i:s', $result['cached_at']) : 'just now';

        $html .= "<div class='remote-doc-banner' style='background: var(--bg-secondary, #252830); border: 1px solid var(--border-color, #383e4a); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;'>";
        $html .= "<div style='display: flex; align-items: center; gap: 0.5rem;'>";
        $html .= "<span style='font-size: 1.1rem;'>🌐</span>";
        $html .= "<span style='font-size: 0.9rem;'><strong>Federated Document</strong> (Single Source of Truth) &bull; Origin: <a href='" . htmlspecialchars($url) . "' target='_blank' rel='noopener noreferrer' style='color: var(--accent-color, #4a9eff); font-weight: 500; text-decoration: underline;'>" . $origin . "</a></span>";
        $html .= "</div>";

        $html .= "<div style='display: flex; align-items: center; gap: 0.5rem;'>";
        $html .= "<span style='font-size: 0.8rem; color: var(--text-muted, #8b949e);'>Synced: {$cachedTimeStr}</span>";
        if ($isAdmin) {
            $html .= "<button type='button' class='btn btn-outline btn-sm btn-refresh-remote-cache' data-slug='" . htmlspecialchars($slug) . "' style='padding: 0.2rem 0.5rem; font-size: 0.8rem; cursor: pointer;'>🔄 Refresh</button>";
        }
        $html .= "</div>";
        $html .= "</div>";

        // Stale / Offline Warning Banner
        if (!empty($result['is_stale'])) {
            $html .= "<div class='alert warning' style='margin-bottom: 1.5rem;'>";
            $html .= "<strong>Notice:</strong> Origin server is unreachable or offline. Displaying cached version from " . htmlspecialchars($cachedTimeStr) . ".";
            $html .= "</div>";
        }

        // Error display if fetch failed completely
        if (empty($result['success'])) {
            $errorMsg = htmlspecialchars($result['error'] ?? 'Unknown remote error');
            $html .= "<div class='alert danger' style='margin-bottom: 1.5rem;'>";
            $html .= "<strong>Federation Error:</strong> {$errorMsg}<br>";
            if ($isAdmin) {
                $html .= "<button type='button' class='btn btn-outline btn-sm btn-refresh-remote-cache' data-slug='" . htmlspecialchars($slug) . "' style='margin-top: 0.5rem;'>Retry Fetch</button>";
            }
            $html .= "</div>";
            return $html;
        }

        // Render Markdown content natively
        $content = $result['content'] ?? '';
        $parsedown = class_exists('\QwikiParsedown') ? new \QwikiParsedown() : new \Parsedown();
        $rendered = $parsedown->text($content);

        $html .= "<div class='remote-doc-content'>" . $rendered . "</div>";
        return $html;
    }

    /**
     * Extract searchable plain text from the cached remote document.
     *
     * @param array $chapter
     * @return string
     */
    public static function extractSearchableText(array $chapter): string {
        $url = $chapter['url'] ?? '';
        if (empty($url)) {
            return '';
        }

        $cacheFile = self::getCacheFilePath($url);
        if (file_exists($cacheFile)) {
            $raw = @file_get_contents($cacheFile);
            if ($raw) {
                $json = @json_decode($raw, true);
                if (is_array($json) && !empty($json['content'])) {
                    return strip_tags($json['content']);
                }
            }
        }

        // If not cached, attempt a fetch
        $fetched = self::fetchRemoteDocument($url);
        if (!empty($fetched['success']) && !empty($fetched['content'])) {
            return strip_tags($fetched['content']);
        }

        return '';
    }

    /**
     * Warm/ensure cache for all remote documents across the tree.
     *
     * @param array $books
     */
    public static function warmAllRemoteCaches(array $books): void {
        foreach ($books as $node) {
            self::warmNodeRemoteCaches($node);
        }
    }

    private static function warmNodeRemoteCaches(array $node): void {
        if (!empty($node['items']) && is_array($node['items'])) {
            foreach ($node['items'] as $item) {
                if (isset($item['type']) && $item['type'] === 'remote' && !empty($item['url'])) {
                    $cacheFile = self::getCacheFilePath($item['url']);
                    if (!file_exists($cacheFile)) {
                        self::fetchRemoteDocument($item['url'], (int)($item['cacheTtl'] ?? self::DEFAULT_TTL));
                    }
                } elseif (isset($item['type']) && $item['type'] === 'folder') {
                    self::warmNodeRemoteCaches($item);
                }
            }
        }
    }
}
