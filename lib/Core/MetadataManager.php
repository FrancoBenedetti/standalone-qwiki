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
                $fmData = self::parseFrontmatter($rawMarkdownContent);
                if (empty($rawDesc) && !empty($fmData['metadata']['description'])) {
                    $rawDesc = (string)$fmData['metadata']['description'];
                }
                if (empty($rawDesc)) {
                    $cleanSnippet = $fmData['body'];
                    $cleanSnippet = preg_replace('/```[\s\S]*?```/', '', $cleanSnippet);
                    $cleanSnippet = preg_replace('/!\[.*?\]\(.*?\)/', '', $cleanSnippet);
                    $cleanSnippet = preg_replace('/\[(.*?)\]\(.*?\)/', '$1', $cleanSnippet);
                    $cleanSnippet = preg_replace('/[#*_>`~=-]/', '', $cleanSnippet);
                    $cleanSnippet = trim(preg_replace('/\s+/', ' ', strip_tags($cleanSnippet)));
                    if (!empty($cleanSnippet)) {
                        $rawDesc = (mb_strlen($cleanSnippet) > 160) ? mb_substr($cleanSnippet, 0, 157) . '...' : $cleanSnippet;
                    }
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
        if (empty($rawImage) && !empty($rawMarkdownContent) && ($chapterType === 'markdown' || $chapterType === 'md')) {
            $fmData = isset($fmData) ? $fmData : self::parseFrontmatter($rawMarkdownContent);
            if (!empty($fmData['metadata']['image'])) {
                $rawImage = trim((string)$fmData['metadata']['image']);
            } elseif (!empty($fmData['metadata']['cover'])) {
                $rawImage = trim((string)$fmData['metadata']['cover']);
            }
        }
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
        } elseif (!empty($rawMarkdownContent) && ($chapterType === 'markdown' || $chapterType === 'md')) {
            $fmData = isset($fmData) ? $fmData : self::parseFrontmatter($rawMarkdownContent);
            if (!empty($fmData['metadata']['tags'])) {
                $fmTags = $fmData['metadata']['tags'];
                $articleTags = is_array($fmTags) ? $fmTags : explode(',', (string)$fmTags);
            }
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

    /**
     * Parse leading YAML frontmatter from Markdown text
     *
     * @param string $content Raw markdown content
     * @return array [hasFrontmatter: bool, rawYaml: string, metadata: array, body: string]
     */
    public static function parseFrontmatter(string $content): array {
        // Strip UTF-8 BOM if present
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $pattern = '/^---[\r\n]+([\s\S]*?)[\r\n]+---[\r\n]*/';
        if (preg_match($pattern, $content, $matches)) {
            $rawYaml = $matches[1];
            $body = substr($content, strlen($matches[0]));
            $meta = self::parseYamlString($rawYaml);

            return [
                'hasFrontmatter' => true,
                'rawYaml' => $rawYaml,
                'metadata' => $meta,
                'body' => $body,
            ];
        }

        return [
            'hasFrontmatter' => false,
            'rawYaml' => '',
            'metadata' => [],
            'body' => $content,
        ];
    }

    /**
     * Lightweight YAML string parser for frontmatter blocks
     *
     * @param string $yaml
     * @return array
     */
    public static function parseYamlString(string $yaml): array {
        $lines = preg_split('/\r\n|\r|\n/', trim($yaml));
        $result = [];
        $currentKey = null;
        $isList = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || $trimmed[0] === '#') {
                continue;
            }

            // List item under current key: "  - item" or "- item"
            if (preg_match('/^-\s+(.*)$/', $trimmed, $mList) && $currentKey !== null) {
                if (!isset($result[$currentKey]) || !is_array($result[$currentKey]) || !$isList) {
                    $result[$currentKey] = [];
                    $isList = true;
                }
                $val = self::cleanYamlValue($mList[1]);
                $result[$currentKey][] = $val;
                continue;
            }

            // Key-value pair: "key: value" or "key:"
            if (preg_match('/^([a-zA-Z0-9_\-\.]+)\s*:\s*(.*)$/', $trimmed, $mKey)) {
                $key = $mKey[1];
                $val = trim($mKey[2]);
                $currentKey = $key;
                $isList = false;

                if ($val === '') {
                    $result[$key] = [];
                    $isList = true;
                } elseif (preg_match('/^\[(.*)\]$/', $val, $mInlineList)) {
                    // Inline array: [item1, "item2"]
                    $rawItems = explode(',', $mInlineList[1]);
                    $cleaned = [];
                    foreach ($rawItems as $rawItem) {
                        $ci = self::cleanYamlValue(trim($rawItem));
                        if ($ci !== '') {
                            $cleaned[] = $ci;
                        }
                    }
                    $result[$key] = $cleaned;
                } else {
                    $result[$key] = self::cleanYamlValue($val);
                }
                continue;
            }

            // If indented string continuing previous multiline value
            if ($currentKey !== null && isset($result[$currentKey]) && is_string($result[$currentKey]) && (strpos($line, '  ') === 0 || strpos($line, "\t") === 0)) {
                $result[$currentKey] .= ' ' . $trimmed;
            }
        }

        return $result;
    }

    /**
     * Clean and type-cast scalar YAML values
     *
     * @param string $val
     * @return mixed
     */
    private static function cleanYamlValue(string $val) {
        $val = trim($val);
        $lower = strtolower($val);

        if ($lower === 'true' || $lower === 'yes' || $lower === 'on') {
            return true;
        }
        if ($lower === 'false' || $lower === 'no' || $lower === 'off') {
            return false;
        }
        if ($lower === 'null' || $lower === '~' || $lower === '') {
            return null;
        }
        if (is_numeric($val) && !preg_match('/^0[0-9]+/', $val)) {
            return strpos($val, '.') !== false ? (float)$val : (int)$val;
        }

        // Strip enclosing single or double quotes
        if ((strpos($val, '"') === 0 && substr($val, -1) === '"' && strlen($val) >= 2) ||
            (strpos($val, "'") === 0 && substr($val, -1) === "'" && strlen($val) >= 2)) {
            $val = substr($val, 1, -1);
        }

        return $val;
    }

    /**
     * Format a single frontmatter property value for HTML rendering
     *
     * @param mixed $val
     * @return string
     */
    public static function formatFrontmatterValue($val): string {
        if (is_bool($val)) {
            return $val 
                ? '<span class="qwiki-fm-pill qwiki-fm-bool-true">true</span>' 
                : '<span class="qwiki-fm-pill qwiki-fm-bool-false">false</span>';
        }
        if (is_null($val)) {
            return '<span class="qwiki-fm-pill qwiki-fm-null">null</span>';
        }
        if (is_array($val)) {
            $items = [];
            foreach ($val as $k => $item) {
                if (is_string($k) && !is_numeric($k)) {
                    $items[] = '<span class="qwiki-fm-tag"><strong>' . htmlspecialchars((string)$k) . ':</strong> ' . self::formatFrontmatterValue($item) . '</span>';
                } else {
                    $itemStr = is_scalar($item) ? (string)$item : json_encode($item);
                    $items[] = '<span class="qwiki-fm-tag">' . htmlspecialchars($itemStr) . '</span>';
                }
            }
            return '<div class="qwiki-fm-tag-list">' . implode(' ', $items) . '</div>';
        }

        $str = (string)$val;
        if (preg_match('#^https?://#i', $str)) {
            return '<a href="' . htmlspecialchars($str) . '" target="_blank" rel="noopener noreferrer" class="qwiki-fm-link">' . htmlspecialchars($str) . ' <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg></a>';
        }

        // Format source references, paths, and filenames in monospace code badges
        if (preg_match('/\.(txt|md|html|json|php|js|ya?ml|py|css|sh)$/i', $str) || (strpos($str, '/') !== false && !strpos($str, ' '))) {
            return '<code class="qwiki-fm-code">' . htmlspecialchars($str) . '</code>';
        }

        return htmlspecialchars($str);
    }

    /**
     * Render the collapsible frontmatter accordion widget HTML
     *
     * @param array $fmData Result from parseFrontmatter()
     * @param bool $expanded Whether to expand by default
     * @return string
     */
    public static function renderFrontmatterWidget(array $fmData, bool $expanded = false): string {
        if (empty($fmData['hasFrontmatter']) || empty($fmData['metadata'])) {
            return '';
        }

        $openAttr = $expanded ? ' open' : '';
        $count = count($fmData['metadata']);
        $label = $count === 1 ? '1 property' : $count . ' properties';

        $html = '<div class="qwiki-frontmatter-container">' . "\n";
        $html .= '  <details class="qwiki-frontmatter"' . $openAttr . '>' . "\n";
        $html .= '    <summary class="qwiki-frontmatter-summary">' . "\n";
        $html .= '      <div class="qwiki-frontmatter-header">' . "\n";
        $html .= '        <svg class="qwiki-frontmatter-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>' . "\n";
        $html .= '        <span class="qwiki-frontmatter-label">Metadata</span>' . "\n";
        $html .= '        <span class="qwiki-frontmatter-badge">' . htmlspecialchars($label) . '</span>' . "\n";
        $html .= '      </div>' . "\n";
        $html .= '      <span class="qwiki-frontmatter-toggle-hint">Click to toggle</span>' . "\n";
        $html .= '    </summary>' . "\n";
        $html .= '    <div class="qwiki-frontmatter-body">' . "\n";
        $html .= '      <table class="qwiki-frontmatter-table">' . "\n";
        $html .= '        <tbody>' . "\n";

        foreach ($fmData['metadata'] as $key => $val) {
            $html .= '          <tr>' . "\n";
            $html .= '            <th class="qwiki-frontmatter-key">' . htmlspecialchars((string)$key) . '</th>' . "\n";
            $html .= '            <td class="qwiki-frontmatter-val">' . self::formatFrontmatterValue($val) . '</td>' . "\n";
            $html .= '          </tr>' . "\n";
        }

        $html .= '        </tbody>' . "\n";
        $html .= '      </table>' . "\n";

        if (!empty($fmData['rawYaml'])) {
            $html .= '      <details class="qwiki-frontmatter-raw">' . "\n";
            $html .= '        <summary>Raw YAML</summary>' . "\n";
            $html .= '        <pre><code>' . htmlspecialchars(trim($fmData['rawYaml'])) . '</code></pre>' . "\n";
            $html .= '      </details>' . "\n";
        }

        $html .= '    </div>' . "\n";
        $html .= '  </details>' . "\n";
        $html .= '</div>' . "\n";

        return $html;
    }
}
