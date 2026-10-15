<?php
// tests/hyperlink_test.php

require_once __DIR__ . '/../lib/Core/Navigation.php';

use Qwiki\Core\Navigation;

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

echo "Running Hyperlink Menu Items & Domain Detection Tests...\n\n";

// ----------------------------------------------------
// 1. Navigation::isExternalUrl() Tests
// ----------------------------------------------------
$_SERVER['HTTP_HOST'] = 'localhost:8000';

assertTest(Navigation::isExternalUrl('https://github.com') === true, 'External HTTPS URL is detected as external');
assertTest(Navigation::isExternalUrl('http://otherdomain.org/path') === true, 'External HTTP URL is detected as external');
assertTest(Navigation::isExternalUrl('//external.com/path') === true, 'Protocol-relative URL is detected as external');
assertTest(Navigation::isExternalUrl('https://localhost:3000/test') === true, 'Different port on same host is detected as external');
assertTest(Navigation::isExternalUrl('http://localhost:8000/docs/item') === false, 'Same host and port is detected as same-domain');
assertTest(Navigation::isExternalUrl('/content/sample.pdf') === false, 'Root-relative path is detected as same-domain');
assertTest(Navigation::isExternalUrl('?book=getting-started&chapter=features') === false, 'Query string is detected as same-domain');
assertTest(Navigation::isExternalUrl('#section-2') === false, 'Hash anchor is detected as same-domain');
assertTest(Navigation::isExternalUrl('mailto:support@example.com') === false, 'mailto link is not detected as external web page');
assertTest(Navigation::isExternalUrl('') === false, 'Empty URL is not detected as external');

// ----------------------------------------------------
// 2. Navigation::renderLinkItem() HTML Output Tests
// ----------------------------------------------------
$_SERVER['HTTP_HOST'] = 'mywiki.local';

// External Link - Admin View
$externalItem = [
    'title' => 'External Docs',
    'slug' => 'external-docs',
    'type' => 'link',
    'url' => 'https://external-service.io/api',
    'visibility' => 'public'
];

ob_start();
Navigation::renderLinkItem($externalItem, 'test-cat', 1, true, true, false);
$extAdminHtml = ob_get_clean();

assertTest(strpos($extAdminHtml, "target='_blank'") !== false, 'External link includes target="_blank"');
assertTest(strpos($extAdminHtml, "rel='noopener noreferrer'") !== false, 'External link includes rel="noopener noreferrer"');
assertTest(strpos($extAdminHtml, 'badge-link') !== false, 'External link includes badge-link class');
assertTest(strpos($extAdminHtml, 'btn-edit-link-icon') !== false, 'Admin view includes inline edit gear icon');

// External Link - Viewer View (No Admin)
ob_start();
Navigation::renderLinkItem($externalItem, 'test-cat', 1, false, true, false);
$extViewerHtml = ob_get_clean();

assertTest(strpos($extViewerHtml, 'btn-edit-link-icon') === false, 'Viewer view omits inline edit gear icon');

// Internal / Same-Domain Link
$internalItem = [
    'title' => 'Internal Report',
    'slug' => 'internal-report',
    'type' => 'link',
    'url' => '/reports/annual',
    'visibility' => 'public'
];

ob_start();
Navigation::renderLinkItem($internalItem, 'test-cat', 1, false, true, false);
$intHtml = ob_get_clean();

assertTest(strpos($intHtml, "target='_self'") !== false, 'Internal link includes target="_self"');
assertTest(strpos($intHtml, "rel='noopener noreferrer'") === false, 'Internal link omits rel="noopener noreferrer"');
assertTest(strpos($intHtml, 'badge-internal-link') !== false, 'Internal link includes badge-internal-link class');
assertTest(strpos($intHtml, 'nav-top-link') === false, 'Nested link omits nav-top-link class');
assertTest(strpos($intHtml, 'top-link-icon') === false, 'Nested link omits top-link-icon');

// Top-Level Link (depth = 0)
ob_start();
Navigation::renderLinkItem($externalItem, '', 0, true, true, false);
$topLevelHtml = ob_get_clean();

assertTest(strpos($topLevelHtml, 'nav-top-link') !== false, 'Top-level link includes nav-top-link class');
assertTest(strpos($topLevelHtml, 'top-link-icon') !== false, 'Top-level link includes top-link-icon');
assertTest(strpos($topLevelHtml, '🔗') !== false, 'Top-level link includes 🔗 icon');

// Top-Level Link via renderSidebarNode
ob_start();
Navigation::renderSidebarNode($externalItem, '', [], '', 0, true, true, false);
$renderSidebarTopHtml = ob_get_clean();
assertTest(strpos($renderSidebarTopHtml, 'nav-top-link') !== false, 'renderSidebarNode with link delegates to renderLinkItem with depth 0');

// ----------------------------------------------------
// 3. Navigation::findChapterAndPath() Skip Links Test
// ----------------------------------------------------
$mockTree = [
    'id' => 'guides',
    'title' => 'Guides',
    'type' => 'folder',
    'items' => [
        [
            'title' => 'External Portal',
            'slug' => 'external-portal',
            'type' => 'link',
            'url' => 'https://portal.com'
        ],
        [
            'title' => 'Real Chapter',
            'slug' => 'real-chapter',
            'type' => 'markdown',
            'file' => 'content/guides/real.md'
        ]
    ]
];

$trail = [];
$activeIds = [];
$found = Navigation::findChapterAndPath($mockTree, 'guides', '', $trail, $activeIds, false, false);
assertTest($found !== null && $found['slug'] === 'real-chapter', 'findChapterAndPath skips link and selects first real document');

// ----------------------------------------------------
// 4. Navigation::flattenNavTree() Skip Links Test
// ----------------------------------------------------
$flatList = [];
Navigation::flattenNavTree($mockTree, 'guides', $flatList, false, false);
assertTest(count($flatList) === 1 && $flatList[0]['slug'] === 'real-chapter', 'flattenNavTree excludes link items from sequential reading list');

// ----------------------------------------------------
// 5. URL Security Validation
// ----------------------------------------------------
require_once __DIR__ . '/../lib/Core/Config.php';

function test_is_safe_url($url) {
    $trimmed = trim($url);
    if (empty($trimmed)) return false;
    if (preg_match('/^\s*(javascript|data|vbscript):/i', $trimmed)) {
        return false;
    }
    if (preg_match('/^([a-z0-9+.-]+):/i', $trimmed, $matches)) {
        $scheme = strtolower($matches[1]);
        if (!in_array($scheme, ['http', 'https', 'mailto'])) {
            return false;
        }
    }
    return true;
}

assertTest(test_is_safe_url('https://secure.example.com') === true, 'Safe HTTPS URL passes validation');
assertTest(test_is_safe_url('http://example.com/api') === true, 'Safe HTTP URL passes validation');
assertTest(test_is_safe_url('/local/path') === true, 'Safe relative URL passes validation');
assertTest(test_is_safe_url('javascript:alert(1)') === false, 'javascript: URL is rejected');
assertTest(test_is_safe_url('  JavaScript:void(0)') === false, 'javascript: with spaces is rejected');
assertTest(test_is_safe_url('data:text/html,<script>alert(1)</script>') === false, 'data: URI is rejected');
assertTest(test_is_safe_url('vbscript:msgbox(1)') === false, 'vbscript: URL is rejected');

echo "\n----------------------------------------------------\n";
echo "Tests Completed: Passed: {$passed}, Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
