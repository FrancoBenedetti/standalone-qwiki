<?php
/**
 * Automated Test Suite for Standalone Qwiki - Backup & Export Extension
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\ExtensionManager;

$passed = 0;
$failed = 0;

function assert_true($condition, $testName) {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$testName}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$testName}\n";
        $failed++;
    }
}

echo "=== Running Backup & Export Extension Tests ===\n\n";

// TEST 1: Extension Registration in ExtensionManager
echo "1. Testing Extension Registration...\n";
$extManager = ExtensionManager::getInstance();
$utilities = $extManager->getUtilities();
assert_true(isset($utilities['backup']), "Backup extension is discovered by ExtensionManager");
assert_true(($utilities['backup']['type'] ?? '') === 'utility', "Extension type is 'utility'");
assert_true(in_array('ext_backup_scan', $utilities['backup']['actions'] ?? []), "Registers action 'ext_backup_scan'");
assert_true(in_array('ext_backup_download', $utilities['backup']['actions'] ?? []), "Registers action 'ext_backup_download'");

// TEST 2: Authorization & Handler Routing
echo "\n2. Testing Authorization & Routing...\n";
$baseDir = Config::getBaseDir();

Auth::startSession();
// First test: non-admin should be rejected
$_SESSION['qwiki_instance'] = Auth::getInstanceIdentifier();
$_SESSION['qwiki_user'] = ['username' => 'guest', 'role' => 'viewer'];

ob_start();
$_REQUEST['action'] = 'ext_backup_scan';
$extManager->handleAction('ext_backup_scan', $_REQUEST);
$nonAdminOutput = ob_get_clean();
$nonAdminJson = json_decode($nonAdminOutput, true);
assert_true(isset($nonAdminJson['success']) && $nonAdminJson['success'] === false, "Non-admin access is rejected with unauthorized error");

// Second test: admin user should succeed
$_SESSION['qwiki_user'] = ['username' => 'admin', 'role' => 'admin'];

ob_start();
$_REQUEST['action'] = 'ext_backup_scan';
$result = $extManager->handleAction('ext_backup_scan', $_REQUEST);
$scanOutput = ob_get_clean();
$scanJson = json_decode($scanOutput, true);

assert_true($result === true, "ExtensionManager routes ext_backup_scan successfully");
assert_true(isset($scanJson['success']) && $scanJson['success'] === true, "ext_backup_scan returns success: true for admin");
assert_true(isset($scanJson['summary']['content']['filesCount']), "Summary contains content file count");
assert_true(isset($scanJson['summary']['qwikiJson']), "Summary contains qwiki.json info");
assert_true(isset($scanJson['tree']['content']), "Tree contains content directory hierarchy");

// TEST 3: Path Traversal Defenses
echo "\n3. Testing Directory Traversal Defenses...\n";
assert_true(function_exists('backupIsSafePath'), "backupIsSafePath function exists");

$safe1 = backupIsSafePath('../../etc/passwd', $baseDir);
assert_true($safe1 === false, "Rejects relative traversal '../../etc/passwd'");

$safe2 = backupIsSafePath('content/../../../etc/shadow', $baseDir);
assert_true($safe2 === false, "Rejects embedded traversal 'content/../../../etc/shadow'");

$safe3 = backupIsSafePath('.env', $baseDir);
assert_true($safe3 === false, "Rejects sensitive file '.env'");

$safe4 = backupIsSafePath('.git', $baseDir);
assert_true($safe4 === false, "Rejects internal repository directory '.git'");

$safe5 = backupIsSafePath('content', $baseDir);
assert_true($safe5 !== false && is_dir($safe5), "Accepts valid directory 'content'");

$safe6 = backupIsSafePath('qwiki.json', $baseDir);
assert_true($safe6 !== false && is_file($safe6), "Accepts valid file 'qwiki.json'");

// TEST 4: ZIP Creation & Content Verification
echo "\n4. Testing ZIP Generation & Integrity...\n";
assert_true(class_exists('ZipArchive'), "ZipArchive PHP extension is installed");

// Test full archive creation directly using handler logic
$tempZip = tempnam(sys_get_temp_dir(), 'test_qwiki_bkp_');
$zip = new \ZipArchive();
$openRes = $zip->open($tempZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
assert_true($openRes === true, "Successfully created temporary ZIP archive");

$fileMap = [];
if (file_exists($baseDir . '/qwiki.json')) {
    $fileMap[$baseDir . '/qwiki.json'] = 'qwiki.json';
}
if (file_exists($baseDir . '/users.json')) {
    $fileMap[$baseDir . '/users.json'] = 'users.json';
}
if (is_dir($baseDir . '/content')) {
    backupCollectDirFiles($baseDir . '/content', $baseDir, $fileMap);
}

$filesAdded = 0;
foreach ($fileMap as $diskPath => $zipPath) {
    if (is_file($diskPath)) {
        $zip->addFile($diskPath, $zipPath);
        $filesAdded++;
    }
}
$zip->close();

assert_true($filesAdded > 0, "Added {$filesAdded} files to ZIP archive");
assert_true(file_exists($tempZip) && filesize($tempZip) > 100, "ZIP archive exists and has non-zero size");

// Verify reading ZIP back
$verifyZip = new \ZipArchive();
$vOpen = $verifyZip->open($tempZip);
assert_true($vOpen === true, "Re-opened ZIP archive for verification");
assert_true($verifyZip->locateName('qwiki.json') !== false, "ZIP contains 'qwiki.json'");
assert_true($verifyZip->numFiles === $filesAdded, "ZIP entry count matches expected file count");

// Clean up
$verifyZip->close();
@unlink($tempZip);

// TEST 5: Selective Mode Validation
echo "\n5. Testing Selective ZIP Generation...\n";
$selZip = tempnam(sys_get_temp_dir(), 'test_qwiki_sel_');
$zip2 = new \ZipArchive();
$zip2->open($selZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

// Select ONLY qwiki.json
$selFiles = ['qwiki.json'];
$selAdded = 0;
foreach ($selFiles as $item) {
    $path = backupIsSafePath($item, $baseDir);
    if ($path && is_file($path)) {
        $zip2->addFile($path, $item);
        $selAdded++;
    }
}
$zip2->close();

$verifySel = new \ZipArchive();
$verifySel->open($selZip);
assert_true($verifySel->numFiles === 1, "Selective ZIP contains exactly 1 file");
assert_true($verifySel->locateName('qwiki.json') !== false, "Selective ZIP contains 'qwiki.json'");
assert_true($verifySel->locateName('users.json') === false, "Selective ZIP excludes unselected 'users.json'");
$verifySel->close();
@unlink($selZip);

// Summary
echo "\n=== Test Results ===\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
echo "All backup tests passed successfully!\n";
exit(0);
