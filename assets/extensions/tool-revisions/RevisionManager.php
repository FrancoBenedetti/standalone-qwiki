<?php
namespace Qwiki\Extensions\Revisions;

use Qwiki\Core\Config;

class RevisionManager {
    const REVISIONS_DIR_NAME = 'data/revisions';
    const DEFAULT_MAX_REVISIONS = 20;

    public static function getRevisionsRoot(): string {
        $baseDir = Config::getBaseDir();
        $dir = $baseDir . '/' . self::REVISIONS_DIR_NAME;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Order allow,deny\nDeny from all\n");
        }
        return $dir;
    }

    public static function getDocDir(string $docId): string {
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $docId);
        $dir = self::getRevisionsRoot() . '/' . $safeId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Captures a snapshot of an existing document file before it is overwritten.
     */
    public static function snapshot(array $chapter, string $absFilePath, array $context = []): ?array {
        $docId = $chapter['docId'] ?? '';
        if (empty($docId) || !is_file($absFilePath) || !is_readable($absFilePath)) {
            return null;
        }

        $docDir = self::getDocDir($docId);
        $revId = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
        $bakFile = $docDir . '/' . $revId . '.bak';
        $metaFile = $docDir . '/' . $revId . '.json';

        if (!@copy($absFilePath, $bakFile)) {
            return null;
        }

        $meta = [
            'revId' => $revId,
            'docId' => $docId,
            'slug' => $chapter['slug'] ?? '',
            'file' => $chapter['file'] ?? '',
            'type' => $chapter['type'] ?? 'markdown',
            'status' => $chapter['status'] ?? 'published',
            'createdAt' => date('c'),
            'actor' => $context['actor'] ?? 'system',
            'source' => $context['source'] ?? 'unknown',
            'sha256' => $context['sha256Before'] ?? hash_file('sha256', $absFilePath),
            'sizeBytes' => filesize($absFilePath),
        ];
        @file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $config = Config::load();
        $maxKeep = (int)($config['revisions']['maxPerDoc'] ?? self::DEFAULT_MAX_REVISIONS);
        if ($maxKeep > 0) {
            self::pruneRevisions($docId, $maxKeep);
        }

        return $meta;
    }

    public static function listRevisions(string $docId): array {
        if (empty($docId)) return [];
        $docDir = self::getDocDir($docId);
        $files = @scandir($docDir) ?: [];
        $revisions = [];
        foreach ($files as $f) {
            if (substr($f, -5) !== '.json') continue;
            $data = json_decode(@file_get_contents($docDir . '/' . $f), true);
            if (is_array($data)) {
                $revisions[] = $data;
            }
        }
        usort($revisions, function ($a, $b) {
            return strcmp($b['revId'] ?? '', $a['revId'] ?? '');
        });
        return $revisions;
    }

    public static function getRevision(string $docId, string $revId): ?array {
        $safeRev = basename($revId);
        $docDir = self::getDocDir($docId);
        $metaFile = $docDir . '/' . $safeRev . '.json';
        $bakFile = $docDir . '/' . $safeRev . '.bak';
        if (!is_file($metaFile) || !is_file($bakFile)) {
            return null;
        }
        $meta = json_decode(@file_get_contents($metaFile), true);
        if (!is_array($meta)) return null;
        $meta['bakPath'] = $bakFile;
        $meta['content'] = @file_get_contents($bakFile);
        return $meta;
    }

    public static function pruneRevisions(string $docId, int $maxKeep): void {
        $revisions = self::listRevisions($docId);
        if (count($revisions) <= $maxKeep) return;
        $docDir = self::getDocDir($docId);
        $toDelete = array_slice($revisions, $maxKeep);
        foreach ($toDelete as $rev) {
            $revId = basename($rev['revId'] ?? '');
            if ($revId === '') continue;
            @unlink($docDir . '/' . $revId . '.json');
            @unlink($docDir . '/' . $revId . '.bak');
        }
    }
}
