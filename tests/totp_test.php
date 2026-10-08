<?php
require_once __DIR__ . '/../lib/Core/Totp.php';

use Qwiki\Core\Totp;

echo "Running Totp Test Suite...\n";

// 1. Test Base32 roundtrip
$original = "Hello World! 123";
$b32 = Totp::encodeBase32($original);
$decoded = Totp::decodeBase32($b32);
assert($decoded === $original, "Base32 roundtrip failed: expected '{$original}', got '{$decoded}'");
echo "1. Base32 encode & decode roundtrip: PASS\n";

// 2. Test RFC 6238 test vectors for HMAC-SHA1
// Test secret: ASCII "12345678901234567890" (20 bytes)
// Base32 for "12345678901234567890" is GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ
$rfcSecret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

// Known RFC 6238 test vectors for SHA-1:
// Time: 59 sec -> slice 1 -> TOTP: 287082
$code59 = Totp::getCode($rfcSecret, (int)floor(59 / 30));
assert($code59 === '287082', "RFC vector at 59s failed: expected 287082, got {$code59}");

// Time: 1111111109 sec -> slice 37037036 -> TOTP: 081804
$code1111111109 = Totp::getCode($rfcSecret, (int)floor(1111111109 / 30));
assert($code1111111109 === '081804', "RFC vector at 1111111109s failed: expected 081804, got {$code1111111109}");

// Time: 1234567890 sec -> slice 41152263 -> TOTP: 005924
$code1234567890 = Totp::getCode($rfcSecret, (int)floor(1234567890 / 30));
assert($code1234567890 === '005924', "RFC vector at 1234567890s failed: expected 005924, got {$code1234567890}");

// Time: 2000000000 sec -> slice 66666666 -> TOTP: 279037
$code2000000000 = Totp::getCode($rfcSecret, (int)floor(2000000000 / 30));
assert($code2000000000 === '279037', "RFC vector at 2000000000s failed: expected 279037, got {$code2000000000}");
echo "2. RFC 6238 test vectors verification: PASS\n";

// 3. Test verification with drift window
$time = 1700000000;
$slice = (int)floor($time / 30);
$codeCurrent = Totp::getCode($rfcSecret, $slice);
$codePrev = Totp::getCode($rfcSecret, $slice - 1);
$codeNext = Totp::getCode($rfcSecret, $slice + 1);
$codeFar = Totp::getCode($rfcSecret, $slice + 5);

assert(Totp::verifyCode($rfcSecret, $codeCurrent, 1, $time) === true, "Current slice verification failed");
assert(Totp::verifyCode($rfcSecret, $codePrev, 1, $time) === true, "Previous slice verification failed");
assert(Totp::verifyCode($rfcSecret, $codeNext, 1, $time) === true, "Next slice verification failed");
assert(Totp::verifyCode($rfcSecret, $codeFar, 1, $time) === false, "Far slice should not be valid with drift 1");
assert(Totp::verifyCode($rfcSecret, '123456', 1, $time) === false, "Random code should fail");
assert(Totp::verifyCode($rfcSecret, 'abc', 1, $time) === false, "Invalid format should fail");
echo "3. Time-drift window verification: PASS\n";

// 4. Test recovery codes generation, hashing and one-time consumption
$codes = Totp::generateRecoveryCodes(8);
assert(count($codes) === 8, "Expected 8 recovery codes");
assert(preg_match('/^[a-f0-9]{4}-[a-f0-9]{4}$/', $codes[0]), "Invalid recovery code format: {$codes[0]}");

$hashed = Totp::hashRecoveryCodes($codes);
assert(count($hashed) === 8, "Expected 8 hashed codes");

// Verify first code
$firstCode = $codes[0];
assert(Totp::verifyAndConsumeRecoveryCode($hashed, $firstCode) === true, "Verification of first code failed");
assert(count($hashed) === 7, "Hashed codes array should have decreased by 1");
// Verify that the consumed code cannot be used again
assert(Totp::verifyAndConsumeRecoveryCode($hashed, $firstCode) === false, "Consumed code should not work twice");

// Verify another code with hyphen omitted
$secondCodeNoHyphen = str_replace('-', '', $codes[1]);
assert(Totp::verifyAndConsumeRecoveryCode($hashed, $secondCodeNoHyphen) === true, "Code without hyphen failed");
assert(count($hashed) === 6, "Hashed codes array should have decreased to 6");
echo "4. Recovery codes generation, verification, and single-use consumption: PASS\n";

// 5. Test otpauth URI formatting
$uri = Totp::getOtpAuthUri('admin', 'JBSWY3DPEHPK3PXP', 'My Docs');
assert(strpos($uri, 'otpauth://totp/My%20Docs:admin?') === 0, "URI prefix invalid: {$uri}");
assert(strpos($uri, 'secret=JBSWY3DPEHPK3PXP') !== false, "URI secret missing: {$uri}");
assert(strpos($uri, 'issuer=My%20Docs') !== false, "URI issuer missing: {$uri}");
echo "5. otpauth:// URI formatting: PASS\n";

echo "\nALL TOTP TESTS PASSED SUCCESSFULLY! 🎉\n";
