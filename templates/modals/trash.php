<!-- Trash Bin Modal -->
<div class="modal-overlay" id="trash-modal" aria-labelledby="trash-modal-title">
    <div class="modal-card" style="max-width: 760px;">
        <div class="modal-header">
            <h3 id="trash-modal-title">🗑️ Trash Bin</h3>
            <button type="button" class="modal-close" data-close="trash-modal" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body" style="padding: 1rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap;">
                <span id="trash-modal-status" style="font-size: 0.88rem; color: var(--text-muted);">Loading trashed items...</span>
                <button type="button" class="btn btn-outline btn-danger-text btn-sm" id="btn-empty-trash" style="display: none;">Empty Trash Permanently</button>
            </div>
            <div id="trash-items-list" style="display: flex; flex-direction: column; gap: 0.65rem; max-height: 55vh; overflow-y: auto;"></div>
        </div>
    </div>
</div>
