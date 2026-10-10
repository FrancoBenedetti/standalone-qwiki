<?php
/**
 * Test Suite: Headless HTML Publishing & Share Key Generation (CR-QWIKI-2026-01)
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';

use Qwiki\Core\Config;
use Qwiki\Core\Navigation;

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

echo "Running Headless HTML Publishing & Share Key Test Suite...\n\n";

// Set up temporary environment
$tempDir = sys_get_temp_dir() . '/qwiki_publish_test_' . uniqid();
@mkdir($tempDir . '/content/grower-guides/western-cape', 0755, true);

$apiKey = 'secret-publisher-token-2026';
$initialConfig = [
    'title' => 'Grower Wiki',
    'publishApiKey' => $apiKey,
    'books' => [
        [
            'id' => 'grower-guides',
            'title' => 'Grower Guides',
            'type' => 'folder',
            'items' => [
                [
                    'id' => 'western-cape',
                    'title' => 'Western Cape Region',
                    'type' => 'folder',
                    'folder' => 'grower-guides/western-cape',
                    'items' => []
                ]
            ]
        ]
    ]
];
file_put_contents($tempDir . '/qwiki.json', json_encode($initialConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

function callPublishApi($tempDir, array $post, array $server = []) {
    $script = sprintf('
        require_once "%s/lib/Core/Config.php";
        require_once "%s/lib/Core/Navigation.php";
        \Qwiki\Core\Config::init("%s");
        $_POST = %s;
        $_SERVER = array_merge($_SERVER, %s);
        $_REQUEST = $_POST;
        include "%s/api/publish.php";
    ',
    addslashes(realpath(__DIR__ . '/..')),
    addslashes(realpath(__DIR__ . '/..')),
    addslashes($tempDir),
    var_export($post, true),
    var_export($server, true),
    addslashes(realpath(__DIR__ . '/..'))
    );

    $cmd = sprintf('php -r %s 2>&1', escapeshellarg($script));
    $output = shell_exec($cmd);
    $json = json_decode($output, true);
    return ['raw' => $output, 'json' => $json];
}

// ----------------------------------------------------
// 1. Authentication Tests
// ----------------------------------------------------
echo "--- 1. Authentication & Security Gateways ---\n";
$res1 = callPublishApi($tempDir, ['title' => 'Test', 'bookId' => 'grower-guides', 'content' => 'Body'], []);
assertTest(empty($res1['json']['success']) && strpos($res1['raw'], 'Invalid API key') !== false, 'Rejects request with missing API key');

$res2 = callPublishApi($tempDir, ['title' => 'Test', 'bookId' => 'grower-guides', 'content' => 'Body'], ['HTTP_X_API_KEY' => 'wrong-key']);
assertTest(empty($res2['json']['success']) && strpos($res2['raw'], 'Invalid API key') !== false, 'Rejects request with invalid API key');

// Test authentication via active LLM access key
$llmKeyToken = 'qwk_llm_test_agent_12345';
$cfgAuthTest = json_decode(file_get_contents($tempDir . '/qwiki.json'), true);
$cfgAuthTest['llmKeys'] = [
    [
        'id' => 'key-agent-1',
        'name' => 'Agent Key',
        'key' => $llmKeyToken,
        'category' => '',
        'allowedTypes' => ['markdown', 'html'],
        'status' => 'active',
        'createdAt' => date('Y-m-d H:i:s'),
        'lastUsedAt' => null
    ]
];
file_put_contents($tempDir . '/qwiki.json', json_encode($cfgAuthTest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$resLlmAuth = callPublishApi($tempDir, ['action' => 'list_categories'], ['HTTP_X_API_KEY' => $llmKeyToken]);
assertTest(!empty($resLlmAuth['json']['success']), 'Authenticates successfully using active LLM access key (qwk_llm_...)');

// ----------------------------------------------------
// 2. Backward Compatibility: Markdown Publishing
// ----------------------------------------------------
echo "\n--- 2. Backward Compatibility (Markdown) ---\n";
$mdPayload = [
    'title' => 'Intro to Cultivars',
    'bookId' => 'grower-guides',
    'content' => "# Cultivars Guide\nStandard text with <style>body{}</style> removed."
];
$res3 = callPublishApi($tempDir, $mdPayload, ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($res3['json']['success']), 'Markdown publishing succeeds');
assertTest(($res3['json']['type'] ?? '') === 'markdown', 'Returns type: markdown');
assertTest(!empty($res3['json']['shareKey']), 'Generates shareKey for markdown document');
assertTest(strpos($res3['json']['shareUrl'], '?share=') !== false, 'Returns working shareUrl');
$mdFile = $tempDir . '/' . ($res3['json']['file'] ?? '');
assertTest(file_exists($mdFile) && strpos($mdFile, '.md') !== false, 'Markdown file written with .md extension');
$mdContent = file_get_contents($mdFile);
assertTest(strpos($mdContent, '<style>') === false, 'Markdown publisher strips style tags');

// ----------------------------------------------------
// 3. Headless HTML Publishing
// ----------------------------------------------------
echo "\n--- 3. Headless HTML Publishing ---\n";
$htmlDossier = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bellevue Estate Grower Guide</title>
    <meta name="description" content="Detailed spray schedule and soil chemistry for Bellevue Estate.">
    <meta property="og:image" content="https://mobioplus.co.za/assets/bellevue.jpg">
    <style>
        body { font-family: "Helvetica Neue", sans-serif; line-height: 1.6; }
        .yield-box { border: 2px solid #28a745; padding: 1rem; border-radius: 8px; }
        @media print {
            .no-print { display: none; }
            body { font-size: 11pt; }
        }
    </style>
</head>
<body>
    <div class="yield-box">
        <h1>Bellevue Estate Dossier</h1>
        <svg width="80" height="80"><circle cx="40" cy="40" r="30" fill="#28a745" /></svg>
        <p>Target yield: 45 tons/ha</p>
    </div>
</body>
</html>
HTML;

$htmlPayload = [
    'type' => 'html',
    'title' => 'Bellevue Estate Grower Guide',
    'bookId' => 'grower-guides',
    'content' => $htmlDossier,
    'translations' => json_encode(['af' => 'bellevue-estate-grower-guide-af'])
];

$res4 = callPublishApi($tempDir, $htmlPayload, ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($res4['json']['success']), 'HTML publishing succeeds');
assertTest(($res4['json']['type'] ?? '') === 'html', 'Returns type: html');
assertTest(($res4['json']['slug'] ?? '') === 'bellevue-estate-grower-guide', 'Correct slug generated');
assertTest(!empty($res4['json']['shareKey']) && strlen($res4['json']['shareKey']) === 16, 'Generates 16-hex character shareKey');
assertTest(!empty($res4['json']['shareUrl']), 'Returns shareUrl');

$htmlFile = $tempDir . '/' . ($res4['json']['file'] ?? '');
assertTest(file_exists($htmlFile) && strpos($htmlFile, '.html') !== false, 'HTML file written with .html extension');

$savedHtml = file_get_contents($htmlFile);
assertTest(strpos($savedHtml, '<style>') !== false, 'Preserves <style> tags in HTML document');
assertTest(strpos($savedHtml, '@media print') !== false, 'Preserves @media print rules');
assertTest(strpos($savedHtml, '<svg') !== false, 'Preserves SVG graphics');
assertTest(strpos($savedHtml, '<base href="../../">') !== false, 'Injects <base href="../../"> for 2-depth path');

// Verify metadata extracted into qwiki.json
Config::init($tempDir);
$cfg = Config::load();
$chapterNode = Navigation::findChapterBySlug($cfg['books'], 'bellevue-estate-grower-guide');
assertTest($chapterNode !== null, 'Chapter node found in qwiki.json');
assertTest(($chapterNode['type'] ?? '') === 'html', 'Node type is html in qwiki.json');
assertTest(($chapterNode['description'] ?? '') === 'Detailed spray schedule and soil chemistry for Bellevue Estate.', 'Auto-extracted meta description into node metadata');
assertTest(($chapterNode['image'] ?? '') === 'https://mobioplus.co.za/assets/bellevue.jpg', 'Auto-extracted og:image into node metadata');
assertTest(($chapterNode['translations']['af'] ?? '') === 'bellevue-estate-grower-guide-af', 'Persisted translations dictionary in node');

// ----------------------------------------------------
// 4. Security Precautions: Sanitization & Path Traversal
// ----------------------------------------------------
echo "\n--- 4. Security Precautions ---\n";
$maliciousHtml = <<<HTML
<!DOCTYPE html>
<html>
<head><title>Exploit Test</title></head>
<body>
    <script>window.maliciousPayload = true; alert("XSS");</script>
    <div onload="alert(1)" onclick="alert(2)" onmouseover="stealCookies()">Hover me</div>
    <a href="javascript:alert(document.cookie)">Malicious Link</a>
    <a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">Data Exploit</a>
    <iframe src="https://attacker.com/phish"></iframe>
</body>
</html>
HTML;

$res5 = callPublishApi($tempDir, [
    'type' => 'html',
    'title' => 'Sanitization Test Page',
    'bookId' => 'grower-guides',
    'content' => $maliciousHtml
], ['HTTP_X_API_KEY' => $apiKey]);

assertTest(!empty($res5['json']['success']), 'Sanitization test document published successfully');
$sanitizedFile = $tempDir . '/' . ($res5['json']['file'] ?? '');
$sanitizedContent = file_get_contents($sanitizedFile);

assertTest(strpos($sanitizedContent, '<script') === false, 'XSS Protection: <script> tags are stripped');
assertTest(strpos($sanitizedContent, 'window.maliciousPayload') === false, 'XSS Protection: Script body content stripped');
assertTest(strpos($sanitizedContent, 'onload=') === false, 'XSS Protection: onload event handler stripped');
assertTest(strpos($sanitizedContent, 'onclick=') === false, 'XSS Protection: onclick event handler stripped');
assertTest(strpos($sanitizedContent, 'onmouseover=') === false, 'XSS Protection: onmouseover event handler stripped');
assertTest(strpos($sanitizedContent, 'javascript:') === false, 'XSS Protection: javascript: URI neutralized to href="#"');
assertTest(strpos($sanitizedContent, 'data:text/html') === false, 'XSS Protection: data:text/html neutralized');
assertTest(strpos($sanitizedContent, '<iframe') === false, 'XSS Protection: nested iframe tags stripped');

// Path traversal testing
$res6 = callPublishApi($tempDir, [
    'type' => 'html',
    'title' => 'Traversal Test',
    'bookId' => '../../etc',
    'content' => '<h1>Exploit</h1>'
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(empty($res6['json']['success']) && strpos($res6['raw'], 'Invalid bookId') !== false, 'Rejects path traversal in bookId with 400');

// ----------------------------------------------------
// 5. Nested Category Publishing
// ----------------------------------------------------
echo "\n--- 5. Nested Category Publishing ---\n";
$res7 = callPublishApi($tempDir, [
    'type' => 'html',
    'title' => 'Stellenbosch Soil Report',
    'bookId' => 'western-cape',
    'content' => '<h1>Stellenbosch Region</h1><p>Soil profile data.</p>'
], ['HTTP_X_API_KEY' => $apiKey]);

assertTest(!empty($res7['json']['success']), 'Publishes into nested category subfolder');
$nestedFile = $tempDir . '/' . ($res7['json']['file'] ?? '');
assertTest(strpos($nestedFile, 'content/grower-guides/western-cape/stellenbosch-soil-report.html') !== false, 'Nested file written to correct subfolder path');
assertTest(file_exists($nestedFile), 'Nested file exists on disk');

$cfg = Config::load();
$nestedNode = Navigation::findChapterBySlug($cfg['books'], 'stellenbosch-soil-report');
assertTest($nestedNode !== null, 'Nested node found in navigation tree');
$parentId = Navigation::findChapterParentId($cfg['books'], 'stellenbosch-soil-report');
assertTest($parentId === 'western-cape', 'Chapter parent is correctly identified as western-cape subfolder');

// ----------------------------------------------------
// 6. Share Link Resolution via Navigation
// ----------------------------------------------------
echo "\n--- 6. Share Link Resolution ---\n";
$shareKey = $res4['json']['shareKey'];
$foundByShare = Navigation::findChapterByShareKey($cfg['books'], $shareKey, $parentBook);
assertTest($foundByShare !== null, 'findChapterByShareKey locates the published HTML document');
assertTest(($foundByShare['slug'] ?? '') === 'bellevue-estate-grower-guide', 'Resolved chapter slug matches');
// ----------------------------------------------------
// 7. Headless Category Management (create_category & list_categories)
// ----------------------------------------------------
echo "\n--- 7. Headless Category Management ---\n";
$catPayload = [
    'action' => 'create_category',
    'id' => 'orange-river-corridor',
    'title' => 'Orange River Corridor & Desert Oasis',
    'description' => 'Hyper-arid desert river oasis covering Upington and Kakamas'
];
$resCat = callPublishApi($tempDir, $catPayload, ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($resCat['json']['success']), 'Category creation succeeds');
assertTest(($resCat['json']['bookId'] ?? '') === 'orange-river-corridor', 'Returns correct bookId');
assertTest(is_dir($tempDir . '/content/orange-river-corridor'), 'Creates category directory on disk');

$cfg = Config::load();
$foundCat = false;
foreach ($cfg['books'] as $b) {
    if (($b['id'] ?? '') === 'orange-river-corridor') {
        $foundCat = true;
        break;
    }
}
assertTest($foundCat, 'Category added to books array in qwiki.json');

// Test idempotency
$resCatDup = callPublishApi($tempDir, $catPayload, ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($resCatDup['json']['success']) && ($resCatDup['json']['message'] ?? '') === 'Category already exists', 'Category creation is idempotent when category exists');

// Test listing categories
$resList = callPublishApi($tempDir, ['action' => 'list_categories'], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($resList['json']['success']) && is_array($resList['json']['categories']), 'Listing categories succeeds');
$catIds = array_column($resList['json']['categories'], 'id');
assertTest(in_array('orange-river-corridor', $catIds), 'Created category appears in list_categories');

// ----------------------------------------------------
// 8. Document Update & Idempotent Publishing (No Slug Incrementation)
// ----------------------------------------------------
echo "\n--- 8. Document Updates & Slug Idempotency ---\n";
// Publish a document with explicit slug
$docPayload1 = [
    'bookId' => 'western-cape',
    'title' => 'Stellenbosch Estate Dossier',
    'slug' => 'stellenbosch-estate-dossier',
    'type' => 'html',
    'content' => '<h1>Version 1</h1><p>Initial content</p>'
];
$resDoc1 = callPublishApi($tempDir, $docPayload1, ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($resDoc1['json']['success']), 'Initial document with explicit slug published successfully');
assertTest(($resDoc1['json']['slug'] ?? '') === 'stellenbosch-estate-dossier', 'Slug matches explicit slug without incrementation');
$initialShareKey = $resDoc1['json']['shareKey'] ?? '';
assertTest(!empty($initialShareKey), 'Generated initial share key');

// Update the exact same document in place with update=true
$docPayload2 = [
    'bookId' => 'western-cape',
    'title' => 'Stellenbosch Estate Dossier Revised',
    'slug' => 'stellenbosch-estate-dossier',
    'type' => 'html',
    'update' => 'true',
    'content' => '<h1>Version 2</h1><p>Updated content</p>'
];
$resDoc2 = callPublishApi($tempDir, $docPayload2, ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($resDoc2['json']['success']), 'Document update with update=true succeeds');
assertTest(($resDoc2['json']['slug'] ?? '') === 'stellenbosch-estate-dossier', 'Slug is preserved exactly without auto-incrementing suffix (-1)');
assertTest(!empty($resDoc2['json']['updated']), 'Response reports updated: true');
assertTest(($resDoc2['json']['shareKey'] ?? '') === $initialShareKey, 'Existing shareKey preserved across updates');

// Verify updated file content on disk
$updatedFileDisk = $tempDir . '/' . ($resDoc2['json']['file'] ?? '');
$diskContent = file_exists($updatedFileDisk) ? file_get_contents($updatedFileDisk) : '';
assertTest(strpos($diskContent, 'Version 2') !== false, 'Disk file contains updated content');

// Verify only one entry in qwiki.json (no duplicate items)
$cfgUpdated = json_decode(file_get_contents($tempDir . '/qwiki.json'), true);
$wcNode = null;
foreach ($cfgUpdated['books'] as $b) {
    if ($b['id'] === 'grower-guides') {
        foreach ($b['items'] as $sub) {
            if ($sub['id'] === 'western-cape') {
                $wcNode = $sub;
                break 2;
            }
        }
    }
}
$matchingItems = array_filter($wcNode['items'] ?? [], function($it) {
    return ($it['slug'] ?? '') === 'stellenbosch-estate-dossier';
});
assertTest(count($matchingItems) === 1, 'Only one chapter node exists in qwiki.json (no duplicate items created)');

// Clean up temp environment
function recursiveClean($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $f) {
        $path = $dir . '/' . $f;
        is_dir($path) ? recursiveClean($path) : @unlink($path);
    }
    @rmdir($dir);
}
recursiveClean($tempDir);

echo "\n============================================\n";
echo "Test Results: {$passed} passed, {$failed} failed.\n";
echo "============================================\n";

if ($failed > 0) {
    exit(1);
}
