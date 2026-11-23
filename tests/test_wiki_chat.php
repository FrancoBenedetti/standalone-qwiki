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

echo "\nAll current tests passed!\n";
