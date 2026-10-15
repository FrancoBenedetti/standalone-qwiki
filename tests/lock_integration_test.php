<?php
// Test API endpoint behaviors with simulation

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/LockManager.php';
require_once __DIR__ . '/../lib/Core/Auth.php';

use Qwiki\Core\Config;
use Qwiki\Core\LockManager;
use Qwiki\Core\Auth;

Config::init();

echo "Running API Concurrency & Save Protection Simulation...\n\n";

$testDoc = 'content/test-book/test.md';
$tab1 = 'client-tab-1111';
$tab2 = 'client-tab-2222';
$user = 'admin';

// Clean start
LockManager::releaseLock($testDoc, $tab1, $user, true);

// 1. Initial status
$status = LockManager::checkLock($testDoc, $tab1);
assert($status['locked'] === false, "Should be initially unlocked");
echo "1. Initial checkLock: PASS\n";

// 2. Tab 1 acquires lock
$acq1 = LockManager::acquireLock($testDoc, $tab1, $user);
assert($acq1['success'] === true, "Tab 1 acquisition failed");
echo "2. Tab 1 acquireLock: PASS\n";

// 3. Tab 2 checks status
$status2 = LockManager::checkLock($testDoc, $tab2);
assert($status2['locked'] === true, "Doc should report locked to Tab 2");
assert($status2['isCurrentTab'] === false, "Tab 2 must not be current tab");
echo "3. Tab 2 checkLock: PASS\n";

// 4. Tab 1 verifies save allowed
$save1 = LockManager::verifyOrRejectSave($testDoc, $tab1, $user);
assert($save1['allowed'] === true, "Tab 1 save should be allowed");
echo "4. Tab 1 save validation: PASS\n";

// 5. Tab 2 verifies save BLOCKED
$save2 = LockManager::verifyOrRejectSave($testDoc, $tab2, $user);
assert($save2['allowed'] === false, "Tab 2 save MUST be rejected");
echo "5. Tab 2 save blocked: PASS\n";

// 6. Tab 2 forces takeover
$takeover = LockManager::acquireLock($testDoc, $tab2, $user, true);
assert($takeover['success'] === true, "Tab 2 force takeover should succeed");
echo "6. Tab 2 force takeover: PASS\n";

// 7. Tab 1 tries to save now (was evicted!)
$save1After = LockManager::verifyOrRejectSave($testDoc, $tab1, $user);
assert($save1After['allowed'] === false, "Tab 1 save after eviction MUST be blocked");
echo "7. Tab 1 evicted save blocked: PASS\n";

// 8. Tab 2 saves and auto-releases lock
$save2Allowed = LockManager::verifyOrRejectSave($testDoc, $tab2, $user);
assert($save2Allowed['allowed'] === true, "Tab 2 save allowed");
LockManager::releaseLock($testDoc, $tab2, $user);
$finalCheck = LockManager::checkLock($testDoc);
assert($finalCheck['locked'] === false, "Document must be unlocked after release");
echo "8. Tab 2 release & final status: PASS\n";

echo "\nALL CONCURRENCY TESTS PASSED! 🚀\n";
