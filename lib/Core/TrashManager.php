<?php
namespace Qwiki\Core;

class TrashManager {
    const TRASH_DIR_NAME = 'data/trash';

    public static function getTrashDir(): string {
        $baseDir = Config::getBaseDir();
        $trashDir = $baseDir . '/' . self::TRASH_DIR_NAME;
        if (!is_dir($trashDir)) {
            @mkdir($trashDir, 0755, true);
        }
        $htaccess = $trashDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Order allow,deny\nDeny from all\n");
        }
        return $trashDir;
    }

    /**
     * Counts nested subcategories and documents inside a category node.
     */
    public static function countCategoryDescendants(array $node): array {
        $counts = ['categories' => 0, 'documents' => 0, 'slugs' => []];
        if (empty($node['items']) || !is_array($node['items'])) {
            return $counts;
        }
        foreach ($node['items'] as $item) {
            if (!is_array($item)) continue;
            if (($item['type'] ?? '') === 'folder' || isset($item['id'])) {
                $counts['categories']++;
                $sub = self::countCategoryDescendants($item);
                $counts['categories'] += $sub['categories'];
                $counts['documents'] += $sub['documents'];
                $counts['slugs'] = array_merge($counts['slugs'], $sub['slugs']);
            } else {
                $counts['documents']++;
                if (!empty($item['slug'])) {
                    $counts['slugs'][] = $item['slug'];
                }
            }
        }
        return $counts;
    }

    /**
     * Collects all relative file paths referenced by a node or subtree.
     */
    private static function collectNodeFiles(array $node): array {
        $files = [];
        if (!empty($node['file']) && strpos($node['file'], 'content/') === 0) {
            $files[] = $node['file'];
        }
        if (!empty($node['items']) && is_array($node['items'])) {
            foreach ($node['items'] as $item) {
                if (is_array($item)) {
                    $files = array_merge($files, self::collectNodeFiles($item));
                }
            }
        }
        return array_values(array_unique($files));
    }

    /**
     * Moves a chapter's metadata and content file into data/trash/.
     */
    public static function trashChapter(array $chapter, string $parentBookId, string $actor, string $source = 'ui_delete'): array {
        $slug = $chapter['slug'] ?? 'unknown';
        $trashId = 'doc_' . date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $slug) . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
        $entryDir = self::getTrashDir() . '/' . $trashId;
        $filesDir = $entryDir . '/files';
        @mkdir($filesDir, 0755, true);

        $baseDir = Config::getBaseDir();
        $movedFiles = [];
        foreach (self::collectNodeFiles($chapter) as $relFile) {
            $absSrc = Config::safePath($baseDir, $relFile);
            if ($absSrc && file_exists($absSrc) && is_file($absSrc)) {
                $destAbs = $filesDir . '/' . $relFile;
                @mkdir(dirname($destAbs), 0755, true);
                if (@rename($absSrc, $destAbs) || (@copy($absSrc, $destAbs) && @unlink($absSrc))) {
                    $movedFiles[] = $relFile;
                }
            }
        }

        $manifest = [
            'trashId' => $trashId,
            'kind' => 'chapter',
            'title' => $chapter['title'] ?? $slug,
            'slug' => $slug,
            'docId' => $chapter['docId'] ?? '',
            'parentBookId' => $parentBookId,
            'deletedAt' => date('c'),
            'deletedBy' => $actor,
            'source' => $source,
            'node' => $chapter,
            'files' => $movedFiles,
        ];
        @file_put_contents($entryDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $manifest;
    }

    /**
     * Moves an entire category subtree and its content files into data/trash/.
     */
    public static function trashCategory(array $categoryNode, string $parentBookId, string $actor, string $source = 'ui_delete'): array {
        $bookId = $categoryNode['id'] ?? 'unknown';
        $trashId = 'cat_' . date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $bookId) . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
        $entryDir = self::getTrashDir() . '/' . $trashId;
        $filesDir = $entryDir . '/files';
        @mkdir($filesDir, 0755, true);

        $baseDir = Config::getBaseDir();
        $movedFiles = [];
        foreach (self::collectNodeFiles($categoryNode) as $relFile) {
            $absSrc = Config::safePath($baseDir, $relFile);
            if ($absSrc && file_exists($absSrc) && is_file($absSrc)) {
                $destAbs = $filesDir . '/' . $relFile;
                @mkdir(dirname($destAbs), 0755, true);
                if (@rename($absSrc, $destAbs) || (@copy($absSrc, $destAbs) && @unlink($absSrc))) {
                    $movedFiles[] = $relFile;
                }
            }
        }

        $counts = self::countCategoryDescendants($categoryNode);
        $manifest = [
            'trashId' => $trashId,
            'kind' => 'category',
            'title' => $categoryNode['title'] ?? $bookId,
            'bookId' => $bookId,
            'parentBookId' => $parentBookId,
            'deletedAt' => date('c'),
            'deletedBy' => $actor,
            'source' => $source,
            'documentCount' => $counts['documents'],
            'categoryCount' => $counts['categories'],
            'slugs' => $counts['slugs'],
            'node' => $categoryNode,
            'files' => $movedFiles,
        ];
        @file_put_contents($entryDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $manifest;
    }

    /**
     * Lists all items currently in Trash, newest first.
     */
    public static function listTrash(): array {
        $trashDir = self::getTrashDir();
        $items = [];
        $dirs = @scandir($trashDir) ?: [];
        foreach ($dirs as $d) {
            if ($d === '.' || $d === '..' || $d === '.htaccess') continue;
            $manifestFile = $trashDir . '/' . $d . '/manifest.json';
            if (is_file($manifestFile)) {
                $data = json_decode(@file_get_contents($manifestFile), true);
                if (is_array($data)) {
                    $items[] = $data;
                }
            }
        }
        usort($items, function ($a, $b) {
            return strcmp($b['deletedAt'] ?? '', $a['deletedAt'] ?? '');
        });
        return $items;
    }

    /**
     * Restores a trashed chapter or category back into $config['books'] and content/.
     */
    public static function restoreItem(array &$config, string $trashId, string $actor): array {
        $safeId = basename($trashId);
        $entryDir = self::getTrashDir() . '/' . $safeId;
        $manifestFile = $entryDir . '/manifest.json';
        if (!is_file($manifestFile)) {
            return ['success' => false, 'error' => 'Trash item not found'];
        }
        $manifest = json_decode(@file_get_contents($manifestFile), true);
        if (!is_array($manifest) || empty($manifest['node'])) {
            return ['success' => false, 'error' => 'Corrupted trash manifest'];
        }

        $baseDir = Config::getBaseDir();
        $filesDir = $entryDir . '/files';
        foreach (($manifest['files'] ?? []) as $relFile) {
            $srcAbs = $filesDir . '/' . $relFile;
            $destAbs = $baseDir . '/' . $relFile;
            if (is_file($srcAbs)) {
                @mkdir(dirname($destAbs), 0755, true);
                @rename($srcAbs, $destAbs) || (@copy($srcAbs, $destAbs) && @unlink($srcAbs));
            }
        }

        if (!isset($config['books']) || !is_array($config['books'])) {
            $config['books'] = [];
        }

        $node = $manifest['node'];
        if (($manifest['kind'] ?? '') === 'category') {
            $parentId = $manifest['parentBookId'] ?? '';
            if (!empty($parentId) && Navigation::insertCategoryIntoNode($config['books'], $parentId, $node)) {
                // Inserted back under parent
            } else {
                $config['books'][] = $node;
            }
        } else {
            $parentId = $manifest['parentBookId'] ?? '';
            $inserted = false;
            if (!empty($parentId)) {
                foreach ($config['books'] as &$book) {
                    if (Navigation::insertChapterIntoNode($book, $parentId, $node)) {
                        $inserted = true;
                        break;
                    }
                }
            }
            if (!$inserted) {
                if (empty($config['books'])) {
                    $config['books'][] = [
                        'id' => 'restored',
                        'title' => 'Restored Documents',
                        'type' => 'folder',
                        'folder' => 'content/restored',
                        'items' => [$node],
                    ];
                } else {
                    if (!isset($config['books'][0]['items']) || !is_array($config['books'][0]['items'])) {
                        $config['books'][0]['items'] = [];
                    }
                    $config['books'][0]['items'][] = $node;
                }
            }
        }

        Config::save($config);
        self::removeDirRecursive($entryDir);

        if (class_exists('\Qwiki\Core\AuditLogger')) {
            AuditLogger::logMutation('trash_restored', 200, [
                'trashId' => $safeId,
                'kind' => $manifest['kind'] ?? 'unknown',
                'title' => $manifest['title'] ?? '',
            ], $actor);
        }

        return ['success' => true, 'restored' => $manifest];
    }

    /**
     * Permanently deletes a single item from Trash.
     */
    public static function purgeItem(string $trashId, string $actor): array {
        $safeId = basename($trashId);
        $entryDir = self::getTrashDir() . '/' . $safeId;
        if (!is_dir($entryDir)) {
            return ['success' => false, 'error' => 'Trash item not found'];
        }
        self::removeDirRecursive($entryDir);
        if (class_exists('\Qwiki\Core\AuditLogger')) {
            AuditLogger::logMutation('trash_purged', 200, ['trashId' => $safeId], $actor);
        }
        return ['success' => true];
    }

    /**
     * Permanently deletes all items in Trash.
     */
    public static function emptyTrash(string $actor): array {
        $trashDir = self::getTrashDir();
        $dirs = @scandir($trashDir) ?: [];
        $count = 0;
        foreach ($dirs as $d) {
            if ($d === '.' || $d === '..' || $d === '.htaccess') continue;
            $path = $trashDir . '/' . $d;
            if (is_dir($path)) {
                self::removeDirRecursive($path);
                $count++;
            }
        }
        if (class_exists('\Qwiki\Core\AuditLogger')) {
            AuditLogger::logMutation('trash_emptied', 200, ['purged_count' => $count], $actor);
        }
        return ['success' => true, 'purged' => $count];
    }

    private static function removeDirRecursive(string $dir): void {
        if (!is_dir($dir)) return;
        $items = @scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::removeDirRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
