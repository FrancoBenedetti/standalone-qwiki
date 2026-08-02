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

    case 'save_markdown':
        if (empty($_SESSION['qwiki_admin'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        $relFile = $_POST['file'] ?? '';
        $content = $_POST['content'] ?? '';
        
        // Security check: stay within project root
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

        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', $title))) . '-' . time();
        $targetRelDir = 'content/' . $bookId;
        $targetAbsDir = __DIR__ . '/../' . $targetRelDir;

        if (!is_dir($targetAbsDir)) {
            mkdir($targetAbsDir, 0755, true);
        }

        $targetRelFile = $targetRelDir . '/' . $slug . '.' . $ext;
        $targetAbsFile = __DIR__ . '/../' . $targetRelFile;

        if (move_uploaded_file($_FILES['document']['tmp_name'], $targetAbsFile)) {
            // Update qwiki.json
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
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
            echo json_encode(['success' => true, 'slug' => $slug]);
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

        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', $title))) . '-' . time();

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
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true, 'slug' => $slug]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
