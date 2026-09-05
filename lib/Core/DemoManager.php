<?php
namespace Qwiki\Core;

class DemoManager {
    /**
     * Check if demo-data templates are present
     */
    public static function isDemoConfigured(): bool {
        $baseDir = Config::getBaseDir();
        $demoDir = $baseDir . '/demo-data';
        return file_exists($demoDir . '/qwiki-default.json')
            && is_dir($demoDir . '/content')
            && file_exists($demoDir . '/users-default.json');
    }

    /**
     * Perform full reload of demo data
     *
     * @return array [success => bool, message => string, error => string|null, timestamp => int]
     */
    public static function reload(): array {
        $baseDir = Config::getBaseDir();
        $demoDir = $baseDir . '/demo-data';

        if (!self::isDemoConfigured()) {
            return [
                'success' => false,
                'error' => 'Demo template files are missing in demo-data directory.',
                'timestamp' => time()
            ];
        }

        $contentDir = $baseDir . '/content';
        $uploadsDir = $baseDir . '/uploads';
        $configFile = Config::getConfigFile();
        $usersFile  = Config::getUsersFile();

        // 1. Clear Active Document Locks
        $locksDir = $contentDir . '/.locks';
        if (is_dir($locksDir)) {
            self::deleteDirectory($locksDir);
        }

        // 2. Wipe Content and Restore from demo-data/content
        if (is_dir($contentDir)) {
            self::deleteDirContents($contentDir);
        } else {
            @mkdir($contentDir, 0755, true);
        }
        self::copyDirectory($demoDir . '/content', $contentDir);

        // 3. Reset qwiki.json configuration
        $configCopied = @copy($demoDir . '/qwiki-default.json', $configFile);
        if (!$configCopied) {
            return [
                'success' => false,
                'error' => 'Failed to reset qwiki.json configuration from demo-data.',
                'timestamp' => time()
            ];
        }

        // 4. Reset users.json user accounts
        $usersCopied = @copy($demoDir . '/users-default.json', $usersFile);
        if (!$usersCopied) {
            return [
                'success' => false,
                'error' => 'Failed to reset users.json user accounts from demo-data.',
                'timestamp' => time()
            ];
        }

        // 5. Clean Uploads and Restore .htaccess
        if (!is_dir($uploadsDir)) {
            @mkdir($uploadsDir, 0755, true);
        } else {
            self::deleteDirContents($uploadsDir);
        }

        if (is_dir($demoDir . '/uploads')) {
            self::copyDirectory($demoDir . '/uploads', $uploadsDir);
        }

        if (file_exists($demoDir . '/htaccess-uploads')) {
            @copy($demoDir . '/htaccess-uploads', $uploadsDir . '/.htaccess');
        }

        return [
            'success' => true,
            'message' => 'Demo package reloaded successfully to fresh state.',
            'timestamp' => time()
        ];
    }

    /**
     * Recursively delete directory and all contents
     */
    public static function deleteDirectory(string $dir): bool {
        if (!is_dir($dir)) {
            return false;
        }
        self::deleteDirContents($dir);
        return @rmdir($dir);
    }

    /**
     * Delete all contents inside a directory without removing the directory itself
     */
    public static function deleteDirContents(string $dir): bool {
        if (!is_dir($dir)) {
            return false;
        }
        $items = scandir($dir);
        if ($items === false) {
            return false;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
        return true;
    }

    /**
     * Recursively copy a directory and its contents
     */
    public static function copyDirectory(string $src, string $dst): bool {
        if (!is_dir($src)) {
            return false;
        }
        if (!is_dir($dst)) {
            @mkdir($dst, 0755, true);
        }
        $dir = opendir($src);
        if (!$dir) {
            return false;
        }
        while (false !== ($file = readdir($dir))) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;
            if (is_dir($srcPath)) {
                self::copyDirectory($srcPath, $dstPath);
            } else {
                @copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
        return true;
    }
}
