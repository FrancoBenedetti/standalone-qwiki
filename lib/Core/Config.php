<?php
namespace Qwiki\Core;

class Config {
    const VERSION = '1.15.0';

    private static $baseDir = null;
    private static $configFile = null;
    private static $usersFile = null;

    public static function init($baseDir = null) {
        if ($baseDir === null) {
            $baseDir = defined('QWIKI_BASE_DIR') ? QWIKI_BASE_DIR : dirname(dirname(__DIR__));
        }
        self::$baseDir = rtrim($baseDir, '/\\');
        self::$configFile = self::$baseDir . '/qwiki.json';
        self::$usersFile = self::$baseDir . '/users.json';
    }

    public static function getBaseDir() {
        if (self::$baseDir === null) {
            self::init();
        }
        return self::$baseDir;
    }

    public static function getConfigFile() {
        if (self::$configFile === null) {
            self::init();
        }
        return self::$configFile;
    }

    public static function getUsersFile() {
        if (self::$usersFile === null) {
            self::init();
        }
        return self::$usersFile;
    }

    public static function ensureSetup() {
        $configFile = self::getConfigFile();
        if (file_exists($configFile)) {
            return true;
        }

        $baseDir = self::getBaseDir();
        $demoDir = $baseDir . '/demo-data';

        if (file_exists($demoDir . '/qwiki-default.json')) {
            if (!is_dir($baseDir . '/uploads')) {
                @mkdir($baseDir . '/uploads', 0755, true);
            }
            if (!is_dir($baseDir . '/content')) {
                @mkdir($baseDir . '/content', 0755, true);
            }

            self::copyDir($demoDir . '/content', $baseDir . '/content');

            $copied = @copy($demoDir . '/qwiki-default.json', $configFile);
            if (!$copied) {
                return false;
            }

            if (file_exists($demoDir . '/users-default.json') && !file_exists($baseDir . '/users.json')) {
                @copy($demoDir . '/users-default.json', $baseDir . '/users.json');
            }

            if (file_exists($demoDir . '/htaccess-uploads') && is_dir($baseDir . '/uploads')) {
                @copy($demoDir . '/htaccess-uploads', $baseDir . '/uploads/.htaccess');
            }
            return true;
        }
        return false;
    }

    public static function load() {
        $configFile = self::getConfigFile();
        if (!file_exists($configFile)) {
            self::ensureSetup();
        }
        if (!file_exists($configFile)) {
            return ['title' => 'Standalone Qwiki', 'books' => []];
        }
        $data = json_decode(file_get_contents($configFile), true);
        if (is_array($data)) {
            if (isset($data['books']) && is_array($data['books'])) {
                if (self::normalizeBooks($data['books'])) {
                    self::save($data);
                }
            }
            return $data;
        }
        return ['title' => 'Standalone Qwiki', 'books' => []];
    }

