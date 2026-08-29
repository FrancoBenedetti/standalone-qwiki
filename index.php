<?php
require_once __DIR__ . '/lib/Parsedown.php';
require_once __DIR__ . '/lib/simple_html_dom.php';
require_once __DIR__ . '/lib/Core/Config.php';
require_once __DIR__ . '/lib/Core/Auth.php';
require_once __DIR__ . '/lib/Core/Navigation.php';
require_once __DIR__ . '/lib/Core/ExtensionManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\Navigation;
use Qwiki\Core\ExtensionManager;

Auth::startSession();
if (!defined('QWIKI_VERSION')) {
    define('QWIKI_VERSION', Config::VERSION);
}

class QwikiParsedown extends Parsedown {
    protected function inlineLink($Excerpt) {
        $Inline = parent::inlineLink($Excerpt);
        if (!isset($Inline)) {
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

$config = Config::load();
$baseDir = Config::getBaseDir();
$extManager = ExtensionManager::getInstance();

// User session evaluation
$currentUser = Auth::getCurrentUser();
$isAdmin = Auth::isAdmin();
$isViewer = Auth::isViewer();
$canViewContent = Auth::canView($config);

// Routing parameters
$requestedBookId = '';
$requestedFolderId = '';
$requestedChapterSlug = '';

// Base URL calculation for clean URLs
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] === '/' || $_SERVER['SCRIPT_NAME'] === '\\' ? '' : $_SERVER['SCRIPT_NAME']), '/\\');
$baseUrl = $protocol . $domainName . $scriptDir . '/';

// Determine requested path across all server environments
$rawPath = '';
if (isset($_GET['path']) && !empty(trim($_GET['path'], '/'))) {
    $rawPath = trim($_GET['path'], '/');
} elseif (!empty($_SERVER['PATH_INFO'])) {
    $rawPath = trim($_SERVER['PATH_INFO'], '/');
} elseif (!empty($_SERVER['REQUEST_URI'])) {
    $parsedPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($parsedPath) {
        if ($scriptDir !== '' && strpos($parsedPath, $scriptDir) === 0) {
            $parsedPath = substr($parsedPath, strlen($scriptDir));
        }
        $parsedPath = preg_replace('#^/index\.php(/|$)#', '$1', $parsedPath);
        $rawPath = trim($parsedPath, '/');
    }
}

if (!empty($rawPath)) {
    $segments = explode('/', $rawPath);
    $segCount = count($segments);
    $requestedBookId = urldecode($segments[0] ?? '');
    if ($segCount === 1) {
        $requestedFolderId = '';
        $requestedChapterSlug = '';
    } elseif ($segCount === 2) {
        $requestedFolderId = '';
        $requestedChapterSlug = urldecode($segments[1] ?? '');
    } else {
        $requestedFolderId = urldecode($segments[1] ?? '');
        $requestedChapterSlug = urldecode($segments[$segCount - 1] ?? '');
    }
} else {
    $requestedBookId = $_GET['book'] ?? '';
    $requestedFolderId = $_GET['folder'] ?? $_GET['dir'] ?? '';
    $requestedChapterSlug = $_GET['chapter'] ?? $_GET['doc'] ?? '';
}

if (empty($requestedBookId)) {
    $requestedBookId = $config['defaultBook'] ?? ($config['books'][0]['id'] ?? '');
}

// Filter books based on visibility
$allowedBooks = Navigation::filterBooks($config['books'] ?? [], $isAdmin, $isViewer);

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
$activePathIds = $activeBook ? [$activeBook['id']] : [];

