<?php
require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';
require_once __DIR__ . '/../lib/Core/LlmAccess.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\ExtensionManager;
use Qwiki\Core\LlmAccess;

function assert_test(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "[FAIL] {$msg}\n");
        exit(1);
    }
    echo "[PASS] {$msg}\n";
}

// Setup isolated temp workspace
$tempDir = sys_get_temp_dir() . '/qwiki_chat_test_' . bin2hex(random_bytes(4));
mkdir($tempDir . '/content', 0755, true);
copy(__DIR__ . '/../demo-data/qwiki-default.json', $tempDir . '/qwiki.json');
copy(__DIR__ . '/../demo-data/users-default.json', $tempDir . '/users.json');

// Copy demo content
$copyRecursive = function ($src, $dst) use (&$copyRecursive) {
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    foreach (scandir($src) as $item) {
        if ($item === '.' || $item === '..') continue;
        $s = $src . '/' . $item;
        $d = $dst . '/' . $item;
        if (is_dir($s)) {
            $copyRecursive($s, $d);
        } else {
            copy($s, $d);
        }
    }
};
$copyRecursive(__DIR__ . '/../demo-data/content', $tempDir . '/content');

register_shutdown_function(function () use ($tempDir) {
    $rmRecursive = function ($dir) use (&$rmRecursive) {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $p = $dir . '/' . $item;
            is_dir($p) ? $rmRecursive($p) : @unlink($p);
        }
        @rmdir($dir);
    };
    $rmRecursive($tempDir);
});

Config::init($tempDir);
// Ensure no env key interferes
putenv('GEMINI_API_KEY=');
putenv('QWIKI_GEMINI_API_KEY=');

$cfg = Config::load();
$extManager = ExtensionManager::getInstance();

// === Task 1: Activation Gate ===
assert_test(Config::hasSiteGeminiApiKey($cfg) === false, 'No site Gemini key initially');
assert_test($extManager->isExtensionEnabled('wiki_chat') === false, 'wiki_chat is disabled by default');

// Even if extensions['wiki_chat'] = true in qwiki.json, without a site key it remains disabled
$cfg['extensions']['wiki_chat'] = true;
$cfg['gemini']['apiKey'] = '';
Config::save($cfg);
assert_test($extManager->isExtensionEnabled('wiki_chat') === false, 'wiki_chat cannot be enabled when site API key is missing');

// When site API key is present AND extensions['wiki_chat'] = true, it is enabled
$cfg['gemini']['apiKey'] = 'AIzaSyTestSiteKey1234567890';
Config::save($cfg);
assert_test(Config::hasSiteGeminiApiKey() === true, 'Site Gemini key detected');
assert_test($extManager->isExtensionEnabled('wiki_chat') === true, 'wiki_chat is enabled when site key exists and extension is toggled on');

// When site API key is removed after being enabled, it auto-deactivates
$cfg['gemini']['apiKey'] = '';
Config::save($cfg);
assert_test($extManager->isExtensionEnabled('wiki_chat') === false, 'wiki_chat auto-deactivates if site API key is removed');

// === Task 2: WikiChatService ===
require_once __DIR__ . '/../assets/extensions/tool-wiki-chat/WikiChatService.php';
use Qwiki\Extension\WikiChat\WikiChatService;

// Re-enable site key & wiki_chat for Task 2 tests
$cfg['gemini']['apiKey'] = 'AIzaSiteKey1234';
$cfg['extensions']['wiki_chat'] = true;
Config::save($cfg);

Auth::startSession();

// 1. Access policy tests
$_SESSION = [];
$cfg['wikiChat'] = ['accessMode' => 'admin_only'];
$cfg['requireLoginToView'] = false;
Config::save($cfg);
assert_test(WikiChatService::canCurrentUserAccessChat($cfg) === false, 'admin_only denies anonymous');

$_SESSION['qwiki_instance'] = Auth::getInstanceIdentifier();
$_SESSION['qwiki_user'] = ['username' => 'bob', 'role' => 'viewer'];
assert_test(WikiChatService::canCurrentUserAccessChat($cfg) === false, 'admin_only denies viewer');

$_SESSION['qwiki_user'] = ['username' => 'admin', 'role' => 'admin'];
assert_test(WikiChatService::canCurrentUserAccessChat($cfg) === true, 'admin_only allows admin');

$cfg['wikiChat'] = ['accessMode' => 'logged_in'];
Config::save($cfg);
$_SESSION = [];
assert_test(WikiChatService::canCurrentUserAccessChat($cfg) === false, 'logged_in denies anonymous');
$_SESSION['qwiki_instance'] = Auth::getInstanceIdentifier();
$_SESSION['qwiki_user'] = ['username' => 'bob', 'role' => 'viewer'];
assert_test(WikiChatService::canCurrentUserAccessChat($cfg) === true, 'logged_in allows viewer');
$_SESSION['qwiki_user'] = ['username' => 'admin', 'role' => 'admin'];
assert_test(WikiChatService::canCurrentUserAccessChat($cfg) === true, 'logged_in allows admin');

$cfg['wikiChat'] = ['accessMode' => 'public'];
$cfg['requireLoginToView'] = false;
Config::save($cfg);
$_SESSION = [];
assert_test(WikiChatService::canCurrentUserAccessChat($cfg) === true, 'public allows anonymous when wiki is public');

$cfg['requireLoginToView'] = true;
Config::save($cfg);
assert_test(WikiChatService::canCurrentUserAccessChat($cfg) === false, 'public denies anonymous when requireLoginToView is true');
$cfg['requireLoginToView'] = false;
Config::save($cfg);

