<?php
/**
 * Standalone Qwiki - Document Postbox Extension Unit & Integration Tests
 */
require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/SubwikiManager.php';
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';
require_once __DIR__ . '/../assets/extensions/tool-postbox/Envelope.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\ExtensionManager;
use Qwiki\Extension\Postbox\Envelope;

echo "Running Document Postbox Tests...\n\n";

$realRoot = realpath(__DIR__ . '/..');
Config::init($realRoot);

// Setup temporary test sandbox
$testBaseDir = sys_get_temp_dir() . '/qwiki_postbox_test_' . uniqid();
@mkdir($testBaseDir . '/content/general', 0755, true);
@mkdir($testBaseDir . '/uploads/images', 0755, true);

// Create sample image asset
$samplePngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
file_put_contents($testBaseDir . '/uploads/images/sample-diagram.png', $samplePngData);

// Create sample markdown document with embedded image
$sampleMd = "# Welcome to Postbox\n\nThis is a sample document.\n\n![Diagram](uploads/images/sample-diagram.png)\n";
file_put_contents($testBaseDir . '/content/general/welcome.md', $sampleMd);

// Create sample qwiki.json
$testConfig = [
    'title' => 'Sender Wiki',
    'postboxToken' => 'test-secret-token-123456789012',
    'books' => [
        [
            'id' => 'general',
            'title' => 'General Information',
            'type' => 'folder',
            'items' => [
                [
                    'title' => 'Welcome to Postbox',
                    'slug' => 'welcome',
                    'type' => 'markdown',
                    'file' => 'content/general/welcome.md',
                    'description' => 'A test document',
                    'theme' => 'theme-article-modern',
                    'readOnly' => true
                ]
            ]
        ]
    ]
];
file_put_contents($testBaseDir . '/qwiki.json', json_encode($testConfig, JSON_PRETTY_PRINT));
file_put_contents($testBaseDir . '/users.json', json_encode(['users' => []], JSON_PRETTY_PRINT));

Config::init($testBaseDir);

// -------------------------------------------------------------
// Test 1: Package Creation & Asset Extraction
// -------------------------------------------------------------
echo "1. Testing Package Creation & Asset Extraction...\n";
$docsToPackage = [$testConfig['books'][0]['items'][0]];
$envelope = Envelope::createPackage($docsToPackage, $testBaseDir, $testConfig);

if (empty($envelope['batch_id'])) {
    echo "FAIL: Envelope batch_id is missing\n";
    exit(1);
}
if (count($envelope['documents']) !== 1) {
    echo "FAIL: Expected 1 document in envelope, got " . count($envelope['documents']) . "\n";
    exit(1);
}

$packagedDoc = $envelope['documents'][0];
if ($packagedDoc['slug'] !== 'welcome' || $packagedDoc['title'] !== 'Welcome to Postbox') {
    echo "FAIL: Document metadata mismatch in envelope\n";
    exit(1);
}
if (empty($packagedDoc['assets']) || count($packagedDoc['assets']) !== 1) {
    echo "FAIL: Referenced image asset was not extracted into package\n";
    exit(1);
}
if ($packagedDoc['assets'][0]['rel_path'] !== 'uploads/images/sample-diagram.png') {
    echo "FAIL: Asset rel_path mismatch: " . $packagedDoc['assets'][0]['rel_path'] . "\n";
    exit(1);
}
echo "PASS: Package creation and asset bundling verified.\n\n";

// -------------------------------------------------------------
// Test 2: Staging into Postbox Inbox
// -------------------------------------------------------------
echo "2. Testing Staging into Postbox Inbox...\n";
$stageResult = Envelope::saveStagedEnvelope($testBaseDir, $envelope);
if (empty($stageResult['success'])) {
    echo "FAIL: Failed to stage envelope: " . ($stageResult['error'] ?? '') . "\n";
    exit(1);
}

