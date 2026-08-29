<?php
namespace Qwiki\Core;

class Auth {
    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            $defaultPath = session_save_path();
            if (empty($defaultPath) || !@is_writable($defaultPath)) {
                @session_save_path(sys_get_temp_dir());
            }
            session_start();
        }
    }

    public static function getCurrentUser() {
        self::startSession();
        return $_SESSION['qwiki_user'] ?? null;
    }

    public static function isAdmin() {
        self::startSession();
        $user = self::getCurrentUser();
        return (!empty($user) && $user['role'] === 'admin') || !empty($_SESSION['qwiki_admin']);
    }

    public static function isViewer() {
        self::startSession();
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

        // Fallback check against legacy admin password hash in config
        if (!$matchedUser && strtolower($username) === 'admin' && !empty($config['adminPasswordHash'])) {
            if (password_verify($password, $config['adminPasswordHash'])) {
                $_SESSION['qwiki_user'] = ['username' => 'admin', 'role' => 'admin'];
                $_SESSION['qwiki_admin'] = true;
                return ['success' => true, 'role' => 'admin', 'username' => 'admin'];
            }
        }

        if ($matchedUser && password_verify($password, $matchedUser['passwordHash'])) {
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

        return ['success' => false, 'error' => 'Invalid username or password'];
    }

    public static function logout() {
        self::startSession();
        unset($_SESSION['qwiki_user']);
        unset($_SESSION['qwiki_admin']);
        if (session_status() === PHP_SESSION_ACTIVE) {
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
