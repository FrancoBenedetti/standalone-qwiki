<?php
session_start();
header('Content-Type: application/json');

$configFile = __DIR__ . '/../qwiki.json';
$usersFile  = __DIR__ . '/../users.json';

if (!file_exists($configFile)) {
    echo json_encode(['success' => false, 'error' => 'Configuration file missing']);
    exit;
}

$config = json_decode(file_get_contents($configFile), true);

// Initialize users store if missing
if (!file_exists($usersFile)) {
    $initialUsers = [
        'users' => [
            [
                'username' => 'admin',
                'role' => 'admin',
                'passwordHash' => '$2y$10$H8vIUts/BIGCXGCmw9xFHuCBnPGgNHZ44F59OcQYYxDVKBmD19DIm',
                'createdAt' => date('Y-m-d H:i:s')
            ]
        ]
    ];
    file_put_contents($usersFile, json_encode($initialUsers, JSON_PRETTY_PRINT));
}

$userData = json_decode(file_get_contents($usersFile), true);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function save_config($configFile, $config) {
    return file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
}

function save_users($usersFile, $userData) {
    return file_put_contents($usersFile, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
}

function make_slug($text) {
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

function is_admin() {
    return (!empty($_SESSION['qwiki_user']) && $_SESSION['qwiki_user']['role'] === 'admin') || !empty($_SESSION['qwiki_admin']);
}

switch ($action) {
    case 'login':
        $username = trim($_POST['username'] ?? 'admin');
        $password = $_POST['password'] ?? '';

        $matchedUser = null;
        if (!empty($userData['users'])) {
            foreach ($userData['users'] as $u) {
                if (strtolower($u['username']) === strtolower($username)) {
                    $matchedUser = $u;
                    break;
                }
            }
        }

        // Fallback check against qwiki.json legacy admin hash if user store doesn't have match
        if (!$matchedUser && strtolower($username) === 'admin') {
            if (password_verify($password, $config['adminPasswordHash'] ?? '')) {
                $_SESSION['qwiki_user'] = ['username' => 'admin', 'role' => 'admin'];
                $_SESSION['qwiki_admin'] = true;
                echo json_encode(['success' => true, 'role' => 'admin', 'username' => 'admin']);
                exit;
            }
        }

        if ($matchedUser && password_verify($password, $matchedUser['passwordHash'])) {
            $_SESSION['qwiki_user'] = [
                'username' => $matchedUser['username'],
                'role' => $matchedUser['role']
            ];
            if ($matchedUser['role'] === 'admin') {
                $_SESSION['qwiki_admin'] = true;
            } else {
                unset($_SESSION['qwiki_admin']);
            }
            echo json_encode(['success' => true, 'role' => $matchedUser['role'], 'username' => $matchedUser['username']]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
        }
        break;

    case 'logout':
        unset($_SESSION['qwiki_user']);
        unset($_SESSION['qwiki_admin']);
        session_destroy();
        echo json_encode(['success' => true]);
        break;

    case 'list_users':
        if (!is_admin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $safeList = [];
        if (!empty($userData['users'])) {
            foreach ($userData['users'] as $u) {
                $safeList[] = [
                    'username' => $u['username'],
                    'role' => $u['role'],
                    'createdAt' => $u['createdAt'] ?? ''
                ];
            }
        }
        echo json_encode(['success' => true, 'users' => $safeList]);
        break;

    case 'add_user':
        if (!is_admin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $newUsername = trim($_POST['username'] ?? '');
        $newPassword = $_POST['password'] ?? '';
        $newRole     = $_POST['role'] ?? 'viewer';

        if (empty($newUsername) || empty($newPassword)) {
            echo json_encode(['success' => false, 'error' => 'Username and password are required']);
            exit;
        }

        if (!in_array($newRole, ['admin', 'viewer'])) {
            $newRole = 'viewer';
        }

        foreach ($userData['users'] as $u) {
            if (strtolower($u['username']) === strtolower($newUsername)) {
                echo json_encode(['success' => false, 'error' => 'User with this username already exists']);
                exit;
            }
        }

        $userData['users'][] = [
            'username' => $newUsername,
            'role' => $newRole,
            'passwordHash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'createdAt' => date('Y-m-d H:i:s')
        ];

        if (save_users($usersFile, $userData)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save users database']);
        }
        break;

    case 'delete_user':
        if (!is_admin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $targetUsername = trim($_POST['username'] ?? '');
        if (empty($targetUsername)) {
            echo json_encode(['success' => false, 'error' => 'Username is required']);
            exit;
        }

        if (strtolower($targetUsername) === 'admin' || (isset($_SESSION['qwiki_user']) && strtolower($_SESSION['qwiki_user']['username']) === strtolower($targetUsername))) {
            echo json_encode(['success' => false, 'error' => 'Cannot delete the primary admin or currently logged-in account']);
            exit;
        }

        $newList = [];
        foreach ($userData['users'] as $u) {
            if (strtolower($u['username']) !== strtolower($targetUsername)) {
                $newList[] = $u;
            }
        }
        $userData['users'] = $newList;

        if (save_users($usersFile, $userData)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save users database']);
        }
        break;

    case 'add_book':
        if (!is_admin()) {
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
        if (!is_admin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $bookId = $_POST['bookId'] ?? '';
        $title = trim($_POST['title'] ?? '');

        if (empty($bookId) || empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Category ID and Title are required']);
            exit;
        }

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
        if (!is_admin()) {
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
        if (!is_admin()) {
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
        if (!is_admin()) {
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
        if (!is_admin()) {
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

            function insert_chapter_into_node_upload(&$node, $targetFolderId, $chapterData) {
                if (($node['id'] ?? '') === $targetFolderId) {
                    if (!isset($node['chapters'])) $node['chapters'] = [];
                    $node['chapters'][] = $chapterData;
                    return true;
                }
                if (!empty($node['subfolders'])) {
                    foreach ($node['subfolders'] as &$sub) {
                        if (insert_chapter_into_node_upload($sub, $targetFolderId, $chapterData)) {
                            return true;
                        }
                    }
                }
                return false;
            }

            foreach ($config['books'] as &$book) {
                if (insert_chapter_into_node_upload($book, $bookId, $chapterData)) {
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
        if (!is_admin()) {
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

        function insert_chapter_into_node_gdoc(&$node, $targetFolderId, $chapterData) {
            if (($node['id'] ?? '') === $targetFolderId) {
                if (!isset($node['chapters'])) $node['chapters'] = [];
                $node['chapters'][] = $chapterData;
                return true;
            }
            if (!empty($node['subfolders'])) {
                foreach ($node['subfolders'] as &$sub) {
                    if (insert_chapter_into_node_gdoc($sub, $targetFolderId, $chapterData)) {
                        return true;
                    }
                }
            }
            return false;
        }

        foreach ($config['books'] as &$book) {
            if (insert_chapter_into_node_gdoc($book, $bookId, $chapterData)) {
                break;
            }
        }
        save_config($configFile, $config);
        echo json_encode(['success' => true, 'bookId' => $bookId, 'slug' => $slug]);
        break;

    case 'delete_chapter':
        if (!is_admin()) {
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
        if (!is_admin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $title = trim($_POST['title'] ?? '');
        $logoText = trim($_POST['logoText'] ?? '');
        $defaultBook = trim($_POST['defaultBook'] ?? '');
        $requireLoginToView = isset($_POST['requireLoginToView']) && $_POST['requireLoginToView'] === '1';

        if (!empty($title)) $config['title'] = $title;
        if (!empty($logoText)) $config['logoText'] = $logoText;
        if (!empty($defaultBook)) $config['defaultBook'] = $defaultBook;
        $config['requireLoginToView'] = $requireLoginToView;

        save_config($configFile, $config);
        echo json_encode(['success' => true]);
        break;

    case 'reorder_tree':
        if (!is_admin()) {
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

    case 'upload_image':
        if (!is_admin()) {
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
            echo json_encode(['success' => false, 'error' => 'Invalid image format. Allowed: png, jpg, jpeg, gif, svg, webp']);
            exit;
        }

        $uploadDir = __DIR__ . '/../uploads/images/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
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

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
