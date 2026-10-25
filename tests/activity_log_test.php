<?php
/**
 * Standalone Qwiki - Activity & Access Log Extension Test Suite
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/AuditLogger.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\AuditLogger;

$passed = 0;
$failed = 0;

function assertTest($name, $condition, $msg = '') {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$name} - {$msg}\n";
        $failed++;
    }
}

echo "Running Standalone Qwiki Activity Log Tests...\n";

// 1. Test directory creation & .htaccess protection
$logDir = AuditLogger::getLogDir();
assertTest("Log directory exists", is_dir($logDir));
$htaccess = $logDir . '/.htaccess';
assertTest(".htaccess protection file exists in log directory", file_exists($htaccess));
$htaccessContent = file_get_contents($htaccess);
assertTest(".htaccess contains 'Deny from all'", strpos($htaccessContent, 'Deny from all') !== false);

// 2. Test IP Anonymization Modes
$testIpv4 = '203.0.113.195';
$resHash = AuditLogger::resolveClientIp($testIpv4, 'hash');
assertTest("IP mode 'hash' generates 12-char hash", strlen($resHash['ip']) === 12 && $resHash['type'] === 'hash');
assertTest("IP mode 'hash' matches sha256 prefix", $resHash['ip'] === substr(hash('sha256', $testIpv4), 0, 12));

$resMasked = AuditLogger::resolveClientIp($testIpv4, 'masked');
assertTest("IP mode 'masked' masks subnet to .0/24", $resMasked['ip'] === '203.0.113.0/24' && $resMasked['type'] === 'masked');

$resRaw = AuditLogger::resolveClientIp($testIpv4, 'raw');
assertTest("IP mode 'raw' returns full IP", $resRaw['ip'] === $testIpv4 && $resRaw['type'] === 'raw');

// 3. Test Share Access Logging
$testShareKey = 'test_share_' . bin2hex(random_bytes(4));
$testChapter = [
    'slug' => 'test-document-slug',
    'title' => 'Test Document Title',
    'file' => 'content/test/test.md',
    'type' => 'markdown'
];

$_SERVER['HTTP_REFERER'] = 'https://example.com/ref';
$_SERVER['HTTP_USER_AGENT'] = 'TestRunner/1.0';

$logSuccess = AuditLogger::logShareAccess($testShareKey, $testChapter, null, false);
assertTest("AuditLogger::logShareAccess returns true on success", $logSuccess === true);

// 4. Test 404 Share Access Logging
$log404 = AuditLogger::logShareAccess('invalid_key_123', null, 'not_found', false);
assertTest("AuditLogger::logShareAccess records 404 error", $log404 === true);

// 5. Test Auth and Mutation Logging
$logAuthRes = AuditLogger::logAuth('login_failed', 401, ['attempted' => 'attacker', 'reason' => 'invalid_pass'], 'attacker');
assertTest("AuditLogger::logAuth records failed login", $logAuthRes === true);

$logMutationRes = AuditLogger::logMutation('doc_saved', 200, ['file' => 'content/test/test.md', 'size_bytes' => 1234], 'admin');
assertTest("AuditLogger::logMutation records document mutation", $logMutationRes === true);

// 6. Test Querying Logs
$queryAll = AuditLogger::queryLogs([], 1, 50);
assertTest("AuditLogger::queryLogs returns items array", isset($queryAll['items']) && is_array($queryAll['items']));
assertTest("AuditLogger::queryLogs found recorded items", $queryAll['total'] >= 4);

// Filter by share category
$queryShare = AuditLogger::queryLogs(['category' => 'share'], 1, 50);
assertTest("AuditLogger::queryLogs filters by category=share", $queryShare['total'] >= 2);
foreach ($queryShare['items'] as $it) {
    if ($it['category'] !== 'share') {
        assertTest("All items in share query have category=share", false, "Found: " . $it['category']);
        break;
    }
}

// Search by share key
$querySearch = AuditLogger::queryLogs(['search' => $testShareKey], 1, 50);
assertTest("AuditLogger::queryLogs searches by share key", $querySearch['total'] >= 1);
assertTest("Matched item has correct share key in details", ($querySearch['items'][0]['details']['share_key'] ?? '') === $testShareKey);

// 7. Test getStats
$stats = AuditLogger::getStats();
assertTest("AuditLogger::getStats returns total_entries", isset($stats['total_entries']) && $stats['total_entries'] >= 4);
assertTest("AuditLogger::getStats returns share_hits_total", isset($stats['share_hits_total']) && $stats['share_hits_total'] >= 2);
assertTest("AuditLogger::getStats returns failed_logins_today", isset($stats['failed_logins_today']) && $stats['failed_logins_today'] >= 1);
assertTest("AuditLogger::getStats tracks top shared document", !empty($stats['top_shared_doc']));

// 8. Test Extension Discovery
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';
$extManager = \Qwiki\Core\ExtensionManager::getInstance();
$utilities = $extManager->getUtilities();
assertTest("ExtensionManager discovers activity_log extension", isset($utilities['activity_log']));
assertTest("Activity log extension is adminOnly", !empty($utilities['activity_log']['adminOnly']));
assertTest("Activity log extension has placement header_menu", ($utilities['activity_log']['placement'] ?? '') === 'header_menu');

// 9. Test Extension Handler via simulated admin
$_SESSION['qwiki_admin'] = true;
$_SESSION['qwiki_user'] = ['username' => 'admin', 'role' => 'admin'];
$_SESSION['qwiki_instance'] = Auth::getInstanceIdentifier();

ob_start();
$handled = $extManager->handleAction('ext_activity_get_stats', []);
$rawOutput = ob_get_clean();
assertTest("ExtensionManager::handleAction handles ext_activity_get_stats", $handled === true);
$statsJson = json_decode($rawOutput, true);
assertTest("ext_activity_get_stats returns success=true", ($statsJson['success'] ?? false) === true);
assertTest("ext_activity_get_stats includes ipPrivacyMode setting", isset($statsJson['settings']['ipPrivacyMode']));

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
if ($failed > 0) {
    exit(1);
}
exit(0);
