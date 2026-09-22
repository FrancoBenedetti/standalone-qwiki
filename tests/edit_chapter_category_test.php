<?php
/**
 * Test Edit Chapter Category Prefilling & Preservation
 *
 * Verifies that when editing document properties (gear icon):
 * 1. Navigation::findChapterParentId returns the immediate parent category ID.
 * 2. index.php renders #btn-edit-chapter-meta with data-book-id and data-category-id equal to the immediate category.
 * 3. #edit-chapter-category dropdown has the immediate category preselected (both for root and nested categories).
 * 4. Updating document metadata without changing targetBookId preserves the document's category in qwiki.json.
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\Navigation;

echo "Running Edit Chapter Category Prefilling & Preservation Tests...\n\n";

$failures = 0;

function assertCondition($name, $condition, &$failures) {
    if ($condition) {
        echo "✅ PASS: {$name}\n";
    } else {
        echo "❌ FAIL: {$name}\n";
        $failures++;
    }
}

// ----------------------------------------------------
// 1. Unit Tests: Navigation::findChapterParentId
// ----------------------------------------------------
echo "--- 1. Navigation::findChapterParentId ---\n";
$config = Config::load();
$books = $config['books'] ?? [];

$parentIntro = Navigation::findChapterParentId($books, 'introduction');
assertCondition("findChapterParentId('introduction') returns 'getting-started'", $parentIntro === 'getting-started', $failures);

$parentPerf = Navigation::findChapterParentId($books, 'performance');
assertCondition("findChapterParentId('performance') returns 'advanced-topics'", $parentPerf === 'advanced-topics', $failures);

$parentAuth = Navigation::findChapterParentId($books, 'authentication-guide');
assertCondition("findChapterParentId('authentication-guide') returns 'advanced-topics'", $parentAuth === 'advanced-topics', $failures);

$parentManaging = Navigation::findChapterParentId($books, 'managing-content');
assertCondition("findChapterParentId('managing-content') returns 'user-guide'", $parentManaging === 'user-guide', $failures);

$parentThemes = Navigation::findChapterParentId($books, 'themes-overview');
assertCondition("findChapterParentId('themes-overview') returns 'themes-guide'", $parentThemes === 'themes-guide', $failures);

$parentNonExistent = Navigation::findChapterParentId($books, 'non-existent-slug-xyz');
assertCondition("findChapterParentId('non-existent-slug-xyz') returns null", $parentNonExistent === null, $failures);

$parentEmpty = Navigation::findChapterParentId($books, '');
assertCondition("findChapterParentId('') returns null", $parentEmpty === null, $failures);


// ----------------------------------------------------
// 2. Integration Tests: index.php HTML Rendering
// ----------------------------------------------------
echo "\n--- 2. index.php Modal Rendering ---\n";

function renderPageAsAdmin($path) {
    Config::init();
    Auth::startSession();
    $_SESSION['qwiki_admin'] = true;
    $_SESSION['qwiki_user'] = ['username' => 'admin_tester', 'role' => 'admin'];
    $_SESSION['qwiki_instance'] = Auth::getInstanceIdentifier();

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['REQUEST_URI'] = '/' . ltrim($path, '/');
    $_GET = ['path' => ltrim($path, '/')];

    ob_start();
    include __DIR__ . '/../index.php';
    return ob_get_clean();
}

// 2a. Root category document: features
$htmlFeatures = renderPageAsAdmin('getting-started/features');
preg_match('/id="btn-edit-chapter-meta"[^>]*data-book-id="([^"]*)"/', $htmlFeatures, $mBook);
preg_match('/id="btn-edit-chapter-meta"[^>]*data-category-id="([^"]*)"/', $htmlFeatures, $mCat);
assertCondition("Root doc (features): btn-edit-chapter-meta data-book-id is 'getting-started'", ($mBook[1] ?? '') === 'getting-started', $failures);
assertCondition("Root doc (features): btn-edit-chapter-meta data-category-id is 'getting-started'", ($mCat[1] ?? '') === 'getting-started', $failures);

if (preg_match('/<select name="targetBookId" id="edit-chapter-category"[^>]*>(.*?)<\/select>/s', $htmlFeatures, $mSel)) {
    preg_match('/<option value="getting-started"([^>]*)>/', $mSel[1], $mOpt);
    assertCondition("Root doc (features): edit-chapter-category has option 'getting-started' selected", strpos($mOpt[1] ?? '', 'selected') !== false, $failures);
} else {
    assertCondition("Root doc (features): edit-chapter-category select found", false, $failures);
}

// 2b. Nested subfolder document: performance (in advanced-topics)
$htmlPerf = renderPageAsAdmin('getting-started/advanced-topics/performance');
preg_match('/id="btn-edit-chapter-meta"[^>]*data-book-id="([^"]*)"/', $htmlPerf, $mBookPerf);
preg_match('/id="btn-edit-chapter-meta"[^>]*data-category-id="([^"]*)"/', $htmlPerf, $mCatPerf);
assertCondition("Nested doc (performance): btn-edit-chapter-meta data-book-id is 'advanced-topics'", ($mBookPerf[1] ?? '') === 'advanced-topics', $failures);
assertCondition("Nested doc (performance): btn-edit-chapter-meta data-category-id is 'advanced-topics'", ($mCatPerf[1] ?? '') === 'advanced-topics', $failures);

if (preg_match('/<select name="targetBookId" id="edit-chapter-category"[^>]*>(.*?)<\/select>/s', $htmlPerf, $mSelPerf)) {
    preg_match('/<option value="advanced-topics"([^>]*)>/', $mSelPerf[1], $mOptPerf);
    preg_match('/<option value="getting-started"([^>]*)>/', $mSelPerf[1], $mOptGetting);
    assertCondition("Nested doc (performance): edit-chapter-category has option 'advanced-topics' selected", strpos($mOptPerf[1] ?? '', 'selected') !== false, $failures);
    assertCondition("Nested doc (performance): edit-chapter-category does NOT select 'getting-started'", strpos($mOptGetting[1] ?? '', 'selected') === false, $failures);
} else {
    assertCondition("Nested doc (performance): edit-chapter-category select found", false, $failures);
}

// 2c. Nested subfolder document in user-guide: themes-overview (in themes-guide)
$htmlThemes = renderPageAsAdmin('user-guide/themes-guide/themes-overview');
preg_match('/id="btn-edit-chapter-meta"[^>]*data-book-id="([^"]*)"/', $htmlThemes, $mBookThemes);
preg_match('/id="btn-edit-chapter-meta"[^>]*data-category-id="([^"]*)"/', $htmlThemes, $mCatThemes);
assertCondition("Nested doc (themes-overview): btn-edit-chapter-meta data-book-id is 'themes-guide'", ($mBookThemes[1] ?? '') === 'themes-guide', $failures);
assertCondition("Nested doc (themes-overview): btn-edit-chapter-meta data-category-id is 'themes-guide'", ($mCatThemes[1] ?? '') === 'themes-guide', $failures);

if (preg_match('/<select name="targetBookId" id="edit-chapter-category"[^>]*>(.*?)<\/select>/s', $htmlThemes, $mSelThemes)) {
    preg_match('/<option value="themes-guide"([^>]*)>/', $mSelThemes[1], $mOptThemes);
    preg_match('/<option value="user-guide"([^>]*)>/', $mSelThemes[1], $mOptUser);
    assertCondition("Nested doc (themes-overview): edit-chapter-category has option 'themes-guide' selected", strpos($mOptThemes[1] ?? '', 'selected') !== false, $failures);
    assertCondition("Nested doc (themes-overview): edit-chapter-category does NOT select 'user-guide'", strpos($mOptUser[1] ?? '', 'selected') === false, $failures);
} else {
    assertCondition("Nested doc (themes-overview): edit-chapter-category select found", false, $failures);
}


// ----------------------------------------------------
// 3. API Test: edit_chapter preserves category when unchanged
// ----------------------------------------------------
echo "\n--- 3. API edit_chapter Category Preservation ---\n";

// Backup qwiki.json
$qwikiJsonPath = Config::getBaseDir() . '/qwiki.json';
$backupContent = file_get_contents($qwikiJsonPath);

try {
    // Add a temporary test chapter inside advanced-topics
    $cfg = json_decode($backupContent, true);
    $testSlug = 'category-test-doc-' . time();
    $testDoc = [
        'title' => 'Initial Title',
        'slug' => $testSlug,
        'type' => 'markdown',
        'file' => ''
    ];

    // Insert into advanced-topics in memory
    $inserted = false;
    foreach ($cfg['books'] as &$b) {
        if (($b['id'] ?? '') === 'getting-started') {
            foreach ($b['items'] as &$it) {
                if (($it['id'] ?? '') === 'advanced-topics') {
                    $it['items'][] = $testDoc;
                    $inserted = true;
                    break 2;
                }
            }
        }
    }
    assertCondition("Setup: Inserted temporary test doc into 'advanced-topics'", $inserted, $failures);
    file_put_contents($qwikiJsonPath, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Verify findChapterParentId recognizes it as 'advanced-topics'
    $reloadedConfig = Config::load();
    $initialParent = Navigation::findChapterParentId($reloadedConfig['books'], $testSlug);
    assertCondition("Setup: Initial parent category is 'advanced-topics'", $initialParent === 'advanced-topics', $failures);

    // Now simulate form submission of edit_chapter where user changes the title to 'Updated Title'
    // but leaves targetBookId as 'advanced-topics' (which is what was prefilled in the dropdown!)
    $_POST = [
        'action' => 'edit_chapter',
        'slug' => $testSlug,
        'newSlug' => $testSlug,
        'title' => 'Updated Title',
        'targetBookId' => 'advanced-topics', // prefilled from dropdown
        'type' => 'markdown'
    ];
    $_SERVER['REQUEST_METHOD'] = 'POST';

    ob_start();
    include __DIR__ . '/../api/admin.php';
    $apiRespJson = ob_get_clean();
    $apiResp = json_decode($apiRespJson, true);

    assertCondition("API edit_chapter returned success: true", !empty($apiResp['success']), $failures);

    // Verify document in qwiki.json has the updated title AND is STILL inside 'advanced-topics'
    $afterConfig = Config::load();
    $parentAfterEdit = Navigation::findChapterParentId($afterConfig['books'], $testSlug);
    assertCondition("Preservation: Document is STILL in 'advanced-topics' after editing title", $parentAfterEdit === 'advanced-topics', $failures);

    // Check title updated
    $foundDoc = Navigation::findChapterBySlug($afterConfig['books'], $testSlug);
    assertCondition("Title updated to 'Updated Title'", ($foundDoc['title'] ?? '') === 'Updated Title', $failures);
} finally {
    // Restore original qwiki.json
    file_put_contents($qwikiJsonPath, $backupContent);
}

echo "\n----------------------------------------------------\n";
if ($failures === 0) {
    echo "🎉 ALL EDIT CHAPTER CATEGORY TESTS PASSED! (0 failures)\n";
} else {
    echo "❌ TESTS FAILED with {$failures} failure(s)!\n";
    exit(1);
}
