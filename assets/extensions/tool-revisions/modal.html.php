<div class="modal-overlay" id="ext-revisions-modal">
    <div class="modal-card" style="max-width: 780px;">
        <div class="modal-header">
            <h3>🕒 Document Revision History</h3>
            <button class="modal-close" data-close="ext-revisions-modal">&times;</button>
        </div>
        <div class="modal-body" style="padding: 1rem;">
            <div id="ext-revisions-status" style="margin-bottom: 0.75rem; font-size: 0.9rem; color: var(--text-muted);">
                Select a revision to preview or restore.
            </div>
            <div class="revisions-layout">
                <div id="ext-revisions-list" class="revisions-list"></div>
                <div class="revisions-preview">
                    <div class="revisions-preview-header">
                        <span id="ext-revisions-preview-title">Preview</span>
                        <button type="button" id="ext-revisions-restore-btn" class="btn btn-primary btn-sm" style="display:none;">Restore This Version</button>
                    </div>
                    <pre id="ext-revisions-preview-code" class="revisions-preview-code">Select a revision on the left.</pre>
                </div>
            </div>
        </div>
    </div>
</div>
