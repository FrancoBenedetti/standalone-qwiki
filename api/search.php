<?php
require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\ExtensionManager;

Auth::startSession();
header('Content-Type: application/json');

$config = Config::load();
$baseDir = Config::getBaseDir();
$isAdmin = Auth::isAdmin();
$isViewer = Auth::isViewer();

$query = strtolower(trim($_GET['q'] ?? ''));
if (empty($query)) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

$results = [];
$extManager = ExtensionManager::getInstance();

function search_node_tree($node, $query, $isAdmin, $isViewer, $baseDir, $extManager, &$results) {
    $visibility = $node['visibility'] ?? 'public';
    if (!$isAdmin) {
        if ($visibility === 'admin_only') return;
        if ($visibility === 'logged_in' && !$isViewer) return;
    }

    if (!empty($node['items'])) {
        foreach ($node['items'] as $item) {
            if (isset($item['type']) && $item['type'] === 'folder') {
                search_node_tree($item, $query, $isAdmin, $isViewer, $baseDir, $extManager, $results);
            } else {
                $isMatch = false;

                // 1. Match title
                $title = strtolower($item['title'] ?? '');
                if (strpos($title, $query) !== false) {
                    $isMatch = true;
                }

                // 2. Match description
                if (!$isMatch) {
                    $desc = strtolower($item['description'] ?? '');
                    if (strpos($desc, $query) !== false) {
                        $isMatch = true;
                    }
                }

                // 3. Match content (via ExtensionManager for Markdown, HTML, and other page types)
                if (!$isMatch) {
                    $extracted = $extManager->extractSearchableText($item, $baseDir);
                    if ($extracted && strpos(strtolower($extracted), $query) !== false) {
                        $isMatch = true;
                    }
                }

                if ($isMatch && isset($item['slug'])) {
                    $results[] = $item['slug'];
                }
            }
        }
    }
}

if (!empty($config['books'])) {
    foreach ($config['books'] as $book) {
        search_node_tree($book, $query, $isAdmin, $isViewer, $baseDir, $extManager, $results);
    }
}

echo json_encode([
    'success' => true,
    'results' => array_values(array_unique($results))
]);
