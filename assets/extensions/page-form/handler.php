<?php
/**
 * Form Page Extension Action Handler
 */
use Qwiki\Core\Auth;
use Qwiki\Core\Config;
use Qwiki\Core\Navigation;

$baseDir = Config::getBaseDir();
$action = $action ?? $_REQUEST['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';

if (!function_exists('form_find_target_folder')) {
    function form_find_target_folder(&$node, $targetId) {
        if (($node['id'] ?? '') === $targetId) {
            return $node['folder'] ?? $node['id'];
        }
        if (!empty($node['items'])) {
            foreach ($node['items'] as &$child) {
                if (isset($child['type']) && $child['type'] === 'folder') {
                    $found = form_find_target_folder($child, $targetId);
                    if ($found !== null) {
                        $parentFolder = $node['folder'] ?? $node['id'];
                        return $parentFolder . '/' . $found;
                    }
                }
            }
        }
        return null;
    }
}

if (!function_exists('form_insert_chapter')) {
    function form_insert_chapter(&$node, $targetId, $chapterData) {
        if (($node['id'] ?? '') === $targetId) {
            if (!isset($node['items'])) $node['items'] = [];
            $node['items'][] = $chapterData;
            return true;
        }
        if (!empty($node['items'])) {
            foreach ($node['items'] as &$child) {
                if (isset($child['type']) && $child['type'] === 'folder') {
                    if (form_insert_chapter($child, $targetId, $chapterData)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}

if (!function_exists('form_update_chapter_title')) {
    function form_update_chapter_title(&$node, $slug, $newTitle) {
        if (($node['slug'] ?? '') === $slug) {
            $node['title'] = $newTitle;
            return true;
        }
        if (!empty($node['items'])) {
            foreach ($node['items'] as &$child) {
                if (form_update_chapter_title($child, $slug, $newTitle)) {
                    return true;
                }
            }
        }
        return false;
    }
}

// --------------------------------------------------------------------------
// 1. PUBLIC / VIEWER ACTION: Submit Form Response
// --------------------------------------------------------------------------
if ($action === 'submit_form' || $action === 'ext_form_submit') {
    $file = trim($_POST['file'] ?? '');
    $fieldsData = $_POST['fields'] ?? [];
    $hp = trim($_POST['_qwiki_hp'] ?? '');
    $token = trim($_POST['_qwiki_t'] ?? '');

    if (empty($file)) {
        echo json_encode(['success' => false, 'error' => 'Missing form reference']);
        return;
    }

    $absolutePath = Config::safePath($baseDir, $file);
    if (!$absolutePath || !file_exists($absolutePath) || !preg_match('/\.form\.json$/i', $absolutePath)) {
        echo json_encode(['success' => false, 'error' => 'Form document not found']);
        return;
    }

    $schema = json_decode(file_get_contents($absolutePath), true);
    if (!is_array($schema)) {
        echo json_encode(['success' => false, 'error' => 'Invalid form configuration']);
        return;
    }

    // A. Anti-spam Honeypot Check: silently pretend success if filled by a bot
    if (!empty($hp)) {
        echo json_encode([
            'success' => true,
            'message' => $schema['successMessage'] ?? 'Thank you! Your response has been recorded.'
        ]);
        return;
    }

    // B. Anti-spam Timestamp Token Verification
    $config = Config::load();
    $secret = $config['adminPasswordHash'] ?? 'qwiki-form-token';
    if (!empty($token)) {
        $tokenParts = explode('.', $token);
        if (count($tokenParts) === 2) {
            $tokenTime = (int)$tokenParts[0];
            $tokenHash = $tokenParts[1];
            if (hash_hmac('sha256', (string)$tokenTime, $secret) === $tokenHash) {
                $elapsed = time() - $tokenTime;
                if ($elapsed < 2) {
                    echo json_encode(['success' => false, 'error' => 'Submission received too quickly. Please try again.']);
                    return;
                }
            }
        }
    }

    // C. Authentication requirement check
    $user = Auth::getCurrentUser();
    $isLoggedIn = !empty($user);
    if (!empty($schema['requireLogin']) && !$isLoggedIn) {
        echo json_encode(['success' => false, 'error' => 'You must be logged in to submit this form.']);
        return;
    }

    // D. Validation against field definitions
    $schemaFields = $schema['fields'] ?? [];
    $sanitized = [];

    foreach ($schemaFields as $sf) {
        $fId = $sf['id'] ?? '';
        $fLabel = $sf['label'] ?? $fId;
        $fReq = !empty($sf['required']);
        $fType = $sf['type'] ?? 'text';

        $val = $fieldsData[$fId] ?? null;

        if ($fReq) {
            if ($val === null || $val === '' || (is_array($val) && count($val) === 0)) {
                echo json_encode(['success' => false, 'error' => "Please fill in the required field: {$fLabel}"]);
                return;
            }
        }

        if ($val !== null && $val !== '') {
            if ($fType === 'email') {
                $trimmedEmail = trim($val);
                if (!filter_var($trimmedEmail, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'error' => "Please enter a valid email address for: {$fLabel}"]);
                    return;
                }
                $sanitized[$fId] = $trimmedEmail;
            } elseif (is_array($val)) {
                $cleanArr = [];
                foreach ($val as $subVal) {
                    $cleanArr[] = trim(strip_tags((string)$subVal));
                }
                $sanitized[$fId] = $cleanArr;
            } else {
                $sanitized[$fId] = trim(strip_tags((string)$val));
            }
        } else {
            $sanitized[$fId] = '';
        }
    }

    // E. Construct Atomic Record
    $submissionId = 'sub_' . bin2hex(random_bytes(6));
    $timestamp = date('Y-m-d H:i:s');
    $ipHash = substr(hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'), 0, 12);
    $submittedBy = $user['username'] ?? 'guest';

    $record = [
        'id' => $submissionId,
        'created_at' => $timestamp,
        'ip_hash' => $ipHash,
        'submitted_by' => $submittedBy,
        'data' => $sanitized
    ];

    $subFile = preg_replace('/\.form\.json$/i', '.submissions.json', $absolutePath);
    $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

    if (file_put_contents($subFile, $line, FILE_APPEND | LOCK_EX) === false) {
        echo json_encode(['success' => false, 'error' => 'Failed to write submission to disk. Check permissions.']);
        return;
    }

    // F. Optional Notifications (Email & Webhook)
    if (!empty($schema['notificationEmail']) && filter_var($schema['notificationEmail'], FILTER_VALIDATE_EMAIL)) {
        $subject = "[Qwiki Form] New Submission: " . ($schema['title'] ?? 'Form');
        $body = "A new form response was submitted on {$timestamp}.\n\n";
        $body .= "Form: " . ($schema['title'] ?? 'Form') . "\n";
        $body .= "Submitted By: {$submittedBy}\n\n";
        $body .= "--- Answers ---\n";
        foreach ($schemaFields as $sf) {
            $fId = $sf['id'] ?? '';
            $fLabel = $sf['label'] ?? $fId;
            $ans = $sanitized[$fId] ?? '';
            if (is_array($ans)) {
                $ans = implode(', ', $ans);
            }
            $body .= "• {$fLabel}: {$ans}\n";
        }
        $headers = "From: Qwiki <noreply@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . ">\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        @mail($schema['notificationEmail'], $subject, $body, $headers);
    }

    if (!empty($schema['webhookUrl']) && preg_match('#^https?://#i', $schema['webhookUrl'])) {
        $webhookPayload = json_encode([
            'event' => 'form_submission',
            'form_title' => $schema['title'] ?? '',
            'form_file' => $file,
            'submission' => $record
        ]);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($webhookPayload) . "\r\n",
                'content' => $webhookPayload,
                'timeout' => 3
            ]
        ]);
        @file_get_contents($schema['webhookUrl'], false, $ctx);
    }

    echo json_encode([
        'success' => true,
        'message' => $schema['successMessage'] ?? 'Thank you! Your response has been recorded.'
    ]);
    return;
}

// --------------------------------------------------------------------------
// 2. ADMIN ONLY ACTIONS
// --------------------------------------------------------------------------
if (!Auth::isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    return;
}

// Action: Create Form Document
if ($action === 'create_form' || $action === 'ext_form_create') {
    $title = trim($_POST['title'] ?? '');
    $bookId = $_POST['bookId'] ?? '';
    $schemaRaw = $_POST['schema_json'] ?? '';

    if (empty($title)) {
        echo json_encode(['success' => false, 'error' => 'Document title is required']);
        return;
    }

    $slug = Config::makeSlug($title);
    if (empty($slug)) {
        $slug = 'form-' . time();
    }

    $config = Config::load();
    $targetFolder = $bookId;
    foreach ($config['books'] as $b) {
        $resolved = form_find_target_folder($b, $bookId);
        if ($resolved !== null) {
            $targetFolder = $resolved;
            break;
        }
    }

    $contentDir = $baseDir . '/content/' . $targetFolder;
    if (!is_dir($contentDir)) {
        if (!@mkdir($contentDir, 0755, true)) {
            echo json_encode(['success' => false, 'error' => 'Cannot create folder ' . $contentDir]);
            return;
        }
    }

    $filePath = 'content/' . $targetFolder . '/' . $slug . '.form.json';
    $absolutePath = $baseDir . '/' . $filePath;

    $schema = json_decode($schemaRaw, true);
    if (!is_array($schema)) {
        $schema = [
            'title' => $title,
            'description' => trim($_POST['description'] ?? ''),
            'submitButtonText' => 'Submit',
            'successMessage' => 'Thank you! Your response has been recorded.',
            'requireLogin' => false,
            'allowMultiple' => true,
            'fields' => [
                [
                    'id' => 'name',
                    'label' => 'Your Name',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'Jane Doe'
                ],
                [
                    'id' => 'email',
                    'label' => 'Email Address',
                    'type' => 'email',
                    'required' => true,
                    'placeholder' => 'jane@example.com'
                ],
                [
                    'id' => 'message',
                    'label' => 'Message',
                    'type' => 'textarea',
                    'required' => true,
                    'placeholder' => 'Write your message here...'
                ]
            ]
        ];
    } else {
        $schema['title'] = $title;
        if (isset($_POST['description'])) {
            $schema['description'] = trim($_POST['description']);
        }
    }

    $jsonStr = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($absolutePath, $jsonStr, LOCK_EX) === false) {
        echo json_encode(['success' => false, 'error' => 'Failed to write form schema to disk']);
        return;
    }

    // Pre-create empty submissions file
    $subFile = preg_replace('/\.form\.json$/i', '.submissions.json', $absolutePath);
    if (!file_exists($subFile)) {
        file_put_contents($subFile, '', LOCK_EX);
    }

    $chapterData = [
        'title' => $title,
        'slug' => $slug,
        'type' => 'form',
        'file' => $filePath
    ];

    $inserted = false;
    foreach ($config['books'] as &$book) {
        if (form_insert_chapter($book, $bookId, $chapterData)) {
            $inserted = true;
            break;
        }
    }

    if ($inserted && Config::save($config)) {
        echo json_encode([
            'success' => true,
            'slug' => $slug,
            'bookId' => $bookId,
            'file' => $filePath
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update configuration tree']);
    }
    return;
}

// Action: Save Form Schema
if ($action === 'save_form_schema' || $action === 'ext_form_save_schema') {
    $file = trim($_POST['file'] ?? '');
    $schemaRaw = $_POST['schema_json'] ?? '';

    if (empty($file) || empty($schemaRaw)) {
        echo json_encode(['success' => false, 'error' => 'File path and schema are required']);
        return;
    }

    $absolutePath = Config::safePath($baseDir, $file);
    if (!$absolutePath || !file_exists($absolutePath) || !preg_match('/\.form\.json$/i', $absolutePath)) {
        echo json_encode(['success' => false, 'error' => 'Form document not found']);
        return;
    }

    if (Config::isChapterProtected($file)) {
        echo json_encode(['success' => false, 'error' => 'This document is protected and cannot be edited.']);
        return;
    }

    $schema = json_decode($schemaRaw, true);
    if (!is_array($schema)) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON schema payload']);
        return;
    }

    $jsonStr = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($absolutePath, $jsonStr, LOCK_EX) === false) {
        echo json_encode(['success' => false, 'error' => 'Failed to save form schema']);
        return;
    }

    // Sync title in qwiki.json if title changed
    if (!empty($schema['title'])) {
        $config = Config::load();
        $slug = basename($file, '.form.json');
        $updated = false;
        foreach ($config['books'] as &$book) {
            if (form_update_chapter_title($book, $slug, $schema['title'])) {
                $updated = true;
                break;
            }
        }
        if ($updated) {
            Config::save($config);
        }
    }

    echo json_encode(['success' => true]);
    return;
}

// Action: Get Form Schema
if ($action === 'get_form_schema' || $action === 'ext_form_get_schema') {
    $file = trim($_POST['file'] ?? $_GET['file'] ?? '');
    $absolutePath = Config::safePath($baseDir, $file);
    if (!$absolutePath || !file_exists($absolutePath)) {
        echo json_encode(['success' => false, 'error' => 'File not found']);
        return;
    }

    $schema = json_decode(file_get_contents($absolutePath), true);
    echo json_encode(['success' => true, 'schema' => $schema]);
    return;
}

// Action: Get Submissions
if ($action === 'get_form_submissions' || $action === 'ext_form_get_submissions') {
    $file = trim($_POST['file'] ?? $_GET['file'] ?? '');
    $absolutePath = Config::safePath($baseDir, $file);
    if (!$absolutePath || !file_exists($absolutePath)) {
        echo json_encode(['success' => false, 'error' => 'File not found']);
        return;
    }

    $schema = json_decode(file_get_contents($absolutePath), true);
    $subFile = preg_replace('/\.form\.json$/i', '.submissions.json', $absolutePath);

    $items = [];
    if (file_exists($subFile)) {
        $lines = file($subFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if (is_array($entry)) {
                    $items[] = $entry;
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'schema' => $schema,
        'submissions' => array_reverse($items),
        'total' => count($items)
    ]);
    return;
}

// Action: Delete Single Submission
if ($action === 'delete_form_submission' || $action === 'ext_form_delete_submission') {
    $file = trim($_POST['file'] ?? '');
    $subId = trim($_POST['id'] ?? '');

    $absolutePath = Config::safePath($baseDir, $file);
    if (!$absolutePath || !file_exists($absolutePath)) {
        echo json_encode(['success' => false, 'error' => 'File not found']);
        return;
    }

    $subFile = preg_replace('/\.form\.json$/i', '.submissions.json', $absolutePath);
    if (!file_exists($subFile)) {
        echo json_encode(['success' => false, 'error' => 'No submissions found']);
        return;
    }

    $lines = file($subFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $kept = [];
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if (is_array($entry) && ($entry['id'] ?? '') === $subId) {
            continue;
        }
        $kept[] = $line;
    }

    $newContent = count($kept) > 0 ? implode("\n", $kept) . "\n" : '';
    file_put_contents($subFile, $newContent, LOCK_EX);

    echo json_encode(['success' => true]);
    return;
}

// Action: Clear All Submissions
if ($action === 'clear_form_submissions' || $action === 'ext_form_clear_submissions') {
    $file = trim($_POST['file'] ?? '');
    $absolutePath = Config::safePath($baseDir, $file);
    if (!$absolutePath || !file_exists($absolutePath)) {
        echo json_encode(['success' => false, 'error' => 'File not found']);
        return;
    }

    $subFile = preg_replace('/\.form\.json$/i', '.submissions.json', $absolutePath);
    file_put_contents($subFile, '', LOCK_EX);

    echo json_encode(['success' => true]);
    return;
}

// Action: Export Submissions to CSV
if ($action === 'export_form_csv' || $action === 'ext_form_export_csv') {
    $file = trim($_GET['file'] ?? $_POST['file'] ?? '');
    $absolutePath = Config::safePath($baseDir, $file);
    if (!$absolutePath || !file_exists($absolutePath)) {
        http_response_code(404);
        echo "Form document not found";
        return;
    }

    $schema = json_decode(file_get_contents($absolutePath), true);
    $fields = $schema['fields'] ?? [];
    $subFile = preg_replace('/\.form\.json$/i', '.submissions.json', $absolutePath);

    $slug = basename($file, '.form.json');
    $filename = $slug . '-submissions-' . date('Y-m-d') . '.csv';

    if (!headers_sent()) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    $out = fopen('php://output', 'w');
    // Output UTF-8 BOM for Microsoft Excel compatibility
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Headers
    $headers = ['Submission ID', 'Date / Time', 'Submitted By', 'IP Hash'];
    $fieldMap = [];
    foreach ($fields as $f) {
        $fId = $f['id'] ?? '';
        $fLabel = $f['label'] ?? $fId;
        $headers[] = $fLabel;
        $fieldMap[] = $fId;
    }
    fputcsv($out, $headers);

    // Rows
    if (file_exists($subFile)) {
        $handle = fopen($subFile, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (empty($line)) continue;
                $entry = json_decode($line, true);
                if (!is_array($entry)) continue;

                $row = [
                    $entry['id'] ?? '',
                    $entry['created_at'] ?? '',
                    $entry['submitted_by'] ?? '',
                    $entry['ip_hash'] ?? ''
                ];

                $entryData = $entry['data'] ?? [];
                foreach ($fieldMap as $fId) {
                    $val = $entryData[$fId] ?? '';
                    if (is_array($val)) {
                        $row[] = implode(', ', $val);
                    } else {
                        $row[] = (string)$val;
                    }
                }
                fputcsv($out, $row);
            }
            fclose($handle);
        }
    }

    fclose($out);
    return;
}
