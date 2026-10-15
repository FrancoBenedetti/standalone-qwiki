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
                <label class="form-label" for="book-description">Category Description (Optional)</label>
                <textarea name="description" id="book-description" class="form-control" rows="2" placeholder="Brief explanation of this category (shown as tooltip)..."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label" for="book-id-input">Category Folder / Slug (Optional)</label>
                <input type="text" name="id" id="book-id-input" class="form-control" placeholder="e.g. developer-guides">
            </div>
            <div class="form-group">
                <label class="form-label" for="book-parent-select">Parent Category</label>
                <select name="parentId" id="book-parent-select" class="form-control">
                    <option value="">-- Root (Top Level) --</option>
                    <?php foreach ($categoryHierarchy as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['id']) ?>">
                            <?= str_repeat('&nbsp;&nbsp;', $cat['depth']) ?><?= $cat['depth'] > 0 ? '↳ ' : '' ?><?= htmlspecialchars($cat['path']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Create Category</button>
        </form>
    </div>
</div>
