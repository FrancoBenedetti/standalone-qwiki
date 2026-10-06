<?php
/**
 * HTML Page Extension Action Handler
 */
use Qwiki\Core\Auth;
use Qwiki\Core\Config;
use Qwiki\Core\LockManager;
use Qwiki\Core\Navigation;

if (!class_exists('Qwiki\Core\Navigation')) {
    $navCandidates = [
        Config::getBaseDir() . '/lib/Core/Navigation.php',
        dirname(__DIR__, 3) . '/lib/Core/Navigation.php',
        __DIR__ . '/../../../lib/Core/Navigation.php'
    ];
    foreach ($navCandidates as $candidate) {
        if (file_exists($candidate)) {
            require_once $candidate;
            break;
        }
    }
}

if (!Auth::isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    return;
}

if (!function_exists('ensure_html_base_href')) {
    function ensure_html_base_href($content, $filePath) {
        return Config::ensureHtmlBaseHref($content, $filePath);
    }
}

if (!function_exists('find_target_folder')) {
    function find_target_folder(&$node, $targetId) {
        if (($node['id'] ?? '') === $targetId) {
            $folder = $node['folder'] ?? $node['id'];
            return ltrim(preg_replace('#^content/#i', '', $folder), '/');
        }
        if (!empty($node['items'])) {
            foreach ($node['items'] as &$child) {
                if (isset($child['type']) && $child['type'] === 'folder') {
                    $found = find_target_folder($child, $targetId);
                    if ($found !== null) {
                        $parentFolder = $node['folder'] ?? $node['id'];
                        $parentFolder = ltrim(preg_replace('#^content/#i', '', $parentFolder), '/');
                        if (strpos($found, $parentFolder . '/') === 0) {
                            return $found;
                        }
                        return $parentFolder . '/' . $found;
                    }
                }
            }
        }
        return null;
    }
}

