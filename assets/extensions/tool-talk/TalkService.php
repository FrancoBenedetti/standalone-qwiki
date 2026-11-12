<?php
namespace Qwiki\Extension\Talk;

require_once dirname(dirname(dirname(__DIR__))) . '/lib/Parsedown.php';
require_once dirname(dirname(dirname(__DIR__))) . '/lib/Core/Config.php';

use Qwiki\Core\Config;

class TalkService {
    private static $storageDir = null;
    private static $activeLocks = [];

    /**
     * Get and initialize the secure flat-file storage directory for talk pages.
     */
    public static function getStorageDir(): string {
        if (self::$storageDir === null) {
            $baseDir = Config::getBaseDir();
            self::$storageDir = $baseDir . '/data/talk';
        }

        if (!is_dir(self::$storageDir)) {
            @mkdir(self::$storageDir, 0755, true);
        }

        $htFile = self::$storageDir . '/.htaccess';
        if (!file_exists($htFile)) {
            $htContent = "# Deny all direct web access to talk markdown storage\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n";
            @file_put_contents($htFile, $htContent);
        }

        return self::$storageDir;
    }

    /**
     * Compute safe storage filename for given book ID and chapter slug.
     */
    public static function getFilePath(string $bookId, string $slug): string {
        $dir = self::getStorageDir();
        $safeBook = preg_replace('/[^a-zA-Z0-9_-]/', '_', $bookId ?: 'default');
        $safeSlug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $slug ?: 'index');
        return $dir . '/' . $safeBook . '__' . $safeSlug . '.talk.md';
    }

    /**
     * Resolve cascading inheritance for talk pages enablement:
     * Category default: disabled (unless explicitly allowTalk: true)
     * Chapter default: inherit (or override with enabled/disabled)
     */
    public static function isTalkEnabled(string $bookId, string $slug, array $config): bool {
        if (empty($config['books']) || !is_array($config['books'])) {
            return false;
        }

        $targetCategory = null;
        $targetChapter = null;

        self::findCategoryAndChapter($config['books'], $bookId, $slug, $targetCategory, $targetChapter);

        if (!$targetChapter) {
            return false;
        }

        // Chapter level override
        if (isset($targetChapter['allowTalk'])) {
            $chapterVal = $targetChapter['allowTalk'];
            if ($chapterVal === true || $chapterVal === 'enabled' || $chapterVal === '1' || $chapterVal === 1) {
                return true;
            }
            if ($chapterVal === false || $chapterVal === 'disabled' || $chapterVal === '0' || $chapterVal === 0) {
                return false;
            }
        }

        // Category level
        if ($targetCategory && !empty($targetCategory['allowTalk'])) {
            return true;
        }

        // Default if not set is disabled
        return false;
    }

    /**
     * Recursively find matching category and chapter.
     */
    private static function findCategoryAndChapter(array $nodes, string $bookId, string $slug, &$foundCategory, &$foundChapter, $currentParent = null): void {
        foreach ($nodes as $node) {
            $hasItems = isset($node['items']) && is_array($node['items']);
            $hasChapters = isset($node['chapters']) && is_array($node['chapters']);
            $isFolder = $hasItems || $hasChapters || (($node['type'] ?? '') === 'folder');

            if ($isFolder) {
                $isCurrentBook = (!empty($node['id']) && $node['id'] === $bookId);
                $parentForChildren = $isCurrentBook ? $node : ($currentParent ?: $node);
                if ($isCurrentBook && !$foundCategory) {
                    $foundCategory = $node;
                }

                if ($hasItems) {
                    self::findCategoryAndChapter($node['items'], $bookId, $slug, $foundCategory, $foundChapter, $parentForChildren);
                }
                if ($hasChapters) {
                    self::findCategoryAndChapter($node['chapters'], $bookId, $slug, $foundCategory, $foundChapter, $parentForChildren);
                }
            }

            if (!empty($node['slug']) && $node['slug'] === $slug) {
                $foundChapter = $node;
                if (!$foundCategory && $currentParent) {
                    $foundCategory = $currentParent;
                }
            }
        }
    }

    /**
     * Synchronize file operations using an exclusive process lock with re-entrant support.
     */
    public static function withLock(string $bookId, string $slug, callable $callback) {
        $dir = self::getStorageDir();
        $safeBook = preg_replace('/[^a-zA-Z0-9_-]/', '_', $bookId ?: 'default');
        $safeSlug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $slug ?: 'index');
        $lockKey = $safeBook . '__' . $safeSlug;

        // Re-entrant lock check: if this process already holds the lock on this chapter, execute callback directly
        if (!empty(self::$activeLocks[$lockKey])) {
            return $callback();
        }

        $lockFile = $dir . '/' . $lockKey . '.lock';
        $fp = @fopen($lockFile, 'c+');
        if (!$fp) {
            return $callback();
        }

        flock($fp, LOCK_EX);
        self::$activeLocks[$lockKey] = $fp;
        try {
            return $callback();
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
            unset(self::$activeLocks[$lockKey]);
        }
    }

    /**
     * Read raw Markdown content from storage.
     */
    public static function getRawContent(string $bookId, string $slug): string {
        $file = self::getFilePath($bookId, $slug);
        if (!file_exists($file)) {
            return '';
        }

        $fp = @fopen($file, 'rb');
        if (!$fp) {
            return '';
        }

        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return $content !== false ? $content : '';
    }

    /**
     * Atomically save raw Markdown content.
     */
    public static function saveRawContent(string $bookId, string $slug, string $markdown): bool {
        return self::withLock($bookId, $slug, function() use ($bookId, $slug, $markdown) {
            $dir = self::getStorageDir();
            $targetFile = self::getFilePath($bookId, $slug);
            $tempFile = $dir . '/tmp_' . uniqid('', true) . '.md';

            $fp = @fopen($tempFile, 'wb');
            if (!$fp) {
                return false;
            }

            flock($fp, LOCK_EX);
            fwrite($fp, $markdown);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);

            return @rename($tempFile, $targetFile);
        });
    }

    /**
     * Parse raw markdown into structured topic items.
     */
    public static function getTopics(string $bookId, string $slug): array {
        $raw = self::getRawContent($bookId, $slug);
        if (trim($raw) === '') {
            return [];
        }

        // Split by markdown H2 headings (## Heading)
        $lines = explode("\n", $raw);
        $topics = [];
        $currentTitle = null;
        $currentLines = [];
        $introLines = [];

        foreach ($lines as $line) {
            if (preg_match('/^##\s+(.+)$/', $line, $matches)) {
                if ($currentTitle !== null) {
                    $sectionText = implode("\n", $currentLines);
                    $topics[] = [
                        'index' => count($topics),
                        'title' => trim($currentTitle),
                        'rawMarkdown' => trim($sectionText),
                        'html' => self::renderSafeMarkdown($sectionText)
                    ];
                } else {
                    $introLines = $currentLines;
                }
                $currentTitle = $matches[1];
                $currentLines = [];
            } else {
                $currentLines[] = $line;
            }
        }

        if ($currentTitle !== null) {
            $sectionText = implode("\n", $currentLines);
            $topics[] = [
                'index' => count($topics),
                'title' => trim($currentTitle),
                'rawMarkdown' => trim($sectionText),
                'html' => self::renderSafeMarkdown($sectionText)
            ];
        }

        return $topics;
    }

    /**
     * Add a new topic section to the talk page.
     */
    public static function addTopic(string $bookId, string $slug, string $username, string $role, string $title, string $message): array {
        return self::withLock($bookId, $slug, function() use ($bookId, $slug, $username, $role, $title, $message) {
            $cleanTitle = trim(preg_replace('/[\r\n\t]+/', ' ', strip_tags($title)));
            if ($cleanTitle === '') {
                $cleanTitle = 'Untitled Discussion';
            }

            $cleanUsername = trim(strip_tags($username)) ?: 'Anonymous';
            $cleanRole = trim(strip_tags($role)) ?: 'viewer';
            $timestamp = date('Y-m-d H:i');

            // Demote any H2 in message body to prevent breaking topic boundaries
            $cleanMessage = preg_replace('/^##\s+/m', '### ', trim($message));

            $topicMarkdown = "## {$cleanTitle}\n\n"
                           . "**@{$cleanUsername}** ({$cleanRole}) *({$timestamp})*:\n"
                           . $cleanMessage . "\n\n";

            $raw = self::getRawContent($bookId, $slug);
            $updatedRaw = rtrim($raw) . ($raw ? "\n\n" : "") . $topicMarkdown;

            $saved = self::saveRawContent($bookId, $slug, $updatedRaw);
            if (!$saved) {
                return ['success' => false, 'error' => 'Failed to save topic'];
            }

            $topics = self::getTopics($bookId, $slug);
            return [
                'success' => true,
                'topicsCount' => count($topics),
                'topic' => end($topics) ?: null
            ];
        });
    }

    /**
     * Add an indented reply under a specific topic index.
     */
    public static function addReply(string $bookId, string $slug, string $username, string $role, int $topicIndex, string $message): array {
        return self::withLock($bookId, $slug, function() use ($bookId, $slug, $username, $role, $topicIndex, $message) {
            $raw = self::getRawContent($bookId, $slug);
            if (trim($raw) === '') {
                return ['success' => false, 'error' => 'Talk page is empty'];
            }

            $lines = explode("\n", $raw);
            $topicIndices = [];
            $currentIndex = -1;

            foreach ($lines as $lineNum => $line) {
                if (preg_match('/^##\s+(.+)$/', $line)) {
                    $currentIndex++;
                    $topicIndices[$currentIndex] = $lineNum;
                }
            }

            if (!isset($topicIndices[$topicIndex])) {
                return ['success' => false, 'error' => 'Topic not found'];
            }

            // Determine insertion point: before next ## topic or at end of file
            $insertLineNum = isset($topicIndices[$topicIndex + 1]) ? $topicIndices[$topicIndex + 1] : count($lines);

            $cleanUsername = trim(strip_tags($username)) ?: 'Anonymous';
            $cleanRole = trim(strip_tags($role)) ?: 'viewer';
            $timestamp = date('Y-m-d H:i');

            // Demote any H2 in reply message to prevent breaking topic boundaries
            $cleanMessage = preg_replace('/^##\s+/m', '### ', trim($message));
            $indentedMessage = implode("\n> ", explode("\n", $cleanMessage));
            $replyBlock = "\n> **@{$cleanUsername}** ({$cleanRole}) *({$timestamp})*:\n"
                        . "> {$indentedMessage}\n";

            $before = array_slice($lines, 0, $insertLineNum);
            $after = array_slice($lines, $insertLineNum);

            $updatedLines = array_merge($before, explode("\n", $replyBlock), $after);
            $updatedRaw = implode("\n", $updatedLines);

            $saved = self::saveRawContent($bookId, $slug, $updatedRaw);
            if (!$saved) {
                return ['success' => false, 'error' => 'Failed to save reply'];
            }

            $topics = self::getTopics($bookId, $slug);
            return [
                'success' => true,
                'topic' => $topics[$topicIndex] ?? null
            ];
        });
    }

    /**
     * Render safe Markdown stripped of XSS scripts and dangerous protocols.
     */
    public static function renderSafeMarkdown(string $markdown): string {
        $parsedown = new \Parsedown();
        $parsedown->setSafeMode(true);
        $html = $parsedown->text($markdown);

        // Sanitize scripts, iframes, and dangerous handlers
        $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
        $html = preg_replace('#<iframe(.*?)>(.*?)</iframe>#is', '', $html);
        $html = preg_replace('#\s*on[a-z]+\s*=\s*(["\']).*?\1#i', '', $html);
        $html = preg_replace('#\s*on[a-z]+\s*=[^\s>]+#i', '', $html);
        $html = preg_replace('#href=["\']\s*javascript:[^"\']*["\']#i', 'href="#"', $html);
        $html = preg_replace('#src=["\']\s*javascript:[^"\']*["\']#i', 'src=""', $html);
        $html = preg_replace('/<a\s+href=/i', '<a target="_blank" rel="noopener noreferrer" href=', $html);

        return $html;
    }
}
