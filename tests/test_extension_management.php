<?php
/**
 * Test Suite: Per-Wiki Extension Management
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';
require_once __DIR__ . '/../lib/Core/Auth.php';

use Qwiki\Core\Config;
use Qwiki\Core\ExtensionManager;
use Qwiki\Core\Auth;

$passed = 0;
$failed = 0;

function assert_test($condition, $testName) {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$testName}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$testName}\n";
        $failed++;
    }
}

echo "=== Running Extension Management Tests ===\n\n";

$extManager = ExtensionManager::getInstance();

// Backup live config
$configFile = Config::getBaseDir() . '/qwiki.json';
$originalConfigContent = file_get_contents($configFile);

try {
    // -------------------------------------------------------------
    // 1. Default State: No "extensions" key in qwiki.json
    // -------------------------------------------------------------
    $configData = json_decode($originalConfigContent, true);
    unset($configData['extensions']);
    file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT));

    echo "1. Testing Default Enabled State (Backward Compatibility):\n";
    assert_test($extManager->isExtensionEnabled('comments') === true, "Comments extension is enabled by default");
    assert_test($extManager->isExtensionEnabled('html') === true, "HTML extension is enabled by default");
    assert_test($extManager->isExtensionEnabled('form') === true, "Form extension is enabled by default");
    assert_test($extManager->isExtensionEnabled('gallery') === true, "Gallery extension is enabled by default");

    $allExts = $extManager->getAllExtensions();
    assert_test(is_array($allExts) && isset($allExts['comments']), "getAllExtensions() returns registered extensions");
    assert_test(isset($allExts['comments']['enabled']) && $allExts['comments']['enabled'] === true, "getAllExtensions() marks comments as enabled");
    assert_test(isset($allExts['html']['enabled']) && $allExts['html']['enabled'] === true, "getAllExtensions() marks html as enabled");

    // -------------------------------------------------------------
    // 2. Explicit Disable: Set extensions in qwiki.json
    // -------------------------------------------------------------
    echo "\n2. Testing Explicit Disabling:\n";
    $configData['extensions'] = [
        'comments' => false,
        'html' => false
    ];
    file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT));

    assert_test($extManager->isExtensionEnabled('comments') === false, "isExtensionEnabled('comments') returns false when disabled");
    assert_test($extManager->isExtensionEnabled('html') === false, "isExtensionEnabled('html') returns false when disabled");
    assert_test($extManager->isExtensionEnabled('form') === true, "isExtensionEnabled('form') remains true when not explicitly disabled");

    $enabledPageTypes = $extManager->getEnabledPageTypes();
    assert_test(!isset($enabledPageTypes['html']), "getEnabledPageTypes() excludes disabled 'html'");
    assert_test(isset($enabledPageTypes['form']), "getEnabledPageTypes() includes enabled 'form'");

    $enabledUtilities = $extManager->getEnabledUtilities();
    assert_test(!isset($enabledUtilities['comments']), "getEnabledUtilities() excludes disabled 'comments'");
    assert_test(isset($enabledUtilities['gallery']), "getEnabledUtilities() includes enabled 'gallery'");

    $allExtsDisabled = $extManager->getAllExtensions();
    assert_test($allExtsDisabled['comments']['enabled'] === false, "getAllExtensions() reflects comments enabled = false");
    assert_test($allExtsDisabled['html']['enabled'] === false, "getAllExtensions() reflects html enabled = false");
    assert_test($allExtsDisabled['form']['enabled'] === true, "getAllExtensions() reflects form enabled = true");

    // -------------------------------------------------------------
    // 3. Asset Filtering
    // -------------------------------------------------------------
    echo "\n3. Testing Frontend Asset Filtering:\n";
    $assets = $extManager->getFrontendAssets();
    $foundCommentsAsset = false;
    $foundHtmlAsset = false;

    foreach ($assets['styles'] as $s) {
        if (strpos($s, 'tool-comments') !== false) $foundCommentsAsset = true;
        if (strpos($s, 'page-html') !== false || strpos($s, 'suneditor') !== false) $foundHtmlAsset = true;
    }
    foreach ($assets['scripts'] as $s) {
        if (strpos($s, 'tool-comments') !== false) $foundCommentsAsset = true;
        if (strpos($s, 'page-html') !== false || strpos($s, 'suneditor') !== false) $foundHtmlAsset = true;
    }

    assert_test(!$foundCommentsAsset, "getFrontendAssets() excludes assets from disabled 'comments'");
    assert_test(!$foundHtmlAsset, "getFrontendAssets() excludes assets from disabled 'html'");

    // -------------------------------------------------------------
    // 4. Action Interception for Disabled Extensions
    // -------------------------------------------------------------
    echo "\n4. Testing Action Interception for Disabled Extensions:\n";
    // Disable activity_log and html
    $configData['extensions'] = [
        'activity_log' => false,
        'html' => false
    ];
    file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT));

    ob_start();
    $handledUtil = $extManager->handleAction('ext_activity_get_logs', []);
    $outputUtil = ob_get_clean();
    $jsonUtil = json_decode($outputUtil, true);

    assert_test($handledUtil === true, "handleAction() handles disabled utility action");
    assert_test(isset($jsonUtil['success']) && $jsonUtil['success'] === false, "handleAction() returns success=false for disabled utility");
    assert_test(strpos($jsonUtil['error'] ?? '', 'disabled') !== false, "handleAction() returns informative error for disabled utility");

    ob_start();
    $handledPage = $extManager->handleAction('save_html', []);
    $outputPage = ob_get_clean();
    $jsonPage = json_decode($outputPage, true);

    assert_test($handledPage === true, "handleAction() handles disabled page type action");
    assert_test(isset($jsonPage['success']) && $jsonPage['success'] === false, "handleAction() returns success=false for disabled page type");
    assert_test(strpos($jsonPage['error'] ?? '', 'disabled') !== false, "handleAction() returns informative error for disabled page type");

    // -------------------------------------------------------------
    // 5. Read-Only Rendering for Disabled Page Types
    // -------------------------------------------------------------
    echo "\n5. Testing Read-Only Rendering for Disabled Page Types:\n";
    $sampleHtmlChapter = [
        'title' => 'Sample HTML Document',
        'slug' => 'sample-html',
        'type' => 'html',
        'file' => 'content/getting-started/interactive-dashboard.html'
    ];
    $sampleBook = ['id' => 'getting-started', 'title' => 'Getting Started'];

    // Render while HTML extension is disabled
    $renderedDisabled = $extManager->renderPage($sampleHtmlChapter, $sampleBook, $configData);
    assert_test(strpos($renderedDisabled, 'Read-only mode') !== false, "renderPage() displays read-only mode banner when page type is disabled");
    assert_test(strpos($renderedDisabled, 'alert') !== false, "renderPage() formats read-only notice as an alert banner");

    // Enable HTML extension and verify no read-only banner
    $configData['extensions']['html'] = true;
    file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT));
    $renderedEnabled = $extManager->renderPage($sampleHtmlChapter, $sampleBook, $configData);
    assert_test(strpos($renderedEnabled, 'Read-only mode') === false, "renderPage() does not display read-only banner when page type is enabled");

    // -------------------------------------------------------------
    // 6. Backend Settings Persistence via api/admin.php
    // -------------------------------------------------------------
    echo "\n6. Testing Settings Persistence via api/admin.php:\n";
    $_SESSION['qwiki_user'] = ['username' => 'admin', 'role' => 'admin'];
    $_SESSION['qwiki_admin'] = true;
    $_SERVER['REQUEST_METHOD'] = 'POST';

    $_POST = [
        'action' => 'update_settings',
        'title' => $configData['title'] ?? 'Test Wiki',
        'logoText' => $configData['logoText'] ?? 'QWIKI',
        'theme' => 'theme-default.css',
        'extensions_configured' => '1',
        'extensions' => [
            'comments' => '1',
            'postbox' => '1',
            'gallery' => '0'
            // html, form, etc. omitted - should be treated as false!
        ]
    ];

    ob_start();
    include __DIR__ . '/../api/admin.php';
    $resStr = ob_get_clean();
    $resJson = json_decode($resStr, true);

    assert_test(isset($resJson['success']) && $resJson['success'] === true, "api/admin.php update_settings returned success");

    $savedConfig = Config::load();
    assert_test(isset($savedConfig['extensions']), "Config contains 'extensions' key after update_settings");
    assert_test($savedConfig['extensions']['comments'] === true, "extensions['comments'] persisted as true");
    assert_test($savedConfig['extensions']['postbox'] === true, "extensions['postbox'] persisted as true");
    assert_test($savedConfig['extensions']['gallery'] === false, "extensions['gallery'] persisted as false");
    assert_test($savedConfig['extensions']['html'] === false, "Omitted extension 'html' persisted as false");
    assert_test($savedConfig['extensions']['form'] === false, "Omitted extension 'form' persisted as false");

    // -------------------------------------------------------------
    // 7. Subwiki Isolation
    // -------------------------------------------------------------
    echo "\n7. Testing Subwiki Isolation:\n";
    $subwikiDir = Config::getBaseDir() . '/test-subwiki-isolation';
    @mkdir($subwikiDir, 0755, true);
    $subwikiConfig = [
        'title' => 'Isolation Subwiki',
        'isSubwiki' => true,
        'extensions' => [
            'comments' => false,
            'html' => true
        ]
    ];
    file_put_contents($subwikiDir . '/qwiki.json', json_encode($subwikiConfig, JSON_PRETTY_PRINT));

    // Restore parent config to original
    file_put_contents($configFile, $originalConfigContent);

    // Read parent and subwiki configs
    $parentCurrent = Config::load();
    $subwikiCurrent = json_decode(file_get_contents($subwikiDir . '/qwiki.json'), true);

    assert_test(!isset($parentCurrent['extensions']['comments']) || $parentCurrent['extensions']['comments'] !== false, "Parent wiki retains independent configuration");
    assert_test($subwikiCurrent['extensions']['comments'] === false, "Subwiki maintains its own disabled extension state");
    assert_test($subwikiCurrent['extensions']['html'] === true, "Subwiki maintains its own enabled extension state");

    // Clean up test subwiki directory
    @unlink($subwikiDir . '/qwiki.json');
    @rmdir($subwikiDir);

    // -------------------------------------------------------------
    // 8. Utility Button Placement Filtering
    // -------------------------------------------------------------
    echo "\n8. Testing Utility Button Placement Filtering:\n";
    $allEnabledConfig = json_decode($originalConfigContent, true);
    unset($allEnabledConfig['extensions']);
    file_put_contents($configFile, json_encode($allEnabledConfig, JSON_PRETTY_PRINT));

    ob_start();
    $extManager->renderHeaderUtilityButtons(true);
    $headerUtilHtml = ob_get_clean();

    assert_test(strpos($headerUtilHtml, 'btn-util-comments') === false, "renderHeaderUtilityButtons() omits content_footer utility 'comments'");
    assert_test(strpos($headerUtilHtml, 'btn-util-talk') === false, "renderHeaderUtilityButtons() omits content_tabs utility 'talk'");
    assert_test(strpos($headerUtilHtml, 'btn-util-revisions') === false, "renderHeaderUtilityButtons() omits document_toolbar utility 'revisions'");
    assert_test(strpos($headerUtilHtml, 'btn-util-gallery') !== false, "renderHeaderUtilityButtons() includes header_menu utility 'gallery'");
    assert_test(strpos($headerUtilHtml, 'btn-util-backup') !== false, "renderHeaderUtilityButtons() includes header_menu utility 'backup'");

    ob_start();
    $extManager->renderChapterHeaderUtilityButtons(true);
    $chapterUtilHtml = ob_get_clean();

    assert_test(strpos($chapterUtilHtml, 'btn-chapter-talk') === false, "renderChapterHeaderUtilityButtons() omits dead 'talk' button");

} finally {
    // Restore original config
    file_put_contents($configFile, $originalConfigContent);
}

echo "\nSummary: {$passed} passed, {$failed} failed\n";
if ($failed > 0) {
    exit(1);
}
