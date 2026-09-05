<?php
namespace Qwiki\Core;

class Auth {
    public static function getInstanceIdentifier() {
        $baseDir = Config::getBaseDir();
        return realpath($baseDir) ?: $baseDir;
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

        if ($matchedUser && password_verify($password, $matchedUser['passwordHash'])) {
            $_SESSION['qwiki_instance'] = self::getInstanceIdentifier();
            $_SESSION['qwiki_user'] = [
                'username' => $matchedUser['username'],
                'role' => $matchedUser['role']
            ];
            if ($matchedUser['role'] === 'admin') {
                $_SESSION['qwiki_admin'] = true;
            } else {
                unset($_SESSION['qwiki_admin']);
            }
            return ['success' => true, 'role' => $matchedUser['role'], 'username' => $matchedUser['username']];
        }

        // Fallback & self-healing check against adminPasswordHash in config
        if (strtolower($username) === 'admin' && !empty($config['adminPasswordHash'])) {
            if (password_verify($password, $config['adminPasswordHash'])) {
                if ($matchedUser) {
                    foreach ($userData['users'] as &$u) {
                        if (strtolower($u['username']) === 'admin') {
                            $u['passwordHash'] = $config['adminPasswordHash'];
                        }
                    }
                    unset($u);
                    Config::saveUsers($userData);
                }
                $_SESSION['qwiki_instance'] = self::getInstanceIdentifier();
                $_SESSION['qwiki_user'] = ['username' => 'admin', 'role' => 'admin'];
                $_SESSION['qwiki_admin'] = true;
                return ['success' => true, 'role' => 'admin', 'username' => 'admin'];
            }
        }

        return ['success' => false, 'error' => 'Invalid username or password'];
    }

    public static function updateUserPassword($username, $newPassword) {
        if (!self::isAdmin()) {
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
                $safeList[] = [
                    'username' => $u['username'],
                    'role' => $u['role'],
                    'createdAt' => $u['createdAt'] ?? ''
                ];
            }
        }
        return ['success' => true, 'users' => $safeList];
    }

    public static function addUser($username, $password, $role = 'viewer') {
        if (!self::isAdmin()) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }
        $username = trim($username);
        $role = in_array($role, ['admin', 'viewer']) ? $role : 'viewer';

        if (empty($username) || empty($password)) {
            return ['success' => false, 'error' => 'Username and password are required'];
        }

        $userData = Config::loadUsers();
        foreach ($userData['users'] as $u) {
            if (strtolower($u['username']) === strtolower($username)) {
                return ['success' => false, 'error' => 'User with this username already exists'];
            }
        }

        $userData['users'][] = [
            'username' => $username,
            'role' => $role,
            'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
            'createdAt' => date('Y-m-d H:i:s')
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
}
