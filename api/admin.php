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
$action = $_REQUEST['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '';

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
            'items' => []
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
        $theme = trim($_POST['theme'] ?? '');
        $visibility = trim($_POST['visibility'] ?? 'public');

        if (empty($bookId) || empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Category ID and Title are required']);
            exit;
        }

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

        $updated = false;
        foreach ($config['books'] as &$book) {
            if (update_node_meta($book, $bookId, $title, $theme, $visibility)) {
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

    case 'delete_book':
        if (!is_admin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $bookId = $_POST['bookId'] ?? '';
        if (empty($bookId)) {
            echo json_encode(['success' => false, 'error' => 'Category ID is required']);
            exit;
        }

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

        if (delete_node_recursive($config['books'], $bookId)) {
            save_config($configFile, $config);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Category not found or failed to delete']);
        }
        break;

    case 'create_markdown':
        if (!is_admin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $bookId = $_POST['bookId'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? "";
        if (isset($_POST['content_base64'])) {
            $content = base64_decode($_POST['content_base64']);
        }

        // Auto-extract title from first H1 heading if title was left blank
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

        function update_chapter_in_node(&$node, $slug, $updatedData) {
            if (!empty($node['items'])) {
                foreach ($node['items'] as &$ch) {
                    if (!isset($ch['type']) || $ch['type'] !== 'folder') {
                        if ($ch['slug'] === $slug) {
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
        if (isset($_POST['content_base64'])) {
            $content = base64_decode($_POST['content_base64']);
        }
        
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
                    if (!isset($node['items'])) $node['items'] = [];
                    $node['items'][] = $chapterData;
                    return true;
                }
                if (!empty($node['items'])) {
                    foreach ($node['items'] as &$sub) {
                        if (isset($sub['type']) && $sub['type'] === 'folder') {
                            if (insert_chapter_into_node_upload($sub, $targetFolderId, $chapterData)) {
                                return true;
                            }
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
                if (!isset($node['items'])) $node['items'] = [];
                $node['items'][] = $chapterData;
                return true;
            }
            if (!empty($node['items'])) {
                foreach ($node['items'] as &$sub) {
                    if (isset($sub['type']) && $sub['type'] === 'folder') {
                        if (insert_chapter_into_node_gdoc($sub, $targetFolderId, $chapterData)) {
                            return true;
                        }
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
            if (!empty($node['items'])) {
                $newItems = [];
                foreach ($node['items'] as &$item) {
                    if (!isset($item['type']) || $item['type'] !== 'folder') {
                        if ($item['slug'] !== $slug) {
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
        $logoUrl = trim($_POST['logoUrl'] ?? '');
        $theme = trim($_POST['theme'] ?? 'theme-default.css');
        $defaultBook = trim($_POST['defaultBook'] ?? '');
        $requireLoginToView = isset($_POST['requireLoginToView']) && $_POST['requireLoginToView'] === '1';
        $showDocTypesOnlyToAdmin = isset($_POST['showDocTypesOnlyToAdmin']) && $_POST['showDocTypesOnlyToAdmin'] === '1';
        $newAdminPassword = $_POST['newAdminPassword'] ?? '';
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

        if (!empty($newAdminPassword)) {
            if (strlen($newAdminPassword) < 4) {
                echo json_encode(['success' => false, 'error' => 'New password must be at least 4 characters']);
                exit;
            }
            $newHash = password_hash($newAdminPassword, PASSWORD_DEFAULT);
            $config['adminPasswordHash'] = $newHash;
            if (isset($userData['users']) && is_array($userData['users'])) {
                foreach ($userData['users'] as &$u) {
                    if (($u['username'] ?? '') === 'admin') {
                        $u['passwordHash'] = $newHash;
                    }
                }
                save_users($usersFile, $userData);
            }
        }

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

    case 'list_themes':
        if (!is_admin()) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
        $themes = [];
        $dir = __DIR__ . '/../assets/css/';
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
        if (!is_admin()) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
        $themeFile = basename($_POST['theme'] ?? $_GET['theme'] ?? '');
        if (empty($themeFile)) { echo json_encode(['success' => false, 'error' => 'No theme specified']); exit; }
        $path = __DIR__ . '/../assets/css/' . $themeFile;
        if (file_exists($path)) {
            echo json_encode(['success' => true, 'content' => file_get_contents($path)]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Theme not found']);
        }
        break;

    case 'save_theme':
        if (!is_admin()) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
        $themeFile = basename($_POST['theme'] ?? '');
        $content = $_POST['content'] ?? '';
        if (isset($_POST['content_base64'])) {
            $content = base64_decode($_POST['content_base64']);
        }
        if (empty($themeFile) || empty($content)) { echo json_encode(['success' => false, 'error' => 'Invalid parameters']); exit; }
        if (!preg_match('/^theme-[a-zA-Z0-9-]+\.css$/', $themeFile)) { echo json_encode(['success' => false, 'error' => 'Invalid theme file name. Must start with theme- and end with .css']); exit; }
        $path = __DIR__ . '/../assets/css/' . $themeFile;
        if (file_put_contents($path, $content) !== false) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save theme']);
        }
        break;

    case 'check_updates':
        if (!is_admin()) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
        
        $cacheFile = __DIR__ . '/../uploads/update_cache.json';
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
            $cache = json_decode(file_get_contents($cacheFile), true);
            echo json_encode(['success' => true, 'has_update' => $cache['has_update'], 'version' => $cache['version'], 'notes' => $cache['notes'], 'zip_url' => $cache['zip_url']]);
            exit;
        }

        $indexContent = file_get_contents(__DIR__ . '/../index.php');
        $currentVersion = '1.0.0';
        if (preg_match("/define\('QWIKI_VERSION',\s*'([^']+)'\)/", $indexContent, $matches)) {
            $currentVersion = $matches[1];
        }

        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => ['User-Agent: PHP-Qwiki-Updater']
            ]
        ];
        $context = stream_context_create($opts);
        $response = @file_get_contents('https://api.github.com/repos/FrancoBenedetti/standalone-qwiki/releases', false, $context);
        
        if ($response) {
            $releases = json_decode($response, true);
            if (!empty($releases) && is_array($releases)) {
                $latest = $releases[0]; // Releases API is chronologically sorted
                $latestVersion = ltrim($latest['tag_name'], 'v');
                $currVerClean = ltrim($currentVersion, 'v');
                
                $hasUpdate = version_compare($latestVersion, $currVerClean, '>');
                
                $data = [
                    'has_update' => $hasUpdate,
                    'version' => $latest['tag_name'],
                    'notes' => $latest['body'],
                    'zip_url' => $latest['zipball_url']
                ];
                
                if (!is_dir(__DIR__ . '/../uploads')) mkdir(__DIR__ . '/../uploads', 0755, true);
                file_put_contents($cacheFile, json_encode($data));
                
                echo json_encode(array_merge(['success' => true], $data));
                exit;
            }
        }
        echo json_encode(['success' => false, 'error' => 'Failed to check for updates']);
        break;

    case 'install_update':
        if (!is_admin()) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }
        
        $zipUrl = $_POST['zip_url'] ?? '';
        if (empty($zipUrl)) { echo json_encode(['success' => false, 'error' => 'Missing zip URL']); exit; }
        
        if (!class_exists('ZipArchive')) {
            echo json_encode(['success' => false, 'error' => 'ZipArchive PHP extension is not installed']);
            exit;
        }

        $tempZip = __DIR__ . '/../uploads/update_temp.zip';
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => ['User-Agent: PHP-Qwiki-Updater']
            ]
        ];
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
            // Exclude these paths
            $excludes = ['content/', 'uploads/', 'qwiki.json', 'users.json'];
            
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if ($i === 0) {
                    $rootFolder = $filename;
                }
                
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
                
                $targetPath = __DIR__ . '/../' . $relativePath;
                
                if (substr($filename, -1) === '/') {
                    if (!is_dir($targetPath)) mkdir($targetPath, 0755, true);
                } else {
                    $dir = dirname($targetPath);
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    
                    $content = $zip->getFromIndex($i);
                    file_put_contents($targetPath, $content);
                }
            }
            $zip->close();
            unlink($tempZip);
            if (file_exists(__DIR__ . '/../uploads/update_cache.json')) {
                unlink(__DIR__ . '/../uploads/update_cache.json');
            }
            echo json_encode(['success' => true]);
        } else {
            @unlink($tempZip);
            echo json_encode(['success' => false, 'error' => 'Failed to extract update zip']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
