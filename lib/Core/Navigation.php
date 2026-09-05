<?php
namespace Qwiki\Core;

class Navigation {
    public static function filterBooks(array $books, $isAdmin = false, $isViewer = false) {
        $allowed = [];
        foreach ($books as $book) {
            $visibility = $book['visibility'] ?? 'public';
            if (!$isAdmin) {
                if ($visibility === 'admin_only') continue;
                if ($visibility === 'logged_in' && !$isViewer) continue;
            }
            $allowed[] = $book;
        }
        return $allowed;
    }

    public static function isExternalUrl($url) {
        if (empty($url)) return false;
        $rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $currentHostParts = explode(':', $rawHost);
        $currentHostName = strtolower($currentHostParts[0]);
        $currentPort = isset($currentHostParts[1]) ? (int)$currentHostParts[1] : null;

        $parsed = parse_url($url);
        $linkHost = isset($parsed['host']) ? strtolower($parsed['host']) : '';

        // Handle protocol-relative URLs like "//example.com/path"
        if (empty($linkHost) && strpos($url, '//') === 0) {
            $parsedRel = parse_url('http:' . $url);
            $linkHost = isset($parsedRel['host']) ? strtolower($parsedRel['host']) : '';
            $linkPort = $parsedRel['port'] ?? null;
        } else {
            $linkPort = $parsed['port'] ?? null;
        }

        if (!empty($linkHost)) {
            if ($linkHost !== $currentHostName) {
                return true;
            }
            if ($currentPort !== null && $linkPort !== null && $currentPort !== $linkPort) {
                return true;
            }
            return false;
        }

        return false;
    }

