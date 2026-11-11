<!-- Quick Replace Document Content Modal -->
<div class="modal-overlay" id="replace-document-modal">
    <div class="modal-card" style="max-width: 520px;">
        <div class="modal-header">
            <h3>Replace Document Content</h3>
            <button class="modal-close" data-close="replace-document-modal">&times;</button>
        </div>
        <form id="replace-document-form" enctype="multipart/form-data">
            <input type="hidden" name="slug" id="replace-document-slug" value="<?= htmlspecialchars($activeChapter['slug'] ?? '') ?>">
            <p style="margin-bottom: 1rem; font-size: 0.9rem; color: var(--text-secondary); line-height: 1.4;">
                Replace the content of <strong id="replace-document-title-display"><?= htmlspecialchars($activeChapter['title'] ?? '') ?></strong> with an updated file without modifying its URL slug, category, custom themes, or sharing settings.
            </p>
            <div class="form-group">
                <label class="form-label" for="replace-document-file-input">Select Replacement File (.md or .pdf)</label>
                <input type="file" name="document" id="replace-document-file-input" class="form-control" accept=".md,.pdf" required>
            </div>
            <button type="submit" class="btn btn-primary" id="btn-submit-replace-doc" style="width: 100%;">Upload &amp; Replace</button>
        </form>
    </div>
</div>
