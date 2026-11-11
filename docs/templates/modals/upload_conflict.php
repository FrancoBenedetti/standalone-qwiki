<!-- Upload Duplicate Conflict Modal -->
<div class="modal-overlay" id="upload-conflict-modal">
    <div class="modal-card" style="max-width: 580px;">
        <div class="modal-header">
            <h3>⚠️ Document Already Exists</h3>
            <button class="modal-close" data-close="upload-conflict-modal">&times;</button>
        </div>
        <div style="padding: 0.75rem 0;">
            <p style="margin-bottom: 0.85rem; font-size: 0.95rem; line-height: 1.5;" id="upload-conflict-msg">
                A document or file with this identifier already exists in this wiki.
            </p>
            <div style="background-color: var(--bg-tertiary); padding: 0.85rem 1rem; border-radius: 6px; margin-bottom: 1.25rem; font-size: 0.88rem;">
                <div><strong>Existing Title:</strong> <span id="conflict-existing-title"></span></div>
                <div style="margin-top: 0.35rem;"><strong>Slug Identifier:</strong> <code id="conflict-existing-slug" style="color: var(--accent-color);"></code></div>
                <div style="margin-top: 0.35rem;"><strong>Category:</strong> <span id="conflict-existing-category"></span></div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                <!-- Option A: Replace -->
                <div id="conflict-replace-card" style="border: 1px solid var(--border-color); border-radius: 6px; padding: 0.85rem 1rem; background-color: var(--bg-secondary);">
                    <div style="font-weight: 600; margin-bottom: 0.25rem; font-size: 0.95rem;">1. Replace Existing Document</div>
                    <p style="font-size: 0.83rem; color: var(--text-muted); margin-bottom: 0.75rem; line-height: 1.4;">
                        Overwrites the content of the existing document with this uploaded file. All other settings (URL slug, category, custom themes, permissions, and sharing keys) will be preserved.
                    </p>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-conflict-replace">Replace Document Content</button>
                </div>

                <!-- Option B: Save as Copy -->
                <div id="conflict-copy-card" style="border: 1px solid var(--border-color); border-radius: 6px; padding: 0.85rem 1rem; background-color: var(--bg-secondary);">
                    <div style="font-weight: 600; margin-bottom: 0.25rem; font-size: 0.95rem;">2. Upload as Copy (Auto-Incremented Slug)</div>
                    <p style="font-size: 0.83rem; color: var(--text-muted); margin-bottom: 0.75rem; line-height: 1.4;">
                        Uploads this file as a new separate document under an incremented or custom URL slug so both versions exist side-by-side.
                    </p>
                    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                        <input type="text" id="conflict-new-slug" class="form-control" style="flex: 1; min-width: 180px; font-size: 0.85rem;" placeholder="new-slug-identifier">
                        <button type="button" class="btn btn-outline btn-sm" id="btn-conflict-rename">Upload as Copy</button>
                    </div>
                </div>
            </div>
        </div>
        <div style="margin-top: 1rem; text-align: right; border-top: 1px solid var(--border-color); padding-top: 0.75rem;">
            <button type="button" class="btn btn-outline btn-sm" data-close="upload-conflict-modal">Cancel Upload</button>
        </div>
    </div>
</div>
