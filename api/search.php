<?php
session_start();
header('Content-Type: application/json');

$configFile = __DIR__ . '/../qwiki.json';
if (!file_exists($configFile)) {
    echo json_encode(['success' => false, 'error' => 'Config file not found']);
    exit;
}

$config = json_decode(file_get_contents($configFile), true);

$currentUser = $_SESSION['qwiki_user'] ?? null;
$isAdmin = (!empty($currentUser) && $currentUser['role'] === 'admin') || !empty($_SESSION['qwiki_admin']);
$isViewer = !empty($currentUser);

$query = strtolower(trim($_GET['q'] ?? ''));
if (empty($query)) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

$results = [];

function search_node($node, $query, $isAdmin, $isViewer, &$results) {
    $visibility = $node['visibility'] ?? 'public';
    if (!$isAdmin) {
        if ($visibility === 'admin_only') return;
        if ($visibility === 'logged_in' && !$isViewer) return;
    }

    if (!empty($node['items'])) {
        foreach ($node['items'] as $item) {
            if (isset($item['type']) && $item['type'] === 'folder') {
                search_node($item, $query, $isAdmin, $isViewer, $results);
            } else {
                $isMatch = false;

                // Check title
                $title = strtolower($item['title'] ?? '');
                if (strpos($title, $query) !== false) {
                    $isMatch = true;
                }
                
                // Check description
                if (!$isMatch) {
                    $desc = strtolower($item['description'] ?? '');
                    if (strpos($desc, $query) !== false) {
                        $isMatch = true;
                    }
                }

                // Check markdown content
                if (!$isMatch && ($item['type'] ?? '') === 'markdown') {
                    $file = $item['file'] ?? '';
                    if ($file) {
                        $filePath = __DIR__ . '/../' . $file;
                        if (file_exists($filePath)) {
                            $content = strtolower(file_get_contents($filePath));
                            if (strpos($content, $query) !== false) {
                                $isMatch = true;
                            }
                        }
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
        search_node($book, $query, $isAdmin, $isViewer, $results);
    }
}

echo json_encode([
    'success' => true,
    'results' => array_unique($results)
]);
