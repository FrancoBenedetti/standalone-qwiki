<?php
namespace Qwiki\Extension\Comments;

require_once dirname(dirname(dirname(__DIR__))) . '/lib/Parsedown.php';
require_once dirname(dirname(dirname(__DIR__))) . '/lib/Core/Config.php';

use Qwiki\Core\Config;

class CommentService {
    private static $storageDir = null;

    /**
     * Get and initialize the secure flat-file storage directory for comments.
     */
    public static function getStorageDir(): string {
        if (self::$storageDir === null) {
            $baseDir = Config::getBaseDir();
            self::$storageDir = $baseDir . '/data/comments';
        }

        if (!is_dir(self::$storageDir)) {
            @mkdir(self::$storageDir, 0755, true);
        }

        $htFile = self::$storageDir . '/.htaccess';
        if (!file_exists($htFile)) {
            $htContent = "# Deny all direct web access to comments JSON storage\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n";
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
        return $dir . '/' . $safeBook . '__' . $safeSlug . '.json';
    }

    /**
     * Resolve cascading inheritance for comments enablement:
     * - Category setting: default (if not set) is disabled (false)
     * - Page/Document setting: default is 'inherit from category', with explicit 'enabled' and 'disabled'
     */
    public static function isCommentsEnabled(string $bookId, string $slug, array $config): bool {
        $targetCategory = null;
        $targetChapter = null;

        if (!empty($config['books']) && is_array($config['books'])) {
            self::findCategoryAndChapter($config['books'], $bookId, $slug, $targetCategory, $targetChapter);
        }

        // Category setting: default (if not set) is disabled (false)
        $categoryEnabled = false;
        if ($targetCategory && !empty($targetCategory['allowComments'])) {
            $categoryEnabled = true;
        }

        // Document / Chapter level:
        // Default: 'inherit from category' ($categoryEnabled)
        // If chapter explicitly sets allowComments as boolean, it overrides the category
        if ($targetChapter && isset($targetChapter['allowComments']) && is_bool($targetChapter['allowComments'])) {
            return $targetChapter['allowComments'];
        }

        return $categoryEnabled;
    }

    /**
     * Recursive helper to locate category and chapter in navigation tree.
     */
    private static function findCategoryAndChapter(array $nodes, string $bookId, string $slug, &$foundCategory, &$foundChapter, $currentParent = null): void {
        foreach ($nodes as $node) {
            $hasItems = isset($node['items']) && is_array($node['items']);
            $isFolder = $hasItems || (($node['type'] ?? '') === 'folder');

            if ($isFolder) {
                $isCurrentBook = (!empty($node['id']) && $node['id'] === $bookId);
                $parentForChildren = $isCurrentBook ? $node : ($currentParent ?: $node);
                if ($isCurrentBook && !$foundCategory) {
                    $foundCategory = $node;
                }

                if ($hasItems) {
                    self::findCategoryAndChapter($node['items'], $bookId, $slug, $foundCategory, $foundChapter, $parentForChildren);
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
     * Render restricted, safe Markdown to HTML with strict XSS sanitization.
     */
    public static function renderSafeMarkdown(string $markdown): string {
        $parsedown = new \Parsedown();
        $parsedown->setSafeMode(true);
        $parsedown->setMarkupEscaped(true);

        $html = $parsedown->text($markdown);

        // Strip any remaining dangerous tags, event handlers or javascript: protocols
        $html = preg_replace('/<\/?(script|iframe|style|object|embed|form|input|button|meta|link)[^>]*>/i', '', $html);
        $html = preg_replace('/\s+on[a-z]+\s*=\s*(["\'][^"\']*["\']|[^\s>]+)/i', '', $html);
        $html = preg_replace('/href\s*=\s*["\']\s*javascript:[^"\']*["\']/i', 'href="#"', $html);
        $html = preg_replace('/href\s*=\s*["\']\s*data:[^"\']*["\']/i', 'href="#"', $html);

        // Ensure external links open securely
        $html = preg_replace('/<a\s+href=/i', '<a target="_blank" rel="noopener noreferrer" href=', $html);

        return trim($html);
    }

    /**
     * Read all comment threads for a document.
     */
    public static function getComments(string $bookId, string $slug): array {
        $file = self::getFilePath($bookId, $slug);
        if (!file_exists($file)) {
            return [
                'chapter' => $slug,
                'book' => $bookId,
                'updatedAt' => null,
                'comments' => []
            ];
        }

        $content = @file_get_contents($file);
        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['comments']) || !is_array($data['comments'])) {
            return [
                'chapter' => $slug,
                'book' => $bookId,
                'updatedAt' => null,
                'comments' => []
            ];
        }

        // Format and render safe HTML for each comment
        foreach ($data['comments'] as &$root) {
            $root['renderedHtml'] = $root['isDeleted']
                ? '<p class="comment-tombstone"><em>[Comment deleted]</em></p>'
                : self::renderSafeMarkdown($root['content'] ?? '');

            if (!isset($root['replies']) || !is_array($root['replies'])) {
                $root['replies'] = [];
            } else {
                foreach ($root['replies'] as &$reply) {
                    $reply['renderedHtml'] = $reply['isDeleted']
                        ? '<p class="comment-tombstone"><em>[Comment deleted]</em></p>'
                        : self::renderSafeMarkdown($reply['content'] ?? '');
                }
            }
        }

        return $data;
    }

    /**
     * Add a root comment or a 1-level reply to a chapter.
     */
    public static function addComment(string $bookId, string $slug, string $username, string $role, string $content, ?string $parentId = null): array {
        $cleanContent = trim($content);
        if ($cleanContent === '') {
            return ['success' => false, 'error' => 'Comment content cannot be empty.'];
        }
        if (mb_strlen($cleanContent) > 2000) {
            return ['success' => false, 'error' => 'Comment exceeds maximum allowed length of 2000 characters.'];
        }

        $commentId = 'cmt_' . substr(bin2hex(random_bytes(6)), 0, 8);
        $now = date('c');

        $newEntry = [
            'id' => $commentId,
            'parentId' => null,
            'author' => $username,
            'role' => $role,
            'content' => $cleanContent,
            'createdAt' => $now,
            'updatedAt' => null,
            'isDeleted' => false
        ];

        $file = self::getFilePath($bookId, $slug);
        $data = self::readDataWithLock($file);
        if (!$data) {
            $data = [
                'chapter' => $slug,
                'book' => $bookId,
                'updatedAt' => $now,
                'comments' => []
            ];
        }

        if (empty($parentId)) {
            // New root comment
            $newEntry['replies'] = [];
            $data['comments'][] = $newEntry;
        } else {
            // Find parent comment to attach reply. If parent is already a reply, resolve to root.
            $targetRootId = $parentId;
            foreach ($data['comments'] as $root) {
                if ($root['id'] === $parentId) {
                    $targetRootId = $root['id'];
                    break;
                }
                if (!empty($root['replies'])) {
                    foreach ($root['replies'] as $rep) {
                        if ($rep['id'] === $parentId) {
                            $targetRootId = $root['id'];
                            break 2;
                        }
                    }
                }
            }

            $newEntry['parentId'] = $targetRootId;

            $attached = false;
            foreach ($data['comments'] as &$root) {
                if ($root['id'] === $targetRootId) {
                    if (!isset($root['replies']) || !is_array($root['replies'])) {
                        $root['replies'] = [];
                    }
                    $root['replies'][] = $newEntry;
                    $attached = true;
                    break;
                }
            }

            if (!$attached) {
                // If parent wasn't found, treat as root comment
                $newEntry['parentId'] = null;
                $newEntry['replies'] = [];
                $data['comments'][] = $newEntry;
            }
        }

        $data['updatedAt'] = $now;
        self::writeDataWithLock($file, $data);

        $newEntry['renderedHtml'] = self::renderSafeMarkdown($cleanContent);
        return $newEntry;
    }

    /**
     * Edit a comment or reply with author / admin verification.
     */
    public static function editComment(string $bookId, string $slug, string $commentId, string $username, bool $isAdmin, string $newContent): array {
        $cleanContent = trim($newContent);
        if ($cleanContent === '') {
            return ['success' => false, 'error' => 'Comment content cannot be empty.'];
        }
        if (mb_strlen($cleanContent) > 2000) {
            return ['success' => false, 'error' => 'Comment exceeds maximum allowed length of 2000 characters.'];
        }

        $file = self::getFilePath($bookId, $slug);
        $data = self::readDataWithLock($file);
        if (!$data || empty($data['comments'])) {
            return ['success' => false, 'error' => 'Comment not found.'];
        }

        $found = null;
        $now = date('c');

        foreach ($data['comments'] as &$root) {
            if ($root['id'] === $commentId) {
                if (!$isAdmin && $root['author'] !== $username) {
                    return ['success' => false, 'error' => 'Unauthorized: You can only edit your own comments.'];
                }
                $root['content'] = $cleanContent;
                $root['updatedAt'] = $now;
                $found = $root;
                break;
            }
            if (!empty($root['replies'])) {
                foreach ($root['replies'] as &$rep) {
                    if ($rep['id'] === $commentId) {
                        if (!$isAdmin && $rep['author'] !== $username) {
                            return ['success' => false, 'error' => 'Unauthorized: You can only edit your own comments.'];
                        }
                        $rep['content'] = $cleanContent;
                        $rep['updatedAt'] = $now;
                        $found = $rep;
                        break 2;
                    }
                }
            }
        }

        if (!$found) {
            return ['success' => false, 'error' => 'Comment not found.'];
        }

        $data['updatedAt'] = $now;
        self::writeDataWithLock($file, $data);

        $found['renderedHtml'] = self::renderSafeMarkdown($cleanContent);
        return ['success' => true, 'comment' => $found];
    }

    /**
     * Delete a comment. If it has active replies, tombstone it; otherwise, prune it.
     */
    public static function deleteComment(string $bookId, string $slug, string $commentId, string $username, bool $isAdmin): array {
        $file = self::getFilePath($bookId, $slug);
        $data = self::readDataWithLock($file);
        if (!$data || empty($data['comments'])) {
            return ['success' => false, 'error' => 'Comment not found.'];
        }

        $deleted = false;
        $now = date('c');

        // 1. Check if it is a root comment
        for ($i = 0; $i < count($data['comments']); $i++) {
            if ($data['comments'][$i]['id'] === $commentId) {
                $target = &$data['comments'][$i];
                if (!$isAdmin && $target['author'] !== $username) {
                    return ['success' => false, 'error' => 'Unauthorized: You can only delete your own comments.'];
                }

                $hasActiveReplies = false;
                if (!empty($target['replies'])) {
                    foreach ($target['replies'] as $r) {
                        if (empty($r['isDeleted'])) {
                            $hasActiveReplies = true;
                            break;
                        }
                    }
                }

                if ($hasActiveReplies) {
                    // Soft-delete to preserve reply thread
                    $target['isDeleted'] = true;
                    $target['content'] = '';
                    $target['updatedAt'] = $now;
                } else {
                    // Hard-delete
                    array_splice($data['comments'], $i, 1);
                }
                $deleted = true;
                break;
            }
        }

        // 2. Check if it is a reply
        if (!$deleted) {
            foreach ($data['comments'] as &$root) {
                if (!empty($root['replies'])) {
                    for ($j = 0; $j < count($root['replies']); $j++) {
                        if ($root['replies'][$j]['id'] === $commentId) {
                            $targetReply = &$root['replies'][$j];
                            if (!$isAdmin && $targetReply['author'] !== $username) {
                                return ['success' => false, 'error' => 'Unauthorized: You can only delete your own comments.'];
                            }
                            array_splice($root['replies'], $j, 1);
                            $deleted = true;
                            break 2;
                        }
                    }
                }
            }
        }

        if (!$deleted) {
            return ['success' => false, 'error' => 'Comment not found.'];
        }

        $data['updatedAt'] = $now;
        self::writeDataWithLock($file, $data);

        return ['success' => true];
    }

    /**
     * Read JSON file with shared file lock.
     */
    private static function readDataWithLock(string $file): ?array {
        if (!file_exists($file)) {
            return null;
        }

        $fp = @fopen($file, 'r');
        if (!$fp) {
            return null;
        }

        @flock($fp, LOCK_SH);
        $contents = stream_get_contents($fp);
        @flock($fp, LOCK_UN);
        @fclose($fp);

        if (!$contents) {
            return null;
        }

        $data = json_decode($contents, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Atomically write JSON data with exclusive lock.
     */
    private static function writeDataWithLock(string $file, array $data): bool {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        $tmpFile = tempnam($dir, 'cmt_tmp_');
        $fp = @fopen($tmpFile, 'w');
        if (!$fp) {
            return false;
        }

        @flock($fp, LOCK_EX);
        fwrite($fp, $json);
        fflush($fp);
        @flock($fp, LOCK_UN);
        fclose($fp);

        return @rename($tmpFile, $file);
    }
}
