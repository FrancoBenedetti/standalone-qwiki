<?php
// tests/test_form_page_extension.php

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/LockManager.php';
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\ExtensionManager;

$passed = 0;
$failed = 0;

function assertTest($condition, $name) {
    global $passed, $failed;
    if ($condition) {
        echo "✅ PASS: {$name}\n";
        $passed++;
    } else {
        echo "❌ FAIL: {$name}\n";
        $failed++;
    }
}

echo "Running Native Flat-File Form Extension Tests...\n\n";

// Set up temporary environment in sys_get_temp_dir()
$tempDir = sys_get_temp_dir() . '/qwiki_form_test_' . uniqid();
@mkdir($tempDir . '/content/getting-started', 0755, true);
@mkdir($tempDir . '/assets/extensions/page-form', 0755, true);

// Copy page-form extension files to temp dir
$origExtDir = __DIR__ . '/../assets/extensions/page-form';
foreach (scandir($origExtDir) as $f) {
    if ($f !== '.' && $f !== '..') {
        copy($origExtDir . '/' . $f, $tempDir . '/assets/extensions/page-form/' . $f);
    }
}

$initialConfig = [
    'title' => 'Test Wiki',
    'adminPasswordHash' => password_hash('admin-secret', PASSWORD_DEFAULT),
    'books' => [
        [
            'id' => 'getting-started',
            'title' => 'Getting Started',
            'type' => 'folder',
            'items' => []
        ]
    ]
];
file_put_contents($tempDir . '/qwiki.json', json_encode($initialConfig, JSON_PRETTY_PRINT));

// Configure base directory override for tests
Config::init($tempDir);

// ----------------------------------------------------
// 1. Test Extension Discovery
// ----------------------------------------------------
$extManager = ExtensionManager::getInstance();
$pageTypes = $extManager->getPageTypes();

assertTest(isset($pageTypes['form']), 'ExtensionManager discovers page-form as "form" page type');
assertTest(($pageTypes['form']['type'] ?? '') === 'page_type', 'Extension registered with type page_type');
assertTest(!empty($pageTypes['form']['badge']), 'Extension defines badge in manifest');

$assets = $extManager->getFrontendAssets();
$hasFormStyle = false;
foreach ($assets['styles'] as $s) {
    if (strpos($s, 'page-form/style.css') !== false) $hasFormStyle = true;
}
$hasFormScript = false;
foreach ($assets['scripts'] as $sc) {
    if (strpos($sc, 'page-form/script.js') !== false) $hasFormScript = true;
}
assertTest($hasFormStyle, 'Extension stylesheet is loaded in frontend assets');
assertTest($hasFormScript, 'Extension script is loaded in frontend assets');

// ----------------------------------------------------
// 2. Test Search Extractor
// ----------------------------------------------------
$sampleSchema = [
    'title' => 'Community Feedback Survey',
    'description' => 'Please provide feedback on documentation',
    'fields' => [
        ['id' => 'name', 'label' => 'Full Name', 'type' => 'text'],
        ['id' => 'rating', 'label' => 'Satisfaction Level', 'options' => ['High', 'Medium', 'Low']]
    ]
];
file_put_contents($tempDir . '/content/getting-started/sample.form.json', json_encode($sampleSchema));

$searchExtracted = $extManager->extractSearchableText([
    'type' => 'form',
    'file' => 'content/getting-started/sample.form.json'
], $tempDir);

assertTest(strpos($searchExtracted, 'Feedback') !== false, 'Search extractor finds form title');
assertTest(strpos($searchExtracted, 'Satisfaction') !== false, 'Search extractor finds field labels');
assertTest(strpos($searchExtracted, 'High') !== false, 'Search extractor finds select/radio options');

// ----------------------------------------------------
// 3. Test Handler Action: Form Creation (Admin Check)
// ----------------------------------------------------
// Non-admin request
Auth::startSession();
$_SESSION = []; // logged out

