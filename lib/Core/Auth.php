<?php
namespace Qwiki\Core;

require_once __DIR__ . '/Totp.php';
require_once __DIR__ . '/Mailer.php';

class Auth {
    public static function getInstanceIdentifier() {
        $baseDir = Config::getBaseDir();
        return realpath($baseDir) ?: $baseDir;
    }

    public static function getBaseUrl(): string {
        if (php_sapi_name() === 'cli') {
            return 'http://localhost';
        }
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir = rtrim(dirname($scriptName === '/' || $scriptName === '\\' ? '' : $scriptName), '/\\');
        $webPath = preg_replace('#/(api|assets|content).*$#i', '', $scriptDir);
        return $scheme . '://' . $host . $webPath;
    }

    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            $instanceId = self::getInstanceIdentifier();
            $instanceHash = substr(hash('sha256', $instanceId), 0, 8);
            $sessionName = 'QWIKISESSID_' . $instanceHash;

            if (session_name() !== $sessionName) {
                @session_name($sessionName);
            }

            if (php_sapi_name() !== 'cli') {
                $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
                $scriptDir = rtrim(dirname($scriptName === '/' || $scriptName === '\\' ? '' : $scriptName), '/\\');
                $webPath = preg_replace('#/(api|assets|content).*$#i', '', $scriptDir);
                $cookiePath = !empty($webPath) ? $webPath . '/' : '/';

                $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

                @session_set_cookie_params([
                    'lifetime' => 0,
                    'path'     => $cookiePath,
                    'domain'   => '',
                    'secure'   => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }

            $defaultPath = session_save_path();
            if (empty($defaultPath) || !@is_writable($defaultPath)) {
                @session_save_path(sys_get_temp_dir());
            }
            session_start();
        }
    }

    public static function getCurrentUser() {
        self::startSession();
        $expectedInstance = self::getInstanceIdentifier();
        if (!empty($_SESSION['qwiki_instance']) && $_SESSION['qwiki_instance'] !== $expectedInstance) {
            return null;
        }
        return $_SESSION['qwiki_user'] ?? null;
    }

    public static function isAdmin() {
        self::startSession();
        $expectedInstance = self::getInstanceIdentifier();
        if (!empty($_SESSION['qwiki_instance']) && $_SESSION['qwiki_instance'] !== $expectedInstance) {
            return false;
        }
        $user = self::getCurrentUser();
        return (!empty($user) && $user['role'] === 'admin') || !empty($_SESSION['qwiki_admin']);
    }

    public static function isViewer() {
        self::startSession();
        $expectedInstance = self::getInstanceIdentifier();
        if (!empty($_SESSION['qwiki_instance']) && $_SESSION['qwiki_instance'] !== $expectedInstance) {
            return false;
        }
        return !empty($_SESSION['qwiki_user']);
    }

    public static function canView(array $config) {
        $requireLogin = !empty($config['requireLoginToView']);
        if (!$requireLogin) {
            return true;
        }
        return self::isViewer() || self::isAdmin();
    }

