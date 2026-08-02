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

// Helper to save qwiki.json safely
function save_config($configFile, $config) {
    return file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
}

// Helper to sanitize slug
function make_slug($text) {
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
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
            echo json_encode(['success' => false, 'error' => 'Book title is required']);
            exit;
        }

        // Check if book already exists
        foreach ($config['books'] as $b) {
            if ($b['id'] === $bookId) {
                echo json_encode(['success' => false, 'error' => 'Book with this ID already exists']);
                exit;
            }
        }

        // Auto-create folder if needed
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

    case 'create_markdown':
        if (empty($_SESSION['qwiki_admin'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $bookId = $_POST['bookId'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? "# {$title}\n\nWrite your documentation content here...";

        if (empty($bookId) || empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Book and title are required']);
            exit;
        }

        $slug = make_slug($title);
        $targetRelDir = 'content/' . $bookId;
        $targetAbsDir = __DIR__ . '/../' . $targetRelDir;

        // Auto-create directory structure if needed
        if (!is_dir($targetAbsDir)) {
            mkdir($targetAbsDir, 0755, true);
        }

        $targetRelFile = $targetRelDir . '/' . $slug . '.md';
        $targetAbsFile = __DIR__ . '/../' . $targetRelFile;

        if (file_put_contents($targetAbsFile, $content) === false) {
            echo json_encode(['success' => false, 'error' => 'Failed to create Markdown file']);
            exit;
        }

        // Update qwiki.json
        $added = false;
        foreach ($config['books'] as &$book) {
            if ($book['id'] === $bookId) {
                $book['chapters'][] = [
                    'title' => $title,
                    'slug' => $slug,
                    'type' => 'markdown',
                    'file' => $targetRelFile
                ];
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

        // Auto-create directory structure if needed
        if (!is_dir($targetAbsDir)) {
            mkdir($targetAbsDir, 0755, true);
        }

        $targetRelFile = $targetRelDir . '/' . $slug . '.' . $ext;
        $targetAbsFile = __DIR__ . '/../' . $targetRelFile;

        if (move_uploaded_file($_FILES['document']['tmp_name'], $targetAbsFile)) {
            foreach ($config['books'] as &$book) {
                if ($book['id'] === $bookId) {
                    $book['chapters'][] = [
                        'title' => $title,
                        'slug' => $slug,
                        'type' => ($ext === 'pdf') ? 'pdf' : 'markdown',
                        'file' => $targetRelFile
                    ];
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

        // Automatically ensure embedded=true parameter
        if (strpos($url, 'embedded=true') === false) {
            $url .= (strpos($url, '?') !== false) ? '&embedded=true' : '?embedded=true';
        }

        $slug = make_slug($title);

        foreach ($config['books'] as &$book) {
            if ($book['id'] === $bookId) {
                $book['chapters'][] = [
                    'title' => $title,
                    'slug' => $slug,
                    'type' => 'gdoc',
                    'url' => $url,
                    'editUrl' => $editUrl
                ];
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

        foreach ($config['books'] as &$book) {
            if ($book['id'] === $bookId) {
                $newChapters = [];
                foreach ($book['chapters'] as $ch) {
                    if ($ch['slug'] !== $slug) {
                        $newChapters[] = $ch;
                    }
                }
                $book['chapters'] = $newChapters;
                break;
            }
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

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
