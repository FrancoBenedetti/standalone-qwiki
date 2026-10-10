<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';
require_once __DIR__ . '/../lib/Core/LlmAccess.php';

use Qwiki\Core\Config;
use Qwiki\Core\Navigation;
use Qwiki\Core\LlmAccess;

$config = Config::load();

// 1. Authenticate via API Key (publishApiKey or active llmKey)
$publishApiKey = $config['publishApiKey'] ?? '';
$llmKeys = $config['llmKeys'] ?? [];
if (empty($publishApiKey) && empty($llmKeys)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Publishing via API is disabled (no API key configured)']);
    exit;
}

// Get API Key from header, POST body, or query param
$providedKey = (string)($_SERVER['HTTP_X_API_KEY'] ?? $_POST['api_key'] ?? $_POST['key'] ?? $_GET['key'] ?? '');
if (empty($providedKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

$isAuthorized = false;
if (!empty($publishApiKey) && hash_equals((string)$publishApiKey, $providedKey)) {
    $isAuthorized = true;
} elseif (!empty($llmKeys)) {
    $llmVal = LlmAccess::validateKey($providedKey);
    if (!empty($llmVal['valid'])) {
        $isAuthorized = true;
        if (!empty($llmVal['key']['id'])) {
            LlmAccess::updateLastUsed($llmVal['key']['id']);
        }
    }
}

if (!$isAuthorized) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

// Helper functions for category and chapter resolution
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

function upsert_chapter_into_node(&$node, $targetFolderId, $chapterData, &$isUpdated = false) {
    if (($node['id'] ?? '') === $targetFolderId || ($node['folder'] ?? '') === $targetFolderId) {
        if (!isset($node['items']) || !is_array($node['items'])) {
            $node['items'] = [];
        }
        foreach ($node['items'] as &$it) {
            if ((!isset($it['type']) || $it['type'] !== 'folder') && ($it['slug'] ?? '') === $chapterData['slug']) {
                if (empty($chapterData['shareKey']) && !empty($it['shareKey'])) {
                    $chapterData['shareKey'] = $it['shareKey'];
                }
                $it = array_merge($it, $chapterData);
                $isUpdated = true;
                return true;
            }
        }
        $node['items'][] = $chapterData;
        return true;
    }
    if (!empty($node['items']) && is_array($node['items'])) {
        foreach ($node['items'] as &$sub) {
            if (isset($sub['type']) && $sub['type'] === 'folder') {
                if (upsert_chapter_into_node($sub, $targetFolderId, $chapterData, $isUpdated)) {
                    return true;
                }
            }
        }
    }
    return false;
}

function insert_chapter_into_node(&$node, $targetFolderId, $chapterData) {
    $dummy = false;
    return upsert_chapter_into_node($node, $targetFolderId, $chapterData, $dummy);
}

// 2. Dispatch Action
$action = trim($_POST['action'] ?? $_GET['action'] ?? 'publish_doc');

if ($action === 'create_category' || $action === 'add_book') {
    $catId = Config::makeSlug($_POST['id'] ?? $_POST['bookId'] ?? $_POST['title'] ?? '');
    $catTitle = trim($_POST['title'] ?? '');
    $catDesc = trim($_POST['description'] ?? '');
    $parentId = trim($_POST['parentId'] ?? '');

    if (empty($catTitle) || empty($catId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Category title and ID are required']);
        exit;
    }

    if (Navigation::isSlugTaken($catId, $config['books'] ?? [])) {
        $updated = false;
        $updateFn = function(&$nodes) use (&$updateFn, $catId, $catTitle, $catDesc, &$updated) {
            foreach ($nodes as &$node) {
                if (($node['id'] ?? '') === $catId) {
                    if (!empty($catTitle)) $node['title'] = $catTitle;
                    if ($catDesc !== '') $node['description'] = $catDesc;
                    $updated = true;
                    return;
                }
                if (!empty($node['items']) && is_array($node['items'])) {
                    $updateFn($node['items']);
                    if ($updated) return;
                }
            }
        };
        $updateFn($config['books']);
        if ($updated) {
            Config::save($config);
        }
        echo json_encode([
            'success' => true,
            'bookId' => $catId,
            'title' => $catTitle,
            'description' => $catDesc,
            'message' => ($action === 'update_category' || $action === 'edit_category') ? 'Category updated' : 'Category already exists'
        ]);
        exit;
    }

    $baseDir = Config::getBaseDir();
    if (!empty($parentId)) {
        $parentFolder = null;
        foreach ($config['books'] as $b) {
            $resolved = find_target_folder($b, $parentId);
            if ($resolved !== null) {
                $parentFolder = $resolved;
                break;
            }
        }
        if ($parentFolder === null) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Parent category not found']);
            exit;
        }

        $targetRelFolder = 'content/' . ltrim(preg_replace('#^content/#i', '', $parentFolder), '/') . '/' . $catId;
        $targetAbsFolder = $baseDir . '/' . $targetRelFolder;
        if (!is_dir($targetAbsFolder)) {
            @mkdir($targetAbsFolder, 0755, true);
        }

        $newNode = [
            'id' => $catId,
            'title' => $catTitle,
            'type' => 'folder',
            'folder' => $targetRelFolder,
            'items' => []
        ];
        if ($catDesc !== '') {
            $newNode['description'] = $catDesc;
        }

        $inserted = false;
        foreach ($config['books'] as &$book) {
            if (insert_chapter_into_node($book, $parentId, $newNode)) {
                $inserted = true;
                break;
            }
        }
        if (!$inserted) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to nest category in parent']);
            exit;
        }
    } else {
        $targetRelFolder = 'content/' . $catId;
        $targetAbsFolder = $baseDir . '/' . $targetRelFolder;
        if (!is_dir($targetAbsFolder)) {
            @mkdir($targetAbsFolder, 0755, true);
        }

        $newNode = [
            'id' => $catId,
            'title' => $catTitle,
            'type' => 'folder',
            'folder' => $targetRelFolder,
            'items' => []
        ];
        if ($catDesc !== '') {
            $newNode['description'] = $catDesc;
        }

        if (!isset($config['books']) || !is_array($config['books'])) {
            $config['books'] = [];
        }
        $config['books'][] = $newNode;
    }

    if (Config::save($config)) {
        echo json_encode([
            'success' => true,
            'bookId' => $catId,
            'title' => $catTitle,
            'description' => $catDesc,
            'folder' => $targetRelFolder
        ]);
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update qwiki.json']);
        exit;
    }
}

if ($action === 'list_categories' || $action === 'get_books') {
    $categories = [];
    foreach ($config['books'] ?? [] as $book) {
        if (($book['type'] ?? '') === 'folder') {
            $categories[] = [
                'id' => $book['id'] ?? '',
                'title' => $book['title'] ?? '',
                'description' => $book['description'] ?? '',
                'folder' => $book['folder'] ?? ('content/' . ($book['id'] ?? ''))
            ];
        }
    }
    echo json_encode(['success' => true, 'categories' => $categories]);
    exit;
}

// 3. Extract and Validate Document Inputs
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

$providedSlug = trim($_POST['slug'] ?? '');
$allowOverwrite = false;
if (isset($_POST['update']) || isset($_POST['overwrite']) || in_array($action, ['update_doc', 'edit_doc'], true)) {
    $rawUp = $_POST['update'] ?? $_POST['overwrite'] ?? true;
    if ($rawUp === true || $rawUp === 'true' || $rawUp === '1' || $rawUp === 1 || in_array($action, ['update_doc', 'edit_doc'], true)) {
        $allowOverwrite = true;
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

// 5. Generate or Resolve Slug & Unique Filename & Path
if (!empty($providedSlug)) {
    $baseSlug = Config::makeSlug($providedSlug);
} else {
    $baseSlug = Config::makeSlug($title);
}
if (empty($baseSlug)) {
    $baseSlug = 'doc-' . time();
}

$ext = ($type === 'html') ? '.html' : '.md';

// Helper to find an existing chapter with this slug in target category
$findExistingChapterInFolder = function($node, $targetFolderId, $targetSlug) use (&$findExistingChapterInFolder) {
    if (($node['id'] ?? '') === $targetFolderId || ($node['folder'] ?? '') === $targetFolderId) {
        if (!empty($node['items']) && is_array($node['items'])) {
            foreach ($node['items'] as $item) {
                if (($item['slug'] ?? '') === $targetSlug && (!isset($item['type']) || $item['type'] !== 'folder')) {
                    return $item;
                }
            }
        }
        return null;
    }
    if (!empty($node['items']) && is_array($node['items'])) {
        foreach ($node['items'] as $child) {
            if (isset($child['type']) && $child['type'] === 'folder') {
                $found = $findExistingChapterInFolder($child, $targetFolderId, $targetSlug);
                if ($found !== null) return $found;
            }
        }
    }
    return null;
};

$existingChapter = null;
foreach ($config['books'] as $b) {
    $existingChapter = $findExistingChapterInFolder($b, $targetNodeId, $baseSlug);
    if ($existingChapter !== null) break;
}

if ($allowOverwrite || $existingChapter !== null) {
    // Preserve slug exactly, do not increment!
    $slug = $baseSlug;
    $targetAbsFile = $targetAbsDir . '/' . $slug . $ext;
    if ($existingChapter !== null && empty($providedShareKey) && !empty($existingChapter['shareKey'])) {
        $providedShareKey = $existingChapter['shareKey'];
    }
} else {
    // Only increment if a document with this slug is actively registered in qwiki.json
    $slug = $baseSlug;
    $counter = 1;
    $targetAbsFile = $targetAbsDir . '/' . $slug . $ext;
    while (Navigation::isSlugTaken($slug, $config['books'] ?? [])) {
        $slug = $baseSlug . '-' . $counter;
        $targetAbsFile = $targetAbsDir . '/' . $slug . $ext;
        $counter++;
    }
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
$collidingChapter = Navigation::findChapterByShareKey($config['books'] ?? [], $shareKey);
if ($collidingChapter !== null && ($collidingChapter['slug'] ?? '') !== $slug) {
    do {
        $shareKey = Navigation::generateShareKey();
    } while (Navigation::findChapterByShareKey($config['books'] ?? [], $shareKey) !== null);
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

// 9. Insert or Update Chapter Node and Atomic Save
$isUpdated = false;
$saved = false;
foreach ($config['books'] as &$book) {
    if (upsert_chapter_into_node($book, $targetNodeId, $chapterData, $isUpdated)) {
        $saved = true;
        break;
    }
}

if ($saved && Config::save($config)) {
    $baseUrl = Config::getBaseUrl();
    $shareUrl = $baseUrl . '?share=' . urlencode($shareKey);
    $url = $baseUrl . urlencode($bookId) . '/' . urlencode($slug);

    echo json_encode([
        'success' => true,
        'updated' => $isUpdated,
        'bookId' => $bookId,
        'slug' => $slug,
        'file' => $targetRelFile,
        'type' => $type,
        'shareKey' => $shareKey,
        'shareUrl' => $shareUrl,
        'url' => $url
    ]);
    exit;
} else {
    // Rollback file creation if new file and config fails
    if (!$isUpdated) {
        @unlink($targetAbsFile);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update qwiki.json']);
    exit;
}
