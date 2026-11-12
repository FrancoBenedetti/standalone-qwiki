<?php
/**
 * Automated Test Suite for Standalone Qwiki - Chapter Comments Extension
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';
require_once __DIR__ . '/../lib/Core/LlmAccess.php';
require_once __DIR__ . '/../assets/extensions/tool-comments/CommentService.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\Navigation;
use Qwiki\Core\ExtensionManager;
use Qwiki\Core\LlmAccess;
use Qwiki\Extension\Comments\CommentService;

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

echo "=== Running Comments Extension Tests ===\n\n";

$baseDir = Config::getBaseDir();
$commentsDir = $baseDir . '/data/comments';

// Cleanup any leftover test files
if (is_dir($commentsDir)) {
    $testFiles = glob($commentsDir . '/*test*');
    if ($testFiles) {
        foreach ($testFiles as $f) {
            @unlink($f);
        }
    }
}

// TEST 1: Storage directory and .htaccess initialization
echo "1. Testing Storage Directory & Security Initialization...\n";
$storageDir = CommentService::getStorageDir();
assert_true(is_dir($storageDir), "Comments storage directory exists: {$storageDir}");
assert_true(file_exists($storageDir . '/.htaccess'), ".htaccess security protection file created in comments directory");
$htContent = file_get_contents($storageDir . '/.htaccess');
assert_true(strpos($htContent, 'Require all denied') !== false || strpos($htContent, 'Deny from all') !== false, ".htaccess denies direct web access to comments JSON");

// TEST 2: Cascading Inheritance Resolution (Global -> Category -> Chapter)
echo "\n2. Testing Cascading Comments Enablement Inheritance...\n";
$mockConfig = [
    'allowComments' => false,
    'books' => [
        [
            'id' => 'cat-default',
            'title' => 'Default Category',
            'items' => [
                ['slug' => 'doc-inherit', 'title' => 'Doc Inherit'],
                ['slug' => 'doc-enabled', 'title' => 'Doc Enabled', 'allowComments' => true],
                ['slug' => 'doc-disabled', 'title' => 'Doc Disabled', 'allowComments' => false]
            ]
        ],
        [
            'id' => 'cat-enabled',
            'title' => 'Enabled Category',
            'allowComments' => true,
            'items' => [
                ['slug' => 'doc-inherit-enabled', 'title' => 'Doc Inherit Enabled'],
                ['slug' => 'doc-explicit-disabled', 'title' => 'Doc Explicit Disabled', 'allowComments' => false]
            ]
        ],
        [
            'id' => 'cat-disabled',
            'title' => 'Disabled Category',
            'allowComments' => false,
            'items' => [
                ['slug' => 'doc-inherit-disabled', 'title' => 'Doc Inherit Disabled'],
                ['slug' => 'doc-override-enabled', 'title' => 'Doc Override Enabled', 'allowComments' => true]
            ]
        ]
    ]
];

// Global false, Category default, Chapter inherit -> false
assert_true(CommentService::isCommentsEnabled('cat-default', 'doc-inherit', $mockConfig) === false, "Global false + Category unset + Doc inherit resolves to false");

// Global false, Category default, Chapter explicit true -> true
assert_true(CommentService::isCommentsEnabled('cat-default', 'doc-enabled', $mockConfig) === true, "Chapter override true takes precedence over global false");

// Category enabled, Doc inherit -> true
assert_true(CommentService::isCommentsEnabled('cat-enabled', 'doc-inherit-enabled', $mockConfig) === true, "Category enabled propagates to inheriting doc");

// Category enabled, Doc explicit false -> false
assert_true(CommentService::isCommentsEnabled('cat-enabled', 'doc-explicit-disabled', $mockConfig) === false, "Chapter explicit false overrides category enabled");

// Category disabled, Doc explicit true -> true
assert_true(CommentService::isCommentsEnabled('cat-disabled', 'doc-override-enabled', $mockConfig) === true, "Chapter explicit true overrides category disabled");

// Category-first behavior when user has not set anything (clean slate configuration)
$cleanSlateConfig = [
    'books' => [
        [
            'id' => 'clean-category',
            'title' => 'Clean Category',
            'items' => [
                ['slug' => 'clean-doc-inherit', 'title' => 'Inherit Doc'],
                ['slug' => 'clean-doc-disabled', 'title' => 'Disabled Doc', 'allowComments' => false],
                ['slug' => 'clean-doc-enabled', 'title' => 'Enabled Doc', 'allowComments' => true]
            ]
        ],
        [
            'id' => 'active-category',
            'title' => 'Active Category',
            'allowComments' => true,
            'items' => [
                ['slug' => 'active-doc-inherit', 'title' => 'Inherit Doc in Active Cat'],
                ['slug' => 'active-doc-subsequently-disabled', 'title' => 'Subsequently Disabled Doc', 'allowComments' => false],
                ['slug' => 'active-doc-explicit-enabled', 'title' => 'Explicit Enabled Doc', 'allowComments' => true]
            ]
        ]
    ]
];

// If user has not set anything (category unset, doc unset/inherit) -> function is NOT available (false)
assert_true(CommentService::isCommentsEnabled('clean-category', 'clean-doc-inherit', $cleanSlateConfig) === false, "Unset category + doc inherit resolves to disabled by default");
assert_true(CommentService::isCommentsEnabled('clean-category', 'clean-doc-disabled', $cleanSlateConfig) === false, "Unset category + explicit disabled resolves to disabled");
assert_true(CommentService::isCommentsEnabled('clean-category', 'clean-doc-enabled', $cleanSlateConfig) === true, "Unset category + explicit enabled resolves to enabled");

// When user sets it to active for category -> available for each document unless subsequently disabled
assert_true(CommentService::isCommentsEnabled('active-category', 'active-doc-inherit', $cleanSlateConfig) === true, "Active category propagates to inheriting documents");
assert_true(CommentService::isCommentsEnabled('active-category', 'active-doc-subsequently-disabled', $cleanSlateConfig) === false, "Specific document subsequent disable overrides active category");
assert_true(CommentService::isCommentsEnabled('active-category', 'active-doc-explicit-enabled', $cleanSlateConfig) === true, "Active category + explicit enabled resolves to enabled");

// TEST 3: Safe Markdown Rendering & XSS Sanitization
echo "\n3. Testing Safe Markdown Rendering & XSS Protection...\n";
$dirtyMarkdown = "**Bold** and `code` and <script>alert('xss')</script> and [Safe Link](https://qwiki.org) and [Malicious](javascript:alert(1))";
$rendered = CommentService::renderSafeMarkdown($dirtyMarkdown);

assert_true(strpos($rendered, '<strong>Bold</strong>') !== false || strpos($rendered, '<b>Bold</b>') !== false, "Markdown bold rendered to strong");
assert_true(strpos($rendered, '<code>code</code>') !== false, "Markdown code rendered to code tag");
assert_true(strpos($rendered, '<script>') === false, "Raw script tags are completely stripped or escaped");
assert_true(strpos($rendered, 'javascript:') === false, "javascript: URIs in markdown links are stripped");
assert_true(strpos($rendered, 'https://qwiki.org') !== false, "Safe https:// link preserved");

// TEST 4: Adding Comments and 1-Level Threading
echo "\n4. Testing Adding Comments & 1-Level Nesting...\n";
$bookId = 'test-book';
$slug = 'test-chapter';

// Clean test file if any
$testFile = CommentService::getFilePath($bookId, $slug);
if (file_exists($testFile)) {
    @unlink($testFile);
}

$c1 = CommentService::addComment($bookId, $slug, 'alice', 'viewer', 'This is a root comment on the documentation.');
assert_true(!empty($c1['id']), "Root comment created with unique ID");
assert_true($c1['author'] === 'alice', "Comment author recorded as alice");
assert_true($c1['role'] === 'viewer', "Comment author role recorded as viewer");
assert_true(empty($c1['parentId']), "Root comment has no parentId");

// Add reply to root comment
$reply1 = CommentService::addComment($bookId, $slug, 'bob', 'admin', 'Here is a helpful reply.', $c1['id']);
assert_true(!empty($reply1['id']), "Reply created with unique ID");
assert_true($reply1['parentId'] === $c1['id'], "Reply correctly points to parent ID");

// Attempt nested reply (reply to reply): should flatten to 1-level (point to root parent)
$reply2 = CommentService::addComment($bookId, $slug, 'charlie', 'viewer', 'Replying to Bob reply.', $reply1['id']);
assert_true($reply2['parentId'] === $c1['id'], "Deeply nested reply is flattened to parent root ID (1-level nesting)");

$threads = CommentService::getComments($bookId, $slug);
assert_true(count($threads['comments']) === 1, "Exactly 1 root thread returned");
assert_true(count($threads['comments'][0]['replies']) === 2, "Root thread contains exactly 2 replies");

// TEST 5: Editing Comments & Permission Verification
echo "\n5. Testing Editing Comments...\n";
// Non-author non-admin cannot edit
$editAttempt1 = CommentService::editComment($bookId, $slug, $c1['id'], 'intruder', false, 'Hacked comment');
assert_true($editAttempt1['success'] === false, "Unauthorized user cannot edit comment");

// Original author can edit
$editAttempt2 = CommentService::editComment($bookId, $slug, $c1['id'], 'alice', false, 'This is an updated root comment.');
assert_true($editAttempt2['success'] === true, "Original author can edit comment");
assert_true(!empty($editAttempt2['comment']['updatedAt']), "Edited comment has updatedAt timestamp");
assert_true(strpos($editAttempt2['comment']['content'], 'updated root comment') !== false, "Edited comment content persisted");

// Admin can edit any comment
$editAttempt3 = CommentService::editComment($bookId, $slug, $c1['id'], 'admin', true, 'Moderated root comment.');
assert_true($editAttempt3['success'] === true, "Admin can moderate and edit comments");

// TEST 6: Deleting Comments & Tombstone Preservation
echo "\n6. Testing Deletion & Tombstone Preservation...\n";
// Non-author non-admin cannot delete
$delAttempt1 = CommentService::deleteComment($bookId, $slug, $reply2['id'], 'intruder', false);
assert_true($delAttempt1['success'] === false, "Unauthorized user cannot delete comment");

// Author deletes leaf reply (no children): hard deleted
$delAttempt2 = CommentService::deleteComment($bookId, $slug, $reply2['id'], 'charlie', false);
assert_true($delAttempt2['success'] === true, "Author can delete leaf reply");

$threadsAfterLeafDel = CommentService::getComments($bookId, $slug);
assert_true(count($threadsAfterLeafDel['comments'][0]['replies']) === 1, "Leaf reply removed from replies array");

// Author deletes parent with active reply: soft-deleted (tombstoned)
$delParentAttempt = CommentService::deleteComment($bookId, $slug, $c1['id'], 'alice', true);
assert_true($delParentAttempt['success'] === true, "Parent comment deletion succeeded");

$threadsAfterParentDel = CommentService::getComments($bookId, $slug);
assert_true(count($threadsAfterParentDel['comments']) === 1, "Thread root preserved for replies");
assert_true($threadsAfterParentDel['comments'][0]['isDeleted'] === true, "Parent comment marked as isDeleted");
assert_true(strpos($threadsAfterParentDel['comments'][0]['renderedHtml'], 'deleted') !== false, "Parent comment displays deletion tombstone");

// TEST 7: Extension Discovery & Action Routing via ExtensionManager
echo "\n7. Testing Extension Registration & ExtensionManager Routing...\n";
$extManager = ExtensionManager::getInstance();
$utilities = $extManager->getUtilities();
assert_true(isset($utilities['comments']), "Comments extension discovered by ExtensionManager");
assert_true(($utilities['comments']['type'] ?? '') === 'utility', "Extension type is utility");
assert_true(in_array('ext_comments_get', $utilities['comments']['actions'] ?? []), "Registers ext_comments_get action");
assert_true(in_array('ext_comments_add', $utilities['comments']['actions'] ?? []), "Registers ext_comments_add action");

$frontendAssets = $extManager->getFrontendAssets();
$hasCommentsCss = false;
$hasCommentsJs = false;
foreach ($frontendAssets['styles'] as $s) {
    if (strpos($s, 'tool-comments/style.css') !== false) $hasCommentsCss = true;
}
foreach ($frontendAssets['scripts'] as $sc) {
    if (strpos($sc, 'tool-comments/script.js') !== false) $hasCommentsJs = true;
}
assert_true($hasCommentsCss, "Comments stylesheet automatically included in getFrontendAssets()");
assert_true($hasCommentsJs, "Comments script automatically included in getFrontendAssets()");

// TEST 8: API Authentication & Action Dispatching via handler.php
echo "\n8. Testing API Authentication & Action Dispatching...\n";
Auth::startSession();

$originalConfig = Config::load();
$testConfig = $originalConfig;
// Add temporary test book with comments enabled
$testConfig['books'][] = [
    'id' => 'test-book',
    'title' => 'Test Book',
    'type' => 'folder',
    'allowComments' => true,
    'items' => [
        ['slug' => 'test-chapter', 'title' => 'Test Chapter', 'type' => 'markdown', 'file' => 'content/getting-started/introduction.md']
    ]
];
Config::save($testConfig);

try {
    // A: Unauthenticated user attempt
    $_SESSION['qwiki_instance'] = Auth::getInstanceIdentifier();
    unset($_SESSION['qwiki_user']);

    ob_start();
    $reqDataUnauth = ['action' => 'ext_comments_get', 'book' => 'test-book', 'slug' => 'test-chapter'];
    $extManager->handleAction('ext_comments_get', $reqDataUnauth);
    $outUnauth = ob_get_clean();
    $jsonUnauth = json_decode($outUnauth, true);
    assert_true(isset($jsonUnauth['success']) && $jsonUnauth['success'] === false, "Unauthenticated user calling ext_comments_get is rejected");
    assert_true(($jsonUnauth['code'] ?? '') === 401, "Rejection has HTTP 401 error code");

    // B: Authenticated Viewer adds comment
    $_SESSION['qwiki_user'] = ['username' => 'viewer_user', 'role' => 'viewer'];

    ob_start();
    $reqDataAdd = [
        'action' => 'ext_comments_add',
        'book' => 'test-book',
        'slug' => 'test-chapter',
        'content' => 'First comment posted via extension API handler!'
    ];
    $extManager->handleAction('ext_comments_add', $reqDataAdd);
    $outAdd = ob_get_clean();
    $jsonAdd = json_decode($outAdd, true);
    assert_true(isset($jsonAdd['success']) && $jsonAdd['success'] === true, "Authenticated user can post comment via API handler");
    assert_true(!empty($jsonAdd['comment']['id']), "API handler returns newly created comment");
    $newCommentId = $jsonAdd['comment']['id'] ?? '';

    // C: Authenticated Viewer gets comments
    ob_start();
    $reqDataGet = ['action' => 'ext_comments_get', 'book' => 'test-book', 'slug' => 'test-chapter'];
    $extManager->handleAction('ext_comments_get', $reqDataGet);
    $outGet = ob_get_clean();
    $jsonGet = json_decode($outGet, true);
    assert_true(isset($jsonGet['success']) && $jsonGet['success'] === true, "Authenticated user can fetch comments via ext_comments_get");
    assert_true(isset($jsonGet['currentUser']['username']) && $jsonGet['currentUser']['username'] === 'viewer_user', "ext_comments_get returns currentUser info");
    assert_true(count($jsonGet['comments']) >= 1, "Comments array populated in API response");

    // D: Authenticated Viewer edits own comment
    ob_start();
    $reqDataEdit = [
        'action' => 'ext_comments_edit',
        'book' => 'test-book',
        'slug' => 'test-chapter',
        'commentId' => $newCommentId,
        'content' => 'Updated comment content via API handler.'
    ];
    $extManager->handleAction('ext_comments_edit', $reqDataEdit);
    $outEdit = ob_get_clean();
    $jsonEdit = json_decode($outEdit, true);
    assert_true(isset($jsonEdit['success']) && $jsonEdit['success'] === true, "Author can edit own comment via API handler");

    // E: Authenticated Viewer deletes own comment
    ob_start();
    $reqDataDelete = [
        'action' => 'ext_comments_delete',
        'book' => 'test-book',
        'slug' => 'test-chapter',
        'commentId' => $newCommentId
    ];
    $extManager->handleAction('ext_comments_delete', $reqDataDelete);
    $outDelete = ob_get_clean();
    $jsonDelete = json_decode($outDelete, true);
    assert_true(isset($jsonDelete['success']) && $jsonDelete['success'] === true, "Author can delete own comment via API handler");

    // TEST 9: Category and Chapter Metadata Persistence
    echo "\n9. Testing Category & Chapter Comments Enablement Persistence...\n";
    $_SESSION['qwiki_admin'] = true;
    $_SESSION['qwiki_user'] = ['username' => 'admin', 'role' => 'admin'];

    // Test updating category with allowComments = true
    $targetNode = ['id' => 'test-book', 'title' => 'Test Book', 'type' => 'folder', 'items' => []];
    \Qwiki\Core\Navigation::updateNodeMeta($targetNode, 'test-book', 'Test Book Renamed', '', 'public', null, null, true);
    assert_true(isset($targetNode['allowComments']) && $targetNode['allowComments'] === true, "Navigation::updateNodeMeta sets allowComments = true");

    // Test updating category with allowComments = false
    \Qwiki\Core\Navigation::updateNodeMeta($targetNode, 'test-book', 'Test Book Renamed', '', 'public', null, null, false);
    assert_true(isset($targetNode['allowComments']) && $targetNode['allowComments'] === false, "Navigation::updateNodeMeta sets allowComments = false");

    // TEST 10: LLM Access Token Retrieval for Comments
    echo "\n10. Testing LLM Access Token Retrieval for Comments...\n";

    // First, populate a sample comment on test-chapter
    CommentService::addComment('test-book', 'test-chapter', 'reviewer1', 'viewer', 'Initial question on architecture');
    $cRoot = CommentService::addComment('test-book', 'test-chapter', 'alice', 'admin', 'Official clarification');
    CommentService::addComment('test-book', 'test-chapter', 'bob', 'viewer', 'Follow-up thanks', $cRoot['id']);

    // Generate LLM keys
    $genKeyAll = LlmAccess::generateKey('AI Assistant All', '', ['markdown'], null);
    $keyAll = $genKeyAll['key']['key'];

    $genKeyScoped = LlmAccess::generateKey('AI Assistant Scoped', 'other-book', ['markdown'], null);
    $keyScoped = $genKeyScoped['key']['key'];

    // Helper for running api/llm.php
    $runApiLlm = function(array $getParams, array $serverParams = []) {
        $phpCode = '$_GET = ' . var_export($getParams, true) . '; ';
        $phpCode .= '$_SERVER = array_merge($_SERVER, ' . var_export($serverParams, true) . '); ';
        $phpCode .= 'include "' . __DIR__ . '/../api/llm.php";';
        $cmd = 'php -r ' . escapeshellarg($phpCode);
        return shell_exec($cmd);
    };

    // A: Retrieve comments via mode=comments (JSON)
    $llmRespJson = $runApiLlm(['mode' => 'comments', 'slug' => 'test-chapter', 'key' => $keyAll]);
    $llmParsed = json_decode($llmRespJson, true);
    assert_true(!empty($llmParsed['success']), "api/llm.php?mode=comments returns success=true with valid key");
    assert_true(($llmParsed['slug'] ?? '') === 'test-chapter', "api/llm.php?mode=comments returns correct slug");
    assert_true(count($llmParsed['comments'] ?? []) >= 2, "api/llm.php?mode=comments returns thread comments");
    assert_true(($llmParsed['total_comments'] ?? 0) >= 3, "api/llm.php?mode=comments returns accurate total comment count");

    // B: Retrieve comments via mode=comments (Markdown format)
    $llmRespMd = $runApiLlm(['mode' => 'comments', 'slug' => 'test-chapter', 'format' => 'markdown', 'key' => $keyAll]);
    assert_true(strpos($llmRespMd, '# Discussion Comments for') !== false, "mode=comments&format=markdown returns markdown transcript header");
    assert_true(strpos($llmRespMd, '@reviewer1') !== false, "Markdown transcript contains comment author");
    assert_true(strpos($llmRespMd, 'Follow-up thanks') !== false, "Markdown transcript contains nested reply content");

    // C: Scoped key outside category is rejected (403 Forbidden)
    $scopedResp = $runApiLlm(['mode' => 'comments', 'slug' => 'test-chapter', 'key' => $keyScoped]);
    $scopedParsed = json_decode($scopedResp, true);
    assert_true(isset($scopedParsed['success']) && $scopedParsed['success'] === false, "Scoped LLM key outside category is rejected");
    assert_true(($scopedParsed['code'] ?? 0) === 403, "Rejection returns HTTP 403 Forbidden");

    // D: Retrieve comments embedded in mode=doc
    $docCommentsResp = $runApiLlm(['mode' => 'doc', 'slug' => 'test-chapter', 'include_comments' => '1', 'key' => $keyAll]);
    $docCommentsParsed = json_decode($docCommentsResp, true);
    assert_true(!empty($docCommentsParsed['success']), "mode=doc with include_comments=1 returns success=true");
    assert_true(isset($docCommentsParsed['document']['comments']), "mode=doc response includes comments payload");

    // E: Direct extension action authentication with LLM Token
    unset($_SESSION['qwiki_user']);
    unset($_SESSION['qwiki_admin']);
    ob_start();
    $extManager->handleAction('ext_comments_get', [
        'action' => 'ext_comments_get',
        'book' => 'test-book',
        'slug' => 'test-chapter',
        'key' => $keyAll
    ]);
    $tokenActionOut = ob_get_clean();
    $tokenActionJson = json_decode($tokenActionOut, true);
    assert_true(!empty($tokenActionJson['success']), "ext_comments_get accepts valid LLM token parameter");
    assert_true(strpos($tokenActionJson['currentUser']['username'] ?? '', 'agent:') === 0, "currentUser marked as agent with key name");

    // F: LLM token mutation rejection (tokens cannot post comments)
    ob_start();
    $extManager->handleAction('ext_comments_add', [
        'action' => 'ext_comments_add',
        'book' => 'test-book',
        'slug' => 'test-chapter',
        'content' => 'Malicious mutation attempt by LLM token',
        'key' => $keyAll
    ]);
    $tokenAddOut = ob_get_clean();
    $tokenAddJson = json_decode($tokenAddOut, true);
    assert_true(isset($tokenAddJson['success']) && $tokenAddJson['success'] === false, "LLM access tokens are rejected from mutating comments");
    assert_true(($tokenAddJson['code'] ?? 0) === 403, "Mutation attempt by token returns HTTP 403");
} finally {
    Config::save($originalConfig);
}

// Cleanup test file
if (file_exists($testFile)) {
    @unlink($testFile);
}

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
if ($failed > 0) {
    exit(1);
}
