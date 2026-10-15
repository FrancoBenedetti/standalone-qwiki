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
}
