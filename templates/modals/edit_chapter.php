<!-- Edit Document Metadata Modal -->
<div class="modal-overlay" id="edit-chapter-modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Edit Document Entry Details</h3>
            <button class="modal-close" data-close="edit-chapter-modal">&times;</button>
        </div>
        <form id="edit-chapter-form">
            <input type="hidden" name="slug" id="edit-chapter-slug-hidden" value="<?= htmlspecialchars($activeChapter['slug'] ?? '') ?>">
            <div class="form-group">
                <label class="form-label" for="edit-chapter-title">Document Title</label>
                <input type="text" name="title" id="edit-chapter-title" class="form-control" value="<?= htmlspecialchars($activeChapter['title'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="edit-chapter-slug">Document Slug (URL Identifier)</label>
                <input type="text" name="newSlug" id="edit-chapter-slug" class="form-control" value="<?= htmlspecialchars($activeChapter['slug'] ?? '') ?>" required pattern="[a-zA-Z0-9\-_]+">
                <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.25rem; display: block;">Unique identifier used in URLs. Changing this renames the file on disk.</small>
            </div>
            <div class="form-group">
                <label class="form-label" for="edit-chapter-category">Category / Folder</label>
                <select name="targetBookId" id="edit-chapter-category" class="form-control">
                    <?php foreach ($categoryHierarchy as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['id']) ?>" <?= ($currentCategoryId === $cat['id']) ? 'selected' : '' ?>>
                            <?= str_repeat('&nbsp;&nbsp;', $cat['depth']) ?><?= $cat['depth'] > 0 ? '↳ ' : '' ?><?= htmlspecialchars($cat['path']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
                <input type="text" name="file" id="edit-chapter-file" class="form-control" value="<?= htmlspecialchars($activeChapter['file'] ?? '') ?>" readonly style="background-color: var(--bg-tertiary); cursor: not-allowed;">
                <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.25rem; display: block;">Managed automatically by Qwiki based on category and slug.</small>
            </div>
            <div class="form-group" id="group-edit-replacement-file">
                <label class="form-label" for="edit-chapter-replacement-file">Replace Document Content (.md or .pdf)</label>
                <input type="file" name="replacement_file" id="edit-chapter-replacement-file" class="form-control" accept=".md,.pdf">
                <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.25rem; display: block;">Optional: Upload a replacement file to update content while preserving its slug, settings, and sharing keys.</small>
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
            <div class="form-group">
                <input type="hidden" name="publicShareable" value="0">
                <label class="checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none;">
                    <input type="checkbox" name="publicShareable" id="edit-chapter-public-shareable" value="1" <?= (!isset($activeChapter['publicShareable']) || !empty($activeChapter['publicShareable'])) ? 'checked' : '' ?>>
                    <span>Allow Public Sharing (open in full screen)</span>
                </label>
            </div>
            <div class="form-group" id="group-edit-share-key" style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--border-color);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                    <label class="form-label" style="margin: 0;">Unique Share Key</label>
                    <button type="button" class="btn btn-outline btn-sm btn-danger-text" id="btn-edit-modal-reset-key" style="font-size: 0.75rem; padding: 0.15rem 0.4rem;">Reset Key</button>
                </div>
                <input type="text" id="edit-chapter-share-key" class="form-control" value="<?= htmlspecialchars($activeChapter['shareKey'] ?? '') ?>" readonly style="font-family: monospace; font-size: 0.85rem;" placeholder="Generated automatically upon first share">
                <input type="hidden" name="regenerateShareKey" id="edit-chapter-regenerate-key" value="0">
            </div>
            <div class="form-group" id="group-edit-chapter-readonly" style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--border-color);">
                <input type="hidden" name="readOnly" value="0">
                <label class="checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none;">
                    <input type="checkbox" name="readOnly" id="edit-chapter-readonly-input" value="1">
                    <span>🔒 Lock Document (Prevent Deletion &amp; Edits)</span>
                </label>
                <small id="edit-chapter-readonly-help" style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.25rem; display: block;">Protected documents cannot be edited or deleted.</small>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Save Document Details</button>
        </form>
    </div>
</div>
