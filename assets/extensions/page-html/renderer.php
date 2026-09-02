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

$fileUrl = htmlspecialchars($chapter['file'] ?? '');
$isAdmin = Auth::isAdmin();
?>
<div class="html-page-container">
    <div class="html-viewer-toolbar">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span class="badge badge-html">HTML</span>
            <span class="text-muted" style="font-size: 0.85rem;"><?= htmlspecialchars($chapter['title'] ?? 'HTML Document') ?></span>
        </div>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <?php if ($isAdmin): ?>
                <button type="button" class="btn btn-primary btn-sm" id="btn-edit-html-doc" 
                        data-file="<?= htmlspecialchars($chapter['file'] ?? '') ?>" 
                        data-title="<?= htmlspecialchars($chapter['title'] ?? '') ?>">
                    ✏️ Edit HTML
                </button>
            <?php endif; ?>
            <a href="<?= $fileUrl ?>" target="_blank" class="btn btn-outline btn-sm">Open in Full Tab ↗</a>
        </div>
    </div>
    <iframe class="html-viewer-frame" id="current-html-frame" src="<?= $fileUrl ?>" sandbox="allow-scripts allow-same-origin allow-popups allow-forms" title="HTML Document View"></iframe>
</div>

<?php if ($isAdmin): ?>
<!-- HTML Visual Editor Modal -->
<div class="modal-overlay" id="edit-html-modal">
    <div class="modal-card" style="max-width: 960px; width: 95vw; max-height: 92vh; display: flex; flex-direction: column;">
        <div class="modal-header">
            <h3>🌐 Edit HTML Document &mdash; <span id="edit-html-modal-title" style="font-weight: normal; font-size: 0.9em;"><?= htmlspecialchars($chapter['title'] ?? '') ?></span></h3>
            <button class="modal-close" data-close="edit-html-modal">&times;</button>
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