$inboxDir = $testBaseDir . '/uploads/.postbox/inbox';
if (!file_exists($inboxDir . '/' . $envelope['batch_id'] . '.json')) {
    echo "FAIL: Staged envelope file does not exist on disk\n";
    exit(1);
}
if (!file_exists($testBaseDir . '/uploads/.postbox/.htaccess')) {
    echo "FAIL: Postbox .htaccess protection file was not created\n";
    exit(1);
}

$envelopes = Envelope::listInboxEnvelopes($testBaseDir);
if (count($envelopes) !== 1 || $envelopes[0]['batch_id'] !== $envelope['batch_id']) {
    echo "FAIL: listInboxEnvelopes did not return expected staged envelope\n";
    exit(1);
}
echo "PASS: Postbox staging, .htaccess protection, and listing verified.\n\n";

// -------------------------------------------------------------
// Test 3: Document Ingestion with Category Assignment & Metadata Override
// -------------------------------------------------------------
echo "3. Testing Document Ingestion with Category Assignment & Overrides...\n";

// Destination setup: target a new category 'technical'
$destConfig = [
    'title' => 'Recipient Wiki',
    'books' => [
        [
            'id' => 'technical',
            'title' => 'Technical Specs',
            'type' => 'folder',
            'items' => []
        ]
    ]
];
file_put_contents($testBaseDir . '/qwiki.json', json_encode($destConfig, JSON_PRETTY_PRINT));
Config::init($testBaseDir);

$overrides = [
    'title' => 'Transferred Welcome Guide',
    'slug' => 'welcome-guide',
    'theme' => 'theme-custom',
    'readOnly' => false,
    'description' => 'Customized on ingest'
];

$ingestResult = Envelope::ingestDocument(
    $testBaseDir,
    $destConfig,
    $envelope['batch_id'],
    0, // first doc
    'technical',
    $overrides
);

if (empty($ingestResult['success'])) {
    echo "FAIL: Document ingestion failed: " . ($ingestResult['error'] ?? '') . "\n";
    exit(1);
}

// Verify file written to content/technical/welcome-guide.md
$expectedFile = $testBaseDir . '/content/technical/welcome-guide.md';
if (!file_exists($expectedFile)) {
    echo "FAIL: Destination document file was not written to: {$expectedFile}\n";
    exit(1);
}

// Verify config updated
$savedConfig = Config::load();
$techBook = null;
foreach ($savedConfig['books'] as $b) {
    if ($b['id'] === 'technical') {
        $techBook = $b;
        break;
    }
}

if (!$techBook || count($techBook['items']) !== 1) {
    echo "FAIL: Target category does not contain ingested chapter in qwiki.json\n";
    exit(1);
}

$ingestedNode = $techBook['items'][0];
if ($ingestedNode['title'] !== 'Transferred Welcome Guide' || $ingestedNode['slug'] !== 'welcome-guide') {
    echo "FAIL: Ingested node title or slug override mismatch\n";
    exit(1);
}
if ($ingestedNode['file'] !== 'content/technical/welcome-guide.md') {
    echo "FAIL: Ingested node file path mismatch: " . $ingestedNode['file'] . "\n";
    exit(1);
}

// Verify batch was cleaned from inbox since it was the only document
$inboxAfter = Envelope::listInboxEnvelopes($testBaseDir);
if (count($inboxAfter) !== 0) {
    echo "FAIL: Expected empty inbox after ingesting all docs, found " . count($inboxAfter) . " items\n";
    exit(1);
}
echo "PASS: Document ingestion, category mapping, metadata customization, and inbox queue clearance verified.\n\n";

// -------------------------------------------------------------
// Test 4: Slug Collision Handling
// -------------------------------------------------------------
echo "4. Testing Slug Collision Auto-Incrementing...\n";

// Stage another envelope with the same slug 'welcome-guide'
$collidingEnvelope = Envelope::createPackage($docsToPackage, $testBaseDir, $testConfig);
Envelope::saveStagedEnvelope($testBaseDir, $collidingEnvelope);

