<?php
session_start();
header('Content-Type: application/json');

$configFile = __DIR__ . '/../qwiki.json';
if (!file_exists($configFile)) {
    echo json_encode(['success' => false, 'error' => 'Configuration file missing']);
    exit;
}

$config = json_decode(file_get_contents($configFile), true);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function save_config($configFile, $config) {
    return file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
}

function make_slug($text) {
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

/**
 * Recursive helper to insert chapter into matching book or subfolder node
 */
function insert_chapter_into_node(&$node, $targetFolderId, $chapterData) {
    if (($node['id'] ?? '') === $targetFolderId) {
        if (!isset($node['chapters'])) $node['chapters'] = [];
        $node['chapters'][] = $chapterData;
        return true;
    }
    if (!empty($node['subfolders'])) {
        foreach ($node['subfolders'] as &$sub) {
            if (insert_chapter_into_node($sub, $targetFolderId, $chapterData)) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Recursive helper to update folder/book node metadata
 */
function update_node_title(&$node, $targetId, $newTitle) {
    if (($node['id'] ?? '') === $targetId) {
        $node['title'] = $newTitle;
        return true;
    }
    if (!empty($node['subfolders'])) {
        foreach ($node['subfolders'] as &$sub) {
            if (update_node_title($sub, $targetId, $newTitle)) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Recursive helper to update chapter metadata
 */
function update_chapter_in_node(&$node, $slug, $updatedData) {
    if (!empty($node['chapters'])) {
        foreach ($node['chapters'] as &$ch) {
            if ($ch['slug'] === $slug) {
                if (!empty($updatedData['title'])) $ch['title'] = $updatedData['title'];
                if (!empty($updatedData['type'])) $ch['type'] = $updatedData['type'];
                if (isset($updatedData['url'])) $ch['url'] = $updatedData['url'];
                if (isset($updatedData['editUrl'])) $ch['editUrl'] = $updatedData['editUrl'];
                if (isset($updatedData['file'])) $ch['file'] = $updatedData['file'];
                return true;
            }
        }
    }
    if (!empty($node['subfolders'])) {
        foreach ($node['subfolders'] as &$sub) {
            if (update_chapter_in_node($sub, $slug, $updatedData)) {
                return true;
            }
        }
    }
    return false;
}

switch ($action) {
    case 'login':
        $password = $_POST['password'] ?? '';
        if (password_verify($password, $config['adminPasswordHash'] ?? '')) {
            $_SESSION['qwiki_admin'] = true;
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid password']);
        }
        break;

    case 'logout':
        unset($_SESSION['qwiki_admin']);
        session_destroy();
        echo json_encode(['success' => true]);
        break;

    case 'add_book':
        if (empty($_SESSION['qwiki_admin'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $title = trim($_POST['title'] ?? '');
        $bookId = make_slug($_POST['id'] ?? $title);

        if (empty($title) || empty($bookId)) {
            echo json_encode(['success' => false, 'error' => 'Category title is required']);
            exit;
        }

        $bookFolder = __DIR__ . '/../content/' . $bookId;
        if (!is_dir($bookFolder)) {
            mkdir($bookFolder, 0755, true);
        }

        $config['books'][] = [
            'id' => $bookId,
            'title' => $title,
            'folder' => 'content/' . $bookId,
            'chapters' => []
        ];

        if (save_config($configFile, $config)) {
            echo json_encode(['success' => true, 'bookId' => $bookId]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update qwiki.json']);
        }
        break;

    case 'edit_book':
        if (empty($_SESSION['qwiki_admin'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $bookId = $_POST['bookId'] ?? '';
        $title = trim($_POST['title'] ?? '');

        if (empty($bookId) || empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Category ID and Title are required']);
            exit;
        }

        $updated = false;
        foreach ($config['books'] as &$book) {
            if (update_node_title($book, $bookId, $title)) {
                $updated = true;
                break;
            }
        }

        if ($updated && save_config($configFile, $config)) {
            echo json_encode(['success' => true, 'bookId' => $bookId]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Category not found or save failed']);
        }
        break;

    case 'create_markdown':
        if (empty($_SESSION['qwiki_admin'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $bookId = $_POST['bookId'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? "# {$title}\n\nWrite your documentation content here...";

        if (empty($bookId) || empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Category and title are required']);
            exit;
        }

        $slug = make_slug($title);
        $targetRelDir = 'content/' . $bookId;
        $targetAbsDir = __DIR__ . '/../' . $targetRelDir;

        if (!is_dir($targetAbsDir)) {
            mkdir($targetAbsDir, 0755, true);
        }

        $targetRelFile = $targetRelDir . '/' . $slug . '.md';
        $targetAbsFile = __DIR__ . '/../' . $targetRelFile;

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

        if ($added && save_config($configFile, $config)) {
            echo json_encode(['success' => true, 'bookId' => $bookId, 'slug' => $slug]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update qwiki.json']);
        }
        break;

    case 'edit_chapter':
        if (empty($_SESSION['qwiki_admin'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $slug = $_POST['slug'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $type = $_POST['type'] ?? 'markdown';
        $url = trim($_POST['url'] ?? '');
        $editUrl = trim($_POST['editUrl'] ?? '');
        $file = trim($_POST['file'] ?? '');

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
            'file' => $file
        ];

        $updated = false;
        foreach ($config['books'] as &$book) {
            if (update_chapter_in_node($book, $slug, $updatedData)) {
                $updated = true;
                break;
            }
        }

        if ($updated && save_config($configFile, $config)) {
            echo json_encode(['success' => true, 'slug' => $slug]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Document entry not found or save failed']);
        }
        break;

    case 'save_markdown':
        if (empty($_SESSION['qwiki_admin'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $relFile = $_POST['file'] ?? '';
        $content = $_POST['content'] ?? '';
        
        $targetPath = realpath(__DIR__ . '/../' . $relFile);
        $projectRoot = realpath(__DIR__ . '/../');
        
        if (!$targetPath || strpos($targetPath, $projectRoot) !== 0 || !preg_match('/\.md$/i', $targetPath)) {
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
        if (empty($_SESSION['qwiki_admin'])) {
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
            echo json_encode(['success' => false, 'error' => 'Only .md and .pdf files are supported']);
            exit;
        }

        $slug = make_slug($title);
        $targetRelDir = 'content/' . $bookId;
        $targetAbsDir = __DIR__ . '/../' . $targetRelDir;

        if (!is_dir($targetAbsDir)) {
            mkdir($targetAbsDir, 0755, true);
        }

        $targetRelFile = $targetRelDir . '/' . $slug . '.' . $ext;
        $targetAbsFile = __DIR__ . '/../' . $targetRelFile;

        if (move_uploaded_file($_FILES['document']['tmp_name'], $targetAbsFile)) {
            $chapterData = [
                'title' => $title,
                'slug' => $slug,
                'type' => ($ext === 'pdf') ? 'pdf' : 'markdown',
                'file' => $targetRelFile
            ];

            foreach ($config['books'] as &$book) {
                if (insert_chapter_into_node($book, $bookId, $chapterData)) {
                    break;
                }
            }
            save_config($configFile, $config);
            echo json_encode(['success' => true, 'bookId' => $bookId, 'slug' => $slug]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
        }
        break;

    case 'add_gdoc':
        if (empty($_SESSION['qwiki_admin'])) {
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

        $slug = make_slug($title);
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
        save_config($configFile, $config);
        echo json_encode(['success' => true, 'bookId' => $bookId, 'slug' => $slug]);
        break;

    case 'delete_chapter':
        if (empty($_SESSION['qwiki_admin'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $bookId = $_POST['bookId'] ?? '';
        $slug = $_POST['slug'] ?? '';

        if (empty($bookId) || empty($slug)) {
            echo json_encode(['success' => false, 'error' => 'Book ID and chapter slug required']);
            exit;
        }

        function delete_chapter_from_node(&$node, $slug) {
            if (!empty($node['chapters'])) {
                $newCh = [];
                foreach ($node['chapters'] as $ch) {
                    if ($ch['slug'] !== $slug) {
                        $newCh[] = $ch;
                    }
                }
                $node['chapters'] = $newCh;
            }
            if (!empty($node['subfolders'])) {
                foreach ($node['subfolders'] as &$sub) {
                    delete_chapter_from_node($sub, $slug);
                }
            }
        }

        foreach ($config['books'] as &$book) {
            delete_chapter_from_node($book, $slug);
        }
        save_config($configFile, $config);
        echo json_encode(['success' => true]);
        break;

    case 'update_settings':
        if (empty($_SESSION['qwiki_admin'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $title = trim($_POST['title'] ?? '');
        $logoText = trim($_POST['logoText'] ?? '');
        $defaultBook = trim($_POST['defaultBook'] ?? '');

        if (!empty($title)) $config['title'] = $title;
        if (!empty($logoText)) $config['logoText'] = $logoText;
        if (!empty($defaultBook)) $config['defaultBook'] = $defaultBook;

        save_config($configFile, $config);
        echo json_encode(['success' => true]);
        break;

    case 'reorder_tree':
        if (empty($_SESSION['qwiki_admin'])) {
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
            $config['books'] = $tree;
            if (save_config($configFile, $config)) {
                echo json_encode(['success' => true]);
                exit;
            }
        }
        echo json_encode(['success' => false, 'error' => 'Invalid tree data provided']);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
