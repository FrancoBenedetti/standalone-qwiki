<?php
/**
 * Test Suite: Document Lifecycle Hardening, Trash, Revisions & Tracking
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';
require_once __DIR__ . '/../lib/Core/DocumentService.php';
require_once __DIR__ . '/../lib/Core/TrashManager.php';
require_once __DIR__ . '/../lib/Core/LockManager.php';
require_once __DIR__ . '/../lib/Core/AuditLogger.php';
require_once __DIR__ . '/../assets/extensions/tool-revisions/RevisionManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\Navigation;
use Qwiki\Core\DocumentService;
use Qwiki\Core\TrashManager;
use Qwiki\Core\LockManager;
use Qwiki\Extensions\Revisions\RevisionManager;

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

echo "Running Lifecycle Hardening, Trash & Revisions Test Suite...\n\n";

$tempDir = sys_get_temp_dir() . '/qwiki_hardening_test_' . uniqid();
@mkdir($tempDir . '/content/cat-a', 0755, true);
@mkdir($tempDir . '/content/cat-b', 0755, true);

$initialConfig = [
    'title' => 'Hardening Test Wiki',
    'books' => [
        [
            'id' => 'cat-a',
            'title' => 'Category A',
            'type' => 'folder',
            'folder' => 'content/cat-a',
            'items' => [
                [
                    'title' => 'Alpha Doc',
                    'slug' => 'alpha-doc',
                    'type' => 'markdown',
                    'file' => 'content/cat-a/alpha-doc.md',
                    'status' => 'published',
                ],
                [
                    'title' => 'Secret Draft',
                    'slug' => 'secret-draft',
                    'type' => 'markdown',
                    'file' => 'content/cat-a/secret-draft.md',
                    'status' => 'draft',
                ],
                [
                    'title' => 'Old Archived',
                    'slug' => 'old-archived',
                    'type' => 'markdown',
                    'file' => 'content/cat-a/old-archived.md',
                    'status' => 'archived',
                ]
            ]
        ],
        [
            'id' => 'cat-b',
            'title' => 'Category B',
            'type' => 'folder',
            'folder' => 'content/cat-b',
            'items' => []
        ]
    ]
];

file_put_contents($tempDir . '/qwiki.json', json_encode($initialConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
file_put_contents($tempDir . '/content/cat-a/alpha-doc.md', "# Alpha v1\nInitial body.\n");
file_put_contents($tempDir . '/content/cat-a/secret-draft.md', "# Secret Draft\n");
file_put_contents($tempDir . '/content/cat-a/old-archived.md', "# Archived\n");

Config::init($tempDir);
$config = Config::load();

// 1. docId & Status Visibility
echo "--- 1. docId & Status Visibility ---\n";
$ch = ['slug' => 'alpha-doc', 'status' => 'published'];
$docId = Navigation::ensureDocId($ch);
assertTest(strpos($docId, 'doc_') === 0 && strlen($docId) === 20, 'ensureDocId generates doc_ prefixed 16-hex ID');
assertTest(Navigation::ensureDocId($ch) === $docId, 'ensureDocId is idempotent');

assertTest(Navigation::isChapterVisibleForStatus(['status' => 'published'], false) === true, 'published visible to public');
assertTest(Navigation::isChapterVisibleForStatus(['status' => 'review'], false) === true, 'review visible to public');
assertTest(Navigation::isChapterVisibleForStatus(['status' => 'draft'], false) === false, 'draft hidden from public');
assertTest(Navigation::isChapterVisibleForStatus(['status' => 'archived'], false) === false, 'archived hidden from public');
assertTest(Navigation::isChapterVisibleForStatus(['status' => 'draft'], true) === true, 'draft visible to admin');
assertTest(Navigation::isChapterVisibleForStatus(['status' => 'archived'], true) === true, 'archived visible to admin');

// 2. Unified Replace & Revision Snapshotting
echo "\n--- 2. DocumentService::replaceContent & RevisionManager ---\n";
$alphaFile = $tempDir . '/content/cat-a/alpha-doc.md';
$alphaChapter = [
    'docId' => $docId,
    'slug' => 'alpha-doc',
    'file' => 'content/cat-a/alpha-doc.md',
    'type' => 'markdown',
    'status' => 'published',
];

$res1 = DocumentService::replaceContent($alphaChapter, $alphaFile, [
    'content' => "# Alpha v2\nUpdated body.\n",
    'oldAbsFile' => $alphaFile,
    'oldRelFile' => 'content/cat-a/alpha-doc.md',
    'newRelFile' => 'content/cat-a/alpha-doc.md',
    'source' => 'api_publish',
    'actor' => 'tester',
    'event' => 'doc_replaced',
]);
assertTest(!empty($res1['success']), 'replaceContent succeeds');
assertTest($res1['changed'] === true, 'replaceContent detects content change');
assertTest($res1['sha256Before'] !== $res1['sha256After'], 'sha256Before differs from sha256After');

$revs = RevisionManager::listRevisions($docId);
assertTest(count($revs) === 1, 'Revision snapshot created before overwrite');
$revDetail = RevisionManager::getRevision($docId, $revs[0]['revId']);
assertTest($revDetail && strpos($revDetail['content'], 'Alpha v1') !== false, 'Revision snapshot preserves v1 content');

// 3. Lock migration on file relocation
echo "\n--- 3. LockManager::migrateLock ---\n";
$acq = LockManager::acquireLock('content/cat-a/alpha-doc.md', 'tab-1', 'tester');
assertTest(!empty($acq['success']), 'Lock acquired on original path');
LockManager::migrateLock('content/cat-a/alpha-doc.md', 'content/cat-b/alpha-doc.md');
assertTest(!file_exists(LockManager::getLockFilePath('content/cat-a/alpha-doc.md')), 'Old lock file removed after migration');
assertTest(file_exists(LockManager::getLockFilePath('content/cat-b/alpha-doc.md')), 'New lock file created after migration');
LockManager::releaseLock('content/cat-b/alpha-doc.md', 'tab-1', 'tester');

// 4. TrashManager: Category & Chapter Soft-Delete + Restore
echo "\n--- 4. TrashManager: Category & Chapter Soft-Delete + Restore ---\n";
$counts = TrashManager::countCategoryDescendants($config['books'][0]);
assertTest($counts['documents'] === 3 && $counts['categories'] === 0, 'countCategoryDescendants counts 3 docs');

$catManifest = TrashManager::trashCategory($config['books'][0], '', 'tester', 'ui_delete');
Navigation::deleteNodeRecursive($config['books'], 'cat-a');
Config::save($config);

assertTest(!file_exists($tempDir . '/content/cat-a/alpha-doc.md'), 'Trashed category files moved out of content/');
assertTest(count(TrashManager::listTrash()) === 1, 'Trash contains 1 trashed category');

$restoreRes = TrashManager::restoreItem($config, $catManifest['trashId'], 'tester');
assertTest(!empty($restoreRes['success']), 'Category restore succeeds');
assertTest(file_exists($tempDir . '/content/cat-a/alpha-doc.md'), 'Restored category files returned to content/');
assertTest(Navigation::findCategoryById($config['books'], 'cat-a') !== null, 'Restored category present in qwiki.json');
assertTest(count(TrashManager::listTrash()) === 0, 'Trash is empty after restore');

// Cleanup temp directory
function rrmdir($dir) {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $p = $dir . '/' . $item;
        is_dir($p) ? rrmdir($p) : @unlink($p);
    }
    @rmdir($dir);
}
rrmdir($tempDir);

echo "\n========================================\n";
echo "Tests Passed: {$passed}\n";
echo "Tests Failed: {$failed}\n";
echo "========================================\n";
exit($failed > 0 ? 1 : 0);
