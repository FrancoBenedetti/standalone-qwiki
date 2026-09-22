<?php
/**
 * Test Gallery Upload, Selection, Insertion into Markdown & HTML Documents, and Usage Detection
 */

$testRoot = sys_get_temp_dir() . '/qwiki_gallery_test_' . uniqid();
@mkdir($testRoot, 0777, true);
@mkdir($testRoot . '/content/test-cat', 0777, true);
@mkdir($testRoot . '/uploads/images', 0777, true);
@mkdir($testRoot . '/assets', 0777, true);

// Symlink extensions into test root so ExtensionManager can discover them
@symlink(dirname(__DIR__) . '/assets/extensions', $testRoot . '/assets/extensions');

require_once __DIR__ . '/../lib/Core/Config.php';
use Qwiki\Core\Config;
Config::init($testRoot);

// Create valid qwiki.json in testRoot
$initialConfig = [
    'title' => 'Test Wiki',
    'defaultBook' => 'test-cat',
    'books' => [
        [
            'id' => 'test-cat',
            'title' => 'Test Category',
            'type' => 'folder',
            'items' => [
                [
                    'title' => 'Article Test',
                    'slug' => 'article-test',
                    'type' => 'markdown',
                    'file' => 'content/test-cat/article-test.md'
                ],
                [
                    'title' => 'Page Test',
                    'slug' => 'page-test',
                    'type' => 'html',
                    'file' => 'content/test-cat/page-test.html'
                ]
            ]
        ]
    ]
];
file_put_contents($testRoot . '/qwiki.json', json_encode($initialConfig));

require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/LockManager.php';
require_once __DIR__ . '/../lib/Parsedown.php';
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';

use Qwiki\Core\Auth;
use Qwiki\Core\ExtensionManager;

// Configure session properly via Auth
Auth::startSession();
$_SESSION['qwiki_instance'] = Auth::getInstanceIdentifier();
$_SESSION['qwiki_user'] = [
    'username' => 'admin',
    'role' => 'admin',
    'fullName' => 'Administrator'
];
$_SESSION['qwiki_admin'] = true;

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

echo "=== Testing Gallery Upload, Markdown Insertion & HTML Insertion ===\n\n";

$baseDir = Config::getBaseDir();
$extManager = ExtensionManager::getInstance();

// -------------------------------------------------------------
// STEP 1: Add image to the gallery via ext_gallery_upload
// -------------------------------------------------------------
echo "--- Step 1: Uploading Image to Gallery ---\n";

$tmpImgPath = tempnam(sys_get_temp_dir(), 'gallery_test_') . '.png';
$pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
file_put_contents($tmpImgPath, $pngData);

$_FILES = [
    'image' => [
        'name' => 'sample_chart_diagram.png',
        'type' => 'image/png',
        'tmp_name' => $tmpImgPath,
        'error' => UPLOAD_ERR_OK,
        'size' => strlen($pngData)
    ]
];

// Capture output of upload action
$_REQUEST['action'] = 'ext_gallery_upload';
ob_start();
$extManager->handleAction('ext_gallery_upload', []);
$uploadOutput = ob_get_clean();
$uploadRes = json_decode($uploadOutput, true);

assertTest(!empty($uploadRes['success']), 'Gallery image upload succeeded: ' . ($uploadRes['error'] ?? ''));
assertTest(!empty($uploadRes['image']['url']), 'Gallery upload returned image URL: ' . ($uploadRes['image']['url'] ?? 'none'));
assertTest(!empty($uploadRes['image']['filename']), 'Gallery upload returned filename: ' . ($uploadRes['image']['filename'] ?? 'none'));

$uploadedImg = $uploadRes['image'] ?? [];
$uploadedUrl = $uploadedImg['url'] ?? '';
$uploadedFileOnDisk = $baseDir . '/' . $uploadedUrl;

assertTest(file_exists($uploadedFileOnDisk), 'Physical image file exists on disk: ' . $uploadedUrl);

// Verify ext_gallery_list finds the uploaded image
$_REQUEST['action'] = 'ext_gallery_list';
ob_start();
$extManager->handleAction('ext_gallery_list', []);
$listOutput = ob_get_clean();
$listRes = json_decode($listOutput, true);

assertTest(!empty($listRes['success']), 'ext_gallery_list returned success');
$foundInList = false;
foreach ($listRes['images'] ?? [] as $img) {
    if (($img['filename'] ?? '') === ($uploadedImg['filename'] ?? '')) {
        $foundInList = true;
        break;
    }
}
assertTest($foundInList, 'Uploaded image appears in gallery list');

// -------------------------------------------------------------
// STEP 2: Add gallery image to a Markdown document
// -------------------------------------------------------------
echo "\n--- Step 2: Inserting Gallery Image into Markdown File ---\n";

$mdRelPath = 'content/test-cat/article-test.md';
$mdDiskPath = $baseDir . '/' . $mdRelPath;

$initialMd = "# Sample Article\n\nThis is an introduction to the project.\n";
file_put_contents($mdDiskPath, $initialMd);

// The gallery client script formats Alt text: cleanAltFromFilename("1725...-sample_chart_diagram.png") => "sample chart diagram"
// and formats snippet: ![sample chart diagram](uploads/images/...)
$mdSnippet = "![sample chart diagram]({$uploadedUrl})";
$updatedMd = $initialMd . "\n" . $mdSnippet . "\n\nAdditional text after diagram.\n";