if (!function_exists('insert_html_chapter')) {
    function insert_html_chapter(&$node, $targetId, $chapterData) {
        if (($node['id'] ?? '') === $targetId) {
            if (!isset($node['items'])) $node['items'] = [];
            $node['items'][] = $chapterData;
            return true;
        }
        if (!empty($node['items'])) {
            foreach ($node['items'] as &$child) {
                if (isset($child['type']) && $child['type'] === 'folder') {
                    if (insert_html_chapter($child, $targetId, $chapterData)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}

$action = $_POST['action'] ?? 'create_html';
$baseDir = Config::getBaseDir();

// 1. Save / Update Existing HTML Document
if ($action === 'save_html' || $action === 'ext_html_save') {
    $file = trim($_POST['file'] ?? '');
    $content = $_POST['content'] ?? '';
    if (isset($_POST['content_base64'])) {
        $content = base64_decode($_POST['content_base64']);
    }

    if (empty($file)) {
        echo json_encode(['success' => false, 'error' => 'File path is required']);
        return;
    }

    if (Config::isChapterProtected($file)) {
        echo json_encode(['success' => false, 'error' => 'This document is protected and cannot be edited.']);
        return;
    }

    $absolutePath = Config::safePath($baseDir, $file);
    if (!$absolutePath || !file_exists($absolutePath)) {
        echo json_encode(['success' => false, 'error' => 'Invalid or non-existent file path']);
        return;
    }

    // Verify it is an HTML file
    if (!preg_match('/\.(html|htm)$/i', $absolutePath)) {
        echo json_encode(['success' => false, 'error' => 'Only HTML files can be saved with this action']);
        return;
    }

    if (file_exists($absolutePath) && !is_writable($absolutePath)) {
        echo json_encode(['success' => false, 'error' => 'Permission denied: HTML file is not writable by the web server process']);
        return;
    }

    $tabId = $_POST['tab_id'] ?? '';
    $user = Auth::getCurrentUser();
    $username = $user['username'] ?? 'admin';
    if (!empty($tabId)) {
        $lockCheck = LockManager::verifyOrRejectSave($file, $tabId, $username);
        if (!$lockCheck['allowed']) {
            echo json_encode([
                'success' => false,
                'code' => 'LOCKED_BY_OTHER',
                'lockedBy' => $lockCheck['lockedBy'] ?? 'another session',
                'isSameUser' => !empty($lockCheck['isSameUser']),
                'error' => 'Document save rejected: Document is currently locked by ' . ($lockCheck['lockedBy'] ?? 'another session')
            ]);
            return;
        }
    }

    $content = ensure_html_base_href($content, $file);

    if (file_put_contents($absolutePath, $content) === false) {
        echo json_encode(['success' => false, 'error' => 'Failed to save HTML file to disk']);
        return;
    }

    if (!empty($tabId)) {
        LockManager::releaseLock($file, $tabId, $username);
    }

    // Synchronize meta description to chapter metadata if present in saved HTML
    $foundDesc = '';
    if (preg_match('/<meta\s+[^>]*(?:name=["\']description["\']|property=["\']og:description["\'])[^>]*content=["\']([^"\']+)["\']/i', $content, $mDesc) ||
        preg_match('/<meta\s+[^>]*content=["\']([^"\']+)["\'][^>]*(?:name=["\']description["\']|property=["\']og:description["\'])/i', $content, $mDesc)) {
        $foundDesc = trim(html_entity_decode($mDesc[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if (!empty($foundDesc)) {
        $cfg = Config::load();
        $descUpdated = false;
        $syncDesc = function(&$nodes) use (&$syncDesc, $file, $foundDesc, &$descUpdated) {
            foreach ($nodes as &$node) {
                if (($node['file'] ?? '') === $file) {
                    if (empty($node['description']) || $node['description'] !== $foundDesc) {
                        $node['description'] = $foundDesc;
                        $descUpdated = true;
                    }
                    return;
                }
                if (!empty($node['items']) && is_array($node['items'])) {
                    $syncDesc($node['items']);
                }
            }
        };
        $syncDesc($cfg['books']);
        if ($descUpdated) {
            Config::save($cfg);
        }
    }

    echo json_encode(['success' => true, 'file' => $file]);
    return;
}

// 2. Fetch HTML file content for editor
if ($action === 'get_html' || $action === 'ext_html_get') {
    $file = trim($_POST['file'] ?? $_GET['file'] ?? '');
    $absolutePath = Config::safePath($baseDir, $file);
    if (!$absolutePath || !file_exists($absolutePath)) {
        echo json_encode(['success' => false, 'error' => 'File not found']);
        return;
    }

    echo json_encode([
        'success' => true,
        'file' => $file,
        'content' => file_get_contents($absolutePath)
    ]);
    return;
}

// 3. Create New HTML Document
$title = trim($_POST['title'] ?? '');
$bookId = $_POST['bookId'] ?? '';
$content = $_POST['content'] ?? '';
$description = trim($_POST['description'] ?? '');
if (isset($_POST['content_base64'])) {
    $content = base64_decode($_POST['content_base64']);
}

if (empty($title)) {
    echo json_encode(['success' => false, 'error' => 'Document title is required']);
    return;
}

$slug = Config::makeSlug($title);
if (empty($slug)) {
    $slug = 'doc-' . time();
}

// Auto-extract description and social image from HTML if not explicitly supplied
$image = '';
if (!empty($content)) {
    if (empty($description)) {
        if (preg_match('/<meta\s+[^>]*(?:name=["\']description["\']|property=["\']og:description["\'])[^>]*content=["\']([^"\']+)["\']/i', $content, $mDesc) ||
            preg_match('/<meta\s+[^>]*content=["\']([^"\']+)["\'][^>]*(?:name=["\']description["\']|property=["\']og:description["\'])/i', $content, $mDesc)) {
            $description = trim(html_entity_decode($mDesc[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
    }
    if (preg_match('/<meta\s+[^>]*(?:property=["\']og:image["\']|name=["\']twitter:image["\'])[^>]*content=["\']([^"\']+)["\']/i', $content, $mImg) ||
        preg_match('/<meta\s+[^>]*content=["\']([^"\']+)["\'][^>]*(?:property=["\']og:image["\']|name=["\']twitter:image["\'])/i', $content, $mImg)) {
        $image = trim($mImg[1]);
    }
}

$config = Config::load();

$targetFolder = $bookId;
foreach ($config['books'] as $b) {
    $resolved = find_target_folder($b, $bookId);
    if ($resolved !== null) {
        $targetFolder = $resolved;
        break;
    }
}

$contentDir = $baseDir . '/content/' . $targetFolder;
if (!is_dir($contentDir)) {
    if (!@mkdir($contentDir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Permission denied: Cannot create directory ' . $contentDir . '. Check web server permissions.']);
        return;
    }
}

if (!is_writable($contentDir)) {
    echo json_encode(['success' => false, 'error' => 'Permission denied: Target directory ' . $contentDir . ' is not writable by the web server process']);
    return;
}

$filePath = 'content/' . $targetFolder . '/' . $slug . '.html';
$absolutePath = $baseDir . '/' . $filePath;

if (empty($content)) {
    $metaTags = '';
    if (!empty($description)) {
        $escapedDesc = htmlspecialchars($description);
        $escapedTitle = htmlspecialchars($title);
        $metaTags = "    <meta name=\"description\" content=\"{$escapedDesc}\">\n    <meta property=\"og:title\" content=\"{$escapedTitle}\">\n    <meta property=\"og:description\" content=\"{$escapedDesc}\">\n";
    }
    $content = "<!DOCTYPE html>\n<html>\n<head>\n    <meta charset=\"UTF-8\">\n    <title>" . htmlspecialchars($title) . "</title>\n{$metaTags}    <style>body { font-family: system-ui, sans-serif; padding: 2rem; }</style>\n</head>\n<body>\n    <h1>" . htmlspecialchars($title) . "</h1>\n    <p>" . (!empty($description) ? htmlspecialchars($description) : "Welcome to this HTML document.") . "</p>\n</body>\n</html>";
}

$content = ensure_html_base_href($content, $filePath);

if (file_put_contents($absolutePath, $content) === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to write HTML file']);
    return;
}

$shareKey = !empty($_POST['shareKey']) ? trim($_POST['shareKey']) : Navigation::generateShareKey();
while (Navigation::findChapterByShareKey($config['books'] ?? [], $shareKey) !== null) {
    $shareKey = Navigation::generateShareKey();
}

$chapterData = [
    'title' => $title,
    'slug' => $slug,
    'type' => 'html',
    'file' => $filePath,
    'shareKey' => $shareKey
];
if (!empty($description)) {
    $chapterData['description'] = $description;
}
if (!empty($image)) {
    $chapterData['image'] = $image;
}
if (!empty($_POST['translations'])) {
    $translations = $_POST['translations'];
    if (is_string($translations)) {
        $decoded = json_decode($translations, true);
        if (is_array($decoded)) $translations = $decoded;
    }
    if (is_array($translations)) {
        $cleanTranslations = [];
        foreach ($translations as $lang => $transSlug) {
            if (is_string($lang) && is_string($transSlug) && preg_match('/^[a-zA-Z0-9\-_]{2,10}$/', $lang) && preg_match('/^[a-zA-Z0-9\-_]+$/', $transSlug)) {
                $cleanTranslations[$lang] = $transSlug;
            }
        }
        if (!empty($cleanTranslations)) {
            $chapterData['translations'] = $cleanTranslations;
        }
    }
}

$inserted = false;
foreach ($config['books'] as &$book) {
    if (insert_html_chapter($book, $bookId, $chapterData)) {
        $inserted = true;
        break;
    }
}

if ($inserted && Config::save($config)) {
    $baseUrl = Config::getBaseUrl();
    echo json_encode([
        'success' => true,
        'slug' => $slug,
        'bookId' => $bookId,
        'file' => $filePath,
        'shareKey' => $shareKey,
        'shareUrl' => $baseUrl . '?share=' . urlencode($shareKey)
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save configuration']);
}
return;
