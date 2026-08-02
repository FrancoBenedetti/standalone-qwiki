<?php
session_start();
require_once __DIR__ . '/lib/Parsedown.php';
require_once __DIR__ . '/lib/simple_html_dom.php';

$configFile = __DIR__ . '/qwiki.json';
if (!file_exists($configFile)) {
    die("Configuration file qwiki.json not found.");
}

$config = json_decode(file_get_contents($configFile), true);
$isAdmin = !empty($_SESSION['qwiki_admin']);

// Routing parameters
$requestedBookId = $_GET['book'] ?? $config['defaultBook'] ?? ($config['books'][0]['id'] ?? '');
$requestedChapterSlug = $_GET['chapter'] ?? '';

// Find active book and active chapter
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
if ($activeBook && !empty($activeBook['chapters'])) {
    if ($requestedChapterSlug) {
        foreach ($activeBook['chapters'] as $ch) {
            if ($ch['slug'] === $requestedChapterSlug) {
                $activeChapter = $ch;
                break;
            }
        }
    }
    if (!$activeChapter) {
        $activeChapter = $activeBook['chapters'][0];
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
            <?php if ($isAdmin): ?>
                <span class="doc-badge badge-md">Admin</span>
                <button class="btn btn-outline btn-sm" id="btn-add-book">+ Book</button>
                <button class="btn btn-primary btn-sm" id="btn-add-chapter">+ Chapter</button>
                <button class="btn btn-outline btn-sm" id="btn-settings">⚙️ Settings</button>
                <button class="btn btn-outline btn-sm" id="btn-logout">Logout</button>
            <?php else: ?>
                <button class="btn btn-outline btn-sm" id="btn-login">Admin Login</button>
            <?php endif; ?>
        </div>
    </header>

    <div class="app-container">
        <!-- Sidebar Navigation -->
        <aside class="app-sidebar" id="app-sidebar">
            <div class="sidebar-search">
                <input type="text" id="search-input" class="search-input" placeholder="Search documentation...">
            </div>
            <nav class="sidebar-nav">
                <?php foreach ($config['books'] as $book): ?>
                    <div class="nav-book-title"><?= htmlspecialchars($book['title']) ?></div>
                    <?php if (!empty($book['chapters'])): ?>
                        <?php foreach ($book['chapters'] as $ch): ?>
                            <?php 
                                $isActive = ($activeBook['id'] === $book['id'] && $activeChapter['slug'] === $ch['slug']); 
                                $badgeClass = 'badge-' . htmlspecialchars($ch['type']);
                            ?>
                            <a href="index.php?book=<?= urlencode($book['id']) ?>&chapter=<?= urlencode($ch['slug']) ?>" 
                               class="nav-link <?= $isActive ? 'active' : '' ?>">
                                <span><?= htmlspecialchars($ch['title']) ?></span>
                                <span class="doc-badge <?= $badgeClass ?>"><?= htmlspecialchars($ch['type']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="app-content">
            <?php if ($activeChapter): ?>
                <div class="content-header">
                    <div class="breadcrumbs">
                        <a href="index.php?book=<?= urlencode($activeBook['id']) ?>"><?= htmlspecialchars($activeBook['title']) ?></a>
                        <span>/</span>
                        <span><?= htmlspecialchars($activeChapter['title']) ?></span>
                    </div>
                    <div class="content-actions">
                        <?php if ($activeChapter['type'] === 'gdoc' && !empty($activeChapter['editUrl'])): ?>
                            <a href="<?= htmlspecialchars($activeChapter['editUrl']) ?>" target="_blank" class="btn btn-outline btn-sm">Edit Google Doc ↗</a>
                        <?php endif; ?>
                        <?php if ($isAdmin): ?>
                            <?php if ($activeChapter['type'] === 'markdown'): ?>
                                <button class="btn btn-primary btn-sm" id="btn-edit-markdown">✏️ Edit Page</button>
                            <?php endif; ?>
                            <button class="btn btn-outline btn-sm btn-danger-text" id="btn-delete-chapter" data-book="<?= htmlspecialchars($activeBook['id']) ?>" data-slug="<?= htmlspecialchars($activeChapter['slug']) ?>">🗑️ Delete Chapter</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="content-body">
                    <?= $renderedContent ?>
                </div>
            <?php else: ?>
                <div class="content-body">
                    <h1>No Documentation Selected</h1>
                    <p>Select a book or chapter from the sidebar to view content.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Login Modal -->
    <div class="modal-overlay" id="login-modal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Admin Authentication</h3>
                <button class="modal-close" data-close="login-modal">&times;</button>
            </div>
            <form id="login-form">
                <div class="form-group">
                    <label class="form-label" for="admin-password">Admin Password</label>
                    <input type="password" id="admin-password" name="password" class="form-control" placeholder="Enter password (default: admin)" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Log In</button>
            </form>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <!-- Add Book Modal -->
    <div class="modal-overlay" id="book-modal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Add New Book Category</h3>
                <button class="modal-close" data-close="book-modal">&times;</button>
            </div>
            <form id="add-book-form">
                <div class="form-group">
                    <label class="form-label" for="book-title">Book Title</label>
                    <input type="text" name="title" id="book-title" class="form-control" placeholder="e.g. Developer APIs" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="book-id-input">Book Slug / Folder (Optional)</label>
                    <input type="text" name="id" id="book-id-input" class="form-control" placeholder="e.g. developer-apis">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Create Book</button>
            </form>
        </div>
    </div>

    <!-- Unified Add Chapter Modal -->
    <div class="modal-overlay" id="chapter-modal">
        <div class="modal-card" style="max-width: 700px;">
            <div class="modal-header">
                <h3>Add New Chapter</h3>
                <button class="modal-close" data-close="chapter-modal">&times;</button>
            </div>
            
            <!-- Type Selector Tabs -->
            <div class="tab-header">
                <button class="tab-btn active" data-tab="tab-create-md">✏️ New Markdown</button>
                <button class="tab-btn" data-tab="tab-upload">📁 Upload File (MD/PDF)</button>
                <button class="tab-btn" data-tab="tab-gdoc">🌐 Google Doc</button>
            </div>

            <!-- Tab 1: Create Markdown Online -->
            <form id="form-create-md" class="tab-content active">
                <div class="form-group">
                    <label class="form-label">Target Book</label>
                    <select name="bookId" class="form-control" required>
                        <?php foreach ($config['books'] as $b): ?>
                            <option value="<?= htmlspecialchars($b['id']) ?>" <?= ($activeBook && $activeBook['id'] === $b['id']) ? 'selected' : '' ?>><?= htmlspecialchars($b['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Chapter Title</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Architecture Overview" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Initial Markdown Content</label>
                    <textarea name="content" class="form-control" style="min-height: 180px;" placeholder="# Chapter Title&#10;&#10;Write your documentation here..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Create Chapter</button>
            </form>

            <!-- Tab 2: Upload File -->
            <form id="form-upload-file" class="tab-content" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Target Book</label>
                    <select name="bookId" class="form-control" required>
                        <?php foreach ($config['books'] as $b): ?>
                            <option value="<?= htmlspecialchars($b['id']) ?>" <?= ($activeBook && $activeBook['id'] === $b['id']) ? 'selected' : '' ?>><?= htmlspecialchars($b['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Chapter Title</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Specification Datasheet" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Select File (.md or .pdf)</label>
                    <input type="file" name="document" class="form-control" accept=".md,.pdf" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Upload & Add</button>
            </form>

            <!-- Tab 3: Google Doc -->
            <form id="form-add-gdoc" class="tab-content">
                <div class="form-group">
                    <label class="form-label">Target Book</label>
                    <select name="bookId" class="form-control" required>
                        <?php foreach ($config['books'] as $b): ?>
                            <option value="<?= htmlspecialchars($b['id']) ?>" <?= ($activeBook && $activeBook['id'] === $b['id']) ? 'selected' : '' ?>><?= htmlspecialchars($b['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Chapter Title</label>
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
                    <label class="form-label" for="setting-default-book">Default Book</label>
                    <select name="defaultBook" id="setting-default-book" class="form-control">
                        <?php foreach ($config['books'] as $b): ?>
                            <option value="<?= htmlspecialchars($b['id']) ?>" <?= (($config['defaultBook'] ?? '') === $b['id']) ? 'selected' : '' ?>><?= htmlspecialchars($b['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Settings</button>
            </form>
        </div>
    </div>

    <!-- Edit Markdown Modal -->
    <?php if ($activeChapter && $activeChapter['type'] === 'markdown'): ?>
    <div class="modal-overlay" id="editor-modal">
        <div class="modal-card" style="max-width: 900px;">
            <div class="modal-header">
                <h3>Edit Markdown: <?= htmlspecialchars($activeChapter['title']) ?></h3>
                <button class="modal-close" data-close="editor-modal">&times;</button>
            </div>
            <div class="form-group">
                <textarea id="markdown-editor-textarea" class="form-control"><?= htmlspecialchars($rawMarkdownContent) ?></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                <button class="btn btn-outline" data-close="editor-modal">Cancel</button>
                <button class="btn btn-primary" id="btn-save-markdown" data-file="<?= htmlspecialchars($activeChapter['file']) ?>">Save Changes</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <script src="assets/js/app.js"></script>
</body>
</html>
