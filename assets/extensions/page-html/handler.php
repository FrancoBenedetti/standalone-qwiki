<?php
/**
 * HTML Page Extension Action Handler
 */
use Qwiki\Core\Auth;
use Qwiki\Core\Config;

if (!Auth::isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    return;
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

    if (file_put_contents($absolutePath, $content) === false) {
        echo json_encode(['success' => false, 'error' => 'Failed to save HTML file to disk']);
        return;
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

$config = Config::load();

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
    $content = "<!DOCTYPE html>\n<html>\n<head>\n    <meta charset=\"UTF-8\">\n    <title>" . htmlspecialchars($title) . "</title>\n    <style>body { font-family: system-ui, sans-serif; padding: 2rem; }</style>\n</head>\n<body>\n    <h1>" . htmlspecialchars($title) . "</h1>\n    <p>Welcome to this HTML document.</p>\n</body>\n</html>";
}

if (file_put_contents($absolutePath, $content) === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to write HTML file']);
    return;
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
return;
