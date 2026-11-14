<?php
// Test Logo Inheritance & Favicon Rendering across Root Wiki & Subwikis

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/SubwikiManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\SubwikiManager;

echo "Running Logo & Favicon Integration Tests...\n\n";

$realRoot = realpath(__DIR__ . '/..');
$tempParent = sys_get_temp_dir() . '/qwiki_logo_test_' . uniqid();
@mkdir($tempParent, 0755, true);

function cleanupLogoTestDir($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_link($path)) {
            @unlink($path);
        } elseif (is_dir($path)) {
            cleanupLogoTestDir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function assertContainsStr($haystack, $needle, $message) {
    if (strpos($haystack, $needle) === false) {
        echo "FAIL: {$message} (missing '{$needle}')\n";
        exit(1);
    }
}

try {
    // 1. Setup Parent Wiki with default Qwiki logo
    $parentConfig = [
        'title' => 'Standalone Qwiki',
        'logoText' => 'QWIKI',
        'logoUrl' => 'assets/images/logo-qwiki.svg',
        'theme' => 'theme-default.css',
        'books' => [
            [
                'id' => 'home',
                'title' => 'Home',
                'type' => 'folder',
                'items' => [
                    ['slug' => 'overview', 'title' => 'Overview', 'type' => 'markdown', 'file' => 'content/overview.md', 'shareKey' => 'root-share']
                ]
            ]
        ]
    ];
    file_put_contents($tempParent . '/qwiki.json', json_encode($parentConfig, JSON_PRETTY_PRINT));
    @mkdir($tempParent . '/content', 0755, true);
    file_put_contents($tempParent . '/content/overview.md', '# Overview');
    @symlink($realRoot . '/assets', $tempParent . '/assets');
    @symlink($realRoot . '/lib', $tempParent . '/lib');

    // 2. Setup Subwiki with empty logoUrl (inherits parent's assets/images/logo-qwiki.svg)
    $subDocs = $tempParent . '/docs';
    @mkdir($subDocs . '/content', 0755, true);
    file_put_contents($subDocs . '/.subwiki', "subwiki\n");
    $subConfig = [
        'title' => 'Qwiki Manual',
        'logoText' => 'MANUAL',
        'logoUrl' => '',
        'isSubwiki' => true,
        'parentUrl' => '../',
        'parentTitle' => 'Standalone Qwiki',
        'books' => [
            [
                'id' => 'guide',
                'title' => 'Guide',
                'type' => 'folder',
                'items' => [
                    ['slug' => 'intro', 'title' => 'Intro', 'type' => 'markdown', 'file' => 'content/intro.md', 'shareKey' => 'sub-share']
                ]
            ]
        ]
    ];
    file_put_contents($subDocs . '/qwiki.json', json_encode($subConfig, JSON_PRETTY_PRINT));
    file_put_contents($subDocs . '/content/intro.md', '# Intro');
    @symlink($realRoot . '/assets', $subDocs . '/assets');
    @symlink($realRoot . '/lib', $subDocs . '/lib');

    // Render Root Wiki
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_HOST'] = 'home.qwiki.wiki';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['REQUEST_URI'] = '/index.php';
    $_GET = [];

    Config::init($tempParent);
    ob_start();
    include $realRoot . '/index.php';
    $rootHtml = ob_get_clean();

    assertContainsStr($rootHtml, 'images/favicon.svg', 'Root wiki includes SVG favicon link');
    assertContainsStr($rootHtml, 'images/favicon-32x32.png', 'Root wiki includes 32x32 PNG favicon link');
    assertContainsStr($rootHtml, 'images/apple-touch-icon.png', 'Root wiki includes apple-touch-icon link');
    assertContainsStr($rootHtml, 'src="assets/images/logo-qwiki.svg"', 'Root wiki renders Qwiki SVG logo');
    echo "1. Root wiki renders favicon links and logo-qwiki.svg: PASS\n";

    // Render Subwiki (empty logoUrl -> inherits parent logo)
    $_SERVER['SCRIPT_NAME'] = '/docs/index.php';
    $_SERVER['REQUEST_URI'] = '/docs/index.php';
    Config::init($subDocs);
    ob_start();
    include $realRoot . '/index.php';
    $subHtml = ob_get_clean();

    assertContainsStr($subHtml, 'images/favicon.svg', 'Subwiki includes SVG favicon link');
    assertContainsStr($subHtml, 'src="assets/images/logo-qwiki.svg"', 'Subwiki inherits parent assets logo');
    assertContainsStr($subHtml, 'class="brand-subwiki-badge"', 'Subwiki still displays subwiki badge');
    echo "2. Subwiki inherits parent assets logo and favicon: PASS\n";

    // Render Subwiki Share Mode
    $_GET['share'] = 'sub-share';
    ob_start();
    include $realRoot . '/index.php';
    $shareHtml = ob_get_clean();
    assertContainsStr($shareHtml, 'src="assets/images/logo-qwiki.svg"', 'Share bar inherits parent logo');
    $_GET = [];
    echo "3. Subwiki share mode renders inherited logo: PASS\n";

    // Test unit resolution for parent uploads/ path inheritance and custom override
    $parentConfig['logoUrl'] = 'uploads/images/custom-parent.svg';
    file_put_contents($tempParent . '/qwiki.json', json_encode($parentConfig, JSON_PRETTY_PRINT));
    $resolvedFromUpload = SubwikiManager::resolveBrandLogoUrl($subConfig, true, 'assets');
    if ($resolvedFromUpload !== '../uploads/images/custom-parent.svg') {
        echo "FAIL: Expected ../uploads/images/custom-parent.svg, got {$resolvedFromUpload}\n";
        exit(1);
    }
    $subOverride = $subConfig;
    $subOverride['logoUrl'] = 'uploads/images/sub-only.png';
    $resolvedOverride = SubwikiManager::resolveBrandLogoUrl($subOverride, true, 'assets');
    if ($resolvedOverride !== 'uploads/images/sub-only.png') {
        echo "FAIL: Expected subwiki override uploads/images/sub-only.png, got {$resolvedOverride}\n";
        exit(1);
    }
    $resolvedMultiTenant = SubwikiManager::resolveBrandLogoUrl($subConfig, true, '/_core/assets');
    $parentConfig['logoUrl'] = 'assets/images/logo-qwiki.svg';
    file_put_contents($tempParent . '/qwiki.json', json_encode($parentConfig, JSON_PRETTY_PRINT));
    $resolvedMtAssets = SubwikiManager::resolveBrandLogoUrl($subConfig, true, '/_core/assets');
    if ($resolvedMtAssets !== '/_core/assets/images/logo-qwiki.svg') {
        echo "FAIL: Expected /_core/assets/images/logo-qwiki.svg, got {$resolvedMtAssets}\n";
        exit(1);
    }
    echo "4. Upload path inheritance, subwiki override, and multi-tenant assetsUrl resolution: PASS\n";

    // Verify all asset files exist
    $requiredAssets = [
        'assets/images/logo-qwiki.svg',
        'assets/images/logo-qwiki.png',
        'assets/images/favicon.svg',
        'assets/images/favicon-16x16.png',
        'assets/images/favicon-32x32.png',
        'assets/images/apple-touch-icon.png',
        'assets/images/favicon.ico',
        'favicon.ico',
    ];
    foreach ($requiredAssets as $rel) {
        if (!file_exists($realRoot . '/' . $rel) || filesize($realRoot . '/' . $rel) === 0) {
            echo "FAIL: Missing or empty asset {$rel}\n";
            exit(1);
        }
    }
    echo "5. All compiled logo and favicon files exist and are non-empty: PASS\n";

} finally {
    cleanupLogoTestDir($tempParent);
    Config::init($realRoot);
}

echo "\nALL LOGO & FAVICON TESTS PASSED! ✨\n";
