<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';

use Qwiki\Core\Config;
use Qwiki\Core\Navigation;

$config = Config::load();

// 1. Authenticate via API Key (constant-time check against timing attacks)
$publishApiKey = $config['publishApiKey'] ?? '';
if (empty($publishApiKey)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Publishing via API is disabled (no API key configured)']);
    exit;
}

// Get API Key from header or POST body
$providedKey = (string)($_SERVER['HTTP_X_API_KEY'] ?? $_POST['api_key'] ?? '');
if (empty($providedKey) || !hash_equals((string)$publishApiKey, $providedKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

// 2. Extract and Validate Inputs
$bookId = trim($_POST['bookId'] ?? '');
$title = trim($_POST['title'] ?? '');
$content = $_POST['content'] ?? '';
$type = strtolower(trim($_POST['type'] ?? 'markdown'));
if (!in_array($type, ['markdown', 'html'], true)) {
    $type = 'markdown';
}

$description = trim($_POST['description'] ?? '');
$providedShareKey = trim($_POST['shareKey'] ?? '');
$publicShareable = true;
if (isset($_POST['publicShareable'])) {
    $val = $_POST['publicShareable'];
    if ($val === false || $val === 'false' || $val === '0' || $val === 0) {
        $publicShareable = false;
    }
}

if (empty($bookId) || empty($title) || empty($content)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'bookId, title, and content are required']);
    exit;
}

// Sanitize bookId to prevent path traversal (allow alphanumeric, hyphens, underscores, slashes)
if (!preg_match('/^[a-zA-Z0-9\-_]+(\/[a-zA-Z0-9\-_]+)*$/', $bookId) || strpos($bookId, '..') !== false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid bookId format']);
    exit;
}

