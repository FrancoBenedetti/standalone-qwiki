/**
 * Test Suite: Tabbed Settings Modals Redesign & Extension Awareness
 */

const { execSync } = require('child_process');
const assert = require('assert');
const path = require('path');
const fs = require('fs');

console.log('=== Running Settings Modals Redesign & Extension Awareness Tests ===\n');

function renderPhpSnippet(phpCode) {
    return execSync('php', {
        input: phpCode,
        cwd: path.join(__dirname, '..')
    }).toString();
}

// -----------------------------------------------------------------------------
// 1. Chapter / Document Settings Modal Tests
// -----------------------------------------------------------------------------
console.log('1. Testing Document / Chapter Settings Modal (edit_chapter.php)...');

function renderChapterModal(commentsEnabled = true, talkEnabled = true, activeChapterType = 'markdown') {
    const script = `<?php
require_once __DIR__ . '/lib/Core/Config.php';
require_once __DIR__ . '/lib/Core/ExtensionManager.php';

$activeChapter = [
    'title' => 'Test Document',
    'slug' => 'test-document',
    'type' => ${JSON.stringify(activeChapterType)},
    'url' => 'https://example.com/gdoc',
    'editUrl' => 'https://example.com/gdoc/edit',
    'file' => 'content/test/test-document.md',
    'theme' => 'theme-default.css',
    'description' => 'Test description',
    'image' => 'https://example.com/img.png',
    'publicShareable' => 1,
    'shareKey' => 'share123',
    'allowComments' => true,
    'allowTalk' => true
];
$categoryHierarchy = [
    ['id' => 'test-cat', 'path' => 'Test Category', 'depth' => 0]
];
$currentCategoryId = 'test-cat';

// Mock ExtensionManager
class MockExtManager {
    private $comments;
    private $talk;
    public function __construct($c, $t) {
        $this->comments = $c;
        $this->talk = $t;
    }
    public function isExtensionEnabled(string $id): bool {
        if ($id === 'comments') return $this->comments;
        if ($id === 'talk') return $this->talk;
        if ($id === 'html') return true;
        if ($id === 'form') return false; // form is disabled
        return true;
    }
    public function getEnabledPageTypes(): array {
        return [
            'html' => ['title' => 'HTML Document']
        ];
    }
    public function getPageTypes(): array {
        return [
            'html' => ['title' => 'HTML Document'],
            'form' => ['title' => 'Interactive Form']
        ];
    }
}
$extManager = new MockExtManager(${commentsEnabled ? 'true' : 'false'}, ${talkEnabled ? 'true' : 'false'});

require __DIR__ . '/templates/modals/edit_chapter.php';
`;
    return renderPhpSnippet(script);
}

// 1.1 Both extensions enabled
const chHtmlBoth = renderChapterModal(true, true);

// Verify Tab Buttons
const requiredChTabs = [
    'edit-chapter-tab-general',
    'edit-chapter-tab-seo',
    'edit-chapter-tab-sharing',
    'edit-chapter-tab-collab'
];
requiredChTabs.forEach(tabId => {
    assert(chHtmlBoth.includes(`data-tab="${tabId}"`), `Document modal missing tab button for ${tabId}`);
    assert(chHtmlBoth.includes(`id="${tabId}"`), `Document modal missing tab pane id="${tabId}"`);
    console.log(`  [PASS] Tab present: ${tabId}`);
});

// Verify core inputs
[
    'edit-chapter-title',
    'edit-chapter-slug',
    'edit-chapter-category',
    'edit-chapter-type',
    'edit-chapter-theme',
    'edit-chapter-description',
    'edit-chapter-image',
    'edit-chapter-public-shareable',
    'edit-chapter-share-key',
    'edit-chapter-readonly-input',
    'edit-chapter-allow-comments',
    'edit-chapter-allow-talk'
].forEach(id => {
    assert(chHtmlBoth.includes(`id="${id}"`), `Document modal missing element id="${id}"`);
    console.log(`  [PASS] Document input present: ${id}`);
});

