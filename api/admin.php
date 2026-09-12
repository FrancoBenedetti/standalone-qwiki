<?php
require_once __DIR__ . '/../lib/Parsedown.php';
require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';
require_once __DIR__ . '/../lib/Core/LockManager.php';
require_once __DIR__ . '/../lib/Core/SubwikiManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\Navigation;
use Qwiki\Core\ExtensionManager;
use Qwiki\Core\LockManager;
use Qwiki\Core\SubwikiManager;

if (!defined('QWIKI_VERSION')) {
    define('QWIKI_VERSION', Config::VERSION);
}

Auth::startSession();
if (!headers_sent()) {
    header('Content-Type: application/json');
}

$config = Config::load();
$baseDir = Config::getBaseDir();
$action = $_REQUEST['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '';

// Helper for tree category updates
if (!function_exists('update_node_meta')) {
    function update_node_meta(&$node, $targetId, $newTitle, $newTheme, $newVisibility, $readOnly = null) {
        if (($node['id'] ?? '') === $targetId) {
            $node['title'] = $newTitle;
            if ($newTheme !== '') {
                $node['theme'] = $newTheme;
            } else {
                unset($node['theme']);
            }
            $node['visibility'] = $newVisibility;
            if ($readOnly !== null) {
                if ($readOnly) {
                    $node['readOnly'] = true;
                    $node['editable'] = false;
                } else {
                    unset($node['readOnly']);
                    unset($node['editable']);
                    unset($node['locked']);
                }
            }
            return true;
        }
        if (!empty($node['items'])) {
            foreach ($node['items'] as &$sub) {
                if (isset($sub['type']) && $sub['type'] === 'folder') {
                    if (update_node_meta($sub, $targetId, $newTitle, $newTheme, $newVisibility, $readOnly)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}

// Helper to validate URL protocol safety (prevent javascript:, data:, vbscript:)
if (!function_exists('is_safe_url')) {
    function is_safe_url($url) {
        $trimmed = trim($url);
        if (empty($trimmed)) return false;
        if (preg_match('/^\s*(javascript|data|vbscript):/i', $trimmed)) {
            return false;
        }
        if (preg_match('/^([a-z0-9+.-]+):/i', $trimmed, $matches)) {
            $scheme = strtolower($matches[1]);
            if (!in_array($scheme, ['http', 'https', 'mailto'])) {
                return false;
            }
        }
        return true;
    }
}

// Helper for tree node deletions
if (!function_exists('delete_node_recursive')) {
    function delete_node_recursive(&$list, $targetId) {
        $filtered = [];
        $deleted = false;
        foreach ($list as &$b) {
            if (($b['id'] ?? '') === $targetId) {
                if (\Qwiki\Core\Config::isCategoryProtected($targetId, [$b])) {
                    $filtered[] = $b;
                    continue;
                }
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
}

// Helper for chapter insertion into tree
if (!function_exists('insert_chapter_into_node')) {
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
}

// Helper for chapter update in tree
if (!function_exists('update_chapter_in_node')) {
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
                    if (isset($updatedData['publicShareable'])) {
                        $ch['publicShareable'] = (bool)$updatedData['publicShareable'];
                    }
                    if (isset($updatedData['shareKey']) && $updatedData['shareKey'] !== '') {
                        $ch['shareKey'] = $updatedData['shareKey'];
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
}

// Helper for chapter deletion from tree
if (!function_exists('delete_chapter_from_node')) {
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
}

// Safely moves/renames a document file within content/ directory
if (!function_exists('relocate_document_file')) {
    function relocate_document_file($baseDir, $origRelFile, $destRelDir, $destFileName = null) {
        if (empty($origRelFile) || strpos($origRelFile, 'content/') !== 0) {
            return $origRelFile;
        }
        $destRelDir = trim($destRelDir, '/\\');
        $destAbsDir = $baseDir . '/' . $destRelDir;
        if (!is_dir($destAbsDir)) {
            if (!@mkdir($destAbsDir, 0755, true) && !is_dir($destAbsDir)) {
                return $origRelFile;
            }
        }

        $origFileName = basename($origRelFile);
        $targetName = $destFileName ?: $origFileName;
        $ext = pathinfo($targetName, PATHINFO_EXTENSION);
        $nameWithoutExt = pathinfo($targetName, PATHINFO_FILENAME);

        $origAbsFile = $baseDir . '/' . $origRelFile;
        $destAbsFile = $destAbsDir . '/' . $targetName;

        // Check if target already exists and is a different file
        if (file_exists($destAbsFile) && realpath($destAbsFile) !== realpath($origAbsFile)) {
            $counter = 1;
            while (file_exists($destAbsDir . '/' . $nameWithoutExt . '-' . $counter . ($ext ? '.' . $ext : ''))) {
                $counter++;
            }
            $targetName = $nameWithoutExt . '-' . $counter . ($ext ? '.' . $ext : '');
            $destAbsFile = $destAbsDir . '/' . $targetName;
        }

        $newRelFile = $destRelDir . '/' . $targetName;

        if (file_exists($origAbsFile) && $destAbsFile !== $origAbsFile) {
            if (@rename($origAbsFile, $destAbsFile)) {
                return $newRelFile;
            }
        } elseif (!file_exists($origAbsFile)) {
            return $newRelFile;
        }

        return $newRelFile;
    }
}

// Checks if a slug is already taken across all books and documents
if (!function_exists('is_slug_taken')) {
    function is_slug_taken($slug, $nodes, $currentSlug = null) {
        if (empty($slug) || !is_array($nodes)) return false;
        foreach ($nodes as $node) {
            if (isset($node['id']) && $node['id'] === $slug) {
                return true;
            }
            if (isset($node['slug']) && $node['slug'] === $slug) {
                if ($currentSlug === null || $currentSlug !== $slug) {
                    return true;
                }
            }
            if (!empty($node['items']) && is_array($node['items'])) {
                if (is_slug_taken($slug, $node['items'], $currentSlug)) {
                    return true;
                }
            }
        }
        return false;
    }
}

// Finds a chapter and its parent node by slug (read-only)
if (!function_exists('find_chapter_and_parent')) {
    function find_chapter_and_parent($nodes, $slug, &$foundChapter, &$foundParentNode, &$topBookId, $parentNode = null, $currentTopBookId = null) {
        if (!is_array($nodes)) return false;
        foreach ($nodes as $node) {
            $effectiveTopBook = $currentTopBookId ?? ($node['id'] ?? null);
            if (!isset($node['type']) || $node['type'] !== 'folder') {
                if (($node['slug'] ?? '') === $slug) {
                    $foundChapter = $node;
                    $foundParentNode = $parentNode;
                    $topBookId = $effectiveTopBook ?: $slug;
                    return true;
                }
            }
            if (!empty($node['items']) && is_array($node['items'])) {
                if (find_chapter_and_parent($node['items'], $slug, $foundChapter, $foundParentNode, $topBookId, $node, $effectiveTopBook)) {
                    return true;
                }
            }
        }
        return false;
    }
}

// Finds a chapter and executes a mutating callback in-place
if (!function_exists('find_chapter_and_update')) {
    function find_chapter_and_update(&$nodes, $slug, $callback, &$parentNode = null, $currentTopBookId = null) {
        if (!is_array($nodes)) return false;
        foreach ($nodes as &$node) {
            $effectiveTopBook = $currentTopBookId ?? ($node['id'] ?? null);
            if (!isset($node['type']) || $node['type'] !== 'folder') {
                if (($node['slug'] ?? '') === $slug) {
                    return $callback($node, $parentNode, $effectiveTopBook);
                }
            }
            if (!empty($node['items']) && is_array($node['items'])) {
                $res = find_chapter_and_update($node['items'], $slug, $callback, $node, $effectiveTopBook);
                if ($res !== false) {
                    return $res;
                }
            }
        }
        return false;
    }
}

// Resolves category folder path based on hierarchy
if (!function_exists('get_category_folder')) {
    function get_category_folder($nodes, $targetId, $parentFolder = null) {
        if (!is_array($nodes)) return null;
        foreach ($nodes as $node) {
            $nodeId = $node['id'] ?? null;
            if ($nodeId === null) continue;
            $curFolder = $node['folder'] ?? (!empty($parentFolder) ? $parentFolder . '/' . $nodeId : 'content/' . $nodeId);
            if ($nodeId === $targetId) {
                return $curFolder;
            }
            if (!empty($node['items']) && is_array($node['items'])) {
                $found = get_category_folder($node['items'], $targetId, $curFolder);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }
}

// Backward compatibility helper
if (!function_exists('is_chapter_protected')) {
    function is_chapter_protected($slug_or_file, $nodes = null) {
        return \Qwiki\Core\Config::isChapterProtected($slug_or_file, $nodes);
    }
}
if (!function_exists('is_category_protected')) {
    function is_category_protected($category_id, $nodes = null) {
        return \Qwiki\Core\Config::isCategoryProtected($category_id, $nodes);
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
        $parentId = trim($_POST['parentId'] ?? '');
        if (empty($title) || empty($bookId)) {
            echo json_encode(['success' => false, 'error' => 'Category title is required']);
            exit;
        }
        $val = SubwikiManager::validateSlug($bookId, null, false);
        if (!$val['valid']) {
            echo json_encode(['success' => false, 'error' => $val['error']]);
            exit;
        }
        if (is_slug_taken($bookId, $config['books'] ?? [])) {
            echo json_encode(['success' => false, 'error' => "A document or category with the slug '{$bookId}' already exists"]);
            exit;
        }

        if (!empty($parentId)) {
            $parentFolder = get_category_folder($config['books'] ?? [], $parentId);
            if ($parentFolder === null) {
                echo json_encode(['success' => false, 'error' => 'Selected parent category does not exist']);
                exit;
            }
            $targetRelFolder = $parentFolder . '/' . $bookId;
            $targetAbsFolder = $baseDir . '/' . $targetRelFolder;
            if (!is_dir($targetAbsFolder)) {
                @mkdir($targetAbsFolder, 0755, true);
            }
            $newNode = [
                'id' => $bookId,
                'title' => $title,
                'type' => 'folder',
                'folder' => $targetRelFolder,
                'items' => []
            ];
            $inserted = false;
            foreach ($config['books'] as &$book) {
                if (insert_chapter_into_node($book, $parentId, $newNode)) {
                    $inserted = true;
                    break;
                }
            }
            if (!$inserted) {
                echo json_encode(['success' => false, 'error' => 'Failed to nest category in parent']);
                exit;
            }
        } else {
            $targetRelFolder = 'content/' . $bookId;
            $targetAbsFolder = $baseDir . '/' . $targetRelFolder;
            if (!is_dir($targetAbsFolder)) {
                @mkdir($targetAbsFolder, 0755, true);
            }
            $config['books'][] = [
                'id' => $bookId,
                'title' => $title,
                'type' => 'folder',
                'folder' => $targetRelFolder,
                'items' => []
            ];
        }

        if (Config::save($config)) {
            echo json_encode(['success' => true, 'bookId' => $bookId, 'parentId' => $parentId]);
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

        $readOnly = isset($_POST['readOnly']) ? ($_POST['readOnly'] === '1' || $_POST['readOnly'] === 'true') : null;

        // In demo mode, prevent unlocking directly protected demo categories
        if (Config::isDemoMode() && Config::isCategoryDirectlyProtected($bookId, $config['books'] ?? []) && $readOnly === false) {
            echo json_encode(['success' => false, 'error' => 'Protected demo categories cannot be unlocked in demo mode.']);
            exit;
        }

        $updated = false;
        foreach ($config['books'] as &$book) {
            if (update_node_meta($book, $bookId, $title, $theme, $visibility, $readOnly)) {
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
        if (Config::isCategoryProtected($bookId, $config['books'] ?? [])) {
            echo json_encode(['success' => false, 'error' => 'This category is protected or contains protected documents and cannot be deleted.']);
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
        $targetRelDir = get_category_folder($config['books'], $bookId) ?: ('content/' . $bookId);
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
        $slug = trim($_POST['slug'] ?? '');
        if (empty($slug)) {
            echo json_encode(['success' => false, 'error' => 'Document Slug is required']);
            exit;
        }
        if (Config::isChapterProtected($slug, $config['books'] ?? [])) {
            echo json_encode(['success' => false, 'error' => 'This document is protected and cannot be modified.']);
            exit;
        }

        $title = trim($_POST['title'] ?? '');
        $type = $_POST['type'] ?? 'markdown';
        $url = trim($_POST['url'] ?? '');
        $editUrl = trim($_POST['editUrl'] ?? '');
        $theme = trim($_POST['theme'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $image = trim($_POST['image'] ?? '');
        $targetBookId = trim($_POST['targetBookId'] ?? '');

        // Slug change validation
        $rawNewSlug = trim($_POST['newSlug'] ?? $slug);
        $newSlug = Config::makeSlug($rawNewSlug);
        if (empty($newSlug)) {
            echo json_encode(['success' => false, 'error' => 'Document Slug cannot be empty']);
            exit;
        }
        if (in_array($newSlug, Config::getReservedNames(), true)) {
            echo json_encode(['success' => false, 'error' => "The slug '{$newSlug}' is a reserved system name"]);
            exit;
        }
        if ($newSlug !== $slug && is_slug_taken($newSlug, $config['books'], $slug)) {
            echo json_encode(['success' => false, 'error' => "A document or category with the slug '{$newSlug}' already exists"]);
            exit;
        }

        if (empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Document Title is required']);
            exit;
        }
        if (!empty($url) && !is_safe_url($url)) {
            echo json_encode(['success' => false, 'error' => 'Invalid or unsafe URL protocol']);
            exit;
        }
        if ($type === 'gdoc' && !empty($url)) {
            if (strpos($url, 'embedded=true') === false) {
                $url .= (strpos($url, '?') !== false) ? '&embedded=true' : '?embedded=true';
            }
        }
        $publicShareable = isset($_POST['publicShareable']) ? ($_POST['publicShareable'] === '1' || $_POST['publicShareable'] === 'true') : true;

        $updateResult = find_chapter_and_update($config['books'], $slug, function(&$foundChapter, &$foundParentNode, $topBookId) use (
            $title, $type, $url, $editUrl, $publicShareable, $theme, $description, $image, $newSlug, $slug,
            $targetBookId, $baseDir, &$config
        ) {
            $currentParentId = $foundParentNode['id'] ?? $topBookId;
            $destBookId = (!empty($targetBookId)) ? $targetBookId : $currentParentId;
            $destCategoryFolder = get_category_folder($config['books'], $destBookId) ?: ('content/' . $destBookId);

            $foundChapter['title'] = $title;
            $foundChapter['type'] = $type;
            $foundChapter['url'] = $url;
            $foundChapter['editUrl'] = $editUrl;
            $foundChapter['publicShareable'] = $publicShareable;

            if ($theme !== '') {
                $foundChapter['theme'] = $theme;
            } else {
                unset($foundChapter['theme']);
            }
            if ($description !== '') {
                $foundChapter['description'] = $description;
            } else {
                unset($foundChapter['description']);
            }
            if ($image !== '') {
                $foundChapter['image'] = $image;
            } else {
                unset($foundChapter['image']);
            }
            if (!empty($_POST['regenerateShareKey'])) {
                $foundChapter['shareKey'] = Navigation::generateShareKey();
            }

            // Handle physical file relocation & slug renaming on disk
            $origRelFile = $foundChapter['file'] ?? '';
            if (!empty($origRelFile) && strpos($origRelFile, 'content/') === 0) {
                $ext = pathinfo($origRelFile, PATHINFO_EXTENSION);
                $newFileName = ($newSlug !== $slug) ? ($newSlug . ($ext ? '.' . $ext : '')) : basename($origRelFile);
                $newRelFile = relocate_document_file($baseDir, $origRelFile, $destCategoryFolder, $newFileName);
                $foundChapter['file'] = $newRelFile;
            }
            $foundChapter['slug'] = $newSlug;

            $finalBookId = $topBookId;

            // Move to target category in tree if category changed
            if (!empty($targetBookId) && $foundParentNode && ($foundParentNode['id'] ?? '') !== $targetBookId) {
                $chapterCopy = $foundChapter;
                // Remove from current parent
                $filteredItems = [];
                foreach ($foundParentNode['items'] as $item) {
                    if (($item['slug'] ?? '') !== $slug && ($item['slug'] ?? '') !== $newSlug) {
                        $filteredItems[] = $item;
                    }
                }
                $foundParentNode['items'] = $filteredItems;

                // Insert into target category
                $inserted = false;
                foreach ($config['books'] as &$book) {
                    if (insert_chapter_into_node($book, $targetBookId, $chapterCopy)) {
                        $inserted = true;
                        break;
                    }
                }
                if ($inserted) {
                    $finalBookId = $targetBookId;
                }
            }

            return [
                'bookId' => $finalBookId ?: $destBookId,
                'slug' => $newSlug,
                'file' => $foundChapter['file'] ?? ''
            ];
        });

        if (!$updateResult) {
            echo json_encode(['success' => false, 'error' => 'Document entry not found']);
            exit;
        }

        if (Config::save($config)) {
            echo json_encode([
                'success' => true,
                'bookId' => $updateResult['bookId'],
                'slug' => $updateResult['slug'],
                'file' => $updateResult['file']
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save qwiki.json']);
        }
        break;

    case 'get_or_create_share_key':
        $slug = trim($_REQUEST['slug'] ?? '');
        if (empty($slug)) {
            echo json_encode(['success' => false, 'error' => 'Document slug is required']);
            exit;
        }
        $chapter = Navigation::findChapterBySlug($config['books'], $slug);
        if (!$chapter) {
            echo json_encode(['success' => false, 'error' => 'Document not found']);
            exit;
        }
        $shareKey = $chapter['shareKey'] ?? '';
        $isPublic = !isset($chapter['publicShareable']) || !empty($chapter['publicShareable']);
        if (empty($shareKey)) {
            if (!Auth::isViewer() && !Auth::isAdmin()) {
                echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in to share documents.']);
                exit;
            }
            $shareKey = Navigation::generateShareKey();
            $updatedData = ['shareKey' => $shareKey];
            foreach ($config['books'] as &$book) {
                if (update_chapter_in_node($book, $slug, $updatedData)) {
                    Config::save($config);
                    break;
                }
            }
        } elseif (!$isPublic && !Auth::isViewer() && !Auth::isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized. Public sharing is disabled for this document.']);
            exit;
        }
        $baseUrl = Config::getBaseUrl();
        $shareUrl = $baseUrl . '?share=' . urlencode($shareKey);
        echo json_encode([
            'success' => true,
            'slug' => $slug,
            'title' => $chapter['title'] ?? '',
            'shareKey' => $shareKey,
            'publicShareable' => $isPublic,
            'shareUrl' => $shareUrl,
            'isAdmin' => Auth::isAdmin()
        ]);
        break;

    case 'update_share_settings':
        if (!Auth::isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        $slug = trim($_POST['slug'] ?? '');
        if (empty($slug)) {
            echo json_encode(['success' => false, 'error' => 'Document slug is required']);
            exit;
        }
        $chapter = Navigation::findChapterBySlug($config['books'], $slug);
        if (!$chapter) {
            echo json_encode(['success' => false, 'error' => 'Document not found']);
            exit;
        }
        $publicShareable = isset($_POST['publicShareable']) ? ($_POST['publicShareable'] === '1' || $_POST['publicShareable'] === 'true') : true;
        $regenerate = !empty($_POST['regenerate']);
        $shareKey = $chapter['shareKey'] ?? '';
        if ($regenerate || empty($shareKey)) {
            $shareKey = Navigation::generateShareKey();
        }
        $updatedData = [
            'publicShareable' => $publicShareable,
            'shareKey' => $shareKey
        ];
        $saved = false;
        foreach ($config['books'] as &$book) {
            if (update_chapter_in_node($book, $slug, $updatedData)) {
                $saved = Config::save($config);
                break;
            }
        }
        if ($saved) {
            $baseUrl = Config::getBaseUrl();
            echo json_encode([
                'success' => true,
                'slug' => $slug,
                'shareKey' => $shareKey,
                'publicShareable' => $publicShareable,
                'shareUrl' => $baseUrl . '?share=' . urlencode($shareKey)
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save share settings']);
        }
        break;


    case 'lock_status':
        $file = $_REQUEST['file'] ?? '';
        $tabId = $_REQUEST['tab_id'] ?? null;
        $status = LockManager::checkLock($file, $tabId);
        echo json_encode(['success' => true, 'status' => $status]);
        break;

    case 'lock_acquire':
        if (!Auth::isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        $file = $_POST['file'] ?? '';
        $tabId = $_POST['tab_id'] ?? '';
        $force = !empty($_POST['force']);
        $user = Auth::getCurrentUser();
        $username = $user['username'] ?? 'admin';
        $res = LockManager::acquireLock($file, $tabId, $username, $force);
        echo json_encode($res);
        break;

    case 'lock_heartbeat':
        if (!Auth::isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        $user = Auth::getCurrentUser();
        $username = $user['username'] ?? 'admin';
        // Immediately release session lock to prevent blocking concurrent browsing requests
        session_write_close();

        $file = $_POST['file'] ?? '';
        $tabId = $_POST['tab_id'] ?? '';
        $res = LockManager::renewLock($file, $tabId, $username);
        echo json_encode($res);
        break;

    case 'lock_release':
        if (!Auth::isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        $file = $_POST['file'] ?? '';
        $tabId = $_POST['tab_id'] ?? '';
        $user = Auth::getCurrentUser();
        $username = $user['username'] ?? 'admin';
        $force = !empty($_POST['force']);
        $res = LockManager::releaseLock($file, $tabId, $username, $force);
        echo json_encode($res);
        break;

    case 'save_markdown':
        if (!Auth::isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        $relFile = $_POST['file'] ?? '';
        if (Config::isChapterProtected($relFile, $config['books'] ?? [])) {
            echo json_encode(['success' => false, 'error' => 'This document is protected and cannot be edited.']);
            exit;
        }
        $content = $_POST['content'] ?? '';
        if (isset($_POST['content_base64'])) {
            $content = base64_decode($_POST['content_base64']);
        }
        $targetPath = Config::safePath($baseDir, $relFile);
        if (!$targetPath || !preg_match('/\.(md|markdown)$/i', $targetPath)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file path']);
            exit;
        }

        $tabId = $_POST['tab_id'] ?? '';
        $user = Auth::getCurrentUser();
        $username = $user['username'] ?? 'admin';
        if (!empty($tabId)) {
            $lockCheck = LockManager::verifyOrRejectSave($relFile, $tabId, $username);
            if (!$lockCheck['allowed']) {
                echo json_encode([
                    'success' => false,
                    'code' => 'LOCKED_BY_OTHER',
                    'lockedBy' => $lockCheck['lockedBy'] ?? 'another session',
                    'isSameUser' => !empty($lockCheck['isSameUser']),
                    'error' => 'Document save rejected: Document is currently locked by ' . ($lockCheck['lockedBy'] ?? 'another session')
                ]);
                exit;
            }
        }

        if (file_put_contents($targetPath, $content) !== false) {
            if (!empty($tabId)) {
                LockManager::releaseLock($relFile, $tabId, $username);
            }
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
        $targetRelDir = get_category_folder($config['books'], $bookId) ?: ('content/' . $bookId);
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

    case 'add_link':
        if (!Auth::isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        $bookId = $_POST['bookId'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if (empty($title) || empty($url)) {
            echo json_encode(['success' => false, 'error' => 'Title and URL are required']);
            exit;
        }
        if (!is_safe_url($url)) {
            echo json_encode(['success' => false, 'error' => 'Invalid or unsafe URL protocol']);
            exit;
        }
        $slug = Config::makeSlug($title);
        $baseSlug = $slug;
        $counter = 1;
        $allSlugs = [];
        $collectSlugs = function($nodes) use (&$collectSlugs, &$allSlugs) {
            if (!is_array($nodes)) return;
            foreach ($nodes as $n) {
                if (isset($n['slug'])) $allSlugs[] = $n['slug'];
                if (!empty($n['items']) && is_array($n['items'])) $collectSlugs($n['items']);
            }
        };
        $collectSlugs($config['books'] ?? []);
        while (in_array($slug, $allSlugs)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        $linkData = [
            'title' => $title,
            'slug' => $slug,
            'type' => 'link',
            'url' => $url
        ];
        if (!empty($description)) {
            $linkData['description'] = $description;
        }

        if (empty($bookId)) {
            $config['books'][] = $linkData;
        } else {
            $added = false;
            foreach ($config['books'] as &$book) {
                if (insert_chapter_into_node($book, $bookId, $linkData)) {
                    $added = true;
                    break;
                }
            }
            if (!$added) {
                $config['books'][] = $linkData;
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
        if (empty($slug)) {
            echo json_encode(['success' => false, 'error' => 'Item slug is required']);
            exit;
        }
        if (Config::isChapterProtected($slug, $config['books'] ?? [])) {
            echo json_encode(['success' => false, 'error' => 'This document is protected and cannot be deleted.']);
            exit;
        }
        $filteredBooks = [];
        foreach ($config['books'] as &$book) {
            if (($book['slug'] ?? '') === $slug) {
                continue;
            }
            delete_chapter_from_node($book, $slug);
            $filteredBooks[] = $book;
        }
        $config['books'] = $filteredBooks;
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
        $showPoweredBy = isset($_POST['showPoweredBy']) && $_POST['showPoweredBy'] === '1';
        $showSubwikisInSidebar = isset($_POST['showSubwikisInSidebar']) && $_POST['showSubwikisInSidebar'] === '1';
        $shareDescription = trim($_POST['shareDescription'] ?? '');
        $shareImageUrl = trim($_POST['shareImageUrl'] ?? '');
        $feedItemCount = isset($_POST['feedItemCount']) ? (int)$_POST['feedItemCount'] : 10;
        $feedAccessToken = trim($_POST['feedAccessToken'] ?? '');

        if (isset($_POST['title'])) $config['title'] = $title;
        if (isset($_POST['logoText'])) $config['logoText'] = $logoText;
        if (isset($_POST['logoUrl'])) $config['logoUrl'] = $logoUrl;
        if (isset($_POST['theme'])) $config['theme'] = $theme;
        $config['showDocTypesOnlyToAdmin'] = $showDocTypesOnlyToAdmin;
        $config['showPoweredBy'] = $showPoweredBy;
        $config['showSubwikisInSidebar'] = $showSubwikisInSidebar;
        if (isset($config['hideDocTypesFromPublic'])) {
            unset($config['hideDocTypesFromPublic']);
        }
        if (isset($_POST['defaultBook'])) $config['defaultBook'] = $defaultBook;
        $config['requireLoginToView'] = $requireLoginToView;
        if (isset($_POST['shareDescription'])) $config['shareDescription'] = $shareDescription;
        if (isset($_POST['shareImageUrl'])) $config['shareImageUrl'] = $shareImageUrl;
        $config['feedItemCount'] = $feedItemCount;
        $config['feedAccessToken'] = $feedAccessToken;

        if (Config::isSubwiki()) {
            if (isset($_POST['parentTitle'])) {
                $config['parentTitle'] = trim($_POST['parentTitle']);
                $config['parentTitleCustom'] = true;
            }
            if (isset($_POST['parentUrl'])) {
                $config['parentUrl'] = trim($_POST['parentUrl']);
            }
        }

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
                    } elseif (isset($node['slug'])) {
                        $existingDocuments[$node['slug']] = $node;
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

            $updatedFiles = [];

            $mergeTree = function ($nodes, $parentFolder = null) use (&$mergeTree, &$existingCategories, &$existingDocuments, &$updatedFiles, $baseDir) {
                if (!is_array($nodes)) return [];
                $merged = [];
                foreach ($nodes as $node) {
                    if (!is_array($node)) continue;
                    $nodeId = $node['id'] ?? null;
                    $nodeSlug = $node['slug'] ?? null;

                    $currentFolder = $parentFolder;

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
                        foreach (['readOnly', 'editable', 'locked'] as $field) {
                            if (!isset($node[$field]) && isset($orig[$field])) {
                                $mergedNode[$field] = $orig[$field];
                            }
                        }
                        $categoryFolder = $mergedNode['folder'] ?? (!empty($parentFolder) ? $parentFolder . '/' . $nodeId : 'content/' . $nodeId);
                        $mergedNode['folder'] = $categoryFolder;
                        $currentFolder = $categoryFolder;
                    } elseif ($nodeId !== null) {
                        $mergedNode = $node;
                        $categoryFolder = $node['folder'] ?? (!empty($parentFolder) ? $parentFolder . '/' . $nodeId : 'content/' . $nodeId);
                        $mergedNode['folder'] = $categoryFolder;
                        $currentFolder = $categoryFolder;
                    } elseif ($nodeSlug !== null && isset($existingDocuments[$nodeSlug])) {
                        $origDoc = $existingDocuments[$nodeSlug];
                        $mergedNode = array_merge($origDoc, $node);
                        foreach (['theme', 'description', 'image', 'file', 'url', 'editUrl', 'readOnly', 'editable'] as $field) {
                            if (empty($node[$field]) && isset($origDoc[$field])) {
                                $mergedNode[$field] = $origDoc[$field];
                            }
                        }
                    } else {
                        $mergedNode = $node;
                    }

                    if (isset($node['items']) && is_array($node['items'])) {
                        $mergedItems = [];
                        foreach ($node['items'] as $item) {
                            if (!is_array($item)) continue;
                            if (isset($item['type']) && $item['type'] === 'folder') {
                                $mergedSub = $mergeTree([$item], $currentFolder);
                                if (!empty($mergedSub)) {
                                    $mergedItems[] = $mergedSub[0];
                                }
                            } elseif (isset($item['slug'])) {
                                $slug = $item['slug'];
                                if (isset($existingDocuments[$slug])) {
                                    $origDoc = $existingDocuments[$slug];
                                    $mergedDoc = array_merge($origDoc, $item);
                                    foreach (['theme', 'description', 'image', 'file', 'url', 'editUrl', 'readOnly', 'editable'] as $field) {
                                        if (empty($item[$field]) && isset($origDoc[$field])) {
                                            $mergedDoc[$field] = $origDoc[$field];
                                        }
                                    }

                                    // Relocate file if moved to a different category folder
                                    if (!empty($currentFolder) && !empty($mergedDoc['file']) && strpos($mergedDoc['file'], 'content/') === 0) {
                                        $origRelFile = $mergedDoc['file'];
                                        $origRelDir = dirname($origRelFile);
                                        if ($origRelDir !== $currentFolder) {
                                            $newRelFile = relocate_document_file($baseDir, $origRelFile, $currentFolder);
                                            if ($newRelFile && $newRelFile !== $origRelFile) {
                                                $mergedDoc['file'] = $newRelFile;
                                                $updatedFiles[$slug] = $newRelFile;
                                            }
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

            $newBooks = $mergeTree($tree);

            // Safety guard: ensure no protected documents or protected categories are lost during reorder
            foreach ($existingDocuments as $slug => $origDoc) {
                if (Config::isChapterProtected($slug, $config['books'] ?? [])) {
                    if (!Config::isChapterProtected($slug, $newBooks)) {
                        echo json_encode(['success' => false, 'error' => 'Cannot save tree: protected document "' . $slug . '" would be lost.']);
                        exit;
                    }
                }
            }
            foreach ($existingCategories as $catId => $origCat) {
                if (Config::isCategoryProtected($catId, $config['books'] ?? [])) {
                    if (!Config::isCategoryProtected($catId, $newBooks)) {
                        echo json_encode(['success' => false, 'error' => 'Cannot save tree: protected category "' . $catId . '" would be lost.']);
                        exit;
                    }
                }
            }

            $config['books'] = $newBooks;
            if (Config::save($config)) {
                echo json_encode(['success' => true, 'updatedFiles' => $updatedFiles]);
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
        if (Config::isDemoMode()) {
            echo json_encode(['success' => true, 'has_update' => false]);
            exit;
        }
        if (Config::isSubwiki()) {
            echo json_encode([
                'success' => true,
                'has_update' => false,
                'managed_by_parent' => true,
                'message' => 'Updates are managed automatically by the parent Qwiki.'
            ]);
            exit;
        }
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
        if (Config::isDemoMode()) {
            echo json_encode(['success' => false, 'error' => 'In-app updates are disabled in demo mode.']);
            exit;
        }
        if (Config::isSubwiki()) {
            echo json_encode(['success' => false, 'error' => 'Subwikis are updated automatically from the parent Qwiki.']);
            exit;
        }
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
            $excludes = ['content/', 'uploads/', 'qwiki.json', 'users.json', 'wikis/'];
            $existingSubwikis = SubwikiManager::listSubwikis();
            foreach ($existingSubwikis as $sub) {
                if (!empty($sub['slug'])) {
                    $excludes[] = $sub['slug'] . '/';
                }
            }
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

            // 1. Cascade updates to all child subwikis
            $cascadeResult = SubwikiManager::cascadeUpdates($baseDir);

            // 2. Run migration routine for legacy subwikis or deep folders
            $migrationResult = SubwikiManager::runMigration();

            echo json_encode([
                'success' => true,
                'subwikisUpdated' => $cascadeResult['updatedCount'] ?? 0,
                'migration' => $migrationResult['migrated'] ?? []
            ]);
        } else {
            @unlink($tempZip);
            echo json_encode(['success' => false, 'error' => 'Failed to extract update zip']);
        }
        break;

    case 'check_subwiki_slug':
        if (!Auth::isAdmin()) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
        $slug = Config::makeSlug($_GET['slug'] ?? $_POST['slug'] ?? '');
        $isForSubwiki = !empty($_REQUEST['for_subwiki']);
        $val = SubwikiManager::validateSlug($slug, null, $isForSubwiki);
        echo json_encode([
            'success' => true,
            'slug' => $slug,
            'valid' => $val['valid'],
            'error' => $val['error']
        ]);
        break;

    case 'list_subwikis':
        if (!Auth::isAdmin()) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
        $subwikis = SubwikiManager::listSubwikis();
        echo json_encode([
            'success' => true,
            'subwikis' => $subwikis,
            'isSubwiki' => Config::isSubwiki()
        ]);
        break;

    case 'deploy_subwiki':
        if (!Auth::isAdmin()) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
        if (Config::isSubwiki()) {
            echo json_encode(['success' => false, 'error' => 'Subwikis cannot deploy further subwikis. Maximum depth reached.']);
            exit;
        }
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $adminUser = trim($_POST['adminUser'] ?? 'admin');
        $adminPass = trim($_POST['adminPass'] ?? '');
        $includeDemo = !empty($_POST['includeDemo']);
        $res = SubwikiManager::deploySubwiki($slug, $title, $adminUser, $adminPass, $includeDemo);
        echo json_encode($res);
        break;

    case 'delete_subwiki':
        if (!Auth::isAdmin()) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
        $slug = trim($_POST['slug'] ?? '');
        $deleteFiles = !empty($_POST['deleteFiles']);
        $res = SubwikiManager::deleteSubwiki($slug, $deleteFiles);
        echo json_encode($res);
        break;

    case 'reload_demo':
        if (!Auth::isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        if (!Config::isDemoMode() && !\Qwiki\Core\DemoManager::isDemoConfigured()) {
            echo json_encode(['success' => false, 'error' => 'Demo mode is not configured']);
            exit;
        }
        require_once $baseDir . '/lib/Core/DemoManager.php';
        $reloadResult = \Qwiki\Core\DemoManager::reload();
        echo json_encode($reloadResult);
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
