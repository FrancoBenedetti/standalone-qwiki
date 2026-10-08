<?php
/**
 * Test Suite for Gemini AI Assistant Extension
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\ExtensionManager;

$testsPassed = 0;
$testsFailed = 0;

function assert_true($cond, $msg) {
    global $testsPassed, $testsFailed;
    if ($cond) {
        echo "PASS: {$msg}\n";
        $testsPassed++;
    } else {
        echo "FAIL: {$msg}\n";
        $testsFailed++;
    }
}

echo "=== Testing Gemini AI Assistant Extension ===\n\n";

// Set up isolated temp directory for test configuration & users to avoid sandbox ro restrictions
$testDir = sys_get_temp_dir() . '/qwiki_gemini_test_' . uniqid();
@mkdir($testDir . '/assets', 0755, true);
@symlink(dirname(__DIR__) . '/assets/extensions', $testDir . '/assets/extensions');
@copy(dirname(__DIR__) . '/qwiki.json', $testDir . '/qwiki.json');
if (file_exists(dirname(__DIR__) . '/users.json')) {
    @copy(dirname(__DIR__) . '/users.json', $testDir . '/users.json');
}
Config::init($testDir);

// 1. Discovery & Manifest
$extManager = ExtensionManager::getInstance();
$extManager->discover();
$utility = $extManager->getUtility('gemini_assistant');

assert_true(!empty($utility), "Gemini assistant utility is discovered");
assert_true(($utility['id'] ?? '') === 'gemini_assistant', "Utility ID is gemini_assistant");
assert_true(in_array('ext_gemini_generate_meta', $utility['actions'] ?? []), "Action ext_gemini_generate_meta is registered");
assert_true(in_array('ext_gemini_save_settings', $utility['actions'] ?? []), "Action ext_gemini_save_settings is registered");
assert_true(in_array('ext_gemini_test_connection', $utility['actions'] ?? []), "Action ext_gemini_test_connection is registered");
assert_true(in_array('ext_gemini_list_models', $utility['actions'] ?? []), "Action ext_gemini_list_models is registered");

// 2. Asset Discovery
$assets = $extManager->getFrontendAssets();
$hasScript = false;
$hasStyle = false;
foreach ($assets['scripts'] as $s) {
    if (strpos($s, 'tool-gemini-assistant/script.js') !== false) {
        $hasScript = true;
    }
}
foreach ($assets['styles'] as $st) {
    if (strpos($st, 'tool-gemini-assistant/style.css') !== false) {
        $hasStyle = true;
    }
}
assert_true($hasScript, "script.js is in frontend assets");
assert_true($hasStyle, "style.css is in frontend assets");

// 3. Modal Rendering
ob_start();
$extManager->renderUtilityModals();
$modalOutput = ob_get_clean();
assert_true(strpos($modalOutput, 'id="modal-gemini-assistant"') !== false, "Modal markup rendered with id modal-gemini-assistant");
assert_true(strpos($modalOutput, 'gemini-card-simulator') !== false, "OpenGraph card simulator is in modal markup");

// 4. Header Menu Button
ob_start();
$extManager->renderHeaderUtilityButtons();
$headerButtons = ob_get_clean();
assert_true(strpos($headerButtons, 'btn-util-gemini_assistant') !== false, "Header button btn-util-gemini_assistant is rendered");

// 5. Backend Handler - Security Check (Unauthenticated)
// Ensure no active session
Auth::startSession();
unset($_SESSION['qwiki_user']);
unset($_SESSION['qwiki_admin']);
unset($_SESSION['qwiki_logged_in']);
$_SESSION['qwiki_instance'] = Auth::getInstanceIdentifier();

ob_start();
$_POST = [];
$_GET = [];
$_REQUEST = ['action' => 'ext_gemini_get_settings'];
$handled = $extManager->handleAction('ext_gemini_get_settings', $_REQUEST);
$output = ob_get_clean();
$resp = json_decode($output, true);

assert_true($handled, "Action was dispatched to extension handler");
assert_true(is_array($resp) && empty($resp['success']) && ($resp['error'] ?? '') === 'Unauthorized', "Unauthenticated request is rejected with Unauthorized");

// 6. Backend Handler - Authenticated Admin
$_SESSION['qwiki_logged_in'] = true;
$_SESSION['qwiki_admin'] = true;
$_SESSION['qwiki_user'] = ['username' => 'admin', 'role' => 'admin'];
$_SESSION['qwiki_instance'] = Auth::getInstanceIdentifier();

// Back up initial gemini configuration if present to ensure clean testing environment
$preTestConfig = Config::load();
$origGeminiConfig = $preTestConfig['gemini'] ?? null;
if (isset($preTestConfig['gemini'])) {
    unset($preTestConfig['gemini']);
    Config::save($preTestConfig);
}

// Test Get Settings
ob_start();
$handled = $extManager->handleAction('ext_gemini_get_settings', ['action' => 'ext_gemini_get_settings']);
$output = ob_get_clean();
$resp = json_decode($output, true);
assert_true(is_array($resp) && !empty($resp['success']), "ext_gemini_get_settings succeeds for admin");
assert_true(isset($resp['model']), "Settings response includes model");
assert_true(isset($resp['hasKey']), "Settings response includes hasKey flag");

// Test Demo Mode / Mock Generation when no key is set
putenv('GEMINI_API_KEY=');
putenv('QWIKI_GEMINI_API_KEY=');

ob_start();
$handled = $extManager->handleAction('ext_gemini_generate_meta', [
    'action' => 'ext_gemini_generate_meta',
    'slug' => 'introduction',
    'title' => 'Introduction'
]);
$output = ob_get_clean();
$resp = json_decode($output, true);

// In non-demo without key, it should prompt to configure key.
// Let's verify graceful error reporting or response:
assert_true(is_array($resp), "ext_gemini_generate_meta returns valid JSON response");
if (!empty($resp['success'])) {
    assert_true(!empty($resp['metadata']['description']), "Generated metadata has description");
    assert_true(is_array($resp['metadata']['tags']), "Generated metadata has tags array");
} else {
    assert_true(strpos($resp['error'], 'key') !== false, "Reports clear API key requirement when key is missing: " . $resp['error']);
}

// 7. Test ext_gemini_save_settings
ob_start();
$handled = $extManager->handleAction('ext_gemini_save_settings', [
    'action' => 'ext_gemini_save_settings',
    'apiKey' => 'AIzaSyTestKey1234567890TestKey987654321',
    'model' => 'gemini-2.5-pro'
]);
$output = ob_get_clean();
$resp = json_decode($output, true);
assert_true(!empty($resp['success']), "ext_gemini_save_settings successfully saves key and model");
assert_true(($resp['model'] ?? '') === 'gemini-2.5-pro', "Saved model is gemini-2.5-pro");
assert_true(strpos($resp['maskedKey'] ?? '', '••••') !== false, "API key is masked in response");

// Verify get_settings reflects changes
ob_start();
$handled = $extManager->handleAction('ext_gemini_get_settings', ['action' => 'ext_gemini_get_settings']);
$output = ob_get_clean();
$resp = json_decode($output, true);
assert_true(($resp['model'] ?? '') === 'gemini-2.5-pro', "get_settings reports saved model");
assert_true(!empty($resp['hasKey']), "get_settings reports hasKey true");

// 8. Test ext_gemini_apply_meta
$cfgBefore = Config::load();
$origIntroDesc = '';
$origIntroTags = null;
foreach ($cfgBefore['books'] as $b) {
    foreach ($b['items'] ?? [] as $it) {
        if (($it['slug'] ?? '') === 'introduction') {
            $origIntroDesc = $it['description'] ?? '';
            $origIntroTags = $it['tags'] ?? null;
            break 2;
        }
    }
}

$testNewDesc = "High-impact social summary generated by Gemini AI test suite.";
$testTags = ['Qwiki', 'Testing', 'AI'];

ob_start();
$handled = $extManager->handleAction('ext_gemini_apply_meta', [
    'action' => 'ext_gemini_apply_meta',
    'slug' => 'introduction',
    'description' => $testNewDesc,
    'tags' => $testTags
]);
$output = ob_get_clean();
$resp = json_decode($output, true);
assert_true(!empty($resp['success']), "ext_gemini_apply_meta succeeds");
assert_true(($resp['description'] ?? '') === $testNewDesc, "Applied description matches test description");

// Verify persistence in qwiki.json
$cfgAfter = Config::load();
$updatedDesc = '';
$updatedTags = [];
foreach ($cfgAfter['books'] as $b) {
    foreach ($b['items'] ?? [] as $it) {
        if (($it['slug'] ?? '') === 'introduction') {
            $updatedDesc = $it['description'] ?? '';
            $updatedTags = $it['tags'] ?? [];
            break 2;
        }
    }
}
assert_true($updatedDesc === $testNewDesc, "Description successfully persisted to qwiki.json");
assert_true($updatedTags === $testTags, "Tags successfully persisted to qwiki.json");

// Restore original introduction metadata and clear test key
foreach ($cfgAfter['books'] as &$b) {
    foreach ($b['items'] ?? [] as &$it) {
        if (($it['slug'] ?? '') === 'introduction') {
            $it['description'] = $origIntroDesc;
            if ($origIntroTags !== null) {
                $it['tags'] = $origIntroTags;
            } else {
                unset($it['tags']);
            }
            break 2;
        }
    }
}
if ($origGeminiConfig !== null) {
    $cfgAfter['gemini'] = $origGeminiConfig;
} else {
    unset($cfgAfter['gemini']);
}
Config::save($cfgAfter);
echo "PASS: Cleanup restored original introduction metadata and removed test gemini config\n";
$testsPassed++;

// 8b. Two-Tier Hierarchical BYOK API Key Tests
echo "\n--- Two-Tier BYOK Hierarchy & Role Tests ---\n";

// Ensure clean state: configure a Site Default Key
$siteKeyTest = 'AIzaSySiteWideDefaultKey99999999999';
$userKeyTest = 'AIzaSyUserPersonalKeyOverride11111';

ob_start();
$handled = $extManager->handleAction('ext_gemini_save_settings', [
    'action' => 'ext_gemini_save_settings',
    'siteApiKey' => $siteKeyTest,
    'siteModel' => 'gemini-2.5-flash'
]);
$output = ob_get_clean();
$resp = json_decode($output, true);
assert_true(!empty($resp['success']), "Admin can save site-wide default key");
assert_true(($resp['keySource'] ?? '') === 'site', "Effective keySource is 'site' when no user key is set");
assert_true(!empty($resp['hasSiteKey']), "Reports hasSiteKey true");
assert_true(empty($resp['hasUserKey']), "Reports hasUserKey false when personal key not yet set");

// Admin sets a personal key override
ob_start();
$handled = $extManager->handleAction('ext_gemini_save_settings', [
    'action' => 'ext_gemini_save_settings',
    'userApiKey' => $userKeyTest,
    'userModel' => 'gemini-2.5-pro'
]);
$output = ob_get_clean();
$resp = json_decode($output, true);
assert_true(!empty($resp['success']), "Admin can set personal user key override");
assert_true(($resp['keySource'] ?? '') === 'user', "keySource switches to 'user' when personal key is set");
assert_true(!empty($resp['hasUserKey']), "Reports hasUserKey true");
assert_true(($resp['model'] ?? '') === 'gemini-2.5-pro', "Effective model reflects userModel override");

// Admin clears personal key override to revert to site default key
ob_start();
$handled = $extManager->handleAction('ext_gemini_save_settings', [
    'action' => 'ext_gemini_save_settings',
    'clearUserKey' => '1'
]);
$output = ob_get_clean();
$resp = json_decode($output, true);
assert_true(!empty($resp['success']), "Clearing personal key succeeds");
assert_true(($resp['keySource'] ?? '') === 'site', "keySource reverts to 'site' after clearing user key");
assert_true(empty($resp['hasUserKey']), "hasUserKey is false after clearing user key");
assert_true(($resp['model'] ?? '') === 'gemini-2.5-flash', "Effective model reverts to siteModel");

// Viewer Role Tests
Auth::addUser('viewer_test_user', 'password123', 'viewer');
$_SESSION['qwiki_logged_in'] = true;
$_SESSION['qwiki_admin'] = false;
$_SESSION['qwiki_user'] = ['username' => 'viewer_test_user', 'role' => 'viewer'];

// Viewer reads settings
ob_start();
$handled = $extManager->handleAction('ext_gemini_get_settings', ['action' => 'ext_gemini_get_settings']);
$output = ob_get_clean();
$resp = json_decode($output, true);
assert_true(!empty($resp['success']), "Viewer can read Gemini settings");
assert_true(empty($resp['isAdmin']), "Viewer isAdmin flag is false");
assert_true(($resp['username'] ?? '') === 'viewer_test_user', "Reports viewer username");
assert_true(($resp['keySource'] ?? '') === 'site', "Viewer inherits site default key by default");

// Viewer sets their own personal key
$viewerPersonalKey = 'AIzaSyViewerPersonalFreeKey77777';
ob_start();
$handled = $extManager->handleAction('ext_gemini_save_settings', [
    'action' => 'ext_gemini_save_settings',
    'userApiKey' => $viewerPersonalKey
]);
$output = ob_get_clean();
$resp = json_decode($output, true);
assert_true(!empty($resp['success']), "Viewer can save personal key override");
assert_true(($resp['keySource'] ?? '') === 'user', "Viewer effective keySource is now 'user'");
assert_true(!empty($resp['hasUserKey']), "Viewer hasUserKey is true");

// Viewer attempts to tamper with site default key
ob_start();
$handled = $extManager->handleAction('ext_gemini_save_settings', [
    'action' => 'ext_gemini_save_settings',
    'siteApiKey' => 'HackedSiteKey'
]);
$output = ob_get_clean();
$resp = json_decode($output, true);
assert_true(empty($resp['success']) && strpos($resp['error'] ?? '', 'Administrator') !== false, "Viewer blocked from modifying site default key");

// Viewer attempts to apply metadata directly to documents
ob_start();
$handled = $extManager->handleAction('ext_gemini_apply_meta', [
    'action' => 'ext_gemini_apply_meta',
    'slug' => 'introduction',
    'description' => 'Viewer trying to alter doc meta',
    'tags' => ['hacked']
]);
$output = ob_get_clean();
$resp = json_decode($output, true);
assert_true(empty($resp['success']) && strpos($resp['error'] ?? '', 'Administrator') !== false, "Viewer blocked from applying metadata to documents");

// Multi-User Independence: Another user does not have viewer_test_user's personal key
$_SESSION['qwiki_user'] = ['username' => 'second_user', 'role' => 'viewer'];
ob_start();
$handled = $extManager->handleAction('ext_gemini_get_settings', ['action' => 'ext_gemini_get_settings']);
$output = ob_get_clean();
$resp = json_decode($output, true);
assert_true(($resp['keySource'] ?? '') === 'site', "Second user uses site default key, independent from first user's key");
assert_true(empty($resp['hasUserKey']), "Second user has no user key set");

// Test Header Utilities Filtering for Viewers vs Admins
ob_start();
$extManager->renderHeaderUtilityButtons(false); // viewer mode
$viewerHeaderHtml = ob_get_clean();
assert_true(strpos($viewerHeaderHtml, 'btn-util-gemini_assistant') !== false, "Viewer sees Gemini assistant in header utilities");
assert_true(strpos($viewerHeaderHtml, 'btn-util-backup') === false, "Viewer does not see admin-only Backup utility");
assert_true(strpos($viewerHeaderHtml, 'btn-util-postbox') === false, "Viewer does not see admin-only Postbox utility");

ob_start();
$extManager->renderHeaderUtilityButtons(true); // admin mode
$adminHeaderHtml = ob_get_clean();
assert_true(strpos($adminHeaderHtml, 'btn-util-gemini_assistant') !== false, "Admin sees Gemini assistant");
assert_true(strpos($adminHeaderHtml, 'btn-util-backup') !== false, "Admin sees Backup utility");
assert_true(strpos($adminHeaderHtml, 'btn-util-postbox') !== false, "Admin sees Postbox utility");

// Restore admin session and cleanup viewer_test_user
$_SESSION['qwiki_admin'] = true;
$_SESSION['qwiki_user'] = ['username' => 'admin', 'role' => 'admin'];
Auth::deleteUser('viewer_test_user');
Auth::updateUserGeminiSettings('admin', ''); // clear admin test key

// 9. Test Demo Mode Safeguards
putenv('QWIKI_DEMO_MODE=1');
$_SERVER['QWIKI_DEMO_MODE'] = '1';

ob_start();
$handled = $extManager->handleAction('ext_gemini_save_settings', [
    'action' => 'ext_gemini_save_settings',
    'apiKey' => 'NewKeyInDemoMode'
]);
$output = ob_get_clean();
$resp = json_decode($output, true);
assert_true(empty($resp['success']) && strpos($resp['error'] ?? '', 'Demo Mode') !== false, "Demo Mode blocks saving API keys");

ob_start();
$handled = $extManager->handleAction('ext_gemini_generate_meta', [
    'action' => 'ext_gemini_generate_meta',
    'slug' => 'introduction',
    'title' => 'Introduction'
]);
$output = ob_get_clean();
$resp = json_decode($output, true);
assert_true(!empty($resp['success']) && !empty($resp['isMock']), "Demo Mode returns simulated metadata without real API key");
assert_true(!empty($resp['metadata']['description']), "Demo Mode mock metadata has description");
assert_true(is_array($resp['metadata']['tags']), "Demo Mode mock metadata has tags");

ob_start();
$handled = $extManager->handleAction('ext_gemini_list_models', [
    'action' => 'ext_gemini_list_models'
]);
$output = ob_get_clean();
$resp = json_decode($output, true);
assert_true(!empty($resp['success']) && !empty($resp['models']), "Demo Mode ext_gemini_list_models returns list of models");
assert_true(is_array($resp['models']) && count($resp['models']) > 0, "Demo Mode models array has items");

// Cleanup Demo Mode
putenv('QWIKI_DEMO_MODE=');
unset($_SERVER['QWIKI_DEMO_MODE']);

// Final cleanup: ensure test keys don't linger in qwiki.json
$finalConfig = Config::load();
if ($origGeminiConfig !== null) {
    $finalConfig['gemini'] = $origGeminiConfig;
} else {
    unset($finalConfig['gemini']);
}
Config::save($finalConfig);

echo "\n=== Tests Complete: {$testsPassed} Passed, {$testsFailed} Failed ===\n";
exit($testsFailed === 0 ? 0 : 1);

