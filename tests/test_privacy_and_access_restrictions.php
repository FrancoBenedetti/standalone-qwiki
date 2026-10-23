<?php
/**
 * Test Suite: Privacy & Access Restrictions
 * Verifies:
 * 1. Full-Page Login Gateway when requireLoginToView is true (no leaked chrome/metadata)
 * 2. Search API denial when requireLoginToView is true
 * 3. Strict Silent Omission for categories with visibility: 'logged_in' or 'admin_only'
 * 4. Strict Silent Omission for documents with visibility: 'logged_in' or 'admin_only'
 * 5. Sequential Navigation list excludes restricted documents
 * 6. Search API respects document and category visibility for guest users
 * 7. Direct URL to restricted resource displays clean "Access Restricted" gate without leaking sidebar
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\Navigation;

echo "Running Privacy & Access Restrictions Test Suite...\n\n";

$failures = 0;
function assertCondition($name, $condition, &$failures) {
    if ($condition) {
        echo "✅ PASS: {$name}\n";
    } else {
        echo "❌ FAIL: {$name}\n";
        $failures++;
    }
}

Config::init();
Auth::startSession();

// Setup mock session environment
$instanceId = Auth::getInstanceIdentifier();
$_SESSION['qwiki_instance'] = $instanceId;
unset($_SESSION['qwiki_user']);
unset($_SESSION['qwiki_admin']);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/index.php';

// --------------------------------------------------------------------------
// TEST GROUP 1: Private Wiki Full-Page Login Gateway
// --------------------------------------------------------------------------
echo "--- 1. Private Wiki (requireLoginToView = true) for Guest ---\n";

// Backup live qwiki.json
$baseDir = Config::getBaseDir();
$configFile = $baseDir . '/qwiki.json';
$origConfigRaw = file_get_contents($configFile);
$origConfig = json_decode($origConfigRaw, true);

// Set requireLoginToView = true
$privateConfig = $origConfig;
$privateConfig['requireLoginToView'] = true;
file_put_contents($configFile, json_encode($privateConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// Render index.php as guest
$_SERVER['REQUEST_URI'] = '/';
unset($_GET['book']);
unset($_GET['chapter']);
unset($_GET['doc']);
unset($_GET['share']);

ob_start();
include __DIR__ . '/../index.php';
$guestHtml = ob_get_clean();

assertCondition("Gateway wrapper is rendered", strpos($guestHtml, 'class="auth-gateway-wrapper"') !== false, $failures);
assertCondition("Gateway card is rendered", strpos($guestHtml, 'class="auth-gateway-card"') !== false, $failures);
assertCondition("Login form is rendered in gateway", strpos($guestHtml, 'id="login-form"') !== false, $failures);
assertCondition("2FA form is rendered in gateway", strpos($guestHtml, 'id="login-2fa-form"') !== false, $failures);
assertCondition("Theme toggle is rendered in gateway", strpos($guestHtml, 'id="theme-toggle"') !== false, $failures);

assertCondition("Sidebar nav is completely omitted", strpos($guestHtml, 'class="sidebar-nav"') === false, $failures);
assertCondition("App sidebar is completely omitted", strpos($guestHtml, 'class="app-sidebar"') === false, $failures);
assertCondition("Search input is completely omitted", strpos($guestHtml, 'id="search-input"') === false, $failures);
assertCondition("Breadcrumbs are completely omitted", strpos($guestHtml, 'class="breadcrumbs"') === false, $failures);
assertCondition("Table of Contents is completely omitted", strpos($guestHtml, 'class="app-toc"') === false, $failures);
assertCondition("Page title contains '— Sign In'", strpos($guestHtml, '— Sign In</title>') !== false, $failures);
assertCondition("Robots noindex tag is present", strpos($guestHtml, '<meta name="robots" content="noindex, nofollow">') !== false, $failures);

// Test Search API when private
$searchOutput = shell_exec('php -r \'$_GET["q"] = "features"; require "' . addslashes(__DIR__ . '/../api/search.php') . '";\'');
$searchRes = json_decode($searchOutput, true);
assertCondition("Private wiki search API returns empty results for guest", !empty($searchRes['success']) && empty($searchRes['results']), $failures);

// Restore config
file_put_contents($configFile, $origConfigRaw);

// --------------------------------------------------------------------------
// TEST GROUP 2: Granular Access Control & Silent Omission in Navigation
// --------------------------------------------------------------------------
echo "\n--- 2. Silent Omission for Granular Permissions (Guest) ---\n";

$testBooks = [
    [
        'id' => 'public-cat',
        'title' => 'Public Category',
        'visibility' => 'public',
        'type' => 'folder',
        'items' => [
            [
                'title' => 'Public Doc',
                'slug' => 'public-doc',
                'type' => 'markdown',
                'visibility' => 'public'
            ],
            [
                'title' => 'Secret Doc Inside Public Cat',
                'slug' => 'secret-doc',
                'type' => 'markdown',
                'visibility' => 'logged_in'
            ]
        ]
    ],
    [
        'id' => 'members-only-cat',
        'title' => 'Members Only Dossiers',
        'visibility' => 'logged_in',
        'type' => 'folder',
        'items' => [
            [
                'title' => 'Member Analysis',
                'slug' => 'member-analysis',
                'type' => 'markdown',
                'visibility' => 'public'
            ]
        ]
    ],
    [
        'id' => 'admin-cat',
        'title' => 'Executive Only',
        'visibility' => 'admin_only',
        'type' => 'folder',
        'items' => [
            [
                'title' => 'Board Deck',
                'slug' => 'board-deck',
                'type' => 'markdown',
                'visibility' => 'admin_only'
            ]
        ]
    ]
];

// Test filterBooks
$guestFiltered = Navigation::filterBooks($testBooks, false, false);
$guestBookIds = array_column($guestFiltered, 'id');
assertCondition("filterBooks excludes logged_in book for guest", !in_array('members-only-cat', $guestBookIds), $failures);
assertCondition("filterBooks excludes admin_only book for guest", !in_array('admin-cat', $guestBookIds), $failures);
assertCondition("filterBooks includes public book for guest", in_array('public-cat', $guestBookIds), $failures);

$viewerFiltered = Navigation::filterBooks($testBooks, false, true);
$viewerBookIds = array_column($viewerFiltered, 'id');
assertCondition("filterBooks includes logged_in book for viewer", in_array('members-only-cat', $viewerBookIds), $failures);
assertCondition("filterBooks excludes admin_only book for viewer", !in_array('admin-cat', $viewerBookIds), $failures);

$adminFiltered = Navigation::filterBooks($testBooks, true, true);
$adminBookIds = array_column($adminFiltered, 'id');
assertCondition("filterBooks includes all books for admin", count($adminBookIds) === 3, $failures);

// Test renderSidebarNode silent omission on document
ob_start();
Navigation::renderSidebarNode($testBooks[0], 'public-cat', ['public-cat'], 'public-doc', 0, false, false, true, null);
$sidebarHtml = ob_get_clean();

assertCondition("renderSidebarNode renders Public Doc", strpos($sidebarHtml, 'Public Doc') !== false, $failures);
assertCondition("renderSidebarNode silently omits Secret Doc Inside Public Cat for guest", strpos($sidebarHtml, 'Secret Doc Inside Public Cat') === false, $failures);

// Test flattenNavTree (sequential reading)
$flatGuest = [];
Navigation::flattenNavTree($testBooks[0], 'public-cat', $flatGuest, false, false);
$flatGuestSlugs = array_column($flatGuest, 'slug');
assertCondition("flattenNavTree includes public doc", in_array('public-doc', $flatGuestSlugs), $failures);
assertCondition("flattenNavTree excludes secret doc for guest", !in_array('secret-doc', $flatGuestSlugs), $failures);

$flatViewer = [];
Navigation::flattenNavTree($testBooks[0], 'public-cat', $flatViewer, false, true);
$flatViewerSlugs = array_column($flatViewer, 'slug');
assertCondition("flattenNavTree includes secret doc for viewer", in_array('secret-doc', $flatViewerSlugs), $failures);

// Test findChapterAndPath for guest
$trail = [];
$activeIds = [];
$foundDocGuest = Navigation::findChapterAndPath($testBooks[0], 'public-cat', 'secret-doc', $trail, $activeIds, false, false);
assertCondition("findChapterAndPath returns null for restricted document for guest", $foundDocGuest === null, $failures);

$trailViewer = [];
$activeIdsViewer = [];
$foundDocViewer = Navigation::findChapterAndPath($testBooks[0], 'public-cat', 'secret-doc', $trailViewer, $activeIdsViewer, false, true);
assertCondition("findChapterAndPath returns document for viewer", $foundDocViewer !== null && ($foundDocViewer['slug'] ?? '') === 'secret-doc', $failures);

// Test getCategoriesHierarchy filtering
$catHierGuest = Navigation::getCategoriesHierarchy($testBooks, '', 0, null, false, false);
$catHierGuestIds = array_column($catHierGuest, 'id');
assertCondition("getCategoriesHierarchy excludes restricted categories for guest", !in_array('members-only-cat', $catHierGuestIds) && !in_array('admin-cat', $catHierGuestIds), $failures);

// --------------------------------------------------------------------------
// TEST GROUP 3: Direct URL to Restricted Document in Public Wiki
// --------------------------------------------------------------------------
echo "\n--- 3. Direct Route to Restricted Category/Document ---\n";

// Temporarily set testBooks in live qwiki.json
$tempConfig = $origConfig;
$tempConfig['books'] = $testBooks;
$tempConfig['defaultBook'] = 'public-cat';
$tempConfig['requireLoginToView'] = false;
file_put_contents($configFile, json_encode($tempConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// Attempt direct navigation to members-only-cat as guest
$_SERVER['REQUEST_URI'] = '/members-only-cat/member-analysis';
$_GET['book'] = 'members-only-cat';
$_GET['chapter'] = 'member-analysis';

ob_start();
include __DIR__ . '/../index.php';
$restrictedHtml = ob_get_clean();

assertCondition("Direct route to restricted doc renders Access Restricted heading", strpos($restrictedHtml, '<h2>Access Restricted</h2>') !== false, $failures);
assertCondition("Direct route breadcrumb displays 'Access Restricted'", strpos($restrictedHtml, '<span>Access Restricted</span>') !== false, $failures);
assertCondition("Sidebar does NOT leak restricted category", strpos($restrictedHtml, 'data-category-id="members-only-cat"') === false, $failures);
assertCondition("Page title contains 'Access Restricted'", strpos($restrictedHtml, '<title>Access Restricted -') !== false, $failures);

// Test Search API under granular permissions
$searchGuestSecret = json_decode(shell_exec('php -r \'$_GET["q"] = "secret"; require "' . addslashes(__DIR__ . '/../api/search.php') . '";\''), true);
assertCondition("Search API does not return restricted document for guest", empty($searchGuestSecret['results']) || !in_array('secret-doc', $searchGuestSecret['results']), $failures);

$searchGuestMember = json_decode(shell_exec('php -r \'$_GET["q"] = "member"; require "' . addslashes(__DIR__ . '/../api/search.php') . '";\''), true);
assertCondition("Search API does not return document inside restricted category for guest", empty($searchGuestMember['results']) || !in_array('member-analysis', $searchGuestMember['results']), $failures);

$searchGuestPublic = json_decode(shell_exec('php -r \'$_GET["q"] = "public"; require "' . addslashes(__DIR__ . '/../api/search.php') . '";\''), true);
assertCondition("Search API returns public document for guest", !empty($searchGuestPublic['results']) && in_array('public-doc', $searchGuestPublic['results']), $failures);

// Restore original config
file_put_contents($configFile, $origConfigRaw);

echo "\n----------------------------------------------------\n";
if ($failures === 0) {
    echo "🎉 ALL PRIVACY & ACCESS RESTRICTION TESTS PASSED!\n";
    exit(0);
} else {
    echo "❌ TOTAL FAILURES: {$failures}\n";
    exit(1);
}
