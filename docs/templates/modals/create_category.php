<?php
$extManager = isset($extManager) ? $extManager : \Qwiki\Core\ExtensionManager::getInstance();
?>
<!-- Add Category Modal -->
<div class="modal-overlay" id="book-modal">
    <div class="modal-card" style="max-width: 600px;">
        <div class="modal-header">
            <h3><?= htmlspecialchars(__('cat.add_title', 'Add New Category')) ?></h3>
            <button class="modal-close" data-close="book-modal">&times;</button>
        </div>
        <form id="add-book-form">
            <div class="settings-tab-scroll">
                <input type="hidden" name="isCustomSlug" id="book-is-custom-slug" value="0">
            <div class="form-group">
                <label class="form-label" for="book-title"><?= htmlspecialchars(__('cat.title_label', 'Category Title')) ?></label>
                <input type="text" name="title" id="book-title" class="form-control" placeholder="<?= htmlspecialchars(__('cat.title_ph', 'e.g. Developer Guides')) ?>" required autocomplete="off">
            </div>
            <div class="form-group">
                <label class="form-label" for="book-parent-select"><?= htmlspecialchars(__('cat.parent_label', 'Parent Category')) ?></label>
                <select name="parentId" id="book-parent-select" class="form-control">
                    <option value="" data-folder="content"><?= htmlspecialchars(__('cat.root_level', '-- Root (Top Level) --')) ?></option>
                    <?php foreach ($categoryHierarchy as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['id']) ?>" data-folder="<?= htmlspecialchars($cat['folder'] ?? ('content/' . $cat['id'])) ?>">
                            <?= str_repeat('&nbsp;&nbsp;', $cat['depth']) ?><?= $cat['depth'] > 0 ? '↳ ' : '' ?><?= htmlspecialchars($cat['path']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: baseline;">
                    <label class="form-label" for="book-id-input" style="margin-bottom: 0;"><?= htmlspecialchars(__('cat.slug_label', 'Category Folder / Slug')) ?></label>
                    <span id="book-slug-mode" style="font-size: 0.78rem; color: var(--text-muted);"><?= htmlspecialchars(__('cat.auto_generated', '(Auto-generated)')) ?></span>
                </div>
                <input type="text" name="id" id="book-id-input" class="form-control" placeholder="e.g. developer-guides" pattern="[a-z0-9\-]+" style="margin-top: 0.35rem;" autocomplete="off">
                <div style="margin-top: 0.4rem; font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap;">
                    <span><?= htmlspecialchars(__('cat.storage_path', 'Folder storage path:')) ?></span>
                    <code id="book-path-preview" style="font-size: 0.82rem; color: var(--accent-color); font-weight: 500;">content/</code>
                </div>
                <small id="book-slug-feedback" style="display: block; margin-top: 0.25rem; font-size: 0.78rem;"></small>
            </div>
            <div class="form-group">
                <label class="form-label" for="book-description"><?= htmlspecialchars(__('cat.desc_label', 'Category Description (Optional)')) ?></label>
                <textarea name="description" id="book-description" class="form-control" rows="2" placeholder="<?= htmlspecialchars(__('cat.desc_ph', 'Brief explanation of this category (shown as tooltip)...')) ?>"></textarea>
            </div>
<?php if ($extManager->isExtensionEnabled('comments')): ?>
<div class="form-group" style="margin-top: 0.5rem;">
<input type="hidden" name="allowComments" value="0">
<label class="checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none;">
<input type="checkbox" name="allowComments" id="create-category-allow-comments" value="1">
<span>💬 <?= htmlspecialchars(__('cat.allow_comments', 'Allow signed-in users to comment on documents in this category (Disabled by default)')) ?></span>
</label>
</div>
<?php endif; ?>
<?php if ($extManager->isExtensionEnabled('talk')): ?>
<div class="form-group" style="margin-top: 0.5rem;">
<input type="hidden" name="allowTalk" value="0">
<label class="checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none;">
<input type="checkbox" name="allowTalk" id="create-category-allow-talk" value="1">
<span>🗣️ <?= htmlspecialchars(__('cat.allow_talk', 'Enable Discussion & Talk Pages for chapters in this category (Disabled by default)')) ?></span>
</label>
</div>
<?php endif; ?>
            </div>
            <div style="margin-top: 1rem;">
                <button type="submit" class="btn btn-primary" id="btn-submit-add-book" style="width: 100%;"><?= htmlspecialchars(__('cat.create_btn', 'Create Category')) ?></button>
            </div>
        </form>
    </div>
</div>
