<?php
session_start();
define('QWIKI_VERSION', '1.1.0');
require_once __DIR__ . '/lib/Parsedown.php';
require_once __DIR__ . '/lib/simple_html_dom.php';

class QwikiParsedown extends Parsedown {
    protected function inlineLink($Excerpt) {
        $Inline = parent::inlineLink($Excerpt);
        if ( ! isset($Inline)) {
            return;
        }

        $href = $Inline['element']['attributes']['href'] ?? '';
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        $parsedUrl = parse_url($href);
        $linkHost = $parsedUrl['host'] ?? '';
        
        if ($linkHost && $linkHost !== $currentHost) {
            $Inline['element']['attributes']['target'] = '_blank';
            $Inline['element']['attributes']['rel'] = 'noopener noreferrer';
        }
        
        return $Inline;
    }
}

$configFile = __DIR__ . '/qwiki.json';
if (!file_exists($configFile)) {
    // Auto-setup mechanism
    $demoDir = __DIR__ . '/demo-data';
    
    if (file_exists($demoDir . '/qwiki-default.json')) {
        // Create necessary directories
        if (!is_dir(__DIR__ . '/uploads')) @mkdir(__DIR__ . '/uploads', 0755, true);
        
        // Recursive copy function for content
        if (!function_exists('qwiki_copy_dir')) {
            function qwiki_copy_dir($src, $dst) {
                if (!is_dir($src)) return;
                @mkdir($dst, 0755, true);
                $dir = opendir($src);
                while (false !== ($file = readdir($dir))) {
                    if (($file != '.') && ($file != '..')) {
                        if (is_dir($src . '/' . $file)) qwiki_copy_dir($src . '/' . $file, $dst . '/' . $file);
                        else copy($src . '/' . $file, $dst . '/' . $file);
                    }
                }
                closedir($dir);
            }
        }
        
        @qwiki_copy_dir($demoDir . '/content', __DIR__ . '/content');
        
        $copiedConfig = @copy($demoDir . '/qwiki-default.json', $configFile);
        if (!$copiedConfig) {
            die("
                <div style='font-family: sans-serif; padding: 20px; max-width: 600px; margin: 40px auto; border: 1px solid #ffcccc; background: #fff5f5; border-radius: 8px;'>
                    <h2 style='color: #cc0000; margin-top: 0;'>Auto-setup failed</h2>
                    <p>Could not create <code>qwiki.json</code>. The web server does not have write permissions to the Qwiki directory.</p>
                    <p><strong>Option 1: Using SSH (Terminal)</strong><br>
                    Change the ownership of the directory to your web server user (e.g., <code>www-data</code> for Apache/Nginx on Ubuntu):</p>
                    <pre style='background: #333; color: #fff; padding: 15px; border-radius: 4px; overflow-x: auto;'>sudo chown -R www-data:www-data " . realpath(__DIR__) . "</pre>
                    <p><strong>Option 2: Using FTP/SFTP (cPanel, FileZilla, etc.)</strong><br>
                    Right-click the Qwiki folder in your FTP client, select <em>File Permissions</em> (or Attributes), and change the permissions to <strong>755</strong> or <strong>775</strong>. Ensure you apply this to all files and directories.</p>
                </div>
            ");
        }
        
        if (!is_dir(__DIR__ . '/uploads')) {
            @mkdir(__DIR__ . '/uploads', 0755, true);
        }
        if (file_exists($demoDir . '/htaccess-uploads') && is_dir(__DIR__ . '/uploads')) {
            @copy($demoDir . '/htaccess-uploads', __DIR__ . '/uploads/.htaccess');
        }
        
    } else {
        die("<strong>Setup Error:</strong> Configuration file <code>qwiki.json</code> not found, and <code>demo-data</code> template is missing.");
    }
}

$config = json_decode(file_get_contents($configFile), true);

// User session evaluation
$currentUser = $_SESSION['qwiki_user'] ?? null;
$isAdmin = (!empty($currentUser) && $currentUser['role'] === 'admin') || !empty($_SESSION['qwiki_admin']);
$isViewer = !empty($currentUser);
$requireLoginToView = !empty($config['requireLoginToView']);
$canViewContent = !$requireLoginToView || $isViewer || $isAdmin;

// Routing parameters
$requestedBookId = $_GET['book'] ?? $config['defaultBook'] ?? ($config['books'][0]['id'] ?? '');
$requestedFolderId = $_GET['folder'] ?? $_GET['dir'] ?? '';
$requestedChapterSlug = $_GET['chapter'] ?? $_GET['doc'] ?? '';

// Filter books based on visibility
$allowedBooks = [];
foreach ($config['books'] as $book) {
    $visibility = $book['visibility'] ?? 'public';
    if (!$isAdmin) {
        if ($visibility === 'admin_only') continue;
        if ($visibility === 'logged_in' && !$isViewer) continue;
    }
    $allowedBooks[] = $book;
}

// Active tree resolution state
$activeBook = null;
foreach ($allowedBooks as $book) {
    if ($book['id'] === $requestedBookId) {
        $activeBook = $book;
        break;
    }
}
if (!$activeBook && !empty($allowedBooks)) {
    $activeBook = $allowedBooks[0];
}

$activeChapter = null;
$breadcrumbsTrail = [];
$activePathIds = [$activeBook['id']];

/**
 * Recursive search to locate active folder & chapter, building breadcrumb trail and active path IDs
 */
function find_chapter_and_path($node, $targetFolderId, $targetChapterSlug, &$trail, &$activeIds, $isAdmin = false, $isViewer = false, $inTargetFolder = false) {
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
                if (!isset($item['type']) || $item['type'] !== 'folder') {
                    if ($item['slug'] === $targetChapterSlug) {
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
                    $found = find_chapter_and_path($item, $targetFolderId, $targetChapterSlug, $currentTrail, $currentActiveIds, $isAdmin, $isViewer, false);
                    if ($found) {
                        $trail = $currentTrail;
                        $activeIds = $currentActiveIds;
                        return $found;
                    }
                }
            }
        } else {
            // Looking for the first document in the target context
            if ($isTargetContext) {
                // Pre-order traversal: return the first document we encounter
                foreach ($node['items'] as $item) {
                    if (!isset($item['type']) || $item['type'] !== 'folder') {
                        $trail = $currentTrail;
                        $activeIds = $currentActiveIds;
                        return $item;
                    } else {
                        $found = find_chapter_and_path($item, $targetFolderId, $targetChapterSlug, $currentTrail, $currentActiveIds, $isAdmin, $isViewer, true);
                        if ($found) {
                            $trail = $currentTrail;
                            $activeIds = $currentActiveIds;
                            return $found;
                        }
                    }
                }
            } else {
                // Not in target context yet, keep searching for the target folder
                foreach ($node['items'] as $item) {
                    if (isset($item['type']) && $item['type'] === 'folder') {
                        $found = find_chapter_and_path($item, $targetFolderId, $targetChapterSlug, $currentTrail, $currentActiveIds, $isAdmin, $isViewer, false);
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

$dummyTrail = [];
$dummyIds = [];
$activeChapter = find_chapter_and_path($activeBook, $requestedFolderId, $requestedChapterSlug, $dummyTrail, $dummyIds, $isAdmin, $isViewer);

if ($activeChapter) {
    $breadcrumbsTrail = $dummyTrail;
    $activePathIds = array_unique(array_merge([$activeBook['id']], $dummyIds));
} else {
    if (!empty($activeBook['items'])) {
        foreach ($activeBook['items'] as $item) {
            if (!isset($item['type']) || $item['type'] !== 'folder') {
                $activeChapter = $item;
                $breadcrumbsTrail = [['title' => $activeBook['title'], 'id' => $activeBook['id']]];
                break;
            }
        }
    }
}

// Content rendering logic based on chapter type
$renderedContent = '';
$rawMarkdownContent = '';

if ($activeChapter) {
    $type = $activeChapter['type'] ?? 'markdown';

    if ($type === 'markdown') {
        $filePath = __DIR__ . '/' . ($activeChapter['file'] ?? '');
        if (file_exists($filePath)) {
            $rawMarkdownContent = file_get_contents($filePath);
            $parsedown = new QwikiParsedown();
            $renderedContent = $parsedown->text($rawMarkdownContent);
        } else {
            $renderedContent = "<div class='alert warning'>Markdown file not found: " . htmlspecialchars($activeChapter['file'] ?? '') . "</div>";
        }
    } elseif ($type === 'pdf') {
        $pdfUrl = htmlspecialchars($activeChapter['file'] ?? '');
        $renderedContent = "
            <div class='pdf-viewer-container'>
                <iframe src='{$pdfUrl}' title='PDF Viewer'></iframe>
            </div>
            <p style='margin-top: 1rem;'><a href='{$pdfUrl}' target='_blank' class='btn btn-outline btn-sm'>Download Original PDF</a></p>
        ";
    } elseif ($type === 'gdoc') {
        $docUrl = $activeChapter['url'] ?? '';
        if ($docUrl) {
            if (strpos($docUrl, 'embedded=true') === false) {
                $docUrl .= (strpos($docUrl, '?') !== false) ? '&embedded=true' : '?embedded=true';
            }
            $ctx = stream_context_create(['http' => ['timeout' => 5, 'header' => "User-Agent: Mozilla/5.0\r\n"]]);
            $html = @file_get_contents($docUrl, false, $ctx);
            
            if ($html && function_exists('str_get_html')) {
                $dom = str_get_html($html);
                if ($dom && $dom->find('#contents', 0)) {
                    $body = $dom->find('#contents', 0)->innertext;
                    $renderedContent = "<div class='gdoc-content'>" . $body . "</div>";
                } elseif ($dom && $dom->find('body', 0)) {
                    $renderedContent = "<div class='gdoc-content'>" . $dom->find('body', 0)->innertext . "</div>";
                } else {
                    $renderedContent = "<iframe src='" . htmlspecialchars($docUrl) . "' style='width:100%; height:750px; border:none;'></iframe>";
                }
            } else {
                $renderedContent = "
                    <div class='gdoc-container'>
                        <iframe src='" . htmlspecialchars($docUrl) . "' style='width:100%; height:750px; border:1px solid var(--border-color); border-radius:8px;'></iframe>
                    </div>
                ";
            }
        } else {
            $renderedContent = "<p>No Google Doc URL provided.</p>";
        }
    }
}

// Theme Resolution
$siteTheme = $config['theme'] ?? 'theme-default.css';
$categoryTheme = $activeBook['theme'] ?? null;
$chapterTheme = $activeChapter['theme'] ?? null;
$resolvedTheme = $chapterTheme ?: $categoryTheme ?: $siteTheme;
$showDocTypesOnlyToAdmin = isset($config['showDocTypesOnlyToAdmin']) ? !empty($config['showDocTypesOnlyToAdmin']) : true;

/**
 * Recursive function to render sidebar navigation with subfolders
 */
function render_sidebar_node($node, $bookId, $activePathIds, $activeChapterSlug, $depth = 0, $isAdmin = false, $isViewer = false, $showDocTypesOnlyToAdmin = true) {
    $visibility = $node['visibility'] ?? 'public';
    if (!$isAdmin) {
        if ($visibility === 'admin_only') return;
        if ($visibility === 'logged_in' && !$isViewer) return;
    }

    $nodeId = $node['id'] ?? '';
    $nodeTitle = $node['title'] ?? '';
    $isExpanded = in_array($nodeId, $activePathIds);
    $icon = ($depth === 0) ? '📂' : '📁';
    $indentClass = 'depth-' . min($depth, 5);

    $draggableAttr = $isAdmin ? "draggable='true' data-drag-type='category' data-node-id='" . htmlspecialchars($nodeId) . "' data-node-title='" . htmlspecialchars($nodeTitle) . "'" : "";

    echo "<div class='nav-category-item {$indentClass} " . ($isExpanded ? '' : 'collapsed') . "' {$draggableAttr}>";
    echo "<div class='nav-category-header'>";
    echo "<span>";
    if ($isAdmin) echo "<span class='drag-handle' title='Drag to reorder'>⣿</span> ";
    echo "{$icon} " . htmlspecialchars($nodeTitle) . "</span>";
    echo "<span class='header-actions-inline'>";
    if ($isAdmin) {
        $nodeTheme = htmlspecialchars($node['theme'] ?? '');
        $nodeVis = htmlspecialchars($node['visibility'] ?? 'public');
        echo "<button class='btn-edit-cat-icon' data-book-id='" . htmlspecialchars($nodeId) . "' data-book-title='" . htmlspecialchars($nodeTitle) . "' data-book-theme='{$nodeTheme}' data-book-visibility='{$nodeVis}' title='Edit Category'>⚙️</button> ";
    }
    echo "<span class='chevron-icon'>▾</span>";
    echo "</span>";
    echo "</div>";

    echo "<div class='nav-document-list' data-parent-node-id='" . htmlspecialchars($nodeId) . "'>";

    if (!empty($node['items'])) {
        foreach ($node['items'] as $item) {
            if (isset($item['type']) && $item['type'] === 'folder') {
                render_sidebar_node($item, $bookId, $activePathIds, $activeChapterSlug, $depth + 1, $isAdmin, $isViewer, $showDocTypesOnlyToAdmin);
            } else {
                $ch = $item;
                $isActive = ($isExpanded && $activeChapterSlug === $ch['slug']);
                $badgeClass = 'badge-' . htmlspecialchars($ch['type']);
                $linkUrl = "index.php?book=" . urlencode($bookId) . "&folder=" . urlencode($nodeId) . "&chapter=" . urlencode($ch['slug']);
                
                $chTheme = htmlspecialchars($ch['theme'] ?? '');
                $docDragAttr = $isAdmin ? "draggable='true' data-drag-type='document' data-doc-title='" . htmlspecialchars($ch['title']) . "' data-doc-slug='" . htmlspecialchars($ch['slug']) . "' data-doc-type='" . htmlspecialchars($ch['type']) . "' data-doc-url='" . htmlspecialchars($ch['url'] ?? '') . "' data-doc-editurl='" . htmlspecialchars($ch['editUrl'] ?? '') . "' data-doc-file='" . htmlspecialchars($ch['file'] ?? '') . "' data-doc-theme='{$chTheme}'" : "";

                echo "<a href='{$linkUrl}' class='nav-link " . ($isActive ? 'active' : '') . "' {$docDragAttr}>";
                echo "<span>";
                if ($isAdmin) echo "<span class='drag-handle' title='Drag to reorder'>⣿</span> ";
                echo htmlspecialchars($ch['title']) . "</span>";
                
                if ($isAdmin || !$showDocTypesOnlyToAdmin) {
                    $docType = strtolower($ch['type'] ?? 'markdown');
                    $badgeTitle = strtoupper($docType);
                    if ($docType === 'markdown' || $docType === 'md') {
                        $iconSvg = '<svg class="doc-badge-svg" viewBox="0 0 208 128" width="16" height="10" fill="currentColor" aria-hidden="true"><rect width="198" height="118" x="5" y="5" rx="14" fill="none" stroke="currentColor" stroke-width="14"/><path d="M30 98V30h20l20 25 20-25h20v68H90V55L70 80 50 55v43H30zm135 0l-30-35h20V30h20v33h20l-30 35z"/></svg>';
                    } elseif ($docType === 'pdf') {
                        $iconSvg = '<svg class="doc-badge-svg" viewBox="0 0 24 24" width="13" height="13" fill="currentColor" aria-hidden="true"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-9.5 8.5c0 .8-.7 1.5-1.5 1.5H7v2H5.5V9H8c.8 0 1.5.7 1.5 1.5v1zm5 2c0 .8-.7 1.5-1.5 1.5h-2.5V9H13c.8 0 1.5.7 1.5 1.5v3zm3.5-3.5h-2.5v1.5H17V13h-1.5v2H14V9h4v1.5zM7 10.5h1v1H7v-1zm5.5 0h1v3h-1v-3z"/></svg>';
                    } elseif ($docType === 'gdoc') {
                        $iconSvg = '<svg class="doc-badge-svg" viewBox="0 0 24 24" width="13" height="13" fill="currentColor" aria-hidden="true"><path d="M14.5 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V7.5L14.5 2zM14 8V3.5L18.5 8H14zm-6 3h8v1.5H8V11zm0 3h8v1.5H8V14zm0 3h5v1.5H8V17z"/></svg>';
                    } else {
                        $iconSvg = htmlspecialchars($ch['type']);
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
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(($activeChapter['title'] ?? 'Documentation') . ' - ' . ($config['title'] ?? 'Standalone Qwiki')) ?></title>
    
    <!-- Open Graph & Social Share Tags -->
    <?php
        $ogTitle = htmlspecialchars(($activeChapter['title'] ?? 'Documentation') . ' - ' . ($config['title'] ?? ''));
        $ogDesc = htmlspecialchars($activeChapter['description'] ?? $config['shareDescription'] ?? 'Read this documentation on Qwiki.');
        $ogImage = htmlspecialchars($activeChapter['image'] ?? $config['shareImageUrl'] ?? $config['logoUrl'] ?? '');
    ?>
    <meta property="og:title" content="<?= $ogTitle ?>">
    <meta property="og:description" content="<?= $ogDesc ?>">
    <?php if ($ogImage): ?>
    <meta property="og:image" content="<?= $ogImage ?>">
    <meta name="twitter:image" content="<?= $ogImage ?>">
    <?php endif; ?>
    <meta property="og:type" content="article">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $ogTitle ?>">
    <meta name="twitter:description" content="<?= $ogDesc ?>">

    <link rel="stylesheet" href="assets/css/qwiki.css?v=<?= filemtime(__DIR__ . '/assets/css/qwiki.css') ?>">
    <?php if ($resolvedTheme && $resolvedTheme !== 'theme-default.css'): ?>
        <link rel="stylesheet" href="assets/css/<?= htmlspecialchars($resolvedTheme) ?>" id="dynamic-theme-css">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Toast UI Editor -->
    <link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/toastui-editor.min.css" />
    <link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/theme/toastui-editor-dark.min.css" />
</head>
<body>

    <!-- App Header -->
    <header class="app-header">
        <div class="brand-container">
            <button class="mobile-toggle" id="mobile-toggle" aria-label="Toggle navigation">☰</button>
            <a href="index.php" class="brand-logo">
                <?php if (!empty($config['logoUrl'])): ?>
                    <img src="<?= htmlspecialchars($config['logoUrl']) ?>" alt="Logo" style="max-height: 64px; border-radius: 4px;">
                <?php else: ?>
                    ⚡ <?= htmlspecialchars($config['logoText'] ?? 'QWIKI') ?>
                <?php endif; ?>
            </a>
        </div>
        <div class="header-actions">
            <button class="btn btn-outline btn-sm" id="theme-toggle" title="Toggle Dark/Light Mode">🌙 Theme</button>
            <?php if ($isViewer): ?>
                <div class="dropdown">
                    <button class="btn btn-outline btn-sm dropdown-toggle" id="user-dropdown-toggle">
                        <span class="doc-badge badge-md" style="margin: 0; padding: 0.1rem 0.3rem;"><?= htmlspecialchars($currentUser['username'] ?? 'User') ?></span> ▾
                    </button>
                    <div class="dropdown-menu">
                        <?php if ($isAdmin): ?>
                            <button class="dropdown-item" id="btn-add-book">+ Category</button>
                            <button class="dropdown-item" id="btn-add-chapter">+ Document</button>
                            <button class="dropdown-item" id="btn-users">👥 Users</button>
                            <button class="dropdown-item" id="btn-settings" data-theme="<?= htmlspecialchars($config['theme'] ?? 'theme-default.css') ?>">⚙️ Settings</button>
                            <button class="dropdown-item" id="btn-update-available" style="display: none; background-color: #f59e0b; color: #fff;">🎉 Update Available!</button>
                            <div class="dropdown-divider"></div>
                        <?php endif; ?>
                        <button class="dropdown-item text-danger" id="btn-logout">Logout</button>
                    </div>
                </div>
            <?php else: ?>
                <button class="btn btn-outline btn-sm" id="btn-login">Login</button>
            <?php endif; ?>
        </div>
    </header>

    <div class="app-container">
        <!-- Sidebar Navigation -->
        <aside class="app-sidebar" id="app-sidebar">
            <div class="sidebar-resizer" id="sidebar-resizer" title="Drag right edge to resize sidebar"></div>
            <div class="sidebar-search">
                <input type="text" id="search-input" class="search-input" placeholder="Search documentation...">
            </div>
            <nav class="sidebar-nav">
                <?php foreach ($config['books'] as $book): ?>
                    <?php render_sidebar_node($book, $book['id'], $activePathIds, $activeChapter['slug'] ?? '', 0, $isAdmin, $isViewer, $showDocTypesOnlyToAdmin); ?>
                <?php endforeach; ?>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="app-content">
            <?php if ($activeChapter): ?>
                <div class="content-header">
                    <div class="breadcrumbs">
                        <?php foreach ($breadcrumbsTrail as $index => $crumb): ?>
                            <?php if ($index > 0): ?><span>/</span><?php endif; ?>
                            <a href="index.php?book=<?= urlencode($activeBook['id']) ?>&folder=<?= urlencode($crumb['id']) ?>"><?= htmlspecialchars($crumb['title']) ?></a>
                        <?php endforeach; ?>
                        <span>/</span>
                        <span><?= htmlspecialchars($activeChapter['title']) ?></span>
                    </div>
                    <div class="content-actions">
                        <div id="read-actions" style="display: flex; gap: 0.75rem;">
                            <?php if ($activeChapter['type'] === 'gdoc' && !empty($activeChapter['editUrl'])): ?>
                                <a href="<?= htmlspecialchars($activeChapter['editUrl']) ?>" target="_blank" class="btn btn-outline btn-sm">Edit Google Doc ↗</a>
                            <?php endif; ?>
                            <?php if ($isAdmin): ?>
                                <?php if ($activeChapter['type'] === 'markdown'): ?>
                                    <button class="btn btn-primary btn-sm" id="btn-edit-markdown">✏️ Edit Content</button>
                                <?php endif; ?>
                                <button class="btn btn-outline btn-sm" id="btn-edit-chapter-meta"
                                        data-title="<?= htmlspecialchars($activeChapter['title']) ?>"
                                        data-slug="<?= htmlspecialchars($activeChapter['slug']) ?>"
                                        data-type="<?= htmlspecialchars($activeChapter['type']) ?>"
                                        data-url="<?= htmlspecialchars($activeChapter['url'] ?? '') ?>"
                                        data-edit-url="<?= htmlspecialchars($activeChapter['editUrl'] ?? '') ?>"
                                        data-file="<?= htmlspecialchars($activeChapter['file'] ?? '') ?>"
                                        data-theme="<?= htmlspecialchars($activeChapter['theme'] ?? '') ?>">⚙️ Edit Details</button>
                                <button class="btn btn-outline btn-sm btn-danger-text" id="btn-delete-chapter" data-book="<?= htmlspecialchars($activeBook['id']) ?>" data-slug="<?= htmlspecialchars($activeChapter['slug']) ?>">🗑️ Delete Document</button>
                            <?php endif; ?>
                        </div>
                        <?php if ($isAdmin && $activeChapter['type'] === 'markdown'): ?>
                        <div id="edit-actions" style="display: none; gap: 0.75rem;">
                            <button class="btn btn-outline btn-sm" id="btn-cancel-edit">Cancel</button>
                            <button class="btn btn-primary btn-sm" id="btn-save-inline-markdown" data-file="<?= htmlspecialchars($activeChapter['file'] ?? '') ?>">Save Changes</button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($isAdmin && $activeChapter['type'] === 'markdown'): ?>
                    <textarea id="raw-markdown-data" style="display: none;"><?= htmlspecialchars($rawMarkdownContent) ?></textarea>
                    <div id="inline-editor-container" style="display: none; margin-top: 1rem; width: 100%;"></div>
                <?php endif; ?>
                <div class="content-body" id="content-body">
                    <?php if ($canViewContent): ?>
                        <?= $renderedContent ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 4rem 2rem;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">🔒</div>
                            <h2>Private Documentation Portal</h2>
                            <p style="color: var(--text-muted); margin-bottom: 2rem;">Authentication is required to view documentation content.</p>
                            <button class="btn btn-primary" onclick="document.getElementById('login-modal').classList.add('open')">Log In to Access</button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="content-body">
                    <h1>No Document Selected</h1>
                    <p>Select a category or document from the sidebar to view content.</p>
                </div>
            <?php endif; ?>
        </main>

        <!-- Table of Contents Sidebar -->
        <aside class="app-toc" id="app-toc">
            <div class="toc-header">Table of Contents</div>
            <div class="toc-content" id="toc-content"></div>
        </aside>
    </div>

    <!-- Login Modal -->
    <div class="modal-overlay" id="login-modal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Account Authentication</h3>
                <button class="modal-close" data-close="login-modal">&times;</button>
            </div>
            <form id="login-form">
                <div class="form-group">
                    <label class="form-label" for="login-username">Username</label>
                    <input type="text" id="login-username" name="username" class="form-control" placeholder="Enter username (default: admin)" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="login-password">Password</label>
                    <input type="password" id="login-password" name="password" class="form-control" placeholder="Enter password (default: admin)" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Log In</button>
            </form>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <!-- User Management Modal -->
    <div class="modal-overlay" id="users-modal">
        <div class="modal-card" style="max-width: 700px;">
            <div class="modal-header">
                <h3>👥 Manage Users & Permissions</h3>
                <button class="modal-close" data-close="users-modal">&times;</button>
            </div>
            
            <form id="add-user-form" style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color);">
                <h4 style="margin-bottom: 1rem; color: var(--text-primary);">Add New User</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" placeholder="e.g. john_viewer" required>
                    </div>
                    <div>
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Set password" required>
                    </div>
                    <div>
                        <label class="form-label">Role</label>
                        <select name="role" class="form-control" required>
                            <option value="viewer">Viewer (Read-only)</option>
                            <option value="admin">Admin (Full Access)</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">+ Add User</button>
            </form>

            <h4 style="margin-bottom: 1rem; color: var(--text-primary);">Existing Users</h4>
            <div id="users-list-container">
                <p style="color: var(--text-muted);">Loading users...</p>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal-overlay" id="book-modal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Add New Category</h3>
                <button class="modal-close" data-close="book-modal">&times;</button>
            </div>
            <form id="add-book-form">
                <div class="form-group">
                    <label class="form-label" for="book-title">Category Title</label>
                    <input type="text" name="title" id="book-title" class="form-control" placeholder="e.g. Developer Guides" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="book-id-input">Category Folder / Slug (Optional)</label>
                    <input type="text" name="id" id="book-id-input" class="form-control" placeholder="e.g. developer-guides">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Create Category</button>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal-overlay" id="edit-book-modal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Edit Category Title</h3>
                <button class="modal-close" data-close="edit-book-modal">&times;</button>
            </div>
            <form id="edit-book-form">
                <input type="hidden" name="bookId" id="edit-book-id-hidden">
                <div class="form-group">
                    <label class="form-label" for="edit-book-title-input">Category Title</label>
                    <input type="text" name="title" id="edit-book-title-input" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit-book-theme-input">Category Theme (Optional)</label>
                    <select name="theme" id="edit-book-theme-input" class="form-control theme-selector">
                        <option value="">-- Inherit from Site --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit-book-visibility-input">Visibility</label>
                    <select name="visibility" id="edit-book-visibility-input" class="form-control">
                        <option value="public">Public (Everyone)</option>
                        <option value="logged_in">Logged In Users Only</option>
                        <option value="admin_only">Admins Only</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Category RSS Feed URL</label>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="text" id="edit-book-rss-url" class="form-control" readonly value="" onclick="this.select();" style="flex: 1;">
                        <button type="button" class="btn btn-outline btn-copy-rss" id="btn-copy-cat-rss" title="Copy Category RSS Feed URL" data-copy-target="edit-book-rss-url">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        </button>
                    </div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-outline btn-danger-text" id="btn-delete-book">🗑️ Delete Category</button>
                    <button type="submit" class="btn btn-primary">Save Category Title</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Unified Add Document Modal -->
    <div class="modal-overlay" id="chapter-modal">
        <div class="modal-card" style="max-width: 700px;">
            <div class="modal-header">
                <h3>Add New Document</h3>
                <button class="modal-close" data-close="chapter-modal">&times;</button>
            </div>
            
            <div class="tab-header">
                <button class="tab-btn active" data-tab="tab-create-md">✏️ New Markdown</button>
                <button class="tab-btn" data-tab="tab-upload">📁 Upload File (MD/PDF)</button>
                <button class="tab-btn" data-tab="tab-gdoc">🌐 Google Doc</button>
            </div>

            <!-- Tab 1: Create Markdown Online -->
            <form id="tab-create-md" class="tab-content active">
                <div class="form-group">
                    <label class="form-label">Target Category / Folder</label>
                    <select name="bookId" class="form-control" required>
                        <?php foreach ($config['books'] as $b): ?>
                            <option value="<?= htmlspecialchars($b['id']) ?>" <?= ($activeBook && $activeBook['id'] === $b['id']) ? 'selected' : '' ?>><?= htmlspecialchars($b['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Document Title (Auto-detected from # Heading if left blank)</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Architecture Overview">
                </div>
                <div class="form-group">
                    <label class="form-label">Initial Markdown Content <span style="font-size: 0.85em; color: var(--text-muted); font-weight: normal;">(Or <a href="#" id="upload-md-link" style="color: var(--primary-color);">upload a .md file</a>)</span></label>
                    <input type="file" id="md-file-upload-input" accept=".md" style="display: none;">
                    <textarea name="content" id="md-content-textarea" class="form-control" style="min-height: 180px;" placeholder="# Document Title&#10;&#10;Write your documentation here..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Create Document</button>
            </form>

            <!-- Tab 2: Upload File -->
            <form id="tab-upload" class="tab-content" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Target Category / Folder</label>
                    <select name="bookId" class="form-control" required>
                        <?php foreach ($config['books'] as $b): ?>
                            <option value="<?= htmlspecialchars($b['id']) ?>" <?= ($activeBook && $activeBook['id'] === $b['id']) ? 'selected' : '' ?>><?= htmlspecialchars($b['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Document Title</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Specification Datasheet" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Select File (.md or .pdf)</label>
                    <input type="file" name="document" class="form-control" accept=".md,.pdf" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Upload & Add</button>
            </form>

            <!-- Tab 3: Google Doc -->
            <form id="tab-gdoc" class="tab-content">
                <div class="form-group">
                    <label class="form-label">Target Category / Folder</label>
                    <select name="bookId" class="form-control" required>
                        <?php foreach ($config['books'] as $b): ?>
                            <option value="<?= htmlspecialchars($b['id']) ?>" <?= ($activeBook && $activeBook['id'] === $b['id']) ? 'selected' : '' ?>><?= htmlspecialchars($b['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Document Title</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. User Guide Google Doc" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Published Google Doc URL</label>
                    <input type="url" name="url" class="form-control" placeholder="https://docs.google.com/document/d/e/.../pub?embedded=true" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Google Doc Edit URL (Optional)</label>
                    <input type="url" name="editUrl" class="form-control" placeholder="https://docs.google.com/document/d/.../edit">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Link Google Doc</button>
            </form>
        </div>
    </div>

    <!-- Edit Document Metadata Modal -->
    <?php if ($activeChapter): ?>
    <div class="modal-overlay" id="edit-chapter-modal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Edit Document Entry Details</h3>
                <button class="modal-close" data-close="edit-chapter-modal">&times;</button>
            </div>
            <form id="edit-chapter-form">
                <input type="hidden" name="slug" id="edit-chapter-slug-hidden" value="<?= htmlspecialchars($activeChapter['slug']) ?>">
                <div class="form-group">
                    <label class="form-label" for="edit-chapter-title">Document Title</label>
                    <input type="text" name="title" id="edit-chapter-title" class="form-control" value="<?= htmlspecialchars($activeChapter['title']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit-chapter-type">Type</label>
                    <select name="type" id="edit-chapter-type" class="form-control">
                        <option value="markdown" <?= ($activeChapter['type'] === 'markdown') ? 'selected' : '' ?>>Markdown (.md)</option>
                        <option value="gdoc" <?= ($activeChapter['type'] === 'gdoc') ? 'selected' : '' ?>>Google Doc (URL)</option>
                        <option value="pdf" <?= ($activeChapter['type'] === 'pdf') ? 'selected' : '' ?>>PDF Document (.pdf)</option>
                    </select>
                </div>
                <div class="form-group" id="group-edit-gdoc-url">
                    <label class="form-label" for="edit-chapter-url">Published Google Doc URL</label>
                    <input type="url" name="url" id="edit-chapter-url" class="form-control" value="<?= htmlspecialchars($activeChapter['url'] ?? '') ?>">
                </div>
                <div class="form-group" id="group-edit-gdoc-editurl">
                    <label class="form-label" for="edit-chapter-editurl">Google Doc Edit URL (Optional)</label>
                    <input type="url" name="editUrl" id="edit-chapter-editurl" class="form-control" value="<?= htmlspecialchars($activeChapter['editUrl'] ?? '') ?>">
                </div>
                <div class="form-group" id="group-edit-file">
                    <label class="form-label" for="edit-chapter-file">File Path</label>
                    <input type="text" name="file" id="edit-chapter-file" class="form-control" value="<?= htmlspecialchars($activeChapter['file'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit-chapter-theme">Document Theme (Optional)</label>
                    <select name="theme" id="edit-chapter-theme" class="form-control theme-selector">
                        <option value="">-- Inherit from Category/Site --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit-chapter-description">Short Description (for social sharing)</label>
                    <textarea name="description" id="edit-chapter-description" class="form-control" style="min-height: 80px;"><?= htmlspecialchars($activeChapter['description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit-chapter-image">Social Share Image URL</label>
                    <input type="url" name="image" id="edit-chapter-image" class="form-control" value="<?= htmlspecialchars($activeChapter['image'] ?? '') ?>" placeholder="https://example.com/image.jpg">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Document Details</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Qwiki Settings Modal -->
    <div class="modal-overlay" id="settings-modal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Qwiki Settings</h3>
                <button class="modal-close" data-close="settings-modal">&times;</button>
            </div>
            <form id="settings-form">
                <div class="form-group">
                    <label class="form-label" for="setting-title">Documentation Portal Title</label>
                    <input type="text" name="title" id="setting-title" class="form-control" value="<?= htmlspecialchars($config['title'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="setting-logo">Logo Text (Fallback)</label>
                    <input type="text" name="logoText" id="setting-logo" class="form-control" value="<?= htmlspecialchars($config['logoText'] ?? 'QWIKI') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="setting-logo-upload">Logo Image (Replaces Text)</label>
                    <input type="file" id="setting-logo-upload" class="form-control" accept="image/*">
                    <input type="hidden" name="logoUrl" id="setting-logo-url" value="<?= htmlspecialchars($config['logoUrl'] ?? '') ?>">
                    <?php if (!empty($config['logoUrl'])): ?>
                        <div style="margin-top: 0.5rem;">
                            <img src="<?= htmlspecialchars($config['logoUrl']) ?>" style="max-height: 40px; border-radius: 4px; background: #fff; padding: 4px;">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label" for="setting-site-theme">Site Default Theme</label>
                    <select name="theme" id="setting-site-theme" class="form-control theme-selector">
                        <option value="theme-default.css">theme-default.css</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="showDocTypesOnlyToAdmin" value="1" <?= $showDocTypesOnlyToAdmin ? 'checked' : '' ?>>
                        Show Document Type Badges Only to Admin Users
                    </label>
                </div>
                <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--border-color);">
                <div class="form-group">
                    <label class="form-label" for="setting-share-desc">Global Social Share Description</label>
                    <textarea name="shareDescription" id="setting-share-desc" class="form-control" style="min-height: 80px;"><?= htmlspecialchars($config['shareDescription'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="setting-share-img">Global Social Share Image URL</label>
                    <input type="url" name="shareImageUrl" id="setting-share-img" class="form-control" value="<?= htmlspecialchars($config['shareImageUrl'] ?? '') ?>" placeholder="https://example.com/banner.jpg">
                </div>
                <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--border-color);">
                <div class="form-group">
                    <label class="form-label" for="setting-default-book">Default Category</label>
                    <select name="defaultBook" id="setting-default-book" class="form-control">
                        <?php foreach ($config['books'] as $b): ?>
                            <option value="<?= htmlspecialchars($b['id']) ?>" <?= (($config['defaultBook'] ?? '') === $b['id']) ? 'selected' : '' ?>><?= htmlspecialchars($b['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="setting-access-mode">Access Mode</label>
                    <select name="requireLoginToView" id="setting-access-mode" class="form-control">
                        <option value="0" <?= empty($config['requireLoginToView']) ? 'selected' : '' ?>>Public Access (Anyone can view docs without logging in)</option>
                        <option value="1" <?= !empty($config['requireLoginToView']) ? 'selected' : '' ?>>Private Portal (Login required to view documentation)</option>
                    </select>
                </div>
                <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--border-color);">
                <div class="form-group">
                    <label class="form-label" for="setting-feed-item-count">RSS Feed Item Count</label>
                    <input type="number" name="feedItemCount" id="setting-feed-item-count" class="form-control" value="<?= htmlspecialchars($config['feedItemCount'] ?? '10') ?>" min="1" max="100">
                </div>
                <div class="form-group">
                    <label class="form-label" for="setting-feed-token">RSS Feed Access Token (For Private Mode)</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" name="feedAccessToken" id="setting-feed-token" class="form-control" value="<?= htmlspecialchars($config['feedAccessToken'] ?? '') ?>" placeholder="Leave blank to disable">
                        <button type="button" class="btn btn-outline" onclick="document.getElementById('setting-feed-token').value = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);">Generate</button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">RSS Feed URL</label>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="text" id="setting-rss-feed-url" class="form-control" readonly value="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] === '/' || $_SERVER['SCRIPT_NAME'] === '\\' ? '' : $_SERVER['SCRIPT_NAME']), '/\\') . '/api/feed.php' . (!empty($config['feedAccessToken']) ? '?token=' . urlencode($config['feedAccessToken']) : '')) ?>" onclick="this.select();" style="flex: 1;">
                        <button type="button" class="btn btn-outline btn-copy-rss" id="btn-copy-main-rss" title="Copy RSS Feed URL" data-copy-target="setting-rss-feed-url">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        </button>
                    </div>
                </div>
                <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--border-color);">
                <div class="form-group">
                    <label class="form-label" for="setting-new-password">Change Admin Password</label>
                    <input type="password" name="newAdminPassword" id="setting-new-password" class="form-control" placeholder="Leave blank to keep current password">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Settings</button>
            </form>
        </div>
    </div>

    <!-- Theme Editor Modal -->
    <div class="modal-overlay" id="theme-editor-modal">
        <div class="modal-card" style="max-width: 800px;">
            <div class="modal-header">
                <h3>🎨 Theme Editor</h3>
                <button class="modal-close" data-close="theme-editor-modal">&times;</button>
            </div>
            <div class="form-group">
                <label class="form-label">Select Theme to Edit</label>
                <div style="display: flex; gap: 0.5rem;">
                    <select id="editor-theme-selector" class="form-control theme-selector" style="flex: 1;">
                        <option value="">-- Select a Theme --</option>
                    </select>
                    <button class="btn btn-outline" id="btn-load-theme">Load</button>
                </div>
            </div>
            <div class="form-group" id="theme-editor-area" style="display: none;">
                <label class="form-label">CSS Content</label>
                <textarea id="theme-css-content" class="form-control" style="font-family: monospace; min-height: 300px;"></textarea>
                
                <label class="form-label" style="margin-top: 1rem;">Save As (Filename)</label>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" id="theme-filename" class="form-control" placeholder="theme-custom.css" style="flex: 1;">
                    <button class="btn btn-primary" id="btn-save-theme">Save Theme</button>
                </div>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">Must start with <code>theme-</code> and end with <code>.css</code>.</p>
            </div>
        </div>
    </div>

    <!-- Update Available Modal -->
    <div class="modal-overlay" id="update-modal">
        <div class="modal-card" style="max-width: 700px;">
            <div class="modal-header">
                <h3>🎉 New Update Available!</h3>
                <button class="modal-close" data-close="update-modal">&times;</button>
            </div>
            <div style="margin-bottom: 1.5rem;">
                <p>A new version of Standalone Qwiki is available: <strong id="update-version-text"></strong> (Current: <?= QWIKI_VERSION ?>)</p>
                <div id="update-release-notes" style="background: var(--bg-color); padding: 1rem; border-radius: 6px; border: 1px solid var(--border-color); max-height: 300px; overflow-y: auto; margin-top: 1rem; font-size: 0.9rem;"></div>
            </div>
            <form id="update-form">
                <input type="hidden" name="zip_url" id="update-zip-url">
                <button type="submit" class="btn btn-primary" id="btn-install-update" style="width: 100%;">Download & Install Update</button>
                <p id="update-loading-text" style="display: none; text-align: center; color: var(--primary-color); margin-top: 0.5rem;">Installing update... Please wait and do not close this page.</p>
            </form>
        </div>
    </div>

    <?php endif; ?>

    <script src="https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js"></script>
    <script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
</body>
</html>
