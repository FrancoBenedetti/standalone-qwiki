<?php
namespace Qwiki\Core;

/**
 * Pure PHP RFC 6238 (TOTP) and RFC 4226 (HOTP) implementation.
 * Zero external Composer dependencies.
 */
class Totp {
    const TIME_STEP = 30;
    const DIGITS = 6;
    private static $base32Alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a cryptographically secure random Base32 secret key.
     *
     * @param int $byteLength Number of random bytes (10 bytes = 16 Base32 characters)
     * @return string Base32 encoded secret key
     */
    public static function generateSecret(int $byteLength = 10): string {
        $randomBytes = random_bytes($byteLength);
        return self::encodeBase32($randomBytes);
    }

    /**
     * Encode binary string to Base32 string.
     */
    public static function encodeBase32(string $binary): string {
        if ($binary === '') {
            return '';
        }

        $chars = self::$base32Alphabet;
        $output = '';
        $buffer = 0;
        $bufferBits = 0;

        $length = strlen($binary);
        for ($i = 0; $i < $length; $i++) {
            $buffer = ($buffer << 8) | ord($binary[$i]);
            $bufferBits += 8;

            while ($bufferBits >= 5) {
                $bufferBits -= 5;
                $index = ($buffer >> $bufferBits) & 0x1F;
                $output .= $chars[$index];
            }
        }

        if ($bufferBits > 0) {
            $index = ($buffer << (5 - $bufferBits)) & 0x1F;
            $output .= $chars[$index];
        }

        return $output;
    }

    /**
     * Decode Base32 string to binary string.
     */
    public static function decodeBase32(string $base32): string {
        $base32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $base32));
        if ($base32 === '') {
            return '';
        }

        $chars = self::$base32Alphabet;
        $output = '';
        $buffer = 0;
        $bufferBits = 0;

        $length = strlen($base32);
        for ($i = 0; $i < $length; $i++) {
            $pos = strpos($chars, $base32[$i]);
            if ($pos === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $pos;
            $bufferBits += 5;

            if ($bufferBits >= 8) {
                $bufferBits -= 8;
                $output .= chr(($buffer >> $bufferBits) & 0xFF);
            }
        }

        return $output;
    }

    /**
     * Calculate 6-digit TOTP code for a given timestamp or time-slice.
     *
     * @param string $secret Base32 secret key
     * @param int|null $timeSlice Specific counter/time-slice, or null for current time
     * @return string 6-digit code
     */
    public static function getCode(string $secret, ?int $timeSlice = null): string {
        if ($timeSlice === null) {
            $timeSlice = (int)floor(time() / self::TIME_STEP);
        }

        $secretBin = self::decodeBase32($secret);
        if ($secretBin === '') {
            return '000000';
        }

        // Pack 64-bit integer into big-endian binary (RFC 4226)
        $timeBytes = pack('N*', 0) . pack('N*', $timeSlice);

        // HMAC-SHA1
        $hash = hash_hmac('sha1', $timeBytes, $secretBin, true);

        // Dynamic truncation
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = substr($hash, $offset, 4);
        $value = unpack('N', $truncated)[1] & 0x7FFFFFFF;

        $otp = $value % (10 ** self::DIGITS);
        return str_pad((string)$otp, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a submitted TOTP code against a secret with time-drift window.
     *
     * @param string $secret Base32 secret key
     * @param string $code 6-digit code submitted by user
     * @param int $discrepancy Allowed time-step drift before/after (default 1 = ±30s)
     * @param int|null $currentTime Optional fixed timestamp for testing
     * @return bool True if valid
     */
    public static function verifyCode(string $secret, string $code, int $discrepancy = 1, ?int $currentTime = null): bool {
        $code = trim($code);
        if (strlen($code) !== self::DIGITS || !ctype_digit($code)) {
            return false;
        }

        $now = $currentTime !== null ? $currentTime : time();
        $currentSlice = (int)floor($now / self::TIME_STEP);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $validCode = self::getCode($secret, $currentSlice + $i);
            if (hash_equals($validCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build standard otpauth:// URI for authenticator applications.
     */
    public static function getOtpAuthUri(string $username, string $secret, string $issuer = 'Standalone Qwiki'): string {
        $cleanUsername = rawurlencode($username);
        $cleanIssuer = rawurlencode($issuer);
        $cleanSecret = preg_replace('/[^A-Za-z2-7]/', '', strtoupper($secret));

        return "otpauth://totp/{$cleanIssuer}:{$cleanUsername}?secret={$cleanSecret}&issuer={$cleanIssuer}&algorithm=SHA1&digits=" . self::DIGITS . "&period=" . self::TIME_STEP;
    }

    /**
     * Generate 8 one-time backup recovery codes formatted as 'xxxx-xxxx'.
     *
     * @param int $count Number of codes to generate
     * @return array List of plain recovery codes
     */
    public static function generateRecoveryCodes(int $count = 8): array {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $bytes = random_bytes(4);
            $hex = bin2hex($bytes);
            $codes[] = substr($hex, 0, 4) . '-' . substr($hex, 4, 4);
        }
        return $codes;
    }

    /**
     * Hash plain recovery codes using password_hash() for safe flat-file storage.
     */
    public static function hashRecoveryCodes(array $plainCodes): array {
        $hashed = [];
        foreach ($plainCodes as $code) {
            $normalized = strtolower(str_replace('-', '', trim($code)));
            $hashed[] = password_hash($normalized, PASSWORD_DEFAULT);
        }
        return $hashed;
    }

    /**
     * Verify an entered recovery code against hashed codes list and consume it upon match.
     *
     * @param array &$hashedCodes Array of hashed codes (modified by reference on success)
     * @param string $inputCode The entered recovery code
     * @return bool True if valid and consumed
     */
    public static function verifyAndConsumeRecoveryCode(array &$hashedCodes, string $inputCode): bool {
        $normalized = strtolower(str_replace('-', '', trim($inputCode)));
        if (strlen($normalized) !== 8) {
            return false;
        }

        foreach ($hashedCodes as $index => $hash) {
            if (password_verify($normalized, $hash)) {
                // Consume the recovery code so it cannot be reused
                unset($hashedCodes[$index]);
                $hashedCodes = array_values($hashedCodes);
                return true;
            }
        }

        return false;
    }
}
