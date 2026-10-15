<?php
require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/LockManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\LockManager;

Config::init();

$testFile = 'content/test-book/test.md';
$tabA = 'tab-uuid-aaaa-1111';
$tabB = 'tab-uuid-bbbb-2222';
$userA = 'admin';
$userB = 'editor_jane';

echo "Running LockManager Tests...\n\n";

// Ensure clean slate
LockManager::releaseLock($testFile, $tabA, $userA, true);

// 1. Tab A acquires lock
echo "1. Tab A acquires lock: ";
$res1 = LockManager::acquireLock($testFile, $tabA, $userA);
assert($res1['success'] === true, "Tab A should acquire lock");
echo "PASS\n";

// 2. Tab B tries to acquire lock without force
echo "2. Tab B attempts to acquire without force: ";
$res2 = LockManager::acquireLock($testFile, $tabB, $userB, false);
assert($res2['success'] === false, "Tab B should be blocked");
assert($res2['code'] === 'LOCKED_BY_OTHER', "Expected code LOCKED_BY_OTHER, got: " . ($res2['code'] ?? ''));
assert($res2['lockedBy'] === $userA, "Expected lockedBy = admin");
echo "PASS\n";

// 3. Status check
echo "3. Status check: ";
$statusA = LockManager::checkLock($testFile, $tabA);
$statusB = LockManager::checkLock($testFile, $tabB);
assert($statusA['locked'] === true && $statusA['isCurrentTab'] === true, "Tab A is owner");
assert($statusB['locked'] === true && $statusB['isCurrentTab'] === false, "Tab B is not owner");
echo "PASS\n";

// 4. Tab A renews lock
echo "4. Tab A renews lock: ";
$renewA = LockManager::renewLock($testFile, $tabA, $userA);
assert($renewA['success'] === true, "Tab A renew should succeed");
echo "PASS\n";

// 5. Save verification
echo "5. Save verification: ";
$saveA = LockManager::verifyOrRejectSave($testFile, $tabA, $userA);
$saveB = LockManager::verifyOrRejectSave($testFile, $tabB, $userB);
assert($saveA['allowed'] === true, "Tab A save must be allowed");
assert($saveB['allowed'] === false, "Tab B save must be blocked");
echo "PASS\n";

// 6. Tab B forces takeover
echo "6. Tab B force takeover: ";
$takeover = LockManager::acquireLock($testFile, $tabB, $userB, true);
assert($takeover['success'] === true, "Tab B takeover should succeed");
$statusNow = LockManager::checkLock($testFile, $tabB);
assert($statusNow['user'] === $userB, "Owner should now be Tab B");
echo "PASS\n";

// 7. Tab A attempts renewal after being taken over
echo "7. Tab A renewal after takeover: ";
$renewA2 = LockManager::renewLock($testFile, $tabA, $userA);
assert($renewA2['success'] === false, "Tab A renewal should fail");
assert($renewA2['code'] === 'LOCK_TAKEN_OVER', "Expected LOCK_TAKEN_OVER, got: " . ($renewA2['code'] ?? ''));
echo "PASS\n";

// 8. Tab B releases lock
echo "8. Tab B voluntary release: ";
$rel = LockManager::releaseLock($testFile, $tabB, $userB);
assert($rel['success'] === true, "Release should succeed");
$finalStatus = LockManager::checkLock($testFile);
assert($finalStatus['locked'] === false, "Doc should now be unlocked");
echo "PASS\n";

echo "\nALL LOCK TESTS PASSED SUCCESSFULLY! 🎉\n";