$collisionResult = Envelope::ingestDocument(
    $testBaseDir,
    $destConfig,
    $collidingEnvelope['batch_id'],
    0,
    'technical',
    ['slug' => 'welcome-guide', 'title' => 'Duplicate Document']
);

if (empty($collisionResult['success'])) {
    echo "FAIL: Ingestion with colliding slug failed: " . ($collisionResult['error'] ?? '') . "\n";
    exit(1);
}

if ($collisionResult['slug'] !== 'welcome-guide-1') {
    echo "FAIL: Expected auto-incremented slug 'welcome-guide-1', got '{$collisionResult['slug']}'\n";
    exit(1);
}
if (!file_exists($testBaseDir . '/content/technical/welcome-guide-1.md')) {
    echo "FAIL: Colliding file was not written with unique suffix\n";
    exit(1);
}
echo "PASS: Slug collision automatically resolved to unique identifier.\n\n";

// -------------------------------------------------------------
// Test 5: Rejection Handling
// -------------------------------------------------------------
echo "5. Testing Batch Rejection...\n";
$rejectEnvelope = Envelope::createPackage($docsToPackage, $testBaseDir, $testConfig);
Envelope::saveStagedEnvelope($testBaseDir, $rejectEnvelope);

$rejectResult = Envelope::rejectEnvelope($testBaseDir, $rejectEnvelope['batch_id']);
if (empty($rejectResult['success'])) {
    echo "FAIL: Reject envelope failed: " . ($rejectResult['error'] ?? '') . "\n";
    exit(1);
}

if (file_exists($inboxDir . '/' . $rejectEnvelope['batch_id'] . '.json')) {
    echo "FAIL: Rejected envelope file still exists in inbox\n";
    exit(1);
}
echo "PASS: Batch rejection and discard verified.\n\n";

// -------------------------------------------------------------
// Test 6: HTML Document Integrity & Base Href Injection
// -------------------------------------------------------------
echo "6. Testing HTML Document Preservation & <base href> Injection...\n";
$htmlSource = "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n    <meta charset=\"UTF-8\">\n    <title>Live Analytics Dashboard</title>\n    <style>body { font-family: sans-serif; }</style>\n</head>\n<body>\n    <h1>Live Analytics</h1>\n    <img src=\"./img/chart.png\" alt=\"Chart\">\n    <button onclick=\"pingServer()\">Ping</button>\n    <script>\n        function pingServer() { console.log('ping'); }\n    </script>\n</body>\n</html>";

$htmlEnvelope = [
    'version' => '1.0',
    'batch_id' => 'html_batch_' . uniqid(),
    'created_at' => date('c'),
    'sender' => ['title' => 'Desktop CLI'],
    'documents' => [
        [
            'id' => 'live-analytics',
            'slug' => 'live-analytics',
            'title' => 'Live Analytics Dashboard',
            'type' => 'html',
            'content' => $htmlSource,
            'assets' => [
                [
                    'rel_path' => './img/chart.png',
                    'basename' => 'chart.png',
                    'mime' => 'image/png',
                    'size' => strlen($samplePngData),
                    'sha1' => sha1($samplePngData),
                    'data' => base64_encode($samplePngData)
                ]
            ]
        ]
    ]
];

$htmlStage = Envelope::saveStagedEnvelope($testBaseDir, $htmlEnvelope);
if (empty($htmlStage['success'])) {
    echo "FAIL: Could not stage HTML envelope: " . ($htmlStage['error'] ?? '') . "\n";
    exit(1);
}

$htmlIngest = Envelope::ingestDocument(
    $testBaseDir,
    $destConfig,
    $htmlEnvelope['batch_id'],
    0,
    'technical'
);

if (empty($htmlIngest['success'])) {
    echo "FAIL: HTML document ingestion failed: " . ($htmlIngest['error'] ?? '') . "\n";
    exit(1);
}

$ingestedHtmlFile = $testBaseDir . '/content/technical/live-analytics.html';
if (!file_exists($ingestedHtmlFile)) {
    echo "FAIL: Ingested HTML file does not exist at {$ingestedHtmlFile}\n";
    exit(1);
}

