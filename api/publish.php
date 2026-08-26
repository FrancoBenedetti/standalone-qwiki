<?php
header('Content-Type: application/json');

$configFile = __DIR__ . '/../qwiki.json';

if (!file_exists($configFile)) {
    echo json_encode(['success' => false, 'error' => 'Configuration file missing']);
    exit;
}

$config = json_decode(file_get_contents($configFile), true);

// 1. Authenticate via API Key
$publishApiKey = $config['publishApiKey'] ?? '';
if (empty($publishApiKey)) {
    echo json_encode(['success' => false, 'error' => 'Publishing via API is disabled (no API key configured)']);
    exit;
}

// Get API Key from header or POST body
$providedKey = $_SERVER['HTTP_X_API_KEY'] ?? $_POST['api_key'] ?? '';
if ($providedKey !== $publishApiKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

// 2. Extract and Sanitize Inputs
$bookId = trim($_POST['bookId'] ?? '');
$title = trim($_POST['title'] ?? '');
$content = $_POST['content'] ?? '';

if (empty($bookId) || empty($title) || empty($content)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'bookId, title, and content are required']);
    exit;
}

// Sanitize bookId to prevent path traversal (allow only alphanumeric and dashes)
if (!preg_match('/^[a-zA-Z0-9\-]+$/', $bookId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid bookId format']);
    exit;
}

function make_slug($text) {
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

function save_config($configFile, $config) {
    return file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
}

// 3. Sanitize Markdown Content against basic XSS
function sanitize_markdown($markdown) {
    // Remove dangerous HTML tags
    $dangerous_tags = ['script', 'iframe', 'object', 'embed', 'applet', 'meta', 'link', 'style', 'base', 'form'];
    foreach ($dangerous_tags as $tag) {
        $markdown = preg_replace('/<\/?\s*' . $tag . '\b[^>]*>/is', '', $markdown);
    }
    
    // Remove inline event handlers (e.g. onload=..., onerror=...)
    $markdown = preg_replace('/on[a-z]+\s*=\s*(["\']).*?\1/is', '', $markdown);
    $markdown = preg_replace('/on[a-z]+\s*=\s*[^>\s]+/is', '', $markdown);
    
    // Remove javascript: URIs
    $markdown = preg_replace('/href\s*=\s*(["\']?)javascript:.*?\1/is', 'href="#"', $markdown);
    
    return $markdown;
}

$sanitizedContent = sanitize_markdown($content);

// 4. Generate Unique Filename
$baseSlug = make_slug($title);
$targetRelDir = 'content/' . $bookId;
$targetAbsDir = __DIR__ . '/../' . $targetRelDir;

if (!is_dir($targetAbsDir)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Category (bookId) does not exist']);
    exit;
}

$slug = $baseSlug;
$counter = 1;
$targetAbsFile = $targetAbsDir . '/' . $slug . '.md';

// Auto-increment filename if it exists
while (file_exists($targetAbsFile)) {
    $slug = $baseSlug . '-' . $counter;
    $targetAbsFile = $targetAbsDir . '/' . $slug . '.md';
    $counter++;
}

$targetRelFile = $targetRelDir . '/' . $slug . '.md';

// Save the file
if (file_put_contents($targetAbsFile, $sanitizedContent) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to write markdown file']);
    exit;
}

// 5. Update qwiki.json
$chapterData = [
    'title' => $title,
    'slug' => $slug,
    'type' => 'markdown',
    'file' => $targetRelFile
];

function insert_chapter_into_node(&$node, $targetFolderId, $chapterData) {
    if (($node['id'] ?? '') === $targetFolderId) {
        if (!isset($node['items'])) $node['items'] = [];
        $node['items'][] = $chapterData;
        return true;
    }
    if (!empty($node['items'])) {
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

$added = false;
foreach ($config['books'] as &$book) {
    if (insert_chapter_into_node($book, $bookId, $chapterData)) {
        $added = true;
        break;
    }
}

if ($added && save_config($configFile, $config)) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'];
    $path = dirname(dirname($_SERVER['REQUEST_URI']));
    // Clean path (might have a trailing slash)
    $path = rtrim($path, '/');
    
    $url = $protocol . $domainName . $path . "/" . urlencode($bookId) . "/" . urlencode($slug);
    
    echo json_encode([
        'success' => true, 
        'bookId' => $bookId, 
        'slug' => $slug,
        'url' => $url
    ]);
} else {
    // Rollback file creation if config fails
    @unlink($targetAbsFile);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update qwiki.json']);
}
