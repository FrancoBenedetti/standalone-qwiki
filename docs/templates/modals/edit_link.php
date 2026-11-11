<!-- Edit Hyperlink Modal -->
<div class="modal-overlay" id="edit-link-modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Edit Hyperlink</h3>
            <button class="modal-close" data-close="edit-link-modal">&times;</button>
        </div>
        <form id="edit-link-form">
            <input type="hidden" name="slug" id="edit-link-slug-hidden">
            <input type="hidden" name="bookId" id="edit-link-book-hidden">
            <input type="hidden" name="type" value="link">
            <div class="form-group">
                <label class="form-label" for="edit-link-title">Link Title</label>
                <input type="text" name="title" id="edit-link-title" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="edit-link-url">Target URL</label>
                <input type="text" name="url" id="edit-link-url" class="form-control" required>
                <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.25rem; display: block;">External URLs open in a new tab automatically; same-domain URLs open in the same tab.</small>
            </div>
            <div class="form-group">
                <label class="form-label" for="edit-link-description">Description (Optional)</label>
                <input type="text" name="description" id="edit-link-description" class="form-control">
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem;">
                <button type="button" class="btn btn-outline btn-danger-text" id="btn-delete-link">🗑️ Delete Link</button>
                <button type="submit" class="btn btn-primary">Save Link Changes</button>
            </div>
        </form>
    </div>
</div>
