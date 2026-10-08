<?php
require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/Totp.php';
require_once __DIR__ . '/../lib/Core/Mailer.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\Totp;

echo "Running Auth, Password Reset & 2FA Test Suite...\n";

// Use scratch dir for safe isolated test environment
$testDir = sys_get_temp_dir() . '/qwiki_auth_test_' . bin2hex(random_bytes(4));
mkdir($testDir, 0755, true);
Config::init($testDir);

// 1. Initialize test users file
$initialUsers = [
    'users' => [
        [
            'username' => 'admin',
            'role' => 'admin',
            'passwordHash' => password_hash('admin123', PASSWORD_DEFAULT),
            'createdAt' => date('Y-m-d H:i:s'),
            'email' => 'admin@example.com',
            'emailVerified' => true
        ],
        [
            'username' => 'alice',
            'role' => 'viewer',
            'passwordHash' => password_hash('alice123', PASSWORD_DEFAULT),
            'createdAt' => date('Y-m-d H:i:s')
            // Note: missing email & twoFactor to test backward compatibility!
        ]
    ]
];
Config::saveUsers($initialUsers);

// Test 1: Backward compatibility loading legacy user records
$loadedUsers = Config::loadUsers();
assert(count($loadedUsers['users']) === 2, "Expected 2 users");
$alice = $loadedUsers['users'][1];
assert($alice['email'] === null, "Legacy user email should be null");
assert($alice['emailVerified'] === false, "Legacy user emailVerified should be false");
assert($alice['twoFactor'] === null, "Legacy user twoFactor should be null");
echo "1. Legacy user record normalization & backward compatibility: PASS\n";

// Test 2: Stateless Token generation and validation
$token = Auth::generateStatelessToken('alice', 'test_type', 3600, 'extra_salt');
$verifiedUser = Auth::verifyStatelessToken($token, 'test_type', 'extra_salt');
assert($verifiedUser === 'alice', "Stateless token verification failed for user 'alice'");

$invalidType = Auth::verifyStatelessToken($token, 'wrong_type', 'extra_salt');
assert($invalidType === null, "Token with wrong type should be rejected");

$invalidSalt = Auth::verifyStatelessToken($token, 'test_type', 'wrong_salt');
assert($invalidSalt === null, "Token with wrong salt should be rejected");
echo "2. Stateless HMAC token generation & cryptographic verification: PASS\n";

// Test 3: Admin Offline Reset Link Generation
// Fake admin session
Auth::startSession();
$_SESSION['qwiki_instance'] = Auth::getInstanceIdentifier();
$_SESSION['qwiki_user'] = ['username' => 'admin', 'role' => 'admin'];
$_SESSION['qwiki_admin'] = true;

$adminLinkResult = Auth::generateAdminResetLink('alice');
assert($adminLinkResult['success'] === true, "Admin reset link generation failed");
assert(!empty($adminLinkResult['resetUrl']), "Reset URL should not be empty");

// Extract token from resetUrl
parse_str(parse_url($adminLinkResult['resetUrl'], PHP_URL_QUERY), $queryParts);
$resetToken = $queryParts['token'] ?? null;
assert(!empty($resetToken), "Could not extract reset token from URL");

$valResult = Auth::validateResetToken($resetToken);
assert($valResult['valid'] === true, "Reset token should be valid");
assert($valResult['username'] === 'alice', "Token should belong to alice");
echo "3. Admin offline reset link generation & validation: PASS\n";

// Test 4: Password Reset and Instant Auto-Invalidation
$resetExec = Auth::resetPasswordWithToken($resetToken, 'newsecret456');
assert($resetExec['success'] === true, "Password reset execution failed");

// Test login with new password
$loginAfterReset = Auth::login('alice', 'newsecret456');
assert($loginAfterReset['success'] === true, "Login with new password failed");

// Crucial: Old reset token MUST now be invalid mathematically without any disk cleanup!
$valOldToken = Auth::validateResetToken($resetToken);
assert($valOldToken['valid'] === false, "Used token MUST be invalid after password change");
echo "4. Password reset execution and automatic cryptographic token invalidation: PASS\n";

// Test 5: Email Verification Flow
$setEmailRes = Auth::setUserEmail('alice', 'alice@qwiki.test', false);
assert($setEmailRes['success'] === true, "setUserEmail failed");

