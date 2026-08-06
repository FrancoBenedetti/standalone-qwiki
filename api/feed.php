<?php
// api/feed.php

session_start();

$configFile = __DIR__ . '/../qwiki.json';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration file qwiki.json not found.']);
    exit;
}

$config = json_decode(file_get_contents($configFile), true);

if (!empty($config['requireLoginToView'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Feed not available. Private portal mode is enabled.']);
    exit;
}

$categoryId = $_GET['category'] ?? '';
if (empty($categoryId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing category parameter.']);
    exit;
}

// Find the category and its context path
function findCategoryPath($nodes, $targetId, $bookId = null, $folderId = null) {
    foreach ($nodes as $node) {
        $currentBook = $bookId;
        $currentFolder = $folderId;
        
        if ($bookId === null) {
            $currentBook = $node['id'] ?? null;
        } else if (isset($node['type']) && $node['type'] === 'folder') {
            $currentFolder = $node['id'] ?? $currentFolder;
        }

        if (isset($node['id']) && $node['id'] === $targetId) {
            return ['node' => $node, 'bookId' => $currentBook, 'folderId' => $currentFolder];
        }

        if (!empty($node['items'])) {
            $found = findCategoryPath($node['items'], $targetId, $currentBook, $currentFolder);
            if ($found) return $found;
        }
    }
    return null;
}

$foundCtx = findCategoryPath($config['books'] ?? [], $categoryId);
if (!$foundCtx) {
    http_response_code(404);
    echo json_encode(['error' => 'Category not found.']);
    exit;
}

$targetCategory = $foundCtx['node'];
$foundBookId = $foundCtx['bookId'];
$foundFolderId = $foundCtx['folderId'];

// Gather all markdown files under this category
function gatherMarkdownFiles($node, &$files, $bookId, $folderId) {
    if (!empty($node['items'])) {
        foreach ($node['items'] as $item) {
            if (isset($item['type']) && $item['type'] === 'folder') {
                gatherMarkdownFiles($item, $files, $bookId, $item['id'] ?? $folderId);
            } else if (isset($item['type']) && $item['type'] === 'markdown') {
                if (!empty($item['file']) && file_exists(__DIR__ . '/../' . $item['file'])) {
                    $item['bookId'] = $bookId;
                    $item['folderId'] = $folderId;
                    $files[] = $item;
                }
            }
        }
    }
}

$markdownFiles = [];
gatherMarkdownFiles($targetCategory, $markdownFiles, $foundBookId, $foundFolderId);

// Sort by filemtime descending
usort($markdownFiles, function($a, $b) {
    $timeA = filemtime(__DIR__ . '/../' . $a['file']);
    $timeB = filemtime(__DIR__ . '/../' . $b['file']);
    return $timeB - $timeA;
});

// Take top 5
$topFiles = array_slice($markdownFiles, 0, 5);

// Determine base URL
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = dirname(dirname($_SERVER['SCRIPT_NAME']));
if ($scriptDir === '/' || $scriptDir === '\\') $scriptDir = '';
$baseUrl = $protocol . "://" . $host . $scriptDir . "/";

$feedItems = [];
foreach ($topFiles as $file) {
    $filePath = __DIR__ . '/../' . $file['file'];
    $content = file_get_contents($filePath);
    $mtime = filemtime($filePath);

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
    
    $linkUrl = $baseUrl . "index.php?book=" . urlencode($file['bookId'] ?? '') . "&folder=" . urlencode($file['folderId'] ?? '') . "&chapter=" . urlencode($file['slug'] ?? '');

    $feedItems[] = [
        'title' => $file['title'] ?? '',
        'slug'  => $file['slug'] ?? '',
        'url'   => $linkUrl,
        'date_modified' => date('c', $mtime),
        'content_markdown' => $content
    ];
}

header('Content-Type: application/json');
echo json_encode([
    'feed_title' => ($config['title'] ?? 'Qwiki') . ' - ' . ($targetCategory['title'] ?? 'Category'),
    'generator'  => 'Qwiki',
    'base_url'   => $baseUrl,
    'items'      => $feedItems
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
