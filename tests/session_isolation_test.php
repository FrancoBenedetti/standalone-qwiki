<?php
// Test Session Isolation and Instance Scoping

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;

Config::init();
Auth::startSession();

echo "Running Session Isolation & Multi-Instance Tests...\n\n";

$currentInstance = Auth::getInstanceIdentifier();
if (empty($currentInstance)) {
    echo "FAIL: Current instance identifier is empty\n";
    exit(1);
}
echo "1. Instance identifier resolved: {$currentInstance} (PASS)\n";

// 2. Verify instance hash and session name generation
$instanceHash = substr(hash('sha256', $currentInstance), 0, 8);
$expectedSessionName = 'QWIKISESSID_' . $instanceHash;

if (session_name() !== $expectedSessionName) {
    echo "FAIL: Session name must be {$expectedSessionName}, got " . session_name() . "\n";
    exit(1);
}
echo "2. Session name uniqueness: " . session_name() . " (PASS)\n";

// 3. Verify distinct paths produce distinct session names
$simulatedParent = '/var/www/parent';
$simulatedNested = '/var/www/parent/nested';
$parentHash = substr(hash('sha256', $simulatedParent), 0, 8);
$nestedHash = substr(hash('sha256', $simulatedNested), 0, 8);
if ($parentHash === $nestedHash) {
    echo "FAIL: Parent and nested hashes must differ\n";
    exit(1);
}
if ('QWIKISESSID_' . $parentHash === 'QWIKISESSID_' . $nestedHash) {
    echo "FAIL: Parent and nested session names must differ\n";
    exit(1);
}
echo "3. Parent vs Nested distinct session names: PASS\n";

// 4. Verify cross-instance session rejection (foreign instance session bleed)
$_SESSION['qwiki_instance'] = '/some/foreign/installation/path';
$_SESSION['qwiki_user'] = ['username' => 'foreign_admin', 'role' => 'admin'];
$_SESSION['qwiki_admin'] = true;

if (Auth::isAdmin() !== false) {
    echo "FAIL: Foreign instance session must NOT be granted admin rights\n";
    exit(1);
}
if (Auth::getCurrentUser() !== null) {
    echo "FAIL: Foreign instance session must NOT return current user\n";
    exit(1);
}
if (Auth::isViewer() !== false) {
    echo "FAIL: Foreign instance session must NOT be considered viewer\n";
    exit(1);
}
echo "4. Foreign instance session bleed rejection: PASS\n";

// 5. Verify matching instance session acceptance
$_SESSION['qwiki_instance'] = $currentInstance;
$_SESSION['qwiki_user'] = ['username' => 'local_admin', 'role' => 'admin'];
$_SESSION['qwiki_admin'] = true;

if (Auth::isAdmin() !== true) {
    echo "FAIL: Matching instance session MUST be granted admin rights\n";
    exit(1);
}
if (Auth::getCurrentUser()['username'] !== 'local_admin') {
    echo "FAIL: Matching instance session must return correct user\n";
    exit(1);
}
if (Auth::isViewer() !== true) {
    echo "FAIL: Matching instance session must be considered viewer\n";
    exit(1);
}
echo "5. Local instance session acceptance: PASS\n";

// 6. Test logout unsets instance
Auth::logout();
if (!empty($_SESSION['qwiki_user']) || !empty($_SESSION['qwiki_admin']) || !empty($_SESSION['qwiki_instance'])) {
    echo "FAIL: Logout failed to clear session variables\n";
    exit(1);
}
echo "6. Logout session clearance: PASS\n";

echo "\nALL SESSION ISOLATION TESTS PASSED SUCCESSFULLY! 🛡️\n";