// 1.2 Both extensions disabled -> Collaboration tab must be suppressed
const chHtmlNone = renderChapterModal(false, false);
assert(!chHtmlNone.includes('data-tab="edit-chapter-tab-collab"'), 'Document modal must NOT render Collaboration tab button when both comments and talk are disabled');
assert(!chHtmlNone.includes('id="edit-chapter-tab-collab"'), 'Document modal must NOT render Collaboration tab pane when both comments and talk are disabled');
assert(!chHtmlNone.includes('id="edit-chapter-allow-comments"'), 'Document modal must NOT render edit-chapter-allow-comments when comments is disabled');
assert(!chHtmlNone.includes('id="edit-chapter-allow-talk"'), 'Document modal must NOT render edit-chapter-allow-talk when talk is disabled');
console.log('  [PASS] Collaboration tab and inputs suppressed when extensions are disabled');

// 1.3 Comments enabled, talk disabled -> Collab tab present, talk suppressed
const chHtmlCommentsOnly = renderChapterModal(true, false);
assert(chHtmlCommentsOnly.includes('data-tab="edit-chapter-tab-collab"'), 'Collab tab should be present if comments is enabled');
assert(chHtmlCommentsOnly.includes('id="edit-chapter-allow-comments"'), 'edit-chapter-allow-comments should be present');
assert(!chHtmlCommentsOnly.includes('id="edit-chapter-allow-talk"'), 'edit-chapter-allow-talk should be suppressed');
console.log('  [PASS] Collab tab has comments only when talk is disabled');

// 1.4 Comments disabled, talk enabled -> Collab tab present, comments suppressed
const chHtmlTalkOnly = renderChapterModal(false, true);
assert(chHtmlTalkOnly.includes('data-tab="edit-chapter-tab-collab"'), 'Collab tab should be present if talk is enabled');
assert(!chHtmlTalkOnly.includes('id="edit-chapter-allow-comments"'), 'edit-chapter-allow-comments should be suppressed');
assert(chHtmlTalkOnly.includes('id="edit-chapter-allow-talk"'), 'edit-chapter-allow-talk should be present');
console.log('  [PASS] Collab tab has talk only when comments is disabled');

// 1.5 Type options: enabled page types included, disabled excluded unless active
const chHtmlDisabledType = renderChapterModal(true, true, 'form'); // 'form' is disabled in mock
assert(chHtmlBoth.includes('value="html"'), 'Type dropdown includes enabled html type');
assert(!chHtmlBoth.includes('value="form"'), 'Type dropdown excludes disabled form type for markdown doc');
assert(chHtmlDisabledType.includes('value="form"'), 'Type dropdown preserves disabled form type when doc is already form');
console.log('  [PASS] Document type dropdown honors enabled extensions and preserves legacy type');

// -----------------------------------------------------------------------------
// 2. Category / Folder Settings Modal Tests
// -----------------------------------------------------------------------------
console.log('\n2. Testing Category / Folder Settings Modal (edit_category.php)...');

function renderCategoryModal(commentsEnabled = true, talkEnabled = true) {
    const script = `<?php
require_once __DIR__ . '/lib/Core/Config.php';
require_once __DIR__ . '/lib/Core/ExtensionManager.php';

$categoryHierarchy = [
    ['id' => 'test-cat', 'path' => 'Test Category', 'depth' => 0]
];
$config = \\Qwiki\\Core\\Config::load();

class MockExtManager {
    private $comments;
    private $talk;
    public function __construct($c, $t) {
        $this->comments = $c;
        $this->talk = $t;
    }
    public function isExtensionEnabled(string $id): bool {
        if ($id === 'comments') return $this->comments;
        if ($id === 'talk') return $this->talk;
        return true;
    }
}
$extManager = new MockExtManager(${commentsEnabled ? 'true' : 'false'}, ${talkEnabled ? 'true' : 'false'});

require __DIR__ . '/templates/modals/edit_category.php';
`;
    return renderPhpSnippet(script);
}

// 2.1 Both extensions enabled
const catHtmlBoth = renderCategoryModal(true, true);

const requiredCatTabs = [
    'edit-book-tab-general',
    'edit-book-tab-access',
    'edit-book-tab-features'
];
requiredCatTabs.forEach(tabId => {
    assert(catHtmlBoth.includes(`data-tab="${tabId}"`), `Category modal missing tab button for ${tabId}`);
    assert(catHtmlBoth.includes(`id="${tabId}"`), `Category modal missing tab pane id="${tabId}"`);
    console.log(`  [PASS] Category tab present: ${tabId}`);
});

