<?php
// Test Subwikis Accordion Navigation & Permission Enforcement

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/SubwikiManager.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\SubwikiManager;
use Qwiki\Core\Navigation;

echo "Running Subwikis Sidebar Accordion Tests...\n\n";

$realRoot = realpath(__DIR__ . '/..');
$tempParent = sys_get_temp_dir() . '/qwiki_test_parent_' . uniqid();
@mkdir($tempParent, 0755, true);

// Recursive delete helper
function cleanupDir($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? cleanupDir($path) : @unlink($path);
    }
    @rmdir($dir);
}

try {
    // Setup simulated parent environment in /tmp
    $parentConfig = [
        'title' => 'Parent Documentation Portal',
        'logoText' => 'PARENT',
        'theme' => 'theme-default.css',
        'showPoweredBy' => true,
        'showSubwikisInSidebar' => false,
        'books' => [
            [
                'id' => 'intro',
                'title' => 'Introduction',
                'type' => 'folder',
                'items' => [
                    ['slug' => 'welcome', 'title' => 'Welcome', 'type' => 'markdown', 'file' => 'content/welcome.md']
                ]
            ]
        ],
        'subwikis' => []
    ];
    file_put_contents($tempParent . '/qwiki.json', json_encode($parentConfig, JSON_PRETTY_PRINT));
    @mkdir($tempParent . '/content', 0755, true);
    file_put_contents($tempParent . '/content/welcome.md', '# Welcome to Parent Wiki');

    // Create subwiki-alpha
    $alphaDir = $tempParent . '/subwiki-alpha';
    @mkdir($alphaDir . '/content', 0755, true);
    file_put_contents($alphaDir . '/.subwiki', "subwiki\n");
    $alphaConfig = [
        'title' => 'Alpha Subwiki',
        'isSubwiki' => true,
        'parentUrl' => '../',
        'parentTitle' => 'Parent Documentation Portal',
        'books' => [
            [
                'id' => 'alpha-docs',
                'title' => 'Alpha Docs',
                'type' => 'folder',
                'items' => [
                    ['slug' => 'getting-started', 'title' => 'Getting Started', 'type' => 'markdown']
                ]
            ]
        ]
    ];
    file_put_contents($alphaDir . '/qwiki.json', json_encode($alphaConfig, JSON_PRETTY_PRINT));
    file_put_contents($alphaDir . '/index.php', '<?php // stub');

    // Create subwiki-beta
    $betaDir = $tempParent . '/subwiki-beta';
    @mkdir($betaDir . '/content', 0755, true);
    file_put_contents($betaDir . '/.subwiki', "subwiki\n");
    $betaConfig = [
        'title' => 'Beta Subwiki',
        'isSubwiki' => true,
        'parentUrl' => '../',
        'parentTitle' => 'Parent Documentation Portal',
        'books' => [
            [
                'id' => 'beta-docs',
                'title' => 'Beta Docs',
                'type' => 'folder',
                'items' => [
                    ['slug' => 'overview', 'title' => 'Overview', 'type' => 'markdown'],
                    ['slug' => 'api', 'title' => 'API Reference', 'type' => 'markdown']
                ]
            ]
        ]
    ];
    file_put_contents($betaDir . '/qwiki.json', json_encode($betaConfig, JSON_PRETTY_PRINT));
    file_put_contents($betaDir . '/index.php', '<?php // stub');

    // Register subwikis in parent config
    $parentConfig['subwikis'] = [
        ['slug' => 'subwiki-alpha', 'title' => 'Alpha Subwiki'],
        ['slug' => 'subwiki-beta', 'title' => 'Beta Subwiki']
    ];
    file_put_contents($tempParent . '/qwiki.json', json_encode($parentConfig, JSON_PRETTY_PRINT));

    // 1. Test getSidebarSubwikis in Parent Context
    Config::init($tempParent);
    if (SubwikiManager::isSubwiki()) {
        echo "FAIL: Expected parent to not be a subwiki\n";
        exit(1);
    }

    $parentList = SubwikiManager::getSidebarSubwikis();
    if (count($parentList) !== 2) {
        echo "FAIL: Expected 2 subwikis in parent context, got " . count($parentList) . "\n";
        exit(1);
    }
    echo "1. SubwikiManager::getSidebarSubwikis() in parent context returns 2 subwikis: PASS\n";

    // 2. Test getSidebarSubwikis in Child Subwiki Context (subwiki-alpha querying siblings)
    Config::init($alphaDir);
    if (!SubwikiManager::isSubwiki()) {
        echo "FAIL: Expected subwiki-alpha to be identified as subwiki\n";
        exit(1);
    }

    $alphaSidebarList = SubwikiManager::getSidebarSubwikis();
    if (count($alphaSidebarList) !== 1) {
        echo "FAIL: Expected subwiki-alpha to see 1 sibling subwiki, got " . count($alphaSidebarList) . "\n";
        exit(1);
    }

    $sibling = $alphaSidebarList[0];
    if ($sibling['slug'] !== 'subwiki-beta') {
        echo "FAIL: Expected sibling slug to be 'subwiki-beta', got '{$sibling['slug']}'\n";
        exit(1);
    }
    if ($sibling['title'] !== 'Beta Subwiki') {
        echo "FAIL: Expected sibling title to be 'Beta Subwiki', got '{$sibling['title']}'\n";
        exit(1);
    }
    if ($sibling['url'] !== '../subwiki-beta/') {
        echo "FAIL: Expected sibling URL to be '../subwiki-beta/', got '{$sibling['url']}'\n";
        exit(1);
    }
    if ($sibling['docCount'] !== 2) {
        echo "FAIL: Expected sibling docCount to be 2, got '{$sibling['docCount']}'\n";
        exit(1);
    }
    echo "2. Child subwiki resolves siblings excluding self: PASS\n";

    // 3. Test Settings Persistence via api/admin.php
    Config::init($tempParent);
    Auth::startSession();
    $_SESSION['qwiki_instance'] = Auth::getInstanceIdentifier();
    $_SESSION['qwiki_user'] = ['username' => 'admin', 'role' => 'admin'];
    $_SESSION['qwiki_admin'] = true;
    $_SERVER['REQUEST_METHOD'] = 'POST';

    // A: Enable showSubwikisInSidebar
    $_POST = [
        'action' => 'update_settings',
        'title' => 'Parent Documentation Portal',
        'logoText' => 'PARENT',
        'theme' => 'theme-default.css',
        'showSubwikisInSidebar' => '1',
        'showPoweredBy' => '1'
    ];

    ob_start();
    include __DIR__ . '/../api/admin.php';
    $saveResponse = ob_get_clean();

    $updated = Config::load();
    if (empty($updated['showSubwikisInSidebar'])) {
        echo "FAIL: showSubwikisInSidebar was not saved as true\n";
        exit(1);
    }
    echo "3a. Settings persistence (enable showSubwikisInSidebar): PASS\n";

    // B: Disable showSubwikisInSidebar
    $_POST = [
        'action' => 'update_settings',
        'title' => 'Parent Documentation Portal',
        'logoText' => 'PARENT',
        'theme' => 'theme-default.css',
        'showPoweredBy' => '1'
    ];

    ob_start();
    include __DIR__ . '/../api/admin.php';
    $saveResponse2 = ob_get_clean();

    $updated2 = Config::load();
    if (!empty($updated2['showSubwikisInSidebar'])) {
        echo "FAIL: showSubwikisInSidebar was not saved as false\n";
        exit(1);
    }
    echo "3b. Settings persistence (disable showSubwikisInSidebar): PASS\n";

    // 4. Test Rendering in index.php
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['REQUEST_URI'] = '/index.php';
    $_GET = [];

    // Link required directories for index.php execution
    @symlink($realRoot . '/assets', $tempParent . '/assets');
    @symlink($realRoot . '/lib', $tempParent . '/lib');

    // 4a. When showSubwikisInSidebar is false:
    $updated2['showSubwikisInSidebar'] = false;
    Config::save($updated2);
    Config::init($tempParent);

    ob_start();
    include __DIR__ . '/../index.php';
    $htmlDisabled = ob_get_clean();

    if (strpos($htmlDisabled, 'nav-subwikis-group') !== false) {
        echo "FAIL: nav-subwikis-group must NOT be in HTML when showSubwikisInSidebar is false\n";
        exit(1);
    }
    echo "4a. Sidebar accordion omitted when showSubwikisInSidebar = false: PASS\n";

    // 4b. When showSubwikisInSidebar is true:
    $updated2['showSubwikisInSidebar'] = true;
    Config::save($updated2);
    Config::init($tempParent);

    ob_start();
    include __DIR__ . '/../index.php';
    $htmlEnabled = ob_get_clean();

    if (strpos($htmlEnabled, 'nav-subwikis-group') === false) {
        echo "FAIL: nav-subwikis-group MUST be present in sidebar HTML when showSubwikisInSidebar is true\n";
        exit(1);
    }
    if (strpos($htmlEnabled, 'Alpha Subwiki') === false || strpos($htmlEnabled, 'Beta Subwiki') === false) {
        echo "FAIL: Both subwiki titles must be present in sidebar accordion\n";
        exit(1);
    }
    if (strpos($htmlEnabled, 'badge-subwiki-count') === false) {
        echo "FAIL: badge-subwiki-count must be present in sidebar subwiki items\n";
        exit(1);
    }
    if (strpos($htmlEnabled, 'name="showSubwikisInSidebar"') === false) {
        echo "FAIL: showSubwikisInSidebar checkbox must be present in settings modal\n";
        exit(1);
    }
    echo "4b. Sidebar accordion renders with subwiki items and count badges: PASS\n";

} finally {
    // Cleanup temporary directory
    cleanupDir($tempParent);
    Config::init($realRoot);
}

echo "\nALL SUBWIKIS SIDEBAR ACCORDION TESTS PASSED! 🌐✨\n";
