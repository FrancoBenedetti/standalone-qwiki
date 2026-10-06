<?php
/**
 * Test Suite: Federated Remote Documents via Sharelinks
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';
require_once __DIR__ . '/../lib/Core/RemoteContentManager.php';
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\Navigation;
use Qwiki\Core\RemoteContentManager;
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

echo "Running Federated Remote Documents & Sharelink Test Suite...\n\n";

// =========================================================================
// 1. SSRF & URL Safety Validation Tests
// =========================================================================
echo "--- Testing SSRF & URL Safety ---\n";

assertTest(RemoteContentManager::validateUrlSafety('http://127.0.0.1/test') === false, 'Blocks IPv4 loopback (127.0.0.1)');
assertTest(RemoteContentManager::validateUrlSafety('http://localhost/test') === false, 'Blocks localhost hostname');
assertTest(RemoteContentManager::validateUrlSafety('http://169.254.169.254/latest/meta-data') === false, 'Blocks link-local/cloud metadata (169.254.169.254)');
assertTest(RemoteContentManager::validateUrlSafety('http://10.0.0.1/private') === false, 'Blocks RFC 1918 Class A private IP (10.0.0.1)');
assertTest(RemoteContentManager::validateUrlSafety('http://192.168.1.1/router') === false, 'Blocks RFC 1918 Class C private IP (192.168.1.1)');
assertTest(RemoteContentManager::validateUrlSafety('http://172.16.0.1/corp') === false, 'Blocks RFC 1918 Class B private IP (172.16.0.1)');
assertTest(RemoteContentManager::validateUrlSafety('ftp://example.com/file') === false, 'Blocks non-HTTP/HTTPS protocols (ftp)');
assertTest(RemoteContentManager::validateUrlSafety('javascript:alert(1)') === false, 'Blocks javascript pseudo-protocol');
assertTest(RemoteContentManager::validateUrlSafety('data:text/html,test') === false, 'Blocks data URI');
assertTest(RemoteContentManager::validateUrlSafety('') === false, 'Blocks empty URL');
assertTest(RemoteContentManager::validateUrlSafety('https://8.8.8.8/test') === true, 'Allows valid public IPv4 address (8.8.8.8)');
if (gethostbyname('google.com') !== 'google.com') {
    assertTest(RemoteContentManager::validateUrlSafety('https://google.com/test') === true, 'Allows valid public domain (google.com)');
} else {
    assertTest(RemoteContentManager::isPublicIp('142.250.190.46') === true, 'Validates public domain IP address (142.250.190.46)');
}

// =========================================================================
// 2. Relative Image & Asset Rewriting Tests
// =========================================================================
echo "\n--- Testing Relative Asset Rewriting ---\n";

$origin = 'https://origin.example.com/wiki';

$mdInput = "Here is a diagram: ![Architecture](./assets/diagram.png)\nAnd another: ![Flow](images/flow.png)\nAnd external: ![Ext](https://cdn.example.com/logo.png)";
$mdRewritten = RemoteContentManager::rewriteRelativeAssets($mdInput, $origin);

assertTest(strpos($mdRewritten, '![Architecture](https://origin.example.com/wiki/assets/diagram.png)') !== false, 'Rewrites ./assets/diagram.png to origin URL');
assertTest(strpos($mdRewritten, '![Flow](https://origin.example.com/wiki/images/flow.png)') !== false, 'Rewrites images/flow.png to origin URL');
assertTest(strpos($mdRewritten, '![Ext](https://cdn.example.com/logo.png)') !== false, 'Preserves absolute external image URL');

$htmlInput = '<img src="./images/test.jpg" alt="test"> and <img src="https://cdn.net/pic.png">';
$htmlRewritten = RemoteContentManager::rewriteRelativeAssets($htmlInput, $origin);
assertTest(strpos($htmlRewritten, 'src="https://origin.example.com/wiki/images/test.jpg"') !== false, 'Rewrites HTML <img> tag relative src');
assertTest(strpos($htmlRewritten, 'src="https://cdn.net/pic.png"') !== false, 'Preserves HTML <img> tag external src');

// =========================================================================
// 3. Cache Management & Stale-While-Revalidate Fallback
// =========================================================================
echo "\n--- Testing Cache Management & Fallback ---\n";

$tempDir = sys_get_temp_dir() . '/qwiki_remote_test_' . uniqid();
@mkdir($tempDir . '/uploads/cache', 0755, true);
Config::init($tempDir);

$testUrl = 'https://origin.example.com/wiki/?share=testkey123';
$cacheFile = RemoteContentManager::getCacheFilePath($testUrl);

assertTest(strpos($cacheFile, 'remote_' . md5($testUrl) . '.json') !== false, 'Calculates deterministic cache file path');

// Simulate fresh cached payload
$mockPayload = [
    'success' => true,
    'title' => 'Remote Architecture Spec',
    'slug' => 'remote-arch-spec',
    'type' => 'markdown',
    'content' => "# Remote Architecture Spec\n\nThis is native content.",
    'description' => 'Shared from origin wiki',
    'origin' => 'https://origin.example.com',
    'cached_at' => time(),
    'is_stale' => false
];
file_put_contents($cacheFile, json_encode($mockPayload));

// Fetch from cache (within TTL)
$cachedFetch = RemoteContentManager::fetchRemoteDocument($testUrl, 3600);
assertTest($cachedFetch['success'] === true, 'Successfully loads valid cached document');
assertTest($cachedFetch['from_cache'] === true, 'Identifies document loaded from cache');
assertTest($cachedFetch['title'] === 'Remote Architecture Spec', 'Cached title matches payload');

// Simulate expired cache + unreachable server fallback
$stalePayload = $mockPayload;
$stalePayload['cached_at'] = time() - 7200; // 2 hours old
file_put_contents($cacheFile, json_encode($stalePayload));

// Fetching invalid URL with force refresh should fallback to stale cache
$unreachableUrl = $testUrl;
// Since origin.example.com will fail or return invalid for format=json, fetch should fallback to stale
$staleFetch = RemoteContentManager::fetchRemoteDocument($unreachableUrl, 3600, true);
assertTest($staleFetch['success'] === true, 'Serves stale document when remote is unreachable');
assertTest($staleFetch['is_stale'] === true, 'Flags response as is_stale: true');

// Clean up cache file
@unlink($cacheFile);

// =========================================================================
// 4. Search Text Extraction & Cache Warming Tests
// =========================================================================
echo "\n--- Testing Search Text Extraction & Cache Warming ---\n";

$cachedChapter = [
    'title' => 'Remote Architecture Spec',
    'slug' => 'remote-arch-spec',
    'type' => 'remote',
    'url' => $testUrl
];

// Write cache file again
file_put_contents($cacheFile, json_encode($mockPayload));

$extracted = RemoteContentManager::extractSearchableText($cachedChapter);
assertTest(strpos($extracted, 'Remote Architecture Spec') !== false, 'Extracts title from cached remote document for search');
assertTest(strpos($extracted, 'This is native content') !== false, 'Extracts body text from cached remote document for search');

// Test ExtensionManager integration
$extManager = ExtensionManager::getInstance();
$extExtracted = $extManager->extractSearchableText($cachedChapter, $tempDir);
assertTest(strpos($extExtracted, 'This is native content') !== false, 'ExtensionManager delegates to RemoteContentManager for search extraction');

@unlink($cacheFile);

// =========================================================================
// 5. Producer Side (JSON API on Share Links)
// =========================================================================
echo "\n--- Testing Producer Sharelink JSON Content Negotiation ---\n";

$producerDir = sys_get_temp_dir() . '/qwiki_producer_test_' . uniqid();
@mkdir($producerDir . '/content/docs', 0755, true);
@mkdir($producerDir . '/uploads/cache', 0755, true);
Config::init($producerDir);

$docContent = "# Single Source of Truth\n\nThis document is hosted on the origin wiki.";
file_put_contents($producerDir . '/content/docs/ssot.md', $docContent);

$shareKey = 'prod12345678';
$producerConfig = [
    'title' => 'Origin Wiki',
    'books' => [
        [
            'id' => 'docs',
            'title' => 'Documentation',
            'type' => 'folder',
            'items' => [
                [
                    'title' => 'SSOT Document',
                    'slug' => 'ssot-document',
                    'file' => 'content/docs/ssot.md',
                    'type' => 'markdown',
                    'shareKey' => $shareKey,
                    'publicShareable' => true
                ],
                [
                    'title' => 'Private Document',
                    'slug' => 'private-document',
                    'file' => 'content/docs/ssot.md',
                    'type' => 'markdown',
                    'shareKey' => 'privatekey123',
                    'publicShareable' => false
                ]
            ]
        ]
    ]
];
file_put_contents($producerDir . '/qwiki.json', json_encode($producerConfig, JSON_PRETTY_PRINT));

// Function to simulate index.php share link call
function simulateShareLinkRequest($baseDir, $shareKey, $format = 'json', $acceptHeader = '') {
    $cmd = sprintf(
        'php -r \'%s\'',
        sprintf(
            '$_GET = ["share" => "%s", "format" => "%s"]; $_SERVER["HTTP_ACCEPT"] = "%s"; $_SERVER["REQUEST_URI"] = "/"; $_SERVER["SCRIPT_NAME"] = "/index.php"; define("QWIKI_BASE_DIR", "%s"); require "%s/index.php";',
            addslashes($shareKey),
            addslashes($format),
            addslashes($acceptHeader),
            addslashes($baseDir),
            addslashes(dirname(__DIR__))
        )
    );
    $output = shell_exec($cmd);
    return json_decode($output, true);
}

$shareRes = simulateShareLinkRequest($producerDir, $shareKey, 'json');
assertTest(!empty($shareRes['success']), 'Producer returns success: true for valid share key');
assertTest(($shareRes['title'] ?? '') === 'SSOT Document', 'Producer returns correct document title');
assertTest(($shareRes['type'] ?? '') === 'markdown', 'Producer returns document type');
assertTest(strpos($shareRes['content'] ?? '', 'Single Source of Truth') !== false, 'Producer returns raw document markdown content');
assertTest(($shareRes['readOnly'] ?? false) === true, 'Producer returns readOnly: true flag');

// Test non-existent share key
$notFoundRes = simulateShareLinkRequest($producerDir, 'nonexistentkey', 'json');
assertTest(empty($notFoundRes['success']) && ($notFoundRes['error'] ?? '') === 'Share link not found', 'Producer returns 404/error for non-existent key');

// Test restricted share key
$restrictedRes = simulateShareLinkRequest($producerDir, 'privatekey123', 'json');
assertTest(empty($restrictedRes['success']) && ($restrictedRes['error'] ?? '') === 'Share link is restricted', 'Producer returns 403/restricted for private document');

// =========================================================================
// 6. Admin API Integration: add_remote & save_markdown Guard
// =========================================================================
echo "\n--- Testing Admin API add_remote & Save Guard ---\n";

function callAdminApi($baseDir, array $post) {
    $script = sprintf('
        define("QWIKI_BASE_DIR", "%s");
        define("QWIKI_TEST_ALLOW_LOCALHOST", true);
        require_once "%s/lib/Core/Config.php";
        require_once "%s/lib/Core/Auth.php";
        \Qwiki\Core\Config::init("%s");
        \Qwiki\Core\Auth::startSession();
        $_SESSION["qwiki_user"] = ["username" => "admin", "role" => "admin"];
        $_POST = %s;
        $_REQUEST = $_POST;
        ob_start();
        require "%s/api/admin.php";
        $out = ob_get_clean();
        echo $out;
    ', addslashes($baseDir), addslashes(dirname(__DIR__)), addslashes(dirname(__DIR__)), addslashes($baseDir), var_export($post, true), addslashes(dirname(__DIR__)));

    $cmd = 'php -r ' . escapeshellarg($script);
    $output = shell_exec($cmd);
    return json_decode($output, true);
}

// 1. Add remote document via admin API
$addRes = callAdminApi($producerDir, [
    'action' => 'add_remote',
    'bookId' => 'docs',
    'title' => 'External Federated Guidelines',
    'url' => 'https://example.com/wiki/?share=test',
    'description' => 'Federated guidelines doc'
]);

assertTest(!empty($addRes['success']), 'Admin API successfully creates remote document');
$updatedConfig = json_decode(file_get_contents($producerDir . '/qwiki.json'), true);
$createdNode = null;
foreach ($updatedConfig['books'][0]['items'] as $item) {
    if (($item['slug'] ?? '') === ($addRes['slug'] ?? '')) {
        $createdNode = $item;
        break;
    }
}
assertTest($createdNode !== null, 'Remote document node added to qwiki.json');
assertTest(($createdNode['type'] ?? '') === 'remote', 'Remote document node has type="remote"');
assertTest(!empty($createdNode['readOnly']), 'Remote document node enforces readOnly=true');

// 2. Try saving markdown on remote document (Must be rejected)
$saveRes = callAdminApi($producerDir, [
    'action' => 'save_markdown',
    'slug' => $createdNode['slug'],
    'file' => 'some/path.md',
    'content' => 'Hacked content'
]);
assertTest(empty($saveRes['success']), 'save_markdown rejects edits to remote documents');
assertTest(strpos($saveRes['error'] ?? '', 'Single Source of Truth') !== false, 'save_markdown cites Single Source of Truth rejection message');

// =========================================================================
// Cleanup
// =========================================================================
function rmDirRecursive($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? rmDirRecursive($path) : @unlink($path);
    }
    @rmdir($dir);
}
rmDirRecursive($tempDir);
rmDirRecursive($producerDir);

echo "\n----------------------------------------\n";
echo "Test Suite Finished: {$passed} passed, {$failed} failed.\n";
if ($failed > 0) {
    exit(1);
}
