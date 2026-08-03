<?php
session_start();
require_once __DIR__ . '/lib/Parsedown.php';
require_once __DIR__ . '/lib/simple_html_dom.php';

$configFile = __DIR__ . '/qwiki.json';
if (!file_exists($configFile)) {
    die("Configuration file qwiki.json not found.");
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

// Active tree resolution state
$activeBook = null;
foreach ($config['books'] as $book) {
    if ($book['id'] === $requestedBookId) {
        $activeBook = $book;
        break;
    }
}
if (!$activeBook && !empty($config['books'])) {
    $activeBook = $config['books'][0];
}

$activeChapter = null;
$breadcrumbsTrail = [];
$activePathIds = [$activeBook['id']];

/**
 * Recursive search to locate active folder & chapter, building breadcrumb trail and active path IDs
 */
function find_chapter_and_path($node, $targetFolderId, $targetChapterSlug, &$trail, &$activeIds) {
    $nodeId = $node['id'] ?? '';
    $nodeTitle = $node['title'] ?? '';
    $currentTrail = array_merge($trail, [['title' => $nodeTitle, 'id' => $nodeId]]);
    $currentActiveIds = array_merge($activeIds, [$nodeId]);

    $isFolderMatch = ($targetFolderId && $nodeId === $targetFolderId);

    if (!empty($node['chapters'])) {
        if ($targetChapterSlug) {
            foreach ($node['chapters'] as $ch) {
                if ($ch['slug'] === $targetChapterSlug) {
                    if (!$targetFolderId || $isFolderMatch) {
                        $trail = $currentTrail;
                        $activeIds = $currentActiveIds;
                        return $ch;
                    }
                }
            }
        } elseif ($isFolderMatch) {
            $trail = $currentTrail;
            $activeIds = $currentActiveIds;
            return $node['chapters'][0];
        }
    }

    if (!empty($node['subfolders'])) {
        foreach ($node['subfolders'] as $sub) {
            $found = find_chapter_and_path($sub, $targetFolderId, $targetChapterSlug, $currentTrail, $currentActiveIds);
            if ($found) {
                $trail = $currentTrail;
                $activeIds = $currentActiveIds;
                return $found;
            }
        }
    }

    if (!$targetFolderId && !$targetChapterSlug && !empty($node['chapters'])) {
        $trail = $currentTrail;
        $activeIds = $currentActiveIds;
        return $node['chapters'][0];
    }

    return null;
}

$dummyTrail = [];
$dummyIds = [];
$activeChapter = find_chapter_and_path($activeBook, $requestedFolderId, $requestedChapterSlug, $dummyTrail, $dummyIds);

if ($activeChapter) {
    $breadcrumbsTrail = $dummyTrail;
    $activePathIds = array_unique(array_merge([$activeBook['id']], $dummyIds));
} else {
    if (!empty($activeBook['chapters'])) {
        $activeChapter = $activeBook['chapters'][0];
        $breadcrumbsTrail = [['title' => $activeBook['title'], 'id' => $activeBook['id']]];
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
            $parsedown = new Parsedown();
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

/**
 * Recursive function to render sidebar navigation with subfolders
 */
function render_sidebar_node($node, $bookId, $activePathIds, $activeChapterSlug, $depth = 0, $isAdmin = false) {
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
        echo "<button class='btn-edit-cat-icon' data-book-id='" . htmlspecialchars($nodeId) . "' data-book-title='" . htmlspecialchars($nodeTitle) . "' title='Rename Category'>✏️</button> ";
    }
    echo "<span class='chevron-icon'>▾</span>";
    echo "</span>";
    echo "</div>";

    echo "<div class='nav-document-list' data-parent-node-id='" . htmlspecialchars($nodeId) . "'>";

    if (!empty($node['chapters'])) {
        foreach ($node['chapters'] as $ch) {
            $isActive = ($isExpanded && $activeChapterSlug === $ch['slug']);
            $badgeClass = 'badge-' . htmlspecialchars($ch['type']);
            $linkUrl = "index.php?book=" . urlencode($bookId) . "&folder=" . urlencode($nodeId) . "&chapter=" . urlencode($ch['slug']);
            
            $docDragAttr = $isAdmin ? "draggable='true' data-drag-type='document' data-doc-title='" . htmlspecialchars($ch['title']) . "' data-doc-slug='" . htmlspecialchars($ch['slug']) . "' data-doc-type='" . htmlspecialchars($ch['type']) . "' data-doc-url='" . htmlspecialchars($ch['url'] ?? '') . "' data-doc-editurl='" . htmlspecialchars($ch['editUrl'] ?? '') . "' data-doc-file='" . htmlspecialchars($ch['file'] ?? '') . "'" : "";

            echo "<a href='{$linkUrl}' class='nav-link " . ($isActive ? 'active' : '') . "' {$docDragAttr}>";
            echo "<span>";
            if ($isAdmin) echo "<span class='drag-handle' title='Drag to reorder'>⣿</span> ";
            echo htmlspecialchars($ch['title']) . "</span>";
            echo "<span class='doc-badge {$badgeClass}'>" . htmlspecialchars($ch['type']) . "</span>";
            echo "</a>";
        }
    }

    if (!empty($node['subfolders'])) {
        foreach ($node['subfolders'] as $sub) {
            render_sidebar_node($sub, $bookId, $activePathIds, $activeChapterSlug, $depth + 1, $isAdmin);
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
    <link rel="stylesheet" href="assets/css/qwiki.css">
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
                ⚡ <?= htmlspecialchars($config['logoText'] ?? 'QWIKI') ?>
            </a>
        </div>
        <div class="header-actions">
            <button class="btn btn-outline btn-sm" id="theme-toggle" title="Toggle Dark/Light Mode">🌙 Theme</button>
            <?php if ($isViewer): ?>
                <span class="doc-badge badge-md"><?= htmlspecialchars($currentUser['username'] ?? 'User') ?> (<?= htmlspecialchars(ucfirst($currentUser['role'] ?? 'viewer')) ?>)</span>
                <?php if ($isAdmin): ?>
                    <button class="btn btn-outline btn-sm" id="btn-add-book">+ Category</button>
                    <button class="btn btn-primary btn-sm" id="btn-add-chapter">+ Document</button>
                    <button class="btn btn-outline btn-sm" id="btn-users">👥 Users</button>
                    <button class="btn btn-outline btn-sm" id="btn-settings">⚙️ Settings</button>
                <?php endif; ?>
                <button class="btn btn-outline btn-sm" id="btn-logout">Logout</button>
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
                    <?php render_sidebar_node($book, $book['id'], $activePathIds, $activeChapter['slug'] ?? '', 0, $isAdmin); ?>
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
                                        data-file="<?= htmlspecialchars($activeChapter['file'] ?? '') ?>">⚙️ Edit Details</button>
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
                    <label class="form-label">Initial Markdown Content</label>
                    <textarea name="content" class="form-control" style="min-height: 180px;" placeholder="# Document Title&#10;&#10;Write your documentation here..."></textarea>
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
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Document Details</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Wiki Settings Modal -->
    <div class="modal-overlay" id="settings-modal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Wiki Settings</h3>
                <button class="modal-close" data-close="settings-modal">&times;</button>
            </div>
            <form id="settings-form">
                <div class="form-group">
                    <label class="form-label" for="setting-title">Documentation Portal Title</label>
                    <input type="text" name="title" id="setting-title" class="form-control" value="<?= htmlspecialchars($config['title'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="setting-logo">Logo Text</label>
                    <input type="text" name="logoText" id="setting-logo" class="form-control" value="<?= htmlspecialchars($config['logoText'] ?? 'QWIKI') ?>" required>
                </div>
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
                    <label class="form-label" for="setting-new-password">Change Admin Password</label>
                    <input type="password" name="newAdminPassword" id="setting-new-password" class="form-control" placeholder="Leave blank to keep current password">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Settings</button>
            </form>
        </div>
    </div>

    <!-- Edit Markdown Modal Removed in favor of inline editor -->
    <?php endif; ?>

    <script src="https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
