<?php
/**
 * Automated Test Suite for Standalone Qwiki - Talk Pages Extension
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';
require_once __DIR__ . '/../assets/extensions/tool-talk/TalkService.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\Navigation;
use Qwiki\Core\ExtensionManager;
use Qwiki\Extension\Talk\TalkService;

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

function assert_equals($expected, $actual, $testName) {
    global $passed, $failed;
    if ($expected === $actual) {
        echo "  [PASS] {$testName}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$testName} (Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true) . ")\n";
        $failed++;
    }
}

echo "=== Running Talk Pages Extension Tests ===\n\n";

$baseDir = Config::getBaseDir();
$talkDir = $baseDir . '/data/talk';

// Cleanup any leftover test files
if (is_dir($talkDir)) {
    $testFiles = glob($talkDir . '/*test*');
    if ($testFiles) {
        foreach ($testFiles as $f) {
            @unlink($f);
        }
    }
}

// TEST 1: Storage directory and .htaccess initialization
echo "1. Testing Storage Directory & Security Initialization...\n";
$dir = TalkService::getStorageDir();
assert_true(is_dir($dir), "Storage directory data/talk exists");
assert_true(file_exists($dir . '/.htaccess'), ".htaccess file exists in data/talk");
$htaccessContent = file_get_contents($dir . '/.htaccess');
assert_true(strpos($htaccessContent, 'Require all denied') !== false || strpos($htaccessContent, 'Deny from all') !== false, ".htaccess denies direct web access");

// TEST 2: Cascading enablement truth table
echo "\n2. Testing Opt-in Cascading Enablement (Default Disabled)...\n";
$dummyConfig = [
    'books' => [
        [
            'id' => 'cat_unconfigured',
            'title' => 'Unconfigured Category',
            'chapters' => [
                ['slug' => 'doc_unset', 'title' => 'Doc Unset']
            ]
        ],
        [
            'id' => 'cat_disabled',
            'title' => 'Disabled Category',
            'allowTalk' => false,
            'chapters' => [
                ['slug' => 'doc_inherit_disabled', 'title' => 'Doc Inherit Disabled'],
                ['slug' => 'doc_explicit_enabled', 'title' => 'Doc Explicit Enabled', 'allowTalk' => true]
            ]
        ],
        [
            'id' => 'cat_enabled',
            'title' => 'Enabled Category',
            'allowTalk' => true,
            'chapters' => [
                ['slug' => 'doc_inherit_enabled', 'title' => 'Doc Inherit Enabled'],
                ['slug' => 'doc_explicit_disabled', 'title' => 'Doc Explicit Disabled', 'allowTalk' => false]
            ]
        ]
    ]
];

// Case 1: Unconfigured category and document -> false (default disabled)
assert_equals(false, TalkService::isTalkEnabled('cat_unconfigured', 'doc_unset', $dummyConfig), "Unconfigured category & doc defaults to disabled");

// Case 2: Category allowTalk: false, chapter unset -> false
assert_equals(false, TalkService::isTalkEnabled('cat_disabled', 'doc_inherit_disabled', $dummyConfig), "Category disabled & chapter unset resolves to disabled");

// Case 3: Category allowTalk: true, chapter unset -> true
assert_equals(true, TalkService::isTalkEnabled('cat_enabled', 'doc_inherit_enabled', $dummyConfig), "Category enabled & chapter unset resolves to enabled");

// Case 4: Category allowTalk: true, chapter allowTalk: false -> false
assert_equals(false, TalkService::isTalkEnabled('cat_enabled', 'doc_explicit_disabled', $dummyConfig), "Category enabled & chapter explicitly disabled resolves to disabled");

// Case 5: Category allowTalk: false, chapter allowTalk: true -> true
assert_equals(true, TalkService::isTalkEnabled('cat_disabled', 'doc_explicit_enabled', $dummyConfig), "Category disabled & chapter explicitly enabled resolves to enabled");

// TEST 3: Reading empty talk page
echo "\n3. Testing Empty Talk Page Behavior...\n";
$rawEmpty = TalkService::getRawContent('test_book', 'test_nonexistent');
assert_equals('', $rawEmpty, "Nonexistent talk page returns empty string");
$topicsEmpty = TalkService::getTopics('test_book', 'test_nonexistent');
assert_equals([], $topicsEmpty, "Nonexistent talk page returns empty topics array");

// TEST 4: Adding topics and replies
echo "\n4. Testing Topic Addition & Indented Replies...\n";
$t1 = TalkService::addTopic('test_book', 'test_topic_doc', 'alice', 'editor', 'First Proposal', 'We should reorganize Section 3.');
assert_true(!empty($t1['success']), "First topic added successfully");
assert_equals(1, $t1['topicsCount'], "Topic count is 1");

$t2 = TalkService::addTopic('test_book', 'test_topic_doc', 'bob', 'viewer', 'Second Proposal', 'What about code snippet formatting?');
assert_true(!empty($t2['success']), "Second topic added successfully");
assert_equals(2, $t2['topicsCount'], "Topic count is 2");

// Add reply to Topic 1 (index 0)
$r1 = TalkService::addReply('test_book', 'test_topic_doc', 'charlie', 'admin', 0, 'I agree with Alice, Section 3 needs revision.');
assert_true(!empty($r1['success']), "Reply to Topic 0 added successfully");

// Fetch topics and verify structure
$topics = TalkService::getTopics('test_book', 'test_topic_doc');
assert_equals(2, count($topics), "Extracted 2 topics");
assert_equals('First Proposal', $topics[0]['title'], "Topic 0 title matches");
assert_equals('Second Proposal', $topics[1]['title'], "Topic 1 title matches");
assert_true(strpos($topics[0]['rawMarkdown'], 'Alice') !== false || strpos($topics[0]['rawMarkdown'], 'alice') !== false, "Topic 0 contains author alice");
assert_true(strpos($topics[0]['rawMarkdown'], 'charlie') !== false, "Topic 0 contains reply by charlie");

// TEST 5: Raw content reading and saving
echo "\n5. Testing Raw Markdown Reading & Saving...\n";
$raw = TalkService::getRawContent('test_book', 'test_topic_doc');
assert_true(strpos($raw, '## First Proposal') !== false, "Raw content contains topic 1 heading");
assert_true(strpos($raw, '## Second Proposal') !== false, "Raw content contains topic 2 heading");

$customMarkdown = "## Archived Discussion\n\nAll previous topics resolved on 2026-11-12.\n";
$saveResult = TalkService::saveRawContent('test_book', 'test_topic_doc', $customMarkdown);
assert_true($saveResult, "Saved raw markdown successfully");
$updatedRaw = TalkService::getRawContent('test_book', 'test_topic_doc');
assert_equals($customMarkdown, $updatedRaw, "Updated raw content matches saved markdown");

// TEST 6: XSS Sanitization in Safe Markdown Rendering
echo "\n6. Testing Safe Markdown Rendering & XSS Stripping...\n";
$xssPayload = "## Malicious Topic\n\n<script>alert(1)</script> [Click me](javascript:alert('xss')) <img src=x onerror=alert(2)> **Bold Text**";
$rendered = TalkService::renderSafeMarkdown($xssPayload);
assert_true(strpos($rendered, '<script>') === false, "Script tags stripped");
assert_true(strpos($rendered, 'javascript:') === false, "Javascript links stripped");
assert_true(strpos($rendered, 'onerror') === false, "Inline onerror event handler stripped");
assert_true(strpos($rendered, '<strong>Bold Text</strong>') !== false || strpos($rendered, '<b>Bold Text</b>') !== false, "Safe bold formatting preserved");

// TEST 7: Extension Discovery via ExtensionManager
echo "\n7. Testing Extension Registration & Discovery...\n";
$extManager = ExtensionManager::getInstance();
$utilities = $extManager->getUtilities();
assert_true(isset($utilities['talk']), "Talk extension discovered by ExtensionManager");
assert_true(($utilities['talk']['type'] ?? '') === 'utility', "Extension type is utility");
assert_true(in_array('ext_talk_get', $utilities['talk']['actions'] ?? []), "Registers ext_talk_get action");
assert_true(in_array('ext_talk_add_topic', $utilities['talk']['actions'] ?? []), "Registers ext_talk_add_topic action");
assert_true(in_array('ext_talk_reply', $utilities['talk']['actions'] ?? []), "Registers ext_talk_reply action");
assert_true(in_array('ext_talk_save_raw', $utilities['talk']['actions'] ?? []), "Registers ext_talk_save_raw action");

// TEST 8: API Action Handling, Session Auth & Permission Checks
echo "\n8. Testing API Action Handling & Security Guards...\n";
Auth::startSession();
$_SESSION['qwiki_instance'] = Auth::getInstanceIdentifier();

// A. Unauthenticated add topic should fail with 401
unset($_SESSION['qwiki_user']);
unset($_SESSION['qwiki_admin']);
ob_start();
$handled = $extManager->handleAction('ext_talk_add_topic', [
    'book' => 'test_book',
    'slug' => 'test_topic_doc',
    'title' => 'Unauth Topic',
    'message' => 'Should fail'
]);
$output = ob_get_clean();
$res = json_decode($output, true);
assert_true($handled, "Action was handled by ExtensionManager");
assert_equals(401, $res['code'] ?? null, "Unauthenticated add topic returns 401");

// B. Authenticated viewer adds topic
$_SESSION['qwiki_user'] = ['username' => 'viewer_user', 'role' => 'viewer'];
unset($_SESSION['qwiki_admin']);
ob_start();
$handled = $extManager->handleAction('ext_talk_add_topic', [
    'book' => 'test_book',
    'slug' => 'test_topic_doc',
    'title' => 'Viewer Topic',
    'message' => 'Valid message from viewer'
]);
$output = ob_get_clean();
$res = json_decode($output, true);
assert_true(!empty($res['success']), "Authenticated viewer can add topic");

// C. Non-admin viewer attempting ext_talk_save_raw returns 403
ob_start();
$handled = $extManager->handleAction('ext_talk_save_raw', [
    'book' => 'test_book',
    'slug' => 'test_topic_doc',
    'markdown' => '## Hacked raw markdown'
]);
$output = ob_get_clean();
$res = json_decode($output, true);
assert_equals(403, $res['code'] ?? null, "Non-admin viewer calling ext_talk_save_raw returns 403");

// D. Admin user can save raw
$_SESSION['qwiki_user'] = ['username' => 'admin_user', 'role' => 'admin'];
$_SESSION['qwiki_admin'] = true;
ob_start();
$handled = $extManager->handleAction('ext_talk_save_raw', [
    'book' => 'test_book',
    'slug' => 'test_topic_doc',
    'markdown' => "## Admin Controlled\n\nRaw edit approved.\n"
]);
$output = ob_get_clean();
$res = json_decode($output, true);
assert_true(!empty($res['success']), "Admin can save raw talk markdown");

// Cleanup test files
$testFiles = glob($talkDir . '/*test*');
if ($testFiles) {
    foreach ($testFiles as $f) {
        @unlink($f);
    }
}

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
if ($failed > 0) {
    exit(1);
}
