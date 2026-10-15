<!-- Edit Category Modal -->
<div class="modal-overlay" id="edit-book-modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Edit Category</h3>
            <button class="modal-close" data-close="edit-book-modal">&times;</button>
        </div>
        <form id="edit-book-form">
            <input type="hidden" name="bookId" id="edit-book-id-hidden">
            <div class="form-group">
                <label class="form-label" for="edit-book-title-input">Category Title</label>
                <input type="text" name="title" id="edit-book-title-input" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="edit-book-description-input">Category Description (Optional)</label>
                <textarea name="description" id="edit-book-description-input" class="form-control" rows="2" placeholder="Brief explanation of this category (shown as tooltip)..."></textarea>
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
            <div class="form-group" id="group-edit-book-readonly">
                <input type="hidden" name="readOnly" value="0">
                <label class="checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none;">
                    <input type="checkbox" name="readOnly" id="edit-book-readonly-input" value="1">
                    <span>🔒 Lock Category (Prevent Deletion)</span>
                </label>
                <small id="edit-book-readonly-help" style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.25rem; display: block;">Protected categories and categories containing protected documents cannot be deleted.</small>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem;">
                <div>
                    <button type="button" class="btn btn-outline btn-danger-text" id="btn-delete-book">🗑️ Delete Category</button>
                    <span id="edit-book-protected-notice" class="badge badge-secondary" title="This category is protected against deletion" style="display: none; align-items: center; gap: 0.35rem; padding: 0.4rem 0.65rem; font-size: 0.8rem; border-radius: 4px; opacity: 0.85;">
                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        <span id="edit-book-protected-label"><?= \Qwiki\Core\Config::isDemoMode() ? 'Protected Demo Category' : 'Protected Category' ?></span>
                    </span>
                </div>
                <button type="submit" class="btn btn-primary">Save Category Details</button>
            </div>
        </form>
    </div>
</div>