// Execute save_markdown action in api/admin.php
$_POST = [
    'action' => 'save_markdown',
    'file' => $mdRelPath,
    'content' => $updatedMd,
    'content_base64' => base64_encode($updatedMd)
];
$_REQUEST['action'] = 'save_markdown';

ob_start();
require __DIR__ . '/../api/admin.php';
$saveMdOutput = ob_get_clean();
$saveMdRes = json_decode($saveMdOutput, true);

assertTest(!empty($saveMdRes['success']), 'save_markdown returned success: ' . ($saveMdRes['error'] ?? ''));

$savedMdContent = file_get_contents($mdDiskPath);
assertTest(strpos($savedMdContent, $mdSnippet) !== false, 'Markdown file on disk contains the gallery image snippet');

// Verify Parsedown renders it into an <img> tag with src and alt attributes
$parsedown = new Parsedown();
$renderedHtml = $parsedown->text($savedMdContent);
assertTest(strpos($renderedHtml, '<img') !== false && strpos($renderedHtml, $uploadedUrl) !== false, 'Parsedown rendered markdown into valid <img> tag with gallery URL');
assertTest(strpos($renderedHtml, 'alt="sample chart diagram"') !== false, 'Parsedown output contains correct alt attribute');

// -------------------------------------------------------------
// STEP 3: Add gallery image to an HTML document
// -------------------------------------------------------------
echo "\n--- Step 3: Inserting Gallery Image into HTML File ---\n";

$htmlRelPath = 'content/test-cat/page-test.html';
$htmlDiskPath = $baseDir . '/' . $htmlRelPath;

$initialHtml = "<!DOCTYPE html>\n<html>\n<head><title>HTML Page</title></head>\n<body>\n<h1>Dashboard</h1>\n<p>Welcome to the dashboard.</p>\n</body>\n</html>";
file_put_contents($htmlDiskPath, $initialHtml);

// In HTML editor mode, gallery formats: <img src="uploads/images/..." alt="sample chart diagram">
$htmlImgSnippet = "<img src=\"{$uploadedUrl}\" alt=\"sample chart diagram\">";
$updatedHtml = str_replace('<p>Welcome to the dashboard.</p>', "<p>Welcome to the dashboard.</p>\n<div class=\"chart-container\">{$htmlImgSnippet}</div>", $initialHtml);

$_POST = [
    'action' => 'save_html',
    'file' => $htmlRelPath,
    'content' => $updatedHtml
];
$_REQUEST['action'] = 'save_html';

ob_start();
$extManager->handleAction('save_html', $_POST);
$saveHtmlOutput = ob_get_clean();
$saveHtmlRes = json_decode($saveHtmlOutput, true);

assertTest(!empty($saveHtmlRes['success']), 'save_html returned success: ' . ($saveHtmlRes['error'] ?? ''));

$savedHtmlContent = file_get_contents($htmlDiskPath);
assertTest(strpos($savedHtmlContent, $htmlImgSnippet) !== false, 'HTML file on disk contains the gallery <img> snippet');

// -------------------------------------------------------------
// STEP 4: Verify Gallery Usage Detection finds both documents
// -------------------------------------------------------------
echo "\n--- Step 4: Verifying Gallery Usage Tracking ---\n";

$_GET['file'] = $uploadedUrl;
$_REQUEST['file'] = $uploadedUrl;
$_REQUEST['action'] = 'ext_gallery_check_usage';
ob_start();
$extManager->handleAction('ext_gallery_check_usage', ['file' => $uploadedUrl]);
$usageOutput = ob_get_clean();
$usageRes = json_decode($usageOutput, true);

assertTest(!empty($usageRes['success']), 'ext_gallery_check_usage returned success');
assertTest(!empty($usageRes['used']), 'Gallery tracks image as USED across documentation');
assertTest(($usageRes['count'] ?? 0) >= 2, 'Usage count reflects references in both Markdown and HTML files (found: ' . ($usageRes['count'] ?? 0) . ')');

$foundMdUsage = false;
$foundHtmlUsage = false;
foreach ($usageRes['documents'] ?? [] as $doc) {
    if (strpos($doc['file'] ?? '', 'article-test.md') !== false) {
        $foundMdUsage = true;
    }
    if (strpos($doc['file'] ?? '', 'page-test.html') !== false) {
        $foundHtmlUsage = true;
    }
}
assertTest($foundMdUsage, 'Usage tracker identified markdown file: article-test.md');
assertTest($foundHtmlUsage, 'Usage tracker identified HTML file: page-test.html');

// -------------------------------------------------------------
// CLEANUP: Clean up temporary test files
// -------------------------------------------------------------
echo "\n--- Cleanup ---\n";
@unlink($mdDiskPath);
@unlink($htmlDiskPath);
@unlink($uploadedFileOnDisk);
@unlink($tmpImgPath);
@unlink($testRoot . '/qwiki.json');
@unlink($testRoot . '/assets/extensions');
@rmdir($testRoot . '/assets');
@rmdir($testRoot . '/content/test-cat');
@rmdir($testRoot . '/content');
@rmdir($testRoot . '/uploads/images');
@rmdir($testRoot . '/uploads');
@rmdir($testRoot);

echo "Temporary test directory cleaned up.\n\n";

if ($failed === 0) {
    echo "🎉 ALL TESTS PASSED! ({$passed} / " . ($passed + $failed) . " assertions succeeded)\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED! ({$failed} failures)\n";
    exit(1);
}