    /**
     * Normalizes the root books structure by rescuing stranded non-link documents into categories
     * and pruning invalid phantom categories that lack an ID and content.
     *
     * @param array $books
     * @return bool True if any modifications were made
     */
    public static function normalizeBooks(array &$books): bool {
        if (empty($books) || !is_array($books)) {
            return false;
        }

        $modified = false;
        $cleanBooks = [];
        $strandedDocs = [];

        // First pass: separate valid top-level items from stranded documents and empty phantom categories
        foreach ($books as $node) {
            if (!is_array($node)) continue;

            $nodeId = trim($node['id'] ?? '');
            $nodeSlug = trim($node['slug'] ?? '');
            $nodeType = trim($node['type'] ?? '');

            // 1. Valid Top-Level Link
            if ($nodeType === 'link' || (!empty($nodeSlug) && !empty($node['url']) && empty($nodeId) && empty($node['items']))) {
                if ($nodeType !== 'link') {
                    $node['type'] = 'link';
                    $modified = true;
                }
                $cleanBooks[] = $node;
                continue;
            }

            // 2. Stranded non-link document at root level (has slug and no id)
            if (empty($nodeId) && !empty($nodeSlug)) {
                $strandedDocs[] = $node;
                $modified = true;
                continue;
            }

            // 3. Phantom category with empty id and no slug
            if (empty($nodeId) && empty($nodeSlug)) {
                // If it has items, assign a generated ID so it doesn't stay broken
                if (!empty($node['items']) && is_array($node['items'])) {
                    $generatedId = !empty($node['title']) ? preg_replace('/[^a-z0-9_-]/', '', strtolower(str_replace(' ', '-', (string)$node['title']))) : '';
                    if (empty($generatedId)) $generatedId = 'category-' . uniqid();
                    $node['id'] = $generatedId;
                    $node['type'] = 'folder';
                    $cleanBooks[] = $node;
                    $modified = true;
                } else {
                    // Empty phantom category without items and without ID: prune it!
                    $modified = true;
                }
                continue;
            }

            // 4. Regular Category / Book
            $cleanBooks[] = $node;
        }

        // If there are stranded documents, rescue them by placing them into the appropriate category
        if (!empty($strandedDocs)) {
            foreach ($strandedDocs as $doc) {
                $placed = false;
                $docFile = $doc['file'] ?? '';

                // Try to match category by file prefix: content/{categoryFolder}/...
                if (!empty($docFile) && strpos($docFile, 'content/') === 0) {
                    $parts = explode('/', substr($docFile, strlen('content/')));
                    $potentialCatFolder = $parts[0] ?? '';
                    if (!empty($potentialCatFolder)) {
                        foreach ($cleanBooks as &$targetCat) {
                            if (($targetCat['type'] ?? 'folder') === 'folder') {
                                if (($targetCat['id'] ?? '') === $potentialCatFolder
                                    || ($targetCat['folder'] ?? '') === 'content/' . $potentialCatFolder
                                    || ($targetCat['folder'] ?? '') === $potentialCatFolder) {
                                    if (!isset($targetCat['items']) || !is_array($targetCat['items'])) {
                                        $targetCat['items'] = [];
                                    }
                                    $targetCat['items'][] = $doc;
                                    $placed = true;
                                    break;
                                }
                            }
                        }
                    }
                }

                // If not matched, place in the first available folder category
                if (!$placed) {
                    foreach ($cleanBooks as &$targetCat) {
                        if (($targetCat['type'] ?? 'folder') === 'folder') {
                            if (!isset($targetCat['items']) || !is_array($targetCat['items'])) {
                                $targetCat['items'] = [];
                            }
                            $targetCat['items'][] = $doc;
                            $placed = true;
                            break;
                        }
                    }
                }

                // If no folder category exists at all in the wiki, create one
                if (!$placed) {
                    $cleanBooks[] = [
                        'id' => 'general',
                        'title' => 'General',
                        'type' => 'folder',
                        'items' => [$doc]
                    ];
                }
            }
        }

        if ($modified) {
            $books = $cleanBooks;
        }

        return $modified;
    }

    public static function save(array $config) {
        $configFile = self::getConfigFile();
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return file_put_contents($configFile, $json, LOCK_EX) !== false;
    }

    public static function loadUsers() {
        $usersFile = self::getUsersFile();
        if (!file_exists($usersFile)) {
            $initialUsers = [
                'users' => [
                    [
                        'username' => 'admin',
                        'role' => 'admin',
                        'passwordHash' => '$2y$10$H8vIUts/BIGCXGCmw9xFHuCBnPGgNHZ44F59OcQYYxDVKBmD19DIm',
                        'createdAt' => date('Y-m-d H:i:s')
                    ]
                ]
            ];
            file_put_contents($usersFile, json_encode($initialUsers, JSON_PRETTY_PRINT), LOCK_EX);
            return $initialUsers;
        }
        $data = json_decode(file_get_contents($usersFile), true);
        return is_array($data) ? $data : ['users' => []];
    }

    public static function saveUsers(array $userData) {
        $usersFile = self::getUsersFile();
        $json = json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return file_put_contents($usersFile, $json, LOCK_EX) !== false;
    }