// 2. Two-tier BYOK precedence
$usersData = Config::loadUsers();
$usersData['users'][] = [
    'username' => 'alice',
    'passwordHash' => password_hash('secret', PASSWORD_DEFAULT),
    'role' => 'viewer',
    'geminiApiKey' => 'AIzaAliceKey9999',
    'geminiModel' => 'gemini-2.5-pro'
];
$usersData['users'][] = [
    'username' => 'bob',
    'passwordHash' => password_hash('secret', PASSWORD_DEFAULT),
    'role' => 'viewer'
];
Config::saveUsers($usersData);

$aliceCreds = WikiChatService::resolveLlmCredentials('alice');
assert_test($aliceCreds['apiKey'] === 'AIzaAliceKey9999' && $aliceCreds['keySource'] === 'user', 'BYOK user key overrides site key');
$bobCreds = WikiChatService::resolveLlmCredentials('bob');
assert_test($bobCreds['apiKey'] === 'AIzaSiteKey1234' && $bobCreds['keySource'] === 'site', 'User without personal key falls back to site key');

// 3. Wiki qwk_llm_... key provisioning & revocation resilience
$cfg['wikiChat'] = ['accessMode' => 'public', 'llmKeyId' => '', 'includeActiveDoc' => true, 'systemPromptExtra' => ''];
Config::save($cfg);
$keyRes = WikiChatService::resolveWikiLlmKey(null, true);
assert_test($keyRes['success'] === true && strpos($keyRes['key']['key'], 'qwk_llm_') === 0, 'Auto-provisioned qwk_llm_ key');
$autoKeyId = $keyRes['key']['id'];
$cfgAfter = Config::load();
assert_test(($cfgAfter['wikiChat']['llmKeyId'] ?? '') === $autoKeyId, 'Auto-provisioned key ID persisted in qwiki.json');

// Delete auto-managed key -> should auto-provision a fresh one
LlmAccess::deleteKey($autoKeyId);
$keyRes2 = WikiChatService::resolveWikiLlmKey(null, true);
assert_test($keyRes2['success'] === true && $keyRes2['key']['id'] !== $autoKeyId, 'Auto-managed deleted key triggers fresh key provisioning');

// Admin explicitly pinned key that is revoked -> returns 403 error
$pinned = LlmAccess::generateKey('Pinned Key', '', ['markdown']);
$pinnedId = $pinned['key']['id'];
$cfgPinned = Config::load();
$cfgPinned['wikiChat']['llmKeyId'] = $pinnedId;
$cfgPinned['wikiChat']['llmKeyAuto'] = false;
Config::save($cfgPinned);
LlmAccess::revokeKey($pinnedId, 'revoked');
$keyRes3 = WikiChatService::resolveWikiLlmKey(null, true);
assert_test($keyRes3['success'] === false && stripos($keyRes3['error'], 'revoked') !== false, 'Explicitly pinned revoked key returns error');

// Restore active auto key for remaining tests
$cfgReset = Config::load();
$cfgReset['wikiChat']['llmKeyId'] = '';
$cfgReset['wikiChat']['llmKeyAuto'] = true;
Config::save($cfgReset);
$activeKeyRes = WikiChatService::resolveWikiLlmKey(null, true);
$activeKeyRecord = $activeKeyRes['key'];

// 4. Caller visibility filtering for logged_in-restricted docs in public mode
$cfgVis = Config::load();
$privateMd = $tempDir . '/content/private-doc.md';
file_put_contents($privateMd, "# Secret Viewer Doc\n\nConfidential viewer content.");
$cfgVis['books'][0]['items'][] = [
    'id' => 'private-doc',
    'title' => 'Private Viewer Doc',
    'slug' => 'private-doc',
    'file' => 'content/private-doc.md',
    'type' => 'markdown',
    'status' => 'published',
    'visibility' => 'logged_in'
];
Config::save($cfgVis);

$anonTree = WikiChatService::executeToolCall('qwiki_list_tree', [], $activeKeyRecord, false, false);
$anonSlugs = array_column($anonTree['result']['documents'] ?? [], 'slug');
assert_test(!in_array('private-doc', $anonSlugs, true), 'Anonymous caller tree omits logged_in document');

$anonDoc = WikiChatService::executeToolCall('qwiki_get_document', ['slug' => 'private-doc'], $activeKeyRecord, false, false);
assert_test(($anonDoc['result']['success'] ?? true) === false, 'Anonymous caller cannot retrieve logged_in document');

$viewerDoc = WikiChatService::executeToolCall('qwiki_get_document', ['slug' => 'private-doc'], $activeKeyRecord, false, true);
assert_test(($viewerDoc['result']['success'] ?? false) === true, 'Signed-in viewer can retrieve logged_in document');

// 5. Grounded system prompt & unknown tool handling
$promptInfo = WikiChatService::buildSystemPrompt($activeKeyRecord, 'introduction', true, false, true);
assert_test(strpos($promptInfo['prompt'], 'api/llm.php') !== false, 'System prompt includes /api/llm.php spec');
assert_test(strpos($promptInfo['prompt'], 'Wiki Chat Assistant') !== false, 'System prompt includes key name');
assert_test(!empty($promptInfo['activeDoc']) && $promptInfo['activeDoc']['slug'] === 'introduction', 'System prompt includes active chapter context');

$unknownTool = WikiChatService::executeToolCall('unknown_tool', [], $activeKeyRecord, false, true);
assert_test(($unknownTool['result']['success'] ?? true) === false && strpos($unknownTool['result']['error'] ?? '', 'Unsupported tool') !== false, 'Unknown tool returns structured error');

echo "\nAll current tests passed!\n";
