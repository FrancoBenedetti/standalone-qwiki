<!-- Delete Non-Empty Category Confirmation Modal -->
<div class="modal-overlay" id="delete-category-confirm-modal" aria-labelledby="delete-cat-confirm-title">
    <div class="modal-card" style="max-width: 540px;">
        <div class="modal-header">
            <h3 id="delete-cat-confirm-title"><?= htmlspecialchars(__('cat.delete_nonempty_title', 'Delete Non-Empty Category')) ?></h3>
            <button type="button" class="modal-close" data-close="delete-category-confirm-modal" aria-label="<?= htmlspecialchars(__('common.close', 'Close')) ?>">&times;</button>
        </div>
        <form id="delete-category-confirm-form">
            <input type="hidden" id="delete-cat-confirm-id" value="">
            <div class="modal-body" style="padding: 1rem;">
                <p id="delete-cat-confirm-summary" style="margin-bottom: 1rem; font-size: 0.92rem; line-height: 1.5; color: var(--text-primary);"></p>

                <div class="form-group" style="margin-bottom: 0.85rem;">
                    <label style="display: flex; align-items: flex-start; gap: 0.6rem; cursor: pointer; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px;">
                        <input type="radio" name="deleteCatMode" value="trash" id="delete-cat-mode-trash" checked style="margin-top: 0.2rem;">
                        <span>
                            <strong><?= htmlspecialchars(__('cat.move_to_trash_title', 'Move category & contents to Trash')) ?></strong>
                            <small style="display: block; color: var(--text-muted); margin-top: 0.15rem;"><?= htmlspecialchars(__('cat.move_to_trash_desc', 'Files are safely archived in Trash Bin and can be restored later.')) ?></small>
                        </span>
                    </label>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: flex; align-items: flex-start; gap: 0.6rem; cursor: pointer; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px;">
                        <input type="radio" name="deleteCatMode" value="move_contents" id="delete-cat-mode-move" style="margin-top: 0.2rem;">
                        <span style="flex: 1;">
                            <strong><?= htmlspecialchars(__('cat.move_contents_title', 'Move contents to another category first')) ?></strong>
                            <small style="display: block; color: var(--text-muted); margin-top: 0.15rem;"><?= htmlspecialchars(__('cat.move_contents_desc', 'Relocate all documents and sub-categories, then remove this empty category.')) ?></small>
                            <select id="delete-cat-target-select" class="form-control" style="margin-top: 0.5rem;" disabled></select>
                        </span>
                    </label>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem;">
                    <button type="button" class="btn btn-outline" data-close="delete-category-confirm-modal"><?= htmlspecialchars(__('common.cancel', 'Cancel')) ?></button>
                    <button type="submit" class="btn btn-primary" id="btn-confirm-delete-cat"><?= htmlspecialchars(__('common.proceed', 'Proceed')) ?></button>
                </div>
            </div>
        </form>
    </div>
</div>
