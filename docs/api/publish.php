<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';
require_once __DIR__ . '/../lib/Core/LlmAccess.php';
require_once __DIR__ . '/../lib/Core/DocumentService.php';
require_once __DIR__ . '/../lib/Core/TrashManager.php';
require_once __DIR__ . '/../lib/Core/LockManager.php';
require_once __DIR__ . '/../lib/Core/AuditLogger.php';

use Qwiki\Core\Config;
use Qwiki\Core\Navigation;
use Qwiki\Core\LlmAccess;
use Qwiki\Core\DocumentService;
use Qwiki\Core\TrashManager;
use Qwiki\Core\LockManager;
use Qwiki\Core\AuditLogger;

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
$providedKey = (string)($_SERVER['HTTP_X_API_KEY'] ?? $_POST['api_key'] ?? $_POST['apiKey'] ?? $_POST['key'] ?? $_GET['apiKey'] ?? $_GET['key'] ?? '');
if (empty($providedKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

$isAuthorized = false;
$llmKeyRecord = null;
if (!empty($publishApiKey) && hash_equals((string)$publishApiKey, $providedKey)) {
    $isAuthorized = true;
} elseif (!empty($llmKeys)) {
    $llmVal = LlmAccess::validateKey($providedKey);
    if (!empty($llmVal['valid'])) {
        $isAuthorized = true;
        $llmKeyRecord = $llmVal['key'] ?? null;
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

// Helper functions for category and chapter resolution (delegating to Navigation)
function find_target_folder(&$node, $targetId) {
    return Navigation::findTargetFolder($node, $targetId);
}

function upsert_chapter_into_node(&$node, $targetFolderId, $chapterData, &$isUpdated = false) {
    return Navigation::upsertChapterIntoNode($node, $targetFolderId, $chapterData, $isUpdated);
}

function insert_chapter_into_node(&$node, $targetFolderId, $chapterData) {
    return Navigation::insertChapterIntoNode($node, $targetFolderId, $chapterData);
}

function validate_theme_filename($rawTheme) {
    $themeFile = basename(trim((string)$rawTheme));
    if ($themeFile === '') {
        return '';
    }
    if (!preg_match('/^theme-[a-zA-Z0-9-]+\.css$/', $themeFile)) {
        return false;
    }
    return $themeFile;
}

// 2. Dispatch Action
$action = trim($_POST['action'] ?? $_GET['action'] ?? 'publish_doc');

if ($action === 'upload_image' || $action === 'upload_asset' || $action === 'upload_media') {
    $fileKey = isset($_FILES['image']) ? 'image' : (isset($_FILES['file']) ? 'file' : (isset($_FILES['asset']) ? 'asset' : null));
    if (!$fileKey || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No image or media file uploaded or upload error occurred']);
        exit;
    }
    $fileName = basename($_FILES[$fileKey]['name']);
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $imageExts = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];
    $videoExts = ['mp4', 'webm', 'ogg', 'ogv', 'mov'];
    $allowedExts = array_merge($imageExts, $videoExts);
    if (!in_array($ext, $allowedExts, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid image format or unsupported media type. Allowed formats: ' . implode(', ', $allowedExts)]);
        exit;
    }
    $baseDir = Config::getBaseDir();
    $subDir = in_array($ext, $videoExts, true) ? 'uploads/videos/' : 'uploads/images/';
    $uploadDir = $baseDir . '/' . $subDir;
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    $safeName = preg_replace('/[^a-z0-9\._-]/', '-', strtolower(pathinfo($fileName, PATHINFO_FILENAME)));
    $timestamp = time();
    $newFileName = $timestamp . '-' . $safeName . '.' . $ext;
    $targetPath = $uploadDir . $newFileName;
    $relUrl = $subDir . $newFileName;

    $fullUrl = rtrim(Config::getBaseUrl(), '/') . '/' . $relUrl;

    if (@move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetPath) || @copy($_FILES[$fileKey]['tmp_name'], $targetPath)) {
        echo json_encode([
            'success' => true,
            'url' => $relUrl,
            'fullUrl' => $fullUrl,
            'filename' => $newFileName,
            'alt' => pathinfo($fileName, PATHINFO_FILENAME)
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save uploaded asset to disk']);
    }
    exit;
}

if ($action === 'list_themes') {
    $baseDir = Config::getBaseDir();
    $themes = [];
    $coreDir = dirname(__DIR__) . '/assets/css/';
    $localUploadsDir = $baseDir . '/uploads/themes/';
    $localAssetsDir = $baseDir . '/assets/css/';

    foreach ([$coreDir, $localAssetsDir, $localUploadsDir] as $dir) {
        if (is_dir($dir)) {
            $files = scandir($dir);
            if (is_array($files)) {
                foreach ($files as $f) {
                    if (preg_match('/^theme-.*\.css$/', $f) && !in_array($f, $themes, true)) {
                        $themes[] = $f;
                    }
                }
            }
        }
    }

    sort($themes);
    echo json_encode([
        'success' => true,
        'siteTheme' => $config['theme'] ?? 'theme-default.css',
        'themes' => $themes
    ]);
    exit;
}

if ($action === 'get_theme') {
    $themeFile = validate_theme_filename($_POST['theme'] ?? $_GET['theme'] ?? '');
    if ($themeFile === '' || $themeFile === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid or missing theme filename (must match theme-*.css)']);
        exit;
    }

    $baseDir = Config::getBaseDir();
    $localUploadsPath = $baseDir . '/uploads/themes/' . $themeFile;
    $localAssetsPath = $baseDir . '/assets/css/' . $themeFile;
    $corePath = dirname(__DIR__) . '/assets/css/' . $themeFile;

    if (file_exists($localUploadsPath)) {
        echo json_encode(['success' => true, 'theme' => $themeFile, 'content' => file_get_contents($localUploadsPath), 'is_custom' => true]);
    } elseif (file_exists($localAssetsPath)) {
        echo json_encode(['success' => true, 'theme' => $themeFile, 'content' => file_get_contents($localAssetsPath), 'is_custom' => !defined('QWIKI_BASE_DIR')]);
    } elseif (file_exists($corePath)) {
        echo json_encode(['success' => true, 'theme' => $themeFile, 'content' => file_get_contents($corePath), 'is_custom' => false]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Theme not found']);
    }
    exit;
}

if ($action === 'save_theme' || $action === 'upload_theme') {
    if ($llmKeyRecord !== null && !empty($llmKeyRecord['category']) && $llmKeyRecord['category'] !== 'all') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Category-scoped API key cannot upload site themes']);
        exit;
    }

    $rawTheme = $_POST['theme'] ?? '';
    $content = $_POST['content'] ?? '';
    if (isset($_POST['content_base64'])) {
        $decoded = base64_decode((string)$_POST['content_base64'], true);
        if ($decoded !== false) {
            $content = $decoded;
        }
    }

    $fileKey = isset($_FILES['theme']) ? 'theme' : (isset($_FILES['file']) ? 'file' : (isset($_FILES['css']) ? 'css' : null));
    if ($fileKey && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
        if ($rawTheme === '') {
            $rawTheme = basename($_FILES[$fileKey]['name']);
        }
        $content = file_get_contents($_FILES[$fileKey]['tmp_name']);
    }

    $themeFile = validate_theme_filename($rawTheme);
    if ($themeFile === '' || $themeFile === false || trim((string)$content) === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid theme parameters: theme must match theme-*.css and CSS content must be non-empty']);
        exit;
    }

    if (strlen((string)$content) > 512 * 1024) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Theme CSS exceeds maximum allowed size (512 KB)']);
        exit;
    }

    $baseDir = Config::getBaseDir();
    $saveDir = $baseDir . '/uploads/themes';
    if (!defined('QWIKI_BASE_DIR') && is_dir($baseDir . '/assets/css')) {
        $saveDir = $baseDir . '/assets/css';
    }
    if (!is_dir($saveDir)) {
        @mkdir($saveDir, 0755, true);
    }

    $path = $saveDir . '/' . $themeFile;
    if (file_put_contents($path, $content) !== false) {
        echo json_encode([
            'success' => true,
            'theme' => $themeFile,
            'url' => Config::getThemeUrl($themeFile)
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save theme CSS']);
    }
    exit;
}

if ($action === 'set_site_theme') {
    if ($llmKeyRecord !== null && !empty($llmKeyRecord['category']) && $llmKeyRecord['category'] !== 'all') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Category-scoped API key cannot modify site theme']);
        exit;
    }

    $themeFile = validate_theme_filename($_POST['theme'] ?? '');
    if ($themeFile === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid theme filename (must match theme-*.css)']);
        exit;
    }
    if ($themeFile === '') {
        $themeFile = 'theme-default.css';
    }

    $config['theme'] = $themeFile;
    if (Config::save($config)) {
        echo json_encode(['success' => true, 'theme' => $themeFile]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update qwiki.json']);
    }
    exit;
}

if ($action === 'create_category' || $action === 'add_book' || $action === 'update_category' || $action === 'edit_category') {
    $catId = Config::makeSlug($_POST['id'] ?? $_POST['bookId'] ?? $_POST['title'] ?? '');
    $catTitle = trim($_POST['title'] ?? '');
    $catDesc = trim($_POST['description'] ?? '');
    $parentId = trim($_POST['parentId'] ?? '');
    $hasThemeParam = isset($_POST['theme']);
    $catTheme = $hasThemeParam ? validate_theme_filename($_POST['theme']) : '';
    if ($catTheme === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid category theme filename (must match theme-*.css)']);
        exit;
    }

    if (empty($catId) || (empty($catTitle) && !in_array($action, ['update_category', 'edit_category'], true))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Category title and ID are required']);
        exit;
    }

    if (Navigation::isSlugTaken($catId, $config['books'] ?? [])) {
        $updated = false;
        $resolvedTitle = $catTitle;
        $resolvedDesc = $catDesc;
        $resolvedTheme = '';
        $updateFn = function(&$nodes) use (&$updateFn, $catId, $catTitle, $catDesc, $hasThemeParam, $catTheme, &$updated, &$resolvedTitle, &$resolvedDesc, &$resolvedTheme) {
            foreach ($nodes as &$node) {
                if (($node['id'] ?? '') === $catId) {
                    if (!empty($catTitle)) $node['title'] = $catTitle;
                    if ($catDesc !== '') $node['description'] = $catDesc;
                    if ($hasThemeParam) {
                        if ($catTheme !== '') {
                            $node['theme'] = $catTheme;
                        } else {
                            unset($node['theme']);
                        }
                    }
                    $resolvedTitle = $node['title'] ?? $catTitle;
                    $resolvedDesc = $node['description'] ?? $catDesc;
                    $resolvedTheme = $node['theme'] ?? '';
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
            'title' => $resolvedTitle,
            'description' => $resolvedDesc,
            'theme' => $resolvedTheme,
            'message' => ($action === 'update_category' || $action === 'edit_category') ? 'Category updated' : 'Category already exists'
        ]);
        exit;
    }

    if (empty($catTitle)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Category not found']);
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
        if ($hasThemeParam && $catTheme !== '') {
            $newNode['theme'] = $catTheme;
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
        if ($hasThemeParam && $catTheme !== '') {
            $newNode['theme'] = $catTheme;
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
            'theme' => ($hasThemeParam && $catTheme !== '') ? $catTheme : '',
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
    $collectCats = function(array $nodes, ?string $parentId = null, ?string $parentFolder = null, int $depth = 0) use (&$collectCats, &$categories, $llmKeyRecord) {
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'folder' || !empty($node['items'])) {
                $catId = $node['id'] ?? '';
                if ($catId === '') continue;
                if ($llmKeyRecord !== null && !empty($llmKeyRecord['category']) && $llmKeyRecord['category'] !== 'all') {
                    // Only include if matches or is inside scoped category
                    if ($depth === 0 && strcasecmp((string)$llmKeyRecord['category'], (string)$catId) !== 0) {
                        if (!empty($node['items']) && is_array($node['items'])) {
                            $collectCats($node['items'], $catId, $node['folder'] ?? ('content/' . $catId), $depth + 1);
                        }
                        continue;
                    }
                }
                $folder = $node['folder'] ?? (!empty($parentFolder) ? ($parentFolder . '/' . $catId) : ('content/' . $catId));
                $categories[] = [
                    'id' => $catId,
                    'title' => $node['title'] ?? $catId,
                    'description' => $node['description'] ?? '',
                    'theme' => $node['theme'] ?? '',
                    'parent' => $parentId,
                    'depth' => $depth,
                    'folder' => $folder
                ];
                if (!empty($node['items']) && is_array($node['items'])) {
                    $collectCats($node['items'], $catId, $folder, $depth + 1);
                }
            }
        }
    };
    $collectCats($config['books'] ?? []);
    echo json_encode(['success' => true, 'categories' => $categories]);
    exit;
}

if ($action === 'delete_chapter' || $action === 'delete_doc') {
    $slug = trim($_POST['slug'] ?? $_GET['slug'] ?? '');
    $bookId = trim($_POST['bookId'] ?? $_GET['bookId'] ?? '');

    if (empty($slug)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'slug is required for chapter deletion']);
        exit;
    }

    if (Config::isChapterProtected($slug, $config['books'] ?? []) || Config::isChapterAncestorProtected($slug, $config['books'] ?? [])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'This document is protected and cannot be deleted.']);
        exit;
    }

    $permanent = !empty($_POST['permanent']) && ($_POST['permanent'] === '1' || $_POST['permanent'] === 'true');
    $parentBook = null;
    $chapterToDelete = Navigation::findChapterBySlug($config['books'] ?? [], $slug, $parentBook);
    if ($chapterToDelete && !empty($chapterToDelete['file'])) {
        LockManager::releaseLock($chapterToDelete['file'], '', 'api_publish', true);
    }
    $trashId = null;
    if ($chapterToDelete && !$permanent) {
        $manifest = TrashManager::trashChapter($chapterToDelete, $parentBook['id'] ?? $bookId, 'api_publish', 'api_delete');
        $trashId = $manifest['trashId'] ?? null;
    }

    $deleted = false;
    $deletedFile = null;

    $deleteChapterFromNode = function(&$node, $targetSlug) use (&$deleteChapterFromNode, &$deleted, &$deletedFile) {
        if ($deleted || empty($node['items']) || !is_array($node['items'])) return;
        $newItems = [];
        foreach ($node['items'] as &$item) {
            if (!$deleted && (!isset($item['type']) || $item['type'] !== 'folder') && ($item['slug'] ?? '') === $targetSlug) {
                $deleted = true;
                $deletedFile = $item['file'] ?? null;
                continue;
            }
            if (isset($item['type']) && $item['type'] === 'folder') {
                $deleteChapterFromNode($item, $targetSlug);
            }
            $newItems[] = $item;
        }
        $node['items'] = $newItems;
    };

    $filteredBooks = [];
    foreach ($config['books'] as &$book) {
        if (!$deleted && ($book['slug'] ?? '') === $slug) {
            $deleted = true;
            $deletedFile = $book['file'] ?? null;
            continue;
        }
        $deleteChapterFromNode($book, $slug);
        $filteredBooks[] = $book;
    }
    $config['books'] = $filteredBooks;

    if ($deleted) {
        Config::save($config);
        if ($permanent && !empty($deletedFile) && strpos($deletedFile, 'content/') === 0) {
            $baseDir = Config::getBaseDir();
            $filePath = Config::safePath($baseDir, $deletedFile);
            if ($filePath && file_exists($filePath) && is_file($filePath)) {
                @unlink($filePath);
            }
            if (($chapterToDelete['type'] ?? '') === 'form') {
                $subRel = preg_replace('/\.form\.json$/i', '.submissions.json', $deletedFile);
                $subAbs = ($subRel && $subRel !== $deletedFile) ? Config::safePath($baseDir, $subRel) : null;
                if ($subAbs && is_file($subAbs)) {
                    @unlink($subAbs);
                }
            }
        }
        if ($permanent && !empty($chapterToDelete['docId'])) {
            $revManagerPath = __DIR__ . '/../assets/extensions/tool-revisions/RevisionManager.php';
            if (is_file($revManagerPath)) {
                require_once $revManagerPath;
                if (class_exists('\Qwiki\Extensions\Revisions\RevisionManager')) {
                    \Qwiki\Extensions\Revisions\RevisionManager::purgeDocRevisions($chapterToDelete['docId']);
                }
            }
        }
        AuditLogger::logMutation($permanent ? 'doc_deleted' : 'doc_trashed', 200, [
            'slug' => $slug,
            'docId' => $chapterToDelete['docId'] ?? '',
            'file' => $deletedFile ?? '',
            'bookId' => $parentBook['id'] ?? $bookId,
            'trashId' => $trashId ?? '',
            'source' => 'api_publish',
        ], 'api_publish');
        echo json_encode([
            'success' => true,
            'deleted' => true,
            'trashed' => !$permanent,
            'trashId' => $trashId,
            'slug' => $slug,
            'file' => $deletedFile
        ]);
        exit;
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Chapter not found']);
        exit;
    }
}

if ($action === 'list_chapters' || $action === 'get_chapters' || $action === 'get_tree' || $action === 'sync_tree') {
    $targetBookId = trim($_POST['bookId'] ?? $_GET['bookId'] ?? '');
    $baseUrl = Config::getBaseUrl();
    $baseDir = Config::getBaseDir();
    $chapters = [];
    $categories = [];

    $collectTree = function($node, $catId, $parentId = null, $parentFolder = null, $depth = 0, $inScopedBranch = false) use (&$collectTree, &$chapters, &$categories, $targetBookId, $baseUrl, $baseDir, $llmKeyRecord) {
        $curCatId = $node['id'] ?? $catId;
        $isScopedKey = ($llmKeyRecord !== null && !empty($llmKeyRecord['category']) && $llmKeyRecord['category'] !== 'all');
        $nowInScoped = $inScopedBranch || !$isScopedKey || (strcasecmp((string)$llmKeyRecord['category'], (string)$curCatId) === 0);

        $curFolder = $node['folder'] ?? (!empty($parentFolder) ? ($parentFolder . '/' . $curCatId) : ('content/' . $curCatId));
        if ($nowInScoped && (empty($targetBookId) || $curCatId === $targetBookId)) {
            if (!empty($curCatId) && (($node['type'] ?? '') === 'folder' || !empty($node['items']))) {
                $categories[] = [
                    'id' => $curCatId,
                    'title' => $node['title'] ?? $curCatId,
                    'description' => $node['description'] ?? '',
                    'theme' => $node['theme'] ?? '',
                    'parent' => $parentId,
                    'depth' => $depth,
                    'folder' => $curFolder
                ];
            }
        }

        if (!empty($node['items']) && is_array($node['items'])) {
            foreach ($node['items'] as $item) {
                if (isset($item['type']) && $item['type'] === 'folder') {
                    $collectTree($item, $item['id'] ?? $curCatId, $curCatId, $curFolder, $depth + 1, $nowInScoped);
                } elseif (($item['type'] ?? '') === 'link') {
                    continue;
                } else {
                    if ($nowInScoped && (empty($targetBookId) || $curCatId === $targetBookId)) {
                        $sKey = $item['shareKey'] ?? '';
                        $slug = $item['slug'] ?? '';
                        $relFile = $item['file'] ?? '';
                        $absFile = !empty($relFile) ? Config::safePath($baseDir, $relFile) : null;
                        $existsOnDisk = ($absFile && is_file($absFile));
                        $sha256 = $existsOnDisk ? hash_file('sha256', $absFile) : '';
                        $mtime = $existsOnDisk ? (filemtime($absFile) ?: 0) : 0;
                        $sizeBytes = $existsOnDisk ? (filesize($absFile) ?: 0) : 0;
                        $docType = strtolower($item['type'] ?? (
                            preg_match('/\.form\.json$/i', $relFile) ? 'form' : (
                                preg_match('/\.(html|htm)$/i', $relFile) ? 'html' : 'markdown'
                            )
                        ));

                        $chapters[] = [
                            'docId' => $item['docId'] ?? '',
                            'bookId' => $curCatId,
                            'title' => $item['title'] ?? '',
                            'slug' => $slug,
                            'type' => $docType,
                            'status' => $item['status'] ?? Navigation::DEFAULT_STATUS,
                            'description' => $item['description'] ?? '',
                            'theme' => $item['theme'] ?? '',
                            'file' => $relFile,
                            'shareKey' => $sKey,
                            'publicShareable' => !isset($item['publicShareable']) ? true : (bool)$item['publicShareable'],
                            'sha256' => $sha256,
                            'mtime' => $mtime,
                            'dateModified' => $mtime ? date('c', $mtime) : '',
                            'sizeBytes' => $sizeBytes,
                            'shareUrl' => !empty($sKey) ? ($baseUrl . '?share=' . urlencode($sKey)) : '',
                            'url' => $baseUrl . urlencode($curCatId) . '/' . urlencode($slug)
                        ];
                    }
                }
            }
        }
    };

    foreach ($config['books'] ?? [] as $book) {
        $collectTree($book, $book['id'] ?? '', null, null, 0, false);
    }

    echo json_encode([
        'success' => true,
        'siteTitle' => $config['title'] ?? 'Standalone Qwiki',
        'siteTheme' => $config['theme'] ?? 'theme-default.css',
        'total' => count($chapters),
        'categories' => $categories,
        'chapters' => $chapters
    ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($action === 'resolve_share_key' || $action === 'get_chapter_by_share_key' || $action === 'find_by_share_key') {
    $shareKey = trim($_POST['shareKey'] ?? $_GET['shareKey'] ?? '');
    if (empty($shareKey)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'shareKey is required']);
        exit;
    }
    $matchedParent = null;
    $chapter = Navigation::findChapterByShareKey($config['books'] ?? [], $shareKey, $matchedParent);
    if ($chapter !== null) {
        $baseUrl = Config::getBaseUrl();
        $slug = $chapter['slug'] ?? '';
        $bookId = Navigation::findChapterParentId($config['books'] ?? [], $slug) ?? ($matchedParent['id'] ?? '');
        $fullUrl = $baseUrl . urlencode($bookId) . '/' . urlencode($slug);
        $shareUrl = $baseUrl . '?share=' . urlencode($shareKey);
        echo json_encode([
            'success' => true,
            'shareKey' => $shareKey,
            'bookId' => $bookId,
            'title' => $chapter['title'] ?? '',
            'slug' => $slug,
            'type' => $chapter['type'] ?? 'html',
            'file' => $chapter['file'] ?? '',
            'url' => $fullUrl,
            'shareUrl' => $shareUrl
        ]);
        exit;
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Chapter not found for share key']);
        exit;
    }
}

if ($action === 'get_doc' || $action === 'pull_doc' || $action === 'get_chapter') {
    $reqSlug = trim((string)($_POST['slug'] ?? $_GET['slug'] ?? ''));
    $reqShareKey = trim((string)($_POST['shareKey'] ?? $_GET['shareKey'] ?? ''));
    $reqDocId = trim((string)($_POST['docId'] ?? $_GET['docId'] ?? ''));
    $reqBookId = trim((string)($_POST['bookId'] ?? $_GET['bookId'] ?? ''));
    $reqFile = trim((string)($_POST['file'] ?? $_GET['file'] ?? ''));

    $rawIncComments = $_POST['include_comments'] ?? $_GET['include_comments'] ?? '1';
    $includeComments = !($rawIncComments === '0' || $rawIncComments === 'false' || $rawIncComments === false);
    $rawIncTalk = $_POST['include_talk'] ?? $_GET['include_talk'] ?? '1';
    $includeTalk = !($rawIncTalk === '0' || $rawIncTalk === 'false' || $rawIncTalk === false);

    if (empty($reqSlug) && empty($reqShareKey) && empty($reqDocId) && empty($reqFile)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'slug, shareKey, docId, or file is required']);
        exit;
    }

    $matchedChapter = null;
    $matchedParent = null;

    if (!empty($reqDocId)) {
        $matchedChapter = Navigation::findChapterByDocId($config['books'] ?? [], $reqDocId, $matchedParent);
    }
    if ($matchedChapter === null && !empty($reqShareKey)) {
        $matchedChapter = Navigation::findChapterByShareKey($config['books'] ?? [], $reqShareKey, $matchedParent);
    } elseif ($matchedChapter === null && !empty($reqSlug)) {
        if (!empty($reqBookId)) {
            $findInBook = function(array $nodes, string $targetBookId, string $targetSlug, $inTargetBook = false, $curParent = null) use (&$findInBook) {
                foreach ($nodes as $node) {
                    $isFolder = (isset($node['type']) && $node['type'] === 'folder') || !empty($node['items']);
                    if ($isFolder) {
                        $nowInBook = $inTargetBook || (($node['id'] ?? '') === $targetBookId);
                        $nextParent = (($node['id'] ?? '') === $targetBookId) ? $node : ($curParent ?: $node);
                        if (!empty($node['items']) && is_array($node['items'])) {
                            $res = $findInBook($node['items'], $targetBookId, $targetSlug, $nowInBook, $nextParent);
                            if ($res !== null) return $res;
                        }
                    } elseif ($inTargetBook && ($node['slug'] ?? '') === $targetSlug) {
                        return ['chapter' => $node, 'parent' => $curParent];
                    }
                }
                return null;
            };
            $scopedMatch = $findInBook($config['books'] ?? [], $reqBookId, $reqSlug);
            if ($scopedMatch !== null) {
                $matchedChapter = $scopedMatch['chapter'];
                $matchedParent = $scopedMatch['parent'];
            }
        }
        if ($matchedChapter === null) {
            $matchedChapter = Navigation::findChapterBySlug($config['books'] ?? [], $reqSlug, $matchedParent);
        }
    } elseif (!empty($reqFile)) {
        $findByFile = function(array $nodes, string $targetFile, $curParent = null) use (&$findByFile) {
            foreach ($nodes as $node) {
                $isFolder = (isset($node['type']) && $node['type'] === 'folder') || !empty($node['items']);
                if ($isFolder) {
                    if (!empty($node['items']) && is_array($node['items'])) {
                        $res = $findByFile($node['items'], $targetFile, $node);
                        if ($res !== null) return $res;
                    }
                } elseif (($node['file'] ?? '') === $targetFile) {
                    return ['chapter' => $node, 'parent' => $curParent];
                }
            }
            return null;
        };
        $fileMatch = $findByFile($config['books'] ?? [], $reqFile);
        if ($fileMatch !== null) {
            $matchedChapter = $fileMatch['chapter'];
            $matchedParent = $fileMatch['parent'];
        }
    }

    if ($matchedChapter === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Chapter not found']);
        exit;
    }

    $resolvedSlug = $matchedChapter['slug'] ?? '';
    $resolvedBookId = (!empty($reqBookId) && $matchedParent && ($matchedParent['id'] ?? '') === $reqBookId)
        ? $reqBookId
        : (Navigation::findChapterParentId($config['books'] ?? [], $resolvedSlug) ?? ($matchedParent['id'] ?? ''));

    if ($llmKeyRecord !== null && !empty($llmKeyRecord['category']) && $llmKeyRecord['category'] !== 'all') {
        if (strcasecmp((string)$llmKeyRecord['category'], (string)$resolvedBookId) !== 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'API key is not authorized for this category']);
            exit;
        }
    }

    $baseDir = Config::getBaseDir();
    $baseUrl = Config::getBaseUrl();
    $relFile = $matchedChapter['file'] ?? '';
    $absFile = !empty($relFile) ? Config::safePath($baseDir, $relFile) : null;

    if (!$absFile || !is_file($absFile)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Document source file not found on disk']);
        exit;
    }

    $rawContent = file_get_contents($absFile);
    $sha256 = hash_file('sha256', $absFile);
    $mtime = filemtime($absFile) ?: time();
    $sizeBytes = filesize($absFile) ?: 0;
    $docType = strtolower($matchedChapter['type'] ?? (
        preg_match('/\.form\.json$/i', $relFile) ? 'form' : (
            preg_match('/\.(html|htm)$/i', $relFile) ? 'html' : 'markdown'
        )
    ));

    $lockStatus = LockManager::checkLock($relFile);
    $isProtected = Config::isChapterProtected($resolvedSlug, $config['books'] ?? [])
        || Config::isChapterAncestorProtected($resolvedSlug, $config['books'] ?? []);

    $sKey = $matchedChapter['shareKey'] ?? '';
    $docPayload = [
        'docId' => $matchedChapter['docId'] ?? '',
        'bookId' => $resolvedBookId,
        'slug' => $resolvedSlug,
        'title' => $matchedChapter['title'] ?? '',
        'type' => $docType,
        'file' => $relFile,
        'status' => $matchedChapter['status'] ?? Navigation::DEFAULT_STATUS,
        'description' => $matchedChapter['description'] ?? '',
        'shareKey' => $sKey,
        'shareUrl' => !empty($sKey) ? ($baseUrl . '?share=' . urlencode($sKey)) : '',
        'url' => $baseUrl . urlencode($resolvedBookId) . '/' . urlencode($resolvedSlug),
        'publicShareable' => !isset($matchedChapter['publicShareable']) ? true : (bool)$matchedChapter['publicShareable'],
        'theme' => $matchedChapter['theme'] ?? '',
        'allowComments' => $matchedChapter['allowComments'] ?? null,
        'allowTalk' => $matchedChapter['allowTalk'] ?? null,
        'approver' => $matchedChapter['approver'] ?? '',
        'approverNote' => $matchedChapter['approverNote'] ?? '',
        'statusUpdatedAt' => $matchedChapter['statusUpdatedAt'] ?? null,
        'statusUpdatedBy' => $matchedChapter['statusUpdatedBy'] ?? null,
        'translations' => $matchedChapter['translations'] ?? null,
        'isProtected' => $isProtected,
        'sha256' => $sha256,
        'sizeBytes' => $sizeBytes,
        'mtime' => $mtime,
        'dateModified' => date('c', $mtime),
        'lock' => $lockStatus,
        'content' => $rawContent,
    ];

    if ($includeComments) {
        $commentServicePath = $baseDir . '/assets/extensions/tool-comments/CommentService.php';
        if (!is_file($commentServicePath)) {
            $commentServicePath = __DIR__ . '/../assets/extensions/tool-comments/CommentService.php';
        }
        if (is_file($commentServicePath)) {
            require_once $commentServicePath;
            $commentsEnabled = \Qwiki\Extension\Comments\CommentService::isCommentsEnabled($resolvedBookId, $resolvedSlug, $config);
            $commentsData = \Qwiki\Extension\Comments\CommentService::getComments($resolvedBookId, $resolvedSlug);
            $commentsList = $commentsData['comments'] ?? [];
            $commentsCount = 0;
            foreach ($commentsList as $c) {
                if (empty($c['isDeleted'])) $commentsCount++;
                if (!empty($c['replies']) && is_array($c['replies'])) {
                    foreach ($c['replies'] as $r) {
                        if (empty($r['isDeleted'])) $commentsCount++;
                    }
                }
            }
            $docPayload['commentsEnabled'] = $commentsEnabled;
            $docPayload['commentsCount'] = $commentsCount;
            $docPayload['commentsUpdatedAt'] = $commentsData['updatedAt'] ?? null;
            $docPayload['comments'] = $commentsList;
        } else {
            $docPayload['commentsEnabled'] = false;
            $docPayload['commentsCount'] = 0;
            $docPayload['comments'] = [];
        }
    }

    if ($includeTalk) {
        $talkServicePath = $baseDir . '/assets/extensions/tool-talk/TalkService.php';
        if (!is_file($talkServicePath)) {
            $talkServicePath = __DIR__ . '/../assets/extensions/tool-talk/TalkService.php';
        }
        if (is_file($talkServicePath)) {
            require_once $talkServicePath;
            $talkEnabled = \Qwiki\Extension\Talk\TalkService::isTalkEnabled($resolvedBookId, $resolvedSlug, $config);
            $talkTopics = \Qwiki\Extension\Talk\TalkService::getTopics($resolvedBookId, $resolvedSlug);
            $talkRaw = \Qwiki\Extension\Talk\TalkService::getRawContent($resolvedBookId, $resolvedSlug);
            $docPayload['talkEnabled'] = $talkEnabled;
            $docPayload['talkTopicsCount'] = count($talkTopics);
            $docPayload['talkTopics'] = $talkTopics;
            $docPayload['talkRaw'] = $talkRaw;
        } else {
            $docPayload['talkEnabled'] = false;
            $docPayload['talkTopicsCount'] = 0;
            $docPayload['talkTopics'] = [];
            $docPayload['talkRaw'] = '';
        }
    }

    echo json_encode([
        'success' => true,
        'document' => $docPayload
    ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($action === 'set_status' || $action === 'update_status') {
    $identifier = trim($_POST['shareKey'] ?? $_POST['slug'] ?? $_POST['file'] ?? $_GET['shareKey'] ?? $_GET['slug'] ?? '');
    $newStatus = strtolower(trim($_POST['status'] ?? $_GET['status'] ?? ''));
    $approver = trim($_POST['approver'] ?? '');
    $approverNote = trim($_POST['approverNote'] ?? $_POST['note'] ?? '');
    $cleanSource = false;
    if (isset($_POST['clean_source'])) {
        $cleanVal = $_POST['clean_source'];
        if ($cleanVal === true || $cleanVal === 'true' || $cleanVal === '1' || $cleanVal === 1) {
            $cleanSource = true;
        }
    }

    if (empty($identifier) || empty($newStatus)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'identifier (shareKey, slug, or file) and status are required']);
        exit;
    }

    if (!in_array($newStatus, Navigation::VALID_STATUSES, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid status. Allowed: ' . implode(', ', Navigation::VALID_STATUSES)]);
        exit;
    }

    $updatedChapter = Navigation::updateChapterStatus($config['books'], $identifier, $newStatus, [
        'updatedBy' => 'api',
        'approver' => $approver,
        'note' => $approverNote
    ]);

    if ($updatedChapter === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Chapter not found for identifier']);
        exit;
    }

    // If HTML document, update file on disk as well
    $baseDir = Config::getBaseDir();
    $relFile = $updatedChapter['file'] ?? '';
    if (!empty($relFile) && preg_match('/\.(html|htm)$/i', $relFile)) {
        $absFile = Config::safePath($baseDir, $relFile);
        if ($absFile && file_exists($absFile)) {
            Navigation::sanitizeHtmlDocumentStatus($absFile, $newStatus, $cleanSource);
        }
    }

    if (Config::save($config)) {
        $baseUrl = Config::getBaseUrl();
        $sKey = $updatedChapter['shareKey'] ?? '';
        $slug = $updatedChapter['slug'] ?? '';
        $bookId = Navigation::findChapterParentId($config['books'] ?? [], $slug) ?? '';
        $shareUrl = !empty($sKey) ? ($baseUrl . '?share=' . urlencode($sKey)) : '';
        $url = $baseUrl . urlencode($bookId) . '/' . urlencode($slug);

        echo json_encode([
            'success' => true,
            'slug' => $slug,
            'bookId' => $bookId,
            'shareKey' => $sKey,
            'status' => $newStatus,
            'statusUpdatedAt' => $updatedChapter['statusUpdatedAt'] ?? date('c'),
            'approver' => $updatedChapter['approver'] ?? $approver,
            'shareUrl' => $shareUrl,
            'url' => $url
        ]);
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update qwiki.json']);
        exit;
    }
}

// Helper to resolve a form chapter from slug, docId, shareKey, or file
$resolveFormChapter = function() use ($config, $llmKeyRecord) {
    $reqSlug = trim((string)($_POST['slug'] ?? $_GET['slug'] ?? ''));
    $reqDocId = trim((string)($_POST['docId'] ?? $_GET['docId'] ?? ''));
    $reqShareKey = trim((string)($_POST['shareKey'] ?? $_GET['shareKey'] ?? ''));
    $reqBookId = trim((string)($_POST['bookId'] ?? $_GET['bookId'] ?? ''));
    $reqFile = trim((string)($_POST['file'] ?? $_GET['file'] ?? ''));

    $matchedChapter = null;
    $matchedParent = null;

    if (!empty($reqDocId)) {
        $matchedChapter = Navigation::findChapterByDocId($config['books'] ?? [], $reqDocId, $matchedParent);
    }
    if ($matchedChapter === null && !empty($reqShareKey)) {
        $matchedChapter = Navigation::findChapterByShareKey($config['books'] ?? [], $reqShareKey, $matchedParent);
    }
    if ($matchedChapter === null && !empty($reqSlug)) {
        $matchedChapter = Navigation::findChapterBySlug($config['books'] ?? [], $reqSlug, $matchedParent);
    }
    if ($matchedChapter === null && !empty($reqFile)) {
        $findByFile = function(array $nodes, string $targetFile, $curParent = null) use (&$findByFile) {
            foreach ($nodes as $node) {
                $isFolder = (isset($node['type']) && $node['type'] === 'folder') || !empty($node['items']);
                if ($isFolder) {
                    if (!empty($node['items']) && is_array($node['items'])) {
                        $res = $findByFile($node['items'], $targetFile, $node);
                        if ($res !== null) return $res;
                    }
                } elseif (($node['file'] ?? '') === $targetFile) {
                    return ['chapter' => $node, 'parent' => $curParent];
                }
            }
            return null;
        };
        $fileMatch = $findByFile($config['books'] ?? [], $reqFile);
        if ($fileMatch !== null) {
            $matchedChapter = $fileMatch['chapter'];
            $matchedParent = $fileMatch['parent'];
        }
    }

    if ($matchedChapter === null) {
        return ['error' => 'Form chapter not found', 'code' => 404];
    }

    $resolvedSlug = $matchedChapter['slug'] ?? '';
    $resolvedBookId = (!empty($reqBookId) && $matchedParent && ($matchedParent['id'] ?? '') === $reqBookId)
        ? $reqBookId
        : (Navigation::findChapterParentId($config['books'] ?? [], $resolvedSlug) ?? ($matchedParent['id'] ?? ''));

    if ($llmKeyRecord !== null && !empty($llmKeyRecord['category']) && $llmKeyRecord['category'] !== 'all') {
        if (strcasecmp((string)$llmKeyRecord['category'], (string)$resolvedBookId) !== 0) {
            return ['error' => 'API key is not authorized for this category', 'code' => 403];
        }
    }

    $relFile = $matchedChapter['file'] ?? '';
    $baseDir = Config::getBaseDir();
    $absFile = !empty($relFile) ? Config::safePath($baseDir, $relFile) : null;
    if (!$absFile || !is_file($absFile) || !preg_match('/\.form\.json$/i', $absFile)) {
        return ['error' => 'Form schema file (.form.json) not found on disk', 'code' => 404];
    }

    $subFile = preg_replace('/\.form\.json$/i', '.submissions.json', $absFile);
    return [
        'chapter' => $matchedChapter,
        'bookId' => $resolvedBookId,
        'slug' => $resolvedSlug,
        'relFile' => $relFile,
        'absFile' => $absFile,
        'subFile' => $subFile
    ];
};

if ($action === 'list_forms' || $action === 'get_forms') {
    $targetBookId = trim($_POST['bookId'] ?? $_GET['bookId'] ?? '');
    $baseUrl = Config::getBaseUrl();
    $baseDir = Config::getBaseDir();
    $forms = [];

    $collectForms = function($node, $catId, $inScopedBranch = false) use (&$collectForms, &$forms, $targetBookId, $baseUrl, $baseDir, $llmKeyRecord) {
        $curCatId = $node['id'] ?? $catId;
        $isScopedKey = ($llmKeyRecord !== null && !empty($llmKeyRecord['category']) && $llmKeyRecord['category'] !== 'all');
        $nowInScoped = $inScopedBranch || !$isScopedKey || (strcasecmp((string)$llmKeyRecord['category'], (string)$curCatId) === 0);

        if (!empty($node['items']) && is_array($node['items'])) {
            foreach ($node['items'] as $item) {
                if (isset($item['type']) && $item['type'] === 'folder') {
                    $collectForms($item, $item['id'] ?? $curCatId, $nowInScoped);
                } else {
                    $relFile = $item['file'] ?? '';
                    $isForm = (($item['type'] ?? '') === 'form') || preg_match('/\.form\.json$/i', $relFile);
                    if ($isForm && $nowInScoped && (empty($targetBookId) || $curCatId === $targetBookId)) {
                        $absFile = !empty($relFile) ? Config::safePath($baseDir, $relFile) : null;
                        $subCount = 0;
                        $latestSubAt = null;
                        $fieldsCount = 0;
                        $sha256 = '';
                        if ($absFile && is_file($absFile)) {
                            $sha256 = hash_file('sha256', $absFile);
                            $schema = json_decode((string)file_get_contents($absFile), true);
                            if (is_array($schema) && isset($schema['fields']) && is_array($schema['fields'])) {
                                $fieldsCount = count($schema['fields']);
                            }
                            $subFile = preg_replace('/\.form\.json$/i', '.submissions.json', $absFile);
                            if ($subFile && is_file($subFile)) {
                                $lines = file($subFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                                if (is_array($lines)) {
                                    $subCount = count($lines);
                                    if ($subCount > 0) {
                                        $lastEntry = json_decode($lines[$subCount - 1], true);
                                        if (is_array($lastEntry)) {
                                            $latestSubAt = $lastEntry['created_at'] ?? null;
                                        }
                                    }
                                }
                            }
                        }
                        $sKey = $item['shareKey'] ?? '';
                        $slug = $item['slug'] ?? '';
                        $forms[] = [
                            'docId' => $item['docId'] ?? '',
                            'bookId' => $curCatId,
                            'slug' => $slug,
                            'title' => $item['title'] ?? '',
                            'file' => $relFile,
                            'status' => $item['status'] ?? Navigation::DEFAULT_STATUS,
                            'shareKey' => $sKey,
                            'sha256' => $sha256,
                            'fieldsCount' => $fieldsCount,
                            'submissionsCount' => $subCount,
                            'latestSubmissionAt' => $latestSubAt,
                            'shareUrl' => !empty($sKey) ? ($baseUrl . '?share=' . urlencode($sKey)) : '',
                            'url' => $baseUrl . urlencode($curCatId) . '/' . urlencode($slug)
                        ];
                    }
                }
            }
        }
    };

    foreach ($config['books'] ?? [] as $book) {
        $collectForms($book, $book['id'] ?? '', false);
    }

    echo json_encode([
        'success' => true,
        'total' => count($forms),
        'forms' => $forms
    ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($action === 'get_form_submissions' || $action === 'ext_form_get_submissions' || $action === 'export_form_csv' || $action === 'ext_form_export_csv') {
    $resolved = $resolveFormChapter();
    if (isset($resolved['error'])) {
        http_response_code($resolved['code'] ?? 404);
        echo json_encode(['success' => false, 'error' => $resolved['error']]);
        exit;
    }

    $absFile = $resolved['absFile'];
    $subFile = $resolved['subFile'];
    $schema = json_decode((string)file_get_contents($absFile), true);
    if (!is_array($schema)) {
        $schema = [];
    }

    $since = trim((string)($_POST['since'] ?? $_GET['since'] ?? ''));
    $sinceId = trim((string)($_POST['since_id'] ?? $_POST['sinceId'] ?? $_GET['since_id'] ?? $_GET['sinceId'] ?? ''));
    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : (isset($_GET['limit']) ? (int)$_GET['limit'] : 0);
    $format = strtolower(trim((string)($_POST['format'] ?? $_GET['format'] ?? 'json')));
    if ($action === 'export_form_csv' || $action === 'ext_form_export_csv') {
        $format = 'csv';
    }

    $allItems = [];
    if ($subFile && is_file($subFile)) {
        $lines = file($subFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if (is_array($entry)) {
                    $allItems[] = $entry;
                }
            }
        }
    }

    $filtered = $allItems;
    if ($sinceId !== '') {
        $afterIdx = null;
        foreach ($filtered as $idx => $item) {
            if (($item['id'] ?? '') === $sinceId) {
                $afterIdx = $idx;
                break;
            }
        }
        if ($afterIdx !== null) {
            $filtered = array_slice($filtered, $afterIdx + 1);
        }
    }
    if ($since !== '') {
        $sinceTs = strtotime($since);
        if ($sinceTs !== false) {
            $filtered = array_values(array_filter($filtered, function($item) use ($sinceTs) {
                $itemTs = !empty($item['created_at']) ? strtotime($item['created_at']) : false;
                return $itemTs !== false && $itemTs >= $sinceTs;
            }));
        }
    }

    if ($limit > 0 && count($filtered) > $limit) {
        $filtered = array_slice($filtered, -$limit);
    }

    if ($format === 'csv') {
        $fields = $schema['fields'] ?? [];
        $headers = ['Submission ID', 'Date / Time', 'Submitted By', 'IP Hash'];
        $fieldMap = [];
        foreach ($fields as $f) {
            $fId = $f['id'] ?? '';
            $fLabel = $f['label'] ?? $fId;
            $headers[] = $fLabel;
            $fieldMap[] = $fId;
        }
        $fp = fopen('php://temp', 'r+');
        fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($fp, $headers);
        foreach ($filtered as $entry) {
            $row = [
                $entry['id'] ?? '',
                $entry['created_at'] ?? '',
                $entry['submitted_by'] ?? '',
                $entry['ip_hash'] ?? ''
            ];
            $entryData = $entry['data'] ?? [];
            foreach ($fieldMap as $fId) {
                $val = $entryData[$fId] ?? '';
                $row[] = is_array($val) ? implode(', ', $val) : (string)$val;
            }
            fputcsv($fp, $row);
        }
        rewind($fp);
        $csvContent = stream_get_contents($fp);
        fclose($fp);

        $rawCsv = !empty($_POST['raw_csv']) || !empty($_GET['raw_csv']) || $action === 'export_form_csv' || $action === 'ext_form_export_csv';
        if ($rawCsv) {
            if (!headers_sent()) {
                header('Content-Type: text/csv; charset=UTF-8');
                header('Content-Disposition: attachment; filename="' . $resolved['slug'] . '-submissions-' . date('Y-m-d') . '.csv"');
            }
            echo $csvContent;
            exit;
        }

        echo json_encode([
            'success' => true,
            'slug' => $resolved['slug'],
            'bookId' => $resolved['bookId'],
            'total' => count($allItems),
            'returned' => count($filtered),
            'csv' => $csvContent
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    echo json_encode([
        'success' => true,
        'docId' => $resolved['chapter']['docId'] ?? '',
        'bookId' => $resolved['bookId'],
        'slug' => $resolved['slug'],
        'title' => $resolved['chapter']['title'] ?? ($schema['title'] ?? ''),
        'file' => $resolved['relFile'],
        'schema' => $schema,
        'total' => count($allItems),
        'returned' => count($filtered),
        'submissions' => array_reverse($filtered)
    ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($action === 'delete_form_submission' || $action === 'ext_form_delete_submission') {
    $resolved = $resolveFormChapter();
    if (isset($resolved['error'])) {
        http_response_code($resolved['code'] ?? 404);
        echo json_encode(['success' => false, 'error' => $resolved['error']]);
        exit;
    }

    $subId = trim((string)($_POST['id'] ?? $_POST['submissionId'] ?? $_GET['id'] ?? ''));
    if ($subId === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Submission id is required']);
        exit;
    }

    $subFile = $resolved['subFile'];
    if (!$subFile || !is_file($subFile)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'No submissions found']);
        exit;
    }

    $lines = file($subFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $kept = [];
    $deleted = false;
    if (is_array($lines)) {
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (is_array($entry) && ($entry['id'] ?? '') === $subId) {
                $deleted = true;
                continue;
            }
            $kept[] = $line;
        }
    }

    if (!$deleted) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => "Submission '{$subId}' not found"]);
        exit;
    }

    $newContent = count($kept) > 0 ? implode("\n", $kept) . "\n" : '';
    file_put_contents($subFile, $newContent, LOCK_EX);

    echo json_encode([
        'success' => true,
        'deleted' => true,
        'id' => $subId,
        'remaining' => count($kept)
    ]);
    exit;
}

if ($action === 'clear_form_submissions' || $action === 'ext_form_clear_submissions') {
    $resolved = $resolveFormChapter();
    if (isset($resolved['error'])) {
        http_response_code($resolved['code'] ?? 404);
        echo json_encode(['success' => false, 'error' => $resolved['error']]);
        exit;
    }

    $subFile = $resolved['subFile'];
    if ($subFile && is_file($subFile)) {
        file_put_contents($subFile, '', LOCK_EX);
    }

    echo json_encode([
        'success' => true,
        'cleared' => true,
        'slug' => $resolved['slug']
    ]);
    exit;
}

// 3. Extract and Validate Document Inputs
$bookId = trim($_POST['bookId'] ?? '');
$title = trim($_POST['title'] ?? '');
$content = $_POST['content'] ?? '';
$type = strtolower(trim($_POST['type'] ?? 'markdown'));
if (!in_array($type, ['markdown', 'html', 'form'], true)) {
    $type = 'markdown';
}

$ogTitle = trim($_POST['ogTitle'] ?? ($_POST['og_title'] ?? ''));
$description = trim($_POST['description'] ?? '');
$providedShareKey = trim($_POST['shareKey'] ?? '');
$providedDocId = trim($_POST['docId'] ?? ($_POST['doc_id'] ?? ''));
$hasThemeParam = isset($_POST['theme']);
$providedTheme = $hasThemeParam ? validate_theme_filename($_POST['theme']) : '';
if ($providedTheme === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid chapter theme filename (must match theme-*.css)']);
    exit;
}
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

$ext = ($type === 'form') ? '.form.json' : (($type === 'html') ? '.html' : '.md');

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
$existingParentId = null;

if (!empty($providedDocId)) {
    $parentOfDoc = null;
    $chapterByDocId = Navigation::findChapterByDocId($config['books'] ?? [], $providedDocId, $parentOfDoc);
    if ($chapterByDocId !== null) {
        $existingChapter = $chapterByDocId;
        $existingParentId = $parentOfDoc['id'] ?? null;
    }
}

if ($existingChapter === null && !empty($providedShareKey)) {
    $parentOfShare = null;
    $chapterByShare = Navigation::findChapterByShareKey($config['books'] ?? [], $providedShareKey, $parentOfShare);
    if ($chapterByShare !== null) {
        $existingChapter = $chapterByShare;
        $existingParentId = $parentOfShare['id'] ?? null;
        if (empty($providedSlug) || $providedSlug === ($chapterByShare['slug'] ?? '')) {
            $baseSlug = $chapterByShare['slug'] ?? $baseSlug;
        }
        if (empty($_POST['bookId']) && !empty($parentOfShare['id']) && $parentOfShare['id'] !== $targetNodeId) {
            $targetNodeId = $parentOfShare['id'];
            foreach ($config['books'] as $b) {
                $resolvedShareFolder = find_target_folder($b, $targetNodeId);
                if ($resolvedShareFolder !== null) {
                    $resolvedAbs = Config::safePath($contentRoot, $resolvedShareFolder);
                    if ($resolvedAbs && (!$realContentRoot || strpos($resolvedAbs, $realContentRoot) === 0)) {
                        $targetFolder = $resolvedShareFolder;
                        $targetAbsDir = $resolvedAbs;
                        if (!is_dir($targetAbsDir)) {
                            @mkdir($targetAbsDir, 0755, true);
                        }
                    }
                    break;
                }
            }
        }
    }
}

if ($existingChapter === null) {
    foreach ($config['books'] as $b) {
        $existingChapter = $findExistingChapterInFolder($b, $targetNodeId, $baseSlug);
        if ($existingChapter !== null) {
            $existingParentId = $targetNodeId;
            break;
        }
    }
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

$docStatus = strtolower(trim($_POST['status'] ?? ''));
if (!in_array($docStatus, Navigation::VALID_STATUSES, true)) {
    $docStatus = ($existingChapter && !empty($existingChapter['status']) && in_array($existingChapter['status'], Navigation::VALID_STATUSES, true))
        ? $existingChapter['status']
        : Navigation::DEFAULT_STATUS;
}

$cleanOnPublish = false;
if (isset($_POST['clean_source'])) {
    $cVal = $_POST['clean_source'];
    if ($cVal === true || $cVal === 'true' || $cVal === '1' || $cVal === 1) {
        $cleanOnPublish = true;
    }
}

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
    $sanitizedContent = DocumentService::applyHtmlStatusToContent($sanitizedContent, $docStatus, $cleanOnPublish);
} elseif ($type === 'form') {
    $parsedSchema = json_decode((string)$content, true);
    if (!is_array($parsedSchema) || !isset($parsedSchema['fields']) || !is_array($parsedSchema['fields'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid form schema JSON: must be a JSON object with a "fields" array']);
        exit;
    }
    if (!empty($title) && empty($parsedSchema['title'])) {
        $parsedSchema['title'] = $title;
    }
    if (empty($description) && !empty($parsedSchema['description'])) {
        $description = trim((string)$parsedSchema['description']);
    }
    $sanitizedContent = json_encode($parsedSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    $sanitizedContent = sanitize_markdown($content);
}

// Prepare docId (preserve existing or generate new)
$docId = ($existingChapter && !empty($existingChapter['docId']))
    ? $existingChapter['docId']
    : ('doc_' . bin2hex(random_bytes(8)));

$chapterForWrite = $existingChapter ?? [
    'docId' => $docId,
    'slug' => $slug,
    'file' => $targetRelFile,
    'type' => $type,
];
$chapterForWrite['docId'] = $docId;

$oldAbsFile = ($existingChapter && !empty($existingChapter['file']))
    ? Config::safePath(Config::getBaseDir(), $existingChapter['file'])
    : $targetAbsFile;

$rawForce = $_POST['force'] ?? '';
$forceOverwrite = ($rawForce === true || $rawForce === 'true' || $rawForce === '1' || $rawForce === 1);
$expectedSha256 = strtolower(trim((string)($_POST['expectedSha256'] ?? $_POST['expected_sha256'] ?? $_POST['ifMatch'] ?? '')));

if ($existingChapter !== null && !$forceOverwrite) {
    $lockTargetRel = !empty($existingChapter['file']) ? $existingChapter['file'] : $targetRelFile;
    $activeLock = LockManager::checkLock($lockTargetRel);
    if (!empty($activeLock['locked'])) {
        http_response_code(423);
        echo json_encode([
            'success' => false,
            'code' => 'LOCKED_BY_OTHER',
            'lockedBy' => $activeLock['user'] ?? 'Unknown',
            'expiresIn' => $activeLock['expiresIn'] ?? 0,
            'error' => "Document is currently locked in the editor by '" . ($activeLock['user'] ?? 'Unknown') . "' (expires in " . ($activeLock['expiresIn'] ?? 0) . "s). Pass force=1 to override.",
        ]);
        exit;
    }

    if ($expectedSha256 !== '') {
        $hashCheckFile = ($oldAbsFile && is_file($oldAbsFile)) ? $oldAbsFile : (is_file($targetAbsFile) ? $targetAbsFile : null);
        if ($hashCheckFile !== null) {
            $actualSha256 = strtolower((string)hash_file('sha256', $hashCheckFile));
            if (!hash_equals($expectedSha256, $actualSha256)) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'code' => 'CONFLICT_SHA256_MISMATCH',
                    'expectedSha256' => $expectedSha256,
                    'actualSha256' => $actualSha256,
                    'error' => 'Document has changed on the remote wiki since it was pulled (SHA-256 mismatch). Pull and merge remote changes before re-uploading, or pass force=1 to overwrite.',
                ]);
                exit;
            }
        }
    }
}

// Write the document file via unified DocumentService (tracks sha256 + pre-write hooks + audit log)
$writeRes = DocumentService::replaceContent($chapterForWrite, $targetAbsFile, [
    'content' => $sanitizedContent,
    'oldAbsFile' => $oldAbsFile,
    'oldRelFile' => $existingChapter['file'] ?? '',
    'newRelFile' => $targetRelFile,
    'source' => 'api_publish',
    'actor' => 'api_publish',
    'event' => $existingChapter ? 'doc_replaced' : 'doc_published',
    'extraAudit' => ['type' => $type, 'bookId' => $bookId],
]);
if (empty($writeRes['success'])) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to write document file to disk']);
    exit;
}

if ($type === 'form') {
    $targetSubFile = preg_replace('/\.form\.json$/i', '.submissions.json', $targetAbsFile);
    if ($oldAbsFile && $oldAbsFile !== $targetAbsFile && preg_match('/\.form\.json$/i', $oldAbsFile)) {
        $oldSubFile = preg_replace('/\.form\.json$/i', '.submissions.json', $oldAbsFile);
        if ($oldSubFile && $targetSubFile && is_file($oldSubFile) && !is_file($targetSubFile)) {
            @rename($oldSubFile, $targetSubFile);
        }
    }
    if ($targetSubFile && !file_exists($targetSubFile)) {
        @file_put_contents($targetSubFile, '', LOCK_EX);
    }
}

// 7. Auto-Generate Share Key with Collision Check
$shareKey = !empty($providedShareKey) ? $providedShareKey : Navigation::generateShareKey();
$collidingChapter = Navigation::findChapterByShareKey($config['books'] ?? [], $shareKey);
if ($collidingChapter !== null && ($collidingChapter['slug'] ?? '') !== $slug) {
    if ($existingChapter !== null && ($existingChapter['shareKey'] ?? '') === $shareKey) {
        // Legitimate in-place update of existing chapter: keep original shareKey!
    } else {
        do {
            $shareKey = Navigation::generateShareKey();
        } while (Navigation::findChapterByShareKey($config['books'] ?? [], $shareKey) !== null);
    }
}

// 8. Prepare Chapter Data for qwiki.json
$chapterData = [
    'docId' => $docId,
    'title' => $title,
    'slug' => $slug,
    'type' => $type,
    'file' => $targetRelFile,
    'shareKey' => $shareKey
];

$chapterData['status'] = $docStatus;
$chapterData['statusUpdatedAt'] = date('c');
$chapterData['statusUpdatedBy'] = 'api';

if (!empty($_POST['approver'])) {
    $chapterData['approver'] = trim($_POST['approver']);
} elseif ($existingChapter && !empty($existingChapter['approver'])) {
    $chapterData['approver'] = $existingChapter['approver'];
}
if (!empty($_POST['approverNote']) || !empty($_POST['note'])) {
    $chapterData['approverNote'] = trim($_POST['approverNote'] ?? $_POST['note'] ?? '');
} elseif ($existingChapter && !empty($existingChapter['approverNote'])) {
    $chapterData['approverNote'] = $existingChapter['approverNote'];
}

if (!$publicShareable) {
    $chapterData['publicShareable'] = false;
}
if (!empty($ogTitle)) {
    $chapterData['ogTitle'] = $ogTitle;
}
if (!empty($description)) {
    $chapterData['description'] = $description;
}
if (!empty($image)) {
    $chapterData['image'] = $image;
}
if ($hasThemeParam) {
    if ($providedTheme !== '') {
        $chapterData['theme'] = $providedTheme;
    }
} elseif ($existingChapter && !empty($existingChapter['theme'])) {
    $chapterData['theme'] = $existingChapter['theme'];
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
if ($existingChapter !== null && $existingParentId !== null && $existingParentId !== $targetNodeId) {
    $removeOldByDocIdOrSlug = function(&$node, $targetFolderId, $targetDocId, $targetSlug) use (&$removeOldByDocIdOrSlug) {
        if (($node['id'] ?? '') === $targetFolderId && !empty($node['items']) && is_array($node['items'])) {
            $filtered = [];
            foreach ($node['items'] as $it) {
                $isFolder = (isset($it['type']) && $it['type'] === 'folder');
                if (!$isFolder && ((!empty($targetDocId) && ($it['docId'] ?? '') === $targetDocId) || ($it['slug'] ?? '') === $targetSlug)) {
                    continue;
                }
                $filtered[] = $it;
            }
            $node['items'] = $filtered;
            return true;
        }
        if (!empty($node['items']) && is_array($node['items'])) {
            foreach ($node['items'] as &$child) {
                if (isset($child['type']) && $child['type'] === 'folder') {
                    if ($removeOldByDocIdOrSlug($child, $targetFolderId, $targetDocId, $targetSlug)) {
                        return true;
                    }
                }
            }
        }
        return false;
    };
    foreach ($config['books'] as &$book) {
        if ($removeOldByDocIdOrSlug($book, $existingParentId, $docId, $existingChapter['slug'] ?? '')) {
            break;
        }
    }
}

$isUpdated = ($existingChapter !== null);
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
        'docId' => $docId,
        'bookId' => $bookId,
        'slug' => $slug,
        'file' => $targetRelFile,
        'type' => $type,
        'theme' => $chapterData['theme'] ?? '',
        'shareKey' => $shareKey,
        'status' => $docStatus,
        'statusUpdatedAt' => $chapterData['statusUpdatedAt'],
        'sha256' => $writeRes['sha256After'] ?? null,
        'changed' => $writeRes['changed'] ?? true,
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