$_POST = [
    'action' => 'ext_form_create',
    'bookId' => 'getting-started',
    'title' => 'Visitor Contact Form'
];
ob_start();
$resUnauthorized = $extManager->handleAction('ext_form_create', $_POST);
$outUnauth = json_decode(ob_get_clean(), true);

assertTest($resUnauthorized === true, 'Handler intercepts ext_form_create');
assertTest($outUnauth['success'] === false && $outUnauth['error'] === 'Unauthorized', 'ext_form_create blocks non-admin users');

// Log in as Admin
$_SESSION['qwiki_user'] = ['username' => 'admin', 'role' => 'admin'];
$_SESSION['qwiki_instance'] = Auth::getInstanceIdentifier();

$_POST = [
    'action' => 'ext_form_create',
    'bookId' => 'getting-started',
    'title' => 'Visitor Contact Form',
    'schema_json' => json_encode($sampleSchema)
];
ob_start();
$extManager->handleAction('ext_form_create', $_POST);
$outCreate = json_decode(ob_get_clean(), true);

assertTest($outCreate['success'] === true, 'ext_form_create succeeds for admin');
$createdFile = $tempDir . '/' . ($outCreate['file'] ?? '');
assertTest(file_exists($createdFile), 'Form schema file created on disk');

$subFile = preg_replace('/\.form\.json$/i', '.submissions.json', $createdFile);
assertTest(file_exists($subFile), 'Empty submissions store file initialized on disk');

$updatedConfig = Config::load();
$chapterFound = false;
foreach ($updatedConfig['books'][0]['items'] as $it) {
    if (($it['slug'] ?? '') === 'visitor-contact-form' && ($it['type'] ?? '') === 'form') {
        $chapterFound = true;
    }
}
assertTest($chapterFound, 'New form chapter registered in qwiki.json');

// ----------------------------------------------------
// 4. Test Public Form Submission & Anti-Spam
// ----------------------------------------------------
// Log out admin
$_SESSION = [];

// A. Spam Honeypot Trap
$_POST = [
    'action' => 'ext_form_submit',
    'file' => $outCreate['file'],
    '_qwiki_hp' => 'I am a spam bot',
    'fields' => ['name' => 'Spammer']
];
ob_start();
$extManager->handleAction('ext_form_submit', $_POST);
$outHoneypot = json_decode(ob_get_clean(), true);