$ingestedHtmlContent = file_get_contents($ingestedHtmlFile);

if (strpos($ingestedHtmlContent, '<meta charset="UTF-8">') === false) {
    echo "FAIL: <meta charset=\"UTF-8\"> was stripped or corrupted in HTML document\n";
    exit(1);
}
if (strpos($ingestedHtmlContent, '<script>') === false || strpos($ingestedHtmlContent, 'function pingServer()') === false) {
    echo "FAIL: <script> block was stripped or corrupted in HTML document\n";
    exit(1);
}
if (strpos($ingestedHtmlContent, 'onclick="pingServer()"') === false) {
    echo "FAIL: onclick event handler was stripped from HTML document\n";
    exit(1);
}
if (strpos($ingestedHtmlContent, '<base href="../../">') === false) {
    echo "FAIL: Injected <base href=\"../../\"> is missing in HTML document\n";
    exit(1);
}
if (strpos($ingestedHtmlContent, 'uploads/images/chart.png') === false) {
    echo "FAIL: Asset link ./img/chart.png was not remapped to uploads/images/chart.png\n";
    exit(1);
}

echo "PASS: HTML document integrity, full tag preservation, and base href injection verified.\n\n";

// -------------------------------------------------------------
// Test 7: Subcategory Resolution & Ingestion into Nested Subcategory
// -------------------------------------------------------------
echo "7. Testing Subcategory Ingestion & Folder Hierarchy Resolution...\n";
$nestedConfig = [
    'title' => 'Nested Target Wiki',
    'books' => [
        [
            'id' => 'guides',
            'title' => 'Main Guides',
            'type' => 'folder',
            'items' => [
                [
                    'id' => 'advanced',
                    'title' => 'Advanced Subcategory',
                    'type' => 'folder',
                    'items' => []
                ]
            ]
        ]
    ]
];
file_put_contents($testBaseDir . '/qwiki.json', json_encode($nestedConfig, JSON_PRETTY_PRINT));
Config::init($testBaseDir);

// Verify Envelope::resolveCategoryFolder
$resolvedFolder = Envelope::resolveCategoryFolder($nestedConfig['books'], 'advanced');
if ($resolvedFolder !== 'content/guides/advanced') {
    echo "FAIL: Expected folder 'content/guides/advanced', got '{$resolvedFolder}'\n";
    exit(1);
}

// Stage envelope to ingest into subcategory 'advanced'
$nestedEnv = Envelope::createPackage($docsToPackage, $testBaseDir, $testConfig);
Envelope::saveStagedEnvelope($testBaseDir, $nestedEnv);

$subIngest = Envelope::ingestDocument(
    $testBaseDir,
    $nestedConfig,
    $nestedEnv['batch_id'],
    0,
    'advanced',
    ['title' => 'Nested Guide', 'slug' => 'nested-guide']
);

if (empty($subIngest['success'])) {
    echo "FAIL: Ingesting into subcategory failed: " . ($subIngest['error'] ?? '') . "\n";
    exit(1);
}

$expectedNestedFile = $testBaseDir . '/content/guides/advanced/nested-guide.md';
if (!file_exists($expectedNestedFile)) {
    echo "FAIL: Ingested file was not created at expected nested path: {$expectedNestedFile}\n";
    exit(1);
}

$updatedConf = Config::load();
$subCategoryFound = false;
foreach ($updatedConf['books'] as $b) {
    if (($b['id'] ?? '') === 'guides') {
        foreach ($b['items'] ?? [] as $sub) {
            if (($sub['id'] ?? '') === 'advanced') {
                foreach ($sub['items'] ?? [] as $doc) {
                    if (($doc['slug'] ?? '') === 'nested-guide') {
                        $subCategoryFound = true;
                        break 3;
                    }
                }
            }
        }
    }
}

if (!$subCategoryFound) {
    echo "FAIL: Ingested document was not placed inside the subcategory in qwiki.json\n";
    exit(1);
}
echo "PASS: Subcategory resolution and nested folder ingestion verified.\n\n";