$usersAfterEmail = Config::loadUsers();
$aliceUpdated = null;
foreach ($usersAfterEmail['users'] as $u) {
    if ($u['username'] === 'alice') $aliceUpdated = $u;
}
assert($aliceUpdated['email'] === 'alice@qwiki.test', "Email was not saved");
assert($aliceUpdated['emailVerified'] === false, "Email should start as unverified");

// Simulate verification token
$verifyToken = Auth::generateStatelessToken('alice', 'verify_email', 86400, substr($aliceUpdated['passwordHash'], 0, 16) . ':' . $aliceUpdated['email']);
$verifyRes = Auth::verifyEmail($verifyToken);
assert($verifyRes['success'] === true, "verifyEmail failed: " . ($verifyRes['error'] ?? ''));

$usersAfterVerify = Config::loadUsers();
foreach ($usersAfterVerify['users'] as $u) {
    if ($u['username'] === 'alice') {
        assert($u['emailVerified'] === true, "Email should be verified now");
    }
}
echo "5. Email addition and verification workflow: PASS\n";

// Test 6: 2FA Enrollment and Two-Step Authentication
// Switch session to alice
$_SESSION['qwiki_user'] = ['username' => 'alice', 'role' => 'viewer'];
unset($_SESSION['qwiki_admin']);

$init2fa = Auth::initiate2faSetup('alice');
assert($init2fa['success'] === true, "initiate2faSetup failed");
assert(!empty($init2fa['secret']), "2FA secret missing");
assert(count($init2fa['recoveryCodes']) === 8, "Expected 8 recovery codes");

// Confirm 2FA setup using valid code
$validCode = Totp::getCode($init2fa['secret']);
$confirmRes = Auth::confirm2faSetup($validCode);
assert($confirmRes['success'] === true, "confirm2faSetup failed: " . ($confirmRes['error'] ?? ''));

// Logout alice
Auth::logout();

// Now attempt login: should require 2FA
$loginStep1 = Auth::login('alice', 'newsecret456');
assert($loginStep1['success'] === true, "Step 1 login failed");
assert(!empty($loginStep1['require2fa']), "Expected require2fa = true");

// Step 2: verify 2fa login with code
$step2Code = Totp::getCode($init2fa['secret']);
$loginStep2 = Auth::verify2faLogin($step2Code);
assert($loginStep2['success'] === true, "2FA verification step failed: " . ($loginStep2['error'] ?? ''));
assert(Auth::getCurrentUser()['username'] === 'alice', "Session not established after 2FA");
echo "6. 2FA enrollment, conditional challenge, and TOTP code verification: PASS\n";

// Test 7: Recovery Code Login
Auth::logout();
$loginAgain = Auth::login('alice', 'newsecret456');
assert(!empty($loginAgain['require2fa']), "Expected require2fa = true");

$firstRecoveryCode = $init2fa['recoveryCodes'][0];
$recoveryLoginRes = Auth::verify2faLogin($firstRecoveryCode);
assert($recoveryLoginRes['success'] === true, "Recovery code login failed");

// Test that used recovery code is consumed
Auth::logout();
Auth::login('alice', 'newsecret456');
$reuseRecovery = Auth::verify2faLogin($firstRecoveryCode);
assert($reuseRecovery['success'] === false, "Used recovery code should not be accepted twice");
echo "7. One-time recovery code login & consumption: PASS\n";

// Test 8: Emergency / Admin 2FA Disabling
// Log in as admin
$adminLogin = Auth::login('admin', 'admin123');
assert($adminLogin['success'] === true, "Admin login failed");

$disableRes = Auth::disable2fa('alice');
assert($disableRes['success'] === true, "Admin disable 2FA failed");

// Alice should now be able to log in without 2FA
Auth::logout();
$aliceDirect = Auth::login('alice', 'newsecret456');
assert($aliceDirect['success'] === true && empty($aliceDirect['require2fa']), "Alice should log in directly without 2FA");
echo "8. Administrative 2FA reset & instant direct login restoration: PASS\n";

// Cleanup temp test directory
@unlink($testDir . '/users.json');
@unlink($testDir . '/qwiki.json');
@rmdir($testDir);

echo "\nALL AUTH, PASSWORD RESET & 2FA TESTS PASSED SUCCESSFULLY! 🎉\n";