    public static function findChapterAndPath($node, $targetFolderId, $targetChapterSlug, &$trail, &$activeIds, $isAdmin = false, $isViewer = false, $inTargetFolder = false) {
        $visibility = $node['visibility'] ?? 'public';
        if (!$isAdmin) {
            if ($visibility === 'admin_only') return null;
            if ($visibility === 'logged_in' && !$isViewer) return null;
        }

        $nodeId = $node['id'] ?? '';
        $nodeTitle = $node['title'] ?? '';
        $currentTrail = array_merge($trail, [['title' => $nodeTitle, 'id' => $nodeId]]);
        $currentActiveIds = array_merge($activeIds, [$nodeId]);

        $isFolderMatch = ($targetFolderId && $nodeId === $targetFolderId);
        $isTargetContext = ($inTargetFolder || $isFolderMatch || !$targetFolderId);

        if (!empty($node['items'])) {
            if ($targetChapterSlug) {
                // Looking for a specific document
                foreach ($node['items'] as $item) {
                    if (!isset($item['type']) || ($item['type'] !== 'folder' && $item['type'] !== 'link')) {
                        if (($item['slug'] ?? '') === $targetChapterSlug) {
                            if (!$targetFolderId || $isFolderMatch) {
                                $trail = $currentTrail;
                                $activeIds = $currentActiveIds;
                                return $item;
                            }
                        }
                    }
                }
                // Recurse into subfolders
                foreach ($node['items'] as $item) {
                    if (isset($item['type']) && $item['type'] === 'folder') {
                        $found = self::findChapterAndPath($item, $targetFolderId, $targetChapterSlug, $currentTrail, $currentActiveIds, $isAdmin, $isViewer, false);
                        if ($found) {
                            $trail = $currentTrail;
                            $activeIds = $currentActiveIds;
                            return $found;
                        }
                    }
                }
            } else {
                // Looking for the first document in target context
                if ($isTargetContext) {
                    foreach ($node['items'] as $item) {
                        if (!isset($item['type']) || ($item['type'] !== 'folder' && $item['type'] !== 'link')) {
                            $trail = $currentTrail;
                            $activeIds = $currentActiveIds;
                            return $item;
                        } else {
                            if (isset($item['type']) && $item['type'] === 'folder') {
                                $found = self::findChapterAndPath($item, $targetFolderId, $targetChapterSlug, $currentTrail, $currentActiveIds, $isAdmin, $isViewer, true);
                                if ($found) {
                                    $trail = $currentTrail;
                                    $activeIds = $currentActiveIds;
                                    return $found;
                                }
                            }
                        }
                    }
                } else {
                    foreach ($node['items'] as $item) {
                        if (isset($item['type']) && $item['type'] === 'folder') {
                            $found = self::findChapterAndPath($item, $targetFolderId, $targetChapterSlug, $currentTrail, $currentActiveIds, $isAdmin, $isViewer, false);
                            if ($found) {
                                $trail = $currentTrail;
                                $activeIds = $currentActiveIds;
                                return $found;
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    public static function flattenNavTree($node, $bookId, &$flatList, $isAdmin = false, $isViewer = false) {
        $visibility = $node['visibility'] ?? 'public';
        if (!$isAdmin) {
            if ($visibility === 'admin_only') return;
            if ($visibility === 'logged_in' && !$isViewer) return;
        }

        if (!empty($node['items'])) {
            foreach ($node['items'] as $item) {
                if (isset($item['type']) && $item['type'] === 'folder') {
                    self::flattenNavTree($item, $bookId, $flatList, $isAdmin, $isViewer);
                } elseif (isset($item['type']) && $item['type'] === 'link') {
                    // Hyperlinks are excluded from sequential reading list
                    continue;
                } else {
                    $ch = $item;
                    $nodeId = $node['id'] ?? '';
                    if ($nodeId === $bookId) {
                        $linkUrl = urlencode($bookId) . "/" . urlencode($ch['slug'] ?? '');
                    } else {
                        $linkUrl = urlencode($bookId) . "/" . urlencode($nodeId) . "/" . urlencode($ch['slug'] ?? '');
                    }
                    $flatList[] = [
                        'title' => $ch['title'] ?? '',
                        'slug' => $ch['slug'] ?? '',
                        'bookId' => $bookId,
                        'url' => $linkUrl
                    ];
                }
            }
        }
    }

    public static function renderLinkItem($item, $bookId = '', $depth = 0, $isAdmin = false, $isViewer = false, $showDocTypesOnlyToAdmin = true) {
        $visibility = $item['visibility'] ?? 'public';
        if (!$isAdmin) {
            if ($visibility === 'admin_only') return;
            if ($visibility === 'logged_in' && !$isViewer) return;
        }

        $title = $item['title'] ?? 'Link';
        $slug = $item['slug'] ?? '';
        $rawUrl = $item['url'] ?? '#';
        $isExternal = self::isExternalUrl($rawUrl);
        $targetAttr = $isExternal ? "target='_blank' rel='noopener noreferrer'" : "target='_self'";

        $depthClass = ($depth === 0) ? 'nav-top-link' : '';
        $chTheme = htmlspecialchars($item['theme'] ?? '');
        $chDesc = htmlspecialchars($item['description'] ?? '');
        $chImg = htmlspecialchars($item['image'] ?? '');
        $safeUrl = htmlspecialchars($rawUrl);
        $safeTitle = htmlspecialchars($title);
        $safeSlug = htmlspecialchars($slug);

        $docDragAttr = $isAdmin ? "draggable='true' data-drag-type='document' data-doc-title='{$safeTitle}' data-doc-slug='{$safeSlug}' data-doc-type='link' data-doc-url='{$safeUrl}' data-doc-editurl='' data-doc-file='' data-doc-theme='{$chTheme}' data-doc-description='{$chDesc}' data-doc-image='{$chImg}'" : "";

        echo "<a href='{$safeUrl}' {$targetAttr} class='nav-link nav-link-hyperlink {$depthClass}' {$docDragAttr}>";
        echo "<span class='nav-link-title-container'>";
        if ($isAdmin) echo "<span class='drag-handle' title='Drag to reorder'>⣿</span> ";
        if ($depth === 0) echo "<span class='top-link-icon'>🔗</span> ";
        echo "{$safeTitle}</span>";

        echo "<span class='nav-link-actions-container'>";
        if ($isAdmin) {
            echo "<button type='button' class='btn-edit-link-icon' data-book-id='" . htmlspecialchars($bookId) . "' data-slug='{$safeSlug}' data-title='{$safeTitle}' data-url='{$safeUrl}' data-description='{$chDesc}' data-visibility='" . htmlspecialchars($visibility) . "' title='Edit Link'>⚙️</button> ";
        }

        if ($isAdmin || !$showDocTypesOnlyToAdmin) {
            if ($isExternal) {
                echo "<span class='doc-badge badge-link' title='External Link (Opens in new tab)'><svg class='doc-badge-svg' viewBox='0 0 24 24' width='13' height='13' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6'></path><polyline points='15 3 21 3 21 9'></polyline><line x1='10' y1='14' x2='21' y2='3'></line></svg></span>";
            } else {
                echo "<span class='doc-badge badge-internal-link' title='Internal Link (Opens in same tab)'><svg class='doc-badge-svg' viewBox='0 0 24 24' width='13' height='13' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71'></path><path d='M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71'></path></svg></span>";
            }
        }
        echo "</span>";
        echo "</a>";
    }

    public static function renderSidebarNode($node, $bookId, $activePathIds, $activeChapterSlug, $depth = 0, $isAdmin = false, $isViewer = false, $showDocTypesOnlyToAdmin = true, $extensionManager = null) {
        $visibility = $node['visibility'] ?? 'public';
        if (!$isAdmin) {
            if ($visibility === 'admin_only') return;
            if ($visibility === 'logged_in' && !$isViewer) return;
        }

        if (($node['type'] ?? '') === 'link') {
            self::renderLinkItem($node, '', $depth, $isAdmin, $isViewer, $showDocTypesOnlyToAdmin);
            return;
        }

        $nodeId = $node['id'] ?? '';
        $nodeTitle = $node['title'] ?? '';
        $isExpanded = in_array($nodeId, $activePathIds);
        $icon = ($depth === 0) ? '📂' : '📁';
        $indentClass = 'depth-' . min($depth, 5);

        $nodeTheme = htmlspecialchars($node['theme'] ?? '');
        $nodeVis = htmlspecialchars($node['visibility'] ?? 'public');
        $nodeFolder = htmlspecialchars($node['folder'] ?? '');
        $draggableAttr = $isAdmin ? "draggable='true' data-drag-type='category' data-node-id='" . htmlspecialchars($nodeId) . "' data-node-title='" . htmlspecialchars($nodeTitle) . "' data-node-visibility='{$nodeVis}' data-node-theme='{$nodeTheme}' data-node-folder='{$nodeFolder}'" : "";

        echo "<div class='nav-category-item {$indentClass} " . ($isExpanded ? '' : 'collapsed') . "' {$draggableAttr}>";
        echo "<div class='nav-category-header'>";
        echo "<span>";
        if ($isAdmin) echo "<span class='drag-handle' title='Drag to reorder'>⣿</span> ";
        echo "{$icon} " . htmlspecialchars($nodeTitle) . "</span>";
        echo "<span class='header-actions-inline'>";
        if ($isAdmin) {
            echo "<button class='btn-edit-cat-icon' data-book-id='" . htmlspecialchars($nodeId) . "' data-book-title='" . htmlspecialchars($nodeTitle) . "' data-book-theme='{$nodeTheme}' data-book-visibility='{$nodeVis}' title='Edit Category'>⚙️</button> ";
        }
        echo "<span class='chevron-icon'>▾</span>";
        echo "</span>";
        echo "</div>";

        echo "<div class='nav-document-list' data-parent-node-id='" . htmlspecialchars($nodeId) . "'>";

        if (!empty($node['items'])) {
            foreach ($node['items'] as $item) {
                if (isset($item['type']) && $item['type'] === 'folder') {
                    self::renderSidebarNode($item, $bookId, $activePathIds, $activeChapterSlug, $depth + 1, $isAdmin, $isViewer, $showDocTypesOnlyToAdmin, $extensionManager);
                } elseif (isset($item['type']) && $item['type'] === 'link') {
                    self::renderLinkItem($item, $bookId, $depth + 1, $isAdmin, $isViewer, $showDocTypesOnlyToAdmin);
                } else {
                    $ch = $item;
                    $isActive = ($isExpanded && $activeChapterSlug === ($ch['slug'] ?? ''));
                    $docType = strtolower($ch['type'] ?? 'markdown');
                    $badgeClass = 'badge-' . htmlspecialchars($docType);

                    if ($nodeId === $bookId) {
                        $linkUrl = urlencode($bookId) . "/" . urlencode($ch['slug'] ?? '');
                    } else {
                        $linkUrl = urlencode($bookId) . "/" . urlencode($nodeId) . "/" . urlencode($ch['slug'] ?? '');
                    }

                    $chTheme = htmlspecialchars($ch['theme'] ?? '');
                    $chDesc = htmlspecialchars($ch['description'] ?? '');
                    $chImg = htmlspecialchars($ch['image'] ?? '');
                    $isChReadOnly = !empty($ch['readOnly']) || (isset($ch['editable']) && $ch['editable'] === false);
                    $readOnlyAttr = $isChReadOnly ? "data-doc-readonly='1'" : "";
                    $docDragAttr = $isAdmin ? "draggable='true' data-drag-type='document' data-doc-title='" . htmlspecialchars($ch['title'] ?? '') . "' data-doc-slug='" . htmlspecialchars($ch['slug'] ?? '') . "' data-doc-type='" . htmlspecialchars($docType) . "' data-doc-url='" . htmlspecialchars($ch['url'] ?? '') . "' data-doc-editurl='" . htmlspecialchars($ch['editUrl'] ?? '') . "' data-doc-file='" . htmlspecialchars($ch['file'] ?? '') . "' data-doc-theme='{$chTheme}' data-doc-description='{$chDesc}' data-doc-image='{$chImg}' {$readOnlyAttr}" : "";

                    echo "<a href='{$linkUrl}' class='nav-link " . ($isActive ? 'active' : '') . "' {$docDragAttr}>";
                    echo "<span>";
                    if ($isAdmin) echo "<span class='drag-handle' title='Drag to reorder'>⣿</span> ";
                    echo htmlspecialchars($ch['title'] ?? '') . "</span>";

                    if ($isAdmin || !$showDocTypesOnlyToAdmin) {
                        $badgeTitle = strtoupper($docType);
                        $iconSvg = null;

                        if ($extensionManager && method_exists($extensionManager, 'renderBadge')) {
                            $iconSvg = $extensionManager->renderBadge($docType);
                        }

                        if (!$iconSvg) {
                            if ($docType === 'markdown' || $docType === 'md') {
                                $iconSvg = '<svg class="doc-badge-svg" viewBox="0 0 208 128" width="16" height="10" fill="currentColor" aria-hidden="true"><rect width="198" height="118" x="5" y="5" rx="14" fill="none" stroke="currentColor" stroke-width="14"/><path d="M30 98V30h20l20 25 20-25h20v68H90V55L70 80 50 55v43H30zm135 0l-30-35h20V30h20v33h20l-30 35z"/></svg>';
                            } elseif ($docType === 'pdf') {
                                $iconSvg = '<svg class="doc-badge-svg" viewBox="0 0 24 24" width="13" height="13" fill="currentColor" aria-hidden="true"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-9.5 8.5c0 .8-.7 1.5-1.5 1.5H7v2H5.5V9H8c.8 0 1.5.7 1.5 1.5v1zm5 2c0 .8-.7 1.5-1.5 1.5h-2.5V9H13c.8 0 1.5.7 1.5 1.5v3zm3.5-3.5h-2.5v1.5H17V13h-1.5v2H14V9h4v1.5zM7 10.5h1v1H7v-1zm5.5 0h1v3h-1v-3z"/></svg>';
                            } elseif ($docType === 'gdoc') {
                                $iconSvg = '<svg class="doc-badge-svg" viewBox="0 0 24 24" width="13" height="13" fill="currentColor" aria-hidden="true"><path d="M14.5 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V7.5L14.5 2zM14 8V3.5L18.5 8H14zm-6 3h8v1.5H8V11zm0 3h8v1.5H8V14zm0 3h5v1.5H8V17z"/></svg>';
                            } else {
                                $iconSvg = htmlspecialchars($docType);
                            }
                        }
                        echo "<span class='doc-badge {$badgeClass}' title='" . htmlspecialchars($badgeTitle) . " Document'>{$iconSvg}</span>";
                    }
                    echo "</a>";
                }
            }
        }

        echo "</div>";
        echo "</div>";
    }
}
