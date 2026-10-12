<?php
/**
 * Test Suite: Document Lifecycle & Status Architecture (CR-QWIKI-LIFECYCLE-2026)
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

echo "Running Document Lifecycle & Status Architecture Test Suite...\n\n";

// Set up temporary isolated environment
$tempDir = sys_get_temp_dir() . '/qwiki_lifecycle_test_' . uniqid();
@mkdir($tempDir . '/content/estate-guides', 0755, true);

$apiKey = 'lifecycle-secret-api-key-2026';
$sampleShareKey = 'share_test_123456';

$initialConfig = [
    'title' => 'Lifecycle Wiki Test',
    'publishApiKey' => $apiKey,
    'books' => [
        [
            'id' => 'estate-guides',
            'title' => 'Estate Guides',
            'type' => 'folder',
            'items' => [
                [
                    'title' => 'Bellevue Estate Guide',
                    'slug' => 'bellevue-estate-guide',
                    'type' => 'html',
                    'file' => 'content/estate-guides/bellevue-guide.html',
                    'shareKey' => $sampleShareKey,
                    'status' => 'draft',
                    'statusUpdatedAt' => '2026-10-10T12:00:00+02:00'
                ]
            ]
        ]
    ]
];
file_put_contents($tempDir . '/qwiki.json', json_encode($initialConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// Helper to invoke publish.php in isolated process
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
// 1. Navigation Core Method Tests
// ----------------------------------------------------
echo "--- 1. Navigation::updateChapterStatus & Validation ---\n";

$booksTest = $initialConfig['books'];

// 1.1 Update status by slug
$resSlug = Navigation::updateChapterStatus($booksTest, 'bellevue-estate-guide', 'review', [
    'updatedBy' => 'agronomist',
    'approver' => 'Gerhard Cronje',
    'note' => 'Dosage verified at 2.0 L/ha'
]);
assertTest($resSlug !== null && ($resSlug['status'] ?? '') === 'review', 'updateChapterStatus by slug updates status to review');
assertTest(($resSlug['statusUpdatedBy'] ?? '') === 'agronomist', 'Sets statusUpdatedBy');
assertTest(($resSlug['approver'] ?? '') === 'Gerhard Cronje', 'Sets approver');
assertTest(($resSlug['approverNote'] ?? '') === 'Dosage verified at 2.0 L/ha', 'Sets approverNote');

// 1.2 Update status by shareKey
$resShare = Navigation::updateChapterStatus($booksTest, $sampleShareKey, 'published', [
    'approver' => 'Franco Benedetti'
]);
assertTest($resShare !== null && ($resShare['status'] ?? '') === 'published', 'updateChapterStatus by shareKey updates status to published');

// 1.3 Reject invalid status
$resInvalid = Navigation::updateChapterStatus($booksTest, 'bellevue-estate-guide', 'invalid_status_xyz');
assertTest($resInvalid === null, 'updateChapterStatus rejects invalid status');

// 1.4 Non-existent identifier
$resMissing = Navigation::updateChapterStatus($booksTest, 'non_existent_slug', 'draft');
assertTest($resMissing === null, 'updateChapterStatus returns null for unknown identifier');


// ----------------------------------------------------
// 2. DOM Sanitization & HTML File Updates
// ----------------------------------------------------
echo "\n--- 2. Navigation::sanitizeHtmlDocumentStatus & DOM Cleaning ---\n";

$testHtmlFile = $tempDir . '/content/estate-guides/bellevue-guide.html';
$sampleHtmlContent = '<!DOCTYPE html>
<html lang="en" data-qwiki-status="draft">
<head>
    <meta charset="UTF-8">
    <title>Bellevue Estate - Grower Guide</title>
</head>
<body>
    <!-- QWIKI_DRAFT_START -->
    <aside class="draft-boundary-box">Comment Boundary Box</aside>
    <!-- QWIKI_DRAFT_END -->
    <div id="gerhard-review-docket" class="review-docket-banner">
        <div class="docket-header">Docket Header</div>
        <table class="docket-table"><tr><td>Auditor</td></tr></table>
        <div><strong>Reviewer Audit Checklist:</strong> Complete</div>
    </div>
    <div class="watermark-draft qwiki-draft qwiki-watermark" data-qwiki-draft>
        DRAFT FOR REVIEW ONLY
    </div>
    <div class="review-docket qwiki-draft" data-qwiki-draft>
        <h3>Review Docket</h3>
        <p>Reviewer: Gerhard Cronje</p>
    </div>
    <main>
        <h1>Operational Guide</h1>
        <p>MoBioplus Application: 2.0 L/ha</p>
    </main>
</body>
</html>';

file_put_contents($testHtmlFile, $sampleHtmlContent);

// 2.1 CSS-only status switch (cleanSource = false)
$ok1 = Navigation::sanitizeHtmlDocumentStatus($testHtmlFile, 'review', false);
assertTest($ok1 === true, 'sanitizeHtmlDocumentStatus succeeds with cleanSource=false');
$contentAfterReview = file_get_contents($testHtmlFile);
assertTest(strpos($contentAfterReview, 'data-qwiki-status="review"') !== false, 'Root html tag updated to data-qwiki-status="review"');
assertTest(strpos($contentAfterReview, 'watermark-draft') !== false, 'Draft watermark preserved when cleanSource=false');
assertTest(strpos($contentAfterReview, 'review-docket') !== false, 'Review docket preserved when cleanSource=false');
assertTest(strpos($contentAfterReview, 'QWIKI_DRAFT_START') !== false, 'Comment boundaries preserved when cleanSource=false');
assertTest(strpos($contentAfterReview, 'docket-table') !== false, 'Docket table preserved when cleanSource=false');

// 2.2 Permanent cleaning on release (cleanSource = true and status = \'published\')
$ok2 = Navigation::sanitizeHtmlDocumentStatus($testHtmlFile, 'published', true);
assertTest($ok2 === true, 'sanitizeHtmlDocumentStatus succeeds with cleanSource=true');
$contentAfterPublish = file_get_contents($testHtmlFile);
assertTest(strpos($contentAfterPublish, 'data-qwiki-status="published"') !== false, 'Root html tag updated to data-qwiki-status="published"');
assertTest(strpos($contentAfterPublish, 'watermark-draft') === false, 'Watermark DOM node stripped from file');
assertTest(strpos($contentAfterPublish, 'review-docket') === false, 'Review docket DOM node stripped from file');
assertTest(strpos($contentAfterPublish, 'QWIKI_DRAFT_START') === false, 'Comment boundaries stripped from file');
assertTest(strpos($contentAfterPublish, 'gerhard-review-docket') === false, 'Review docket banner stripped from file');
assertTest(strpos($contentAfterPublish, 'docket-table') === false, 'Docket table stripped from file');
assertTest(strpos($contentAfterPublish, 'Reviewer Audit Checklist:') === false, 'Audit checklist stripped from file');
assertTest(strpos($contentAfterPublish, 'Operational Guide') !== false, 'Main content intact after sanitization');


// ----------------------------------------------------
// 3. Headless Publish API Endpoint Tests (publish.php)
// ----------------------------------------------------
echo "\n--- 3. Headless Publish API (api/publish.php) ---\n";

// 3.1 Publish new doc with explicit draft status
$postDoc = [
    'action' => 'publish_doc',
    'bookId' => 'estate-guides',
    'title' => 'Meerendal Dossier',
    'slug' => 'meerendal-dossier',
    'type' => 'html',
    'status' => 'draft',
    'content' => '<!DOCTYPE html><html data-qwiki-status="draft"><body><div class="qwiki-draft">DRAFT DOCKET</div><h1>Meerendal</h1></body></html>'
];
$pubRes = callPublishApi($tempDir, $postDoc, ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($pubRes['json']['success']), 'publish_doc succeeds with status=draft');
assertTest(($pubRes['json']['status'] ?? '') === 'draft', 'publish_doc returns status=draft');

$meerendalShareKey = $pubRes['json']['shareKey'] ?? '';
assertTest(!empty($meerendalShareKey), 'publish_doc generates valid shareKey');

// 3.2 Update status via action=set_status using shareKey
$setStatusPost = [
    'action' => 'set_status',
    'shareKey' => $meerendalShareKey,
    'status' => 'published',
    'approver' => 'Gerhard Cronje',
    'approverNote' => 'Agronomically sealed and approved',
    'clean_source' => '1'
];
$setRes = callPublishApi($tempDir, $setStatusPost, ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($setRes['json']['success']), 'set_status succeeds for shareKey');
assertTest(($setRes['json']['status'] ?? '') === 'published', 'set_status returns status=published');
assertTest(($setRes['json']['approver'] ?? '') === 'Gerhard Cronje', 'set_status returns approver');

// Verify file on disk was sanitized
$diskFile = $tempDir . '/content/estate-guides/meerendal-dossier.html';
assertTest(file_exists($diskFile), 'Document file exists on disk');
$sanitizedDisk = file_get_contents($diskFile);
assertTest(strpos($sanitizedDisk, 'data-qwiki-status="published"') !== false, 'Disk file has data-qwiki-status="published"');
assertTest(strpos($sanitizedDisk, 'qwiki-draft') === false, 'Disk file has stripped qwiki-draft node');

// 3.3 Error handling on set_status
$badStatusPost = [
    'action' => 'set_status',
    'shareKey' => $meerendalShareKey,
    'status' => 'bad_status_value'
];
$badRes = callPublishApi($tempDir, $badStatusPost, ['HTTP_X_API_KEY' => $apiKey]);
assertTest(empty($badRes['json']['success']) && strpos($badRes['raw'], 'Invalid status') !== false, 'set_status rejects invalid status value');

$missingIdPost = [
    'action' => 'set_status',
    'status' => 'published'
];
$missRes = callPublishApi($tempDir, $missingIdPost, ['HTTP_X_API_KEY' => $apiKey]);
assertTest(empty($missRes['json']['success']), 'set_status requires identifier');

// ----------------------------------------------------
// Summary
// ----------------------------------------------------
echo "\n========================================\n";
echo "Tests Passed: {$passed}\n";
echo "Tests Failed: {$failed}\n";
echo "========================================\n";

// Cleanup temp dir
$cleanCmd = sprintf('rm -rf %s', escapeshellarg($tempDir));
shell_exec($cleanCmd);

if ($failed > 0) {
    exit(1);
}
exit(0);
