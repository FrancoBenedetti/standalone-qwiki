<?php
require_once __DIR__ . '/../lib/Parsedown.php';
require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\Navigation;
use Qwiki\Core\ExtensionManager;

if (!defined('QWIKI_VERSION')) {
    define('QWIKI_VERSION', Config::VERSION);
}

Auth::startSession();
header('Content-Type: application/json');

$config = Config::load();
$baseDir = Config::getBaseDir();
$action = $_REQUEST['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '';

// Helper for tree category updates
function update_node_meta(&$node, $targetId, $newTitle, $newTheme, $newVisibility) {
    if (($node['id'] ?? '') === $targetId) {
        $node['title'] = $newTitle;
        if ($newTheme !== '') {
            $node['theme'] = $newTheme;
        } else {
            unset($node['theme']);
        }
        $node['visibility'] = $newVisibility;
        return true;
    }
    if (!empty($node['items'])) {
        foreach ($node['items'] as &$sub) {
            if (isset($sub['type']) && $sub['type'] === 'folder') {
                if (update_node_meta($sub, $targetId, $newTitle, $newTheme, $newVisibility)) {
                    return true;
                }
            }
        }
    }
    return false;
}

// Helper for tree node deletions
function delete_node_recursive(&$list, $targetId) {
    $filtered = [];
    $deleted = false;
    foreach ($list as &$b) {
        if (($b['id'] ?? '') === $targetId) {
            $deleted = true;
            continue;
        }
        if (!empty($b['items'])) {
            if (delete_node_recursive($b['items'], $targetId)) {
                $deleted = true;
            }
        }
        $filtered[] = $b;
    }
    $list = $filtered;
    return $deleted;
}

// Helper for chapter insertion into tree
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

// Helper for chapter update in tree
function update_chapter_in_node(&$node, $slug, $updatedData) {
    if (!empty($node['items'])) {
        foreach ($node['items'] as &$ch) {
            if (!isset($ch['type']) || $ch['type'] !== 'folder') {
                if (($ch['slug'] ?? '') === $slug) {
                    if (!empty($updatedData['title'])) $ch['title'] = $updatedData['title'];
                    if (!empty($updatedData['type'])) $ch['type'] = $updatedData['type'];
                    if (isset($updatedData['url'])) $ch['url'] = $updatedData['url'];
                    if (isset($updatedData['editUrl'])) $ch['editUrl'] = $updatedData['editUrl'];
                    if (isset($updatedData['file'])) $ch['file'] = $updatedData['file'];
                    if (isset($updatedData['theme']) && $updatedData['theme'] !== '') {
                        $ch['theme'] = $updatedData['theme'];
                    } elseif (isset($ch['theme'])) {
                        unset($ch['theme']);
                    }
                    if (isset($updatedData['description']) && $updatedData['description'] !== '') {
                        $ch['description'] = $updatedData['description'];
                    } elseif (isset($ch['description'])) {
                        unset($ch['description']);
                    }
                    if (isset($updatedData['image']) && $updatedData['image'] !== '') {
                        $ch['image'] = $updatedData['image'];
                    } elseif (isset($ch['image'])) {
                        unset($ch['image']);
                    }
                    return true;
                }
            } else {
                if (update_chapter_in_node($ch, $slug, $updatedData)) {
                    return true;
                }
            }
        }
    }
    return false;
}

// Helper for chapter deletion from tree
function delete_chapter_from_node(&$node, $slug) {
    if (!empty($node['items'])) {
        $newItems = [];
        foreach ($node['items'] as &$item) {
            if (!isset($item['type']) || $item['type'] !== 'folder') {
                if (($item['slug'] ?? '') !== $slug) {
                    $newItems[] = $item;
                }
            } else {
                delete_chapter_from_node($item, $slug);
                $newItems[] = $item;
            }
        }
        $node['items'] = $newItems;
    }
}

if (isset($_POST['content_base64']) && !isset($_POST['content'])) {
    $_POST['content'] = base64_decode($_POST['content_base64']);
    $_REQUEST['content'] = $_POST['content'];
}

switch ($action) {
    case 'login':
        $username = trim($_POST['username'] ?? 'admin');
        $password = $_POST['password'] ?? '';
        echo json_encode(Auth::login($username, $password, $config));
        break;

    case 'logout':
        echo json_encode(Auth::logout());
        break;

    case 'list_users':
        echo json_encode(Auth::listUsers());
        break;

    case 'add_user':
        $newUsername = trim($_POST['username'] ?? '');
        $newPassword = $_POST['password'] ?? '';
        $newRole     = $_POST['role'] ?? 'viewer';
        echo json_encode(Auth::addUser($newUsername, $newPassword, $newRole));
        break;

    case 'delete_user':
        $targetUsername = trim($_POST['username'] ?? '');
        echo json_encode(Auth::deleteUser($targetUsername));
        break;

    case 'update_user_password':
        $targetUsername = trim($_POST['username'] ?? '');
        $newPassword = $_POST['newPassword'] ?? $_POST['password'] ?? '';
        echo json_encode(Auth::updateUserPassword($targetUsername, $newPassword));
        break;

    case 'add_book':
        if (!Auth::isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        $title = trim($_POST['title'] ?? '');
        $bookId = Config::makeSlug($_POST['id'] ?? $title);
        if (empty($title) || empty($bookId)) {
            echo json_encode(['success' => false, 'error' => 'Category title is required']);
            exit;
        }
        $bookFolder = $baseDir . '/content/' . $bookId;
        if (!is_dir($bookFolder)) {
            @mkdir($bookFolder, 0755, true);
        }
        $config['books'][] = [
            'id' => $bookId,
            'title' => $title,
            'folder' => 'content/' . $bookId,
            'items' => []
        ];
        if (Config::save($config)) {
            echo json_encode(['success' => true, 'bookId' => $bookId]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update qwiki.json']);
        }
        break;

    case 'edit_book':
        if (!Auth::isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        $bookId = $_POST['bookId'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $theme = trim($_POST['theme'] ?? '');
        $visibility = trim($_POST['visibility'] ?? 'public');
        if (empty($bookId) || empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Category ID and Title are required']);
            exit;
        }
        $updated = false;
        foreach ($config['books'] as &$book) {
            if (update_node_meta($book, $bookId, $title, $theme, $visibility)) {
                $updated = true;
                break;
            }
        }
        if ($updated && Config::save($config)) {
            echo json_encode(['success' => true, 'bookId' => $bookId]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Category not found or save failed']);
        }
        break;

    case 'delete_book':
        if (!Auth::isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        $bookId = $_POST['bookId'] ?? '';
        if (empty($bookId)) {
            echo json_encode(['success' => false, 'error' => 'Category ID is required']);
            exit;
        }
        if (delete_node_recursive($config['books'], $bookId)) {
            Config::save($config);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Category not found or failed to delete']);
        }
        break;

    case 'create_markdown':
        if (!Auth::isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        $bookId = $_POST['bookId'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? "";
        if (isset($_POST['content_base64'])) {
            $content = base64_decode($_POST['content_base64']);
        }
        if (empty($title) && !empty($content)) {
            if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
                $title = trim(strip_tags($matches[1]));
            }
        }
        if (empty($content)) {
            $content = "# {$title}\n\nWrite your documentation content here...";
        }
        if (empty($bookId) || empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Category and title are required']);
            exit;
        }
        $slug = Config::makeSlug($title);
        $targetRelDir = 'content/' . $bookId;
        $targetAbsDir = $baseDir . '/' . $targetRelDir;
        if (!is_dir($targetAbsDir)) {
            @mkdir($targetAbsDir, 0755, true);
        }
        $targetRelFile = $targetRelDir . '/' . $slug . '.md';
        $targetAbsFile = $baseDir . '/' . $targetRelFile;
        if (file_put_contents($targetAbsFile, $content) === false) {
            echo json_encode(['success' => false, 'error' => 'Failed to create Markdown file']);
            exit;
        }
        $chapterData = [
            'title' => $title,
            'slug' => $slug,
            'type' => 'markdown',
            'file' => $targetRelFile
        ];
        $added = false;
        foreach ($config['books'] as &$book) {
            if (insert_chapter_into_node($book, $bookId, $chapterData)) {
                $added = true;
                break;
            }
        }
        if ($added && Config::save($config)) {
            echo json_encode(['success' => true, 'bookId' => $bookId, 'slug' => $slug]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update qwiki.json']);
        }
        break;

    case 'edit_chapter':
        if (!Auth::isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        $slug = $_POST['slug'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $type = $_POST['type'] ?? 'markdown';
        $url = trim($_POST['url'] ?? '');
        $editUrl = trim($_POST['editUrl'] ?? '');
        $file = trim($_POST['file'] ?? '');
        $theme = trim($_POST['theme'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $image = trim($_POST['image'] ?? '');
        if (empty($slug) || empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Document Slug and Title are required']);
            exit;
        }
        if ($type === 'gdoc' && !empty($url)) {
            if (strpos($url, 'embedded=true') === false) {
                $url .= (strpos($url, '?') !== false) ? '&embedded=true' : '?embedded=true';
            }
        }
        $updatedData = [
            'title' => $title,
            'type' => $type,
            'url' => $url,
            'editUrl' => $editUrl,
            'file' => $file,
            'theme' => $theme,
            'description' => $description,
            'image' => $image
        ];
        $updated = false;
        foreach ($config['books'] as &$book) {
            if (update_chapter_in_node($book, $slug, $updatedData)) {
                $updated = true;
                break;
            }
        }
        if ($updated && Config::save($config)) {
            echo json_encode(['success' => true, 'slug' => $slug]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Document entry not found or save failed']);
        }
        break;

    case 'save_markdown':
        if (!Auth::isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        $relFile = $_POST['file'] ?? '';
        $content = $_POST['content'] ?? '';
        if (isset($_POST['content_base64'])) {
            $content = base64_decode($_POST['content_base64']);
        }
        $targetPath = Config::safePath($baseDir, $relFile);
        if (!$targetPath || !preg_match('/\.(md|markdown)$/i', $targetPath)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file path']);
            exit;
        }
        if (file_put_contents($targetPath, $content) !== false) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save file']);
        }
        break;

    case 'upload_file':
        if (!Auth::isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        $bookId = $_POST['bookId'] ?? '';
        $title = trim($_POST['title'] ?? '');
        if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK || empty($title) || empty($bookId)) {
            echo json_encode(['success' => false, 'error' => 'Missing file or required parameters']);
            exit;
        }
        $fileName = basename($_FILES['document']['name']);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['md', 'pdf'])) {
            echo json_encode(['success' => false, 'error' => 'Only .md and .pdf files are supported via direct upload']);
            exit;
        }
        $slug = Config::makeSlug($title);
        $targetRelDir = 'content/' . $bookId;
        $targetAbsDir = $baseDir . '/' . $targetRelDir;
        if (!is_dir($targetAbsDir)) {
            @mkdir($targetAbsDir, 0755, true);
        }
        $targetRelFile = $targetRelDir . '/' . $slug . '.' . $ext;
        $targetAbsFile = $baseDir . '/' . $targetRelFile;
        if (move_uploaded_file($_FILES['document']['tmp_name'], $targetAbsFile)) {
            $docType = ($ext === 'pdf') ? 'pdf' : 'markdown';
            $chapterData = [
                'title' => $title,
                'slug' => $slug,
                'type' => $docType,
                'file' => $targetRelFile
            ];
            foreach ($config['books'] as &$book) {
                if (insert_chapter_into_node($book, $bookId, $chapterData)) {
                    break;
                }
            }
            Config::save($config);
            echo json_encode(['success' => true, 'bookId' => $bookId, 'slug' => $slug]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
        }
        break;

    case 'add_gdoc':
        if (!Auth::isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        $bookId = $_POST['bookId'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $editUrl = trim($_POST['editUrl'] ?? '');
        if (empty($bookId) || empty($title) || empty($url)) {
            echo json_encode(['success' => false, 'error' => 'Title and Published Google Doc URL required']);
            exit;
        }
        if (strpos($url, 'embedded=true') === false) {
            $url .= (strpos($url, '?') !== false) ? '&embedded=true' : '?embedded=true';
        }
        $slug = Config::makeSlug($title);
        $chapterData = [
            'title' => $title,
            'slug' => $slug,
            'type' => 'gdoc',
            'url' => $url,
            'editUrl' => $editUrl
        ];
        foreach ($config['books'] as &$book) {
            if (insert_chapter_into_node($book, $bookId, $chapterData)) {
                break;
            }
        }
        Config::save($config);
        echo json_encode(['success' => true, 'bookId' => $bookId, 'slug' => $slug]);
        break;

    case 'delete_chapter':
        if (!Auth::isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        $bookId = $_POST['bookId'] ?? '';
        $slug = $_POST['slug'] ?? '';
        if (empty($bookId) || empty($slug)) {
            echo json_encode(['success' => false, 'error' => 'Book ID and chapter slug required']);
            exit;
        }
        foreach ($config['books'] as &$book) {
            delete_chapter_from_node($book, $slug);
        }
        Config::save($config);
        echo json_encode(['success' => true]);
        break;

    case 'update_settings':
        if (!Auth::isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        $title = trim($_POST['title'] ?? '');
        $logoText = trim($_POST['logoText'] ?? '');
        $logoUrl = trim($_POST['logoUrl'] ?? '');
        $theme = trim($_POST['theme'] ?? 'theme-default.css');
        $defaultBook = trim($_POST['defaultBook'] ?? '');
        $requireLoginToView = isset($_POST['requireLoginToView']) && $_POST['requireLoginToView'] === '1';
        $showDocTypesOnlyToAdmin = isset($_POST['showDocTypesOnlyToAdmin']) && $_POST['showDocTypesOnlyToAdmin'] === '1';
        $shareDescription = trim($_POST['shareDescription'] ?? '');
        $shareImageUrl = trim($_POST['shareImageUrl'] ?? '');
        $feedItemCount = isset($_POST['feedItemCount']) ? (int)$_POST['feedItemCount'] : 10;
        $feedAccessToken = trim($_POST['feedAccessToken'] ?? '');

        if (isset($_POST['title'])) $config['title'] = $title;
        if (isset($_POST['logoText'])) $config['logoText'] = $logoText;
        if (isset($_POST['logoUrl'])) $config['logoUrl'] = $logoUrl;
        if (isset($_POST['theme'])) $config['theme'] = $theme;
        $config['showDocTypesOnlyToAdmin'] = $showDocTypesOnlyToAdmin;
        if (isset($config['hideDocTypesFromPublic'])) {
            unset($config['hideDocTypesFromPublic']);
        }
        if (isset($_POST['defaultBook'])) $config['defaultBook'] = $defaultBook;
        $config['requireLoginToView'] = $requireLoginToView;
        if (isset($_POST['shareDescription'])) $config['shareDescription'] = $shareDescription;
        if (isset($_POST['shareImageUrl'])) $config['shareImageUrl'] = $shareImageUrl;
        $config['feedItemCount'] = $feedItemCount;
        $config['feedAccessToken'] = $feedAccessToken;

        Config::save($config);
        echo json_encode(['success' => true]);
        break;

    case 'reorder_tree':
        if (!Auth::isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        $rawInput = file_get_contents('php://input');
        $json = json_decode($rawInput, true);
        $tree = $json['tree'] ?? $_POST['tree'] ?? null;
        if (is_string($tree)) {
            $tree = json_decode($tree, true);
        }
        if (is_array($tree)) {
            $existingCategories = [];
            $existingDocuments = [];

            $indexExistingNodes = function ($nodes) use (&$indexExistingNodes, &$existingCategories, &$existingDocuments) {
                if (!is_array($nodes)) return;
                foreach ($nodes as $node) {
                    if (isset($node['id'])) {
                        $catCopy = $node;
                        unset($catCopy['items']);
                        $existingCategories[$node['id']] = $catCopy;
                    }
                    if (!empty($node['items']) && is_array($node['items'])) {
                        foreach ($node['items'] as $item) {
                            if (isset($item['type']) && $item['type'] === 'folder') {
                                $indexExistingNodes([$item]);
                            } elseif (isset($item['slug'])) {
                                $existingDocuments[$item['slug']] = $item;
                            }
                        }
                    }
                }
            };
            $indexExistingNodes($config['books'] ?? []);

            $mergeTree = function ($nodes) use (&$mergeTree, &$existingCategories, &$existingDocuments) {
                if (!is_array($nodes)) return [];
                $merged = [];
                foreach ($nodes as $node) {
                    if (!is_array($node)) continue;
                    $nodeId = $node['id'] ?? null;
                    if ($nodeId !== null && isset($existingCategories[$nodeId])) {
                        $orig = $existingCategories[$nodeId];
                        $mergedNode = array_merge($orig, $node);
                        if (empty($node['visibility']) && isset($orig['visibility'])) {
                            $mergedNode['visibility'] = $orig['visibility'];
                        }
                        if (empty($node['theme']) && isset($orig['theme'])) {
                            $mergedNode['theme'] = $orig['theme'];
                        }
                        if (empty($node['folder']) && isset($orig['folder'])) {
                            $mergedNode['folder'] = $orig['folder'];
                        }
                    } else {
                        $mergedNode = $node;
                    }

                    if (isset($node['items']) && is_array($node['items'])) {
                        $mergedItems = [];
                        foreach ($node['items'] as $item) {
                            if (!is_array($item)) continue;
                            if (isset($item['type']) && $item['type'] === 'folder') {
                                $mergedSub = $mergeTree([$item]);
                                if (!empty($mergedSub)) {
                                    $mergedItems[] = $mergedSub[0];
                                }
                            } elseif (isset($item['slug'])) {
                                $slug = $item['slug'];
                                if (isset($existingDocuments[$slug])) {
                                    $origDoc = $existingDocuments[$slug];
                                    $mergedDoc = array_merge($origDoc, $item);
                                    foreach (['theme', 'description', 'image', 'file', 'url', 'editUrl'] as $field) {
                                        if (empty($item[$field]) && isset($origDoc[$field])) {
                                            $mergedDoc[$field] = $origDoc[$field];
                                        }
                                    }
                                    $mergedItems[] = $mergedDoc;
                                } else {
                                    $mergedItems[] = $item;
                                }
                            } else {
                                $mergedItems[] = $item;
                            }
                        }
                        $mergedNode['items'] = $mergedItems;
                    }
                    $merged[] = $mergedNode;
                }
                return $merged;
            };

            $config['books'] = $mergeTree($tree);
            if (Config::save($config)) {
                echo json_encode(['success' => true]);
                exit;
            }
        }
        echo json_encode(['success' => false, 'error' => 'Invalid tree data provided']);
        break;

    case 'upload_image':
        if (!Auth::isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No image file uploaded or upload error']);
            exit;
        }
        $fileName = basename($_FILES['image']['name']);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExts = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];
        if (!in_array($ext, $allowedExts)) {
            echo json_encode(['success' => false, 'error' => 'Invalid image format']);
            exit;
        }
        $uploadDir = $baseDir . '/uploads/images/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        $safeName = preg_replace('/[^a-z0-9\._-]/', '-', strtolower(pathinfo($fileName, PATHINFO_FILENAME)));
        $newFileName = time() . '-' . $safeName . '.' . $ext;
        $targetPath = $uploadDir . $newFileName;
        $relUrl = 'uploads/images/' . $newFileName;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            echo json_encode(['success' => true, 'url' => $relUrl, 'alt' => pathinfo($fileName, PATHINFO_FILENAME)]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save image to server']);
        }
        break;

    case 'list_themes':
        if (!Auth::isAdmin()) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
        $themes = [];
        $dir = $baseDir . '/assets/css/';
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $f) {
                if (preg_match('/^theme-.*\.css$/', $f)) {
                    $themes[] = $f;
                }
            }
        }
        echo json_encode(['success' => true, 'themes' => $themes]);
        break;

    case 'get_theme':
        if (!Auth::isAdmin()) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
        $themeFile = basename($_POST['theme'] ?? $_GET['theme'] ?? '');
        if (empty($themeFile)) { echo json_encode(['success' => false, 'error' => 'No theme specified']); exit; }
        $path = $baseDir . '/assets/css/' . $themeFile;
        if (file_exists($path)) {
            echo json_encode(['success' => true, 'content' => file_get_contents($path)]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Theme not found']);
        }
        break;

    case 'save_theme':
        if (!Auth::isAdmin()) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
        $themeFile = basename($_POST['theme'] ?? '');
        $content = $_POST['content'] ?? '';
        if (isset($_POST['content_base64'])) {
            $content = base64_decode($_POST['content_base64']);
        }
        if (empty($themeFile) || empty($content)) { echo json_encode(['success' => false, 'error' => 'Invalid parameters']); exit; }
        if (!preg_match('/^theme-[a-zA-Z0-9-]+\.css$/', $themeFile)) { echo json_encode(['success' => false, 'error' => 'Invalid theme file name']); exit; }
        $path = $baseDir . '/assets/css/' . $themeFile;
        if (file_put_contents($path, $content) !== false) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save theme']);
        }
        break;

    case 'check_updates':
        if (!Auth::isAdmin()) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
        $cacheFile = $baseDir . '/uploads/update_cache.json';
        $currVerClean = ltrim(preg_replace('/^v\.?/i', '', trim(QWIKI_VERSION)), 'vV');

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
            $cache = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cache) && isset($cache['version'])) {
                $latestVerClean = ltrim(preg_replace('/^v\.?/i', '', trim($cache['version'])), 'vV');
                $hasUpdate = version_compare($latestVerClean, $currVerClean, '>');
                echo json_encode([
                    'success' => true,
                    'has_update' => $hasUpdate,
                    'version' => $cache['version'],
                    'notes' => $cache['notes'] ?? '',
                    'notes_html' => $cache['notes_html'] ?? '',
                    'zip_url' => $cache['zip_url'] ?? ''
                ]);
                exit;
            }
        }

        $opts = ['http' => ['method' => 'GET', 'header' => ['User-Agent: PHP-Qwiki-Updater']]];
        $context = stream_context_create($opts);
        $response = @file_get_contents('https://api.github.com/repos/FrancoBenedetti/standalone-qwiki/releases', false, $context);
        if ($response) {
            $releases = json_decode($response, true);
            if (!empty($releases) && is_array($releases)) {
                $latest = $releases[0];
                $latestVersion = ltrim(preg_replace('/^v\.?/i', '', trim($latest['tag_name'] ?? '')), 'vV');
                $hasUpdate = version_compare($latestVersion, $currVerClean, '>');
                
                $parsedown = new Parsedown();
                $parsedown->setSafeMode(true);
                $notesHtml = $parsedown->text($latest['body'] ?? '');

                $data = [
                    'has_update' => $hasUpdate,
                    'version' => $latest['tag_name'],
                    'notes' => $latest['body'] ?? '',
                    'notes_html' => $notesHtml,
                    'zip_url' => $latest['zipball_url'] ?? ''
                ];
                if (!is_dir($baseDir . '/uploads')) @mkdir($baseDir . '/uploads', 0755, true);
                file_put_contents($cacheFile, json_encode($data));
                echo json_encode(array_merge(['success' => true], $data));
                exit;
            }
        }
        echo json_encode(['success' => false, 'error' => 'Failed to check for updates']);
        break;

    case 'install_update':
        if (!Auth::isAdmin()) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
        $zipUrl = $_POST['zip_url'] ?? '';
        if (empty($zipUrl)) { echo json_encode(['success' => false, 'error' => 'Missing zip URL']); exit; }
        if (!class_exists('ZipArchive')) {
            echo json_encode(['success' => false, 'error' => 'ZipArchive PHP extension is not installed']);
            exit;
        }
        $tempZip = $baseDir . '/uploads/update_temp.zip';
        $opts = ['http' => ['method' => 'GET', 'header' => ['User-Agent: PHP-Qwiki-Updater']]];
        $context = stream_context_create($opts);
        $zipData = @file_get_contents($zipUrl, false, $context);
        if (!$zipData) {
            echo json_encode(['success' => false, 'error' => 'Failed to download update']);
            exit;
        }
        file_put_contents($tempZip, $zipData);
        $zip = new ZipArchive;
        if ($zip->open($tempZip) === TRUE) {
            $rootFolder = '';
            // NOTE: We do not exclude assets/extensions/ here because we need built-in extensions to receive bug fixes.
            // Custom extensions added by users will not be deleted, as ZipArchive extraction only overwrites existing files.
            $excludes = ['content/', 'uploads/', 'qwiki.json', 'users.json'];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if ($i === 0) $rootFolder = $filename;
                if ($filename === $rootFolder) continue;
                $relativePath = substr($filename, strlen($rootFolder));
                if (empty($relativePath)) continue;
                $skip = false;
                foreach ($excludes as $ex) {
                    if (strpos($relativePath, $ex) === 0 || $relativePath === $ex) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) continue;
                $targetPath = $baseDir . '/' . $relativePath;
                if (substr($filename, -1) === '/') {
                    if (!is_dir($targetPath)) @mkdir($targetPath, 0755, true);
                } else {
                    $dir = dirname($targetPath);
                    if (!is_dir($dir)) @mkdir($dir, 0755, true);
                    $content = $zip->getFromIndex($i);
                    file_put_contents($targetPath, $content);
                }
            }
            $zip->close();
            @unlink($tempZip);
            if (file_exists($baseDir . '/uploads/update_cache.json')) {
                @unlink($baseDir . '/uploads/update_cache.json');
            }
            echo json_encode(['success' => true]);
        } else {
            @unlink($tempZip);
            echo json_encode(['success' => false, 'error' => 'Failed to extract update zip']);
        }
        break;

    default:
        // Try extension action handlers
        $extResult = ExtensionManager::getInstance()->handleAction($action, $_REQUEST);
        if ($extResult) {
            exit;
        }
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