// Verify core inputs
[
    'edit-book-title-input',
    'edit-book-description-input',
    'edit-book-theme-input',
    'edit-book-visibility-input',
    'edit-book-readonly-input',
    'edit-book-rss-url',
    'edit-book-allow-comments-input',
    'edit-book-allow-talk-input',
    'btn-delete-book'
].forEach(id => {
    assert(catHtmlBoth.includes(`id="${id}"`), `Category modal missing element id="${id}"`);
    console.log(`  [PASS] Category input present: ${id}`);
});

// 2.2 Extensions disabled
const catHtmlNone = renderCategoryModal(false, false);
assert(!catHtmlNone.includes('id="edit-book-allow-comments-input"'), 'Category modal must NOT render allow-comments checkbox when comments is disabled');
assert(!catHtmlNone.includes('id="edit-book-allow-talk-input"'), 'Category modal must NOT render allow-talk checkbox when talk is disabled');
console.log('  [PASS] Category modal suppresses extension inputs when extensions disabled');

// -----------------------------------------------------------------------------
// 3. Category Flag Preservation when Extensions are Disabled
// -----------------------------------------------------------------------------
console.log('\n3. Testing Category Flag Preservation during edit_book without extension inputs...');

const preserveScript = `<?php
require_once __DIR__ . '/lib/Core/Config.php';
require_once __DIR__ . '/lib/Core/Navigation.php';

$node = [
    'id' => 'test-cat',
    'title' => 'Original Title',
    'allowComments' => true,
    'allowTalk' => true
];

// Simulate what api/admin.php produces for updateNodeMeta when fields are absent from $_POST:
// If api/admin.php defaults to null when not set:
$postAllowComments = isset($_POST['allowComments']) ? ($_POST['allowComments'] === '1' || $_POST['allowComments'] === 'true') : null;
$postAllowTalk = isset($_POST['allowTalk']) ? ($_POST['allowTalk'] === '1' || $_POST['allowTalk'] === 'true') : null;

\\Qwiki\\Core\\Navigation::updateNodeMeta($node, 'test-cat', 'Updated Title', '', 'public', null, null, $postAllowComments, $postAllowTalk);

echo json_encode([
    'title' => $node['title'],
    'allowComments' => $node['allowComments'] ?? null,
    'allowTalk' => $node['allowTalk'] ?? null
]);
`;

// Test 3.1: Verify updateNodeMeta preserves flags when null is passed
const resPreserve = JSON.parse(renderPhpSnippet(preserveScript));
assert.strictEqual(resPreserve.title, 'Updated Title');
assert.strictEqual(resPreserve.allowComments, true, 'allowComments should remain true when not in $_POST');
assert.strictEqual(resPreserve.allowTalk, true, 'allowTalk should remain true when not in $_POST');
console.log('  [PASS] Navigation::updateNodeMeta preserves allowComments and allowTalk when null is passed');

// Test 3.2: Verify api/admin.php handles missing $_POST fields as null
const adminPhpCode = fs.readFileSync(path.join(__dirname, '../api/admin.php'), 'utf8');
const hasCorrectCommentsFallback = adminPhpCode.includes("\$allowComments = isset(\$_POST['allowComments']) ? (\$_POST['allowComments'] === '1' || \$_POST['allowComments'] === 'true') : null;");
const hasCorrectTalkFallback = adminPhpCode.includes("\$allowTalk = isset(\$_POST['allowTalk']) ? (\$_POST['allowTalk'] === '1' || \$_POST['allowTalk'] === 'true') : null;");

assert(hasCorrectCommentsFallback, "api/admin.php must default allowComments to null when omitted from $_POST");
assert(hasCorrectTalkFallback, "api/admin.php must default allowTalk to null when omitted from $_POST");
console.log('  [PASS] api/admin.php correctly defaults allowComments and allowTalk to null');

console.log('\nAll Settings Modals Redesign & Extension Awareness tests passed successfully! 🎉');
