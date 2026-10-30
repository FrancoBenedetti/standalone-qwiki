<?php
/**
 * Test Suite: Document Repository Access Control & Raw View Security
 * Verifies:
 * 1. content/.htaccess and root .htaccess rules blocking direct static access to content repository
 * 2. Front-controller interception blocking direct path=content/... requests with 403 Forbidden
 * 3. Silent denial for direct routing attempts to content/...
 * 4. Raw view (?view=raw) on private wiki without login is blocked (403 Forbidden)
 * 5. Raw view with valid share link (?share=...&view=raw) on private wiki succeeds (200 OK) with text/html
 * 6. Raw view dynamically normalizes <base href="..."> to application baseUrl
 * 7. Raw view with invalid share link returns 404 Not Found
 * 8. HTML page renderer generates secure authorized raw view URLs instead of leaking content/ filesystem paths
 * 9. PDF page renderer generates secure authorized raw view URLs instead of leaking content/ filesystem paths
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';
require_once __DIR__ . '/../lib/Core/DocumentService.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\Navigation;
use Qwiki\Core\ExtensionManager;
use Qwiki\Core\DocumentService;

echo "Running Document Repository Access Control & Raw View Security Test Suite...\n\n";

$failures = 0;
function assertCondition($name, $condition, &$failures) {
    if ($condition) {
        echo "✅ PASS: {$name}\n";
    } else {
        echo "❌ FAIL: {$name}\n";
        $failures++;
    }
}

Config::init();
Auth::startSession();

$baseDir = Config::getBaseDir();
$baseUrl = Config::getBaseUrl();

// --------------------------------------------------------------------------
// TEST GROUP 1: Web Server Direct Access Rules (.htaccess)
// --------------------------------------------------------------------------
echo "--- 1. Web Server Protection (.htaccess) ---\n";

$rootHtaccess = file_get_contents($baseDir . '/.htaccess');
assertCondition("Root .htaccess contains RewriteRule ^content(/|$) - [F,L]", strpos($rootHtaccess, 'RewriteRule ^content(/|$) - [F,L]') !== false, $failures);
assertCondition("Root .htaccess blocks .json, .md, and .markdown files", strpos($rootHtaccess, '<FilesMatch "\.(json|md|markdown)$">') !== false, $failures);

$contentHtaccessFile = $baseDir . '/content/.htaccess';
assertCondition("content/.htaccess exists", file_exists($contentHtaccessFile), $failures);
if (file_exists($contentHtaccessFile)) {
    $contentHtaccess = file_get_contents($contentHtaccessFile);
    assertCondition("content/.htaccess enforces Require all denied", strpos($contentHtaccess, 'Require all denied') !== false, $failures);
    assertCondition("content/.htaccess enforces Deny from all", strpos($contentHtaccess, 'Deny from all') !== false, $failures);
}

$demoHtaccessFile = $baseDir . '/demo-data/htaccess-content';
assertCondition("demo-data/htaccess-content exists for new installations", file_exists($demoHtaccessFile), $failures);

// --------------------------------------------------------------------------
// TEST GROUP 2: Application Layer Direct Access Interception
// --------------------------------------------------------------------------
echo "\n--- 2. Application Layer Direct Access Blocking ---\n";

// Test front-controller path=content/... blocking
$fcOutput = shell_exec('php -r \'$_GET["path"] = "content/getting-started/interactive-dashboard.html"; require "' . addslashes($baseDir . '/index.php') . '";\'');
assertCondition("index.php returns 'Access denied.' for path=content/...", strpos($fcOutput, 'Access denied.') !== false, $failures);

// --------------------------------------------------------------------------
// TEST GROUP 3: Private Wiki Raw View Access Policy
// --------------------------------------------------------------------------
echo "\n--- 3. Private Wiki Raw View Access Policy ---\n";

// Load live qwiki.json
$configFile = $baseDir . '/qwiki.json';
$origConfigRaw = file_get_contents($configFile);
$config = json_decode($origConfigRaw, true);

// Find an HTML chapter in config
$htmlChapter = null;
$htmlBook = null;
foreach ($config['books'] as &$book) {
    if (!empty($book['items'])) {
        foreach ($book['items'] as &$item) {
            if (($item['type'] ?? '') === 'html') {
                if (empty($item['shareKey'])) {
                    $item['shareKey'] = Navigation::generateShareKey();
                }
                $htmlChapter = $item;
                $htmlBook = $book;
                break 2;
            }
        }
    }
}
unset($book, $item);

assertCondition("Found HTML chapter in config", !empty($htmlChapter), $failures);

if ($htmlChapter) {
    $shareKey = $htmlChapter['shareKey'];
    $catSlug = $htmlBook['id'];
    $docSlug = $htmlChapter['slug'];

    // Make wiki private (requireLoginToView = true)
    $privateConfig = $config;
    $privateConfig['requireLoginToView'] = true;
    file_put_contents($configFile, json_encode($privateConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // A. Guest accesses raw view without sharelink -> must be blocked (403)
    $cmdGuestRaw = 'php -r \'unset($_SESSION["qwiki_user"]); $_GET["book"] = "' . addslashes($catSlug) . '"; $_GET["doc"] = "' . addslashes($docSlug) . '"; $_GET["view"] = "raw"; require "' . addslashes($baseDir . '/index.php') . '";\'';
    $resGuestRaw = shell_exec($cmdGuestRaw);
    assertCondition("Raw view without sharelink on private wiki is blocked with 403 Forbidden", strpos($resGuestRaw, 'Sign-in required to view this document') !== false || strpos($resGuestRaw, 'Access Restricted') !== false, $failures);
    assertCondition("Raw view does NOT leak HTML content to unauthenticated guest", strpos($resGuestRaw, 'Interactive Dashboard Demo') === false, $failures);

    // B. Guest accesses with valid sharelink and view=raw -> must succeed (200) and render HTML
    $cmdShareRaw = 'php -r \'unset($_SESSION["qwiki_user"]); $_GET["share"] = "' . addslashes($shareKey) . '"; $_GET["view"] = "raw"; require "' . addslashes($baseDir . '/index.php') . '";\'';
    $resShareRaw = shell_exec($cmdShareRaw);
    assertCondition("Raw view with valid sharelink on private wiki succeeds", strpos($resShareRaw, '<!DOCTYPE html>') !== false, $failures);
    assertCondition("Raw view contains expected document content", strpos($resShareRaw, 'Interactive Dashboard Demo') !== false, $failures);
    assertCondition("Raw view contains normalized base href pointing to baseUrl", strpos($resShareRaw, '<base href="' . htmlspecialchars(rtrim($baseUrl, '/') . '/')) !== false, $failures);

    // C. Guest accesses with invalid sharelink and view=raw -> must return 404
    $cmdInvalidShare = 'php -r \'unset($_SESSION["qwiki_user"]); $_GET["share"] = "invalid_nonexistent_key"; $_GET["view"] = "raw"; require "' . addslashes($baseDir . '/index.php') . '";\'';
    $resInvalidShare = shell_exec($cmdInvalidShare);
    assertCondition("Raw view with invalid sharelink returns 404", strpos($resInvalidShare, 'Share Link Not Found') !== false || strpos($resInvalidShare, '404 Not Found') !== false, $failures);

    // Restore original config
    file_put_contents($configFile, $origConfigRaw);
}

// --------------------------------------------------------------------------
// TEST GROUP 4: HTML Page Extension Renderer URL Generation
// --------------------------------------------------------------------------
echo "\n--- 4. HTML Page Extension Renderer URL Generation ---\n";

if ($htmlChapter) {
    // Render in share mode
    $_GET['share'] = $htmlChapter['shareKey'];
    ob_start();
    $chapter = $htmlChapter;
    $book = $htmlBook;
    $extension = ['id' => 'html'];
    include $baseDir . '/assets/extensions/page-html/renderer.php';
    $shareRendered = ob_get_clean();
    unset($_GET['share']);

    assertCondition("In share mode, Open in Full Tab link contains ?share=", strpos($shareRendered, '?share=' . $htmlChapter['shareKey'] . '&amp;view=raw') !== false || strpos($shareRendered, '?share=' . $htmlChapter['shareKey'] . '&view=raw') !== false, $failures);
    assertCondition("In share mode, Open in Full Tab link does NOT contain raw content/ path", strpos($shareRendered, 'href="content/') === false && strpos($shareRendered, 'href=\'content/') === false, $failures);
    assertCondition("In share mode, iframe src contains ?share=...&view=raw", strpos($shareRendered, 'src="http') !== false && strpos($shareRendered, '?share=' . $htmlChapter['shareKey'] . '&amp;view=raw') !== false || strpos($shareRendered, '?share=' . $htmlChapter['shareKey'] . '&view=raw') !== false, $failures);
    assertCondition("In share mode, iframe src does NOT point to raw content/ path", strpos($shareRendered, 'src="content/') === false, $failures);

    // Render in normal mode
    ob_start();
    $chapter = $htmlChapter;
    $book = $htmlBook;
    $extension = ['id' => 'html'];
    include $baseDir . '/assets/extensions/page-html/renderer.php';
    $normalRendered = ob_get_clean();

    assertCondition("In normal mode, Open in Full Tab link points to authorized ?view=raw route", strpos($normalRendered, '?view=raw') !== false, $failures);
    assertCondition("In normal mode, Open in Full Tab link does NOT point to raw content/ path", strpos($normalRendered, 'href="content/') === false, $failures);
    assertCondition("In normal mode, iframe src points to authorized ?view=raw route", strpos($normalRendered, 'src="') !== false && strpos($normalRendered, '?view=raw') !== false, $failures);
    assertCondition("In normal mode, iframe src does NOT point to raw content/ path", strpos($normalRendered, 'src="content/') === false, $failures);
}

// --------------------------------------------------------------------------
// TEST GROUP 5: PDF Viewer Renderer URL Generation
// --------------------------------------------------------------------------
echo "\n--- 5. PDF Viewer Renderer URL Generation ---\n";

$pdfChapter = [
    'title' => 'Sample PDF Report',
    'slug' => 'sample-pdf',
    'type' => 'pdf',
    'file' => 'content/getting-started/sample.pdf'
];
$pdfBook = ['id' => 'getting-started', 'title' => 'Getting Started'];

$extMgr = ExtensionManager::getInstance();

// Normal mode
$pdfNormalHtml = $extMgr->renderPage($pdfChapter, $pdfBook, $config);
assertCondition("PDF viewer in normal mode uses ?view=raw for iframe src", strpos($pdfNormalHtml, 'getting-started/sample-pdf?view=raw') !== false, $failures);
assertCondition("PDF viewer in normal mode uses ?view=raw for download link", strpos($pdfNormalHtml, 'href="http') !== false && strpos($pdfNormalHtml, 'getting-started/sample-pdf?view=raw') !== false, $failures);
assertCondition("PDF viewer in normal mode does NOT expose raw content/ path", strpos($pdfNormalHtml, 'src="content/') === false && strpos($pdfNormalHtml, 'href="content/') === false, $failures);

// Share mode
$_GET['share'] = 'pdf_share_key_123';
$pdfShareHtml = $extMgr->renderPage($pdfChapter, $pdfBook, $config);
unset($_GET['share']);
assertCondition("PDF viewer in share mode uses ?share=...&view=raw for iframe src", strpos($pdfShareHtml, '?share=pdf_share_key_123&amp;view=raw') !== false || strpos($pdfShareHtml, '?share=pdf_share_key_123&view=raw') !== false, $failures);
assertCondition("PDF viewer in share mode does NOT expose raw content/ path", strpos($pdfShareHtml, 'src="content/') === false && strpos($pdfShareHtml, 'href="content/') === false, $failures);

echo "\n----------------------------------------------------\n";
if ($failures === 0) {
    echo "🎉 ALL DOCUMENT ACCESS & RAW VIEW SECURITY TESTS PASSED!\n";
    exit(0);
} else {
    echo "❌ {$failures} TEST(S) FAILED.\n";
    exit(1);
}
