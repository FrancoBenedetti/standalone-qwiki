<?php
/**
 * Standalone Qwiki - Demo Reload, Demo Mode & Read-Only Protection Tests
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/DemoManager.php';
require_once __DIR__ . '/../lib/Core/LockManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\DemoManager;
use Qwiki\Core\LockManager;

Config::init();
$baseDir = Config::getBaseDir();

echo "Running Demo Reload, Demo Mode & Read-Only Protection Tests...\n\n";

// ----------------------------------------------------
// 1. Read-Only Document Protection Checks (Config::isChapterProtected)
// ----------------------------------------------------
echo "1. Read-Only Document Protection Checks: ";

$testNodes = [
    [
        'title' => 'Getting Started',
        'slug' => 'getting-started',
        'type' => 'folder',
        'items' => [
            [
                'title' => 'Protected Doc',
                'slug' => 'protected-doc',
                'file' => 'content/getting-started/protected-doc.md',
                'readOnly' => true
            ],
            [
                'title' => 'Uneditable Doc',
                'slug' => 'uneditable-doc',
                'file' => 'content/getting-started/uneditable-doc.md',
                'editable' => false
            ],
            [
                'title' => 'Regular Doc',
                'slug' => 'regular-doc',
                'file' => 'content/getting-started/regular-doc.md'
            ]
        ]
    ]
];

assert(Config::isChapterProtected('protected-doc', $testNodes) === true, "Slug 'protected-doc' must be detected as protected");
assert(Config::isChapterProtected('content/getting-started/protected-doc.md', $testNodes) === true, "File path for protected-doc must be detected as protected");
assert(Config::isChapterProtected('uneditable-doc', $testNodes) === true, "Slug 'uneditable-doc' (editable: false) must be detected as protected");
assert(Config::isChapterProtected('content/getting-started/uneditable-doc.md', $testNodes) === true, "File path for uneditable-doc must be detected as protected");
assert(Config::isChapterProtected('regular-doc', $testNodes) === false, "Regular doc should not be protected");
assert(Config::isChapterProtected('content/getting-started/regular-doc.md', $testNodes) === false, "Regular doc file should not be protected");

echo "PASS\n";

// ----------------------------------------------------
// 2. Demo Mode Detection (Config::isDemoMode)
// ----------------------------------------------------
echo "2. Demo Mode Detection Checks: ";

// Clean state
putenv('QWIKI_DEMO_MODE');
if (file_exists($baseDir . '/.demo')) {
    @unlink($baseDir . '/.demo');
}

// Without env or marker or config
$defaultDemoMode = Config::isDemoMode();
// If config has demoMode: true (like on demo branch), it returns true; otherwise false
// Test explicit env toggles
putenv('QWIKI_DEMO_MODE=1');
assert(Config::isDemoMode() === true, "QWIKI_DEMO_MODE=1 must activate demoMode");

putenv('QWIKI_DEMO_MODE=true');
assert(Config::isDemoMode() === true, "QWIKI_DEMO_MODE=true must activate demoMode");

putenv('QWIKI_DEMO_MODE=0');
assert(Config::isDemoMode() === false, "QWIKI_DEMO_MODE=0 must deactivate demoMode");

putenv('QWIKI_DEMO_MODE'); // unset env

// Test marker file .demo
touch($baseDir . '/.demo');
assert(Config::isDemoMode() === true, "Presence of .demo file must activate demoMode");
@unlink($baseDir . '/.demo');

echo "PASS\n";

// ----------------------------------------------------
// 3. Simulate User Additions & Content Pollution
// ----------------------------------------------------
echo "3. Simulating User Additions & Content Pollution: ";

// Create ad-hoc file and folder in content/
$dummyDir = $baseDir . '/content/visitor-category';
@mkdir($dummyDir, 0755, true);
file_put_contents($dummyDir . '/visitor-page.md', "# Visitor Page\nThis should be wiped out.");
file_put_contents($baseDir . '/content/ad-hoc-test.md', "# Ad-hoc Test\nShould be deleted.");

// Create simulated lock file
LockManager::acquireLock('content/getting-started/features.md', 'dummy-tab-123', 'visitor_user');

// Add a test user to users.json
$userData = Config::loadUsers();
$userData['users'][] = [
    'username' => 'visitor_john',
    'role' => 'admin',
    'passwordHash' => password_hash('secret', PASSWORD_BCRYPT),
    'createdAt' => date('Y-m-d H:i:s')
];
Config::saveUsers($userData);

// Add a dummy upload to uploads/
file_put_contents($baseDir . '/uploads/visitor-upload.svg', '<svg></svg>');

// Verify pollution exists
assert(file_exists($dummyDir . '/visitor-page.md'), "Dummy page must exist before reload");
assert(file_exists($baseDir . '/uploads/visitor-upload.svg'), "Dummy upload must exist before reload");

$checkUsersBefore = Config::loadUsers();
$foundVisitor = false;
foreach ($checkUsersBefore['users'] as $u) {
    if ($u['username'] === 'visitor_john') $foundVisitor = true;
}
assert($foundVisitor === true, "Visitor user must exist before reload");

echo "PASS\n";

// ----------------------------------------------------
// 4. Execute Demo Reload
// ----------------------------------------------------
echo "4. Executing DemoManager::reload(): ";

$reloadResult = DemoManager::reload();
assert($reloadResult['success'] === true, "DemoManager::reload() must succeed: " . ($reloadResult['error'] ?? ''));

echo "PASS\n";

// ----------------------------------------------------
// 5. Verify Fresh Clean State
// ----------------------------------------------------
echo "5. Verifying Clean Restored State: ";

// Verify user additions were wiped
assert(!file_exists($dummyDir . '/visitor-page.md'), "Dummy visitor page must be deleted");
assert(!file_exists($dummyDir), "Dummy visitor directory must be deleted");
assert(!file_exists($baseDir . '/content/ad-hoc-test.md'), "Ad-hoc test page must be deleted");
assert(!file_exists($baseDir . '/uploads/visitor-upload.svg'), "Visitor upload must be deleted");
assert(file_exists($baseDir . '/uploads/.htaccess'), "uploads/.htaccess must be restored");

// Verify users restored
$usersAfter = Config::loadUsers();
assert(count($usersAfter['users']) === 1, "Only 1 default admin user should exist after reload");
assert($usersAfter['users'][0]['username'] === 'admin', "Default user must be 'admin'");

// Verify locks cleared
$lockStatus = LockManager::checkLock('content/getting-started/features.md');
assert($lockStatus['locked'] === false, "Locks must be cleared after reload");

// Verify landing page content exists
$landingContent = file_get_contents($baseDir . '/content/getting-started/introduction.md');
assert(!empty($landingContent), "Landing page content must exist and not be empty");

echo "PASS\n";

// ----------------------------------------------------
// 6. CLI Execution Test
// ----------------------------------------------------
echo "6. Testing demo-reload.php CLI: ";

exec('php ' . escapeshellarg($baseDir . '/demo-reload.php') . ' --quiet', $output, $returnCode);
assert($returnCode === 0, "demo-reload.php CLI must exit with code 0");

echo "PASS\n";

// ----------------------------------------------------
// 7. Auto-Updater Suppression in Demo Mode
// ----------------------------------------------------
echo "7. Testing In-App Updater Suppression in Demo Mode: ";

// Execute a small PHP one-liner that loads admin.php in demo mode and returns JSON
$testScript = <<<'EOD'
putenv('QWIKI_DEMO_MODE=1');
require_once __DIR__ . '/lib/Core/Config.php';
require_once __DIR__ . '/lib/Core/Auth.php';
$_POST = [];
$_REQUEST = ['action' => 'check_updates'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION['qwiki_instance'] = \Qwiki\Core\Auth::getInstanceIdentifier();
$_SESSION['qwiki_user'] = ['username' => 'admin', 'role' => 'admin'];
$_SESSION['qwiki_admin'] = true;
require __DIR__ . '/api/admin.php';
EOD;

$checkOut = shell_exec('php -r ' . escapeshellarg($testScript));
$checkData = json_decode(trim($checkOut), true);
assert(!empty($checkData) && $checkData['success'] === true, "check_updates must succeed");
assert(isset($checkData['has_update']) && $checkData['has_update'] === false, "check_updates must return has_update: false under demo mode");

$testInstallScript = <<<'EOD'
putenv('QWIKI_DEMO_MODE=1');
require_once __DIR__ . '/lib/Core/Config.php';
require_once __DIR__ . '/lib/Core/Auth.php';
$_POST = [];
$_REQUEST = ['action' => 'install_update', 'zip_url' => 'https://example.com/fake.zip'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION['qwiki_instance'] = \Qwiki\Core\Auth::getInstanceIdentifier();
$_SESSION['qwiki_user'] = ['username' => 'admin', 'role' => 'admin'];
$_SESSION['qwiki_admin'] = true;
require __DIR__ . '/api/admin.php';
EOD;

$installOut = shell_exec('php -r ' . escapeshellarg($testInstallScript));
$installData = json_decode(trim($installOut), true);
assert(!empty($installData) && $installData['success'] === false, "install_update must fail under demo mode");
assert(strpos($installData['error'] ?? '', 'disabled in demo mode') !== false, "install_update must return disabled in demo mode error");

echo "PASS\n\n";

echo "ALL DEMO RELOAD, MODE & PROTECTION TESTS PASSED SUCCESSFULLY! 🎉\n";