    public static function makeSlug($text) {
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    public static function safePath($baseDir, $subPath) {
        $realBase = realpath($baseDir);
        if ($realBase === false) return false;
        
        $combined = $realBase . '/' . ltrim($subPath, '/\\');
        $realTarget = realpath($combined);
        
        // If file doesn't exist yet, check directory part
        if ($realTarget === false) {
            $dirPart = dirname($combined);
            $realDir = realpath($dirPart);
            if ($realDir === false || strpos($realDir, $realBase) !== 0) {
                return false;
            }
            return $combined;
        }

        if (strpos($realTarget, $realBase) !== 0) {
            return false;
        }
        return $realTarget;
    }

    public static function copyDir($src, $dst) {
        if (!is_dir($src)) return;
        @mkdir($dst, 0755, true);
        $dir = opendir($src);
        while (false !== ($file = readdir($dir))) {
            if ($file !== '.' && $file !== '..') {
                if (is_dir($src . '/' . $file)) {
                    self::copyDir($src . '/' . $file, $dst . '/' . $file);
                } else {
                    @copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    public static function getReservedNames(): array {
        return ['api', 'assets', 'content', 'uploads', 'lib', 'tests', 'demo-data', 'wikis', '_core', 'admin'];
    }

    public static function isSubwiki(): bool {
        $baseDir = self::getBaseDir();
        if (file_exists($baseDir . '/.subwiki')) {
            return true;
        }
        $config = self::load();
        return !empty($config['isSubwiki']) || !empty($config['parentUrl']);
    }

    public static function isDemoMode(): bool {
        $env = getenv('QWIKI_DEMO_MODE');
        if ($env !== false && $env !== '') {
            return in_array(strtolower((string)$env), ['1', 'true', 'yes', 'on'], true);
        }
        $baseDir = self::getBaseDir();
        if (file_exists($baseDir . '/.demo')) {
            return true;
        }
        $config = self::load();
        return !empty($config['demoMode']);
    }

    public static function isChapterProtected(string $slugOrFile, ?array $nodes = null, bool $inheritedProtection = false): bool {
        if (empty($slugOrFile)) return false;
        if ($nodes === null) {
            $config = self::load();
            $nodes = $config['books'] ?? [];
        }
        $normTarget = ltrim(str_replace('\\', '/', $slugOrFile), '/');
        foreach ($nodes as $node) {
            $isNodeDirectlyProtected = !empty($node['readOnly']) || (isset($node['editable']) && $node['editable'] === false) || !empty($node['locked']);
            $isProtected = $inheritedProtection || $isNodeDirectlyProtected;
            if ($isProtected) {
                if (!empty($node['slug']) && $node['slug'] === $slugOrFile) {
                    return true;
                }
                if (!empty($node['file'])) {
                    $normFile = ltrim(str_replace('\\', '/', $node['file']), '/');
                    if ($normFile === $normTarget) {
                        return true;
                    }
                }
            }
            if (!empty($node['items']) && is_array($node['items'])) {
                if (self::isChapterProtected($slugOrFile, $node['items'], $isProtected)) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function isChapterDirectlyProtected(string $slugOrFile, ?array $nodes = null): bool {
        if (empty($slugOrFile)) return false;
        if ($nodes === null) {
            $config = self::load();
            $nodes = $config['books'] ?? [];
        }
        $normTarget = ltrim(str_replace('\\', '/', $slugOrFile), '/');
        foreach ($nodes as $node) {
            if (!empty($node['slug']) && $node['slug'] === $slugOrFile) {
                return !empty($node['readOnly']) || (isset($node['editable']) && $node['editable'] === false) || !empty($node['locked']);
            }
            if (!empty($node['file'])) {
                $normFile = ltrim(str_replace('\\', '/', $node['file']), '/');
                if ($normFile === $normTarget) {
                    return !empty($node['readOnly']) || (isset($node['editable']) && $node['editable'] === false) || !empty($node['locked']);
                }
            }
            if (!empty($node['items']) && is_array($node['items'])) {
                if (self::isChapterDirectlyProtected($slugOrFile, $node['items'])) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function isChapterAncestorProtected(string $slugOrFile, ?array $nodes = null, bool $inheritedProtection = false): bool {
        if (empty($slugOrFile)) return false;
        if ($nodes === null) {
            $config = self::load();
            $nodes = $config['books'] ?? [];
        }
        $normTarget = ltrim(str_replace('\\', '/', $slugOrFile), '/');
        foreach ($nodes as $node) {
            if (!empty($node['slug']) && $node['slug'] === $slugOrFile) {
                return $inheritedProtection;
            }
            if (!empty($node['file'])) {
                $normFile = ltrim(str_replace('\\', '/', $node['file']), '/');
                if ($normFile === $normTarget) {
                    return $inheritedProtection;
                }
            }
            $isNodeDirectlyProtected = !empty($node['readOnly']) || (isset($node['editable']) && $node['editable'] === false) || !empty($node['locked']);
            $isProtected = $inheritedProtection || $isNodeDirectlyProtected;
            if (!empty($node['items']) && is_array($node['items'])) {
                if (self::isChapterAncestorProtected($slugOrFile, $node['items'], $isProtected)) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function isCategoryProtected(string $categoryId, ?array $nodes = null, bool $inheritedProtection = false): bool {
        if (empty($categoryId)) return false;
        if ($nodes === null) {
            $config = self::load();
            $nodes = $config['books'] ?? [];
        }
        foreach ($nodes as $node) {
            $isDirectlyProtected = !empty($node['readOnly']) || (isset($node['editable']) && $node['editable'] === false) || !empty($node['locked']);
            $isProtected = $inheritedProtection || $isDirectlyProtected;

            if (($node['id'] ?? '') === $categoryId) {
                if ($isProtected) {
                    return true;
                }
                return self::nodeContainsProtectedItems($node);
            }

            if (!empty($node['items']) && is_array($node['items'])) {
                if (self::isCategoryProtected($categoryId, $node['items'], $isProtected)) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function isCategoryDirectlyProtected(string $categoryId, ?array $nodes = null): bool {
        if (empty($categoryId)) return false;
        if ($nodes === null) {
            $config = self::load();
            $nodes = $config['books'] ?? [];
        }
        foreach ($nodes as $node) {
            if (($node['id'] ?? '') === $categoryId) {
                return !empty($node['readOnly']) || (isset($node['editable']) && $node['editable'] === false) || !empty($node['locked']);
            }
            if (!empty($node['items']) && is_array($node['items'])) {
                if (self::isCategoryDirectlyProtected($categoryId, $node['items'])) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function nodeContainsProtectedItems(array $node): bool {
        if (empty($node['items']) || !is_array($node['items'])) {
            return false;
        }
        foreach ($node['items'] as $item) {
            if (!is_array($item)) continue;
            $isItemProtected = !empty($item['readOnly']) || (isset($item['editable']) && $item['editable'] === false) || !empty($item['locked']);
            if ($isItemProtected) {
                return true;
            }
            if (!empty($item['items']) && is_array($item['items'])) {
                if (self::nodeContainsProtectedItems($item)) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function getBaseUrl(): string {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir = rtrim(dirname($scriptName === '/' || $scriptName === '\\' ? '' : $scriptName), '/\\');
        $normalizedDir = '/' . ltrim($scriptDir, '/\\');
        $webPath = preg_replace('#/(api|assets|content|tests).*$#i', '', $normalizedDir);
        $webPath = trim($webPath, '/\\');
        if ($webPath === '.') {
            $webPath = '';
        }
        return $protocol . $domainName . (!empty($webPath) ? '/' . $webPath . '/' : '/');
    }

    public static function isPostboxEnabled(): bool {
        $config = self::load();
        return !isset($config['postboxEnabled']) || !empty($config['postboxEnabled']);
    }

    public static function getPostboxToken(): string {
        $config = self::load();
        if (!empty($config['postboxToken']) && is_string($config['postboxToken'])) {
            return $config['postboxToken'];
        }
        try {
            $newToken = bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            $newToken = md5(uniqid((string)mt_rand(), true));
        }
        $config['postboxToken'] = $newToken;
        self::save($config);
        return $newToken;
    }

    public static function setPostboxToken(string $token): bool {
        $config = self::load();
        $config['postboxToken'] = trim($token);
        return self::save($config);
    }

    public static function getPostboxPeers(): array {
        $config = self::load();
        return isset($config['postboxPeers']) && is_array($config['postboxPeers']) ? $config['postboxPeers'] : [];
    }

    public static function savePostboxPeers(array $peers): bool {
        $config = self::load();
        $config['postboxPeers'] = array_values($peers);
        return self::save($config);
    }
}

