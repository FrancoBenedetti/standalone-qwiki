<!-- Add Category Modal -->
<div class="modal-overlay" id="book-modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Add New Category</h3>
            <button class="modal-close" data-close="book-modal">&times;</button>
        </div>
        <form id="add-book-form">
            <input type="hidden" name="isCustomSlug" id="book-is-custom-slug" value="0">
            <div class="form-group">
                <label class="form-label" for="book-title">Category Title</label>
                <input type="text" name="title" id="book-title" class="form-control" placeholder="e.g. Developer Guides" required autocomplete="off">
            </div>
            <div class="form-group">
                <label class="form-label" for="book-parent-select">Parent Category</label>
                <select name="parentId" id="book-parent-select" class="form-control">
                    <option value="" data-folder="content">-- Root (Top Level) --</option>
                    <?php foreach ($categoryHierarchy as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['id']) ?>" data-folder="<?= htmlspecialchars($cat['folder'] ?? ('content/' . $cat['id'])) ?>">
                            <?= str_repeat('&nbsp;&nbsp;', $cat['depth']) ?><?= $cat['depth'] > 0 ? '↳ ' : '' ?><?= htmlspecialchars($cat['path']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: baseline;">
                    <label class="form-label" for="book-id-input" style="margin-bottom: 0;">Category Folder / Slug</label>
                    <span id="book-slug-mode" style="font-size: 0.78rem; color: var(--text-muted);">(Auto-generated)</span>
                </div>
                <input type="text" name="id" id="book-id-input" class="form-control" placeholder="e.g. developer-guides" pattern="[a-z0-9\-]+" style="margin-top: 0.35rem;" autocomplete="off">
                <div style="margin-top: 0.4rem; font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap;">
                    <span>Folder storage path:</span>
                    <code id="book-path-preview" style="font-size: 0.82rem; color: var(--accent-color); font-weight: 500;">content/</code>
                </div>
                <small id="book-slug-feedback" style="display: block; margin-top: 0.25rem; font-size: 0.78rem;"></small>
            </div>
            <div class="form-group">
                <label class="form-label" for="book-description">Category Description (Optional)</label>
                <textarea name="description" id="book-description" class="form-control" rows="2" placeholder="Brief explanation of this category (shown as tooltip)..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary" id="btn-submit-add-book" style="width: 100%;">Create Category</button>
        </form>
    </div>
</div>
