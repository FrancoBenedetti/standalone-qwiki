<?php
namespace Qwiki\Core;

class Config {
    private static $baseDir = null;
    private static $configFile = null;
    private static $usersFile = null;

    public static function init($baseDir = null) {
        if ($baseDir === null) {
            $baseDir = dirname(dirname(__DIR__));
        }
        self::$baseDir = rtrim($baseDir, '/\\');
        self::$configFile = self::$baseDir . '/qwiki.json';
        self::$usersFile = self::$baseDir . '/users.json';
    }

    public static function getBaseDir() {
        if (self::$baseDir === null) {
            self::init();
        }
        return self::$baseDir;
    }

    public static function getConfigFile() {
        if (self::$configFile === null) {
            self::init();
        }
        return self::$configFile;
    }

    public static function getUsersFile() {
        if (self::$usersFile === null) {
            self::init();
        }
        return self::$usersFile;
    }

    public static function ensureSetup() {
        $configFile = self::getConfigFile();
        if (file_exists($configFile)) {
            return true;
        }

        $baseDir = self::getBaseDir();
        $demoDir = $baseDir . '/demo-data';

        if (file_exists($demoDir . '/qwiki-default.json')) {
            if (!is_dir($baseDir . '/uploads')) {
                @mkdir($baseDir . '/uploads', 0755, true);
            }
            if (!is_dir($baseDir . '/content')) {
                @mkdir($baseDir . '/content', 0755, true);
            }

            self::copyDir($demoDir . '/content', $baseDir . '/content');

            $copied = @copy($demoDir . '/qwiki-default.json', $configFile);
            if (!$copied) {
                return false;
            }

            if (file_exists($demoDir . '/htaccess-uploads') && is_dir($baseDir . '/uploads')) {
                @copy($demoDir . '/htaccess-uploads', $baseDir . '/uploads/.htaccess');
            }
            return true;
        }
        return false;
    }

    public static function load() {
        $configFile = self::getConfigFile();
        if (!file_exists($configFile)) {
            self::ensureSetup();
        }
        if (!file_exists($configFile)) {
            return ['title' => 'Standalone Qwiki', 'books' => []];
        }
        $data = json_decode(file_get_contents($configFile), true);
        return is_array($data) ? $data : ['title' => 'Standalone Qwiki', 'books' => []];
    }

    public static function save(array $config) {
        $configFile = self::getConfigFile();
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return file_put_contents($configFile, $json, LOCK_EX) !== false;
    }

    public static function loadUsers() {
        $usersFile = self::getUsersFile();
        if (!file_exists($usersFile)) {
            $initialUsers = [
                'users' => [
                    [
                        'username' => 'admin',
                        'role' => 'admin',
                        'passwordHash' => '$2y$10$H8vIUts/BIGCXGCmw9xFHuCBnPGgNHZ44F59OcQYYxDVKBmD19DIm',
                        'createdAt' => date('Y-m-d H:i:s')
                    ]
                ]
            ];
            file_put_contents($usersFile, json_encode($initialUsers, JSON_PRETTY_PRINT), LOCK_EX);
            return $initialUsers;
        }
        $data = json_decode(file_get_contents($usersFile), true);
        return is_array($data) ? $data : ['users' => []];
    }

    public static function saveUsers(array $userData) {
        $usersFile = self::getUsersFile();
        $json = json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return file_put_contents($usersFile, $json, LOCK_EX) !== false;
    }

    public static function makeSlug($text) {
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    public static function safePath($baseDir, $subPath) {
        $realBase = realpath($baseDir);
        if ($realBase === false) return false;
        
        $combined = $realBase . '/' . ltrim($subPath, '/\\');
        $realTarget = realpath($combined);
        
        // If file doesn't exist yet, check directory part
        if ($realTarget === false) {
            $dirPart = dirname($combined);
            $realDir = realpath($dirPart);
            if ($realDir === false || strpos($realDir, $realBase) !== 0) {
                return false;
            }
            return $combined;
        }

        if (strpos($realTarget, $realBase) !== 0) {
            return false;
        }
        return $realTarget;
    }

    private static function copyDir($src, $dst) {
        if (!is_dir($src)) return;
        @mkdir($dst, 0755, true);
        $dir = opendir($src);
        while (false !== ($file = readdir($dir))) {
            if ($file !== '.' && $file !== '..') {
                if (is_dir($src . '/' . $file)) {
                    self::copyDir($src . '/' . $file, $dst . '/' . $file);
                } else {
                    @copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }
}