if ($activeBook) {
    $dummyTrail = [];
    $dummyIds = [];
    $activeChapter = Navigation::findChapterAndPath($activeBook, $requestedFolderId, $requestedChapterSlug, $dummyTrail, $dummyIds, $isAdmin, $isViewer);

    // Fallback: 2-segment URL might be a subfolder instead of a chapter (e.g. /book/subfolder)
    if (!$activeChapter && !empty($requestedChapterSlug) && empty($requestedFolderId)) {
        $dummyTrail = [];
        $dummyIds = [];
        $fallbackFolderId = $requestedChapterSlug;
        $activeChapter = Navigation::findChapterAndPath($activeBook, $fallbackFolderId, '', $dummyTrail, $dummyIds, $isAdmin, $isViewer);
    }

    if ($activeChapter) {
        $breadcrumbsTrail = $dummyTrail;
        $activePathIds = array_unique(array_merge([$activeBook['id']], $dummyIds));
    } else {
        // Fallback to first document in the active book
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
}

// Calculate Previous and Next Document Navigation
$flatNavList = [];
foreach ($allowedBooks as $book) {
    Navigation::flattenNavTree($book, $book['id'], $flatNavList, $isAdmin, $isViewer);
}

$prevDoc = null;
$nextDoc = null;
if ($activeChapter) {
    $currentIndex = -1;
    foreach ($flatNavList as $index => $item) {
        if ($item['bookId'] === ($activeBook['id'] ?? '') && $item['slug'] === ($activeChapter['slug'] ?? '')) {
            $currentIndex = $index;
            break;
        }
    }
    if ($currentIndex !== -1) {
        if ($currentIndex > 0) {
            $prevDoc = $flatNavList[$currentIndex - 1];
        }
        if ($currentIndex < count($flatNavList) - 1) {
            $nextDoc = $flatNavList[$currentIndex + 1];
        }
    }
}

// Content rendering via ExtensionManager
$renderedContent = '';
$rawMarkdownContent = '';
if ($activeChapter) {
    $renderedContent = $extManager->renderPage($activeChapter, $activeBook, $config);
    if (($activeChapter['type'] ?? 'markdown') === 'markdown') {
        $filePath = $baseDir . '/' . ($activeChapter['file'] ?? '');
        if (file_exists($filePath)) {
            $rawMarkdownContent = file_get_contents($filePath);
        }
    }
}

// Theme Resolution
$siteTheme = $config['theme'] ?? 'theme-default.css';
$categoryTheme = $activeBook['theme'] ?? null;
$chapterTheme = $activeChapter['theme'] ?? null;
$resolvedTheme = $chapterTheme ?: $categoryTheme ?: $siteTheme;
$showDocTypesOnlyToAdmin = isset($config['showDocTypesOnlyToAdmin']) ? !empty($config['showDocTypesOnlyToAdmin']) : true;

// Collect Frontend Assets from Extensions
$extensionAssets = $extManager->getFrontendAssets();
$userTheme = isset($_COOKIE['qwiki_theme']) && in_array($_COOKIE['qwiki_theme'], ['light', 'dark']) ? $_COOKIE['qwiki_theme'] : 'dark';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $userTheme ?>">
<head>
    <meta charset="UTF-8">
    <script>
        (function() {
            try {
                var savedTheme = localStorage.getItem('qwiki_theme');
                if (savedTheme && (savedTheme === 'light' || savedTheme === 'dark')) {
                    document.documentElement.setAttribute('data-theme', savedTheme);
                }
                var savedWidth = localStorage.getItem('qwiki_sidebar_width');
                if (savedWidth) {
                    document.documentElement.style.setProperty('--sidebar-width', savedWidth + 'px');
                }
            } catch(e) {}
        })();
    </script>
    <base href="<?= htmlspecialchars($baseUrl) ?>">
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

    <!-- Extension Styles -->
    <?php foreach ($extensionAssets['styles'] as $styleFile): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($styleFile) ?>">
    <?php endforeach; ?>

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
            <a href="<?= htmlspecialchars($baseUrl) ?>" class="brand-logo">
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
                            <?php $extManager->renderHeaderUtilityButtons(); ?>
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
                    <?php Navigation::renderSidebarNode($book, $book['id'], $activePathIds, $activeChapter['slug'] ?? '', 0, $isAdmin, $isViewer, $showDocTypesOnlyToAdmin, $extManager); ?>
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
                            <?php 
                            $crumbUrl = urlencode($activeBook['id']);
                            if ($crumb['id'] !== $activeBook['id']) {
                                $crumbUrl .= '/' . urlencode($crumb['id']);
                            }
                            ?>
                            <a href="<?= $crumbUrl ?>"><?= htmlspecialchars($crumb['title']) ?></a>
                        <?php endforeach; ?>
                        <span>/</span>
                        <span><?= htmlspecialchars($activeChapter['title']) ?></span>
                    </div>
                    <div class="content-actions">
                        <div id="read-actions" style="display: flex; gap: 0.75rem;">
                            <?php if (($activeChapter['type'] ?? '') === 'gdoc' && !empty($activeChapter['editUrl'])): ?>
                                <a href="<?= htmlspecialchars($activeChapter['editUrl']) ?>" target="_blank" class="btn btn-outline btn-sm">Edit Google Doc ↗</a>
                            <?php endif; ?>
                            <?php if ($isAdmin): ?>
                                <?php if (($activeChapter['type'] ?? 'markdown') === 'markdown'): ?>
                                    <button class="btn btn-primary btn-sm" id="btn-edit-markdown">✏️ Edit Content</button>
                                <?php endif; ?>
                                <button class="btn btn-outline btn-sm" id="btn-edit-chapter-meta"
                                        data-title="<?= htmlspecialchars($activeChapter['title']) ?>"
                                        data-slug="<?= htmlspecialchars($activeChapter['slug']) ?>"
                                        data-type="<?= htmlspecialchars($activeChapter['type'] ?? 'markdown') ?>"
                                        data-url="<?= htmlspecialchars($activeChapter['url'] ?? '') ?>"
                                        data-edit-url="<?= htmlspecialchars($activeChapter['editUrl'] ?? '') ?>"
                                        data-file="<?= htmlspecialchars($activeChapter['file'] ?? '') ?>"
                                        data-theme="<?= htmlspecialchars($activeChapter['theme'] ?? '') ?>">⚙️ Edit Details</button>
                                <button class="btn btn-outline btn-sm btn-danger-text" id="btn-delete-chapter" data-book="<?= htmlspecialchars($activeBook['id'] ?? '') ?>" data-slug="<?= htmlspecialchars($activeChapter['slug']) ?>">🗑️ Delete Document</button>
                            <?php endif; ?>
                        </div>
                        <?php if ($isAdmin && ($activeChapter['type'] ?? 'markdown') === 'markdown'): ?>
                        <div id="edit-actions" style="display: none; gap: 0.75rem;">
                            <button class="btn btn-outline btn-sm" id="btn-cancel-edit">Cancel</button>
                            <button class="btn btn-primary btn-sm" id="btn-save-inline-markdown" data-file="<?= htmlspecialchars($activeChapter['file'] ?? '') ?>">Save Changes</button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($isAdmin && ($activeChapter['type'] ?? 'markdown') === 'markdown'): ?>
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
            <?php if ($activeChapter && ($prevDoc || $nextDoc)): ?>
                <div class="toc-nav-buttons" style="display: flex; justify-content: space-between; gap: 0.5rem; margin-bottom: 1.5rem;">
                    <?php if ($prevDoc): ?>
                        <a href="<?= htmlspecialchars($prevDoc['url']) ?>" class="btn btn-outline btn-sm" style="flex: 1; text-align: center; padding: 0.4rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="Previous: <?= htmlspecialchars($prevDoc['title']) ?>">&laquo; Prev</a>
                    <?php else: ?>
                        <div style="flex: 1;"></div>
                    <?php endif; ?>
                    
                    <?php if ($nextDoc): ?>
                        <a href="<?= htmlspecialchars($nextDoc['url']) ?>" class="btn btn-outline btn-sm" style="flex: 1; text-align: center; padding: 0.4rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="Next: <?= htmlspecialchars($nextDoc['title']) ?>">Next &raquo;</a>
                    <?php else: ?>
                        <div style="flex: 1;"></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

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

    <!-- Unified Add Document Modal (Dynamic with Extensions) -->
    <div class="modal-overlay" id="chapter-modal">
        <div class="modal-card" style="max-width: 700px;">
            <div class="modal-header">
                <h3>Add New Document</h3>
                <button class="modal-close" data-close="chapter-modal">&times;</button>
            </div>
            
            <div class="tab-header">
                <button class="tab-btn active" data-tab="tab-create-md">✏️ New Markdown</button>
                <button class="tab-btn" data-tab="tab-upload">📁 Upload File (MD/PDF/HTML)</button>
                <button class="tab-btn" data-tab="tab-gdoc">🌐 Google Doc</button>
                <?php $extManager->renderAddDocumentTabs(); ?>
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
                    <label class="form-label">Select File (.md, .pdf, or .html)</label>
                    <input type="file" name="document" class="form-control" accept=".md,.pdf,.html,.htm" required>
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

            <!-- Dynamic Extension Tabs -->
            <?php $extManager->renderAddDocumentForms($activeBook, $config); ?>
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
                        <option value="markdown" <?= (($activeChapter['type'] ?? 'markdown') === 'markdown') ? 'selected' : '' ?>>Markdown (.md)</option>
                        <option value="gdoc" <?= (($activeChapter['type'] ?? '') === 'gdoc') ? 'selected' : '' ?>>Google Doc (URL)</option>
                        <option value="pdf" <?= (($activeChapter['type'] ?? '') === 'pdf') ? 'selected' : '' ?>>PDF Document (.pdf)</option>
                        <?php foreach ($extManager->getPageTypes() as $ptId => $pt): ?>
                            <option value="<?= htmlspecialchars($ptId) ?>" <?= (($activeChapter['type'] ?? '') === $ptId) ? 'selected' : '' ?>><?= htmlspecialchars($pt['title'] ?? ucfirst($ptId)) ?></option>
                        <?php endforeach; ?>
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
            <form id="settings-form" autocomplete="off">
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
                        <input type="text" id="setting-rss-feed-url" class="form-control" readonly value="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $scriptDir . '/api/feed.php' . (!empty($config['feedAccessToken']) ? '?token=' . urlencode($config['feedAccessToken']) : '')) ?>" onclick="this.select();" style="flex: 1;">
                        <button type="button" class="btn btn-outline btn-copy-rss" id="btn-copy-main-rss" title="Copy RSS Feed URL" data-copy-target="setting-rss-feed-url">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1.5rem;">Save Settings</button>
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
                <div id="update-release-notes" class="article-content" style="background: var(--bg-color); padding: 1.25rem; border-radius: 6px; border: 1px solid var(--border-color); max-height: 350px; overflow-y: auto; margin-top: 1rem; font-size: 0.9rem; line-height: 1.6;"></div>
            </div>
            <form id="update-form">
                <input type="hidden" name="zip_url" id="update-zip-url">
                <button type="submit" class="btn btn-primary" id="btn-install-update" style="width: 100%;">Download & Install Update</button>
                <p id="update-loading-text" style="display: none; text-align: center; color: var(--primary-color); margin-top: 0.5rem;">Installing update... Please wait and do not close this page.</p>
            </form>
        </div>
    </div>

    <!-- Utility Modals Injected by Extensions -->
    <?php $extManager->renderUtilityModals(); ?>

    <?php endif; ?>

    <script src="https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
    <script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>

    <!-- Extension Scripts -->
    <?php foreach ($extensionAssets['scripts'] as $scriptFile): ?>
        <script src="<?= htmlspecialchars($scriptFile) ?>"></script>
    <?php endforeach; ?>
</body>
</html>
