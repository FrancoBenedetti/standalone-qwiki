<?php
// tests/test_html_page_extension.php

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

echo "Running HTML Page Extension & Error Handling Tests...\n\n";

// Set up temporary environment in sys_get_temp_dir()
$tempDir = sys_get_temp_dir() . '/qwiki_html_test_' . uniqid();
@mkdir($tempDir . '/content/getting-started', 0755, true);
@mkdir($tempDir . '/assets/extensions/page-html', 0755, true);

// Copy page-html extension files to temp dir
$origExtDir = __DIR__ . '/../assets/extensions/page-html';
foreach (scandir($origExtDir) as $f) {
    if ($f !== '.' && $f !== '..') {
        copy($origExtDir . '/' . $f, $tempDir . '/assets/extensions/page-html/' . $f);
    }
}

$initialConfig = [
    'title' => 'Test Wiki',
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

// Mock admin session
Auth::startSession();
$_SESSION['qwiki_user'] = ['username' => 'admin', 'role' => 'admin'];
$_SESSION['qwiki_instance'] = Auth::getInstanceIdentifier();

// ----------------------------------------------------
// 1. Test ensure_html_base_href behavior
// ----------------------------------------------------
ob_start();
require_once $tempDir . '/assets/extensions/page-html/handler.php';
ob_end_clean();

assertTest(function_exists('ensure_html_base_href'), 'ensure_html_base_href is defined at top level');

$htmlWithoutBase = "<!DOCTYPE html><html><head><title>Test</title></head><body><h1>Hello</h1></body></html>";
$processed1 = ensure_html_base_href($htmlWithoutBase, 'content/getting-started/test.html');
assertTest(strpos($processed1, '<base href="../../">') !== false, 'ensure_html_base_href injects ../../ for 2-depth path');

$htmlWithBase = "<!DOCTYPE html><html><head><base href=\"/custom/\"><title>Test</title></head><body></body></html>";
$processed2 = ensure_html_base_href($htmlWithBase, 'content/getting-started/test.html');
assertTest($processed2 === $htmlWithBase, 'ensure_html_base_href preserves pre-existing base href');

$htmlFragment = "<div>Fragment</div>";
$processed3 = ensure_html_base_href($htmlFragment, 'content/test.html');
assertTest(strpos($processed3, '<base href="../">') === 0, 'ensure_html_base_href prepends base href when no head tag exists');

// ----------------------------------------------------
// 2. Test create_html action via ExtensionManager
// ----------------------------------------------------
$htmlContent = "<!DOCTYPE html><html><head><title>Nitida Dossier</title></head><body><h1>Technical Dossier Nitida Wine Farm</h1><p>Estate report details.</p></body></html>";

$_POST = [
    'action' => 'create_html',
    'title' => 'Technical Dossier Nitida Wine Farm',
    'bookId' => 'getting-started',
    'content_base64' => base64_encode($htmlContent)
];

ob_start();
$handled = ExtensionManager::getInstance()->handleAction('create_html', $_POST);
$output = ob_get_clean();

assertTest($handled === true, 'ExtensionManager handles create_html action');

$response = json_decode($output, true);
assertTest(is_array($response) && !empty($response['success']), 'create_html returns successful response');
assertTest(($response['slug'] ?? '') === 'technical-dossier-nitida-wine-farm', 'Slug is properly generated');

$createdFile = $tempDir . '/' . ($response['file'] ?? '');
assertTest(file_exists($createdFile), 'Physical HTML file was created on disk');
$fileContents = file_get_contents($createdFile);
assertTest(strpos($fileContents, 'Technical Dossier Nitida Wine Farm') !== false, 'Created file contains original HTML content');
assertTest(strpos($fileContents, '<base href="../../">') !== false, 'Created file contains injected base href');

// Verify qwiki.json has the new chapter
$updatedConfig = json_decode(file_get_contents($tempDir . '/qwiki.json'), true);
$foundItem = null;
foreach ($updatedConfig['books'][0]['items'] as $item) {
    if (($item['slug'] ?? '') === 'technical-dossier-nitida-wine-farm') {
        $foundItem = $item;
        break;
    }
}
assertTest($foundItem !== null, 'New HTML chapter inserted into qwiki.json');
assertTest(($foundItem['type'] ?? '') === 'html', 'Chapter type is html');

// ----------------------------------------------------
// 3. Test save_html action via ExtensionManager
// ----------------------------------------------------
$updatedHtml = "<!DOCTYPE html><html><head><title>Nitida Dossier Updated</title></head><body><h1>Updated Content</h1></body></html>";

$_POST = [
    'action' => 'save_html',
    'file' => $response['file'],
    'content_base64' => base64_encode($updatedHtml)
];

ob_start();
$handledSave = ExtensionManager::getInstance()->handleAction('save_html', $_POST);
$outputSave = ob_get_clean();

assertTest($handledSave === true, 'ExtensionManager handles save_html action');
$saveResponse = json_decode($outputSave, true);
assertTest(!empty($saveResponse['success']), 'save_html returns successful response');

$savedContents = file_get_contents($createdFile);
assertTest(strpos($savedContents, 'Updated Content') !== false, 'Physical file updated with new content');

// ----------------------------------------------------
// 4. Test idempotence: multiple handler inclusions
// ----------------------------------------------------
$multipleIncludeSuccess = true;
try {
    $_POST['action'] = 'get_html';
    $_POST['file'] = $response['file'];
    ob_start();
    require $tempDir . '/assets/extensions/page-html/handler.php';
    ob_get_clean();
} catch (\Throwable $e) {
    $multipleIncludeSuccess = false;
}
assertTest($multipleIncludeSuccess, 'Multiple inclusions of handler.php do not throw redeclaration errors');

// ----------------------------------------------------
// Clean up temporary files
// ----------------------------------------------------
function cleanDir($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $f) {
        $path = $dir . '/' . $f;
        is_dir($path) ? cleanDir($path) : @unlink($path);
    }
    @rmdir($dir);
}
cleanDir($tempDir);

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
if ($failed > 0) exit(1);
