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
            $htContent = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n";
            @file_put_contents($htaccess, $htContent);
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
            if (($node['type'] ?? '') === 'form') {
                $subRel = preg_replace('/\.form\.json$/i', '.submissions.json', $node['file']);
                if ($subRel && $subRel !== $node['file']) {
                    $absSub = Config::safePath(Config::getBaseDir(), $subRel);
                    if ($absSub && is_file($absSub)) {
                        $files[] = $subRel;
                    }
                }
            }
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
     * Collects all category IDs, chapter slugs, and docIds within a node subtree or node list.
     */
    private static function collectTreeIdentifiers(array $nodes): array {
        $ids = [];
        $slugs = [];
        $docIds = [];
        foreach ($nodes as $node) {
            if (!is_array($node)) continue;
            if (!empty($node['id'])) {
                $ids[] = (string)$node['id'];
            }
            if (!empty($node['slug'])) {
                $slugs[] = (string)$node['slug'];
            }
            if (!empty($node['docId'])) {
                $docIds[] = (string)$node['docId'];
            }
            if (!empty($node['items']) && is_array($node['items'])) {
                $sub = self::collectTreeIdentifiers($node['items']);
                $ids = array_merge($ids, $sub['ids']);
                $slugs = array_merge($slugs, $sub['slugs']);
                $docIds = array_merge($docIds, $sub['docIds']);
            }
        }
        return [
            'ids' => array_values(array_unique($ids)),
            'slugs' => array_values(array_unique($slugs)),
            'docIds' => array_values(array_unique($docIds)),
        ];
    }

    private static function purgeManifestRevisions(array $manifest): void {
        $revManagerPath = dirname(__DIR__, 2) . '/assets/extensions/tool-revisions/RevisionManager.php';
        if (!is_file($revManagerPath)) {
            return;
        }
        require_once $revManagerPath;
        if (!class_exists('\Qwiki\Extensions\Revisions\RevisionManager') || !method_exists('\Qwiki\Extensions\Revisions\RevisionManager', 'purgeDocRevisions')) {
            return;
        }
        $docIds = [];
        if (!empty($manifest['docId'])) {
            $docIds[] = (string)$manifest['docId'];
        }
        if (!empty($manifest['node']) && is_array($manifest['node'])) {
            $collected = self::collectTreeIdentifiers([$manifest['node']]);
            $docIds = array_merge($docIds, $collected['docIds']);
        }
        foreach (array_unique($docIds) as $docId) {
            if ($docId !== '') {
                \Qwiki\Extensions\Revisions\RevisionManager::purgeDocRevisions($docId);
            }
        }
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
        if (!isset($config['books']) || !is_array($config['books'])) {
            $config['books'] = [];
        }

        // Collision checks before touching disk or config
        $existingIds = self::collectTreeIdentifiers($config['books']);
        $restoreIds = self::collectTreeIdentifiers([$manifest['node']]);
        $collidingCats = array_values(array_intersect($existingIds['ids'], $restoreIds['ids']));
        if (!empty($collidingCats)) {
            return ['success' => false, 'error' => "Cannot restore: category ID '{$collidingCats[0]}' already exists."];
        }
        $collidingSlugs = array_values(array_intersect($existingIds['slugs'], $restoreIds['slugs']));
        if (!empty($collidingSlugs)) {
            return ['success' => false, 'error' => "Cannot restore: document slug '{$collidingSlugs[0]}' already exists."];
        }
        foreach (($manifest['files'] ?? []) as $relFile) {
            $destAbs = Config::safePath($baseDir, $relFile);
            if ($destAbs && file_exists($destAbs)) {
                return ['success' => false, 'error' => "Cannot restore: target file '{$relFile}' already exists on disk."];
            }
        }

        $filesDir = $entryDir . '/files';
        foreach (($manifest['files'] ?? []) as $relFile) {
            $srcAbs = $filesDir . '/' . $relFile;
            $destAbs = Config::safePath($baseDir, $relFile) ?: ($baseDir . '/' . $relFile);
            if (is_file($srcAbs)) {
                @mkdir(dirname($destAbs), 0755, true);
                @rename($srcAbs, $destAbs) || (@copy($srcAbs, $destAbs) && @unlink($srcAbs));
            }
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
        $manifestFile = $entryDir . '/manifest.json';
        if (is_file($manifestFile)) {
            $manifest = json_decode(@file_get_contents($manifestFile), true);
            if (is_array($manifest)) {
                self::purgeManifestRevisions($manifest);
            }
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
                $manifestFile = $path . '/manifest.json';
                if (is_file($manifestFile)) {
                    $manifest = json_decode(@file_get_contents($manifestFile), true);
                    if (is_array($manifest)) {
                        self::purgeManifestRevisions($manifest);
                    }
                }
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