assertTest($outHoneypot['success'] === true, 'Honeypot returns silent success to deceive bot');
$subLines = file($subFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
assertTest(count($subLines) === 0, 'Honeypot submission is NOT saved to disk');

// B. Valid Public Submission
$_POST = [
    'action' => 'ext_form_submit',
    'file' => $outCreate['file'],
    '_qwiki_hp' => '',
    'fields' => [
        'name' => 'Alice Smith',
        'rating' => 'High'
    ]
];
ob_start();
$extManager->handleAction('ext_form_submit', $_POST);
$outSubmit = json_decode(ob_get_clean(), true);

assertTest($outSubmit['success'] === true, 'ext_form_submit succeeds for valid visitor submission');
$subLines = file($subFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
assertTest(count($subLines) === 1, 'Submission successfully saved to .submissions.json');

$record = json_decode($subLines[0], true);
assertTest(($record['data']['name'] ?? '') === 'Alice Smith', 'Submitted data parsed correctly');
assertTest(($record['submitted_by'] ?? '') === 'guest', 'Submitter recorded as guest');

// ----------------------------------------------------
// 5. Test Admin Submissions Query & Delete
// ----------------------------------------------------
// As guest: should fail
$_GET = ['action' => 'ext_form_get_submissions', 'file' => $outCreate['file']];
$_REQUEST = $_GET;
ob_start();
$extManager->handleAction('ext_form_get_submissions', $_GET);
$outGuestQuery = json_decode(ob_get_clean(), true);
assertTest($outGuestQuery['success'] === false, 'Guest cannot view form submissions');

// Log back in as admin
$_SESSION['qwiki_user'] = ['username' => 'admin', 'role' => 'admin'];
$_SESSION['qwiki_instance'] = Auth::getInstanceIdentifier();

ob_start();
$extManager->handleAction('ext_form_get_submissions', $_GET);
$outAdminQuery = json_decode(ob_get_clean(), true);
assertTest($outAdminQuery['success'] === true && count($outAdminQuery['submissions']) === 1, 'Admin can retrieve submissions');

// Delete submission
$subId = $record['id'];
$_POST = [
    'file' => $outCreate['file'],
    'id' => $subId
];
ob_start();
$extManager->handleAction('ext_form_delete_submission', $_POST);
$outDelete = json_decode(ob_get_clean(), true);
assertTest($outDelete['success'] === true, 'ext_form_delete_submission succeeds');

$remainingLines = file($subFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
assertTest(count($remainingLines) === 0, 'Submission successfully deleted from disk');

// ----------------------------------------------------
// 6. Test CSV Export & Clear Submissions
// ----------------------------------------------------
// Submit another entry first
$_POST = [
    'action' => 'ext_form_submit',
    'file' => $outCreate['file'],
    '_qwiki_hp' => '',
    'fields' => [
        'name' => 'Bob Builder',
        'rating' => 'Medium'
    ]
];
ob_start();
$extManager->handleAction('ext_form_submit', $_POST);
ob_end_clean();

// Export CSV
$_GET = [
    'action' => 'ext_form_export_csv',
    'file' => $outCreate['file']
];
$_REQUEST = $_GET;
ob_start();
$extManager->handleAction('ext_form_export_csv', $_GET);
$csvOutput = ob_get_clean();

assertTest(strpos($csvOutput, "\xEF\xBB\xBF") === 0, 'CSV export includes UTF-8 BOM');
assertTest(strpos($csvOutput, 'Full Name') !== false, 'CSV export includes field label header');
assertTest(strpos($csvOutput, 'Bob Builder') !== false, 'CSV export includes submitted respondent data');

// Clear all submissions
$_POST = [
    'action' => 'ext_form_clear_submissions',
    'file' => $outCreate['file']
];
ob_start();
$extManager->handleAction('ext_form_clear_submissions', $_POST);
$outClear = json_decode(ob_get_clean(), true);
assertTest($outClear['success'] === true, 'ext_form_clear_submissions succeeds');
assertTest(filesize($subFile) === 0, 'Submissions file is cleared to 0 bytes');

// ----------------------------------------------------
// 7. Test Save Form Schema & Title Synchronization
// ----------------------------------------------------
$updatedSchema = $sampleSchema;
$updatedSchema['title'] = 'Updated Contact Form';
$_POST = [
    'action' => 'ext_form_save_schema',
    'file' => $outCreate['file'],
    'schema_json' => json_encode($updatedSchema)
];
ob_start();
$extManager->handleAction('ext_form_save_schema', $_POST);
$outSaveSchema = json_decode(ob_get_clean(), true);
assertTest($outSaveSchema['success'] === true, 'ext_form_save_schema succeeds');

$savedJson = json_decode(file_get_contents($createdFile), true);
assertTest($savedJson['title'] === 'Updated Contact Form', 'Form schema title updated on disk');

$cfgAfterSave = Config::load();
$chapterTitleUpdated = false;
foreach ($cfgAfterSave['books'][0]['items'] as $it) {
    if (($it['slug'] ?? '') === 'visitor-contact-form' && ($it['title'] ?? '') === 'Updated Contact Form') {
        $chapterTitleUpdated = true;
    }
}
assertTest($chapterTitleUpdated, 'Chapter title synchronized in qwiki.json');
$it = new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS);
$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
foreach ($files as $file) {
    if ($file->isDir()) {
        rmdir($file->getRealPath());
    } else {
        unlink($file->getRealPath());
    }
}
rmdir($tempDir);

echo "\nTest Results: {$passed} passed, {$failed} failed.\n";
if ($failed > 0) {
    exit(1);
}