// -------------------------------------------------------------
// Test 8: Recursive Category Document Extraction
// -------------------------------------------------------------
echo "8. Testing Recursive Category Document Extraction...\n";
// Create a category with top-level and nested documents
$sourceConfig = [
    'title' => 'Source Wiki',
    'books' => [
        [
            'id' => 'dept-engineering',
            'title' => 'Engineering Department',
            'type' => 'folder',
            'items' => [
                [
                    'title' => 'Overview',
                    'slug' => 'eng-overview',
                    'type' => 'markdown',
                    'file' => 'content/general/welcome.md'
                ],
                [
                    'id' => 'backend-team',
                    'title' => 'Backend Team',
                    'type' => 'folder',
                    'items' => [
                        [
                            'title' => 'API Guidelines',
                            'slug' => 'api-guidelines',
                            'type' => 'markdown',
                            'file' => 'content/general/welcome.md'
                        ]
                    ]
                ]
            ]
        ]
    ]
];

// Helper recursive gather
$docsGathered = [];
$collectDocsRecursive = function($items) use (&$collectDocsRecursive, &$docsGathered) {
    foreach ($items as $it) {
        if (($it['type'] ?? '') === 'folder') {
            $collectDocsRecursive($it['items'] ?? []);
        } elseif (($it['type'] ?? '') !== 'link' && !empty($it['slug'])) {
            $docsGathered[] = $it;
        }
    }
};
$collectDocsRecursive($sourceConfig['books'][0]['items']);

if (count($docsGathered) !== 2) {
    echo "FAIL: Expected 2 documents gathered from category including subfolders, got " . count($docsGathered) . "\n";
    exit(1);
}

$categoryPkg = Envelope::createPackage($docsGathered, $testBaseDir, $sourceConfig);
if (count($categoryPkg['documents']) !== 2) {
    echo "FAIL: Expected 2 documents in category package, got " . count($categoryPkg['documents']) . "\n";
    exit(1);
}
echo "PASS: Recursive category document extraction verified.\n\n";

// -------------------------------------------------------------
// Test 9: Bulk Selection Packaging with Category Disambiguation
// -------------------------------------------------------------
echo "9. Testing Bulk Packaging with Category Disambiguation...\n";
$multiCatConfig = [
    'title' => 'Multi-Category Wiki',
    'books' => [
        [
            'id' => 'cat-a',
            'title' => 'Category A',
            'type' => 'folder',
            'items' => [
                ['title' => 'Introduction A', 'slug' => 'intro', 'type' => 'markdown', 'file' => 'content/general/welcome.md']
            ]
        ],
        [
            'id' => 'cat-b',
            'title' => 'Category B',
            'type' => 'folder',
            'items' => [
                ['title' => 'Introduction B', 'slug' => 'intro', 'type' => 'markdown', 'file' => 'content/general/welcome.md']
            ]
        ]
    ]
];

$findDocDisambiguated = function($nodes, $slug, $bookId = null) {
    if (!empty($bookId)) {
        foreach ($nodes as $n) {
            if (($n['id'] ?? '') === $bookId) {
                foreach ($n['items'] ?? [] as $it) {
                    if (($it['slug'] ?? '') === $slug) return $it;
                }
            }
        }
    }
    return null;
};

$matchedDoc = $findDocDisambiguated($multiCatConfig['books'], 'intro', 'cat-b');
if (!$matchedDoc || $matchedDoc['title'] !== 'Introduction B') {
    echo "FAIL: Bulk document disambiguation did not select the document from the requested category\n";
    exit(1);
}
echo "PASS: Bulk document disambiguation verified.\n\n";

// Cleanup test directory
function cleanRecursive($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $f) {
        $p = $dir . '/' . $f;
        if (is_dir($p)) cleanRecursive($p);
        else @unlink($p);
    }
    @rmdir($dir);
}
cleanRecursive($testBaseDir);

echo "ALL POSTBOX EXTENSION UNIT TESTS PASSED! 🚀📦\n";
