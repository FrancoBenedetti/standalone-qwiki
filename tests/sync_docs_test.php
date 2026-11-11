<?php
/**
 * Standalone Qwiki - Documentation Subwiki Synchronization Tests
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/SubwikiManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\SubwikiManager;

Config::init();
$baseDir = Config::getBaseDir();

echo "Running Documentation Subwiki Synchronization Tests...\n\n";

$testSlug = 'test-docs-' . substr(md5(uniqid()), 0, 6);
$testDir = $baseDir . '/' . $testSlug;

function cleanupTestDir($dir) {
    if (!file_exists($dir)) return;
    if (is_link($dir)) {
        @unlink($dir);
        return;
    }
    if (is_dir($dir)) {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_link($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                cleanupTestDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}

try {
    // ----------------------------------------------------
    // 1. Direct SubwikiManager::syncDocumentation Test
    // ----------------------------------------------------
    echo "1. Testing SubwikiManager::syncDocumentation('{$testSlug}', 'copy'): ";

    $res = SubwikiManager::syncDocumentation($testSlug, 'copy');
    assert($res['success'] === true, "syncDocumentation must succeed: " . ($res['error'] ?? ''));
    assert($res['mode'] === 'copy', "Mode must be 'copy'");
    assert(is_dir($testDir), "Target directory must exist: {$testDir}");
    assert(file_exists($testDir . '/qwiki.json'), "qwiki.json must exist in target subwiki");
    assert(file_exists($testDir . '/.subwiki'), ".subwiki marker must exist in target subwiki");
    assert(file_exists($testDir . '/index.php'), "index.php must exist in target subwiki");
    assert(is_dir($testDir . '/content'), "content/ directory must exist in target subwiki");
    assert(file_exists($testDir . '/content/.htaccess'), "content/.htaccess must exist");

    $childConfig = json_decode(file_get_contents($testDir . '/qwiki.json'), true);
    assert(!empty($childConfig['isSubwiki']), "childConfig['isSubwiki'] must be true");
    assert(!empty($childConfig['isDocSubwiki']), "childConfig['isDocSubwiki'] must be true");
    assert(!empty($childConfig['books']), "childConfig['books'] must not be empty");

    // Verify presence of canonical markdown doc
    assert(file_exists($testDir . '/content/getting-started/introduction.md'), "introduction.md must be synced to child");

    echo "PASS\n";

    // ----------------------------------------------------
    // 2. Parent Subwiki Registry Verification
    // ----------------------------------------------------
    echo "2. Verifying Subwiki Registration in Parent Config: ";

    $parentConfig = Config::load();
    $found = false;
    foreach ($parentConfig['subwikis'] as $sub) {
        if ($sub['slug'] === $testSlug) {
            $found = true;
            assert(!empty($sub['isDocSubwiki']), "isDocSubwiki must be true in parent registry");
            break;
        }
    }
    assert($found === true, "Subwiki '{$testSlug}' must be registered in parent config");

    $subList = SubwikiManager::listSubwikis();
    $foundInList = false;
    foreach ($subList as $s) {
        if ($s['slug'] === $testSlug) {
            $foundInList = true;
            assert(!empty($s['isDocSubwiki']), "listSubwikis must flag isDocSubwiki");
            break;
        }
    }
    assert($foundInList === true, "Subwiki must be present in SubwikiManager::listSubwikis()");

    echo "PASS\n";

    // ----------------------------------------------------
    // 3. CLI Script Execution (bin/sync-subwiki-docs.php & sync-docs.php)
    // ----------------------------------------------------
    echo "3. Testing CLI sync-docs.php and bin/sync-subwiki-docs.php: ";

    exec('php ' . escapeshellarg($baseDir . '/sync-docs.php') . ' ' . escapeshellarg($testSlug) . ' --copy 2>&1', $output1, $code1);
    assert($code1 === 0, "sync-docs.php CLI must exit with 0. Output: " . implode("\n", $output1));

    exec('php ' . escapeshellarg($baseDir . '/bin/sync-subwiki-docs.php') . ' ' . escapeshellarg($testSlug) . ' --copy 2>&1', $output2, $code2);
    assert($code2 === 0, "bin/sync-subwiki-docs.php CLI must exit with 0. Output: " . implode("\n", $output2));

    echo "PASS\n";

    // ----------------------------------------------------
    // 4. Web Execution Security (sync-docs.php via HTTP simulation)
    // ----------------------------------------------------
    echo "4. Testing Web Security & Token Auth in sync-docs.php: ";

    // Simulation A: Unauthenticated request should be rejected (403)
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $_REQUEST = ['slug' => $testSlug, 'format' => 'json'];
    $_GET = $_REQUEST;
    $_SESSION = []; // No session

    ob_start();
    // Use subprocess to test web invocation cleanly
    $cmdUnauthorized = 'php -r ' . escapeshellarg('
        $_SERVER["REQUEST_METHOD"] = "GET";
        $_REQUEST = ["slug" => "' . $testSlug . '", "format" => "json"];
        require "' . $baseDir . '/sync-docs.php";
    ') . ' 2>&1';
    exec($cmdUnauthorized, $outUnauth, $retUnauth);
    $jsonUnauth = json_decode(implode('', $outUnauth), true);
    assert(is_array($jsonUnauth) && $jsonUnauth['success'] === false, "Unauthenticated request must fail");

    // Simulation B: Token authenticated request should succeed
    $token = $parentConfig['feedAccessToken'] ?? 'test-sync-token-' . uniqid();
    if (empty($parentConfig['feedAccessToken'])) {
        $parentConfig['feedAccessToken'] = $token;
        Config::save($parentConfig);
    }

    $cmdAuthorized = 'php -r ' . escapeshellarg('
        $_SERVER["REQUEST_METHOD"] = "GET";
        $_REQUEST = ["slug" => "' . $testSlug . '", "format" => "json", "token" => "' . $token . '", "mode" => "copy"];
        require "' . $baseDir . '/sync-docs.php";
    ') . ' 2>&1';
    exec($cmdAuthorized, $outAuth, $retAuth);
    $jsonAuth = json_decode(implode('', $outAuth), true);
    assert(is_array($jsonAuth) && $jsonAuth['success'] === true, "Token authenticated request must succeed: " . implode('', $outAuth));
    assert($jsonAuth['slug'] === $testSlug, "Returned slug must match target");

    echo "PASS\n";

    // ----------------------------------------------------
    // 5. Admin API (api/admin.php?action=sync_subwiki_docs)
    // ----------------------------------------------------
    echo "5. Testing api/admin.php?action=sync_subwiki_docs: ";

    // Test with admin auth
    $cmdAdminApi = 'php -r ' . escapeshellarg('
        require "' . $baseDir . '/lib/Core/Config.php";
        require "' . $baseDir . '/lib/Core/Auth.php";
        \Qwiki\Core\Config::init();
        // Mock admin session
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION["user"] = ["username" => "admin", "role" => "admin"];
        $_SERVER["REQUEST_METHOD"] = "POST";
        $_POST = ["action" => "sync_subwiki_docs", "slug" => "' . $testSlug . '", "mode" => "copy"];
        $_REQUEST = $_POST;
        require "' . $baseDir . '/api/admin.php";
    ') . ' 2>&1';
    exec($cmdAdminApi, $outAdmin, $retAdmin);
    $jsonAdmin = json_decode(implode('', $outAdmin), true);
    assert(is_array($jsonAdmin) && $jsonAdmin['success'] === true, "Admin API must succeed: " . implode('', $outAdmin));

    echo "PASS\n";

    // ----------------------------------------------------
    // 6. User Data Protection & Anti-Overwrite Safety Guard
    // ----------------------------------------------------
    echo "6. Testing Anti-Overwrite Protection for Custom User Subwikis: ";

    $customSlug = 'user-corp-docs-' . substr(md5(uniqid()), 0, 6);
    $customDir = $baseDir . '/' . $customSlug;

    // Simulate custom company subwiki with user content and NO isDocSubwiki flag
    $depCustom = SubwikiManager::deploySubwiki($customSlug, 'Acme Corp Internal Docs', 'admin', 'pass123', false);
    assert($depCustom['success'] === true, "Custom subwiki deployment must succeed");

    // Add user-authored books
    $customConfigPath = $customDir . '/qwiki.json';
    $customConfig = json_decode(file_get_contents($customConfigPath), true);
    $customConfig['books'] = [
        [
            'id' => 'acme-sop',
            'title' => 'Acme Internal SOPs',
            'items' => [['slug' => 'welcome', 'title' => 'Welcome to Acme', 'file' => 'content/welcome.md']]
        ]
    ];
    $customConfig['isDocSubwiki'] = false;
    file_put_contents($customConfigPath, json_encode($customConfig, JSON_PRETTY_PRINT), LOCK_EX);

    // Verify listSubwikis does NOT treat it as a doc subwiki even if named docs
    $subwikisCheck = SubwikiManager::listSubwikis();
    foreach ($subwikisCheck as $sc) {
        if ($sc['slug'] === $customSlug) {
            assert(empty($sc['isDocSubwiki']), "Custom subwiki must NOT have isDocSubwiki flag");
        }
    }

    // Attempt sync WITHOUT force - MUST BE REJECTED to protect user data
    $safeSyncResult = SubwikiManager::syncDocumentation($customSlug, 'copy', false);
    assert($safeSyncResult['success'] === false, "Sync without force MUST fail on custom subwiki");
    assert(!empty($safeSyncResult['requiresForce']), "Result must flag requiresForce");

    // Verify user content was preserved and NOT overwritten
    $verifyCustomCfg = json_decode(file_get_contents($customConfigPath), true);
    assert($verifyCustomCfg['title'] === 'Acme Corp Internal Docs', "User custom title must be preserved");
    assert($verifyCustomCfg['books'][0]['id'] === 'acme-sop', "User custom books must not be replaced");

    // Now test with force = true - should succeed when explicitly forced
    $forcedSyncResult = SubwikiManager::syncDocumentation($customSlug, 'copy', true);
    assert($forcedSyncResult['success'] === true, "Forced sync must succeed when explicitly requested");

    cleanupTestDir($customDir);
    $cfg = Config::load();
    if (!empty($cfg['subwikis'])) {
        $cfg['subwikis'] = array_values(array_filter($cfg['subwikis'], fn($s) => ($s['slug'] ?? '') !== $customSlug));
        Config::save($cfg);
    }

    echo "PASS\n";

} finally {
    // Clean up test subwiki
    cleanupTestDir($testDir);
    // Remove from parent config if present
    $cfg = Config::load();
    if (!empty($cfg['subwikis'])) {
        $cfg['subwikis'] = array_values(array_filter($cfg['subwikis'], fn($s) => ($s['slug'] ?? '') !== $testSlug));
        Config::save($cfg);
    }
}

echo "\nAll Documentation Subwiki Sync tests PASSED!\n";