    public static function login($username, $password, array $config = []) {
        self::startSession();
        $username = trim($username);
        if (empty($username) || empty($password)) {
            return ['success' => false, 'error' => 'Username and password are required'];
        }

        $userData = Config::loadUsers();
        $matchedUser = null;
        if (!empty($userData['users'])) {
            foreach ($userData['users'] as $u) {
                if (strtolower($u['username']) === strtolower($username)) {
                    $matchedUser = $u;
                    break;
                }
            }
        }

        $authenticated = false;

        if ($matchedUser && password_verify($password, $matchedUser['passwordHash'])) {
            $authenticated = true;
        } elseif (strtolower($username) === 'admin' && !empty($config['adminPasswordHash'])) {
            // Fallback & self-healing check against adminPasswordHash in config
            if (password_verify($password, $config['adminPasswordHash'])) {
                if ($matchedUser) {
                    foreach ($userData['users'] as &$u) {
                        if (strtolower($u['username']) === 'admin') {
                            $u['passwordHash'] = $config['adminPasswordHash'];
                        }
                    }
                    unset($u);
                    Config::saveUsers($userData);
                } else {
                    $matchedUser = [
                        'username' => 'admin',
                        'role' => 'admin',
                        'passwordHash' => $config['adminPasswordHash'],
                        'twoFactor' => null
                    ];
                }
                $authenticated = true;
            }
        }

        if (!$authenticated || !$matchedUser) {
            return ['success' => false, 'error' => 'Invalid username or password'];
        }

        $policy = $config['twoFactorPolicy'] ?? 'optional';
        $has2fa = !empty($matchedUser['twoFactor']['enabled']) && !empty($matchedUser['twoFactor']['secret']);

        // Check if 2FA verification is required
        if ($policy !== 'disabled' && $has2fa) {
            $_SESSION['qwiki_2fa_pending'] = [
                'username' => $matchedUser['username'],
                'role'     => $matchedUser['role'],
                'expires'  => time() + 300 // 5 minutes
            ];
            return [
                'success'    => true,
                'require2fa' => true,
                'username'   => $matchedUser['username']
            ];
        }

        // Check if wiki policy forces 2FA enrollment before allowing access
        $mustEnroll = false;
        if ($policy === 'required_all') {
            $mustEnroll = true;
        } elseif ($policy === 'required_admins' && $matchedUser['role'] === 'admin') {
            $mustEnroll = true;
        }

        if ($mustEnroll && !$has2fa) {
            $_SESSION['qwiki_2fa_pending'] = [
                'username' => $matchedUser['username'],
                'role'     => $matchedUser['role'],
                'setup'    => true,
                'expires'  => time() + 600 // 10 minutes to setup
            ];
            return [
                'success'          => true,
                'require2fa_setup' => true,
                'username'         => $matchedUser['username']
            ];
        }

        // Establish full session
        return self::establishSession($matchedUser['username'], $matchedUser['role']);
    }

    private static function establishSession(string $username, string $role): array {
        self::startSession();
        $_SESSION['qwiki_instance'] = self::getInstanceIdentifier();
        $_SESSION['qwiki_user'] = [
            'username' => $username,
            'role'     => $role
        ];
        if ($role === 'admin') {
            $_SESSION['qwiki_admin'] = true;
        } else {
            unset($_SESSION['qwiki_admin']);
        }
        unset($_SESSION['qwiki_2fa_pending']);
        unset($_SESSION['qwiki_2fa_enrolling']);

        return ['success' => true, 'role' => $role, 'username' => $username];
    }

    /**
     * Complete 2FA login by verifying submitted 6-digit TOTP code or recovery code.
     */
    public static function verify2faLogin(string $codeOrRecovery): array {
        self::startSession();
        $pending = $_SESSION['qwiki_2fa_pending'] ?? null;
        if (empty($pending) || empty($pending['username']) || empty($pending['expires']) || time() > $pending['expires']) {
            unset($_SESSION['qwiki_2fa_pending']);
            return ['success' => false, 'error' => 'Authentication session expired. Please log in again.'];
        }

        $codeOrRecovery = trim($codeOrRecovery);
        if (empty($codeOrRecovery)) {
            return ['success' => false, 'error' => 'Authentication code is required.'];
        }

        $userData = Config::loadUsers();
        $targetUser = null;
        $userIndex = null;
        foreach ($userData['users'] as $idx => $u) {
            if (strtolower($u['username']) === strtolower($pending['username'])) {
                $targetUser = $u;
                $userIndex = $idx;
                break;
            }
        }

        if (!$targetUser || empty($targetUser['twoFactor']['secret'])) {
            return ['success' => false, 'error' => '2FA configuration not found for user.'];
        }

        $verified = false;
        // 1. Try TOTP code if numeric 6-digit
        if (strlen($codeOrRecovery) === 6 && ctype_digit($codeOrRecovery)) {
            $verified = Totp::verifyCode($targetUser['twoFactor']['secret'], $codeOrRecovery);
        }

        // 2. Try recovery code if TOTP didn't match
        if (!$verified && !empty($targetUser['twoFactor']['recoveryCodes'])) {
            $hashedCodes = $targetUser['twoFactor']['recoveryCodes'];
            if (Totp::verifyAndConsumeRecoveryCode($hashedCodes, $codeOrRecovery)) {
                $verified = true;
                // Persist consumed recovery codes to flat file
                $userData['users'][$userIndex]['twoFactor']['recoveryCodes'] = $hashedCodes;
                Config::saveUsers($userData);
            }
        }

        if (!$verified) {
            return ['success' => false, 'error' => 'Invalid authentication code or recovery code.'];
        }

        return self::establishSession($targetUser['username'], $targetUser['role']);
    }