function find_target_folder(&$node, $targetId) {
    if (($node['id'] ?? '') === $targetId) {
        $folder = $node['folder'] ?? $node['id'];
        return ltrim(preg_replace('#^content/#i', '', $folder), '/');
    }
    if (!empty($node['items']) && is_array($node['items'])) {
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

function insert_chapter_into_node(&$node, $targetFolderId, $chapterData) {
    if (($node['id'] ?? '') === $targetFolderId || ($node['folder'] ?? '') === $targetFolderId) {
        if (!isset($node['items']) || !is_array($node['items'])) {
            $node['items'] = [];
        }
        $node['items'][] = $chapterData;
        return true;
    }
    if (!empty($node['items']) && is_array($node['items'])) {
        foreach ($node['items'] as &$sub) {
            if (isset($sub['type']) && $sub['type'] === 'folder') {
                if (insert_chapter_into_node($sub, $targetFolderId, $chapterData)) {
                    return true;
                }
            }
        }
    }
    return false;
}

// 3. Resolve Target Category / Subfolder
$targetFolder = null;
$targetNodeId = $bookId;

foreach ($config['books'] as $b) {
    $resolved = find_target_folder($b, $bookId);
    if ($resolved !== null) {
        $targetFolder = $resolved;
        $targetNodeId = $bookId;
        break;
    }
}

if ($targetFolder === null && strpos($bookId, '/') !== false) {
    $parts = explode('/', $bookId);
    $leafId = end($parts);
    foreach ($config['books'] as $b) {
        $resolved = find_target_folder($b, $leafId);
        if ($resolved !== null) {
            $targetFolder = $resolved;
            $targetNodeId = $leafId;
            break;
        }
    }
}

if ($targetFolder === null) {
    $targetFolder = $bookId;
}

// Verify directory safety
$baseDir = Config::getBaseDir();
$contentRoot = $baseDir . '/content';
$targetAbsDir = Config::safePath($contentRoot, $targetFolder);
$realContentRoot = realpath($contentRoot) ?: $contentRoot;

if (!$targetAbsDir || ($realContentRoot && strpos($targetAbsDir, $realContentRoot) !== 0)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or unsafe category directory']);
    exit;
}

if (!is_dir($targetAbsDir)) {
    if (!@mkdir($targetAbsDir, 0755, true)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Category (bookId) does not exist and could not be created']);
        exit;
    }
}

// 4. Sanitize Markdown Content against basic XSS
function sanitize_markdown($markdown) {
    $dangerous_tags = ['script', 'iframe', 'object', 'embed', 'applet', 'meta', 'link', 'style', 'base', 'form'];
    foreach ($dangerous_tags as $tag) {
        $markdown = preg_replace('/<\/?\s*' . $tag . '\b[^>]*>/is', '', $markdown);
    }
    $markdown = preg_replace('/on[a-z]+\s*=\s*(["\']).*?\1/is', '', $markdown);
    $markdown = preg_replace('/on[a-z]+\s*=\s*[^>\s]+/is', '', $markdown);
    $markdown = preg_replace('/href\s*=\s*(["\']?)javascript:.*?\1/is', 'href="#"', $markdown);
    return $markdown;
}

// 5. Generate Unique Filename & Path
$baseSlug = Config::makeSlug($title);
if (empty($baseSlug)) {
    $baseSlug = 'doc-' . time();
}

$ext = ($type === 'html') ? '.html' : '.md';
$slug = $baseSlug;
$counter = 1;
$targetAbsFile = $targetAbsDir . '/' . $slug . $ext;

while (file_exists($targetAbsFile)) {
    $slug = $baseSlug . '-' . $counter;
    $targetAbsFile = $targetAbsDir . '/' . $slug . $ext;
    $counter++;
}

$targetRelFile = 'content/' . trim($targetFolder, '/') . '/' . $slug . $ext;

// 6. Content Sanitization & Preparation
$image = '';
if ($type === 'html') {
    // Preserve styles, @media print, meta, and SVG while stripping executable scripts and event handlers
    $sanitizedContent = Config::sanitizeHtml($content);

    // Auto-extract description and social image from HTML if not provided
    if (empty($description)) {
        if (preg_match('/<meta\s+[^>]*(?:name=["\']description["\']|property=["\']og:description["\'])[^>]*content=["\']([^"\']+)["\']/i', $sanitizedContent, $mDesc) ||
            preg_match('/<meta\s+[^>]*content=["\']([^"\']+)["\'][^>]*(?:name=["\']description["\']|property=["\']og:description["\'])/i', $sanitizedContent, $mDesc)) {
            $description = trim(html_entity_decode($mDesc[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
    }
    if (preg_match('/<meta\s+[^>]*(?:property=["\']og:image["\']|name=["\']twitter:image["\'])[^>]*content=["\']([^"\']+)["\']/i', $sanitizedContent, $mImg) ||
        preg_match('/<meta\s+[^>]*content=["\']([^"\']+)["\'][^>]*(?:property=["\']og:image["\']|name=["\']twitter:image["\'])/i', $sanitizedContent, $mImg)) {
        $image = trim($mImg[1]);
    }

    $sanitizedContent = Config::ensureHtmlBaseHref($sanitizedContent, $targetRelFile);
} else {
    $sanitizedContent = sanitize_markdown($content);
}

// Write the document file
if (file_put_contents($targetAbsFile, $sanitizedContent) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to write document file to disk']);
    exit;
}

// 7. Auto-Generate Share Key with Collision Check
$shareKey = !empty($providedShareKey) ? $providedShareKey : Navigation::generateShareKey();
while (Navigation::findChapterByShareKey($config['books'] ?? [], $shareKey) !== null) {
    $shareKey = Navigation::generateShareKey();
}

// 8. Prepare Chapter Data for qwiki.json
$chapterData = [
    'title' => $title,
    'slug' => $slug,
    'type' => $type,
    'file' => $targetRelFile,
    'shareKey' => $shareKey
];

if (!$publicShareable) {
    $chapterData['publicShareable'] = false;
}
if (!empty($description)) {
    $chapterData['description'] = $description;
}
if (!empty($image)) {
    $chapterData['image'] = $image;
}

// Validate optional translations metadata
if (!empty($_POST['translations'])) {
    $rawTranslations = $_POST['translations'];
    if (is_string($rawTranslations)) {
        $decoded = json_decode($rawTranslations, true);
        if (is_array($decoded)) {
            $rawTranslations = $decoded;
        }
    }
    if (is_array($rawTranslations)) {
        $cleanTranslations = [];
        foreach ($rawTranslations as $lang => $transSlug) {
            if (is_string($lang) && is_string($transSlug) && preg_match('/^[a-zA-Z0-9\-_]{2,10}$/', $lang) && preg_match('/^[a-zA-Z0-9\-_]+$/', $transSlug)) {
                $cleanTranslations[$lang] = $transSlug;
            }
        }
        if (!empty($cleanTranslations)) {
            $chapterData['translations'] = $cleanTranslations;
        }
    }
}

// 9. Insert Chapter Node and Atomic Save
$added = false;
foreach ($config['books'] as &$book) {
    if (insert_chapter_into_node($book, $targetNodeId, $chapterData)) {
        $added = true;
        break;
    }
}

if ($added && Config::save($config)) {
    $baseUrl = Config::getBaseUrl();
    $shareUrl = $baseUrl . '?share=' . urlencode($shareKey);
    $url = $baseUrl . urlencode($bookId) . '/' . urlencode($slug);

    echo json_encode([
        'success' => true,
        'bookId' => $bookId,
        'slug' => $slug,
        'file' => $targetRelFile,
        'type' => $type,
        'shareKey' => $shareKey,
        'shareUrl' => $shareUrl,
        'url' => $url
    ]);
} else {
    // Rollback file creation if config fails
    @unlink($targetAbsFile);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update qwiki.json']);
}
