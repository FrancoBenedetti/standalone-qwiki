<?php
namespace Qwiki\Core;

class LockManager {
    const DEFAULT_TTL = 60; // Lock lease duration in seconds
    const LOCK_DIR_NAME = 'content/.locks';

    /**
     * Get and ensure lock directory exists and is secured
     */
    public static function getLockDir() {
        $baseDir = Config::getBaseDir();
        $lockDir = $baseDir . '/' . self::LOCK_DIR_NAME;
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0755, true);
        }
        $htaccess = $lockDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Order allow,deny\nDeny from all\n");
        }
        return $lockDir;
    }

    /**
     * Generate normalized lock file path based on document relative path
     */
    public static function getLockFilePath($filePath) {
        $cleanPath = str_replace('\\', '/', trim($filePath));
        $cleanPath = ltrim($cleanPath, '/');
        $hash = md5($cleanPath);
        return self::getLockDir() . '/' . $hash . '.lock.json';
    }

    /**
     * Acquire or extend a document lock
     *
     * @param string $filePath Relative document path
     * @param string $tabId Unique client tab identifier
     * @param string $username Username of editor
     * @param bool $force Force takeover if already locked by another
     * @return array [success => bool, lock => array|null, error => string|null, code => string|null]
     */
    public static function acquireLock($filePath, $tabId, $username, $force = false) {
        if (empty($filePath) || empty($tabId) || empty($username)) {
            return ['success' => false, 'error' => 'Missing required lock parameters'];
        }

        $lockFile = self::getLockFilePath($filePath);
        $fp = @fopen($lockFile, 'c+');
        if (!$fp) {
            return ['success' => false, 'error' => 'Unable to open lock file'];
        }

        if (flock($fp, LOCK_EX)) {
            $fileSize = filesize($lockFile);
            $existing = null;
            if ($fileSize > 0) {
                rewind($fp);
                $content = fread($fp, $fileSize);
                $existing = json_decode($content, true);
            }

            $now = time();
            $isExpired = empty($existing['expiresAt']) || ($existing['expiresAt'] <= $now);

            // Check if already locked by someone else
            if ($existing && !$isExpired && !$force) {
                $isSameSession = ($existing['tabId'] === $tabId && $existing['user'] === $username);
                if (!$isSameSession) {
                    flock($fp, LOCK_UN);
                    fclose($fp);
                    return [
                        'success' => false,
                        'code' => 'LOCKED_BY_OTHER',
                        'lockedBy' => $existing['user'] ?? 'Unknown',
                        'isSameUser' => (($existing['user'] ?? '') === $username),
                        'tabId' => $existing['tabId'] ?? '',
                        'acquiredAt' => $existing['acquiredAt'] ?? $now,
                        'expiresIn' => max(0, ($existing['expiresAt'] ?? $now) - $now)
                    ];
                }
            }

            // Fresh lock or lease extension / takeover
            $lockData = [
                'file' => str_replace('\\', '/', trim($filePath)),
                'user' => $username,
                'tabId' => $tabId,
                'acquiredAt' => ($existing && !$isExpired && !$force) ? ($existing['acquiredAt'] ?? $now) : $now,
                'lastHeartbeat' => $now,
                'expiresAt' => $now + self::DEFAULT_TTL
            ];

            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, json_encode($lockData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);

            return [
                'success' => true,
                'lock' => $lockData,
                'expiresIn' => self::DEFAULT_TTL
            ];
        }

        fclose($fp);
        return ['success' => false, 'error' => 'Lock acquisition timeout'];
    }

    /**
     * Renew lease via heartbeat
     *
     * @param string $filePath
     * @param string $tabId
     * @param string $username
     * @return array
     */
    public static function renewLock($filePath, $tabId, $username) {
        if (empty($filePath) || empty($tabId) || empty($username)) {
            return ['success' => false, 'error' => 'Missing required parameters'];
        }

        $lockFile = self::getLockFilePath($filePath);
        if (!file_exists($lockFile)) {
            return [
                'success' => false,
                'code' => 'LOCK_LOST',
                'error' => 'Lock file does not exist. Lock may have expired.'
            ];
        }

        $fp = @fopen($lockFile, 'r+');
        if (!$fp) {
            return ['success' => false, 'error' => 'Unable to open lock file'];
        }

        if (flock($fp, LOCK_EX)) {
            $fileSize = filesize($lockFile);
            $content = $fileSize > 0 ? fread($fp, $fileSize) : '';
            $lock = json_decode($content, true);

            $now = time();
            if (!$lock || empty($lock['expiresAt']) || $lock['expiresAt'] < $now) {
                flock($fp, LOCK_UN);
                fclose($fp);
                @unlink($lockFile);
                return [
                    'success' => false,
                    'code' => 'LOCK_EXPIRED',
                    'error' => 'Document lock expired'
                ];
            }

            // Verify owner
            if ($lock['tabId'] !== $tabId || $lock['user'] !== $username) {
                flock($fp, LOCK_UN);
                fclose($fp);
                return [
                    'success' => false,
                    'code' => 'LOCK_TAKEN_OVER',
                    'lockedBy' => $lock['user'],
                    'isSameUser' => ($lock['user'] === $username),
                    'error' => 'Document lock was taken over by another tab or user'
                ];
            }

            // Renew
            $lock['lastHeartbeat'] = $now;
            $lock['expiresAt'] = $now + self::DEFAULT_TTL;

            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);

            return [
                'success' => true,
                'expiresIn' => self::DEFAULT_TTL
            ];
        }

        fclose($fp);
        return ['success' => false, 'error' => 'Unable to acquire file lock for renewal'];
    }

    /**
     * Release lock voluntarily (e.g., on save, cancel, or tab unload)
     *
     * @param string $filePath
     * @param string $tabId
     * @param string $username
     * @param bool $force
     * @return array
     */
    public static function releaseLock($filePath, $tabId, $username, $force = false) {
        $lockFile = self::getLockFilePath($filePath);
        if (!file_exists($lockFile)) {
            return ['success' => true];
        }

        $fp = @fopen($lockFile, 'r+');
        if (!$fp) {
            return ['success' => true];
        }

        if (flock($fp, LOCK_EX)) {
            $fileSize = filesize($lockFile);
            $content = $fileSize > 0 ? fread($fp, $fileSize) : '';
            $lock = json_decode($content, true);

            $shouldDelete = false;
            if (!$lock) {
                $shouldDelete = true;
            } elseif ($force || ($lock['tabId'] === $tabId && $lock['user'] === $username)) {
                $shouldDelete = true;
            } elseif ($lock['expiresAt'] < time()) {
                $shouldDelete = true;
            }

            flock($fp, LOCK_UN);
            fclose($fp);

            if ($shouldDelete) {
                @unlink($lockFile);
                return ['success' => true];
            }

            return [
                'success' => false,
                'error' => 'Lock belongs to a different session'
            ];
        }

        fclose($fp);
        return ['success' => false, 'error' => 'Failed to unlock file'];
    }

    /**
     * Check lock status of a document
     *
     * @param string $filePath
     * @param string|null $currentTabId
     * @return array
     */
    public static function checkLock($filePath, $currentTabId = null) {
        $lockFile = self::getLockFilePath($filePath);
        if (!file_exists($lockFile)) {
            return ['locked' => false];
        }

        $content = @file_get_contents($lockFile);
        $lock = json_decode($content, true);
        $now = time();

        if (!$lock || empty($lock['expiresAt']) || $lock['expiresAt'] <= $now) {
            @unlink($lockFile); // Lazy cleanup
            return ['locked' => false];
        }

        return [
            'locked' => true,
            'user' => $lock['user'] ?? 'Unknown',
            'tabId' => $lock['tabId'] ?? '',
            'isCurrentTab' => ($currentTabId && ($lock['tabId'] ?? '') === $currentTabId),
            'acquiredAt' => $lock['acquiredAt'] ?? $now,
            'expiresIn' => max(0, $lock['expiresAt'] - $now)
        ];
    }

    /**
     * Verify if save is permitted under current locking state
     *
     * @param string $filePath
     * @param string $tabId
     * @param string $username
     * @return array [allowed => bool, error => string|null, lockedBy => string|null]
     */
    public static function verifyOrRejectSave($filePath, $tabId, $username) {
        $lockFile = self::getLockFilePath($filePath);
        if (!file_exists($lockFile)) {
            // No lock currently active, permitted
            return ['allowed' => true];
        }

        $content = @file_get_contents($lockFile);
        $lock = json_decode($content, true);
        $now = time();

        // Expired locks do not block save
        if (!$lock || empty($lock['expiresAt']) || $lock['expiresAt'] <= $now) {
            @unlink($lockFile);
            return ['allowed' => true];
        }

        // Must belong to caller session
        if ($lock['tabId'] === $tabId && $lock['user'] === $username) {
            return ['allowed' => true];
        }

        return [
            'allowed' => false,
            'error' => 'Document is locked by another tab or user',
            'lockedBy' => $lock['user'] ?? 'Unknown',
            'isSameUser' => (($lock['user'] ?? '') === $username)
        ];
    }
}
