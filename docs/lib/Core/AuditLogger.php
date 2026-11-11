<?php
namespace Qwiki\Core;

/**
 * Standalone Qwiki - Audit & Activity Logger Engine
 *
 * Provides high-performance, non-blocking append-only JSON Lines (.jsonl)
 * audit logging for share link hits, authentication events, content modifications,
 * and system operations.
 */
class AuditLogger {
    const LOG_DIR_NAME = 'content/.logs';
    const DEFAULT_RETENTION_DAYS = 30;

    /**
     * Get and ensure the log directory exists and is secured with .htaccess
     */
    public static function getLogDir(): string {
        $baseDir = Config::getBaseDir();
        $logDir = $baseDir . '/' . self::LOG_DIR_NAME;
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $htaccess = $logDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Order allow,deny\nDeny from all\n");
        }
        return $logDir;
    }

    /**
     * Resolve path to current month's log file (e.g. content/.logs/activity-2026-10.jsonl)
     */
    public static function getCurrentLogFilePath(?string $month = null): string {
        $m = $month ?: date('Y-m');
        // Sanitize month string to prevent directory traversal
        $cleanMonth = preg_replace('/[^0-9\-]/', '', $m);
        return self::getLogDir() . '/activity-' . $cleanMonth . '.jsonl';
    }

    /**
     * Resolve client IP according to configured privacy mode
     *
     * Modes:
     * - 'hash' (default, GDPR compliant): 12-char SHA-256 hash
     * - 'masked': subnet masked (e.g. 192.168.1.0/24 or IPv6 /48)
     * - 'raw': full IP address
     *
     * @param string|null $rawIp
     * @param string|null $modeOverride
     * @return array [ 'ip' => string, 'type' => string ]
     */
    public static function resolveClientIp(?string $rawIp = null, ?string $modeOverride = null): array {
        if ($rawIp === null) {
            $rawIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            // Check for forwarded proxy IP if trusted proxy header present
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $candidate = trim($parts[0]);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    $rawIp = $candidate;
                }
            }
        }

        // Demo mode override: always anonymize test visitors
        if (Config::isDemoMode()) {
            return [
                'ip' => 'demo-visitor',
                'type' => 'demo'
            ];
        }

        $config = Config::load();
        $mode = $modeOverride ?: ($config['ipPrivacyMode'] ?? 'hash');

        if ($mode === 'raw') {
            return [
                'ip' => $rawIp,
                'type' => 'raw'
            ];
        }

        if ($mode === 'masked') {
            if (filter_var($rawIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $octets = explode('.', $rawIp);
                $masked = $octets[0] . '.' . $octets[1] . '.' . $octets[2] . '.0/24';
            } elseif (filter_var($rawIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $hextets = explode(':', $rawIp);
                $masked = implode(':', array_slice($hextets, 0, 3)) . '::/48';
            } else {
                $masked = '127.0.0.0/24';
            }
            return [
                'ip' => $masked,
                'type' => 'masked'
            ];
        }

        // Default: 12-character truncated SHA-256 hash
        return [
            'ip' => substr(hash('sha256', $rawIp), 0, 12),
            'type' => 'hash'
        ];
    }

    /**
     * Primary log method (append-only JSON Lines)
     *
     * @param string $category 'share'|'auth'|'content'|'system'
     * @param string $event Event identifier (e.g. 'share_access', 'login_success')
     * @param int $status HTTP or logical status code (e.g. 200, 401, 403, 404)
     * @param array $details Arbitrary structured event payload
     * @param string|null $username Acting user or null for auto-session resolution
     * @return bool
     */
    public static function log(string $category, string $event, int $status = 200, array $details = [], ?string $username = null): bool {
        try {
            // Determine active user if not explicitly passed
            if ($username === null) {
                if (Auth::isLoggedIn()) {
                    $u = Auth::getCurrentUser();
                    $username = $u['username'] ?? 'unknown';
                } else {
                    $username = 'guest';
                }
            }

            $ipInfo = self::resolveClientIp();
            $logFile = self::getCurrentLogFilePath();

            $record = [
                'id'       => 'log_' . bin2hex(random_bytes(6)),
                'ts'       => date('c'), // ISO 8601 with timezone
                'category' => $category,
                'event'    => $event,
                'user'     => $username,
                'ip'       => $ipInfo['ip'],
                'ip_type'  => $ipInfo['type'],
                'status'   => (int)$status,
                'details'  => $details
            ];

            $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
            return @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX) !== false;
        } catch (\Throwable $e) {
            // Failsafe: logging must never crash normal page flow
            return false;
        }
    }

    /**
     * Helper for logging Share Link hits and transclusions
     *
     * @param string $shareKey 16-hexadecimal share token
     * @param array|null $chapter Matched chapter record if found
     * @param string|null $shareError Error code: null, 'not_found', 'restricted'
     * @param bool $isJson Whether accessed as raw JSON transclusion
     */
    public static function logShareAccess(string $shareKey, ?array $chapter = null, ?string $shareError = null, bool $isJson = false): bool {
        $status = 200;
        if ($shareError === 'not_found' || empty($chapter)) {
            $status = 404;
        } elseif ($shareError === 'restricted') {
            $status = 403;
        }

        $details = [
            'share_key'  => $shareKey,
            'slug'       => $chapter['slug'] ?? '',
            'title'      => $chapter['title'] ?? '',
            'file'       => $chapter['file'] ?? '',
            'type'       => $chapter['type'] ?? 'markdown',
            'format'     => $isJson ? 'json' : 'html',
            'error'      => $shareError,
            'referrer'   => $_SERVER['HTTP_REFERER'] ?? '',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        ];

        return self::log('share', 'share_access', $status, $details);
    }

    /**
     * Helper for logging authentication events
     */
    public static function logAuth(string $event, int $status = 200, array $details = [], ?string $username = null): bool {
        return self::log('auth', $event, $status, $details, $username);
    }

    /**
     * Helper for logging document, media, and structural mutations
     */
    public static function logMutation(string $event, int $status = 200, array $details = [], ?string $username = null): bool {
        return self::log('content', $event, $status, $details, $username);
    }

    /**
     * List all log partition files in reverse chronological order
     */
    public static function getLogFiles(): array {
        $logDir = self::getLogDir();
        $files = glob($logDir . '/activity-*.jsonl');
        if (!$files) {
            return [];
        }
        rsort($files); // Newest month first
        return $files;
    }

    /**
     * Query and paginate logs in reverse chronological order
     *
     * @param array $filters [ 'category' => string, 'event' => string, 'search' => string, 'status' => int|string, 'since' => string ]
     * @param int $page 1-indexed page
     * @param int $perPage records per page
     * @return array [ 'total' => int, 'page' => int, 'per_page' => int, 'items' => array ]
     */
    public static function queryLogs(array $filters = [], int $page = 1, int $perPage = 50): array {
        $files = self::getLogFiles();
        if (empty($files)) {
            return ['total' => 0, 'page' => $page, 'per_page' => $perPage, 'items' => []];
        }

        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset = ($page - 1) * $perPage;

        $targetCategory = !empty($filters['category']) ? strtolower(trim($filters['category'])) : '';
        $targetEvent = !empty($filters['event']) ? strtolower(trim($filters['event'])) : '';
        $searchTerm = !empty($filters['search']) ? strtolower(trim($filters['search'])) : '';
        $targetStatus = isset($filters['status']) && $filters['status'] !== '' ? (int)$filters['status'] : null;
        $sinceTimestamp = !empty($filters['since']) ? strtotime($filters['since']) : null;

        $matchedCount = 0;
        $items = [];

        // Scan files newest first
        foreach ($files as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!$lines) {
                continue;
            }

            // Iterate lines from end to start (newest records first)
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $line = trim($lines[$i]);
                if ($line === '') continue;

                $record = json_decode($line, true);
                if (!is_array($record)) continue;

                // 1. Time Filter
                if ($sinceTimestamp !== null) {
                    $recTime = isset($record['ts']) ? strtotime($record['ts']) : 0;
                    if ($recTime < $sinceTimestamp) {
                        continue;
                    }
                }

                // 2. Category Filter
                if ($targetCategory !== '' && $targetCategory !== 'all') {
                    if (strtolower($record['category'] ?? '') !== $targetCategory) {
                        continue;
                    }
                }

                // 3. Event Filter
                if ($targetEvent !== '' && $targetEvent !== 'all') {
                    if (strtolower($record['event'] ?? '') !== $targetEvent) {
                        continue;
                    }
                }

                // 4. Status Filter
                if ($targetStatus !== null) {
                    if ((int)($record['status'] ?? 0) !== $targetStatus) {
                        continue;
                    }
                }

                // 5. Search Filter (matches slug, title, file, user, IP, or raw text)
                if ($searchTerm !== '') {
                    $found = false;
                    if (stripos($record['user'] ?? '', $searchTerm) !== false) $found = true;
                    elseif (stripos($record['ip'] ?? '', $searchTerm) !== false) $found = true;
                    elseif (stripos($record['event'] ?? '', $searchTerm) !== false) $found = true;
                    elseif (!empty($record['details']) && is_array($record['details'])) {
                        foreach (['slug', 'title', 'file', 'share_key', 'referrer'] as $f) {
                            if (!empty($record['details'][$f]) && stripos((string)$record['details'][$f], $searchTerm) !== false) {
                                $found = true;
                                break;
                            }
                        }
                    }
                    if (!$found) continue;
                }

                $matchedCount++;

                // Check pagination window
                if ($matchedCount > $offset && count($items) < $perPage) {
                    $items[] = $record;
                }
            }
        }

        return [
            'total'    => $matchedCount,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $matchedCount > 0 ? (int)ceil($matchedCount / $perPage) : 1,
            'items'    => $items
        ];
    }

    /**
     * Compute activity metrics for dashboard cards
     */
    public static function getStats(): array {
        $files = self::getLogFiles();
        $totalEntries = 0;
        $totalBytes = 0;
        $shareHitsTotal = 0;
        $shareHitsToday = 0;
        $failedLoginsToday = 0;
        $todayStr = date('Y-m-d');
        $shareDocCounts = [];

        foreach ($files as $file) {
            $totalBytes += (int)@filesize($file);
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!$lines) continue;

            $totalEntries += count($lines);

            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $record = json_decode($lines[$i], true);
                if (!is_array($record)) continue;

                $isToday = !empty($record['ts']) && strpos($record['ts'], $todayStr) === 0;
                $cat = $record['category'] ?? '';
                $event = $record['event'] ?? '';
                $status = (int)($record['status'] ?? 0);

                if ($cat === 'share' && $event === 'share_access') {
                    $shareHitsTotal++;
                    if ($isToday) {
                        $shareHitsToday++;
                    }
                    $docName = $record['details']['title'] ?? $record['details']['slug'] ?? 'Unknown Document';
                    if (!empty($docName)) {
                        $shareDocCounts[$docName] = ($shareDocCounts[$docName] ?? 0) + 1;
                    }
                }

                if ($cat === 'auth' && ($event === 'login_failed' || $event === '2fa_failed') && $isToday) {
                    $failedLoginsToday++;
                }
            }
        }

        arsort($shareDocCounts);
        $topDoc = !empty($shareDocCounts) ? array_key_first($shareDocCounts) : 'None yet';
        $topDocCount = !empty($shareDocCounts) ? reset($shareDocCounts) : 0;

        return [
            'total_entries'        => $totalEntries,
            'storage_bytes'        => $totalBytes,
            'storage_formatted'    => self::formatBytes($totalBytes),
            'share_hits_total'     => $shareHitsTotal,
            'share_hits_today'     => $shareHitsToday,
            'failed_logins_today'  => $failedLoginsToday,
            'top_shared_doc'       => $topDoc,
            'top_shared_doc_count' => $topDocCount
        ];
    }

    /**
     * Purge log records older than given retention days
     */
    public static function purgeLogs(int $keepDays = self::DEFAULT_RETENTION_DAYS): int {
        if ($keepDays < 1) $keepDays = 1;
        $files = self::getLogFiles();
        $cutoff = time() - ($keepDays * 86400);
        $deletedFiles = 0;

        foreach ($files as $file) {
            // Extract YYYY-MM from filename (activity-YYYY-MM.jsonl)
            if (preg_match('/activity-(\d{4}-\d{2})\.jsonl$/', $file, $matches)) {
                $monthTime = strtotime($matches[1] . '-01 +1 month');
                // If entire month ended before cutoff, remove whole file
                if ($monthTime < $cutoff) {
                    @unlink($file);
                    $deletedFiles++;
                }
            }
        }
        return $deletedFiles;
    }

    /**
     * Format byte sizes into readable units
     */
    public static function formatBytes(int $bytes, int $precision = 1): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min((int)$pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
