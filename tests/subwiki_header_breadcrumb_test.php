<?php
// Test Subwiki Header Brand Breadcrumb & Wayfinding

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/SubwikiManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\SubwikiManager;

echo "Running Subwiki Header Brand Breadcrumb Tests...\n\n";

$realRoot = realpath(__DIR__ . '/..');
$tempParent = sys_get_temp_dir() . '/qwiki_header_test_' . uniqid();
@mkdir($tempParent, 0755, true);

function cleanupDir($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_link($path)) {
            @unlink($path);
        } elseif (is_dir($path)) {
            cleanupDir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

try {
    // 1. Setup Parent Environment
    $parentConfig = [
        'title' => 'MoWiki',
        'logoText' => 'MOWIKI',
        'logoUrl' => 'uploads/images/mobioplus.png',
        'theme' => 'theme-default.css',
        'showPoweredBy' => true,
        'books' => [
            [
                'id' => 'general',
                'title' => 'General',
                'type' => 'folder',
                'items' => [
                    ['slug' => 'home', 'title' => 'Home', 'type' => 'markdown', 'file' => 'content/home.md']
                ]
            ]
        ]
    ];
    file_put_contents($tempParent . '/qwiki.json', json_encode($parentConfig, JSON_PRETTY_PRINT));
    @mkdir($tempParent . '/content', 0755, true);
    file_put_contents($tempParent . '/content/home.md', '# Parent Home');

    // Symlink assets and lib
    @symlink($realRoot . '/assets', $tempParent . '/assets');
    @symlink($realRoot . '/lib', $tempParent . '/lib');

    // 2. Setup Subwiki "Library"
    $libraryDir = $tempParent . '/library';
    @mkdir($libraryDir . '/content', 0755, true);
    file_put_contents($libraryDir . '/.subwiki', "subwiki\n");
    $subwikiConfig = [
        'title' => 'Library',
        'logoText' => 'LIBRARY',
        'logoUrl' => 'uploads/images/mobioplus.png', // Same company logo selected!
        'isSubwiki' => true,
        'parentUrl' => '../',
        'parentTitle' => 'MoWiki',
        'books' => [
            [
                'id' => 'home',
                'title' => 'About This Library',
                'type' => 'folder',
                'items' => [
                    ['slug' => 'read-me', 'title' => 'Read Me', 'type' => 'markdown', 'file' => 'content/read-me.md', 'shareKey' => 'test-share-123']
                ]
            ]
        ]
    ];
    file_put_contents($libraryDir . '/qwiki.json', json_encode($subwikiConfig, JSON_PRETTY_PRINT));
    file_put_contents($libraryDir . '/content/read-me.md', '# What is the Library?');
    @symlink($realRoot . '/assets', $libraryDir . '/assets');
    @symlink($realRoot . '/lib', $libraryDir . '/lib');

    // 3. Test Parent Wiki Header Rendering
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_HOST'] = 'wiki.mobioplus.co.za';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['REQUEST_URI'] = '/index.php';
    $_GET = [];

    Config::init($tempParent);
    ob_start();
    include __DIR__ . '/../index.php';
    $parentHtml = ob_get_clean();

    // Verification in Parent Wiki:
    if (strpos($parentHtml, 'class="brand-header-group"') === false) {
        echo "FAIL: brand-header-group container missing in parent wiki\n";
        exit(1);
    }
    if (strpos($parentHtml, 'class="brand-subwiki-badge"') !== false) {
        echo "FAIL: brand-subwiki-badge must NOT be rendered in parent wiki\n";
        exit(1);
    }
    if (strpos($parentHtml, 'class="brand-separator"') !== false) {
        echo "FAIL: brand-separator must NOT be rendered in parent wiki\n";
        exit(1);
    }
    echo "1. Parent wiki header renders logo without subwiki badge: PASS\n";

    // 4. Test Subwiki Header Rendering
    $_SERVER['SCRIPT_NAME'] = '/library/index.php';
    $_SERVER['REQUEST_URI'] = '/library/index.php';
    Config::init($libraryDir);

    ob_start();
    include __DIR__ . '/../index.php';
    $subwikiHtml = ob_get_clean();

    // Verification in Subwiki:
    if (strpos($subwikiHtml, 'class="brand-header-group"') === false) {
        echo "FAIL: brand-header-group container missing in subwiki\n";
        exit(1);
    }
    if (strpos($subwikiHtml, 'class="brand-separator"') === false) {
        echo "FAIL: brand-separator missing in subwiki header\n";
        exit(1);
    }
    if (strpos($subwikiHtml, 'class="brand-subwiki-badge"') === false) {
        echo "FAIL: brand-subwiki-badge missing in subwiki header\n";
        exit(1);
    }
    if (strpos($subwikiHtml, 'Library') === false) {
        echo "FAIL: Subwiki title 'Library' must appear in brand header\n";
        exit(1);
    }
    if (strpos($subwikiHtml, 'Back to MoWiki') === false) {
        echo "FAIL: Logo tooltip must indicate return path 'Back to MoWiki'\n";
        exit(1);
    }
    if (strpos($subwikiHtml, 'href="../"') === false) {
        echo "FAIL: Logo in subwiki must link back to parentUrl\n";
        exit(1);
    }
    echo "2. Subwiki header renders logo linking to parent and subwiki badge linking to subwiki root: PASS\n";

    // 5. Test Share Mode in Subwiki
    $_GET['share'] = 'test-share-123';
    ob_start();
    include __DIR__ . '/../index.php';
    $shareHtml = ob_get_clean();

    if (strpos($shareHtml, 'brand-subwiki-badge-share') === false) {
        echo "FAIL: Share mode in subwiki must include brand-subwiki-badge-share\n";
        exit(1);
    }
    if (strpos($shareHtml, 'Library') === false) {
        echo "FAIL: Share mode in subwiki must display subwiki title\n";
        exit(1);
    }
    echo "3. Share mode in subwiki displays subwiki context: PASS\n";

} finally {
    cleanupDir($tempParent);
    Config::init($realRoot);
}

echo "\nALL SUBWIKI HEADER BREADCRUMB TESTS PASSED! 🌐✨\n";
