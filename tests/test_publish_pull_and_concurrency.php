<?php
/**
 * Test Suite: api/publish.php get_doc (Pull), Comments/Talk Retrieval, Active Lock Guard & Optimistic Concurrency (expectedSha256)
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';
require_once __DIR__ . '/../lib/Core/LockManager.php';
require_once __DIR__ . '/../assets/extensions/tool-comments/CommentService.php';
require_once __DIR__ . '/../assets/extensions/tool-talk/TalkService.php';

use Qwiki\Core\Config;
use Qwiki\Core\LockManager;
use Qwiki\Extension\Comments\CommentService;
use Qwiki\Extension\Talk\TalkService;

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

echo "Running Publish API Pull & Concurrency Test Suite...\n\n";

$tempDir = sys_get_temp_dir() . '/qwiki_pull_test_' . uniqid();
@mkdir($tempDir . '/content/guides', 0755, true);
@mkdir($tempDir . '/content/restricted', 0755, true);

$apiKey = 'pub-secret-2026';
$scopedLlmKey = 'qwk_llm_scoped_guides_123';

$initialConfig = [
    'title' => 'Pull & Concurrency Wiki',
    'publishApiKey' => $apiKey,
    'llmKeys' => [
        [
            'id' => 'key-guides',
            'name' => 'Guides Agent',
            'key' => $scopedLlmKey,
            'category' => 'guides',
            'allowedTypes' => ['markdown', 'html'],
            'status' => 'active',
            'createdAt' => date('Y-m-d H:i:s'),
        ]
    ],
    'books' => [
        [
            'id' => 'guides',
            'title' => 'Guides',
            'type' => 'folder',
            'folder' => 'content/guides',
            'allowComments' => true,
            'allowTalk' => true,
            'items' => []
        ],
        [
            'id' => 'restricted',
            'title' => 'Restricted',
            'type' => 'folder',
            'folder' => 'content/restricted',
            'items' => []
        ]
    ]
];
file_put_contents($tempDir . '/qwiki.json', json_encode($initialConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

Config::init($tempDir);

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

// 1. Publish Markdown & HTML & Draft documents
echo "--- 1. Initial Publishing & SHA-256 Reporting ---\n";
$pubMd = callPublishApi($tempDir, [
    'bookId' => 'guides',
    'title' => 'Safety Guide',
    'slug' => 'safety-guide',
    'status' => 'draft',
    'content' => "# Safety Guide\n\n![Photo](uploads/images/live-photo.png)\n",
], ['HTTP_X_API_KEY' => $apiKey]);

assertTest(!empty($pubMd['json']['success']), 'Publishes draft markdown document');
assertTest(!empty($pubMd['json']['docId']), 'Returns docId on publish');
assertTest(!empty($pubMd['json']['sha256']) && strlen($pubMd['json']['sha256']) === 64, 'Returns 64-char sha256 on publish');

$initialSha = $pubMd['json']['sha256'];
$shareKeyMd = $pubMd['json']['shareKey'];

$pubHtml = callPublishApi($tempDir, [
    'bookId' => 'guides',
    'title' => 'HTML Spec',
    'slug' => 'html-spec',
    'type' => 'html',
    'content' => "<!DOCTYPE html><html><head><style>.x{color:red}</style></head><body><h1>HTML Spec</h1></body></html>",
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($pubHtml['json']['success']), 'Publishes HTML document');

$pubRestricted = callPublishApi($tempDir, [
    'bookId' => 'restricted',
    'title' => 'Private Note',
    'slug' => 'private-note',
    'content' => "# Private\nSecret body.\n",
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($pubRestricted['json']['success']), 'Publishes restricted document');

// Seed live comments and talk topics
CommentService::addComment('guides', 'safety-guide', 'jannie', 'viewer', 'Please clarify nozzle pressure.');
TalkService::addTopic('guides', 'safety-guide', 'gerhard', 'admin', 'Dosage Update', 'Update section 2 to 2.5 L/ha.');

// 2. Pull via get_doc (draft, comments, talk, verbatim markdown, verbatim HTML)
echo "\n--- 2. Pull (action=get_doc) ---\n";
$pullMd = callPublishApi($tempDir, [
    'action' => 'get_doc',
    'slug' => 'safety-guide',
    'bookId' => 'guides',
], ['HTTP_X_API_KEY' => $apiKey]);

assertTest(!empty($pullMd['json']['success']), 'get_doc succeeds for draft document');
$docMd = $pullMd['json']['document'] ?? [];
assertTest(($docMd['status'] ?? '') === 'draft', 'get_doc returns draft status');
assertTest(($docMd['sha256'] ?? '') === $initialSha, 'get_doc sha256 matches published sha256');
assertTest(strpos($docMd['content'] ?? '', '![Photo](uploads/images/live-photo.png)') !== false, 'get_doc preserves relative uploads/ link verbatim');
assertTest(($docMd['commentsEnabled'] ?? false) === true && ($docMd['commentsCount'] ?? 0) === 1, 'get_doc returns comments count and enablement');
assertTest(strpos($docMd['comments'][0]['content'] ?? '', 'nozzle pressure') !== false, 'get_doc includes comment text');
assertTest(($docMd['talkEnabled'] ?? false) === true && ($docMd['talkTopicsCount'] ?? 0) === 1, 'get_doc returns talk topics count and enablement');
assertTest(strpos($docMd['talkRaw'] ?? '', 'Dosage Update') !== false, 'get_doc includes talkRaw markdown');

// Pull by shareKey
$pullShare = callPublishApi($tempDir, [
    'action' => 'get_doc',
    'shareKey' => $shareKeyMd,
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($pullShare['json']['success']) && ($pullShare['json']['document']['slug'] ?? '') === 'safety-guide', 'get_doc resolves by shareKey');

// Pull HTML verbatim (must NOT strip <style> or HTML tags!)
$pullHtml = callPublishApi($tempDir, [
    'action' => 'get_doc',
    'slug' => 'html-spec',
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($pullHtml['json']['success']), 'get_doc succeeds for HTML document');
assertTest(strpos($pullHtml['json']['document']['content'] ?? '', '<style>.x{color:red}</style>') !== false, 'get_doc preserves raw HTML and <style> blocks');

// Category scope enforcement for scoped LLM key
$pullDenied = callPublishApi($tempDir, [
    'action' => 'get_doc',
    'slug' => 'private-note',
], ['HTTP_X_API_KEY' => $scopedLlmKey]);
assertTest(empty($pullDenied['json']['success']) && strpos($pullDenied['raw'], 'not authorized') !== false, 'get_doc rejects out-of-scope category for scoped LLM key');

// 3. Optimistic Concurrency (expectedSha256)
echo "\n--- 3. Optimistic Concurrency (expectedSha256) ---\n";
$staleUpdate = callPublishApi($tempDir, [
    'bookId' => 'guides',
    'title' => 'Safety Guide',
    'slug' => 'safety-guide',
    'content' => "# Safety Guide\nOverwrite attempt.\n",
    'expectedSha256' => str_repeat('0', 64),
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(empty($staleUpdate['json']['success']) && ($staleUpdate['json']['code'] ?? '') === 'CONFLICT_SHA256_MISMATCH', 'Rejects update with CONFLICT_SHA256_MISMATCH when expectedSha256 mismatches');
assertTest(($staleUpdate['json']['actualSha256'] ?? '') === $initialSha, 'Conflict payload includes actualSha256');

$validUpdate = callPublishApi($tempDir, [
    'bookId' => 'guides',
    'title' => 'Safety Guide',
    'slug' => 'safety-guide',
    'content' => "# Safety Guide\nMerged v2.\n",
    'expectedSha256' => $initialSha,
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($validUpdate['json']['success']), 'Allows update when expectedSha256 matches');
assertTest(($validUpdate['json']['sha256'] ?? '') !== $initialSha, 'Returns updated sha256 after write');

$forceUpdate = callPublishApi($tempDir, [
    'bookId' => 'guides',
    'title' => 'Safety Guide',
    'slug' => 'safety-guide',
    'content' => "# Safety Guide\nForced v3.\n",
    'expectedSha256' => str_repeat('0', 64),
    'force' => '1',
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($forceUpdate['json']['success']), 'Allows expectedSha256 override when force=1');

// 4. Active LockManager Guard
echo "\n--- 4. Active UI Lock Guard ---\n";
$relFile = $validUpdate['json']['file'] ?? 'content/guides/safety-guide.md';
$acq = LockManager::acquireLock($relFile, 'browser-tab-99', 'gerhard');
assertTest(!empty($acq['success']), 'Acquired UI lock for test');

$blockedByLock = callPublishApi($tempDir, [
    'bookId' => 'guides',
    'title' => 'Safety Guide',
    'slug' => 'safety-guide',
    'content' => "# Safety Guide\nAttempt while locked.\n",
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(empty($blockedByLock['json']['success']) && ($blockedByLock['json']['code'] ?? '') === 'LOCKED_BY_OTHER', 'Rejects API overwrite with LOCKED_BY_OTHER while editor holds active lock');
assertTest(($blockedByLock['json']['lockedBy'] ?? '') === 'gerhard', 'Returns lockedBy username in 423 payload');

// Pull reports active lock state
$pullLocked = callPublishApi($tempDir, [
    'action' => 'get_doc',
    'slug' => 'safety-guide',
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($pullLocked['json']['document']['lock']['locked']) && ($pullLocked['json']['document']['lock']['user'] ?? '') === 'gerhard', 'get_doc reports active lock details');

$forcedOverLock = callPublishApi($tempDir, [
    'bookId' => 'guides',
    'title' => 'Safety Guide',
    'slug' => 'safety-guide',
    'content' => "# Safety Guide\nForced while locked.\n",
    'force' => '1',
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($forcedOverLock['json']['success']), 'Allows lock override when force=1');

LockManager::releaseLock($relFile, 'browser-tab-99', 'gerhard', true);

// 5. Tree Discovery (action=get_tree) & docId Move/Rename Reconciliation
echo "\n--- 5. Tree Discovery & Immutable docId Move/Rename ---\n";
$catSub = callPublishApi($tempDir, [
    'action' => 'create_category',
    'id' => 'sub-guides',
    'title' => 'Sub Guides',
    'parentId' => 'guides',
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($catSub['json']['success']), 'Creates nested sub-category via API');

$treeRes = callPublishApi($tempDir, [
    'action' => 'get_tree',
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($treeRes['json']['success']), 'get_tree succeeds');
$catIds = array_column($treeRes['json']['categories'] ?? [], 'id');
assertTest(in_array('guides', $catIds, true) && in_array('sub-guides', $catIds, true), 'get_tree returns recursive categories including nested sub-guides');
$chMap = [];
foreach ($treeRes['json']['chapters'] ?? [] as $ch) {
    $chMap[$ch['slug']] = $ch;
}
assertTest(!empty($chMap['safety-guide']['docId']) && !empty($chMap['safety-guide']['sha256']), 'get_tree includes docId and sha256 for chapters');

$safetyDocId = $chMap['safety-guide']['docId'];
$moveRes = callPublishApi($tempDir, [
    'bookId' => 'sub-guides',
    'docId' => $safetyDocId,
    'title' => 'Safety Guide Renamed',
    'slug' => 'safety-guide-v2',
    'content' => "# Safety Guide Renamed\nMoved to sub-guides.\n",
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($moveRes['json']['success']) && !empty($moveRes['json']['updated']), 'Updates and moves chapter across categories by immutable docId');
assertTest(($moveRes['json']['docId'] ?? '') === $safetyDocId, 'Preserves original docId after rename and category move');

$treeAfterMove = callPublishApi($tempDir, [
    'action' => 'get_tree',
], ['HTTP_X_API_KEY' => $apiKey]);
$slugsAfterMove = array_column($treeAfterMove['json']['chapters'] ?? [], 'slug');
assertTest(in_array('safety-guide-v2', $slugsAfterMove, true) && !in_array('safety-guide', $slugsAfterMove, true), 'Old slug removed from previous category and new slug active in target category');

// 6. Headless JSON Form Publishing & Submission Monitoring API
echo "\n--- 6. Headless JSON Form Publishing & Submission Monitoring ---\n";
$formSchema = [
    'title' => 'Equipment Inspection Form',
    'description' => 'Daily operator safety checklist',
    'submitButtonText' => 'Submit Checklist',
    'fields' => [
        ['id' => 'operator', 'label' => 'Operator Name', 'type' => 'text', 'required' => true],
        ['id' => 'status', 'label' => 'Machine Status', 'type' => 'select', 'options' => ['Pass', 'Fail'], 'required' => true],
    ]
];

$pubForm = callPublishApi($tempDir, [
    'bookId' => 'guides',
    'title' => 'Equipment Inspection Form',
    'slug' => 'equipment-inspection',
    'type' => 'form',
    'content' => json_encode($formSchema),
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($pubForm['json']['success']) && ($pubForm['json']['type'] ?? '') === 'form', 'Publishes form chapter via api/publish.php');
assertTest($pubForm['json']['file'] === 'content/guides/equipment-inspection.form.json', 'Saves form schema with .form.json extension');
assertTest(file_exists($tempDir . '/content/guides/equipment-inspection.submissions.json'), 'Initializes companion .submissions.json file');

// Simulate 2 visitor submissions in .submissions.json
$subFileAbs = $tempDir . '/content/guides/equipment-inspection.submissions.json';
$sub1 = ['id' => 'sub_aaa111', 'created_at' => '2026-11-20 09:00:00', 'ip_hash' => '111111111111', 'submitted_by' => 'guest', 'data' => ['operator' => 'Franco', 'status' => 'Pass']];
$sub2 = ['id' => 'sub_bbb222', 'created_at' => '2026-11-21 10:30:00', 'ip_hash' => '222222222222', 'submitted_by' => 'gerhard', 'data' => ['operator' => 'Gerhard', 'status' => 'Pass']];
file_put_contents($subFileAbs, json_encode($sub1) . "\n" . json_encode($sub2) . "\n");

// Update form schema via API and verify existing submissions are preserved
$formSchema['description'] = 'Updated daily checklist';
$updForm = callPublishApi($tempDir, [
    'bookId' => 'guides',
    'title' => 'Equipment Inspection Form',
    'slug' => 'equipment-inspection',
    'type' => 'form',
    'content' => json_encode($formSchema),
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($updForm['json']['success']) && !empty($updForm['json']['updated']), 'Updates form schema in-place');

$listForms = callPublishApi($tempDir, [
    'action' => 'list_forms',
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($listForms['json']['success']) && ($listForms['json']['total'] ?? 0) === 1, 'list_forms returns published form');
assertTest(($listForms['json']['forms'][0]['submissionsCount'] ?? 0) === 2, 'list_forms reports accurate submissionsCount after schema update');

$getSubs = callPublishApi($tempDir, [
    'action' => 'get_form_submissions',
    'slug' => 'equipment-inspection',
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($getSubs['json']['success']) && count($getSubs['json']['submissions'] ?? []) === 2, 'get_form_submissions retrieves all form responses');

$getSubsSince = callPublishApi($tempDir, [
    'action' => 'get_form_submissions',
    'slug' => 'equipment-inspection',
    'since_id' => 'sub_aaa111',
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($getSubsSince['json']['success']) && count($getSubsSince['json']['submissions'] ?? []) === 1 && ($getSubsSince['json']['submissions'][0]['id'] ?? '') === 'sub_bbb222', 'get_form_submissions filters incrementally by since_id');

$getSubsCsv = callPublishApi($tempDir, [
    'action' => 'get_form_submissions',
    'slug' => 'equipment-inspection',
    'format' => 'csv',
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($getSubsCsv['json']['success']) && strpos($getSubsCsv['json']['csv'] ?? '', 'Operator Name') !== false && strpos($getSubsCsv['json']['csv'] ?? '', 'Gerhard') !== false, 'get_form_submissions exports CSV with field headers and answers');

$delSub = callPublishApi($tempDir, [
    'action' => 'delete_form_submission',
    'slug' => 'equipment-inspection',
    'id' => 'sub_aaa111',
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($delSub['json']['success']) && ($delSub['json']['remaining'] ?? -1) === 1, 'delete_form_submission deletes target response by ID');

$clearSubs = callPublishApi($tempDir, [
    'action' => 'clear_form_submissions',
    'slug' => 'equipment-inspection',
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($clearSubs['json']['success']) && filesize($subFileAbs) === 0, 'clear_form_submissions empties submissions file');

// Cleanup tempDir
$it = new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS);
$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
foreach ($files as $file) {
    if ($file->isDir()) {
        @rmdir($file->getRealPath());
    } else {
        @unlink($file->getRealPath());
    }
}
@rmdir($tempDir);

echo "\n============================================\n";
echo "Test Results: {$passed} passed, {$failed} failed.\n";
echo "============================================\n";
exit($failed > 0 ? 1 : 0);
