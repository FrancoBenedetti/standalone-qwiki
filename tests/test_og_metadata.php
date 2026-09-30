<?php
/**
 * Test OG Metadata Extraction & Partial Node Update Preservation
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\Navigation;
use Qwiki\Core\ExtensionManager;

echo "Running OG Metadata & Chapter Properties Preservation Tests...\n\n";

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
// 1. Unit Tests: update_chapter_in_node preservation
// ----------------------------------------------------
echo "--- 1. update_chapter_in_node Field Preservation ---\n";

ob_start();
require_once __DIR__ . '/../api/admin.php';
ob_end_clean();

$testNode = [
    'id' => 'test-cat',
    'title' => 'Test Category',
    'items' => [
        [
            'title' => 'Technical Dossier Alfalfa Emilia Romagna',
            'slug' => 'technical-dossier-alfalfa',
            'type' => 'html',
            'file' => 'content/test-cat/technical-dossier-alfalfa.html',
            'description' => 'Comprehensive agronomic guide for Alfalfa cultivation in Emilia-Romagna.',
            'theme' => 'theme-custom.css',
            'image' => 'https://example.com/alfalfa.jpg'
        ]
    ]
];

// 1a. Partial update with only shareKey
update_chapter_in_node($testNode, 'technical-dossier-alfalfa', ['shareKey' => 'abc12345def67890']);
$ch = $testNode['items'][0];
assertCondition("Partial update with shareKey sets shareKey", ($ch['shareKey'] ?? '') === 'abc12345def67890', $failures);
assertCondition("Partial update with shareKey PRESERVES description", ($ch['description'] ?? '') === 'Comprehensive agronomic guide for Alfalfa cultivation in Emilia-Romagna.', $failures);
assertCondition("Partial update with shareKey PRESERVES theme", ($ch['theme'] ?? '') === 'theme-custom.css', $failures);
assertCondition("Partial update with shareKey PRESERVES image", ($ch['image'] ?? '') === 'https://example.com/alfalfa.jpg', $failures);

// 1b. Partial update with publicShareable
update_chapter_in_node($testNode, 'technical-dossier-alfalfa', ['publicShareable' => false]);
$ch = $testNode['items'][0];
assertCondition("Partial update with publicShareable preserves description", ($ch['description'] ?? '') === 'Comprehensive agronomic guide for Alfalfa cultivation in Emilia-Romagna.', $failures);

// 1c. Explicit clearing of description
update_chapter_in_node($testNode, 'technical-dossier-alfalfa', ['description' => '']);
$ch = $testNode['items'][0];
assertCondition("Explicit clearing of description unsets description", !isset($ch['description']), $failures);
assertCondition("Explicit clearing of description preserves theme", ($ch['theme'] ?? '') === 'theme-custom.css', $failures);

// ----------------------------------------------------
// 2. Integration Tests: index.php OG Metadata for HTML docs
// ----------------------------------------------------
echo "\n--- 2. index.php OG Metadata Rendering for HTML Documents ---\n";

function renderDocPage($path, $asAdmin = false) {
    Config::init();
    Auth::startSession();
    if ($asAdmin) {
        $_SESSION['qwiki_admin'] = true;
        $_SESSION['qwiki_user'] = ['username' => 'admin_tester', 'role' => 'admin'];
    } else {
        unset($_SESSION['qwiki_admin']);
        unset($_SESSION['qwiki_user']);
    }
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

$originalConfig = Config::load();

// 2a. HTML document with explicit description in chapter properties
$testConfig = $originalConfig;
foreach ($testConfig['books'][0]['items'] as &$it) {
    if (($it['slug'] ?? '') === 'interactive-dashboard') {
        $it['description'] = 'Technical Dossier Alfalfa Emilia Romagna - Comprehensive Agronomic Research';
    }
}
Config::save($testConfig);

$htmlRendered = renderDocPage('getting-started/interactive-dashboard', true);
preg_match('/<meta property="og:description" content="([^"]*)"/', $htmlRendered, $mOgDesc);
$renderedOgDesc = $mOgDesc[1] ?? '';

assertCondition("HTML doc with chapter description uses chapter description in og:description", 
    $renderedOgDesc === 'Technical Dossier Alfalfa Emilia Romagna - Comprehensive Agronomic Research', $failures);

assertCondition("og:description does NOT contain viewer toolbar text", 
    strpos($renderedOgDesc, 'Print / Save as PDF') === false && strpos($renderedOgDesc, 'Edit HTML') === false, $failures);

// 2b. Fallback when chapter description is absent: extracts text from HTML file body
Config::save($originalConfig); // restore original where interactive-dashboard has no description

$htmlRendered2 = renderDocPage('getting-started/interactive-dashboard', true);
preg_match('/<meta property="og:description" content="([^"]*)"/', $htmlRendered2, $mOgDesc2);
$renderedOgDesc2 = $mOgDesc2[1] ?? '';

assertCondition("HTML doc fallback extracts clean visible text from HTML file", 
    strpos($renderedOgDesc2, 'Live System Analytics') !== false, $failures);
assertCondition("HTML doc fallback does NOT contain viewer toolbar text", 
    strpos($renderedOgDesc2, 'Print / Save as PDF') === false && strpos($renderedOgDesc2, 'Edit HTML') === false && strpos($renderedOgDesc2, 'Edit HTML Document') === false, $failures);

// 2c. Share Key Generation API preserving chapter description in qwiki.json
$testConfig2 = $originalConfig;
foreach ($testConfig2['books'][0]['items'] as &$it) {
    if (($it['slug'] ?? '') === 'interactive-dashboard') {
        $it['description'] = 'Preserved Technical Description';
        unset($it['shareKey']); // force regeneration
    }
}
Config::save($testConfig2);

Auth::startSession();
$_SESSION['qwiki_admin'] = true;
$_SESSION['qwiki_user'] = ['username' => 'admin_tester', 'role' => 'admin'];
$_SESSION['qwiki_instance'] = Auth::getInstanceIdentifier();
$_SERVER['REQUEST_METHOD'] = 'GET';
$_REQUEST['action'] = 'get_or_create_share_key';
$_REQUEST['slug'] = 'interactive-dashboard';

ob_start();
include __DIR__ . '/../api/admin.php';
$shareResp = ob_get_clean();

$afterShareConfig = Config::load();
Config::save($originalConfig); // restore

$savedDesc = null;
foreach ($afterShareConfig['books'][0]['items'] as $it) {
    if (($it['slug'] ?? '') === 'interactive-dashboard') {
        $savedDesc = $it['description'] ?? null;
    }
}
assertCondition("get_or_create_share_key API does NOT wipe description from qwiki.json", 
    $savedDesc === 'Preserved Technical Description', $failures);

echo "\n----------------------------------------------------\n";
if ($failures === 0) {
    echo "🎉 ALL OG METADATA & CHAPTER PRESERVATION TESTS PASSED! (0 failures)\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED: {$failures} failure(s)\n";
    exit(1);
}
