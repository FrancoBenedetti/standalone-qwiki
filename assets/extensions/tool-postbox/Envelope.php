<?php
namespace Qwiki\Extension\Postbox;

use Qwiki\Core\Config;
use Qwiki\Core\SubwikiManager;

class Envelope {
    const PROTOCOL_VERSION = '1.0';

    /**
     * Allowed document types
     */
    const ALLOWED_DOC_TYPES = ['markdown', 'md', 'html', 'pdf', 'gdoc', 'form'];

    /**
     * Allowed media extensions for embedded asset transfer
     */
    const ALLOWED_ASSET_EXTS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'mp4', 'webm', 'pdf', 'txt', 'csv'];

    /**
     * Ensure the staging inbox exists and is protected from direct browser access.
     */
    public static function ensureInboxDir(string $baseDir): string {
        $postboxDir = $baseDir . '/uploads/.postbox';
        $inboxDir = $postboxDir . '/inbox';

        if (!is_dir($inboxDir)) {
            @mkdir($inboxDir, 0755, true);
        }

        // Write .htaccess if missing to protect staging payloads
        $htaccess = $postboxDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            $htContent = "# Prevent direct access to postbox payloads\n"
                       . "<IfModule !mod_authz_core.c>\n"
                       . "    Order deny,allow\n"
                       . "    Deny from all\n"
                       . "</IfModule>\n"
                       . "<IfModule mod_authz_core.c>\n"
                       . "    Require all denied\n"
                       . "</IfModule>\n";
            @file_put_contents($htaccess, $htContent);
        }

        // Empty index.html for non-Apache servers
        $indexHtml = $postboxDir . '/index.html';
        if (!file_exists($indexHtml)) {
            @file_put_contents($indexHtml, '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body></body></html>');
        }

        return $inboxDir;
    }

    /**
     * Detect MIME type safely based on file extension
     */
    public static function detectMimeType(string $path): string {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'txt' => 'text/plain',
            'csv' => 'text/csv'
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }

    /**
     * Extract referenced local uploads from document content.
     */
    public static function extractReferencedAssets(string $content, string $baseDir): array {
        $assets = [];
        $patterns = [
            '#uploads/(images|files)/[a-zA-Z0-9_\-\./]+#i',
            '#uploads/[a-zA-Z0-9_\-\.]+\.(png|jpe?g|gif|webp|svg|pdf|mp4|webm)#i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[0] as $match) {
                    $relPath = ltrim(str_replace('\\', '/', $match), '/');
                    // Prevent path traversal
                    if (strpos($relPath, '..') !== false || strpos($relPath, "\0") !== false) {
                        continue;
                    }

                    $ext = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));
                    if (!in_array($ext, self::ALLOWED_ASSET_EXTS, true)) {
                        continue;
                    }

                    $fullPath = $baseDir . '/' . $relPath;
                    if (file_exists($fullPath) && is_file($fullPath) && !isset($assets[$relPath])) {
                        // Max 25MB per embedded asset
                        $size = filesize($fullPath);
                        if ($size <= 25 * 1024 * 1024) {
                            $raw = file_get_contents($fullPath);
                            $assets[$relPath] = [
                                'rel_path' => $relPath,
                                'basename' => basename($relPath),
                                'mime' => self::detectMimeType($fullPath),
                                'size' => $size,
                                'sha1' => sha1($raw),
                                'data' => base64_encode($raw)
                            ];
                        }
                    }
                }
            }
        }
        return array_values($assets);
    }

    /**
     * Create a transfer envelope package for one or more documents.
     */
    public static function createPackage(array $docs, string $baseDir, array $config): array {
        $batchId = 'pb_' . bin2hex(random_bytes(8));
        $packagedDocs = [];

        foreach ($docs as $doc) {
            $slug = $doc['slug'] ?? '';
            $title = $doc['title'] ?? $slug;
            $type = strtolower($doc['type'] ?? 'markdown');
            if ($type === 'md') $type = 'markdown';

            $fileRel = $doc['file'] ?? '';
            $content = '';
            $assets = [];

            if (!empty($fileRel)) {
                $safeRel = ltrim(str_replace('\\', '/', $fileRel), '/');
                $fullPath = $baseDir . '/' . $safeRel;
                if (file_exists($fullPath) && is_file($fullPath)) {
                    if (in_array($type, ['markdown', 'html'])) {
                        $content = file_get_contents($fullPath);
                        $assets = self::extractReferencedAssets($content, $baseDir);
                    } elseif ($type === 'pdf') {
                        $content = base64_encode(file_get_contents($fullPath));
                    }
                }
            }

            $packagedDocs[] = [
                'id' => $doc['id'] ?? $slug,
                'slug' => $slug,
                'title' => $title,
                'type' => $type,
                'description' => $doc['description'] ?? '',
                'image' => $doc['image'] ?? '',
                'theme' => $doc['theme'] ?? '',
                'readOnly' => !empty($doc['readOnly']),
                'tags' => $doc['tags'] ?? [],
                'url' => $doc['url'] ?? '',
                'editUrl' => $doc['editUrl'] ?? '',
                'content' => $content,
                'assets' => $assets
            ];
        }

        return [
            'version' => self::PROTOCOL_VERSION,
            'batch_id' => $batchId,
            'created_at' => date('c'),
            'sender' => [
                'title' => $config['title'] ?? 'Standalone Qwiki',
                'base_url' => Config::getBaseUrl(),
                'is_subwiki' => Config::isSubwiki()
            ],
            'documents' => $packagedDocs
        ];
    }

    /**
     * Save an envelope package into the local postbox inbox staging area.
     */
    public static function saveStagedEnvelope(string $baseDir, array $envelope): array {
        $batchId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $envelope['batch_id'] ?? ('pb_' . uniqid()));
        if (empty($batchId)) {
            return ['success' => false, 'error' => 'Invalid batch ID in envelope'];
        }

        if (empty($envelope['documents']) || !is_array($envelope['documents'])) {
            return ['success' => false, 'error' => 'Envelope contains no valid documents'];
        }

        $inboxDir = self::ensureInboxDir($baseDir);
        $filePath = $inboxDir . '/' . $batchId . '.json';

        $json = json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($filePath, $json, LOCK_EX) === false) {
            return ['success' => false, 'error' => 'Failed to stage envelope in postbox inbox'];
        }

        return ['success' => true, 'batch_id' => $batchId];
    }

    /**
     * List all pending envelopes in the staging inbox.
     */
    public static function listInboxEnvelopes(string $baseDir): array {
        $inboxDir = self::ensureInboxDir($baseDir);
        $files = glob($inboxDir . '/*.json');
        if (!$files) return [];

        $items = [];
        foreach ($files as $file) {
            $data = @json_decode(file_get_contents($file), true);
            if (!is_array($data) || empty($data['batch_id'])) {
                continue;
            }

            $docsSummary = [];
            foreach ($data['documents'] ?? [] as $idx => $d) {
                $docsSummary[] = [
                    'index' => $idx,
                    'id' => $d['id'] ?? ($d['slug'] ?? ('doc_' . $idx)),
                    'title' => $d['title'] ?? 'Untitled Document',
                    'slug' => $d['slug'] ?? '',
                    'type' => $d['type'] ?? 'markdown',
                    'description' => $d['description'] ?? '',
                    'theme' => $d['theme'] ?? '',
                    'readOnly' => !empty($d['readOnly']),
                    'category_hint' => $d['category_hint'] ?? ($data['category_hint'] ?? ''),
                    'tags' => $d['tags'] ?? [],
                    'asset_count' => count($d['assets'] ?? []),
                    'content_size' => strlen($d['content'] ?? '')
                ];
            }

            $items[] = [
                'batch_id' => $data['batch_id'],
                'created_at' => $data['created_at'] ?? '',
                'category_hint' => $data['category_hint'] ?? '',
                'sender' => $data['sender'] ?? ['title' => 'Unknown Wiki'],
                'doc_count' => count($docsSummary),
                'documents' => $docsSummary
            ];
        }

        // Sort latest first
        usort($items, function($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });

        return $items;
    }

    /**
     * Retrieve a specific pending envelope.
     */
    public static function getInboxEnvelope(string $baseDir, string $batchId): ?array {
        $cleanId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $batchId);
        $inboxDir = self::ensureInboxDir($baseDir);
        $filePath = $inboxDir . '/' . $cleanId . '.json';

        if (!file_exists($filePath)) return null;
        $data = json_decode(file_get_contents($filePath), true);
        return is_array($data) ? $data : null;
    }

    /**
     * Reject and remove an envelope or a specific document within an envelope.
     */
    public static function rejectEnvelope(string $baseDir, string $batchId, $docIndex = null): array {
        $cleanId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $batchId);
        $inboxDir = self::ensureInboxDir($baseDir);
        $filePath = $inboxDir . '/' . $cleanId . '.json';

        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => 'Batch not found in postbox inbox'];
        }

        if ($docIndex === null || $docIndex === '' || $docIndex === 'all') {
            @unlink($filePath);
            return ['success' => true, 'message' => 'Batch rejected and removed from inbox'];
        }

        $data = json_decode(file_get_contents($filePath), true);
        if (isset($data['documents'][(int)$docIndex])) {
            array_splice($data['documents'], (int)$docIndex, 1);
            if (empty($data['documents'])) {
                @unlink($filePath);
            } else {
                file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
            }
            return ['success' => true, 'message' => 'Document rejected from batch'];
        }

        return ['success' => false, 'error' => 'Document index not found in batch'];
    }

    /**
     * Ensure an HTML document contains a <base href="..."> pointing to the wiki root
     * so that relative assets (e.g. uploads/images/...) resolve correctly in the iframe.
     */
    public static function ensureHtmlBaseHref(string $content, string $filePath): string {
        if (empty($content) || preg_match('/<base\s+[^>]*href=/i', $content)) {
            return $content;
        }

        $dir = dirname(str_replace('\\', '/', $filePath));
        $segments = array_filter(explode('/', $dir), function($s) { return $s !== '' && $s !== '.'; });
        $depth = count($segments);
        $relativeBase = $depth > 0 ? str_repeat('../', $depth) : './';

        if (preg_match('/<head[^>]*>/i', $content)) {
            return preg_replace('/(<head[^>]*>)/i', "$1\n    <base href=\"{$relativeBase}\">", $content, 1);
        }

        return "<base href=\"{$relativeBase}\">\n" . $content;
    }

    /**
     * Sanitize markdown content against high-risk executable tags.
     * Note: HTML documents are isolated inside sandboxed iframes (page-html extension)
     * and their meta, script, form, and event handlers must remain fully intact.
     */
    public static function sanitizeContent(string $content, string $type): string {
        if ($type === 'pdf' || $type === 'html') {
            return $content;
        }

        // For Markdown: strip entire script blocks (tags and script body)
        $content = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $content);
        $content = preg_replace('/<script\b[^>]*\/?>/is', '', $content);

        $dangerous_tags = ['applet', 'meta', 'base', 'form', 'embed', 'object'];
        foreach ($dangerous_tags as $tag) {
            $content = preg_replace('/<\/?\s*' . $tag . '\b[^>]*>/is', '', $content);
        }
        $content = preg_replace('/on[a-z]+\s*=\s*(["\']).*?\1/is', '', $content);
        $content = preg_replace('/on[a-z]+\s*=\s*[^>\s]+/is', '', $content);
        $content = preg_replace('/href\s*=\s*(["\']?)javascript:.*?\1/is', 'href="#"', $content);

        return $content;
    }

    /**
     * Ingest a staged document into the destination wiki navigation and filesystem.
     */
    public static function ingestDocument(
        string $baseDir,
        array &$config,
        string $batchId,
        $docIdentifier,
        string $targetBookId,
        array $overrides = []
    ): array {
        $envelope = self::getInboxEnvelope($baseDir, $batchId);
        if (!$envelope) {
            return ['success' => false, 'error' => 'Envelope batch not found in inbox'];
        }

        // Find document by index or ID
        $doc = null;
        $docIndex = null;
        if (is_numeric($docIdentifier) && isset($envelope['documents'][(int)$docIdentifier])) {
            $docIndex = (int)$docIdentifier;
            $doc = $envelope['documents'][$docIndex];
        } else {
            foreach ($envelope['documents'] as $i => $d) {
                if (($d['id'] ?? '') === $docIdentifier || ($d['slug'] ?? '') === $docIdentifier) {
                    $docIndex = $i;
                    $doc = $d;
                    break;
                }
            }
        }

        if (!$doc) {
            return ['success' => false, 'error' => 'Document not found in envelope batch'];
        }

        $targetBookId = trim($targetBookId);
        if (empty($targetBookId)) {
            return ['success' => false, 'error' => 'Target category (book ID) is required'];
        }

        // 1. Locate existing category and collect existing slugs
        $findCategory = function(array $books, string $targetId) use (&$findCategory) {
            foreach ($books as $b) {
                if (($b['id'] ?? '') === $targetId) {
                    return $b;
                }
                if (!empty($b['items']) && is_array($b['items'])) {
                    $found = $findCategory($b['items'], $targetId);
                    if ($found) return $found;
                }
            }
            return null;
        };

        $existingCategory = $findCategory($config['books'] ?? [], $targetBookId);
        $categoryExists = ($existingCategory !== null);

        // Ensure category physical directory exists in content/
        $catDir = $baseDir . '/content/' . $targetBookId;
        if (!is_dir($catDir)) {
            @mkdir($catDir, 0755, true);
        }

        // 2. Prepare title and slug
        $title = !empty($overrides['title']) ? trim($overrides['title']) : ($doc['title'] ?? 'Transferred Document');
        $rawSlug = !empty($overrides['slug']) ? trim($overrides['slug']) : ($doc['slug'] ?? Config::makeSlug($title));
        $baseSlug = Config::makeSlug($rawSlug);
        if (empty($baseSlug)) {
            $baseSlug = 'doc-' . uniqid();
        }

        $docType = strtolower(!empty($overrides['type']) ? $overrides['type'] : ($doc['type'] ?? 'markdown'));
        if ($docType === 'md') $docType = 'markdown';
        if (!in_array($docType, self::ALLOWED_DOC_TYPES, true)) {
            $docType = 'markdown';
        }

        $ext = 'md';
        if ($docType === 'html') $ext = 'html';
        elseif ($docType === 'pdf') $ext = 'pdf';
        elseif ($docType === 'form') $ext = 'form.json';

        // Ensure unique slug and filename in target category
        $slug = $baseSlug;
        $counter = 1;
        $existingSlugs = [];
        if ($existingCategory && !empty($existingCategory['items'])) {
            foreach ($existingCategory['items'] as $item) {
                if (!empty($item['slug'])) $existingSlugs[] = $item['slug'];
            }
        }

        $targetAbsFile = $catDir . '/' . $slug . '.' . $ext;
        while (in_array($slug, $existingSlugs, true) || file_exists($targetAbsFile)) {
            $slug = $baseSlug . '-' . $counter;
            $targetAbsFile = $catDir . '/' . $slug . '.' . $ext;
            $counter++;
        }

        $targetRelFile = 'content/' . $targetBookId . '/' . $slug . '.' . $ext;

        // 3. Extract and remap referenced assets
        $content = $doc['content'] ?? '';
        $uploadsImagesDir = $baseDir . '/uploads/images';
        if (!is_dir($uploadsImagesDir)) {
            @mkdir($uploadsImagesDir, 0755, true);
        }

        if (!empty($doc['assets']) && is_array($doc['assets'])) {
            foreach ($doc['assets'] as $asset) {
                if (empty($asset['data']) || empty($asset['rel_path'])) continue;
                $origRel = $asset['rel_path'];
                $origBase = !empty($asset['basename']) ? basename($asset['basename']) : basename($origRel);
                $origExt = strtolower(pathinfo($origBase, PATHINFO_EXTENSION));

                if (!in_array($origExt, self::ALLOWED_ASSET_EXTS, true)) continue;

                $assetBinary = base64_decode($asset['data']);
                if ($assetBinary === false) continue;

                $destName = $origBase;
                $destAbs = $uploadsImagesDir . '/' . $destName;

                // Collision detection: if file exists and content differs, append postbox hash
                if (file_exists($destAbs)) {
                    if (sha1_file($destAbs) !== sha1($assetBinary)) {
                        $nameOnly = pathinfo($origBase, PATHINFO_FILENAME);
                        $shortHash = substr(sha1($assetBinary), 0, 6);
                        $destName = $nameOnly . '-pb-' . $shortHash . '.' . $origExt;
                        $destAbs = $uploadsImagesDir . '/' . $destName;
                    }
                }

                @file_put_contents($destAbs, $assetBinary, LOCK_EX);

                // Remap asset link in document content
                $newRelPath = 'uploads/images/' . $destName;
                if ($origRel !== $newRelPath && in_array($docType, ['markdown', 'html'])) {
                    $content = str_replace($origRel, $newRelPath, $content);
                    // Also replace urlencoded variations
                    $content = str_replace(urlencode($origRel), $newRelPath, $content);
                    $trimmedRel = ltrim($origRel, './');
                    if ($trimmedRel !== $origRel && !empty($trimmedRel)) {
                        $content = str_replace($trimmedRel, $newRelPath, $content);
                    }
                }
            }
        }

        // 4. Save Document File
        if ($docType === 'pdf') {
            $pdfData = base64_decode($content);
            if ($pdfData === false || file_put_contents($targetAbsFile, $pdfData, LOCK_EX) === false) {
                return ['success' => false, 'error' => 'Failed to write PDF file to storage'];
            }
        } elseif ($docType === 'html') {
            $htmlContent = self::ensureHtmlBaseHref($content, $targetRelFile);
            if (file_put_contents($targetAbsFile, $htmlContent, LOCK_EX) === false) {
                return ['success' => false, 'error' => 'Failed to write HTML file to storage'];
            }
        } else {
            $sanitizedContent = self::sanitizeContent($content, $docType);
            if (file_put_contents($targetAbsFile, $sanitizedContent, LOCK_EX) === false) {
                return ['success' => false, 'error' => 'Failed to write document file to storage'];
            }
            if ($docType === 'form') {
                $subFile = preg_replace('/\.form\.json$/i', '.submissions.json', $targetAbsFile);
                if (!file_exists($subFile)) {
                    @file_put_contents($subFile, '', LOCK_EX);
                }
            }
        }

        // 5. Build Chapter Navigation Node
        $newChapter = [
            'title' => $title,
            'slug' => $slug,
            'type' => $docType,
            'file' => $targetRelFile
        ];

        // Metadata overrides or original doc metadata
        $description = isset($overrides['description']) ? trim($overrides['description']) : ($doc['description'] ?? '');
        if (!empty($description)) $newChapter['description'] = $description;

        $theme = isset($overrides['theme']) ? trim($overrides['theme']) : ($doc['theme'] ?? '');
        if (!empty($theme)) $newChapter['theme'] = $theme;

        $readOnly = isset($overrides['readOnly']) ? !empty($overrides['readOnly']) : !empty($doc['readOnly']);
        if ($readOnly) $newChapter['readOnly'] = true;

        if (!empty($doc['url'])) $newChapter['url'] = $doc['url'];
        if (!empty($doc['editUrl'])) $newChapter['editUrl'] = $doc['editUrl'];
        $inserted = false;
        $insertIntoCategory = function(&$books, $targetId, $chapter) use (&$inserted, &$insertIntoCategory) {
            foreach ($books as &$b) {
                if (($b['id'] ?? '') === $targetId) {
                    if (!isset($b['items']) || !is_array($b['items'])) {
                        $b['items'] = [];
                    }
                    $b['items'][] = $chapter;
                    $inserted = true;
                    return true;
                }
                if (!empty($b['items']) && is_array($b['items'])) {
                    if ($insertIntoCategory($b['items'], $targetId, $chapter)) {
                        return true;
                    }
                }
            }
            return false;
        };

        if ($categoryExists) {
            $insertIntoCategory($config['books'], $targetBookId, $newChapter);
        }

        if (!$inserted) {
            $newTitle = !empty($overrides['new_category_title']) ? trim($overrides['new_category_title']) : ucfirst($targetBookId);
            $config['books'][] = [
                'id' => $targetBookId,
                'title' => $newTitle,
                'type' => 'folder',
                'items' => [$newChapter]
            ];
        }

        // Atomic save of config
        if (!Config::save($config)) {
            @unlink($targetAbsFile);
            return ['success' => false, 'error' => 'Failed to persist navigation changes to qwiki.json'];
        }

        // 6. Remove document from inbox batch or delete batch if empty
        array_splice($envelope['documents'], $docIndex, 1);
        $cleanBatchId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $batchId);
        $inboxFile = self::ensureInboxDir($baseDir) . '/' . $cleanBatchId . '.json';

        if (empty($envelope['documents'])) {
            @unlink($inboxFile);
        } else {
            file_put_contents($inboxFile, json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        }

        return [
            'success' => true,
            'message' => "Document '{$title}' successfully ingested into category '{$targetBookId}'",
            'bookId' => $targetBookId,
            'slug' => $slug,
            'file' => $targetRelFile
        ];
    }
}
