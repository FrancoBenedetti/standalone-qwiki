<?php
// Test Multi-Tenant Mode & BaseDir Decoupling

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/SubwikiManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\SubwikiManager;

echo "Running Multi-Tenant Mode & BaseDir Decoupling Tests...\n\n";

$realRoot = realpath(__DIR__ . '/..');

// 1. Test Standalone Default Resolution
Config::init($realRoot);
if (Config::getBaseDir() !== $realRoot) {
    echo "FAIL: Standalone base dir mismatch. Expected {$realRoot}, got " . Config::getBaseDir() . "\n";
    exit(1);
}
echo "1. Standalone default resolution: PASS\n";

// 2. Test QWIKI_BASE_DIR Constant Override
$tempTenant = sys_get_temp_dir() . '/qwiki_tenant_' . uniqid();
@mkdir($tempTenant, 0755, true);

if (!defined('QWIKI_BASE_DIR')) {
    define('QWIKI_BASE_DIR', $tempTenant);
}

// Reset static cache in Config
$reflProp = new ReflectionProperty(Config::class, 'baseDir');
$reflProp->setAccessible(true);
$reflProp->setValue(null, null);

Config::init(); // should pick up QWIKI_BASE_DIR
if (Config::getBaseDir() !== $tempTenant) {
    echo "FAIL: QWIKI_BASE_DIR override failed. Expected {$tempTenant}, got " . Config::getBaseDir() . "\n";
    exit(1);
}
if (Config::getConfigFile() !== $tempTenant . '/qwiki.json') {
    echo "FAIL: Config file path mismatch\n";
    exit(1);
}
if (Config::getUsersFile() !== $tempTenant . '/users.json') {
    echo "FAIL: Users file path mismatch\n";
    exit(1);
}
echo "2. QWIKI_BASE_DIR constant override: PASS\n";

// 3. Test Hosted Subwiki Lightweight Bootstrap Generation
$tenantConfig = [
    'title' => 'Hosted Tenant Wiki',
    'books' => [
        [
            'id' => 'general',
            'title' => 'General',
            'type' => 'folder',
            'items' => [
                ['slug' => 'intro', 'title' => 'Intro', 'type' => 'markdown']
            ]
        ]
    ]
];
file_put_contents($tempTenant . '/qwiki.json', json_encode($tenantConfig, JSON_PRETTY_PRINT));
file_put_contents($tempTenant . '/users.json', json_encode(['users' => []], JSON_PRETTY_PRINT));

$deployResult = SubwikiManager::deploySubwiki('client-portal', 'Client Portal', 'admin', 'Secr3tP@ss!');
if (empty($deployResult['success'])) {
    echo "FAIL: Subwiki deployment failed: " . ($deployResult['error'] ?? 'unknown') . "\n";
    exit(1);
}

$subwikiIndex = $tempTenant . '/client-portal/index.php';
if (!file_exists($subwikiIndex)) {
    echo "FAIL: Subwiki index.php not generated\n";
    exit(1);
}

$indexContent = file_get_contents($subwikiIndex);
if (strpos($indexContent, "define('QWIKI_BASE_DIR', __DIR__);") === false) {
    echo "FAIL: Subwiki index.php does not contain QWIKI_BASE_DIR bootstrap\n";
    exit(1);
}
if (strpos($indexContent, "define('QWIKI_ASSETS_URL'") === false) {
    echo "FAIL: Subwiki index.php does not contain QWIKI_ASSETS_URL bootstrap\n";
    exit(1);
}
if (is_dir($tempTenant . '/client-portal/lib')) {
    echo "FAIL: Hosted subwiki must NOT duplicate the lib directory\n";
    exit(1);
}
if (is_dir($tempTenant . '/client-portal/assets')) {
    echo "FAIL: Hosted subwiki must NOT duplicate the assets directory\n";
    exit(1);
}
echo "3. Hosted subwiki lightweight bootstrap generation: PASS\n";

// 4. Verify cascadeUpdates in hosted mode
$cascade = SubwikiManager::cascadeUpdates($tempTenant);
if (empty($cascade['success'])) {
    echo "FAIL: cascadeUpdates failed in hosted mode\n";
    exit(1);
}
echo "4. cascadeUpdates in hosted mode: PASS\n";

// Cleanup
function cleanRecursive($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $p = $dir . '/' . $file;
        if (is_link($p)) {
            @unlink($p);
        } elseif (is_dir($p)) {
            cleanRecursive($p);
        } else {
            @unlink($p);
        }
    }
    @rmdir($dir);
}
cleanRecursive($tempTenant);

echo "\nALL MULTI-TENANT TESTS PASSED SUCCESSFULLY! 🚀✨\n";
