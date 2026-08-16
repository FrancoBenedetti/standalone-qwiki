<?php
// api/feed.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$configFile = __DIR__ . '/../qwiki.json';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration file qwiki.json not found.']);
    exit;
}

$config = json_decode(file_get_contents($configFile), true);

$feedToken = trim($_GET['token'] ?? '');
$validToken = trim($config['feedAccessToken'] ?? '');

$hasValidToken = !empty($validToken) && !empty($feedToken) && hash_equals($validToken, $feedToken);

// 1. If an explicit token query parameter was supplied in the URL, but it does not correspond to the valid token, fail immediately
if (!empty($feedToken) && !$hasValidToken) {
    http_response_code(403);
    echo json_encode([
        'error' => 'Invalid feed access token provided.',
        'items' => []
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$isLoggedIn = isset($_SESSION['qwiki_user']);
$isViewer = $isLoggedIn || $hasValidToken;
$isAdmin = ($isLoggedIn && ($_SESSION['qwiki_user']['role'] ?? '') === 'admin') || $hasValidToken;

// 2. If private portal mode is enabled, require authentication or a valid token
if (!empty($config['requireLoginToView']) && !$isViewer) {
    http_response_code(403);
    echo json_encode([
        'error' => 'Feed access denied. Private portal mode is enabled and a valid access token or login is required.',
        'items' => []
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// 3. If a feedAccessToken is configured on the site, require a matching token
if (!empty($validToken) && !$isViewer) {
    http_response_code(403);
    echo json_encode([
        'error' => 'Feed access token is required.',
        'items' => []
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$categoryId = $_GET['category'] ?? '';

// Helper to calculate effective visibility inheriting from parent
function resolveEffectiveVisibility($nodeVisibility, $parentVisibility = 'public') {
    if ($parentVisibility === 'admin_only' || $nodeVisibility === 'admin_only') {
        return 'admin_only';
    }
    if ($parentVisibility === 'logged_in' || $nodeVisibility === 'logged_in') {
        return 'logged_in';
    }
    return $nodeVisibility ?? 'public';
}

// Find the category/item and its context path, carrying down inherited visibility
function findCategoryPath($nodes, $targetId, $bookId = null, $folderId = null, $parentVisibility = 'public') {
    foreach ($nodes as $node) {
        $nodeId = $node['id'] ?? $node['slug'] ?? null;
        $currentBook = $bookId;
        $currentFolder = $folderId;

        $effectiveVis = resolveEffectiveVisibility($node['visibility'] ?? null, $parentVisibility);

        if ($bookId === null) {
            $currentBook = $nodeId;
        } else if (isset($node['type']) && strtolower($node['type']) === 'folder') {
            $currentFolder = $nodeId ?? $currentFolder;
        }

        if ($nodeId !== null && $nodeId === $targetId) {
            $nodeCopy = $node;
            $nodeCopy['visibility'] = $effectiveVis;
            return ['node' => $nodeCopy, 'bookId' => $currentBook, 'folderId' => $currentFolder];
        }

        if (!empty($node['items'])) {
            $found = findCategoryPath($node['items'], $targetId, $currentBook, $currentFolder, $effectiveVis);
            if ($found) return $found;
        }
    }
    return null;
}

// Detect item type based on file extension, URL pattern, or explicit configuration type
function detectItemType($item) {
    $file = $item['file'] ?? '';
    $url  = $item['url'] ?? '';
    $type = isset($item['type']) ? strtolower(trim($item['type'])) : '';

    // Check file path or URL extensions first (ignoring query strings/anchors)
    if (!empty($file) && preg_match('/\.pdf($|\?|#)/i', $file)) {
        return 'pdf';
    }
    if (!empty($url) && preg_match('/\.pdf($|\?|#)/i', $url)) {
        return 'pdf';
    }
    if (!empty($url) && preg_match('/docs\.google\.com|drive\.google\.com/i', $url)) {
        return 'gdoc';
    }
    if (!empty($file) && preg_match('/\.(md|markdown)($|\?|#)/i', $file)) {
        return 'markdown';
    }

    // Fall back to explicit type configuration
    if (!empty($type)) {
        if (in_array($type, ['pdf', 'pdf_document', 'pdf-document'])) {
            return 'pdf';
        }
        if (in_array($type, ['gdoc', 'googledoc', 'google-doc'])) {
            return 'gdoc';
        }
        if (in_array($type, ['md', 'markdown', 'text'])) {
            return 'markdown';
        }
    }

    // Default fallback
    return 'markdown';
}

// Helper to check if a node is accessible given user session/token
function isNodeAccessible($node, $isViewer, $isAdmin) {
    $visibility = $node['visibility'] ?? 'public';
    if ($visibility === 'admin_only' && !$isAdmin) {
        return false;
    }
    if ($visibility === 'logged_in' && !$isViewer) {
        return false;
    }
    return true;
}

// Process a single leaf feed item
function processSingleFeedItem($item, &$items, $bookId, $folderId, $isViewer = false, $isAdmin = false, $parentVisibility = 'public') {
    $effectiveVis = resolveEffectiveVisibility($item['visibility'] ?? null, $parentVisibility);
    $itemCopy = $item;
    $itemCopy['visibility'] = $effectiveVis;

    if (!isNodeAccessible($itemCopy, $isViewer, $isAdmin)) {
        return;
    }

    $resolvedType = detectItemType($itemCopy);
    $itemCopy['resolvedType'] = $resolvedType;
    $itemCopy['bookId'] = $bookId;
    $itemCopy['folderId'] = $folderId;

    $mtime = null;
    $filePath = !empty($itemCopy['file']) ? $itemCopy['file'] : (!empty($itemCopy['url']) && !preg_match('/^(http|https):\/\//i', $itemCopy['url']) ? $itemCopy['url'] : '');

    if (!empty($filePath)) {
        $localPath = __DIR__ . '/../' . ltrim($filePath, '/');
        if (file_exists($localPath)) {
            $mtime = filemtime($localPath);
        }
    }

    if ($resolvedType === 'markdown') {
        if ($mtime !== null) {
            $itemCopy['mtime'] = $mtime;
            $items[] = $itemCopy;
        }
    } else if ($resolvedType === 'pdf') {
        $itemCopy['mtime'] = $mtime ?? time();
        $items[] = $itemCopy;
    } else if ($resolvedType === 'gdoc') {
        $itemCopy['mtime'] = $mtime ?? time();
        $items[] = $itemCopy;
    }
}

// Gather feed items (markdown, pdf, gdoc) under a node
function gatherFeedItems($node, &$items, $bookId, $folderId, $isViewer = false, $isAdmin = false, $parentVisibility = 'public') {
    $effectiveVis = resolveEffectiveVisibility($node['visibility'] ?? null, $parentVisibility);
    $nodeCopy = $node;
    $nodeCopy['visibility'] = $effectiveVis;

    if (!isNodeAccessible($nodeCopy, $isViewer, $isAdmin)) {
        return;
    }

    $isFolder = (isset($nodeCopy['type']) && strtolower($nodeCopy['type']) === 'folder') || !empty($nodeCopy['items']);

    if ($isFolder) {
        if (!empty($nodeCopy['items'])) {
            foreach ($nodeCopy['items'] as $item) {
                $itemIsFolder = (isset($item['type']) && strtolower($item['type']) === 'folder') || !empty($item['items']);
                if ($itemIsFolder) {
                    gatherFeedItems($item, $items, $bookId, $item['id'] ?? $item['slug'] ?? $folderId, $isViewer, $isAdmin, $effectiveVis);
                } else {
                    processSingleFeedItem($item, $items, $bookId, $folderId, $isViewer, $isAdmin, $effectiveVis);
                }
            }
        }
    } else {
        processSingleFeedItem($nodeCopy, $items, $bookId, $folderId, $isViewer, $isAdmin, $effectiveVis);
    }
}

$feedFiles = [];

if (empty($categoryId)) {
    // If no category specified, gather all files across all books
    foreach ($config['books'] ?? [] as $book) {
        $bId = $book['id'] ?? $book['slug'] ?? '';
        gatherFeedItems($book, $feedFiles, $bId, $bId, $isViewer, $isAdmin, $book['visibility'] ?? 'public');
    }
    $targetCategory = ['title' => 'All Updates'];
} else {
    $foundCtx = findCategoryPath($config['books'] ?? [], $categoryId);
    if (!$foundCtx) {
        http_response_code(404);
        echo json_encode(['error' => 'Category not found.']);
        exit;
    }

    $targetCategory = $foundCtx['node'];
    $foundBookId = $foundCtx['bookId'];
    $foundFolderId = $foundCtx['folderId'];

    if (!isNodeAccessible($targetCategory, $isViewer, $isAdmin)) {
        http_response_code(403);
        echo json_encode([
            'error' => 'Feed access denied. The requested category is private and a valid key or login is required.',
            'items' => []
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    gatherFeedItems($targetCategory, $feedFiles, $foundBookId, $foundFolderId, $isViewer, $isAdmin, $targetCategory['visibility'] ?? 'public');
}

// Sort by mtime descending
usort($feedFiles, function($a, $b) {
    return ($b['mtime'] ?? 0) - ($a['mtime'] ?? 0);
});

// Take the requested amount
$itemCount = isset($config['feedItemCount']) ? (int)$config['feedItemCount'] : 10;
if ($itemCount < 1) $itemCount = 10;
$topFiles = array_slice($feedFiles, 0, $itemCount);

// Determine base URL
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = dirname(dirname($_SERVER['SCRIPT_NAME']));
if ($scriptDir === '/' || $scriptDir === '\\') $scriptDir = '';
$baseUrl = $protocol . "://" . $host . $scriptDir . "/";

$feedItems = [];
foreach ($topFiles as $file) {
    $itemSlug = $file['slug'] ?? $file['id'] ?? '';
    $linkUrl = $baseUrl . "index.php?book=" . urlencode($file['bookId'] ?? '') . "&folder=" . urlencode($file['folderId'] ?? '') . "&chapter=" . urlencode($itemSlug);

    $feedItem = [
        'title' => $file['title'] ?? '',
        'slug'  => $itemSlug,
        'type'  => $file['resolvedType'],
        'url'   => $linkUrl,
        'date_modified' => date('c', $file['mtime'] ?? time()),
    ];

    if ($file['resolvedType'] === 'markdown') {
        $filePath = __DIR__ . '/../' . ltrim($file['file'], '/');
        $content = file_exists($filePath) ? file_get_contents($filePath) : '';

        // Replace relative markdown links and images: [text](path) or ![alt](path)
        $content = preg_replace_callback('/(\[.*?\]\()([^\)]+)(\))/i', function($matches) use ($baseUrl) {
            $url = trim($matches[2]);
            if (!preg_match('/^(http|https|mailto|ftp|\/|#)/i', $url)) {
                return $matches[1] . $baseUrl . $url . $matches[3];
            }
            return $matches[0];
        }, $content);

        // Replace relative HTML links and images: src="path" or href="path"
        $content = preg_replace_callback('/(src=["\']|href=["\'])([^"\']+)(["\'])/i', function($matches) use ($baseUrl) {
            $url = trim($matches[2]);
            if (!preg_match('/^(http|https|mailto|data:|\/|#)/i', $url)) {
                return $matches[1] . $baseUrl . $url . $matches[3];
            }
            return $matches[0];
        }, $content);

        $feedItem['content_markdown'] = $content;
    } else if ($file['resolvedType'] === 'pdf') {
        $rawPdfUrl = !empty($file['file']) ? $file['file'] : ($file['url'] ?? '');
        if (!empty($rawPdfUrl)) {
            if (preg_match('/^(http|https):\/\//i', $rawPdfUrl)) {
                $pdfFileUrl = $rawPdfUrl;
            } else {
                $pdfFileUrl = $baseUrl . ltrim($rawPdfUrl, '/');
            }
        } else {
            $pdfFileUrl = '';
        }
        $feedItem['file_url'] = $pdfFileUrl;
    } else if ($file['resolvedType'] === 'gdoc') {
        $feedItem['gdoc_url'] = $file['url'] ?? '';
        if (!empty($file['editUrl'])) {
            $feedItem['edit_url'] = $file['editUrl'];
        }
    }

    $feedItems[] = $feedItem;
}

header('Content-Type: application/json');
echo json_encode([
    'feed_title' => ($config['title'] ?? 'Qwiki') . ' - ' . ($targetCategory['title'] ?? 'Category'),
    'generator'  => 'Qwiki',
    'base_url'   => $baseUrl,
    'items'      => $feedItems
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

