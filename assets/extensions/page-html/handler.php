<?php
/**
 * HTML Page Extension Action Handler
 */
use Qwiki\Core\Auth;
use Qwiki\Core\Config;

if (!Auth::isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$title = trim($_POST['title'] ?? '');
$bookId = $_POST['bookId'] ?? '';
$content = $_POST['content'] ?? '';

if (empty($title)) {
    echo json_encode(['success' => false, 'error' => 'Document title is required']);
    exit;
}

$slug = Config::makeSlug($title);
if (empty($slug)) {
    $slug = 'doc-' . time();
}

$config = Config::load();
$baseDir = Config::getBaseDir();

function find_target_folder(&$node, $targetId) {
    if (($node['id'] ?? '') === $targetId) {
        return $node['folder'] ?? $node['id'];
    }
    if (!empty($node['items'])) {
        foreach ($node['items'] as &$child) {
            if (isset($child['type']) && $child['type'] === 'folder') {
                $found = find_target_folder($child, $targetId);
                if ($found !== null) {
                    $parentFolder = $node['folder'] ?? $node['id'];
                    return $parentFolder . '/' . $found;
                }
            }
        }
    }
    return null;
}

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
    @mkdir($contentDir, 0755, true);
}

$filePath = 'content/' . $targetFolder . '/' . $slug . '.html';
$absolutePath = $baseDir . '/' . $filePath;

if (empty($content)) {
    $content = "<!DOCTYPE html>\n<html>\n<head>\n    <meta charset=\"UTF-8\">\n    <title>" . htmlspecialchars($title) . "</title>\n    <style>body { font-family: system-ui, sans-serif; padding: 2rem; }</style>\n</head>\n<body>\n    <h1>" . htmlspecialchars($title) . "</h1>\n    <p>Welcome to this HTML document.</p>\n</body>\n</html>";
}

if (file_put_contents($absolutePath, $content) === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to write HTML file']);
    exit;
}

$chapterData = [
    'title' => $title,
    'slug' => $slug,
    'type' => 'html',
    'file' => $filePath
];

$inserted = false;
foreach ($config['books'] as &$book) {
    if (insert_html_chapter($book, $bookId, $chapterData)) {
        $inserted = true;
        break;
    }
}

if ($inserted && Config::save($config)) {
    echo json_encode([
        'success' => true,
        'slug' => $slug,
        'bookId' => $bookId,
        'file' => $filePath
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save configuration']);
}
exit;
