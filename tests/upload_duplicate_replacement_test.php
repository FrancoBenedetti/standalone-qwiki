<?php
/**
 * Test Suite: Upload Duplicate Detection & Document Replacement
 *
 * Verifies:
 * 1. Navigation::isSlugTaken and Navigation::generateUniqueSlug
 * 2. upload_file duplicate collision detection (conflict response)
 * 3. upload_file with conflictAction = 'replace'
 * 4. upload_file with conflictAction = 'rename' (auto-incremented slug)
 * 5. replace_document_file endpoint (quick in-place replacement and protection)
 * 6. Deletion resilience ($deleted flag in delete_chapter_from_node)
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\Navigation;

echo "Running Upload Duplicate & Document Replacement Tests...\n\n";

$failures = 0;

function assertCondition($name, $condition, &$failures) {
    if ($condition) {
        echo "✅ PASS: {$name}\n";
    } else {
        echo "❌ FAIL: {$name}\n";
        $failures++;
    }
}

function runAdminApi(array $post, array $files = []): array {
    $script = sprintf('
        require_once "%s/../lib/Core/Config.php";
        require_once "%s/../lib/Core/Auth.php";
        require_once "%s/../lib/Core/Navigation.php";
        \Qwiki\Core\Config::init();
        \Qwiki\Core\Auth::startSession();
        $_SESSION["qwiki_instance"] = \Qwiki\Core\Auth::getInstanceIdentifier();
        $_SESSION["qwiki_user"] = ["username" => "admin", "role" => "admin", "fullName" => "Administrator"];
        $_SESSION["qwiki_admin"] = true;
        $_POST = %s;
        $_FILES = %s;
        $_SERVER["REQUEST_METHOD"] = "POST";
        $_REQUEST = $_POST;
        include "%s/../api/admin.php";
    ', __DIR__, __DIR__, __DIR__, var_export($post, true), var_export($files, true), __DIR__);

    $cmd = sprintf('php -r %s', escapeshellarg($script));
    $output = trim(shell_exec($cmd));
    $json = json_decode($output, true);
    return is_array($json) ? $json : ['raw' => $output];
}

// Backup qwiki.json
$baseDir = Config::getBaseDir();
$qwikiJsonPath = $baseDir . '/qwiki.json';
$backupQwikiJson = file_get_contents($qwikiJsonPath);

// Temporary test files to clean up
$createdFiles = [];

try {
    // ----------------------------------------------------
    // 1. Unit Tests: Navigation::isSlugTaken & generateUniqueSlug
    // ----------------------------------------------------
    echo "--- 1. Navigation::isSlugTaken & Navigation::generateUniqueSlug ---\n";
    $config = Config::load();
    $books = $config['books'] ?? [];

    assertCondition("isSlugTaken('getting-started', \$books) is true (root category)", Navigation::isSlugTaken('getting-started', $books), $failures);
    assertCondition("isSlugTaken('features', \$books) is true (document)", Navigation::isSlugTaken('features', $books), $failures);
    assertCondition("isSlugTaken('completely-nonexistent-xyz', \$books) is false", !Navigation::isSlugTaken('completely-nonexistent-xyz', $books), $failures);
    assertCondition("isSlugTaken('features', \$books, 'features') is false (current slug excluded)", !Navigation::isSlugTaken('features', $books, 'features'), $failures);

    // Test generateUniqueSlug with books
    $uniqueSlug1 = Navigation::generateUniqueSlug('features', $books);
    assertCondition("generateUniqueSlug('features') starts with 'features-'", strpos($uniqueSlug1, 'features-') === 0, $failures);

    $uniqueSlug2 = Navigation::generateUniqueSlug('nonexistent-test-slug', $books);
    assertCondition("generateUniqueSlug('nonexistent-test-slug') returns unchanged", $uniqueSlug2 === 'nonexistent-test-slug', $failures);

    // Test numeric increment: 'test-doc-1' should become 'test-doc-3' if 'test-doc-1' & 'test-doc-2' are taken
    $mockNodes = [
        ['id' => 'cat1', 'items' => [
            ['slug' => 'test-doc-1'],
            ['slug' => 'test-doc-2']
        ]]
    ];
    $incSlug = Navigation::generateUniqueSlug('test-doc-1', $mockNodes);
    assertCondition("generateUniqueSlug('test-doc-1') increments to 'test-doc-3'", $incSlug === 'test-doc-3', $failures);

    // Test with disk targetDir check in temp dir
    $testDir = sys_get_temp_dir() . '/qwiki_test_cat_' . uniqid();
    @mkdir($testDir, 0755, true);
    $mockDiskFile = $testDir . '/unique-disk-test.md';
    file_put_contents($mockDiskFile, "Disk test file");
    $createdFiles[] = $mockDiskFile;
    $createdFiles[] = $testDir;

    $diskSlug = Navigation::generateUniqueSlug('unique-disk-test', [], $testDir, 'md');
    assertCondition("generateUniqueSlug detects file on disk and increments to 'unique-disk-test-1'", $diskSlug === 'unique-disk-test-1', $failures);

    // ----------------------------------------------------
    // 2. Integration: upload_file duplicate conflict detection
    // ----------------------------------------------------
    echo "\n--- 2. upload_file Conflict Detection ---\n";
    $tmpUploadPath = sys_get_temp_dir() . '/upload_test_' . uniqid() . '.md';
    file_put_contents($tmpUploadPath, "# Uploaded Content\n\nNew upload version.");
    $createdFiles[] = $tmpUploadPath;

    $resp = runAdminApi([
        'action' => 'upload_file',
        'bookId' => 'getting-started',
        'title' => 'Features'
    ], [
        'document' => [
            'name' => 'features.md',
            'type' => 'text/markdown',
            'tmp_name' => $tmpUploadPath,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpUploadPath)
        ]
    ]);

    assertCondition("upload_file returns conflict: true when slug already exists", !empty($resp['conflict']), $failures);
    assertCondition("Conflict response contains existingSlug: 'features'", ($resp['existingSlug'] ?? '') === 'features', $failures);
    assertCondition("Conflict response suggests non-colliding slug", !empty($resp['suggestedSlug']) && strpos($resp['suggestedSlug'], 'features-') === 0, $failures);

    // ----------------------------------------------------
    // 3. Integration: upload_file with conflictAction = 'replace'
    // ----------------------------------------------------
    echo "\n--- 3. upload_file with conflictAction = 'replace' ---\n";
    $targetTestTitle = 'Doc To Replace ' . uniqid();
    $targetTestSlug = Config::makeSlug($targetTestTitle);
    $targetRelFile = 'content/getting-started/' . $targetTestSlug . '.md';
    $targetAbsFile = $baseDir . '/' . $targetRelFile;
    file_put_contents($targetAbsFile, "# Original Content\n\nOld version before replacement.");
    $createdFiles[] = $targetAbsFile;

    $cfg = Config::load();
    $cfg['books'][0]['items'][] = [
        'title' => $targetTestTitle,
        'slug' => $targetTestSlug,
        'type' => 'markdown',
        'file' => $targetRelFile,
        'customSettingTest' => 'preserved-123'
    ];
    Config::save($cfg);

    $tmpReplacePath = sys_get_temp_dir() . '/repl_' . uniqid() . '.md';
    file_put_contents($tmpReplacePath, "# Replaced Content\n\nThis is the newly uploaded content.");
    $createdFiles[] = $tmpReplacePath;

    $replaceResp = runAdminApi([
        'action' => 'upload_file',
        'bookId' => 'getting-started',
        'title' => $targetTestTitle,
        'conflictAction' => 'replace'
    ], [
        'document' => [
            'name' => 'doc-to-replace.md',
            'type' => 'text/markdown',
            'tmp_name' => $tmpReplacePath,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpReplacePath)
        ]
    ]);

    assertCondition("upload_file replace returns success: true", !empty($replaceResp['success']), $failures);
    assertCondition("upload_file replace returns action: 'replaced'", ($replaceResp['action'] ?? '') === 'replaced', $failures);

    $diskContentAfter = file_get_contents($targetAbsFile);
    assertCondition("Target file on disk contains newly replaced content", strpos($diskContentAfter, 'This is the newly uploaded content.') !== false, $failures);

    $cfgAfterReplace = Config::load();
    $matchingNodes = [];
    foreach ($cfgAfterReplace['books'][0]['items'] as $it) {
        if (($it['slug'] ?? '') === $targetTestSlug) {
            $matchingNodes[] = $it;
        }
    }
    assertCondition("Only 1 document entry exists in qwiki.json (no duplicate created)", count($matchingNodes) === 1, $failures);
    assertCondition("Custom document setting was preserved", ($matchingNodes[0]['customSettingTest'] ?? '') === 'preserved-123', $failures);

    // ----------------------------------------------------
    // 4. Integration: upload_file with conflictAction = 'rename'
    // ----------------------------------------------------
    echo "\n--- 4. upload_file with conflictAction = 'rename' ---\n";
    $tmpCopyPath = sys_get_temp_dir() . '/copy_' . uniqid() . '.md';
    file_put_contents($tmpCopyPath, "# Copy Content\n\nThis is the separate copy.");
    $createdFiles[] = $tmpCopyPath;

    $copyResp = runAdminApi([
        'action' => 'upload_file',
        'bookId' => 'getting-started',
        'title' => $targetTestTitle,
        'conflictAction' => 'rename'
    ], [
        'document' => [
            'name' => 'doc-to-replace.md',
            'type' => 'text/markdown',
            'tmp_name' => $tmpCopyPath,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpCopyPath)
        ]
    ]);

    assertCondition("upload_file rename returns success: true", !empty($copyResp['success']), $failures);
    assertCondition("upload_file rename returns action: 'created'", ($copyResp['action'] ?? '') === 'created', $failures);
    $createdSlug = $copyResp['slug'] ?? '';
    assertCondition("Created copy slug is different from original", $createdSlug !== $targetTestSlug, $failures);
    assertCondition("Created copy slug starts with original base slug", strpos($createdSlug, $targetTestSlug) === 0, $failures);

    $copyRelFile = 'content/getting-started/' . $createdSlug . '.md';
    $copyAbsFile = $baseDir . '/' . $copyRelFile;
    $createdFiles[] = $copyAbsFile;
    assertCondition("Original file still exists on disk", file_exists($targetAbsFile), $failures);
    assertCondition("New copy file exists on disk", file_exists($copyAbsFile), $failures);

    $cfgAfterCopy = Config::load();
    $origFound = Navigation::findChapterBySlug($cfgAfterCopy['books'], $targetTestSlug);
    $copyFound = Navigation::findChapterBySlug($cfgAfterCopy['books'], $createdSlug);
    assertCondition("Original document still present in qwiki.json", $origFound !== null, $failures);
    assertCondition("Copy document present in qwiki.json with unique slug", $copyFound !== null, $failures);

    // ----------------------------------------------------
    // 5. Integration: replace_document_file endpoint
    // ----------------------------------------------------
    echo "\n--- 5. replace_document_file Endpoint ---\n";
    $tmpDirectReplPath = sys_get_temp_dir() . '/direct_repl_' . uniqid() . '.md';
    file_put_contents($tmpDirectReplPath, "# Directly Replaced Content\n\nDirect replace successful.");
    $createdFiles[] = $tmpDirectReplPath;

    $directResp = runAdminApi([
        'action' => 'replace_document_file',
        'slug' => $targetTestSlug
    ], [
        'document' => [
            'name' => 'direct-replace.md',
            'type' => 'text/markdown',
            'tmp_name' => $tmpDirectReplPath,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpDirectReplPath)
        ]
    ]);

    assertCondition("replace_document_file returns success: true", !empty($directResp['success']), $failures);
    $directDiskContent = file_get_contents($targetAbsFile);
    assertCondition("Target file contains directly replaced content", strpos($directDiskContent, 'Direct replace successful.') !== false, $failures);

    // Test protection blocking on replace_document_file
    $cfgLock = Config::load();
    foreach ($cfgLock['books'][0]['items'] as &$it) {
        if (($it['slug'] ?? '') === $targetTestSlug) {
            $it['readOnly'] = true;
            $it['editable'] = false;
        }
    }
    Config::save($cfgLock);

    $tmpBlockedPath = sys_get_temp_dir() . '/blocked_' . uniqid() . '.md';
    file_put_contents($tmpBlockedPath, "Should be blocked");
    $createdFiles[] = $tmpBlockedPath;

    $blockedResp = runAdminApi([
        'action' => 'replace_document_file',
        'slug' => $targetTestSlug
    ], [
        'document' => [
            'name' => 'blocked.md',
            'type' => 'text/markdown',
            'tmp_name' => $tmpBlockedPath,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpBlockedPath)
        ]
    ]);
    assertCondition("replace_document_file fails when document is protected", empty($blockedResp['success']), $failures);

    // ----------------------------------------------------
    // 6. Deletion Resilience Test
    // ----------------------------------------------------
    echo "\n--- 6. Deletion Resilience ---\n";
    $cfgDup = Config::load();
    $dupSlug = 'duplicate-resilience-' . time();
    $cfgDup['books'][0]['items'][] = ['title' => 'Dup 1', 'slug' => $dupSlug, 'type' => 'markdown', 'file' => ''];
    $cfgDup['books'][0]['items'][] = ['title' => 'Dup 2', 'slug' => $dupSlug, 'type' => 'markdown', 'file' => ''];
    Config::save($cfgDup);

    $delResp = runAdminApi([
        'action' => 'delete_chapter',
        'slug' => $dupSlug
    ]);

    assertCondition("delete_chapter returns success: true", !empty($delResp['success']), $failures);

    $cfgAfterDel = Config::load();
    $remainingDups = [];
    foreach ($cfgAfterDel['books'][0]['items'] as $it) {
        if (($it['slug'] ?? '') === $dupSlug) {
            $remainingDups[] = $it;
        }
    }
    assertCondition("Single-item deletion resilience: Only 1 duplicate deleted, other preserved", count($remainingDups) === 1, $failures);

} finally {
    // Restore backup qwiki.json
    file_put_contents($qwikiJsonPath, $backupQwikiJson);

    // Clean up temporary files
    foreach ($createdFiles as $f) {
        if (is_file($f)) {
            @unlink($f);
        } elseif (is_dir($f)) {
            @rmdir($f);
        }
    }
}

echo "\n=======================================================\n";
if ($failures === 0) {
    echo "🎉 ALL UPLOAD DUPLICATE & REPLACEMENT TESTS PASSED!\n";
    exit(0);
} else {
    echo "❌ {$failures} TEST(S) FAILED.\n";
    exit(1);
}
