<?php
namespace Qwiki\Core;

class DocumentService {

    /**
     * Updates or injects the data-qwiki-status attribute on the root <html> tag of a document,
     * and optionally strips review dockets, boundary comment blocks, and draft watermarks permanently on publish.
     *
     * @param string $absFilePath Absolute filesystem path to the target HTML file
     * @param string $newStatus New workflow status ('draft', 'review', 'published', 'archived')
     * @param bool $cleanSource If true and status is 'published', permanently purges draft nodes
     * @return bool
     */
    public static function sanitizeHtmlDocumentStatus(string $absFilePath, string $newStatus, bool $cleanSource = false): bool {
        if (!file_exists($absFilePath) || !is_file($absFilePath) || !is_writable($absFilePath)) {
            return false;
        }

        $content = file_get_contents($absFilePath);
        if ($content === false) {
            return false;
        }

        $cleanStatus = htmlspecialchars(strtolower(trim($newStatus)), ENT_QUOTES, 'UTF-8');

        // 1. Update or inject data-qwiki-status on the <html ...> element
        if (preg_match('/<html\b([^>]*)data-qwiki-status=["\'][^"\']*["\']([^>]*)>/i', $content)) {
            $content = preg_replace('/(<html\b[^>]*)data-qwiki-status=["\'][^"\']*["\']([^>]*>)/i', '${1}data-qwiki-status="' . $cleanStatus . '"${2}', $content);
        } elseif (preg_match('/<html\b([^>]*)>/i', $content)) {
            $content = preg_replace('/<html\b([^>]*)>/i', '<html${1} data-qwiki-status="' . $cleanStatus . '">', $content);
        }

        // 2. If cleanSource is active and status is 'published', remove draft nodes & watermarks permanently
        if ($cleanSource && $newStatus === 'published') {
            // A. Remove explicit comment boundary blocks first (<!-- QWIKI_DRAFT_START -->...<!-- QWIKI_DRAFT_END -->)
            $content = preg_replace('/<!--\s*QWIKI_DRAFT_START\s*-->[\s\S]*?<!--\s*QWIKI_DRAFT_END\s*-->\s*/is', '', $content);

            // B. Remove full review docket banners (including multi-div/table contents up to next major section)
            $content = preg_replace('/<div\b[^>]*(?:id=["\']gerhard-review-docket["\']|class=["\'][^"\']*\b(?:review-docket-banner|qwiki-review-docket)\b[^"\']*["\'])[^>]*>[\s\S]*?<\/div>\s*(?=<div\b[^>]*\b(?:watermark-draft|qwiki-watermark)\b|<header\b|<main\b|<div\b[^>]*\bcontainer\b)/is', '', $content);

            // C. Remove watermarks
            $content = preg_replace('/<(?:div|section|aside|header|footer|p|span)\b[^>]*(?:\bclass=["\'][^"\']*\b(?:watermark-draft|qwiki-watermark)\b[^"\']*["\'])[^>]*>[\s\S]*?<\/(?:div|section|aside|header|footer|p|span)>\s*/is', '', $content);

            // D. Remove orphan docket tables and checklists if previously partially stripped
            $content = preg_replace('/<table\b[^>]*\b(?:docket-table)\b[^>]*>[\s\S]*?<\/table>\s*/is', '', $content);
            $content = preg_replace('/<div\b[^>]*>(?:\s*<strong>\s*Reviewer Audit Checklist:[\s\S]*?)<\/div>\s*/is', '', $content);
            $content = preg_replace('/<div\b[^>]*\b(?:docket-header|docket-checklist|docket-feedback-box)\b[^>]*>[\s\S]*?<\/div>\s*/is', '', $content);

            // E. Remove any other elements marked with data-qwiki-draft or qwiki-draft
            $content = preg_replace('/<(?:div|section|p|span|aside|header|footer|article|table)\b[^>]*(?:\bdata-qwiki-draft\b|\bclass=["\'][^"\']*\bqwiki-draft\b[^"\']*["\'])[^>]*>[\s\S]*?<\/(?:div|section|p|span|aside|header|footer|article|table)>\s*/is', '', $content);
        }

        return (file_put_contents($absFilePath, $content) !== false);
    }

    /**
     * Streams a document in raw format (e.g. HTML or PDF) with proper Content-Type headers
     * and runtime base URL normalization for secure iframe embedding and full-tab viewing.
     * Enforces that all document access must pass through Qwiki access policies.
     *
     * @param array $chapter Chapter/document metadata
     * @param string $baseDir Base installation directory
     * @param string $baseUrl Base URL of the wiki installation
     * @return void
     */
    public static function streamDocumentRaw(array $chapter, string $baseDir, string $baseUrl): void {
        $docType = strtolower($chapter['type'] ?? 'markdown');
        $relFile = $chapter['file'] ?? '';
        $filePath = !empty($relFile) ? Config::safePath($baseDir, $relFile) : null;

        if (!$filePath || !file_exists($filePath)) {
            http_response_code(404);
            echo "<!DOCTYPE html><html><head><title>File Not Found</title></head><body><h1>404 Not Found</h1><p>The requested document file could not be found.</p></body></html>";
            exit;
        }

        if ($docType === 'html' || preg_match('/\.(html|htm)$/i', $filePath)) {
            $htmlContent = file_get_contents($filePath);
            $normalizedBaseUrl = rtrim($baseUrl, '/') . '/';

            // Ensure base href points to the application root for proper resolution of uploads/ and assets/
            if (preg_match('/<base\s+[^>]*href=["\'][^"\']*["\'][^>]*>/i', $htmlContent)) {
                $htmlContent = preg_replace('/<base\s+[^>]*href=["\'][^"\']*["\'][^>]*>/i', '<base href="' . htmlspecialchars($normalizedBaseUrl) . '">', $htmlContent, 1);
            } elseif (preg_match('/<head\b[^>]*>/i', $htmlContent)) {
                $htmlContent = preg_replace('/(<head\b[^>]*>)/i', "$1\n    <base href=\"" . htmlspecialchars($normalizedBaseUrl) . "\">", $htmlContent, 1);
            } else {
                $htmlContent = '<base href="' . htmlspecialchars($normalizedBaseUrl) . "\">\n" . $htmlContent;
            }

            // In hosted multitenant mode, rewrite relative assets/ references to QWIKI_ASSETS_URL if defined
            if (defined('QWIKI_ASSETS_URL')) {
                $assetsUrl = rtrim(QWIKI_ASSETS_URL, '/') . '/';
                $htmlContent = preg_replace('/(\b(?:src|href)=["\'])assets\//i', '$1' . $assetsUrl, $htmlContent);
            }

            header('Content-Type: text/html; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            echo $htmlContent;
            exit;
        }

        if ($docType === 'pdf' || preg_match('/\.pdf$/i', $filePath)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . rawurlencode(basename($filePath)) . '"');
            header('Content-Length: ' . filesize($filePath));
            header('X-Content-Type-Options: nosniff');
            readfile($filePath);
            exit;
        }

        if ($docType === 'markdown' || $docType === 'md' || preg_match('/\.(md|markdown|txt)$/i', $filePath)) {
            header('Content-Type: text/markdown; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            readfile($filePath);
            exit;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $filePath) : 'application/octet-stream';
        if ($finfo) {
            finfo_close($finfo);
        }

        header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($filePath));
        header('X-Content-Type-Options: nosniff');
        readfile($filePath);
        exit;
    }
}
