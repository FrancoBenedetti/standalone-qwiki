<?php
/**
 * HTML Page Type Renderer
 * Variables available: $chapter, $book, $extension
 */
use Qwiki\Core\Auth;
use Qwiki\Core\Config;

$baseDir = Config::getBaseDir();
$filePath = $baseDir . '/' . ($chapter['file'] ?? '');

if (!file_exists($filePath)) {
    echo "<div class='alert warning'>HTML file not found: " . htmlspecialchars($chapter['file'] ?? '') . "</div>";
    return;
}

$baseUrl = Config::getBaseUrl();
$isShare = !empty($_GET['share']);
$shareKeyVal = $isShare ? trim($_GET['share']) : ($chapter['shareKey'] ?? '');

if ($isShare && !empty($shareKeyVal)) {
    $rawUrl = $baseUrl . '?share=' . urlencode($shareKeyVal) . '&view=raw';
} else {
    $catSlug = $book['id'] ?? '';
    $docSlug = $chapter['slug'] ?? '';
    if (!empty($catSlug) && !empty($docSlug)) {
        $rawUrl = $baseUrl . urlencode($catSlug) . '/' . urlencode($docSlug) . '?view=raw';
    } else {
        $rawUrl = $baseUrl . '?doc=' . urlencode($docSlug) . '&view=raw';
    }
}

$isAdmin = Auth::isAdmin();
$status = strtolower($chapter['status'] ?? 'published');
?>
<div class="html-page-container">
    <div class="html-viewer-toolbar">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span class="badge badge-html">HTML</span>
            <span class="badge badge-status badge-status-<?= htmlspecialchars($status) ?>" id="qwiki-current-status-badge">
                <?= htmlspecialchars(ucfirst($status)) ?>
            </span>
            <span class="text-muted" style="font-size: 0.85rem;"><?= htmlspecialchars($chapter['title'] ?? 'HTML Document') ?></span>
        </div>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <button type="button" class="btn btn-outline btn-sm" id="btn-print-html-frame" title="Print document or save as PDF">
                🖨️ Print / Save as PDF
            </button>
            <?php if ($isAdmin): ?>
                <div class="dropdown status-dropdown">
                    <button type="button" class="btn btn-outline btn-sm" id="btn-status-dropdown-toggle" 
                            data-file="<?= htmlspecialchars($chapter['file'] ?? '') ?>" 
                            data-slug="<?= htmlspecialchars($chapter['slug'] ?? '') ?>" 
                            data-sharekey="<?= htmlspecialchars($chapter['shareKey'] ?? '') ?>" 
                            data-status="<?= htmlspecialchars($status) ?>"
                            title="Change Document Lifecycle Status">
                        Status: <strong id="status-current-label"><?= htmlspecialchars(ucfirst($status)) ?></strong> ▾
                    </button>
                    <div class="dropdown-menu" id="status-dropdown-menu">
                        <button type="button" class="dropdown-item status-action-item<?= $status === 'draft' ? ' active' : '' ?>" data-set-status="draft">
                            <span class="dropdown-item-icon">📝</span>
                            <span class="dropdown-item-text">Set to Draft</span>
                            <?php if ($status === 'draft'): ?><span class="status-check-indicator">✓</span><?php endif; ?>
                        </button>
                        <button type="button" class="dropdown-item status-action-item<?= $status === 'review' ? ' active' : '' ?>" data-set-status="review">
                            <span class="dropdown-item-icon">🔍</span>
                            <span class="dropdown-item-text">Set to In Review</span>
                            <?php if ($status === 'review'): ?><span class="status-check-indicator">✓</span><?php endif; ?>
                        </button>
                        <button type="button" class="dropdown-item status-action-item<?= $status === 'published' ? ' active' : '' ?>" data-set-status="published">
                            <span class="dropdown-item-icon">🚀</span>
                            <span class="dropdown-item-text">Set to Published (Release)</span>
                            <?php if ($status === 'published'): ?><span class="status-check-indicator">✓</span><?php endif; ?>
                        </button>
                        <button type="button" class="dropdown-item status-action-item<?= $status === 'archived' ? ' active' : '' ?>" data-set-status="archived">
                            <span class="dropdown-item-icon">📦</span>
                            <span class="dropdown-item-text">Set to Archived</span>
                            <?php if ($status === 'archived'): ?><span class="status-check-indicator">✓</span><?php endif; ?>
                        </button>
                    </div>
                </div>
                <button type="button" class="btn btn-primary btn-sm" id="btn-edit-html-doc" 
                        data-file="<?= htmlspecialchars($chapter['file'] ?? '') ?>" 
                        data-title="<?= htmlspecialchars($chapter['title'] ?? '') ?>">
                    ✏️ Edit HTML
                </button>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($rawUrl) ?>" target="_blank" class="btn btn-outline btn-sm">Open in Full Tab ↗</a>
        </div>
    </div>
    <iframe class="html-viewer-frame" id="current-html-frame" src="<?= htmlspecialchars($rawUrl) ?>" sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-forms allow-downloads allow-modals" title="HTML Document View"></iframe>
</div>

<?php if ($isAdmin): ?>
<!-- HTML Visual Editor Modal -->
<div class="modal-overlay" id="edit-html-modal">
    <div class="modal-card" style="max-width: 960px; width: 95vw; max-height: 92vh; display: flex; flex-direction: column;">
        <div class="modal-header">
            <h3>🌐 Edit HTML Document &mdash; <span id="edit-html-modal-title" style="font-weight: normal; font-size: 0.9em;"><?= htmlspecialchars($chapter['title'] ?? '') ?></span></h3>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <button type="button" class="btn btn-outline btn-sm btn-open-gallery" id="btn-edit-html-gallery" title="Browse and select images from gallery">🖼️ Gallery</button>
                <button class="modal-close" data-close="edit-html-modal">&times;</button>
            </div>
        </div>
        <form id="edit-html-form" style="display: flex; flex-direction: column; flex: 1; overflow: hidden;">
            <input type="hidden" name="file" id="edit-html-file" value="<?= htmlspecialchars($chapter['file'] ?? '') ?>">
            <div style="flex: 1; margin-bottom: 1rem; overflow-y: auto; display: flex; flex-direction: column;">
                <div style="display: flex; justify-content: flex-end; margin-bottom: 0.5rem;">
                    <label style="font-size: 0.85em; cursor: pointer; color: var(--text-muted);">
                        <input type="checkbox" id="edit-use-visual-editor" checked> Visual Editor
                    </label>
                </div>
                <textarea id="edit-html-textarea" name="content" style="width: 100%; flex: 1; min-height: 500px; font-family: monospace; resize: vertical;"></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--border-color);">
                <button type="button" class="btn btn-outline" data-close="edit-html-modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btn-save-html-doc">💾 Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
