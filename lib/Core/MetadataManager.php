<?php
namespace Qwiki\Core;

class MetadataManager {

    /**
     * Resolve SEO, OpenGraph and Twitter card metadata for active document
     *
     * @param array|null $activeChapter
     * @param array|null $activeBook
     * @param array $config
     * @param string $baseUrl
     * @param string $baseDir
     * @param bool $isShareMode
     * @param string $shareKey
     * @param string|null $shareError
     * @param string $rawMarkdownContent
     * @return array
     */
    public static function resolveArticleMetadata(
        ?array $activeChapter,
        ?array $activeBook,
        array $config,
        string $baseUrl,
        string $baseDir,
        bool $isShareMode = false,
        string $shareKey = '',
        ?string $shareError = null,
        string $rawMarkdownContent = ''
    ): array {
        if ($isShareMode && !empty($shareKey)) {
            $canonicalUrl = rtrim($baseUrl, '/') . '/?share=' . urlencode($shareKey);
        } elseif ($activeBook && $activeChapter) {
            $canonicalUrl = rtrim($baseUrl, '/') . '/' . urlencode($activeBook['id']) . '/' . urlencode($activeChapter['slug'] ?? '');
        } else {
            $canonicalUrl = $baseUrl;
        }

        $siteTitle = $config['title'] ?? 'Standalone Qwiki';
        $docTitle = $activeChapter['title'] ?? ($shareError ? 'Document Share' : 'Documentation');
        $ogTitle = $docTitle . ' - ' . $siteTitle;

        $rawDesc = trim($activeChapter['description'] ?? '');
        $htmlFileContent = '';

        if (empty($rawDesc)) {
            $chapterType = strtolower($activeChapter['type'] ?? 'markdown');
            $docFilePath = (!empty($activeChapter) && !empty($activeChapter['file'])) ? ($baseDir . '/' . $activeChapter['file']) : '';

            if (($chapterType === 'markdown' || $chapterType === 'md') && !empty($rawMarkdownContent)) {
                $cleanSnippet = preg_replace('/```[\s\S]*?```/', '', $rawMarkdownContent);
                $cleanSnippet = preg_replace('/!\[.*?\]\(.*?\)/', '', $cleanSnippet);
                $cleanSnippet = preg_replace('/\[(.*?)\]\(.*?\)/', '$1', $cleanSnippet);
                $cleanSnippet = preg_replace('/[#*_>`~=-]/', '', $cleanSnippet);
                $cleanSnippet = trim(preg_replace('/\s+/', ' ', strip_tags($cleanSnippet)));
                if (!empty($cleanSnippet)) {
                    $rawDesc = (mb_strlen($cleanSnippet) > 160) ? mb_substr($cleanSnippet, 0, 157) . '...' : $cleanSnippet;
                }
            } elseif (($chapterType === 'html' || preg_match('/\.(html|htm)$/i', $docFilePath)) && !empty($docFilePath) && file_exists($docFilePath)) {
                $htmlFileContent = @file_get_contents($docFilePath) ?: '';
                if (!empty($htmlFileContent)) {
                    // 1. Check for explicit meta description in the HTML file itself
                    if (preg_match('/<meta\s+[^>]*(?:name=["\']description["\']|property=["\']og:description["\'])[^>]*content=["\']([^"\']+)["\']/i', $htmlFileContent, $mDesc) ||
                        preg_match('/<meta\s+[^>]*content=["\']([^"\']+)["\'][^>]*(?:name=["\']description["\']|property=["\']og:description["\'])/i', $htmlFileContent, $mDesc)) {
                        $rawDesc = trim(html_entity_decode($mDesc[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    }
                    // 2. Fall back to body text, stripping scripts, styles, and markup
                    if (empty($rawDesc)) {
                        $cleanHtml = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $htmlFileContent);
                        $cleanHtml = preg_replace('/<style\b[^>]*>[\s\S]*?<\/style>/i', '', $cleanHtml);
                        $cleanHtml = preg_replace('/<head\b[^>]*>[\s\S]*?<\/head>/i', '', $cleanHtml);
                        $cleanText = trim(preg_replace('/\s+/', ' ', strip_tags($cleanHtml)));
                        if (!empty($cleanText)) {
                            $rawDesc = (mb_strlen($cleanText) > 160) ? mb_substr($cleanText, 0, 157) . '...' : $cleanText;
                        }
                    }
                }
            }
        }

        if (empty($rawDesc)) {
            $rawDesc = $config['shareDescription'] ?? 'Read this documentation on Qwiki.';
        }

        $rawImage = trim($activeChapter['image'] ?? $config['shareImageUrl'] ?? $config['logoUrl'] ?? '');
        $ogImage = '';

        if (!empty($rawImage)) {
            if (preg_match('#^https?://#i', $rawImage)) {
                $ogImage = $rawImage;
            } else {
                $ogImage = rtrim($baseUrl, '/') . '/' . ltrim($rawImage, '/');
            }
        } elseif (!empty($rawMarkdownContent)) {
            if (preg_match('/!\[.*?\]\((https?:\/\/[^\s\)]+|[^\s\)]+)\)/i', $rawMarkdownContent, $imgMatch)) {
                $firstImg = trim($imgMatch[1]);
                if (preg_match('#^https?://#i', $firstImg)) {
                    $ogImage = $firstImg;
                } else {
                    $ogImage = rtrim($baseUrl, '/') . '/' . ltrim($firstImg, '/');
                }
            }
        } elseif (!empty($htmlFileContent)) {
            if (preg_match('/<meta\s+[^>]*(?:property=["\']og:image["\']|name=["\']twitter:image["\'])[^>]*content=["\']([^"\']+)["\']/i', $htmlFileContent, $mImg) ||
                preg_match('/<meta\s+[^>]*content=["\']([^"\']+)["\'][^>]*(?:property=["\']og:image["\']|name=["\']twitter:image["\'])/i', $htmlFileContent, $mImg)) {
                $htmlImg = trim($mImg[1]);
                if (preg_match('#^https?://#i', $htmlImg)) {
                    $ogImage = $htmlImg;
                } else {
                    $ogImage = rtrim($baseUrl, '/') . '/' . ltrim($htmlImg, '/');
                }
            }
        }

        $articleModifiedTime = '';
        if ($activeChapter && !empty($activeChapter['file'])) {
            $docFilePath = $baseDir . '/' . $activeChapter['file'];
            if (file_exists($docFilePath)) {
                $articleModifiedTime = date('c', filemtime($docFilePath));
            }
        }

        $categoryTitle = $activeBook['title'] ?? '';
        $articleTags = [];
        if (!empty($activeChapter['tags'])) {
            $articleTags = is_array($activeChapter['tags']) ? $activeChapter['tags'] : explode(',', (string)$activeChapter['tags']);
        }

        return [
            'canonicalUrl' => $canonicalUrl,
            'siteTitle' => $siteTitle,
            'docTitle' => $docTitle,
            'ogTitle' => $ogTitle,
            'description' => $rawDesc,
            'ogImage' => $ogImage,
            'categoryTitle' => $categoryTitle,
            'articleModifiedTime' => $articleModifiedTime,
            'articleTags' => $articleTags,
        ];
    }

    /**
     * Render Open Graph & Twitter Card meta HTML tags
     *
     * @param array $meta Result from resolveArticleMetadata()
     * @return string
     */
    public static function renderTags(array $meta): string {
        $html = '    <link rel="canonical" href="' . htmlspecialchars($meta['canonicalUrl']) . '">' . "\n";
        $html .= '    <meta property="og:site_name" content="' . htmlspecialchars($meta['siteTitle']) . '">' . "\n";
        $html .= '    <meta property="og:type" content="article">' . "\n";
        $html .= '    <meta property="og:title" content="' . htmlspecialchars($meta['ogTitle']) . '">' . "\n";
        $html .= '    <meta property="og:description" content="' . htmlspecialchars($meta['description']) . '">' . "\n";
        $html .= '    <meta property="og:url" content="' . htmlspecialchars($meta['canonicalUrl']) . '">' . "\n";

        if (!empty($meta['ogImage'])) {
            $html .= '    <meta property="og:image" content="' . htmlspecialchars($meta['ogImage']) . '">' . "\n";
            $html .= '    <meta name="twitter:image" content="' . htmlspecialchars($meta['ogImage']) . '">' . "\n";
        }

        if (!empty($meta['categoryTitle'])) {
            $html .= '    <meta property="article:section" content="' . htmlspecialchars($meta['categoryTitle']) . '">' . "\n";
        }

        if (!empty($meta['articleModifiedTime'])) {
            $html .= '    <meta property="article:modified_time" content="' . htmlspecialchars($meta['articleModifiedTime']) . '">' . "\n";
            $html .= '    <meta property="og:updated_time" content="' . htmlspecialchars($meta['articleModifiedTime']) . '">' . "\n";
        }

        foreach ($meta['articleTags'] as $tag) {
            $t = trim($tag);
            if ($t !== '') {
                $html .= '    <meta property="article:tag" content="' . htmlspecialchars($t) . '">' . "\n";
            }
        }

        $html .= '    <meta name="twitter:card" content="summary_large_image">' . "\n";
        $html .= '    <meta name="twitter:title" content="' . htmlspecialchars($meta['ogTitle']) . '">' . "\n";
        $html .= '    <meta name="twitter:description" content="' . htmlspecialchars($meta['description']) . '">' . "\n";

        return $html;
    }
}