    /**
     * Start 2FA enrollment process for the current authenticated or pending user.
     */
    public static function initiate2faSetup(?string $targetUsername = null): array {
        self::startSession();
        $currentUser = self::getCurrentUser();
        $pending = $_SESSION['qwiki_2fa_pending'] ?? null;

        $username = null;
        if ($currentUser) {
            $username = $currentUser['username'];
        } elseif ($pending && !empty($pending['setup']) && time() <= $pending['expires']) {
            $username = $pending['username'];
        }

        if (empty($username)) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }

        $config = Config::load();
        $wikiTitle = $config['title'] ?? 'Standalone Qwiki';

        $secret = Totp::generateSecret(10); // 16-char Base32
        $recoveryCodes = Totp::generateRecoveryCodes(8);
        $otpUri = Totp::getOtpAuthUri($username, $secret, $wikiTitle);

        $_SESSION['qwiki_2fa_enrolling'] = [
            'username'      => $username,
            'secret'        => $secret,
            'recoveryCodes' => $recoveryCodes,
            'expires'       => time() + 900 // 15 min setup window
        ];

        return [
            'success'       => true,
            'secret'        => $secret,
            'otpUri'        => $otpUri,
            'recoveryCodes' => $recoveryCodes
        ];
    }

    /**
     * Confirm and finalize 2FA enrollment with a test 6-digit TOTP code.
     */
    public static function confirm2faSetup(string $code): array {
        self::startSession();
        $enrolling = $_SESSION['qwiki_2fa_enrolling'] ?? null;
        if (empty($enrolling) || empty($enrolling['username']) || time() > $enrolling['expires']) {
            return ['success' => false, 'error' => 'Enrollment session expired. Please restart setup.'];
        }

        $code = trim($code);
        if (!Totp::verifyCode($enrolling['secret'], $code)) {
            return ['success' => false, 'error' => 'Invalid confirmation code. Please check your authenticator app clock and try again.'];
        }

        $userData = Config::loadUsers();
        $found = false;
        $matchedRole = 'viewer';
        foreach ($userData['users'] as &$u) {
            if (strtolower($u['username']) === strtolower($enrolling['username'])) {
                $u['twoFactor'] = [
                    'enabled'       => true,
                    'secret'        => $enrolling['secret'],
                    'recoveryCodes' => Totp::hashRecoveryCodes($enrolling['recoveryCodes']),
                    'enabledAt'     => date('Y-m-d H:i:s')
                ];
                $matchedRole = $u['role'];
                $found = true;
                break;
            }
        }
        unset($u);

        if (!$found || !Config::saveUsers($userData)) {
            return ['success' => false, 'error' => 'Failed to save 2FA configuration.'];
        }

        $username = $enrolling['username'];
        unset($_SESSION['qwiki_2fa_enrolling']);

        // If user was completing a mandatory setup during login, establish session now
        if (!self::getCurrentUser()) {
            return self::establishSession($username, $matchedRole);
        }

        return ['success' => true];
    }

    /**
     * Disable 2FA for a user (admin can disable for anyone; user can disable for themselves).
     */
    public static function disable2fa(string $targetUsername): array {
        self::startSession();
        $currentUser = self::getCurrentUser();
        $isAdmin = self::isAdmin();

        if (!$isAdmin && (!$currentUser || strtolower($currentUser['username']) !== strtolower($targetUsername))) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }

        $userData = Config::loadUsers();
        $found = false;
        foreach ($userData['users'] as &$u) {
            if (strtolower($u['username']) === strtolower($targetUsername)) {
                $u['twoFactor'] = null;
                $found = true;
                break;
            }
        }
        unset($u);

        if (!$found) {
            return ['success' => false, 'error' => 'User not found'];
        }

        if (Config::saveUsers($userData)) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Failed to save changes'];
    }

    // --- Stateless Cryptographic Token Methods ---

    public static function generateStatelessToken(string $username, string $type, int $expiresInSeconds, string $extraSecret = ''): string {
        $payload = [
            'u'   => $username,
            't'   => $type,
            'exp' => time() + $expiresInSeconds
        ];
        $payloadB64 = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $salt = hash('sha256', self::getInstanceIdentifier() . '_qwiki_auth_salt');
        $sig = hash_hmac('sha256', $payloadB64 . ':' . $extraSecret, $salt);
        return $payloadB64 . '.' . $sig;
    }

    public static function parseTokenPayload(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }
        $payloadJson = base64_decode(strtr($parts[0], '-_', '+/'));
        $payload = json_decode($payloadJson, true);
        return is_array($payload) ? $payload : null;
    }

    public static function verifyStatelessToken(string $token, string $expectedType, string $extraSecret = ''): ?string {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }
        list($payloadB64, $sig) = $parts;
        $salt = hash('sha256', self::getInstanceIdentifier() . '_qwiki_auth_salt');
        $expectedSig = hash_hmac('sha256', $payloadB64 . ':' . $extraSecret, $salt);
        if (!hash_equals($expectedSig, $sig)) {
            return null;
        }
        $payload = self::parseTokenPayload($token);
        if (!$payload || ($payload['t'] ?? '') !== $expectedType || empty($payload['u']) || ($payload['exp'] ?? 0) < time()) {
            return null;
        }
        return $payload['u'];
    }

    // --- Self-Service Password Reset ---

    public static function requestPasswordReset(string $identifier): array {
        $identifier = trim($identifier);
        $genericResponse = [
            'success' => true,
            'message' => 'If an account with that verified email or username exists, a password reset link has been dispatched.'
        ];

        if (empty($identifier)) {
            return $genericResponse;
        }

        $userData = Config::loadUsers();
        $targetUser = null;
        foreach ($userData['users'] as $u) {
            if (strtolower($u['username']) === strtolower($identifier) ||
                (!empty($u['email']) && strtolower($u['email']) === strtolower($identifier))) {
                $targetUser = $u;
                break;
            }
        }

        // Only dispatch if user exists, has an email, and email is verified
        if ($targetUser && !empty($targetUser['email']) && !empty($targetUser['emailVerified'])) {
            $extra = substr($targetUser['passwordHash'], 0, 16);
            $token = self::generateStatelessToken($targetUser['username'], 'reset', 3600, $extra);
            $resetUrl = self::getBaseUrl() . '/index.php?action=reset_password&token=' . urlencode($token);

            $subject = "Standalone Qwiki: Password Reset Request";
            $body = "Hello {$targetUser['username']},\n\n"
                  . "We received a request to reset the password for your account on Standalone Qwiki.\n\n"
                  . "Click the link below to set a new password (valid for 1 hour):\n"
                  . "{$resetUrl}\n\n"
                  . "If you did not request this password reset, you can safely ignore this email.\n\n"
                  . "Best regards,\nStandalone Qwiki";

            Mailer::send($targetUser['email'], $subject, $body);
        }

        return $genericResponse;
    }

    public static function validateResetToken(string $token): array {
        $payload = self::parseTokenPayload($token);
        if (!$payload || ($payload['t'] ?? '') !== 'reset' || empty($payload['u']) || ($payload['exp'] ?? 0) < time()) {
            return ['valid' => false, 'error' => 'Reset link is invalid or has expired.'];
        }

        $userData = Config::loadUsers();
        $targetUser = null;
        foreach ($userData['users'] as $u) {
            if (strtolower($u['username']) === strtolower($payload['u'])) {
                $targetUser = $u;
                break;
            }
        }

        if (!$targetUser) {
            return ['valid' => false, 'error' => 'Associated user account no longer exists.'];
        }

        $parts = explode('.', $token);
        $payloadB64 = $parts[0];
        $sig = $parts[1] ?? '';
        $extra = substr($targetUser['passwordHash'], 0, 16);
        $salt = hash('sha256', self::getInstanceIdentifier() . '_qwiki_auth_salt');
        $expectedSig = hash_hmac('sha256', $payloadB64 . ':' . $extra, $salt);

        if (!hash_equals($expectedSig, $sig)) {
            return ['valid' => false, 'error' => 'Reset link has already been used or is invalid.'];
        }

        return ['valid' => true, 'username' => $targetUser['username']];
    }

    public static function resetPasswordWithToken(string $token, string $newPassword): array {
        $validation = self::validateResetToken($token);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }

        $newPassword = trim($newPassword);
        if (strlen($newPassword) < 4) {
            return ['success' => false, 'error' => 'Password must be at least 4 characters long.'];
        }

        $username = $validation['username'];
        $userData = Config::loadUsers();
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $found = false;

        foreach ($userData['users'] as &$u) {
            if (strtolower($u['username']) === strtolower($username)) {
                $u['passwordHash'] = $newHash;
                $found = true;
                break;
            }
        }
        unset($u);

        if (!$found) {
            return ['success' => false, 'error' => 'User not found.'];
        }

        Config::saveUsers($userData);

        if (strtolower($username) === 'admin') {
            $config = Config::load();
            $config['adminPasswordHash'] = $newHash;
            Config::save($config);
        }

        return ['success' => true];
    }

    public static function generateAdminResetLink(string $username): array {
        if (!self::isAdmin()) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }

        $username = trim($username);
        $userData = Config::loadUsers();
        $targetUser = null;
        foreach ($userData['users'] as $u) {
            if (strtolower($u['username']) === strtolower($username)) {
                $targetUser = $u;
                break;
            }
        }

        if (!$targetUser) {
            return ['success' => false, 'error' => 'User not found'];
        }

        // 24-hour validity for admin-generated link
        $extra = substr($targetUser['passwordHash'], 0, 16);
        $token = self::generateStatelessToken($targetUser['username'], 'reset', 86400, $extra);
        $resetUrl = self::getBaseUrl() . '/index.php?action=reset_password&token=' . urlencode($token);

        return [
            'success'   => true,
            'resetUrl'  => $resetUrl,
            'expiresIn' => '24 hours'
        ];
    }

    // --- User Email & Verification ---

    public static function setUserEmail(string $targetUsername, string $email, bool $markVerified = false): array {
        self::startSession();
        $currentUser = self::getCurrentUser();
        $isAdmin = self::isAdmin();

        if (!$isAdmin && (!$currentUser || strtolower($currentUser['username']) !== strtolower($targetUsername))) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }

        $email = trim($email);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email address format.'];
        }

        $userData = Config::loadUsers();
        // Check uniqueness if email is non-empty
        if ($email !== '') {
            foreach ($userData['users'] as $u) {
                if (strtolower($u['username']) !== strtolower($targetUsername) &&
                    !empty($u['email']) && strtolower($u['email']) === strtolower($email)) {
                    return ['success' => false, 'error' => 'This email address is already in use by another account.'];
                }
            }
        }

        $found = false;
        foreach ($userData['users'] as &$u) {
            if (strtolower($u['username']) === strtolower($targetUsername)) {
                $u['email'] = $email !== '' ? $email : null;
                $u['emailVerified'] = ($email !== '') ? $markVerified : false;
                $u['emailVerifiedAt'] = ($email !== '' && $markVerified) ? date('Y-m-d H:i:s') : null;
                $found = true;
                break;
            }
        }
        unset($u);

        if (!$found) {
            return ['success' => false, 'error' => 'User not found'];
        }

        Config::saveUsers($userData);

        if ($email !== '' && !$markVerified) {
            self::sendEmailVerification($targetUsername);
        }

        return ['success' => true, 'emailVerified' => $markVerified];
    }

    public static function sendEmailVerification(string $username): array {
        $userData = Config::loadUsers();
        $targetUser = null;
        foreach ($userData['users'] as $u) {
            if (strtolower($u['username']) === strtolower($username)) {
                $targetUser = $u;
                break;
            }
        }

        if (!$targetUser || empty($targetUser['email'])) {
            return ['success' => false, 'error' => 'User or email address not found.'];
        }

        $extra = substr($targetUser['passwordHash'], 0, 16) . ':' . $targetUser['email'];
        $token = self::generateStatelessToken($targetUser['username'], 'verify_email', 86400, $extra);
        $verifyUrl = self::getBaseUrl() . '/index.php?action=verify_email&token=' . urlencode($token);

        $subject = "Standalone Qwiki: Verify Your Email Address";
        $body = "Hello {$targetUser['username']},\n\n"
              . "Please verify your email address for Standalone Qwiki by clicking the link below:\n"
              . "{$verifyUrl}\n\n"
              . "Best regards,\nStandalone Qwiki";

        return Mailer::send($targetUser['email'], $subject, $body);
    }

    public static function verifyEmail(string $token): array {
        $payload = self::parseTokenPayload($token);
        if (!$payload || ($payload['t'] ?? '') !== 'verify_email' || empty($payload['u']) || ($payload['exp'] ?? 0) < time()) {
            return ['success' => false, 'error' => 'Verification link is invalid or has expired.'];
        }

        $userData = Config::loadUsers();
        $found = false;
        $userIndex = null;
        foreach ($userData['users'] as $idx => $u) {
            if (strtolower($u['username']) === strtolower($payload['u'])) {
                $found = true;
                $userIndex = $idx;
                break;
            }
        }

        if (!$found) {
            return ['success' => false, 'error' => 'Associated user account no longer exists.'];
        }

        $targetUser = $userData['users'][$userIndex];
        if (empty($targetUser['email'])) {
            return ['success' => false, 'error' => 'No email address on file to verify.'];
        }

        $parts = explode('.', $token);
        $payloadB64 = $parts[0];
        $sig = $parts[1] ?? '';
        $extra = substr($targetUser['passwordHash'], 0, 16) . ':' . $targetUser['email'];
        $salt = hash('sha256', self::getInstanceIdentifier() . '_qwiki_auth_salt');
        $expectedSig = hash_hmac('sha256', $payloadB64 . ':' . $extra, $salt);

        if (!hash_equals($expectedSig, $sig)) {
            return ['success' => false, 'error' => 'Verification link is invalid.'];
        }

        $userData['users'][$userIndex]['emailVerified'] = true;
        $userData['users'][$userIndex]['emailVerifiedAt'] = date('Y-m-d H:i:s');
        Config::saveUsers($userData);

        return ['success' => true, 'username' => $targetUser['username'], 'email' => $targetUser['email']];
    }

    // --- Standard User Administration ---

    public static function updateUserPassword($username, $newPassword) {
        $currentUser = self::getCurrentUser();
        $isAdmin = self::isAdmin();

        if (!$isAdmin && (!$currentUser || strtolower($currentUser['username']) !== strtolower($username))) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }

        $username = trim($username);
        $newPassword = trim($newPassword);

        if (empty($username) || empty($newPassword)) {
            return ['success' => false, 'error' => 'Username and new password are required'];
        }

        if (strlen($newPassword) < 4) {
            return ['success' => false, 'error' => 'Password must be at least 4 characters'];
        }

        $userData = Config::loadUsers();
        $found = false;
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

        if (!empty($userData['users'])) {
            foreach ($userData['users'] as &$u) {
                if (strtolower($u['username']) === strtolower($username)) {
                    $u['passwordHash'] = $newHash;
                    $found = true;
                    break;
                }
            }
            unset($u);
        }

        if (!$found) {
            return ['success' => false, 'error' => 'User not found'];
        }

        Config::saveUsers($userData);

        if (strtolower($username) === 'admin') {
            $config = Config::load();
            $config['adminPasswordHash'] = $newHash;
            Config::save($config);
        }

        return ['success' => true];
    }

    public static function logout() {
        self::startSession();
        unset($_SESSION['qwiki_user']);
        unset($_SESSION['qwiki_admin']);
        unset($_SESSION['qwiki_instance']);
        unset($_SESSION['qwiki_2fa_pending']);
        unset($_SESSION['qwiki_2fa_enrolling']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get("session.use_cookies") && php_sapi_name() !== 'cli' && !headers_sent()) {
                $params = session_get_cookie_params();
                @setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
        }
        return ['success' => true];
    }

    public static function listUsers() {
        if (!self::isAdmin()) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        $userData = Config::loadUsers();
        $safeList = [];
        if (!empty($userData['users'])) {
            foreach ($userData['users'] as $u) {
                $has2fa = !empty($u['twoFactor']['enabled']) && !empty($u['twoFactor']['secret']);
                $safeList[] = [
                    'username'      => $u['username'],
                    'role'          => $u['role'],
                    'email'         => $u['email'] ?? null,
                    'emailVerified' => !empty($u['emailVerified']),
                    'has2fa'        => $has2fa,
                    'createdAt'     => $u['createdAt'] ?? ''
                ];
            }
        }
        return ['success' => true, 'users' => $safeList];
    }

    public static function addUser($username, $password, $role = 'viewer', $email = null) {
        if (!self::isAdmin()) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        $username = trim($username);
        $role = in_array($role, ['admin', 'viewer']) ? $role : 'viewer';
        $email = !empty($email) ? trim($email) : null;

        if (empty($username) || empty($password)) {
            return ['success' => false, 'error' => 'Username and password are required'];
        }

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email address format.'];
        }

        $userData = Config::loadUsers();
        foreach ($userData['users'] as $u) {
            if (strtolower($u['username']) === strtolower($username)) {
                return ['success' => false, 'error' => 'User with this username already exists'];
            }
            if ($email !== null && !empty($u['email']) && strtolower($u['email']) === strtolower($email)) {
                return ['success' => false, 'error' => 'Another user already has this email address'];
            }
        }

        $userData['users'][] = [
            'username'      => $username,
            'role'          => $role,
            'email'         => $email,
            'emailVerified' => ($email !== null), // Admin-created email is verified by default
            'emailVerifiedAt' => ($email !== null) ? date('Y-m-d H:i:s') : null,
            'twoFactor'     => null,
            'passwordHash'  => password_hash($password, PASSWORD_DEFAULT),
            'createdAt'     => date('Y-m-d H:i:s')
        ];

        if (Config::saveUsers($userData)) {
            return ['success' => true];
        }
        return ['success' => false, 'error' => 'Failed to save user'];
    }

    public static function deleteUser($username) {
        if (!self::isAdmin()) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        $username = trim($username);
        $currentUser = self::getCurrentUser();
        if ($currentUser && strtolower($currentUser['username']) === strtolower($username)) {
            return ['success' => false, 'error' => 'You cannot delete your own active account'];
        }

        $userData = Config::loadUsers();
        $filtered = [];
        $found = false;
        foreach ($userData['users'] as $u) {
            if (strtolower($u['username']) === strtolower($username)) {
                $found = true;
                continue;
            }
            $filtered[] = $u;
        }

        if (!$found) {
            return ['success' => false, 'error' => 'User not found'];
        }

        $adminCount = 0;
        foreach ($filtered as $u) {
            if ($u['role'] === 'admin') $adminCount++;
        }
        if ($adminCount === 0) {
            return ['success' => false, 'error' => 'Cannot delete the last remaining admin user'];
        }

        $userData['users'] = $filtered;
        if (Config::saveUsers($userData)) {
            return ['success' => true];
        }
        return ['success' => false, 'error' => 'Failed to save changes'];
    }

    public static function getUser(string $username): ?array {
        $username = trim($username);
        if (empty($username)) {
            return null;
        }
        $userData = Config::loadUsers();
        foreach ($userData['users'] ?? [] as $u) {
            if (strtolower($u['username'] ?? '') === strtolower($username)) {
                return $u;
            }
        }
        return null;
    }

    public static function updateUserGeminiSettings(string $username, ?string $apiKey, ?string $model = null): bool {
        $username = trim($username);
        if (empty($username)) {
            return false;
        }
        $userData = Config::loadUsers();
        $found = false;
        foreach ($userData['users'] as &$u) {
            if (strtolower($u['username'] ?? '') === strtolower($username)) {
                if ($apiKey !== null) {
                    $trimmedKey = trim($apiKey);
                    if ($trimmedKey === '') {
                        unset($u['geminiApiKey']);
                    } elseif (strpos($trimmedKey, '••••') === false) {
                        $u['geminiApiKey'] = $trimmedKey;
                    }
                }
                if ($model !== null) {
                    $trimmedModel = trim($model);
                    if ($trimmedModel === '') {
                        unset($u['geminiModel']);
                    } else {
                        $u['geminiModel'] = $trimmedModel;
                    }
                }
                $found = true;
                break;
            }
        }
        unset($u);

        // If user is admin and not in users.json yet (fallback admin), create record
        if (!$found && strtolower($username) === 'admin') {
            $newRecord = [
                'username' => 'admin',
                'role' => 'admin',
                'passwordHash' => '',
                'createdAt' => date('Y-m-d H:i:s')
            ];
            if ($apiKey !== null && trim($apiKey) !== '' && strpos($apiKey, '••••') === false) {
                $newRecord['geminiApiKey'] = trim($apiKey);
            }
            if ($model !== null && trim($model) !== '') {
                $newRecord['geminiModel'] = trim($model);
            }
            $userData['users'][] = $newRecord;
            $found = true;
        }

        if ($found) {
            return Config::saveUsers($userData);
        }
        return false;
    }
}

